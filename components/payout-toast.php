<?php
if(empty($menuLoggedIn))return;
$verifiedPayouts=[];
try{
    require_once __DIR__.'/../app/auth.php';
    $payoutDb=app_db();
    $verifiedPayouts=$payoutDb->query("SELECT destino,valor,data FROM saques WHERE status IN ('Pago','PAID','PAID_OUT','Concluído','Concluido') AND CAST(valor AS DECIMAL(12,2))>0 ORDER BY STR_TO_DATE(data,'%d-%m-%Y %H:%i:%s') DESC LIMIT 12")->fetch_all(MYSQLI_ASSOC);
    $payoutDb->close();
}catch(Throwable $error){error_log('verified payout toast: '.$error->getMessage());}
if(!$verifiedPayouts)return;
$maskPayoutName=static function($name):string{
    $name=trim(preg_replace('/\s+/u',' ',(string)$name));
    if($name==='')return 'Jogador';
    $parts=explode(' ',$name);
    $first=function_exists('mb_convert_case')?mb_convert_case($parts[0],MB_CASE_TITLE,'UTF-8'):ucfirst(strtolower($parts[0]));
    if(!isset($parts[1]))return $first;
    $last=$parts[count($parts)-1];$initial=function_exists('mb_substr')?mb_substr($last,0,1,'UTF-8'):substr($last,0,1);
    return $first.' '.strtoupper($initial).'.';
};
$toastItems=array_map(static fn($item):array=>['name'=>$maskPayoutName($item['destino']??''),'amount'=>'R$ '.number_format((float)$item['valor'],2,',','.')],$verifiedPayouts);
$first=$toastItems[0];
?>
<link rel="stylesheet" href="<?=$menuEscape($menuBase)?>arquivos/payout-toast.css?v=<?=filemtime(__DIR__.'/../arquivos/payout-toast.css')?>">
<aside class="payout-toast" data-payouts="<?=app_escape(json_encode($toastItems,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT))?>" aria-live="polite" aria-label="Saques confirmados recentemente">
    <span class="payout-toast__icon"><?=ui_icon('withdraw')?><i></i></span>
    <span class="payout-toast__copy"><small><i></i> SAQUE CONFIRMADO</small><strong data-payout-name><?=app_escape($first['name'])?></strong><span>acabou de sacar</span></span>
    <b data-payout-amount><?=app_escape($first['amount'])?></b>
</aside>
<script src="<?=$menuEscape($menuBase)?>arquivos/payout-toast.js?v=<?=filemtime(__DIR__.'/../arquivos/payout-toast.js')?>" defer></script>
