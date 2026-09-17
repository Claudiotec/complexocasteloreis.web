<?php
// config/database.php

// Configurações do banco de dados
$host = 'localhost';
$user = 'root';
$password = 'Claudtec';
$database = 'softgest_db';

// Estabelecer conexão (mysqli - para compatibilidade)
$conn = mysqli_connect($host, $user, $password, $database);

// Verificar conexão
if (!$conn) {
    die('Erro ao conectar ao banco de dados: ' . mysqli_connect_error());
}

// Definir charset para UTF-8
if (!mysqli_set_charset($conn, "utf8mb4")) {
    die('Erro ao definir charset: ' . mysqli_error($conn));
}

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// ============================================
// FUNÇÃO PDO PARA COMPATIBILIDADE
// ============================================
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

// ============================================
// FUNÇÃO PARA VERIFICAR PERMISSÕES
// ============================================
function temPermissao($modulo, $acao = 'visualizar') {
    // Verifica se está logado
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }
    
    // Admin tem todas as permissões
    if (isset($_SESSION['usuario_perfil']) && $_SESSION['usuario_perfil'] === 'admin') {
        return true;
    }
    
    try {
        $pdo = conectarBanco();
        
        // Verifica se a tabela permissoes existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'permissoes'");
        if ($stmt->rowCount() == 0) {
            // Se a tabela não existe, só admin tem acesso
            return false;
        }
        
        // Busca permissão
        $stmt = $pdo->prepare("SELECT $acao FROM permissoes WHERE usuario_id = ? AND modulo = ?");
        $stmt->execute([$_SESSION['usuario_id'], $modulo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (bool)$result[$acao] : false;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// FUNÇÃO PARA VERIFICAR PERMISSÃO DA ESCOLA
// ============================================
function verificarMultiplasPermissoesEscola($modulo, $permissoes = ['visualizar', 'criar', 'editar', 'excluir']) {
    $resultado = [];
    foreach ($permissoes as $acao) {
        $resultado[$acao] = temPermissao($modulo, $acao);
    }
    return $resultado;
}
?>