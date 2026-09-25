(() => {
    const header = document.querySelector('.sk-header');
    if (!header) return;
    const toggle = header.querySelector('.sk-toggle');
    const nav = header.querySelector('.sk-navigation');
    toggle.hidden = false;
    header.classList.add('sk-enhanced');
    function setOpen(open, restoreFocus = false) {
        header.classList.toggle('sk-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
        if (restoreFocus) toggle.focus();
    }
    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && header.classList.contains('sk-open')) setOpen(false, true);
    });
    document.addEventListener('click', event => {
        if (!header.contains(event.target)) setOpen(false);
    });
    nav.addEventListener('click', event => { if (event.target.closest('a')) setOpen(false); });
    window.matchMedia('(min-width: 1101px)').addEventListener('change', () => setOpen(false));
})();
