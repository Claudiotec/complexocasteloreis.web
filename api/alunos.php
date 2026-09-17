<?php
// ============================================
// api/alunos.php - Buscar Alunos por Classe e Turma
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

// Pegar parâmetros
$classe = $_GET['classe'] ?? '';
$turma = $_GET['turma'] ?? '';
$ano_letivo = $_GET['ano_letivo'] ?? date('Y') . '/' . (date('Y') + 1);

// Validar parâmetros
if (empty($classe) || empty($turma)) {
    echo json_encode(['success' => false, 'message' => 'Classe e turma são obrigatórios']);
    exit;
}

try {
    // Buscar alunos da turma
    $sql = "SELECT 
                id,
                nome,
                Sexo as sexo,
                Idade as idade,
                TURMA as turma,
                Classe as classe,
                status,
                data_matricula,
                N_BI as n_bi,
                Naturalidade as naturalidade,
                Municipio as municipio,
                Provincia as provincia,
                Morada as morada,
                Contacto_do_Aluno as contacto,
                Ocupacao_do_Aluno as ocupacao,
                Periodo as periodo,
                nome_pai,
                nome_mae,
                telefone_responsavel
            FROM alunos 
            WHERE Classe = ? 
            AND TURMA = ? 
            AND status = 'ativo'
            ORDER BY nome ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$classe, $turma]);
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para cada aluno, buscar notas do ano letivo atual
    foreach ($alunos as &$aluno) {
        // Buscar notas do aluno para o ano letivo
        $sql_notas = "SELECT 
                        disciplina,
                        mac_t1, npt_t1,
                        mac_t2, npt_t2,
                        mac_t3, npt_t3,
                        neo, en,
                        mfd,
                        classificacao,
                        ano_letivo
                      FROM notas 
                      WHERE id_aluno = ? 
                      AND ano_letivo = ?
                      LIMIT 1";
        
        $stmt_notas = $pdo->prepare($sql_notas);
        $stmt_notas->execute([$aluno['id'], $ano_letivo]);
        $notas = $stmt_notas->fetch(PDO::FETCH_ASSOC);
        
        if ($notas) {
            $aluno['notas'] = $notas;
        } else {
            $aluno['notas'] = null;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Alunos carregados com sucesso',
        'alunos' => $alunos,
        'total' => count($alunos),
        'ano_letivo' => $ano_letivo
    ]);

} catch (PDOException $e) {
    error_log("Erro ao buscar alunos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar alunos: ' . $e->getMessage()
    ]);
}
?>