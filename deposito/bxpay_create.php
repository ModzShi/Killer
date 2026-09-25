<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Included only by the authenticated deposit controller.
if (!isset($conn, $email, $valor, $nome, $cpf) || !isset($_SESSION['email'])) { http_response_code(404); exit; }
try {
    if (!hash_equals($_SESSION['deposit_csrf'] ?? '', is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '') || empty($_SESSION['deposit_csrf'])) {
        throw new RuntimeException('Recarregue a página antes de gerar o PIX.');
    }
    $reference = 'bx_' . bin2hex(random_bytes(16));
    $lockName = 'bxpay_create_' . substr(hash('sha256', $email), 0, 40);
    $lock = $conn->prepare('SELECT GET_LOCK(?, 0)');
    $lock->bind_param('s', $lockName); $lock->execute();
    if ((int) $lock->get_result()->fetch_row()[0] !== 1) throw new RuntimeException('Um PIX já está sendo gerado. Aguarde alguns instantes.');
    // The connection releases this advisory lock on exit, including error paths.
    // Keep a record before calling the provider, including uncertain timeouts.
    $stmt = $conn->prepare("SELECT reference, status FROM bxpay_deposits WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param('s', $email); $stmt->execute();
    $recent = $stmt->get_result()->fetch_assoc();
    if ($recent) {
        header('Location: bxpay.php?token=' . urlencode($recent['reference'])); exit;
    }
    $amount = number_format($valor, 2, '.', '');
    $stmt = $conn->prepare('INSERT INTO bxpay_deposits (reference, email, amount) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $reference, $email, $amount); $stmt->execute();
    $response = BXPay::fromDb($conn)->criarDeposito($nome, $cpf, $valor, 'Depósito ' . $reference, $reference);
    $body = isset($response['data']) && is_array($response['data']) ? $response['data'] : $response;
    $pix = $body['qrcode'] ?? null;
    if (!empty($response['_error']) || !is_string($pix) || !preg_match('/^000201[^\r\n]+6304[0-9a-fA-F]{4}$/D', $pix)) {
        $stmt = $conn->prepare("UPDATE bxpay_deposits SET status = 'REVIEW' WHERE reference = ?");
        $stmt->bind_param('s', $reference); $stmt->execute();
        throw new RuntimeException($response['_message'] ?? 'A BX Pay não retornou um código PIX reconhecido. Confira a cobrança no painel antes de gerar outra.');
    }
    $providerId = $body['transactionId'] ?? ($body['transaction_id'] ?? null);
    if (!is_scalar($providerId)) $providerId = null;
    if ($providerId !== null) $providerId = (string) $providerId;
    $date = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i');
    $conn->begin_transaction();
    $stmt = $conn->prepare("UPDATE bxpay_deposits SET provider_id = ?, pix_code = ?, status = 'PENDING' WHERE reference = ?");
    $stmt->bind_param('sss', $providerId, $pix, $reference); $stmt->execute();
    $stmt = $conn->prepare("INSERT INTO confirmar_deposito (email, valor, externalreference, pix_code, status, data) VALUES (?, ?, ?, ?, 'WAITING_FOR_APPROVAL', ?)");
    $stmt->bind_param('sssss', $email, $amount, $reference, $pix, $date); $stmt->execute();
    $conn->commit();
    header('Location: bxpay.php?token=' . urlencode($reference)); exit;
} catch (Throwable $error) {
    $conn->rollback();
    $_SESSION['dep_errors'] = [$error instanceof RuntimeException && !($error instanceof mysqli_sql_exception) ? $error->getMessage() : 'Não foi possível concluir o depósito. Confira o painel da BX Pay antes de tentar novamente.'];
    header('Location: ./'); exit;
}
