<?php
// ============================================
// sse_notificacoes.php - Server-Sent Events
// ============================================

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    echo "event: error\ndata: Não autorizado\n\n";
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$ultimo_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Enviar heartbeat a cada 10 segundos para manter conexão
$heartbeat = 10;
$timeout = 30;
$start_time = time();

while (true) {
    try {
        // Buscar novas notificações
        $stmt = $pdo->prepare("
            SELECT id, titulo, mensagem, icone, link, cor, created_at 
            FROM notificacoes_sistema 
            WHERE usuario_id = ? AND id > ? AND entregue = 0
            ORDER BY id ASC
        ");
        $stmt->execute([$usuario_id, $ultimo_id]);
        $notificacoes = $stmt->fetchAll();
        
        foreach ($notificacoes as $notif) {
            // Marcar como entregue
            $stmt2 = $pdo->prepare("UPDATE notificacoes_sistema SET entregue = 1 WHERE id = ?");
            $stmt2->execute([$notif['id']]);
            
            // Enviar notificação
            $data = json_encode([
                'id' => $notif['id'],
                'titulo' => $notif['titulo'],
                'mensagem' => $notif['mensagem'],
                'icone' => $notif['icone'] ?? '📬',
                'link' => $notif['link'] ?? null,
                'cor' => $notif['cor'] ?? 'gold',
                'created_at' => $notif['created_at']
            ]);
            
            echo "event: notification\ndata: $data\n\n";
            ob_flush();
            flush();
            
            $ultimo_id = $notif['id'];
        }
        
        // Heartbeat (mantém conexão viva)
        if (time() - $start_time > $heartbeat) {
            echo ": heartbeat\n\n";
            ob_flush();
            flush();
            $start_time = time();
        }
        
        // Timeout (30 segundos sem eventos)
        if (time() - $start_time > $timeout) {
            echo "event: ping\ndata: keepalive\n\n";
            ob_flush();
            flush();
            $start_time = time();
        }
        
        sleep(2); // Aguardar 2 segundos antes da próxima verificação
        
    } catch (Exception $e) {
        // Erro silencioso
        error_log("SSE Error: " . $e->getMessage());
        sleep(5);
    }
}
?>