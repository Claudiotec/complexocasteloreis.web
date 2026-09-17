<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
$stmt->execute([$id]);
$produto = $stmt->fetch();

if (!$produto) {
    header("Location: index.php");
    exit;
}

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
        $stmt = $pdo->prepare("UPDATE produtos SET 
            codigo=?, nome=?, descricao=?, categoria=?, quantidade=?, 
            preco_compra=?, preco_venda=?, fornecedor=?, estoque_minimo=?,
            localizacao=?, unidade=?, peso=?, dimensoes=?, observacoes=?
            WHERE id=?");
        
        $stmt->execute([
            $codigo, $nome, $descricao, $categoria, $quantidade,
            $preco_compra, $preco_venda, $fornecedor, $estoque_minimo,
            $localizacao, $unidade, $peso, $dimensoes, $observacoes, $id
        ]);
        
        header("Location: index.php?updated=1");
        exit;
    } catch(PDOException $e) {
        $mensagem = 'Erro ao atualizar produto: ' . $e->getMessage();
        $tipoMensagem = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto - SoftGest Web</title>
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
        <h2>✏️ Editar Produto</h2>
        
        <?php if (isset($mensagem)): ?>
            <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-section">
                <h4>📋 Dados do Produto</h4>
                <p>Informações básicas do produto</p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Código *</label>
                    <input type="text" name="codigo" value="<?= htmlspecialchars($produto['codigo']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($produto['nome']) ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Descrição</label>
                <textarea name="descricao"><?= htmlspecialchars($produto['descricao']) ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Categoria</label>
                    <input type="text" name="categoria" value="<?= htmlspecialchars($produto['categoria']) ?>">
                </div>
                <div class="form-group">
                    <label>Unidade</label>
                    <select name="unidade">
                        <option value="un" <?= ($produto['unidade'] ?? 'un') == 'un' ? 'selected' : '' ?>>Unidade</option>
                        <option value="kg" <?= ($produto['unidade'] ?? '') == 'kg' ? 'selected' : '' ?>>Quilograma</option>
                        <option value="g" <?= ($produto['unidade'] ?? '') == 'g' ? 'selected' : '' ?>>Grama</option>
                        <option value="l" <?= ($produto['unidade'] ?? '') == 'l' ? 'selected' : '' ?>>Litro</option>
                        <option value="ml" <?= ($produto['unidade'] ?? '') == 'ml' ? 'selected' : '' ?>>Mililitro</option>
                        <option value="m" <?= ($produto['unidade'] ?? '') == 'm' ? 'selected' : '' ?>>Metro</option>
                        <option value="cm" <?= ($produto['unidade'] ?? '') == 'cm' ? 'selected' : '' ?>>Centímetro</option>
                        <option value="cx" <?= ($produto['unidade'] ?? '') == 'cx' ? 'selected' : '' ?>>Caixa</option>
                        <option value="pct" <?= ($produto['unidade'] ?? '') == 'pct' ? 'selected' : '' ?>>Pacote</option>
                    </select>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📦 Estoque</h4>
                <p>Controle de quantidade e localização</p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Quantidade *</label>
                    <input type="number" name="quantidade" min="0" value="<?= $produto['quantidade'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Estoque Mínimo</label>
                    <input type="number" name="estoque_minimo" min="0" value="<?= $produto['estoque_minimo'] ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Localização</label>
                <input type="text" name="localizacao" value="<?= htmlspecialchars($produto['localizacao']) ?>">
            </div>
            
            <div class="form-section">
                <h4>💰 Preços</h4>
                <p>Valores de compra e venda</p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Preço de Compra</label>
                    <input type="number" step="0.01" name="preco_compra" value="<?= $produto['preco_compra'] ?>">
                </div>
                <div class="form-group">
                    <label>Preço de Venda *</label>
                    <input type="number" step="0.01" name="preco_venda" value="<?= $produto['preco_venda'] ?>" required>
                </div>
            </div>
            
            <div class="form-section">
                <h4>🏢 Fornecedor</h4>
                <p>Informações do fornecedor</p>
            </div>
            
            <div class="form-group">
                <label>Fornecedor</label>
                <input type="text" name="fornecedor" value="<?= htmlspecialchars($produto['fornecedor']) ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Peso (kg)</label>
                    <input type="number" step="0.001" name="peso" value="<?= $produto['peso'] ?>">
                </div>
                <div class="form-group">
                    <label>Dimensões</label>
                    <input type="text" name="dimensoes" value="<?= htmlspecialchars($produto['dimensoes']) ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes"><?= htmlspecialchars($produto['observacoes']) ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">💾 Atualizar</button>
            <a href="index.php" class="btn">Cancelar</a>
        </form>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>