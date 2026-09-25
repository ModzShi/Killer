<?php
require_once __DIR__.'/account.php';
$db=app_db();$user=account_user($db);withdrawal_install($db);
$notice=$_SESSION['affiliate_notice']??'';unset($_SESSION['affiliate_notice']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    try {
        if(!app_check_csrf())throw new InvalidArgumentException('Formulário expirado. Recarregue a página.');
        $name=trim(app_input('withdrawName'));$pix=trim(app_input('withdrawCPF'));
        if(strlen($name)<2||strlen($name)>120||strlen($pix)<5||strlen($pix)>77)throw new InvalidArgumentException('Informe seu nome e uma chave PIX válida.');
        $db->begin_transaction();
        $locked=app_query($db,'SELECT demo,comissaofake FROM appconfig WHERE email=? FOR UPDATE',[$user['email']])->get_result()->fetch_assoc();
        if(!$locked||$locked['demo']==='1')throw new InvalidArgumentException('Contas demo não podem solicitar saques.');
        $amount=round((float)$locked['comissaofake'],2);
        if($amount<=0)throw new InvalidArgumentException('Nenhuma comissão disponível para sacar.');
        app_query($db,"INSERT INTO saque_afiliado(email,nome,pix,valor,status) VALUES(?,?,?,?,'Aguardando Aprovação')",[$user['email'],$name,$pix,(string)$amount]);
        app_query($db,'UPDATE appconfig SET comissaofake=0,saldo_comissao=0,saldo_cpa=0 WHERE email=?',[$user['email']]);
        $db->commit();$_SESSION['affiliate_notice']='Solicitação registrada. Sua comissão está reservada e aguarda aprovação.';
    }catch(Throwable $e){$db->rollback();$_SESSION['affiliate_notice']=$e instanceof InvalidArgumentException?$e->getMessage():'Não foi possível solicitar o saque. Tente novamente.';error_log('affiliate withdrawal: '.$e->getMessage());}
    header('Location: '.app_url('saque-afiliado/'),true,303);exit;
}
$nomeUnico=$db->query('SELECT nome_unico FROM app LIMIT 1')->fetch_assoc()['nome_unico']??'Subway Run';
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sacar comissão</title><link rel="stylesheet" href="<?= app_url('arquivos/account.css') ?>"></head><body><?php $menuBase='../';$menuLoggedIn=true;$menuCurrent='afiliate/';include __DIR__.'/../components/menu.php'; ?><main class="account-shell" style="max-width:680px"><header class="account-heading"><span class="icon-tile"><?= ui_icon('withdraw') ?></span><div><p class="eyebrow">SUA RECOMPENSA</p><h1>Sacar comissão</h1><p>Da sua rede para a sua carteira.</p></div></header><section class="account-card wallet-card"><small>SALDO DISPONÍVEL</small><strong class="balance"><?= account_money($user['comissaofake']) ?></strong><p>O valor integral será reservado para aprovação manual.</p></section><?php if($notice): ?><p class="account-notice" role="status"><?= app_escape($notice) ?></p><?php endif; ?><form class="account-card" method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><label for="withdrawName">Nome do titular</label><input id="withdrawName" name="withdrawName" autocomplete="name" required minlength="2" maxlength="120" value="<?= app_escape($user['nome']??'') ?>"><label for="withdrawCPF">Chave PIX do titular</label><input id="withdrawCPF" name="withdrawCPF" required minlength="5" maxlength="77" placeholder="CPF, e-mail, telefone ou chave aleatória"><button class="account-button" <?= $user['demo']==='1'||(float)$user['comissaofake']<=0?'disabled':'' ?>><?= ui_icon('withdraw') ?> Solicitar saque</button><small>Confira os dados. Você pode acompanhar o pedido no histórico do afiliado.</small></form><a class="account-button secondary" href="<?= app_url('afiliate/') ?>">Voltar à minha rede</a></main></body></html>
