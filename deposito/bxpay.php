<?php
require_once __DIR__ . '/../app/auth.php';
require_once dirname(__DIR__) . '/payments/bxpay_service.php';
header('Cache-Control: no-store, private');

if (empty($_SESSION['email'])) {
    header('Location: ' . app_url('login/'), true, 303);
    exit;
}

$email = (string) $_SESSION['email'];
$notice = '';
$deposit = null;
$reference = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$db = null;
try {
    $db = app_db();
    $user = app_query($db, 'SELECT demo FROM appconfig WHERE email=? LIMIT 1', [$email])->get_result()->fetch_assoc();
    if (!$user || (string) ($user['demo'] ?? '0') === '1') {
        $db->close();
        header('Location: ' . app_url('painel/'), true, 303);
        exit;
    }
    $deposit = bxpay_row($db, $reference);
    if (!$deposit || $deposit['email'] !== $email) {
        http_response_code(404);
        $notice = 'Não foi possível localizar este depósito.';
    } else {
        $_SESSION['deposit_csrf'] = $_SESSION['deposit_csrf'] ?? bin2hex(random_bytes(32));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $postedCsrf = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
            $jsonRequest = ($_POST['action'] ?? '') === 'auto_check';
            if (!hash_equals($_SESSION['deposit_csrf'], $postedCsrf)) {
                http_response_code(403);
                $notice = 'Sua sessão expirou. Atualize a página e tente novamente.';
            } else {
                if (($_POST['action'] ?? '') === 'cancel' && $deposit['status'] === 'PENDING') {
                    $db->begin_transaction();
                    $cancel = $db->prepare("UPDATE bxpay_deposits SET status = 'CANCELED' WHERE reference = ? AND email = ? AND status = 'PENDING'");
                    $cancel->bind_param('ss', $reference, $email); $cancel->execute();
                    if ($cancel->affected_rows === 1) {
                        $update = $db->prepare("UPDATE confirmar_deposito SET status = 'CANCELED' WHERE externalreference = ? AND email = ? AND status = 'WAITING_FOR_APPROVAL'");
                        $update->bind_param('ss', $reference, $email); $update->execute();
                    }
                    $db->commit();
                    header('Location: ' . app_url('deposito/'), true, 303);
                    exit;
                }
                $paid = $deposit['status'] === 'PAID_OUT';
                if (!$paid && in_array($deposit['status'], ['PENDING', 'CANCELED'], true)) {
                    // A remote request must not hold the PHP session lock for other open pages.
                    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
                    $paid = bxpay_reconcile($db, BXPay::fromDb($db), $reference);
                }
                if (!$jsonRequest) $notice = $paid
                    ? 'Pagamento confirmado! Seu saldo foi atualizado.'
                    : 'Pagamento ainda pendente. Se já pagou, aguarde alguns instantes: a consulta automática continuará por até 10 minutos.';
                $deposit = bxpay_row($db, $reference);
            }
        }
    }
} catch (Throwable $error) {
    error_log('BX Pay deposit confirmation: ' . $error->getMessage());
    http_response_code(503);
    $notice = 'Não foi possível consultar a confirmação agora. Se já pagou, aguarde e tente novamente; não gere outro PIX.';
} finally {
    if ($db instanceof mysqli) $db->close();
}
if (isset($_GET['status']) || (($_POST['action'] ?? '') === 'auto_check')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['paid' => $deposit !== null && $deposit['status'] === 'PAID_OUT', 'status' => $deposit['status'] ?? null, 'message' => $notice]);
    exit;
}
$escape = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#090b1a"><title>PIX | Subway Run</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:24px 14px 48px;background:radial-gradient(ellipse at 50% 0,#38206b85,transparent 48%),#090b1a;color:#f8f8ff;font-family:Inter,"Segoe UI",Arial,sans-serif}.pix-shell{width:100%;max-width:560px;margin:0 auto}.pix-back{display:inline-flex;align-items:center;gap:8px;margin:4px 0 20px;color:#c8c1e8;text-decoration:none;font-size:13px;font-weight:750}.pix-back:hover{color:#fff}.pix-card{padding:clamp(18px,5vw,28px);border:1px solid #a78bfa40;border-radius:24px;background:linear-gradient(145deg,#17172ff5,#101426f5);box-shadow:0 20px 55px #0006,inset 0 1px #ffffff10}.pix-brand{margin:0;color:#8eead7;font-size:10px;font-weight:900;letter-spacing:.17em}.pix-title{margin:7px 0 0;font-size:clamp(25px,7vw,34px);line-height:1.1;letter-spacing:-.04em}.pix-total{margin:18px 0;padding:15px 16px;border:1px solid #34dfbc42;border-radius:16px;background:linear-gradient(110deg,#0c352f,#102b37 75%,#171d3b)}.pix-total small{display:block;color:#bbd8d6;font-size:10px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.pix-total strong{display:block;margin-top:3px;color:#5df0ca;font-size:30px;font-variant-numeric:tabular-nums}.pix-copy{display:block;width:100%;min-height:126px;padding:13px;border:1px solid #a78bfa50;border-radius:14px;background:#0b1022;color:#f2efff;font:500 13px/1.55 ui-monospace,Consolas,monospace;overflow-wrap:anywhere;resize:vertical}.pix-label{display:block;margin:18px 0 8px;color:#e6e8f5;font-size:12px;font-weight:850}.pix-button{display:flex;align-items:center;justify-content:center;width:100%;min-height:48px;margin-top:12px;padding:11px 15px;border:1px solid #a4ffe6;border-radius:14px;background:linear-gradient(105deg,#4df0c2,#16d7aa 52%,#75efa4);color:#062b26;font-size:14px;font-weight:900;cursor:pointer}.pix-button:hover{filter:brightness(1.06)}.pix-button.secondary{border-color:#a78bfa55;background:#211d43;color:#f5f1ff}.pix-instructions{margin:14px 0 0;color:#bbc3df;font-size:12px;line-height:1.55}.pix-notice{margin:0 0 16px;padding:12px 14px;border:1px solid #ffd86d55;border-radius:13px;background:#45361955;color:#ffedb0;font-size:13px;line-height:1.5}.pix-notice.success{border-color:#45e6c255;background:#103d35;color:#a9f8de}.pix-feedback{min-height:18px;margin:8px 0 0;color:#a7f1d9;font-size:12px;text-align:center}.pix-reference{margin:18px 0 0;color:#9ca6c3;font-size:10px;overflow-wrap:anywhere}.pix-reference strong{color:#cbd2e8}@media(max-width:420px){body{padding:18px 11px 32px}.pix-card{border-radius:20px}.pix-total strong{font-size:27px}}
</style><style>
.pix-qr-card{display:grid;justify-items:center;gap:10px;margin:18px 0 6px;padding:16px;border:1px solid #a78bfa45;border-radius:18px;background:linear-gradient(145deg,#211c42,#12182d)}.pix-qr-card strong{color:#f5f1ff;font-size:13px}.pix-qr{display:grid;place-items:center;width:min(252px,100%);aspect-ratio:1;padding:10px;border-radius:14px;background:#fff}.pix-qr canvas,.pix-qr img{display:block;max-width:100%;height:auto!important}.pix-qr-error{margin:0;color:#c8c8dc;font-size:12px;text-align:center}.pix-wait{margin:16px 0;padding:14px;border:1px solid #67e8c67a;border-radius:15px;background:#13363780}.pix-wait-top{display:flex;justify-content:space-between;gap:8px;color:#eafffa;font-size:12px;font-weight:800}.pix-wait-track{height:7px;margin-top:11px;border-radius:99px;background:#ffffff21;overflow:hidden}.pix-wait-track span{display:block;width:0;height:100%;border-radius:inherit;background:linear-gradient(90deg,#55e3c5,#fee174);box-shadow:0 0 12px #58efc9}.pix-wait small{display:block;margin-top:8px;color:#c6d8d9;font-size:11px}.pix-cancel{display:block;width:100%;margin-top:9px;padding:10px;border:0;background:none;color:#d4caea;font-size:12px;text-decoration:underline;cursor:pointer}
.pix-dialog{width:min(390px,calc(100% - 28px));padding:0;border:1px solid #b59afb82;border-radius:22px;background:linear-gradient(145deg,#231b42,#11172b);color:#f8f8ff;box-shadow:0 28px 80px #000b,0 0 35px #7f55ee4d}.pix-dialog::backdrop{background:#050716bc;backdrop-filter:blur(6px)}.pix-dialog-inner{padding:24px}.pix-dialog-icon{display:grid;place-items:center;width:43px;height:43px;border:1px solid #ffdb835e;border-radius:14px;background:#ffcf621a;color:#ffe39d;font-size:22px}.pix-dialog h2{margin:14px 0 7px;font-size:23px;line-height:1.1}.pix-dialog p{margin:0;color:#cbd1e9;font-size:13px;line-height:1.55}.pix-dialog-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:20px}.pix-dialog-actions button{min-height:43px;border:1px solid #8a79ba;border-radius:12px;background:#252044;color:#fff;font-weight:850;cursor:pointer}.pix-dialog-actions .pix-dialog-danger{border-color:#ffb2a3;background:linear-gradient(110deg,#ff746e,#d94968);color:#250d19}.pix-dialog-actions button:hover{filter:brightness(1.1)}
</style></head>
<body><main class="pix-shell">
<a class="pix-back" href="<?= app_escape(app_url('painel/')) ?>">← Voltar ao painel</a>
<?php if ($notice): ?><p class="pix-notice" role="status"><?= $escape($notice) ?></p><?php endif; ?>
<?php if ($deposit): ?>
<section class="pix-card" aria-labelledby="pix-heading">
<p class="pix-brand">SUBWAY RUN · PAGAMENTO</p><h1 class="pix-title" id="pix-heading">Depósito via PIX</h1>
<div class="pix-total"><small>Valor do depósito</small><strong>R$ <?= number_format((float) $deposit['amount'], 2, ',', '.') ?></strong></div>
<?php if ($deposit['status'] === 'PAID_OUT'): ?>
<p class="pix-notice success">Pagamento confirmado. O saldo já está disponível no seu painel.</p><a class="pix-button" href="<?= app_escape(app_url('painel/')) ?>">Voltar ao painel</a>
<?php elseif ($deposit['status'] === 'PENDING'): ?>
<div class="pix-wait" data-pix-pending data-url="<?= $escape(app_url('deposito/bxpay.php?token=' . rawurlencode($reference))) ?>" data-csrf="<?= $escape($_SESSION['deposit_csrf']) ?>" data-age="<?= (int) $deposit['age_seconds'] ?>"><div class="pix-wait-top"><span>PIX aguardando pagamento</span><span data-pix-time>10:00</span></div><div class="pix-wait-track"><span data-pix-progress></span></div><small data-pix-label>Verificando automaticamente por até 10 minutos.</small></div>
<p class="pix-instructions">Copie o código e, no aplicativo do seu banco, escolha <strong>PIX Copia e Cola</strong>. Confira o valor antes de confirmar.</p>
<label class="pix-label" for="pix-code">Código PIX</label><textarea class="pix-copy" id="pix-code" readonly rows="5"><?= $escape($deposit['pix_code']) ?></textarea>
<div class="pix-qr-card"><strong>Escaneie com o app do seu banco</strong><div class="pix-qr" id="pix-qr" role="img" aria-label="QR Code do pagamento PIX"></div><p class="pix-qr-error" id="pix-qr-error" hidden>O QR Code não carregou. Você ainda pode copiar o código PIX acima.</p></div>
<button class="pix-button" id="copy-pix" type="button">Copiar código PIX</button><p class="pix-feedback" id="copy-feedback" role="status" aria-live="polite"></p>
<form method="post"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['deposit_csrf']) ?>"><button class="pix-button secondary" type="submit">Já paguei · conferir agora</button></form>
<form method="post" id="pix-cancel-form"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['deposit_csrf']) ?>"><input type="hidden" name="action" value="cancel"><button class="pix-cancel" id="pix-cancel-open" type="button">Cancelar esta cobrança</button></form>
<dialog class="pix-dialog" id="pix-cancel-dialog" aria-labelledby="pix-cancel-title"><div class="pix-dialog-inner"><span class="pix-dialog-icon" aria-hidden="true">!</span><h2 id="pix-cancel-title">Cancelar este PIX?</h2><p>Você poderá gerar um novo código. Se já pagou no banco, este cancelamento não desfaz a transferência e o pagamento ainda poderá ser confirmado.</p><div class="pix-dialog-actions"><button type="button" id="pix-cancel-close">Voltar</button><button type="submit" form="pix-cancel-form" class="pix-dialog-danger">Sim, cancelar</button></div></div></dialog>
<p class="pix-instructions">Se você pagou, aguarde a confirmação antes de gerar outro PIX. Cancelar aqui libera uma nova cobrança, mas não cancela uma transferência já feita no banco.</p>
<?php elseif ($deposit['status'] === 'CANCELED'): ?>
<p class="pix-instructions">Esta cobrança foi cancelada no site. Se você já pagou o código PIX, o pagamento ainda pode ser confirmado e creditado.</p><form method="post"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['deposit_csrf']) ?>"><button class="pix-button secondary" type="submit">Conferir pagamento anterior</button></form><a class="pix-button" href="<?= app_escape(app_url('deposito/')) ?>">Novo depósito</a>
<?php else: ?><p class="pix-notice" role="status">Esta cobrança precisa ser conferida. Fale com o suporte antes de tentar outro depósito.</p><?php endif; ?>
<p class="pix-reference">Referência: <strong><?= $escape($deposit['reference']) ?></strong></p>
</section>
<?php endif; ?>
</main><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><script>
const qrTarget = document.getElementById('pix-qr');
const pixField = document.getElementById('pix-code');
if (qrTarget && pixField && window.QRCode) {
 new QRCode(qrTarget, {text: pixField.value, width: 232, height: 232, colorDark: '#11152a', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M});
} else if (qrTarget) {
 document.getElementById('pix-qr-error').hidden = false;
}
const copy = document.getElementById('copy-pix');
if (copy) copy.addEventListener('click', async () => {
 const input = document.getElementById('pix-code');
 const feedback = document.getElementById('copy-feedback');
 try { await navigator.clipboard.writeText(input.value); feedback.textContent = 'Código copiado. Abra o aplicativo do seu banco.'; }
 catch (_) { input.focus(); input.select(); feedback.textContent = 'Selecione e copie o código acima.'; }
});
const cancelDialog = document.getElementById('pix-cancel-dialog');
document.getElementById('pix-cancel-open')?.addEventListener('click', () => cancelDialog?.showModal());
document.getElementById('pix-cancel-close')?.addEventListener('click', () => cancelDialog?.close());
cancelDialog?.addEventListener('click', event => { if (event.target === cancelDialog) cancelDialog.close(); });
</script><script src="<?= $escape(app_url('arquivos/pending-pix.js')) ?>?v=<?= filemtime(dirname(__DIR__) . '/arquivos/pending-pix.js') ?>" defer></script></body></html>
