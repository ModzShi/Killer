<?php
// PHP 8.1 / mk/lib.php / Subway MK service
require_once dirname(__DIR__) . '/app/auth.php';

function mk_install(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS mk_licenses (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        seller VARCHAR(64) NOT NULL,
        key_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        suffix CHAR(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        created_at BIGINT NOT NULL,
        expires_at BIGINT NULL,
        duration_seconds BIGINT NULL,
        activated_at BIGINT NULL,
        revoked TINYINT(1) NOT NULL DEFAULT 0,
        device VARCHAR(128) NULL,
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS mk_accounts (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        username VARCHAR(32) NOT NULL UNIQUE,
        password_salt CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        password_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        license_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        created_at BIGINT NOT NULL,
        last_login_at BIGINT NULL,
        device VARCHAR(128) NOT NULL,
        CONSTRAINT fk_mk_account_license FOREIGN KEY (license_id) REFERENCES mk_licenses(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS mk_releases (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        version VARCHAR(32) NOT NULL UNIQUE,
        sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        size BIGINT NOT NULL,
        published_at BIGINT NOT NULL,
        notes VARCHAR(1000) NOT NULL DEFAULT '',
        INDEX (published_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function mk_secret(): string {
    $external = dirname(SK_ROOT) . '/mk-private/server-secret.bin';
    $path = getenv('MK_SECRET_FILE') ?: (is_file($external) ? $external : SK_ROOT . '/config/mk-secret.bin');
    if (!is_file($path)) throw new RuntimeException('Segredo MK ausente. Instale ou migre o segredo antes de emitir keys.');
    $secret = file_get_contents($path);
    if ($secret === false || strlen($secret) !== 32) throw new RuntimeException('Segredo MK invalido.');
    return $secret;
}

function mk_key_hash(string $key): string { return strtoupper(hash_hmac('sha256', strtoupper(trim($key)), mk_secret())); }
function mk_valid_user(string $user): bool { return preg_match('/^[A-Za-z0-9_.-]{3,32}$/D', $user) === 1; }
function mk_valid_device(string $device): bool { return strlen($device) >= 8 && strlen($device) <= 128; }
function mk_denied(): array { return ['ok' => false, 'username' => '', 'key' => '', 'expiresAt' => 0]; }

function mk_register(mysqli $db, string $key, string $user, string $pass, string $device): array {
    if (strlen($key) < 8 || strlen($key) > 128 || !mk_valid_user($user) || strlen($pass) < 6 || strlen($pass) > 128 || !mk_valid_device($device)) return mk_denied();
    $db->begin_transaction();
    try {
        $license = app_query($db, 'SELECT * FROM mk_licenses WHERE key_hash=? FOR UPDATE', [mk_key_hash($key)])->get_result()->fetch_assoc();
        $now = time();
        if (!$license || (int)$license['revoked'] || ($license['duration_seconds'] === null && (int)$license['expires_at'] <= $now) ||
            ($license['device'] !== null && $license['device'] !== $device) ||
            app_query($db, 'SELECT id FROM mk_accounts WHERE license_id=? OR username=? LIMIT 1', [$license['id'], $user])->get_result()->num_rows) {
            $db->rollback(); return mk_denied();
        }
        $expires = $license['duration_seconds'] !== null ? $now + (int)$license['duration_seconds'] : (int)$license['expires_at'];
        $salt = random_bytes(16);
        $hash = hash_pbkdf2('sha256', $pass, $salt, 210000, 32, true);
        app_query($db, 'INSERT INTO mk_accounts (id,username,password_salt,password_hash,license_id,created_at,device) VALUES (?,?,?,?,?,?,?)',
            [bin2hex(random_bytes(16)), $user, strtoupper(bin2hex($salt)), strtoupper(bin2hex($hash)), $license['id'], (string)$now, $device]);
        app_query($db, 'UPDATE mk_licenses SET activated_at=?,expires_at=?,device=? WHERE id=?', [(string)$now, (string)$expires, $device, $license['id']]);
        $db->commit();
        return ['ok' => true, 'username' => $user, 'key' => $key, 'expiresAt' => $expires];
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

function mk_login(mysqli $db, string $user, string $pass, string $device): array {
    if (!mk_valid_user($user) || strlen($pass) < 6 || strlen($pass) > 128 || !mk_valid_device($device)) return mk_denied();
    $row = app_query($db, 'SELECT a.*, l.suffix, l.expires_at, l.duration_seconds, l.activated_at, l.revoked FROM mk_accounts a JOIN mk_licenses l ON l.id=a.license_id WHERE a.username=? LIMIT 1', [$user])->get_result()->fetch_assoc();
    if (!$row || $row['device'] !== $device) return mk_denied();
    $salt = hex2bin($row['password_salt']); $stored = hex2bin($row['password_hash']);
    if ($salt === false || $stored === false || !hash_equals($stored, hash_pbkdf2('sha256', $pass, $salt, 210000, 32, true)) || (int)$row['revoked']) return mk_denied();
    $expires = $row['expires_at'] === null ? 0 : (int)$row['expires_at'];
    if ($row['duration_seconds'] !== null && $row['activated_at'] === null) {
        $expires = (int)$row['created_at'] + (int)$row['duration_seconds'];
        app_query($db, 'UPDATE mk_licenses SET activated_at=?,expires_at=? WHERE id=? AND activated_at IS NULL', [$row['created_at'], (string)$expires, $row['license_id']]);
    }
    if ($expires <= time()) return mk_denied();
    app_query($db, 'UPDATE mk_accounts SET last_login_at=? WHERE id=?', [(string)time(), $row['id']]);
    return ['ok' => true, 'username' => $row['username'], 'key' => 'EC-...' . $row['suffix'], 'expiresAt' => $expires];
}

function mk_issue(mysqli $db, int $days, string $seller): string {
    $seller = trim($seller);
    if ($days < 1 || $days > 3650 || $seller === '' || strlen($seller) > 64) throw new InvalidArgumentException('Informe vendedor e duracao de 1 a 3650 dias.');
    $random = strtoupper(bin2hex(random_bytes(16)));
    $key = 'EC-' . implode('-', str_split($random, 8));
    app_query($db, 'INSERT INTO mk_licenses (id,seller,key_hash,suffix,created_at,duration_seconds) VALUES (?,?,?,?,?,?)',
        [bin2hex(random_bytes(16)), $seller, mk_key_hash($key), substr($key, -4), (string)time(), (string)($days * 86400)]);
    return $key;
}

function mk_release_dir(): string {
    $external = dirname(SK_ROOT) . '/mk-private/releases';
    return getenv('MK_RELEASE_DIR') ?: (is_dir($external) ? $external : SK_ROOT . '/config/mk-releases');
}
function mk_release_info(array $row): array {
    return ['version' => $row['version'], 'sha256' => $row['sha256'], 'size' => (int)$row['size'],
        'publishedAt' => gmdate('Y-m-d\TH:i:s+00:00', (int)$row['published_at']), 'notes' => $row['notes'],
        'downloadPath' => app_url('mk/api.php?route=releases/file&sha=' . $row['sha256'])];
}

function mk_verify_dll(string $path): void {
    $fp = fopen($path, 'rb');
    if (!$fp) throw new RuntimeException('Nao foi possivel abrir a DLL.');
    try {
        $head = fread($fp, 64);
        if (strlen($head) < 64 || substr($head, 0, 2) !== 'MZ') throw new InvalidArgumentException('Arquivo nao e PE.');
        $offset = unpack('V', substr($head, 0x3c, 4))[1];
        if ($offset < 64 || $offset > filesize($path) - 24) throw new InvalidArgumentException('Cabecalho PE invalido.');
        fseek($fp, $offset); $pe = fread($fp, 24);
        if (substr($pe, 0, 4) !== "PE\0\0" || unpack('v', substr($pe, 4, 2))[1] !== 0x8664 || !(unpack('v', substr($pe, 22, 2))[1] & 0x2000))
            throw new InvalidArgumentException('DLL deve ser PE x64.');
    } finally { fclose($fp); }
}
