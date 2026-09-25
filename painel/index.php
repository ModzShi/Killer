<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/account.php';

$db = app_db();
$user = account_user($db);
game_install($db);

$settings = $db->query('SELECT difficulty,meta_multiplier,coin_value_demo,coin_value_paid FROM game_settings WHERE id=1')->fetch_assoc() ?: [];
$multiplier = max(1.0, (float) ($settings['meta_multiplier'] ?? 10));
$difficulty = (string) ($settings['difficulty'] ?? 'medio');
$difficultyCode = ['facil' => 'B1C2', 'medio' => 'B1C3', 'dificil' => 'B1C4'][$difficulty] ?? 'B1C3';
$isDemo = (string) ($user['demo'] ?? '0') === '1';
$displayName = trim((string) ($user['nome'] ?? ''));
if ($displayName === '') $displayName = ucfirst((string) strtok((string) $user['email'], '@'));
$firstName = explode(' ', $displayName)[0];
$balance = (float) ($user['saldo'] ?? 0);
$gameError = (string) ($_SESSION['game_error'] ?? '');
unset($_SESSION['game_error']);

$recentRounds = app_query(
    $db,
    'SELECT bet,payout,status,created_at FROM game_rounds WHERE email=? ORDER BY created_at DESC LIMIT 3',
    [(string) $user['email']]
)->get_result()->fetch_all(MYSQLI_ASSOC);

$bets = [
    ['code' => '1BC', 'value' => 1.00, 'label' => 'R$ 1,00'],
    ['code' => '2BC', 'value' => 2.00, 'label' => 'R$ 2,00'],
    ['code' => '3BC', 'value' => 5.00, 'label' => 'R$ 5,00'],
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
</head>
<body class="player-dashboard">
<?php $menuBase = '../'; $menuLoggedIn = true; $menuCurrent = 'painel/'; require __DIR__ . '/../components/menu.php'; ?>

<main class="player-main">
    <section class="player-hero" aria-labelledby="player-title">
        <div class="player-hero__light" aria-hidden="true"></div>
        <div class="player-shell player-hero__grid">
            <div class="player-intro">
                <span class="player-kicker"><i></i> Sua próxima corrida começa agora</span>
                <h1 id="player-title">Olá, <?= app_escape($firstName) ?>.<br><span>Pronto para correr?</span></h1>
                <p>Escolha sua entrada, supere a meta da rodada e resgate as moedas antes que a corrida termine.</p>
                <div class="player-highlights">
                    <span><?= ui_icon('spark') ?> Partida rápida</span>
                    <span><?= ui_icon('trophy') ?> Meta visível</span>
                    <span><?= ui_icon('shield') ?> Saldo protegido</span>
                </div>
            </div>

            <aside class="player-wallet" aria-label="Resumo da conta">
                <div class="player-wallet__top">
                    <span class="player-wallet__icon"><?= ui_icon('wallet') ?></span>
                    <a href="<?= app_url('perfil/') ?>">Ver perfil <?= ui_icon('arrow') ?></a>
                </div>
                <small>Saldo disponível</small>
                <strong><?= account_money($balance) ?></strong>
                <div class="player-wallet__actions">
                    <?php if (!$isDemo): ?>
                        <a class="player-action player-action--primary" href="<?= app_url('deposito/') ?>"><?= ui_icon('deposit') ?> Depositar</a>
                        <a class="player-action player-action--secondary" href="<?= app_url('saque/') ?>"><?= ui_icon('withdraw') ?> Sacar</a>
                    <?php else: ?>
                        <span class="player-demo-chip"><?= ui_icon('spark') ?> Conta de demonstração</span>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </section>

    <section class="player-game-section" aria-labelledby="choose-title">
        <div class="player-shell">
            <?php if ($gameError !== ''): ?>
                <div class="player-alert" role="alert"><?= ui_icon('help') ?><span><?= app_escape($gameError) ?></span></div>
            <?php endif; ?>

            <div class="player-section-title">
                <div>
                    <span>Escolha sua corrida</span>
                    <h2 id="choose-title">Quanto você quer colocar em jogo?</h2>
                </div>
                <div class="player-target-pill"><?= ui_icon('trophy') ?><span>Meta da rodada<strong><?= app_escape(number_format($multiplier, 0, ',', '.')) ?>× a entrada</strong></span></div>
            </div>

            <div class="player-bets">
                <?php foreach ($bets as $index => $bet): ?>
                    <article class="player-bet-card <?= $index === 1 ? 'is-featured' : '' ?>">
                        <?php if ($index === 1): ?><span class="player-popular">Mais escolhido</span><?php endif; ?>
                        <div class="player-bet-card__icon"><?= ui_icon($index === 1 ? 'trophy' : 'coins') ?></div>
                        <small>Entrada</small>
                        <h3><?= app_escape($bet['label']) ?></h3>
                        <div class="player-potential"><span>Meta</span><strong><?= account_money($bet['value'] * $multiplier) ?></strong></div>
                        <form method="post" action="<?= app_url('game/start.php') ?>">
                            <input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>">
                            <input type="hidden" name="bet" value="<?= app_escape($bet['code']) ?>">
                            <input type="hidden" name="difficulty" value="<?= app_escape($isDemo ? 'B1C2' : $difficultyCode) ?>">
                            <button type="submit" class="player-play-button" <?= $balance < $bet['value'] ? 'disabled' : '' ?>>
                                <?= ui_icon('play') ?> <?= $balance < $bet['value'] ? 'Saldo insuficiente' : 'Começar corrida' ?>
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (!$isDemo && $balance < 1): ?>
                <div class="player-balance-callout">
                    <span class="player-balance-callout__icon"><?= ui_icon('wallet') ?></span>
                    <div><strong>Abasteça sua carteira e entre na corrida</strong><p>Faça um depósito para liberar as opções de partida.</p></div>
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
                    <h2>Treine os movimentos e encontre seu ritmo.</h2>
                    <p>Pratique trocas de trilho, saltos e rolamentos com o tutorial guiado.</p>
                    <a href="<?= app_url('jogar/?demo=1&jogarsubway=1BC&SbSB1C2') ?>"><?= ui_icon('play') ?> Treinar agora</a>
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

    <section class="player-community">
        <div class="player-shell">
            <div class="player-section-title"><div><span>Comunidade Subway Run</span><h2>Jogadores em movimento</h2></div></div>
            <?php require __DIR__ . '/../components/recent-payouts.php'; ?>
        </div>
    </section>
</main>
</body>
</html>
