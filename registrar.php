<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    header("Location: " . SITE_URL);
    exit;
}

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    
    // Limpar telefone (remover caracteres especiais)
    $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone);
    
    if (empty($nome) || empty($email) || empty($telefone) || empty($senha)) {
        $mensagem = '❌ Todos os campos são obrigatórios!';
        $tipoMensagem = 'error';
    } elseif ($senha !== $confirmar_senha) {
        $mensagem = '❌ As senhas não coincidem!';
        $tipoMensagem = 'error';
    } elseif (strlen($senha) < 6) {
        $mensagem = '❌ A senha deve ter pelo menos 6 caracteres!';
        $tipoMensagem = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = '❌ Email inválido!';
        $tipoMensagem = 'error';
    } else {
        // VALIDAÇÃO DE TELEFONE - FORMATO ANGOLANO
        // Angola: 9 dígitos começando com 9 (ex: 946646242)
        $telefone_valido = false;
        $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone);
        
        // Verificar se tem 9 dígitos e começa com 9 (formato Angola)
        if (strlen($telefone_limpo) == 9 && substr($telefone_limpo, 0, 1) == '9') {
            $telefone_valido = true;
        }
        // Verificar se tem 12 dígitos (com +244) e começa com 2449
        elseif (strlen($telefone_limpo) == 12 && substr($telefone_limpo, 0, 3) == '244') {
            $telefone_valido = true;
        }
        // Verificar se tem 10 dígitos (com 0 no início) - ex: 0946646242
        elseif (strlen($telefone_limpo) == 10 && substr($telefone_limpo, 0, 1) == '0') {
            $telefone_valido = true;
        }
        
        if (!$telefone_valido) {
            $mensagem = '❌ Telefone inválido! Use formato Angola: 9 dígitos (ex: 946646242)';
            $tipoMensagem = 'error';
        } else {
            try {
                // Verificar se email já existe
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $mensagem = '❌ Este email já está cadastrado!';
                    $tipoMensagem = 'error';
                } 
                // Verificar se telefone já existe
                else {
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = ?");
                    $stmt->execute([$telefone_limpo]);
                    if ($stmt->fetch()) {
                        $mensagem = '❌ Este telefone já está cadastrado!';
                        $tipoMensagem = 'error';
                    } else {
                        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, senha, perfil, status) VALUES (?, ?, ?, ?, 'usuario', 'pendente')");
                        $stmt->execute([$nome, $email, $telefone_limpo, $senha_hash]);
                        
                        $mensagem = '✅ Cadastro realizado com sucesso! Aguarde a aprovação do administrador.';
                        $tipoMensagem = 'success';
                    }
                }
            } catch(PDOException $e) {
                $mensagem = '❌ Erro ao cadastrar: ' . $e->getMessage();
                $tipoMensagem = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar - SoftGest Web</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f1724 0%, #1a2332 50%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            width: 100%;
            max-width: 460px;
        }

        .register-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
            padding: 40px 35px 35px;
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #c9a84c, #f5d76e, #c9a84c);
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 5px;
        }

        .register-logo .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            color: #1a2332;
        }

        .register-logo h1 {
            font-size: 26px;
            font-weight: 800;
            color: #1a2332;
        }

        .register-logo h1 span {
            color: #c9a84c;
        }

        .register-subtitle {
            color: #94a3b8;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 5px;
        }

        .form-group label .required {
            color: #ef4444;
            margin-left: 3px;
        }

        .form-group .input-wrapper {
            position: relative;
        }

        .form-group .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 17px;
            color: #94a3b8;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            color: #1e293b;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: #c9a84c;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(197, 165, 50, 0.1);
            outline: none;
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        .form-group .hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 5px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(197, 165, 50, 0.35);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid transparent;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .register-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #f1f5f9;
        }

        .register-footer p {
            font-size: 14px;
            color: #64748b;
        }

        .register-footer a {
            color: #c9a84c;
            text-decoration: none;
            font-weight: 600;
        }

        .register-footer a:hover {
            color: #a88a3a;
            text-decoration: underline;
        }

        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .info-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #64748b;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .info-box strong {
            color: #1a2332;
        }

        @media (max-width: 480px) {
            .register-card {
                padding: 25px 20px 20px;
            }
            .row-2 {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .register-logo h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="register-logo">
                    <div class="logo-icon">SG</div>
                    <h1>SoftGest <span>Web</span></h1>
                </div>
                <p class="register-subtitle">Crie sua conta gratuitamente</p>
            </div>

            <div class="info-box">
                📱 Telefone formato Angola: <strong>9 dígitos</strong> (ex: 946646242)
            </div>

            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Nome Completo <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" name="nome" placeholder="Seu nome completo" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row-2">
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">📧</span>
                            <input type="email" name="email" placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Telefone <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">📱</span>
                            <input type="tel" name="telefone" id="telefone" placeholder="946646242" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" required>
                        </div>
                        <div class="hint">Digite apenas os 9 dígitos (ex: 946646242)</div>
                    </div>
                </div>

                <div class="row-2">
                    <div class="form-group">
                        <label>Senha <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input type="password" name="senha" id="senha" placeholder="Mínimo 6 caracteres" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirmar Senha <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔐</span>
                            <input type="password" name="confirmar_senha" id="confirmar_senha" placeholder="Digite novamente" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-register">📝 Registrar</button>
            </form>

            <div class="register-footer">
                <p>Já tem conta? <a href="login.php">Faça login</a></p>
                <p style="font-size: 12px; color: #cbd5e1; margin-top: 5px;">v3.0 — Sistema licenciado</p>
            </div>
        </div>
    </div>

    <script>
        // Máscara de telefone Angola (9 dígitos)
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            // Limitar a 9 dígitos
            if (value.length > 9) {
                value = value.slice(0, 9);
            }
            this.value = value;
        });

        // Validação em tempo real
        document.getElementById('telefone').addEventListener('blur', function(e) {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 0 && value.length !== 9) {
                this.style.borderColor = '#ef4444';
                this.style.backgroundColor = '#fef2f2';
            } else if (value.length === 9 && value[0] === '9') {
                this.style.borderColor = '#22c55e';
                this.style.backgroundColor = '#f0fdf4';
            } else {
                this.style.borderColor = '#e2e8f0';
                this.style.backgroundColor = '#f8fafc';
            }
        });
    </script>
</body>
</html>