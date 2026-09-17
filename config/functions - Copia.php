<?php
// ============================================================
// config/funcoes.php - Funções Globais do Sistema
// ============================================================

// ============================================================
// FUNÇÕES DE PERMISSÃO (ÚNICO LUGAR)
// ============================================================

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

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================

/**
 * Verifica se o usuário está logado
 */
function isLoggedIn() {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

/**
 * Redireciona para uma URL
 */
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit;
}

/**
 * Exibe mensagem formatada
 */
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

/**
 * Obtém a URL atual
 */
function getCurrentURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    return $protocol . '://' . $host . $uri;
}

/**
 * Obtém o IP do visitante
 */
function getVisitorIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    return $ip;
}

/**
 * Log de acesso
 */
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