<?php
// ============================================
// api_gerar_token.php - Gerar token de notificação
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Incluir configurações
require_once '../../config/database.php';
require_once '../../config/app_modes.php';

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

$usuario_id = (int)$_SESSION['usuario_id'];

try {
    // ============================================
    // CRIAR TABELA SE NÃO EXISTIR
    // ============================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dispositivos_conectados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            dispositivo_nome VARCHAR(100) DEFAULT NULL,
            dispositivo_tipo VARCHAR(50) DEFAULT 'web',
            token VARCHAR(100) UNIQUE NOT NULL,
            ultima_atividade TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ativo TINYINT(1) DEFAULT 1,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (usuario_id),
            INDEX idx_token (token),
            INDEX idx_ativo (ativo),
            INDEX idx_ultima_atividade (ultima_atividade)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ============================================
    // BUSCAR TOKEN EXISTENTE
    // ============================================
    $stmt = $pdo->prepare("
        SELECT token, id FROM dispositivos_conectados 
        WHERE usuario_id = ? AND ativo = 1 
        ORDER BY ultima_atividade DESC LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetch();

    if ($result) {
        $token = $result['token'];
        
        // Atualizar atividade
        $stmt = $pdo->prepare("
            UPDATE dispositivos_conectados 
            SET ultima_atividade = NOW(), ip = ?, user_agent = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $result['id']
        ]);
    } else {
        // Gerar novo token
        $token = bin2hex(random_bytes(32));
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $dispositivo_tipo = 'web';
        if (strpos($user_agent, 'Mobile') !== false) {
            $dispositivo_tipo = 'mobile';
        } elseif (strpos($user_agent, 'Tablet') !== false) {
            $dispositivo_tipo = 'tablet';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO dispositivos_conectados 
            (usuario_id, dispositivo_nome, dispositivo_tipo, token, ip, user_agent, ultima_atividade)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $usuario_id,
            $dispositivo_tipo . '_' . date('YmdHis'),
            $dispositivo_tipo,
            $token,
            $ip,
            $user_agent
        ]);
    }

    echo json_encode([
        'success' => true,
        'token' => $token,
        'message' => 'Token gerado com sucesso'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>