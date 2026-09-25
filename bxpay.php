<?php
require_once __DIR__ . '/app/bootstrap.php';

/** BX Pay client: in-memory session, no automatic retries of payments. */
class BXPay
{
    private string $clientId;
    private string $clientSecret;
    private $handle = null;
    private bool $authenticated = false;

    public function __construct(array $config)
    {
        if (rtrim($config['api_url'] ?? 'https://bxpay.shop', '/') !== 'https://bxpay.shop') {
            throw new InvalidArgumentException('Use a URL oficial https://bxpay.shop.');
        }
        $this->clientId = trim($config['client_id'] ?? '');
        $this->clientSecret = trim($config['client_secret'] ?? '');
    }
    public function __destruct()
    {
        if ($this->handle !== null) curl_close($this->handle);
    }
    private function error(string $message, int $code = 0): array
    {
        return ['_error' => true, '_message' => $message, '_http_code' => $code];
    }
    protected function transport(string $path, array $payload, string $method): array
    {
        if (defined('SK_OFFLINE') && SK_OFFLINE) return ['body' => '', 'code' => 503];
        if (!extension_loaded('curl')) return ['body' => '', 'code' => 0];
        if ($this->handle === null) {
            $this->handle = curl_init();
            curl_setopt($this->handle, CURLOPT_COOKIEFILE, '');
        }
        curl_setopt_array($this->handle, [
            CURLOPT_URL => 'https://bxpay.shop' . $path,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json, text/plain'],
        ]);
        if ($method === 'POST') {
            curl_setopt($this->handle, CURLOPT_POST, true);
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            curl_setopt($this->handle, CURLOPT_HTTPGET, true);
        }
        $body = curl_exec($this->handle);
        return ['body' => $body === false ? '' : $body, 'code' => (int) curl_getinfo($this->handle, CURLINFO_HTTP_CODE)];
    }
    private function request(string $path, array $payload = [], string $method = 'POST', bool $allowPix = false): array
    {
        $response = $this->transport($path, $payload, $method);
        $code = $response['code'];
        if ($code === 0) return $this->error('Falha de conexão com a BX Pay. Confira a operação no painel antes de tentar novamente.');
        if ($code === 401) $this->authenticated = false;
        if ($code < 200 || $code >= 300) {
            return $this->error($code === 429 ? 'Limite de consultas atingido. Aguarde antes de tentar novamente.' : 'A BX Pay recusou a operação (HTTP ' . $code . '). Confira os dados e as permissões da conta.', $code);
        }
        $data = json_decode($response['body'], true);
        if ($allowPix) {
            $pix = is_string($data) ? $data : trim($response['body']);
            if (preg_match('/^000201[^\r\n]+6304[0-9a-fA-F]{4}$/D', $pix)) return ['qrcode' => $pix, '_http_code' => $code];
        }
        if (!is_array($data)) return $this->error('Resposta inesperada da BX Pay. Confira a operação no painel.', $code);
        if (($data['statusCode'] ?? 200) >= 400 || ($data['success'] ?? true) === false || !empty($data['error'])) return $this->error('A BX Pay não concluiu a operação. Confira os dados e os limites da conta.', $code);
        unset($data['_error'], $data['_message']);
        $data['_http_code'] = $code;
        return $data;
    }
    private function authenticate(): array
    {
        if ($this->authenticated) return [];
        if ($this->clientId === '' || $this->clientSecret === '') return $this->error('Configure o Client ID e o Client Secret da BX Pay.');
        $result = $this->request('/api/auth/login.php', ['client_id' => $this->clientId, 'client_secret' => $this->clientSecret]);
        if (!empty($result['_error'])) return $result;
        if (($result['authenticated'] ?? false) !== true || (int) ($result['statusCode'] ?? 0) !== 200) return $this->error('A BX Pay não confirmou a autenticação.');
        $this->authenticated = true;
        return [];
    }
    public function consultarSaldo(): array
    {
        $auth = $this->authenticate();
        return $auth ?: $this->request('/api/user/balance.php', [], 'GET');
    }
    public function listarTransacoes(int $page = 1, int $limit = 10): array
    {
        if ($page < 1 || $limit < 1 || $limit > 100) return $this->error('Paginação inválida.');
        $auth = $this->authenticate();
        return $auth ?: $this->request('/api/transactions/index.php?' . http_build_query(['page' => $page, 'limit' => $limit]), [], 'GET');
    }
    public function criarDeposito(string $nome, string $cpf, float $valor, string $descricao = '', string $externalId = ''): array
    {
        if (!is_finite($valor) || $valor < 5 || abs($valor - round($valor, 2)) > 0.000001) return $this->error('Informe um valor a partir de R$ 5,00, com até duas casas decimais.');
        $auth = $this->authenticate();
        if ($auth) return $auth;
        $payload = ['amount' => $valor, 'currency' => 'BRL', 'name' => $nome, 'document' => preg_replace('/\D/', '', $cpf), 'description' => $descricao];
        if ($externalId !== '') $payload['external_id'] = $externalId;
        return $this->request('/libs/funcoes/gerar_pix.php', $payload, 'POST', true);
    }
    public function criarSaque(string $nome, string $cpf, float $valor, string $chavePix, string $descricao = '', string $keyType = '', string $pin = ''): array
    {
        if (!is_finite($valor) || $valor < 5 || abs($valor - round($valor, 2)) > 0.000001) return $this->error('O saque deve ser de pelo menos R$ 5,00, com até duas casas decimais.');
        if (trim($chavePix) === '' || !in_array($keyType, ['CPF', 'CNPJ', 'EMAIL', 'PHONE', 'EVP'], true)) return $this->error('Informe a chave PIX e seu tipo.');
        if ($pin !== '' && !preg_match('/^\d{6}$/D', $pin)) return $this->error('O PIN deve ter seis dígitos.');
        $auth = $this->authenticate();
        if ($auth) return $auth;
        $payload = ['valor' => $valor, 'chave_pix' => $chavePix, 'key_type' => $keyType, 'nome' => $nome, 'cpf' => preg_replace('/\D/', '', $cpf), 'descricao' => $descricao];
        if ($pin !== '') $payload['pin'] = $pin;
        return $this->request('/libs/funcoes/gerar_saque.php', $payload);
    }
    public static function fromDb(mysqli $conn): self
    {
        $result = $conn->query('SELECT client_id, client_secret FROM bxpay_config WHERE id = 1');
        if (!$result) throw new RuntimeException('Configure a BX Pay no painel administrativo.');
        return new self($result->fetch_assoc() ?: []);
    }
}
