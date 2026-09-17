<?php
// ============================================
// api/pauta-exame/gerar.php - Gerar Pauta de Exame
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
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

// ===== RECEBER DADOS =====
$data = json_decode(file_get_contents('php://input'), true);
$classe = $data['classe'] ?? '';
$turma = $data['turma'] ?? '';
$sala = $data['sala'] ?? '';
$periodo = $data['periodo'] ?? 'final';
$ano_letivo = $data['ano_letivo'] ?? date('Y') . '/' . (date('Y') + 1);
$disciplinas = $data['disciplinas'] ?? [];
$incluir_en = $data['incluir_en'] ?? true;

if (empty($classe) || empty($turma) || empty($disciplinas)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

try {
    $pdo = conectarBanco();
    
    // ===== BUSCAR ALUNOS =====
    $sql = "SELECT id, nome, Sexo as sexo, Idade as idade, TURMA as turma, Classe as classe 
            FROM alunos 
            WHERE TURMA = ? AND Classe = ? AND status = 'ativo' 
            ORDER BY nome ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$turma, $classe]);
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($alunos)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum aluno encontrado']);
        exit;
    }
    
    // ===== BUSCAR NOTAS =====
    $ids = array_column($alunos, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $sql_notas = "SELECT id_aluno, disciplina, 
                  mac_t1, npt_t1, mt1, mac_t2, npt_t2, mt2, mac_t3, npt_t3, mt3,
                  neo, en, mec, mfed, mfd, classificacao
                  FROM notas_alunos 
                  WHERE id_aluno IN ($placeholders) AND turma = ? AND classe = ?";
    $params = array_merge($ids, [$turma, $classe]);
    $stmt_notas = $pdo->prepare($sql_notas);
    $stmt_notas->execute($params);
    $notas_list = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== ORGANIZAR NOTAS =====
    $notas_por_aluno = [];
    foreach ($notas_list as $n) {
        if (!isset($notas_por_aluno[$n['id_aluno']])) {
            $notas_por_aluno[$n['id_aluno']] = [];
        }
        $notas_por_aluno[$n['id_aluno']][$n['disciplina']] = $n;
    }
    
    // ===== MONTAR RESULTADO =====
    $resultado = [
        'success' => true,
        'classe' => $classe,
        'turma' => $turma,
        'sala' => $sala,
        'periodo' => $periodo,
        'ano_letivo' => $ano_letivo,
        'disciplinas' => $disciplinas,
        'alunos' => []
    ];
    
    foreach ($alunos as $aluno) {
        $notas_aluno = $notas_por_aluno[$aluno['id']] ?? [];
        $notas = [];
        
        foreach ($disciplinas as $disciplina) {
            if (isset($notas_aluno[$disciplina])) {
                $n = $notas_aluno[$disciplina];
                $notas[$disciplina] = [
                    'mt1' => $n['mt1'] ?? $n['mac_t1'] ?? 0,
                    'mt2' => $n['mt2'] ?? $n['mac_t2'] ?? 0,
                    'mt3' => $n['mt3'] ?? $n['mac_t3'] ?? 0,
                    'mfd' => $n['mfd'] ?? 0,
                    'en' => $n['en'] ?? $n['neo'] ?? 0
                ];
            } else {
                $notas[$disciplina] = [
                    'mt1' => 0,
                    'mt2' => 0,
                    'mt3' => 0,
                    'mfd' => 0,
                    'en' => 0
                ];
            }
        }
        
        // Calcular MDF Geral
        $mdfs = [];
        foreach ($disciplinas as $disciplina) {
            $mdf = $notas[$disciplina]['mfd'] ?? 0;
            if ($mdf > 0) $mdfs[] = $mdf;
        }
        $mdf_geral = !empty($mdfs) ? round(array_sum($mdfs) / count($mdfs), 1) : 0;
        
        // Calcular situação
        $situacao = 'N/A';
        if ($mdf_geral === 0) {
            $situacao = 'DESISTENTE';
        } elseif ($mdf_geral >= 10) {
            $situacao = 'APTO';
        } else {
            $situacao = 'NÃO APTO';
        }
        
        $resultado['alunos'][] = [
            'id' => $aluno['id'],
            'nome' => $aluno['nome'],
            'sexo' => $aluno['sexo'] ?? '',
            'notas' => $notas,
            'mdf_geral' => $mdf_geral,
            'situacao' => $situacao
        ];
    }
    
    echo json_encode($resultado);
    
} catch (PDOException $e) {
    error_log("Erro ao gerar pauta de exame: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao gerar pauta: ' . $e->getMessage()]);
}
?>