<?php
require_once __DIR__ . '/../../app/manager.php';
if(empty($_SESSION['emailadm'])) { header('Location: '.app_url('adm/login/')); exit; }
$db=app_db(); manager_install($db); $notice=''; $error='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
    try {
        if(!app_check_csrf()) throw new InvalidArgumentException('Formulário expirado.');
        $action=app_input('action');
        if($action==='create') {
            $name=trim(app_input('name')); $email=strtolower(trim(app_input('email'))); $password=app_input('password');
            if(strlen($name)<2||strlen($name)>120) throw new InvalidArgumentException('Informe o nome do gerente.');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254) throw new InvalidArgumentException('E-mail inválido.');
            if(strlen($password)<8||strlen($password)>72) throw new InvalidArgumentException('A senha deve ter de 8 a 72 caracteres.');
            app_query($db,'INSERT INTO manager_accounts(name,email,password_hash) VALUES(?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            $notice='Gerente criado. Entregue a senha a ele por um canal seguro.';
        } elseif($action==='toggle') {
            $id=(int)app_input('id'); if($id<1) throw new InvalidArgumentException('Gerente inválido.');
            app_query($db,'UPDATE manager_accounts SET active=1-active WHERE id=?',[(string)$id]);
            $notice='Acesso do gerente atualizado.';
        }
    } catch(mysqli_sql_exception $e) { $error=$e->getCode()===1062?'Este e-mail já está em uso.':'Não foi possível salvar o gerente.'; }
    catch(Throwable $e) { $error=$e->getMessage(); }
}
$managers=$db->query('SELECT id,name,email,active,created_at FROM manager_accounts ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gerentes • Administração</title><link rel="stylesheet" href="<?= app_escape(app_url('gerente/manager.css')) ?>"></head><body><main class="shell"><header class="head"><div><span class="kicker">CONTROLE DE ACESSO</span><h1>Gerentes</h1><p>Crie os acessos de quem administra influenciadores e contas demo.</p></div><a class="link-pill" href="<?= app_escape(app_url('adm/')) ?>">← Voltar ao ADM</a></header><?php if($notice): ?><div class="notice success" role="status"><?= app_escape($notice) ?></div><?php endif; ?><?php if($error): ?><div class="notice error" role="alert"><?= app_escape($error) ?></div><?php endif; ?><div class="grid"><section class="panel"><h2>Novo gerente</h2><p class="muted">O orçamento de divisão de cada convite é limitado a 70%.</p><form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="create"><label>Nome<input name="name" maxlength="120" required></label><label>E-mail<input name="email" type="email" maxlength="254" required></label><label>Senha inicial<input name="password" type="password" minlength="8" maxlength="72" required></label><button class="primary">Criar gerente</button></form></section><section class="panel"><h2>Acessos</h2><?php if(!$managers): ?><p class="muted">Nenhum gerente cadastrado.</p><?php endif; ?><div class="rows"><?php foreach($managers as $m): ?><div class="row"><div><strong><?= app_escape($m['name']) ?></strong><small><?= app_escape($m['email']) ?></small></div><form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="mini <?= $m['active']?'on':'off' ?>" type="submit"><?= $m['active']?'Ativo':'Inativo' ?></button></form></div><?php endforeach; ?></div></section></div><p class="muted">Painel do gerente: <a href="<?= app_escape(app_url('gerente/')) ?>"><?= app_escape(app_url('gerente/')) ?></a></p></main></body></html>
