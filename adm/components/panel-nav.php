<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../components/icons.php';
$adminActive = $adminActive ?? 'dashboard';
$adminTitle = $adminTitle ?? 'Painel de controle';
$adminLinks = [
    ['dashboard', 'adm/', 'Painel', 'wallet'],
    ['ggr', 'adm/GGR/', 'GGR', 'coins'],
    ['usuarios', 'adm/usuarios/', 'Usuários', 'users'],
    ['depositos', 'adm/depositos/', 'Depósitos', 'deposit'],
    ['saques', 'adm/saques/', 'Saques', 'withdraw'],
    ['gerentes', 'adm/gerentes/', 'Gerentes', 'user'],
    ['comissoes', 'adm/comissoes/', 'Comissões', 'coins'],
    ['gateway', 'adm/gateway/bxpay.php', 'Gateway BX Pay', 'shield'],
    ['jogo', 'adm/jogo/', 'Jogo e ganhos', 'play'],
    ['config', 'adm/config/', 'Configurações', 'edit'],
    ['planos', 'adm/planos/', 'Afiliados', 'link'],
    ['pixels', 'adm/pixels/', 'Pixels', 'spark'],
    ['conta', 'adm/conta/', 'Minha conta', 'user'],
    ['consulta', 'modo-consulta/', 'Modo Consulta', 'shield'],
];
?>
<aside class="manager-sidebar" id="admin-sidebar">
    <a class="manager-brand" href="<?= app_escape(app_url('adm/')) ?>"><span class="brand-glyph">S</span><span>SUBWAY<small>RUN · ADMIN</small></span></a>
    <button class="sidebar-close" type="button" data-admin-menu-close aria-label="Fechar menu">×</button>
    <nav aria-label="Navegação administrativa">
        <small class="nav-caption">OPERAÇÃO</small>
        <?php foreach ($adminLinks as [$key, $path, $label, $icon]): ?>
        <a class="nav-link<?= $adminActive === $key ? ' active' : '' ?>" href="<?= app_escape(app_url($path)) ?>"<?= $adminActive === $key ? ' aria-current="page"' : '' ?>><?= ui_icon($icon) ?><?= app_escape($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <a class="sidebar-logout" href="<?= app_escape(app_url('adm/logout.php')) ?>"><?= ui_icon('logout') ?> Sair</a>
</aside>
<button class="sidebar-shade" type="button" data-admin-menu-close aria-label="Fechar navegação"></button>
<main class="manager-main"><header class="manager-topbar"><button class="mobile-menu" type="button" data-admin-menu-open aria-label="Abrir navegação"><?= ui_icon('menu') ?></button><strong class="view-title"><?= app_escape($adminTitle) ?></strong><span class="admin-topmark">SUBWAY RUN <b>ADMIN</b></span><a class="manager-avatar" href="<?= app_escape(app_url('adm/conta/')) ?>" aria-label="Minha conta">A</a></header><div class="manager-content">
