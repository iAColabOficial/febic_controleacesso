<?php
// Debug sem verificação de login
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo json_encode([
    'status' => 'API funcionando',
    'timestamp' => date('Y-m-d H:i:s'),
    'servidor' => $_SERVER['HTTP_HOST'] ?? 'indefinido',
    'caminho_atual' => __DIR__,
    'caminho_config' => realpath(__DIR__ . '/../config/database.php'),
    'config_existe' => file_exists(__DIR__ . '/../config/database.php'),
    'sessao_ativa' => session_status() === PHP_SESSION_ACTIVE,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'indefinido'
]);
?>