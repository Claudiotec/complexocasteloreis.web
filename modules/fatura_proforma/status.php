<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? '';

// Status permitidos
$statusPermitidos = ['rascunho', 'enviada', 'aprovada', 'rejeitada'];

if ($id && in_array($status, $statusPermitidos)) {
    $stmt = $pdo->prepare("UPDATE faturas_proforma SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    // Se for enviada, registrar data de envio
    if ($status == 'enviada') {
        $stmt = $pdo->prepare("UPDATE faturas_proforma SET data_envio = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }
}

header("Location: index.php");
exit;
?>