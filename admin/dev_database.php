<?php
// ============================================
// admin/dev_database.php - Gerenciador de Banco
// ============================================

session_start();

// Verificar autenticação
if (!isset($_SESSION['dev_authenticated']) || $_SESSION['dev_authenticated'] !== true) {
    header('Location: dev_auth.php');
    exit;
}

// Verificar tempo de sessão (60 minutos)
if (isset($_SESSION['dev_auth_time']) && (time() - $_SESSION['dev_auth_time'] > 3600)) {
    session_destroy();
    header('Location: dev_auth.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/app_modes.php';

// Conectar ao banco
try {
    $pdo = conectarBanco();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// ===== PROCESSAR AÇÕES =====
$message = '';
$messageType = '';

// --- Adicionar registro ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $table = $_POST['table'] ?? '';
    $columns = $_POST['columns'] ?? [];
    $values = $_POST['values'] ?? [];
    
    if ($table && !empty($columns) && !empty($values)) {
        try {
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $sql = "INSERT INTO `$table` (" . implode(',', array_map(function($c) { return "`$c`"; }, $columns)) . ") VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $message = "✅ Registro adicionado com sucesso!";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "❌ Erro ao adicionar: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// --- Editar registro (formulário) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $table = $_POST['table'] ?? '';
    $id = $_POST['id'] ?? 0;
    $columns = $_POST['columns'] ?? [];
    $values = $_POST['values'] ?? [];
    $primaryKey = $_POST['primary_key'] ?? 'id';
    
    if ($table && $id && !empty($columns)) {
        try {
            $setParts = [];
            foreach ($columns as $col) {
                $setParts[] = "`$col` = ?";
            }
            $values[] = $id;
            $sql = "UPDATE `$table` SET " . implode(',', $setParts) . " WHERE `$primaryKey` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $message = "✅ Registro atualizado com sucesso!";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "❌ Erro ao atualizar: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// --- Edição inline (AJAX) ---
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'inline_edit') {
    header('Content-Type: application/json');
    
    $table = $_POST['table'] ?? '';
    $id = $_POST['id'] ?? 0;
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    $primaryKey = $_POST['primary_key'] ?? 'id';
    
    if ($table && $id && $field) {
        try {
            // Verificar se a tabela existe
            $checkTable = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount();
            if ($checkTable == 0) {
                echo json_encode(['success' => false, 'message' => 'Tabela não encontrada']);
                exit;
            }
            
            // Verificar se a coluna existe
            $checkColumn = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$field'")->rowCount();
            if ($checkColumn == 0) {
                echo json_encode(['success' => false, 'message' => 'Coluna não encontrada']);
                exit;
            }
            
            $sql = "UPDATE `$table` SET `$field` = ? WHERE `$primaryKey` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$value, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Atualizado com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro SQL: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    }
    exit;
}

// --- Excluir registro ---
if (isset($_GET['delete'])) {
    $table = $_GET['table'] ?? '';
    $id = $_GET['id'] ?? 0;
    $primaryKey = $_GET['primary_key'] ?? 'id';
    
    if ($table && $id) {
        try {
            $sql = "DELETE FROM `$table` WHERE `$primaryKey` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $message = "✅ Registro excluído com sucesso!";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "❌ Erro ao excluir: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// --- Exportar banco completo ---
if (isset($_GET['export']) && $_GET['export'] === 'full') {
    exportFullDatabase($pdo);
    exit;
}

// --- Exportar tabela específica ---
if (isset($_GET['export']) && $_GET['export'] === 'table' && isset($_GET['table_name'])) {
    exportTable($pdo, $_GET['table_name']);
    exit;
}

// --- Exportar para Excel (CSV) ---
if (isset($_GET['export_excel']) && $_GET['export_excel'] === '1' && isset($_GET['table'])) {
    exportExcel($pdo, $_GET['table'], $_GET);
    exit;
}

// --- Exportar para Excel (HTML) ---
if (isset($_GET['export_excel_html']) && $_GET['export_excel_html'] === '1' && isset($_GET['table'])) {
    exportExcelHtml($pdo, $_GET['table'], $_GET);
    exit;
}

// --- Importar arquivo SQL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_sql') {
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['sql_file']['tmp_name'];
        $sql = file_get_contents($file);
        
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Dividir em múltiplas queries
            $queries = explode(';', $sql);
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $message = "✅ Arquivo SQL importado com sucesso!";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "❌ Erro ao importar SQL: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "❌ Erro no upload do arquivo.";
        $messageType = 'error';
    }
}

// ===== FUNÇÕES DE EXPORTAÇÃO =====
function exportFullDatabase($pdo) {
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $output = "-- =============================================\n";
        $output .= "-- EXPORTAÇÃO COMPLETA DO BANCO DE DADOS\n";
        $output .= "-- Data: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- =============================================\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            // Estrutura da tabela
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $output .= "-- ===== Estrutura da tabela: $table =====\n";
            $output .= $create['Create Table'] . ";\n\n";
            
            // Dados
            $data = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($data)) {
                $output .= "-- ===== Dados da tabela: $table =====\n";
                $columns = array_keys($data[0]);
                foreach ($data as $row) {
                    $values = array_map(function($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote($v);
                    }, array_values($row));
                    $output .= "INSERT INTO `$table` (" . implode(',', array_map(function($c) { return "`$c`"; }, $columns)) . ") VALUES (" . implode(',', $values) . ");\n";
                }
                $output .= "\n";
            }
        }
        
        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Baixar arquivo
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="backup_' . date('Ymd_His') . '.sql"');
        echo $output;
        exit;
        
    } catch (Exception $e) {
        die("Erro na exportação: " . $e->getMessage());
    }
}

function exportTable($pdo, $table) {
    try {
        $output = "-- =============================================\n";
        $output .= "-- EXPORTAÇÃO DA TABELA: $table\n";
        $output .= "-- Data: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- =============================================\n\n";
        
        // Estrutura
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $output .= $create['Create Table'] . ";\n\n";
        
        // Dados
        $data = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($data)) {
            $columns = array_keys($data[0]);
            foreach ($data as $row) {
                $values = array_map(function($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote($v);
                }, array_values($row));
                $output .= "INSERT INTO `$table` (" . implode(',', array_map(function($c) { return "`$c`"; }, $columns)) . ") VALUES (" . implode(',', $values) . ");\n";
            }
        }
        
        // Baixar arquivo
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $table . '_' . date('Ymd_His') . '.sql"');
        echo $output;
        exit;
        
    } catch (Exception $e) {
        die("Erro na exportação: " . $e->getMessage());
    }
}

// ===== FUNÇÃO DE EXPORTAÇÃO PARA EXCEL (CSV) =====
function exportExcel($pdo, $table, $params) {
    try {
        // Buscar colunas
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        // Identificar chave primária
        $primaryKey = 'id';
        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI') {
                $primaryKey = $col['Field'];
                break;
            }
        }
        
        // Construir query com filtros
        $whereConditions = [];
        $paramsList = [];
        
        // Busca global
        $searchTerm = $params['search'] ?? '';
        if (!empty($searchTerm)) {
            $searchConditions = [];
            foreach ($columns as $col) {
                $searchConditions[] = "`{$col['Field']}` LIKE ?";
                $paramsList[] = "%$searchTerm%";
            }
            $whereConditions[] = "(" . implode(' OR ', $searchConditions) . ")";
        }
        
        // Filtros
        $filters = [];
        if (isset($params['filters']) && is_array($params['filters'])) {
            $filters = $params['filters'];
        } else {
            foreach ($_GET as $key => $value) {
                if (strpos($key, 'filter_') === 0 && !empty($value)) {
                    $field = substr($key, 7);
                    $filters[$field] = $value;
                }
            }
        }
        
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $whereConditions[] = "`$field` LIKE ?";
                $paramsList[] = "%$value%";
            }
        }
        
        // Ordenação
        $orderBy = $params['order_by'] ?? $primaryKey;
        $orderDir = $params['order_dir'] ?? 'DESC';
        
        // Buscar dados
        $sql = "SELECT * FROM `$table`";
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        $sql .= " ORDER BY `$orderBy` $orderDir";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsList);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Gerar CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $table . '_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Adicionar BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        $delimiter = ';';
        
        // Cabeçalho
        fputcsv($output, $columnNames, $delimiter);
        
        // Dados - preservando exatamente como estão
        foreach ($data as $row) {
            $line = [];
            foreach ($columnNames as $col) {
                $value = $row[$col] ?? '';
                $line[] = $value;
            }
            fputcsv($output, $line, $delimiter);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        die("Erro na exportação Excel: " . $e->getMessage());
    }
}

// ===== FUNÇÃO DE EXPORTAÇÃO PARA EXCEL (HTML) =====
function exportExcelHtml($pdo, $table, $params) {
    try {
        // Buscar colunas
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        // Identificar chave primária
        $primaryKey = 'id';
        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI') {
                $primaryKey = $col['Field'];
                break;
            }
        }
        
        // Construir query com filtros
        $whereConditions = [];
        $paramsList = [];
        
        $searchTerm = $params['search'] ?? '';
        if (!empty($searchTerm)) {
            $searchConditions = [];
            foreach ($columns as $col) {
                $searchConditions[] = "`{$col['Field']}` LIKE ?";
                $paramsList[] = "%$searchTerm%";
            }
            $whereConditions[] = "(" . implode(' OR ', $searchConditions) . ")";
        }
        
        $filters = [];
        if (isset($params['filters']) && is_array($params['filters'])) {
            $filters = $params['filters'];
        } else {
            foreach ($_GET as $key => $value) {
                if (strpos($key, 'filter_') === 0 && !empty($value)) {
                    $field = substr($key, 7);
                    $filters[$field] = $value;
                }
            }
        }
        
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $whereConditions[] = "`$field` LIKE ?";
                $paramsList[] = "%$value%";
            }
        }
        
        $orderBy = $params['order_by'] ?? $primaryKey;
        $orderDir = $params['order_dir'] ?? 'DESC';
        
        $sql = "SELECT * FROM `$table`";
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        $sql .= " ORDER BY `$orderBy` $orderDir";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsList);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Exportar como HTML (abre no Excel e preserva caracteres)
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $table . '_' . date('Ymd_His') . '.xls"');
        
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . $table . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '</head><body>';
        echo '<table border="1">';
        
        // Cabeçalho
        echo '<tr>';
        foreach ($columnNames as $col) {
            echo '<th>' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr>';
        
        // Dados - preservando caracteres
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($columnNames as $col) {
                $value = $row[$col] ?? '';
                echo '<td>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        
        echo '</table></body></html>';
        exit;
        
    } catch (Exception $e) {
        die("Erro na exportação Excel: " . $e->getMessage());
    }
}

// ===== BUSCAR TABELAS =====
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// ===== BUSCAR DADOS DA TABELA SELECIONADA =====
$selectedTable = $_GET['table'] ?? ($tables[0] ?? '');
$tableData = [];
$tableColumns = [];
$primaryKey = 'id';
$totalRecords = 0;
$showAll = isset($_GET['show_all']) && $_GET['show_all'] === '1';

// Variáveis de busca e filtro
$searchTerm = $_GET['search'] ?? '';
$filterColumn = $_GET['filter_column'] ?? '';
$filterValue = $_GET['filter_value'] ?? '';
$orderBy = $_GET['order_by'] ?? '';
$orderDir = $_GET['order_dir'] ?? 'ASC';

// Filtros múltiplos
$multiFilters = [];
if (isset($_GET['filters']) && is_string($_GET['filters'])) {
    $filterParts = explode(';', $_GET['filters']);
    foreach ($filterParts as $part) {
        if (strpos($part, ':') !== false) {
            list($field, $value) = explode(':', $part, 2);
            if (!empty($field) && !empty($value)) {
                $multiFilters[$field] = $value;
            }
        }
    }
} elseif (isset($_SESSION['multi_filters'][$selectedTable])) {
    $multiFilters = $_SESSION['multi_filters'][$selectedTable];
}

if ($selectedTable) {
    try {
        // Buscar colunas
        $columns = $pdo->query("SHOW COLUMNS FROM `$selectedTable`")->fetchAll(PDO::FETCH_ASSOC);
        $tableColumns = $columns;
        
        // Identificar chave primária
        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI') {
                $primaryKey = $col['Field'];
                break;
            }
        }
        
        // Construir query com filtros
        $whereConditions = [];
        $params = [];
        
        // Busca global
        if (!empty($searchTerm)) {
            $searchConditions = [];
            foreach ($columns as $col) {
                $searchConditions[] = "`{$col['Field']}` LIKE ?";
                $params[] = "%$searchTerm%";
            }
            $whereConditions[] = "(" . implode(' OR ', $searchConditions) . ")";
        }
        
        // Filtro por coluna específica (simples)
        if (!empty($filterColumn) && !empty($filterValue)) {
            $whereConditions[] = "`$filterColumn` LIKE ?";
            $params[] = "%$filterValue%";
        }
        
        // Filtros múltiplos
        foreach ($multiFilters as $field => $value) {
            if (!empty($value)) {
                $whereConditions[] = "`$field` LIKE ?";
                $params[] = "%$value%";
            }
        }
        
        // Contar total de registros
        $countSql = "SELECT COUNT(*) FROM `$selectedTable`";
        if (!empty($whereConditions)) {
            $countSql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();
        
        // Construir query final
        $sql = "SELECT * FROM `$selectedTable`";
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        // Ordenação
        if (!empty($orderBy)) {
            $sql .= " ORDER BY `$orderBy` $orderDir";
        } else {
            $sql .= " ORDER BY `$primaryKey` DESC";
        }
        
        // Limite (apenas se não mostrar todos)
        if (!$showAll) {
            $sql .= " LIMIT 100";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $message = "Erro ao carregar tabela: " . $e->getMessage();
        $messageType = 'error';
    }
}

// ===== CONTAR REGISTROS =====
$rowCounts = [];
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $rowCounts[$table] = $count;
    } catch (Exception $e) {
        $rowCounts[$table] = '?';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Gerenciador de Banco de Dados</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a1a;
            color: #e0e0e0;
            min-height: 100vh;
        }
        .dev-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .dev-header {
            background: linear-gradient(135deg, #1a1a3e, #2d1b69);
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            border: 1px solid rgba(108, 99, 255, 0.2);
        }
        .dev-header h1 {
            font-size: 28px;
            background: linear-gradient(135deg, #6c63ff, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .dev-header .sub {
            font-size: 14px;
            color: rgba(255,255,255,0.5);
        }
        .dev-header .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-primary { background: #6c63ff; color: #fff; }
        .btn-primary:hover { background: #5a52e0; transform: translateY(-2px); }
        .btn-success { background: #22c55e; color: #fff; }
        .btn-success:hover { background: #16a34a; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-warning { background: #f59e0b; color: #fff; }
        .btn-warning:hover { background: #d97706; transform: translateY(-2px); }
        .btn-info { background: #3b82f6; color: #fff; }
        .btn-info:hover { background: #2563eb; transform: translateY(-2px); }
        .btn-excel { background: #217346; color: #fff; }
        .btn-excel:hover { background: #1a5c38; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 2px solid rgba(255,255,255,0.2); color: #fff; }
        .btn-outline:hover { background: rgba(255,255,255,0.1); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-xs { padding: 3px 8px; font-size: 11px; border-radius: 4px; }
        .btn-back { background: rgba(255,255,255,0.1); color: #fff; }
        .btn-back:hover { background: rgba(255,255,255,0.2); }
        
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message.success { background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); color: #4ade80; }
        .message.error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        
        .dev-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
        }
        @media (max-width: 992px) {
            .dev-grid { grid-template-columns: 1fr; }
        }
        
        .table-list {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.06);
            max-height: 80vh;
            overflow-y: auto;
        }
        .table-list h3 {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        .table-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 3px;
            text-decoration: none;
            color: #e0e0e0;
        }
        .table-list-item:hover {
            background: rgba(255,255,255,0.08);
        }
        .table-list-item.active {
            background: rgba(108, 99, 255, 0.2);
            border-left: 3px solid #6c63ff;
        }
        .table-list-item .name {
            font-weight: 500;
        }
        .table-list-item .count {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.06);
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .table-content {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .table-toolbar h2 {
            font-size: 20px;
            color: #fff;
        }
        .table-toolbar .badge {
            background: rgba(108, 99, 255, 0.2);
            color: #a78bfa;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        .table-toolbar .badge-info {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }
        
        /* Filtros e Busca */
        .filter-section {
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .filter-row .search-group {
            flex: 1;
            min-width: 200px;
            display: flex;
            gap: 8px;
        }
        .filter-row .search-group input {
            flex: 1;
            padding: 8px 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
            min-width: 150px;
        }
        .filter-row .search-group input:focus {
            border-color: #6c63ff;
        }
        .filter-row .filter-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .filter-row select {
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #fff;
            font-size: 13px;
            outline: none;
            cursor: pointer;
        }
        .filter-row select option {
            background: #1a1a3e;
        }
        .filter-row select:focus {
            border-color: #6c63ff;
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-actions .btn {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        /* Filtros Múltiplos */
        .multi-filters {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .multi-filters-title {
            color: rgba(255,255,255,0.5);
            font-size: 13px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .multi-filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        .multi-filter-item {
            display: flex;
            gap: 6px;
            align-items: center;
            background: rgba(255,255,255,0.03);
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .multi-filter-item select {
            flex: 1;
            padding: 5px 8px;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 12px;
            outline: none;
            min-width: 80px;
        }
        .multi-filter-item select option {
            background: #1a1a3e;
        }
        .multi-filter-item input {
            flex: 1;
            padding: 5px 8px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 4px;
            color: #fff;
            font-size: 12px;
            outline: none;
            min-width: 60px;
        }
        .multi-filter-item input:focus {
            border-color: #6c63ff;
        }
        .multi-filter-item .remove-filter-btn {
            background: none;
            border: none;
            color: rgba(255,255,255,0.3);
            cursor: pointer;
            font-size: 14px;
            padding: 0 4px;
            transition: color 0.3s;
        }
        .multi-filter-item .remove-filter-btn:hover {
            color: #ef4444;
        }
        .add-filter-btn {
            background: rgba(108, 99, 255, 0.15);
            border: 1px dashed rgba(108, 99, 255, 0.3);
            color: #a78bfa;
            padding: 4px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        .add-filter-btn:hover {
            background: rgba(108, 99, 255, 0.25);
        }
        
        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(108, 99, 255, 0.15);
            color: #a78bfa;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            margin-top: 10px;
        }
        .filter-badge .remove-filter {
            cursor: pointer;
            color: rgba(255,255,255,0.4);
            transition: color 0.3s;
        }
        .filter-badge .remove-filter:hover {
            color: #ef4444;
        }
        
        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            max-height: 600px;
            overflow-y: auto;
        }
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .table-wrapper table th {
            background: rgba(108, 99, 255, 0.15);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #a78bfa;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
            cursor: pointer;
            user-select: none;
            transition: background 0.3s;
        }
        .table-wrapper table th:hover {
            background: rgba(108, 99, 255, 0.25);
        }
        .table-wrapper table th .sort-icon {
            margin-left: 5px;
            opacity: 0.3;
        }
        .table-wrapper table th .sort-icon.active {
            opacity: 1;
            color: #a78bfa;
        }
        .table-wrapper table td {
            padding: 10px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            position: relative;
        }
        .table-wrapper table tr:hover td {
            background: rgba(255,255,255,0.03);
        }
        
        /* Estilo para edição inline */
        .table-wrapper table td.editable {
            cursor: pointer;
            transition: background 0.3s;
        }
        .table-wrapper table td.editable:hover {
            background: rgba(108, 99, 255, 0.1);
        }
        .table-wrapper table td .edit-hint {
            display: none;
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            color: rgba(108, 99, 255, 0.4);
        }
        .table-wrapper table td.editable:hover .edit-hint {
            display: block;
        }
        .table-wrapper table td .inline-input {
            display: none;
            width: 100%;
            padding: 4px 8px;
            background: rgba(255,255,255,0.1);
            border: 2px solid #6c63ff;
            border-radius: 4px;
            color: #fff;
            font-size: 14px;
            outline: none;
        }
        .table-wrapper table td .inline-input:focus {
            background: rgba(255,255,255,0.15);
        }
        .table-wrapper table td .inline-value {
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .table-wrapper table td.editing .inline-value {
            display: none;
        }
        .table-wrapper table td.editing .inline-input {
            display: block;
        }
        .table-wrapper table td .save-indicator {
            display: none;
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
        }
        .table-wrapper table td.saving .save-indicator {
            display: block;
        }
        
        .table-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255,255,255,0.3);
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 15px; display: block; }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #1a1a3e;
            border-radius: 16px;
            padding: 30px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .modal h2 { color: #fff; margin-bottom: 20px; }
        .modal .form-group {
            margin-bottom: 15px;
        }
        .modal .form-group label {
            display: block;
            color: rgba(255,255,255,0.7);
            font-size: 13px;
            margin-bottom: 5px;
        }
        .modal .form-group input,
        .modal .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }
        .modal .form-group input:focus,
        .modal .form-group textarea:focus {
            border-color: #6c63ff;
        }
        .modal .form-group textarea {
            min-height: 80px;
            resize: vertical;
            font-family: monospace;
        }
        .modal .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .import-area {
            background: rgba(255,255,255,0.03);
            border: 2px dashed rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin-top: 20px;
        }
        .import-area input[type="file"] {
            display: none;
        }
        .import-area label {
            display: inline-block;
            padding: 12px 30px;
            background: rgba(108, 99, 255, 0.2);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: #a78bfa;
            font-weight: 600;
        }
        .import-area label:hover {
            background: rgba(108, 99, 255, 0.3);
        }
        
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            z-index: 9999;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease;
            pointer-events: none;
            max-width: 400px;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast.success { background: rgba(34, 197, 94, 0.95); }
        .toast.error { background: rgba(239, 68, 68, 0.95); }
        .toast.info { background: rgba(108, 99, 255, 0.95); }
        
        .record-info {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
            padding: 8px 0;
        }
        .record-info strong {
            color: rgba(255,255,255,0.8);
        }
        
        @media (max-width: 768px) {
            .dev-header { flex-direction: column; align-items: stretch; }
            .dev-header .actions { justify-content: center; }
            .table-toolbar { flex-direction: column; align-items: stretch; }
            .table-actions { flex-wrap: wrap; }
            .table-wrapper table td, .table-wrapper table th { padding: 8px 10px; font-size: 12px; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-row .search-group { flex-direction: column; }
            .filter-row .filter-group { flex-wrap: wrap; }
            .multi-filters-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dev-container">
        <!-- HEADER -->
        <div class="dev-header">
            <div>
                <h1>🔧 Gerenciador de Banco de Dados</h1>
                <div class="sub">Modo Desenvolvedor • <?= APP_MODE_NAME ?> • <?= DB_TYPE ?></div>
            </div>
            <div class="actions">
                <a href="../index.php" class="btn btn-back">← Dashboard</a>
                <a href="?export=full" class="btn btn-success">💾 Exportar Tudo</a>
                <a href="#" onclick="document.getElementById('importModal').classList.add('active')" class="btn btn-warning">📥 Importar SQL</a>
                <a href="dev_auth.php?logout=1" class="btn btn-danger" onclick="return confirm('Sair do modo desenvolvedor?')">🚪 Sair</a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="message <?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <!-- CONTEÚDO -->
        <div class="dev-grid">
            <!-- Lista de Tabelas -->
            <div class="table-list">
                <h3>📋 Tabelas</h3>
                <?php foreach ($tables as $table): ?>
                <a href="?table=<?= urlencode($table) ?>" class="table-list-item <?= $selectedTable === $table ? 'active' : '' ?>">
                    <span class="name"><?= htmlspecialchars($table) ?></span>
                    <span class="count"><?= $rowCounts[$table] ?? '?' ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Conteúdo da Tabela -->
            <div class="table-content">
                <?php if ($selectedTable): ?>
                <div class="table-toolbar">
                    <div>
                        <h2>📊 <?= htmlspecialchars($selectedTable) ?></h2>
                        <span class="badge"><?= count($tableData) ?> exibidos</span>
                        <span class="badge badge-info">Total: <?= number_format($totalRecords) ?> registros</span>
                        <?php if ($showAll): ?>
                        <span class="badge" style="background: rgba(34, 197, 94, 0.2); color: #4ade80;">📄 Todos os registros</span>
                        <?php else: ?>
                        <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #fbbf24;">📄 Últimos 100</span>
                        <?php endif; ?>
                        <span class="badge" style="background: rgba(34, 197, 94, 0.2); color: #4ade80; margin-left: 5px;">
                            ⚡ Duplo clique para editar
                        </span>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a href="?export_excel_html=1&table=<?= urlencode($selectedTable) ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?><?= !empty($filterColumn) ? '&filter_column=' . urlencode($filterColumn) : '' ?><?= !empty($filterValue) ? '&filter_value=' . urlencode($filterValue) : '' ?><?= !empty($orderBy) ? '&order_by=' . urlencode($orderBy) : '' ?><?= !empty($orderDir) ? '&order_dir=' . urlencode($orderDir) : '' ?><?= !empty($_GET['filters']) ? '&filters=' . urlencode($_GET['filters']) : '' ?>" class="btn btn-excel btn-sm">📊 Excel</a>
                        <a href="?export_excel=1&table=<?= urlencode($selectedTable) ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?><?= !empty($filterColumn) ? '&filter_column=' . urlencode($filterColumn) : '' ?><?= !empty($filterValue) ? '&filter_value=' . urlencode($filterValue) : '' ?><?= !empty($orderBy) ? '&order_by=' . urlencode($orderBy) : '' ?><?= !empty($orderDir) ? '&order_dir=' . urlencode($orderDir) : '' ?><?= !empty($_GET['filters']) ? '&filters=' . urlencode($_GET['filters']) : '' ?>" class="btn btn-excel btn-sm">📊 CSV</a>
                        <a href="?export=table&table_name=<?= urlencode($selectedTable) ?>" class="btn btn-success btn-sm">📤 SQL</a>
                        <button onclick="openAddModal('<?= htmlspecialchars($selectedTable) ?>')" class="btn btn-primary btn-sm">➕ Adicionar</button>
                        <button onclick="openEditModal()" class="btn btn-warning btn-sm" id="editBtn" disabled>✏️ Editar</button>
                        <button onclick="openDeleteModal()" class="btn btn-danger btn-sm" id="deleteBtn" disabled>🗑️ Excluir</button>
                        <button onclick="refreshTable()" class="btn btn-outline btn-sm">🔄 Atualizar</button>
                    </div>
                </div>
                
                <!-- Seção de Filtros e Busca -->
                <div class="filter-section">
                    <form method="GET" id="filterForm" onsubmit="return applyFilters()">
                        <input type="hidden" name="table" value="<?= htmlspecialchars($selectedTable) ?>">
                        <input type="hidden" name="show_all" id="showAllHidden" value="<?= $showAll ? '1' : '0' ?>">
                        <input type="hidden" name="filters" id="filtersHidden" value="">
                        
                        <div class="filter-row">
                            <!-- Busca Global -->
                            <div class="search-group">
                                <input type="text" name="search" id="searchInput" placeholder="🔍 Buscar em todos os campos..." value="<?= htmlspecialchars($searchTerm) ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
                            </div>
                            
                            <!-- Filtro por Coluna (simples) -->
                            <div class="filter-group">
                                <select name="filter_column" id="filterColumn">
                                    <option value="">Filtrar por...</option>
                                    <?php foreach ($tableColumns as $col): ?>
                                    <option value="<?= htmlspecialchars($col['Field']) ?>" <?= $filterColumn === $col['Field'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($col['Field']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="filter_value" id="filterValue" placeholder="Valor do filtro..." value="<?= htmlspecialchars($filterValue) ?>" style="padding:8px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;font-size:14px;outline:none;min-width:120px;">
                                <button type="submit" class="btn btn-info btn-sm">🔍 Filtrar</button>
                            </div>
                            
                            <!-- Ações -->
                            <div class="filter-actions">
                                <?php if (!empty($searchTerm) || !empty($filterColumn) || !empty($filterValue) || !empty($multiFilters)): ?>
                                <a href="?table=<?= urlencode($selectedTable) ?>&<?= $showAll ? 'show_all=1' : '' ?>" class="btn btn-outline btn-sm">❌ Limpar Filtros</a>
                                <?php endif; ?>
                                <a href="?table=<?= urlencode($selectedTable) ?>&show_all=<?= $showAll ? '0' : '1' ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?><?= !empty($filterColumn) ? '&filter_column=' . urlencode($filterColumn) : '' ?><?= !empty($filterValue) ? '&filter_value=' . urlencode($filterValue) : '' ?><?= !empty($orderBy) ? '&order_by=' . urlencode($orderBy) : '' ?><?= !empty($orderDir) ? '&order_dir=' . urlencode($orderDir) : '' ?>" class="btn btn-<?= $showAll ? 'warning' : 'outline' ?> btn-sm">
                                    <?= $showAll ? '📄 Mostrar últimos 100' : '📄 Mostrar todos' ?>
                                </a>
                                <button type="button" class="btn btn-primary btn-sm" onclick="toggleMultiFilters()">🔧 Filtros Avançados</button>
                            </div>
                        </div>
                        
                        <!-- Filtros Múltiplos -->
                        <div class="multi-filters" id="multiFiltersSection" style="<?= empty($multiFilters) ? 'display:none;' : '' ?>">
                            <div class="multi-filters-title">
                                <span>🎯 Filtros por campos específicos</span>
                                <button type="button" class="add-filter-btn" onclick="addFilterRow()">+ Adicionar Filtro</button>
                            </div>
                            <div class="multi-filters-grid" id="multiFiltersGrid">
                                <?php 
                                $filterIndex = 0;
                                foreach ($multiFilters as $field => $value): 
                                    if (empty($value)) continue;
                                ?>
                                <div class="multi-filter-item" data-index="<?= $filterIndex ?>">
                                    <select name="multi_filter_field_<?= $filterIndex ?>" class="multi-filter-field">
                                        <?php foreach ($tableColumns as $col): ?>
                                        <option value="<?= htmlspecialchars($col['Field']) ?>" <?= $field === $col['Field'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($col['Field']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="multi_filter_value_<?= $filterIndex ?>" class="multi-filter-value" placeholder="Valor..." value="<?= htmlspecialchars($value) ?>">
                                    <button type="button" class="remove-filter-btn" onclick="removeFilterRow(this)" title="Remover filtro">✕</button>
                                </div>
                                <?php 
                                $filterIndex++;
                                endforeach; 
                                ?>
                            </div>
                            <div style="margin-top: 10px;">
                                <button type="submit" class="btn btn-info btn-sm">✅ Aplicar Filtros</button>
                            </div>
                        </div>
                        
                        <!-- Filtros ativos (badges) -->
                        <?php if (!empty($searchTerm) || !empty($filterColumn) || !empty($filterValue) || !empty($multiFilters) || !empty($orderBy)): ?>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
                            <?php if (!empty($searchTerm)): ?>
                            <span class="filter-badge">🔍 Busca: "<?= htmlspecialchars($searchTerm) ?>" <span class="remove-filter" onclick="window.location.href='?table=<?= urlencode($selectedTable) ?>&<?= $showAll ? 'show_all=1' : '' ?>'">✕</span></span>
                            <?php endif; ?>
                            <?php if (!empty($filterColumn) && !empty($filterValue)): ?>
                            <span class="filter-badge">📌 <?= htmlspecialchars($filterColumn) ?>: "<?= htmlspecialchars($filterValue) ?>" <span class="remove-filter" onclick="window.location.href='?table=<?= urlencode($selectedTable) ?>&<?= $showAll ? 'show_all=1' : '' ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>'">✕</span></span>
                            <?php endif; ?>
                            <?php foreach ($multiFilters as $field => $value): if (empty($value)) continue; ?>
                            <span class="filter-badge">📌 <?= htmlspecialchars($field) ?>: "<?= htmlspecialchars($value) ?>" <span class="remove-filter" onclick="removeMultiFilter('<?= htmlspecialchars($field) ?>')">✕</span></span>
                            <?php endforeach; ?>
                            <?php if (!empty($orderBy)): ?>
                            <span class="filter-badge">📊 Ordenado por: <?= htmlspecialchars($orderBy) ?> (<?= $orderDir ?>)</span>
                            <?php endif; ?>
                            <?php if ($showAll): ?>
                            <span class="filter-badge" style="background:rgba(34,197,94,0.15);color:#4ade80;">📄 Todos os registros</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Informações de Registros -->
                <div class="record-info">
                    Mostrando <strong><?= count($tableData) ?></strong> de <strong><?= number_format($totalRecords) ?></strong> registros
                    <?php if (!$showAll && $totalRecords > 100): ?>
                    (exibindo os 100 mais recentes)
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($tableData)): ?>
                <div class="table-wrapper" id="tableWrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 30px;">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                </th>
                                <?php foreach ($tableColumns as $col): 
                                    $field = $col['Field'];
                                    $isOrdered = $orderBy === $field;
                                    $newDir = $isOrdered && $orderDir === 'ASC' ? 'DESC' : 'ASC';
                                ?>
                                <th onclick="sortTable('<?= htmlspecialchars($field) ?>', '<?= $isOrdered && $orderDir === 'ASC' ? 'DESC' : 'ASC' ?>')">
                                    <?= htmlspecialchars($field) ?>
                                    <span class="sort-icon <?= $isOrdered ? 'active' : '' ?>">
                                        <?php if ($isOrdered): ?>
                                            <?= $orderDir === 'ASC' ? '↑' : '↓' ?>
                                        <?php else: ?>
                                            ⇅
                                        <?php endif; ?>
                                    </span>
                                    <span style="font-weight:300;color:rgba(255,255,255,0.3);font-size:10px;display:block;">
                                        <?= $col['Type'] ?>
                                    </span>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($tableData as $row): ?>
                            <tr data-id="<?= addslashes($row[$primaryKey] ?? '') ?>">
                                <td><input type="radio" name="selected_row" value="<?= addslashes($row[$primaryKey] ?? '') ?>" onchange="updateActionButtons()"></td>
                                <?php foreach ($tableColumns as $col): 
                                    $field = $col['Field'];
                                    $value = $row[$field] ?? '';
                                    $isPrimary = ($field === $primaryKey);
                                    $isEditable = !$isPrimary && strpos($col['Extra'], 'auto_increment') === false;
                                    $displayValue = is_null($value) ? '<span style="color:rgba(255,255,255,0.2);">NULL</span>' : htmlspecialchars($value);
                                ?>
                                <td class="<?= $isEditable ? 'editable' : '' ?>" 
                                    data-field="<?= $field ?>"
                                    data-primary="<?= $isPrimary ? 'true' : 'false' ?>"
                                    ondblclick="<?= $isEditable ? "startInlineEdit(this, '" . addslashes($field) . "')" : '' ?>"
                                    style="<?= $isPrimary ? 'color:#a78bfa;font-weight:bold;' : '' ?>">
                                    <span class="inline-value"><?= $displayValue ?></span>
                                    <?php if ($isEditable): ?>
                                    <input type="text" class="inline-input" value="<?= htmlspecialchars($value) ?>" 
                                           onblur="saveInlineEdit(this, '<?= addslashes($field) ?>')"
                                           onkeydown="if(event.key==='Enter'){this.blur();} if(event.key==='Escape'){cancelInlineEdit(this);}">
                                    <span class="edit-hint">✏️</span>
                                    <span class="save-indicator">💾</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <span class="icon">📭</span>
                    <p>Nenhum registro encontrado nesta tabela.</p>
                    <?php if (!empty($searchTerm) || !empty($filterColumn) || !empty($filterValue) || !empty($multiFilters)): ?>
                    <p style="font-size:13px;color:rgba(255,255,255,0.4);">Tente ajustar os filtros de busca.</p>
                    <a href="?table=<?= urlencode($selectedTable) ?>" class="btn btn-outline" style="margin-top:10px;">Limpar Filtros</a>
                    <?php else: ?>
                    <button onclick="openAddModal('<?= htmlspecialchars($selectedTable) ?>')" class="btn btn-primary" style="margin-top:15px;">➕ Adicionar primeiro registro</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="empty-state">
                    <span class="icon">🗄️</span>
                    <p>Selecione uma tabela ao lado para visualizar os dados.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Modal de Importação -->
        <div class="modal-overlay" id="importModal">
            <div class="modal" style="max-width:500px;">
                <h2>📥 Importar Arquivo SQL</h2>
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <input type="hidden" name="action" value="import_sql">
                    <div class="import-area">
                        <p style="margin-bottom:15px;color:rgba(255,255,255,0.6);">Selecione um arquivo .sql para importar</p>
                        <input type="file" name="sql_file" id="sqlFile" accept=".sql">
                        <label for="sqlFile">📂 Escolher arquivo</label>
                        <div id="fileName" style="margin-top:10px;font-size:13px;color:rgba(255,255,255,0.4);"></div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">📥 Importar</button>
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('importModal').classList.remove('active')">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Adicionar -->
        <div class="modal-overlay" id="addModal">
            <div class="modal">
                <h2>➕ Adicionar Registro</h2>
                <form method="POST" id="addForm" action="<?= $_SERVER['PHP_SELF'] ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="table" id="addTable">
                    <div id="addFields"></div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">✅ Salvar</button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Editar -->
        <div class="modal-overlay" id="editModal">
            <div class="modal">
                <h2>✏️ Editar Registro</h2>
                <form method="POST" id="editForm" action="<?= $_SERVER['PHP_SELF'] ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="table" id="editTable">
                    <input type="hidden" name="id" id="editId">
                    <input type="hidden" name="primary_key" id="editPrimaryKey" value="id">
                    <div id="editFields"></div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-warning">💾 Atualizar</button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Excluir -->
        <div class="modal-overlay" id="deleteModal">
            <div class="modal" style="max-width:400px;">
                <h2>🗑️ Confirmar Exclusão</h2>
                <p style="color:rgba(255,255,255,0.7);margin:20px 0;">Tem certeza que deseja excluir este registro?<br>Esta ação não pode ser desfeita!</p>
                <div class="form-actions">
                    <a href="#" id="deleteLink" class="btn btn-danger">🗑️ Excluir</a>
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast de notificação -->
    <div class="toast" id="toast"></div>
    
    <script>
        // ===== VARIÁVEIS GLOBAIS =====
        var currentTable = '<?= addslashes($selectedTable) ?>';
        var primaryKey = '<?= addslashes($primaryKey) ?>';
        var editingCell = null;
        var originalValue = '';
        var filterCounter = <?= max(count($multiFilters), 1) ?>;
        
        // ===== TOAST =====
        function showToast(message, type) {
            var toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            clearTimeout(toast._timeout);
            toast._timeout = setTimeout(function() {
                toast.classList.remove('show');
            }, 4000);
        }
        
        // ===== FUNÇÕES GERAIS =====
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        function refreshTable() {
            var params = new URLSearchParams(window.location.search);
            window.location.href = '?' + params.toString();
        }
        
        // ===== ORDENAÇÃO =====
        function sortTable(field, dir) {
            var params = new URLSearchParams(window.location.search);
            params.set('order_by', field);
            params.set('order_dir', dir);
            window.location.href = '?' + params.toString();
        }
        
        // ===== FILTROS MÚLTIPLOS =====
        function toggleMultiFilters() {
            var section = document.getElementById('multiFiltersSection');
            if (section.style.display === 'none') {
                section.style.display = 'block';
                var grid = document.getElementById('multiFiltersGrid');
                if (grid.children.length === 0) {
                    addFilterRow();
                }
            } else {
                section.style.display = 'none';
            }
        }
        
        function addFilterRow() {
            var grid = document.getElementById('multiFiltersGrid');
            var index = filterCounter++;
            
            var div = document.createElement('div');
            div.className = 'multi-filter-item';
            div.dataset.index = index;
            
            var select = document.createElement('select');
            select.className = 'multi-filter-field';
            select.name = 'multi_filter_field_' + index;
            
            <?php foreach ($tableColumns as $col): ?>
            var opt = document.createElement('option');
            opt.value = '<?= htmlspecialchars($col['Field']) ?>';
            opt.textContent = '<?= htmlspecialchars($col['Field']) ?>';
            select.appendChild(opt);
            <?php endforeach; ?>
            
            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'multi-filter-value';
            input.name = 'multi_filter_value_' + index;
            input.placeholder = 'Valor...';
            
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'remove-filter-btn';
            btn.textContent = '✕';
            btn.onclick = function() { removeFilterRow(this); };
            
            div.appendChild(select);
            div.appendChild(input);
            div.appendChild(btn);
            grid.appendChild(div);
        }
        
        function removeFilterRow(btn) {
            var item = btn.closest('.multi-filter-item');
            if (item) {
                item.remove();
            }
        }
        
        function removeMultiFilter(field) {
            var params = new URLSearchParams(window.location.search);
            var filters = params.get('filters') || '';
            var newFilters = [];
            var parts = filters.split(';');
            for (var i = 0; i < parts.length; i++) {
                if (parts[i].indexOf(':') > 0) {
                    var pair = parts[i].split(':');
                    if (pair[0] !== field) {
                        newFilters.push(parts[i]);
                    }
                }
            }
            var newFilterStr = newFilters.join(';');
            if (newFilterStr) {
                params.set('filters', newFilterStr);
            } else {
                params.delete('filters');
            }
            window.location.href = '?' + params.toString();
        }
        
        function applyFilters() {
            var filterItems = document.querySelectorAll('.multi-filter-item');
            var filters = [];
            var hasFilters = false;
            
            filterItems.forEach(function(item) {
                var select = item.querySelector('.multi-filter-field');
                var input = item.querySelector('.multi-filter-value');
                if (select && input && input.value.trim() !== '') {
                    filters.push(select.value + ':' + input.value.trim());
                    hasFilters = true;
                }
            });
            
            var params = new URLSearchParams();
            params.set('table', currentTable);
            
            var search = document.getElementById('searchInput').value;
            if (search) params.set('search', search);
            
            var filterColumn = document.getElementById('filterColumn').value;
            var filterValue = document.getElementById('filterValue').value;
            if (filterColumn && filterValue) {
                params.set('filter_column', filterColumn);
                params.set('filter_value', filterValue);
            }
            
            var showAll = document.getElementById('showAllHidden').value;
            if (showAll) params.set('show_all', showAll);
            
            if (hasFilters) {
                params.set('filters', filters.join(';'));
            }
            
            var currentParams = new URLSearchParams(window.location.search);
            var orderBy = currentParams.get('order_by');
            var orderDir = currentParams.get('order_dir');
            if (orderBy) params.set('order_by', orderBy);
            if (orderDir) params.set('order_dir', orderDir);
            
            window.location.href = '?' + params.toString();
            return false;
        }
        
        // ===== EDIÇÃO INLINE =====
        function startInlineEdit(element, field) {
            if (element.dataset.primary === 'true') {
                showToast('⚠️ Não é possível editar a chave primária', 'error');
                return;
            }
            
            if (editingCell && editingCell !== element) {
                cancelInlineEdit(editingCell.querySelector('.inline-input'));
            }
            
            var input = element.querySelector('.inline-input');
            var valueSpan = element.querySelector('.inline-value');
            
            if (!input || !valueSpan) return;
            
            originalValue = input.value;
            element.classList.add('editing');
            setTimeout(function() {
                input.focus();
                input.select();
            }, 50);
            
            editingCell = element;
        }
        
        function saveInlineEdit(input, field) {
            var td = input.closest('td');
            var tr = td.closest('tr');
            var id = tr.dataset.id;
            var newValue = input.value;
            
            if (newValue === originalValue) {
                cancelInlineEdit(input);
                return;
            }
            
            td.classList.add('saving');
            
            var formData = new FormData();
            formData.append('ajax_action', 'inline_edit');
            formData.append('table', currentTable);
            formData.append('id', id);
            formData.append('field', field);
            formData.append('value', newValue);
            formData.append('primary_key', primaryKey);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                td.classList.remove('saving');
                if (data.success) {
                    var valueSpan = td.querySelector('.inline-value');
                    if (valueSpan) {
                        valueSpan.textContent = newValue;
                    }
                    td.classList.remove('editing');
                    editingCell = null;
                    showToast('✅ ' + data.message, 'success');
                } else {
                    showToast('❌ ' + data.message, 'error');
                    cancelInlineEdit(input);
                }
            })
            .catch(function(err) {
                td.classList.remove('saving');
                showToast('❌ Erro ao salvar: ' + err.message, 'error');
                cancelInlineEdit(input);
            });
        }
        
        function cancelInlineEdit(input) {
            var td = input.closest('td');
            td.classList.remove('editing');
            td.classList.remove('saving');
            input.value = originalValue;
            editingCell = null;
        }
        
        // ===== OPEN MODALS =====
        function openAddModal(table) {
            document.getElementById('addTable').value = table;
            var fieldsDiv = document.getElementById('addFields');
            
            fetch('dev_ajax.php?action=get_columns&table=' + encodeURIComponent(table))
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    fieldsDiv.innerHTML = '';
                    data.columns.forEach(function(col) {
                        if (col.Key === 'PRI' && col.Extra === 'auto_increment') return;
                        
                        var div = document.createElement('div');
                        div.className = 'form-group';
                        var required = col.Null === 'NO' ? 'required' : '';
                        div.innerHTML = 
                            '<label>' + col.Field + ' (' + col.Type + ')</label>' +
                            '<input type="hidden" name="columns[]" value="' + col.Field + '">' +
                            '<input type="text" name="values[]" placeholder="Valor para ' + col.Field + '" ' + required + '>';
                        fieldsDiv.appendChild(div);
                    });
                    document.getElementById('addModal').classList.add('active');
                })
                .catch(function(err) {
                    showToast('❌ Erro: ' + err.message, 'error');
                });
        }
        
        function openEditModal() {
            var selected = document.querySelector('input[name="selected_row"]:checked');
            if (!selected) {
                showToast('⚠️ Selecione um registro para editar.', 'error');
                return;
            }
            
            var id = selected.value;
            document.getElementById('editTable').value = currentTable;
            document.getElementById('editId').value = id;
            document.getElementById('editPrimaryKey').value = primaryKey;
            
            fetch('dev_ajax.php?action=get_row&table=' + encodeURIComponent(currentTable) + '&id=' + encodeURIComponent(id) + '&pk=' + encodeURIComponent(primaryKey))
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    var fieldsDiv = document.getElementById('editFields');
                    fieldsDiv.innerHTML = '';
                    
                    data.columns.forEach(function(col) {
                        var div = document.createElement('div');
                        div.className = 'form-group';
                        var value = data.row[col.Field] || '';
                        var isPrimary = col.Key === 'PRI';
                        var readonly = isPrimary ? 'readonly style="opacity:0.5;"' : '';
                        div.innerHTML = 
                            '<label>' + col.Field + ' (' + col.Type + ')' + (isPrimary ? ' 🔑' : '') + '</label>' +
                            '<input type="hidden" name="columns[]" value="' + col.Field + '">' +
                            '<input type="text" name="values[]" value="' + value.replace(/"/g, '&quot;') + '" ' + readonly + '>';
                        fieldsDiv.appendChild(div);
                    });
                    document.getElementById('editModal').classList.add('active');
                })
                .catch(function(err) {
                    showToast('❌ Erro: ' + err.message, 'error');
                });
        }
        
        function openDeleteModal() {
            var selected = document.querySelector('input[name="selected_row"]:checked');
            if (!selected) {
                showToast('⚠️ Selecione um registro para excluir.', 'error');
                return;
            }
            
            var id = selected.value;
            var link = document.getElementById('deleteLink');
            link.href = '?delete=1&table=' + encodeURIComponent(currentTable) + '&id=' + encodeURIComponent(id) + '&primary_key=' + encodeURIComponent(primaryKey);
            document.getElementById('deleteModal').classList.add('active');
        }
        
        // ===== SELECIONAR LINHA =====
        function toggleSelectAll(checkbox) {
            var radios = document.querySelectorAll('input[name="selected_row"]');
            for (var i = 0; i < radios.length; i++) {
                radios[i].checked = checkbox.checked;
            }
            updateActionButtons();
        }
        
        function updateActionButtons() {
            var selected = document.querySelector('input[name="selected_row"]:checked');
            var editBtn = document.getElementById('editBtn');
            var deleteBtn = document.getElementById('deleteBtn');
            if (editBtn) editBtn.disabled = !selected;
            if (deleteBtn) deleteBtn.disabled = !selected;
        }
        
        // ===== IMPORT =====
        document.getElementById('sqlFile')?.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                document.getElementById('fileName').textContent = '📄 ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
            }
        });
        
        // ===== FECHAR MODAL CLICANDO FORA =====
        document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
        
        // ===== INICIALIZAR =====
        document.addEventListener('DOMContentLoaded', function() {
            updateActionButtons();
            
            document.querySelectorAll('input[name="selected_row"]').forEach(function(radio) {
                radio.addEventListener('change', updateActionButtons);
            });
            
            var grid = document.getElementById('multiFiltersGrid');
            if (grid && grid.children.length === 0) {
                addFilterRow();
            }
        });
    </script>
</body>
</html>