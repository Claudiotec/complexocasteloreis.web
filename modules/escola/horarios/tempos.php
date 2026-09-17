<?php
// ============================================
// modules/escola/horarios/tempos.php - Gerenciar Tempos
// ============================================

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

$pdo = conectarBanco();
$erro = '';
$sucesso = '';

// Apagar tempo
if (isset($_GET['apagar']) && intval($_GET['apagar']) > 0) {
    try {
        $id = intval($_GET['apagar']);
        // Verificar se há horários usando este tempo
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM horarios WHERE tempo_id = ?");
        $stmt->execute([$id]);
        $uso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($uso['total'] > 0) {
            $erro = "Não é possível apagar: este tempo está a ser usado em {$uso['total']} horário(s).";
        } else {
            $stmt = $pdo->prepare("DELETE FROM tempos WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['sucesso_tempo'] = 'Tempo apagado com sucesso!';
            header('Location: tempos.php');
            exit;
        }
    } catch (Exception $e) {
        $erro = 'Erro ao apagar: ' . $e->getMessage();
    }
}

// Buscar tempos
$tempos = [];
try {
    $stmt = $pdo->query("SELECT * FROM tempos ORDER BY turno, ordem, hora_inicio");
    $tempos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback sem is_intervalo
    try {
        $stmt = $pdo->query("SELECT id, nome, hora_inicio, hora_fim, ordem, turno, status FROM tempos ORDER BY ordem");
        $tempos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tempos as &$t) {
            $t['is_intervalo'] = (stripos($t['nome'], 'intervalo') !== false) ? 1 : 0;
        }
        unset($t);
    } catch (Exception $e2) {
        $erro = 'Erro ao buscar tempos: ' . $e2->getMessage();
    }
}

// Garantir is_intervalo
foreach ($tempos as &$t) {
    if (!isset($t['is_intervalo'])) {
        $t['is_intervalo'] = (stripos($t['nome'], 'intervalo') !== false) ? 1 : 0;
    }
}
unset($t);

if (isset($_SESSION['sucesso_tempo'])) {
    $sucesso = $_SESSION['sucesso_tempo'];
    unset($_SESSION['sucesso_tempo']);
}

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
    .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
    .page-header .subtitle { color: #94a3b8; font-size: 14px; margin: 2px 0 0; }
    .btn {
        padding: 8px 20px; border-radius: 8px; text-decoration: none; font-size: 13px;
        font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center;
        gap: 6px; border: none; cursor: pointer;
    }
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; transform: translateY(-2px); }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-danger { background: #e74c3c; color: #fff; }
    .btn-danger:hover { background: #c0392b; }
    .btn-info { background: #3b82f6; color: #fff; }
    .btn-info:hover { background: #2563eb; }
    .btn-sm { padding: 5px 12px; font-size: 12px; }
    .alert { padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #e74c3c; }
    .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #2ecc71; }
    .table-responsive {
        background: white; border-radius: 12px; border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04); overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; }
    thead th {
        background: #f8fafc; padding: 14px 18px; text-align: left; font-weight: 600;
        color: #4a5568; border-bottom: 2px solid #e2e8f0; font-size: 13px;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    tbody td { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    tbody tr:hover { background: #fafbfc; }
    .badge {
        display: inline-block; padding: 3px 12px; border-radius: 12px;
        font-size: 12px; font-weight: 600;
    }
    .badge-manha { background: #fef3c7; color: #92400e; }
    .badge-tarde { background: #dbeafe; color: #1e40af; }
    .badge-noite { background: #e0e7ff; color: #3730a3; }
    .badge-intervalo { background: #fef9e7; color: #d4a843; border: 1px solid #d4a843; }
    .badge-aula { background: #dbeafe; color: #1e40af; }
    .empty-state { text-align: center; padding: 50px 20px; color: #94a3b8; }
    .empty-state .icon { font-size: 48px; display: block; margin-bottom: 15px; }
    .empty-state h3 { font-size: 18px; color: #4a5568; margin: 0 0 5px; }
    .acoes { display: flex; gap: 5px; }
    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: stretch; }
    }
</style>

<div class="page-header">
    <div>
        <h1>⏱️ Gerenciar Tempos</h1>
        <p class="subtitle">Cadastrar, editar e apagar os tempos de aula</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="tempo_add.php" class="btn btn-primary">➕ Novo Tempo</a>
        <a href="index.php" class="btn btn-secondary">← Voltar à Grade</a>
    </div>
</div>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<?php if (!empty($sucesso)): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<?php if (count($tempos) > 0): ?>
<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th style="width: 60px;">#</th>
                <th>Nome</th>
                <th>Horário</th>
                <th>Turno</th>
                <th>Tipo</th>
                <th>Ordem</th>
                <th>Status</th>
                <th style="width: 200px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tempos as $i => $t): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($t['nome']) ?></strong></td>
                <td>
                    <?= date('H:i', strtotime($t['hora_inicio'])) ?> 
                    - 
                    <?= date('H:i', strtotime($t['hora_fim'])) ?>
                </td>
                <td>
                    <span class="badge badge-<?= htmlspecialchars($t['turno'] ?? 'manha') ?>">
                        <?= ucfirst(htmlspecialchars($t['turno'] ?? 'manha')) ?>
                    </span>
                </td>
                <td>
                    <?php if (!empty($t['is_intervalo'])): ?>
                        <span class="badge badge-intervalo">☕ Intervalo</span>
                    <?php else: ?>
                        <span class="badge badge-aula">📚 Aula</span>
                    <?php endif; ?>
                </td>
                <td><?= intval($t['ordem'] ?? 0) ?></td>
                <td>
                    <?php if (($t['status'] ?? 'ativo') === 'ativo'): ?>
                        <span style="color: #2ecc71; font-weight: 600;">● Ativo</span>
                    <?php else: ?>
                        <span style="color: #94a3b8;">● Inativo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="acoes">
                        <a href="tempo_edit.php?id=<?= $t['id'] ?>" class="btn btn-info btn-sm">✏️ Editar</a>
                        <a href="tempos.php?apagar=<?= $t['id'] ?>" 
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Tem a certeza que deseja apagar o tempo \'<?= htmlspecialchars($t['nome'], ENT_QUOTES) ?>\'?')">
                           🗑️ Apagar
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state">
    <span class="icon">⏱️</span>
    <h3>Nenhum tempo cadastrado</h3>
    <p>Clique em "Novo Tempo" para criar o primeiro tempo de aula.</p>
    <div style="margin-top: 20px;">
        <a href="tempo_add.php" class="btn btn-primary">➕ Novo Tempo</a>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer_escola.php'; ?>