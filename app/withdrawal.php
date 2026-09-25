<?php
require_once __DIR__ . '/auth.php';

function withdrawal_install(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS saques (
        email VARCHAR(255) NULL,
        externalreference VARCHAR(255) NULL,
        destino VARCHAR(255) NULL,
        chavepix VARCHAR(255) NULL,
        data VARCHAR(255) NULL,
        valor DECIMAL(12,2) NULL,
        status VARCHAR(255) NULL,
        UNIQUE KEY uq_saques_reference (externalreference),
        INDEX idx_saques_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS saque_afiliado (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        nome VARCHAR(255) NOT NULL,
        pix VARCHAR(255) NOT NULL,
        valor DECIMAL(12,2) NOT NULL,
        status VARCHAR(255) NOT NULL,
        INDEX idx_saque_afiliado_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
