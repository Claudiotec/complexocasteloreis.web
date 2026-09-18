<?php
// ============================================
// config/paths.php - Configuração de Caminhos do Sistema
// Funciona no Windows (XAMPP) E no Linux (Render/Docker)
// ============================================

$isWindows = (PHP_OS_FAMILY === 'Windows');
$isRender  = (getenv('RENDER') !== false);

// ============================================
// CAMINHOS BASE
// ============================================

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
}

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . 'config' . DIRECTORY_SEPARATOR);
}

if (!defined('MODULES_PATH')) {
    define('MODULES_PATH', BASE_PATH . 'modules' . DIRECTORY_SEPARATOR);
}

if (!defined('DOCUMENTOS_PATH')) {
    if ($isWindows) {
        define('DOCUMENTOS_PATH', 'C:/xampp/htdocs/meus_documentos' . DIRECTORY_SEPARATOR);
    } else {
        define('DOCUMENTOS_PATH', getenv('DOCUMENTOS_PATH') ?: '/var/www/html/meus_documentos' . DIRECTORY_SEPARATOR);
    }
}

if (!defined('FATURAS_PATH')) {
    define('FATURAS_PATH', DOCUMENTOS_PATH . 'faturas' . DIRECTORY_SEPARATOR);
}

// ============================================
// URLS
// ============================================

if (!defined('BASE_URL')) {
    if ($isRender) {
        define('BASE_URL', '/');
    } else {
        define('BASE_URL', '/softgest_web/');
    }
}

if (!defined('SITE_URL')) {
    if ($isRender) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        define('SITE_URL', $protocol . '://' . $host . '/');
    } else {
        define('SITE_URL', 'http://localhost/softgest_web/');
    }
}
?>
