<?php
require_once 'config/database.php';
require_once 'config/auth.php';

redirecionarSeNaoLogado();

// Buscar tipos de controle
$stmt = $pdo->prepare("SELECT * FROM tipos_controle ORDER BY nome");
$stmt->execute();
$tipos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FEBIC - Controle de Acesso</title>
    <link rel="stylesheet" href="assets/style.css">
    <!-- PWA Configuration -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="FEBIC Controle">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">

    <!-- PWA Script -->
    <script src="assets/pwa.js" defer></script>
    <style>
        .header {
            background: white;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-info {
            font-size: 14px;
            color: #666;
        }
        
        .tipos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .tipo-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }
        
        .tipo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .tipo-card.desenvolvimento {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .tipo-card.desenvolvimento:hover {
            transform: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .tipo-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .tipo-status {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .status-ativo {
            background: #d4edda;
            color: #155724;
        }
        
        .status-desenvolvimento {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 800px;">
        <div class="header">
            <div>
                <h1>FEBIC - Controle de Acesso</h1>
                <p>Selecione o tipo de controle</p>
            </div>
            <div class="user-info">
                <strong><?= htmlspecialchars($_SESSION['operador_nome']) ?></strong><br>
                <small><?= ucfirst($_SESSION['operador_tipo']) ?></small><br>
                <a href="logout.php" style="color: #666; font-size: 12px;">Sair</a>
                <?php if ($_SESSION['operador_tipo'] === 'admin'): ?>
                    | <a href="admin/" style="color: #666; font-size: 12px;">Admin</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="tipos-grid">
            <?php foreach ($tipos as $tipo): ?>
                <?php if ($tipo['status'] === 'ativo'): ?>
                    <a href="controle.php?tipo=<?= $tipo['id'] ?>" class="tipo-card">
                <?php else: ?>
                    <div class="tipo-card desenvolvimento">
                <?php endif; ?>
                    <div class="tipo-title"><?= htmlspecialchars($tipo['nome']) ?></div>
                    <div class="tipo-status status-<?= $tipo['status'] ?>">
                        <?= $tipo['status'] === 'ativo' ? 'Disponível' : 'Em Desenvolvimento' ?>
                    </div>
                <?php if ($tipo['status'] === 'ativo'): ?>
                    </a>
                <?php else: ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>