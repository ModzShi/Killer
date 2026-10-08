<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/consulta.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

function consulta_error(int $status, string $message): never {
    http_response_code($status);
    echo json_encode(['error'=>$message], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    consulta_error(405, 'Método não permitido.');
}
if (empty($_SESSION['email'])) consulta_error(401, 'Entre na sua conta para consultar.');
if (!app_check_csrf()) consulta_error(403, 'Formulário expirado. Recarregue a página.');

$catalog = consulta_catalog();
$id = is_string($_POST['query_id'] ?? null) ? $_POST['query_id'] : '';
$entry = $catalog[$id] ?? null;
if (!$entry) consulta_error(400, 'Escolha uma consulta válida.');
$value = trim(is_string($_POST['value'] ?? null) ? $_POST['value'] : '');
$value = consulta_normalize_input($entry['param'], $value, $entry['fixed']);
if (!consulta_validate_input($entry['param'], $value, $entry['fixed'])) consulta_error(422, 'Valor inválido para este tipo de busca.');

$configuredBase = trim((string)getenv('CONSULTA_API_BASE_URL'));
$base = rtrim($configuredBase !== '' ? $configuredBase : 'http://apisbrasilpro.site', '/');
$headerName = (string)getenv('CONSULTA_API_AUTH_HEADER');
$headerValue = (string)getenv('CONSULTA_API_AUTH_VALUE');
$parsed = parse_url($base);
$scheme = is_array($parsed) ? strtolower((string)($parsed['scheme'] ?? '')) : '';
if (!$parsed || !in_array($scheme, ['http','https'], true)
    || strtolower((string)($parsed['host'] ?? '')) !== 'apisbrasilpro.site'
    || isset($parsed['port']) || isset($parsed['user']) || isset($parsed['pass'])
    || ($parsed['path'] ?? '') !== '' || isset($parsed['query']) || isset($parsed['fragment'])
    || (($headerName === '') !== ($headerValue === ''))
    || ($headerName !== '' && !preg_match('/^[A-Za-z0-9-]{1,60}$/D', $headerName))
    || strlen($headerValue) > 2048 || preg_match('/[\r\n\x00]/', $headerValue)
    || (!function_exists('curl_init') && !(bool)ini_get('allow_url_fopen'))) {
    consulta_error(503, 'A conexão com a API não está disponível.');
}

$window = (int)($_SESSION['consulta_window'] ?? 0);
if ($window < time() - 60) { $_SESSION['consulta_window'] = time(); $_SESSION['consulta_count'] = 0; }
$_SESSION['consulta_count'] = (int)($_SESSION['consulta_count'] ?? 0) + 1;
if ($_SESSION['consulta_count'] > 12) consulta_error(429, 'Muitas consultas. Aguarde um minuto.');

$query = $entry['fixed'];
$query[$entry['param']] = $value;
$url = $base . '/' . $entry['file'] . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
$response = '';
$tooLarge = false;
$limit = 16 * 1024 * 1024;
$headers = ['Accept: application/json'];
if ($headerName !== '') $headers[] = $headerName . ': ' . $headerValue;
if (function_exists('curl_init')) {
    $curl = curl_init($url);
    if ($curl === false) consulta_error(503, 'Não foi possível iniciar a conexão.');
    curl_setopt_array($curl, [
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => $scheme === 'https' ? CURLPROTO_HTTPS : CURLPROTO_HTTP,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'SubwayRun-Consulta/1.0',
        CURLOPT_WRITEFUNCTION => static function($handle, string $chunk) use (&$response, &$tooLarge, $limit): int {
            if (strlen($response) + strlen($chunk) > $limit) { $tooLarge = true; return 0; }
            $response .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
} else {
    $context = stream_context_create(['http'=>[
        'method'=>'GET', 'header'=>implode("\r\n", array_merge($headers, ['User-Agent: SubwayRun-Consulta/1.0'])),
        'timeout'=>20, 'follow_location'=>0, 'max_redirects'=>0, 'ignore_errors'=>true,
    ], 'ssl'=>['verify_peer'=>true, 'verify_peer_name'=>true]]);
    $stream = @fopen($url, 'rb', false, $context);
    $ok = $stream !== false;
    $status = 0;
    if ($stream !== false) {
        $metadata = stream_get_meta_data($stream);
        $statusLine = $metadata['wrapper_data'][0] ?? '';
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string)$statusLine, $match)) $status = (int)$match[1];
        while (!feof($stream)) {
            $chunk = fread($stream, min(8192, $limit + 1 - strlen($response)));
            if ($chunk === false) { $ok = false; break; }
            if ($chunk === '') break;
            $response .= $chunk;
            if (strlen($response) > $limit) { $tooLarge = true; break; }
        }
        fclose($stream);
    }
}
if ($tooLarge) consulta_error(502, 'A API retornou mais de 16 MB. Nenhum dado foi cortado; a resposta não foi exibida.');
if ($ok === false) consulta_error(502, 'A API não respondeu. Tente novamente mais tarde.');
if ($status < 200 || $status >= 300) consulta_error(502, 'A API recusou a consulta (HTTP ' . $status . ').');
json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) consulta_error(502, 'A API não retornou um JSON válido.');
echo $response;
