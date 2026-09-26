<?php
require_once __DIR__ . '/../app/bootstrap.php';
$affiliate = is_string($_GET['aff'] ?? null) && preg_match('/^\d{1,12}$/D', $_GET['aff']) ? $_GET['aff'] : '';
$managerCode = is_string($_GET['ref'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $_GET['ref']) ? $_GET['ref'] : '';
$managerInfluencerId = is_string($_GET['by'] ?? null) && preg_match('/^\d{1,12}$/D', $_GET['by']) ? $_GET['by'] : '';
if ($affiliate === '' && $managerCode === '' && ($_GET['continuar'] ?? '') !== '1') unset($_SESSION['landing_affiliate'], $_SESSION['landing_manager_code'], $_SESSION['landing_manager_influencer']);
if ($affiliate !== '' || $managerCode !== '') {
    if (!empty($_SESSION['email'])) { header('Location: ' . app_url('painel/'), true, 303); exit; }
    if ($affiliate !== '') $_SESSION['landing_affiliate'] = $affiliate; else unset($_SESSION['landing_affiliate']);
    if ($managerCode !== '') $_SESSION['landing_manager_code'] = $managerCode; else unset($_SESSION['landing_manager_code'], $_SESSION['landing_manager_influencer']);
    if ($managerCode !== '' && $managerInfluencerId !== '') $_SESSION['landing_manager_influencer'] = $managerInfluencerId; elseif ($managerCode !== '') unset($_SESSION['landing_manager_influencer']);
    if (($_GET['continuar'] ?? '') !== '1') {
        $query = array_filter(['aff' => $affiliate, 'ref' => $managerCode, 'by' => $managerInfluencerId]);
        header('Location: ' . app_url('presell/jogoteste/?' . http_build_query($query)), true, 303);
        exit;
    }
}
$authMode = 'register'; require dirname(__DIR__) . '/app/auth_page.php';
