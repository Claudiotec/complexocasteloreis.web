<?php
// ============================================
// header_escola.php - Header do Módulo Escola
// ============================================

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão do módulo Escola
if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ============================================
// INCLUIR FUNÇÕES DE MENSAGENS
// ============================================
$mensagens_nao_lidas = 0;
$mensagens_functions_path = __DIR__ . '/mensagens_functions.php';
if (file_exists($mensagens_functions_path)) {
    require_once $mensagens_functions_path;
    if (isset($_SESSION['usuario_id'])) {
        $mensagens_nao_lidas = getTotalMensagensNaoLidas($_SESSION['usuario_id']);
    }
}

// ============================================
// DADOS DO MÓDULO ESCOLA
// ============================================
$totalAlunos = 0;
$totalProfessores = 0;
$totalTurmas = 0;
$totalMatriculas = 0;
$totalDisciplinas = 0;

try {
    $totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos")->fetchColumn() ?? 0;
} catch (Exception $e) {}
try {
    $totalProfessores = $pdo->query("SELECT COUNT(*) FROM professores")->fetchColumn() ?? 0;
} catch (Exception $e) {}
try {
    $totalTurmas = $pdo->query("SELECT COUNT(*) FROM turmas")->fetchColumn() ?? 0;
} catch (Exception $e) {}
try {
    $totalMatriculas = $pdo->query("SELECT COUNT(*) FROM matriculas WHERE status = 'ativa'")->fetchColumn() ?? 0;
} catch (Exception $e) {}
try {
    $totalDisciplinas = $pdo->query("SELECT COUNT(*) FROM disciplinas")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// ============================================
// DADOS DA PÁGINA ATUAL
// ============================================
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// ============================================
// DADOS DA EMPRESA
// ============================================
if (!isset($empresaData)) {
    try {
        $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
        $empresaData = $stmt->fetch();
        if (!$empresaData) {
            $empresaData = [
                'nome_fantasia' => 'SoftGest',
                'razao_social' => 'SoftGest Sistemas Ltda',
                'logo' => null
            ];
        }
    } catch(PDOException $e) {
        $empresaData = [
            'nome_fantasia' => 'SoftGest',
            'razao_social' => 'SoftGest Sistemas Ltda',
            'logo' => null
        ];
    }
}

$nomeEmpresa = $empresaData['nome_fantasia'] ?? $empresaData['razao_social'] ?? 'SoftGest';
$logoEmpresa = $empresaData['logo'] ?? null;

// ============================================
// GERAR TOKEN DE NOTIFICAÇÃO
// ============================================
$token_notificacao = null;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'dispositivos_conectados'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT token FROM dispositivos_conectados 
            WHERE usuario_id = ? AND ativo = 1 
            ORDER BY ultima_atividade DESC LIMIT 1
        ");
        $stmt->execute([$_SESSION['usuario_id']]);
        $result = $stmt->fetch();
        
        if ($result) {
            $token_notificacao = $result['token'];
        } else {
            $token_notificacao = bin2hex(random_bytes(32));
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $dispositivo_tipo = 'web';
            
            if (strpos($user_agent, 'Mobile') !== false) {
                $dispositivo_tipo = 'mobile';
            } elseif (strpos($user_agent, 'Tablet') !== false) {
                $dispositivo_tipo = 'tablet';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO dispositivos_conectados 
                (usuario_id, dispositivo_nome, dispositivo_tipo, token, ip, user_agent, ultima_atividade)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['usuario_id'],
                $dispositivo_tipo . '_' . date('YmdHis'),
                $dispositivo_tipo,
                $token_notificacao,
                $ip,
                $user_agent
            ]);
        }
    }
} catch (Exception $e) {}

// ============================================
// CONTAR CHAMADAS PENDENTES
// ============================================
$chamadasPendentes = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM chamadas_internas WHERE usuario_destino = ? AND status = 'pendente'");
    $stmt->execute([$_SESSION['usuario_id']]);
    $result = $stmt->fetch();
    $chamadasPendentes = $result['total'] ?? 0;
} catch (Exception $e) {}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
$usuario_email = $_SESSION['usuario_email'] ?? '';
$isAdmin = ($usuario_perfil == 'admin');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>🎓 Escola - <?= SITE_NAME ?></title>
    
    <!-- ===== CSS ===== -->
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==========================================
           RESET E LAYOUT GERAL
           ========================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f0f2f5;
            height: 100%;
            overflow-x: hidden;
            font-size: 16px;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            position: relative;
            background: #f0f2f5;
        }

        /* ==========================================
           SIDEBAR - MESMO ESTILO DO DASHBOARD
           ========================================== */
        .sidebar-escola {
            width: 260px;
            min-width: 260px;
            max-width: 260px;
            flex-shrink: 0;
            background: linear-gradient(180deg, #1a2332 0%, #2c3e50 100%);
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
            box-shadow: 2px 0 20px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }

        .sidebar-escola::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-escola::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        .sidebar-escola::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
        }

        .sidebar-escola .sidebar-brand {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-escola .brand-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
            overflow: hidden;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            flex-shrink: 0;
        }

        .sidebar-escola .brand-icon img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 8px;
        }

        .sidebar-escola .brand-text h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            color: #fff;
        }

        .sidebar-escola .brand-text h2 span {
            color: #f5d76e;
        }

        .sidebar-escola .brand-text .subtitle {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            font-weight: 300;
        }

        .sidebar-escola .sidebar-nav {
            flex: 1;
            padding: 15px 10px;
        }

        .sidebar-escola .sidebar-nav .nav-section {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.3);
            padding: 12px 12px 6px;
            font-weight: 600;
        }

        .sidebar-escola .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            margin-bottom: 2px;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            position: relative;
        }

        .sidebar-escola .sidebar-nav a .nav-icon {
            font-size: 18px;
            width: 30px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-escola .sidebar-nav a .nav-text {
            flex: 1;
            font-size: 14px;
        }

        .sidebar-escola .sidebar-nav a .nav-badge {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
            font-size: 11px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            min-width: 22px;
            text-align: center;
        }

        .sidebar-escola .sidebar-nav a .nav-badge.primary { background: rgba(52,152,219,0.2); color: #3498db; }
        .sidebar-escola .sidebar-nav a .nav-badge.success { background: rgba(46,204,113,0.2); color: #2ecc71; }
        .sidebar-escola .sidebar-nav a .nav-badge.warning { background: rgba(243,156,18,0.2); color: #f39c12; }
        .sidebar-escola .sidebar-nav a .nav-badge.danger { background: rgba(231,76,60,0.2); color: #e74c3c; }
        .sidebar-escola .sidebar-nav a .nav-badge.gold { background: rgba(201,168,76,0.2); color: #c9a84c; }
        .sidebar-escola .sidebar-nav a .nav-badge.purple { background: rgba(155,89,182,0.2); color: #9b59b6; }

        .sidebar-escola .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
            transform: translateX(5px);
        }

        .sidebar-escola .sidebar-nav a.active {
            background: rgba(245, 215, 110, 0.15);
            color: #f5d76e;
            box-shadow: inset 3px 0 0 #f5d76e;
        }

        .sidebar-escola .sidebar-nav a.active .nav-icon {
            color: #f5d76e;
        }

        .sidebar-escola .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-escola .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .sidebar-escola .sidebar-footer .user-info .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .sidebar-escola .sidebar-footer .user-info .name {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
        }

        .sidebar-escola .sidebar-footer .user-info .email {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }

        .sidebar-escola .sidebar-footer .user-info .perfil {
            font-size: 10px;
            color: #c9a84c;
            font-weight: 600;
            margin-top: 2px;
        }

        .sidebar-escola .sidebar-footer .btn-voltar {
            display: block;
            text-align: center;
            padding: 10px;
            background: rgba(201, 168, 76, 0.15);
            color: #c9a84c;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .sidebar-escola .sidebar-footer .btn-voltar:hover {
            background: rgba(201, 168, 76, 0.25);
        }

        .sidebar-escola .sidebar-footer .btn-sair {
            display: block;
            text-align: center;
            padding: 10px;
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 8px;
        }

        .sidebar-escola .sidebar-footer .btn-sair:hover {
            background: rgba(231, 76, 60, 0.25);
        }

        .badge-chamada {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: bold;
            animation: pulse 1.5s infinite;
            margin-left: 5px;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        /* ==========================================
           SIDEBAR OVERLAY (MOBILE)
           ========================================== */
        .sidebar-overlay-escola {
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

        .sidebar-overlay-escola.active {
            display: block !important;
        }

        /* ==========================================
           MAIN CONTENT
           ========================================== */
        .main-content-escola {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
            background: #f0f2f5;
        }

        /* ==========================================
           TOP BAR - MESMO ESTILO DO DASHBOARD
           ========================================== */
        .top-bar-escola {
            background: linear-gradient(135deg, #ffffff 0%, #fefcf3 100%);
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 20px rgba(197,165,50,0.15);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 2px solid #f5d76e;
            min-height: 60px;
        }

        .top-bar-escola .left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .top-bar-escola .menu-toggle {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #1a2332;
            display: none;
            padding: 5px 12px;
            border-radius: 6px;
            transition: background 0.3s;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }

        .top-bar-escola .menu-toggle:hover {
            background: #f0f2f5;
        }

        .top-bar-escola .page-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
        }

        .top-bar-escola .page-title span {
            color: #c9a84c;
        }

        .top-bar-escola .right {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-shrink: 0;
        }

        .top-bar-escola .right .welcome {
            font-size: 14px;
            color: #4a5568;
            font-weight: 500;
        }

        .top-bar-escola .right .date-time {
            font-size: 14px;
            color: #c9a84c;
            background: rgba(197,165,50,0.1);
            padding: 6px 18px;
            border-radius: 20px;
            border: 1px solid rgba(197,165,50,0.2);
            font-weight: 500;
            white-space: nowrap;
        }

        /* ==========================================
           NAVEGAÇÃO SUPERIOR
           ========================================== */
        .nav-top-escola {
            background: #ffffff;
            padding: 8px 30px;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            width: 100%;
            flex-shrink: 0;
            min-height: 44px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.02);
        }

        .nav-top-escola a {
            color: #4a5568;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 6px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .nav-top-escola a:hover {
            background: rgba(197,165,50,0.1);
            color: #c9a84c;
        }

        .nav-top-escola a.active {
            background: rgba(201, 168, 76, 0.12);
            color: #c9a84c;
            font-weight: 600;
        }

        .nav-top-escola a .badge {
            background: #c9a84c;
            color: #1a2332;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 8px;
            border-radius: 10px;
            line-height: 1.4;
        }

        .nav-top-escola a .badge.danger {
            background: #e74c3c;
            color: white;
        }

        .nav-top-escola .separator {
            color: #e2e8f0;
            font-size: 13px;
        }

        /* ==========================================
           CONTENT AREA
           ========================================== */
        .content-area-escola {
            padding: 25px 30px;
            width: 100%;
            max-width: 100%;
            flex: 1;
        }

        /* ==========================================
           RESPONSIVIDADE
           ========================================== */
        @media (max-width: 992px) {
            .sidebar-escola {
                transform: translateX(-100%);
                width: 260px;
                min-width: 260px;
                max-width: 260px;
            }

            .sidebar-escola.open {
                transform: translateX(0);
            }

            .main-content-escola {
                margin-left: 0;
            }

            .top-bar-escola .menu-toggle {
                display: block;
            }

            .top-bar-escola {
                padding: 10px 15px;
                flex-wrap: wrap;
                gap: 10px;
            }

            .top-bar-escola .right .welcome {
                display: none;
            }

            .nav-top-escola {
                padding: 6px 15px;
                gap: 4px;
                overflow-x: auto;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            
            .nav-top-escola::-webkit-scrollbar {
                display: none;
            }
            
            .nav-top-escola a {
                font-size: 12px;
                padding: 4px 12px;
            }
            
            .nav-top-escola .separator {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .content-area-escola {
                padding: 15px;
            }

            .top-bar-escola .page-title {
                font-size: 17px;
            }

            .top-bar-escola .right .date-time {
                font-size: 12px;
                padding: 4px 12px;
            }

            .top-bar-escola {
                padding: 8px 12px;
                min-height: 54px;
            }

            .top-bar-escola .menu-toggle {
                font-size: 24px;
                padding: 4px 10px;
            }

            .nav-top-escola a {
                font-size: 11px;
                padding: 3px 10px;
            }
        }

        @media (max-width: 480px) {
            .top-bar-escola .page-title {
                font-size: 15px;
            }

            .top-bar-escola .right {
                gap: 10px;
            }

            .top-bar-escola .right .date-time {
                font-size: 11px;
                padding: 3px 10px;
            }

            .sidebar-escola {
                width: 280px;
                min-width: 280px;
                max-width: 280px;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- ============================================
         SIDEBAR
         ============================================ -->
    <aside class="sidebar-escola" id="sidebarEscola">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <?php if (!empty($logoEmpresa) && file_exists("assets/uploads/" . $logoEmpresa)): ?>
                    <img src="<?= SITE_URL ?>assets/uploads/<?= $logoEmpresa ?>" alt="Logo">
                <?php else: ?>
                    🎓
                <?php endif; ?>
            </div>
            <div class="brand-text">
                <h2><?= htmlspecialchars($nomeEmpresa) ?> <span>Escola</span></h2>
                <div class="subtitle">Módulo Acadêmico</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <!-- ===== VISÃO GERAL ===== -->
            <div class="nav-section">📊 Visão Geral</div>
            <a href="<?= SITE_URL ?>modules/escola/" class="<?= $current_page == 'index.php' && $current_dir == 'escola' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>

            <!-- ===== COMUNICAÇÃO ===== -->
            <div class="nav-section">💬 Comunicação</div>
            <?php if (file_exists(__DIR__ . '/mensagens_functions.php')): ?>
            <a href="<?= SITE_URL ?>modules/escola/mensagens.php" class="<?= $current_page == 'mensagens.php' ? 'active' : '' ?>">
                <span class="nav-icon">💬</span>
                <span class="nav-text">Mensagens</span>
                <?php if ($mensagens_nao_lidas > 0): ?>
                    <span class="nav-badge danger"><?= $mensagens_nao_lidas ?></span>
                <?php else: ?>
                    <span class="nav-badge">0</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <a href="<?= SITE_URL ?>modules/escola/chamada.php" class="<?= $current_page == 'chamada.php' ? 'active' : '' ?>">
                <span class="nav-icon">📞</span>
                <span class="nav-text">Chamadas</span>
                <?php if ($chamadasPendentes > 0): ?>
                    <span class="badge-chamada"><?= $chamadasPendentes ?></span>
                <?php endif; ?>
            </a>

            <!-- ===== GESTÃO ACADÊMICA ===== -->
            <div class="nav-section">👥 Gestão Acadêmica</div>

            <a href="<?= SITE_URL ?>modules/escola/alunos/" class="<?= $current_dir == 'alunos' ? 'active' : '' ?>">
                <span class="nav-icon">👨‍🎓</span>
                <span class="nav-text">Alunos</span>
                <span class="nav-badge primary"><?= $totalAlunos ?></span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/professores/" class="<?= $current_dir == 'professores' ? 'active' : '' ?>">
                <span class="nav-icon">👨‍🏫</span>
                <span class="nav-text">Professores</span>
                <span class="nav-badge success"><?= $totalProfessores ?></span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/turmas/" class="<?= $current_dir == 'turmas' ? 'active' : '' ?>">
                <span class="nav-icon">🏫</span>
                <span class="nav-text">Turmas</span>
                <span class="nav-badge warning"><?= $totalTurmas ?></span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/disciplinas/" class="<?= $current_dir == 'disciplinas' ? 'active' : '' ?>">
                <span class="nav-icon">📚</span>
                <span class="nav-text">Disciplinas</span>
                <span class="nav-badge info"><?= $totalDisciplinas ?></span>
            </a>

            <!-- ===== CONTROLE ===== -->
            <div class="nav-section">📋 Controle</div>

            <a href="<?= SITE_URL ?>modules/escola/frequencia/" class="<?= $current_dir == 'frequencia' ? 'active' : '' ?>">
                <span class="nav-icon">✅</span>
                <span class="nav-text">Frequência</span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/notas/" class="<?= $current_dir == 'notas' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Notas</span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/horarios/" class="<?= $current_dir == 'horarios' ? 'active' : '' ?>">
                <span class="nav-icon">⏰</span>
                <span class="nav-text">Horários</span>
            </a>

            <!-- ===== PEDAGÓGICO ===== -->
            <div class="nav-section">📖 Pedagógico</div>

            <a href="<?= SITE_URL ?>modules/escola/pedagogico/" class="<?= $current_dir == 'pedagogico' ? 'active' : '' ?>">
                <span class="nav-icon">📋</span>
                <span class="nav-text">Pedagógico</span>
            </a>

            <!-- ===== FINANCEIRO ===== -->
            <div class="nav-section">💰 Financeiro</div>

            <a href="<?= SITE_URL ?>modules/escola/financeiro/" class="<?= $current_dir == 'financeiro' ? 'active' : '' ?>">
                <span class="nav-icon">💰</span>
                <span class="nav-text">Financeiro</span>
            </a>

            <!-- ===== NOTIFICAÇÕES ===== -->
            <div class="nav-section">📱 Notificações</div>
            <button class="btn-compartilhar-notif" onclick="compartilharTokenNotificacao()" style="
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 15px;
                width: 100%;
                background: rgba(201, 168, 76, 0.1);
                border: 1px solid rgba(201, 168, 76, 0.2);
                border-radius: 8px;
                color: rgba(255,255,255,0.7);
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.3s;
                font-family: inherit;
            ">
                <span style="font-size:18px;">📱</span>
                <span style="flex:1;text-align:left;">Compartilhar Notificações</span>
                <span style="font-size:12px;color:#c9a84c;">→</span>
            </button>

            <!-- ===== CONEXÃO DE DISPOSITIVOS ===== -->
            <div class="nav-section">📱 Conexão</div>
            <a href="<?= SITE_URL ?>modules/escola/conectar.php" class="<?= $current_page == 'conectar.php' ? 'active' : '' ?>">
                <span class="nav-icon">📱</span>
                <span class="nav-text">Conectar Dispositivo</span>
            </a>

            <!-- ===== RELATÓRIOS ===== -->
            <div class="nav-section">📈 Relatórios</div>

            <a href="<?= SITE_URL ?>modules/escola/relatorios/" class="<?= $current_dir == 'relatorios' ? 'active' : '' ?>">
                <span class="nav-icon">📈</span>
                <span class="nav-text">Relatórios</span>
            </a>

            <!-- ===== REDE ===== -->
            <div class="nav-section">🌐 Rede</div>

            <a href="<?= SITE_URL ?>network_access.php" class="<?= $current_page == 'network_access.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-wifi" style="font-size:18px;"></i></span>
                <span class="nav-text">Acesso à Rede</span>
            </a>

            <a href="<?= SITE_URL ?>monitorar_dispositivos.php" class="<?= $current_page == 'monitorar_dispositivos.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-desktop" style="font-size:18px;"></i></span>
                <span class="nav-text">Monitor de Dispositivos</span>
            </a>

            <a href="<?= SITE_URL ?>rede_info.php" class="<?= $current_page == 'rede_info.php' ? 'active' : '' ?>">
                <span class="nav-icon">🌐</span>
                <span class="nav-text">Acesso à Rede por IP</span>
            </a>

            <!-- ===== VOLTAR ===== -->
            <div class="nav-section">🏠 Sistema</div>
            <a href="<?= SITE_URL ?>index.php">
                <span class="nav-icon">🏠</span>
                <span class="nav-text">Dashboard Principal</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar">👤</div>
                <div>
                    <div class="name"><?= htmlspecialchars($usuario_nome) ?></div>
                    <div class="email"><?= htmlspecialchars($usuario_email) ?></div>
                    <div class="perfil"><?= ucfirst($usuario_perfil) ?></div>
                </div>
            </div>
            <a href="<?= SITE_URL ?>" class="btn-voltar">
                🏠 Voltar ao Sistema
            </a>
            <a href="<?= SITE_URL ?>logout.php" class="btn-sair">
                🚪 Sair
            </a>
        </div>
    </aside>

    <!-- Overlay mobile -->
    <div class="sidebar-overlay-escola" id="sidebarOverlayEscola" onclick="closeSidebarEscola()"></div>

    <!-- ============================================
         MAIN CONTENT
         ============================================ -->
    <main class="main-content-escola" id="mainContentEscola">
        <!-- ===== TOP BAR ===== -->
        <div class="top-bar-escola" id="topBarEscola">
            <div class="left">
                <button class="menu-toggle" id="menuToggleBtn" onclick="toggleSidebarEscola()" aria-label="Abrir menu">
                    ☰
                </button>
                <span class="page-title">🎓 <span>Gestão Escolar</span></span>
            </div>
            <div class="right">
                <span class="welcome">Bem-vindo, <?= htmlspecialchars($usuario_nome) ?></span>
                <span class="date-time" id="currentDateTimeEscola"></span>
            </div>
        </div>

        <!-- ===== NAVEGAÇÃO SUPERIOR ===== -->
        <div class="nav-top-escola">
            <a href="<?= SITE_URL ?>modules/escola/" class="<?= $current_dir == 'escola' && $current_page == 'index.php' ? 'active' : '' ?>">
                📊 Dashboard
            </a>
            
            <?php if (file_exists(__DIR__ . '/mensagens_functions.php')): ?>
            <span class="separator">|</span>
            <a href="<?= SITE_URL ?>modules/escola/mensagens.php" class="<?= $current_page == 'mensagens.php' ? 'active' : '' ?>">
                💬 Mensagens
                <?php if ($mensagens_nao_lidas > 0): ?>
                    <span class="badge danger"><?= $mensagens_nao_lidas ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <span class="separator">|</span>
            <a href="<?= SITE_URL ?>modules/escola/alunos/" class="<?= $current_dir == 'alunos' ? 'active' : '' ?>">
                👨‍🎓 Alunos <span class="badge"><?= $totalAlunos ?></span>
            </a>
            
            <span class="separator">|</span>
            <a href="<?= SITE_URL ?>modules/escola/professores/" class="<?= $current_dir == 'professores' ? 'active' : '' ?>">
                👨‍🏫 Professores <span class="badge"><?= $totalProfessores ?></span>
            </a>
            
            <span class="separator">|</span>
            <a href="<?= SITE_URL ?>modules/escola/turmas/" class="<?= $current_dir == 'turmas' ? 'active' : '' ?>">
                🏫 Turmas <span class="badge"><?= $totalTurmas ?></span>
            </a>
            
            <span class="separator">|</span>
            <a href="<?= SITE_URL ?>modules/escola/disciplinas/" class="<?= $current_dir == 'disciplinas' ? 'active' : '' ?>">
                📚 Disciplinas
            </a>
            
            <span class="separator">|</span>
            <a href="<?= SITE_URL ?>modules/escola/frequencia/" class="<?= $current_dir == 'frequencia' ? 'active' : '' ?>">
                ✅ Frequência
            </a>
            
            <span class="separator">|</span>
            <a href="<?= SITE_URL ?>modules/escola/notas/" class="<?= $current_dir == 'notas' ? 'active' : '' ?>">
                📊 Notas
            </a>
            
            <span class="separator">|</span>
            <a href="<?= SITE_URL ?>modules/escola/horarios/" class="<?= $current_dir == 'horarios' ? 'active' : '' ?>">
                ⏰ Horários
            </a>
            
            <span class="separator">|</span>
            <a href="<?= SITE_URL ?>modules/escola/financeiro/" class="<?= $current_dir == 'financeiro' ? 'active' : '' ?>">
                💰 Financeiro
            </a>
            
            <span class="separator">|</span>
            <a href="<?= SITE_URL ?>modules/escola/relatorios/" class="<?= $current_dir == 'relatorios' ? 'active' : '' ?>">
                📈 Relatórios
            </a>
        </div>

        <!-- ===== CONTENT AREA ===== -->
        <div class="content-area-escola">