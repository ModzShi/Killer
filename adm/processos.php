<?php
require_once __DIR__ . '/../app/auth.php';
if (empty($_SESSION['emailadm'])) { http_response_code(403); exit('Não autorizado.'); }
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
if (!hash_equals(app_csrf(), app_input('csrf'))) { http_response_code(403); exit('Formulário expirado. Atualize a página.'); }
$columns=['depositoMin'=>'deposito_min','saqueMin'=>'saques_min','apostaMax'=>'aposta_max','apostaMin'=>'aposta_min','rolloverSaque'=>'rollover_saque','taxaSaque'=>'taxa_saque'];
$option=is_string($_GET['opcao']??null)?$_GET['opcao']:'';$raw=app_input('valor');
try{
    $db=app_db();
    if(!isset($columns[$option])||!is_numeric($raw)||!is_finite((float)$raw)||(float)$raw<0||(float)$raw>9999999999)throw new InvalidArgumentException();
    $value=number_format((float)$raw,2,'.','');
    $column=$columns[$option];
    $stmt=$db->prepare("UPDATE app SET `$column`=? LIMIT 1");$stmt->bind_param('s',$value);$stmt->execute();
    $_SESSION['admin_notice']='Configuração atualizada.';
}catch(Throwable $e){$_SESSION['admin_notice']='Valor ou configuração inválida.';}
header('Location: '.app_url('adm/'),true,303);exit;
