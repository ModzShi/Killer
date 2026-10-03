(() => {
    const side = document.getElementById('admin-sidebar');
    const shade = document.querySelector('.sidebar-shade');
    const close = () => { side?.classList.remove('open'); shade?.classList.remove('show'); };
    document.querySelector('[data-admin-menu-open]')?.addEventListener('click', () => { side?.classList.add('open'); shade?.classList.add('show'); });
    document.querySelectorAll('[data-admin-menu-close]').forEach(button => button.addEventListener('click', close));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    document.querySelector('[data-admin-refresh]')?.addEventListener('click', () => location.reload());
    const filter = document.querySelector('[data-admin-filter]');
    const status = document.querySelector('[data-admin-status]');
    function applyFilters() {
        const query = filter?.value.trim().toLocaleLowerCase('pt-BR') || '';
        const selected = status?.value || '';
        document.querySelectorAll('[data-admin-row]').forEach(row => {
            row.hidden = (Boolean(query) && !row.textContent.toLocaleLowerCase('pt-BR').includes(query)) || (Boolean(selected) && row.dataset.status !== selected);
        });
    }
    filter?.addEventListener('input', applyFilters);
    status?.addEventListener('change', applyFilters);
})();
