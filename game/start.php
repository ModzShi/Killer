<?php
require_once __DIR__ . '/../app/game.php';
if(empty($_SESSION['email'])){header('Location: '.app_url('login/'));exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'||!app_check_csrf()){http_response_code(403);exit('Formulário expirado.');}
try{
    $code=app_input('bet');$difficulty=app_input('difficulty');
    if(!in_array($difficulty,['B1C2','B1C3','B1C4'],true))$difficulty='B1C3';
    $db=app_db();
    $account=app_query($db,'SELECT demo FROM appconfig WHERE email=?',[$_SESSION['email']])->get_result()->fetch_assoc();
    if(!$account) throw new RuntimeException('Conta não encontrada.');
    if((string)$account['demo']==='1') $difficulty='B1C2';
    $token=game_start($db,$_SESSION['email'],$code);
    header('Location: '.app_url('jogar/?jogarsubway='.urlencode($code).'&SbS'.urlencode($difficulty).'&round='.urlencode($token)),true,303);exit;
}catch(Throwable $e){$_SESSION['game_error']=$e->getMessage();header('Location: '.app_url('painel/'),true,303);exit;}
