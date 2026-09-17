<?php
// ============================================
// import_sql.php - Importação DIRETA (Executa SQL bruto)
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$root_dir = dirname(__DIR__);
require_once $root_dir . '/config/app_modes.php';
require_once $root_dir . '/config/database.php';

$usuario_perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
if ($usuario_perfil !== 'admin') {
    die('❌ Acesso negado.');
}

try {
    $pdo = conectarBanco();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// ============================================
// VERIFICAÇÃO DE SENHA
// ============================================
$senha_correta = 'Claudtec2011';
$senha_verificada = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha_acesso'])) {
    if ($_POST['senha_acesso'] === $senha_correta) {
        $senha_verificada = true;
        $_SESSION['import_sql_verified'] = true;
    } else {
        $erro_senha = '❌ Senha incorreta. Tente novamente.';
    }
}

// Se já foi verificado antes, manter verificado
if (isset($_SESSION['import_sql_verified']) && $_SESSION['import_sql_verified'] === true) {
    $senha_verificada = true;
}

// ============================================
// EXPORTAÇÃO DO BANCO
// ============================================
if (isset($_GET['exportar_banco']) && $senha_verificada) {
    try {
        // Busca todas as tabelas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Configura o nome do arquivo
        $nome_arquivo = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Headers para download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Abre a saída
        $output = fopen('php://output', 'w');
        
        // Escreve cabeçalho do backup
        fwrite($output, "-- ============================================\n");
        fwrite($output, "-- Backup do banco: " . DB_NAME . "\n");
        fwrite($output, "-- Data: " . date('Y-m-d H:i:s') . "\n");
        fwrite($output, "-- ============================================\n\n");
        
        // Desabilita constraints durante a exportação
        fwrite($output, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
        
        foreach ($tables as $table) {
            // Estrutura da tabela
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                fwrite($output, "-- --------------------------------------------------------\n");
                fwrite($output, "-- Tabela: `$table`\n");
                fwrite($output, "-- --------------------------------------------------------\n\n");
                fwrite($output, "DROP TABLE IF EXISTS `$table`;\n");
                fwrite($output, $row['Create Table'] . ";\n\n");
            }
            
            // Dados da tabela
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                // Obtém nomes das colunas
                $columns = array_keys($rows[0]);
                $colunas_str = '`' . implode('`, `', $columns) . '`';
                
                // Processa em lotes para melhor performance
                $batch = [];
                $batch_size = 50;
                $total_rows = count($rows);
                $row_count = 0;
                
                fwrite($output, "INSERT INTO `$table` ($colunas_str) VALUES\n");
                
                foreach ($rows as $row) {
                    $row_count++;
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $pdo->quote($value);
                        }
                    }
                    $batch[] = '(' . implode(', ', $values) . ')';
                    
                    if (count($batch) >= $batch_size || $row_count == $total_rows) {
                        fwrite($output, implode(",\n", $batch));
                        if ($row_count < $total_rows) {
                            fwrite($output, ",\n");
                        } else {
                            fwrite($output, ";\n\n");
                        }
                        $batch = [];
                    }
                }
            }
        }
        
        // Reabilita constraints
        fwrite($output, "SET FOREIGN_KEY_CHECKS = 1;\n");
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        $erro_exportacao = '❌ Erro ao exportar: ' . $e->getMessage();
    }
}

// ============================================
// CONFIGURAÇÕES
// ============================================
define('BATCH_SIZE', 50);

// ============================================
// FUNÇÃO PARA EXTRAIR E EXECUTAR SQL DIRETAMENTE
// ============================================
function executarSQLDireto($pdo, $sqlContent) {
    $resultados = [
        'total' => 0,
        'inseridos' => 0,
        'ignorados' => 0,
        'erros' => 0,
        'detalhes' => []
    ];
    
    // Remove comentários
    $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
    $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);
    
    // Remove SET, START TRANSACTION, etc.
    $sqlContent = preg_replace('/^SET.*$/m', '', $sqlContent);
    $sqlContent = preg_replace('/^START TRANSACTION.*$/m', '', $sqlContent);
    $sqlContent = preg_replace('/^COMMIT.*$/m', '', $sqlContent);
    $sqlContent = preg_replace('/^\/\*.*?\*\/$/m', '', $sqlContent);
    
    // Encontra todos os INSERTs
    preg_match_all(
        '/INSERT\s+INTO\s+`?([a-zA-Z_][a-zA-Z0-9_]*?)`?\s*(?:\([^)]*\))?\s+VALUES\s*/i',
        $sqlContent,
        $matches,
        PREG_OFFSET_CAPTURE
    );
    
    if (empty($matches[0])) {
        throw new Exception('Nenhum INSERT encontrado no arquivo.');
    }
    
    $resultados['total'] = count($matches[0]);
    
    // Para cada INSERT, extrai o SQL completo
    foreach ($matches[0] as $idx => $match) {
        $table = $matches[1][$idx][0];
        $startPos = $match[1];
        
        // Encontra o fim do INSERT (próximo INSERT ou fim do arquivo)
        $endPos = strlen($sqlContent);
        if ($idx < count($matches[0]) - 1) {
            $endPos = $matches[0][$idx + 1][1];
        }
        
        // Extrai o SQL completo deste INSERT
        $sql = substr($sqlContent, $startPos, $endPos - $startPos);
        $sql = trim($sql);
        
        // Remove o ponto e vírgula final se existir
        $sql = rtrim($sql, ';');
        
        try {
            // Tenta executar o SQL
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $affected = $stmt->rowCount();
            
            if ($affected > 0) {
                $resultados['inseridos'] += $affected;
                $resultados['detalhes'][] = [
                    'numero' => $idx + 1,
                    'tabela' => $table,
                    'status' => 'inserido',
                    'motivo' => "$affected registro(s) inserido(s)"
                ];
            } else {
                $resultados['ignorados']++;
                $resultados['detalhes'][] = [
                    'numero' => $idx + 1,
                    'tabela' => $table,
                    'status' => 'ignorado',
                    'motivo' => 'Nenhum registro afetado'
                ];
            }
        } catch (Exception $e) {
            // Se falhou, tenta executar cada VALUES separadamente
            try {
                // Extrai os VALUES
                preg_match('/VALUES\s*(.*)$/is', $sql, $valMatch);
                if (!empty($valMatch)) {
                    $valuesPart = $valMatch[1];
                    preg_match_all('/\(([^)]*)\)/s', $valuesPart, $valGroups);
                    
                    $tablePart = preg_replace('/VALUES\s*.*$/is', '', $sql);
                    
                    foreach ($valGroups[1] as $valGroup) {
                        try {
                            $insertSQL = $tablePart . 'VALUES (' . $valGroup . ')';
                            $stmt = $pdo->prepare($insertSQL);
                            $stmt->execute();
                            $resultados['inseridos']++;
                            $resultados['detalhes'][] = [
                                'numero' => $idx + 1,
                                'tabela' => $table,
                                'status' => 'inserido',
                                'motivo' => 'Inserido (via fallback)'
                            ];
                        } catch (Exception $e2) {
                            // Verifica se é duplicata
                            if (strpos($e2->getMessage(), 'Duplicate entry') !== false) {
                                $resultados['ignorados']++;
                                $resultados['detalhes'][] = [
                                    'numero' => $idx + 1,
                                    'tabela' => $table,
                                    'status' => 'ignorado',
                                    'motivo' => 'Registro duplicado'
                                ];
                            } else {
                                $resultados['erros']++;
                                $resultados['detalhes'][] = [
                                    'numero' => $idx + 1,
                                    'tabela' => $table,
                                    'status' => 'erro',
                                    'motivo' => substr($e2->getMessage(), 0, 100)
                                ];
                            }
                        }
                    }
                } else {
                    throw new Exception('Não foi possível extrair VALUES');
                }
            } catch (Exception $e2) {
                $resultados['erros']++;
                $resultados['detalhes'][] = [
                    'numero' => $idx + 1,
                    'tabela' => $table,
                    'status' => 'erro',
                    'motivo' => substr($e->getMessage(), 0, 100)
                ];
            }
        }
    }
    
    return $resultados;
}

// ============================================
// PROCESSAMENTO
// ============================================

$mensagem = '';
$resultados = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK && $senha_verificada) {
    
    $fileTmpPath = $_FILES['sql_file']['tmp_name'];
    $fileName = $_FILES['sql_file']['name'];
    $fileSize = $_FILES['sql_file']['size'];
    
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, ['sql', 'txt'])) {
        $mensagem = '❌ Apenas arquivos .sql ou .txt são permitidos.';
    } elseif ($fileSize > 50 * 1024 * 1024) {
        $mensagem = '❌ Arquivo muito grande. Máximo: 50MB.';
    } else {
        try {
            $sqlContent = file_get_contents($fileTmpPath);
            
            if ($sqlContent === false) {
                throw new Exception('Não foi possível ler o arquivo.');
            }
            
            if (stripos($sqlContent, 'INSERT') === false) {
                throw new Exception('Arquivo sem INSERTs válidos.');
            }
            
            // Executa o SQL diretamente
            $resultados = executarSQLDireto($pdo, $sqlContent);
            
            $mensagem = sprintf(
                '✅ Importação concluída! %d registros processados: %d inseridos, %d ignorados, %d erros.',
                $resultados['total'],
                $resultados['inseridos'],
                $resultados['ignorados'],
                $resultados['erros']
            );
            
        } catch (Exception $e) {
            $mensagem = '❌ Erro: ' . $e->getMessage();
        }
    }
}

// ============================================
// DADOS PARA VIEW
// ============================================
$tabelas = [];
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $tabelas = [];
}
$totalTabelas = count($tabelas);

// ============================================
// INCLUI HEADER (LAYOUT DO SISTEMA)
// ============================================
include $root_dir . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importação SQL - SoftGest</title>
    <style>
        /* Reset e estilos base */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        
        /* Container principal - ocupa toda largura disponível */
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 25px 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header h1 {
            font-size: 24px;
            color: #1a2332;
        }
        .header .badge {
            background: #3498db;
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 14px;
        }
        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .alert.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        .alert.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        .upload-area {
            background: #fafbfc;
            border: 2px dashed #d1d5db;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            cursor: pointer;
            width: 100%;
        }
        .upload-area:hover {
            border-color: #3498db;
            background: #f0f7ff;
        }
        .upload-area .icon {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
        }
        .upload-area h3 {
            font-size: 16px;
            color: #1a2332;
        }
        .upload-area p {
            color: #6b7280;
            font-size: 13px;
        }
        .upload-area .feature {
            margin-top: 6px;
            font-size: 12px;
            color: #22c55e;
            font-weight: 600;
        }
        .upload-area .supported {
            margin-top: 8px;
            font-size: 12px;
            color: #9ca3af;
        }
        
        input[type="file"] { display: none; }
        
        .btn {
            display: inline-block;
            padding: 8px 24px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-success {
            background: #22c55e;
            color: white;
        }
        .btn-success:hover {
            background: #16a34a;
            transform: translateY(-1px);
        }
        .btn-sm {
            padding: 5px 14px;
            font-size: 12px;
        }
        .btn-lg {
            padding: 12px 32px;
            font-size: 16px;
        }
        
        .file-info {
            margin-top: 12px;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 8px;
            display: none;
        }
        .file-info.show { display: block; }
        .file-info .file-name { font-weight: 600; color: #1a2332; }
        .file-info .file-size { color: #6b7280; font-size: 13px; }
        .btn-remove-file {
            background: #fee2e2;
            color: #991b1b;
            border: none;
            padding: 2px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        .btn-remove-file:hover { background: #fca5a5; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 26px;
            font-weight: 700;
            display: block;
        }
        .stat-card .label {
            font-size: 12px;
            color: #6b7280;
            margin-top: 3px;
        }
        .stat-card .number.green { color: #22c55e; }
        .stat-card .number.blue { color: #3b82f6; }
        .stat-card .number.purple { color: #8b5cf6; }
        .stat-card .number.red { color: #ef4444; }
        
        .tables-info {
            margin-top: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            width: 100%;
            overflow-x: auto;
        }
        .tables-info .tag {
            display: inline-block;
            background: white;
            padding: 3px 10px;
            border-radius: 4px;
            margin: 2px;
            font-size: 12px;
            border: 1px solid #e5e7eb;
            word-break: break-all;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 15px;
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover { color: #3498db; }
        
        .progress-container {
            margin: 15px 0;
            display: none;
        }
        .progress-container.show { display: block; }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar .fill {
            height: 100%;
            background: linear-gradient(90deg, #3498db, #60a5fa);
            width: 0%;
            transition: width 0.5s ease;
        }
        .progress-text {
            text-align: center;
            margin-top: 6px;
            font-size: 13px;
            color: #6b7280;
        }
        
        .text-center { text-align: center; }
        .mt-15 { margin-top: 15px; }
        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }
        .mb-20 { margin-bottom: 20px; }
        
        .relatorios {
            margin-top: 20px;
            border-top: 2px solid #f0f2f5;
            padding-top: 20px;
        }
        .relatorios-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 8px 16px;
            border: 2px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .tab-btn:hover { background: #f8fafc; }
        .tab-btn.active {
            border-color: #3498db;
            background: #dbeafe;
            color: #1e40af;
        }
        .tab-btn .badge-count {
            display: inline-block;
            padding: 0 8px;
            border-radius: 10px;
            font-size: 11px;
            color: white;
            margin-left: 4px;
        }
        .tab-btn .badge-count.verde { background: #22c55e; }
        .tab-btn .badge-count.roxo { background: #8b5cf6; }
        .tab-btn .badge-count.vermelho { background: #ef4444; }
        
        .tab-content {
            display: none;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            background: #fafbfc;
        }
        .tab-content.active { display: block; }
        .tab-content table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .tab-content table th {
            background: #f1f5f9;
            text-align: left;
            padding: 8px 10px;
            position: sticky;
            top: 0;
            z-index: 10;
            font-weight: 600;
            color: #1a2332;
        }
        .tab-content table td {
            padding: 6px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .tab-content table tr:hover { background: #f8fafc; }
        .no-data {
            text-align: center;
            padding: 30px;
            color: #9ca3af;
            font-size: 14px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-badge.inserido { background: #d1fae5; color: #065f46; }
        .status-badge.ignorado { background: #f3e8ff; color: #6d28d9; }
        .status-badge.erro { background: #fee2e2; color: #991b1b; }
        
        /* Estilos para o modal de senha */
        .senha-overlay {
            display: <?= $senha_verificada ? 'none' : 'flex' ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .senha-modal {
            background: white;
            padding: 35px 40px;
            border-radius: 16px;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .senha-modal .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .senha-modal h2 {
            color: #1a2332;
            margin-bottom: 8px;
        }
        .senha-modal p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .senha-modal input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            outline: none;
        }
        .senha-modal input[type="password"]:focus {
            border-color: #3498db;
        }
        .senha-modal .btn-senha {
            width: 100%;
            margin-top: 12px;
            padding: 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .senha-modal .btn-senha:hover {
            background: #2980b9;
        }
        .senha-modal .erro-senha {
            color: #ef4444;
            font-size: 13px;
            margin-top: 8px;
        }
        
        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .export-section {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            width: 100%;
        }
        .export-section .icon {
            font-size: 28px;
            vertical-align: middle;
            margin-right: 8px;
        }
        .export-section .desc {
            color: #166534;
            font-size: 14px;
            margin-top: 5px;
        }
        .export-section .flex-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .export-section .info-text {
            font-size: 12px;
            color: #6b7280;
            margin-top: 8px;
        }
        
        /* Responsivo */
        @media (max-width: 640px) {
            .container { padding: 15px; }
            .header { flex-direction: column; gap: 8px; text-align: center; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .relatorios-tabs { flex-direction: column; }
            .senha-modal { padding: 25px 20px; }
            .export-section .flex-container { flex-direction: column; align-items: stretch; }
            .export-section .flex-container .btn { text-align: center; }
        }
    </style>
</head>
<body>

<!-- Modal de Senha -->
<div class="senha-overlay" id="senhaOverlay">
    <div class="senha-modal">
        <div class="icon">🔐</div>
        <h2>Acesso Restrito</h2>
        <p>Digite a senha de acesso para importar ou exportar o banco de dados.</p>
        <form method="POST" action="">
            <input type="password" name="senha_acesso" placeholder="Digite a senha" required autofocus>
            <button type="submit" class="btn-senha">🔓 Verificar Acesso</button>
            <?php if (isset($erro_senha)): ?>
                <div class="erro-senha"><?= $erro_senha ?></div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="container">
    <div class="header">
        <h1>📥 Importar SQL</h1>
        <span class="badge">🔒 Admin</span>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="alert <?= strpos($mensagem, '✅') !== false ? 'success' : (strpos($mensagem, '⚠️') !== false ? 'warning' : 'error') ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($erro_exportacao)): ?>
        <div class="alert error"><?= htmlspecialchars($erro_exportacao) ?></div>
    <?php endif; ?>
    
    <div class="alert info" style="font-size: 13px; padding: 10px 16px; overflow-wrap: break-word;">
        <strong>📊 Banco:</strong> <?= DB_NAME ?> &bull; 
        <strong>📋 Tabelas:</strong> <?= $totalTabelas ?> &bull;
        <strong>⚡ Limite:</strong> Sem limite &bull;
        <strong>🔄 Lógica:</strong> Importa SQL diretamente como phpMyAdmin
    </div>
    
    <!-- Seção de Exportação -->
    <?php if ($senha_verificada): ?>
    <div class="export-section">
        <div class="flex-container">
            <div>
                <span class="icon">💾</span>
                <strong style="font-size: 16px; color: #166534;">Exportar Banco de Dados</strong>
                <div class="desc">Faça backup completo de todas as tabelas do banco <strong><?= DB_NAME ?></strong></div>
            </div>
            <a href="?exportar_banco=1" class="btn btn-success btn-lg btn-export" onclick="return confirm('Deseja exportar o banco de dados completo? O arquivo será baixado automaticamente.');">
                📤 Exportar Banco
            </a>
        </div>
        <div class="info-text">
            ⚡ O arquivo será salvo com o nome: backup_<?= DB_NAME ?>_YYYY-MM-DD_HH-MM-SS.sql
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data" id="uploadForm" style="width: 100%;">
        <div class="upload-area" id="uploadArea">
            <span class="icon">📄</span>
            <h3>Clique ou arraste um arquivo SQL</h3>
            <p>.sql ou .txt até 50MB</p>
            <div class="feature">⚡ Executa SQL diretamente (como phpMyAdmin)</div>
            <div class="supported">Importação exata, sem parsing manual</div>
            <input type="file" name="sql_file" id="sqlFile" accept=".sql,.txt" <?= $senha_verificada ? '' : 'disabled' ?>>
            <div class="mt-10">
                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('sqlFile').click()" <?= $senha_verificada ? '' : 'disabled' ?>>
                    📁 Selecionar
                </button>
            </div>
            <?php if (!$senha_verificada): ?>
                <div style="margin-top: 10px; color: #ef4444; font-size: 13px;">🔒 Verifique a senha para importar</div>
            <?php endif; ?>
        </div>
        
        <div class="file-info" id="fileInfo">
            <span class="file-name" id="fileName">arquivo.sql</span>
            <span class="file-size" id="fileSize">(0 KB)</span>
            <button type="button" class="btn-remove-file" onclick="removeFile()">✕</button>
        </div>
        
        <div class="text-center mt-15">
            <button type="submit" class="btn btn-primary" id="importBtn" disabled <?= $senha_verificada ? '' : 'disabled' ?>>
                🚀 Importar
            </button>
        </div>
    </form>
    
    <div class="progress-container" id="progressContainer">
        <div class="progress-bar"><div class="fill" id="progressFill"></div></div>
        <div class="progress-text" id="progressText">Processando...</div>
    </div>
    
    <?php if ($resultados): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <span class="number blue"><?= $resultados['total'] ?></span>
                <span class="label">Total</span>
            </div>
            <div class="stat-card">
                <span class="number green"><?= $resultados['inseridos'] ?></span>
                <span class="label">✅ Inseridos</span>
            </div>
            <div class="stat-card">
                <span class="number purple"><?= $resultados['ignorados'] ?></span>
                <span class="label">⏭️ Ignorados</span>
            </div>
            <div class="stat-card">
                <span class="number red"><?= $resultados['erros'] ?></span>
                <span class="label">❌ Erros</span>
            </div>
        </div>
        
        <div class="relatorios">
            <h3 style="margin-bottom: 10px; color: #1a2332;">📋 Relatório Detalhado</h3>
            
            <div class="relatorios-tabs">
                <button class="tab-btn active" data-tab="inseridos">
                    ✅ Inseridos
                    <span class="badge-count verde"><?= $resultados['inseridos'] ?></span>
                </button>
                <button class="tab-btn" data-tab="ignorados">
                    ⏭️ Ignorados
                    <span class="badge-count roxo"><?= $resultados['ignorados'] ?></span>
                </button>
                <button class="tab-btn" data-tab="erros">
                    ❌ Erros
                    <span class="badge-count vermelho"><?= $resultados['erros'] ?></span>
                </button>
            </div>
            
            <!-- Inseridos -->
            <div class="tab-content active" id="tab-inseridos">
                <?php 
                $inseridos = array_filter($resultados['detalhes'], function($item) {
                    return $item['status'] === 'inserido';
                });
                ?>
                <?php if (empty($inseridos)): ?>
                    <div class="no-data">Nenhum registro inserido</div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>#</th><th>Tabela</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($inseridos as $item): ?>
                                <tr>
                                    <td><?= $item['numero'] ?></td>
                                    <td><strong><?= htmlspecialchars($item['tabela']) ?></strong></td>
                                    <td><span class="status-badge inserido">✅ Inserido</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Ignorados -->
            <div class="tab-content" id="tab-ignorados">
                <?php 
                $ignorados = array_filter($resultados['detalhes'], function($item) {
                    return $item['status'] === 'ignorado';
                });
                ?>
                <?php if (empty($ignorados)): ?>
                    <div class="no-data">Nenhum registro ignorado</div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>#</th><th>Tabela</th><th>Motivo</th></tr></thead>
                        <tbody>
                            <?php foreach ($ignorados as $item): ?>
                                <tr>
                                    <td><?= $item['numero'] ?></td>
                                    <td><strong><?= htmlspecialchars($item['tabela']) ?></strong></td>
                                    <td style="color: #8b5cf6;"><?= htmlspecialchars($item['motivo']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Erros -->
            <div class="tab-content" id="tab-erros">
                <?php 
                $erros = array_filter($resultados['detalhes'], function($item) {
                    return $item['status'] === 'erro';
                });
                ?>
                <?php if (empty($erros)): ?>
                    <div class="no-data">Nenhum erro ocorreu</div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>#</th><th>Tabela</th><th>Erro</th></tr></thead>
                        <tbody>
                            <?php foreach ($erros as $item): ?>
                                <tr>
                                    <td><?= $item['numero'] ?></td>
                                    <td><strong><?= htmlspecialchars($item['tabela']) ?></strong></td>
                                    <td style="color: #ef4444;"><?= htmlspecialchars($item['motivo']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Lista de tabelas com scroll horizontal -->
    <div class="tables-info">
        <strong>🗂️ Tabelas (<?= $totalTabelas ?>):</strong>
        <div style="margin-top: 5px; display: flex; flex-wrap: wrap; gap: 4px;">
            <?php foreach ($tabelas as $t): ?>
                <span class="tag"><?= htmlspecialchars($t) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    
    <a href="../index.php" class="back-link">← Voltar ao Dashboard</a>
</div>

<script>
const fileInput = document.getElementById('sqlFile');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');
const importBtn = document.getElementById('importBtn');
const uploadArea = document.getElementById('uploadArea');

// Tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const tabId = this.dataset.tab;
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
    });
});

// File input
fileInput.addEventListener('change', function() {
    if (this.files && this.files.length > 0) {
        const file = this.files[0];
        fileName.textContent = file.name;
        fileSize.textContent = `(${(file.size / 1024).toFixed(1)} KB)`;
        fileInfo.classList.add('show');
        importBtn.disabled = false;
        uploadArea.style.borderColor = '#22c55e';
    } else {
        removeFile();
    }
});

function removeFile() {
    fileInput.value = '';
    fileInfo.classList.remove('show');
    importBtn.disabled = true;
    uploadArea.style.borderColor = '#d1d5db';
}

// Drag and drop
uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.style.borderColor = '#3498db'; });
uploadArea.addEventListener('dragleave', e => { e.preventDefault(); uploadArea.style.borderColor = '#d1d5db'; });
uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '#d1d5db';
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        fileInput.dispatchEvent(new Event('change'));
    }
});

uploadArea.addEventListener('click', function(e) {
    if (e.target === this || e.target.closest('.upload-area')) {
        fileInput.click();
    }
});

// Progress
document.getElementById('uploadForm').addEventListener('submit', function() {
    const pc = document.getElementById('progressContainer');
    const pf = document.getElementById('progressFill');
    const pt = document.getElementById('progressText');
    const btn = document.getElementById('importBtn');
    
    pc.classList.add('show');
    btn.disabled = true;
    btn.textContent = '⏳ Processando...';
    
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 10 + 5;
        if (progress > 90) { progress = 90; clearInterval(interval); }
        pf.style.width = progress + '%';
        pt.textContent = `Importando... ${Math.floor(progress)}%`;
    }, 150);
});
</script>

</body>
</html>