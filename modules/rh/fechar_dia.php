<?php
// fechar_dia.php - Fechamento diário de presenças
require_once '../../config/database.php';

// =============================================
// CONFIGURAÇÃO DE FUSO HORÁRIO - ANGOLA (UTC+1)
// =============================================
date_default_timezone_set('Africa/Luanda');
putenv('TZ=Africa/Luanda');

if (date_default_timezone_get() !== 'Africa/Luanda') {
    date_default_timezone_set('Etc/GMT-1');
}

function getDataAngola() {
    $timestamp = time() + 3600;
    return date('Y-m-d', $timestamp);
}

// =============================================
// GERENCIAMENTO DE SESSÃO - CORRIGIDO
// =============================================

// Verificar se a sessão já está ativa antes de iniciar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado - CORRIGIDO
$user_logado = false;
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $user_logado = true;
}

// Se NÃO estiver logado, redirecionar para login
if (!$user_logado) {
    header('Location: ../../login.php?error=' . urlencode('Por favor, faça login primeiro.'));
    exit;
}

// =============================================
// VERIFICAR SE A TABELA FECHAMENTO_DIARIO EXISTE
// =============================================

function tabelaExiste($tabela) {
    global $pdo;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Se a tabela não existir, criar
if (!tabelaExiste('fechamento_diario')) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS fechamento_diario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE NOT NULL,
            hora_fechamento TIME NOT NULL,
            total_funcionarios INT DEFAULT 0,
            presentes INT DEFAULT 0,
            ausentes INT DEFAULT 0,
            anulados INT DEFAULT 0,
            realizado_por INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_data (data)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        // Se não conseguir criar, continuar com erro controlado
    }
}

$dataHoje = getDataAngola();
$horaAtual = date('H:i:s', time() + 3600);
$dataHojeFormatada = date('d/m/Y', strtotime($dataHoje));

// =============================================
// PROCESSAR FECHAMENTO
// =============================================

if (isset($_POST['fechar_dia'])) {
    try {
        $pdo->beginTransaction();
        
        // 1. Buscar todos os funcionários ativos
        $stmt = $pdo->query("SELECT id, nome_completo FROM forca_trabalho WHERE status = 'ativo'");
        $funcionarios = $stmt->fetchAll();
        
        if (count($funcionarios) == 0) {
            $mensagem_erro = "⚠️ Nenhum funcionário ativo encontrado para fechamento.";
        } else {
            $atualizados = 0;
            $anulados = 0;
            $faltas = 0;
            
            foreach ($funcionarios as $func) {
                // Verificar presença de hoje
                $stmt = $pdo->prepare("SELECT * FROM presenca_qr WHERE funcionario_id = ? AND data = ?");
                $stmt->execute([$func['id'], $dataHoje]);
                $presenca = $stmt->fetch();
                
                if ($presenca) {
                    // Se tem presença registrada
                    if ($presenca['status'] === 'presente') {
                        if (empty($presenca['hora_saida'])) {
                            // Registrou entrada mas NÃO registrou saída -> ANULAR DIA
                            $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                                status = 'anulado',
                                observacao = 'Dia anulado - Não registrou saída',
                                hora_saida = ?,
                                qr_scanned = 0,
                                qr_scan_time = NOW()
                                WHERE id = ?");
                            $stmt->execute([$horaAtual, $presenca['id']]);
                            $anulados++;
                        } else {
                            // Já tem saída registrada -> manter como presente
                            $atualizados++;
                        }
                    } elseif ($presenca['status'] === 'ausente') {
                        // Estava ausente -> manter ausente
                        $faltas++;
                    } elseif ($presenca['status'] === 'atraso') {
                        // Estava com atraso -> verificar se tem saída
                        if (empty($presenca['hora_saida'])) {
                            $stmt = $pdo->prepare("UPDATE presenca_qr SET 
                                status = 'anulado',
                                observacao = 'Dia anulado - Não registrou saída',
                                hora_saida = ?,
                                qr_scanned = 0,
                                qr_scan_time = NOW()
                                WHERE id = ?");
                            $stmt->execute([$horaAtual, $presenca['id']]);
                            $anulados++;
                        } else {
                            $atualizados++;
                        }
                    }
                } else {
                    // Não tem registro de presença -> MARCAR FALTA
                    $stmt = $pdo->prepare("INSERT INTO presenca_qr 
                        (funcionario_id, data, status, observacao, qr_scanned, qr_scan_time) 
                        VALUES (?, ?, 'ausente', 'Falta - Não compareceu', 0, NOW())");
                    $stmt->execute([$func['id'], $dataHoje]);
                    $faltas++;
                }
            }
            
            // Registrar o fechamento
            $stmt = $pdo->prepare("INSERT INTO fechamento_diario 
                (data, hora_fechamento, total_funcionarios, presentes, ausentes, anulados, realizado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $dataHoje,
                $horaAtual,
                count($funcionarios),
                $atualizados,
                $faltas,
                $anulados,
                $_SESSION['user_id'] ?? 1
            ]);
            
            $pdo->commit();
            
            $mensagem_sucesso = "✅ Dia fechado com sucesso!<br>
                📊 Resumo: <br>
                👥 Total: " . count($funcionarios) . "<br>
                ✅ Presentes: $atualizados<br>
                ❌ Faltas: $faltas<br>
                ⚠️ Anulados (sem saída): $anulados";
        }
            
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem_erro = "❌ Erro ao fechar o dia: " . $e->getMessage();
    }
}

// =============================================
// BUSCAR FECHAMENTOS ANTERIORES
// =============================================

$fechamentos = [];
try {
    if (tabelaExiste('fechamento_diario')) {
        $stmtFechamentos = $pdo->query("SELECT * FROM fechamento_diario ORDER BY data DESC LIMIT 30");
        $fechamentos = $stmtFechamentos->fetchAll();
    }
} catch (PDOException $e) {
    // Ignorar erro se a tabela não existir
}

// =============================================
// BUSCAR STATUS ATUAL
// =============================================

$statusAtual = ['total' => 0, 'presentes' => 0, 'ausentes' => 0, 'sem_saida' => 0, 'anulados' => 0];
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN p.status = 'presente' AND p.hora_saida IS NOT NULL THEN 1 ELSE 0 END) as presentes,
        SUM(CASE WHEN p.status = 'ausente' THEN 1 ELSE 0 END) as ausentes,
        SUM(CASE WHEN p.status = 'presente' AND p.hora_saida IS NULL THEN 1 ELSE 0 END) as sem_saida,
        SUM(CASE WHEN p.status = 'anulado' THEN 1 ELSE 0 END) as anulados
        FROM forca_trabalho f
        LEFT JOIN presenca_qr p ON f.id = p.funcionario_id AND p.data = '$dataHoje'
        WHERE f.status = 'ativo'");
    $statusAtual = $stmt->fetch();
    if (!$statusAtual) {
        $statusAtual = ['total' => 0, 'presentes' => 0, 'ausentes' => 0, 'sem_saida' => 0, 'anulados' => 0];
    }
} catch (PDOException $e) {
    // Ignorar erro
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fechamento Diário - Presenças</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        }
        
        .container { max-width: 1000px; margin: 0 auto; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .page-title { font-size: 28px; font-weight: 800; color: #1a2332; }
        .page-title span { color: #c9a84c; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #eef2f7;
            margin-bottom: 20px;
        }
        
        .card h3 { margin-bottom: 15px; color: #1a2332; }
        
        .btn {
            padding: 12px 30px;
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
        
        .btn-primary {
            background: #c9a84c;
            color: #1a2332;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3); }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
        
        .btn-outline {
            background: transparent;
            color: #c9a84c;
            padding: 12px 30px;
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
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .stat-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card .number { font-size: 32px; font-weight: 800; }
        .stat-card .label { color: #94a3b8; font-size: 13px; margin-top: 4px; }
        .stat-card .number.green { color: #10b981; }
        .stat-card .number.red { color: #ef4444; }
        .stat-card .number.yellow { color: #f59e0b; }
        .stat-card .number.blue { color: #3b82f6; }
        .stat-card .number.orange { color: #f97316; }
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table th { background: #f8fafc; padding: 10px; text-align: left; border-bottom: 2px solid #eef2f7; }
        table td { padding: 10px; border-bottom: 1px solid #eef2f7; }
        table tr:hover { background: #f8fafc; }
        
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.info { background: #dbeafe; color: #1e40af; }
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; max-width: 100%; padding: 15px; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
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
            <div class="page-header">
                <h1 class="page-title">🔒 <span>Fechamento</span> Diário</h1>
                <div>
                    <a href="presenca_qr.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
            </div>
            
            <?php if (isset($mensagem_sucesso)): ?>
                <div class="alert alert-success"><?= $mensagem_sucesso ?></div>
            <?php endif; ?>
            
            <?php if (isset($mensagem_erro)): ?>
                <div class="alert alert-error"><?= $mensagem_erro ?></div>
            <?php endif; ?>
            
            <!-- Status atual -->
            <div class="card">
                <h3>📊 Status do Dia - <?= $dataHojeFormatada ?></h3>
                <p style="color: #64748b; margin-bottom: 15px;">
                    <strong>Hora atual:</strong> <?= date('H:i:s', time() + 3600) ?>
                </p>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number blue"><?= $statusAtual['total'] ?? 0 ?></div>
                        <div class="label">👥 Total</div>
                    </div>
                    <div class="stat-card">
                        <div class="number green"><?= $statusAtual['presentes'] ?? 0 ?></div>
                        <div class="label">✅ Presentes</div>
                    </div>
                    <div class="stat-card">
                        <div class="number red"><?= $statusAtual['ausentes'] ?? 0 ?></div>
                        <div class="label">❌ Ausentes</div>
                    </div>
                    <div class="stat-card">
                        <div class="number yellow"><?= $statusAtual['sem_saida'] ?? 0 ?></div>
                        <div class="label">⚠️ Sem Saída</div>
                    </div>
                    <div class="stat-card">
                        <div class="number orange"><?= $statusAtual['anulados'] ?? 0 ?></div>
                        <div class="label">🚫 Anulados</div>
                    </div>
                </div>
                
                <?php if (($statusAtual['total'] ?? 0) > 0): ?>
                    <div style="margin-top: 15px; text-align: center;">
                        <form method="POST" onsubmit="return confirm('⚠️ Tem certeza que deseja fechar o dia?\n\nEsta ação irá:\n- Marcar falta para quem não registrou presença\n- Anular o dia de quem não registrou saída\n\nEsta ação não pode ser desfeita!')">
                            <button type="submit" name="fechar_dia" class="btn btn-danger">
                                🔒 Fechar Dia
                            </button>
                        </form>
                        <p style="font-size: 12px; color: #94a3b8; margin-top: 10px;">
                            ⚠️ Após fechar o dia, os registros não podem mais ser alterados
                        </p>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 15px; background: #f1f5f9; border-radius: 8px; color: #64748b;">
                        <p>📋 Nenhum funcionário ativo encontrado para fechamento.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Histórico de fechamentos -->
            <div class="card">
                <h3>📜 Histórico de Fechamentos</h3>
                
                <?php if (count($fechamentos) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Hora</th>
                                    <th>Total</th>
                                    <th>Presentes</th>
                                    <th>Ausentes</th>
                                    <th>Anulados</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fechamentos as $fech): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($fech['data'])) ?></td>
                                    <td><?= date('H:i', strtotime($fech['hora_fechamento'])) ?></td>
                                    <td><strong><?= $fech['total_funcionarios'] ?></strong></td>
                                    <td><span class="badge success"><?= $fech['presentes'] ?></span></td>
                                    <td><span class="badge danger"><?= $fech['ausentes'] ?></span></td>
                                    <td><span class="badge warning"><?= $fech['anulados'] ?></span></td>
                                    <td><span class="badge info">Fechado</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px; color: #94a3b8;">
                        <p style="font-size: 40px; margin-bottom: 10px;">📭</p>
                        <p>Nenhum fechamento realizado ainda.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>