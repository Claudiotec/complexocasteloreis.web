<?php
require_once '../config/database.php';
require_once '../config/security.php';

// Verificar se é admin
if (!isset($_SESSION['usuario_perfil']) || $_SESSION['usuario_perfil'] != 'admin') {
    header("Location: " . SITE_URL . "login.php");
    exit;
}

$mensagem = '';
$tipoMensagem = '';

// Ações
if (isset($_GET['acao'])) {
    switch ($_GET['acao']) {
        case 'gerar_hashes':
            $arquivos = [
                'index.php',
                'login.php',
                'registrar.php',
                'logout.php',
                'config/database.php',
                'config/security.php',
                'includes/header.php',
                'includes/footer.php'
            ];
            foreach ($arquivos as $arquivo) {
                armazenarHash($arquivo);
            }
            $mensagem = '✅ Hashes gerados com sucesso!';
            $tipoMensagem = 'success';
            break;
            
        case 'verificar':
            $resultado = verificarIntegridade();
            if ($resultado['status']) {
                $mensagem = '✅ Todos os arquivos estão íntegros!';
                $tipoMensagem = 'success';
            } else {
                $mensagem = '⚠️ Arquivos alterados: ' . implode(', ', $resultado['alteracoes']);
                $tipoMensagem = 'error';
            }
            break;
            
        case 'limpar_logs':
            file_put_contents('data/security.log', '');
            file_put_contents('data/suspeitas.log', '');
            file_put_contents('data/acessos.log', '');
            $mensagem = '✅ Logs limpos com sucesso!';
            $tipoMensagem = 'success';
            break;
    }
}

// Buscar logs
$logs = [];
if (file_exists('data/security.log')) {
    $logs = array_reverse(file('data/security.log'));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proteção - SoftGest Web</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .protect-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .protect-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .protect-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            text-align: center;
            transition: all 0.3s;
        }
        .protect-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        .protect-card .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .protect-card h3 {
            color: #1a2332;
            margin-bottom: 8px;
        }
        .protect-card p {
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .protect-card .status {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-ok { background: #d1fae5; color: #065f46; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-danger { background: #fee2e2; color: #991b1b; }
        
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(197,165,50,0.3);
        }
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .log-list {
            background: #1a2332;
            color: #a8b2c1;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .log-list .log-item {
            padding: 4px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .log-list .log-item .time { color: #c9a84c; }
        .log-list .log-item .ip { color: #3498db; }
        .log-list .log-item .type { color: #2ecc71; }
        .log-list .log-item .detail { color: #94a3b8; }
        
        @media (max-width: 768px) {
            .protect-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="protect-container">
        <h2>🔒 Sistema de Proteção</h2>
        <p style="color: #94a3b8; margin-bottom: 20px;">Proteção contra alteração, modificação e cópia de arquivos</p>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>
        
        <!-- Cards -->
        <div class="protect-grid">
            <div class="protect-card">
                <div class="icon">🛡️</div>
                <h3>Integridade dos Arquivos</h3>
                <p>Verifica se os arquivos foram alterados</p>
                <a href="?acao=verificar" class="btn-gold">🔍 Verificar</a>
                <a href="?acao=gerar_hashes" class="btn-gold" style="background: #3498db; color: white;">🔄 Gerar Hashes</a>
            </div>
            
            <div class="protect-card">
                <div class="icon">📋</div>
                <h3>Logs de Segurança</h3>
                <p>Registro de atividades suspeitas</p>
                <a href="?acao=limpar_logs" class="btn-danger">🗑️ Limpar Logs</a>
                <a href="logs.php" class="btn-gold" style="background: #8b5cf6; color: white;">📄 Ver Logs</a>
            </div>
            
            <div class="protect-card">
                <div class="icon">🔐</div>
                <h3>Status do Sistema</h3>
                <p>Verificação de segurança em tempo real</p>
                <?php
                $integ = verificarIntegridade();
                $status = $integ['status'] ? 'ok' : 'warning';
                $statusText = $integ['status'] ? '✅ Sistema Seguro' : '⚠️ Arquivos Alterados';
                ?>
                <span class="status status-<?= $status ?>"><?= $statusText ?></span>
                <p style="margin-top: 10px; font-size: 12px;">
                    <?php if (!$integ['status']): ?>
                        Arquivos: <?= implode(', ', $integ['alteracoes']) ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="protect-card">
                <div class="icon">📊</div>
                <h3>Estatísticas</h3>
                <p>Dados de segurança</p>
                <?php
                $total_logs = file_exists('data/security.log') ? count(file('data/security.log')) : 0;
                $total_suspeitas = file_exists('data/suspeitas.log') ? count(file('data/suspeitas.log')) : 0;
                ?>
                <div style="text-align: left; font-size: 14px; color: #64748b;">
                    <p>📝 Logs: <strong><?= $total_logs ?></strong></p>
                    <p>⚠️ Suspeitas: <strong><?= $total_suspeitas ?></strong></p>
                    <p>📅 Última verificação: <strong><?= date('d/m/Y H:i') ?></strong></p>
                </div>
            </div>
        </div>
        
        <!-- Últimos Logs -->
        <h3 style="margin-top: 30px;">📋 Últimos Logs de Segurança</h3>
        <div class="log-list">
            <?php if (count($logs) > 0): ?>
                <?php for($i = 0; $i < min(20, count($logs)); $i++): 
                    $log = $logs[$i];
                    $parts = explode('|', $log);
                ?>
                <div class="log-item">
                    <span class="time"><?= trim($parts[0] ?? '') ?></span>
                    <span class="ip">| <?= trim($parts[1] ?? '') ?></span>
                    <span class="type">| <?= trim($parts[2] ?? '') ?></span>
                    <span class="detail">| <?= trim($parts[3] ?? '') ?></span>
                </div>
                <?php endfor; ?>
            <?php else: ?>
                <div class="log-item" style="color: #94a3b8;">Nenhum log registrado.</div>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="<?= SITE_URL ?>" class="btn">← Voltar ao Dashboard</a>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>