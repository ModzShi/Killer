<?php
$verifiedPayouts=[];
try {
    require_once __DIR__.'/../app/auth.php';
    $payoutDb=app_db();
    $verifiedPayouts=$payoutDb->query("SELECT destino,valor FROM saques WHERE status IN ('Pago','PAID','PAID_OUT','Concluído','Concluido') AND CAST(valor AS DECIMAL(12,2))>0 ORDER BY STR_TO_DATE(data,'%d-%m-%Y %H:%i:%s') DESC LIMIT 12")->fetch_all(MYSQLI_ASSOC);
    $payoutDb->close();
} catch(Throwable $error) { error_log('verified payout toast: '.$error->getMessage()); }
if(!$verifiedPayouts)return;
$maskPayoutName=static function($name):string {
    $name=trim(preg_replace('/\s+/u',' ',(string)$name));
    if($name==='')return 'Jogador';
    $parts=explode(' ',$name);
    $first=function_exists('mb_convert_case')?mb_convert_case($parts[0],MB_CASE_TITLE,'UTF-8'):ucfirst(strtolower($parts[0]));
    if(!isset($parts[1]))return $first;
    $last=$parts[count($parts)-1];$initial=function_exists('mb_substr')?mb_substr($last,0,1,'UTF-8'):substr($last,0,1);
    return $first.' '.strtoupper($initial).'.';
};
$toastItems=[];
foreach($verifiedPayouts as $payout){
    $amount=(float)($payout['valor']??0);
    if(!is_finite($amount)||$amount<=0)continue;
    $toastItems[]=['name'=>$maskPayoutName($payout['destino']??''),'amount'=>'R$ '.number_format($amount,2,',','.')];
}
if(!$toastItems)return;
$first=$toastItems[0];
?>
<link rel="stylesheet" href="<?=$menuEscape($menuBase)?>arquivos/payout-toast.css?v=<?=filemtime(__DIR__.'/../arquivos/payout-toast.css')?>">
<aside class="payout-toast" data-payouts="<?=app_escape(json_encode($toastItems,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT))?>" aria-live="polite" aria-label="Atividade de saque">
    <strong class="payout-toast__name" data-payout-name><?=app_escape($first['name'])?></strong>
    <b data-payout-amount><?=app_escape($first['amount'])?></b>
</aside>
<script src="<?=$menuEscape($menuBase)?>arquivos/payout-toast.js?v=<?=filemtime(__DIR__.'/../arquivos/payout-toast.js')?>" defer></script>
