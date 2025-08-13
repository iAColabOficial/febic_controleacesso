<?php
require_once 'config/database.php';
require_once 'config/auth.php';

redirecionarSeNaoLogado();

$tipo_id = (int)($_GET['tipo'] ?? 0);

if ($tipo_id === 0) {
    header('Location: index.php');
    exit;
}

// Buscar o tipo selecionado
$stmt = $pdo->prepare("SELECT * FROM tipos_controle WHERE id = ? AND status = 'ativo'");
$stmt->execute([$tipo_id]);
$tipo = $stmt->fetch();

if (!$tipo) {
    header('Location: index.php');
    exit;
}

// Buscar cursos deste tipo
$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(DISTINCT r.codigo_qr) as total_participantes,
           COUNT(CASE WHEN r.tipo_registro = 'entrada' THEN 1 END) as total_entradas,
           COUNT(CASE WHEN r.tipo_registro = 'saida' THEN 1 END) as total_saidas
    FROM cursos c
    LEFT JOIN registros r ON c.id = r.curso_id
    WHERE c.tipo_controle_id = ? AND c.status = 'ativo'
    GROUP BY c.id
    ORDER BY c.data, c.horario_inicio, c.nome
");
$stmt->execute([$tipo_id]);
$cursos = $stmt->fetchAll();

$mensagem = '';
if (count($cursos) === 0) {
    $mensagem = 'Nenhum curso/oficina cadastrado para este tipo ainda.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tipo['nome']) ?> - FEBIC Controle</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .controle-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 25px;
            margin-bottom: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .cursos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .curso-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .curso-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .curso-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .curso-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .curso-info {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .curso-body {
            padding: 20px;
        }
        
        .curso-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        
        .curso-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-controle {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-entrada {
            background: #28a745;
            color: white;
        }
        
        .btn-entrada:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .btn-saida {
            background: #dc3545;
            color: white;
        }
        
        .btn-saida:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .cursos-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="controle-container">
        <div class="header">
            <div>
                <h1><?= htmlspecialchars($tipo['nome']) ?></h1>
                <p>Selecione o curso/oficina para controle de acesso</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary">← Voltar</a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3>Nenhum curso disponível</h3>
                <p><?= htmlspecialchars($mensagem) ?></p>
                <div style="margin-top: 20px;">
                    <?php if ($_SESSION['operador_tipo'] === 'admin'): ?>
                        <a href="admin/cursos.php" class="btn">➕ Cadastrar Cursos</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="cursos-grid">
                <?php foreach ($cursos as $curso): ?>
                    <div class="curso-card">
                        <div class="curso-header">
                            <div class="curso-title"><?= htmlspecialchars($curso['nome']) ?></div>
                            <div class="curso-info">
                                📅 <?= date('d/m/Y', strtotime($curso['data'])) ?>
                                <?php if ($curso['periodo']): ?>
                                    • <?= htmlspecialchars($curso['periodo']) ?>
                                <?php endif; ?>
                                <?php if ($curso['horario_inicio'] && $curso['horario_fim']): ?>
                                    <br>⏰ <?= date('H:i', strtotime($curso['horario_inicio'])) ?> - <?= date('H:i', strtotime($curso['horario_fim'])) ?>
                                <?php endif; ?>
                                <?php if ($curso['nome_docente']): ?>
                                    <br>👨‍🏫 <?= htmlspecialchars($curso['nome_docente']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="curso-body">
                            <div class="curso-stats">
                                <div class="stat">
                                    <div class="stat-number"><?= $curso['total_participantes'] ?></div>
                                    <div class="stat-label">Participantes</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-number"><?= $curso['total_entradas'] ?></div>
                                    <div class="stat-label">Entradas</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-number"><?= $curso['total_saidas'] ?></div>
                                    <div class="stat-label">Saídas</div>
                                </div>
                            </div>
                            
                            <div class="curso-actions">
                                <a href="leitura.php?curso=<?= $curso['id'] ?>&modo=entrada" class="btn-controle btn-entrada">
                                    🚪➡️ ENTRADA
                                </a>
                                <a href="leitura.php?curso=<?= $curso['id'] ?>&modo=saida" class="btn-controle btn-saida">
                                    ➡️🚪 SAÍDA
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>