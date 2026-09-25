<?php
require_once __DIR__ . '/../app/bootstrap.php';

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['message' => 'O saldo de depósito é atualizado somente após confirmação da gateway.']);
exit;
$valor_transacao_multiplicado = isset($_GET["valor_transacao_multiplicado"])
    ? $_GET["valor_transacao_multiplicado"]
    : 0;

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

include "./../conectarbanco.php";

$conn = new mysqli($config['db_host'] ?? 'localhost',
    $config["db_user"],
    $config["db_pass"],
    $config["db_name"]
);

if ($conn->connect_error) {
    die("Falha na conexão com o banco de dados: " . $conn->connect_error);
}

if (isset($_SESSION["email"])) {
    $email = $_SESSION["email"];

    $saldo = isset($_POST["valor_transacao_multiplicado"])
        ? $_POST["valor_transacao_multiplicado"]
        : 0;

    $sql = "UPDATE appconfig SET saldo = saldo + '$saldo' WHERE email = '$email'";

    if ($conn->query($sql) === true) {
        echo "Saldo atualizado com sucesso!";
    } else {
        echo "Erro ao atualizar o saldo: " . $conn->error;
    }
} else {
    echo "Email não encontrado na sessão.";
}

$conn->close();
?>
