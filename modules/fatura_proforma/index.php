<?php
// ============================================
// modules/fatura_proforma/index.php - Faturas Proforma
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
if (!temPermissao('Faturas', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== DADOS DO DASHBOARD =====
$totalRascunho = 0;
$totalEnviadas = 0;
$totalAprovadas = 0;
$totalRejeitadas = 0;
$totalFaturas = 0;

try {
    $totalRascunho = $pdo->query("SELECT COUNT(*) FROM faturas_proforma WHERE status = 'rascunho'")->fetchColumn() ?? 0;
    $totalEnviadas = $pdo->query("SELECT COUNT(*) FROM faturas_proforma WHERE status = 'enviada'")->fetchColumn() ?? 0;
    $totalAprovadas = $pdo->query("SELECT COUNT(*) FROM faturas_proforma WHERE status = 'aprovada'")->fetchColumn() ?? 0;
    $totalRejeitadas = $pdo->query("SELECT COUNT(*) FROM faturas_proforma WHERE status = 'rejeitada'")->fetchColumn() ?? 0;
    $totalFaturas = $pdo->query("SELECT COUNT(*) FROM faturas_proforma")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// Buscar faturas
$faturas = [];
try {
    $faturas = $pdo->query("
        SELECT fp.*, c.nome as cliente_nome 
        FROM faturas_proforma fp 
        LEFT JOIN clientes c ON fp.cliente_id = c.id 
        ORDER BY fp.created_at DESC
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
    <title>Faturas Proforma - <?= SITE_NAME ?></title>
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
        
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
        
        .stat-card.rascunho { border-left-color: #95a5a6; }
        .stat-card.enviada { border-left-color: #3498db; }
        .stat-card.aprovada { border-left-color: #2ecc71; }
        .stat-card.rejeitada { border-left-color: #e74c3c; }
        .stat-card.total { border-left-color: #c9a84c; }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 750px;
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
        
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-rascunho {
            background: #f1f5f9;
            color: #4a5568;
        }
        
        .status-enviada {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-aprovada {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-rejeitada {
            background: #fee2e2;
            color: #991b1b;
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
        
        .valor {
            font-weight: 600;
            color: #1a2332;
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
            
            .page-header .actions {
                justify-content: stretch;
            }
            
            .page-header .actions .btn {
                flex: 1;
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
                    <h1>📄 Faturas Proforma</h1>
                    <p class="subtitle">Criar e gerenciar faturas proforma</p>
                </div>
                <div class="actions">
                    <a href="add.php" class="btn btn-primary">➕ Nova Fatura</a>
                    <a href="<?= SITE_URL ?>" class="btn btn-secondary">← Voltar</a>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card rascunho">
                    <span class="icon">📝</span>
                    <div class="number"><?= $totalRascunho ?></div>
                    <div class="label">Rascunho</div>
                </div>
                <div class="stat-card enviada">
                    <span class="icon">📤</span>
                    <div class="number"><?= $totalEnviadas ?></div>
                    <div class="label">Enviadas</div>
                </div>
                <div class="stat-card aprovada">
                    <span class="icon">✅</span>
                    <div class="number"><?= $totalAprovadas ?></div>
                    <div class="label">Aprovadas</div>
                </div>
                <div class="stat-card rejeitada">
                    <span class="icon">❌</span>
                    <div class="number"><?= $totalRejeitadas ?></div>
                    <div class="label">Rejeitadas</div>
                </div>
                <div class="stat-card total">
                    <span class="icon">📊</span>
                    <div class="number"><?= $totalFaturas ?></div>
                    <div class="label">Total</div>
                </div>
            </div>
            
            <!-- Tabela -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Data Emissão</th>
                            <th>Validade</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($faturas) > 0): ?>
                            <?php foreach($faturas as $fatura): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($fatura['numero']) ?></strong></td>
                                <td><?= htmlspecialchars($fatura['cliente_nome'] ?? '-') ?></td>
                                <td class="valor">R$ <?= number_format($fatura['total'], 2, ',', '.') ?></td>
                                <td>
                                    <span class="status-badge status-<?= $fatura['status'] ?>">
                                        <?= ucfirst($fatura['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($fatura['data_emissao'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($fatura['data_validade'])) ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="view.php?id=<?= $fatura['id'] ?>" class="btn btn-sm btn-info">Visualizar</a>
                                        <a href="edit.php?id=<?= $fatura['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                        <?php if ($fatura['status'] == 'enviada'): ?>
                                        <a href="aprovar.php?id=<?= $fatura['id'] ?>" class="btn btn-sm btn-success">✅ Aprovar</a>
                                        <a href="rejeitar.php?id=<?= $fatura['id'] ?>" class="btn btn-sm btn-danger">❌ Rejeitar</a>
                                        <?php endif; ?>
                                        <a href="delete.php?id=<?= $fatura['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?')">Excluir</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <span class="icon">📭</span>
                                        <h3>Nenhuma fatura cadastrada</h3>
                                        <p>Clique em "Nova Fatura" para começar.</p>
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

    console.log('📄 Módulo Faturas Proforma carregado!');
</script>

</body>
</html>