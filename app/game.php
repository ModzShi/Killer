<?php
require_once __DIR__ . '/auth.php';
function game_install(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS game_rounds (
        token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        email VARCHAR(255) NOT NULL, bet DECIMAL(12,2) NOT NULL,
        max_payout DECIMAL(12,2) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'PLAYING',
        payout DECIMAL(12,2) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        settled_at DATETIME NULL, INDEX(email,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS game_settings (
        id TINYINT UNSIGNED PRIMARY KEY,
        difficulty VARCHAR(16) NOT NULL DEFAULT 'medio',
        meta_multiplier DECIMAL(8,2) NOT NULL DEFAULT 10.00,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("INSERT IGNORE INTO game_settings (id,difficulty,meta_multiplier) VALUES (1,'medio',10.00)");
    foreach (['coin_value_demo'=>'0.11','coin_value_paid'=>'0.03'] as $column=>$default) {
        if (!$db->query("SHOW COLUMNS FROM game_settings LIKE '$column'")->num_rows) {
            $db->query("ALTER TABLE game_settings ADD $column DECIMAL(8,2) NOT NULL DEFAULT $default");
        }
    }
    if (!$db->query("SHOW COLUMNS FROM game_rounds LIKE 'coin_value'")->num_rows) {
        $db->query('ALTER TABLE game_rounds ADD coin_value DECIMAL(8,2) NOT NULL DEFAULT 0.03');
    }
    if (!$db->query("SHOW COLUMNS FROM game_settings LIKE 'demo_coin_value_version'")->num_rows) {
        $db->query('ALTER TABLE game_settings ADD demo_coin_value_version TINYINT UNSIGNED NOT NULL DEFAULT 0');
    }
    $db->query('UPDATE game_settings SET coin_value_demo=0.11,demo_coin_value_version=2 WHERE id=1 AND demo_coin_value_version<2');
}
function game_start(mysqli $db,string $email,string $code): string {
    $bets=['5BC'=>5.00,'10BC'=>10.00,'20BC'=>20.00,'30BC'=>30.00,'50BC'=>50.00,'100BC'=>100.00];
    if(!isset($bets[$code])) throw new InvalidArgumentException('Aposta inválida.');
    game_install($db); $bet=$bets[$code];
    $settings=$db->query('SELECT * FROM game_settings WHERE id=1')->fetch_assoc()?:[];
    $multiplier=max(1.0,min(100.0,(float)($settings['meta_multiplier']??10)));
    $max=round($bet*$multiplier,2); $token=bin2hex(random_bytes(32));
    $db->begin_transaction();
    try {
        $stmt=$db->prepare('UPDATE appconfig SET saldo=saldo-?,total_apostado=total_apostado+? WHERE email=? AND CAST(saldo AS DECIMAL(12,2))>=?');
        $stmt->bind_param('ddsd',$bet,$bet,$email,$bet);$stmt->execute();
        if($stmt->affected_rows!==1)throw new RuntimeException('Saldo insuficiente. Faça um depósito para jogar.');
        $stmt=$db->prepare('INSERT INTO game_rounds(token,email,bet,max_payout) VALUES(?,?,?,?)');
        $stmt->bind_param('ssdd',$token,$email,$bet,$max);$stmt->execute();
        $coinValue=(float)$settings[!empty($_SESSION['demo_account'])?'coin_value_demo':'coin_value_paid'];
        $stmt=$db->prepare('UPDATE game_rounds SET coin_value=? WHERE token=?');
        $stmt->bind_param('ds',$coinValue,$token);$stmt->execute();
        $db->commit();return $token;
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function game_settle(mysqli $db,string $email,string $token,string $result,float $requested=0): array {
    if(!in_array($result,['WIN','LOSS'],true))throw new InvalidArgumentException('Resultado inválido.');
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new InvalidArgumentException('Rodada inválida.');
    $db->begin_transaction();
    try {
        $stmt=$db->prepare('SELECT * FROM game_rounds WHERE token=? AND email=? FOR UPDATE');
        $stmt->bind_param('ss',$token,$email);$stmt->execute();$round=$stmt->get_result()->fetch_assoc();
        if(!$round)throw new InvalidArgumentException('Rodada não encontrada.');
        if($round['status']!=='PLAYING'){ $db->commit(); return $round; }
        if($result==='WIN'){
            if(!is_finite($requested)||$requested<(float)$round['max_payout']-.005)throw new InvalidArgumentException('A meta ainda não foi atingida.');
            $payout=min(round($requested,2),(float)$round['max_payout']);
            $stmt=$db->prepare('UPDATE appconfig SET saldo=saldo+?,ganhos=ganhos+? WHERE email=?');
            $stmt->bind_param('dds',$payout,$payout,$email);$stmt->execute();
        }else{
            $payout=0.0;$bet=(float)$round['bet'];
            $stmt=$db->prepare('UPDATE appconfig SET percas=percas+? WHERE email=?');
            $stmt->bind_param('ds',$bet,$email);$stmt->execute();
        }
        $stmt=$db->prepare('UPDATE game_rounds SET status=?,payout=?,settled_at=NOW() WHERE token=?');
        $stmt->bind_param('sds',$result,$payout,$token);$stmt->execute();
        $db->commit();$round['status']=$result;$round['payout']=$payout;return $round;
    }catch(Throwable $e){$db->rollback();throw $e;}
}
