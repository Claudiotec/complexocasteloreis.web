<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$professor_id = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("UPDATE notificacoes_professor SET lida = 1 WHERE professor_id = ?");
$success = $stmt->execute([$professor_id]);
echo json_encode(['success' => $success]);
?>