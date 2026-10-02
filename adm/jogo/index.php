<?php
require_once __DIR__ . '/../../app/game.php';
if (empty($_SESSION['emailadm'])) { header('Location: ' . app_url('adm/login/')); exit; }
$db = app_db();
game_install($db);
$settings = $db->query('SELECT * FROM game_settings WHERE id=1')->fetch_assoc() ?: [];
$notice = $_SESSION['game_settings_notice'] ?? '';
unset($_SESSION['game_settings_notice']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Configuração do jogo</title>
  <link rel="stylesheet" href="<?= app_escape(app_url('adm/gateway/bxpay.css')) ?>">
  <style>
    body{margin:0;background:#0b1020;color:#eef2ff;font-family:Inter,system-ui,Arial,sans-serif}
    .wrap{width:min(100% - 28px,780px);margin:28px auto 48px}.back{color:#f7c853;text-decoration:none;font-weight:700}
    .card{margin-top:18px;padding:clamp(20px,5vw,34px);border:1px solid #39415d;border-radius:24px;background:linear-gradient(145deg,#19233b,#11182a);box-shadow:0 24px 70px #0007}
    h1{margin:0 0 8px;font-size:clamp(26px,5vw,36px)}.intro{margin:0;color:#bdc7df;line-height:1.6}
    .section-title{margin:28px 0 12px;color:#f8d878;font-size:13px;letter-spacing:.12em;text-transform:uppercase}
    .speed-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{padding:17px;border:1px solid #3a4663;border-radius:18px;background:#10182b}
    label{display:block;margin-bottom:8px;font-weight:800}.field input{width:100%;box-sizing:border-box;padding:13px 14px;border:1px solid #596783;border-radius:12px;background:#080f20;color:#fff;font-size:18px;font-weight:800}
    small,.hint{display:block;margin-top:8px;color:#aeb9d2;line-height:1.5}.notice{margin:18px 0;padding:13px 15px;border-radius:12px;background:#164c37;color:#d6ffe9}
    .btn{margin-top:24px;padding:14px 22px;border:0;border-radius:13px;background:linear-gradient(100deg,#ffe18a,#ffb72e);color:#251700;font-size:16px;font-weight:900;cursor:pointer;box-shadow:0 7px 0 #a45a11}
    .bet-presets{display:flex;flex-wrap:wrap;gap:8px}.bet-presets span{padding:8px 12px;border:1px solid #4b5873;border-radius:999px;background:#10182b;color:#dbe5fb;font-weight:800}
    @media(max-width:560px){.speed-grid{grid-template-columns:1fr}.wrap{margin-top:18px}.btn{width:100%}}
  </style>
</head>
<body><main class="wrap"><a class="back" href="<?= app_escape(app_url('adm/')) ?>">← Voltar ao painel</a>
  <section class="card">
    <h1>Ritmo e recompensas</h1>
    <p class="intro">Ajuste a velocidade inicial de cada modo. O jogo aumenta o ritmo gradualmente durante a corrida. As alterações afetam partidas iniciadas depois de salvar.</p>
    <?php if ($notice): ?><div class="notice" role="status"><?= app_escape($notice) ?></div><?php endif; ?>
    <form method="post" action="<?= app_escape(app_url('adm/jogo/salvar.php')) ?>">
      <input type="hidden" name="csrf" value="<?= app_escape(app_csrf()) ?>">
      <h2 class="section-title">Velocidade inicial</h2>
      <div class="speed-grid">
        <div class="field"><label for="speed_demo">Treino e contas demo</label><input id="speed_demo" name="speed_demo" type="number" min="100" max="300" step="1" value="<?= (int)($settings['speed_demo'] ?? 155) ?>" required><small>Faixa permitida: 100–300. Um aumento gradual de até 35 acompanha a corrida.</small></div>
        <div class="field"><label for="speed_paid">Partidas com saldo</label><input id="speed_paid" name="speed_paid" type="number" min="100" max="300" step="1" value="<?= (int)($settings['speed_paid'] ?? 180) ?>" required><small>Faixa permitida: 100–300. Um aumento gradual de até 140 acompanha a corrida.</small></div>
      </div>
      <h2 class="section-title">Meta e moedas</h2>
      <div class="field"><label for="meta_multiplier">Multiplicador da meta</label><input id="meta_multiplier" name="meta_multiplier" type="number" min="1" max="100" step="0.01" value="<?= app_escape($settings['meta_multiplier'] ?? 10) ?>" required><small>Exemplo: entrada de R$ 5 × 10 = meta de R$ 50.</small></div>
      <div class="speed-grid" style="margin-top:14px">
        <div class="field"><label for="coin_value_demo">Valor por moeda no treino (R$)</label><input id="coin_value_demo" name="coin_value_demo" type="number" min="0.01" max="100" step="0.01" value="<?= app_escape($settings['coin_value_demo'] ?? 0.11) ?>" required></div>
        <div class="field"><label for="coin_value_paid">Valor por moeda em partidas com saldo (R$)</label><input id="coin_value_paid" name="coin_value_paid" type="number" min="0.01" max="100" step="0.01" value="<?= app_escape($settings['coin_value_paid'] ?? 0.03) ?>" required></div>
      </div>
      <h2 class="section-title">Entradas disponíveis</h2><div class="bet-presets"><span>R$ 5</span><span>R$ 10</span><span>R$ 20</span><span>R$ 30</span><span>R$ 50</span><span>R$ 100</span></div>
      <p class="hint">O modo de treino e o modo com saldo agora leem velocidades próprias desta tela. O controle antigo de dificuldade foi removido do painel principal.</p>
      <button class="btn" type="submit">Salvar configurações</button>
    </form>
  </section>
</main></body></html>
