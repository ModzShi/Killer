<?php
require_once __DIR__ . '/../app/game.php';
$preview = !empty($gamePreview) && SK_OFFLINE;
// A partida de treino pode ser concluída sem conta; o prêmio permanece fictício.
$submittedToken = app_input('token');
$validDemoToken = preg_match('/^demo_[a-f0-9]{16,64}$/D', $submittedToken) === 1;
if (!$preview && !$validDemoToken && empty($_SESSION['email'])) { header('Location: '.app_url('login/')); exit; }
if (!$preview && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: '.app_url('painel/')); exit; }
$token = $submittedToken;
header('Cache-Control: no-store, private');
$demo = $preview || $validDemoToken;
$fictional = $demo || !empty($_SESSION['demo_account']);
$betAmounts = ['1BC'=>1.0,'2BC'=>2.0,'3BC'=>5.0];
$bet = $betAmounts[app_input('bet')] ?? 0.0;
$payout = 0.0;
$error = '';
try {
    if ($preview) { $bet=5; $payout=$gameResult==='WIN'?50:0; }
    elseif (!$demo) {
        $round = game_settle(app_db(), $_SESSION['email'], $token, $gameResult, (float)app_input('msg'));
        if ($round['status'] !== $gameResult) throw new RuntimeException('Resultado da rodada diferente.');
        $bet = (float)$round['bet']; $payout = (float)$round['payout'];
    } elseif ($gameResult === 'WIN') {
        $payout = max(0, min((float)app_input('msg'), 10000));
    }
} catch (Throwable $e) {
    $error = 'Não foi possível confirmar o resultado. Confira seu saldo no painel.';
    error_log('game result: '.$e->getMessage());
}
$win = $gameResult === 'WIN' && $error === '';
$again = $demo ? app_url('presell/jogoteste/') : app_url('painel/');
$money = static fn(float $v): string => 'R$ '.number_format($v, 2, ',', '.');
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#07142c"><title><?= $error ? 'Resultado da corrida' : ($win ? 'Vitória!' : 'Fim da corrida') ?></title><link rel="stylesheet" href="<?= app_escape(app_url('gameover/result.css')) ?>"><link rel="stylesheet" href="<?= app_escape(app_url('gameover/prompt.css')) ?>"></head>
<body class="<?= $error ? 'error' : ($win ? 'win' : 'loss') ?>">
<script>try{sessionStorage.setItem('finished:'+<?= json_encode($token, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,'1')}catch(e){}</script>
<div class="stage" aria-hidden="true"></div><div class="confetti" aria-hidden="true"><?php if ($win): for($i=0;$i<24;$i++): ?><i style="--x:<?= ($i*47)%100 ?>%;--d:<?= ($i%8)*.15 ?>s;--r:<?= ($i*71)%340 ?>deg"></i><?php endfor; endif; ?></div>
<main class="result-card"><div class="topline"><span class="brand"><span class="brand-bolt">ϟ</span> SUBWAY <b>RUN</b></span><button id="sound" type="button" aria-label="Reproduzir som" title="Som"><?= ui_icon("sound") ?></button></div>
<div class="halo"><div class="emblem" aria-hidden="true"><?= ui_icon($error ? 'help' : ($win ? 'trophy' : 'history')) ?></div></div>
<p class="eyebrow"><?= $preview ? 'PRÉVIA VISUAL • SEM TRANSAÇÃO' : ($fictional ? 'MODO TREINO • RESULTADO FICTÍCIO' : 'RESULTADO DA CORRIDA') ?></p>
<h1><?= $error ? 'OCORREU UM PROBLEMA' : ($win ? 'CORRIDA VENCIDA!' : 'FIM DE CORRIDA') ?></h1>
<p class="subtitle"><?= $error ? app_escape($error) : ($win ? ($fictional ? 'Boa corrida! No treino, os valores são apenas uma simulação.' : 'Meta alcançada. Seu prêmio foi registrado na conta.') : 'A corrida terminou. Respire, ajuste o ritmo e tente outra vez.') ?></p>
<?php if (!$error): ?><div class="reward"><span class="reward-label"><?= $win ? ($fictional ? 'MOEDAS DE TREINO' : 'PRÊMIO CREDITADO') : 'APOSTA DA RODADA' ?></span><strong id="amount" data-value="<?= $win ? app_escape((string)$payout) : app_escape((string)$bet) ?>"><?= $win ? 'R$ 0,00' : app_escape($money($bet)) ?></strong><span class="reward-detail"><?= $win ? '✦ META CONCLUÍDA ✦' : 'TENTE DE NOVO NO SEU RITMO' ?></span></div><?php endif; ?>
<div class="actions"><a class="button primary" href="<?= app_escape($again) ?>"><span><?= $demo ? 'TREINAR DE NOVO' : 'JOGAR DE NOVO' ?></span><b aria-hidden="true">→</b></a><a class="button secondary" href="<?= app_escape(app_url('painel/')) ?>">VOLTAR AO PAINEL</a></div>
<p class="footnote"><?= $fictional ? 'Nenhum valor real foi apostado ou recebido.' : 'Jogue com responsabilidade. Maiores de 18 anos.' ?></p></main>
<?php if ($demo && !$error): ?>
<div class="demo-prompt" id="demo-prompt" hidden>
  <div class="demo-prompt-backdrop" data-close-prompt></div>
  <section class="demo-prompt-card" role="dialog" aria-modal="true" aria-labelledby="demo-prompt-title" aria-describedby="demo-prompt-description">
    <button class="demo-prompt-close" type="button" data-close-prompt aria-label="Fechar convite">×</button>
    <span class="demo-prompt-tag"><?= $win ? '✦ TREINO CONCLUÍDO' : '✦ QUASE LÁ' ?></span>
    <span class="demo-prompt-icon" aria-hidden="true"><?= ui_icon($win ? 'trophy' : 'play') ?></span>
    <h2 id="demo-prompt-title"><?= $win ? 'Mandou bem no treino!' : 'Quase lá! Sua próxima corrida espera.' ?></h2>
    <p id="demo-prompt-description"><?= $win ? 'Você concluiu uma corrida de demonstração. Crie sua conta para acessar o jogo completo e jogar valendo.' : 'O treino terminou, mas você pode tentar de novo. Crie sua conta para acessar o jogo completo quando quiser.' ?></p>
    <p class="demo-prompt-note">Os valores deste treino são fictícios. Resultados futuros dependem de cada corrida.</p>
    <div class="demo-prompt-actions">
      <a class="demo-prompt-primary" href="<?= app_escape(app_url('cadastrar/')) ?>">CRIAR CONTA <span aria-hidden="true">→</span></a>
      <a class="demo-prompt-secondary" href="<?= app_escape(app_url('login/')) ?>">JÁ TENHO CONTA · ENTRAR</a>
      <button class="demo-prompt-later" type="button" data-close-prompt>Ver meu resultado</button>
    </div>
  </section>
</div>
<?php endif; ?>
<script>window.RESULT_ICONS=<?= json_encode(["sound"=>ui_icon("sound"),"muted"=>ui_icon("muted")],JSON_HEX_TAG) ?>;</script><script src="<?= app_escape(app_url('gameover/result.js')) ?>?v=4" defer></script></body></html>
