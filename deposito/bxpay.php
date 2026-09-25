<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Cache-Control: no-store');
if (!isset($_SESSION['email'])) { header('Location: ../login/'); exit; }
require_once dirname(__DIR__) . '/app/auth.php';
$demoDb=app_db();$demoStmt=app_query($demoDb,'SELECT demo FROM appconfig WHERE email=?',[(string)$_SESSION['email']]);
$demoRow=$demoStmt->get_result()->fetch_assoc();$demoStmt->close();$demoDb->close();
if($demoRow && (string)($demoRow['demo']??'0')==='1'){header('Location: '.app_url('painel/'),true,303);exit;}
require dirname(__DIR__) . '/conectarbanco.php';
require_once dirname(__DIR__) . '/payments/bxpay_service.php';
$notice = ''; $deposit = null;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
    $reference = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
    $deposit = bxpay_row($db, $reference);
    if (!$deposit || $deposit['email'] !== $_SESSION['email']) { http_response_code(404); exit('Depósito não encontrado.'); }
    $_SESSION['deposit_csrf'] = $_SESSION['deposit_csrf'] ?? bin2hex(random_bytes(32));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['deposit_csrf'], $_POST['csrf'])) {
            http_response_code(403); $notice = 'Recarregue a página e tente novamente.';
        } else {
            $paid = bxpay_reconcile($db, BXPay::fromDb($db), $reference);
            $notice = $paid ? 'Pagamento confirmado! Seu saldo foi atualizado.' : 'O pagamento ainda não foi confirmado. Aguarde alguns instantes e confira novamente. Se já pagou, não faça outro PIX.';
            $deposit = bxpay_row($db, $reference);
        }
    }
} catch (Throwable $error) {
    http_response_code(503);
    $notice = 'Não foi possível consultar a confirmação agora. Se já pagou, aguarde e tente novamente; não faça outro PIX.';
}
$escape = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Seu depósito PIX</title><link rel="stylesheet" href="../adm/gateway/bxpay.css"></head>
<body><main class="gateway-shell" style="max-width:660px">
<a class="back-link" href="../painel/">← Voltar ao painel</a>
<h1>Depósito via PIX</h1>
<?php if ($notice): ?><p class="notice" role="status"><?= $escape($notice) ?></p><?php endif; ?>
<?php if ($deposit): ?>
<section class="gateway-card">
<span class="eyebrow">BX PAY</span><h2>R$ <?= number_format((float) $deposit['amount'], 2, ',', '.') ?></h2>
<?php if ($deposit['status'] === 'PAID_OUT'): ?>
<p class="notice notice-success">Pagamento confirmado. O valor já está no seu saldo.</p><a class="back-link" href="../painel/">Ir para meu painel →</a>
<?php elseif ($deposit['status'] === 'PENDING'): ?>
<p>Copie o código abaixo. No aplicativo do seu banco, escolha <strong>PIX → Copia e Cola</strong> e confira o valor antes de pagar.</p>
<label for="pix-code">PIX copia e cola</label><textarea id="pix-code" readonly rows="5" style="width:100%;padding:14px;border-radius:10px;resize:vertical"><?= $escape($deposit['pix_code']) ?></textarea>
<button id="copy-pix" type="button">Copiar código PIX</button><p id="copy-feedback" role="status"></p>
<form method="post"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['deposit_csrf']) ?>"><button class="secondary" type="submit">Já paguei — conferir depósito</button></form>
<p class="hint">A confirmação consulta sua cobrança na BX Pay. Aguarde pelo menos 20 segundos entre as consultas.</p>
<?php else: ?><p>A geração desta cobrança precisa ser conferida. Entre em contato com o suporte antes de tentar outro depósito.</p><?php endif; ?>
<p class="hint">Referência: <?= $escape($deposit['reference']) ?></p>
</section>
<?php endif; ?>
</main><script>
const copy = document.getElementById('copy-pix');
if (copy) copy.addEventListener('click', async () => {
 const input = document.getElementById('pix-code');
 const feedback = document.getElementById('copy-feedback');
 try { await navigator.clipboard.writeText(input.value); feedback.textContent = 'Código copiado! Abra o aplicativo do seu banco.'; }
 catch (_) { input.focus(); input.select(); feedback.textContent = 'Selecione e copie o código acima.'; }
});
</script></body></html>
