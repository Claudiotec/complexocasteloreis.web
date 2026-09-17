<?php
// ============================================
// api/marcar_todas_lidas.php
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

try {
    $sql = "UPDATE notificacoes_professor SET lida = 1 WHERE professor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['usuario_id']]);

    echo json_encode(['success' => true, 'message' => 'Todas notificações marcadas como lidas']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>