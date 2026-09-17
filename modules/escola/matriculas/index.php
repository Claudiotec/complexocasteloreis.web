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
require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

$totalMatriculas = 0;
$totalAtivas = 0;
$totalTrancadas = 0;
$totalConcluidas = 0;

try {
    $totalMatriculas = $pdo->query("SELECT COUNT(*) FROM matriculas")->fetchColumn() ?? 0;
    $totalAtivas = $pdo->query("SELECT COUNT(*) FROM matriculas WHERE status = 'ativa'")->fetchColumn() ?? 0;
    $totalTrancadas = $pdo->query("SELECT COUNT(*) FROM matriculas WHERE status = 'trancada'")->fetchColumn() ?? 0;
    $totalConcluidas = $pdo->query("SELECT COUNT(*) FROM matriculas WHERE status = 'concluida'")->fetchColumn() ?? 0;
} catch (Exception $e) {}

$matriculas = [];
try {
    $matriculas = $pdo->query("
        SELECT m.*, a.nome as aluno_nome, t.nome as turma_nome
        FROM matriculas m
        LEFT JOIN alunos a ON m.aluno_id = a.id
        LEFT JOIN turmas t ON m.turma_id = t.id
        ORDER BY m.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS matriculas (
                id INT PRIMARY KEY AUTO_INCREMENT,
                aluno_id INT NOT NULL,
                turma_id INT NOT NULL,
                data_matricula DATE NOT NULL,
                status ENUM('ativa','trancada','cancelada','concluida') DEFAULT 'ativa',
                observacoes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $matriculas = [];
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
    .stat-card.trancadas { border-left-color: #f39c12; }
    .stat-card.concluidas { border-left-color: #3498db; }
    .stat-card.total { border-left-color: #c9a84c; }
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; }
    .table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 700px; }
    .table th { background: #f8fafc; padding: 12px 16px; text-align: left; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
    .table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .table tr:hover { background: #fafbfc; }
    .status-badge { display: inline-block; padding: 3px 14px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .status-ativa { background: #d1fae5; color: #065f46; }
    .status-trancada { background: #fef3c7; color: #92400e; }
    .status-cancelada { background: #fee2e2; color: #991b1b; }
    .status-concluida { background: #dbeafe; color: #1e40af; }
    .empty-state { text-align: center; padding: 50px 20px; color: #94a3b8; }
    .empty-state .icon { font-size: 48px; display: block; margin-bottom: 15px; }
    .empty-state h3 { font-size: 18px; color: #4a5568; margin: 0 0 5px; }
    .table-actions { display: flex; gap: 4px; flex-wrap: wrap; }
    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: stretch; }
        .actions { flex-direction: column; }
        .actions .btn { justify-content: center; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card { padding: 14px 16px; }
        .stat-card .number { font-size: 22px; }
        .table { font-size: 12px; min-width: 600px; }
        .table th, .table td { padding: 8px 10px; }
        .btn-sm { font-size: 10px; padding: 3px 8px; }
    }
    @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
</style>

<div class="page-header">
    <div>
        <h1>📝 Matrículas</h1>
        <p class="subtitle">Gestão de matrículas dos alunos</p>
    </div>
    <div class="actions">
        <a href="add.php" class="btn btn-primary">➕ Nova Matrícula</a>
        <a href="../index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card ativas">
        <span class="icon">✅</span>
        <div class="number"><?= $totalAtivas ?></div>
        <div class="label">Ativas</div>
    </div>
    <div class="stat-card trancadas">
        <span class="icon">⏸️</span>
        <div class="number"><?= $totalTrancadas ?></div>
        <div class="label">Trancadas</div>
    </div>
    <div class="stat-card concluidas">
        <span class="icon">🎓</span>
        <div class="number"><?= $totalConcluidas ?></div>
        <div class="label">Concluídas</div>
    </div>
    <div class="stat-card total">
        <span class="icon">📊</span>
        <div class="number"><?= $totalMatriculas ?></div>
        <div class="label">Total</div>
    </div>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Aluno</th>
                <th>Turma</th>
                <th>Data Matrícula</th>
                <th>Status</th>
                <th>Observações</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($matriculas) > 0): ?>
                <?php foreach($matriculas as $m): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['aluno_nome'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($m['turma_nome'] ?? '-') ?></td>
                    <td><?= date('d/m/Y', strtotime($m['data_matricula'])) ?></td>
                    <td>
                        <span class="status-badge status-<?= $m['status'] ?? 'ativa' ?>">
                            <?= ucfirst($m['status'] ?? 'ativa') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(substr($m['observacoes'] ?? '', 0, 25)) ?></td>
                    <td>
                        <div class="table-actions">
                            <a href="view.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-info">Visualizar</a>
                            <a href="edit.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                            <a href="delete.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?')">Excluir</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhuma matrícula registrada</h3>
                            <p>Clique em "Nova Matrícula" para começar.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer_escola.php'; ?>