<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../components/icons.php';
$legacyLinks = [
    ['adm/', 'Painel', 'wallet'], ['adm/GGR/', 'GGR', 'coins'],
    ['adm/usuarios/', 'Usuários', 'users'], ['adm/depositos/', 'Depósitos', 'deposit'],
    ['adm/saques/', 'Saques', 'withdraw'], ['adm/gerentes/', 'Gerentes', 'user'],
    ['adm/gerentes/saques.php', 'Saques de gerentes', 'withdraw'],
    ['adm/comissoes/', 'Comissões', 'coins'], ['adm/gateway/bxpay.php', 'Gateway BX Pay', 'shield'],
    ['adm/jogo/', 'Jogo e ganhos', 'play'], ['adm/config/', 'Configurações', 'edit'],
    ['adm/planos/', 'Afiliados', 'link'], ['adm/pixels/', 'Pixels', 'spark'],
    ['adm/conta/', 'Minha conta', 'user'],
    ['modo-consulta/', 'Modo Consulta', 'shield'],
];
?>
<link rel="stylesheet" href="<?= app_escape(app_url('adm/premium-admin.css')) ?>?v=<?= filemtime(dirname(__DIR__).'/premium-admin.css') ?>">
<button class="admin-legacy-menu" type="button" aria-expanded="false" aria-controls="admin-legacy-nav" aria-label="Abrir menu administrativo"><?= ui_icon('menu') ?></button>
<button class="admin-legacy-shade" type="button" aria-label="Fechar menu administrativo"></button>
<aside class="left-sidebar" data-sidebarbg="skin5" id="admin-legacy-nav">
  <div class="scroll-sidebar"><nav class="sidebar-nav" aria-label="Navegação administrativa"><ul id="sidebarnav" class="pt-4">
    <?php foreach ($legacyLinks as [$path,$label,$icon]): ?>
    <li class="sidebar-item"><a class="sidebar-link waves-effect waves-dark" href="<?= app_escape(app_url($path)) ?>"><?= ui_icon($icon) ?><span class="hide-menu"><?= app_escape($label) ?></span></a></li>
    <?php endforeach; ?>
    <li class="sidebar-item"><a class="sidebar-link admin-legacy-exit" href="<?= app_escape(app_url('adm/logout.php')) ?>"><?= ui_icon('logout') ?><span class="hide-menu">Sair</span></a></li>
  </ul></nav></div>
</aside>
<script>
(() => {
  document.body.classList.add('sk-admin');
  const wrapper=document.getElementById('main-wrapper'),button=document.querySelector('.admin-legacy-menu'),shade=document.querySelector('.admin-legacy-shade');
  const close=()=>{wrapper?.classList.remove('admin-legacy-open');button?.setAttribute('aria-expanded','false')};
  button?.addEventListener('click',()=>{const open=!wrapper?.classList.contains('admin-legacy-open');wrapper?.classList.toggle('admin-legacy-open',open);button.setAttribute('aria-expanded',String(open))});
  shade?.addEventListener('click',close);
  document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
  document.querySelectorAll('#admin-legacy-nav a').forEach(a=>{if(new URL(a.href).pathname===location.pathname)a.setAttribute('aria-current','page')});
})();
</script>
