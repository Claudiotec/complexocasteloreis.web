<?php
// ============================================
// modules/escola/financeiro/contas/add.php - Adicionar Conta
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

$mensagem = '';
$tipo_mensagem = '';

// ===== FUNÇÃO PARA VERIFICAR E CORRIGIR TABELA =====
function verificarEstruturaContas($pdo) {
    try {
        // Verificar se a tabela existe
        $check = $pdo->query("SHOW TABLES LIKE 'contas'");
        if ($check->rowCount() == 0) {
            // Criar tabela completa
            $pdo->exec("
                CREATE TABLE contas (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    descricao VARCHAR(255) NOT NULL,
                    categoria VARCHAR(100) NOT NULL,
                    tipo ENUM('receita', 'despesa') DEFAULT 'despesa',
                    valor DECIMAL(10,2) NOT NULL,
                    data_vencimento DATE NOT NULL,
                    data_pagamento DATE DEFAULT NULL,
                    status ENUM('pendente', 'pago', 'vencido', 'cancelado') DEFAULT 'pendente',
                    forma_pagamento VARCHAR(50) DEFAULT NULL,
                    observacoes TEXT,
                    fornecedor VARCHAR(100) DEFAULT NULL,
                    categoria_id INT DEFAULT NULL,
                    aluno_id INT DEFAULT NULL,
                    funcionario_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_tipo (tipo),
                    INDEX idx_vencimento (data_vencimento)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            return true;
        }
        
        // Verificar e adicionar colunas faltantes
        $colunas_necessarias = [
            'tipo' => "ALTER TABLE contas ADD COLUMN tipo ENUM('receita', 'despesa') DEFAULT 'despesa' AFTER categoria",
            'data_pagamento' => "ALTER TABLE contas ADD COLUMN data_pagamento DATE DEFAULT NULL AFTER data_vencimento",
            'forma_pagamento' => "ALTER TABLE contas ADD COLUMN forma_pagamento VARCHAR(50) DEFAULT NULL AFTER status",
            'fornecedor' => "ALTER TABLE contas ADD COLUMN fornecedor VARCHAR(100) DEFAULT NULL AFTER observacoes",
            'categoria_id' => "ALTER TABLE contas ADD COLUMN categoria_id INT DEFAULT NULL AFTER fornecedor",
            'aluno_id' => "ALTER TABLE contas ADD COLUMN aluno_id INT DEFAULT NULL AFTER categoria_id",
            'funcionario_id' => "ALTER TABLE contas ADD COLUMN funcionario_id INT DEFAULT NULL AFTER aluno_id",
            'updated_at' => "ALTER TABLE contas ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
        ];
        
        foreach ($colunas_necessarias as $coluna => $sql) {
            try {
                $check = $pdo->query("SHOW COLUMNS FROM contas LIKE '$coluna'");
                if ($check->rowCount() == 0) {
                    $pdo->exec($sql);
                }
            } catch (Exception $e) {
                // Se a coluna já existir, ignorar erro
            }
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ===== EXECUTAR VERIFICAÇÃO =====
verificarEstruturaContas($pdo);

// ===== PROCESSAR FORMULÁRIO =====
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descricao = $_POST['descricao'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $tipo = $_POST['tipo'] ?? 'despesa';
    $valor = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'])));
    $data_vencimento = $_POST['data_vencimento'] ?? '';
    $data_pagamento = $_POST['data_pagamento'] ?? null;
    $status = $_POST['status'] ?? 'pendente';
    $forma_pagamento = $_POST['forma_pagamento'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    $fornecedor = $_POST['fornecedor'] ?? '';
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    $aluno_id = intval($_POST['aluno_id'] ?? 0);
    $funcionario_id = intval($_POST['funcionario_id'] ?? 0);
    
    if (empty($descricao) || empty($categoria) || $valor <= 0 || empty($data_vencimento)) {
        $mensagem = 'Preencha todos os campos obrigatórios!';
        $tipo_mensagem = 'danger';
    } else {
        try {
            // Verificar quais colunas existem na tabela
            $colunas = $pdo->query("SHOW COLUMNS FROM contas")->fetchAll(PDO::FETCH_COLUMN);
            
            // Construir query dinâmica baseada nas colunas existentes
            $campos = ['descricao', 'categoria', 'valor', 'data_vencimento', 'status', 'observacoes'];
            $placeholders = ['?', '?', '?', '?', '?', '?'];
            $valores = [$descricao, $categoria, $valor, $data_vencimento, $status, $observacoes];
            
            if (in_array('tipo', $colunas)) {
                $campos[] = 'tipo';
                $placeholders[] = '?';
                $valores[] = $tipo;
            }
            
            if (in_array('data_pagamento', $colunas)) {
                $campos[] = 'data_pagamento';
                $placeholders[] = '?';
                $valores[] = !empty($data_pagamento) ? $data_pagamento : null;
            }
            
            if (in_array('forma_pagamento', $colunas)) {
                $campos[] = 'forma_pagamento';
                $placeholders[] = '?';
                $valores[] = $forma_pagamento;
            }
            
            if (in_array('fornecedor', $colunas)) {
                $campos[] = 'fornecedor';
                $placeholders[] = '?';
                $valores[] = $fornecedor;
            }
            
            if (in_array('categoria_id', $colunas)) {
                $campos[] = 'categoria_id';
                $placeholders[] = '?';
                $valores[] = $categoria_id > 0 ? $categoria_id : null;
            }
            
            if (in_array('aluno_id', $colunas)) {
                $campos[] = 'aluno_id';
                $placeholders[] = '?';
                $valores[] = $aluno_id > 0 ? $aluno_id : null;
            }
            
            if (in_array('funcionario_id', $colunas)) {
                $campos[] = 'funcionario_id';
                $placeholders[] = '?';
                $valores[] = $funcionario_id > 0 ? $funcionario_id : null;
            }
            
            $sql = "INSERT INTO contas (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($valores);
            
            header('Location: index.php?sucesso=Conta adicionada com sucesso');
            exit;
            
        } catch (Exception $e) {
            $mensagem = 'Erro ao adicionar: ' . $e->getMessage();
            $tipo_mensagem = 'danger';
        }
    }
}

// ===== BUSCAR ALUNOS E FUNCIONÁRIOS =====
$alunos = [];
$funcionarios = [];

try {
    $alunos = $pdo->query("SELECT id, nome FROM alunos WHERE status = 'ativo' ORDER BY nome")->fetchAll();
    $funcionarios = $pdo->query("SELECT id, nome FROM funcionarios WHERE status = 'ativo' ORDER BY nome")->fetchAll();
} catch (Exception $e) {}

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
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 800px;
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
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-danger {
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
    }
</style>

<div class="page-header">
    <div>
        <h1>➕ Nova Conta</h1>
        <p class="subtitle">Adicionar nova conta a pagar ou a receber</p>
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
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="descricao">Descrição <span class="required">*</span></label>
            <input type="text" name="descricao" id="descricao" class="form-control" 
                   value="<?= htmlspecialchars($_POST['descricao'] ?? '') ?>" 
                   placeholder="Ex: Conta de Luz - Janeiro 2026" required>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="categoria">Categoria <span class="required">*</span></label>
                <select name="categoria" id="categoria" class="form-control" required>
                    <option value="">Selecione</option>
                    <option value="Aluguel" <?= ($_POST['categoria'] ?? '') == 'Aluguel' ? 'selected' : '' ?>>🏢 Aluguel</option>
                    <option value="Energia" <?= ($_POST['categoria'] ?? '') == 'Energia' ? 'selected' : '' ?>>⚡ Energia Elétrica</option>
                    <option value="Água" <?= ($_POST['categoria'] ?? '') == 'Água' ? 'selected' : '' ?>>💧 Água</option>
                    <option value="Internet" <?= ($_POST['categoria'] ?? '') == 'Internet' ? 'selected' : '' ?>>🌐 Internet</option>
                    <option value="Telefone" <?= ($_POST['categoria'] ?? '') == 'Telefone' ? 'selected' : '' ?>>📞 Telefone</option>
                    <option value="Salário" <?= ($_POST['categoria'] ?? '') == 'Salário' ? 'selected' : '' ?>>💰 Salário</option>
                    <option value="Material" <?= ($_POST['categoria'] ?? '') == 'Material' ? 'selected' : '' ?>>📚 Material Escolar</option>
                    <option value="Manutenção" <?= ($_POST['categoria'] ?? '') == 'Manutenção' ? 'selected' : '' ?>>🔧 Manutenção</option>
                    <option value="Seguro" <?= ($_POST['categoria'] ?? '') == 'Seguro' ? 'selected' : '' ?>>🛡️ Seguro</option>
                    <option value="Impostos" <?= ($_POST['categoria'] ?? '') == 'Impostos' ? 'selected' : '' ?>>📋 Impostos</option>
                    <option value="Outros" <?= ($_POST['categoria'] ?? '') == 'Outros' ? 'selected' : '' ?>>📦 Outros</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="tipo">Tipo <span class="required">*</span></label>
                <select name="tipo" id="tipo" class="form-control" required>
                    <option value="despesa" <?= ($_POST['tipo'] ?? '') == 'despesa' ? 'selected' : '' ?>>💰 Despesa</option>
                    <option value="receita" <?= ($_POST['tipo'] ?? '') == 'receita' ? 'selected' : '' ?>>📈 Receita</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="valor">Valor (Kz) <span class="required">*</span></label>
                <input type="text" name="valor" id="valor" class="form-control" 
                       value="<?= number_format($_POST['valor'] ?? 0, 2, ',', '.') ?>" 
                       placeholder="0,00" required>
            </div>
            
            <div class="form-group">
                <label for="data_vencimento">Data de Vencimento <span class="required">*</span></label>
                <input type="date" name="data_vencimento" id="data_vencimento" class="form-control" 
                       value="<?= $_POST['data_vencimento'] ?? date('Y-m-d', strtotime('+30 days')) ?>" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="pendente" <?= ($_POST['status'] ?? '') == 'pendente' ? 'selected' : '' ?>>⏳ Pendente</option>
                    <option value="pago" <?= ($_POST['status'] ?? '') == 'pago' ? 'selected' : '' ?>>✅ Pago</option>
                    <option value="vencido" <?= ($_POST['status'] ?? '') == 'vencido' ? 'selected' : '' ?>>⚠️ Vencido</option>
                    <option value="cancelado" <?= ($_POST['status'] ?? '') == 'cancelado' ? 'selected' : '' ?>>❌ Cancelado</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="forma_pagamento">Forma de Pagamento</label>
                <select name="forma_pagamento" id="forma_pagamento" class="form-control">
                    <option value="">Selecione</option>
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
            <label for="data_pagamento">Data de Pagamento</label>
            <input type="date" name="data_pagamento" id="data_pagamento" class="form-control" 
                   value="<?= $_POST['data_pagamento'] ?? '' ?>">
            <div class="form-text">Preencha se já foi pago</div>
        </div>
        
        <div class="form-group">
            <label for="fornecedor">Fornecedor / Credor</label>
            <input type="text" name="fornecedor" id="fornecedor" class="form-control" 
                   value="<?= htmlspecialchars($_POST['fornecedor'] ?? '') ?>" 
                   placeholder="Nome do fornecedor ou credor">
        </div>
        
        <div class="form-group">
            <label for="observacoes">Observações</label>
            <textarea name="observacoes" id="observacoes" class="form-control" 
                      placeholder="Observações adicionais sobre esta conta"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="aluno_id">Aluno (opcional)</label>
                <select name="aluno_id" id="aluno_id" class="form-control">
                    <option value="0">Nenhum</option>
                    <?php foreach($alunos as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($_POST['aluno_id'] ?? 0) == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="funcionario_id">Funcionário (opcional)</label>
                <select name="funcionario_id" id="funcionario_id" class="form-control">
                    <option value="0">Nenhum</option>
                    <?php foreach($funcionarios as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= ($_POST['funcionario_id'] ?? 0) == $f['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">💾 Salvar Conta</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
    document.getElementById('valor').addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 0) {
            value = (parseFloat(value) / 100).toFixed(2);
            value = value.replace('.', ',');
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            this.value = value;
        }
    });
</script>

<?php include '../../includes/footer_escola.php'; ?>