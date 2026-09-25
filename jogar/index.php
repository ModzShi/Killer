<?php
require_once __DIR__ . '/../app/game.php';
$gameDb = app_db();
game_install($gameDb);
$gameSettings = $gameDb->query('SELECT * FROM game_settings WHERE id=1')->fetch_assoc();
$gameDemo = ($_GET['demo'] ?? '') === '1';
$roundToken = (string)($_GET['round'] ?? '');
$gameCoinValue = (float)$gameSettings[($gameDemo || !empty($_SESSION['demo_account'])) ? 'coin_value_demo' : 'coin_value_paid'];
if (!$gameDemo) {
    $email = (string)($_SESSION['email'] ?? '');
    $stmt = $gameDb->prepare('SELECT bet,max_payout,coin_value,status FROM game_rounds WHERE token=? AND email=?');
    $stmt->bind_param('ss', $roundToken, $email); $stmt->execute();
    $activeRound = $stmt->get_result()->fetch_assoc();
    if (!$activeRound || $activeRound['status'] !== 'PLAYING') { header('Location: '.app_url('painel/'), true, 303); exit; }
    $gameCoinValue = (float)$activeRound['coin_value'];
    $gameSettings['meta_multiplier'] = (float)$activeRound['max_payout'] / (float)$activeRound['bet'];
}
header('Cache-Control: no-store, private');
$gameDb->close();
?><!DOCTYPE html>


<html lang="pt-BR">

<head>
    <script>
    (()=>{const token=<?= json_encode($roundToken, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,home=<?= json_encode(app_url($gameDemo?'':'painel/')) ?>;
    function guard(){try{if(sessionStorage.getItem('finished:'+token))location.replace(home)}catch(e){}}
    guard();addEventListener('pageshow',guard);})();
    </script>


    <script>
        // Diferencia o treino gratuito das rodadas autorizadas pelo servidor.
        try { localStorage.setItem('realBetPage', <?= $gameDemo ? "'false'" : "'true'" ?>); } catch (ignore) {}
    </script>

    <script type="text/javascript">
        function extrairvalorAposta() {
            var url = window.location.href;
            var match = url.match(/jogarsubway=(\d+BC)/);

            if (match) {
                var valorAposta = match[1];
                var valorMapeado;
                switch (valorAposta) {
                    case '1BC':
                        valorMapeado = 1;
                        break;
                    case '2BC':
                        valorMapeado = 2;
                        break;
                    case '3BC':
                        valorMapeado = 5;
                        break;
                    default:
                        window.location.href = '/painel';
                        return;
                }

                return valorMapeado;
            } else {
                window.location.href = '/painel';
            }
        }


        var valorAposta = <?= json_encode($gameDemo ? 1 : (float)$activeRound['bet']) ?>;
        const aposta = valorAposta;
    </script>


    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="viewport"
        content="height=device-height, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no, minimal-ui, viewport-fit=cover" />
    <link rel="manifest" href="subwaysurfers.webmanifest">
    <link rel="icon" href="assets/images/app-icon-16.png" type="image/png">
    <link rel="apple-touch-icon-precomposed" sizes="114x114" href="assets/images/app-icon-114.png">
    <link rel="apple-touch-icon-precomposed" sizes="72x72" href="assets/images/app-icon-72.png">
    <link rel="apple-touch-icon-precomposed" sizes="57x57" href="assets/images/app-icon-57.png">
    <link rel="apple-touch-icon-precomposed" href="assets/images/app-icon-57.png">
    <script src="<?= app_escape(app_url('adm/assets/libs/jquery/dist/jquery.min.js')) ?>"></script>
    <meta name="robots" content="noindex,nofollow" />
    <title>Subway Run · <?= $gameDemo ? 'Treino' : 'Corrida' ?></title>
    <style>
        body,
        html {
            margin: 0;
            height: 100%;
            background-color: #0b316b;
            overflow: hidden;
            background-image: url('assets/preload/splash.png');
            background-repeat: no-repeat;
            background-position: center;
        }

        #message {
            text-align: center;
            font-size: 8px;
            z-index: 5;
            font-family: "Verdana", sans-serif;
            color: #fff;
            position: fixed;
            width: 100%;
            z-index: 9999;
        }

        .dot {
            display: inline;
            margin-left: 0.2em;
            margin-right: 0.2em;
            position: relative;
            top: -1em;
            font-size: 3.5em;
            opacity: 0;
            animation: showHideDot 2.5s ease-in-out infinite;
        }

        .dot.one {
            animation-delay: 0.2s;
        }

        .dot.two {
            animation-delay: 0.4s;
        }

        .dot.three {
            animation-delay: 0.6s;
        }

        @keyframes showHideDot {
            0% {
                opacity: 0;
            }

            50% {
                opacity: 1;
            }

            60% {
                opacity: 1;
            }

            100% {
                opacity: 0;
            }
        }

        button#sair {
            position: absolute;
            display: none;
            top: 135px;
            left: calc(50% - 130px);
            padding: .85rem 2.8rem;
            line-height: 32px;
            font-size: 18px;
            text-align: center;
            font-weight: 700;
            color: white;
            background-color: #1fbffe;
            box-shadow: -3px 3px 0 0 #1f2024;
            border: 4px solid #1f2024;
            border-radius: 8px;
            transition: background-color .2s ease, transform .2s ease, box-shadow .2s ease;
            z-index: 100000;
        }
    </style>
    <style>
        html,body{background-color:#090b1a;background-image:radial-gradient(ellipse at 50% 0,#36206b 0,transparent 48%),url('assets/preload/splash.png');background-size:cover;background-position:center}
        html body #game-meta-hud{top:auto;bottom:max(18px,env(safe-area-inset-bottom));min-width:min(300px,calc(100vw - 36px));padding:12px 17px;border:1px solid #ffe26d88;border-radius:20px;background:linear-gradient(135deg,#191843f2,#090e24f2);box-shadow:0 0 0 4px #7f52ff29,0 8px 32px #0009,0 0 24px #ffd43b33;backdrop-filter:blur(12px);animation:hudEnter .55s cubic-bezier(.2,1.4,.3,1) both;text-align:center}
        .meta-track{height:7px;margin-top:8px;background:#ffffff27}.meta-fill{background:linear-gradient(90deg,#ffc83d,#fff294);box-shadow:0 0 14px #ffdc58}
        #game-tutorial{background:radial-gradient(ellipse at 50% 38%,#6938c166,transparent 60%),#050817d9!important;backdrop-filter:blur(9px)}
        html body #game-tutorial .tutorial-card{box-sizing:border-box;position:relative;width:min(100%,460px);max-height:calc(100dvh - 40px);overflow-y:auto;padding:clamp(24px,6vw,38px);border:1px solid #a378ff;border-radius:28px;background:linear-gradient(150deg,#242054 0%,#11172e 68%,#10152a);box-shadow:0 28px 90px #000b,0 0 55px #7845ff55;animation:tutorialIn .55s cubic-bezier(.2,1.2,.3,1)}
        #game-tutorial .tutorial-card:before{content:'✦  MODO TESTE  ✦';display:inline-block;margin-bottom:15px;padding:7px 12px;border:1px solid #ffd74c66;border-radius:99px;color:#ffe47c;background:#ffd74c12;font-size:11px;font-weight:900;letter-spacing:.14em}
        html body #game-tutorial h2{font-size:clamp(30px,8vw,40px);color:#fff;text-shadow:0 3px #412289,0 0 25px #9e70ff99}
        #game-tutorial p{color:#cad1ee;font-size:15px;line-height:1.65}.tutorial-step{align-items:center;gap:13px;margin:13px 0;padding:12px;border:1px solid #ffffff17;border-radius:15px;background:#ffffff08;color:#e6e9fa}.tutorial-step b{flex:0 0 32px;width:32px;height:32px;display:grid;place-items:center;border-radius:50%;background:linear-gradient(140deg,#8b55ff,#5129c3);color:#fff;box-shadow:0 4px 14px #6337ce66}
        #game-tutorial button{border:1px solid #fff7!important;border-radius:15px!important;background:linear-gradient(110deg,#ffe56e,#ffb623)!important;color:#211600!important;font-weight:1000!important;box-shadow:0 6px 0 #a6670d,0 12px 28px #ffc33845!important;transition:transform .18s,filter .18s,box-shadow .18s!important;cursor:pointer;width:100%;margin-top:14px;padding:16px 22px;font-size:16px}
        #game-tutorial button:hover{transform:translateY(-3px);filter:brightness(1.08)}#game-tutorial button:active{transform:translateY(3px)}
        #game-meta-hud #sair{position:relative;inset:auto;display:none;width:auto;min-width:148px;height:42px;margin:11px auto 0;padding:0 17px;align-items:center;justify-content:center;gap:8px;border:1px solid #a6ffe8!important;border-radius:13px!important;background:linear-gradient(120deg,#42f4c5,#14c79f)!important;color:#05241c!important;font:900 12px/1 Arial,sans-serif!important;letter-spacing:.03em;box-shadow:0 4px 0 #08745f,0 9px 22px #00d6a53d!important;cursor:pointer;transform:none!important;transition:transform .18s,filter .18s,box-shadow .18s!important}
        #game-meta-hud #sair:hover{transform:translateY(-2px)!important;filter:brightness(1.08)}#game-meta-hud #sair:active{transform:translateY(2px)!important}.rescue-icon{display:grid;place-items:center;width:25px;height:25px;border-radius:8px;background:#06291f16}.rescue-icon svg{width:17px;height:17px}.rescue-label{display:grid;text-align:left;gap:1px}.rescue-label small{font-size:8px;font-weight:700;opacity:.72;letter-spacing:0}
        @keyframes hudEnter{from{opacity:0;transform:translate(-50%,-15px)}to{opacity:1;transform:translate(-50%,0)}}
        @media(prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
    </style>
</head>

<body>
    <style>
        #game-meta-hud{position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:100001;min-width:230px;padding:9px 16px;border-radius:999px;background:linear-gradient(135deg,#172554ee,#0b122bee);color:#fff;font:700 15px Arial;box-shadow:0 0 0 2px rgba(255,193,7,.65),0 8px 24px #0008}.coin-icon{display:inline-grid;place-items:center;width:27px;height:27px;margin-right:7px;border-radius:50%;vertical-align:middle;background:radial-gradient(circle at 35% 30%,#fff3a3 0 8%,#ffd43b 9% 45%,#d99100 46% 78%,#fff0 79%),#f4b400;border:2px solid #ffe88a;box-shadow:inset 0 0 0 2px #bd7600,0 0 12px #ffd43b99;color:#fff8bf;text-shadow:1px 1px #9a5b00;font-size:13px}.meta-track{height:5px;margin-top:5px;border-radius:9px;background:#ffffff24;overflow:hidden}.meta-fill{height:100%;width:0;background:linear-gradient(90deg,#ffe66d,#ffae00);transition:width .25s ease}
        #game-tutorial{position:fixed;inset:0;z-index:100002;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(3,10,27,.78);font-family:Arial;color:#fff}
        #game-tutorial .tutorial-card{max-width:440px;padding:28px;border-radius:24px;background:linear-gradient(145deg,#162e63,#08142e);box-shadow:0 20px 80px #000b,0 0 0 1px #ffffff22;text-align:center;animation:tutorialIn .45s ease-out}
        #game-tutorial h2{margin:0 0 12px;font-size:28px;color:#ffd34e}#game-tutorial p{line-height:1.5;color:#e7efff}.tutorial-step{display:flex;gap:10px;text-align:left;margin:12px 0}.tutorial-step b{color:#ffd34e;font-size:20px}#game-tutorial button{border:0;border-radius:999px;padding:13px 24px;background:#ffc107;color:#211b0b;font-weight:800;font-size:16px;cursor:pointer;box-shadow:0 6px 0 #a87500}@keyframes tutorialIn{from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:none}}
    </style>
    <div id="game-meta-hud"><span class="coin-icon">$</span><span id="game-meta-value">R$ 0,00 / R$ 0,00</span><div class="meta-track"><div id="game-meta-progress" class="meta-fill"></div></div><button id="sair" type="button"><span class="rescue-icon"><?= ui_icon('withdraw') ?></span><span class="rescue-label">Resgatar<small>Encerrar corrida</small></span></button></div>
    <?php if ($gameDemo): ?><div id="game-tutorial"><div class="tutorial-card"><h2>Aprenda a jogar</h2><p>Entre no ritmo da corrida e domine cada movimento antes do próximo desafio.</p><div class="tutorial-step"><b>1</b><span>Deslize para os lados para trocar de trilho e desviar dos obstáculos.</span></div><div class="tutorial-step"><b>2</b><span>Deslize para cima para pular e para baixo para rolar. No computador, use as setas.</span></div><div class="tutorial-step"><b>3</b><span>Cada moeda aumenta seu placar em R$ <?= number_format($gameCoinValue,2,",",".") ?>. Acompanhe seu avanço no painel da corrida.</span></div><button type="button" id="start-tutorial">Começar treinamento</button></div></div><?php endif; ?>
    <script>
        window.NOSW = true;
        window.GAME_CONFIG = {
            leaderboard: 'mockup',
            bundlesPath: './bundles',
        }
        window.GAME_META_MULTIPLIER = <?= json_encode((float)$gameSettings['meta_multiplier']) ?>;
        window.GAME_COIN_VALUE = <?= json_encode($gameCoinValue) ?>;
        window.GAME_DIFFICULTY = <?= json_encode($gameSettings['difficulty']) ?>;
        window.GAME_DEMO = <?= $gameDemo ? 'true' : 'false' ?>;
        window.GAME_SLOW = <?= ($gameDemo || !empty($_SESSION['demo_account'])) ? 'true' : 'false' ?>;
        (function(){var bet=aposta;var target=(bet*window.GAME_META_MULTIPLIER).toFixed(2).replace('.',',');var value=document.getElementById('game-meta-value');if(value)value.textContent='R$ 0,00 / R$ '+target;var start=document.getElementById('start-tutorial');if(start)start.addEventListener('click',function(){document.getElementById('game-tutorial').remove();var boot=document.createElement('script');boot.src='js/boot.js?v=<?= filemtime(__DIR__ . '/js/boot.js') ?>';document.body.appendChild(boot);},{once:true});}());
    </script>
    <div id="message">
        <h1>Loading</h1>
        <h1 class="dot one">.</h1>
        <h1 class="dot two">.</h1>
        <h1 class="dot three">.</h1>
    </div>
    <script src="js/loading.js"></script>
    <?php if (!$gameDemo): ?><script src="js/boot.js?v=<?= filemtime(__DIR__ . '/js/boot.js') ?>"></script><?php endif; ?>
</body>

</html>
