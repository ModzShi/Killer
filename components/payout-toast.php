<?php
$toastNames=['Ana C.','Lucas M.','Alice R.','Bruno S.','Camila F.','João P.','Helena A.','Rafaela L.','Igor T.','Mariana S.','Felipe G.','Beatriz M.','Caio R.','Julia F.','Mateus A.','Clara P.','Thiago L.','Eduarda C.','Gabriel D.','Valentina B.'];
?>
<link rel="stylesheet" href="<?=$menuEscape($menuBase)?>arquivos/payout-toast.css?v=<?=filemtime(__DIR__.'/../arquivos/payout-toast.css')?>">
<aside class="payout-toast payout-toast--demo" data-names="<?=app_escape(json_encode($toastNames,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT))?>" aria-live="polite" aria-label="Notificações de exemplo, simuladas">
    <span class="payout-toast__badge">ACABOU DE SACAR</span>
    <strong class="payout-toast__name" data-payout-name>Ana C.</strong>
    <b data-payout-amount>R$ 184,50</b>
</aside>
<script src="<?=$menuEscape($menuBase)?>arquivos/payout-toast.js?v=<?=filemtime(__DIR__.'/../arquivos/payout-toast.js')?>" defer></script>
