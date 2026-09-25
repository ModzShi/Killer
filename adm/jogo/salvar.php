<?php
require_once __DIR__ . '/../../app/game.php';
if (empty($_SESSION['emailadm'])) { http_response_code(403); exit('Não autorizado'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !app_check_csrf()) { http_response_code(403); exit('Formulário expirado'); }
$difficulty = app_input('difficulty'); $multiplier = (float) app_input('meta_multiplier');
$demoValue=filter_var(app_input('coin_value_demo'),FILTER_VALIDATE_FLOAT);
$paidValue=filter_var(app_input('coin_value_paid'),FILTER_VALIDATE_FLOAT);
try {
    foreach ([$demoValue,$paidValue] as $value) if ($value===false || !is_finite($value) || $value<0.01 || $value>100 || abs($value*100-round($value*100))>0.00001) throw new InvalidArgumentException();
    if (!in_array($difficulty, ['facil','medio','dificil','impossivel'], true) || !is_finite($multiplier) || $multiplier < 1 || $multiplier > 100) throw new InvalidArgumentException();
    $db = app_db(); game_install($db); $stmt = $db->prepare('UPDATE game_settings SET difficulty=?,meta_multiplier=?,coin_value_demo=?,coin_value_paid=? WHERE id=1'); $stmt->bind_param('sddd', $difficulty, $multiplier, $demoValue, $paidValue); $stmt->execute(); $_SESSION['game_settings_notice'] = 'Configurações do jogo salvas.';
} catch (Throwable $e) { $_SESSION['game_settings_notice'] = 'Não foi possível salvar as configurações.'; }
header('Location: ' . app_url('adm/jogo/'), true, 303); exit;
