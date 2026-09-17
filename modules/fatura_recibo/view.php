<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM faturas_recibo WHERE id = ?");
$stmt->execute([$id]);
$recibo = $stmt->fetch();

if (!$recibo) {
    header("Location: index.php");
    exit;
}

$stmtCli = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmtCli->execute([$recibo['cliente_id']]);
$cliente = $stmtCli->fetch();

$empresa = getEmpresa();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo <?= $recibo['numero'] ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .invoice-wrapper { max-width: 800px; margin: 40px auto; padding: 20px; }
        .invoice-container {
            background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            padding: 50px 60px; position: relative; overflow: hidden;
        }
        .invoice-container::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 6px; background: linear-gradient(90deg, #c9a84c, #f5d76e, #8b5cf6);
        }
        .invoice-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #f0f2f5;
        }
        .invoice-brand h1 { font-size: 24px; color: #2c3e50; }
        .invoice-brand h1 span { color: #c9a84c; }
        .invoice-brand small { color: #7f8c8d; }
        .invoice-document-info { text-align: right; }
        .invoice-document-info .doc-title {
            font-size: 20px; font-weight: 700; color: #2c3e50;
        }
        .invoice-document-info .doc-number {
            font-size: 16px; color: #8b5cf6; background: #f3f0ff;
            padding: 4px 16px; border-radius: 20px; display: inline-block; margin-top: 5px;
        }
        .status-badge {
            display: inline-block; padding: 3px 14px; border-radius: 20px;
            font-size: 12px; font-weight: 600; margin-top: 8px;
        }
        .status-pendente { background: #fef3c7; color: #92400e; }
        .status-pago { background: #d1fae5; color: #065f46; }
        .status-cancelado { background: #fee2e2; color: #991b1b; }
        .invoice-parties {
            display: grid; grid-template-columns: 1fr 1fr; gap: 30px;
            margin-bottom: 30px; padding: 20px; background: #f8fafc; border-radius: 12px;
        }
        .party-box h3 { font-size: 12px; text-transform: uppercase; color: #94a3b8; margin-bottom: 8px; }
        .party-box .party-name { font-size: 16px; font-weight: 700; color: #1e293b; }
        .party-box .party-detail { font-size: 14px; color: #475569; }
        .invoice-info { display: flex; gap: 30px; margin-bottom: 30px; flex-wrap: wrap; }
        .invoice-info .info-item { font-size: 14px; color: #475569; }
        .invoice-info .info-item strong { color: #1e293b; }
        .invoice-totals {
            display: flex; justify-content: flex-end; padding-top: 20px;
            border-top: 2px solid #f1f5f9; margin-top: 20px;
        }
        .invoice-totals .totals-box { width: 250px; }
        .invoice-totals .totals-row {
            display: flex; justify-content: space-between; padding: 6px 0;
            font-size: 15px; color: #475569;
        }
        .invoice-totals .totals-row.total {
            font-size: 20px; font-weight: 700; color: #0f172a;
            padding-top: 10px; border-top: 2px solid #c9a84c;
        }
        .invoice-footer {
            margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9;
            text-align: center; font-size: 13px; color: #94a3b8;
        }
        .invoice-actions {
            display: flex; gap: 12px; margin-bottom: 25px; flex-wrap: wrap;
        }
        .btn-print {
            background: #2c3e50; color: white; padding: 10px 24px;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-print:hover { background: #1a252f; }
        .btn-back {
            background: #e2e8f0; color: #475569; padding: 10px 24px;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-back:hover { background: #cbd5e1; }
        .btn-pagar {
            background: #2ecc71; color: white; padding: 10px 24px;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-pagar:hover { background: #27ae60; }
        .btn-enviar {
            background: #8b5cf6; color: white; padding: 10px 24px;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-enviar:hover { background: #7c3aed; }
        @media print {
            body { background: white; }
            .invoice-container { box-shadow: none; padding: 30px; }
            .invoice-actions { display: none !important; }
            .invoice-container::before { display: none; }
        }
        @media (max-width: 768px) {
            .invoice-container { padding: 25px 20px; }
            .invoice-header { flex-direction: column; gap: 15px; }
            .invoice-document-info { text-align: left; width: 100%; }
            .invoice-parties { grid-template-columns: 1fr; gap: 15px; }
            .invoice-info { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="invoice-wrapper">
        <div class="invoice-actions">
            <a href="index.php" class="btn-back">← Voltar</a>
            <a href="javascript:window.print()" class="btn-print">🖨️ Imprimir</a>
            <?php if ($recibo['status'] == 'pendente'): ?>
                <a href="pagar.php?id=<?= $recibo['id'] ?>" class="btn-pagar" onclick="return confirm('Marcar este recibo como pago?')">💰 Pagar</a>
            <?php endif; ?>
            <a href="enviar_recibo.php?id=<?= $recibo['id'] ?>" class="btn-enviar">📤 Enviar</a>
        </div>

        <div class="invoice-container">
            <div class="invoice-header">
                <div class="invoice-brand">
                    <h1><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest') ?> <span>Web</span></h1>
                    <small><?= htmlspecialchars($empresa['razao_social'] ?? '') ?></small>
                </div>
                <div class="invoice-document-info">
                    <div class="doc-title">RECIBO DE PAGAMENTO</div>
                    <div class="doc-number"><?= $recibo['numero'] ?></div>
                    <span class="status-badge status-<?= $recibo['status'] ?>">
                        <?= ucfirst($recibo['status']) ?>
                    </span>
                </div>
            </div>

            <div class="invoice-parties">
                <div class="party-box">
                    <h3>Cliente</h3>
                    <div class="party-name"><?= htmlspecialchars($cliente['nome'] ?? 'N/A') ?></div>
                    <div class="party-detail">📞 <?= htmlspecialchars($cliente['telefone'] ?? '') ?></div>
                    <div class="party-detail">✉️ <?= htmlspecialchars($cliente['email'] ?? '') ?></div>
                </div>
                <div class="party-box" style="text-align: right;">
                    <h3>Empresa</h3>
                    <div class="party-name"><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest Web') ?></div>
                    <div class="party-detail">CNPJ: <?= htmlspecialchars($empresa['cnpj'] ?? '') ?></div>
                    <div class="party-detail">📞 <?= htmlspecialchars($empresa['telefone'] ?? '') ?></div>
                </div>
            </div>

            <div class="invoice-info">
                <div class="info-item"><strong>Emissão:</strong> <?= date('d/m/Y', strtotime($recibo['data_emissao'])) ?></div>
                <div class="info-item"><strong>Vencimento:</strong> <?= date('d/m/Y', strtotime($recibo['data_vencimento'])) ?></div>
                <div class="info-item"><strong>Status:</strong> <?= ucfirst($recibo['status']) ?></div>
            </div>

            <div style="margin: 20px 0; padding: 30px; background: #f8fafc; border-radius: 8px; text-align: center;">
                <div style="font-size: 14px; color: #94a3b8;">Valor do Recibo</div>
                <div style="font-size: 48px; font-weight: 700; color: #c9a84c;">R$ <?= number_format($recibo['total'], 2, ',', '.') ?></div>
                <div style="font-size: 14px; color: #64748b; margin-top: 10px;">
                    Por Extenso: <?php
                        function extenso($valor) {
                            try {
                                $fmt = new NumberFormatter('pt_BR', NumberFormatter::SPELLOUT);
                                return ucfirst($fmt->format($valor)) . ' reais';
                            } catch(Exception $e) {
                                return number_format($valor, 2, ',', '.') . ' reais';
                            }
                        }
                        echo extenso($recibo['total']);
                    ?>
                </div>
            </div>

            <div class="invoice-totals">
                <div class="totals-box">
                    <div class="totals-row">
                        <span>Subtotal</span>
                        <span>R$ <?= number_format($recibo['subtotal'], 2, ',', '.') ?></span>
                    </div>
                    <?php if ($recibo['desconto'] > 0): ?>
                    <div class="totals-row">
                        <span>Desconto</span>
                        <span style="color: #dc2626;">- R$ <?= number_format($recibo['desconto'], 2, ',', '.') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="totals-row total">
                        <span>TOTAL</span>
                        <span>R$ <?= number_format($recibo['total'], 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <div class="invoice-footer">
                <p>
                    <span style="font-weight: 500; color: #475569;">Este documento é um recibo de pagamento</span><br>
                    <span style="font-size: 12px; color: #cbd5e1;">
                        <?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest Web') ?> - 
                        CNPJ: <?= htmlspecialchars($empresa['cnpj'] ?? '') ?>
                    </span><br>
                    <span style="font-size: 11px; color: #d1d5db;">
                        Gerado em <?= date('d/m/Y H:i:s') ?>
                    </span>
                </p>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>