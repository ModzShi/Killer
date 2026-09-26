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
                    case '5BC':
                        valorMapeado = 5;
                        break;
                    case '10BC':
                        valorMapeado = 10;
                        break;
                    case '20BC':
                        valorMapeado = 20;
                        break;
                    case '30BC':
                        valorMapeado = 30;
                        break;
                    case '50BC':
                        valorMapeado = 50;
                        break;
                    case '100BC':
                        valorMapeado = 100;
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
        html body #game-meta-hud{top:max(6px,env(safe-area-inset-top));bottom:auto;left:50%;transform:translateX(-50%);width:min(232px,calc(100vw - 32px));min-width:0;display:flex;flex-direction:column;align-items:center;gap:5px;padding:7px 10px 9px;border:1px solid #b29aff55;border-radius:17px;background:linear-gradient(135deg,#11162be8,#171333e8);box-shadow:0 7px 24px #0007,inset 0 1px #ffffff22;backdrop-filter:blur(10px);text-align:center}
        #game-meta-value{color:#fff;font:1000 clamp(17px,4.7vw,21px)/1.1 Inter,Arial,sans-serif;letter-spacing:-.035em;text-shadow:0 1px 10px #c4a0ff55;font-variant-numeric:tabular-nums;white-space:nowrap}.coin-icon{width:20px;height:20px;margin:0 5px 0 0;font-size:10px}
        .meta-track{width:100%;height:5px;margin-top:1px;border-radius:99px;background:#ffffff25;overflow:hidden}.meta-fill{border-radius:inherit;background:linear-gradient(90deg,#7c5bff,#45f2c1,#ffe36c);background-size:200% 100%;box-shadow:0 0 12px #55ebc9aa;transition:width .25s ease}
        html body #game-meta-hud #sair{position:relative;inset:auto;display:none;width:min(164px,100%);min-width:0;height:37px;margin:2px auto 0;padding:0 13px;align-items:center;justify-content:center;gap:7px;overflow:hidden;border:1px solid #c6ffebbb!important;border-radius:12px!important;background:linear-gradient(110deg,#58f5c5,#19d7a5,#9cf477,#58f5c5)!important;background-size:240% 100%!important;color:#063126!important;font:1000 12px/1 Arial,sans-serif!important;letter-spacing:.07em;box-shadow:0 3px 0 #08745f,0 7px 18px #00d6a53d!important;cursor:pointer;transform:none!important;animation:rescueShine 3.1s ease-in-out infinite;transition:transform .18s,filter .18s!important}
        html body #game-meta-hud #sair:hover{transform:translateY(-1px)!important;filter:brightness(1.07)}html body #game-meta-hud #sair:active{transform:translateY(2px)!important}.rescue-icon{width:20px;height:20px;border-radius:7px}.rescue-icon svg{width:15px;height:15px}.rescue-label{display:block;text-align:center;font-size:12px;font-weight:1000;letter-spacing:.07em;text-transform:uppercase}.rescue-label small{display:none}
        @keyframes rescueShine{0%,70%,100%{background-position:0% 50%}85%{background-position:100% 50%}}@media(max-width:480px){html body #game-meta-hud{top:max(4px,env(safe-area-inset-top));width:min(212px,calc(100vw - 36px));padding:6px 9px 8px}#game-meta-value{font-size:18px}html body #game-meta-hud #sair{height:35px;width:148px}}@media(prefers-reduced-motion:reduce){html body #game-meta-hud #sair{animation:none}}
        #rescue-action{position:fixed;left:50%;bottom:max(22px,env(safe-area-inset-bottom));transform:translateX(-50%);z-index:100001;width:min(248px,calc(100vw - 40px));display:flex;justify-content:center;pointer-events:none}
        #rescue-action #sair{position:static!important;inset:auto!important;display:none;width:100%;min-width:0;height:52px;margin:0;padding:0 22px;align-items:center;justify-content:center;gap:10px;border:1px solid #fff0a8!important;border-radius:16px!important;background:linear-gradient(105deg,#ffe98a,#ffc52e 48%,#ffdc68)!important;background-size:200% 100%!important;color:#322000!important;font:950 14px/1 Inter,Arial,sans-serif!important;letter-spacing:.06em;box-shadow:0 4px 0 #a66b12,0 10px 28px #ffc83c66!important;cursor:pointer;pointer-events:auto;animation:rescueShine 3.1s ease-in-out infinite;transition:transform .18s,filter .18s!important}
        #rescue-action #sair:hover{transform:translateY(-2px)!important;filter:brightness(1.07)}#rescue-action #sair:active{transform:translateY(2px)!important}#rescue-action .rescue-icon{width:24px;height:24px;border-radius:8px;background:#5f3c1012}#rescue-action .rescue-icon svg{width:18px;height:18px}@media(max-width:480px){#rescue-action{width:min(268px,calc(100vw - 44px));bottom:max(18px,env(safe-area-inset-bottom))}#rescue-action #sair{height:54px;font-size:15px!important}}
        html body #game-tutorial .tutorial-card{padding:22px;max-width:380px}.tutorial-step{margin:8px 0;padding:9px}.tutorial-step span{font-size:13px;line-height:1.4}
        #game-tutorial .tutorial-card{max-width:440px;padding:28px;border-radius:24px;background:linear-gradient(145deg,#162e63,#08142e);box-shadow:0 20px 80px #000b,0 0 0 1px #ffffff22;text-align:center;animation:tutorialIn .45s ease-out}
        #game-tutorial h2{margin:0 0 12px;font-size:28px;color:#ffd34e}#game-tutorial p{line-height:1.5;color:#e7efff}.tutorial-step{display:flex;gap:10px;text-align:left;margin:12px 0}.tutorial-step b{color:#ffd34e;font-size:20px}#game-tutorial button{border:0;border-radius:999px;padding:13px 24px;background:#ffc107;color:#211b0b;font-weight:800;font-size:16px;cursor:pointer;box-shadow:0 6px 0 #a87500}@keyframes tutorialIn{from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:none}}
    </style>
    <div id="game-meta-hud"><span class="coin-icon">$</span><span id="game-meta-value">R$ 0,00 / R$ 0,00</span><div class="meta-track"><div id="game-meta-progress" class="meta-fill"></div></div></div>
    <div id="rescue-action"><button id="sair" type="button"><span class="rescue-icon"><?= ui_icon('withdraw') ?></span><span class="rescue-label">Resgatar</span></button></div>
    <?php if ($gameDemo): ?><div id="game-tutorial"><div class="tutorial-card"><h2>Pronto para correr?</h2><p>Desvie dos obstáculos e colete moedas. Neste treino, cada moeda soma R$ <?= number_format($gameCoinValue,2,",",".") ?> à pontuação simulada, sem valor para saque.</p><div class="tutorial-step"><b>↔</b><span>Deslize para trocar de trilho.</span></div><div class="tutorial-step"><b>↑</b><span>Deslize para pular; para baixo, role.</span></div><button type="button" id="start-tutorial">Começar treino</button></div></div><?php endif; ?>
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
