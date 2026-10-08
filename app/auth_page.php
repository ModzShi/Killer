<?php
require_once __DIR__ . '/auth.php';
$admin = $authMode === 'admin'; $register = $authMode === 'register';
$nextMode = !$admin && is_string($_GET['next'] ?? $_POST['next'] ?? null) ? ($_GET['next'] ?? $_POST['next']) : '';
$modeDestinations = ['trader' => 'modo-trader/', 'bubble' => 'modo-bubble/painel', 'consulta' => 'modo-consulta/'];
$destination = app_url($admin ? 'adm/' : ($modeDestinations[$nextMode] ?? 'painel/'));
if (!empty($_SESSION[$admin ? 'emailadm' : 'email'])) { header('Location: ' . $destination); exit; }
$error = ''; $identifier = trim(app_input($admin ? 'email' : 'telefone')); $phone = app_input('telefone'); $name = trim(app_input('nome'));
try {
    $db = app_db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!app_check_csrf()) { http_response_code(403); $error = 'O formulário expirou. Tente novamente.'; }
        elseif (($_SESSION['auth_wait_until'] ?? 0) > time()) { http_response_code(429); $error = 'Aguarde um minuto antes de tentar novamente.'; }
        else {
            if ($register) {
                $managerCode=is_string($_GET['ref']??null)?$_GET['ref']:(string)($_SESSION['landing_manager_code']??'');
                app_register($db, ['nome'=>$name,'senha'=>app_input('senha'),'telefone'=>$phone], is_string($_GET['aff']??null) ? $_GET['aff'] : (string)($_SESSION['landing_affiliate']??''), $managerCode, null, is_string($_GET['by']??null) ? $_GET['by'] : (string)($_SESSION['landing_manager_influencer']??''));
                if($managerCode!=='') { require_once __DIR__.'/manager.php'; try { manager_pushcut_flush($db); } catch(Throwable $pushError) { error_log('manager signup notification: '.$pushError->getMessage()); } }
            }
            if (app_signin($db, $register ? $phone : $identifier, app_input('senha'), $admin)) {
                unset($_SESSION['auth_failures'], $_SESSION['auth_wait_until']);
                if ($register) unset($_SESSION['landing_affiliate'], $_SESSION['landing_manager_code'], $_SESSION['landing_manager_influencer']);
                header('Location: ' . $destination, true, 303); exit;
            }
            $_SESSION['auth_failures'] = ($_SESSION['auth_failures'] ?? 0) + 1;
            if ($_SESSION['auth_failures'] >= 8) { $_SESSION['auth_wait_until'] = time() + 60; $_SESSION['auth_failures'] = 0; }
            $error = $admin ? 'E-mail ou senha incorretos, ou conta indisponível.' : 'Telefone ou senha incorretos, ou conta indisponível.';
        }
    }
} catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
catch (Throwable $e) { error_log('auth page: ' . $e->getMessage()); http_response_code(503); $error = 'Não foi possível acessar o banco de dados. Tente novamente em instantes.'; }
$title = $register ? 'Crie sua conta' : ($admin ? 'Acesso administrativo' : 'Entre na sua conta');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#0a0819"><title><?= app_escape($title) ?> · Subway Run</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= app_escape(app_url('adm/gateway/bxpay.css')) ?>"><link rel="stylesheet" href="<?= app_escape(app_url('arquivos/auth-page.css')) ?>?v=<?= filemtime(SK_ROOT . '/arquivos/auth-page.css') ?>"></head>
<body class="sk-auth-page<?= $admin ? ' sk-auth-page--admin' : '' ?>"><main class="gateway-shell sk-auth-shell">
<a class="back-link sk-auth-back" href="<?= app_escape(app_url()) ?>"><span aria-hidden="true">←</span> Voltar ao início</a>
<header class="sk-auth-intro"><a class="sk-auth-brand" href="<?= app_escape(app_url()) ?>"><span class="sk-auth-brand-mark" aria-hidden="true"><?= ui_icon('play') ?></span><span>Subway Run<small>PLAY. RUN. REPEAT.</small></span></a><p class="sk-auth-kicker"><?= $admin ? 'ÁREA RESTRITA' : ($register ? 'COMECE SUA JORNADA' : 'BEM-VINDO DE VOLTA') ?></p><h1><?= app_escape($title) ?></h1>
<p class="lead"><?= $admin ? 'Gerencie usuários, configurações e pagamentos.' : ($register ? 'Crie seu perfil para acompanhar partidas e acessar sua conta.' : 'Entre para abrir seu painel e continuar de onde parou.') ?></p></header>
<section class="gateway-card">
<?php if ($error): ?><p class="notice notice-error" role="alert"><?= app_escape($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><?php if (isset($modeDestinations[$nextMode])): ?><input type="hidden" name="next" value="<?= app_escape($nextMode) ?>"><?php endif; ?>
<?php if ($register): ?><label for="nome">Nome</label><input id="nome" name="nome" type="text" autocomplete="name" maxlength="120" minlength="2" placeholder="Seu nome completo" value="<?= app_escape($name) ?>" required><?php endif; ?>
<?php if (!$admin): ?><label for="telefone"><?= $register ? 'Telefone com DDD (somente números)' : 'Telefone (somente números)' ?></label><input id="telefone" name="telefone" type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="11" pattern="[0-9]{10,11}" placeholder="11999990000" value="<?= app_escape($phone !== '' ? $phone : ($register ? '' : $identifier)) ?>" required>
<?php else: ?><label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" maxlength="254" value="<?= app_escape($identifier) ?>" required><?php endif; ?>
<label for="senha">Senha</label><input id="senha" name="senha" type="password" autocomplete="<?= $register ? 'new-password' : 'current-password' ?>" <?= $register ? 'minlength="6" maxlength="72"' : '' ?> required>
<?php if ($register): ?><p class="hint">Ao se cadastrar, você declara ter pelo menos 18 anos e concordar com os <a style="color:inherit" href="<?= app_escape(app_url('legal/')) ?>">termos de uso</a>.</p><?php endif; ?>
<?php if (!$register): ?><label class="enable-option"><input type="checkbox" name="remember_me" value="1" checked> Lembrar</label><?php endif; ?>
<label class="enable-option"><input id="show-password" type="checkbox"> Mostrar senha</label>
<button type="submit"><?= $register ? 'Criar conta' : 'Entrar' ?></button></form>
<?php if (!$admin && $register): ?><p><a class="back-link" href="<?= app_escape(app_url('login/' . (isset($modeDestinations[$nextMode]) ? '?next=' . $nextMode : ''))) ?>">Já tenho conta — entrar</a></p><?php elseif (!$admin): ?><p><a class="back-link" href="<?= app_escape(app_url('cadastrar/' . (isset($modeDestinations[$nextMode]) ? '?next=' . $nextMode : ''))) ?>">Ainda não tem conta? Criar conta</a></p><?php endif; ?>
</section></main><script>document.querySelectorAll('input[name="telefone"]').forEach(input=>input.addEventListener('input',()=>{input.value=input.value.replace(/\D/g,'').slice(0,11)}));document.getElementById('show-password').addEventListener('change',function(){const el=document.getElementById('senha');if(el)el.type=this.checked?'text':'password';});</script></body></html>
