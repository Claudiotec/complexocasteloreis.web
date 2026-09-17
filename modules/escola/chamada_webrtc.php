<?php
// ============================================
// chamada_webrtc.php - Sinalização WebRTC
// ============================================

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

header('Content-Type: application/json');

try {
    if ($acao == 'salvar') {
        $chamada_id = intval($_POST['chamada_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';
        $dados = $_POST['dados'] ?? '';
        
        if (!$chamada_id || !$tipo || !$dados) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO chamadas_webrtc (chamada_id, usuario_id, tipo, dados)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$chamada_id, $usuario_id, $tipo, $dados]);
        
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }
    
    if ($acao == 'buscar') {
        $chamada_id = intval($_GET['chamada_id'] ?? 0);
        $ultimo_id = intval($_GET['ultimo_id'] ?? 0);
        
        $stmt = $pdo->prepare("
            SELECT * FROM chamadas_webrtc 
            WHERE chamada_id = ? AND id > ? AND usuario_id != ?
            ORDER BY id ASC
        ");
        $stmt->execute([$chamada_id, $ultimo_id, $usuario_id]);
        $dados = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'dados' => $dados]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>