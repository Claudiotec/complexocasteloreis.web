<?php
// ============================================
// api/pautas/classes.php - Listar Classes
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';

// ===== SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== VERIFICAR LOGIN =====
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$ano_letivo = isset($_GET['ano_letivo']) ? $_GET['ano_letivo'] : date('Y') . '/' . (date('Y') + 1);

try {
    $pdo = conectarBanco();
    
    // Buscar classes distintas da tabela alunos
    $sql = "SELECT DISTINCT Classe FROM alunos WHERE status = 'ativo' AND Classe IS NOT NULL AND Classe != '' ORDER BY Classe";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Se não encontrou classes, usar lista padrão
    if (empty($classes)) {
        $classes = ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª', '7ª', '8ª', '9ª', '10ª', '11ª', '12ª'];
    }
    
    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'total' => count($classes),
        'ano_letivo' => $ano_letivo
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar classes: ' . $e->getMessage()]);
}
?>