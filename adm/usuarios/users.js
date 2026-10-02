(() => {
  const rows = Array.from(document.querySelectorAll('#userRows tr'));
  const search = document.getElementById('userSearch');
  const filters = Array.from(document.querySelectorAll('[data-filter]'));
  const empty = document.getElementById('noResults');
  const count = document.getElementById('filterCount');
  let activeFilter = 'all';

  const normalize = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('pt-BR');

  const applyFilters = () => {
    const term = normalize(search?.value.trim() || '');
    let visible = 0;

    rows.forEach((row) => {
      const typeMatches = activeFilter === 'all'
        || (activeFilter === 'blocked' && row.dataset.blocked === '1')
        || row.dataset.kind === activeFilter;
      const searchMatches = !term || normalize(row.dataset.search).includes(term);
      const show = typeMatches && searchMatches;

      row.hidden = !show;
      row.classList.toggle('is-filtered-out', !show);
      if (show) visible += 1;
    });

    if (empty) empty.hidden = visible !== 0;
    if (count) count.textContent = `${visible} ${visible === 1 ? 'conta exibida' : 'contas exibidas'}`;
  };

  filters.forEach((button) => {
    button.addEventListener('click', () => {
      activeFilter = button.dataset.filter || 'all';
      filters.forEach((item) => {
        const selected = item === button;
        item.classList.toggle('active', selected);
        item.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
      applyFilters();
    });
  });
  search?.addEventListener('input', applyFilters);
  applyFilters();

  const dialog = document.getElementById('userDialog');
  const money = (value) => new Intl.NumberFormat('pt-BR', {
    style: 'currency', currency: 'BRL'
  }).format(Number(value) || 0);

  document.querySelectorAll('.usr-edit').forEach((button) => {
    button.addEventListener('click', () => {
      let user;
      try {
        user = JSON.parse(button.dataset.user || '{}');
      } catch (error) {
        console.error('Não foi possível abrir os dados deste usuário.', error);
        return;
      }

      const name = String(user.nome || user.email?.split('@')[0] || '').trim();
      document.getElementById('editId').value = user.id || '';
      document.getElementById('editName').value = name;
      document.getElementById('editEmail').value = user.email || '';
      document.getElementById('editPhone').value = user.telefone || '';
      document.getElementById('editBalance').value = String(user.saldo ?? '0').replace('.', ',');
      document.getElementById('editCommission').value = Number(user.comissaofake) || 0;
      document.getElementById('editPlan').value = 50;
      document.getElementById('editBlocked').checked = ['1', 'true', 'on'].includes(normalize(user.bloc));
      document.getElementById('editAvatar').textContent = name.charAt(0).toUpperCase() || 'U';
      document.getElementById('editSummaryName').textContent = name || 'Usuário';
      document.getElementById('editSummaryType').textContent = String(user.demo) === '1'
        ? 'Conta demo · saldo de treino' : 'Conta de usuário';
      document.getElementById('viewDeposited').textContent = money(user.depositou);
      document.getElementById('viewWagered').textContent = money(user.total_apostado);
      document.getElementById('viewReferrals').textContent = user.indicados || '0';
      dialog?.showModal();
    });
  });

  document.querySelector('[data-close]')?.addEventListener('click', () => dialog?.close());
  dialog?.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });
})();
