<?php
// includes/verificar_permissao.php
// Sistema de verificação de permissões - VERSÃO SIMPLES

function verificarPermissao($modulo, $acao = 'visualizar', $redirect = true, $voltar_para = null) {
    // Verificar se está logado
    if (!isset($_SESSION['usuario_id'])) {
        if ($redirect) {
            header('Location: ../login.php');
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
            require_once __DIR__ . '/../config/database.php';
            $pdo = conectarBanco();
        }
        
        $stmt = $pdo->prepare("SELECT $acao FROM permissoes WHERE usuario_id = ? AND modulo = ?");
        $stmt->execute([$_SESSION['usuario_id'], $modulo]);
        $result = $stmt->fetch();
        
        $tem_permissao = $result && $result[$acao] == 1;
        
        if (!$tem_permissao && $redirect) {
            // Se foi passado um caminho para voltar, usa ele
            if ($voltar_para) {
                header('Location: ' . $voltar_para . '?erro=permissao');
            } else {
                // Tenta voltar para a página anterior
                $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
                if (!empty($referer) && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
                    header('Location: ' . $referer . '?erro=permissao');
                } else {
                    // Fallback: vai para o módulo específico
                    header('Location: ../index.php?erro=permissao');
                }
            }
            exit;
        }
        
        return $tem_permissao;
        
    } catch (Exception $e) {
        if ($redirect) {
            if ($voltar_para) {
                header('Location: ' . $voltar_para . '?erro=permissao');
            } else {
                header('Location: ../index.php?erro=permissao');
            }
            exit;
        }
        return false;
    }
}

function podeCriar($modulo) {
    return verificarPermissao($modulo, 'criar', false);
}

function podeEditar($modulo) {
    return verificarPermissao($modulo, 'editar', false);
}

function podeExcluir($modulo) {
    return verificarPermissao($modulo, 'excluir', false);
}

function podeVisualizar($modulo) {
    return verificarPermissao($modulo, 'visualizar', false);
}