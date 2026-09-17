<?php
// ============================================================
// SCRIPT PARA LIMPAR TODOS OS DADOS DO RENDER DB
// ATENÇÃO: ESTA OPERAÇÃO É IRREVERSÍVEL!
// ============================================================

// Configuração do Render DB
$render_host = 'dpg-da5bufrm8hqs73c5dadg-a.virginia-postgres.render.com';
$render_port = '5432';
$render_db = 'softgest_web';
$render_user = 'softgest_web_user';
$render_pass = '0gN9IscY8pBk5EYSH7LeqXQy9f6WwMen';

// ============================================================
// FUNÇÃO PARA LISTAR TABELAS
// ============================================================
function listarTabelas($pdo) {
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================================
// FUNÇÃO PARA CONTAR REGISTROS
// ============================================================
function contarRegistros($pdo, $tabela) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM \"$tabela\"");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

echo "<!DOCTYPE html>
<html lang='pt'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Limpar Dados - Render DB</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; border-bottom: 3px solid #d32f2f; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 20px; }
        .warning { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .danger { background: #f8d7da; border: 2px solid #dc3545; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .success { background: #d4edda; border: 2px solid #28a745; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .error { background: #f8d7da; border: 2px solid #dc3545; padding: 15px; border-radius: 8px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #333; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
        .btn { 
            display: inline-block; 
            padding: 12px 30px; 
            border: none; 
            border-radius: 5px; 
            font-size: 16px; 
            font-weight: bold; 
            cursor: pointer; 
            text-decoration: none;
            margin: 5px;
        }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0069d9; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .checkbox-label { display: block; margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px; cursor: pointer; }
        .checkbox-label:hover { background: #e9ecef; }
        .checkbox-label input[type='checkbox'] { margin-right: 10px; transform: scale(1.2); }
        .progress-bar { 
            width: 100%; 
            height: 30px; 
            background: #e9ecef; 
            border-radius: 15px; 
            overflow: hidden; 
            margin: 10px 0; 
            display: none;
        }
        .progress-fill { 
            height: 100%; 
            background: linear-gradient(90deg, #28a745, #20c997); 
            width: 0%; 
            transition: width 0.5s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .hidden { display: none; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 14px; overflow-x: auto; }
        .tabela-nome { font-weight: bold; color: #007bff; }
        .registros { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>
    <h1>⚠️ LIMPAR TODOS OS DADOS</h1>
    <div class='danger'>
        <h2>🔴 ATENÇÃO: ESTA OPERAÇÃO É IRREVERSÍVEL!</h2>
        <p><strong>Isso vai apagar TODOS os dados de TODAS as tabelas do banco de dados.</strong></p>
        <p>Não é possível desfazer esta ação após ser executada.</p>
    </div>";

try {
    // Conecta ao Render DB
    echo "<p>🔄 Conectando ao Render DB...</p>";
    $dsn = "pgsql:host=$render_host;port=$render_port;dbname=$render_db;sslmode=require;connect_timeout=30";
    $pdo = new PDO($dsn, $render_user, $render_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 60
    ]);
    echo "<div class='success'>✅ Conectado ao Render DB com sucesso!</div>";

    // ============================================================
    // LISTAR TABELAS E REGISTROS
    // ============================================================
    $tabelas = listarTabelas($pdo);
    $total_registros = 0;
    $dados_tabelas = [];

    foreach ($tabelas as $tabela) {
        $count = contarRegistros($pdo, $tabela);
        $total_registros += $count;
        $dados_tabelas[] = ['nome' => $tabela, 'registros' => $count];
    }

    // ============================================================
    // EXIBIR RESUMO
    // ============================================================
    echo "<h2>📊 Resumo do Banco de Dados</h2>";
    echo "<table>";
    echo "<tr><th>#</th><th>Nome da Tabela</th><th>Registros</th></tr>";
    $i = 1;
    foreach ($dados_tabelas as $tabela) {
        $cor = $tabela['registros'] > 0 ? '#28a745' : '#6c757d';
        echo "<tr>";
        echo "<td>{$i}</td>";
        echo "<td class='tabela-nome'>{$tabela['nome']}</td>";
        echo "<td class='registros' style='color:{$cor}'>" . number_format($tabela['registros'], 0, ',', '.') . "</td>";
        echo "</tr>";
        $i++;
    }
    echo "<tr style='font-weight: bold; background: #e9ecef;'>";
    echo "<td colspan='2' style='text-align: right;'>TOTAL:</td>";
    echo "<td class='registros' style='color: #dc3545;'>" . number_format($total_registros, 0, ',', '.') . "</td>";
    echo "</tr>";
    echo "</table>";

    // ============================================================
    // FORMULÁRIO DE CONFIRMAÇÃO
    // ============================================================
    if ($total_registros == 0) {
        echo "<div class='success'>✅ O banco de dados já está vazio! Nenhuma ação necessária.</div>";
        echo "<p><a href='/' class='btn btn-primary'>Voltar ao Sistema</a></p>";
    } else {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Você está prestes a apagar <strong>" . number_format($total_registros, 0, ',', '.') . "</strong> registros de <strong>" . count($tabelas) . "</strong> tabelas.</h3>";
        echo "</div>";

        echo "<form method='POST' action='' onsubmit='return confirmarExclusao()'>";
        echo "<input type='hidden' name='confirmar' value='1'>";
        
        echo "<div class='danger'>";
        echo "<h3>🔴 Confirmação Necessária</h3>";
        echo "<label class='checkbox-label'>";
        echo "<input type='checkbox' id='confirm1' required> ";
        echo "<strong>Entendo que esta ação é IRREVERSÍVEL e todos os dados serão permanentemente apagados.</strong>";
        echo "</label>";
        echo "<label class='checkbox-label'>";
        echo "<input type='checkbox' id='confirm2' required> ";
        echo "<strong>Confirmo que tenho um backup dos dados ou que estou ciente das consequências.</strong>";
        echo "</label>";
        echo "<label class='checkbox-label'>";
        echo "<input type='checkbox' id='confirm3' required> ";
        echo "<strong>Desejo prosseguir com a limpeza total do banco de dados.</strong>";
        echo "</label>";
        echo "</div>";

        echo "<div style='margin-top: 20px;'>";
        echo "<button type='submit' class='btn btn-danger' id='btnExcluir' disabled>🗑️ APAGAR TODOS OS DADOS</button>";
        echo "<a href='/' class='btn btn-secondary'>Cancelar</a>";
        echo "</div>";
        echo "</form>";

        echo "<div class='progress-bar' id='progressBar'>";
        echo "<div class='progress-fill' id='progressFill'>0%</div>";
        echo "</div>";
        echo "<div id='statusMsg' style='margin-top: 10px;'></div>";
        echo "<div id='logMsg' style='margin-top: 10px; max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 13px; display: none;'></div>";
    }

} catch (PDOException $e) {
    echo "<div class='error'>❌ ERRO: " . $e->getMessage() . "</div>";
    echo "<p>Verifique se o banco de dados está acessível e as credenciais estão corretas.</p>";
}

// ============================================================
// PROCESSAR A EXCLUSÃO
// ============================================================
if (isset($_POST['confirmar']) && $_POST['confirmar'] == '1') {
    echo "<div id='processando'>";
    
    try {
        // Desativa verificações de chave estrangeira temporariamente
        $pdo->exec("SET session_replication_role = replica;");
        
        // Lista todas as tabelas
        $tabelas = listarTabelas($pdo);
        $total_deletados = 0;
        
        echo "<div class='warning'><h2>🔄 Processando exclusão...</h2></div>";
        echo "<div class='progress-bar' id='progressBar2' style='display:block;'>";
        echo "<div class='progress-fill' id='progressFill2' style='width: 0%;'>0%</div>";
        echo "</div>";
        echo "<div id='log2' style='margin-top: 10px; max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 13px;'></div>";
        
        // Força o flush do buffer
        ob_flush();
        flush();
        
        $i = 0;
        $total_tabelas = count($tabelas);
        
        foreach ($tabelas as $tabela) {
            $i++;
            $count = contarRegistros($pdo, $tabela);
            
            if ($count > 0) {
                echo "<div id='log_$i'>🗑️ Apagando tabela: <strong>$tabela</strong> ($count registros)... </div>";
                ob_flush();
                flush();
                
                $pdo->exec("TRUNCATE TABLE \"$tabela\" RESTART IDENTITY CASCADE");
                $total_deletados += $count;
                
                echo "<div id='log_$i' style='color:green;'>✅ Tabela <strong>$tabela</strong> limpa! ($count registros removidos)</div>";
            } else {
                echo "<div id='log_$i'>⏭️ Tabela <strong>$tabela</strong> já está vazia (0 registros)</div>";
            }
            
            // Atualiza progresso
            $percent = round(($i / $total_tabelas) * 100);
            echo "<script>document.getElementById('progressFill2').style.width = '{$percent}%'; document.getElementById('progressFill2').textContent = '{$percent}%';</script>";
            ob_flush();
            flush();
        }
        
        // Reativa verificações
        $pdo->exec("SET session_replication_role = DEFAULT;");
        
        // Reseta sequências (auto-increment)
        echo "<div>🔄 Resetando sequências...</div>";
        ob_flush();
        flush();
        
        $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        $tabelas2 = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tabelas2 as $tabela) {
            try {
                $pdo->exec("ALTER SEQUENCE IF EXISTS \"{$tabela}_id_seq\" RESTART WITH 1");
            } catch (PDOException $e) {
                // Ignora erros de sequência
            }
        }
        
        echo "<div class='success' style='margin-top: 20px;'>";
        echo "<h2>✅ LIMPEZA CONCLUÍDA COM SUCESSO!</h2>";
        echo "<p><strong>" . number_format($total_deletados, 0, ',', '.') . "</strong> registros foram removidos de <strong>" . count($tabelas) . "</strong> tabelas.</p>";
        echo "</div>";
        echo "<p><a href='/' class='btn btn-primary'>Voltar ao Sistema</a></p>";
        echo "<p><a href='migrar_dados.php' class='btn btn-success'>Recarregar Dados</a></p>";
        
    } catch (PDOException $e) {
        echo "<div class='error'>❌ ERRO DURANTE A EXCLUSÃO: " . $e->getMessage() . "</div>";
        // Tenta reativar verificações
        try {
            $pdo->exec("SET session_replication_role = DEFAULT;");
        } catch (PDOException $e2) {
            // Ignora
        }
    }
    
    echo "</div>";
}

echo "
<script>
function confirmarExclusao() {
    var c1 = document.getElementById('confirm1');
    var c2 = document.getElementById('confirm2');
    var c3 = document.getElementById('confirm3');
    
    if (!c1.checked || !c2.checked || !c3.checked) {
        alert('Por favor, marque todas as opções de confirmação para prosseguir.');
        return false;
    }
    
    var total = " . ($total_registros ?? 0) . ";
    var msg = '⚠️ ATENÇÃO! Você está prestes a apagar ' + total.toLocaleString() + ' registros permanentemente.\\n\\n';
    msg += 'Esta ação NÃO pode ser desfeita!\\n\\n';
    msg += 'Tem certeza absoluta que deseja continuar?';
    
    return confirm(msg);
}

// Habilita o botão quando todos os checkboxes estiverem marcados
document.addEventListener('DOMContentLoaded', function() {
    var c1 = document.getElementById('confirm1');
    var c2 = document.getElementById('confirm2');
    var c3 = document.getElementById('confirm3');
    var btn = document.getElementById('btnExcluir');
    
    function verificarCheckboxes() {
        if (c1 && c2 && c3 && btn) {
            btn.disabled = !(c1.checked && c2.checked && c3.checked);
        }
    }
    
    if (c1) c1.addEventListener('change', verificarCheckboxes);
    if (c2) c2.addEventListener('change', verificarCheckboxes);
    if (c3) c3.addEventListener('change', verificarCheckboxes);
});
</script>
";

echo "</div></body></html>";
?>