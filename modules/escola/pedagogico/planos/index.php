<?php
// ============================================
// modules/escola/pedagogico/planos/index.php - Planos de Aula
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

<!-- ===== SIDEBAR ===== -->
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
        
        <div class="nav-section">📋 Controle</div>
        <a href="../../../frequencia/index.php">
            <i class="fas fa-check-circle"></i> Frequência
        </a>
        <a href="../../../notas/index.php">
            <i class="fas fa-chart-bar"></i> Notas
        </a>
        <a href="../../../horarios/index.php">
            <i class="fas fa-clock"></i> Horários
        </a>
        
        <div class="nav-section">💰 Financeiro</div>
        <a href="../../../financeiro/index.php">
            <i class="fas fa-coins"></i> Financeiro
        </a>
        
        <div class="nav-section">📖 Pedagógico</div>
        <a href="../index.php" class="active">
            <i class="fas fa-chalkboard-teacher"></i> Pedagógico
        </a>
        <a href="index.php" style="padding-left: 52px; font-size: 14px; color: #c9a84c;">
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
        <a href="../relatorios/index.php" style="padding-left: 52px; font-size: 14px;">
            <i class="fas fa-chart-pie"></i> Relatórios
        </a>
        
        <div class="nav-section">📈 Relatórios Gerais</div>
        <a href="../../../relatorios/index.php">
            <i class="fas fa-chart-line"></i> Relatórios
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
        <h1>📝 Planos de Aula</h1>
        <p>Criação e gestão de planos de aula por disciplina e turma</p>
        <div class="breadcrumb">
            <a href="../../../index.php">Dashboard</a> / 
            <a href="../index.php">Pedagógico</a> / Planos de Aula
        </div>
    </div>
    
    <div style="display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;">
        <a href="#" class="btn btn-primary">
            <i class="fas fa-plus"></i> Novo Plano de Aula
        </a>
        <a href="#" class="btn btn-outline">
            <i class="fas fa-file-export"></i> Exportar
        </a>
        <a href="#" class="btn btn-outline">
            <i class="fas fa-print"></i> Imprimir
        </a>
    </div>
    
    <!-- Filtros -->
    <div class="card" style="padding: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 4px;">Disciplina</label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid #eef2f7; border-radius: 8px; font-size: 15px; background: #fff;">
                    <option value="">Todas</option>
                    <option value="matematica">Matemática</option>
                    <option value="portugues">Português</option>
                    <option value="ciencias">Ciências</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 4px;">Turma</label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid #eef2f7; border-radius: 8px; font-size: 15px; background: #fff;">
                    <option value="">Todas</option>
                    <option value="6a">6º Ano A</option>
                    <option value="7a">7º Ano A</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 4px;">Status</label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid #eef2f7; border-radius: 8px; font-size: 15px; background: #fff;">
                    <option value="">Todos</option>
                    <option value="ativo">Ativo</option>
                    <option value="concluido">Concluído</option>
                </select>
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Lista de Planos -->
    <div class="card">
        <h3 class="card-title">📋 Planos de Aula Cadastrados</h3>
        
        <div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 64px; margin-bottom: 16px;">📝</div>
            <p style="font-size: 20px; color: #1a2332; font-weight: 600;">Nenhum plano de aula cadastrado</p>
            <p style="color: #94a3b8; font-size: 16px; margin-top: 8px;">Comece criando seu primeiro plano de aula</p>
            <a href="#" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-plus"></i> Criar Plano de Aula
            </a>
        </div>
    </div>
</div>

<?php include '../../../includes/footer_escola.php'; ?>