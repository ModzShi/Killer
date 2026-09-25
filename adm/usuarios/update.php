<?php
require_once __DIR__.'/../../app/auth.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
function admin_user_number(string $name,float $min,float $max):string{
    $value=app_input($name);
    if(!is_numeric($value)||!is_finite((float)$value)||(float)$value<$min||(float)$value>$max)throw new InvalidArgumentException('Confira o valor informado em '.$name.'.');
    return number_format((float)$value,2,'.','');
}
try{
    $transaction=false;
    $db=app_db();$id=trim(app_input('id'));$name=trim(app_input('nome'));$email=strtolower(trim(app_input('email')));
    if($id===''||!ctype_digit($id))throw new InvalidArgumentException('Usuário inválido.');
    if(strlen($name)<2||strlen($name)>120)throw new InvalidArgumentException('Informe um nome entre 2 e 120 caracteres.');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new InvalidArgumentException('Informe um e-mail válido.');
    $current=app_query($db,'SELECT email FROM appconfig WHERE id=?',[$id])->get_result()->fetch_assoc();
    if(!$current)throw new InvalidArgumentException('Usuário não encontrado.');
    if(app_query($db,'SELECT id FROM appconfig WHERE email=? AND id<>?',[$email,$id])->get_result()->num_rows)throw new InvalidArgumentException('Este e-mail já pertence a outra conta.');
    $phone=preg_replace('/\D/','',app_input('telefone'));
    if($phone!==''&&!preg_match('/^\d{10,13}$/D',$phone))throw new InvalidArgumentException('Informe um telefone válido com DDD.');
    $balance=admin_user_number('saldo',0,9999999999);$commission=admin_user_number('comissaofake',0,9999999999);$plan=admin_user_number('plano',0,100);$cpa=admin_user_number('cpa',0,9999999999);
    $blocked=isset($_POST['bloqueado'])?'1':'0';$affiliate=isset($_POST['afiliado_ativo'])?'1':'0';$password=app_input('senha');
    if($password!==''&&(strlen($password)<6||strlen($password)>72))throw new InvalidArgumentException('A nova senha deve ter entre 6 e 72 caracteres.');
    $db->begin_transaction();$transaction=true;
    app_query($db,'UPDATE appconfig SET nome=?,email=?,telefone=?,saldo=?,comissaofake=?,plano=?,cpa=?,bloc=?,afiliado_ativo=? WHERE id=?',[$name,$email,$phone,$balance,$commission,$plan,$cpa,$blocked,$affiliate,$id]);
    if($password!=='')app_query($db,'UPDATE appconfig SET senha=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);
    if($email!==$current['email'])app_query($db,'UPDATE manager_demos SET email=? WHERE email=?',[$email,$current['email']]);
    $db->commit();$transaction=false;$_SESSION['admin_notice']='Usuário atualizado com sucesso.';
}catch(InvalidArgumentException $e){if(!empty($transaction))$db->rollback();$_SESSION['admin_notice']=$e->getMessage();}
catch(Throwable $e){if(!empty($transaction)){try{$db->rollback();}catch(Throwable $ignored){}}error_log('admin user update: '.$e->getMessage());$_SESSION['admin_notice']='Não foi possível atualizar o usuário.';}
header('Location: '.app_url('adm/usuarios/'),true,303);exit;
