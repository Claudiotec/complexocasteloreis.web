<?php
// ============================================
// chamada_polling.php - Polling para chamadas
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
$ultimo_id = intval($_GET['ultimo_id'] ?? 0);
$chamada_id = intval($_GET['chamada_id'] ?? 0);
$ultimo_msg = intval($_GET['ultimo_msg'] ?? 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$response = [
    'success' => true,
    'chamadas' => [],
    'mensagens' => [],
    'pendentes' => 0,
    'ultimo_id' => $ultimo_id
];

try {
    // Buscar novas chamadas
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u1.nome as origem_nome, u1.perfil as origem_perfil,
               u2.nome as destino_nome, u2.perfil as destino_perfil
        FROM chamadas_internas c
        LEFT JOIN usuarios u1 ON c.usuario_origem = u1.id
        LEFT JOIN usuarios u2 ON c.usuario_destino = u2.id
        WHERE (c.usuario_destino = ? OR c.usuario_origem = ?)
        AND c.id > ?
        AND c.status IN ('pendente', 'em_andamento')
        ORDER BY c.id ASC
    ");
    $stmt->execute([$usuario_id, $usuario_id, $ultimo_id]);
    $chamadas = $stmt->fetchAll();
    
    foreach ($chamadas as $chamada) {
        $response['chamadas'][] = $chamada;
        if ($chamada['id'] > $ultimo_id) {
            $ultimo_id = $chamada['id'];
        }
    }
    $response['ultimo_id'] = $ultimo_id;
    
    // Buscar novas mensagens
    if ($chamada_id > 0) {
        $stmt = $pdo->prepare("
            SELECT m.*, u.nome, u.perfil
            FROM chamadas_mensagens m
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.chamada_id = ? AND m.id > ?
            ORDER BY m.id ASC
        ");
        $stmt->execute([$chamada_id, $ultimo_msg]);
        $mensagens = $stmt->fetchAll();
        
        foreach ($mensagens as $msg) {
            $response['mensagens'][] = $msg;
        }
    }
    
    // Contar chamadas pendentes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM chamadas_internas 
        WHERE usuario_destino = ? AND status = 'pendente'
    ");
    $stmt->execute([$usuario_id]);
    $pendentes = $stmt->fetch();
    $response['pendentes'] = $pendentes['total'] ?? 0;
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>