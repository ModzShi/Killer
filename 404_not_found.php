<?php
require_once __DIR__ . '/app/bootstrap.php';
http_response_code(404);
header('Cache-Control: no-store, max-age=0');

$homeUrl = app_url();
$startUrl = app_url('presell/jogoteste/');
$accountUrl = !empty($_SESSION['emailadm'])
    ? app_url('adm/')
    : (!empty($_SESSION['manager_id']) ? app_url('gerente/') : (!empty($_SESSION['email']) ? app_url('painel/') : app_url('login/')));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#090b19">
    <title>Página não encontrada · Subway Run</title>
    <style>
        :root{color-scheme:dark;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;background:#090b19;color:#f7f8ff;font-synthesis:none;text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased}
        *{box-sizing:border-box}
        body{min-height:100svh;margin:0;display:grid;place-items:center;padding:max(22px,env(safe-area-inset-top)) 18px max(22px,env(safe-area-inset-bottom));overflow-x:hidden;background:radial-gradient(ellipse at 50% 5%,#34205f 0,transparent 43%),radial-gradient(ellipse at 8% 90%,#103c3b 0,transparent 38%),#090b19}
        .error-card{position:relative;width:min(100%,570px);padding:clamp(26px,7vw,54px);overflow:hidden;text-align:center;border:1px solid #a184e15c;border-radius:30px;background:linear-gradient(145deg,#1b1a35ed,#101426f5 72%);box-shadow:0 28px 90px #0009,inset 0 1px #ffffff18;isolation:isolate}
        .error-card:before{position:absolute;z-index:-1;top:-120px;left:50%;width:300px;height:240px;content:"";transform:translateX(-50%);border-radius:50%;background:#8458e83b;filter:blur(55px)}
        .brand{display:inline-flex;align-items:center;gap:10px;color:#fff;text-decoration:none;font-size:15px;font-weight:1000;letter-spacing:.1em}
        .brand-mark{display:grid;width:38px;height:38px;place-items:center;border:1px solid #57ebc27a;border-radius:13px;background:linear-gradient(145deg,#1bdbad,#178d9d);box-shadow:0 8px 24px #1bd3a63a}
        .brand-mark svg{width:22px;height:22px;fill:none;stroke:#06241f;stroke-linecap:round;stroke-linejoin:round;stroke-width:2.4}
        .code{margin:34px 0 4px;color:#7ce9c9;font-size:clamp(82px,21vw,132px);font-weight:1000;line-height:.85;letter-spacing:-.09em;text-shadow:0 8px 35px #27d3a631}
        h1{margin:20px 0 10px;font-size:clamp(25px,6vw,34px);line-height:1.12;letter-spacing:-.04em}
        .description{max-width:390px;margin:0 auto;color:#c0c8dd;font-size:15px;line-height:1.65}
        .actions{display:grid;grid-template-columns:1fr 1fr;gap:11px;margin-top:28px}
        .action{min-height:50px;display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:12px 16px;border:1px solid #ffffff29;border-radius:14px;background:#ffffff0b;color:#f7f8ff;text-decoration:none;font-size:14px;font-weight:850;transition:transform .18s ease,filter .18s ease,border-color .18s ease}
        .action:hover{transform:translateY(-2px);border-color:#ffffff5b;filter:brightness(1.08)}
        .action:focus-visible{outline:3px solid #8a77ff;outline-offset:3px}
        .action.primary{border-color:#a4ffdf;background:linear-gradient(115deg,#5cf2c1,#1bd3a5 55%,#55e9bb);color:#08271e;box-shadow:0 5px 0 #087a64,0 12px 28px #11d9a23b}
        .action svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:2}
        .foot{margin:24px 0 0;color:#8994ae;font-size:12px}
        @media(max-width:420px){.error-card{border-radius:24px}.actions{grid-template-columns:1fr}.code{margin-top:29px}.description{font-size:14px}}
        @media(prefers-reduced-motion:reduce){*,*:before,*:after{scroll-behavior:auto!important;transition:none!important}}
    </style>
</head>
<body>
    <main class="error-card">
        <a class="brand" href="<?= app_escape($homeUrl) ?>" aria-label="Subway Run — início">
            <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 17.5 8.2 6l3.8 11.5L15.8 6 20 17.5"/><path d="M3 20h18"/></svg></span>
            <span>SUBWAY RUN</span>
        </a>
        <div class="code" aria-hidden="true">404</div>
        <h1>Esse caminho não existe</h1>
        <p class="description">O endereço pode ter sido alterado ou não está disponível. Volte ao início e escolha para onde quer ir.</p>
        <nav class="actions" aria-label="Próximas ações">
            <a class="action primary" href="<?= app_escape($homeUrl) ?>"><svg viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>Ir ao início</a>
            <a class="action" href="<?= app_escape($accountUrl) ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>Minha conta</a>
            <a class="action" href="<?= app_escape($startUrl) ?>"><svg viewBox="0 0 24 24"><path d="m8 5 12 7-12 7z"/></svg>Experimentar o jogo</a>
            <a class="action" href="<?= app_escape($homeUrl) ?>" onclick="if(history.length>1){history.back();return false}"><svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/><path d="M9 12h12"/></svg>Voltar</a>
        </nav>
        <p class="foot">Subway Run · A corrida continua por aqui.</p>
    </main>
</body>
</html>
