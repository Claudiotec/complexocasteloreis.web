<?php
// ============================================
// diagnostico_professores.php - DIAGNÓSTICO AUTÔNOMO
// ============================================

// ============================================
// CONFIGURAÇÕES DIRETAS
// ============================================
$host = 'localhost';
$user = 'root';
$password = 'Claudtec';
$database = 'softgest_db';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>🔍 Diagnóstico de Professores</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a2332; border-bottom: 3px solid #c9a84c; padding-bottom: 10px; }
        h2 { color: #1a2332; margin-top: 25px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th { background: #1a2332; color: white; padding: 10px; text-align: left; }
        td { padding: 8px 10px; border: 1px solid #ddd; }
        .verde { background: #d1fae5; }
        .vermelho { background: #fee2e2; }
        .total { font-size: 28px; font-weight: bold; color: #2ecc71; }
        .erro { color: red; font-size: 18px; }
        .info { background: #dbeafe; padding: 10px; border-radius: 8px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 DIAGNÓSTICO DE PROFESSORES</h1>";
echo "<p>Data: " . date('d/m/Y H:i:s') . "</p>";
echo "<hr>";

try {
    // ============================================
    // 1. CONEXÃO DIRETA
    // ============================================
    echo "<h2>📡 1. TESTANDO CONEXÃO</h2>";
    
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo "<p style='color:green;font-size:18px;'>✅ Conexão com o banco <strong>$database</strong> estabelecida!</p>";
    echo "<hr>";
    
    // ============================================
    // 2. LISTAR TABELAS
    // ============================================
    echo "<h2>📊 2. TABELAS NO BANCO</h2>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>";
    $tem_funcionarios = false;
    $tem_forca_trabalho = false;
    $tem_professores = false;
    
    foreach ($tables as $table) {
        $found = '';
        if ($table == 'funcionarios') { $tem_funcionarios = true; $found = ' ✅ <strong>USAR ESTA!</strong>'; }
        if ($table == 'forca_trabalho') { $tem_forca_trabalho = true; $found = ' ⚠️ PODE SER ESTA'; }
        if ($table == 'professores') { $tem_professores = true; $found = ' ⚠️ PODE SER ESTA'; }
        echo "<li>" . $table . $found . "</li>";
    }
    echo "</ul>";
    echo "<hr>";
    
    // ============================================
    // 3. ANALISAR TABELA funcionarios
    // ============================================
    echo "<h2>👨‍🏫 3. ANALISANDO TABELA 'funcionarios'</h2>";
    
    if ($tem_funcionarios) {
        echo "<p style='color:green;'>✅ Tabela 'funcionarios' ENCONTRADA!</p>";
        
        // Verificar colunas
        $cols = $pdo->query("DESCRIBE funcionarios")->fetchAll();
        echo "<h3>📋 Colunas disponíveis:</h3>";
        echo "<ul>";
        foreach ($cols as $col) {
            echo "<li><strong>" . $col['Field'] . "</strong> - " . $col['Type'] . "</li>";
        }
        echo "</ul>";
        
        // ============================================
        // 4. MOSTRAR TODOS OS REGISTROS
        // ============================================
        echo "<h3>📋 TODOS OS REGISTROS</h3>";
        $todos = $pdo->query("SELECT id, nome, categoria_actual, cargo, funcao_instituicao, status FROM funcionarios")->fetchAll();
        
        echo "<table>";
        echo "<tr><th>ID</th><th>Nome</th><th>categoria_actual</th><th>cargo</th><th>funcao_instituicao</th><th>status</th><th>É Professor?</th><th>Origem</th></tr>";
        
        $total_professores = 0;
        foreach ($todos as $reg) {
            $cat = strtolower($reg['categoria_actual'] ?? '');
            $car = strtolower($reg['cargo'] ?? '');
            $fun = strtolower($reg['funcao_instituicao'] ?? '');
            
            $eh_cat = strpos($cat, 'professor') !== false;
            $eh_car = strpos($car, 'professor') !== false;
            $eh_fun = strpos($fun, 'professor') !== false;
            
            $eh_professor = $eh_cat || $eh_car || $eh_fun;
            
            if ($eh_professor) $total_professores++;
            
            $cor = $eh_professor ? 'style="background:#d1fae5;"' : '';
            $origem = '';
            if ($eh_cat) $origem = 'categoria_actual';
            elseif ($eh_car) $origem = 'cargo';
            elseif ($eh_fun) $origem = 'funcao_instituicao';
            
            echo "<tr $cor>";
            echo "<td>" . ($reg['id'] ?? '-') . "</td>";
            echo "<td><strong>" . ($reg['nome'] ?? '-') . "</strong></td>";
            echo "<td>" . ($reg['categoria_actual'] ?? 'NULL') . ($eh_cat ? ' ✅' : '') . "</td>";
            echo "<td>" . ($reg['cargo'] ?? 'NULL') . ($eh_car ? ' ✅' : '') . "</td>";
            echo "<td>" . ($reg['funcao_instituicao'] ?? 'NULL') . ($eh_fun ? ' ✅' : '') . "</td>";
            echo "<td>" . ($reg['status'] ?? 'NULL') . "</td>";
            echo "<td>" . ($eh_professor ? '✅ SIM' : '❌ NÃO') . "</td>";
            echo "<td>" . $origem . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h2 style='color:#2ecc71;font-size:32px;'>👥 TOTAL DE PROFESSORES ENCONTRADOS: " . $total_professores . "</h2>";
        
        // ============================================
        // 5. TESTE INDIVIDUAL ID 7
        // ============================================
        echo "<h2>🔍 4. REGISTRO ID 7 (Cláudio Raul)</h2>";
        $stmt = $pdo->query("SELECT * FROM funcionarios WHERE id = 7");
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($reg) {
            echo "<table>";
            foreach ($reg as $key => $value) {
                $cor = ($key == 'categoria_actual' || $key == 'cargo' || $key == 'funcao_instituicao') ? 'style="background:#d1fae5;"' : '';
                echo "<tr $cor><td><strong>" . $key . "</strong></td><td>" . ($value ?? 'NULL') . "</td></tr>";
            }
            echo "</table>";
            
            $cat = strtolower($reg['categoria_actual'] ?? '');
            $car = strtolower($reg['cargo'] ?? '');
            $fun = strtolower($reg['funcao_instituicao'] ?? '');
            
            echo "<div class='info'>";
            echo "<p><strong>categoria_actual</strong> contém 'professor'? " . (strpos($cat, 'professor') !== false ? '✅ SIM' : '❌ NÃO') . "</p>";
            echo "<p><strong>cargo</strong> contém 'professor'? " . (strpos($car, 'professor') !== false ? '✅ SIM' : '❌ NÃO') . "</p>";
            echo "<p><strong>funcao_instituicao</strong> contém 'professor'? " . (strpos($fun, 'professor') !== false ? '✅ SIM' : '❌ NÃO') . "</p>";
            
            $eh_prof = (strpos($cat, 'professor') !== false || strpos($car, 'professor') !== false || strpos($fun, 'professor') !== false);
            echo "<h3 style='color:" . ($eh_prof ? '#2ecc71' : '#e74c3c') . ";'>" . ($eh_prof ? '✅ É PROFESSOR!' : '❌ NÃO É PROFESSOR') . "</h3>";
            echo "</div>";
        } else {
            echo "<p style='color:red;'>❌ Registro ID 7 não encontrado!</p>";
        }
        
    } elseif ($tem_forca_trabalho) {
        echo "<p style='color:orange;'>⚠️ Tabela 'funcionarios' não existe, mas 'forca_trabalho' existe!</p>";
        
        // Mostrar estrutura
        $cols = $pdo->query("DESCRIBE forca_trabalho")->fetchAll();
        echo "<h3>📋 Colunas da tabela forca_trabalho:</h3>";
        echo "<ul>";
        foreach ($cols as $col) {
            echo "<li><strong>" . $col['Field'] . "</strong> - " . $col['Type'] . "</li>";
        }
        echo "</ul>";
        
        // Mostrar registros
        $dados = $pdo->query("SELECT * FROM forca_trabalho LIMIT 10")->fetchAll();
        echo "<h3>📋 Registros (até 10):</h3>";
        echo "<pre>";
        print_r($dados);
        echo "</pre>";
        
    } elseif ($tem_professores) {
        echo "<p style='color:orange;'>⚠️ Tabela 'professores' existe!</p>";
        $dados = $pdo->query("SELECT * FROM professores LIMIT 10")->fetchAll();
        echo "<pre>";
        print_r($dados);
        echo "</pre>";
        
    } else {
        echo "<p style='color:red;'>❌ NENHUMA TABELA DE FUNCIONÁRIOS ENCONTRADA!</p>";
        echo "<p>Tabelas disponíveis: " . implode(', ', $tables) . "</p>";
    }
    
    // ============================================
    // 6. SOLUÇÃO RECOMENDADA
    // ============================================
    echo "<hr>";
    echo "<h2>📌 SOLUÇÃO RECOMENDADA</h2>";
    
    if ($tem_funcionarios && isset($total_professores)) {
        echo "<div style='background:#d1fae5;padding:15px;border-radius:8px;border:2px solid #2ecc71;'>";
        echo "<p style='font-size:20px;font-weight:bold;'>✅ A contagem correta é: <span style='color:#2ecc71;font-size:32px;'>$total_professores</span> professor(es)</p>";
        echo "<p>👉 <strong>USE ESTA CONSULTA PARA CONTAR:</strong></p>";
        echo "<pre style='background:#1a2332;color:#f5d76e;padding:15px;border-radius:8px;overflow:auto;'>";
        echo "SELECT COUNT(*) as total \n";
        echo "FROM funcionarios \n";
        echo "WHERE categoria_actual LIKE '%Professor%' \n";
        echo "   OR categoria_actual LIKE '%professor%' \n";
        echo "   OR cargo LIKE '%Professor%' \n";
        echo "   OR cargo LIKE '%professor%' \n";
        echo "   OR funcao_instituicao LIKE '%Professor%' \n";
        echo "   OR funcao_instituicao LIKE '%professor%'";
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background:#fef3c7;padding:15px;border-radius:8px;border:2px solid #f39c12;'>";
        echo "<p style='font-weight:bold;'>⚠️ Não foi possível encontrar professores.</p>";
        echo "<p>Verifique:</p>";
        echo "<ul>";
        echo "<li>Se a tabela correta é 'funcionarios' ou 'forca_trabalho'</li>";
        echo "<li>Se os registros têm 'professor' nos campos corretos</li>";
        echo "</ul>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div style='background:#fee2e2;padding:15px;border-radius:8px;border:2px solid #e74c3c;'>";
    echo "<p style='color:red;font-size:18px;'>❌ ERRO DE BANCO DE DADOS</p>";
    echo "<p><strong>Mensagem:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Verifique:</strong></p>";
    echo "<ul>";
    echo "<li>O MySQL está rodando?</li>";
    echo "<li>O banco <strong>$database</strong> existe?</li>";
    echo "<li>A senha <strong>$password</strong> está correta?</li>";
    echo "</ul>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='background:#fee2e2;padding:15px;border-radius:8px;border:2px solid #e74c3c;'>";
    echo "<p style='color:red;font-size:18px;'>❌ ERRO: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</div></body></html>";
?>