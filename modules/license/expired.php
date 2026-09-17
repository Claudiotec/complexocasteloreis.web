<?php
// license/expired.php
require_once '../config/database.php';
include '../includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Licença Expirada - SoftGest</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .expired-container {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .icon { font-size: 80px; margin-bottom: 20px; }
        h1 { color: #e74c3c; margin-bottom: 10px; }
        p { color: #64748b; margin-bottom: 20px; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 700;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(197, 165, 50, 0.3);
        }
    </style>
</head>
<body>
<div class="expired-container">
    <div class="icon">⏰</div>
    <h1>Licença Expirada</h1>
    <p>Sua licença do SoftGest expirou. Entre em contato com o suporte para renovar.</p>
    <a href="activate.php" class="btn">🔄 Renovar Licença</a>
</div>
</body>
</html>