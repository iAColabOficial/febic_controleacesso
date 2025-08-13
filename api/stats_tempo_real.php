<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!verificarLogin()) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($curso_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Curso ID obrigatório']);
    exit;
}

try {
    // Estatísticas do curso
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT r.codigo_qr) as total_participantes,
            COUNT(CASE WHEN r.tipo_registro = 'entrada' THEN 1 END) as total_entradas,
            COUNT(CASE WHEN r.tipo_registro = 'saida' THEN 1 END) as total_saidas,
            COUNT(CASE WHEN r.tipo_registro = 'entrada' AND DATE(r.timestamp_registro) = CURDATE() THEN 1 END) as entradas_hoje,
            COUNT(CASE WHEN r.tipo_registro = 'saida' AND DATE(r.timestamp_registro) = CURDATE() THEN 1 END) as saidas_hoje
        FROM registros r
        WHERE r.curso_id = ?
    ");
    $stmt->execute([$curso_id]);
    $stats = $stmt->fetch();
    
    // Últimos registros (10 mais recentes)
    $stmt = $pdo->prepare("
        SELECT r.*, u.nome_usuario, u.funcao
        FROM registros r 
        JOIN usuarios u ON r.codigo_qr = u.codigo_qr 
        WHERE r.curso_id = ? 
        ORDER BY r.timestamp_registro DESC 
        LIMIT 10
    ");
    $stmt->execute([$curso_id]);
    $ultimos_registros = $stmt->fetchAll();
    
    // Registros por hora (últimas 24h)
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(timestamp_registro) as hora,
            COUNT(*) as quantidade,
            tipo_registro
        FROM registros 
        WHERE curso_id = ? 
        AND timestamp_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY HOUR(timestamp_registro), tipo_registro
        ORDER BY hora
    ");
    $stmt->execute([$curso_id]);
    $registros_por_hora = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'ultimos_registros' => $ultimos_registros,
        'registros_por_hora' => $registros_por_hora,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Erro em stats_tempo_real.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar estatísticas']);
}
?>