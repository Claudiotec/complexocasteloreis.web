<?php
// ============================================
// modules/escola/financeiro/emolumentos/edit.php - Editar Emolumento (COMPLETO)
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
if (!temPermissao('Escola', 'editar')) {
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

// ===== PROCESSAR FORMULÁRIO =====
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'] ?? '';
    $classe = $_POST['classe'] ?? '1ª';
    $descricao = $_POST['descricao'] ?? '';
    $mes_referencia = $_POST['mes_referencia'] ?? date('m');
    $ano_referencia = $_POST['ano_referencia'] ?? date('Y');
    $valor = str_replace(['R$ ', '.', ','], ['', '', '.'], $_POST['valor'] ?? 0);
    $multa_tipo = $_POST['multa_tipo'] ?? 'percentual';
    $multa_valor = $_POST['multa_valor'] ?? 0;
    $prazo_dias = $_POST['prazo_dias'] ?? 30;
    $mes_inicio_multa = $_POST['mes_inicio_multa'] ?? date('m');
    $ano_inicio_multa = $_POST['ano_inicio_multa'] ?? date('Y');
    $tipo = $_POST['tipo'] ?? 'Outros';
    $status = $_POST['status'] ?? 'ativo';

    if (empty($nome) || $valor <= 0) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE emolumentos SET
                    nome = ?,
                    classe = ?,
                    descricao = ?,
                    mes_referencia = ?,
                    ano_referencia = ?,
                    valor = ?,
                    multa_tipo = ?,
                    multa_valor = ?,
                    prazo_dias = ?,
                    mes_inicio_multa = ?,
                    ano_inicio_multa = ?,
                    tipo = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
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
                $status,
                $id
            ]);
            
            $sucesso = '✅ Emolumento atualizado com sucesso!';
            
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT * FROM emolumentos WHERE id = ?");
            $stmt->execute([$id]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
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
    
    .info-box .id-display {
        color: #c9a84c;
        font-weight: 700;
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
        <h1>✏️ Editar Emolumento</h1>
        <p class="subtitle">ID: <strong><?= $dados['id'] ?></strong> - Editando emolumento existente</p>
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
<div class="alert alert-success"><?= $sucesso ?></div>
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
            <input type="text" name="nome" value="<?= htmlspecialchars($dados['nome']) ?>" placeholder="Ex: Mensalidade 6º Ano" required>
        </div>
        
        <div class="form-row-2">
            <div class="form-group">
                <label>Classe / Categoria <span class="required">*</span></label>
                <select name="classe" required>
                    <option value="">Selecione...</option>
                    <?php 
                    $classes = ['Pré', '1ª', '2ª', '3ª', '4ª', '5ª', '6ª', '7ª', '8ª', '9ª', '10ª', '11ª', '12ª'];
                    foreach ($classes as $c): 
                    ?>
                        <option value="<?= $c ?>" <?= $dados['classe'] == $c ? 'selected' : '' ?>>
                            <?= $c ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Tipo <span class="required">*</span></label>
                <select name="tipo" id="tipo" required>
                    <option value="">Selecione...</option>
                    <option value="Propina" <?= $dados['tipo'] == 'Propina' ? 'selected' : '' ?>>Propina</option>
                    <option value="Transporte" <?= $dados['tipo'] == 'Transporte' ? 'selected' : '' ?>>Transporte</option>
                    <option value="Matrícula" <?= $dados['tipo'] == 'Matrícula' ? 'selected' : '' ?>>Matrícula</option>
                    <option value="Uniforme Diário" <?= $dados['tipo'] == 'Uniforme Diário' ? 'selected' : '' ?>>Uniforme Diário</option>
                    <option value="Uniforme de Ed. Física" <?= $dados['tipo'] == 'Uniforme de Ed. Física' ? 'selected' : '' ?>>Uniforme de Ed. Física</option>
                    <option value="Cartão" <?= $dados['tipo'] == 'Cartão' ? 'selected' : '' ?>>Cartão</option>
                    <option value="Confirmação" <?= $dados['tipo'] == 'Confirmação' ? 'selected' : '' ?>>Confirmação</option>
                    <option value="Folha de Prova" <?= $dados['tipo'] == 'Folha de Prova' ? 'selected' : '' ?>>Folha de Prova</option>
                    <option value="Boletim de notas" <?= $dados['tipo'] == 'Boletim de notas' ? 'selected' : '' ?>>Boletim de notas</option>
                    <option value="Outros" <?= $dados['tipo'] == 'Outros' ? 'selected' : '' ?>>Outros</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Descrição Detalhada</label>
            <textarea name="descricao" placeholder="Descreva detalhadamente o emolumento"><?= htmlspecialchars($dados['descricao']) ?></textarea>
        </div>
        
        <!-- ===== MÊS DE REFERÊNCIA ===== -->
        <div class="section-title">
            <span class="icon">📅</span> Mês de Referência
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Mês de Referência <span class="required">*</span></label>
                <select name="mes_referencia" id="mesReferencia" required>
                    <?php 
                    $meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                              'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                    for($m = 1; $m <= 12; $m++): 
                    ?>
                    <option value="<?= $m ?>" <?= $dados['mes_referencia'] == $m ? 'selected' : '' ?>>
                        <?= $meses[$m-1] ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <span class="help">Mês a que se refere este emolumento</span>
            </div>
            
            <div class="form-group">
                <label>Ano de Referência <span class="required">*</span></label>
                <select name="ano_referencia" id="anoReferencia" required>
                    <?php for($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
                    <option value="<?= $a ?>" <?= $dados['ano_referencia'] == $a ? 'selected' : '' ?>>
                        <?= $a ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <span class="help">Ano a que se refere este emolumento</span>
            </div>
            
            <div class="form-group">
                <label>Valor <span class="required">*</span></label>
                <input type="text" name="valor" id="valor" value="R$ <?= number_format($dados['valor'], 2, ',', '.') ?>" placeholder="R$ 0,00" required>
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
                    <option value="percentual" <?= $dados['multa_tipo'] == 'percentual' ? 'selected' : '' ?>>📊 Percentual (%)</option>
                    <option value="fixo" <?= $dados['multa_tipo'] == 'fixo' ? 'selected' : '' ?>>💰 Valor Fixo (R$)</option>
                </select>
                <span class="help">Como a multa será calculada</span>
            </div>
            
            <div class="form-group">
                <label>Valor da Multa <span class="required">*</span></label>
                <input type="number" step="0.01" name="multa_valor" id="multaValor" value="<?= $dados['multa_valor'] ?>" placeholder="10" required>
                <span class="help" id="multaLabel">% sobre o valor</span>
            </div>
            
            <div class="form-group">
                <label>Prazo para Pagamento (dias) <span class="required">*</span></label>
                <input type="number" name="prazo_dias" value="<?= $dados['prazo_dias'] ?>" min="1" required>
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
                    <option value="<?= $m ?>" <?= $dados['mes_inicio_multa'] == $m ? 'selected' : '' ?>>
                        <?= $meses[$m-1] ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <span class="help">Mês a partir do qual a multa começa a contar</span>
            </div>
            
            <div class="form-group">
                <label>Ano de Início da Multa <span class="required">*</span></label>
                <select name="ano_inicio_multa" id="anoInicioMulta" required>
                    <?php for($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
                    <option value="<?= $a ?>" <?= $dados['ano_inicio_multa'] == $a ? 'selected' : '' ?>>
                        <?= $a ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <span class="help">Ano a partir do qual a multa começa a contar</span>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="ativo" <?= $dados['status'] == 'ativo' ? 'selected' : '' ?>>🟢 Ativo</option>
                    <option value="inativo" <?= $dados['status'] == 'inativo' ? 'selected' : '' ?>>🔴 Inativo</option>
                </select>
                <span class="help">Situação do emolumento</span>
            </div>
        </div>
        
        <!-- ===== RESUMO ===== -->
        <div class="section-title">
            <span class="icon">📊</span> Resumo do Emolumento
        </div>
        
        <div class="info-box">
            <p><strong>ID:</strong> <span class="id-display">#<?= $dados['id'] ?></span></p>
            <p><strong>Nome:</strong> <span id="resumoNome"><?= htmlspecialchars($dados['nome']) ?></span></p>
            <p><strong>Classe:</strong> <span id="resumoClasse"><?= $dados['classe'] ?></span></p>
            <p><strong>Tipo:</strong> <span id="resumoTipo"><?= $dados['tipo'] ?></span></p>
            <p><strong>Valor:</strong> <span id="resumoValor">R$ <?= number_format($dados['valor'], 2, ',', '.') ?></span></p>
            <p><strong>Multa:</strong> <span id="resumoMulta"><?= $dados['multa_tipo'] == 'percentual' ? $dados['multa_valor'] . '%' : 'R$ ' . number_format($dados['multa_valor'], 2, ',', '.') ?></span></p>
            <p><strong>Prazo:</strong> <span id="resumoPrazo"><?= $dados['prazo_dias'] ?> dias</span></p>
            <p><strong>Mês Referência:</strong> <span id="resumoMes"><?= $meses[$dados['mes_referencia']-1] ?></span>/<span id="resumoAno"><?= $dados['ano_referencia'] ?></span></p>
            <p><strong>Mês Início Multa:</strong> <span id="resumoMesMulta"><?= $meses[$dados['mes_inicio_multa']-1] ?></span>/<span id="resumoAnoMulta"><?= $dados['ano_inicio_multa'] ?></span></p>
            <p><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($dados['created_at'])) ?></p>
            <p><strong>Última atualização:</strong> <?= date('d/m/Y H:i', strtotime($dados['updated_at'])) ?></p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">💾 Salvar Alterações</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
            <a href="delete.php?id=<?= $dados['id'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este emolumento?')">🗑️ Excluir</a>
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
    });

    // Máscara para valor
    document.getElementById('valor').addEventListener('input', function(e) {
        let value = this.value.replace(/[^0-9,]/g, '');
        if (value.length > 0) {
            let parts = value.split(',');
            let integer = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            let decimal = parts[1] ? parts[1].slice(0, 2) : '';
            this.value = 'R$ ' + integer + (decimal ? ',' + decimal : ',00');
        } else {
            this.value = '';
        }
    });
</script>

<?php include '../../includes/footer_escola.php'; ?>