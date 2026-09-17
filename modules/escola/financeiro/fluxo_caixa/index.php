<?php
// ============================================
// modules/escola/financeiro/fluxo_caixa/index.php - Fluxo de Caixa
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
$totalEntradas = 0;
$totalSaidas = 0;
$totalPendentes = 0;
$saldoAtual = 0;
$movimentos = [];
$entradas = [];
$saidas = [];
$pendentes = [];

// Buscar configurações da escola
$nome_escola = 'Sistema Escolar';
$endereco = '';
$contacto = '';
$email = '';
$nif = '';

$config_file = '../../../../config_escola.json';
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
    $nome_escola = $config['nome'] ?? 'Sistema Escolar';
    $endereco = $config['endereco'] ?? '';
    $contacto = $config['contacto'] ?? '';
    $email = $config['email'] ?? '';
    $nif = $config['nif'] ?? '';
}

try {
    // ===== TOTAL DE ENTRADAS (Pagamentos confirmados) =====
    $sql = "SELECT SUM(p.valor) FROM pagamentos p WHERE p.status IN ('confirmado', 'Pago', 'pago')";
    $stmt = $pdo->query($sql);
    $totalEntradas = $stmt->fetchColumn() ?? 0;
    
    // ===== TOTAL DE SAÍDAS (Contas pagas) =====
    $sql = "SELECT SUM(c.valor) FROM contas c WHERE c.status = 'paga'";
    $stmt = $pdo->query($sql);
    $totalSaidas = $stmt->fetchColumn() ?? 0;
    
    // ===== TOTAL PENDENTE (Contas não pagas) =====
    $sql = "SELECT SUM(c.valor) FROM contas c WHERE c.status != 'paga' OR c.status IS NULL OR c.status = ''";
    $stmt = $pdo->query($sql);
    $totalPendentes = $stmt->fetchColumn() ?? 0;
    
    $saldoAtual = $totalEntradas - $totalSaidas;
    
    // ===== MOVIMENTOS =====
    // 1. Pagamentos (entradas)
    $sql = "
        SELECT 
            'entrada' as tipo,
            p.data_pagamento as data,
            'Pagamento' as categoria,
            CONCAT('Pagamento de ', IFNULL(e.nome, 'mensalidade')) as descricao,
            p.valor as valor,
            'confirmado' as status,
            a.nome as referencia,
            p.forma_pagamento as forma
        FROM pagamentos p
        LEFT JOIN alunos a ON p.aluno_id = a.id
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        WHERE p.status IN ('confirmado', 'Pago', 'pago')
        ORDER BY p.data_pagamento DESC
        LIMIT 50
    ";
    $stmt = $pdo->query($sql);
    $entradas = $stmt->fetchAll() ?: [];
    
    // 2. Contas pagas (saídas)
    $sql = "
        SELECT 
            'saida' as tipo,
            c.data_pagamento as data,
            c.categoria,
            c.descricao,
            c.valor as valor,
            c.status,
            c.fornecedor as referencia,
            c.forma_pagamento as forma
        FROM contas c
        WHERE c.status = 'paga'
        ORDER BY c.data_pagamento DESC
        LIMIT 50
    ";
    $stmt = $pdo->query($sql);
    $saidas = $stmt->fetchAll() ?: [];
    
    // 3. Contas pendentes (CORRIGIDO)
    try {
        // Verificar se a tabela contas existe
        $tableExists = $pdo->query("SHOW TABLES LIKE 'contas'")->rowCount() > 0;
        
        if ($tableExists) {
            $sql = "
                SELECT 
                    'pendente' as tipo,
                    c.data_vencimento as data,
                    c.categoria,
                    c.descricao,
                    c.valor as valor,
                    c.status,
                    c.fornecedor as referencia,
                    c.forma_pagamento as forma,
                    c.data_pagamento
                FROM contas c
                WHERE c.status != 'paga' 
                   OR c.status IS NULL 
                   OR c.status = ''
                ORDER BY c.data_vencimento ASC
                LIMIT 50
            ";
            $stmt = $pdo->query($sql);
            $pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Garantir que é um array
            if (!is_array($pendentes)) {
                $pendentes = [];
            }
        } else {
            $pendentes = [];
        }
    } catch (Exception $e) {
        $pendentes = [];
        error_log("Erro ao buscar contas pendentes: " . $e->getMessage());
    }
    
    // Verificação final
    if (!is_array($pendentes)) {
        $pendentes = [];
    }
    
    // Combinar e ordenar
    $movimentos = array_merge($entradas, $saidas);
    usort($movimentos, function($a, $b) {
        return strtotime($b['data']) - strtotime($a['data']);
    });
    
    // Limitar a 50 registros
    $movimentos = array_slice($movimentos, 0, 50);
    
} catch (Exception $e) {
    $entradas = [];
    $saidas = [];
    $pendentes = [];
    $movimentos = [];
}

// ===== INCLUIR HEADER =====
include '../../includes/header_escola.php';
?>

<style>
    /* ===== ESTILOS GERAIS ===== */
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
    .stat-card.pendentes { border-left-color: #f39c12; }
    
    .saldo-destaque {
        font-size: 28px;
        font-weight: 800;
    }
    
    .saldo-positivo { color: #2ecc71; }
    .saldo-negativo { color: #e74c3c; }
    
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
    
    .badge-status {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
    }
    
    .badge-confirmado {
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-paga {
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-pendente-status {
        background: #fef3c7;
        color: #92400e;
    }
    
    .valor-positivo {
        color: #2ecc71;
        font-weight: 600;
    }
    
    .valor-negativo {
        color: #e74c3c;
        font-weight: 600;
    }
    
    .valor-pendente {
        color: #f39c12;
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
    
    .text-muted {
        color: #94a3b8;
        font-size: 12px;
    }
    
    .forma-pagamento {
        font-size: 11px;
        color: #94a3b8;
        background: #f1f5f9;
        padding: 2px 8px;
        border-radius: 10px;
        display: inline-block;
    }
    
    /* ===== IMPRESSÃO ESTILO AGT ===== */
    @media print {
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        body {
            background: white !important;
            padding: 0 !important;
            margin: 0 !important;
            font-family: 'Times New Roman', Times, serif !important;
        }
        
        .no-print { display: none !important; }
        .menu-financeiro { display: none !important; }
        .page-header .btn { display: none !important; }
        
        .print-header {
            display: block !important;
            text-align: center;
            border-bottom: 3px double #1a2332;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .print-header h1 {
            font-size: 22px;
            margin: 0;
            color: #1a2332;
            letter-spacing: 2px;
        }
        
        .print-header .sub {
            font-size: 14px;
            color: #555;
            margin: 3px 0;
            letter-spacing: 1px;
        }
        
        .print-header .info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .print-header .nif {
            border: 1px solid #1a2332;
            padding: 2px 15px;
            display: inline-block;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .print-footer {
            display: block !important;
            text-align: center;
            border-top: 1px solid #1a2332;
            padding-top: 10px;
            margin-top: 20px;
            font-size: 10px;
            color: #666;
        }
        
        .stats-grid-print {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-card-print {
            border: 1px solid #1a2332;
            padding: 10px;
            text-align: center;
        }
        
        .stat-card-print .number {
            font-size: 20px;
            font-weight: 700;
        }
        
        .stat-card-print .label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card-print .number.verde { color: #27ae60; }
        .stat-card-print .number.vermelho { color: #e74c3c; }
        .stat-card-print .number.azul { color: #1a2332; }
        .stat-card-print .number.laranja { color: #f39c12; }
        
        .table-print {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            border: 1px solid #1a2332;
            margin-bottom: 15px;
        }
        
        .table-print th {
            background: #1a2332 !important;
            color: white !important;
            padding: 6px 10px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #1a2332;
        }
        
        .table-print td {
            padding: 5px 10px;
            border: 1px solid #1a2332;
            color: #333;
        }
        
        .table-print .text-right { text-align: right; }
        .table-print .text-center { text-align: center; }
        
        .table-print .entrada { color: #27ae60; font-weight: 600; }
        .table-print .saida { color: #e74c3c; font-weight: 600; }
        .table-print .pendente { color: #f39c12; font-weight: 600; }
        
        .table-print tr:nth-child(even) { background: #f9f9f9 !important; }
        
        .resumo-print {
            border: 2px solid #1a2332;
            padding: 15px;
            margin-top: 15px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
        }
        
        .resumo-print .item {
            text-align: center;
        }
        
        .resumo-print .item .label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        
        .resumo-print .item .valor {
            font-size: 16px;
            font-weight: 700;
        }
        
        .resumo-print .item .valor.verde { color: #27ae60; }
        .resumo-print .item .valor.vermelho { color: #e74c3c; }
        .resumo-print .item .valor.laranja { color: #f39c12; }
        .resumo-print .item .valor.azul { color: #1a2332; }
    }
    
    .print-header { display: none; }
    .print-footer { display: none; }
    .stats-grid-print { display: none; }
    .table-print { display: none; }
    .resumo-print { display: none; }
    
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
        .saldo-destaque {
            font-size: 24px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ===== CABEÇALHO PARA IMPRESSÃO ===== -->
<div class="print-header">
    <h1><?= htmlspecialchars($nome_escola) ?></h1>
    <div class="sub">RELATÓRIO DE FLUXO DE CAIXA</div>
    <div class="info">
        <?= htmlspecialchars($endereco) ?>
        <?php if ($contacto): ?> | 📞 <?= htmlspecialchars($contacto) ?><?php endif; ?>
        <?php if ($email): ?> | ✉ <?= htmlspecialchars($email) ?><?php endif; ?>
    </div>
    <?php if ($nif): ?>
    <div class="nif">NIF: <?= htmlspecialchars($nif) ?></div>
    <?php endif; ?>
    <div style="font-size:11px;color:#999;margin-top:8px;">
        Período: <?= date('d/m/Y') ?> | Gerado em: <?= date('d/m/Y H:i:s') ?>
    </div>
</div>

<div class="page-header">
    <div>
        <h1>💵 Fluxo de Caixa</h1>
        <p class="subtitle">Visão geral das entradas e saídas financeiras</p>
    </div>

    <!-- Botão de Impressão no index.php -->
    <div class="no-print">
        <a href="imprimir.php" target="_blank" class="btn btn-primary">🖨️ Imprimir Relatório</a>
        <a href="../index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro no-print">
    <a href="../index.php">📊 Dashboard</a>
    <a href="../pagamentos/">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="index.php" class="active">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<!-- ===== VERSÃO PARA IMPRESSÃO ===== -->
<div class="stats-grid-print">
    <div class="stat-card-print">
        <div class="number verde">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></div>
        <div class="label">📥 Total Entradas</div>
    </div>
    <div class="stat-card-print">
        <div class="number vermelho">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></div>
        <div class="label">📤 Total Saídas</div>
    </div>
    <div class="stat-card-print">
        <div class="number laranja">R$ <?= number_format($totalPendentes, 2, ',', '.') ?></div>
        <div class="label">⏳ Total Pendente</div>
    </div>
    <div class="stat-card-print">
        <div class="number azul">R$ <?= number_format($saldoAtual, 2, ',', '.') ?></div>
        <div class="label">💰 Saldo Atual</div>
    </div>
</div>

<!-- ===== TABELA PARA IMPRESSÃO ===== -->
<table class="table-print">
    <thead>
        <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>Categoria</th>
            <th>Descrição</th>
            <th>Referência</th>
            <th>Valor</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($movimentos) > 0): ?>
            <?php foreach($movimentos as $m): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($m['data'])) ?></td>
                <td class="text-center">
                    <span style="font-weight:600;color:<?= $m['tipo'] == 'entrada' ? '#27ae60' : '#e74c3c' ?>;">
                        <?= $m['tipo'] == 'entrada' ? 'ENTRADA' : 'SAÍDA' ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($m['categoria'] ?? '-') ?></td>
                <td><?= htmlspecialchars($m['descricao'] ?? '-') ?></td>
                <td><?= htmlspecialchars($m['referencia'] ?? '-') ?></td>
                <td class="text-right <?= $m['tipo'] == 'entrada' ? 'entrada' : 'saida' ?>">
                    <?= $m['tipo'] == 'entrada' ? '+' : '-' ?> R$ <?= number_format($m['valor'], 2, ',', '.') ?>
                </td>
                <td class="text-center">
                    <span style="font-weight:600;color:#27ae60;">PAGO</span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="text-center" style="padding:30px;color:#999;">
                    Nenhum movimento registrado
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- ===== CONTAS PENDENTES PARA IMPRESSÃO CORRIGIDA ===== -->
<?php if (!empty($pendentes) && is_array($pendentes)): ?>
<div style="margin-top:15px;">
    <h3 style="font-size:14px;font-weight:700;color:#1a2332;border-bottom:2px solid #1a2332;padding-bottom:5px;margin-bottom:10px;">
        ⏳ CONTAS PENDENTES
    </h3>
    <table class="table-print">
        <thead>
            <tr>
                <th>Vencimento</th>
                <th>Categoria</th>
                <th>Descrição</th>
                <th>Fornecedor</th>
                <th class="text-right">Valor</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($pendentes as $p): ?>
            <?php if (is_array($p) && isset($p['valor'])): ?>
            <tr>
                <td>
                    <?php 
                    if (isset($p['data']) && !empty($p['data'])) {
                        echo date('d/m/Y', strtotime($p['data']));
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td><?= htmlspecialchars($p['categoria'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['descricao'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['referencia'] ?? '-') ?></td>
                <td class="text-right pendente">
                    R$ <?= number_format(floatval($p['valor'] ?? 0), 2, ',', '.') ?>
                </td>
                <td class="text-center">
                    <span style="font-weight:600;color:#f39c12;">⏳ <?= htmlspecialchars($p['status'] ?? 'PENDENTE') ?></span>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ===== RESUMO PARA IMPRESSÃO ===== -->
<div class="resumo-print">
    <div class="item">
        <div class="label">Total Entradas</div>
        <div class="valor verde">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></div>
    </div>
    <div class="item">
        <div class="label">Total Saídas</div>
        <div class="valor vermelho">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></div>
    </div>
    <div class="item">
        <div class="label">Total Pendente</div>
        <div class="valor laranja">R$ <?= number_format($totalPendentes, 2, ',', '.') ?></div>
    </div>
    <div class="item">
        <div class="label">Saldo Atual</div>
        <div class="valor azul">R$ <?= number_format($saldoAtual, 2, ',', '.') ?></div>
    </div>
</div>

<!-- ===== RODAPÉ PARA IMPRESSÃO ===== -->
<div class="print-footer">
    <p><?= htmlspecialchars($nome_escola) ?> - Relatório de Fluxo de Caixa</p>
    <p>Documento gerado em <?= date('d/m/Y H:i:s') ?></p>
</div>

<!-- ===== VERSÃO PARA VISUALIZAÇÃO ===== -->
<!-- Stats -->
<div class="stats-grid no-print">
    <div class="stat-card entradas">
        <span class="icon">📥</span>
        <div class="number">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></div>
        <div class="label">Total Entradas</div>
    </div>
    <div class="stat-card saidas">
        <span class="icon">📤</span>
        <div class="number">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></div>
        <div class="label">Total Saídas (Pagas)</div>
    </div>
    <div class="stat-card pendentes">
        <span class="icon">⏳</span>
        <div class="number">R$ <?= number_format($totalPendentes, 2, ',', '.') ?></div>
        <div class="label">Total Pendente</div>
    </div>
    <div class="stat-card saldo">
        <span class="icon">💰</span>
        <div class="number saldo-destaque <?= $saldoAtual >= 0 ? 'saldo-positivo' : 'saldo-negativo' ?>">
            R$ <?= number_format($saldoAtual, 2, ',', '.') ?>
        </div>
        <div class="label">Saldo Atual</div>
    </div>
</div>

<!-- Tabela de Movimentos - Visualização -->
<div class="table-responsive no-print">
    <table class="table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Tipo</th>
                <th>Categoria</th>
                <th>Descrição</th>
                <th>Referência</th>
                <th>Forma</th>
                <th>Valor</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($movimentos) > 0): ?>
                <?php foreach($movimentos as $m): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($m['data'])) ?></td>
                    <td>
                        <span class="badge-tipo badge-<?= $m['tipo'] ?>">
                            <?= $m['tipo'] == 'entrada' ? '📥 Entrada' : '📤 Saída' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($m['categoria'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($m['descricao'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($m['referencia'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($m['forma'])): ?>
                            <span class="forma-pagamento"><?= ucfirst($m['forma']) ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="<?= $m['tipo'] == 'entrada' ? 'valor-positivo' : 'valor-negativo' ?>">
                        <?= $m['tipo'] == 'entrada' ? '+' : '-' ?> R$ <?= number_format($m['valor'], 2, ',', '.') ?>
                    </td>
                    <td>
                        <?php if ($m['tipo'] == 'entrada'): ?>
                            <span class="badge-status badge-confirmado">✅ Confirmado</span>
                        <?php else: ?>
                            <span class="badge-status badge-paga">✅ Paga</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhum movimento registrado</h3>
                            <p>Registre pagamentos ou contas para visualizar o fluxo de caixa.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Contas Pendentes - Visualização CORRIGIDA -->
<?php if (!empty($pendentes) && is_array($pendentes)): ?>
<div style="margin-top:20px;" class="no-print">
    <h3 style="font-size:16px;font-weight:700;color:#1a2332;margin-bottom:10px;">⏳ Contas Pendentes</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Vencimento</th>
                    <th>Categoria</th>
                    <th>Descrição</th>
                    <th>Fornecedor</th>
                    <th>Valor</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pendentes as $p): ?>
                <?php if (is_array($p) && isset($p['valor'])): ?>
                <tr>
                    <td>
                        <?php 
                        if (isset($p['data']) && !empty($p['data'])) {
                            echo date('d/m/Y', strtotime($p['data']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($p['categoria'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['descricao'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['referencia'] ?? '-') ?></td>
                    <td class="valor-pendente">
                        R$ <?= number_format(floatval($p['valor'] ?? 0), 2, ',', '.') ?>
                    </td>
                    <td>
                        <span class="badge-status badge-pendente-status">
                            <?= htmlspecialchars($p['status'] ?? 'Pendente') ?>
                        </span>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<!-- Mensagem quando não há contas pendentes -->
<div style="margin-top:20px;" class="no-print">
    <div style="background:#f8fafc;border-radius:12px;padding:20px;text-align:center;border:1px solid #e2e8f0;">
        <span style="font-size:24px;">✅</span>
        <h3 style="color:#4a5568;margin:5px 0;">Nenhuma conta pendente</h3>
        <p style="color:#94a3b8;font-size:14px;">Todas as contas estão pagas.</p>
    </div>
</div>
<?php endif; ?>

<!-- Resumo - Visualização -->
<div style="background:#f8fafc;border-radius:12px;padding:15px 20px;border:1px solid #e2e8f0;margin-top:20px;" class="no-print">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:20px;">
        <div style="text-align:center;">
            <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;font-weight:600;">Total Entradas</div>
            <div style="font-size:18px;font-weight:700;color:#27ae60;">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></div>
            <div class="text-muted"><?= is_array($entradas) ? count($entradas) : 0 ?> pagamento(s)</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;font-weight:600;">Total Saídas</div>
            <div style="font-size:18px;font-weight:700;color:#e74c3c;">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></div>
            <div class="text-muted"><?= is_array($saidas) ? count($saidas) : 0 ?> conta(s) paga(s)</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;font-weight:600;">Total Pendente</div>
            <div style="font-size:18px;font-weight:700;color:#f39c12;">R$ <?= number_format($totalPendentes, 2, ',', '.') ?></div>
            <div class="text-muted"><?= is_array($pendentes) ? count($pendentes) : 0 ?> conta(s) pendente(s)</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;font-weight:600;">Saldo</div>
            <div style="font-size:18px;font-weight:700;color:#1a2332;">R$ <?= number_format($saldoAtual, 2, ',', '.') ?></div>
            <div class="text-muted"><?= count($movimentos) ?> movimento(s)</div>
        </div>
    </div>
</div>

<?php include '../../includes/footer_escola.php'; ?>