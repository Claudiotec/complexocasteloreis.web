<?php
// ============================================
// sincronizar_mensalidades.php
// Script para sincronizar status das mensalidades
// ============================================

require_once '../../config/database.php';

echo "<h1>🔄 Sincronizando Status das Mensalidades</h1>";

try {
    // 1. Verificar estrutura das tabelas
    echo "<h2>📋 Verificando estrutura...</h2>";
    
    // Verificar colunas da tabela mensalidades
    $stmt = $pdo->query("SHOW COLUMNS FROM mensalidades");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>";
    foreach ($colunas as $coluna) {
        echo "<li>$coluna</li>";
    }
    echo "</ul>";
    
    // 2. Buscar todos os pagamentos
    echo "<h2>📊 Pagamentos registrados:</h2>";
    $stmt = $pdo->query("
        SELECT p.*, e.nome as emolumento_nome, a.nome as aluno_nome 
        FROM pagamentos p
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        LEFT JOIN alunos a ON p.aluno_id = a.id
        ORDER BY p.id DESC
        LIMIT 20
    ");
    $pagamentos = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Aluno</th><th>Valor</th><th>Mês</th><th>Status</th></tr>";
    foreach ($pagamentos as $p) {
        echo "<tr>";
        echo "<td>{$p['id']}</td>";
        echo "<td>{$p['aluno_nome']} (ID: {$p['aluno_id']})</td>";
        echo "<td>R$ " . number_format($p['valor'], 2, ',', '.') . "</td>";
        echo "<td>{$p['mes_referencia']}</td>";
        echo "<td style='color: " . ($p['status'] == 'Pago' ? 'green' : 'red') . ";'>{$p['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 3. Buscar todas as mensalidades
    echo "<h2>📋 Mensalidades cadastradas:</h2>";
    $stmt = $pdo->query("
        SELECT m.*, a.nome as aluno_nome 
        FROM mensalidades m
        LEFT JOIN alunos a ON m.aluno_id = a.id
        ORDER BY m.id
        LIMIT 20
    ");
    $mensalidades = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Aluno</th><th>Mês/Ano</th><th>Valor</th><th>Status</th></tr>";
    foreach ($mensalidades as $m) {
        echo "<tr>";
        echo "<td>{$m['id']}</td>";
        echo "<td>{$m['aluno_nome']} (ID: {$m['aluno_id']})</td>";
        echo "<td>{$m['mes']}/{$m['ano']}</td>";
        echo "<td>R$ " . number_format($m['valor'], 2, ',', '.') . "</td>";
        echo "<td style='color: " . ($m['status'] == 'Pago' ? 'green' : 'red') . "; font-weight:bold;'>{$m['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 4. Sincronizar: Atualizar mensalidades com base nos pagamentos
    echo "<h2>🔄 Sincronizando...</h2>";
    
    $mapa_meses = array(
        'Janeiro' => 1, 'Fevereiro' => 2, 'Março' => 3, 'Abril' => 4,
        'Maio' => 5, 'Junho' => 6, 'Julho' => 7, 'Agosto' => 8,
        'Setembro' => 9, 'Outubro' => 10, 'Novembro' => 11, 'Dezembro' => 12
    );
    
    $atualizados = 0;
    
    // Buscar todos os pagamentos com status Pago
    $stmt = $pdo->query("
        SELECT p.*, e.nome as emolumento_nome 
        FROM pagamentos p
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        WHERE p.status = 'Pago' AND p.mes_referencia != '-'
    ");
    $pagamentos_pagos = $stmt->fetchAll();
    
    echo "<p>Encontrados " . count($pagamentos_pagos) . " pagamentos com status Pago</p>";
    
    foreach ($pagamentos_pagos as $pag) {
        $mes_ref = $pag['mes_referencia'];
        $aluno_id = $pag['aluno_id'];
        
        // Extrair mês e ano
        $mes_ano = explode('/', $mes_ref);
        if (count($mes_ano) == 2) {
            $mes = trim($mes_ano[0]);
            $ano = intval(trim($mes_ano[1]));
        } else {
            $mes_ano = explode(' ', $mes_ref);
            if (count($mes_ano) >= 2) {
                $mes = trim($mes_ano[0]);
                $ano = intval(trim($mes_ano[1]));
            } else {
                continue;
            }
        }
        
        // Verificar se existe mensalidade para este aluno/mês/ano
        $stmt = $pdo->prepare("SELECT id, status FROM mensalidades WHERE aluno_id = ? AND mes = ? AND ano = ?");
        $stmt->execute([$aluno_id, $mes, $ano]);
        $mensalidade = $stmt->fetch();
        
        if ($mensalidade) {
            if ($mensalidade['status'] != 'Pago') {
                $stmt = $pdo->prepare("UPDATE mensalidades SET status = 'Pago', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$mensalidade['id']]);
                $atualizados++;
                echo "<p style='color:green;'>✅ Atualizado: Aluno ID $aluno_id - $mes/$ano</p>";
            }
        } else {
            // Criar mensalidade se não existir
            $stmt = $pdo->prepare("
                INSERT INTO mensalidades (aluno_id, mes, ano, valor, status) 
                VALUES (?, ?, ?, ?, 'Pago')
            ");
            $stmt->execute([$aluno_id, $mes, $ano, $pag['valor']]);
            echo "<p style='color:blue;'>📝 Criado: Aluno ID $aluno_id - $mes/$ano</p>";
        }
    }
    
    echo "<h2>✅ Sincronização concluída!</h2>";
    echo "<p>Total de mensalidades atualizadas: <strong>$atualizados</strong></p>";
    
    // 5. Mostrar resultado final
    echo "<h2>📊 Resultado final:</h2>";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pago' THEN 1 ELSE 0 END) as pagas,
            SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes
        FROM mensalidades
    ");
    $stats = $stmt->fetch();
    
    echo "<ul>";
    echo "<li>Total: {$stats['total']}</li>";
    echo "<li style='color:green;'>Pagas: {$stats['pagas']}</li>";
    echo "<li style='color:red;'>Pendentes: {$stats['pendentes']}</li>";
    echo "</ul>";
    
    // 6. Verificar especificamente Mariana Costa
    echo "<h2>🔍 Verificando Mariana Costa (ID: 3)</h2>";
    $stmt = $pdo->query("
        SELECT m.*, a.nome as aluno_nome 
        FROM mensalidades m
        LEFT JOIN alunos a ON m.aluno_id = a.id
        WHERE a.id = 3
        ORDER BY m.ano, FIELD(mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro')
    ");
    $mensalidades_mariana = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Aluno</th><th>Mês/Ano</th><th>Valor</th><th>Status</th></tr>";
    foreach ($mensalidades_mariana as $m) {
        echo "<tr>";
        echo "<td>{$m['id']}</td>";
        echo "<td>{$m['aluno_nome']}</td>";
        echo "<td>{$m['mes']}/{$m['ano']}</td>";
        echo "<td>R$ " . number_format($m['valor'], 2, ',', '.') . "</td>";
        echo "<td style='color: " . ($m['status'] == 'Pago' ? 'green' : 'red') . "; font-weight:bold;'>{$m['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Botão para voltar
    echo "<br><a href='index.php' style='display:inline-block;padding:10px 20px;background:#c9a84c;color:#1a2332;text-decoration:none;border-radius:8px;font-weight:bold;'>← Voltar para Mensalidades</a>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Erro: " . $e->getMessage() . "</h2>";
}
?>