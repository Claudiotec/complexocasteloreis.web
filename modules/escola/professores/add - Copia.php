<?php
// ============================================
// modules/escola/alunos/add.php - Cadastrar Aluno
// ============================================

require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para CRIAR
bloquearAcesso('criar');

// Resto do código...
?>

<?php
// ============================================
// modules/escola/professores/add.php - Cadastrar Novo Professor
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

$erro = '';
$sucesso = '';

// Buscar disciplinas para o select
$disciplinas = [];
try {
    $disciplinas = $pdo->query("SELECT id, nome FROM disciplinas ORDER BY nome")->fetchAll();
} catch (Exception $e) {}

// Buscar turmas para o select
$turmas = [];
try {
    $turmas = $pdo->query("SELECT id, nome, classe, turno FROM turmas WHERE status = 'ativa' ORDER BY nome")->fetchAll();
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Coletar dados do formulário
    $nome = $_POST['nome'] ?? '';
    $sexo = $_POST['sexo'] ?? 'M';
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $morada = $_POST['morada'] ?? '';
    $contacto = $_POST['contacto'] ?? '';
    $email = $_POST['email'] ?? '';
    $bi = $_POST['bi'] ?? '';
    $nuit = $_POST['nuit'] ?? '';
    $especialidade = $_POST['especialidade'] ?? '';
    $disciplina_id = $_POST['disciplina_id'] ?? null;
    $turma_id = $_POST['turma_id'] ?? null;
    $data_contratacao = $_POST['data_contratacao'] ?? date('Y-m-d');
    $salario = $_POST['salario'] ?? 0;
    $observacoes = $_POST['observacoes'] ?? '';
    $status = $_POST['status'] ?? 'Ativo';

    // Validar campos obrigatórios
    if (empty($nome) || empty($contacto)) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        try {
            // Verificar se já existe professor com este BI
            if (!empty($bi)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM professores WHERE bi = ?");
                $stmt->execute([$bi]);
                if ($stmt->fetchColumn() > 0) {
                    $erro = "Já existe um professor cadastrado com este BI!";
                }
            }
            
            // Verificar se já existe professor com este email
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM professores WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $erro = "Já existe um professor cadastrado com este email!";
                }
            }
            
            if (empty($erro)) {
                // Inserir professor
                $stmt = $pdo->prepare("
                    INSERT INTO professores (
                        nome, sexo, data_nascimento, morada, contacto, 
                        email, bi, nuit, especialidade, disciplina_id,
                        turma_id, data_contratacao, salario, observacoes, 
                        status, data_cadastro
                    ) VALUES (
                        ?, ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, NOW()
                    )
                ");
                
                $stmt->execute([
                    $nome, $sexo, $data_nascimento, $morada, $contacto,
                    $email, $bi, $nuit, $especialidade, $disciplina_id,
                    $turma_id, $data_contratacao, $salario, $observacoes,
                    $status
                ]);
                
                $id = $pdo->lastInsertId();
                $sucesso = "Professor '$nome' cadastrado com sucesso! (ID: $id)";
                $_POST = [];
            }
        } catch (Exception $e) {
            $erro = "Erro ao cadastrar: " . $e->getMessage();
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
    
    .subtitle {
        color: #64748b;
        font-size: 14px;
        margin-top: 5px;
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
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
    }
    
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 900px;
    }
    
    .form-section {
        margin-bottom: 25px;
    }
    
    .form-section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1a2332;
        padding-bottom: 8px;
        border-bottom: 2px solid #eef2f7;
        margin-bottom: 15px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 10px;
    }
    
    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 15px;
        margin-bottom: 10px;
    }
    
    .form-group {
        margin-bottom: 10px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 4px;
        color: #1a2332;
        font-size: 12px;
    }
    
    .form-group label .required {
        color: #e74c3c;
    }
    
    .form-group label .help {
        font-weight: 400;
        color: #94a3b8;
        font-size: 10px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
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
        min-height: 80px;
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
    
    .nav-professores {
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
    
    .nav-professores a {
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
    
    .nav-professores a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .nav-professores a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
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
            padding: 15px;
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
        .nav-professores {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-professores a {
            text-align: center;
            justify-content: center;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>👨‍🏫 Cadastrar Novo Professor</h1>
        <p class="subtitle">Preencha todos os campos obrigatórios (*)</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Navegação -->
<div class="nav-professores">
    <a href="index.php">📋 Lista de Professores</a>
    <a href="add.php" class="active">➕ Cadastrar Professor</a>
    <a href="consulta.php">🔍 Consulta</a>
    <a href="relatorio.php">📈 Relatório</a>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success">✅ <?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error">❌ <?= $erro ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" id="formProfessor" onsubmit="return validarFormulario()">
        <!-- Dados Pessoais -->
        <div class="form-section">
            <div class="form-section-title">📌 Dados Pessoais</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nome Completo <span class="required">*</span></label>
                    <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required placeholder="Nome completo do professor">
                </div>
                <div class="form-group">
                    <label>Sexo <span class="required">*</span></label>
                    <select name="sexo" required>
                        <option value="M" <?= ($_POST['sexo'] ?? '') == 'M' ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= ($_POST['sexo'] ?? '') == 'F' ? 'selected' : '' ?>>Feminino</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Data de Nascimento</label>
                    <input type="date" name="data_nascimento" value="<?= htmlspecialchars($_POST['data_nascimento'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Contacto <span class="required">*</span></label>
                    <input type="tel" name="contacto" id="contacto" value="<?= htmlspecialchars($_POST['contacto'] ?? '') ?>" required placeholder="9XX XXX XXX">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="professor@escola.com">
                </div>
                <div class="form-group">
                    <label>Morada</label>
                    <input type="text" name="morada" value="<?= htmlspecialchars($_POST['morada'] ?? '') ?>" placeholder="Endereço completo">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>BI / Documento</label>
                    <input type="text" name="bi" value="<?= htmlspecialchars($_POST['bi'] ?? '') ?>" placeholder="Número do BI">
                </div>
                <div class="form-group">
                    <label>NUIT</label>
                    <input type="text" name="nuit" value="<?= htmlspecialchars($_POST['nuit'] ?? '') ?>" placeholder="Número de contribuinte">
                </div>
            </div>
        </div>
        
        <!-- Dados Profissionais -->
        <div class="form-section">
            <div class="form-section-title">🏫 Dados Profissionais</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Especialidade</label>
                    <input type="text" name="especialidade" value="<?= htmlspecialchars($_POST['especialidade'] ?? '') ?>" placeholder="Ex: Matemática, Português, etc.">
                </div>
                <div class="form-group">
                    <label>Disciplina</label>
                    <select name="disciplina_id">
                        <option value="">Selecione uma disciplina</option>
                        <?php foreach($disciplinas as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($_POST['disciplina_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Turma</label>
                    <select name="turma_id">
                        <option value="">Selecione uma turma</option>
                        <?php foreach($turmas as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($_POST['turma_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nome']) ?> (<?= $t['classe'] ?> - <?= $t['turno'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data de Contratação</label>
                    <input type="date" name="data_contratacao" value="<?= htmlspecialchars($_POST['data_contratacao'] ?? date('Y-m-d')) ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Salário (KZ)</label>
                    <input type="number" name="salario" value="<?= htmlspecialchars($_POST['salario'] ?? '') ?>" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Ativo" <?= ($_POST['status'] ?? '') == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="Inativo" <?= ($_POST['status'] ?? '') == 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                        <option value="Licença" <?= ($_POST['status'] ?? '') == 'Licença' ? 'selected' : '' ?>>Licença</option>
                        <option value="Férias" <?= ($_POST['status'] ?? '') == 'Férias' ? 'selected' : '' ?>>Férias</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Observações -->
        <div class="form-section">
            <div class="form-section-title">📝 Observações</div>
            
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" placeholder="Informações adicionais sobre o professor..."><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
            </div>
        </div>
        
        <!-- Ações -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Cadastrar Professor</button>
            <button type="reset" class="btn btn-secondary" onclick="limparFormulario()">🗑️ Limpar Campos</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
    // ===== VALIDAÇÃO DO FORMULÁRIO =====
    function validarFormulario() {
        var nome = document.getElementById('nome').value.trim();
        var contacto = document.getElementById('contacto').value.trim();
        
        if (!nome) {
            alert('❌ Por favor, informe o nome completo do professor!');
            document.getElementById('nome').focus();
            return false;
        }
        
        if (!contacto) {
            alert('❌ Por favor, informe o contacto do professor!');
            document.getElementById('contacto').focus();
            return false;
        }
        
        // Validar contacto (apenas números)
        if (!/^[0-9]{9,10}$/.test(contacto)) {
            alert('❌ O contacto deve conter apenas números (9 ou 10 dígitos)!');
            document.getElementById('contacto').focus();
            return false;
        }
        
        // Validar email se preenchido
        var email = document.querySelector('input[name="email"]').value.trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            alert('❌ Por favor, informe um email válido!');
            document.querySelector('input[name="email"]').focus();
            return false;
        }
        
        return true;
    }
    
    // ===== LIMPAR FORMULÁRIO =====
    function limparFormulario() {
        if (confirm('Tem certeza que deseja limpar todos os campos?')) {
            document.querySelectorAll('input[type="text"], input[type="tel"], input[type="email"], input[type="number"], input[type="date"], textarea').forEach(function(el) {
                el.value = '';
            });
            document.querySelectorAll('select').forEach(function(el) {
                el.selectedIndex = 0;
            });
            document.getElementById('nome').focus();
        }
    }
    
    // ===== MÁSCARA PARA CONTACTO =====
    document.getElementById('contacto').addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '');
    });
    
    // ===== MÁSCARA PARA BI =====
    document.querySelector('input[name="bi"]').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    // ===== PREVENIR ENVIO DUPLICADO =====
    document.getElementById('formProfessor').addEventListener('submit', function() {
        var btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '⏳ Cadastrando...';
        return true;
    });
</script>

<?php include '../includes/footer_escola.php'; ?>