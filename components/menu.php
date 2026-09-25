<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/icons.php';

// Callers provide the relative project root and the authenticated menu state.
$menuBase = $menuBase ?? './';
$menuLoggedIn = $menuLoggedIn ?? false;
$menuCurrent = $menuCurrent ?? '';
$menuLinks = $menuLoggedIn
    ? ['painel/' => 'Jogar', 'saque/' => 'Sacar', 'afiliate/' => 'Afiliado', 'perfil/' => 'Perfil']
    : ['presell/jogoteste/' => 'Jogar agora', 'login/' => 'Entrar'];
$menuEscape = static function ($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); };
?>
<link rel="stylesheet" href="<?= $menuEscape($menuBase) ?>arquivos/menu.css?v=<?= filemtime(__DIR__.'/../arquivos/menu.css') ?>">
<header class="sk-header">
    <div class="sk-header-inner">
        <a class="sk-brand" href="<?= $menuEscape($menuBase . ($menuLoggedIn ? 'painel/' : '')) ?>" aria-label="Página inicial">
            <span class="sk-brand-mark" aria-hidden="true"><?= ui_icon('play') ?></span>
        <span>Subway Run<small>PLAY. RUN. REPEAT.</small></span>
        </a>
        <button class="sk-toggle" type="button" aria-expanded="false" aria-controls="sk-navigation" aria-label="Abrir menu" hidden>
            <span></span><span></span><span></span>
        </button>
        <nav class="sk-navigation" id="sk-navigation" aria-label="Menu principal">
            <?php foreach ($menuLinks as $path => $label): ?>
                <a class="sk-link" href="<?= $menuEscape($menuBase . $path) ?>" <?= $menuCurrent === $path ? 'aria-current="page"' : '' ?>><?= ui_icon(['painel/'=>'play','saque/'=>'withdraw','afiliate/'=>'users','perfil/'=>'user','presell/jogoteste/'=>'play','login/'=>'user'][$path]??'spark') ?> <?= $menuEscape($label) ?></a>
            <?php endforeach; ?>
            <a class="sk-cta" href="<?= $menuEscape($menuBase . ($menuLoggedIn ? 'deposito/' : 'cadastrar/')) ?>" <?= $menuCurrent === 'deposito/' ? 'aria-current="page"' : '' ?>><?= ui_icon($menuLoggedIn?'deposit':'user') ?> <?= $menuLoggedIn ? 'Depositar' : 'Criar conta' ?></a>
            <?php if ($menuLoggedIn): ?><a class="sk-exit" href="<?= $menuEscape($menuBase) ?>logout.php"><?= ui_icon('logout') ?> Sair</a><?php endif; ?>
        </nav>
    </div>
</header>
<script src="<?= $menuEscape($menuBase) ?>arquivos/menu.js?v=<?= filemtime(__DIR__.'/../arquivos/menu.js') ?>" defer></script>
