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
// GERAR TOKEN DE NOTIFICAÇÃO AUTOMATICAMENTE
// ============================================
$token_notificacao = null;
try {
    // Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'dispositivos_conectados'");
    if ($stmt->rowCount() > 0) {
        // Verificar se já existe um token para o usuário
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
            // Gerar novo token
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
} catch (Exception $e) {
    error_log("Erro ao gerar token: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
           SIDEBAR EXCLUSIVA DO ESCOLA
           ========================================== */
        .sidebar-escola {
            width: 280px;
            min-width: 280px;
            max-width: 280px;
            flex-shrink: 0;
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
            border-right: 3px solid #c9a84c;
            transition: transform 0.3s ease-in-out;
        }

        .sidebar-escola::-webkit-scrollbar { width: 4px; }
        .sidebar-escola::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); }
        .sidebar-escola::-webkit-scrollbar-thumb { background: rgba(201, 168, 76, 0.3); border-radius: 10px; }

        .sidebar-escola::after {
            content: '';
            position: absolute;
            top: 0;
            right: -3px;
            width: 3px;
            height: 100%;
            background: linear-gradient(180deg, transparent 0%, #c9a84c 20%, #f5d76e 50%, #c9a84c 80%, transparent 100%);
            animation: glowBorder 3s ease-in-out infinite;
        }

        @keyframes glowBorder {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        .sidebar-escola .sidebar-brand {
            padding: 22px 20px 18px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .sidebar-escola .brand-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
            font-size: 22px;
            font-weight: 800;
            color: #0f1724;
        }

        .sidebar-escola .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-escola .brand-text h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            color: #fff;
        }

        .sidebar-escola .brand-text h2 span { color: #c9a84c; }
        .sidebar-escola .brand-text .subtitle {
            font-size: 11px;
            color: rgba(255,255,255,0.35);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 500;
            margin-top: 2px;
        }

        .sidebar-escola .sidebar-nav {
            flex: 1;
            padding: 14px 12px 20px;
        }

        .sidebar-escola .sidebar-nav .nav-section {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255,255,255,0.2);
            padding: 14px 14px 8px;
            font-weight: 600;
        }

        .sidebar-escola .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: rgba(255,255,255,0.55);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            margin-bottom: 2px;
            gap: 14px;
            font-size: 15px;
            font-weight: 500;
            position: relative;
        }

        .sidebar-escola .sidebar-nav a .nav-icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-escola .sidebar-nav a .nav-text {
            flex: 1;
            font-size: 15px;
        }

        .sidebar-escola .sidebar-nav a .nav-badge {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.5);
            font-size: 11px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 14px;
            min-width: 22px;
            text-align: center;
        }

        .sidebar-escola .sidebar-nav a .nav-badge.primary { background: rgba(52,152,219,0.2); color: #3498db; }
        .sidebar-escola .sidebar-nav a .nav-badge.success { background: rgba(46,204,113,0.2); color: #2ecc71; }
        .sidebar-escola .sidebar-nav a .nav-badge.info { background: rgba(52,152,219,0.2); color: #3498db; }
        .sidebar-escola .sidebar-nav a .nav-badge.warning { background: rgba(243,156,18,0.2); color: #f39c12; }
        .sidebar-escola .sidebar-nav a .nav-badge.danger { background: rgba(231,76,60,0.2); color: #e74c3c; }
        .sidebar-escola .sidebar-nav a .nav-badge.purple { background: rgba(155,89,182,0.2); color: #9b59b6; }

        .sidebar-escola .sidebar-nav a:hover {
            background: rgba(255,255,255,0.06);
            color: #fff;
            transform: translateX(4px);
        }

        .sidebar-escola .sidebar-nav a.active {
            background: rgba(201, 168, 76, 0.1);
            color: #c9a84c;
            border: 1px solid rgba(201, 168, 76, 0.1);
        }

        .sidebar-escola .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: #c9a84c;
            border-radius: 0 3px 3px 0;
        }

        .sidebar-escola .sidebar-nav a.active .nav-icon { color: #c9a84c; }

        .sidebar-escola .sidebar-footer {
            padding: 14px 18px 18px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .sidebar-escola .sidebar-footer .btn-voltar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background: rgba(201, 168, 76, 0.1);
            color: #c9a84c;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            border: 1px solid rgba(201, 168, 76, 0.1);
        }

        .sidebar-escola .sidebar-footer .btn-voltar:hover {
            background: rgba(201, 168, 76, 0.2);
            transform: translateY(-2px);
        }

        .sidebar-escola .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding: 8px 4px;
        }

        .sidebar-escola .sidebar-footer .user-info .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: #0f1724;
        }

        .sidebar-escola .sidebar-footer .user-info .name {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
        }

        .sidebar-escola .sidebar-footer .user-info .email {
            font-size: 12px;
            color: rgba(255,255,255,0.35);
        }

        /* ==========================================
           MAIN CONTENT
           ========================================== */
        .main-content-escola {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
            width: calc(100% - 280px);
            max-width: calc(100% - 280px);
            background: #f0f2f5;
            overflow-x: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* ==========================================
           TOP BAR
           ========================================== */
        .top-bar-escola {
            background: linear-gradient(135deg, #ffffff 0%, #fefcf3 100%);
            padding: 10px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 20px rgba(197,165,50,0.1);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 2px solid #f5d76e;
            min-height: 60px;
            width: 100%;
            flex-shrink: 0;
        }

        .top-bar-escola .left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .top-bar-escola .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #1a2332;
            padding: 6px 10px;
        }

        .top-bar-escola .page-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
        }

        .top-bar-escola .page-title span { color: #c9a84c; }

        .top-bar-escola .right {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-shrink: 0;
        }

        .top-bar-escola .right .welcome {
            font-size: 15px;
            color: #4a5568;
            font-weight: 500;
        }

        .top-bar-escola .right .date-time {
            font-size: 14px;
            color: #c9a84c;
            background: rgba(197,165,50,0.08);
            padding: 5px 18px;
            border-radius: 22px;
            border: 1px solid rgba(197,165,50,0.1);
            font-weight: 500;
            white-space: nowrap;
        }

        /* ==========================================
           NAVEGAÇÃO SUPERIOR
           ========================================== */
        .nav-top-escola {
            background: #ffffff;
            padding: 8px 25px;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            width: 100%;
            flex-shrink: 0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.02);
            min-height: 44px;
        }

        .nav-top-escola a {
            color: #4a5568;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 6px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .nav-top-escola a:hover {
            background: rgba(201, 168, 76, 0.08);
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
            padding: 20px 30px;
            width: 100%;
            max-width: 100%;
            flex: 1;
            overflow-x: auto;
            background: #f0f2f5;
        }

        /* ==========================================
           OVERLAY MOBILE
           ========================================== */
        .sidebar-overlay-escola {
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

        .sidebar-overlay-escola.active { display: block; }

        /* ==========================================
           RESPONSIVO
           ========================================== */
        @media (max-width: 992px) {
            .sidebar-escola {
                transform: translateX(-100%);
                border-right: none;
                width: 300px;
                min-width: 300px;
                max-width: 300px;
            }
            .sidebar-escola::after { display: none; }
            .sidebar-escola.open {
                transform: translateX(0);
                border-right: 3px solid #c9a84c;
            }
            .sidebar-escola.open::after { display: block; }
            .main-content-escola {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
            }
            .top-bar-escola .menu-toggle { display: block; }
            .top-bar-escola { padding: 10px 15px; }
            .top-bar-escola .right .welcome { display: none; }
            .top-bar-escola .page-title { font-size: 17px; }
            .content-area-escola { padding: 15px; }
            .nav-top-escola {
                padding: 6px 15px;
                gap: 4px;
                overflow-x: auto;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            .nav-top-escola::-webkit-scrollbar { display: none; }
            .nav-top-escola a { font-size: 12px; padding: 3px 10px; }
            .nav-top-escola .separator { display: none; }
            .nav-top-escola a .badge { font-size: 8px; padding: 0 6px; }
        }

        @media (max-width: 576px) {
            .top-bar-escola .page-title { font-size: 15px; }
            .top-bar-escola .right .date-time { font-size: 12px; padding: 3px 12px; }
            .top-bar-escola { padding: 8px 12px; min-height: 54px; }
            .content-area-escola { padding: 12px 14px; }
            .sidebar-escola { width: 280px; min-width: 280px; max-width: 280px; }
            .nav-top-escola { padding: 4px 12px; min-height: 36px; }
            .nav-top-escola a { font-size: 11px; padding: 2px 8px; }
        }

        /* ==========================================
           SCROLLBAR
           ========================================== */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c9a84c; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #b8973a; }

        /* ==========================================
           BADGE CHAMADA
           ========================================== */
        .badge-chamada {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: bold;
            animation: pulse 1.5s infinite;
            margin-left: 5px;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        /* ==========================================
           ÍCONE DE MENSAGENS NO TOPO
           ========================================== */
        .mensagens-top-icon {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #4a5568;
            text-decoration: none;
            font-size: 20px;
            padding: 6px 8px;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .mensagens-top-icon:hover {
            background: rgba(201, 168, 76, 0.1);
            transform: scale(1.05);
        }

        .mensagens-top-icon .badge-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            min-width: 18px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.4);
            animation: pulse-badge 2s infinite;
        }

        .mensagens-top-icon .badge-count.zero { display: none; }

        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* ==========================================
           BOTÃO COMPARTILHAR NOTIFICAÇÕES
           ========================================== */
        .btn-compartilhar-notif {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 8px;
            color: #4a5568;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-compartilhar-notif:hover {
            background: rgba(201, 168, 76, 0.1);
            color: #c9a84c;
        }

        /* ==========================================
           BOTÃO TOP BAR - COMPARTILHAR
           ========================================== */
        .btn-top-compartilhar {
            background: rgba(201, 168, 76, 0.1);
            border: 1px solid rgba(201, 168, 76, 0.3);
            cursor: pointer;
            padding: 6px 14px;
            border-radius: 20px;
            color: #1a2332;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-top-compartilhar:hover {
            background: rgba(201, 168, 76, 0.2);
            transform: translateY(-1px);
        }

        .btn-top-compartilhar i {
            color: #c9a84c;
        }

        @media (max-width: 768px) {
            .btn-top-compartilhar span { display: none; }
        }

        /* ==========================================
           ANIMAÇÕES DAS NOTIFICAÇÕES
           ========================================== */
        @keyframes notifSlideInMulti {
            from { opacity: 0; transform: translateX(80px) scale(0.95); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }

        @keyframes notifSlideOutMulti {
            from { opacity: 1; transform: translateX(0) scale(1); }
            to { opacity: 0; transform: translateX(80px) scale(0.95); }
        }
    </style>
</head>
<body>

<!-- ============================================
     SIDEBAR EXCLUSIVA DO ESCOLA
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
        <?php if (file_exists(__DIR__ . '/mensagens_functions.php')): ?>
        <div class="nav-section">💬 Comunicação</div>
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

        <a href="/softgest_web/modules/escola/chamada.php" class="<?= $current_page == 'chamada.php' ? 'active' : '' ?>">
            <span class="nav-icon">📞</span>
            <span class="nav-text">Chamada</span>
            <?php 
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM chamadas_internas WHERE usuario_destino = ? AND status = 'pendente'");
                $stmt->execute([$_SESSION['usuario_id']]);
                $pendentes = $stmt->fetch();
                if ($pendentes && $pendentes['total'] > 0): 
            ?>
                <span class="badge-chamada"><?= $pendentes['total'] ?></span>
            <?php 
                endif;
            } catch (Exception $e) {}
            ?>
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

        <!-- ===== COMPARTILHAR NOTIFICAÇÕES ===== -->
        <div class="nav-section">📱 Notificações</div>
        <button class="btn-compartilhar-notif" onclick="compartilharTokenNotificacao()" title="Compartilhar token para receber notificações em outros dispositivos">
            <i class="fas fa-share-alt"></i> Compartilhar Notificações
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
            <span class="nav-icon"><i class="fas fa-wifi"></i></span>
            <span class="nav-text">Acesso à Rede</span>
        </a>

        <a href="<?= SITE_URL ?>monitorar_dispositivos.php" class="<?= $current_page == 'monitorar_dispositivos.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-wifi"></i></span>
            <span class="nav-text">Monitor de Dispositivos</span>
        </a>

        <a href="../../rede_info.php" class="<?= $current_page == 'rede_info.php' ? 'active' : '' ?>">
            <span class="nav-icon">🌐</span>
            <span class="nav-text">Acesso à Rede por IP</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar">
                <?= substr($_SESSION['usuario_nome'] ?? 'U', 0, 1) ?>
            </div>
            <div>
                <div class="name"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></div>
                <div class="email"><?= htmlspecialchars($_SESSION['usuario_email'] ?? '') ?></div>
            </div>
        </div>
        <a href="<?= SITE_URL ?>" class="btn-voltar">
            <i class="fas fa-arrow-left"></i> Voltar ao Sistema
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
    <div class="top-bar-escola">
        <div class="left">
            <button class="menu-toggle" onclick="toggleSidebarEscola()" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>
            <span class="page-title">🎓 <span>Gestão Escolar</span></span>
        </div>
        <div class="right">
            <?php if (file_exists(__DIR__ . '/mensagens_functions.php')): ?>
            <a href="<?= SITE_URL ?>modules/escola/mensagens.php" class="mensagens-top-icon" title="Mensagens">
                <i class="fas fa-envelope"></i>
                <span class="badge-count <?= $mensagens_nao_lidas == 0 ? 'zero' : '' ?>">
                    <?= $mensagens_nao_lidas ?>
                </span>
            </a>
            <?php endif; ?>
            
            <!-- ===== BOTÃO COMPARTILHAR NOTIFICAÇÕES (TOP BAR) ===== -->
            <button class="btn-top-compartilhar" onclick="compartilharTokenNotificacao()" title="Compartilhar token para receber notificações em outros dispositivos">
                <i class="fas fa-share-alt"></i>
                <span>📱 Compartilhar Notificações</span>
            </button>
            
            <span class="welcome">👋 <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></span>
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

<!-- ============================================
     SCRIPT: NOTIFICAÇÕES MULTI-DISPOSITIVO
     ============================================ -->
<script src="<?= SITE_URL ?>assets/js/notification-client.js"></script>

<script>
// ==========================================
// TOKEN DE NOTIFICAÇÃO (GERADO PELO SERVIDOR)
// ==========================================
const TOKEN_NOTIFICACAO = <?= json_encode($token_notificacao) ?>;

// ==========================================
// FUNÇÕES DO SISTEMA DE NOTIFICAÇÕES
// ==========================================

// Gerar token automaticamente via AJAX
async function gerarTokenNotificacao() {
    try {
        const response = await fetch('/modules/escola/api_gerar_token.php', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        
        const data = await response.json();
        
        if (data.success && data.token) {
            // Salvar token
            sessionStorage.setItem('notif_token_multi', data.token);
            localStorage.setItem('notif_token_multi', data.token);
            
            // Adicionar à URL
            const url = new URL(window.location);
            url.searchParams.set('notif_token', data.token);
            window.history.replaceState({}, '', url);
            
            console.log('✅ Token gerado:', data.token.substring(0, 15) + '...');
            
            // Inicializar cliente de notificações
            iniciarClienteNotificacoes(data.token);
            
            return data.token;
        } else {
            console.error('❌ Erro ao gerar token:', data);
            return null;
        }
    } catch (error) {
        console.error('❌ Erro na requisição:', error);
        return null;
    }
}

// Iniciar cliente de notificações
function iniciarClienteNotificacoes(token) {
    if (typeof NotificationClient !== 'undefined' && token) {
        if (!window.notificationClient) {
            window.notificationClient = new NotificationClient({
                pollingInterval: 3000,
                onNotification: function(notificacao) {
                    console.log('📬 Notificação recebida:', notificacao);
                    
                    // Tocar som
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.frequency.value = 880;
                        osc.type = 'sine';
                        gain.gain.setValueAtTime(0.08, ctx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
                        osc.start(ctx.currentTime);
                        osc.stop(ctx.currentTime + 0.2);
                    } catch (e) {}
                    
                    // Vibrar (mobile)
                    if (navigator.vibrate) {
                        navigator.vibrate([100, 50, 100]);
                    }
                    
                    // Mostrar toast
                    mostrarToastNotificacao(notificacao);
                },
                onError: function(error) {
                    console.error('❌ Erro no cliente:', error);
                }
            });
            
            window.notificationClient.token = token;
            window.notificationClient.isRegistered = true;
            window.notificationClient.startPolling();
            
            console.log('📱 Cliente de notificações iniciado');
        }
    } else {
        console.warn('⚠️ NotificationClient não encontrado');
    }
}

// Mostrar toast de notificação
function mostrarToastNotificacao(notificacao) {
    let container = document.getElementById('toastContainerMulti');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainerMulti';
        container.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 99999;
            max-width: 380px;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    const cor = notificacao.cor || 'gold';
    const cores = {
        'success': '#2ecc71',
        'error': '#e74c3c',
        'warning': '#f39c12',
        'gold': '#c9a84c'
    };
    
    toast.style.cssText = `
        pointer-events: all;
        background: #ffffff;
        border-radius: 12px;
        padding: 16px 18px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        border-left: 4px solid ${cores[cor] || '#c9a84c'};
        animation: notifSlideInMulti 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    `;
    
    toast.innerHTML = `
        <div style="font-size:24px;flex-shrink:0;width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(201,168,76,0.1);">
            ${notificacao.icone || '📬'}
        </div>
        <div style="flex:1;min-width:0;">
            <div style="font-weight:700;color:#1a2332;font-size:14px;margin:0 0 3px;">${escapeHtml(notificacao.titulo)}</div>
            <div style="font-size:13px;color:#94a3b8;margin:0;line-height:1.5;">${escapeHtml(notificacao.mensagem)}</div>
            <span style="font-size:11px;color:#a0aec0;margin-top:4px;display:block;">${new Date().toLocaleTimeString('pt-BR')}</span>
        </div>
        <button onclick="this.closest('div[style]').remove()" style="background:none;border:none;color:#a0aec0;cursor:pointer;font-size:16px;padding:4px;">✕</button>
    `;
    
    if (notificacao.link) {
        toast.onclick = function(e) {
            if (!e.target.closest('button')) {
                window.location.href = notificacao.link;
            }
        };
    }
    
    container.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'notifSlideOutMulti 0.4s forwards';
            setTimeout(() => { if (toast.parentNode) toast.remove(); }, 400);
        }
    }, 8000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ==========================================
// COMPARTILHAR TOKEN DE NOTIFICAÇÃO
// ==========================================
function compartilharTokenNotificacao() {
    // Tentar pegar token de várias fontes
    let token = sessionStorage.getItem('notif_token_multi') || 
                localStorage.getItem('notif_token_multi') ||
                TOKEN_NOTIFICACAO;
    
    // Se não tiver token, tentar gerar
    if (!token) {
        alert('⏳ Gerando token... Aguarde um momento.');
        
        gerarTokenNotificacao().then(novoToken => {
            if (novoToken) {
                compartilharToken(novoToken);
            } else {
                alert('❌ Erro ao gerar token. Tente recarregar a página.');
            }
        });
        return;
    }
    
    compartilharToken(token);
}

function compartilharToken(token) {
    // Gerar link completo com token
    const linkCompleto = window.location.origin + window.location.pathname + '?notif_token=' + token;
    
    // Tentar compartilhar via Web Share API (mobile)
    if (navigator.share) {
        navigator.share({
            title: '🔔 Notificações do Sistema Escolar',
            text: 'Conecte-se para receber notificações em tempo real.',
            url: linkCompleto
        }).catch(() => {
            copiarToken(token, linkCompleto);
        });
    } else {
        copiarToken(token, linkCompleto);
    }
}

function copiarToken(token, link) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(link).then(() => {
            alert('✅ Link copiado! Compartilhe com outros dispositivos na mesma rede.\n\n' + link);
        }).catch(() => {
            prompt('Copie este link para usar em outro dispositivo:', link);
        });
    } else {
        prompt('Copie este link para usar em outro dispositivo:', link);
    }
}

// ==========================================
// CONECTAR COM TOKEN COMPARTILHADO
// ==========================================
function conectarComToken(token) {
    if (token && token.length > 10) {
        sessionStorage.setItem('notif_token_multi', token);
        localStorage.setItem('notif_token_multi', token);
        
        const url = new URL(window.location);
        url.searchParams.set('notif_token', token);
        window.history.replaceState({}, '', url);
        
        // Reiniciar cliente
        if (window.notificationClient) {
            window.notificationClient.token = token;
            window.notificationClient.isRegistered = true;
            window.notificationClient.startPolling();
        }
        
        alert('✅ Conectado com sucesso!');
        location.reload();
    } else {
        alert('⚠️ Token inválido');
    }
}

// ==========================================
// INICIALIZAR AO CARREGAR A PÁGINA
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    // Verificar token
    let token = sessionStorage.getItem('notif_token_multi') || 
                localStorage.getItem('notif_token_multi') ||
                TOKEN_NOTIFICACAO;
    
    // Verificar se tem token na URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlToken = urlParams.get('notif_token');
    if (urlToken && urlToken.length > 10) {
        token = urlToken;
        sessionStorage.setItem('notif_token_multi', token);
        localStorage.setItem('notif_token_multi', token);
    }
    
    if (token) {
        console.log('✅ Token encontrado:', token.substring(0, 15) + '...');
        iniciarClienteNotificacoes(token);
    } else {
        console.log('🔄 Gerando token automaticamente...');
        gerarTokenNotificacao();
    }
});

console.log('🔔 Sistema de notificações carregado');
</script>