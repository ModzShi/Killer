<?php
require_once __DIR__ . '/../../app/withdrawal.php';
require_once __DIR__ . '/../../app/bootstrap.php';

try {

    include './../../conectarbanco.php';

    $conn = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
    withdrawal_install($conn);

    if ($conn->connect_error) {
        die("Erro na conexão com o banco de dados: " . $conn->connect_error);
    }

    $data = array();

    // Saques normais (jogo)
    $sql = "SELECT email, externalreference, destino, chavepix, data, valor, status FROM saques";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['tipo'] = 'Jogo';
            $row['id_registro'] = $row['externalreference'];
            $data[] = $row;
        }
    }

    // Saques de afiliado
    $sql2 = "SELECT id, email, nome, pix, valor, status FROM saque_afiliado";
    $result2 = $conn->query($sql2);

    if ($result2) {
        while ($row2 = $result2->fetch_assoc()) {
            $data[] = array(
                'email' => $row2['email'],
                'externalreference' => '-',
                'destino' => $row2['nome'],
                'chavepix' => $row2['pix'],
                'data' => '-',
                'valor' => $row2['valor'],
                'status' => $row2['status'],
                'tipo' => 'Afiliado',
                'id_registro' => $row2['id'],
            );
        }
    }

    $data = array_reverse($data);

    $conn->close();

    header('Content-Type: application/json');
    echo json_encode($data);
} catch(Exception $e) {
    /* log removido */
    http_response_code(200);
}
?>
