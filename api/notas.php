<?php
// ============================================
// api/notas.php - Gerenciar Notas (GET e POST)
// ============================================

// Definição do caminho base
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Configurar cabeçalho JSON
header('Content-Type: application/json');

// ==========================================
// MÉTODO GET - Buscar Notas
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $disciplina = $_GET['disciplina'] ?? '';
    $classe = $_GET['classe'] ?? '';
    $turma = $_GET['turma'] ?? '';
    $ano_letivo = $_GET['ano_letivo'] ?? date('Y') . '/' . (date('Y') + 1);

    if (empty($disciplina) || empty($classe) || empty($turma)) {
        echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios: disciplina, classe, turma']);
        exit;
    }

    try {
        // Buscar notas da turma para a disciplina
        $sql = "SELECT 
                    n.*,
                    a.nome as nome_aluno,
                    a.TURMA as turma,
                    a.Classe as classe
                FROM notas n
                INNER JOIN alunos a ON n.id_aluno = a.id
                WHERE n.disciplina = ? 
                AND a.Classe = ? 
                AND a.TURMA = ?
                AND n.ano_letivo = ?
                ORDER BY a.nome ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$disciplina, $classe, $turma, $ano_letivo]);
        $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Notas carregadas com sucesso',
            'notas' => $notas,
            'total' => count($notas),
            'ano_letivo' => $ano_letivo
        ]);

    } catch (PDOException $e) {
        error_log("Erro ao buscar notas: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar notas: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ==========================================
// MÉTODO POST - Salvar Notas
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pegar dados do corpo da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    $notas = $input['notas'] ?? [];
    $ano_letivo = $input['ano_letivo'] ?? date('Y') . '/' . (date('Y') + 1);

    if (empty($notas)) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma nota para salvar']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $salvos = 0;
        $erros = [];

        foreach ($notas as $nota) {
            // Validar dados obrigatórios
            if (empty($nota['id_aluno']) || empty($nota['disciplina'])) {
                $erros[] = "Dados incompletos para aluno ID: " . ($nota['id_aluno'] ?? 'desconhecido');
                continue;
            }

            // Extrair campos
            $id_aluno = $nota['id_aluno'];
            $disciplina = $nota['disciplina'];
            $turma = $nota['turma'] ?? '';
            $classe = $nota['classe'] ?? '';
            
            // Notas por trimestre
            $mac_t1 = !empty($nota['mac_t1']) ? floatval($nota['mac_t1']) : null;
            $npt_t1 = !empty($nota['npt_t1']) ? floatval($nota['npt_t1']) : null;
            $mac_t2 = !empty($nota['mac_t2']) ? floatval($nota['mac_t2']) : null;
            $npt_t2 = !empty($nota['npt_t2']) ? floatval($nota['npt_t2']) : null;
            $mac_t3 = !empty($nota['mac_t3']) ? floatval($nota['mac_t3']) : null;
            $npt_t3 = !empty($nota['npt_t3']) ? floatval($nota['npt_t3']) : null;
            $neo = !empty($nota['neo']) ? floatval($nota['neo']) : null;
            $en = !empty($nota['en']) ? floatval($nota['en']) : null;

            // Verificar se a nota já existe
            $sql_check = "SELECT id FROM notas WHERE id_aluno = ? AND disciplina = ? AND ano_letivo = ?";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$id_aluno, $disciplina, $ano_letivo]);
            $existe = $stmt_check->fetch();

            if ($existe) {
                // UPDATE - Nota já existe
                $sql = "UPDATE notas SET 
                            mac_t1 = ?, npt_t1 = ?,
                            mac_t2 = ?, npt_t2 = ?,
                            mac_t3 = ?, npt_t3 = ?,
                            neo = ?, en = ?,
                            updated_at = NOW()
                        WHERE id_aluno = ? AND disciplina = ? AND ano_letivo = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $mac_t1, $npt_t1,
                    $mac_t2, $npt_t2,
                    $mac_t3, $npt_t3,
                    $neo, $en,
                    $id_aluno, $disciplina, $ano_letivo
                ]);
            } else {
                // INSERT - Nova nota
                $sql = "INSERT INTO notas (
                            id_aluno, disciplina, turma, classe,
                            mac_t1, npt_t1,
                            mac_t2, npt_t2,
                            mac_t3, npt_t3,
                            neo, en,
                            ano_letivo, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, ?,
                            ?, ?,
                            ?, ?,
                            ?, ?,
                            ?, ?,
                            ?, NOW(), NOW()
                        )";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $id_aluno, $disciplina, $turma, $classe,
                    $mac_t1, $npt_t1,
                    $mac_t2, $npt_t2,
                    $mac_t3, $npt_t3,
                    $neo, $en,
                    $ano_letivo
                ]);
            }

            $salvos++;
        }

        // Commit da transação
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "{$salvos} notas salvas com sucesso",
            'salvos' => $salvos,
            'erros' => $erros,
            'ano_letivo' => $ano_letivo
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao salvar notas: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao salvar notas: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ==========================================
// MÉTODO DELETE - Excluir Nota
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id_nota = $input['id'] ?? $_GET['id'] ?? 0;

    if (empty($id_nota)) {
        echo json_encode(['success' => false, 'message' => 'ID da nota é obrigatório']);
        exit;
    }

    try {
        $sql = "DELETE FROM notas WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_nota]);

        echo json_encode([
            'success' => true,
            'message' => 'Nota excluída com sucesso'
        ]);

    } catch (PDOException $e) {
        error_log("Erro ao excluir nota: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao excluir nota: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ==========================================
// MÉTODO NÃO SUPORTADO
// ==========================================
echo json_encode([
    'success' => false,
    'message' => 'Método não suportado'
]);
?>