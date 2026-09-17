<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

if ($id) {
    $stmt = $pdo->prepare("UPDATE faturas_recibo SET status = 'pago', data_pagamento = NOW() WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: index.php?pago=1");
exit;
?>