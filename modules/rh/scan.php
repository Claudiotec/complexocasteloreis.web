<?php
// scan.php - Página que o QR Code vai abrir no celular
// =============================================
// 1º SCAN = ENTRADA | 2º SCAN = SAÍDA | 3º SCAN = BLOQUEADO
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
// CORRIGIR ESTRUTURA DA TABELA
// =============================================

function corrigirStatusPresencas() {
    global $pdo;
    try {
        // Verificar se a tabela existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'presenca_qr'");
        if (!$stmt->fetch()) {
            return;
        }
        
        // Verificar a estrutura atual da coluna status
        $stmt = $pdo->query("SHOW COLUMNS FROM presenca_qr LIKE 'status'");
        $coluna = $stmt->fetch();
        
        if ($coluna) {
            $type = $coluna['Type'] ?? '';
            // Se o tipo contém 'fechado', remover e adicionar 'anulado'
            if (strpos($type, 'fechado') !== false || strpos($type, 'anulado') === false) {
                $pdo->exec("ALTER TABLE presenca_qr MODIFY COLUMN status ENUM('presente', 'ausente', 'atraso', 'anulado') DEFAULT 'ausente'");
            }
        } else {
            // Adicionar coluna se não existir
            $pdo->exec("ALTER TABLE presenca_qr ADD COLUMN status ENUM('presente', 'ausente', 'atraso', 'anulado') DEFAULT 'ausente'");
        }
        
        // Corrigir registros existentes - manter 'presente' para quem tem entrada
        $pdo->exec("UPDATE presenca_qr SET status = 'presente' WHERE hora_entrada IS NOT NULL AND (status IS NULL OR status = 'fechado' OR status = '')");
        $pdo->exec("UPDATE presenca_qr SET status = 'ausente' WHERE hora_entrada IS NULL AND (status IS NULL OR status = '' OR status = 'fechado')");
        
        // ANULAR registros do dia anterior que têm entrada mas não têm saída
        $dataOntem = date('Y-m-d', strtotime('-1 day'));
        $pdo->exec("UPDATE presenca_qr SET status = 'anulado' WHERE data <= '$dataOntem' AND hora_entrada IS NOT NULL AND hora_saida IS NULL AND (status IS NULL OR status != 'anulado')");
        
        error_log("Status corrigidos com sucesso!");
    } catch (PDOException $e) {
        error_log("Erro ao corrigir status: " . $e->getMessage());
    }
}

// Executar correção
corrigirStatusPresencas();

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
            
            $stmt = $pdo->prepare("SELECT id, nome FROM funcionarios WHERE id = ? AND status = 'ativo'");
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
                // CASO 2: JÁ TEM ENTRADA E SAÍDA → BLOQUEAR
                // =============================================
                $temEntrada = !empty($presenca['hora_entrada']);
                $temSaida = !empty($presenca['hora_saida']);
                $isAnulado = ($presenca['status'] === 'anulado');
                
                // Se o registro foi anulado, não permitir marcação
                if ($isAnulado) {
                    $mensagem = "⛔ PONTO ANULADO! Contate o RH.";
                    header('Location: scan.php?action=error&msg=' . urlencode($mensagem));
                    exit;
                }
                
                if ($temEntrada && $temSaida) {
                    $mensagem = "⛔ PONTO JÁ FINALIZADO HOJE! Saída às " . date('H:i:s', strtotime($presenca['hora_saida']));
                    header('Location: scan.php?action=error&msg=' . urlencode($mensagem));
                    exit;
                }
                
                // =============================================
                // CASO 3: TEM ENTRADA, NÃO TEM SAÍDA → SAÍDA
                // =============================================
                if ($temEntrada && !$temSaida) {
                    // Calcular horas trabalhadas
                    $entrada = new DateTime($presenca['hora_entrada']);
                    $saida = new DateTime($hora_atual);
                    $intervalo = $entrada->diff($saida);
                    $horas = $intervalo->h + ($intervalo->i / 60);
                    
                    // Atualizar com SAÍDA - mantém status 'presente'
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
                    
                    // Atualizar último acesso
                    $stmt = $pdo->prepare("UPDATE dispositivos_funcionarios SET ultimo_acesso = NOW() WHERE device_id = ?");
                    $stmt->execute([$device_id]);
                    
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
// PROCESSAR SAÍDA MANUAL (BOTÃO)
// =============================================

if ($action === 'solicitar_saida') {
    $device_id = getDeviceId();
    $data = getDataAngola();
    
    // Verificar se o dispositivo está registrado
    $stmt = $pdo->prepare("SELECT funcionario_id FROM dispositivos_funcionarios WHERE device_id = ?");
    $stmt->execute([$device_id]);
    $dispositivo = $stmt->fetch();
    
    if ($dispositivo) {
        $funcionario_id = $dispositivo['funcionario_id'];
        
        // Verificar presença hoje
        $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$funcionario_id, $data]);
        $presenca = $stmt->fetch();
        
        if ($presenca) {
            $temEntrada = !empty($presenca['hora_entrada']);
            $temSaida = !empty($presenca['hora_saida']);
            $isAnulado = ($presenca['status'] === 'anulado');
            
            if ($isAnulado) {
                header('Location: scan.php?action=error&msg=' . urlencode('⛔ Ponto anulado! Contate o RH.'));
                exit;
            } elseif ($temEntrada && !$temSaida) {
                // Redirecionar para confirmar saída
                header('Location: scan.php?action=confirmar_saida');
                exit;
            } elseif ($temEntrada && $temSaida) {
                header('Location: scan.php?action=error&msg=' . urlencode('⛔ Ponto já finalizado hoje!'));
                exit;
            } elseif (!$temEntrada) {
                header('Location: scan.php?action=error&msg=' . urlencode('❌ Você não tem entrada registrada hoje!'));
                exit;
            } else {
                header('Location: scan.php?action=error&msg=' . urlencode('⚠️ Ponto já finalizado!'));
                exit;
            }
        } else {
            header('Location: scan.php?action=error&msg=' . urlencode('❌ Nenhuma presença registrada hoje!'));
            exit;
        }
    } else {
        header('Location: scan.php?action=error&msg=' . urlencode('❌ Dispositivo não registrado!'));
        exit;
    }
}

// =============================================
// PROCESSAR CONFIRMAÇÃO DE SAÍDA
// =============================================

if ($action === 'confirmar_saida' && isset($_GET['confirmar']) && $_GET['confirmar'] === 'sim') {
    $device_id = getDeviceId();
    $data = getDataAngola();
    $hora_atual = getHoraAngola();
    
    // Verificar se o dispositivo está registrado
    $stmt = $pdo->prepare("SELECT funcionario_id FROM dispositivos_funcionarios WHERE device_id = ?");
    $stmt->execute([$device_id]);
    $dispositivo = $stmt->fetch();
    
    if ($dispositivo) {
        $funcionario_id = $dispositivo['funcionario_id'];
        
        // Verificar presença hoje
        $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$funcionario_id, $data]);
        $presenca = $stmt->fetch();
        
        if ($presenca && !empty($presenca['hora_entrada']) && empty($presenca['hora_saida'])) {
            // Calcular horas trabalhadas
            $entrada = new DateTime($presenca['hora_entrada']);
            $saida = new DateTime($hora_atual);
            $intervalo = $entrada->diff($saida);
            $horas = $intervalo->h + ($intervalo->i / 60);
            
            // Atualizar com SAÍDA - mantém status 'presente'
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
            
            // Atualizar último acesso
            $stmt = $pdo->prepare("UPDATE dispositivos_funcionarios SET ultimo_acesso = NOW() WHERE device_id = ?");
            $stmt->execute([$device_id]);
            
            $mensagem = "✅ SAÍDA registrada às " . $hora_atual . " (Horas: " . number_format($horas, 2) . "h)";
            header('Location: scan.php?action=success&msg=' . urlencode($mensagem));
            exit;
        } else {
            header('Location: scan.php?action=error&msg=' . urlencode('❌ Não foi possível registrar a saída!'));
            exit;
        }
    } else {
        header('Location: scan.php?action=error&msg=' . urlencode('❌ Dispositivo não registrado!'));
        exit;
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
$tem_entrada = false;
$tem_saida = false;
$ponto_finalizado = false;
$ponto_anulado = false;

if ($funcionario_id) {
    $dataHoje = getDataAngola();
    $dataOntem = date('Y-m-d', strtotime('-1 day'));
    
    // Buscar presença de hoje
    $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$funcionario_id, $dataHoje]);
    $presenca_hoje = $stmt->fetch();
    
    if ($presenca_hoje) {
        $tem_entrada = !empty($presenca_hoje['hora_entrada']);
        $tem_saida = !empty($presenca_hoje['hora_saida']);
        $ponto_finalizado = ($tem_entrada && $tem_saida);
        $ponto_anulado = ($presenca_hoje['status'] === 'anulado');
        
        // Se tem entrada e NÃO tem saída, verificar se é do dia anterior
        if ($tem_entrada && !$tem_saida && $presenca_hoje['data'] <= $dataOntem) {
            // Anular registros do dia anterior sem saída
            $stmtAnular = $pdo->prepare("UPDATE presenca_qr SET status = 'anulado' WHERE id = ?");
            $stmtAnular->execute([$presenca_hoje['id']]);
            $ponto_anulado = true;
        }
        
        // Garantir que o status seja 'presente' se tiver entrada (e não for anulado)
        if ($tem_entrada && !$ponto_anulado && $presenca_hoje['status'] !== 'presente') {
            $stmtCorrigir = $pdo->prepare("UPDATE presenca_qr SET status = 'presente' WHERE id = ?");
            $stmtCorrigir->execute([$presenca_hoje['id']]);
            $presenca_hoje['status'] = 'presente';
        }
    }
}

$hora_atual_angola = getHoraAngola();
$modo_confirmar_saida = ($action === 'confirmar_saida');
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
        
        .status-box.finalizado { 
            background: #dbeafe; 
            border: 2px solid #bfdbfe; 
        }
        .status-box.finalizado .mensagem { 
            color: #1e40af; 
        }
        
        .status-box.anulado { 
            background: #fee2e2; 
            border: 2px solid #fecaca; 
        }
        .status-box.anulado .mensagem { 
            color: #991b1b; 
        }
        
        .status-box.confirmar {
            background: #fef3c7;
            border: 2px solid #f59e0b;
        }
        .status-box.confirmar .mensagem {
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
        
        .btn-danger { 
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        .btn-danger:hover { 
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
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
        .status-badge.finalizado { background: #dbeafe; color: #1e40af; }
        .status-badge.anulado { background: #fee2e2; color: #991b1b; }
        
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
        
        .instrucao-scan.finalizado {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        
        .instrucao-scan.sucesso {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .instrucao-scan.anulado {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-group .btn {
            flex: 1;
        }
        
        .btn-saida {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            width: 100%;
            transition: all 0.3s ease;
            display: inline-block;
            text-align: center;
            margin-top: 10px;
        }
        .btn-saida:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .btn-saida:active {
            transform: scale(0.98);
        }
        
        @media (max-width: 480px) {
            .container { padding: 20px; }
            h1 { font-size: 20px; }
            .relogio { font-size: 24px; }
            .btn-group { flex-direction: column; }
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
            <div class="status-box sucesso" style="background: #d1fae5; border: 2px solid #a7f3d0;">
                <div class="icon">✅</div>
                <div class="mensagem"><?= htmlspecialchars($_GET['msg']) ?></div>
                <div class="detalhe">Data: <?= getDataHoraAngola() ?></div>
            </div>
            
            <div class="instrucao-scan sucesso">
                <span class="icone-qr">📷</span>
                <div class="texto">Escaneie o QR Code novamente para continuar</div>
                <div class="sub">Aponte a câmera para o QR Code</div>
            </div>
            
            <a href="scan.php" class="btn">🔙 Voltar</a>
            
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
            
        <?php elseif ($action === 'confirmar_saida'): ?>
            <!-- TELA DE CONFIRMAÇÃO DE SAÍDA -->
            <div class="status-box confirmar">
                <div class="icon">⏳</div>
                <div class="mensagem">Confirmar Saída</div>
                <div class="detalhe"><?= htmlspecialchars($funcionario_nome) ?></div>
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
                    <span class="valor hora-destaque"><?= date('H:i:s', strtotime($presenca_hoje['hora_entrada'])) ?></span>
                </div>
                <div class="linha" style="color: #f59e0b;">
                    <span class="label">⏰ Saída</span>
                    <span class="valor" style="color: #f59e0b;">⏳ Pendente</span>
                </div>
                <div class="linha">
                    <span class="label">📌 Status</span>
                    <span class="valor">
                        <span class="status-badge presente">✅ Presente</span>
                    </span>
                </div>
            </div>
            
            <div class="instrucao-scan pendente" style="border-color: #ef4444; background: #fef2f2;">
                <span class="icone-qr">📷</span>
                <div class="texto" style="color: #991b1b;">🔴 ESCANEIE O QR CODE PARA REGISTRAR A SAÍDA</div>
                <div class="sub">Aponte a câmera para o QR Code para finalizar o ponto</div>
            </div>
            
            <div class="alert alert-warning">
                ⚠️ Atenção: Você está prestes a registrar sua saída. 
                Escaneie o QR Code para confirmar.
            </div>
            
            <div class="btn-group">
                <a href="scan.php" class="btn btn-secondary">Cancelar</a>
                <a href="scan.php?action=confirmar_saida&confirmar=sim" class="btn btn-danger">✅ Confirmar Saída</a>
            </div>
            
        <?php else: ?>
            <!-- Página principal -->
            <?php if ($dispositivo_registrado): ?>
                
                <?php if ($ponto_anulado): ?>
                <!-- PONTO ANULADO -->
                <div class="status-box anulado">
                    <div class="icon">🚫</div>
                    <div class="mensagem">PONTO ANULADO</div>
                    <div class="detalhe">Registro sem saída do dia anterior</div>
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
                        <span class="valor" style="color: #991b1b;">❌ Não registrada</span>
                    </div>
                    <div class="linha">
                        <span class="label">📌 Status</span>
                        <span class="valor">
                            <span class="status-badge anulado">🚫 Anulado</span>
                        </span>
                    </div>
                </div>
                
                <div class="instrucao-scan anulado">
                    <span class="icone-qr">⛔</span>
                    <div class="texto">Ponto anulado!</div>
                    <div class="sub">Contate o RH para regularizar</div>
                </div>
                
                <?php elseif ($ponto_finalizado): ?>
                <!-- PONTO FINALIZADO (com entrada e saída) -->
                <div class="status-box finalizado">
                    <div class="icon">✅</div>
                    <div class="mensagem">PONTO FINALIZADO</div>
                    <div class="detalhe">Jornada concluída hoje</div>
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
                            <span class="status-badge finalizado">✅ Presente</span>
                        </span>
                    </div>
                </div>
                
                <div class="instrucao-scan finalizado">
                    <span class="icone-qr">⛔</span>
                    <div class="texto">Ponto já finalizado hoje!</div>
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
                            <span class="status-badge presente">✅ Presente</span>
                        </span>
                    </div>
                </div>
                
                <div class="instrucao-scan pendente">
                    <span class="icone-qr">📷</span>
                    <div class="texto">🔴 ESCANEIE O QR CODE PARA REGISTRAR A SAÍDA</div>
                    <div class="sub">Aponte a câmera para o QR Code para finalizar o ponto</div>
                </div>
                
                <!-- BOTÃO PARA SOLICITAR SAÍDA -->
                <a href="scan.php?action=solicitar_saida" class="btn-saida">
                    🚪 Solicitar Saída
                </a>
                
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