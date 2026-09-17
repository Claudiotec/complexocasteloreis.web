<?php
// ============================================
// admin/dev_ajax.php - Suporte AJAX
// ============================================

session_start();

if (!isset($_SESSION['dev_authenticated']) || $_SESSION['dev_authenticated'] !== true) {
    http_response_code(403);
    die(json_encode(['error' => 'Acesso negado']));
}

require_once '../config/database.php';

try {
    $pdo = conectarBanco();
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => $e->getMessage()]));
}

$action = $_GET['action'] ?? '';

if ($action === 'get_columns') {
    $table = $_GET['table'] ?? '';
    if (!$table) {
        die(json_encode(['error' => 'Tabela não especificada']));
    }
    
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['columns' => $columns]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_row') {
    $table = $_GET['table'] ?? '';
    $id = $_GET['id'] ?? 0;
    $pk = $_GET['pk'] ?? 'id';
    
    if (!$table || !$id) {
        die(json_encode(['error' => 'Parâmetros incompletos']));
    }
    
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'columns' => $columns,
            'row' => $row ?: []
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);