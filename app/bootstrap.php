<?php
if (defined('SK_BOOTSTRAPPED')) return;
define('SK_BOOTSTRAPPED', true);
define('SK_ROOT', dirname(__DIR__));
require_once SK_ROOT . '/components/icons.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
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
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=15552000');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('SK_SESSION');
        $secureCookie = getenv('APP_ENV') === 'production' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params(['path' => app_url(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secureCookie]);
        session_start();
    }
    if (isset($_COOKIE['SK_REMEMBER_PLAYER']) || isset($_COOKIE['SK_REMEMBER_ADMIN']) || isset($_COOKIE['SK_REMEMBER_MANAGER'])) {
        try {
            require_once SK_ROOT . '/app/auth.php';
            $rememberDb=app_db();
            app_auth_restore_remembered($rememberDb);
            $rememberDb->close();
        } catch (Throwable $rememberError) {
            if (isset($rememberDb) && $rememberDb instanceof mysqli) $rememberDb->close();
            error_log('remembered login restore failed: '.$rememberError->getMessage());
        }
    }
    // Recheck access and credential version on every authenticated PHP request.
    // A block, deletion or password change revokes sessions that are already open.
    if (!empty($_SESSION['email']) || !empty($_SESSION['emailadm']) || !empty($_SESSION['manager_id'])) {
        try {
            require_once SK_ROOT . '/app/auth.php';
            $identityDb = app_db();
            if (!empty($_SESSION['email'])) {
                $player = app_query($identityDb, 'SELECT id,senha,demo,bloc FROM appconfig WHERE email=? LIMIT 1', [(string)$_SESSION['email']])->get_result()->fetch_assoc();
                $valid = $player && !in_array(strtolower((string)($player['bloc'] ?? '')), ['1','true','on'], true)
                    && is_string($_SESSION['player_auth_hash'] ?? null)
                    && hash_equals(app_password_fingerprint((string)$player['senha']), $_SESSION['player_auth_hash']);
                if (!$valid) {
                    unset($_SESSION['email'], $_SESSION['user_id'], $_SESSION['demo_account'], $_SESSION['player_auth_hash']);
                    app_auth_forget($identityDb, 'player');
                } else {
                    $_SESSION['user_id'] = $player['id'];
                    $_SESSION['demo_account'] = (string)$player['demo'] === '1';
                }
            }
            if (!empty($_SESSION['emailadm'])) {
                $admin = app_query($identityDb, 'SELECT senha FROM admlogin WHERE email=? LIMIT 1', [(string)$_SESSION['emailadm']])->get_result()->fetch_assoc();
                $valid = $admin && is_string($_SESSION['admin_auth_hash'] ?? null)
                    && hash_equals(app_password_fingerprint((string)$admin['senha']), $_SESSION['admin_auth_hash']);
                if (!$valid) {
                    unset($_SESSION['emailadm'], $_SESSION['admin_auth_hash']);
                    app_auth_forget($identityDb, 'admin');
                }
            }
            if (!empty($_SESSION['manager_id'])) {
                $manager = app_query($identityDb, 'SELECT password_hash FROM manager_accounts WHERE id=? AND active=1 LIMIT 1', [(string)$_SESSION['manager_id']])->get_result()->fetch_assoc();
                $valid = $manager && is_string($_SESSION['manager_auth_hash'] ?? null)
                    && hash_equals(app_password_fingerprint((string)$manager['password_hash']), $_SESSION['manager_auth_hash']);
                if (!$valid) {
                    unset($_SESSION['manager_id'], $_SESSION['manager_auth_hash']);
                    app_auth_forget($identityDb, 'manager');
                }
            }
            $identityDb->close();
        } catch (Throwable $identityError) {
            if (isset($identityDb) && $identityDb instanceof mysqli) $identityDb->close();
            error_log('identity validation: '.$identityError->getMessage());
            http_response_code(503); exit('Serviço temporariamente indisponível.');
        }
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
        if (!preg_match('~^/adm/(?!login/)|^/modo-bubble/|^/(?:gerente|webhook|gameover)(?:/|$)|^/404_not_found\\.php$~', $relativeFile)
            && stripos($html, '</head>') !== false && stripos($html, '<body') !== false) {
            $theme = '<script>(function(){try{var t=localStorage.getItem("sr-theme");if(["default","gold","red","purple","yellow"].indexOf(t)>=0)document.documentElement.setAttribute("data-sr-theme",t)}catch(e){}})();</script><link rel="stylesheet" href="' . app_escape(app_url('arquivos/premium-ui.css')) . '?v=4"><link rel="stylesheet" href="' . app_escape(app_url('arquivos/premium-fixes.css')) . '?v=5"><link rel="stylesheet" href="' . app_escape(app_url('arquivos/account.css')) . '?v=4"><link rel="stylesheet" href="' . app_escape(app_url('arquivos/theme-system.css')) . '?v=' . filemtime(SK_ROOT . '/arquivos/theme-system.css') . '"><script src="' . app_escape(app_url('arquivos/theme-system.js')) . '?v=1" defer></script>';
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
        if (strpos($relativeFile, '/modo-bubble/') !== 0) {
        // A mesma onda da marca identifica todas as páginas PHP no navegador.
        $html = preg_replace('~<link\b(?=[^>]*\brel\s*=\s*["\'](?:icon|shortcut icon|apple-touch-icon)["\'])[^>]*>~i', '', $html);
        $waveIcon = app_escape(app_url('img/logo.png')) . '?v=' . filemtime(SK_ROOT . '/img/logo.png');
        $html = preg_replace('~</head>~i', '<link rel="icon" type="image/png" href="' . $waveIcon . '"><link rel="apple-touch-icon" href="' . $waveIcon . '"></head>', $html, 1);
        $html = preg_replace('~</head>~i', '<link rel="stylesheet" href="'.app_escape(app_url('arquivos/mobile-polish.css')).'?v='.filemtime(SK_ROOT.'/arquivos/mobile-polish.css').'"></head>', $html, 1);
        }
        $html = preg_replace('~<script\b[^>]*disable-devtool[^>]*>.*?</script>~is', '', $html);
        return $html;
    });
}
