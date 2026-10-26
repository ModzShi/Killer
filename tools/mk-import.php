<?php
// PHP 8.1 / tools/mk-import.php / CLI migration from OwnKeys JSON
if (PHP_SAPI !== 'cli' || $argc !== 2) { fwrite(STDERR, "Uso: php tools/mk-import.php CAMINHO_DADOS_MK\n"); exit(2); }
require_once dirname(__DIR__) . '/mk/lib.php';
$source = realpath($argv[1]);
if ($source === false) { fwrite(STDERR, "Pasta nao encontrada.\n"); exit(2); }
$secret = @file_get_contents($source . '/server-secret.bin');
$licenses = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', @file_get_contents($source . '/licenses.json') ?: ''), true);
$accounts = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', @file_get_contents($source . '/accounts.json') ?: ''), true);
if (strlen((string)$secret) !== 32 || !is_array($licenses) || !is_array($accounts)) { fwrite(STDERR, "Dados MK incompletos.\n"); exit(2); }
$config = SK_ROOT . '/config';
if (!is_dir($config) && !mkdir($config, 0700, true)) throw new RuntimeException('Nao foi possivel criar config.');
$targetSecret = $config . '/mk-secret.bin';
if (is_file($targetSecret) && !hash_equals((string)file_get_contents($targetSecret), $secret)) throw new RuntimeException('Segredo MK diferente. Importacao cancelada.');
if (!is_file($targetSecret)) file_put_contents($targetSecret, $secret, LOCK_EX);
$db = app_db(); mk_install($db);
$db->begin_transaction();
try {
    foreach ($licenses as $item) {
        $created = strtotime($item['CreatedAt'] ?? '') ?: 0;
        $expiry = isset($item['ExpiresAt']) ? strtotime($item['ExpiresAt']) : null;
        $activated = isset($item['ActivatedAt']) ? strtotime($item['ActivatedAt']) : null;
        if (!$created || $expiry === false || $activated === false) throw new RuntimeException('Data de key invalida.');
        // DateTimeOffset.MaxValue in the old store means a permanent key.
        if ($expiry !== null && $expiry > 253402300799) $expiry = 253402300799;
        app_query($db, 'INSERT INTO mk_licenses (id,seller,key_hash,suffix,created_at,expires_at,duration_seconds,activated_at,revoked,device) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',
            [$item['Id'], $item['Label'], $item['KeyHash'], $item['Suffix'], (string)$created,
             $expiry === null ? null : (string)$expiry, isset($item['DurationSeconds']) ? (string)$item['DurationSeconds'] : null,
             $activated === null ? null : (string)$activated, !empty($item['Revoked']) ? '1' : '0', $item['Device'] ?? null]);
    }
    foreach ($accounts as $item) {
        $created = strtotime($item['CreatedAt'] ?? '') ?: 0;
        $last = isset($item['LastLoginAt']) ? strtotime($item['LastLoginAt']) : null;
        if (!$created || $last === false) throw new RuntimeException('Data de conta invalida.');
        app_query($db, 'INSERT INTO mk_accounts (id,username,password_salt,password_hash,license_id,created_at,last_login_at,device) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',
            [$item['Id'], $item['Username'], $item['PasswordSalt'], $item['PasswordHash'], $item['LicenseId'],
             (string)$created, $last === null ? null : (string)$last, $item['Device']]);
    }
    $db->commit();
    echo 'Importadas ' . count($licenses) . ' keys e ' . count($accounts) . " contas.\n";
} catch (Throwable $e) { $db->rollback(); throw $e; }
