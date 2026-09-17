<?php
// api/distribuicao/buscar.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM destribuicao_professores WHERE id = ?");
    $stmt->execute([$id]);
    $distribuicao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$distribuicao) {
        echo json_encode(['success' => false, 'message' => 'Distribuição não encontrada']);
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'distribuicao' => $distribuicao
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao buscar: ' . $e->getMessage()
    ]);
}