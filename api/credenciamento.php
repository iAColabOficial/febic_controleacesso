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

$acao = $input['acao'] ?? '';
$codigo_qr = $input['codigo_qr'] ?? '';
$tipo_kit = $input['tipo_kit'] ?? 'participante';
$observacoes = trim($input['observacoes'] ?? '');

// Validações básicas
if (empty($codigo_qr)) {
    echo json_encode(['success' => false, 'message' => 'Código QR é obrigatório']);
    exit;
}

// Validar formato do QR Code
if (!preg_match('/^\d{11}$/', $codigo_qr)) {
    echo json_encode(['success' => false, 'message' => 'QR deve ter exatamente 11 dígitos']);
    exit;
}

try {
    if ($acao === 'verificar') {
        // === VERIFICAR PARTICIPANTE PARA CREDENCIAMENTO ===
        
        // Verificar se o participante existe
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE codigo_qr = ? AND ativo = 1");
        $stmt->execute([$codigo_qr]);
        $participante = $stmt->fetch();
        
        if (!$participante) {
            echo json_encode([
                'success' => false, 
                'message' => 'Participante não encontrado no sistema ou inativo'
            ]);
            exit;
        }
        
        // Verificar se já foi credenciado
        $stmt = $pdo->prepare("SELECT * FROM credenciamentos WHERE codigo_qr = ?");
        $stmt->execute([$codigo_qr]);
        $credenciamento = $stmt->fetch();
        
        if ($credenciamento) {
            // Buscar dados do operador que fez o credenciamento
            $stmt = $pdo->prepare("SELECT nome FROM operadores WHERE id = ?");
            $stmt->execute([$credenciamento['operador_id']]);
            $operador = $stmt->fetch();
            
            echo json_encode([
                'success' => false, 
                'message' => 'Participante já credenciado!',
                'ja_credenciado' => true,
                'dados_credenciamento' => [
                    'participante' => $participante['nome_usuario'],
                    'data_credenciamento' => date('d/m/Y H:i', strtotime($credenciamento['data_credenciamento'])),
                    'operador' => $operador['nome'] ?? 'Desconhecido',
                    'tipo_kit' => $credenciamento['tipo_kit'],
                    'observacoes' => $credenciamento['observacoes']
                ]
            ]);
            exit;
        }
        
        // Sucesso - pode credenciar
        echo json_encode([
            'success' => true,
            'participante' => [
                'id_usuario' => $participante['id_usuario'],
                'nome_usuario' => $participante['nome_usuario'],
                'funcao' => $participante['funcao'],
                'email_usuario' => $participante['email_usuario'],
                'telefone_usuario' => $participante['telefone_usuario'],
                'cidade_usuario' => $participante['cidade_usuario'],
                'estado_usuario' => $participante['estado_usuario'],
                'codigo_qr' => $participante['codigo_qr']
            ]
        ]);
        
    } elseif ($acao === 'credenciar') {
        // === REALIZAR CREDENCIAMENTO ===
        
        // Verificar novamente se já foi credenciado (proteção extra)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM credenciamentos WHERE codigo_qr = ?");
        $stmt->execute([$codigo_qr]);
        
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Participante já foi credenciado']);
            exit;
        }
        
        // Verificar se participante existe
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE codigo_qr = ? AND ativo = 1");
        $stmt->execute([$codigo_qr]);
        $participante = $stmt->fetch();
        
        if (!$participante) {
            echo json_encode(['success' => false, 'message' => 'Participante não encontrado']);
            exit;
        }
        
        // Determinar tipo de kit baseado na função se não especificado
        if ($tipo_kit === 'participante' && $participante['funcao']) {
            $funcao_lower = strtolower($participante['funcao']);
            if (strpos($funcao_lower, 'palestrante') !== false || strpos($funcao_lower, 'instrutor') !== false) {
                $tipo_kit = 'palestrante';
            } elseif (strpos($funcao_lower, 'organizador') !== false || strpos($funcao_lower, 'coordenador') !== false) {
                $tipo_kit = 'organizador';
            }
        }
        
        // Inserir credenciamento
        $stmt = $pdo->prepare("
            INSERT INTO credenciamentos (
                codigo_qr, 
                operador_id, 
                tipo_kit, 
                observacoes, 
                device_id, 
                sincronizado
            ) VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        $device_id = $input['device_id'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([
            $codigo_qr, 
            $_SESSION['operador_id'], 
            $tipo_kit, 
            $observacoes, 
            $device_id
        ]);
        
        $credenciamento_id = $pdo->lastInsertId();
        
        // Atualizar estatísticas do dia (se tabela existe)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO credenciamento_stats (data_evento, total_credenciados) 
                VALUES (CURDATE(), 1) 
                ON DUPLICATE KEY UPDATE 
                total_credenciados = total_credenciados + 1
            ");
            $stmt->execute();
        } catch (Exception $e) {
            // Ignora erro se tabela não existe
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Credenciamento realizado com sucesso!',
            'credenciamento_id' => $credenciamento_id,
            'participante' => [
                'nome_usuario' => $participante['nome_usuario'],
                'funcao' => $participante['funcao'],
                'tipo_kit' => $tipo_kit
            ],
            'data_credenciamento' => date('d/m/Y H:i:s'),
            'operador' => $_SESSION['operador_nome']
        ]);
        
    } elseif ($acao === 'estatisticas') {
        // === BUSCAR ESTATÍSTICAS ===
        
        // Estatísticas gerais
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_usuarios,
                COUNT(c.codigo_qr) as total_credenciados,
                COUNT(*) - COUNT(c.codigo_qr) as nao_credenciados,
                ROUND((COUNT(c.codigo_qr) / COUNT(*)) * 100, 2) as percentual_credenciados
            FROM usuarios u
            LEFT JOIN credenciamentos c ON u.codigo_qr = c.codigo_qr
            WHERE u.ativo = 1
        ");
        $stmt->execute();
        $stats_gerais = $stmt->fetch();
        
        // Credenciamentos hoje
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as credenciados_hoje
            FROM credenciamentos 
            WHERE DATE(data_credenciamento) = CURDATE()
        ");
        $stmt->execute();
        $credenciados_hoje = $stmt->fetchColumn();
        
        // Por tipo de kit
        $stmt = $pdo->prepare("
            SELECT 
                tipo_kit, 
                COUNT(*) as quantidade
            FROM credenciamentos 
            GROUP BY tipo_kit
            ORDER BY quantidade DESC
        ");
        $stmt->execute();
        $por_tipo_kit = $stmt->fetchAll();
        
        // Últimos credenciamentos
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                u.nome_usuario,
                u.funcao,
                o.nome as operador_nome
            FROM credenciamentos c
            JOIN usuarios u ON c.codigo_qr = u.codigo_qr
            JOIN operadores o ON c.operador_id = o.id
            ORDER BY c.data_credenciamento DESC
            LIMIT 10
        ");
        $stmt->execute();
        $ultimos_credenciamentos = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'estatisticas' => [
                'geral' => $stats_gerais,
                'credenciados_hoje' => $credenciados_hoje,
                'por_tipo_kit' => $por_tipo_kit,
                'ultimos_credenciamentos' => $ultimos_credenciamentos
            ]
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} catch (Exception $e) {
    error_log("Erro em credenciamento.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do sistema',
        'error_details' => $e->getMessage() // Apenas para debug, remover em produção
    ]);
}
?>