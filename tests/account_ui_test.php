<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/account.php';
$db=app_db();withdrawal_install($db);game_install($db);
$email='ui-'.bin2hex(random_bytes(6)).'@example.invalid';$password=bin2hex(random_bytes(16));
ini_set('session.use_cookies','0');session_cache_limiter('');session_name('SK_SESSION');session_id(bin2hex(random_bytes(24)));session_start();
$sessionId=session_id();$_SESSION['email']=$email;$csrf=app_csrf();session_write_close();
function request_page(string $route,string $sessionId,?array $post=null):array {
    $curl=curl_init(rtrim(getenv('TEST_BASE_URL') ?: 'http://localhost/Subway-Knuckles','/').'/'.$route);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIE=>'SK_SESSION='.$sessionId,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);
    if($post!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $body=curl_exec($curl);$code=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    if($body===false)throw new RuntimeException(curl_error($curl));curl_close($curl);return [$code,$body];
}
function account_check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS: $name\n";}
try {
    app_register($db,['nome'=>'Jogador de teste','email'=>$email,'senha'=>$password,'password_confirmation'=>$password,'telefone_confirmation'=>'11900000000'],'');
    $storedHash=app_query($db,'SELECT senha FROM appconfig WHERE email=?',[$email])->get_result()->fetch_assoc()['senha'];
    session_id($sessionId);session_start();$_SESSION['player_auth_hash']=app_password_fingerprint((string)$storedHash);session_write_close();
    account_check((app_query($db,'SELECT nome FROM appconfig WHERE email=?',[$email])->get_result()->fetch_assoc()['nome']??'')==='Jogador de teste','Registration stores the submitted full name');
    account_update_profile($db,['nome'=>'Jogador Atualizado','telefone'=>'11911112222']);
    $profile=app_query($db,'SELECT nome,telefone FROM appconfig WHERE email=?',[$email])->get_result()->fetch_assoc();
    account_check(($profile['nome']??'')==='Jogador Atualizado'&&($profile['telefone']??'')==='11911112222','Profile update saves name and phone');
    try{account_update_profile($db,['nome'=>'Nome não salvo','telefone'=>'123']);account_check(false,'Profile rejects invalid phone');}catch(InvalidArgumentException $error){account_check(true,'Profile rejects invalid phone');}
    app_query($db,'UPDATE appconfig SET comissaofake=25.50,saldo_comissao=99 WHERE email=?',[$email]);
    $payoutReference='ui-paid-'.bin2hex(random_bytes(5));$pendingReference='ui-pending-'.bin2hex(random_bytes(5));$now=date('d-m-Y H:i:s');
    app_query($db,"INSERT INTO saques(email,externalreference,destino,chavepix,data,valor,status) VALUES(?,?,?,?,?,87.65,'PAID')",[$email,$payoutReference,'Marina Oliveira','00000000000',$now]);
    app_query($db,"INSERT INTO saques(email,externalreference,destino,chavepix,data,valor,status) VALUES(?,?,?,?,?,999.99,'Processando')",[$email,$pendingReference,'Registro Pendente','00000000000',$now]);
    foreach(['afiliate/'=>'Sua rede, suas conquistas.','perfil/'=>'Histórico de','saque-afiliado/'=>'Sacar comissão','painel/'=>'Treinar','deposito/'=>'</html>','saque/'=>'</html>'] as $route=>$expected){
        [$code,$body]=request_page($route,$sessionId);
        account_check($code===200&&str_contains($body,'</html>')&&!preg_match('/Warning|Fatal error|Deprecated/',$body),'Authenticated page renders: '.$route.' (HTTP '.$code.')');
        if(in_array('--preview',$argv,true)){
            $body=str_replace('<head>','<head><base href="/Subway-Knuckles/'.$route.'">',$body);
            $body=preg_replace('/name="csrf" value="[^"]*"/','name="csrf" value="preview"',$body);
            file_put_contents(__DIR__.'/../arquivos/qa-'.trim($route,'/').'.html',$body);
        }
    }
    [$activityCode,$activityBody]=request_page('perfil/',$sessionId);
    account_check($activityCode===200&&str_contains($activityBody,'R$ 87,65')&&str_contains($activityBody,'R$ 999,99')&&str_contains($activityBody,'Pago')&&str_contains($activityBody,'Processando'),'Profile distinguishes paid and pending withdrawals');
    [$balanceCode,$balanceBody]=request_page('api/balance.php',$sessionId);
    $balancePayload=json_decode($balanceBody,true);
    account_check($balanceCode===200&&($balancePayload['ok']??false)===true&&is_string($balancePayload['formatted']??null),'Live balance endpoint returns authenticated balance');
    $user=app_query($db,'SELECT saldo_comissao FROM appconfig WHERE email=?',[$email])->get_result()->fetch_assoc();
    account_check((float)$user['saldo_comissao']===99.0,'Viewing affiliate wallet does not mutate balances');
    request_page('saque-afiliado/',$sessionId,['csrf'=>$csrf,'withdrawName'=>'Jogador de teste','withdrawCPF'=>'test@example.invalid']);
    request_page('saque-afiliado/',$sessionId,['csrf'=>$csrf,'withdrawName'=>'Jogador de teste','withdrawCPF'=>'test@example.invalid']);
    $row=app_query($db,'SELECT COUNT(*) n,SUM(valor) amount FROM saque_afiliado WHERE email=?',[$email])->get_result()->fetch_assoc();
    account_check((int)$row['n']===1&&(float)$row['amount']===25.50,'Duplicate submission reserves commission only once');
    [$code,$body]=request_page('perfil/','');account_check($code===302,'Profile requires authentication');
    app_query($db,'UPDATE appconfig SET bloc=1 WHERE email=?',[$email]);
    [$code,$body]=request_page('painel/',$sessionId);account_check($code===302,'Blocking a player revokes an open session');
}finally{
    if(isset($payoutReference))app_query($db,'DELETE FROM saques WHERE externalreference IN (?,?)',[$payoutReference,$pendingReference]);
    app_query($db,'DELETE FROM saque_afiliado WHERE email=?',[$email]);
    app_query($db,'DELETE FROM appconfig WHERE email=?',[$email]);
    session_id($sessionId);session_start();session_destroy();$db->close();
}
