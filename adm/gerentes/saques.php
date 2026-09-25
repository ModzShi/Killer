<?php
require_once __DIR__ . '/../../app/manager.php';
if (empty($_SESSION['emailadm'])) { header('Location: ' . app_url('adm/login/')); exit; }
$db = app_db();
manager_install($db);
$notice = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!app_check_csrf()) throw new InvalidArgumentException('Formulário expirado.');
        $id = filter_var(app_input('id'), FILTER_VALIDATE_INT);
        $action = app_input('action');
        if (!$id || !in_array($action, ['paid', 'reject'], true)) throw new InvalidArgumentException('Solicitação inválida.');
        $db->begin_transaction();
        try {
            $request = app_query($db, 'SELECT id,status FROM manager_payout_requests WHERE id=? FOR UPDATE', [(string)$id])->get_result()->fetch_assoc();
            if (!$request || $request['status'] !== 'PENDING') throw new InvalidArgumentException('Solicitação não está pendente.');
            $status = $action === 'paid' ? 'PAID' : 'REJECTED';
            app_query($db, 'UPDATE manager_payout_requests SET status=?,processed_at=NOW(),processed_by=? WHERE id=? AND status=\'PENDING\'', [$status, (string)$_SESSION['emailadm'], (string)$id]);
            $db->commit();
            $notice = $status === 'PAID' ? 'Saque marcado como pago após conferência da transferência.' : 'Solicitação rejeitada. O valor voltou ao disponível do gerente.';
        } catch (Throwable $e) { $db->rollback(); throw $e; }
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log('manager payout admin: ' . $e->getMessage()); $error = 'Não foi possível atualizar a solicitação.'; }
}
$requests = $db->query('SELECT r.*,m.name,m.email FROM manager_payout_requests r JOIN manager_accounts m ON m.id=r.manager_id ORDER BY (r.status=\'PENDING\') DESC,r.created_at DESC LIMIT 100')->fetch_all(MYSQLI_ASSOC);
$money = static fn($value): string => 'R$ ' . number_format((float)$value, 2, ',', '.');
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saques de gerentes • Administração</title><link rel="stylesheet" href="<?= app_escape(app_url('gerente/manager.css')) ?>"></head><body><main class="shell"><header class="head"><div><span class="kicker">APROVAÇÃO MANUAL</span><h1>Saques de gerentes</h1><p>Confirme a transferência PIX antes de marcar uma solicitação como paga.</p></div><a class="link-pill" href="<?= app_escape(app_url('adm/gerentes/')) ?>">← Gerentes</a></header>
<?php if ($notice): ?><div class="notice success" role="status"><?= app_escape($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error" role="alert"><?= app_escape($error) ?></div><?php endif; ?>
<section class="panel wide"><h2>Solicitações</h2><?php if (!$requests): ?><p class="muted">Nenhuma solicitação recebida.</p><?php endif; ?><div class="table-wrap"><table><thead><tr><th>Gerente</th><th>Valor</th><th>Chave PIX</th><th>Status</th><th>Data</th><th>Ação</th></tr></thead><tbody><?php foreach ($requests as $request): ?><tr><td><?= app_escape($request['name']) ?><small style="display:block"><?= app_escape($request['email']) ?></small></td><td><?= $money($request['amount']) ?></td><td><?= app_escape($request['pix_key']) ?></td><td><?= app_escape($request['status']) ?></td><td><?= app_escape($request['created_at']) ?></td><td><?php if ($request['status'] === 'PENDING'): ?><div class="payout-actions"><form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="id" value="<?= (int)$request['id'] ?>"><button class="mini on" name="action" value="paid">Marcar pago</button></form><form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="id" value="<?= (int)$request['id'] ?>"><button class="mini off" name="action" value="reject">Rejeitar</button></form></div><?php else: ?><?= app_escape($request['processed_at'] ?? '—') ?><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section></main></body></html>
