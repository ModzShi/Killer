<?php
require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) { http_response_code(413); exit; }
require_once dirname(__DIR__) . '/payments/bxpay_service.php';
require dirname(__DIR__) . '/conectarbanco.php';
try {
    $payload = json_decode(file_get_contents('php://input', false, null, 0, 65537), true);
    $body = $payload['requestBody'] ?? $payload;
    if (!is_array($body) || ($body['transactionType'] ?? '') !== 'RECEIVEPIX' || ($body['status'] ?? '') !== 'PAID') { http_response_code(400); exit; }
    $external = is_string($body['external_id'] ?? null) ? $body['external_id'] : '';
    $id = is_string($body['transactionId'] ?? null) ? $body['transactionId'] : '';
    if ($external === '' && $id === '') { http_response_code(400); exit; }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
    $stmt = $db->prepare('SELECT reference FROM bxpay_deposits WHERE reference = ? OR provider_id = ? LIMIT 1');
    $stmt->bind_param('ss', $external, $id); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) { http_response_code(404); exit; }
    // This public payload is only a trigger. Trust exclusively the authenticated API.
    $paid = bxpay_reconcile($db, BXPay::fromDb($db), $row['reference']);
    http_response_code($paid ? 200 : 503);
    echo json_encode(['confirmed' => $paid]);
} catch (Throwable $error) { http_response_code(503); echo json_encode(['confirmed' => false]); }
