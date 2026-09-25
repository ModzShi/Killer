<?php
require_once __DIR__ . '/../app/bootstrap.php';

/**
 * webhook/pix.php — KnucklesPay Webhook Handler
 *
 * Payload recebido da KnucklesPay:
 *   {
 *     "transactionType": "RECEIVEPIX",
 *     "transactionId": "<id da transação>",
 *     "amount": 150.99,
 *     "status": "PAID",
 *     ...
 *   }
 *
 * Header de assinatura:
 *   X-KnucklesPay-Signature: sha256=<hmac-sha256>
 */

ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$rawPayload = file_get_contents('php://input');

require_once dirname(__DIR__) . '/knucklespay.php';

include dirname(__DIR__) . '/conectarbanco.php';
$conn = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
if ($conn->connect_error) {
    http_response_code(500);
    exit();
}

$kp = KnucklesPay::fromDb($conn);

// ----- Validação HMAC ----------------------------------------
$signature = $_SERVER['HTTP_X_KNUCKLESPAY_SIGNATURE'] ?? '';

if (!$kp->validarWebhook($rawPayload, $signature)) {
    error_log('[KnucklesPay Webhook] Assinatura inválida. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    http_response_code(401);
    exit();
}

// ----- Decodificar payload -----------------------------------
$payload = json_decode($rawPayload, true);
if (!is_array($payload)) {
    http_response_code(400);
    exit();
}

$transactionType = $payload['transactionType'] ?? '';
$transactionId   = $payload['transactionId'] ?? '';
// BX Pay deposits must be confirmed using the BX Pay authenticated API.
if (is_string($transactionId) && strpos($transactionId, 'bx_') === 0) { http_response_code(400); exit; }
$status          = strtolower($payload['status'] ?? '');

if (empty($transactionId)) {
    http_response_code(400);
    exit();
}

// ----- Processar apenas pagamentos recebidos -----------------
if ($transactionType === 'RECEIVEPIX' && in_array($status, ['paid', 'approved', 'completed'], true)) {

    // Buscar depósito pelo transactionId
    $stmt = $conn->prepare(
        "SELECT * FROM confirmar_deposito
         WHERE externalreference = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $transactionId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result) {
        $conn->close();
        http_response_code(404);
        exit();
    }

    // Idempotência: já processado
    if ($result['status'] === 'PAID_OUT') {
        $conn->close();
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Já processado']);
        exit();
    }

    // IMPORTANTE: usar SEMPRE o valor do banco — nunca o amount do webhook
    $valorDepositado = floatval($result['valor']);
    $emailUsuario    = $result['email'];

    // Marcar depósito como pago
    $stmtUpd = $conn->prepare("UPDATE confirmar_deposito SET status = 'PAID_OUT' WHERE externalreference = ?");
    $stmtUpd->bind_param('s', $transactionId);
    $stmtUpd->execute();
    $stmtUpd->close();

    // Buscar dados do usuário e do app
    $stmtUser = $conn->prepare("SELECT * FROM appconfig WHERE email = ? LIMIT 1");
    $stmtUser->bind_param('s', $emailUsuario);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    $resultApp = $conn->query("SELECT * FROM app LIMIT 1")->fetch_assoc();

    // Incrementar depositou
    $stmtDep = $conn->prepare("UPDATE appconfig SET depositou = depositou + ? WHERE email = ?");
    $stmtDep->bind_param('ds', $valorDepositado, $emailUsuario);
    $stmtDep->execute();
    $stmtDep->close();

    // ----- Sistema de afiliados --------------------------------
    if (!empty($resultUser['afiliado'])) {

        $stmtAff = $conn->prepare(
            "SELECT plano, afiliado_ativo, cpa FROM appconfig WHERE id = ? LIMIT 1"
        );
        $stmtAff->bind_param('s', $resultUser['afiliado']);
        $stmtAff->execute();
        $affData = $stmtAff->get_result()->fetch_assoc();
        $stmtAff->close();

        $isFirstDeposit = ($resultUser['status_primeiro_deposito'] == '0');

        $stmtFD = $conn->prepare("UPDATE appconfig SET status_primeiro_deposito = 1 WHERE email = ?");
        $stmtFD->bind_param('s', $emailUsuario);
        $stmtFD->execute();
        $stmtFD->close();

        if ($affData) {
            if ($affData['afiliado_ativo'] == '1') {
                // Revenue Share
                if (is_numeric($affData['plano']) && floatval($affData['plano']) > 0) {
                    $revShare = $valorDepositado * (floatval($affData['plano']) / 100);
                    $stmtRS   = $conn->prepare(
                        "UPDATE appconfig SET comissaofake = comissaofake + ? WHERE id = ?"
                    );
                    $stmtRS->bind_param('ds', $revShare, $resultUser['afiliado']);
                    $stmtRS->execute();
                    $stmtRS->close();
                }
            } else {
                // CPA — somente primeiro depósito qualificado
                if ($isFirstDeposit && $valorDepositado >= floatval($resultApp['deposito_min_cpa'] ?? 0)) {
                    $cpaValor = floatval($affData['cpa']);
                    if ($cpaValor > 0) {
                        $stmtCPA = $conn->prepare(
                            "UPDATE appconfig SET comissaofake = comissaofake + ?, cont_cpa = cont_cpa + 1 WHERE id = ?"
                        );
                        $stmtCPA->bind_param('ds', $cpaValor, $resultUser['afiliado']);
                        $stmtCPA->execute();
                        $stmtCPA->close();
                    }
                }
            }
        }
    }

    // ----- Creditar saldo do usuário ---------------------------
    $stmtSaldo = $conn->prepare("UPDATE appconfig SET saldo = saldo + ? WHERE email = ?");
    $stmtSaldo->bind_param('ds', $valorDepositado, $emailUsuario);
    $stmtSaldo->execute();
    $stmtSaldo->close();

    $conn->close();
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Depósito confirmado']);
    exit();
}

// Status irrelevante — responde 200 para não gerar retry desnecessário
$conn->close();
http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Status ignorado: ' . $status]);
