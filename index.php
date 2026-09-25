<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$nomeUnico = 'Subway Run';
$pageTitle = 'Subway Run · Corra. Desvie. Supere.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#080b1a">
    <meta name="description" content="Conheça o Subway Run, teste o jogo gratuitamente e acompanhe sua evolução em uma experiência rápida e divertida.">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="arquivos/menu.css">
    <link rel="stylesheet" href="arquivos/home.css?v=<?= filemtime(__DIR__ . '/arquivos/home.css') ?>">
</head>
<body class="home-page">
<?php require __DIR__ . '/components/menu.php'; ?>

<main>
    <section class="home-hero" aria-labelledby="hero-title">
        <div class="home-hero__glow" aria-hidden="true"></div>
        <div class="home-shell home-hero__grid">
            <div class="home-hero__content">
                <div class="home-eyebrow">
                    <span class="home-live-dot" aria-hidden="true"></span>
                    Modo teste liberado
                </div>
                <h1 id="hero-title"><span>Subway</span> Run</h1>
                <p class="home-hero__lead"><span>Corra pelos trilhos e desvie dos obstáculos.</span><span>Colete moedas e avance</span><span>em uma corrida eletrizante a qualquer hora.</span></p>

                <div class="home-hero__actions">
                    <a class="home-button home-button--primary" href="presell/jogoteste/">
                        <?= ui_icon('play', 'home-svg-21') ?>
                        Jogar modo teste
                    </a>
                    <a class="home-button home-button--ghost" href="cadastrar/">Criar minha conta</a>
                </div>

                <div class="home-trust" aria-label="Destaques">
                    <span><?= ui_icon('shield', 'home-svg-18') ?> Ambiente protegido</span>
                    <span><?= ui_icon('spark', 'home-svg-18') ?> Jogo otimizado</span>
                    <span><?= ui_icon('check', 'home-svg-18') ?> Tutorial guiado</span>
                </div>
            </div>

            <div class="home-game-card" aria-label="Prévia do Subway Run">
                <div class="home-game-card__top">
                    <span><i aria-hidden="true"></i> Corrida ativa</span>
                    <strong>Modo treino</strong>
                </div>
                <div class="home-game-card__scene">
                    <img src="af835635b84ba0916d7c0ddd4e0bd25b.jpg" alt="Personagem correndo pelos trilhos no Subway Run">
                    <div class="home-game-card__score">
                        <small>Moedas coletadas</small>
                        <strong>+ R$ 0,50</strong>
                    </div>
                </div>
                <div class="home-game-card__footer">
                    <div>
                        <span>Treine no seu ritmo</span>
                        <small>Treino separado do saldo da conta</small>
                    </div>
                    <a href="presell/jogoteste/" aria-label="Abrir modo teste"><?= ui_icon('arrow', 'home-svg-20') ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section" aria-labelledby="steps-title">
        <div class="home-shell">
            <div class="home-section__heading">
                <span>Comece em segundos</span>
                <h2 id="steps-title">Uma jornada simples e envolvente</h2>
                <p>Conheça os controles no modo teste e avance para sua conta quando estiver pronto.</p>
            </div>

            <div class="home-steps">
                <article class="home-step-card">
                    <div class="home-icon home-icon--cyan"><?= ui_icon('play', 'home-svg-26') ?></div>
                    <span>01</span>
                    <h3>Entre no treino</h3>
                    <p>Abra o modo teste e acompanhe o tutorial com velocidade normal.</p>
                </article>
                <article class="home-step-card">
                    <div class="home-icon home-icon--violet"><?= ui_icon('spark', 'home-svg-26') ?></div>
                    <span>02</span>
                    <h3>Domine a corrida</h3>
                    <p>Desvie, pule e colete moedas enquanto melhora sua pontuação.</p>
                </article>
                <article class="home-step-card">
                    <div class="home-icon home-icon--gold"><?= ui_icon('trophy', 'home-svg-26') ?></div>
                    <span>03</span>
                    <h3>Crie sua conta</h3>
                    <p>Salve seu perfil, veja o histórico e acesse todos os recursos da plataforma.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section home-section--experience" aria-labelledby="experience-title">
        <div class="home-shell home-experience">
            <div class="home-experience__visual">
                <img src="arquivos/SubwaySurfers02.jpg" alt="Cenário de corrida entre trens">
                <div class="home-experience__badge">
                    <?= ui_icon('spark', 'home-svg-21') ?>
                    <div><strong>Desafio em movimento</strong><span>Corra, colete e avance</span></div>
                </div>
            </div>
            <div class="home-experience__content">
                <span class="home-kicker">A corrida começa aqui</span>
                <h2 id="experience-title">Cada corrida é uma nova chance de superar sua marca</h2>
                <p>Acelere pelos trilhos, encontre o melhor caminho e acompanhe sua evolução a cada nova partida.</p>
                <ul class="home-check-list">
                    <li><?= ui_icon('check', 'home-svg-18') ?><span><strong>Treino guiado</strong> para dominar todos os movimentos.</span></li>
                    <li><?= ui_icon('check', 'home-svg-18') ?><span><strong>Meta sempre visível</strong> durante toda a corrida.</span></li>
                    <li><?= ui_icon('check', 'home-svg-18') ?><span><strong>Histórico completo</strong> para acompanhar cada partida.</span></li>
                </ul>
                <a class="home-text-link" href="presell/jogoteste/">Experimentar agora <?= ui_icon('arrow', 'home-svg-17') ?></a>
            </div>
        </div>
    </section>

    <section class="home-section home-section--payouts" aria-labelledby="payouts-title">
        <div class="home-shell">
            <div class="home-section__heading home-section__heading--left">
                <span>Movimentações recentes</span>
                <h2 id="payouts-title">Atividade da comunidade</h2>
                <p>Registros confirmados aparecem aqui automaticamente.</p>
            </div>
            <?php require __DIR__ . '/components/recent-payouts.php'; ?>
        </div>
    </section>

    <section class="home-section" aria-labelledby="faq-title">
        <div class="home-shell home-faq-layout">
            <div class="home-section__heading home-section__heading--left">
                <span>Central de ajuda</span>
                <h2 id="faq-title">Dúvidas frequentes</h2>
                <p>Informações diretas para você começar com tranquilidade.</p>
            </div>
            <div class="home-faq">
                <details open>
                    <summary>Como funciona o modo teste?<span>+</span></summary>
                    <p>Ele apresenta os controles e a dinâmica da corrida sem exigir depósito. A pontuação do treino fica separada do saldo da conta.</p>
                </details>
                <details>
                    <summary>Preciso criar uma conta para treinar?<span>+</span></summary>
                    <p>Não. Você pode abrir o modo teste diretamente. Ao final, pode criar uma conta ou entrar em uma conta existente.</p>
                </details>
                <details>
                    <summary>Posso jogar pelo celular?<span>+</span></summary>
                    <p>Sim. A interface foi adaptada para iPhone, Android, tablets e computadores.</p>
                </details>
                <details>
                    <summary>Onde acompanho minhas partidas?<span>+</span></summary>
                    <p>Depois de entrar, abra seu perfil para consultar histórico, movimentações e informações da conta.</p>
                </details>
            </div>
        </div>
    </section>

    <section class="home-final-cta">
        <div class="home-shell home-final-cta__inner">
            <div>
                <span>Pronto para correr?</span>
                <h2>Descubra o Subway Run no modo teste.</h2>
            </div>
            <a class="home-button home-button--primary" href="presell/jogoteste/">
                <?= ui_icon('play', 'home-svg-21') ?> Jogar agora
            </a>
        </div>
    </section>
</main>

<footer class="home-footer">
    <div class="home-shell home-footer__inner">
        <div class="home-footer__brand">
            <span class="home-footer__mark"><?= ui_icon('play', 'home-svg-19') ?></span>
            <div><strong>Subway Run</strong><small>PLAY. RUN. REPEAT.</small></div>
        </div>
        <nav aria-label="Links do rodapé">
            <a href="login/">Entrar</a>
            <a href="cadastrar/">Criar conta</a>
            <a href="#faq-title">Ajuda</a>
        </nav>
        <small>© <?= date('Y') ?> Subway Run. Todos os direitos reservados.</small>
    </div>
</footer>
</body>
</html>
