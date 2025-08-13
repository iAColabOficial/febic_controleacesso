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
$tipo_registro = $input['tipo_registro'] ?? '';
$operador_id = $_SESSION['operador_id']; // Usar da sessão

// Validações básicas
if (empty($codigo_qr) || $curso_id === 0 || !in_array($tipo_registro, ['entrada', 'saida'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios faltando']);
    exit;
}

try {
    // Verificar duplicata novamente (proteção extra)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM registros 
        WHERE codigo_qr = ? AND curso_id = ? AND tipo_registro = ? 
        AND DATE(timestamp_registro) = CURDATE()
    ");
    $stmt->execute([$codigo_qr, $curso_id, $tipo_registro]);
    
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => ucfirst($tipo_registro) . ' já registrada hoje']);
        exit;
    }
    
    // Inserir registro na tabela
    $stmt = $pdo->prepare("
        INSERT INTO registros (codigo_qr, curso_id, tipo_registro, operador_id, timestamp_registro, sincronizado)
        VALUES (?, ?, ?, ?, NOW(), 1)
    ");
    $stmt->execute([$codigo_qr, $curso_id, $tipo_registro, $operador_id]);
    
    // Buscar dados do participante para retorno
    $stmt = $pdo->prepare("SELECT nome_usuario, funcao FROM usuarios WHERE codigo_qr = ?");
    $stmt->execute([$codigo_qr]);
    $user = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($tipo_registro) . ' registrada com sucesso!',
        'registro' => [
            'nome_participante' => $user['nome_usuario'],
            'funcao' => $user['funcao'],
            'tipo_registro' => $tipo_registro
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar: ' . $e->getMessage()]);
}
?>