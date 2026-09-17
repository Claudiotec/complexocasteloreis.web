<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

// Buscar cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Cliente - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .view-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
        }
        .view-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 30px;
        }
        .view-card h2 {
            color: #2c3e50;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .view-item {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .view-item .label {
            font-weight: 600;
            color: #64748b;
            width: 150px;
            flex-shrink: 0;
        }
        .view-item .value {
            color: #1e293b;
            font-weight: 500;
        }
        .view-item .value.empty {
            color: #94a3b8;
            font-style: italic;
        }
        .btn-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        @media (max-width: 768px) {
            .view-item {
                flex-direction: column;
            }
            .view-item .label {
                width: 100%;
                margin-bottom: 4px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="view-container">
        <div class="view-card">
            <h2>👤 Visualizar Cliente</h2>
            
            <div class="view-item">
                <span class="label">ID</span>
                <span class="value">#<?= $cliente['id'] ?></span>
            </div>
            <div class="view-item">
                <span class="label">Nome</span>
                <span class="value"><?= htmlspecialchars($cliente['nome']) ?></span>
            </div>
            <div class="view-item">
                <span class="label">Email</span>
                <span class="value <?= empty($cliente['email']) ? 'empty' : '' ?>">
                    <?= htmlspecialchars($cliente['email'] ?? 'Não informado') ?>
                </span>
            </div>
            <div class="view-item">
                <span class="label">Telefone</span>
                <span class="value <?= empty($cliente['telefone']) ? 'empty' : '' ?>">
                    <?= htmlspecialchars($cliente['telefone'] ?? 'Não informado') ?>
                </span>
            </div>
            <div class="view-item">
                <span class="label">Endereço</span>
                <span class="value <?= empty($cliente['endereco']) ? 'empty' : '' ?>">
                    <?= htmlspecialchars($cliente['endereco'] ?? 'Não informado') ?>
                </span>
            </div>
            <div class="view-item">
                <span class="label">CPF/CNPJ</span>
                <span class="value <?= empty($cliente['documento']) ? 'empty' : '' ?>">
                    <?= htmlspecialchars($cliente['documento'] ?? 'Não informado') ?>
                </span>
            </div>
            <div class="view-item">
                <span class="label">Data Cadastro</span>
                <span class="value"><?= date('d/m/Y H:i', strtotime($cliente['created_at'])) ?></span>
            </div>
            
            <div class="btn-actions">
                <a href="edit.php?id=<?= $cliente['id'] ?>" class="btn btn-primary">✏️ Editar</a>
                <a href="delete.php?id=<?= $cliente['id'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este cliente?')">🗑️ Excluir</a>
                <a href="index.php" class="btn">← Voltar</a>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>