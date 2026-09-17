<?php
require_once '../../config/database.php';

// Buscar correspondências
$stmt = $pdo->query("SELECT * FROM correspondencias ORDER BY created_at DESC");
$correspondencias = $stmt->fetchAll();

// Contar por status
$stmtEnviados = $pdo->query("SELECT COUNT(*) as total FROM correspondencias WHERE status = 'enviado'");
$totalEnviados = $stmtEnviados->fetch()['total'];

$stmtRascunhos = $pdo->query("SELECT COUNT(*) as total FROM correspondencias WHERE status = 'rascunho'");
$totalRascunhos = $stmtRascunhos->fetch()['total'];

$stmtFalhas = $pdo->query("SELECT COUNT(*) as total FROM correspondencias WHERE status = 'falha'");
$totalFalhas = $stmtFalhas->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correspondência - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-enviado {
            background: #d1fae5;
            color: #065f46;
        }
        .status-rascunho {
            background: #fef3c7;
            color: #92400e;
        }
        .status-falha {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-entregue {
            background: #dbeafe;
            color: #1e40af;
        }
        .tipo-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .tipo-email {
            background: #dbeafe;
            color: #1e40af;
        }
        .tipo-whatsapp {
            background: #d1fae5;
            color: #065f46;
        }
        .tipo-sms {
            background: #fef3c7;
            color: #92400e;
        }
        .btn-reenviar {
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-reenviar:hover {
            background: #2563eb;
        }
        .correspondencia-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .correspondencia-card .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .correspondencia-card .destinatario {
            font-weight: 700;
            color: #1e293b;
            font-size: 16px;
        }
        .correspondencia-card .assunto {
            color: #64748b;
            font-size: 14px;
        }
        .correspondencia-card .footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .correspondencia-card .mensagem-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 6px;
            font-size: 14px;
            color: #475569;
            white-space: pre-line;
            max-height: 100px;
            overflow: hidden;
        }
        .btn-enviar-novo {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-enviar-novo:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>✉️ Correspondências</h2>
        
        <div class="actions">
            <a href="enviar.php" class="btn-enviar-novo">📤 Nova Mensagem</a>
        </div>
        
        <!-- Cards de Resumo -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin: 20px 0;">
            <div style="background: #10b981; color: white; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="font-size: 24px;"><?= $totalEnviados ?></h3>
                <p>Enviados</p>
            </div>
            <div style="background: #f59e0b; color: white; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="font-size: 24px;"><?= $totalRascunhos ?></h3>
                <p>Rascunhos</p>
            </div>
            <div style="background: #ef4444; color: white; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="font-size: 24px;"><?= $totalFalhas ?></h3>
                <p>Falhas</p>
            </div>
            <div style="background: #3b82f6; color: white; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="font-size: 24px;"><?= count($correspondencias) ?></h3>
                <p>Total</p>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Mensagem enviada com sucesso!</div>
        <?php endif; ?>
        
        <!-- Lista de Correspondências -->
        <?php if (count($correspondencias) > 0): ?>
            <?php foreach($correspondencias as $corresp): ?>
                <div class="correspondencia-card">
                    <div class="header">
                        <div>
                            <span class="destinatario">👤 <?= htmlspecialchars($corresp['destinatario']) ?></span>
                            <span class="assunto"> - <?= htmlspecialchars($corresp['assunto']) ?></span>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span class="tipo-badge tipo-<?= $corresp['tipo'] ?>">
                                <?= $corresp['tipo'] == 'whatsapp' ? '💬' : ($corresp['tipo'] == 'email' ? '📧' : '📱') ?>
                                <?= ucfirst($corresp['tipo']) ?>
                            </span>
                            <span class="status-badge status-<?= $corresp['status'] ?>">
                                <?= ucfirst($corresp['status']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mensagem-preview">
                        <?= nl2br(htmlspecialchars(substr($corresp['mensagem'], 0, 200))) ?>
                        <?= strlen($corresp['mensagem']) > 200 ? '...' : '' ?>
                    </div>
                    
                    <div class="footer">
                        <div style="font-size: 13px; color: #94a3b8;">
                            📅 <?= date('d/m/Y H:i', strtotime($corresp['created_at'])) ?>
                            <?php if ($corresp['enviado_em']): ?>
                                | 📤 Enviado: <?= date('d/m/Y H:i', strtotime($corresp['enviado_em'])) ?>
                            <?php endif; ?>
                            <?php if ($corresp['mensagem_erro']): ?>
                                | ❌ <?= htmlspecialchars($corresp['mensagem_erro']) ?>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <?php if ($corresp['status'] == 'rascunho'): ?>
                                <a href="editar.php?id=<?= $corresp['id'] ?>" class="btn-small" style="background: #f59e0b;">Editar</a>
                            <?php endif; ?>
                            <?php if ($corresp['status'] == 'falha' || $corresp['status'] == 'rascunho'): ?>
                                <a href="reenviar.php?id=<?= $corresp['id'] ?>" class="btn-small" style="background: #3b82f6;">Reenviar</a>
                            <?php endif; ?>
                            <a href="excluir.php?id=<?= $corresp['id'] ?>" class="btn-small" style="background: #ef4444;" onclick="return confirm('Tem certeza que deseja excluir?')">Excluir</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px;">
                <p style="font-size: 64px; margin-bottom: 20px;">✉️</p>
                <h3 style="color: #64748b;">Nenhuma correspondência</h3>
                <p style="color: #94a3b8; margin-top: 10px;">Clique em "Nova Mensagem" para enviar sua primeira correspondência</p>
                <a href="enviar.php" class="btn btn-primary" style="margin-top: 20px;">📤 Nova Mensagem</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>