<?php
// ============================================
// modules/caixa/venda.php - Nova Venda com Fatura AGT
// ============================================

// Carregar configurações
require_once '../../config/database.php';
require_once '../../config/app_modes.php';
require_once '../../includes/agt_functions.php';

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
if (!temPermissao('Fluxo de Caixa', 'criar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== BUSCAR CLIENTES E PRODUTOS =====
$clientes = [];
$produtos = [];

try {
    $clientes = $pdo->query("SELECT id, nome, nif_agt, telefone, endereco, email FROM clientes WHERE status = 'ativo' ORDER BY nome")->fetchAll();
    $produtos = $pdo->query("SELECT id, nome, codigo, preco_venda, quantidade FROM produtos WHERE quantidade > 0 ORDER BY nome")->fetchAll();
} catch (Exception $e) {}

// ===== PROCESSAR FORMULÁRIO =====
$erro = '';
$sucesso = '';
$fatura_emitida = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cliente_id = $_POST['cliente_id'] ?? null;
    $produto_id = $_POST['produto_id'] ?? null;
    $quantidade = $_POST['quantidade'] ?? 1;
    $forma_pagamento = $_POST['forma_pagamento'] ?? 'dinheiro';
    $observacoes = $_POST['observacoes'] ?? '';
    $emitir_fatura = isset($_POST['emitir_fatura']) ? true : false;

    if (!$cliente_id || !$produto_id || $quantidade <= 0) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        try {
            // Buscar cliente e produto
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch();

            $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
            $stmt->execute([$produto_id]);
            $produto = $stmt->fetch();

            if (!$produto) {
                $erro = 'Produto não encontrado!';
            } elseif ($produto['quantidade'] < $quantidade) {
                $erro = 'Estoque insuficiente! Disponível: ' . $produto['quantidade'];
            } else {
                // Calcular valores
                $preco_unitario = $produto['preco_venda'];
                $subtotal = $preco_unitario * $quantidade;
                $taxa_iva = 14;
                $iva = $subtotal * ($taxa_iva / 100);
                $total = $subtotal + $iva;

                // Inserir movimentação
                $stmt = $pdo->prepare("
                    INSERT INTO movimentacoes_caixa 
                    (tipo, categoria, descricao, valor, desconto, iva, taxa_iva, data_movimento, forma_pagamento, cliente_id, produto_id, quantidade, status) 
                    VALUES 
                    ('entrada', 'Vendas', ?, ?, 0, ?, ?, NOW(), ?, ?, ?, ?, 'confirmado')
                ");
                
                $descricao = "Venda de {$quantidade}x {$produto['nome']}";
                $stmt->execute([
                    $descricao,
                    $subtotal,
                    $iva,
                    $taxa_iva,
                    $forma_pagamento,
                    $cliente_id,
                    $produto_id,
                    $quantidade
                ]);

                $movimento_id = $pdo->lastInsertId();

                // Atualizar estoque
                $stmt = $pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");
                $stmt->execute([$quantidade, $produto_id]);

                // ===== EMITIR FATURA AGT =====
                $fatura_result = null;
                if ($emitir_fatura) {
                    $dados_fatura = [
                        'cliente_id' => $cliente_id,
                        'cliente_nome' => $cliente['nome'],
                        'cliente_nif' => $cliente['nif_agt'] ?? '999999999',
                        'cliente_endereco' => $cliente['endereco'] ?? null,
                        'cliente_telefone' => $cliente['telefone'] ?? null,
                        'cliente_email' => $cliente['email'] ?? null,
                        'forma_pagamento' => $forma_pagamento,
                        'observacoes' => $observacoes,
                        'itens' => [
                            [
                                'produto_id' => $produto_id,
                                'codigo' => $produto['codigo'],
                                'descricao' => $produto['nome'],
                                'quantidade' => $quantidade,
                                'preco_unitario' => $preco_unitario,
                                'desconto' => 0
                            ]
                        ]
                    ];
                    
                    $fatura_result = emitirFaturaAGT($dados_fatura);
                }

                $sucesso = 'Venda realizada com sucesso!';
                if ($fatura_result && $fatura_result['success']) {
                    $sucesso .= ' Fatura AGT emitida: ' . $fatura_result['numero_fatura'];
                    $fatura_emitida = $fatura_result;
                }
                
                // Limpar formulário
                $_POST = [];
            }
        } catch (Exception $e) {
            $erro = $e->getMessage();
        }
    }
}

// ===== INCLUIR HEADER =====
include '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Venda - Fluxo de Caixa</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .dashboard-container {
            display: flex !important;
            min-height: 100vh !important;
            width: 100% !important;
        }

        .sidebar {
            width: 260px !important;
            min-width: 260px !important;
            flex-shrink: 0 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            height: 100vh !important;
            z-index: 1000 !important;
            overflow-y: auto !important;
        }

        .main-content {
            flex: 1 !important;
            margin-left: 260px !important;
            min-height: 100vh !important;
            width: calc(100% - 260px) !important;
            max-width: calc(100% - 260px) !important;
            background: #f0f2f5 !important;
            overflow-x: hidden !important;
        }

        .content-area {
            padding: 20px 30px !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: auto !important;
        }

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
        
        .menu-caixa {
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
        
        .menu-caixa a {
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
        
        .menu-caixa a:hover {
            background: #c9a84c;
            color: #1a2332;
            border-color: #c9a84c;
            transform: translateY(-2px);
        }
        
        .menu-caixa a.active {
            background: #c9a84c;
            color: #1a2332;
            border-color: #c9a84c;
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
        
        .btn-success {
            background: #2ecc71;
            color: #fff;
        }
        
        .btn-success:hover {
            background: #27ae60;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: #fff;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .btn-warning {
            background: #f39c12;
            color: #fff;
        }
        
        .btn-warning:hover {
            background: #d68910;
        }
        
        .btn-info {
            background: #3498db;
            color: #fff;
        }
        
        .btn-info:hover {
            background: #2980b9;
        }
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 11px;
            border-radius: 6px;
        }
        
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            max-width: 700px;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #1a2332;
            font-size: 13px;
        }
        
        .form-group label .required {
            color: #e74c3c;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
        }
        
        .form-group textarea {
            min-height: 60px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .info-produto {
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-top: 5px;
            font-size: 13px;
            color: #4a5568;
        }
        
        .info-produto strong {
            color: #1a2332;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            font-weight: 600;
            color: #1a2332;
            cursor: pointer;
        }
        
        .fatura-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .fatura-info .numero {
            font-weight: 700;
            color: #c9a84c;
            font-size: 18px;
        }
        
        .fatura-info .codigo {
            font-family: monospace;
            background: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }
        
        @media (max-width: 768px) {
            .content-area {
                padding: 15px !important;
            }
            
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .menu-caixa {
                flex-direction: column;
                align-items: stretch;
            }
            
            .menu-caixa a {
                text-align: center;
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .content-area {
                padding: 10px !important;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <main class="main-content">
        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1>🛒 Nova Venda</h1>
                    <p class="subtitle">Registrar venda e emitir fatura fiscal AGT</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Voltar</a>
            </div>
            
            <div class="menu-caixa">
                <a href="index.php">📊 Dashboard</a>
                <a href="venda.php" class="active">🛒 Vender Produto</a>
                <a href="entrada.php">📥 Entrada</a>
                <a href="saida.php">📤 Saída</a>
                <a href="movimentacoes.php">📋 Movimentações</a>
                <a href="relatorio.php">📈 Relatório</a>
            </div>
            
            <?php if ($sucesso): ?>
            <div class="alert alert-success">✅ <?= $sucesso ?></div>
            <?php endif; ?>
            
            <?php if ($erro): ?>
            <div class="alert alert-error">❌ <?= $erro ?></div>
            <?php endif; ?>
            
            <?php if ($fatura_emitida && $fatura_emitida['success']): ?>
            <div class="alert alert-info">
                <strong>📄 Fatura AGT Emitida!</strong><br>
                Número: <span class="numero"><?= $fatura_emitida['numero_fatura'] ?></span><br>
                Código Validação: <span class="codigo"><?= $fatura_emitida['codigo_validacao'] ?></span><br>
                Total: R$ <?= number_format($fatura_emitida['valor_total'], 2, ',', '.') ?>
                <br><br>
                <a href="imprimir_fatura.php?id=<?= $fatura_emitida['fatura_id'] ?>" target="_blank" class="btn btn-primary btn-sm">🖨️ Imprimir Fatura</a>
            </div>
            <?php endif; ?>
            
            <div class="form-container">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Cliente <span class="required">*</span></label>
                            <select name="cliente_id" required>
                                <option value="">Selecione um cliente</option>
                                <?php foreach($clientes as $cliente): ?>
                                <option value="<?= $cliente['id'] ?>" <?= ($_POST['cliente_id'] ?? '') == $cliente['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cliente['nome']) ?> <?= $cliente['nif_agt'] ? '(NIF: '.$cliente['nif_agt'].')' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Forma de Pagamento <span class="required">*</span></label>
                            <select name="forma_pagamento" required>
                                <option value="dinheiro" <?= ($_POST['forma_pagamento'] ?? '') == 'dinheiro' ? 'selected' : '' ?>>💰 Dinheiro</option>
                                <option value="cartao_credito" <?= ($_POST['forma_pagamento'] ?? '') == 'cartao_credito' ? 'selected' : '' ?>>💳 Cartão Crédito</option>
                                <option value="cartao_debito" <?= ($_POST['forma_pagamento'] ?? '') == 'cartao_debito' ? 'selected' : '' ?>>💳 Cartão Débito</option>
                                <option value="pix" <?= ($_POST['forma_pagamento'] ?? '') == 'pix' ? 'selected' : '' ?>>📱 Pix</option>
                                <option value="transferencia" <?= ($_POST['forma_pagamento'] ?? '') == 'transferencia' ? 'selected' : '' ?>>🏦 Transferência</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Produto <span class="required">*</span></label>
                        <select name="produto_id" id="produto_id" required onchange="atualizarInfoProduto()">
                            <option value="">Selecione um produto</option>
                            <?php foreach($produtos as $produto): ?>
                            <option value="<?= $produto['id'] ?>" 
                                    data-preco="<?= $produto['preco_venda'] ?>" 
                                    data-estoque="<?= $produto['quantidade'] ?>"
                                    <?= ($_POST['produto_id'] ?? '') == $produto['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($produto['nome']) ?> (<?= $produto['codigo'] ?>) - Estoque: <?= $produto['quantidade'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="infoProduto" class="info-produto" style="display: none;">
                        <div class="form-row">
                            <div><strong>Preço Unitário:</strong> <span id="precoUnitario">R$ 0,00</span></div>
                            <div><strong>Estoque Disponível:</strong> <span id="estoqueDisponivel">0</span></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantidade <span class="required">*</span></label>
                        <input type="number" name="quantidade" id="quantidade" value="<?= $_POST['quantidade'] ?? 1 ?>" min="1" required onchange="calcularTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>Observações</label>
                        <textarea name="observacoes" placeholder="Observações sobre a venda"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="emitir_fatura" id="emitir_fatura" checked>
                        <label for="emitir_fatura">📄 Emitir Fatura Fiscal (AGT)</label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">✅ Finalizar Venda</button>
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
            
            <div class="dashboard-footer">
                <p>© <?= date('Y') ?> <strong><?= htmlspecialchars($nomeEmpresa ?? 'SoftGest') ?></strong> - Sistema de Gestão Empresarial</p>
                <p style="font-size: 11px; color: #94a3b8;">Sistema autorizado para emissão de faturas AGT</p>
            </div>
        </div>
    </main>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('active');
    }

    function closeSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
    }

    function atualizarInfoProduto() {
        const select = document.getElementById('produto_id');
        const infoDiv = document.getElementById('infoProduto');
        const precoSpan = document.getElementById('precoUnitario');
        const estoqueSpan = document.getElementById('estoqueDisponivel');
        
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption.value) {
            const preco = parseFloat(selectedOption.dataset.preco) || 0;
            const estoque = parseInt(selectedOption.dataset.estoque) || 0;
            
            precoSpan.textContent = 'R$ ' + preco.toFixed(2).replace('.', ',');
            estoqueSpan.textContent = estoque;
            infoDiv.style.display = 'block';
            
            document.getElementById('quantidade').max = estoque;
        } else {
            infoDiv.style.display = 'none';
        }
    }

    function calcularTotal() {
        const select = document.getElementById('produto_id');
        const quantidade = parseInt(document.getElementById('quantidade').value) || 0;
        
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption.value) {
            const preco = parseFloat(selectedOption.dataset.preco) || 0;
            const total = preco * quantidade;
            const estoque = parseInt(selectedOption.dataset.estoque) || 0;
            
            if (quantidade > estoque) {
                alert('Quantidade maior que o estoque disponível!');
                document.getElementById('quantidade').value = estoque;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        atualizarInfoProduto();
    });

    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('.menu-toggle');
        const isMobile = window.innerWidth <= 992;
        if (isMobile && sidebar && toggle) {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                closeSidebar();
            }
        }
    });

    console.log('🛒 Página de Nova Venda com Fatura AGT carregada!');
</script>

</body>
</html>