<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Public test approval cannot be used with real payment balances.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['message' => 'Aprovação de teste desativada. Consulte o pagamento na gateway.']);
exit;

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
    http_response_code(401);

    echo json_encode([
        'status' => 'error',
        'message' => 'Usuário não está logado.'
    ]);

    exit();
}

$email = $_SESSION['email'];
$token = trim($_GET['token'] ?? '');

if ($token === '') {
    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'message' => 'Token não informado.'
    ]);

    exit();
}

include './../conectarbanco.php';

$conn = new mysqli($config['db_host'] ?? 'localhost',
    $config['db_user'],
    $config['db_pass'],
    $config['db_name']
);

if ($conn->connect_error) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Erro de conexão com o banco: ' . $conn->connect_error
    ]);

    exit();
}

$conn->begin_transaction();

try {

    /*
     * Procura o depósito pelo token.
     * Não usamos "id", pois essa tabela não possui essa coluna.
     */
    $stmt = $conn->prepare(
        "SELECT valor, status
         FROM confirmar_deposito
         WHERE externalreference = ?
         AND email = ?
         LIMIT 1
         FOR UPDATE"
    );

    if (!$stmt) {
        throw new Exception(
            'Erro ao preparar consulta: ' . $conn->error
        );
    }

    $stmt->bind_param('ss', $token, $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $deposito = $result->fetch_assoc();

    $stmt->close();

    if (!$deposito) {
        throw new Exception(
            'Depósito não encontrado para este usuário.'
        );
    }

    $valor = (float) $deposito['valor'];
    $status = $deposito['status'];

    /*
     * Impede crédito duplicado.
     */
    if ($status === 'PAID_OUT') {

        $conn->commit();
        $conn->close();

        echo json_encode([
            'status' => 'already_paid',
            'message' => 'Este depósito já foi aprovado anteriormente.',
            'valor' => $valor
        ]);

        exit();
    }

    if ($status !== 'WAITING_FOR_APPROVAL') {
        throw new Exception(
            'O depósito não está aguardando aprovação. Status atual: ' .
            $status
        );
    }

    /*
     * Marca o depósito como pago.
     */
    $stmt = $conn->prepare(
        "UPDATE confirmar_deposito
         SET status = 'PAID_OUT'
         WHERE externalreference = ?
         AND email = ?"
    );

    if (!$stmt) {
        throw new Exception(
            'Erro ao preparar atualização: ' . $conn->error
        );
    }

    $stmt->bind_param('ss', $token, $email);
    $stmt->execute();
    $stmt->close();

    /*
     * Adiciona o valor ao saldo.
     */
    $stmt = $conn->prepare(
        "UPDATE appconfig
         SET saldo = saldo + ?,
             depositou = depositou + ?
         WHERE email = ?"
    );

    if (!$stmt) {
        throw new Exception(
            'Erro ao preparar atualização do saldo: ' . $conn->error
        );
    }

    $stmt->bind_param(
        'dds',
        $valor,
        $valor,
        $email
    );

    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception(
            'Nenhum usuário encontrado em appconfig com este email.'
        );
    }

    $stmt->close();

    /*
     * Confirma a transação.
     */
    $conn->commit();
    $conn->close();

    echo json_encode([
        'status' => 'PAID_OUT',
        'message' => 'Depósito de teste aprovado com sucesso.',
        'valor' => $valor
    ]);

} catch (Throwable $e) {

    $conn->rollback();
    $conn->close();

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
