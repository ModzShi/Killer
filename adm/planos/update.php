<?php
require_once __DIR__ . '/../../app/bootstrap.php';


if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!isset($_SESSION["emailadm"])) {
    header("Location: ../login");

    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    exit();
}

include "./../../conectarbanco.php";

$conn = new mysqli($config['db_host'] ?? 'localhost',
    $config["db_user"],
    $config["db_pass"],
    $config["db_name"]
);

if ($conn->connect_error) {
    die("Erro na conexão com o banco de dados: " . $conn->connect_error);
}

$field = $_GET["field"];

$value = $_POST["value"];

$result = $conn->query("SELECT * FROM app LIMIT 1");

if ($result->num_rows > 0) {
    $sql = "UPDATE app SET $field = $value";
} else {
    $sql = "INSERT INTO app SET  $field = $value";
}

$result = $conn->query($sql);

header("Location: ./");
