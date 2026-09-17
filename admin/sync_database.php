<?php
// admin/sync_database.php
// Sincronização de Banco de Dados - COM BACKUP AUTOMÁTICO (ARQUIVO ÚNICO)

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0); // Permite execução longa

// ============================================
// CONFIGURAÇÕES
// ============================================

// Local (MySQL - XAMPP) - SEM SENHA
define('DB_LOCAL_HOST', 'localhost');
define('DB_LOCAL_PORT', '3306');
define('DB_LOCAL_NAME', 'softgest_db');
define('DB_LOCAL_USER', 'root');
define('DB_LOCAL_PASS', '');  // SENHA REMOVIDA!

// Público (PostgreSQL - Neon.tech)
define('DB_PUBLIC_HOST', 'ep-holy-resonance-atm1ick2-pooler.c-9.us-east-1.aws.neon.tech');
define('DB_PUBLIC_PORT', '5432');
define('DB_PUBLIC_NAME', 'neondb');
define('DB_PUBLIC_USER', 'neondb_owner');
define('DB_PUBLIC_PASS', 'npg_gRkKHXNJAI40');
define('DB_PUBLIC_ENDPOINT', 'ep-holy-resonance-atm1ick2-pooler');

// ============================================
// CONFIGURAÇÕES DE BACKUP
// ============================================

// Nome fixo do arquivo de backup (sempre o mesmo)
define('BACKUP_FILE_NAME', 'softgest_backup.sql');

// Pastas de backup (caminhos absolutos)
define('BACKUP_FOLDER_DOCUMENTS', 'C:/Users/' . getenv('USERNAME') . '/Documents/SoftGest_Backups/');
define('BACKUP_FOLDER_ONEDRIVE', 'C:/Users/' . getenv('USERNAME') . '/OneDrive/Documents/SoftGest_Backups/');

// ============================================
// FUNÇÕES DE CONEXÃO
// ============================================

function conectarLocal() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_LOCAL_HOST . ";dbname=" . DB_LOCAL_NAME . ";charset=utf8mb4",
            DB_LOCAL_USER,
            DB_LOCAL_PASS,  // Agora está vazio ''
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
// FUNÇÕES DE BACKUP (ARQUIVO ÚNICO)
// ============================================

function criarPastasBackup() {
    $pastas = [
        BACKUP_FOLDER_DOCUMENTS,
        BACKUP_FOLDER_ONEDRIVE
    ];
    
    foreach ($pastas as $pasta) {
        if (!file_exists($pasta)) {
            mkdir($pasta, 0755, true);
        }
    }
}

function realizarBackupCompleto() {
    criarPastasBackup();
    
    try {
        $pdo = conectarLocal();
        
        // Buscar todas as tabelas
        $stmt = $pdo->query("SHOW TABLES");
        $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tabelas)) {
            return ['success' => false, 'message' => 'Nenhuma tabela encontrada'];
        }
        
        // Construir SQL de backup
        $sqlBackup = "-- ============================================\n";
        $sqlBackup .= "-- SOFTGEST - BACKUP COMPLETO\n";
        $sqlBackup .= "-- Última atualização: " . date('Y-m-d H:i:s') . "\n";
        $sqlBackup .= "-- ============================================\n\n";
        $sqlBackup .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sqlBackup .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        $sqlBackup .= "SET AUTOCOMMIT=0;\n";
        $sqlBackup .= "START TRANSACTION;\n\n";
        
        $totalRegistros = 0;
        
        foreach ($tabelas as $tabela) {
            // Estrutura da tabela
            $stmt = $pdo->query("SHOW CREATE TABLE `$tabela`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $createTable = $row['Create Table'];
            
            $sqlBackup .= "-- --------------------------------------------------------\n";
            $sqlBackup .= "-- Estrutura da tabela: $tabela\n";
            $sqlBackup .= "-- --------------------------------------------------------\n\n";
            $sqlBackup .= "DROP TABLE IF EXISTS `$tabela`;\n";
            $sqlBackup .= $createTable . ";\n\n";
            
            // Dados da tabela
            $stmt = $pdo->query("SELECT * FROM `$tabela`");
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($dados)) {
                $sqlBackup .= "-- --------------------------------------------------------\n";
                $sqlBackup .= "-- Dados da tabela: $tabela (" . count($dados) . " registros)\n";
                $sqlBackup .= "-- --------------------------------------------------------\n\n";
                
                // Obter colunas
                $colunas = array_keys($dados[0]);
                $colunasStr = "`" . implode("`, `", $colunas) . "`";
                
                $sqlBackup .= "INSERT INTO `$tabela` ($colunasStr) VALUES\n";
                
                $valores = [];
                foreach ($dados as $row) {
                    $valoresLinha = [];
                    foreach ($row as $valor) {
                        if ($valor === null) {
                            $valoresLinha[] = 'NULL';
                        } elseif (is_numeric($valor)) {
                            $valoresLinha[] = $valor;
                        } else {
                            $valoresLinha[] = "'" . addslashes($valor) . "'";
                        }
                    }
                    $valores[] = "(" . implode(", ", $valoresLinha) . ")";
                    $totalRegistros++;
                }
                
                $sqlBackup .= implode(",\n", $valores) . ";\n\n";
            }
        }
        
        $sqlBackup .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $sqlBackup .= "COMMIT;\n";
        
        // Salvar nos dois locais com o MESMO nome (sobrescrevendo)
        $arquivosSalvos = [];
        $erros = [];
        
        // Salvar em Documentos (sobrescreve)
        $caminhoDocs = BACKUP_FOLDER_DOCUMENTS . BACKUP_FILE_NAME;
        if (file_put_contents($caminhoDocs, $sqlBackup)) {
            $arquivosSalvos[] = $caminhoDocs;
        } else {
            $erros[] = "Não foi possível salvar em: " . BACKUP_FOLDER_DOCUMENTS;
        }
        
        // Salvar em OneDrive (sobrescreve)
        $caminhoOnedrive = BACKUP_FOLDER_ONEDRIVE . BACKUP_FILE_NAME;
        if (file_put_contents($caminhoOnedrive, $sqlBackup)) {
            $arquivosSalvos[] = $caminhoOnedrive;
        } else {
            $erros[] = "Não foi possível salvar em: " . BACKUP_FOLDER_ONEDRIVE;
        }
        
        return [
            'success' => true,
            'message' => 'Backup atualizado com sucesso!',
            'arquivos' => $arquivosSalvos,
            'tabelas' => count($tabelas),
            'registros' => $totalRegistros,
            'arquivo' => BACKUP_FILE_NAME,
            'tamanho' => round(strlen($sqlBackup) / 1024 / 1024, 2) . ' MB',
            'data_hora' => date('Y-m-d H:i:s'),
            'erros' => $erros
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro no backup: ' . $e->getMessage()];
    }
}

function listarBackups() {
    $backups = [];
    $pastas = [
        'Documentos' => BACKUP_FOLDER_DOCUMENTS,
        'OneDrive' => BACKUP_FOLDER_ONEDRIVE
    ];
    
    foreach ($pastas as $nome => $pasta) {
        if (!file_exists($pasta)) continue;
        
        $arquivo = $pasta . BACKUP_FILE_NAME;
        if (file_exists($arquivo)) {
            $backups[] = [
                'nome' => BACKUP_FILE_NAME,
                'caminho' => $arquivo,
                'pasta' => $nome,
                'tamanho' => round(filesize($arquivo) / 1024 / 1024, 2) . ' MB',
                'data' => date('Y-m-d H:i:s', filemtime($arquivo)),
                'extensao' => 'sql'
            ];
        }
    }
    
    return $backups;
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
    if ($valor === null || $valor === '') {
        if ($coluna === 'data_nascimento') {
            return '1970-01-01';
        }
        return null;
    }
    
    if ($valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
        if ($coluna === 'data_nascimento') {
            return '1970-01-01';
        }
        return null;
    }
    
    $timestamp = strtotime($valor);
    if ($timestamp === false || $timestamp < 0) {
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
        
        $tipo_pg = converterTipoMySQLparaPostgres($tipo);
        
        if ($extra === 'auto_increment') {
            $tipo_pg = 'SERIAL PRIMARY KEY';
            $nulo = '';
            $padrao = null;
        }
        
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
    
    $tabelas_publico = listarTabelasPostgres($publico);
    if (!in_array($tabela, $tabelas_publico)) {
        $resultado = criarTabelaPostgres($tabela);
        if (!$resultado['success']) {
            return $resultado;
        }
    }
    
    if (empty($colunas_selecionadas)) {
        $colunas = listarColunasMySQL($local, $tabela);
    } else {
        $colunas = $colunas_selecionadas;
    }
    
    if (empty($colunas)) {
        return ['success' => false, 'message' => "Nenhuma coluna encontrada para a tabela '$tabela'"];
    }
    
    $where = [];
    $params = [];
    foreach ($filtros as $campo => $valor) {
        if (!empty($valor) && in_array($campo, $colunas)) {
            $where[] = "$campo = ?";
            $params[] = $valor;
        }
    }
    
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
    
    if ($limpar) {
        $publico->exec("TRUNCATE TABLE $tabela");
    }
    
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
        $row_processado = [];
        foreach ($colunas as $col) {
            $valor = $row[$col];
            
            if ($col === 'data_nascimento') {
                $valor = tratarData($valor, 'data_nascimento');
            } elseif (strpos($col, 'data') !== false || strpos($col, 'date') !== false) {
                $valor = tratarData($valor);
            }
            
            if ($valor === null && $col === 'data_nascimento') {
                $valor = '1970-01-01';
            }
            
            $row_processado[$col] = $valor;
        }
        
        try {
            $stmt_check = $publico->prepare("SELECT COUNT(*) FROM $tabela WHERE $pk_coluna = ?");
            $stmt_check->execute([$row_processado[$pk_coluna] ?? null]);
            $existe = $stmt_check->fetchColumn();
            
            if ($existe) {
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
    
    $tabelas_publico = listarTabelasPostgres($publico);
    if (!in_array($tabela, $tabelas_publico)) {
        return ['success' => false, 'message' => "Tabela '$tabela' não encontrada no PostgreSQL"];
    }
    
    if (empty($colunas_selecionadas)) {
        $colunas = listarColunasPostgres($publico, $tabela);
    } else {
        $colunas = $colunas_selecionadas;
    }
    
    if (empty($colunas)) {
        return ['success' => false, 'message' => "Nenhuma coluna encontrada para a tabela '$tabela'"];
    }
    
    $where = [];
    $params = [];
    foreach ($filtros as $campo => $valor) {
        if (!empty($valor) && in_array($campo, $colunas)) {
            $where[] = "$campo = ?";
            $params[] = $valor;
        }
    }
    
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
    
    if ($limpar) {
        $local->exec("TRUNCATE TABLE $tabela");
    }
    
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
            $stmt_check = $local->prepare("SELECT COUNT(*) FROM $tabela WHERE $pk_coluna = ?");
            $stmt_check->execute([$row[$pk_coluna] ?? null]);
            $existe = $stmt_check->fetchColumn();
            
            if ($existe) {
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
$backup_resultado = null;
$backup_automatico_ativo = false;

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
            $publico = conectarPublico();
            $colunas = listarColunasPostgres($publico, $tabela);
        } else {
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
            $publico = conectarPublico();
            $tabelas = listarTabelasPostgres($publico);
        } else {
            $local = conectarLocal();
            $tabelas = listarTabelasMySQL($local);
        }
    } catch (Exception $e) {}
    
    echo json_encode($tabelas);
    exit;
}

// AJAX para listar backups
if (isset($_GET['action']) && $_GET['action'] === 'listar_backups') {
    header('Content-Type: application/json');
    echo json_encode(listarBackups());
    exit;
}

// AJAX para backup automático
if (isset($_GET['action']) && $_GET['action'] === 'backup_auto') {
    header('Content-Type: application/json');
    $resultado = realizarBackupCompleto();
    echo json_encode($resultado);
    exit;
}

// AJAX para download do backup
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $pasta = $_GET['pasta'] ?? '';
    $arquivo = BACKUP_FILE_NAME;
    
    if ($pasta === 'Documentos') {
        $caminho = BACKUP_FOLDER_DOCUMENTS . $arquivo;
    } elseif ($pasta === 'OneDrive') {
        $caminho = BACKUP_FOLDER_ONEDRIVE . $arquivo;
    } else {
        die('Pasta inválida');
    }
    
    if (file_exists($caminho)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
        exit;
    } else {
        die('Arquivo não encontrado');
    }
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
    
    if ($acao === 'backup_manual') {
        $backup_resultado = realizarBackupCompleto();
        if ($backup_resultado['success']) {
            $mensagem = "✅ " . $backup_resultado['message'] . "\n";
            $mensagem .= "📊 Tabelas: " . $backup_resultado['tabelas'] . "\n";
            $mensagem .= "📊 Registros: " . $backup_resultado['registros'] . "\n";
            $mensagem .= "📦 Tamanho: " . $backup_resultado['tamanho'] . "\n";
            $mensagem .= "📁 Arquivo: " . $backup_resultado['arquivo'] . " (atualizado)\n";
            $mensagem .= "🕐 Última atualização: " . $backup_resultado['data_hora'];
            if (!empty($backup_resultado['erros'])) {
                $mensagem .= "\n\n⚠️ Erros: " . implode("\n", $backup_resultado['erros']);
            }
            $tipo_mensagem = 'success';
        } else {
            $mensagem = "❌ " . $backup_resultado['message'];
            $tipo_mensagem = 'error';
        }
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
        .btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
        .btn-danger:hover { box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3); }
        .btn-warning { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
        .btn-warning:hover { box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3); }
        .btn-secondary { background: #e2e8f0; color: #1a2332; }
        .btn-secondary:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
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
        
        /* Backup Styles */
        .backup-status {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .backup-status .indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        .backup-status .indicator.active { background: #2ecc71; animation: pulse 1s infinite; }
        .backup-status .indicator.inactive { background: #94a3b8; }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        .backup-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid #eef2f7;
            font-size: 13px;
        }
        .backup-item:hover { background: #f8fafc; }
        .backup-item .nome { font-weight: 500; color: #1a2332; }
        .backup-item .info { color: #94a3b8; font-size: 12px; }
        .backup-item .download-btn {
            padding: 3px 10px;
            background: #c9a84c;
            color: #1a2332;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
        }
        .backup-item .download-btn:hover { background: #b8952e; }
        
        .backup-info {
            font-size: 13px;
            color: #64748b;
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #eef2f7;
            margin-top: 10px;
        }
        .backup-info strong { color: #1a2332; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔄 <span>Sincronização de Banco de Dados</span></h1>
        <p>MySQL Local (XAMPP) ↔ PostgreSQL Público (Neon.tech) | Backup Automático (Arquivo Único)</p>
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
    
    <!-- ============================================ -->
    <!-- CARD DE BACKUP AUTOMÁTICO (ARQUIVO ÚNICO)    -->
    <!-- ============================================ -->
    <div class="card">
        <h2>💾 Backup Automático <span style="font-size: 14px; color: #94a3b8; font-weight: normal;">(arquivo único sempre atualizado)</span></h2>
        
        <div class="backup-status">
            <div>
                <span class="indicator <?= $backup_automatico_ativo ? 'active' : 'inactive' ?>" id="backupIndicator"></span>
                <span id="backupStatusText" style="font-weight: 600;">
                    <?= $backup_automatico_ativo ? '🔴 Backup automático ATIVO' : '⚪ Backup automático DESATIVADO' ?>
                </span>
            </div>
            <div style="font-size: 13px; color: #64748b;">
                ⏱️ Última atualização: <span id="ultimoBackup"><?= $backup_resultado && $backup_resultado['success'] ? date('H:i:s') : 'Nunca' ?></span>
            </div>
            <div style="font-size: 13px; color: #64748b;">
                📁 Arquivo: <strong>softgest_backup.sql</strong>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
            <button id="btnBackupAuto" class="btn <?= $backup_automatico_ativo ? 'btn-danger' : 'btn-success' ?>" onclick="toggleBackupAuto()">
                <?= $backup_automatico_ativo ? '⏹️ Parar Backup Automático' : '▶️ Iniciar Backup Automático' ?>
            </button>
            <button class="btn btn-primary" onclick="executarBackupManual()">
                📥 Backup Agora
            </button>
            <button class="btn btn-secondary" onclick="carregarListaBackups()">
                🔄 Atualizar
            </button>
        </div>
        
        <div id="backupResultado" style="display: none;" class="alert"></div>
        
        <div class="backup-info">
            💡 O backup é salvo como <strong>softgest_backup.sql</strong> em ambas as pastas:
            <br>📂 <strong>Documentos:</strong> <?= BACKUP_FOLDER_DOCUMENTS ?>
            <br>☁️ <strong>OneDrive:</strong> <?= BACKUP_FOLDER_ONEDRIVE ?>
            <br>⏱️ O arquivo é <strong>sobrescrito</strong> a cada 5 segundos mantendo sempre a versão mais recente.
        </div>
        
        <div style="margin-top: 15px;">
            <h4 style="color: #1a2332; margin-bottom: 10px;">📋 Backup Atual</h4>
            <div id="listaBackups" class="backup-list">
                <div style="padding: 10px; text-align: center; color: #94a3b8;">Carregando informações do backup...</div>
            </div>
        </div>
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
// ============================================
// VARIÁVEIS DE BACKUP AUTOMÁTICO
// ============================================
let backupAutoInterval = null;
let backupAutoAtivo = <?= $backup_automatico_ativo ? 'true' : 'false' ?>;

// ============================================
// FUNÇÕES DE BACKUP (ARQUIVO ÚNICO)
// ============================================

function toggleBackupAuto() {
    if (backupAutoAtivo) {
        pararBackupAuto();
    } else {
        iniciarBackupAuto();
    }
}

function iniciarBackupAuto() {
    if (backupAutoInterval) {
        clearInterval(backupAutoInterval);
    }
    
    backupAutoAtivo = true;
    document.getElementById('backupIndicator').className = 'indicator active';
    document.getElementById('backupStatusText').textContent = '🔴 Backup automático ATIVO';
    document.getElementById('btnBackupAuto').textContent = '⏹️ Parar Backup Automático';
    document.getElementById('btnBackupAuto').className = 'btn btn-danger';
    
    // Executar primeiro backup imediatamente
    executarBackupAuto();
    
    // Configurar intervalo de 5 segundos
    backupAutoInterval = setInterval(function() {
        executarBackupAuto();
    }, 5000);
}

function pararBackupAuto() {
    if (backupAutoInterval) {
        clearInterval(backupAutoInterval);
        backupAutoInterval = null;
    }
    
    backupAutoAtivo = false;
    document.getElementById('backupIndicator').className = 'indicator inactive';
    document.getElementById('backupStatusText').textContent = '⚪ Backup automático DESATIVADO';
    document.getElementById('btnBackupAuto').textContent = '▶️ Iniciar Backup Automático';
    document.getElementById('btnBackupAuto').className = 'btn btn-success';
}

function executarBackupAuto() {
    fetch('?action=backup_auto')
        .then(response => response.json())
        .then(data => {
            const el = document.getElementById('ultimoBackup');
            if (data.success) {
                el.textContent = new Date().toLocaleTimeString();
                // Atualizar lista de backups
                carregarListaBackups();
                
                // Mostrar no console para debug
                console.log('✅ Backup atualizado:', data.data_hora, '| Registros:', data.registros);
            } else {
                el.textContent = '⚠️ Erro';
                console.error('Erro no backup automático:', data.message);
            }
        })
        .catch(error => {
            console.error('Erro no backup automático:', error);
        });
}

function executarBackupManual() {
    const btn = document.querySelector('button[onclick="executarBackupManual()"]');
    const originalText = btn.textContent;
    btn.textContent = '⏳ Processando...';
    btn.disabled = true;
    
    const resultadoDiv = document.getElementById('backupResultado');
    resultadoDiv.style.display = 'block';
    resultadoDiv.className = 'alert alert-info';
    resultadoDiv.textContent = '⏳ Realizando backup do banco de dados completo...';
    
    fetch('?action=backup_auto')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultadoDiv.className = 'alert alert-success';
                let msg = '✅ ' + data.message + '\n';
                msg += '📊 Tabelas: ' + data.tabelas + '\n';
                msg += '📊 Registros: ' + data.registros + '\n';
                msg += '📦 Tamanho: ' + data.tamanho + '\n';
                msg += '📁 Arquivo: ' + data.arquivo + ' (atualizado)\n';
                msg += '🕐 Última atualização: ' + data.data_hora;
                if (data.erros && data.erros.length > 0) {
                    msg += '\n\n⚠️ Erros: ' + data.erros.join('\n');
                }
                resultadoDiv.textContent = msg;
                document.getElementById('ultimoBackup').textContent = new Date().toLocaleTimeString();
                carregarListaBackups();
            } else {
                resultadoDiv.className = 'alert alert-error';
                resultadoDiv.textContent = '❌ ' + data.message;
            }
            
            btn.textContent = originalText;
            btn.disabled = false;
        })
        .catch(error => {
            resultadoDiv.className = 'alert alert-error';
            resultadoDiv.textContent = '❌ Erro ao executar backup: ' + error.message;
            btn.textContent = originalText;
            btn.disabled = false;
        });
}

function carregarListaBackups() {
    const container = document.getElementById('listaBackups');
    container.innerHTML = '<div style="padding: 10px; text-align: center; color: #94a3b8;">⏳ Carregando informações...</div>';
    
    fetch('?action=listar_backups')
        .then(response => response.json())
        .then(backups => {
            if (backups.length === 0) {
                container.innerHTML = '<div style="padding: 10px; text-align: center; color: #94a3b8;">📭 Nenhum backup encontrado</div>';
                return;
            }
            
            let html = '';
            backups.forEach((backup) => {
                const statusIcon = backup.pasta === 'Documentos' ? '📂' : '☁️';
                html += `
                    <div class="backup-item">
                        <div>
                            <span class="nome">💾 ${backup.nome}</span>
                            <span class="info">${statusIcon} ${backup.pasta} • ${backup.tamanho} • ${backup.data}</span>
                        </div>
                        <a href="?action=download_backup&pasta=${encodeURIComponent(backup.pasta)}" 
                           class="download-btn" 
                           target="_blank">📥 Download</a>
                    </div>
                `;
            });
            container.innerHTML = html;
        })
        .catch(error => {
            container.innerHTML = '<div style="padding: 10px; text-align: center; color: #e74c3c;">❌ Erro ao carregar informações</div>';
            console.error('Erro:', error);
        });
}

// ============================================
// FUNÇÕES DE SINCRONIZAÇÃO (existentes)
// ============================================

function carregarTabelas() {
    const direcao = document.getElementById('direcaoSelect').value;
    const tabelaSelect = document.getElementById('tabelaSelect');
    const tabelasCount = document.getElementById('tabelasCount');
    const origemLabel = document.getElementById('origemLabel');
    
    if (direcao === 'publico_para_local') {
        origemLabel.innerHTML = '☁️ Origem: PostgreSQL Público';
    } else {
        origemLabel.innerHTML = '📀 Origem: MySQL Local';
    }
    
    tabelaSelect.innerHTML = '<option value="">⏳ Carregando tabelas...</option>';
    tabelasCount.textContent = 'Carregando...';
    
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

// ============================================
// INICIALIZAÇÃO
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    carregarTabelas();
    carregarListaBackups();
    
    <?php if ($backup_automatico_ativo): ?>
        iniciarBackupAuto();
    <?php endif; ?>
});

document.getElementById('direcaoSelect').addEventListener('change', function() {
    carregarTabelas();
});

document.getElementById('tabelaSelect').addEventListener('change', function() {
    carregarColunas();
});

document.getElementById('formSincronizar').addEventListener('submit', function() {
    document.getElementById('loadingIndicator').style.display = 'inline';
});
</script>

</body>
</html>