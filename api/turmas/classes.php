<?php
// ============================================
// api/turmas/classes.php
// Lista todas as classes únicas das turmas
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';

try {
    $stmt = $pdo->query("
        SELECT DISTINCT classe 
        FROM turmas 
        WHERE classe IS NOT NULL AND classe != ''
        ORDER BY classe
    ");
    
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'total' => count($classes)
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao listar classes: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar classes: ' . $e->getMessage(),
        'classes' => []
    ]);
}
?>