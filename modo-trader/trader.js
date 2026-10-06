(() => {
  const amount = document.querySelector('#trade-amount');
  document.querySelectorAll('[data-amount]').forEach(button => {
    button.addEventListener('click', () => {
      if (amount) amount.value = button.dataset.amount || '';
    });
  });

  const countdown = document.querySelector('[data-remaining]');
  if (countdown) {
    let remaining = Math.max(0, Number(countdown.dataset.remaining) || 0);
    countdown.textContent = String(remaining);
    const interval = setInterval(() => {
      remaining = Math.max(0, remaining - 1);
      countdown.textContent = String(remaining);
      if (remaining === 0) {
        clearInterval(interval);
        window.setTimeout(() => location.reload(), 1200);
      }
    }, 1000);
  }

  const chart = document.querySelector('#trader-chart');
  if (!chart) return;
  const width = 800, height = 300;
  let value = 145;
  const points = Array.from({length: 48}, (_, index) => {
    value = Math.max(28, Math.min(265, value + (Math.random() - .5) * 48));
    return [index * width / 47, value];
  });
  const line = points.map(([x, y], index) => `${index ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`).join(' ');
  const area = `${line} L${width} ${height} L0 ${height}Z`;
  chart.innerHTML = `<svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="trader-fill" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#25e0ab" stop-opacity=".29"/><stop offset="1" stop-color="#25e0ab" stop-opacity="0"/></linearGradient></defs><path d="${area}" fill="url(#trader-fill)"/><path d="${line}" fill="none" stroke="#26e3ac" stroke-width="3" vector-effect="non-scaling-stroke" stroke-linejoin="round"/><circle cx="${points[47][0]}" cy="${points[47][1]}" r="6" fill="#28e3b0" stroke="#d5fff0" stroke-width="2"/></svg>`;
})();
