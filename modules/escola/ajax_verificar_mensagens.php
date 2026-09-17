<?php
// ============================================
// ajax_verificar_mensagens.php
// ============================================

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once 'includes/mensagens_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Usuário não logado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$ultimo_id = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;

// Busca novas mensagens não lidas
$stmt = $pdo->prepare("
    SELECT m.*, 
           u.nome as remetente_nome, 
           u.perfil as remetente_perfil
    FROM mensagens m
    LEFT JOIN usuarios u ON m.remetente_id = u.id
    WHERE ((m.destinatario_id = ? AND m.tipo = 'privada')
           OR m.tipo = 'publica')
    AND m.status = 'nao_lida'
    AND m.id > ?
    ORDER BY m.data_envio DESC
");
$stmt->execute([$usuario_id, $ultimo_id]);
$novas_mensagens = $stmt->fetchAll();

// Total de mensagens não lidas
$total_nao_lidas = getTotalMensagensNaoLidas($usuario_id);

// Atualiza o último ID se houver novas mensagens
$ultimo_id_atualizado = $ultimo_id;
if (!empty($novas_mensagens)) {
    $ultimo_id_atualizado = $novas_mensagens[0]['id'];
}

$response = [
    'sucesso' => true,
    'total_nao_lidas' => $total_nao_lidas,
    'novas_mensagens' => $novas_mensagens,
    'ultimo_id' => $ultimo_id_atualizado
];

echo json_encode($response);
?>