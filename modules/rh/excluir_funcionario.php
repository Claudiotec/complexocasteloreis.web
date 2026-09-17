<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM funcionarios WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: funcionarios.php?deleted=1");
exit;
?>