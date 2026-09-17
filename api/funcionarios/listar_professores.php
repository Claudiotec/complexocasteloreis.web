<?php
// ============================================
// api/funcionarios/listar_professores.php
// Lista todos os professores
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';

try {
    // Buscar professores (incluindo ID 0)
    $stmt = $pdo->query("
        SELECT id, nome, cargo, email, contacto_telefonico 
        FROM funcionarios 
        WHERE cargo LIKE '%Professor%' OR cargo LIKE '%PROFESSOR%'
        AND (status = 'ativo' OR status IS NULL OR status = '')
        ORDER BY nome
    ");
    
    $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se não encontrou, buscar todos os funcionários
    if (empty($professores)) {
        $stmt = $pdo->query("
            SELECT id, nome, cargo, email, contacto_telefonico 
            FROM funcionarios 
            WHERE status = 'ativo' OR status IS NULL
            ORDER BY nome
        ");
        $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'professores' => $professores,
        'total' => count($professores)
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao listar professores: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar professores: ' . $e->getMessage(),
        'professores' => []
    ]);
}
?>