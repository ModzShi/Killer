<?php
require_once __DIR__ . '/../app/bootstrap.php';
header('Location: ' . app_url('painel/'), true, 302);
exit;
