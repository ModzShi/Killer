<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['emailadm'])) {
    header('Location: ../login');
    exit();
}
include '../../conectarbanco.php';
$conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
?>
<!DOCTYPE html>
<html dir="ltr" lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Saques de Afiliados</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon.png">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
    <link href="../assets/libs/flot/css/float-chart.css" rel="stylesheet">
    <link href="../dist/css/style.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        .btn-aprovar { padding:5px 14px; background:#28a745; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:13px; }
        .btn-aprovar:hover { background:#218838; }
        .badge-aguardando { background:#ffc107; color:#212529; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:700; }
        .badge-pago       { background:#28a745; color:#fff; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:700; }
        .badge-rejeitado  { background:#dc3545; color:#fff; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:700; }
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
        <div class="card" style="margin:20px;">
            <div class="card-body">
                <h5 class="card-title">Saques de Afiliados</h5>
                <p class="text-muted" style="font-size:13px;">
                    ⚠️ Os saques de afiliados são pagos <strong>manualmente</strong>.
                    Ao clicar em "Aprovar", marque como Pago e efetue a transferência PIX pelo seu banco.
                </p>
                <div class="table-responsive">
                    <table id="user-table" class="table table-striped table-bordered">
                        <thead>
                            <tr style="background:#1f262d;color:#a5a8ab;">
                                <th style="background:#1f262d;color:#a5a8ab;">EMAIL</th>
                                <th style="background:#1f262d;color:#a5a8ab;">NOME</th>
                                <th style="background:#1f262d;color:#a5a8ab;">CHAVE PIX</th>
                                <th style="background:#1f262d;color:#a5a8ab;">VALOR</th>
                                <th style="background:#1f262d;color:#a5a8ab;">STATUS</th>
                                <th style="background:#1f262d;color:#a5a8ab;">AÇÃO</th>
                            </tr>
                        </thead>
                        <tbody id="table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal de Confirmação Manual -->
<div class="modal fade" id="modalAprovar" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Pagamento Manual</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" style="font-size:14px;">
                    <strong>⚠️ Atenção:</strong> Esta ação marca o saque como <strong>Pago</strong>.
                    Certifique-se de ter realizado o PIX manualmente antes de confirmar.
                </div>
                <table class="table table-sm">
                    <tr><td><strong>Email:</strong></td><td id="m-email"></td></tr>
                    <tr><td><strong>Nome:</strong></td><td id="m-nome"></td></tr>
                    <tr><td><strong>Chave PIX:</strong></td><td id="m-pix" style="word-break:break-all;"></td></tr>
                    <tr><td><strong>Valor:</strong></td><td id="m-valor" style="color:#28a745;font-weight:700;"></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarPagamento">
                    ✔ Confirmar como Pago
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    // Carregar dados
    $.get('bd.php', function(data) {
        $('#table-body').empty();

        data.forEach(function(row) {
            var badgeClass = row.status === 'Aguardando Aprovação' ? 'badge-aguardando'
                           : (row.status === 'Pago' ? 'badge-pago' : 'badge-rejeitado');

            var acaoBtn = row.status === 'Aguardando Aprovação'
                ? `<button class="btn-aprovar" data-id="${row.id}" data-email="${row.email}"
                       data-nome="${row.nome}" data-pix="${row.pix}" data-valor="${row.valor}">
                       Aprovar
                   </button>`
                : '—';

            var newRow = `<tr>
                <td>${row.email}</td>
                <td>${row.nome}</td>
                <td>${row.pix}</td>
                <td>R$ ${parseFloat(row.valor).toFixed(2).replace('.', ',')}</td>
                <td><span class="${badgeClass}">${row.status}</span></td>
                <td>${acaoBtn}</td>
            </tr>`;
            $('#table-body').append(newRow);
        });

        $('#user-table').DataTable({ ordering: false });

        // Clique em Aprovar → abre modal
        $(document).on('click', '.btn-aprovar', function() {
            var $btn = $(this);
            $('#m-email').text($btn.data('email'));
            $('#m-nome').text($btn.data('nome'));
            $('#m-pix').text($btn.data('pix'));
            $('#m-valor').text('R$ ' + parseFloat($btn.data('valor')).toFixed(2).replace('.', ','));
            $('#btnConfirmarPagamento').data('id', $btn.data('id'));
            $('#modalAprovar').modal('show');
        });

        // Confirmar pagamento manual
        $('#btnConfirmarPagamento').on('click', function() {
            var id = $(this).data('id');
            $.ajax({
                url: 'aprovar.php',
                method: 'POST',
                data: { id: id },
                success: function(res) {
                    $('#modalAprovar').modal('hide');
                    alert(res);
                    location.reload();
                },
                error: function() {
                    alert('Erro ao processar. Tente novamente.');
                }
            });
        });

    }, 'json').fail(function() {
        alert('Erro ao carregar dados.');
    });

});
</script>

<footer style="position:fixed;bottom:0;width:100%;left:0;" class="footer text-center">
    Desenvolvido por <a href="">CDC COMPANY</a>.
</footer>

</body>
</html>
