<?php
require_once __DIR__.'/game.php';
require_once __DIR__.'/withdrawal.php';
require_once __DIR__.'/../components/icons.php';
function account_user(mysqli $db): array {
    if(empty($_SESSION['email'])) { header('Location: '.app_url('login/')); exit; }
    $user=app_query($db,'SELECT * FROM appconfig WHERE email=?',[$_SESSION['email']])->get_result()->fetch_assoc();
    if(!$user) { header('Location: '.app_url('login/')); exit; }
    return $user;
}
function account_money($value): string { return 'R$ '.number_format((float)($value??0),2,',','.'); }
function account_status(string $status): string {
    return ['PAID_OUT'=>'Confirmado','PAID'=>'Pago','WIN'=>'Vitória','LOSS'=>'Derrota','PLAYING'=>'Em andamento','PENDING'=>'Em análise','WAITING_FOR_APPROVAL'=>'Aguardando pagamento','EXPIRED'=>'Expirado','CANCELLED'=>'Cancelado'][$status]??$status;
}
function account_mask(string $email): string { preg_match('/^(.{0,2})/us',explode('@',$email)[0],$prefix);return ($prefix[1]??'').'•••'; }
