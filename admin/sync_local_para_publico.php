<?php
// ============================================
// admin/sync_local_para_publico.php
// Sincroniza MySQL → PostgreSQL (Neon)
// COM conversão automática de tipos e criação de tabelas
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if (($_SESSION['usuario_perfil'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Apenas administradores']);
    exit;
}

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
$acao = $input['acao'] ?? 'sincronizar_tudo';

// ============================================
// FUNÇÃO: Converter tipo MySQL → PostgreSQL
// ============================================
function converterTipoMySQLparaPG($tipo_mysql) {
    $tipo = strtolower(trim($tipo_mysql));
    
    // Extrair parâmetros (ex: varchar(255) → 255)
    $params = '';
    if (preg_match('/\(([^)]+)\)/', $tipo, $m)) {
        $params = $m[1];
    }
    
    $tipo_base = preg_replace('/\(.*\)/', '', $tipo);
    $tipo_base = trim($tipo_base);
    
    $mapa = [
        'int' => 'INTEGER',
        'tinyint' => 'SMALLINT',
        'smallint' => 'SMALLINT',
        'mediumint' => 'INTEGER',
        'bigint' => 'BIGINT',
        'decimal' => 'NUMERIC',
        'numeric' => 'NUMERIC',
        'float' => 'REAL',
        'double' => 'DOUBLE PRECISION',
        'real' => 'REAL',
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
        'bool' => 'BOOLEAN',
        'bit' => 'SMALLINT'
    ];
    
    $tipo_pg = $mapa[$tipo_base] ?? 'TEXT';
    
    // Para VARCHAR, CHAR, NUMERIC, DECIMAL: manter os parâmetros
    if (in_array($tipo_base, ['varchar', 'char', 'decimal', 'numeric']) && !empty($params)) {
        $tipo_pg .= '(' . $params . ')';
    }
    
    // ENUM → VARCHAR(255) por padrão se não tiver params
    if ($tipo_base === 'enum') {
        $tipo_pg = 'VARCHAR(255)';
    }
    
    return $tipo_pg;
}

// ============================================
// FUNÇÃO: Normalizar valor MySQL → PostgreSQL
// ============================================
function normalizarValorParaPG($valor, $tipo_mysql) {
    if ($valor === null) {
        return null;
    }
    
    $tipo = strtolower($tipo_mysql);
    
    // Booleanos MySQL (0/1) → PostgreSQL (t/f)
    if (strpos($tipo, 'tinyint(1)') !== false || 
        strpos($tipo, 'boolean') !== false ||
        strpos($tipo, 'bool') !== false) {
        return $valor ? '1' : '0';
    }
    
    // Strings vazias em colunas numéricas → NULL
    if ($valor === '' && preg_match('/(int|decimal|float|double|numeric|bigint|smallint|tinyint|mediumint)/i', $tipo)) {
        return null;
    }
    
    // Strings vazias em datas → NULL
    if ($valor === '' && preg_match('/(date|time|timestamp|year)/i', $tipo)) {
        return null;
    }
    
    // Datas inválidas '0000-00-00' → NULL
    if (is_string($valor) && strpos($valor, '0000-00-00') === 0) {
        return null;
    }
    
    return $valor;
}

// ============================================
// FUNÇÃO: Criar tabela no PostgreSQL
// ============================================
function criarTabelaPG($local, $publico, $tabela) {
    try {
        // Verificar se já existe no PostgreSQL
        $stmt = $publico->query("
            SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = 'public' AND table_name = '$tabela'
        ");
        if ($stmt->fetchColumn() > 0) {
            return ['existe' => true, 'criada' => false];
        }
        
        // Buscar estrutura do MySQL
        $stmt = $local->query("DESCRIBE `$tabela`");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar chave primária
        $stmt = $local->query("SHOW KEYS FROM `$tabela` WHERE Key_name = 'PRIMARY'");
        $pk = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pk_cols = array_column($pk, 'Column_name');
        
        // Construir CREATE TABLE PostgreSQL
        $colunas_pg = [];
        $auto_increment_col = null;
        
        foreach ($colunas as $col) {
            $nome = $col['Field'];
            $tipo_mysql = $col['Type'];
            $null = $col['Null'];
            $default = $col['Default'];
            $extra = $col['Extra'];
            
            $tipo_pg = converterTipoMySQLparaPG($tipo_mysql);
            
            // AUTO_INCREMENT → SERIAL
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
        
        $sql = "CREATE TABLE IF NOT EXISTS \"$tabela\" (\n    " . implode(",\n    ", $colunas_pg) . "\n)";
        
        $publico->exec($sql);
        
        return ['existe' => true, 'criada' => true, 'sql' => $sql];
        
    } catch (Exception $e) {
        return ['existe' => false, 'criada' => false, 'erro' => $e->getMessage()];
    }
}

try {
    // ===== CONEXÕES =====
    $local = conectarLocal();
    $publico = conectarPublico();
    
    // ===== TABELAS A SINCRONIZAR =====
    $tabelas = [
        'alunos', 'turmas', 'disciplinas', 'professores', 'funcionarios',
        'empresa', 'mensagens', 'mensagens_anexos', 'horarios', 'tempos',
        'matriculas', 'cursos', 'emolumentos', 'pagamentos', 'usuarios',
        'notas', 'frequencia', 'distribuicao_professores'
    ];
    
    $total_registros = 0;
    $detalhes = [];
    $erros = [];
    $tabelas_criadas = [];
    
    foreach ($tabelas as $tabela) {
        try {
            // Verificar se existe no MySQL
            try {
                $local->query("SELECT 1 FROM `$tabela` LIMIT 1");
            } catch (Exception $e) {
                $detalhes[$tabela] = 'Não existe no local';
                continue;
            }
            
            // ===== CRIAR TABELA NO POSTGRESQL SE NÃO EXISTIR =====
            $resultado_criacao = criarTabelaPG($local, $publico, $tabela);
            
            if (!$resultado_criacao['existe']) {
                $detalhes[$tabela] = 'ERRO ao criar: ' . ($resultado_criacao['erro'] ?? 'desconhecido');
                $erros[] = "$tabela: " . ($resultado_criacao['erro'] ?? 'desconhecido');
                continue;
            }
            
            if ($resultado_criacao['criada']) {
                $tabelas_criadas[] = $tabela;
            }
            
            // ===== BUSCAR ESTRUTURA DA TABELA MYSQL =====
            $stmt = $local->query("DESCRIBE `$tabela`");
            $colunas_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tipos_colunas = [];
            foreach ($colunas_info as $col) {
                $tipos_colunas[$col['Field']] = $col['Type'];
            }
            
            // ===== BUSCAR DADOS DO MYSQL =====
            $stmt = $local->query("SELECT * FROM `$tabela`");
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($dados)) {
                $detalhes[$tabela] = 0;
                continue;
            }
            
            // ===== LIMPAR TABELA NO POSTGRESQL =====
            try {
                $publico->exec("SET session_replication_role = 'replica'");
            } catch (Exception $e) {}
            
            try {
                $publico->exec("TRUNCATE TABLE \"$tabela\" RESTART IDENTITY CASCADE");
            } catch (Exception $e) {
                try {
                    $publico->exec("TRUNCATE TABLE \"$tabela\" CASCADE");
                } catch (Exception $e2) {
                    try {
                        $publico->exec("DELETE FROM \"$tabela\"");
                    } catch (Exception $e3) {}
                }
            }
            
            // ===== PREPARAR INSERT =====
            $colunas = array_keys($dados[0]);
            $colunas_quoted = array_map(function($c) { return '"' . $c . '"'; }, $colunas);
            $placeholders = [];
            for ($i = 1; $i <= count($colunas); $i++) {
                $placeholders[] = '$' . $i;
            }
            
            $colunas_str = implode(', ', $colunas_quoted);
            $placeholders_str = implode(', ', $placeholders);
            
            $insert = $publico->prepare("INSERT INTO \"$tabela\" ($colunas_str) VALUES ($placeholders_str)");
            
            $contador = 0;
            $publico->beginTransaction();
            
            foreach ($dados as $linha) {
                try {
                    // Normalizar cada valor
                    $valores = [];
                    foreach ($linha as $coluna => $valor) {
                        $tipo_mysql = $tipos_colunas[$coluna] ?? 'varchar';
                        $valores[] = normalizarValorParaPG($valor, $tipo_mysql);
                    }
                    
                    $insert->execute($valores);
                    $contador++;
                    
                } catch (Exception $e) {
                    $erros[] = "$tabela (ID " . ($linha['id'] ?? '?') . "): " . $e->getMessage();
                }
            }
            
            $publico->commit();
            
            try {
                $publico->exec("SET session_replication_role = 'origin'");
            } catch (Exception $e) {}
            
            $detalhes[$tabela] = $contador;
            $total_registros += $contador;
            
        } catch (Exception $e) {
            $detalhes[$tabela] = 'ERRO: ' . $e->getMessage();
            $erros[] = "$tabela: " . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Sincronização Local → Público concluída',
        'total_registros' => $total_registros,
        'tabelas_criadas' => $tabelas_criadas,
        'detalhes' => $detalhes,
        'erros' => array_slice($erros, 0, 10),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}