<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/trader.php';
header('Cache-Control: private, no-store');
$base = app_url('modo-trader/');
$logged = !empty($_SESSION['email']);
$tab = is_string($_GET['tab'] ?? null) ? $_GET['tab'] : 'home';
if (!in_array($tab, ['home','trade','history','account'], true)) $tab = 'home';
$error = '';
$flash = (string) ($_SESSION['trader_flash'] ?? '');
unset($_SESSION['trader_flash']);
$user = null; $account = ['balance_cents' => 0]; $rounds = []; $open = null; $stats = ['total' => 0, 'wins' => 0];
if ($logged) {
    try {
        $db = app_db();
        $user = app_query($db, 'SELECT id,nome,email FROM appconfig WHERE email=? LIMIT 1', [(string) $_SESSION['email']])->get_result()->fetch_assoc();
        if (!$user) { $logged = false; }
        else {
            $playerId = (int) $user['id'];
            trader_install($db);
            $account = trader_profile($db, $playerId);
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                if (!app_check_csrf()) { http_response_code(403); $error = 'Formulário expirado. Recarregue a página.'; }
                else {
                    try {
                        $action = app_input('action');
                        if ($action === 'start') trader_start($db, $playerId, trim(app_input('amount')), app_input('direction'));
                        elseif ($action === 'reset') trader_reset($db, $playerId);
                        else throw new InvalidArgumentException('Ação inválida.');
                        $_SESSION['trader_flash'] = $action === 'reset' ? 'Créditos de treino restaurados.' : 'Operação iniciada. Aguarde o resultado.';
                        header('Location: ' . $base . '?tab=' . ($action === 'start' ? 'trade' : 'account'), true, 303); exit;
                    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
                }
            }
            $account = trader_profile($db, $playerId);
            $rounds = app_query($db, 'SELECT id,stake_cents,payout_cents,direction,status,created_at,ends_at FROM trader_rounds WHERE player_id=? ORDER BY id DESC LIMIT 30', [(string) $playerId])->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($rounds as $round) if ($round['status'] === 'OPEN') { $open = $round; break; }
            $stats = app_query($db, "SELECT COUNT(*) AS total,COALESCE(SUM(status='WIN'),0) AS wins FROM trader_rounds WHERE player_id=?", [(string) $playerId])->get_result()->fetch_assoc() ?: $stats;
        }
        $db->close();
    } catch (Throwable $exception) {
        error_log('Trader mode: ' . $exception->getMessage());
        http_response_code(503); $logged = false; $error = 'O Modo Trader está temporariamente indisponível. Tente novamente em instantes.';
    }
}
$firstName = $user ? (preg_split('/\s+/u', trim((string) $user['nome']))[0] ?? 'Jogador') : 'Visitante';
$esc = static fn($value): string => app_escape($value);
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#0b1518"><meta name="robots" content="noindex,nofollow">
  <title>Modo Trader · Subway Run</title>
  <link rel="stylesheet" href="<?= $esc($base) ?>trader.css?v=<?= filemtime(__DIR__ . '/trader.css') ?>">
</head>
<body>
<div class="trader-app">
  <header class="topbar"><a class="brand" href="<?= $esc($base) ?>"><span class="brand-icon">↗</span><span>Subway <b>Trader</b><small>MODO DE TREINO</small></span></a><a class="back" href="<?= $esc(app_url($logged ? 'painel/' : '')) ?>">Voltar ao Run <span aria-hidden="true">↗</span></a></header>
  <main class="content">
  <?php if ($error): ?><div class="message error" role="alert"><?= $esc($error) ?></div><?php endif; ?>
  <?php if ($flash): ?><div class="message success" role="status"><?= $esc($flash) ?></div><?php endif; ?>
  <?php if (!$logged): ?>
    <section class="guest-hero">
      <span class="eyebrow"><i></i> NOVO MODO · TRADER</span>
      <h1>Descubra o ritmo<br><em>do mercado.</em></h1>
      <p>Entre com sua conta Subway Run para explorar o painel, acompanhar o gráfico e praticar operações com créditos de treino.</p>
      <div class="guest-actions"><a class="primary-button" href="<?= $esc(app_url('login/?next=trader')) ?>">Entrar no Modo Trader <span>→</span></a><a class="secondary-button" href="<?= $esc(app_url('cadastrar/?next=trader')) ?>">Criar conta</a></div>
      <div class="guest-preview"><div><small>CARTEIRA DE TREINO</small><strong>R$ 1.000,00</strong><span>Créditos para praticar</span></div><div class="preview-graph" aria-hidden="true"><svg viewBox="0 0 400 150" preserveAspectRatio="none"><defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#13daac" stop-opacity=".4"/><stop offset="1" stop-color="#13daac" stop-opacity="0"/></linearGradient></defs><path d="M0 128 L38 109 L74 119 L104 75 L138 90 L170 49 L203 70 L245 40 L271 63 L310 24 L353 45 L400 7 L400 150 L0 150Z" fill="url(#g)"/><path d="M0 128 L38 109 L74 119 L104 75 L138 90 L170 49 L203 70 L245 40 L271 63 L310 24 L353 45 L400 7" fill="none" stroke="#20e2af" stroke-width="4"/></svg></div></div>
    </section>
  <?php else: ?>
    <?php if ($tab === 'home'): ?>
    <section class="welcome"><span class="eyebrow"><i></i> PAINEL TRADER</span><h1>Olá, <?= $esc($firstName) ?>.</h1><p>Acompanhe seu treino e escolha o próximo movimento.</p></section>
    <section class="wallet-card"><div><span class="card-label">CARTEIRA DE TREINO</span><strong><?= trader_money((int) $account['balance_cents']) ?></strong><small>Créditos simulados · sem efeito no saldo Subway Run</small></div><span class="wallet-symbol">↗</span></section>
    <div class="quick-grid"><a href="<?= $esc($base) ?>?tab=trade"><span class="quick-icon up">↗</span><strong>Operar</strong><small>Abrir gráfico</small></a><a href="<?= $esc($base) ?>?tab=history"><span class="quick-icon">◷</span><strong>Registros</strong><small>Ver histórico</small></a></div>
    <section class="section-card"><div class="section-heading"><div><span>VISÃO GERAL</span><h2>Seu desempenho</h2></div></div><div class="stats"><div><small>OPERAÇÕES</small><strong><?= (int) $stats['total'] ?></strong></div><div><small>ACERTOS</small><strong><?= (int) $stats['wins'] ?></strong></div></div></section>
    <section class="section-card"><div class="section-heading"><div><span>ÚLTIMA ATIVIDADE</span><h2>Operações recentes</h2></div><a href="<?= $esc($base) ?>?tab=history">Ver todas →</a></div><?php if (!$rounds): ?><p class="empty">Sua primeira operação de treino aparecerá aqui.</p><?php else: ?><?php foreach (array_slice($rounds, 0, 3) as $round): ?><div class="round-row"><span class="round-icon <?= $round['direction'] === 'UP' ? 'up' : 'down' ?>"><?= $round['direction'] === 'UP' ? '↗' : '↘' ?></span><span><strong><?= $round['direction'] === 'UP' ? 'Alta' : 'Baixa' ?></strong><small><?= $esc(date('d/m · H:i', strtotime((string) $round['created_at']))) ?></small></span><span class="round-value"><strong><?= trader_money((int) $round['stake_cents']) ?></strong><small><?= $round['status'] === 'OPEN' ? 'Em andamento' : ($round['status'] === 'WIN' ? 'Acerto' : 'Não acertou') ?></small></span></div><?php endforeach; ?><?php endif; ?></section>
    <?php elseif ($tab === 'trade'): ?>
    <section class="welcome compact"><span class="eyebrow"><i></i> ÁREA DE OPERAÇÃO</span><h1>Hora de operar.</h1><p>Escolha alta ou baixa e acompanhe a rodada de treino.</p></section>
    <div class="trade-balance"><span>Créditos disponíveis</span><strong><?= trader_money((int) $account['balance_cents']) ?></strong></div>
    <section class="chart-card"><div class="chart-head"><div><span class="coin-symbol">₿</span><div><strong>BTC / USDT</strong><small>Gráfico de referência</small></div></div><span class="market-dot">MERCADO</span></div><div id="trader-chart" role="img" aria-label="Gráfico de referência BTC/USDT"><div class="chart-fallback">Carregando gráfico…</div></div><p class="chart-note">O gráfico é uma referência visual. O resultado desta rodada de treino é simulado e independente da cotação.</p></section>
    <section class="operation-card"><div class="section-heading"><div><span>OPERAÇÃO DE TREINO</span><h2>Escolha seu movimento</h2></div></div>
      <?php if ($open): ?><div class="pending"><span class="pending-pulse"></span><div><strong>Operação em andamento</strong><p>Resultado disponível em <b data-remaining="<?= max(0, strtotime((string) $open['ends_at']) - time()) ?>">15</b>s.</p></div></div><p class="hint">A tela atualiza automaticamente ao fim da rodada.</p>
      <?php else: ?><form method="post" action="<?= $esc($base) ?>?tab=trade"><input type="hidden" name="csrf" value="<?= $esc(app_csrf()) ?>"><input type="hidden" name="action" value="start"><label for="trade-amount">Créditos de treino</label><div class="amount-field"><span>R$</span><input id="trade-amount" name="amount" type="text" inputmode="decimal" pattern="[0-9]{1,5}([.,][0-9]{1,2})?" value="10,00" required></div><div class="amount-shortcuts"><button type="button" data-amount="5,00">R$ 5</button><button type="button" data-amount="10,00">R$ 10</button><button type="button" data-amount="50,00">R$ 50</button></div><p class="hint">Duração: 15 segundos · Retorno em caso de acerto: 1,8× os créditos usados.</p><div class="direction-actions"><button class="trade-up" type="submit" name="direction" value="UP"><span>↗</span> Alta</button><button class="trade-down" type="submit" name="direction" value="DOWN"><span>↘</span> Baixa</button></div></form><?php endif; ?>
    </section>
    <?php if ($rounds && $rounds[0]['status'] !== 'OPEN'): $last = $rounds[0]; ?><div class="last-result <?= $last['status'] === 'WIN' ? 'won' : 'lost' ?>"><span><?= $last['status'] === 'WIN' ? '✦' : '◇' ?></span><div><small>ÚLTIMA OPERAÇÃO</small><strong><?= $last['status'] === 'WIN' ? 'Você acertou a direção' : 'Tente outro movimento' ?></strong><p><?= $last['status'] === 'WIN' ? '+' . trader_money((int) $last['payout_cents']) : '−' . trader_money((int) $last['stake_cents']) ?> em créditos de treino</p></div></div><?php endif; ?>
    <?php elseif ($tab === 'history'): ?>
    <section class="welcome compact"><span class="eyebrow"><i></i> SEUS REGISTROS</span><h1>Histórico.</h1><p>Veja os movimentos feitos no Modo Trader.</p></section>
    <section class="section-card history-card"><div class="section-heading"><div><span>ÚLTIMAS 30</span><h2>Operações</h2></div></div><?php if (!$rounds): ?><p class="empty">Nenhuma operação de treino ainda.</p><?php else: ?><?php foreach ($rounds as $round): ?><div class="round-row"><span class="round-icon <?= $round['direction'] === 'UP' ? 'up' : 'down' ?>"><?= $round['direction'] === 'UP' ? '↗' : '↘' ?></span><span><strong><?= $round['direction'] === 'UP' ? 'Alta' : 'Baixa' ?></strong><small><?= $esc(date('d/m/Y H:i', strtotime((string) $round['created_at']))) ?></small></span><span class="round-value"><strong><?= trader_money((int) $round['stake_cents']) ?></strong><small><?= $round['status'] === 'OPEN' ? 'Em andamento' : ($round['status'] === 'WIN' ? 'Acerto · +' . trader_money((int) $round['payout_cents']) : 'Não acertou') ?></small></span></div><?php endforeach; ?><?php endif; ?></section>
    <?php else: ?>
    <section class="welcome compact"><span class="eyebrow"><i></i> SUA CONTA</span><h1>Perfil Trader.</h1><p>Seu acesso está conectado ao Subway Run.</p></section>
    <section class="section-card profile-card"><div class="avatar"><?= $esc(mb_strtoupper(mb_substr($firstName, 0, 1))) ?></div><div><strong><?= $esc((string) $user['nome']) ?></strong><small><?= $esc((string) $user['email']) ?></small></div></section>
    <section class="wallet-card small-wallet"><div><span class="card-label">CRÉDITOS DE TREINO</span><strong><?= trader_money((int) $account['balance_cents']) ?></strong><small>Carteira separada do saldo principal</small></div></section>
    <section class="section-card"><div class="section-heading"><div><span>CONTROLES</span><h2>Minha prática</h2></div></div><p class="support-copy">Você pode restaurar seus créditos de treino a qualquer momento. O histórico das operações permanece salvo.</p><form method="post" action="<?= $esc($base) ?>?tab=account"><input type="hidden" name="csrf" value="<?= $esc(app_csrf()) ?>"><input type="hidden" name="action" value="reset"><button type="submit" class="secondary-button reset-button">Restaurar R$ 1.000,00 de treino</button></form><a class="profile-link" href="<?= $esc(app_url('perfil/')) ?>">Editar dados da conta Subway Run <span>→</span></a></section>
    <?php endif; ?>
  <?php endif; ?>
  </main>
  <?php if ($logged): ?><nav class="bottom-nav" aria-label="Navegação Trader"><a <?= $tab === 'home' ? 'aria-current="page"' : '' ?> href="<?= $esc($base) ?>"><span>⌂</span>Início</a><a <?= $tab === 'trade' ? 'aria-current="page"' : '' ?> href="<?= $esc($base) ?>?tab=trade"><span>↗</span>Operar</a><a <?= $tab === 'history' ? 'aria-current="page"' : '' ?> href="<?= $esc($base) ?>?tab=history"><span>◷</span>Registros</a><a <?= $tab === 'account' ? 'aria-current="page"' : '' ?> href="<?= $esc($base) ?>?tab=account"><span>♙</span>Conta</a></nav><?php endif; ?>
</div>
<script src="<?= $esc($base) ?>trader.js?v=<?= filemtime(__DIR__ . '/trader.js') ?>" defer></script>
</body></html>
