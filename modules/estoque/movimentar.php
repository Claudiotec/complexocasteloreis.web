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

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo = $_POST['tipo'];
    $quantidade = $_POST['quantidade'];
    $observacao = $_POST['observacao'];
    
    if ($tipo == 'entrada') {
        $nova_quantidade = $produto['quantidade'] + $quantidade;
    } else {
        if ($quantidade > $produto['quantidade']) {
            $mensagem = 'Erro: Quantidade insuficiente em estoque!';
            $tipoMensagem = 'error';
        } else {
            $nova_quantidade = $produto['quantidade'] - $quantidade;
        }
    }
    
    if (empty($mensagem)) {
        // Atualizar estoque
        $stmt = $pdo->prepare("UPDATE produtos SET quantidade = ? WHERE id = ?");
        $stmt->execute([$nova_quantidade, $id]);
        
        // Registrar movimentação
        $stmt = $pdo->prepare("INSERT INTO movimentacoes_estoque (produto_id, tipo, quantidade, observacao) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id, $tipo, $quantidade, $observacao]);
        
        header("Location: index.php?movimentado=1");
        exit;
    }
}

// Buscar movimentações
$stmt = $pdo->prepare("SELECT * FROM movimentacoes_estoque WHERE produto_id = ? ORDER BY data_movimento DESC LIMIT 10");
$stmt->execute([$id]);
$movimentacoes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimentar Estoque - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>📊 Movimentar Estoque</h2>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3><?= htmlspecialchars($produto['nome']) ?></h3>
            <p><strong>Código:</strong> <?= htmlspecialchars($produto['codigo']) ?></p>
            <p><strong>Quantidade Atual:</strong> <span style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?= $produto['quantidade'] ?></span></p>
        </div>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Formulário de Movimentação -->
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04);">
                <h3>📝 Nova Movimentação</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="tipo" required>
                            <option value="entrada">✅ Entrada</option>
                            <option value="saida">❌ Saída</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantidade *</label>
                        <input type="number" name="quantidade" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Observação</label>
                        <input type="text" name="observacao" placeholder="Motivo da movimentação">
                    </div>
                    <button type="submit" class="btn btn-primary">📊 Registrar</button>
                    <a href="index.php" class="btn">Cancelar</a>
                </form>
            </div>
            
            <!-- Histórico -->
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04);">
                <h3>📋 Últimas Movimentações</h3>
                <?php if (count($movimentacoes) > 0): ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach($movimentacoes as $mov): ?>
                            <div style="padding: 10px; border-bottom: 1px solid #f0f2f5; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span style="font-weight: 600; color: <?= $mov['tipo'] == 'entrada' ? '#2ecc71' : '#e74c3c' ?>;">
                                        <?= $mov['tipo'] == 'entrada' ? '✅' : '❌' ?> <?= ucfirst($mov['tipo']) ?>
                                    </span>
                                    <span style="margin-left: 10px;"><?= $mov['quantidade'] ?> un</span>
                                    <?php if ($mov['observacao']): ?>
                                        <br><small style="color: #94a3b8;"><?= htmlspecialchars($mov['observacao']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 12px; color: #94a3b8;">
                                    <?= date('d/m/Y H:i', strtotime($mov['data_movimento'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #94a3b8; padding: 20px;">Nenhuma movimentação registrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>