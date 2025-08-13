<?php
// Teste mais básico possível
header('Content-Type: application/json');
echo json_encode([
    'status' => 'funcionou',
    'time' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['HTTP_HOST'] ?? 'indefinido'
]);
?>