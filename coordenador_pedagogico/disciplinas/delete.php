<?php
// ============================================
// modules/escola/disciplinas/delete.php - Excluir Disciplina
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para EXCLUIR
bloquearAcesso('excluir');

// Verifica se o ID foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?erro=ID inválido');
    exit;
}

$disciplina_id = (int)$_GET['id'];

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

// Buscar turmas associadas
$turmas_associadas = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.nome, t.classe, t.sala 
        FROM turmas t 
        INNER JOIN disciplina_turma dt ON t.id = dt.turma_id 
        WHERE dt.disciplina_id = ?
    ");
    $stmt->execute([$disciplina_id]);
    $turmas_associadas = $stmt->fetchAll();
} catch (Exception $e) {
    // Tabela pode não existir
}

// Verificar se a disciplina tem registros relacionados (exemplo: notas, frequência)
$tem_registros = false;
$quantidade_registros = 0;
try {
    // Verificar se existe tabela de notas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notas WHERE disciplina_id = ?");
    $stmt->execute([$disciplina_id]);
    $quantidade_registros += (int)$stmt->fetchColumn();
    $tem_registros = $quantidade_registros > 0;
} catch (Exception $e) {
    // Tabela pode não existir, ignorar
}

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        // Verificar se tem registros relacionados
        if ($tem_registros) {
            throw new Exception("Esta disciplina possui {$quantidade_registros} registro(s) de notas. Não pode ser excluída.");
        }

        // Iniciar transação
        $pdo->beginTransaction();

        // Remover associações com turmas
        $stmt = $pdo->prepare("DELETE FROM disciplina_turma WHERE disciplina_id = ?");
        $stmt->execute([$disciplina_id]);

        // Excluir disciplina
        $stmt = $pdo->prepare("DELETE FROM disciplinas WHERE id = ?");
        $stmt->execute([$disciplina_id]);

        $pdo->commit();
        
        $_SESSION['sucesso'] = 'Disciplina excluída com sucesso!';
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = $e->getMessage();
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
    .btn-danger {
        background: #ef4444;
        color: white;
    }
    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(239,68,68,0.3);
    }
    .btn-primary { 
        background: #c9a84c; 
        color: #1a2332; 
    }
    .btn-primary:hover { 
        background: #b8973a; 
    }
    .delete-container { 
        background: white; 
        border-radius: 12px; 
        padding: 30px; 
        box-shadow: 0 2px 10px rgba(0,0,0,0.04); 
        border: 1px solid #eef2f7; 
        max-width: 600px; 
        margin: 0 auto;
    }
    .delete-icon {
        text-align: center;
        font-size: 60px;
        margin-bottom: 20px;
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
    .alert-warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    .info-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
        margin: 15px 0;
    }
    .info-box .label {
        font-weight: 600;
        color: #4a5568;
        font-size: 13px;
    }
    .info-box .value {
        font-size: 15px;
        color: #1a2332;
        margin-top: 3px;
    }
    .info-box .value .carga-horaria {
        display: inline-block;
        background: #eef2f7;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 13px;
        color: #4a5568;
    }
    .turma-tag {
        display: inline-block;
        background: #dbeafe;
        color: #1e40af;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        margin: 2px 4px 2px 0;
    }
    .form-actions { 
        display: flex; 
        gap: 10px; 
        margin-top: 25px; 
        flex-wrap: wrap; 
        justify-content: center;
    }
    @media (max-width: 768px) {
        .delete-container { 
            padding: 20px; 
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
    <h1>🗑️ Excluir Disciplina</h1>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="delete-container">
    <div class="delete-icon">⚠️</div>
    
    <?php if (isset($erro)): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>
    
    <?php if ($tem_registros): ?>
        <div class="alert alert-warning">
            ⚠️ Esta disciplina possui <strong><?= $quantidade_registros ?></strong> registro(s) de notas associados. 
            Não pode ser excluída para manter a integridade dos dados.
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-bottom: 20px;">
        <p style="font-size: 16px; color: #1a2332; font-weight: 600;">
            Tem certeza que deseja excluir a disciplina abaixo?
        </p>
        <p style="font-size: 13px; color: #6b7280;">
            Esta ação não pode ser desfeita e todos os dados da disciplina serão removidos permanentemente.
        </p>
    </div>
    
    <div class="info-box">
        <div style="margin-bottom: 10px;">
            <div class="label">Nome da Disciplina</div>
            <div class="value"><?= htmlspecialchars($disciplina['nome']) ?></div>
        </div>
        
        <?php if (!empty($disciplina['descricao'])): ?>
            <div style="margin-bottom: 10px;">
                <div class="label">Descrição</div>
                <div class="value"><?= htmlspecialchars($disciplina['descricao']) ?></div>
            </div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div>
                <div class="label">Carga Horária</div>
                <div class="value">
                    <span class="carga-horaria">
                        <?= $disciplina['carga_horaria'] ?? 'Não definida' ?> horas
                    </span>
                </div>
            </div>
            <div>
                <div class="label">Status</div>
                <div class="value">
                    <span style="display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; background: <?= ($disciplina['status'] ?? '') == 'ativa' ? '#d1fae5' : '#fee2e2' ?>; color: <?= ($disciplina['status'] ?? '') == 'ativa' ? '#065f46' : '#991b1b' ?>">
                        <?= ucfirst($disciplina['status'] ?? 'ativa') ?>
                    </span>
                </div>
            </div>
        </div>
        
        <?php if (!empty($disciplina['professor_id'])): ?>
            <?php 
            // Buscar nome do professor
            $professor_nome = '';
            try {
                $stmt = $pdo->prepare("SELECT nome FROM funcionarios WHERE id = ?");
                $stmt->execute([$disciplina['professor_id']]);
                $professor_nome = $stmt->fetchColumn();
            } catch (Exception $e) {}
            ?>
            <div style="margin-top: 10px;">
                <div class="label">Professor Responsável</div>
                <div class="value"><?= htmlspecialchars($professor_nome ?? 'ID: ' . $disciplina['professor_id']) ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($turmas_associadas)): ?>
            <div style="margin-top: 10px;">
                <div class="label">Turmas Associadas</div>
                <div class="value" style="margin-top: 5px;">
                    <?php foreach($turmas_associadas as $t): ?>
                        <span class="turma-tag">
                            <?= htmlspecialchars($t['nome']) ?>
                            (<?= htmlspecialchars($t['classe'] ?? '-') ?>)
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($tem_registros): ?>
        <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin: 15px 0;">
            <p style="color: #991b1b; font-size: 13px; margin: 0;">
                <strong>⚠️ Atenção:</strong> Esta disciplina não pode ser excluída porque possui registros de notas associados.
                Para excluí-la, primeiro remova todos os registros de notas relacionados.
            </p>
        </div>
    <?php else: ?>
        <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin: 15px 0;">
            <p style="color: #991b1b; font-size: 13px; margin: 0;">
                <strong>⚠️ Atenção:</strong> Ao excluir esta disciplina, você perderá permanentemente todos os dados relacionados, incluindo associações com turmas.
            </p>
        </div>
        
        <form method="POST">
            <div class="form-actions">
                <button type="submit" name="confirmar" class="btn btn-danger" onclick="return confirm('Última chance! Tem certeza que deseja excluir esta disciplina?')">
                    🗑️ Confirmar Exclusão
                </button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include '../includes/footer_escola.php'; ?>