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
  <script src="<?= app_escape($bubbleBase) ?>/demo-engine.js"></script>
  <script src="<?= app_escape($bubbleBase) ?>/demo-api-v2.js"></script>
  <style>
    .bubble-test-banner{position:fixed;z-index:10000;top:env(safe-area-inset-top,0px);left:50%;transform:translateX(-50%);width:calc(100vw - 24px);max-width:436px;box-sizing:border-box;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 10px;border:1px solid #ffffff30;border-radius:0 0 12px 12px;background:#21143be8;color:#fff;font:700 10px/1.4 system-ui,sans-serif;letter-spacing:.04em;pointer-events:none}
    .bubble-test-banner span{white-space:nowrap;text-transform:uppercase}
    .bubble-test-banner a{pointer-events:auto;flex:none;padding:3px 8px;border:1px solid #ffffff40;border-radius:7px;background:#ffffff12;color:#fff;text-decoration:none;white-space:nowrap}
  </style>
</head>
<body>
  <div class="bubble-test-banner"><span>Bubble · Treino</span><a href="<?= app_escape(app_url('painel/')) ?>">Voltar ao Subway Run ↗</a></div>
  <div id="root"></div>
  <script type="module" src="<?= app_escape($bubbleBase) ?>/assets/index-BubblePlayable.js"></script>
</body>
</html>
