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
    <link rel="stylesheet" href="<?= app_escape(app_url('arquivos/wallet.css')) ?>?v=<?= filemtime(dirname(__DIR__) . '/arquivos/wallet.css') ?>">
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
