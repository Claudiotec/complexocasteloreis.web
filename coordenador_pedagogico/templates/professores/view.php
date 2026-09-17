<?php
// ============================================
// modules/escola/professores/view.php - Visualizar Professor
// ============================================

require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../../../login.php');
    exit;
}

bloquearAcesso('visualizar');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$professor = null;
$erro = '';

if ($id > 0) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ?");
        $stmt->execute([$id]);
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$professor) {
            $erro = "Professor não encontrado!";
        }
    } catch (Exception $e) {
        $erro = "Erro ao buscar professor: " . $e->getMessage();
    }
} else {
    $erro = "ID do professor não informado!";
}

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
    .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
    .page-header .subtitle { color: #94a3b8; font-size: 14px; margin: 2px 0 0; }
    
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
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-warning { background: #f39c12; color: #fff; }
    .btn-danger { background: #e74c3c; color: #fff; }
    
    .card-detalhes {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 900px;
    }
    
    .card-detalhes .header-info {
        display: flex;
        align-items: center;
        gap: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #eef2f7;
        margin-bottom: 15px;
    }
    
    .card-detalhes .header-info .avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: #c9a84c;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: #fff;
        font-weight: 700;
        flex-shrink: 0;
    }
    
    .card-detalhes .header-info .info h2 {
        font-size: 22px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .card-detalhes .header-info .info .sub {
        color: #94a3b8;
        font-size: 14px;
        margin-top: 4px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-ativo { background: #d1fae5; color: #065f46; }
    .status-inativo { background: #fee2e2; color: #991b1b; }
    .status-ferias { background: #fef3c7; color: #92400e; }
    
    .card-detalhes .grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 30px;
    }
    
    .card-detalhes .item {
        display: flex;
        padding: 6px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .card-detalhes .item .label {
        width: 140px;
        font-weight: 600;
        color: #4a5568;
        flex-shrink: 0;
        font-size: 13px;
    }
    
    .card-detalhes .item .valor {
        color: #1a2332;
        font-weight: 500;
        font-size: 13px;
    }
    
    .section-title {
        font-size: 14px;
        font-weight: 700;
        color: #1a2332;
        margin: 15px 0 10px;
        padding-bottom: 5px;
        border-bottom: 1px solid #eef2f7;
    }
    
    .acoes {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 20px;
    }
    
    @media (max-width: 768px) {
        .card-detalhes .grid { grid-template-columns: 1fr; gap: 0; }
        .card-detalhes .header-info { flex-direction: column; text-align: center; }
        .acoes { flex-direction: column; align-items: stretch; }
        .acoes .btn { justify-content: center; }
    }
</style>

<div class="page-header">
    <h1>👤 Visualizar Professor</h1>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if ($erro): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:15px;border-radius:8px;margin-bottom:20px;">❌ <?= $erro ?></div>
<?php endif; ?>

<?php if ($professor): ?>
<div class="card-detalhes">
    <div class="header-info">
        <div class="avatar">
            <?= strtoupper(substr($professor['nome'] ?? 'U', 0, 2)) ?>
        </div>
        <div class="info">
            <h2><?= htmlspecialchars($professor['nome'] ?? '') ?></h2>
            <div class="sub">
                <span class="status-badge status-<?= $professor['status'] ?? 'ativo' ?>">
                    <?= ucfirst($professor['status'] ?? 'ativo') ?>
                </span>
                <span class="separator" style="margin:0 10px;color:#e2e8f0;">|</span>
                ID: <?= $professor['id'] ?>
            </div>
        </div>
    </div>

    <!-- Dados Pessoais -->
    <div class="section-title">📌 Dados Pessoais</div>
    <div class="grid">
        <div class="item"><span class="label">Nome Completo:</span><span class="valor"><?= htmlspecialchars($professor['nome'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Nº Agente:</span><span class="valor"><?= htmlspecialchars($professor['num_agente'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Data Nascimento:</span><span class="valor"><?= $professor['data_nascimento'] ? date('d/m/Y', strtotime($professor['data_nascimento'])) : '-' ?></span></div>
        <div class="item"><span class="label">Gênero:</span><span class="valor"><?= $professor['genero'] ?? '-' ?></span></div>
        <div class="item"><span class="label">Nº BI:</span><span class="valor"><?= htmlspecialchars($professor['num_bi'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Contacto:</span><span class="valor"><?= htmlspecialchars($professor['contacto_telefonico'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Telefone Alt.:</span><span class="valor"><?= htmlspecialchars($professor['telefone'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Email:</span><span class="valor"><?= htmlspecialchars($professor['email'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Município:</span><span class="valor"><?= htmlspecialchars($professor['municipio_residencia'] ?? '-') ?></span></div>
    </div>

    <!-- Dados Profissionais -->
    <div class="section-title">🏫 Dados Profissionais</div>
    <div class="grid">
        <div class="item"><span class="label">Categoria Actual:</span><span class="valor"><?= htmlspecialchars($professor['categoria_actual'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Cargo:</span><span class="valor"><?= htmlspecialchars($professor['cargo'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Função Instituição:</span><span class="valor"><?= htmlspecialchars($professor['funcao_instituicao'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Departamento:</span><span class="valor"><?= htmlspecialchars($professor['departamento'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Data Admissão:</span><span class="valor"><?= $professor['data_admissao'] ? date('d/m/Y', strtotime($professor['data_admissao'])) : '-' ?></span></div>
        <div class="item"><span class="label">Status:</span><span class="valor"><span class="status-badge status-<?= $professor['status'] ?? 'ativo' ?>"><?= ucfirst($professor['status'] ?? 'ativo') ?></span></span></div>
    </div>

    <!-- Formação -->
    <div class="section-title">📚 Formação</div>
    <div class="grid">
        <div class="item"><span class="label">Habilitações:</span><span class="valor"><?= htmlspecialchars($professor['habilitacoes_literarias'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Especialidade Superior:</span><span class="valor"><?= htmlspecialchars($professor['especialidade_superior'] ?? '-') ?></span></div>
        <div class="item"><span class="label">Disciplina que Leciona:</span><span class="valor"><?= htmlspecialchars($professor['disciplina_lecciona'] ?? '-') ?></span></div>
    </div>

    <!-- Financeiro -->
    <div class="section-title">💰 Dados Financeiros</div>
    <div class="grid">
        <div class="item"><span class="label">Salário Base:</span><span class="valor"><?= number_format($professor['salario_base'] ?? 0, 2, ',', '.') ?> Kz</span></div>
        <div class="item"><span class="label">Salário Actual:</span><span class="valor"><?= number_format($professor['salario'] ?? 0, 2, ',', '.') ?> Kz</span></div>
    </div>

    <!-- Ações -->
    <div class="acoes">
        <a href="edit.php?id=<?= $professor['id'] ?>" class="btn btn-warning">✏️ Editar</a>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>
<?php endif; ?>

