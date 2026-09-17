<?php
// ============================================
// modules/escola/disciplinas/edit.php - Editar Disciplina
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para EDITAR
bloquearAcesso('editar');

// Verifica se o ID foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?erro=ID inválido');
    exit;
}

$disciplina_id = (int)$_GET['id'];
$erro = '';
$sucesso = '';

// Buscar professores (funcionários com categoria professor)
$professores = [];
try {
    $professores = $pdo->query("SELECT id, nome, categoria_actual, funcao_instituicao 
                               FROM funcionarios 
                               WHERE status = 'ativo' 
                               AND (categoria_actual LIKE '%professor%' OR funcao_instituicao LIKE '%professor%')
                               ORDER BY nome")->fetchAll();
} catch (Exception $e) {
    // Log do erro se necessário
}

// Buscar turmas para associar à disciplina
$turmas = [];
try {
    $turmas = $pdo->query("SELECT id, nome, sala, turno, ano_letivo, classe 
                           FROM turmas 
                           WHERE status = 'ativa' 
                           ORDER BY ano_letivo DESC, nome")->fetchAll();
} catch (Exception $e) {
    // Log do erro se necessário
}

// Buscar dados da disciplina
$disciplina = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM disciplinas WHERE id = ?");
    $stmt->execute([$disciplina_id]);
    $disciplina = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$disciplina) {
        header('Location: index.php?erro=Disciplina não encontrada');
        exit;
    }
} catch (Exception $e) {
    header('Location: index.php?erro=Erro ao carregar dados da disciplina');
    exit;
}

// Buscar turmas já associadas à disciplina
$turmas_associadas = [];
try {
    $stmt = $pdo->prepare("SELECT turma_id FROM disciplina_turma WHERE disciplina_id = ?");
    $stmt->execute([$disciplina_id]);
    $turmas_associadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Tabela pode não existir, ignorar
}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Verificar se campos obrigatórios foram preenchidos
        if (empty($_POST['nome'])) {
            throw new Exception('Nome da disciplina é obrigatório.');
        }

        // Iniciar transação
        $pdo->beginTransaction();

        // Atualizar disciplina
        $stmt = $pdo->prepare("UPDATE disciplinas SET 
            nome = ?,
            descricao = ?,
            carga_horaria = ?,
            professor_id = ?,
            status = ?,
            updated_at = NOW()
            WHERE id = ?");
        
        $stmt->execute([
            $_POST['nome'],
            $_POST['descricao'],
            $_POST['carga_horaria'] ?: null,
            $_POST['professor_id'] ?: null,
            $_POST['status'] ?? 'ativa',
            $disciplina_id
        ]);

        // Atualizar associações com turmas
        // Remover associações antigas
        $stmt = $pdo->prepare("DELETE FROM disciplina_turma WHERE disciplina_id = ?");
        $stmt->execute([$disciplina_id]);

        // Adicionar novas associações
        if (isset($_POST['turmas']) && is_array($_POST['turmas'])) {
            $stmt = $pdo->prepare("INSERT INTO disciplina_turma (disciplina_id, turma_id) VALUES (?, ?)");
            foreach ($_POST['turmas'] as $turma_id) {
                $stmt->execute([$disciplina_id, $turma_id]);
            }
        }

        $pdo->commit();
        $sucesso = 'Disciplina atualizada com sucesso!';
        
        // Recarregar dados da disciplina
        $stmt = $pdo->prepare("SELECT * FROM disciplinas WHERE id = ?");
        $stmt->execute([$disciplina_id]);
        $disciplina = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recarregar turmas associadas
        $stmt = $pdo->prepare("SELECT turma_id FROM disciplina_turma WHERE disciplina_id = ?");
        $stmt->execute([$disciplina_id]);
        $turmas_associadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = $e->getMessage();
    }
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
    .btn-danger {
        background: #ef4444;
        color: white;
    }
    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(239,68,68,0.3);
    }
    .form-container { 
        background: white; 
        border-radius: 12px; 
        padding: 30px; 
        box-shadow: 0 2px 10px rgba(0,0,0,0.04); 
        border: 1px solid #eef2f7; 
        max-width: 800px; 
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
        background: #fff;
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
    .info-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        background: #eef2f7;
        color: #4a5568;
        margin-left: 5px;
    }
    .info-badge.professor {
        background: #d1fae5;
        color: #065f46;
    }
    .info-badge.turma {
        background: #dbeafe;
        color: #1e40af;
    }
    .select-option-info {
        font-size: 12px;
        color: #6b7280;
        padding-left: 5px;
    }
    .checkbox-group {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
        padding: 10px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        max-height: 200px;
        overflow-y: auto;
    }
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 10px;
        border-radius: 6px;
        transition: background 0.2s;
        cursor: pointer;
    }
    .checkbox-item:hover {
        background: #e2e8f0;
    }
    .checkbox-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
    .checkbox-item label {
        cursor: pointer;
        font-weight: normal;
        margin: 0;
        font-size: 13px;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 5px;
    }
    .status-badge.ativa {
        background: #d1fae5;
        color: #065f46;
    }
    .status-badge.inativa {
        background: #fee2e2;
        color: #991b1b;
    }
    @media (max-width: 768px) {
        .form-row, .form-row-3 { 
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
        .checkbox-group {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <h1>✏️ Editar Disciplina</h1>
    <div>
        <span class="status-badge <?= $disciplina['status'] ?>">
            <?= ucfirst($disciplina['status'] ?? 'ativa') ?>
        </span>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<div class="form-container">
    <?php if ($sucesso): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Nome da Disciplina <span class="required">*</span></label>
            <input type="text" name="nome" value="<?= htmlspecialchars($disciplina['nome'] ?? '') ?>" required placeholder="Digite o nome da disciplina">
        </div>
        
        <div class="form-group">
            <label>Descrição</label>
            <textarea name="descricao" rows="3" placeholder="Descreva a disciplina"><?= htmlspecialchars($disciplina['descricao'] ?? '') ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Carga Horária (horas)</label>
                <input type="number" name="carga_horaria" value="<?= $disciplina['carga_horaria'] ?? '' ?>" min="1" placeholder="Ex: 45">
            </div>
            <div class="form-group">
                <label>Professor Responsável</label>
                <select name="professor_id">
                    <option value="">Selecione um professor</option>
                    <?php foreach($professores as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($disciplina['professor_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nome']) ?>
                            <span class="select-option-info">
                                (<?= htmlspecialchars($p['categoria_actual'] ?? $p['funcao_instituicao'] ?? 'Professor') ?>)
                            </span>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #6b7280; font-size: 12px;">
                    <span class="info-badge professor">👨‍🏫</span> Professor que leciona esta disciplina
                </small>
            </div>
        </div>
        
        <div class="form-group">
            <label>Turmas <span class="info-badge turma">ℹ️</span></label>
            <small style="display: block; color: #6b7280; font-size: 12px; margin-bottom: 8px;">
                Selecione as turmas em que esta disciplina será lecionada
            </small>
            <div class="checkbox-group">
                <?php if (empty($turmas)): ?>
                    <p style="color: #6b7280; font-size: 13px; text-align: center; padding: 20px 0;">
                        Nenhuma turma ativa encontrada.
                        <a href="../turmas/add.php" style="color: #c9a84c; text-decoration: underline;">Cadastrar turma</a>
                    </p>
                <?php else: ?>
                    <?php foreach($turmas as $t): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" 
                                   id="turma_<?= $t['id'] ?>" 
                                   name="turmas[]" 
                                   value="<?= $t['id'] ?>"
                                   <?= in_array($t['id'], $turmas_associadas) ? 'checked' : '' ?>>
                            <label for="turma_<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['nome']) ?>
                                <span class="select-option-info">
                                    (<?= htmlspecialchars($t['classe'] ?? '-') ?> - 
                                    Sala: <?= htmlspecialchars($t['sala'] ?? '-') ?>)
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="ativa" <?= ($disciplina['status'] ?? '') == 'ativa' ? 'selected' : '' ?>>Ativa</option>
                    <option value="inativa" <?= ($disciplina['status'] ?? '') == 'inativa' ? 'selected' : '' ?>>Inativa</option>
                </select>
            </div>
            <div class="form-group">
                <label>Data de Criação</label>
                <input type="text" value="<?= date('d/m/Y H:i', strtotime($disciplina['created_at'] ?? 'now')) ?>" disabled style="background: #f3f4f6; color: #6b7280;">
                <small style="color: #6b7280; font-size: 12px;">Data de registro da disciplina</small>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Atualizar Disciplina</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
            <a href="delete.php?id=<?= $disciplina_id ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta disciplina? Esta ação não pode ser desfeita.')">🗑️ Excluir</a>
        </div>
    </form>
</div>

<?php include '../includes/footer_escola.php'; ?>