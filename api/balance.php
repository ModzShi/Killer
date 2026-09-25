<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

if (empty($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = app_db();
    $row = app_query($db, 'SELECT saldo FROM appconfig WHERE email=? LIMIT 1', [(string) $_SESSION['email']])->get_result()->fetch_assoc();
    if (!$row) throw new RuntimeException('Conta não encontrada.');
    $balance = round((float) $row['saldo'], 2);
    echo json_encode([
        'ok' => true,
        'balance' => $balance,
        'formatted' => 'R$ ' . number_format($balance, 2, ',', '.'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
}
