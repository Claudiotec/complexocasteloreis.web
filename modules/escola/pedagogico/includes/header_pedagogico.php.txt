<?php
// ============================================
// modules/pedagogico/includes/header_pedagogico.php
// ============================================

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Pedagogico', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo Pedagógico - SOFTGEST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-gold: #c9a84c;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f5f7fb;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            background: #1a2332;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        
        .sidebar-brand .logo {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-gold);
        }
        
        .sidebar-brand .logo span {
            display: block;
            font-size: 12px;
            font-weight: 400;
            color: #94a3b8;
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 12px;
            overflow-y: auto;
        }
        
        .sidebar-nav .nav-section {
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            padding: 12px 12px 6px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.06);
            color: white;
        }
        
        .sidebar-nav a.active {
            background: var(--primary-gold);
            color: white;
        }
        
        .sidebar-nav a .badge {
            margin-left: auto;
            background: rgba(255,255,255,0.1);
            color: #94a3b8;
            font-size: 11px;
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .sidebar-nav a.active .badge {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .sidebar-nav a i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        
        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        
        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .sidebar-footer .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: white;
        }
        
        .sidebar-footer .name {
            font-size: 14px;
            font-weight: 600;
        }
        
        .sidebar-footer .email {
            font-size: 12px;
            color: #94a3b8;
        }
        
        .sidebar-footer .btn-back {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .sidebar-footer .btn-back:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-height: 100vh;
            background: #f5f7fb;
        }
        
        .topbar {
            background: white;
            padding: 16px 28px;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .topbar h2 {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
            margin: 0;
        }
        
        .topbar .breadcrumb {
            font-size: 13px;
            color: #94a3b8;
            margin: 2px 0 0;
        }
        
        .topbar-right {
            text-align: right;
        }
        
        .topbar-right .welcome {
            display: block;
            font-weight: 600;
            color: #1a2332;
            font-size: 14px;
        }
        
        .topbar-right .date {
            font-size: 12px;
            color: #94a3b8;
        }
        
        .content {
            padding: 24px 28px 40px;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .topbar {
                padding: 12px 16px;
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .topbar h2 {
                font-size: 16px;
            }
            
            .content {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- ===== SIDEBAR ===== -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="logo">
                    🎓 SOFTGEST
                    <span>Módulo Pedagógico</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <!-- Visão Geral -->
                <div class="nav-section">📊 Visão Geral</div>
                
                <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                
                <!-- Gestão Acadêmica -->
                <div class="nav-section">👥 Gestão Acadêmica</div>
                
                <a href="turmas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'turmas.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Turmas
                    <span class="badge" id="totalTurmas">0</span>
                </a>
                
                <a href="notas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'notas.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Notas
                </a>
                
                <a href="pautas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'pautas.php' ? 'active' : '' ?>">
                    <i class="fas fa-table"></i> Pautas
                </a>
                
                <!-- Controle -->
                <div class="nav-section">📋 Controle</div>
                
                <a href="frequencia.php" class="<?= basename($_SERVER['PHP_SELF']) == 'frequencia.php' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Frequência
                </a>
                
                <a href="boletins.php" class="<?= basename($_SERVER['PHP_SELF']) == 'boletins.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-alt"></i> Boletins
                </a>
                
                <a href="certificados.php" class="<?= basename($_SERVER['PHP_SELF']) == 'certificados.php' ? 'active' : '' ?>">
                    <i class="fas fa-certificate"></i> Certificados
                </a>
                
                <a href="ocorrencias.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ocorrencias.php' ? 'active' : '' ?>">
                    <i class="fas fa-exclamation-triangle"></i> Ocorrências
                </a>
                
                <!-- Voltar -->
                <div class="nav-section" style="margin-top: 20px;">🔙 Navegação</div>
                
                <a href="../escola/index.php">
                    <i class="fas fa-school"></i> Voltar à Escola
                </a>
                
                <a href="../../index.php">
                    <i class="fas fa-home"></i> Dashboard Principal
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="avatar"><?= strtoupper(substr($_SESSION['usuario_nome'] ?? 'A', 0, 1)) ?></div>
                    <div>
                        <div class="name"><?= $_SESSION['usuario_nome'] ?? 'Administrador' ?></div>
                        <div class="email"><?= $_SESSION['usuario_email'] ?? 'admin@softgest.com' ?></div>
                    </div>
                </div>
                <a href="../../logout.php" class="btn-back">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </aside>
        
        <!-- ===== MAIN CONTENT ===== -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div>
                    <h2>📚 Módulo Pedagógico</h2>
                    <p class="breadcrumb">
                        <?php
                        $page = basename($_SERVER['PHP_SELF'], '.php');
                        $pageNames = [
                            'index' => 'Dashboard',
                            'turmas' => 'Turmas',
                            'notas' => 'Notas',
                            'pautas' => 'Pautas',
                            'frequencia' => 'Frequência',
                            'boletins' => 'Boletins',
                            'certificados' => 'Certificados',
                            'ocorrencias' => 'Ocorrências'
                        ];
                        echo 'Pedagógico / ' . ($pageNames[$page] ?? ucfirst($page));
                        ?>
                    </p>
                </div>
                <div class="topbar-right">
                    <span class="welcome">👋 <?= $_SESSION['usuario_nome'] ?? 'Administrador' ?></span>
                    <span class="date"><?= date('D, d \d\e M \d\e Y, H:i:s') ?></span>
                </div>
            </header>
            
            <!-- Content -->
            <div class="content">