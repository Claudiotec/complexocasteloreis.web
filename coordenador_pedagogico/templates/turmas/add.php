<?php
// ============================================
// modules/escola/alunos/add.php - Cadastrar Aluno
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para CRIAR
bloquearAcesso('criar');

// Resto do código...
?>




<?php
// ============================================
// modules/escola/turmas/add.php - Cadastrar Nova Turma (COM IDADES)
// ============================================

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

// ===== CLASSES PRÉ-DEFINIDAS =====
$CLASSES_PRE_DEFINIDAS = [
    'PRÉ', '1ª', '2ª', '3ª', '4ª', '5ª', '6ª', 
    '7ª', '8ª', '9ª', '10ª', '11ª', '12ª'
];

// ===== BUSCAR DADOS =====
$professores = [];
$cursos = [];

try {
    $professores = $pdo->query("SELECT id, nome FROM professores WHERE status = 'ativo' ORDER BY nome")->fetchAll();
    $cursos = $pdo->query("SELECT DISTINCT curso FROM turmas WHERE curso IS NOT NULL AND curso != '' UNION SELECT 'Nenhum' ORDER BY curso")->fetchAll();
} catch (Exception $e) {}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'] ?? '';
    $classe = $_POST['classe'] ?? '';
    $curso = $_POST['curso'] ?? '';
    $ano_letivo = $_POST['ano_letivo'] ?? date('Y');
    $turno = $_POST['turno'] ?? 'manha';
    $sala = $_POST['sala'] ?? '';
    $limite = $_POST['limite'] ?? 30;
    $idades = $_POST['idades'] ?? '';
    $disciplinas = $_POST['disciplinas'] ?? '';
    $professor_id = $_POST['professor_id'] ?? null;
    $status = $_POST['status'] ?? 'ativa';

    if (empty($nome) || empty($classe) || empty($curso)) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        try {
            // Verificar duplicidade
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM turmas WHERE nome = ? AND classe = ? AND turno = ?");
            $stmt->execute([$nome, $classe, $turno]);
            if ($stmt->fetchColumn() > 0) {
                $erro = "Já existe uma turma com o nome '$nome', classe '$classe' e turno '$turno'";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO turmas (nome, classe, curso, ano_letivo, turno, sala, limite, idades, disciplinas, professor_id, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $classe, $curso, $ano_letivo, $turno, $sala, $limite, $idades, $disciplinas, $professor_id, $status]);
                $sucesso = 'Turma cadastrada com sucesso!';
                $_POST = [];
            }
        } catch (Exception $e) {
            $erro = $e->getMessage();
        }
    }
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
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
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
        box-shadow: 0 4px 15px rgba(201,168,76,0.3);
    }
    
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    
    .btn-success:hover {
        background: #27ae60;
    }
    
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 850px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 15px;
    }
    
    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-bottom: 15px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #1a2332;
        font-size: 13px;
    }
    
    .form-group label .required {
        color: #e74c3c;
    }
    
    .form-group label .help {
        font-weight: 400;
        color: #94a3b8;
        font-size: 11px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    
    .form-group textarea {
        min-height: 60px;
        resize: vertical;
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1a2332;
        margin: 20px 0 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid #eef2f7;
    }
    
    .section-title .icon {
        margin-right: 8px;
    }
    
    .idades-info {
        background: #f3e8ff;
        border: 1px solid #d8b4fe;
        border-radius: 8px;
        padding: 10px 14px;
        margin-top: 5px;
        font-size: 12px;
        color: #6b21a8;
    }
    
    .idades-info strong {
        color: #4c1d95;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .form-row-3 {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .form-container {
            padding: 20px;
        }
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
        }
    }
</style>

<div class="page-header">
    <h1>➕ Nova Turma</h1>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="form-container">
    <?php if ($sucesso): ?>
        <div class="alert alert-success">✅ <?= $sucesso ?></div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
        <div class="alert alert-error">❌ <?= $erro ?></div>
    <?php endif; ?>
    
    <form method="POST" id="formTurma">
        <!-- Dados Básicos -->
        <div class="section-title">
            <span class="icon">📌</span> Dados da Turma
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Nome da Turma <span class="required">*</span></label>
                <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" placeholder="Ex: 6º Ano A" required>
            </div>
            <div class="form-group">
                <label>Classe <span class="required">*</span></label>
                <select name="classe" required>
                    <option value="">Selecione uma classe</option>
                    <?php foreach($CLASSES_PRE_DEFINIDAS as $classe): ?>
                    <option value="<?= $classe ?>" <?= ($_POST['classe'] ?? '') == $classe ? 'selected' : '' ?>>
                        <?= $classe ?> Classe
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Curso <span class="required">*</span></label>
                <input type="text" name="curso" value="<?= htmlspecialchars($_POST['curso'] ?? '') ?>" placeholder="Ex: Ensino Secundário" required>
            </div>
            <div class="form-group">
                <label>Ano Letivo <span class="required">*</span></label>
                <input type="text" name="ano_letivo" value="<?= htmlspecialchars($_POST['ano_letivo'] ?? date('Y')) ?>" required>
            </div>
        </div>
        
        <div class="form-row-3">
            <div class="form-group">
                <label>Turno <span class="required">*</span></label>
                <select name="turno" required>
                    <option value="manha" <?= ($_POST['turno'] ?? '') == 'manha' ? 'selected' : '' ?>>Manhã</option>
                    <option value="tarde" <?= ($_POST['turno'] ?? '') == 'tarde' ? 'selected' : '' ?>>Tarde</option>
                    <option value="noite" <?= ($_POST['turno'] ?? '') == 'noite' ? 'selected' : '' ?>>Noite</option>
                    <option value="integral" <?= ($_POST['turno'] ?? '') == 'integral' ? 'selected' : '' ?>>Integral</option>
                </select>
            </div>
            <div class="form-group">
                <label>Sala</label>
                <input type="text" name="sala" value="<?= htmlspecialchars($_POST['sala'] ?? '') ?>" placeholder="Ex: Sala 101">
            </div>
            <div class="form-group">
                <label>Limite de Alunos <span class="required">*</span></label>
                <input type="number" name="limite" value="<?= $_POST['limite'] ?? 30 ?>" min="1" max="100" required>
            </div>
        </div>
        
        <!-- ===== IDADES COMPREENDIDAS ===== -->
        <div class="section-title">
            <span class="icon">👶</span> Idades Compreendidas
        </div>
        
        <div class="form-group">
            <label>Idades Compreendidas <span class="required">*</span></label>
            <input type="text" name="idades" value="<?= htmlspecialchars($_POST['idades'] ?? '') ?>" placeholder="Ex: 10-12 anos" required>
            <div class="idades-info">
                💡 Informe o intervalo de idades dos alunos desta turma.<br>
                <strong>Exemplos:</strong> 10-12 anos | 6-8 anos | 14-16 anos
            </div>
        </div>
        
        <!-- Disciplinas -->
        <div class="section-title">
            <span class="icon">📚</span> Disciplinas
        </div>
        
        <div class="form-group">
            <label>Disciplinas (separadas por vírgula) <span class="required">*</span></label>
            <textarea name="disciplinas" placeholder="Ex: Matemática, Português, História, Geografia, Ciências" rows="3"><?= htmlspecialchars($_POST['disciplinas'] ?? '') ?></textarea>
            <span class="help">Digite as disciplinas separadas por vírgula</span>
        </div>
        
        <!-- Dados Complementares -->
        <div class="section-title">
            <span class="icon">👨‍🏫</span> Dados Complementares
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Professor Responsável</label>
                <select name="professor_id">
                    <option value="">Selecione um professor</option>
                    <?php foreach($professores as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($_POST['professor_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="ativa" <?= ($_POST['status'] ?? '') == 'ativa' ? 'selected' : '' ?>>Ativa</option>
                    <option value="concluida" <?= ($_POST['status'] ?? '') == 'concluida' ? 'selected' : '' ?>>Concluída</option>
                    <option value="cancelada" <?= ($_POST['status'] ?? '') == 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Salvar Turma</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
    // Validar formulário antes de enviar
    document.getElementById('formTurma').addEventListener('submit', function(e) {
        const nome = document.querySelector('input[name="nome"]').value.trim();
        const classe = document.querySelector('select[name="classe"]').value;
        const curso = document.querySelector('input[name="curso"]').value.trim();
        const idades = document.querySelector('input[name="idades"]').value.trim();
        const disciplinas = document.querySelector('textarea[name="disciplinas"]').value.trim();
        
        if (!nome || !classe || !curso) {
            alert('Preencha todos os campos obrigatórios!');
            e.preventDefault();
            return false;
        }
        
        if (!idades) {
            alert('Informe o intervalo de idades compreendidas!');
            e.preventDefault();
            return false;
        }
        
        if (!disciplinas) {
            alert('Informe pelo menos uma disciplina!');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
</script>

<?php include '../includes/footer_escola.php'; ?>