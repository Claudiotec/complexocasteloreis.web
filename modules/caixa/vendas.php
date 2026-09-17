<?php
require_once '../../config/database.php';

$produtos = $pdo->query("SELECT * FROM produtos WHERE quantidade > 0 ORDER BY nome")->fetchAll();
$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();

$mensagem = '';
$tipoMensagem = '';
$comprovante = null;

// Taxa de IVA padrão (Angola: 14%)
$TAXA_IVA = 14;

// Gerar número de comprovante
function gerarNumeroComprovante() {
    return 'AGT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cliente_id = $_POST['cliente_id'] ?: null;
    $produto_id = $_POST['produto_id'];
    $quantidade = $_POST['quantidade'];
    $forma_pagamento = $_POST['forma_pagamento'];
    $desconto = floatval($_POST['desconto'] ?? 0);
    $taxa_iva = floatval($_POST['taxa_iva'] ?? $TAXA_IVA);
    $observacoes = $_POST['observacoes'] ?? '';
    
    // Buscar produto
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch();
    
    // Buscar cliente
    $cliente = null;
    if ($cliente_id) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $cliente = $stmt->fetch();
    }
    
    if (!$produto) {
        $mensagem = 'Produto não encontrado!';
        $tipoMensagem = 'error';
    } elseif ($quantidade > $produto['quantidade']) {
        $mensagem = 'Quantidade insuficiente em estoque! Disponível: ' . $produto['quantidade'];
        $tipoMensagem = 'error';
    } else {
        $subtotal = $quantidade * $produto['preco_venda'];
        $valor_desconto = ($desconto / 100) * $subtotal;
        $total_com_desconto = $subtotal - $valor_desconto;
        $valor_iva = ($taxa_iva / 100) * $total_com_desconto;
        $valor_total = $total_com_desconto + $valor_iva;
        
        $nif_cliente = $cliente ? ($cliente['nif_agt'] ?? $cliente['nif'] ?? '999999999') : '999999999';
        
        try {
            $pdo->beginTransaction();
            
            // Registrar movimentação no caixa
            $stmt = $pdo->prepare("INSERT INTO movimentacoes_caixa (tipo, categoria, descricao, valor, desconto, iva, taxa_iva, data_movimento, forma_pagamento, cliente_id, produto_id, quantidade, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmado')");
            $stmt->execute([
                'entrada',
                'Vendas',
                "Venda de {$quantidade}x {$produto['nome']}",
                $valor_total,
                $valor_desconto,
                $valor_iva,
                $taxa_iva,
                date('Y-m-d'),
                $forma_pagamento,
                $cliente_id,
                $produto_id,
                $quantidade
            ]);
            $movimento_id = $pdo->lastInsertId();
            
            // Atualizar estoque
            $nova_qtd = $produto['quantidade'] - $quantidade;
            $stmt = $pdo->prepare("UPDATE produtos SET quantidade = ? WHERE id = ?");
            $stmt->execute([$nova_qtd, $produto_id]);
            
            // Gerar comprovante
            $numero_comprovante = gerarNumeroComprovante();
            
            // Salvar comprovante
            $stmt = $pdo->prepare("INSERT INTO comprovantes_venda (movimento_id, numero_comprovante, cliente_id, cliente_nome, cliente_nif, valor_total, subtotal, desconto, valor_iva, taxa_iva, forma_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $movimento_id,
                $numero_comprovante,
                $cliente_id,
                $cliente ? $cliente['nome'] : 'Cliente não identificado',
                $nif_cliente,
                $valor_total,
                $subtotal,
                $valor_desconto,
                $valor_iva,
                $taxa_iva,
                $forma_pagamento
            ]);
            $comprovante_id = $pdo->lastInsertId();
            
            $pdo->commit();
            
            // Buscar comprovante para exibir
            $stmt = $pdo->prepare("SELECT * FROM comprovantes_venda WHERE id = ?");
            $stmt->execute([$comprovante_id]);
            $comprovante = $stmt->fetch();
            
            $mensagem = '✅ Venda registrada com sucesso!';
            $tipoMensagem = 'success';
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $mensagem = 'Erro ao registrar venda: ' . $e->getMessage();
            $tipoMensagem = 'error';
        }
    }
}

// Buscar empresa para o comprovante
$empresa = getEmpresa();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Venda - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .venda-container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .venda-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 30px; position: relative; overflow: hidden;
        }
        .venda-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 5px; background: linear-gradient(90deg, #2ecc71, #3498db);
        }
        .venda-card h2 { color: #1a2332; border-bottom: 2px solid #f0f2f5; padding-bottom: 15px; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #2c3e50; margin-bottom: 5px; }
        .form-group label .required { color: #e74c3c; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0;
            border-radius: 8px; font-size: 14px; transition: all 0.3s; font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #3498db; outline: none; box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-vender {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white; padding: 14px 30px; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s;
        }
        .btn-vender:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(46,204,113,0.3); }
        .preview-venda {
            background: #f8fafc; padding: 15px 20px; border-radius: 8px;
            margin: 15px 0; border-left: 4px solid #2ecc71;
        }
        .preview-venda .total { font-size: 24px; font-weight: 700; color: #2ecc71; }
        .btn-imprimir {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332; padding: 10px 20px; border: none; border-radius: 8px;
            font-weight: 600; cursor: pointer; transition: all 0.3s;
            text-decoration: none; display: inline-block;
        }
        .btn-imprimir:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,165,50,0.3); }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; gap: 0; } }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="venda-container">
        <div class="venda-card">
            <h2>🛒 Nova Venda</h2>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
            <?php endif; ?>
            
            <?php if ($comprovante): ?>
                <!-- Exibir comprovante -->
                <div style="margin: 20px 0;">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: center;">
                        <button onclick="imprimirComprovante()" class="btn-imprimir">🖨️ Imprimir Comprovante</button>
                        <a href="vendas.php" class="btn">📋 Nova Venda</a>
                        <a href="index.php" class="btn">← Voltar</a>
                    </div>
                    
                    <!-- COMPROVANTE ESTILO AGT -->
                    <div class="comprovante" id="comprovante" style="margin-top: 20px; background: white; border: 2px solid #1a2332; border-radius: 8px; padding: 30px; max-width: 650px; margin-left: auto; margin-right: auto;">
                        <!-- Cabeçalho -->
                        <div style="text-align: center; border-bottom: 2px solid #1a2332; padding-bottom: 15px; margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                                <div style="text-align: left;">
                                    <div style="font-size: 24px; font-weight: 800; color: #1a2332;"><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest Web') ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($empresa['razao_social'] ?? '') ?></div>
                                    <div style="font-size: 12px; color: #64748b;">NIF: <?= htmlspecialchars($empresa['cnpj'] ?? '00.000.000/0001-00') ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($empresa['endereco'] ?? '') ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 14px; font-weight: 700; color: #1a2332;">COMPROVANTE DE VENDA</div>
                                    <div style="font-size: 12px; color: #64748b;">Nº: <strong><?= $comprovante['numero_comprovante'] ?></strong></div>
                                    <div style="font-size: 11px; color: #94a3b8;">Documento válido para efeitos fiscais</div>
                                </div>
                            </div>
                            <div style="margin-top: 8px; background: #f8fafc; padding: 4px 10px; border-radius: 4px; display: inline-block; font-size: 11px; color: #64748b;">
                                ✅ Documento emitido por sistema licenciado pela AGT
                            </div>
                        </div>
                        
                        <!-- Corpo -->
                        <div style="margin-bottom: 15px;">
                            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 4px 0; width: 40%;"><strong>Cliente:</strong></td>
                                    <td style="padding: 4px 0;"><?= htmlspecialchars($comprovante['cliente_nome']) ?></td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0;"><strong>NIF:</strong></td>
                                    <td style="padding: 4px 0;"><?= htmlspecialchars($comprovante['cliente_nif']) ?></td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0;"><strong>Data:</strong></td>
                                    <td style="padding: 4px 0;"><?= date('d/m/Y H:i:s', strtotime($comprovante['data_emissao'])) ?></td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0;"><strong>Forma de Pagamento:</strong></td>
                                    <td style="padding: 4px 0;"><?= ucfirst(str_replace('_', ' ', $comprovante['forma_pagamento'])) ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Detalhes do Produto -->
                        <div style="border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 10px 0; margin-bottom: 15px;">
                            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <th style="padding: 6px 0; text-align: left;">Produto</th>
                                        <th style="padding: 6px 0; text-align: center;">Qtd</th>
                                        <th style="padding: 6px 0; text-align: right;">Preço Unit.</th>
                                        <th style="padding: 6px 0; text-align: right;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding: 6px 0;"><?= htmlspecialchars($produto['nome']) ?></td>
                                        <td style="padding: 6px 0; text-align: center;"><?= $quantidade ?></td>
                                        <td style="padding: 6px 0; text-align: right;">AKZ <?= number_format($produto['preco_venda'], 2, ',', '.') ?></td>
                                        <td style="padding: 6px 0; text-align: right;">AKZ <?= number_format($subtotal, 2, ',', '.') ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Totais com Desconto e IVA -->
                        <div style="text-align: right; margin-bottom: 15px;">
                            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 4px 0; width: 70%;"><strong>Subtotal:</strong></td>
                                    <td style="padding: 4px 0; text-align: right;">AKZ <?= number_format($comprovante['subtotal'], 2, ',', '.') ?></td>
                                </tr>
                                <?php if ($comprovante['desconto'] > 0): ?>
                                <tr>
                                    <td style="padding: 4px 0;"><strong>Desconto:</strong></td>
                                    <td style="padding: 4px 0; text-align: right; color: #e74c3c;">- AKZ <?= number_format($comprovante['desconto'], 2, ',', '.') ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="padding: 4px 0;"><strong>IVA (<?= $comprovante['taxa_iva'] ?>%):</strong></td>
                                    <td style="padding: 4px 0; text-align: right;">AKZ <?= number_format($comprovante['valor_iva'], 2, ',', '.') ?></td>
                                </tr>
                                <tr style="border-top: 2px solid #1a2332;">
                                    <td style="padding: 8px 0; font-size: 18px; font-weight: 800;"><strong>Total a Pagar:</strong></td>
                                    <td style="padding: 8px 0; text-align: right; font-size: 24px; font-weight: 800; color: #2ecc71;">
                                        AKZ <?= number_format($comprovante['valor_total'], 2, ',', '.') ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- QR Code e Validação -->
                        <div style="text-align: center; border-top: 2px solid #1a2332; padding-top: 15px;">
                            <div style="display: inline-block; background: #f8fafc; padding: 10px 20px; border-radius: 4px;">
                                <span style="font-size: 11px; color: #64748b;">🔲 QR Code para validação</span>
                            </div>
                            <div style="font-size: 10px; color: #94a3b8; margin-top: 8px;">
                                Documento emitido por sistema licenciado pela AGT - Angola
                            </div>
                            <div style="font-size: 10px; color: #cbd5e1; margin-top: 4px;">
                                Chave: <?= substr(md5($comprovante['numero_comprovante'] . $comprovante['valor_total']), 0, 16) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Formulário de Venda -->
                <form method="POST" id="formVenda">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Cliente (opcional)</label>
                            <select name="cliente_id" id="cliente_id" onchange="preencherNIF()">
                                <option value="">Cliente não identificado</option>
                                <?php foreach($clientes as $cliente): ?>
                                <option value="<?= $cliente['id'] ?>" data-nif="<?= htmlspecialchars($cliente['nif_agt'] ?? $cliente['nif'] ?? '') ?>">
                                    <?= htmlspecialchars($cliente['nome']) ?> 
                                    <?php if ($cliente['nif_agt'] ?? $cliente['nif'] ?? ''): ?>
                                        (NIF: <?= htmlspecialchars($cliente['nif_agt'] ?? $cliente['nif']) ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>NIF do Cliente</label>
                            <input type="text" name="nif_cliente" id="nif_cliente" placeholder="Número de Identificação Fiscal" readonly>
                            <small style="color: #94a3b8; font-size: 11px;">Preenchido automaticamente</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Forma de Pagamento <span class="required">*</span></label>
                            <select name="forma_pagamento" required>
                                <option value="dinheiro">💵 Dinheiro</option>
                                <option value="cartao_credito">💳 Cartão de Crédito</option>
                                <option value="cartao_debito">💳 Cartão de Débito</option>
                                <option value="pix">📱 PIX</option>
                                <option value="boleto">📄 Boleto</option>
                                <option value="transferencia">🏦 Transferência</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Produto <span class="required">*</span></label>
                            <select name="produto_id" id="produto_id" required onchange="atualizarPreco()">
                                <option value="">Selecione um produto</option>
                                <?php foreach($produtos as $produto): ?>
                                <option value="<?= $produto['id'] ?>" data-preco="<?= $produto['preco_venda'] ?>" data-estoque="<?= $produto['quantidade'] ?>">
                                    <?= htmlspecialchars($produto['nome']) ?> - AKZ <?= number_format($produto['preco_venda'], 2, ',', '.') ?> (<?= $produto['quantidade'] ?> un)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantidade <span class="required">*</span></label>
                            <input type="number" name="quantidade" id="quantidade" min="1" value="1" required oninput="calcularTotal()">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Desconto (%)</label>
                            <input type="number" name="desconto" id="desconto" min="0" max="100" value="0" oninput="calcularTotal()">
                        </div>
                        <div class="form-group">
                            <label>Taxa de IVA (%)</label>
                            <input type="number" name="taxa_iva" id="taxa_iva" min="0" max="100" value="<?= $TAXA_IVA ?>" oninput="calcularTotal()">
                        </div>
                    </div>
                    
                    <div class="preview-venda">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                            <div>
                                <span style="font-weight: 600; color: #1a2332;">Total da Venda</span>
                                <div style="font-size: 13px; color: #94a3b8;" id="infoProduto">Selecione um produto</div>
                                <div style="font-size: 12px; color: #94a3b8;" id="infoIva"></div>
                            </div>
                            <span class="total" id="totalVenda">AKZ 0,00</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Observações</label>
                        <textarea name="observacoes" placeholder="Informações adicionais sobre a venda..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-vender">💾 Registrar Venda e Emitir Comprovante</button>
                    <a href="index.php" class="btn">Cancelar</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function preencherNIF() {
            const select = document.getElementById('cliente_id');
            const option = select.options[select.selectedIndex];
            const nif = option.dataset.nif || '';
            document.getElementById('nif_cliente').value = nif;
        }
        
        function atualizarPreco() {
            const select = document.getElementById('produto_id');
            const option = select.options[select.selectedIndex];
            const info = document.getElementById('infoProduto');
            
            if (option.value) {
                const preco = parseFloat(option.dataset.preco) || 0;
                const estoque = option.dataset.estoque || 0;
                info.textContent = 'Preço: AKZ ' + preco.toFixed(2).replace('.', ',') + ' | Estoque: ' + estoque + ' un';
                calcularTotal();
            } else {
                info.textContent = 'Selecione um produto';
                document.getElementById('totalVenda').textContent = 'AKZ 0,00';
            }
        }
        
        function calcularTotal() {
            const select = document.getElementById('produto_id');
            const option = select.options[select.selectedIndex];
            const quantidade = parseInt(document.getElementById('quantidade').value) || 0;
            const desconto = parseFloat(document.getElementById('desconto').value) || 0;
            const taxa_iva = parseFloat(document.getElementById('taxa_iva').value) || 0;
            
            if (option.value) {
                const preco = parseFloat(option.dataset.preco) || 0;
                const subtotal = preco * quantidade;
                const valor_desconto = (desconto / 100) * subtotal;
                const total_com_desconto = subtotal - valor_desconto;
                const valor_iva = (taxa_iva / 100) * total_com_desconto;
                const total = total_com_desconto + valor_iva;
                
                document.getElementById('totalVenda').textContent = 'AKZ ' + total.toFixed(2).replace('.', ',');
                document.getElementById('infoIva').textContent = 'IVA: ' + taxa_iva + '% | Desconto: ' + desconto + '%';
            }
        }
        
        function imprimirComprovante() {
            var conteudo = document.getElementById('comprovante').innerHTML;
            var win = window.open('', '_blank');
            win.document.write(`
                <html>
                <head>
                    <title>Comprovante de Venda</title>
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body { font-family: 'Courier New', monospace; padding: 20px; background: white; }
                        .comprovante { max-width: 650px; margin: 0 auto; }
                        @media print {
                            body { padding: 0; }
                            .no-print { display: none !important; }
                        }
                    </style>
                </head>
                <body>
                    <div class="comprovante">${conteudo}</div>
                    <div style="text-align: center; margin-top: 20px;" class="no-print">
                        <button onclick="window.print()" style="padding: 10px 30px; background: #1a2332; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer;">🖨️ Imprimir</button>
                        <button onclick="window.close()" style="padding: 10px 30px; background: #e2e8f0; color: #1a2332; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; margin-left: 10px;">✕ Fechar</button>
                    </div>
                    <script>
                        // Auto imprimir
                        setTimeout(function() { window.print(); }, 500);
                    <\/script>
                </body>
                </html>
            `);
            win.document.close();
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            preencherNIF();
        });
    </script>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>