<?php
session_start();
require_once 'config/database.php';

$erro = '';
$perfis_disponiveis = [];
$mostrar_selecao = false;
$usuario_temp = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $perfil_escolhido = $_POST['perfil_escolhido'] ?? '';

    if (empty($login) || empty($senha)) {
        $erro = 'Preencha todos os campos!';
    } else {
        try {
            // Busca por email OU telefone
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? OR telefone = ?");
            $stmt->execute([$login, $login]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                // Verifica a senha
                if (password_verify($senha, $usuario['senha'])) {
                    // Verifica se o usuário está ativo
                    if ($usuario['status'] == 'ativo') {
                        
                        // ==========================================
                        // CARREGAR PERFIS DISPONÍVEIS
                        // ==========================================
                        $perfil_principal = strtolower($usuario['perfil'] ?? 'usuario');
                        $multi_perfil = $usuario['multi_perfil'] ?? '';
                        $multi_perfil_array = array_map('trim', explode(',', $multi_perfil));
                        
                        // Montar lista de perfis disponíveis
                        $perfis_disponiveis = [];
                        
                        // Adicionar perfil principal
                        if (!empty($perfil_principal) && $perfil_principal != 'usuario') {
                            $perfis_disponiveis[] = $perfil_principal;
                        }
                        
                        // Adicionar multi perfis
                        foreach ($multi_perfil_array as $p) {
                            if (!empty($p) && !in_array($p, $perfis_disponiveis)) {
                                $perfis_disponiveis[] = $p;
                            }
                        }
                        
                        // Se não tiver nenhum perfil específico, adicionar 'usuario'
                        if (empty($perfis_disponiveis)) {
                            $perfis_disponiveis[] = 'usuario';
                        }
                        
                        // ==========================================
                        // VERIFICAR SE O USUÁRIO ESCOLHEU UM PERFIL
                        // ==========================================
                        if (!empty($perfil_escolhido) && in_array($perfil_escolhido, $perfis_disponiveis)) {
                            $perfil_nome = $perfil_escolhido;
                            
                            // ==========================================
                            // DETERMINAR REDIRECIONAMENTO - TODOS OS PERFIS ESCOLARES
                            // ==========================================
                            $perfil_redirect = 'index.php';
                            
                            switch ($perfil_nome) {
                                // ===== GESTÃO ESCOLAR =====
                                case 'admin':
                                case 'administrador':
                                    $perfil_redirect = 'index.php';
                                    break;
                                case 'diretor':
                                    $perfil_redirect = 'diretor/dashboard.php';
                                    break;
                                case 'vice_diretor':
                                    $perfil_redirect = 'vice_diretor/dashboard.php';
                                    break;

                                case 'pedagogico':
                                case 'coordenador':
                                    $perfil_redirect = 'coordenador/dashboard.php';
                                    break;
                                case 'coordenador_pedagogico':
                                    $perfil_redirect = 'coordenador_pedagogico/dashboard.php';
                                    break;
                                case 'supervisor':
                                case 'supervisor_escolar':
                                    $perfil_redirect = 'supervisor/dashboard.php';
                                    break;
                                case 'orientador':
                                case 'orientador_educacional':
                                    $perfil_redirect = 'orientador/dashboard.php';
                                    break;
                                case 'pedagogo':
                                    $perfil_redirect = 'pedagogo/dashboard.php';
                                    break;
                                case 'psicopedagogo':
                                    $perfil_redirect = 'psicopedagogo/dashboard.php';
                                    break;
                                
                                // ===== CORPO DOCENTE =====
                                case 'professor':
                                case 'docente':
                                    $perfil_redirect = 'professor/dashboard.php';
                                    break;
                                case 'instrutor':
                                    $perfil_redirect = 'instrutor/dashboard.php';
                                    break;
                                case 'monitor':
                                    $perfil_redirect = 'monitor/dashboard.php';
                                    break;
                                case 'tutor':
                                    $perfil_redirect = 'tutor/dashboard.php';
                                    break;
                                case 'auxiliar_docente':
                                    $perfil_redirect = 'auxiliar_docente/dashboard.php';
                                    break;
                                
                                // ===== ALUNOS =====
                                case 'aluno':
                                case 'estudante':
                                    $perfil_redirect = 'aluno/dashboard.php';
                                    break;
                                case 'bolsista':
                                    $perfil_redirect = 'bolsista/dashboard.php';
                                    break;
                                case 'estagiario':
                                    $perfil_redirect = 'estagiario/dashboard.php';
                                    break;
                                
                                // ===== ADMINISTRATIVO =====
                                case 'secretario':
                                case 'secretaria':
                                    $perfil_redirect = 'secretaria/dashboard.php';
                                    break;
                                case 'financeiro':
                                    $perfil_redirect = 'financeiro/dashboard.php';
                                    break;
                                case 'contador':
                                    $perfil_redirect = 'contador/dashboard.php';
                                    break;
                                case 'rh':
                                    $perfil_redirect = 'rh/dashboard.php';
                                    break;
                                case 'atendimento':
                                    $perfil_redirect = 'atendimento/dashboard.php';
                                    break;
                                case 'recepcionista':
                                    $perfil_redirect = 'recepcionista/dashboard.php';
                                    break;
                                
                                // ===== SERVIÇOS GERAIS =====
                                case 'vigilante':
                                    $perfil_redirect = 'vigilante/dashboard.php';
                                    break;
                                case 'motorista':
                                    $perfil_redirect = 'motorista/dashboard.php';
                                    break;
                                case 'aux_limpeza':
                                    $perfil_redirect = 'aux_limpeza/dashboard.php';
                                    break;
                                case 'jardineiro':
                                    $perfil_redirect = 'jardineiro/dashboard.php';
                                    break;
                                case 'manutencao':
                                    $perfil_redirect = 'manutencao/dashboard.php';
                                    break;
                                case 'porteiro':
                                    $perfil_redirect = 'porteiro/dashboard.php';
                                    break;
                                case 'merendeira':
                                    $perfil_redirect = 'merendeira/dashboard.php';
                                    break;
                                case 'aux_servicos':
                                    $perfil_redirect = 'aux_servicos/dashboard.php';
                                    break;
                                
                                // ===== FAMÍLIA =====
                                case 'encarregado':
                                    $perfil_redirect = 'encarregado/dashboard.php';
                                    break;
                                case 'responsavel':
                                    $perfil_redirect = 'responsavel/dashboard.php';
                                    break;
                                case 'pai':
                                    $perfil_redirect = 'pai/dashboard.php';
                                    break;
                                case 'mae':
                                    $perfil_redirect = 'mae/dashboard.php';
                                    break;
                                case 'tutor_legal':
                                    $perfil_redirect = 'tutor_legal/dashboard.php';
                                    break;
                                
                                // ===== GESTÃO =====
                                case 'gerente':
                                    $perfil_redirect = 'gerente/dashboard.php';
                                    break;
                                case 'gestor':
                                    $perfil_redirect = 'gestor/dashboard.php';
                                    break;
                                
                                // ===== OUTROS =====
                                default:
                                    $perfil_redirect = 'index.php';
                                    break;
                            }
                            
                            // ==========================================
                            // SALVAR NA SESSÃO
                            // ==========================================
                            $_SESSION['usuario_id'] = $usuario['id'];
                            $_SESSION['usuario_nome'] = $usuario['nome'];
                            $_SESSION['usuario_perfil'] = $perfil_nome;
                            $_SESSION['usuario_perfil_principal'] = $perfil_principal;
                            $_SESSION['usuario_multi_perfil'] = $multi_perfil;
                            $_SESSION['usuario_email'] = $usuario['email'];
                            $_SESSION['usuario_telefone'] = $usuario['telefone'];
                            $_SESSION['usuario_perfis_disponiveis'] = $perfis_disponiveis;
                            
                            // Atualiza último acesso
                            $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                            $stmt->execute([$usuario['id']]);
                            
                            // ==========================================
                            // REDIRECIONAR
                            // ==========================================
                            header("Location: " . $perfil_redirect);
                            exit;
                            
                        } elseif (count($perfis_disponiveis) > 1) {
                            // ==========================================
                            // MÚLTIPLOS PERFIS - MOSTRAR OPÇÕES
                            // ==========================================
                            $mostrar_selecao = true;
                            $usuario_temp = $usuario;
                            $perfis_disponiveis = array_unique($perfis_disponiveis);
                            
                            // Salvar temporariamente na sessão
                            $_SESSION['temp_usuario'] = [
                                'id' => $usuario['id'],
                                'nome' => $usuario['nome'],
                                'email' => $usuario['email'],
                                'telefone' => $usuario['telefone'],
                                'perfis' => $perfis_disponiveis
                            ];
                        } else {
                            // ==========================================
                            // APENAS UM PERFIL - REDIRECIONAR DIRETO
                            // ==========================================
                            $perfil_nome = $perfis_disponiveis[0];
                            $perfil_redirect = 'index.php';
                            
                            switch ($perfil_nome) {
                                // ===== GESTÃO ESCOLAR =====
                                case 'admin':
                                case 'administrador':
                                    $perfil_redirect = 'index.php';
                                    break;
                                case 'diretor':
                                    $perfil_redirect = 'diretor/dashboard.php';
                                    break;
                                case 'vice_diretor':
                                    $perfil_redirect = 'vice_diretor/dashboard.php';
                                    break;
                                case 'coordenador':
                                    $perfil_redirect = 'coordenador/dashboard.php';
                                    break;
                                case 'coordenador_pedagogico':
                                    $perfil_redirect = 'coordenador_pedagogico/dashboard.php';
                                    break;
                                case 'supervisor':
                                case 'supervisor_escolar':
                                    $perfil_redirect = 'supervisor/dashboard.php';
                                    break;
                                case 'orientador':
                                case 'orientador_educacional':
                                    $perfil_redirect = 'orientador/dashboard.php';
                                    break;
                                case 'pedagogo':
                                    $perfil_redirect = 'pedagogo/dashboard.php';
                                    break;
                                case 'psicopedagogo':
                                    $perfil_redirect = 'psicopedagogo/dashboard.php';
                                    break;
                                
                                // ===== CORPO DOCENTE =====
                                case 'professor':
                                case 'docente':
                                    $perfil_redirect = 'professor/dashboard.php';
                                    break;
                                case 'instrutor':
                                    $perfil_redirect = 'instrutor/dashboard.php';
                                    break;
                                case 'monitor':
                                    $perfil_redirect = 'monitor/dashboard.php';
                                    break;
                                case 'tutor':
                                    $perfil_redirect = 'tutor/dashboard.php';
                                    break;
                                case 'auxiliar_docente':
                                    $perfil_redirect = 'auxiliar_docente/dashboard.php';
                                    break;
                                
                                // ===== ALUNOS =====
                                case 'aluno':
                                case 'estudante':
                                    $perfil_redirect = 'aluno/dashboard.php';
                                    break;
                                case 'bolsista':
                                    $perfil_redirect = 'bolsista/dashboard.php';
                                    break;
                                case 'estagiario':
                                    $perfil_redirect = 'estagiario/dashboard.php';
                                    break;
                                
                                // ===== ADMINISTRATIVO =====
                                case 'secretario':
                                case 'secretaria':
                                    $perfil_redirect = 'secretaria/dashboard.php';
                                    break;
                                case 'financeiro':
                                    $perfil_redirect = 'financeiro/dashboard.php';
                                    break;
                                case 'contador':
                                    $perfil_redirect = 'contador/dashboard.php';
                                    break;
                                case 'rh':
                                    $perfil_redirect = 'rh/dashboard.php';
                                    break;
                                case 'atendimento':
                                    $perfil_redirect = 'atendimento/dashboard.php';
                                    break;
                                case 'recepcionista':
                                    $perfil_redirect = 'recepcionista/dashboard.php';
                                    break;
                                
                                // ===== SERVIÇOS GERAIS =====
                                case 'vigilante':
                                    $perfil_redirect = 'vigilante/dashboard.php';
                                    break;
                                case 'motorista':
                                    $perfil_redirect = 'motorista/dashboard.php';
                                    break;
                                case 'aux_limpeza':
                                    $perfil_redirect = 'aux_limpeza/dashboard.php';
                                    break;
                                case 'jardineiro':
                                    $perfil_redirect = 'jardineiro/dashboard.php';
                                    break;
                                case 'manutencao':
                                    $perfil_redirect = 'manutencao/dashboard.php';
                                    break;
                                case 'porteiro':
                                    $perfil_redirect = 'porteiro/dashboard.php';
                                    break;
                                case 'merendeira':
                                    $perfil_redirect = 'merendeira/dashboard.php';
                                    break;
                                case 'aux_servicos':
                                    $perfil_redirect = 'aux_servicos/dashboard.php';
                                    break;
                                
                                // ===== FAMÍLIA =====
                                case 'encarregado':
                                    $perfil_redirect = 'encarregado/dashboard.php';
                                    break;
                                case 'responsavel':
                                    $perfil_redirect = 'responsavel/dashboard.php';
                                    break;
                                case 'pai':
                                    $perfil_redirect = 'pai/dashboard.php';
                                    break;
                                case 'mae':
                                    $perfil_redirect = 'mae/dashboard.php';
                                    break;
                                case 'tutor_legal':
                                    $perfil_redirect = 'tutor_legal/dashboard.php';
                                    break;
                                
                                // ===== GESTÃO =====
                                case 'gerente':
                                    $perfil_redirect = 'gerente/dashboard.php';
                                    break;
                                case 'gestor':
                                    $perfil_redirect = 'gestor/dashboard.php';
                                    break;
                                
                                // ===== OUTROS =====
                                default:
                                    $perfil_redirect = 'index.php';
                                    break;
                            }
                            
                            $_SESSION['usuario_id'] = $usuario['id'];
                            $_SESSION['usuario_nome'] = $usuario['nome'];
                            $_SESSION['usuario_perfil'] = $perfil_nome;
                            $_SESSION['usuario_perfil_principal'] = $perfil_principal;
                            $_SESSION['usuario_multi_perfil'] = $multi_perfil;
                            $_SESSION['usuario_email'] = $usuario['email'];
                            $_SESSION['usuario_telefone'] = $usuario['telefone'];
                            $_SESSION['usuario_perfis_disponiveis'] = $perfis_disponiveis;
                            
                            $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                            $stmt->execute([$usuario['id']]);
                            
                            header("Location: " . $perfil_redirect);
                            exit;
                        }
                        
                    } else {
                        $erro = '❌ Usuário ' . $usuario['status'] . '. Entre em contato com o administrador.';
                    }
                } else {
                    $erro = '❌ Senha incorreta!';
                }
            } else {
                $erro = '❌ Usuário não encontrado!';
            }
        } catch (Exception $e) {
            $erro = 'Erro no sistema: ' . $e->getMessage();
        }
    }
}

// ==========================================
// CARREGAR DADOS TEMPORÁRIOS DA SESSÃO
// ==========================================
if ($mostrar_selecao && isset($_SESSION['temp_usuario'])) {
    $temp = $_SESSION['temp_usuario'];
    $perfis_disponiveis = $temp['perfis'];
    $nome_usuario = $temp['nome'];
    $email_usuario = $temp['email'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SoftGest Web</title>
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a2332 0%, #2d3748 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #1a2332;
            font-size: 28px;
            font-weight: 700;
        }
        .logo span {
            color: #c9a84c;
        }
        .logo p {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 5px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1a2332;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
        }
        .input-group {
            position: relative;
        }
        .input-group input {
            padding-right: 45px;
        }
        .input-group .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 18px;
            background: none;
            border: none;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #c9a84c;
            color: #1a2332;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: #b8973a;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
        }
        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }
        .alert-info {
            background: #ebf8ff;
            color: #2a4365;
            border-color: #bee3f8;
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #94a3b8;
            font-size: 14px;
        }
        .register-link a {
            color: #c9a84c;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .version {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #94a3b8;
        }
        .hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }
        
        /* ===== ESTILOS PARA SELEÇÃO DE PERFIL ===== */
        .perfil-selection {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
        }
        .perfil-selection .user-info {
            text-align: center;
            margin-bottom: 15px;
        }
        .perfil-selection .user-info .name {
            font-size: 16px;
            font-weight: 600;
            color: #1a2332;
        }
        .perfil-selection .user-info .email {
            font-size: 13px;
            color: #94a3b8;
        }
        .perfil-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
            max-height: 300px;
            overflow-y: auto;
            padding: 5px;
        }
        .perfil-grid::-webkit-scrollbar {
            width: 6px;
        }
        .perfil-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .perfil-grid::-webkit-scrollbar-thumb {
            background: #c9a84c;
            border-radius: 10px;
        }
        .perfil-option {
            padding: 12px 10px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
            background: white;
        }
        .perfil-option:hover {
            border-color: #c9a84c;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.2);
        }
        .perfil-option input[type="radio"] {
            display: none;
        }
        .perfil-option.selected {
            border-color: #c9a84c;
            background: #f5edd6;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.2);
        }
        .perfil-option .icon {
            font-size: 28px;
            display: block;
            margin-bottom: 3px;
        }
        .perfil-option .label {
            font-size: 11px;
            font-weight: 600;
            color: #1a2332;
            line-height: 1.2;
        }
        .btn-selecionar {
            width: 100%;
            padding: 12px;
            background: #c9a84c;
            color: #1a2332;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-selecionar:hover {
            background: #b8973a;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
        }
        .btn-selecionar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .perfil-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin: 2px;
        }
        .perfil-badge-professor { background: #dbeafe; color: #1e40af; }
        .perfil-badge-financeiro { background: #d1fae5; color: #065f46; }
        .perfil-badge-admin { background: #fef3c7; color: #92400e; }
        .perfil-badge-aluno { background: #fce4ec; color: #c62828; }
        .perfil-badge-secretaria { background: #e8eaf6; color: #283593; }
        .perfil-badge-diretor { background: #f3e5f5; color: #6a1b9a; }
        .perfil-badge-usuario { background: #f5f5f5; color: #616161; }
        
        .connection-status {
            text-align: center;
            margin-top: 15px;
            padding: 8px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: none;
        }
        .connection-status.online {
            display: block;
            background: #d1fae5;
            color: #065f46;
        }
        .connection-status.offline {
            display: block;
            background: #fee2e2;
            color: #991b1b;
        }
        
        .perfil-count {
            display: inline-block;
            background: #c9a84c;
            color: #1a2332;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 8px;
        }
        
        @media (max-width: 480px) {
            .login-container { padding: 30px 20px; margin: 10px; }
            .perfil-grid { grid-template-columns: 1fr 1fr; max-height: 250px; }
            .perfil-option .icon { font-size: 24px; }
            .perfil-option .label { font-size: 10px; }
        }
        
        @media (max-width: 360px) {
            .perfil-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
            .perfil-option { padding: 8px 6px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>SoftGest <span>Web</span></h1>
            <p>Sistema de Gestão Escolar</p>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['registrado'])): ?>
            <div class="alert alert-success">✅ Conta criada! Faça login para continuar.</div>
        <?php endif; ?>

        <?php if (isset($_GET['saiu'])): ?>
            <div class="alert alert-success">👋 Você saiu do sistema. Até logo!</div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- FORMULÁRIO DE LOGIN COM SELEÇÃO DE PERFIL -->
        <!-- ========================================== -->
        <form method="POST">
            <!-- Campos de Login -->
            <div class="form-group">
                <label>📧📱 Email ou Telefone *</label>
                <input type="text" name="login" placeholder="admin@softgest.com ou 999999999" 
                       value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required>
                <div class="hint">Digite seu email ou telefone cadastrado</div>
            </div>

            <div class="form-group">
                <label>🔒 Senha *</label>
                <div class="input-group">
                    <input type="password" name="senha" id="senha" placeholder="••••••••" required>
                    <button type="button" class="toggle-password" onclick="toggleSenha()">🙈</button>
                </div>
                <div class="hint">Senha padrão: admin123</div>
            </div>

            <!-- ========================================== -->
            <!-- SELEÇÃO DE PERFIL (MOSTRAR QUANDO HOUVER MÚLTIPLOS) -->
            <!-- ========================================== -->
            <?php if ($mostrar_selecao && isset($perfis_disponiveis) && count($perfis_disponiveis) > 1): ?>
            <div class="perfil-selection">
                <div class="user-info">
                    <div class="name">👤 <?= htmlspecialchars($nome_usuario ?? '') ?></div>
                    <div class="email">📧 <?= htmlspecialchars($email_usuario ?? '') ?></div>
                    <div style="margin-top:5px;font-size:12px;color:#94a3b8;">
                        Selecione o perfil que deseja usar:
                        <span class="perfil-count"><?= count($perfis_disponiveis) ?> perfis disponíveis</span>
                    </div>
                </div>

                <div class="perfil-grid">
                    <?php 
                    $perfil_icons = [
                        // Gestão Escolar
                        'admin' => '👑',
                        'administrador' => '👑',
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
                        // Gestão
                        'gerente' => '📊',
                        'gestor' => '⚙️',
                        // Outros
                        'usuario' => '👤',
                        'visitante' => '👋',
                        'convidado' => '🎫'
                    ];
                    
                    $perfil_labels = [
                        // Gestão Escolar
                        'admin' => 'Admin Geral',
                        'administrador' => 'Administrador',
                        'diretor' => 'Diretor',
                        'vice_diretor' => 'Vice-Diretor',
                        'coordenador' => 'Coordenador',
                        'coordenador_pedagogico' => 'Coord. Pedagógico',
                        'supervisor' => 'Supervisor',
                        'supervisor_escolar' => 'Supervisor Escolar',
                        'orientador' => 'Orientador',
                        'orientador_educacional' => 'Orientador Educ.',
                        'pedagogo' => 'Pedagogo',
                        'psicopedagogo' => 'Psicopedagogo',
                        // Corpo Docente
                        'professor' => 'Professor',
                        'docente' => 'Docente',
                        'instrutor' => 'Instrutor',
                        'monitor' => 'Monitor',
                        'tutor' => 'Tutor',
                        'auxiliar_docente' => 'Aux. Docente',
                        // Alunos
                        'aluno' => 'Aluno',
                        'estudante' => 'Estudante',
                        'bolsista' => 'Bolsista',
                        'estagiario' => 'Estagiário',
                        // Administrativo
                        'secretario' => 'Secretário',
                        'secretaria' => 'Secretaria',
                        'financeiro' => 'Financeiro',
                        'contador' => 'Contador',
                        'rh' => 'RH',
                        'atendimento' => 'Atendimento',
                        'recepcionista' => 'Recepcionista',
                        // Serviços Gerais
                        'vigilante' => 'Vigilante',
                        'motorista' => 'Motorista',
                        'aux_limpeza' => 'Aux. Limpeza',
                        'jardineiro' => 'Jardineiro',
                        'manutencao' => 'Manutenção',
                        'porteiro' => 'Porteiro',
                        'merendeira' => 'Merendeira',
                        'aux_servicos' => 'Aux. Serviços',
                        // Família
                        'encarregado' => 'Encarregado',
                        'responsavel' => 'Responsável',
                        'pai' => 'Pai',
                        'mae' => 'Mãe',
                        'tutor_legal' => 'Tutor Legal',
                        // Gestão
                        'gerente' => 'Gerente',
                        'gestor' => 'Gestor',
                        // Outros
                        'usuario' => 'Usuário',
                        'visitante' => 'Visitante',
                        'convidado' => 'Convidado'
                    ];
                    
                    foreach ($perfis_disponiveis as $perfil): 
                        $icon = $perfil_icons[$perfil] ?? '📌';
                        $label = $perfil_labels[$perfil] ?? ucfirst(str_replace('_', ' ', $perfil));
                    ?>
                    <label class="perfil-option" onclick="selectPerfil(this)">
                        <input type="radio" name="perfil_escolhido" value="<?= htmlspecialchars($perfil) ?>">
                        <span class="icon"><?= $icon ?></span>
                        <span class="label"><?= htmlspecialchars($label) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-selecionar" id="btnSelecionar" disabled>
                    🚀 Acessar com este perfil
                </button>
            </div>
            <?php endif; ?>

            <!-- Botão de Login (quando não tem seleção de perfil) -->
            <?php if (!$mostrar_selecao): ?>
            <button type="submit" class="btn-login">🔐 Entrar</button>
            <?php endif; ?>
        </form>

        <!-- ========================================== -->
        <!-- STATUS DE CONEXÃO -->
        <!-- ========================================== -->
        <div id="connectionStatus" class="connection-status">
            <span id="statusIcon">🟢</span>
            <span id="statusText">Conectado</span>
        </div>

        <div class="register-link">
            Não tem conta? <a href="registrar.php">Registre-se</a>
        </div>

        <div class="version">v4.0 — Sistema de Gestão Escolar</div>
    </div>

    <script>
        // ==========================================
        // MOSTRAR/ESCONDER SENHA
        // ==========================================
        function toggleSenha() {
            const input = document.getElementById('senha');
            const btn = document.querySelector('.toggle-password');
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙉';
            } else {
                input.type = 'password';
                btn.textContent = '🙈';
            }
        }

        // ==========================================
        // AUTO-FOCUS
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="login"]')?.focus();
        });

        // ==========================================
        // SELEÇÃO DE PERFIL
        // ==========================================
        let selectedPerfil = null;

        function selectPerfil(element) {
            // Remover seleção anterior
            document.querySelectorAll('.perfil-option').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Selecionar atual
            element.classList.add('selected');
            const radio = element.querySelector('input[type="radio"]');
            radio.checked = true;
            selectedPerfil = radio.value;
            
            // Habilitar botão
            const btn = document.getElementById('btnSelecionar');
            if (btn) {
                btn.disabled = false;
                const label = element.querySelector('.label').textContent;
                btn.innerHTML = '🚀 Acessar como ' + label;
            }
        }

        // Selecionar o primeiro perfil automaticamente
        document.addEventListener('DOMContentLoaded', function() {
            const firstOption = document.querySelector('.perfil-option');
            if (firstOption) {
                selectPerfil(firstOption);
            }
        });

        // ==========================================
        // MONITORAR STATUS DA CONEXÃO
        // ==========================================
        const statusDiv = document.getElementById('connectionStatus');
        const statusIcon = document.getElementById('statusIcon');
        const statusText = document.getElementById('statusText');

        function updateConnectionStatus(online) {
            if (online) {
                statusDiv.className = 'connection-status online';
                statusIcon.textContent = '🟢';
                statusText.textContent = 'Conectado';
                statusDiv.style.display = 'block';
            } else {
                statusDiv.className = 'connection-status offline';
                statusIcon.textContent = '🔴';
                statusText.textContent = 'Offline - Verifique sua conexão';
                statusDiv.style.display = 'block';
            }
        }

        updateConnectionStatus(navigator.onLine);

        window.addEventListener('online', () => {
            updateConnectionStatus(true);
            console.log('🟢 Conexão restaurada!');
        });

        window.addEventListener('offline', () => {
            updateConnectionStatus(false);
            console.log('🔴 Conexão perdida!');
        });

        function verificarServidor() {
            fetch('/softgest_web/api/sync.php?acao=ping', {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => {
                if (response.ok) {
                    updateConnectionStatus(true);
                    console.log('✅ Servidor respondendo');
                } else {
                    updateConnectionStatus(false);
                    console.log('❌ Servidor sem resposta');
                }
            })
            .catch(() => {
                updateConnectionStatus(false);
                console.log('❌ Erro ao conectar ao servidor');
            });
        }

        setInterval(verificarServidor, 30000);
        setTimeout(verificarServidor, 2000);

        let tentativasReconexao = 0;
        const maxTentativas = 5;

        function tentarReconectar() {
            if (!navigator.onLine) {
                console.log(`⏳ Tentativa ${tentativasReconexao + 1} de reconexão...`);
                tentativasReconexao++;
                
                if (tentativasReconexao <= maxTentativas) {
                    setTimeout(() => {
                        verificarServidor();
                    }, 5000 * tentativasReconexao);
                } else {
                    statusText.textContent = '⚠️ Não foi possível conectar. Verifique a rede.';
                    tentativasReconexao = 0;
                }
            }
        }

        window.addEventListener('offline', () => {
            tentativasReconexao = 0;
            setTimeout(tentarReconectar, 3000);
        });

        window.addEventListener('online', () => {
            tentativasReconexao = 0;
            if (Notification.permission === 'granted') {
                new Notification('🔔 Conexão Restaurada', {
                    body: 'O sistema está novamente online!',
                    icon: '/softgest_web/assets/images/logo.png'
                });
            }
        });

        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        console.log('📡 Sistema de monitoramento de conexão ativo!');
    </script>
</body>
</html>