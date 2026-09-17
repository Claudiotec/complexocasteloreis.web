<?php
// admin/teste_sync.php
// TESTE SIMPLES - SEM DEPENDÊNCIAS

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste - Sincronização de Banco</h1>";
echo "<p>PHP está funcionando!</p>";

// Testar conexão local
echo "<h2>Testando Conexão Local (MySQL)</h2>";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=softgest_db;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p style='color:green;'>✅ Conexão Local OK!</p>";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tabelas = $stmt->fetchAll();
    echo "<p>Tabelas encontradas: " . count($tabelas) . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erro Local: " . $e->getMessage() . "</p>";
}

// Testar conexão pública
echo "<h2>Testando Conexão Pública (PostgreSQL - Neon)</h2>";
try {
    $dsn = "pgsql:host=ep-holy-resonance-atm1ick2-pooler.c-9.us-east-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
    $pdo = new PDO($dsn, "neondb_owner", "npg_gRkKHXNJAI40", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 30
    ]);
    echo "<p style='color:green;'>✅ Conexão Pública OK!</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erro Público: " . $e->getMessage() . "</p>";
}

echo "<h2>Fim do teste</h2>";
?>