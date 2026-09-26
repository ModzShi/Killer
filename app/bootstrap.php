<?php
if (defined('SK_BOOTSTRAPPED')) return;
define('SK_BOOTSTRAPPED', true);
define('SK_ROOT', dirname(__DIR__));
require_once SK_ROOT . '/components/icons.php';
if (getenv('APP_ENV') === 'production') { ini_set('display_errors', '0'); error_reporting(E_ALL); }
define('SK_OFFLINE', false);
$scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
$relativeFile = substr($scriptFile, strlen(str_replace('\\', '/', SK_ROOT)));
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$base = $relativeFile !== '' && substr($scriptName, -strlen($relativeFile)) === $relativeFile ? substr($scriptName, 0, -strlen($relativeFile)) : '';
define('SK_BASE', rtrim($base, '/'));
function app_url(string $path = ''): string { return SK_BASE . '/' . ltrim($path, '/'); }
function app_escape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function app_csrf(): string { return $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32)); }
function app_check_csrf(): bool { return is_string($_POST['csrf'] ?? null) && hash_equals(app_csrf(), $_POST['csrf']); }
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: frame-ancestors 'self'");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=15552000');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        session_name('SK_SESSION');
        session_set_cookie_params(['path' => app_url(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        session_start();
    }
    if (!empty($_SESSION['email']) || !empty($_SESSION['emailadm']) || !empty($_SESSION['manager_id'])) header('Cache-Control: private, no-store');
    // All admin entry points, including JSON handlers, require an admin session.
    if (strpos($relativeFile, '/adm/') === 0 && strpos($relativeFile, '/adm/login/') !== 0 && empty($_SESSION['emailadm'])) {
        header('Location: ' . app_url('adm/login/')); exit;
    }
    $adminRequest = strpos($relativeFile, '/adm/') === 0 && strpos($relativeFile, '/adm/login/') !== 0;
    if ($adminRequest && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $csrf = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        // The BX Pay form also has its existing dedicated token.
        $valid = is_string($csrf) && (hash_equals(app_csrf(), $csrf) || (!empty($_SESSION['bxpay_csrf']) && hash_equals($_SESSION['bxpay_csrf'], $csrf)));
        if (!$valid) { http_response_code(403); exit('Formulário expirado. Recarregue a página.'); }
    }
    header_register_callback(static function (): void {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Location: /') === 0 && stripos($header, 'Location: //') !== 0) {
                $path = substr($header, 10);
                if (SK_BASE !== '' && strpos($path, SK_BASE . '/') !== 0) header('Location: ' . app_url($path), true, http_response_code());
            }
        }
    });
    ob_start(static function (string $html) use ($adminRequest, $relativeFile): string {
        if (stripos($html, '<html') === false && stripos($html, '<body') === false) return $html;
        $html=preg_replace_callback('~<script\b[^>]*>.*?</script>(*SKIP)(*F)|<i\b[^>]*class=["\'][^"\']*\b(?:mdi|fa)[ -]([^"\']*)["\'][^>]*>\s*</i>~is',static function(array $match):string {
            $name=$match[1];$icon='spark';
            foreach(['account|user'=>'user','cash|money|wallet|bank'=>'wallet','chart|history'=>'history','check'=>'check','game|play'=>'play','menu'=>'menu','logout|exit'=>'logout','lock|shield'=>'shield','help|question'=>'help','arrow|chevron'=>'arrow'] as $pattern=>$candidate)if(preg_match('~'.$pattern.'~i',$name)){$icon=$candidate;break;}
            return ui_icon($icon);
        },$html);
        // Resolve legacy root-relative routes for installations in an XAMPP subfolder.
        if (SK_BASE !== '') {
            $html = preg_replace('~((?:href|src|action)=["\'])/(?!/|' . preg_quote(ltrim(SK_BASE, '/'), '~') . '(?:/|["\']))~i', '$1' . SK_BASE . '/', $html);
            $html = preg_replace('~(url\(["\']?)/(?!/)~i', '$1' . SK_BASE . '/', $html);
            $html = preg_replace('~(["\'])/(painel|jogar|demo|deposito|saque|cadastrar|login|adm|auth|game|gameover|enddemo|play|presell)(?=[/?"\'])~', '$1' . SK_BASE . '/$2', $html);
        }
        if ($adminRequest) {
            $token = app_csrf();
            $html = preg_replace('~(<form\b[^>]*>)~i', '$1<input type="hidden" name="csrf" value="' . $token . '">', $html);
            $script = '<script>(()=>{const token=' . json_encode($token) . ';const open=XMLHttpRequest.prototype.open,send=XMLHttpRequest.prototype.send;XMLHttpRequest.prototype.open=function(m,u,...a){this.skCsrf=m.toUpperCase()!=="GET"&&new URL(u,location.href).origin===location.origin;return open.call(this,m,u,...a)};XMLHttpRequest.prototype.send=function(b){if(this.skCsrf)this.setRequestHeader("X-CSRF-Token",token);return send.call(this,b)}})();</script>';
            $adminTheme = '<link rel="stylesheet" href="' . app_escape(app_url('adm/premium-admin.css')) . '?v=3">';
            $html = preg_replace('~</head>~i', $adminTheme . $script . '</head>', $html, 1);
            $html = preg_replace_callback('~<body\b[^>]*>~i', static function (array $match): string {
                if (preg_match('~\bclass\s*=\s*(["\'])(.*?)\1~i', $match[0])) {
                    return preg_replace('~\bclass\s*=\s*(["\'])~i', 'class=$1sk-admin ', $match[0], 1);
                }
                return substr($match[0], 0, -1) . ' class="sk-admin">';
            }, $html, 1);
        }
        // Shared visual layer for the public PHP screens. Game canvases and the
        // dedicated result screen retain their own full-screen presentation.
        if (!preg_match('~^/adm/(?!login/)|^/(?:gerente|webhook|gameover)(?:/|$)~', $relativeFile)
            && stripos($html, '</head>') !== false && stripos($html, '<body') !== false) {
            $theme = '<link rel="stylesheet" href="' . app_escape(app_url('arquivos/premium-ui.css')) . '?v=4"><link rel="stylesheet" href="' . app_escape(app_url('arquivos/premium-fixes.css')) . '?v=5"><link rel="stylesheet" href="' . app_escape(app_url('arquivos/account.css')) . '?v=4">';
            $html = preg_replace_callback('~<script\b[^>]*>.*?</script>(*SKIP)(*F)|<img\b[^>]*src=["\']([^"\']+)["\'][^>]*>~is', static function(array $m): string {
                $file=basename($m[1]);
                if (!preg_match('~^(?:money\.(?:png|gif)|(?:deposit|with|jake|trofeu|trophy)\.gif|60f[0-9a-f]+_.*\.svg|61070a.*\.svg)$~i',$file)) return $m[0];
                $icon=preg_match('~money|deposit|with~i',$file)?'wallet':(preg_match('~head|Body~i',$file)?'users':(preg_match('~Helmet|Shades~i',$file)?'shield':(preg_match('~jake|trofeu|trophy~i',$file)?'trophy':'spark')));
                return '<span class="premium-symbol" aria-hidden="true">'.ui_icon($icon).'</span>';
            },$html);
            $html = preg_replace('~</head>~i', $theme . '</head>', $html, 1);
            $html = preg_replace_callback('~<body\b[^>]*>~i', static function (array $match): string {
                if (preg_match('~\bclass\s*=\s*(["\'])(.*?)\1~i', $match[0])) {
                    return preg_replace('~\bclass\s*=\s*(["\'])~i', 'class=$1sk-premium ', $match[0], 1);
                }
                return substr($match[0], 0, -1) . ' class="sk-premium">';
            }, $html, 1);
        }
        $html = preg_replace('~</head>~i', '<link rel="stylesheet" href="'.app_escape(app_url('arquivos/mobile-polish.css')).'?v='.filemtime(SK_ROOT.'/arquivos/mobile-polish.css').'"></head>', $html, 1);
        $html = preg_replace('~<script\b[^>]*disable-devtool[^>]*>.*?</script>~is', '', $html);
        if (!empty($_SESSION['demo_account'])) {
            $demoBanner = '<div style="position:relative;z-index:10002;padding:10px 16px;background:#163b78;color:#fff3c3;text-align:center;font:800 13px Arial;letter-spacing:.04em">CONTA DEMO · SALDO DE TREINO · PIX E SAQUE INDISPONÍVEIS</div>';
            $html = preg_replace('~(<body\b[^>]*>)~i', '$1' . $demoBanner, $html, 1);
        }
        return $html;
    });
}
