<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/affiliate.php';

$cases = [
    [10.00, 5.00],
    [80.01, 40.01],
    [0.01, 0.01],
    [0.00, 0.00],
    [-10.00, 0.00],
    [NAN, 0.00],
];
foreach ($cases as [$deposit, $expected]) {
    $actual = app_affiliate_deposit_commission($deposit);
    if ($actual !== $expected) throw new RuntimeException('Incorrect affiliate commission for deposit ' . (string)$deposit);
}
echo "PASS: 50% affiliate commission calculation and invalid amounts\n";
