<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
if (!empty($_SESSION['emailadm'])) { header('Location: ' . app_url('adm/'), true, 303); exit; }
if (!empty($_SESSION['manager_id'])) { header('Location: ' . app_url('gerente/'), true, 303); exit; }
if (!empty($_SESSION['email'])) { header('Location: ' . app_url('painel/'), true, 303); exit; }
$nomeUnico = 'Subway Run';
$pageTitle = 'Subway Run · Entre na corrida';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#071426">
    <meta name="description" content="Conheça o Subway Run: treine a corrida, crie sua conta e escolha sua entrada.">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="arquivos/home.css?v=<?= filemtime(__DIR__ . '/arquivos/home.css') ?>">
    <link rel="stylesheet" href="arquivos/home-compact.css?v=<?= filemtime(__DIR__ . '/arquivos/home-compact.css') ?>">
</head>
<body class="home-page">
<?php require __DIR__ . '/components/menu.php'; ?>
<main>
    <section class="home-hero" aria-labelledby="hero-title">
        <div class="home-shell home-hero__grid">
            <div class="home-hero__content">
                <span class="home-eyebrow"><span class="home-live-dot" aria-hidden="true"></span> SUA CORRIDA COMEÇA AQUI</span>
                <h1 id="hero-title"><span>Subway</span> Run</h1>
                <p class="home-hero__lead">Corra, desvie e colete moedas. Comece pelo treino ou crie sua conta para escolher uma entrada.</p>
                <div class="home-hero__actions">
                    <a class="home-button home-button--primary" href="presell/jogoteste/"><?= ui_icon('play', 'home-svg-21') ?> Jogar agora</a>
                    <a class="home-button home-button--ghost" href="cadastrar/">Criar conta <?= ui_icon('arrow', 'home-svg-18') ?></a>
                </div>
                <p class="home-hero__micro">Treino gratuito · Entradas a partir de R$ 5,00</p>
            </div>
            <div class="home-arcade" aria-label="Destaques do jogo">
                <div class="home-arcade__stage" id="home-arcade-stage">
                    <a class="home-arcade__slide is-active" href="cadastrar/" aria-label="Criar conta para entrar na corrida" style="--slide-art:url('har-art/corrida-cidade.jpg')"><span class="home-arcade__copy"><small>MISSÃO 01</small><strong>Entre na corrida</strong><em>Crie sua conta para começar <?= ui_icon('arrow', 'home-svg-18') ?></em></span></a>
                    <a class="home-arcade__slide" href="cadastrar/" aria-label="Criar conta para escolher a entrada" style="--slide-art:url('har-art/personagens-corrida.png')"><span class="home-arcade__copy"><small>ESCOLHA SUA ENTRADA</small><strong>Pronto para jogar?</strong><em>Entre a partir de R$ 5,00 <?= ui_icon('arrow', 'home-svg-18') ?></em></span></a>
                    <a class="home-arcade__slide home-arcade__slide--scene" href="cadastrar/" aria-label="Criar conta e acompanhar suas corridas" style="--slide-art:url('SubwaySurfers02.jpg')"><span class="home-arcade__copy"><small>SUBWAY RUN</small><strong>Seu próximo desafio</strong><em>Acompanhe cada corrida <?= ui_icon('arrow', 'home-svg-18') ?></em></span></a>
                </div>
                <div class="home-arcade__bar"><span>ESCOLHA SEU CAMINHO</span><div class="home-arcade__dots" role="group" aria-label="Selecionar banner"><button type="button" class="is-active" aria-label="Banner 1" aria-current="true"></button><button type="button" aria-label="Banner 2"></button><button type="button" aria-label="Banner 3"></button></div></div>
            </div>
        </div>
    </section>
    <section class="home-entry" aria-label="Como começar">
        <div class="home-shell home-entry__grid">
            <a class="home-entry__card" href="presell/jogoteste/"><span><?= ui_icon('play', 'home-svg-26') ?></span><div><small>PASSO 01</small><strong>Teste a corrida</strong><p>Aprenda os controles gratuitamente.</p></div><?= ui_icon('arrow', 'home-svg-20') ?></a>
            <a class="home-entry__card" href="cadastrar/"><span><?= ui_icon('user', 'home-svg-26') ?></span><div><small>PASSO 02</small><strong>Crie sua conta</strong><p>Escolha a entrada e acompanhe seu saldo.</p></div><?= ui_icon('arrow', 'home-svg-20') ?></a>
        </div>
    </section>
    <section class="home-final-cta"><div class="home-shell home-final-cta__inner"><div><span>PRÓXIMO NÍVEL</span><h2>Pronto para entrar no jogo?</h2></div><a class="home-button home-button--primary" href="cadastrar/">Criar conta <?= ui_icon('arrow', 'home-svg-21') ?></a></div></section>
</main>
<footer class="home-footer"><div class="home-shell home-footer__inner"><div class="home-footer__brand"><span class="home-footer__mark"><?= ui_icon('play', 'home-svg-19') ?></span><div><strong>Subway Run</strong><small>PLAY. RUN. REPEAT.</small></div></div><nav aria-label="Links do rodapé"><a href="login/">Entrar</a><a href="cadastrar/">Criar conta</a><a href="presell/jogoteste/">Treinar</a></nav><small>© <?= date('Y') ?> Subway Run. O treino usa pontuação simulada, sem valor financeiro. Notificações de exemplo são fictícias e não representam saques reais.</small></div></footer>
<script src="arquivos/home-carousel.js?v=<?= filemtime(__DIR__ . '/arquivos/home-carousel.js') ?>" defer></script>
</body>
</html>
