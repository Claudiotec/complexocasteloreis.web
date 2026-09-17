<?php
// ============================================
// Configuração de Caminhos do Sistema
// ============================================

// Definir caminho base do projeto
define('BASE_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('CONFIG_PATH', BASE_PATH . 'config' . DIRECTORY_SEPARATOR);
define('MODULES_PATH', BASE_PATH . 'modules' . DIRECTORY_SEPARATOR);

// URLs
define('BASE_URL', '/softgest_web/');
define('SITE_URL', 'http://localhost/softgest_web/');
?>