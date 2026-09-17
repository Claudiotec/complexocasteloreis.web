<?php
require_once '../../config/database.php';

$funcionario_id = $_GET['funcionario'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ?");
$stmt->execute([$funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    header("Location: funcionarios.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM folha_pagamento WHERE funcionario_id = ? ORDER BY ano DESC, mes DESC");
$stmt->execute([$funcionario_id]);
$folhas = $stmt->fetchAll();

$totalPago = 0;
foreach ($folhas as $folha) {
    if ($folha['status'] == 'pago') {
        $totalPago += $folha['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folha de Pagamento - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>💰 Folha de Pagamento</h2>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3><?= htmlspecialchars($funcionario['nome']) ?></h3>
            <p><strong>Cargo:</strong> <?= htmlspecialchars($funcionario['cargo']) ?></p>
            <p><strong>Salário Base:</strong> R$ <?= number_format($funcionario['salario'], 2, ',', '.') ?></p>
            <p><strong>Total Pago:</strong> R$ <?= number_format($totalPago, 2, ',', '.') ?></p>
        </div>
        
        <div class="actions">
            <a href="add_folha.php?funcionario=<?= $funcionario_id ?>" class="btn btn-primary">+ Nova Folha</a>
            <a href="funcionarios.php" class="btn">Voltar</a>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Mês/Ano</th>
                    <th>Salário Base</th>
                    <th>Bônus</th>
                    <th>Descontos</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($folhas) > 0): ?>
                    <?php foreach($folhas as $folha): ?>
                    <tr>
                        <td><?= str_pad($folha['mes'], 2, '0', STR_PAD_LEFT) ?>/<?= $folha['ano'] ?></td>
                        <td>R$ <?= number_format($folha['salario_base'], 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($folha['bonus'], 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($folha['descontos'], 2, ',', '.') ?></td>
                        <td><strong>R$ <?= number_format($folha['total'], 2, ',', '.') ?></strong></td>
                        <td><?= ucfirst($folha['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                            Nenhuma folha de pagamento encontrada.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>