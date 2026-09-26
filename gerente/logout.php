<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
try {$logoutDb=app_db();app_auth_forget($logoutDb,'manager');$logoutDb->close();}
catch(Throwable $logoutError){app_auth_remember_clear_cookie('manager');}
unset($_SESSION['manager_id']);
session_regenerate_id(true);
header('Location: '.app_url('gerente/login.php'),true,303); exit;
