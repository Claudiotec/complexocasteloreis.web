<?php
// ============================================
// modules/escola/financeiro/emolumentos/delete.php - Excluir Emolumento (COMPLETO)
// ============================================

// Carregar configurações
require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'excluir')) {
    header('Location: ' . SITE_URL);
    exit;
}

// Verificar ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php?erro=ID inválido');
    exit;
}

// Buscar dados do emolumento
$stmt = $pdo->prepare("SELECT * FROM emolumentos WHERE id = ?");
$stmt->execute([$id]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dados) {
    header('Location: index.php?erro=Emolumento não encontrado');
    exit;
}

// ===== PROCESSAR EXCLUSÃO =====
$erro = '';
$sucesso = '';
$confirmado = isset($_POST['confirmar']) && $_POST['confirmar'] == 'sim';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $confirmado) {
    try {
        // Verificar se o emolumento está sendo usado em algum lugar
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pagamentos WHERE emolumento_id = ?");
        $stmt->execute([$id]);
        $temPagamentos = $stmt->fetchColumn() > 0;
        
        if ($temPagamentos) {
            $erro = '❌ Não é possível excluir este emolumento pois existem pagamentos vinculados a ele!';
        } else {
            // Excluir o emolumento
            $stmt = $pdo->prepare("DELETE FROM emolumentos WHERE id = ?");
            $stmt->execute([$id]);
            
            $sucesso = '✅ Emolumento excluído com sucesso!';
            
            // Redirecionar após 2 segundos
            header('refresh:2;url=index.php?msg=' . urlencode($sucesso));
        }
    } catch (Exception $e) {
        $erro = '❌ Erro ao excluir: ' . $e->getMessage();
    }
}

// ===== INCLUIR HEADER =====
include '../../includes/header_escola.php';
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
    
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    
    .btn-success:hover {
        background: #27ae60;
    }
    
    .menu-financeiro {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 25px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .menu-financeiro a {
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
        color: #4a5568;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .menu-financeiro a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .menu-financeiro a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    
    .delete-container {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 700px;
        margin: 0 auto;
    }
    
    .delete-header {
        text-align: center;
        padding: 20px 0;
    }
    
    .delete-header .icon {
        font-size: 64px;
        display: block;
        margin-bottom: 10px;
    }
    
    .delete-header h2 {
        color: #e74c3c;
        font-size: 24px;
        margin: 0;
    }
    
    .delete-header p {
        color: #94a3b8;
        margin: 5px 0 0;
    }
    
    .info-box {
        background: #f8fafc;
        padding: 15px 20px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        margin: 20px 0;
    }
    
    .info-box p {
        margin: 8px 0;
        font-size: 14px;
    }
    
    .info-box strong {
        color: #1a2332;
        display: inline-block;
        width: 140px;
    }
    
    .info-box .id-display {
        color: #c9a84c;
        font-weight: 700;
    }
    
    .info-box .destaque {
        color: #e74c3c;
        font-weight: 700;
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
    
    .warning-box {
        background: #fef3c7;
        border: 2px solid #f59e0b;
        border-radius: 8px;
        padding: 16px 20px;
        margin: 20px 0;
    }
    
    .warning-box h3 {
        color: #92400e;
        margin: 0 0 10px;
        font-size: 16px;
    }
    
    .warning-box ul {
        margin: 5px 0;
        padding-left: 20px;
        color: #78350f;
    }
    
    .warning-box ul li {
        margin: 5px 0;
    }
    
    .delete-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 25px;
        flex-wrap: wrap;
    }
    
    .delete-actions .btn {
        padding: 12px 30px;
        font-size: 15px;
    }
    
    .btn-danger-lg {
        background: #e74c3c;
        color: #fff;
        padding: 12px 35px;
        font-size: 16px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 700;
    }
    
    .btn-danger-lg:hover {
        background: #c0392b;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
    }
    
    .btn-secondary-lg {
        background: #f1f5f9;
        color: #4a5568;
        padding: 12px 35px;
        font-size: 16px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-secondary-lg:hover {
        background: #e2e8f0;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .menu-financeiro {
            flex-direction: column;
            align-items: stretch;
        }
        .menu-financeiro a {
            text-align: center;
            justify-content: center;
        }
        .delete-container {
            padding: 20px;
        }
        .delete-actions {
            flex-direction: column;
            align-items: stretch;
        }
        .delete-actions .btn {
            justify-content: center;
        }
        .info-box strong {
            width: 100%;
            display: block;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>🗑️ Excluir Emolumento</h1>
        <p class="subtitle">ID: <strong><?= $dados['id'] ?></strong> - Confirmar exclusão</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="../pagamentos/">💳 Pagamentos</a>
    <a href="index.php" class="active">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<?php if ($sucesso): ?>
<div class="alert alert-success">
    <?= $sucesso ?>
    <br>
    <small>Redirecionando...</small>
</div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert-error"><?= $erro ?></div>
<?php endif; ?>

<?php if (!$sucesso): ?>
<div class="delete-container">
    
    <div class="delete-header">
        <span class="icon">⚠️</span>
        <h2>Confirmar Exclusão</h2>
        <p>Você está prestes a excluir este emolumento permanentemente</p>
    </div>
    
    <?php
    // Verificar se o emolumento está sendo usado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pagamentos WHERE emolumento_id = ?");
    $stmt->execute([$id]);
    $temPagamentos = $stmt->fetchColumn() > 0;
    ?>
    
    <?php if ($temPagamentos): ?>
        <div class="alert alert-error">
            ❌ <strong>Não é possível excluir!</strong> Este emolumento possui pagamentos vinculados.
        </div>
        <div class="warning-box">
            <h3>📌 Motivo:</h3>
            <ul>
                <li>Existem pagamentos registrados com este emolumento</li>
                <li>Para excluir, primeiro remova os pagamentos vinculados</li>
                <li>Ou altere o status para "Inativo"</li>
            </ul>
        </div>
        <div class="delete-actions">
            <a href="edit.php?id=<?= $id ?>" class="btn-secondary-lg">✏️ Editar (Desativar)</a>
            <a href="index.php" class="btn-secondary-lg">← Voltar</a>
        </div>
    <?php else: ?>
    
    <div class="info-box">
        <p><strong>ID:</strong> <span class="id-display">#<?= $dados['id'] ?></span></p>
        <p><strong>Nome:</strong> <?= htmlspecialchars($dados['nome']) ?></p>
        <p><strong>Classe:</strong> <?= $dados['classe'] ?></p>
        <p><strong>Tipo:</strong> <?= $dados['tipo'] ?></p>
        <p><strong>Valor:</strong> <span class="destaque">R$ <?= number_format($dados['valor'], 2, ',', '.') ?></span></p>
        <p><strong>Status:</strong> <?= $dados['status'] == 'ativo' ? '🟢 Ativo' : '🔴 Inativo' ?></p>
        <p><strong>Mês Referência:</strong> <?= $dados['mes_referencia'] ?>/<?= $dados['ano_referencia'] ?></p>
        <p><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($dados['created_at'])) ?></p>
    </div>
    
    <div class="warning-box">
        <h3>⚠️ Atenção!</h3>
        <ul>
            <li><strong>Esta ação não pode ser desfeita</strong></li>
            <li>O emolumento será <strong>permanentemente excluído</strong> do sistema</li>
            <li>Todos os dados relacionados serão removidos</li>
            <li>Recomendamos desativar ao invés de excluir</li>
        </ul>
    </div>
    
    <form method="POST">
        <div class="delete-actions">
            <button type="submit" name="confirmar" value="sim" class="btn-danger-lg" onclick="return confirm('Tem certeza absoluta que deseja excluir este emolumento?\n\nNome: <?= htmlspecialchars($dados['nome']) ?>\nID: <?= $dados['id'] ?>\n\nEsta ação não pode ser desfeita!')">
                🗑️ Sim, Excluir Permanentemente
            </button>
            <a href="edit.php?id=<?= $id ?>" class="btn-secondary-lg">✏️ Editar (Desativar)</a>
            <a href="index.php" class="btn-secondary-lg">← Cancelar</a>
        </div>
    </form>
    
    <?php endif; ?>
    
</div>
<?php endif; ?>

<?php include '../../includes/footer_escola.php'; ?>