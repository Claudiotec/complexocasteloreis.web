<?php
require_once '../../config/database.php';

$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');

// Buscar movimentações do mês
$stmt = $pdo->prepare("SELECT * FROM movimentacoes_caixa WHERE MONTH(data_movimento) = ? AND YEAR(data_movimento) = ? AND status = 'confirmado' ORDER BY data_movimento");
$stmt->execute([$mes, $ano]);
$movimentacoes = $stmt->fetchAll();

// Calcular totais
$totalEntradas = 0;
$totalSaidas = 0;
$categoriasEntrada = [];
$categoriasSaida = [];

foreach ($movimentacoes as $m) {
    if ($m['tipo'] == 'entrada') {
        $totalEntradas += $m['valor'];
        $categoriasEntrada[$m['categoria']] = ($categoriasEntrada[$m['categoria']] ?? 0) + $m['valor'];
    } else {
        $totalSaidas += $m['valor'];
        $categoriasSaida[$m['categoria']] = ($categoriasSaida[$m['categoria']] ?? 0) + $m['valor'];
    }
}

$saldo = $totalEntradas - $totalSaidas;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Caixa - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .relatorio-header {
            text-align: center; padding: 20px 0; border-bottom: 2px solid #f0f2f5; margin-bottom: 20px;
        }
        .relatorio-header h1 { color: #1a2332; font-size: 28px; }
        .relatorio-header p { color: #94a3b8; }
        .relatorio-resumo {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px; margin: 20px 0;
        }
        .relatorio-resumo .item {
            background: white; padding: 20px; border-radius: 8px;
            text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border-left: 4px solid #c9a84c;
        }
        .relatorio-resumo .item h3 { font-size: 24px; margin: 0; }
        .relatorio-resumo .item p { margin: 0; color: #94a3b8; font-size: 13px; }
        .relatorio-resumo .item.entradas { border-left-color: #2ecc71; }
        .relatorio-resumo .item.saidas { border-left-color: #e74c3c; }
        .relatorio-resumo .item.saldo { border-left-color: #c9a84c; }
        .relatorio-resumo .item.saldo h3 { color: <?= $saldo >= 0 ? '#2ecc71' : '#e74c3c' ?>; }
        .categoria-bar {
            display: flex; align-items: center; gap: 10px; margin: 5px 0;
        }
        .categoria-bar .barra {
            flex: 1; height: 20px; background: #f1f5f9; border-radius: 10px; overflow: hidden;
        }
        .categoria-bar .barra .preenchida {
            height: 100%; border-radius: 10px; transition: width 0.5s;
        }
        .filtro-mes {
            display: flex; gap: 10px; align-items: center; justify-content: center; margin: 20px 0;
        }
        .filtro-mes select, .filtro-mes input {
            padding: 8px 14px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;
        }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="relatorio-header">
            <h1>📈 Relatório de Caixa</h1>
            <p><?= date('F', mktime(0, 0, 0, $mes, 1)) ?> de <?= $ano ?></p>
        </div>
        
        <div class="filtro-mes no-print">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select name="mes">
                    <?php for($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $mes == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <input type="number" name="ano" value="<?= $ano ?>" min="2020" max="2099">
                <button type="submit" style="background: #c9a84c; color: #1a2332; padding: 8px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">🔍 Filtrar</button>
                <a href="relatorio.php?mes=<?= date('m') ?>&ano=<?= date('Y') ?>" class="btn">↻ Atualizar</a>
                <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</button>
            </form>
        </div>
        
        <div class="relatorio-resumo">
            <div class="item entradas">
                <h3>R$ <?= number_format($totalEntradas, 2, ',', '.') ?></h3>
                <p>📥 Total de Entradas</p>
            </div>
            <div class="item saidas">
                <h3>R$ <?= number_format($totalSaidas, 2, ',', '.') ?></h3>
                <p>📤 Total de Saídas</p>
            </div>
            <div class="item saldo">
                <h3>R$ <?= number_format($saldo, 2, ',', '.') ?></h3>
                <p>💰 Saldo do Mês</p>
            </div>
            <div class="item">
                <h3><?= count($movimentacoes) ?></h3>
                <p>📊 Total de Movimentações</p>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <h4 style="color: #2ecc71; margin-bottom: 15px;">📥 Entradas por Categoria</h4>
                <?php if (count($categoriasEntrada) > 0): 
                    $maxEntrada = max($categoriasEntrada);
                ?>
                    <?php foreach($categoriasEntrada as $cat => $valor): ?>
                    <div class="categoria-bar">
                        <span style="min-width: 80px; font-size: 13px;"><?= htmlspecialchars($cat) ?></span>
                        <div class="barra">
                            <div class="preenchida" style="width: <?= ($valor / $maxEntrada) * 100 ?>%; background: #2ecc71;"></div>
                        </div>
                        <span style="font-size: 13px; font-weight: 600;">R$ <?= number_format($valor, 2, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #94a3b8;">Nenhuma entrada registrada.</p>
                <?php endif; ?>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <h4 style="color: #e74c3c; margin-bottom: 15px;">📤 Saídas por Categoria</h4>
                <?php if (count($categoriasSaida) > 0): 
                    $maxSaida = max($categoriasSaida);
                ?>
                    <?php foreach($categoriasSaida as $cat => $valor): ?>
                    <div class="categoria-bar">
                        <span style="min-width: 80px; font-size: 13px;"><?= htmlspecialchars($cat) ?></span>
                        <div class="barra">
                            <div class="preenchida" style="width: <?= ($valor / $maxSaida) * 100 ?>%; background: #e74c3c;"></div>
                        </div>
                        <span style="font-size: 13px; font-weight: 600;">R$ <?= number_format($valor, 2, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #94a3b8;">Nenhuma saída registrada.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <h3 style="margin-top: 20px; color: #1a2332;">📋 Movimentações do Mês</h3>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Categoria</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($movimentacoes) > 0): ?>
                        <?php foreach($movimentacoes as $mov): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($mov['data_movimento'])) ?></td>
                            <td>
                                <span class="tipo-badge tipo-<?= $mov['tipo'] ?>">
                                    <?= $mov['tipo'] == 'entrada' ? '📥 Entrada' : '📤 Saída' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($mov['categoria']) ?></td>
                            <td><?= htmlspecialchars($mov['descricao'] ?? '-') ?></td>
                            <td style="font-weight: 600; color: <?= $mov['tipo'] == 'entrada' ? '#2ecc71' : '#e74c3c' ?>;">
                                <?= $mov['tipo'] == 'entrada' ? '+' : '-' ?> R$ <?= number_format($mov['valor'], 2, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #999;">
                                Nenhuma movimentação neste período.
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