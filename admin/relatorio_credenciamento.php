<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

verificarPermissaoAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Filtros
$filtro_status = $_GET['status'] ?? 'todos'; // todos, credenciados, pendentes
$filtro_tipo_kit = $_GET['tipo_kit'] ?? '';
$filtro_data = $_GET['data'] ?? '';
$filtro_operador = $_GET['operador'] ?? '';
$filtro_nome = $_GET['nome'] ?? '';

// Gerar relatório Excel
if (isset($_GET['gerar_excel'])) {
    try {
        // Query principal
        $sql = "
            SELECT 
                u.id_usuario,
                u.nome_usuario,
                u.cpf,
                u.funcao,
                u.email_usuario,
                u.telefone_usuario,
                u.cidade_usuario,
                u.estado_usuario,
                u.codigo_qr,
                CASE 
                    WHEN c.codigo_qr IS NOT NULL THEN 'SIM'
                    ELSE 'NÃO'
                END as credenciado,
                c.data_credenciamento,
                c.tipo_kit,
                c.observacoes,
                o.nome as operador_nome
            FROM usuarios u
            LEFT JOIN credenciamentos c ON u.codigo_qr = c.codigo_qr
            LEFT JOIN operadores o ON c.operador_id = o.id
            WHERE u.ativo = 1
        ";
        
        $params = [];
        
        // Aplicar filtros
        if ($filtro_status === 'credenciados') {
            $sql .= " AND c.codigo_qr IS NOT NULL";
        } elseif ($filtro_status === 'pendentes') {
            $sql .= " AND c.codigo_qr IS NULL";
        }
        
        if (!empty($filtro_tipo_kit)) {
            $sql .= " AND c.tipo_kit = ?";
            $params[] = $filtro_tipo_kit;
        }
        
        if (!empty($filtro_data)) {
            $sql .= " AND DATE(c.data_credenciamento) = ?";
            $params[] = $filtro_data;
        }
        
        if (!empty($filtro_operador)) {
            $sql .= " AND c.operador_id = ?";
            $params[] = $filtro_operador;
        }
        
        if (!empty($filtro_nome)) {
            $sql .= " AND u.nome_usuario LIKE ?";
            $params[] = '%' . $filtro_nome . '%';
        }
        
        $sql .= " ORDER BY 
            CASE WHEN c.codigo_qr IS NOT NULL THEN 0 ELSE 1 END,
            c.data_credenciamento DESC,
            u.nome_usuario";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $participantes = $stmt->fetchAll();
        
        if (count($participantes) > 0) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Configurar planilha
            $sheet->setTitle('Relatório Credenciamento');
            
            // Cabeçalhos
            $headers = [
                'ID', 'Nome Completo', 'CPF', 'Função', 'Email', 'Telefone',
                'Cidade', 'Estado', 'QR Code', 'CREDENCIADO', 'Data/Hora Credenciamento', 
                'Tipo Kit', 'Observações', 'Operador'
            ];
            
            $sheet->fromArray([$headers], null, 'A1');
            
            // Formatação do cabeçalho
            $headerRange = 'A1:N1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($headerRange)->getFill()->getStartColor()->setARGB('FF28a745');
            $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB('FFFFFFFF');
            
            // Dados
            $row = 2;
            $credenciados = 0;
            $nao_credenciados = 0;
            $stats_tipos = [];
            
            foreach ($participantes as $participante) {
                // Formatar data de credenciamento
                $data_credenciamento = '';
                if ($participante['data_credenciamento']) {
                    $data_credenciamento = date('d/m/Y H:i', strtotime($participante['data_credenciamento']));
                }
                
                $sheet->fromArray([
                    $participante['id_usuario'],
                    $participante['nome_usuario'],
                    $participante['cpf'],
                    $participante['funcao'],
                    $participante['email_usuario'],
                    $participante['telefone_usuario'],
                    $participante['cidade_usuario'],
                    $participante['estado_usuario'],
                    $participante['codigo_qr'],
                    $participante['credenciado'],
                    $data_credenciamento,
                    $participante['tipo_kit'] ? ucfirst($participante['tipo_kit']) : '',
                    $participante['observacoes'],
                    $participante['operador_nome']
                ], null, 'A' . $row);
                
                // Colorir coluna "CREDENCIADO"
                $credenciadoCell = 'J' . $row;
                if ($participante['credenciado'] === 'SIM') {
                    $sheet->getStyle($credenciadoCell)->getFont()->getColor()->setARGB('FF155724');
                    $sheet->getStyle($credenciadoCell)->getFont()->setBold(true);
                    $credenciados++;
                    
                    // Contabilizar tipos de kit
                    $tipo = $participante['tipo_kit'] ?: 'não informado';
                    $stats_tipos[$tipo] = ($stats_tipos[$tipo] ?? 0) + 1;
                } else {
                    $sheet->getStyle($credenciadoCell)->getFont()->getColor()->setARGB('FF721c24');
                    $nao_credenciados++;
                }
                
                $row++;
            }
            
            // Adicionar estatísticas no final
            $row += 2;
            $sheet->setCellValue('A' . $row, 'ESTATÍSTICAS:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Total de Participantes:');
            $sheet->setCellValue('B' . $row, count($participantes));
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Credenciados:');
            $sheet->setCellValue('B' . $row, $credenciados);
            $sheet->getStyle('B' . $row)->getFont()->getColor()->setARGB('FF155724');
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Pendentes:');
            $sheet->setCellValue('B' . $row, $nao_credenciados);
            $sheet->getStyle('B' . $row)->getFont()->getColor()->setARGB('FF721c24');
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Percentual Credenciado:');
            $percentual = count($participantes) > 0 ? round(($credenciados / count($participantes)) * 100, 2) : 0;
            $sheet->setCellValue('B' . $row, $percentual . '%');
            $row += 2;
            
            // Estatísticas por tipo de kit
            if (count($stats_tipos) > 0) {
                $sheet->setCellValue('A' . $row, 'POR TIPO DE KIT:');
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;
                
                foreach ($stats_tipos as $tipo => $quantidade) {
                    $sheet->setCellValue('A' . $row, ucfirst($tipo) . ':');
                    $sheet->setCellValue('B' . $row, $quantidade);
                    $row++;
                }
            }
            
            // Auto-ajustar colunas
            foreach (range('A', 'N') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Nome do arquivo
            $filtros_nome = [];
            if ($filtro_status !== 'todos') $filtros_nome[] = $filtro_status;
            if ($filtro_tipo_kit) $filtros_nome[] = $filtro_tipo_kit;
            if ($filtro_data) $filtros_nome[] = $filtro_data;
            
            $filename = 'credenciamento_febic_' . date('Y-m-d_H-i-s');
            if (count($filtros_nome) > 0) {
                $filename .= '_' . implode('_', $filtros_nome);
            }
            $filename .= '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } else {
            $mensagem = 'Nenhum dado encontrado com os filtros aplicados.';
            $tipo_mensagem = 'warning';
        }
        
    } catch (Exception $e) {
        error_log("Erro ao gerar Excel: " . $e->getMessage());
        $mensagem = 'Erro ao gerar relatório: ' . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// Buscar operadores para filtro
$stmt = $pdo->prepare("SELECT id, nome FROM operadores WHERE ativo = 1 ORDER BY nome");
$stmt->execute();
$operadores = $stmt->fetchAll();

// Estatísticas gerais para exibição
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

// Credenciados hoje
$stmt = $pdo->prepare("SELECT COUNT(*) FROM credenciamentos WHERE DATE(data_credenciamento) = CURDATE()");
$stmt->execute();
$credenciados_hoje = $stmt->fetchColumn();

// Estatísticas por tipo de kit
$stmt = $pdo->prepare("
    SELECT 
        tipo_kit, 
        COUNT(*) as quantidade
    FROM credenciamentos 
    GROUP BY tipo_kit
    ORDER BY quantidade DESC
");
$stmt->execute();
$stats_tipos = $stmt->fetchAll();

// Preview de dados (se filtros aplicados)
$preview_dados = [];
if (!empty($filtro_status) && $filtro_status !== 'todos' || !empty($filtro_tipo_kit) || !empty($filtro_data) || !empty($filtro_operador) || !empty($filtro_nome)) {
    $sql = "
        SELECT 
            u.nome_usuario,
            u.funcao,
            u.cidade_usuario,
            u.estado_usuario,
            CASE 
                WHEN c.codigo_qr IS NOT NULL THEN 'SIM'
                ELSE 'NÃO'
            END as credenciado,
            c.data_credenciamento,
            c.tipo_kit,
            o.nome as operador_nome
        FROM usuarios u
        LEFT JOIN credenciamentos c ON u.codigo_qr = c.codigo_qr
        LEFT JOIN operadores o ON c.operador_id = o.id
        WHERE u.ativo = 1
    ";
    
    $params = [];
    
    if ($filtro_status === 'credenciados') {
        $sql .= " AND c.codigo_qr IS NOT NULL";
    } elseif ($filtro_status === 'pendentes') {
        $sql .= " AND c.codigo_qr IS NULL";
    }
    
    if (!empty($filtro_tipo_kit)) {
        $sql .= " AND c.tipo_kit = ?";
        $params[] = $filtro_tipo_kit;
    }
    
    if (!empty($filtro_data)) {
        $sql .= " AND DATE(c.data_credenciamento) = ?";
        $params[] = $filtro_data;
    }
    
    if (!empty($filtro_operador)) {
        $sql .= " AND c.operador_id = ?";
        $params[] = $filtro_operador;
    }
    
    if (!empty($filtro_nome)) {
        $sql .= " AND u.nome_usuario LIKE ?";
        $params[] = '%' . $filtro_nome . '%';
    }
    
    $sql .= " ORDER BY 
        CASE WHEN c.codigo_qr IS NOT NULL THEN 0 ELSE 1 END,
        c.data_credenciamento DESC,
        u.nome_usuario
        LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $preview_dados = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Credenciamento - FEBIC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 15px; text-align: center; }
        .stat-number { font-size: 32px; font-weight: bold; margin-bottom: 5px; }
        .stat-label { opacity: 0.9; }
        .preview-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .preview-table th, .preview-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; }
        .preview-table th { background: #f8f9fa; font-weight: bold; }
        .preview-table .credenciado-sim { color: #155724; font-weight: bold; }
        .preview-table .credenciado-nao { color: #721c24; font-weight: bold; }
        .kit-badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .kit-participante { background: #d4edda; color: #155724; }
        .kit-palestrante { background: #fff3cd; color: #856404; }
        .kit-organizador { background: #d1ecf1; color: #0c5460; }
        .kit-visitante { background: #f8d7da; color: #721c24; }
        .btn-excel { background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: bold; text-decoration: none; display: inline-block; margin: 0 5px; }
        .btn-excel:hover { background: #218838; transform: translateY(-1px); }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .filter-summary { background: #e7f3ff; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #007bff; }
        .empty-state { text-align: center; padding: 40px; color: #666; }
        .tipos-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
        .tipo-stat { background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center; }
        .tipo-numero { font-size: 24px; font-weight: bold; color: #28a745; }
        .tipo-label { font-size: 12px; color: #666; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>📊 Relatório de Credenciamento</h1>
            <div>
                <a href="../credenciamento.php" class="btn-excel" style="background: #17a2b8;">🎫 Credenciamento</a>
                <a href="index.php" class="btn-excel btn-secondary">← Admin</a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <!-- Estatísticas Gerais -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats_gerais['total_credenciados']) ?></div>
                <div class="stat-label">Credenciados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($credenciados_hoje) ?></div>
                <div class="stat-label">Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats_gerais['nao_credenciados']) ?></div>
                <div class="stat-label">Pendentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats_gerais['percentual_credenciados'], 1) ?>%</div>
                <div class="stat-label">Completude</div>
            </div>
        </div>

        <!-- Estatísticas por Tipo de Kit -->
        <?php if (count($stats_tipos) > 0): ?>
        <div class="card">
            <h3>📦 Estatísticas por Tipo de Kit</h3>
            <div class="tipos-stats">
                <?php foreach ($stats_tipos as $tipo_stat): ?>
                    <div class="tipo-stat">
                        <div class="tipo-numero"><?= $tipo_stat['quantidade'] ?></div>
                        <div class="tipo-label"><?= ucfirst($tipo_stat['tipo_kit']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card">
            <h2>🔍 Filtros para Relatório</h2>
            
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status">
                            <option value="todos" <?= $filtro_status === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="credenciados" <?= $filtro_status === 'credenciados' ? 'selected' : '' ?>>Apenas Credenciados</option>
                            <option value="pendentes" <?= $filtro_status === 'pendentes' ? 'selected' : '' ?>>Apenas Pendentes</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo_kit">Tipo de Kit:</label>
                        <select name="tipo_kit" id="tipo_kit">
                            <option value="">Todos os tipos</option>
                            <option value="participante" <?= $filtro_tipo_kit === 'participante' ? 'selected' : '' ?>>Participante</option>
                            <option value="palestrante" <?= $filtro_tipo_kit === 'palestrante' ? 'selected' : '' ?>>Palestrante</option>
                            <option value="organizador" <?= $filtro_tipo_kit === 'organizador' ? 'selected' : '' ?>>Organizador</option>
                            <option value="visitante" <?= $filtro_tipo_kit === 'visitante' ? 'selected' : '' ?>>Visitante</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="data">Data Credenciamento:</label>
                        <input type="date" name="data" id="data" value="<?= htmlspecialchars($filtro_data) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="operador">Operador:</label>
                        <select name="operador" id="operador">
                            <option value="">Todos os operadores</option>
                            <?php foreach ($operadores as $op): ?>
                                <option value="<?= $op['id'] ?>" <?= $filtro_operador == $op['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($op['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="nome">Buscar por Nome:</label>
                        <input type="text" name="nome" id="nome" placeholder="Digite o nome..." value="<?= htmlspecialchars($filtro_nome) ?>">
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <button type="submit" class="btn-excel btn-secondary">🔍 Aplicar Filtros</button>
                    <button type="submit" name="gerar_excel" value="1" class="btn-excel">📥 Gerar Excel</button>
                    <?php if (!empty($filtro_status) && $filtro_status !== 'todos' || !empty($filtro_tipo_kit) || !empty($filtro_data) || !empty($filtro_operador) || !empty($filtro_nome)): ?>
                        <a href="relatorio_credenciamento.php" class="btn-excel" style="background: #ffc107; color: #212529;">🔄 Limpar Filtros</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Preview dos Dados -->
        <?php if (count($preview_dados) > 0): ?>
            <div class="card">
                <div class="filter-summary">
                    <strong>📋 Preview dos Dados</strong> - Mostrando até 50 registros com os filtros aplicados
                </div>
                
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Função</th>
                            <th>Cidade/Estado</th>
                            <th>Credenciado</th>
                            <th>Data/Hora</th>
                            <th>Tipo Kit</th>
                            <th>Operador</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_dados as $dados): ?>
                            <tr>
                                <td><?= htmlspecialchars($dados['nome_usuario']) ?></td>
                                <td><?= htmlspecialchars($dados['funcao']) ?></td>
                                <td><?= htmlspecialchars($dados['cidade_usuario']) ?> - <?= htmlspecialchars($dados['estado_usuario']) ?></td>
                                <td class="credenciado-<?= $dados['credenciado'] === 'SIM' ? 'sim' : 'nao' ?>">
                                    <?= $dados['credenciado'] ?>
                                </td>
                                <td>
                                    <?= $dados['data_credenciamento'] ? date('d/m H:i', strtotime($dados['data_credenciamento'])) : '-' ?>
                                </td>
                                <td>
                                    <?php if ($dados['tipo_kit']): ?>
                                        <span class="kit-badge kit-<?= $dados['tipo_kit'] ?>">
                                            <?= ucfirst($dados['tipo_kit']) ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($dados['operador_nome']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!empty($filtro_status) && $filtro_status !== 'todos' || !empty($filtro_tipo_kit) || !empty($filtro_data) || !empty($filtro_operador) || !empty($filtro_nome)): ?>
            <div class="card">
                <div class="empty-state">
                    <h3>🔍 Nenhum resultado encontrado</h3>
                    <p>Tente ajustar os filtros para encontrar dados</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Relatórios Rápidos -->
        <div class="card">
            <h2>📋 Relatórios Rápidos</h2>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="?status=credenciados&gerar_excel=1" class="btn-excel">📅 Todos Credenciados</a>
                <a href="?status=pendentes&gerar_excel=1" class="btn-excel">⏳ Pendentes</a>
                <a href="?data=<?= date('Y-m-d') ?>&gerar_excel=1" class="btn-excel">📊 Credenciados Hoje</a>
                <a href="?gerar_excel=1" class="btn-excel">📈 Relatório Completo</a>
            </div>
        </div>
    </div>
</body>
</html>