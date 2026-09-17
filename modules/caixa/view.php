<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT m.*, c.nome as cliente_nome, p.nome as produto_nome 
                        FROM movimentacoes_caixa m 
                        LEFT JOIN clientes c ON m.cliente_id = c.id 
                        LEFT JOIN produtos p ON m.produto_id = p.id 
                        WHERE m.id = ?");
$stmt->execute([$id]);
$mov = $stmt->fetch();

if (!$mov) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Movimentação - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .view-container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .view-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 30px;
            border-left: 5px solid <?= $mov['tipo'] == 'entrada' ? '#2ecc71' : '#e74c3c' ?>;
        }
        .view-card h2 { color: #1a2332; border-bottom: 2px solid #f0f2f5; padding-bottom: 15px; margin-bottom: 20px; }
        .view-item { display: flex; padding: 10px 0; border-bottom: 1px solid #f0f2f5; }
        .view-item .label { font-weight: 600; color: #64748b; width: 130px; flex-shrink: 0; }
        .view-item .value { color: #1e293b; font-weight: 500; }
        .view-item .value.valor { font-size: 24px; font-weight: 700; color: <?= $mov['tipo'] == 'entrada' ? '#2ecc71' : '#e74c3c' ?>; }
        .btn-actions { display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap; }
        @media (max-width: 768px) { .view-item { flex-direction: column; } .view-item .label { width: 100%; } }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="view-container">
        <div class="view-card">
            <h2><?= $mov['tipo'] == 'entrada' ? '📥' : '📤' ?> <?= ucfirst($mov['tipo']) ?></h2>
            
            <div class="view-item">
                <span class="label">ID</span>
                <span class="value">#<?= $mov['id'] ?></span>
            </div>
            <div class="view-item">
                <span class="label">Data</span>
                <span class="value"><?= date('d/m/Y', strtotime($mov['data_movimento'])) ?></span>
            </div>
            <div class="view-item">
                <span class="label">Tipo</span>
                <span class="value"><?= $mov['tipo'] == 'entrada' ? '✅ Entrada (Receita)' : '❌ Saída (Despesa)' ?></span>
            </div>
            <div class="view-item">
                <span class="label">Categoria</span>
                <span class="value"><?= htmlspecialchars($mov['categoria']) ?></span>
            </div>
            <div class="view-item">
                <span class="label">Descrição</span>
                <span class="value"><?= htmlspecialchars($mov['descricao'] ?? '-') ?></span>
            </div>
            <div class="view-item">
                <span class="label">Valor</span>
                <span class="value valor"><?= $mov['tipo'] == 'entrada' ? '+' : '-' ?> R$ <?= number_format($mov['valor'], 2, ',', '.') ?></span>
            </div>
            <div class="view-item">
                <span class="label">Forma de Pagamento</span>
                <span class="value"><?= ucfirst(str_replace('_', ' ', $mov['forma_pagamento'])) ?></span>
            </div>
            <?php if ($mov['cliente_nome']): ?>
            <div class="view-item">
                <span class="label">Cliente</span>
                <span class="value"><?= htmlspecialchars($mov['cliente_nome']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($mov['produto_nome']): ?>
            <div class="view-item">
                <span class="label">Produto</span>
                <span class="value"><?= htmlspecialchars($mov['produto_nome']) ?> (<?= $mov['quantidade'] ?> un)</span>
            </div>
            <?php endif; ?>
            <?php if ($mov['observacoes']): ?>
            <div class="view-item">
                <span class="label">Observações</span>
                <span class="value"><?= nl2br(htmlspecialchars($mov['observacoes'])) ?></span>
            </div>
            <?php endif; ?>
            <div class="view-item">
                <span class="label">Status</span>
                <span class="value">
                    <span style="background: <?= $mov['status'] == 'confirmado' ? '#d1fae5' : '#fef3c7' ?>; padding: 2px 12px; border-radius: 12px; font-weight: 600;">
                        <?= ucfirst($mov['status']) ?>
                    </span>
                </span>
            </div>
            <div class="view-item">
                <span class="label">Cadastro</span>
                <span class="value"><?= date('d/m/Y H:i', strtotime($mov['created_at'])) ?></span>
            </div>
            
            <div class="btn-actions">
                <a href="delete.php?id=<?= $mov['id'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza?')">🗑️ Excluir</a>
                <a href="movimentacoes.php" class="btn">← Voltar</a>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>