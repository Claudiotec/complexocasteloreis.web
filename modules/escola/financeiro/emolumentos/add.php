<?php
// ============================================
// modules/escola/financeiro/emolumentos/add.php - Novo Emolumento (COMPLETO)
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

// ===== PROCESSAR FORMULÁRIO =====
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'] ?? '';
    $classe = $_POST['classe'] ?? '1ª';
    $descricao = $_POST['descricao'] ?? '';
    $mes_referencia = $_POST['mes_referencia'] ?? date('m');
    $ano_referencia = $_POST['ano_referencia'] ?? date('Y');
    $valor = $_POST['valor'] ?? 0;
    $multa_tipo = $_POST['multa_tipo'] ?? 'percentual';
    $multa_valor = $_POST['multa_valor'] ?? 0;
    $prazo_dias = $_POST['prazo_dias'] ?? 30;
    $mes_inicio_multa = $_POST['mes_inicio_multa'] ?? date('m');
    $ano_inicio_multa = $_POST['ano_inicio_multa'] ?? date('Y');
    $tipo = $_POST['tipo'] ?? 'Outros';
    $status = $_POST['status'] ?? 'ativo';
    $marcar_todos_meses = isset($_POST['marcar_todos_meses']) ? 1 : 0;

    if (empty($nome) || $valor <= 0) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        try {
            // Se marcar todos os meses e for Propina ou Transporte
            if ($marcar_todos_meses && in_array($tipo, ['Propina', 'Transporte'])) {
                $meses = range(1, 12);
                $count = 0;
                
                foreach ($meses as $mes) {
                    $stmt = $pdo->prepare("
                        INSERT INTO emolumentos (
                            nome, classe, descricao, mes_referencia, ano_referencia, 
                            valor, multa_tipo, multa_valor, prazo_dias, 
                            mes_inicio_multa, ano_inicio_multa, tipo, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $nome, 
                        $classe, 
                        $descricao, 
                        $mes, 
                        $ano_referencia, 
                        $valor, 
                        $multa_tipo, 
                        $multa_valor, 
                        $prazo_dias, 
                        $mes_inicio_multa, 
                        $ano_inicio_multa, 
                        $tipo, 
                        $status
                    ]);
                    $count++;
                }
                
                $sucesso = "✅ $count emolumentos cadastrados com sucesso (todos os meses de $ano_referencia)!";
                $_POST = [];
            } else {
                // Cadastro normal de um único emolumento
                $stmt = $pdo->prepare("
                    INSERT INTO emolumentos (
                        nome, classe, descricao, mes_referencia, ano_referencia, 
                        valor, multa_tipo, multa_valor, prazo_dias, 
                        mes_inicio_multa, ano_inicio_multa, tipo, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $nome, 
                    $classe, 
                    $descricao, 
                    $mes_referencia, 
                    $ano_referencia, 
                    $valor, 
                    $multa_tipo, 
                    $multa_valor, 
                    $prazo_dias, 
                    $mes_inicio_multa, 
                    $ano_inicio_multa, 
                    $tipo, 
                    $status
                ]);
                
                $sucesso = 'Emolumento cadastrado com sucesso!';
                $_POST = [];
            }
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
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-warning:hover {
        background: #e67e22;
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
        max-width: 800px;
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
        box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
    }
    
    .form-group textarea {
        min-height: 60px;
        resize: vertical;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
    }
    
    .form-row-2 {
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
    
    .info-box {
        background: #f8fafc;
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        margin-top: 5px;
        font-size: 13px;
        color: #4a5568;
    }
    
    .info-box strong {
        color: #1a2332;
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: #fef9e7;
        border: 2px solid #f9e79f;
        border-radius: 8px;
        margin-top: 5px;
    }
    
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #c9a84c;
    }
    
    .checkbox-group label {
        font-weight: 600;
        color: #1a2332;
        cursor: pointer;
        margin: 0;
    }
    
    .checkbox-group .badge {
        background: #c9a84c;
        color: #1a2332;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
    }
    
    .checkbox-group .warning-text {
        color: #7d6608;
        font-size: 12px;
    }
    
    .checkbox-group.disabled {
        opacity: 0.5;
        background: #f4f6f7;
        border-color: #d5d8dc;
        cursor: not-allowed;
    }
    
    .checkbox-group.disabled input[type="checkbox"],
    .checkbox-group.disabled label {
        cursor: not-allowed;
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
        .form-row-2 {
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
    }
</style>

<div class="page-header">
    <div>
        <h1>📋 Novo Emolumento</h1>
        <p class="subtitle">Cadastrar nova taxa, mensalidade ou emolumento</p>
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
<div class="alert alert-success">✅ <?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert-error">❌ <?= $erro ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" id="formEmolumento">
        <!-- ===== DADOS BÁSICOS ===== -->
        <div class="section-title">
            <span class="icon">📌</span> Dados Básicos
        </div>
        
        <div class="form-group">
            <label>Nome do Emolumento <span class="required">*</span></label>
            <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" placeholder="Ex: Mensalidade 6º Ano" required>
        </div>
        
        <div class="form-row-2">
            <div class="form-group">
                <label>Classe / Categoria <span class="required">*</span></label>
                <select name="classe" required>
                    <option value="">Selecione...</option>
                    <?php for($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>ª" <?= ($_POST['classe'] ?? '') == $i.'ª' ? 'selected' : '' ?>>
                            <?= $i ?>ª
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Tipo <span class="required">*</span></label>
                <select name="tipo" id="tipo" required>
                    <option value="">Selecione...</option>
                    <option value="Propina" <?= ($_POST['tipo'] ?? '') == 'Propina' ? 'selected' : '' ?>>Propina</option>
                    <option value="Transporte" <?= ($_POST['tipo'] ?? '') == 'Transporte' ? 'selected' : '' ?>>Transporte</option>
                    <option value="Matrícula" <?= ($_POST['tipo'] ?? '') == 'Matrícula' ? 'selected' : '' ?>>Matrícula</option>
                    <option value="Uniforme Diário" <?= ($_POST['tipo'] ?? '') == 'Uniforme Diário' ? 'selected' : '' ?>>Uniforme Diário</option>
                    <option value="Uniforme de Ed. Física" <?= ($_POST['tipo'] ?? '') == 'Uniforme de Ed. Física' ? 'selected' : '' ?>>Uniforme de Ed. Física</option>
                    <option value="Cartão" <?= ($_POST['tipo'] ?? '') == 'Cartão' ? 'selected' : '' ?>>Cartão</option>
                    <option value="Confirmação" <?= ($_POST['tipo'] ?? '') == 'Confirmação' ? 'selected' : '' ?>>Confirmação</option>
                    <option value="Folha de Prova" <?= ($_POST['tipo'] ?? '') == 'Folha de Prova' ? 'selected' : '' ?>>Folha de Prova</option>
                    <option value="Boletim de notas" <?= ($_POST['tipo'] ?? '') == 'Boletim de notas' ? 'selected' : '' ?>>Boletim de notas</option>
                    <option value="Outros" <?= ($_POST['tipo'] ?? '') == 'Outros' ? 'selected' : '' ?>>Outros</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Descrição Detalhada</label>
            <textarea name="descricao" placeholder="Descreva detalhadamente o emolumento"><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
        </div>
        
        <!-- ===== MARCADOR DE TODOS OS MESES ===== -->
        <div class="form-group" id="marcarMesesGroup" style="display: none;">
            <label>Opções de Cadastro</label>
            <div class="checkbox-group" id="checkboxMarcarMeses">
                <input type="checkbox" name="marcar_todos_meses" id="marcarTodosMeses" value="1" <?= isset($_POST['marcar_todos_meses']) ? 'checked' : '' ?>>
                <label for="marcarTodosMeses">
                    📅 Marcar todos os meses
                    <span class="badge">12 meses</span>
                </label>
                <span class="warning-text">Criar emolumentos para todos os meses do ano selecionado</span>
            </div>
            <span class="help">Disponível apenas para Propina e Transporte</span>
        </div>
        
        <!-- ===== MÊS DE REFERÊNCIA ===== -->
        <div class="section-title">
            <span class="icon">📅</span> Mês de Referência
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Mês de Referência <span class="required">*</span></label>
                <select name="mes_referencia" id="mesReferencia" required>
                    <?php for($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($_POST['mes_referencia'] ?? date('m')) == $m ? 'selected' : '' ?>>
                        <?= strftime('%B', mktime(0,0,0,$m,1,2000)) ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <span class="help">Mês a que se refere este emolumento</span>
            </div>
            
            <div class="form-group">
                <label>Ano de Referência <span class="required">*</span></label>
                <select name="ano_referencia" id="anoReferencia" required>
                    <?php for($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
                    <option value="<?= $a ?>" <?= ($_POST['ano_referencia'] ?? date('Y')) == $a ? 'selected' : '' ?>>
                        <?= $a ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <span class="help">Ano a que se refere este emolumento</span>
            </div>
            
            <div class="form-group">
                <label>Valor <span class="required">*</span></label>
                <input type="number" step="0.01" name="valor" id="valor" value="<?= $_POST['valor'] ?? '' ?>" placeholder="0.00" required>
                <span class="help">Valor base do emolumento</span>
            </div>
        </div>
        
        <!-- ===== MULTA E PRAZO ===== -->
        <div class="section-title">
            <span class="icon">⚠️</span> Multa e Prazo
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Tipo de Multa <span class="required">*</span></label>
                <select name="multa_tipo" id="multaTipo" required>
                    <option value="percentual" <?= ($_POST['multa_tipo'] ?? 'percentual') == 'percentual' ? 'selected' : '' ?>>📊 Percentual (%)</option>
                    <option value="fixo" <?= ($_POST['multa_tipo'] ?? '') == 'fixo' ? 'selected' : '' ?>>💰 Valor Fixo (R$)</option>
                </select>
                <span class="help">Como a multa será calculada</span>
            </div>
            
            <div class="form-group">
                <label>Valor da Multa <span class="required">*</span></label>
                <input type="number" step="0.01" name="multa_valor" id="multaValor" value="<?= $_POST['multa_valor'] ?? 10 ?>" placeholder="10" required>
                <span class="help" id="multaLabel">% sobre o valor</span>
            </div>
            
            <div class="form-group">
                <label>Prazo para Pagamento (dias) <span class="required">*</span></label>
                <input type="number" name="prazo_dias" value="<?= $_POST['prazo_dias'] ?? 30 ?>" min="1" required>
                <span class="help">Dias para pagamento sem multa</span>
            </div>
        </div>
        
        <!-- ===== MÊS DE INÍCIO DA MULTA ===== -->
        <div class="section-title">
            <span class="icon">📆</span> Mês de Início da Multa
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Mês de Início da Multa <span class="required">*</span></label>
                <select name="mes_inicio_multa" id="mesInicioMulta" required>
                    <?php for($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($_POST['mes_inicio_multa'] ?? date('m')) == $m ? 'selected' : '' ?>>
                        <?= strftime('%B', mktime(0,0,0,$m,1,2000)) ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <span class="help">Mês a partir do qual a multa começa a contar</span>
            </div>
            
            <div class="form-group">
                <label>Ano de Início da Multa <span class="required">*</span></label>
                <select name="ano_inicio_multa" id="anoInicioMulta" required>
                    <?php for($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
                    <option value="<?= $a ?>" <?= ($_POST['ano_inicio_multa'] ?? date('Y')) == $a ? 'selected' : '' ?>>
                        <?= $a ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <span class="help">Ano a partir do qual a multa começa a contar</span>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="ativo" <?= ($_POST['status'] ?? '') == 'ativo' ? 'selected' : '' ?>>🟢 Ativo</option>
                    <option value="inativo" <?= ($_POST['status'] ?? '') == 'inativo' ? 'selected' : '' ?>>🔴 Inativo</option>
                </select>
                <span class="help">Situação do emolumento</span>
            </div>
        </div>
        
        <!-- ===== RESUMO ===== -->
        <div class="section-title">
            <span class="icon">📊</span> Resumo do Emolumento
        </div>
        
        <div class="info-box" id="resumoEmolumento">
            <p><strong>Nome:</strong> <span id="resumoNome">-</span></p>
            <p><strong>Classe:</strong> <span id="resumoClasse">-</span></p>
            <p><strong>Tipo:</strong> <span id="resumoTipo">-</span></p>
            <p><strong>Valor:</strong> <span id="resumoValor">R$ 0,00</span></p>
            <p><strong>Multa:</strong> <span id="resumoMulta">-</span></p>
            <p><strong>Prazo:</strong> <span id="resumoPrazo">-</span></p>
            <p><strong>Mês Referência:</strong> <span id="resumoMes">-</span>/<span id="resumoAno">-</span></p>
            <p><strong>Mês Início Multa:</strong> <span id="resumoMesMulta">-</span>/<span id="resumoAnoMulta">-</span></p>
            <p id="resumoMarcarMeses" style="color: #c9a84c; font-weight: 700; display: none;">
                ⭐ Serão criados emolumentos para todos os 12 meses do ano
            </p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success" id="btnSubmit">✅ Cadastrar Emolumento</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
    // Atualizar label da multa
    document.getElementById('multaTipo').addEventListener('change', function() {
        const label = document.getElementById('multaLabel');
        if (this.value == 'percentual') {
            label.textContent = '% sobre o valor';
        } else {
            label.textContent = 'Valor fixo em R$';
        }
        atualizarResumo();
    });

    // Mostrar/esconder opção de marcar todos os meses
    document.getElementById('tipo').addEventListener('change', function() {
        const group = document.getElementById('marcarMesesGroup');
        const checkbox = document.getElementById('marcarTodosMeses');
        const isPropinaOuTransporte = ['Propina', 'Transporte'].includes(this.value);
        
        if (isPropinaOuTransporte) {
            group.style.display = 'block';
            document.getElementById('checkboxMarcarMeses').classList.remove('disabled');
            checkbox.disabled = false;
            document.querySelector('#checkboxMarcarMeses label').style.cursor = 'pointer';
        } else {
            group.style.display = 'none';
            checkbox.checked = false;
            checkbox.disabled = true;
            document.getElementById('checkboxMarcarMeses').classList.add('disabled');
            document.querySelector('#checkboxMarcarMeses label').style.cursor = 'not-allowed';
        }
        atualizarResumo();
    });

    // Atualizar resumo quando marcar todos os meses mudar
    document.getElementById('marcarTodosMeses').addEventListener('change', function() {
        atualizarResumo();
        // Atualizar texto do botão
        const btn = document.getElementById('btnSubmit');
        if (this.checked) {
            btn.innerHTML = '✅ Cadastrar para Todos os Meses (12x)';
            btn.classList.add('btn-warning');
            btn.classList.remove('btn-success');
        } else {
            btn.innerHTML = '✅ Cadastrar Emolumento';
            btn.classList.remove('btn-warning');
            btn.classList.add('btn-success');
        }
    });

    // Função para atualizar o resumo em tempo real
    function atualizarResumo() {
        const nome = document.querySelector('input[name="nome"]').value || '-';
        const classe = document.querySelector('select[name="classe"]').value || '-';
        const tipoSelect = document.getElementById('tipo');
        const tipo = tipoSelect ? tipoSelect.options[tipoSelect.selectedIndex].text : '-';
        const valor = parseFloat(document.querySelector('input[name="valor"]').value) || 0;
        const multaTipo = document.querySelector('select[name="multa_tipo"]').value || 'percentual';
        const multaValor = parseFloat(document.querySelector('input[name="multa_valor"]').value) || 0;
        const prazoDias = document.querySelector('input[name="prazo_dias"]').value || '-';
        
        const mesRef = document.querySelector('select[name="mes_referencia"]');
        const mesRefText = mesRef ? mesRef.options[mesRef.selectedIndex].text : '-';
        const anoRef = document.querySelector('select[name="ano_referencia"]').value || '-';
        
        const mesMulta = document.querySelector('select[name="mes_inicio_multa"]');
        const mesMultaText = mesMulta ? mesMulta.options[mesMulta.selectedIndex].text : '-';
        const anoMulta = document.querySelector('select[name="ano_inicio_multa"]').value || '-';
        
        const marcarTodos = document.getElementById('marcarTodosMeses').checked;
        
        document.getElementById('resumoNome').textContent = nome;
        document.getElementById('resumoClasse').textContent = classe;
        document.getElementById('resumoTipo').textContent = tipo;
        document.getElementById('resumoValor').textContent = 'R$ ' + valor.toFixed(2).replace('.', ',');
        
        let multaTexto = '';
        if (multaTipo == 'percentual') {
            multaTexto = multaValor + '% sobre o valor';
        } else {
            multaTexto = 'R$ ' + multaValor.toFixed(2).replace('.', ',') + ' (fixo)';
        }
        document.getElementById('resumoMulta').textContent = multaTexto;
        document.getElementById('resumoPrazo').textContent = prazoDias + ' dias';
        document.getElementById('resumoMes').textContent = mesRefText;
        document.getElementById('resumoAno').textContent = anoRef;
        document.getElementById('resumoMesMulta').textContent = mesMultaText;
        document.getElementById('resumoAnoMulta').textContent = anoMulta;
        
        const resumoMarcar = document.getElementById('resumoMarcarMeses');
        if (marcarTodos && ['Propina', 'Transporte'].includes(tipoSelect.value)) {
            resumoMarcar.style.display = 'block';
            resumoMarcar.textContent = '⭐ Serão criados emolumentos para todos os 12 meses de ' + anoRef;
        } else {
            resumoMarcar.style.display = 'none';
        }
    }
    
    // Adicionar eventos para atualizar o resumo
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', atualizarResumo);
            input.addEventListener('change', atualizarResumo);
        });
        
        // Disparar o evento de mudança no tipo para configurar o estado inicial
        const tipoSelect = document.getElementById('tipo');
        if (tipoSelect) {
            tipoSelect.dispatchEvent(new Event('change'));
        }
        
        atualizarResumo();
    });
</script>

<?php include '../../includes/footer_escola.php'; ?>