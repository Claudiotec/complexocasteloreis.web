<?php
// includes/verificar_permissao_escola.php

// Verificar se o usuário está logado
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /softgest_web/login.php');
    exit();
}

// Verificar permissão da escola
function verificarPermissaoEscola($usuario_id, $escola_id = null) {
    // Implemente sua lógica de permissão aqui
    // Exemplo básico:
    if ($_SESSION['perfil'] == 'admin') {
        return true;
    }
    
    // Verificar se o usuário tem acesso à escola específica
    if ($escola_id && $_SESSION['escola_id'] != $escola_id) {
        return false;
    }
    
    return true;
}

// Verificar permissão
if (!verificarPermissaoEscola($_SESSION['usuario_id'])) {
    die('ACESSO NEGADO, SEM PERMISSÃO');
}
?>