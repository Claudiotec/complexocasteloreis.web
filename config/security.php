<?php
// ============================================================
// SOFTGEST WEB - SISTEMA DE PROTEÇÃO
// ============================================================

// ============================================================
// CONFIGURAÇÕES DE SEGURANÇA
// ============================================================
define('SECURITY_KEY', 'softgest_web_2026_secure_key_123456789');
define('SECURITY_SALT', 's0ftg3st_w3b_s3cur1ty_s4lt');
define('SESSION_TIMEOUT', 1800); // 30 minutos
define('MAX_LOGIN_ATTEMPTS', 5);
define('BLOCK_TIME', 900); // 15 minutos

// ============================================================
// FUNÇÃO: VERIFICAR INTEGRIDADE DOS ARQUIVOS
// ============================================================
function verificarIntegridade() {
    $arquivos_criticos = [
        'index.php',
        'login.php',
        'registrar.php',
        'logout.php',
        'config/database.php',
        'config/security.php',
        'includes/header.php',
        'includes/footer.php'
    ];
    
    $integridade = true;
    $alteracoes = [];
    
    foreach ($arquivos_criticos as $arquivo) {
        if (file_exists($arquivo)) {
            $hash = hash_file('sha256', $arquivo);
            $hash_armazenado = getHashArmazenado($arquivo);
            
            if ($hash_armazenado && $hash !== $hash_armazenado) {
                $integridade = false;
                $alteracoes[] = $arquivo;
            }
        }
    }
    
    return ['status' => $integridade, 'alteracoes' => $alteracoes];
}

// ============================================================
// FUNÇÃO: ARMAZENAR HASH DOS ARQUIVOS
// ============================================================
function armazenarHash($arquivo) {
    if (file_exists($arquivo)) {
        $hash = hash_file('sha256', $arquivo);
        $hash_file = 'data/hashes.json';
        
        if (!file_exists('data')) {
            mkdir('data', 0755, true);
        }
        
        $hashes = [];
        if (file_exists($hash_file)) {
            $hashes = json_decode(file_get_contents($hash_file), true);
        }
        
        $hashes[$arquivo] = [
            'hash' => $hash,
            'data' => date('Y-m-d H:i:s'),
            'tamanho' => filesize($arquivo),
            'modificado' => date('Y-m-d H:i:s', filemtime($arquivo))
        ];
        
        file_put_contents($hash_file, json_encode($hashes, JSON_PRETTY_PRINT));
        return true;
    }
    return false;
}

// ============================================================
// FUNÇÃO: OBTER HASH ARMAZENADO
// ============================================================
function getHashArmazenado($arquivo) {
    $hash_file = 'data/hashes.json';
    if (file_exists($hash_file)) {
        $hashes = json_decode(file_get_contents($hash_file), true);
        if (isset($hashes[$arquivo]['hash'])) {
            return $hashes[$arquivo]['hash'];
        }
    }
    return null;
}

// ============================================================
// FUNÇÃO: VERIFICAR SE O SISTEMA ESTÁ BLOQUEADO
// ============================================================
function isSystemBlocked() {
    if (isset($_SESSION['blocked_until']) && time() < $_SESSION['blocked_until']) {
        return true;
    }
    return false;
}

// ============================================================
// FUNÇÃO: REGISTRAR TENTATIVA DE ACESSO
// ============================================================
function registrarTentativa($tipo, $detalhes = '') {
    $log_file = 'data/security.log';
    if (!file_exists('data')) {
        mkdir('data', 0755, true);
    }
    
    $log = date('Y-m-d H:i:s') . " | " . $_SERVER['REMOTE_ADDR'] . " | " . $tipo . " | " . $detalhes . "\n";
    file_put_contents($log_file, $log, FILE_APPEND);
}

// ============================================================
// FUNÇÃO: VERIFICAR PERMISSÕES DE ARQUIVO
// ============================================================
function verificarPermissoesArquivo() {
    $problemas = [];
    $arquivos = [
        'config/database.php' => 0644,
        'config/security.php' => 0644,
        'index.php' => 0644,
        '.htaccess' => 0644
    ];
    
    foreach ($arquivos as $arquivo => $permissao) {
        if (file_exists($arquivo)) {
            $perm_atual = fileperms($arquivo) & 0777;
            if ($perm_atual != $permissao) {
                $problemas[] = $arquivo . " (atual: " . decoct($perm_atual) . ", esperado: " . decoct($permissao) . ")";
            }
        }
    }
    
    return $problemas;
}

// ============================================================
// FUNÇÃO: GERAR TOKEN DE SEGURANÇA
// ============================================================
function gerarToken() {
    return bin2hex(random_bytes(32));
}

// ============================================================
// FUNÇÃO: VERIFICAR TOKEN
// ============================================================
function verificarToken($token) {
    return isset($_SESSION['security_token']) && hash_equals($_SESSION['security_token'], $token);
}

// ============================================================
// FUNÇÃO: LOG DE ATIVIDADES SUSPEITAS
// ============================================================
function logAtividadeSuspeita($descricao) {
    $log_file = 'data/suspeitas.log';
    if (!file_exists('data')) {
        mkdir('data', 0755, true);
    }
    
    $log = date('Y-m-d H:i:s') . " | " . $_SERVER['REMOTE_ADDR'] . " | " . $_SERVER['REQUEST_URI'] . " | " . $descricao . "\n";
    file_put_contents($log_file, $log, FILE_APPEND);
}

// ============================================================
// FUNÇÃO: VERIFICAR ACESSO SUSPEITO
// ============================================================
function verificarAcessoSuspeito() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $log_file = 'data/acessos.log';
    
    if (!file_exists('data')) {
        mkdir('data', 0755, true);
    }
    
    // Verificar tentativas de acesso em 5 minutos
    $acessos = file_exists($log_file) ? file($log_file) : [];
    $tentativas = 0;
    $limite = time() - 300; // 5 minutos
    
    foreach ($acessos as $linha) {
        $dados = explode('|', $linha);
        if (isset($dados[0]) && isset($dados[1]) && trim($dados[1]) == $ip) {
            $tempo = strtotime(trim($dados[0]));
            if ($tempo > $limite) {
                $tentativas++;
            }
        }
    }
    
    if ($tentativas > 10) {
        logAtividadeSuspeita("Múltiplos acessos suspeitos: $tentativas tentativas em 5 minutos");
        return true;
    }
    
    return false;
}

// ============================================================
// FUNÇÃO: PROTEGER CONTRA ACESSO DIRETO
// ============================================================
function protegerAcessoDireto() {
    $arquivo = basename($_SERVER['PHP_SELF']);
    $arquivos_permitidos = ['index.php', 'login.php', 'registrar.php', 'logout.php'];
    
    if (!in_array($arquivo, $arquivos_permitidos) && !isLoggedIn()) {
        header("Location: " . SITE_URL . "login.php");
        exit;
    }
}

// ============================================================
// INICIAR SISTEMA DE PROTEÇÃO
// ============================================================
function iniciarProtecao() {
    // Verificar integridade
    $integ = verificarIntegridade();
    if (!$integ['status'] && $_SESSION['usuario_perfil'] == 'admin') {
        $_SESSION['alerta_integridade'] = $integ['alteracoes'];
    }
    
    // Verificar acessos suspeitos
    if (verificarAcessoSuspeito()) {
        logAtividadeSuspeita("Acesso suspeito detectado");
    }
    
    // Verificar se o sistema está bloqueado
    if (isSystemBlocked()) {
        $tempo_restante = $_SESSION['blocked_until'] - time();
        if ($tempo_restante > 0) {
            die("🚫 Sistema temporariamente bloqueado. Tente novamente em " . ceil($tempo_restante / 60) . " minutos.");
        }
    }
    
    // Gerar token de segurança
    if (empty($_SESSION['security_token'])) {
        $_SESSION['security_token'] = gerarToken();
    }
}

// ============================================================
// EXECUTAR PROTEÇÃO (SE FOR ADMIN)
// ============================================================
if (isset($_SESSION['usuario_perfil']) && $_SESSION['usuario_perfil'] == 'admin') {
    iniciarProtecao();
}
?>