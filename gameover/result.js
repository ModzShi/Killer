(()=>{
  const amount=document.getElementById('amount');
  if(amount&&document.body.classList.contains('win')){
    const target=Number(amount.dataset.value);
    if(Number.isFinite(target)){
      const format=new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'});
      if(matchMedia('(prefers-reduced-motion: reduce)').matches) amount.textContent=format.format(target);
      else {const start=performance.now();const tick=now=>{const t=Math.min(1,(now-start)/1500),e=1-Math.pow(1-t,3);amount.textContent=format.format(target*e);if(t<1)requestAnimationFrame(tick)};setTimeout(()=>requestAnimationFrame(tick),250)}
    }
  }
  const button=document.getElementById('sound'), win=document.body.classList.contains('win');
  let context=null, muted=false, started=false, loop=null;
  function playResult(){
    if(muted||started)return;
    try{
      const C=window.AudioContext||window.webkitAudioContext;if(!C)return;
      context=context||new C();
      const run=()=>{
        if(muted||started)return;started=true;
        const notes=win?[[523,.00,.18],[659,.17,.18],[784,.34,.2],[1047,.55,.48],[784,1.45,.2],[1047,1.7,.2],[1175,1.95,.55]]:[[392,.00,.24],[349,.26,.24],[311,.54,.26],[262,.86,.55],[311,1.55,.25],[294,1.83,.28],[262,2.14,.6]];
        const phrase=()=>notes.forEach(([frequency,delay,duration])=>{const osc=context.createOscillator(),gain=context.createGain(),at=context.currentTime+delay;osc.type=win?'sine':'triangle';osc.frequency.setValueAtTime(frequency,at);gain.gain.setValueAtTime(.0001,at);gain.gain.exponentialRampToValueAtTime(.048,at+.025);gain.gain.exponentialRampToValueAtTime(.0001,at+duration);osc.connect(gain);gain.connect(context.destination);osc.start(at);osc.stop(at+duration+.02)});
        phrase();clearInterval(loop);loop=setInterval(phrase,4200);
        if(button){button.innerHTML=window.RESULT_ICONS.sound;button.setAttribute('aria-label','Silenciar música');button.title='Silenciar música'}
      };
      if(context.state==='suspended') context.resume().then(run).catch(()=>{}); else run();
    }catch(ignore){}
  }
  function unlock(event){if(event?.target?.closest?.('#sound'))return;playResult();window.removeEventListener('pointerdown',unlock);window.removeEventListener('keydown',unlock)}
  playResult();
  window.addEventListener('pointerdown',unlock,{once:true});window.addEventListener('keydown',unlock,{once:true});
  button?.addEventListener('click',()=>{
    if(!started){muted=false;playResult();return}
    muted=!muted;
    if(muted){clearInterval(loop);context.suspend();button.innerHTML=window.RESULT_ICONS.muted;button.setAttribute('aria-label','Ativar música');button.title='Ativar música'}
    else{started=false;context.resume().then(playResult).catch(()=>{})}
  });
  const prompt=document.getElementById('demo-prompt');
  if(prompt){
    const page=document.querySelector('.result-card'),close=prompt.querySelector('.demo-prompt-close');
    let previousFocus=null;
    const dismiss=()=>{prompt.hidden=true;document.body.classList.remove('prompt-open');if(page)page.inert=false;previousFocus?.focus?.()};
    prompt.querySelectorAll('[data-close-prompt]').forEach(control=>control.addEventListener('click',dismiss));
    prompt.addEventListener('keydown',event=>{
      if(event.key==='Escape'){dismiss();return}
      if(event.key!=='Tab')return;
      const focusable=[...prompt.querySelectorAll('a[href],button:not([disabled])')];
      if(!focusable.length)return;
      const first=focusable[0],last=focusable[focusable.length-1];
      if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}
      else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
    });
    setTimeout(()=>{previousFocus=document.activeElement;prompt.hidden=false;document.body.classList.add('prompt-open');if(page)page.inert=true;close?.focus()},4500);
  }
  window.addEventListener('pagehide',()=>{clearInterval(loop);context?.close?.()},{once:true});
})();
