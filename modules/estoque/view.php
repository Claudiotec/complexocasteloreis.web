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
    <title>Visualizar Produto - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .view-container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .view-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 30px;
        }
        .view-card h2 {
            color: #2c3e50; border-bottom: 2px solid #f0f2f5;
            padding-bottom: 15px; margin-bottom: 20px;
        }
        .view-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px 30px;
        }
        .view-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .view-item .label {
            font-size: 12px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
        }
        .view-item .value {
            font-size: 15px;
            color: #1e293b;
            font-weight: 500;
            margin-top: 2px;
        }
        .view-item.full { grid-column: 1 / -1; }
        .view-item .value.empty { color: #94a3b8; font-style: italic; font-weight: 400; }
        .btn-actions { display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap; }
        .btn-print {
            background: #1a2332;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover { background: #2c3e50; }
        .estoque-baixo {
            background: #fee2e2;
            color: #991b1b;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }
        .estoque-normal {
            background: #d1fae5;
            color: #065f46;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }
        @media (max-width: 768px) { .view-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="view-container">
        <div class="view-card">
            <h2>📦 <?= htmlspecialchars($produto['nome']) ?></h2>
            
            <div class="view-grid">
                <div class="view-item">
                    <div class="label">Código</div>
                    <div class="value"><?= htmlspecialchars($produto['codigo']) ?></div>
                </div>
                <div class="view-item">
                    <div class="label">Categoria</div>
                    <div class="value <?= empty($produto['categoria']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($produto['categoria'] ?? 'Não informado') ?>
                    </div>
                </div>
                
                <div class="view-item full">
                    <div class="label">Descrição</div>
                    <div class="value <?= empty($produto['descricao']) ? 'empty' : '' ?>">
                        <?= nl2br(htmlspecialchars($produto['descricao'] ?? 'Não informado')) ?>
                    </div>
                </div>
                
                <div class="view-item">
                    <div class="label">Quantidade em Estoque</div>
                    <div class="value"><?= $produto['quantidade'] ?></div>
                </div>
                <div class="view-item">
                    <div class="label">Estoque Mínimo</div>
                    <div class="value"><?= $produto['estoque_minimo'] ?></div>
                </div>
                
                <div class="view-item">
                    <div class="label">Status do Estoque</div>
                    <div class="value">
                        <?php if ($produto['quantidade'] <= $produto['estoque_minimo']): ?>
                            <span class="estoque-baixo">⚠️ Estoque Baixo</span>
                        <?php else: ?>
                            <span class="estoque-normal">✅ Estoque Normal</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="view-item">
                    <div class="label">Fornecedor</div>
                    <div class="value <?= empty($produto['fornecedor']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($produto['fornecedor'] ?? 'Não informado') ?>
                    </div>
                </div>
                
                <div class="view-item">
                    <div class="label">Preço de Compra</div>
                    <div class="value">R$ <?= number_format($produto['preco_compra'], 2, ',', '.') ?></div>
                </div>
                <div class="view-item">
                    <div class="label">Preço de Venda</div>
                    <div class="value"><strong>R$ <?= number_format($produto['preco_venda'], 2, ',', '.') ?></strong></div>
                </div>
                
                <div class="view-item">
                    <div class="label">Margem de Lucro</div>
                    <div class="value">
                        <?php 
                        $margem = $produto['preco_compra'] > 0 ? (($produto['preco_venda'] - $produto['preco_compra']) / $produto['preco_compra']) * 100 : 0;
                        echo number_format($margem, 1) . '%';
                        ?>
                    </div>
                </div>
                <div class="view-item">
                    <div class="label">Data Cadastro</div>
                    <div class="value"><?= date('d/m/Y H:i', strtotime($produto['created_at'])) ?></div>
                </div>
            </div>
            
            <div class="btn-actions">
                <a href="edit.php?id=<?= $produto['id'] ?>" class="btn btn-primary">✏️ Editar</a>
                <a href="ficha.php?id=<?= $produto['id'] ?>" class="btn-print" target="_blank">📄 Imprimir Ficha</a>
                <a href="movimentar.php?id=<?= $produto['id'] ?>" class="btn" style="background: #10b981; color: white;">📊 Movimentar</a>
                <a href="index.php" class="btn">← Voltar</a>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>