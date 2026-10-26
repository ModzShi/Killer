<?php
// PHP 8.1 / mk/api.php / Subway MK public API
require_once __DIR__ . '/lib.php';
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
$route = (string)($_GET['route'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    $db = app_db();
    if ($method === 'GET' && $route === 'releases/latest') {
        $row = $db->query('SELECT * FROM mk_releases ORDER BY id DESC LIMIT 1')->fetch_assoc();
        if (!$row) { http_response_code(404); exit; }
        echo json_encode(mk_release_info($row), JSON_UNESCAPED_SLASHES); exit;
    }
    if ($method === 'GET' && $route === 'releases/file') {
        $sha = strtoupper((string)($_GET['sha'] ?? ''));
        if (!preg_match('/^[A-F0-9]{64}$/D', $sha)) { http_response_code(404); exit; }
        $row = app_query($db, 'SELECT size FROM mk_releases WHERE sha256=? LIMIT 1', [$sha])->get_result()->fetch_assoc();
        $path = mk_release_dir() . '/' . $sha . '.dll';
        if (!$row || !is_file($path) || filesize($path) !== (int)$row['size']) { http_response_code(404); exit; }
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . $row['size']);
        header('Content-Disposition: attachment; filename="MicrosoftStore.dll"');
        readfile($path); exit;
    }
    if ($method !== 'POST' || !in_array($route, ['register', 'login'], true)) { http_response_code(404); exit; }
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 2048) { http_response_code(413); exit; }
    $body = json_decode(file_get_contents('php://input', false, null, 0, 2049) ?: '', true);
    if (!is_array($body)) { http_response_code(400); exit; }
    foreach (['username', 'password', 'device'] as $field) if (!is_string($body[$field] ?? null)) { http_response_code(400); exit; }
    if ($route === 'register' && !is_string($body['key'] ?? null)) { http_response_code(400); exit; }
    $user = $body['username'];
    if (!app_auth_allowed($db, 'mk_' . $route, $user)) { http_response_code(429); exit; }
    $result = $route === 'register'
        ? mk_register($db, $body['key'], $user, $body['password'], $body['device'])
        : mk_login($db, $user, $body['password'], $body['device']);
    if ($result['ok']) app_auth_clear($db, 'mk_' . $route, $user);
    else app_auth_failed($db, 'mk_' . $route, $user);
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('mk api: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Servico temporariamente indisponivel.']);
}
