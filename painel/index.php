<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/account.php';

$db = app_db();
$user = account_user($db);
game_install($db);

$settings = $db->query('SELECT meta_multiplier,coin_value_demo,coin_value_paid FROM game_settings WHERE id=1')->fetch_assoc() ?: [];
$multiplier = max(1.0, (float) ($settings['meta_multiplier'] ?? 10));
$isDemo = (string) ($user['demo'] ?? '0') === '1';
$balance = (float) ($user['saldo'] ?? 0);
$gameError = (string) ($_SESSION['game_error'] ?? '');
unset($_SESSION['game_error']);

$recentRounds = app_query(
    $db,
    'SELECT bet,payout,status,created_at FROM game_rounds WHERE email=? ORDER BY created_at DESC LIMIT 3',
    [(string) $user['email']]
)->get_result()->fetch_all(MYSQLI_ASSOC);

$bets = [
    ['code' => '5BC', 'value' => 5.00, 'label' => 'R$ 5,00'],
    ['code' => '10BC', 'value' => 10.00, 'label' => 'R$ 10,00'],
    ['code' => '20BC', 'value' => 20.00, 'label' => 'R$ 20,00'],
    ['code' => '30BC', 'value' => 30.00, 'label' => 'R$ 30,00'],
    ['code' => '50BC', 'value' => 50.00, 'label' => 'R$ 50,00'],
    ['code' => '100BC', 'value' => 100.00, 'label' => 'R$ 100,00'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#080b1a">
    <title>Jogar · Subway Run</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="arquivos/dashboard.css?v=<?= filemtime(__DIR__ . '/arquivos/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= app_escape(app_url('arquivos/banner-carousel.css')) ?>?v=<?= filemtime(__DIR__ . '/../arquivos/banner-carousel.css') ?>">
</head>
<body class="player-dashboard">
<?php $menuBase = '../'; $menuLoggedIn = true; $menuCurrent = 'painel/'; require __DIR__ . '/../components/menu.php'; ?>

<main class="player-main">
    <section class="player-banner-section" aria-label="Artes demonstrativas do jogo">
        <div class="player-shell">
            <?php require __DIR__ . '/../components/game-banners.php'; ?>
        </div>
    </section>

    <section class="player-game-section" aria-labelledby="choose-title">
        <div class="player-shell">
            <?php if ($gameError !== ''): ?>
                <div class="player-alert" role="alert"><?= ui_icon('help') ?><span><?= app_escape($gameError) ?></span></div>
            <?php endif; ?>

            <?php $firstAvailableBet = null; foreach ($bets as $candidate) { if ($balance >= $candidate['value']) { $firstAvailableBet = $candidate['code']; break; } } ?>
            <form class="player-race-picker" id="player-race-form" method="post" action="<?= app_url('game/start.php') ?>">
                <input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>">
                <div class="player-section-title">
                    <div>
                        <span>Escolha sua corrida</span>
                        <h2 id="choose-title">Escolha sua entrada</h2>
                    </div>
                    <div class="player-target-pill"><?= ui_icon('trophy') ?><span>Meta da rodada<strong><?= app_escape(number_format($multiplier, 0, ',', '.')) ?>× a entrada</strong></span></div>
                </div>

                <div class="player-bet-options" role="radiogroup" aria-label="Valor da entrada">
                    <?php foreach ($bets as $bet): $unavailable = $balance < $bet['value']; ?>
                        <?php if ($unavailable && !$isDemo): ?>
                        <a class="player-bet-option is-deposit-link" href="<?= app_url('deposito/') ?>" aria-label="Depositar para jogar com entrada de <?= app_escape($bet['label']) ?>">
                            <span class="player-bet-option__label">Entrada</span><strong><?= app_escape($bet['label']) ?></strong><span class="player-bet-option__target">Depositar para jogar</span>
                        </a>
                        <?php else: ?>
                        <label class="player-bet-option<?= $firstAvailableBet === $bet['code'] ? ' is-selected' : '' ?><?= $unavailable ? ' is-disabled' : '' ?>">
                            <input type="radio" name="bet" value="<?= app_escape($bet['code']) ?>" data-target="<?= app_escape(number_format($bet['value'] * $multiplier, 2, '.', '')) ?>" <?= $firstAvailableBet === $bet['code'] ? 'checked' : '' ?> <?= $unavailable ? 'disabled' : '' ?> <?= !$unavailable && $firstAvailableBet === $bet['code'] ? 'required' : '' ?>>
                            <span class="player-bet-option__label">Entrada</span>
                            <strong><?= app_escape($bet['label']) ?></strong>
                            <span class="player-bet-option__target">Meta <?= account_money($bet['value'] * $multiplier) ?></span>
                        </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="player-race-picker__footer">
                    <div class="player-race-picker__summary">
                        <span><?= ui_icon('trophy') ?></span>
                        <div><small>Meta da corrida selecionada</small><strong id="player-selected-target"><?= $firstAvailableBet ? account_money((float)array_column($bets, 'value', 'code')[$firstAvailableBet] * $multiplier) : '—' ?></strong></div>
                    </div>
                    <?php if ($firstAvailableBet): ?>
                        <button type="submit" class="player-play-button"><?= ui_icon('play') ?> Iniciar corrida</button>
                    <?php elseif (!$isDemo): ?>
                        <a class="player-play-button" href="<?= app_url('deposito/') ?>"><?= ui_icon('deposit') ?> Depositar para jogar</a>
                    <?php else: ?>
                        <a class="player-play-button" href="<?= app_url('jogar/?demo=1&jogarsubway=5BC&SbSB1C2') ?>"><?= ui_icon('play') ?> Treinar agora</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!$isDemo && $balance < 5): ?>
                <div class="player-balance-callout">
                    <span class="player-balance-callout__icon"><?= ui_icon('wallet') ?></span>
                    <div><strong>Faltam <?= account_money(max(0, 5 - $balance)) ?> para a primeira entrada</strong><p>Adicione saldo para jogar a partir de R$ 5,00.</p></div>
                    <a href="<?= app_url('deposito/') ?>">Depositar agora <?= ui_icon('arrow') ?></a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="player-extra-section">
        <div class="player-shell player-extra-grid">
            <article class="player-training-card">
                <div class="player-training-card__copy">
                    <span class="player-kicker"><i></i> Aqueça antes da corrida</span>
                    <h2>Treine antes de correr</h2>
                    <p>Aprenda os movimentos no tutorial.</p>
                    <a href="<?= app_url('jogar/?demo=1&jogarsubway=5BC&SbSB1C2') ?>"><?= ui_icon('play') ?> Treinar agora</a>
                </div>
                <div class="player-training-card__art" aria-hidden="true"><span><?= ui_icon('play') ?></span></div>
            </article>

            <article class="player-history-card">
                <div class="player-history-card__head"><div><span>Últimas partidas</span><h2>Seu ritmo recente</h2></div><a href="<?= app_url('perfil/') ?>">Histórico <?= ui_icon('arrow') ?></a></div>
                <?php if (!$recentRounds): ?>
                    <div class="player-empty"><?= ui_icon('history') ?><p>Sua primeira corrida aparecerá aqui.</p></div>
                <?php else: ?>
                    <div class="player-round-list">
                        <?php foreach ($recentRounds as $round): ?>
                            <div class="player-round">
                                <span class="player-round__icon"><?= ui_icon($round['status'] === 'WIN' ? 'trophy' : 'play') ?></span>
                                <div><strong><?= app_escape(account_status((string) $round['status'])) ?></strong><small><?= app_escape(date('d/m · H:i', strtotime((string) $round['created_at']))) ?></small></div>
                                <div class="player-round__value"><strong><?= account_money($round['status'] === 'WIN' ? $round['payout'] : $round['bet']) ?></strong><small><?= $round['status'] === 'WIN' ? 'resgatado' : 'entrada' ?></small></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    </section>

</main>
<script>
(() => {
    const form = document.getElementById('player-race-form');
    const target = document.getElementById('player-selected-target');
    if (!form || !target) return;
    const currency = new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'});
    form.querySelectorAll('input[name="bet"]').forEach(input => input.addEventListener('change', () => {
        form.querySelectorAll('.player-bet-option').forEach(option => option.classList.remove('is-selected'));
        input.closest('.player-bet-option')?.classList.add('is-selected');
        target.textContent = currency.format(Number(input.dataset.target || 0));
    }));
})();
</script>
<script src="<?= app_escape(app_url('arquivos/home-carousel.js')) ?>?v=<?= filemtime(__DIR__ . '/../arquivos/home-carousel.js') ?>" defer></script>
<footer class="player-disclosure" role="note"><div class="player-shell">As notificações de exemplo exibidas nesta página são fictícias e não correspondem a saques reais. O histórico da sua conta mostra as movimentações registradas no sistema.</div></footer>
</body>
</html>
