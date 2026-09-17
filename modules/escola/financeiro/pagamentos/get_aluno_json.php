<?php
// get_aluno_json.php
require_once '../../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

$id = intval($_GET['id']);

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM pendencias_anterior WHERE id = ?");
    $stmt->execute([$id]);
    $aluno = $stmt->fetch();
    
    if ($aluno) {
        echo json_encode([
            'success' => true,
            'nome' => $aluno['nome_aluno'],
            'classe' => $aluno['classe'],
            'turma' => $aluno['turma'],
            'propina_status' => $aluno['propina_status'],
            'transporte_status' => $aluno['transporte_status']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aluno não encontrado']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>