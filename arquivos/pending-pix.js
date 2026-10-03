(() => {
    const pendingNodes = [...document.querySelectorAll('[data-pix-pending]')];
    const pending = pendingNodes[0];
    if (!pending) return;
    const windowSeconds = 600;
    const age = Number(pending.dataset.age);
    if (!Number.isFinite(age) || age < 0) return;
    const openedAt = performance.now();

    function remaining() { return Math.max(0, windowSeconds - age - Math.floor((performance.now() - openedAt) / 1000)); }
    function paint() {
        const left = remaining();
        for (const node of pendingNodes) {
            const progress = node.querySelector('[data-pix-progress]');
            const time = node.querySelector('[data-pix-time]');
            const label = node.querySelector('[data-pix-label]');
            if (progress) progress.style.width = `${Math.min(100, (1 - left / windowSeconds) * 100)}%`;
            if (time) time.textContent = left ? `${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}` : 'Concluída';
            if (label && !left) label.textContent = node.classList.contains('sk-pix-pending')
                ? 'Pendente · toque para conferir'
                : 'A cobrança continua pendente. Abra o PIX e use “Já paguei” para conferir novamente.';
        }
    }
    paint();
    const visualTimer = window.setInterval(paint, 1000);
    let checking = false;
    let lastCheck = 0;
    async function check() {
        if (checking || document.hidden || remaining() === 0 || Date.now() - lastCheck < 25000) return;
        checking = true;
        lastCheck = Date.now();
        try {
            const body = new URLSearchParams({ csrf: pending.dataset.csrf || '', action: 'auto_check' });
            const response = await fetch(pending.dataset.url, { method: 'POST', body, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const result = await response.json();
            if (result.paid) {
                window.clearInterval(visualTimer);
                window.clearInterval(checkTimer);
                for (const node of pendingNodes) {
                    const label = node.querySelector('[data-pix-label]');
                    const time = node.querySelector('[data-pix-time]');
                    const progress = node.querySelector('[data-pix-progress]');
                    if (label) label.textContent = 'Pagamento confirmado! Atualizando saldo…';
                    if (time) time.textContent = 'Pago';
                    if (progress) progress.style.width = '100%';
                }
                window.setTimeout(() => location.reload(), 700);
            } else if (result.status === 'CANCELED') {
                location.reload();
            }
        } catch (_) { /* A próxima consulta tenta novamente. */ }
        finally { checking = false; }
    }
    const checkTimer = window.setInterval(check, 25000);
    window.setTimeout(check, 3000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { paint(); check(); } });
    window.addEventListener('pagehide', () => { window.clearInterval(visualTimer); window.clearInterval(checkTimer); }, { once: true });
})();
