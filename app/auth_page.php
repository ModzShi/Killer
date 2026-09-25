<?php
require_once __DIR__ . '/auth.php';
$admin = $authMode === 'admin'; $register = $authMode === 'register';
$destination = app_url($admin ? 'adm/' : 'painel/');
if (!empty($_SESSION[$admin ? 'emailadm' : 'email'])) { header('Location: ' . $destination); exit; }
$error = ''; $email = trim(app_input('email')); $phone = app_input('telefone_confirmation');
try {
    $db = app_db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!app_check_csrf()) { http_response_code(403); $error = 'O formulário expirou. Tente novamente.'; }
        elseif (($_SESSION['auth_wait_until'] ?? 0) > time()) { http_response_code(429); $error = 'Aguarde um minuto antes de tentar novamente.'; }
        else {
            if ($register) $email = app_register($db, ['email'=>$email,'senha'=>app_input('senha'),'telefone_confirmation'=>$phone,'password_confirmation'=>app_input('password_confirmation')], is_string($_GET['aff']??null) ? $_GET['aff'] : '', is_string($_GET['ref']??null) ? $_GET['ref'] : '');
            if (app_signin($db, strtolower($email), app_input('senha'), $admin)) {
                unset($_SESSION['auth_failures'], $_SESSION['auth_wait_until']);
                header('Location: ' . $destination, true, 303); exit;
            }
            $_SESSION['auth_failures'] = ($_SESSION['auth_failures'] ?? 0) + 1;
            if ($_SESSION['auth_failures'] >= 8) { $_SESSION['auth_wait_until'] = time() + 60; $_SESSION['auth_failures'] = 0; }
            $error = 'E-mail ou senha incorretos, ou conta indisponível.';
        }
    }
} catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
catch (Throwable $e) { error_log('auth page: ' . $e->getMessage()); http_response_code(503); $error = 'Não foi possível acessar o banco de dados. Tente novamente em instantes.'; }
$title = $register ? 'Crie sua conta' : ($admin ? 'Acesso administrativo' : 'Entre na sua conta');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $title ?></title><link rel="stylesheet" href="<?= app_escape(app_url('adm/gateway/bxpay.css')) ?>"></head>
<body><main class="gateway-shell" style="max-width:540px">
<a class="back-link" href="<?= app_escape(app_url()) ?>">← Voltar ao início</a><h1><?= $title ?></h1>
<p class="lead"><?= $admin ? 'Gerencie usuários, configurações e pagamentos.' : 'Acesse seu painel para continuar.' ?></p>
<section class="gateway-card">
<?php if ($error): ?><p class="notice notice-error" role="alert"><?= app_escape($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>">
<label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" maxlength="254" value="<?= app_escape($email) ?>" required>
<?php if ($register): ?><label for="telefone">Telefone com DDD</label><input id="telefone" name="telefone_confirmation" type="tel" autocomplete="tel" value="<?= app_escape($phone) ?>" required><?php endif; ?>
<label for="senha">Senha</label><input id="senha" name="senha" type="password" autocomplete="<?= $register ? 'new-password' : 'current-password' ?>" <?= $register ? 'minlength="6" maxlength="72"' : '' ?> required>
<?php if ($register): ?><label for="confirmar">Confirme sua senha</label><input id="confirmar" name="password_confirmation" type="password" autocomplete="new-password" minlength="6" maxlength="72" required><p class="hint">Ao se cadastrar, você declara ter pelo menos 18 anos e concordar com os <a style="color:inherit" href="<?= app_escape(app_url('legal/')) ?>">termos de uso</a>.</p><?php endif; ?>
<label class="enable-option"><input id="show-password" type="checkbox"> Mostrar senha</label>
<button type="submit"><?= $register ? 'Criar conta' : 'Entrar' ?></button></form>
<?php if (!$admin): ?><p><a class="back-link" href="<?= app_escape(app_url($register ? 'login/' : 'cadastrar/')) ?>"><?= $register ? 'Já tenho conta — entrar' : 'Não tenho conta — cadastrar' ?></a></p><?php endif; ?>
</section></main><script>document.getElementById('show-password').addEventListener('change',function(){for(const id of ['senha','confirmar']){const el=document.getElementById(id);if(el)el.type=this.checked?'text':'password';}});</script></body></html>
