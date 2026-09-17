<?php
require_once '../../config/database.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header("Location: " . SITE_URL . "login.php");
    exit;
}

$empresa = getEmpresa();
$mensagem = '';
$tipoMensagem = '';
$logo_upload_success = false;

// Função para upload de logo (CORRIGIDA)
function uploadLogo($file) {
    // Caminho absoluto para a pasta de uploads
    $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/assets/uploads/';
    
    // Criar pasta se não existir
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Verificar se é uma imagem válida
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ['success' => false, 'message' => 'O arquivo não é uma imagem válida.'];
    }
    
    // Verificar tamanho (máx 2MB)
    if ($file["size"] > 2000000) {
        return ['success' => false, 'message' => 'A imagem deve ter no máximo 2MB.'];
    }
    
    // Verificar formato
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_formats = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
    if (!in_array($file_extension, $allowed_formats)) {
        return ['success' => false, 'message' => 'Formato não permitido. Use JPG, PNG, GIF, SVG ou WEBP.'];
    }
    
    // Gerar nome único
    $new_filename = "logo_" . time() . "_" . uniqid() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Fazer upload
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => true, 'filename' => $new_filename];
    } else {
        return ['success' => false, 'message' => 'Erro ao fazer upload da imagem. Verifique as permissões da pasta.'];
    }
}

// Remover logo
if (isset($_POST['remover_logo'])) {
    // Remover arquivo físico
    if (!empty($empresa['logo'])) {
        $old_logo_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/assets/uploads/' . $empresa['logo'];
        if (file_exists($old_logo_path)) {
            unlink($old_logo_path);
        }
    }
    
    $dados = $empresa;
    $dados['logo'] = null;
    if (updateEmpresa($dados)) {
        $empresa = getEmpresa();
        $mensagem = '✅ Logo removido com sucesso!';
        $tipoMensagem = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['remover_logo'])) {
    // Limpar telefones
    $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone'] ?? '');
    $celular = preg_replace('/[^0-9]/', '', $_POST['celular'] ?? '');
    
    if (empty($telefone)) $telefone = '222646242';
    if (empty($celular)) $celular = '946646242';
    
    $dados = [
        'razao_social' => $_POST['razao_social'] ?? '',
        'nome_fantasia' => $_POST['nome_fantasia'] ?? '',
        'nif' => $_POST['nif'] ?? '',
        'inscricao_estadual' => $_POST['inscricao_estadual'] ?? '',
        'inscricao_municipal' => $_POST['inscricao_municipal'] ?? '',
        'endereco' => $_POST['endereco'] ?? '',
        'numero' => $_POST['numero'] ?? '',
        'complemento' => $_POST['complemento'] ?? '',
        'bairro' => $_POST['bairro'] ?? '',
        'cidade' => $_POST['cidade'] ?? '',
        'pais' => $_POST['pais'] ?? '',
        'cep' => $_POST['cep'] ?? '',
        'telefone' => $telefone,
        'celular' => $celular,
        'email' => $_POST['email'] ?? '',
        'site' => $_POST['site'] ?? ''
    ];
    
    // Processar upload do logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0 && $_FILES['logo']['size'] > 0) {
        $upload_result = uploadLogo($_FILES['logo']);
        if ($upload_result['success']) {
            // Remover logo antigo
            if (!empty($empresa['logo'])) {
                $old_logo_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/assets/uploads/' . $empresa['logo'];
                if (file_exists($old_logo_path)) {
                    unlink($old_logo_path);
                }
            }
            
            $dados['logo'] = $upload_result['filename'];
            $logo_upload_success = true;
            $mensagem = '✅ Logo atualizado com sucesso!';
            $tipoMensagem = 'success';
        } else {
            $mensagem = '❌ ' . $upload_result['message'];
            $tipoMensagem = 'error';
            $dados['logo'] = $empresa['logo'] ?? null;
        }
    } else {
        $dados['logo'] = $empresa['logo'] ?? null;
    }
    
    // Atualizar dados
    if (updateEmpresa($dados)) {
        if (empty($mensagem)) {
            $mensagem = '✅ Dados atualizados com sucesso!';
            $tipoMensagem = 'success';
        }
        $empresa = getEmpresa();
    } else {
        $mensagem = '❌ Erro ao atualizar os dados. Tente novamente.';
        $tipoMensagem = 'error';
    }
}

// Função updateEmpresa
if (!function_exists('updateEmpresa')) {
    function updateEmpresa($dados) {
        global $pdo;
        try {
            // Verificar se a tabela empresa existe
            $tableExists = $pdo->query("SHOW TABLES LIKE 'empresa'")->rowCount() > 0;
            
            if (!$tableExists) {
                $sql = "CREATE TABLE IF NOT EXISTS empresa (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    razao_social VARCHAR(255),
                    nome_fantasia VARCHAR(255),
                    nif VARCHAR(50),
                    inscricao_estadual VARCHAR(50),
                    inscricao_municipal VARCHAR(50),
                    endereco VARCHAR(255),
                    numero VARCHAR(20),
                    complemento VARCHAR(100),
                    bairro VARCHAR(100),
                    cidade VARCHAR(100),
                    pais VARCHAR(100),
                    cep VARCHAR(20),
                    telefone VARCHAR(20),
                    celular VARCHAR(20),
                    email VARCHAR(100),
                    site VARCHAR(100),
                    logo VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $pdo->exec($sql);
            }
            
            // Verificar se já existe
            $stmt = $pdo->query("SELECT id FROM empresa LIMIT 1");
            $exists = $stmt->fetch();
            
            if ($exists) {
                $sql = "UPDATE empresa SET 
                    razao_social = :razao_social,
                    nome_fantasia = :nome_fantasia,
                    nif = :nif,
                    inscricao_estadual = :inscricao_estadual,
                    inscricao_municipal = :inscricao_municipal,
                    endereco = :endereco,
                    numero = :numero,
                    complemento = :complemento,
                    bairro = :bairro,
                    cidade = :cidade,
                    pais = :pais,
                    cep = :cep,
                    telefone = :telefone,
                    celular = :celular,
                    email = :email,
                    site = :site,
                    logo = :logo
                    WHERE id = :id";
                $dados['id'] = $exists['id'];
            } else {
                $sql = "INSERT INTO empresa (
                    razao_social, nome_fantasia, nif, inscricao_estadual, inscricao_municipal,
                    endereco, numero, complemento, bairro, cidade, pais, cep,
                    telefone, celular, email, site, logo
                ) VALUES (
                    :razao_social, :nome_fantasia, :nif, :inscricao_estadual, :inscricao_municipal,
                    :endereco, :numero, :complemento, :bairro, :cidade, :pais, :cep,
                    :telefone, :celular, :email, :site, :logo
                )";
            }
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($dados);
            
        } catch (PDOException $e) {
            error_log("Erro ao atualizar empresa: " . $e->getMessage());
            return false;
        }
    }
}

// Função getEmpresa
if (!function_exists('getEmpresa')) {
    function getEmpresa() {
        global $pdo;
        try {
            $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
            return $empresa ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Empresa - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #c9a84c;
            --primary-dark: #b8973a;
            --primary-light: #f5edd6;
            --secondary: #1a2332;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.12);
            --radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
        }

        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .content-area {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            background: var(--gray-50);
            min-height: 100vh;
        }

        .container-modern {
            max-width: 900px;
            margin: 0 auto;
        }

        .form-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 35px;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }

        .form-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .form-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--gray-100);
        }

        .form-header .header-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
            flex-shrink: 0;
        }

        .form-header .header-title h2 {
            color: var(--gray-900);
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .form-header .header-title p {
            color: var(--gray-500);
            font-size: 14px;
            margin: 4px 0 0;
        }

        .alert-modern {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
            animation: fadeInUp 0.3s ease-out;
        }

        .alert-modern i {
            font-size: 20px;
            flex-shrink: 0;
        }

        .alert-modern .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.7;
            transition: var(--transition);
            padding: 0 5px;
        }

        .alert-modern .alert-close:hover {
            opacity: 1;
        }

        .alert-success {
            background: #f0fff4;
            border-color: #c6f6d5;
            color: #22543d;
        }

        .alert-success i {
            color: #38a169;
        }

        .alert-error {
            background: #fff5f5;
            border-color: #fed7d7;
            color: #9b2c2c;
        }

        .alert-error i {
            color: #e53e3e;
        }

        .section-title {
            color: var(--gray-800);
            font-size: 18px;
            font-weight: 700;
            margin: 30px 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title:first-of-type {
            margin-top: 0;
        }

        .section-title i {
            color: var(--primary);
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 5px;
        }

        .form-grid .full-width {
            grid-column: 1 / -1;
        }

        .form-group {
            margin-bottom: 5px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
            font-size: 14px;
        }

        .form-group label .optional {
            font-size: 11px;
            color: var(--gray-400);
            font-weight: 400;
            margin-left: 5px;
        }

        .form-group label .required {
            color: #e53e3e;
            margin-left: 3px;
        }

        .form-group input[type="text"],
        .form-group input[type="tel"],
        .form-group input[type="email"],
        .form-group input[type="url"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 14px;
            color: var(--gray-800);
            transition: var(--transition);
            background: var(--gray-50);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary);
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px rgba(201, 168, 76, 0.15);
        }

        .form-group input:hover,
        .form-group select:hover {
            border-color: var(--gray-300);
        }

        .form-group .hint {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-group .hint i {
            font-size: 12px;
            color: var(--primary);
        }

        .form-group .hint .example {
            color: var(--primary);
            font-weight: 600;
        }

        /* Logo Section */
        .logo-section {
            display: flex;
            gap: 35px;
            align-items: flex-start;
            flex-wrap: wrap;
            padding: 25px;
            background: var(--gray-50);
            border-radius: var(--radius);
            border: 2px dashed var(--gray-300);
            transition: var(--transition);
        }

        .logo-section:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .logo-preview {
            width: 150px;
            height: 150px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: white;
            flex-shrink: 0;
            transition: var(--transition);
        }

        .logo-preview:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 15px;
        }

        .logo-preview .placeholder {
            color: var(--gray-400);
            text-align: center;
            padding: 20px;
        }

        .logo-preview .placeholder i {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
            color: var(--gray-300);
        }

        .logo-preview .placeholder span {
            font-size: 13px;
            display: block;
        }

        .logo-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding-top: 5px;
            flex: 1;
        }

        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-wrapper .btn-upload {
            background: white;
            color: var(--gray-700);
            padding: 12px 28px;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            width: 100%;
            justify-content: center;
        }

        .file-upload-wrapper .btn-upload:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .file-upload-wrapper .btn-upload i {
            font-size: 18px;
        }

        .file-upload-wrapper .file-name {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 5px;
            display: block;
            text-align: center;
        }

        .logo-info {
            font-size: 13px;
            color: var(--gray-600);
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logo-info i {
            color: #38a169;
        }

        .btn-remove-logo {
            background: #fff5f5;
            color: #9b2c2c;
            border: 1px solid #fed7d7;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
        }

        .btn-remove-logo:hover {
            background: #fed7d7;
            transform: scale(1.02);
        }

        .form-actions {
            margin-top: 35px;
            padding-top: 25px;
            border-top: 2px solid var(--gray-100);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--secondary);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(201, 168, 76, 0.4);
            color: var(--secondary);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 2px solid var(--gray-200);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
        }

        @media (max-width: 992px) {
            .content-area {
                margin-left: 0;
                padding: 15px;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .form-grid .full-width {
                grid-column: 1;
            }
            .form-card {
                padding: 25px;
            }
        }

        @media (max-width: 768px) {
            .content-area {
                padding: 10px;
            }
            .form-card {
                padding: 20px;
            }
            .form-header {
                flex-direction: column;
                text-align: center;
            }
            .logo-section {
                flex-direction: column;
                align-items: center;
                padding: 20px;
            }
            .logo-actions {
                align-items: center;
                width: 100%;
            }
            .file-upload-wrapper {
                width: 100%;
            }
            .file-upload-wrapper .btn-upload {
                width: 100%;
                justify-content: center;
            }
            .form-actions {
                flex-direction: column;
            }
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-card {
            animation: fadeInUp 0.5s ease-out;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include '../../includes/header.php'; ?>
        
        <div class="content-area">
            <div class="container-modern">
                <div class="form-card">
                    <div class="form-header">
                        <div class="header-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="header-title">
                            <h2><i class="fas fa-edit" style="color: var(--primary);"></i> Editar Dados da Empresa</h2>
                            <p><i class="fas fa-info-circle"></i> Atualize as informações da sua empresa que aparecerão nos documentos</p>
                        </div>
                    </div>
                    
                    <?php if ($mensagem): ?>
                        <div class="alert-modern alert-<?= $tipoMensagem ?>">
                            <i class="fas fa-<?= $tipoMensagem == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                            <span><?= $mensagem ?></span>
                            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="formEmpresa">
                        <!-- Logo -->
                        <div class="section-title">
                            <i class="fas fa-image"></i>
                            Logo da Empresa
                        </div>
                        
                        <div class="logo-section">
                            <div class="logo-preview">
                                <?php 
                                $has_logo = !empty($empresa['logo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/softgest_web/assets/uploads/' . $empresa['logo']);
                                ?>
                                <?php if ($has_logo): ?>
                                    <img src="../../assets/uploads/<?= $empresa['logo'] ?>?t=<?= time() ?>" alt="Logo da Empresa" id="logoPreview">
                                <?php else: ?>
                                    <div class="placeholder" id="placeholderLogo">
                                        <i class="fas fa-building"></i>
                                        <span>Sem logo</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="logo-actions">
                                <div class="file-upload-wrapper">
                                    <span class="btn-upload">
                                        <i class="fas fa-upload"></i> Selecionar Logo
                                    </span>
                                    <input type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp" id="logoInput">
                                    <span class="file-name" id="fileName">Nenhum arquivo selecionado</span>
                                </div>
                                <small style="color: var(--gray-500); font-size: 12px;">
                                    <i class="fas fa-info-circle"></i>
                                    Formatos: JPG, PNG, GIF, SVG, WEBP | Máx: 2MB
                                </small>
                                <?php if ($has_logo): ?>
                                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                        <span class="logo-info">
                                            <i class="fas fa-check-circle"></i>
                                            Logo atual: <?= htmlspecialchars($empresa['logo']) ?>
                                        </span>
                                        <button type="button" class="btn-remove-logo" onclick="removerLogo()">
                                            <i class="fas fa-trash"></i> Remover
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Dados Principais -->
                        <div class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Dados Principais
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Razão Social <span class="required">*</span></label>
                                <input type="text" name="razao_social" 
                                       value="<?= htmlspecialchars($empresa['razao_social'] ?? '') ?>" 
                                       required placeholder="Ex: SoftGest Tecnologia Ltda">
                            </div>
                            <div class="form-group">
                                <label>Nome Fantasia</label>
                                <input type="text" name="nome_fantasia" 
                                       value="<?= htmlspecialchars($empresa['nome_fantasia'] ?? '') ?>" 
                                       placeholder="Ex: SoftGest Web">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>NIF <span class="required">*</span></label>
                                <input type="text" name="nif" 
                                       value="<?= htmlspecialchars($empresa['nif'] ?? '') ?>" 
                                       required placeholder="Ex: 5001234567">
                                <div class="hint">
                                    <i class="fas fa-info-circle"></i>
                                    Número inteiro, sem pontos ou traços. Ex: <span class="example">5001234567</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Inscrição Estadual <span class="optional">(opcional)</span></label>
                                <input type="text" name="inscricao_estadual" 
                                       value="<?= htmlspecialchars($empresa['inscricao_estadual'] ?? '') ?>" 
                                       placeholder="Opcional">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Inscrição Municipal <span class="optional">(opcional)</span></label>
                                <input type="text" name="inscricao_municipal" 
                                       value="<?= htmlspecialchars($empresa['inscricao_municipal'] ?? '') ?>" 
                                       placeholder="Opcional">
                            </div>
                        </div>
                        
                        <!-- Endereço -->
                        <div class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Endereço
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Endereço</label>
                                <input type="text" name="endereco" 
                                       value="<?= htmlspecialchars($empresa['endereco'] ?? '') ?>" 
                                       placeholder="Rua, Avenida...">
                            </div>
                            <div class="form-group">
                                <label>Número</label>
                                <input type="text" name="numero" 
                                       value="<?= htmlspecialchars($empresa['numero'] ?? '') ?>" 
                                       placeholder="Nº">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Complemento</label>
                                <input type="text" name="complemento" 
                                       value="<?= htmlspecialchars($empresa['complemento'] ?? '') ?>" 
                                       placeholder="Complemento">
                            </div>
                            <div class="form-group">
                                <label>Bairro</label>
                                <input type="text" name="bairro" 
                                       value="<?= htmlspecialchars($empresa['bairro'] ?? '') ?>" 
                                       placeholder="Bairro">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Cidade</label>
                                <input type="text" name="cidade" 
                                       value="<?= htmlspecialchars($empresa['cidade'] ?? '') ?>" 
                                       placeholder="Cidade">
                            </div>
                            <div class="form-group">
                                <label>País</label>
                                <select name="pais">
                                    <option value="">Selecione o País</option>
                                    <option value="Angola" <?= ($empresa['pais'] ?? '') == 'Angola' ? 'selected' : '' ?>>🇦🇴 Angola</option>
                                    <option value="África do Sul" <?= ($empresa['pais'] ?? '') == 'África do Sul' ? 'selected' : '' ?>>🇿🇦 África do Sul</option>
                                    <option value="Brasil" <?= ($empresa['pais'] ?? '') == 'Brasil' ? 'selected' : '' ?>>🇧🇷 Brasil</option>
                                    <option value="Portugal" <?= ($empresa['pais'] ?? '') == 'Portugal' ? 'selected' : '' ?>>🇵🇹 Portugal</option>
                                    <option value="Moçambique" <?= ($empresa['pais'] ?? '') == 'Moçambique' ? 'selected' : '' ?>>🇲🇿 Moçambique</option>
                                    <option value="Cabo Verde" <?= ($empresa['pais'] ?? '') == 'Cabo Verde' ? 'selected' : '' ?>>🇨🇻 Cabo Verde</option>
                                    <option value="São Tomé e Príncipe" <?= ($empresa['pais'] ?? '') == 'São Tomé e Príncipe' ? 'selected' : '' ?>>🇸🇹 São Tomé e Príncipe</option>
                                    <option value="Guiné-Bissau" <?= ($empresa['pais'] ?? '') == 'Guiné-Bissau' ? 'selected' : '' ?>>🇬🇼 Guiné-Bissau</option>
                                    <option value="Timor-Leste" <?= ($empresa['pais'] ?? '') == 'Timor-Leste' ? 'selected' : '' ?>>🇹🇱 Timor-Leste</option>
                                    <option value="EUA" <?= ($empresa['pais'] ?? '') == 'EUA' ? 'selected' : '' ?>>🇺🇸 EUA</option>
                                    <option value="Reino Unido" <?= ($empresa['pais'] ?? '') == 'Reino Unido' ? 'selected' : '' ?>>🇬🇧 Reino Unido</option>
                                    <option value="França" <?= ($empresa['pais'] ?? '') == 'França' ? 'selected' : '' ?>>🇫🇷 França</option>
                                    <option value="Alemanha" <?= ($empresa['pais'] ?? '') == 'Alemanha' ? 'selected' : '' ?>>🇩🇪 Alemanha</option>
                                    <option value="Espanha" <?= ($empresa['pais'] ?? '') == 'Espanha' ? 'selected' : '' ?>>🇪🇸 Espanha</option>
                                    <option value="Itália" <?= ($empresa['pais'] ?? '') == 'Itália' ? 'selected' : '' ?>>🇮🇹 Itália</option>
                                    <option value="China" <?= ($empresa['pais'] ?? '') == 'China' ? 'selected' : '' ?>>🇨🇳 China</option>
                                    <option value="Japão" <?= ($empresa['pais'] ?? '') == 'Japão' ? 'selected' : '' ?>>🇯🇵 Japão</option>
                                    <option value="Outro" <?= ($empresa['pais'] ?? '') == 'Outro' ? 'selected' : '' ?>>🌍 Outro</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>CEP</label>
                                <input type="text" name="cep" 
                                       value="<?= htmlspecialchars($empresa['cep'] ?? '') ?>" 
                                       placeholder="00000-000">
                            </div>
                        </div>
                        
                        <!-- Contato -->
                        <div class="section-title">
                            <i class="fas fa-phone"></i>
                            Contato
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Telefone</label>
                                <input type="tel" name="telefone" 
                                       value="<?= htmlspecialchars($empresa['telefone'] ?? '222 646242') ?>" 
                                       placeholder="222 646242">
                                <div class="hint">
                                    <i class="fas fa-info-circle"></i>
                                    Exemplo: <span class="example">222 646242</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Celular</label>
                                <input type="tel" name="celular" 
                                       value="<?= htmlspecialchars($empresa['celular'] ?? '946 646242') ?>" 
                                       placeholder="946 646242">
                                <div class="hint">
                                    <i class="fas fa-info-circle"></i>
                                    Exemplo: <span class="example">946 646242</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>E-mail</label>
                                <input type="email" name="email" 
                                       value="<?= htmlspecialchars($empresa['email'] ?? '') ?>" 
                                       placeholder="contato@empresa.com">
                            </div>
                            <div class="form-group">
                                <label>Site <span class="optional">(opcional)</span></label>
                                <input type="text" name="site" 
                                       value="<?= htmlspecialchars($empresa['site'] ?? '') ?>" 
                                       placeholder="www.empresa.com">
                                <div class="hint">
                                    <i class="fas fa-info-circle"></i>
                                    Campo opcional - deixe em branco se não tiver site
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Dados
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Remover logo
    function removerLogo() {
        if (confirm('Deseja remover o logo da empresa?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remover_logo';
            input.value = '1';
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Preview do logo antes de enviar
    document.getElementById('logoInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const fileNameSpan = document.getElementById('fileName');
        const logoPreview = document.getElementById('logoPreview');
        const placeholderLogo = document.getElementById('placeholderLogo');
        
        if (file) {
            // Mostrar nome do arquivo
            fileNameSpan.textContent = file.name;
            
            // Preview da imagem
            const reader = new FileReader();
            reader.onload = function(e) {
                if (logoPreview) {
                    logoPreview.src = e.target.result;
                } else {
                    // Criar preview se não existir
                    const previewDiv = document.querySelector('.logo-preview');
                    const img = document.createElement('img');
                    img.id = 'logoPreview';
                    img.src = e.target.result;
                    img.alt = 'Logo Preview';
                    
                    // Remover placeholder
                    const placeholder = document.getElementById('placeholderLogo');
                    if (placeholder) {
                        placeholder.remove();
                    }
                    previewDiv.appendChild(img);
                }
            };
            reader.readAsDataURL(file);
        } else {
            fileNameSpan.textContent = 'Nenhum arquivo selecionado';
        }
    });
    
    // Fechar alertas automaticamente
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.querySelector('.alert-modern');
        if (alert) {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        }
    });
    </script>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>