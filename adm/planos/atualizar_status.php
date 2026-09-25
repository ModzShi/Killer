<?php
require_once __DIR__ . '/../../app/auth.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
try{
    $db=app_db();$email=strtolower(trim(app_input('email')));$status=app_input('status');$amount=app_input('valor');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($status,['Pendente','Pago','Recusado'],true)||!is_numeric($amount))throw new InvalidArgumentException();
    $amount=number_format((float)$amount,2,'.','');
    $stmt=$db->prepare('UPDATE saque_afiliado SET status=? WHERE valor=? AND email=?');$stmt->bind_param('sss',$status,$amount,$email);$stmt->execute();
    echo $stmt->affected_rows>=0?'success':'error';
}catch(Throwable $e){http_response_code(400);echo 'Dados inválidos';}
