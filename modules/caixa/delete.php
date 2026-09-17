<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM movimentacoes_caixa WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: movimentacoes.php");
exit;
?>