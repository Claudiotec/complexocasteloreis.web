<?php
// ============================================
// verificar_permissao.php - Verificação Centralizada de Permissões
// Módulo: Escola > Alunos
// ============================================

require_once 'mensagens_permissao.php';

// ============================================
// FUNÇÃO PARA VERIFICAR PERMISSÃO
// ============================================
function verificarPermissaoAluno($acao_necessaria) {
    // Se não estiver logado, redireciona
    if (!isset($_SESSION['usuario_id'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ../../../login.php');
        exit;
    }
    
    $usuario_id = $_SESSION['usuario_id'];
    $usuario_perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
    
    // 🔑 ADMIN TEM ACESSO TOTAL (ID = 1 ou perfil admin)
    if ($usuario_id == 1 || $usuario_perfil == 'admin') {
        return true;
    }
    
    // CONSULTA DIRETA AO BANCO
    try {
        $pdo_check = conectarBanco();
        
        // Primeiro tenta permissão específica para 'Alunos'
        $stmt = $pdo_check->prepare("SELECT visualizar, criar, editar, excluir FROM permissoes WHERE usuario_id = ? AND modulo = 'Alunos'");
        $stmt->execute([$usuario_id]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($perm) {
            // Verifica a ação específica
            switch ($acao_necessaria) {
                case 'visualizar':
                    return (int)$perm['visualizar'] === 1;
                case 'criar':
                    return (int)$perm['criar'] === 1;
                case 'editar':
                    return (int)$perm['editar'] === 1;
                case 'excluir':
                    return (int)$perm['excluir'] === 1;
                default:
                    return false;
            }
        }
        
        // Fallback: verifica permissão para módulo 'Escola'
        $stmt = $pdo_check->prepare("SELECT visualizar, criar, editar, excluir FROM permissoes WHERE usuario_id = ? AND modulo = 'Escola'");
        $stmt->execute([$usuario_id]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($perm) {
            switch ($acao_necessaria) {
                case 'visualizar':
                    return (int)$perm['visualizar'] === 1;
                case 'criar':
                    return (int)$perm['criar'] === 1;
                case 'editar':
                    return (int)$perm['editar'] === 1;
                case 'excluir':
                    return (int)$perm['excluir'] === 1;
                default:
                    return false;
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Erro ao verificar permissão: " . $e->getMessage());
        return false;
    }
}

// ============================================
// FUNÇÃO PARA BLOQUEAR ACESSO COM MENSAGEM
// ============================================
function bloquearAcesso($acao_necessaria, $voltar_para = 'index.php') {
    if (!verificarPermissaoAluno($acao_necessaria)) {
        exibirMensagemPermissaoNegada($acao_necessaria, $voltar_para);
    }
    return true;
}

// ============================================
// FUNÇÃO PARA VERIFICAR SE PODE (RETORNA BOOL)
// ============================================
function pode($acao) {
    return verificarPermissaoAluno($acao);
}

// ============================================
// FUNÇÃO PARA VERIFICAR E RETORNAR MENSAGEM
// ============================================
function verificarPermissaoComMensagem($acao, $voltar_para = 'index.php') {
    if (!verificarPermissaoAluno($acao)) {
        $_SESSION['erro_permissao'] = "Você não tem permissão para " . $acao . " neste módulo!";
        header('Location: ' . $voltar_para);
        exit;
    }
    return true;
}
?>