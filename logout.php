<?php
require_once __DIR__ . '/app/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$_SESSION = array();

header("Location: index.php");
exit();
?>
