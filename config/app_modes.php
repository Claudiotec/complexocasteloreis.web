<?php
// ============================================
// config/app_modes.php - Configurações de Modos
// ============================================

$env = getenv('APP_ENV') ?: 'local';

$modes = [
    'local' => [
        'name' => 'Modo Local',
        'icon' => '💻',
        'color' => '#3498db',
        'badge' => 'Local',
        'database' => [
            'type' => 'pgsql',
            'host' => 'ep-aged-paper-b4jtvclh-pooler.c-6.us-east-2.aws.neon.tech',
            'port' => '5432',
            'name' => 'neondb',
            'user' => 'neondb_owner',
            'pass' => 'npg_xKFNESzC5pt2',
            'ssl' => true,
            'endpoint' => 'ep-aged-paper-b4jtvclh-pooler'
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
            'host' => 'ep-aged-paper-b4jtvclh-pooler.c-6.us-east-2.aws.neon.tech',
            'port' => '5432',
            'name' => 'neondb',
            'user' => 'neondb_owner',
            'pass' => 'npg_xKFNESzC5pt2',
            'ssl' => true,
            'endpoint' => 'ep-aged-paper-b4jtvclh-pooler'
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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['modo']) && isset($modes[$_GET['modo']])) {
    $_SESSION['app_mode'] = $_GET['modo'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$modo_atual = $_SESSION['app_mode'] ?? $env;
if (!isset($modes[$modo_atual])) {
    $modo_atual = 'local';
}

$modo_config = $modes[$modo_atual];
$db_config = $modo_config['database'];

if (!defined('APP_MODE')) define('APP_MODE', $modo_atual);
if (!defined('APP_MODE_NAME')) define('APP_MODE_NAME', $modo_config['name']);
if (!defined('APP_MODE_ICON')) define('APP_MODE_ICON', $modo_config['icon']);
if (!defined('APP_MODE_COLOR')) define('APP_MODE_COLOR', $modo_config['color']);
if (!defined('APP_MODE_BADGE')) define('APP_MODE_BADGE', $modo_config['badge']);
if (!defined('APP_URL')) define('APP_URL', $modo_config['url']);
if (!defined('DEBUG_MODE')) define('DEBUG_MODE', $modo_config['features']['debug'] ?? false);
if (!defined('CACHE_ENABLED')) define('CACHE_ENABLED', $modo_config['features']['cache'] ?? false);

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

function conectarPublico() {
    if (!extension_loaded('pdo_pgsql')) {
        throw new Exception("Extensão PDO_PGSQL não está instalada/habilitada no PHP");
    }

    // Endpoint ID do Neon (extraído do DB_HOST ou da constante DB_ENDPOINT)
    $endpoint_id = defined('DB_ENDPOINT') && DB_ENDPOINT
        ? DB_ENDPOINT
        : explode('.', DB_HOST)[0];

    // Opção endpoint para o Neon (resolve o erro de SNI)
    $options = 'endpoint=' . $endpoint_id;

    // CA bundle do XAMPP
    $ca_path = 'C:/xampp/apache/bin/curl-ca-bundle.crt';

    if (file_exists($ca_path)) {
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;sslmode=verify-full;sslrootcert=%s;options='%s'",
            DB_HOST, DB_PORT, DB_NAME, $ca_path, $options
        );
    } else {
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;sslmode=require;options='%s'",
            DB_HOST, DB_PORT, DB_NAME, $options
        );
    }

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 30
    ]);
}

function conectarLocal() {
    return conectarPublico();
}

function conectarBanco() {
    return conectarPublico();
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
?>
