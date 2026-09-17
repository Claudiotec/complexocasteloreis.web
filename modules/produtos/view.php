<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
$stmt->execute([$id]);
$produto = $stmt->fetch();

if (!$produto) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produto['nome']) ?> - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .view-container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .view-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 30px;
        }
        .view-card h2 {
            color: #2c3e50; border-bottom: 2px solid #f0f2f5;
            padding-bottom: 15px; margin-bottom: 20px;
        }
        .view-item {
            display: flex; padding: 10px 0; border-bottom: 1px solid #f0f2f5;
        }
        .view-item .label {
            font-weight: 600; color: #64748b; width: 150px; flex-shrink: 0;
        }
        .view-item .value { color: #1e293b; font-weight: 500; }
        .btn-actions { display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .view-item { flex-direction: column; }
            .view-item .label { width: 100%; margin-bottom: 4px; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="view-container">
        <div class="view-card">
            <h2>📦 <?= htmlspecialchars($produto['nome']) ?></h2>
            
            <div class="view-item">
                <span class="label">Código</span>
                <span class="value"><?= htmlspecialchars($produto['codigo']) ?></span>
            </div>
            <div class="view-item">
                <span class="label">Categoria</span>
                <span class="value"><?= htmlspecialchars($produto['categoria'] ?? '-') ?></span>
            </div>
            <div class="view-item">
                <span class="label">Descrição</span>
                <span class="value"><?= nl2br(htmlspecialchars($produto['descricao'] ?? '-')) ?></span>
            </div>
            <div class="view-item">
                <span class="label">Quantidade</span>
                <span class="value"><?= $produto['quantidade'] ?></span>
            </div>
            <div class="view-item">
                <span class="label">Estoque Mínimo</span>
                <span class="value"><?= $produto['estoque_minimo'] ?></span>
            </div>
            <div class="view-item">
                <span class="label">Preço de Compra</span>
                <span class="value">R$ <?= number_format($produto['preco_compra'], 2, ',', '.') ?></span>
            </div>
            <div class="view-item">
                <span class="label">Preço de Venda</span>
                <span class="value"><strong>R$ <?= number_format($produto['preco_venda'], 2, ',', '.') ?></strong></span>
            </div>
            <div class="view-item">
                <span class="label">Fornecedor</span>
                <span class="value"><?= htmlspecialchars($produto['fornecedor'] ?? '-') ?></span>
            </div>
            
            <div class="btn-actions">
                <a href="edit.php?id=<?= $produto['id'] ?>" class="btn btn-primary">✏️ Editar</a>
                <a href="index.php" class="btn">← Voltar</a>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>