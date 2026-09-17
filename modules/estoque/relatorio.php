<?php
require_once '../../config/database.php';

$produtos = $pdo->query("SELECT * FROM produtos ORDER BY nome")->fetchAll();

$totalProdutos = count($produtos);
$totalEstoque = 0;
$totalValor = 0;
$produtosBaixos = 0;

foreach ($produtos as $p) {
    $totalEstoque += $p['quantidade'];
    $totalValor += $p['quantidade'] * $p['preco_venda'];
    if ($p['quantidade'] <= $p['estoque_minimo']) $produtosBaixos++;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Estoque - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .relatorio-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 2px solid #f0f2f5;
            margin-bottom: 20px;
        }
        .relatorio-header h1 {
            color: #1a2332;
            font-size: 28px;
        }
        .relatorio-header p {
            color: #94a3b8;
        }
        .relatorio-resumo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .relatorio-resumo .item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border-left: 4px solid #c9a84c;
        }
        .relatorio-resumo .item h3 {
            font-size: 24px;
            margin: 0;
        }
        .relatorio-resumo .item p {
            margin: 0;
            color: #94a3b8;
            font-size: 13px;
        }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="relatorio-header">
            <h1>📊 Relatório de Estoque</h1>
            <p>Gerado em <?= date('d/m/Y H:i:s') ?></p>
        </div>
        
        <div class="actions no-print">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir Relatório</button>
            <a href="index.php" class="btn">← Voltar</a>
        </div>
        
        <div class="relatorio-resumo">
            <div class="item">
                <h3><?= $totalProdutos ?></h3>
                <p>Total de Produtos</p>
            </div>
            <div class="item" style="border-left-color: #2ecc71;">
                <h3><?= $totalEstoque ?></h3>
                <p>Itens em Estoque</p>
            </div>
            <div class="item" style="border-left-color: #f59e0b;">
                <h3>R$ <?= number_format($totalValor, 2, ',', '.') ?></h3>
                <p>Valor Total do Estoque</p>
            </div>
            <div class="item" style="border-left-color: #ef4444;">
                <h3><?= $produtosBaixos ?></h3>
                <p>Produtos com Estoque Baixo</p>
            </div>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome</th>
                    <th>Categoria</th>
                    <th>Qtd</th>
                    <th>Preço Venda</th>
                    <th>Valor Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($produtos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['codigo']) ?></td>
                    <td><?= htmlspecialchars($p['nome']) ?></td>
                    <td><?= htmlspecialchars($p['categoria'] ?? '-') ?></td>
                    <td><?= $p['quantidade'] ?></td>
                    <td>R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($p['quantidade'] * $p['preco_venda'], 2, ',', '.') ?></td>
                    <td>
                        <?php if ($p['quantidade'] <= $p['estoque_minimo']): ?>
                            <span style="color: #ef4444; font-weight: 600;">⚠️ Baixo</span>
                        <?php else: ?>
                            <span style="color: #2ecc71; font-weight: 600;">✅ Normal</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>