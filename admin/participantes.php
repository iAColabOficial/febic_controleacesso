<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../vendor/autoload.php'; // Para PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

verificarPermissaoAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Função para gerar código QR único
function gerarCodigoQR($pdo) {
    do {
        // Buscar o maior código existente
        $stmt = $pdo->query("SELECT MAX(CAST(codigo_qr AS UNSIGNED)) as max_codigo FROM usuarios");
        $max = $stmt->fetchColumn() ?: 0;
        $novo_codigo = str_pad($max + 1, 11, '0', STR_PAD_LEFT);
        
        // Verificar se já existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE codigo_qr = ?");
        $stmt->execute([$novo_codigo]);
        $existe = $stmt->fetchColumn();
        
    } while ($existe > 0);
    
    return $novo_codigo;
}

// Upload de planilha
if ($_POST && isset($_FILES['planilha'])) {
    $arquivo = $_FILES['planilha'];
    
    if ($arquivo['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        
        if (in_array($extensao, ['xlsx', 'xls'])) {
            try {
                $spreadsheet = IOFactory::load($arquivo['tmp_name']);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                // Remover cabeçalho
                $header = array_shift($rows);
                
                $importados = 0;
                $erros = 0;
                
                // Limpar participantes existentes (confirmação necessária)
                if (isset($_POST['limpar_existentes'])) {
                    $pdo->exec("DELETE FROM usuarios");
                }
                
                foreach ($rows as $row) {
                    // Pular linhas vazias
                    if (empty(array_filter($row))) continue;
                    
                    try {
                        $codigo_qr = gerarCodigoQR($pdo);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO usuarios (
                                id_usuario, nome_usuario, cpf, funcao, email_usuario, 
                                telefone_usuario, sexo, cidade_usuario, estado_usuario, id_projeto, 
                                identificador, tipo, area_conhecimento_pai, nome_projeto, codigo_qr
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $row[0] ?? '', // id_usuario
                            $row[1] ?? '', // nome_usuario  
                            $row[2] ?? '', // cpf
                            $row[3] ?? '', // funcao
                            $row[4] ?? '', // email_usuario
                            $row[5] ?? '', // telefone_usuario
                            $row[6] ?? '', // sexo
                            $row[7] ?? '', // cidade_usuario ← NOVA COLUNA
                            $row[8] ?? '', // estado_usuario (era $row[7])
                            $row[9] ?? '', // id_projeto (era $row[8])
                            $row[10] ?? '', // identificador (era $row[9])
                            $row[11] ?? '', // tipo (era $row[10])
                            $row[12] ?? '', // area_conhecimento_pai (era $row[11])
                            $row[13] ?? '', // nome_projeto (era $row[12])
                            $codigo_qr
                        ]);
                        
                        $importados++;
                        
                    } catch (Exception $e) {
                        $erros++;
                    }
                }
                
                $mensagem = "Importação concluída! $importados participantes importados.";
                if ($erros > 0) $mensagem .= " $erros erros encontrados.";
                $tipo_mensagem = 'success';
                
            } catch (Exception $e) {
                $mensagem = "Erro ao processar planilha: " . $e->getMessage();
                $tipo_mensagem = 'error';
            }
        } else {
            $mensagem = "Formato inválido. Use apenas .xlsx ou .xls";
            $tipo_mensagem = 'error';
        }
    } else {
        $mensagem = "Erro no upload do arquivo.";
        $tipo_mensagem = 'error';
    }
}

// Download da planilha com QR codes
if (isset($_GET['download'])) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios ORDER BY codigo_qr");
    $stmt->execute();
    $usuarios = $stmt->fetchAll();
    
    if (count($usuarios) > 0) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Cabeçalhos - ATUALIZADO COM CIDADE USUARIO
        $headers = [
            'ID Usuario', 'Nome Usuario', 'CPF', 'Funcao', 'Email Usuario',
            'Telefone Usuario', 'Sexo', 'Cidade Usuario', 'Estado Usuario', 'ID Projeto',
            'Identificador', 'Tipo', 'Area Conhecimento PAI', 'Nome Projeto', 'Codigo QR'
        ];
        
        $sheet->fromArray([$headers], null, 'A1');
        
        // Dados - ATUALIZADO COM CIDADE USUARIO
        $row = 2;
        foreach ($usuarios as $usuario) {
            $sheet->fromArray([
                $usuario['id_usuario'], $usuario['nome_usuario'], $usuario['cpf'],
                $usuario['funcao'], $usuario['email_usuario'], $usuario['telefone_usuario'],
                $usuario['sexo'], $usuario['cidade_usuario'], $usuario['estado_usuario'], $usuario['id_projeto'],
                $usuario['identificador'], $usuario['tipo'], $usuario['area_conhecimento_pai'],
                $usuario['nome_projeto'], $usuario['codigo_qr']
            ], null, 'A' . $row);
            $row++;
        }
        
        $filename = 'participantes_febic_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

// Contar participantes
$total_participantes = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participantes - Admin FEBIC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .upload-area { border: 2px dashed #ddd; padding: 40px; text-align: center; border-radius: 10px; margin-bottom: 20px; }
        .file-input { margin: 20px 0; }
        .stats-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-secondary { background: #6c757d; }
        .btn-success { background: #28a745; }
        .checkbox-group { margin: 20px 0; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Gerenciar Participantes</h1>
            <a href="index.php" class="btn btn-secondary">← Voltar</a>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Upload de Participantes</h2>
            <p>Envie a planilha Excel com os dados dos participantes. O sistema gerará automaticamente os códigos QR.</p>
            <p><strong>⚠️ IMPORTANTE:</strong> A planilha deve conter a coluna "Cidade Usuario" na posição correta (após Sexo, antes de Estado Usuario).</p>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="upload-area">
                    <p>📁 Selecione a planilha Excel (.xlsx ou .xls)</p>
                    <input type="file" name="planilha" accept=".xlsx,.xls" required class="file-input">
                </div>
                
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="limpar_existentes" value="1">
                        Substituir participantes existentes (apaga dados atuais)
                    </label>
                </div>
                
                <button type="submit" class="btn">📤 Importar Planilha</button>
            </form>
        </div>

        <div class="card">
            <div class="stats-row">
                <div>
                    <h2>Participantes Cadastrados</h2>
                    <p><strong><?= number_format($total_participantes) ?></strong> participantes no sistema</p>
                </div>
                <div>
                    <?php if ($total_participantes > 0): ?>
                        <a href="?download=1" class="btn btn-success">⬇️ Download Planilha com QR</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>