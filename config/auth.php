<?php
session_start();

function verificarLogin() {
    return isset($_SESSION['operador_id']) && isset($_SESSION['operador_tipo']);
}

function redirecionarSeNaoLogado() {
    if (!verificarLogin()) {
        header('Location: login.php');
        exit;
    }
}

function verificarPermissaoAdmin() {
    if (!verificarLogin() || $_SESSION['operador_tipo'] !== 'admin') {
        header('Location: index.php');
        exit;
    }
}

function fazerLogin($usuario, $senha, $pdo) {
    $stmt = $pdo->prepare("SELECT id, usuario, senha, nome, tipo, ativo FROM operadores WHERE usuario = ? AND ativo = 1");
    $stmt->execute([$usuario]);
    $operador = $stmt->fetch();
    
    if ($operador && password_verify($senha, $operador['senha'])) {
        $_SESSION['operador_id'] = $operador['id'];
        $_SESSION['operador_usuario'] = $operador['usuario'];
        $_SESSION['operador_nome'] = $operador['nome'];
        $_SESSION['operador_tipo'] = $operador['tipo'];
        return true;
    }
    return false;
}

function fazerLogout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>