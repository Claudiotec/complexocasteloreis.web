<?php
// ============================================================
// SOFTGEST WEB - SISTEMA DE LICENCIAMENTO SIMPLES
// ============================================================

define('LICENSE_FILE', __DIR__ . '/../license/license.dat');

// ============================================================
// FUNÇÃO: VERIFICAR LICENÇA (SEM ASSINATURA)
// ============================================================
function verificarLicenca() {
    // Se não existir arquivo, criar um automaticamente
    if (!file_exists(LICENSE_FILE)) {
        $dados = [
            'licenca_id' => 'SG-LOCAL-' . date('Y'),
            'cliente' => 'Desenvolvimento Local',
            'data_emissao' => date('Y-m-d'),
            'data_expiracao' => date('Y-m-d', strtotime('+5 years')),
            'anos' => 5,
            'dominios' => ['localhost', '127.0.0.1'],
            'status' => 'ativo',
            'versao' => '3.0'
        ];
        
        if (!is_dir(dirname(LICENSE_FILE))) {
            mkdir(dirname(LICENSE_FILE), 0755, true);
        }
        
        file_put_contents(LICENSE_FILE, json_encode($dados, JSON_PRETTY_PRINT));
        return ['status' => true, 'mensagem' => 'Licença criada!', 'dados' => $dados];
    }
    
    // Ler licença
    $conteudo = file_get_contents(LICENSE_FILE);
    $dados = json_decode($conteudo, true);
    
    if (!$dados) {
        return ['status' => false, 'mensagem' => 'Licença inválida!'];
    }
    
    // ===== VERIFICAÇÃO SIMPLES (SEM ASSINATURA) =====
    
    // 1. Verificar data de expiração
    $data_atual = time();
    $data_expiracao = strtotime($dados['data_expiracao']);
    
    if ($data_atual > $data_expiracao) {
        return ['status' => false, 'mensagem' => 'Licença expirada em ' . date('d/m/Y', $data_expiracao)];
    }
    
    // 2. Verificar status
    if (isset($dados['status']) && $dados['status'] !== 'ativo') {
        return ['status' => false, 'mensagem' => 'Licença ' . $dados['status']];
    }
    
    // 3. Verificar domínio (apenas se não for localhost)
    $dominio_atual = $_SERVER['HTTP_HOST'];
    if ($dominio_atual != 'localhost' && $dominio_atual != '127.0.0.1') {
        if (isset($dados['dominios']) && !in_array($dominio_atual, $dados['dominios'])) {
            return ['status' => false, 'mensagem' => 'Domínio não autorizado!'];
        }
    }
    
    return [
        'status' => true,
        'mensagem' => 'Licença válida!',
        'dados' => $dados
    ];
}

// ============================================================
// FUNÇÃO: VALIDAR LICENÇA
// ============================================================
function validarLicenca() {
    // Se for admin, não bloquear
    if (isset($_SESSION['usuario_perfil']) && $_SESSION['usuario_perfil'] == 'admin') {
        return ['status' => true, 'mensagem' => 'Admin - Licença ignorada'];
    }
    
    $verificacao = verificarLicenca();
    
    if (!$verificacao['status']) {
        $mensagem = urlencode($verificacao['mensagem']);
        header("Location: " . SITE_URL . "license_error.php?erro=" . $mensagem);
        exit;
    }
    
    return $verificacao;
}

// ============================================================
// FUNÇÃO: OBTER INFORMAÇÕES DA LICENÇA
// ============================================================
function getInfoLicenca() {
    if (!file_exists(LICENSE_FILE)) {
        return null;
    }
    $conteudo = file_get_contents(LICENSE_FILE);
    return json_decode($conteudo, true);
}

// ============================================================
// FUNÇÃO: DIAS RESTANTES
// ============================================================
function diasRestantesLicenca() {
    $info = getInfoLicenca();
    if (!$info) return 0;
    $diferenca = strtotime($info['data_expiracao']) - time();
    return floor($diferenca / (60 * 60 * 24));
}

// ============================================================
// FUNÇÃO: ALERTA DE LICENÇA
// ============================================================
function alertaLicenca() {
    $dias = diasRestantesLicenca();
    $info = getInfoLicenca();
    
    if (!$info) {
        return '<div class="alert alert-warning"><strong>⚠️ Licença não encontrada!</strong></div>';
    }
    
    if ($dias <= 0) {
        return '<div class="alert alert-danger"><strong>⚠️ Licença Expirada!</strong> Contate o suporte.</div>';
    } elseif ($dias <= 30) {
        return '<div class="alert alert-warning"><strong>⚠️ Licença Expira em ' . $dias . ' dias!</strong></div>';
    }
    
    return '<div class="alert alert-success"><strong>✅ Licença Válida</strong> Expira em: ' . date('d/m/Y', strtotime($info['data_expiracao'])) . ' (' . $dias . ' dias)</div>';
}
?>