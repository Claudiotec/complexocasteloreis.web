<?php
// ============================================
// modules/escola/financeiro/relatorios/imprimir_pagamentos.php
// Página de Impressão do Relatório de Pagamentos
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== FILTROS (recebidos via GET) =====
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$filtro_aluno = isset($_GET['aluno_id']) ? intval($_GET['aluno_id']) : 0;
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_forma = isset($_GET['forma']) ? $_GET['forma'] : '';

// ===== BUSCAR DADOS =====
$pagamentos = [];
$total_geral = 0;
$total_confirmados = 0;
$total_pendentes = 0;
$total_por_forma = [];
$total_por_mes = [];

try {
    $sql = "
        SELECT 
            p.*,
            a.nome as aluno_nome,
            a.Classe as aluno_classe,
            a.TURMA as aluno_turma,
            a.Periodo as aluno_periodo,
            e.nome as emolumento_nome
        FROM pagamentos p
        LEFT JOIN alunos a ON p.aluno_id = a.id
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        WHERE p.data_pagamento BETWEEN ? AND ?
    ";
    $params = [$filtro_data_inicio, $filtro_data_fim];
    
    if ($filtro_aluno > 0) {
        $sql .= " AND p.aluno_id = ?";
        $params[] = $filtro_aluno;
    }
    
    if (!empty($filtro_status)) {
        $sql .= " AND p.status = ?";
        $params[] = $filtro_status;
    }
    
    if (!empty($filtro_forma)) {
        $sql .= " AND p.forma_pagamento = ?";
        $params[] = $filtro_forma;
    }
    
    $sql .= " ORDER BY p.data_pagamento DESC, p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pagamentos = $stmt->fetchAll();
    
    // Calcular totais e estatísticas
    foreach ($pagamentos as $p) {
        $total_geral += $p['valor'];
        
        if ($p['status'] == 'confirmado') {
            $total_confirmados++;
        } else {
            $total_pendentes++;
        }
        
        // Por forma de pagamento
        $forma = $p['forma_pagamento'] ?? 'outros';
        if (!isset($total_por_forma[$forma])) {
            $total_por_forma[$forma] = ['total' => 0, 'count' => 0];
        }
        $total_por_forma[$forma]['total'] += $p['valor'];
        $total_por_forma[$forma]['count']++;
        
        // Por mês
        $mes = date('m/Y', strtotime($p['data_pagamento']));
        if (!isset($total_por_mes[$mes])) {
            $total_por_mes[$mes] = ['total' => 0, 'count' => 0];
        }
        $total_por_mes[$mes]['total'] += $p['valor'];
        $total_por_mes[$mes]['count']++;
    }
    
    ksort($total_por_mes);
    
} catch (Exception $e) {
    $pagamentos = [];
}

// ===== BUSCAR NOME DO ALUNO =====
$nome_aluno = '';
if ($filtro_aluno > 0) {
    try {
        $stmt = $pdo->prepare("SELECT nome FROM alunos WHERE id = ?");
        $stmt->execute([$filtro_aluno]);
        $aluno = $stmt->fetch();
        $nome_aluno = $aluno['nome'] ?? '';
    } catch (Exception $e) {}
}

// ===== FUNÇÃO PARA FORMATAR MOEDA =====
function formatarMoeda($valor) {
    return 'Kz ' . number_format($valor, 2, ',', '.');
}

// ===== FUNÇÃO PARA STATUS =====
function getStatusLabel($status) {
    $statuses = [
        'confirmado' => 'Confirmado',
        'pendente' => 'Pendente',
        'cancelado' => 'Cancelado',
        'reembolsado' => 'Reembolsado'
    ];
    return $statuses[$status] ?? 'Pendente';
}

// ===== FUNÇÃO PARA STATUS BADGE =====
function getStatusBadgePrint($status) {
    $statuses = [
        'confirmado' => 'bg-success',
        'pendente' => 'bg-warning',
        'cancelado' => 'bg-danger',
        'reembolsado' => 'bg-info'
    ];
    $class = $statuses[$status] ?? 'bg-warning';
    return '<span class="badge ' . $class . '">' . getStatusLabel($status) . '</span>';
}

// ===== FUNÇÃO PARA FORMA DE PAGAMENTO =====
function getFormaLabel($forma) {
    $formas = [
        'dinheiro' => 'Dinheiro',
        'cartao' => 'Cartão',
        'cartao_credito' => 'Cartão Crédito',
        'cartao_debito' => 'Cartão Débito',
        'transferencia' => 'Transferência',
        'pix' => 'PIX',
        'boleto' => 'Boleto'
    ];
    return $formas[$forma] ?? $forma;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Pagamentos - Impressão</title>
    <style>
        /* ===== ESTILOS GERAIS ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            background: #f5f6fa;
            padding: 20px;
            color: #1a2332;
        }
        
        .print-container {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 30px 35px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        /* ===== CABEÇALHO ===== */
        .report-header {
            text-align: center;
            border-bottom: 3px double #1a2332;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        
        .report-header .school-name {
            font-size: 28px;
            font-weight: 800;
            color: #1a2332;
            letter-spacing: 1px;
        }
        
        .report-header .school-name .icon {
            color: #c9a84c;
        }
        
        .report-header .report-title {
            font-size: 22px;
            color: #c9a84c;
            margin: 5px 0 0;
            font-weight: 700;
        }
        
        .report-header .report-subtitle {
            font-size: 13px;
            color: #94a3b8;
            margin: 3px 0 0;
        }
        
        .report-header .period {
            font-size: 13px;
            color: #4a5568;
            margin: 10px 0 0;
            background: #f8fafc;
            display: inline-block;
            padding: 5px 25px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .report-header .filters-info {
            font-size: 12px;
            color: #94a3b8;
            margin: 8px 0 0;
        }
        
        /* ===== STATS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .stat-card .number {
            font-size: 24px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .stat-card .label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 3px;
        }
        
        .stat-card.total { border-top: 4px solid #3498db; }
        .stat-card.count { border-top: 4px solid #c9a84c; }
        .stat-card.confirmados { border-top: 4px solid #2ecc71; }
        .stat-card.pendentes { border-top: 4px solid #f39c12; }
        
        /* ===== GRÁFICOS ===== */
        .chart-container {
            margin: 20px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .chart-container .chart-title {
            font-size: 15px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .chart-bars {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            height: 180px;
            padding: 10px 0;
            border-bottom: 2px solid #1a2332;
        }
        
        .chart-bar-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            max-width: 80px;
        }
        
        .chart-bar {
            width: 40px;
            background: linear-gradient(to top, #c9a84c, #e8d07a);
            border-radius: 4px 4px 0 0;
            min-height: 5px;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .chart-bar .bar-value {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            font-weight: 700;
            color: #1a2332;
            white-space: nowrap;
            background: rgba(255,255,255,0.9);
            padding: 1px 6px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        
        .chart-bar-label {
            font-size: 10px;
            color: #4a5568;
            margin-top: 6px;
            text-align: center;
            font-weight: 600;
        }
        
        .chart-progress {
            width: 100%;
            margin: 5px 0;
        }
        
        .chart-progress .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #4a5568;
            margin-bottom: 2px;
        }
        
        .chart-progress .progress-bar {
            width: 100%;
            height: 20px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .chart-progress .progress-bar .progress-fill {
            height: 100%;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
            font-size: 10px;
            font-weight: 600;
            color: #fff;
            min-width: 30px;
        }
        
        /* ===== TABELA ===== */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a2332;
            margin: 25px 0 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #c9a84c;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title .badge-count {
            background: #c9a84c;
            color: #1a2332;
            padding: 1px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .table-print {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 15px 0;
        }
        
        .table-print th {
            background: #1a2332;
            color: #fff;
            padding: 8px 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .table-print th:last-child,
        .table-print td:last-child {
            text-align: right;
        }
        
        .table-print td {
            padding: 6px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .table-print tr:hover {
            background: #f8fafc;
        }
        
        .table-print .total-row {
            background: #f8fafc;
            font-weight: 700;
        }
        
        .table-print .total-row td {
            border-top: 2px solid #1a2332;
        }
        
        .table-print .valor {
            color: #2ecc71;
            font-weight: 600;
        }
        
        /* ===== BADGES ===== */
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .bg-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .bg-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .bg-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .bg-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-forma {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 600;
            background: #e2e8f0;
            color: #4a5568;
        }
        
        /* ===== RODAPÉ ===== */
        .print-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            color: #94a3b8;
        }
        
        .print-footer .footer-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        /* ===== BOTÕES ===== */
        .no-print {
            text-align: center;
            padding: 20px;
            margin-top: 20px;
        }
        
        .no-print .btn {
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn-print {
            background: #1a2332;
            color: #fff;
        }
        
        .btn-print:hover {
            background: #2d3748;
            transform: translateY(-2px);
        }
        
        .btn-close {
            background: #e74c3c;
            color: #fff;
        }
        
        .btn-close:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-back {
            background: #f1f5f9;
            color: #4a5568;
        }
        
        .btn-back:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        
        /* ===== RESPONSIVO ===== */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .print-container {
                padding: 15px;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .chart-bars {
                height: 120px;
            }
            .chart-bar {
                width: 25px;
            }
            .report-header .school-name {
                font-size: 22px;
            }
            .report-header .report-title {
                font-size: 18px;
            }
            .stat-card .number {
                font-size: 18px;
            }
            .table-print {
                font-size: 9px;
            }
            .table-print th,
            .table-print td {
                padding: 4px 6px;
            }
        }
        
        /* ===== IMPRESSÃO ===== */
        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
            }
            .print-container {
                box-shadow: none !important;
                padding: 10px !important;
                border-radius: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .table-print {
                font-size: 9px !important;
            }
            .table-print th,
            .table-print td {
                padding: 3px 5px !important;
            }
            .stats-grid {
                page-break-inside: avoid;
            }
            .chart-container {
                page-break-inside: avoid;
            }
            .stat-card {
                padding: 10px !important;
            }
            .stat-card .number {
                font-size: 18px !important;
            }
            .chart-bar {
                width: 30px !important;
            }
            .chart-bars {
                height: 140px !important;
            }
            .print-footer {
                margin-top: 15px !important;
                padding-top: 10px !important;
            }
            .section-title {
                font-size: 14px !important;
                margin: 15px 0 8px !important;
            }
            .report-header .school-name {
                font-size: 22px !important;
            }
            .report-header .report-title {
                font-size: 18px !important;
            }
            .print-container {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="print-container" id="printArea">
        <!-- ===== CABEÇALHO ===== -->
        <div class="report-header">
            <h1 class="school-name"><span class="icon">🎓</span> SoftGest Web</h1>
            <h2 class="report-title">📊 RELATÓRIO DE PAGAMENTOS</h2>
            <p class="report-subtitle">Sistema de Gestão Escolar</p>
            <p class="period">
                📅 Período: <?= date('d/m/Y', strtotime($filtro_data_inicio)) ?> a <?= date('d/m/Y', strtotime($filtro_data_fim)) ?>
            </p>
            <p class="filters-info">
                <?php if ($filtro_aluno > 0 && !empty($nome_aluno)): ?>
                    👤 Aluno: <strong><?= htmlspecialchars($nome_aluno) ?></strong>
                <?php endif; ?>
                <?php if (!empty($filtro_status)): ?>
                    <?= $filtro_aluno > 0 ? ' | ' : '' ?>
                    📌 Status: <strong><?= getStatusLabel($filtro_status) ?></strong>
                <?php endif; ?>
                <?php if (!empty($filtro_forma)): ?>
                    <?= ($filtro_aluno > 0 || !empty($filtro_status)) ? ' | ' : '' ?>
                    💳 Forma: <strong><?= getFormaLabel($filtro_forma) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <!-- ===== STATS ===== -->
        <div class="stats-grid">
            <div class="stat-card count">
                <div class="number"><?= count($pagamentos) ?></div>
                <div class="label">Total de Pagamentos</div>
            </div>
            <div class="stat-card total">
                <div class="number"><?= formatarMoeda($total_geral) ?></div>
                <div class="label">Valor Total</div>
            </div>
            <div class="stat-card confirmados">
                <div class="number"><?= $total_confirmados ?></div>
                <div class="label">✅ Confirmados</div>
            </div>
            <div class="stat-card pendentes">
                <div class="number"><?= $total_pendentes ?></div>
                <div class="label">⏳ Pendentes</div>
            </div>
        </div>

        <!-- ===== GRÁFICO DE BARRAS - EVOLUÇÃO MENSAL ===== -->
        <?php if (count($total_por_mes) > 0): ?>
        <div class="chart-container">
            <div class="chart-title">📈 Evolução Mensal dos Pagamentos</div>
            <div class="chart-bars">
                <?php 
                $max_valor = max(array_column($total_por_mes, 'total'));
                $max_valor = $max_valor > 0 ? $max_valor : 1;
                foreach ($total_por_mes as $mes => $dados): 
                    $altura = ($dados['total'] / $max_valor) * 100;
                    $altura = max($altura, 5);
                ?>
                <div class="chart-bar-item">
                    <div class="chart-bar" style="height: <?= $altura ?>%;">
                        <span class="bar-value"><?= formatarMoeda($dados['total']) ?></span>
                    </div>
                    <div class="chart-bar-label"><?= $mes ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== GRÁFICO - FORMA DE PAGAMENTO ===== -->
        <?php if (count($total_por_forma) > 0): ?>
        <div class="chart-container">
            <div class="chart-title">💳 Distribuição por Forma de Pagamento</div>
            <?php 
            $cores = ['#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c', '#e67e22'];
            $total_formas = array_sum(array_column($total_por_forma, 'total'));
            $i = 0;
            foreach ($total_por_forma as $forma => $dados): 
                $percentual = $total_formas > 0 ? ($dados['total'] / $total_formas) * 100 : 0;
                $cor = $cores[$i % count($cores)];
                $i++;
            ?>
            <div class="chart-progress">
                <div class="progress-label">
                    <span><strong><?= getFormaLabel($forma) ?></strong></span>
                    <span><?= formatarMoeda($dados['total']) ?> (<?= $dados['count'] ?> pag.)</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $percentual ?>%;background:<?= $cor ?>;">
                        <?= $percentual >= 10 ? number_format($percentual, 1) . '%' : '' ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== TABELA DETALHADA ===== -->
        <div class="section-title">
            📋 Detalhamento dos Pagamentos
            <span class="badge-count"><?= count($pagamentos) ?> registros</span>
        </div>

        <?php if (count($pagamentos) > 0): ?>
        <table class="table-print">
            <thead>
                <tr>
                    <th style="width:30px;">#</th>
                    <th style="width:18%;">Aluno</th>
                    <th style="width:20%;">Emolumento</th>
                    <th style="width:12%;text-align:right;">Valor</th>
                    <th style="width:12%;">Data</th>
                    <th style="width:10%;">Forma</th>
                    <th style="width:10%;">Status</th>
                    <th style="width:12%;">Mês Ref.</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($pagamentos as $p): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($p['aluno_nome'] ?? 'N/A') ?></strong></td>
                    <td><?= htmlspecialchars($p['emolumento_nome'] ?? 'N/A') ?></td>
                    <td style="text-align:right;" class="valor"><?= formatarMoeda($p['valor']) ?></td>
                    <td><?= date('d/m/Y', strtotime($p['data_pagamento'])) ?></td>
                    <td><span class="badge-forma"><?= getFormaLabel($p['forma_pagamento'] ?? '') ?></span></td>
                    <td><?= getStatusBadgePrint($p['status'] ?? 'pendente') ?></td>
                    <td><?= htmlspecialchars($p['mes_referencia'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" style="text-align:right;">TOTAL GERAL</td>
                    <td style="text-align:right;"><?= formatarMoeda($total_geral) ?></td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
        <div style="text-align:center;padding:40px;color:#94a3b8;">
            <span style="font-size:48px;display:block;margin-bottom:15px;">📭</span>
            <h3 style="color:#4a5568;">Nenhum pagamento encontrado</h3>
            <p>Não há pagamentos para os filtros selecionados.</p>
        </div>
        <?php endif; ?>

        <!-- ===== RODAPÉ ===== -->
        <div class="print-footer">
            <div class="footer-info">
                <span>📅 Relatório gerado em <?= date('d/m/Y H:i:s') ?></span>
                <span>© <?= date('Y') ?> SoftGest Web - Sistema de Gestão Escolar</span>
                <span style="font-size:8px;color:#bbb;">Documento emitido eletronicamente</span>
            </div>
        </div>
    </div>

    <!-- ===== BOTÕES ===== -->
    <div class="no-print">
        <button class="btn btn-print" onclick="imprimirRelatorio()">🖨️ Imprimir</button>
        <button class="btn btn-back" onclick="window.location.href='pagamentos.php'">← Voltar</button>
        <button class="btn btn-close" onclick="window.close()">✕ Fechar</button>
    </div>

    <script>
        function imprimirRelatorio() {
            window.print();
        }

        // Atalho Ctrl+P
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                imprimirRelatorio();
            }
        });
    </script>
</body>
</html>