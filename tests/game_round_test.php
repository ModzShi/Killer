<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/game.php';
$db=app_db();
$db->query("CREATE TEMPORARY TABLE game_settings(id TINYINT PRIMARY KEY,difficulty VARCHAR(16) DEFAULT 'medio',meta_multiplier DECIMAL(8,2) DEFAULT 10,coin_value_demo DECIMAL(8,2) DEFAULT 0.11,coin_value_paid DECIMAL(8,2) DEFAULT 0.03,demo_coin_value_version TINYINT UNSIGNED DEFAULT 2) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE appconfig(id VARCHAR(255),email VARCHAR(255),saldo DECIMAL(12,2),total_apostado DECIMAL(12,2),ganhos DECIMAL(12,2),percas DECIMAL(12,2)) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE game_rounds(token CHAR(64) PRIMARY KEY,email VARCHAR(255),bet DECIMAL(12,2),max_payout DECIMAL(12,2),status VARCHAR(16) DEFAULT 'PLAYING',payout DECIMAL(12,2),created_at DATETIME DEFAULT CURRENT_TIMESTAMP,settled_at DATETIME NULL) ENGINE=InnoDB");
$db->query("INSERT INTO appconfig VALUES('1','test@example.test',10,0,0,0)");
function check_game(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS: $name\n";}
$token=game_start($db,'test@example.test','10BC');
$coin=$db->query("SELECT coin_value FROM game_rounds")->fetch_assoc();
check_game((float)$coin['coin_value']===0.03,'Paid coin value captured at round start');
$db->query('UPDATE game_settings SET coin_value_paid=0.07 WHERE id=1');
$coin=$db->query("SELECT coin_value FROM game_rounds")->fetch_assoc();
check_game((float)$coin['coin_value']===0.03,'Settings changes preserve an existing round');
$row=$db->query("SELECT * FROM appconfig")->fetch_assoc();
check_game((float)$row['saldo']===0.0&&(float)$row['total_apostado']===10.0,'Starting round reserves bet once');
$settled=game_settle($db,'test@example.test',$token,'WIN',999999);
check_game((float)$settled['payout']===100.0,'Payout matches configured target and is capped by server round');
game_settle($db,'test@example.test',$token,'WIN',999999);
$row=$db->query("SELECT * FROM appconfig")->fetch_assoc();
check_game((float)$row['saldo']===100.0&&(float)$row['ganhos']===100.0,'Repeated win cannot credit twice');
$token=game_start($db,'test@example.test','5BC');game_settle($db,'test@example.test',$token,'LOSS');game_settle($db,'test@example.test',$token,'LOSS');
$row=$db->query("SELECT * FROM appconfig")->fetch_assoc();
check_game((float)$row['saldo']===95.0&&(float)$row['percas']===5.0,'Repeated loss settles once');
foreach(['5BC'=>5.0,'10BC'=>10.0,'20BC'=>20.0,'30BC'=>30.0,'50BC'=>50.0,'100BC'=>100.0] as $code=>$expectedBet){
    $db->query('UPDATE appconfig SET saldo=1000');$roundToken=game_start($db,'test@example.test',$code);
    $round=app_query($db,'SELECT bet FROM game_rounds WHERE token=?',[$roundToken])->get_result()->fetch_assoc();
    check_game((float)$round['bet']===$expectedBet,'Stake preset '.$code.' is accepted');
    app_query($db,"UPDATE game_rounds SET status='LOSS' WHERE token=?",[$roundToken]);
}
try{game_start($db,'test@example.test','invalid');check_game(false,'Invalid bet rejected');}catch(InvalidArgumentException $e){check_game(true,'Invalid bet rejected');}
try{game_settle($db,'other@example.test',$token,'WIN',1);check_game(false,'Round ownership enforced');}catch(InvalidArgumentException $e){check_game(true,'Round ownership enforced');}
$_SESSION['demo_account']=true;
$demoToken=game_start($db,'test@example.test','5BC');
$demo=app_query($db,'SELECT coin_value FROM game_rounds WHERE token=?',[$demoToken])->get_result()->fetch_assoc();
check_game((float)$demo['coin_value']===0.11,'Demo accounts use the independent 11-cent value');
unset($_SESSION['demo_account']);
$db->close();
