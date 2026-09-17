<?php
// ============================================
// modules/escola/relatorios/turmas.php - Relatório Detalhado por Turma
// CORRIGIDO: Usando a coluna 'Sexo' da tabela alunos
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== FILTROS =====
$filtroTurma = $_GET['turma'] ?? '';
$filtroStatus = $_GET['status'] ?? 'todos';
$busca = $_GET['busca'] ?? '';

// ===== DADOS DAS TURMAS =====
$turmas = [];
$totalAlunosGeral = 0;
$totalTurmasAtivas = 0;
$totalM = 0;
$totalF = 0;

try {
    // CORREÇÃO: Usar 'Sexo' em vez de 'genero'
    $sql = "
        SELECT 
            t.id,
            t.nome,
            t.status,
            t.sala,
            t.ano_letivo,
            t.turno,
            COUNT(DISTINCT a.id) as total_alunos,
            COUNT(DISTINCT CASE WHEN a.status = 'ativo' THEN a.id END) as alunos_ativos,
            COUNT(DISTINCT CASE WHEN a.Sexo = 'M' THEN a.id END) as alunos_masculino,
            COUNT(DISTINCT CASE WHEN a.Sexo = 'F' THEN a.id END) as alunos_feminino
        FROM turmas t
        LEFT JOIN alunos a ON t.nome = a.TURMA
        WHERE t.status = 'ativa'
        GROUP BY t.id, t.nome, t.status, t.sala, t.ano_letivo, t.turno
        ORDER BY t.nome
    ";
    $turmas = $pdo->query($sql)->fetchAll();
    
    foreach ($turmas as $t) {
        $totalAlunosGeral += $t['total_alunos'];
        $totalM += $t['alunos_masculino'];
        $totalF += $t['alunos_feminino'];
    }
    $totalTurmasAtivas = count($turmas);
    
} catch (Exception $e) {
    // Fallback: Buscar apenas da tabela alunos
    try {
        $turmas = $pdo->query("
            SELECT 
                TURMA as nome,
                COUNT(DISTINCT id) as total_alunos,
                COUNT(DISTINCT CASE WHEN Sexo = 'M' THEN id END) as alunos_masculino,
                COUNT(DISTINCT CASE WHEN Sexo = 'F' THEN id END) as alunos_feminino,
                COUNT(DISTINCT CASE WHEN status = 'ativo' THEN id END) as alunos_ativos
            FROM alunos 
            WHERE status = 'ativo' AND TURMA IS NOT NULL AND TURMA != ''
            GROUP BY TURMA
            ORDER BY TURMA
        ")->fetchAll();
        
        foreach ($turmas as $t) {
            $totalAlunosGeral += $t['total_alunos'];
            $totalM += $t['alunos_masculino'];
            $totalF += $t['alunos_feminino'];
        }
        $totalTurmasAtivas = count($turmas);
    } catch (Exception $e2) {}
}

// ===== ALUNOS POR TURMA (DETALHADO) =====
$alunosPorTurma = [];
$turmaSelecionada = '';

if ($filtroTurma) {
    try {
        // CORREÇÃO: Usar 'Sexo' e DISTINCT
        $sql = "
            SELECT DISTINCT
                a.id,
                a.nome,
                a.Sexo as genero,
                a.data_nascimento,
                a.telefone,
                a.email,
                a.nome_pai,
                a.nome_mae,
                a.telefone_responsavel,
                a.status,
                a.data_matricula,
                a.foto,
                t.nome as turma_nome,
                t.sala,
                t.turno
            FROM alunos a
            LEFT JOIN turmas t ON t.nome = a.TURMA
            WHERE a.TURMA = :turma
        ";
        
        if ($filtroStatus !== 'todos') {
            $sql .= " AND a.status = :status";
        }
        
        if ($busca) {
            $sql .= " AND (a.nome LIKE :busca OR a.telefone LIKE :busca OR a.email LIKE :busca)";
        }
        
        $sql .= " ORDER BY a.nome";
        
        $stmt = $pdo->prepare($sql);
        $params = [':turma' => $filtroTurma];
        
        if ($filtroStatus !== 'todos') {
            $params[':status'] = $filtroStatus;
        }
        
        if ($busca) {
            $params[':busca'] = "%$busca%";
        }
        
        $stmt->execute($params);
        $alunosPorTurma = $stmt->fetchAll();
        $turmaSelecionada = $filtroTurma;
        
    } catch (Exception $e) {
        // Fallback
        try {
            $sql = "
                SELECT DISTINCT
                    id,
                    nome,
                    Sexo as genero,
                    data_nascimento,
                    telefone,
                    email,
                    nome_pai,
                    nome_mae,
                    telefone_responsavel,
                    status,
                    data_matricula,
                    foto,
                    TURMA as turma_nome,
                    SALA as sala,
                    Periodo as turno
                FROM alunos 
                WHERE TURMA = :turma
            ";
            
            if ($filtroStatus !== 'todos') {
                $sql .= " AND status = :status";
            }
            
            if ($busca) {
                $sql .= " AND (nome LIKE :busca OR telefone LIKE :busca OR email LIKE :busca)";
            }
            
            $sql .= " ORDER BY nome";
            
            $stmt = $pdo->prepare($sql);
            $params = [':turma' => $filtroTurma];
            
            if ($filtroStatus !== 'todos') {
                $params[':status'] = $filtroStatus;
            }
            
            if ($busca) {
                $params[':busca'] = "%$busca%";
            }
            
            $stmt->execute($params);
            $alunosPorTurma = $stmt->fetchAll();
            $turmaSelecionada = $filtroTurma;
            
        } catch (Exception $e2) {}
    }
}

// ===== CALCULAR GÊNERO NA LISTA FILTRADA =====
$totalMLista = 0;
$totalFLista = 0;
foreach ($alunosPorTurma as $aluno) {
    $sexo = trim(strtoupper($aluno['genero'] ?? ''));
    if ($sexo === 'M') {
        $totalMLista++;
    } elseif ($sexo === 'F') {
        $totalFLista++;
    }
}

// ===== INCLUIR HEADER =====
include '../includes/header_escola.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório por Turma - Complexo Escolar Castelo Reis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #1a2332;
        }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .page-header {
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            border-radius: 16px;
            padding: 30px 35px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 40px rgba(26, 35, 50, 0.2);
        }
        .page-header .title-group h1 {
            color: #fff;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header .title-group h1 i { color: #c9a84c; }
        .page-header .title-group .subtitle {
            color: rgba(255,255,255,0.7);
            font-size: 14px;
            margin-top: 5px;
        }
        .page-header .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            padding: 10px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.12);
            color: #fff;
            backdrop-filter: blur(10px);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #c9a84c;
            color: #1a2332;
        }
        .btn-primary:hover {
            background: #b8973a;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.4);
        }
        .btn-sm { padding: 6px 14px; font-size: 12px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #fff;
            padding: 22px 24px;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border-left: 5px solid #c9a84c;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        .stat-card .icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .stat-card .icon.blue { background: #ebf5fb; color: #3498db; }
        .stat-card .icon.green { background: #eafaf1; color: #27ae60; }
        .stat-card .icon.purple { background: #f4ecf7; color: #8e44ad; }
        .stat-card .icon.orange { background: #fef9e7; color: #f39c12; }
        .stat-card .icon.red { background: #fdedec; color: #e74c3c; }
        .stat-card .info h3 { font-size: 28px; font-weight: 700; color: #1a2332; margin: 0; line-height: 1.2; }
        .stat-card .info p { font-size: 13px; color: #94a3b8; margin: 0; }
        .stat-card.alunos { border-left-color: #8e44ad; }
        .stat-card.turmas { border-left-color: #f39c12; }
        .stat-card.masculino { border-left-color: #3498db; }
        .stat-card.feminino { border-left-color: #e74c3c; }
        
        .filters-section {
            background: #fff;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 30px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filters-section .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 180px;
        }
        .filters-section .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filters-section .form-group select,
        .filters-section .form-group input {
            padding: 10px 14px;
            border: 2px solid #eef2f7;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8fafc;
            color: #1a2332;
            width: 100%;
        }
        .filters-section .form-group select:focus,
        .filters-section .form-group input:focus {
            border-color: #c9a84c;
            outline: none;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(201, 168, 76, 0.1);
        }
        
        .table-container {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .table-container .table-header {
            padding: 18px 24px;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .table-container .table-header h2 {
            font-size: 18px;
            color: #1a2332;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .table-container .table-header h2 i { color: #c9a84c; }
        .table-container .table-header .info-badge {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #94a3b8;
        }
        .table-container .table-header .info-badge span { 
            background: #f8fafc;
            padding: 4px 14px;
            border-radius: 20px;
        }
        .table-container .table-header .info-badge span strong { color: #1a2332; }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .table th {
            background: #f8fafc;
            padding: 14px 18px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #eef2f7;
            white-space: nowrap;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .table tr:hover { background: #fafbfc; }
        .table tr:last-child td { border-bottom: none; }
        
        .aluno-foto {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #eef2f7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #94a3b8;
            object-fit: cover;
        }
        .aluno-foto img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-ativo { background: #d1fae5; color: #065f46; }
        .status-inativo { background: #fee2e2; color: #991b1b; }
        .status-pendente { background: #fef3c7; color: #92400e; }
        
        .genero-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .genero-m { background: #dbeafe; color: #1e40af; }
        .genero-f { background: #fce4ec; color: #c62828; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state i { font-size: 48px; display: block; margin-bottom: 15px; color: #d1d5db; }
        .empty-state h3 { font-size: 20px; color: #4a5568; margin-bottom: 8px; }
        .empty-state p { font-size: 14px; }
        
        .turmas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .turma-chip {
            padding: 10px 16px;
            border-radius: 10px;
            background: #f8fafc;
            border: 2px solid #eef2f7;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #1a2332;
            font-weight: 600;
            font-size: 14px;
        }
        .turma-chip:hover {
            border-color: #c9a84c;
            background: #fef9e7;
            transform: translateY(-2px);
        }
        .turma-chip.active {
            border-color: #c9a84c;
            background: #c9a84c;
            color: #fff;
        }
        .turma-chip .count {
            display: block;
            font-size: 11px;
            font-weight: 400;
            color: #94a3b8;
            margin-top: 2px;
        }
        .turma-chip.active .count { color: rgba(255,255,255,0.8); }
        
        .alert-warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        .alert-warning i { font-size: 20px; }
        
        @media (max-width: 768px) {
            .page-header { padding: 20px; flex-direction: column; align-items: stretch; text-align: center; }
            .page-header .title-group h1 { font-size: 22px; justify-content: center; }
            .page-header .actions { justify-content: center; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 16px; }
            .stat-card .info h3 { font-size: 22px; }
            .filters-section { flex-direction: column; }
            .filters-section .form-group { min-width: 100%; }
            .table-container .table-header { flex-direction: column; align-items: stretch; text-align: center; }
            .table-container .table-header .info-badge { justify-content: center; flex-wrap: wrap; }
            .turmas-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            .table th, .table td { padding: 10px 12px; font-size: 12px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .page-header .title-group h1 { font-size: 18px; }
            .container { padding: 12px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeInUp 0.4s ease forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        
        @media print {
            .page-header { background: #1a2332 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .btn, .filters-section .btn { display: none; }
            .stat-card { box-shadow: none; border: 1px solid #ddd; }
            .filters-section { box-shadow: none; border: 1px solid #ddd; }
            .table-container { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- HEADER -->
    <div class="page-header fade-in">
        <div class="title-group">
            <h1><i class="fas fa-chalkboard"></i> Relatório por Turma</h1>
            <div class="subtitle">
                <i class="fas fa-calendar-alt"></i> 
                <?= date('d/m/Y H:i') ?> &nbsp;|&nbsp; 
                <i class="fas fa-building"></i> Complexo Escolar Castelo Reis
            </div>
        </div>
        <div class="actions">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>

    <!-- ALERTA SOBRE DUPLICATAS -->
    <?php 
    // Verificar duplicatas
    $totalRegistros = $pdo->query("SELECT COUNT(*) FROM alunos")->fetchColumn() ?? 0;
    $totalUnicos = $pdo->query("SELECT COUNT(DISTINCT id) FROM alunos")->fetchColumn() ?? 0;
    if ($totalRegistros > $totalUnicos): 
    ?>
    <div class="alert-warning fade-in">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Atenção:</strong> Existem <strong><?= $totalRegistros - $totalUnicos ?></strong> registros duplicados na tabela de alunos. 
            Os números abaixo mostram apenas alunos únicos (<strong><?= $totalUnicos ?></strong>).
        </div>
    </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid fade-in delay-1">
        <div class="stat-card alunos">
            <div class="icon purple"><i class="fas fa-user-graduate"></i></div>
            <div class="info">
                <h3><?= $totalAlunosGeral ?></h3>
                <p>Total de Alunos</p>
            </div>
        </div>
        <div class="stat-card turmas">
            <div class="icon orange"><i class="fas fa-door-open"></i></div>
            <div class="info">
                <h3><?= $totalTurmasAtivas ?></h3>
                <p>Turmas Ativas</p>
            </div>
        </div>
        <div class="stat-card masculino">
            <div class="icon blue"><i class="fas fa-male"></i></div>
            <div class="info">
                <h3><?= $totalM ?></h3>
                <p>Alunos Masculino</p>
            </div>
        </div>
        <div class="stat-card feminino">
            <div class="icon red"><i class="fas fa-female"></i></div>
            <div class="info">
                <h3><?= $totalF ?></h3>
                <p>Alunas Feminino</p>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filters-section fade-in delay-2">
        <div class="form-group">
            <label for="turmaSelect"><i class="fas fa-door-open"></i> Turma</label>
            <select id="turmaSelect" onchange="window.location.href='?turma='+this.value+'&status=<?= $filtroStatus ?>&busca=<?= urlencode($busca) ?>'">
                <option value="">Todas as Turmas</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?= htmlspecialchars($t['nome']) ?>" <?= $filtroTurma == $t['nome'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nome']) ?> (<?= $t['total_alunos'] ?? 0 ?> alunos)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="statusSelect"><i class="fas fa-filter"></i> Status</label>
            <select id="statusSelect" onchange="window.location.href='?turma=<?= urlencode($filtroTurma) ?>&status='+this.value+'&busca=<?= urlencode($busca) ?>'">
                <option value="todos" <?= $filtroStatus == 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="ativo" <?= $filtroStatus == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                <option value="inativo" <?= $filtroStatus == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                <option value="transferido" <?= $filtroStatus == 'transferido' ? 'selected' : '' ?>>Transferido</option>
                <option value="concluido" <?= $filtroStatus == 'concluido' ? 'selected' : '' ?>>Concluído</option>
            </select>
        </div>
        
        <div class="form-group" style="flex: 2;">
            <label for="buscaInput"><i class="fas fa-search"></i> Buscar Aluno</label>
            <div style="display: flex; gap: 8px;">
                <input type="text" id="buscaInput" placeholder="Nome, telefone ou email..." value="<?= htmlspecialchars($busca) ?>">
                <button onclick="window.location.href='?turma=<?= urlencode($filtroTurma) ?>&status=<?= $filtroStatus ?>&busca='+document.getElementById('buscaInput').value" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
                <?php if ($busca): ?>
                    <a href="?turma=<?= urlencode($filtroTurma) ?>&status=<?= $filtroStatus ?>" class="btn btn-secondary" style="background: #eef2f7; color: #4a5568;">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TURMAS CHIPS -->
    <?php if (!empty($turmas)): ?>
    <div class="turmas-grid fade-in delay-3">
        <a href="?" class="turma-chip <?= !$filtroTurma ? 'active' : '' ?>">
            Todas
            <span class="count"><?= $totalAlunosGeral ?> alunos</span>
        </a>
        <?php foreach ($turmas as $t): ?>
            <a href="?turma=<?= urlencode($t['nome']) ?>&status=<?= $filtroStatus ?>&busca=<?= urlencode($busca) ?>" 
               class="turma-chip <?= $filtroTurma == $t['nome'] ? 'active' : '' ?>">
                <?= htmlspecialchars($t['nome']) ?>
                <span class="count"><?= $t['total_alunos'] ?? 0 ?> alunos</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- TABELA DE ALUNOS -->
    <div class="table-container fade-in delay-4">
        <div class="table-header">
            <h2>
                <i class="fas fa-users"></i>
                <?= $turmaSelecionada ? "Turma: $turmaSelecionada" : 'Todas as Turmas' ?>
                <span style="font-size: 13px; font-weight: 400; color: #94a3b8; margin-left: 8px;">
                    (<?= count($alunosPorTurma) ?> alunos encontrados)
                </span>
            </h2>
            <div class="info-badge">
                <span><i class="fas fa-male"></i> <strong><?= $totalMLista ?></strong></span>
                <span><i class="fas fa-female"></i> <strong><?= $totalFLista ?></strong></span>
                <span><i class="fas fa-calendar-check"></i> <strong><?= date('Y') ?></strong></span>
            </div>
        </div>
        
        <div class="table-responsive">
            <?php if (!empty($alunosPorTurma)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Aluno</th>
                            <th>Gênero</th>
                            <th>Contato</th>
                            <th>Responsável</th>
                            <th>Data Matrícula</th>
                            <th>Status</th>
                            <th style="text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $contador = 1;
                        foreach ($alunosPorTurma as $aluno): 
                            $sexo = trim(strtoupper($aluno['genero'] ?? ''));
                            $generoLabel = '';
                            $generoClass = '';
                            
                            if ($sexo === 'M') {
                                $generoLabel = 'Masculino';
                                $generoClass = 'genero-m';
                            } elseif ($sexo === 'F') {
                                $generoLabel = 'Feminino';
                                $generoClass = 'genero-f';
                            } else {
                                $generoLabel = 'N/A';
                                $generoClass = '';
                            }
                            
                            $status = strtolower($aluno['status'] ?? '');
                            $statusClass = match($status) {
                                'ativo' => 'status-ativo',
                                'inativo' => 'status-inativo',
                                'transferido' => 'status-pendente',
                                'concluido' => 'status-ativo',
                                default => 'status-pendente'
                            };
                            $statusLabel = ucfirst($status ?: 'N/A');
                        ?>
                        <tr>
                            <td style="text-align: center; font-weight: 600; color: #94a3b8;"><?= $contador++ ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <?php if (!empty($aluno['foto'])): ?>
                                        <img src="<?= htmlspecialchars($aluno['foto']) ?>" class="aluno-foto" alt="Foto">
                                    <?php else: ?>
                                        <div class="aluno-foto">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; color: #1a2332;"><?= htmlspecialchars($aluno['nome'] ?? 'N/A') ?></div>
                                        <?php if (!empty($aluno['turma_nome'])): ?>
                                            <div style="font-size: 11px; color: #94a3b8;">
                                                <i class="fas fa-door-open"></i> <?= htmlspecialchars($aluno['turma_nome']) ?>
                                                <?php if (!empty($aluno['sala'])): ?>
                                                    | Sala <?= htmlspecialchars($aluno['sala']) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($aluno['turno'])): ?>
                                                    | <?= htmlspecialchars($aluno['turno']) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="genero-badge <?= $generoClass ?>">
                                    <?= $generoLabel ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($aluno['telefone'])): ?>
                                    <div style="font-size: 13px;"><i class="fas fa-phone" style="color: #94a3b8; width: 16px;"></i> <?= htmlspecialchars($aluno['telefone']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($aluno['email'])): ?>
                                    <div style="font-size: 12px; color: #94a3b8;"><i class="fas fa-envelope" style="width: 16px;"></i> <?= htmlspecialchars($aluno['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 13px;">
                                    <?php if (!empty($aluno['nome_pai'])): ?>
                                        <div><i class="fas fa-user-tie" style="color: #94a3b8; width: 16px;"></i> <?= htmlspecialchars($aluno['nome_pai']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($aluno['nome_mae'])): ?>
                                        <div><i class="fas fa-user-tie" style="color: #94a3b8; width: 16px;"></i> <?= htmlspecialchars($aluno['nome_mae']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($aluno['telefone_responsavel'])): ?>
                                        <div style="font-size: 12px; color: #94a3b8;"><i class="fas fa-phone" style="width: 16px;"></i> <?= htmlspecialchars($aluno['telefone_responsavel']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="font-size: 13px; color: #4a5568;">
                                <?= !empty($aluno['data_matricula']) ? date('d/m/Y', strtotime($aluno['data_matricula'])) : 'N/A' ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= $statusLabel ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <a href="../alunos/visualizar.php?id=<?= $aluno['id'] ?? 0 ?>" class="btn btn-secondary btn-sm" title="Ver detalhes" style="background:#eef2f7;color:#4a5568;">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="../alunos/editar.php?id=<?= $aluno['id'] ?? 0 ?>" class="btn btn-primary btn-sm" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>Nenhum aluno encontrado</h3>
                    <p>
                        <?= $filtroTurma ? "Não há alunos matriculados na turma <strong>$filtroTurma</strong>." : 'Nenhum aluno cadastrado no sistema.' ?>
                    </p>
                    <?php if ($busca): ?>
                        <p style="margin-top: 8px;">Tente ajustar os filtros de busca.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- FOOTER DA TABELA -->
        <div style="padding: 14px 24px; border-top: 1px solid #eef2f7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; font-size: 13px; color: #94a3b8;">
            <div>
                <i class="fas fa-info-circle"></i> 
                Mostrando <strong><?= count($alunosPorTurma) ?></strong> alunos
                <?php if ($filtroTurma): ?>
                    da turma <strong><?= htmlspecialchars($filtroTurma) ?></strong>
                <?php endif; ?>
            </div>
            <div>
                <span class="genero-badge genero-m"><i class="fas fa-male"></i> <?= $totalMLista ?></span>
                <span class="genero-badge genero-f"><i class="fas fa-female"></i> <?= $totalFLista ?></span>
            </div>
        </div>
    </div>

    <!-- RODAPÉ -->
    <div style="margin-top: 30px; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #eef2f7;">
        <p>
            <i class="fas fa-copyright"></i> <?= date('Y') ?> Complexo Escolar Castelo Reis - 
            Módulo Gestão Escolar v1.0
            <span style="margin: 0 10px;">|</span>
            <i class="fas fa-clock"></i> Gerado em <?= date('d/m/Y H:i:s') ?>
            <span style="margin: 0 10px;">|</span>
            <i class="fas fa-user"></i> <?= $_SESSION['usuario_nome'] ?? 'Administrador' ?>
        </p>
    </div>

</div>

<script>
document.getElementById('buscaInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        window.location.href = '?turma=<?= urlencode($filtroTurma) ?>&status=<?= $filtroStatus ?>&busca=' + encodeURIComponent(this.value);
    }
});
</script>

<?php include '../includes/footer_escola.php'; ?>
</body>
</html>