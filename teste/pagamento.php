<?php
require dirname(__DIR__) . '/app/offline.php';
if(empty($_SESSION['email'])) { header('Location: '.app_url('login/')); exit; }
$db=app_db(); $reference=is_string($_GET['token']??null)?$_GET['token']:'';
$row=bxpay_row($db,$reference);
if(!$row || $row['email']!==$_SESSION['email'] || strpos($reference,'test_')!==0) { http_response_code(404); exit('Simulação não encontrada.'); }
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!app_check_csrf()) { http_response_code(403); exit('Formulário expirado.'); }
    if(app_input('action')==='approve') bxpay_credit($db,$reference,['external_id'=>$reference,'type'=>'DEPOSIT','status'=>'PAID','amount'=>$row['amount']]);
    elseif(app_input('action')==='reject') {
        $db->begin_transaction();
        app_query($db,"UPDATE bxpay_deposits SET status='REJECTED' WHERE reference=? AND status='PENDING'",[$reference]);
        if($db->affected_rows===1) app_query($db,"UPDATE confirmar_deposito SET status='REJECTED' WHERE externalreference=?",[$reference]);
        $db->commit();
    }
    header('Location: '.app_url('teste/pagamento.php?token='.urlencode($reference)),true,303); exit;
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pagamento simulado</title><link rel="stylesheet" href="<?= app_escape(app_url('adm/gateway/bxpay.css')) ?>"></head><body><main class="gateway-shell" style="max-width:650px"><a class="back-link" href="<?= app_escape(app_url('teste/')) ?>">← Central de testes</a><h1>PIX de demonstração</h1><section class="gateway-card"><h2>R$ <?= number_format((float)$row['amount'],2,',','.') ?></h2><p>Não existe uma cobrança real. Escolha o resultado para testar a atualização de saldo.</p>
<?php if($row['status']==='PENDING'): ?><form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><button name="action" value="approve">Simular pagamento aprovado</button><button class="secondary" name="action" value="reject">Simular pagamento recusado</button></form>
<?php else: ?><p class="notice"><?= $row['status']==='PAID_OUT'?'Pagamento simulado aprovado. Saldo fictício atualizado.':'Pagamento simulado recusado. Nenhum saldo foi adicionado.' ?></p><?php endif; ?>
<p><a class="back-link" href="<?= app_escape(app_url('painel/')) ?>">Ir para meu saldo →</a></p></section></main></body></html>
