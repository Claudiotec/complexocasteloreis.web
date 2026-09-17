<?php
// ============================================
// api/turmas/disciplinas.php
// Busca disciplinas das turmas
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuração do banco
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';

// Receber dados (POST ou GET)
$turmasIds = isset($_POST['turmas']) ? $_POST['turmas'] : (isset($_GET['turmas']) ? $_GET['turmas'] : '');

if (empty($turmasIds)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nenhuma turma selecionada',
        'disciplinas' => []
    ]);
    exit;
}

// Converter para array
$ids = explode(',', $turmasIds);
$ids = array_filter($ids, 'is_numeric'); // Filtrar apenas números

if (empty($ids)) {
    echo json_encode([
        'success' => false,
        'message' => 'IDs de turmas inválidos',
        'disciplinas' => []
    ]);
    exit;
}

try {
    // Buscar disciplinas das turmas
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, nome, disciplinas FROM turmas WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extrair disciplinas únicas
    $disciplinas = [];
    foreach ($turmas as $turma) {
        if (!empty($turma['disciplinas'])) {
            // Separar por vírgula e limpar
            $discs = explode(',', $turma['disciplinas']);
            foreach ($discs as $d) {
                $d = trim($d);
                if (!empty($d) && !in_array($d, $disciplinas)) {
                    $disciplinas[] = $d;
                }
            }
        }
    }
    
    // Ordenar alfabeticamente
    sort($disciplinas);
    
    echo json_encode([
        'success' => true,
        'message' => count($disciplinas) . ' disciplinas encontradas',
        'disciplinas' => $disciplinas,
        'total' => count($disciplinas)
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar disciplinas das turmas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar disciplinas: ' . $e->getMessage(),
        'disciplinas' => []
    ]);
}
?>