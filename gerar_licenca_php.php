<?php
// gerar_licenca_php.php

// Dados da licença
$dados = [
    'tipo' => 'anual',
    'prefixo' => 'SG',
    'cliente_nome' => 'João Silva',
    'cliente_email' => 'joao@empresa.com',
    'cliente_empresa' => 'Tech Solutions',
    'max_usuarios' => 10,
    'modulos' => ['clientes', 'produtos', 'faturas', 'caixa']
];

// Se o servidor Python não estiver rodando, insere direto no banco
try {
    // Tentar conectar na API Python
    $ch = curl_init('http://localhost:5000/api/licenca/gerar');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $resultado = json_decode($response, true);
        echo "✅ Licença gerada via API Python!\n";
        echo "Código: " . $resultado['codigo'] . "\n";
        echo "Chave: " . $resultado['chave_ativacao'] . "\n";
        echo "Expiração: " . $resultado['data_expiracao'] . "\n";
    } else {
        throw new Exception('API não respondeu');
    }
    
} catch (Exception $e) {
    echo "⚠️ API Python não disponível. Gerando diretamente no banco...\n\n";
    
    // Gerar manualmente
    require_once 'config/database.php';
    
    $codigo = 'SG-' . date('Ym') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $chave = strtoupper(substr(md5($codigo . time()), 0, 16));
    $chave_formatada = substr($chave, 0, 4) . '-' . substr($chave, 4, 4) . '-' . substr($chave, 8, 4) . '-' . substr($chave, 12, 4);
    $expiracao = date('Y-m-d H:i:s', strtotime('+365 days'));
    
    // Inserir no banco
    $stmt = $pdo->prepare("
        INSERT INTO licencas (codigo_licenca, chave_ativacao, tipo, status, cliente_nome, cliente_email, cliente_empresa, data_expiracao, max_usuarios)
        VALUES (?, ?, ?, 'ativa', ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $codigo,
        $chave_formatada,
        'anual',
        'João Silva',
        'joao@empresa.com',
        'Tech Solutions',
        $expiracao,
        10
    ]);
    
    echo "✅ Licença gerada diretamente no banco de dados!\n";
    echo "📝 Código: " . $codigo . "\n";
    echo "🔑 Chave: " . $chave_formatada . "\n";
    echo "📅 Expiração: " . $expiracao . "\n";
}
?>