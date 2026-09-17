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

if (!temPermissao('Escola', 'criar')) {
    header('Location: ' . SITE_URL);
    exit;
}

$erro = '';
$sucesso = '';

$alunos = [];
$turmas = [];

try {
    $alunos = $pdo->query("SELECT id, nome FROM alunos WHERE status = 'ativo' ORDER BY nome")->fetchAll();
    $turmas = $pdo->query("SELECT id, nome FROM turmas WHERE status = 'ativa' ORDER BY nome")->fetchAll();
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Verificar se já existe matrícula ativa
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM matriculas WHERE aluno_id = ? AND status = 'ativa'");
        $stmt->execute([$_POST['aluno_id']]);
        if ($stmt->fetchColumn() > 0) {
            $erro = 'Este aluno já possui uma matrícula ativa!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO matriculas (aluno_id, turma_id, data_matricula, status, observacoes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['aluno_id'],
                $_POST['turma_id'],
                $_POST['data_matricula'],
                $_POST['status'],
                $_POST['observacoes']
            ]);
            $sucesso = 'Matrícula realizada com sucesso!';
            $_POST = [];
        }
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

include '../includes/header_escola.php';
?>

<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
    .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
    .btn { padding: 8px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(201,168,76,0.3); }
    .form-container { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; max-width: 700px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #1a2332; font-size: 13px; }
    .form-group label .required { color: #e74c3c; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; font-family: inherit; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }
    .form-group textarea { min-height: 60px; resize: vertical; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
    .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .form-actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
    @media (max-width: 768px) {
        .form-row { grid-template-columns: 1fr; gap: 0; }
        .form-container { padding: 20px; }
        .page-header { flex-direction: column; align-items: stretch; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { justify-content: center; }
    }
</style>

<div class="page-header">
    <h1>➕ Nova Matrícula</h1>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="form-container">
    <?php if ($sucesso): ?><div class="alert alert-success">✅ <?= $sucesso ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-error">❌ <?= $erro ?></div><?php endif; ?>
    
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Aluno <span class="required">*</span></label>
                <select name="aluno_id" required>
                    <option value="">Selecione um aluno</option>
                    <?php foreach($alunos as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= ($_POST['aluno_id'] ?? '') == $a['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Turma <span class="required">*</span></label>
                <select name="turma_id" required>
                    <option value="">Selecione uma turma</option>
                    <?php foreach($turmas as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($_POST['turma_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Data da Matrícula <span class="required">*</span></label>
                <input type="date" name="data_matricula" value="<?= $_POST['data_matricula'] ?? date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="ativa" <?= ($_POST['status'] ?? '') == 'ativa' ? 'selected' : '' ?>>Ativa</option>
                    <option value="trancada" <?= ($_POST['status'] ?? '') == 'trancada' ? 'selected' : '' ?>>Trancada</option>
                    <option value="concluida" <?= ($_POST['status'] ?? '') == 'concluida' ? 'selected' : '' ?>>Concluída</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Observações</label>
            <textarea name="observacoes"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">✅ Registrar Matrícula</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php include '../includes/footer_escola.php'; ?>