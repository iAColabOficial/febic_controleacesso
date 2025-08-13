<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Teste simples de conectividade
if (!verificarLogin()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    // Teste de conexão com banco
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $total_users = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cursos WHERE status = 'ativo'");
    $total_cursos = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => 'Conexão OK',
        'server_time' => date('Y-m-d H:i:s'),
        'total_usuarios' => $total_users,
        'total_cursos' => $total_cursos,
        'operador' => $_SESSION['operador_nome'] ?? 'Desconhecido'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro de conexão: ' . $e->getMessage()
    ]);
}
?>