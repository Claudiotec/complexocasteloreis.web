<?php
// config/url_config.php
// Configuração dinâmica de URLs para acesso na rede

function getBaseURL() {
    // Detectar se está acessando via IP ou localhost
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    $port = $_SERVER['SERVER_PORT'];
    
    // Se já está acessando via IP, manter IP
    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host)) {
        $base = $host;
    } else {
        // Tentar obter IP local
        $base = getLocalIP();
    }
    
    // Se tiver porta diferente de 80
    if ($port != 80) {
        return $protocol . '://' . $base . ':' . $port;
    }
    
    return $protocol . '://' . $base;
}

function getLocalIP() {
    // Tentar obter IP via comandos do sistema
    if (PHP_OS_FAMILY === 'Windows') {
        $ip = shell_exec('ipconfig | findstr "IPv4"');
        if (preg_match('/IPv4.*?(\d+\.\d+\.\d+\.\d+)/', $ip, $matches)) {
            return $matches[1];
        }
    } else {
        $ip = shell_exec('hostname -I 2>/dev/null');
        if ($ip) {
            $ips = explode(' ', trim($ip));
            return $ips[0] ?? 'localhost';
        }
    }
    
    $ip = gethostbyname(gethostname());
    if ($ip && $ip !== '127.0.0.1' && $ip !== '::1') {
        return $ip;
    }
    
    return 'localhost';
}

// Definir constantes
define('BASE_URL', getBaseURL());
define('SITE_URL', BASE_URL . '/softgest_web/');
?>