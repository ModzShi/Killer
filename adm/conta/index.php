<?php
require_once __DIR__ . '/../../app/auth.php';
if (empty($_SESSION['emailadm'])) { header('Location: ' . app_url('adm/login/')); exit; }
$db = app_db();
$notice = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!app_check_csrf()) throw new InvalidArgumentException('Recarregue o formulário e tente novamente.');
        $currentPassword = app_input('current_password');
        $newEmail = strtolower(trim(app_input('email')));
        $newPassword = app_input('new_password');
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 254) throw new InvalidArgumentException('Informe um e-mail válido.');
        if ($newPassword !== '' && (strlen($newPassword) < 8 || strlen($newPassword) > 72)) throw new InvalidArgumentException('A nova senha precisa ter entre 8 e 72 caracteres.');
        if ($newPassword !== app_input('confirm_password')) throw new InvalidArgumentException('A confirmação da senha não confere.');
        $oldEmail = (string) $_SESSION['emailadm'];
        app_auth_remember_install($db);
        $db->begin_transaction();
        try {
            $account = app_query($db, 'SELECT senha FROM admlogin WHERE email=? FOR UPDATE', [$oldEmail])->get_result()->fetch_assoc();
            if (!$account || !app_password_matches($currentPassword, (string)$account['senha'])) throw new InvalidArgumentException('Senha atual incorreta.');
            if ($newEmail !== $oldEmail && app_query($db, 'SELECT email FROM admlogin WHERE email=? LIMIT 1', [$newEmail])->get_result()->fetch_assoc()) throw new InvalidArgumentException('Este e-mail já está em uso.');
            $hash = $newPassword !== '' ? password_hash($newPassword, PASSWORD_DEFAULT) : (password_needs_rehash((string)$account['senha'], PASSWORD_DEFAULT) ? password_hash($currentPassword, PASSWORD_DEFAULT) : (string)$account['senha']);
            app_query($db, 'UPDATE admlogin SET email=?,senha=? WHERE email=?', [$newEmail,$hash,$oldEmail]);
            app_query($db, "DELETE FROM auth_remember_tokens WHERE scope='admin' AND subject=?", [$oldEmail]);
            $db->commit();
            $_SESSION['emailadm'] = $newEmail;
            session_regenerate_id(true);
            app_auth_remember_clear_cookie('admin');
            $notice = 'Acesso administrativo atualizado. Entre novamente nos outros aparelhos.';
        } catch (Throwable $e) { $db->rollback(); throw $e; }
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log('admin account update: '.$e->getMessage()); $error = 'Não foi possível atualizar o acesso.'; }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Minha conta · Subway Run</title><link rel="stylesheet" href="<?= app_escape(app_url('adm/usuarios/users.css')) ?>?v=3"></head><body class="sk-admin"><main class="usr-shell"><header class="usr-head"><div><a class="usr-brand" href="<?= app_escape(app_url('adm/')) ?>">SUBWAY <span>RUN</span></a><p>ADMINISTRAÇÃO</p><h1>Minha conta</h1><span>Atualize seu acesso com a senha atual.</span></div><div class="usr-head-actions"><a href="<?= app_escape(app_url('adm/')) ?>">← Painel</a></div></header>
<?php if ($notice): ?><div class="usr-notice" role="status"><?= app_escape($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="usr-notice usr-notice--error" role="alert"><?= app_escape($error) ?></div><?php endif; ?>
<section class="usr-panel usr-account-panel"><div class="usr-panel-head"><div><p>ACESSO ADMINISTRATIVO</p><h2>E-mail e senha</h2></div></div><form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><div class="usr-form-grid"><label>E-mail de acesso<input type="email" name="email" value="<?= app_escape($_SESSION['emailadm']) ?>" maxlength="254" autocomplete="username" required></label><label>Senha atual<input type="password" name="current_password" autocomplete="current-password" required></label><label>Nova senha <small>Deixe vazio para manter a atual</small><input type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password"></label><label>Confirme a nova senha<input type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password"></label></div><button class="usr-save" type="submit">Salvar acesso</button></form></section></main></body></html>
