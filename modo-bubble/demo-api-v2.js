/* Practice API: /api calls stay local and never contact the real wallet. */
(() => {
  'use strict';
  const engine = window.BubbleDemoEngine;
  if (!engine) throw new Error('O mecanismo de treino Bubble não foi carregado.');
  let session = null, balanceCents = 50000;
  const history = [];
  const json = (body, status = 200) => new Response(JSON.stringify(body), {
    status, headers: { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' }
  });
  const error = (message, status = 400, code = 'DEMO_ERROR') => json({ error: { message, code } }, status);
  const copy = value => JSON.parse(JSON.stringify(value));
  const originalFetch = window.fetch.bind(window);
  const readBody = async (input, init) => {
    try { return JSON.parse(init.body ?? (typeof input?.clone === 'function' ? await input.clone().text() : '{}')); }
    catch { return null; }
  };
  const archive = () => { if (session) history.push(copy(session)); };
  window.fetch = async (input, init = {}) => {
    let url;
    try { url = new URL(typeof input === 'string' || input instanceof URL ? input : input?.url, location.href); }
    catch { return originalFetch(input, init); }
    if (!url.pathname.startsWith('/api/')) return originalFetch(input, init);
    const method = String(init.method || input?.method || 'GET').toUpperCase();
    const path = url.pathname;
    if (path === '/api/auth/me') return json({
      user: { id: 'bubble-test', name: 'Jogador Teste', phone: '', createdAt: new Date().toISOString(), demo: true, modoDemo: true }, balanceCents
    });
    if (path === '/api/auth/logout' || path.startsWith('/api/users/session/') || path === '/api/analytics/event') return json({ ok: true });
    if (['/api/auth/login', '/api/auth/register', '/api/auth/refresh'].includes(path)) return error('Use seu login do Subway Run.', 403);
    if (path === '/api/public/config') return json({
      site_nome: 'Bubble · Modo de teste', site_logo_url: `${window.BUBBLE_BASE}/images/logos/logo.webp`,
      site_favicon_url: `${window.BUBBLE_BASE}/images/icons/iconb-180.png`, suporte_links: [],
      deposito_valores_rapidos: [], deposito_botoes_labels: {}, deposito_botoes_cores: {},
      entrada_valores: [5, 10, 20, 30, 50, 100], fin: {}, popup: null, redeposito: null
    });
    if (path === '/api/wallet/' || path === '/api/wallet') return json({ balanceCents, transactions: [] });
    if (path === '/api/wallet/deposit-info') return json({ bonus_ativo: false, bonus_tipo: '', bonus_percentual: 0, bonus_minimo: 0, bonus_maximo: 0, elegivel: false, redeposito: null });
    if (path === '/api/wallet/withdraw-info') return json({ nivelAtivo: false, level: 1, limiteDiarioCents: 0, limiteSemanalCents: 0, bonusDepositoPercent: 0, restanteDiarioCents: 0, restanteSemanalCents: 0, nextLevel: null });
    if (path.startsWith('/api/wallet/')) return error('PIX e saques estão indisponíveis no treino Bubble.', 403);
    if (path === '/api/game/config') return json({ minBetCents: 500, maxBetCents: 10000, ratePerLine: 0.1, targetMultiplier: 10, comboBonus: 0.1 });
    if (path === '/api/game/active') return json({ session: session?.status === 'ACTIVE' ? copy(session) : null });
    if (path === '/api/game/history') return json({ games: copy(history) });
    if (path === '/api/game/forfeit' && method === 'POST') {
      if (session?.status === 'ACTIVE') { session.status = 'FORFEITED'; archive(); }
      return json({ ok: true });
    }
    if (path === '/api/game/start' && method === 'POST') {
      const body = await readBody(input, init), betCents = body?.betCents;
      if (!Number.isInteger(betCents) || betCents < 500 || betCents > 10000) return error('Escolha um valor de entrada entre R$ 5 e R$ 100.');
      if (betCents > balanceCents) return error('Saldo de treino insuficiente.', 409, 'INSUFFICIENT_BALANCE');
      if (session?.status === 'ACTIVE') return error('Já existe uma partida em andamento.', 409, 'CONFLICT');
      const id = typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
      const grid = engine.newGrid();
      session = {
        id: `bubble-test-${id}`, status: 'ACTIVE', betCents, targetCents: betCents * 10,
        accumulatedCents: 0, grid, parity: 0,
        current: engine.pickColor(grid), next: engine.pickColor(grid),
        shotsUntilRow: 6, pressureEvery: 6, progress: 0, createdAt: new Date().toISOString()
      };
      balanceCents -= betCents;
      return json({ session: copy(session) }, 201);
    }
    const move = path.match(/^\/api\/game\/([^/]+)\/move$/);
    if (move && method === 'POST') {
      if (!session || move[1] !== session.id || session.status !== 'ACTIVE') return error('Esta partida já terminou.', 409);
      const body = await readBody(input, init);
      if (!body || !engine.canLand(session.grid, session.parity, body.row, body.col)) return error('Mire em um espaço livre junto às bolhas.');
      const result = engine.move(session, body.row, body.col);
      session = result.session;
      if (result.gameOver) archive();
      return json(copy(result));
    }
    const cashout = path.match(/^\/api\/game\/([^/]+)\/cashout$/);
    if (cashout && method === 'POST') {
      if (!session || cashout[1] !== session.id || session.status !== 'ACTIVE') return error('Esta partida já terminou.', 409);
      if (session.accumulatedCents < session.targetCents) return error('Alcance a meta para resgatar o saldo de treino.', 409);
      session.status = 'CASHED_OUT'; balanceCents += session.accumulatedCents; archive();
      return json({ session: copy(session) });
    }
    if (path.startsWith('/api/game/')) return error('Ação indisponível nesta partida de treino.', 404);
    if (path === '/api/users/stats') return json({ gamesPlayed: history.length, gamesWon: history.filter(game => game.status === 'CASHED_OUT').length, totalWageredCents: history.reduce((total, game) => total + game.betCents, 0), totalWonCents: history.filter(game => game.status === 'CASHED_OUT').reduce((total, game) => total + game.accumulatedCents, 0), biggestWinCents: Math.max(0, ...history.filter(game => game.status === 'CASHED_OUT').map(game => game.accumulatedCents)) });
    if (path === '/api/users/level') return json({ ativo: false, level: 1, nextLevel: 2, maxLevel: 1, progress: 0, depositCount: 0, betCount: history.length, betCents: 0, levels: [] });
    if (path === '/api/users/referrals') return json({ refCode: '', link: '', commissionRate: 0, showCommissionPerc: false, totalCommissionCents: 0, affiliateBalanceCents: 0, n1Count: 0, n1DepositedCents: 0, n2Count: 0, n2DepositedCents: 0, n3Count: 0, n3DepositedCents: 0, n4Count: 0, n4DepositedCents: 0, history: [] });
    if (path === '/api/indicacao/info') return json({ codigo: '', link: '', ger_influencer_link_ativo: false, total_indicados: 0, total_com_deposito: 0, comissao_nivel1_perc: 0, comissao_nivel2_perc: 0, comissao_nivel3_perc: 0, afil_exibir_perc: false, saldo_afiliado: 0, total_comissao: 0, taxa_saque_ativo: false, taxa_saque_valor: 0 });
    return error('Este recurso não existe no treino Bubble.', 404);
  };
})();
