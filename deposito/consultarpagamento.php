<?php
require_once __DIR__ . '/../app/bootstrap.php';

/**
 * deposito/consultarpagamento.php
 * Polling de status do depósito — chamado pelo JS a cada 2s.
 * Retorna JSON: { "status": "WAITING_FOR_APPROVAL" } ou { "status": "PAID_OUT" }
 */

if (!isset($_GET['token'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Token ausente']);
    exit();
}

$uuid = trim($_GET['token']);

include './../conectarbanco.php';
$conn = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['message' => 'Erro de banco']);
    exit();
}

// Busca por externalreference
$stmt = $conn->prepare(
    "SELECT status FROM confirmar_deposito
     WHERE externalreference = ?
     LIMIT 1"
);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$stmt->bind_result($status);
$stmt->fetch();
$stmt->close();
$conn->close();

if (!$status) {
    http_response_code(400);
    echo json_encode(['message' => 'Token inválido']);
    exit();
}

http_response_code(200);
echo json_encode(['status' => $status]);
