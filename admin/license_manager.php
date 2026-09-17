<?php
require_once '../config/database.php';
require_once '../config/license.php';

// Verificar se é admin
if (!isset($_SESSION['usuario_perfil']) || $_SESSION['usuario_perfil'] != 'admin') {
    header("Location: " . SITE_URL . "login.php");
    exit;
}

$mensagem = '';
$tipoMensagem = '';

// Obter informações da licença atual
$info_licenca = getInfoLicenca();
$dias_restantes = $info_licenca ? diasRestantesLicenca() : 0;

// Verificar licença
$verificacao = verificarLicenca();

// Ações
if (isset($_GET['acao'])) {
    switch ($_GET['acao']) {
        case 'gerar':
            // Redirecionar para o gerador Python
            $mensagem = 'Use o gerador Python para criar uma nova licença.';
            $tipoMensagem = 'info';
            break;
            
        case 'remover':
            if (file_exists(LICENSE_FILE)) {
                unlink(LICENSE_FILE);
                $mensagem = '✅ Licença removida com sucesso!';
                $tipoMensagem = 'success';
                $info_licenca = null;
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Licenças - SoftGest Web</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .license-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .license-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #c9a84c;
        }
        .license-card .status {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }
        .status-ok { background: #d1fae5; color: #065f46; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-error { background: #fee2e2; color: #991b1b; }
        .license-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }
        .license-item {
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #eef2f7;
        }
        .license-item .label {
            font-size: 11px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
        }
        .license-item .value {
            font-size: 15px;
            color: #1a2332;
            font-weight: 500;
            margin-top: 4px;
        }
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            padding: 10px 24px;
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
            padding: 10px 24px;
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
        .code-block {
            background: #1a2332;
            color: #a8b2c1;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 15px 0;
        }
        .python-commands {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin: 15px 0;
        }
        .python-commands code {
            display: block;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #1a2332;
            padding: 4px 0;
        }
        @media (max-width: 768px) {
            .license-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="license-container">
        <h2>🔐 Gerenciamento de Licenças</h2>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>
        
        <!-- Status da Licença -->
        <div class="license-card">
            <h3>📊 Status da Licença</h3>
            
            <?php if ($verificacao['status']): ?>
                <span class="status status-ok">✅ Licença Válida</span>
                <p style="color: #065f46; margin-top: 5px;"><?= $verificacao['mensagem'] ?></p>
            <?php else: ?>
                <span class="status status-error">❌ Licença Inválida</span>
                <p style="color: #991b1b; margin-top: 5px;"><?= $verificacao['mensagem'] ?></p>
            <?php endif; ?>
        </div>
        
        <?php if ($info_licenca): ?>
        <!-- Detalhes da Licença -->
        <div class="license-card">
            <h3>📋 Detalhes da Licença</h3>
            
            <div class="license-grid">
                <div class="license-item">
                    <div class="label">ID da Licença</div>
                    <div class="value"><?= htmlspecialchars($info_licenca['licenca_id']) ?></div>
                </div>
                <div class="license-item">
                    <div class="label">Cliente</div>
                    <div class="value"><?= htmlspecialchars($info_licenca['cliente']) ?></div>
                </div>
                <div class="license-item">
                    <div class="label">Data de Emissão</div>
                    <div class="value"><?= date('d/m/Y', strtotime($info_licenca['data_emissao'])) ?></div>
                </div>
                <div class="license-item">
                    <div class="label">Data de Expiração</div>
                    <div class="value">
                        <?= date('d/m/Y', strtotime($info_licenca['data_expiracao'])) ?>
                        <?php if ($dias_restantes > 0): ?>
                            <span style="font-size: 12px; color: <?= $dias_restantes <= 30 ? '#e74c3c' : '#2ecc71' ?>;">
                                (<?= $dias_restantes ?> dias restantes)
                            </span>
                        <?php else: ?>
                            <span style="color: #e74c3c; font-weight: 700;">(EXPIROU!)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="license-item">
                    <div class="label">Anos de Licença</div>
                    <div class="value"><?= $info_licenca['anos'] ?> ano(s)</div>
                </div>
                <div class="license-item">
                    <div class="label">Domínios Autorizados</div>
                    <div class="value"><?= implode(', ', $info_licenca['dominios']) ?></div>
                </div>
                <div class="license-item">
                    <div class="label">Status</div>
                    <div class="value">
                        <span style="color: <?= $info_licenca['status'] == 'ativo' ? '#2ecc71' : '#e74c3c' ?>;">
                            <?= ucfirst($info_licenca['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="?acao=remover" class="btn-danger" onclick="return confirm('Remover a licença atual?')">🗑️ Remover Licença</a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Gerador de Licença -->
        <div class="license-card">
            <h3>🛠️ Gerar Nova Licença</h3>
            <p style="color: #64748b; margin-bottom: 15px;">Use o script Python abaixo para gerar uma nova licença.</p>
            
            <div class="python-commands">
                <h4 style="margin-bottom: 10px;">📝 Comandos:</h4>
                <code>cd C:\xampp\htdocs\softgest_web\python</code>
                <code>python gerar_licenca.py --cliente "Nome do Cliente" --anos 1</code>
                <code style="color: #94a3b8; margin-top: 5px;"># Exemplo:</code>
                <code>python gerar_licenca.py --cliente "Empresa XPTO" --anos 2</code>
            </div>
            
            <p style="margin: 10px 0; font-size: 13px; color: #64748b;">
                Após gerar, copie o arquivo <strong>license.dat</strong> para a pasta:
                <code style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px;">C:\xampp\htdocs\softgest_web\license\</code>
            </p>
            
            <a href="../python/gerar_licenca.py" class="btn-gold" download>📥 Baixar Gerador Python</a>
        </div>
        
        <!-- Informações do Sistema -->
        <div class="license-card">
            <h3>💻 Informações do Sistema</h3>
            <div class="license-grid">
                <div class="license-item">
                    <div class="label">Domínio Atual</div>
                    <div class="value"><?= $_SERVER['HTTP_HOST'] ?></div>
                </div>
                <div class="license-item">
                    <div class="label">IP do Servidor</div>
                    <div class="value"><?= $_SERVER['SERVER_ADDR'] ?? 'N/A' ?></div>
                </div>
                <div class="license-item">
                    <div class="label">Sistema Operacional</div>
                    <div class="value"><?= php_uname('s') ?></div>
                </div>
                <div class="license-item">
                    <div class="label">Versão do PHP</div>
                    <div class="value"><?= phpversion() ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>