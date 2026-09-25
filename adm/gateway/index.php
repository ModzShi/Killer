<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Location: ' . app_url('adm/gateway/bxpay.php'), true, 303);
exit;

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['emailadm'])) {
    header('Location: ../login');
    exit();
}
include './bd.php';
include '../../conectarbanco.php';
$conn = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
$row  = $conn->query("SELECT * FROM app LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html dir="ltr" lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Gateway - KnucklesPay</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon.png">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/libs/flot/css/float-chart.css" rel="stylesheet">
    <link href="../dist/css/style.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <style>
        .ep-card { border:none; border-radius:15px; padding:20px; margin:16px 0;
            box-shadow:rgba(149,157,165,.2) 0 8px 24px; background:#fff; }
        .ep-input { width:100%; min-height:42px; background:#f5f5f5; border:1px solid #ddd;
            border-radius:10px; padding:8px 15px; font-size:14px; box-sizing:border-box; }
        .ep-input:focus { outline:none; border-color:#1fbffe; background:#fff; }
        .ep-btn { padding:10px 28px; border:none; border-radius:10px; background:#1fbffe;
            color:#fff; font-size:15px; font-weight:600; cursor:pointer; }
        .ep-btn:hover { background:#18aee5; }
        .ep-label { font-weight:600; font-size:13px; color:#555; margin-bottom:6px; display:block; }
        .ep-hint { font-size:12px; color:#888; margin-top:4px; }
        .ep-badge { display:inline-block; padding:3px 10px; border-radius:20px;
            font-size:11px; font-weight:700; background:#d4edda; color:#155724; }
        #alert-box { display:none; padding:12px 18px; border-radius:8px; margin:16px 0; font-size:14px; }
        .alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .alert-danger  { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    </style>
</head>
<body>
<div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5"
     data-sidebartype="full" data-sidebar-position="absolute"
     data-header-position="absolute" data-boxed-layout="full">

    <header class="topbar" data-navbarbg="skin5">
        <nav class="navbar top-navbar navbar-expand-md navbar-dark">
            <div class="navbar-header" data-logobg="skin5">
                <a class="navbar-brand" href="../">
                    <b class="logo-icon ps-2">
                        <span class="text-white font-20 font-weight-bold">Admin Dashboard</span>
                    </b>
                </a>
                <a class="nav-toggler waves-effect waves-light d-block d-md-none" href="javascript:void(0)">
                    <i class="ti-menu ti-close"></i>
                </a>
            </div>
        </nav>
    </header>

    <?php include '../components/aside.php' ?>

    <div class="page-wrapper">
        <div class="container-fluid" style="padding:30px;">

            <!-- Cabeçalho -->
            <div class="d-flex align-items-center mb-4">
                <div>
                    <h3 style="margin:0;font-weight:700;">Gateway de Pagamento</h3>
                    <p style="margin:4px 0 0;color:#888;font-size:14px;">
                        Configuração da integração com
                        <strong style="color:#1fbffe;">KnucklesPay</strong>
                        <span class="ep-badge ml-2">Ativo</span>
                    </p>
                </div>
            </div>

            <div class="ep-card">
                <strong>BX Pay</strong>
                <p>Prepare sua conta BX Pay e teste a conexão com suas credenciais.</p>
                <a class="ep-btn" href="bxpay.php">Configurar BX Pay →</a>
            </div>
            <div id="alert-box"></div>

            <!-- Formulário de configuração -->
            <form id="gateway-form">
                <div class="row">

                    <!-- Client ID -->
                    <div class="col-md-6">
                        <div class="ep-card">
                            <label class="ep-label">🔑 Client ID</label>
                            <input type="text" class="ep-input" id="input-client-id"
                                   value="<?= htmlspecialchars($client_id ?? '') ?>"
                                   placeholder="Seu Client ID da KnucklesPay">
                            <p class="ep-hint">Encontre em: KnucklesPay → API → Credenciais</p>
                        </div>
                    </div>

                    <!-- Client Secret -->
                    <div class="col-md-6">
                        <div class="ep-card">
                            <label class="ep-label">🔐 Client Secret</label>
                            <input type="password" class="ep-input" id="input-client-secret"
                                   value="<?= htmlspecialchars($client_secret ?? '') ?>"
                                   placeholder="Seu Client Secret da KnucklesPay">
                            <p class="ep-hint">Nunca exponha este valor em código client-side</p>
                        </div>
                    </div>

                    <!-- Webhook Secret -->
                    <div class="col-md-6">
                        <div class="ep-card">
                            <label class="ep-label">🔏 Webhook Secret (HMAC)</label>
                            <input type="text" class="ep-input" id="input-webhook-secret"
                                   value="<?= htmlspecialchars($webhook_secret ?? '') ?>"
                                   placeholder="Secret para validar assinatura HMAC (opcional)">
                            <p class="ep-hint">Ative em: KnucklesPay → Keys → Ativar Assinatura de Webhook</p>
                        </div>
                    </div>

                </div>

                <!-- URL do Webhook (somente leitura) -->
                <div class="ep-card" style="background:#f8f9fa;">
                    <label class="ep-label">📬 URL do Webhook (configure na KnucklesPay)</label>
                    <div class="d-flex align-items-center">
                        <input type="text" class="ep-input" id="webhook-url"
                               value="<?= isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ?>://<?= $_SERVER['HTTP_HOST'] ?>/webhook/pix.php"
                               readonly style="background:#e9ecef;cursor:default;">
                        <button type="button" class="ep-btn ml-2" onclick="copiarWebhook()">Copiar</button>
                    </div>
                    <p class="ep-hint">Cole esta URL no campo Webhook da KnucklesPay para receber notificações de pagamento</p>
                </div>

                <div class="mt-3">
                    <button type="submit" class="ep-btn">💾 Salvar Configurações</button>
                    <button type="button" class="btn btn-outline-secondary ml-2" onclick="testarConexao()">
                        🔍 Testar Conexão
                    </button>
                </div>
            </form>

        </div>
    </div><!-- /page-wrapper -->
</div>

<script>
    $('#gateway-form').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: 'bd.php',
            type: 'POST',
            data: {
                client_id:      $('#input-client-id').val(),
                client_secret:  $('#input-client-secret').val(),
                webhook_secret: $('#input-webhook-secret').val()
            },
            success: function(r) {
                showAlert(r.includes('Sucesso') || r.includes('sucesso') ? 'success' : 'danger', r);
            },
            error: function() {
                showAlert('danger', 'Erro ao salvar. Verifique o console.');
            }
        });
    });

    function testarConexao() {
        showAlert('success', '⏳ Testando conexão com a KnucklesPay...');
        $.post('bd.php', { action: 'test' }, function(r) {
            showAlert(r.includes('OK') ? 'success' : 'danger', r);
        });
    }

    function copiarWebhook() {
        var el = document.getElementById('webhook-url');
        el.select();
        document.execCommand('copy');
        alert('URL do webhook copiada!');
    }

    function showAlert(type, msg) {
        var box = $('#alert-box');
        box.removeClass('alert-success alert-danger').addClass('alert-' + type).text(msg).show();
        setTimeout(function() { box.fadeOut(); }, 5000);
    }
</script>

</body>
</html>
