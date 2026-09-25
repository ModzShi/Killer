<?php
require_once __DIR__ . '/bootstrap.php';
function app_db(): mysqli {
    require SK_ROOT . '/conectarbanco.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
    $db->set_charset('utf8mb4'); return $db;
}
function app_query(mysqli $db, string $sql, array $params = []): mysqli_stmt {
    $stmt = $db->prepare($sql);
    if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute(); return $stmt;
}
function app_input(string $key): string { return is_string($_POST[$key] ?? null) ? $_POST[$key] : ''; }
function app_password_matches(string $password, string $stored): bool {
    if ((password_get_info($stored)['algoName'] ?? 'unknown') !== 'unknown') return password_verify($password, $stored);
    return $stored !== '' && hash_equals($stored, $password);
}
function app_auth_attempt_key(string $scope,string $email): string {
    return hash('sha256',$scope.'|'.strtolower(trim($email)).'|'.($_SERVER['REMOTE_ADDR']??'cli'));
}
function app_auth_attempts_install(mysqli $db): void {
    $db->query('CREATE TABLE IF NOT EXISTS auth_attempts (attempt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, failures SMALLINT UNSIGNED NOT NULL DEFAULT 0, blocked_until DATETIME NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function app_auth_allowed(mysqli $db,string $scope,string $email): bool {
    app_auth_attempts_install($db);
    $row=app_query($db,'SELECT 1 FROM auth_attempts WHERE attempt_key=? AND blocked_until>NOW()',[app_auth_attempt_key($scope,$email)])->get_result()->fetch_assoc();
    return !$row;
}
function app_auth_failed(mysqli $db,string $scope,string $email): void {
    $key=app_auth_attempt_key($scope,$email);
    $db->begin_transaction();
    try {
        app_query($db,'INSERT IGNORE INTO auth_attempts(attempt_key) VALUES(?)',[$key]);
        $row=app_query($db,'SELECT failures,UNIX_TIMESTAMP(updated_at) AS updated_epoch FROM auth_attempts WHERE attempt_key=? FOR UPDATE',[$key])->get_result()->fetch_assoc();
        $failures=(int)$row['updated_epoch']<time()-900?1:min(65535,(int)$row['failures']+1);
        app_query($db,'UPDATE auth_attempts SET failures=?,blocked_until=IF(?=1,DATE_ADD(NOW(),INTERVAL 15 MINUTE),NULL),updated_at=NOW() WHERE attempt_key=?',[(string)$failures,$failures>=8?'1':'0',$key]);
        $db->commit();
    } catch(Throwable $error) { $db->rollback(); throw $error; }
}
function app_auth_clear(mysqli $db,string $scope,string $email): void {
    app_query($db,'DELETE FROM auth_attempts WHERE attempt_key=?',[app_auth_attempt_key($scope,$email)]);
}
function app_signin(mysqli $db, string $email, string $password, bool $admin = false): bool {
    $scope=$admin?'admin':'player';
    if(!app_auth_allowed($db,$scope,$email)) return false;
    $table = $admin ? 'admlogin' : 'appconfig';
    $result = app_query($db, "SELECT * FROM $table WHERE email = ?", [$email])->get_result();
    if ($result->num_rows !== 1) { app_auth_failed($db,$scope,$email); return false; }
    $user = $result->fetch_assoc();
    if (!app_password_matches($password, $user['senha'])) { app_auth_failed($db,$scope,$email); return false; }
    if (!$admin && in_array(strtolower((string) ($user['bloc'] ?? '')), ['on', '1', 'true'], true)) return false;
    if (password_needs_rehash($user['senha'], PASSWORD_DEFAULT)) app_query($db, "UPDATE $table SET senha = ? WHERE email = ? AND senha = ?", [password_hash($password, PASSWORD_DEFAULT), $email, $user['senha']]);
    session_regenerate_id(true);
    app_auth_clear($db,$scope,$email);
    $_SESSION[$admin ? 'emailadm' : 'email'] = $user['email'];
    if (!$admin) { $_SESSION['user_id'] = $user['id']; $_SESSION['demo_account'] = (string)($user['demo']??'0') === '1'; }
    return true;
}
function app_register(mysqli $db, array $input, string $affiliate, string $managerCode = '', ?int $demoManagerId = null): string {
    $email = strtolower(trim($input['email'])); $password = $input['senha'];
    $phone = preg_replace('/\D/', '', $input['telefone_confirmation']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) throw new InvalidArgumentException('Informe um e-mail válido.');
    if (strlen($password) < 6 || strlen($password) > 72) throw new InvalidArgumentException('Use uma senha entre 6 e 72 caracteres.');
    if ($password !== $input['password_confirmation']) throw new InvalidArgumentException('As senhas não coincidem.');
    if (!preg_match('/^\d{10,13}$/D', $phone)) throw new InvalidArgumentException('Informe um telefone válido com DDD.');
    if ($managerCode !== '' || $demoManagerId !== null) { require_once __DIR__ . '/manager.php'; manager_install($db); }
    if ((int) $db->query("SELECT GET_LOCK('sk_account_registration', 5)")->fetch_row()[0] !== 1) throw new RuntimeException('Cadastro ocupado.');
    try {
        $db->begin_transaction();
        if (app_query($db, 'SELECT id FROM appconfig WHERE email = ?', [$email])->get_result()->num_rows) throw new InvalidArgumentException('Já existe uma conta com esse e-mail. Entre na sua conta.');
        $app = $db->query('SELECT cpa, revenue_share FROM app LIMIT 1')->fetch_assoc() ?: [];
        $id = (string) ((int) $db->query('SELECT MAX(CAST(id AS UNSIGNED)) FROM appconfig')->fetch_row()[0] + 1);
        if ($managerCode !== '') $affiliate = '';
        if ($affiliate !== '' && !app_query($db, 'SELECT id FROM appconfig WHERE id = ?', [$affiliate])->get_result()->num_rows) $affiliate = '';
        $demo = $demoManagerId !== null;
        app_query($db, "INSERT INTO appconfig (id,email,senha,telefone,saldo,linkafiliado,indicados,plano,cpa,data_cadastro,afiliado,afiliado_ativo,demo,jogo_demo,total_apostado) VALUES (?,?,?,?,?,?,0,?,?,?,?,0,?,?,0)", [$id,$email,password_hash($password,PASSWORD_DEFAULT),$phone,$demo?'1000.00':'0',app_url('cadastrar/?aff=' . urlencode($id)),(string)($app['revenue_share']??0),(string)($app['cpa']??0),date('d-m-Y H:i'),$affiliate,$demo?'1':'0',$demo?'1':'0']);
        if ($demo) app_query($db,'INSERT INTO manager_demos(email,manager_id) VALUES(?,?)',[$email,(string)$demoManagerId]);
        elseif ($managerCode !== '') manager_referral_record($db,$email,$managerCode);
        $db->commit(); return $email;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
    finally { $db->query("SELECT RELEASE_LOCK('sk_account_registration')"); }
}
