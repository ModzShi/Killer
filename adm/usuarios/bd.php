<?php
require_once __DIR__ . '/../../app/bootstrap.php';

try {
    include './../../conectarbanco.php';

    $conn = new mysqli($config['db_host'] ?? 'localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
    
    if ($conn->connect_error) {
        die("Erro na conexão com o banco de dados: " . $conn->connect_error);
    }
    
    $leadAff = isset($_GET['leadAff']) ? $_GET['leadAff'] : null;

    $sql = "SELECT id, data_cadastro, email, senha, telefone, saldo, linkafiliado, plano, depositou, indicados, bloc, saldo_comissao, percas, ganhos, cpa, cpafake, comissaofake, afiliado_ativo
            FROM appconfig";

    if (!empty($leadAff)) {
        $sql .= " WHERE afiliado = ?";
    }

    $sql .= " ORDER BY 
                CASE 
                    WHEN data_cadastro IS NULL THEN 1  -- Coloca registros com data_cadastro vazia por último
                    ELSE 0
                END,
                STR_TO_DATE(data_cadastro, '%H:%i:%s') ASC";

    if (!empty($leadAff)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $leadAff);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    if (!$result) {
        die("Erro na consulta: " . $conn->error);
    }
    
    $data = array();
    
    while ($row = $result->fetch_assoc()) {
        $row['senha'] = ''; // Never return stored passwords or hashes to the browser.
        $data[] = $row;
    }
    
    $conn->close();
    
    header('Content-Type: application/json');
    echo json_encode($data);
} catch(Exception $e) {
    /* log removido */
    http_response_code(200);
}
?>
