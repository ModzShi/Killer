<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/manager.php';
$db=app_db();manager_install($db);$suffix=bin2hex(random_bytes(6));$email='manager-'.$suffix.'@example.invalid';$demo='demo-long-address-'.$suffix.'@example.invalid';$password=bin2hex(random_bytes(16));$id=0;
$curl=curl_init();curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>20]);
function manager_http($curl,string $path,?array $post=null):array{
    curl_setopt($curl,CURLOPT_URL,'http://localhost/Subway-Knuckles/'.$path);
    if($post===null){curl_setopt($curl,CURLOPT_HTTPGET,true);}else{curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}
    $html=curl_exec($curl);if($html===false)throw new RuntimeException(curl_error($curl));return [curl_getinfo($curl,CURLINFO_RESPONSE_CODE),$html];
}
function manager_assert(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS: $name\n";}
try{
    app_query($db,'INSERT INTO manager_accounts(name,email,password_hash) VALUES(?,?,?)',['Gerente de teste com nome completo',$email,password_hash($password,PASSWORD_DEFAULT)]);$id=$db->insert_id;
    manager_partner_create($db,$id,'Influenciador com nome longo para conferir o celular',35,35);
    manager_demo_create($db,$id,$demo,$password);
    [$code,$html]=manager_http($curl,'gerente/login.php');preg_match('/name="csrf" value="([^"]+)"/',$html,$match);
    manager_assert($code===200&&!empty($match[1]),'Manager login form and CSRF render');
    [$code,$html]=manager_http($curl,'gerente/login.php',['email'=>$email,'password'=>$password,'csrf'=>$match[1]]);
    manager_assert($code===303,'Manager credentials establish a session');
    [$code,$html]=manager_http($curl,'gerente/');
    manager_assert($code===200&&str_contains($html,$demo)&&!preg_match('/Fatal error|Warning|Deprecated/',$html),'Dashboard loads demo accounts without collation errors');
    preg_match('/name="csrf" value="([^"]+)"/',$html,$match);
    if(in_array('--preview',$argv,true))file_put_contents(__DIR__.'/../arquivos/qa-manager.html',preg_replace('/name="csrf" value="[^"]*"/','name="csrf" value="preview"',$html));
    [$code,$html]=manager_http($curl,'gerente/',['csrf'=>$match[1],'action'=>'demo_balance','email'=>$demo,'balance'=>'1234.50']);
    $row=app_query($db,'SELECT saldo FROM appconfig WHERE email=?',[$demo])->get_result()->fetch_assoc();
    manager_assert($code===200&&(float)$row['saldo']===1234.50&&!str_contains($html,'Fatal error'),'Demo balance update works across table collations');
    manager_http($curl,'gerente/logout.php');[$code,$html]=manager_http($curl,'gerente/');manager_assert($code===302,'Logout removes access');
}finally{
    curl_close($curl);
    app_query($db,'DELETE FROM manager_demo_balance_log WHERE manager_id=?',[(string)$id]);app_query($db,'DELETE FROM manager_demos WHERE manager_id=?',[(string)$id]);app_query($db,'DELETE FROM appconfig WHERE email=?',[$demo]);app_query($db,'DELETE FROM manager_partners WHERE manager_id=?',[(string)$id]);app_query($db,'DELETE FROM auth_attempts WHERE attempt_key=?',[app_auth_attempt_key('manager',$email)]);app_query($db,'DELETE FROM manager_accounts WHERE id=?',[(string)$id]);
}
