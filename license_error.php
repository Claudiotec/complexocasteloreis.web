<?php
$erro = $_GET['erro'] ?? 'Licença inválida!';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro de Licença - SoftGest Web</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-container {
            max-width: 500px;
            width: 100%;
        }
        .error-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .error-card .icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        .error-card h1 {
            color: #e74c3c;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .error-card p {
            color: #64748b;
            font-size: 16px;
            line-height: 1.6;
        }
        .error-card .codigo {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 14px;
            border: 1px solid #e2e8f0;
            color: #1a2332;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #c9a84c;
            color: #1a2332;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(197,165,50,0.3);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <div class="icon">🔒</div>
            <h1>Erro de Licença</h1>
            <p><?= htmlspecialchars($erro) ?></p>
            <div class="codigo">
                <strong>SoftGest Web</strong><br>
                Sistema de Gestão Empresarial<br>
                <span style="color:#94a3b8;font-size:12px;">Entre em contato com o suporte para renovar sua licença.</span>
            </div>
            <div>
                <a href="login.php" class="btn">🔐 Tentar Novamente</a>
                <a href="mailto:suporte@softgest.com" class="btn btn-secondary">📧 Suporte</a>
            </div>
        </div>
    </div>
</body>
</html>