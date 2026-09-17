<?php
// ============================================
// grade.php - Grade Horária Escolar
// Mostra apenas os tempos que têm aulas cadastradas
// Botão Voltar redireciona conforme perfil (professor/admin)
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

// ============================================
// DETECTAR PERFIL DO UTILIZADOR
// ============================================
$is_professor = false;
$is_admin = false;
$pode_mover = false;
$url_voltar = '../index.php'; // fallback

try {
    $pdo = conectarBanco();
    
    $uid = $_SESSION['usuario_id'] ?? 0;
    $user_func = null;
    
    // Tentar 1: tabela usuarios
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $tipo = strtolower(trim(
                $user['tipo'] ?? 
                $user['cargo'] ?? 
                $user['perfil'] ?? 
                $user['nivel'] ?? 
                ''
            ));
            
            if (strpos($tipo, 'admin') !== false 
                || strpos($tipo, 'gestor') !== false 
                || strpos($tipo, 'diretor') !== false 
                || strpos($tipo, 'coordenador') !== false
                || strpos($tipo, 'secretario') !== false) {
                $is_admin = true;
            }
            
            if (strpos($tipo, 'professor') !== false 
                || strpos($tipo, 'prof') !== false 
                || strpos($tipo, 'docente') !== false) {
                $is_professor = true;
            }
            
            // Se tiver funcionario_id, buscar cargo em funcionarios
            if (!empty($user['funcionario_id'])) {
                $stmt2 = $pdo->prepare("SELECT cargo FROM funcionarios WHERE id = ? LIMIT 1");
                $stmt2->execute([$user['funcionario_id']]);
                $func = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($func) {
                    $cargo = strtolower(trim($func['cargo'] ?? ''));
                    if (strpos($cargo, 'professor') !== false || strpos($cargo, 'prof') !== false || strpos($cargo, 'docente') !== false) {
                        $is_professor = true;
                    }
                    if (strpos($cargo, 'admin') !== false || strpos($cargo, 'gestor') !== false || strpos($cargo, 'diretor') !== false) {
                        $is_admin = true;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Tabela usuarios pode não existir ou não ter essas colunas
    }
    
    // Tentar 2: tabela funcionarios (se ainda não identificou)
    if (!$is_professor && !$is_admin) {
        if ($uid > 0) {
            $stmt = $pdo->prepare("SELECT id, nome, email, cargo FROM funcionarios WHERE id = ? LIMIT 1");
            $stmt->execute([$uid]);
            $user_func = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$user_func && !empty($_SESSION['usuario_email'])) {
            $stmt = $pdo->prepare("SELECT id, nome, email, cargo FROM funcionarios WHERE email = ? LIMIT 1");
            $stmt->execute([$_SESSION['usuario_email']]);
            $user_func = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$user_func && !empty($_SESSION['usuario_nome'])) {
            $stmt = $pdo->prepare("SELECT id, nome, email, cargo FROM funcionarios WHERE nome = ? LIMIT 1");
            $stmt->execute([$_SESSION['usuario_nome']]);
            $user_func = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($user_func) {
            $cargo = strtolower(trim($user_func['cargo'] ?? ''));
            
            $is_professor = 
                strpos($cargo, 'professor') !== false ||
                strpos($cargo, 'prof') !== false ||
                strpos($cargo, 'docente') !== false;
            
            $is_admin = 
                strpos($cargo, 'admin') !== false ||
                strpos($cargo, 'gestor') !== false ||
                strpos($cargo, 'diretor') !== false ||
                strpos($cargo, 'coordenador') !== false ||
                strpos($cargo, 'secretario') !== false;
        }
    }
    
} catch (Exception $e) {
    error_log("Erro ao verificar perfil: " . $e->getMessage());
}

// Pode mover se for admin
$pode_mover = $is_admin;

// ============================================
// DEFINIR URL DE VOLTAR CONFORME PERFIL
// ============================================
if ($is_professor && !$is_admin) {
    // Professor → painel do professor
    $url_voltar = SITE_URL . 'professor/dashboard.php';
} elseif ($is_admin) {
    // Admin → index do módulo escola
    $url_voltar = '../index.php';
} else {
    // Fallback
    $url_voltar = '../index.php';
}

// ============================================
// BUSCAR DADOS DO BANCO
// ============================================
$selectedClasse = isset($_GET['classe']) ? $_GET['classe'] : '';
$selectedTurma = isset($_GET['turma']) ? $_GET['turma'] : '';

$classes = [];
$turmas = [];
$horarios = [];
$funcionarios = [];
$disciplinas_lista = [];
$tempos = [];

try {
    $pdo = conectarBanco();
    
    $stmt = $pdo->query("SELECT DISTINCT classe FROM turmas WHERE classe IS NOT NULL AND classe != '' ORDER BY classe");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($selectedClasse)) {
        $stmt = $pdo->prepare("SELECT id, nome FROM turmas WHERE classe = ? AND status = 'ativa' ORDER BY nome");
        $stmt->execute([$selectedClasse]);
        $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $stmt = $pdo->query("SELECT id, nome, cargo FROM funcionarios WHERE cargo = 'Professor' OR cargo LIKE '%Professor%' ORDER BY nome");
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($funcionarios)) {
        $stmt = $pdo->query("SELECT id, nome, cargo FROM funcionarios ORDER BY nome");
        $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $stmt = $pdo->query("SELECT * FROM tempos WHERE status = 'ativo' ORDER BY ordem, hora_inicio");
    $tempos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tempos as &$t) {
        if (!isset($t['is_intervalo'])) {
            $t['is_intervalo'] = (stripos($t['nome'], 'intervalo') !== false) ? 1 : 0;
        }
    }
    unset($t);
    
    if (!empty($selectedTurma)) {
        $stmt = $pdo->prepare("SELECT disciplinas FROM turmas WHERE id = ?");
        $stmt->execute([$selectedTurma]);
        $turma_disciplinas = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($turma_disciplinas['disciplinas'])) {
            $discs = explode(',', $turma_disciplinas['disciplinas']);
            foreach ($discs as $disc) {
                $disc = trim($disc);
                if (!empty($disc)) $disciplinas_lista[] = $disc;
            }
            sort($disciplinas_lista);
        }
    }
    
    if (!empty($selectedTurma)) {
        $stmt = $pdo->prepare("
            SELECT h.*, 
                   t.nome as turma_nome,
                   t.classe as turma_classe,
                   f.nome as professor_nome,
                   f.cargo as professor_cargo
            FROM horarios h
            LEFT JOIN turmas t ON h.turma_id = t.id
            LEFT JOIN funcionarios f ON h.funcionario_id = f.id
            WHERE h.turma_id = ?
            ORDER BY FIELD(h.dia_semana, 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'), h.hora_inicio
        ");
        $stmt->execute([$selectedTurma]);
        $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $erro = 'Erro ao carregar dados: ' . $e->getMessage();
}

$turmaNome = '';
$classeNome = '';
if (!empty($selectedTurma)) {
    foreach ($turmas as $t) {
        if ($t['id'] == $selectedTurma) {
            $turmaNome = $t['nome'];
            $classeNome = $selectedClasse;
            break;
        }
    }
}

$diasSemana = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

// ===== AGRUPAR HORÁRIOS POR TEMPO_ID E POR HORA =====
$horariosPorTempo = [];
foreach ($horarios as $h) {
    $tid = intval($h['tempo_id'] ?? 0);
    $keyHora = $h['hora_inicio'] . '|' . $h['hora_fim'];
    
    if ($tid > 0) {
        $horariosPorTempo[$tid][$h['dia_semana']] = $h;
    }
    if (!isset($horariosPorTempo[$keyHora])) {
        $horariosPorTempo[$keyHora] = [];
    }
    $horariosPorTempo[$keyHora][$h['dia_semana']] = $h;
}

// ===== FILTRAR APENAS TEMPOS QUE TÊM AULAS NESTA TURMA =====
$temposComAulas = [];
foreach ($tempos as $tempo) {
    $tid = intval($tempo['id']);
    $keyHora = $tempo['hora_inicio'] . '|' . $tempo['hora_fim'];
    
    $temAula = false;
    foreach ($diasSemana as $dia) {
        if (isset($horariosPorTempo[$tid][$dia]) || isset($horariosPorTempo[$keyHora][$dia])) {
            $temAula = true;
            break;
        }
    }
    
    if ($temAula) {
        $temposComAulas[] = $tempo;
    }
}

// Ordenar por ordem
usort($temposComAulas, function($a, $b) {
    $oA = intval($a['ordem'] ?? 999);
    $oB = intval($b['ordem'] ?? 999);
    if ($oA === $oB) {
        return strcmp($a['hora_inicio'], $b['hora_inicio']);
    }
    return $oA - $oB;
});

function getTempoNome($tempo_id, $tempos) {
    foreach ($tempos as $t) {
        if ($t['id'] == $tempo_id) return $t['nome'];
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Horária - <?= htmlspecialchars($classeNome . ' - ' . $turmaNome) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8; padding: 20px; color: #333;
        }
        .container {
            max-width: 1400px; margin: 0 auto; background: #fff;
            border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); padding: 30px;
        }
        .header {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; margin-bottom: 25px;
            border-bottom: 3px solid #d4a843; padding-bottom: 15px;
        }
        .header h1 { font-size: 26px; color: #1e293b; }
        .header h1 small {
            font-size: 16px; font-weight: normal; color: #64748b;
            display: block; margin-top: 4px;
        }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            padding: 8px 18px; border: none; border-radius: 6px; font-size: 14px;
            font-weight: 600; cursor: pointer; transition: all 0.3s ease;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary { background: #d4a843; color: #1a2a3a; }
        .btn-primary:hover { background: #c9a84c; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(212,168,67,0.3); }
        .btn-print { background: #475569; color: white; }
        .btn-print:hover { background: #334155; }
        .btn-back { background: #f1f5f9; color: #4a5568; }
        .btn-back:hover { background: #e2e8f0; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #22c55e; color: white; }
        .btn-success:hover { background: #16a34a; }
        
        .banner-view {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #3b82f6;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .banner-view strong { font-weight: 700; }
        
        .filters {
            background: #f8fafc; padding: 20px; border-radius: 10px;
            margin-bottom: 25px; display: flex; flex-wrap: wrap;
            gap: 15px; align-items: flex-end; border: 1px solid #e2e8f0;
        }
        .filter-group {
            display: flex; flex-direction: column; gap: 4px;
            flex: 1; min-width: 150px;
        }
        .filter-group label {
            font-size: 13px; font-weight: 600; color: #475569;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .filter-group select {
            padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; background: white; width: 100%;
        }
        .filter-group select:focus {
            outline: none; border-color: #d4a843;
            box-shadow: 0 0 0 3px rgba(212,168,67,0.1);
        }
        .filter-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        
        .schedule-wrapper { overflow-x: auto; margin-top: 10px; }
        .schedule-table {
            width: 100%; border-collapse: collapse; font-size: 14px; min-width: 800px;
        }
        .schedule-table th {
            background: #1a2a3a; color: white; padding: 12px 8px;
            text-align: center; font-weight: 600; text-transform: uppercase;
            font-size: 12px; letter-spacing: 0.5px;
        }
        .schedule-table th:first-child { border-radius: 8px 0 0 0; }
        .schedule-table th:last-child { border-radius: 0 8px 0 0; }
        .schedule-table td {
            padding: 8px 6px; text-align: center; border: 1px solid #e2e8f0;
            vertical-align: top; min-height: 60px; position: relative;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .time-col {
            font-weight: 700; color: #475569; background: #f8fafc !important;
            white-space: nowrap; font-size: 12px; min-width: 90px;
            vertical-align: middle !important;
        }
        .time-col .tempo-nome {
            font-size: 10px; color: #94a3b8; display: block; margin-top: 3px;
        }
        
        .class-cell {
            min-width: 100px;
            min-height: 70px;
            position: relative;
        }
        .class-cell.empty {
            cursor: pointer;
        }
        .class-cell.empty:hover {
            background: #f1f5f9;
        }
        .class-cell.drag-over {
            background: #fef9e7 !important;
            box-shadow: inset 0 0 0 2px #d4a843;
        }
        .class-cell.dragging {
            opacity: 0.4;
        }
        
        .aula-card {
            background: #f8fafc;
            border-left: 3px solid #d4a843;
            border-radius: 6px;
            padding: 6px 8px;
            text-align: left;
            transition: all 0.2s;
            user-select: none;
        }
        .aula-card.movivel {
            cursor: grab;
        }
        .aula-card.movivel:hover {
            background: #fef9e7;
            box-shadow: 0 2px 8px rgba(212,168,67,0.2);
        }
        .aula-card.movivel:active { cursor: grabbing; }
        .aula-card.intervalo {
            background: #fef9e7;
            border-left-color: #d4a843;
        }
        .aula-card .subject {
            font-weight: 700; color: #0f172a; display: block;
            font-size: 13px; line-height: 1.2;
            padding-right: 18px;
        }
        .aula-card.intervalo .subject { color: #d4a843; }
        .aula-card .teacher {
            font-size: 10px; color: #64748b; display: block; margin-top: 2px;
        }
        .aula-card .room {
            display: inline-block; background: #e2e8f0; padding: 0px 6px;
            border-radius: 8px; font-size: 9px; font-weight: 600;
            color: #475569; margin-top: 2px;
        }
        .aula-card .drag-handle {
            position: absolute; top: 4px; right: 4px;
            font-size: 12px; color: #94a3b8; cursor: grab;
            line-height: 1;
        }
        
        .mover-btns {
            display: flex;
            gap: 2px;
            margin-top: 4px;
            justify-content: center;
        }
        .mover-btns button {
            width: 20px; height: 20px; border: 1px solid #cbd5e1;
            background: white; border-radius: 4px; cursor: pointer;
            font-size: 10px; color: #475569; padding: 0;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.15s;
        }
        .mover-btns button:hover {
            background: #d4a843; color: white; border-color: #d4a843;
        }
        .mover-btns button:disabled {
            opacity: 0.3; cursor: not-allowed;
        }
        
        .empty-cell {
            color: #cbd5e1; font-size: 20px; line-height: 60px;
            display: block; text-align: center;
        }
        .empty-cell.sem-permissao {
            color: #e2e8f0;
            cursor: default;
        }
        
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state .icon { font-size: 64px; margin-bottom: 15px; }
        .empty-state h3 { color: #1e293b; font-size: 20px; margin-bottom: 8px; }
        .empty-state p { font-size: 15px; max-width: 400px; margin: 0 auto; }
        
        .footer {
            margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; font-size: 13px; color: #94a3b8;
        }
        
        .legenda {
            display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;
            padding: 12px 15px; background: #f8fafc; border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .legenda-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; }
        .legenda-item .cor { width: 20px; height: 20px; border-radius: 4px; border: 1px solid #e2e8f0; }
        .legenda-item .cor.aula { background: #f8fafc; border-left: 3px solid #d4a843; }
        .legenda-item .cor.intervalo { background: #fef9e7; border-left: 3px solid #d4a843; }
        .legenda-item .cor.livre { background: white; }
        
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1000;
            justify-content: center; align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white; border-radius: 12px; max-width: 600px; width: 90%;
            max-height: 90vh; overflow-y: auto; padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;
        }
        .modal-header h2 { font-size: 20px; color: #1a2a3a; }
        .modal-close {
            background: none; border: none; font-size: 28px;
            cursor: pointer; color: #94a3b8;
        }
        .modal-close:hover { color: #ef4444; }
        .modal .form-group { margin-bottom: 15px; }
        .modal .form-group label {
            display: block; font-weight: 600; margin-bottom: 5px;
            color: #4a5568; font-size: 14px;
        }
        .modal .form-group .obrigatorio { color: #e53e3e; }
        .modal .form-group input,
        .modal .form-group select {
            width: 100%; padding: 10px 12px; border: 2px solid #e2e8f0;
            border-radius: 6px; font-size: 14px; box-sizing: border-box;
        }
        .modal .form-group input:focus,
        .modal .form-group select:focus {
            border-color: #d4a843; outline: none;
        }
        .modal .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal .modal-actions {
            display: flex; gap: 10px; margin-top: 20px;
            padding-top: 15px; border-top: 1px solid #f0f0f0; flex-wrap: wrap;
        }
        .modal .info-display {
            background: #f8fafc; padding: 10px 15px; border-radius: 6px;
            margin-bottom: 15px; border-left: 3px solid #d4a843;
        }
        
        .toast {
            position: fixed; top: 20px; right: 20px; padding: 14px 20px;
            background: #1a2a3a; color: white; border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 9999; font-size: 14px; font-weight: 600;
            opacity: 0; transform: translateY(-20px);
            transition: all 0.3s; pointer-events: none;
            display: flex; align-items: center; gap: 8px;
        }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: #16a34a; }
        .toast.error { background: #dc2626; }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .header h1 { font-size: 20px; }
            .filters { flex-direction: column; }
            .filter-group { min-width: 100%; }
            .schedule-table { font-size: 12px; min-width: 700px; }
            .modal .form-row { grid-template-columns: 1fr; }
        }
        @media print {
            .no-print { display: none !important; }
            .container { box-shadow: none; padding: 10px; }
            body { background: white; padding: 0; }
            .schedule-table th {
                background: #1a2a3a !important; color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .aula-card {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .aula-card.intervalo {
                background: #fef9e7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .mover-btns, .drag-handle { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- CABEÇALHO -->
        <div class="header">
            <h1>
                📚 Grade Horária
                <small>
                    <?php 
                    if (!empty($selectedTurma) && !empty($turmaNome)) {
                        echo htmlspecialchars($classeNome . ' - Turma ' . $turmaNome);
                    } else {
                        echo 'Selecione uma turma para visualizar';
                    }
                    ?>
                </small>
            </h1>
            <div class="header-actions no-print">
                <a href="print.php?classe=<?= urlencode($selectedClasse) ?>&turma=<?= $selectedTurma ?>&print=1" 
                   target="_blank" 
                   class="btn btn-print">🖨️ Imprimir</a>
                <?php if ($pode_mover): ?>
                    <a href="add.php?turma=<?= $selectedTurma ?>" class="btn btn-primary">➕ Adicionar</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($url_voltar) ?>" class="btn btn-back">← Voltar</a>
            </div>
        </div>

        <?php if (isset($erro)): ?>
            <div class="alert alert-danger">❌ <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        
        <!-- BANNER DE MODO VISUALIZAÇÃO -->
        <?php if (!$pode_mover): ?>
            <div class="banner-view no-print">
                <span style="font-size: 20px;">👁️</span>
                <div>
                    <strong>Modo Visualização</strong> — Está a visualizar a grade horária apenas para consulta.
                    Contacte a administração para fazer alterações.
                </div>
            </div>
        <?php endif; ?>
        
        <!-- FILTROS -->
        <form method="GET" class="filters no-print">
            <div class="filter-group">
                <label for="classe">📖 Classe</label>
                <select name="classe" id="classe" onchange="this.form.submit()">
                    <option value="">Selecione a classe</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?= htmlspecialchars($classe) ?>" <?= ($selectedClasse == $classe) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($classe) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="turma">🏫 Turma</label>
                <select name="turma" id="turma" onchange="this.form.submit()" <?= empty($selectedClasse) ? 'disabled' : '' ?>>
                    <option value="">Selecione a turma</option>
                    <?php foreach ($turmas as $turma): ?>
                        <option value="<?= $turma['id'] ?>" <?= ($selectedTurma == $turma['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($turma['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">🔍 Consultar</button>
                <a href="grade.php" class="btn btn-back">↺ Limpar</a>
            </div>
        </form>
        
        <!-- GRADE HORÁRIA -->
        <?php if (!empty($selectedTurma) && !empty($horarios)): ?>
            
            <div class="legenda no-print">
                <div class="legenda-item">
                    <span class="cor aula"></span>
                    Aula
                    <?php if ($pode_mover): ?>
                        <span style="font-size:11px;color:#94a3b8;">(arraste ou use as setas)</span>
                    <?php else: ?>
                        <span style="font-size:11px;color:#94a3b8;">(modo visualização)</span>
                    <?php endif; ?>
                </div>
                <div class="legenda-item">
                    <span class="cor intervalo"></span>
                    Intervalo
                </div>
                <div class="legenda-item">
                    <span class="cor livre"></span>
                    Horário livre
                    <?php if ($pode_mover): ?>
                        <span style="font-size:11px;color:#94a3b8;">(clique 2x para adicionar)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="schedule-wrapper">
                <table class="schedule-table" id="scheduleTable">
                    <thead>
                        <tr>
                            <th>Horário</th>
                            <?php foreach ($diasSemana as $dia): ?>
                                <th><?= substr($dia, 0, -4) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($temposComAulas)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:40px; color:#94a3b8; font-size:14px;">
                                    📭 Nenhum horário cadastrado para esta turma.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($temposComAulas as $tempo): 
                                $tid = intval($tempo['id']);
                                $keyHora = $tempo['hora_inicio'] . '|' . $tempo['hora_fim'];
                            ?>
                                <tr data-tempo-id="<?= $tid ?>" 
                                    data-hora-inicio="<?= $tempo['hora_inicio'] ?>" 
                                    data-hora-fim="<?= $tempo['hora_fim'] ?>">
                                    <td class="time-col">
                                        <?= date('H:i', strtotime($tempo['hora_inicio'])) ?> - 
                                        <?= date('H:i', strtotime($tempo['hora_fim'])) ?>
                                        <span class="tempo-nome"><?= htmlspecialchars($tempo['nome']) ?></span>
                                    </td>
                                    <?php foreach ($diasSemana as $dia): 
                                        $aula = $horariosPorTempo[$tid][$dia] ?? $horariosPorTempo[$keyHora][$dia] ?? null;
                                        $isIntervalo = $aula && ($aula['is_intervalo'] ?? 0) == 1;
                                    ?>
                                        <td class="class-cell <?= $aula ? ($isIntervalo ? 'intervalo-cell' : 'has-class') : 'empty' ?>"
                                            data-dia="<?= $dia ?>"
                                            data-tempo-id="<?= $tid ?>"
                                            data-hora-inicio="<?= $tempo['hora_inicio'] ?>"
                                            data-hora-fim="<?= $tempo['hora_fim'] ?>"
                                            data-horario-id="<?= $aula['id'] ?? '' ?>"
                                            <?php if ($pode_mover): ?>
                                                ondblclick="abrirModal(this)"
                                                ondragover="dragOver(event, this)"
                                                ondragleave="dragLeave(event, this)"
                                                ondrop="drop(event, this)"
                                            <?php endif; ?>>
                                            
                                            <?php if ($aula): ?>
                                                <div class="aula-card <?= $isIntervalo ? 'intervalo' : '' ?> <?= $pode_mover ? 'movivel' : '' ?>"
                                                     <?= $pode_mover ? 'draggable="true" ondragstart="dragStart(event, this)" ondragend="dragEnd(event, this)"' : '' ?>
                                                     data-id="<?= $aula['id'] ?>">
                                                    <?php if ($pode_mover): ?>
                                                        <span class="drag-handle">⋮⋮</span>
                                                    <?php endif; ?>
                                                    <?php if ($isIntervalo): ?>
                                                        <span class="subject">☕ INTERVALO</span>
                                                    <?php else: ?>
                                                        <span class="subject"><?= htmlspecialchars($aula['disciplina']) ?></span>
                                                        <span class="teacher">👨‍🏫 <?= htmlspecialchars($aula['professor_nome'] ?? '-') ?></span>
                                                        <?php if (!empty($aula['sala'])): ?>
                                                            <span class="room">🏠 <?= htmlspecialchars($aula['sala']) ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($pode_mover): ?>
                                                    <div class="mover-btns no-print" onclick="event.stopPropagation()">
                                                        <button type="button" title="Mover para dia anterior"
                                                                onclick="moverHorario(<?= $aula['id'] ?>, '<?= $dia ?>', -1, 0, this)"
                                                                <?= $dia === 'Segunda-feira' ? 'disabled' : '' ?>>◀</button>
                                                        <button type="button" title="Mover para cima (tempo anterior)"
                                                                onclick="moverHorario(<?= $aula['id'] ?>, '<?= $dia ?>', 0, -1, this)">▲</button>
                                                        <button type="button" title="Mover para baixo (tempo seguinte)"
                                                                onclick="moverHorario(<?= $aula['id'] ?>, '<?= $dia ?>', 0, 1, this)">▼</button>
                                                        <button type="button" title="Mover para dia seguinte"
                                                                onclick="moverHorario(<?= $aula['id'] ?>, '<?= $dia ?>', 1, 0, this)"
                                                                <?= $dia === 'Sábado' ? 'disabled' : '' ?>>▶</button>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="empty-cell <?= !$pode_mover ? 'sem-permissao' : '' ?>">
                                                    <?= $pode_mover ? '+' : '—' ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="footer">
                <div class="info">
                    <span>© <?= date('Y') ?> SoftGest - Sistema de Gestão Escolar</span>
                    <span>| Módulo: Escola / Horários</span>
                </div>
                <div><span>Versão 1.0</span></div>
            </div>
            
        <?php elseif (!empty($selectedTurma) && empty($horarios)): ?>
            <div class="empty-state">
                <div class="icon">📋</div>
                <h3>Nenhuma aula cadastrada</h3>
                <p>Não há horários definidos para <?= htmlspecialchars($classeNome . ' - Turma ' . $turmaNome) ?>.</p>
                <br>
                <?php if ($pode_mover): ?>
                    <a href="add.php?turma=<?= $selectedTurma ?>" class="btn btn-primary">➕ Adicionar Horários</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">🔍</div>
                <h3>Selecione uma turma</h3>
                <p>Use os filtros acima para visualizar a grade horária de uma turma específica.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================
    MODAL DE EDIÇÃO (só para quem pode mover)
    ============================================ -->
    <?php if ($pode_mover): ?>
    <div class="modal-overlay" id="modalEditar">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modalTitulo">✏️ Editar Horário</h2>
                <button class="modal-close" onclick="fecharModal()">&times;</button>
            </div>
            
            <form id="formEditar" method="POST" action="editar_horario.php">
                <input type="hidden" name="id" id="edit_id" value="">
                <input type="hidden" name="turma_id" id="edit_turma_id" value="<?= $selectedTurma ?>">
                <input type="hidden" name="dia_semana" id="edit_dia_semana" value="">
                <input type="hidden" name="tempo_id" id="edit_tempo_id" value="">
                
                <div class="info-display">
                    <strong>📅 Dia:</strong> <span id="edit_dia"></span> &nbsp;|&nbsp;
                    <strong>⏰ Horário:</strong> <span id="edit_horario"></span>
                </div>
                
                <div class="form-group">
                    <label>Disciplina <span class="obrigatorio">*</span></label>
                    <select name="disciplina" id="edit_disciplina" required>
                        <option value="">Selecione a disciplina</option>
                        <?php foreach ($disciplinas_lista as $disc): ?>
                            <option value="<?= htmlspecialchars($disc) ?>"><?= htmlspecialchars($disc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Professor <span class="obrigatorio">*</span></label>
                    <select name="funcionario_id" id="edit_funcionario_id" required>
                        <option value="">Selecione o professor</option>
                        <?php foreach ($funcionarios as $func): ?>
                            <option value="<?= $func['id'] ?>">
                                <?= htmlspecialchars($func['nome']) ?>
                                <?= !empty($func['cargo']) ? '('.htmlspecialchars($func['cargo']).')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Hora Início</label>
                        <input type="time" name="hora_inicio" id="edit_hora_inicio">
                    </div>
                    <div class="form-group">
                        <label>Hora Fim</label>
                        <input type="time" name="hora_fim" id="edit_hora_fim">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Sala</label>
                    <input type="text" name="sala" id="edit_sala" placeholder="Ex: Sala 101">
                </div>
                
                <div class="form-group">
                    <label style="font-size:13px; color:#94a3b8;">
                        <input type="checkbox" name="is_intervalo" id="edit_is_intervalo" value="1">
                        ☕ Este é um intervalo
                    </label>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-success">💾 Salvar</button>
                    <button type="button" class="btn btn-danger" onclick="excluirHorario()">🗑️ Excluir</button>
                    <button type="button" class="btn btn-back" onclick="fecharModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- TOAST -->
    <div class="toast" id="toast"></div>

    <script>
        // ============================================
        // PERMISSÃO
        // ============================================
        var PODE_MOVER = <?= $pode_mover ? 'true' : 'false' ?>;

        // ============================================
        // LISTA COMPLETA DE TEMPOS
        // ============================================
        var TEMPOS_COMPLETOS = <?= json_encode(array_map(function($t) {
            return [
                'id' => intval($t['id']),
                'ordem' => intval($t['ordem'] ?? 0),
                'hora_inicio' => $t['hora_inicio'],
                'hora_fim' => $t['hora_fim'],
                'nome' => $t['nome']
            ];
        }, $tempos)) ?>;

        // ============================================
        // DRAG & DROP
        // ============================================
        var dragData = null;

        function dragStart(e, card) {
            if (!PODE_MOVER) {
                e.preventDefault();
                return false;
            }
            dragData = {
                id: card.getAttribute('data-id'),
                sourceCell: card.closest('.class-cell')
            };
            card.closest('.class-cell').classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', dragData.id);
        }

        function dragEnd(e, card) {
            document.querySelectorAll('.class-cell.dragging').forEach(el => el.classList.remove('dragging'));
            document.querySelectorAll('.class-cell.drag-over').forEach(el => el.classList.remove('drag-over'));
            dragData = null;
        }

        function dragOver(e, cell) {
            if (!PODE_MOVER) return;
            e.preventDefault();
            if (!dragData) return;
            if (cell.getAttribute('data-horario-id')) {
                e.dataTransfer.dropEffect = 'none';
                return;
            }
            e.dataTransfer.dropEffect = 'move';
            cell.classList.add('drag-over');
        }

        function dragLeave(e, cell) {
            cell.classList.remove('drag-over');
        }

        function drop(e, cell) {
            if (!PODE_MOVER) return;
            e.preventDefault();
            cell.classList.remove('drag-over');
            
            if (!dragData) return;
            
            if (cell.getAttribute('data-horario-id')) {
                mostrarToast('Esta célula já tem uma aula.', 'error');
                return;
            }
            
            var id = dragData.id;
            var novoDia = cell.getAttribute('data-dia');
            var tempoId = cell.getAttribute('data-tempo-id');
            var horaIni = cell.getAttribute('data-hora-inicio');
            var horaFim = cell.getAttribute('data-hora-fim');
            
            moverHorarioAjax(id, novoDia, tempoId, horaIni, horaFim);
        }

        // ============================================
        // MOVER VIA AJAX
        // ============================================
        function moverHorarioAjax(id, dia, tempoId, horaIni, horaFim) {
            if (!PODE_MOVER) {
                mostrarToast('Não tem permissão para mover horários.', 'error');
                return;
            }
            
            var params = new URLSearchParams({
                id: id,
                dia_semana: dia,
                tempo_id: tempoId,
                hora_inicio: horaIni,
                hora_fim: horaFim
            });
            
            fetch('mover_horario.php?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        mostrarToast('✅ ' + data.message, 'success');
                        setTimeout(() => location.reload(), 600);
                    } else {
                        mostrarToast('❌ ' + data.message, 'error');
                    }
                })
                .catch(err => {
                    mostrarToast('❌ Erro ao mover: ' + err.message, 'error');
                });
        }

        // ============================================
        // MOVER COM SETAS
        // ============================================
        var DIAS = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

        function moverHorario(id, diaAtual, deltaDia, deltaTempo, btn) {
            if (!PODE_MOVER) {
                mostrarToast('Não tem permissão para mover horários.', 'error');
                return;
            }
            
            var idxDia = DIAS.indexOf(diaAtual);
            if (idxDia === -1) return;
            
            var novoIdxDia = idxDia + deltaDia;
            if (novoIdxDia < 0 || novoIdxDia >= DIAS.length) {
                mostrarToast('Limite de dias atingido.', 'error');
                return;
            }
            var novoDia = DIAS[novoIdxDia];
            
            var cellAtual = btn.closest('.class-cell');
            var tempoIdAtual = parseInt(cellAtual.getAttribute('data-tempo-id')) || 0;
            var horaIniAtual = cellAtual.getAttribute('data-hora-inicio');
            
            var novoTempo = null;
            
            if (deltaTempo !== 0) {
                var idxAtual = -1;
                for (var i = 0; i < TEMPOS_COMPLETOS.length; i++) {
                    if (TEMPOS_COMPLETOS[i].id == tempoIdAtual || 
                        TEMPOS_COMPLETOS[i].hora_inicio == horaIniAtual) {
                        idxAtual = i;
                        break;
                    }
                }
                
                if (idxAtual === -1) return;
                
                var novoIdx = idxAtual + deltaTempo;
                if (novoIdx < 0 || novoIdx >= TEMPOS_COMPLETOS.length) {
                    mostrarToast('Limite de tempos atingido.', 'error');
                    return;
                }
                
                novoTempo = TEMPOS_COMPLETOS[novoIdx];
            } else {
                novoTempo = {
                    id: tempoIdAtual,
                    hora_inicio: cellAtual.getAttribute('data-hora-inicio'),
                    hora_fim: cellAtual.getAttribute('data-hora-fim')
                };
            }
            
            var trs = Array.from(document.querySelectorAll('#scheduleTable tbody tr'));
            var trAtual = cellAtual.closest('tr');
            var idxCol = Array.from(trAtual.children).indexOf(cellAtual);
            
            if (deltaTempo !== 0) {
                var trDestino = null;
                trs.forEach(function(tr) {
                    if (parseInt(tr.getAttribute('data-tempo-id')) == novoTempo.id) {
                        trDestino = tr;
                    }
                });
                
                if (trDestino) {
                    var cellDestino = trDestino.children[idxCol];
                    if (cellDestino && cellDestino.getAttribute('data-horario-id')) {
                        mostrarToast('A célula de destino já está ocupada.', 'error');
                        return;
                    }
                }
            }
            
            moverHorarioAjax(id, novoDia, novoTempo.id, novoTempo.hora_inicio, novoTempo.hora_fim);
        }

        // ============================================
        // TOAST
        // ============================================
        function mostrarToast(msg, tipo) {
            var t = document.getElementById('toast');
            t.className = 'toast ' + (tipo || '');
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2500);
        }

        // ============================================
        // MODAL
        // ============================================
        function abrirModal(celula) {
            if (!PODE_MOVER) return;
            
            var dia = celula.getAttribute('data-dia');
            var horaInicio = celula.getAttribute('data-hora-inicio');
            var horaFim = celula.getAttribute('data-hora-fim');
            var horarioId = celula.getAttribute('data-horario-id');
            var tempoId = celula.getAttribute('data-tempo-id');
            
            document.getElementById('edit_dia').textContent = dia;
            document.getElementById('edit_horario').textContent = horaInicio + ' - ' + horaFim;
            document.getElementById('edit_hora_inicio').value = horaInicio;
            document.getElementById('edit_hora_fim').value = horaFim;
            document.getElementById('edit_dia_semana').value = dia;
            document.getElementById('edit_tempo_id').value = tempoId;
            
            if (horarioId) {
                document.getElementById('modalTitulo').textContent = '✏️ Editar Horário';
                buscarHorario(horarioId);
            } else {
                document.getElementById('modalTitulo').textContent = '➕ Adicionar Horário';
                document.getElementById('edit_id').value = '';
                document.getElementById('edit_disciplina').value = '';
                document.getElementById('edit_funcionario_id').value = '';
                document.getElementById('edit_sala').value = '';
                document.getElementById('edit_is_intervalo').checked = false;
                document.getElementById('formEditar').action = 'add_horario_ajax.php';
            }
            
            document.getElementById('modalEditar').classList.add('active');
        }

        function fecharModal() {
            var modal = document.getElementById('modalEditar');
            if (modal) modal.classList.remove('active');
        }

        function buscarHorario(id) {
            if (!PODE_MOVER) return;
            
            fetch('get_horario.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_id').value = data.id;
                        document.getElementById('edit_disciplina').value = data.disciplina || '';
                        document.getElementById('edit_funcionario_id').value = data.funcionario_id || '';
                        document.getElementById('edit_sala').value = data.sala || '';
                        document.getElementById('edit_is_intervalo').checked = data.is_intervalo == 1;
                        document.getElementById('formEditar').action = 'editar_horario.php';
                    }
                })
                .catch(err => console.error('Erro:', err));
        }

        function excluirHorario() {
            if (!PODE_MOVER) {
                mostrarToast('Não tem permissão para excluir horários.', 'error');
                return;
            }
            
            var id = document.getElementById('edit_id').value;
            if (!id) {
                mostrarToast('Nada para excluir.', 'error');
                return;
            }
            if (confirm('Tem certeza que deseja excluir este horário?')) {
                window.location.href = 'delete.php?id=' + id + '&turma_id=<?= $selectedTurma ?>';
            }
        }

        // Fechar modal
        var modalEl = document.getElementById('modalEditar');
        if (modalEl) {
            modalEl.addEventListener('click', function(e) {
                if (e.target === this) fecharModal();
            });
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') fecharModal();
        });

        // Destacar dia de hoje
        document.addEventListener('DOMContentLoaded', function() {
            const days = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
            const today = new Date();
            const dayIndex = today.getDay();
            if (dayIndex >= 1 && dayIndex <= 6) {
                const todayName = days[dayIndex - 1];
                const headers = document.querySelectorAll('.schedule-table th');
                headers.forEach((th, index) => {
                    if (index > 0 && th.textContent.trim() === todayName.replace('-feira', '')) {
                        th.style.background = '#d4a843';
                        th.style.color = '#1a2a3a';
                    }
                });
            }
        });
    </script>
</body>
</html>