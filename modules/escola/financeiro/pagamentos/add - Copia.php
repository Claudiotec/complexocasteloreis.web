<?php
// ============================================
// modules/escola/financeiro/pagamentos/add.php - Novo Pagamento
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
if (!temPermissao('Escola', 'criar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== BUSCAR DADOS =====
$alunos = [];
$emolumentos = [];
$aluno_selecionado = null;

try {
    // Buscar todos os alunos ativos
    $alunos = $pdo->query("SELECT id, nome, matricula, cpf FROM alunos WHERE status = 'ativo' ORDER BY nome")->fetchAll();
    $emolumentos = $pdo->query("SELECT id, nome, valor FROM emolumentos WHERE status = 'ativo' ORDER BY nome")->fetchAll();
} catch (Exception $e) {}

// ===== PROCESSAR FORMULÁRIO =====
$erro = '';
$sucesso = '';
$aluno_busca = $_GET['busca'] ?? '';

// Se houver busca por aluno
if (!empty($aluno_busca)) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, matricula, cpf 
            FROM alunos 
            WHERE status = 'ativo' 
            AND (nome LIKE ? OR id = ? OR matricula LIKE ?)
            ORDER BY nome 
            LIMIT 10
        ");
        $busca_param = "%{$aluno_busca}%";
        $stmt->execute([$busca_param, $aluno_busca, $busca_param]);
        $alunos_busca = $stmt->fetchAll();
        
        // Se encontrou exatamente um aluno, selecionar automaticamente
        if (count($alunos_busca) == 1) {
            $aluno_selecionado = $alunos_busca[0];
        }
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $aluno_id = $_POST['aluno_id'] ?? null;
    $emolumento_id = $_POST['emolumento_id'] ?? null;
    $valor = $_POST['valor'] ?? 0;
    $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    $forma_pagamento = $_POST['forma_pagamento'] ?? 'dinheiro';
    $referencia = $_POST['referencia'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';

    if (!$aluno_id || !$emolumento_id || $valor <= 0) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pagamentos (aluno_id, emolumento_id, valor, data_pagamento, forma_pagamento, referencia, observacoes, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmado')
            ");
            $stmt->execute([$aluno_id, $emolumento_id, $valor, $data_pagamento, $forma_pagamento, $referencia, $observacoes]);
            
            $sucesso = 'Pagamento registrado com sucesso!';
            
            // Limpar formulário
            $_POST = [];
            $aluno_selecionado = null;
            $_GET['busca'] = '';
        } catch (Exception $e) {
            $erro = $e->getMessage();
        }
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
    
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    
    .btn-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
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
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .btn-info:hover {
        background: #2980b9;
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
    
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 700px;
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
    
    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .info-valor {
        background: #f8fafc;
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        margin-top: 5px;
        font-size: 13px;
        color: #4a5568;
    }
    
    .info-valor strong {
        color: #1a2332;
    }
    
    .busca-aluno-container {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .busca-aluno-container .busca-row {
        display: flex;
        gap: 10px;
    }
    
    .busca-aluno-container .busca-row input {
        flex: 1;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .busca-aluno-container .busca-row input:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
    }
    
    .busca-aluno-container .busca-row .btn {
        padding: 10px 20px;
        white-space: nowrap;
    }
    
    .resultado-busca {
        margin-top: 12px;
        border-top: 1px solid #e2e8f0;
        padding-top: 12px;
    }
    
    .resultado-busca .aluno-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        background: white;
        border-radius: 6px;
        margin-bottom: 6px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .resultado-busca .aluno-item:hover {
        border-color: #c9a84c;
        background: #fef9e8;
    }
    
    .resultado-busca .aluno-item .info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .resultado-busca .aluno-item .info .nome {
        font-weight: 600;
        color: #1a2332;
    }
    
    .resultado-busca .aluno-item .info .detalhes {
        font-size: 12px;
        color: #94a3b8;
    }
    
    .resultado-busca .aluno-item .selecionar-btn {
        padding: 4px 12px;
        border-radius: 4px;
        background: #c9a84c;
        color: #1a2332;
        border: none;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .resultado-busca .aluno-item .selecionar-btn:hover {
        background: #b8973a;
    }
    
    .aluno-selecionado {
        background: #d1fae5 !important;
        border-color: #2ecc71 !important;
    }
    
    .aluno-selecionado .selecionar-btn {
        background: #2ecc71 !important;
        color: #fff !important;
    }
    
    .aluno-selecionado .selecionar-btn:hover {
        background: #27ae60 !important;
    }
    
    .nenhum-resultado {
        text-align: center;
        padding: 15px;
        color: #94a3b8;
        font-size: 14px;
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
        .busca-aluno-container .busca-row {
            flex-direction: column;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>💳 Novo Pagamento</h1>
        <p class="subtitle">Registrar novo pagamento escolar</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="index.php" class="active">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<?php if ($sucesso): ?>
<div class="alert alert-success">✅ <?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert-error">❌ <?= $erro ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <!-- Busca de Alunos -->
        <div class="busca-aluno-container">
            <div class="busca-row">
                <input type="text" 
                       id="busca_aluno" 
                       name="busca" 
                       placeholder="🔍 Buscar aluno por nome, ID ou matrícula..." 
                       value="<?= htmlspecialchars($aluno_busca) ?>"
                       autocomplete="off">
                <button type="submit" class="btn btn-primary" formmethod="GET">Buscar</button>
                <?php if (!empty($aluno_busca)): ?>
                <a href="add.php" class="btn btn-secondary">Limpar</a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($aluno_busca) && isset($alunos_busca)): ?>
            <div class="resultado-busca">
                <?php if (count($alunos_busca) > 0): ?>
                    <?php foreach($alunos_busca as $aluno): ?>
                    <div class="aluno-item <?= ($aluno_selecionado && $aluno_selecionado['id'] == $aluno['id']) ? 'aluno-selecionado' : '' ?>">
                        <div class="info">
                            <span class="nome"><?= htmlspecialchars($aluno['nome']) ?></span>
                            <span class="detalhes">
                                ID: <?= $aluno['id'] ?> | 
                                Matrícula: <?= htmlspecialchars($aluno['matricula'] ?? 'N/A') ?> | 
                                CPF: <?= htmlspecialchars($aluno['cpf'] ?? 'N/A') ?>
                            </span>
                        </div>
                        <button type="button" class="selecionar-btn" onclick="selecionarAluno(<?= $aluno['id'] ?>, '<?= addslashes($aluno['nome']) ?>')">
                            Selecionar
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="nenhum-resultado">
                        Nenhum aluno encontrado com "<?= htmlspecialchars($aluno_busca) ?>"
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Aluno Selecionado (hidden) -->
        <div class="form-group">
            <label>Aluno <span class="required">*</span></label>
            <select name="aluno_id" id="aluno_id" required>
                <option value="">Selecione um aluno</option>
                <?php foreach($alunos as $aluno): ?>
                <option value="<?= $aluno['id'] ?>" <?= ($_POST['aluno_id'] ?? $aluno_selecionado['id'] ?? '') == $aluno['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($aluno['nome']) ?> (ID: <?= $aluno['id'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($aluno_selecionado): ?>
            <div style="margin-top: 8px; padding: 8px 12px; background: #d1fae5; border-radius: 6px; font-size: 13px; color: #065f46;">
                ✅ Aluno selecionado: <strong><?= htmlspecialchars($aluno_selecionado['nome']) ?></strong> 
                (ID: <?= $aluno_selecionado['id'] ?>, Matrícula: <?= htmlspecialchars($aluno_selecionado['matricula'] ?? 'N/A') ?>)
            </div>
            <?php endif; ?>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Emolumento <span class="required">*</span></label>
                <select name="emolumento_id" id="emolumento_id" required onchange="atualizarValor()">
                    <option value="">Selecione um emolumento</option>
                    <?php foreach($emolumentos as $emolumento): ?>
                    <option value="<?= $emolumento['id'] ?>" data-valor="<?= $emolumento['valor'] ?>" <?= ($_POST['emolumento_id'] ?? '') == $emolumento['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emolumento['nome']) ?> - R$ <?= number_format($emolumento['valor'], 2, ',', '.') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Valor <span class="required">*</span></label>
                <input type="number" step="0.01" name="valor" id="valor" value="<?= $_POST['valor'] ?? '' ?>" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Data do Pagamento <span class="required">*</span></label>
                <input type="date" name="data_pagamento" value="<?= $_POST['data_pagamento'] ?? date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Forma de Pagamento <span class="required">*</span></label>
                <select name="forma_pagamento" required>
                    <option value="dinheiro" <?= ($_POST['forma_pagamento'] ?? '') == 'dinheiro' ? 'selected' : '' ?>>💰 Dinheiro</option>
                    <option value="cartao" <?= ($_POST['forma_pagamento'] ?? '') == 'cartao' ? 'selected' : '' ?>>💳 Cartão</option>
                    <option value="transferencia" <?= ($_POST['forma_pagamento'] ?? '') == 'transferencia' ? 'selected' : '' ?>>🏦 Transferência</option>
                    <option value="pix" <?= ($_POST['forma_pagamento'] ?? '') == 'pix' ? 'selected' : '' ?>>📱 Pix</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Referência</label>
                <input type="text" name="referencia" placeholder="Nº do comprovante, recibo..." value="<?= htmlspecialchars($_POST['referencia'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" placeholder="Observações sobre o pagamento"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
            </div>
        </div>
        
        <div class="info-valor">
            💡 O pagamento será registrado como <strong>confirmado</strong> automaticamente.
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">✅ Registrar Pagamento</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
    function atualizarValor() {
        const select = document.getElementById('emolumento_id');
        const valorInput = document.getElementById('valor');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption.value) {
            const valor = parseFloat(selectedOption.dataset.valor) || 0;
            valorInput.value = valor.toFixed(2);
        } else {
            valorInput.value = '';
        }
    }
    
    function selecionarAluno(id, nome) {
        // Selecionar no dropdown
        const select = document.getElementById('aluno_id');
        for (let option of select.options) {
            if (option.value == id) {
                option.selected = true;
                break;
            }
        }
        
        // Atualizar visualização
        const container = document.querySelector('.busca-aluno-container');
        const items = container.querySelectorAll('.aluno-item');
        items.forEach(item => {
            item.classList.remove('aluno-selecionado');
        });
        
        // Marcar item como selecionado
        const selectedItem = Array.from(items).find(item => 
            item.querySelector('.selecionar-btn').onclick.toString().includes(id)
        );
        if (selectedItem) {
            selectedItem.classList.add('aluno-selecionado');
        }
        
        // Mostrar feedback
        const feedbackDiv = document.querySelector('.form-group .aluno-selecionado-feedback') || 
                           document.createElement('div');
        feedbackDiv.className = 'aluno-selecionado-feedback';
        feedbackDiv.style.cssText = 'margin-top: 8px; padding: 8px 12px; background: #d1fae5; border-radius: 6px; font-size: 13px; color: #065f46;';
        feedbackDiv.innerHTML = `✅ Aluno selecionado: <strong>${nome}</strong> (ID: ${id})`;
        
        const parentGroup = document.querySelector('.form-group');
        const existingFeedback = parentGroup.querySelector('.aluno-selecionado-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        parentGroup.appendChild(feedbackDiv);
    }
    
    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        atualizarValor();
        
        // Se já houver um aluno selecionado, destacar
        const select = document.getElementById('aluno_id');
        if (select.value) {
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const nome = selectedOption.text.replace(/ \(ID: \d+\)$/, '');
                const id = parseInt(selectedOption.value);
                // Tentar destacar na busca
                const items = document.querySelectorAll('.aluno-item');
                items.forEach(item => {
                    const btn = item.querySelector('.selecionar-btn');
                    if (btn && btn.onclick.toString().includes(id)) {
                        item.classList.add('aluno-selecionado');
                    }
                });
            }
        }
    });
    
    // Busca automática com debounce
    let timeoutId = null;
    document.getElementById('busca_aluno')?.addEventListener('input', function() {
        clearTimeout(timeoutId);
        const value = this.value.trim();
        
        if (value.length >= 2) {
            timeoutId = setTimeout(() => {
                // Criar formulário de busca e submeter via GET
                const form = document.createElement('form');
                form.method = 'GET';
                form.action = window.location.pathname;
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'busca';
                input.value = value;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }, 500);
        }
    });
</script>

<?php include '../../includes/footer_escola.php'; ?>