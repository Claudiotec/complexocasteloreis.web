<?php
// modules/escola/includes/verificar_permissao_escola.php

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Função que já existe (ou vamos criar)
function verificarPermissaoEscola($usuario_id, $escola_id = null) {
    if (isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin') {
        return true;
    }
    
    if (isset($_SESSION['escola_id'])) {
        if ($escola_id && $_SESSION['escola_id'] != $escola_id) {
            return false;
        }
        return true;
    }
    
    return false;
}

// =============================================
// ADICIONE ESTA FUNÇÃO QUE ESTÁ FALTANDO:
// =============================================
function verificarMultiplasPermissoesEscola($usuario_id, $escola_ids = array()) {
    // Se for admin, tem permissão total
    if (isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin') {
        return true;
    }
    
    // Se não tem escola_id na sessão, negar
    if (!isset($_SESSION['escola_id'])) {
        return false;
    }
    
    // Se não passou escolas específicas, verifica se tem alguma escola
    if (empty($escola_ids)) {
        return true; // Tem permissão padrão
    }
    
    // Verifica se a escola do usuário está na lista de escolas permitidas
    return in_array($_SESSION['escola_id'], $escola_ids);
}

// Verificar permissão padrão
$usuario_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
if (!$usuario_id || !verificarPermissaoEscola($usuario_id)) {
    die('ACESSO NEGADO, SEM PERMISSÃO');
}
?>