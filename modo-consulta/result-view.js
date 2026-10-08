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
    score: 'Pontuação', dados: 'Dados', estatisticas: 'Resumo da consulta',
    parametros: 'Parâmetros', paginacao: 'Paginação', enderecos: 'Endereços',
    parentes: 'Parentes', poder_aquisitivo: 'Poder aquisitivo',
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

  function appendEntries(container, value) {
    const entries = Object.entries(value);
    const fields = node('div', 'consulta-data-grid');
    const groups = node('div', 'consulta-data-groups');
    const images = node('div', 'consulta-photo-grid');
    for (const [key, item] of entries) {
      if (photoKeys.has(key.toLowerCase()) && typeof item === 'string') {
        const source = photoSource(item);
        if (source) {
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
            if (record !== null && typeof record === 'object') appendEntries(card, record);
            else card.append(node('p', 'consulta-record-value', display(record)));
            group.append(card);
          });
        } else if (!Object.keys(item).length) {
          group.append(node('p', 'consulta-no-data', 'Nenhuma informação.'));
        } else appendEntries(group, item);
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
    const header = node('div', 'consulta-result-hero');
    const titleBox = node('div');
    titleBox.append(node('span', 'consulta-result-eyebrow', 'RESULTADO DA CONSULTA'));
    const title = recordName(data) || recordName(data.resultado) || (Array.isArray(data.dados) ? recordName(data.dados[0]) : '') || 'Informações encontradas';
    titleBox.append(node('h3', '', title));
    const total = data.estatisticas?.total_registros;
    if (total !== undefined && total !== null) titleBox.append(node('p', '', String(total) + (Number(total) === 1 ? ' registro encontrado' : ' registros encontrados')));
    const success = data.success ?? data.sucesso;
    if (success !== undefined) header.append(titleBox, node('span', success ? 'consulta-result-badge' : 'consulta-result-badge is-error', success ? 'CONSULTA CONCLUÍDA' : 'CONSULTA SEM RESULTADO'));
    else header.append(titleBox);
    container.append(header);
    if (Array.isArray(data)) {
      const wrapper = node('div', 'consulta-data-record');
      appendEntries(wrapper, {dados: data});
      container.append(wrapper);
    } else appendEntries(container, data);
  }

  window.ConsultaResultView = {render, photoSource, display};
})();
