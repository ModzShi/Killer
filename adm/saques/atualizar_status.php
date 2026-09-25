<?php
require_once __DIR__ . '/../../app/withdrawal.php';

include './../../conectarbanco.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!isset($_SESSION['emailadm'])) {
    http_response_code(403);
    echo "Não autorizado";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $novoStatus = $_POST['novoStatus'];
    $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'Jogo';

    $conn = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
    withdrawal_install($conn);

    if ($conn->connect_error) {
        die("Erro na conexão com o banco de dados: " . $conn->connect_error);
    }

    if ($tipo === 'Afiliado') {
        $stmt = $conn->prepare("UPDATE saque_afiliado SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $novoStatus, $id);
    } else {
        $stmt = $conn->prepare("UPDATE saques SET status = ? WHERE externalreference = ?");
        $stmt->bind_param("ss", $novoStatus, $id);
    }

    if ($stmt->execute()) {
        echo "Status atualizado com sucesso!";
    } else {
        echo "Erro ao atualizar o status: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo "Método não permitido";
}
?>
