<?php
require_once '../../config/database.php';

// Buscar produtos
$stmt = $pdo->query("SELECT * FROM produtos ORDER BY nome");
$produtos = $stmt->fetchAll();

// Calcular totais
$totalProdutos = count($produtos);
$totalEstoque = 0;
$totalValorEstoque = 0;
$produtosBaixos = 0;

foreach ($produtos as $p) {
    $totalEstoque += $p['quantidade'];
    $totalValorEstoque += $p['quantidade'] * $p['preco_venda'];
    if ($p['quantidade'] <= $p['estoque_minimo']) {
        $produtosBaixos++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #3498db;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-card h3 {
            font-size: 26px;
            margin: 0;
            color: #1a2332;
        }
        .stat-card p {
            margin: 5px 0 0;
            font-size: 13px;
            color: #94a3b8;
        }
        .stat-card.estoque { border-left-color: #2ecc71; }
        .stat-card.valor { border-left-color: #f59e0b; }
        .stat-card.baixo { border-left-color: #ef4444; }
        .stat-card.total { border-left-color: #3498db; }
        
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
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
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(197,165,50,0.3);
            color: #1a2332;
        }
        .btn-print {
            background: #1a2332;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .btn-print:hover {
            background: #2c3e50;
        }
        .estoque-baixo {
            background: #fee2e2;
            color: #991b1b;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .estoque-normal {
            background: #d1fae5;
            color: #065f46;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .btn-small {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            margin: 2px;
            color: white;
        }
        .btn-small.view { background: #3498db; }
        .btn-small.edit { background: #f59e0b; }
        .btn-small.ficha { background: #8b5cf6; }
        .btn-small.mov { background: #10b981; }
        .btn-small.delete { background: #ef4444; }
        .btn-small:hover { opacity: 0.85; transform: scale(1.02); }
        
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>📦 Controle de Estoque</h2>
        
        <div class="actions no-print">
            <a href="add.php" class="btn-gold">➕ Novo Produto</a>
            <a href="relatorio.php" class="btn-gold" style="background: #3498db; color: white;">📊 Relatório</a>
            <button onclick="window.print()" class="btn-print">🖨️ Imprimir</button>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card total">
                <h3><?= $totalProdutos ?></h3>
                <p>📦 Total Produtos</p>
            </div>
            <div class="stat-card estoque">
                <h3><?= $totalEstoque ?></h3>
                <p>📊 Itens em Estoque</p>
            </div>
            <div class="stat-card valor">
                <h3>R$ <?= number_format($totalValorEstoque, 2, ',', '.') ?></h3>
                <p>💰 Valor do Estoque</p>
            </div>
            <div class="stat-card baixo">
                <h3><?= $produtosBaixos ?></h3>
                <p>⚠️ Estoque Baixo</p>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Produto cadastrado com sucesso!</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">✅ Produto atualizado com sucesso!</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">✅ Produto excluído com sucesso!</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['movimentado'])): ?>
            <div class="alert alert-success">✅ Estoque movimentado com sucesso!</div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Qtd</th>
                        <th>Preço Compra</th>
                        <th>Preço Venda</th>
                        <th>Estoque</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($produtos) > 0): ?>
                        <?php foreach($produtos as $produto): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($produto['codigo']) ?></strong></td>
                            <td><?= htmlspecialchars($produto['nome']) ?></td>
                            <td><?= htmlspecialchars($produto['categoria'] ?? '-') ?></td>
                            <td><?= $produto['quantidade'] ?></td>
                            <td>R$ <?= number_format($produto['preco_compra'], 2, ',', '.') ?></td>
                            <td><strong>R$ <?= number_format($produto['preco_venda'], 2, ',', '.') ?></strong></td>
                            <td>
                                <?php if ($produto['quantidade'] <= $produto['estoque_minimo']): ?>
                                    <span class="estoque-baixo">⚠️ Baixo</span>
                                <?php else: ?>
                                    <span class="estoque-normal">✅ Normal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view.php?id=<?= $produto['id'] ?>" class="btn-small view">Visualizar</a>
                                <a href="edit.php?id=<?= $produto['id'] ?>" class="btn-small edit">Editar</a>
                                <a href="ficha.php?id=<?= $produto['id'] ?>" class="btn-small ficha" target="_blank">📄 Ficha</a>
                                <a href="movimentar.php?id=<?= $produto['id'] ?>" class="btn-small mov">📊 Mov</a>
                                <a href="delete.php?id=<?= $produto['id'] ?>" class="btn-small delete" onclick="return confirm('Tem certeza?')">Excluir</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                <p style="font-size: 48px;">📦</p>
                                <p>Nenhum produto cadastrado.</p>
                                <a href="add.php" class="btn btn-primary" style="margin-top: 10px;">Cadastrar Produto</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>