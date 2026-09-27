<audio id="menuMusicAudio" src="<?= app_escape(app_url('arquivos/audio/subway-menu.mp3')) ?>" preload="none" loop></audio>
<button class="menu-music" type="button" id="menuMusicToggle" aria-pressed="false" aria-label="Ativar música do menu">
    <span class="menu-music__icon" aria-hidden="true"><?= ui_icon('sound') ?></span>
    <span class="menu-music__label">Ativar música</span>
</button>
<style>
.menu-music{position:fixed;z-index:9980;left:max(14px,env(safe-area-inset-left));bottom:calc(18px + env(safe-area-inset-bottom));display:flex;align-items:center;gap:9px;min-height:44px;padding:0 14px;border:1px solid #8b72ee77;border-radius:999px;background:linear-gradient(120deg,#211942f5,#11182bf5);box-shadow:0 9px 28px #0007,0 0 18px #8768ef24;color:#f4f0ff;font:750 12px/1 system-ui,-apple-system,"Segoe UI",sans-serif;cursor:pointer;transition:transform .18s,border-color .18s,background .18s}.menu-music:hover{transform:translateY(-2px);border-color:#50e7c1;background:linear-gradient(120deg,#183b3b,#171a35)}.menu-music:focus-visible{outline:3px solid #76f0c9;outline-offset:3px}.menu-music__icon{display:grid;place-items:center;color:#67edc7}.menu-music__icon svg{width:18px;height:18px}.menu-music[aria-pressed=true] .menu-music__icon{animation:menu-music-pulse 1.5s ease-in-out infinite}@keyframes menu-music-pulse{50%{transform:scale(1.12);filter:drop-shadow(0 0 5px #61efc4)}}@media(max-width:680px){.menu-music{left:max(12px,env(safe-area-inset-left));bottom:calc(14px + env(safe-area-inset-bottom));min-height:42px;padding:0 12px}}@media(prefers-reduced-motion:reduce){.menu-music,.menu-music__icon{animation:none!important;transition:none!important}}
</style>
<script src="<?= app_escape(app_url('arquivos/menu-music.js')) ?>?v=1" defer></script>
