<?php
// ============================================
// modules/escola/pedagogico/relatorios/index.php - Relatórios Pedagógicos
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

<!-- ===== SIDEBAR (mesmo padrão) ===== -->
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
        <a href="../avaliacoes/index.php" style="padding-left: 52px; font-size: 14px;">
            <i class="fas fa-tasks"></i> Avaliações
        </a>
        <a href="../conteudos/index.php" style="padding-left: 52px; font-size: 14px;">
            <i class="fas fa-book-open"></i> Conteúdos
        </a>
        <a href="../habilidades/index.php" style="padding-left: 52px; font-size: 14px;">
            <i class="fas fa-brain"></i> Habilidades
        </a>
        <a href="index.php" style="padding-left: 52px; font-size: 14px; color: #c9a84c;">
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
        <h1>📈 Relatórios Pedagógicos</h1>
        <p>Relatórios de desempenho e acompanhamento pedagógico</p>
        <div class="breadcrumb">
            <a href="../../../index.php">Dashboard</a> / 
            <a href="../index.php">Pedagógico</a> / Relatórios
        </div>
    </div>
    
    <!-- Tipos de Relatórios -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <div class="card" style="cursor: pointer; border-left: 5px solid #c9a84c;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="font-size: 40px;">📊</div>
                <div>
                    <h4 style="color: #1a2332; margin: 0; font-size: 18px;">Desempenho por Turma</h4>
                    <p style="color: #94a3b8; margin: 4px 0 0; font-size: 14px;">Médias e aprovações por turma</p>
                </div>
                <div style="margin-left: auto; color: #c9a84c;">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        </div>
        
        <div class="card" style="cursor: pointer; border-left: 5px solid #3b82f6;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="font-size: 40px;">👨‍🎓</div>
                <div>
                    <h4 style="color: #1a2332; margin: 0; font-size: 18px;">Desempenho por Aluno</h4>
                    <p style="color: #94a3b8; margin: 4px 0 0; font-size: 14px;">Acompanhamento individual</p>
                </div>
                <div style="margin-left: auto; color: #3b82f6;">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        </div>
        
        <div class="card" style="cursor: pointer; border-left: 5px solid #22c55e;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="font-size: 40px;">📋</div>
                <div>
                    <h4 style="color: #1a2332; margin: 0; font-size: 18px;">Frequência Geral</h4>
                    <p style="color: #94a3b8; margin: 4px 0 0; font-size: 14px;">Relatório de frequência</p>
                </div>
                <div style="margin-left: auto; color: #22c55e;">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div class="card" style="cursor: pointer; border-left: 5px solid #f59e0b;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="font-size: 40px;">📝</div>
                <div>
                    <h4 style="color: #1a2332; margin: 0; font-size: 18px;">Planos de Aula</h4>
                    <p style="color: #94a3b8; margin: 4px 0 0; font-size: 14px;">Relatório de planos de aula</p>
                </div>
                <div style="margin-left: auto; color: #f59e0b;">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        </div>
        
        <div class="card" style="cursor: pointer; border-left: 5px solid #8b5cf6;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="font-size: 40px;">🧠</div>
                <div>
                    <h4 style="color: #1a2332; margin: 0; font-size: 18px;">Habilidades BNCC</h4>
                    <p style="color: #94a3b8; margin: 4px 0 0; font-size: 14px;">Alinhamento com BNCC</p>
                </div>
                <div style="margin-left: auto; color: #8b5cf6;">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        </div>
        
        <div class="card" style="cursor: pointer; border-left: 5px solid #ef4444;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="font-size: 40px;">📈</div>
                <div>
                    <h4 style="color: #1a2332; margin: 0; font-size: 18px;">Relatório Consolidado</h4>
                    <p style="color: #94a3b8; margin: 4px 0 0; font-size: 14px;">Visão geral do período</p>
                </div>
                <div style="margin-left: auto; color: #ef4444;">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card" style="margin-top: 24px;">
        <h3 class="card-title">ℹ️ Sobre os Relatórios</h3>
        <p style="color: #64748b; line-height: 1.8; font-size: 15px;">
            Os relatórios pedagógicos fornecem uma visão completa do desempenho acadêmico,
            permitindo acompanhar o progresso dos alunos, identificar áreas que precisam de
            atenção e tomar decisões baseadas em dados.
        </p>
    </div>
</div>

<?php include '../../../includes/footer_escola.php'; ?>