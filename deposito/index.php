<?php
require_once __DIR__ . '/../app/bootstrap.php';

/* ============================================================
 * deposito/index.php — KnucklesPay Integration
 * ============================================================ */

// ----- Dados do app -----------------------------------------
include './../conectarbanco.php';
$conn = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
if ($conn->connect_error) die('Conexão falhou: ' . $conn->connect_error);

$row = $conn->query("SELECT nome_unico, nome_um, nome_dois, deposito_min FROM app LIMIT 1")->fetch_assoc();
$nomeUnico   = $row['nome_unico'] ?? '';
$nomeUm      = $row['nome_um']   ?? '';
$nomeDois    = $row['nome_dois'] ?? '';
$depositoMin = floatval($row['deposito_min'] ?? 2);
require_once dirname(__DIR__) . '/payments/bxpay_service.php';
$useBXPay = bxpay_enabled($conn);
$depositoMin = max(5, $depositoMin);
$conn->close();

// ----- Sessão -----------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['email'])) {
    header('Location: ../login');
    exit();
}
$email = $_SESSION['email'];
require_once dirname(__DIR__) . '/app/auth.php';
$demoCheck = app_db();
$demoRow = app_query($demoCheck,'SELECT demo FROM appconfig WHERE email=?',[$email])->get_result()->fetch_assoc();
if($demoRow && (string)($demoRow['demo']??'0')==='1') { header('Location: '.app_url('painel/'),true,303); exit; }
$_SESSION['deposit_csrf'] = $_SESSION['deposit_csrf'] ?? bin2hex(random_bytes(32));

// ----- Processar POST ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome  = trim($_POST['name']            ?? '');
    $cpf   = trim($_POST['document']        ?? '');
    $valor = floatval($_POST['valor_transacao'] ?? 0);

    $errors = [];
    if (!is_finite($valor) || $valor <= 0 || $valor > 9999999999.99 || abs($valor - round($valor, 2)) > 0.000001) $errors[] = 'Informe um valor válido com até duas casas decimais.';
    if (empty($nome))          $errors[] = 'O nome é obrigatório.';
    if (empty($cpf))           $errors[] = 'O CPF é obrigatório.';
    if ($valor < $depositoMin) $errors[] = 'Valor mínimo: R$ ' . number_format($depositoMin, 2, ',', '.');

    if (!empty($errors)) {
        $_SESSION['dep_errors'] = $errors;
        header('Location: ../deposito/');
        exit();
    }

    include './../conectarbanco.php';
    $conn = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);

    if (!$useBXPay) {
        $_SESSION['dep_errors'] = ['Depósitos indisponíveis. Configure e ative a BX Pay no painel administrativo.'];
        header('Location: ' . app_url('deposito/'), true, 303);
        exit;
    }
    require __DIR__ . '/bxpay_create.php';
    exit;

}

// Erros de sessão
$sessionErrors = $_SESSION['dep_errors'] ?? [];
unset($_SESSION['dep_errors']);
?>
<!DOCTYPE html>
<html lang="pt-br" class="w-mod-js w-mod-ix wf-spacemono-n4-active wf-spacemono-n7-active wf-active">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <title><?= htmlspecialchars($nomeUnico) ?> 🌊</title>
    <meta property="og:image" content="../img/logo.png">
    <meta content="<?= htmlspecialchars($nomeUnico) ?> 🌊" property="og:title">
    <meta name="twitter:image" content="../img/logo.png">
    <meta content="<?= htmlspecialchars($nomeUnico) ?> 🌊" property="twitter:title">
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="arquivos/page.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="arquivos/css" media="all">
    <link rel="apple-touch-icon" sizes="180x180" href="../img/logo.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../img/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../img/logo.png">
    <script type="text/javascript">
        WebFont.load({ google: { families: ["Space Mono:regular,700"] } });
    </script>
    <?php include '../pixels.php'; ?>
</head>
<body>
    <?php include '../pixels.php'; ?>
    <div>
        <?php $menuBase = '../'; $menuLoggedIn = true; $menuCurrent = 'deposito/'; include __DIR__ . '/../components/menu.php'; ?>

        <section id="hero" class="hero-section dark wf-section">
            <div class="minting-container w-container">
                <div class="finance-emblem" role="img" aria-label="Depósito PIX"><span class="finance-orbit"></span><img src="<?= app_escape(app_url('arquivos/icons/wallet-cards.svg')) ?>" alt="" width="72" height="72"><span class="finance-badge" aria-hidden="true">↓</span></div>
                <h2>Depósito via PIX</h2>
                <p>Depósitos instantâneos com praticidade.</p>
                <?php if (!$useBXPay): ?><div class="notice notice-error" role="status">Depósitos temporariamente indisponíveis. A BX Pay ainda não foi ativada pelo administrador.</div><?php endif; ?>

                <?php if (!empty($sessionErrors)): ?>
                    <div style="background:#ff4444;color:#fff;padding:12px 20px;border-radius:8px;margin-bottom:16px;">
                        <?php foreach ($sessionErrors as $e): ?>
                            <p style="margin:4px 0;"><?= htmlspecialchars($e) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="./" method="POST"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['deposit_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="properties">
                        <h4 class="rarity-heading">NOME</h4>
                        <div class="rarity-row roboto-type2">
                            <input class="large-input-field w-input" type="text" placeholder="Seu nome"
                                id="name" name="name" required>
                        </div>
                        <h4 class="rarity-heading">CPF</h4>
                        <div class="rarity-row roboto-type2">
                            <input class="large-input-field w-input" maxlength="14"
                                placeholder="Seu número de CPF" type="text" id="document"
                                name="document" oninput="formatarCPF(this)" required>
                        </div>
                        <h4 class="rarity-heading">Valor para depósito</h4>
                        <div class="rarity-row roboto-type2">
                            <input type="number" class="large-input-field w-input" name="valor_transacao"
                                id="valuedeposit"
                                placeholder="Depósito mínimo de R$ <?= number_format($depositoMin, 2, ',', '.') ?>"
                                required min="<?= $depositoMin ?>" step="0.01">
                        </div>
                    </div>

                    <div class="button-container">
                        <button style="width:105px;height:65px;" type="button" class="button nav w-button"
                            onclick="updateValue(25)">R$25</button>
                        <button style="width:105px;height:65px;" type="button" class="button nav w-button"
                            onclick="updateValue(30)">R$30</button>
                        <br><br>
                        <button style="width:105px;height:65px;" type="button" class="button nav w-button"
                            onclick="updateValue(50)">R$50</button>
                        <button style="width:105px;height:65px;" type="button" class="button nav w-button"
                            onclick="updateValue(100)">R$100</button>
                        <br><br>
                    </div>

                    <input type="submit" id="submitButton" name="gerar_qr_code"
                        value="Depositar via PIX" class="primary-button w-button" <?= $useBXPay ? '' : 'disabled' ?>>
                </form>
            </div>
        </section>

        <div class="intermission wf-section"></div>

        <div id="about" class="comic-book white wf-section">
            <div class="minting-container left w-container">
                <div class="w-layout-grid grid-2">
                    <img src="arquivos/money.png" loading="lazy" width="240" alt="" class="mint-card-image v2">
                    <div>
                        <h2>Indique um amigo e ganhe R$ no PIX</h2>
                        <h3>Como funciona?</h3>
                        <p>Convide seus amigos que ainda não estão na plataforma. Você receberá por cada amigo que
                            se inscrever e fizer um depósito.</p>
                        <h3>Como recebo o dinheiro?</h3>
                        <p>O saldo é adicionado diretamente ao seu saldo no painel, com o qual você pode sacar via PIX.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-section wf-section">
            <div class="domo-text"><?= htmlspecialchars($nomeUm) ?></div>
            <div class="domo-text purple"><?= htmlspecialchars($nomeDois) ?></div>
            <div class="follow-test">© Copyright. Todos os direitos reservados.</div>
            <div class="follow-test">
                <a href="/legal"><strong class="bold-white-link">Termos de uso</strong></a>
            </div>
            <div class="follow-test">contato@<?= strtolower(str_replace(' ', '', htmlspecialchars($nomeUnico))) ?>.com</div>
        </div>
    </div>

    <script>
        function formatarCPF(input) {
            var v = input.value.replace(/\D/g, '');
            v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            input.value = v;
        }
        function updateValue(value) {
            document.getElementById('valuedeposit').value = value;
        }
    </script>
</body>
</html>
