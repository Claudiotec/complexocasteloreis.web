<?php
// ============================================
// keep_alive.php - Mantém o sistema ativo
// ============================================

// ============================================
// 1. MANTER SESSÃO ATIVA
// ============================================
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    // Se não estiver logado, apenas mantém a conexão
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'message' => 'Sistema ativo']);
    exit;
}

// Renovar a sessão para mantê-la ativa
$_SESSION['last_activity'] = time();

// ============================================
// 2. MANTER CONEXÃO COM O BANCO DE DADOS
// ============================================
require_once 'config/database.php';

try {
    $pdo = conectarBanco();
    // Testar conexão
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    // Se cair, tenta reconectar
    error_log("Keep Alive: Reconectando ao banco de dados...");
}

// ============================================
// 3. EXECUTAR TAREFAS LEVES EM BACKGROUND
// ============================================

// Limpar chamadas antigas (mais de 1 hora)
try {
    $pdo->prepare("
        UPDATE chamadas_internas 
        SET status = 'finalizada' 
        WHERE status IN ('pendente', 'em_andamento') 
        AND data_chamada < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")->execute();
} catch (Exception $e) {
    // Ignora erro se a tabela não existir
}

// ============================================
// 4. RESPOSTA
// ============================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

echo json_encode([
    'status' => 'ok',
    'timestamp' => time(),
    'session_id' => session_id(),
    'usuario' => $_SESSION['usuario_nome'] ?? 'Convidado',
    'message' => 'Sistema mantido ativo'
]);
?>