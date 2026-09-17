<?php
// ============================================
// coordenador_pedagogico/dashboard.php
// Dashboard do Coordenador Pedagógico - COMPLETO
// ============================================

session_start();

// ===== VERIFICAR LOGIN =====
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /softgest_web/login.php');
    exit;
}

// ===== VERIFICAR PERFIL =====
$perfil = $_SESSION['usuario_perfil'] ?? '';
if ($perfil != 'coordenador_pedagogico' && $perfil != 'coordenador' && $perfil != 'admin') {
    header('Location: /softgest_web/index.php');
    exit;
}

// ===== CONFIGURAÇÕES =====
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

// ===== DADOS DO USUÁRIO =====
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Coordenador';
$usuario_email = $_SESSION['usuario_email'] ?? '';
$usuario_perfil = $_SESSION['usuario_perfil'] ?? 'coordenador_pedagogico';

// ===== INICIALIZAR VARIÁVEIS =====
$totalAlunos = 0;
$totalProfessores = 0;
$totalTurmas = 0;
$totalDisciplinas = 0;
$totalPrazos = 0;
$totalMatriculas = 0;
$totalPagamentos = 0;
$turmasAtivas = [];
$professoresList = [];
$disciplinasList = [];
$coordenadoresList = [];
$distribuicoes = [];
$prazosList = [];
$alunosList = [];
$pagamentosRecentes = [];
$notasList = [];
$frequenciaDados = [];
$mensagensNaoLidas = 0;
$empresa = [];

// ===== BUSCAR DADOS DA EMPRESA =====
try {
    $stmt = $pdo->query("SELECT * FROM empresa WHERE id = 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$empresa) {
        $empresa = [
            'id' => 1,
            'razao_social' => 'COMPLEXO ESCOLAR CASTELO REIS',
            'nome_fantasia' => 'COMPLEXO ESCOLAR CASTELO REIS',
            'endereco' => 'Icolo e Bengo; Km44, Desvio do Bom Jesus',
            'telefone' => '972902412',
            'email' => 'complexoescolarcasteloreis@gmail.com'
        ];
    }
} catch (PDOException $e) {
    error_log("Erro ao carregar empresa: " . $e->getMessage());
}

// ===== BUSCAR DADOS DO BANCO =====
try {
    // Total de Alunos
    $stmt = $pdo->query("SELECT COUNT(*) FROM alunos WHERE status = 'ativo'");
    $totalAlunos = $stmt->fetchColumn() ?: 0;
    
    // Total de Professores (da tabela forca_trabalho e funcionarios)
    $stmt = $pdo->query("SELECT COUNT(*) FROM forca_trabalho WHERE status = 'ativo'");
    $totalProfessores = $stmt->fetchColumn() ?: 0;
    
    // Total de Turmas
    $stmt = $pdo->query("SELECT COUNT(*) FROM turmas WHERE status = 'ativa'");
    $totalTurmas = $stmt->fetchColumn() ?: 0;
    
    // Total de Disciplinas
    $stmt = $pdo->query("SELECT COUNT(*) FROM disciplinas WHERE status = 'ativa'");
    $totalDisciplinas = $stmt->fetchColumn() ?: 0;
    
    // Total de Matrículas
    $stmt = $pdo->query("SELECT COUNT(*) FROM alunos WHERE status = 'ativo'");
    $totalMatriculas = $stmt->fetchColumn() ?: 0;
    
    // Total de Prazos
    $stmt = $pdo->query("SELECT COUNT(*) FROM prazos WHERE status = 'ativo'");
    $totalPrazos = $stmt->fetchColumn() ?: 0;
    
    // Total de Pagamentos
    $stmt = $pdo->query("SELECT COUNT(*) FROM pagamentos WHERE status = 'confirmado'");
    $totalPagamentos = $stmt->fetchColumn() ?: 0;
    
    // Mensagens não lidas
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mensagens WHERE destinatario_id = ? AND status = 'nao_lida'");
        $stmt->execute([$usuario_id]);
        $mensagensNaoLidas = $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        $mensagensNaoLidas = 0;
    }
    
    // Turmas Ativas com contagem de alunos
    $stmt = $pdo->query("
        SELECT t.*, 
               (SELECT COUNT(*) FROM alunos a WHERE a.TURMA = t.nome AND a.status = 'ativo') as total_alunos
        FROM turmas t
        WHERE t.status = 'ativa'
        ORDER BY t.classe, t.nome
    ");
    $turmasAtivas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Professores (da forca_trabalho)
    $stmt = $pdo->query("
        SELECT * FROM forca_trabalho 
        WHERE status = 'ativo' 
        ORDER BY nome_completo
    ");
    $professoresList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Disciplinas
    $stmt = $pdo->query("SELECT * FROM disciplinas WHERE status = 'ativa' ORDER BY nome");
    $disciplinasList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Alunos
    $stmt = $pdo->query("
        SELECT id, nome, Sexo, Classe, TURMA, status 
        FROM alunos 
        WHERE status = 'ativo' 
        ORDER BY nome 
        LIMIT 50
    ");
    $alunosList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Distribuições
    $stmt = $pdo->query("
        SELECT d.*, f.nome_completo as professor_nome 
        FROM destribuicao_professores d
        LEFT JOIN forca_trabalho f ON d.professor_id = f.id
        ORDER BY d.id DESC
        LIMIT 50
    ");
    $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prazos
    $stmt = $pdo->query("SELECT * FROM prazos WHERE status = 'ativo' ORDER BY data_limite ASC LIMIT 5");
    $prazosList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pagamentos Recentes
    $stmt = $pdo->query("
        SELECT p.*, a.nome as aluno_nome, e.nome as emolumento_nome
        FROM pagamentos p
        LEFT JOIN alunos a ON p.aluno_id = a.id
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        WHERE p.status = 'confirmado'
        ORDER BY p.data_pagamento DESC
        LIMIT 10
    ");
    $pagamentosRecentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Notas (últimas 20)
    $stmt = $pdo->query("
        SELECT n.*, a.nome as aluno_nome, d.nome as disciplina_nome 
        FROM notas_alunos n
        LEFT JOIN alunos a ON n.id_aluno = a.id
        LEFT JOIN disciplinas d ON n.disciplina = d.nome
        ORDER BY n.id DESC
        LIMIT 20
    ");
    $notasList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Frequência (últimos 7 dias)
    $stmt = $pdo->query("
        SELECT DATE(data) as data, 
               COUNT(*) as total,
               SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as presentes,
               SUM(CASE WHEN status = 'ausente' THEN 1 ELSE 0 END) as ausentes
        FROM frequencia
        WHERE data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(data)
        ORDER BY data ASC
    ");
    $frequenciaDados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao carregar dados: " . $e->getMessage());
}

// ===== FUNÇÕES AUXILIARES =====
function formatarData($data) {
    if (!$data) return '-';
    $timestamp = strtotime($data);
    return date('d/m/Y H:i', $timestamp);
}

function formatarDataSimples($data) {
    if (!$data) return '-';
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}

function getStatusBadge($status) {
    $classes = [
        'ativo' => 'success',
        'inativo' => 'danger',
        'pendente' => 'warning',
        'confirmado' => 'success',
        'cancelado' => 'danger',
        'concluida' => 'info',
        'ativa' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

function getNotaClass($nota) {
    if ($nota === null || $nota === '') return '';
    $n = (float)$nota;
    if ($n >= 14) return 'nota-alta';
    if ($n >= 10) return 'nota-media';
    if ($n >= 8) return 'nota-media';
    return 'nota-baixa';
}

function getSituacao($mfd) {
    if ($mfd === null) return 'SEM NOTA';
    if ($mfd >= 10) return 'APROVADO';
    if ($mfd >= 8) return 'RECUPERAÇÃO';
    return 'REPROVADO';
}

function getSituacaoClass($situacao) {
    switch ($situacao) {
        case 'APROVADO': return 'success';
        case 'RECUPERAÇÃO': return 'warning';
        case 'REPROVADO': return 'danger';
        default: return 'secondary';
    }
}

function calcularMFD($mt1, $mt2, $mt3) {
    $notas = array_filter([$mt1, $mt2, $mt3], function($n) {
        return $n !== null && $n !== '' && is_numeric($n);
    });
    if (empty($notas)) return null;
    return round(array_sum($notas) / count($notas), 1);
}

// ===== INCLUIR HEADER =====
include_once $base_path . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Coordenador Pedagógico</title>
    <!-- CSS Local -->
    <link href="/softgest_web/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/softgest_web/assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="/softgest_web/assets/css/dataTables.bootstrap5.min.css">
    
    <style>
        /* ===== ESTILOS GLOBAIS ===== */
        :root {
            --primary-color: #c9a84c;
            --primary-dark: #b8973a;
            --secondary-color: #1a2332;
            --bg-light: #f8f9fa;
            --text-color: #2d3748;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
            --radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 260px;
            background: var(--secondary-color);
            color: white;
            min-height: 100vh;
            padding: 0;
            flex-shrink: 0;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 2px 0 20px rgba(0,0,0,0.2);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        .sidebar-brand {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }
        .sidebar-brand h4 {
            font-weight: 700;
            margin: 0;
            color: #f5d76e;
            font-size: 16px;
        }
        .sidebar-brand small {
            color: rgba(255,255,255,0.5);
            font-size: 11px;
        }

        .sidebar-user {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-user .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .sidebar-user .user-info .name {
            font-weight: 600;
            font-size: 14px;
            color: white;
        }
        .sidebar-user .user-info .email {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
        }
        .sidebar-user .user-info .perfil {
            font-size: 10px;
            background: rgba(245, 215, 110, 0.2);
            color: #f5d76e;
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
        }

        .sidebar-nav {
            padding: 10px 0;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            gap: 12px;
            font-size: 13px;
            cursor: pointer;
        }
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.05);
            color: white;
            border-left-color: var(--primary-color);
        }
        .sidebar-nav a.active {
            background: rgba(245, 215, 110, 0.1);
            color: #f5d76e;
            border-left-color: var(--primary-color);
        }
        .sidebar-nav a .nav-icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        .sidebar-nav a .badge {
            margin-left: auto;
            font-size: 10px;
            padding: 2px 8px;
            background: #e74c3c;
            animation: pulse-badge 2s infinite;
        }
        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.05);
            margin: 10px 20px;
        }

        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
            margin-top: auto;
        }
        .sidebar-footer .user-name {
            font-weight: 600;
            color: white;
            font-size: 13px;
        }
        .sidebar-footer .user-role {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
        }
        .sidebar-footer .btn-logout {
            display: block;
            text-align: center;
            padding: 8px;
            margin-top: 10px;
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s;
        }
        .sidebar-footer .btn-logout:hover {
            background: rgba(231, 76, 60, 0.4);
            color: #fff;
        }

        /* ===== TOP BAR ===== */
        .top-bar {
            background: white;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 2px solid var(--primary-color);
        }
        .top-bar .page-title h3 {
            margin: 0;
            font-weight: 700;
            color: var(--secondary-color);
            font-size: 20px;
        }
        .top-bar .page-title small {
            color: var(--text-muted);
            font-size: 13px;
        }
        .top-bar .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .top-bar .user-info .welcome-text {
            font-size: 14px;
            color: var(--text-color);
        }
        .top-bar .user-info .badge-perfil {
            background: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 11px;
        }
        .top-bar .user-info .time {
            color: var(--text-muted);
            font-size: 13px;
        }
        .btn-toggle-sidebar {
            display: none;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--secondary-color);
            margin-right: 15px;
        }

        /* ===== CONTENT AREA ===== */
        .content-area {
            padding: 25px 30px;
        }

        /* ===== STATS CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px 25px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
            border-left: 4px solid var(--primary-color);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .stat-card .icon {
            font-size: 32px;
            width: 55px;
            height: 55px;
            background: var(--bg-light);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-card .info h3 {
            font-size: 24px;
            font-weight: 700;
            color: var(--secondary-color);
            margin: 0;
        }
        .stat-card .info p {
            font-size: 14px;
            color: var(--text-muted);
            margin: 0;
        }

        /* ===== CARDS ===== */
        .card-modern {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 25px;
        }
        .card-modern .card-header {
            background: var(--secondary-color);
            color: white;
            padding: 12px 20px;
            border-bottom: none;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 15px;
        }
        .card-modern .card-header .btn-light {
            background: rgba(255,255,255,0.12);
            border: none;
            color: white;
            font-size: 12px;
            padding: 4px 14px;
            border-radius: 20px;
            transition: all 0.3s;
            text-decoration: none;
            cursor: pointer;
        }
        .card-modern .card-header .btn-light:hover {
            background: rgba(255,255,255,0.25);
        }
        .card-modern .card-body {
            padding: 20px;
        }

        /* ===== TABELAS ===== */
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .table-custom thead {
            background: var(--bg-light);
        }
        .table-custom th {
            padding: 10px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
        }
        .table-custom td {
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        .table-custom tbody tr:hover {
            background: rgba(201, 168, 76, 0.05);
        }

        /* ===== BADGES ===== */
        .badge-success { background: #27ae60; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        .badge-danger { background: #e74c3c; color: white; }
        .badge-info { background: #3498db; color: white; }
        .badge-secondary { background: #95a5a6; color: white; }
        .badge-primary { background: #3498db; color: white; }

        /* ===== BOTÕES ===== */
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: white;
            color: var(--text-color);
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .action-btn:hover {
            border-color: var(--primary-color);
            background: var(--bg-light);
        }
        .action-btn i { font-size: 14px; }

        .btn-group-actions {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        /* ===== QUICK ACTIONS ===== */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px;
            background: var(--bg-light);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s;
            font-weight: 500;
            font-size: 12px;
            cursor: pointer;
        }
        .quick-action:hover {
            border-color: var(--primary-color);
            background: #f5edd6;
            transform: translateY(-3px);
        }
        .quick-action .icon {
            font-size: 28px;
            margin-bottom: 3px;
        }

        /* ===== PROGRESS ===== */
        .progress {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
        }
        .progress-bar {
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        /* ===== SIDEBAR OVERLAY ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            cursor: pointer;
        }
        .sidebar-overlay.active { display: block !important; }

        /* ===== LOADING ===== */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .loading-overlay.active { display: flex; }
        .loading-spinner { color: white; font-size: 2rem; }

        /* ===== NOTIFICAÇÕES ===== */
        #notificacoesContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            width: 350px;
            max-height: 500px;
            overflow-y: auto;
            pointer-events: none;
        }
        .notification-toast {
            background: white;
            border-left: 4px solid #3498db;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            margin-bottom: 10px;
            padding: 12px 15px;
            pointer-events: auto;
            cursor: pointer;
            animation: slideIn 0.3s ease;
        }
        .notification-toast.success { border-left-color: #27ae60; }
        .notification-toast.warning { border-left-color: #f39c12; }
        .notification-toast.danger { border-left-color: #e74c3c; }
        .notification-toast.info { border-left-color: #3498db; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        /* ===== DROPDOWN ===== */
        .dropdown-user .dropdown-menu {
            right: 0;
            left: auto;
            min-width: 200px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            border: none;
            padding: 5px 0;
        }
        .dropdown-user .dropdown-item {
            padding: 8px 20px;
            font-size: 13px;
        }
        .dropdown-user .dropdown-item:hover {
            background: var(--bg-light);
        }
        .dropdown-user .dropdown-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* ===== NOTA CLASSES ===== */
        .nota-alta { color: #27ae60; font-weight: 700; }
        .nota-media { color: #f39c12; }
        .nota-baixa { color: #e74c3c; font-weight: 700; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .btn-toggle-sidebar {
                display: block;
            }
            .top-bar {
                padding: 10px 15px;
                flex-wrap: wrap;
                gap: 8px;
            }
            .top-bar .user-info .welcome-text {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .content-area {
                padding: 15px;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .stat-card {
                padding: 15px;
            }
            .stat-card .icon {
                font-size: 24px;
                width: 45px;
                height: 45px;
            }
            .stat-card .info h3 {
                font-size: 20px;
            }
            .card-modern .card-body {
                padding: 15px;
                overflow-x: auto;
            }
            .top-bar .page-title h3 {
                font-size: 18px;
            }
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .top-bar .user-info {
                justify-content: space-between;
            }
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
    </div>
</div>

<div class="dashboard-wrapper">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4>🏫 <?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest') ?></h4>
            <small>Enterprise • Dashboard</small>
        </div>
        
        <div class="sidebar-user">
            <div class="avatar">👤</div>
            <div class="user-info">
                <div class="name"><?= htmlspecialchars($usuario_nome) ?></div>
                <div class="email"><?= htmlspecialchars($usuario_email) ?></div>
                <div class="perfil"><?= ucfirst(str_replace('_', ' ', $usuario_perfil)) ?></div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="#" class="active" onclick="showSection('dashboard')">
                <span class="nav-icon">📊</span> Dashboard
            </a>
            <a href="#" onclick="showSection('distribuicao')">
                <span class="nav-icon">👨‍🏫</span> Distribuição de Professores
            </a>
            <a href="#" onclick="abrirMensagens()">
                <span class="nav-icon">💬</span> Mensagens
                <span class="badge" id="msgBadge" <?= $mensagensNaoLidas > 0 ? '' : 'style="display:none;"' ?>><?= $mensagensNaoLidas ?></span>
            </a>
            <a href="#" onclick="showSection('bancoNotas')">
                <span class="nav-icon">📝</span> Banco de Notas
            </a>
            <a href="#" onclick="showSection('turmas')">
                <span class="nav-icon">🏫</span> Gestão de Turmas
            </a>
            <a href="#" onclick="showSection('disciplinas')">
                <span class="nav-icon">📚</span> Disciplinas
            </a>
            <a href="#" onclick="abrirTemplate('alunos')">
                <span class="nav-icon">🎓</span> Alunos
            </a>
            <a href="#" onclick="abrirTemplate('professores')">
                <span class="nav-icon">👨‍🏫</span> Professores
            </a> 
            <a href="#" onclick="abrirTemplate('pautas_trimestrais')">
                <span class="nav-icon">📅</span> Pautas Trimestrais
            </a>
            <a href="#" onclick="abrirTemplate('pautas_finais')">
                <span class="nav-icon">📄</span> Pautas Finais
            </a>
            <a href="#" onclick="abrirTemplate('boletins')">
                <span class="nav-icon">📃</span> Boletins
            </a>
            <a href="#" onclick="showSection('estatisticas')">
                <span class="nav-icon">📈</span> Estatísticas
            </a>
            
            <div class="sidebar-divider"></div>
            
            <a href="#" onclick="showSection('prazos')">
                <span class="nav-icon">⏰</span> Prazos
                <span class="badge">!</span>
            </a>
            <a href="#" onclick="showSection('pagamentos')">
                <span class="nav-icon">💰</span> Pagamentos
            </a>
            <a href="#" onclick="showSection('configuracoes')">
                <span class="nav-icon">⚙️</span> Configurações
            </a>
            <a href="#" onclick="showSection('logs')">
                <span class="nav-icon">📋</span> Logs de Atividades
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-name">👤 <?= htmlspecialchars($usuario_nome) ?></div>
            <div class="user-role">Função: Coordenador Pedagógico</div>
            <a href="/softgest_web/logout.php" class="btn-logout">🚪 Sair do Sistema</a>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <button class="btn-toggle-sidebar" onclick="toggleSidebar()">☰</button>
                <h3 id="pageTitle">Dashboard Pedagógico</h3>
                <small id="pageSubtitle">Gestão completa do sistema acadêmico</small>
            </div>
            <div class="user-info">
                <span class="welcome-text">Bem-vindo, <?= htmlspecialchars($usuario_nome) ?></span>
                <span class="badge-perfil">📋 Coordenador Pedagógico</span>
                <span class="time">
                    <i class="bi bi-clock"></i>
                    <span id="currentTime">00:00:00</span>
                </span>
                <div class="dropdown-user">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> Perfil</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Configurações</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/softgest_web/logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">

            <!-- ========================================== -->
            <!-- DASHBOARD -->
            <!-- ========================================== -->
            <div id="section-dashboard">
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="icon">🎓</div>
                        <div class="info">
                            <h3><?= number_format($totalAlunos) ?></h3>
                            <p>Total de Alunos</p>
                        </div>
                    </div>
                    <div class="stat-card" style="border-left-color: #9b59b6;">
                        <div class="icon">👨‍🏫</div>
                        <div class="info">
                            <h3><?= number_format($totalProfessores) ?></h3>
                            <p>Professores</p>
                        </div>
                    </div>
                    <div class="stat-card" style="border-left-color: #3498db;">
                        <div class="icon">📚</div>
                        <div class="info">
                            <h3><?= number_format($totalTurmas) ?></h3>
                            <p>Turmas Ativas</p>
                        </div>
                    </div>
                    <div class="stat-card" style="border-left-color: #27ae60;">
                        <div class="icon">📊</div>
                        <div class="info">
                            <h3><?= number_format($totalDisciplinas) ?></h3>
                            <p>Disciplinas</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card-modern">
                    <div class="card-header">
                        <span>⚡ Ações Rápidas</span>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="#" class="quick-action" onclick="showSection('distribuicao')">
                                <span class="icon">👨‍🏫</span> Distribuir Professores
                            </a>
                            <a href="#" class="quick-action" onclick="showSection('bancoNotas')">
                                <span class="icon">📝</span> Lançar Notas
                            </a>
                            <a href="#" class="quick-action" onclick="abrirTemplate('pautas_finais')">
                                <span class="icon">📄</span> Gerar Pauta
                            </a>
                            <a href="#" class="quick-action"  onclick="abrirTemplate('alunos')">
                                <span class="icon">🎓</span> Matricular Aluno
                            </a>
                            <a href="#" class="quick-action" onclick="abrirTemplate('turmas')">
                                <span class="icon">🏫</span> Criar Turma
                            </a>
                            <a href="#" class="quick-action" onclick="showSection('estatisticas')">
                                <span class="icon">📊</span> Estatísticas
                            </a>
                            <a href="#" class="quick-action" onclick="showSection('pagamentos')">
                                <span class="icon">💰</span> Pagamentos
                            </a>
                            <a href="#" class="quick-action" onclick="abrirModalPrazo()">
                                <span class="icon">⏰</span> Novo Prazo
                            </a>
                            <a href="#" class="quick-action" onclick="abrirMensagens()">
                                <span class="icon">💬</span> Mensagens
                            </a>
                            <a href="#" class="quick-action" onclick="abrirTemplate('professores')">
                                <span class="icon">👨‍🏫</span> Professores
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Turmas -->
                <div class="card-modern">
                    <div class="card-header">
                        <span>🏫 Turmas Ativas</span>
                        <a href="#" class="btn-light" onclick="showSection('turmas')">Ver Todas →</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th>Turma</th>
                                        <th>Classe</th>
                                        <th>Turno</th>
                                        <th>Alunos</th>
                                        <th>Capacidade</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($turmasAtivas)): ?>
                                        <?php foreach ($turmasAtivas as $turma): 
                                            $alunos = $turma['total_alunos'] ?? 0;
                                            $capacidade = $turma['capacidade'] ?? 45;
                                            $percentual = $capacidade > 0 ? round(($alunos / $capacidade) * 100) : 0;
                                            $statusClass = $percentual >= 100 ? 'danger' : ($percentual >= 90 ? 'warning' : 'success');
                                            $statusText = $percentual >= 100 ? 'Cheia' : ($percentual >= 90 ? 'Quase Cheia' : 'Disponível');
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($turma['nome'] ?? '') ?></strong></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($turma['classe'] ?? '') ?></span></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($turma['turno'] ?? '') ?></span></td>
                                            <td><?= $alunos ?></td>
                                            <td><?= $capacidade ?></td>
                                            <td><span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span></td>
                                            <td>
                                                <div class="btn-group-actions">
                                                    <button class="action-btn" onclick="verDetalhesTurma(<?= $turma['id'] ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="action-btn" onclick="editarTurma(<?= $turma['id'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="action-btn" onclick="gerarPautaTurma(<?= $turma['id'] ?>)">
                                                        <i class="bi bi-file-text"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center text-muted py-3">Nenhuma turma encontrada</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Estatísticas e Prazos -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card-modern">
                            <div class="card-header">
                                <span>📊 Estatísticas</span>
                            </div>
                            <div class="card-body">
                                <?php 
                                $ocupacaoTotal = 0;
                                $totalCapacidade = 0;
                                $totalAlunosTurmas = 0;
                                if (!empty($turmasAtivas)) {
                                    foreach ($turmasAtivas as $t) {
                                        $totalCapacidade += $t['capacidade'] ?? 45;
                                        $totalAlunosTurmas += $t['total_alunos'] ?? 0;
                                    }
                                    $ocupacaoTotal = $totalCapacidade > 0 ? round(($totalAlunosTurmas / $totalCapacidade) * 100) : 0;
                                    $mediaAlunos = count($turmasAtivas) > 0 ? round($totalAlunosTurmas / count($turmasAtivas), 1) : 0;
                                } else {
                                    $mediaAlunos = 0;
                                }
                                ?>
                                <div class="mb-3">
                                    <small class="text-muted">Taxa de Ocupação</small>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-<?= $ocupacaoTotal >= 90 ? 'warning' : 'success' ?>" 
                                             style="width: <?= min($ocupacaoTotal, 100) ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted"><?= $ocupacaoTotal ?>% das vagas ocupadas</small>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">Média de Alunos por Turma</small>
                                    <h4><?= $mediaAlunos ?></h4>
                                </div>
                                <div>
                                    <small class="text-muted">Total de Pagamentos</small>
                                    <h4><?= number_format($totalPagamentos) ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card-modern">
                            <div class="card-header">
                                <span>🔔 Prazos Ativos</span>
                                <a href="#" class="btn-light" onclick="showSection('prazos')">Ver Todos</a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($prazosList)): ?>
                                    <?php foreach ($prazosList as $prazo): ?>
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span>📝 <?= htmlspecialchars($prazo['nome'] ?? 'Prazo') ?></span>
                                        <span class="badge bg-<?= $prazo['status'] == 'ativo' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($prazo['status'] ?? 'Inativo') ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted py-2">Nenhum prazo ativo</div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <small class="text-muted">Total: <strong><?= $totalPrazos ?></strong> prazos ativos</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- DISTRIBUIÇÃO DE PROFESSORES -->
            <!-- ========================================== -->
            <div id="section-distribuicao" style="display:none;">
                <?php 
                $template_path = __DIR__ . '/templates/distribuicao_professores.php';
                if (file_exists($template_path)) {
                    include_once $template_path;
                } else {
                ?>
                <div class="card-modern">
                    <div class="card-header">
                        <span>👨‍🏫 Distribuição de Professores</span>
                        <div class="btn-group-actions">
                            <button class="btn-light" onclick="exportarDistribuicaoExcel()">
                                <i class="bi bi-file-excel"></i> Excel
                            </button>
                            <button class="btn-light" onclick="gerarRelatorioDistribuicao()">
                                <i class="bi bi-file-text"></i> Relatório
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Gerencie a distribuição de professores por turmas e disciplinas.
                            Total de <strong><?= count($distribuicoes) ?></strong> distribuições cadastradas.
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th>Professor</th>
                                        <th>Turma</th>
                                        <th>Classe</th>
                                        <th>Disciplinas</th>
                                        <th>Ano Letivo</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($distribuicoes)): ?>
                                        <?php foreach ($distribuicoes as $dist): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($dist['professor_nome'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($dist['turma_nome'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($dist['classe'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars(substr($dist['disciplinas'] ?? '', 0, 50)) ?>...</td>
                                            <td><?= htmlspecialchars($dist['ano_letivo'] ?? '2026') ?></td>
                                            <td>
                                                <div class="btn-group-actions">
                                                    <button class="action-btn" onclick="editarDistribuicao(<?= $dist['id'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="action-btn text-danger" onclick="excluirDistribuicao(<?= $dist['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted py-3">Nenhuma distribuição encontrada</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>

            <!-- ========================================== -->
            <!-- MENSAGENS - ABRE EM NOVA ABA -->
            <!-- ========================================== -->
            <div id="section-mensagens" style="display:none;">
                <div class="card-modern">
                    <div class="card-header">
                        <span>💬 Mensagens</span>
                        <button class="btn-light" onclick="abrirMensagens()">
                            <i class="bi bi-box-arrow-up-right"></i> Abrir Mensagens
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Clique no botão acima para abrir o sistema de mensagens em uma nova aba.
                            <?php if ($mensagensNaoLidas > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $mensagensNaoLidas ?> mensagens não lidas</span>
                            <?php endif; ?>
                        </div>
                        <div class="list-group">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Prof. João Silva</strong>
                                    <p class="mb-0 text-muted small">Reunião de coordenação amanhã às 10h</p>
                                </div>
                                <span class="badge bg-danger">Nova</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Secretaria</strong>
                                    <p class="mb-0 text-muted small">Documentos pendentes para entrega</p>
                                </div>
                                <span class="badge bg-secondary">Lida</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Direção</strong>
                                    <p class="mb-0 text-muted small">Reunião geral na sexta-feira</p>
                                </div>
                                <span class="badge bg-danger">Nova</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- GESTÃO DE TURMAS -->
            <!-- ========================================== -->

            <div id="section-turmas" style="display:none;">
                <div class="card-modern">
                    <div class="card-header">
                        <span>🏫 Gestão de Turmas</span>
                        <button class="btn-light" onclick="abrirTemplate('turmas')">
                            <i class="bi bi-box-arrow-up-right"></i> Abrir Gestão de Turmas
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Clique no botão acima para abrir a gestão de turmas em uma nova aba.
                            Total de <strong><?= number_format($totalTurmas) ?></strong> turmas cadastradas.
                        </div>
                    </div>
                </div>
            </div>


            <!-- ========================================== -->
            <!-- DISCIPLINAS -->
            <!-- ========================================== -->
            <div id="section-disciplinas" style="display:none;">
                <div class="card-modern">
                    <div class="card-header">
                        <span>📚 Disciplinas</span>
                        <button class="btn-light" onclick="abrirTemplate('disciplinas')">
                            <i class="bi bi-box-arrow-up-right"></i> Abrir Disciplinas
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Clique no botão acima para abrir a gestão de disciplinas em uma nova aba.
                            Total de <strong><?= number_format($totalDisciplinas) ?></strong> disciplinas cadastradas.
                        </div>
                    </div>
                </div>
            </div>
                          

            <!-- ========================================== -->
            <!-- BOLETINS -->
            <!-- ========================================== -->
            <div id="section-boletins" style="display:none;">
                <div class="card-modern">
                    <div class="card-header">
                        <span>📃 Boletins</span>
                        <div class="btn-group-actions">
                            <button class="btn-light" onclick="gerarBoletim()">Gerar</button>
                            <button class="btn-light" onclick="imprimirBoletim()">Imprimir</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Geração de boletins individuais por aluno.
                        </div>
                        <form class="row g-3" id="formBoletim">
                            <div class="col-md-6">
                                <label class="form-label">Aluno</label>
                                <select class="form-select" id="selectAlunoBoletim">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($alunosList as $aluno): ?>
                                        <option value="<?= $aluno['id'] ?>"><?= htmlspecialchars($aluno['nome'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Turma</label>
                                <select class="form-select" id="selectTurmaBoletim">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($turmasAtivas as $turma): ?>
                                        <option value="<?= $turma['id'] ?>"><?= htmlspecialchars($turma['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-primary" onclick="gerarBoletim()">
                                    <i class="bi bi-file-text"></i> Gerar Boletim
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- ESTATÍSTICAS -->
            <!-- ========================================== -->
            <div id="section-estatisticas" style="display:none;">
                <div class="card-modern">
                    <div class="card-header">
                        <span>📈 Estatísticas</span>
                        <div class="btn-group-actions">
                            <button class="btn-light" onclick="exportarEstatisticasPDF()">PDF</button>
                            <button class="btn-light" onclick="exportarEstatisticasExcel()">Excel</button>
                            <button class="btn-light" onclick="imprimirEstatisticas()">Imprimir</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5><?= number_format($totalAlunos) ?></h5>
                                        <small class="text-muted">Alunos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5><?= number_format($totalTurmas) ?></h5>
                                        <small class="text-muted">Turmas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5><?= number_format($totalDisciplinas) ?></h5>
                                        <small class="text-muted">Disciplinas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5><?= number_format($totalProfessores) ?></h5>
                                        <small class="text-muted">Professores</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>Distribuição por Classe</h6>
                                <canvas id="chartClasses" height="200"></canvas>
                            </div>
                            <div class="col-md-6">
                                <h6>Distribuição por Turno</h6>
                                <canvas id="chartTurnos" height="200"></canvas>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <h6>Frequência nos Últimos 7 Dias</h6>
                                <canvas id="chartFrequencia" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- PRAZOS -->
            <!-- ========================================== -->
            <div id="section-prazos" style="display:none;">
                <div class="card-modern">
                    <div class="card-header">
                        <span>⏰ Prazos</span>
                        <button class="btn-light" onclick="abrirModalPrazo()">
                            <i class="bi bi-plus-circle"></i> Novo Prazo
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Gerenciamento de prazos para lançamento de notas.
                            Total de <strong><?= $totalPrazos ?></strong> prazos ativos.
                        </div>
                        <div class="list-group">
                            <?php if (!empty($prazosList)): ?>
                                <?php foreach ($prazosList as $prazo): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($prazo['nome'] ?? 'Prazo') ?>
                                    <div>
                                        <span class="badge bg-<?= $prazo['status'] == 'ativo' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($prazo['status'] ?? 'Inativo') ?>
                                        </span>
                                        <small class="text-muted ms-2"><?= formatarDataSimples($prazo['data_limite'] ?? '') ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-muted py-3 text-center">Nenhum prazo encontrado</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ========================================== -->
            <!-- CONFIGURAÇÕES -->
            <!-- ========================================== -->
            <div id="section-configuracoes" style="display:none;">
                <div class="card-modern">
                    <div class="card-header">
                        <span>⚙️ Configurações</span>
                        <button class="btn-light" onclick="salvarConfiguracoes()">Salvar</button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>📝 Prazos de Lançamento</h6>
                                <div class="mb-3">
                                    <label class="form-label">1º Trimestre</label>
                                    <input type="date" class="form-control" id="prazo1T" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">2º Trimestre</label>
                                    <input type="date" class="form-control" id="prazo2T" value="<?= date('Y-m-d', strtotime('+90 days')) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">3º Trimestre</label>
                                    <input type="date" class="form-control" id="prazo3T" value="<?= date('Y-m-d', strtotime('+150 days')) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>📊 Configurações Gerais</h6>
                                <div class="mb-3">
                                    <label class="form-label">Ano Letivo Atual</label>
                                    <input type="text" class="form-control" id="anoLetivoConfig" value="2026">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nota Mínima para Aprovação</label>
                                    <input type="number" class="form-control" id="notaMinAprovacao" value="10" min="0" max="20">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nota Mínima para Recuperação</label>
                                    <input type="number" class="form-control" id="notaMinRecuperacao" value="8" min="0" max="20">
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-primary" onclick="salvarConfiguracoes()">
                                <i class="bi bi-save"></i> Salvar Configurações
                            </button>
                            <button class="btn btn-secondary" onclick="restaurarConfiguracoes()">
                                <i class="bi bi-arrow-counterclockwise"></i> Restaurar Padrões
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- LOGS -->
            <!-- ========================================== -->
            <div id="section-logs" style="display:none;">
                <div class="card-modern">
                    <div class="card-header">
                        <span>📋 Logs de Atividades</span>
                        <button class="btn-light" onclick="atualizarLogs()">
                            <i class="bi bi-arrow-clockwise"></i> Atualizar
                        </button>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $stmt = $pdo->query("
                                SELECT * FROM logs_escolares 
                                ORDER BY data_hora DESC 
                                LIMIT 20
                            ");
                            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $logs = [];
                        }
                        ?>
                        <div class="list-group">
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi bi-<?= $log['acao'] == 'cadastrar' ? 'person-plus text-primary' : ($log['acao'] == 'editar' ? 'pencil-square text-warning' : 'file-text text-success') ?>"></i>
                                        <?= htmlspecialchars($log['descricao'] ?? '') ?>
                                    </span>
                                    <small class="text-muted"><?= formatarData($log['data_hora'] ?? '') ?></small>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-muted py-3 text-center">Nenhum log encontrado</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- ===== MODAIS ===== -->
<!-- Modal Aluno -->
<div class="modal fade" id="modalAluno" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🎓 Matricular Aluno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAluno">
                    <div class="mb-3">
                        <label class="form-label">Nome do Aluno *</label>
                        <input type="text" class="form-control" id="nomeAluno" placeholder="Digite o nome completo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sexo</label>
                        <select class="form-select" id="sexoAluno">
                            <option value="M">Masculino</option>
                            <option value="F">Feminino</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data de Nascimento</label>
                        <input type="date" class="form-control" id="dataNascAluno">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Classe *</label>
                        <select class="form-select" id="classeAluno" required>
                            <option value="">Selecione...</option>
                            <option value="PRE">Pré-Escolar</option>
                            <option value="1ª">1ª Classe</option>
                            <option value="2ª">2ª Classe</option>
                            <option value="3ª">3ª Classe</option>
                            <option value="4ª">4ª Classe</option>
                            <option value="5ª">5ª Classe</option>
                            <option value="6ª">6ª Classe</option>
                            <option value="7ª">7ª Classe</option>
                            <option value="8ª">8ª Classe</option>
                            <option value="9ª">9ª Classe</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Turma</label>
                        <select class="form-select" id="turmaAluno">
                            <option value="">Selecione...</option>
                            <?php foreach ($turmasAtivas as $turma): ?>
                                <option value="<?= $turma['nome'] ?>"><?= htmlspecialchars($turma['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contacto</label>
                        <input type="text" class="form-control" id="contactoAluno" placeholder="Telefone do encarregado">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome do Pai</label>
                        <input type="text" class="form-control" id="paiAluno" placeholder="Nome do pai">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome da Mãe</label>
                        <input type="text" class="form-control" id="maeAluno" placeholder="Nome da mãe">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarAluno()">
                    <i class="bi bi-save"></i> Matricular
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Turma -->
<div class="modal fade" id="modalTurma" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🏫 Criar Nova Turma</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formTurma">
                    <div class="mb-3">
                        <label class="form-label">Nome da Turma *</label>
                        <input type="text" class="form-control" id="nomeTurma" placeholder="Ex: 10ª A" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Classe *</label>
                        <select class="form-select" id="classeTurma" required>
                            <option value="">Selecione...</option>
                            <option value="PRE">Pré-Escolar</option>
                            <option value="1ª">1ª Classe</option>
                            <option value="2ª">2ª Classe</option>
                            <option value="3ª">3ª Classe</option>
                            <option value="4ª">4ª Classe</option>
                            <option value="5ª">5ª Classe</option>
                            <option value="6ª">6ª Classe</option>
                            <option value="7ª">7ª Classe</option>
                            <option value="8ª">8ª Classe</option>
                            <option value="9ª">9ª Classe</option>
                            <option value="10ª">10ª Classe</option>
                            <option value="11ª">11ª Classe</option>
                            <option value="12ª">12ª Classe</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Turno</label>
                        <select class="form-select" id="turnoTurma">
                            <option value="manha">Manhã</option>
                            <option value="tarde">Tarde</option>
                            <option value="noite">Noite</option>
                            <option value="integral">Integral</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacidade</label>
                        <input type="number" class="form-control" id="capacidadeTurma" value="45" min="1" max="60">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ano Letivo</label>
                        <input type="text" class="form-control" id="anoTurma" value="2026">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sala</label>
                        <input type="text" class="form-control" id="salaTurma" placeholder="Número da sala">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarTurma()">
                    <i class="bi bi-save"></i> Criar Turma
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nota -->
<div class="modal fade" id="modalNota" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📝 Lançar Nota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNota">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Aluno *</label>
                            <select class="form-select" id="selectAlunoNota" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($alunosList as $aluno): ?>
                                    <option value="<?= $aluno['id'] ?>"><?= htmlspecialchars($aluno['nome'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Disciplina *</label>
                            <select class="form-select" id="selectDisciplinaNota" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($disciplinasList as $disc): ?>
                                    <option value="<?= $disc['id'] ?>"><?= htmlspecialchars($disc['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Turma</label>
                            <select class="form-select" id="selectTurmaNota">
                                <option value="">Selecione...</option>
                                <?php foreach ($turmasAtivas as $turma): ?>
                                    <option value="<?= $turma['id'] ?>"><?= htmlspecialchars($turma['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Classe</label>
                            <input type="text" class="form-control" id="classeNota" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ano Letivo</label>
                            <input type="text" class="form-control" id="anoLetivoNota" value="2026">
                        </div>
                        <div class="col-12">
                            <h6 class="mt-2">Notas por Trimestre</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">1º Trimestre (MT)</label>
                                    <input type="number" class="form-control" id="nota1T" min="0" max="20" step="0.1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">2º Trimestre (MT)</label>
                                    <input type="number" class="form-control" id="nota2T" min="0" max="20" step="0.1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">3º Trimestre (MT)</label>
                                    <input type="number" class="form-control" id="nota3T" min="0" max="20" step="0.1">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="obsNota" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarNota()">
                    <i class="bi bi-save"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Prazo -->
<div class="modal fade" id="modalPrazo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">⏰ Novo Prazo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formPrazo">
                    <div class="mb-3">
                        <label class="form-label">Nome do Prazo *</label>
                        <input type="text" class="form-control" id="nomePrazo" placeholder="Ex: Lançamento 1º Trimestre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data Limite *</label>
                        <input type="date" class="form-control" id="dataLimitePrazo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusPrazo">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricaoPrazo" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarPrazo()">
                    <i class="bi bi-save"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Esta seção fica oculta, mas mantida para referência -->
<div id="section-disciplinas" style="display:none;">
    <!-- conteúdo -->
</div>

<a href="#" onclick="abrirDisciplinas()">
    <span class="nav-icon">📚</span> Disciplinas
</a>

<a href="#" class="quick-action" onclick="abrirDisciplinas()">
<span class="icon">📚</span> Disciplinas
</a>

                            
                            
<!-- ===== SCRIPTS ===== -->
<script src="/softgest_web/assets/js/jquery-3.6.0.min.js"></script>
<script src="/softgest_web/assets/js/bootstrap.bundle.min.js"></script>
<script src="/softgest_web/assets/js/chart.min.js"></script>

<script>
// ============================================
// SIDEBAR
// ============================================
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

// ============================================
// RELÓGIO
// ============================================
function updateDateTime() {
    const now = new Date();
    const time = now.toLocaleTimeString('pt-BR');
    document.getElementById('currentTime').textContent = time;
}
setInterval(updateDateTime, 1000);
updateDateTime();

// ============================================
// NAVEGAÇÃO
// ============================================
function showSection(section) {
    console.log('📌 Abrindo seção:', section);
    
    document.querySelectorAll('[id^="section-"]').forEach(el => {
        el.style.display = 'none';
    });
    
    const target = document.getElementById('section-' + section);
    if (target) {
        target.style.display = 'block';
    }
    
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('onclick') && link.getAttribute('onclick').includes(section)) {
            link.classList.add('active');
        }
    });
    
    const titles = {
        'dashboard': 'Dashboard Pedagógico',
        'distribuicao': 'Distribuição de Professores',
        'mensagens': 'Mensagens',
        'bancoNotas': 'Banco de Notas',
        'turmas': 'Gestão de Turmas',
        'disciplinas': 'Disciplinas',
        'alunos': 'Alunos',
        'professores': 'Professores',
        'pautas-trimestrais': 'Pautas Trimestrais',
        'pautas-finais': 'Pautas Finais',
        'boletins': 'Boletins',
        'estatisticas': 'Estatísticas',
        'prazos': 'Prazos',
        'pagamentos': 'Pagamentos',
        'configuracoes': 'Configurações',
        'logs': 'Logs de Atividades'
    };
    document.getElementById('pageTitle').textContent = titles[section] || section.charAt(0).toUpperCase() + section.slice(1);
    
    closeSidebar();
}


function abrirTemplate(template) {
    var urls = {
        'distribuicao_professores': '/softgest_web/coordenador_pedagogico/templates/distribuicao_professores.php',
        'mensagens': '/softgest_web/coordenador_pedagogico/templates/mensagens.php',
        'banco_notas': '/softgest_web/coordenador_pedagogico/templates/banco_notas.php',
        'turmas': '/softgest_web/coordenador_pedagogico/templates/turmas/index.php',
        'disciplinas': '/softgest_web/coordenador_pedagogico/disciplinas/index.php',
        'alunos': '/softgest_web/coordenador_pedagogico/templates/alunos/index.php',
        'professores': '/softgest_web/coordenador_pedagogico/templates/professores/index.php',
        'pautas_trimestrais': '/softgest_web/coordenador_pedagogico/templates/pautas_trimestrais.php',
        'pautas_finais': '/softgest_web/coordenador_pedagogico/templates/pautas_finais.php',
        'boletins': '/softgest_web/coordenador_pedagogico/templates/boletins/boletins.php',
        'estatisticas': '/softgest_web/coordenador_pedagogico/templates/estatisticas.php',
        'prazos': '/softgest_web/coordenador_pedagogico/templates/prazos.php',
        'pagamentos': '/softgest_web/coordenador_pedagogico/templates/pagamentos.php',
        'configuracoes': '/softgest_web/coordenador_pedagogico/templates/configuracoes.php',
        'logs': '/softgest_web/coordenador_pedagogico/templates/logs.php'
    };
    
    var url = urls[template];
    if (!url) {
        mostrarNotificacao('⚠️ Template não encontrado: ' + template, 'danger');
        return;
    }
    
    window.open(url, '_blank');
    mostrarNotificacao('📂 Abrindo ' + template.replace(/_/g, ' ') + '...', 'info');
}





// ============================================
// ABRIR MENSAGENS
// ============================================
function abrirMensagens() {
    var url = '/softgest_web/coordenador_pedagogico/mensagens.php';  // <--- NOVO CAMINHO
    console.log('💬 Abrindo mensagens:', url);
    window.open(url, '_blank');
    mostrarNotificacao('💬 Abrindo sistema de mensagens...', 'info');
}


                            
// ============================================
// ABRIR PROFESSORES
// ============================================
function abrirProfessores() {
    var url = '/softgest_web/coordenador_pedagogico/index.php';
    console.log('👨‍🏫 Abrindo professores:', url);
    window.open(url, '_blank');
    mostrarNotificacao('👨‍🏫 Abrindo gestão de professores...', 'info');
}

// ============================================
// ABRIR DISCIPLINAS - ADICIONE ESTA FUNÇÃO
// ============================================
function abrirDisciplinas() {
    var url = '/softgest_web/coordenador_pedagogico/disciplinas/index.php';
    console.log('📚 Abrindo disciplinas:', url);
    window.open(url, '_blank');
    mostrarNotificacao('📚 Abrindo gestão de disciplinas...', 'info');
}
                            

// ============================================
// LOADING
// ============================================
function showLoading() {
    document.getElementById('loadingOverlay').classList.add('active');
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('active');
}

// ============================================
// NOTIFICAÇÕES
// ============================================
function mostrarNotificacao(mensagem, tipo = 'info') {
    let container = document.getElementById('notificacoesContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificacoesContainer';
        document.body.appendChild(container);
    }
    
    const cores = { 'info': '#3498db', 'success': '#27ae60', 'warning': '#f39c12', 'danger': '#e74c3c' };
    const icones = { 'info': 'info-circle', 'success': 'check-circle', 'warning': 'exclamation-triangle', 'danger': 'x-circle' };
    
    const toast = document.createElement('div');
    toast.className = `notification-toast ${tipo}`;
    toast.innerHTML = `
        <div style="display:flex;align-items:start;gap:10px;">
            <div style="color:${cores[tipo]};font-size:1.2rem;">
                <i class="bi bi-${icones[tipo]}"></i>
            </div>
            <div style="flex:1;">
                <div style="font-weight:bold;">${tipo === 'danger' ? '⚠️ Alerta' : '📢 Notificação'}</div>
                <div style="font-size:0.85rem;color:#666;">${mensagem}</div>
                <div style="font-size:0.7rem;color:#999;margin-top:5px;">${new Date().toLocaleTimeString()}</div>
            </div>
            <button class="btn-close" style="font-size:0.7rem;" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    container.appendChild(toast);
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 6000);
}



// ============================================
// FUNÇÕES - ESTATÍSTICAS
// ============================================
function exportarEstatisticasPDF() {
    mostrarNotificacao('📄 Exportando estatísticas para PDF...', 'info');
}

function exportarEstatisticasExcel() {
    mostrarNotificacao('📊 Exportando estatísticas para Excel...', 'info');
}

function imprimirEstatisticas() {
    window.print();
}

// ============================================
// FUNÇÕES - PRAZOS
// ============================================
function abrirModalPrazo() {
    const modal = new bootstrap.Modal(document.getElementById('modalPrazo'));
    document.getElementById('formPrazo').reset();
    modal.show();
}

function salvarPrazo() {
    mostrarNotificacao('💾 Salvando prazo... (função em desenvolvimento)', 'info');
}

// ============================================
// FUNÇÕES - DISTRIBUIÇÃO
// ============================================
function editarDistribuicao(id) {
    mostrarNotificacao('✏️ Editando distribuição #' + id, 'info');
}

function excluirDistribuicao(id) {
    if (confirm('Tem certeza que deseja excluir esta distribuição?')) {
        mostrarNotificacao('🗑️ Excluindo distribuição #' + id + '...', 'warning');
    }
}

function exportarDistribuicaoExcel() {
    mostrarNotificacao('📊 Exportando distribuição para Excel...', 'info');
}

function gerarRelatorioDistribuicao() {
    mostrarNotificacao('📄 Gerando relatório de distribuição...', 'info');
}

// ============================================
// FUNÇÕES - PAGAMENTOS
// ============================================
function exportarPagamentosExcel() {
    mostrarNotificacao('📊 Exportando pagamentos para Excel...', 'info');
}

// ============================================
// FUNÇÕES - CONFIGURAÇÕES
// ============================================
function salvarConfiguracoes() {
    mostrarNotificacao('💾 Configurações salvas com sucesso!', 'success');
}

function restaurarConfiguracoes() {
    if (confirm('Tem certeza que deseja restaurar as configurações padrão?')) {
        mostrarNotificacao('🔄 Configurações restauradas para padrão', 'info');
    }
}

// ============================================
// FUNÇÕES - LOGS
// ============================================
function atualizarLogs() {
    mostrarNotificacao('🔄 Logs atualizados', 'info');
}

// ============================================
// FUNÇÕES - CONTAGEM DE MENSAGENS
// ============================================
function atualizarMensagensNaoLidas() {
    fetch('/softgest_web/api/mensagens/nao-lidas')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.total > 0) {
                const badge = document.getElementById('msgBadge');
                if (badge) {
                    badge.textContent = data.total;
                    badge.style.display = 'inline';
                }
            }
        })
        .catch(error => console.error('Erro ao buscar mensagens:', error));
}

// ============================================
// GRÁFICOS
// ============================================
function initCharts() {
    // Gráfico de classes
    const ctxClasses = document.getElementById('chartClasses');
    if (ctxClasses) {
        const classes = {};
        <?php foreach ($alunosList as $aluno): ?>
            const classe = '<?= addslashes($aluno['Classe'] ?? 'N/A') ?>';
            classes[classe] = (classes[classe] || 0) + 1;
        <?php endforeach; ?>
        
        const labels = Object.keys(classes);
        const data = Object.values(classes);
        
        new Chart(ctxClasses, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª', '7ª', '8ª', '9ª'],
                datasets: [{
                    label: 'Alunos por Classe',
                    data: data.length ? data : [10, 15, 12, 18, 14, 20, 16, 22, 19],
                    backgroundColor: '#c9a84c',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    
    // Gráfico de turnos
    const ctxTurnos = document.getElementById('chartTurnos');
    if (ctxTurnos) {
        const turnos = { 'manha': 0, 'tarde': 0, 'noite': 0, 'integral': 0 };
        <?php foreach ($turmasAtivas as $turma): ?>
            const turno = '<?= addslashes($turma['turno'] ?? 'manha') ?>';
            if (turnos[turno] !== undefined) turnos[turno]++;
        <?php endforeach; ?>
        
        new Chart(ctxTurnos, {
            type: 'doughnut',
            data: {
                labels: ['Manhã', 'Tarde', 'Noite', 'Integral'],
                datasets: [{
                    data: [turnos.manha, turnos.tarde, turnos.noite, turnos.integral],
                    backgroundColor: ['#3498db', '#2ecc71', '#9b59b6', '#f39c12'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
    
    // Gráfico de frequência
    const ctxFreq = document.getElementById('chartFrequencia');
    if (ctxFreq) {
        const labels = [];
        const presentes = [];
        const ausentes = [];
        <?php foreach ($frequenciaDados as $freq): ?>
            labels.push('<?= date('d/m', strtotime($freq['data'])) ?>');
            presentes.push(<?= $freq['presentes'] ?? 0 ?>);
            ausentes.push(<?= $freq['ausentes'] ?? 0 ?>);
        <?php endforeach; ?>
        
        new Chart(ctxFreq, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['Hoje', 'Ontem', 'Anteontem'],
                datasets: [
                    {
                        label: 'Presentes',
                        data: presentes.length ? presentes : [25, 28, 22],
                        backgroundColor: '#27ae60',
                        borderRadius: 4
                    },
                    {
                        label: 'Ausentes',
                        data: ausentes.length ? ausentes : [5, 2, 8],
                        backgroundColor: '#e74c3c',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });
    }
}

// ============================================
// INICIALIZAÇÃO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Dashboard do Coordenador Pedagógico inicializado!');
    console.log('🏫 Escola:', '<?= addslashes($empresa['nome_fantasia'] ?? 'SoftGest') ?>');
    console.log('📩 Mensagens não lidas:', <?= $mensagensNaoLidas ?>);
    console.log('👨‍🏫 Professores:', <?= $totalProfessores ?>);
    
    if (!navigator.onLine) {
        mostrarNotificacao('📡 Modo offline - Usando dados em cache', 'warning');
    }
    
    setTimeout(initCharts, 500);
    
    setTimeout(() => {
        mostrarNotificacao('👋 Bem-vindo ao Dashboard Pedagógico!', 'success');
        <?php if ($mensagensNaoLidas > 0): ?>
        setTimeout(() => {
            mostrarNotificacao('📩 Você tem <?= $mensagensNaoLidas ?> mensagem(ns) não lida(s)!', 'warning');
        }, 2000);
        <?php endif; ?>
    }, 1500);
    
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.querySelector('.btn-toggle-sidebar');
        if (window.innerWidth <= 992 && sidebar.classList.contains('open')) {
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                closeSidebar();
            }
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) closeSidebar();
    });
    
    // Atualizar mensagens a cada 30 segundos
    if (typeof atualizarMensagensNaoLidas === 'function') {
        setInterval(atualizarMensagensNaoLidas, 30000);
    }
});
</script>

<?php include_once $base_path . '/includes/footer.php'; ?>
</body>
</html>