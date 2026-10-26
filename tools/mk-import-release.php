<?php
// PHP 8.1 / tools/mk-import-release.php / CLI migration of latest OwnKeys release
if (PHP_SAPI !== 'cli' || $argc !== 2) { fwrite(STDERR, "Uso: php tools/mk-import-release.php CAMINHO_DADOS_MK\n"); exit(2); }
require_once dirname(__DIR__) . '/mk/lib.php';
$source = realpath($argv[1]);
if ($source === false) throw new RuntimeException('Pasta nao encontrada.');
$raw = @file_get_contents($source . '/releases/index.json');
$items = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $raw ?: ''), true);
if (!is_array($items) || !$items) throw new RuntimeException('Indice de versoes invalido.');
$db = app_db(); mk_install($db);
$dir = mk_release_dir();
if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Diretorio de releases indisponivel.');
$imported = 0;
foreach ($items as $item) {
    $sha = strtoupper((string)($item['Sha256'] ?? ''));
    $version = (string)($item['Version'] ?? '');
    $size = (int)($item['Size'] ?? 0);
    $published = strtotime((string)($item['PublishedAt'] ?? ''));
    if (!preg_match('/^[A-F0-9]{64}$/D', $sha) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/D', $version) || $size < 4096 || $size > 25 * 1024 * 1024 || !$published)
        throw new RuntimeException('Metadados de release invalidos.');
    $original = $source . '/releases/' . $sha . '.dll';
    if (!is_file($original) || filesize($original) !== $size || strtoupper(hash_file('sha256', $original)) !== $sha) throw new RuntimeException('DLL ausente ou hash incorreto.');
    mk_verify_dll($original);
    $target = $dir . '/' . $sha . '.dll';
    if (!is_file($target) && !copy($original, $target)) throw new RuntimeException('Falha ao copiar DLL.');
    app_query($db, 'INSERT INTO mk_releases (version,sha256,size,published_at,notes) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE version=version',
        [$version, $sha, (string)$size, (string)$published, (string)($item['Notes'] ?? '')]);
    $imported++;
}
echo "Importadas {$imported} versoes.\n";
