<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/auth.php';
if(!SK_OFFLINE) throw new RuntimeException('Offline mode required');
$base='http://localhost/Subway-Knuckles';
$client=curl_init(); curl_setopt($client,CURLOPT_COOKIEFILE,'');
function request_page(string $path, ?array $data=null): array {
    global $client,$base;
    curl_setopt_array($client,[CURLOPT_URL=>$base.$path,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15]);
    if($data===null)curl_setopt($client,CURLOPT_HTTPGET,true);
    else {curl_setopt($client,CURLOPT_POST,true);curl_setopt($client,CURLOPT_POSTFIELDS,http_build_query($data));}
    $response=curl_exec($client);$size=curl_getinfo($client,CURLINFO_HEADER_SIZE);
    if($response===false)throw new RuntimeException(curl_error($client));
    $headers=substr($response,0,$size);preg_match('/^Location:\s*(.+)$/mi',$headers,$loc);
    return ['code'=>curl_getinfo($client,CURLINFO_HTTP_CODE),'body'=>substr($response,$size),'location'=>trim($loc[1]??''),'headers'=>$headers];
}
function csrf(array $page):string {if(!preg_match('/name="csrf" value="([a-f0-9]+)"/',$page['body'],$match))throw new RuntimeException('Missing CSRF in '.$page['code'].'; body: '.substr(strip_tags($page['body']),0,300));return $match[1];}
function field(array $page,string $name):string {if(!preg_match('/name="'.preg_quote($name,'/').'" value="([^"]+)"/',$page['body'],$match))throw new RuntimeException('Missing field '.$name);return html_entity_decode($match[1],ENT_QUOTES,'UTF-8');}
function expect_test(bool $ok,string $name):void {if(!$ok)throw new RuntimeException($name);echo "PASS: $name\n";}
$email='qa_'.bin2hex(random_bytes(6)).'@example.test';$password='Test<&!934xyz';$db=app_db();
try {
    $page=request_page('/adm/usuarios/bd.php');expect_test($page['code']===302 && strpos($page['location'],'/adm/login/')!==false,'Admin JSON requires login');
    $page=request_page('/cadastrar/');$token=csrf($page);
    expect_test(strpos($page['body'],'MODO OFFLINE')!==false,'Offline banner displayed');
    $data=['csrf'=>$token,'email'=>$email,'senha'=>$password,'password_confirmation'=>$password,'telefone_confirmation'=>'11999999999'];
    $invalid=request_page('/cadastrar/',array_replace($data,['password_confirmation'=>'wrong']));
    expect_test(strpos($invalid['body'],'não coincidem')!==false,'Mismatched passwords are rejected');
    $registered=request_page('/cadastrar/',$data);
    expect_test($registered['code']===303 && $registered['location']==='/Subway-Knuckles/painel/','Registration redirects within project');
    $row=app_query($db,'SELECT senha,saldo FROM appconfig WHERE email=?',[$email])->get_result()->fetch_assoc();
    expect_test($row && password_verify($password,$row['senha']),'Registration stores a password hash without corrupting special characters');
    $dashboard=request_page('/painel/');
    expect_test($dashboard['code']===200 && !preg_match('/Fatal error|Warning:|Notice:/',$dashboard['body']),'Player dashboard loads without PHP errors');
    $page=request_page('/teste/');$token=csrf($page);
    $deposit=request_page('/teste/',['csrf'=>$token,'action'=>'deposit','amount'=>'200']);
    expect_test($deposit['code']===303 && strpos($deposit['location'],'/teste/pagamento.php?token=test_')!==false,'Offline deposit creates a simulation');
    $path=substr($deposit['location'],strlen('/Subway-Knuckles'));
    $payment=request_page($path);$token=csrf($payment);
    request_page($path,['csrf'=>$token,'action'=>'approve']);
    request_page($path,['csrf'=>$token,'action'=>'approve']);
    $balance=app_query($db,'SELECT saldo FROM appconfig WHERE email=?',[$email])->get_result()->fetch_row()[0];
    expect_test((float)$balance===200.0,'Repeated simulation approval credits exactly once');
    $page=request_page('/teste/');$rejected=request_page('/teste/',['csrf'=>csrf($page),'action'=>'deposit','amount'=>'50']);
    $path=substr($rejected['location'],strlen('/Subway-Knuckles'));$payment=request_page($path);
    request_page($path,['csrf'=>csrf($payment),'action'=>'reject']);
    request_page($path,['csrf'=>csrf($payment),'action'=>'approve']);
    expect_test((float)app_query($db,'SELECT saldo FROM appconfig WHERE email=?',[$email])->get_result()->fetch_row()[0]===200.0,'Rejected deposit cannot later credit');
    request_page('/logout.php');$page=request_page('/login/');
    $bad=request_page('/login/',['csrf'=>csrf($page),'email'=>$email,'senha'=>'wrong']);
    expect_test(strpos($bad['body'],'incorretos')!==false,'Incorrect password rejected');
    $login=request_page('/login/',['csrf'=>csrf($bad),'email'=>$email,'senha'=>$password]);
    expect_test($login['code']===303,'Hashed password login succeeds');
    app_query($db,'UPDATE appconfig SET total_apostado=10000 WHERE email=?',[$email]);
    $withdrawPage=request_page('/saque/');$playerCsrf=csrf($withdrawPage);$withdrawData=['csrf'=>$playerCsrf,'nonce'=>field($withdrawPage,'nonce'),'withdrawName'=>'Pessoa Teste','withdrawCPF'=>'12345678901','withdrawValue'=>'100'];
    $withdraw=request_page('/saque/process.php',$withdrawData);
    expect_test($withdraw['code']===303,'Withdrawal request uses POST and redirects safely');
    $duplicate=request_page('/saque/process.php',$withdrawData);
    expect_test($duplicate['code']===409,'Duplicate withdrawal submission is rejected');
    expect_test((float)app_query($db,'SELECT saldo FROM appconfig WHERE email=?',[$email])->get_result()->fetch_row()[0]===100.0,'Withdrawal debits offline balance exactly once');
    $dashboard=request_page('/painel/');$round=request_page('/game/start.php',['csrf'=>$playerCsrf,'bet'=>'1BC','difficulty'=>'B1C3']);
    expect_test($round['code']===303&&preg_match('/round=([a-f0-9]{64})/',$round['location'],$roundMatch),'Game starts with a server round');
    request_page('/gameover/win.php',['token'=>$roundMatch[1],'msg'=>'999999','bet'=>'1BC']);
    request_page('/gameover/win.php',['token'=>$roundMatch[1],'msg'=>'999999','bet'=>'1BC']);
    expect_test((float)app_query($db,'SELECT saldo FROM appconfig WHERE email=?',[$email])->get_result()->fetch_row()[0]===109.0,'Game reward matches the target and is credited once');
    $page=request_page('/teste/');$admin=request_page('/teste/',['csrf'=>csrf($page),'action'=>'admin']);
    expect_test($admin['location']==='/Subway-Knuckles/adm/','Offline admin entry works');
    foreach(['/adm/','/adm/usuarios/','/adm/depositos/','/adm/saques/','/adm/config/','/adm/gateway/bxpay.php','/adm/gerentes/'] as $path){
        $page=request_page($path);
        expect_test($page['code']===200 && !preg_match('/Fatal error|Warning:|Notice:/',$page['body']),'Admin page loads: '.$path);
    }
    $testId=app_query($db,'SELECT id FROM appconfig WHERE email=?',[$email])->get_result()->fetch_row()[0];
    $userPage=request_page('/adm/usuarios/');
    $updated=request_page('/adm/usuarios/update.php',['csrf'=>csrf($userPage),'id'=>$testId,'email'=>$email,'senha'=>'','telefone'=>'11988887777','saldo'=>'123.45','linkafiliado'=>'','plano'=>'20','depositou'=>'100','saldo_comissao'=>'0','percas'=>'1','ganhos'=>'2','cpa'=>'3','comissaofake'=>'0']);
    expect_test($updated['code']===303,'Admin user update succeeds with CSRF');
    $updatedUser=app_query($db,'SELECT saldo,senha FROM appconfig WHERE id=?',[$testId])->get_result()->fetch_assoc();
    expect_test((float)$updatedUser['saldo']===123.45&&password_verify($password,$updatedUser['senha']),'Admin edit preserves blank password and uses prepared values');
    $users=request_page('/adm/usuarios/bd.php');$list=json_decode($users['body'],true);
    expect_test(is_array($list),'Admin users JSON loads');
    foreach($list as $user)expect_test(empty($user['senha']),'Passwords are not returned by admin API');
    $forbidden=request_page('/adm/usuarios/update.php',['id'=>'none']);expect_test($forbidden['code']===403,'Admin writes require CSRF');
} finally {
    app_query($db,'DELETE FROM game_rounds WHERE email=?',[$email]);
    app_query($db,'DELETE FROM saques WHERE email=?',[$email]);
    app_query($db,'DELETE FROM confirmar_deposito WHERE email=?',[$email]);
    app_query($db,'DELETE FROM bxpay_deposits WHERE email=?',[$email]);
    app_query($db,'DELETE FROM appconfig WHERE email=?',[$email]);
    curl_close($client);$db->close();
}
