<?php
require_once __DIR__ . '/../app/bootstrap.php';

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
    $stmt = $db->prepare('SELECT * FROM bxpay_deposits WHERE reference = ?');
    $stmt->bind_param('s', $reference); $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/** Match only an exact reference/id and gross amount from the authenticated API. */
function bxpay_matches(array $deposit, array $transaction): bool
{
    $external = $transaction['external_id'] ?? null;
    $id = $transaction['id'] ?? ($transaction['transactionId'] ?? null);
    $referenceMatches = is_string($external) && hash_equals($deposit['reference'], $external);
    $idMatches = !empty($deposit['provider_id']) && is_scalar($id) && hash_equals($deposit['provider_id'], (string) $id);
    if (!$referenceMatches && !$idMatches) return false;
    if (!empty($deposit['provider_id']) && is_scalar($id) && !hash_equals($deposit['provider_id'], (string) $id)) return false;
    if (isset($transaction['currency']) && $transaction['currency'] !== 'BRL') return false;
    $type = $transaction['type'] ?? ($transaction['transactionType'] ?? '');
    if (!in_array($type, ['DEPOSIT', 'RECEIVEPIX'], true) || ($transaction['status'] ?? '') !== 'PAID') return false;
    $amount = $transaction['amount'] ?? null;
    return is_numeric($amount) && is_finite((float) $amount)
        && abs((float) $amount - (float) $deposit['amount']) < 0.000001;
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
        if ($deposit['status'] !== 'PENDING') { $db->rollback(); return false; }
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
            $stmt = $db->prepare('SELECT plano, afiliado_ativo, cpa FROM appconfig WHERE id = ? FOR UPDATE');
            $stmt->bind_param('s', $user['afiliado']); $stmt->execute();
            $affiliate = $stmt->get_result()->fetch_assoc();
            if ($affiliate) {
                $commission = 0; $count = 0;
                if ($affiliate['afiliado_ativo'] == '1') {
                    $commission = round((float) $amount * max(0, (float) $affiliate['plano']) / 100, 2);
                } elseif (($user['status_primeiro_deposito'] ?? '0') == '0') {
                    $app = $db->query('SELECT deposito_min_cpa FROM app LIMIT 1')->fetch_assoc();
                    if ((float) $amount >= (float) ($app['deposito_min_cpa'] ?? 0)) {
                        $commission = max(0, (float) $affiliate['cpa']); $count = $commission > 0 ? 1 : 0;
                    }
                }
                if ($commission > 0) {
                    $stmt = $db->prepare('UPDATE appconfig SET comissaofake = comissaofake + ?, cont_cpa = cont_cpa + ? WHERE id = ?');
                    $stmt->bind_param('dis', $commission, $count, $user['afiliado']); $stmt->execute();
                }
            }
        }
        try { manager_commission_record($db,$reference,$email,(float)$amount); }
        catch(mysqli_sql_exception $e) { if($e->getCode()!==1146) throw $e; }
        $stmt = $db->prepare("UPDATE bxpay_deposits SET status = 'PAID_OUT' WHERE reference = ?");
        $stmt->bind_param('s', $reference); $stmt->execute();
        $stmt = $db->prepare("UPDATE confirmar_deposito SET status = 'PAID_OUT' WHERE externalreference = ? AND email = ?");
        $stmt->bind_param('ss', $reference, $email); $stmt->execute();
        $db->commit(); return true;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

function bxpay_reconcile(mysqli $db, BXPay $api, string $reference): bool
{
    $deposit = bxpay_row($db, $reference);
    if (!$deposit) return false;
    if ($deposit['status'] === 'PAID_OUT') return true;
    if ($deposit['status'] !== 'PENDING') return false;
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
