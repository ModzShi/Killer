(()=>{
 const audio=document.getElementById('menuMusicAudio');
 const toggle=document.getElementById('profileMusicToggle');
 if(!audio)return;
 const label=toggle?.querySelector('.menu-music__label');
 const positionKey='menuMusicPosition';
 let enabled=true;
 let wasPlaying=false;
 try{enabled=localStorage.getItem('menuMusicEnabled')!=='0'}catch(e){}
 const update=()=>{
  if(!toggle)return;
  const playing=!audio.paused;
  toggle.setAttribute('aria-pressed',String(playing));
  toggle.setAttribute('aria-label',playing?'Pausar música do menu':'Ativar música do menu');
  if(label)label.textContent=playing?'Música ativada':'Ativar música';
 };
 const savePosition=()=>{
  if(!Number.isFinite(audio.currentTime))return;
  try{sessionStorage.setItem(positionKey,String(audio.currentTime))}catch(e){}
 };
 const start=()=>audio.play().then(update).catch(update);
 audio.volume=0.28;
 audio.addEventListener('play',update);
 audio.addEventListener('pause',()=>{savePosition();update()});
 audio.addEventListener('timeupdate',savePosition);
 const restoreAndStart=()=>{
  let saved=0;
  try{saved=parseFloat(sessionStorage.getItem(positionKey)||'0')}catch(e){}
  if(Number.isFinite(saved)&&saved>0&&Number.isFinite(audio.duration)&&audio.duration>0){
   audio.currentTime=saved>=audio.duration?saved%audio.duration:saved;
  }
  if(enabled)start();
 };
 if(audio.readyState>=1)restoreAndStart();
 else audio.addEventListener('loadedmetadata',restoreAndStart,{once:true});
 window.addEventListener('pagehide',savePosition);
 document.addEventListener('visibilitychange',()=>{
  if(document.visibilityState==='hidden'){wasPlaying=!audio.paused;savePosition()}
  else if(wasPlaying&&enabled){wasPlaying=false;start()}
 });
 toggle?.addEventListener('click',()=>{
  if(audio.paused){enabled=true;try{localStorage.setItem('menuMusicEnabled','1')}catch(e){};start()}
  else{enabled=false;wasPlaying=false;try{localStorage.setItem('menuMusicEnabled','0')}catch(e){};audio.pause();update()}
 });
 const resume=()=>{if(enabled&&audio.paused)start()};
 document.addEventListener('pointerdown',resume,{once:true,passive:true});
 document.addEventListener('keydown',resume,{once:true});
})();
