<?php
// =============================================
// DASHBOARD PRINCIPAL DO RH
// =============================================

require_once '../../config/database.php';

// Configurar fuso horário Angola
date_default_timezone_set('Africa/Luanda');

// =============================================
// FUNÇÕES AUXILIARES
// =============================================

function getHoraAngola() {
    $timestamp = time() + 3600;
    return date('H:i:s', $timestamp);
}

function getDataAngola() {
    $timestamp = time() + 3600;
    return date('Y-m-d', $timestamp);
}

// =============================================
// ESTATÍSTICAS GERAIS
// =============================================

// Total de funcionários
$stmtTotal = $pdo->query("SELECT COUNT(*) as total FROM forca_trabalho");
$totalFuncionarios = $stmtTotal->fetch()['total'];

// Funcionários por status
$stmtAtivos = $pdo->query("SELECT COUNT(*) as total FROM forca_trabalho WHERE status = 'ativo'");
$totalAtivos = $stmtAtivos->fetch()['total'];

$stmtInativos = $pdo->query("SELECT COUNT(*) as total FROM forca_trabalho WHERE status = 'inativo'");
$totalInativos = $stmtInativos->fetch()['total'];

$stmtFerias = $pdo->query("SELECT COUNT(*) as total FROM forca_trabalho WHERE status = 'ferias'");
$totalFerias = $stmtFerias->fetch()['total'];

// Presenças de hoje
$dataHoje = getDataAngola();
$stmtPresencas = $pdo->query("SELECT 
    COUNT(CASE WHEN status = 'presente' THEN 1 END) as presentes,
    COUNT(CASE WHEN status = 'ausente' THEN 1 END) as ausentes,
    COUNT(CASE WHEN status = 'atraso' THEN 1 END) as atrasos
    FROM presenca_qr WHERE data = '$dataHoje'");
$presencasHoje = $stmtPresencas->fetch();

// Dispositivos registrados
$stmtDispositivos = $pdo->query("SELECT COUNT(*) as total FROM dispositivos_funcionarios");
$totalDispositivos = $stmtDispositivos->fetch()['total'];

// QR Codes gerados hoje
$stmtQrHoje = $pdo->query("SELECT COUNT(*) as total FROM qr_codes_diarios WHERE data = '$dataHoje'");
$totalQrHoje = $stmtQrHoje->fetch()['total'];

// =============================================
// ÚLTIMOS REGISTROS
// =============================================

$stmtUltimos = $pdo->query("
    SELECT p.*, f.nome_completo, f.numero_agente, f.categoria_actual 
    FROM presenca_qr p 
    JOIN forca_trabalho f ON p.funcionario_id = f.id 
    ORDER BY p.data DESC, p.hora_entrada DESC 
    LIMIT 10
");
$ultimosRegistros = $stmtUltimos->fetchAll();

// =============================================
// RESUMO POR FUNCIONÁRIO (Últimos 30 dias)
// =============================================

$dataLimite = date('Y-m-d', strtotime('-30 days'));
$stmtResumo = $pdo->query("
    SELECT 
        f.id,
        f.nome_completo,
        f.numero_agente,
        COUNT(p.id) as total_dias,
        SUM(CASE WHEN p.status = 'presente' THEN 1 ELSE 0 END) as presentes,
        SUM(CASE WHEN p.status = 'ausente' THEN 1 ELSE 0 END) as ausentes,
        SUM(p.horas_trabalhadas) as total_horas
    FROM forca_trabalho f
    LEFT JOIN presenca_qr p ON f.id = p.funcionario_id AND p.data >= '$dataLimite'
    WHERE f.status = 'ativo'
    GROUP BY f.id
    ORDER BY presentes DESC
    LIMIT 10
");
$resumoFuncionarios = $stmtResumo->fetchAll();

// =============================================
// EFETIVIDADE GERAL
// =============================================

$stmtEfetividade = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status = 'presente' THEN 1 END) as presentes,
        COUNT(CASE WHEN status = 'ausente' THEN 1 END) as ausentes
    FROM presenca_qr 
    WHERE data >= '$dataLimite'
");
$efetividadeData = $stmtEfetividade->fetch();
$totalRegistros = ($efetividadeData['presentes'] + $efetividadeData['ausentes']);
$efetividadeGeral = $totalRegistros > 0 ? round(($efetividadeData['presentes'] / $totalRegistros) * 100, 2) : 0;

// =============================================
// FUNCIONÁRIOS SEM DISPOSITIVO
// =============================================

$stmtSemDispositivo = $pdo->query("
    SELECT COUNT(*) as total 
    FROM forca_trabalho f 
    WHERE f.status = 'ativo' 
    AND f.id NOT IN (SELECT funcionario_id FROM dispositivos_funcionarios)
");
$totalSemDispositivo = $stmtSemDispositivo->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard RH - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== RESET E BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f0f4f8; 
            color: #1a2332;
            display: flex;
            min-height: 100vh;
        }
        
        /* ===== CONTEÚDO PRINCIPAL ===== */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 20px;
            max-width: calc(100% - 280px);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        .page-title { font-size: 28px; font-weight: 800; color: #1a2332; }
        .page-title span { color: #c9a84c; }
        .page-subtitle { color: #64748b; font-size: 14px; margin-top: 4px; }
        .page-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        
        /* ===== MENU RH ===== */
        .rh-menu {
            background: white;
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            align-items: center;
        }
        .rh-menu a {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            color: #4a5568;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .rh-menu a:hover { background: rgba(197, 165, 50, 0.1); color: #c9a84c; transform: translateY(-2px); }
        .rh-menu a.active {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            font-weight: 600;
            box-shadow: 0 2px 15px rgba(197, 165, 50, 0.3);
        }
        .rh-menu a .menu-icon { font-size: 16px; }
        .rh-menu .menu-divider { width: 1px; height: 25px; background: #e2e8f0; margin: 0 5px; }
        
        /* ===== CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .stat-card .number { font-size: 28px; font-weight: 800; color: #1a2332; }
        .stat-card .label { font-size: 12px; color: #94a3b8; margin-top: 4px; }
        .stat-card .icon { font-size: 24px; display: block; margin-bottom: 8px; }
        .stat-card.primary { border-left: 4px solid #3498db; }
        .stat-card.success { border-left: 4px solid #2ecc71; }
        .stat-card.danger { border-left: 4px solid #e74c3c; }
        .stat-card.warning { border-left: 4px solid #f39c12; }
        .stat-card.purple { border-left: 4px solid #8b5cf6; }
        .stat-card.gold { border-left: 4px solid #c9a84c; }
        
        /* ===== BOTÕES ===== */
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
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
        .btn-purple:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3); }
        
        .btn-blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
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
        .btn-blue:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3); }
        
        /* ===== TABELAS ===== */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eef2f7;
            overflow-x: auto;
        }
        .table-container table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table-container thead { background: #f8fafc; }
        .table-container th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-container td { padding: 10px 16px; border-bottom: 1px solid #f1f5f9; }
        .table-container tr:hover { background: #f8fafc; }
        
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-badge.presente { background: #d1fae5; color: #065f46; }
        .status-badge.ausente { background: #fee2e2; color: #991b1b; }
        .status-badge.atraso { background: #fef3c7; color: #92400e; }
        .status-badge.ativo { background: #d1fae5; color: #065f46; }
        .status-badge.inativo { background: #fee2e2; color: #991b1b; }
        .status-badge.ferias { background: #fef3c7; color: #92400e; }
        
        /* ===== GRID DE RESULTADOS ===== */
        .result-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .result-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #eef2f7;
            transition: all 0.3s ease;
        }
        .result-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .result-card .nome { font-weight: 600; font-size: 15px; color: #1a2332; }
        .result-card .info { color: #94a3b8; font-size: 12px; margin: 4px 0 8px; }
        .result-card .stats { display: flex; justify-content: space-around; margin: 8px 0; }
        .result-card .stats .item { text-align: center; }
        .result-card .stats .item .num { font-size: 18px; font-weight: 700; }
        .result-card .stats .item .label { font-size: 10px; color: #94a3b8; }
        .result-card .stats .item.presente .num { color: #2ecc71; }
        .result-card .stats .item.ausente .num { color: #e74c3c; }
        .result-card .progress-bar { height: 6px; background: #eef2f7; border-radius: 4px; overflow: hidden; margin-top: 8px; }
        .result-card .progress-bar .fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }
        
        /* ===== ALERTAS ===== */
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .alert a { color: #c9a84c; font-weight: 600; text-decoration: none; }
        .alert a:hover { text-decoration: underline; }
        
        /* ===== AÇÕES RÁPIDAS ===== */
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            justify-content: center;
        }
        
        /* ===== FOOTER ===== */
        .footer {
            text-align: center;
            padding: 20px 0;
            margin-top: 30px;
            border-top: 1px solid #eef2f7;
            color: #94a3b8;
            font-size: 14px;
        }
        .footer strong { color: #c9a84c; }
        
        /* ===== RESPONSIVO ===== */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                max-width: 100%;
                padding: 15px;
            }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 768px) {
            .rh-menu { flex-direction: column; align-items: stretch; }
            .rh-menu .menu-divider { display: none; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .page-actions { width: 100%; }
            .page-actions a { flex: 1; justify-content: center; font-size: 12px; padding: 8px 12px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 15px; }
            .stat-card .number { font-size: 22px; }
            .quick-actions { flex-direction: column; }
            .quick-actions a { justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .main-content { padding: 10px; }
            .page-title { font-size: 22px; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <!-- ============================================
         CONTEÚDO PRINCIPAL
         ============================================ -->
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">👥 <span>RH</span> Dashboard</h1>
                    <p class="page-subtitle">Visão geral completa dos recursos humanos</p>
                </div>
                <div class="page-actions">
                    <a href="add_funcionario.php" class="btn-gold"><i class="fas fa-user-plus"></i> Novo Funcionário</a>
                    <a href="presenca_qr.php" class="btn-purple"><i class="fas fa-qrcode"></i> Presença QR</a>
                    <a href="assiduidade.php" class="btn-blue"><i class="fas fa-chart-bar"></i> Assiduidade</a>
                </div>
            </div>
            
            <!-- Menu RH -->
            <div class="rh-menu">
                <a href="index.php" class="active">
                    <span class="menu-icon">📊</span> Dashboard
                </a>
                <a href="funcionarios.php">
                    <span class="menu-icon">👤</span> Funcionários
                </a>
                <a href="add_funcionario.php">
                    <span class="menu-icon">➕</span> Novo
                </a>
                <div class="menu-divider"></div>
                <a href="presenca_qr.php">
                    <span class="menu-icon">📱</span> Presença QR
                </a>
                <a href="horarios.php">
                    <span class="menu-icon">🕐</span> Horários
                </a>
                <a href="assiduidade.php">
                    <span class="menu-icon">📊</span> Assiduidade
                </a>
                <div class="menu-divider"></div>
                <a href="folha_pagamento.php">
                    <span class="menu-icon">💰</span> Folha
                </a>

                <a href="<?= SITE_URL ?>modules/rh/descontos_beneficios.php" class="<?= $current_page == 'descontos_beneficios' ? 'active' : '' ?>">
                    <span class="nav-icon">🏦</span>
                    <span class="nav-text">Descontos e Benefícios</span>
                </a>


                <a href="relatorios.php">
                    <span class="menu-icon">📈</span> Relatórios
                </a>
                <a href="gerar_passes.php">
                    <span class="menu-icon">🪪</span> Passes
                </a>

                         
            </div>


                         
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <span class="icon">👥</span>
                    <div class="number"><?= $totalFuncionarios ?></div>
                    <div class="label">Total Funcionários</div>
                </div>
                <div class="stat-card success">
                    <span class="icon">✅</span>
                    <div class="number"><?= $totalAtivos ?></div>
                    <div class="label">Ativos</div>
                </div>
                <div class="stat-card warning">
                    <span class="icon">🏖️</span>
                    <div class="number"><?= $totalFerias ?></div>
                    <div class="label">Férias</div>
                </div>
                <div class="stat-card danger">
                    <span class="icon">⛔</span>
                    <div class="number"><?= $totalInativos ?></div>
                    <div class="label">Inativos</div>
                </div>
                <div class="stat-card success">
                    <span class="icon">📌</span>
                    <div class="number"><?= $presencasHoje['presentes'] ?? 0 ?></div>
                    <div class="label">Presentes Hoje</div>
                </div>
                <div class="stat-card danger">
                    <span class="icon">❌</span>
                    <div class="number"><?= $presencasHoje['ausentes'] ?? 0 ?></div>
                    <div class="label">Ausentes Hoje</div>
                </div>
                <div class="stat-card purple">
                    <span class="icon">📱</span>
                    <div class="number"><?= $totalDispositivos ?></div>
                    <div class="label">Dispositivos Registrados</div>
                </div>
                <div class="stat-card gold">
                    <span class="icon">📈</span>
                    <div class="number"><?= $efetividadeGeral ?>%</div>
                    <div class="label">Efetividade Geral</div>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if ($totalSemDispositivo > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span><strong>Atenção!</strong> <?= $totalSemDispositivo ?> funcionários ainda não registraram seu dispositivo. <a href="presenca_qr.php">Gerenciar QR Code</a></span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($_GET['success']) ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Últimos Registros -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; flex-wrap: wrap; gap: 10px;">
                <h3 style="color: #1a2332;">📋 Últimos Registros de Presença</h3>
                <a href="assiduidade.php" class="btn-outline" style="padding: 6px 16px; font-size: 13px;">Ver Todos →</a>
            </div>
            
            <div class="table-container" style="margin-top: 10px;">
                <table>
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Nº Agente</th>
                            <th>Data</th>
                            <th>Entrada</th>
                            <th>Saída</th>
                            <th>Horas</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ultimosRegistros) > 0): ?>
                            <?php foreach($ultimosRegistros as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['nome_completo']) ?></strong></td>
                                <td><?= htmlspecialchars($r['numero_agente']) ?></td>
                                <td><?= date('d/m/Y', strtotime($r['data'])) ?></td>
                                <td><?= $r['hora_entrada'] ? date('H:i', strtotime($r['hora_entrada'])) : '—' ?></td>
                                <td><?= $r['hora_saida'] ? date('H:i', strtotime($r['hora_saida'])) : '—' ?></td>
                                <td><?= $r['horas_trabalhadas'] ? number_format($r['horas_trabalhadas'], 2, ',', '.') . 'h' : '—' ?></td>
                                <td><span class="status-badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center; padding: 30px; color: #999;">Nenhum registro de presença encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Resumo por Funcionário -->
            <h3 style="margin-top: 30px; color: #1a2332;">🏆 Top Funcionários (Últimos 30 Dias)</h3>
            
            <div class="result-grid">
                <?php if (count($resumoFuncionarios) > 0): ?>
                    <?php foreach($resumoFuncionarios as $r): 
                        $percentual = $r['total_dias'] > 0 ? round(($r['presentes'] / $r['total_dias']) * 100, 2) : 0;
                    ?>
                    <div class="result-card">
                        <div class="nome"><?= htmlspecialchars($r['nome_completo']) ?></div>
                        <div class="info">Nº: <?= htmlspecialchars($r['numero_agente']) ?></div>
                        <div class="stats">
                            <div class="item presente">
                                <div class="num"><?= $r['presentes'] ?></div>
                                <div class="label">✅ Presentes</div>
                            </div>
                            <div class="item ausente">
                                <div class="num"><?= $r['ausentes'] ?></div>
                                <div class="label">❌ Ausentes</div>
                            </div>
                            <div class="item">
                                <div class="num"><?= number_format($r['total_horas'] ?? 0, 1) ?></div>
                                <div class="label">⏱️ Horas</div>
                            </div>
                        </div>
                        <div style="font-size: 12px; color: #64748b; text-align: center;">
                            Efetividade: <strong style="color: <?= $percentual >= 80 ? '#2ecc71' : ($percentual >= 50 ? '#f39c12' : '#e74c3c') ?>">
                                <?= $percentual ?>%
                            </strong>
                        </div>
                        <div class="progress-bar">
                            <div class="fill" style="width: <?= $percentual ?>%; <?= $percentual >= 80 ? 'background: linear-gradient(90deg, #2ecc71, #27ae60);' : ($percentual >= 50 ? 'background: linear-gradient(90deg, #f39c12, #e67e22);' : 'background: linear-gradient(90deg, #e74c3c, #c0392b);') ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px; background: white; border-radius: 12px; border: 1px solid #eef2f7; grid-column: 1 / -1; color: #999;">
                        Nenhum dado disponível para exibir o resumo.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Ações Rápidas -->
            <div class="quick-actions">
                <a href="add_funcionario.php" class="btn-gold"><i class="fas fa-user-plus"></i> Novo Funcionário</a>
                <a href="presenca_qr.php" class="btn-purple"><i class="fas fa-qrcode"></i> Presença QR</a>
                <a href="assiduidade.php" class="btn-blue"><i class="fas fa-chart-bar"></i> Assiduidade</a>
                <a href="horarios.php" class="btn-outline"><i class="fas fa-clock"></i> Horários</a>
                <a href="importar_forca.php" class="btn-outline"><i class="fas fa-file-import"></i> Importar</a>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                © <?= date('Y') ?> <strong>SoftGest Web</strong> - Sistema de Gestão Empresarial
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // =============================================
        // TOGGLE SIDEBAR PARA MOBILE
        // =============================================
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) {
                sidebar.classList.toggle('open');
                if (overlay) {
                    overlay.classList.toggle('active');
                }
            }
        }
        
        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) {
                sidebar.classList.remove('open');
                if (overlay) {
                    overlay.classList.remove('active');
                }
            }
        }
        
        // Fechar sidebar ao redimensionar para desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                closeSidebar();
            }
        });
        
        console.log('📊 Dashboard RH carregado com sucesso!');
    </script>
</body>
</html>