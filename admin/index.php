<?php
require_once '../config/database.php';
require_once '../config/auth.php';

verificarPermissaoAdmin();

// Estatísticas básicas
$stats = [
    'participantes' => $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
    'cursos' => $pdo->query("SELECT COUNT(*) FROM cursos WHERE status = 'ativo'")->fetchColumn(),
    'registros_hoje' => $pdo->query("SELECT COUNT(*) FROM registros WHERE DATE(timestamp_registro) = CURDATE()")->fetchColumn(),
    'operadores' => $pdo->query("SELECT COUNT(*) FROM operadores WHERE ativo = 1")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - FEBIC Controle</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .admin-header { background: white; padding: 20px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 32px; font-weight: bold; color: #667eea; }
        .stat-label { color: #666; margin-top: 5px; }
        .admin-menu { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .menu-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s; }
        .menu-card:hover { transform: translateY(-5px); }
        .menu-card a { text-decoration: none; color: #333; }
        .menu-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .menu-desc { color: #666; font-size: 14px; }
        .btn-back { display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Área Administrativa</h1>
                    <p>Painel de controle - FEBIC</p>
                </div>
                <div>
                    <a href="../index.php" class="btn-back">← Voltar</a>
                    <a href="../logout.php" style="color: #dc3545; text-decoration: none; margin-left: 15px;">Sair</a>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['participantes']) ?></div>
                <div class="stat-label">Participantes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['cursos']) ?></div>
                <div class="stat-label">Cursos Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['registros_hoje']) ?></div>
                <div class="stat-label">Registros Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['operadores']) ?></div>
                <div class="stat-label">Operadores</div>
            </div>
        </div>

        <div class="admin-menu">
            <div class="menu-card">
                <a href="participantes.php">
                    <div class="menu-title">👥 Participantes</div>
                    <div class="menu-desc">Upload de planilha, gerar QR codes e gerenciar participantes</div>
                </a>
            </div>
            
            <div class="menu-card">
                <a href="cursos.php">
                    <div class="menu-title">📚 Cursos/Oficinas</div>
                    <div class="menu-desc">Cadastrar e gerenciar cursos e oficinas</div>
                </a>
            </div>
            
            <div class="menu-card">
                <a href="operadores.php">
                    <div class="menu-title">👤 Operadores</div>
                    <div class="menu-desc">Gerenciar usuários do sistema</div>
                </a>
            </div>
            
            <div class="menu-card">
                <a href="relatorios.php">
                    <div class="menu-title">📊 Relatórios</div>
                    <div class="menu-desc">Gerar relatórios de presença e participação</div>
                </a>
            </div>
        </div>
    </div>
</body>
</html>