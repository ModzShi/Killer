(() => {
  'use strict';

  const names = {
    cpf: 'CPF', cnpj: 'CNPJ', rg: 'RG', cep: 'CEP', uf: 'UF', id: 'ID',
    nome: 'Nome', nome_mae: 'Nome da mãe', nome_pai: 'Nome do pai',
    nasc: 'Nascimento', sexo: 'Sexo', email: 'E-mail', telefone: 'Telefone',
    logradouro: 'Logradouro', logr_tipo: 'Tipo de logradouro', logr_nome: 'Logradouro',
    logr_numero: 'Número', logr_complemento: 'Complemento', bairro: 'Bairro', cidade: 'Cidade',
    dt_atualizacao: 'Atualizado em', dt_inclusao: 'Incluído em',
    total_registros: 'Total de registros', registros_retornados: 'Registros retornados',
    registros_com_foto: 'Com foto', registros_sem_foto: 'Sem foto',
    total_paginas: 'Total de páginas', tipo_busca: 'Tipo de busca',
    tempo_segundos: 'Tempo de resposta (s)', tem_foto: 'Foto disponível',
    score: 'Pontuação', dados: 'Dados principais', estatisticas: 'Resumo da consulta',
    parametros: 'Parâmetros', paginacao: 'Paginação', enderecos: 'Endereços',
    parentes: 'Familiares', poder_aquisitivo: 'Poder aquisitivo',
    resultado: 'Resultado', consulta: 'Consulta', mensagem: 'Mensagem',
    success: 'Status', sucesso: 'Status'
  };
  const photoKeys = new Set(['foto', 'foto_base64', 'imagem', 'imagem_base64']);
  const label = key => {
    const normalized = String(key).toLowerCase();
    return Object.prototype.hasOwnProperty.call(names, normalized) ? names[normalized] : String(key).replace(/_/g, ' ').toLocaleLowerCase('pt-BR').replace(/^./u, c => c.toLocaleUpperCase('pt-BR'));
  };
  const display = value => value === null || value === '' || value === 'NULL' ? 'Não informado' : typeof value === 'boolean' ? (value ? 'Sim' : 'Não') : String(value);
  const node = (tag, className, text) => {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
  };
  const photoSource = value => {
    if (typeof value !== 'string') return null;
    const compact = value.replace(/\s+/g, '');
    const supplied = compact.match(/^data:image\/(jpeg|png|webp|gif);base64,([A-Za-z0-9+/]+={0,2})$/i);
    if (supplied) return 'data:image/' + supplied[1].toLowerCase() + ';base64,' + supplied[2];
    if (!/^[A-Za-z0-9+/]+={0,2}$/.test(compact)) return null;
    const mime = compact.startsWith('/9j/') ? 'jpeg' : compact.startsWith('iVBORw0KGgo') ? 'png' : compact.startsWith('UklGR') ? 'webp' : compact.startsWith('R0lGOD') ? 'gif' : null;
    return mime ? 'data:image/' + mime + ';base64,' + compact : null;
  };
  const recordName = value => value && typeof value === 'object'
    ? (value.NOME || value.nome || value.RAZAO_SOCIAL || value.razao_social || value.dados?.NOME || value.dados?.nome || '')
    : '';

  const present = value => value !== null && value !== '' && value !== 'NULL'
    && (typeof value !== 'object' || Object.values(value).some(present));
  let viewSequence = 0;
  function scanIcon() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 32 32');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M4 11V6a2 2 0 0 1 2-2h5m10 0h5a2 2 0 0 1 2 2v5M4 21v5a2 2 0 0 0 2 2h5m10 0h5a2 2 0 0 0 2-2v-5M10 17v-3a6 6 0 0 1 12 0v3m-9 7c2-3 3-6 3-10m3 0c0 5-1 8-3 12');
    svg.append(path);
    return svg;
  }

  function appendEntries(container, value, featuredRecord = null) {
    const entries = Object.entries(value);
    const fields = node('div', 'consulta-data-grid');
    const groups = node('div', 'consulta-data-groups');
    const images = node('div', 'consulta-photo-grid');
    for (const [key, item] of entries) {
      if (!present(item)) continue;
      if (photoKeys.has(key.toLowerCase()) && typeof item === 'string') {
        const source = photoSource(item);
        if (source) {
          if (value === featuredRecord) continue;
          const frame = node('figure', 'consulta-photo');
          const img = node('img');
          img.src = source;
          img.alt = 'Foto do registro consultado';
          img.loading = 'lazy';
          img.decoding = 'async';
          frame.append(img, node('figcaption', '', label(key)));
          images.append(frame);
          continue;
        }
        const unavailable = node('div', 'consulta-data-field');
        unavailable.append(node('small', '', label(key)), node('strong', '', 'Imagem não exibida. O dado original está na resposta técnica abaixo.'));
        fields.append(unavailable);
        continue;
      }
      if (item !== null && typeof item === 'object') {
        const group = node('section', 'consulta-data-section');
        const count = Array.isArray(item) ? ' · ' + item.length + (item.length === 1 ? ' item' : ' itens') : '';
        group.append(node('h4', '', label(key) + count));
        if (Array.isArray(item)) {
          if (!item.length) group.append(node('p', 'consulta-no-data', 'Nenhum registro.'));
          item.forEach((record, index) => {
            const card = node('article', 'consulta-data-record');
            const title = recordName(record);
            card.append(node('h5', '', title ? title : 'Registro ' + (index + 1)));
            if (record !== null && typeof record === 'object') appendEntries(card, record, featuredRecord);
            else card.append(node('p', 'consulta-record-value', display(record)));
            group.append(card);
          });
        } else if (!Object.keys(item).length) {
          group.append(node('p', 'consulta-no-data', 'Nenhuma informação.'));
        } else appendEntries(group, item, featuredRecord);
        groups.append(group);
        continue;
      }
      const field = node('div', 'consulta-data-field');
      const valueText = (key === 'success' || key === 'sucesso') && typeof item === 'boolean'
        ? (item ? 'Concluída' : 'Não concluída') : display(item);
      field.append(node('small', '', label(key)), node('strong', '', valueText));
      fields.append(field);
    }
    if (images.childElementCount) container.append(images);
    if (fields.childElementCount) container.append(fields);
    if (groups.childElementCount) container.append(groups);
  }

  function render(container, data) {
    container.replaceChildren();
    if (data === null || typeof data !== 'object') {
      container.append(node('p', 'consulta-no-data', display(data)));
      return;
    }
    const payload = data.resultado && typeof data.resultado === 'object' ? data.resultado : data;
    const identity = Array.isArray(payload.dados) ? payload.dados[0] : (payload.dados || payload);
    const header = node('div', 'consulta-result-hero');
    const portrait = node('div', 'consulta-identity-photo');
    const photo = identity && typeof identity === 'object'
      ? Object.entries(identity).find(([key, value]) => photoKeys.has(key.toLowerCase()) && photoSource(value)) : null;
    if (photo) {
      const image = node('img');
      image.src = photoSource(photo[1]);
      image.alt = 'Foto do registro em destaque';
      image.decoding = 'async';
      portrait.append(image);
    } else portrait.append(scanIcon());
    header.append(portrait);
    const titleBox = node('div');
    titleBox.className = 'consulta-identity-title';
    titleBox.append(node('span', 'consulta-result-eyebrow', 'IDENTIFICAÇÃO · RESULTADO'));
    const title = recordName(data) || recordName(data.resultado) || (Array.isArray(data.dados) ? recordName(data.dados[0]) : '') || 'Informações encontradas';
    titleBox.append(node('h3', '', title));
    const total = data.estatisticas?.total_registros;
    if (total !== undefined && total !== null) titleBox.append(node('p', '', String(total) + (Number(total) === 1 ? ' registro encontrado' : ' registros encontrados')));
    const success = data.success ?? data.sucesso;
    if (success !== undefined) header.append(titleBox, node('span', success ? 'consulta-result-badge' : 'consulta-result-badge is-error', success ? 'CONSULTA CONCLUÍDA' : 'CONSULTA SEM RESULTADO'));
    else header.append(titleBox);
    container.append(header);
    const sections = [];
    const scalar = {};
    const metadataKeys = new Set(['success','sucesso','mensagem','message','consulta','estatisticas','tempo_segundos','tipo_busca']);
    const metadata = {};
    if (Array.isArray(payload)) sections.push(['Dados principais', payload]);
    else Object.entries(payload).forEach(([key, value]) => {
      if (!present(value)) return;
      if (metadataKeys.has(key)) { Object.defineProperty(metadata, key, {value, enumerable:true}); return; }
      if (typeof value === 'object') sections.push([label(key), value]);
      else Object.defineProperty(scalar, key, {value, enumerable:true});
    });
    if (Object.keys(scalar).length) sections.push([sections.length ? 'Outras informações' : 'Dados principais', scalar]);
    if (!sections.length) container.append(node('p', 'consulta-no-data', 'Nenhuma informação disponível nesta resposta.'));
    const tabs = node('div', 'consulta-result-tabs');
    tabs.setAttribute('role', 'tablist');
    tabs.setAttribute('aria-label', 'Seções do resultado');
    const panels = node('div', 'consulta-result-panels');
    const prefix = 'consulta-view-' + (++viewSequence);
    const activate = index => {
      Array.from(tabs.children).forEach((button, i) => {
        button.setAttribute('aria-selected', String(i === index));
        button.tabIndex = i === index ? 0 : -1;
        panels.children[i].hidden = i !== index;
      });
    };
    sections.forEach(([title, value], index) => {
      const button = node('button', '', title);
      button.type = 'button';
      button.id = prefix + '-tab-' + index;
      button.setAttribute('role', 'tab');
      button.setAttribute('aria-controls', prefix + '-panel-' + index);
      const panel = node('section', 'consulta-result-panel');
      panel.id = prefix + '-panel-' + index;
      panel.setAttribute('role', 'tabpanel');
      panel.setAttribute('aria-labelledby', button.id);
      panel.tabIndex = 0;
      panel.append(node('h4', 'consulta-panel-title', title));
      if (Array.isArray(value)) {
        value.forEach((record, i) => {
          const card = node('article', 'consulta-data-record');
          card.append(node('h5', '', recordName(record) || 'Registro ' + (i + 1)));
          if (record !== null && typeof record === 'object') appendEntries(card, record, photo ? identity : null);
          else card.append(node('p', 'consulta-record-value', display(record)));
          panel.append(card);
        });
      } else appendEntries(panel, value, photo ? identity : null);
      button.addEventListener('click', () => activate(index));
      button.addEventListener('keydown', event => {
        let next = index;
        if (event.key === 'ArrowRight') next = (index + 1) % sections.length;
        else if (event.key === 'ArrowLeft') next = (index - 1 + sections.length) % sections.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = sections.length - 1;
        else return;
        event.preventDefault(); activate(next); tabs.children[next].focus();
      });
      tabs.append(button); panels.append(panel);
    });
    if (sections.length) { container.append(tabs, panels); activate(0); }
    if (Object.keys(metadata).length) {
      const details = node('details', 'consulta-result-metadata');
      details.append(node('summary', '', 'Detalhes da consulta'));
      appendEntries(details, metadata);
      container.append(details);
    }
  }

  window.ConsultaResultView = {render, photoSource, display};
})();
