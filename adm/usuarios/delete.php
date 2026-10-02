<?php
require_once __DIR__.'/../../app/auth.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
$db=app_db();
try{
    if(!app_check_csrf())throw new InvalidArgumentException('Formulário expirado.');
    $id=app_input('id');
    if(!ctype_digit($id)||$id==='0')throw new InvalidArgumentException('Usuário inválido.');
    $admin=app_query($db,'SELECT senha FROM admlogin WHERE email=?',[(string)$_SESSION['emailadm']])->get_result()->fetch_assoc();
    if(!$admin||!app_password_matches(app_input('admin_password'),(string)$admin['senha']))throw new InvalidArgumentException('Senha do administrador incorreta.');
    $db->query('CREATE TABLE IF NOT EXISTS deleted_user_ids (id BIGINT UNSIGNED PRIMARY KEY, deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    app_auth_remember_install($db);
    $db->begin_transaction();
    try{
        $user=app_query($db,'SELECT id,email,demo,saldo,depositou,total_apostado,comissaofake FROM appconfig WHERE id=? FOR UPDATE',[$id])->get_result()->fetch_assoc();
        if(!$user)throw new InvalidArgumentException('Usuário não encontrado.');
        $email=(string)$user['email'];
        if((string)$user['demo']!=='1'&&((float)$user['saldo']>0||(float)$user['depositou']>0||(float)$user['total_apostado']>0||(float)$user['comissaofake']>0))throw new InvalidArgumentException('Esta conta tem saldo ou movimentação. Bloqueie o acesso para preservar o histórico financeiro.');
        if(app_query($db,'SELECT id FROM appconfig WHERE afiliado=? LIMIT 1',[$id])->get_result()->fetch_assoc())throw new InvalidArgumentException('Esta conta possui indicados. Bloqueie o acesso para preservar os vínculos.');
        $checks=[
            ['game_rounds','email'],['saques','email'],['saque_afiliado','email'],
            ['bxpay_deposits','email'],['confirmar_deposito','email'],
            ['manager_referrals','email'],['manager_demo_balance_log','email'],['manager_commissions','influencer_email'],
            ['manager_commissions','referred_email'],['manager_partners','influencer_email']
        ];
        foreach($checks as [$table,$column]){
            $exists=app_query($db,'SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$table,$column])->get_result()->fetch_assoc();
            if($exists&&app_query($db,"SELECT 1 FROM `$table` WHERE `$column`=? LIMIT 1",[$email])->get_result()->fetch_assoc())throw new InvalidArgumentException('Esta conta possui histórico vinculado. Bloqueie o acesso em vez de excluí-la.');
        }
        $demoTable=app_query($db,"SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='manager_demos'")->get_result()->fetch_assoc();
        if($demoTable)app_query($db,'DELETE FROM manager_demos WHERE email=?',[$email]);
        app_query($db,"DELETE FROM auth_remember_tokens WHERE scope='player' AND subject=?",[$email]);
        app_query($db,'INSERT INTO deleted_user_ids(id) VALUES(?)',[$id]);
        app_query($db,'DELETE FROM appconfig WHERE id=?',[$id]);
        $db->commit();
        $_SESSION['admin_notice']='Conta excluída. O identificador não será reutilizado.';
    }catch(Throwable $e){$db->rollback();throw $e;}
}catch(InvalidArgumentException $e){$_SESSION['admin_notice']=$e->getMessage();}
catch(Throwable $e){error_log('admin user delete: '.$e->getMessage());$_SESSION['admin_notice']='Não foi possível excluir a conta.';}
header('Location: '.app_url('adm/usuarios/'),true,303);exit;
