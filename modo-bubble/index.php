<?php
require_once __DIR__ . '/../app/auth.php';
if (empty($_SESSION['email'])) {
    header('Location: ' . app_url('login/'), true, 303);
    exit;
}
header('Cache-Control: no-store, private');
$bubbleBase = app_url('modo-bubble');
?><!doctype html>
<html lang="pt-BR" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#c9a8e8">
  <meta name="robots" content="noindex,nofollow">
  <title>Modo Bubble | Subway Run</title>
  <link rel="manifest" href="<?= app_escape($bubbleBase) ?>/manifest.json">
  <link rel="stylesheet" href="<?= app_escape($bubbleBase) ?>/assets/index-CVxiDctz.css">
  <script>window.BUBBLE_BASE=<?= json_encode($bubbleBase, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;try{localStorage.setItem('bb-tutorial-v1:bubble-test','1')}catch(e){}</script>
  <script src="<?= app_escape($bubbleBase) ?>/theme-boot.js"></script>
  <script src="<?= app_escape($bubbleBase) ?>/mock-api.js"></script>
  <style>
    .bubble-test-banner{position:fixed;z-index:10000;top:env(safe-area-inset-top,0px);left:50%;transform:translateX(-50%);max-width:calc(100vw - 20px);display:flex;align-items:center;justify-content:center;gap:10px;padding:6px 12px;border:1px solid #ffffff50;border-radius:0 0 12px 12px;background:#21143bdd;color:#fff;font:800 10px/1.3 system-ui,sans-serif;letter-spacing:.06em;text-align:center;box-shadow:0 5px 18px #10082466;pointer-events:none}
    .bubble-test-banner a{pointer-events:auto;flex:none;padding:4px 8px;border:1px solid #ffffff55;border-radius:8px;background:#ffffff18;color:#fff;text-decoration:none;white-space:nowrap}
    .bubble-test-banner{position:fixed;z-index:10000;top:env(safe-area-inset-top,0px);left:50%;transform:translateX(-50%);max-width:calc(100vw - 20px);display:flex;align-items:center;justify-content:center;gap:10px;padding:6px 12px;border:1px solid #ffffff50;border-radius:0 0 12px 12px;background:#21143bdd;color:#fff;font:800 10px/1.3 system-ui,sans-serif;letter-spacing:.06em;text-align:center;box-shadow:0 5px 18px #10082466;pointer-events:none}
  </style>
</head>
<body>
  <div class="bubble-test-banner"><span>MODO BUBBLE | TESTE | SEM PIX OU ALTERACAO DE SALDO</span><a href="<?= app_escape(app_url('painel/')) ?>">Voltar ao Subway Run</a></div>
  <div id="root"></div>
  <script type="module" src="<?= app_escape($bubbleBase) ?>/assets/index-ATEq7JZJ.js?v=bubble-router-fix-2"></script>
</body>
</html>
