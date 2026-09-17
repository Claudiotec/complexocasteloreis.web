<?php
// ============================================
// modules/escola/frequencia/edit.php - Editar Frequência
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

// ============================================
// 1. RECEBE ID
// ============================================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// ============================================
// 2. BUSCAR DADOS DA FREQUÊNCIA
// ============================================
$frequencia = null;
$erro = '';

try {
    $sql = "
        SELECT f.*, a.nome AS aluno_nome, t.nome AS turma_nome
        FROM frequencia f
        LEFT JOIN alunos a ON f.aluno_id = a.id
        LEFT JOIN turmas t ON f.turma_id = t.id
        WHERE f.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $frequencia = $stmt->fetch();
    
    if (!$frequencia) {
        header('Location: index.php');
        exit;
    }
    
} catch (Exception $e) {
    $erro = 'Erro ao buscar dados: ' . $e->getMessage();
}

// ============================================
// 3. PROCESSAR FORMULÁRIO
// ============================================
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? 'presente';
    $observacao = $_POST['observacao'] ?? '';
    $data = $_POST['data'] ?? '';
    
    try {
        $sql = "UPDATE frequencia SET status = ?, observacao = ?, data = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $observacao, $data, $id]);
        
        $mensagem = 'Frequência atualizada com sucesso!';
        $tipo_mensagem = 'success';
        
        // Atualizar dados
        $stmt = $pdo->prepare("SELECT * FROM frequencia WHERE id = ?");
        $stmt->execute([$id]);
        $frequencia = $stmt->fetch();
        
    } catch (Exception $e) {
        $mensagem = 'Erro ao atualizar: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// ============================================
// 4. INCLUIR HEADER
// ============================================
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
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    .btn-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201,168,76,0.3);
    }
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    .btn-success:hover {
        background: #27ae60;
    }
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    .btn-danger:hover {
        background: #c0392b;
    }
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    .btn-warning:hover {
        background: #d68910;
    }
    
    .card {
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        padding: 25px;
        max-width: 600px;
        margin: 0 auto;
    }
    .card .info-row {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .card .info-row .label {
        font-weight: 600;
        color: #4a5568;
        width: 120px;
        flex-shrink: 0;
    }
    .card .info-row .value {
        color: #1a2332;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        font-size: 13px;
        color: #4a5568;
        margin-bottom: 5px;
    }
    .form-group select,
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        transition: border-color 0.3s;
    }
    .form-group select:focus,
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .alert {
        padding: 12px 18px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-presente { background: #d1fae5; color: #065f46; }
    .status-ausente { background: #fee2e2; color: #991b1b; }
    .status-justificado { background: #fef3c7; color: #92400e; }
    .status-atrasado { background: #dbeafe; color: #1e40af; }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        justify-content: flex-end;
    }
    
    @media (max-width: 768px) {
        .card {
            padding: 15px;
        }
        .card .info-row {
            flex-direction: column;
            gap: 5px;
        }
        .card .info-row .label {
            width: 100%;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
        }
    }
</style>

<!-- ===== PAGE HEADER ===== -->
<div class="page-header">
    <div>
        <h1>✏️ Editar Frequência</h1>
        <p class="subtitle">Altere o registro de presença</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- ===== MENSAGEM ===== -->
<?php if (!empty($mensagem)): ?>
    <div class="alert alert-<?= $tipo_mensagem ?>">
        <?= htmlspecialchars($mensagem) ?>
    </div>
<?php endif; ?>

<!-- ===== FORMULÁRIO ===== -->
<div class="card">
    <?php if ($frequencia): ?>
        <!-- Informações fixas -->
        <div class="info-row">
            <span class="label">ID:</span>
            <span class="value">#<?= $frequencia['id'] ?></span>
        </div>
        <div class="info-row">
            <span class="label">Aluno:</span>
            <span class="value"><strong><?= htmlspecialchars($frequencia['aluno_nome'] ?? 'Aluno #' . $frequencia['aluno_id']) ?></strong></span>
        </div>
        <div class="info-row">
            <span class="label">Turma:</span>
            <span class="value"><?= htmlspecialchars($frequencia['turma_nome'] ?? 'Turma #' . $frequencia['turma_id']) ?></span>
        </div>
        
        <hr>
        
        <!-- Formulário -->
        <form method="POST">
            <div class="form-group">
                <label for="data">Data</label>
                <input type="date" id="data" name="data" value="<?= $frequencia['data'] ?>" required>
            </div>
            
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="presente" <?= $frequencia['status'] == 'presente' ? 'selected' : '' ?>>✅ Presente</option>
                    <option value="ausente" <?= $frequencia['status'] == 'ausente' ? 'selected' : '' ?>>❌ Ausente</option>
                    <option value="justificado" <?= $frequencia['status'] == 'justificado' ? 'selected' : '' ?>>📋 Justificado</option>
                    <option value="atrasado" <?= $frequencia['status'] == 'atrasado' ? 'selected' : '' ?>>⏰ Atrasado</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="observacao">Observação</label>
                <textarea id="observacao" name="observacao" placeholder="Digite uma observação (opcional)"><?= htmlspecialchars($frequencia['observacao'] ?? '') ?></textarea>
            </div>
            
            <div class="form-actions">
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-danger">Registro não encontrado.</div>
    <?php endif; ?>
</div>

<?php include '../includes/footer_escola.php'; ?>