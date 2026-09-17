<?php
// assiduidade.php - Relatório de Assiduidade e Efetividade
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
// FILTROS
// =============================================

$filtro_data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$filtro_data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$filtro_funcionario = $_GET['funcionario'] ?? '';
$filtro_mes = $_GET['mes'] ?? date('m');
$filtro_ano = $_GET['ano'] ?? date('Y');

// =============================================
// BUSCAR DADOS
// =============================================

// Buscar funcionários para o filtro
$stmtFuncionarios = $pdo->query("SELECT id, nome_completo FROM forca_trabalho WHERE status = 'ativo' ORDER BY nome_completo");
$funcionarios_lista = $stmtFuncionarios->fetchAll();

// Construir query com filtros
$sql = "SELECT 
    f.id as funcionario_id,
    f.nome_completo,
    f.numero_agente,
    f.categoria_actual,
    f.funcao,
    f.instituicao,
    p.data,
    p.hora_entrada,
    p.hora_saida,
    p.status as presenca_status,
    p.horas_trabalhadas,
    p.qr_scanned,
    p.scan_type,
    DATE_FORMAT(p.data, '%d/%m/%Y') as data_formatada,
    CASE 
        WHEN p.hora_entrada IS NOT NULL AND p.hora_saida IS NOT NULL THEN 'Completo'
        WHEN p.hora_entrada IS NOT NULL AND p.hora_saida IS NULL THEN 'Sem Saída'
        WHEN p.hora_entrada IS NULL AND p.hora_saida IS NULL THEN 'Ausente'
        ELSE 'Pendente'
    END as status_registro
FROM forca_trabalho f
LEFT JOIN presenca_qr p ON f.id = p.funcionario_id 
WHERE f.status = 'ativo'";

// Aplicar filtros
if (!empty($filtro_funcionario)) {
    $sql .= " AND f.id = " . intval($filtro_funcionario);
}

if (!empty($filtro_data_inicio) && !empty($filtro_data_fim)) {
    $sql .= " AND p.data BETWEEN '" . $filtro_data_inicio . "' AND '" . $filtro_data_fim . "'";
}

if (!empty($filtro_mes) && !empty($filtro_ano)) {
    $sql .= " AND MONTH(p.data) = " . intval($filtro_mes) . " AND YEAR(p.data) = " . intval($filtro_ano);
}

$sql .= " ORDER BY f.nome_completo, p.data DESC";

$stmt = $pdo->query($sql);
$registros = $stmt->fetchAll();

// =============================================
// CALCULAR ESTATÍSTICAS
// =============================================

$total_funcionarios = count($funcionarios_lista);
$total_presentes = 0;
$total_ausentes = 0;
$total_horas = 0;
$total_registros = count($registros);

foreach ($registros as $r) {
    if ($r['presenca_status'] === 'presente') {
        $total_presentes++;
    } else {
        $total_ausentes++;
    }
    $total_horas += floatval($r['horas_trabalhadas'] ?? 0);
}

// Calcular efetividade
$efetividade = $total_registros > 0 ? round(($total_presentes / max($total_registros, 1)) * 100, 2) : 0;

// =============================================
// RESUMO POR FUNCIONÁRIO
// =============================================

$resumo_funcionarios = [];
foreach ($registros as $r) {
    $id = $r['funcionario_id'];
    if (!isset($resumo_funcionarios[$id])) {
        $resumo_funcionarios[$id] = [
            'nome' => $r['nome_completo'],
            'numero_agente' => $r['numero_agente'],
            'total_dias' => 0,
            'presentes' => 0,
            'ausentes' => 0,
            'horas_trabalhadas' => 0,
            'status' => []
        ];
    }
    $resumo_funcionarios[$id]['total_dias']++;
    if ($r['presenca_status'] === 'presente') {
        $resumo_funcionarios[$id]['presentes']++;
    } else {
        $resumo_funcionarios[$id]['ausentes']++;
    }
    $resumo_funcionarios[$id]['horas_trabalhadas'] += floatval($r['horas_trabalhadas'] ?? 0);
    $resumo_funcionarios[$id]['status'][] = $r['presenca_status'] ?? 'ausente';
}

// Buscar nome do funcionário se filtrado
$nome_funcionario = 'Todos';
if (!empty($filtro_funcionario)) {
    $stmt = $pdo->prepare("SELECT nome_completo FROM forca_trabalho WHERE id = ?");
    $stmt->execute([$filtro_funcionario]);
    $func = $stmt->fetch();
    if ($func) $nome_funcionario = $func['nome_completo'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assiduidade e Efetividade - SoftGest Web</title>
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
            margin-bottom: 20px;
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
        
        .btn-export {
            padding: 10px 24px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-export:hover { background: #059669; transform: translateY(-2px); }
        
        .btn-print {
            padding: 10px 24px;
            background: #8b5cf6;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-print:hover { background: #7c3aed; transform: translateY(-2px); }
        
        /* ===== FILTROS ===== */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filters .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 120px;
        }
        .filters .filter-group label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filters .filter-group input,
        .filters .filter-group select {
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            width: 100%;
        }
        .filters .filter-group input:focus,
        .filters .filter-group select:focus {
            border-color: #c9a84c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(197, 165, 50, 0.1);
        }
        .filters .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-filter {
            padding: 10px 30px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-filter:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3); }
        
        /* ===== TABELAS ===== */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eef2f7;
            margin-top: 15px;
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
            white-space: nowrap;
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
        .status-badge.completo { background: #d1fae5; color: #065f46; }
        .status-badge.sem-saida { background: #fef3c7; color: #92400e; }
        
        /* ===== RESULT GRID ===== */
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
            .filters { flex-direction: column; align-items: stretch; }
            .filters .filter-group { min-width: 100%; }
            .filters .filter-actions { flex-direction: column; }
            .filters .filter-actions button { width: 100%; justify-content: center; }
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
            .result-grid { grid-template-columns: 1fr; }
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
                    <h1 class="page-title">📊 <span>Assiduidade</span> e Efetividade</h1>
                    <p class="page-subtitle">Relatório completo de presenças, faltas e horas trabalhadas</p>
                </div>
                <div class="page-actions">
                    <a href="index.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Voltar</a>
                    <a href="presenca_qr.php" class="btn-purple"><i class="fas fa-qrcode"></i> Presença QR</a>
                </div>
            </div>
            
            <!-- Menu RH -->
            <div class="rh-menu">
                <a href="index.php">
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
                <a href="assiduidade.php" class="active">
                    <span class="menu-icon">📊</span> Assiduidade
                </a>
                <div class="menu-divider"></div>
                <a href="folha_pagamento.php">
                    <span class="menu-icon">💰</span> Folha
                </a>
                <a href="relatorios.php">
                    <span class="menu-icon">📈</span> Relatórios
                </a>
            </div>
            
            <!-- Cards de Resumo -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <span class="icon">👥</span>
                    <div class="number"><?= $total_funcionarios ?></div>
                    <div class="label">Total de Funcionários</div>
                </div>
                <div class="stat-card success">
                    <span class="icon">✅</span>
                    <div class="number"><?= $total_presentes ?></div>
                    <div class="label">Total de Presenças</div>
                </div>
                <div class="stat-card danger">
                    <span class="icon">❌</span>
                    <div class="number"><?= $total_ausentes ?></div>
                    <div class="label">Total de Ausências</div>
                </div>
                <div class="stat-card gold">
                    <span class="icon">📈</span>
                    <div class="number"><?= $efetividade ?>%</div>
                    <div class="label">Efetividade Geral</div>
                </div>
                <div class="stat-card purple">
                    <span class="icon">⏱️</span>
                    <div class="number"><?= number_format($total_horas, 1, ',', '.') ?>h</div>
                    <div class="label">Total de Horas</div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filters">
                <div class="filter-group">
                    <label>Período de</label>
                    <input type="date" name="data_inicio" id="data_inicio" value="<?= $filtro_data_inicio ?>">
                </div>
                <div class="filter-group">
                    <label>Até</label>
                    <input type="date" name="data_fim" id="data_fim" value="<?= $filtro_data_fim ?>">
                </div>
                <div class="filter-group">
                    <label>Mês</label>
                    <select name="mes" id="mes">
                        <option value="">Todos</option>
                        <?php for($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $filtro_mes == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                                <?= date('F', mktime(0,0,0,$m,1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Ano</label>
                    <select name="ano" id="ano">
                        <option value="">Todos</option>
                        <?php for($a = date('Y'); $a >= 2020; $a--): ?>
                            <option value="<?= $a ?>" <?= $filtro_ano == $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Funcionário</label>
                    <select name="funcionario" id="funcionario">
                        <option value="">Todos</option>
                        <?php foreach($funcionarios_lista as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $filtro_funcionario == $f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nome_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="btn-filter" onclick="aplicarFiltros()">🔍 Filtrar</button>
                    <button class="btn-export" onclick="exportarExcel()"><i class="fas fa-file-excel"></i> Exportar</button>
                    <button class="btn-print" onclick="imprimirRelatorio()"><i class="fas fa-print"></i> Imprimir</button>
                </div>
            </div>
            
            <!-- Tabela de Registros -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 10px;">
                <h3 style="color: #1a2332;">📋 Registros Detalhados</h3>
                <span style="font-size: 13px; color: #64748b;">
                    Mostrando <?= count($registros) ?> registros
                    <?php if ($nome_funcionario !== 'Todos'): ?>
                        para <strong><?= htmlspecialchars($nome_funcionario) ?></strong>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="table-container">
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
                            <th>Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($registros) > 0): ?>
                            <?php foreach($registros as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['nome_completo']) ?></strong></td>
                                <td><?= htmlspecialchars($r['numero_agente']) ?></td>
                                <td><?= $r['data_formatada'] ?></td>
                                <td><?= $r['hora_entrada'] ? date('H:i:s', strtotime($r['hora_entrada'])) : '—' ?></td>
                                <td><?= $r['hora_saida'] ? date('H:i:s', strtotime($r['hora_saida'])) : '—' ?></td>
                                <td><?= $r['horas_trabalhadas'] ? number_format($r['horas_trabalhadas'], 2, ',', '.') . 'h' : '—' ?></td>
                                <td>
                                    <span class="status-badge <?= $r['presenca_status'] ?? 'ausente' ?>">
                                        <?= ucfirst($r['presenca_status'] ?? 'ausente') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= strtolower(str_replace(' ', '-', $r['status_registro'])) ?>">
                                        <?= $r['status_registro'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                    <p style="font-size: 48px; margin-bottom: 10px;">📭</p>
                                    <p>Nenhum registro encontrado para os filtros selecionados.</p>
                                    <p style="margin-top: 10px; font-size: 13px;">
                                        Tente ajustar os filtros ou cadastrar presenças.
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Resumo por Funcionário -->
            <h3 style="margin-top: 30px; color: #1a2332;">👤 Resumo por Funcionário</h3>
            
            <div class="result-grid">
                <?php if (count($resumo_funcionarios) > 0): ?>
                    <?php foreach($resumo_funcionarios as $id => $resumo): 
                        $percentual = $resumo['total_dias'] > 0 ? round(($resumo['presentes'] / $resumo['total_dias']) * 100, 2) : 0;
                    ?>
                    <div class="result-card">
                        <div class="nome"><?= htmlspecialchars($resumo['nome']) ?></div>
                        <div class="info">Nº: <?= htmlspecialchars($resumo['numero_agente']) ?></div>
                        
                        <div class="stats">
                            <div class="item presente">
                                <div class="num"><?= $resumo['presentes'] ?></div>
                                <div class="label">✅ Presentes</div>
                            </div>
                            <div class="item ausente">
                                <div class="num"><?= $resumo['ausentes'] ?></div>
                                <div class="label">❌ Ausentes</div>
                            </div>
                            <div class="item">
                                <div class="num"><?= number_format($resumo['horas_trabalhadas'], 1, ',', '.') ?></div>
                                <div class="label">⏱️ Horas</div>
                            </div>
                        </div>
                        
                        <div style="font-size: 12px; color: #64748b; text-align: center;">
                            Total dias: <?= $resumo['total_dias'] ?> | 
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
            
            <!-- Footer -->
            <div class="footer">
                © <?= date('Y') ?> <strong>SoftGest Web</strong> - Sistema de Gestão Empresarial
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // =============================================
        // FUNÇÕES DE FILTRO E EXPORTAÇÃO
        // =============================================
        
        function aplicarFiltros() {
            const data_inicio = document.getElementById('data_inicio').value;
            const data_fim = document.getElementById('data_fim').value;
            const mes = document.getElementById('mes').value;
            const ano = document.getElementById('ano').value;
            const funcionario = document.getElementById('funcionario').value;
            
            let url = 'assiduidade.php?';
            if (data_inicio) url += 'data_inicio=' + data_inicio + '&';
            if (data_fim) url += 'data_fim=' + data_fim + '&';
            if (mes) url += 'mes=' + mes + '&';
            if (ano) url += 'ano=' + ano + '&';
            if (funcionario) url += 'funcionario=' + funcionario;
            
            window.location.href = url;
        }
        
        function exportarExcel() {
            const data_inicio = document.getElementById('data_inicio').value;
            const data_fim = document.getElementById('data_fim').value;
            const mes = document.getElementById('mes').value;
            const ano = document.getElementById('ano').value;
            const funcionario = document.getElementById('funcionario').value;
            
            let url = 'exportar_assiduidade.php?';
            if (data_inicio) url += 'data_inicio=' + data_inicio + '&';
            if (data_fim) url += 'data_fim=' + data_fim + '&';
            if (mes) url += 'mes=' + mes + '&';
            if (ano) url += 'ano=' + ano + '&';
            if (funcionario) url += 'funcionario=' + funcionario;
            
            window.open(url, '_blank');
        }
        
        function imprimirRelatorio() {
            const data_inicio = document.getElementById('data_inicio').value;
            const data_fim = document.getElementById('data_fim').value;
            const mes = document.getElementById('mes').value;
            const ano = document.getElementById('ano').value;
            const funcionario = document.getElementById('funcionario').value;
            
            let url = 'exportar_assiduidade.php?';
            if (data_inicio) url += 'data_inicio=' + data_inicio + '&';
            if (data_fim) url += 'data_fim=' + data_fim + '&';
            if (mes) url += 'mes=' + mes + '&';
            if (ano) url += 'ano=' + ano + '&';
            if (funcionario) url += 'funcionario=' + funcionario;
            url += '&imprimir=1';
            
            window.open(url, '_blank');
        }
        
        // =============================================
        // PERMITIR ENTER NOS CAMPOS
        // =============================================
        
        document.querySelectorAll('.filters input, .filters select').forEach(el => {
            el.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    aplicarFiltros();
                }
            });
        });
        
        // =============================================
        // FECHAR SIDEBAR MOBILE
        // =============================================
        
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
        
        console.log('📊 Assiduidade carregada com sucesso!');
        console.log('Total de registros: <?= count($registros) ?>');
        console.log('Efetividade: <?= $efetividade ?>%');
    </script>
</body>
</html>