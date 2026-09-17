<?php
// modules/escola/includes/verificar_permissao_escola.php
// Verificação de permissões - SEMPRE redireciona para o index da pasta escola

function verificarPermissaoEscola($modulo, $acao = 'visualizar', $redirect = true) {
    // Verificar se está logado
    if (!isset($_SESSION['usuario_id'])) {
        if ($redirect) {
            header('Location: ../../../login.php');
            exit;
        }
        return false;
    }
    
    // Admin tem acesso total
    if ($_SESSION['usuario_perfil'] == 'admin') {
        return true;
    }
    
    try {
        global $pdo;
        if (!isset($pdo)) {
            require_once '../../../config/database.php';
            $pdo = conectarBanco();
        }
        
        $stmt = $pdo->prepare("SELECT $acao FROM permissoes WHERE usuario_id = ? AND modulo = ?");
        $stmt->execute([$_SESSION['usuario_id'], $modulo]);
        $result = $stmt->fetch();
        
        $tem_permissao = $result && $result[$acao] == 1;
        
        if (!$tem_permissao && $redirect) {
            // SEMPRE redireciona para o index da pasta escola
            header('Location: ../index.php?erro=permissao');
            exit;
        }
        
        return $tem_permissao;
        
    } catch (Exception $e) {
        if ($redirect) {
            header('Location: ../index.php?erro=permissao');
            exit;
        }
        return false;
    }
}

function podeCriarEscola($modulo) {
    return verificarPermissaoEscola($modulo, 'criar', false);
}

function podeEditarEscola($modulo) {
    return verificarPermissaoEscola($modulo, 'editar', false);
}

function podeExcluirEscola($modulo) {
    return verificarPermissaoEscola($modulo, 'excluir', false);
}

function podeVisualizarEscola($modulo) {
    return verificarPermissaoEscola($modulo, 'visualizar', false);
}
?>