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
    const balanceUrl = header.dataset.balanceUrl;
    const balanceTargets = document.querySelectorAll('[data-live-balance]');
    let balanceTimer = 0;
    async function refreshBalance() {
        if (!balanceUrl || document.hidden) return;
        try {
            const response = await fetch(balanceUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            if (!data.ok || typeof data.formatted !== 'string') return;
            balanceTargets.forEach(target => { target.textContent = data.formatted; });
        } catch (ignored) {}
    }
    if (balanceUrl && balanceTargets.length) {
        refreshBalance();
        balanceTimer = window.setInterval(refreshBalance, 4000);
        window.addEventListener('pageshow', refreshBalance);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshBalance(); });
        window.addEventListener('pagehide', () => window.clearInterval(balanceTimer), { once: true });
    }
})();
