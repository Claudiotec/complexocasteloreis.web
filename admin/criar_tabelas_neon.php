<?php
// ============================================
// admin/criar_tabelas_neon.php
// Cria automaticamente as tabelas no Neon (PostgreSQL)
// a partir da estrutura do MySQL (local)
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/app_modes.php';

header('Content-Type: text/html; charset=utf-8');

echo "<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; background: #f0f2f5; }
    .box { background: white; padding: 20px; border-radius: 10px; margin: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 5px solid #3b82f6; }
    .ok { color: #16a34a; font-weight: 600; }
    .erro { color: #dc2626; font-weight: 600; }
    .aviso { color: #f59e0b; font-weight: 600; }
    .info { color: #2563eb; }
    pre { background: #1a2332; color: #e2e8f0; padding: 15px; border-radius: 8px; overflow: auto; font-size: 12px; line-height: 1.5; }
    code { background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-family: monospace; color: #1a2332; }
    h1 { color: #1a2332; }
    h2 { color: #1a2332; margin-top: 0; border-bottom: 2px solid #eef2f7; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eef2f7; font-size: 13px; }
    th { background: #f8fafc; font-weight: 600; color: #4a5568; }
    .btn { display: inline-block; padding: 12px 25px; background: #c9a84c; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; font-size: 15px; }
    .btn:hover { background: #b8973a; }
    .btn-perigo { background: #ef4444; }
    .btn-perigo:hover { background: #dc2626; }
</style>";

echo "<h1>🔧 Criar Tabelas no Neon (PostgreSQL)</h1>";

// ===== VERIFICAR AÇÃO =====
$acao = $_GET['acao'] ?? 'preview';

if ($acao === 'executar') {
    echo "<div class='box' style='border-left-color:#ef4444;background:#fef2f2;'>";
    echo "<p class='erro'>⚠️ <strong>MODO DE EXECUÇÃO</strong> — As tabelas serão criadas no Neon agora.</p>";
    echo "</div>";
}

try {
    $local = conectarLocal();
    $publico = conectarPublico();
    
    // ===== 1. BUSCAR TODAS AS TABELAS DO MYSQL =====
    $stmt = $local->query("SHOW TABLES");
    $tabelas_mysql = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<div class='box'>";
    echo "<h2>📋 Tabelas encontradas no MySQL</h2>";
    echo "<p>Total: <strong>" . count($tabelas_mysql) . "</strong></p>";
    echo "<p>" . implode(', ', $tabelas_mysql) . "</p>";
    echo "</div>";
    
    // ===== 2. FUNÇÃO PARA CONVERTER TIPO MYSQL → POSTGRESQL =====
    function converterTipo($tipo_mysql) {
        $tipo = strtolower($tipo_mysql);
        
        // Remover parâmetros
        $tipo_base = preg_replace('/\(.*\)/', '', $tipo);
        $tipo_base = trim($tipo_base);
        
        // Mapeamento
        $mapa = [
            'int' => 'INTEGER',
            'tinyint' => 'SMALLINT',
            'smallint' => 'SMALLINT',
            'mediumint' => 'INTEGER',
            'bigint' => 'BIGINT',
            'decimal' => 'NUMERIC',
            'float' => 'REAL',
            'double' => 'DOUBLE PRECISION',
            'varchar' => 'VARCHAR',
            'char' => 'CHAR',
            'text' => 'TEXT',
            'tinytext' => 'TEXT',
            'mediumtext' => 'TEXT',
            'longtext' => 'TEXT',
            'date' => 'DATE',
            'datetime' => 'TIMESTAMP',
            'timestamp' => 'TIMESTAMP',
            'time' => 'TIME',
            'year' => 'INTEGER',
            'blob' => 'BYTEA',
            'tinyblob' => 'BYTEA',
            'mediumblob' => 'BYTEA',
            'longblob' => 'BYTEA',
            'enum' => 'VARCHAR',
            'set' => 'TEXT',
            'json' => 'JSONB',
            'boolean' => 'BOOLEAN',
            'bit' => 'SMALLINT'
        ];
        
        $resultado = $mapa[$tipo_base] ?? 'TEXT';
        
        // Manter parâmetros para VARCHAR/CHAR/DECIMAL
        if (preg_match('/\((\d+)(,(\d+))?\)/', $tipo_mysql, $matches)) {
            if (in_array($tipo_base, ['varchar', 'char', 'decimal', 'numeric'])) {
                $resultado .= '(' . $matches[1];
                if (isset($matches[3])) $resultado .= ',' . $matches[3];
                $resultado .= ')';
            }
        }
        
        return $resultado;
    }
    
    // ===== 3. GERAR SQL PARA CADA TABELA =====
    $sqls = [];
    $total_sqls = 0;
    
    foreach ($tabelas_mysql as $tabela) {
        try {
            // Buscar estrutura
            $stmt = $local->query("SHOW CREATE TABLE `$tabela`");
            $create = $stmt->fetch(PDO::FETCH_ASSOC);
            $sql_mysql = $create['Create Table'] ?? '';
            
            // Buscar colunas detalhadas
            $stmt = $local->query("DESCRIBE `$tabela`");
            $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Buscar chave primária
            $stmt = $local->query("SHOW KEYS FROM `$tabela` WHERE Key_name = 'PRIMARY'");
            $primary_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $pk_cols = array_column($primary_keys, 'Column_name');
            
            // Construir CREATE TABLE PostgreSQL
            $colunas_pg = [];
            $auto_increment_col = null;
            
            foreach ($colunas as $col) {
                $nome = $col['Field'];
                $tipo_mysql = $col['Type'];
                $null = $col['Null'];
                $key = $col['Key'];
                $default = $col['Default'];
                $extra = $col['Extra'];
                
                $tipo_pg = converterTipo($tipo_mysql);
                
                // Detectar AUTO_INCREMENT
                if (stripos($extra, 'auto_increment') !== false) {
                    $auto_increment_col = $nome;
                    $tipo_pg = 'SERIAL';
                }
                
                $linha = '"' . $nome . '" ' . $tipo_pg;
                
                // NOT NULL
                if ($null === 'NO' && stripos($extra, 'auto_increment') === false) {
                    $linha .= ' NOT NULL';
                }
                
                // DEFAULT
                if ($default !== null && stripos($extra, 'auto_increment') === false) {
                    if (strtolower($default) === 'current_timestamp()' || strtolower($default) === 'current_timestamp') {
                        $linha .= ' DEFAULT CURRENT_TIMESTAMP';
                    } elseif (is_numeric($default)) {
                        $linha .= ' DEFAULT ' . $default;
                    } else {
                        $linha .= " DEFAULT '" . addslashes($default) . "'";
                    }
                }
                
                $colunas_pg[] = $linha;
            }
            
            // Adicionar PRIMARY KEY
            if (!empty($pk_cols)) {
                $pk_quoted = array_map(function($c) { return '"' . $c . '"'; }, $pk_cols);
                $colunas_pg[] = 'PRIMARY KEY (' . implode(', ', $pk_quoted) . ')';
            }
            
            // Montar SQL final
            $sql_pg = "CREATE TABLE IF NOT EXISTS \"$tabela\" (\n    " . implode(",\n    ", $colunas_pg) . "\n);";
            
            $sqls[$tabela] = $sql_pg;
            $total_sqls++;
            
        } catch (Exception $e) {
            $sqls[$tabela] = "-- ERRO: " . $e->getMessage();
        }
    }
    
    // ===== 4. MOSTRAR PREVIEW =====
    echo "<div class='box'>";
    echo "<h2>📝 SQL gerado para PostgreSQL</h2>";
    echo "<p>Total de tabelas: <strong>$total_sqls</strong></p>";
    echo "</div>";
    
    foreach ($sqls as $tabela => $sql) {
        echo "<div class='box'>";
        echo "<h3>📄 $tabela</h3>";
        echo "<pre>" . htmlspecialchars($sql) . "</pre>";
        echo "</div>";
    }
    
    // ===== 5. EXECUTAR SE SOLICITADO =====
    if ($acao === 'executar') {
        echo "<div class='box' style='border-left-color:#16a34a;background:#f0fdf4;'>";
        echo "<h2>🚀 Executando no Neon...</h2>";
        
        $sucesso = 0;
        $erros = 0;
        $detalhes = [];
        
        foreach ($sqls as $tabela => $sql) {
            try {
                $publico->exec($sql);
                $sucesso++;
                $detalhes[] = "✅ <strong>$tabela</strong> — criada com sucesso";
            } catch (Exception $e) {
                $erros++;
                $detalhes[] = "❌ <strong>$tabela</strong> — " . htmlspecialchars($e->getMessage());
            }
        }
        
        echo "<p class='ok'>✅ Tabelas criadas: $sucesso</p>";
        if ($erros > 0) {
            echo "<p class='erro'>❌ Erros: $erros</p>";
        }
        echo "<ul>";
        foreach ($detalhes as $d) {
            echo "<li>$d</li>";
        }
        echo "</ul>";
        echo "</div>";
        
        // Verificar resultado
        echo "<div class='box'>";
        echo "<h2>📊 Tabelas no Neon após execução</h2>";
        $stmt = $publico->query("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            ORDER BY table_name
        ");
        $tabelas_neon = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p>Total: <strong>" . count($tabelas_neon) . "</strong></p>";
        echo "<ul>";
        foreach ($tabelas_neon as $t) {
            echo "<li>✅ " . htmlspecialchars($t) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
        
    } else {
        // ===== BOTÃO PARA EXECUTAR =====
        echo "<div class='box' style='border-left-color:#c9a84c;background:#fefcf3;'>";
        echo "<h2>⚠️ Ação necessária</h2>";
        echo "<p>Revise o SQL acima. Se estiver correto, clique no botão abaixo para <strong>criar as tabelas no Neon</strong>.</p>";
        echo "<p style='color:#dc2626;'><strong>Atenção:</strong> Isto vai criar as tabelas na base de dados pública.</p>";
        echo "<a href='?acao=executar' class='btn' onclick=\"return confirm('Confirma a criação das tabelas no Neon?')\">🚀 Executar no Neon</a>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='box' style='border-left-color:#dc2626;background:#fef2f2;'>";
    echo "<h2 class='erro'>❌ Erro</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>