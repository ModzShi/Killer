<?php
require_once __DIR__ . '/../../app/bootstrap.php';

/**
 * adm/saques-afiliados/aprovar.php
 * Marca saque de afiliado como "Pago" — pagamento manual pelo admin.
 * NÃO aciona nenhum gateway — o admin realiza o PIX pelo próprio banco.
 */

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['emailadm'])) {
    http_response_code(403);
    echo 'Não autorizado';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método não permitido';
    exit();
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'ID inválido';
    exit();
}

include './../../conectarbanco.php';
$conn = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);

if ($conn->connect_error) {
    http_response_code(500);
    echo 'Erro de conexão';
    exit();
}

$novoStatus = 'Pago';
$stmt = $conn->prepare("UPDATE saque_afiliado SET status = ? WHERE id = ?");
$stmt->bind_param('si', $novoStatus, $id);

if ($stmt->execute()) {
    echo 'Saque marcado como Pago com sucesso! Lembre-se de realizar o PIX manualmente.';
} else {
    echo 'Erro ao atualizar: ' . $conn->error;
}

$stmt->close();
$conn->close();
