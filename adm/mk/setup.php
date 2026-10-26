<?php
// PHP 8.1 / adm/mk/setup.php / Subway MK private migration
require_once dirname(__DIR__, 2) . '/mk/lib.php';
$db = app_db();
mk_install($db);
$message = '';

function mk_uploaded(string $name, int $limit): string {
    $file = $_FILES[$name] ?? null;
    if (!is_array($file) || ($file['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || (int)$file['size'] > $limit)
        throw new InvalidArgumentException('Confira o arquivo ' . $name . '.');
    $data = file_get_contents($file['tmp_name']);
    if ($data === false) throw new RuntimeException('Falha ao ler ' . $name . '.');
    return $data;
}
function mk_uploaded_json(string $name): array {
    $value = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', mk_uploaded($name, 1024 * 1024)), true);
    if (!is_array($value)) throw new InvalidArgumentException('JSON invalido: ' . $name . '.');
    return $value;
}
function mk_private_dir(): string {
    $outside = dirname(SK_ROOT) . '/mk-private';
    if ((is_dir($outside) || @mkdir($outside, 0700, true)) && is_writable($outside)) return $outside;
    $fallback = SK_ROOT . '/config';
    if (!is_dir($fallback) || !is_writable($fallback)) throw new RuntimeException('Diretorio privado indisponivel.');
    return $fallback;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'import') {
            $secret = mk_uploaded('secret', 32);
            if (strlen($secret) !== 32) throw new InvalidArgumentException('Segredo MK invalido.');
            $licenses = mk_uploaded_json('licenses');
            $accounts = mk_uploaded_json('accounts');
            $releases = mk_uploaded_json('releases');
            $private = mk_private_dir();
            $secretPath = $private === SK_ROOT . '/config' ? $private . '/mk-secret.bin' : $private . '/server-secret.bin';
            if (is_file($secretPath) && !hash_equals((string)file_get_contents($secretPath), $secret)) throw new RuntimeException('O segredo atual e diferente.');
            if (!is_file($secretPath) && file_put_contents($secretPath, $secret, LOCK_EX) !== 32) throw new RuntimeException('Falha ao gravar o segredo.');
            $db->begin_transaction();
            try {
                foreach ($licenses as $item) {
                    $created = strtotime((string)($item['CreatedAt'] ?? ''));
                    $expiry = isset($item['ExpiresAt']) ? strtotime((string)$item['ExpiresAt']) : null;
                    $activated = isset($item['ActivatedAt']) ? strtotime((string)$item['ActivatedAt']) : null;
                    if (!$created || $expiry === false || $activated === false || !preg_match('/^[a-fA-F0-9]{32}$/D', (string)($item['Id'] ?? '')) || !preg_match('/^[a-fA-F0-9]{64}$/D', (string)($item['KeyHash'] ?? '')))
                        throw new InvalidArgumentException('Licenca antiga invalida.');
                    app_query($db, 'INSERT INTO mk_licenses (id,seller,key_hash,suffix,created_at,expires_at,duration_seconds,activated_at,revoked,device) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',
                        [$item['Id'], $item['Label'], $item['KeyHash'], $item['Suffix'], (string)$created,
                         $expiry === null ? null : (string)$expiry, isset($item['DurationSeconds']) ? (string)$item['DurationSeconds'] : null,
                         $activated === null ? null : (string)$activated, !empty($item['Revoked']) ? '1' : '0', $item['Device'] ?? null]);
                }
                foreach ($accounts as $item) {
                    $created = strtotime((string)($item['CreatedAt'] ?? ''));
                    $last = isset($item['LastLoginAt']) ? strtotime((string)$item['LastLoginAt']) : null;
                    if (!$created || $last === false || !preg_match('/^[a-fA-F0-9]{32}$/D', (string)($item['Id'] ?? '')))
                        throw new InvalidArgumentException('Conta antiga invalida.');
                    app_query($db, 'INSERT INTO mk_accounts (id,username,password_salt,password_hash,license_id,created_at,last_login_at,device) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',
                        [$item['Id'], $item['Username'], $item['PasswordSalt'], $item['PasswordHash'], $item['LicenseId'], (string)$created, $last === null ? null : (string)$last, $item['Device']]);
                }
                foreach ($releases as $item) {
                    $sha = strtoupper((string)($item['Sha256'] ?? ''));
                    $version = (string)($item['Version'] ?? '');
                    $size = (int)($item['Size'] ?? 0);
                    $published = strtotime((string)($item['PublishedAt'] ?? ''));
                    if (!preg_match('/^[A-F0-9]{64}$/D', $sha) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/D', $version) || $size < 4096 || $size > 25 * 1024 * 1024 || !$published)
                        throw new InvalidArgumentException('Versao antiga invalida.');
                    app_query($db, 'INSERT INTO mk_releases (version,sha256,size,published_at,notes) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE version=version',
                        [$version, $sha, (string)$size, (string)$published, (string)($item['Notes'] ?? '')]);
                }
                $db->commit();
                $message = 'Keys, contas e manifesto importados.';
            } catch (Throwable $e) { $db->rollback(); throw $e; }
        } elseif ($action === 'dll') {
            $file = $_FILES['dll'] ?? null;
            if (!is_array($file) || ($file['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || (int)$file['size'] > 25 * 1024 * 1024)
                throw new InvalidArgumentException('Confira o arquivo DLL e o limite de upload do PHP.');
            mk_verify_dll($file['tmp_name']);
            $sha = strtoupper(hash_file('sha256', $file['tmp_name']));
            $row = app_query($db, 'SELECT size FROM mk_releases WHERE sha256=? LIMIT 1', [$sha])->get_result()->fetch_assoc();
            if (!$row || (int)$row['size'] !== (int)$file['size']) throw new InvalidArgumentException('DLL diferente do manifesto importado.');
            $private = mk_private_dir();
            $dir = $private === SK_ROOT . '/config' ? $private . '/mk-releases' : $private . '/releases';
            if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Falha ao criar diretorio de DLL.');
            $target = $dir . '/' . $sha . '.dll';
            if (!is_file($target) && !move_uploaded_file($file['tmp_name'], $target)) throw new RuntimeException('Falha ao salvar DLL.');
            $message = 'DLL instalada e verificada.';
        } else throw new InvalidArgumentException('Acao invalida.');
    }
} catch (Throwable $e) { $message = $e->getMessage(); }
$count = $db->query('SELECT (SELECT COUNT(*) FROM mk_licenses) AS licenses, (SELECT COUNT(*) FROM mk_accounts) AS accounts, (SELECT COUNT(*) FROM mk_releases) AS releases')->fetch_assoc();
$db->close();
$adminActive = 'mk'; $adminTitle = 'Migracao MK';
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Migracao MK - Subway</title><link rel="stylesheet" href="<?= app_escape(app_url('gerente/manager-premium.css')) ?>"><link rel="stylesheet" href="<?= app_escape(app_url('adm/admin-shell.css')) ?>"><style>.mk-setup{max-width:850px;margin:auto}.mk-setup section{background:#191d27;border-radius:18px;padding:24px;margin:18px 0}.mk-setup label{display:block;margin:14px 0}.mk-setup input{display:block;margin-top:5px}.mk-setup button{padding:10px 18px;cursor:pointer}</style></head><body class="admin-app"><?php require dirname(__DIR__) . '/components/panel-nav.php'; ?><div class="mk-setup"><h1>Migrar MK para a hospedagem</h1><p>Keys: <?= (int)$count['licenses'] ?> · Contas: <?= (int)$count['accounts'] ?> · Versoes: <?= (int)$count['releases'] ?></p><?php if ($message !== ''): ?><p role="status"><?= app_escape($message) ?></p><?php endif; ?><section><h2>1. Importar dados</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="import"><label>server-secret.bin<input type="file" name="secret" required></label><label>licenses.json<input type="file" name="licenses" required></label><label>accounts.json<input type="file" name="accounts" required></label><label>releases/index.json<input type="file" name="releases" required></label><button type="submit">Importar dados</button></form></section><section><h2>2. Instalar DLL</h2><p>Limite PHP: <?= app_escape((string)ini_get('upload_max_filesize')) ?> por arquivo.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="dll"><label>DLL da versao importada<input type="file" name="dll" accept=".dll" required></label><button type="submit">Instalar DLL</button></form></section><p><a href="<?= app_escape(app_url('adm/mk/')) ?>">Voltar ao painel de keys</a></p></div></main><script src="<?= app_escape(app_url('adm/admin-shell.js')) ?>" defer></script></body></html>
