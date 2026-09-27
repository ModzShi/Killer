(()=>{
 const audio=document.getElementById('menuMusicAudio');
 const toggle=document.getElementById('profileMusicToggle');
 if(!audio)return;

 const label=toggle?.querySelector('.menu-music__label');
 const positionKey='menuMusicPosition';
 let enabled=true;
 let wasPlaying=false;
 let lastSavedPosition=0;
 let leavingPage=false;

 const readPosition=()=>{
  try{
   const saved=Number.parseFloat(sessionStorage.getItem(positionKey)||'0');
   return Number.isFinite(saved)&&saved>0?saved:0;
  }catch(e){return 0}
 };
 const savePosition=(allowZero=false)=>{
  const position=Number(audio.currentTime);
  if(!Number.isFinite(position))return;
  // Some browsers reset currentTime while tearing down a page. Keep the last
  // valid checkpoint instead of replacing it with that teardown value.
  if(position<.08&&!allowZero&&lastSavedPosition>.5)return;
  lastSavedPosition=position;
  try{sessionStorage.setItem(positionKey,String(position))}catch(e){}
 };
 const update=()=>{
  if(!toggle)return;
  const playing=!audio.paused;
  toggle.setAttribute('aria-pressed',String(playing));
  toggle.setAttribute('aria-label',playing?'Pausar música do menu':'Ativar música do menu');
  if(label)label.textContent=playing?'Música ativada':'Ativar música';
 };
 const start=()=>audio.play().then(update).catch(update);
 const restorePosition=()=>{
  const saved=readPosition();
  lastSavedPosition=saved;
  if(saved<=0||!Number.isFinite(audio.duration)||audio.duration<=0)return;
  const position=saved>=audio.duration?saved%audio.duration:saved;
  try{audio.currentTime=position}catch(e){}
 };
 const restoreAndStart=()=>{
  restorePosition();
  if(enabled)start();
 };

 try{enabled=localStorage.getItem('menuMusicEnabled')!=='0'}catch(e){}
 audio.volume=.28;
 audio.addEventListener('play',update);
 audio.addEventListener('pause',()=>{if(!leavingPage)savePosition();update()});
 audio.addEventListener('timeupdate',()=>savePosition());
 if(audio.readyState>=1)restoreAndStart();
 else audio.addEventListener('loadedmetadata',restoreAndStart,{once:true});

 window.addEventListener('pagehide',()=>{leavingPage=true;savePosition()});
 document.addEventListener('visibilitychange',()=>{
  if(document.visibilityState==='hidden'){
   wasPlaying=!audio.paused;
   savePosition();
   return;
  }
  if(!wasPlaying||!enabled)return;
  wasPlaying=false;
  if(audio.paused){
   const saved=readPosition();
   if(saved>0&&audio.currentTime<saved-1){
    try{audio.currentTime=Number.isFinite(audio.duration)&&audio.duration>0?saved%audio.duration:saved}catch(e){}
   }
   start();
  }
 });

 toggle?.addEventListener('click',()=>{
  if(audio.paused){
   enabled=true;
   try{localStorage.setItem('menuMusicEnabled','1')}catch(e){}
   start();
  }else{
   enabled=false;
   wasPlaying=false;
   savePosition(true);
   try{localStorage.setItem('menuMusicEnabled','0')}catch(e){}
   audio.pause();
   update();
  }
 });

 const resume=()=>{if(enabled&&audio.paused)start()};
 document.addEventListener('pointerdown',resume,{once:true,passive:true});
 document.addEventListener('keydown',resume,{once:true});
})();
