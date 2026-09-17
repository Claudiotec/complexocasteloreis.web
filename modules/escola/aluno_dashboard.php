<?php
// ============================================
// modules/escola/aluno_dashboard.php - Painel do Aluno
// VERSÃO PROFISSIONAL - CORRIGIDA
// ============================================

// ============================================
// CAMINHOS CORRETOS - AJUSTADOS
// ============================================

// Definir caminho base
$base_path = dirname(__DIR__, 2); // Vai para a raiz do projeto

// Incluir arquivos com caminhos absolutos
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// ===== BUSCAR DADOS DO ALUNO =====
$usuario_id = $_SESSION['usuario_id'];
$aluno_id = null;
$aluno_nome = '';
$aluno_email = '';
$aluno_classe = '';
$aluno_curso = '';
$aluno_foto = '';

try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.nome as usuario_nome, u.email 
        FROM alunos a
        JOIN usuarios u ON a.usuario_id = u.id
        WHERE a.usuario_id = ? AND a.status = 'ativo'
    ");
    $stmt->execute([$usuario_id]);
    $aluno = $stmt->fetch();
    
    if ($aluno) {
        $aluno_id = $aluno['id'];
        $aluno_nome = $aluno['usuario_nome'] ?? $aluno['nome'] ?? 'Aluno';
        $aluno_email = $aluno['email'] ?? '';
        $aluno_classe = $aluno['Classe'] ?? '';
        $aluno_curso = $aluno['Curso'] ?? '';
        $aluno_foto = $aluno['foto'] ?? '';
    }
} catch (Exception $e) {
    // Erro ao buscar aluno
}

// ===== BUSCAR MATRÍCULA ATIVA =====
$matricula = null;
$turma_id = null;
try {
    $stmt = $pdo->prepare("
        SELECT m.*, t.nome as turma_nome, t.classe, t.curso, t.sala, t.turno
        FROM matriculas m
        JOIN turmas t ON m.turma_id = t.id
        WHERE m.aluno_id = ? AND m.status = 'ativa'
        ORDER BY m.data_matricula DESC
        LIMIT 1
    ");
    $stmt->execute([$aluno_id]);
    $matricula = $stmt->fetch();
    if ($matricula) {
        $turma_id = $matricula['turma_id'];
    }
} catch (Exception $e) {
    $matricula = null;
}

// ===== BUSCAR NOTAS =====
$notas = [];
$media_geral = 0;
try {
    if ($turma_id) {
        $stmt = $pdo->prepare("
            SELECT n.*, d.nome as disciplina_nome, d.codigo
            FROM notas n
            JOIN disciplinas d ON n.disciplina_id = d.id
            WHERE n.aluno_id = ? AND n.turma_id = ?
            ORDER BY d.nome
        ");
        $stmt->execute([$aluno_id, $turma_id]);
        $notas = $stmt->fetchAll();
        
        if (count($notas) > 0) {
            $soma = 0;
            foreach ($notas as $n) {
                $soma += $n['nota_final'] ?? 0;
            }
            $media_geral = $soma / count($notas);
        }
    }
} catch (Exception $e) {
    $notas = [];
}

// ===== BUSCAR FREQUÊNCIA =====
$total_aulas = 0;
$presencas = 0;
try {
    if ($turma_id) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as presentes
            FROM frequencias
            WHERE aluno_id = ? AND turma_id = ?
        ");
        $stmt->execute([$aluno_id, $turma_id]);
        $freq_data = $stmt->fetch();
        if ($freq_data) {
            $total_aulas = $freq_data['total'] ?? 0;
            $presencas = $freq_data['presentes'] ?? 0;
        }
    }
} catch (Exception $e) {
    $total_aulas = 0;
    $presencas = 0;
}
$percentual_frequencia = $total_aulas > 0 ? round(($presencas / $total_aulas) * 100, 1) : 0;

// ===== BUSCAR HORÁRIOS =====
$horarios = [];
try {
    if ($turma_id) {
        $stmt = $pdo->prepare("
            SELECT h.*, d.nome as disciplina_nome
            FROM horarios h
            JOIN disciplinas d ON h.disciplina_id = d.id
            WHERE h.turma_id = ?
            ORDER BY FIELD(h.dia_semana, 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'), h.hora_inicio
        ");
        $stmt->execute([$turma_id]);
        $horarios = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $horarios = [];
}

// ===== BUSCAR COLEGAS =====
$colegas = [];
try {
    if ($turma_id) {
        $stmt = $pdo->prepare("
            SELECT a.id, a.nome, a.Contacto_do_Aluno
            FROM alunos a
            JOIN matriculas m ON a.id = m.aluno_id
            WHERE m.turma_id = ? AND m.status = 'ativa' AND a.id != ?
            ORDER BY a.nome
            LIMIT 20
        ");
        $stmt->execute([$turma_id, $aluno_id]);
        $colegas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $colegas = [];
}

// ===== DADOS DO USUÁRIO PARA O MENU =====
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_email = $_SESSION['usuario_email'] ?? '';
$usuario_perfil = $_SESSION['usuario_perfil'] ?? 'aluno';

// ============================================
// INCLUIR HEADER - CAMINHO CORRETO
// ============================================
// Usando caminho absoluto a partir da raiz
include $base_path . '/modules/escola/includes/header_aluno.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Aluno - SoftGest Escola</title>
    <base href="<?= SITE_URL ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           ESTILOS COMPLETOS
           ============================================ */
        :root {
            --primary: #c9a84c;
            --primary-dark: #b8973a;
            --primary-light: #f5d76e;
            --bg-dark: #0f1724;
            --bg-sidebar: #1a2332;
            --text-light: #e8edf5;
            --text-muted: #94a3b8;
            --card-bg: #ffffff;
            --border-color: #eef2f7;
            --shadow: 0 2px 15px rgba(0,0,0,0.08);
            --radius: 16px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', Tahoma, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        /* ============================================
           LAYOUT PRINCIPAL
           ============================================ */
        .dashboard-aluno {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* ============================================
           SIDEBAR PROFISSIONAL
           ============================================ */
        .sidebar-aluno {
            width: 280px;
            min-width: 280px;
            max-width: 280px;
            background: linear-gradient(180deg, #0f1724 0%, #1a2332 50%, #1e2d3d 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 4px 0 30px rgba(0,0,0,0.3);
            border-right: 3px solid var(--primary);
            transition: transform 0.3s ease-in-out;
        }

        .sidebar-aluno::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-aluno::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.02);
        }
        .sidebar-aluno::-webkit-scrollbar-thumb {
            background: rgba(201, 168, 76, 0.3);
            border-radius: 10px;
        }

        /* Perfil do Aluno na Sidebar */
        .sidebar-aluno .profile {
            padding: 25px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            text-align: center;
        }

        .sidebar-aluno .profile .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            color: #0f1724;
            margin: 0 auto 12px;
            border: 3px solid rgba(201, 168, 76, 0.3);
            box-shadow: 0 4px 20px rgba(201, 168, 76, 0.2);
            overflow: hidden;
        }

        .sidebar-aluno .profile .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-aluno .profile .name {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }

        .sidebar-aluno .profile .email {
            font-size: 13px;
            color: rgba(255,255,255,0.4);
            margin-top: 2px;
        }

        .sidebar-aluno .profile .badge-status {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        /* Menu da Sidebar */
        .sidebar-aluno .menu {
            flex: 1;
            padding: 15px 12px 20px;
        }

        .sidebar-aluno .menu .section-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255,255,255,0.2);
            padding: 14px 14px 8px;
            font-weight: 600;
        }

        .sidebar-aluno .menu a {
            display: flex;
            align-items: center;
            padding: 11px 16px;
            color: rgba(255,255,255,0.55);
            text-decoration: none;
            border-radius: 10px;
            transition: var(--transition);
            margin-bottom: 2px;
            gap: 14px;
            font-size: 15px;
            font-weight: 500;
            position: relative;
        }

        .sidebar-aluno .menu a .icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-aluno .menu a .text {
            flex: 1;
        }

        .sidebar-aluno .menu a .badge {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.5);
            font-size: 11px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 14px;
            min-width: 22px;
            text-align: center;
        }

        .sidebar-aluno .menu a .badge.primary {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
        }
        .sidebar-aluno .menu a .badge.success {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }
        .sidebar-aluno .menu a .badge.warning {
            background: rgba(243, 156, 18, 0.2);
            color: #f39c12;
        }
        .sidebar-aluno .menu a .badge.danger {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .sidebar-aluno .menu a:hover {
            background: rgba(255,255,255,0.06);
            color: #fff;
            transform: translateX(4px);
        }

        .sidebar-aluno .menu a.active {
            background: rgba(201, 168, 76, 0.12);
            color: var(--primary);
            border: 1px solid rgba(201, 168, 76, 0.08);
        }

        .sidebar-aluno .menu a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: var(--primary);
            border-radius: 0 3px 3px 0;
        }

        .sidebar-aluno .menu a.active .icon {
            color: var(--primary);
        }

        /* Footer da Sidebar - Botão Sair */
        .sidebar-aluno .sidebar-footer {
            padding: 14px 18px 18px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .sidebar-aluno .sidebar-footer .btn-sair {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
            border: 1px solid rgba(231, 76, 60, 0.1);
            width: 100%;
        }

        .sidebar-aluno .sidebar-footer .btn-sair:hover {
            background: rgba(231, 76, 60, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.2);
        }

        /* ============================================
           MAIN CONTENT
           ============================================ */
        .main-content-aluno {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
            width: calc(100% - 280px);
            max-width: calc(100% - 280px);
            background: #f0f2f5;
        }

        /* ============================================
           TOP BAR
           ============================================ */
        .topbar-aluno {
            background: #fff;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 2px solid var(--primary-light);
            min-height: 64px;
        }

        .topbar-aluno .left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .topbar-aluno .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #1a2332;
            padding: 6px 10px;
        }

        .topbar-aluno .page-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
        }

        .topbar-aluno .page-title span {
            color: var(--primary);
        }

        .topbar-aluno .right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .topbar-aluno .right .welcome {
            font-size: 14px;
            color: #4a5568;
        }

        .topbar-aluno .right .datetime {
            font-size: 13px;
            color: var(--primary);
            background: rgba(197,165,50,0.08);
            padding: 5px 16px;
            border-radius: 20px;
            border: 1px solid rgba(197,165,50,0.1);
            font-weight: 500;
            white-space: nowrap;
        }

        .topbar-aluno .right .btn-sair-top {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: #fee2e2;
            color: #991b1b;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }

        .topbar-aluno .right .btn-sair-top:hover {
            background: #fecaca;
            transform: translateY(-2px);
        }

        /* ============================================
           OVERLAY MOBILE
           ============================================ */
        .overlay-aluno {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 999;
        }

        .overlay-aluno.active {
            display: block;
        }

        /* ============================================
           CONTEÚDO DO PAINEL
           ============================================ */
        .content-aluno {
            padding: 25px 30px 40px;
        }

        /* Cards de Stats */
        .stats-aluno {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card-aluno {
            background: #fff;
            padding: 20px 24px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: default;
            position: relative;
            overflow: hidden;
        }

        .stat-card-aluno::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }

        .stat-card-aluno:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .stat-card-aluno .icon {
            font-size: 28px;
            display: block;
            margin-bottom: 6px;
        }

        .stat-card-aluno .number {
            font-size: 30px;
            font-weight: 800;
            color: #1a2332;
        }

        .stat-card-aluno .number.gold { color: var(--primary); }
        .stat-card-aluno .number.green { color: #10b981; }
        .stat-card-aluno .number.blue { color: #3b82f6; }
        .stat-card-aluno .number.red { color: #e74c3c; }

        .stat-card-aluno .label {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .stat-card-aluno .sub-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid var(--border-color);
        }

        /* Grid de 2 colunas */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .card-aluno {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .card-aluno:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        .card-aluno .card-header {
            padding: 16px 22px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-aluno .card-header .title {
            font-weight: 700;
            font-size: 16px;
            color: #1a2332;
        }

        .card-aluno .card-header .badge-count {
            background: var(--primary);
            color: #1a2332;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .card-aluno .card-body {
            padding: 20px 22px;
        }

        /* Notas */
        .nota-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .nota-item:last-child {
            border-bottom: none;
        }

        .nota-item .disciplina {
            font-weight: 500;
            color: #1a2332;
        }

        .nota-item .nota-valor {
            font-weight: 700;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 14px;
        }

        .nota-item .nota-valor.aprovado {
            background: #d1fae5;
            color: #065f46;
        }

        .nota-item .nota-valor.recuperacao {
            background: #fef3c7;
            color: #92400e;
        }

        .nota-item .nota-valor.reprovado {
            background: #fee2e2;
            color: #991b1b;
        }

        .media-geral {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px solid var(--border-color);
            text-align: center;
            font-weight: 700;
            font-size: 16px;
        }

        .media-geral .valor {
            color: var(--primary);
            font-size: 20px;
        }

        /* Frequência */
        .freq-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }

        .freq-info .label {
            color: var(--text-muted);
            font-size: 14px;
        }

        .freq-info .value {
            font-size: 20px;
            font-weight: 700;
        }

        .freq-bar {
            width: 100%;
            height: 22px;
            background: #f1f5f9;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 8px;
        }

        .freq-bar .fill {
            height: 100%;
            border-radius: 12px;
            transition: width 1s ease;
        }

        .freq-bar .fill.good { background: linear-gradient(90deg, #10b981, #34d399); }
        .freq-bar .fill.warning { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .freq-bar .fill.danger { background: linear-gradient(90deg, #ef4444, #f87171); }

        .freq-status {
            margin-top: 10px;
            font-size: 13px;
            text-align: center;
            padding: 8px;
            border-radius: 8px;
        }

        .freq-status.success { background: #d1fae5; color: #065f46; }
        .freq-status.warning { background: #fef3c7; color: #92400e; }
        .freq-status.danger { background: #fee2e2; color: #991b1b; }

        /* Tabela de Horários */
        .table-horarios {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table-horarios th {
            background: #f8fafc;
            padding: 10px 14px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }

        .table-horarios td {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-horarios tr:last-child td {
            border-bottom: none;
        }

        .table-horarios .dia {
            font-weight: 600;
            color: #1a2332;
        }

        .table-horarios .horario {
            color: var(--primary);
            font-weight: 600;
        }

        /* Colegas */
        .colega-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .colega-item:last-child {
            border-bottom: none;
        }

        .colega-item .nome {
            font-weight: 500;
            color: #1a2332;
        }

        .colega-item .contato {
            color: var(--text-muted);
            font-size: 13px;
        }

        /* Info da Turma */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row .label-info {
            color: var(--text-muted);
            font-size: 14px;
        }

        .info-row .value-info {
            font-weight: 600;
            color: #1a2332;
        }

        /* ============================================
           LEGENDA INTERATIVA COM ROLAGEM
           ============================================ */
        .legenda-container {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-top: 30px;
            overflow: hidden;
            position: relative;
        }

        .legenda-container .legenda-header {
            padding: 16px 22px;
            background: linear-gradient(135deg, #1a2332, #2c3e50);
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }

        .legenda-container .legenda-header h3 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .legenda-container .legenda-header .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s ease;
        }

        .legenda-container .legenda-header .toggle-icon.rotated {
            transform: rotate(180deg);
        }

        .legenda-container .legenda-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease, padding 0.3s ease;
            padding: 0 22px;
        }

        .legenda-container .legenda-body.open {
            max-height: 800px;
            padding: 20px 22px;
            overflow-y: auto;
        }

        .legenda-container .legenda-body::-webkit-scrollbar {
            width: 4px;
        }
        .legenda-container .legenda-body::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .legenda-container .legenda-body::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .legenda-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .legenda-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .legenda-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
        }

        .legenda-item .icon-legenda {
            font-size: 22px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .legenda-item .content-legenda h4 {
            font-size: 15px;
            font-weight: 600;
            color: #1a2332;
        }

        .legenda-item .content-legenda p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
            line-height: 1.5;
        }

        .legenda-item .content-legenda .exemplo {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 12px;
            background: #eef2f7;
            border-radius: 6px;
            font-size: 12px;
            color: #1a2332;
            font-weight: 600;
        }

        /* ============================================
           SEM MATRÍCULA
           ============================================ */
        .sem-matricula {
            background: #fef9e7;
            border-radius: var(--radius);
            padding: 50px;
            text-align: center;
            border: 2px solid #f39c12;
        }

        .sem-matricula .icon {
            font-size: 64px;
            display: block;
            margin-bottom: 15px;
        }

        .sem-matricula h3 {
            color: #f39c12;
            font-size: 24px;
        }

        .sem-matricula p {
            color: #4a5568;
            margin-top: 10px;
        }

        .sem-matricula .btn-matricula {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 35px;
            background: var(--primary);
            color: #1a2332;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
        }

        .sem-matricula .btn-matricula:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
        }

        /* ============================================
           RESPONSIVIDADE
           ============================================ */
        @media (max-width: 1024px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar-aluno {
                transform: translateX(-100%);
                width: 300px;
                min-width: 300px;
                max-width: 300px;
            }

            .sidebar-aluno.open {
                transform: translateX(0);
            }

            .main-content-aluno {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
            }

            .topbar-aluno .menu-toggle {
                display: block;
            }

            .topbar-aluno {
                padding: 10px 15px;
            }

            .topbar-aluno .page-title {
                font-size: 16px;
            }

            .topbar-aluno .right .welcome {
                display: none;
            }

            .topbar-aluno .right .datetime {
                font-size: 11px;
                padding: 3px 10px;
            }

            .topbar-aluno .right .btn-sair-top span {
                display: none;
            }

            .content-aluno {
                padding: 15px;
            }

            .stats-aluno {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .stat-card-aluno {
                padding: 15px 18px;
            }

            .stat-card-aluno .number {
                font-size: 24px;
            }

            .legenda-grid {
                grid-template-columns: 1fr;
            }

            .legenda-container .legenda-body.open {
                padding: 15px;
            }

            .sem-matricula {
                padding: 30px 20px;
            }
        }

        @media (max-width: 480px) {
            .stats-aluno {
                grid-template-columns: 1fr;
            }

            .topbar-aluno .page-title {
                font-size: 14px;
            }

            .topbar-aluno .right .datetime {
                display: none;
            }

            .table-horarios {
                font-size: 12px;
            }

            .table-horarios th,
            .table-horarios td {
                padding: 6px 8px;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-aluno">

    <!-- ============================================
         OVERLAY MOBILE
         ============================================ -->
    <div class="overlay-aluno" id="overlayAluno" onclick="toggleSidebar()"></div>

    <!-- ============================================
         SIDEBAR PROFISSIONAL
         ============================================ -->
    <aside class="sidebar-aluno" id="sidebarAluno">

        <!-- Perfil -->
        <div class="profile">
            <div class="avatar">
                <?php if (!empty($aluno_foto) && file_exists($base_path . '/assets/uploads/' . $aluno_foto)): ?>
                    <img src="<?= SITE_URL ?>assets/uploads/<?= $aluno_foto ?>" alt="Foto">
                <?php else: ?>
                    <?= strtoupper(substr($aluno_nome, 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="name"><?= htmlspecialchars($aluno_nome) ?></div>
            <div class="email"><?= htmlspecialchars($aluno_email) ?></div>
            <span class="badge-status"><?= $matricula ? '✅ Matriculado' : '⚠️ Sem Matrícula' ?></span>
        </div>

        <!-- Menu -->
        <nav class="menu">
            <div class="section-label">📊 Navegação</div>

            <a href="<?= SITE_URL ?>modules/escola/aluno_dashboard.php" class="active">
                <span class="icon">📊</span>
                <span class="text">Dashboard</span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_notas.php">
                <span class="icon">📝</span>
                <span class="text">Minhas Notas</span>
                <span class="badge primary"><?= count($notas) ?></span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_frequencia.php">
                <span class="icon">✅</span>
                <span class="text">Frequência</span>
                <span class="badge <?= $percentual_frequencia >= 75 ? 'success' : ($percentual_frequencia >= 50 ? 'warning' : 'danger') ?>">
                    <?= $percentual_frequencia ?>%
                </span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_horarios.php">
                <span class="icon">🕐</span>
                <span class="text">Horários</span>
                <span class="badge info"><?= count($horarios) ?></span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_boletim.php">
                <span class="icon">📋</span>
                <span class="text">Boletim</span>
            </a>

            <div class="section-label">👥 Social</div>

            <a href="<?= SITE_URL ?>modules/escola/aluno_colegas.php">
                <span class="icon">👥</span>
                <span class="text">Colegas</span>
                <span class="badge success"><?= count($colegas) ?></span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_mensagens.php">
                <span class="icon">💬</span>
                <span class="text">Mensagens</span>
                <span class="badge danger">0</span>
            </a>

            <div class="section-label">⚙️ Configurações</div>

            <a href="<?= SITE_URL ?>modules/escola/aluno_perfil.php">
                <span class="icon">👤</span>
                <span class="text">Meu Perfil</span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_configuracoes.php">
                <span class="icon">⚙️</span>
                <span class="text">Configurações</span>
            </a>
        </nav>

        <!-- Footer com Botão Sair -->
        <div class="sidebar-footer">
            <a href="<?= SITE_URL ?>logout.php" class="btn-sair" onclick="return confirm('Tem certeza que deseja sair?')">
                <i class="fas fa-sign-out-alt"></i>
                Sair do Sistema
            </a>
        </div>
    </aside>

    <!-- ============================================
         MAIN CONTENT
         ============================================ -->
    <main class="main-content-aluno">

        <!-- Top Bar -->
        <div class="topbar-aluno">
            <div class="left">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="page-title">🎓 <span>Painel do Aluno</span></span>
            </div>
            <div class="right">
                <span class="welcome">👋 <?= htmlspecialchars($aluno_nome) ?></span>
                <span class="datetime" id="currentDateTime"></span>
                <a href="<?= SITE_URL ?>logout.php" class="btn-sair-top" onclick="return confirm('Tem certeza que deseja sair?')">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
            </div>
        </div>

        <!-- Conteúdo -->
        <div class="content-aluno">

            <!-- ===== STATS ===== -->
            <div class="stats-aluno">
                <div class="stat-card-aluno">
                    <span class="icon">📊</span>
                    <div class="number gold"><?= count($notas) ?></div>
                    <div class="label">Disciplinas</div>
                    <div class="sub-label">Total de disciplinas cursando</div>
                </div>
                <div class="stat-card-aluno">
                    <span class="icon">⭐</span>
                    <div class="number <?= $media_geral >= 7 ? 'green' : ($media_geral >= 5 ? 'gold' : 'red') ?>">
                        <?= number_format($media_geral, 1) ?>
                    </div>
                    <div class="label">Média Geral</div>
                    <div class="sub-label">Média de todas as disciplinas</div>
                </div>
                <div class="stat-card-aluno">
                    <span class="icon">✅</span>
                    <div class="number <?= $percentual_frequencia >= 75 ? 'green' : ($percentual_frequencia >= 50 ? 'gold' : 'red') ?>">
                        <?= $percentual_frequencia ?>%
                    </div>
                    <div class="label">Frequência</div>
                    <div class="sub-label"><?= $presencas ?> de <?= $total_aulas ?> aulas</div>
                </div>
                <div class="stat-card-aluno">
                    <span class="icon">👥</span>
                    <div class="number blue"><?= count($colegas) ?></div>
                    <div class="label">Colegas</div>
                    <div class="sub-label">Alunos da sua turma</div>
                </div>
            </div>

            <!-- ===== INFORMAÇÕES DA TURMA E NOTAS ===== -->
            <?php if ($matricula): ?>
            <div class="grid-2">
                <!-- Info Turma -->
                <div class="card-aluno">
                    <div class="card-header">
                        <span class="title">🏫 Minha Turma</span>
                        <span class="badge-count"><?= htmlspecialchars($matricula['turma_nome']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="label-info">Turma</span>
                            <span class="value-info"><?= htmlspecialchars($matricula['turma_nome']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label-info">Classe</span>
                            <span class="value-info"><?= htmlspecialchars($matricula['classe']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label-info">Curso</span>
                            <span class="value-info"><?= htmlspecialchars($matricula['curso']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label-info">Sala</span>
                            <span class="value-info"><?= htmlspecialchars($matricula['sala'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label-info">Turno</span>
                            <span class="value-info"><?= htmlspecialchars($matricula['turno'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label-info">Matrícula</span>
                            <span class="value-info"><?= date('d/m/Y', strtotime($matricula['data_matricula'])) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Notas -->
                <div class="card-aluno">
                    <div class="card-header">
                        <span class="title">📊 Minhas Notas</span>
                        <span class="badge-count"><?= count($notas) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (count($notas) > 0): ?>
                            <?php foreach ($notas as $nota): ?>
                            <div class="nota-item">
                                <span class="disciplina"><?= htmlspecialchars($nota['disciplina_nome']) ?></span>
                                <span class="nota-valor <?= ($nota['nota_final'] ?? 0) >= 7 ? 'aprovado' : (($nota['nota_final'] ?? 0) >= 5 ? 'recuperacao' : 'reprovado') ?>">
                                    <?= number_format($nota['nota_final'] ?? 0, 1) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                            <div class="media-geral">
                                Média Geral: <span class="valor"><?= number_format($media_geral, 1) ?></span>
                            </div>
                        <?php else: ?>
                            <p style="color: #94a3b8; text-align: center; padding: 10px 0;">Nenhuma nota cadastrada.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ===== FREQUÊNCIA ===== -->
            <div class="card-aluno" style="margin-bottom: 30px;">
                <div class="card-header">
                    <span class="title">✅ Minha Frequência</span>
                    <span class="badge-count"><?= $percentual_frequencia ?>%</span>
                </div>
                <div class="card-body">
                    <div class="freq-info">
                        <span class="label">Presenças: <strong><?= $presencas ?></strong> de <strong><?= $total_aulas ?></strong> aulas</span>
                        <span class="value <?= $percentual_frequencia >= 75 ? 'green' : ($percentual_frequencia >= 50 ? 'gold' : 'red') ?>">
                            <?= $percentual_frequencia ?>%
                        </span>
                    </div>
                    <div class="freq-bar">
                        <div class="fill <?= $percentual_frequencia >= 75 ? 'good' : ($percentual_frequencia >= 50 ? 'warning' : 'danger') ?>" 
                             style="width: <?= $percentual_frequencia ?>%;">
                        </div>
                    </div>
                    <div class="freq-status <?= $percentual_frequencia >= 75 ? 'success' : ($percentual_frequencia >= 50 ? 'warning' : 'danger') ?>">
                        <?php if ($percentual_frequencia >= 75): ?>
                            ✅ Frequência excelente! Continue assim!
                        <?php elseif ($percentual_frequencia >= 50): ?>
                            ⚠️ Atenção! Sua frequência está abaixo do ideal (mínimo 75%).
                        <?php else: ?>
                            ❌ Sua frequência está crítica! Procure a coordenação imediatamente.
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ===== HORÁRIOS ===== -->
            <div class="card-aluno" style="margin-bottom: 30px;">
                <div class="card-header">
                    <span class="title">🕐 Meus Horários</span>
                    <span class="badge-count"><?= count($horarios) ?></span>
                </div>
                <div class="card-body" style="padding: 0; overflow-x: auto;">
                    <?php if (count($horarios) > 0): ?>
                    <table class="table-horarios">
                        <thead>
                            <tr>
                                <th>Dia</th>
                                <th>Horário</th>
                                <th>Disciplina</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horarios as $horario): ?>
                            <tr>
                                <td class="dia"><?= htmlspecialchars($horario['dia_semana']) ?></td>
                                <td class="horario"><?= date('H:i', strtotime($horario['hora_inicio'])) ?> - <?= date('H:i', strtotime($horario['hora_fim'])) ?></td>
                                <td><?= htmlspecialchars($horario['disciplina_nome'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="color: #94a3b8; text-align: center; padding: 20px;">Nenhum horário cadastrado para sua turma.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== COLEGAS ===== -->
            <div class="card-aluno" style="margin-bottom: 30px;">
                <div class="card-header">
                    <span class="title">👥 Colegas de Turma</span>
                    <span class="badge-count"><?= count($colegas) ?></span>
                </div>
                <div class="card-body">
                    <?php if (count($colegas) > 0): ?>
                        <?php foreach ($colegas as $colega): ?>
                        <div class="colega-item">
                            <span class="nome"><?= htmlspecialchars($colega['nome']) ?></span>
                            <span class="contato">📱 <?= htmlspecialchars($colega['Contacto_do_Aluno'] ?? 'N/A') ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #94a3b8; text-align: center; padding: 10px 0;">Nenhum colega encontrado.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <!-- ===== SEM MATRÍCULA ===== -->
            <div class="sem-matricula">
                <span class="icon">📋</span>
                <h3>Você não está matriculado em nenhuma turma</h3>
                <p>Entre em contato com a secretaria para realizar sua matrícula.</p>
                <a href="<?= SITE_URL ?>modules/escola/matricula.php" class="btn-matricula">📝 Solicitar Matrícula</a>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 LEGENDA INTERATIVA COM ROLAGEM
                 ============================================ -->
            <div class="legenda-container">
                <div class="legenda-header" onclick="toggleLegenda()">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        Legenda do Painel do Aluno
                        <span style="font-size: 12px; color: rgba(255,255,255,0.5); font-weight: 400;">
                            (Clique para expandir)
                        </span>
                    </h3>
                    <span class="toggle-icon" id="legendaIcon">▼</span>
                </div>
                <div class="legenda-body" id="legendaBody">
                    <div class="legenda-grid">

                        <div class="legenda-item">
                            <span class="icon-legenda">📊</span>
                            <div class="content-legenda">
                                <h4>Dashboard</h4>
                                <p>Visão geral do seu desempenho escolar com estatísticas em tempo real de notas, frequência e colegas.</p>
                                <span class="exemplo">Ex: Média Geral, Total de Disciplinas</span>
                            </div>
                        </div>

                        <div class="legenda-item">
                            <span class="icon-legenda">📝</span>
                            <div class="content-legenda">
                                <h4>Minhas Notas</h4>
                                <p>Acompanhe suas notas por disciplina. Cores indicam: <span style="color: #10b981;">Verde</span> = Aprovado, <span style="color: #f39c12;">Amarelo</span> = Recuperação, <span style="color: #e74c3c;">Vermelho</span> = Reprovado.</p>
                                <span class="exemplo">Ex: Matemática: 8.5 ✅</span>
                            </div>
                        </div>

                        <div class="legenda-item">
                            <span class="icon-legenda">✅</span>
                            <div class="content-legenda">
                                <h4>Frequência</h4>
                                <p>Percentual de presença nas aulas. <span style="color: #10b981;">Verde</span> = Bom (&gt;75%), <span style="color: #f39c12;">Amarelo</span> = Atenção (&gt;50%), <span style="color: #e74c3c;">Vermelho</span> = Crítico (&lt;50%).</p>
                                <span class="exemplo">Ex: 85% - Frequência Excelente</span>
                            </div>
                        </div>

                        <div class="legenda-item">
                            <span class="icon-legenda">🕐</span>
                            <div class="content-legenda">
                                <h4>Horários</h4>
                                <p>Visualize seus horários semanais com dias, horários e disciplinas da sua turma.</p>
                                <span class="exemplo">Ex: Segunda 08:00 - Matemática</span>
                            </div>
                        </div>

                        <div class="legenda-item">
                            <span class="icon-legenda">📋</span>
                            <div class="content-legenda">
                                <h4>Boletim</h4>
                                <p>Relatório completo com todas as suas notas, frequência e desempenho geral por período.</p>
                                <span class="exemplo">Ex: Boletim do 1º Bimestre</span>
                            </div>
                        </div>

                        <div class="legenda-item">
                            <span class="icon-legenda">👥</span>
                            <div class="content-legenda">
                                <h4>Colegas</h4>
                                <p>Lista dos colegas da sua turma com informações de contato para trabalho em grupo.</p>
                                <span class="exemplo">Ex: João Silva - (11) 99999-9999</span>
                            </div>
                        </div>

                        <div class="legenda-item">
                            <span class="icon-legenda">💬</span>
                            <div class="content-legenda">
                                <h4>Mensagens</h4>
                                <p>Comunique-se com professores e colegas através do sistema de mensagens integrado.</p>
                                <span class="exemplo">Ex: Nova mensagem do Professor</span>
                            </div>
                        </div>

                        <div class="legenda-item">
                            <span class="icon-legenda">👤</span>
                            <div class="content-legenda">
                                <h4>Meu Perfil</h4>
                                <p>Gerencie seus dados pessoais, foto e informações de contato do aluno.</p>
                                <span class="exemplo">Ex: Atualizar foto e telefone</span>
                            </div>
                        </div>

                        <div class="legenda-item">
                            <span class="icon-legenda">⚙️</span>
                            <div class="content-legenda">
                                <h4>Configurações</h4>
                                <p>Personalize preferências do sistema, notificações e temas do painel do aluno.</p>
                                <span class="exemplo">Ex: Alterar senha, tema escuro</span>
                            </div>
                        </div>

                        <div class="legenda-item" style="border-left-color: #e74c3c;">
                            <span class="icon-legenda">🚪</span>
                            <div class="content-legenda">
                                <h4>Botão Sair</h4>
                                <p>Encerre sua sessão com segurança clicando no botão "Sair" no menu lateral ou no canto superior direito.</p>
                                <span class="exemplo">Ex: Sair do Sistema</span>
                            </div>
                        </div>

                    </div>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color); text-align: center; font-size: 13px; color: var(--text-muted);">
                        <i class="fas fa-arrow-up"></i> Role para ver mais funções
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script>
    // Toggle Sidebar Mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebarAluno');
        const overlay = document.getElementById('overlayAluno');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }

    // Fechar sidebar ao clicar fora
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebarAluno');
        const toggle = document.querySelector('.menu-toggle');
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('open');
            document.getElementById('overlayAluno').classList.remove('active');
        }
    });

    // Toggle Legenda
    function toggleLegenda() {
        const body = document.getElementById('legendaBody');
        const icon = document.getElementById('legendaIcon');
        body.classList.toggle('open');
        icon.classList.toggle('rotated');
    }

    // Abrir legenda automaticamente ao carregar a página (após 1s)
    setTimeout(() => {
        const body = document.getElementById('legendaBody');
        const icon = document.getElementById('legendaIcon');
        body.classList.add('open');
        icon.classList.add('rotated');
    }, 1000);

    // Fechar legenda automaticamente após 10 segundos
    setTimeout(() => {
        const body = document.getElementById('legendaBody');
        const icon = document.getElementById('legendaIcon');
        body.classList.remove('open');
        icon.classList.remove('rotated');
    }, 10000);

    // Relógio em tempo real
    function updateDateTime() {
        const now = new Date();
        const options = { 
            weekday: 'short', 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        document.getElementById('currentDateTime').textContent = now.toLocaleDateString('pt-BR', options);
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Animação da barra de frequência ao carregar
    document.addEventListener('DOMContentLoaded', function() {
        const fills = document.querySelectorAll('.freq-bar .fill');
        fills.forEach(function(fill) {
            const width = fill.style.width;
            fill.style.width = '0%';
            setTimeout(() => {
                fill.style.width = width;
            }, 500);
        });
    });
</script>

</body>
</html>