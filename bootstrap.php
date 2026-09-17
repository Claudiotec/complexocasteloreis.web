<?php
// Crie: softgest_web/bootstrap.php

defined('BASE_PATH') or define('BASE_PATH', realpath(__DIR__));
defined('INCLUDE_PATH') or define('INCLUDE_PATH', BASE_PATH . '/includes');
defined('CONFIG_PATH') or define('CONFIG_PATH', BASE_PATH . '/config');
defined('MODULES_PATH') or define('MODULES_PATH', BASE_PATH . '/modules');

// Configurações globais
if (file_exists(CONFIG_PATH . '/config.php')) {
    require_once CONFIG_PATH . '/config.php';
} else {
    die('Arquivo de configuração não encontrado em: ' . CONFIG_PATH . '/config.php');
}
?>