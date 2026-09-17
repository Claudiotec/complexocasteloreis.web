<?php
// ============================================
// modules/usuarios/edit.php - Editar Usuário
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('usuarios', 'editar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== VARIÁVEIS =====
$id = $_GET['id'] ?? 0;
$usuario = null;
$mensagem = '';
$tipo_mensagem = '';
$erros = [];

// ===== VALIDAR ID =====
if ($id <= 0) {
    $_SESSION['mensagem'] = "ID de usuário inválido";
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: index.php');
    exit;
}

// ===== BUSCAR DADOS DO USUÁRIO =====
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem = "Erro ao buscar usuário: " . $e->getMessage();
    $tipo_mensagem = 'danger';
}

if (!$usuario) {
    $_SESSION['mensagem'] = "Usuário não encontrado";
    $_SESSION['tipo_mensagem'] = 'warning';
    header('Location: index.php');
    exit;
}

// ===== PROCESSAR FORMULÁRIO =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $perfil = $_POST['perfil'] ?? 'usuario';
    $status = $_POST['status'] ?? 'ativo';
    $senha = trim($_POST['senha'] ?? '');
    
    // ===== MULTI PERFIL - PERMISSÕES ADICIONAIS =====
    $multi_perfil = isset($_POST['multi_perfil']) ? $_POST['multi_perfil'] : [];
    $multi_perfil_str = implode(',', $multi_perfil);
    
    // ===== VALIDAÇÕES =====
    if (empty($nome)) {
        $erros[] = "Nome é obrigatório";
    }
    
    if (empty($email)) {
        $erros[] = "Email é obrigatório";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = "Email inválido";
    }
    
    // Verificar se email já existe (exceto o próprio usuário)
    if (!empty($email)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                $erros[] = "Este email já está em uso por outro usuário";
            }
        } catch (PDOException $e) {
            // Ignorar erro
        }
    }
    
    // ===== ATUALIZAR =====
    if (empty($erros)) {
        try {
            if (!empty($senha)) {
                // Atualizar com senha
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET 
                        nome = ?, 
                        email = ?, 
                        perfil = ?, 
                        status = ?, 
                        senha = ?, 
                        multi_perfil = ?,
                        updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $email, $perfil, $status, $senha_hash, $multi_perfil_str, $id]);
            } else {
                // Atualizar sem senha
                $sql = "UPDATE usuarios SET 
                        nome = ?, 
                        email = ?, 
                        perfil = ?, 
                        status = ?, 
                        multi_perfil = ?,
                        updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $email, $perfil, $status, $multi_perfil_str, $id]);
            }
            
            $_SESSION['mensagem'] = "✅ Usuário atualizado com sucesso!";
            $_SESSION['tipo_mensagem'] = 'success';
            header('Location: index.php');
            exit;
            
        } catch (PDOException $e) {
            $mensagem = "Erro ao atualizar usuário: " . $e->getMessage();
            $tipo_mensagem = 'danger';
            error_log("Erro ao atualizar usuário ID $id: " . $e->getMessage());
        }
    } else {
        $mensagem = implode('<br>', $erros);
        $tipo_mensagem = 'warning';
    }
}

// ===== INCLUIR HEADER =====
include '../../includes/header.php';
?>

<style>
    /* ===== ESTILOS PROFISSIONAIS ===== */
    :root {
        --primary-color: #c9a84c;
        --primary-dark: #b8973a;
        --primary-light: #f5edd6;
        --secondary-color: #1a2332;
        --text-color: #2d3748;
        --text-muted: #718096;
        --border-color: #e2e8f0;
        --bg-light: #f7fafc;
        --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
        --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
        --shadow-lg: 0 10px 40px rgba(0,0,0,0.12);
        --radius: 16px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .edit-user-wrapper {
        padding: 30px 20px;
        max-width: 900px;
        margin: 0 auto;
    }

    .page-header-modern {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
    }

    .page-header-modern .header-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .page-header-modern .header-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }

    .page-header-modern h2 {
        font-size: 24px;
        font-weight: 700;
        color: var(--secondary-color);
        margin: 0;
        letter-spacing: -0.5px;
    }

    .page-header-modern .subtitle {
        color: var(--text-muted);
        font-size: 14px;
        margin: 0;
    }

    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        color: var(--text-color);
        font-weight: 500;
        text-decoration: none;
        transition: var(--transition);
    }

    .btn-back:hover {
        background: var(--bg-light);
        transform: translateX(-3px);
        box-shadow: var(--shadow-sm);
    }

    .card-modern {
        background: white;
        border-radius: var(--radius);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
        overflow: hidden;
        transition: var(--transition);
    }

    .card-modern:hover {
        box-shadow: var(--shadow-lg);
    }

    .card-modern .card-header-modern {
        padding: 25px 30px;
        background: linear-gradient(135deg, var(--secondary-color), #2d3748);
        border-bottom: none;
        position: relative;
        overflow: hidden;
    }

    .card-modern .card-header-modern::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 200px;
        height: 200px;
        background: rgba(201, 168, 76, 0.1);
        border-radius: 50%;
    }

    .card-modern .card-header-modern h5 {
        color: white;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
        z-index: 1;
    }

    .card-modern .card-header-modern .badge-status-header {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
        margin-left: 10px;
    }

    .card-modern .card-body-modern {
        padding: 30px;
    }

    .form-group-modern {
        margin-bottom: 24px;
    }

    .form-group-modern .form-label-modern {
        display: block;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 8px;
        font-size: 14px;
    }

    .form-group-modern .form-label-modern .required {
        color: #e53e3e;
        margin-left: 3px;
    }

    .form-group-modern .form-label-modern .label-icon {
        margin-right: 8px;
        opacity: 0.7;
    }

    .form-control-modern {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 14px;
        color: var(--text-color);
        transition: var(--transition);
        background: var(--bg-light);
        outline: none;
    }

    .form-control-modern:focus {
        border-color: var(--primary-color);
        background: white;
        box-shadow: 0 0 0 4px rgba(201, 168, 76, 0.15);
    }

    .form-control-modern:hover {
        border-color: #cbd5e0;
    }

    .form-control-modern::placeholder {
        color: #a0aec0;
    }

    select.form-control-modern {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234a5568' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        padding-right: 40px;
        cursor: pointer;
    }

    /* ===== MULTI SELECT ESTILOS ===== */
    select[multiple].form-control-modern {
        min-height: 200px;
        padding: 8px 12px;
        background-image: none;
        cursor: pointer;
    }

    select[multiple].form-control-modern option {
        padding: 8px 14px;
        border-radius: 6px;
        margin-bottom: 2px;
        cursor: pointer;
        transition: var(--transition);
        font-size: 13px;
    }

    select[multiple].form-control-modern option:hover {
        background: var(--primary-light);
    }

    select[multiple].form-control-modern option:checked {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: var(--secondary-color);
        font-weight: 600;
    }

    select[multiple].form-control-modern option:checked::before {
        content: '✅ ';
    }

    .selected-count {
        display: inline-block;
        background: var(--primary-color);
        color: var(--secondary-color);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        margin-left: 10px;
    }

    .perfil-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
        padding: 8px;
        background: var(--bg-light);
        border-radius: 8px;
        min-height: 40px;
        border: 1px dashed var(--border-color);
    }

    .perfil-tag {
        background: white;
        border: 1px solid var(--border-color);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: var(--shadow-sm);
    }

    .perfil-tag .remove-tag {
        cursor: pointer;
        color: #e53e3e;
        font-weight: 700;
        margin-left: 4px;
    }

    .perfil-tag .remove-tag:hover {
        color: #c53030;
    }

    .form-control-modern.error {
        border-color: #e53e3e;
        background: #fff5f5;
    }

    .form-control-modern.success {
        border-color: #38a169;
        background: #f0fff4;
    }

    .form-text-modern {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: var(--text-muted);
    }

    .form-text-modern i {
        margin-right: 5px;
    }

    .btn-modern {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 28px;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        transition: var(--transition);
        cursor: pointer;
        text-decoration: none;
    }

    .btn-modern-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: var(--secondary-color);
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }

    .btn-modern-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(201, 168, 76, 0.4);
        color: var(--secondary-color);
    }

    .btn-modern-secondary {
        background: var(--bg-light);
        color: var(--text-color);
        border: 2px solid var(--border-color);
    }

    .btn-modern-secondary:hover {
        background: white;
        border-color: #cbd5e0;
        transform: translateY(-2px);
    }

    .btn-modern-danger {
        background: #fff5f5;
        color: #e53e3e;
        border: 2px solid #fed7d7;
    }

    .btn-modern-danger:hover {
        background: #fed7d7;
        transform: translateY(-2px);
    }

    .alert-modern {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        border: 1px solid transparent;
    }

    .alert-modern .alert-icon {
        font-size: 20px;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .alert-modern .alert-content {
        flex: 1;
    }

    .alert-modern .alert-content strong {
        display: block;
        margin-bottom: 2px;
    }

    .alert-modern .btn-close-modern {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: inherit;
        opacity: 0.7;
        padding: 0 0 0 10px;
        transition: var(--transition);
    }

    .alert-modern .btn-close-modern:hover {
        opacity: 1;
    }

    .alert-success {
        background: #f0fff4;
        border-color: #c6f6d5;
        color: #22543d;
    }

    .alert-danger {
        background: #fff5f5;
        border-color: #fed7d7;
        color: #9b2c2c;
    }

    .alert-warning {
        background: #fffff0;
        border-color: #fefcbf;
        color: #744210;
    }

    .alert-info {
        background: #ebf8ff;
        border-color: #bee3f8;
        color: #2a4365;
    }

    .info-card-modern {
        background: var(--bg-light);
        border-radius: 12px;
        padding: 16px 20px;
        margin-top: 20px;
        border: 1px solid var(--border-color);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 20px;
    }

    .info-card-modern .info-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--text-muted);
    }

    .info-card-modern .info-item strong {
        color: var(--text-color);
    }

    .info-card-modern .info-divider {
        width: 1px;
        height: 24px;
        background: var(--border-color);
    }

    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .optgroup-header {
        font-weight: 700;
        color: var(--secondary-color);
        background: var(--bg-light);
        padding: 4px 8px;
        border-radius: 4px;
        margin: 2px 0;
    }

    @media (max-width: 768px) {
        .grid-2 {
            grid-template-columns: 1fr;
        }
        
        .edit-user-wrapper {
            padding: 15px 10px;
        }

        .page-header-modern {
            flex-direction: column;
            align-items: stretch;
            gap: 15px;
        }

        .page-header-modern .header-left {
            gap: 12px;
        }

        .page-header-modern .header-icon {
            width: 40px;
            height: 40px;
            font-size: 20px;
        }

        .page-header-modern h2 {
            font-size: 20px;
        }

        .card-modern .card-header-modern {
            padding: 18px 20px;
        }

        .card-modern .card-body-modern {
            padding: 20px;
        }

        .btn-modern {
            padding: 10px 20px;
            font-size: 13px;
            justify-content: center;
        }

        .info-card-modern {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }

        .info-card-modern .info-divider {
            display: none;
        }

        select[multiple].form-control-modern {
            min-height: 150px;
        }
    }

    @media (max-width: 480px) {
        .form-group-modern {
            margin-bottom: 18px;
        }

        .form-control-modern {
            padding: 10px 14px;
            font-size: 13px;
        }

        .card-modern .card-body-modern {
            padding: 15px;
        }
        
        select[multiple].form-control-modern {
            min-height: 120px;
        }

        select[multiple].form-control-modern option {
            padding: 6px 10px;
            font-size: 12px;
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

    .card-modern {
        animation: fadeInUp 0.5s ease-out;
    }

    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: var(--bg-light);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }

    .btn-modern .spinner {
        display: none;
        width: 18px;
        height: 18px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: var(--secondary-color);
        animation: spin 0.8s linear infinite;
    }

    .btn-modern.loading .spinner {
        display: inline-block;
    }

    .btn-modern.loading .btn-text {
        opacity: 0.7;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Ícones de perfil */
    .perfil-icon {
        font-size: 16px;
    }
</style>

<!-- ===== CONTEÚDO PRINCIPAL ===== -->
<div class="edit-user-wrapper">
    <!-- Cabeçalho -->
    <div class="page-header-modern">
        <div class="header-left">
            <div class="header-icon">👤</div>
            <div>
                <h2>Editar Usuário</h2>
                <p class="subtitle">Atualize as informações do usuário no sistema</p>
            </div>
        </div>
        <a href="index.php" class="btn-back">
            <span>←</span> Voltar
        </a>
    </div>

    <!-- Card Principal -->
    <div class="card-modern">
        <div class="card-header-modern">
            <h5>
                ✏️ Editar: <?= htmlspecialchars($usuario['nome'] ?? '') ?>
                <span class="badge-status-header">
                    <?= ($usuario['status'] ?? '') == 'ativo' ? '🟢 Ativo' : '🔴 Inativo' ?>
                </span>
            </h5>
        </div>
        <div class="card-body-modern">
            <!-- Mensagens -->
            <?php if ($mensagem): ?>
                <div class="alert-modern alert-<?= $tipo_mensagem ?>">
                    <span class="alert-icon">
                        <?= $tipo_mensagem == 'success' ? '✅' : ($tipo_mensagem == 'danger' ? '❌' : '⚠️') ?>
                    </span>
                    <div class="alert-content">
                        <strong><?= $tipo_mensagem == 'success' ? 'Sucesso!' : ($tipo_mensagem == 'danger' ? 'Erro!' : 'Atenção!') ?></strong>
                        <?= $mensagem ?>
                    </div>
                    <button type="button" class="btn-close-modern" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <!-- Formulário -->
            <form method="POST" id="formEditUser">
                <!-- Nome -->
                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <span class="label-icon">👤</span>
                        Nome Completo
                        <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="nome" 
                        class="form-control-modern" 
                        value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" 
                        placeholder="Digite o nome completo"
                        required
                    >
                    <span class="form-text-modern">
                        <i class="fas fa-info-circle"></i>
                        Nome completo do usuário
                    </span>
                </div>

                <!-- Email -->
                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <span class="label-icon">📧</span>
                        Email
                        <span class="required">*</span>
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-control-modern" 
                        value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" 
                        placeholder="exemplo@email.com"
                        required
                    >
                    <span class="form-text-modern">
                        <i class="fas fa-info-circle"></i>
                        Email de acesso ao sistema
                    </span>
                </div>

                <div class="grid-2">
                    <!-- Perfil Principal -->
                    <div class="form-group-modern">
                        <label class="form-label-modern">
                            <span class="label-icon">🎯</span>
                            Perfil Principal
                            <span class="required">*</span>
                        </label>
                        <select name="perfil" class="form-control-modern" required>
                            <option value="">Selecione um perfil...</option>
                            
                            <!-- ===== GESTÃO ESCOLAR ===== -->
                            <optgroup label="🏫 Gestão Escolar">
                                <option value="admin" <?= ($usuario['perfil'] ?? '') == 'admin' ? 'selected' : '' ?>>👑 Administrador Geral</option>
                                <option value="diretor" <?= ($usuario['perfil'] ?? '') == 'diretor' ? 'selected' : '' ?>>🎯 Diretor</option>
                                <option value="vice_diretor" <?= ($usuario['perfil'] ?? '') == 'vice_diretor' ? 'selected' : '' ?>>🎯 Vice-Diretor</option>
                                <option value="coordenador" <?= ($usuario['perfil'] ?? '') == 'coordenador' ? 'selected' : '' ?>>📋 Coordenador</option>
                                <option value="coordenador_pedagogico" <?= ($usuario['perfil'] ?? '') == 'coordenador_pedagogico' ? 'selected' : '' ?>>📋 Coordenador Pedagógico</option>
                                <option value="supervisor" <?= ($usuario['perfil'] ?? '') == 'supervisor' ? 'selected' : '' ?>>👀 Supervisor</option>
                                <option value="supervisor_escolar" <?= ($usuario['perfil'] ?? '') == 'supervisor_escolar' ? 'selected' : '' ?>>👀 Supervisor Escolar</option>
                                <option value="orientador" <?= ($usuario['perfil'] ?? '') == 'orientador' ? 'selected' : '' ?>>🧭 Orientador</option>
                                <option value="orientador_educacional" <?= ($usuario['perfil'] ?? '') == 'orientador_educacional' ? 'selected' : '' ?>>🧭 Orientador Educacional</option>
                                <option value="pedagogo" <?= ($usuario['perfil'] ?? '') == 'pedagogo' ? 'selected' : '' ?>>📚 Pedagogo</option>
                                <option value="psicopedagogo" <?= ($usuario['perfil'] ?? '') == 'psicopedagogo' ? 'selected' : '' ?>>🧠 Psicopedagogo</option>
                            </optgroup>
                            
                            <!-- ===== CORPO DOCENTE ===== -->
                            <optgroup label="👨‍🏫 Corpo Docente">
                                <option value="professor" <?= ($usuario['perfil'] ?? '') == 'professor' ? 'selected' : '' ?>>👨‍🏫 Professor</option>
                                <option value="docente" <?= ($usuario['perfil'] ?? '') == 'docente' ? 'selected' : '' ?>>👩‍🏫 Docente</option>
                                <option value="instrutor" <?= ($usuario['perfil'] ?? '') == 'instrutor' ? 'selected' : '' ?>>🎓 Instrutor</option>
                                <option value="monitor" <?= ($usuario['perfil'] ?? '') == 'monitor' ? 'selected' : '' ?>>📊 Monitor</option>
                                <option value="tutor" <?= ($usuario['perfil'] ?? '') == 'tutor' ? 'selected' : '' ?>>📖 Tutor</option>
                                <option value="auxiliar_docente" <?= ($usuario['perfil'] ?? '') == 'auxiliar_docente' ? 'selected' : '' ?>>👩‍🏫 Auxiliar Docente</option>
                            </optgroup>
                            
                            <!-- ===== ALUNOS ===== -->
                            <optgroup label="🎓 Alunos">
                                <option value="aluno" <?= ($usuario['perfil'] ?? '') == 'aluno' ? 'selected' : '' ?>>🎒 Aluno</option>
                                <option value="estudante" <?= ($usuario['perfil'] ?? '') == 'estudante' ? 'selected' : '' ?>>📖 Estudante</option>
                                <option value="bolsista" <?= ($usuario['perfil'] ?? '') == 'bolsista' ? 'selected' : '' ?>>💰 Bolsista</option>
                                <option value="estagiario" <?= ($usuario['perfil'] ?? '') == 'estagiario' ? 'selected' : '' ?>>💼 Estagiário</option>
                            </optgroup>
                            
                            <!-- ===== ADMINISTRATIVO ===== -->
                            <optgroup label="📋 Administrativo">
                                <option value="secretario" <?= ($usuario['perfil'] ?? '') == 'secretario' ? 'selected' : '' ?>>📄 Secretário</option>
                                <option value="secretaria" <?= ($usuario['perfil'] ?? '') == 'secretaria' ? 'selected' : '' ?>>📝 Secretaria</option>
                                <option value="financeiro" <?= ($usuario['perfil'] ?? '') == 'financeiro' ? 'selected' : '' ?>>💰 Financeiro</option>
                                <option value="contador" <?= ($usuario['perfil'] ?? '') == 'contador' ? 'selected' : '' ?>>🧮 Contador</option>
                                <option value="rh" <?= ($usuario['perfil'] ?? '') == 'rh' ? 'selected' : '' ?>>👥 RH</option>
                                <option value="atendimento" <?= ($usuario['perfil'] ?? '') == 'atendimento' ? 'selected' : '' ?>>📞 Atendimento</option>
                                <option value="recepcionista" <?= ($usuario['perfil'] ?? '') == 'recepcionista' ? 'selected' : '' ?>>🛎️ Recepcionista</option>
                            </optgroup>
                            
                            <!-- ===== SERVIÇOS GERAIS ===== -->
                            <optgroup label="🔧 Serviços Gerais">
                                <option value="vigilante" <?= ($usuario['perfil'] ?? '') == 'vigilante' ? 'selected' : '' ?>>🚨 Vigilante</option>
                                <option value="motorista" <?= ($usuario['perfil'] ?? '') == 'motorista' ? 'selected' : '' ?>>🚗 Motorista</option>
                                <option value="aux_limpeza" <?= ($usuario['perfil'] ?? '') == 'aux_limpeza' ? 'selected' : '' ?>>🧹 Aux. Limpeza</option>
                                <option value="jardineiro" <?= ($usuario['perfil'] ?? '') == 'jardineiro' ? 'selected' : '' ?>>🌿 Jardineiro</option>
                                <option value="manutencao" <?= ($usuario['perfil'] ?? '') == 'manutencao' ? 'selected' : '' ?>>🔧 Manutenção</option>
                                <option value="porteiro" <?= ($usuario['perfil'] ?? '') == 'porteiro' ? 'selected' : '' ?>>🚪 Porteiro</option>
                                <option value="merendeira" <?= ($usuario['perfil'] ?? '') == 'merendeira' ? 'selected' : '' ?>>🍳 Merendeira</option>
                                <option value="aux_servicos" <?= ($usuario['perfil'] ?? '') == 'aux_servicos' ? 'selected' : '' ?>>🧹 Aux. Serviços</option>
                            </optgroup>
                            
                            <!-- ===== FAMÍLIA ===== -->
                            <optgroup label="👨‍👩‍👦 Família">
                                <option value="encarregado" <?= ($usuario['perfil'] ?? '') == 'encarregado' ? 'selected' : '' ?>>👨‍👧 Encarregado</option>
                                <option value="responsavel" <?= ($usuario['perfil'] ?? '') == 'responsavel' ? 'selected' : '' ?>>👩‍👦 Responsável</option>
                                <option value="pai" <?= ($usuario['perfil'] ?? '') == 'pai' ? 'selected' : '' ?>>👨 Pai</option>
                                <option value="mae" <?= ($usuario['perfil'] ?? '') == 'mae' ? 'selected' : '' ?>>👩 Mãe</option>
                                <option value="tutor_legal" <?= ($usuario['perfil'] ?? '') == 'tutor_legal' ? 'selected' : '' ?>>⚖️ Tutor Legal</option>
                            </optgroup>
                            
                            <!-- ===== OUTROS ===== -->
                            <optgroup label="🔧 Outros">
                                <option value="usuario" <?= ($usuario['perfil'] ?? '') == 'usuario' ? 'selected' : '' ?>>👤 Usuário</option>
                                <option value="visitante" <?= ($usuario['perfil'] ?? '') == 'visitante' ? 'selected' : '' ?>>👋 Visitante</option>
                                <option value="convidado" <?= ($usuario['perfil'] ?? '') == 'convidado' ? 'selected' : '' ?>>🎫 Convidado</option>
                            </optgroup>
                        </select>
                        <span class="form-text-modern">
                            <i class="fas fa-info-circle"></i>
                            Perfil principal que define o redirecionamento e permissões básicas
                        </span>
                    </div>

                    <!-- Multi Perfil (Permissões Adicionais) -->
                    <div class="form-group-modern">
                        <label class="form-label-modern">
                            <span class="label-icon">🔐</span>
                            Multi Perfil (Permissões Adicionais)
                            <span class="selected-count" id="selectedCount">0 selecionados</span>
                        </label>
                        <select 
                            name="multi_perfil[]" 
                            class="form-control-modern" 
                            multiple
                            id="multiPerfil"
                            size="10"
                        >
                            <!-- ===== GESTÃO ESCOLAR ===== -->
                            <optgroup label="🏫 Gestão Escolar">
                                <option value="admin" <?= strpos($usuario['multi_perfil'] ?? '', 'admin') !== false ? 'selected' : '' ?>>👑 Administrador Geral</option>
                                <option value="diretor" <?= strpos($usuario['multi_perfil'] ?? '', 'diretor') !== false ? 'selected' : '' ?>>🎯 Diretor</option>
                                <option value="vice_diretor" <?= strpos($usuario['multi_perfil'] ?? '', 'vice_diretor') !== false ? 'selected' : '' ?>>🎯 Vice-Diretor</option>
                                <option value="coordenador" <?= strpos($usuario['multi_perfil'] ?? '', 'coordenador') !== false ? 'selected' : '' ?>>📋 Coordenador</option>
                                <option value="coordenador_pedagogico" <?= strpos($usuario['multi_perfil'] ?? '', 'coordenador_pedagogico') !== false ? 'selected' : '' ?>>📋 Coord. Pedagógico</option>
                                <option value="supervisor" <?= strpos($usuario['multi_perfil'] ?? '', 'supervisor') !== false ? 'selected' : '' ?>>👀 Supervisor</option>
                                <option value="supervisor_escolar" <?= strpos($usuario['multi_perfil'] ?? '', 'supervisor_escolar') !== false ? 'selected' : '' ?>>👀 Supervisor Escolar</option>
                                <option value="orientador" <?= strpos($usuario['multi_perfil'] ?? '', 'orientador') !== false ? 'selected' : '' ?>>🧭 Orientador</option>
                                <option value="orientador_educacional" <?= strpos($usuario['multi_perfil'] ?? '', 'orientador_educacional') !== false ? 'selected' : '' ?>>🧭 Orientador Educacional</option>
                                <option value="pedagogo" <?= strpos($usuario['multi_perfil'] ?? '', 'pedagogo') !== false ? 'selected' : '' ?>>📚 Pedagogo</option>
                                <option value="psicopedagogo" <?= strpos($usuario['multi_perfil'] ?? '', 'psicopedagogo') !== false ? 'selected' : '' ?>>🧠 Psicopedagogo</option>
                            </optgroup>
                            
                            <!-- ===== CORPO DOCENTE ===== -->
                            <optgroup label="👨‍🏫 Corpo Docente">
                                <option value="professor" <?= strpos($usuario['multi_perfil'] ?? '', 'professor') !== false ? 'selected' : '' ?>>👨‍🏫 Professor</option>
                                <option value="docente" <?= strpos($usuario['multi_perfil'] ?? '', 'docente') !== false ? 'selected' : '' ?>>👩‍🏫 Docente</option>
                                <option value="instrutor" <?= strpos($usuario['multi_perfil'] ?? '', 'instrutor') !== false ? 'selected' : '' ?>>🎓 Instrutor</option>
                                <option value="monitor" <?= strpos($usuario['multi_perfil'] ?? '', 'monitor') !== false ? 'selected' : '' ?>>📊 Monitor</option>
                                <option value="tutor" <?= strpos($usuario['multi_perfil'] ?? '', 'tutor') !== false ? 'selected' : '' ?>>📖 Tutor</option>
                                <option value="auxiliar_docente" <?= strpos($usuario['multi_perfil'] ?? '', 'auxiliar_docente') !== false ? 'selected' : '' ?>>👩‍🏫 Aux. Docente</option>
                            </optgroup>
                            
                            <!-- ===== ALUNOS ===== -->
                            <optgroup label="🎓 Alunos">
                                <option value="aluno" <?= strpos($usuario['multi_perfil'] ?? '', 'aluno') !== false ? 'selected' : '' ?>>🎒 Aluno</option>
                                <option value="estudante" <?= strpos($usuario['multi_perfil'] ?? '', 'estudante') !== false ? 'selected' : '' ?>>📖 Estudante</option>
                                <option value="bolsista" <?= strpos($usuario['multi_perfil'] ?? '', 'bolsista') !== false ? 'selected' : '' ?>>💰 Bolsista</option>
                                <option value="estagiario" <?= strpos($usuario['multi_perfil'] ?? '', 'estagiario') !== false ? 'selected' : '' ?>>💼 Estagiário</option>
                            </optgroup>
                            
                            <!-- ===== ADMINISTRATIVO ===== -->
                            <optgroup label="📋 Administrativo">
                                <option value="secretario" <?= strpos($usuario['multi_perfil'] ?? '', 'secretario') !== false ? 'selected' : '' ?>>📄 Secretário</option>
                                <option value="secretaria" <?= strpos($usuario['multi_perfil'] ?? '', 'secretaria') !== false ? 'selected' : '' ?>>📝 Secretaria</option>
                                <option value="financeiro" <?= strpos($usuario['multi_perfil'] ?? '', 'financeiro') !== false ? 'selected' : '' ?>>💰 Financeiro</option>
                                <option value="contador" <?= strpos($usuario['multi_perfil'] ?? '', 'contador') !== false ? 'selected' : '' ?>>🧮 Contador</option>
                                <option value="rh" <?= strpos($usuario['multi_perfil'] ?? '', 'rh') !== false ? 'selected' : '' ?>>👥 RH</option>
                                <option value="atendimento" <?= strpos($usuario['multi_perfil'] ?? '', 'atendimento') !== false ? 'selected' : '' ?>>📞 Atendimento</option>
                                <option value="recepcionista" <?= strpos($usuario['multi_perfil'] ?? '', 'recepcionista') !== false ? 'selected' : '' ?>>🛎️ Recepcionista</option>
                            </optgroup>
                            
                            <!-- ===== SERVIÇOS GERAIS ===== -->
                            <optgroup label="🔧 Serviços Gerais">
                                <option value="vigilante" <?= strpos($usuario['multi_perfil'] ?? '', 'vigilante') !== false ? 'selected' : '' ?>>🚨 Vigilante</option>
                                <option value="motorista" <?= strpos($usuario['multi_perfil'] ?? '', 'motorista') !== false ? 'selected' : '' ?>>🚗 Motorista</option>
                                <option value="aux_limpeza" <?= strpos($usuario['multi_perfil'] ?? '', 'aux_limpeza') !== false ? 'selected' : '' ?>>🧹 Aux. Limpeza</option>
                                <option value="jardineiro" <?= strpos($usuario['multi_perfil'] ?? '', 'jardineiro') !== false ? 'selected' : '' ?>>🌿 Jardineiro</option>
                                <option value="manutencao" <?= strpos($usuario['multi_perfil'] ?? '', 'manutencao') !== false ? 'selected' : '' ?>>🔧 Manutenção</option>
                                <option value="porteiro" <?= strpos($usuario['multi_perfil'] ?? '', 'porteiro') !== false ? 'selected' : '' ?>>🚪 Porteiro</option>
                                <option value="merendeira" <?= strpos($usuario['multi_perfil'] ?? '', 'merendeira') !== false ? 'selected' : '' ?>>🍳 Merendeira</option>
                                <option value="aux_servicos" <?= strpos($usuario['multi_perfil'] ?? '', 'aux_servicos') !== false ? 'selected' : '' ?>>🧹 Aux. Serviços</option>
                            </optgroup>
                            
                            <!-- ===== FAMÍLIA ===== -->
                            <optgroup label="👨‍👩‍👦 Família">
                                <option value="encarregado" <?= strpos($usuario['multi_perfil'] ?? '', 'encarregado') !== false ? 'selected' : '' ?>>👨‍👧 Encarregado</option>
                                <option value="responsavel" <?= strpos($usuario['multi_perfil'] ?? '', 'responsavel') !== false ? 'selected' : '' ?>>👩‍👦 Responsável</option>
                                <option value="pai" <?= strpos($usuario['multi_perfil'] ?? '', 'pai') !== false ? 'selected' : '' ?>>👨 Pai</option>
                                <option value="mae" <?= strpos($usuario['multi_perfil'] ?? '', 'mae') !== false ? 'selected' : '' ?>>👩 Mãe</option>
                                <option value="tutor_legal" <?= strpos($usuario['multi_perfil'] ?? '', 'tutor_legal') !== false ? 'selected' : '' ?>>⚖️ Tutor Legal</option>
                            </optgroup>
                            
                            <!-- ===== OUTROS ===== -->
                            <optgroup label="🔧 Outros">
                                <option value="usuario" <?= strpos($usuario['multi_perfil'] ?? '', 'usuario') !== false ? 'selected' : '' ?>>👤 Usuário</option>
                                <option value="visitante" <?= strpos($usuario['multi_perfil'] ?? '', 'visitante') !== false ? 'selected' : '' ?>>👋 Visitante</option>
                                <option value="convidado" <?= strpos($usuario['multi_perfil'] ?? '', 'convidado') !== false ? 'selected' : '' ?>>🎫 Convidado</option>
                            </optgroup>
                        </select>
                        <span class="form-text-modern">
                            <i class="fas fa-info-circle"></i>
                            Selecione múltiplos perfis para permissões adicionais (Ctrl+Clique ou Cmd+Clique)
                        </span>
                        
                        <!-- Tags dos perfis selecionados -->
                        <div class="perfil-tags" id="perfilTags">
                            <?php 
                            $multi_perfil_array = explode(',', $usuario['multi_perfil'] ?? '');
                            $perfil_icons = [
                                // Gestão Escolar
                                'admin' => '👑',
                                'diretor' => '🎯',
                                'vice_diretor' => '🎯',
                                'coordenador' => '📋',
                                'coordenador_pedagogico' => '📋',
                                'supervisor' => '👀',
                                'supervisor_escolar' => '👀',
                                'orientador' => '🧭',
                                'orientador_educacional' => '🧭',
                                'pedagogo' => '📚',
                                'psicopedagogo' => '🧠',
                                // Corpo Docente
                                'professor' => '👨‍🏫',
                                'docente' => '👩‍🏫',
                                'instrutor' => '🎓',
                                'monitor' => '📊',
                                'tutor' => '📖',
                                'auxiliar_docente' => '👩‍🏫',
                                // Alunos
                                'aluno' => '🎒',
                                'estudante' => '📖',
                                'bolsista' => '💰',
                                'estagiario' => '💼',
                                // Administrativo
                                'secretario' => '📄',
                                'secretaria' => '📝',
                                'financeiro' => '💰',
                                'contador' => '🧮',
                                'rh' => '👥',
                                'atendimento' => '📞',
                                'recepcionista' => '🛎️',
                                // Serviços Gerais
                                'vigilante' => '🚨',
                                'motorista' => '🚗',
                                'aux_limpeza' => '🧹',
                                'jardineiro' => '🌿',
                                'manutencao' => '🔧',
                                'porteiro' => '🚪',
                                'merendeira' => '🍳',
                                'aux_servicos' => '🧹',
                                // Família
                                'encarregado' => '👨‍👧',
                                'responsavel' => '👩‍👦',
                                'pai' => '👨',
                                'mae' => '👩',
                                'tutor_legal' => '⚖️',
                                // Outros
                                'usuario' => '👤',
                                'visitante' => '👋',
                                'convidado' => '🎫'
                            ];
                            foreach ($multi_perfil_array as $perfil_item) {
                                if (!empty($perfil_item)) {
                                    $icon = $perfil_icons[$perfil_item] ?? '📌';
                                    $label = ucwords(str_replace('_', ' ', $perfil_item));
                                    echo "<span class='perfil-tag'>{$icon} {$label}</span>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <span class="label-icon">📌</span>
                        Status
                    </label>
                    <select name="status" class="form-control-modern">
                        <option value="ativo" <?= ($usuario['status'] ?? '') == 'ativo' ? 'selected' : '' ?>>🟢 Ativo</option>
                        <option value="inativo" <?= ($usuario['status'] ?? '') == 'inativo' ? 'selected' : '' ?>>🔴 Inativo</option>
                        <option value="bloqueado" <?= ($usuario['status'] ?? '') == 'bloqueado' ? 'selected' : '' ?>>🔒 Bloqueado</option>
                        <option value="pendente" <?= ($usuario['status'] ?? '') == 'pendente' ? 'selected' : '' ?>>⏳ Pendente</option>
                    </select>
                    <span class="form-text-modern">
                        <i class="fas fa-info-circle"></i>
                        Usuários inativos ou bloqueados não podem acessar o sistema
                    </span>
                </div>

                <!-- Senha -->
                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <span class="label-icon">🔑</span>
                        Nova Senha
                    </label>
                    <input 
                        type="password" 
                        name="senha" 
                        class="form-control-modern" 
                        placeholder="Deixe em branco para manter a senha atual"
                        minlength="6"
                    >
                    <span class="form-text-modern">
                        <i class="fas fa-info-circle"></i>
                        Mínimo 6 caracteres. Deixe em branco para não alterar.
                    </span>
                </div>

                <!-- Botões -->
                <div class="d-flex flex-wrap gap-3 mt-4 pt-3" style="border-top: 2px solid var(--border-color);">
                    <a href="index.php" class="btn-modern btn-modern-secondary">
                        ← Cancelar
                    </a>
                    <button type="submit" class="btn-modern btn-modern-primary" id="btnSubmit">
                        💾 Salvar Alterações
                    </button>
                    <?php if (($_SESSION['usuario_id'] ?? 0) != $id): ?>
                        <button type="button" class="btn-modern btn-modern-danger" onclick="confirmDelete(<?= $id ?>)">
                            🗑️ Excluir
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Informações Adicionais -->
    <div class="info-card-modern">
        <div class="info-item">
            <span>🆔</span>
            <strong>ID:</strong> <?= $usuario['id'] ?? 'N/A' ?>
        </div>
        <div class="info-divider"></div>
        <div class="info-item">
            <span>📅</span>
            <strong>Criado em:</strong> 
            <?= isset($usuario['created_at']) ? date('d/m/Y H:i', strtotime($usuario['created_at'])) : 'N/A' ?>
        </div>
        <div class="info-divider"></div>
        <div class="info-item">
            <span>🔄</span>
            <strong>Última atualização:</strong>
            <?= isset($usuario['updated_at']) ? date('d/m/Y H:i', strtotime($usuario['updated_at'])) : 'N/A' ?>
        </div>
    </div>
</div>

<script>
// ===== JAVASCRIPT PARA INTERAÇÕES =====

// Confirmar exclusão
function confirmDelete(id) {
    if (confirm('⚠️ Tem certeza que deseja excluir este usuário?\nEsta ação não pode ser desfeita!')) {
        window.location.href = 'delete.php?id=' + id;
    }
}

// ===== MULTI PERFIL - CONTADOR E TAGS =====
document.addEventListener('DOMContentLoaded', function() {
    const multiSelect = document.getElementById('multiPerfil');
    const selectedCount = document.getElementById('selectedCount');
    const perfilTags = document.getElementById('perfilTags');
    
    const perfilIcons = {
        // Gestão Escolar
        'admin': '👑',
        'diretor': '🎯',
        'vice_diretor': '🎯',
        'coordenador': '📋',
        'coordenador_pedagogico': '📋',
        'supervisor': '👀',
        'supervisor_escolar': '👀',
        'orientador': '🧭',
        'orientador_educacional': '🧭',
        'pedagogo': '📚',
        'psicopedagogo': '🧠',
        // Corpo Docente
        'professor': '👨‍🏫',
        'docente': '👩‍🏫',
        'instrutor': '🎓',
        'monitor': '📊',
        'tutor': '📖',
        'auxiliar_docente': '👩‍🏫',
        // Alunos
        'aluno': '🎒',
        'estudante': '📖',
        'bolsista': '💰',
        'estagiario': '💼',
        // Administrativo
        'secretario': '📄',
        'secretaria': '📝',
        'financeiro': '💰',
        'contador': '🧮',
        'rh': '👥',
        'atendimento': '📞',
        'recepcionista': '🛎️',
        // Serviços Gerais
        'vigilante': '🚨',
        'motorista': '🚗',
        'aux_limpeza': '🧹',
        'jardineiro': '🌿',
        'manutencao': '🔧',
        'porteiro': '🚪',
        'merendeira': '🍳',
        'aux_servicos': '🧹',
        // Família
        'encarregado': '👨‍👧',
        'responsavel': '👩‍👦',
        'pai': '👨',
        'mae': '👩',
        'tutor_legal': '⚖️',
        // Outros
        'usuario': '👤',
        'visitante': '👋',
        'convidado': '🎫'
    };
    
    function updateMultiPerfil() {
        const selected = Array.from(multiSelect.selectedOptions).map(opt => opt.value);
        const count = selected.length;
        
        // Atualizar contador
        selectedCount.textContent = count + ' selecionado' + (count > 1 ? 's' : '');
        
        // Atualizar tags
        perfilTags.innerHTML = '';
        selected.forEach(val => {
            if (val) {
                const icon = perfilIcons[val] || '📌';
                const label = val.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                const tag = document.createElement('span');
                tag.className = 'perfil-tag';
                tag.innerHTML = icon + ' ' + label;
                perfilTags.appendChild(tag);
            }
        });
        
        if (count === 0) {
            const empty = document.createElement('span');
            empty.className = 'form-text-modern';
            empty.style.margin = '0';
            empty.innerHTML = '<i class="fas fa-info-circle"></i> Nenhum perfil adicional selecionado';
            perfilTags.appendChild(empty);
        }
    }
    
    // Event listener para mudanças no select
    multiSelect.addEventListener('change', updateMultiPerfil);
    
    // Inicializar
    updateMultiPerfil();
});

// Animação ao enviar formulário
document.getElementById('formEditUser')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('btnSubmit');
    btn.classList.add('loading');
    btn.innerHTML = '<span class="spinner"></span><span class="btn-text">Salvando...</span>';
    btn.disabled = true;
    
    setTimeout(() => {
        btn.classList.remove('loading');
        btn.innerHTML = '💾 Salvar Alterações';
        btn.disabled = false;
    }, 5000);
});

// Validação em tempo real
document.querySelectorAll('.form-control-modern').forEach(input => {
    input.addEventListener('blur', function() {
        if (this.hasAttribute('required') && !this.value.trim()) {
            this.classList.add('error');
            this.classList.remove('success');
        } else if (this.value.trim()) {
            this.classList.remove('error');
            this.classList.add('success');
        } else {
            this.classList.remove('error', 'success');
        }
    });
    
    input.addEventListener('input', function() {
        if (this.classList.contains('error') && this.value.trim()) {
            this.classList.remove('error');
            this.classList.add('success');
        }
    });
});

// Fechar alertas automaticamente
document.querySelectorAll('.alert-modern').forEach(alert => {
    setTimeout(() => {
        if (alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    }, 5000);
});
</script>

<?php include '../../includes/footer.php'; ?>