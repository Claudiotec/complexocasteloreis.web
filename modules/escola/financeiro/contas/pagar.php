<?php
// ============================================
// modules/escola/financeiro/contas/pagar.php - Pagar Conta
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'criar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== VERIFICAR ID =====
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?erro=ID não informado');
    exit;
}

$id = intval($_GET['id']);

// ===== BUSCAR DADOS DA CONTA =====
try {
    $stmt = $pdo->prepare("SELECT * FROM contas WHERE id = ?");
    $stmt->execute([$id]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conta) {
        header('Location: index.php?erro=Conta não encontrada');
        exit;
    }
    
    if ($conta['status'] == 'paga') {
        header('Location: index.php?erro=Esta conta já está paga');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: index.php?erro=Erro ao buscar conta: ' . $e->getMessage());
    exit;
}

// ===== PROCESSAR PAGAMENTO =====
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    $forma_pagamento = $_POST['forma_pagamento'] ?? 'dinheiro';
    $observacoes = $_POST['observacoes'] ?? '';
    
    try {
        // ATUALIZAR A CONTA - STATUS = 'paga'
        $stmt = $pdo->prepare("
            UPDATE contas 
            SET 
                status = 'paga',
                data_pagamento = ?,
                forma_pagamento = ?,
                observacoes = CONCAT(IFNULL(observacoes, ''), ' | Pago em: ', ?, ' - ', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $data_pagamento,
            $forma_pagamento,
            $data_pagamento,
            $forma_pagamento,
            $id
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            header('Location: index.php?sucesso=Conta paga com sucesso!');
            exit;
        } else {
            $mensagem = 'Erro: Não foi possível atualizar a conta. Nenhuma linha foi afetada.';
            $tipo_mensagem = 'danger';
        }
        
    } catch (Exception $e) {
        $mensagem = 'Erro ao processar pagamento: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

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
    
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    
    .btn-success:hover {
        background: #27ae60;
        transform: translateY(-2px);
    }
    
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .form-group {
        margin-bottom: 18px;
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
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
    }
    
    .form-group textarea {
        min-height: 60px;
        resize: vertical;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .alert-warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .info-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 20px;
    }
    
    .info-box .row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    
    .info-box .label {
        font-size: 12px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-box .value {
        font-size: 15px;
        font-weight: 600;
        color: #1a2332;
    }
    
    .info-box .value.destaque {
        color: #c9a84c;
        font-size: 18px;
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
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-pendente {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-vencida {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .status-paga {
        background: #d1fae5;
        color: #065f46;
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
        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .form-container {
            padding: 20px;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
        }
        .info-box .row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>💰 Pagar Conta</h1>
        <p class="subtitle">Registrar pagamento da conta #<?= str_pad($conta['id'], 6, '0', STR_PAD_LEFT) ?></p>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="../pagamentos/">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="index.php" class="active">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<div class="form-container">
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
    <?php endif; ?>
    
    <!-- Informações da Conta -->
    <div class="info-box">
        <div class="row">
            <div>
                <div class="label">📝 Descrição</div>
                <div class="value"><?= htmlspecialchars($conta['descricao']) ?></div>
            </div>
            <div>
                <div class="label">📂 Categoria</div>
                <div class="value"><?= htmlspecialchars($conta['categoria']) ?></div>
            </div>
            <div>
                <div class="label">💵 Valor</div>
                <div class="value destaque">R$ <?= number_format($conta['valor'], 2, ',', '.') ?></div>
            </div>
            <div>
                <div class="label">📅 Vencimento</div>
                <div class="value"><?= date('d/m/Y', strtotime($conta['data_vencimento'])) ?></div>
            </div>
            <div>
                <div class="label">📊 Status</div>
                <div class="value">
                    <span class="status-badge status-<?= $conta['status'] ?? 'pendente' ?>">
                        <?= ucfirst($conta['status'] ?? 'Pendente') ?>
                    </span>
                </div>
            </div>
            <?php if (!empty($conta['fornecedor'])): ?>
            <div>
                <div class="label">🏢 Fornecedor</div>
                <div class="value"><?= htmlspecialchars($conta['fornecedor']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Formulário de Pagamento -->
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="data_pagamento">Data do Pagamento <span class="required">*</span></label>
                <input type="date" name="data_pagamento" id="data_pagamento" class="form-control" 
                       value="<?= $_POST['data_pagamento'] ?? date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="forma_pagamento">Forma de Pagamento <span class="required">*</span></label>
                <select name="forma_pagamento" id="forma_pagamento" class="form-control" required>
                    <option value="dinheiro" <?= ($_POST['forma_pagamento'] ?? '') == 'dinheiro' ? 'selected' : '' ?>>💵 Dinheiro</option>
                    <option value="transferencia" <?= ($_POST['forma_pagamento'] ?? '') == 'transferencia' ? 'selected' : '' ?>>🏦 Transferência</option>
                    <option value="pix" <?= ($_POST['forma_pagamento'] ?? '') == 'pix' ? 'selected' : '' ?>>📱 PIX</option>
                    <option value="cartao_credito" <?= ($_POST['forma_pagamento'] ?? '') == 'cartao_credito' ? 'selected' : '' ?>>💳 Cartão Crédito</option>
                    <option value="cartao_debito" <?= ($_POST['forma_pagamento'] ?? '') == 'cartao_debito' ? 'selected' : '' ?>>💳 Cartão Débito</option>
                    <option value="boleto" <?= ($_POST['forma_pagamento'] ?? '') == 'boleto' ? 'selected' : '' ?>>📄 Boleto</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="observacoes">Observações do Pagamento</label>
            <textarea name="observacoes" id="observacoes" class="form-control" 
                      placeholder="Observações sobre este pagamento"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
        </div>
        
        <div class="alert alert-warning" style="background: #fef3c7; border: 1px solid #fde68a; color: #92400e;">
            ⚠️ <strong>Atenção:</strong> Ao confirmar, esta conta será marcada como <strong>PAGA</strong> e não poderá ser desfeita.
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">✅ Confirmar Pagamento</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php include '../../includes/footer_escola.php'; ?>