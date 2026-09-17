<?php
// admin/sync_database.php
// Sincronização de Banco de Dados - CORRIGIDO

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// CONFIGURAÇÕES
// ============================================

// Local (MySQL - XAMPP)
define('DB_LOCAL_HOST', 'localhost');
define('DB_LOCAL_PORT', '3306');
define('DB_LOCAL_NAME', 'softgest_db');
define('DB_LOCAL_USER', 'root');
define('DB_LOCAL_PASS', 'Claudtec');

// Público (PostgreSQL - Neon.tech)
define('DB_PUBLIC_HOST', 'ep-holy-resonance-atm1ick2-pooler.c-9.us-east-1.aws.neon.tech');
define('DB_PUBLIC_PORT', '5432');
define('DB_PUBLIC_NAME', 'neondb');
define('DB_PUBLIC_USER', 'neondb_owner');
define('DB_PUBLIC_PASS', 'npg_gRkKHXNJAI40');
define('DB_PUBLIC_ENDPOINT', 'ep-holy-resonance-atm1ick2-pooler');

// ============================================
// FUNÇÕES DE CONEXÃO
// ============================================

function conectarLocal() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_LOCAL_HOST . ";dbname=" . DB_LOCAL_NAME . ";charset=utf8mb4",
            DB_LOCAL_USER,
            DB_LOCAL_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro Local: " . $e->getMessage());
    }
}

function conectarPublico() {
    try {
        $options = 'endpoint=' . DB_PUBLIC_ENDPOINT;
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;sslmode=require;options='%s'",
            DB_PUBLIC_HOST,
            DB_PUBLIC_PORT,
            DB_PUBLIC_NAME,
            $options
        );
        
        $pdo = new PDO($dsn, DB_PUBLIC_USER, DB_PUBLIC_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 30
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro Público: " . $e->getMessage());
    }
}

// ============================================
// FUNÇÕES DE LISTAGEM
// ============================================

function listarTabelasMySQL($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

function listarTabelasPostgres($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

function listarColunasMySQL($pdo, $tabela) {
    try {
        $stmt = $pdo->query("DESCRIBE $tabela");
        $colunas = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $colunas[] = $row['Field'];
        }
        return $colunas;
    } catch (Exception $e) {
        return [];
    }
}

function listarColunasPostgres($pdo, $tabela) {
    try {
        $stmt = $pdo->prepare("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = ? 
            AND table_schema = 'public'
            ORDER BY ordinal_position
        ");
        $stmt->execute([$tabela]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

// ============================================
// FUNÇÃO PARA TRATAR DATAS
// ============================================

function tratarData($valor, $coluna = null) {
    // Se for null ou vazio
    if ($valor === null || $valor === '') {
        // Para data_nascimento, retornar uma data padrão
        if ($coluna === 'data_nascimento') {
            return '1970-01-01';
        }
        return null;
    }
    
    // Se for data inválida do MySQL
    if ($valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
        if ($coluna === 'data_nascimento') {
            return '1970-01-01';
        }
        return null;
    }
    
    // Tentar converter para timestamp
    $timestamp = strtotime($valor);
    if ($timestamp === false || $timestamp < 0) {
        // Tentar formato brasileiro (dd/mm/YYYY)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $valor, $matches)) {
            $data_br = $matches[3] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $timestamp = strtotime($data_br);
            if ($timestamp !== false && $timestamp > 0) {
                return date('Y-m-d', $timestamp);
            }
        }
        if ($coluna === 'data_nascimento') {
            return '1970-01-01';
        }
        return null;
    }
    
    return date('Y-m-d', $timestamp);
}

// ============================================
// FUNÇÃO PARA CRIAR TABELA NO POSTGRES
// ============================================

function criarTabelaPostgres($tabela) {
    $publico = conectarPublico();
    $local = conectarLocal();
    
    $stmt = $local->query("DESCRIBE $tabela");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($colunas)) {
        return ['success' => false, 'message' => "Tabela '$tabela' não encontrada no MySQL"];
    }
    
    $sql = "CREATE TABLE IF NOT EXISTS $tabela (";
    $colunas_sql = [];
    
    foreach ($colunas as $col) {
        $nome = $col['Field'];
        $tipo = $col['Type'];
        $nulo = $col['Null'] === 'YES' ? '' : ' NOT NULL';
        $padrao = $col['Default'];
        $extra = $col['Extra'] ?? '';
        
        // Converter tipo MySQL para PostgreSQL
        $tipo_pg = converterTipoMySQLparaPostgres($tipo);
        
        if ($extra === 'auto_increment') {
            $tipo_pg = 'SERIAL PRIMARY KEY';
            $nulo = '';
            $padrao = null;
        }
        
        // Tratar DEFAULT
        $default_sql = '';
        if ($padrao !== null && $extra !== 'auto_increment') {
            if ($padrao === '0000-00-00' || $padrao === '0000-00-00 00:00:00') {
                $padrao = null;
            } elseif (strpos(strtolower($padrao), 'current_timestamp') !== false) {
                $default_sql = " DEFAULT CURRENT_TIMESTAMP";
            } elseif (strpos(strtolower($padrao), 'now()') !== false) {
                $default_sql = " DEFAULT CURRENT_TIMESTAMP";
            } elseif (is_numeric($padrao)) {
                $default_sql = " DEFAULT $padrao";
            } elseif ($padrao !== null && $padrao !== '') {
                $default_sql = " DEFAULT '" . addslashes($padrao) . "'";
            }
        }
        
        $colunas_sql[] = "$nome $tipo_pg$nulo$default_sql";
    }
    
    $sql .= implode(", ", $colunas_sql);
    $sql .= ")";
    
    try {
        $publico->exec($sql);
        return ['success' => true, 'message' => "Tabela '$tabela' criada com sucesso"];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => "Erro ao criar tabela: " . $e->getMessage()];
    }
}

function converterTipoMySQLparaPostgres($tipo_mysql) {
    $tipo_mysql = strtolower(trim($tipo_mysql));
    $tipo_base = preg_replace('/\(.*\)/', '', $tipo_mysql);
    $tipo_base = trim($tipo_base);
    
    preg_match('/\((\d+)\)/', $tipo_mysql, $matches);
    $tamanho = $matches[1] ?? null;
    
    $mapa = [
        'int' => 'INTEGER',
        'integer' => 'INTEGER',
        'tinyint' => 'SMALLINT',
        'smallint' => 'SMALLINT',
        'mediumint' => 'INTEGER',
        'bigint' => 'BIGINT',
        'decimal' => 'DECIMAL(10,2)',
        'float' => 'REAL',
        'double' => 'DOUBLE PRECISION',
        'varchar' => 'VARCHAR',
        'char' => 'CHAR',
        'text' => 'TEXT',
        'tinytext' => 'TEXT',
        'mediumtext' => 'TEXT',
        'longtext' => 'TEXT',
        'datetime' => 'TIMESTAMP',
        'timestamp' => 'TIMESTAMP',
        'date' => 'DATE',
        'time' => 'TIME',
        'year' => 'INTEGER',
        'enum' => 'VARCHAR(50)',
        'set' => 'TEXT',
        'blob' => 'BYTEA',
        'tinyblob' => 'BYTEA',
        'mediumblob' => 'BYTEA',
        'longblob' => 'BYTEA',
        'json' => 'JSONB'
    ];
    
    if (isset($mapa[$tipo_base])) {
        $tipo_pg = $mapa[$tipo_base];
        if ($tamanho && ($tipo_base === 'varchar' || $tipo_base === 'char')) {
            $tamanho = min($tamanho, 10485760);
            $tipo_pg = "$tipo_pg($tamanho)";
        }
        return $tipo_pg;
    }
    
    return 'TEXT';
}

// ============================================
// FUNÇÃO PRINCIPAL DE SINCRONIZAÇÃO
// ============================================

function sincronizarLocalParaPublico($tabela, $colunas_selecionadas = null, $filtros = [], $limpar = false) {
    $publico = conectarPublico();
    $local = conectarLocal();
    
    // Verificar se a tabela existe no público
    $tabelas_publico = listarTabelasPostgres($publico);
    if (!in_array($tabela, $tabelas_publico)) {
        $resultado = criarTabelaPostgres($tabela);
        if (!$resultado['success']) {
            return $resultado;
        }
    }
    
    // Obter colunas
    if (empty($colunas_selecionadas)) {
        $colunas = listarColunasMySQL($local, $tabela);
    } else {
        $colunas = $colunas_selecionadas;
    }
    
    if (empty($colunas)) {
        return ['success' => false, 'message' => "Nenhuma coluna encontrada para a tabela '$tabela'"];
    }
    
    // Montar WHERE
    $where = [];
    $params = [];
    foreach ($filtros as $campo => $valor) {
        if (!empty($valor) && in_array($campo, $colunas)) {
            $where[] = "$campo = ?";
            $params[] = $valor;
        }
    }
    
    // Buscar dados
    $sql = "SELECT " . implode(', ', $colunas) . " FROM $tabela";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $stmt = $local->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($dados)) {
        return ['success' => true, 'message' => "Nenhum dado encontrado", 'total' => 0];
    }
    
    // Limpar destino se solicitado
    if ($limpar) {
        $publico->exec("TRUNCATE TABLE $tabela");
    }
    
    // Buscar chave primária
    $pk_coluna = $colunas[0];
    try {
        $stmt_pk = $local->query("SHOW KEYS FROM $tabela WHERE Key_name = 'PRIMARY'");
        $pk = $stmt_pk->fetch(PDO::FETCH_ASSOC);
        if ($pk) {
            $pk_coluna = $pk['Column_name'];
        }
    } catch (Exception $e) {}
    
    $inseridos = 0;
    $atualizados = 0;
    $erros = [];
    
    foreach ($dados as $row) {
        // Processar cada coluna
        $row_processado = [];
        foreach ($colunas as $col) {
            $valor = $row[$col];
            
            // Tratar datas
            if ($col === 'data_nascimento') {
                $valor = tratarData($valor, 'data_nascimento');
            } elseif (strpos($col, 'data') !== false || strpos($col, 'date') !== false) {
                $valor = tratarData($valor);
            }
            
            // Se for null e a coluna é NOT NULL no PostgreSQL, colocar valor padrão
            if ($valor === null && $col === 'data_nascimento') {
                $valor = '1970-01-01';
            }
            
            $row_processado[$col] = $valor;
        }
        
        try {
            // Verificar se existe
            $stmt_check = $publico->prepare("SELECT COUNT(*) FROM $tabela WHERE $pk_coluna = ?");
            $stmt_check->execute([$row_processado[$pk_coluna] ?? null]);
            $existe = $stmt_check->fetchColumn();
            
            if ($existe) {
                // UPDATE
                $set = [];
                $params_update = [];
                foreach ($colunas as $campo) {
                    if ($campo !== $pk_coluna) {
                        $set[] = "$campo = ?";
                        $params_update[] = $row_processado[$campo];
                    }
                }
                if (!empty($set)) {
                    $params_update[] = $row_processado[$pk_coluna];
                    $sql_update = "UPDATE $tabela SET " . implode(', ', $set) . " WHERE $pk_coluna = ?";
                    $stmt_update = $publico->prepare($sql_update);
                    $stmt_update->execute($params_update);
                    $atualizados++;
                }
            } else {
                // INSERT - filtrar colunas com valor não nulo
                $colunas_insert = [];
                $valores_insert = [];
                foreach ($colunas as $campo) {
                    $valor = $row_processado[$campo];
                    if ($valor !== null) {
                        $colunas_insert[] = $campo;
                        $valores_insert[] = $valor;
                    }
                }
                
                if (!empty($colunas_insert)) {
                    $placeholders = implode(', ', array_fill(0, count($colunas_insert), '?'));
                    $sql_insert = "INSERT INTO $tabela (" . implode(', ', $colunas_insert) . ") VALUES ($placeholders)";
                    $stmt_insert = $publico->prepare($sql_insert);
                    $stmt_insert->execute($valores_insert);
                    $inseridos++;
                }
            }
        } catch (PDOException $e) {
            $erros[] = "Erro no registro " . ($row_processado[$pk_coluna] ?? '') . ": " . $e->getMessage();
        }
    }
    
    $mensagem = "Sincronização concluída!";
    if (!empty($erros)) {
        $mensagem .= "\n⚠️ " . count($erros) . " erros encontrados.";
    }
    
    return [
        'success' => true,
        'message' => $mensagem,
        'total' => count($dados),
        'inseridos' => $inseridos,
        'atualizados' => $atualizados,
        'erros' => $erros
    ];
}

function sincronizarPublicoParaLocal($tabela, $colunas_selecionadas = null, $filtros = [], $limpar = false) {
    $publico = conectarPublico();
    $local = conectarLocal();
    
    // Verificar se a tabela existe no público
    $tabelas_publico = listarTabelasPostgres($publico);
    if (!in_array($tabela, $tabelas_publico)) {
        return ['success' => false, 'message' => "Tabela '$tabela' não encontrada no PostgreSQL"];
    }
    
    // Obter colunas
    if (empty($colunas_selecionadas)) {
        $colunas = listarColunasPostgres($publico, $tabela);
    } else {
        $colunas = $colunas_selecionadas;
    }
    
    if (empty($colunas)) {
        return ['success' => false, 'message' => "Nenhuma coluna encontrada para a tabela '$tabela'"];
    }
    
    // Montar WHERE
    $where = [];
    $params = [];
    foreach ($filtros as $campo => $valor) {
        if (!empty($valor) && in_array($campo, $colunas)) {
            $where[] = "$campo = ?";
            $params[] = $valor;
        }
    }
    
    // Buscar dados do PostgreSQL
    $sql = "SELECT " . implode(', ', $colunas) . " FROM $tabela";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $stmt = $publico->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($dados)) {
        return ['success' => true, 'message' => "Nenhum dado encontrado", 'total' => 0];
    }
    
    // Limpar destino se solicitado
    if ($limpar) {
        $local->exec("TRUNCATE TABLE $tabela");
    }
    
    // Buscar chave primária no MySQL
    $pk_coluna = $colunas[0];
    try {
        $stmt_pk = $local->query("SHOW KEYS FROM $tabela WHERE Key_name = 'PRIMARY'");
        $pk = $stmt_pk->fetch(PDO::FETCH_ASSOC);
        if ($pk) {
            $pk_coluna = $pk['Column_name'];
        }
    } catch (Exception $e) {}
    
    $inseridos = 0;
    $atualizados = 0;
    $erros = [];
    
    foreach ($dados as $row) {
        try {
            // Verificar se existe no MySQL
            $stmt_check = $local->prepare("SELECT COUNT(*) FROM $tabela WHERE $pk_coluna = ?");
            $stmt_check->execute([$row[$pk_coluna] ?? null]);
            $existe = $stmt_check->fetchColumn();
            
            if ($existe) {
                // UPDATE
                $set = [];
                $params_update = [];
                foreach ($colunas as $campo) {
                    if ($campo !== $pk_coluna) {
                        $set[] = "$campo = ?";
                        $params_update[] = $row[$campo];
                    }
                }
                if (!empty($set)) {
                    $params_update[] = $row[$pk_coluna];
                    $sql_update = "UPDATE $tabela SET " . implode(', ', $set) . " WHERE $pk_coluna = ?";
                    $stmt_update = $local->prepare($sql_update);
                    $stmt_update->execute($params_update);
                    $atualizados++;
                }
            } else {
                // INSERT
                $placeholders = implode(', ', array_fill(0, count($colunas), '?'));
                $sql_insert = "INSERT INTO $tabela (" . implode(', ', $colunas) . ") VALUES ($placeholders)";
                $stmt_insert = $local->prepare($sql_insert);
                $stmt_insert->execute(array_values($row));
                $inseridos++;
            }
        } catch (PDOException $e) {
            $erros[] = "Erro no registro " . ($row[$pk_coluna] ?? '') . ": " . $e->getMessage();
        }
    }
    
    $mensagem = "Sincronização concluída!";
    if (!empty($erros)) {
        $mensagem .= "\n⚠️ " . count($erros) . " erros encontrados.";
    }
    
    return [
        'success' => true,
        'message' => $mensagem,
        'total' => count($dados),
        'inseridos' => $inseridos,
        'atualizados' => $atualizados,
        'erros' => $erros
    ];
}

// ============================================
// PROCESSAR AÇÕES
// ============================================

$mensagem = '';
$tipo_mensagem = '';
$resultado = null;
$local_ok = false;
$publico_ok = false;
$tabelas_local = [];
$tabelas_publico = [];

try {
    $local = conectarLocal();
    $local_ok = true;
    $tabelas_local = listarTabelasMySQL($local);
} catch (Exception $e) {}

try {
    $publico = conectarPublico();
    $publico_ok = true;
    $tabelas_publico = listarTabelasPostgres($publico);
} catch (Exception $e) {}

// AJAX para listar colunas
if (isset($_GET['action']) && $_GET['action'] === 'get_colunas') {
    header('Content-Type: application/json');
    $tabela = $_GET['tabela'] ?? '';
    $direcao = $_GET['direcao'] ?? 'local_para_publico';
    $colunas = [];
    
    try {
        if ($direcao === 'publico_para_local') {
            // Buscar colunas do PostgreSQL
            $publico = conectarPublico();
            $colunas = listarColunasPostgres($publico, $tabela);
        } else {
            // Buscar colunas do MySQL
            $local = conectarLocal();
            $colunas = listarColunasMySQL($local, $tabela);
        }
    } catch (Exception $e) {}
    
    echo json_encode($colunas);
    exit;
}

// AJAX para listar tabelas
if (isset($_GET['action']) && $_GET['action'] === 'get_tabelas') {
    header('Content-Type: application/json');
    $direcao = $_GET['direcao'] ?? 'local_para_publico';
    $tabelas = [];
    
    try {
        if ($direcao === 'publico_para_local') {
            // Buscar tabelas do PostgreSQL
            $publico = conectarPublico();
            $tabelas = listarTabelasPostgres($publico);
        } else {
            // Buscar tabelas do MySQL
            $local = conectarLocal();
            $tabelas = listarTabelasMySQL($local);
        }
    } catch (Exception $e) {}
    
    echo json_encode($tabelas);
    exit;
}

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'testar') {
        $mensagem = "🔍 Testando conexões...\n\n";
        
        try {
            conectarLocal();
            $mensagem .= "✅ Local (MySQL - XAMPP) OK!\n";
            $local_ok = true;
        } catch (Exception $e) {
            $mensagem .= "❌ Local: " . $e->getMessage() . "\n";
        }
        
        try {
            conectarPublico();
            $mensagem .= "✅ Público (PostgreSQL - Neon.tech) OK!";
            $publico_ok = true;
        } catch (Exception $e) {
            $mensagem .= "❌ Público: " . $e->getMessage();
        }
        $tipo_mensagem = 'info';
    }
    
    if ($acao === 'sincronizar') {
        $tabela = $_POST['tabela'] ?? '';
        $direcao = $_POST['direcao'] ?? 'local_para_publico';
        $colunas_selecionadas = isset($_POST['colunas']) ? array_filter($_POST['colunas']) : null;
        $filtros = $_POST['filtros'] ?? [];
        $limpar = isset($_POST['limpar_destino']) ? true : false;
        
        if ($tabela) {
            try {
                $filtros = array_filter($filtros, function($v) {
                    return $v !== '';
                });
                
                if ($direcao === 'publico_para_local') {
                    $resultado = sincronizarPublicoParaLocal($tabela, $colunas_selecionadas, $filtros, $limpar);
                } else {
                    $resultado = sincronizarLocalParaPublico($tabela, $colunas_selecionadas, $filtros, $limpar);
                }
                
                if ($resultado['success']) {
                    $mensagem = "✅ Sincronização concluída!\n";
                    $mensagem .= "📊 Total: " . ($resultado['total'] ?? 0) . " registros\n";
                    $mensagem .= "📥 Inseridos: " . ($resultado['inseridos'] ?? 0) . "\n";
                    $mensagem .= "🔄 Atualizados: " . ($resultado['atualizados'] ?? 0);
                    if ($limpar) {
                        $mensagem .= "\n🗑️ Modo limpeza ativado";
                    }
                    if (!empty($resultado['erros'])) {
                        $mensagem .= "\n\n⚠️ " . count($resultado['erros']) . " erros encontrados.";
                    }
                    $tipo_mensagem = 'success';
                } else {
                    $mensagem = "❌ " . $resultado['message'];
                    $tipo_mensagem = 'error';
                }
            } catch (Exception $e) {
                $mensagem = "❌ Erro: " . $e->getMessage();
                $tipo_mensagem = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronização de Banco - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #1a2332, #2c3e50); color: white; padding: 20px 30px; border-radius: 12px; margin-bottom: 25px; }
        .header h1 { font-size: 28px; }
        .header h1 span { color: #f5d76e; }
        .header p { color: #94a3b8; margin-top: 5px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; }
        .card h2 { font-size: 20px; color: #1a2332; margin-bottom: 20px; border-bottom: 2px solid #f5d76e; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 14px; color: #1a2332; margin-bottom: 5px; }
        .form-group select, .form-group input { width: 100%; padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        .form-group select:focus, .form-group input:focus { border-color: #c9a84c; outline: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn { padding: 10px 25px; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; }
        .btn-success:hover { box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3); }
        .btn-primary { background: linear-gradient(135deg, #c9a84c, #f5d76e); color: #1a2332; }
        .btn-primary:hover { box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3); }
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; }
        .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; white-space: pre-line; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .status-item { padding: 15px 20px; border-radius: 8px; border-left: 4px solid; }
        .status-item.online { background: #d1fae5; border-color: #2ecc71; }
        .status-item.offline { background: #fee2e2; border-color: #e74c3c; }
        .status-item .label { font-size: 12px; text-transform: uppercase; font-weight: 600; }
        .status-item .value { font-size: 16px; font-weight: 700; }
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 5px; max-height: 150px; overflow-y: auto; padding: 10px; border: 1px solid #eef2f7; border-radius: 8px; background: white; margin-top: 5px; }
        .checkbox-grid label { display: flex; align-items: center; gap: 5px; font-size: 13px; padding: 3px 5px; cursor: pointer; }
        .checkbox-grid label:hover { background: #f8fafc; border-radius: 4px; }
        .checkbox-grid input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
        .filtros-container { background: #f8fafc; padding: 15px; border-radius: 8px; margin-top: 10px; border: 1px solid #eef2f7; }
        .filtro-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 10px; }
        .btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 6px; border: none; cursor: pointer; transition: all 0.3s; }
        .btn-sm-primary { background: #c9a84c; color: #1a2332; }
        .btn-sm-secondary { background: #e2e8f0; color: #1a2332; }
        .loading { display: inline-block; width: 16px; height: 16px; border: 2px solid #f3f3f3; border-top: 2px solid #c9a84c; border-radius: 50%; animation: spin 0.8s linear infinite; margin-left: 10px; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .resultado-box { background: #f8fafc; padding: 15px; border-radius: 8px; margin-top: 15px; border: 1px solid #e2e8f0; }
        .resultado-box .numero { font-size: 32px; font-weight: 700; color: #c9a84c; }
        .resultado-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-top: 10px; }
        @media (max-width: 768px) { .form-row, .status-grid, .filtro-row { grid-template-columns: 1fr; } }
        
        /* Estilo para o ícone de origem */
        .origin-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        .origin-badge.mysql { background: #dbeafe; color: #1e40af; }
        .origin-badge.postgres { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔄 <span>Sincronização de Banco de Dados</span></h1>
        <p>MySQL Local (XAMPP) ↔ PostgreSQL Público (Neon.tech)</p>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_mensagem ?>"><?= nl2br(htmlspecialchars($mensagem)) ?></div>
    <?php endif; ?>
    
    <div class="card">
        <h2>🔌 Status das Conexões</h2>
        <div class="status-grid">
            <div class="status-item <?= $local_ok ? 'online' : 'offline' ?>">
                <div class="label">📀 Local (MySQL - XAMPP)</div>
                <div class="value"><?= $local_ok ? '✅ Online' : '❌ Offline' ?></div>
                <div style="font-size: 12px; margin-top: 5px;"><?= DB_LOCAL_HOST ?>/<?= DB_LOCAL_NAME ?></div>
            </div>
            <div class="status-item <?= $publico_ok ? 'online' : 'offline' ?>">
                <div class="label">☁️ Público (PostgreSQL - Neon.tech)</div>
                <div class="value"><?= $publico_ok ? '✅ Online' : '❌ Offline' ?></div>
                <div style="font-size: 12px; margin-top: 5px;"><?= DB_PUBLIC_HOST ?></div>
            </div>
        </div>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="acao" value="testar">
            <button type="submit" class="btn btn-primary">🔍 Testar Conexões</button>
        </form>
    </div>
    
    <div class="card">
        <h2>🔄 Sincronizar Dados</h2>
        
        <div style="background: #dbeafe; padding: 12px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #3498db; font-size: 13px; color: #1e40af;">
            💡 <strong>Dica:</strong> A direção da sincronização determina de onde as tabelas serão carregadas.
            <br>📅 <strong>Tratamento:</strong> Datas inválidas (0000-00-00) serão convertidas para 1970-01-01
        </div>
        
        <form method="POST" id="formSincronizar">
            <input type="hidden" name="acao" value="sincronizar">
            
            <div class="form-row">
                <div class="form-group">
                    <label>🔄 Direção</label>
                    <select name="direcao" id="direcaoSelect" required onchange="carregarTabelas()">
                        <option value="local_para_publico">💻 Local → Público</option>
                        <option value="publico_para_local">☁️ Público → Local</option>
                    </select>
                    <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                        <span id="origemLabel">📀 Origem: MySQL Local</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>📋 Tabela</label>
                    <select name="tabela" id="tabelaSelect" required onchange="carregarColunas()">
                        <option value="">Carregando tabelas...</option>
                    </select>
                    <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                        <span id="tabelasCount">0 tabelas disponíveis</span>
                    </div>
                </div>
            </div>
            
            <div class="selecao-colunas" id="colunasContainer" style="display: none;">
                <div class="form-group">
                    <label>📋 Selecionar Colunas</label>
                    <div style="display: flex; gap: 10px; margin-bottom: 5px; flex-wrap: wrap;">
                        <button type="button" class="btn-sm btn-sm-primary" onclick="selecionarTodasColunas(true)">✅ Selecionar Todas</button>
                        <button type="button" class="btn-sm btn-sm-secondary" onclick="selecionarTodasColunas(false)">❌ Desmarcar Todas</button>
                    </div>
                    <div id="colunasCheckboxes" class="checkbox-grid"></div>
                </div>
            </div>
            
            <div id="filtrosContainer" style="display: none;">
                <div class="filtros-container">
                    <label style="font-weight: 600; font-size: 14px;">🔍 Filtros (opcional)</label>
                    <div id="filtrosCampos" class="filtro-row"></div>
                </div>
            </div>
            
            <div style="margin-top: 15px; display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                    <input type="checkbox" name="limpar_destino" value="1">
                    🗑️ Limpar dados no destino antes de importar
                </label>
            </div>
            
            <div style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-top: 10px; font-size: 13px; color: #64748b;">
                ⚠️ <strong>Atenção:</strong> A sincronização irá sobrescrever os dados no destino com os dados da origem.
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-success" <?= (!$local_ok || !$publico_ok) ? 'disabled' : '' ?>>
                    🚀 Sincronizar
                </button>
                <span id="loadingIndicator" style="display: none;">
                    <span class="loading"></span> Sincronizando...
                </span>
            </div>
        </form>
        
        <?php if ($resultado && $resultado['success']): ?>
            <div class="resultado-box">
                <h4 style="color: #1a2332; margin-bottom: 10px;">📊 Resultado da Sincronização</h4>
                <div class="resultado-grid">
                    <div style="text-align: center;">
                        <div class="numero"><?= $resultado['total'] ?? 0 ?></div>
                        <div style="color: #94a3b8; font-size: 14px;">Total</div>
                    </div>
                    <div style="text-align: center;">
                        <div class="numero" style="color: #2ecc71;"><?= $resultado['inseridos'] ?? 0 ?></div>
                        <div style="color: #94a3b8; font-size: 14px;">Inseridos</div>
                    </div>
                    <div style="text-align: center;">
                        <div class="numero" style="color: #f39c12;"><?= $resultado['atualizados'] ?? 0 ?></div>
                        <div style="color: #94a3b8; font-size: 14px;">Atualizados</div>
                    </div>
                </div>
                <?php if (!empty($resultado['erros'])): ?>
                    <div style="background: #fee2e2; padding: 10px; border-radius: 6px; margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #fecaca;">
                        <strong style="color: #991b1b;">⚠️ Erros encontrados:</strong>
                        <?php foreach ($resultado['erros'] as $erro): ?>
                            <div style="color: #991b1b; font-size: 13px; padding: 3px 0; border-bottom: 1px solid #fee2e2;">• <?= htmlspecialchars($erro) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Carregar tabelas baseado na direção selecionada
function carregarTabelas() {
    const direcao = document.getElementById('direcaoSelect').value;
    const tabelaSelect = document.getElementById('tabelaSelect');
    const tabelasCount = document.getElementById('tabelasCount');
    const origemLabel = document.getElementById('origemLabel');
    
    // Atualizar label de origem
    if (direcao === 'publico_para_local') {
        origemLabel.innerHTML = '☁️ Origem: PostgreSQL Público';
    } else {
        origemLabel.innerHTML = '📀 Origem: MySQL Local';
    }
    
    // Mostrar loading
    tabelaSelect.innerHTML = '<option value="">⏳ Carregando tabelas...</option>';
    tabelasCount.textContent = 'Carregando...';
    
    // Buscar tabelas via AJAX
    fetch('?action=get_tabelas&direcao=' + encodeURIComponent(direcao))
        .then(response => response.json())
        .then(tabelas => {
            if (tabelas.length === 0) {
                tabelaSelect.innerHTML = '<option value="">⚠️ Nenhuma tabela encontrada</option>';
                tabelasCount.textContent = '0 tabelas disponíveis';
                return;
            }
            
            let options = '<option value="">Selecione uma tabela...</option>';
            tabelas.forEach(tabela => {
                options += `<option value="${tabela}">${tabela}</option>`;
            });
            tabelaSelect.innerHTML = options;
            tabelasCount.textContent = tabelas.length + ' tabelas disponíveis';
            
            // Carregar colunas da primeira tabela se disponível
            if (tabelas.length > 0) {
                tabelaSelect.value = tabelas[0];
                carregarColunas();
            }
        })
        .catch(error => {
            tabelaSelect.innerHTML = '<option value="">❌ Erro ao carregar tabelas</option>';
            tabelasCount.textContent = 'Erro ao carregar';
            console.error('Erro:', error);
        });
}

// Carregar colunas da tabela selecionada
function carregarColunas() {
    const tabela = document.getElementById('tabelaSelect').value;
    const direcao = document.getElementById('direcaoSelect').value;
    const container = document.getElementById('colunasContainer');
    const filtrosContainer = document.getElementById('filtrosContainer');
    const colunasDiv = document.getElementById('colunasCheckboxes');
    const filtrosDiv = document.getElementById('filtrosCampos');
    
    if (!tabela) {
        container.style.display = 'none';
        filtrosContainer.style.display = 'none';
        return;
    }
    
    colunasDiv.innerHTML = '<div style="padding: 10px; text-align: center; color: #94a3b8;">⏳ Carregando colunas...</div>';
    container.style.display = 'block';
    
    fetch('?action=get_colunas&tabela=' + encodeURIComponent(tabela) + '&direcao=' + encodeURIComponent(direcao))
        .then(response => response.json())
        .then(colunas => {
            if (colunas.length === 0) {
                colunasDiv.innerHTML = '<div style="padding: 10px; text-align: center; color: #94a3b8;">⚠️ Nenhuma coluna encontrada</div>';
                return;
            }
            
            let html = '';
            colunas.forEach(col => {
                html += `<label>
                    <input type="checkbox" name="colunas[]" value="${col}" checked>
                    ${col}
                </label>`;
            });
            colunasDiv.innerHTML = html;
            
            // Filtros
            const colunasFiltro = colunas.filter(c => !['id', 'created_at', 'updated_at'].includes(c)).slice(0, 6);
            if (colunasFiltro.length > 0) {
                let filtrosHtml = '';
                colunasFiltro.forEach(col => {
                    filtrosHtml += `<div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 12px; font-weight: 500; color: #64748b;">${col}</label>
                        <input type="text" name="filtros[${col}]" placeholder="Valor" style="width: 100%; padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;">
                    </div>`;
                });
                filtrosDiv.innerHTML = filtrosHtml;
                filtrosContainer.style.display = 'block';
            } else {
                filtrosContainer.style.display = 'none';
            }
        })
        .catch(error => {
            colunasDiv.innerHTML = '<div style="padding: 10px; text-align: center; color: #e74c3c;">❌ Erro ao carregar colunas</div>';
            console.error('Erro:', error);
        });
}

function selecionarTodasColunas(selecionar) {
    document.querySelectorAll('#colunasCheckboxes input[type="checkbox"]').forEach(cb => cb.checked = selecionar);
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    // Carregar tabelas ao iniciar
    carregarTabelas();
});

// Ao mudar a direção, recarregar tabelas
document.getElementById('direcaoSelect').addEventListener('change', function() {
    carregarTabelas();
});

// Ao mudar a tabela, carregar colunas
document.getElementById('tabelaSelect').addEventListener('change', function() {
    carregarColunas();
});

document.getElementById('formSincronizar').addEventListener('submit', function() {
    document.getElementById('loadingIndicator').style.display = 'inline';
});
</script>

</body>
</html>