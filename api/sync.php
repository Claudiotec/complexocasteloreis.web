<?php
// ============================================
// api/sync.php - API de Sincronização
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$acao = $_GET['acao'] ?? $input['acao'] ?? '';

// ============================================
// PROCESSAR AÇÕES
// ============================================

switch ($acao) {
    case 'ping':
        echo json_encode([
            'sucesso' => true,
            'timestamp' => time(),
            'servidor' => 'online'
        ]);
        break;
        
    case 'sync':
        $tabela = $input['tabela'] ?? '';
        $dados = $input['dados'] ?? [];
        $acao_sync = $input['acao'] ?? 'insert';
        
        try {
            // Processar sincronização conforme tabela
            $resultado = processarSync($tabela, $dados, $acao_sync);
            echo json_encode([
                'sucesso' => true,
                'resultado' => $resultado
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ]);
        }
        break;
        
    case 'status':
        echo json_encode([
            'sucesso' => true,
            'status' => 'online',
            'timestamp' => time()
        ]);
        break;
        
    default:
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Ação inválida'
        ]);
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function processarSync($tabela, $dados, $acao) {
    global $pdo;
    
    switch ($tabela) {
        case 'mensagens':
            return processarMensagens($dados, $acao);
        case 'frequencia':
            return processarFrequencia($dados, $acao);
        default:
            throw new Exception('Tabela não suportada: ' . $tabela);
    }
}

function processarMensagens($dados, $acao) {
    global $pdo;
    
    // Implementar lógica para mensagens
    return ['status' => 'ok', 'mensagem' => 'Mensagem sincronizada'];
}

function processarFrequencia($dados, $acao) {
    global $pdo;
    
    // Implementar lógica para frequência
    return ['status' => 'ok', 'mensagem' => 'Frequência sincronizada'];
}
?>