<?php
/**
 * Verificação de Permissão para Módulo Escola
 * 
 * @package Softgest
 * @subpackage Modules/Escola/Includes
 * @version 1.0.0
 */

// ============================================
// 1. INICIAR SESSÃO
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 2. VERIFICAR LOGIN
// ============================================

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] == '') {
    header('Location: ../../../../login.php');
    exit;
}

// ============================================
// 3. CONEXÃO COM BANCO DE DADOS
// ============================================

// Verifica se a conexão já existe
if (!isset($conn) || !$conn) {
    $db_host = 'localhost';
    $db_name = 'softgest_db';
    $db_user = 'root';
    $db_pass = '';
    
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    
    if (!$conn) {
        die('<div class="alert alert-danger">
            <h4>Erro de Conexão</h4>
            <p>' . mysqli_connect_error() . '</p>
        </div>');
    }
    
    mysqli_set_charset($conn, "utf8mb4");
}

// ============================================
// 4. FUNÇÃO PRINCIPAL DE VERIFICAÇÃO
// ============================================

/**
 * Verifica se o usuário tem permissão para um módulo específico
 * 
 * @param string $modulo Nome do módulo (ex: 'escola', 'financeiro', 'rh')
 * @param string $acao Ação a ser verificada (ex: 'visualizar', 'criar', 'editar', 'excluir')
 * @return bool
 */
function verificarPermissaoEscola($modulo, $acao = 'visualizar') {
    global $conn;
    
    // Se não tem conexão, retorna false
    if (!$conn) {
        return false;
    }
    
    // Usuário administrador tem acesso total
    if (isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin') {
        return true;
    }
    
    // Verifica permissão na tabela permissoes
    $usuario_id = (int)$_SESSION['usuario_id'];
    
    $query = "SELECT COUNT(*) as total 
              FROM permissoes 
              WHERE usuario_id = ? 
              AND modulo = ? 
              AND $acao = 1";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "is", $usuario_id, $modulo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $row['total'] > 0;
}

/**
 * Verifica múltiplas permissões de uma vez
 * 
 * @param string $modulo Nome do módulo
 * @param array $acoes Lista de ações ['visualizar', 'criar', 'editar', 'excluir']
 * @return array Array com as permissões
 */
function verificarMultiplasPermissoesEscola($modulo, $acoes = ['visualizar', 'criar', 'editar', 'excluir']) {
    $permissoes = [];
    
    foreach ($acoes as $acao) {
        $permissoes[$acao] = verificarPermissaoEscola($modulo, $acao);
    }
    
    return $permissoes;
}

/**
 * Verifica se o usuário tem permissão para acessar o módulo escola
 * Versão simplificada para uso rápido
 * 
 * @return bool
 */
function temAcessoEscola() {
    // Administrador sempre tem acesso
    if (isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin') {
        return true;
    }
    
    return verificarPermissaoEscola('escola', 'visualizar');
}

// ============================================
// 5. VERIFICAÇÃO AUTOMÁTICA PARA O MÓDULO ESCOLA
// ============================================

// Se a variável $modulo_escola_verificar for definida como true, faz a verificação automática
if (isset($modulo_escola_verificar) && $modulo_escola_verificar === true) {
    $modulo = isset($modulo_escola_nome) ? $modulo_escola_nome : 'escola';
    $acao = isset($modulo_escola_acao) ? $modulo_escola_acao : 'visualizar';
    
    if (!verificarPermissaoEscola($modulo, $acao)) {
        die('<div class="alert alert-danger" style="margin: 20px; padding: 20px; border-radius: 5px;">
            <h4><i class="fas fa-ban"></i> Acesso Negado</h4>
            <p>Você não tem permissão para acessar este módulo.</p>
            <p><strong>Módulo:</strong> ' . htmlspecialchars($modulo) . '</p>
            <p><strong>Ação:</strong> ' . htmlspecialchars($acao) . '</p>
            <hr>
            <p>Entre em contato com o administrador do sistema.</p>
        </div>');
        exit;
    }
}

// ============================================
// 6. CRIAR PERMISSÕES PADRÃO (SE NÃO EXISTIREM)
// ============================================

/**
 * Cria permissões padrão para um usuário no módulo escola
 * 
 * @param int $usuario_id ID do usuário
 * @param array $permissoes Lista de permissões ['visualizar', 'criar', 'editar', 'excluir']
 */
function criarPermissoesPadraoEscola($usuario_id, $permissoes = ['visualizar' => 1, 'criar' => 1, 'editar' => 1, 'excluir' => 0]) {
    global $conn;
    
    if (!$conn) return false;
    
    // Verifica se já existe permissão para este usuário
    $check = "SELECT id FROM permissoes WHERE usuario_id = ? AND modulo = 'escola'";
    $stmt = mysqli_prepare($conn, $check);
    mysqli_stmt_bind_param($stmt, "i", $usuario_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Atualiza permissões existentes
        $update = "UPDATE permissoes SET 
                    visualizar = ?, 
                    criar = ?, 
                    editar = ?, 
                    excluir = ? 
                  WHERE usuario_id = ? AND modulo = 'escola'";
        
        $stmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($stmt, "iiiii", 
            $permissoes['visualizar'], 
            $permissoes['criar'], 
            $permissoes['editar'], 
            $permissoes['excluir'], 
            $usuario_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        // Insere novas permissões
        $insert = "INSERT INTO permissoes (usuario_id, modulo, visualizar, criar, editar, excluir) 
                   VALUES (?, 'escola', ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $insert);
        mysqli_stmt_bind_param($stmt, "iiiii", 
            $usuario_id, 
            $permissoes['visualizar'], 
            $permissoes['criar'], 
            $permissoes['editar'], 
            $permissoes['excluir']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    return true;
}

// ============================================
// 7. RETORNAR FUNÇÕES PARA USO EXTERNO
// ============================================

// Retorna as funções para serem usadas em outros arquivos
// Se o arquivo for incluído, as funções ficam disponíveis

// ============================================
// 8. LOG DE ACESSO (OPCIONAL)
// ============================================

/**
 * Registra o acesso do usuário ao módulo escola
 */
function registrarAcessoEscola() {
    global $conn;
    
    if (!$conn) return false;
    
    $usuario_id = (int)$_SESSION['usuario_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $data_hora = date('Y-m-d H:i:s');
    
    $query = "INSERT INTO logs_escolares (usuario_id, acao, tabela, descricao, ip, data_hora) 
              VALUES (?, 'acesso', 'modulo_escola', 'Acesso ao módulo Escola', ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iss", $usuario_id, $ip, $data_hora);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

// Se o usuário tem acesso, registra o acesso
if (temAcessoEscola()) {
    registrarAcessoEscola();
}
?>