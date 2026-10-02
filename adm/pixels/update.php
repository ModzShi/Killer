<?php
require_once __DIR__ . '/../../app/auth.php';

if (empty($_SESSION['emailadm'])) { http_response_code(403); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
if (!app_check_csrf()) { http_response_code(403); exit('Formulário expirado.'); }

$field = is_string($_GET['field'] ?? null) ? $_GET['field'] : '';
$value = strtoupper(trim(app_input('value')));
$patterns = [
    'google_ads_tag' => '/^(?:G-[A-Z0-9]{6,16}|AW-[0-9]{6,16}|GT-[A-Z0-9]{6,16})$/D',
    'facebook_ads_tag' => '/^[0-9]{6,25}$/D',
];
if (!isset($patterns[$field]) || ($value !== '' && !preg_match($patterns[$field], $value))) {
    http_response_code(400); exit('Identificador de pixel inválido.');
}

try {
    $db = app_db();
    $exists = $db->query('SELECT 1 FROM app LIMIT 1')->fetch_row();
    $sql = $exists ? "UPDATE app SET `$field`=? LIMIT 1" : "INSERT INTO app (`$field`) VALUES(?)";
    app_query($db, $sql, [$value]);
    header('Location: '.app_url('adm/pixels/'), true, 303);
} catch (Throwable $e) {
    error_log('admin pixel settings: '.$e->getMessage());
    http_response_code(503); echo 'Não foi possível salvar o identificador.';
}
