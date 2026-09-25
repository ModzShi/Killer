(()=>{
  const copy=async(value,button,label='Copiar')=>{try{await navigator.clipboard.writeText(value)}catch(error){const area=document.createElement('textarea');area.value=value;document.body.append(area);area.select();document.execCommand('copy');area.remove()}button.textContent='Copiado ✓';setTimeout(()=>button.textContent=label,1800)};
  document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',()=>copy(button.dataset.copy,button,'Copiar')));
  document.querySelectorAll('[data-copy-credentials]').forEach(button=>button.addEventListener('click',()=>copy(button.dataset.copyCredentials,button,'Copiar acesso')));
  const partnerForm=document.querySelector('form:has(input[name="action"][value="partner"])');
  if(partnerForm){const inputs=[partnerForm.elements.manager_percent,partnerForm.elements.influencer_percent],left=document.getElementById('budget-left'),box=left?.parentElement;const update=()=>{const sum=inputs.reduce((total,input)=>total+(Number(input.value)||0),0),remaining=70-sum,invalid=Math.abs(remaining)>.001;left.textContent=new Intl.NumberFormat('pt-BR',{maximumFractionDigits:2}).format(remaining)+'%';box.classList.toggle('invalid',invalid);partnerForm.querySelector('button.primary').disabled=invalid};inputs.forEach(input=>input.addEventListener('input',update));update()}
  const dialog=document.getElementById('demoEditor');
  document.querySelectorAll('.edit-demo').forEach(button=>button.addEventListener('click',()=>{document.getElementById('demoEmail').value=button.dataset.email;document.getElementById('demoEmailLabel').textContent=button.dataset.email;document.getElementById('demoName').value=button.dataset.name;document.getElementById('demoBalance').value=button.dataset.balance;dialog.showModal()}));
  document.querySelectorAll('[data-close-dialog]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
  dialog?.addEventListener('click',event=>{if(event.target===dialog)dialog.close()});
  document.querySelectorAll('.table-wrap table').forEach(table=>{const labels=Array.from(table.querySelectorAll('thead th'),cell=>cell.textContent.trim());table.querySelectorAll('tbody tr').forEach(row=>Array.from(row.cells).forEach((cell,index)=>cell.dataset.label=labels[index]||''));table.dataset.mobileReady='true'});
})();
