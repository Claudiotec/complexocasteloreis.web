<?php
// redirect.php - Redireciona para a URL correta
require_once 'config/database.php';

// Obter a URL atual
$current_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Verificar se está usando localhost
if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    // Redirecionar para o IP
    $ip = getLocalIP();
    if ($ip && $ip !== 'localhost') {
        $port = $_SERVER['SERVER_PORT'];
        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
        $redirect_url = $protocol . '://' . $ip;
        if ($port != 80) {
            $redirect_url .= ':' . $port;
        }
        $redirect_url .= $_SERVER['REQUEST_URI'];
        
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Se não, redirecionar para o sistema
header('Location: ' . SITE_URL);
exit;
?>