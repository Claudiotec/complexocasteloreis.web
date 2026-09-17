<?php
// presenca_qr.php - Controle de Presença com QR Code
require_once '../../config/database.php';

// =============================================
// CONFIGURAÇÃO DE FUSO HORÁRIO - ANGOLA (UTC+1)
// =============================================
date_default_timezone_set('Africa/Luanda');
putenv('TZ=Africa/Luanda');

if (date_default_timezone_get() !== 'Africa/Luanda') {
    date_default_timezone_set('Etc/GMT-1');
}

// Função para obter hora Angola
function getHoraAngola() {
    $timestamp = time() + 3600;
    return date('H:i:s', $timestamp);
}

function getDataAngola() {
    $timestamp = time() + 3600;
    return date('Y-m-d', $timestamp);
}

// =============================================
// VERIFICAR SE O DIA JÁ FOI FECHADO
// =============================================

function diaFechado($data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM fechamento_diario WHERE data = ?");
        $stmt->execute([$data]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

// =============================================
// FUNÇÃO PARA VALIDAR FUNCIONÁRIO
// =============================================

function validarFuncionario($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$id]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

// =============================================
// FILTRO DE DATA
// =============================================

$dataHoje = getDataAngola();
$dataSelecionada = isset($_GET['data']) ? $_GET['data'] : $dataHoje;

// Validar data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataSelecionada)) {
    $dataSelecionada = $dataHoje;
}

$isDataHoje = ($dataSelecionada === $dataHoje);
$isDataAnterior = ($dataSelecionada < $dataHoje);
$diaSelecionadoFechado = diaFechado($dataSelecionada);

// =============================================
// PROCESSAR MARCAÇÃO MANUAL
// =============================================

// Verificar se o dia está fechado antes de qualquer marcação
if ($diaSelecionadoFechado && (isset($_GET['marcar_entrada']) || isset($_GET['marcar_saida']) || isset($_GET['confirmar_saida']))) {
    header('Location: presenca_qr.php?data=' . $dataSelecionada . '&error=' . urlencode('⚠️ O dia já foi fechado. Não é possível fazer marcações.'));
    exit;
}

// Processar marcação manual de entrada
if (isset($_GET['marcar_entrada']) && isset($_GET['funcionario'])) {
    $funcionario_id = (int)$_GET['funcionario'];
    $data = $dataSelecionada;
    $hora_atual = getHoraAngola();
    
    // VALIDAR SE O FUNCIONÁRIO EXISTE
    if (!validarFuncionario($funcionario_id)) {
        header('Location: presenca_qr.php?data=' . $dataSelecionada . '&error=' . urlencode('⚠️ Funcionário inválido ou inativo.'));
        exit;
    }
    
    // Buscar funcionário - USANDO TABELA funcionarios
    $stmt = $pdo->prepare("SELECT nome FROM funcionarios WHERE id = ?");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch();
    
    if ($funcionario) {
        // VERIFICAR SE JÁ EXISTE REGISTRO PARA ESTE FUNCIONÁRIO E DATA
        $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$funcionario_id, $data]);
        $presenca = $stmt->fetch();
        
        if ($presenca) {
            // JÁ TEM REGISTRO
            if ($presenca['status'] === 'presente' && empty($presenca['hora_saida'])) {
                // Tem entrada, não tem saída - perguntar se quer marcar saída
                header('Location: presenca_qr.php?data=' . $data . '&confirmar_saida=1&funcionario=' . $funcionario_id);
                exit;
            } elseif ($presenca['status'] === 'presente' && !empty($presenca['hora_saida'])) {
                // JÁ TEM ENTRADA E SAÍDA - PONTO FEITO
                header('Location: presenca_qr.php?data=' . $data . '&error=' . urlencode('⚠️ Ponto já registrado para ' . $funcionario['nome'] . ' (Entrada: ' . date('H:i', strtotime($presenca['hora_entrada'])) . ' - Saída: ' . date('H:i', strtotime($presenca['hora_saida'])) . ')'));
                exit;
            } else {
                // Atualizar para presente
                $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                    hora_entrada = ?,
                    status = 'presente',
                    qr_scanned = 0,
                    qr_scan_time = NOW()
                    WHERE id = ?");
                $stmt->execute([$hora_atual, $presenca['id']]);
                $mensagem = "✅ Entrada manual registrada para " . $funcionario['nome'] . " às " . $hora_atual;
            }
        } else {
            // NÃO TEM REGISTRO - CRIAR NOVO
            $stmt = $pdo->prepare("INSERT INTO presenca_qr 
                (funcionario_id, data, hora_entrada, status, qr_scanned, qr_scan_time) 
                VALUES (?, ?, ?, 'presente', 0, NOW())");
            $stmt->execute([$funcionario_id, $data, $hora_atual]);
            $mensagem = "✅ Entrada manual registrada para " . $funcionario['nome'] . " às " . $hora_atual;
        }
        
        if (isset($mensagem)) {
            header('Location: presenca_qr.php?data=' . $data . '&success=' . urlencode($mensagem));
            exit;
        }
    }
}

// Processar marcação manual de saída
if (isset($_GET['marcar_saida']) && isset($_GET['funcionario'])) {
    $funcionario_id = (int)$_GET['funcionario'];
    $data = $dataSelecionada;
    $hora_atual = getHoraAngola();
    
    // VALIDAR SE O FUNCIONÁRIO EXISTE
    if (!validarFuncionario($funcionario_id)) {
        header('Location: presenca_qr.php?data=' . $dataSelecionada . '&error=' . urlencode('⚠️ Funcionário inválido ou inativo.'));
        exit;
    }
    
    // Buscar funcionário - USANDO TABELA funcionarios
    $stmt = $pdo->prepare("SELECT nome FROM funcionarios WHERE id = ?");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch();
    
    if ($funcionario) {
        // Verificar presença na data selecionada
        $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$funcionario_id, $data]);
        $presenca = $stmt->fetch();
        
        if ($presenca) {
            if ($presenca['status'] === 'presente' && empty($presenca['hora_saida'])) {
                // Tem entrada, não tem saída - registrar saída
                $entrada = new DateTime($presenca['hora_entrada']);
                $saida = new DateTime($hora_atual);
                $intervalo = $entrada->diff($saida);
                $horas = $intervalo->h + ($intervalo->i / 60);
                
                $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                    hora_saida = ?, 
                    horas_trabalhadas = ?,
                    qr_scanned = 0,
                    qr_scan_time = NOW()
                    WHERE id = ?");
                $stmt->execute([$hora_atual, $horas, $presenca['id']]);
                
                $mensagem = "✅ Saída manual registrada para " . $funcionario['nome'] . " às " . $hora_atual . " (Horas: " . number_format($horas, 2) . "h)";
            } elseif ($presenca['status'] === 'presente' && !empty($presenca['hora_saida'])) {
                // JÁ TEM SAÍDA - PONTO FEITO
                $mensagem = "⚠️ Ponto já registrado para " . $funcionario['nome'] . " (Entrada: " . date('H:i', strtotime($presenca['hora_entrada'])) . " - Saída: " . date('H:i', strtotime($presenca['hora_saida'])) . ")";
            } else {
                $mensagem = "⚠️ " . $funcionario['nome'] . " não tem entrada registrada na data " . date('d/m/Y', strtotime($data)) . "!";
            }
        } else {
            $mensagem = "⚠️ " . $funcionario['nome'] . " não tem entrada registrada na data " . date('d/m/Y', strtotime($data)) . "!";
        }
        
        header('Location: presenca_qr.php?data=' . $data . '&success=' . urlencode($mensagem));
        exit;
    }
}

// Processar confirmação de saída
if (isset($_GET['confirmar_saida']) && isset($_GET['funcionario'])) {
    $funcionario_id = (int)$_GET['funcionario'];
    $data = $dataSelecionada;
    $hora_atual = getHoraAngola();
    
    // VALIDAR SE O FUNCIONÁRIO EXISTE
    if (!validarFuncionario($funcionario_id)) {
        header('Location: presenca_qr.php?data=' . $dataSelecionada . '&error=' . urlencode('⚠️ Funcionário inválido ou inativo.'));
        exit;
    }
    
    // Buscar funcionário - USANDO TABELA funcionarios
    $stmt = $pdo->prepare("SELECT nome FROM funcionarios WHERE id = ?");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch();
    
    if ($funcionario) {
        $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$funcionario_id, $data]);
        $presenca = $stmt->fetch();
        
        if ($presenca && $presenca['status'] === 'presente' && empty($presenca['hora_saida'])) {
            $entrada = new DateTime($presenca['hora_entrada']);
            $saida = new DateTime($hora_atual);
            $intervalo = $entrada->diff($saida);
            $horas = $intervalo->h + ($intervalo->i / 60);
            
            $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                hora_saida = ?, 
                horas_trabalhadas = ?,
                qr_scanned = 0,
                qr_scan_time = NOW()
                WHERE id = ?");
            $stmt->execute([$hora_atual, $horas, $presenca['id']]);
            
            $mensagem = "✅ Saída manual registrada para " . $funcionario['nome'] . " às " . $hora_atual . " (Horas: " . number_format($horas, 2) . "h)";
        } else {
            $mensagem = "⚠️ " . $funcionario['nome'] . " não tem entrada registrada na data " . date('d/m/Y', strtotime($data)) . "!";
        }
        
        header('Location: presenca_qr.php?data=' . $data . '&success=' . urlencode($mensagem));
        exit;
    }
}

// =============================================
// GERAR QR CODE
// =============================================

// Gerar QR Code para funcionário específico
if (isset($_GET['gerar_qr']) && isset($_GET['funcionario'])) {
    $funcionario_id = $_GET['funcionario'];
    $data = $dataSelecionada;
    
    // VALIDAR SE O FUNCIONÁRIO EXISTE
    if (!validarFuncionario($funcionario_id)) {
        header('Location: presenca_qr.php?data=' . $dataSelecionada . '&error=' . urlencode('⚠️ Funcionário inválido ou inativo.'));
        exit;
    }
    
    // Buscar funcionário - USANDO TABELA funcionarios
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ?");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch();
    
    if ($funcionario) {
        $token = bin2hex(random_bytes(32));
        $qr_data = json_encode([
            'funcionario_id' => $funcionario_id,
            'nome' => $funcionario['nome'],
            'data' => $data,
            'token' => $token
        ]);
        
        $stmt = $pdo->prepare("INSERT INTO qr_codes_diarios (funcionario_id, data, qr_code, token, expiracao) 
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ON DUPLICATE KEY UPDATE qr_code = VALUES(qr_code), token = VALUES(token), expiracao = VALUES(expiracao)");
        $stmt->execute([$funcionario_id, $data, base64_encode($qr_data), $token]);
        
        // Verificar se já existe registro de presença para este funcionário/data
        $stmt = $pdo->prepare("SELECT id FROM presenca_qr WHERE funcionario_id = ? AND data = ?");
        $stmt->execute([$funcionario_id, $data]);
        $existe = $stmt->fetch();
        
        if ($existe) {
            $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                qr_code = ?,
                status = 'ausente'
                WHERE funcionario_id = ? AND data = ?");
            $stmt->execute([base64_encode($qr_data), $funcionario_id, $data]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO presenca_qr (funcionario_id, data, status, qr_code) 
                VALUES (?, ?, 'ausente', ?)");
            $stmt->execute([$funcionario_id, $data, base64_encode($qr_data)]);
        }
        
        header('Location: presenca_qr.php?data=' . $data . '&success=' . urlencode("QR Code gerado para " . $funcionario['nome']));
        exit;
    }
}

// Gerar QR para todos os funcionários ativos
if (isset($_GET['gerar_todos'])) {
    $data = $dataSelecionada;
    
    // USANDO TABELA funcionarios
    $stmt = $pdo->query("SELECT id, nome FROM funcionarios WHERE status = 'ativo'");
    $funcionarios = $stmt->fetchAll();
    
    $gerados = 0;
    foreach($funcionarios as $func) {
        $token = bin2hex(random_bytes(32));
        $qr_data = json_encode([
            'funcionario_id' => $func['id'],
            'nome' => $func['nome'],
            'data' => $data,
            'token' => $token
        ]);
        
        $stmt = $pdo->prepare("INSERT INTO qr_codes_diarios (funcionario_id, data, qr_code, token, expiracao) 
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ON DUPLICATE KEY UPDATE qr_code = VALUES(qr_code), token = VALUES(token), expiracao = VALUES(expiracao)");
        $stmt->execute([$func['id'], $data, base64_encode($qr_data), $token]);
        
        // Verificar se já existe registro
        $stmt = $pdo->prepare("SELECT id FROM presenca_qr WHERE funcionario_id = ? AND data = ?");
        $stmt->execute([$func['id'], $data]);
        $existe = $stmt->fetch();
        
        if ($existe) {
            $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                qr_code = ?,
                status = 'ausente'
                WHERE funcionario_id = ? AND data = ?");
            $stmt->execute([base64_encode($qr_data), $func['id'], $data]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO presenca_qr (funcionario_id, data, status, qr_code) 
                VALUES (?, ?, 'ausente', ?)");
            $stmt->execute([$func['id'], $data, base64_encode($qr_data)]);
        }
        $gerados++;
    }
    
    header('Location: presenca_qr.php?data=' . $data . '&success=' . urlencode("✅ $gerados QR Codes gerados com sucesso para " . date('d/m/Y', strtotime($data))));
    exit;
}

// =============================================
// BUSCAR DADOS PARA EXIBIÇÃO - USANDO TABELA funcionarios
// =============================================

$stmt = $pdo->prepare("SELECT 
    f.id, f.nome, f.num_agente, f.status,
    p.id as presenca_id, p.hora_entrada, p.hora_saida, p.status as presenca_status,
    p.qr_scanned, p.horas_trabalhadas, p.observacao,
    q.qr_code, q.expiracao
    FROM funcionarios f
    LEFT JOIN presenca_qr p ON f.id = p.funcionario_id AND p.data = ?
    LEFT JOIN qr_codes_diarios q ON f.id = q.funcionario_id AND q.data = ?
    WHERE f.status = 'ativo'
    ORDER BY f.nome");

$stmt->execute([$dataSelecionada, $dataSelecionada]);
$funcionarios = $stmt->fetchAll();

// =============================================
// CORREÇÃO: CORRIGIR STATUS NULL DOS REGISTROS
// =============================================

foreach ($funcionarios as $key => $func) {
    // Verificar e corrigir status NULL
    if (!empty($func['hora_entrada']) && 
        ($func['presenca_status'] === 'ausente' || $func['presenca_status'] === null || $func['presenca_status'] === '')) {
        
        // Corrigir no banco de dados
        if ($func['presenca_id']) {
            $stmtUpdate = $pdo->prepare("UPDATE presenca_qr SET status = 'presente' WHERE id = ?");
            $stmtUpdate->execute([$func['presenca_id']]);
            
            // Se tem saída, calcular horas
            if (!empty($func['hora_saida'])) {
                $entrada = new DateTime($func['hora_entrada']);
                $saida = new DateTime($func['hora_saida']);
                $intervalo = $entrada->diff($saida);
                $horas = $intervalo->h + ($intervalo->i / 60);
                
                $stmtUpdate = $pdo->prepare("UPDATE presenca_qr SET horas_trabalhadas = ? WHERE id = ?");
                $stmtUpdate->execute([$horas, $func['presenca_id']]);
            }
            
            // Atualizar o array com o novo status
            $funcionarios[$key]['presenca_status'] = 'presente';
        }
    }
}

// =============================================
// CORREÇÃO: RESUMO CORRETO DE PRESENÇA
// =============================================

$stmtResumo = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE 
            WHEN p.id IS NOT NULL 
            AND p.status = 'presente' 
            AND p.hora_saida IS NOT NULL 
            AND p.status != 'anulado' 
            THEN 1 ELSE 0 
        END) as presentes_com_saida,
        SUM(CASE 
            WHEN p.id IS NOT NULL 
            AND p.status = 'presente' 
            AND p.hora_saida IS NULL 
            AND p.status != 'anulado' 
            THEN 1 ELSE 0 
        END) as presentes_sem_saida,
        SUM(CASE 
            WHEN p.id IS NOT NULL 
            AND p.status = 'anulado' 
            THEN 1 ELSE 0 
        END) as anulados,
        SUM(CASE 
            WHEN p.id IS NOT NULL 
            AND p.qr_scanned = 1 
            THEN 1 ELSE 0 
        END) as scans
    FROM funcionarios f
    LEFT JOIN presenca_qr p ON f.id = p.funcionario_id AND p.data = ?
    WHERE f.status = 'ativo'
");

$stmtResumo->execute([$dataSelecionada]);
$resumo = $stmtResumo->fetch();

// Calcular totais corretamente
$totalPresentes = ($resumo['presentes_com_saida'] ?? 0) + ($resumo['presentes_sem_saida'] ?? 0);
$totalAusentes = ($resumo['total'] ?? 0) - $totalPresentes - ($resumo['anulados'] ?? 0);
if ($totalAusentes < 0) $totalAusentes = 0;

if (!$resumo) {
    $resumo = [
        'total' => 0, 
        'presentes_com_saida' => 0,
        'presentes_sem_saida' => 0,
        'presentes' => 0,
        'ausentes' => 0, 
        'sem_saida' => 0, 
        'anulados' => 0, 
        'scans' => 0
    ];
} else {
    $resumo['presentes'] = $totalPresentes;
    $resumo['ausentes'] = $totalAusentes;
    $resumo['sem_saida'] = $resumo['presentes_sem_saida'] ?? 0;
}

// Buscar dispositivos registrados
$stmtDispositivos = $pdo->query("SELECT COUNT(*) as total FROM dispositivos_funcionarios");
$totalDispositivos = $stmtDispositivos->fetch()['total'];

// Função para gerar QR Code do sistema
function gerarQRCodeSistema() {
    $host = $_SERVER['HTTP_HOST'];
    $url_scan = "http://" . $host . "/softgest_web/modules/rh/scan.php";
    $url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($url_scan);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $imagem = curl_exec($ch);
    curl_close($ch);
    
    if ($imagem) {
        return 'data:image/png;base64,' . base64_encode($imagem);
    }
    return null;
}

$qr_code_sistema = gerarQRCodeSistema();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presença QR - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f0f4f8; 
            color: #1a2332;
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 20px;
            max-width: calc(100% - 280px);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .container { max-width: 1200px; margin: 0 auto; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .page-title { font-size: 28px; font-weight: 800; color: #1a2332; }
        .page-title span { color: #c9a84c; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        
        .filtro-data {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filtro-data label {
            font-weight: 600;
            color: #1a2332;
            font-size: 14px;
        }
        .filtro-data input[type="date"] {
            padding: 8px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            min-width: 180px;
        }
        .filtro-data input[type="date"]:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
        }
        .filtro-data .btn {
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .filtro-data .btn-filtrar {
            background: #c9a84c;
            color: #1a2332;
        }
        .filtro-data .btn-filtrar:hover {
            background: #b8973a;
            transform: translateY(-2px);
        }
        .filtro-data .btn-hoje {
            background: #8b5cf6;
            color: white;
        }
        .filtro-data .btn-hoje:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }
        .filtro-data .data-info {
            font-size: 13px;
            color: #94a3b8;
            margin-left: auto;
        }
        .filtro-data .data-info strong {
            color: #1a2332;
        }
        
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3); }
        
        .btn-outline {
            background: transparent;
            color: #c9a84c;
            padding: 10px 20px;
            border: 2px solid #c9a84c;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-outline:hover { background: rgba(197, 165, 50, 0.1); transform: translateY(-2px); }
        
        .btn-purple {
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-purple:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4); }
        
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
        
        .btn-success {
            background: #10b981;
            color: white;
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }
        .btn-success:hover { background: #059669; transform: translateY(-2px); }
        
        .btn-danger-small {
            background: #ef4444;
            color: white;
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }
        .btn-danger-small:hover { background: #dc2626; transform: translateY(-2px); }
        
        .btn-small {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            margin: 2px;
        }
        .btn-small.qr { background: #10b981; color: white; }
        .btn-small.qr:hover { background: #059669; }
        .btn-small.ponto-feito { 
            background: #d1fae5; 
            color: #065f46; 
            cursor: default;
            pointer-events: none;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            text-align: center;
        }
        .stat-card .number { font-size: 26px; font-weight: 800; color: #1a2332; }
        .stat-card .label { color: #94a3b8; font-size: 12px; margin-top: 4px; }
        .stat-card .number.green { color: #10b981; }
        .stat-card .number.red { color: #ef4444; }
        .stat-card .number.yellow { color: #f59e0b; }
        .stat-card .number.orange { color: #f97316; }
        .stat-card .number.purple { color: #8b5cf6; }
        .stat-card .number.blue { color: #3b82f6; }
        
        .qr-display {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            border: 2px dashed #c9a84c;
            margin-bottom: 25px;
        }
        .qr-display .qr-code { display: inline-block; padding: 15px; background: white; border-radius: 8px; border: 1px solid #eef2f7; margin: 10px 0; }
        .qr-display .qr-code img { max-width: 180px; height: auto; }
        .qr-display .instrucoes { color: #64748b; font-size: 13px; line-height: 1.8; }
        
        .scan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .scan-card {
            background: white;
            border-radius: 12px;
            padding: 18px;
            border: 1px solid #eef2f7;
            text-align: center;
            transition: all 0.3s ease;
        }
        .scan-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .scan-card .nome { font-weight: 600; font-size: 15px; color: #1a2332; }
        .scan-card .info { color: #94a3b8; font-size: 12px; margin: 5px 0; }
        .scan-card .status-badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .scan-card .status-badge.presente { background: #d1fae5; color: #065f46; }
        .scan-card .status-badge.ausente { background: #fee2e2; color: #991b1b; }
        .scan-card .status-badge.atraso { background: #fef3c7; color: #92400e; }
        .scan-card .status-badge.anulado { background: #fef3c7; color: #92400e; }
        .scan-card .status-badge.ponto-feito { background: #dbeafe; color: #1e40af; }
        .scan-card .status-badge.pendente { background: #fef3c7; color: #92400e; }
        .scan-card .acoes { margin-top: 10px; display: flex; gap: 5px; justify-content: center; flex-wrap: wrap; }
        .scan-card .obs { font-size: 10px; color: #94a3b8; margin-top: 5px; font-style: italic; }
        .scan-card .bloqueado { 
            background: #f1f5f9; 
            padding: 5px 10px; 
            border-radius: 6px; 
            font-size: 11px; 
            color: #64748b;
            margin-top: 5px;
        }
        .scan-card .horario-info {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 5px;
            padding: 5px;
            background: #f8fafc;
            border-radius: 4px;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .status-fechado {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 15px;
            text-align: center;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .status-fechado .icone { font-size: 24px; }
        .status-fechado .texto { font-weight: 600; color: #1a2332; }
        .status-fechado .subtexto { font-size: 12px; color: #64748b; }
        
        .data-anterior-warning {
            background: #fef9e8;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 10px 15px;
            text-align: center;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .data-anterior-warning .icone { font-size: 20px; }
        .data-anterior-warning .texto { font-weight: 600; color: #92400e; }
        .data-anterior-warning .subtexto { font-size: 12px; color: #78350f; }
        
        .legenda-grid {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; max-width: 100%; padding: 15px; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .scan-grid { grid-template-columns: 1fr; }
            .actions { flex-direction: column; }
            .actions a { width: 100%; justify-content: center; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .qr-display .qr-code img { max-width: 130px; }
            .filtro-data { flex-direction: column; align-items: stretch; }
            .filtro-data .data-info { margin-left: 0; text-align: center; }
            .legenda-grid { flex-direction: column; align-items: center; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">📱 <span>Presença</span> com QR Code</h1>
                </div>
                <div>
                    <a href="index.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">❌ <?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>
            
            <!-- FILTRO DE DATA -->
            <div class="filtro-data">
                <label for="dataSelecionada"><i class="fas fa-calendar-alt"></i> Data:</label>
                <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <input type="date" id="dataSelecionada" name="data" value="<?= $dataSelecionada ?>">
                    <button type="submit" class="btn btn-filtrar"><i class="fas fa-filter"></i> Filtrar</button>
                    <a href="presenca_qr.php" class="btn btn-hoje"><i class="fas fa-calendar-day"></i> Hoje</a>
                </form>
                <div class="data-info">
                    <strong>📅 <?= date('d/m/Y', strtotime($dataSelecionada)) ?></strong>
                    <?php if ($isDataHoje): ?>
                        <span style="color: #10b981; margin-left: 5px;">(Hoje)</span>
                    <?php elseif ($isDataAnterior): ?>
                        <span style="color: #f59e0b; margin-left: 5px;">(Data Anterior)</span>
                    <?php endif; ?>
                    <?php if ($diaSelecionadoFechado): ?>
                        <span style="color: #ef4444; margin-left: 5px;">🔒 Fechado</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Aviso de data anterior -->
            <?php if ($isDataAnterior): ?>
                <div class="data-anterior-warning">
                    <span class="icone">📅</span>
                    <div>
                        <div class="texto">Visualizando dados de <?= date('d/m/Y', strtotime($dataSelecionada)) ?></div>
                        <div class="subtexto">As marcações manuais são permitidas apenas para a data atual.</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Aviso de dia fechado -->
            <?php if ($diaSelecionadoFechado): ?>
                <div class="status-fechado">
                    <span class="icone">🔒</span>
                    <div>
                        <div class="texto">Dia Fechado!</div>
                        <div class="subtexto">Este dia já foi finalizado. Não é possível fazer novas marcações.</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- QR Code Display (apenas para data atual) -->
            <?php if ($isDataHoje): ?>
            <div class="qr-display">
                <h3>📋 QR Code do Sistema</h3>
                <p style="color: #64748b; margin-bottom: 5px;">Imprima este QR Code e cole na vitrine</p>
                
                <div class="qr-code">
                    <?php if ($qr_code_sistema): ?>
                        <img src="<?= $qr_code_sistema ?>" alt="QR Code do Sistema">
                    <?php else: ?>
                        <div style="padding: 20px; color: #e74c3c;">⚠️ Não foi possível gerar o QR Code.</div>
                    <?php endif; ?>
                </div>
                
                <div class="instrucoes">
                    <strong>Como usar:</strong><br>
                    1. Escaneie o QR Code com seu celular<br>
                    2. Na primeira vez, informe seu nome<br>
                    3. Depois, marque presença com um clique
                </div>
                
                <div style="margin-top: 10px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <button onclick="window.print()" class="btn-gold">🖨️ Imprimir QR Code</button>
                    <a href="presenca_qr.php" class="btn-outline">🔄 Recarregar</a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Resumo -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number blue"><?= $resumo['total'] ?? 0 ?></div>
                    <div class="label">👥 Total Ativos</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #10b981;">
                    <div class="number green"><?= $resumo['presentes'] ?? 0 ?></div>
                    <div class="label">✅ Presentes</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #ef4444;">
                    <div class="number red"><?= $resumo['ausentes'] ?? 0 ?></div>
                    <div class="label">❌ Ausentes</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #f59e0b;">
                    <div class="number yellow"><?= $resumo['sem_saida'] ?? 0 ?></div>
                    <div class="label">⚠️ Sem Saída</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #f97316;">
                    <div class="number orange"><?= $resumo['anulados'] ?? 0 ?></div>
                    <div class="label">🚫 Anulados</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #8b5cf6;">
                    <div class="number purple"><?= $totalDispositivos ?></div>
                    <div class="label">📱 Dispositivos</div>
                </div>
            </div>
            
            <!-- Ações -->
            <div class="actions">
                <a href="ficha_presenca_diaria.php?data=<?= $dataSelecionada ?>" target="_blank" class="btn-purple">
                    <i class="fas fa-clipboard-list"></i> 📋 Ficha Diária
                </a>
                <?php if (!$diaSelecionadoFechado && $isDataHoje): ?>
                    <a href="presenca_qr.php?gerar_todos=1" class="btn-gold" onclick="return confirm('Gerar QR para todos os funcionários ativos?')">
                        <i class="fas fa-qrcode"></i> Gerar QR para Todos
                    </a>
                <?php endif; ?>
                <a href="horarios.php" class="btn-outline">
                    <i class="fas fa-clock"></i> Gerenciar Horários
                </a>
                <a href="assiduidade.php" class="btn-outline">
                    <i class="fas fa-chart-bar"></i> Assiduidade
                </a>
                <?php if ($isDataHoje): ?>
                <a href="fechar_dia.php" class="btn-danger">
                    <i class="fas fa-lock"></i> 🔒 Fechar Dia
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Cards de Funcionários -->
            <h3 style="margin-top: 25px; color: #1a2332;">📋 Status de Presença - <?= date('d/m/Y', strtotime($dataSelecionada)) ?></h3>
            
            <div class="scan-grid">
                <?php if (count($funcionarios) > 0): ?>
                    <?php 
                    // Variáveis para controle de contagem
                    $contadorPresentes = 0;
                    $contadorAusentes = 0;
                    $contadorSemSaida = 0;
                    $contadorAnulados = 0;
                    
                    foreach($funcionarios as $func): 
                        // Verificar status real
                        $temEntrada = !empty($func['hora_entrada']);
                        $temSaida = !empty($func['hora_saida']);
                        $status = $func['presenca_status'] ?? 'ausente';
                        $isAnulado = ($status === 'anulado');
                        
                        // Se tem entrada mas status não é 'presente' ou 'anulado', corrigir
                        if ($temEntrada && $status !== 'presente' && !$isAnulado) {
                            if ($func['presenca_id']) {
                                $stmtUpdate = $pdo->prepare("UPDATE presenca_qr SET status = 'presente' WHERE id = ?");
                                $stmtUpdate->execute([$func['presenca_id']]);
                            }
                            $status = 'presente';
                            $func['presenca_status'] = 'presente';
                        }
                        
                        // Determinar status final
                        $isPontoFeito = $temEntrada && $temSaida && $status === 'presente' && !$isAnulado;
                        $isPendente = $temEntrada && !$temSaida && $status === 'presente' && !$isAnulado;
                        
                        // Contar corretamente
                        if ($isAnulado) {
                            $statusExibicao = 'anulado';
                            $statusLabel = '🚫 Anulado';
                            $statusClass = 'anulado';
                            $contadorAnulados++;
                        } elseif ($isPontoFeito) {
                            $statusExibicao = 'ponto-feito';
                            $statusLabel = '✅ Ponto Feito';
                            $statusClass = 'ponto-feito';
                            $contadorPresentes++;
                        } elseif ($isPendente) {
                            $statusExibicao = 'pendente';
                            $statusLabel = '⏳ Pendente';
                            $statusClass = 'pendente';
                            $contadorPresentes++;
                            $contadorSemSaida++;
                        } elseif ($status === 'atraso') {
                            $statusExibicao = 'atraso';
                            $statusLabel = '⚠️ Atraso';
                            $statusClass = 'atraso';
                            $contadorPresentes++;
                        } else {
                            $statusExibicao = 'ausente';
                            $statusLabel = '❌ Ausente';
                            $statusClass = 'ausente';
                            $contadorAusentes++;
                        }
                        
                        $hora_entrada = $func['hora_entrada'] ? date('H:i', strtotime($func['hora_entrada'])) : '';
                        $hora_saida = $func['hora_saida'] ? date('H:i', strtotime($func['hora_saida'])) : '';
                    ?>
                    <div class="scan-card">
                        <div class="nome"><?= htmlspecialchars($func['nome']) ?></div>
                        <div class="info">Nº: <?= htmlspecialchars($func['num_agente']) ?></div>
                        
                        <div style="margin: 8px 0;">
                            <span class="status-badge <?= $statusClass ?>">
                                <?= $statusLabel ?>
                            </span>
                            <?php if ($isPontoFeito): ?>
                                <span style="font-size: 10px; color: #3b82f6; margin-left: 5px;">✅</span>
                            <?php endif; ?>
                            <?php if ($temEntrada && $func['qr_scanned'] == 1): ?>
                                <span style="font-size: 10px; color: #8b5cf6; margin-left: 5px;">📱 QR</span>
                            <?php elseif ($temEntrada && $func['qr_scanned'] == 0 && $temSaida): ?>
                                <span style="font-size: 10px; color: #f59e0b; margin-left: 5px;">✏️ Manual</span>
                            <?php endif; ?>
                            <?php if ($isAnulado): ?>
                                <span style="font-size: 10px; color: #ef4444; margin-left: 5px;">🚫</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($isPontoFeito): ?>
                            <div class="horario-info">
                                <strong>Entrada:</strong> <?= $hora_entrada ?> | 
                                <strong>Saída:</strong> <?= $hora_saida ?> | 
                                <strong>Horas:</strong> <?= number_format($func['horas_trabalhadas'] ?? 0, 2, ',', '.') ?>h
                            </div>
                        <?php elseif ($isPendente): ?>
                            <div style="font-size: 12px; color: #94a3b8;">
                                Entrada: <?= $hora_entrada ?: '—' ?>
                                <?php if (!$diaSelecionadoFechado): ?>
                                    <br><span style="color: #f59e0b;">⏳ Aguardando saída</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($func['observacao']): ?>
                            <div class="obs">📝 <?= htmlspecialchars($func['observacao']) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($diaSelecionadoFechado && !$isPontoFeito && !$isAnulado): ?>
                            <div class="bloqueado">🔒 Dia fechado</div>
                        <?php endif; ?>
                        
                        <div class="acoes">
                            <?php if (!$diaSelecionadoFechado && !$isPontoFeito && !$isAnulado && $isDataHoje): ?>
                                <a href="presenca_qr.php?gerar_qr=1&funcionario=<?= $func['id'] ?>&data=<?= $dataSelecionada ?>" class="btn-small qr">🔲 QR</a>
                                
                                <?php if ($status !== 'presente' || !$temEntrada): ?>
                                    <a href="presenca_qr.php?marcar_entrada=1&funcionario=<?= $func['id'] ?>&data=<?= $dataSelecionada ?>" 
                                       class="btn-success" 
                                       onclick="return confirm('Deseja marcar ENTRADA para <?= htmlspecialchars($func['nome']) ?>?')">
                                        <i class="fas fa-sign-in-alt"></i> Entrada
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($status === 'presente' && $temEntrada && !$temSaida && !$isAnulado): ?>
                                    <a href="presenca_qr.php?marcar_saida=1&funcionario=<?= $func['id'] ?>&data=<?= $dataSelecionada ?>" 
                                       class="btn-danger-small" 
                                       onclick="return confirm('Deseja marcar SAÍDA para <?= htmlspecialchars($func['nome']) ?>?')">
                                        <i class="fas fa-sign-out-alt"></i> Saída
                                    </a>
                                <?php endif; ?>
                            <?php elseif ($isPontoFeito): ?>
                                <span class="btn-small ponto-feito">
                                    <i class="fas fa-check-circle"></i> Ponto Feito
                                </span>
                            <?php elseif ($isDataAnterior): ?>
                                <span style="font-size: 11px; color: #94a3b8;">📅 Somente visualização</span>
                            <?php else: ?>
                                <span style="font-size: 11px; color: #94a3b8;">🔒 Bloqueado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Atualizar o resumo com os contadores reais -->
                    <?php 
                    $resumo['presentes'] = $contadorPresentes;
                    $resumo['ausentes'] = $contadorAusentes;
                    $resumo['sem_saida'] = $contadorSemSaida;
                    $resumo['anulados'] = $contadorAnulados;
                    ?>
                    
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; background: white; border-radius: 12px; border: 1px solid #eef2f7; grid-column: 1 / -1;">
                        <p style="font-size: 48px; margin-bottom: 10px;">👥</p>
                        <p style="color: #999;">Nenhum funcionário ativo encontrado.</p>
                        <p style="margin-top: 10px;">
                            <a href="importar_forca.php" class="btn-gold">📥 Importar Força de Trabalho</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Legenda -->
            <div style="margin-top: 20px; padding: 15px; background: white; border-radius: 12px; border: 1px solid #eef2f7;">
                <div class="legenda-grid">
                    <span><span style="display: inline-block; width: 12px; height: 12px; background: #dbeafe; border-radius: 4px; vertical-align: middle;"></span> ✅ Ponto Feito (Entrada + Saída)</span>
                    <span><span style="display: inline-block; width: 12px; height: 12px; background: #fef3c7; border-radius: 4px; vertical-align: middle;"></span> ⏳ Pendente (só entrada)</span>
                    <span><span style="display: inline-block; width: 12px; height: 12px; background: #fee2e2; border-radius: 4px; vertical-align: middle;"></span> ❌ Ausente</span>
                    <span><span style="display: inline-block; width: 12px; height: 12px; background: #fef3c7; border-radius: 4px; vertical-align: middle;"></span> 🚫 Anulado</span>
                    <span><span style="display: inline-block; width: 12px; height: 12px; background: #10b981; border-radius: 4px; vertical-align: middle;"></span> 📱 Entrada Manual</span>
                    <span><span style="display: inline-block; width: 12px; height: 12px; background: #ef4444; border-radius: 4px; vertical-align: middle;"></span> 📱 Saída Manual</span>
                    <span><span style="display: inline-block; width: 12px; height: 12px; background: #8b5cf6; border-radius: 4px; vertical-align: middle;"></span> 📱 QR Code</span>
                    <span><span style="display: inline-block; width: 12px; height: 12px; background: #f1f5f9; border-radius: 4px; border: 1px solid #cbd5e1; vertical-align: middle;"></span> 🔒 Bloqueado</span>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>