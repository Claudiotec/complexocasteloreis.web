<?php
// ============================================
// modules/escola/notas/delete.php - Excluir Nota
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'excluir')) {
    header('Location: ' . SITE_URL);
    exit;
}

if (!isset($pdo) || !$pdo) {
    $pdo = conectarBanco();
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php?msg=erro_id');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM notas_alunos WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: index.php?msg=excluido');
} catch (Exception $e) {
    error_log("Erro ao excluir nota: " . $e->getMessage());
    header('Location: index.php?msg=erro_excluir');
}
exit;