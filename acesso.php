<?php
// acesso.php - Redireciona automaticamente
// Salve em: C:\xampp\htdocs\softgest_web\acesso.php

// Obter IP do servidor
function getServerIP() {
    // Tentar obter IP via hostname
    $hostname = gethostname();
    $ip = gethostbyname($hostname);
    if ($ip && $ip !== '127.0.0.1' && filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    
    // Tentar via ipconfig (Windows)
    if (PHP_OS_FAMILY === 'Windows') {
        $output = shell_exec('ipconfig | findstr "IPv4"');
        if (preg_match('/IPv4.*?(\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
            return $matches[1];
        }
    }
    
    return $_SERVER['SERVER_ADDR'] ?? 'localhost';
}

$ip = getServerIP();
$port = $_SERVER['SERVER_PORT'];
$protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';

// Montar URL
if ($port == 80) {
    $url = "{$protocol}://{$ip}/softgest_web/";
} else {
    $url = "{$protocol}://{$ip}:{$port}/softgest_web/";
}

// Redirecionar
header("Location: " . $url);
exit;
?>