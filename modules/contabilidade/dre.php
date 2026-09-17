<?php
// ============================================
// DEMONSTRAÇÃO DE RESULTADOS (DRE)
// ============================================

require_once '../../config/app_modes.php';
require_once '../../config/database.php';
require_once 'config.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pdo = conectarBanco();

$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');
$periodo = $_GET['periodo'] ?? 'mensal'; // mensal, acumulado, anual

// Buscar receitas
$receitas = $pdo->query("
    SELECT 
        pc.codigo,
        pc.nome,
        SUM(li.valor) as total
    FROM lancamentos_contabeis l
    JOIN lancamentos_itens li ON l.id = li.lancamento_id
    JOIN plano_contas pc ON li.conta_id = pc.id
    WHERE pc.tipo = 'RECEITA'
    AND li.tipo_movimento = 'CREDITO'
    AND l.status = 'confirmado'
    AND YEAR(l.data_lancamento) = $ano
    AND MONTH(l.data_lancamento) = $mes
    GROUP BY pc.id
    ORDER BY pc.codigo
")->fetchAll();

// Buscar despesas
$despesas = $pdo->query("
    SELECT 
        pc.codigo,
        pc.nome,
        SUM(li.valor) as total
    FROM lancamentos_contabeis l
    JOIN lancamentos_itens li ON l.id = li.lancamento_id
    JOIN plano_contas pc ON li.conta_id = pc.id
    WHERE pc.tipo IN ('DESPESA', 'CUSTO')
    AND li.tipo_movimento = 'DEBITO'
    AND l.status = 'confirmado'
    AND YEAR(l.data_lancamento) = $ano
    AND MONTH(l.data_lancamento) = $mes
    GROUP BY pc.id
    ORDER BY pc.codigo
")->fetchAll();

// Calcular totais
$totalReceitas = array_sum(array_column($receitas, 'total'));
$totalDespesas = array_sum(array_column($despesas, 'total'));
$resultado = $totalReceitas - $totalDespesas;

include_once '../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DRE - Demonstração de Resultados - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; }
        
        .module-container { padding: 20px 30px; max-width: 1200px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 { font-size: 24px; color: #1a2332; }
        .header h1 span { color: #c9a84c; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
        }
        
        .filtros {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        .filtros .form-group { margin: 0; }
        .filtros label { font-size: 13px; color: #94a3b8; display: block; margin-bottom: 3px; }
        .filtros select, .filtros input {
            padding: 8px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        .filtros button {
            padding: 8px 20px;
            background: #f5d76e;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            color: #1a2332;
        }
        .filtros button:hover { background: #e6c753; }
        
        .dre-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .dre-item .codigo { color: #94a3b8; font-size: 12px; margin-right: 8px; }
        .dre-item .valor { font-weight: 600; }
        .dre-item .valor.receita { color: #2ecc71; }
        .dre-item .valor.despesa { color: #e74c3c; }
        
        .dre-total {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-weight: 700;
            font-size: 16px;
            border-top: 2px solid #1a2332;
            margin-top: 10px;
        }
        .dre-total .valor { font-size: 18px; }
        .dre-total .valor.lucro { color: #2ecc71; }
        .dre-total .valor.prejuizo { color: #e74c3c; }
        
        .resultado-box {
            margin-top: 20px;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .resultado-box.lucro { background: #d1fae5; color: #065f46; }
        .resultado-box.prejuizo { background: #fee2e2; color: #991b1b; }
        .resultado-box .valor { font-size: 32px; font-weight: 800; }
        .resultado-box .label { font-size: 16px; }
        
        .periodo-info {
            text-align: center;
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .actions a {
            padding: 8px 18px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
            border-radius: 8px;
            text-decoration: none;
            color: #1a2332;
            font-size: 14px;
            transition: all 0.3s;
        }
        .actions a:hover { background: #f5d76e20; border-color: #f5d76e; }
        .actions a.primary { background: #f5d76e; border: none; }
        .actions a.primary:hover { background: #e6c753; }
        
        .footer-info {
            text-align: center;
            padding: 20px 0 10px;
            color: #94a3b8;
            font-size: 14px;
            border-top: 1px solid #eef2f7;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="module-container">
    <div class="header">
        <div>
            <h1>📈 <span>DRE</span></h1>
            <div style="color: #94a3b8; font-size: 14px;">Demonstração de Resultados do Exercício</div>
        </div>
        <div class="actions">
            <a href="index.php">📊 Dashboard</a>
            <a href="balanco.php">⚖️ Balanço</a>
            <a href="javascript:window.print()" class="primary">🖨️ Imprimir</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card">
        <form method="GET" action="" class="filtros">
            <div class="form-group">
                <label>Ano</label>
                <select name="ano">
                    <?php for ($a = date('Y')-2; $a <= date('Y'); $a++): ?>
                    <option value="<?= $a ?>" <?= $a == $ano ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Mês</label>
                <select name="mes">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit">Filtrar</button>
        </form>
    </div>

    <div class="periodo-info">
        📅 Período: <?= date('F', mktime(0,0,0,$mes,1)) ?> de <?= $ano ?>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 15px;">📊 Demonstração de Resultados</h3>

        <!-- RECEITAS -->
        <h4 style="color: #2ecc71; margin: 15px 0 10px;">RECEITAS</h4>
        <?php foreach ($receitas as $r): ?>
        <div class="dre-item">
            <span>
                <span class="codigo"><?= $r['codigo'] ?></span>
                <?= htmlspecialchars($r['nome']) ?>
            </span>
            <span class="valor receita">
                Kz <?= number_format($r['total'], 2, ',', '.') ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($receitas)): ?>
        <div style="color: #94a3b8; padding: 10px 0;">Nenhuma receita registrada no período</div>
        <?php endif; ?>
        <div class="dre-total" style="border-color: #2ecc71;">
            <span>TOTAL DAS RECEITAS</span>
            <span class="valor" style="color: #2ecc71;">Kz <?= number_format($totalReceitas, 2, ',', '.') ?></span>
        </div>

        <!-- DESPESAS -->
        <h4 style="color: #e74c3c; margin: 20px 0 10px;">DESPESAS</h4>
        <?php foreach ($despesas as $d): ?>
        <div class="dre-item">
            <span>
                <span class="codigo"><?= $d['codigo'] ?></span>
                <?= htmlspecialchars($d['nome']) ?>
            </span>
            <span class="valor despesa">
                Kz <?= number_format($d['total'], 2, ',', '.') ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($despesas)): ?>
        <div style="color: #94a3b8; padding: 10px 0;">Nenhuma despesa registrada no período</div>
        <?php endif; ?>
        <div class="dre-total" style="border-color: #e74c3c;">
            <span>TOTAL DAS DESPESAS</span>
            <span class="valor" style="color: #e74c3c;">Kz <?= number_format($totalDespesas, 2, ',', '.') ?></span>
        </div>

        <!-- RESULTADO -->
        <div class="dre-total" style="border-color: <?= $resultado >= 0 ? '#2ecc71' : '#e74c3c' ?>; background: #f8fafc; margin-top: 20px;">
            <span>RESULTADO DO PERÍODO</span>
            <span class="valor <?= $resultado >= 0 ? 'lucro' : 'prejuizo' ?>">
                <?= $resultado >= 0 ? '📈 ' : '📉 ' ?>
                Kz <?= number_format($resultado, 2, ',', '.') ?>
            </span>
        </div>
    </div>

    <!-- Resultado em destaque -->
    <div class="resultado-box <?= $resultado >= 0 ? 'lucro' : 'prejuizo' ?>">
        <div class="label"><?= $resultado >= 0 ? '🟢 LUCRO' : '🔴 PREJUÍZO' ?></div>
        <div class="valor">Kz <?= number_format($resultado, 2, ',', '.') ?></div>
        <div style="font-size: 14px; margin-top: 5px;">
            Receitas: Kz <?= number_format($totalReceitas, 2, ',', '.') ?> | 
            Despesas: Kz <?= number_format($totalDespesas, 2, ',', '.') ?>
        </div>
    </div>

    <!-- Margens -->
    <div class="card" style="margin-top: 20px;">
        <h3 style="margin-bottom: 15px;">📊 Margens</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div>
                <div style="color: #94a3b8; font-size: 13px;">Margem Bruta</div>
                <div style="font-size: 22px; font-weight: 700; color: #c9a84c;">
                    <?= $totalReceitas > 0 ? number_format(($resultado / $totalReceitas) * 100, 1, ',', '.') . '%' : 'N/A' ?>
                </div>
            </div>
            <div>
                <div style="color: #94a3b8; font-size: 13px;">Eficiência Operacional</div>
                <div style="font-size: 22px; font-weight: 700; color: #c9a84c;">
                    <?= $totalReceitas > 0 ? number_format((1 - ($totalDespesas / $totalReceitas)) * 100, 1, ',', '.') . '%' : 'N/A' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-info">
        <p>© <?= date('Y') ?> <strong>SoftGest</strong> - Módulo de Contabilidade</p>
    </div>
</div>

</body>
</html>
<?php include_once '../../includes/footer.php'; ?>