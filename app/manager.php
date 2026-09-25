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
    $demoNameColumn = $db->query("SHOW COLUMNS FROM manager_demos LIKE 'display_name'")->fetch_assoc();
    if (!$demoNameColumn) $db->query("ALTER TABLE manager_demos ADD display_name VARCHAR(120) NOT NULL DEFAULT 'Conta demo' AFTER manager_id");
}
function manager_referral_record(mysqli $db,string $email,string $code): void {
    if (!preg_match('/^[a-f0-9]{24}$/D',$code)) return;
    $stmt=app_query($db,'SELECT id FROM manager_partners WHERE code=? AND active=1',[$code]);
    $partner=$stmt->get_result()->fetch_assoc();
    if($partner) app_query($db,'INSERT INTO manager_referrals(email,partner_id) VALUES(?,?)',[$email,(string)$partner['id']]);
}
function manager_commission_record(mysqli $db,string $reference,string $email,float $amount): void {
    $stmt=app_query($db,'SELECT p.*,m.active AS manager_active FROM manager_referrals r JOIN manager_partners p ON p.id=r.partner_id JOIN manager_accounts m ON m.id=p.manager_id WHERE r.email=?',[$email]);
    $p=$stmt->get_result()->fetch_assoc();
    if(!$p || !(int)$p['manager_active']) return;
    $mp=(float)$p['manager_percent']; $ip=(float)$p['influencer_percent'];
    if($mp<0 || $ip<0 || abs($mp+$ip-MANAGER_BUDGET_PERCENT)>.0001) { error_log('Divisão de gerente inválida no convite '.$p['id']); return; }
    $ma=round($amount*$mp/100,2); $ia=round($amount*$ip/100,2);
    app_query($db,'INSERT INTO manager_commissions(reference,partner_id,manager_id,deposit_amount,manager_percent,influencer_percent,manager_amount,influencer_amount) VALUES(?,?,?,?,?,?,?,?)',[$reference,(string)$p['id'],(string)$p['manager_id'],(string)$amount,(string)$mp,(string)$ip,(string)$ma,(string)$ia]);
}
function manager_login(mysqli $db,string $email,string $password): bool {
    manager_install($db);
    $email=strtolower(trim($email));
    if(!app_auth_allowed($db,'manager',$email)) return false;
    $stmt=app_query($db,'SELECT id,password_hash,active FROM manager_accounts WHERE email=?',[$email]);
    $row=$stmt->get_result()->fetch_assoc();
    if(!$row || !(int)$row['active'] || !password_verify($password,$row['password_hash'])) { app_auth_failed($db,'manager',$email); return false; }
    app_auth_clear($db,'manager',$email);
    session_regenerate_id(true); $_SESSION['manager_id']=(int)$row['id']; return true;
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
    app_register($db,['email'=>$email,'senha'=>$password,'password_confirmation'=>$password,'telefone_confirmation'=>$phone],'','',$managerId);
}
function manager_demo_create_auto(mysqli $db,int $managerId): array {
    $suffix=bin2hex(random_bytes(5));
    $email='demo-'.$managerId.'-'.$suffix.'@subwayrun.demo';
    $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $password='SR!';
    for($i=0;$i<11;$i++) $password.=$alphabet[random_int(0,strlen($alphabet)-1)];
    manager_demo_create($db,$managerId,$email,$password);
    $name='Conta demo '.strtoupper(substr($suffix,-4));
    app_query($db,'UPDATE manager_demos SET display_name=? WHERE manager_id=? AND email=?',[$name,(string)$managerId,$email]);
    app_query($db,'UPDATE appconfig SET nome=? WHERE email=? AND demo=1',[$name,$email]);
    return ['name'=>$name,'email'=>$email,'password'=>$password];
}
function manager_demo_update(mysqli $db,int $managerId,string $email,string $name,string $balance,string $password=''): void {
    $email=strtolower(trim($email));
    $name=trim($name);
    if(strlen($name)<2||strlen($name)>120) throw new InvalidArgumentException('Informe um nome entre 2 e 120 caracteres.');
    if(!preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?$/D',$balance)) throw new InvalidArgumentException('Informe um saldo entre R$ 0,00 e R$ 999.999,99.');
    if($password!==''&&(strlen($password)<8||strlen($password)>72)) throw new InvalidArgumentException('A nova senha deve ter de 8 a 72 caracteres.');
    $newBalance=number_format((float)$balance,2,'.','');
    $db->begin_transaction();
    try {
        $row=app_query($db,'SELECT a.saldo FROM manager_demos d JOIN appconfig a ON a.email COLLATE utf8mb4_unicode_ci=d.email COLLATE utf8mb4_unicode_ci WHERE d.manager_id=? AND d.email=? AND a.demo=1 FOR UPDATE',[(string)$managerId,$email])->get_result()->fetch_assoc();
        if(!$row) throw new InvalidArgumentException('Conta demo não encontrada para este gerente.');
        app_query($db,'UPDATE manager_demos SET display_name=? WHERE manager_id=? AND email=?',[$name,(string)$managerId,$email]);
        app_query($db,'UPDATE appconfig SET nome=?,saldo=? WHERE email=? AND demo=1',[$name,$newBalance,$email]);
        if($password!=='') app_query($db,'UPDATE appconfig SET senha=? WHERE email=? AND demo=1',[password_hash($password,PASSWORD_DEFAULT),$email]);
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
