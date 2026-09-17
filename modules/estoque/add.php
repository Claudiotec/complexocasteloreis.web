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
    $peso = $_POST['peso'] ?? 0;
    $dimensoes = $_POST['dimensoes'];
    $observacoes = $_POST['observacoes'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO produtos (
            codigo, nome, descricao, categoria, quantidade, 
            preco_compra, preco_venda, fornecedor, estoque_minimo,
            localizacao, unidade, peso, dimensoes, observacoes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $codigo, $nome, $descricao, $categoria, $quantidade,
            $preco_compra, $preco_venda, $fornecedor, $estoque_minimo,
            $localizacao, $unidade, $peso, $dimensoes, $observacoes
        ]);
        
        header("Location: index.php?success=1");
        exit;
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
        .form-section {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0 10px 0;
            border-left: 4px solid #c9a84c;
        }
        .form-section h4 { color: #1a2332; margin: 0; font-size: 15px; }
        .form-section p { color: #94a3b8; margin: 2px 0 0; font-size: 13px; }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>➕ Novo Produto</h2>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Dados Principais -->
            <div class="form-section">
                <h4>📋 Dados do Produto</h4>
                <p>Informações básicas do produto</p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Código *</label>
                    <input type="text" name="codigo" required placeholder="Código único do produto">
                </div>
                <div class="form-group">
                    <label>Nome *</label>
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
            <div class="form-section">
                <h4>📦 Estoque</h4>
                <p>Controle de quantidade e localização</p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Quantidade *</label>
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
            <div class="form-section">
                <h4>💰 Preços</h4>
                <p>Valores de compra e venda</p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Preço de Compra</label>
                    <input type="number" step="0.01" name="preco_compra" placeholder="0,00">
                </div>
                <div class="form-group">
                    <label>Preço de Venda *</label>
                    <input type="number" step="0.01" name="preco_venda" placeholder="0,00" required>
                </div>
            </div>
            
            <!-- Fornecedor -->
            <div class="form-section">
                <h4>🏢 Fornecedor</h4>
                <p>Informações do fornecedor</p>
            </div>
            
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
            
            <button type="submit" class="btn btn-primary">💾 Salvar Produto</button>
            <a href="index.php" class="btn">Cancelar</a>
        </form>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>