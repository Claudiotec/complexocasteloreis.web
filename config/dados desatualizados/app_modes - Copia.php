<?php
// ============================================
// config/app_modes.php - Configurações e Funções
// ============================================

// Configurações do sistema
define('SITE_URL', '/softgest_web/');
define('APP_NAME', 'SoftGest Web');
define('APP_VERSION', '1.0.0');






// config/app_modes.php
// Configuração de Modos: Público e Local


// ============================================
// DETECTAR AMBIENTE
// ============================================

$env = getenv('APP_ENV') ?: 'local';

// ============================================
// CONFIGURAÇÕES DOS MODOS
// ============================================

$modes = [
    'local' => [
        'name' => 'Modo Local',
        'icon' => '💻',
        'color' => '#3498db',
        'badge' => 'Local',
        'database' => [
            'type' => 'mysql',
            'host' => 'localhost',
            'port' => '3306',
            'name' => 'softgest_db',
            'user' => 'root',
            'pass' => 'Claudtec',  // ← SENHA CORRIGIDA!
            'ssl' => false
        ],
        'features' => [
            'debug' => true,
            'cache' => false,
            'logs' => true,
            'maintenance' => false
        ],
        'url' => 'http://localhost/softgest_web'
    ],
    'public' => [
        'name' => 'Modo Público',
        'icon' => '☁️',
        'color' => '#2ecc71',
        'badge' => 'Público',
        'database' => [
            'type' => 'pgsql',
            'host' => 'ep-holy-resonance-atm1ick2-pooler.c-9.us-east-1.aws.neon.tech',
            'port' => '5432',
            'name' => 'neondb',
            'user' => 'neondb_owner',
            'pass' => 'npg_gRkKHXNJAI40',
            'ssl' => true,
            'endpoint' => 'ep-holy-resonance-atm1ick2-pooler'
        ],
        'features' => [
            'debug' => false,
            'cache' => true,
            'logs' => true,
            'maintenance' => false
        ],
        'url' => 'https://seu-site-publico.com'
    ]
];

// ============================================
// DEFINIR MODO ATUAL
// ============================================

// Verificar se o modo foi passado via GET
if (isset($_GET['modo']) && isset($modes[$_GET['modo']])) {
    $_SESSION['app_mode'] = $_GET['modo'];
    // Redirecionar para remover o parâmetro da URL
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Usar modo da sessão ou fallback
$modo_atual = $_SESSION['app_mode'] ?? $env;

if (!isset($modes[$modo_atual])) {
    $modo_atual = 'local';
}

$modo_config = $modes[$modo_atual];
$db_config = $modo_config['database'];

// ============================================
// DEFINIR CONSTANTES
// ============================================

if (!defined('APP_MODE')) define('APP_MODE', $modo_atual);
if (!defined('APP_MODE_NAME')) define('APP_MODE_NAME', $modo_config['name']);
if (!defined('APP_MODE_ICON')) define('APP_MODE_ICON', $modo_config['icon']);
if (!defined('APP_MODE_COLOR')) define('APP_MODE_COLOR', $modo_config['color']);
if (!defined('APP_MODE_BADGE')) define('APP_MODE_BADGE', $modo_config['badge']);
if (!defined('APP_URL')) define('APP_URL', $modo_config['url']);
if (!defined('DEBUG_MODE')) define('DEBUG_MODE', $modo_config['features']['debug'] ?? false);
if (!defined('CACHE_ENABLED')) define('CACHE_ENABLED', $modo_config['features']['cache'] ?? false);

// ============================================
// CONSTANTES DE BANCO DE DADOS
// ============================================

if (!defined('DB_TYPE')) define('DB_TYPE', $db_config['type']);
if (!defined('DB_HOST')) define('DB_HOST', $db_config['host']);
if (!defined('DB_PORT')) define('DB_PORT', $db_config['port']);
if (!defined('DB_NAME')) define('DB_NAME', $db_config['name']);
if (!defined('DB_USER')) define('DB_USER', $db_config['user']);
if (!defined('DB_PASS')) define('DB_PASS', $db_config['pass']);
if (!defined('DB_SSL')) define('DB_SSL', $db_config['ssl'] ?? false);
if (!defined('DB_ENDPOINT')) define('DB_ENDPOINT', $db_config['endpoint'] ?? null);

// ============================================
// FUNÇÕES DE CONEXÃO
// ============================================

function conectarBanco() {
    if (APP_MODE === 'local') {
        return conectarLocal();
    } else {
        return conectarPublico();
    }
}

function conectarLocal() {
    try {
        // Verificar se a extensão PDO_MYSQL está disponível
        if (!extension_loaded('pdo_mysql')) {
            throw new Exception("Extensão PDO_MYSQL não está instalada/habilitada no PHP");
        }
        
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro Local (MySQL): " . $e->getMessage());
    }
}

function conectarPublico() {
    try {
        // Verificar se a extensão PDO_PGSQL está disponível
        if (!extension_loaded('pdo_pgsql')) {
            throw new Exception("Extensão PDO_PGSQL não está instalada/habilitada no PHP");
        }
        
        $options = 'endpoint=' . DB_ENDPOINT;
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;sslmode=require;options='%s'",
            DB_HOST,
            DB_PORT,
            DB_NAME,
            $options
        );
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro Público (PostgreSQL): " . $e->getMessage());
    }
}

function isModoLocal() {
    return APP_MODE === 'local';
}

function isModoPublico() {
    return APP_MODE === 'public';
}

function getModoAtual() {
    return [
        'modo' => APP_MODE,
        'nome' => APP_MODE_NAME,
        'icone' => APP_MODE_ICON,
        'cor' => APP_MODE_COLOR,
        'badge' => APP_MODE_BADGE
    ];
}

function getModosDisponiveis() {
    global $modes;
    $list = [];
    foreach ($modes as $key => $mode) {
        $list[$key] = [
            'key' => $key,
            'name' => $mode['name'],
            'icon' => $mode['icon'],
            'color' => $mode['color'],
            'badge' => $mode['badge'],
            'url' => $mode['url']
        ];
    }
    return $list;
}

// ============================================
// ============================================
// FUNÇÃO PARA CARREGAR PERMISSÕES NA SESSÃO
// ============================================
// ============================================

/**
 * Carrega as permissões do usuário na sessão
 * @param PDO $pdo Conexão com o banco
 * @param int $usuario_id ID do usuário
 */
function carregarPermissoesUsuario($pdo, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT modulo, acao FROM permissoes 
            WHERE usuario_id = ? 
            ORDER BY modulo, acao
        ");
        $stmt->execute([$usuario_id]);
        $permissoes = $stmt->fetchAll();
        $_SESSION['permissoes'] = $permissoes;
        return $permissoes;
    } catch (Exception $e) {
        error_log("Erro ao carregar permissões: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se o usuário tem permissão para uma ação em um módulo
 * @param string $modulo Nome do módulo
 * @param string $acao Ação (visualizar, criar, editar, excluir)
 * @return bool
 */
function temPermissao($modulo, $acao = 'visualizar') {
    // Se não estiver logado, não tem permissão
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }
    
    // Usuário admin tem todas as permissões
    if (isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin') {
        return true;
    }
    
    // Se não houver permissões na sessão, tentar carregar
    if (!isset($_SESSION['permissoes']) || !is_array($_SESSION['permissoes'])) {
        try {
            $pdo = conectarBanco();
            if ($pdo) {
                carregarPermissoesUsuario($pdo, $_SESSION['usuario_id']);
            }
        } catch (Exception $e) {
            error_log("Erro ao carregar permissões automaticamente: " . $e->getMessage());
        }
    }
    
    // Verifica permissões específicas
    if (isset($_SESSION['permissoes']) && is_array($_SESSION['permissoes'])) {
        foreach ($_SESSION['permissoes'] as $permissao) {
            // Verifica formato novo (modulo + acao)
            if (isset($permissao['modulo']) && isset($permissao['acao'])) {
                if (strtolower(trim($permissao['modulo'])) == strtolower(trim($modulo)) && 
                    strtolower(trim($permissao['acao'])) == strtolower(trim($acao))) {
                    return true;
                }
            }
            
            // Verifica formato antigo (modulo + colunas)
            if (isset($permissao['modulo']) && isset($permissao[$acao])) {
                if (strtolower(trim($permissao['modulo'])) == strtolower(trim($modulo)) && 
                    $permissao[$acao] == 1) {
                    return true;
                }
            }
        }
    }
    
    return false;
}

/**
 * Verifica se o usuário tem permissão para acessar um módulo
 * @param string $modulo Nome do módulo
 * @return bool
 */
function temAcessoModulo($modulo) {
    return temPermissao($modulo, 'visualizar');
}

/**
 * Verifica se o usuário é administrador
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin';
}

/**
 * Verifica se o usuário tem uma permissão específica
 * @param string $modulo Nome do módulo
 * @param string $acao Ação
 * @return bool
 */
function verificarPermissao($modulo, $acao) {
    return temPermissao($modulo, $acao);
}