<?php
require_once 'config/database.php';

echo "<h2>🔍 Debug - Licenças</h2>";
echo "<pre>";

try {
    // 1. Verificar se a tabela existe
    $stmt = $pdo->query("SELECT to_regclass('public.licencas') AS tabela");
    $row = $stmt->fetch();
    
    if (!$row['tabela']) {
        die("❌ Tabela 'licencas' NÃO existe no Neon!\n\n👉 Executa o SQL de criação primeiro.");
    }
    
    echo "✅ Tabela 'licencas' existe\n\n";
    
    // 2. Listar todos os registos
    $stmt = $pdo->query("SELECT * FROM licencas ORDER BY id");
    $licencas = $stmt->fetchAll();
    
    echo "📊 Total de licenças: " . count($licencas) . "\n\n";
    
    foreach ($licencas as $l) {
        echo "--- ID: {$l['id']} ---\n";
        echo "Cliente:        {$l['cliente']}\n";
        echo "Código:         {$l['codigo_licenca']}\n";
        echo "Tipo:           {$l['tipo']}\n";
        echo "Data ativação:  {$l['data_ativacao']}\n";
        echo "Data expiração: {$l['data_expiracao']}\n";
        echo "Status:         {$l['status']}\n";
        echo "Observações:    {$l['observacoes']}\n\n";
    }
    
    // 3. Verificar a data de hoje
    echo "📅 Data atual do servidor: " . date('Y-m-d H:i:s') . "\n";
    echo "📅 Timezone: " . date_default_timezone_get() . "\n\n";
    
    // 4. Testar a query que o license_check provavelmente usa
    echo "🔎 Testando query com status='ativa':\n";
    $stmt = $pdo->prepare("SELECT * FROM licencas WHERE status = 'ativa' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $licenca = $stmt->fetch();
    
    if ($licenca) {
        echo "✅ Encontrada licença ativa:\n";
        echo "   Código: {$licenca['codigo_licenca']}\n";
        echo "   Expira: {$licenca['data_expiracao']}\n";
        echo "   Status: {$licenca['status']}\n";
    } else {
        echo "❌ Nenhuma licença com status='ativa' encontrada!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";