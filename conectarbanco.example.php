<?php
declare(strict_types=1);

/**
 * Copie este arquivo para conectarbanco.php apenas no servidor ou computador
 * local. Nunca salve credenciais reais neste modelo versionado.
 */
$config = [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_user' => getenv('DB_USER') ?: '',
    'db_pass' => getenv('DB_PASS') ?: '',
    'db_name' => getenv('DB_NAME') ?: '',
];

require_once __DIR__ . '/app/bootstrap.php';
