<?php
// ============================================
// modules/escola/disciplinas/index.php - Listar Disciplinas
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para VISUALIZAR
bloquearAcesso('visualizar');

$totalDisciplinas = 0;
$totalAtivas = 0;
$totalInativas = 0;

try {
    $totalDisciplinas = $pdo->query("SELECT COUNT(*) FROM disciplinas")->fetchColumn() ?? 0;
    $totalAtivas = $pdo->query("SELECT COUNT(*) FROM disciplinas WHERE status = 'ativa'")->fetchColumn() ?? 0;
    $totalInativas = $pdo->query("SELECT COUNT(*) FROM disciplinas WHERE status = 'inativa'")->fetchColumn() ?? 0;
} catch (Exception $e) {}

$disciplinas = [];
try {
    // 🔧 CORREÇÃO AQUI: Mudado de 'professores' para 'funcionarios'
    $disciplinas = $pdo->query("
        SELECT d.*, f.nome as professor_nome
        FROM disciplinas d
        LEFT JOIN funcionarios f ON d.professor_id = f.id
        ORDER BY d.nome
    ")->fetchAll();
} catch (Exception $e) {
    // Se a tabela não existir, cria
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS disciplinas (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nome VARCHAR(100) NOT NULL,
                descricao TEXT,
                carga_horaria INT,
                professor_id INT,
                status ENUM('ativa','inativa') DEFAULT 'ativa',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $disciplinas = [];
    } catch (Exception $e2) {}
}

include '../includes/header_escola.php';
?>

<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
    .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
    .page-header .subtitle { color: #94a3b8; font-size: 14px; margin: 2px 0 0; }
    .btn { padding: 8px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; }
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(201,168,76,0.3); }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-info { background: #3498db; color: #fff; }
    .btn-info:hover { background: #2980b9; }
    .btn-warning { background: #f39c12; color: #fff; }
    .btn-warning:hover { background: #d68910; }
    .btn-danger { background: #e74c3c; color: #fff; }
    .btn-danger:hover { background: #c0392b; }
    .btn-sm { padding: 4px 12px; font-size: 11px; border-radius: 6px; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; padding: 18px 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; border-left: 4px solid #c9a84c; transition: all 0.3s; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .stat-card .number { font-size: 26px; font-weight: 700; color: #1a2332; margin: 0; }
    .stat-card .label { font-size: 12px; color: #94a3b8; margin: 3px 0 0; }
    .stat-card .icon { font-size: 24px; display: block; margin-bottom: 5px; }
    .stat-card.ativas { border-left-color: #2ecc71; }
    .stat-card.inativas { border-left-color: #e74c3c; }
    .stat-card.total { border-left-color: #3498db; }
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; }
    .table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 600px; }
    .table th { background: #f8fafc; padding: 12px 16px; text-align: left; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
    .table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .table tr:hover { background: #fafbfc; }
    .table .nome { font-weight: 600; color: #1a2332; }
    .status-badge { display: inline-block; padding: 3px 14px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .status-ativa { background: #d1fae5; color: #065f46; }
    .status-inativa { background: #fee2e2; color: #991b1b; }
    .empty-state { text-align: center; padding: 50px 20px; color: #94a3b8; }
    .empty-state .icon { font-size: 48px; display: block; margin-bottom: 15px; }
    .empty-state h3 { font-size: 18px; color: #4a5568; margin: 0 0 5px; }
    .table-actions { display: flex; gap: 4px; flex-wrap: wrap; }
    
    /* Alertas e notificações */
    .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
    .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #2ecc71; }
    .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #e74c3c; }
    .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f39c12; }
    .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3498db; }
    .alert .icon { font-size: 20px; }
    .alert .close { margin-left: auto; cursor: pointer; font-size: 20px; }
    
    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: stretch; }
        .actions { flex-direction: column; }
        .actions .btn { justify-content: center; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card { padding: 14px 16px; }
        .stat-card .number { font-size: 22px; }
        .table { font-size: 12px; min-width: 500px; }
        .table th, .table td { padding: 8px 10px; }
        .btn-sm { font-size: 10px; padding: 3px 8px; }
    }
    @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
</style>

<div class="page-header">
    <div>
        <h1>📚 Disciplinas</h1>
        <p class="subtitle">Gestão de disciplinas e matérias</p>
    </div>
    <div class="actions">
        <a href="add.php" class="btn btn-primary">➕ Nova Disciplina</a>
        <a href="../index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Mensagens de feedback -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <span class="icon">✅</span>
        <span><?= $_SESSION['success'] ?></span>
        <span class="close" onclick="this.parentElement.remove()">✕</span>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <span class="icon">❌</span>
        <span><?= $_SESSION['error'] ?></span>
        <span class="close" onclick="this.parentElement.remove()">✕</span>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card ativas">
        <span class="icon">✅</span>
        <div class="number"><?= $totalAtivas ?></div>
        <div class="label">Ativas</div>
    </div>
    <div class="stat-card inativas">
        <span class="icon">⛔</span>
        <div class="number"><?= $totalInativas ?></div>
        <div class="label">Inativas</div>
    </div>
    <div class="stat-card total">
        <span class="icon">📊</span>
        <div class="number"><?= $totalDisciplinas ?></div>
        <div class="label">Total</div>
    </div>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Disciplina</th>
                <th>Descrição</th>
                <th>Carga Horária</th>
                <th>Professor</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($disciplinas) > 0): ?>
                <?php foreach($disciplinas as $d): ?>
                <tr>
                    <td>#<?= $d['id'] ?></td>
                    <td class="nome"><?= htmlspecialchars($d['nome']) ?></td>
                    <td><?= htmlspecialchars(substr($d['descricao'] ?? '', 0, 30)) ?></td>
                    <td><?= $d['carga_horaria'] ?>h</td>
                    <td>
                        <?php if (!empty($d['professor_nome'])): ?>
                            <span style="display:flex;align-items:center;gap:6px;">
                                👨‍🏫 <?= htmlspecialchars($d['professor_nome']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#94a3b8;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $d['status'] ?? 'ativa' ?>">
                            <?= ucfirst($d['status'] ?? 'ativa') ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="edit.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                            <a href="delete.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta disciplina?')">🗑️ Excluir</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhuma disciplina cadastrada</h3>
                            <p>Clique em "Nova Disciplina" para começar.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Informações adicionais -->
<div style="margin-top: 20px; padding: 15px 20px; background: #f8fafc; border-radius: 8px; border: 1px solid #eef2f7; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; font-size: 13px; color: #64748b;">
    <span>📌 Total de disciplinas: <strong><?= $totalDisciplinas ?></strong></span>
    <span>👨‍🏫 Total de professores cadastrados: <strong><?= $pdo->query("SELECT COUNT(*) FROM funcionarios WHERE cargo = 'Professor' OR categoria_actual = 'Professor'")->fetchColumn() ?? 0 ?></strong></span>
    <span>🕐 Última atualização: <?= date('d/m/Y H:i:s') ?></span>
</div>

<?php include '../includes/footer_escola.php'; ?>