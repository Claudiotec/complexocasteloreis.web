<?php
// ============================================
// modules/escola/financeiro/relatorios/index.php - Relatórios
// ============================================

// Carregar configurações
require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== DADOS PARA RELATÓRIOS =====
$totalAlunos = 0;
$totalPagamentos = 0;
$totalMensalidades = 0;
$totalEmolumentos = 0;
$totalArrecadado = 0;
$totalPendente = 0;

try {
    $totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos WHERE status = 'ativo'")->fetchColumn() ?? 0;
    $totalPagamentos = $pdo->query("SELECT COUNT(*) FROM pagamentos WHERE status = 'confirmado'")->fetchColumn() ?? 0;
    $totalMensalidades = $pdo->query("SELECT COUNT(*) FROM mensalidades")->fetchColumn() ?? 0;
    $totalEmolumentos = $pdo->query("SELECT COUNT(*) FROM emolumentos WHERE status = 'ativo'")->fetchColumn() ?? 0;
    $totalArrecadado = $pdo->query("SELECT SUM(valor) FROM pagamentos WHERE status = 'confirmado'")->fetchColumn() ?? 0;
    $totalPendente = $pdo->query("SELECT SUM(valor) FROM mensalidades WHERE status IN ('pendente', 'atrasado')")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// ===== PAGAMENTOS POR MÊS =====
$pagamentosMes = [];
try {
    $pagamentosMes = $pdo->query("
        SELECT 
            DATE_FORMAT(data_pagamento, '%Y-%m') as mes,
            COUNT(*) as total,
            SUM(valor) as valor_total
        FROM pagamentos 
        WHERE status = 'confirmado'
        GROUP BY DATE_FORMAT(data_pagamento, '%Y-%m')
        ORDER BY mes DESC
        LIMIT 12
    ")->fetchAll();
} catch (Exception $e) {}

// ===== INCLUIR HEADER =====
include '../../includes/header_escola.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .page-header .subtitle {
        color: #94a3b8;
        font-size: 14px;
        margin: 2px 0 0;
    }
    
    .btn {
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
    }
    
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    
    .btn-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }
    
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    
    .btn-success:hover {
        background: #27ae60;
    }
    
    .menu-financeiro {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 25px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .menu-financeiro a {
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
        color: #4a5568;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .menu-financeiro a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .menu-financeiro a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-card {
        background: white;
        padding: 18px 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        border-left: 4px solid #c9a84c;
        transition: all 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .stat-card .number {
        font-size: 26px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .stat-card .label {
        font-size: 12px;
        color: #94a3b8;
        margin: 3px 0 0;
    }
    
    .stat-card .icon {
        font-size: 24px;
        display: block;
        margin-bottom: 5px;
    }
    
    .stat-card.alunos { border-left-color: #9b59b6; }
    .stat-card.pagamentos { border-left-color: #2ecc71; }
    .stat-card.emolumentos { border-left-color: #3498db; }
    .stat-card.mensalidades { border-left-color: #f39c12; }
    
    .report-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .report-card {
        background: white;
        border-radius: 12px;
        padding: 20px 24px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .report-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    }
    
    .report-card .icon {
        font-size: 36px;
        display: block;
        margin-bottom: 10px;
    }
    
    .report-card h3 {
        font-size: 16px;
        color: #1a2332;
        margin: 0 0 5px;
        font-weight: 600;
    }
    
    .report-card p {
        font-size: 13px;
        color: #94a3b8;
        margin: 0 0 12px;
    }
    
    .report-card .badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(201, 168, 76, 0.1);
        color: #c9a84c;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        margin-top: 20px;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        min-width: 500px;
    }
    
    .table th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }
    
    .table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    
    .table tr:hover {
        background: #fafbfc;
    }
    
    .valor-positivo {
        color: #2ecc71;
        font-weight: 600;
    }
    
    .empty-state {
        text-align: center;
        padding: 30px 20px;
        color: #94a3b8;
    }
    
    .empty-state .icon {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        font-size: 18px;
        color: #4a5568;
        margin: 0 0 5px;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .menu-financeiro {
            flex-direction: column;
            align-items: stretch;
        }
        .menu-financeiro a {
            text-align: center;
            justify-content: center;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .stat-card {
            padding: 14px 16px;
        }
        .stat-card .number {
            font-size: 22px;
        }
        .report-grid {
            grid-template-columns: 1fr;
        }
        .table {
            font-size: 12px;
            min-width: 400px;
        }
        .table th, .table td {
            padding: 8px 10px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📈 Relatórios Financeiros</h1>
        <p class="subtitle">Análise e relatórios do financeiro escolar</p>
    </div>
    <a href="../index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="../pagamentos/">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="index.php" class="active">📈 Relatórios</a>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card alunos">
        <span class="icon">👨‍🎓</span>
        <div class="number"><?= $totalAlunos ?></div>
        <div class="label">Alunos Ativos</div>
    </div>
    <div class="stat-card pagamentos">
        <span class="icon">💳</span>
        <div class="number"><?= $totalPagamentos ?></div>
        <div class="label">Pagamentos Realizados</div>
    </div>
    <div class="stat-card emolumentos">
        <span class="icon">📋</span>
        <div class="number"><?= $totalEmolumentos ?></div>
        <div class="label">Emolumentos Ativos</div>
    </div>
    <div class="stat-card mensalidades">
        <span class="icon">📅</span>
        <div class="number"><?= $totalMensalidades ?></div>
        <div class="label">Mensalidades</div>
    </div>
</div>

<!-- Cards de Relatórios -->
<div class="report-grid">
    <div class="report-card" onclick="window.location.href='resumo.php'">
        <span class="icon">📊</span>
        <h3>Resumo Financeiro</h3>
        <p>Visão geral consolidada das finanças</p>
        <span class="badge">Total: R$ <?= number_format($totalArrecadado, 2, ',', '.') ?></span>
    </div>
    
    <div class="report-card" onclick="window.location.href='pagamentos.php'">
        <span class="icon">💳</span>
        <h3>Relatório de Pagamentos</h3>
        <p>Histórico completo de pagamentos</p>
        <span class="badge"><?= $totalPagamentos ?> registros</span>
    </div>
    
    <div class="report-card" onclick="window.location.href='mensalidades.php'">
        <span class="icon">📅</span>
        <h3>Relatório de Mensalidades</h3>
        <p>Status e inadimplência</p>
        <span class="badge">Pendente: R$ <?= number_format($totalPendente, 2, ',', '.') ?></span>
    </div>
    
    <div class="report-card" onclick="window.location.href='alunos.php'">
        <span class="icon">👨‍🎓</span>
        <h3>Relatório por Aluno</h3>
        <p>Histórico financeiro por aluno</p>
        <span class="badge"><?= $totalAlunos ?> alunos</span>
    </div>
</div>

<!-- Resumo de Pagamentos por Mês -->
<div class="table-responsive">
    <h3 style="padding: 16px 16px 0; color: #1a2332; font-size: 16px;">📊 Pagamentos por Mês</h3>
    <?php if (count($pagamentosMes) > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>Mês/Ano</th>
                <th>Total de Pagamentos</th>
                <th>Valor Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($pagamentosMes as $pm): ?>
            <tr>
                <td><?= date('m/Y', strtotime($pm['mes'] . '-01')) ?></td>
                <td><?= $pm['total'] ?></td>
                <td class="valor-positivo">R$ <?= number_format($pm['valor_total'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
        <span class="icon">📭</span>
        <h3>Nenhum dado disponível</h3>
        <p>Registre pagamentos para visualizar os relatórios.</p>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer_escola.php'; ?>