<?php
require_once __DIR__ . '/auth.php';
const MANAGER_BUDGET_PERCENT = 70.0;
function manager_install(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS manager_accounts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(254) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS manager_partners (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, manager_id INT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, code CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, manager_percent DECIMAL(5,2) NOT NULL, influencer_percent DECIMAL(5,2) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(manager_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS manager_referrals (email VARCHAR(254) NOT NULL PRIMARY KEY, partner_id INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(partner_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS manager_demos (email VARCHAR(254) NOT NULL PRIMARY KEY, manager_id INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(manager_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS manager_demo_balance_log (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, manager_id INT UNSIGNED NOT NULL, email VARCHAR(254) NOT NULL, old_balance DECIMAL(12,2) NOT NULL, new_balance DECIMAL(12,2) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(manager_id,created_at), INDEX(email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS manager_commissions (reference VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, partner_id INT UNSIGNED NOT NULL, manager_id INT UNSIGNED NOT NULL, deposit_amount DECIMAL(12,2) NOT NULL, manager_percent DECIMAL(5,2) NOT NULL, influencer_percent DECIMAL(5,2) NOT NULL, manager_amount DECIMAL(12,2) NOT NULL, influencer_amount DECIMAL(12,2) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(manager_id), INDEX(partner_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS manager_payout_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, manager_id INT UNSIGNED NOT NULL, amount DECIMAL(12,2) NOT NULL, pix_key VARCHAR(77) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'PENDING', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, processed_at DATETIME NULL, processed_by VARCHAR(254) NULL, INDEX(manager_id,status), INDEX(status,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $commissionInfluencerColumn = $db->query("SHOW COLUMNS FROM manager_commissions LIKE 'influencer_email'")->fetch_assoc();
    if (!$commissionInfluencerColumn) $db->query("ALTER TABLE manager_commissions ADD influencer_email VARCHAR(254) NULL AFTER manager_id, ADD INDEX idx_manager_commission_influencer (influencer_email)");
    $commissionReferral = $db->query("SHOW COLUMNS FROM manager_commissions LIKE 'referred_email'")->fetch_assoc();
    if (!$commissionReferral) $db->query("ALTER TABLE manager_commissions ADD referred_email VARCHAR(254) NULL AFTER influencer_email, ADD INDEX idx_manager_commission_referred (referred_email)");
    try{$db->query("UPDATE manager_commissions c JOIN bxpay_deposits d ON d.reference=c.reference SET c.referred_email=d.email WHERE c.referred_email IS NULL");}catch(Throwable $ignored){}
    try{$db->query("UPDATE manager_commissions c JOIN confirmar_deposito d ON d.externalreference=c.reference SET c.referred_email=d.email WHERE c.referred_email IS NULL");}catch(Throwable $ignored){}
    $db->query("CREATE TABLE IF NOT EXISTS manager_notification_settings (manager_id INT UNSIGNED PRIMARY KEY, webhook_url VARCHAR(512) NOT NULL DEFAULT '', event_signup TINYINT(1) NOT NULL DEFAULT 1, event_deposit TINYINT(1) NOT NULL DEFAULT 1, event_sale TINYINT(1) NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS manager_notification_outbox (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_key VARCHAR(191) NOT NULL UNIQUE, manager_id INT UNSIGNED NOT NULL, event_type VARCHAR(16) NOT NULL, title VARCHAR(120) NOT NULL, message VARCHAR(500) NOT NULL, attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(manager_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $demoNameColumn = $db->query("SHOW COLUMNS FROM manager_demos LIKE 'display_name'")->fetch_assoc();
    if (!$demoNameColumn) $db->query("ALTER TABLE manager_demos ADD display_name VARCHAR(120) NOT NULL DEFAULT 'Conta demo' AFTER manager_id");
    $demoPasswordColumn=$db->query("SHOW COLUMNS FROM manager_demos LIKE 'password_encrypted'")->fetch_assoc();
    if(!$demoPasswordColumn)$db->query('ALTER TABLE manager_demos ADD password_encrypted TEXT NULL AFTER display_name');
    $partnerInfluencerColumn = $db->query("SHOW COLUMNS FROM manager_partners LIKE 'influencer_email'")->fetch_assoc();
    if (!$partnerInfluencerColumn) $db->query("ALTER TABLE manager_partners ADD influencer_email VARCHAR(254) NULL AFTER code");
    $refInfluencerColumn = $db->query("SHOW COLUMNS FROM manager_referrals LIKE 'influencer_email'")->fetch_assoc();
    if (!$refInfluencerColumn) $db->query("ALTER TABLE manager_referrals ADD influencer_email VARCHAR(254) NULL AFTER partner_id");
}
function manager_referral_record(mysqli $db,string $email,string $code,string $influencerEmail=''): void {
    if (!preg_match('/^[a-f0-9]{24}$/D',$code)) return;
    $stmt=app_query($db,'SELECT id,manager_id,influencer_email FROM manager_partners WHERE code=? AND active=1',[$code]);
    $partner=$stmt->get_result()->fetch_assoc();
    if($partner) {
        $owner=(string)($partner['influencer_email']??'');
        if($owner===''||!hash_equals(strtolower($owner),strtolower($influencerEmail))) return;
        app_query($db,'INSERT INTO manager_referrals(email,partner_id,influencer_email) VALUES(?,?,?)',[$email,(string)$partner['id'],$owner]);
        $account=app_query($db,'SELECT nome,telefone FROM appconfig WHERE email=? LIMIT 1',[$email])->get_result()->fetch_assoc()?:[];
        $level=manager_referral_level($db,(int)$partner['id'],(string)$partner['influencer_email'],$owner);
        manager_notification_enqueue($db,(int)$partner['manager_id'],'signup','signup:'.$email,'Cadastro realizado',trim((string)($account['nome']??'Novo indicado')).' entrou na sua rede (N'.$level.'). Telefone: '.(string)($account['telefone']??''));
    }
}
function manager_commission_record(mysqli $db,string $reference,string $email,float $amount): void {
    $stmt=app_query($db,'SELECT p.*,r.influencer_email,m.active AS manager_active FROM manager_referrals r JOIN manager_partners p ON p.id=r.partner_id JOIN manager_accounts m ON m.id=p.manager_id WHERE r.email=?',[$email]);
    $p=$stmt->get_result()->fetch_assoc();
    if(!$p || !(int)$p['manager_active']) return;
    $mp=(float)$p['manager_percent']; $ip=(float)$p['influencer_percent'];
    if($mp<0 || $ip<0 || abs($mp+$ip-MANAGER_BUDGET_PERCENT)>.0001) { error_log('Divisão de gerente inválida no convite '.$p['id']); return; }
    $ma=round($amount*$mp/100,2); $ia=round($amount*$ip/100,2);
    $insert=app_query($db,'INSERT IGNORE INTO manager_commissions(reference,partner_id,manager_id,influencer_email,referred_email,deposit_amount,manager_percent,influencer_percent,manager_amount,influencer_amount) VALUES(?,?,?,?,?,?,?,?,?,?)',[$reference,(string)$p['id'],(string)$p['manager_id'],(string)$p['influencer_email'],$email,(string)$amount,(string)$mp,(string)$ip,(string)$ma,(string)$ia]);
    if($insert->affected_rows===1){
        $account=app_query($db,'SELECT nome FROM appconfig WHERE email=? LIMIT 1',[$email])->get_result()->fetch_assoc()?:[];
        $name=trim((string)($account['nome']??'Indicado'));
        $ref=app_query($db,'SELECT influencer_email FROM manager_referrals WHERE email=? AND partner_id=? LIMIT 1',[$email,(string)$p['id']])->get_result()->fetch_assoc();
        $level=manager_referral_level($db,(int)$p['id'],(string)$p['influencer_email'],(string)($ref['influencer_email']??''));
        manager_notification_enqueue($db,(int)$p['manager_id'],'deposit','deposit:'.$reference,'Depósito realizado',$name.' depositou R$ '.number_format($amount,2,',','.').' (N'.$level.').');
        if($ma>0) manager_notification_enqueue($db,(int)$p['manager_id'],'sale','sale:'.$reference,'Venda aprovada','Comissão de R$ '.number_format($ma,2,',','.').' aprovada por depósito de '.$name.' (N'.$level.').');
    }
}
function manager_referral_level(mysqli $db,int $partnerId,string $rootEmail,string $parentEmail): int {
    $frontier=[strtolower($rootEmail)];$target=strtolower($parentEmail);if($target===''||$target===strtolower($rootEmail))return 1;
    // Walk the small referral graph without relying on recursive-CTE support.
    $depth=[strtolower($rootEmail)=>0];$all=app_query($db,'SELECT email,influencer_email FROM manager_referrals WHERE partner_id=?',[(string)$partnerId])->get_result()->fetch_all(MYSQLI_ASSOC);
    for($pass=0;$pass<3;$pass++){foreach($all as $row){$email=strtolower((string)$row['email']);$parent=strtolower((string)$row['influencer_email']);if(isset($depth[$parent])&&!isset($depth[$email]))$depth[$email]=$depth[$parent]+1;}if(isset($depth[$target]))return min(3,$depth[$target]+1);}
    return 1;
}
function manager_pushcut_url_valid(string $url): bool {
    if(strlen($url)>512||str_contains($url,"\r")||str_contains($url,"\n"))return false;
    $parts=parse_url(trim($url));
    return is_array($parts)&&($parts['scheme']??'')==='https'&&strtolower((string)($parts['host']??''))==='api.pushcut.io'&&!isset($parts['user'])&&!isset($parts['pass'])&&!isset($parts['query'])&&!isset($parts['fragment'])&&preg_match('~^/[A-Za-z0-9_-]{8,160}/notifications/[A-Za-z0-9_-]{1,100}/?$~D',(string)($parts['path']??''))===1;
}
function manager_notification_enqueue(mysqli $db,int $managerId,string $type,string $key,string $title,string $message): void {
    if(!in_array($type,['signup','deposit','sale'],true))return;
    app_query($db,'INSERT IGNORE INTO manager_notification_outbox(event_key,manager_id,event_type,title,message) VALUES(?,?,?,?,?)',[$key,(string)$managerId,$type,substr($title,0,120),substr($message,0,500)]);
}
function manager_pushcut_send(string $url,string $title,string $message): bool {
    if(!manager_pushcut_url_valid($url)||!function_exists('curl_init'))return false;
    $target=$url.'?'.http_build_query(['title'=>$title,'text'=>$message],'','&',PHP_QUERY_RFC3986);
    $curl=curl_init($target);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_errno($curl);curl_close($curl);
    return $error===0&&$status>=200&&$status<300;
}
function manager_pushcut_flush(mysqli $db,int $limit=20): void {
    manager_install($db);
    $rows=$db->query("SELECT o.id,o.manager_id,o.event_type,o.title,o.message,s.webhook_url,s.event_signup,s.event_deposit,s.event_sale FROM manager_notification_outbox o JOIN manager_notification_settings s ON s.manager_id=o.manager_id WHERE o.attempts<5 ORDER BY o.id LIMIT ".max(1,min(50,$limit)))->fetch_all(MYSQLI_ASSOC);
    $eventColumns=['signup'=>'event_signup','deposit'=>'event_deposit','sale'=>'event_sale'];
    foreach($rows as $row){
        $enabled=(int)($row[$eventColumns[$row['event_type']]??'event_signup']??0)===1;
        $ok=!$enabled||manager_pushcut_send(manager_secret_decrypt((string)$row['webhook_url']),(string)$row['title'],(string)$row['message']);
        if($ok)app_query($db,'DELETE FROM manager_notification_outbox WHERE id=?',[(string)$row['id']]);
        else app_query($db,'UPDATE manager_notification_outbox SET attempts=attempts+1 WHERE id=?',[(string)$row['id']]);
    }
}
function manager_login(mysqli $db,string $email,string $password): bool {
    manager_install($db);
    $email=strtolower(trim($email));
    if(!app_auth_allowed($db,'manager',$email)) return false;
    $stmt=app_query($db,'SELECT id,password_hash,active FROM manager_accounts WHERE email=?',[$email]);
    $row=$stmt->get_result()->fetch_assoc();
    if(!$row || !(int)$row['active'] || !password_verify($password,$row['password_hash'])) { app_auth_failed($db,'manager',$email); return false; }
    app_auth_clear($db,'manager',$email);
    session_regenerate_id(true); $_SESSION['manager_id']=(int)$row['id']; $_SESSION['manager_auth_hash']=app_password_fingerprint((string)$row['password_hash']);
    app_auth_remember($db,'manager',(string)$row['id'],!empty($_POST['remember_me']));
    return true;
}
function manager_register(mysqli $db,string $name,string $email,string $password,string $confirmation): int {
    manager_install($db);$name=trim($name);$email=strtolower(trim($email));
    if(strlen($name)<2||strlen($name)>120)throw new InvalidArgumentException('Informe seu nome (2 a 120 caracteres).');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new InvalidArgumentException('Informe um e-mail válido.');
    if(strlen($password)<10||strlen($password)>72)throw new InvalidArgumentException('Use uma senha com pelo menos 10 caracteres.');
    if(!hash_equals($password,$confirmation))throw new InvalidArgumentException('As senhas não coincidem.');
    if(app_query($db,'SELECT id FROM manager_accounts WHERE email=? LIMIT 1',[$email])->get_result()->fetch_assoc())throw new InvalidArgumentException('Já existe um acesso de gerente com esse e-mail.');
    app_query($db,'INSERT INTO manager_accounts(name,email,password_hash,active) VALUES(?,?,?,1)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    return (int)$db->insert_id;
}
function manager_current(mysqli $db): ?array {
    if(empty($_SESSION['manager_id'])) return null;
    $stmt=app_query($db,'SELECT id,name,email FROM manager_accounts WHERE id=? AND active=1',[(string)$_SESSION['manager_id']]);
    return $stmt->get_result()->fetch_assoc() ?: null;
}
function manager_partner_create(mysqli $db,int $managerId,string $name,float $mp,float $ip): string {
    $name=trim($name);
    if(strlen($name)<2 || strlen($name)>120) throw new InvalidArgumentException('Informe o nome do influenciador.');
    if(!is_finite($mp)||!is_finite($ip)||$mp<0||$ip<0||abs($mp+$ip-MANAGER_BUDGET_PERCENT)>.0001 || abs($mp-round($mp,2))>.0001 || abs($ip-round($ip,2))>.0001) throw new InvalidArgumentException('Divida exatamente 70% entre gerente e influenciador. Os outros 30% ficam com a plataforma.');
    $code=bin2hex(random_bytes(12));
    app_query($db,'INSERT INTO manager_partners(manager_id,name,code,manager_percent,influencer_percent) VALUES(?,?,?,?,?)',[(string)$managerId,$name,$code,(string)$mp,(string)$ip]);
    return $code;
}
function manager_demo_create(mysqli $db,int $managerId,string $email,string $password): void {
    $email=strtolower(trim($email));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254) throw new InvalidArgumentException('E-mail demo inválido.');
    if(strlen($password)<8||strlen($password)>72) throw new InvalidArgumentException('A senha demo deve ter de 8 a 72 caracteres.');
    $phone='119'.str_pad((string)random_int(0,99999999),8,'0',STR_PAD_LEFT);
    app_register($db,['nome'=>'Conta de demonstração','email'=>$email,'senha'=>$password,'password_confirmation'=>$password,'telefone_confirmation'=>$phone],'','',$managerId);
}
function manager_demo_crypto_key(): string {
    require SK_ROOT.'/conectarbanco.php';
    $secret=(string)(getenv('APP_KEY')?:($config['db_pass']??''));
    if($secret==='')throw new RuntimeException('Não foi possível proteger as credenciais demo neste servidor.');
    return hash('sha256','SubwayRun manager demo credential|'.$secret.'|'.SK_ROOT,true);
}
function manager_demo_encrypt_password(string $password): string {
    $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($password,'aes-256-gcm',manager_demo_crypto_key(),OPENSSL_RAW_DATA,$iv,$tag,'',16);
    if($cipher===false)throw new RuntimeException('Não foi possível proteger a senha demo.');
    return 'v1.'.base64_encode($iv).'.'.base64_encode($tag).'.'.base64_encode($cipher);
}
function manager_demo_decrypt_password(string $encrypted): string {
    $parts=explode('.',$encrypted);if(count($parts)!==4||$parts[0]!=='v1')return '';
    $iv=base64_decode($parts[1],true);$tag=base64_decode($parts[2],true);$cipher=base64_decode($parts[3],true);if($iv===false||$tag===false||$cipher===false)return '';
    $plain=openssl_decrypt($cipher,'aes-256-gcm',manager_demo_crypto_key(),OPENSSL_RAW_DATA,$iv,$tag,'');return is_string($plain)?$plain:'';
}
function manager_secret_encrypt(string $secret): string { return manager_demo_encrypt_password($secret); }
function manager_secret_decrypt(string $stored): string { return str_starts_with($stored,'v1.')?manager_demo_decrypt_password($stored):$stored; }
function manager_demo_create_auto(mysqli $db,int $managerId,string $initialBalance='1000',string $namePrefix='',string $passwordPrefix=''): array {
    if(!preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?$/D',$initialBalance))throw new InvalidArgumentException('Informe um saldo inicial entre R$ 0,00 e R$ 999.999,99.');
    $namePrefix=trim($namePrefix);$passwordPrefix=trim($passwordPrefix);if(strlen($namePrefix)>80||strlen($passwordPrefix)>55)throw new InvalidArgumentException('O padrão de nome ou senha é muito longo.');
    $suffix=bin2hex(random_bytes(5));
    $email='demo-'.$managerId.'-'.$suffix.'@subwayrun.demo';
    $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $password=$passwordPrefix;
    while(strlen($password)<14)$password.=$alphabet[random_int(0,strlen($alphabet)-1)];
    if(strlen($password)<8||strlen($password)>72)throw new InvalidArgumentException('O padrão de senha precisa ter até 55 caracteres.');
    manager_demo_create($db,$managerId,$email,$password);
    $firstNames=['Alice','Ana','Beatriz','Bruno','Camila','Caio','Clara','Daniel','Eduarda','Felipe','Gabriel','Helena','Igor','Isabela','João','Julia','Lucas','Mariana','Mateus','Rafaela','Thiago','Valentina'];
    $lastNames=['Almeida','Barbosa','Carvalho','Costa','Dias','Fernandes','Ferreira','Lima','Martins','Mendes','Oliveira','Pereira','Ribeiro','Rocha','Santos','Silva'];
    $name=trim(($namePrefix!==''?$namePrefix.' ':'').$firstNames[random_int(0,count($firstNames)-1)].' '.$lastNames[random_int(0,count($lastNames)-1)]);
    app_query($db,'UPDATE manager_demos SET display_name=? WHERE manager_id=? AND email=?',[$name,(string)$managerId,$email]);
    app_query($db,'UPDATE appconfig SET nome=? WHERE email=? AND demo=1',[$name,$email]);
    $encrypted=manager_demo_encrypt_password($password);
    app_query($db,'UPDATE manager_demos SET password_encrypted=? WHERE manager_id=? AND email=?',[$encrypted,(string)$managerId,$email]);
    if((float)$initialBalance!==1000.0)manager_demo_set_balance($db,$managerId,$email,$initialBalance);
    return ['name'=>$name,'email'=>$email,'password'=>$password];
}
function manager_demo_update(mysqli $db,int $managerId,string $email,string $name,string $balance,string $password=''): void {
    $email=strtolower(trim($email));
    $name=trim($name);
    if(strlen($name)<2||strlen($name)>120) throw new InvalidArgumentException('Informe um nome entre 2 e 120 caracteres.');
    if(!preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?$/D',$balance)) throw new InvalidArgumentException('Informe um saldo entre R$ 0,00 e R$ 999.999,99.');
    if($password!==''&&(strlen($password)<8||strlen($password)>72)) throw new InvalidArgumentException('A nova senha deve ter de 8 a 72 caracteres.');
    if($password!=='') app_auth_remember_install($db);
    $newBalance=number_format((float)$balance,2,'.','');
    $db->begin_transaction();
    try {
        $row=app_query($db,'SELECT a.saldo FROM manager_demos d JOIN appconfig a ON a.email COLLATE utf8mb4_unicode_ci=d.email COLLATE utf8mb4_unicode_ci WHERE d.manager_id=? AND d.email=? AND a.demo=1 FOR UPDATE',[(string)$managerId,$email])->get_result()->fetch_assoc();
        if(!$row) throw new InvalidArgumentException('Conta demo não encontrada para este gerente.');
        app_query($db,'UPDATE manager_demos SET display_name=? WHERE manager_id=? AND email=?',[$name,(string)$managerId,$email]);
        app_query($db,'UPDATE appconfig SET nome=?,saldo=? WHERE email=? AND demo=1',[$name,$newBalance,$email]);
        if($password!=='') { app_query($db,'UPDATE appconfig SET senha=? WHERE email=? AND demo=1',[password_hash($password,PASSWORD_DEFAULT),$email]);app_query($db,'UPDATE manager_demos SET password_encrypted=? WHERE manager_id=? AND email=?',[manager_demo_encrypt_password($password),(string)$managerId,$email]);app_query($db,"DELETE FROM auth_remember_tokens WHERE scope='player' AND subject=?",[$email]); }
        if((float)$row['saldo']!==(float)$newBalance) app_query($db,'INSERT INTO manager_demo_balance_log(manager_id,email,old_balance,new_balance) VALUES(?,?,?,?)',[(string)$managerId,$email,(string)$row['saldo'],$newBalance]);
        $db->commit();
    } catch(Throwable $error) { $db->rollback(); throw $error; }
}
function manager_demo_set_balance(mysqli $db,int $managerId,string $email,string $amount): void {
    $email=strtolower(trim($email));
    if(!preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?$/D',$amount)) throw new InvalidArgumentException('Informe um saldo de treino entre R$ 0,00 e R$ 999.999,99.');
    $newBalance=number_format((float)$amount,2,'.','');
    $db->begin_transaction();
    try {
        $row=app_query($db,'SELECT a.saldo FROM manager_demos d JOIN appconfig a ON a.email COLLATE utf8mb4_unicode_ci=d.email COLLATE utf8mb4_unicode_ci WHERE d.manager_id=? AND d.email=? AND a.demo=1 FOR UPDATE',[(string)$managerId,$email])->get_result()->fetch_assoc();
        if(!$row) throw new InvalidArgumentException('Conta demo não encontrada para este gerente.');
        app_query($db,'UPDATE appconfig SET saldo=? WHERE email=? AND demo=1',[$newBalance,$email]);
        app_query($db,'INSERT INTO manager_demo_balance_log(manager_id,email,old_balance,new_balance) VALUES(?,?,?,?)',[(string)$managerId,$email,(string)$row['saldo'],$newBalance]);
        $db->commit();
    } catch(Throwable $error) { $db->rollback(); throw $error; }
}
function manager_payout_available(mysqli $db,int $managerId): string {
    $earned=app_query($db,'SELECT COALESCE(SUM(manager_amount),0) AS amount FROM manager_commissions WHERE manager_id=?',[(string)$managerId])->get_result()->fetch_assoc();
    $reserved=app_query($db,"SELECT COALESCE(SUM(amount),0) AS amount FROM manager_payout_requests WHERE manager_id=? AND status IN ('PENDING','PAID')",[(string)$managerId])->get_result()->fetch_assoc();
    return number_format(max(0,(float)$earned['amount']-(float)$reserved['amount']),2,'.','');
}
function manager_payout_request(mysqli $db,int $managerId,string $amount,string $pixKey): void {
    $pixKey=trim($pixKey);
    if(!preg_match('/^(?:[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/D',$amount) || (float)$amount<1) throw new InvalidArgumentException('Informe um valor de saque a partir de R$ 1,00.');
    if(strlen($pixKey)<5 || strlen($pixKey)>77 || preg_match('/[\x00-\x1F\x7F]/',$pixKey)) throw new InvalidArgumentException('Informe uma chave PIX válida.');
    $value=number_format((float)$amount,2,'.','');
    $db->begin_transaction();
    try {
        $account=app_query($db,'SELECT id FROM manager_accounts WHERE id=? AND active=1 FOR UPDATE',[(string)$managerId])->get_result()->fetch_assoc();
        if(!$account) throw new InvalidArgumentException('Acesso de gerente indisponível.');
        if((float)$value>(float)manager_payout_available($db,$managerId)) throw new InvalidArgumentException('Saldo de comissão insuficiente.');
        app_query($db,"INSERT INTO manager_payout_requests(manager_id,amount,pix_key,status) VALUES(?,?,?,'PENDING')",[(string)$managerId,$value,$pixKey]);
        $db->commit();
    } catch(Throwable $error) { $db->rollback(); throw $error; }
}
