<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$erro = '';

if ($_POST) {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    if (empty($usuario) || empty($senha)) {
        $erro = 'Usuário e senha são obrigatórios.';
    } else {
        if (fazerLogin($usuario, $senha, $pdo)) {
            header('Location: index.php');
            exit;
        } else {
            $erro = 'Usuário ou senha inválidos.';
        }
    }
}

// Se já está logado, redireciona
if (verificarLogin()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FEBIC Controle de Acesso</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>FEBIC</h1>
            <p>Controle de Acesso</p>
        </div>
        
        <?php if ($erro): ?>
            <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="usuario">Usuário:</label>
                <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            
            <button type="submit" class="btn">Entrar</button>
        </form>
    </div>
</body>
</html>