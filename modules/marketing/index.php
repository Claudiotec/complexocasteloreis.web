<?php
require_once '../../config/database.php';

// Buscar planos de marketing
$stmt = $pdo->query("SELECT * FROM plano_marketing ORDER BY created_at DESC");
$planos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>📊 Planos de Marketing</h2>
        
        <div class="actions">
            <a href="plano.php" class="btn btn-primary">+ Novo Plano</a>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                ✅ Plano criado com sucesso!
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                ✅ Plano excluído com sucesso!
            </div>
        <?php endif; ?>
        
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Objetivo</th>
                    <th>Orçamento</th>
                    <th>Status</th>
                    <th>Data Início</th>
                    <th>Data Fim</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($planos) > 0): ?>
                    <?php foreach($planos as $plano): ?>
                    <tr>
                        <td><?= $plano['id'] ?></td>
                        <td><strong><?= htmlspecialchars($plano['titulo']) ?></strong></td>
                        <td><?= htmlspecialchars(substr($plano['objetivo'], 0, 50)) ?>...</td>
                        <td>R$ <?= number_format($plano['orcamento'], 2, ',', '.') ?></td>
                        <td>
                            <?php
                            $statusColors = [
                                'planejado' => '#3498db',
                                'em_andamento' => '#f39c12',
                                'concluido' => '#2ecc71',
                                'cancelado' => '#e74c3c'
                            ];
                            $color = $statusColors[$plano['status']] ?? '#666';
                            ?>
                            <span style="background: <?= $color ?>; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px;">
                                <?= str_replace('_', ' ', ucfirst($plano['status'])) ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y', strtotime($plano['data_inicio'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($plano['data_fim'])) ?></td>
                        <td>
                            <a href="editar.php?id=<?= $plano['id'] ?>" class="btn-small">Editar</a>
                            <a href="excluir.php?id=<?= $plano['id'] ?>" class="btn-small" style="background: #e74c3c;" onclick="return confirm('Tem certeza que deseja excluir este plano?')">Excluir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                            <p style="font-size: 48px; margin-bottom: 10px;">📊</p>
                            <p>Nenhum plano de marketing cadastrado.</p>
                            <p style="margin-top: 10px;">
                                <a href="plano.php" class="btn btn-primary">Criar Primeiro Plano</a>
                            </p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3>📈 Resumo</h3>
            <ul>
                <li><strong>Total de Planos:</strong> <?= count($planos) ?></li>
                <?php
                $stmt = $pdo->query("SELECT SUM(orcamento) as total FROM plano_marketing");
                $totalOrcamento = $stmt->fetch()['total'] ?? 0;
                ?>
                <li><strong>Orçamento Total:</strong> R$ <?= number_format($totalOrcamento, 2, ',', '.') ?></li>
            </ul>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>