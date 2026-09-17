<?php
// config/db_config.php
// ============================================================
// CONFIGURAÇÕES DO BANCO DE DADOS - VERSÃO MELHORADA
// ============================================================

// ===== CARREGAR VARIÁVEIS DE AMBIENTE =====
if (file_exists(__DIR__ . '/../.env')) {
    $envFile = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envFile as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// ===== DEFINIR CONSTANTES =====
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'softgest_db');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: 'Claudtec');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
}

// ===== CONFIGURAÇÕES DO SISTEMA =====
if (!defined('SITE_NAME')) define('SITE_NAME', 'SoftGest Web');
if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/softgest_web/');
if (!defined('TIMEZONE')) define('TIMEZONE', 'America/Sao_Paulo');

// ===== DEFINIR TIMEZONE =====
date_default_timezone_set(TIMEZONE);

// ===== FUNÇÃO DE CONEXÃO MELHORADA =====
function conectarBanco() {
    $host = DB_HOST;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
    $port = DB_PORT;
    
    try {
        // Testa diferentes combinações
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Log detalhado
        error_log("Erro de conexão: " . $e->getMessage());
        error_log("Host: $host, DB: $dbname, User: $user, Port: $port");
        
        // Tenta conectar sem banco específico para criar
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass);
            // Cria o banco se não existir
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");
            return $pdo;
        } catch (PDOException $e2) {
            throw new Exception("Erro na conexão com o banco de dados: " . $e2->getMessage());
        }
    }
}

// ===== FUNÇÃO DE DIAGNÓSTICO =====
function diagnosticarConexao() {
    $resultado = [
        'status' => 'error',
        'mensagem' => '',
        'detalhes' => []
    ];
    
    // Verificar extensão PDO
    if (!extension_loaded('pdo_mysql')) {
        $resultado['detalhes'][] = 'Extensão PDO MySQL não está carregada';
        return $resultado;
    }
    
    try {
        $pdo = conectarBanco();
        $resultado['status'] = 'success';
        $resultado['mensagem'] = 'Conectado com sucesso!';
        $resultado['detalhes'][] = 'Banco: ' . DB_NAME;
        $resultado['detalhes'][] = 'Versão MySQL: ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        return $resultado;
    } catch (Exception $e) {
        $resultado['mensagem'] = $e->getMessage();
        return $resultado;
    }
}

// ===== REALIZAR CONEXÃO =====
try {
    $pdo = conectarBanco();
} catch (Exception $e) {
    // Se for erro 1045 (acesso negado), tenta sem senha
    if (strpos($e->getMessage(), '1045') !== false) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                '',  // Tenta sem senha
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e2) {
            die("❌ Erro: " . $e2->getMessage());
        }
    } else {
        die("❌ Erro: " . $e->getMessage());
    }
}