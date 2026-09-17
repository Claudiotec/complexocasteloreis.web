<?php
// ============================================
// modules/escola/horarios/tempo_add.php - Adicionar Tempo
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $hora_inicio = trim($_POST['hora_inicio'] ?? '');
    $hora_fim = trim($_POST['hora_fim'] ?? '');
    $ordem = intval($_POST['ordem'] ?? 0);
    $turno = $_POST['turno'] ?? 'manha';
    $is_intervalo = isset($_POST['is_intervalo']) ? 1 : 0;
    $status = $_POST['status'] ?? 'ativo';
    
    if (empty($nome) || empty($hora_inicio) || empty($hora_fim)) {
        $erro = 'Preencha todos os campos obrigatórios.';
    } elseif ($hora_fim <= $hora_inicio) {
        $erro = 'A hora de fim deve ser posterior à hora de início.';
    } else {
        try {
            // Verificar se já existe um tempo com o mesmo nome/turno
            $stmt = $pdo->prepare("SELECT id FROM tempos WHERE nome = ? AND turno = ?");
            $stmt->execute([$nome, $turno]);
            if ($stmt->fetch()) {
                $erro = 'Já existe um tempo com este nome e turno.';
            } else {
                // Tentar inserir com is_intervalo
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO tempos (nome, hora_inicio, hora_fim, ordem, turno, is_intervalo, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$nome, $hora_inicio, $hora_fim, $ordem, $turno, $is_intervalo, $status]);
                } catch (Exception $e) {
                    // Fallback sem is_intervalo
                    $stmt = $pdo->prepare("
                        INSERT INTO tempos (nome, hora_inicio, hora_fim, ordem, turno, status)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$nome, $hora_inicio, $hora_fim, $ordem, $turno, $status]);
                }
                
                $_SESSION['sucesso_tempo'] = 'Tempo adicionado com sucesso!';
                header('Location: tempos.php');
                exit;
            }
        } catch (Exception $e) {
            $erro = 'Erro ao adicionar: ' . $e->getMessage();
        }
    }
}

include '../includes/header_escola.php';
?>

<style>
    .container-form {
        max-width: 700px;
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
    }
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
        padding: 10px 24px; border-radius: 8px; text-decoration: none; font-size: 14px;
        font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center;
        gap: 6px; border: none; cursor: pointer;
    }
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-success { background: #2ecc71; color: #fff; }
    .btn-success:hover { background: #27ae60; }
    .form-group { margin-bottom: 20px; }
    .form-group label {
        display: block; font-weight: 600; color: #4a5568; margin-bottom: 6px; font-size: 14px;
    }
    .form-group label .req { color: #e74c3c; }
    .form-group input,
    .form-group select {
        width: 100%; padding: 10px 14px; border: 1px solid #d1d5db;
        border-radius: 8px; font-size: 14px; transition: all 0.3s; box-sizing: border-box;
    }
    .form-group input:focus,
    .form-group select:focus {
        outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    .form-group .help { font-size: 12px; color: #94a3b8; margin-top: 4px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
    .checkbox-group {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 15px; background: #f8fafc; border-radius: 8px;
        border: 1px solid #e2e8f0; margin-bottom: 15px;
    }
    .checkbox-group input[type="checkbox"] {
        width: 18px; height: 18px; accent-color: #c9a84c; cursor: pointer;
    }
    .checkbox-group label {
        margin: 0; cursor: pointer; font-size: 14px; color: #1a2332;
    }
    .form-actions {
        display: flex; gap: 10px; justify-content: flex-end;
        margin-top: 25px; padding-top: 20px; border-top: 1px solid #eef2f7;
    }
    .alert { padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #e74c3c; }
    @media (max-width: 600px) {
        .form-row, .form-row-3 { grid-template-columns: 1fr; }
        .page-header { flex-direction: column; align-items: stretch; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { justify-content: center; }
    }
</style>

<div class="page-header">
    <div>
        <h1>⏱️ Novo Tempo</h1>
        <p class="subtitle">Cadastrar um novo tempo de aula</p>
    </div>
    <a href="tempos.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="container-form">
    <form method="POST" action="">
        <div class="form-group">
            <label>Nome do Tempo <span class="req">*</span></label>
            <input type="text" name="nome" required 
                   placeholder="Ex: 1º Tempo, 2º Tempo, Intervalo"
                   value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
            <div class="help">Nome que aparecerá na grade horária</div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Hora de Início <span class="req">*</span></label>
                <input type="time" name="hora_inicio" required 
                       value="<?= htmlspecialchars($_POST['hora_inicio'] ?? '07:30') ?>">
            </div>
            <div class="form-group">
                <label>Hora de Fim <span class="req">*</span></label>
                <input type="time" name="hora_fim" required 
                       value="<?= htmlspecialchars($_POST['hora_fim'] ?? '08:15') ?>">
            </div>
        </div>
        
        <div class="form-row-3">
            <div class="form-group">
                <label>Turno <span class="req">*</span></label>
                <select name="turno" required>
                    <option value="manha" <?= ($_POST['turno'] ?? '') == 'manha' ? 'selected' : '' ?>>🌅 Manhã</option>
                    <option value="tarde" <?= ($_POST['turno'] ?? '') == 'tarde' ? 'selected' : '' ?>>🌇 Tarde</option>
                    <option value="noite" <?= ($_POST['turno'] ?? '') == 'noite' ? 'selected' : '' ?>>🌙 Noite</option>
                </select>
            </div>
            <div class="form-group">
                <label>Ordem</label>
                <input type="number" name="ordem" min="1" max="99" 
                       value="<?= htmlspecialchars($_POST['ordem'] ?? '1') ?>">
                <div class="help">Posição na grade (1, 2, 3...)</div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="ativo" <?= ($_POST['status'] ?? 'ativo') == 'ativo' ? 'selected' : '' ?>>✅ Ativo</option>
                    <option value="inativo" <?= ($_POST['status'] ?? '') == 'inativo' ? 'selected' : '' ?>>⏸️ Inativo</option>
                </select>
            </div>
        </div>
        
        <div class="checkbox-group">
            <input type="checkbox" name="is_intervalo" id="is_intervalo" 
                   <?= !empty($_POST['is_intervalo']) ? 'checked' : '' ?>>
            <label for="is_intervalo">☕ Este tempo é um intervalo (não precisa de disciplina/professor)</label>
        </div>
        
        <div class="form-actions">
            <a href="tempos.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success">💾 Salvar Tempo</button>
        </div>
    </form>
</div>

<?php include '../includes/footer_escola.php'; ?>