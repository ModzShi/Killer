(()=>{
 const audio=document.getElementById('menuMusicAudio');
 const toggle=document.getElementById('profileMusicToggle');
 if(!audio)return;
 const label=toggle?.querySelector('.menu-music__label');
 const update=()=>{
  if(!toggle)return;
  const playing=!audio.paused;
  toggle.setAttribute('aria-pressed',String(playing));
  toggle.setAttribute('aria-label',playing?'Pausar música do menu':'Ativar música do menu');
  if(label)label.textContent=playing?'Música ativada':'Ativar música';
 };
 audio.volume=0.28;
 audio.addEventListener('play',update);
 audio.addEventListener('pause',update);
 toggle?.addEventListener('click',()=>{
  if(audio.paused){audio.play().then(()=>{try{localStorage.setItem('menuMusicEnabled','1')}catch(e){};update()}).catch(update)}
  else{audio.pause();try{localStorage.setItem('menuMusicEnabled','0')}catch(e){};update()}
 });
 let enabled=true;
 try{enabled=localStorage.getItem('menuMusicEnabled')!=='0'}catch(e){}
 const start=()=>audio.play().then(update).catch(update);
 if(enabled){
  start();
  const resume=()=>{if(!audio.paused)return;start()};
  document.addEventListener('pointerdown',resume,{once:true,passive:true});
  document.addEventListener('keydown',resume,{once:true});
 }
})();
