<?php
require_once __DIR__.'/game.php';
require_once __DIR__.'/withdrawal.php';
require_once __DIR__.'/../components/icons.php';
function account_user(mysqli $db): array {
    if(empty($_SESSION['email'])) { header('Location: '.app_url('login/')); exit; }
    $user=app_query($db,'SELECT * FROM appconfig WHERE email=?',[$_SESSION['email']])->get_result()->fetch_assoc();
    if(!$user) { header('Location: '.app_url('login/')); exit; }
    return $user;
}
function account_update_profile(mysqli $db, array $input): void {
    $email=(string)($_SESSION['email']??'');
    if($email==='') throw new RuntimeException('Sua sessão expirou. Entre novamente.');
    $name=trim((string)($input['nome']??''));
    $name=preg_replace('/\s+/u',' ',$name)??$name;
    $phone=app_phone_normalize((string)($input['telefone']??''));
    if(!preg_match('/^.{2,120}$/usD',$name)) throw new InvalidArgumentException('Informe seu nome completo (de 2 a 120 caracteres).');
    if(!preg_match('/^\d{10,11}$/D',$phone)) throw new InvalidArgumentException('Informe um telefone válido com DDD.');

    $newPassword=(string)($input['nova_senha']??'');
    $changePassword=$newPassword!=='';
    $account=app_query($db,'SELECT senha,demo FROM appconfig WHERE email=? LIMIT 1',[$email])->get_result()->fetch_assoc();
    if(!$account) throw new RuntimeException('Conta não encontrada.');
    if($changePassword){
        $currentPassword=(string)($input['senha_atual']??'');
        $confirmation=(string)($input['confirmar_senha']??'');
        if(!app_password_matches($currentPassword,(string)$account['senha'])) throw new InvalidArgumentException('A senha atual está incorreta.');
        if(strlen($newPassword)<8||strlen($newPassword)>72) throw new InvalidArgumentException('Use uma nova senha entre 8 e 72 caracteres.');
        if(!hash_equals($newPassword,$confirmation)) throw new InvalidArgumentException('A confirmação da nova senha não confere.');
    }

    if($changePassword)app_auth_remember_install($db);
    $newHash=$changePassword?password_hash($newPassword,PASSWORD_DEFAULT):'';
    $db->begin_transaction();
    try {
        if(app_query($db,'SELECT id FROM appconfig WHERE telefone IN (?,?) AND email<>? LIMIT 1 FOR UPDATE',[$phone,'55'.$phone,$email])->get_result()->fetch_assoc()) throw new InvalidArgumentException('Este telefone já está vinculado a outra conta.');
        app_query($db,'UPDATE appconfig SET nome=?,telefone=? WHERE email=?',[$name,$phone,$email]);
        if((string)($account['demo']??'0')==='1'){
            $table=app_query($db,'SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['manager_demos'])->get_result()->fetch_assoc();
            if((int)($table['n']??0)>0) app_query($db,'UPDATE manager_demos SET display_name=? WHERE email=?',[$name,$email]);
        }
        if($changePassword){
            app_query($db,'UPDATE appconfig SET senha=? WHERE email=?',[$newHash,$email]);
            app_query($db,'DELETE FROM auth_remember_tokens WHERE scope=? AND subject=?',['player',$email]);
        }
        $db->commit();
    }catch(Throwable $error){$db->rollback();throw $error;}
    if($changePassword){
        $_SESSION['player_auth_hash']=app_password_fingerprint($newHash);
        try{app_auth_remember($db,'player',$email,!empty($_COOKIE['SK_REMEMBER_PLAYER']));}
        catch(Throwable $error){error_log('remember token after password change: '.$error->getMessage());app_auth_remember_clear_cookie('player');}
    }
}
function account_money($value): string { return 'R$ '.number_format((float)($value??0),2,',','.'); }
function account_status(string $status): string {
    return ['PAID_OUT'=>'Confirmado','PAID'=>'Pago','WIN'=>'Vitória','LOSS'=>'Derrota','PLAYING'=>'Em andamento','PENDING'=>'Em análise','WAITING_FOR_APPROVAL'=>'Aguardando pagamento','EXPIRED'=>'Expirado','CANCELLED'=>'Cancelado'][$status]??$status;
}
function account_mask(string $email): string { preg_match('/^(.{0,2})/us',explode('@',$email)[0],$prefix);return ($prefix[1]??'').'•••'; }
