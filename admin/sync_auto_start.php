<?php
// ============================================
// sync_auto_start.php - Sincronização Automática
// Inicia imediatamente ao carregar a página
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
session_start();

// ============================================
// CONFIGURAÇÕES
// ============================================

// Local (MySQL - XAMPP) - SEM SENHA
define('DB_LOCAL_HOST', 'localhost');
define('DB_LOCAL_PORT', '3306');
define('DB_LOCAL_NAME', 'softgest_db');
define('DB_LOCAL_USER', 'root');
define('DB_LOCAL_PASS', '');

// Público (PostgreSQL - Neon.tech)
define('DB_PUBLIC_HOST', 'ep-holy-resonance-atm1ick2-pooler.c-9.us-east-1.aws.neon.tech');
define('DB_PUBLIC_PORT', '5432');
define('DB_PUBLIC_NAME', 'neondb');
define('DB_PUBLIC_USER', 'neondb_owner');
define('DB_PUBLIC_PASS', 'npg_gRkKHXNJAI40');
define('DB_PUBLIC_ENDPOINT', 'ep-holy-resonance-atm1ick2-pooler');

// PASTA DE DESTINO - Pega da sessão ou usa o padrão
$sync_folder_default = 'F:/Nova pasta/';
$sync_folder = isset($_SESSION['sync_folder']) ? $_SESSION['sync_folder'] : $sync_folder_default;

if (empty($sync_folder)) {
    $sync_folder = $sync_folder_default;
    $_SESSION['sync_folder'] = $sync_folder;
}

define('SYNC_FOLDER', $sync_folder);

if (!isset($_SESSION['sync_continuar'])) {
    $_SESSION['sync_continuar'] = true;
}

// Configuração de backup automático
if (!isset($_SESSION['backup_automatico'])) {
    $_SESSION['backup_automatico'] = true;
}

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

function getCurrentSyncFolder() {
    if (isset($_SESSION['sync_folder']) && !empty($_SESSION['sync_folder'])) {
        return $_SESSION['sync_folder'];
    }
    return SYNC_FOLDER;
}

// ============================================
// FUNÇÃO DE BACKUP COMPLETO - ATUALIZA ARQUIVO
// ============================================

function gerarBackupCompleto($direcao = 'local', $return_data = false) {
    $pasta_destino = getCurrentSyncFolder();
    
    if (!is_dir($pasta_destino)) {
        mkdir($pasta_destino, 0755, true);
    }
    
    $resultado = [
        'success' => true,
        'message' => '',
        'arquivo' => 'backup_completo.sql',
        'tamanho' => 0,
        'data_hora' => date('Y-m-d H:i:s'),
        'tabelas' => 0,
        'registros' => 0,
        'pasta_destino' => $pasta_destino
    ];
    
    try {
        if ($direcao === 'local') {
            $pdo = conectarLocal();
            $tabelas = listarTabelasMySQL($pdo);
            $tipo = 'mysql';
            $nome_banco = DB_LOCAL_NAME;
        } else {
            $pdo = conectarPublico();
            $tabelas = listarTabelasPostgres($pdo);
            $tipo = 'postgresql';
            $nome_banco = DB_PUBLIC_NAME;
        }
        
        if (empty($tabelas)) {
            $resultado['success'] = false;
            $resultado['message'] = 'Nenhuma tabela encontrada para backup';
            return $resultado;
        }
        
        // Nome fixo para o backup - sempre o mesmo arquivo
        $nome_arquivo = "backup_completo.sql";
        $caminho_completo = $pasta_destino . $nome_arquivo;
        
        $sql = "-- ============================================\n";
        $sql .= "-- BACKUP COMPLETO DO BANCO DE DADOS\n";
        $sql .= "-- ============================================\n";
        $sql .= "-- Banco: {$nome_banco}\n";
        $sql .= "-- Tipo: " . strtoupper($tipo) . "\n";
        $sql .= "-- Última atualização: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Host: " . ($direcao === 'local' ? DB_LOCAL_HOST : DB_PUBLIC_HOST) . "\n";
        $sql .= "-- ============================================\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        $total_registros = 0;
        $total_tabelas = 0;
        
        foreach ($tabelas as $tabela) {
            $total_tabelas++;
            
            if ($tipo === 'mysql') {
                $stmt = $pdo->query("SHOW CREATE TABLE `{$tabela}`");
                $create = $stmt->fetch(PDO::FETCH_ASSOC);
                $sql .= "-- --------------------------------------------------------\n";
                $sql .= "-- Estrutura da tabela: {$tabela}\n";
                $sql .= "-- --------------------------------------------------------\n\n";
                $sql .= "DROP TABLE IF EXISTS `{$tabela}`;\n";
                $sql .= $create['Create Table'] . ";\n\n";
                
                $stmt = $pdo->query("SELECT * FROM `{$tabela}`");
                $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($dados)) {
                    $sql .= "-- --------------------------------------------------------\n";
                    $sql .= "-- Dados da tabela: {$tabela}\n";
                    $sql .= "-- Total: " . count($dados) . " registros\n";
                    $sql .= "-- --------------------------------------------------------\n\n";
                    
                    $colunas = array_keys($dados[0]);
                    $colunas_str = "`" . implode("`, `", $colunas) . "`";
                    
                    $sql .= "INSERT INTO `{$tabela}` ({$colunas_str}) VALUES\n";
                    
                    $valores = [];
                    foreach ($dados as $row) {
                        $linha = [];
                        foreach ($row as $valor) {
                            if ($valor === null) {
                                $linha[] = 'NULL';
                            } elseif (is_numeric($valor)) {
                                $linha[] = $valor;
                            } else {
                                $valor_escapado = addslashes($valor);
                                $linha[] = "'" . $valor_escapado . "'";
                            }
                        }
                        $valores[] = "(" . implode(", ", $linha) . ")";
                    }
                    $sql .= implode(",\n", $valores) . ";\n\n";
                    
                    $total_registros += count($dados);
                } else {
                    $sql .= "-- Nenhum dado encontrado na tabela {$tabela}\n\n";
                }
            } else {
                $sql .= "-- --------------------------------------------------------\n";
                $sql .= "-- Estrutura da tabela: {$tabela}\n";
                $sql .= "-- --------------------------------------------------------\n\n";
                
                $stmt = $pdo->query("
                    SELECT 
                        column_name,
                        data_type,
                        is_nullable,
                        column_default
                    FROM information_schema.columns 
                    WHERE table_schema = 'public' 
                    AND table_name = '{$tabela}'
                    ORDER BY ordinal_position
                ");
                $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $sql .= "DROP TABLE IF EXISTS \"{$tabela}\" CASCADE;\n";
                $sql .= "CREATE TABLE \"{$tabela}\" (\n";
                $colunas_sql = [];
                foreach ($colunas as $coluna) {
                    $linha = "    \"{$coluna['column_name']}\" {$coluna['data_type']}";
                    if ($coluna['is_nullable'] === 'NO') {
                        $linha .= " NOT NULL";
                    }
                    if ($coluna['column_default'] !== null) {
                        $linha .= " DEFAULT {$coluna['column_default']}";
                    }
                    $colunas_sql[] = $linha;
                }
                $sql .= implode(",\n", $colunas_sql) . "\n);\n\n";
                
                $stmt = $pdo->query("SELECT * FROM \"{$tabela}\"");
                $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($dados)) {
                    $sql .= "-- --------------------------------------------------------\n";
                    $sql .= "-- Dados da tabela: {$tabela}\n";
                    $sql .= "-- Total: " . count($dados) . " registros\n";
                    $sql .= "-- --------------------------------------------------------\n\n";
                    
                    $colunas_nomes = array_keys($dados[0]);
                    $colunas_str = "\"" . implode("\", \"", $colunas_nomes) . "\"";
                    
                    $sql .= "INSERT INTO \"{$tabela}\" ({$colunas_str}) VALUES\n";
                    
                    $valores = [];
                    foreach ($dados as $row) {
                        $linha = [];
                        foreach ($row as $valor) {
                            if ($valor === null) {
                                $linha[] = 'NULL';
                            } elseif (is_numeric($valor)) {
                                $linha[] = $valor;
                            } else {
                                $valor_escapado = str_replace("'", "''", $valor);
                                $linha[] = "'" . $valor_escapado . "'";
                            }
                        }
                        $valores[] = "(" . implode(", ", $linha) . ")";
                    }
                    $sql .= implode(",\n", $valores) . ";\n\n";
                    
                    $total_registros += count($dados);
                } else {
                    $sql .= "-- Nenhum dado encontrado na tabela {$tabela}\n\n";
                }
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $sql .= "\n-- ============================================\n";
        $sql .= "-- FIM DO BACKUP\n";
        $sql .= "-- Total de tabelas: {$total_tabelas}\n";
        $sql .= "-- Total de registros: {$total_registros}\n";
        $sql .= "-- Última atualização: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- ============================================\n";
        
        // Sobrescrever o arquivo (atualizar)
        file_put_contents($caminho_completo, $sql);
        
        $tamanho = filesize($caminho_completo);
        
        $resultado = [
            'success' => true,
            'message' => '✅ Backup atualizado com sucesso!',
            'arquivo' => $nome_arquivo,
            'tamanho' => round($tamanho / 1024, 2) . ' KB',
            'tamanho_bytes' => $tamanho,
            'data_hora' => date('Y-m-d H:i:s'),
            'tabelas' => $total_tabelas,
            'registros' => $total_registros,
            'pasta_destino' => $pasta_destino,
            'caminho_completo' => $caminho_completo
        ];
        
    } catch (Exception $e) {
        $resultado = [
            'success' => false,
            'message' => '❌ Erro ao gerar backup: ' . $e->getMessage()
        ];
    }
    
    if ($return_data) {
        return $resultado;
    }
    
    $backup_info_file = $pasta_destino . 'ultimo_backup.json';
    file_put_contents($backup_info_file, json_encode($resultado, JSON_PRETTY_PRINT));
    
    return $resultado;
}

// ============================================
// FUNÇÃO DE SINCRONIZAÇÃO - ATUALIZA ARQUIVOS
// ============================================

function sincronizarDinamico($direcao = 'local_para_publico') {
    $pasta_destino = getCurrentSyncFolder();
    $backup_automatico = $_SESSION['backup_automatico'] ?? true;
    
    $resultados = [
        'success' => true,
        'message' => '',
        'tabelas' => [],
        'total_registros' => 0,
        'data_hora' => date('Y-m-d H:i:s'),
        'pasta_destino' => $pasta_destino,
        'continuar' => $_SESSION['sync_continuar'] ?? true,
        'backup_gerado' => false
    ];
    
    try {
        if (!is_dir($pasta_destino)) {
            mkdir($pasta_destino, 0755, true);
        }
        
        if ($direcao === 'local_para_publico') {
            $origem = conectarLocal();
            $destino = conectarPublico();
            $tabelas = listarTabelasMySQL($origem);
        } else {
            $origem = conectarPublico();
            $destino = conectarLocal();
            $tabelas = listarTabelasPostgres($origem);
        }
        
        // Verificar se já existe um arquivo de status para saber se é a primeira execução
        $status_file = $pasta_destino . 'status.json';
        $primeira_execucao = !file_exists($status_file);
        
        foreach ($tabelas as $tabela) {
            try {
                $stmt = $origem->query("SELECT * FROM $tabela");
                $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($dados)) {
                    continue;
                }
                
                // JSON - SEMPRE SOBRESCREVER (ATUALIZAR)
                $arquivo_json = $pasta_destino . $tabela . '.json';
                file_put_contents($arquivo_json, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                // CSV - SEMPRE SOBRESCREVER (ATUALIZAR)
                $arquivo_csv = $pasta_destino . $tabela . '.csv';
                $csv = fopen($arquivo_csv, 'w');
                if (!empty($dados)) {
                    fputcsv($csv, array_keys($dados[0]));
                    foreach ($dados as $linha) {
                        fputcsv($csv, $linha);
                    }
                }
                fclose($csv);
                
                // SQL individual - SEMPRE SOBRESCREVER (ATUALIZAR)
                $arquivo_sql = $pasta_destino . $tabela . '.sql';
                $sql = "-- Tabela: $tabela\n";
                $sql .= "-- Registros: " . count($dados) . "\n";
                $sql .= "-- Última atualização: " . date('Y-m-d H:i:s') . "\n\n";
                $sql .= "INSERT INTO $tabela VALUES\n";
                $valores = [];
                foreach ($dados as $row) {
                    $linha = [];
                    foreach ($row as $valor) {
                        if ($valor === null) {
                            $linha[] = 'NULL';
                        } elseif (is_numeric($valor)) {
                            $linha[] = $valor;
                        } else {
                            $linha[] = "'" . addslashes($valor) . "'";
                        }
                    }
                    $valores[] = "(" . implode(", ", $linha) . ")";
                }
                $sql .= implode(",\n", $valores) . ";\n";
                file_put_contents($arquivo_sql, $sql);
                
                $resultados['tabelas'][] = [
                    'nome' => $tabela,
                    'registros' => count($dados),
                    'json' => basename($arquivo_json),
                    'csv' => basename($arquivo_csv),
                    'sql' => basename($arquivo_sql),
                    'atualizado' => date('Y-m-d H:i:s')
                ];
                
                $resultados['total_registros'] += count($dados);
                
            } catch (Exception $e) {
                $resultados['message'] .= "Erro na tabela $tabela: " . $e->getMessage() . "\n";
            }
        }
        
        // GERAR BACKUP AUTOMÁTICO (SEMPRE ATUALIZAR O MESMO ARQUIVO)
        if ($backup_automatico) {
            $direcao_backup = ($direcao === 'local_para_publico') ? 'local' : 'publico';
            $backup_result = gerarBackupCompleto($direcao_backup, true);
            
            if ($backup_result['success']) {
                $resultados['backup_gerado'] = true;
                $resultados['backup_info'] = [
                    'arquivo' => $backup_result['arquivo'],
                    'tamanho' => $backup_result['tamanho'],
                    'tabelas' => $backup_result['tabelas'],
                    'registros' => $backup_result['registros'],
                    'atualizado' => $backup_result['data_hora']
                ];
            }
        }
        
        // Status
        $status = [
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
            'primeira_execucao' => $primeira_execucao,
            'direcao' => $direcao,
            'tabelas' => count($tabelas),
            'registros' => $resultados['total_registros'],
            'pasta_destino' => $pasta_destino,
            'continuar' => $_SESSION['sync_continuar'] ?? true,
            'backup_automatico' => $backup_automatico,
            'backup_gerado' => $resultados['backup_gerado'] ?? false,
            'arquivos_atualizados' => count($resultados['tabelas'])
        ];
        file_put_contents($pasta_destino . 'status.json', json_encode($status, JSON_PRETTY_PRINT));
        
        $resultados['message'] = "✅ Sincronização concluída! Arquivos atualizados.";
        if ($backup_automatico && $resultados['backup_gerado']) {
            $resultados['message'] .= " 📦 Backup atualizado!";
        }
        
    } catch (Exception $e) {
        $resultados['success'] = false;
        $resultados['message'] = "❌ " . $e->getMessage();
    }
    
    return $resultados;
}

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

function detectarDrives() {
    $drives = [];
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        for ($i = 65; $i <= 90; $i++) {
            $drive = chr($i) . ':/';
            if (is_dir($drive)) {
                $tipo = '';
                try {
                    $volume = shell_exec("wmic logicaldisk where DeviceID='{$drive}' get DriveType 2>nul");
                    if (strpos($volume, '2') !== false) {
                        $tipo = '💾 Pen Drive';
                    } elseif (strpos($volume, '3') !== false) {
                        $tipo = '💻 Disco Local';
                    } else {
                        $tipo = '📀 Outro';
                    }
                } catch (Exception $e) {
                    $tipo = '📀 Drive';
                }
                
                $freeSpace = @disk_free_space($drive);
                $totalSpace = @disk_total_space($drive);
                $freeGB = $freeSpace ? round($freeSpace / (1024 * 1024 * 1024), 2) : 'N/A';
                $totalGB = $totalSpace ? round($totalSpace / (1024 * 1024 * 1024), 2) : 'N/A';
                $percentual = ($totalSpace && $freeSpace) ? round(($freeSpace / $totalSpace) * 100, 1) . '%' : 'N/A';
                
                $drives[] = [
                    'letra' => $drive,
                    'tipo' => $tipo,
                    'livre' => $freeGB . ' GB',
                    'total' => $totalGB . ' GB',
                    'percentual' => $percentual
                ];
            }
        }
    } else {
        $drives[] = [
            'letra' => '/media/',
            'tipo' => '📀 Media',
            'livre' => 'N/A',
            'total' => 'N/A',
            'percentual' => 'N/A'
        ];
        $drives[] = [
            'letra' => '/mnt/',
            'tipo' => '📀 Mount',
            'livre' => 'N/A',
            'total' => 'N/A',
            'percentual' => 'N/A'
        ];
    }
    return $drives;
}

// ============================================
// FUNÇÃO PARA CRIAR PASTA
// ============================================

function criarPasta($caminho) {
    try {
        // Verificar se a pasta já existe
        if (is_dir($caminho)) {
            return ['success' => true, 'message' => '📁 Pasta já existe', 'caminho' => $caminho];
        }
        
        // Criar a pasta (recursivamente)
        if (mkdir($caminho, 0755, true)) {
            // Criar um arquivo index.html dentro da pasta para evitar listagem
            $index_file = $caminho . 'index.html';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<html><body><h1>📁 Pasta de Sincronização</h1><p>Arquivos gerados automaticamente.</p></body></html>');
            }
            return ['success' => true, 'message' => '✅ Pasta criada com sucesso!', 'caminho' => $caminho];
        } else {
            return ['success' => false, 'message' => '❌ Erro ao criar pasta'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => '❌ Erro: ' . $e->getMessage()];
    }
}

// ============================================
// PROCESSAR AÇÕES
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $acao = $_POST['acao'] ?? '';
    $direcao = $_POST['direcao'] ?? 'local_para_publico';
    
    if ($acao === 'definir_caminho') {
        $novo_caminho = $_POST['caminho'] ?? '';
        $criar_pasta = isset($_POST['criar_pasta']) ? filter_var($_POST['criar_pasta'], FILTER_VALIDATE_BOOLEAN) : false;
        
        if (!empty($novo_caminho)) {
            // Adicionar barra no final
            if (substr($novo_caminho, -1) !== '/' && substr($novo_caminho, -1) !== '\\') {
                $novo_caminho .= '/';
            }
            
            // Verificar se deve criar a pasta
            if ($criar_pasta) {
                $resultado_criar = criarPasta($novo_caminho);
                if (!$resultado_criar['success']) {
                    echo json_encode([
                        'success' => false,
                        'message' => $resultado_criar['message']
                    ]);
                    exit;
                }
            } else {
                // Verificar se a pasta existe
                if (!is_dir($novo_caminho)) {
                    echo json_encode([
                        'success' => false,
                        'message' => '❌ A pasta não existe. Marque "Criar pasta" para criá-la automaticamente.'
                    ]);
                    exit;
                }
            }
            
            $_SESSION['sync_folder'] = $novo_caminho;
            $_SESSION['sync_continuar'] = true;
            
            echo json_encode([
                'success' => true,
                'message' => '✅ Caminho atualizado com sucesso! A sincronização continuará automaticamente.',
                'caminho' => $novo_caminho,
                'continuar' => true,
                'pasta_criada' => $criar_pasta
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '❌ Caminho inválido!'
            ]);
        }
        exit;
    }
    
    if ($acao === 'sincronizar_dinamico') {
        $resultado = sincronizarDinamico($direcao);
        echo json_encode($resultado);
        exit;
    }
    
    if ($acao === 'status') {
        $pasta_atual = getCurrentSyncFolder();
        $status_file = $pasta_atual . 'status.json';
        if (file_exists($status_file)) {
            $status = json_decode(file_get_contents($status_file), true);
            $status['pasta_atual'] = $pasta_atual;
            $status['continuar'] = $_SESSION['sync_continuar'] ?? true;
            $status['backup_automatico'] = $_SESSION['backup_automatico'] ?? true;
            echo json_encode($status);
        } else {
            echo json_encode([
                'error' => 'Nenhuma sincronização realizada ainda',
                'pasta_atual' => $pasta_atual,
                'continuar' => $_SESSION['sync_continuar'] ?? true,
                'backup_automatico' => $_SESSION['backup_automatico'] ?? true
            ]);
        }
        exit;
    }
    
    if ($acao === 'listar_arquivos') {
        $pasta_atual = getCurrentSyncFolder();
        $arquivos = [];
        if (is_dir($pasta_atual)) {
            $files = scandir($pasta_atual);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $caminho = $pasta_atual . $file;
                    $arquivos[] = [
                        'nome' => $file,
                        'tamanho' => round(filesize($caminho) / 1024, 2) . ' KB',
                        'data' => date('Y-m-d H:i:s', filemtime($caminho)),
                        'extensao' => pathinfo($file, PATHINFO_EXTENSION)
                    ];
                }
            }
        }
        echo json_encode([
            'arquivos' => $arquivos,
            'pasta_atual' => $pasta_atual
        ]);
        exit;
    }
    
    if ($acao === 'listar_drives') {
        $drives = detectarDrives();
        echo json_encode($drives);
        exit;
    }
    
    if ($acao === 'definir_continuar') {
        $continuar = isset($_POST['continuar']) ? filter_var($_POST['continuar'], FILTER_VALIDATE_BOOLEAN) : true;
        $_SESSION['sync_continuar'] = $continuar;
        echo json_encode([
            'success' => true,
            'continuar' => $continuar,
            'message' => $continuar ? '✅ Sincronização continuará automaticamente' : '⏹️ Sincronização será interrompida'
        ]);
        exit;
    }
    
    if ($acao === 'get_caminho_atual') {
        echo json_encode([
            'caminho' => getCurrentSyncFolder(),
            'continuar' => $_SESSION['sync_continuar'] ?? true,
            'backup_automatico' => $_SESSION['backup_automatico'] ?? true
        ]);
        exit;
    }
    
    if ($acao === 'gerar_backup') {
        $tipo_backup = $_POST['tipo_backup'] ?? 'local';
        $resultado = gerarBackupCompleto($tipo_backup, true);
        echo json_encode($resultado);
        exit;
    }
    
    if ($acao === 'toggle_backup_automatico') {
        $habilitar = isset($_POST['habilitar']) ? filter_var($_POST['habilitar'], FILTER_VALIDATE_BOOLEAN) : true;
        $_SESSION['backup_automatico'] = $habilitar;
        echo json_encode([
            'success' => true,
            'habilitado' => $habilitar,
            'message' => $habilitar ? '✅ Backup automático ativado' : '⏹️ Backup automático desativado'
        ]);
        exit;
    }
}

// ============================================
// INTERFACE HTML
// ============================================

if (!isset($_POST['acao'])) {
    $caminho_atual = getCurrentSyncFolder();
    $backup_auto = $_SESSION['backup_automatico'] ?? true;
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronização Automática - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header {
            background: rgba(255,255,255,0.95);
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .header h1 {
            font-size: 32px;
            color: #1a2332;
        }
        .header h1 span { color: #2ecc71; }
        .header p { color: #64748b; margin-top: 5px; }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card h2 {
            font-size: 20px;
            color: #1a2332;
            margin-bottom: 20px;
            border-bottom: 3px solid #2ecc71;
            padding-bottom: 10px;
        }
        
        .status-bar {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 15px;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-item .label { color: #64748b; font-size: 13px; }
        .status-item .value { font-weight: 700; color: #1a2332; }
        .status-item .online { color: #2ecc71; }
        .status-item .offline { color: #e74c3c; }
        .status-item .warning { color: #f39c12; }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,0,0,0.15); }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-success { background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; }
        .btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
        .btn-secondary { background: #e2e8f0; color: #1a2332; }
        .btn-warning { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
        .btn-info { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
        .btn-dark { background: #1a2332; color: white; }
        .btn-backup { background: linear-gradient(135deg, #8e44ad, #6c3483); color: white; }
        
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2ecc71;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .pulse {
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.95); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            margin-top: 15px;
            font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .file-item {
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-item .nome { font-weight: 500; color: #1a2332; font-size: 13px; }
        .file-item .info { color: #94a3b8; font-size: 12px; }
        .file-item .badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #2ecc71;
            color: white;
        }
        .file-item .badge-backup {
            background: #8e44ad;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .stat-box {
            background: linear-gradient(135deg, #2ecc7115, #27ae6015);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .stat-box .numero {
            font-size: 28px;
            font-weight: 700;
            color: #2ecc71;
        }
        .stat-box .label {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
        
        .direcao-select {
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            min-width: 200px;
        }
        .direcao-select:focus { border-color: #2ecc71; outline: none; }
        
        .auto-status {
            background: #2ecc71;
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        .auto-status.parado {
            background: #94a3b8;
        }
        .auto-status .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: white;
            animation: pulse 1s infinite;
        }
        .auto-status.parado .dot {
            animation: none;
        }
        
        .caminho-input-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .caminho-input-group input {
            flex: 1;
            min-width: 300px;
            padding: 12px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Courier New', monospace;
            background: #f8fafc;
            transition: border-color 0.3s;
        }
        .caminho-input-group input:focus {
            border-color: #2ecc71;
            outline: none;
            background: white;
        }
        
        .criar-pasta-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: #1a2332;
            cursor: pointer;
        }
        .criar-pasta-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .drive-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 10px 0;
        }
        .drive-item {
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
            min-width: 120px;
        }
        .drive-item:hover {
            border-color: #2ecc71;
            background: #f0fdf4;
            transform: scale(1.02);
        }
        .drive-item.active {
            border-color: #2ecc71;
            background: #d1fae5;
        }
        .drive-item .drive-letra {
            font-weight: 700;
            font-size: 16px;
            color: #1a2332;
        }
        .drive-item .drive-info {
            color: #64748b;
            font-size: 11px;
        }
        .drive-item .drive-espaco {
            color: #2ecc71;
            font-weight: 600;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-indicator.running {
            background: #d1fae5;
            color: #065f46;
        }
        .status-indicator.stopped {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-caminho {
            background: #1a2332;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        
        .backup-info {
            background: linear-gradient(135deg, #8e44ad15, #6c348315);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #8e44ad30;
            margin: 10px 0;
        }
        .backup-info .detail {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .backup-info .detail:last-child {
            border-bottom: none;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #2ecc71;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .info-box {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 10px;
            color: #065f46;
            font-size: 13px;
        }
        .info-box .highlight {
            font-weight: 700;
            color: #16a34a;
        }
        
        @media (max-width: 768px) {
            .header h1 { font-size: 24px; }
            .status-bar { flex-direction: column; }
            .caminho-input-group input { min-width: 200px; }
            .drive-item { min-width: 100px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔄 <span>Sincronização Automática Contínua</span></h1>
        <p style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <span>Sincronizando para: <strong id="pastaAtual" class="badge-caminho"><?php echo $caminho_atual; ?></strong></span>
            <span class="auto-status" id="statusContainer">
                <span class="dot"></span>
                <span id="statusLabel">ATIVO</span>
            </span>
            <span id="statusIndicator" class="status-indicator running">▶️ Sincronizando</span>
        </p>
    </div>
    
    <div class="card">
        <h2>📁 Configurar Pasta de Destino</h2>
        <p style="color: #64748b; margin-bottom: 15px; font-size: 14px;">
            ⚡ Os arquivos são <strong>atualizados automaticamente</strong> (sobrescritos) a cada sincronização.
            Não são criados novos arquivos, apenas atualizados os existentes.
        </p>
        
        <div class="caminho-input-group">
            <input type="text" id="caminhoPasta" placeholder="Digite o caminho da pasta (ex: D:/MeuPenDrive/)" value="<?php echo $caminho_atual; ?>">
            <label class="criar-pasta-checkbox">
                <input type="checkbox" id="criarPasta" checked>
                📁 Criar pasta
            </label>
            <button class="btn btn-success" onclick="definirCaminho()">
                ✅ Definir Caminho
            </button>
            <button class="btn btn-info" onclick="listarDrives()">
                🔄 Detectar Drives
            </button>
        </div>
        
        <div class="info-box">
            💡 <span class="highlight">Dica:</span> Marque "Criar pasta" para criar automaticamente a pasta se ela não existir.
            A sincronização continuará funcionando mesmo mudando o caminho.
        </div>
        
        <div id="driveList" class="drive-list" style="margin-top: 10px;">
            <div style="color: #94a3b8; font-size: 13px; width: 100%;">
                💡 Clique em um drive abaixo para selecionar automaticamente:
            </div>
        </div>
        
        <div id="msgCaminho" style="display: none;" class="alert"></div>
    </div>
    
    <div class="card">
        <h2>💾 Backup Automático</h2>
        <p style="color: #64748b; margin-bottom: 15px; font-size: 14px;">
            📦 O backup completo é <strong>atualizado automaticamente</strong> a cada sincronização.
            O arquivo <strong>backup_completo.sql</strong> é sempre sobrescrito com os dados mais recentes.
        </p>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center; margin-bottom: 15px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-weight: 600;">Backup Automático:</span>
                <label class="toggle-switch">
                    <input type="checkbox" id="toggleBackupAuto" <?php echo $backup_auto ? 'checked' : ''; ?> onchange="toggleBackupAutomatico()">
                    <span class="slider"></span>
                </label>
                <span id="backupStatusLabel" style="font-weight: 600; color: <?php echo $backup_auto ? '#2ecc71' : '#94a3b8'; ?>;">
                    <?php echo $backup_auto ? '✅ ATIVO' : '⏹️ DESATIVADO'; ?>
                </span>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <select id="tipoBackup" class="direcao-select" style="min-width: 150px;">
                    <option value="local">💻 Local (MySQL)</option>
                    <option value="publico">☁️ Público (PostgreSQL)</option>
                </select>
                <button class="btn btn-backup" onclick="gerarBackupManual()">
                    📥 Gerar Backup Agora
                </button>
            </div>
        </div>
        
        <div id="backupInfo" style="display: none;" class="backup-info">
            <h3 style="color: #8e44ad; margin-bottom: 10px;">📋 Último Backup</h3>
            <div id="backupDetalhes"></div>
        </div>
        
        <div id="msgBackup" style="display: none;" class="alert"></div>
    </div>
    
    <div class="card">
        <h2>⚙️ Status da Sincronização</h2>
        
        <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px;">
            <div style="background: #f8fafc; padding: 10px 20px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <span style="color: #64748b;">📁 Pasta:</span>
                <span id="pastaStatus" class="badge-caminho" style="background: #2ecc71; margin-left: 8px;"><?php echo $caminho_atual; ?></span>
            </div>
            <div style="background: #f8fafc; padding: 10px 20px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <span style="color: #64748b;">🔄 Status:</span>
                <span id="statusSync" class="value online">▶️ Atualizando arquivos...</span>
            </div>
            <div style="background: #f8fafc; padding: 10px 20px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <span style="color: #64748b;">💾 Backup:</span>
                <span id="backupStatus" class="value" style="color: <?php echo $backup_auto ? '#2ecc71' : '#94a3b8'; ?>;">
                    <?php echo $backup_auto ? '✅ Automático' : '⏹️ Desativado'; ?>
                </span>
            </div>
        </div>
        
        <div class="status-bar">
            <div class="status-item">
                <span class="label">⏱️ Última atualização:</span>
                <span id="ultimaSync" class="value">Carregando...</span>
            </div>
            <div class="status-item">
                <span class="label">📊 Tabelas:</span>
                <span id="totalTabelas" class="value">0</span>
            </div>
            <div class="status-item">
                <span class="label">📝 Registros:</span>
                <span id="totalRegistros" class="value">0</span>
            </div>
            <div class="status-item">
                <span class="label">⏱️ Tempo ativo:</span>
                <span id="tempoAtivo" class="value">00:00:00</span>
            </div>
        </div>
        
        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 15px;">
            <select id="direcaoSync" class="direcao-select">
                <option value="local_para_publico">💻 Local → Público</option>
                <option value="publico_para_local">☁️ Público → Local</option>
            </select>
            
            <div class="btn-group">
                <button id="btnParar" class="btn btn-danger" onclick="toggleSync()">
                    ⏹️ Parar Sincronização
                </button>
                <button id="btnSyncAgora" class="btn btn-primary" onclick="syncAgora()">
                    🔄 Sincronizar Agora
                </button>
                <button class="btn btn-secondary" onclick="carregarStatus()">
                    🔄 Atualizar Status
                </button>
                <button class="btn btn-warning" onclick="abrirPasta()">
                    📂 Abrir Pasta
                </button>
            </div>
        </div>
        
        <div id="mensagem" style="display: none;" class="alert"></div>
        
        <div class="stats-grid">
            <div class="stat-box">
                <div class="numero" id="statTabelas">0</div>
                <div class="label">📋 Tabelas</div>
            </div>
            <div class="stat-box">
                <div class="numero" id="statRegistros">0</div>
                <div class="label">📝 Registros</div>
            </div>
            <div class="stat-box">
                <div class="numero" id="statArquivos">0</div>
                <div class="label">📁 Arquivos</div>
            </div>
            <div class="stat-box">
                <div class="numero" id="statTamanho">0 KB</div>
                <div class="label">💾 Tamanho Total</div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2>📁 Arquivos (Atualizados Automaticamente)</h2>
        <p style="color: #64748b; font-size: 13px; margin-bottom: 10px;">
            🔄 Os arquivos são <strong>sobrescritos</strong> a cada sincronização com os dados mais recentes.
        </p>
        <div id="listaArquivos" style="max-height: 400px; overflow-y: auto;">
            <div style="text-align: center; color: #94a3b8; padding: 20px;">
                ⏳ Carregando arquivos...
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// VARIÁVEIS
// ============================================
let intervalo = null;
let syncAtivo = true;
let tempoSegundos = 0;
let tempoIntervalo = null;
let backupAutomatico = <?php echo $backup_auto ? 'true' : 'false'; ?>;

// ============================================
// INICIAR AUTOMATICAMENTE
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando sincronização automática contínua...');
    console.log('💾 Backup automático:', backupAutomatico ? 'ATIVO' : 'DESATIVADO');
    console.log('📁 Arquivos serão ATUALIZADOS (sobrescritos) a cada sincronização');
    
    listarDrives();
    carregarStatus();
    carregarArquivos();
    carregarUltimoBackup();
    iniciarSyncContinuo();
    
    setInterval(() => {
        if (syncAtivo) {
            carregarStatus();
            carregarArquivos();
        }
    }, 5000);
    
    iniciarContadorTempo();
});

// ============================================
// FUNÇÕES DE BACKUP
// ============================================

function toggleBackupAutomatico() {
    const checked = document.getElementById('toggleBackupAuto').checked;
    backupAutomatico = checked;
    
    const formData = new FormData();
    formData.append('acao', 'toggle_backup_automatico');
    formData.append('habilitar', checked ? 'true' : 'false');
    
    fetch('sync_auto_start.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const label = document.getElementById('backupStatusLabel');
            const status = document.getElementById('backupStatus');
            if (checked) {
                label.textContent = '✅ ATIVO';
                label.style.color = '#2ecc71';
                status.textContent = '✅ Automático';
                status.style.color = '#2ecc71';
                mostrarMsgBackup('✅ Backup automático ativado!', 'success');
            } else {
                label.textContent = '⏹️ DESATIVADO';
                label.style.color = '#94a3b8';
                status.textContent = '⏹️ Desativado';
                status.style.color = '#94a3b8';
                mostrarMsgBackup('⏹️ Backup automático desativado', 'warning');
            }
        }
    })
    .catch(error => {
        mostrarMsgBackup('❌ Erro ao alterar configuração: ' + error, 'error');
    });
}

function gerarBackupManual() {
    const tipo = document.getElementById('tipoBackup').value;
    const btn = document.querySelector('.btn-backup');
    const originalText = btn.textContent;
    
    btn.textContent = '⏳ Gerando...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('acao', 'gerar_backup');
    formData.append('tipo_backup', tipo);
    
    fetch('sync_auto_start.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.textContent = originalText;
        btn.disabled = false;
        
        if (data.success) {
            mostrarDetalhesBackup(data);
            mostrarMsgBackup('✅ ' + data.message, 'success');
            carregarArquivos();
        } else {
            mostrarMsgBackup('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        btn.textContent = originalText;
        btn.disabled = false;
        mostrarMsgBackup('❌ Erro ao gerar backup: ' + error, 'error');
    });
}

function carregarUltimoBackup() {
    const formData = new FormData();
    formData.append('acao', 'listar_arquivos');
    
    fetch('sync_auto_start.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const arquivos = data.arquivos || [];
        const backupFile = arquivos.find(f => f.nome === 'backup_completo.sql');
        
        if (backupFile) {
            const backupInfo = document.getElementById('backupInfo');
            const detalhes = document.getElementById('backupDetalhes');
            
            detalhes.innerHTML = `
                <div class="detail">
                    <span>📄 Arquivo:</span>
                    <span><strong>${backupFile.nome}</strong></span>
                </div>
                <div class="detail">
                    <span>💾 Tamanho:</span>
                    <span><strong>${backupFile.tamanho}</strong></span>
                </div>
                <div class="detail">
                    <span>📅 Última atualização:</span>
                    <span><strong>${backupFile.data}</strong></span>
                </div>
                <div style="margin-top: 15px; text-align: center; color: #8e44ad; font-size: 13px;">
                    🔄 Arquivo atualizado automaticamente a cada sincronização
                </div>
            `;
            
            backupInfo.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar último backup:', error);
    });
}

function mostrarDetalhesBackup(data) {
    const backupInfo = document.getElementById('backupInfo');
    const detalhes = document.getElementById('backupDetalhes');
    
    detalhes.innerHTML = `
        <div class="detail">
            <span>📄 Arquivo:</span>
            <span><strong>${data.arquivo}</strong></span>
        </div>
        <div class="detail">
            <span>📊 Tabelas:</span>
            <span><strong>${data.tabelas}</strong></span>
        </div>
        <div class="detail">
            <span>📝 Registros:</span>
            <span><strong>${data.registros}</strong></span>
        </div>
        <div class="detail">
            <span>💾 Tamanho:</span>
            <span><strong>${data.tamanho}</strong></span>
        </div>
        <div class="detail">
            <span>📁 Pasta:</span>
            <span><strong style="font-size: 12px;">${data.pasta_destino}</strong></span>
        </div>
        <div class="detail">
            <span>⏱️ Atualizado:</span>
            <span><strong>${data.data_hora}</strong></span>
        </div>
        <div style="margin-top: 15px; text-align: center;">
            <button class="btn btn-success" onclick="window.open('${data.caminho_completo}', '_blank')">
                📥 Baixar Backup
            </button>
        </div>
    `;
    
    backupInfo.style.display = 'block';
}

function mostrarMsgBackup(texto, tipo) {
    const el = document.getElementById('msgBackup');
    el.textContent = texto;
    el.className = 'alert alert-' + tipo;
    el.style.display = 'block';
    
    setTimeout(() => {
        el.style.display = 'none';
    }, 5000);
}

// ============================================
// FUNÇÃO DE SINCRONIZAÇÃO CONTÍNUA
// ============================================

function iniciarSyncContinuo() {
    if (intervalo) {
        clearInterval(intervalo);
    }
    
    intervalo = setInterval(function() {
        if (syncAtivo) {
            syncAgora();
        }
    }, 1000);
    
    console.log('🔄 Sincronização contínua iniciada (atualizando arquivos a cada 1 segundo)');
}

function toggleSync() {
    syncAtivo = !syncAtivo;
    
    const btn = document.getElementById('btnParar');
    const statusContainer = document.getElementById('statusContainer');
    const statusLabel = document.getElementById('statusLabel');
    const statusSync = document.getElementById('statusSync');
    const statusIndicator = document.getElementById('statusIndicator');
    
    if (!syncAtivo) {
        btn.textContent = '▶️ Iniciar Sincronização';
        btn.className = 'btn btn-success';
        statusContainer.className = 'auto-status parado';
        statusLabel.textContent = 'PARADO';
        statusSync.className = 'value offline';
        statusSync.textContent = '⏹️ Sincronização parada';
        statusIndicator.className = 'status-indicator stopped';
        statusIndicator.textContent = '⏹️ Parado';
        
        if (intervalo) {
            clearInterval(intervalo);
            intervalo = null;
        }
        
        const formData = new FormData();
        formData.append('acao', 'definir_continuar');
        formData.append('continuar', 'false');
        fetch('sync_auto_start.php', { method: 'POST', body: formData });
        
        mostrarMensagem('⏹️ Sincronização automática parada', 'warning');
    } else {
        btn.textContent = '⏹️ Parar Sincronização';
        btn.className = 'btn btn-danger';
        statusContainer.className = 'auto-status';
        statusLabel.textContent = 'ATIVO';
        statusSync.className = 'value online';
        statusSync.textContent = '▶️ Atualizando arquivos...';
        statusIndicator.className = 'status-indicator running';
        statusIndicator.textContent = '▶️ Sincronizando';
        
        const formData = new FormData();
        formData.append('acao', 'definir_continuar');
        formData.append('continuar', 'true');
        fetch('sync_auto_start.php', { method: 'POST', body: formData });
        
        iniciarSyncContinuo();
        mostrarMensagem('▶️ Sincronização automática reiniciada', 'success');
    }
}

// ============================================
// FUNÇÕES DE CONFIGURAÇÃO DE CAMINHO
// ============================================

function definirCaminho() {
    const caminho = document.getElementById('caminhoPasta').value.trim();
    const criarPasta = document.getElementById('criarPasta').checked;
    
    if (!caminho) {
        mostrarMsgCaminho('❌ Por favor, digite um caminho válido.', 'error');
        return;
    }
    
    let caminhoFinal = caminho;
    if (caminhoFinal.slice(-1) !== '/' && caminhoFinal.slice(-1) !== '\\') {
        caminhoFinal += '/';
    }
    
    const formData = new FormData();
    formData.append('acao', 'definir_caminho');
    formData.append('caminho', caminhoFinal);
    formData.append('criar_pasta', criarPasta ? 'true' : 'false');
    
    mostrarMsgCaminho('⏳ Processando...', 'info');
    
    fetch('sync_auto_start.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let msg = data.message;
            if (data.pasta_criada) {
                msg = '✅ Pasta criada e ' + data.message.toLowerCase();
            }
            mostrarMsgCaminho(msg, 'success');
            document.getElementById('pastaAtual').textContent = data.caminho;
            document.getElementById('pastaStatus').textContent = data.caminho;
            document.getElementById('caminhoPasta').value = data.caminho;
            
            carregarArquivos();
            carregarStatus();
        } else {
            mostrarMsgCaminho(data.message, 'error');
        }
    })
    .catch(error => {
        mostrarMsgCaminho('❌ Erro ao definir caminho: ' + error, 'error');
    });
}

function listarDrives() {
    const container = document.getElementById('driveList');
    container.innerHTML = '<div style="color: #94a3b8; font-size: 13px;">⏳ Carregando drives...</div>';
    
    const formData = new FormData();
    formData.append('acao', 'listar_drives');
    
    fetch('sync_auto_start.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(drives => {
        if (drives.length === 0) {
            container.innerHTML = '<div style="color: #94a3b8; font-size: 13px;">📭 Nenhum drive encontrado</div>';
            return;
        }
        
        let html = '';
        const caminhoAtual = document.getElementById('caminhoPasta').value;
        drives.forEach(drive => {
            const isActive = drive.letra === caminhoAtual;
            html += `
                <div class="drive-item ${isActive ? 'active' : ''}" onclick="selecionarDrive('${drive.letra}')">
                    <div class="drive-letra">${drive.letra}</div>
                    <div class="drive-info">${drive.tipo}</div>
                    <div class="drive-espaco">💾 ${drive.livre} livre</div>
                    <div style="font-size: 10px; color: #94a3b8;">${drive.percentual} disponível</div>
                </div>
            `;
        });
        container.innerHTML = html;
    })
    .catch(error => {
        container.innerHTML = '<div style="color: #e74c3c; font-size: 13px;">❌ Erro ao carregar drives</div>';
    });
}

function selecionarDrive(drive) {
    document.getElementById('caminhoPasta').value = drive;
    // Marcar criar pasta automaticamente ao selecionar um drive
    document.getElementById('criarPasta').checked = true;
    definirCaminho();
}

function mostrarMsgCaminho(texto, tipo) {
    const el = document.getElementById('msgCaminho');
    el.textContent = texto;
    el.className = 'alert alert-' + tipo;
    el.style.display = 'block';
    
    setTimeout(() => {
        el.style.display = 'none';
    }, 5000);
}

// ============================================
// FUNÇÕES PRINCIPAIS
// ============================================

function syncAgora() {
    const direcao = document.getElementById('direcaoSync').value;
    
    const formData = new FormData();
    formData.append('acao', 'sincronizar_dinamico');
    formData.append('direcao', direcao);
    
    fetch('sync_auto_start.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('ultimaSync').textContent = data.data_hora;
            document.getElementById('totalTabelas').textContent = data.tabelas.length;
            document.getElementById('totalRegistros').textContent = data.total_registros;
            
            document.getElementById('statTabelas').textContent = data.tabelas.length;
            document.getElementById('statRegistros').textContent = data.total_registros;
            
            if (data.pasta_destino) {
                document.getElementById('pastaAtual').textContent = data.pasta_destino;
                document.getElementById('pastaStatus').textContent = data.pasta_destino;
                document.getElementById('caminhoPasta').value = data.pasta_destino;
            }
            
            // Verificar se backup foi gerado
            if (data.backup_gerado && data.backup_info) {
                const backupInfo = document.getElementById('backupInfo');
                const detalhes = document.getElementById('backupDetalhes');
                
                detalhes.innerHTML = `
                    <div class="detail">
                        <span>📄 Arquivo:</span>
                        <span><strong>${data.backup_info.arquivo}</strong></span>
                    </div>
                    <div class="detail">
                        <span>📊 Tabelas:</span>
                        <span><strong>${data.backup_info.tabelas}</strong></span>
                    </div>
                    <div class="detail">
                        <span>📝 Registros:</span>
                        <span><strong>${data.backup_info.registros}</strong></span>
                    </div>
                    <div class="detail">
                        <span>💾 Tamanho:</span>
                        <span><strong>${data.backup_info.tamanho}</strong></span>
                    </div>
                    <div class="detail">
                        <span>⏱️ Atualizado:</span>
                        <span><strong>${data.backup_info.atualizado}</strong></span>
                    </div>
                    <div style="margin-top: 15px; text-align: center; color: #8e44ad; font-size: 13px;">
                        ✅ Backup atualizado automaticamente
                    </div>
                `;
                
                backupInfo.style.display = 'block';
            }
            
            carregarArquivos();
        } else {
            document.getElementById('statusSync').className = 'value offline';
            document.getElementById('statusSync').textContent = '⚠️ Erro na sincronização';
        }
    })
    .catch(error => {
        console.error('Erro na sincronização:', error);
    });
}

function carregarStatus() {
    const formData = new FormData();
    formData.append('acao', 'status');
    
    fetch('sync_auto_start.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.ultima_sincronizacao) {
            document.getElementById('ultimaSync').textContent = data.ultima_sincronizacao;
            document.getElementById('totalTabelas').textContent = data.tabelas || 0;
            document.getElementById('totalRegistros').textContent = data.registros || 0;
            document.getElementById('statTabelas').textContent = data.tabelas || 0;
            document.getElementById('statRegistros').textContent = data.registros || 0;
            
            if (data.pasta_destino || data.pasta_atual) {
                const pasta = data.pasta_destino || data.pasta_atual;
                document.getElementById('pastaAtual').textContent = pasta;
                document.getElementById('pastaStatus').textContent = pasta;
                document.getElementById('caminhoPasta').value = pasta;
            }
            
            if (data.backup_automatico !== undefined) {
                backupAutomatico = data.backup_automatico;
                document.getElementById('toggleBackupAuto').checked = backupAutomatico;
                const label = document.getElementById('backupStatusLabel');
                const status = document.getElementById('backupStatus');
                if (backupAutomatico) {
                    label.textContent = '✅ ATIVO';
                    label.style.color = '#2ecc71';
                    status.textContent = '✅ Automático';
                    status.style.color = '#2ecc71';
                } else {
                    label.textContent = '⏹️ DESATIVADO';
                    label.style.color = '#94a3b8';
                    status.textContent = '⏹️ Desativado';
                    status.style.color = '#94a3b8';
                }
            }
        } else if (data.pasta_atual) {
            document.getElementById('pastaAtual').textContent = data.pasta_atual;
            document.getElementById('pastaStatus').textContent = data.pasta_atual;
            document.getElementById('caminhoPasta').value = data.pasta_atual;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar status:', error);
    });
}

function carregarArquivos() {
    const container = document.getElementById('listaArquivos');
    
    const formData = new FormData();
    formData.append('acao', 'listar_arquivos');
    
    fetch('sync_auto_start.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const arquivos = data.arquivos || [];
        
        if (data.pasta_atual) {
            document.getElementById('pastaAtual').textContent = data.pasta_atual;
            document.getElementById('pastaStatus').textContent = data.pasta_atual;
        }
        
        if (arquivos.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: #94a3b8; padding: 20px;">📭 Nenhum arquivo encontrado</div>';
            document.getElementById('statArquivos').textContent = '0';
            document.getElementById('statTamanho').textContent = '0 KB';
            return;
        }
        
        let html = '<div class="file-grid">';
        let tamanhoTotal = 0;
        
        arquivos.sort((a, b) => new Date(b.data) - new Date(a.data));
        
        arquivos.forEach(arquivo => {
            const extensao = arquivo.extensao.toUpperCase();
            let cor = '#667eea';
            let badgeClass = '';
            
            if (extensao === 'JSON') cor = '#f39c12';
            else if (extensao === 'CSV') cor = '#2ecc71';
            else if (extensao === 'SQL') cor = '#3498db';
            else if (extensao === 'TXT') cor = '#95a5a6';
            else if (extensao === 'HTML') cor = '#e67e22';
            
            const tamanhoNum = parseFloat(arquivo.tamanho) || 0;
            tamanhoTotal += tamanhoNum;
            
            const isBackup = arquivo.nome.includes('backup_completo');
            
            html += `
                <div class="file-item">
                    <div>
                        <div class="nome">${isBackup ? '💾 ' : ''}${arquivo.nome}</div>
                        <div class="info">${arquivo.tamanho} • ${arquivo.data}</div>
                    </div>
                    <span class="badge ${badgeClass}" style="background: ${cor};">${extensao}</span>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
        document.getElementById('statArquivos').textContent = arquivos.length;
        document.getElementById('statTamanho').textContent = tamanhoTotal.toFixed(2) + ' KB';
    })
    .catch(error => {
        container.innerHTML = '<div style="text-align: center; color: #e74c3c; padding: 20px;">❌ Erro ao carregar arquivos</div>';
        console.error('Erro:', error);
    });
}

function mostrarMensagem(texto, tipo) {
    const el = document.getElementById('mensagem');
    el.textContent = texto;
    el.className = 'alert alert-' + tipo;
    el.style.display = 'block';
    
    setTimeout(() => {
        el.style.display = 'none';
    }, 5000);
}

function abrirPasta() {
    const pasta = document.getElementById('caminhoPasta').value;
    try {
        const urlPasta = pasta.replace(/[\/\\]$/, '').replace(/:/g, '|');
        window.open('file:///' + urlPasta, '_blank');
    } catch(e) {
        mostrarMensagem('❌ Não foi possível abrir a pasta', 'error');
    }
}

function iniciarContadorTempo() {
    tempoIntervalo = setInterval(() => {
        if (syncAtivo) {
            tempoSegundos++;
            const horas = Math.floor(tempoSegundos / 3600);
            const minutos = Math.floor((tempoSegundos % 3600) / 60);
            const segs = tempoSegundos % 60;
            const tempo = String(horas).padStart(2, '0') + ':' + 
                         String(minutos).padStart(2, '0') + ':' + 
                         String(segs).padStart(2, '0');
            document.getElementById('tempoAtivo').textContent = tempo;
        }
    }, 1000);
}

// ============================================
// ATALHOS DO TECLADO
// ============================================

document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        syncAgora();
    }
    
    if (e.ctrlKey && e.shiftKey && e.key === 'P') {
        e.preventDefault();
        toggleSync();
    }
    
    if (e.ctrlKey && e.shiftKey && e.key === 'A') {
        e.preventDefault();
        abrirPasta();
    }
    
    if (e.ctrlKey && e.shiftKey && e.key === 'B') {
        e.preventDefault();
        gerarBackupManual();
    }
});

// ============================================
// CONFIRMAR SAÍDA
// ============================================

window.addEventListener('beforeunload', function(e) {
    if (syncAtivo) {
        e.preventDefault();
        e.returnValue = 'A sincronização contínua está ativa. Deseja realmente sair?';
        return e.returnValue;
    }
});
</script>

</body>
</html>
<?php
}
?>