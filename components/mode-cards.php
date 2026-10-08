<?php
$modeLoggedIn = $modeLoggedIn ?? !empty($_SESSION['email']);
$modeEscape = static fn(string $value): string => app_escape($value);
?>
<section class="sk-modes" aria-labelledby="sk-modes-title">
    <div class="sk-modes__shell">
        <div class="sk-modes__heading"><span>OUTROS MODOS</span><h2 id="sk-modes-title">Escolha um modo</h2></div>
        <div class="sk-modes__grid">
            <a class="sk-mode-card sk-mode-card--bubble" href="<?= $modeEscape(app_url($modeLoggedIn ? 'modo-bubble/painel' : 'login/?next=bubble')) ?>">
                <span class="sk-mode-card__symbol" aria-hidden="true">◉</span><span><small>ESTILO ARCADE</small><strong>Modo Bubble</strong><em>Explore o jogo de bolhas</em></span><b aria-hidden="true">↗</b>
            </a>
            <a class="sk-mode-card sk-mode-card--trader" href="<?= $modeEscape(app_url('modo-trader/')) ?>">
                <span class="sk-mode-card__symbol" aria-hidden="true">↗</span><span><small>PAINEL DE TREINO</small><strong>Modo Trader</strong><em>Explore o mercado em treino</em></span><b aria-hidden="true">↗</b>
            </a>
        </div>
    </div>
</section>
