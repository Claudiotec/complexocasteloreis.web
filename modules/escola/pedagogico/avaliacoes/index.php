<?php
// ============================================
// modules/escola/pedagogico/avaliacoes/index.php - Avaliações
// ============================================

$basePath = realpath(__DIR__ . '/../../../../');
require_once $basePath . '/config/database.php';
require_once $basePath . '/config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . $basePath . '/login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . $basePath . '/index.php');
    exit;
}

include '../../../includes/header_escola.php';
?>

<!-- ===== SIDEBAR (mesmo padrão dos outros) ===== -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo">
            🎓 SOFTGEST
            <span>Módulo Acadêmico</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">📊 Visão Geral</div>
        <a href="../../../index.php">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <div class="nav-section">👥 Gestão Acadêmica</div>
        <a href="../../../alunos/index.php">
            <i class="fas fa-user-graduate"></i> Alunos
        </a>
        <a href="../../../professores/index.php">
            <i class="fas fa-chalkboard-teacher"></i> Professores
        </a>
        <a href="../../../turmas/index.php">
            <i class="fas fa-users"></i> Turmas
        </a>
        <a href="../../../disciplinas/index.php">
            <i class="fas fa-book"></i> Disciplinas
        </a>
        <a href="../../../matriculas/index.php">
            <i class="fas fa-file-signature"></i> Matrículas
        </a>
        
        <div class="nav-section">📖 Pedagógico</div>
        <a href="../index.php">
            <i class="fas fa-chalkboard-teacher"></i> Pedagógico
        </a>
        <a href="../planos/index.php" style="padding-left: 52px; font-size: 14px;">
            <i class="fas fa-file-alt"></i> Planos de Aula
        </a>
        <a href="../aulas/index.php" style="padding-left: 52px; font-size: 14px;">
            <i class="fas fa-video"></i> Aulas
        </a>
        <a href="index.php" style="padding-left: 52px; font-size: 14px; color: #c9a84c;">
            <i class="fas fa-tasks"></i> Avaliações
        </a>
        <a href="../conteudos/index.php" style="padding-left: 52px; font-size: 14px;">
            <i class="fas fa-book-open"></i> Conteúdos
        </a>
        <a href="../habilidades/index.php" style="padding-left: 52px; font-size: 14px;">
            <i class="fas fa-brain"></i> Habilidades
        </a>
        <a href="../relatorios/index.php" style="padding-left: 52px; font-size: 14px;">
            <i class="fas fa-chart-pie"></i> Relatórios
        </a>
        
        <div class="nav-section">👤 Usuário</div>
        <a href="../../../perfil/index.php">
            <i class="fas fa-user-circle"></i> Perfil
        </a>
        <a href="<?= $basePath ?>/logout.php">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar">A</div>
            <div>
                <div class="name"><?= $_SESSION['usuario_nome'] ?? 'Administrador' ?></div>
                <div class="email"><?= $_SESSION['usuario_email'] ?? 'admin@softgest.com' ?></div>
            </div>
        </div>
        <a href="<?= $basePath ?>/index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Voltar ao Sistema
        </a>
    </div>
</aside>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
    <div class="page-header">
        <h1>📊 Avaliações</h1>
        <p>Criação e gestão de avaliações, provas e trabalhos</p>
        <div class="breadcrumb">
            <a href="../../../index.php">Dashboard</a> / 
            <a href="../index.php">Pedagógico</a> / Avaliações
        </div>
    </div>
    
    <div style="display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;">
        <a href="#" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nova Avaliação
        </a>
        <a href="#" class="btn btn-outline">
            <i class="fas fa-file-pdf"></i> Modelos
        </a>
    </div>
    
    <div class="card">
        <h3 class="card-title">📋 Avaliações</h3>
        
        <div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 64px; margin-bottom: 16px;">📊</div>
            <p style="font-size: 20px; color: #1a2332; font-weight: 600;">Nenhuma avaliação cadastrada</p>
            <p style="color: #94a3b8; font-size: 16px; margin-top: 8px;">Crie avaliações para suas turmas</p>
            <a href="#" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-plus"></i> Criar Avaliação
            </a>
        </div>
    </div>
</div>

<?php include '../../../includes/footer_escola.php'; ?>