<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['emailadm'])) {
    header('Location: ../login/');
    exit;
}
header('Cache-Control: no-store');
require_once '../../payments/bxpay_service.php';
require '../../conectarbanco.php';
$_SESSION['bxpay_csrf'] = $_SESSION['bxpay_csrf'] ?? bin2hex(random_bytes(32));
$message = '';
$success = false;
$saved = [];
$dbReady = false; $enabled = false;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
    $db->set_charset('utf8mb4');
    // Additive setup; the current gateway's credentials stay in its own table.
    bxpay_install($db);
    $saved = $db->query('SELECT client_id, client_secret FROM bxpay_config WHERE id = 1')->fetch_assoc() ?: [];
    $dbReady = true; $enabled = bxpay_enabled($db);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['bxpay_csrf'], $_POST['csrf'])) {
            http_response_code(403);
            $message = 'Sua sessão de formulário expirou. Recarregue a página.';
        } elseif (($_POST['action'] ?? '') === 'test') {
            if (SK_OFFLINE) { $message = 'Modo offline: nenhuma chamada foi enviada à BX Pay. Use a Central de testes para simular pagamentos.'; }
            else {
            $result = (new BXPay($saved))->consultarSaldo();
            $success = empty($result['_error']) && is_numeric($result['balance'] ?? null);
            if ($success) {
                $message = 'Conexão confirmada. A BX Pay aceitou as credenciais e retornou o saldo.';
            } else {
                $httpCode = (int) ($result['_http_code'] ?? 0);
                $message = match ($httpCode) {
                    401 => 'A BX Pay recusou a autenticação (HTTP 401). Confira se Client ID e Client Secret são do mesmo ambiente e estão ativos.',
                    403 => 'A BX Pay negou permissão (HTTP 403). Confira se a conta tem acesso à API e se o IP/domínio está autorizado.',
                    404 => 'A BX Pay não encontrou o endpoint consultado (HTTP 404). Confirme com o suporte da BX Pay a URL e a versão corretas da API.',
                    429 => 'A BX Pay limitou as consultas (HTTP 429). Aguarde um pouco e tente novamente.',
                    0 => $result['_message'] ?? 'Não houve resposta da BX Pay. Confira se a hospedagem permite conexões HTTPS de saída e se o cURL/SSL está ativo.',
                    default => 'A BX Pay não confirmou o teste (HTTP ' . $httpCode . '). ' . ($result['_message'] ?? 'Confira as credenciais, o ambiente e as permissões da API.'),
                };
            }
            }
        } elseif (($_POST['action'] ?? '') === 'save') {
            $clientId = trim(is_string($_POST['client_id'] ?? null) ? $_POST['client_id'] : '');
            $secret = trim(is_string($_POST['client_secret'] ?? null) ? $_POST['client_secret'] : '');
            if ($secret === '' && $clientId === ($saved['client_id'] ?? '')) $secret = $saved['client_secret'] ?? '';
            if ($clientId === '' || $secret === '' || strlen($clientId) > 255 || strlen($secret) > 255 || preg_match('/\s/', $clientId . $secret)) {
                $message = 'Informe o Client ID e o Client Secret válidos, sem espaços, da mesma conta BX Pay.';
            } else {
                $db->begin_transaction();
                $statement = $db->prepare('INSERT INTO bxpay_config (id, client_id, client_secret) VALUES (1, ?, ?) ON DUPLICATE KEY UPDATE client_id = VALUES(client_id), client_secret = VALUES(client_secret)');
                $statement->bind_param('ss', $clientId, $secret);
                $statement->execute();
                $enabled = isset($_POST['enabled']) ? 1 : 0;
                $stmtEnabled = $db->prepare('INSERT INTO bxpay_settings (id, enabled) VALUES (1, ?) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)');
                $stmtEnabled->bind_param('i', $enabled);
                $stmtEnabled->execute();
                $db->commit();
                $_SESSION['bxpay_saved'] = true;
                header('Location: bxpay.php');
                exit;
            }
        }
    } elseif (!empty($_SESSION['bxpay_saved'])) {
        unset($_SESSION['bxpay_saved']);
        $success = true;
        $message = 'Credenciais salvas. Agora você pode testar a conexão.';
    }
} catch (Throwable $error) {
    if (isset($db)) $db->rollback();
    http_response_code(503);
    $dbReady = false; $enabled = false;
    $message = 'Não foi possível acessar a configuração. Verifique a conexão e as permissões do banco de dados.';
}
$escape = static function ($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); };
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>Configurar BX Pay</title>
    <link rel="stylesheet" href="bxpay.css">
</head>
<body>
<main class="gateway-shell">
    <a class="back-link" href="./">← Voltar para gateways</a>
    <header class="gateway-heading"><div class="gateway-logo">BX<span>PAY</span></div><span class="status-badge"><?= $enabled ? 'BX Pay selecionada' : 'Aguardando configuração' ?></span></header>
    <h1>Seu PIX começa aqui.</h1>
    <p class="lead">Conecte sua conta BX Pay para preparar os pagamentos do seu site.</p>
    <?php if ($message !== ''): ?><div class="notice <?= $success ? 'notice-success' : 'notice-error' ?>" role="status"><?= $escape($message) ?></div><?php endif; ?>
    <div class="gateway-grid">
        <section class="gateway-card">
            <span class="eyebrow">01 / CONECTAR CONTA</span>
            <h2>Credenciais da BX Pay</h2>
            <p>Copie os dados em <strong>Configurações → Credenciais</strong> no painel da BX Pay.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= $escape($_SESSION['bxpay_csrf']) ?>">
                <label for="base-url">Endereço da API</label><input id="base-url" value="https://bxpay.shop" readonly>
                <label for="client-id">Client ID</label><input id="client-id" name="client_id" placeholder="Client ID da sua conta BX Pay" autocomplete="off" maxlength="255" value="<?= $escape($saved['client_id'] ?? '') ?>" required>
                <label for="client-secret">Client Secret</label><input id="client-secret" name="client_secret" type="password" autocomplete="new-password" maxlength="255" placeholder="<?= !empty($saved['client_secret']) ? 'Credencial salva — deixe vazio para manter' : 'Client Secret da sua conta BX Pay' ?>">
                <p class="hint">A chave secreta salva não é exibida nesta página.</p>
                <label class="enable-option"><input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>> Usar BX Pay nos depósitos PIX</label><button name="action" value="save" <?= !$dbReady ? 'disabled' : '' ?>>Salvar credenciais <span aria-hidden="true">↗</span></button>
            </form>
        </section>
        <aside class="gateway-card steps-card">
            <span class="eyebrow">02 / CONFERIR CONEXÃO</span><h2>Tudo conectado?</h2>
            <p>Depois de salvar, teste se a BX Pay reconhece sua conta. O teste não faz cobranças nem saques.</p>
            <form method="post"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['bxpay_csrf']) ?>"><button class="secondary" name="action" value="test" <?= empty($saved['client_secret']) || !$dbReady ? 'disabled' : '' ?>>Testar conexão</button></form>
            <div class="next-step"><span class="eyebrow">03 / RECEBER PAGAMENTOS</span><h2>Confirmação do PIX</h2><p>Após pagar, o cliente pode tocar em “Já paguei” para conferir o depósito diretamente na BX Pay.</p><p>Para receber avisos automáticos, cadastre a URL abaixo como URL de retorno da credencial, se a BX Pay disponibilizar esse recurso.</p><label for="callback-url">URL de retorno</label><input id="callback-url" readonly value="<?= $escape((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/') . '/webhook/bxpay.php') ?>"><p class="hint">Na hospedagem, use a URL pública com HTTPS. O endereço localhost funciona apenas neste computador.</p><p class="hint">Saques continuam sujeitos à aprovação manual no painel.</p></div>
        </aside>
    </div>
</main>
</body>
</html>
