<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!verificarLogin()) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

$termo = trim($_GET['termo'] ?? '');

if (empty($termo) || strlen($termo) < 3) {
    echo json_encode(['success' => false, 'message' => 'Digite pelo menos 3 caracteres']);
    exit;
}

try {
    // Buscar por nome, email ou código QR
    $stmt = $pdo->prepare("
        SELECT 
            id_usuario, nome_usuario, cpf, funcao, email_usuario, codigo_qr
        FROM usuarios 
        WHERE 
            nome_usuario LIKE ? OR 
            email_usuario LIKE ? OR 
            codigo_qr LIKE ? OR
            cpf LIKE ?
        ORDER BY nome_usuario
        LIMIT 20
    ");
    
    $termo_busca = '%' . $termo . '%';
    $stmt->execute([$termo_busca, $termo_busca, $termo_busca, $termo_busca]);
    $participantes = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'participantes' => $participantes,
        'total' => count($participantes)
    ]);
    
} catch (Exception $e) {
    error_log("Erro em buscar_participante.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro na busca']);
}
?>