<?php
// ============================================
// modules/escola/professores/edit.php - Editar Professor
// ============================================

// ============================================
// 1. CARREGAR CONFIGURAÇÕES
// ============================================
require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';
require_once __DIR__ . '/../includes/contadores.php';

// ============================================
// 2. INICIAR SESSÃO
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 3. VERIFICAR LOGIN
// ============================================
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../../../login.php');
    exit;
}

// ============================================
// 4. 🔒 VERIFICAR PERMISSÃO PARA EDITAR
// ============================================
bloquearAcesso('editar');

// ============================================
// 5. FUNÇÃO PARA UPLOAD DE FOTO
// ============================================
function uploadFoto($file, $id, $pasta = '../../../uploads/professores/') {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido pelo formulário.',
            UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP interrompeu o upload.'
        ];
        throw new Exception('Erro no upload: ' . ($errors[$file['error']] ?? 'Erro desconhecido'));
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP.');
    }
    
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception('O arquivo excede o tamanho máximo de 5MB.');
    }
    
    $upload_dir = __DIR__ . '/' . $pasta;
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception('Não foi possível criar o diretório de upload.');
        }
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nome_arquivo = 'professor_' . $id . '_' . time() . '.' . $extension;
    $caminho_completo = $upload_dir . $nome_arquivo;
    
    if (!move_uploaded_file($file['tmp_name'], $caminho_completo)) {
        throw new Exception('Erro ao mover o arquivo para o destino final.');
    }
    
    return 'uploads/professores/' . $nome_arquivo;
}

// ============================================
// 6. BUSCAR PROFESSOR
// ============================================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$professor = null;
$erro = '';
$sucesso = '';

if ($id > 0) {
    try {
        $pdo = conectarBanco();
        
        $stmt = $pdo->query("SHOW COLUMNS FROM funcionarios LIKE 'foto'");
        $coluna_foto = $stmt->rowCount() > 0;
        
        if ($coluna_foto) {
            $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("SELECT *, NULL as foto FROM funcionarios WHERE id = ?");
        }
        $stmt->execute([$id]);
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$professor) {
            $erro = "Professor não encontrado!";
        }
    } catch (Exception $e) {
        $erro = "Erro ao buscar professor: " . $e->getMessage();
    }
} else {
    $erro = "ID do professor não informado!";
}

// ============================================
// 7. PROCESSAR FORMULÁRIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $nome = $_POST['nome'] ?? '';
    $categoria_actual = $_POST['categoria_actual'] ?? '';
    $cargo = $_POST['cargo'] ?? '';
    $funcao_instituicao = $_POST['funcao_instituicao'] ?? '';
    $disciplina_lecciona = $_POST['disciplina_lecciona'] ?? '';
    $contacto_telefonico = $_POST['contacto_telefonico'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $email = $_POST['email'] ?? '';
    $data_nascimento = $_POST['data_nascimento'] ?? null;
    $num_bi = $_POST['num_bi'] ?? '';
    $genero = $_POST['genero'] ?? 'M';
    $habilitacoes_literarias = $_POST['habilitacoes_literarias'] ?? '';
    $especialidade_superior = $_POST['especialidade_superior'] ?? '';
    $municipio_residencia = $_POST['municipio_residencia'] ?? '';
    $data_admissao = $_POST['data_admissao'] ?? null;
    $status = $_POST['status'] ?? 'ativo';
    $departamento = $_POST['departamento'] ?? 'Pedagogico';
    $salario_base = $_POST['salario_base'] ?? 0;
    $salario = $_POST['salario'] ?? 0;
    $num_agente = $_POST['num_agente'] ?? '';
    $remover_foto = isset($_POST['remover_foto']) ? 1 : 0;
    
    if (empty($nome)) {
        $erro = 'O campo Nome é obrigatório!';
    } else {
        try {
            $pdo = conectarBanco();
            
            $stmt = $pdo->query("SHOW COLUMNS FROM funcionarios LIKE 'foto'");
            $coluna_foto = $stmt->rowCount() > 0;
            
            $foto_path = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                try {
                    $foto_path = uploadFoto($_FILES['foto'], $id);
                } catch (Exception $e) {
                    $erro = $e->getMessage();
                }
            }
            
            if (empty($erro)) {
                $sql = "UPDATE funcionarios SET 
                    num_agente = :num_agente,
                    nome = :nome,
                    categoria_actual = :categoria_actual,
                    cargo = :cargo,
                    funcao_instituicao = :funcao_instituicao,
                    disciplina_lecciona = :disciplina_lecciona,
                    contacto_telefonico = :contacto_telefonico,
                    telefone = :telefone,
                    email = :email,
                    data_nascimento = :data_nascimento,
                    num_bi = :num_bi,
                    genero = :genero,
                    habilitacoes_literarias = :habilitacoes_literarias,
                    especialidade_superior = :especialidade_superior,
                    municipio_residencia = :municipio_residencia,
                    data_admissao = :data_admissao,
                    status = :status,
                    departamento = :departamento,
                    salario_base = :salario_base,
                    salario = :salario,
                    updated_at = NOW()";
                
                $params = [
                    ':num_agente' => $num_agente,
                    ':nome' => $nome,
                    ':categoria_actual' => $categoria_actual,
                    ':cargo' => $cargo,
                    ':funcao_instituicao' => $funcao_instituicao,
                    ':disciplina_lecciona' => $disciplina_lecciona,
                    ':contacto_telefonico' => $contacto_telefonico,
                    ':telefone' => $telefone,
                    ':email' => $email,
                    ':data_nascimento' => $data_nascimento ?: null,
                    ':num_bi' => $num_bi,
                    ':genero' => $genero,
                    ':habilitacoes_literarias' => $habilitacoes_literarias,
                    ':especialidade_superior' => $especialidade_superior,
                    ':municipio_residencia' => $municipio_residencia,
                    ':data_admissao' => $data_admissao ?: null,
                    ':status' => $status,
                    ':departamento' => $departamento,
                    ':salario_base' => $salario_base,
                    ':salario' => $salario,
                    ':id' => $id
                ];
                
                if ($coluna_foto) {
                    if ($foto_path) {
                        $sql .= ", foto = :foto";
                        $params[':foto'] = $foto_path;
                    }
                    if ($remover_foto) {
                        $sql .= ", foto = NULL";
                    }
                }
                
                $sql .= " WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $sucesso = "✅ Professor atualizado com sucesso!";
                
                // Recarregar dados FORÇADAMENTE
                if ($coluna_foto) {
                    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("SELECT *, NULL as foto FROM funcionarios WHERE id = ?");
                }
                $stmt->execute([$id]);
                $professor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // FORÇAR ATUALIZAÇÃO DA PÁGINA
                echo "<meta http-equiv='refresh' content='0;url=edit.php?id=" . $id . "'>";
                exit;
            }
            
        } catch (Exception $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
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
    .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
    .page-header .subtitle { color: #94a3b8; font-size: 14px; margin: 2px 0 0; }
    
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
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(201,168,76,0.3); }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-danger:hover { background: #c0392b; }
    .btn-success { background: #2ecc71; color: white; }
    .btn-success:hover { background: #27ae60; }
    
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 900px;
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 10px;
    }
    .form-group { margin-bottom: 10px; }
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 4px;
        color: #1a2332;
        font-size: 12px;
    }
    .form-group label .required { color: #e74c3c; }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    
    .section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1a2332;
        padding-bottom: 8px;
        border-bottom: 2px solid #eef2f7;
        margin-bottom: 15px;
        margin-top: 15px;
    }
    
    .foto-container {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        padding: 20px;
        background: #f8fafc;
        border-radius: 12px;
        border: 2px solid #c9a84c;
        min-height: 180px;
    }
    .foto-wrapper {
        position: relative;
        width: 150px;
        height: 150px;
        border-radius: 50%;
        border: 4px solid #c9a84c;
        overflow: hidden;
        background: #f1f5f9;
        flex-shrink: 0;
    }
    .foto-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .foto-wrapper .placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 60px;
        color: #94a3b8;
    }
    .foto-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
    }
    .foto-actions .btn {
        justify-content: center;
        min-width: 180px;
    }
    .foto-actions .btn-camera {
        background: #3498db;
        color: white;
    }
    .foto-actions .btn-camera:hover {
        background: #2980b9;
    }
    .foto-actions .btn-upload {
        background: #c9a84c;
        color: #1a2332;
    }
    .foto-actions .btn-upload:hover {
        background: #b8973a;
    }
    .foto-actions .btn-remover {
        background: #e74c3c;
        color: white;
    }
    .foto-actions .btn-remover:hover {
        background: #c0392b;
    }
    #fotoInput, #cameraInput {
        display: none;
    }
    .foto-info {
        font-size: 13px;
        color: #94a3b8;
        margin-top: 4px;
        font-weight: 500;
    }
    .foto-info.success { color: #065f46; }
    .foto-info.error { color: #991b1b; }
    
    #videoContainer {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.95);
        z-index: 99999;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }
    #videoContainer video {
        max-width: 90%;
        max-height: 70%;
        border-radius: 12px;
        border: 3px solid #c9a84c;
        background: #000;
        object-fit: cover;
    }
    #videoContainer .cam-actions {
        margin-top: 20px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        justify-content: center;
    }
    #videoContainer .cam-actions button {
        padding: 12px 30px;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    #videoContainer .cam-actions .btn-capturar {
        background: #2ecc71;
        color: white;
    }
    #videoContainer .cam-actions .btn-capturar:hover {
        background: #27ae60;
        transform: scale(1.05);
    }
    #videoContainer .cam-actions .btn-fechar-cam {
        background: #e74c3c;
        color: white;
    }
    #videoContainer .cam-actions .btn-fechar-cam:hover {
        background: #c0392b;
    }
    
    .mensagem-flutuante {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 999999;
        background: #d1fae5;
        color: #065f46;
        padding: 12px 24px;
        border-radius: 8px;
        border: 1px solid #a7f3d0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        font-weight: 600;
        font-family: Arial, sans-serif;
        animation: slideDown 0.5s ease;
        max-width: 90%;
        text-align: center;
    }
    
    @keyframes slideDown {
        from { transform: translateX(-50%) translateY(-30px); opacity: 0; }
        to { transform: translateX(-50%) translateY(0); opacity: 1; }
    }
    
    @media (max-width: 768px) {
        .form-row { grid-template-columns: 1fr; gap: 0; }
        .form-container { padding: 15px; }
        .page-header { flex-direction: column; align-items: stretch; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { justify-content: center; }
        .foto-container { flex-direction: column; text-align: center; }
        .foto-actions .btn { min-width: 100%; }
        .foto-wrapper { width: 120px; height: 120px; }
        #videoContainer video { max-width: 95%; max-height: 60%; }
        #videoContainer .cam-actions button { padding: 10px 20px; font-size: 14px; }
    }
</style>

<div class="page-header">
    <div>
        <h1>✏️ Editar Professor</h1>
        <p class="subtitle">Atualize os dados do professor</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success">✅ <?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error">❌ <?= $erro ?></div>
<?php endif; ?>

<?php if ($professor): ?>
<div class="form-container">
    <form method="POST" enctype="multipart/form-data" id="formProfessor">
        <input type="hidden" name="id" value="<?= $professor['id'] ?>">
        
        <!-- ===== FOTO COM FORÇA BRUTA ===== -->
        <div class="section-title">📸 Foto do Professor</div>
        
        <div class="foto-container" id="fotoContainer">
            <div class="foto-wrapper">
                <?php 
                $foto_path = !empty($professor['foto']) ? $professor['foto'] : '';
                $foto_url = !empty($foto_path) ? '../../' . $foto_path : '';
                $tem_foto = !empty($foto_path) && file_exists(__DIR__ . '/../../' . $foto_path);
                
                if ($tem_foto): 
                ?>
                    <img id="fotoPreviewImg" src="<?= $foto_url ?>" alt="Foto do Professor">
                <?php else: ?>
                    <div class="placeholder" id="fotoPlaceholder">👤</div>
                    <img id="fotoPreviewImg" src="" style="display:none;">
                <?php endif; ?>
            </div>
            
            <div class="foto-actions">
                <input type="file" id="fotoInput" name="foto" accept="image/*">
                <input type="file" id="cameraInput" accept="image/*" capture="environment">
                
                <button type="button" class="btn btn-upload" onclick="document.getElementById('fotoInput').click()">
                    📁 Escolher Foto
                </button>
                <button type="button" class="btn btn-camera" onclick="abrirCamera()">
                    📷 Tirar Foto
                </button>
                <?php if ($tem_foto): ?>
                <button type="button" class="btn btn-remover" onclick="removerFoto()">
                    🗑️ Remover Foto
                </button>
                <?php endif; ?>
                <input type="hidden" name="remover_foto" id="removerFotoInput" value="0">
                <div class="foto-info" id="fotoInfo">
                    <?php if ($tem_foto): ?>
                        <span class="success">✅ Foto atual: <?= basename($foto_path) ?></span>
                    <?php else: ?>
                        <span>📌 Nenhuma foto selecionada</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Dados Pessoais -->
        <div class="section-title">📌 Dados Pessoais</div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Nome Completo <span class="required">*</span></label>
                <input type="text" name="nome" required value="<?= htmlspecialchars($professor['nome'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Nº Agente</label>
                <input type="text" name="num_agente" value="<?= htmlspecialchars($professor['num_agente'] ?? '') ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Data de Nascimento</label>
                <input type="date" name="data_nascimento" value="<?= htmlspecialchars($professor['data_nascimento'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Gênero</label>
                <select name="genero">
                    <option value="M" <?= ($professor['genero'] ?? 'M') == 'M' ? 'selected' : '' ?>>Masculino</option>
                    <option value="F" <?= ($professor['genero'] ?? 'M') == 'F' ? 'selected' : '' ?>>Feminino</option>
                    <option value="Outro" <?= ($professor['genero'] ?? 'M') == 'Outro' ? 'selected' : '' ?>>Outro</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Nº BI</label>
                <input type="text" name="num_bi" value="<?= htmlspecialchars($professor['num_bi'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Contacto Telefónico</label>
                <input type="tel" name="contacto_telefonico" value="<?= htmlspecialchars($professor['contacto_telefonico'] ?? '') ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Telefone Alternativo</label>
                <input type="tel" name="telefone" value="<?= htmlspecialchars($professor['telefone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($professor['email'] ?? '') ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Município de Residência</label>
                <input type="text" name="municipio_residencia" value="<?= htmlspecialchars($professor['municipio_residencia'] ?? '') ?>">
            </div>
        </div>
        
        <!-- Dados Profissionais -->
        <div class="section-title">🏫 Dados Profissionais</div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Categoria Actual <span class="required">*</span></label>
                <input type="text" name="categoria_actual" required value="<?= htmlspecialchars($professor['categoria_actual'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Cargo <span class="required">*</span></label>
                <input type="text" name="cargo" required value="<?= htmlspecialchars($professor['cargo'] ?? '') ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Função na Instituição</label>
                <input type="text" name="funcao_instituicao" value="<?= htmlspecialchars($professor['funcao_instituicao'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Departamento</label>
                <input type="text" name="departamento" value="<?= htmlspecialchars($professor['departamento'] ?? 'Pedagogico') ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Data de Admissão</label>
                <input type="date" name="data_admissao" value="<?= htmlspecialchars($professor['data_admissao'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="ativo" <?= ($professor['status'] ?? 'ativo') == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= ($professor['status'] ?? 'ativo') == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    <option value="ferias" <?= ($professor['status'] ?? 'ativo') == 'ferias' ? 'selected' : '' ?>>Férias</option>
                </select>
            </div>
        </div>
        
        <!-- Formação -->
        <div class="section-title">📚 Formação</div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Habilitações Literárias</label>
                <input type="text" name="habilitacoes_literarias" value="<?= htmlspecialchars($professor['habilitacoes_literarias'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Especialidade Superior</label>
                <input type="text" name="especialidade_superior" value="<?= htmlspecialchars($professor['especialidade_superior'] ?? '') ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Disciplina que Leciona</label>
                <input type="text" name="disciplina_lecciona" value="<?= htmlspecialchars($professor['disciplina_lecciona'] ?? '') ?>">
            </div>
        </div>
        
        <!-- Financeiro -->
        <div class="section-title">💰 Dados Financeiros</div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Salário Base</label>
                <input type="number" step="0.01" name="salario_base" value="<?= htmlspecialchars($professor['salario_base'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Salário Actual</label>
                <input type="number" step="0.01" name="salario" value="<?= htmlspecialchars($professor['salario'] ?? '') ?>">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ===== VIDEO DA CÂMERA ===== -->
<div id="videoContainer">
    <video id="video" autoplay playsinline></video>
    <div class="cam-actions">
        <button class="btn-capturar" onclick="capturarFoto()">📸 Capturar</button>
        <button class="btn-fechar-cam" onclick="fecharCamera()">✕ Fechar</button>
    </div>
</div>

<script>
// ============================================
// FUNÇÕES PARA FOTO - FORÇA BRUTA
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('📸 Sistema de foto inicializado - FORÇA BRUTA');
    
    // Verificar se tem foto carregada
    const img = document.getElementById('fotoPreviewImg');
    const placeholder = document.getElementById('fotoPlaceholder');
    
    if (img && img.src && img.src.length > 0 && !img.src.includes('data:image')) {
        console.log('✅ Foto encontrada:', img.src);
        if (placeholder) placeholder.style.display = 'none';
        if (img) img.style.display = 'block';
    } else {
        console.log('📌 Nenhuma foto encontrada');
    }
    
    // Configurar input de foto
    const fotoInput = document.getElementById('fotoInput');
    if (fotoInput) {
        fotoInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                console.log('📁 Arquivo selecionado:', this.files[0].name);
                previewFoto(this);
            }
        });
    }
    
    // Configurar input da câmera
    const cameraInput = document.getElementById('cameraInput');
    if (cameraInput) {
        cameraInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                console.log('📷 Foto da câmera:', this.files[0].name);
                previewFoto(this);
            }
        });
    }
    
    console.log('✅ Sistema pronto!');
});

function previewFoto(input) {
    if (!input.files || input.files.length === 0) {
        console.log('⚠️ Nenhum arquivo selecionado');
        return;
    }
    
    const file = input.files[0];
    console.log('📁 Arquivo:', file.name, 'Tamanho:', file.size, 'Tipo:', file.type);
    
    // Verificar tamanho
    if (file.size > 5 * 1024 * 1024) {
        alert('❌ O arquivo excede o tamanho máximo de 5MB!');
        input.value = '';
        return;
    }
    
    // Verificar tipo
    const tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!tiposPermitidos.includes(file.type)) {
        alert('❌ Formato não permitido! Use JPG, PNG, GIF ou WEBP.');
        input.value = '';
        return;
    }
    
    // FORÇAR preview - método mais direto
    const img = document.getElementById('fotoPreviewImg');
    const placeholder = document.getElementById('fotoPlaceholder');
    
    if (img) {
        // Criar URL
        const url = URL.createObjectURL(file);
        img.src = url;
        img.style.display = 'block';
        img.dataset.objectUrl = url;
        
        // Esconder placeholder
        if (placeholder) placeholder.style.display = 'none';
        
        console.log('✅ Preview FORÇADO com sucesso!');
        console.log('🔍 URL:', url.substring(0, 50) + '...');
    }
    
    // Atualizar hidden
    document.getElementById('removerFotoInput').value = 0;
    
    // Atualizar info
    const info = document.getElementById('fotoInfo');
    if (info) {
        const tamanho = (file.size / 1024).toFixed(1);
        info.innerHTML = `<span class="success">✅ ${file.name} (${tamanho} KB) - PREVIEW ATIVO</span>`;
        info.className = 'foto-info success';
    }
    
    // Mostrar botão de remover se não tiver
    const btnRemover = document.querySelector('.btn-remover');
    if (!btnRemover) {
        const actions = document.querySelector('.foto-actions');
        if (actions) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-remover';
            btn.textContent = '🗑️ Remover Foto';
            btn.onclick = removerFoto;
            actions.appendChild(btn);
        }
    }
}

function removerFoto() {
    if (!confirm('Tem certeza que deseja remover a foto do professor?')) {
        return;
    }
    
    const img = document.getElementById('fotoPreviewImg');
    const placeholder = document.getElementById('fotoPlaceholder');
    
    if (img) {
        if (img.dataset.objectUrl) {
            URL.revokeObjectURL(img.dataset.objectUrl);
        }
        img.src = '';
        img.style.display = 'none';
    }
    
    if (placeholder) placeholder.style.display = 'flex';
    
    document.getElementById('removerFotoInput').value = 1;
    document.getElementById('fotoInput').value = '';
    document.getElementById('cameraInput').value = '';
    
    const info = document.getElementById('fotoInfo');
    if (info) {
        info.innerHTML = '<span class="error">🗑️ Foto removida</span>';
        info.className = 'foto-info error';
    }
    
    console.log('🗑️ Foto removida');
    mostrarMensagem('🗑️ Foto removida com sucesso!');
}

// ============================================
// FUNÇÕES PARA CÂMERA
// ============================================

let stream = null;
const videoElement = document.getElementById('video');
const videoContainer = document.getElementById('videoContainer');

function abrirCamera() {
    console.log('📷 Abrindo câmera...');
    
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('⚠️ Seu navegador não suporta acesso à câmera.');
        return;
    }
    
    videoContainer.style.display = 'flex';
    
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            facingMode: 'user',
            width: { ideal: 640 },
            height: { ideal: 480 }
        },
        audio: false 
    })
    .then(function(mediaStream) {
        stream = mediaStream;
        videoElement.srcObject = mediaStream;
        videoElement.play();
        console.log('📷 Câmera iniciada');
    })
    .catch(function(err) {
        console.error('❌ Erro:', err);
        alert('❌ Não foi possível acessar a câmera: ' + err.message);
        fecharCamera();
    });
}

function capturarFoto() {
    if (!stream) {
        alert('❌ Câmera não está ativa.');
        return;
    }
    
    try {
        const canvas = document.createElement('canvas');
        const videoWidth = videoElement.videoWidth || 640;
        const videoHeight = videoElement.videoHeight || 480;
        
        canvas.width = videoWidth;
        canvas.height = videoHeight;
        const context = canvas.getContext('2d');
        context.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
        
        canvas.toBlob(function(blob) {
            if (!blob) {
                alert('❌ Erro ao capturar!');
                return;
            }
            
            const file = new File([blob], 'foto_camera_' + Date.now() + '.jpg', { type: 'image/jpeg' });
            console.log('📸 Foto capturada:', file.name);
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            
            const input = document.getElementById('fotoInput');
            input.files = dataTransfer.files;
            
            previewFoto(input);
            fecharCamera();
            mostrarMensagem('✅ Foto capturada com sucesso!');
            
        }, 'image/jpeg', 0.9);
        
    } catch (error) {
        console.error('❌ Erro:', error);
        alert('❌ Erro ao capturar: ' + error.message);
    }
}

function fecharCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    videoElement.srcObject = null;
    videoContainer.style.display = 'none';
    console.log('📷 Câmera fechada');
}

function mostrarMensagem(msg) {
    const old = document.querySelector('.mensagem-flutuante');
    if (old) old.remove();
    
    const div = document.createElement('div');
    div.className = 'mensagem-flutuante';
    div.textContent = msg;
    document.body.appendChild(div);
    
    setTimeout(() => {
        div.style.opacity = '0';
        div.style.transition = 'opacity 0.5s';
        setTimeout(() => div.remove(), 500);
    }, 3000);
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharCamera();
    if (e.key === 'Enter' && videoContainer.style.display === 'flex') capturarFoto();
});

// ============================================
// VALIDAÇÃO
// ============================================

document.getElementById('formProfessor')?.addEventListener('submit', function(e) {
    const nome = document.querySelector('input[name="nome"]');
    if (!nome || nome.value.trim() === '') {
        alert('❌ O campo Nome é obrigatório!');
        e.preventDefault();
        return false;
    }
    console.log('📤 Enviando formulário...');
    return true;
});

console.log('📸 Sistema FORÇA BRUTA carregado!');
</script>

<?php include '../includes/footer_escola.php'; ?>