<?php
// ============================================
// enviar_notificacao.php - Enviar notificação
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$titulo = $_POST['titulo'] ?? '';
$mensagem = $_POST['mensagem'] ?? '';
$icone = $_POST['icone'] ?? '📬';
$link = $_POST['link'] ?? null;
$cor = $_POST['cor'] ?? 'gold';

if (!$titulo || !$mensagem) {
    http_response_code(400);
    echo json_encode(['error' => 'Título e mensagem são obrigatórios']);
    exit;
}

try {
    // Criar tabela se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notificacoes_sistema (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            mensagem TEXT NOT NULL,
            icone VARCHAR(50) DEFAULT '📬',
            link VARCHAR(255) DEFAULT NULL,
            cor VARCHAR(20) DEFAULT 'gold',
            lida TINYINT(1) DEFAULT 0,
            entregue TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (usuario_id),
            INDEX idx_entregue (entregue),
            INDEX idx_lida (lida),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Inserir notificação
    $stmt = $pdo->prepare("
        INSERT INTO notificacoes_sistema 
        (usuario_id, titulo, mensagem, icone, link, cor, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$usuario_id, $titulo, $mensagem, $icone, $link, $cor]);
    
    $id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'id' => $id,
        'message' => 'Notificação enviada com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>