<?php
// ============================================
// api/turmas/listar.php
// Lista todas as turmas
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';

try {
    $stmt = $pdo->query("
        SELECT id, nome, classe, turno, capacidade, sala, ano_letivo, status, disciplinas
        FROM turmas
        WHERE status = 'ativa' OR status IS NULL OR status = ''
        ORDER BY classe, nome
    ");
    
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'turmas' => $turmas,
        'total' => count($turmas)
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao listar turmas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar turmas: ' . $e->getMessage(),
        'turmas' => []
    ]);
}
?>