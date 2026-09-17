<?php
// ============================================
// api/user_data.php - Dados do Professor Logado
// ============================================

// Definição do caminho base
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) session_start();

// Configurar cabeçalho JSON
header('Content-Type: application/json');

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

try {
    $usuario_id = $_SESSION['usuario_id'];
    $usuario_email = $_SESSION['usuario_email'] ?? '';
    
    // Buscar dados do professor
    $sql = "SELECT 
                id,
                nome,
                email,
                cargo,
                categoria_actual,
                disciplina_lecciona,
                departamento,
                status,
                telefone,
                created_at
            FROM funcionarios 
            WHERE id = ? OR email = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, $usuario_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }

    // Buscar disciplinas do professor
    $disciplinas = [];
    if (!empty($user['disciplina_lecciona'])) {
        $disciplinas = array_map('trim', explode(',', $user['disciplina_lecciona']));
    }

    // Buscar turmas do professor (turmas onde ele leciona)
    $turmas = [];
    $classes = [];
    
    if (!empty($disciplinas)) {
        // Buscar turmas e classes das disciplinas do professor
        $placeholders = implode(',', array_fill(0, count($disciplinas), '?'));
        $sql_turmas = "SELECT DISTINCT TURMA, Classe FROM alunos WHERE disciplina IN ($placeholders) AND TURMA IS NOT NULL AND TURMA != ''";
        $stmt_turmas = $pdo->prepare($sql_turmas);
        $stmt_turmas->execute($disciplinas);
        $resultados = $stmt_turmas->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultados as $row) {
            if (!empty($row['TURMA'])) {
                $turmas[] = $row['TURMA'];
            }
            if (!empty($row['Classe'])) {
                $classes[] = $row['Classe'];
            }
        }
    }

    // Se não encontrou turmas, buscar todas disponíveis
    if (empty($turmas)) {
        $stmt_turmas = $pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA");
        $turmas = $stmt_turmas->fetchAll(PDO::FETCH_COLUMN);
    }
    
    if (empty($classes)) {
        $stmt_classes = $pdo->query("SELECT DISTINCT Classe FROM alunos WHERE Classe IS NOT NULL AND Classe != '' ORDER BY Classe");
        $classes = $stmt_classes->fetchAll(PDO::FETCH_COLUMN);
    }

    // Dados de retorno
    $user_data = [
        'id' => $user['id'],
        'nome' => $user['nome'],
        'email' => $user['email'],
        'cargo' => $user['cargo'],
        'categoria' => $user['categoria_actual'],
        'disciplina' => array_values(array_unique($disciplinas)),
        'turma' => array_values(array_unique($turmas)),
        'classe' => array_values(array_unique($classes)),
        'departamento' => $user['departamento'],
        'status' => $user['status'],
        'telefone' => $user['telefone']
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Dados carregados com sucesso',
        'user' => $user_data
    ]);

} catch (PDOException $e) {
    error_log("Erro ao buscar dados do usuário: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}
?>