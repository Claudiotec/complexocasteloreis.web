<?php
// ============================================
// testar_notificacao.php - Testar envio de notificação
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    die('Não autorizado');
}

$usuario_id = (int)$_SESSION['usuario_id'];

// Buscar token do usuário
$stmt = $pdo->prepare("
    SELECT token FROM dispositivos_conectados 
    WHERE usuario_id = ? AND ativo = 1 
    ORDER BY ultima_atividade DESC LIMIT 1
");
$stmt->execute([$usuario_id]);
$result = $stmt->fetch();

if (!$result) {
    die('Nenhum dispositivo conectado');
}

$token = $result['token'];

// Criar tabela de notificações se não existir
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

// Inserir notificação de teste
$stmt = $pdo->prepare("
    INSERT INTO notificacoes_multi_dispositivo 
    (usuario_id, dispositivo_token, titulo, mensagem, icone, link, cor, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->execute([
    $usuario_id,
    $token,
    '🧪 Teste de Notificação',
    'Esta é uma notificação de teste enviada manualmente!',
    '📱',
    '/softgest_web/modules/escola/financeiro/',
    'success'
]);

echo "✅ Notificação de teste enviada para o dispositivo!<br>";
echo "Token: " . substr($token, 0, 20) . "...<br>";
echo "Usuário ID: " . $usuario_id . "<br>";
echo "<br><a href='index.php'>Voltar</a>";
?>