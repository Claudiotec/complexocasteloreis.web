<?php
// ============================================
// api_notificacoes.php - API de Notificações
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

// ============================================
// AÇÕES DA API
// ============================================

switch ($acao) {
    case 'listar':
        listarNotificacoes($pdo, $usuario_id);
        break;
    
    case 'marcar_lida':
        marcarComoLida($pdo, $usuario_id);
        break;
    
    case 'marcar_todas':
        marcarTodasComoLidas($pdo, $usuario_id);
        break;
    
    case 'contar_nao_lidas':
        contarNaoLidas($pdo, $usuario_id);
        break;
    
    case 'excluir':
        excluirNotificacao($pdo, $usuario_id);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação inválida']);
        break;
}

// ============================================
// FUNÇÕES
// ============================================

function listarNotificacoes($pdo, $usuario_id) {
    $limit = $_GET['limit'] ?? 20;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notificacoes 
            WHERE usuario_id IS NULL OR usuario_id = ?
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$usuario_id, $limit]);
        $notificacoes = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $notificacoes,
            'total' => count($notificacoes)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao listar notificações']);
    }
}

function marcarComoLida($pdo, $usuario_id) {
    $notificacao_id = $_POST['id'] ?? 0;
    
    if (!$notificacao_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da notificação não informado']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notificacoes 
            SET lido = TRUE 
            WHERE id = ? AND (usuario_id IS NULL OR usuario_id = ?)
        ");
        $stmt->execute([$notificacao_id, $usuario_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Notificação marcada como lida']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notificação não encontrada']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao marcar notificação']);
    }
}

function marcarTodasComoLidas($pdo, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notificacoes 
            SET lido = TRUE 
            WHERE (usuario_id IS NULL OR usuario_id = ?) AND lido = FALSE
        ");
        $stmt->execute([$usuario_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Todas as notificações marcadas como lidas',
            'count' => $stmt->rowCount()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao marcar notificações']);
    }
}

function contarNaoLidas($pdo, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM notificacoes 
            WHERE (usuario_id IS NULL OR usuario_id = ?) AND lido = FALSE
        ");
        $stmt->execute([$usuario_id]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'total' => (int)$result['total']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao contar notificações']);
    }
}

function excluirNotificacao($pdo, $usuario_id) {
    $notificacao_id = $_POST['id'] ?? 0;
    
    if (!$notificacao_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da notificação não informado']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM notificacoes 
            WHERE id = ? AND (usuario_id IS NULL OR usuario_id = ?)
        ");
        $stmt->execute([$notificacao_id, $usuario_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Notificação excluída']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notificação não encontrada']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao excluir notificação']);
    }
}