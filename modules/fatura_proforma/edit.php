<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

// Buscar dados da fatura
$stmt = $pdo->prepare("SELECT * FROM faturas_proforma WHERE id = ?");
$stmt->execute([$id]);
$fatura = $stmt->fetch();

if (!$fatura) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $data_emissao = $_POST['data_emissao'];
    $data_validade = $_POST['data_validade'];
    $observacoes = $_POST['observacoes'];
    $status = $_POST['status'];
    
    // Atualizar fatura
    $stmt = $pdo->prepare("UPDATE faturas_proforma SET cliente_id=?, data_emissao=?, data_validade=?, observacoes=?, status=? WHERE id=?");
    $stmt->execute([$cliente_id, $data_emissao, $data_validade, $observacoes, $status, $id]);
    
    // Remover itens antigos
    $stmt = $pdo->prepare("DELETE FROM fatura_proforma_itens WHERE fatura_id = ?");
    $stmt->execute([$id]);
    
    // Inserir novos itens
    $subtotal = 0;
    foreach ($_POST['produto_id'] as $key => $produto_id) {
        if ($produto_id) {
            $quantidade = $_POST['quantidade'][$key];
            $preco_unitario = $_POST['preco_unitario'][$key];
            $desconto = $_POST['desconto'][$key];
            $total_item = ($quantidade * $preco_unitario) - $desconto;
            
            $stmt = $pdo->prepare("INSERT INTO fatura_proforma_itens (fatura_id, produto_id, quantidade, preco_unitario, desconto, total) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $produto_id, $quantidade, $preco_unitario, $desconto, $total_item]);
            
            $subtotal += $total_item;
        }
    }
    
    // Atualizar total
    $stmt = $pdo->prepare("UPDATE faturas_proforma SET subtotal = ?, total = ? WHERE id = ?");
    $stmt->execute([$subtotal, $subtotal, $id]);
    
    header("Location: index.php?updated=1");
    exit;
}

// Buscar clientes e produtos
$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();
$produtos = $pdo->query("SELECT * FROM produtos ORDER BY nome")->fetchAll();

// Buscar itens existentes
$stmtItens = $pdo->prepare("SELECT * FROM fatura_proforma_itens WHERE fatura_id = ?");
$stmtItens->execute([$id]);
$itens = $stmtItens->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Fatura - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>✏️ Editar Fatura Proforma</h2>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente:</label>
                    <select name="cliente_id" required>
                        <option value="">Selecione</option>
                        <?php foreach($clientes as $cliente): ?>
                        <option value="<?= $cliente['id'] ?>" <?= $cliente['id'] == $fatura['cliente_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cliente['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status:</label>
                    <select name="status" required>
                        <option value="rascunho" <?= $fatura['status'] == 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
                        <option value="enviada" <?= $fatura['status'] == 'enviada' ? 'selected' : '' ?>>Enviada</option>
                        <option value="aprovada" <?= $fatura['status'] == 'aprovada' ? 'selected' : '' ?>>Aprovada</option>
                        <option value="rejeitada" <?= $fatura['status'] == 'rejeitada' ? 'selected' : '' ?>>Rejeitada</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Data de Emissão:</label>
                    <input type="date" name="data_emissao" value="<?= $fatura['data_emissao'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Data de Validade:</label>
                    <input type="date" name="data_validade" value="<?= $fatura['data_validade'] ?>" required>
                </div>
            </div>
            
            <h3>Itens da Fatura</h3>
            <div id="itens-container">
                <?php if (count($itens) > 0): ?>
                    <?php foreach($itens as $index => $item): ?>
                    <div class="item-row">
                        <hr>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Produto:</label>
                                <select name="produto_id[]" required>
                                    <option value="">Selecione</option>
                                    <?php foreach($produtos as $produto): ?>
                                    <option value="<?= $produto['id'] ?>" <?= $produto['id'] == $item['produto_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($produto['nome']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantidade:</label>
                                <input type="number" name="quantidade[]" min="1" value="<?= $item['quantidade'] ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Preço Unitário:</label>
                                <input type="number" step="0.01" name="preco_unitario[]" value="<?= $item['preco_unitario'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Desconto:</label>
                                <input type="number" step="0.01" name="desconto[]" value="<?= $item['desconto'] ?>">
                            </div>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" class="btn-small" style="background: #e74c3c;">Remover Item</button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="item-row">
                        <hr>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Produto:</label>
                                <select name="produto_id[]" required>
                                    <option value="">Selecione</option>
                                    <?php foreach($produtos as $produto): ?>
                                    <option value="<?= $produto['id'] ?>"><?= htmlspecialchars($produto['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantidade:</label>
                                <input type="number" name="quantidade[]" min="1" value="1" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Preço Unitário:</label>
                                <input type="number" step="0.01" name="preco_unitario[]" required>
                            </div>
                            <div class="form-group">
                                <label>Desconto:</label>
                                <input type="number" step="0.01" name="desconto[]" value="0">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" onclick="adicionarItem()" class="btn">+ Adicionar Item</button>
            
            <div class="form-group" style="margin-top: 20px;">
                <label>Observações:</label>
                <textarea name="observacoes"><?= htmlspecialchars($fatura['observacoes']) ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Atualizar Fatura</button>
            <a href="index.php" class="btn">Cancelar</a>
        </form>
    </div>
    
    <script>
    function adicionarItem() {
        const container = document.getElementById('itens-container');
        const novoItem = document.createElement('div');
        novoItem.className = 'item-row';
        novoItem.innerHTML = `
            <hr>
            <div class="form-row">
                <div class="form-group">
                    <label>Produto:</label>
                    <select name="produto_id[]" required>
                        <option value="">Selecione</option>
                        <?php foreach($produtos as $produto): ?>
                        <option value="<?= $produto['id'] ?>"><?= htmlspecialchars($produto['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantidade:</label>
                    <input type="number" name="quantidade[]" min="1" value="1" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Preço Unitário:</label>
                    <input type="number" step="0.01" name="preco_unitario[]" required>
                </div>
                <div class="form-group">
                    <label>Desconto:</label>
                    <input type="number" step="0.01" name="desconto[]" value="0">
                </div>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="btn-small" style="background: #e74c3c;">Remover Item</button>
        `;
        container.appendChild(novoItem);
    }
    </script>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>