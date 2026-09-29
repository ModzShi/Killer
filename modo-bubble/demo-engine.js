/* Local Bubble practice rules. Values never reach the payment system. */
(() => {
  'use strict';
  const columns = (row, parity) => (row + parity) % 2 === 0 ? 9 : 8;
  const at = (grid, row, col) => grid[row]?.[col] ?? 0;
  const neighbors = (row, col, parity, rows) => {
    const diagonal = (row + parity) % 2 === 0 ? -1 : 1;
    return [[row, col - 1], [row, col + 1],
      [row - 1, col], [row - 1, col + diagonal],
      [row + 1, col], [row + 1, col + diagonal]]
      .filter(([r, c]) => r >= 0 && r < rows && c >= 0 && c < columns(r, parity));
  };
  const colors = grid => [...new Set(grid.flat().filter(color => color >= 1 && color <= 6))];
  const pickColor = (grid, random = Math.random) => {
    const palette = colors(grid);
    return palette.length ? palette[Math.floor(random() * palette.length)] : 1 + Math.floor(random() * 6);
  };
  const newRow = (row, parity, random = Math.random) => {
    const result = [];
    for (let col = 0; col < columns(row, parity); col++) {
      result.push(col % 2 === 1 ? result[col - 1] : 1 + Math.floor(random() * 6));
    }
    return result;
  };
  const newGrid = (rows = 5, parity = 0, random = Math.random) =>
    Array.from({ length: rows }, (_, row) => newRow(row, parity, random));
  const canLand = (grid, parity, row, col) => Number.isInteger(row) && Number.isInteger(col) &&
    row >= 0 && row <= 11 && col >= 0 && col < columns(row, parity) && row <= grid.length &&
    at(grid, row, col) === 0 && (row === 0 ||
      neighbors(row, col, parity, Math.max(grid.length, row + 1)).some(([r, c]) => at(grid, r, c) > 0));
  const connected = (grid, parity, origin, predicate) => {
    const pending = [origin], visited = new Set(), found = [];
    while (pending.length) {
      const point = pending.pop(), [row, col] = point, key = `${row}:${col}`;
      if (visited.has(key) || !predicate(at(grid, row, col))) continue;
      visited.add(key); found.push(point);
      pending.push(...neighbors(row, col, parity, grid.length));
    }
    return found;
  };
  function shoot(source, row, col, color) {
    const parity = source.parity, grid = source.grid.map(line => [...line]);
    if (!canLand(grid, parity, row, col)) throw new Error('Mire em um espaço livre junto às bolhas.');
    while (grid.length <= row) grid.push(Array(columns(grid.length, parity)).fill(0));
    grid[row][col] = color;
    const group = connected(grid, parity, [row, col], value => value === color);
    const popped = group.length >= 3 ? group : [];
    for (const [r, c] of popped) grid[r][c] = 0;
    const attached = new Set();
    for (let c = 0; c < (grid[0]?.length ?? 0); c++) {
      if (!grid[0][c] || attached.has(`0:${c}`)) continue;
      for (const [r, col] of connected(grid, parity, [0, c], value => value > 0)) attached.add(`${r}:${col}`);
    }
    const dropped = [];
    if (popped.length) {
      for (let r = 0; r < grid.length; r++) for (let c = 0; c < grid[r].length; c++) {
        if (grid[r][c] > 0 && !attached.has(`${r}:${c}`)) { dropped.push([r, c]); grid[r][c] = 0; }
      }
    }
    while (grid.length > 0 && grid[grid.length - 1].every(value => value === 0)) grid.pop();
    return { grid, popped, dropped, landed: [row, col], bombHit: false, exploded: [] };
  }
  function move(source, row, col, random = Math.random) {
    const hit = shoot(source, row, col, source.current);
    const session = { ...source, grid: hit.grid, shotsUntilRow: source.shotsUntilRow - 1 };
    const gainedCents = (hit.popped.length + hit.dropped.length) * Math.max(1, Math.round(source.betCents * 0.1));
    session.accumulatedCents += gainedCents;
    session.progress = Math.min(1, session.accumulatedCents / session.targetCents);
    let rowAdded = false;
    let gameOver = session.grid.some((line, r) => r >= 11 && line.some(value => value > 0));
    if (!gameOver && session.grid.length === 0) {
      session.grid = newGrid(4, session.parity, random);
      session.shotsUntilRow = source.pressureEvery;
      rowAdded = true;
    } else if (!gameOver && session.shotsUntilRow <= 0) {
      session.parity = 1 - session.parity;
      session.grid.unshift(newRow(0, session.parity, random));
      session.shotsUntilRow = source.pressureEvery;
      rowAdded = true;
      gameOver = session.grid.some((line, r) => r >= 11 && line.some(value => value > 0));
    }
    session.current = source.next;
    session.next = pickColor(session.grid, random);
    if (gameOver) session.status = 'LOST';
    return { ...hit, session, gainedCents, rowAdded, gameOver };
  }
  window.BubbleDemoEngine = Object.freeze({ columns, neighbors, newGrid, pickColor, canLand, shoot, move });
})();
