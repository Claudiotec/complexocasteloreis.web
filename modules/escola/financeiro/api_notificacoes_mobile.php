<?php
// ============================================
// api_notificacoes_mobile.php - API Multi-dispositivo
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function gerarToken() {
    return bin2hex(random_bytes(32));
}

function registrarDispositivo($pdo, $usuario_id, $dispositivo_nome = null) {
    $token = gerarToken();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $dispositivo_tipo = 'web';
    
    // Detectar se é mobile
    if (strpos($user_agent, 'Mobile') !== false) {
        $dispositivo_tipo = 'mobile';
    } elseif (strpos($user_agent, 'Tablet') !== false) {
        $dispositivo_tipo = 'tablet';
    }
    
    // Se não for informado nome, gerar baseado no tipo
    if (!$dispositivo_nome) {
        $dispositivo_nome = $dispositivo_tipo . '_' . date('YmdHis');
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO dispositivos_notificacao 
            (usuario_id, dispositivo_nome, dispositivo_tipo, token, ip, user_agent, ultima_atividade)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$usuario_id, $dispositivo_nome, $dispositivo_tipo, $token, $ip, $user_agent]);
        
        return [
            'success' => true,
            'token' => $token,
            'dispositivo_nome' => $dispositivo_nome,
            'dispositivo_tipo' => $dispositivo_tipo
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function enviarNotificacaoParaDispositivo($pdo, $token, $titulo, $mensagem, $icone = '📬', $link = null, $cor = 'gold') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO fila_notificacoes 
            (dispositivo_token, titulo, mensagem, icone, link, cor, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$token, $titulo, $mensagem, $icone, $link, $cor]);
        
        // Atualizar última atividade do dispositivo
        $stmt = $pdo->prepare("
            UPDATE dispositivos_notificacao 
            SET ultima_atividade = NOW() 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function enviarNotificacaoParaTodosDispositivos($pdo, $usuario_id, $titulo, $mensagem, $icone = '📬', $link = null, $cor = 'gold') {
    try {
        $stmt = $pdo->prepare("
            SELECT token FROM dispositivos_notificacao 
            WHERE usuario_id = ? AND ativo = 1
        ");
        $stmt->execute([$usuario_id]);
        $dispositivos = $stmt->fetchAll();
        
        $enviados = 0;
        foreach ($dispositivos as $dispositivo) {
            $result = enviarNotificacaoParaDispositivo(
                $pdo, 
                $dispositivo['token'], 
                $titulo, 
                $mensagem, 
                $icone, 
                $link, 
                $cor
            );
            if ($result['success']) $enviados++;
        }
        
        return [
            'success' => true,
            'total_dispositivos' => count($dispositivos),
            'enviados' => $enviados
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function buscarNotificacoesDispositivo($pdo, $token, $limite = 20) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM fila_notificacoes 
            WHERE dispositivo_token = ? AND entregue = 0
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$token, $limite]);
        $notificacoes = $stmt->fetchAll();
        
        // Marcar como entregues
        if (!empty($notificacoes)) {
            $ids = array_column($notificacoes, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("
                UPDATE fila_notificacoes 
                SET entregue = 1 
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($ids);
        }
        
        return ['success' => true, 'data' => $notificacoes];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ============================================
// ROTAS DA API
// ============================================

switch ($acao) {
    case 'registrar':
        $dispositivo_nome = $_POST['dispositivo_nome'] ?? $_GET['dispositivo_nome'] ?? null;
        $result = registrarDispositivo($pdo, $usuario_id, $dispositivo_nome);
        echo json_encode($result);
        break;
    
    case 'buscar':
        $token = $_GET['token'] ?? $_POST['token'] ?? '';
        if (!$token) {
            http_response_code(400);
            echo json_encode(['error' => 'Token não informado']);
            break;
        }
        
        // Verificar se o token pertence ao usuário
        $stmt = $pdo->prepare("
            SELECT id FROM dispositivos_notificacao 
            WHERE token = ? AND usuario_id = ? AND ativo = 1
        ");
        $stmt->execute([$token, $usuario_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Token inválido']);
            break;
        }
        
        $limite = $_GET['limite'] ?? 20;
        $result = buscarNotificacoesDispositivo($pdo, $token, $limite);
        echo json_encode($result);
        break;
    
    case 'enviar':
        $token = $_POST['token'] ?? $_GET['token'] ?? '';
        $titulo = $_POST['titulo'] ?? '';
        $mensagem = $_POST['mensagem'] ?? '';
        $icone = $_POST['icone'] ?? '📬';
        $link = $_POST['link'] ?? null;
        $cor = $_POST['cor'] ?? 'gold';
        
        if (!$token || !$titulo || !$mensagem) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados incompletos']);
            break;
        }
        
        // Verificar se o token pertence ao usuário
        $stmt = $pdo->prepare("
            SELECT id FROM dispositivos_notificacao 
            WHERE token = ? AND usuario_id = ? AND ativo = 1
        ");
        $stmt->execute([$token, $usuario_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Token inválido']);
            break;
        }
        
        $result = enviarNotificacaoParaDispositivo($pdo, $token, $titulo, $mensagem, $icone, $link, $cor);
        echo json_encode($result);
        break;
    
    case 'enviar_todos':
        $titulo = $_POST['titulo'] ?? '';
        $mensagem = $_POST['mensagem'] ?? '';
        $icone = $_POST['icone'] ?? '📬';
        $link = $_POST['link'] ?? null;
        $cor = $_POST['cor'] ?? 'gold';
        
        if (!$titulo || !$mensagem) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados incompletos']);
            break;
        }
        
        $result = enviarNotificacaoParaTodosDispositivos($pdo, $usuario_id, $titulo, $mensagem, $icone, $link, $cor);
        echo json_encode($result);
        break;
    
    case 'listar_dispositivos':
        $stmt = $pdo->prepare("
            SELECT id, dispositivo_nome, dispositivo_tipo, token, ultima_atividade, ativo, ip, created_at
            FROM dispositivos_notificacao 
            WHERE usuario_id = ?
            ORDER BY ultima_atividade DESC
        ");
        $stmt->execute([$usuario_id]);
        $dispositivos = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $dispositivos]);
        break;
    
    case 'desativar':
        $token = $_POST['token'] ?? $_GET['token'] ?? '';
        if (!$token) {
            http_response_code(400);
            echo json_encode(['error' => 'Token não informado']);
            break;
        }
        
        $stmt = $pdo->prepare("
            UPDATE dispositivos_notificacao 
            SET ativo = 0 
            WHERE token = ? AND usuario_id = ?
        ");
        $stmt->execute([$token, $usuario_id]);
        echo json_encode(['success' => true, 'message' => 'Dispositivo desativado']);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação inválida']);
        break;
}