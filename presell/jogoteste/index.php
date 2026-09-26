<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (!empty($_SESSION['email'])) {
    header('Location: ' . app_url('painel/'), true, 303);
    exit;
}

$affiliate = is_string($_GET['aff'] ?? null) && preg_match('/^\d{1,12}$/D', $_GET['aff']) ? $_GET['aff'] : '';
$managerCode = is_string($_GET['ref'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $_GET['ref']) ? $_GET['ref'] : '';
if ($affiliate !== '') $_SESSION['landing_affiliate'] = $affiliate;
if ($managerCode !== '') $_SESSION['landing_manager_code'] = $managerCode;
$signupQuery = array_filter(['aff' => $affiliate, 'ref' => $managerCode, 'continuar' => '1']);
$signupUrl = app_url('cadastrar/?' . http_build_query($signupQuery));
$gameUrl = app_url('jogar/?demo=1&jogarsubway=5BC&SbSB1C2');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#090b1a">
    <title>Teste o Subway Run</title>
    <link rel="stylesheet" href="<?= app_escape(app_url('arquivos/menu.css')) ?>?v=<?= filemtime(dirname(__DIR__, 2) . '/arquivos/menu.css') ?>">
    <style>
        :root{color-scheme:dark;--ink:#f8f8ff;--muted:#bec5df;--violet:#a98aff;--mint:#52edc6;--gold:#ffdf74}*{box-sizing:border-box}body{min-height:100vh;margin:0;color:var(--ink);font-family:Inter,"Segoe UI",Arial,sans-serif;background:radial-gradient(ellipse at 50% 0,#452a83 0,transparent 45%),radial-gradient(ellipse at 85% 70%,#0d495055 0,transparent 38%),#090b1a}.intro-shell{width:calc(100% - 28px);max-width:980px;margin:0 auto;padding:clamp(28px,7vw,74px) 0 56px}.intro-card{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.1fr) minmax(240px,.9fr);gap:clamp(22px,5vw,54px);align-items:center;padding:clamp(22px,5vw,54px);border:1px solid #a78bfa55;border-radius:30px;background:linear-gradient(135deg,#171a35f5,#15112df5 58%,#0d2635f5);box-shadow:0 24px 90px #0008,inset 0 1px #ffffff18}.intro-card:before{content:"";position:absolute;width:310px;height:310px;right:-100px;top:-150px;border-radius:50%;background:#754dff35;filter:blur(25px);pointer-events:none}.intro-copy,.intro-visual{position:relative;z-index:1}.intro-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 11px;border:1px solid #52edc64d;border-radius:999px;background:#0a3a343c;color:#abffe9;font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.intro-dot{width:8px;height:8px;border-radius:50%;background:var(--mint);box-shadow:0 0 12px #52edc6}.intro-copy h1{max-width:560px;margin:18px 0 13px;font-size:clamp(34px,7vw,62px);line-height:.98;letter-spacing:-.06em}.intro-copy h1 span{color:var(--gold)}.intro-lead{max-width:480px;margin:0;color:#d5daf0;font-size:clamp(15px,2.2vw,18px);line-height:1.55}.intro-features{display:grid;gap:10px;margin:23px 0 0;padding:0;list-style:none}.intro-features li{display:flex;align-items:center;gap:9px;color:#e3e7f8;font-size:13px}.intro-features svg{flex:0 0 17px;width:17px;height:17px;color:var(--mint)}.intro-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:26px}.intro-button{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:49px;padding:0 18px;border:1px solid #a4ffe6;border-radius:14px;background:linear-gradient(105deg,#4df0c2,#16d7aa 52%,#75efa4);color:#062b26;text-decoration:none;font-size:14px;font-weight:950;box-shadow:0 4px 0 #08765f,0 10px 24px #17d4a533;transition:transform .17s,filter .17s}.intro-button:hover{transform:translateY(-2px);filter:brightness(1.06)}.intro-button svg{width:18px;height:18px}.intro-button--secondary{border-color:#a78bfa66;background:#ffffff08;color:#f4efff;box-shadow:none}.intro-truth{max-width:510px;margin:15px 0 0;color:#abb5d4;font-size:11px;line-height:1.5}.intro-visual{min-height:280px;display:grid;place-items:center}.runner-orbit{position:absolute;width:min(31vw,300px);aspect-ratio:1;border:1px solid #ad8dff55;border-radius:50%;box-shadow:0 0 65px #7651fa42,inset 0 0 42px #7251fc20;animation:orbitPulse 5s ease-in-out infinite}.runner-orbit:before,.runner-orbit:after{content:"";position:absolute;inset:13%;border:1px solid #51e8ca35;border-radius:50%}.runner-orbit:after{inset:27%;border-color:#ffdc7040}.runner-icon{position:relative;display:grid;place-items:center;width:126px;height:126px;border:1px solid #d5c3ff;border-radius:36px;background:linear-gradient(145deg,#855cff,#4131a1 58%,#173450);color:#fff;box-shadow:0 16px 48px #7049ff6e, inset 0 1px #ffffff80;transform:rotate(-7deg);animation:runnerFloat 3.2s ease-in-out infinite}.runner-icon svg{width:56px;height:56px;stroke-width:1.7}.visual-tag{position:absolute;right:0;bottom:20px;padding:11px 14px;border:1px solid #ffffff24;border-radius:14px;background:#10162bea;color:#e9eaff;font-size:11px;font-weight:800;box-shadow:0 8px 25px #0006}.visual-tag span{display:block;margin-top:3px;color:#9ff2dc;font-size:10px}.intro-footer{display:flex;justify-content:center;margin:18px 0 0;color:#8e98b7;font-size:10px;text-align:center}.intro-footer a{color:#c7b5ff;text-decoration:none}@keyframes runnerFloat{0%,100%{transform:translateY(0) rotate(-7deg)}50%{transform:translateY(-9px) rotate(-4deg)}}@keyframes orbitPulse{0%,100%{transform:scale(.98);opacity:.72}50%{transform:scale(1.04);opacity:1}}@media(max-width:680px){.intro-shell{width:calc(100% - 22px);padding:24px 0 38px}.intro-card{grid-template-columns:1fr;gap:10px;padding:23px 19px 20px;border-radius:24px}.intro-copy h1{font-size:clamp(37px,10vw,52px);margin-top:15px}.intro-features{gap:8px;margin-top:18px}.intro-features li{font-size:12px}.intro-actions{display:grid;grid-template-columns:1fr;gap:9px;margin-top:21px}.intro-button{width:100%}.intro-visual{min-height:188px;margin-top:2px}.runner-orbit{width:min(50vw,210px)}.runner-icon{width:94px;height:94px;border-radius:28px}.runner-icon svg{width:44px;height:44px}.visual-tag{right:2px;bottom:7px}.intro-truth{font-size:10px}}
        @media(prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;scroll-behavior:auto!important;transition:none!important}}
    </style>
</head>
<body>
<main class="intro-shell">
    <section class="intro-card" aria-labelledby="intro-title">
        <div class="intro-copy">
            <span class="intro-badge"><i class="intro-dot" aria-hidden="true"></i> Tutorial gratuito</span>
            <h1 id="intro-title">Conheça o <span>Subway Run</span></h1>
            <p class="intro-lead">Desvie, pule e troque de trilho. Comece pelo tutorial para conhecer os controles da corrida.</p>
            <ul class="intro-features">
                <li><?= ui_icon('check') ?> Tutorial guiado com os movimentos principais</li>
                <li><?= ui_icon('check') ?> Acesso pelo celular ou computador</li>
                <li><?= ui_icon('check') ?> Sem depósito para experimentar o treino</li>
            </ul>
            <div class="intro-actions">
                <a class="intro-button" href="<?= app_escape($gameUrl) ?>"><?= ui_icon('play') ?> Testar agora</a>
                <a class="intro-button intro-button--secondary" href="<?= app_escape($signupUrl) ?>"><?= ui_icon('user') ?> Criar conta</a>
            </div>
            <p class="intro-truth">O treino usa uma pontuação simulada, separada do saldo da conta. Recursos com saldo e respectivas condições aparecem na plataforma após o cadastro.</p>
        </div>
        <div class="intro-visual" aria-hidden="true"><span class="runner-orbit"></span><span class="runner-icon"><?= ui_icon('play') ?></span><span class="visual-tag">SUBWAY RUN<span>DESVIE · PULE · AVANCE</span></span></div>
    </section>
    <footer class="intro-footer">Ao criar uma conta, é necessário ter 18 anos ou mais e aceitar os <a href="<?= app_escape(app_url('legal/')) ?>">termos de uso</a>.</footer>
</main>
</body>
</html>
