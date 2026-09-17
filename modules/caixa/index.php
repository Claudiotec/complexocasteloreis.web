<?php
// ============================================
// modules/caixa/index.php - Fluxo de Caixa
// ============================================

// Carregar configurações
require_once '../../config/database.php';
require_once '../../config/app_modes.php';

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
if (!temPermissao('Fluxo de Caixa', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== DADOS DO DASHBOARD =====
$totalEntradas = 0;
$totalSaidas = 0;
$saldoAtual = 0;
$vendasMes = 0;

try {
    $totalEntradas = $pdo->query("SELECT SUM(valor) as total FROM movimentacoes_caixa WHERE tipo = 'entrada' AND status = 'confirmado'")->fetchColumn() ?? 0;
    $totalSaidas = $pdo->query("SELECT SUM(valor) as total FROM movimentacoes_caixa WHERE tipo = 'saida' AND status = 'confirmado'")->fetchColumn() ?? 0;
    $saldoAtual = $totalEntradas - $totalSaidas;
    
    $mes = date('Y-m');
    $vendasMes = $pdo->query("SELECT SUM(valor) as total FROM movimentacoes_caixa WHERE tipo = 'entrada' AND categoria = 'Vendas' AND DATE_FORMAT(data_movimento, '%Y-%m') = '$mes' AND status = 'confirmado'")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// Buscar movimentações recentes
$movimentacoes = [];
try {
    $movimentacoes = $pdo->query("
        SELECT mc.*, c.nome as cliente_nome 
        FROM movimentacoes_caixa mc 
        LEFT JOIN clientes c ON mc.cliente_id = c.id 
        WHERE mc.status = 'confirmado' 
        ORDER BY mc.data_movimento DESC 
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {}

// ===== INCLUIR HEADER =====
include '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fluxo de Caixa - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================
           CORREÇÃO DE LAYOUT
           ============================================ */
        .dashboard-container {
            display: flex !important;
            min-height: 100vh !important;
            width: 100% !important;
        }

        .sidebar {
            width: 260px !important;
            min-width: 260px !important;
            flex-shrink: 0 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            height: 100vh !important;
            z-index: 1000 !important;
            overflow-y: auto !important;
        }

        .main-content {
            flex: 1 !important;
            margin-left: 260px !important;
            min-height: 100vh !important;
            width: calc(100% - 260px) !important;
            max-width: calc(100% - 260px) !important;
            background: #f0f2f5 !important;
            overflow-x: hidden !important;
        }

        .content-area {
            padding: 20px 30px !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: auto !important;
        }

        /* ============================================
           ESTILOS DO MÓDULO
           ============================================ */
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
        
        .menu-caixa {
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
        
        .menu-caixa a {
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
        
        .menu-caixa a:hover {
            background: #c9a84c;
            color: #1a2332;
            border-color: #c9a84c;
            transform: translateY(-2px);
        }
        
        .menu-caixa a.active {
            background: #c9a84c;
            color: #1a2332;
            border-color: #c9a84c;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
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
        
        .btn-danger {
            background: #e74c3c;
            color: #fff;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-warning {
            background: #f39c12;
            color: #fff;
        }
        
        .btn-warning:hover {
            background: #d68910;
        }
        
        .btn-info {
            background: #3498db;
            color: #fff;
        }
        
        .btn-info:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 11px;
            border-radius: 6px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        
        .stat-card.entradas { border-left-color: #2ecc71; }
        .stat-card.saidas { border-left-color: #e74c3c; }
        .stat-card.saldo { border-left-color: #3498db; }
        .stat-card.vendas { border-left-color: #f39c12; }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            margin-top: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 800px;
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
        
        .badge-tipo {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-entrada {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-saida {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .valor-positivo {
            color: #2ecc71;
            font-weight: 600;
        }
        
        .valor-negativo {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
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
        
        .table-actions {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a2332;
            margin: 20px 0 15px;
        }
        
        /* ============================================
           RESPONSIVIDADE
           ============================================ */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }
        
        @media (max-width: 768px) {
            .content-area {
                padding: 15px !important;
            }
            
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .menu-caixa {
                flex-direction: column;
                align-items: stretch;
            }
            
            .menu-caixa a {
                text-align: center;
                justify-content: center;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .actions .btn {
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
            
            .table {
                font-size: 12px;
                min-width: 650px;
            }
            
            .table th, .table td {
                padding: 8px 10px;
            }
            
            .btn-sm {
                font-size: 10px;
                padding: 3px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .content-area {
                padding: 10px !important;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar já está incluída pelo header.php -->
    
    <main class="main-content">
        <!-- Top Bar já está incluída pelo header.php -->
        
        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1>💰 Fluxo de Caixa</h1>
                    <p class="subtitle">Controle de entradas, saídas e vendas</p>
                </div>
                <a href="<?= SITE_URL ?>" class="btn btn-secondary">← Voltar</a>
            </div>
            
            <!-- Menu do Caixa -->
            <div class="menu-caixa">
                <a href="index.php" class="active">📊 Dashboard</a>
                <a href="venda.php">🛒 Vender Produto</a>
                <a href="entrada.php">📥 Entrada (Receita)</a>
                <a href="saida.php">📤 Saída (Despesa)</a>
                <a href="movimentacoes.php">📋 Movimentações</a>
                <a href="relatorio.php">📈 Relatório</a>
            </div>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card entradas">
                    <span class="icon">📥</span>
                    <div class="number">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></div>
                    <div class="label">Total Entradas</div>
                </div>
                <div class="stat-card saidas">
                    <span class="icon">📤</span>
                    <div class="number">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></div>
                    <div class="label">Total Saídas</div>
                </div>
                <div class="stat-card saldo">
                    <span class="icon">💰</span>
                    <div class="number" style="color: <?= $saldoAtual >= 0 ? '#2ecc71' : '#e74c3c' ?>;">
                        R$ <?= number_format($saldoAtual, 2, ',', '.') ?>
                    </div>
                    <div class="label">Saldo Atual</div>
                </div>
                <div class="stat-card vendas">
                    <span class="icon">🛒</span>
                    <div class="number">R$ <?= number_format($vendasMes, 2, ',', '.') ?></div>
                    <div class="label">Vendas do Mês</div>
                </div>
            </div>
            
            <!-- Ações Rápidas -->
            <div class="actions">
                <a href="venda.php" class="btn btn-primary">🛒 Nova Venda</a>
                <a href="entrada.php" class="btn btn-success">📥 Nova Entrada</a>
                <a href="saida.php" class="btn btn-danger">📤 Nova Saída</a>
                <a href="relatorio.php" class="btn btn-info">📈 Relatório</a>
            </div>
            
            <!-- Últimas Movimentações -->
            <div class="section-title">📋 Últimas Movimentações</div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th>Descrição</th>
                            <th>Cliente</th>
                            <th>Valor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($movimentacoes) > 0): ?>
                            <?php foreach($movimentacoes as $mov): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($mov['data_movimento'])) ?></td>
                                <td>
                                    <span class="badge-tipo badge-<?= $mov['tipo'] ?>">
                                        <?= $mov['tipo'] == 'entrada' ? '📥 Entrada' : '📤 Saída' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($mov['categoria']) ?></td>
                                <td><?= htmlspecialchars(substr($mov['descricao'] ?? '', 0, 30)) ?></td>
                                <td><?= htmlspecialchars($mov['cliente_nome'] ?? '-') ?></td>
                                <td class="<?= $mov['tipo'] == 'entrada' ? 'valor-positivo' : 'valor-negativo' ?>">
                                    <?= $mov['tipo'] == 'entrada' ? '+' : '-' ?> R$ <?= number_format($mov['valor'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="view.php?id=<?= $mov['id'] ?>" class="btn btn-sm btn-info">Visualizar</a>
                                        <a href="delete.php?id=<?= $mov['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?')">Excluir</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <span class="icon">📭</span>
                                        <h3>Nenhuma movimentação registrada</h3>
                                        <p>Clique em "Nova Entrada" ou "Nova Saída" para começar.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Rodapé -->
            <div class="dashboard-footer">
                <p>© <?= date('Y') ?> <strong><?= htmlspecialchars($nomeEmpresa ?? 'SoftGest') ?></strong> - Sistema de Gestão Empresarial</p>
            </div>
        </div>
    </main>
</div>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script>
    // Função para toggle sidebar (mobile)
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('active');
    }

    function closeSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
    }

    // Fechar sidebar ao clicar fora (mobile)
    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('.menu-toggle');
        const isMobile = window.innerWidth <= 992;
        if (isMobile && sidebar && toggle) {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                closeSidebar();
            }
        }
    });

    console.log('💰 Módulo Fluxo de Caixa carregado!');
</script>

</body>
</html>