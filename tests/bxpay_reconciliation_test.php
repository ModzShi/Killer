<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/payments/bxpay_service.php';
function check($value, $name) { if (!$value) throw new RuntimeException($name); echo "PASS: $name\n"; }
$deposit = ['reference' => 'bx_test', 'provider_id' => null, 'amount' => '10.00'];
$paid = ['external_id' => 'bx_test', 'id' => 'provider_1', 'type' => 'DEPOSIT', 'status' => 'PAID', 'amount' => 10];
check(bxpay_matches($deposit, $paid), 'Match authenticated paid deposit');
foreach (['external_id' => 'another', 'amount' => 9.99, 'status' => 'PENDING', 'type' => 'WITHDRAW', 'currency' => 'USD'] as $field => $value) {
    check(!bxpay_matches($deposit, array_replace($paid, [$field => $value])), 'Reject mismatch: ' . $field);
}
check(!bxpay_matches($deposit, ['amount' => 10, 'status' => 'PAID', 'type' => 'DEPOSIT']), 'Never match by amount alone');
$pixTxid = 'S80202610022106156160148779008';
$additional = '05' . sprintf('%02d', strlen($pixTxid)) . $pixTxid;
$pixCode = '00020162' . sprintf('%02d', strlen($additional)) . $additional . '6304ABCD';
$qrDeposit = ['reference' => 'bx_qr', 'provider_id' => null, 'pix_code' => $pixCode, 'amount' => '5.00'];
$qrPaid = ['external_id' => $pixTxid, 'id' => 'provider_qr', 'type' => 'DEPOSIT', 'status' => 'PAID', 'amount' => 5, 'tax' => 0.50];
check(bxpay_pix_txid($pixCode) === $pixTxid, 'Read PIX transaction ID from QR payload');
check(bxpay_matches($qrDeposit, $qrPaid), 'Match paid deposit by exact PIX transaction ID and gross amount');
check(!bxpay_matches($qrDeposit, array_replace($qrPaid, ['amount' => 4.50])), 'Do not mistake amount after fees for amount paid');
check(!bxpay_matches($qrDeposit, array_replace($qrPaid, ['external_id' => 'S00000000000000000000000000000'])), 'Reject a different PIX transaction ID');
check(!bxpay_matches(array_replace($qrDeposit, ['pix_code' => '0002016304ABCD']), $qrPaid), 'Reject missing PIX transaction ID');
check(bxpay_matches($deposit, array_replace($paid, ['external_id' => 'provider_external', 'description' => 'Depósito bx_test'])), 'Match exact deposit reference in provider description');
check(bxpay_transactions(['transactions' => [$paid]]) === [$paid], 'Parse transaction list');
try { bxpay_transactions(['balance' => 100]); check(false, 'Unknown response rejected'); }
catch (RuntimeException $e) { check(true, 'Unknown response rejected'); }

// Temporary tables shadow the real tables only on this connection.
require dirname(__DIR__) . '/conectarbanco.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
$db->query("CREATE TEMPORARY TABLE bxpay_deposits (reference VARCHAR(64) PRIMARY KEY, provider_id VARCHAR(255), pix_code TEXT, email VARCHAR(255), amount DECIMAL(12,2), status VARCHAR(24), last_checked DATETIME NULL) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE appconfig (id VARCHAR(255), email VARCHAR(255), saldo DECIMAL(12,2), depositou DECIMAL(12,2), status_primeiro_deposito INT, afiliado VARCHAR(255), afiliado_ativo INT, plano INT, cpa DECIMAL(12,2), comissaofake DECIMAL(12,2), cont_cpa INT) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE confirmar_deposito (externalreference VARCHAR(64), email VARCHAR(255), status VARCHAR(24)) ENGINE=InnoDB");
$db->query('CREATE TEMPORARY TABLE app (deposito_min_cpa DECIMAL(12,2)) ENGINE=InnoDB');
$db->query('INSERT INTO app VALUES (5)');
$db->query("INSERT INTO appconfig VALUES ('user','user@example.test',0,0,0,'affiliate',0,0,0,0,0), ('affiliate','affiliate@example.test',0,0,0,'',0,0,3,0,0)");
$db->query("INSERT INTO bxpay_deposits VALUES ('bx_test',NULL,NULL,'user@example.test',10,'PENDING',NULL)");
$stmt = $db->prepare("INSERT INTO bxpay_deposits (reference, provider_id, pix_code, email, amount, status) VALUES ('bx_qr', NULL, ?, 'user@example.test', 5, 'PENDING')");
$stmt->bind_param('s', $pixCode); $stmt->execute();
check(bxpay_webhook_reference($db, $pixTxid, 'provider_qr') === 'bx_qr', 'Find local deposit from webhook PIX transaction ID');
$db->query("INSERT INTO confirmar_deposito VALUES ('bx_test','user@example.test','WAITING_FOR_APPROVAL')");
check(!bxpay_credit($db, 'bx_test', array_replace($paid, ['amount' => 11])), 'Mismatched amount does not credit');
check(bxpay_credit($db, 'bx_test', $paid), 'Verified payment credits player');
check(bxpay_credit($db, 'bx_test', $paid), 'Duplicate notification is acknowledged');
$user = $db->query("SELECT * FROM appconfig WHERE id = 'user'")->fetch_assoc();
$aff = $db->query("SELECT * FROM appconfig WHERE id = 'affiliate'")->fetch_assoc();
check((float) $user['saldo'] === 10.0 && (float) $user['depositou'] === 10.0, 'Balance credited exactly once');
check((float) $aff['comissaofake'] === 5.0 && (int) $aff['cont_cpa'] === 0, 'Affiliate receives 50% of the confirmed deposit exactly once');
check(bxpay_credit($db, 'bx_qr', $qrPaid), 'Paid PIX transaction ID credits the matching deposit');
check(bxpay_credit($db, 'bx_qr', $qrPaid), 'Repeated PIX notification does not credit twice');
check((float) $db->query("SELECT saldo FROM appconfig WHERE id = 'user'")->fetch_row()[0] === 15.0, 'PIX deposit adds the gross paid amount once');
$db->query("INSERT INTO bxpay_deposits VALUES ('bx_missing',NULL,NULL,'missing@example.test',10,'PENDING',NULL)");
try { bxpay_credit($db, 'bx_missing', array_replace($paid, ['external_id' => 'bx_missing'])); check(false, 'Missing user must fail'); }
catch (RuntimeException $e) { check(true, 'Missing user fails safely'); }
check($db->query("SELECT status FROM bxpay_deposits WHERE reference = 'bx_missing'")->fetch_row()[0] === 'PENDING', 'Failed credit rolls back deposit status');
$db->close();
