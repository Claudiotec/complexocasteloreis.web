<?php
// api/distribuicao/listar.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT * FROM destribuicao_professores
        ORDER BY id DESC
    ");
    $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'distribuicoes' => $distribuicoes,
        'total' => count($distribuicoes)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao listar: ' . $e->getMessage()]);
}