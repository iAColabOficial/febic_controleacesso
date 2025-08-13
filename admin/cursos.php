<?php
require_once '../config/database.php';
require_once '../config/auth.php';

verificarPermissaoAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Cadastrar/Editar curso
if ($_POST) {
    $nome = trim($_POST['nome'] ?? '');
    $data = $_POST['data'] ?? '';
    $periodo = trim($_POST['periodo'] ?? '');
    $horario_inicio = $_POST['horario_inicio'] ?? '';
    $horario_fim = $_POST['horario_fim'] ?? '';
    $limite_participantes = (int)($_POST['limite_participantes'] ?? 0);
    $nome_docente = trim($_POST['nome_docente'] ?? '');
    $tipo_controle_id = (int)($_POST['tipo_controle_id'] ?? 0);
    $id = (int)($_POST['id'] ?? 0);
    
    if (empty($nome) || empty($data) || $tipo_controle_id === 0) {
        $mensagem = 'Nome, data e tipo são obrigatórios.';
        $tipo_mensagem = 'error';
    } else {
        try {
            if ($id > 0) {
                // Editar
                $stmt = $pdo->prepare("
                    UPDATE cursos SET nome=?, data=?, periodo=?, horario_inicio=?, 
                    horario_fim=?, limite_participantes=?, nome_docente=?, tipo_controle_id=?
                    WHERE id=?
                ");
                $stmt->execute([$nome, $data, $periodo, $horario_inicio, $horario_fim, 
                              $limite_participantes, $nome_docente, $tipo_controle_id, $id]);
                $mensagem = 'Curso atualizado com sucesso!';
            } else {
                // Criar
                $stmt = $pdo->prepare("
                    INSERT INTO cursos (nome, data, periodo, horario_inicio, horario_fim, 
                                      limite_participantes, nome_docente, tipo_controle_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $data, $periodo, $horario_inicio, $horario_fim, 
                              $limite_participantes, $nome_docente, $tipo_controle_id]);
                $mensagem = 'Curso cadastrado com sucesso!';
            }
            $tipo_mensagem = 'success';
        } catch (Exception $e) {
            $mensagem = 'Erro: ' . $e->getMessage();
            $tipo_mensagem = 'error';
        }
    }
}

// Excluir curso
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    try {
        $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = ?");
        $stmt->execute([$id]);
        $mensagem = 'Curso excluído com sucesso!';
        $tipo_mensagem = 'success';
    } catch (Exception $e) {
        $mensagem = 'Erro ao excluir: ' . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// Buscar curso para edição
$curso_edit = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmt->execute([$id]);
    $curso_edit = $stmt->fetch();
}

// Buscar tipos de controle ativos
$stmt = $pdo->prepare("SELECT * FROM tipos_controle WHERE status = 'ativo' ORDER BY nome");
$stmt->execute();
$tipos_controle = $stmt->fetchAll();

// Buscar cursos
$stmt = $pdo->prepare("
    SELECT c.*, tc.nome as tipo_nome 
    FROM cursos c 
    JOIN tipos_controle tc ON c.tipo_controle_id = tc.id 
    ORDER BY c.data DESC, c.nome
");
$stmt->execute();
$cursos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos - Admin FEBIC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container { max-width: 1000px; margin: 20px auto; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .btn-sm { padding: 6px 12px; font-size: 12px; margin-right: 5px; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Gerenciar Cursos/Oficinas</h1>
            <a href="index.php" class="btn btn-secondary">← Voltar</a>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2><?= $curso_edit ? 'Editar Curso' : 'Cadastrar Novo Curso' ?></h2>
            
            <form method="POST">
                <?php if ($curso_edit): ?>
                    <input type="hidden" name="id" value="<?= $curso_edit['id'] ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo_controle_id">Tipo:</label>
                        <select name="tipo_controle_id" id="tipo_controle_id" required>
                            <option value="">Selecione o tipo</option>
                            <?php foreach ($tipos_controle as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>" <?= $curso_edit && $curso_edit['tipo_controle_id'] == $tipo['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="nome">Nome do Curso/Oficina:</label>
                        <input type="text" id="nome" name="nome" required 
                               value="<?= $curso_edit ? htmlspecialchars($curso_edit['nome']) : '' ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="data">Data:</label>
                        <input type="date" id="data" name="data" required 
                               value="<?= $curso_edit ? $curso_edit['data'] : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="periodo">Período:</label>
                        <input type="text" id="periodo" name="periodo" placeholder="Ex: Manhã, Tarde, Noite" 
                               value="<?= $curso_edit ? htmlspecialchars($curso_edit['periodo']) : '' ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="horario_inicio">Horário Início:</label>
                        <input type="time" id="horario_inicio" name="horario_inicio" 
                               value="<?= $curso_edit ? $curso_edit['horario_inicio'] : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="horario_fim">Horário Fim:</label>
                        <input type="time" id="horario_fim" name="horario_fim" 
                               value="<?= $curso_edit ? $curso_edit['horario_fim'] : '' ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="limite_participantes">Limite de Participantes:</label>
                        <input type="number" id="limite_participantes" name="limite_participantes" min="0" placeholder="0 = Ilimitado"
                               value="<?= $curso_edit ? $curso_edit['limite_participantes'] : '0' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="nome_docente">Nome do Docente:</label>
                        <input type="text" id="nome_docente" name="nome_docente" 
                               value="<?= $curso_edit ? htmlspecialchars($curso_edit['nome_docente']) : '' ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <?= $curso_edit ? '✏️ Atualizar Curso' : '➕ Cadastrar Curso' ?>
                </button>
                
                <?php if ($curso_edit): ?>
                    <a href="cursos.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h2>Cursos Cadastrados</h2>
            
            <?php if (count($cursos) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Nome</th>
                            <th>Data</th>
                            <th>Período</th>
                            <th>Horário</th>
                            <th>Docente</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos as $curso): ?>
                            <tr>
                                <td><?= htmlspecialchars($curso['tipo_nome']) ?></td>
                                <td><?= htmlspecialchars($curso['nome']) ?></td>
                                <td><?= date('d/m/Y', strtotime($curso['data'])) ?></td>
                                <td><?= htmlspecialchars($curso['periodo']) ?></td>
                                <td>
                                    <?php if ($curso['horario_inicio'] && $curso['horario_fim']): ?>
                                        <?= date('H:i', strtotime($curso['horario_inicio'])) ?> - 
                                        <?= date('H:i', strtotime($curso['horario_fim'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($curso['nome_docente']) ?></td>
                                <td>
                                    <a href="?editar=<?= $curso['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <a href="?excluir=<?= $curso['id'] ?>" class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Deseja excluir este curso?')">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nenhum curso cadastrado ainda.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>