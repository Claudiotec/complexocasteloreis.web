<?php
// ============================================
// modules/escola/professor_dashboard.php - Painel do Professor
// ============================================

require_once '../../config/database.php';
require_once '../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

// ===== BUSCAR DADOS DO PROFESSOR DA SESSÃO =====
$professor_id = $_SESSION['professor_id'] ?? 0;
$professor_nome = $_SESSION['professor_nome'] ?? 'Professor';
$turma_id = $_SESSION['professor_turma_id'] ?? 0;
$turma_nome = $_SESSION['professor_turma_nome'] ?? '';
$classe = $_SESSION['professor_classe'] ?? '';
$disciplinas = $_SESSION['professor_disciplinas'] ?? '';

// Se não tiver dados na sessão, buscar do banco
if ($professor_id == 0) {
    try {
        $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            $stmt = $pdo->prepare("
                SELECT * FROM distribuicao_professores 
                WHERE professor_nome = ?
                LIMIT 1
            ");
            $stmt->execute([$usuario['nome']]);
            $prof = $stmt->fetch();
            
            if ($prof) {
                $professor_id = $prof['id'];
                $professor_nome = $prof['professor_nome'];
                $turma_id = $prof['turma_id'];
                $turma_nome = $prof['turma_nome'];
                $classe = $prof['classe'];
                $disciplinas = $prof['disciplinas'];
                
                $_SESSION['professor_id'] = $professor_id;
                $_SESSION['professor_nome'] = $professor_nome;
                $_SESSION['professor_dados'] = $prof;
                $_SESSION['professor_turma_id'] = $turma_id;
                $_SESSION['professor_turma_nome'] = $turma_nome;
                $_SESSION['professor_classe'] = $classe;
                $_SESSION['professor_disciplinas'] = $disciplinas;
                $_SESSION['is_professor'] = true;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar professor: " . $e->getMessage());
    }
}

// Se ainda não encontrou, redirecionar
if ($professor_id == 0) {
    header('Location: ../../index.php');
    exit;
}

// ===== BUSCAR DADOS DO PROFESSOR =====
$professor = $_SESSION['professor_dados'] ?? null;
$turmas = [];
$horarios = [];
$alunos_por_turma = [];
$total_alunos = 0;
$total_presentes = 0;
$total_ausentes = 0;

try {
    // Buscar turmas do professor
    if ($turma_id > 0) {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   (SELECT COUNT(*) FROM alunos a 
                    JOIN matriculas m ON a.id = m.aluno_id 
                    WHERE m.turma_id = t.id AND m.status = 'ativa') as total_alunos
            FROM turmas t
            WHERE t.id = ? AND t.status = 'ativa'
        ");
        $stmt->execute([$turma_id]);
        $turmas = $stmt->fetchAll();
    }
    
    // Se não encontrou turmas, buscar todas
    if (empty($turmas)) {
        $stmt = $pdo->query("
            SELECT t.*, 
                   (SELECT COUNT(*) FROM alunos a 
                    JOIN matriculas m ON a.id = m.aluno_id 
                    WHERE m.turma_id = t.id AND m.status = 'ativa') as total_alunos
            FROM turmas t
            WHERE t.status = 'ativa'
            ORDER BY t.nome
            LIMIT 10
        ");
        $turmas = $stmt->fetchAll();
    }
    
    // Buscar alunos por turma
    foreach ($turmas as $turma) {
        $stmt = $pdo->prepare("
            SELECT a.id, a.nome, a.Classe, a.Curso, a.Contacto_do_Aluno,
                   m.data_matricula, m.status as matricula_status
            FROM alunos a
            JOIN matriculas m ON a.id = m.aluno_id
            WHERE m.turma_id = ? AND m.status = 'ativa'
            ORDER BY a.nome
        ");
        $stmt->execute([$turma['id']]);
        $alunos_por_turma[$turma['id']] = $stmt->fetchAll();
        $total_alunos += count($alunos_por_turma[$turma['id']]);
    }
    
    // Buscar horários do professor
    $stmt = $pdo->prepare("
        SELECT h.*, t.nome as turma_nome, t.classe, t.curso
        FROM horarios h
        JOIN turmas t ON h.turma_id = t.id
        WHERE h.professor_id = ? 
        ORDER BY FIELD(h.dia_semana, 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'), h.hora_inicio
    ");
    $stmt->execute([$professor_id]);
    $horarios = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Erro no professor_dashboard: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Professor - SoftGest Escola</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ============================================
           RESET E ESTILOS GERAIS
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }
        
        /* ============================================
           MENU LATERAL PROFISSIONAL
           ============================================ */
        .sidebar-professor {
            width: 280px;
            background: linear-gradient(180deg, #0f1724 0%, #1a2332 50%, #0f1724 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            box-shadow: 4px 0 30px rgba(0,0,0,0.3);
            border-right: 1px solid rgba(255,255,255,0.05);
        }
        
        .sidebar-professor::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-professor::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.02);
        }
        .sidebar-professor::-webkit-scrollbar-thumb {
            background: rgba(201, 168, 76, 0.3);
            border-radius: 4px;
        }
        
        .sidebar-brand {
            padding: 25px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .sidebar-brand .logo-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            color: #1a2332;
            flex-shrink: 0;
        }
        
        .sidebar-brand .brand-text h2 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            color: #fff;
        }
        
        .sidebar-brand .brand-text span {
            font-size: 11px;
            color: #f5d76e;
            font-weight: 300;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
        }
        
        .sidebar-profile {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .sidebar-profile .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #1a2332;
            flex-shrink: 0;
            font-weight: 700;
        }
        
        .sidebar-profile .info {
            flex: 1;
            min-width: 0;
        }
        
        .sidebar-profile .info .name {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar-profile .info .role {
            font-size: 12px;
            color: #f5d76e;
            font-weight: 400;
            opacity: 0.8;
        }
        
        .sidebar-profile .info .turma {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 2px;
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 15px 12px;
        }
        
        .sidebar-nav .nav-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.3);
            padding: 10px 12px 8px;
            font-weight: 600;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 11px 16px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin-bottom: 3px;
            gap: 14px;
            position: relative;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.08);
            color: #fff;
            transform: translateX(5px);
        }
        
        .sidebar-nav a.active {
            background: rgba(201, 168, 76, 0.15);
            color: #f5d76e;
            box-shadow: inset 3px 0 0 #c9a84c;
        }
        
        .sidebar-nav a .icon {
            font-size: 18px;
            width: 28px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .sidebar-nav a .text {
            flex: 1;
        }
        
        .sidebar-nav a .badge {
            background: rgba(201, 168, 76, 0.2);
            color: #f5d76e;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            min-width: 20px;
            text-align: center;
        }
        
        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        
        .btn-sair {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 10px;
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-sair:hover {
            background: rgba(231, 76, 60, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(231, 76, 60, 0.15);
        }
        
        .btn-sair i {
            font-size: 16px;
        }
        
        .sidebar-footer .version {
            text-align: center;
            margin-top: 10px;
            font-size: 10px;
            color: rgba(255,255,255,0.2);
            letter-spacing: 1px;
        }
        
        /* ============================================
           CONTEÚDO PRINCIPAL
           ============================================ */
        .main-content {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
            background: #f0f2f5;
        }
        
        .top-bar {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 20px rgba(0,0,0,0.04);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 2px solid rgba(201, 168, 76, 0.2);
        }
        
        .top-bar .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #1a2332;
            padding: 5px 10px;
            border-radius: 6px;
        }
        
        .top-bar .menu-toggle:hover {
            background: #f0f2f5;
        }
        
        .top-bar .page-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .top-bar .page-title span {
            color: #c9a84c;
        }
        
        .top-bar .right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .top-bar .right .date {
            font-size: 14px;
            color: #94a3b8;
        }
        
        .top-bar .right .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            padding: 6px 16px 6px 12px;
            border-radius: 30px;
            border: 1px solid #eef2f7;
        }
        
        .top-bar .right .user-badge .avatar-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #1a2332;
            font-weight: 700;
        }
        
        .top-bar .right .user-badge .name {
            font-size: 14px;
            font-weight: 600;
            color: #1a2332;
        }
        
        .top-bar .right .user-badge .role {
            font-size: 11px;
            color: #c9a84c;
            font-weight: 500;
        }
        
        /* ============================================
           CONTEÚDO
           ============================================ */
        .content {
            padding: 25px 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #1a2332;
            margin: 0;
        }
        
        .page-header p {
            color: #94a3b8;
            font-size: 15px;
            margin: 5px 0 0;
        }
        
        /* ============================================
           ANIMAÇÃO DOS CARDS
           ============================================ */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .animate-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        
        .animate-card:nth-child(1) { animation-delay: 0.1s; }
        .animate-card:nth-child(2) { animation-delay: 0.2s; }
        .animate-card:nth-child(3) { animation-delay: 0.3s; }
        .animate-card:nth-child(4) { animation-delay: 0.4s; }
        
        .stat-card {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        
        .stat-card .icon {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #c9a84c;
        }
        
        .stat-card .label {
            font-size: 14px;
            color: #94a3b8;
            margin-top: 5px;
        }
        
        /* ============================================
           LEGENDA INTERATIVA
           ============================================ */
        .legend-tooltip {
            position: fixed;
            bottom: 30px;
            right: 30px;
            max-width: 350px;
            background: rgba(15, 23, 36, 0.95);
            color: white;
            padding: 20px 25px;
            border-radius: 16px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            z-index: 9999;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(201, 168, 76, 0.2);
            transition: all 0.5s ease;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .legend-tooltip .header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .legend-tooltip .header .icon {
            font-size: 20px;
            color: #f5d76e;
        }
        
        .legend-tooltip .header h4 {
            font-size: 14px;
            font-weight: 600;
            color: #f5d76e;
            margin: 0;
        }
        
        .legend-tooltip .content {
            font-size: 13px;
            line-height: 1.6;
            color: rgba(255,255,255,0.8);
        }
        
        .legend-tooltip .content strong {
            color: #f5d76e;
        }
        
        .legend-tooltip .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            color: rgba(255,255,255,0.4);
            font-size: 18px;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .legend-tooltip .close-btn:hover {
            color: #fff;
        }
        
        .legend-tooltip .steps {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .legend-tooltip .steps .step {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 0;
            font-size: 12px;
            color: rgba(255,255,255,0.6);
        }
        
        .legend-tooltip .steps .step .num {
            width: 20px;
            height: 20px;
            background: rgba(201, 168, 76, 0.2);
            color: #f5d76e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        /* ============================================
           GRÁFICOS
           ============================================ */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            animation: fadeInUp 0.8s ease forwards;
            opacity: 0;
        }
        
        .chart-card:nth-child(1) { animation-delay: 0.3s; }
        .chart-card:nth-child(2) { animation-delay: 0.5s; }
        
        .chart-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        
        .chart-card .chart-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a2332;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-card .chart-title .icon {
            font-size: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 250px;
        }
        
        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }
        
        /* ============================================
           INFO PROFESSOR
           ============================================ */
        .info-professor {
            background: white;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
            animation-delay: 0.1s;
        }
        
        .info-professor .row {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .info-professor .row .item {
            flex: 1;
            min-width: 120px;
        }
        
        .info-professor .row .item .label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .info-professor .row .item .value {
            font-size: 16px;
            font-weight: 600;
            color: #1a2332;
            margin-top: 3px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
            margin: 30px 0 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title .badge-count {
            background: #c9a84c;
            color: #1a2332;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card-turma {
            background: white;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        
        .card-turma:nth-child(1) { animation-delay: 0.4s; }
        .card-turma:nth-child(2) { animation-delay: 0.6s; }
        .card-turma:nth-child(3) { animation-delay: 0.8s; }
        
        .card-turma:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        
        .card-turma .header {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            padding: 15px 20px;
            color: #1a2332;
        }
        
        .card-turma .header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }
        
        .card-turma .header .sub {
            font-size: 13px;
            opacity: 0.8;
            margin-top: 3px;
        }
        
        .card-turma .body {
            padding: 20px;
        }
        
        .card-turma .body .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .card-turma .body .info-row:last-child {
            border-bottom: none;
        }
        
        .card-turma .body .info-row .label {
            color: #94a3b8;
            font-size: 13px;
        }
        
        .card-turma .body .info-row .value {
            font-weight: 600;
            color: #1a2332;
        }
        
        .card-turma .body .alunos-list {
            margin-top: 15px;
        }
        
        .card-turma .body .alunos-list .title {
            font-weight: 600;
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 5px;
        }
        
        .card-turma .body .alunos-list .aluno-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f8fafc;
            font-size: 14px;
        }
        
        .card-turma .body .alunos-list .aluno-item:last-child {
            border-bottom: none;
        }
        
        .card-turma .body .alunos-list .aluno-item .nome {
            color: #1a2332;
        }
        
        .card-turma .body .alunos-list .aluno-item .contato {
            color: #94a3b8;
            font-size: 12px;
        }
        
        .card-turma .footer {
            padding: 12px 20px;
            background: #f8fafc;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            border-top: 1px solid #eef2f7;
        }
        
        .btn {
            padding: 6px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #c9a84c;
            color: #1a2332;
        }
        
        .btn-primary:hover {
            background: #b8973a;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201,168,76,0.3);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: #3b82f6;
            color: white;
        }
        
        .btn-info:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #eef2f7;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .horarios-table {
            background: white;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            animation: fadeInUp 0.7s ease forwards;
            opacity: 0;
        }
        
        .horarios-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .horarios-table th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            border-bottom: 2px solid #eef2f7;
        }
        
        .horarios-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        
        .horarios-table tr:hover td {
            background: #fafafa;
        }
        
        .horarios-table .dia {
            font-weight: 600;
            color: #1a2332;
        }
        
        .horarios-table .horario {
            color: #c9a84c;
            font-weight: 600;
        }
        
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
        
        .sidebar-overlay.active {
            display: block !important;
        }
        
        /* ============================================
           RESPONSIVIDADE
           ============================================ */
        @media (max-width: 992px) {
            .sidebar-professor {
                transform: translateX(-100%);
            }
            
            .sidebar-professor.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .top-bar .menu-toggle {
                display: block;
            }
            
            .top-bar {
                padding: 12px 20px;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .info-professor .row {
                flex-direction: column;
                gap: 10px;
            }
            
            .top-bar .right .user-badge .name {
                font-size: 12px;
            }
            
            .top-bar .right .user-badge .role {
                display: none;
            }
            
            .page-header h1 {
                font-size: 22px;
            }
            
            .legend-tooltip {
                bottom: 15px;
                right: 15px;
                left: 15px;
                max-width: none;
                padding: 15px 20px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .top-bar {
                padding: 10px 15px;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .top-bar .right {
                width: 100%;
                justify-content: space-between;
            }
            
            .horarios-table {
                overflow-x: auto;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- ============================================
     MENU LATERAL
     ============================================ -->
<aside class="sidebar-professor" id="sidebarProfessor">
    <div class="sidebar-brand">
        <div class="logo-icon">🎓</div>
        <div class="brand-text">
            <h2>SoftGest</h2>
            <span>Escola • Professor</span>
        </div>
    </div>
    
    <div class="sidebar-profile">
        <div class="avatar"><?= strtoupper(substr($professor_nome, 0, 2)) ?></div>
        <div class="info">
            <div class="name"><?= htmlspecialchars($professor_nome) ?></div>
            <div class="role">👨‍🏫 Professor</div>
            <div class="turma"><?= htmlspecialchars($turma_nome) ?> • <?= htmlspecialchars($classe) ?></div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-label">📋 Menu Principal</div>
        
        <a href="professor_dashboard.php" class="active" data-legend="Aqui você visualiza o resumo das suas atividades diárias, incluindo estatísticas de alunos, turmas e frequência.">
            <span class="icon">📊</span>
            <span class="text">Dashboard</span>
        </a>
        
        <a href="#" data-legend="Visualize a lista completa dos seus alunos, com opções para filtrar por turma, ver detalhes e entrar em contato.">
            <span class="icon">👨‍🎓</span>
            <span class="text">Meus Alunos</span>
            <span class="badge"><?= $total_alunos ?></span>
        </a>
        
        <a href="#" data-legend="Gerencie suas turmas: veja detalhes, alunos matriculados, horários e atividades programadas.">
            <span class="icon">🏫</span>
            <span class="text">Minhas Turmas</span>
            <span class="badge"><?= count($turmas) ?></span>
        </a>
        
        <div class="nav-label" style="margin-top: 15px;">📝 Atividades</div>
        
        <a href="#" data-legend="Registre a presença dos alunos diariamente. Visualize o histórico de frequência e gere relatórios.">
            <span class="icon">✅</span>
            <span class="text">Frequência</span>
        </a>
        
        <a href="#" data-legend="Lançe e gerencie as notas dos alunos. Acompanhe o desempenho individual e da turma.">
            <span class="icon">📊</span>
            <span class="text">Notas</span>
        </a>
        
        <a href="#" data-legend="Consulte e gerencie seus horários de aula. Visualize sua grade semanal completa.">
            <span class="icon">🕐</span>
            <span class="text">Horários</span>
            <span class="badge"><?= count($horarios) ?></span>
        </a>
        
        <div class="nav-label" style="margin-top: 15px;">📖 Recursos</div>
        
        <a href="#" data-legend="Acesse e compartilhe materiais didáticos com seus alunos. Organize por disciplina e turma.">
            <span class="icon">📚</span>
            <span class="text">Material Didático</span>
        </a>
        
        <a href="#" data-legend="Crie e gerencie seus planos de aula. Organize por disciplina, turma e período letivo.">
            <span class="icon">📋</span>
            <span class="text">Plano de Aula</span>
        </a>
        
        <a href="#" data-legend="Gere relatórios detalhados de desempenho, frequência e evolução dos alunos.">
            <span class="icon">📈</span>
            <span class="text">Relatórios</span>
        </a>
        
        <a href="#" data-legend="Configure suas preferências, notificações e informações pessoais do professor.">
            <span class="icon">⚙️</span>
            <span class="text">Configurações</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../../logout.php" class="btn-sair">
            <i class="fas fa-sign-out-alt"></i>
            Sair do Sistema
        </a>
        <div class="version">v1.0 • <?= date('Y') ?></div>
    </div>
</aside>

<!-- Overlay Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ============================================
     LEGENDA INTERATIVA
     ============================================ -->
<div class="legend-tooltip" id="legendTooltip">
    <button class="close-btn" onclick="closeLegend()">✕</button>
    <div class="header">
        <span class="icon">💡</span>
        <h4>Dica Rápida</h4>
    </div>
    <div class="content" id="legendContent">
        Clique em qualquer item do menu para ver a explicação da sua função.
    </div>
    <div class="steps">
        <div class="step">
            <span class="num">1</span>
            <span>Navegue pelo menu lateral</span>
        </div>
        <div class="step">
            <span class="num">2</span>
            <span>Clique em qualquer opção</span>
        </div>
        <div class="step">
            <span class="num">3</span>
            <span>Veja a explicação da função</span>
        </div>
    </div>
</div>

<!-- ============================================
     CONTEÚDO PRINCIPAL
     ============================================ -->
<main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <div>
            <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
            <span class="page-title">📊 <span>Dashboard</span> do Professor</span>
        </div>
        <div class="right">
            <span class="date"><?= date('d/m/Y H:i') ?></span>
            <div class="user-badge">
                <div class="avatar-small"><?= strtoupper(substr($professor_nome, 0, 2)) ?></div>
                <span class="name"><?= htmlspecialchars($professor_nome) ?></span>
                <span class="role">Professor</span>
            </div>
        </div>
    </div>
    
    <!-- Conteúdo -->
    <div class="content">
        <!-- Page Header -->
        <div class="page-header animate-card">
            <h1>👨‍🏫 Painel do Professor</h1>
            <p>Bem-vindo(a), <?= htmlspecialchars($professor_nome) ?>! Aqui está o resumo das suas atividades.</p>
        </div>
        
        <!-- Informações do Professor -->
        <?php if ($professor): ?>
        <div class="info-professor">
            <div class="row">
                <div class="item">
                    <div class="label">ID</div>
                    <div class="value"><?= $professor['id'] ?? 'N/A' ?></div>
                </div>
                <div class="item">
                    <div class="label">Turma</div>
                    <div class="value"><?= htmlspecialchars($professor['turma_nome'] ?? 'N/A') ?></div>
                </div>
                <div class="item">
                    <div class="label">Classe</div>
                    <div class="value"><?= htmlspecialchars($professor['classe'] ?? 'N/A') ?></div>
                </div>
                <div class="item">
                    <div class="label">Disciplinas</div>
                    <div class="value"><?= htmlspecialchars($professor['disciplinas'] ?? 'N/A') ?></div>
                </div>
                <div class="item">
                    <div class="label">Ano Letivo</div>
                    <div class="value"><?= htmlspecialchars($professor['ano_letivo'] ?? 'N/A') ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card animate-card">
                <span class="icon">📚</span>
                <div class="number"><?= count($turmas) ?></div>
                <div class="label">Turmas</div>
            </div>
            <div class="stat-card animate-card">
                <span class="icon">👨‍🎓</span>
                <div class="number"><?= $total_alunos ?></div>
                <div class="label">Alunos</div>
            </div>
            <div class="stat-card animate-card">
                <span class="icon">🕐</span>
                <div class="number"><?= count($horarios) ?></div>
                <div class="label">Horários</div>
            </div>
            <div class="stat-card animate-card">
                <span class="icon">📊</span>
                <div class="number"><?= count($turmas) > 0 ? round($total_alunos / count($turmas), 1) : 0 ?></div>
                <div class="label">Média por Turma</div>
            </div>
        </div>
        
        <!-- ============================================
             GRÁFICOS
             ============================================ -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">
                    <span class="icon">📊</span>
                    Distribuição de Alunos por Turma
                </div>
                <div class="chart-container">
                    <canvas id="chartTurmas"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">
                    <span class="icon">✅</span>
                    Frequência dos Alunos
                </div>
                <div class="chart-container">
                    <canvas id="chartFrequencia"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Turmas -->
        <div class="section-title">
            📚 Minhas Turmas
            <span class="badge-count"><?= count($turmas) ?></span>
        </div>
        
        <?php if (count($turmas) > 0): ?>
        <div class="cards-grid">
            <?php foreach ($turmas as $turma): ?>
            <div class="card-turma">
                <div class="header">
                    <h3><?= htmlspecialchars($turma['nome'] ?? 'Turma ' . $turma['id']) ?></h3>
                    <div class="sub"><?= htmlspecialchars($turma['classe'] ?? 'N/A') ?> • <?= htmlspecialchars($turma['curso'] ?? 'N/A') ?> • <?= htmlspecialchars($turma['turno'] ?? 'Manhã') ?></div>
                </div>
                <div class="body">
                    <div class="info-row">
                        <span class="label">Sala</span>
                        <span class="value"><?= htmlspecialchars($turma['sala'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Limite</span>
                        <span class="value"><?= htmlspecialchars($turma['limite'] ?? 'Ilimitado') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Total Alunos</span>
                        <span class="value"><?= isset($alunos_por_turma[$turma['id']]) ? count($alunos_por_turma[$turma['id']]) : 0 ?></span>
                    </div>
                    
                    <?php if (isset($alunos_por_turma[$turma['id']]) && count($alunos_por_turma[$turma['id']]) > 0): ?>
                    <div class="alunos-list">
                        <div class="title">👨‍🎓 Alunos:</div>
                        <?php foreach (array_slice($alunos_por_turma[$turma['id']], 0, 5) as $aluno): ?>
                        <div class="aluno-item">
                            <span class="nome"><?= htmlspecialchars($aluno['nome']) ?></span>
                            <span class="contato">📱 <?= htmlspecialchars($aluno['Contacto_do_Aluno'] ?? '') ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($alunos_por_turma[$turma['id']]) > 5): ?>
                        <div style="text-align: center; color: #94a3b8; font-size: 12px; padding-top: 5px;">
                            + <?= count($alunos_por_turma[$turma['id']]) - 5 ?> alunos
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="footer">
                    <a href="turma_detalhes.php?id=<?= $turma['id'] ?>" class="btn btn-primary">📋 Ver Turma</a>
                    <a href="chamada.php?turma_id=<?= $turma['id'] ?>" class="btn btn-success">✅ Chamada</a>
                    <a href="notas.php?turma_id=<?= $turma['id'] ?>" class="btn btn-info">📊 Notas</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="background: #fef9e7; border-radius: 12px; padding: 40px; text-align: center; border: 2px solid #f39c12;">
            <span style="font-size: 48px; display: block; margin-bottom: 10px;">📭</span>
            <h3 style="color: #f39c12;">Nenhuma turma atribuída</h3>
            <p style="color: #4a5568; margin-top: 10px;">Você ainda não foi atribuído a nenhuma turma.</p>
        </div>
        <?php endif; ?>
        
        <!-- Horários -->
        <div class="section-title" style="margin-top: 30px;">
            🕐 Meus Horários
            <span class="badge-count"><?= count($horarios) ?></span>
        </div>
        
        <?php if (count($horarios) > 0): ?>
        <div class="horarios-table">
            <table>
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Horário</th>
                        <th>Turma</th>
                        <th>Classe</th>
                        <th>Curso</th>
                        <th>Disciplina</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($horarios as $horario): ?>
                    <tr>
                        <td class="dia"><?= htmlspecialchars($horario['dia_semana']) ?></td>
                        <td class="horario"><?= date('H:i', strtotime($horario['hora_inicio'])) ?> - <?= date('H:i', strtotime($horario['hora_fim'])) ?></td>
                        <td><?= htmlspecialchars($horario['turma_nome']) ?></td>
                        <td><?= htmlspecialchars($horario['classe']) ?></td>
                        <td><?= htmlspecialchars($horario['curso']) ?></td>
                        <td><?= htmlspecialchars($horario['disciplina'] ?? 'N/A') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="background: #f8fafc; border-radius: 12px; padding: 30px; text-align: center; border: 1px solid #eef2f7;">
            <p style="color: #94a3b8;">Nenhum horário cadastrado.</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
    // ============================================
    // FUNÇÕES DO MENU
    // ============================================
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebarProfessor');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebarProfessor');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // ============================================
    // LEGENDA INTERATIVA
    // ============================================
    function closeLegend() {
        const legend = document.getElementById('legendTooltip');
        legend.style.opacity = '0';
        legend.style.transform = 'translateY(30px)';
        setTimeout(() => {
            legend.style.display = 'none';
        }, 500);
    }

    // Mostrar legenda ao passar o mouse nos itens do menu
    document.querySelectorAll('.sidebar-nav a').forEach(item => {
        item.addEventListener('mouseenter', function(e) {
            const legend = document.getElementById('legendTooltip');
            const content = document.getElementById('legendContent');
            const msg = this.getAttribute('data-legend');
            if (msg) {
                content.textContent = msg;
                legend.style.display = 'block';
                legend.style.opacity = '1';
                legend.style.transform = 'translateY(0)';
            }
        });
        
        item.addEventListener('click', function(e) {
            const legend = document.getElementById('legendTooltip');
            const content = document.getElementById('legendContent');
            const msg = this.getAttribute('data-legend');
            if (msg) {
                content.textContent = msg;
                legend.style.display = 'block';
                legend.style.opacity = '1';
                legend.style.transform = 'translateY(0)';
            }
        });
    });

    // ============================================
    // GRÁFICOS
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Dados para os gráficos
        const turmasNomes = <?= json_encode(array_map(function($t) { return $t['nome'] ?? 'Turma ' . $t['id']; }, $turmas)) ?>;
        const turmasAlunos = <?= json_encode(array_map(function($t) use ($alunos_por_turma) { 
            return isset($alunos_por_turma[$t['id']]) ? count($alunos_por_turma[$t['id']]) : 0; 
        }, $turmas)) ?>;
        
        // Cores para os gráficos
        const cores = ['#c9a84c', '#f5d76e', '#10b981', '#3b82f6', '#e74c3c', '#8b5cf6', '#f59e0b'];
        
        // Gráfico 1: Distribuição de Alunos por Turma
        const ctx1 = document.getElementById('chartTurmas').getContext('2d');
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: turmasNomes,
                datasets: [{
                    data: turmasAlunos,
                    backgroundColor: cores.slice(0, turmasNomes.length),
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { size: 12 },
                            padding: 15
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 2000
                }
            }
        });
        
        // Gráfico 2: Frequência dos Alunos
        const ctx2 = document.getElementById('chartFrequencia').getContext('2d');
        
        // Dados simulados de frequência (pode ser substituído por dados reais)
        const presencas = Math.round(Math.random() * 50 + 30);
        const ausencias = Math.round(Math.random() * 10 + 5);
        const justificados = Math.round(Math.random() * 5 + 2);
        
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['Presentes', 'Ausentes', 'Justificados'],
                datasets: [{
                    label: 'Frequência',
                    data: [presencas, ausencias, justificados],
                    backgroundColor: ['#10b981', '#e74c3c', '#f59e0b'],
                    borderColor: '#fff',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeOutBounce'
                }
            }
        });
    });
</script>

</body>
</html>