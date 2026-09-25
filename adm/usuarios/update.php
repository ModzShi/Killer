<?php
require_once __DIR__ . '/../../app/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
function admin_number(string $name, float $min, float $max): string {
    $value = app_input($name);
    if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < $min || (float)$value > $max) throw new InvalidArgumentException('Valor inválido em ' . $name . '.');
    return number_format((float)$value, 2, '.', '');
}
try {
    $db=app_db(); $id=trim(app_input('id')); $email=strtolower(trim(app_input('email')));
    if($id==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Usuário ou e-mail inválido.');
    if(!app_query($db,'SELECT id FROM appconfig WHERE id = ?',[$id])->get_result()->num_rows) throw new InvalidArgumentException('Usuário não encontrado.');
    $duplicate=app_query($db,'SELECT id FROM appconfig WHERE email = ? AND id <> ?',[$email,$id])->get_result();
    if($duplicate->num_rows) throw new InvalidArgumentException('Este e-mail já pertence a outra conta.');
    $phone=preg_replace('/\D/','',app_input('telefone'));
    if($phone!==''&&!preg_match('/^\d{10,13}$/D',$phone)) throw new InvalidArgumentException('Telefone inválido.');
    $values=[
        $email,$phone,admin_number('saldo',0,9999999999),trim(app_input('linkafiliado')),
        admin_number('plano',0,100),admin_number('depositou',0,9999999999),isset($_POST['bloqueado'])?'1':'0',
        admin_number('saldo_comissao',0,9999999999),admin_number('percas',0,9999999999),
        admin_number('ganhos',0,9999999999),admin_number('cpa',0,9999999999),isset($_POST['afiliado_ativo'])?'1':'0',
        admin_number('comissaofake',0,9999999999),$id
    ];
    $db->begin_transaction();
    app_query($db,'UPDATE appconfig SET email=?,telefone=?,saldo=?,linkafiliado=?,plano=?,depositou=?,bloc=?,saldo_comissao=?,percas=?,ganhos=?,cpa=?,afiliado_ativo=?,comissaofake=? WHERE id=?',$values);
    $password=app_input('senha');
    if($password!=='') {
        if(strlen($password)<6||strlen($password)>72) throw new InvalidArgumentException('A nova senha deve ter entre 6 e 72 caracteres.');
        app_query($db,'UPDATE appconfig SET senha=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);
    }
    $db->commit(); $_SESSION['admin_notice']='Usuário atualizado com sucesso.';
} catch(InvalidArgumentException $e) {
    if(isset($db))$db->rollback(); $_SESSION['admin_notice']=$e->getMessage();
} catch(Throwable $e) {
    if(isset($db))$db->rollback(); $_SESSION['admin_notice']='Não foi possível atualizar o usuário.';
}
header('Location: '.app_url('adm/usuarios/'),true,303); exit;
