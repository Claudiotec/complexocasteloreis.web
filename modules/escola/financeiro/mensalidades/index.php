<?php
// ============================================
// modules/escola/financeiro/mensalidades/index.php - Mensalidades
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
$totalMensalidades = 0;
$totalPagas = 0;
$totalPendentes = 0;
$totalAtrasadas = 0;
$valorTotal = 0;

try {
    $totalMensalidades = $pdo->query("SELECT COUNT(*) FROM mensalidades")->fetchColumn() ?? 0;
    $totalPagas = $pdo->query("SELECT COUNT(*) FROM mensalidades WHERE status = 'pago'")->fetchColumn() ?? 0;
    $totalPendentes = $pdo->query("SELECT COUNT(*) FROM mensalidades WHERE status = 'pendente'")->fetchColumn() ?? 0;
    $totalAtrasadas = $pdo->query("SELECT COUNT(*) FROM mensalidades WHERE status = 'atrasado'")->fetchColumn() ?? 0;
    $valorTotal = $pdo->query("SELECT SUM(valor) FROM mensalidades WHERE status = 'pago'")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// ===== LISTAR MENSALIDADES COM EMOLUMENTO E STATUS DE PAGAMENTO =====
$mensalidades = [];
try {
    $mensalidades = $pdo->query("
        SELECT 
            m.*,
            a.nome as aluno_nome,
            a.TURMA as turma,
            a.Classe as classe,
            e.nome as emolumento_nome,
            e.valor as emolumento_valor,
            -- Verifica se existe pagamento confirmado
            (SELECT COUNT(*) FROM pagamentos p 
             WHERE p.aluno_id = m.aluno_id 
             AND p.emolumento_id = m.emolumento_id
             AND p.status = 'confirmado'
             AND (
                 p.mes_referencia = CONCAT(
                     ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                     '/',
                     m.ano
                 )
                 OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
             )
            ) as tem_pagamento,
            -- Busca a data do pagamento
            (SELECT p.data_pagamento FROM pagamentos p 
             WHERE p.aluno_id = m.aluno_id 
             AND p.emolumento_id = m.emolumento_id
             AND p.status = 'confirmado'
             AND (
                 p.mes_referencia = CONCAT(
                     ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                     '/',
                     m.ano
                 )
                 OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
             )
             ORDER BY p.data_pagamento DESC
             LIMIT 1
            ) as data_pagamento_real,
            -- Busca a forma de pagamento
            (SELECT p.forma_pagamento FROM pagamentos p 
             WHERE p.aluno_id = m.aluno_id 
             AND p.emolumento_id = m.emolumento_id
             AND p.status = 'confirmado'
             AND (
                 p.mes_referencia = CONCAT(
                     ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                     '/',
                     m.ano
                 )
                 OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
             )
             ORDER BY p.data_pagamento DESC
             LIMIT 1
            ) as forma_pagamento_real,
            -- Busca o valor pago
            (SELECT SUM(p.valor) FROM pagamentos p 
             WHERE p.aluno_id = m.aluno_id 
             AND p.emolumento_id = m.emolumento_id
             AND p.status = 'confirmado'
             AND (
                 p.mes_referencia = CONCAT(
                     ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                     '/',
                     m.ano
                 )
                 OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
             )
            ) as valor_efetivamente_pago
        FROM mensalidades m
        LEFT JOIN alunos a ON m.aluno_id = a.id
        LEFT JOIN emolumentos e ON m.emolumento_id = e.id
        ORDER BY m.data_vencimento DESC, m.id DESC
    ")->fetchAll();
} catch (Exception $e) {
    $mensalidades = [];
}

// ===== FUNÇÃO PARA FORMATAR MOEDA =====
function formatarMoeda($valor) {
    return 'Kz ' . number_format($valor, 2, ',', '.');
}

// ===== FUNÇÃO PARA STATUS =====
function getStatusBadge($row) {
    $tem_pagamento = ($row['tem_pagamento'] ?? 0) > 0;
    $status = $row['status'] ?? 'pendente';
    
    if ($status == 'cancelado') {
        return '<span class="status-badge status-cancelado">❌ Cancelado</span>';
    }
    
    if ($tem_pagamento) {
        $forma = $row['forma_pagamento_real'] ?? '';
        $label = '';
        if ($forma) {
            $formas = [
                'dinheiro' => '💰 Dinheiro',
                'transferencia' => '🏦 Transferência',
                'pix' => '📱 PIX',
                'cartao' => '💳 Cartão',
                'cartao_credito' => '💳 Cartão Crédito',
                'cartao_debito' => '💳 Cartão Débito',
                'boleto' => '📄 Boleto'
            ];
            $label = ' - ' . ($formas[$forma] ?? $forma);
        }
        $data_pag = $row['data_pagamento_real'] ? date('d/m/Y', strtotime($row['data_pagamento_real'])) : '';
        return '<span class="status-badge status-pago" title="Pago em: ' . $data_pag . '">✅ Pago' . $label . '</span>';
    }
    
    $hoje = time();
    $data_venc = strtotime($row['data_vencimento']);
    if ($data_venc < $hoje) {
        return '<span class="status-badge status-atrasado">⚠️ Atrasado</span>';
    }
    
    return '<span class="status-badge status-pendente">⏳ Pendente</span>';
}

// ===== FUNÇÃO PARA FORMA DE PAGAMENTO =====
function getFormaPagamento($forma) {
    $formas = [
        'dinheiro' => '💰 Dinheiro',
        'transferencia' => '🏦 Transferência',
        'pix' => '📱 PIX',
        'cartao' => '💳 Cartão',
        'cartao_credito' => '💳 Cartão Crédito',
        'cartao_debito' => '💳 Cartão Débito',
        'boleto' => '📄 Boleto'
    ];
    return $formas[$forma] ?? ucfirst($forma);
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
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 11px;
        border-radius: 6px;
    }
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-pago {
        background: #d1fae5;
        color: #065f46;
        cursor: default;
    }
    
    .btn-pago:hover {
        background: #d1fae5;
        transform: none;
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
    
    .stat-card.pagas { border-left-color: #2ecc71; }
    .stat-card.pendentes { border-left-color: #f39c12; }
    .stat-card.atrasadas { border-left-color: #e74c3c; }
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
        min-width: 1000px;
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
    
    .table tr.pago {
        background: #f0fdf4;
    }
    
    .table tr.pago:hover {
        background: #dcfce7;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-pago {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-pendente {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-atrasado {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .status-cancelado {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .valor-positivo {
        color: #065f46;
        font-weight: 600;
    }
    
    .descricao-cell {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #4a5568;
        font-size: 13px;
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
    
    .btn-group-pagar {
        display: flex;
        gap: 3px;
        flex-wrap: wrap;
    }
    
    .btn-group-pagar .btn {
        padding: 2px 8px;
        font-size: 10px;
        border-radius: 4px;
    }
    
    .emolumento-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        background: #e0f2fe;
        color: #0369a1;
    }
    
    .pago-info {
        font-size: 11px;
        color: #065f46;
        display: block;
        margin-top: 2px;
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
            min-width: 700px;
        }
        .table th, .table td {
            padding: 8px 10px;
        }
        .descricao-cell {
            max-width: 100px;
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
        <h1>📅 Mensalidades</h1>
        <p class="subtitle">Gestão de mensalidades escolares</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="gerar.php" class="btn btn-primary">📅 Gerar Mensalidades</a>
        <a href="relatorios_mensalidades.php" class="btn btn-info" style="color: white;">📅 Relatório de Mensalidades</a>
        <a href="../pagamentos/" class="btn btn-success" style="color: white;">💳 Pagamentos</a>
        <a href="../index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="../pagamentos/">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="index.php" class="active">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card pagas">
        <span class="icon">✅</span>
        <div class="number"><?= $totalPagas ?></div>
        <div class="label">Pagas</div>
    </div>
    <div class="stat-card pendentes">
        <span class="icon">⏳</span>
        <div class="number"><?= $totalPendentes ?></div>
        <div class="label">Pendentes</div>
    </div>
    <div class="stat-card atrasadas">
        <span class="icon">⚠️</span>
        <div class="number"><?= $totalAtrasadas ?></div>
        <div class="label">Atrasadas</div>
    </div>
    <div class="stat-card total">
        <span class="icon">📊</span>
        <div class="number"><?= formatarMoeda($valorTotal) ?></div>
        <div class="label">Total Arrecadado</div>
    </div>
</div>

<!-- Tabela -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Aluno</th>
                <th>Emolumento</th>
                <th>Mês/Ano</th>
                <th>Valor</th>
                <th>Vencimento</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($mensalidades) > 0): ?>
                <?php foreach($mensalidades as $m): 
                    $tem_pagamento = ($m['tem_pagamento'] ?? 0) > 0;
                    $classe_linha = $tem_pagamento ? 'pago' : '';
                    $valor_pago = $m['valor_efetivamente_pago'] ?? 0;
                ?>
                <tr class="<?= $classe_linha ?>">
                    <td>
                        <strong><?= htmlspecialchars($m['aluno_nome'] ?? '-') ?></strong>
                        <br>
                        <small class="text-muted"><?= htmlspecialchars($m['turma'] ?? '') ?> - <?= htmlspecialchars($m['classe'] ?? '') ?>ª</small>
                    </td>
                    <td>
                        <?php if(!empty($m['emolumento_nome'])): ?>
                            <span class="emolumento-badge">
                                <?= htmlspecialchars($m['emolumento_nome']) ?>
                            </span>
                        <?php else: ?>
                            <span class="emolumento-badge" style="background:#f1f5f9;color:#4a5568;">
                                Sem emolumento
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= str_pad($m['mes'], 2, '0', STR_PAD_LEFT) ?>/<?= $m['ano'] ?></td>
                    <td class="valor-positivo"><?= formatarMoeda($m['valor']) ?></td>
                    <td><?= $m['data_vencimento'] ? date('d/m/Y', strtotime($m['data_vencimento'])) : '-' ?></td>
                    <td>
                        <?= getStatusBadge($m) ?>
                        <?php if ($tem_pagamento && !empty($m['forma_pagamento_real'])): ?>
                            <div class="pago-info">
                                <?= getFormaPagamento($m['forma_pagamento_real']) ?>
                                <?php if ($valor_pago > 0 && $valor_pago != $m['valor']): ?>
                                    <br><small>Pago: <?= formatarMoeda($valor_pago) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-actions">
                            <?php if (!$tem_pagamento && $m['status'] != 'cancelado'): ?>
                                <div class="btn-group-pagar">
                                    <a href="pagar_auto.php?id=<?= $m['id'] ?>&forma=dinheiro" class="btn btn-sm btn-success" onclick="return confirm('Confirmar pagamento de <?= formatarMoeda($m['valor']) ?> para <?= htmlspecialchars($m['aluno_nome'] ?? '') ?>?')">💰</a>
                                    <a href="pagar_auto.php?id=<?= $m['id'] ?>&forma=pix" class="btn btn-sm" style="background: #00b894; color: white;" onclick="return confirm('Confirmar pagamento via PIX de <?= formatarMoeda($m['valor']) ?>?')">📱</a>
                                    <a href="pagar_auto.php?id=<?= $m['id'] ?>&forma=transferencia" class="btn btn-sm" style="background: #0984e3; color: white;" onclick="return confirm('Confirmar pagamento via Transferência de <?= formatarMoeda($m['valor']) ?>?')">🏦</a>
                                </div>
                            <?php else: ?>
                                <span class="btn btn-sm btn-pago">✅ Pago</span>
                            <?php endif; ?>
                            <a href="view.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-info">👁️</a>
                            <a href="editar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-warning">✏️</a>
                            <a href="delete.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta mensalidade?')">🗑️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhuma mensalidade cadastrada</h3>
                            <p>Clique em "Gerar Mensalidades" para começar.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../../includes/footer_escola.php'; ?>