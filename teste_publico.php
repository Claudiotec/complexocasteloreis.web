<?php
// teste_publico.php
// Teste de conexão com o modo público

echo "========================================\n";
echo "🔍 TESTE DE CONEXÃO - MODO PÚBLICO\n";
echo "========================================\n\n";

// Forçar modo público
$_SESSION['app_mode'] = 'public';
$_GET['modo'] = 'public';

require_once 'config/app_modes.php';

echo "📌 Modo Atual: " . APP_MODE_NAME . "\n";
echo "📌 Tipo: " . DB_TYPE . "\n";
echo "📌 Host: " . DB_HOST . "\n";
echo "📌 Banco: " . DB_NAME . "\n";
echo "📌 Usuário: " . DB_USER . "\n\n";

try {
    $pdo = conectarBanco();
    echo "✅ Conexão estabelecida com sucesso!\n";
    
    // Testar query
    $stmt = $pdo->query("SELECT version() as versao");
    $result = $stmt->fetch();
    echo "✅ Versão: " . $result['versao'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>