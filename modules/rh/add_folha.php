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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mes = $_POST['mes'];
    $ano = $_POST['ano'];
    $salario_base = $_POST['salario_base'];
    $bonus = $_POST['bonus'] ?? 0;
    $descontos = $_POST['descontos'] ?? 0;
    $total = $salario_base + $bonus - $descontos;
    
    $stmt = $pdo->prepare("INSERT INTO folha_pagamento (funcionario_id, mes, ano, salario_base, bonus, descontos, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$funcionario_id, $mes, $ano, $salario_base, $bonus, $descontos, $total]);
    
    header("Location: folha.php?funcionario=" . $funcionario_id . "&success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Folha - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>📊 Nova Folha de Pagamento</h2>
        
        <h3><?= htmlspecialchars($funcionario['nome']) ?></h3>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Mês:</label>
                    <select name="mes" required>
                        <?php for($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>>
                                <?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ano:</label>
                    <select name="ano" required>
                        <?php for($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
                            <option value="<?= $a ?>" <?= $a == date('Y') ? 'selected' : '' ?>>
                                <?= $a ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Salário Base:</label>
                    <input type="number" step="0.01" name="salario_base" value="<?= $funcionario['salario'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Bônus:</label>
                    <input type="number" step="0.01" name="bonus" value="0">
                </div>
            </div>
            
            <div class="form-group">
                <label>Descontos:</label>
                <input type="number" step="0.01" name="descontos" value="0">
            </div>
            
            <button type="submit" class="btn btn-primary">💾 Salvar</button>
            <a href="folha.php?funcionario=<?= $funcionario_id ?>" class="btn">Cancelar</a>
        </form>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>