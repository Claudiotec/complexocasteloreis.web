<?php
// ============================================
// api/user/data.php - Dados do Usuário
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

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$usuario_email = $_SESSION['usuario_email'] ?? '';

try {
    $pdo = conectarBanco();
    
    // Buscar dados do funcionário
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ? AND status = 'ativo' LIMIT 1");
    $stmt->execute([$usuario_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$funcionario) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
    
    // Buscar num_agente
    $professor_num_agente = $funcionario['num_agente'] ?? null;
    
    // Buscar distribuições
    $classes = [];
    $turmas = [];
    $disciplinas = [];
    
    if ($professor_num_agente) {
        $sql = "SELECT * FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$professor_num_agente]);
        $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($distribuicoes as $dist) {
            if (!empty($dist['classe']) && !in_array($dist['classe'], $classes)) {
                $classes[] = trim($dist['classe']);
            }
            if (!empty($dist['turma_nome']) && !in_array($dist['turma_nome'], $turmas)) {
                $turmas[] = trim($dist['turma_nome']);
            }
            if (!empty($dist['disciplinas'])) {
                $discs = array_map('trim', explode(',', $dist['disciplinas']));
                foreach ($discs as $disc) {
                    if (!empty($disc) && !in_array($disc, $disciplinas)) {
                        $disciplinas[] = $disc;
                    }
                }
            }
        }
    }
    
    sort($classes);
    sort($turmas);
    sort($disciplinas);
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $funcionario['id'],
            'nome' => $funcionario['nome'],
            'email' => $funcionario['email'],
            'cargo' => $funcionario['cargo'] ?? 'Professor',
            'num_agente' => $professor_num_agente,
            'classe' => $classes,
            'turma' => $turmas,
            'disciplina' => $disciplinas,
            'escola' => $_SESSION['escola_nome'] ?? 'COMPLEXO ESCOLAR CASTELO REIS'
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar dados: ' . $e->getMessage()]);
}
?>