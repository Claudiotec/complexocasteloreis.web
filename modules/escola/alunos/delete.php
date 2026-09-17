<?php
// ============================================
// modules/escola/alunos/edit.php - Editar Aluno
// ============================================

require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Verifica permissão para EDITAR
bloquearAcesso('excluir');

// Resto do código...
?>


<?php
// ============================================
// modules/escola/alunos/delete.php - Excluir Aluno
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// ============================================
// VERIFICAÇÃO DE PERMISSÃO - BLOQUEIA ACESSO DIRETO
// ============================================
if (function_exists('temPermissao')) {
    if (!temPermissao('Escola', 'excluir')) {
        // Redireciona com mensagem de erro
        $_SESSION['erro_permissao'] = 'Você não tem permissão para excluir alunos!';
        header('Location: index.php');
        exit;
    }
} else {
    // Se a função não existe, verifica manualmente
    $permitido = false;
    if (isset($_SESSION['permissoes']) && is_array($_SESSION['permissoes'])) {
        foreach ($_SESSION['permissoes'] as $permissao) {
            if ($permissao['modulo'] == 'Escola' && $permissao['acao'] == 'excluir') {
                $permitido = true;
                break;
            }
        }
    }
    if (!$permitido) {
        $_SESSION['erro_permissao'] = 'Você não tem permissão para excluir alunos!';
        header('Location: index.php');
        exit;
    }
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    header('Location: index.php');
    exit;
}

// Buscar dados do aluno antes de excluir (para log e para excluir a foto)
$aluno = null;
try {
    $pdo = conectarBanco();
    $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
    $stmt->execute([$id]);
    $aluno = $stmt->fetch();
} catch (Exception $e) {
    error_log("Erro ao buscar aluno: " . $e->getMessage());
}

if (!$aluno) {
    header('Location: index.php');
    exit;
}

// ============================================
// PROCESSAR EXCLUSÃO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo = conectarBanco();
        
        // Iniciar transação
        $pdo->beginTransaction();
        
        // 1. Excluir a foto do aluno se existir
        if (!empty($aluno['foto'])) {
            $caminhos_foto = [
                '../../../uploads/alunos/' . $aluno['foto'],
                '../../../uploads/fotos_alunos/' . $aluno['foto'],
                $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/alunos/' . $aluno['foto'],
                $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/' . $aluno['foto'],
                'C:/xampp/htdocs/softgest_web/uploads/alunos/' . $aluno['foto'],
                'C:/xampp/htdocs/softgest_web/uploads/fotos_alunos/' . $aluno['foto']
            ];
            
            foreach ($caminhos_foto as $caminho) {
                if (file_exists($caminho)) {
                    unlink($caminho);
                    break;
                }
            }
        }
        
        // 2. Tentar excluir arquivos de foto por padrão (ID.extensao, aluno_ID.extensao, etc.)
        $diretorios = [
            '../../../uploads/alunos/',
            '../../../uploads/fotos_alunos/',
            $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/alunos/',
            $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/',
            'C:/xampp/htdocs/softgest_web/uploads/alunos/',
            'C:/xampp/htdocs/softgest_web/uploads/fotos_alunos/'
        ];
        
        $padroes = [
            $id . '.*',
            'aluno_' . $id . '.*',
            'aluno_' . $id . '_*'
        ];
        
        foreach ($diretorios as $dir) {
            if (!is_dir($dir)) continue;
            
            foreach ($padroes as $padrao) {
                $arquivos = glob($dir . $padrao);
                foreach ($arquivos as $arquivo) {
                    if (file_exists($arquivo)) {
                        unlink($arquivo);
                    }
                }
            }
        }
        
        // 3. Excluir o registro do banco de dados
        $stmt = $pdo->prepare("DELETE FROM alunos WHERE id = ?");
        $stmt->execute([$id]);
        
        // Commit da transação
        $pdo->commit();
        
        // Mensagem de sucesso
        $_SESSION['sucesso_exclusao'] = 'Aluno "' . htmlspecialchars($aluno['nome']) . '" excluído com sucesso!';
        
        // Redirecionar para a lista
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Erro ao excluir aluno: " . $e->getMessage());
        $erro = "Erro ao excluir o aluno: " . $e->getMessage();
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
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
    }
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-warning:hover {
        background: #d68910;
    }
    
    .card-confirmacao {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 600px;
        margin: 0 auto;
        text-align: center;
    }
    
    .card-confirmacao .icone-aviso {
        font-size: 60px;
        margin-bottom: 15px;
    }
    
    .card-confirmacao h2 {
        font-size: 22px;
        font-weight: 700;
        color: #1a2332;
        margin: 0 0 10px 0;
    }
    
    .card-confirmacao .subtitulo {
        color: #64748b;
        font-size: 14px;
        margin-bottom: 20px;
    }
    
    .card-confirmacao .dados-aluno {
        background: #f8fafc;
        border-radius: 8px;
        padding: 15px;
        margin: 15px 0;
        text-align: left;
    }
    
    .card-confirmacao .dados-aluno .linha {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px solid #eef2f7;
        font-size: 14px;
    }
    
    .card-confirmacao .dados-aluno .linha:last-child {
        border-bottom: none;
    }
    
    .card-confirmacao .dados-aluno .label {
        font-weight: 600;
        color: #4a5568;
    }
    
    .card-confirmacao .dados-aluno .valor {
        color: #1a2332;
    }
    
    .card-confirmacao .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin: 15px 0;
        font-size: 14px;
    }
    
    .card-confirmacao .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .card-confirmacao .alert-warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    
    .card-confirmacao .botoes {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .foto-preview {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        margin: 10px auto;
        border: 3px solid #e74c3c;
        display: block;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .card-confirmacao {
            padding: 20px;
        }
        .card-confirmacao .botoes {
            flex-direction: column;
        }
        .card-confirmacao .botoes .btn {
            justify-content: center;
        }
        .card-confirmacao .dados-aluno .linha {
            flex-direction: column;
            gap: 2px;
        }
    }
</style>

<div class="page-header">
    <h1>🗑️ Excluir Aluno</h1>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="card-confirmacao">
    <div class="icone-aviso">⚠️</div>
    
    <h2>Tem certeza que deseja excluir?</h2>
    <p class="subtitulo">Esta ação é irreversível e todos os dados do aluno serão removidos permanentemente.</p>
    
    <?php if (isset($erro)): ?>
        <div class="alert alert-danger">❌ <?= $erro ?></div>
    <?php endif; ?>
    
    <div class="alert alert-warning">
        <strong>⚠️ Atenção:</strong> Ao excluir este aluno, todos os seus registros serão removidos do sistema.
    </div>
    
    <!-- Dados do Aluno -->
    <div class="dados-aluno">
        <?php 
        // Buscar foto do aluno para exibir
        $foto_exibicao = null;
        if (!empty($aluno['foto'])) {
            $caminhos_foto = [
                '../../../uploads/alunos/' . $aluno['foto'],
                '../../../uploads/fotos_alunos/' . $aluno['foto']
            ];
            foreach ($caminhos_foto as $caminho) {
                if (file_exists($caminho)) {
                    $foto_exibicao = $caminho;
                    break;
                }
            }
        }
        ?>
        
        <?php if ($foto_exibicao): ?>
            <img src="<?= $foto_exibicao ?>" alt="Foto do aluno" class="foto-preview">
        <?php endif; ?>
        
        <div class="linha">
            <span class="label">🆔 ID:</span>
            <span class="valor"><?= htmlspecialchars($aluno['id'] ?? '') ?></span>
        </div>
        <div class="linha">
            <span class="label">👤 Nome:</span>
            <span class="valor"><strong><?= htmlspecialchars($aluno['nome'] ?? '') ?></strong></span>
        </div>
        <div class="linha">
            <span class="label">🎂 Data Nascimento:</span>
            <span class="valor">
                <?php 
                $dia = $aluno['dia'] ?? 0;
                $mes = $aluno['mes'] ?? 0;
                $ano = $aluno['Ano'] ?? 0;
                if ($dia > 0 && $mes > 0 && $ano > 0) {
                    echo sprintf('%02d/%02d/%04d', $dia, $mes, $ano);
                } else {
                    echo '-';
                }
                ?>
            </span>
        </div>
        <div class="linha">
            <span class="label">🏫 Classe:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Classe'] ?? '-') ?></span>
        </div>
        <div class="linha">
            <span class="label">📚 Curso:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Curso'] ?? '-') ?></span>
        </div>
        <div class="linha">
            <span class="label">📞 Contacto:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Contacto_do_Aluno'] ?? '-') ?></span>
        </div>
        <div class="linha">
            <span class="label">📌 Situação:</span>
            <span class="valor"><?= htmlspecialchars($aluno['Situacao_Cadastro'] ?? 'Matrícula') ?></span>
        </div>
    </div>
    
    <form method="POST">
        <div class="botoes">
            <button type="submit" name="confirmar" class="btn btn-danger" 
                    onclick="return confirm('🔴 Confirmação final:\n\nDeseja realmente excluir o aluno \"<?= htmlspecialchars($aluno['nome']) ?>\"?\nEsta ação não pode ser desfeita!');">
                🗑️ Sim, Excluir Permanentemente
            </button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php include '../includes/footer_escola.php'; ?>