<?php
// ============================================
// ajax_marcar_lida.php
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
$mensagem_id = isset($_POST['mensagem_id']) ? (int)$_POST['mensagem_id'] : 0;

if (!$mensagem_id) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID da mensagem não informado']);
    exit;
}

// Marca como lida
$resultado = marcarComoLida($mensagem_id, $usuario_id);

echo json_encode([
    'sucesso' => $resultado,
    'total_nao_lidas' => getTotalMensagensNaoLidas($usuario_id)
]);
?>