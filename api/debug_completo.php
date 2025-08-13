<?php
header('Content-Type: application/json');

// Credenciais com sua nova senha
$host = 'localhost';
$database = 'u906658109_controleacesso';
$username = 'u906658109_admin123';
$senha = 'OfuturoMerc@do123.';  // ⚠️ Substitua pela senha que você definiu

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $username,
        $senha
    );
    
    // Testar queries
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $total_usuarios = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cursos");
    $total_cursos = $stmt->fetchColumn();
    
    echo json_encode([
        'status' => 'CONECTOU_PERFEITO',
        'credenciais' => [
            'host' => $host,
            'database' => $database,
            'username' => $username
            // Não mostrar senha por segurança
        ],
        'dados_banco' => [
            'total_usuarios' => $total_usuarios,
            'total_cursos' => $total_cursos
        ],
        'message' => '🎉 Banco conectado com sucesso!'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'ERRO_AINDA',
        'erro' => $e->getMessage(),
        'dica' => 'Verifique se a senha foi salva corretamente no cPanel'
    ]);
}
?>