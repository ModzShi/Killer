<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bxpay.php';
class FakeBXPay extends BXPay
{
    public array $responses = [];
    public array $calls = [];
    protected function transport(string $path, array $payload, string $method): array
    {
        $this->calls[] = [$path, $payload, $method];
        if (!$this->responses) throw new RuntimeException('Unexpected request');
        return array_shift($this->responses);
    }
}
function check($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function client(): FakeBXPay { return new FakeBXPay(['client_id' => 'pk_test', 'client_secret' => 'sk_test']); }
function response($data, int $code = 200): array { return ['body' => is_array($data) ? json_encode($data) : $data, 'code' => $code]; }
$login = response(['statusCode' => 200, 'authenticated' => true]);
$api = client();
$api->responses = [$login, response(['balance' => 20]), response(['balance' => 21])];
check($api->consultarSaldo()['balance'] === 20 && $api->consultarSaldo()['balance'] === 21 && count($api->calls) === 3, 'Authenticated session is reused');
$api = client();
check(!empty($api->criarDeposito('', '', 4.99)['_error']) && !$api->calls, 'Reject deposits below minimum before calling provider');
check(!empty($api->criarDeposito('', '', NAN)['_error']) && !$api->calls, 'Reject non-finite amounts');
check(!empty($api->criarDeposito('', '', 5.001)['_error']) && !$api->calls, 'Reject fractional cents');
$api->responses = [response(['statusCode' => 200, 'authenticated' => false])];
check(!empty($api->consultarSaldo()['_error']) && count($api->calls) === 1, 'Do not call balance after rejected authentication');
$api = client();
$pix = '000201testpayload6304ABCD';
$api->responses = [$login, response($pix)];
$result = $api->criarDeposito('Teste', '123.456.789-00', 5, 'Teste', 'order_test');
check($result['qrcode'] === $pix && !isset($result['transactionId']), 'Accept plain PIX without inventing transaction identifiers');
check($api->calls[1][1]['external_id'] === 'order_test' && $api->calls[1][1]['document'] === '12345678900', 'Send reference and normalized payer document');
$api = client();
$api->responses = [$login, response('<html>failure</html>')];
check(!empty($api->criarDeposito('', '', 5)['_error']), 'Reject unexpected HTML responses');
$api = client();
$api->responses = [$login, response(['message' => 'limited'], 429)];
check(!empty($api->criarDeposito('', '', 5)['_error']) && count($api->calls) === 2, 'Never retry a payment automatically after rate limiting');
$api = client();
$api->responses = [$login, response(['success' => false])];
check(!empty($api->consultarSaldo()['_error']), 'Reject API errors even with HTTP 200');
$api = client();
check(!empty($api->criarSaque('', '', 5, 'test', '', 'INVALID')['_error']) && !$api->calls, 'Validate withdrawal key type');
$api->responses = [$login, response(['statusCode' => 200])];
$api->criarSaque('', '', 5, 'test@example.com', '', 'EMAIL', '123456');
check($api->calls[1][1]['pin'] === '123456', 'Include PIN when provided');
try { new BXPay(['api_url' => 'http://other.example']); check(false, 'Reject untrusted base URL'); }
catch (InvalidArgumentException $expected) { check(true, 'Reject untrusted base URL'); }
