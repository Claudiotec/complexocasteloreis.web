<?php
// ============================================
// coordenador_pedagogico/templates/sidebar.php
// ============================================
?>
<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
    </div>
</div>

<div class="dashboard-container">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">📋</div>
            <div class="brand-text">
                <h2>SoftGest</h2>
                <span>Coord. Pedagógico</span>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <!-- Dashboard -->
            <a href="#" class="active" data-section="dashboard" onclick="showSection('dashboard')">
                <span class="nav-icon">📊</span> Dashboard
            </a>
            
            <!-- Distribuição de Professores -->
            <a href="#" data-section="distribuicao" onclick="showSection('distribuicao')">
                <span class="nav-icon">👨‍🏫</span> Distribuição de Professores
                <span class="nav-badge" id="distribCount"><?= count($distribuicoes ?? []) ?></span>
            </a>
            
            <!-- Banco de Notas -->
            <a href="#" data-section="bancoNotas" onclick="showSection('bancoNotas')">
                <span class="nav-icon">📝</span> Banco de Notas
                <span class="nav-badge" id="notasCount"><?= count($notasList ?? []) ?></span>
            </a>
            
            <!-- Pautas -->
            <a href="#" data-section="pautas" onclick="showSection('pautas')">
                <span class="nav-icon">📄</span> Pautas
            </a>
            
            <!-- ===== NOVO: RELATÓRIOS ===== -->
            <a href="#" data-section="relatorios" onclick="showSection('relatorios')">
                <span class="nav-icon">📈</span> Relatórios
                <span class="nav-badge" style="background: #c9a84c; color: #1a2332;">NEW</span>
            </a>
            
            <!-- Configurações -->
            <a href="#" data-section="configuracoes" onclick="showSection('configuracoes')">
                <span class="nav-icon">⚙️</span> Configurações
            </a>
            
            <hr class="bg-secondary mx-3">
            
            <!-- Painel Principal -->
            <a href="/softgest_web/index.php">
                <span class="nav-icon">🏠</span> Painel Principal
            </a>
            
            <!-- Sair -->
            <a href="/softgest_web/logout.php">
                <span class="nav-icon">🚪</span> Sair
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-avatar">
                <div class="avatar-icon">👤</div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($usuario_nome ?? 'Usuário') ?></div>
                    <div class="user-email"><?= htmlspecialchars($usuario_email ?? '') ?></div>
                </div>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div>
                <button class="btn-toggle-sidebar" onclick="toggleSidebar()">☰</button>
                <h3 id="pageTitle">Dashboard Pedagógico</h3>
                <span class="subtitle" id="pageSubtitle">Visão geral do sistema acadêmico</span>
            </div>
            <div class="user-info">
                <span class="welcome-text">Bem-vindo, <?= htmlspecialchars($usuario_nome ?? 'Usuário') ?></span>
                <span class="badge-perfil">📋 Coordenador Pedagógico</span>
                <span id="currentTime" style="font-size:14px;color:var(--text-muted);"></span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">