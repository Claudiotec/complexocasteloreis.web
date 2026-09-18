<?php
// ============================================
// config/paths.php - Configuração de Caminhos do Sistema
// Funciona no Windows (XAMPP) E no Linux (Render/Docker)
// ============================================

// ============================================
// DETECTAR AMBIENTE
// ============================================
$isWindows = PHP_OS_FAMILY === 'Windows';
$isRender  = getenv('RENDER') !== false || getenv('RENDER_SERVICE_ID') !== false;

// ============================================
// CAMINHOS BASE
// ============================================

if ($isWindows) {
    // Windows / XAMPP
    define('BASE_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
    define('DOCUMENTOS_PATH', 'C:/xampp/htdocs/meus_documentos' . DIRECTORY_SEPARATOR);
} else {
    // Linux / Render / Docker
    define('BASE_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
    define('DOCUMENTOS_PATH', getenv('DOCUMENTOS_PATH') ?: '/var/www/html/meus_documentos' . DIRECTORY_SEPARATOR);
}

define('CONFIG_PATH',  BASE_PATH . 'config'  . DIRECTORY_SEPARATOR);
define('MODULES_PATH', BASE_PATH . 'modules' . DIRECTORY_SEPARATOR);
define('FATURAS_PATH', DOCUMENTOS_PATH . 'faturas' . DIRECTORY_SEPARATOR);

// ============================================
// URLS (dinâmicas conforme ambiente)
// ============================================

if ($isRender) {
    // No Render, o domínio é dinâmico
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    define('BASE_URL', '/');
    define('SITE_URL', $protocol . '://' . $host . '/');
} else {
    // Local (XAMPP)
    define('BASE_URL', '/softgest_web/');
    define('SITE_URL', 'http://localhost/softgest_web/');
}

// ============================================
// GARANTIR QUE AS CONSTANTES NÃO SEJAM REDEFINIDAS
// ============================================
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', BASE_PATH);
}
?>
