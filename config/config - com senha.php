<?php
// ============================================================
// config.php - Arquivo de configuração do SoftGest
// ============================================================

// ------------------------------------------------
// 1. CONFIGURAÇÕES DO BANCO DE DADOS
// ------------------------------------------------

// Servidor do banco de dados
define('DB_HOST', 'localhost');

// Usuário do banco de dados
define('DB_USER', 'root');

// Senha do banco de dados
define('DB_PASS', 'Claudtec');  // SENHA CORRIGIDA!

// Nome do banco de dados - ALTERE PARA O NOME REAL DO SEU BANCO!
define('DB_NAME', 'softgest_db');

// Porta do MySQL
define('DB_PORT', '3306');

// ------------------------------------------------
// 2. CONFIGURAÇÕES GERAIS DO SISTEMA
// ------------------------------------------------

// Nome do sistema
define('SISTEMA_NOME', 'SoftGest');

// URL base do sistema
define('URL_BASE', 'http://10.158.77.20/softgest_web');

// Caminho físico do sistema
define('DIR_BASE', 'C:/xampp/htdocs/softgest_web');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// ------------------------------------------------
// 3. CONFIGURAÇÕES DE SESSÃO
// ------------------------------------------------

define('SESSION_NAME', 'softgest_session');
define('SESSION_TIMEOUT', 28800);

// ------------------------------------------------
// 4. CONFIGURAÇÕES DE SEGURANÇA
// ------------------------------------------------

define('ENCRYPT_KEY', 'SoftGest@2026#SecureKey');

// ------------------------------------------------
// 5. CONFIGURAÇÕES DE UPLOAD
// ------------------------------------------------

define('MAX_UPLOAD_SIZE', 10485760);
define('UPLOAD_DIR', DIR_BASE . '/uploads/');

// ------------------------------------------------
// 6. INICIALIZAÇÃO DA SESSÃO
// ------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// ------------------------------------------------
// 7. CONFIGURAÇÕES DE DEBUG
// ------------------------------------------------

ini_set('display_errors', true);
error_reporting(E_ALL);

// ------------------------------------------------
// 8. FUNÇÃO AUXILIAR PARA CONEXÃO
// ------------------------------------------------

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        die('Erro de conexão com o banco de dados: ' . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
    return $conn;
}

// ------------------------------------------------
// 9. VERIFICAÇÃO RÁPIDA
// ------------------------------------------------

if (basename($_SERVER['PHP_SELF']) == 'config.php' && isset($_GET['debug'])) {
    echo '<pre>';
    echo 'CONFIGURAÇÕES DO SISTEMA:' . PHP_EOL;
    echo '=========================' . PHP_EOL;
    echo 'DB_HOST: ' . DB_HOST . PHP_EOL;
    echo 'DB_USER: ' . DB_USER . PHP_EOL;
    echo 'DB_PASS: ' . str_repeat('*', strlen(DB_PASS)) . PHP_EOL;
    echo 'DB_NAME: ' . DB_NAME . PHP_EOL;
    echo 'URL_BASE: ' . URL_BASE . PHP_EOL;
    echo 'DIR_BASE: ' . DIR_BASE . PHP_EOL;
    echo '=========================' . PHP_EOL;
    
    $test_conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($test_conn->connect_error) {
        echo '❌ ERRO NA CONEXÃO: ' . $test_conn->connect_error . PHP_EOL;
    } else {
        echo '✅ Conexão com o banco OK!' . PHP_EOL;
        $test_conn->close();
    }
    echo '</pre>';
    exit;
}

?>