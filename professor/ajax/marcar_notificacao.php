<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$notificacao_id = $_POST['id'] ?? 0;
$professor_id = $_SESSION['usuario_id'];

if ($notificacao_id > 0) {
    $stmt = $pdo->prepare("UPDATE notificacoes_professor SET lida = 1 WHERE id = ? AND professor_id = ?");
    $success = $stmt->execute([$notificacao_id, $professor_id]);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false]);
}
?>