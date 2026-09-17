<?php
// ============================================
// modules/escola/financeiro/contas/view.php - Visualizar Conta
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

// ===== VERIFICAR ID =====
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?erro=ID não informado');
    exit;
}

$id = intval($_GET['id']);

// ===== BUSCAR DADOS DA CONTA =====
try {
    $stmt = $pdo->prepare("SELECT * FROM contas WHERE id = ?");
    $stmt->execute([$id]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conta) {
        header('Location: index.php?erro=Conta não encontrada');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: index.php?erro=Erro ao buscar conta: ' . $e->getMessage());
    exit;
}

// ===== FUNÇÃO PARA FORMATAR MOEDA =====
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// ===== FUNÇÃO PARA STATUS =====
function getStatusBadge($status) {
    if (empty($status)) $status = 'pendente';
    
    $statuses = [
        'paga' => '<span class="status-badge status-paga">✅ Paga</span>',
        'pendente' => '<span class="status-badge status-pendente">⏳ Pendente</span>',
        'vencida' => '<span class="status-badge status-vencida">⚠️ Vencida</span>',
        'cancelada' => '<span class="status-badge status-cancelada">❌ Cancelada</span>'
    ];
    return $statuses[$status] ?? '<span class="status-badge status-pendente">⏳ Pendente</span>';
}

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
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .card {
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        margin-bottom: 25px;
    }
    
    .card-header {
        padding: 18px 25px;
        border-bottom: 2px solid #eef2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .card-header h3 {
        font-size: 16px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .card-body {
        padding: 25px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .info-item {
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 12px;
    }
    
    .info-item .label {
        font-size: 12px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    
    .info-item .value {
        font-size: 16px;
        font-weight: 500;
        color: #1a2332;
        margin-top: 3px;
    }
    
    .info-item .value.destaque {
        color: #c9a84c;
        font-weight: 700;
        font-size: 18px;
    }
    
    .info-item .value.positivo {
        color: #2ecc71;
    }
    
    .info-item .value.negativo {
        color: #e74c3c;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }
    
    .status-paga {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-pendente {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-vencida {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .status-cancelada {
        background: #f1f5f9;
        color: #4a5568;
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
    
    .table-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 10px;
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
        .info-grid {
            grid-template-columns: 1fr;
        }
        .card-body {
            padding: 15px;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>👁️ Visualizar Conta</h1>
        <p class="subtitle">Detalhes da conta #<?= str_pad($conta['id'], 6, '0', STR_PAD_LEFT) ?></p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="edit.php?id=<?= $conta['id'] ?>" class="btn btn-warning">✏️ Editar</a>
        <?php if ($conta['status'] != 'paga' && $conta['status'] != 'cancelada'): ?>
        <a href="pagar.php?id=<?= $conta['id'] ?>" class="btn btn-success">💰 Pagar</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="../pagamentos/">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="index.php" class="active">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<!-- Detalhes da Conta -->
<div class="card">
    <div class="card-header">
        <h3>📋 Informações da Conta</h3>
        <div>
            <?= getStatusBadge($conta['status']) ?>
        </div>
    </div>
    <div class="card-body">
        <div class="info-grid">
            <div class="info-item">
                <div class="label">📝 Descrição</div>
                <div class="value"><?= htmlspecialchars($conta['descricao']) ?></div>
            </div>
            
            <div class="info-item">
                <div class="label">📂 Categoria</div>
                <div class="value"><?= htmlspecialchars($conta['categoria'] ?? '-') ?></div>
            </div>
            
            <div class="info-item">
                <div class="label">💵 Valor</div>
                <div class="value destaque"><?= formatarMoeda($conta['valor']) ?></div>
            </div>
            
            <div class="info-item">
                <div class="label">📊 Tipo</div>
                <div class="value <?= ($conta['tipo'] ?? 'despesa') == 'receita' ? 'positivo' : 'negativo' ?>">
                    <?= ($conta['tipo'] ?? 'despesa') == 'receita' ? '📈 Receita' : '💰 Despesa' ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="label">📅 Data de Vencimento</div>
                <div class="value"><?= $conta['data_vencimento'] ? date('d/m/Y', strtotime($conta['data_vencimento'])) : '-' ?></div>
            </div>
            
            <div class="info-item">
                <div class="label">📅 Data de Pagamento</div>
                <div class="value"><?= $conta['data_pagamento'] ? date('d/m/Y', strtotime($conta['data_pagamento'])) : '<span style="color:#94a3b8;">Não pago</span>' ?></div>
            </div>
            
            <div class="info-item">
                <div class="label">💳 Forma de Pagamento</div>
                <div class="value"><?= $conta['forma_pagamento'] ? ucfirst($conta['forma_pagamento']) : '-' ?></div>
            </div>
            
            <div class="info-item">
                <div class="label">🏢 Fornecedor / Credor</div>
                <div class="value"><?= htmlspecialchars($conta['fornecedor'] ?? '-') ?></div>
            </div>
            
            <?php if (!empty($conta['observacoes'])): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <div class="label">📝 Observações</div>
                <div class="value"><?= nl2br(htmlspecialchars($conta['observacoes'])) ?></div>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <div class="label">📅 Criado em</div>
                <div class="value"><?= $conta['created_at'] ? date('d/m/Y H:i', strtotime($conta['created_at'])) : '-' ?></div>
            </div>
            
            <div class="info-item">
                <div class="label">📅 Última atualização</div>
                <div class="value"><?= $conta['updated_at'] ? date('d/m/Y H:i', strtotime($conta['updated_at'])) : '-' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Ações Rápidas -->
<div class="card">
    <div class="card-header">
        <h3>⚡ Ações Rápidas</h3>
    </div>
    <div class="card-body">
        <div class="table-actions">
            <a href="edit.php?id=<?= $conta['id'] ?>" class="btn btn-warning">✏️ Editar Conta</a>
            
            <?php if ($conta['status'] != 'paga' && $conta['status'] != 'cancelada'): ?>
            <a href="pagar.php?id=<?= $conta['id'] ?>" class="btn btn-success">💰 Pagar Conta</a>
            <?php endif; ?>
            
            <?php if ($conta['status'] == 'pendente' || $conta['status'] == 'vencida'): ?>
            <a href="pagar.php?id=<?= $conta['id'] ?>" class="btn btn-success">💰 Pagar</a>
            <?php endif; ?>
            
            <?php if ($conta['status'] != 'cancelada'): ?>
            <a href="delete.php?id=<?= $conta['id'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta conta?')">🗑️ Excluir</a>
            <?php endif; ?>
            
            <a href="index.php" class="btn btn-secondary">← Voltar para lista</a>
        </div>
    </div>
</div>

<?php include '../../includes/footer_escola.php'; ?>