<?php
// modules/escola/includes/header_aluno.php
// Template Header para o Painel do Aluno

// ============================================
// EVITAR DUPLICAÇÃO DE INCLUSÃO
// ============================================
if (defined('HEADER_ALUNO_LOADED')) {
    return;
}
define('HEADER_ALUNO_LOADED', true);

// ============================================
// VERIFICAR SE O ARQUIVO FOI INCLUÍDO CORRETAMENTE
// ============================================
if (!defined('BASE_PATH')) {
    $base_path = dirname(__DIR__, 3);
    define('BASE_PATH', $base_path);
}

if (!defined('SITE_URL')) {
    require_once BASE_PATH . '/config/database.php';
}

// ============================================
// VARIÁVEIS PARA O TEMPLATE
// ============================================
$pagina_atual = basename($_SERVER['PHP_SELF'], '.php');
$modulo_atual = 'aluno';

// ============================================
// DADOS DO ALUNO
// ============================================
$aluno_nome = $aluno_nome ?? $_SESSION['usuario_nome'] ?? 'Aluno';
$aluno_email = $aluno_email ?? $_SESSION['usuario_email'] ?? '';
$aluno_foto = $aluno_foto ?? '';
$matricula = $matricula ?? null;
$turma_id = $turma_id ?? null;
$notas = $notas ?? [];
$colegas = $colegas ?? [];
$horarios = $horarios ?? [];
$percentual_frequencia = $percentual_frequencia ?? 0;
$total_aulas = $total_aulas ?? 0;
$presencas = $presencas ?? 0;
$media_geral = $media_geral ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?? 'Painel do Aluno' ?> - SoftGest Escola</title>
    <base href="<?= SITE_URL ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           ESTILOS COMPLETOS DO TEMPLATE
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
            font-family: 'Inter', 'Segoe UI', Tahoma, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

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

        .sidebar-aluno .menu a .badge.primary { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .sidebar-aluno .menu a .badge.success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .sidebar-aluno .menu a .badge.warning { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        .sidebar-aluno .menu a .badge.danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }

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
           TOP BAR (SEM NAVEGAÇÃO DUPLICADA)
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

        /* ============================================
           RESPONSIVIDADE
           ============================================ */
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
        }

        @media (max-width: 480px) {
            .topbar-aluno .page-title {
                font-size: 14px;
            }

            .topbar-aluno .right .datetime {
                display: none;
            }
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
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
                <?php if (!empty($aluno_foto) && file_exists(BASE_PATH . '/assets/uploads/' . $aluno_foto)): ?>
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

            <a href="<?= SITE_URL ?>modules/escola/aluno_dashboard.php" class="<?= $pagina_atual == 'aluno_dashboard' ? 'active' : '' ?>">
                <span class="icon">📊</span>
                <span class="text">Dashboard</span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_notas.php" class="<?= $pagina_atual == 'aluno_notas' ? 'active' : '' ?>">
                <span class="icon">📝</span>
                <span class="text">Minhas Notas</span>
                <span class="badge primary"><?= count($notas) ?></span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_frequencia.php" class="<?= $pagina_atual == 'aluno_frequencia' ? 'active' : '' ?>">
                <span class="icon">✅</span>
                <span class="text">Frequência</span>
                <span class="badge <?= $percentual_frequencia >= 75 ? 'success' : ($percentual_frequencia >= 50 ? 'warning' : 'danger') ?>">
                    <?= $percentual_frequencia ?>%
                </span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_horarios.php" class="<?= $pagina_atual == 'aluno_horarios' ? 'active' : '' ?>">
                <span class="icon">🕐</span>
                <span class="text">Horários</span>
                <span class="badge primary"><?= count($horarios) ?></span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_boletim.php" class="<?= $pagina_atual == 'aluno_boletim' ? 'active' : '' ?>">
                <span class="icon">📋</span>
                <span class="text">Boletim</span>
            </a>

            <div class="section-label">👥 Social</div>

            <a href="<?= SITE_URL ?>modules/escola/aluno_colegas.php" class="<?= $pagina_atual == 'aluno_colegas' ? 'active' : '' ?>">
                <span class="icon">👥</span>
                <span class="text">Colegas</span>
                <span class="badge success"><?= count($colegas) ?></span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_mensagens.php" class="<?= $pagina_atual == 'aluno_mensagens' ? 'active' : '' ?>">
                <span class="icon">💬</span>
                <span class="text">Mensagens</span>
                <span class="badge danger">0</span>
            </a>

            <div class="section-label">⚙️ Configurações</div>

            <a href="<?= SITE_URL ?>modules/escola/aluno_perfil.php" class="<?= $pagina_atual == 'aluno_perfil' ? 'active' : '' ?>">
                <span class="icon">👤</span>
                <span class="text">Meu Perfil</span>
            </a>

            <a href="<?= SITE_URL ?>modules/escola/aluno_configuracoes.php" class="<?= $pagina_atual == 'aluno_configuracoes' ? 'active' : '' ?>">
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

        <!-- Top Bar - SEM A BARRA DE NAVEGAÇÃO DUPLICADA -->
        <div class="topbar-aluno">
            <div class="left">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="page-title">🎓 <span><?= $titulo_pagina ?? 'Painel do Aluno' ?></span></span>
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

        <!-- ============================================
             CONTEÚDO DINÂMICO
             ============================================ -->
        <div class="content-aluno">