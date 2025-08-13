<?php
require_once '../config/database.php';
require_once '../config/auth.php';

verificarPermissaoAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Cadastrar/Editar operador
if ($_POST) {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if (empty($usuario) || empty($nome) || empty($tipo)) {
        $mensagem = 'Usuário, nome e tipo são obrigatórios.';
        $tipo_mensagem = 'error';
    } elseif ($id === 0 && empty($senha)) {
        $mensagem = 'Senha é obrigatória para novo operador.';
        $tipo_mensagem = 'error';
    } else {
        try {
            if ($id > 0) {
                // Editar
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE operadores SET usuario=?, senha=?, nome=?, tipo=? WHERE id=?");
                    $stmt->execute([$usuario, $senha_hash, $nome, $tipo, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE operadores SET usuario=?, nome=?, tipo=? WHERE id=?");
                    $stmt->execute([$usuario, $nome, $tipo, $id]);
                }
                $mensagem = 'Operador atualizado com sucesso!';
            } else {
                // Criar
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO operadores (usuario, senha, nome, tipo) VALUES (?, ?, ?, ?)");
                $stmt->execute([$usuario, $senha_hash, $nome, $tipo]);
                $mensagem = 'Operador cadastrado com sucesso!';
            }
            $tipo_mensagem = 'success';
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $mensagem = 'Este usuário já existe!';
            } else {
                $mensagem = 'Erro: ' . $e->getMessage();
            }
            $tipo_mensagem = 'error';
        }
    }
}

// Ativar/Desativar operador
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE operadores SET ativo = NOT ativo WHERE id = ? AND id != 1"); // Não pode desativar admin principal
    $stmt->execute([$id]);
    $mensagem = 'Status do operador alterado!';
    $tipo_mensagem = 'success';
}

// Buscar operador para edição
$operador_edit = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM operadores WHERE id = ?");
    $stmt->execute([$id]);
    $operador_edit = $stmt->fetch();
}

// Buscar todos os operadores
$stmt = $pdo->prepare("SELECT * FROM operadores ORDER BY nome");
$stmt->execute();
$operadores = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operadores - Admin FEBIC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container { max-width: 900px; margin: 20px auto; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .btn-sm { padding: 6px 12px; font-size: 12px; margin-right: 5px; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; }
        .status-ativo { color: #28a745; font-weight: bold; }
        .status-inativo { color: #dc3545; font-weight: bold; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .badge-admin { background: #007bff; color: white; }
        .badge-operador { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Gerenciar Operadores</h1>
            <a href="index.php" class="btn btn-secondary">← Voltar</a>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2><?= $operador_edit ? 'Editar Operador' : 'Cadastrar Novo Operador' ?></h2>
            
            <form method="POST">
                <?php if ($operador_edit): ?>
                    <input type="hidden" name="id" value="<?= $operador_edit['id'] ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="usuario">Usuário de Login:</label>
                        <input type="text" id="usuario" name="usuario" required 
                               value="<?= $operador_edit ? htmlspecialchars($operador_edit['usuario']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="nome">Nome Completo:</label>
                        <input type="text" id="nome" name="nome" required 
                               value="<?= $operador_edit ? htmlspecialchars($operador_edit['nome']) : '' ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="senha">Senha<?= $operador_edit ? ' (deixe vazio para manter atual)' : '' ?>:</label>
                        <input type="password" id="senha" name="senha" <?= $operador_edit ? '' : 'required' ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo">Tipo de Acesso:</label>
                        <select name="tipo" id="tipo" required>
                            <option value="">Selecione o tipo</option>
                            <option value="admin" <?= $operador_edit && $operador_edit['tipo'] == 'admin' ? 'selected' : '' ?>>
                                Administrador (acesso completo)
                            </option>
                            <option value="operador" <?= $operador_edit && $operador_edit['tipo'] == 'operador' ? 'selected' : '' ?>>
                                Operador (apenas leitura QR)
                            </option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <?= $operador_edit ? '✏️ Atualizar Operador' : '➕ Cadastrar Operador' ?>
                </button>
                
                <?php if ($operador_edit): ?>
                    <a href="operadores.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h2>Operadores Cadastrados</h2>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Cadastrado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operadores as $op): ?>
                        <tr>
                            <td><?= htmlspecialchars($op['nome']) ?></td>
                            <td><?= htmlspecialchars($op['usuario']) ?></td>
                            <td>
                                <span class="badge badge-<?= $op['tipo'] ?>"><?= ucfirst($op['tipo']) ?></span>
                            </td>
                            <td>
                                <span class="status-<?= $op['ativo'] ? 'ativo' : 'inativo' ?>">
                                    <?= $op['ativo'] ? '✅ Ativo' : '❌ Inativo' ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($op['criado_em'])) ?></td>
                            <td>
                                <a href="?editar=<?= $op['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                                <?php if ($op['id'] != 1): // Não pode desativar admin principal ?>
                                    <a href="?toggle=<?= $op['id'] ?>" class="btn <?= $op['ativo'] ? 'btn-danger' : 'btn-success' ?> btn-sm">
                                        <?= $op['ativo'] ? 'Desativar' : 'Ativar' ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>