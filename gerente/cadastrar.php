<?php
require_once __DIR__ . '/../app/manager.php';
$db=app_db();manager_install($db);
if(manager_current($db)){header('Location: '.app_url('gerente/'),true,303);exit;}
$error='';$name=app_input('name');$email=app_input('email');
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!app_check_csrf()){$error='Formulário expirado. Recarregue a página.';http_response_code(403);}
    else{
        $ipKey='ip:'.hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
        if(!app_auth_allowed($db,'manager-signup',$ipKey)){$error='Muitas tentativas de cadastro. Tente novamente mais tarde.';http_response_code(429);}
        else{
            app_auth_failed($db,'manager-signup',$ipKey);
            try{
                $managerId=manager_register($db,$name,$email,app_input('password'),app_input('password_confirmation'));
                app_auth_clear($db,'manager-signup',$ipKey);
                session_regenerate_id(true);
                $_SESSION['manager_id']=$managerId;
                app_auth_remember($db,'manager',(string)$managerId,!empty($_POST['remember_me']));
                header('Location: '.app_url('gerente/'),true,303);exit;
            }catch(InvalidArgumentException $exception){$error=$exception->getMessage();}
            catch(Throwable $exception){error_log('manager signup: '.$exception->getMessage());http_response_code(503);$error='Não foi possível criar o acesso agora. Tente novamente.';}
        }
    }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Criar acesso de gerente · Subway Run</title><link rel="stylesheet" href="<?=app_escape(app_url('gerente/manager.css'))?>?v=5"><link rel="stylesheet" href="<?=app_escape(app_url('gerente/mobile.css'))?>?v=3"></head><body class="login"><main class="login-card"><div class="logo">SUBWAY <span>RUN</span></div><span class="kicker">CADASTRO DE GERENTE</span><h1>Criar acesso</h1><p>Cadastre seu acesso individual ao painel de gerente.</p><?php if($error):?><div class="notice error" role="alert"><?=app_escape($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=app_escape(app_csrf())?>"><label>Nome<input name="name" type="text" autocomplete="name" maxlength="120" value="<?=app_escape($name)?>" required></label><label>E-mail<input name="email" type="email" autocomplete="username" maxlength="254" value="<?=app_escape($email)?>" required></label><label>Senha<input name="password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required></label><label>Confirme a senha<input name="password_confirmation" type="password" autocomplete="new-password" minlength="10" maxlength="72" required></label><label class="remember-option"><input type="checkbox" name="remember_me" value="1" checked> Manter conectado neste dispositivo por 30 dias</label><button class="primary">Criar conta de gerente <?=ui_icon('arrow')?></button></form><a class="back" href="<?=app_escape(app_url('gerente/login.php'))?>">Já tenho acesso · Entrar no painel</a><a class="back" href="<?=app_escape(app_url())?>">← Voltar ao site</a></main></body></html>
