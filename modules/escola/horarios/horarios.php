<?php
// ============================================
// CONFIGURAÇÃO DO BANCO DE DADOS
// ============================================

// Configurações do banco
$host = 'localhost';
$dbname = 'softgest_db'; // ALTERE PARA O NOME DO SEU BANCO
$username = 'root';
$password = '';

// ============================================
// CONEXÃO PDO
// ============================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Erro de conexão com o banco de dados: " . $e->getMessage());
}

// ============================================
// CONEXÃO MYSQLI (para compatibilidade)
// ============================================
$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("❌ Erro de conexão MySQLi: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

?>