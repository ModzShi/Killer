<?php
require_once __DIR__ . '/../app/withdrawal.php';
if(empty($_SESSION['email'])){header('Location: '.app_url('login/'));exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'||!app_check_csrf()){http_response_code(403);exit('Formulário expirado.');}
if(!is_string($_POST['nonce']??null)||empty($_SESSION['withdraw_nonce'])||!hash_equals($_SESSION['withdraw_nonce'],$_POST['nonce'])){http_response_code(409);exit('Esta solicitação já foi enviada.');}
unset($_SESSION['withdraw_nonce']);
try{
    $db=app_db();withdrawal_install($db);$email=$_SESSION['email'];$name=trim(app_input('withdrawName'));
    $cpf=preg_replace('/\D/','',app_input('withdrawCPF'));$amount=(float)app_input('withdrawValue');
    if(strlen($name)<2||strlen($name)>120)throw new InvalidArgumentException('Informe o nome do destinatário.');
    if(!preg_match('/^\d{11}$/D',$cpf))throw new InvalidArgumentException('Informe um CPF válido com 11 números.');
    if(!is_finite($amount)||$amount<=0||$amount>9999999999||abs($amount-round($amount,2))>0.000001)throw new InvalidArgumentException('Informe um valor de saque válido.');
    $lock='withdraw_'.substr(hash('sha256',$email),0,40);$stmt=$db->prepare('SELECT GET_LOCK(?,5)');$stmt->bind_param('s',$lock);$stmt->execute();
    if((int)$stmt->get_result()->fetch_row()[0]!==1)throw new RuntimeException('Já existe um saque sendo processado.');
    try{
        $db->begin_transaction();
        $app=$db->query('SELECT saques_min,rollover_saque FROM app LIMIT 1')->fetch_assoc()?:[];
        if($amount<(float)($app['saques_min']??0))throw new InvalidArgumentException('O valor está abaixo do saque mínimo.');
        $stmt=$db->prepare('SELECT saldo,total_apostado,depositou,demo FROM appconfig WHERE email=? FOR UPDATE');$stmt->bind_param('s',$email);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();
        if($user && (string)($user['demo']??'0')==='1')throw new InvalidArgumentException('Contas demo não podem solicitar saques.');
        if(!$user||$amount>(float)$user['saldo'])throw new InvalidArgumentException('Saldo insuficiente.');
        if((float)$user['total_apostado']<(float)$user['depositou']*(float)($app['rollover_saque']??0))throw new InvalidArgumentException('Você ainda precisa cumprir o rollover antes de sacar.');
        $stmt=$db->prepare('UPDATE appconfig SET saldo=saldo-? WHERE email=? AND CAST(saldo AS DECIMAL(12,2))>=?');$stmt->bind_param('dsd',$amount,$email,$amount);$stmt->execute();
        if($stmt->affected_rows!==1)throw new RuntimeException('O saldo mudou. Confira e tente novamente.');
        $reference='wd_'.bin2hex(random_bytes(16));$status=SK_OFFLINE?'Teste - Processando':'Processando';$date=date('d-m-Y H:i:s');
        $stmt=$db->prepare('INSERT INTO saques(email,externalreference,destino,chavepix,data,valor,status) VALUES(?,?,?,?,?,?,?)');$stmt->bind_param('sssssss',$email,$reference,$name,$cpf,$date,$amount,$status);$stmt->execute();
        $db->commit();$_SESSION['withdraw_notice']='Saque solicitado com sucesso. Referência: '.$reference;
    }catch(Throwable $e){$db->rollback();throw $e;}finally{$stmt=$db->prepare('SELECT RELEASE_LOCK(?)');$stmt->bind_param('s',$lock);$stmt->execute();}
}catch(InvalidArgumentException $e){$_SESSION['withdraw_notice']=$e->getMessage();}
catch(Throwable $e){$_SESSION['withdraw_notice']='Não foi possível solicitar o saque.';}
header('Location: '.app_url('saque/'),true,303);exit;
