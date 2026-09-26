<?php
require_once __DIR__ . '/../app/auth.php';
require_once dirname(__DIR__) . '/payments/bxpay_service.php';

if (empty($_SESSION['email'])) {
    header('Location: ' . app_url('login/'), true, 303);
    exit;
}

$email = (string) $_SESSION['email'];
$db = app_db();
$user = app_query($db, 'SELECT saldo, demo FROM appconfig WHERE email=? LIMIT 1', [$email])->get_result()->fetch_assoc();
if (!$user) {
    $db->close();
    header('Location: ' . app_url('logout.php'), true, 303);
    exit;
}
if ((string) ($user['demo'] ?? '0') === '1') {
    $db->close();
    header('Location: ' . app_url('painel/'), true, 303);
    exit;
}

$app = $db->query('SELECT deposito_min FROM app LIMIT 1')->fetch_assoc() ?: [];
$minimum = max(5.0, (float) ($app['deposito_min'] ?? 5));
$useBXPay = bxpay_enabled($db);
$balance = (float) $user['saldo'];
$db->close();

$_SESSION['deposit_csrf'] = $_SESSION['deposit_csrf'] ?? bin2hex(random_bytes(32));
$errors = [];

function deposit_cpf_is_valid(string $cpf): bool {
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    $sum = 0;
    for ($i = 0; $i < 9; $i++) $sum += (int) $cpf[$i] * (10 - $i);
    $digit = ($sum * 10) % 11;
    if ($digit === 10) $digit = 0;
    if ($digit !== (int) $cpf[9]) return false;
    $sum = 0;
    for ($i = 0; $i < 10; $i++) $sum += (int) $cpf[$i] * (11 - $i);
    $digit = ($sum * 10) % 11;
    if ($digit === 10) $digit = 0;
    return $digit === (int) $cpf[10];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
    $cpfInput = is_string($_POST['document'] ?? null) ? trim($_POST['document']) : '';
    $cpf = preg_replace('/\D+/', '', $cpfInput) ?? '';
    $amountInput = is_string($_POST['valor_transacao'] ?? null) ? trim($_POST['valor_transacao']) : '';
    $amount = is_numeric($amountInput) ? (float) $amountInput : 0.0;

    if (!hash_equals($_SESSION['deposit_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    }
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($nameLength < 2 || $nameLength > 120) $errors[] = 'Informe o nome do titular (2 a 120 caracteres).';
    if (!deposit_cpf_is_valid($cpf)) $errors[] = 'Confira o CPF. Ele precisa ser válido e conter 11 números.';
    if (!is_finite($amount) || $amount < $minimum || $amount > 9999999999.99 || abs($amount - round($amount, 2)) > 0.000001) {
        $errors[] = 'Informe um valor válido, com até duas casas decimais e acima do mínimo.';
    }
    if (!$useBXPay) $errors[] = 'Depósitos temporariamente indisponíveis. A integração de pagamento precisa ser ativada pelo administrador.';

    if (!$errors) {
        $nome = $name;
        $valor = $amount;
        $conn = app_db();
        require __DIR__ . '/bxpay_create.php';
        exit;
    }
}

$sessionErrors = $_SESSION['dep_errors'] ?? [];
unset($_SESSION['dep_errors']);
$errors = array_merge($errors, is_array($sessionErrors) ? $sessionErrors : []);
$quickAmounts = array_values(array_filter([25, 50, 100, 200], static fn($value) => $value >= $minimum));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#090b1a">
    <title>Depositar | Subway Run</title>
    <link rel="stylesheet" href="<?= app_escape(app_url('arquivos/menu.css')) ?>?v=<?= filemtime(dirname(__DIR__) . '/arquivos/menu.css') ?>">
    <style>
        :root{color-scheme:dark;--bg:#090b1a;--panel:#11152a;--panel2:#171333;--line:#a78bfa35;--muted:#bac2df;--white:#f8f8ff;--mint:#36e6bd;--gold:#ffd86d}
        *{box-sizing:border-box}html{min-height:100%;background:var(--bg)}body{min-height:100vh;margin:0;color:var(--white);font-family:Inter,"Segoe UI",Arial,sans-serif;background:radial-gradient(ellipse at 50% 0,#38206b85 0,transparent 47%),radial-gradient(ellipse at 100% 58%,#14395755 0,transparent 36%),var(--bg)}
        .deposit-page{width:calc(100% - 32px);max-width:920px;margin:0 auto;padding:clamp(28px,5vw,56px) 0 72px}.deposit-heading{display:flex;align-items:center;gap:16px;margin:4px 0 24px}.deposit-mark{display:grid;place-items:center;flex:0 0 56px;width:56px;height:56px;border:1px solid #8d72f588;border-radius:18px;background:linear-gradient(145deg,#33246b,#14203a);color:#c9b9ff;box-shadow:0 10px 28px #6c45ff25}.deposit-mark svg{width:27px;height:27px}.deposit-eyebrow{margin:0 0 4px;color:#8eead7;font-size:11px;font-weight:900;letter-spacing:.16em}.deposit-heading h1{margin:0;color:#fff;font-size:clamp(26px,5vw,36px);line-height:1.08;letter-spacing:-.045em}.deposit-heading p{margin:7px 0 0;color:var(--muted);font-size:14px;line-height:1.45}
        .deposit-layout{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(230px,.75fr);gap:18px;align-items:start}.deposit-card,.deposit-aside{border:1px solid var(--line);border-radius:24px;background:linear-gradient(145deg,#17172ff2,#101426f5);box-shadow:0 20px 55px #0005,inset 0 1px #ffffff0c}.deposit-card{padding:clamp(18px,4vw,30px)}.wallet-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:22px;padding:15px 17px;border:1px solid #34dfbc38;border-radius:17px;background:linear-gradient(110deg,#0c352f,#102b37 75%,#171d3b);}.wallet-summary span{display:block;color:#b6d8d6;font-size:10px;font-weight:850;letter-spacing:.13em;text-transform:uppercase}.wallet-summary strong{display:block;margin-top:3px;color:#5df0ca;font-size:clamp(22px,5vw,27px);font-variant-numeric:tabular-nums}.wallet-symbol{display:grid;place-items:center;width:42px;height:42px;border:1px solid #56ebc755;border-radius:14px;background:#28dbb018;color:#6cf1d1}.wallet-symbol svg{width:22px;height:22px}
        .field{margin-top:17px}.field label{display:block;margin:0 0 7px;color:#e7eaff;font-size:12px;font-weight:850;letter-spacing:.035em}.field input{display:block;width:100%;height:49px;padding:0 14px;border:1px solid #a78bfa50;border-radius:13px;outline:none;background:#0b1022;color:#fff;font-family:inherit;font-size:15px;font-weight:600;line-height:1.2;box-shadow:inset 0 1px 8px #0003;transition:border-color .18s,box-shadow .18s}.field input::placeholder{color:#9099b8;opacity:1}.field input:focus{border-color:#8d74ff;box-shadow:0 0 0 3px #8d74ff2a}.field input[type=number]{font-variant-numeric:tabular-nums}.field-help{display:block;margin-top:6px;color:#aeb7d4;font-size:11px;line-height:1.4}
        .quick-label{margin:18px 0 9px;color:#c4c9df;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.quick-amounts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.quick-amount{min-width:0;min-height:42px;border:1px solid #8e78e658;border-radius:12px;background:linear-gradient(145deg,#201d45,#171b34);color:#f1edff;font-size:13px;font-weight:850;cursor:pointer;transition:transform .16s,border-color .16s,background .16s}.quick-amount:hover,.quick-amount[aria-pressed=true]{transform:translateY(-1px);border-color:#45e6c2;background:linear-gradient(145deg,#183c43,#20214b);box-shadow:0 5px 18px #32dfb320}.deposit-submit{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;min-height:52px;margin-top:20px;border:1px solid #a4ffe6;border-radius:15px;background:linear-gradient(105deg,#4df0c2,#16d7aa 52%,#75efa4);color:#062b26;font-size:15px;font-weight:950;letter-spacing:.01em;box-shadow:0 5px 0 #08765f,0 12px 24px #17d4a533;cursor:pointer;transition:transform .16s,filter .16s,box-shadow .16s}.deposit-submit:hover{transform:translateY(-2px);filter:brightness(1.06)}.deposit-submit:active{transform:translateY(2px);box-shadow:0 2px 0 #08765f}.deposit-submit:disabled{opacity:.52;cursor:not-allowed}.deposit-submit svg{width:19px;height:19px}
        .deposit-alert{margin-bottom:16px;padding:12px 14px;border:1px solid #ff7b8b66;border-radius:13px;background:#4a1f32;color:#ffe0e5;font-size:13px;line-height:1.5}.deposit-alert p{margin:0}.deposit-alert p+p{margin-top:4px}.deposit-aside{padding:21px}.aside-icon{display:grid;place-items:center;width:42px;height:42px;border:1px solid #ffd86d55;border-radius:14px;background:#ffd86d13;color:var(--gold)}.aside-icon svg{width:22px;height:22px}.deposit-aside h2{margin:14px 0 5px;font-size:17px;letter-spacing:-.02em}.deposit-aside>p{margin:0;color:var(--muted);font-size:12px;line-height:1.55}.deposit-steps{display:grid;gap:12px;margin:20px 0 0;padding:0;list-style:none}.deposit-steps li{display:grid;grid-template-columns:26px minmax(0,1fr);gap:9px;align-items:start;color:#d8ddf2;font-size:12px;line-height:1.45}.step-number{display:grid;place-items:center;width:25px;height:25px;border:1px solid #9578ff55;border-radius:9px;background:#7957ee1c;color:#d2c3ff;font-size:11px;font-weight:900}.deposit-footnote{margin:17px 0 0;padding-top:14px;border-top:1px solid #ffffff15;color:#aeb7d4;font-size:11px;line-height:1.5}.deposit-footnote strong{color:#e8eaff}
        @media(max-width:680px){.deposit-page{width:calc(100% - 24px);max-width:500px;padding:25px 0 40px}.deposit-heading{gap:12px;margin-bottom:17px}.deposit-mark{flex-basis:48px;width:48px;height:48px;border-radius:15px}.deposit-layout{grid-template-columns:1fr;gap:12px}.deposit-card{padding:16px;border-radius:20px}.deposit-aside{padding:16px;border-radius:18px}.deposit-aside h2{margin-top:10px}.deposit-steps{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:13px}.deposit-steps li{grid-template-columns:22px minmax(0,1fr);gap:6px;font-size:10px}.step-number{width:22px;height:22px}.deposit-footnote{margin-top:13px;padding-top:11px}.wallet-summary{margin-bottom:15px;padding:12px 14px}.field{margin-top:13px}.field input{height:46px;font-size:16px}.quick-amount{min-height:40px}}
        @media(max-width:370px){.deposit-page{width:calc(100% - 20px)}.deposit-card{padding:13px}.quick-amounts{grid-template-columns:repeat(2,minmax(0,1fr))}.deposit-steps{grid-template-columns:1fr}.deposit-steps li{font-size:11px}}
        @media(prefers-reduced-motion:reduce){*,*:before,*:after{scroll-behavior:auto!important;transition:none!important}}
    </style>
</head>
<body>
<?php $menuBase = '../'; $menuLoggedIn = true; $menuCurrent = 'deposito/'; require dirname(__DIR__) . '/components/menu.php'; ?>
<main class="deposit-page">
    <header class="deposit-heading">
        <span class="deposit-mark" aria-hidden="true"><?= ui_icon('deposit') ?></span>
        <div><p class="deposit-eyebrow">SUA CARTEIRA</p><h1>Adicionar saldo</h1><p>Escolha o valor e continue pelo PIX.</p></div>
    </header>
    <div class="deposit-layout">
        <section class="deposit-card" aria-label="Formulário de depósito">
            <div class="wallet-summary"><div><span>Saldo disponível</span><strong>R$ <?= number_format($balance, 2, ',', '.') ?></strong></div><span class="wallet-symbol" aria-hidden="true"><?= ui_icon('wallet') ?></span></div>
            <?php if ($errors): ?><div class="deposit-alert" role="alert"><?php foreach ($errors as $error): ?><p><?= app_escape($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            <?php if (!$useBXPay): ?><div class="deposit-alert" role="status"><p>Depósitos estão indisponíveis no momento. A integração de pagamento precisa ser ativada pelo administrador.</p></div><?php endif; ?>
            <form action="<?= app_escape(app_url('deposito/')) ?>" method="post" autocomplete="on">
                <input type="hidden" name="csrf" value="<?= app_escape($_SESSION['deposit_csrf']) ?>">
                <div class="field"><label for="name">Nome do titular</label><input id="name" name="name" type="text" autocomplete="name" minlength="2" maxlength="120" placeholder="Como aparece no documento" value="<?= app_escape(is_string($_POST['name'] ?? null) ? $_POST['name'] : '') ?>" required></div>
                <div class="field"><label for="document">CPF</label><input id="document" name="document" type="text" inputmode="numeric" autocomplete="off" maxlength="14" placeholder="000.000.000-00" value="<?= app_escape(is_string($_POST['document'] ?? null) ? $_POST['document'] : '') ?>" required><small class="field-help">Usado para identificar o titular do pagamento.</small></div>
                <div class="field"><label for="valuedeposit">Valor do depósito</label><input id="valuedeposit" name="valor_transacao" type="number" inputmode="decimal" min="<?= app_escape(number_format($minimum, 2, '.', '')) ?>" step="0.01" placeholder="Mínimo R$ <?= number_format($minimum, 2, ',', '.') ?>" value="<?= app_escape(is_string($_POST['valor_transacao'] ?? null) ? $_POST['valor_transacao'] : '') ?>" required><small class="field-help">Valor mínimo: R$ <?= number_format($minimum, 2, ',', '.') ?>.</small></div>
                <?php if ($quickAmounts): ?><p class="quick-label">Escolha um valor rápido</p><div class="quick-amounts" aria-label="Valores sugeridos"><?php foreach ($quickAmounts as $quick): ?><button class="quick-amount" type="button" data-amount="<?= (int) $quick ?>" aria-pressed="false">R$ <?= (int) $quick ?></button><?php endforeach; ?></div><?php endif; ?>
                <button class="deposit-submit" type="submit" <?= $useBXPay ? '' : 'disabled' ?>><?= ui_icon('shield') ?> Continuar para o PIX</button>
            </form>
        </section>
        <aside class="deposit-aside">
            <span class="aside-icon" aria-hidden="true"><?= ui_icon('help') ?></span>
            <h2>Como funciona</h2>
            <p>Gere o código PIX e conclua o pagamento no aplicativo do seu banco.</p>
            <ol class="deposit-steps"><li><span class="step-number">1</span><span>Informe seus dados e o valor.</span></li><li><span class="step-number">2</span><span>Copie o código PIX gerado.</span></li><li><span class="step-number">3</span><span>Após a confirmação, o saldo aparece no painel.</span></li></ol>
            <p class="deposit-footnote"><strong>Antes de pagar:</strong> confira o valor e os dados exibidos pelo seu banco.</p>
        </aside>
    </div>
</main>
<script>
(() => {
    const cpf = document.getElementById('document');
    const amount = document.getElementById('valuedeposit');
    cpf?.addEventListener('input', () => {
        const digits = cpf.value.replace(/\D/g, '').slice(0, 11);
        let formatted = digits;
        if (digits.length > 9) formatted = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
        else if (digits.length > 6) formatted = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
        else if (digits.length > 3) formatted = `${digits.slice(0, 3)}.${digits.slice(3)}`;
        cpf.value = formatted;
    });
    document.querySelectorAll('[data-amount]').forEach((button) => button.addEventListener('click', () => {
        amount.value = button.dataset.amount;
        document.querySelectorAll('[data-amount]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
        amount.dispatchEvent(new Event('input', { bubbles: true }));
    }));
})();
</script>
</body>
</html>
