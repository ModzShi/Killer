(()=>{
 const audio=document.getElementById('menuMusicAudio');
 const toggle=document.getElementById('menuMusicToggle');
 if(!audio||!toggle)return;
 const label=toggle.querySelector('.menu-music__label');
 const update=()=>{
  const playing=!audio.paused;
  toggle.setAttribute('aria-pressed',String(playing));
  toggle.setAttribute('aria-label',playing?'Pausar música do menu':'Ativar música do menu');
  label.textContent=playing?'Pausar música':'Ativar música';
 };
 audio.volume=0.28;
 audio.addEventListener('play',update);
 audio.addEventListener('pause',update);
 audio.addEventListener('ended',()=>{audio.currentTime=0;audio.play().catch(update)});
 toggle.addEventListener('click',()=>{
  if(audio.paused){audio.play().then(()=>{try{localStorage.setItem('menuMusicEnabled','1')}catch(error){};update()}).catch(update)}
  else{audio.pause();try{localStorage.setItem('menuMusicEnabled','0')}catch(error){};update()}
 });
 let shouldPlay=true;
 try{shouldPlay=localStorage.getItem('menuMusicEnabled')!=='0'}catch(error){}
 if(shouldPlay)audio.play().then(update).catch(update);
})();
