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
function app_phone_normalize(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) >= 12 && strlen($digits) <= 13 && str_starts_with($digits, '55')) $digits = substr($digits, 2);
    return $digits;
}
function app_password_matches(string $password, string $stored): bool {
    if ((password_get_info($stored)['algoName'] ?? 'unknown') !== 'unknown') return password_verify($password, $stored);
    return $stored !== '' && hash_equals($stored, $password);
}
function app_password_fingerprint(string $stored): string { return hash('sha256', $stored); }
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
function app_auth_remember_cookie_name(string $scope): string {
    $names=['player'=>'SK_REMEMBER_PLAYER','admin'=>'SK_REMEMBER_ADMIN','manager'=>'SK_REMEMBER_MANAGER'];
    if(!isset($names[$scope])) throw new InvalidArgumentException('Tipo de sessão inválido.');
    return $names[$scope];
}
function app_auth_remember_install(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS auth_remember_tokens (
        selector CHAR(24) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        scope VARCHAR(16) NOT NULL,
        subject VARCHAR(254) NOT NULL,
        validator_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(scope,subject),
        INDEX(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function app_auth_remember_clear_cookie(string $scope): void {
    $params=session_get_cookie_params();
    setcookie(app_auth_remember_cookie_name($scope),'',['expires'=>time()-3600,'path'=>$params['path']?:'/', 'secure'=>(bool)$params['secure'],'httponly'=>true,'samesite'=>'Lax']);
    unset($_COOKIE[app_auth_remember_cookie_name($scope)]);
}
function app_auth_remember(mysqli $db,string $scope,string $subject,bool $enabled): void {
    app_auth_remember_install($db);
    $cookieName=app_auth_remember_cookie_name($scope);
    $old=$_COOKIE[$cookieName]??'';
    if(is_string($old)&&preg_match('/^([a-f0-9]{24})\\.[a-f0-9]{64}$/D',$old,$match)) app_query($db,'DELETE FROM auth_remember_tokens WHERE selector=? AND scope=?',[$match[1],$scope]);
    app_auth_remember_clear_cookie($scope);
    if(!$enabled)return;
    $selector=bin2hex(random_bytes(12));$validator=bin2hex(random_bytes(32));
    $days=$scope==='admin'?7:30;$expires=time()+$days*86400;
    app_query($db,'INSERT INTO auth_remember_tokens(selector,scope,subject,validator_hash,expires_at) VALUES(?,?,?,?,?)',[$selector,$scope,$subject,hash('sha256',$validator),date('Y-m-d H:i:s',$expires)]);
    $params=session_get_cookie_params();
    setcookie($cookieName,$selector.'.'.$validator,['expires'=>$expires,'path'=>$params['path']?:'/', 'secure'=>(bool)$params['secure'],'httponly'=>true,'samesite'=>'Lax']);
}
function app_auth_forget(mysqli $db,string $scope): void {
    $cookieName=app_auth_remember_cookie_name($scope);$raw=$_COOKIE[$cookieName]??'';
    if(is_string($raw)&&preg_match('/^([a-f0-9]{24})\\.[a-f0-9]{64}$/D',$raw,$match)) {
        app_auth_remember_install($db);
        app_query($db,'DELETE FROM auth_remember_tokens WHERE selector=? AND scope=?',[$match[1],$scope]);
    }
    app_auth_remember_clear_cookie($scope);
}
function app_auth_restore_remembered(mysqli $db): void {
    $regenerated=false;
    foreach(['player','admin','manager'] as $scope) {
        $sessionKey=['player'=>'email','admin'=>'emailadm','manager'=>'manager_id'][$scope];
        $cookieName=app_auth_remember_cookie_name($scope);$raw=$_COOKIE[$cookieName]??'';
        if(!empty($_SESSION[$sessionKey]))continue;
        if(!is_string($raw)||$raw==='' )continue;
        if(!preg_match('/^([a-f0-9]{24})\\.([a-f0-9]{64})$/D',$raw,$parts)){app_auth_remember_clear_cookie($scope);continue;}
        $row=app_query($db,'SELECT subject,validator_hash FROM auth_remember_tokens WHERE selector=? AND scope=? AND expires_at>NOW() LIMIT 1',[$parts[1],$scope])->get_result()->fetch_assoc();
        if(!$row||!hash_equals((string)$row['validator_hash'],hash('sha256',$parts[2]))) {
            if($row)app_query($db,'DELETE FROM auth_remember_tokens WHERE selector=? AND scope=?',[$parts[1],$scope]);
            app_auth_remember_clear_cookie($scope);continue;
        }
        $subject=(string)$row['subject'];$account=null;
        if($scope==='player') {
            $account=app_query($db,'SELECT id,email,demo,bloc,senha FROM appconfig WHERE email=? LIMIT 1',[$subject])->get_result()->fetch_assoc();
            if($account&&in_array(strtolower((string)($account['bloc']??'')),['on','1','true'],true))$account=null;
        }elseif($scope==='admin') {
            $account=app_query($db,'SELECT email,senha FROM admlogin WHERE email=? LIMIT 1',[$subject])->get_result()->fetch_assoc();
        }else {
            $account=app_query($db,'SELECT id,password_hash FROM manager_accounts WHERE id=? AND active=1 LIMIT 1',[$subject])->get_result()->fetch_assoc();
        }
        if(!$account) {
            app_query($db,'DELETE FROM auth_remember_tokens WHERE selector=? AND scope=?',[$parts[1],$scope]);
            app_auth_remember_clear_cookie($scope);continue;
        }
        if(!$regenerated){session_regenerate_id(true);$regenerated=true;}
        if($scope==='player'){$_SESSION['email']=$account['email'];$_SESSION['user_id']=$account['id'];$_SESSION['demo_account']=(string)($account['demo']??'0')==='1';$_SESSION['player_auth_hash']=app_password_fingerprint((string)$account['senha']);}
        elseif($scope==='admin'){$_SESSION['emailadm']=$account['email'];$_SESSION['admin_auth_hash']=app_password_fingerprint((string)$account['senha']);}
        else {$_SESSION['manager_id']=(int)$account['id'];$_SESSION['manager_auth_hash']=app_password_fingerprint((string)$account['password_hash']);}
    }
}
function app_signin(mysqli $db, string $identifier, string $password, bool $admin = false): bool {
    $scope=$admin?'admin':'player';
    $lookup = $admin ? strtolower(trim($identifier)) : app_phone_normalize($identifier);
    if(!app_auth_allowed($db,$scope,$lookup)) return false;
    $table = $admin ? 'admlogin' : 'appconfig';
    if ($admin) {
        $result = app_query($db, 'SELECT * FROM admlogin WHERE email=? LIMIT 2', [$lookup])->get_result();
    } else {
        if (!preg_match('/^\d{10,11}$/D', $lookup)) { app_auth_failed($db,$scope,$lookup); return false; }
        $withCountryCode = '55' . $lookup;
        $result = app_query($db, 'SELECT * FROM appconfig WHERE telefone IN (?,?) LIMIT 2', [$lookup,$withCountryCode])->get_result();
    }
    if ($result->num_rows !== 1) { app_auth_failed($db,$scope,$lookup); return false; }
    $user = $result->fetch_assoc();
    if (!app_password_matches($password, $user['senha'])) { app_auth_failed($db,$scope,$lookup); return false; }
    if (!$admin && in_array(strtolower((string) ($user['bloc'] ?? '')), ['on', '1', 'true'], true)) return false;
    if (password_needs_rehash($user['senha'], PASSWORD_DEFAULT)) {
        $newHash=password_hash($password, PASSWORD_DEFAULT);
        $updated=app_query($db, "UPDATE $table SET senha = ? WHERE email = ? AND senha = ?", [$newHash, $user['email'], $user['senha']]);
        if($updated->affected_rows!==1)return false;
        $user['senha']=$newHash;
    }
    session_regenerate_id(true);
    app_auth_clear($db,$scope,$lookup);
    $_SESSION[$admin ? 'emailadm' : 'email'] = $user['email'];
    $_SESSION[$admin?'admin_auth_hash':'player_auth_hash']=app_password_fingerprint((string)$user['senha']);
    if (!$admin) { $_SESSION['user_id'] = $user['id']; $_SESSION['demo_account'] = (string)($user['demo']??'0') === '1'; }
    app_auth_remember($db,$admin?'admin':'player',(string)$user['email'],!empty($_POST['remember_me']));
    return true;
}
function app_register(mysqli $db, array $input, string $affiliate, string $managerCode = '', ?int $demoManagerId = null, string $managerInfluencerId = ''): string {
    $email = strtolower(trim((string)($input['email'] ?? ''))); $password = (string)($input['senha'] ?? '');
    $name = trim((string)($input['nome'] ?? ''));
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    // Keep the former internal key as a compatibility fallback for demo creation.
    $phone = app_phone_normalize((string)($input['telefone'] ?? $input['telefone_confirmation'] ?? ''));
    if (!preg_match('/^.{2,120}$/usD', $name)) throw new InvalidArgumentException('Informe seu nome completo (de 2 a 120 caracteres).');
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254)) throw new InvalidArgumentException('Informe um e-mail válido.');
    if (strlen($password) < 6 || strlen($password) > 72) throw new InvalidArgumentException('Use uma senha entre 6 e 72 caracteres.');
    if (isset($input['password_confirmation']) && !hash_equals($password, (string)$input['password_confirmation'])) throw new InvalidArgumentException('As senhas não coincidem.');
    if (!preg_match('/^\d{10,11}$/D', $phone)) throw new InvalidArgumentException('Informe um celular válido com DDD. Exemplo: 11987654321.');
    if ($email === '') $email = 'tel-' . substr(hash('sha256', $phone), 0, 32) . '@login.subwayrun.invalid';
    if ($managerCode !== '' || $demoManagerId !== null) { require_once __DIR__ . '/manager.php'; manager_install($db); }
    if ((int) $db->query("SELECT GET_LOCK('sk_account_registration', 5)")->fetch_row()[0] !== 1) throw new RuntimeException('Cadastro ocupado.');
    try {
        $db->query('CREATE TABLE IF NOT EXISTS deleted_user_ids (id BIGINT UNSIGNED PRIMARY KEY, deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $db->begin_transaction();
        if (app_query($db, 'SELECT id FROM appconfig WHERE telefone IN (?,?) LIMIT 1', [$phone,'55'.$phone])->get_result()->num_rows) throw new InvalidArgumentException('Já existe uma conta com esse telefone. Entre usando seu celular.');
        if (app_query($db, 'SELECT id FROM appconfig WHERE email = ? LIMIT 1', [$email])->get_result()->num_rows) throw new InvalidArgumentException('Não foi possível criar a conta com este telefone. Entre em contato com o suporte.');
        $app = $db->query('SELECT cpa, revenue_share FROM app LIMIT 1')->fetch_assoc() ?: [];
        $id = (string) ((int) $db->query('SELECT GREATEST(COALESCE((SELECT MAX(CAST(id AS UNSIGNED)) FROM appconfig),0),COALESCE((SELECT MAX(id) FROM deleted_user_ids),0))')->fetch_row()[0] + 1);
        $managerPartner = null; $managerInfluencerEmail = '';
        if ($managerCode !== '') {
            if (!preg_match('/^[a-f0-9]{24}$/D', $managerCode)) throw new InvalidArgumentException('Este link de parceria não é válido.');
            $partnerStmt = app_query($db, 'SELECT id,manager_id,name,influencer_email,active FROM manager_partners WHERE code=? FOR UPDATE', [$managerCode]);
            $managerPartner = $partnerStmt->get_result()->fetch_assoc();
            if (!$managerPartner || !(int)$managerPartner['active']) throw new InvalidArgumentException('Este link de parceria está indisponível.');
            if ($managerInfluencerId !== '') {
                $ownerStmt = app_query($db, 'SELECT id,email FROM appconfig WHERE id=? LIMIT 1', [$managerInfluencerId]);
                $owner = $ownerStmt->get_result()->fetch_assoc();
                if (!$owner || empty($managerPartner['influencer_email']) || !hash_equals(strtolower((string)$managerPartner['influencer_email']), strtolower((string)$owner['email']))) throw new InvalidArgumentException('O link do influenciador não corresponde a uma parceria ativa.');
                $managerInfluencerEmail = (string)$owner['email'];
            } elseif (empty($managerPartner['influencer_email'])) {
                $demoManagerId = (int)$managerPartner['manager_id'];
                $managerInfluencerEmail = $email;
            } else {
                throw new InvalidArgumentException('Este convite já foi ativado pelo influenciador. Use o link pessoal que ele compartilhou.');
            }
        }
        if ($managerCode !== '') $affiliate = '';
        if ($affiliate !== '' && !app_query($db, 'SELECT id FROM appconfig WHERE id = ?', [$affiliate])->get_result()->num_rows) $affiliate = '';
        $demo = $demoManagerId !== null;
        app_query($db, "INSERT INTO appconfig (id,nome,email,senha,telefone,saldo,linkafiliado,indicados,plano,cpa,data_cadastro,afiliado,afiliado_ativo,demo,jogo_demo,total_apostado) VALUES (?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,0)", [$id,$name,$email,password_hash($password,PASSWORD_DEFAULT),$phone,$demo?'1000.00':'0',app_url('cadastrar/?aff=' . urlencode($id)),'50.00',(string)($app['cpa']??0),date('d-m-Y H:i'),$affiliate,$demo?'0':'1',$demo?'1':'0',$demo?'1':'0']);
        if ($demo) app_query($db,'INSERT INTO manager_demos(email,manager_id,display_name) VALUES(?,?,?)',[$email,(string)$demoManagerId,$name]);
        if ($managerPartner && $managerInfluencerId === '') {
            $claimed=app_query($db,"UPDATE manager_partners SET influencer_email=? WHERE id=? AND (influencer_email IS NULL OR influencer_email='')",[$email,(string)$managerPartner['id']]);
            if ($claimed->affected_rows !== 1) throw new InvalidArgumentException('Este convite já foi ativado por outra conta.');
        } elseif ($managerCode !== '') manager_referral_record($db,$email,$managerCode,$managerInfluencerEmail);
        $db->commit(); return $email;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
    finally { $db->query("SELECT RELEASE_LOCK('sk_account_registration')"); }
}
