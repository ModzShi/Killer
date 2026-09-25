<?php
require_once __DIR__ . '/../app/bootstrap.php';
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['message' => 'Endpoint antigo desativado.']);
exit;


include "./../conectarbanco.php";

$conn = new mysqli($config['db_host'] ?? 'localhost',
    $config["db_user"],
    $config["db_pass"],
    $config["db_name"]
);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!isset($_SESSION["email"])) {
    header("Location: ../");

    exit();
} elseif ($_SERVER["REQUEST_METHOD"] !== "GET") {
    exit();
}

$session = $_POST["session"];

$action = $_GET["action"];

$type = $_GET["type"];

$bet = $_GET["bet"];

$acumulado = $_GET["val"];

if ($action == "game" && $type == "demo") {
    /* log removido */

    http_response_code(200);

    exit();
} elseif ($action != "game" || $type != "win") {
    /* log removido */

    http_response_code(500);

    exit();
}

$email = isset($_SESSION["email"]) ? $_SESSION["email"] : "";

$updateStmt = $conn->prepare(
    "UPDATE appconfig SET saldo = saldo + {$acumulado}, ganhos = ganhos + {$acumulado} WHERE email = ?"
);

$updateStmt->bind_param("s", $email);

$updateStmt->execute();

/* log removido */

http_response_code(200);

exit();

?>
