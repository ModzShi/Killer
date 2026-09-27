<?php
require_once __DIR__ . '/../../app/auth.php';

if (empty($_SESSION['emailadm'])) {
    http_response_code(403);
    exit('Não autorizado.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}
$csrf = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!is_string($csrf) || !hash_equals(app_csrf(), $csrf)) {
    http_response_code(403);
    exit('Formulário expirado. Recarregue a página.');
}

$id = is_string($_POST['id'] ?? null) ? trim($_POST['id']) : '';
$status = is_string($_POST['novoStatus'] ?? null) ? trim($_POST['novoStatus']) : '';
$type = is_string($_POST['tipo'] ?? null) ? $_POST['tipo'] : 'Jogo';
$allowedStatuses = ['Aguardando Aprovação', 'Processando', 'Aprovado', 'Pago', 'Rejeitado'];
if ($id === '' || strlen($id) > 255 || !in_array($type, ['Jogo', 'Afiliado'], true) || !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    exit('Dados da solicitação inválidos.');
}

try {
    $db = app_db();
    $db->begin_transaction();
    if ($type === 'Afiliado') {
        if (!ctype_digit($id) || (int) $id < 1) throw new InvalidArgumentException('Identificador inválido.');
        $stmt = $db->prepare('SELECT status FROM saque_afiliado WHERE id=? FOR UPDATE');
        $numericId = (int) $id;
        $stmt->bind_param('i', $numericId);
    } else {
        $stmt = $db->prepare('SELECT status FROM saques WHERE externalreference=? FOR UPDATE');
        $stmt->bind_param('s', $id);
    }
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    if (!$current) throw new OutOfBoundsException('Solicitação de saque não encontrada.');
    if (in_array(strtolower((string) $current['status']), ['pago', 'paid', 'rejeitado'], true)) {
        if (strtolower((string) $current['status']) !== strtolower($status)) {
            throw new DomainException('Uma solicitação concluída não pode ser alterada.');
        }
        $db->commit();
        echo 'Status já registrado.';
        exit;
    }
    if ($type === 'Afiliado') {
        $stmt = $db->prepare('UPDATE saque_afiliado SET status=? WHERE id=?');
        $stmt->bind_param('si', $status, $numericId);
    } else {
        $stmt = $db->prepare('UPDATE saques SET status=? WHERE externalreference=?');
        $stmt->bind_param('ss', $status, $id);
    }
    $stmt->execute();
    $db->commit();
    echo 'Status atualizado com sucesso.';
} catch (InvalidArgumentException $error) {
    if (isset($db) && $db instanceof mysqli) $db->rollback();
    http_response_code(400);
    echo app_escape($error->getMessage());
} catch (OutOfBoundsException $error) {
    if (isset($db) && $db instanceof mysqli) $db->rollback();
    http_response_code(404);
    echo app_escape($error->getMessage());
} catch (DomainException $error) {
    if (isset($db) && $db instanceof mysqli) $db->rollback();
    http_response_code(409);
    echo app_escape($error->getMessage());
} catch (Throwable $error) {
    if (isset($db) && $db instanceof mysqli) $db->rollback();
    error_log('withdrawal status update failed: ' . $error->getMessage());
    http_response_code(500);
    echo 'Não foi possível atualizar a solicitação.';
} finally {
    if (isset($db) && $db instanceof mysqli) $db->close();
}
