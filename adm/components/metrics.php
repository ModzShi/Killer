<?php
require_once __DIR__ . '/../../app/game.php';
require_once __DIR__ . '/../../app/withdrawal.php';

function admin_metrics(mysqli $db): array
{
    game_install($db);
    withdrawal_install($db);
    $app = $db->query('SELECT * FROM app LIMIT 1')->fetch_assoc() ?: [];
    $users = $db->query('SELECT COUNT(*) AS total FROM appconfig WHERE demo=0')->fetch_assoc();
    $deposits = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(valor),0) AS amount FROM confirmar_deposito WHERE status='PAID_OUT'")->fetch_assoc();
    $pending = $db->query("SELECT COUNT(*) AS total FROM confirmar_deposito WHERE status='WAITING_FOR_APPROVAL'")->fetch_assoc();
    $withdrawals = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(valor),0) AS amount FROM saques WHERE LOWER(status) IN ('pago','paid')")->fetch_assoc();
    $rounds = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(g.bet),0) AS stakes, COALESCE(SUM(g.payout),0) AS prizes,
        COALESCE(SUM(CASE WHEN g.status='WIN' THEN 1 ELSE 0 END),0) AS wins,
        COALESCE(SUM(CASE WHEN g.status='LOSS' THEN 1 ELSE 0 END),0) AS losses
        FROM game_rounds g JOIN appconfig u ON u.email COLLATE utf8mb4_unicode_ci = g.email COLLATE utf8mb4_unicode_ci AND u.demo=0 WHERE g.status IN ('WIN','LOSS')")->fetch_assoc();
    $depositTrend = $db->query("SELECT DATE(STR_TO_DATE(data, '%d/%m/%Y %H:%i')) AS day, COALESCE(SUM(valor),0) AS amount
        FROM confirmar_deposito WHERE status='PAID_OUT' AND STR_TO_DATE(data, '%d/%m/%Y %H:%i') >= CURDATE()-INTERVAL 6 DAY
        GROUP BY day")->fetch_all(MYSQLI_ASSOC);
    $ggrTrend = $db->query("SELECT DATE(g.settled_at) AS day, COALESCE(SUM(g.bet-g.payout),0) AS amount
        FROM game_rounds g JOIN appconfig u ON u.email COLLATE utf8mb4_unicode_ci = g.email COLLATE utf8mb4_unicode_ci AND u.demo=0
        WHERE g.status IN ('WIN','LOSS') AND g.settled_at >= CURDATE()-INTERVAL 6 DAY GROUP BY day")->fetch_all(MYSQLI_ASSOC);
    $fill = static function (array $rows): array {
        $indexed=[];
        foreach ($rows as $row) if (!empty($row['day'])) $indexed[$row['day']] = (float)$row['amount'];
        $days=[];
        for ($n=6;$n>=0;$n--) { $day=date('Y-m-d', strtotime("-$n days")); $days[]=['label'=>date('d/m',strtotime($day)), 'amount'=>$indexed[$day]??0.0]; }
        return $days;
    };
    return [
        'app'=>$app, 'users'=>(int)($users['total']??0),
        'deposits'=>(int)($deposits['total']??0), 'deposit_amount'=>(float)($deposits['amount']??0),
        'pending_deposits'=>(int)($pending['total']??0),
        'withdrawals'=>(int)($withdrawals['total']??0), 'withdraw_amount'=>(float)($withdrawals['amount']??0),
        'rounds'=>(int)($rounds['total']??0), 'stakes'=>(float)($rounds['stakes']??0), 'prizes'=>(float)($rounds['prizes']??0),
        'wins'=>(int)($rounds['wins']??0), 'losses'=>(int)($rounds['losses']??0),
        'ggr'=>(float)($rounds['stakes']??0)-(float)($rounds['prizes']??0),
        'deposit_trend'=>$fill($depositTrend), 'ggr_trend'=>$fill($ggrTrend),
    ];
}

function admin_money(float $value): string { return 'R$ '.number_format($value, 2, ',', '.'); }

function admin_trend_html(array $days, string $label): string
{
    $max=1.0;
    foreach ($days as $day) $max=max($max, abs((float)$day['amount']));
    $html='<div class="admin-chart" role="img" aria-label="'.app_escape($label).'">';
    foreach ($days as $day) {
        $amount=(float)$day['amount'];
        $height=$amount==0.0?2:max(5,min(100,abs($amount)/$max*100));
        $html.='<div class="admin-chart-col'.($amount<0?' loss':'').'"><b>'.app_escape(admin_money($amount)).'</b><span style="height:'.number_format($height,1,'.','').'%"></span><small>'.app_escape($day['label']).'</small></div>';
    }
    return $html.'</div>';
}
