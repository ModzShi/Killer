(()=>{
 const toast=document.querySelector('.payout-toast[data-names]');
 if(!toast)return;
 let names=[];
 try{names=JSON.parse(toast.dataset.names)}catch(error){return}
 if(!names.length)return;
 const name=toast.querySelector('[data-payout-name]');
 const amount=toast.querySelector('[data-payout-amount]');
 const money=new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'});
 let audio=null;

 const nextItem=()=>{
  name.textContent=names[Math.floor(Math.random()*names.length)];
  amount.textContent=money.format(50+Math.random()*750);
 };
 nextItem();

 const unlockAudio=()=>{
  if(!audio){const AudioContextClass=window.AudioContext||window.webkitAudioContext;if(!AudioContextClass)return;audio=new AudioContextClass()}
  if(audio.state==='suspended')audio.resume().catch(()=>{});
 };
 window.addEventListener('pointerdown',unlockAudio,{once:true,passive:true});
 window.addEventListener('keydown',unlockAudio,{once:true});

 const playBubble=()=>{
  if(!audio||audio.state!=='running')return;
  try{
   const now=audio.currentTime;
   const oscillator=audio.createOscillator();
   const filter=audio.createBiquadFilter();
   const gain=audio.createGain();
   oscillator.type='sine';
   oscillator.frequency.setValueAtTime(650,now);
   oscillator.frequency.exponentialRampToValueAtTime(240,now+0.16);
   filter.type='lowpass';filter.frequency.setValueAtTime(1100,now);
   gain.gain.setValueAtTime(0.0001,now);
   gain.gain.exponentialRampToValueAtTime(0.11,now+0.018);
   gain.gain.exponentialRampToValueAtTime(0.0001,now+0.2);
   oscillator.connect(filter);filter.connect(gain);gain.connect(audio.destination);
   oscillator.start(now);oscillator.stop(now+0.21);
  }catch(error){}
 };

 const cycle=()=>window.setTimeout(()=>{
  toast.classList.add('is-exiting');
  window.setTimeout(()=>{
   toast.hidden=true;
   toast.classList.remove('is-exiting');
   window.setTimeout(()=>{
    nextItem();
    toast.hidden=false;
    toast.classList.add('is-entering');
    playBubble();
    window.setTimeout(()=>toast.classList.remove('is-entering'),450);
    cycle();
    },5000);
  },280);
  },3000);
 cycle();
})();
