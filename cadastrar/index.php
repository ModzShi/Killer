<?php
require_once __DIR__ . '/../app/bootstrap.php';
$affiliate = is_string($_GET['aff'] ?? null) && preg_match('/^\d{1,12}$/D', $_GET['aff']) ? $_GET['aff'] : '';
$managerCode = is_string($_GET['ref'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $_GET['ref']) ? $_GET['ref'] : '';
if ($affiliate !== '' || $managerCode !== '') {
    if (!empty($_SESSION['email'])) { header('Location: ' . app_url('painel/'), true, 303); exit; }
    if ($affiliate !== '') $_SESSION['landing_affiliate'] = $affiliate;
    if ($managerCode !== '') $_SESSION['landing_manager_code'] = $managerCode;
    if (($_GET['continuar'] ?? '') !== '1') {
        $query = array_filter(['aff' => $affiliate, 'ref' => $managerCode]);
        header('Location: ' . app_url('presell/jogoteste/?' . http_build_query($query)), true, 303);
        exit;
    }
}
$authMode = 'register'; require dirname(__DIR__) . '/app/auth_page.php';
