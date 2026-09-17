<?php
session_start();
require_once 'config/database.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';

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
                        $_SESSION['usuario_id'] = $usuario['id'];
                        $_SESSION['usuario_nome'] = $usuario['nome'];
                        $_SESSION['usuario_perfil'] = $usuario['perfil'];
                        $_SESSION['usuario_email'] = $usuario['email'];
                        $_SESSION['usuario_telefone'] = $usuario['telefone'];
                        
                        // Atualiza último acesso
                        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                        $stmt->execute([$usuario['id']]);
                        
                        header("Location: index.php");
                        exit;
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
        }
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            width: 100%;
            max-width: 420px;
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
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-group input:focus {
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
        @media (max-width: 480px) {
            .login-container { padding: 30px 20px; margin: 20px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>SoftGest <span>Web</span></h1>
            <p>Sistema de Gestão Empresarial</p>
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

        <form method="POST">
            <div class="form-group">
                <label>📧📱 Email ou Telefone (9 dígitos) *</label>
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

            <button type="submit" class="btn-login">🔐 Entrar</button>
        </form>

        <div class="register-link">
            Não tem conta? <a href="registrar.php">Registre-se</a>
        </div>

        <div class="version">v3.0 — Sistema licenciado</div>
    </div>

    <script>
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

        // Auto-focus no campo de login
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="login"]').focus();
        });
    </script>
</body>
</html>