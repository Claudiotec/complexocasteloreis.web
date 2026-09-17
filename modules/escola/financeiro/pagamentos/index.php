<?php
// modules/escola/index.php
// Ou esta versão (mais robusta):
require_once(__DIR__ . '/verificar_permissao_escola.php');

$permissoes = verificarMultiplasPermissoesEscola('escola', ['visualizar', 'criar', 'editar', 'excluir']);

if ($permissoes['visualizar']) {
    // Mostra conteúdo
}

if ($permissoes['criar']) {
    // Mostra botão criar
}
?>





<?php
// ============================================
// modules/escola/financeiro/pagamentos/index.php - Pagamentos
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

// ===== DADOS =====
$totalPagamentos = 0;
$totalConfirmados = 0;
$totalPendentes = 0;
$totalCancelados = 0;
$valorTotal = 0;

try {
    $totalPagamentos = $pdo->query("SELECT COUNT(*) FROM pagamentos")->fetchColumn() ?? 0;
    $totalConfirmados = $pdo->query("SELECT COUNT(*) FROM pagamentos WHERE status = 'confirmado'")->fetchColumn() ?? 0;
    $totalPendentes = $pdo->query("SELECT COUNT(*) FROM pagamentos WHERE status = 'pendente'")->fetchColumn() ?? 0;
    $totalCancelados = $pdo->query("SELECT COUNT(*) FROM pagamentos WHERE status = 'cancelado'")->fetchColumn() ?? 0;
    $valorTotal = $pdo->query("SELECT SUM(valor) FROM pagamentos WHERE status = 'confirmado'")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// ===== LISTAR PAGAMENTOS =====
$pagamentos = [];
try {
    $pagamentos = $pdo->query("
        SELECT p.*, a.nome as aluno_nome, e.nome as emolumento_nome 
        FROM pagamentos p
        LEFT JOIN alunos a ON p.aluno_id = a.id
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        ORDER BY p.created_at DESC
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
    
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    
    .btn-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }
    
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 11px;
        border-radius: 6px;
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
    
    .stat-card.confirmados { border-left-color: #2ecc71; }
    .stat-card.pendentes { border-left-color: #f39c12; }
    .stat-card.cancelados { border-left-color: #e74c3c; }
    .stat-card.total { border-left-color: #3498db; }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        min-width: 700px;
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
    
    .status-confirmado {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-pendente {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-cancelado {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .valor-positivo {
        color: #2ecc71;
        font-weight: 600;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
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
        .table {
            font-size: 12px;
            min-width: 600px;
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
        <h1>💳 Pagamentos</h1>
        <p class="subtitle">Registro e gestão de pagamentos escolares</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="add.php" class="btn btn-primary">➕ Novo Pagamento</a>
        <a href="../index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="index.php" class="active">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card confirmados">
        <span class="icon">✅</span>
        <div class="number"><?= $totalConfirmados ?></div>
        <div class="label">Confirmados</div>
    </div>
    <div class="stat-card pendentes">
        <span class="icon">⏳</span>
        <div class="number"><?= $totalPendentes ?></div>
        <div class="label">Pendentes</div>
    </div>
    <div class="stat-card cancelados">
        <span class="icon">❌</span>
        <div class="number"><?= $totalCancelados ?></div>
        <div class="label">Cancelados</div>
    </div>
    <div class="stat-card total">
        <span class="icon">📊</span>
        <div class="number">R$ <?= number_format($valorTotal, 2, ',', '.') ?></div>
        <div class="label">Total Confirmado</div>
    </div>
</div>

<!-- Tabela -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Aluno</th>
                <th>Descrição</th>
                <th>Valor</th>
                <th>Forma</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($pagamentos) > 0): ?>
                <?php foreach($pagamentos as $p): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($p['data_pagamento'])) ?></td>
                    <td><strong><?= htmlspecialchars($p['aluno_nome'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($p['emolumento_nome'] ?? 'Pagamento') ?></td>
                    <td class="valor-positivo">R$ <?= number_format($p['valor'], 2, ',', '.') ?></td>
                    <td><?= ucfirst($p['forma_pagamento'] ?? '-') ?></td>
                    <td>
                        <span class="status-badge status-<?= $p['status'] ?>">
                            <?= ucfirst($p['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-sm" style="background: #3498db; color: #fff;">Visualizar</a>
                            <?php if ($p['status'] == 'pendente'): ?>
                            <a href="confirmar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-success">✅ Confirmar</a>
                            <?php endif; ?>
                            <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?')">Excluir</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhum pagamento registrado</h3>
                            <p>Clique em "Novo Pagamento" para começar.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../../includes/footer_escola.php'; ?>