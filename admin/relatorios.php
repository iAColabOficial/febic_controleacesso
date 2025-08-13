<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

verificarPermissaoAdmin();

// Buscar cursos para filtro
$stmt = $pdo->prepare("
    SELECT c.*, tc.nome as tipo_nome 
    FROM cursos c 
    JOIN tipos_controle tc ON c.tipo_controle_id = tc.id 
    ORDER BY c.data DESC, c.nome
");
$stmt->execute();
$cursos = $stmt->fetchAll();

// Filtros
$filtro_curso = $_GET['curso'] ?? '';
$filtro_data = $_GET['data'] ?? '';
$filtro_nome = $_GET['nome'] ?? '';

// Gerar relatório
if (isset($_GET['gerar_excel'])) {
    $sql = "
        SELECT DISTINCT 
            u.id_usuario,
            u.nome_usuario,
            u.cpf,
            u.funcao,
            u.email_usuario,
            u.cidade_usuario,
            u.estado_usuario,
            u.codigo_qr,
            c.nome as curso_nome,
            c.data as curso_data,
            c.periodo,
            GROUP_CONCAT(
                CASE r.tipo_registro 
                    WHEN 'entrada' THEN CONCAT('Entrada: ', TIME_FORMAT(r.timestamp_registro, '%H:%i'))
                    WHEN 'saida' THEN CONCAT('Saída: ', TIME_FORMAT(r.timestamp_registro, '%H:%i'))
                END 
                ORDER BY r.timestamp_registro SEPARATOR ' | '
            ) as registros,
            CASE 
                WHEN COUNT(CASE WHEN r.tipo_registro = 'entrada' THEN 1 END) > 0 
                     AND COUNT(CASE WHEN r.tipo_registro = 'saida' THEN 1 END) > 0 
                THEN 'Concluído'
                WHEN COUNT(CASE WHEN r.tipo_registro = 'entrada' THEN 1 END) > 0
                THEN 'Incompleto (sem saída)'
                ELSE 'Ausente'
            END as status_participacao
        FROM usuarios u
        JOIN registros r ON u.codigo_qr = r.codigo_qr
        JOIN cursos c ON r.curso_id = c.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filtro_curso)) {
        $sql .= " AND c.id = ?";
        $params[] = $filtro_curso;
    }
    
    if (!empty($filtro_data)) {
        $sql .= " AND c.data = ?";
        $params[] = $filtro_data;
    }
    
    if (!empty($filtro_nome)) {
        $sql .= " AND u.nome_usuario LIKE ?";
        $params[] = '%' . $filtro_nome . '%';
    }
    
    $sql .= " GROUP BY u.id, c.id ORDER BY u.nome_usuario, c.data, c.nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if (count($dados) > 0) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Cabeçalhos - ATUALIZADO COM CIDADE E ESTADO
        $headers = [
            'ID Usuário', 'Nome', 'CPF', 'Função', 'Email', 'Cidade', 'Estado', 'Código QR',
            'Curso', 'Data', 'Período', 'Registros', 'Status'
        ];
        
        $sheet->fromArray([$headers], null, 'A1');
        
        // Formatação do cabeçalho - AJUSTADO PARA MAIS COLUNAS (A1:M1)
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A1:M1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A1:M1')->getFill()->getStartColor()->setARGB('FF4472C4');
        $sheet->getStyle('A1:M1')->getFont()->getColor()->setARGB('FFFFFFFF');
        
        // Dados - ATUALIZADO COM CIDADE E ESTADO
        $row = 2;
        foreach ($dados as $item) {
            $sheet->fromArray([
                $item['id_usuario'],
                $item['nome_usuario'],
                $item['cpf'],
                $item['funcao'],
                $item['email_usuario'],
                $item['cidade_usuario'],
                $item['estado_usuario'],
                $item['codigo_qr'],
                $item['curso_nome'],
                date('d/m/Y', strtotime($item['curso_data'])),
                $item['periodo'],
                $item['registros'],
                $item['status_participacao']
            ], null, 'A' . $row);
            
            // Colorir status - AJUSTADO PARA COLUNA M (era K)
            $status_cell = 'M' . $row;
            if ($item['status_participacao'] === 'Concluído') {
                $sheet->getStyle($status_cell)->getFont()->getColor()->setARGB('FF008000');
            } elseif ($item['status_participacao'] === 'Incompleto (sem saída)') {
                $sheet->getStyle($status_cell)->getFont()->getColor()->setARGB('FFFF6600');
            }
            
            $row++;
        }
        
        // Auto-ajustar colunas - ATUALIZADO PARA M (era K)
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $filename = 'relatorio_febic_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

// Estatísticas gerais
$stats = [];

// Total de participações hoje
$stmt = $pdo->prepare("SELECT COUNT(*) FROM registros WHERE DATE(timestamp_registro) = CURDATE()");
$stmt->execute();
$stats['registros_hoje'] = $stmt->fetchColumn();

// Participações por curso (top 5)
$stmt = $pdo->prepare("
    SELECT c.nome, COUNT(DISTINCT r.codigo_qr) as total
    FROM cursos c
    LEFT JOIN registros r ON c.id = r.curso_id
    GROUP BY c.id
    ORDER BY total DESC
    LIMIT 5
");
$stmt->execute();
$stats['top_cursos'] = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Admin FEBIC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 15px; text-align: center; }
        .stat-number { font-size: 32px; font-weight: bold; margin-bottom: 5px; }
        .stat-label { opacity: 0.9; }
        .top-cursos { background: white; border-radius: 10px; padding: 15px; }
        .curso-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .curso-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>📊 Relatórios e Estatísticas</h1>
            <a href="index.php" class="btn btn-secondary">← Voltar</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['registros_hoje']) ?></div>
                <div class="stat-label">Registros Hoje</div>
            </div>
            <div class="top-cursos">
                <h3 style="margin-bottom: 15px; color: #333;">🏆 Top Cursos</h3>
                <?php foreach ($stats['top_cursos'] as $curso): ?>
                    <div class="curso-item">
                        <span><?= htmlspecialchars($curso['nome']) ?></span>
                        <span><strong><?= $curso['total'] ?> participantes</strong></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h2>🔍 Gerar Relatório Personalizado</h2>
            <p>Aplique filtros e gere relatórios em Excel com dados de participação (incluindo cidade e estado dos participantes).</p>
            
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
                        <label for="curso">Filtrar por Curso:</label>
                        <select name="curso" id="curso">
                            <option value="">Todos os cursos</option>
                            <?php foreach ($cursos as $curso): ?>
                                <option value="<?= $curso['id'] ?>" <?= $filtro_curso == $curso['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($curso['tipo_nome'] . ' - ' . $curso['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="data">Filtrar por Data:</label>
                        <input type="date" name="data" id="data" value="<?= htmlspecialchars($filtro_data) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="nome">Buscar por Nome:</label>
                        <input type="text" name="nome" id="nome" placeholder="Digite o nome..." value="<?= htmlspecialchars($filtro_nome) ?>">
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px;">
                    <button type="submit" class="btn btn-secondary">🔍 Aplicar Filtros</button>
                    <button type="submit" name="gerar_excel" value="1" class="btn btn-success">📥 Gerar Excel</button>
                    <?php if (!empty($filtro_curso) || !empty($filtro_data) || !empty($filtro_nome)): ?>
                        <a href="relatorios.php" class="btn" style="background: #6c757d;">🔄 Limpar Filtros</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>📋 Relatórios Rápidos</h2>
            <div class="form-row">
                <a href="?data=<?= date('Y-m-d') ?>&gerar_excel=1" class="btn">📅 Registros de Hoje</a>
                <a href="?gerar_excel=1" class="btn">📊 Relatório Completo</a>
            </div>
        </div>
    </div>
</body>
</html>