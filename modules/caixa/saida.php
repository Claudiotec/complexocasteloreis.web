<?php
require_once '../../config/database.php';

// Buscar categorias de saída
$categorias = $pdo->query("SELECT * FROM categorias_caixa WHERE tipo = 'saida' ORDER BY nome")->fetchAll();

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $categoria = $_POST['categoria'];
    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $data_movimento = $_POST['data_movimento'];
    $forma_pagamento = $_POST['forma_pagamento'];
    $observacoes = $_POST['observacoes'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO movimentacoes_caixa (tipo, categoria, descricao, valor, data_movimento, forma_pagamento, observacoes, status) VALUES ('saida', ?, ?, ?, ?, ?, ?, 'confirmado')");
        $stmt->execute([$categoria, $descricao, $valor, $data_movimento, $forma_pagamento, $observacoes]);
        
        $mensagem = 'Saída registrada com sucesso!';
        $tipoMensagem = 'success';
    } catch(Exception $e) {
        $mensagem = 'Erro ao registrar saída: ' . $e->getMessage();
        $tipoMensagem = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Saída - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .form-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 30px; position: relative; overflow: hidden;
        }
        .form-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 5px; background: linear-gradient(90deg, #e74c3c, #c0392b);
        }
        .form-card h2 {
            color: #1a2332; border-bottom: 2px solid #f0f2f5;
            padding-bottom: 15px; margin-bottom: 25px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; font-weight: 600; color: #2c3e50; margin-bottom: 5px;
        }
        .form-group label .required { color: #e74c3c; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0;
            border-radius: 8px; font-size: 14px; transition: all 0.3s;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #e74c3c; outline: none; box-shadow: 0 0 0 3px rgba(231,76,60,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-danger {
            background: #e74c3c; color: white; padding: 14px 30px; border: none;
            border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer;
            transition: all 0.3s;
        }
        .btn-danger:hover {
            transform: translateY(-2px); box-shadow: 0 4px 15px rgba(231,76,60,0.3);
        }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; gap: 0; } }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="form-container">
        <div class="form-card">
            <h2>📤 Nova Saída (Despesa)</h2>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Categoria <span class="required">*</span></label>
                        <select name="categoria" required>
                            <option value="">Selecione</option>
                            <?php foreach($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['nome']) ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                
                <div class="form-group">
                    <label>Descrição <span class="required">*</span></label>
                    <input type="text" name="descricao" required placeholder="Descrição da saída">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Valor <span class="required">*</span></label>
                        <input type="number" step="0.01" name="valor" required placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label>Data <span class="required">*</span></label>
                        <input type="date" name="data_movimento" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Observações</label>
                    <textarea name="observacoes" placeholder="Informações adicionais..."></textarea>
                </div>
                
                <button type="submit" class="btn-danger">💾 Registrar Saída</button>
                <a href="index.php" class="btn">Cancelar</a>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>