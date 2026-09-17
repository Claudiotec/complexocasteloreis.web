<?php
// ============================================
// api_notificacoes_multi.php - API Multi-dispositivo
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

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

function listarDispositivos($pdo, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, dispositivo_nome, dispositivo_tipo, token, 
                   ultima_atividade, ativo, ip, created_at
            FROM dispositivos_conectados 
            WHERE usuario_id = ?
            ORDER BY ultima_atividade DESC
        ");
        $stmt->execute([$usuario_id]);
        $dispositivos = $stmt->fetchAll();
        
        return ['success' => true, 'data' => $dispositivos];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function buscarNotificacoes($pdo, $usuario_id, $token, $limite = 20) {
    try {
        // Verificar se o token pertence ao usuário
        $stmt = $pdo->prepare("
            SELECT id FROM dispositivos_conectados 
            WHERE token = ? AND usuario_id = ? AND ativo = 1
        ");
        $stmt->execute([$token, $usuario_id]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Token inválido'];
        }
        
        // Criar tabela se não existir
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notificacoes_multi_dispositivo (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                dispositivo_token VARCHAR(100) NOT NULL,
                titulo VARCHAR(200) NOT NULL,
                mensagem TEXT NOT NULL,
                icone VARCHAR(50) DEFAULT '📬',
                link VARCHAR(255) DEFAULT NULL,
                cor VARCHAR(20) DEFAULT 'gold',
                lida TINYINT(1) DEFAULT 0,
                entregue TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_usuario (usuario_id),
                INDEX idx_dispositivo (dispositivo_token),
                INDEX idx_entregue (entregue),
                INDEX idx_lida (lida)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Buscar notificações não entregues
        $stmt = $pdo->prepare("
            SELECT * FROM notificacoes_multi_dispositivo 
            WHERE usuario_id = ? AND dispositivo_token = ? AND entregue = 0
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$usuario_id, $token, (int)$limite]);
        $notificacoes = $stmt->fetchAll();
        
        // Marcar como entregues
        if (!empty($notificacoes)) {
            $ids = array_column($notificacoes, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("
                UPDATE notificacoes_multi_dispositivo 
                SET entregue = 1 
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($ids);
        }
        
        // Atualizar última atividade do dispositivo
        $stmt = $pdo->prepare("
            UPDATE dispositivos_conectados 
            SET ultima_atividade = NOW() 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        
        return ['success' => true, 'data' => $notificacoes];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function enviarNotificacaoMulti($pdo, $usuario_id, $titulo, $mensagem, $icone = '📬', $link = null, $cor = 'gold') {
    try {
        // Buscar token do dispositivo (se for enviado para um específico)
        $token_especifico = $_POST['token'] ?? null;
        
        // Buscar dispositivos do usuário
        $sql = "SELECT token FROM dispositivos_conectados WHERE usuario_id = ? AND ativo = 1";
        $params = [$usuario_id];
        
        if ($token_especifico) {
            $sql .= " AND token = ?";
            $params[] = $token_especifico;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dispositivos = $stmt->fetchAll();
        
        if (empty($dispositivos)) {
            return ['success' => false, 'error' => 'Nenhum dispositivo conectado'];
        }
        
        // Criar tabela se não existir
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notificacoes_multi_dispositivo (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                dispositivo_token VARCHAR(100) NOT NULL,
                titulo VARCHAR(200) NOT NULL,
                mensagem TEXT NOT NULL,
                icone VARCHAR(50) DEFAULT '📬',
                link VARCHAR(255) DEFAULT NULL,
                cor VARCHAR(20) DEFAULT 'gold',
                lida TINYINT(1) DEFAULT 0,
                entregue TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_usuario (usuario_id),
                INDEX idx_dispositivo (dispositivo_token),
                INDEX idx_entregue (entregue),
                INDEX idx_lida (lida)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $enviados = 0;
        $ultimo_id = 0;
        
        foreach ($dispositivos as $disp) {
            $stmt = $pdo->prepare("
                INSERT INTO notificacoes_multi_dispositivo 
                (usuario_id, dispositivo_token, titulo, mensagem, icone, link, cor, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$usuario_id, $disp['token'], $titulo, $mensagem, $icone, $link, $cor]);
            $ultimo_id = $pdo->lastInsertId();
            $enviados++;
        }
        
        return [
            'success' => true,
            'id' => $ultimo_id,
            'total_dispositivos' => count($dispositivos),
            'enviados' => $enviados,
            'message' => 'Notificação enviada com sucesso'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ============================================
// ROTAS DA API
// ============================================

switch ($acao) {
    case 'listar_dispositivos':
        $result = listarDispositivos($pdo, $usuario_id);
        echo json_encode($result);
        break;
    
    case 'buscar':
        $token = $_GET['token'] ?? $_POST['token'] ?? '';
        if (!$token) {
            http_response_code(400);
            echo json_encode(['error' => 'Token não informado']);
            break;
        }
        $limite = $_GET['limite'] ?? 20;
        $result = buscarNotificacoes($pdo, $usuario_id, $token, $limite);
        echo json_encode($result);
        break;
    
    case 'enviar':
        $titulo = $_POST['titulo'] ?? '';
        $mensagem = $_POST['mensagem'] ?? '';
        $icone = $_POST['icone'] ?? '📬';
        $link = $_POST['link'] ?? null;
        $cor = $_POST['cor'] ?? 'gold';
        
        if (!$titulo || !$mensagem) {
            http_response_code(400);
            echo json_encode(['error' => 'Título e mensagem são obrigatórios']);
            break;
        }
        
        $result = enviarNotificacaoMulti($pdo, $usuario_id, $titulo, $mensagem, $icone, $link, $cor);
        echo json_encode($result);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação inválida: ' . $acao]);
        break;
}
?>