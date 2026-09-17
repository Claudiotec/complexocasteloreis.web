<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

// Buscar produto
$stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
$stmt->execute([$id]);
$produto = $stmt->fetch();

if (!$produto) {
    header("Location: index.php");
    exit;
}

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
    
    try {
        $stmt = $pdo->prepare("UPDATE produtos SET codigo=?, nome=?, descricao=?, categoria=?, quantidade=?, preco_compra=?, preco_venda=?, fornecedor=?, estoque_minimo=? WHERE id=?");
        $stmt->execute([$codigo, $nome, $descricao, $categoria, $quantidade, $preco_compra, $preco_venda, $fornecedor, $estoque_minimo, $id]);
        
        $mensagem = 'Produto atualizado com sucesso!';
        $tipoMensagem = 'success';
        
        echo "<script>setTimeout(function(){ window.location.href = 'index.php?updated=1'; }, 1500);</script>";
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
            <h2>✏️ Editar Produto</h2>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Código <span class="required">*</span></label>
                        <input type="text" name="codigo" value="<?= htmlspecialchars($produto['codigo']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nome <span class="required">*</span></label>
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
                        <label>Fornecedor</label>
                        <input type="text" name="fornecedor" value="<?= htmlspecialchars($produto['fornecedor']) ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Quantidade <span class="required">*</span></label>
                        <input type="number" name="quantidade" min="0" value="<?= $produto['quantidade'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Estoque Mínimo</label>
                        <input type="number" name="estoque_minimo" min="0" value="<?= $produto['estoque_minimo'] ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Preço de Compra</label>
                        <input type="number" step="0.01" name="preco_compra" value="<?= $produto['preco_compra'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Preço de Venda <span class="required">*</span></label>
                        <input type="number" step="0.01" name="preco_venda" value="<?= $produto['preco_venda'] ?>" required>
                    </div>
                </div>
                
                <div class="btn-actions">
                    <button type="submit" class="btn btn-primary">💾 Atualizar</button>
                    <a href="index.php" class="btn">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>