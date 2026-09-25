<?php
require_once __DIR__ . '/../app/manager.php';
$db = app_db();
manager_install($db);
$manager = manager_current($db);
if (!$manager) { header('Location: ' . app_url('gerente/login.php')); exit; }

$notice = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!app_check_csrf()) throw new InvalidArgumentException('Formulário expirado. Recarregue a página.');
        $action = app_input('action');
        $managerId = (int)$manager['id'];
        if ($action === 'partner') {
            if (!is_numeric(app_input('manager_percent')) || !is_numeric(app_input('influencer_percent'))) throw new InvalidArgumentException('Informe os dois percentuais.');
            manager_partner_create($db, $managerId, app_input('name'), (float)app_input('manager_percent'), (float)app_input('influencer_percent'));
            $notice = 'Link exclusivo criado. Copie e envie ao influenciador.';
        } elseif ($action === 'update') {
            if (!is_numeric(app_input('manager_percent')) || !is_numeric(app_input('influencer_percent'))) throw new InvalidArgumentException('Informe os dois percentuais.');
            $id = (int)app_input('id');
            $mp = (float)app_input('manager_percent');
            $ip = (float)app_input('influencer_percent');
            if ($id < 1 || !is_finite($mp) || !is_finite($ip) || $mp < 0 || $ip < 0 || abs($mp + $ip - MANAGER_BUDGET_PERCENT) > .0001 || abs($mp - round($mp, 2)) > .0001 || abs($ip - round($ip, 2)) > .0001) {
                throw new InvalidArgumentException('Divida exatamente 70% entre gerente e influenciador.');
            }
            $found = app_query($db, 'SELECT id FROM manager_partners WHERE id=? AND manager_id=?', [(string)$id, (string)$managerId])->get_result()->fetch_assoc();
            if (!$found) throw new InvalidArgumentException('Convite não encontrado.');
            app_query($db, 'UPDATE manager_partners SET manager_percent=?,influencer_percent=? WHERE id=? AND manager_id=?', [(string)$mp, (string)$ip, (string)$id, (string)$managerId]);
            $notice = 'Divisão salva para os próximos depósitos. O histórico anterior permanece registrado.';
        } elseif ($action === 'toggle') {
            $id = (int)app_input('id');
            if ($id < 1) throw new InvalidArgumentException('Convite inválido.');
            $stmt = app_query($db, 'UPDATE manager_partners SET active=1-active WHERE id=? AND manager_id=?', [(string)$id, (string)$managerId]);
            if ($stmt->affected_rows !== 1) throw new InvalidArgumentException('Convite não encontrado.');
            $notice = 'Status do convite atualizado.';
        } elseif ($action === 'demo') {
            manager_demo_create($db, $managerId, app_input('email'), app_input('password'));
            $notice = 'Conta demo criada com R$ 1.000,00 fictícios. Envie a senha por um canal seguro.';
        } elseif ($action === 'demo_balance') {
            manager_demo_set_balance($db, $managerId, app_input('email'), app_input('balance'));
            $notice = 'Saldo fictício atualizado. A alteração foi registrada.';
        } elseif ($action === 'payout') {
            $nonce = app_input('nonce');
            if ($nonce === '' || empty($_SESSION['manager_payout_nonce']) || !hash_equals($_SESSION['manager_payout_nonce'], $nonce)) throw new InvalidArgumentException('Esta solicitação já foi enviada. Atualize a página.');
            unset($_SESSION['manager_payout_nonce']);
            manager_payout_request($db, $managerId, app_input('amount'), app_input('pix_key'));
            $notice = 'Saque solicitado. Aguarde a análise no painel administrativo.';
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }
    } catch (mysqli_sql_exception $e) {
        error_log('manager panel: ' . $e->getMessage());
        $error = $e->getCode() === 1062 ? 'Este e-mail já está cadastrado.' : 'Não foi possível salvar. Tente novamente.';
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('manager panel: ' . $e->getMessage());
        $error = 'Não foi possível concluir a operação.';
    }
}

$managerId = (string)$manager['id'];
$partners = app_query($db, 'SELECT p.*,(SELECT COUNT(*) FROM manager_referrals r WHERE r.partner_id=p.id) AS leads FROM manager_partners p WHERE p.manager_id=? ORDER BY p.id DESC', [$managerId])->get_result()->fetch_all(MYSQLI_ASSOC);
$demos = app_query($db, 'SELECT d.email,d.created_at,a.saldo FROM manager_demos d JOIN appconfig a ON a.email COLLATE utf8mb4_unicode_ci=d.email COLLATE utf8mb4_unicode_ci AND a.demo=1 WHERE d.manager_id=? ORDER BY d.created_at DESC LIMIT 100', [$managerId])->get_result()->fetch_all(MYSQLI_ASSOC);
$totals = app_query($db, 'SELECT COUNT(*) AS deposits,COALESCE(SUM(deposit_amount),0) AS volume,COALESCE(SUM(manager_amount),0) AS manager_total,COALESCE(SUM(influencer_amount),0) AS influencer_total FROM manager_commissions WHERE manager_id=?', [$managerId])->get_result()->fetch_assoc();
$recent = app_query($db, 'SELECT c.*,p.name FROM manager_commissions c JOIN manager_partners p ON p.id=c.partner_id WHERE c.manager_id=? ORDER BY c.created_at DESC LIMIT 20', [$managerId])->get_result()->fetch_all(MYSQLI_ASSOC);
$available = manager_payout_available($db, (int)$manager['id']);
$payouts = app_query($db, 'SELECT id,amount,status,created_at,processed_at FROM manager_payout_requests WHERE manager_id=? ORDER BY id DESC LIMIT 20', [$managerId])->get_result()->fetch_all(MYSQLI_ASSOC);
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
if (!preg_match('/^[a-zA-Z0-9.:-]+$/D', $host)) $host = 'localhost';
$origin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $host;
$money = static fn($value): string => 'R$ ' . number_format((float)$value, 2, ',', '.');
$_SESSION['manager_payout_nonce'] = bin2hex(random_bytes(24));
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gerente • Subway Run</title><link rel="stylesheet" href="<?= app_escape(app_url('gerente/manager.css')) ?>"><link rel="stylesheet" href="<?= app_escape(app_url('gerente/mobile.css')) ?>?v=1"></head>
<body><main class="shell">
<header class="head"><div><div class="logo">ϟ <span>SUBWAY</span> RUN</div><span class="kicker">PAINEL DE CONTROLE</span><h1>Olá, <?= app_escape($manager['name']) ?>.</h1><p>Convites, participações e contas de demonstração em um só lugar.</p></div><a class="link-pill" href="<?= app_escape(app_url('gerente/logout.php')) ?>">Sair ↗</a></header>
<?php if ($notice): ?><div class="notice success" role="status"><?= app_escape($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error" role="alert"><?= app_escape($error) ?></div><?php endif; ?>
<section class="stats">
  <div class="stat"><span>ORÇAMENTO POR CONVITE</span><strong>70%</strong><small>Gerente + influenciador</small></div>
  <div class="stat"><span>PARTE DA PLATAFORMA</span><strong>30%</strong><small>Reservada em cada divisão</small></div>
  <div class="stat"><span>LEADS CADASTRADOS</span><strong><?= array_sum(array_map(static fn($p) => (int)$p['leads'], $partners)) ?></strong><small>Via seus links</small></div>
  <div class="stat"><span>SUA PARTE CALCULADA</span><strong><?= $money($totals['manager_total']) ?></strong><small><?= (int)$totals['deposits'] ?> depósitos confirmados</small></div>
</section>
<section class="panel wide"><span class="kicker">SAQUE MANUAL</span><h2><?= ui_icon('wallet') ?> Suas comissões</h2><p class="muted">Disponível para solicitar: <strong><?= $money($available) ?></strong>. Solicitações ficam reservadas até aprovação ou rejeição. A transferência é conferida manualmente pelo administrador.</p>
  <form method="post" class="payout-form"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="nonce" value="<?= app_escape($_SESSION['manager_payout_nonce']) ?>"><input type="hidden" name="action" value="payout"><label>Valor (R$)<input name="amount" type="number" min="1" max="<?= app_escape($available) ?>" step="0.01" required></label><label>Sua chave PIX<input name="pix_key" maxlength="77" autocomplete="off" required></label><button class="primary" type="submit" <?= (float)$available < 1 ? 'disabled' : '' ?>>Solicitar saque →</button></form>
  <?php if ($payouts): ?><div class="table-wrap"><table><thead><tr><th>Solicitação</th><th>Valor</th><th>Status</th><th>Enviado em</th></tr></thead><tbody><?php foreach ($payouts as $payout): ?><tr><td>#<?= (int)$payout['id'] ?></td><td><?= $money($payout['amount']) ?></td><td><?= app_escape($payout['status']) ?></td><td><?= app_escape($payout['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<div class="grid">
  <section class="panel"><span class="kicker">01 • PARCERIAS</span><h2><?= ui_icon('users') ?> Novo link de influenciador</h2><p class="muted">Distribua os 70% entre você e o influenciador. Os 30% restantes ficam com a plataforma.</p>
    <form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="partner">
      <label>Nome do influenciador<input name="name" maxlength="120" required placeholder="Ex.: Maria Silva"></label>
      <div class="fields"><label>Gerente (%)<input name="manager_percent" type="number" min="0" max="70" step="0.01" value="35" required></label><label>Influenciador (%)<input name="influencer_percent" type="number" min="0" max="70" step="0.01" value="35" required></label></div>
      <div class="budget"><span>A distribuir dos 70%</span><strong id="budget-left">0%</strong></div><button class="primary">Criar link exclusivo →</button>
    </form>
  </section>
  <section class="panel"><span class="kicker">02 • DEMONSTRAÇÃO</span><h2><?= ui_icon('user') ?> Criar conta demo</h2><p class="muted">A conta recebe R$ 1.000,00 fictícios, joga no modo fácil e não pode depositar nem sacar.</p>
    <form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="demo"><label>E-mail da conta<input name="email" type="email" maxlength="254" required></label><label>Senha inicial<input name="password" type="password" minlength="8" maxlength="72" required></label><button class="primary">Criar acesso demo →</button></form>
  </section>
</div>
<section class="panel wide"><span class="kicker">03 • SALDOS FICTÍCIOS</span><h2><?= ui_icon('coins') ?> Contas demo</h2><p class="muted">Você pode alterar apenas o saldo das contas demo criadas por você. Cada alteração é registrada.</p>
  <?php if (!$demos): ?><p class="muted">Nenhuma conta demo criada ainda.</p><?php endif; ?>
  <div class="rows"><?php foreach ($demos as $demo): ?><div class="row demo-balance-row"><div><strong><?= app_escape($demo['email']) ?></strong><small>Saldo atual: <?= $money($demo['saldo']) ?></small></div><form method="post" class="demo-balance-form"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="demo_balance"><input type="hidden" name="email" value="<?= app_escape($demo['email']) ?>"><label>Novo saldo fictício (R$)<input name="balance" type="number" min="0" max="999999.99" step="0.01" value="<?= app_escape($demo['saldo']) ?>" required></label><button class="mini" type="submit">Salvar saldo</button></form></div><?php endforeach; ?></div>
</section>
<section class="panel wide"><span class="kicker">04 • CONVITES</span><h2><?= ui_icon('link') ?> Links e divisões</h2><?php if (!$partners): ?><p class="muted">Crie seu primeiro link para acompanhar os cadastros.</p><?php endif; ?>
  <div class="partner-list"><?php foreach ($partners as $partner): $link = $origin . app_url('cadastrar/?ref=' . $partner['code']); ?><article class="partner"><div class="partner-head"><div><h3><?= app_escape($partner['name']) ?></h3><span class="muted"><?= (int)$partner['leads'] ?> cadastro(s) • <?= $partner['active'] ? 'Link ativo' : 'Link pausado' ?></span></div><span class="tag"><?= app_escape($partner['code']) ?></span></div><div class="copy-line"><input value="<?= app_escape($link) ?>" readonly aria-label="Link de <?= app_escape($partner['name']) ?>"><button type="button" class="copy" data-copy="<?= app_escape($link) ?>">Copiar</button></div><div class="partner-controls"><form method="post" class="split-form"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$partner['id'] ?>"><label>Gerente %<input type="number" name="manager_percent" min="0" max="70" step="0.01" value="<?= app_escape($partner['manager_percent']) ?>" required></label><label>Influenciador %<input type="number" name="influencer_percent" min="0" max="70" step="0.01" value="<?= app_escape($partner['influencer_percent']) ?>" required></label><button class="mini">Salvar divisão</button></form><form method="post"><input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$partner['id'] ?>"><button class="mini <?= $partner['active'] ? 'off' : 'on' ?>"><?= $partner['active'] ? 'Pausar link' : 'Ativar link' ?></button></form></div></article><?php endforeach; ?></div>
</section>
<section class="panel wide"><span class="kicker">05 • HISTÓRICO</span><h2><?= ui_icon('history') ?> Participações calculadas</h2><p class="muted">Valores registrados após a confirmação de depósitos. Não há transferência automática.</p><?php if (!$recent): ?><p class="muted">Nenhum depósito atribuído ainda.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Influenciador</th><th>Depósito</th><th>Gerente</th><th>Influenciador</th><th>Plataforma</th><th>Data</th></tr></thead><tbody><?php foreach ($recent as $entry): ?><tr><td><?= app_escape($entry['name']) ?></td><td><?= $money($entry['deposit_amount']) ?></td><td><?= $money($entry['manager_amount']) ?> <small>(<?= app_escape($entry['manager_percent']) ?>%)</small></td><td><?= $money($entry['influencer_amount']) ?> <small>(<?= app_escape($entry['influencer_percent']) ?>%)</small></td><td><?= $money((float)$entry['deposit_amount'] - (float)$entry['manager_amount'] - (float)$entry['influencer_amount']) ?></td><td><?= app_escape($entry['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<p class="footer">Subway Run • Painel do gerente</p></main><script src="<?= app_escape(app_url('gerente/manager.js')) ?>" defer></script></body></html>
