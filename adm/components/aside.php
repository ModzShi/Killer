<?php require_once __DIR__ . '/../../app/bootstrap.php'; ?><aside class="left-sidebar" data-sidebarbg="skin5">
    <script disable-devtool-auto src='https://cdn.jsdelivr.net/npm/disable-devtool@latest'></script>
    <div class="scroll-sidebar">
        <nav class="sidebar-nav">
            <ul id="sidebarnav" class="pt-4">
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="../" aria-expanded="false"><i
                            class="mdi mdi-view-dashboard"></i><span class="hide-menu">Página inicial</span></a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="../GGR" aria-expanded="false"><i
                            class="mdi mdi-margin"></i><span class="hide-menu">GGR</span></a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="../usuarios"
                        aria-expanded="false"><i class="mdi mdi-account"></i><span class="hide-menu">Usuários</span></a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="../depositos"
                        aria-expanded="false"><i class="mdi mdi-cash"></i><span class="hide-menu">Depositos</span></a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="../saques"
                        aria-expanded="false"><i class="mdi mdi-cash"></i><span class="hide-menu">Saques</span></a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="../pixels"
                        aria-expanded="false"><i class="mdi mdi-code-tags"></i><span class="hide-menu">Pixels</span></a>
                </li>


                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="../gateway/bxpay.php"
                        aria-expanded="false"><i class="mdi mdi-cash"></i><span class="hide-menu">Gateway BX Pay</span></a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= app_escape(app_url('adm/gerentes/')) ?>"
                        aria-expanded="false"><i class="mdi mdi-account-group"></i><span class="hide-menu">Gerentes</span></a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= app_escape(app_url('adm/gerentes/saques.php')) ?>"
                        aria-expanded="false"><i class="mdi mdi-cash-multiple"></i><span class="hide-menu">Saques de gerentes</span></a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="../config"
                        aria-expanded="false"><i class="mdi mdi-account"></i><span
                            class="hide-menu">Configurações</span></a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark sidebar-link" href="../planos"
                        aria-expanded="false"><i class="mdi mdi-square-inc-cash"></i><span
                            class="hide-menu">Afiliados</span></a>
                </li>





                <li class="sidebar-item p-3">
                    <a href="/adm/logout.php" class="
                    w-100
                    btn 
                    d-flex
                    align-items-center
                    text-white
                  " style="background-color: #ff7c6b"><i class="mdi mdi-logout font-20 me-2"></i>Sair</a>
                </li>
            </ul>
        </nav>
    </div>
</aside>
