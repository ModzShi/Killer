<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// The Trader mode uses the Subway account and MySQL connection, but has its
// own practice ledger. No value is transferred to or from appconfig.saldo.
function trader_install(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS trader_accounts (
        player_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        balance_cents BIGINT NOT NULL DEFAULT 100000,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS trader_rounds (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        player_id BIGINT UNSIGNED NOT NULL,
        stake_cents INT UNSIGNED NOT NULL,
        payout_cents INT UNSIGNED NOT NULL DEFAULT 0,
        direction ENUM('UP','DOWN') NOT NULL,
        status ENUM('OPEN','WIN','LOSS') NOT NULL DEFAULT 'OPEN',
        target_status ENUM('WIN','LOSS') NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ends_at DATETIME NOT NULL,
        settled_at DATETIME NULL,
        INDEX player_recent (player_id,id),
        INDEX player_open (player_id,status,ends_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function trader_profile(mysqli $db, int $playerId): array {
    app_query($db, 'INSERT IGNORE INTO trader_accounts(player_id) VALUES(?)', [(string) $playerId]);
    trader_settle($db, $playerId);
    return app_query($db, 'SELECT balance_cents FROM trader_accounts WHERE player_id=?', [(string) $playerId])->get_result()->fetch_assoc() ?: ['balance_cents' => 0];
}

function trader_settle(mysqli $db, int $playerId): void {
    $db->begin_transaction();
    try {
        $account = app_query($db, 'SELECT player_id FROM trader_accounts WHERE player_id=? FOR UPDATE', [(string) $playerId])->get_result()->fetch_assoc();
        if (!$account) { $db->commit(); return; }
        $open = app_query($db, 'SELECT id,stake_cents,target_status FROM trader_rounds WHERE player_id=? AND status=? AND ends_at<=NOW() FOR UPDATE', [(string) $playerId, 'OPEN'])->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($open as $round) {
            $win = $round['target_status'] === 'WIN';
            $payout = $win ? (int) round((int) $round['stake_cents'] * 1.8) : 0;
            app_query($db, 'UPDATE trader_rounds SET status=?,payout_cents=?,settled_at=NOW() WHERE id=? AND status=?', [$win ? 'WIN' : 'LOSS', (string) $payout, (string) $round['id'], 'OPEN']);
            if ($payout > 0) app_query($db, 'UPDATE trader_accounts SET balance_cents=balance_cents+? WHERE player_id=?', [(string) $payout, (string) $playerId]);
        }
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

function trader_start(mysqli $db, int $playerId, string $amount, string $direction): void {
    if (!in_array($direction, ['UP','DOWN'], true)) throw new InvalidArgumentException('Escolha uma direção.');
    if (!preg_match('/^\d{1,5}(?:[.,]\d{1,2})?$/D', $amount)) throw new InvalidArgumentException('Informe um valor válido.');
    $normalized = str_replace(',', '.', $amount);
    $cents = (int) round((float) $normalized * 100);
    if ($cents < 100 || $cents > 100000) throw new InvalidArgumentException('Escolha entre R$ 1,00 e R$ 1.000,00 de créditos de treino.');
    trader_settle($db, $playerId);
    $db->begin_transaction();
    try {
        $account = app_query($db, 'SELECT balance_cents FROM trader_accounts WHERE player_id=? FOR UPDATE', [(string) $playerId])->get_result()->fetch_assoc();
        if (!$account || (int) $account['balance_cents'] < $cents) throw new InvalidArgumentException('Créditos de treino insuficientes.');
        $pending = app_query($db, 'SELECT id FROM trader_rounds WHERE player_id=? AND status=? LIMIT 1 FOR UPDATE', [(string) $playerId, 'OPEN'])->get_result()->fetch_assoc();
        if ($pending) throw new InvalidArgumentException('Aguarde a operação atual terminar.');
        $target = random_int(0, 1) === 1 ? 'WIN' : 'LOSS';
        app_query($db, 'UPDATE trader_accounts SET balance_cents=balance_cents-? WHERE player_id=?', [(string) $cents, (string) $playerId]);
        app_query($db, 'INSERT INTO trader_rounds(player_id,stake_cents,direction,target_status,ends_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 15 SECOND))', [(string) $playerId, (string) $cents, $direction, $target]);
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

function trader_reset(mysqli $db, int $playerId): void {
    trader_settle($db, $playerId);
    $db->begin_transaction();
    try {
        $account = app_query($db, 'SELECT player_id FROM trader_accounts WHERE player_id=? FOR UPDATE', [(string) $playerId])->get_result()->fetch_assoc();
        if (!$account) throw new RuntimeException('Carteira não encontrada.');
        $pending = app_query($db, 'SELECT id FROM trader_rounds WHERE player_id=? AND status=? LIMIT 1 FOR UPDATE', [(string) $playerId, 'OPEN'])->get_result()->fetch_assoc();
        if ($pending) throw new InvalidArgumentException('Espere a operação atual terminar.');
        app_query($db, 'UPDATE trader_accounts SET balance_cents=100000 WHERE player_id=?', [(string) $playerId]);
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

function trader_money(int $cents): string {
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}
