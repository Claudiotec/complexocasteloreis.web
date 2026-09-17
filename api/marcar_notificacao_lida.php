<?php
// ============================================
// api/marcar_notificacao_lida.php
// ============================================

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$id = $_POST['id'] ?? 0;

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'ID da notificação é obrigatório']);
    exit;
}

try {
    $sql = "UPDATE notificacoes_professor SET lida = 1 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Notificação marcada como lida']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>