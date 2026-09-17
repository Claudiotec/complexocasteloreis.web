<?php
// ============================================
// chamada_polling.php - Polling para chamadas
// ============================================

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$ultimo_id = intval($_GET['ultimo_id'] ?? 0);

header('Content-Type: application/json');

try {
    // Buscar novas chamadas
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u1.nome as origem_nome, u1.perfil as origem_perfil,
               u2.nome as destino_nome, u2.perfil as destino_perfil
        FROM chamadas_internas c
        LEFT JOIN usuarios u1 ON c.usuario_origem = u1.id
        LEFT JOIN usuarios u2 ON c.usuario_destino = u2.id
        WHERE (c.usuario_destino = ? OR c.usuario_origem = ?)
        AND c.id > ?
        AND c.status IN ('pendente', 'em_andamento')
        ORDER BY c.id ASC
    ");
    $stmt->execute([$usuario_id, $usuario_id, $ultimo_id]);
    $chamadas = $stmt->fetchAll();
    
    // Buscar novas mensagens se houver chamada ativa
    $mensagens = [];
    if (isset($_GET['chamada_id']) && $_GET['chamada_id'] > 0) {
        $chamada_id = intval($_GET['chamada_id']);
        $ultimo_msg = intval($_GET['ultimo_msg'] ?? 0);
        
        $stmt = $pdo->prepare("
            SELECT m.*, u.nome
            FROM chamadas_mensagens m
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.chamada_id = ? AND m.id > ?
            ORDER BY m.id ASC
        ");
        $stmt->execute([$chamada_id, $ultimo_msg]);
        $mensagens = $stmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'chamadas' => $chamadas,
        'mensagens' => $mensagens,
        'ultimo_id' => $ultimo_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>