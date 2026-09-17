<?php
// modules/escola/index.php
// Ou esta versão (mais robusta):
require_once(__DIR__ . '/../includes/verificar_permissao_escola.php');

$permissoes = verificarMultiplasPermissoesEscola('escola', ['visualizar', 'criar', 'editar', 'excluir']);

if ($permissoes['visualizar']) {
    // Mostra conteúdo
}

if ($permissoes['criar']) {
    // Mostra botão criar
}
?>



<?php
// ============================================
// modules/escola/pedagogico/index.php - Pedagógico
// ============================================

// Carregar configurações (caminho corrigido)
require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== DADOS =====
$totalPlanos = 0;
$totalAtividades = 0;
$totalAvaliacoes = 0;
$totalProjetos = 0;

try {
    // Verificar se a tabela planos_ensino existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'planos_ensino'");
    if ($stmt->rowCount() > 0) {
        $totalPlanos = $pdo->query("SELECT COUNT(*) FROM planos_ensino")->fetchColumn() ?? 0;
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'atividades_pedagogicas'");
    if ($stmt->rowCount() > 0) {
        $totalAtividades = $pdo->query("SELECT COUNT(*) FROM atividades_pedagogicas")->fetchColumn() ?? 0;
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'avaliacoes'");
    if ($stmt->rowCount() > 0) {
        $totalAvaliacoes = $pdo->query("SELECT COUNT(*) FROM avaliacoes")->fetchColumn() ?? 0;
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'projetos'");
    if ($stmt->rowCount() > 0) {
        $totalProjetos = $pdo->query("SELECT COUNT(*) FROM projetos")->fetchColumn() ?? 0;
    }
} catch (Exception $e) {}

// ===== INCLUIR HEADER =====
include '../includes/header_escola.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .page-header .subtitle {
        color: #94a3b8;
        font-size: 14px;
        margin: 2px 0 0;
    }
    
    .btn {
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
    }
    
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    
    .btn-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }
    
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    
    .btn-success:hover {
        background: #27ae60;
    }
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .btn-info:hover {
        background: #2980b9;
    }
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-warning:hover {
        background: #d68910;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 18px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 22px 24px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        border-left: 4px solid #c9a84c;
        transition: all 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.08);
    }
    
    .stat-card .number {
        font-size: 30px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .stat-card .label {
        font-size: 13px;
        color: #94a3b8;
        margin: 5px 0 0;
    }
    
    .stat-card .icon {
        font-size: 28px;
        display: block;
        margin-bottom: 8px;
    }
    
    .stat-card.planos { border-left-color: #3498db; }
    .stat-card.atividades { border-left-color: #2ecc71; }
    .stat-card.avaliacoes { border-left-color: #f39c12; }
    .stat-card.projetos { border-left-color: #9b59b6; }
    
    .menu-pedagogico {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 25px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .menu-pedagogico a {
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
        color: #4a5568;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .menu-pedagogico a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .menu-pedagogico a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    
    .card-list {
        background: white;
        border-radius: 12px;
        padding: 22px 26px;
        margin-top: 20px;
        border: 1px solid #eef2f7;
    }
    
    .card-list h3 {
        margin-bottom: 15px;
        color: #1a2332;
        font-size: 17px;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #94a3b8;
    }
    
    .empty-state .icon {
        font-size: 56px;
        display: block;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    .empty-state h3 {
        font-size: 18px;
        color: #4a5568;
        margin: 0 0 5px;
    }
    
    .empty-state p {
        font-size: 14px;
    }
    
    .acoes-rapidas {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 25px;
        padding: 18px 20px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        justify-content: center;
        align-items: center;
    }
    
    .acoes-rapidas .label {
        color: #94a3b8;
        font-size: 13px;
        font-weight: 500;
        margin-right: 5px;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .menu-pedagogico {
            flex-direction: column;
            align-items: stretch;
        }
        .menu-pedagogico a {
            text-align: center;
            justify-content: center;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .stat-card {
            padding: 16px 18px;
        }
        .stat-card .number {
            font-size: 24px;
        }
        .acoes-rapidas {
            flex-direction: column;
            align-items: stretch;
        }
        .acoes-rapidas .btn {
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📖 Pedagógico</h1>
        <p class="subtitle">Gestão de planos de ensino, atividades, distribuição de professores e projetos pedagógicos</p>
    </div>
    <a href="../index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Menu Pedagógico -->
<div class="menu-pedagogico">
    <a href="index.php" class="active">📊 Dashboard</a>
    <a href="planos/">📋 Planos de Ensino</a>
    <a href="atividades/">📝 Atividades</a>
    <a href="avaliacoes/">📊 Avaliações</a>
    <a href="projetos/">🎯 Projetos</a>
    <a href="distribuicao.php">👨‍🏫 Distribuição de Professores</a>
    
    <!-- ============================================
         PAUTAS TRIMESTRAIS - ADICIONADO
         ============================================ -->
    <a href="pautas/index.php">
        <i class="fas fa-file-alt"></i> 📋 Pautas Trimestrais
    </a>
    
    <a href="relatorios/">📈 Relatórios</a>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card planos">
        <span class="icon">📋</span>
        <div class="number"><?= $totalPlanos ?></div>
        <div class="label">Planos de Ensino</div>
    </div>
    <div class="stat-card atividades">
        <span class="icon">📝</span>
        <div class="number"><?= $totalAtividades ?></div>
        <div class="label">Atividades</div>
    </div>
    <div class="stat-card avaliacoes">
        <span class="icon">📊</span>
        <div class="number"><?= $totalAvaliacoes ?></div>
        <div class="label">Avaliações</div>
    </div>
    <div class="stat-card projetos">
        <span class="icon">🎯</span>
        <div class="number"><?= $totalProjetos ?></div>
        <div class="label">Projetos</div>
    </div>
</div>

<!-- Conteúdo Principal -->
<div class="card-list">
    <h3>📖 Gestão Pedagógica</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 3px solid #3498db;">
            <div style="font-weight: 600; color: #1a2332;">Planos de Ensino</div>
            <div style="font-size: 13px; color: #94a3b8;">Crie e gerencie planos de ensino por disciplina</div>
        </div>
        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 3px solid #2ecc71;">
            <div style="font-weight: 600; color: #1a2332;">Atividades</div>
            <div style="font-size: 13px; color: #94a3b8;">Registre atividades e tarefas pedagógicas</div>
        </div>
        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 3px solid #f39c12;">
            <div style="font-weight: 600; color: #1a2332;">Avaliações</div>
            <div style="font-size: 13px; color: #94a3b8;">Gerencie avaliações e resultados</div>
        </div>
        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 3px solid #9b59b6;">
            <div style="font-weight: 600; color: #1a2332;">Projetos</div>
            <div style="font-size: 13px; color: #94a3b8;">Gerencie projetos pedagógicos</div>
        </div>
        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 3px solid #c9a84c;">
            <div style="font-weight: 600; color: #1a2332;">Distribuição de Professores</div>
            <div style="font-size: 13px; color: #94a3b8;">Distribua professores, coordenadores e diretores de turma</div>
        </div>
        <!-- ============================================
             PAUTAS TRIMESTRAIS - ADICIONADO
             ============================================ -->
        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 3px solid #e74c3c;">
            <div style="font-weight: 600; color: #1a2332;">Pautas Trimestrais</div>
            <div style="font-size: 13px; color: #94a3b8;">Gere pautas com notas, médias e estatísticas por trimestre</div>
        </div>
    </div>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span class="label">⚡ Ações rápidas:</span>
    <a href="planos/add.php" class="btn btn-primary">📋 Novo Plano</a>
    <a href="atividades/add.php" class="btn btn-success">📝 Nova Atividade</a>
    <a href="avaliacoes/add.php" class="btn btn-warning">📊 Nova Avaliação</a>
    <a href="projetos/add.php" class="btn btn-info">🎯 Novo Projeto</a>
    <a href="distribuicao.php" class="btn" style="background: #c9a84c; color: #1a2332;">👨‍🏫 Distribuir Professores</a>
    <!-- ============================================
         PAUTAS TRIMESTRAIS - ADICIONADO NAS AÇÕES RÁPIDAS
         ============================================ -->
    <a href="pautas/index.php" class="btn" style="background: #e74c3c; color: #fff;">
        <i class="fas fa-file-alt"></i> 📋 Gerar Pauta
    </a>
</div>

<?php
// ===== INCLUIR FOOTER =====
include '../includes/footer_escola.php';
?>