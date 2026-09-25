<?php
require_once __DIR__ . '/../../app/bootstrap.php';

/**
 * adm/gateway/bd.php
 * Lê e salva as configurações da KnucklesPay no banco.
 */

include './../../conectarbanco.php';
$conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if ($conn->connect_error) die('Erro na conexão: ' . $conn->connect_error);

// ----- Salvar configurações (POST) ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ação de teste de conexão
    if (isset($_POST['action']) && $_POST['action'] === 'test') {
        $row = $conn->query("SELECT client_id, client_secret FROM gateway LIMIT 1")->fetch_assoc();
        if (empty($row['client_id']) || empty($row['client_secret'])) {
            echo 'Client ID e Client Secret não configurados.';
            exit();
        }
        $url = 'https://knucklespay.space/api/v2/account/balance.php?' . http_build_query([
            'client_id'     => $row['client_id'],
            'client_secret' => $row['client_secret'],
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($res, true);
        if ($code === 200 && isset($data['balance'])) {
            echo 'OK — Conexão estabelecida! Saldo: R$ ' . number_format($data['balance']['available'] ?? 0, 2, ',', '.');
        } else {
            echo 'Falha — HTTP ' . $code . '. Verifique Client ID e Client Secret.';
        }
        exit();
    }

    // Salvar campos
    $clientId      = trim($_POST['client_id']      ?? '');
    $clientSecret  = trim($_POST['client_secret']  ?? '');
    $webhookSecret = trim($_POST['webhook_secret'] ?? '');

    $exists = (int)$conn->query("SELECT COUNT(*) as n FROM gateway")->fetch_assoc()['n'] > 0;

    if ($exists) {
        $stmt = $conn->prepare(
            "UPDATE gateway SET
                client_id      = ?,
                client_secret  = ?,
                webhook_secret = ?
             LIMIT 1"
        );
        $stmt->bind_param('sss', $clientId, $clientSecret, $webhookSecret);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO gateway (client_id, client_secret, webhook_secret)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param('sss', $clientId, $clientSecret, $webhookSecret);
    }

    echo $stmt->execute() ? 'Sucesso: Configurações salvas!' : 'Erro ao salvar: ' . $conn->error;
    $stmt->close();
    $conn->close();
    exit();
}

// ----- Carregar para a view (index.php) ----------------------
$row = $conn->query("SELECT client_id, client_secret, webhook_secret FROM gateway LIMIT 1")->fetch_assoc();

$client_id      = $row['client_id']      ?? '';
$client_secret  = $row['client_secret']  ?? '';
$webhook_secret = $row['webhook_secret'] ?? '';

$conn->close();
