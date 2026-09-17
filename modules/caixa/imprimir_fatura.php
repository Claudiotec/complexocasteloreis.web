<?php
// ============================================
// imprimir_fatura.php - Imprimir Fatura AGT
// ============================================

// Verificar se a sessão já está ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/agt_functions.php';

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    die('Fatura não encontrada');
}

$html = imprimirFaturaAGT($id);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura AGT</title>
    <style>
        body { 
            font-family: 'Arial', sans-serif; 
            margin: 0; 
            padding: 20px;
            background: #f0f2f5;
        }
        .no-print {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn-print {
            padding: 12px 35px;
            background: #c9a84c;
            color: #1a2332;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(201, 168, 76, 0.3);
        }
        .btn-print:hover {
            background: #b8973a;
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(201, 168, 76, 0.4);
        }
        .btn-back {
            display: inline-block;
            margin-left: 15px;
            color: #4a5568;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s;
        }
        .btn-back:hover {
            color: #c9a84c;
        }
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <?= $html ?>
    
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            🖨️ Imprimir / Salvar PDF
        </button>
        <a href="index.php" class="btn-back">← Voltar</a>
    </div>
</body>
</html>