<?php
// PHP 8.1 / tools/mk-export.php / CLI export for phpMyAdmin
if (PHP_SAPI !== 'cli' || $argc !== 2) { fwrite(STDERR, "Uso: php tools/mk-export.php ARQUIVO.sql\n"); exit(2); }
require_once dirname(__DIR__) . '/mk/lib.php';
$db = app_db();
$fp = fopen($argv[1], 'xb');
if (!$fp) throw new RuntimeException('Arquivo de saida ja existe ou nao pode ser criado.');
function mk_sql_value(mysqli $db, string $table, string $column, $value): string {
    if ($value === null) return 'NULL';
    if ((in_array($column, ['created_at', 'expires_at', 'duration_seconds', 'activated_at', 'revoked', 'last_login_at', 'size', 'published_at'], true) || ($table === 'mk_releases' && $column === 'id')) && is_numeric($value))
        return (string)(int)$value;
    return "'" . $db->real_escape_string((string)$value) . "'";
}
try {
    fwrite($fp, "SET NAMES utf8mb4;\nSTART TRANSACTION;\n");
    foreach (['mk_licenses', 'mk_accounts', 'mk_releases'] as $table) {
        $result = $db->query('SELECT * FROM `' . $table . '`');
        while ($row = $result->fetch_assoc()) {
            $cols = implode(',', array_map(static fn($col) => '`' . $col . '`', array_keys($row)));
            $values = implode(',', array_map(static fn($column, $value) => mk_sql_value($db, $table, $column, $value), array_keys($row), array_values($row)));
            fwrite($fp, "INSERT INTO `{$table}` ({$cols}) VALUES ({$values}) ON DUPLICATE KEY UPDATE `id`=`id`;\n");
        }
    }
    fwrite($fp, "COMMIT;\n");
} finally { fclose($fp); $db->close(); }
echo "Exportacao criada.\n";
