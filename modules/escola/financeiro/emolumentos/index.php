<?php
// ============================================
// modules/escola/financeiro/emolumentos/index.php - Emolumentos (COMPLETO)
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
$emolumentos = [];
try {
    $emolumentos = $pdo->query("SELECT * FROM emolumentos ORDER BY created_at DESC")->fetchAll();
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
        font-size: 13px;
        min-width: 900px;
    }
    
    .table th {
        background: #f8fafc;
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        font-size: 13px;
    }
    
    .table tr:hover {
        background: #fafbfc;
    }
    
    .status-badge {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
    }
    
    .status-ativo {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-inativo {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .valor {
        font-weight: 600;
        color: #1a2332;
    }
    
    .multa {
        color: #e74c3c;
        font-weight: 600;
    }
    
    .classe-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        background: #e0f2fe;
        color: #0369a1;
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
    
    .mes-ref {
        font-size: 12px;
        color: #4a5568;
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
        .table {
            font-size: 11px;
            min-width: 700px;
        }
        .table th, .table td {
            padding: 6px 8px;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📋 Emolumentos</h1>
        <p class="subtitle">Cadastro de taxas, mensalidades e emolumentos escolares</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="add.php" class="btn btn-primary">➕ Novo Emolumento</a>
        <a href="../index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="../pagamentos/">💳 Pagamentos</a>
    <a href="index.php" class="active">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<!-- Tabela -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Classe</th>
                <th>Descrição</th>
                <th>Mês Ref.</th>
                <th>Valor</th>
                <th>Multa</th>
                <th>Prazo</th>
                <th>Início Multa</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($emolumentos) > 0): ?>
                <?php foreach($emolumentos as $e): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($e['nome']) ?></strong></td>
                    <td><span class="classe-badge"><?= htmlspecialchars($e['classe'] ?? 'Outro') ?></span></td>
                    <td><?= htmlspecialchars(substr($e['descricao'] ?? '', 0, 25)) ?></td>
                    <td class="mes-ref"><?= str_pad($e['mes_referencia'] ?? '-', 2, '0', STR_PAD_LEFT) ?>/<?= $e['ano_referencia'] ?? '-' ?></td>
                    <td class="valor">R$ <?= number_format($e['valor'], 2, ',', '.') ?></td>
                    <td class="multa">
                        <?php if ($e['multa_tipo'] == 'percentual'): ?>
                            <?= $e['multa_valor'] ?>%
                        <?php else: ?>
                            R$ <?= number_format($e['multa_valor'], 2, ',', '.') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $e['prazo_dias'] ?> dias</td>
                    <td class="mes-ref"><?= str_pad($e['mes_inicio_multa'] ?? '-', 2, '0', STR_PAD_LEFT) ?>/<?= $e['ano_inicio_multa'] ?? '-' ?></td>
                    <td>
                        <span class="status-badge status-<?= $e['status'] ?? 'ativo' ?>">
                            <?= ucfirst($e['status'] ?? 'ativo') ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="edit.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                            <a href="delete.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir este emolumento?')">Excluir</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhum emolumento cadastrado</h3>
                            <p>Clique em "Novo Emolumento" para começar.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../../includes/footer_escola.php'; ?>