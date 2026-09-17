<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT c.*, m.* FROM comprovantes_venda c JOIN movimentacoes_caixa m ON c.movimento_id = m.id WHERE c.id = ?");
$stmt->execute([$id]);
$comprovante = $stmt->fetch();

if (!$comprovante) {
    die("Comprovante não encontrado");
}

$empresa = getEmpresa();

// Buscar produto
$stmt = $pdo->prepare("SELECT p.nome, p.preco_venda, m.quantidade FROM movimentacoes_caixa m JOIN produtos p ON m.produto_id = p.id WHERE m.id = ?");
$stmt->execute([$comprovante['movimento_id']]);
$produto = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante <?= $comprovante['numero_comprovante'] ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .comprovante {
            background: white;
            border: 2px solid #1a2332;
            border-radius: 8px;
            padding: 30px;
            max-width: 650px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .header { text-align: center; border-bottom: 2px solid #1a2332; padding-bottom: 15px; margin-bottom: 15px; }
        .header .empresa { font-size: 24px; font-weight: 800; color: #1a2332; }
        .header .info { font-size: 12px; color: #64748b; }
        .header .titulo { font-size: 14px; font-weight: 700; color: #1a2332; margin-top: 5px; }
        .header .numero { font-size: 12px; color: #64748b; }
        .header .licenca { font-size: 11px; color: #64748b; background: #f8fafc; padding: 4px 10px; border-radius: 4px; display: inline-block; margin-top: 5px; }
        .body { margin-bottom: 15px; }
        .body table { width: 100%; font-size: 13px; border-collapse: collapse; }
        .body table td { padding: 4px 0; }
        .body table .label { width: 40%; font-weight: 600; }
        .detalhes { border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 10px 0; margin-bottom: 15px; }
        .detalhes table { width: 100%; font-size: 13px; border-collapse: collapse; }
        .detalhes table th { padding: 6px 0; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .detalhes table td { padding: 6px 0; }
        .detalhes table td.center { text-align: center; }
        .detalhes table td.right { text-align: right; }
        .total { text-align: right; margin-bottom: 15px; }
        .total .valor { font-size: 24px; font-weight: 800; color: #2ecc71; }
        .footer { text-align: center; border-top: 2px solid #1a2332; padding-top: 15px; }
        .footer .qr { background: #f8fafc; padding: 10px 20px; border-radius: 4px; display: inline-block; font-size: 11px; color: #64748b; }
        .footer .chave { font-size: 10px; color: #cbd5e1; margin-top: 4px; }
        .btn-print {
            display: block; margin: 20px auto; padding: 12px 30px;
            background: #1a2332; color: white; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 600; cursor: pointer;
            transition: all 0.3s;
        }
        .btn-print:hover { background: #2c3e50; transform: scale(1.02); }
        @media print {
            body { background: white; padding: 0; }
            .comprovante { border: none; box-shadow: none; }
            .btn-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div>
        <div class="comprovante">
            <div class="header">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div style="text-align: left;">
                        <div class="empresa"><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest Web') ?></div>
                        <div class="info"><?= htmlspecialchars($empresa['razao_social'] ?? '') ?></div>
                        <div class="info">NIF: <?= htmlspecialchars($empresa['cnpj'] ?? '00.000.000/0001-00') ?></div>
                        <div class="info"><?= htmlspecialchars($empresa['endereco'] ?? '') ?></div>
                    </div>
                    <div style="text-align: right;">
                        <div class="titulo">COMPROVANTE DE VENDA</div>
                        <div class="numero">Nº: <strong><?= $comprovante['numero_comprovante'] ?></strong></div>
                        <div class="licenca">✅ Documento emitido por sistema licenciado pela AGT</div>
                    </div>
                </div>
            </div>
            
            <div class="body">
                <table>
                    <tr><td class="label">Cliente:</td><td><?= htmlspecialchars($comprovante['cliente_nome']) ?></td></tr>
                    <tr><td class="label">NIF:</td><td><?= htmlspecialchars($comprovante['cliente_nif']) ?></td></tr>
                    <tr><td class="label">Data:</td><td><?= date('d/m/Y H:i:s', strtotime($comprovante['data_emissao'])) ?></td></tr>
                    <tr><td class="label">Forma de Pagamento:</td><td><?= ucfirst(str_replace('_', ' ', $comprovante['forma_pagamento'])) ?></td></tr>
                </table>
            </div>
            
            <div class="detalhes">
                <table>
                    <thead>
                        <tr><th>Produto</th><th style="text-align: center;">Qtd</th><th style="text-align: right;">Preço Unit.</th><th style="text-align: right;">Total</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= htmlspecialchars($produto['nome'] ?? '') ?></td>
                            <td class="center"><?= $produto['quantidade'] ?? 1 ?></td>
                            <td class="right">R$ <?= number_format($produto['preco_venda'] ?? 0, 2, ',', '.') ?></td>
                            <td class="right">R$ <?= number_format($comprovante['valor_total'], 2, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="total">
                <div>Total a Pagar:</div>
                <div class="valor">R$ <?= number_format($comprovante['valor_total'], 2, ',', '.') ?></div>
            </div>
            
            <div class="footer">
                <div class="qr">🔲 QR Code para validação</div>
                <div class="chave">Chave: <?= substr(md5($comprovante['numero_comprovante'] . $comprovante['valor_total']), 0, 16) ?></div>
                <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">
                    Documento válido para efeitos fiscais - AGT Angola
                </div>
            </div>
        </div>
        
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir Comprovante</button>
        <button class="btn-print" style="background: #e2e8f0; color: #1a2332; margin-top: 10px;" onclick="window.close()">✕ Fechar</button>
    </div>
    
    <script>
        // Carregar automaticamente a impressão
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>