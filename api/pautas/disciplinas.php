<?php
// ============================================
// api/pautas/disciplinas.php - Listar Disciplinas
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';

// ===== SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== VERIFICAR LOGIN =====
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$ano_letivo = isset($_GET['ano_letivo']) ? $_GET['ano_letivo'] : date('Y') . '/' . (date('Y') + 1);
$usuario_email = $_SESSION['usuario_email'] ?? '';

try {
    $pdo = conectarBanco();
    
    // Buscar disciplinas do professor logado
    $disciplinas = [];
    
    // Buscar o num_agente do professor
    $stmt = $pdo->prepare("SELECT num_agente FROM funcionarios WHERE email = ? AND status = 'ativo' LIMIT 1");
    $stmt->execute([$usuario_email]);
    $professor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($professor) {
        $professor_num_agente = $professor['num_agente'];
        
        // Buscar disciplinas das distribuições do professor
        $sql = "SELECT DISTINCT disciplinas FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$professor_num_agente]);
        $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($resultados as $discs) {
            if (!empty($discs)) {
                $items = array_map('trim', explode(',', $discs));
                foreach ($items as $item) {
                    if (!empty($item) && !in_array($item, $disciplinas)) {
                        $disciplinas[] = $item;
                    }
                }
            }
        }
    }
    
    // Se não encontrou disciplinas, usar lista padrão
    if (empty($disciplinas)) {
        $disciplinas = [
            'L. PORTUGUESA', 'MATEMÁTICA', 'FÍSICA', 'QUÍMICA', 'BIOLOGIA',
            'HISTÓRIA', 'GEOGRAFIA', 'INGLÊS', 'ED. FÍSICA', 'E.M.C', 'E.V.P',
            'FILOSOFIA', 'ED. MANUAL PLÁSTICA', 'ED. MUSICAL', 'C. NATUREZA', 'ED. LABORAL'
        ];
    }
    
    sort($disciplinas);
    
    echo json_encode([
        'success' => true,
        'disciplinas' => $disciplinas,
        'total' => count($disciplinas),
        'ano_letivo' => $ano_letivo
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar disciplinas: ' . $e->getMessage()]);
}
?>