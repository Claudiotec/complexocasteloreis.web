<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

if ($id) {
    // Excluir itens primeiro
    $stmt = $pdo->prepare("DELETE FROM fatura_proforma_itens WHERE fatura_id = ?");
    $stmt->execute([$id]);
    
    // Excluir fatura
    $stmt = $pdo->prepare("DELETE FROM faturas_proforma WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: index.php");
exit;
?>