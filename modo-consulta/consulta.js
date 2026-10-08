(() => {
  const search = document.getElementById('consulta-search');
  const cards = Array.from(document.querySelectorAll('.consulta-card'));
  const empty = document.getElementById('consulta-empty');
  const categories = Array.from(document.querySelectorAll('.consulta-categories button[data-category]'));
  if (!search || !cards.length) return;

  const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');
  let category = 'all';
  const update = () => {
    const term = normalize(search.value.trim());
    let visible = 0;
    cards.forEach(card => {
      const matchesCategory = category === 'all' || card.dataset.category === category;
      const matchesText = !term || normalize(card.dataset.search || '').includes(term);
      card.hidden = !(matchesCategory && matchesText);
      if (!card.hidden) visible++;
    });
    if (empty) empty.hidden = visible !== 0;
  };
  search.addEventListener('input', update);
  categories.forEach(button => button.addEventListener('click', () => {
    category = button.dataset.category || 'all';
    categories.forEach(item => {
      const active = item === button;
      item.classList.toggle('active', active);
      item.setAttribute('aria-pressed', String(active));
    });
    update();
  }));
  document.addEventListener('keydown', event => {
    if (event.key === '/' && document.activeElement !== search && !['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
      event.preventDefault();
      search.focus();
    }
    if (event.key === 'Escape' && document.activeElement === search) {
      search.value = '';
      search.blur();
      update();
    }
  });

  const form = document.getElementById('consulta-form');
  const query = document.getElementById('consulta-query');
  const value = document.getElementById('consulta-value');
  const valueLabel = document.getElementById('consulta-value-label');
  const message = document.getElementById('consulta-form-message');
  const apiResult = document.getElementById('consulta-api-result');
  const visualResult = document.getElementById('consulta-visual-result');
  const rawJson = document.getElementById('consulta-raw-json');
  const copyButton = document.getElementById('consulta-copy');
  const downloadButton = document.getElementById('consulta-download');
  let fullResponse = '';
  const fieldLabels = {cpf:'CPF',cpf_parente:'CPF',cnpj:'CNPJ',telefone:'Telefone',telephone:'Telefone',email:'E-mail',nome:'Nome',nome_mae:'Nome',mae:'Nome',pai:'Nome',placa:'Placa',chassi:'Chassi',renavam:'Renavam',cep:'CEP',rg:'RG',id:'Identificador',doc:'Documento',identity:'Documento',valor:'Identificador'};
  const refreshField = () => {
    if (!query || !value || !valueLabel) return;
    const field = query.selectedOptions[0]?.dataset.param?.toLowerCase() || '';
    const label = fieldLabels[field] || 'Identificador';
    valueLabel.textContent = label + ' para pesquisar';
    value.placeholder = 'Digite ' + label.toLowerCase();
    value.inputMode = ['cpf','cpf_parente','cnpj','telefone','telephone','cep','renavam'].includes(field) ? 'numeric' : 'text';
  };
  query?.addEventListener('change', refreshField);
  refreshField();
  form?.addEventListener('submit', async event => {
    event.preventDefault();
    const submit = form.querySelector('[type="submit"]');
    if (submit?.disabled) return;
    if (submit) submit.disabled = true;
    if (message) { message.textContent = 'Consultando a API…'; message.dataset.state = 'loading'; }
    if (apiResult) apiResult.hidden = true;
    if (visualResult) visualResult.replaceChildren();
    if (rawJson) rawJson.textContent = '';
    fullResponse = '';
    try {
      const response = await fetch(form.action, {method:'POST',body:new FormData(form),credentials:'same-origin',headers:{'Accept':'application/json'}});
      const body = await response.text();
      if (!response.ok) {
        let error = 'Não foi possível concluir a consulta.';
        try { error = JSON.parse(body).error || error; } catch (_) {}
        throw new Error(error);
      }
      const data = JSON.parse(body);
      fullResponse = body;
      if (rawJson) rawJson.textContent = body;
      if (visualResult) {
        if (window.ConsultaResultView?.render) window.ConsultaResultView.render(visualResult, data);
        else visualResult.textContent = 'Resultado recebido. Abra a resposta técnica abaixo para visualizar todos os dados.';
      }
      if (apiResult) { apiResult.hidden = false; apiResult.scrollIntoView({behavior:'smooth',block:'nearest'}); }
      if (message) { message.textContent = 'Resultado exibido abaixo. Todos os campos continuam disponíveis na resposta técnica.'; message.dataset.state = 'success'; }
    } catch (error) {
      if (message) { message.textContent = error instanceof Error ? error.message : 'Falha na consulta.'; message.dataset.state = 'error'; }
    } finally {
      if (submit) submit.disabled = false;
    }
  });
  copyButton?.addEventListener('click', async () => {
    if (!fullResponse) return;
    try { await navigator.clipboard.writeText(fullResponse); copyButton.textContent = 'Copiado'; setTimeout(() => copyButton.textContent = 'Copiar dados', 2000); }
    catch (_) { if (message) message.textContent = 'Não foi possível copiar. Use o botão Baixar dados.'; }
  });
  downloadButton?.addEventListener('click', () => {
    if (!fullResponse) return;
    const blob = new Blob([fullResponse], {type:'application/json;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'consulta-' + (query?.value || 'resultado').replace(/[^a-z0-9_-]/gi, '-') + '.json';
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  });

  const demoButton = document.querySelector('[data-demo-module]');
  const demoResult = document.getElementById('consulta-demo-result');
  if (!demoButton || !demoResult) return;
  const pick = items => items[Math.floor(Math.random() * items.length)];
  const examples = {
    pessoas: () => [['Nome', pick(['Pessoa Exemplo A', 'Pessoa Exemplo B', 'Pessoa Exemplo C'])], ['Documento', '•••.•••.•••-••'], ['Tipo', 'Cadastro ilustrativo']],
    contatos: () => [['Telefone', '(00) 00000-0000'], ['E-mail', 'exemplo@invalid.test'], ['Tipo', 'Contato ilustrativo']],
    restricoes: () => [['Documento', '•••.•••.•••-••'], ['Situação', 'Exemplo ilustrativo'], ['Observação', 'Sem consulta a bureaus']],
    veiculos: () => [['Identificação', 'VEÍCULO-EXEMPLO'], ['Modelo', pick(['Modelo ilustrativo A', 'Modelo ilustrativo B'])], ['Situação', 'Exemplo local']],
    empresas: () => [['Razão social', 'Empresa Exemplo Ltda.'], ['Documento', '••.•••.•••/••••-••'], ['Tipo', 'Cadastro ilustrativo']],
    enderecos: () => [['CEP', '00000-000'], ['Cidade', 'Cidade Exemplo'], ['UF', '—']],
    fotos: () => [['Imagem', 'Não disponível na demonstração'], ['Identificador', 'EXEMPLO-001'], ['Tipo', 'Prévia de cadastro']],
    trabalho: () => [['Vínculo', 'Registro ilustrativo'], ['Documento', '•••.•••.•••-••'], ['Origem', 'Exemplo local']]
  };
  demoButton.addEventListener('click', () => {
    const module = demoButton.dataset.demoModule || '';
    const rows = examples[module]?.();
    if (!rows) return;
    demoResult.replaceChildren();
    const heading = document.createElement('div');
    heading.className = 'consulta-demo-heading';
    const label = document.createElement('span');
    label.textContent = 'RESULTADO DE EXEMPLO';
    const badge = document.createElement('strong');
    badge.textContent = 'DADOS ILUSTRATIVOS';
    heading.append(label, badge);
    const grid = document.createElement('div');
    grid.className = 'consulta-demo-grid';
    rows.forEach(([key, value]) => {
      const item = document.createElement('div');
      const title = document.createElement('small');
      title.textContent = key;
      const text = document.createElement('strong');
      text.textContent = value;
      item.append(title, text);
      grid.append(item);
    });
    demoResult.append(heading, grid);
    demoResult.hidden = false;
    demoResult.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'nearest'});
  });
})();
