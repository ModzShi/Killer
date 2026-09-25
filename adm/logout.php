<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

unset($_SESSION['emailadm']);

header("Location: login");
exit();
?>