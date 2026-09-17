<?php
// ============================================
// api/pautas/turmas.php - Listar Turmas por Classe
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

$classe = isset($_GET['classe']) ? $_GET['classe'] : '';
$ano_letivo = isset($_GET['ano_letivo']) ? $_GET['ano_letivo'] : date('Y') . '/' . (date('Y') + 1);

if (empty($classe)) {
    echo json_encode(['success' => false, 'message' => 'Classe não informada']);
    exit;
}

try {
    $pdo = conectarBanco();
    
    // Buscar turmas da classe
    $sql = "SELECT DISTINCT TURMA FROM alunos WHERE Classe = ? AND status = 'ativo' AND TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$classe]);
    $turmas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Se não encontrou turmas, usar lista padrão
    if (empty($turmas)) {
        $turmas = ['AM', 'BM', 'CM', 'AT', 'BT', 'CT'];
    }
    
    echo json_encode([
        'success' => true,
        'turmas' => $turmas,
        'classe' => $classe,
        'total' => count($turmas),
        'ano_letivo' => $ano_letivo
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar turmas: ' . $e->getMessage()]);
}
?>