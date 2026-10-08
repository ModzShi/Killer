<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/consulta.php';
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');

$logged = !empty($_SESSION['email']);
$base = app_url('modo-consulta/');
$catalog = consulta_catalog();
$modules = [
    'pessoas' => ['title'=>'Identificação', 'subtitle'=>'Dados de pessoa', 'category'=>'Pessoas', 'icon'=>'face', 'accent'=>'violet', 'fields'=>['CPF','Nome','Telefone','E-mail','RG'], 'detail'=>'Localize o tipo de consulta adequado para identificação cadastral.'],
    'contatos' => ['title'=>'Contatos', 'subtitle'=>'Telefones e e-mails', 'category'=>'Pessoas', 'icon'=>'phone', 'accent'=>'cyan', 'fields'=>['Telefone','CPF','Nome'], 'detail'=>'Consulte vínculos de contato quando houver autorização do titular.'],
    'restricoes' => ['title'=>'Situação cadastral', 'subtitle'=>'Análise restritiva', 'category'=>'Pessoas', 'icon'=>'shield', 'accent'=>'orange', 'fields'=>['CPF','CNPJ','Nome'], 'detail'=>'Organize consultas de situação cadastral e restrições.'],
    'veiculos' => ['title'=>'Veículos', 'subtitle'=>'Placa e registro', 'category'=>'Veículos', 'icon'=>'car', 'accent'=>'blue', 'fields'=>['Placa','Chassi','Renavam'], 'detail'=>'Reúna consultas veiculares em uma única área.'],
    'empresas' => ['title'=>'Empresas', 'subtitle'=>'Cadastro empresarial', 'category'=>'Empresas', 'icon'=>'building', 'accent'=>'gold', 'fields'=>['CNPJ','Nome'], 'detail'=>'Encontre consultas relacionadas a empresas e cadastros.'],
    'enderecos' => ['title'=>'Endereços', 'subtitle'=>'Busca por CEP', 'category'=>'Endereços', 'icon'=>'pin', 'accent'=>'green', 'fields'=>['CEP'], 'detail'=>'Acesse informações de endereço pelo CEP.'],
    'fotos' => ['title'=>'Fotos de cadastro', 'subtitle'=>'Imagem por identificador', 'category'=>'Imagens', 'icon'=>'camera', 'accent'=>'pink', 'fields'=>['Identificador'], 'detail'=>'Visualização de foto de cadastro, quando a origem permitir. Não faz reconhecimento facial.'],
    'trabalho' => ['title'=>'Trabalho', 'subtitle'=>'Registros informados', 'category'=>'Outros', 'icon'=>'briefcase', 'accent'=>'teal', 'fields'=>['CPF'], 'detail'=>'Centralize consultas de registros trabalhistas autorizados.'],
    'cadastros' => ['title'=>'Cadastros', 'subtitle'=>'Bases informadas', 'category'=>'Outros', 'icon'=>'grid', 'accent'=>'violet', 'fields'=>['CPF','Nome','Telefone'], 'detail'=>'Pesquise os cadastros disponíveis na API.'],
    'compras' => ['title'=>'Compras', 'subtitle'=>'Histórico informado', 'category'=>'Outros', 'icon'=>'briefcase', 'accent'=>'gold', 'fields'=>['Documento','Telefone'], 'detail'=>'Consulte os dados de compras retornados pela API.'],
];
$selected = is_string($_GET['modulo'] ?? null) ? $_GET['modulo'] : '';
if (!isset($modules[$selected])) $selected = '';

function consulta_icon(string $name): string {
    $paths = [
        'face'=>'<rect x="4" y="3" width="16" height="18" rx="5"/><path d="M9 10h.01M15 10h.01M9 15c1.8 1.8 4.2 1.8 6 0M3 8V5a2 2 0 0 1 2-2h3m8 0h3a2 2 0 0 1 2 2v3M3 16v3a2 2 0 0 0 2 2h3m8 0h3a2 2 0 0 0 2-2v-3"/>',
        'fingerprint'=>'<path d="M4 11a8 8 0 0 1 16 0M6 15v-4a6 6 0 0 1 12 0v4M9 20c1.7-2.2 2-4.7 2-8a1 1 0 1 1 2 0c0 5-1 8-2 10m5-12v3c0 3-.6 5.7-2 8M5 18c-.6 1.4-1.2 2.3-2 3"/>',
        'phone'=>'<path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm3 15h4M8 6h8"/>',
        'shield'=>'<path d="m12 2 8 4v6c0 5-3.6 8.1-8 10-4.4-1.9-8-5-8-10V6l8-4Z"/><path d="m8 12 2.5 2.5L16 9"/>',
        'car'=>'<path d="m5 15 1-5 2-3h8l2 3 1 5M4 15h16v4H4v-4ZM7 19v2m10-2v2M6 12h12M7 16h.01M17 16h.01"/>',
        'building'=>'<path d="M4 21V5l8-3 8 3v16M3 21h18M8 8h1m6 0h1M8 12h1m6 0h1M8 16h1m6 0h1m-4 5v-4h2v4"/>',
        'pin'=>'<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'camera'=>'<path d="M3 7h4l2-3h6l2 3h4v13H3V7Z"/><circle cx="12" cy="13" r="4"/><path d="M7 11v-1m0 6v-1m10-4v-1m0 6v-1"/>',
        'briefcase'=>'<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13c4 2 14 2 18 0m-9 0v3"/>',
        'search'=>'<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
        'arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'lock'=>'<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'grid'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['grid']).'</svg>';
}
$e = static fn($value): string => app_escape($value);
$lower = static fn(string $value): string => function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
$upper = static fn(string $value): string => function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#0a0e1e"><meta name="robots" content="noindex,nofollow">
    <title>Modo Consulta · Subway Run</title>
    <link rel="stylesheet" href="<?= $e($base) ?>consulta.css?v=<?= filemtime(__DIR__ . '/consulta.css') ?>">
</head>
<body>
<div class="consulta-shell">
    <header class="consulta-topbar">
        <a class="consulta-brand" href="<?= $e($base) ?>"><span class="consulta-brand-mark"><?= consulta_icon('fingerprint') ?></span><span>Subway <strong>Consulta</strong><small>IDENTIFICAÇÃO · ANÁLISE · ORGANIZAÇÃO</small></span></a>
        <div class="consulta-topbar-actions"><span class="consulta-status"><i></i> Central de consultas</span><a class="consulta-back" href="<?= $e(app_url($logged ? 'painel/' : '')) ?>">Voltar ao Run <b>↗</b></a></div>
    </header>
    <?php if (!$logged): ?>
    <main class="consulta-guest">
        <div class="consulta-guest-copy"><span class="consulta-kicker"><i></i> NOVO MODO · CONSULTA</span><h1>Informação organizada.<br><em>Decisões mais claras.</em></h1><p>Um espaço para reunir módulos de identificação, contatos, veículos e empresas. Entre na sua conta para explorar o painel.</p><div class="consulta-guest-actions"><a class="consulta-primary" href="<?= $e(app_url('login/?next=consulta')) ?>">Entrar no painel <?= consulta_icon('arrow') ?></a><a class="consulta-ghost" href="<?= $e(app_url('cadastrar/?next=consulta')) ?>">Criar conta</a></div></div>
        <div class="consulta-guest-art" aria-hidden="true"><div class="consulta-orbit consulta-orbit-a"></div><div class="consulta-orbit consulta-orbit-b"></div><div class="consulta-scan-face"><?= consulta_icon('face') ?></div><span class="consulta-art-chip consulta-art-chip-a"><?= consulta_icon('fingerprint') ?></span><span class="consulta-art-chip consulta-art-chip-b"><?= consulta_icon('shield') ?></span><div class="consulta-scan-line"></div></div>
        <p class="consulta-guest-note">Entre na sua conta para pesquisar. O provedor informado usa HTTP; os dados trafegam sem criptografia até a API.</p>
    </main>
    <?php else: ?>
    <div class="consulta-layout">
        <aside class="consulta-sidebar"><span>EXPLORAR</span><a class="active" href="<?= $e($base) ?>"><?= consulta_icon('grid') ?> Todos os módulos</a><a href="<?= $e(app_url('painel/')) ?>"><?= consulta_icon('arrow') ?> Painel Subway Run</a><div class="consulta-sidebar-help"><?= consulta_icon('lock') ?><strong>Pesquisa protegida</strong><small>As consultas são enviadas pelo servidor com acesso autenticado à API.</small></div></aside>
        <main class="consulta-main">
            <section class="consulta-hero"><div><span class="consulta-kicker"><i></i> CENTRAL DE CONSULTAS</span><h1>O que você deseja <em>explorar?</em></h1><p>Encontre o módulo certo em poucos segundos.</p></div><div class="consulta-hero-art" aria-hidden="true"><span><?= consulta_icon('fingerprint') ?></span><span><?= consulta_icon('face') ?></span></div></section>
            <div class="consulta-search"><span><?= consulta_icon('search') ?></span><input id="consulta-search" type="search" placeholder="Buscar um módulo..." autocomplete="off" aria-label="Buscar módulo"><kbd>⌕</kbd></div>
            <div class="consulta-categories" role="group" aria-label="Filtrar módulos"><button type="button" class="active" data-category="all" aria-pressed="true">Todos</button><button type="button" data-category="Pessoas" aria-pressed="false">Pessoas</button><button type="button" data-category="Veículos" aria-pressed="false">Veículos</button><button type="button" data-category="Empresas" aria-pressed="false">Empresas</button><button type="button" data-category="Endereços" aria-pressed="false">Endereços</button><button type="button" data-category="Imagens" aria-pressed="false">Imagens</button><button type="button" data-category="Outros" aria-pressed="false">Outros</button></div>
            <div class="consulta-section-head"><div><span>ESCOLHA UM MÓDULO</span><h2>Ferramentas disponíveis <small><?= count($modules) ?> módulos</small></h2></div></div>
            <div class="consulta-grid" id="consulta-grid">
                <?php foreach ($modules as $slug => $module): ?><a class="consulta-card accent-<?= $e($module['accent']) ?><?= $selected === $slug ? ' is-selected' : '' ?>" href="<?= $e($base) ?>?modulo=<?= rawurlencode($slug) ?>#detalhe" data-category="<?= $e($module['category']) ?>" data-search="<?= $e($lower($module['title'].' '.$module['subtitle'].' '.$module['category'])) ?>"><span class="consulta-card-icon"><?= consulta_icon($module['icon']) ?></span><span class="consulta-card-body"><small><?= $e($upper($module['category'])) ?></small><strong><?= $e($module['title']) ?></strong><em><?= $e($module['subtitle']) ?></em></span><span class="consulta-card-arrow"><?= consulta_icon('arrow') ?></span></a><?php endforeach; ?>
            </div>
            <p class="consulta-empty" id="consulta-empty" hidden>Nenhum módulo encontrado. Tente outro termo.</p>
            <section class="consulta-detail" id="detalhe" aria-labelledby="consulta-detail-title">
                <?php if ($selected !== ''): $module = $modules[$selected]; ?>
                <div class="consulta-detail-top"><span class="consulta-detail-icon accent-<?= $e($module['accent']) ?>"><?= consulta_icon($module['icon']) ?></span><span class="consulta-detail-state"><i></i> Pesquisa na API</span></div>
                <span class="consulta-detail-eyebrow"><?= $e($upper($module['category'])) ?> · MÓDULO DE PESQUISA</span><h2 id="consulta-detail-title"><?= $e($module['title']) ?></h2><p><?= $e($module['detail']) ?></p>
                <div class="consulta-field-list"><strong>FORMAS DE BUSCA</strong><div><?php foreach ($module['fields'] as $field): ?><span><?= $e($field) ?></span><?php endforeach; ?></div></div>
                <form class="consulta-form" id="consulta-form" action="<?= $e(app_url('modo-consulta/consulta.php')) ?>" method="post">
                    <input type="hidden" name="csrf" value="<?= $e(app_csrf()) ?>">
                    <label for="consulta-query">Tipo de consulta</label>
                    <select id="consulta-query" name="query_id" required>
                        <?php foreach ($catalog as $id => $entry): if ($entry['module'] !== $selected) continue; ?>
                        <option value="<?= $e($id) ?>" data-param="<?= $e($entry['param'] === 'valor' ? ($entry['fixed']['campo'] ?? 'valor') : $entry['param']) ?>"><?= $e($entry['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="consulta-value" id="consulta-value-label">Dado para pesquisar</label>
                    <input id="consulta-value" name="value" type="text" maxlength="120" autocomplete="off" spellcheck="false" required placeholder="Digite o dado solicitado">
                    <button type="submit" class="consulta-primary">Pesquisar na API <?= consulta_icon('search') ?></button>
                    <p class="consulta-form-message" id="consulta-form-message" role="status" aria-live="polite"></p>
                </form>
                <section class="consulta-api-result" id="consulta-api-result" hidden aria-live="polite">
                    <div class="consulta-result-head"><div><span>RESPOSTA DA API</span><strong>Dados completos</strong></div><div class="consulta-result-actions"><button type="button" id="consulta-copy">Copiar JSON</button><button type="button" id="consulta-download">Baixar JSON</button></div></div>
                    <pre id="consulta-raw-json" tabindex="0"></pre>
                </section>
                <button class="consulta-demo-button" type="button" data-demo-module="<?= $e($selected) ?>">Ver resultado de exemplo <?= consulta_icon('arrow') ?></button>
                <div class="consulta-demo-result" id="consulta-demo-result" hidden aria-live="polite"></div>
                <div class="consulta-connection-note"><?= consulta_icon('lock') ?><div><strong>Consulta à API informada</strong><p>A conexão atual com o provedor usa HTTP. Dados pesquisados e respostas trafegam sem criptografia nesse trecho. O botão de exemplo gera dados apenas neste navegador.</p></div></div>
                <?php else: ?>
                <div class="consulta-detail-empty"><span><?= consulta_icon('fingerprint') ?></span><h2 id="consulta-detail-title">Escolha um módulo</h2><p>Selecione um card para ver os campos e o fluxo de consulta previsto.</p></div>
                <?php endif; ?>
            </section>
            <footer class="consulta-footer">Modo Consulta · Pesquisas disponíveis para usuários logados.</footer>
        </main>
    </div>
    <?php endif; ?>
</div>
<script src="<?= $e($base) ?>consulta.js?v=<?= filemtime(__DIR__ . '/consulta.js') ?>" defer></script>
</body></html>
