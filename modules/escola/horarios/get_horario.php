<?php
// ============================================
// get_horario.php - Buscar dados de um horário
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID não informado']);
    exit;
}

try {
    $pdo = conectarBanco();
    $stmt = $pdo->prepare("SELECT * FROM horarios WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $horario = $stmt->fetch();
    
    if ($horario) {
        echo json_encode([
            'success' => true,
            'id' => $horario['id'],
            'disciplina' => $horario['disciplina'],
            'funcionario_id' => $horario['funcionario_id'],
            'sala' => $horario['sala'],
            'is_intervalo' => $horario['is_intervalo']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Horário não encontrado']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}