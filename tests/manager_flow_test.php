<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/manager.php';
$db=app_db();manager_install($db);
$suffix=bin2hex(random_bytes(5));$managerEmail='manager_'.$suffix.'@example.test';$leadEmail='lead_'.$suffix.'@example.test';$demoEmail='demo_'.$suffix.'@example.test';$reference='qa_'.$suffix;
$managerId=0;$partnerId=0;$autoDemoEmail='';
function check_manager(bool $condition,string $name):void{if(!$condition)throw new RuntimeException($name);echo "PASS: $name\n";}
try{
    app_query($db,'INSERT INTO manager_accounts(name,email,password_hash) VALUES(?,?,?)',['QA Gerente',$managerEmail,password_hash('ExamplePassword123',PASSWORD_DEFAULT)]);$managerId=$db->insert_id;
    try{manager_partner_create($db,$managerId,'Influenciador QA',40,31);check_manager(false,'Budget guard');}catch(InvalidArgumentException $e){check_manager(true,'Budget guard');}
    $code=manager_partner_create($db,$managerId,'Influenciador QA',40,30);$partnerId=$db->insert_id;
    check_manager((bool)preg_match('/^[a-f0-9]{24}$/D',$code),'Unique invitation code');
    app_register($db,['email'=>$leadEmail,'senha'=>'ExamplePassword123','password_confirmation'=>'ExamplePassword123','telefone_confirmation'=>'11999999999'],'',$code);
    $lead=app_query($db,'SELECT partner_id FROM manager_referrals WHERE email=?',[$leadEmail])->get_result()->fetch_assoc();
    check_manager((int)($lead['partner_id']??0)===$partnerId,'Referral attributed on registration');
    manager_demo_create($db,$managerId,$demoEmail,'ExamplePassword123');
    $demo=app_query($db,'SELECT demo,saldo,total_apostado FROM appconfig WHERE email=?',[$demoEmail])->get_result()->fetch_assoc();
    check_manager(($demo['demo']??'')==='1'&&(float)$demo['saldo']===1000.0&&(float)$demo['total_apostado']===0.0,'Demo has isolated fictional balance');
    $automatic=manager_demo_create_auto($db,$managerId);$autoDemoEmail=$automatic['email'];
    check_manager(str_ends_with($automatic['email'],'@subwayrun.demo')&&strlen($automatic['password'])>=8,'Demo credentials are generated automatically');
    manager_demo_update($db,$managerId,$autoDemoEmail,'Demo Premium','345.67','NewDemoPassword123');
    $updatedDemo=app_query($db,'SELECT nome,saldo,senha FROM appconfig WHERE email=?',[$autoDemoEmail])->get_result()->fetch_assoc();
    check_manager(($updatedDemo['nome']??'')==='Demo Premium'&&(float)$updatedDemo['saldo']===345.67&&password_verify('NewDemoPassword123',$updatedDemo['senha']),'Manager edits demo name balance and password');
    require_once dirname(__DIR__).'/payments/bxpay_service.php'; bxpay_install($db);
    app_query($db,"INSERT INTO bxpay_deposits(reference,email,amount,status) VALUES(?,?,100,'PENDING')",[$reference.'_demo',$demoEmail]);
    try{bxpay_credit($db,$reference.'_demo',['external_id'=>$reference.'_demo','type'=>'DEPOSIT','status'=>'PAID','amount'=>100]);check_manager(false,'Demo deposit rejected');}
    catch(RuntimeException $e){check_manager(true,'Demo deposit rejected');}
    manager_commission_record($db,$reference,$leadEmail,100);
    $allocation=app_query($db,'SELECT manager_amount,influencer_amount FROM manager_commissions WHERE reference=?',[$reference])->get_result()->fetch_assoc();
    check_manager((float)$allocation['manager_amount']===40.0&&(float)$allocation['influencer_amount']===30.0,'Split is recorded for attributed deposit');
    $_SESSION['manager_id']=$managerId;$_SERVER['REQUEST_METHOD']='GET';ob_start();require dirname(__DIR__).'/gerente/index.php';$html=ob_get_clean();unset($_SESSION['manager_id']);
    check_manager(str_contains($html,'QA Gerente')&&str_contains($html,'Influenciador QA')&&str_contains($html,'Usuários de demonstração'),'Manager dashboard renders its own data');
}finally{
    app_query($db,'DELETE FROM bxpay_deposits WHERE reference=?',[$reference.'_demo']);
    app_query($db,'DELETE FROM manager_commissions WHERE reference=?',[$reference]);
    app_query($db,'DELETE FROM manager_referrals WHERE email=?',[$leadEmail]);
    app_query($db,'DELETE FROM manager_demo_balance_log WHERE manager_id=?',[(string)$managerId]);
    app_query($db,'DELETE FROM manager_demos WHERE manager_id=?',[(string)$managerId]);
    app_query($db,'DELETE FROM appconfig WHERE email IN (?,?,?)',[$leadEmail,$demoEmail,$autoDemoEmail]);
    if($partnerId)app_query($db,'DELETE FROM manager_partners WHERE id=?',[(string)$partnerId]);
    if($managerId)app_query($db,'DELETE FROM manager_accounts WHERE id=?',[(string)$managerId]);
}
