<?php
// ============================================
// CONTABILIDADE - DASHBOARD
// ============================================

require_once '../../config/app_modes.php';
require_once '../../config/database.php';
require_once 'config.php';

// ===== CORREÇÃO DA SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pdo = conectarBanco();
$usuario_id = $_SESSION['usuario_id'];
$usuario_perfil = $_SESSION['usuario_perfil'] ?? 'usuario';

// Verificar permissão
$isAdmin = ($usuario_perfil == 'admin');

// ===== FUNÇÃO PARA VERIFICAR SE TABELA EXISTE =====
function tabelaExiste($pdo, $tabela) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ===== VERIFICAR SE AS TABELAS EXISTEM =====
$tabelasExistem = tabelaExiste($pdo, 'lancamentos_contabeis') && 
                  tabelaExiste($pdo, 'plano_contas') && 
                  tabelaExiste($pdo, 'lancamentos_itens');

// ===== SE AS TABELAS NÃO EXISTEM, MOSTRAR MENSAGEM PARA INSTALAR =====
if (!$tabelasExistem) {
    $mensagemInstalacao = true;
    $totalLancamentos = 0;
    $totalLancamentosMes = 0;
    $totalAtivo = 0;
    $totalPassivo = 0;
    $receitaMes = 0;
    $despesaMes = 0;
    $resultadoMes = 0;
    $ultimosLancamentos = [];
    $graficoMeses = [];
} else {
    $mensagemInstalacao = false;
    
    // ===== DADOS DO DASHBOARD =====
    $totalLancamentos = $pdo->query("SELECT COUNT(*) FROM lancamentos_contabeis WHERE status = 'confirmado'")->fetchColumn() ?? 0;
    $totalLancamentosMes = $pdo->query("SELECT COUNT(*) FROM lancamentos_contabeis WHERE status = 'confirmado' AND MONTH(data_lancamento) = MONTH(CURRENT_DATE()) AND YEAR(data_lancamento) = YEAR(CURRENT_DATE())")->fetchColumn() ?? 0;

    // Saldo total
    $totalAtivo = $pdo->query("SELECT SUM(saldo_final) FROM saldo_contas WHERE conta_id IN (SELECT id FROM plano_contas WHERE tipo = 'ATIVO') AND ano = YEAR(CURRENT_DATE()) AND mes = MONTH(CURRENT_DATE())")->fetchColumn() ?? 0;
    $totalPassivo = $pdo->query("SELECT SUM(saldo_final) FROM saldo_contas WHERE conta_id IN (SELECT id FROM plano_contas WHERE tipo = 'PASSIVO') AND ano = YEAR(CURRENT_DATE()) AND mes = MONTH(CURRENT_DATE())")->fetchColumn() ?? 0;

    // Receitas e Despesas do mês
    $receitaMes = $pdo->query("
        SELECT SUM(li.valor) as total
        FROM lancamentos_itens li
        JOIN lancamentos_contabeis l ON li.lancamento_id = l.id
        JOIN plano_contas pc ON li.conta_id = pc.id
        WHERE pc.tipo = 'RECEITA' 
        AND li.tipo_movimento = 'CREDITO'
        AND l.status = 'confirmado'
        AND MONTH(l.data_lancamento) = MONTH(CURRENT_DATE()) 
        AND YEAR(l.data_lancamento) = YEAR(CURRENT_DATE())
    ")->fetchColumn() ?? 0;

    $despesaMes = $pdo->query("
        SELECT SUM(li.valor) as total
        FROM lancamentos_itens li
        JOIN lancamentos_contabeis l ON li.lancamento_id = l.id
        JOIN plano_contas pc ON li.conta_id = pc.id
        WHERE pc.tipo IN ('DESPESA', 'CUSTO')
        AND li.tipo_movimento = 'DEBITO'
        AND l.status = 'confirmado'
        AND MONTH(l.data_lancamento) = MONTH(CURRENT_DATE()) 
        AND YEAR(l.data_lancamento) = YEAR(CURRENT_DATE())
    ")->fetchColumn() ?? 0;

    $resultadoMes = $receitaMes - $despesaMes;

    // Últimos lançamentos
    $ultimosLancamentos = $pdo->query("
        SELECT l.*, COUNT(li.id) as total_itens
        FROM lancamentos_contabeis l
        LEFT JOIN lancamentos_itens li ON l.id = li.lancamento_id
        WHERE l.status = 'confirmado'
        GROUP BY l.id
        ORDER BY l.data_lancamento DESC
        LIMIT 10
    ")->fetchAll();

    // Gráfico - Receitas x Despesas (últimos 6 meses)
    $graficoMeses = [];
    for ($i = 5; $i >= 0; $i--) {
        $mes = date('m', strtotime("-$i months"));
        $ano = date('Y', strtotime("-$i months"));
        $nomeMes = date('M', strtotime("-$i months"));
        
        $rec = $pdo->query("
            SELECT SUM(li.valor) as total
            FROM lancamentos_itens li
            JOIN lancamentos_contabeis l ON li.lancamento_id = l.id
            JOIN plano_contas pc ON li.conta_id = pc.id
            WHERE pc.tipo = 'RECEITA' 
            AND li.tipo_movimento = 'CREDITO'
            AND l.status = 'confirmado'
            AND MONTH(l.data_lancamento) = $mes
            AND YEAR(l.data_lancamento) = $ano
        ")->fetchColumn() ?? 0;
        
        $desp = $pdo->query("
            SELECT SUM(li.valor) as total
            FROM lancamentos_itens li
            JOIN lancamentos_contabeis l ON li.lancamento_id = l.id
            JOIN plano_contas pc ON li.conta_id = pc.id
            WHERE pc.tipo IN ('DESPESA', 'CUSTO')
            AND li.tipo_movimento = 'DEBITO'
            AND l.status = 'confirmado'
            AND MONTH(l.data_lancamento) = $mes
            AND YEAR(l.data_lancamento) = $ano
        ")->fetchColumn() ?? 0;
        
        $graficoMeses[] = [
            'mes' => $nomeMes,
            'receita' => (float)$rec,
            'despesa' => (float)$desp
        ];
    }
}

// ============================================
// NÃO INCLUIR HEADER/FOOTER AQUI!
// O SISTEMA PRINCIPAL JÁ INCLUIU
// ============================================
// include_once '../../includes/header.php';  <-- REMOVER!
// include_once '../../includes/footer.php';  <-- REMOVER!
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contabilidade - SoftGest</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ============================================
           ESTILOS DO MÓDULO DE CONTABILIDADE
           ============================================ */
        
        /* Garantir que o conteúdo principal use toda a largura disponível */
        .main-content .content-area {
            padding: 20px 25px !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        
        .contabilidade-module {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        
        .module-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            box-sizing: border-box;
            padding: 0;
        }
        
        .module-header {
            background: linear-gradient(135deg, #1a2332, #2c3e50);
            color: white;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            width: 100%;
            box-sizing: border-box;
        }
        .module-header h1 { font-size: 24px; margin: 0; }
        .module-header h1 span { color: #f5d76e; }
        .module-header .subtitle { opacity: 0.8; font-size: 13px; margin-top: 3px; }
        
        .module-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .module-actions a {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
        }
        .module-actions a:hover { background: rgba(255,255,255,0.2); transform: translateY(-2px); }
        .module-actions a.primary { background: #f5d76e; color: #1a2332; border: none; }
        .module-actions a.primary:hover { background: #e6c753; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        .stat-card {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            border-left: 4px solid #3498db;
            min-width: 0;
        }
        .stat-card .label { font-size: 12px; color: #94a3b8; font-weight: 500; }
        .stat-card .value { font-size: 24px; font-weight: 700; color: #1a2332; margin: 3px 0; word-break: break-word; }
        .stat-card .sub { font-size: 11px; color: #94a3b8; }
        .stat-card .value.positive { color: #2ecc71; }
        .stat-card .value.negative { color: #e74c3c; }
        .stat-card .value.gold { color: #c9a84c; }
        
        .row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        @media (max-width: 992px) { 
            .row { grid-template-columns: 1fr; } 
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        .card h3 { 
            color: #1a2332; 
            margin-bottom: 12px; 
            font-size: 15px; 
            display: flex; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 8px; 
        }
        .card h3 .badge {
            background: #f5d76e;
            color: #1a2332;
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .table-responsive { 
            overflow-x: auto; 
            width: 100%; 
            -webkit-overflow-scrolling: touch;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 13px; 
            min-width: 600px;
        }
        th { 
            text-align: left; 
            padding: 10px 12px; 
            font-size: 11px; 
            color: #94a3b8; 
            text-transform: uppercase; 
            border-bottom: 2px solid #eef2f7; 
        }
        td { 
            padding: 10px 12px; 
            border-bottom: 1px solid #f1f5f9; 
        }
        tr:hover { background: #f8fafc; }
        
        .status-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        .status-badge.confirmado { background: #d1fae5; color: #065f46; }
        .status-badge.rascunho { background: #fef3c7; color: #92400e; }
        .status-badge.cancelado { background: #fee2e2; color: #991b1b; }
        
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .quick-actions a {
            padding: 12px;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #eef2f7;
            background: #f8fafc;
            color: #1a2332;
            font-size: 13px;
        }
        .quick-actions a:hover { background: #f5d76e20; border-color: #f5d76e; transform: translateY(-2px); }
        .quick-actions a .icon { font-size: 24px; display: block; margin-bottom: 3px; }
        .quick-actions a .label { font-size: 12px; font-weight: 500; }
        
        .chart-container { height: 250px; width: 100%; }
        
        .footer-info {
            text-align: center;
            padding: 15px 0 5px;
            color: #94a3b8;
            font-size: 13px;
            border-top: 1px solid #eef2f7;
            margin-top: 15px;
            width: 100%;
        }
        .footer-info strong { color: #c9a84c; }

        /* ===== ALERTA DE INSTALAÇÃO ===== */
        .install-alert {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        .install-alert .icon { font-size: 40px; margin-bottom: 8px; }
        .install-alert h2 { color: #92400e; margin-bottom: 8px; font-size: 20px; }
        .install-alert p { color: #78350f; margin-bottom: 12px; font-size: 14px; }
        .install-alert .btn-install {
            display: inline-block;
            padding: 10px 25px;
            background: #f59e0b;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            font-size: 14px;
        }
        .install-alert .btn-install:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }

        /* ============================================
           RESPONSIVIDADE
           ============================================ */
        @media (max-width: 992px) {
            .main-content .content-area {
                padding: 15px 15px !important;
            }
            .module-header h1 { font-size: 20px; }
            .module-actions a { font-size: 12px; padding: 6px 12px; }
        }
        
        @media (max-width: 768px) {
            .main-content .content-area {
                padding: 10px 12px !important;
            }
            .module-header { 
                padding: 15px; 
                flex-direction: column; 
                align-items: stretch; 
                text-align: center; 
            }
            .module-actions { justify-content: center; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 12px 15px; }
            .stat-card .value { font-size: 20px; }
            .quick-actions { grid-template-columns: 1fr 1fr; }
            .row { grid-template-columns: 1fr; }
            .card { padding: 12px 15px; }
            table { font-size: 12px; min-width: 500px; }
            th, td { padding: 8px 10px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr 1fr; }
            .module-header h1 { font-size: 18px; }
        }
    </style>
</head>
<body>

<div class="contabilidade-module">
    <div class="module-container">
        <!-- Header -->
        <div class="module-header">
            <div>
                <h1>📊 <span>Contabilidade</span></h1>
                <div class="subtitle">Gestão contábil completa e integrada</div>
            </div>
            <div class="module-actions">
                <?php if ($tabelasExistem): ?>
                <a href="lancamentos.php?acao=novo" class="primary">➕ Novo Lançamento</a>
                <a href="plano_contas.php">📋 Plano de Contas</a>
                <a href="balanco.php">📊 Balanço</a>
                <a href="dre.php">📈 DRE</a>
                <?php else: ?>
                <a href="install.php" class="primary">🔧 Instalar Módulo</a>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                <a href="periodos.php">📅 Períodos</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== ALERTA DE INSTALAÇÃO ===== -->
        <?php if ($mensagemInstalacao): ?>
        <div class="install-alert">
            <div class="icon">📦</div>
            <h2>Módulo de Contabilidade não instalado</h2>
            <p>As tabelas do módulo de contabilidade ainda não foram criadas no banco de dados.</p>
            <div>
                <a href="install.php" class="btn-install">🔧 Instalar Agora</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tabelasExistem): ?>
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total de Lançamentos</div>
                <div class="value"><?= number_format($totalLancamentos, 0, ',', '.') ?></div>
                <div class="sub"><?= $totalLancamentosMes ?> este mês</div>
            </div>
            <div class="stat-card" style="border-left-color: #2ecc71;">
                <div class="label">Receitas do Mês</div>
                <div class="value positive">Kz <?= number_format($receitaMes, 2, ',', '.') ?></div>
                <div class="sub">Mês atual</div>
            </div>
            <div class="stat-card" style="border-left-color: #e74c3c;">
                <div class="label">Despesas do Mês</div>
                <div class="value negative">Kz <?= number_format($despesaMes, 2, ',', '.') ?></div>
                <div class="sub">Mês atual</div>
            </div>
            <div class="stat-card" style="border-left-color: <?= $resultadoMes >= 0 ? '#2ecc71' : '#e74c3c' ?>;">
                <div class="label">Resultado do Mês</div>
                <div class="value <?= $resultadoMes >= 0 ? 'positive' : 'negative' ?>">
                    Kz <?= number_format($resultadoMes, 2, ',', '.') ?>
                </div>
                <div class="sub"><?= $resultadoMes >= 0 ? '📈 Lucro' : '📉 Prejuízo' ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #c9a84c;">
                <div class="label">Patrimônio Líquido</div>
                <div class="value gold">Kz <?= number_format($totalAtivo - $totalPassivo, 2, ',', '.') ?></div>
                <div class="sub">Ativo - Passivo</div>
            </div>
        </div>

        <!-- Gráfico + Ações Rápidas -->
        <div class="row">
            <div class="card">
                <h3>📈 Evolução Receitas x Despesas <span class="badge">últimos 6 meses</span></h3>
                <div class="chart-container">
                    <canvas id="graficoEvolucao"></canvas>
                </div>
            </div>
            <div class="card">
                <h3>⚡ Ações Rápidas</h3>
                <div class="quick-actions">
                    <a href="lancamentos.php?acao=novo">
                        <span class="icon">📝</span>
                        <span class="label">Novo Lançamento</span>
                    </a>
                    <a href="balanco.php">
                        <span class="icon">⚖️</span>
                        <span class="label">Balanço</span>
                    </a>
                    <a href="dre.php">
                        <span class="icon">📈</span>
                        <span class="label">DRE</span>
                    </a>
                    <a href="razao.php">
                        <span class="icon">📚</span>
                        <span class="label">Razão</span>
                    </a>
                    <a href="diario.php">
                        <span class="icon">📖</span>
                        <span class="label">Diário</span>
                    </a>
                    <a href="conciliacao.php">
                        <span class="icon">🔄</span>
                        <span class="label">Conciliação</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Últimos Lançamentos -->
        <div class="card">
            <h3>📋 Últimos Lançamentos</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nº</th>
                            <th>Data</th>
                            <th>Histórico</th>
                            <th>Tipo</th>
                            <th>Itens</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimosLancamentos as $l): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($l['numero_lancamento']) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></td>
                            <td><?= htmlspecialchars(substr($l['historico'], 0, 40)) ?></td>
                            <td><?= $l['tipo_documento'] ?></td>
                            <td><?= $l['total_itens'] ?></td>
                            <td>
                                <span class="status-badge <?= $l['status'] ?>">
                                    <?= ucfirst($l['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="lancamentos.php?acao=view&id=<?= $l['id'] ?>" style="color: #3498db; text-decoration: none; font-size: 13px;">Ver</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($ultimosLancamentos)): ?>
                        <tr><td colspan="7" style="text-align: center; color: #94a3b8; padding: 20px;">Nenhum lançamento registrado ainda</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 12px; text-align: right;">
                <a href="diario.php" style="color: #c9a84c; text-decoration: none; font-weight: 600; font-size: 14px;">Ver todos →</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer-info">
            <p>© <?= date('Y') ?> <strong>SoftGest</strong> - Módulo de Contabilidade v<?= CONTABILIDADE_VERSION ?></p>
        </div>
    </div>
</div>

<?php if ($tabelasExistem): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('graficoEvolucao');
    if (!ctx) return;
    
    const dados = <?= json_encode($graficoMeses) ?>;
    
    new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: dados.map(d => d.mes),
            datasets: [
                {
                    label: 'Receitas',
                    data: dados.map(d => d.receita),
                    backgroundColor: 'rgba(46, 204, 113, 0.7)',
                    borderColor: '#2ecc71',
                    borderWidth: 2
                },
                {
                    label: 'Despesas',
                    data: dados.map(d => d.despesa),
                    backgroundColor: 'rgba(231, 76, 60, 0.7)',
                    borderColor: '#e74c3c',
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 12,
                        font: { size: 11 }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Kz ' + value.toLocaleString('pt-BR');
                        },
                        font: { size: 10 }
                    }
                },
                x: {
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

</body>
</html>