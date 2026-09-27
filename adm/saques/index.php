<?php
require_once __DIR__ . '/../../app/bootstrap.php';
if (empty($_SESSION['emailadm'])) { header('Location: ../login/'); exit; }
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Saques • Administração</title>
    <link rel="stylesheet" href="../assets/libs/flot/css/float-chart.css">
    <link rel="stylesheet" href="../dist/css/style.min.css">
    <style>
        .withdraw-admin{max-width:1440px;margin:0 auto;padding:30px clamp(14px,3vw,34px) 48px;color:#eef0ff}
        .withdraw-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:24px}
        .withdraw-head h1{margin:4px 0 8px;color:#fff;font-size:clamp(25px,4vw,36px);font-weight:850;letter-spacing:-.04em}
        .withdraw-head p{margin:0;color:#b9c2dc}.withdraw-kicker{color:#a98aff;font-size:11px;font-weight:850;letter-spacing:.16em}
        .withdraw-refresh{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:0 15px;border:1px solid #69579a;border-radius:12px;background:#201b38;color:#f5f1ff;font-weight:750;cursor:pointer}
        .withdraw-refresh:hover{border-color:#b496ff;background:#2c2250}
        .withdraw-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px}
        .withdraw-stat{padding:17px 19px;border:1px solid #ffffff18;border-radius:16px;background:linear-gradient(145deg,#1b2038,#14172b);box-shadow:0 12px 28px #0002}
        .withdraw-stat span{display:block;color:#b8bfd9;font-size:12px;font-weight:700}.withdraw-stat strong{display:block;margin-top:8px;color:#fff;font-size:27px;font-variant-numeric:tabular-nums}
        .withdraw-stat--pending{border-color:#e9b75055}.withdraw-stat--pending strong{color:#ffd875}.withdraw-stat--paid strong{color:#62e4b3}.withdraw-stat--rejected strong{color:#ff8794}
        .withdraw-panel{overflow:hidden;border:1px solid #483969;border-radius:18px;background:linear-gradient(145deg,#1a1e35,#121527);box-shadow:0 18px 40px #0003}
        .withdraw-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:17px 19px;border-bottom:1px solid #ffffff14}
        .withdraw-filters{display:flex;gap:7px;flex-wrap:wrap}.withdraw-filter{min-height:36px;padding:0 12px;border:1px solid #ffffff20;border-radius:10px;background:#171a2d;color:#c8cce0;font-size:12px;font-weight:750;cursor:pointer}
        .withdraw-filter[aria-pressed="true"]{border-color:#a78aff;background:#382b5b;color:#fff}
        .withdraw-search{width:min(100%,300px);min-height:40px;padding:0 13px;border:1px solid #514477;border-radius:11px;background:#101326;color:#fff;outline:none}.withdraw-search::placeholder{color:#9fa8c3}.withdraw-search:focus{border-color:#ac91ff;box-shadow:0 0 0 3px #8c55ff30}
        .withdraw-table-wrap{width:100%;overflow-x:auto}.withdraw-table{width:100%;min-width:930px;border-collapse:collapse;color:#eef0ff}.withdraw-table th{padding:13px 14px;background:#171a2d;color:#aaaed0;text-align:left;font-size:10px;letter-spacing:.1em;text-transform:uppercase;white-space:nowrap}.withdraw-table td{padding:14px;border-top:1px solid #ffffff12;vertical-align:middle}.withdraw-table tbody tr:hover{background:#ffffff06}
        .withdraw-person{display:grid;gap:4px}.withdraw-person strong{color:#fff;font-size:13px}.withdraw-person small{color:#aeb6d0;font-size:11px}.withdraw-amount{color:#73ecc2;font-size:15px;font-weight:850;white-space:nowrap}.withdraw-type{display:inline-flex;padding:5px 9px;border:1px solid #8f77d955;border-radius:999px;background:#392b572e;color:#d7c8ff;font-size:10px;font-weight:800}.withdraw-type--affiliate{border-color:#39d9be55;background:#163a3b55;color:#77efda}
        .withdraw-status{display:inline-flex;padding:6px 9px;border-radius:999px;background:#34334b;color:#d9d9eb;font-size:10px;font-weight:800;white-space:nowrap}.withdraw-status--pending{background:#4d3b1d;color:#ffdc86}.withdraw-status--paid{background:#153f35;color:#77ebbe}.withdraw-status--rejected{background:#4a2634;color:#ff9bab}.withdraw-status--processing{background:#263b57;color:#9ec9ff}
        .withdraw-pix{display:flex;align-items:center;gap:7px;max-width:225px}.withdraw-pix span{overflow:hidden;color:#d4d8e9;font-size:12px;text-overflow:ellipsis;white-space:nowrap}.withdraw-copy{flex:0 0 auto;padding:5px 7px;border:1px solid #ffffff20;border-radius:8px;background:#20243b;color:#d9ddf2;font-size:10px;font-weight:750;cursor:pointer}.withdraw-copy:hover{border-color:#9e85ea}
        .withdraw-actions{display:flex;align-items:center;gap:7px;min-width:245px}.withdraw-select{min-width:155px;min-height:38px;padding:0 9px;border:1px solid #554879;border-radius:9px;background:#11152a;color:#f4f3ff}.withdraw-save{min-height:38px;padding:0 12px;border:1px solid #ffffff43;border-radius:9px;background:linear-gradient(120deg,#a78aff,#6c54cd);color:#fff;font-size:11px;font-weight:850;cursor:pointer}.withdraw-save:hover{filter:brightness(1.12);transform:translateY(-1px)}.withdraw-save:disabled{opacity:.55;cursor:wait;transform:none}
        .withdraw-feedback{display:block;min-height:14px;margin-top:4px;color:#9da7c4;font-size:10px}.withdraw-feedback.is-error{color:#ff9bab}.withdraw-feedback.is-ok{color:#70e5b5}
        .withdraw-state{padding:42px 18px;color:#aeb7d2;text-align:center}.withdraw-state strong{display:block;margin-bottom:6px;color:#fff;font-size:15px}.withdraw-state.is-error strong{color:#ff9bab}
        @media(max-width:720px){.withdraw-admin{padding-top:20px}.withdraw-head{align-items:center}.withdraw-head p{font-size:13px}.withdraw-refresh{flex:0 0 auto;padding:0 11px}.withdraw-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.withdraw-stat{padding:13px}.withdraw-stat strong{font-size:23px}.withdraw-toolbar{align-items:stretch}.withdraw-search{width:100%;order:-1}.withdraw-filters{width:100%;overflow:auto;flex-wrap:nowrap;padding-bottom:2px}.withdraw-filter{white-space:nowrap}}
        @media(prefers-reduced-motion:reduce){.withdraw-save:hover{transform:none}}
    </style>
</head>
<body>
<div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="full" data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full">
    <header class="topbar" data-navbarbg="skin5"><nav class="navbar top-navbar navbar-expand-md navbar-dark"><div class="navbar-header" data-logobg="skin5"><a class="navbar-brand" href="<?= app_escape(app_url('adm/')) ?>"><b class="logo-icon ps-2"><span class="text-white font-20 font-weight-bold">Administração</span></b></a><a class="nav-toggler waves-effect waves-light d-block d-md-none" href="javascript:void(0)" aria-label="Abrir menu"><i class="ti-menu ti-close"></i></a></div><div class="navbar-collapse collapse" id="navbarSupportedContent"><ul class="navbar-nav float-start me-auto"><li class="nav-item d-none d-lg-block"><a class="nav-link sidebartoggler waves-effect waves-light" href="javascript:void(0)" data-sidebartype="mini-sidebar" aria-label="Recolher menu"><i class="mdi mdi-menu font-24"></i></a></li></ul></div></nav></header>
    <?php include __DIR__ . '/../components/aside.php'; ?>
    <div class="page-wrapper"><main class="withdraw-admin">
        <header class="withdraw-head"><div><span class="withdraw-kicker">FINANCEIRO · APROVAÇÃO MANUAL</span><h1>Solicitações de saque</h1><p>Confira os dados e registre o resultado de cada solicitação.</p></div><button class="withdraw-refresh" id="withdrawRefresh" type="button">↻ <span>Atualizar</span></button></header>
        <section class="withdraw-stats" aria-label="Resumo de saques"><article class="withdraw-stat withdraw-stat--pending"><span>Aguardando análise</span><strong id="countPending">—</strong></article><article class="withdraw-stat"><span>Em processamento</span><strong id="countProcessing">—</strong></article><article class="withdraw-stat withdraw-stat--paid"><span>Pagos</span><strong id="countPaid">—</strong></article><article class="withdraw-stat withdraw-stat--rejected"><span>Rejeitados</span><strong id="countRejected">—</strong></article></section>
        <section class="withdraw-panel" aria-label="Lista de solicitações"><div class="withdraw-toolbar"><div class="withdraw-filters" role="group" aria-label="Filtrar saques"><button class="withdraw-filter" type="button" data-filter="all" aria-pressed="true">Todos</button><button class="withdraw-filter" type="button" data-filter="pending" aria-pressed="false">Aguardando</button><button class="withdraw-filter" type="button" data-filter="processing" aria-pressed="false">Em processamento</button><button class="withdraw-filter" type="button" data-filter="paid" aria-pressed="false">Pagos</button><button class="withdraw-filter" type="button" data-filter="rejected" aria-pressed="false">Rejeitados</button></div><input class="withdraw-search" id="withdrawSearch" type="search" placeholder="Buscar por nome, e-mail ou chave PIX" aria-label="Buscar solicitações"></div>
        <div class="withdraw-table-wrap"><table class="withdraw-table"><thead><tr><th>Solicitante</th><th>Tipo</th><th>Chave PIX</th><th>Valor</th><th>Status</th><th>Atualizar status</th></tr></thead><tbody id="withdrawRows"><tr><td colspan="6"><div class="withdraw-state">Carregando solicitações…</div></td></tr></tbody></table></div></section>
        <form hidden id="csrfSource"></form>
        <footer class="footer text-center">Painel Administrativo</footer>
    </main></div>
</div>
<script src="../assets/libs/jquery/dist/jquery.min.js"></script><script src="../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script><script src="../assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js"></script><script src="../dist/js/waves.js"></script><script src="../dist/js/sidebarmenu.js"></script><script src="../dist/js/custom.min.js"></script>
<script>
(()=>{
 const rowsEl=document.getElementById('withdrawRows'), search=document.getElementById('withdrawSearch');
 const statuses=['Aguardando Aprovação','Processando','Aprovado','Pago','Rejeitado']; let activeFilter='all', rows=[];
 const norm=s=>String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
 const bucket=s=>{const n=norm(s);if(n.includes('rejeit'))return'rejected';if(n==='pago'||n==='paid')return'paid';if(n.includes('process'))return'processing';if(n.includes('aprov')||n.includes('pend')||n.includes('aguard'))return'pending';return'other'};
 const money=v=>{const n=Number(String(v??0).replace(',','.'));return Number.isFinite(n)?n.toLocaleString('pt-BR',{style:'currency',currency:'BRL'}):String(v??'—')};
 const node=(tag,cls,text)=>{const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el};
 const cell=(row)=>{
  const tr=document.createElement('tr');tr.dataset.type=row.tipo||'Jogo';tr.dataset.id=String(row.id_registro??'');tr.dataset.status=bucket(row.status);
  const person=node('td');const identity=node('div','withdraw-person');identity.append(node('strong','',row.destino||'Nome não informado'),node('small','',row.email||'E-mail não informado'));person.append(identity);
  const type=node('td');type.append(node('span','withdraw-type'+(row.tipo==='Afiliado'?' withdraw-type--affiliate':''),row.tipo==='Afiliado'?'Afiliado':'Jogo'));
  const pix=node('td');const pixWrap=node('div','withdraw-pix');pixWrap.append(node('span','',row.chavepix||'Não informada'));if(row.chavepix&&row.chavepix!=='-'){const copy=node('button','withdraw-copy','Copiar');copy.type='button';copy.dataset.copy=row.chavepix;pixWrap.append(copy)}pix.append(pixWrap);
  const amount=node('td');amount.append(node('strong','withdraw-amount',money(row.valor)));
  const state=node('td');state.append(node('span','withdraw-status withdraw-status--'+bucket(row.status),row.status||'Sem status'));
  const action=node('td');const controls=node('div','withdraw-actions');const select=node('select','withdraw-select');select.setAttribute('aria-label','Novo status');statuses.forEach(status=>{const option=node('option','',status==='Pago'?'Pago (transferência concluída)':status);option.value=status;option.selected=norm(row.status)===norm(status);select.append(option)});const save=node('button','withdraw-save','Salvar');save.type='button';const feedback=node('small','withdraw-feedback','');controls.append(select,save);action.append(controls,feedback);save.addEventListener('click',()=>updateStatus(tr,select,save,feedback));
  tr.append(person,type,pix,amount,state,action);return tr;
 };
 const render=()=>{
  rowsEl.replaceChildren();const query=norm(search.value);let shown=0;
  rows.forEach(row=>{const tr=cell(row);const searchable=norm([row.email,row.destino,row.chavepix,row.valor,row.tipo].join(' '));if((activeFilter==='all'||bucket(row.status)===activeFilter)&&(!query||searchable.includes(query))){rowsEl.append(tr);shown++}});
  if(!shown){const tr=document.createElement('tr'),td=node('td');td.colSpan=6;td.append(node('div','withdraw-state',rows.length?'Nenhuma solicitação corresponde a esse filtro.':'Nenhuma solicitação de saque encontrada.'));tr.append(td);rowsEl.append(tr)}
  const counts={pending:0,processing:0,paid:0,rejected:0};rows.forEach(r=>{const b=bucket(r.status);if(Object.prototype.hasOwnProperty.call(counts,b))counts[b]++});
  document.getElementById('countPending').textContent=counts.pending;document.getElementById('countProcessing').textContent=counts.processing;document.getElementById('countPaid').textContent=counts.paid;document.getElementById('countRejected').textContent=counts.rejected;
 };
 const load=async()=>{rowsEl.innerHTML='<tr><td colspan="6"><div class="withdraw-state">Carregando solicitações…</div></td></tr>';try{const response=await fetch('bd.php',{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)throw new Error('Não foi possível carregar os saques.');const data=await response.json();if(!Array.isArray(data))throw new Error('A resposta recebida não é válida.');rows=data;render()}catch(error){rows=[];const tr=document.createElement('tr'),td=node('td');td.colSpan=6;const state=node('div','withdraw-state is-error');state.append(node('strong','',error.message||'Falha ao carregar saques.'),node('span','','Tente atualizar a lista em alguns instantes.'));td.append(state);tr.append(td);rowsEl.replaceChildren(tr)}};
 const updateStatus=async(tr,select,button,feedback)=>{const next=select.value;if(next==='Pago'&&!window.confirm('Confirma que a transferência PIX foi realizada e deseja marcar este saque como pago?'))return;button.disabled=true;feedback.className='withdraw-feedback';feedback.textContent='Salvando…';try{const body=new URLSearchParams({id:tr.dataset.id,novoStatus:next,tipo:tr.dataset.type});const token=document.querySelector('#csrfSource input[name="csrf"]')?.value||'';const response=await fetch('atualizar_status.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-CSRF-Token':token},body});const result=(await response.text()).trim();if(!response.ok)throw new Error(result||'Não foi possível salvar o status.');if(/erro|falha|não autorizado/i.test(result))throw new Error(result);const row=rows.find(r=>String(r.id_registro)===tr.dataset.id&&String(r.tipo||'Jogo')===tr.dataset.type);if(row)row.status=next;feedback.className='withdraw-feedback is-ok';feedback.textContent='Status atualizado.';render()}catch(error){feedback.className='withdraw-feedback is-error';feedback.textContent=error.message||'Erro ao salvar.'}finally{button.disabled=false}};
 document.querySelectorAll('.withdraw-filter').forEach(button=>button.addEventListener('click',()=>{activeFilter=button.dataset.filter;document.querySelectorAll('.withdraw-filter').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));render()}));
 search.addEventListener('input',render);document.getElementById('withdrawRefresh').addEventListener('click',load);
 rowsEl.addEventListener('click',async event=>{const button=event.target.closest('[data-copy]');if(!button)return;try{await navigator.clipboard.writeText(button.dataset.copy);button.textContent='Copiado';setTimeout(()=>button.textContent='Copiar',1300)}catch(e){button.textContent='Falhou';setTimeout(()=>button.textContent='Copiar',1300)}});
 load();
})();
</script>
</body>
</html>
