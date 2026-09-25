<?php
require_once __DIR__ . '/auth.php';
require_once SK_ROOT . '/payments/bxpay_service.php';
if (!SK_OFFLINE) { http_response_code(404); exit; }
function offline_deposit(mysqli $db, string $email, float $amount): string {
    if (!is_finite($amount) || $amount < 5 || $amount > 10000 || abs($amount-round($amount,2)) > 0.000001) throw new InvalidArgumentException('Use um valor entre R$ 5 e R$ 10.000, com até duas casas decimais.');
    if (substr($db->query('SELECT DATABASE()')->fetch_row()[0], -8) !== '_offline') throw new RuntimeException('A simulação exige o banco offline.');
    bxpay_install($db); $reference = 'test_' . bin2hex(random_bytes(16));
    $db->begin_transaction();
    try {
        app_query($db,"INSERT INTO bxpay_deposits(reference,email,amount,status,pix_code) VALUES(?,?,?,'PENDING','SIMULACAO SEM VALOR')",[$reference,$email,(string)$amount]);
        app_query($db,"INSERT INTO confirmar_deposito(email,externalreference,valor,status,data,pix_code) VALUES(?,?,?,'WAITING_FOR_APPROVAL',?,'SIMULACAO SEM VALOR')",[$email,$reference,(string)$amount,date('d/m/Y H:i')]);
        $db->commit(); return $reference;
    } catch(Throwable $e) { $db->rollback(); throw $e; }
}
