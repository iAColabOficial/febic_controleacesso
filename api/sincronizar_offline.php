<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!verificarLogin()) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['registros']) || !is_array($input['registros'])) {
    echo json_encode(['success' => false, 'message' => 'Dados de sincronização inválidos']);
    exit;
}

$registros_offline = $input['registros'];
$sincronizados = 0;
$erros = [];

try {
    // Processar cada registro offline
    foreach ($registros_offline as $registro) {
        try {
            // Validar dados do registro
            if (empty($registro['codigo_qr']) || 
                empty($registro['curso_id']) || 
                empty($registro['tipo_registro']) || 
                empty($registro['operador_id'])) {
                $erros[] = "Registro incompleto: " . ($registro['id_local'] ?? 'desconhecido');
                continue;
            }
            
            // Verificar se já não foi sincronizado (pelo timestamp + dados únicos)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM registros 
                WHERE codigo_qr = ? AND curso_id = ? AND tipo_registro = ? 
                AND operador_id = ? AND ABS(TIMESTAMPDIFF(SECOND, timestamp_registro, ?)) < 60
            ");
            
            // Converter timestamp do JavaScript para MySQL
            $timestamp_mysql = date('Y-m-d H:i:s', strtotime($registro['timestamp']));
            
            $stmt->execute([
                $registro['codigo_qr'],
                $registro['curso_id'], 
                $registro['tipo_registro'],
                $registro['operador_id'],
                $timestamp_mysql
            ]);
            
            if ($stmt->fetchColumn() > 0) {
                // Já existe registro similar, pular
                $sincronizados++; // Contar como sincronizado
                continue;
            }
            
            // Verificar se participante e curso existem
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE codigo_qr = ?");
            $stmt->execute([$registro['codigo_qr']]);
            if ($stmt->fetchColumn() === 0) {
                $erros[] = "Participante não encontrado: " . $registro['codigo_qr'];
                continue;
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cursos WHERE id = ?");
            $stmt->execute([$registro['curso_id']]);
            if ($stmt->fetchColumn() === 0) {
                $erros[] = "Curso não encontrado: " . $registro['curso_id'];
                continue;
            }
            
            // Verificar duplicata por data (proteção extra)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM registros 
                WHERE codigo_qr = ? AND curso_id = ? AND tipo_registro = ? 
                AND DATE(timestamp_registro) = DATE(?)
            ");
            $stmt->execute([
                $registro['codigo_qr'],
                $registro['curso_id'],
                $registro['tipo_registro'],
                $timestamp_mysql
            ]);
            
            if ($stmt->fetchColumn() > 0) {
                // Já existe para este dia
                $sincronizados++; // Contar como sincronizado
                continue;
            }
            
            // Inserir registro
            $stmt = $pdo->prepare("
                INSERT INTO registros (
                    codigo_qr, curso_id, tipo_registro, operador_id, 
                    timestamp_registro, sincronizado, device_id
                ) VALUES (?, ?, ?, ?, ?, 0, ?)
            ");
            
            $stmt->execute([
                $registro['codigo_qr'],
                $registro['curso_id'],
                $registro['tipo_registro'], 
                $registro['operador_id'],
                $timestamp_mysql,
                $registro['device_id'] ?? 'offline'
            ]);
            
            // Marcar como sincronizado
            $registro_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("UPDATE registros SET sincronizado = 1 WHERE id = ?");
            $stmt->execute([$registro_id]);
            
            $sincronizados++;
            
        } catch (Exception $e) {
            $erros[] = "Erro no registro " . ($registro['id_local'] ?? 'desconhecido') . ": " . $e->getMessage();
            error_log("Erro sincronização individual: " . $e->getMessage());
        }
    }
    
    // Resposta final
    $response = [
        'success' => true,
        'sincronizados' => $sincronizados,
        'total_enviados' => count($registros_offline),
        'message' => "Sincronização concluída: $sincronizados registros processados"
    ];
    
    if (count($erros) > 0) {
        $response['erros'] = $erros;
        $response['message'] .= " com " . count($erros) . " erros";
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Erro geral em sincronizar_offline.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno na sincronização',
        'sincronizados' => $sincronizados
    ]);
}
?>