<?php
// config/funcoes.php
// Funções auxiliares do sistema

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function conectarBanco() {
    $host = 'localhost';
    $user = 'root';
    $password = 'Claudtec';
    $database = 'softgest_db';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro de conexão: " . $e->getMessage());
    }
}

function temPermissao($modulo, $acao = 'visualizar') {
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }
    
    if (isset($_SESSION['usuario_perfil']) && $_SESSION['usuario_perfil'] === 'admin') {
        return true;
    }
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT $acao FROM permissoes WHERE usuario_id = ? AND modulo = ?");
        $stmt->execute([$_SESSION['usuario_id'], $modulo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (bool)$result[$acao] : false;
    } catch (Exception $e) {
        return false;
    }
}

function podeVisualizarEscola($modulo) {
    return temPermissao($modulo, 'visualizar');
}

function podeCriarEscola($modulo) {
    return temPermissao($modulo, 'criar');
}

function podeEditarEscola($modulo) {
    return temPermissao($modulo, 'editar');
}

function podeExcluirEscola($modulo) {
    return temPermissao($modulo, 'excluir');
}