<?php
require_once __DIR__.'/../app/account.php';
$db=app_db();$profileSuccess='';$profileError='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    if(!app_check_csrf()){$profileError='O formulário expirou. Atualize a página e tente novamente.';http_response_code(403);}
    else try{account_update_profile($db,$_POST);$profileSuccess='Seus dados foram atualizados.';}
    catch(InvalidArgumentException $error){$profileError=$error->getMessage();}
    catch(Throwable $error){error_log('profile update: '.$error->getMessage());$profileError='Não foi possível salvar as alterações. Tente novamente.';}
}
$user=account_user($db);game_install($db);withdrawal_install($db);
$nomeUnico=$db->query('SELECT nome_unico FROM app LIMIT 1')->fetch_assoc()['nome_unico']??'Subway Run';
$page=max(1,min(10000,(int)($_GET['page']??1)));$offset=($page-1)*20;
$rounds=app_query($db,"SELECT bet,payout,status,created_at FROM game_rounds WHERE email=? ORDER BY created_at DESC LIMIT 21 OFFSET $offset",[$user['email']])->get_result()->fetch_all(MYSQLI_ASSOC);
$withdrawals=app_query($db,"SELECT valor,status,data FROM saques WHERE email=? ORDER BY STR_TO_DATE(data,'%d-%m-%Y %H:%i:%s') DESC LIMIT 21 OFFSET $offset",[$user['email']])->get_result()->fetch_all(MYSQLI_ASSOC);
$deposits=app_query($db,"SELECT valor,status,data FROM confirmar_deposito WHERE email=? ORDER BY STR_TO_DATE(data,'%d/%m/%Y %H:%i') DESC LIMIT 21 OFFSET $offset",[$user['email']])->get_result()->fetch_all(MYSQLI_ASSOC);
$total=app_query($db,'SELECT COUNT(*) n FROM game_rounds WHERE email=?',[$user['email']])->get_result()->fetch_assoc()['n'];
$more=count($rounds)>20||count($withdrawals)>20||count($deposits)>20;
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Meu perfil</title><link rel="stylesheet" href="<?= app_url('arquivos/account.css') ?>?v=<?= filemtime(__DIR__.'/../arquivos/account.css') ?>"></head><body><?php $menuBase='../';$menuLoggedIn=true;$menuCurrent='perfil/';include __DIR__.'/../components/menu.php'; ?>
<main class="account-shell"><header class="account-heading"><span class="icon-tile"><?= ui_icon('user') ?></span><div><p class="eyebrow">SEU ESPAÇO</p><h1><?= app_escape($user['nome']?:'Meu perfil') ?></h1><p><?= app_escape($user['email']) ?></p></div></header>
<div class="account-grid"><section class="account-card wallet-card"><div class="card-top"><span class="eyebrow">SALDO <?= $user['demo']==='1'?'SIMULADO':'DISPONÍVEL' ?></span><?= ui_icon('wallet') ?></div><strong class="balance"><?= account_money($user['saldo']) ?></strong><a class="account-button" href="<?= app_url('painel/') ?>"><?= ui_icon('play') ?> Jogar agora</a><a class="account-button secondary" href="<?= app_url('afiliate/') ?>"><?= ui_icon('users') ?> Minha rede</a></section><section class="account-card"><h2><?= ui_icon('shield') ?> Dados da conta</h2><div class="activity-row"><div><small>Telefone</small><strong><?= app_escape($user['telefone']) ?></strong></div></div><div class="activity-row"><div><small>Cadastro</small><strong><?= app_escape($user['data_cadastro']) ?></strong></div><span class="chip row-end"><?= $user['demo']==='1'?'Conta demo':'Conta de jogador' ?></span></div></section></div>
<section class="account-card profile-edit-card" aria-labelledby="profile-edit-title">
  <div class="profile-edit-heading">
    <span class="icon-tile small"><?= ui_icon('user') ?></span>
    <div><p class="eyebrow">DADOS PESSOAIS</p><h2 id="profile-edit-title">Editar perfil</h2><p>Atualize seu nome, telefone ou senha.</p></div>
  </div>
  <?php if($profileSuccess): ?><p class="profile-notice success" role="status"><?= app_escape($profileSuccess) ?></p><?php endif; ?>
  <?php if($profileError): ?><p class="profile-notice error" role="alert"><?= app_escape($profileError) ?></p><?php endif; ?>
  <form class="profile-edit-form" method="post" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>">
    <label for="profile-name">Nome completo</label>
    <input id="profile-name" name="nome" type="text" autocomplete="name" minlength="2" maxlength="120" value="<?= app_escape($user['nome']) ?>" required>
    <label for="profile-phone">Telefone com DDD</label>
    <input id="profile-phone" name="telefone" type="tel" autocomplete="tel" inputmode="tel" value="<?= app_escape($user['telefone']) ?>" required>
    <label for="profile-email">E-mail de acesso</label>
    <input id="profile-email" type="email" value="<?= app_escape($user['email']) ?>" readonly>
    <small class="profile-help">O e-mail permanece vinculado ao login e ao histórico da conta.</small>
    <details class="password-edit">
      <summary>Alterar senha</summary>
      <label for="current-password">Senha atual</label>
      <input id="current-password" name="senha_atual" type="password" autocomplete="current-password">
      <label for="new-password">Nova senha</label>
      <input id="new-password" name="nova_senha" type="password" autocomplete="new-password" minlength="8" maxlength="72">
      <label for="confirm-password">Confirme a nova senha</label>
      <input id="confirm-password" name="confirmar_senha" type="password" autocomplete="new-password" minlength="8" maxlength="72">
    </details>
    <button class="account-button profile-save" type="submit"><?= ui_icon('check') ?> Salvar alterações</button>
  </form>
</section><section class="account-card profile-preferences" aria-labelledby="appearance-title"><div class="preferences-heading"><span class="icon-tile small"><?= ui_icon('spark') ?></span><div><p class="eyebrow">PERSONALIZE</p><h2 id="appearance-title">Aparência e música</h2><p>Escolha as cores do seu Subway Run.</p></div></div><div class="theme-options" role="group" aria-label="Tema de cores"><button type="button" class="theme-option" data-theme-choice="default" aria-pressed="false"><i class="theme-swatch theme-swatch--default"></i><span>Atual</span></button><button type="button" class="theme-option" data-theme-choice="gold" aria-pressed="false"><i class="theme-swatch theme-swatch--gold"></i><span>Dourado</span></button><button type="button" class="theme-option" data-theme-choice="red" aria-pressed="false"><i class="theme-swatch theme-swatch--red"></i><span>Vermelho</span></button><button type="button" class="theme-option" data-theme-choice="purple" aria-pressed="false"><i class="theme-swatch theme-swatch--purple"></i><span>Roxo</span></button><button type="button" class="theme-option" data-theme-choice="yellow" aria-pressed="false"><i class="theme-swatch theme-swatch--yellow"></i><span>Amarelo</span></button></div><div class="music-preference"><span class="music-preference__icon"><?= ui_icon('sound') ?></span><span><strong>Música do menu</strong><small>Trilha de fundo do Subway Run</small></span><button class="menu-music" type="button" id="profileMusicToggle" aria-pressed="false"><span class="menu-music__label">Ativar música</span></button></div></section>
<div class="stat-grid"><div class="account-card mini"><?= ui_icon('play') ?><strong><?= (int)$total ?></strong><span>Partidas</span></div><div class="account-card mini"><?= ui_icon('trophy') ?><strong><?= account_money($user['ganhos']) ?></strong><span>Ganhos registrados</span></div><div class="account-card mini"><?= ui_icon('coins') ?><strong><?= account_money($user['depositou']) ?></strong><span>Total depositado</span></div></div>
<?php foreach([['Partidas','play',$rounds],['Depósitos','deposit',$deposits],['Saques','withdraw',$withdrawals]] as [$title,$icon,$rows]): ?><details class="account-card" open><summary><?= ui_icon($icon) ?> Histórico de <?= strtolower($title) ?></summary><?php if(!$rows): ?><div class="empty-state"><?= ui_icon('history') ?><p>Nenhum registro nesta página.</p></div><?php else: foreach(array_slice($rows,0,20) as $item): ?><div class="activity-row"><span class="icon-tile small"><?= ui_icon($icon) ?></span><div><strong><?= app_escape(account_status($item['status'])) ?></strong><small><?= app_escape($item['created_at']??$item['data']) ?></small></div><div class="row-end"><strong><?= account_money($icon==='play'?($item['payout']??0):$item['valor']) ?></strong><?php if($icon==='play'): ?><small>Entrada <?= account_money($item['bet']) ?></small><?php endif; ?></div></div><?php endforeach; endif; ?></details><?php endforeach; ?>
<nav aria-label="Páginas do histórico"><?php if($page>1): ?><a class="account-button secondary" href="?page=<?= $page-1 ?>">Mais recentes</a><?php endif; ?> <span class="chip">Página <?= $page ?></span> <?php if($more): ?><a class="account-button secondary" href="?page=<?= $page+1 ?>">Mais antigos</a><?php endif; ?></nav><a class="account-button danger" href="<?= app_url('logout.php') ?>"><?= ui_icon('logout') ?> Sair da conta</a></main></body></html>
