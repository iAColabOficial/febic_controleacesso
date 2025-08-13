<?php
header('Content-Type: application/json');
session_start();

// Verificar se está logado
if (!isset($_SESSION['operador_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

// Incluir config do banco
require_once __DIR__ . '/../config/database.php';

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método deve ser POST']);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

$codigo_qr = $input['codigo_qr'] ?? '';
$curso_id = (int)($input['curso_id'] ?? 0);
$modo = $input['modo'] ?? '';

// Validações básicas
if (empty($codigo_qr) || $curso_id === 0 || !in_array($modo, ['entrada', 'saida'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios faltando']);
    exit;
}

// Validar formato do QR Code
if (!preg_match('/^\d{11}$/', $codigo_qr)) {
    echo json_encode(['success' => false, 'message' => 'QR deve ter 11 dígitos']);
    exit;
}

try {
    // Verificar se o participante existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE codigo_qr = ?");
    $stmt->execute([$codigo_qr]);
    $participante = $stmt->fetch();
    
    if (!$participante) {
        echo json_encode(['success' => false, 'message' => 'Participante não encontrado']);
        exit;
    }
    
    // Verificar se o curso existe
    $stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ? AND status = 'ativo'");
    $stmt->execute([$curso_id]);
    $curso = $stmt->fetch();
    
    if (!$curso) {
        echo json_encode(['success' => false, 'message' => 'Curso não encontrado']);
        exit;
    }
    
    // Verificar se já registrou hoje
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM registros 
        WHERE codigo_qr = ? AND curso_id = ? AND tipo_registro = ? 
        AND DATE(timestamp_registro) = CURDATE()
    ");
    $stmt->execute([$codigo_qr, $curso_id, $modo]);
    $ja_registrado = $stmt->fetchColumn();
    
    if ($ja_registrado > 0) {
        echo json_encode(['success' => false, 'message' => ucfirst($modo) . ' já registrada hoje']);
        exit;
    }
    
    // Para saída, verificar se teve entrada
    if ($modo === 'saida') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM registros 
            WHERE codigo_qr = ? AND curso_id = ? AND tipo_registro = 'entrada'
            AND DATE(timestamp_registro) = CURDATE()
        ");
        $stmt->execute([$codigo_qr, $curso_id]);
        $teve_entrada = $stmt->fetchColumn();
        
        if ($teve_entrada === 0) {
            echo json_encode(['success' => false, 'message' => 'Participante deve fazer entrada antes da saída']);
            exit;
        }
    }
    
    // Sucesso - retornar dados do participante
    echo json_encode([
        'success' => true,
        'participante' => [
            'nome_usuario' => $participante['nome_usuario'],
            'funcao' => $participante['funcao'],
            'codigo_qr' => $participante['codigo_qr']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>