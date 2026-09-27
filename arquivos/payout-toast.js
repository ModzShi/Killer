(()=>{
 const toast=document.querySelector('.payout-toast[data-payouts]');
 if(!toast)return;
 let items=[];
 try{items=JSON.parse(toast.dataset.payouts)}catch(error){return}
 if(!items.length)return;
 const name=toast.querySelector('[data-payout-name]');
 const amount=toast.querySelector('[data-payout-amount]');
 let index=0;
 let audio=null;

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
    index=(index+1)%items.length;
    name.textContent=items[index].name;
    amount.textContent=items[index].amount;
    toast.hidden=false;
    toast.classList.add('is-entering');
    playBubble();
    window.setTimeout(()=>toast.classList.remove('is-entering'),450);
    cycle();
   },2000);
  },280);
 },2720);
 cycle();
})();
