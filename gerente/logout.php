<?php
require_once __DIR__ . '/../app/bootstrap.php';
unset($_SESSION['manager_id']);
session_regenerate_id(true);
header('Location: '.app_url('gerente/login.php'),true,303); exit;
