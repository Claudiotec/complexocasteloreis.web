<?php
// scan.php - Página que o QR Code vai abrir no celular
// =============================================
// SOLUÇÃO DEFINITIVA - FORÇAR HORA DE ANGOLA (UTC+1)
// =============================================

require_once '../../config/database.php';

// =============================================
// FUNÇÕES FORÇANDO UTC+1 (ANGOLA)
// =============================================

// Função que retorna a hora de Angola (UTC+1)
function getHoraAngola() {
    // Pega a hora UTC e adiciona 1 hora
    $hora_utc = gmdate('H:i:s');
    $timestamp = strtotime($hora_utc) + 3600; // +1 hora
    return date('H:i:s', $timestamp);
}

function getDataAngola() {
    // Pega a data UTC e adiciona 1 hora
    $timestamp = time() + 3600; // +1 hora
    return date('Y-m-d', $timestamp);
}

function getDataHoraAngola() {
    $timestamp = time() + 3600; // +1 hora
    return date('Y-m-d H:i:s', $timestamp);
}

// Função para obter ID único do dispositivo
function getDeviceId() {
    if (isset($_COOKIE['device_id'])) {
        return $_COOKIE['device_id'];
    }
    
    $device_id = bin2hex(random_bytes(32));
    setcookie('device_id', $device_id, time() + (365 * 24 * 60 * 60), '/');
    return $device_id;
}

// =============================================
// PROCESSAR AÇÕES
// =============================================

$action = $_GET['action'] ?? 'scan';
$device_id = getDeviceId();

// Processar marcação de presença via GET
if ($action === 'marcar' && isset($_GET['codigo'])) {
    $codigo = $_GET['codigo'];
    $data = getDataAngola();
    $hora_atual = getHoraAngola();
    $data_hora_completa = getDataHoraAngola();
    
    // Verificar se o código é válido
    if ($codigo === 'PRESENCA2026') {
        // Verificar se o dispositivo já está registrado
        $stmt = $pdo->prepare("SELECT funcionario_id FROM dispositivos_funcionarios WHERE device_id = ?");
        $stmt->execute([$device_id]);
        $dispositivo = $stmt->fetch();
        
        if ($dispositivo) {
            // Dispositivo já registrado
            $funcionario_id = $dispositivo['funcionario_id'];
            
            $stmt = $pdo->prepare("SELECT nome_completo FROM forca_trabalho WHERE id = ?");
            $stmt->execute([$funcionario_id]);
            $funcionario = $stmt->fetch();
            
            if ($funcionario) {
                // Verificar presença hoje
                $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$funcionario_id, $data]);
                $presenca = $stmt->fetch();
                
                if ($presenca) {
                    // Já existe registro hoje
                    if ($presenca['status'] === 'presente' && empty($presenca['hora_saida'])) {
                        // MARCAR SAÍDA
                        $entrada = new DateTime($presenca['hora_entrada']);
                        $saida = new DateTime($hora_atual);
                        $intervalo = $entrada->diff($saida);
                        $horas = $intervalo->h + ($intervalo->i / 60);
                        
                        $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                            hora_saida = ?, 
                            horas_trabalhadas = ?,
                            status = 'presente',
                            qr_scanned = 1,
                            qr_scan_time = NOW(),
                            device_id = ?,
                            updated_at = NOW()
                            WHERE id = ?");
                        $stmt->execute([$hora_atual, $horas, $device_id, $presenca['id']]);
                        
                        $mensagem = "✅ SAÍDA registrada às " . $hora_atual . " (Horas: " . number_format($horas, 2) . "h)";
                        
                    } elseif ($presenca['status'] === 'presente' && !empty($presenca['hora_saida'])) {
                        // Já tem entrada e saída - criar nova entrada
                        try {
                            $stmt = $pdo->prepare("INSERT INTO presenca_qr 
                                (funcionario_id, data, hora_entrada, status, qr_scanned, qr_scan_time, device_id, scan_type, created_at) 
                                VALUES (?, ?, ?, 'presente', 1, NOW(), ?, 'diario', NOW())");
                            $stmt->execute([$funcionario_id, $data, $hora_atual, $device_id]);
                            $mensagem = "✅ NOVA ENTRADA registrada às " . $hora_atual;
                        } catch (PDOException $e) {
                            if ($e->errorInfo[1] == 1062) {
                                $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                                    hora_entrada = ?,
                                    hora_saida = NULL,
                                    horas_trabalhadas = NULL,
                                    status = 'presente',
                                    qr_scanned = 1,
                                    qr_scan_time = NOW(),
                                    device_id = ?,
                                    updated_at = NOW()
                                    WHERE funcionario_id = ? AND data = ?");
                                $stmt->execute([$hora_atual, $device_id, $funcionario_id, $data]);
                                $mensagem = "✅ NOVA ENTRADA registrada às " . $hora_atual;
                            }
                        }
                    } else {
                        // Atualizar para presente
                        $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                            hora_entrada = ?,
                            status = 'presente',
                            qr_scanned = 1,
                            qr_scan_time = NOW(),
                            device_id = ?,
                            updated_at = NOW()
                            WHERE id = ?");
                        $stmt->execute([$hora_atual, $device_id, $presenca['id']]);
                        $mensagem = "✅ ENTRADA registrada às " . $hora_atual;
                    }
                } else {
                    // Não existe registro - criar novo (ENTRADA)
                    try {
                        $stmt = $pdo->prepare("INSERT INTO presenca_qr 
                            (funcionario_id, data, hora_entrada, status, qr_scanned, qr_scan_time, device_id, scan_type, created_at) 
                            VALUES (?, ?, ?, 'presente', 1, NOW(), ?, 'diario', NOW())");
                        $stmt->execute([$funcionario_id, $data, $hora_atual, $device_id]);
                        $mensagem = "✅ ENTRADA registrada às " . $hora_atual;
                    } catch (PDOException $e) {
                        if ($e->errorInfo[1] == 1062) {
                            $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                                hora_entrada = ?,
                                status = 'presente',
                                qr_scanned = 1,
                                qr_scan_time = NOW(),
                                device_id = ?,
                                updated_at = NOW()
                                WHERE funcionario_id = ? AND data = ?");
                            $stmt->execute([$hora_atual, $device_id, $funcionario_id, $data]);
                            $mensagem = "✅ ENTRADA registrada às " . $hora_atual;
                        } else {
                            throw $e;
                        }
                    }
                }
                
                // Atualizar último acesso do dispositivo
                $stmt = $pdo->prepare("UPDATE dispositivos_funcionarios SET ultimo_acesso = NOW() WHERE device_id = ?");
                $stmt->execute([$device_id]);
                
                header('Location: scan.php?action=success&msg=' . urlencode($mensagem));
                exit;
            }
        } else {
            // Primeiro scan - redirecionar para registro
            header('Location: scan.php?action=registrar');
            exit;
        }
    } else {
        header('Location: scan.php?action=error&msg=' . urlencode('❌ Código inválido!'));
        exit;
    }
}

// Processar confirmação de nova entrada
if ($action === 'confirmar_nova' && isset($_GET['confirmar']) && $_GET['confirmar'] === 'sim') {
    $funcionario_id = null;
    $stmt = $pdo->prepare("SELECT funcionario_id FROM dispositivos_funcionarios WHERE device_id = ?");
    $stmt->execute([$device_id]);
    $dispositivo = $stmt->fetch();
    
    if ($dispositivo) {
        $funcionario_id = $dispositivo['funcionario_id'];
        $data = getDataAngola();
        $hora_atual = getHoraAngola();
        
        // Verificar se já existe registro hoje
        $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ?");
        $stmt->execute([$funcionario_id, $data]);
        $presenca = $stmt->fetch();
        
        if ($presenca) {
            // Criar novo registro para hoje (nova entrada)
            try {
                $stmt = $pdo->prepare("INSERT INTO presenca_qr 
                    (funcionario_id, data, hora_entrada, status, qr_scanned, qr_scan_time, device_id, scan_type, created_at) 
                    VALUES (?, ?, ?, 'presente', 1, NOW(), ?, 'diario', NOW())");
                $stmt->execute([$funcionario_id, $data, $hora_atual, $device_id]);
                $mensagem = "✅ NOVA ENTRADA registrada às " . $hora_atual;
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                        hora_entrada = ?,
                        hora_saida = NULL,
                        horas_trabalhadas = NULL,
                        status = 'presente',
                        qr_scanned = 1,
                        qr_scan_time = NOW(),
                        device_id = ?,
                        updated_at = NOW()
                        WHERE funcionario_id = ? AND data = ?");
                    $stmt->execute([$hora_atual, $device_id, $funcionario_id, $data]);
                    $mensagem = "✅ NOVA ENTRADA registrada às " . $hora_atual;
                }
            }
            
            header('Location: scan.php?action=success&msg=' . urlencode($mensagem));
            exit;
        }
    }
}

// Processar registro do funcionário (primeiro acesso)
if ($action === 'registrar' && isset($_POST['nome_funcionario'])) {
    $nome_funcionario = trim($_POST['nome_funcionario']);
    $device_id = getDeviceId();
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $data = getDataAngola();
    $hora_atual = getHoraAngola();
    
    if (!empty($nome_funcionario)) {
        // Buscar funcionário pelo nome
        $stmt = $pdo->prepare("SELECT id, nome_completo FROM forca_trabalho WHERE nome_completo LIKE ? AND status = 'ativo'");
        $stmt->execute(['%' . $nome_funcionario . '%']);
        $funcionarios = $stmt->fetchAll();
        
        if (count($funcionarios) === 1) {
            $funcionario = $funcionarios[0];
            
            try {
                // Registrar dispositivo
                $stmt = $pdo->prepare("INSERT INTO dispositivos_funcionarios 
                    (funcionario_id, device_id, user_agent, ip_address, primeira_vez, ultimo_acesso, created_at) 
                    VALUES (?, ?, ?, ?, 0, NOW(), NOW())");
                $stmt->execute([$funcionario['id'], $device_id, $user_agent, $ip_address]);
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $stmt = $pdo->prepare("UPDATE dispositivos_funcionarios SET 
                        user_agent = ?, ip_address = ?, ultimo_acesso = NOW()
                        WHERE device_id = ?");
                    $stmt->execute([$user_agent, $ip_address, $device_id]);
                }
            }
            
            // Verificar se já existe presença hoje
            $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ?");
            $stmt->execute([$funcionario['id'], $data]);
            $presenca = $stmt->fetch();
            
            if (!$presenca) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO presenca_qr 
                        (funcionario_id, data, hora_entrada, status, qr_scanned, qr_scan_time, device_id, scan_type, created_at) 
                        VALUES (?, ?, ?, 'presente', 1, NOW(), ?, 'primeiro', NOW())");
                    $stmt->execute([$funcionario['id'], $data, $hora_atual, $device_id]);
                } catch (PDOException $e) {
                    if ($e->errorInfo[1] == 1062) {
                        $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                            hora_entrada = ?,
                            status = 'presente',
                            qr_scanned = 1,
                            qr_scan_time = NOW(),
                            device_id = ?,
                            updated_at = NOW()
                            WHERE funcionario_id = ? AND data = ?");
                        $stmt->execute([$hora_atual, $device_id, $funcionario['id'], $data]);
                    }
                }
            }
            
            $mensagem = "✅ Dispositivo registrado para " . $funcionario['nome_completo'] . "! Entrada às " . $hora_atual;
            header('Location: scan.php?action=success&msg=' . urlencode($mensagem));
            exit;
            
        } elseif (count($funcionarios) > 1) {
            $mensagem = "⚠️ Encontramos " . count($funcionarios) . " funcionários com este nome. Por favor, informe o nome completo:";
            header('Location: scan.php?action=registrar&error=' . urlencode($mensagem));
            exit;
        } else {
            $mensagem = "❌ Nenhum funcionário encontrado com este nome. Tente novamente.";
            header('Location: scan.php?action=registrar&error=' . urlencode($mensagem));
            exit;
        }
    }
}

// =============================================
// VERIFICAR STATUS DO DISPOSITIVO
// =============================================

$dispositivo_registrado = false;
$funcionario_nome = '';
$funcionario_id = null;

$stmt = $pdo->prepare("SELECT d.funcionario_id, f.nome_completo FROM dispositivos_funcionarios d 
    JOIN forca_trabalho f ON d.funcionario_id = f.id 
    WHERE d.device_id = ?");
$stmt->execute([$device_id]);
$dispositivo = $stmt->fetch();

if ($dispositivo) {
    $dispositivo_registrado = true;
    $funcionario_nome = $dispositivo['nome_completo'];
    $funcionario_id = $dispositivo['funcionario_id'];
}

// Buscar presença de hoje se estiver registrado
$presenca_hoje = null;
if ($funcionario_id) {
    $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$funcionario_id, getDataAngola()]);
    $presenca_hoje = $stmt->fetch();
}

// Hora atual de Angola para o relógio
$hora_atual_angola = getHoraAngola();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Presença - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a2332, #2d3748);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        
        .logo { font-size: 48px; margin-bottom: 10px; }
        h1 { color: #1a2332; font-size: 22px; margin-bottom: 5px; }
        .subtitle { color: #64748b; font-size: 14px; margin-bottom: 20px; }
        
        .relogio {
            font-size: 32px;
            font-weight: 700;
            color: #c9a84c;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 15px;
            font-family: 'Courier New', monospace;
        }
        
        .status-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
        }
        
        .status-box .icon { font-size: 48px; display: block; margin-bottom: 10px; }
        .status-box .mensagem { font-size: 16px; color: #1a2332; font-weight: 500; }
        .status-box .detalhe { font-size: 13px; color: #64748b; margin-top: 5px; }
        
        .btn {
            display: inline-block;
            padding: 14px 30px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3); }
        .btn:active { transform: scale(0.98); }
        
        .btn-secondary { background: #eef2f7; color: #1a2332; }
        .btn-secondary:hover { background: #e2e8f0; }
        
        .btn-entrada { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-entrada:hover { box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        
        .btn-saida { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn-saida:hover { box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3); }
        
        .input-field {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .input-field:focus { border-color: #c9a84c; outline: none; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        
        .info-text { color: #94a3b8; font-size: 12px; margin-top: 15px; }
        .device-info { font-size: 11px; color: #94a3b8; margin-top: 10px; word-break: break-all; }
        
        .presenca-info {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            text-align: left;
        }
        .presenca-info .linha {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
            border-bottom: 1px solid #eef2f7;
        }
        .presenca-info .linha:last-child { border-bottom: none; }
        .presenca-info .label { color: #64748b; }
        .presenca-info .valor { font-weight: 600; color: #1a2332; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.presente { background: #d1fae5; color: #065f46; }
        .status-badge.ausente { background: #fee2e2; color: #991b1b; }
        
        .btn-group { display: flex; gap: 10px; margin: 10px 0; }
        .btn-group .btn { flex: 1; }
        
        .hora-destaque { font-size: 20px; font-weight: 700; color: #c9a84c; }
        
        @media (max-width: 480px) {
            .container { padding: 20px; }
            h1 { font-size: 20px; }
            .btn-group { flex-direction: column; }
            .relogio { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">📱</div>
        <h1>Controle de Presença</h1>
        <p class="subtitle">SoftGest Web - RH</p>
        
        <!-- Relógio com hora de Angola (UTC+1) -->
        <div class="relogio" id="relogio"><?= $hora_atual_angola ?></div>
        
        <?php if ($action === 'success' && isset($_GET['msg'])): ?>
            <div class="status-box">
                <div class="icon">✅</div>
                <div class="mensagem"><?= htmlspecialchars($_GET['msg']) ?></div>
                <div class="detalhe">Data: <?= getDataHoraAngola() ?></div>
            </div>
            <a href="scan.php" class="btn">🔙 Voltar</a>
            
        <?php elseif ($action === 'confirmar_nova' && isset($_GET['msg'])): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($_GET['msg']) ?></div>
            <div class="btn-group">
                <a href="scan.php?action=confirmar_nova&confirmar=sim" class="btn btn-entrada">✅ Sim, nova entrada</a>
                <a href="scan.php" class="btn btn-secondary">❌ Não, voltar</a>
            </div>
            
        <?php elseif ($action === 'error' && isset($_GET['msg'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_GET['msg']) ?></div>
            <a href="scan.php" class="btn">🔙 Voltar</a>
            
        <?php elseif ($action === 'registrar'): ?>
            <div class="status-box">
                <div class="icon">🆕</div>
                <div class="mensagem">Primeiro Acesso</div>
                <div class="detalhe">Informe seu nome completo para registrar o dispositivo</div>
            </div>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-warning"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="scan.php?action=registrar">
                <input type="text" name="nome_funcionario" class="input-field" 
                       placeholder="Digite seu nome completo..." required autofocus>
                <button type="submit" class="btn">✅ Registrar Dispositivo</button>
            </form>
            <a href="scan.php" class="btn btn-secondary" style="margin-top: 10px;">Cancelar</a>
            
        <?php else: ?>
            <!-- Página principal de scan -->
            <?php if ($dispositivo_registrado): ?>
                <div class="status-box" style="background: #d1fae5; border: 2px solid #a7f3d0;">
                    <div class="icon">✅</div>
                    <div class="mensagem">Dispositivo Registrado</div>
                    <div class="detalhe">Funcionário: <?= htmlspecialchars($funcionario_nome) ?></div>
                </div>
                
                <!-- Informações de Presença Hoje -->
                <?php if ($presenca_hoje): ?>
                <div class="presenca-info">
                    <div class="linha">
                        <span class="label">📅 Data</span>
                        <span class="valor"><?= date('d/m/Y', strtotime($presenca_hoje['data'])) ?></span>
                    </div>
                    <div class="linha">
                        <span class="label">⏰ Entrada</span>
                        <span class="valor hora-destaque"><?= $presenca_hoje['hora_entrada'] ? date('H:i:s', strtotime($presenca_hoje['hora_entrada'])) : '—' ?></span>
                    </div>
                    <?php if ($presenca_hoje['hora_saida']): ?>
                    <div class="linha">
                        <span class="label">⏰ Saída</span>
                        <span class="valor hora-destaque"><?= date('H:i:s', strtotime($presenca_hoje['hora_saida'])) ?></span>
                    </div>
                    <div class="linha">
                        <span class="label">⏱️ Horas</span>
                        <span class="valor"><?= number_format($presenca_hoje['horas_trabalhadas'], 2, ',', '.') ?>h</span>
                    </div>
                    <?php else: ?>
                    <div class="linha" style="color: #f59e0b;">
                        <span class="label">⏰ Saída</span>
                        <span class="valor" style="color: #f59e0b;">⏳ Pendente</span>
                    </div>
                    <?php endif; ?>
                    <div class="linha">
                        <span class="label">📌 Status</span>
                        <span class="valor">
                            <span class="status-badge <?= $presenca_hoje['status'] ?>">
                                <?= ucfirst($presenca_hoje['status']) ?>
                            </span>
                        </span>
                    </div>
                </div>
                
                <!-- Botões de Ação -->
                <div class="btn-group">
                    <?php if ($presenca_hoje['status'] === 'presente' && empty($presenca_hoje['hora_saida'])): ?>
                        <a href="scan.php?action=marcar&codigo=PRESENCA2026" class="btn btn-saida">🚪 Marcar SAÍDA</a>
                    <?php elseif ($presenca_hoje['status'] === 'presente' && !empty($presenca_hoje['hora_saida'])): ?>
                        <a href="scan.php?action=marcar&codigo=PRESENCA2026" class="btn btn-entrada">📌 Nova ENTRADA</a>
                    <?php else: ?>
                        <a href="scan.php?action=marcar&codigo=PRESENCA2026" class="btn btn-entrada">📌 Marcar ENTRADA</a>
                    <?php endif; ?>
                </div>
                
                <?php else: ?>
                <div class="alert alert-info">ℹ️ Nenhuma presença registrada hoje.</div>
                <a href="scan.php?action=marcar&codigo=PRESENCA2026" class="btn btn-entrada">📌 Marcar ENTRADA</a>
                <?php endif; ?>
                
                <div class="device-info">ID: <?= substr($device_id, 0, 16) ?>...</div>
                
            <?php else: ?>
                <div class="status-box" style="background: #fef3c7; border: 2px solid #fde68a;">
                    <div class="icon">📱</div>
                    <div class="mensagem">Dispositivo não registrado</div>
                    <div class="detalhe">Escaneie o QR Code e informe seu nome</div>
                </div>
                <a href="scan.php?action=registrar" class="btn">🆕 Registrar Dispositivo</a>
                <div class="device-info">ID: <?= substr($device_id, 0, 16) ?>...</div>
            <?php endif; ?>
            
            <div class="info-text">🔒 Sistema seguro - SoftGest Web</div>
        <?php endif; ?>
    </div>
    
    <script>
        // =============================================
        // RELÓGIO COM HORA DE ANGOLA (UTC+1)
        // =============================================
        
        // Hora inicial de Angola vinda do servidor
        var horaAngola = '<?= $hora_atual_angola ?>';
        var partes = horaAngola.split(':');
        var horas = parseInt(partes[0]);
        var minutos = parseInt(partes[1]);
        var segundos = parseInt(partes[2]);
        
        function atualizarRelogio() {
            // Incrementar segundos
            segundos++;
            if (segundos >= 60) {
                segundos = 0;
                minutos++;
                if (minutos >= 60) {
                    minutos = 0;
                    horas++;
                    if (horas >= 24) {
                        horas = 0;
                    }
                }
            }
            
            // Formatar
            var horaStr = String(horas).padStart(2, '0');
            var minStr = String(minutos).padStart(2, '0');
            var segStr = String(segundos).padStart(2, '0');
            
            var relogio = document.getElementById('relogio');
            if (relogio) {
                relogio.textContent = horaStr + ':' + minStr + ':' + segStr;
            }
        }
        
        // Atualizar a cada segundo
        setInterval(atualizarRelogio, 1000);
        atualizarRelogio();
        
        console.log('Hora de Angola: <?= $hora_atual_angola ?>');
    </script>
</body>
</html>