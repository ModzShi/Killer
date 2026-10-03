<?php
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../components/metrics.php';
if (empty($_SESSION['emailadm'])) { header('Location: '.app_url('adm/login/'), true, 303); exit; }
$db=app_db(); $stats=admin_metrics($db); $db->close();
$adminActive='ggr'; $adminTitle='Resultado das corridas';
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#101115"><title>GGR · Subway Run</title><link rel="stylesheet" href="<?= app_escape(app_url('gerente/manager-premium.css')) ?>?v=2"><link rel="stylesheet" href="<?= app_escape(app_url('adm/admin-shell.css')) ?>?v=<?= filemtime(dirname(__DIR__).'/admin-shell.css') ?>"></head><body class="admin-app">
<?php require dirname(__DIR__).'/components/panel-nav.php'; ?>
<div class="view-heading"><div><small>FINANCEIRO · JOGO</small><h1>GGR</h1><p>Resultado bruto das corridas concluídas com saldo real.</p></div><button class="btn outline" type="button" data-admin-refresh>Atualizar</button></div>
<section class="metric-grid" aria-label="Cálculo do GGR">
    <article class="metric-card blue-card"><small>ENTRADAS NAS CORRIDAS</small><strong><?= admin_money($stats['stakes']) ?></strong><span><?= $stats['rounds'] ?> partidas encerradas</span></article>
    <article class="metric-card warn-card"><small>PRÊMIOS CREDITADOS</small><strong><?= admin_money($stats['prizes']) ?></strong><span><?= $stats['wins'] ?> vitórias</span></article>
    <article class="metric-card green-card"><small>GGR · ENTRADAS − PRÊMIOS</small><strong><?= admin_money($stats['ggr']) ?></strong><span>Resultado bruto acumulado</span></article>
    <article class="metric-card"><small>CORRIDAS ENCERRADAS</small><strong><?= number_format($stats['rounds'],0,',','.') ?></strong><span><?= $stats['wins'] ?> vitórias · <?= $stats['losses'] ?> derrotas</span></article>
</section>
<section class="surface"><div class="surface-title"><div><small class="overline">ÚLTIMOS 7 DIAS</small><h2>Evolução do GGR</h2></div><span class="legend green-dot">Por data de encerramento</span></div><?= admin_trend_html($stats['ggr_trend'],'GGR por dia das corridas encerradas nos últimos sete dias') ?><p class="admin-ggr-note">GGR = soma das entradas das partidas encerradas − soma dos prêmios creditados nessas partidas. Corridas de treino e contas demo ficam fora deste cálculo. O valor pode ser negativo em um período e não representa saldo disponível na BX Pay.</p></section>
<section class="surface"><div class="admin-compact-head"><h2>Outros valores</h2><a class="text-action" href="<?= app_escape(app_url('adm/depositos/')) ?>">Ver depósitos →</a></div><div class="metric-grid two-metrics"><article class="metric-card"><small>DEPÓSITOS PAGOS</small><strong><?= admin_money($stats['deposit_amount']) ?></strong><span><?= $stats['deposits'] ?> confirmações</span></article><article class="metric-card"><small>SAQUES MARCADOS COMO PAGOS</small><strong><?= admin_money($stats['withdraw_amount']) ?></strong><span><?= $stats['withdrawals'] ?> solicitações</span></article></div></section>
</div></main><script src="<?= app_escape(app_url('adm/admin-shell.js')) ?>?v=<?= filemtime(dirname(__DIR__).'/admin-shell.js') ?>" defer></script></body></html>
