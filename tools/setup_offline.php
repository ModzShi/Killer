<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('SK_SETUP_OFFLINE', true);
require dirname(__DIR__) . '/conectarbanco.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
$source = $config['db_name'];
$target = $source . '_offline';
if (!preg_match('/^[a-zA-Z0-9_]+$/D', $source) || strlen($target) > 64) throw new RuntimeException('Nome do banco inválido.');

$exists = $db->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '" . $db->real_escape_string($target) . "'")->num_rows > 0;
if (!$exists) $db->query("CREATE DATABASE `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$created = 0;
$tables = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all();
foreach ($tables as $table) {
    $rawName = $table[0];
    $name = str_replace('`', '``', $rawName);
    $present = $db->query("SHOW TABLES FROM `$target` LIKE '" . $db->real_escape_string($rawName) . "'")->num_rows > 0;
    if ($present) continue;
    $db->query("CREATE TABLE `$target`.`$name` LIKE `$source`.`$name`");
    $created++;
    if (in_array($rawName, ['app', 'appconfig', 'admlogin'], true)) {
        $db->query("INSERT INTO `$target`.`$name` SELECT * FROM `$source`.`$name`");
    }
}

if (!$exists) {
    $db->query("UPDATE `$target`.appconfig SET saldo = 0, depositou = 0, sacou = 0, ganhos = 0, percas = 0, comissaofake = 0, cont_cpa = 0, status_primeiro_deposito = 0");
    echo "Banco offline criado; contas copiadas com saldo fictício zerado. Nenhum saldo original foi alterado.\n";
} else {
    echo "Banco offline atualizado; $created tabela(s) ausente(s) criada(s), dados preservados.\n";
}
