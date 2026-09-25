<?php
require_once __DIR__ . '/../../app/bootstrap.php';

// O bundle antigo desta rota travava após carregar os assets. O modo treino
// usa a mesma implementação do jogo principal, sem criar rodada nem debitar saldo.
$demoToken = 'demo_' . bin2hex(random_bytes(8));
header('Location: ' . app_url('jogar/?jogarsubway=1BC&SbSB1C2&demo=1&round=' . urlencode($demoToken)), true, 303);
exit;
