<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/components/metrics.php';
if (empty($_SESSION['emailadm'])) { header('Location: '.app_url('adm/login/'), true, 303); exit; }
$db = app_db();
$stats = admin_metrics($db);
$db->close();
$notice = is_string($_SESSION['admin_notice'] ?? null) ? $_SESSION['admin_notice'] : '';
unset($_SESSION['admin_notice']);
$csrf = app_csrf();
$adminActive='dashboard'; $adminTitle='Painel de controle';
$settings = [
    ['depositoMin','Depósito mínimo','deposito_min'],
    ['saqueMin','Saque mínimo','saques_min'],
    ['apostaMin','Entrada mínima','aposta_min'],
    ['apostaMax','Entrada máxima','aposta_max'],
    ['rolloverSaque','Rollover do saque','rollover_saque'],
    ['taxaSaque','Taxa de saque','taxa_saque'],
];
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#101115"><title>Painel administrativo · Subway Run</title><link rel="stylesheet" href="<?= app_escape(app_url('gerente/manager-premium.css')) ?>?v=2"><link rel="stylesheet" href="<?= app_escape(app_url('adm/admin-shell.css')) ?>?v=<?= filemtime(__DIR__.'/admin-shell.css') ?>"></head><body class="admin-app">
<?php require __DIR__.'/components/panel-nav.php'; ?>
<?php if ($notice): ?><p class="admin-notice" role="status"><?= app_escape($notice) ?></p><?php endif; ?>
<div class="view-heading"><div><small>VISÃO GERAL</small><h1>Subway Run</h1><p>Depósitos confirmados, resultados das corridas e operações em um só lugar.</p></div><button class="btn outline" type="button" data-admin-refresh>Atualizar</button></div>
<section class="metric-grid" aria-label="Indicadores principais">
    <article class="metric-card green-card"><small>FATURAMENTO · DEPÓSITOS PAGOS</small><strong><?= admin_money($stats['deposit_amount']) ?></strong><a href="<?= app_escape(app_url('adm/depositos/')) ?>"><?= $stats['deposits'] ?> depósitos confirmados →</a></article>
    <article class="metric-card"><small>GGR · ENTRADAS MENOS PRÊMIOS</small><strong><?= admin_money($stats['ggr']) ?></strong><a href="<?= app_escape(app_url('adm/GGR/')) ?>">Ver cálculo →</a></article>
    <article class="metric-card blue-card"><small>USUÁRIOS REAIS</small><strong><?= number_format($stats['users'],0,',','.') ?></strong><a href="<?= app_escape(app_url('adm/usuarios/')) ?>">Gerenciar usuários →</a></article>
    <article class="metric-card warn-card"><small>PIX AGUARDANDO CONFIRMAÇÃO</small><strong><?= number_format($stats['pending_deposits'],0,',','.') ?></strong><a href="<?= app_escape(app_url('adm/depositos/')) ?>">Acompanhar depósitos →</a></article>
</section>
<section class="panel-grid" aria-label="Gráficos financeiros"><article class="surface"><div class="surface-title"><div><small class="overline">ÚLTIMOS 7 DIAS</small><h2>Faturamento confirmado</h2></div><span class="legend green-dot">PIX pago</span></div><?= admin_trend_html($stats['deposit_trend'],'Depósitos confirmados nos últimos sete dias') ?></article><article class="surface"><div class="surface-title"><div><small class="overline">ÚLTIMOS 7 DIAS</small><h2>GGR por dia</h2></div><span class="legend green-dot">Entradas − prêmios</span></div><?= admin_trend_html($stats['ggr_trend'],'GGR das rodadas encerradas nos últimos sete dias') ?></article></section>
<section class="surface"><div class="admin-compact-head"><h2>Acesso rápido</h2><span class="legend">Operações e ajustes</span></div><div class="admin-quick">
    <a href="<?= app_escape(app_url('adm/depositos/')) ?>"><?= ui_icon('deposit') ?> Depósitos <span>→</span></a>
    <a href="<?= app_escape(app_url('adm/saques/')) ?>"><?= ui_icon('withdraw') ?> Saques <span>→</span></a>
    <a href="<?= app_escape(app_url('adm/usuarios/')) ?>"><?= ui_icon('users') ?> Usuários <span>→</span></a>
    <a href="<?= app_escape(app_url('adm/jogo/')) ?>"><?= ui_icon('play') ?> Jogo e ganhos <span>→</span></a>
    <a href="<?= app_escape(app_url('adm/gerentes/')) ?>"><?= ui_icon('user') ?> Gerentes <span>→</span></a>
    <a href="<?= app_escape(app_url('adm/gateway/bxpay.php')) ?>"><?= ui_icon('shield') ?> Gateway BX Pay <span>→</span></a>
</div></section>
<section class="surface" id="ajustes"><div class="admin-compact-head"><h2>Configurações gerais</h2><span class="legend">Valores vigentes no sistema</span></div><div class="admin-config">
<?php foreach ($settings as [$option,$label,$column]): ?>
<form action="<?= app_escape(app_url('adm/processos.php?opcao='.$option)) ?>" method="post"><input type="hidden" name="csrf" value="<?= app_escape($csrf) ?>"><label><?= app_escape($label) ?><input type="number" name="valor" min="0" max="9999999999" step="0.01" value="<?= app_escape((string)($stats['app'][$column] ?? 0)) ?>" required></label><button type="submit">Salvar</button></form>
<?php endforeach; ?>
</div></section>
</div></main><script src="<?= app_escape(app_url('adm/admin-shell.js')) ?>?v=<?= filemtime(__DIR__.'/admin-shell.js') ?>" defer></script></body></html>
