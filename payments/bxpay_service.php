<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once dirname(__DIR__) . '/app/affiliate.php';

require_once dirname(__DIR__) . '/bxpay.php';

function bxpay_install(mysqli $db): void
{
    $db->query('CREATE TABLE IF NOT EXISTS bxpay_config (id TINYINT UNSIGNED PRIMARY KEY, client_id VARCHAR(255) NOT NULL, client_secret VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->query('CREATE TABLE IF NOT EXISTS bxpay_settings (id TINYINT UNSIGNED PRIMARY KEY, enabled TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
    $db->query("CREATE TABLE IF NOT EXISTS bxpay_deposits (
        reference VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        provider_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
        email VARCHAR(255) NOT NULL, amount DECIMAL(12,2) NOT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'CREATING', pix_code TEXT NULL,
        last_checked DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function bxpay_enabled(mysqli $db): bool
{
    try { return (int) ($db->query('SELECT enabled FROM bxpay_settings WHERE id = 1')->fetch_assoc()['enabled'] ?? 0) === 1; }
    catch (mysqli_sql_exception $e) { if ($e->getCode() === 1146) return false; throw $e; }
}

function bxpay_row(mysqli $db, string $reference): ?array
{
    $stmt = $db->prepare('SELECT *, UNIX_TIMESTAMP(created_at) AS created_epoch FROM bxpay_deposits WHERE reference = ?');
    $stmt->bind_param('s', $reference); $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function bxpay_active_for_email(mysqli $db, string $email): ?array
{
    $stmt = $db->prepare("SELECT reference, amount, status, created_at, UNIX_TIMESTAMP(created_at) AS created_epoch FROM bxpay_deposits WHERE email = ? AND status = 'PENDING' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param('s', $email); $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/** Read the merchant transaction ID (field 62/05) from a PIX EMV payload. */
function bxpay_pix_txid(?string $pix): ?string
{
    if (!is_string($pix) || strlen($pix) > 4096 || !preg_match('/^000201[^\r\n]+6304[0-9a-fA-F]{4}$/D', $pix)) return null;
    $readFields = static function (string $data): ?array {
        $fields = [];
        $offset = 0;
        $total = strlen($data);
        while ($offset < $total) {
            if ($total - $offset < 4) return null;
            $tag = substr($data, $offset, 2);
            $size = substr($data, $offset + 2, 2);
            if (!ctype_digit($tag) || !ctype_digit($size)) return null;
            $offset += 4;
            $length = (int) $size;
            if ($length > $total - $offset || isset($fields[$tag])) return null;
            $fields[$tag] = substr($data, $offset, $length);
            $offset += $length;
        }
        return $fields;
    };
    $outer = $readFields(substr($pix, 0, -8));
    if ($outer === null || !isset($outer['62'])) return null;
    $additional = $readFields($outer['62']);
    $txid = $additional['05'] ?? null;
    return is_string($txid) && preg_match('/^[A-Za-z0-9]{12,35}$/D', $txid) ? $txid : null;
}

/** Match only an exact reference/id and gross amount from the authenticated API. */
function bxpay_matches(array $deposit, array $transaction): bool
{
    $external = $transaction['external_id'] ?? null;
    $txid = bxpay_pix_txid($deposit['pix_code'] ?? null);
    $referenceMatches = is_string($external) && hash_equals($deposit['reference'], $external);
    $pixMatches = is_string($external) && $txid !== null && hash_equals($txid, $external);
    $description = $transaction['description'] ?? ($transaction['descricao'] ?? null);
    $descriptionMatches = is_string($description) && hash_equals('Depósito ' . $deposit['reference'], $description);
    $idMatches = false;
    if (!empty($deposit['provider_id'])) {
        foreach (['transactionId', 'transaction_id', 'id'] as $key) {
            if (isset($transaction[$key]) && is_scalar($transaction[$key])
                && hash_equals((string) $deposit['provider_id'], (string) $transaction[$key])) {
                $idMatches = true;
                break;
            }
        }
    }
    if (!$referenceMatches && !$pixMatches && !$descriptionMatches && !$idMatches) return false;
    if (isset($transaction['currency']) && $transaction['currency'] !== 'BRL') return false;
    $typeValue = $transaction['type'] ?? ($transaction['transactionType'] ?? null);
    $statusValue = $transaction['status'] ?? null;
    if (!is_string($typeValue) || !is_string($statusValue)) return false;
    $type = strtoupper(trim($typeValue));
    $status = strtoupper(trim($statusValue));
    if (!in_array($type, ['DEPOSIT', 'RECEIVEPIX'], true) || $status !== 'PAID') return false;
    $amount = $transaction['amount'] ?? null;
    return is_numeric($amount) && is_finite((float) $amount)
        && abs((float) $amount - (float) $deposit['amount']) < 0.000001;
}

/** A webhook only locates a local deposit; payment is always checked against the API. */
function bxpay_webhook_reference(mysqli $db, string $external, string $id): ?string
{
    $stmt = $db->prepare('SELECT reference FROM bxpay_deposits WHERE reference = ? OR provider_id = ? LIMIT 1');
    $stmt->bind_param('ss', $external, $id);
    $stmt->execute();
    $direct = $stmt->get_result()->fetch_assoc();
    if ($direct) return (string) $direct['reference'];
    if (!preg_match('/^[A-Za-z0-9]{12,35}$/D', $external)) return null;
    $pattern = '%' . $external . '%';
    $stmt = $db->prepare("SELECT reference, pix_code FROM bxpay_deposits WHERE status IN ('PENDING','CANCELED','PAID_OUT') AND pix_code LIKE ? LIMIT 3");
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $matches = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        if (bxpay_pix_txid($row['pix_code']) === $external) $matches[] = $row['reference'];
    }
    return count($matches) === 1 ? (string) $matches[0] : null;
}

function bxpay_transactions(array $response): array
{
    if (!empty($response['_error'])) throw new RuntimeException('A BX Pay não respondeu à consulta. Tente novamente em instantes.');
    $rows = $response['transactions'] ?? ($response['data']['transactions'] ?? ($response['data'] ?? null));
    if (!is_array($rows) || ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1))) {
        throw new RuntimeException('Formato do extrato não reconhecido. Contate o suporte para conferir seu pagamento.');
    }
    return $rows;
}

/** Atomic credit, serialized by deposit and player; duplicate callbacks are harmless. */
function bxpay_credit(mysqli $db, string $reference, array $verified): bool
{
    require_once dirname(__DIR__) . '/app/manager.php';
    manager_install($db);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT * FROM bxpay_deposits WHERE reference = ? FOR UPDATE');
        $stmt->bind_param('s', $reference); $stmt->execute();
        $deposit = $stmt->get_result()->fetch_assoc();
        if (!$deposit || !bxpay_matches($deposit, $verified)) { $db->rollback(); return false; }
        if ($deposit['status'] === 'PAID_OUT') { $db->commit(); return true; }
        if (!in_array($deposit['status'], ['PENDING', 'CANCELED'], true)) { $db->rollback(); return false; }
        $email = $deposit['email']; $amount = $deposit['amount'];
        $stmt = $db->prepare('SELECT * FROM appconfig WHERE email = ? FOR UPDATE');
        $stmt->bind_param('s', $email); $stmt->execute();
        $users = $stmt->get_result();
        if ($users->num_rows !== 1) throw new RuntimeException('Conta do depósito não encontrada.');
        $user = $users->fetch_assoc();
        if ((string)($user['demo']??'0') === '1') throw new RuntimeException('Conta demo não aceita depósitos.');
        $stmt = $db->prepare('UPDATE appconfig SET saldo = saldo + ?, depositou = depositou + ?, status_primeiro_deposito = 1 WHERE email = ?');
        $stmt->bind_param('sss', $amount, $amount, $email); $stmt->execute();
        $managerReferral = app_query($db, 'SELECT partner_id FROM manager_referrals WHERE email=?', [$email])->get_result()->fetch_assoc();
        if (!$managerReferral && !empty($user['afiliado'])) {
            $stmt = $db->prepare('SELECT id FROM appconfig WHERE id = ? FOR UPDATE');
            $stmt->bind_param('s', $user['afiliado']); $stmt->execute();
            $affiliate = $stmt->get_result()->fetch_assoc();
            if ($affiliate) {
                $commission = app_affiliate_deposit_commission((float)$amount);
                if ($commission > 0) {
                    $stmt = $db->prepare('UPDATE appconfig SET comissaofake = comissaofake + ?, cont_cpa = cont_cpa + ? WHERE id = ?');
                    $noCpaCount = 0;
                    $stmt->bind_param('dis', $commission, $noCpaCount, $user['afiliado']); $stmt->execute();
                }
            }
        }
        try { manager_commission_record($db,$reference,$email,(float)$amount); }
        catch(mysqli_sql_exception $e) { if($e->getCode()!==1146) throw $e; }
        $stmt = $db->prepare("UPDATE bxpay_deposits SET status = 'PAID_OUT' WHERE reference = ?");
        $stmt->bind_param('s', $reference); $stmt->execute();
        $stmt = $db->prepare("UPDATE confirmar_deposito SET status = 'PAID_OUT' WHERE externalreference = ? AND email = ?");
        $stmt->bind_param('ss', $reference, $email); $stmt->execute();
        $db->commit();
        try { manager_pushcut_flush($db); } catch(Throwable $pushError) { error_log('manager deposit notification: '.$pushError->getMessage()); }
        return true;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

function bxpay_reconcile(mysqli $db, BXPay $api, string $reference): bool
{
    $deposit = bxpay_row($db, $reference);
    if (!$deposit) return false;
    if ($deposit['status'] === 'PAID_OUT') return true;
    if (!in_array($deposit['status'], ['PENDING', 'CANCELED'], true)) return false;
    // A shared database throttle also limits unauthenticated callback triggers.
    $stmt = $db->prepare('UPDATE bxpay_deposits SET last_checked = NOW() WHERE reference = ? AND (last_checked IS NULL OR last_checked < DATE_SUB(NOW(), INTERVAL 20 SECOND))');
    $stmt->bind_param('s', $reference); $stmt->execute();
    if ($stmt->affected_rows !== 1) return false;
    // Bounded lookup; never match by amount or payer name alone.
    for ($page = 1; $page <= 5; $page++) {
        $rows = bxpay_transactions($api->listarTransacoes($page, 100));
        foreach ($rows as $row) {
            if (is_array($row) && bxpay_matches($deposit, $row)) return bxpay_credit($db, $reference, $row);
        }
        if (count($rows) < 100) break;
    }
    return false;
}
