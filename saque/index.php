<?php
require_once __DIR__ . '/../app/auth.php';
if (empty($_SESSION['email'])) { header('Location: ' . app_url('login/'), true, 303); exit; }
$db = app_db();
$user = app_query($db, 'SELECT nome,saldo,total_apostado,depositou,demo FROM appconfig WHERE email=? LIMIT 1', [(string)$_SESSION['email']])->get_result()->fetch_assoc();
if (!$user) { $db->close(); header('Location: ' . app_url('logout.php'), true, 303); exit; }
$demoAccount = (string)($user['demo'] ?? '0') === '1';
$app = $db->query('SELECT nome_unico,saques_min,rollover_saque FROM app LIMIT 1')->fetch_assoc() ?: [];
$db->close();
$balance = (float)($user['saldo'] ?? 0);
$zeroBalance = $balance <= 0;
$minimum = $demoAccount ? 0.01 : max(0, (float)($app['saques_min'] ?? 0));
$rollover = max(0, (float)($app['rollover_saque'] ?? 0));
$rolloverRequired = (float)($user['depositou'] ?? 0) * $rollover;
$rolloverProgress = $demoAccount ? 100 : ($rolloverRequired > 0 ? min(100, max(0, (float)$user['total_apostado'] / $rolloverRequired * 100)) : 100);
$rolloverComplete = $demoAccount || (float)($user['total_apostado'] ?? 0) >= $rolloverRequired;
$canRequest = $demoAccount ? ($balance >= $minimum) : ($rolloverComplete && $balance > 0 && $balance >= $minimum);
$notice = $_SESSION['withdraw_notice'] ?? '';
$demoSuccess = $demoAccount && !empty($_SESSION['withdraw_demo_success']);
unset($_SESSION['withdraw_notice']);
unset($_SESSION['withdraw_demo_success']);
$_SESSION['withdraw_nonce'] = bin2hex(random_bytes(16));
$quickAmounts = array_values(array_filter([25, 50, 100, 200], static fn($value) => $value >= $minimum && $value <= $balance));
$siteName = (string)($app['nome_unico'] ?? 'Subway Run');
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#090b1a">
    <title>Sacar · <?= app_escape($siteName) ?></title>
    <link rel="stylesheet" href="<?= app_escape(app_url('arquivos/menu.css')) ?>?v=<?= filemtime(dirname(__DIR__) . '/arquivos/menu.css') ?>">
    <link rel="stylesheet" href="<?= app_escape(app_url('arquivos/wallet.css')) ?>?v=<?= filemtime(dirname(__DIR__) . '/arquivos/wallet.css') ?>">
    <style>
        .withdraw-demo-success{display:grid;gap:5px;margin-bottom:16px;padding:14px 16px;border:1px solid #38d9a077;border-radius:14px;background:linear-gradient(135deg,#123d35,#14372f);color:#ebfff7;box-shadow:0 8px 22px #13b98120}.withdraw-demo-success strong{color:#7cf0bd;font-size:14px}.withdraw-demo-success span{color:#e2fff2;font-size:13px;font-weight:750}.withdraw-demo-success small{color:#c1e7d7;font-size:11px;line-height:1.45}
        .withdraw-rollover{margin:18px 0 4px;padding:16px;border:1px solid #8e78e64a;border-radius:17px;background:linear-gradient(145deg,#16162e,#111426)}.withdraw-rollover__top{display:flex;justify-content:space-between;gap:12px;color:#f2efff;font-size:13px;font-weight:850}.withdraw-rollover__top span:last-child{color:<?= $rolloverComplete ? '#5df0ca' : '#ffd86d' ?>}.withdraw-rollover__track{height:9px;margin:11px 0 8px;overflow:hidden;border-radius:99px;background:#ffffff16}.withdraw-rollover__fill{height:100%;width:<?= number_format($rolloverProgress,2,'.','') ?>%;border-radius:inherit;background:linear-gradient(90deg,#8b6cff,#36e6bd);transition:width .35s}.withdraw-rollover p{margin:0;color:#c5c9df;font-size:12px;line-height:1.55}.withdraw-rollover small{display:block;margin-top:7px;color:#aeb7d4;font-size:11px}.withdraw-safe{display:flex;gap:10px;align-items:flex-start;margin-top:17px;padding:12px 13px;border:1px solid #36e6bd30;border-radius:14px;background:#0c352f55;color:#cdeee7;font-size:12px;line-height:1.5}.withdraw-safe svg{width:18px;height:18px;flex:0 0 18px;color:#5df0ca}.field .withdraw-error{color:#ffd2d9}
        @media(max-width:680px){.withdraw-rollover{margin-top:14px;padding:13px}.withdraw-safe{margin-top:13px}}
        .withdraw-empty{width:min(100%,620px);margin:0 auto;padding:clamp(25px,6vw,42px);text-align:center;border-color:#5cddbd69;background:radial-gradient(circle at 50% 0,#30d7aa27,transparent 52%),linear-gradient(145deg,#1b1b38,#101425);box-shadow:0 20px 55px #0006,0 0 35px #35e3bb18}.withdraw-empty__icon{display:grid;place-items:center;width:72px;height:72px;margin:0 auto 18px;border:1px solid #6cf0d58c;border-radius:22px;background:linear-gradient(145deg,#214f60,#193743);color:#83ffe0;box-shadow:0 0 26px #34d8bb45}.withdraw-empty__icon .ui-icon{width:34px;height:34px}.withdraw-empty__eyebrow{margin:0 0 8px;color:#8ef1da;font-size:11px;font-weight:900;letter-spacing:.16em}.withdraw-empty h2{margin:0;color:#fff;font-size:clamp(25px,7vw,36px);line-height:1.12}.withdraw-empty p{max-width:390px;margin:14px auto 0;color:#d9e6ee;font-size:15px;line-height:1.5}.withdraw-empty__balance{display:block;width:max-content;max-width:100%;margin:22px auto;padding:11px 19px;border:1px solid #71e8cf5c;border-radius:14px;background:#103b3c;color:#b8ffdf;font-size:22px;font-weight:900;font-variant-numeric:tabular-nums}.withdraw-empty .deposit-submit{display:inline-flex;align-items:center;justify-content:center;gap:9px;width:min(100%,340px);min-height:51px;margin:0 auto;text-decoration:none}.withdraw-empty .deposit-submit .ui-icon{width:19px;height:19px}.withdraw-empty__note{display:block;margin-top:16px;color:#a9bccc;font-size:11px}
    </style>
</head>
<body>
<?php $menuBase='../'; $menuLoggedIn=true; $menuCurrent='saque/'; require dirname(__DIR__) . '/components/menu.php'; ?>
<main class="deposit-page">
    <header class="deposit-heading"><span class="deposit-mark" aria-hidden="true"><?= ui_icon('withdraw') ?></span><div><p class="deposit-eyebrow">SUA CARTEIRA</p><h1>Solicitar saque</h1><p><?= $zeroBalance ? 'Consulte seu saldo antes de solicitar um saque.' : 'Informe o valor e a chave PIX para receber.' ?></p></div></header>
    <?php if ($zeroBalance): ?>
    <section class="deposit-card withdraw-empty" aria-label="Saque indisponível">
        <span class="withdraw-empty__icon" aria-hidden="true"><?= ui_icon('wallet') ?></span>
        <p class="withdraw-empty__eyebrow">SAQUE INDISPONÍVEL</p>
        <h2>Você tem zero de saldo.</h2>
        <p><?= $demoAccount ? 'Seu saldo de treino está zerado. Volte ao painel para acompanhar sua conta demo.' : 'Deposite para sacar.' ?></p>
        <strong class="withdraw-empty__balance">R$ 0,00</strong>
        <a class="deposit-submit" href="<?= app_escape(app_url($demoAccount ? 'painel/' : 'deposito/')) ?>"><?= ui_icon($demoAccount ? 'play' : 'deposit') ?> <?= $demoAccount ? 'Voltar ao painel' : 'Depositar agora' ?></a>
        <?php if ($notice !== ''): ?><small class="withdraw-empty__note" role="status"><?= app_escape($notice) ?></small><?php endif; ?>
    </section>
    <?php else: ?>
    <div class="deposit-layout">
        <section class="deposit-card" aria-label="Formulário de saque">
            <div class="wallet-summary"><div><span>Saldo disponível</span><strong>R$ <?= number_format($balance,2,',','.') ?></strong></div><span class="wallet-symbol" aria-hidden="true"><?= ui_icon('wallet') ?></span></div>
            <?php if($notice!==''): ?><?php if($demoSuccess): ?><div class="withdraw-demo-success" role="status"><strong>Dinheiro fictício enviado com sucesso!</strong><span>Valor demonstrativo: <?= app_escape($notice) ?></span><small>Simulação: nenhum PIX foi enviado e o saldo demo não foi alterado.</small></div><?php else: ?><div class="deposit-alert" role="status"><p><?= app_escape($notice) ?></p></div><?php endif; ?><?php endif; ?>
            <?php if(!$rolloverComplete): ?><div class="deposit-alert" role="status"><p>Complete o requisito de apostas exibido abaixo para liberar o saque.</p></div><?php elseif($balance<$minimum): ?><div class="deposit-alert" role="status"><p>Seu saldo ainda não alcançou o saque mínimo de R$ <?= number_format($minimum,2,',','.') ?>.</p></div><?php endif; ?>
            <div class="withdraw-rollover" aria-label="Progresso do requisito de apostas">
                <div class="withdraw-rollover__top"><span>Requisito de apostas<?= $rollover>0?' ('.$rollover.'x)':'' ?></span><span><?= $rolloverComplete?'Liberado':number_format($rolloverProgress,0,',','.').'%' ?></span></div>
                <div class="withdraw-rollover__track"><div class="withdraw-rollover__fill"></div></div>
                <p>Apostado: R$ <?= number_format((float)$user['total_apostado'],2,',','.') ?> · Necessário: R$ <?= number_format($rolloverRequired,2,',','.') ?></p>
                <?php if(!$rolloverComplete): ?><small>Faltam R$ <?= number_format(max(0,$rolloverRequired-(float)$user['total_apostado']),2,',','.') ?> para liberar.</small><?php else: ?><small>Requisito cumprido. Você já pode solicitar seu saque.</small><?php endif; ?>
            </div>
            <form action="<?= app_escape(app_url('saque/process.php')) ?>" method="post" autocomplete="on">
                <input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>"><input type="hidden" name="nonce" value="<?= app_escape($_SESSION['withdraw_nonce']) ?>">
                <div class="field"><label for="withdrawName">Nome do titular</label><input id="withdrawName" name="withdrawName" type="text" autocomplete="name" minlength="2" maxlength="120" placeholder="Nome completo do titular" value="<?= app_escape((string)($user['nome']??'')) ?>" required></div>
                <div class="field"><label for="withdrawCPF">CPF da chave PIX</label><input id="withdrawCPF" name="withdrawCPF" type="text" inputmode="numeric" autocomplete="off" maxlength="14" placeholder="000.000.000-00" required><small class="field-help">Use o CPF vinculado à chave PIX.</small></div>
                <div class="field"><label for="withdrawValue">Valor do saque</label><input id="withdrawValue" name="withdrawValue" type="number" inputmode="decimal" min="<?= app_escape(number_format($minimum,2,'.','')) ?>" max="<?= app_escape(number_format($balance,2,'.','')) ?>" step="0.01" placeholder="Mínimo R$ <?= number_format($minimum,2,',','.') ?>" required><small class="field-help">Valor mínimo: R$ <?= number_format($minimum,2,',','.') ?>.</small></div>
                <?php if($quickAmounts): ?><p class="quick-label">Escolha um valor rápido</p><div class="quick-amounts" aria-label="Valores sugeridos para saque"><?php foreach($quickAmounts as $quick): ?><button class="quick-amount" type="button" data-withdraw-amount="<?= (int)$quick ?>">R$ <?= (int)$quick ?></button><?php endforeach; ?></div><?php endif; ?>
                <button class="deposit-submit" type="submit" <?= $canRequest?'':'disabled' ?>><?= ui_icon('withdraw') ?> <?= $demoAccount ? 'Simular aprovação do saque' : 'Solicitar saque via PIX' ?></button>
            </form>
        </section>
        <aside class="deposit-aside"><span class="aside-icon" aria-hidden="true"><?= ui_icon('help') ?></span><h2>Como funciona</h2><?php if($demoAccount): ?><p>Modo demo: este teste mostra uma aprovacao ficticia. Nenhum PIX sera enviado e o saldo permanece igual.</p><?php else: ?><p>A equipe analisa a solicitacao manualmente. Depois da aprovacao, o PIX pode levar ate 24 horas para cair na chave informada.</p><?php endif; ?><ol class="deposit-steps"><li><span class="step-number">1</span><span>Informe o nome e a chave PIX do titular.</span></li><li><span class="step-number">2</span><span>Escolha um valor dentro do saldo disponivel.</span></li><li><span class="step-number">3</span><span><?= $demoAccount ? 'Veja o resultado da simulacao.' : 'Acompanhe a analise manual do pedido.' ?></span></li></ol><p class="deposit-footnote"><strong>Confira antes de enviar:</strong> nome, chave e valor. Consulte os <a href="<?= app_escape(app_url('legal/')) ?>">termos de uso</a>.</p></aside>
    </div>
    <?php endif; ?>
</main>
<script>
(()=>{const cpf=document.getElementById('withdrawCPF');const value=document.getElementById('withdrawValue');cpf?.addEventListener('input',()=>{const d=cpf.value.replace(/\D/g,'').slice(0,11);cpf.value=d.length>9?`${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9)}`:d.length>6?`${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6)}`:d.length>3?`${d.slice(0,3)}.${d.slice(3)}`:d});document.querySelectorAll('[data-withdraw-amount]').forEach(b=>b.addEventListener('click',()=>{value.value=b.dataset.withdrawAmount;value.dispatchEvent(new Event('input',{bubbles:true}))}))})();
</script>
</body>
</html>
