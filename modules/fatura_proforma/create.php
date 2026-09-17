<?php
require_once '../../config/database.php';

// Buscar dados da empresa
$empresa = getEmpresa();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $data_emissao = $_POST['data_emissao'];
    $data_validade = $_POST['data_validade'];
    $observacoes = $_POST['observacoes'];
    
    // Gerar número da fatura
    $numero = 'PF-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Inserir fatura
    $stmt = $pdo->prepare("INSERT INTO faturas_proforma (numero, cliente_id, data_emissao, data_validade, observacoes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$numero, $cliente_id, $data_emissao, $data_validade, $observacoes]);
    $fatura_id = $pdo->lastInsertId();
    
    // Inserir itens
    $subtotal = 0;
    foreach ($_POST['produto_id'] as $key => $produto_id) {
        if ($produto_id) {
            $quantidade = $_POST['quantidade'][$key];
            $preco_unitario = $_POST['preco_unitario'][$key];
            $desconto = $_POST['desconto'][$key] ?? 0;
            $total_item = ($quantidade * $preco_unitario) - $desconto;
            
            $stmt = $pdo->prepare("INSERT INTO fatura_proforma_itens (fatura_id, produto_id, quantidade, preco_unitario, desconto, total) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fatura_id, $produto_id, $quantidade, $preco_unitario, $desconto, $total_item]);
            
            $subtotal += $total_item;
        }
    }
    
    // Atualizar total da fatura
    $stmt = $pdo->prepare("UPDATE faturas_proforma SET subtotal = ?, total = ? WHERE id = ?");
    $stmt->execute([$subtotal, $subtotal, $fatura_id]);
    
    header("Location: index.php?success=1");
    exit;
}

// Buscar clientes e produtos
$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();
$produtos = $pdo->query("SELECT * FROM produtos ORDER BY nome")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Fatura Proforma - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        /* ===== ESTILOS DO FORMULÁRIO ===== */
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.06);
            padding: 40px 45px;
            position: relative;
            overflow: hidden;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #2c3e50, #3498db, #2ecc71);
        }

        .form-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f2f5;
            flex-wrap: wrap;
            gap: 15px;
        }

        .form-header .title-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .form-header .title-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .form-header h2 {
            font-size: 24px;
            color: #1e293b;
            margin: 0;
            font-weight: 700;
        }

        .form-header .subtitle {
            color: #94a3b8;
            font-size: 14px;
            margin: 0;
        }

        .form-header .doc-number-preview {
            background: #f8fafc;
            padding: 8px 20px;
            border-radius: 8px;
            border: 1px dashed #cbd5e1;
            text-align: center;
        }

        .form-header .doc-number-preview small {
            display: block;
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-header .doc-number-preview strong {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 700;
        }

        /* ===== CAMPOS DO FORMULÁRIO ===== */
        .form-group-modern {
            margin-bottom: 22px;
        }

        .form-group-modern label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .form-group-modern label .required {
            color: #ef4444;
            margin-left: 3px;
        }

        .form-group-modern select,
        .form-group-modern input,
        .form-group-modern textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            color: #1e293b;
            background: #fafbfc;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group-modern select:focus,
        .form-group-modern input:focus,
        .form-group-modern textarea:focus {
            border-color: #3498db;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        .form-group-modern select:hover,
        .form-group-modern input:hover {
            border-color: #94a3b8;
        }

        .form-group-modern textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row-modern {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* ===== SEÇÃO DE ITENS ===== */
        .items-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 25px 25px 15px 25px;
            margin: 10px 0 25px 0;
            border: 2px solid #f1f5f9;
        }

        .items-section .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
        }

        .items-section .section-title .badge {
            background: #3498db;
            color: white;
            font-size: 12px;
            padding: 2px 12px;
            border-radius: 20px;
            font-weight: 600;
        }

        .item-row {
            background: white;
            border-radius: 10px;
            padding: 20px 20px 10px 20px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            position: relative;
            transition: all 0.3s ease;
        }

        .item-row:hover {
            border-color: #94a3b8;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .item-row .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .item-row .item-number {
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
        }

        .btn-remove-item {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 14px;
            padding: 4px 12px;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .btn-remove-item:hover {
            background: #fef2f2;
        }

        .item-row .form-group-modern {
            margin-bottom: 15px;
        }

        /* ===== BOTÕES ===== */
        .btn-add-item {
            background: #f1f5f9;
            color: #475569;
            padding: 10px 24px;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            text-align: center;
        }

        .btn-add-item:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
            color: #1e293b;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #f0f2f5;
            flex-wrap: wrap;
        }

        .btn-save {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 14px 40px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.3);
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #64748b;
            padding: 14px 35px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        /* ===== OBSERVAÇÕES ===== */
        .observations-section {
            margin-top: 5px;
        }

        /* ===== RESPONSIVO ===== */
        @media (max-width: 768px) {
            .form-card {
                padding: 25px 20px;
            }
            
            .form-row-modern {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .form-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .form-header .doc-number-preview {
                width: 100%;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-save,
            .btn-cancel {
                width: 100%;
                justify-content: center;
            }

            .items-section {
                padding: 15px 15px 5px 15px;
            }
        }

        /* ===== ANIMAÇÃO ===== */
        .item-row {
            animation: fadeInUp 0.3s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== ESTILO PARA SELECT COM VALOR SELECIONADO ===== */
        .form-group-modern select option {
            padding: 8px;
        }

        /* ===== TOOLTIP ===== */
        .field-hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* ===== PREVIEW DO NÚMERO ===== */
        .preview-number {
            font-size: 13px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="form-container">
        <div class="form-card">
            <!-- HEADER -->
            <div class="form-header">
                <div class="title-section">
                    <div class="title-icon">📄</div>
                    <div>
                        <h2>Nova Fatura Proforma</h2>
                        <p class="subtitle">Preencha os dados para emitir uma nova fatura</p>
                    </div>
                </div>
                <div class="doc-number-preview">
                    <small>Nº da Fatura</small>
                    <strong>PF-<?= date('Y') ?>-XXXX</strong>
                </div>
            </div>

            <!-- FORMULÁRIO -->
            <form method="POST" id="formFatura">
                <!-- DADOS DO CLIENTE -->
                <div class="form-row-modern">
                    <div class="form-group-modern">
                        <label>Cliente <span class="required">*</span></label>
                        <select name="cliente_id" required>
                            <option value="">Selecione um cliente</option>
                            <?php foreach($clientes as $cliente): ?>
                            <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group-modern">
                            <label>Data de Emissão <span class="required">*</span></label>
                            <input type="date" name="data_emissao" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group-modern">
                            <label>Data de Validade <span class="required">*</span></label>
                            <input type="date" name="data_validade" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- ITENS DA FATURA -->
                <div class="items-section">
                    <div class="section-title">
                        🛒 Itens da Fatura
                        <span class="badge" id="contadorItens">0</span>
                    </div>
                    
                    <div id="itens-container">
                        <div class="item-row" data-index="0">
                            <div class="item-header">
                                <span class="item-number">Item #1</span>
                            </div>
                            <div class="form-row-modern">
                                <div class="form-group-modern">
                                    <label>Produto <span class="required">*</span></label>
                                    <select name="produto_id[]" required>
                                        <option value="">Selecione um produto</option>
                                        <?php foreach($produtos as $produto): ?>
                                        <option value="<?= $produto['id'] ?>" data-preco="<?= $produto['preco_venda'] ?>">
                                            <?= htmlspecialchars($produto['nome']) ?> (R$ <?= number_format($produto['preco_venda'], 2, ',', '.') ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group-modern">
                                    <label>Quantidade <span class="required">*</span></label>
                                    <input type="number" name="quantidade[]" min="1" value="1" required>
                                </div>
                            </div>
                            <div class="form-row-modern">
                                <div class="form-group-modern">
                                    <label>Preço Unitário <span class="required">*</span></label>
                                    <input type="number" step="0.01" name="preco_unitario[]" placeholder="0,00" required>
                                </div>
                                <div class="form-group-modern">
                                    <label>Desconto</label>
                                    <input type="number" step="0.01" name="desconto[]" value="0" placeholder="0,00">
                                    <div class="field-hint">Desconto em R$</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" onclick="adicionarItem()" class="btn-add-item">
                        ➕ Adicionar Item
                    </button>
                </div>

                <!-- OBSERVAÇÕES -->
                <div class="observations-section">
                    <div class="form-group-modern">
                        <label>Observações</label>
                        <textarea name="observacoes" placeholder="Informações adicionais sobre a fatura..."></textarea>
                    </div>
                </div>

                <!-- AÇÕES -->
                <div class="form-actions">
                    <button type="submit" class="btn-save">💾 Salvar Fatura</button>
                    <a href="index.php" class="btn-cancel">✕ Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ===== CONTADOR DE ITENS =====
        function atualizarContador() {
            const total = document.querySelectorAll('.item-row').length;
            document.getElementById('contadorItens').textContent = total;
        }

        // ===== ADICIONAR ITEM =====
        function adicionarItem() {
            const container = document.getElementById('itens-container');
            const totalItens = document.querySelectorAll('.item-row').length;
            const novoIndex = totalItens;
            
            const novoItem = document.createElement('div');
            novoItem.className = 'item-row';
            novoItem.dataset.index = novoIndex;
            
            // Clonar select de produtos
            const selectOriginal = document.querySelector('select[name="produto_id[]"]');
            const selectHtml = selectOriginal.outerHTML;
            
            novoItem.innerHTML = `
                <div class="item-header">
                    <span class="item-number">Item #${novoIndex + 1}</span>
                    <button type="button" onclick="removerItem(this)" class="btn-remove-item">✕ Remover</button>
                </div>
                <div class="form-row-modern">
                    <div class="form-group-modern">
                        <label>Produto <span class="required">*</span></label>
                        ${selectHtml.replace('produto_id[]', 'produto_id[]')}
                    </div>
                    <div class="form-group-modern">
                        <label>Quantidade <span class="required">*</span></label>
                        <input type="number" name="quantidade[]" min="1" value="1" required>
                    </div>
                </div>
                <div class="form-row-modern">
                    <div class="form-group-modern">
                        <label>Preço Unitário <span class="required">*</span></label>
                        <input type="number" step="0.01" name="preco_unitario[]" placeholder="0,00" required>
                    </div>
                    <div class="form-group-modern">
                        <label>Desconto</label>
                        <input type="number" step="0.01" name="desconto[]" value="0" placeholder="0,00">
                        <div class="field-hint">Desconto em R$</div>
                    </div>
                </div>
            `;
            
            container.appendChild(novoItem);
            atualizarContador();
            
            // Adicionar evento para preencher preço automaticamente
            const novoSelect = novoItem.querySelector('select[name="produto_id[]"]');
            const novoPreco = novoItem.querySelector('input[name="preco_unitario[]"]');
            
            novoSelect.addEventListener('change', function() {
                const selected = this.options[this.selectedIndex];
                if (selected.value) {
                    const preco = selected.dataset.preco || 0;
                    novoPreco.value = preco;
                } else {
                    novoPreco.value = '';
                }
            });
        }

        // ===== REMOVER ITEM =====
        function removerItem(botao) {
            const item = botao.closest('.item-row');
            const total = document.querySelectorAll('.item-row').length;
            
            if (total <= 1) {
                alert('É necessário ter pelo menos um item na fatura.');
                return;
            }
            
            if (confirm('Remover este item?')) {
                item.remove();
                // Renumerar itens
                document.querySelectorAll('.item-row').forEach((el, index) => {
                    const numero = el.querySelector('.item-number');
                    if (numero) numero.textContent = `Item #${index + 1}`;
                });
                atualizarContador();
            }
        }

        // ===== PREENCHER PREÇO AUTOMATICAMENTE =====
        document.addEventListener('change', function(e) {
            if (e.target.matches('select[name="produto_id[]"]')) {
                const select = e.target;
                const row = select.closest('.item-row');
                const precoInput = row.querySelector('input[name="preco_unitario[]"]');
                const selected = select.options[select.selectedIndex];
                
                if (selected.value) {
                    const preco = selected.dataset.preco || 0;
                    precoInput.value = preco;
                } else {
                    precoInput.value = '';
                }
            }
        });

        // ===== ATUALIZAR CONTADOR INICIAL =====
        atualizarContador();
    </script>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>