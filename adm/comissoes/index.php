<?php
require_once __DIR__ . '/../../app/manager.php';
require_once __DIR__ . '/../../app/withdrawal.php';
if (empty($_SESSION['emailadm'])) { header('Location: '.app_url('adm/login/')); exit; }
$db=app_db(); manager_install($db); withdrawal_install($db);
$tab=($_GET['tab']??'gerentes')==='usuarios'?'usuarios':'gerentes';
$notice=''; $error='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        if(!app_check_csrf())throw new InvalidArgumentException('Formulário expirado.');
        $id=filter_var(app_input('id'),FILTER_VALIDATE_INT);
        $kind=app_input('kind'); $action=app_input('action');
        if(!$id||!in_array($kind,['gerentes','usuarios'],true)||!in_array($action,['paid','reject'],true))throw new InvalidArgumentException('Solicitação inválida.');
        if($kind==='usuarios'&&$action==='reject')throw new InvalidArgumentException('Para rejeitar um saque de usuário, faça a devolução do saldo antes de alterar o status.');
        $db->begin_transaction();
        try{
            if($kind==='gerentes'){
                $request=app_query($db,'SELECT status FROM manager_payout_requests WHERE id=? FOR UPDATE',[(string)$id])->get_result()->fetch_assoc();
                if(!$request||$request['status']!=='PENDING')throw new InvalidArgumentException('Este saque já foi processado.');
                app_query($db,'UPDATE manager_payout_requests SET status=?,processed_at=NOW(),processed_by=? WHERE id=?',[$action==='paid'?'PAID':'REJECTED',(string)$_SESSION['emailadm'],(string)$id]);
            }else{
                $request=app_query($db,'SELECT status FROM saque_afiliado WHERE id=? FOR UPDATE',[(string)$id])->get_result()->fetch_assoc();
                if(!$request||$request['status']!=='Aguardando Aprovação')throw new InvalidArgumentException('Este saque já foi processado.');
                app_query($db,"UPDATE saque_afiliado SET status='Pago' WHERE id=?",[(string)$id]);
            }
            $db->commit(); $notice=$action==='paid'?'Saque marcado como pago. Confirme que o PIX foi enviado.':'Saque de gerente rejeitado.';
        }catch(Throwable $e){$db->rollback();throw $e;}
        $tab=$kind;
    }catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('admin commissions: '.$e->getMessage());$error='Não foi possível processar o saque.';}
}
$managers=$db->query("SELECT r.id,r.amount,r.pix_key,r.status,r.created_at,m.name,m.email FROM manager_payout_requests r JOIN manager_accounts m ON m.id=r.manager_id ORDER BY (r.status='PENDING') DESC,r.created_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$users=$db->query("SELECT id,email,nome,pix,valor,status FROM saque_afiliado ORDER BY (status='Aguardando Aprovação') DESC,id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$money=static fn($v):string=>'R$ '.number_format((float)$v,2,',','.');
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Saques de comissões · Subway Run</title><link rel="stylesheet" href="<?=app_escape(app_url('adm/usuarios/users.css'))?>?v=3"></head><body class="sk-admin"><main class="usr-shell"><header class="usr-head"><div><a class="usr-brand" href="<?=app_escape(app_url('adm/'))?>">SUBWAY <span>RUN</span></a><p>ADMINISTRAÇÃO</p><h1>Saques de comissões</h1><span>Solicitações de gerentes e usuários em abas separadas.</span></div><div class="usr-head-actions"><a href="<?=app_escape(app_url('adm/'))?>">← Painel</a></div></header>
<?php if($notice):?><div class="usr-notice" role="status"><?=app_escape($notice)?></div><?php endif;?><?php if($error):?><div class="usr-notice usr-notice--error" role="alert"><?=app_escape($error)?></div><?php endif;?>
<nav class="usr-filters" aria-label="Tipo de comissão"><a class="usr-tab <?=$tab==='gerentes'?'active':''?>" href="?tab=gerentes">Gerentes <b><?=count($managers)?></b></a><a class="usr-tab <?=$tab==='usuarios'?'active':''?>" href="?tab=usuarios">Usuários <b><?=count($users)?></b></a></nav>
<section class="usr-panel"><div class="usr-panel-head"><div><p>TRANSFERÊNCIA MANUAL</p><h2><?=$tab==='gerentes'?'Gerentes':'Usuários'?></h2></div></div><p class="usr-help">Marque como pago somente após enviar o PIX. O pedido permanece pendente até a sua confirmação.</p>
<?php $rows=$tab==='gerentes'?$managers:$users;if(!$rows):?><div class="usr-empty">Nenhuma solicitação encontrada.</div><?php else:?><div class="usr-table-wrap"><table><thead><tr><th>Pessoa</th><th>Valor</th><th>Chave PIX</th><th>Status</th><th>Ação</th></tr></thead><tbody><?php foreach($rows as $row):$pending=$tab==='gerentes'?$row['status']==='PENDING':$row['status']==='Aguardando Aprovação';?><tr><td><strong><?=app_escape($tab==='gerentes'?$row['name']:$row['nome'])?></strong><small class="usr-subline"><?=app_escape($row['email'])?></small></td><td class="usr-money"><?=$money($tab==='gerentes'?$row['amount']:$row['valor'])?></td><td class="usr-break"><?=app_escape($tab==='gerentes'?$row['pix_key']:$row['pix'])?></td><td><span class="usr-status <?=$pending?'':'blocked'?>"><?=app_escape($row['status'])?></span></td><td><?php if($pending):?><form method="post" class="usr-inline-form" onsubmit="return confirm('Confirma que o PIX já foi enviado para esta chave?')"><input type="hidden" name="csrf" value="<?=app_escape(app_csrf())?>"><input type="hidden" name="kind" value="<?=$tab?>"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button type="submit" name="action" value="paid" class="usr-edit">Marcar pago</button></form><?php if($tab==='gerentes'):?><form method="post" class="usr-inline-form" onsubmit="return confirm('Rejeitar esta solicitação de gerente?')"><input type="hidden" name="csrf" value="<?=app_escape(app_csrf())?>"><input type="hidden" name="kind" value="gerentes"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button type="submit" name="action" value="reject" class="usr-edit usr-danger">Rejeitar</button></form><?php endif;endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section></main></body></html>
