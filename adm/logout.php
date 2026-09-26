<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

try {$logoutDb=app_db();app_auth_forget($logoutDb,'admin');$logoutDb->close();}
catch(Throwable $logoutError){app_auth_remember_clear_cookie('admin');}

unset($_SESSION['emailadm']);
session_regenerate_id(true);

header("Location: login",true,303);
exit();
?>
