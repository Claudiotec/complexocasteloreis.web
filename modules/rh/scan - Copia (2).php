<?php
// scan.php - Página que o QR Code vai abrir no celular
// =============================================
// 1º SCAN = ENTRADA | 2º SCAN = SAÍDA (FECHA) | 3º SCAN = BLOQUEADO
// =============================================

require_once '../../config/database.php';

// =============================================
// FUNÇÕES DE DATA/HORA
// =============================================

function getHoraAngola() {
    $hora_utc = gmdate('H:i:s');
    $timestamp = strtotime($hora_utc) + 3600;
    return date('H:i:s', $timestamp);
}

function getDataAngola() {
    $timestamp = time() + 3600;
    return date('Y-m-d', $timestamp);
}

function getDataHoraAngola() {
    $timestamp = time() + 3600;
    return date('Y-m-d H:i:s', $timestamp);
}

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

// Processar marcação de presença via GET (SÓ POR QR CODE)
if ($action === 'marcar' && isset($_GET['codigo'])) {
    $codigo = $_GET['codigo'];
    $data = getDataAngola();
    $hora_atual = getHoraAngola();
    
    if ($codigo === 'PRESENCA2026') {
        // Verificar se o dispositivo já está registrado
        $stmt = $pdo->prepare("SELECT funcionario_id FROM dispositivos_funcionarios WHERE device_id = ?");
        $stmt->execute([$device_id]);
        $dispositivo = $stmt->fetch();
        
        if ($dispositivo) {
            $funcionario_id = $dispositivo['funcionario_id'];
            
            $stmt = $pdo->prepare("SELECT nome FROM funcionarios WHERE id = ? AND status = 'ativo'");
            $stmt->execute([$funcionario_id]);
            $funcionario = $stmt->fetch();
            
            if ($funcionario) {
                // Verificar presença hoje
                $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$funcionario_id, $data]);
                $presenca = $stmt->fetch();
                
                // =============================================
                // CASO 1: NENHUM REGISTRO → ENTRADA
                // =============================================
                if (!$presenca) {
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
                    
                    header('Location: scan.php?action=success&msg=' . urlencode($mensagem));
                    exit;
                }
                
                // =============================================
                // CASO 2: JÁ FECHADO → BLOQUEAR
                // =============================================
                if ($presenca['status'] === 'fechado') {
                    $mensagem = "⛔ PONTO JÁ FECHADO HOJE! Saída às " . date('H:i:s', strtotime($presenca['hora_saida']));
                    header('Location: scan.php?action=error&msg=' . urlencode($mensagem));
                    exit;
                }
                
                // =============================================
                // CASO 3: TEM ENTRADA, NÃO TEM SAÍDA → SAÍDA (FECHA)
                // =============================================
                if ($presenca['status'] === 'presente' && empty($presenca['hora_saida'])) {
                    // Calcular horas trabalhadas
                    $entrada = new DateTime($presenca['hora_entrada']);
                    $saida = new DateTime($hora_atual);
                    $intervalo = $entrada->diff($saida);
                    $horas = $intervalo->h + ($intervalo->i / 60);
                    
                    // Atualizar com SAÍDA e FECHAR
                    $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                        hora_saida = ?, 
                        horas_trabalhadas = ?,
                        status = 'fechado',
                        qr_scanned = 1,
                        qr_scan_time = NOW(),
                        device_id = ?,
                        updated_at = NOW()
                        WHERE id = ?");
                    $stmt->execute([$hora_atual, $horas, $device_id, $presenca['id']]);
                    
                    $mensagem = "✅ PONTO FECHADO! Saída às " . $hora_atual . " (Horas: " . number_format($horas, 2) . "h)";
                    
                    // Atualizar último acesso
                    $stmt = $pdo->prepare("UPDATE dispositivos_funcionarios SET ultimo_acesso = NOW() WHERE device_id = ?");
                    $stmt->execute([$device_id]);
                    
                    header('Location: scan.php?action=success&msg=' . urlencode($mensagem));
                    exit;
                }
                
                // =============================================
                // CASO 4: TEM ENTRADA E SAÍDA, MAS NÃO FECHOU → CORRIGIR
                // =============================================
                if ($presenca['status'] === 'presente' && !empty($presenca['hora_saida'])) {
                    $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                        status = 'fechado',
                        updated_at = NOW()
                        WHERE id = ?");
                    $stmt->execute([$presenca['id']]);
                    
                    $mensagem = "✅ PONTO FECHADO!";
                    header('Location: scan.php?action=success&msg=' . urlencode($mensagem));
                    exit;
                }
                
                // =============================================
                // CASO 5: QUALQUER OUTRO STATUS → FORÇAR SAÍDA
                // =============================================
                // Se chegou aqui, algo está errado. Vamos forçar a saída.
                if ($presenca['status'] !== 'fechado') {
                    // Calcular horas trabalhadas
                    $entrada = new DateTime($presenca['hora_entrada']);
                    $saida = new DateTime($hora_atual);
                    $intervalo = $entrada->diff($saida);
                    $horas = $intervalo->h + ($intervalo->i / 60);
                    
                    $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                        hora_saida = ?, 
                        horas_trabalhadas = ?,
                        status = 'fechado',
                        qr_scanned = 1,
                        qr_scan_time = NOW(),
                        device_id = ?,
                        updated_at = NOW()
                        WHERE id = ?");
                    $stmt->execute([$hora_atual, $horas, $device_id, $presenca['id']]);
                    
                    $mensagem = "✅ PONTO FECHADO! Saída às " . $hora_atual . " (Horas: " . number_format($horas, 2) . "h)";
                    
                    header('Location: scan.php?action=success&msg=' . urlencode($mensagem));
                    exit;
                }
                
            } else {
                $mensagem = "❌ Funcionário não encontrado ou inativo!";
                header('Location: scan.php?action=error&msg=' . urlencode($mensagem));
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

// Processar registro do funcionário
if ($action === 'registrar' && isset($_POST['nome_funcionario'])) {
    $nome_funcionario = trim($_POST['nome_funcionario']);
    $device_id = getDeviceId();
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $data = getDataAngola();
    $hora_atual = getHoraAngola();
    
    if (!empty($nome_funcionario)) {
        $stmt = $pdo->prepare("SELECT id, nome FROM funcionarios WHERE nome LIKE ? AND status = 'ativo'");
        $stmt->execute(['%' . $nome_funcionario . '%']);
        $funcionarios = $stmt->fetchAll();
        
        if (count($funcionarios) === 1) {
            $funcionario = $funcionarios[0];
            
            try {
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
            
            $mensagem = "✅ Dispositivo registrado para " . $funcionario['nome'] . "! Entrada às " . $hora_atual;
            header('Location: scan.php?action=success&msg=' . urlencode($mensagem));
            exit;
            
        } elseif (count($funcionarios) > 1) {
            $mensagem = "⚠️ Encontramos " . count($funcionarios) . " funcionários com este nome. Informe o nome completo:";
            header('Location: scan.php?action=registrar&error=' . urlencode($mensagem));
            exit;
        } else {
            $mensagem = "❌ Nenhum funcionário ativo encontrado com este nome.";
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

$stmt = $pdo->prepare("SELECT d.funcionario_id, f.nome FROM dispositivos_funcionarios d 
    JOIN funcionarios f ON d.funcionario_id = f.id 
    WHERE d.device_id = ?");
$stmt->execute([$device_id]);
$dispositivo = $stmt->fetch();

if ($dispositivo) {
    $dispositivo_registrado = true;
    $funcionario_nome = $dispositivo['nome'];
    $funcionario_id = $dispositivo['funcionario_id'];
}

$presenca_hoje = null;
$ponto_fechado = false;
$tem_entrada = false;
$tem_saida = false;

if ($funcionario_id) {
    $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$funcionario_id, getDataAngola()]);
    $presenca_hoje = $stmt->fetch();
    
    if ($presenca_hoje) {
        if ($presenca_hoje['status'] === 'fechado') {
            $ponto_fechado = true;
        }
        if (!empty($presenca_hoje['hora_entrada'])) {
            $tem_entrada = true;
        }
        if (!empty($presenca_hoje['hora_saida'])) {
            $tem_saida = true;
        }
    }
}

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
        
        .status-box.fechado { 
            background: #fef3c7; 
            border: 2px solid #fde68a; 
        }
        .status-box.fechado .mensagem { 
            color: #92400e; 
        }
        
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
        .status-badge.fechado { background: #fef3c7; color: #92400e; }
        .status-badge.saida { background: #dbeafe; color: #1e40af; }
        
        .hora-destaque { font-size: 20px; font-weight: 700; color: #c9a84c; }
        
        .instrucao-scan {
            background: #f0f7ff;
            border: 2px dashed #c9a84c;
            border-radius: 12px;
            padding: 15px;
            margin: 10px 0;
            text-align: center;
        }
        .instrucao-scan .icone-qr {
            font-size: 48px;
            display: block;
            margin-bottom: 5px;
        }
        .instrucao-scan .texto {
            font-size: 14px;
            color: #1a2332;
            font-weight: 500;
        }
        .instrucao-scan .sub {
            font-size: 12px;
            color: #64748b;
        }
        
        .instrucao-scan.pendente {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        
        .instrucao-scan.fechado {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .instrucao-scan.sucesso {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        @media (max-width: 480px) {
            .container { padding: 20px; }
            h1 { font-size: 20px; }
            .relogio { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">📱</div>
        <h1>Controle de Presença</h1>
        <p class="subtitle">SoftGest Web - RH</p>
        
        <div class="relogio" id="relogio"><?= $hora_atual_angola ?></div>
        
        <?php if ($action === 'success' && isset($_GET['msg'])): ?>
            <div class="status-box">
                <div class="icon">✅</div>
                <div class="mensagem"><?= htmlspecialchars($_GET['msg']) ?></div>
                <div class="detalhe">Data: <?= getDataHoraAngola() ?></div>
            </div>
            
            <div class="instrucao-scan">
                <span class="icone-qr">📷</span>
                <div class="texto">Escaneie o QR Code novamente para continuar</div>
                <div class="sub">Aponte a câmera para o QR Code</div>
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
            <!-- Página principal -->
            <?php if ($dispositivo_registrado): ?>
                
                <?php if ($ponto_fechado): ?>
                <!-- PONTO FECHADO -->
                <div class="status-box fechado">
                    <div class="icon">🔒</div>
                    <div class="mensagem">PONTO FECHADO</div>
                    <div class="detalhe">Jornada finalizada hoje</div>
                </div>
                
                <div class="presenca-info">
                    <div class="linha">
                        <span class="label">👤 Funcionário</span>
                        <span class="valor"><?= htmlspecialchars($funcionario_nome) ?></span>
                    </div>
                    <div class="linha">
                        <span class="label">📅 Data</span>
                        <span class="valor"><?= date('d/m/Y', strtotime($presenca_hoje['data'])) ?></span>
                    </div>
                    <div class="linha">
                        <span class="label">⏰ Entrada</span>
                        <span class="valor"><?= date('H:i:s', strtotime($presenca_hoje['hora_entrada'])) ?></span>
                    </div>
                    <div class="linha">
                        <span class="label">⏰ Saída</span>
                        <span class="valor"><?= date('H:i:s', strtotime($presenca_hoje['hora_saida'])) ?></span>
                    </div>
                    <div class="linha">
                        <span class="label">⏱️ Horas</span>
                        <span class="valor"><?= number_format($presenca_hoje['horas_trabalhadas'], 2, ',', '.') ?>h</span>
                    </div>
                    <div class="linha">
                        <span class="label">📌 Status</span>
                        <span class="valor">
                            <span class="status-badge fechado">✅ Ponto Feito</span>
                        </span>
                    </div>
                </div>
                
                <div class="instrucao-scan fechado">
                    <span class="icone-qr">⛔</span>
                    <div class="texto">Ponto já fechado hoje!</div>
                    <div class="sub">Não é possível registrar mais hoje</div>
                </div>
                
                <?php elseif ($tem_entrada && !$tem_saida): ?>
                <!-- TEM ENTRADA, NÃO TEM SAÍDA -->
                <div class="status-box" style="background: #fef3c7; border: 2px solid #fde68a;">
                    <div class="icon">⏳</div>
                    <div class="mensagem">Aguardando Saída</div>
                    <div class="detalhe"><?= htmlspecialchars($funcionario_nome) ?></div>
                </div>
                
                <div class="presenca-info">
                    <div class="linha">
                        <span class="label">📅 Data</span>
                        <span class="valor"><?= date('d/m/Y', strtotime($presenca_hoje['data'])) ?></span>
                    </div>
                    <div class="linha">
                        <span class="label">⏰ Entrada</span>
                        <span class="valor hora-destaque"><?= date('H:i:s', strtotime($presenca_hoje['hora_entrada'])) ?></span>
                    </div>
                    <div class="linha" style="color: #f59e0b;">
                        <span class="label">⏰ Saída</span>
                        <span class="valor" style="color: #f59e0b;">⏳ Pendente</span>
                    </div>
                    <div class="linha">
                        <span class="label">📌 Status</span>
                        <span class="valor">
                            <span class="status-badge presente">⏳ Aguardando Saída</span>
                        </span>
                    </div>
                </div>
                
                <div class="instrucao-scan pendente">
                    <span class="icone-qr">📷</span>
                    <div class="texto">🔴 ESCANEIE O QR CODE PARA REGISTRAR A SAÍDA</div>
                    <div class="sub">Aponte a câmera para o QR Code para fechar o ponto</div>
                </div>
                
                <?php elseif ($tem_entrada && $tem_saida): ?>
                <!-- TEM ENTRADA E SAÍDA, MAS NÃO FECHOU -->
                <div class="status-box" style="background: #dbeafe; border: 2px solid #bfdbfe;">
                    <div class="icon">✅</div>
                    <div class="mensagem">Ponto com Entrada e Saída</div>
                    <div class="detalhe"><?= htmlspecialchars($funcionario_nome) ?></div>
                </div>
                
                <div class="presenca-info">
                    <div class="linha">
                        <span class="label">📅 Data</span>
                        <span class="valor"><?= date('d/m/Y', strtotime($presenca_hoje['data'])) ?></span>
                    </div>
                    <div class="linha">
                        <span class="label">⏰ Entrada</span>
                        <span class="valor"><?= date('H:i:s', strtotime($presenca_hoje['hora_entrada'])) ?></span>
                    </div>
                    <div class="linha">
                        <span class="label">⏰ Saída</span>
                        <span class="valor"><?= date('H:i:s', strtotime($presenca_hoje['hora_saida'])) ?></span>
                    </div>
                    <div class="linha">
                        <span class="label">⏱️ Horas</span>
                        <span class="valor"><?= number_format($presenca_hoje['horas_trabalhadas'], 2, ',', '.') ?>h</span>
                    </div>
                    <div class="linha">
                        <span class="label">📌 Status</span>
                        <span class="valor">
                            <span class="status-badge saida">✅ Ponto Feito</span>
                        </span>
                    </div>
                </div>
                
                <div class="instrucao-scan sucesso">
                    <span class="icone-qr">📷</span>
                    <div class="texto">Escaneie o QR Code para FECHAR o ponto</div>
                    <div class="sub">Confirme a saída escaneando o QR Code</div>
                </div>
                
                <?php else: ?>
                <!-- NENHUM REGISTRO -->
                <div class="status-box" style="background: #d1fae5; border: 2px solid #a7f3d0;">
                    <div class="icon">✅</div>
                    <div class="mensagem">Dispositivo Registrado</div>
                    <div class="detalhe"><?= htmlspecialchars($funcionario_nome) ?></div>
                </div>
                
                <div class="alert alert-info">ℹ️ Nenhuma presença registrada hoje.</div>
                
                <div class="instrucao-scan">
                    <span class="icone-qr">📷</span>
                    <div class="texto">🟢 ESCANEIE O QR CODE PARA REGISTRAR A ENTRADA</div>
                    <div class="sub">Aponte a câmera para o QR Code</div>
                </div>
                <?php endif; ?>
                
                <div class="device-info">📱 ID: <?= substr($device_id, 0, 16) ?>...</div>
                
            <?php else: ?>
                <div class="status-box" style="background: #fef3c7; border: 2px solid #fde68a;">
                    <div class="icon">📱</div>
                    <div class="mensagem">Dispositivo não registrado</div>
                    <div class="detalhe">Escaneie o QR Code e informe seu nome</div>
                </div>
                <a href="scan.php?action=registrar" class="btn">🆕 Registrar Dispositivo</a>
                <div class="device-info">📱 ID: <?= substr($device_id, 0, 16) ?>...</div>
            <?php endif; ?>
            
            <div class="info-text">🔒 Sistema seguro - SoftGest Web</div>
        <?php endif; ?>
    </div>
    
    <script>
        var horaAngola = '<?= $hora_atual_angola ?>';
        var partes = horaAngola.split(':');
        var horas = parseInt(partes[0]);
        var minutos = parseInt(partes[1]);
        var segundos = parseInt(partes[2]);
        
        function atualizarRelogio() {
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
            
            var horaStr = String(horas).padStart(2, '0');
            var minStr = String(minutos).padStart(2, '0');
            var segStr = String(segundos).padStart(2, '0');
            
            var relogio = document.getElementById('relogio');
            if (relogio) {
                relogio.textContent = horaStr + ':' + minStr + ':' + segStr;
            }
        }
        
        setInterval(atualizarRelogio, 1000);
        atualizarRelogio();
    </script>
</body>
</html>