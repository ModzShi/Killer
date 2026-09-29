(()=>{
 const valid=['default','gold','red','purple','yellow'];
 const apply=(theme)=>{
  const chosen=valid.includes(theme)?theme:'default';
  document.body?.classList.add('sk-premium');
  document.documentElement.setAttribute('data-sr-theme',chosen);
  document.querySelectorAll('[data-theme-choice]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.themeChoice===chosen)));
 };
 let saved='default';
 try{saved=localStorage.getItem('sr-theme')||'default'}catch(e){}
 apply(saved);
 document.addEventListener('click',event=>{
  const button=event.target.closest('[data-theme-choice]');
  if(!button||!valid.includes(button.dataset.themeChoice))return;
  apply(button.dataset.themeChoice);
  try{localStorage.setItem('sr-theme',button.dataset.themeChoice)}catch(e){}
 });
})();
