<?php
// config/database.php
// Funções auxiliares. A conexão $pdo é criada pelo app_modes.php

require_once __DIR__ . '/app_modes.php';

if (!isset($pdo)) {
    $pdo = conectarBanco();
}

// ============================================================
// CONFIGURAÇÕES DE URL DINÂMICA
// ============================================================

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

function getBaseURL() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $port = $_SERVER['SERVER_PORT'] ?? 80;

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

if (!defined('SITE_NAME')) define('SITE_NAME', 'SoftGest Web');
$base_url = getBaseURL();
if (!defined('SITE_URL')) define('SITE_URL', $base_url . '/softgest_web/');
if (!defined('TIMEZONE')) define('TIMEZONE', 'America/Sao_Paulo');

date_default_timezone_set(TIMEZONE);

// ============================================================
// INICIAR SESSÃO
// ============================================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================

if (!function_exists('temPermissao')) {
    function temPermissao($modulo, $acao = 'visualizar') {
        global $pdo;
        if (!isset($_SESSION['usuario_id'])) return false;
        if (isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin') return true;

        try {
            $stmt = $pdo->prepare("SELECT $acao FROM permissoes WHERE usuario_id = ? AND modulo = ?");
            $stmt->execute([$_SESSION['usuario_id'], $modulo]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result[$acao])) {
                return (int)$result[$acao] === 1;
            }

            $stmt = $pdo->prepare("SELECT $acao FROM permissoes WHERE usuario_id = ? AND modulo = 'Escola'");
            $stmt->execute([$_SESSION['usuario_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result[$acao])) {
                return (int)$result[$acao] === 1;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Erro em temPermissao: " . $e->getMessage());
            return false;
        }
    }
}

function isLoggedIn() {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit;
}

function showMessage($type, $message) {
    $types = ['success' => '✅', 'error' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️'];
    $icon = $types[$type] ?? 'ℹ️';
    return "<div class='alert alert-{$type}'>{$icon} {$message}</div>";
}

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

function updateEmpresa($dados) {
    global $pdo;
    try {
        $sql = "UPDATE empresa SET
            razao_social = ?, nome_fantasia = ?, cnpj = ?, inscricao_estadual = ?,
            inscricao_municipal = ?, endereco = ?, numero = ?, complemento = ?,
            bairro = ?, cidade = ?, estado = ?, cep = ?, telefone = ?, celular = ?,
            email = ?, site = ? WHERE id = 1";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $dados['razao_social'], $dados['nome_fantasia'], $dados['cnpj'],
            $dados['inscricao_estadual'], $dados['inscricao_municipal'],
            $dados['endereco'], $dados['numero'], $dados['complemento'],
            $dados['bairro'], $dados['cidade'], $dados['estado'], $dados['cep'],
            $dados['telefone'], $dados['celular'], $dados['email'], $dados['site']
        ]);
    } catch(PDOException $e) {
        return false;
    }
}

function gerarNumeroComprovante() {
    return 'AGT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function calcularIVA($valor, $taxa = 14) {
    return ($taxa / 100) * $valor;
}

function calcularTotalComIVA($valor, $taxa = 14) {
    return $valor + calcularIVA($valor, $taxa);
}

function getCurrentURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return $protocol . '://' . $host . $uri;
}

function isAccessByIP() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host);
}

function getVisitorIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $ip;
}

function logAccess($page = '') {
    $ip = getVisitorIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = $page ?: ($_SERVER['REQUEST_URI'] ?? '');
    $time = date('Y-m-d H:i:s');
    $log_data = [
        'ip' => $ip, 'user_agent' => $user_agent, 'page' => $page,
        'time' => $time, 'host' => $_SERVER['HTTP_HOST'] ?? ''
    ];
    @file_put_contents(__DIR__ . '/../access_log.txt', json_encode($log_data) . "\n", FILE_APPEND);
}
?>
