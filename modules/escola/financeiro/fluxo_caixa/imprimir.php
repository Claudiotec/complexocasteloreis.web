<?php
// ============================================
// modules/escola/financeiro/fluxo_caixa/imprimir.php - Imprimir Fluxo de Caixa
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
        LIMIT 100
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
        LIMIT 100
    ";
    $stmt = $pdo->query($sql);
    $saidas = $stmt->fetchAll() ?: [];
    
    // 3. Contas pendentes
    $sql = "
        SELECT 
            'pendente' as tipo,
            c.data_vencimento as data,
            c.categoria,
            c.descricao,
            c.valor as valor,
            c.status,
            c.fornecedor as referencia,
            c.forma_pagamento as forma
        FROM contas c
        WHERE c.status != 'paga' OR c.status IS NULL OR c.status = ''
        ORDER BY c.data_vencimento ASC
        LIMIT 100
    ";
    $stmt = $pdo->query($sql);
    $pendentes = $stmt->fetchAll() ?: [];
    
    // Combinar e ordenar
    $movimentos = array_merge($entradas, $saidas);
    usort($movimentos, function($a, $b) {
        return strtotime($b['data']) - strtotime($a['data']);
    });
    
    // Limitar a 100 registros
    $movimentos = array_slice($movimentos, 0, 100);
    
} catch (Exception $e) {
    $entradas = [];
    $saidas = [];
    $pendentes = [];
    $movimentos = [];
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Fluxo de Caixa</title>
    <style>
        /* ===== ESTILOS DO RELATÓRIO ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #f0f0f0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        
        .relatorio-container {
            max-width: 210mm;
            width: 100%;
            background: white;
            padding: 20px 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border: 1px solid #ccc;
        }
        
        /* ===== CABEÇALHO ===== */
        .header {
            text-align: center;
            border-bottom: 3px double #1a2332;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 22px;
            color: #1a2332;
            letter-spacing: 2px;
            margin: 0;
        }
        
        .header .subtitulo {
            font-size: 14px;
            color: #555;
            margin: 3px 0;
            letter-spacing: 1px;
        }
        
        .header .info-escola {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .header .nif {
            border: 1px solid #1a2332;
            padding: 2px 15px;
            display: inline-block;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .header .data-relatorio {
            font-size: 11px;
            color: #999;
            margin-top: 8px;
        }
        
        /* ===== RESUMO ===== */
        .resumo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .resumo-item {
            border: 1px solid #1a2332;
            padding: 10px;
            text-align: center;
            background: #fafafa;
        }
        
        .resumo-item .numero {
            font-size: 20px;
            font-weight: 700;
        }
        
        .resumo-item .numero.verde { color: #27ae60; }
        .resumo-item .numero.vermelho { color: #e74c3c; }
        .resumo-item .numero.laranja { color: #f39c12; }
        .resumo-item .numero.azul { color: #1a2332; }
        
        .resumo-item .label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        /* ===== TABELAS ===== */
        .tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 15px;
            border: 1px solid #1a2332;
        }
        
        .tabela th {
            background: #1a2332;
            color: white;
            padding: 6px 10px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #1a2332;
        }
        
        .tabela td {
            padding: 5px 10px;
            border: 1px solid #1a2332;
            color: #333;
        }
        
        .tabela .text-right { text-align: right; }
        .tabela .text-center { text-align: center; }
        
        .tabela .entrada { color: #27ae60; font-weight: 600; }
        .tabela .saida { color: #e74c3c; font-weight: 600; }
        .tabela .pendente { color: #f39c12; font-weight: 600; }
        
        .tabela tr:nth-child(even) { background: #f9f9f9; }
        
        .tabela .destaque-total {
            font-weight: 700;
            background: #f8fafc;
        }
        
        .tabela .destaque-total td {
            border-top: 2px solid #1a2332;
            padding: 8px 10px;
        }
        
        /* ===== SEÇÃO ===== */
        .secao-titulo {
            font-size: 14px;
            font-weight: 700;
            color: #1a2332;
            margin: 15px 0 8px;
            padding-bottom: 5px;
            border-bottom: 2px solid #1a2332;
            display: flex;
            justify-content: space-between;
        }
        
        .secao-titulo .contador {
            font-size: 11px;
            color: #888;
            font-weight: 400;
        }
        
        /* ===== RESUMO FINAL ===== */
        .resumo-final {
            border: 2px solid #1a2332;
            padding: 15px;
            margin-top: 15px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
            background: #fafafa;
        }
        
        .resumo-final .item {
            text-align: center;
        }
        
        .resumo-final .item .label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .resumo-final .item .valor {
            font-size: 16px;
            font-weight: 700;
        }
        
        .resumo-final .item .valor.verde { color: #27ae60; }
        .resumo-final .item .valor.vermelho { color: #e74c3c; }
        .resumo-final .item .valor.laranja { color: #f39c12; }
        .resumo-final .item .valor.azul { color: #1a2332; }
        
        /* ===== RODAPÉ ===== */
        .footer {
            border-top: 1px solid #1a2332;
            padding-top: 10px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #999;
        }
        
        /* ===== IMPRESSÃO ===== */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .relatorio-container {
                box-shadow: none;
                border: none;
                padding: 15px 20px;
            }
            .no-print {
                display: none !important;
            }
            .tabela th {
                background: #1a2332 !important;
                color: white !important;
            }
            .tabela tr:nth-child(even) {
                background: #f9f9f9 !important;
            }
            .resumo-item {
                background: #fafafa !important;
            }
            .resumo-final {
                background: #fafafa !important;
            }
        }
        
        @media (max-width: 600px) {
            .resumo-grid {
                grid-template-columns: 1fr 1fr;
            }
            .resumo-final {
                grid-template-columns: 1fr 1fr;
            }
            .relatorio-container {
                padding: 10px;
            }
            .tabela {
                font-size: 9px;
            }
            .tabela th, .tabela td {
                padding: 3px 5px;
            }
        }
    </style>
</head>
<body>

<div class="relatorio-container">

    <!-- ===== CABEÇALHO ===== -->
    <div class="header">
        <h1><?= htmlspecialchars($nome_escola) ?></h1>
        <div class="subtitulo">RELATÓRIO DE FLUXO DE CAIXA</div>
        <div class="info-escola">
            <?= htmlspecialchars($endereco) ?>
            <?php if ($contacto): ?> | 📞 <?= htmlspecialchars($contacto) ?><?php endif; ?>
            <?php if ($email): ?> | ✉ <?= htmlspecialchars($email) ?><?php endif; ?>
        </div>
        <?php if ($nif): ?>
        <div class="nif">NIF: <?= htmlspecialchars($nif) ?></div>
        <?php endif; ?>
        <div class="data-relatorio">
            Período: <?= date('d/m/Y') ?> | Gerado em: <?= date('d/m/Y H:i:s') ?>
        </div>
    </div>

    <!-- ===== RESUMO ===== -->
    <div class="resumo-grid">
        <div class="resumo-item">
            <div class="numero verde">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></div>
            <div class="label">📥 Total Entradas</div>
        </div>
        <div class="resumo-item">
            <div class="numero vermelho">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></div>
            <div class="label">📤 Total Saídas</div>
        </div>
        <div class="resumo-item">
            <div class="numero laranja">R$ <?= number_format($totalPendentes, 2, ',', '.') ?></div>
            <div class="label">⏳ Total Pendente</div>
        </div>
        <div class="resumo-item">
            <div class="numero azul">R$ <?= number_format($saldoAtual, 2, ',', '.') ?></div>
            <div class="label">💰 Saldo Atual</div>
        </div>
    </div>

    <!-- ===== MOVIMENTOS ===== -->
    <div class="secao-titulo">
        📋 Movimentos
        <span class="contador"><?= count($movimentos) ?> registro(s)</span>
    </div>

    <table class="tabela">
        <thead>
            <tr>
                <th style="width:10%;">Data</th>
                <th style="width:8%;">Tipo</th>
                <th style="width:12%;">Categoria</th>
                <th style="width:25%;">Descrição</th>
                <th style="width:15%;">Referência</th>
                <th style="width:8%;">Forma</th>
                <th style="width:12%;">Valor</th>
                <th style="width:10%;">Status</th>
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
                    <td class="text-center">
                        <?php if (!empty($m['forma'])): ?>
                            <?= ucfirst($m['forma']) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="text-right <?= $m['tipo'] == 'entrada' ? 'entrada' : 'saida' ?>">
                        <?= $m['tipo'] == 'entrada' ? '+' : '-' ?> R$ <?= number_format($m['valor'], 2, ',', '.') ?>
                    </td>
                    <td class="text-center">
                        <span style="font-weight:600;color:#27ae60;">
                            <?= $m['tipo'] == 'entrada' ? 'CONFIRMADO' : 'PAGO' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding:30px;color:#999;">
                        Nenhum movimento registrado
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ===== CONTAS PENDENTES ===== -->
    <?php if (count($pendentes) > 0): ?>
    <div class="secao-titulo">
        ⏳ Contas Pendentes
        <span class="contador"><?= count($pendentes) ?> registro(s)</span>
    </div>

    <table class="tabela">
        <thead>
            <tr>
                <th style="width:12%;">Vencimento</th>
                <th style="width:15%;">Categoria</th>
                <th style="width:28%;">Descrição</th>
                <th style="width:20%;">Fornecedor</th>
                <th style="width:15%;">Valor</th>
                <th style="width:10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($pendentes as $p): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($p['data'])) ?></td>
                <td><?= htmlspecialchars($p['categoria'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['descricao'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['referencia'] ?? '-') ?></td>
                <td class="text-right pendente">R$ <?= number_format($p['valor'], 2, ',', '.') ?></td>
                <td class="text-center">
                    <span style="font-weight:600;color:#f39c12;">⏳ PENDENTE</span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- ===== RESUMO FINAL ===== -->
    <div class="resumo-final">
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

    <!-- ===== RODAPÉ ===== -->
    <div class="footer">
        <div>
            <strong><?= htmlspecialchars($nome_escola) ?></strong>
            <?php if ($nif): ?> | NIF: <?= htmlspecialchars($nif) ?><?php endif; ?>
        </div>
        <div>
            Documento gerado em <?= date('d/m/Y H:i:s') ?>
        </div>
    </div>

</div>

<!-- ===== BOTÕES ===== -->
<div class="no-print" style="text-align:center;margin-top:20px;">
    <button onclick="window.print()" style="padding:12px 40px;background:#1a2332;color:white;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;">
        🖨️ IMPRIMIR RELATÓRIO
    </button>
    <button onclick="window.close()" style="padding:12px 40px;background:#e74c3c;color:white;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;margin-left:10px;">
        ✕ FECHAR
    </button>
    <button onclick="window.location.href='index.php'" style="padding:12px 40px;background:#c9a84c;color:#1a2332;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;margin-left:10px;">
        ← VOLTAR
    </button>
</div>

</body>
</html>