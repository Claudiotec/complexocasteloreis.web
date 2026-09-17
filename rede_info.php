<?php
// ============================================
// rede_info.php - Informações de Acesso
// ============================================

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$serverIP = $_SERVER['SERVER_ADDR'] ?? '192.168.1.100';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informações de Rede</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            padding: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #1a2332;
            font-size: 28px;
            margin: 0;
        }
        .header p {
            color: #94a3b8;
            margin-top: 5px;
        }
        .info-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 12px;
            border-left: 4px solid #c9a84c;
        }
        .info-card .label {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 600;
        }
        .info-card .value {
            font-size: 18px;
            font-weight: 600;
            color: #1a2332;
            margin-top: 4px;
        }
        .url-box {
            background: #1a2332;
            color: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 10px 0;
            word-break: break-all;
            user-select: all;
            cursor: pointer;
        }
        .copy-btn {
            background: #c9a84c;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        .copy-btn:hover {
            background: #b8973a;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #c9a84c;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #b8973a;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #4a5568;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌐 Acesso à Rede</h1>
            <p>Compartilhe com outros computadores</p>
            <span class="status">🟢 Sistema disponível</span>
        </div>

        <div class="info-card">
            <div class="label">📡 URL de Acesso</div>
            <div class="url-box" onclick="copiarURL(this)">
                http://<?= $serverIP ?>/softgest_web/
            </div>
            <button class="copy-btn" onclick="copiarURL(document.querySelector('.url-box'))">
                📋 Copiar URL
            </button>
        </div>

        <div class="info-card">
            <div class="label">🖥️ IP do Servidor</div>
            <div class="value"><?= $serverIP ?></div>
        </div>

        <div class="info-card">
            <div class="label">🔌 Porta</div>
            <div class="value">80 (HTTP)</div>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;">
            <a href="http://<?= $serverIP ?>/softgest_web/" class="btn" target="_blank">
                🚀 Abrir Sistema
            </a>
            <a href="modules/escola/index.php" class="btn btn-secondary">
                ← Voltar
            </a>
        </div>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eef2f7;">
            <h4 style="margin: 0 0 10px;">📋 Instruções para outros computadores:</h4>
            <ol style="color: #4a5568; line-height: 1.8;">
                <li>Conecte-se à <strong>mesma rede Wi-Fi</strong></li>
                <li>Abra o navegador e digite a URL acima</li>
                <li>Faça login com suas credenciais</li>
            </ol>
        </div>
    </div>

    <script>
        function copiarURL(elemento) {
            const url = elemento.textContent.trim();
            navigator.clipboard.writeText(url).then(() => {
                const btn = document.querySelector('.copy-btn');
                const original = btn.textContent;
                btn.textContent = '✅ Copiado!';
                setTimeout(() => btn.textContent = original, 2000);
            });
        }
    </script>
</body>
</html>