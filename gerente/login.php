<?php
require_once __DIR__.'/../app/manager.php';
$db=app_db();manager_install($db);
if(manager_current($db)){header('Location: '.app_url('gerente/'));exit;}
$error='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!app_check_csrf())$error='Formulário expirado. Recarregue a página.';
    elseif(($_SESSION['manager_wait_until']??0)>time())$error='Aguarde um minuto antes de tentar novamente.';
    elseif(manager_login($db,app_input('email'),app_input('password'))){unset($_SESSION['manager_failures'],$_SESSION['manager_wait_until']);header('Location: '.app_url('gerente/'),true,303);exit;}
    else{$_SESSION['manager_failures']=($_SESSION['manager_failures']??0)+1;if($_SESSION['manager_failures']>=8){$_SESSION['manager_wait_until']=time()+60;$_SESSION['manager_failures']=0;}$error='E-mail ou senha incorretos, ou acesso indisponível.';}
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Entrar • Gerente</title><link rel="stylesheet" href="<?=app_escape(app_url('gerente/manager.css'))?>?v=5"><link rel="stylesheet" href="<?=app_escape(app_url('gerente/mobile.css'))?>?v=3"></head><body class="login"><main class="login-card"><div class="logo">SUBWAY <span>RUN</span></div><span class="kicker">ÁREA EXCLUSIVA</span><h1>Painel do gerente</h1><p>Gerencie seus convites, acompanhe os resultados e crie acessos de demonstração.</p><?php if($error):?><div class="notice error" role="alert"><?=app_escape($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=app_escape(app_csrf())?>"><label>E-mail<input name="email" type="email" autocomplete="username" required></label><label>Senha<input name="password" type="password" autocomplete="current-password" required></label><button class="primary">Entrar no painel <?=ui_icon('arrow')?></button></form><a class="back" href="<?=app_escape(app_url())?>">← Voltar ao site</a></main></body></html>
