<?php
// ============================================
// layout.php - Layout do Módulo Escola
// ============================================

// Verificar se está sendo chamado diretamente
if (!defined('ESCOLA_MODULE')) {
    die('Acesso direto não permitido.');
}

// Incluir o sidebar
require_once __DIR__ . '/includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escola - SoftGest</title>
    <style>
        /* ============================================
           LAYOUT PRINCIPAL DO MÓDULO ESCOLA
           ============================================ */
        .escola-layout {
            display: flex;
            min-height: 100vh;
            background: #f0f2f5;
        }
        
        .escola-content {
            flex: 1;
            padding: 20px 25px;
            margin-left: 0;
            overflow-x: hidden;
        }
        
        /* Quando o módulo está embutido no sistema principal */
        .escola-embedded .escola-layout {
            min-height: auto;
        }
        
        .escola-embedded .sidebar-escola {
            width: 100% !important;
            min-height: auto !important;
            background: transparent !important;
            padding: 0 !important;
            position: relative !important;
        }
        
        .escola-embedded .sidebar-escola .sidebar-header-escola {
            display: none !important;
        }
        
        .escola-embedded .sidebar-escola .sidebar-link-escola {
            padding: 8px 12px !important;
            font-size: 13px !important;
            color: #1a2332 !important;
        }
        
        .escola-embedded .sidebar-escola .sidebar-link-escola:hover {
            background: #f5d76e20 !important;
        }
        
        .escola-embedded .sidebar-escola .sidebar-title-escola {
            color: #94a3b8 !important;
        }
        
        .escola-embedded .escola-content {
            padding: 15px 0 !important;
            background: transparent !important;
        }
        
        /* ============================================
           RESPONSIVIDADE
           ============================================ */
        @media (max-width: 768px) {
            .escola-layout {
                flex-direction: column;
            }
            
            .escola-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<div class="escola-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="escola-content">
        <!-- CONTEÚDO DO MÓDULO -->
        <?php if (isset($content)) echo $content; ?>
    </div>
</div>
</body>
</html>