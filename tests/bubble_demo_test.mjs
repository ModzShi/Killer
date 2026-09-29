import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
import { webcrypto } from 'node:crypto';

function setup(random = Math.random) {
  const forwarded = [], math = Object.create(Math);
  math.random = random;
  const window = { BUBBLE_BASE: '/modo-bubble', fetch: async input => {
    forwarded.push(String(input)); return new Response('asset');
  } };
  const context = vm.createContext({ window, location: { href: 'https://example.test/modo-bubble/jogo' }, URL, Response, Request, crypto: webcrypto, Math: math });
  for (const file of ['demo-engine.js', 'demo-api-v2.js']) {
    vm.runInContext(readFileSync(new URL(`../modo-bubble/${file}`, import.meta.url), 'utf8'), context, { filename: file });
  }
  const call = async (path, body) => {
    const response = await window.fetch(path, body === undefined ? {} : { method: 'POST', body: JSON.stringify(body) });
    return { status: response.status, data: await response.json() };
  };
  return { engine: window.BubbleDemoEngine, call, window, forwarded };
}

test('partida inicia ativa com cores válidas e linhas hexagonais', async () => {
  const { call } = setup();
  const { status, data: { session } } = await call('/api/game/start', { betCents: 1000 });
  assert.equal(status, 201);
  assert.equal(session.status, 'ACTIVE');
  assert.equal(session.targetCents, 10000);
  assert.equal(session.current >= 1 && session.current <= 6, true);
  session.grid.forEach((row, r) => {
    assert.equal(row.length, r % 2 === 0 ? 9 : 8);
    assert.equal(row.every(color => color >= 1 && color <= 6), true);
  });
  assert.equal((await call('/api/game/active')).data.session.id, session.id);
  assert.equal((await call('/api/wallet')).data.balanceCents, 49000);
});

test('disparo encaixa, remove combinação e derruba bolhas sem ligação com o teto', () => {
  const { engine } = setup();
  const source = { grid: [[1, 1, 0, 2, 0, 0, 0, 0, 0], [0, 3, 0, 0, 0, 0, 0, 0]], parity: 0 };
  const result = engine.shoot(source, 0, 2, 1);
  assert.equal(result.popped.length, 3);
  assert.deepEqual(JSON.parse(JSON.stringify(result.dropped)), [[1, 1]]);
  assert.equal(result.grid[0][3], 2);
  assert.equal(source.grid[0][2], 0);
  const miss = engine.shoot(source, 0, 2, 6);
  assert.equal(miss.popped.length, 0);
  assert.equal(miss.grid[0][2], 6);
  assert.throws(() => engine.shoot(source, 0, 0, 1));
});

test('pressão adiciona linha no teto e conserva a largura alternada', () => {
  const { engine } = setup();
  const source = { id: 'test', status: 'ACTIVE', grid: [[1, 1, 0, 2, 0, 0, 0, 0, 0]], parity: 0, betCents: 1000, accumulatedCents: 0, targetCents: 10000, current: 6, next: 3, shotsUntilRow: 1, pressureEvery: 6 };
  const result = engine.move(source, 0, 2, () => 0);
  assert.equal(result.rowAdded, true);
  assert.equal(result.session.parity, 1);
  assert.equal(result.session.grid[0].length, 8);
  assert.equal(result.session.grid[1].length, 9);
  assert.equal(result.session.grid[1][2], 6);
  assert.equal(result.session.current, 3);
  assert.equal(result.session.shotsUntilRow, 6);
});

test('linha limite encerra a partida como LOST', () => {
  const { engine } = setup();
  const source = { id: 'test', status: 'ACTIVE', grid: engine.newGrid(11, 0, () => 0), parity: 0, betCents: 1000, accumulatedCents: 0, targetCents: 10000, current: 6, next: 3, shotsUntilRow: 6, pressureEvery: 6 };
  const result = engine.move(source, 11, 0);
  assert.equal(result.gameOver, true);
  assert.equal(result.session.status, 'LOST');
});

test('movimento inválido não muda a sessão e desistência permite nova partida', async () => {
  const { call } = setup();
  const { data: { session } } = await call('/api/game/start', { betCents: 1000 });
  const invalid = await call(`/api/game/${session.id}/move`, { row: 0, col: 0 });
  assert.equal(invalid.status, 400);
  assert.deepEqual((await call('/api/game/active')).data.session, session);
  assert.equal((await call(`/api/game/${session.id}/cashout`, {})).status, 409);
  await call('/api/game/forfeit', {});
  assert.equal((await call('/api/game/active')).data.session, null);
  assert.equal((await call(`/api/game/${session.id}/move`, { row: 5, col: 0 })).status, 409);
  assert.equal((await call('/api/game/start', { betCents: 2000 })).status, 201);
});

test('partida completa acumula por combinações e resgata uma única vez', async () => {
  const { call } = setup(() => 0);
  let { data: { session } } = await call('/api/game/start', { betCents: 1000 });
  for (let shot = 0; session.accumulatedCents < session.targetCents && shot < 10; shot++) {
    const result = await call(`/api/game/${session.id}/move`, { row: session.grid.length, col: 0 });
    assert.equal(result.status, 200);
    assert.ok(result.data.popped.length >= 3);
    assert.ok(result.data.gainedCents > 0);
    session = result.data.session;
    assert.equal(session.status, 'ACTIVE');
  }
  assert.ok(session.accumulatedCents >= session.targetCents);
  const result = await call(`/api/game/${session.id}/cashout`, {});
  assert.equal(result.data.session.status, 'CASHED_OUT');
  const balance = 49000 + session.accumulatedCents;
  assert.equal((await call('/api/wallet')).data.balanceCents, balance);
  assert.equal((await call(`/api/game/${session.id}/cashout`, {})).status, 409);
  assert.equal((await call('/api/wallet')).data.balanceCents, balance);
  assert.equal((await call('/api/game/history')).data.games.length, 1);
});

test('nenhuma chamada de carteira ou pagamento é enviada ao servidor real', async () => {
  const { call, window, forwarded } = setup();
  await call('/api/wallet/deposit', { amount: 500 });
  await call('/api/wallet/withdraw', { amount: 500 });
  await call('https://another.example/api/game/start', { betCents: 1000 });
  await call('/api/not-supported', {});
  assert.deepEqual(forwarded, []);
  await window.fetch('/modo-bubble/images/logos/logo.webp');
  assert.equal(forwarded.length, 1);
});
