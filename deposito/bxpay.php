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
            if (!hash_equals($_SESSION['deposit_csrf'], $postedCsrf)) {
                http_response_code(403);
                $notice = 'Sua sessão expirou. Atualize a página e tente novamente.';
            } else {
                $paid = bxpay_reconcile($db, BXPay::fromDb($db), $reference);
                $notice = $paid
                    ? 'Pagamento confirmado! Seu saldo foi atualizado.'
                    : 'O pagamento ainda não foi confirmado. Aguarde e confira novamente. Se já pagou, não faça outro PIX.';
                $deposit = bxpay_row($db, $reference);
            }
        }
    }
} catch (Throwable $error) {
    http_response_code(503);
    $notice = 'Não foi possível consultar a confirmação agora. Se já pagou, aguarde e tente novamente; não gere outro PIX.';
} finally {
    if ($db instanceof mysqli) $db->close();
}
$escape = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#090b1a"><title>PIX | Subway Run</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:24px 14px 48px;background:radial-gradient(ellipse at 50% 0,#38206b85,transparent 48%),#090b1a;color:#f8f8ff;font-family:Inter,"Segoe UI",Arial,sans-serif}.pix-shell{width:100%;max-width:560px;margin:0 auto}.pix-back{display:inline-flex;align-items:center;gap:8px;margin:4px 0 20px;color:#c8c1e8;text-decoration:none;font-size:13px;font-weight:750}.pix-back:hover{color:#fff}.pix-card{padding:clamp(18px,5vw,28px);border:1px solid #a78bfa40;border-radius:24px;background:linear-gradient(145deg,#17172ff5,#101426f5);box-shadow:0 20px 55px #0006,inset 0 1px #ffffff10}.pix-brand{margin:0;color:#8eead7;font-size:10px;font-weight:900;letter-spacing:.17em}.pix-title{margin:7px 0 0;font-size:clamp(25px,7vw,34px);line-height:1.1;letter-spacing:-.04em}.pix-total{margin:18px 0;padding:15px 16px;border:1px solid #34dfbc42;border-radius:16px;background:linear-gradient(110deg,#0c352f,#102b37 75%,#171d3b)}.pix-total small{display:block;color:#bbd8d6;font-size:10px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.pix-total strong{display:block;margin-top:3px;color:#5df0ca;font-size:30px;font-variant-numeric:tabular-nums}.pix-copy{display:block;width:100%;min-height:126px;padding:13px;border:1px solid #a78bfa50;border-radius:14px;background:#0b1022;color:#f2efff;font:500 13px/1.55 ui-monospace,Consolas,monospace;overflow-wrap:anywhere;resize:vertical}.pix-label{display:block;margin:18px 0 8px;color:#e6e8f5;font-size:12px;font-weight:850}.pix-button{display:flex;align-items:center;justify-content:center;width:100%;min-height:48px;margin-top:12px;padding:11px 15px;border:1px solid #a4ffe6;border-radius:14px;background:linear-gradient(105deg,#4df0c2,#16d7aa 52%,#75efa4);color:#062b26;font-size:14px;font-weight:900;cursor:pointer}.pix-button:hover{filter:brightness(1.06)}.pix-button.secondary{border-color:#a78bfa55;background:#211d43;color:#f5f1ff}.pix-instructions{margin:14px 0 0;color:#bbc3df;font-size:12px;line-height:1.55}.pix-notice{margin:0 0 16px;padding:12px 14px;border:1px solid #ffd86d55;border-radius:13px;background:#45361955;color:#ffedb0;font-size:13px;line-height:1.5}.pix-notice.success{border-color:#45e6c255;background:#103d35;color:#a9f8de}.pix-feedback{min-height:18px;margin:8px 0 0;color:#a7f1d9;font-size:12px;text-align:center}.pix-reference{margin:18px 0 0;color:#9ca6c3;font-size:10px;overflow-wrap:anywhere}.pix-reference strong{color:#cbd2e8}@media(max-width:420px){body{padding:18px 11px 32px}.pix-card{border-radius:20px}.pix-total strong{font-size:27px}}
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
<p class="pix-instructions">Copie o código e, no aplicativo do seu banco, escolha <strong>PIX Copia e Cola</strong>. Confira o valor antes de confirmar.</p>
<label class="pix-label" for="pix-code">Código PIX</label><textarea class="pix-copy" id="pix-code" readonly rows="5"><?= $escape($deposit['pix_code']) ?></textarea>
<button class="pix-button" id="copy-pix" type="button">Copiar código PIX</button><p class="pix-feedback" id="copy-feedback" role="status" aria-live="polite"></p>
<form method="post"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['deposit_csrf']) ?>"><button class="pix-button secondary" type="submit">Já paguei · conferir status</button></form>
<p class="pix-instructions">A confirmação é consultada na BX Pay. Aguarde pelo menos 20 segundos entre consultas. Se já pagou, não gere outro PIX.</p>
<?php else: ?><p class="pix-notice" role="status">Esta cobrança precisa ser conferida. Fale com o suporte antes de tentar outro depósito.</p><?php endif; ?>
<p class="pix-reference">Referência: <strong><?= $escape($deposit['reference']) ?></strong></p>
</section>
<?php endif; ?>
</main><script>
const copy = document.getElementById('copy-pix');
if (copy) copy.addEventListener('click', async () => {
 const input = document.getElementById('pix-code');
 const feedback = document.getElementById('copy-feedback');
 try { await navigator.clipboard.writeText(input.value); feedback.textContent = 'Código copiado. Abra o aplicativo do seu banco.'; }
 catch (_) { input.focus(); input.select(); feedback.textContent = 'Selecione e copie o código acima.'; }
});
</script></body></html>
