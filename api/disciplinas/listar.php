<?php
// api/disciplinas/listar.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    // Buscar todas as disciplinas
    $stmt = $pdo->query("SELECT id, nome, descricao, carga_horaria, status FROM disciplinas ORDER BY nome");
    $disciplinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'disciplinas' => $disciplinas,
        'total' => count($disciplinas)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}