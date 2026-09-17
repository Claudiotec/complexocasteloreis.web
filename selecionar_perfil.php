<?php
session_start();

// Verificar se tem dados temporários
if (!isset($_SESSION['temp_usuario_id']) || !isset($_SESSION['temp_perfis'])) {
    header("Location: login.php");
    exit;
}

$nome = $_SESSION['temp_usuario_nome'];
$email = $_SESSION['temp_email'];
$perfis = $_SESSION['temp_perfis'];

// Processar seleção
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['perfil_escolhido'])) {
    $perfil_escolhido = $_POST['perfil_escolhido'];
    
    // Salvar na sessão
    $_SESSION['usuario_id'] = $_SESSION['temp_usuario_id'];
    $_SESSION['usuario_nome'] = $nome;
    $_SESSION['usuario_perfil'] = $perfil_escolhido;
    $_SESSION['usuario_email'] = $email;
    $_SESSION['usuario_telefone'] = $_SESSION['temp_telefone'] ?? '';
    $_SESSION['usuario_perfis_disponiveis'] = $perfis;
    
    // Limpar temporários
    unset($_SESSION['temp_usuario_id']);
    unset($_SESSION['temp_usuario_nome']);
    unset($_SESSION['temp_perfis']);
    unset($_SESSION['temp_email']);
    unset($_SESSION['temp_telefone']);
    
    // Redirecionar baseado no perfil escolhido
    $redirect = 'index.php';
    switch ($perfil_escolhido) {
        case 'professor':
        case 'docente':
            $redirect = 'professor/dashboard.php';
            break;
        case 'financeiro':
            $redirect = 'financeiro/dashboard.php';
            break;
        case 'admin':
        case 'administrador':
            $redirect = 'admin/dashboard.php';
            break;
        case 'aluno':
        case 'estudante':
            $redirect = 'aluno/dashboard.php';
            break;
        case 'secretaria':
        case 'secretario':
            $redirect = 'secretaria/dashboard.php';
            break;
        case 'diretor':
            $redirect = 'diretor/dashboard.php';
            break;
        case 'coordenador':
            $redirect = 'coordenador/dashboard.php';
            break;
        case 'gerente':
            $redirect = 'gerente/dashboard.php';
            break;
        case 'contador':
            $redirect = 'contador/dashboard.php';
            break;
        default:
            $redirect = 'index.php';
            break;
    }
    
    header("Location: " . $redirect);
    exit;
}

// Mapear ícones para perfis
$perfil_icons = [
    'professor' => '👨‍🏫',
    'docente' => '👩‍🏫',
    'financeiro' => '💰',
    'admin' => '👑',
    'administrador' => '👑',
    'aluno' => '🎒',
    'estudante' => '📖',
    'secretaria' => '📝',
    'secretario' => '📄',
    'diretor' => '🎯',
    'coordenador' => '📋',
    'gerente' => '📊',
    'contador' => '🧮',
    'usuario' => '👤'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Perfil - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a2332 0%, #2d3748 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            width: 100%;
            max-width: 500px;
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
        }
        .user-info {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }
        .user-info .name {
            font-size: 18px;
            font-weight: 600;
            color: #1a2332;
        }
        .user-info .email {
            font-size: 14px;
            color: #94a3b8;
        }
        .perfil-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 20px 0;
        }
        .perfil-option {
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
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
        }
        .perfil-option .icon {
            font-size: 32px;
            display: block;
            margin-bottom: 5px;
        }
        .perfil-option .label {
            font-size: 13px;
            font-weight: 600;
            color: #1a2332;
        }
        .btn-continuar {
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
            margin-top: 15px;
        }
        .btn-continuar:hover {
            background: #b8973a;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
        }
        .btn-continuar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-sair {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-sair:hover {
            color: #e53e3e;
        }
        @media (max-width: 480px) {
            .container { padding: 30px 20px; margin: 20px; }
            .perfil-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .perfil-option { padding: 12px; }
            .perfil-option .icon { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>SoftGest <span>Web</span></h1>
            <p>Selecione seu perfil de acesso</p>
        </div>

        <div class="user-info">
            <div class="name">👤 <?= htmlspecialchars($nome) ?></div>
            <div class="email"><?= htmlspecialchars($email) ?></div>
        </div>

        <form method="POST">
            <p style="color: #94a3b8; font-size: 14px; margin-bottom: 10px;">
                Você possui múltiplos perfis. Selecione qual deseja usar:
            </p>

            <div class="perfil-grid">
                <?php foreach ($perfis as $perfil): ?>
                    <?php 
                    $icon = $perfil_icons[$perfil] ?? '📌';
                    $label = ucfirst($perfil);
                    ?>
                    <label class="perfil-option" onclick="selectPerfil(this)">
                        <input type="radio" name="perfil_escolhido" value="<?= htmlspecialchars($perfil) ?>">
                        <span class="icon"><?= $icon ?></span>
                        <span class="label"><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-continuar" id="btnContinuar" disabled>
                🚀 Continuar
            </button>
        </form>

        <a href="logout.php" class="btn-sair">🚪 Sair e voltar ao login</a>
    </div>

    <script>
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
            document.getElementById('btnContinuar').disabled = false;
        }

        // Selecionar o primeiro por padrão (opcional)
        document.addEventListener('DOMContentLoaded', function() {
            const firstOption = document.querySelector('.perfil-option');
            if (firstOption) {
                selectPerfil(firstOption);
            }
        });
    </script>
</body>
</html>