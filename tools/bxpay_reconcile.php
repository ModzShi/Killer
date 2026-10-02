<?php
declare(strict_types=1);

// Schedule this file with PHP CLI. It must never be callable over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/payments/bxpay_service.php';
require_once dirname(__DIR__) . '/app/auth.php';

function bxpay_run_scheduled(): int
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = null;
    $lockHeld = false;
    try {
        $db = app_db();
        if (!bxpay_enabled($db)) return 0;
        $lock = $db->query("SELECT GET_LOCK('subway_bxpay_reconcile', 0)")->fetch_row();
        if ((int) ($lock[0] ?? 0) !== 1) return 0;
        $lockHeld = true;

        // Rotate through recent pending deposits without repeatedly scanning the same ones.
        $pending = $db->query("SELECT reference, provider_id, pix_code, amount FROM bxpay_deposits
            WHERE status = 'PENDING' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY last_checked IS NULL DESC, last_checked ASC, created_at DESC LIMIT 100")
            ->fetch_all(MYSQLI_ASSOC);
        if (!$pending) return 0;

        $checked = $db->prepare('UPDATE bxpay_deposits SET last_checked = NOW() WHERE reference = ?');
        foreach ($pending as $deposit) {
            $checked->bind_param('s', $deposit['reference']);
            $checked->execute();
        }

        $api = BXPay::fromDb($db);
        $confirmed = 0;
        for ($page = 1; $page <= 5 && $pending; $page++) {
            $rows = bxpay_transactions($api->listarTransacoes($page, 100));
            foreach ($rows as $transaction) {
                if (!is_array($transaction)) continue;
                foreach ($pending as $key => $deposit) {
                    if (!bxpay_matches($deposit, $transaction)) continue;
                    if (bxpay_credit($db, $deposit['reference'], $transaction)) {
                        ++$confirmed;
                        unset($pending[$key]);
                    }
                    break;
                }
            }
            if (count($rows) < 100) break;
        }
        echo "Depósitos confirmados: $confirmed\n";
        return 0;
    } catch (Throwable $error) {
        error_log('BX Pay scheduled reconciliation: ' . $error->getMessage());
        fwrite(STDERR, "Não foi possível concluir a conciliação BX Pay. Verifique o log do PHP.\n");
        return 1;
    } finally {
        if ($db instanceof mysqli) {
            if ($lockHeld) $db->query("SELECT RELEASE_LOCK('subway_bxpay_reconcile')");
            $db->close();
        }
    }
}

exit(bxpay_run_scheduled());
