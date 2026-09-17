<?php
// api/distribuicao/editar.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Receber dados
$id = $_POST['id'] ?? 0;
$professor_id = $_POST['professor_id'] ?? 0;
$turma_id = $_POST['turma_id'] ?? 0;
$disciplinas = $_POST['disciplinas'] ?? '';
$ano_letivo = $_POST['ano_letivo'] ?? '2026';

// Validar
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

if (!$professor_id || !$turma_id || !$disciplinas) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

try {
    // Buscar dados do professor
    $stmt = $pdo->prepare("SELECT nome, cargo FROM funcionarios WHERE id = ?");
    $stmt->execute([$professor_id]);
    $professor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$professor) {
        echo json_encode(['success' => false, 'message' => 'Professor não encontrado']);
        exit;
    }
    
    // Buscar dados da turma
    $stmt = $pdo->prepare("SELECT nome, classe FROM turmas WHERE id = ?");
    $stmt->execute([$turma_id]);
    $turma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$turma) {
        echo json_encode(['success' => false, 'message' => 'Turma não encontrada']);
        exit;
    }
    
    // Buscar nomes das disciplinas
    $disciplinasArray = explode(',', $disciplinas);
    $disciplinasNomes = [];
    foreach ($disciplinasArray as $discId) {
        $stmt = $pdo->prepare("SELECT nome FROM disciplinas WHERE id = ?");
        $stmt->execute([$discId]);
        $disc = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($disc) {
            $disciplinasNomes[] = $disc['nome'];
        }
    }
    $disciplinasNomesStr = implode(', ', $disciplinasNomes);
    
    // Atualizar na tabela
    $stmt = $pdo->prepare("
        UPDATE destribuicao_professores
        SET 
            professor_id = ?,
            professor_nome = ?,
            turma_id = ?,
            turma_nome = ?,
            classe = ?,
            disciplinas = ?,
            ano_letivo = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $professor_id,
        $professor['nome'],
        $turma_id,
        $turma['nome'],
        $turma['classe'],
        $disciplinasNomesStr,
        $ano_letivo,
        $id
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Distribuição atualizada com sucesso!',
        'data' => [
            'id' => $id,
            'professor' => $professor['nome'],
            'turma' => $turma['nome'],
            'disciplinas' => $disciplinasNomesStr,
            'ano_letivo' => $ano_letivo
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao editar distribuição: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao editar: ' . $e->getMessage()
    ]);
}