<?php
// ============================================
// modules/pedagogico/turmas.php - Gestão de Turmas
// ============================================

require_once '../../config/database.php';
require_once '../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// ===== PROCESSAR AÇÕES =====
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'cadastrar') {
        $sql = "INSERT INTO turmas (nome, classe, turno, sala, limite, curso, ano_letivo, disciplinas, ativo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([
            $_POST['nome'] ?? '',
            $_POST['classe'] ?? '',
            $_POST['turno'] ?? '',
            $_POST['sala'] ?? '',
            $_POST['limite'] ?? 25,
            $_POST['curso'] ?? '',
            $_POST['ano_letivo'] ?? date('Y') . '/' . (date('Y')+1),
            $_POST['disciplinas'] ?? '',
            1
        ])) {
            $mensagem = 'Turma cadastrada com sucesso!';
            $tipo_mensagem = 'success';
        } else {
            $mensagem = 'Erro ao cadastrar turma!';
            $tipo_mensagem = 'danger';
        }
    }
    
    if ($acao === 'excluir') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM turmas WHERE id = ?");
        if ($stmt->execute([$id])) {
            $mensagem = 'Turma excluída com sucesso!';
            $tipo_mensagem = 'success';
        } else {
            $mensagem = 'Erro ao excluir turma!';
            $tipo_mensagem = 'danger';
        }
    }
}

// ===== CARREGAR DADOS =====
$turmas = $pdo->query("
    SELECT t.*, 
           (SELECT COUNT(*) FROM alunos WHERE turma_id = t.id) as total_alunos
    FROM turmas t 
    ORDER BY t.classe, t.nome
")->fetchAll();

$totalTurmas = count($turmas);
$totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos")->fetchColumn() ?? 0;

include 'includes/header_pedagogico.php';
?>

<style>
    .page-header { margin-bottom: 24px; }
    .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; }
    .btn-gold { background: #c9a84c; color: white; border: none; }
    .btn-gold:hover { background: #b8943a; color: white; }
    .stats-mini {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stats-mini .item {
        background: white;
        padding: 14px 18px;
        border-radius: 10px;
        text-align: center;
        border: 1px solid #eef2f7;
    }
    .stats-mini .item .number {
        font-size: 24px;
        font-weight: 700;
        color: #c9a84c;
    }
    .stats-mini .item .label { font-size: 13px; color: #94a3b8; }
    .badge-ativo { background: #d1fae5; color: #065f46; }
    .badge-inativo { background: #fee2e2; color: #991b1b; }
    .modal-content { border-radius: 14px; border: none; }
    .form-control, .form-select { border-radius: 8px; border-color: #e2e8f0; }
    .form-control:focus, .form-select:focus {
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
    }
    .disciplina-tag {
        display: inline-block;
        background: #f1f5f9;
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 13px;
        margin: 3px 4px 3px 0;
    }
    .disciplina-tag .remove { cursor: pointer; color: #ef4444; margin-left: 6px; }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>🏫 Gerenciar Turmas</h1>
        <p style="color: #94a3b8; margin: 4px 0 0;">Cadastre e gerencie as turmas da escola</p>
    </div>
    <button class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#modalTurma">
        <i class="fas fa-plus"></i> Nova Turma
    </button>
</div>

<!-- Estatísticas -->
<div class="stats-mini">
    <div class="item">
        <div class="number"><?= $totalTurmas ?></div>
        <div class="label">Total de Turmas</div>
    </div>
    <div class="item">
        <div class="number"><?= $totalAlunos ?></div>
        <div class="label">Alunos Matriculados</div>
    </div>
</div>

<?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show">
        <?= $mens