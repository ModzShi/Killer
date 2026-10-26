<?php
// PHP 8.1 / adm/mk/index.php / Subway MK admin panel
require_once dirname(__DIR__, 2) . '/mk/lib.php';
$db = app_db();
$flash = (string)($_SESSION['mk_flash'] ?? '');
$newKey = (string)($_SESSION['mk_new_key'] ?? '');
unset($_SESSION['mk_flash'], $_SESSION['mk_new_key']);
try {
    mk_install($db);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'issue') $newKey = mk_issue($db, (int)($_POST['days'] ?? 0), (string)($_POST['seller'] ?? ''));
        elseif ($action === 'revoke') {
            $id = (string)($_POST['id'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw new InvalidArgumentException('Key invalida.');
            app_query($db, 'UPDATE mk_licenses SET revoked=1 WHERE id=?', [$id]);
            $flash = 'Key revogada.';
        } elseif ($action === 'release') {
            $version = trim((string)($_POST['version'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            $file = $_FILES['dll'] ?? null;
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/D', $version) || strlen($notes) > 1000 || !is_array($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK)
                throw new InvalidArgumentException('Confira versao, notas e arquivo DLL.');
            $size = (int)$file['size'];
            if ($size < 4096 || $size > 25 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('DLL invalida ou acima de 25 MB.');
            mk_verify_dll($file['tmp_name']);
            $sha = strtoupper(hash_file('sha256', $file['tmp_name']));
            $dir = mk_release_dir();
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Diretorio de releases indisponivel.');
            $target = $dir . '/' . $sha . '.dll';
            if (!is_file($target) && !move_uploaded_file($file['tmp_name'], $target)) throw new RuntimeException('Falha ao salvar DLL.');
            app_query($db, 'INSERT INTO mk_releases (version,sha256,size,published_at,notes) VALUES (?,?,?,?,?)', [$version, $sha, (string)$size, (string)time(), $notes]);
            $flash = 'Versao publicada.';
        } else throw new InvalidArgumentException('Acao invalida.');
        $_SESSION['mk_flash'] = $flash;
        $_SESSION['mk_new_key'] = $newKey;
        header('Location: ' . app_url('adm/mk/'), true, 303);
        exit;
    }
} catch (Throwable $e) { $flash = $e->getMessage(); }
$licenses = $db->query('SELECT l.*, a.username, a.created_at AS account_created_at, a.last_login_at FROM mk_licenses l LEFT JOIN mk_accounts a ON a.license_id=l.id ORDER BY l.created_at DESC LIMIT 200')->fetch_all(MYSQLI_ASSOC);
$release = $db->query('SELECT * FROM mk_releases ORDER BY id DESC LIMIT 1')->fetch_assoc();
$db->close();
$adminActive = 'mk'; $adminTitle = 'MK Keys';
function mk_date(?string $epoch): string { return $epoch === null ? 'Aguardando cadastro' : ((int)$epoch >= 253402300799 ? 'Permanente' : gmdate('d/m/Y H:i', (int)$epoch) . ' UTC'); }
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>MK Keys - Subway</title><link rel="stylesheet" href="<?= app_escape(app_url('gerente/manager-premium.css')) ?>"><link rel="stylesheet" href="<?= app_escape(app_url('adm/admin-shell.css')) ?>"><style>.mk-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:18px}.mk-box{padding:22px;border-radius:18px;background:#191d27}.mk-box label{display:block;margin:14px 0}.mk-box input,.mk-box textarea{display:block;width:100%;padding:11px;margin-top:6px}.mk-box button{padding:11px 18px;cursor:pointer}.mk-table{width:100%;border-collapse:collapse}.mk-table td,.mk-table th{padding:10px;text-align:left;border-bottom:1px solid #333}.mk-scroll{overflow-x:auto}.mk-key{font-size:1.25rem;user-select:all}.mk-muted{opacity:.7}</style></head><body class="admin-app">
<?php require dirname(__DIR__) . '/components/panel-nav.php'; ?>
<div class="view-heading"><div><small>SUBWAY / MK</small><h1>Keys e atualizacoes</h1><p>A validade comeca no cadastro. O vendedor e o tempo restante aparecem abaixo.</p><a href="<?= app_escape(app_url('adm/mk/setup.php')) ?>">Migrar dados MK</a></div></div>
<?php if ($flash !== ''): ?><p role="status" class="mk-box"><?= app_escape($flash) ?></p><?php endif; ?>
<?php if ($newKey !== ''): ?><div class="mk-box"><strong>Nova key: copie agora</strong><p class="mk-key"><?= app_escape($newKey) ?></p><small>A key completa nao sera mostrada novamente.</small></div><?php endif; ?>
<div class="mk-grid"><section class="mk-box"><h2>Gerar key</h2><form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="issue"><label>Vendedor<input name="seller" maxlength="64" required></label><label>Dias de acesso apos cadastro<input name="days" type="number" min="1" max="3650" required></label><button type="submit">Gerar key</button></form></section>
<section class="mk-box"><h2>Publicar DLL</h2><p>Atual: <?= $release ? app_escape($release['version']) : 'Nenhuma' ?></p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="release"><label>Versao<input name="version" maxlength="32" required></label><label>Notas<textarea name="notes" maxlength="1000"></textarea></label><label>DLL x64 (ate 25 MB)<input type="file" name="dll" accept=".dll" required></label><button type="submit">Publicar versao</button></form></section></div>
<section class="mk-box"><h2>Keys e contas</h2><div class="mk-scroll"><table class="mk-table"><thead><tr><th>Key</th><th>Vendedor</th><th>Usuario</th><th>Criada</th><th>Ativada</th><th>Expira</th><th>Tempo restante</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach ($licenses as $item): $registered = $item['username'] !== null; $activatedAt = $item['activated_at'] ?? $item['account_created_at']; $pending = !$registered && $activatedAt === null; $remaining = $item['expires_at'] === null ? (int)$item['duration_seconds'] : max(0, (int)$item['expires_at'] - time()); ?><tr><td>...<?= app_escape($item['suffix']) ?></td><td><?= app_escape($item['seller']) ?></td><td><?= app_escape($item['username'] ?? '-') ?></td><td><?= mk_date($item['created_at']) ?></td><td><?= mk_date($activatedAt) ?></td><td><?= mk_date($item['expires_at']) ?></td><td><span class="mk-countdown" data-seconds="<?= (int)$remaining ?>" data-pending="<?= $pending ? '1' : '0' ?>"><?= (int)$remaining ?> s</span></td><td><?= (int)$item['revoked'] ? 'Revogada' : ($item['expires_at'] !== null && $remaining === 0 ? 'Expirada' : ($pending ? 'Pendente' : 'Ativa')) ?></td><td><?php if (!(int)$item['revoked']): ?><form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= app_escape($item['id']) ?>"><button type="submit">Revogar</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</div></main><script>
const mkStarted = performance.now();
function mkCountdown() {
  document.querySelectorAll('.mk-countdown').forEach(el => {
    const initial = Number(el.dataset.seconds || 0);
    const pending = el.dataset.pending === '1';
    if (initial > 100 * 365 * 86400) { el.textContent = 'Permanente'; return; }
    let seconds = Math.max(0, initial - (pending ? 0 : Math.floor((performance.now() - mkStarted) / 1000)));
    const days = Math.floor(seconds / 86400); seconds %= 86400;
    const hours = Math.floor(seconds / 3600); seconds %= 3600;
    const minutes = Math.floor(seconds / 60); seconds %= 60;
    el.textContent = `${pending ? 'Ao ativar: ' : ''}${days}d ${hours}h ${minutes}m ${seconds}s`;
  });
}
mkCountdown(); setInterval(mkCountdown, 1000);
</script><script src="<?= app_escape(app_url('adm/admin-shell.js')) ?>" defer></script></body></html>
