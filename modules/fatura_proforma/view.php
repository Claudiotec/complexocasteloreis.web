<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

// Buscar dados da fatura
$stmt = $pdo->prepare("SELECT * FROM faturas_proforma WHERE id = ?");
$stmt->execute([$id]);
$fatura = $stmt->fetch();

if (!$fatura) {
    header("Location: index.php");
    exit;
}

// Buscar cliente
$stmtCli = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmtCli->execute([$fatura['cliente_id']]);
$cliente = $stmtCli->fetch();

// Buscar itens
$stmtItens = $pdo->prepare("SELECT fi.*, p.nome as produto_nome, p.codigo FROM fatura_proforma_itens fi JOIN produtos p ON fi.produto_id = p.id WHERE fi.fatura_id = ?");
$stmtItens->execute([$id]);
$itens = $stmtItens->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura Proforma <?= $fatura['numero'] ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        /* ===== ESTILOS DA FATURA ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .invoice-wrapper {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }

        .invoice-container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            padding: 50px 60px;
            position: relative;
            overflow: hidden;
        }

        /* Borda decorativa superior */
        .invoice-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #2c3e50, #3498db, #2ecc71);
        }

        /* ===== HEADER ===== */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f0f2f5;
        }

        .invoice-brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .invoice-brand-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            font-weight: bold;
        }

        .invoice-brand h1 {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            letter-spacing: -0.5px;
        }

        .invoice-brand h1 span {
            color: #3498db;
        }

        .invoice-brand small {
            display: block;
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 400;
            margin-top: 2px;
        }

        .invoice-document-info {
            text-align: right;
        }

        .invoice-document-info .doc-title {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            letter-spacing: 2px;
        }

        .invoice-document-info .doc-number {
            font-size: 18px;
            font-weight: 600;
            color: #3498db;
            background: #ebf5fb;
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 5px;
        }

        /* ===== STATUS BADGE ===== */
        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }

        .status-rascunho {
            background: #f1f2f6;
            color: #636e72;
        }

        .status-enviada {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-aprovada {
            background: #d1fae5;
            color: #059669;
        }

        .status-rejeitada {
            background: #fee2e2;
            color: #dc2626;
        }

        /* ===== DATAS ===== */
        .invoice-dates {
            display: flex;
            gap: 30px;
            margin-top: 8px;
            font-size: 14px;
            color: #4a5568;
        }

        .invoice-dates span {
            font-weight: 600;
            color: #2d3748;
        }

        /* ===== CLIENTE / EMPRESA ===== */
        .invoice-parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
            padding: 25px 30px;
            background: #f8fafc;
            border-radius: 12px;
        }

        .party-box h3 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .party-box .party-name {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        .party-box .party-detail {
            font-size: 14px;
            color: #475569;
            margin-top: 4px;
        }

        .party-box .party-detail i {
            margin-right: 8px;
            color: #94a3b8;
        }

        .party-box .party-document {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
            background: #eef2f6;
            padding: 2px 12px;
            border-radius: 12px;
            display: inline-block;
        }

        /* ===== TABELA DE ITENS ===== */
        .invoice-items {
            margin-bottom: 30px;
        }

        .invoice-items table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-items thead th {
            background: #f1f5f9;
            color: #334155;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            text-align: left;
            border-radius: 8px 8px 0 0;
        }

        .invoice-items thead th:last-child {
            text-align: right;
        }

        .invoice-items tbody td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
            color: #1e293b;
            vertical-align: middle;
        }

        .invoice-items tbody td:last-child {
            text-align: right;
            font-weight: 600;
        }

        .invoice-items tbody tr:last-child td {
            border-bottom: none;
        }

        .invoice-items .item-product {
            font-weight: 600;
            color: #0f172a;
        }

        .invoice-items .item-code {
            font-size: 13px;
            color: #94a3b8;
            display: block;
            margin-top: 2px;
        }

        .invoice-items .item-details {
            font-size: 14px;
            color: #64748b;
        }

        .invoice-items .item-discount {
            color: #dc2626;
            font-size: 13px;
        }

        /* ===== TOTAIS ===== */
        .invoice-totals {
            display: flex;
            justify-content: flex-end;
            padding-top: 25px;
            border-top: 2px solid #f1f5f9;
            margin-top: 10px;
        }

        .invoice-totals .totals-box {
            width: 280px;
        }

        .invoice-totals .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 15px;
            color: #475569;
        }

        .invoice-totals .totals-row.total {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            padding-top: 12px;
            border-top: 2px solid #2c3e50;
            margin-top: 4px;
        }

        .invoice-totals .totals-row .label {
            color: #64748b;
        }

        /* ===== OBSERVAÇÕES ===== */
        .invoice-observations {
            margin-top: 30px;
            padding: 20px 25px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 4px solid #3498db;
        }

        .invoice-observations h4 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .invoice-observations p {
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
            white-space: pre-line;
        }

        /* ===== RODAPÉ ===== */
        .invoice-footer {
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
        }

        .invoice-footer .footer-highlight {
            color: #64748b;
            font-weight: 500;
        }

        /* ===== BOTÕES DE AÇÃO ===== */
        .invoice-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .btn-print {
            background: #2c3e50;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-print:hover {
            background: #1a252f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44, 62, 80, 0.3);
        }

        .btn-back {
            background: #e2e8f0;
            color: #475569;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #cbd5e1;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-edit:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        /* ===== RESPONSIVO ===== */
        @media print {
            body {
                background: white;
            }
            .invoice-wrapper {
                margin: 0;
                padding: 20px;
            }
            .invoice-container {
                box-shadow: none;
                padding: 30px 40px;
                border-radius: 0;
            }
            .invoice-container::before {
                display: none;
            }
            .invoice-actions {
                display: none !important;
            }
            .invoice-parties {
                background: #f8fafc;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .status-badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .invoice-items thead th {
                background: #f1f5f9;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        @media (max-width: 768px) {
            .invoice-container {
                padding: 25px 20px;
            }
            .invoice-header {
                flex-direction: column;
                gap: 20px;
            }
            .invoice-document-info {
                text-align: left;
                width: 100%;
            }
            .invoice-parties {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 20px;
            }
            .invoice-totals {
                justify-content: flex-start;
            }
            .invoice-totals .totals-box {
                width: 100%;
            }
            .invoice-items table {
                font-size: 14px;
            }
            .invoice-items tbody td {
                padding: 12px;
            }
            .invoice-dates {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="invoice-wrapper">
        <!-- Botões de Ação -->
        <div class="invoice-actions">
            <a href="index.php" class="btn-back">← Voltar</a>
            <a href="javascript:window.print()" class="btn-print">🖨️ Imprimir</a>
            <a href="edit.php?id=<?= $fatura['id'] ?>" class="btn-edit">✏️ Editar</a>
        </div>

        <!-- Fatura -->
        <div class="invoice-container">
            <!-- HEADER -->
            <div class="invoice-header">
                <div class="invoice-brand">
                    <div class="invoice-brand-icon">SG</div>
                    <div>
                        <h1>SoftGest <span>Web</span></h1>
                        <small>Sistema de Gestão Empresarial</small>
                    </div>
                </div>
                <div class="invoice-document-info">
                    <div class="doc-title">FATURA PROFORMA</div>
                    <div class="doc-number"><?= $fatura['numero'] ?></div>
                    <?php
                    $statusClass = 'status-' . $fatura['status'];
                    ?>
                    <span class="status-badge <?= $statusClass ?>">
                        <?= ucfirst($fatura['status']) ?>
                    </span>
                    <div class="invoice-dates">
                        <div>📅 <span>Emissão:</span> <?= date('d/m/Y', strtotime($fatura['data_emissao'])) ?></div>
                        <div>⏳ <span>Validade:</span> <?= date('d/m/Y', strtotime($fatura['data_validade'])) ?></div>
                    </div>
                </div>
            </div>

            <!-- CLIENTE / EMPRESA -->
            <div class="invoice-parties">
                <div class="party-box">
                    <h3>📋 Cliente</h3>
                    <div class="party-name"><?= htmlspecialchars($cliente['nome'] ?? 'N/A') ?></div>
                    <div class="party-detail">📞 <?= htmlspecialchars($cliente['telefone'] ?? '') ?></div>
                    <div class="party-detail">✉️ <?= htmlspecialchars($cliente['email'] ?? '') ?></div>
                    <div class="party-detail">📍 <?= htmlspecialchars($cliente['endereco'] ?? '') ?></div>
                    <?php if ($cliente['documento']): ?>
                        <div class="party-document">📄 <?= htmlspecialchars($cliente['documento']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="party-box" style="text-align: right;">
                    <h3>🏢 Empresa</h3>
                    <div class="party-name">SoftGest Web</div>
                    <div class="party-detail">✉️ sistema@softgest.com</div>
                    <div class="party-detail">📞 (11) 99999-9999</div>
                    <div class="party-detail">📍 São Paulo - SP</div>
                    <div class="party-document">📄 00.000.000/0001-00</div>
                </div>
            </div>

            <!-- ITENS -->
            <div class="invoice-items">
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th style="text-align: right;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $subtotal = 0;
                        foreach($itens as $item): 
                            $subtotal += $item['total'];
                        ?>
                        <tr>
                            <td>
                                <div class="item-product"><?= htmlspecialchars($item['produto_nome']) ?></div>
                                <span class="item-code">Código: <?= $item['codigo'] ?></span>
                                <div class="item-details">
                                    Qtd: <?= $item['quantidade'] ?> × R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?>
                                    <?php if ($item['desconto'] > 0): ?>
                                        <span class="item-discount"> | Desconto: R$ <?= number_format($item['desconto'], 2, ',', '.') ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>R$ <?= number_format($item['total'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- TOTAIS -->
            <div class="invoice-totals">
                <div class="totals-box">
                    <div class="totals-row">
                        <span class="label">Subtotal</span>
                        <span>R$ <?= number_format($subtotal, 2, ',', '.') ?></span>
                    </div>
                    <?php if ($fatura['desconto'] > 0): ?>
                    <div class="totals-row">
                        <span class="label">Desconto</span>
                        <span style="color: #dc2626;">- R$ <?= number_format($fatura['desconto'], 2, ',', '.') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="totals-row total">
                        <span>TOTAL</span>
                        <span>R$ <?= number_format($fatura['total'], 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <!-- OBSERVAÇÕES -->
            <?php if ($fatura['observacoes']): ?>
            <div class="invoice-observations">
                <h4>📝 Observações</h4>
                <p><?= nl2br(htmlspecialchars($fatura['observacoes'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- RODAPÉ -->
            <div class="invoice-footer">
                <p>
                    <span class="footer-highlight">Este documento é uma fatura proforma e não tem valor fiscal.</span><br>
                    <span style="font-size: 12px; color: #cbd5e1;">
                        Gerado em <?= date('d/m/Y H:i:s') ?> - SoftGest Web v1.0
                    </span>
                </p>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>