<?php
// ============================================
// includes/header_optimized.php
// Header otimizado para todos os arquivos
// ============================================

// Carregar bootstrap otimizado
require_once __DIR__ . '/../config/bootstrap_optimized.php';

// Detectar rede lenta
$isSlowNetwork = isSlowNetwork();

// Definir meta tags para cache
header('Cache-Control: private, max-age=3600, must-revalidate');
header('Pragma: private');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Definir charset
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- ===== SEO e Performance ===== -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#c9a84c">
    
    <!-- ===== Cache de Navegador ===== -->
    <meta http-equiv="Cache-Control" content="private, max-age=3600">
    
    <!-- ===== Título Dinâmico ===== -->
    <title><?= $pageTitle ?? 'SoftGest - Gestão Escolar' ?></title>
    
    <!-- ===== Favicon ===== -->
    <link rel="icon" href="/softgest_web/assets/img/favicon.ico">
    
    <!-- ===== CSS Otimizado ===== -->
    <?php if ($isSlowNetwork): ?>
        <!-- Versão para rede lenta (menos recursos) -->
        <link rel="stylesheet" href="/softgest_web/assets/css/performance.min.css">
        <style>
            /* Desativar animações em rede lenta */
            .animate, .animated, [data-animate] {
                animation: none !important;
                transition: none !important;
            }
        </style>
    <?php else: ?>
        <!-- Versão completa -->
        <link rel="stylesheet" href="/softgest_web/assets/css/style.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php endif; ?>
    
    <!-- ===== JavaScript Otimizado ===== -->
    <script>
        // ===== DETECTAR REDE LENTA =====
        (function() {
            const startTime = performance.now();
            
            // Detectar velocidade
            fetch('/softgest_web/api/ping.php', {
                method: 'HEAD',
                cache: 'no-store'
            }).then(() => {
                const latency = performance.now() - startTime;
                const isSlow = latency > 500;
                
                if (isSlow) {
                    document.cookie = 'slow_network=true; path=/; max-age=3600';
                    document.documentElement.classList.add('slow-network');
                } else {
                    document.cookie = 'slow_network=false; path=/; max-age=3600';
                }
            }).catch(() => {
                document.cookie = 'slow_network=true; path=/; max-age=3600';
                document.documentElement.classList.add('slow-network');
            });
        })();
    </script>
    
    <!-- ===== Service Worker (Cache offline) ===== -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/softgest_web/assets/js/service-worker.js')
                .then(reg => console.log('✅ Service Worker registrado'))
                .catch(err => console.log('❌ Service Worker falhou:', err));
        }
    </script>
</head>
<body>
    <!-- ===== ALERTA DE REDE LENTA ===== -->
    <div id="slowNetworkAlert" style="display: none; position: fixed; top: 0; left: 0; right: 0; background: #f39c12; color: #fff; padding: 8px 15px; text-align: center; z-index: 99999; font-size: 13px; font-weight: 600;">
        ⚡ Rede lenta detectada. Modo de economia ativado.
    </div>
    
    <!-- ===== LOADING GLOBAL ===== -->
    <div id="globalLoading" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.9); z-index: 99998; justify-content: center; align-items: center;">
        <div style="width: 50px; height: 50px; border: 5px solid #e2e8f0; border-top: 5px solid #c9a84c; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
    </div>
    
    <style>
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .slow-network * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    </style>