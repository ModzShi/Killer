<?php
require_once __DIR__ . '/app/bootstrap.php';

/**
 * KnucklesPay Helper
 * Centraliza toda comunicação com a API KnucklesPay v2.
 *
 * Inclua este arquivo em qualquer ponto do sistema:
 *   include ROOT . '/knucklespay.php';
 */

class KnucklesPay
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $webhookSecret;

    public function __construct(array $config)
    {
        $this->baseUrl       = rtrim($config['api_url'] ?? 'https://knucklespay.space', '/');
        $this->clientId      = $config['client_id']      ?? '';
        $this->clientSecret  = $config['client_secret']  ?? '';
        $this->webhookSecret = $config['webhook_secret'] ?? '';
    }

    // ----------------------------------------------------------
    // DEPÓSITO PIX — Gerar QRCode
    // POST /api/v2/pix/qrcode.php
    // ----------------------------------------------------------
    public function criarDeposito(string $nome, string $cpf, float $valor, string $descricao = '', string $urlnoty = ''): array
    {
        $payload = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'nome'          => $nome,
            'cpf'           => preg_replace('/\D/', '', $cpf),
            'valor'         => $valor,
        ];

        if (!empty($descricao)) $payload['descricao'] = $descricao;
        if (!empty($urlnoty))   $payload['urlnoty']   = $urlnoty;

        return $this->request('/api/v2/pix/qrcode.php', $payload);
    }

    // ----------------------------------------------------------
    // SAQUE / TRANSFERÊNCIA PIX
    // POST /api/v2/pix/payment.php
    // ----------------------------------------------------------
    public function criarSaque(string $nome, string $cpf, float $valor, string $chavePix, string $descricao = ''): array
    {
        $payload = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'nome'          => $nome,
            'cpf'           => preg_replace('/\D/', '', $cpf),
            'valor'         => $valor,
            'chave_pix'     => $chavePix,
        ];

        if (!empty($descricao)) $payload['descricao'] = $descricao;

        return $this->request('/api/v2/pix/payment.php', $payload);
    }

    // ----------------------------------------------------------
    // CONSULTAR STATUS DE TRANSAÇÃO
    // GET /api/v2/pix/status.php
    // ----------------------------------------------------------
    public function consultarStatus(string $transactionId): array
    {
        $params = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'transaction_id' => $transactionId,
        ];

        return $this->request('/api/v2/pix/status.php', $params, 'GET');
    }

    // ----------------------------------------------------------
    // CONSULTAR SALDO
    // GET /api/v2/account/balance.php
    // ----------------------------------------------------------
    public function consultarSaldo(): array
    {
        $params = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];

        return $this->request('/api/v2/account/balance.php', $params, 'GET');
    }

    // ----------------------------------------------------------
    // VALIDAÇÃO DE WEBHOOK HMAC (KnucklesPay-Signature)
    // ----------------------------------------------------------
    public function validarWebhook(string $payload, string $signatureHeader): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expected, $signatureHeader);
    }

    // ----------------------------------------------------------
    // HTTP Helper
    // ----------------------------------------------------------
    private function request(string $path, array $params, string $method = 'POST'): array
    {
        if (SK_OFFLINE) return ['_error' => true, '_message' => 'Gateway real desativada no modo offline.'];
        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['_error' => true, '_message' => $curlError, '_http_code' => 0];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return ['_error' => true, '_message' => 'Resposta inválida da API', '_raw' => $response, '_http_code' => $httpCode];
        }

        $decoded['_http_code'] = $httpCode;
        return $decoded;
    }

    // ----------------------------------------------------------
    // Factory: instancia a partir das config do banco
    // ----------------------------------------------------------
    public static function fromDb(mysqli $conn): self
    {
        $result = $conn->query("SELECT client_id, client_secret, webhook_secret FROM gateway LIMIT 1");
        $row    = $result ? $result->fetch_assoc() : [];

        return new self([
            'client_id'      => $row['client_id']      ?? '',
            'client_secret'  => $row['client_secret']  ?? '',
            'webhook_secret' => $row['webhook_secret'] ?? '',
        ]);
    }
}
