<?php
// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'softgest_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configurações do sistema
define('SITE_NAME', 'SoftGest Web');
define('SITE_URL', 'http://localhost/softgest_web/');
define('TIMEZONE', 'America/Sao_Paulo');

// Conexão com o banco de dados
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Iniciar sessão
session_start();
?>
