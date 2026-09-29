/* Isolated Bubble demo API. Never forwards /api requests to the real site. */
(() => {
  'use strict';
  const session = {
    id: '', status: 'PLAYING', betCents: 0, targetCents: 0,
    accumulatedCents: 0, grid: [], parity: 0, current: 0, next: 1,
    shotsUntilRow: 5, pressureEvery: 9, progress: 0,
    createdAt: new Date().toISOString()
  };
  let active = false;
  const json = (body, status = 200) => new Response(JSON.stringify(body), {
    status, headers: { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' }
  });
  const error = (message) => json({ error: { message } }, 403);
  const cells = () => Array.from({ length: 10 }, () =>
    Array.from({ length: 9 }, () => Math.floor(Math.random() * 6)));
  const copySession = () => JSON.parse(JSON.stringify(session));
  const ok = () => json({ ok: true });
  const originalFetch = window.fetch.bind(window);

  window.fetch = async (input, init = {}) => {
    const rawUrl = typeof input === 'string' ? input : input?.url;
    let url;
    try { url = new URL(rawUrl, location.href); } catch { return originalFetch(input, init); }
    if (!url.pathname.startsWith('/api/')) return originalFetch(input, init);

    const method = String(init.method || input?.method || 'GET').toUpperCase();
    const path = url.pathname;
    if (path === '/api/auth/me') return json({
      user: { id: 'bubble-test', name: 'Jogador Teste', phone: '', createdAt: new Date().toISOString(), demo: true, modoDemo: true },
      balanceCents: 50000
    });
    if (path === '/api/auth/logout' || path === '/api/game/forfeit' || path.startsWith('/api/users/session/')) return ok();
    if (path === '/api/auth/login' || path === '/api/auth/register' || path === '/api/auth/refresh') return error('O acesso e o cadastro ficam desativados no modo de teste.');
    if (path === '/api/public/config') return json({
      site_nome: 'Bubble · Modo de teste', site_logo_url: `${window.BUBBLE_BASE}/images/logos/logo.webp`,
      site_favicon_url: `${window.BUBBLE_BASE}/images/icons/iconb-180.png`, suporte_links: [],
      deposito_valores_rapidos: [], deposito_botoes_labels: [], deposito_botoes_cores: [],
      entrada_valores: [5, 10, 20, 30, 50, 100], fin: false, popup: false, redeposito: false
    });
    if (path === '/api/wallet/' || path === '/api/wallet') return json({ balanceCents: 50000, transactions: [] });
    if (path === '/api/wallet/deposit-info') return json({ bonus_ativo: false, bonus_tipo: '', bonus_percentual: 0, bonus_minimo: 0, bonus_maximo: 0, elegivel: false, redeposito: false });
    if (path === '/api/wallet/withdraw-info') return json({ nivelAtivo: false, level: 1, limiteDiarioCents: 0, limiteSemanalCents: 0, bonusDepositoPercent: 0, restanteDiarioCents: 0, restanteSemanalCents: 0, nextLevel: null, nextSaqueDiarioCents: 0, nextSaqueSemanalCents: 0, nextBonusDepositoPercent: 0 });
    if (path.startsWith('/api/wallet/')) return error('Depósitos e saques não estão disponíveis no Modo Bubble.');
    if (path === '/api/game/config') return json({ minBetCents: 100, maxBetCents: 10000, ratePerLine: 0.1, targetMultiplier: 10, comboBonus: 0.1 });
    if (path === '/api/game/active') return json({ session: active ? copySession() : null });
    if (path === '/api/game/history') return json({ games: [] });
    if (path === '/api/users/stats') return json({ gamesPlayed: 0, gamesWon: 0, totalWageredCents: 0, totalWonCents: 0, biggestWinCents: 0 });
    if (path === '/api/users/level') return json({ ativo: false, level: 1, nextLevel: 2, maxLevel: 1, progress: 0, depositCount: 0, betCount: 0, betCents: 0, levels: [] });
    if (path === '/api/users/referrals') return json({ refCode: '', link: '', commissionRate: 0, showCommissionPerc: false, totalCommissionCents: 0, affiliateBalanceCents: 0, n1Count: 0, n1DepositedCents: 0, n2Count: 0, n2DepositedCents: 0, n3Count: 0, n3DepositedCents: 0, n4Count: 0, n4DepositedCents: 0, history: [] });
    if (path === '/api/indicacao/info') return json({ codigo: '', link: '', ger_influencer_link_ativo: false, total_indicados: 0, total_com_deposito: 0, comissao_nivel1_perc: 0, comissao_nivel2_perc: 0, comissao_nivel3_perc: 0, afil_exibir_perc: false, saldo_afiliado: 0, total_comissao: 0, taxa_saque_ativo: false, taxa_saque_valor: 0 });
    if (path === '/api/game/start' && method === 'POST') {
      let bet = 500;
      try { bet = Math.max(100, Math.min(10000, Number(JSON.parse(init.body || '{}').betCents) || 500)); } catch { /* keep demo default */ }
      const id = typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
      Object.assign(session, { id: `bubble-test-${id}`, status: 'PLAYING', betCents: bet, targetCents: bet * 10, accumulatedCents: 0, grid: cells(), parity: 0, current: Math.floor(Math.random() * 6), next: Math.floor(Math.random() * 6), shotsUntilRow: 5, pressureEvery: 9, progress: 0, createdAt: new Date().toISOString() });
      active = true;
      return json({ session: copySession() }, 201);
    }
    const move = path.match(/^\/api\/game\/([^/]+)\/move$/);
    if (move && method === 'POST' && active && move[1] === session.id) {
      let row = 0, col = 0;
      try { const point = JSON.parse(init.body || '{}'); row = Math.max(0, Math.min(9, Number(point.row) || 0)); col = Math.max(0, Math.min(8, Number(point.col) || 0)); } catch { /* use first cell */ }
      const color = session.grid[row][col];
      const popped = [[row, col]];
      for (const [dr, dc] of [[1,0],[-1,0],[0,1],[0,-1]]) {
        const r = row + dr, c = col + dc;
        if (r >= 0 && r < 10 && c >= 0 && c < 9 && session.grid[r][c] === color) popped.push([r,c]);
      }
      popped.forEach(([r,c]) => { session.grid[r][c] = -1; });
      const gained = Math.max(1, popped.length) * 10;
      session.accumulatedCents += gained;
      session.progress = Math.min(1, session.accumulatedCents / session.targetCents);
      session.current = session.next;
      session.next = Math.floor(Math.random() * 6);
      session.shotsUntilRow--;
      let rowAdded = false;
      if (session.shotsUntilRow <= 0) { session.grid.push(Array.from({length:9},() => Math.floor(Math.random()*6))); session.grid.shift(); session.shotsUntilRow=5; rowAdded=true; }
      return json({ session: copySession(), landed: [row,col], popped, dropped: [], gainedCents: gained, rowAdded, gameOver: false, bombHit: false, exploded: [] });
    }
    const cashout = path.match(/^\/api\/game\/([^/]+)\/cashout$/);
    if (cashout && method === 'POST' && active && cashout[1] === session.id) {
      session.status = 'CASHED_OUT'; active = false;
      return json({ session: copySession() });
    }
    if (path.startsWith('/api/game/')) return error('Ação indisponível nesta sessão de teste.');
    if (path === '/api/analytics/event') return ok();
    return error('Este recurso não existe no Modo Bubble.');
  };
})();
