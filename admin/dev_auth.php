<?php
// ============================================
// admin/dev_auth.php - Verificação de senha
// ============================================

session_start();

// Senha definida (Claudtec2011)
define('DEV_PASSWORD', 'Claudtec2011');

// Verificar se já está autenticado
if (isset($_SESSION['dev_authenticated']) && $_SESSION['dev_authenticated'] === true) {
    header('Location: dev_database.php');
    exit;
}


// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: dev_auth.php');
    exit;
}


// Processar login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if ($password === DEV_PASSWORD) {
        $_SESSION['dev_authenticated'] = true;
        $_SESSION['dev_auth_time'] = time();
        header('Location: dev_database.php');
        exit;
    } else {
        $error = '❌ Senha incorreta! Tente novamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Acesso Desenvolvedor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0c29, #1a1a2e, #16213e);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .auth-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 50px 40px;
            max-width: 450px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            text-align: center;
        }
        .lock-icon {
            font-size: 72px;
            margin-bottom: 20px;
            display: block;
        }
        h1 {
            color: #fff;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .subtitle {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
            margin-bottom: 30px;
        }
        .input-group {
            margin-bottom: 20px;
        }
        input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 18px;
            outline: none;
            transition: all 0.3s;
        }
        input:focus {
            border-color: #6c63ff;
            box-shadow: 0 0 20px rgba(108, 99, 255, 0.2);
        }
        input::placeholder {
            color: rgba(255,255,255,0.3);
        }
        .btn-auth {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #6c63ff, #3f3d9e);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(108, 99, 255, 0.3);
        }
        .btn-auth:active {
            transform: translateY(0);
        }
        .error {
            color: #ff6b6b;
            font-size: 14px;
            margin-top: 15px;
            padding: 12px;
            background: rgba(255, 107, 107, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(255, 107, 107, 0.2);
        }
        .back-link {
            color: rgba(255,255,255,0.4);
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin-top: 20px;
            transition: color 0.3s;
        }
        .back-link:hover {
            color: rgba(255,255,255,0.8);
        }
        .hint {
            color: rgba(255,255,255,0.2);
            font-size: 12px;
            margin-top: 15px;
        }
        @media (max-width: 480px) {
            .auth-container {
                padding: 30px 20px;
            }
            .lock-icon {
                font-size: 48px;
            }
            h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <span class="lock-icon">🔐</span>
        <h1>Modo Desenvolvedor</h1>
        <p class="subtitle">Digite a senha para acessar o gerenciador de banco de dados</p>
        
        <form method="POST">
            <div class="input-group">
                <input type="password" name="password" placeholder="••••••••" autofocus required>
            </div>
            <button type="submit" class="btn-auth">🔓 Acessar</button>
        </form>
        
        <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <a href="/softgest_web/index.php" class="back-link">← Voltar ao Dashboard</a>
        <div class="hint">🔑 Dica: Senha definida no sistema</div>
    </div>
</body>
</html>