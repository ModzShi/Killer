<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';

try {
    $logoutDb=app_db();
    foreach(['player','admin','manager'] as $logoutScope) app_auth_forget($logoutDb,$logoutScope);
    $logoutDb->close();
} catch (Throwable $logoutError) {
    foreach(['player','admin','manager'] as $logoutScope) app_auth_remember_clear_cookie($logoutScope);
}

$_SESSION = array();
session_regenerate_id(true);

header("Location: index.php",true,303);
exit();
?>
