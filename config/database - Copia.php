
<?php
// config/database.php






// ============================================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// ============================================================

// Definir constantes APENAS se não existirem
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'softgest_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');  // SENHA REMOVIDA!





// ============================================================
// CONFIGURAÇÕES DE URL DINÂMICA
// ============================================================

/**
 * Função para obter o IP local do servidor
 */
function getLocalIP() {
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

/**
 * Função para obter a URL base do sistema
 */
function getBaseURL() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $port = $_SERVER['SERVER_PORT'];
    
    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host)) {
        $base = $host;
    } else {
        $base = getLocalIP();
    }
    
    if ($port != 80 && $port != 443) {
        return $protocol . '://' . $base . ':' . $port;
    }
    
    return $protocol . '://' . $base;
}

// ============================================================
// CONFIGURAÇÕES DO SISTEMA (COM URL DINÂMICA)
// ============================================================
if (!defined('SITE_NAME')) define('SITE_NAME', 'SoftGest Web');

// URL base dinâmica - detecta IP ou localhost
$base_url = getBaseURL();
if (!defined('SITE_URL')) define('SITE_URL', $base_url . '/softgest_web/');

if (!defined('TIMEZONE')) define('TIMEZONE', 'America/Sao_Paulo');

// ============================================================
// DEFINIR TIMEZONE
// ============================================================
date_default_timezone_set(TIMEZONE);

// ============================================================
// CONEXÃO COM O BANCO DE DADOS
// ============================================================
try {
    // Verificar se estamos no modo público (PostgreSQL)
    $is_public = defined('APP_MODE') && APP_MODE === 'public';
    
    if ($is_public) {
        // Usar configurações do modo público (PostgreSQL)
        $host = defined('DB_HOST') ? DB_HOST : 'ep-holy-resonance-atm1ick2-pooler.c-9.us-east-1.aws.neon.tech';
        $port = defined('DB_PORT') ? DB_PORT : '5432';
        $dbname = defined('DB_NAME') ? DB_NAME : 'neondb';
        $user = defined('DB_USER') ? DB_USER : 'neondb_owner';
        $pass = defined('DB_PASS') ? DB_PASS : 'npg_gRkKHXNJAI40';
        $endpoint = defined('DB_ENDPOINT') ? DB_ENDPOINT : 'ep-holy-resonance-atm1ick2-pooler';
        
        $options = 'endpoint=' . $endpoint;
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;sslmode=require;options='%s'",
            $host,
            $port,
            $dbname,
            $options
        );
        
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30
        ]);
    } else {
        // Modo Local (MySQL) - SEM SENHA
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,  // Agora está vazio ''
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        $pdo->exec("SET time_zone = '+00:00'");
    }
    
} catch (PDOException $e) {
    die("❌ Erro na conexão com o banco de dados: " . $e->getMessage());
}

// ============================================================
// INICIAR SESSÃO
// ============================================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// ============================================================
// FUNÇÃO: VERIFICAR LOGIN
// ============================================================
function isLoggedIn() {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

// ============================================================
// FUNÇÃO: REDIRECIONAR
// ============================================================
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit;
}

// ============================================================
// FUNÇÃO: EXIBIR MENSAGENS
// ============================================================
function showMessage($type, $message) {
    $types = [
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️'
    ];
    $icon = isset($types[$type]) ? $types[$type] : 'ℹ️';
    return "<div class='alert alert-{$type}'>{$icon} {$message}</div>";
}

// ============================================================
// FUNÇÃO: BUSCAR EMPRESA
// ============================================================
function getEmpresa() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
        $empresa = $stmt->fetch();
        if (!$empresa) {
            $stmt = $pdo->prepare("INSERT INTO empresa (razao_social, nome_fantasia, cnpj) VALUES (?, ?, ?)");
            $stmt->execute(['SoftGest Sistemas Ltda', 'SoftGest Web', '00.000.000/0001-00']);
            $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
            $empresa = $stmt->fetch();
        }
        return $empresa;
    } catch(PDOException $e) {
        return null;
    }
}

// ============================================================
// FUNÇÃO: ATUALIZAR EMPRESA
// ============================================================
function updateEmpresa($dados) {
    global $pdo;
    try {
        $sql = "UPDATE empresa SET 
            razao_social = ?, 
            nome_fantasia = ?, 
            cnpj = ?, 
            inscricao_estadual = ?, 
            inscricao_municipal = ?, 
            endereco = ?, 
            numero = ?, 
            complemento = ?, 
            bairro = ?, 
            cidade = ?, 
            estado = ?, 
            cep = ?, 
            telefone = ?, 
            celular = ?, 
            email = ?, 
            site = ? 
            WHERE id = 1";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $dados['razao_social'],
            $dados['nome_fantasia'],
            $dados['cnpj'],
            $dados['inscricao_estadual'],
            $dados['inscricao_municipal'],
            $dados['endereco'],
            $dados['numero'],
            $dados['complemento'],
            $dados['bairro'],
            $dados['cidade'],
            $dados['estado'],
            $dados['cep'],
            $dados['telefone'],
            $dados['celular'],
            $dados['email'],
            $dados['site']
        ]);
    } catch(PDOException $e) {
        return false;
    }
}

// ============================================================
// FUNÇÃO: GERAR NÚMERO DE COMPROVANTE
// ============================================================
function gerarNumeroComprovante() {
    return 'AGT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// ============================================================
// FUNÇÃO: CALCULAR IVA
// ============================================================
function calcularIVA($valor, $taxa = 14) {
    return ($taxa / 100) * $valor;
}

// ============================================================
// FUNÇÃO: CALCULAR TOTAL COM IVA
// ============================================================
function calcularTotalComIVA($valor, $taxa = 14) {
    return $valor + calcularIVA($valor, $taxa);
}

// ============================================================
// FUNÇÃO: OBTER URL ATUAL
// ============================================================
function getCurrentURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    return $protocol . '://' . $host . $uri;
}

// ============================================================
// FUNÇÃO: VERIFICAR SE É ACESSO POR IP
// ============================================================
function isAccessByIP() {
    $host = $_SERVER['HTTP_HOST'];
    return preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host);
}

// ============================================================
// FUNÇÃO: OBTER IP DO VISITANTE
// ============================================================
function getVisitorIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    return $ip;
}

// ============================================================
// FUNÇÃO: LOG DE ACESSO
// ============================================================
function logAccess($page = '') {
    $ip = getVisitorIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = $page ?: $_SERVER['REQUEST_URI'] ?? '';
    $time = date('Y-m-d H:i:s');
    
    $log_data = [
        'ip' => $ip,
        'user_agent' => $user_agent,
        'page' => $page,
        'time' => $time,
        'host' => $_SERVER['HTTP_HOST'] ?? ''
    ];
    
    @file_put_contents(__DIR__ . '/../access_log.txt', json_encode($log_data) . "\n", FILE_APPEND);
}
?>