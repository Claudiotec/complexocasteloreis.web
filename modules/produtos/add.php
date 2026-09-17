<?php
require_once '../../config/database.php';

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $codigo = $_POST['codigo'];
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $categoria = $_POST['categoria'];
    $quantidade = $_POST['quantidade'];
    $preco_compra = $_POST['preco_compra'];
    $preco_venda = $_POST['preco_venda'];
    $fornecedor = $_POST['fornecedor'];
    $estoque_minimo = $_POST['estoque_minimo'];
    $localizacao = $_POST['localizacao'];
    $unidade = $_POST['unidade'];
    $peso = $_POST['peso'];
    $dimensoes = $_POST['dimensoes'];
    $observacoes = $_POST['observacoes'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO produtos (codigo, nome, descricao, categoria, quantidade, preco_compra, preco_venda, fornecedor, estoque_minimo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$codigo, $nome, $descricao, $categoria, $quantidade, $preco_compra, $preco_venda, $fornecedor, $estoque_minimo]);
        
        $mensagem = 'Produto cadastrado com sucesso!';
        $tipoMensagem = 'success';
        
        echo "<script>setTimeout(function(){ window.location.href = 'index.php?success=1'; }, 1500);</script>";
    } catch(PDOException $e) {
        $mensagem = 'Erro ao cadastrar produto: ' . $e->getMessage();
        $tipoMensagem = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Produto - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 30px;
        }
        .form-card h2 {
            margin-bottom: 25px; color: #2c3e50; border-bottom: 2px solid #f0f2f5;
            padding-bottom: 15px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #2c3e50; margin-bottom: 5px; }
        .form-group label .required { color: #e74c3c; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; transition: all 0.3s; font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #3498db; outline: none; box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-actions { display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .btn-actions { flex-direction: column; }
            .btn-actions .btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="form-container">
        <div class="form-card">
            <h2>➕ Novo Produto</h2>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <!-- Dados Principais -->
                <h3 style="color: #2c3e50; margin-bottom: 15px;">📋 Dados do Produto</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Código <span class="required">*</span></label>
                        <input type="text" name="codigo" required placeholder="Código único do produto">
                    </div>
                    <div class="form-group">
                        <label>Nome <span class="required">*</span></label>
                        <input type="text" name="nome" required placeholder="Nome do produto">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" placeholder="Descrição detalhada do produto"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Categoria</label>
                        <input type="text" name="categoria" placeholder="Ex: Informática, Periféricos...">
                    </div>
                    <div class="form-group">
                        <label>Unidade</label>
                        <select name="unidade">
                            <option value="un">Unidade</option>
                            <option value="kg">Quilograma</option>
                            <option value="g">Grama</option>
                            <option value="l">Litro</option>
                            <option value="ml">Mililitro</option>
                            <option value="m">Metro</option>
                            <option value="cm">Centímetro</option>
                            <option value="cx">Caixa</option>
                            <option value="pct">Pacote</option>
                        </select>
                    </div>
                </div>
                
                <!-- Estoque -->
                <h3 style="color: #2c3e50; margin: 25px 0 15px 0;">📦 Estoque</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Quantidade em Estoque <span class="required">*</span></label>
                        <input type="number" name="quantidade" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label>Estoque Mínimo</label>
                        <input type="number" name="estoque_minimo" min="0" value="5">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Localização</label>
                    <input type="text" name="localizacao" placeholder="Ex: Prateleira A1, Depósito 2">
                </div>
                
                <!-- Preços -->
                <h3 style="color: #2c3e50; margin: 25px 0 15px 0;">💰 Preços</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Preço de Compra</label>
                        <input type="number" step="0.01" name="preco_compra" placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label>Preço de Venda <span class="required">*</span></label>
                        <input type="number" step="0.01" name="preco_venda" placeholder="0,00" required>
                    </div>
                </div>
                
                <!-- Fornecedor -->
                <h3 style="color: #2c3e50; margin: 25px 0 15px 0;">🏢 Fornecedor</h3>
                
                <div class="form-group">
                    <label>Fornecedor</label>
                    <input type="text" name="fornecedor" placeholder="Nome do fornecedor">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Peso (kg)</label>
                        <input type="number" step="0.001" name="peso" placeholder="0,000">
                    </div>
                    <div class="form-group">
                        <label>Dimensões</label>
                        <input type="text" name="dimensoes" placeholder="Ex: 30x20x10 cm">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Observações</label>
                    <textarea name="observacoes" placeholder="Informações adicionais sobre o produto..."></textarea>
                </div>
                
                <div class="btn-actions">
                    <button type="submit" class="btn btn-primary">💾 Salvar Produto</button>
                    <a href="index.php" class="btn">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>