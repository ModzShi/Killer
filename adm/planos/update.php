<?php
require_once __DIR__ . '/../../app/auth.php';

if (empty($_SESSION['emailadm'])) { http_response_code(403); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
if (!app_check_csrf()) { http_response_code(403); exit('Formulário expirado.'); }

$limits = [
    'cpa' => 1000000,
    'chance_afiliado' => 100,
    'deposito_min_cpa' => 1000000,
    'max_saque_cpa' => 1000,
    'revenue_share' => 100,
];
$field = is_string($_GET['field'] ?? null) ? $_GET['field'] : '';
$raw = str_replace(',', '.', trim(app_input('value')));
if (!isset($limits[$field]) || !preg_match('/^(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,2})?$/D', $raw)
    || (float)$raw > $limits[$field] || ($field === 'max_saque_cpa' && str_contains($raw, '.'))) {
    http_response_code(400); exit('Campo ou valor inválido.');
}

try {
    $db = app_db();
    $value = number_format((float)$raw, $field === 'max_saque_cpa' ? 0 : 2, '.', '');
    $exists = $db->query('SELECT 1 FROM app LIMIT 1')->fetch_row();
    $sql = $exists ? "UPDATE app SET `$field`=? LIMIT 1" : "INSERT INTO app (`$field`) VALUES(?)";
    app_query($db, $sql, [$value]);
    header('Location: '.app_url('adm/planos/'), true, 303);
} catch (Throwable $e) {
    error_log('admin affiliate settings: '.$e->getMessage());
    http_response_code(503); echo 'Não foi possível salvar a configuração.';
}
