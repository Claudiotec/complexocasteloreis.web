<?php
// edit_funcionario.php - Editar funcionário com todos os campos
require_once '../../config/database.php';

// =============================================
// CONFIGURAÇÃO
// =============================================
date_default_timezone_set('Africa/Luanda');

$id = $_GET['id'] ?? 0;

// Buscar funcionário
$stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ?");
$stmt->execute([$id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    header("Location: funcionarios.php");
    exit;
}

// ============================================
// FUNÇÃO PARA UPLOAD DE FOTO
// ============================================
function uploadFoto($file, $id, $pasta = '../../uploads/funcionarios/') {
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
    $nome_arquivo = 'funcionario_' . $id . '_' . time() . '.' . $extension;
    $caminho_completo = $upload_dir . $nome_arquivo;
    
    if (!move_uploaded_file($file['tmp_name'], $caminho_completo)) {
        throw new Exception('Erro ao mover o arquivo para o destino final.');
    }
    
    return 'uploads/funcionarios/' . $nome_arquivo;
}

// =============================================
// PROCESSAR ATUALIZAÇÃO
// =============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Dados pessoais
    $num_agente = trim($_POST['num_agente']);
    $nome = trim($_POST['nome']);
    $genero = $_POST['genero'];
    $data_nascimento = $_POST['data_nascimento'];
    $num_bi = trim($_POST['num_bi']);
    
    // Dados profissionais
    $categoria_actual = trim($_POST['categoria_actual']);
    $instituicao_salario = trim($_POST['instituicao_salario']);
    $funcao_instituicao = trim($_POST['funcao_instituicao']);
    $disciplina_lecciona = trim($_POST['disciplina_lecciona']);
    $formacao_disciplina = trim($_POST['formacao_disciplina']);
    $data_inicio_funcao = $_POST['data_inicio_funcao'];
    $data_inicio_instituicao = $_POST['data_inicio_instituicao'];
    
    // Habilitações
    $habilitacoes_literarias = trim($_POST['habilitacoes_literarias']);
    $especialidade_medio = trim($_POST['especialidade_medio']);
    $especialidade_superior = trim($_POST['especialidade_superior']);
    
    // Contato
    $contacto_telefonico = trim($_POST['contacto_telefonico']);
    $municipio_residencia = trim($_POST['municipio_residencia']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    
    // Dados da empresa
    $cargo = trim($_POST['cargo']);
    $departamento = trim($_POST['departamento']);
    $salario_base = str_replace(',', '.', str_replace('.', '', $_POST['salario_base']));
    $salario = str_replace(',', '.', str_replace('.', '', $_POST['salario']));
    $iban = trim($_POST['iban']);
    $data_admissao = $_POST['data_admissao'];
    $status = $_POST['status'];
    $remover_foto = isset($_POST['remover_foto']) ? 1 : 0;
    
    try {
        // Processar upload da foto
        $foto_path = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            try {
                $foto_path = uploadFoto($_FILES['foto'], $id);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        // Atualizar
        $sql = "UPDATE funcionarios SET 
            num_agente = ?,
            nome = ?,
            genero = ?,
            data_nascimento = ?,
            num_bi = ?,
            categoria_actual = ?,
            instituicao_salario = ?,
            funcao_instituicao = ?,
            disciplina_lecciona = ?,
            formacao_disciplina = ?,
            data_inicio_funcao = ?,
            data_inicio_instituicao = ?,
            habilitacoes_literarias = ?,
            especialidade_medio = ?,
            especialidade_superior = ?,
            contacto_telefonico = ?,
            municipio_residencia = ?,
            email = ?,
            telefone = ?,
            cargo = ?,
            departamento = ?,
            salario_base = ?,
            salario = ?,
            iban = ?,
            data_admissao = ?,
            status = ?";
        
        $params = [
            $num_agente,
            $nome,
            $genero,
            $data_nascimento,
            $num_bi,
            $categoria_actual,
            $instituicao_salario,
            $funcao_instituicao,
            $disciplina_lecciona,
            $formacao_disciplina,
            $data_inicio_funcao,
            $data_inicio_instituicao,
            $habilitacoes_literarias,
            $especialidade_medio,
            $especialidade_superior,
            $contacto_telefonico,
            $municipio_residencia,
            $email,
            $telefone,
            $cargo,
            $departamento,
            $salario_base,
            $salario,
            $iban,
            $data_admissao,
            $status
        ];
        
        // Adicionar foto se foi enviada
        if ($foto_path) {
            $sql .= ", foto = ?";
            $params[] = $foto_path;
        }
        
        // Remover foto se solicitado
        if ($remover_foto) {
            $sql .= ", foto = NULL";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        header("Location: funcionarios.php?success=Funcionário atualizado com sucesso!");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Funcionário - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f0f4f8; 
            color: #1a2332;
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 20px;
            max-width: calc(100% - 280px);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .container { max-width: 1200px; margin: 0 auto; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        .page-title { font-size: 28px; font-weight: 800; color: #1a2332; }
        .page-title span { color: #c9a84c; }
        .page-subtitle { color: #64748b; font-size: 14px; margin-top: 4px; }
        
        .btn-outline {
            background: transparent;
            color: #c9a84c;
            padding: 10px 24px;
            border: 2px solid #c9a84c;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-outline:hover { background: rgba(197,165,50,0.1); transform: translateY(-2px); }
        
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,165,50,0.3); }
        
        .btn-secondary {
            background: #eef2f7;
            color: #1a2332;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }
        .btn-secondary:hover { background: #e2e8f0; transform: translateY(-2px); }
        
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        
        .btn-camera {
            background: #3498db;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        .btn-camera:hover { background: #2980b9; transform: translateY(-2px); }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            border: 1px solid #eef2f7;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a2332;
            padding-bottom: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #c9a84c;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title .icon { color: #c9a84c; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .form-group label .required {
            color: #e74c3c;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
            width: 100%;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: #c9a84c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(197, 165, 50, 0.1);
        }
        
        .form-group .help-text {
            font-size: 10px;
            color: #94a3b8;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            flex-wrap: wrap;
            padding-top: 20px;
            border-top: 1px solid #eef2f7;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* ===== ESTILOS PARA FOTO ===== */
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
        
        /* ===== VIDEO DA CÂMERA ===== */
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
        #videoContainer .cam-actions .btn-fechar-cam {
            background: #e74c3c;
            color: white;
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
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; max-width: 100%; padding: 15px; }
            .form-row { grid-template-columns: 1fr; }
            .form-row-3 { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .form-actions { flex-direction: column; }
            .form-actions button,
            .form-actions a { width: 100%; justify-content: center; }
            .foto-container { flex-direction: column; text-align: center; }
            .foto-actions .btn { min-width: 100%; }
            .foto-wrapper { width: 120px; height: 120px; }
            #videoContainer video { max-width: 95%; max-height: 60%; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">✏️ <span>Editar</span> Funcionário</h1>
                    <p class="page-subtitle">Atualize todas as informações do colaborador</p>
                </div>
                <div>
                    <a href="funcionarios.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
            </div>
            
            <!-- Info -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span>Editando: <strong><?= htmlspecialchars($funcionario['nome']) ?></strong> (ID: #<?= $funcionario['id'] ?>)</span>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Form -->
            <div class="form-card">
                <form method="POST" enctype="multipart/form-data" id="formFuncionario">
                    
                    <!-- ===== FOTO ===== -->
                    <div class="section-title">
                        <span class="icon">📸</span> Foto do Funcionário
                    </div>
                    
                    <div class="foto-container" id="fotoContainer">
                        <div class="foto-wrapper">
                            <?php 
                            $foto_path = !empty($funcionario['foto']) ? $funcionario['foto'] : '';
                            $foto_url = !empty($foto_path) ? '../../' . $foto_path : '';
                            $tem_foto = !empty($foto_path) && file_exists(__DIR__ . '/../' . $foto_path);
                            
                            if ($tem_foto): 
                            ?>
                                <img id="fotoPreviewImg" src="<?= $foto_url ?>" alt="Foto do Funcionário">
                                <div class="placeholder" id="fotoPlaceholder" style="display:none;">👤</div>
                            <?php else: ?>
                                <div class="placeholder" id="fotoPlaceholder">👤</div>
                                <img id="fotoPreviewImg" src="" style="display:none;">
                            <?php endif; ?>
                        </div>
                        
                        <div class="foto-actions">
                            <input type="file" id="fotoInput" name="foto" accept="image/*">
                            <input type="file" id="cameraInput" accept="image/*" capture="environment">
                            
                            <button type="button" class="btn btn-gold" onclick="document.getElementById('fotoInput').click()">
                                📁 Escolher Foto
                            </button>
                            <button type="button" class="btn btn-camera" onclick="abrirCamera()">
                                📷 Tirar Foto
                            </button>
                            <?php if ($tem_foto): ?>
                            <button type="button" class="btn btn-danger" onclick="removerFoto()">
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
                    
                    <!-- ===== DADOS PESSOAIS ===== -->
                    <div class="section-title">
                        <span class="icon">👤</span> Dados Pessoais
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nº de Agente</label>
                            <input type="text" name="num_agente" value="<?= htmlspecialchars($funcionario['num_agente'] ?? '') ?>" placeholder="Ex: 89327219">
                        </div>
                        <div class="form-group">
                            <label>Nome Completo <span class="required">*</span></label>
                            <input type="text" name="nome" value="<?= htmlspecialchars($funcionario['nome']) ?>" required placeholder="Digite o nome completo">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Género</label>
                            <select name="genero">
                                <option value="">Selecione</option>
                                <option value="M" <?= ($funcionario['genero'] ?? '') == 'M' ? 'selected' : '' ?>>Masculino (M)</option>
                                <option value="F" <?= ($funcionario['genero'] ?? '') == 'F' ? 'selected' : '' ?>>Feminino (F)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Data de Nascimento</label>
                            <input type="date" name="data_nascimento" value="<?= $funcionario['data_nascimento'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nº do BI</label>
                            <input type="text" name="num_bi" value="<?= htmlspecialchars($funcionario['num_bi'] ?? '') ?>" placeholder="Ex: 002172786LA036">
                        </div>
                        <div class="form-group">
                            <label>Contacto Telefónico</label>
                            <input type="text" name="contacto_telefonico" value="<?= htmlspecialchars($funcionario['contacto_telefonico'] ?? '') ?>" placeholder="Ex: 931 495 177">
                        </div>
                    </div>
                    
                    <!-- ===== DADOS PROFISSIONAIS ===== -->
                    <div class="section-title" style="margin-top: 30px;">
                        <span class="icon">💼</span> Dados Profissionais
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Categoria Actual</label>
                            <input type="text" name="categoria_actual" value="<?= htmlspecialchars($funcionario['categoria_actual'] ?? '') ?>" placeholder="Ex: Prof. Do Ens. Prim. E Sec. Do 12º Grau">
                        </div>
                        <div class="form-group">
                            <label>Instituição onde aufere o salário</label>
                            <input type="text" name="instituicao_salario" value="<?= htmlspecialchars($funcionario['instituicao_salario'] ?? '') ?>" placeholder="Ex: [Eqt] Complexo Escolar Nº 6006">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Função na Instituição</label>
                            <input type="text" name="funcao_instituicao" value="<?= htmlspecialchars($funcionario['funcao_instituicao'] ?? '') ?>" placeholder="Ex: Directora, Subd. Pedagógico">
                        </div>
                        <div class="form-group">
                            <label>Disciplina que Lecciona</label>
                            <input type="text" name="disciplina_lecciona" value="<?= htmlspecialchars($funcionario['disciplina_lecciona'] ?? '') ?>" placeholder="Ex: Matemática, Português">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Formação na Disciplina</label>
                            <input type="text" name="formacao_disciplina" value="<?= htmlspecialchars($funcionario['formacao_disciplina'] ?? '') ?>" placeholder="Ex: Sim / Não">
                        </div>
                        <div class="form-group">
                            <label>Data de Início de Função</label>
                            <input type="date" name="data_inicio_funcao" value="<?= $funcionario['data_inicio_funcao'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Data de Início na Instituição</label>
                            <input type="date" name="data_inicio_instituicao" value="<?= $funcionario['data_inicio_instituicao'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label>Data de Admissão <span class="required">*</span></label>
                            <input type="date" name="data_admissao" value="<?= $funcionario['data_admissao'] ?>" required>
                        </div>
                    </div>
                    
                    <!-- ===== HABILITAÇÕES ===== -->
                    <div class="section-title" style="margin-top: 30px;">
                        <span class="icon">📚</span> Habilitações Literárias
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label>Habilitações Literárias</label>
                            <input type="text" name="habilitacoes_literarias" value="<?= htmlspecialchars($funcionario['habilitacoes_literarias'] ?? '') ?>" placeholder="Ex: Licenciada, Mestre">
                        </div>
                        <div class="form-group">
                            <label>Especialidade Médio</label>
                            <input type="text" name="especialidade_medio" value="<?= htmlspecialchars($funcionario['especialidade_medio'] ?? '') ?>" placeholder="Ex: Geo/História">
                        </div>
                        <div class="form-group">
                            <label>Especialidade Superior</label>
                            <input type="text" name="especialidade_superior" value="<?= htmlspecialchars($funcionario['especialidade_superior'] ?? '') ?>" placeholder="Ex: Psic. Organizacional">
                        </div>
                    </div>
                    
                    <!-- ===== CONTATO E LOCALIZAÇÃO ===== -->
                    <div class="section-title" style="margin-top: 30px;">
                        <span class="icon">📍</span> Contacto e Localização
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($funcionario['email'] ?? '') ?>" placeholder="email@empresa.com">
                        </div>
                        <div class="form-group">
                            <label>Telefone</label>
                            <input type="text" name="telefone" value="<?= htmlspecialchars($funcionario['telefone'] ?? '') ?>" placeholder="Ex: 777777777">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Município de Residência</label>
                            <input type="text" name="municipio_residencia" value="<?= htmlspecialchars($funcionario['municipio_residencia'] ?? '') ?>" placeholder="Ex: Viana, Luanda">
                        </div>
                        <div class="form-group">
                            <label>IBAN</label>
                            <input type="text" name="iban" value="<?= htmlspecialchars($funcionario['iban'] ?? '') ?>" placeholder="Ex: AO06 0004 0000 0000 0000 0000 0">
                        </div>
                    </div>
                    
                    <!-- ===== DADOS DA EMPRESA ===== -->
                    <div class="section-title" style="margin-top: 30px;">
                        <span class="icon">🏢</span> Dados da Empresa
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Cargo <span class="required">*</span></label>
                            <input type="text" name="cargo" value="<?= htmlspecialchars($funcionario['cargo'] ?? '') ?>" required placeholder="Ex: Gerente, Analista">
                        </div>
                        <div class="form-group">
                            <label>Departamento <span class="required">*</span></label>
                            <input type="text" name="departamento" value="<?= htmlspecialchars($funcionario['departamento'] ?? '') ?>" required placeholder="Ex: TI, RH, Vendas">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Salário Base</label>
                            <input type="text" name="salario_base" value="<?= number_format($funcionario['salario_base'] ?? 0, 2, ',', '.') ?>" placeholder="0,00">
                            <span class="help-text">Use ponto para milhares e vírgula para decimais</span>
                        </div>
                        <div class="form-group">
                            <label>Salário <span class="required">*</span></label>
                            <input type="text" name="salario" value="<?= number_format($funcionario['salario'] ?? 0, 2, ',', '.') ?>" required placeholder="0,00">
                            <span class="help-text">Use ponto para milhares e vírgula para decimais</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Status <span class="required">*</span></label>
                            <select name="status" required>
                                <option value="ativo" <?= ($funcionario['status'] ?? 'ativo') == 'ativo' ? 'selected' : '' ?>>✅ Ativo</option>
                                <option value="ferias" <?= ($funcionario['status'] ?? '') == 'ferias' ? 'selected' : '' ?>>🏖️ Férias</option>
                                <option value="inativo" <?= ($funcionario['status'] ?? '') == 'inativo' ? 'selected' : '' ?>>⛔ Inativo</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <!-- Espaço vazio para alinhamento -->
                        </div>
                    </div>
                    
                    <!-- ===== BOTÕES ===== -->
                    <div class="form-actions">
                        <button type="submit" class="btn-gold"><i class="fas fa-save"></i> Atualizar Funcionário</button>
                        <a href="funcionarios.php" class="btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                        <a href="excluir_funcionario.php?id=<?= $funcionario['id'] ?>" class="btn-danger" onclick="return confirm('Tem certeza que deseja excluir este funcionário? Esta ação não pode ser desfeita!')">
                            <i class="fas fa-trash"></i> Excluir
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- ===== VIDEO DA CÂMERA ===== -->
    <div id="videoContainer">
        <video id="video" autoplay playsinline></video>
        <div class="cam-actions">
            <button class="btn-capturar" onclick="capturarFoto()">📸 Capturar</button>
            <button class="btn-fechar-cam" onclick="fecharCamera()">✕ Fechar</button>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
    // ============================================
    // FUNÇÕES PARA FOTO
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('📸 Sistema de foto inicializado - Edit Funcionário');
        
        const fotoInput = document.getElementById('fotoInput');
        if (fotoInput) {
            fotoInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    console.log('📁 Arquivo selecionado:', this.files[0].name);
                    previewFoto(this);
                }
            });
        }
        
        const cameraInput = document.getElementById('cameraInput');
        if (cameraInput) {
            cameraInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    console.log('📷 Foto da câmera:', this.files[0].name);
                    previewFoto(this);
                }
            });
        }
    });
    
    function previewFoto(input) {
        if (!input.files || input.files.length === 0) {
            console.log('⚠️ Nenhum arquivo selecionado');
            return;
        }
        
        const file = input.files[0];
        console.log('📁 Arquivo:', file.name, 'Tamanho:', file.size, 'Tipo:', file.type);
        
        if (file.size > 5 * 1024 * 1024) {
            alert('❌ O arquivo excede o tamanho máximo de 5MB!');
            input.value = '';
            return;
        }
        
        const tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!tiposPermitidos.includes(file.type)) {
            alert('❌ Formato não permitido! Use JPG, PNG, GIF ou WEBP.');
            input.value = '';
            return;
        }
        
        const img = document.getElementById('fotoPreviewImg');
        const placeholder = document.getElementById('fotoPlaceholder');
        const btnRemover = document.querySelector('.btn-remover');
        
        if (img) {
            const url = URL.createObjectURL(file);
            img.src = url;
            img.style.display = 'block';
            img.dataset.objectUrl = url;
            
            if (placeholder) placeholder.style.display = 'none';
            if (btnRemover) btnRemover.style.display = 'block';
            
            console.log('✅ Preview FORÇADO com sucesso!');
        }
        
        document.getElementById('removerFotoInput').value = 0;
        
        const info = document.getElementById('fotoInfo');
        if (info) {
            const tamanho = (file.size / 1024).toFixed(1);
            info.innerHTML = `<span class="success">✅ ${file.name} (${tamanho} KB) - PRONTO</span>`;
            info.className = 'foto-info success';
        }
    }
    
    function removerFoto() {
        if (!confirm('Tem certeza que deseja remover a foto do funcionário?')) {
            return;
        }
        
        const img = document.getElementById('fotoPreviewImg');
        const placeholder = document.getElementById('fotoPlaceholder');
        const btnRemover = document.querySelector('.btn-remover');
        const input = document.getElementById('fotoInput');
        const info = document.getElementById('fotoInfo');
        
        if (img) {
            if (img.dataset.objectUrl) {
                URL.revokeObjectURL(img.dataset.objectUrl);
            }
            img.src = '';
            img.style.display = 'none';
        }
        
        if (placeholder) placeholder.style.display = 'flex';
        if (btnRemover) btnRemover.style.display = 'none';
        if (input) input.value = '';
        
        document.getElementById('removerFotoInput').value = 1;
        
        if (info) {
            info.innerHTML = '🗑️ Foto removida';
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
    // MÁSCARAS
    // ============================================
    
    // Máscara para salário
    document.querySelectorAll('input[name="salario"], input[name="salario_base"]').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = (parseInt(value) / 100).toFixed(2);
                this.value = value.replace('.', ',');
            }
        });
    });
    
    // Máscara para IBAN
    document.querySelector('input[name="iban"]')?.addEventListener('input', function(e) {
        let value = this.value.replace(/\s/g, '').toUpperCase();
        let formatted = '';
        for (let i = 0; i < value.length; i += 4) {
            formatted += value.substr(i, 4) + ' ';
        }
        this.value = formatted.trim();
    });
    
    // ============================================
    // VALIDAÇÃO
    // ============================================
    
    document.getElementById('formFuncionario').addEventListener('submit', function(e) {
        const iban = document.querySelector('input[name="iban"]');
        if (iban && iban.value.trim() !== '') {
            const ibanClean = iban.value.replace(/\s/g, '');
            if (ibanClean.length < 20) {
                e.preventDefault();
                alert('Por favor, insira um IBAN válido (mínimo 20 caracteres)');
                iban.focus();
                return false;
            }
            if (!ibanClean.toUpperCase().startsWith('AO')) {
                e.preventDefault();
                alert('O IBAN deve começar com "AO" (código de Angola)');
                iban.focus();
                return false;
            }
        }
    });
    
    console.log('📸 Sistema de foto EDIT FUNCIONÁRIO carregado!');
    </script>
</body>
</html>