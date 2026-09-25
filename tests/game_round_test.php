<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/game.php';
$db=app_db();
$db->query("CREATE TEMPORARY TABLE game_settings(id TINYINT PRIMARY KEY,difficulty VARCHAR(16) DEFAULT 'medio',meta_multiplier DECIMAL(8,2) DEFAULT 10,coin_value_demo DECIMAL(8,2) DEFAULT 0.35,coin_value_paid DECIMAL(8,2) DEFAULT 0.03) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE appconfig(id VARCHAR(255),email VARCHAR(255),saldo DECIMAL(12,2),total_apostado DECIMAL(12,2),ganhos DECIMAL(12,2),percas DECIMAL(12,2)) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE game_rounds(token CHAR(64) PRIMARY KEY,email VARCHAR(255),bet DECIMAL(12,2),max_payout DECIMAL(12,2),status VARCHAR(16) DEFAULT 'PLAYING',payout DECIMAL(12,2),created_at DATETIME DEFAULT CURRENT_TIMESTAMP,settled_at DATETIME NULL) ENGINE=InnoDB");
$db->query("INSERT INTO appconfig VALUES('1','test@example.test',10,0,0,0)");
function check_game(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS: $name\n";}
$token=game_start($db,'test@example.test','2BC');
$coin=$db->query("SELECT coin_value FROM game_rounds")->fetch_assoc();
check_game((float)$coin['coin_value']===0.03,'Paid coin value captured at round start');
$db->query('UPDATE game_settings SET coin_value_paid=0.07 WHERE id=1');
$coin=$db->query("SELECT coin_value FROM game_rounds")->fetch_assoc();
check_game((float)$coin['coin_value']===0.03,'Settings changes preserve an existing round');
$row=$db->query("SELECT * FROM appconfig")->fetch_assoc();
check_game((float)$row['saldo']===8.0&&(float)$row['total_apostado']===2.0,'Starting round reserves bet once');
$settled=game_settle($db,'test@example.test',$token,'WIN',999999);
check_game((float)$settled['payout']===20.0,'Payout matches configured target and is capped by server round');
game_settle($db,'test@example.test',$token,'WIN',999999);
$row=$db->query("SELECT * FROM appconfig")->fetch_assoc();
check_game((float)$row['saldo']===28.0&&(float)$row['ganhos']===20.0,'Repeated win cannot credit twice');
$token=game_start($db,'test@example.test','1BC');game_settle($db,'test@example.test',$token,'LOSS');game_settle($db,'test@example.test',$token,'LOSS');
$row=$db->query("SELECT * FROM appconfig")->fetch_assoc();
check_game((float)$row['saldo']===27.0&&(float)$row['percas']===1.0,'Repeated loss settles once');
try{game_start($db,'test@example.test','invalid');check_game(false,'Invalid bet rejected');}catch(InvalidArgumentException $e){check_game(true,'Invalid bet rejected');}
try{game_settle($db,'other@example.test',$token,'WIN',1);check_game(false,'Round ownership enforced');}catch(InvalidArgumentException $e){check_game(true,'Round ownership enforced');}
$_SESSION['demo_account']=true;
$demoToken=game_start($db,'test@example.test','1BC');
$demo=app_query($db,'SELECT coin_value FROM game_rounds WHERE token=?',[$demoToken])->get_result()->fetch_assoc();
check_game((float)$demo['coin_value']===0.35,'Demo accounts use the independent 35-cent value');
unset($_SESSION['demo_account']);
$db->close();
