<?php
require_once __DIR__.'/../app/account.php';
$recentPayouts=[];
try {
    $activityDb=app_db();
    $recentPayouts=$activityDb->query("SELECT valor,data FROM saques WHERE status IN ('Pago','PAID','PAID_OUT','Concluído','Concluido') AND CAST(valor AS DECIMAL(12,2))>0 ORDER BY STR_TO_DATE(data,'%d-%m-%Y %H:%i:%s') DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);
    $activityDb->close();
}catch(Throwable $e){error_log('recent payouts: '.$e->getMessage());}
?>
<section class="recent-payouts" aria-labelledby="recent-payouts-title"><div class="card-top"><div><p class="eyebrow">ATIVIDADE DA COMUNIDADE</p><h2 id="recent-payouts-title">Saques confirmados</h2></div><span class="icon-tile"><?= ui_icon('withdraw') ?></span></div><p>Últimos registros confirmados na plataforma. Identidades preservadas.</p><div class="account-card"><?php if(!$recentPayouts): ?><div class="empty-state"><?= ui_icon('wallet') ?><h3>O próximo registro pode aparecer aqui.</h3><p>Os saques confirmados serão exibidos nesta seção.</p></div><?php else: foreach($recentPayouts as $payout): ?><div class="activity-row"><span class="icon-tile small"><?= ui_icon('check') ?></span><div><strong>Jogador da comunidade</strong><small>Saque confirmado</small></div><strong class="row-end"><?= account_money($payout['valor']) ?></strong></div><?php endforeach; endif; ?></div></section>
