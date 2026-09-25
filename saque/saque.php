<?php
require_once __DIR__ . '/../app/bootstrap.php';
http_response_code(410);
header('Location: ' . app_url('saque/'));
exit;

/**
 * saque/saque.php — KnucklesPay Integration
 * Lógica de saque do usuário:
 *   - Valida rollover
 *   - Débita saldo
 *   - Registra na tabela saques (status "Processando")
 *   - Admin aprova manualmente pelo painel (/adm/saques/)
 */

error_reporting(0);

include './../conectarbanco.php';
$conn = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
if ($conn->connect_error) die('Conexão falhou: ' . $conn->connect_error);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$sessionEmail = $_SESSION['email'] ?? null;
if (!$sessionEmail) die('Sessão não encontrada.');

$withdrawName = trim($_GET['withdrawName'] ?? '');
$withdrawCPF  = trim($_GET['withdrawCPF']  ?? '');
$valor        = floatval($_GET['withdrawValue'] ?? 0);
$idTransaction = md5(uniqid(rand(), true));

// ----- Buscar saldo ------------------------------------------
$stmt = $conn->prepare("SELECT saldo FROM appconfig WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $sessionEmail);
$stmt->execute();
$stmt->bind_result($saldo);
$stmt->fetch();
$stmt->close();

// ----- Verificar rollover ------------------------------------
$rolloverRow     = $conn->query("SELECT rollover_saque FROM app LIMIT 1")->fetch_assoc();
$rolloverSaque   = floatval($rolloverRow['rollover_saque'] ?? 0);

$stmt2 = $conn->prepare("SELECT total_apostado, depositou FROM appconfig WHERE email = ? LIMIT 1");
$stmt2->bind_param('s', $sessionEmail);
$stmt2->execute();
$stmt2->bind_result($totalApostado, $depositou);
$stmt2->fetch();
$stmt2->close();

$rolloverNecessario = floatval($depositou) * $rolloverSaque;
$rolloverCumprido   = (floatval($totalApostado) >= $rolloverNecessario);

// ----- Rollover pendente ------------------------------------
if (!$rolloverCumprido && $saldo >= $valor && $valor > 0) {
    $falta = $rolloverNecessario - floatval($totalApostado);
    $pct   = $rolloverNecessario > 0 ? min(100, (floatval($totalApostado) / $rolloverNecessario) * 100) : 100;
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Rollover Pendente</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>html,body{height:100%}body{display:flex;justify-content:center;align-items:center;background:linear-gradient(45deg,#667eea,#764ba2,#6b8dd6,#8e37d7);background-size:400% 400%;animation:g 15s ease infinite}@keyframes g{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}</style></head>
    <body><div class="text-center max-w-md">
    <h1 class="text-3xl font-bold text-white mb-4">Rollover pendente!</h1>
    <p class="text-white mb-4">Você precisa apostar mais antes de sacar.</p>
    <div class="bg-gray-700 rounded-full h-6 mb-4 overflow-hidden"><div class="h-full rounded-full" style="width:' . number_format($pct, 0) . '%;background-color:' . ($pct >= 100 ? '#4caf50' : '#ff9800') . '"></div></div>
    <p class="text-white mb-2">Apostado: R$ ' . number_format(floatval($totalApostado), 2, ',', '.') . ' / Necessário: R$ ' . number_format($rolloverNecessario, 2, ',', '.') . ' (' . number_format($pct, 0) . '%)</p>
    <p class="text-yellow-300 mb-6">Falta apostar R$ ' . number_format($falta, 2, ',', '.') . '</p>
    <a href="../saque/" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Voltar ao saque</a>
    </div></body></html>';
    exit();
}

// ----- Saldo insuficiente ------------------------------------
if ($saldo < $valor || $valor <= 0) {
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Saldo Insuficiente</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>html,body{height:100%}body{display:flex;justify-content:center;align-items:center;background:linear-gradient(45deg,#667eea,#764ba2);}</style></head>
    <body><div class="text-center">
    <h1 class="text-4xl font-bold text-white mb-8">Saque não realizado!</h1>
    <p class="text-white mb-4">Saldo insuficiente ou valor inválido.</p>
    <a href="../saque/" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg">Voltar ao saque</a>
    </div></body></html>';
    exit();
}

// ----- Processamento do saque --------------------------------
if ($saldo >= $valor && $valor > 0 && $rolloverCumprido) {

    // Débita saldo imediatamente
    $stmtDebit = $conn->prepare("UPDATE appconfig SET saldo = saldo - ? WHERE email = ?");
    $stmtDebit->bind_param('ds', $valor, $sessionEmail);
    $stmtDebit->execute();
    $stmtDebit->close();

    date_default_timezone_set('America/Sao_Paulo');
    $dataHora = date('d-m-Y H:i:s');

    // Registra com status "Processando" — admin aprova no painel
    $status    = 'Processando';
    $chavePix  = $withdrawCPF;

    $stmtIns = $conn->prepare(
        "INSERT INTO saques (email, externalreference, destino, chavepix, data, valor, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmtIns->bind_param('ssssdss', $sessionEmail, $idTransaction, $withdrawName, $chavePix, $dataHora, $valor, $status);
    $stmtIns->execute();
    $stmtIns->close();

    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="w-mod-js w-mod-ix wf-spacemono-n4-active wf-spacemono-n7-active wf-active">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <script disable-devtool-auto src="https://cdn.jsdelivr.net/npm/disable-devtool@latest"></script>
    <title>Saque Solicitado</title>
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="./arquivos/page.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="./arquivos/css" media="all">
    <link rel="icon" type="image/x-icon" href="../img/logo.png">
</head>
<body>
    <div>
        <section id="hero" class="hero-section dark wf-section">
            <style>
                div.escudo { display:block; width:247px; line-height:65px; font-size:12px; margin:-60px 0 0 0;
                    background-image:url(./arquivos/escudo-branco.png); background-size:contain;
                    background-repeat:no-repeat; background-position:center;
                    filter:drop-shadow(1px 1px 3px #00000099); }
                div.escudo img { width:50px; margin:-10px 6px 0 0; }
                .win-warn { color:#22C55E; }
                .cadastro-btn { display:inline-block; margin-top:20px; padding:16px 40px;
                    border:4px solid #1f2024; border-radius:8px; background-color:#1fbffe;
                    box-shadow:-3px 3px 0 0 #1f2024; color:#fff; font-size:1.25em;
                    text-align:center; letter-spacing:.12em; cursor:pointer; text-decoration:none; }
            </style>
            <div class="minting-container w-container" style="margin-top:-20%">
                <div class="escudo"><img src="arquivos/trophy.gif"></div>
                <h2>SAQUE SOLICITADO!</h2>
                <p class="win-warn">
                    <strong>Saque de R$ <?= number_format($valor, 2, ',', '.') ?> registrado com sucesso!<br>
                    Será processado em breve pela equipe.</strong>
                </p>
                <a href="../painel/" class="cadastro-btn">VOLTAR</a>
            </div>
        </section>
    </div>
</body>
</html>
