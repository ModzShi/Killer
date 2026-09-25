<?php
require_once __DIR__ . '/app/bootstrap.php';


class BXPay
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim(
            $config['api_url'] ?? 'https://bxpay.shop',
            '/'
        );

        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
    }

    /**
     * Autentica na BX PAY e mantém o cookie da sessão.
     */
    private function autenticar(): array
    {
        $url = $this->baseUrl . '/api/auth/login.php';

        $payload = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_COOKIEJAR => sys_get_temp_dir() . '/bxpay_cookie.txt',
            CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/bxpay_cookie.txt',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            return [
                '_error' => true,
                '_message' => $curlError,
                '_http_code' => 0
            ];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [
                '_error' => true,
                '_message' => 'Resposta inválida da BX PAY',
                '_raw' => $response,
                '_http_code' => $httpCode
            ];
        }

        $decoded['_http_code'] = $httpCode;

        return $decoded;
    }

    /**
     * Gera cobrança PIX.
     */
    public function criarDeposito(
        string $nome,
        string $cpf,
        float $valor,
        string $descricao = '',
        string $externalId = ''
    ): array {

        $login = $this->autenticar();

        if (
            isset($login['_error']) ||
            (($login['statusCode'] ?? 0) != 200)
        ) {
            return [
                '_error' => true,
                '_message' => 'Falha na autenticação da BX PAY',
                '_login' => $login
            ];
        }

        $url = $this->baseUrl . '/libs/funcoes/gerar_pix.php';

        $payload = [
            'amount' => $valor,
            'currency' => 'BRL',
            'name' => $nome,
            'document' => preg_replace('/\D/', '', $cpf),
        ];

        if ($descricao !== '') {
            $payload['description'] = $descricao;
        }

        if ($externalId !== '') {
            $payload['external_id'] = $externalId;
        }

        return $this->requestJson($url, $payload);
    }

    /**
     * Gera saque PIX.
     */
    public function criarSaque(
        string $nome,
        string $cpf,
        float $valor,
        string $chavePix,
        string $descricao = '',
        string $keyType = ''
    ): array {

        $login = $this->autenticar();

        if (
            isset($login['_error']) ||
            (($login['statusCode'] ?? 0) != 200)
        ) {
            return [
                '_error' => true,
                '_message' => 'Falha na autenticação da BX PAY',
                '_login' => $login
            ];
        }

        $url = $this->baseUrl . '/libs/funcoes/gerar_saque.php';

        $payload = [
            'valor' => $valor,
            'chave_pix' => $chavePix,
        ];

        if ($keyType !== '') {
            $payload['key_type'] = $keyType;
        }

        if ($nome !== '') {
            $payload['nome'] = $nome;
        }

        if ($cpf !== '') {
            $payload['cpf'] = preg_replace('/\D/', '', $cpf);
        }

        if ($descricao !== '') {
            $payload['descricao'] = $descricao;
        }

        return $this->requestJson($url, $payload);
    }

    /**
     * Consulta saldo da conta BX PAY.
     */
    public function consultarSaldo(): array
    {
        $login = $this->autenticar();

        if (
            isset($login['_error']) ||
            (($login['statusCode'] ?? 0) != 200)
        ) {
            return [
                '_error' => true,
                '_message' => 'Falha na autenticação da BX PAY',
                '_login' => $login
            ];
        }

        $url = $this->baseUrl . '/api/user/balance.php';

        return $this->requestJson($url, [], 'GET');
    }

    /**
     * Consulta transações.
     */
    public function listarTransacoes(
        int $page = 1,
        int $limit = 10
    ): array {

        $login = $this->autenticar();

        if (
            isset($login['_error']) ||
            (($login['statusCode'] ?? 0) != 200)
        ) {
            return [
                '_error' => true,
                '_message' => 'Falha na autenticação da BX PAY',
                '_login' => $login
            ];
        }

        $url = $this->baseUrl .
            '/api/transactions/index.php?' .
            http_build_query([
                'page' => $page,
                'limit' => $limit
            ]);

        return $this->requestJson($url, [], 'GET');
    }

    /**
     * Requisição JSON usando a sessão autenticada.
     */
    private function requestJson(
        string $url,
        array $payload = [],
        string $method = 'POST'
    ): array {

        $cookieFile = sys_get_temp_dir() . '/bxpay_cookie.txt';

        $ch = curl_init();

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($method === 'GET') {

            $options[CURLOPT_URL] = $url;
            $options[CURLOPT_HTTPGET] = true;

        } else {

            $options[CURLOPT_URL] = $url;
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            return [
                '_error' => true,
                '_message' => $curlError,
                '_http_code' => 0
            ];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [
                '_error' => true,
                '_message' => 'Resposta inválida da BX PAY',
                '_raw' => $response,
                '_http_code' => $httpCode
            ];
        }

        $decoded['_http_code'] = $httpCode;

        return $decoded;
    }

    /**
     * Cria a instância usando a tabela gateway.
     */
    public static function fromDb(mysqli $conn): self
    {
        $result = $conn->query("
            SELECT client_id, client_secret
            FROM gateway
            LIMIT 1
        ");

        $row = $result
            ? $result->fetch_assoc()
            : [];

        return new self([
            'api_url' => 'https://bxpay.shop',
            'client_id' => $row['client_id'] ?? '',
            'client_secret' => $row['client_secret'] ?? '',
        ]);
    }
}