<?php
require dirname(__DIR__) . '/app/offline.php';
$message = '';
try {
    $db = app_db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!app_check_csrf()) { http_response_code(403); throw new InvalidArgumentException('Recarregue o formulário.'); }
        if (app_input('action') === 'admin') {
            $email = 'admin@offline.test';
            if (!app_query($db,'SELECT email FROM admlogin WHERE email = ?',[$email])->get_result()->num_rows) app_query($db,'INSERT INTO admlogin(email,senha) VALUES(?,?)',[$email,password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['emailadm'] = $email;
            header('Location: ' . app_url('adm/'), true, 303); exit;
        }
        if (empty($_SESSION['email'])) { header('Location: '.app_url('login/')); exit; }
        $ref = offline_deposit($db,$_SESSION['email'],(float)app_input('amount'));
        header('Location: '.app_url('teste/pagamento.php?token='.urlencode($ref)),true,303); exit;
    }
} catch(InvalidArgumentException $e) { $message=$e->getMessage(); }
catch(Throwable $e) { http_response_code(503); $message='Não foi possível acessar o banco offline. Verifique o MySQL no XAMPP.'; }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Central de testes offline</title><link rel="stylesheet" href="<?= app_escape(app_url('adm/gateway/bxpay.css')) ?>"></head><body><main class="gateway-shell">
<a class="back-link" href="<?= app_escape(app_url()) ?>">← Página inicial</a><h1>Teste sem dinheiro real.</h1><p class="lead">Ambiente local do XAMPP. Contas, saldo e pagamentos usam um banco separado. Nenhuma chamada é enviada à BX Pay.</p>
<?php if($message): ?><p class="notice notice-error" role="alert"><?= app_escape($message) ?></p><?php endif; ?>
<div class="gateway-grid"><section class="gateway-card"><h2>Testar como jogador</h2>
<?php if(empty($_SESSION['email'])): ?><p>Cadastre uma conta ou entre com uma conta copiada para os testes.</p><p><a class="back-link" href="<?= app_escape(app_url('cadastrar/')) ?>">Criar conta →</a></p><a class="back-link" href="<?= app_escape(app_url('login/')) ?>">Entrar →</a>
<?php else: ?><p>Conta: <?= app_escape($_SESSION['email']) ?></p><form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="deposit"><label for="amount">Valor fictício</label><input id="amount" name="amount" type="number" min="5" max="10000" step="0.01" value="100" required><button>Simular depósito</button></form><p><a class="back-link" href="<?= app_escape(app_url('painel/')) ?>">Abrir painel do jogador →</a></p><?php endif; ?>
</section><section class="gateway-card"><h2>Testar o administrador</h2><p>Abra o painel com um administrador de demonstração. Esse acesso existe somente neste ambiente offline.</p><form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><button name="action" value="admin">Entrar no ADM de teste</button></form><p class="hint">Para testar suas credenciais administrativas, use o <a class="back-link" href="<?= app_escape(app_url('adm/login/')) ?>">login normal do ADM</a>.</p></section></div>
</main></body></html>
