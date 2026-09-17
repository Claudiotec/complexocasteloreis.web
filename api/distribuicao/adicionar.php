<?php
// ============================================
// api/distribuicao/adicionar.php
// Adicionar distribuição de professores
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';

// Receber dados
$professor_id = isset($_POST['professor_id']) ? $_POST['professor_id'] : null;
$turmas = isset($_POST['turmas']) ? $_POST['turmas'] : '';
$classes = isset($_POST['classes']) ? $_POST['classes'] : '';
$disciplinas = isset($_POST['disciplinas']) ? $_POST['disciplinas'] : '';
$ano_letivo = isset($_POST['ano_letivo']) ? $_POST['ano_letivo'] : '2026';

// DEBUG
error_log("=== ADICIONAR DISTRIBUIÇÃO ===");
error_log("professor_id: " . $professor_id);
error_log("turmas: " . $turmas);
error_log("classes: " . $classes);
error_log("disciplinas: " . $disciplinas);

// VALIDAR
if ($professor_id === null || $professor_id === '' || $professor_id === 'null') {
    echo json_encode(['success' => false, 'message' => 'Selecione um professor']);
    exit;
}

if (empty($turmas)) {
    echo json_encode(['success' => false, 'message' => 'Selecione pelo menos uma turma']);
    exit;
}

if (empty($classes)) {
    echo json_encode(['success' => false, 'message' => 'Selecione pelo menos uma classe']);
    exit;
}

if (empty($disciplinas)) {
    echo json_encode(['success' => false, 'message' => 'Selecione pelo menos uma disciplina']);
    exit;
}

try {
    // Buscar nome e num_agente do professor
    $stmt = $pdo->prepare("SELECT nome, num_agente FROM funcionarios WHERE id = ?");
    $stmt->execute([$professor_id]);
    $professor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$professor) {
        echo json_encode(['success' => false, 'message' => 'Professor não encontrado']);
        exit;
    }
    
    $professor_nome = $professor['nome'];
    // USAR O num_agente COMO professor_id
    $professor_num_agente = $professor['num_agente'];
    
    // Converter turmas para array
    $turmasArray = explode(',', $turmas);
    $turmasArray = array_filter($turmasArray);
    
    if (empty($turmasArray)) {
        echo json_encode(['success' => false, 'message' => 'IDs de turmas inválidos']);
        exit;
    }
    
    // Buscar dados das turmas
    $placeholders = implode(',', array_fill(0, count($turmasArray), '?'));
    $sql = "SELECT id, nome, classe FROM turmas WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($turmasArray);
    $turmasDados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($turmasDados)) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma turma encontrada']);
        exit;
    }
    
    // Inserir na tabela usando num_agente como professor_id
    $inseridos = 0;
    foreach ($turmasDados as $turma) {
        $stmt = $pdo->prepare("
            INSERT INTO destribuicao_professores 
            (professor_id, professor_nome, turma_id, turma_nome, classe, disciplinas, ano_letivo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $professor_num_agente,  // USANDO num_agente
            $professor_nome,
            $turma['id'],
            $turma['nome'],
            $turma['classe'],
            $disciplinas,
            $ano_letivo
        ]);
        $inseridos++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => $inseridos . ' distribuição(ões) adicionada(s) com sucesso!',
        'total' => $inseridos
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao adicionar distribuição: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>