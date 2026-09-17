<?php
// ============================================
// modules/escola/pedagogico/pautas/api/pauta.php
// API para geração de pautas - CORRIGIDO (colunas corretas)
// ============================================

// Carregar configurações
require_once '../../../../../config/database.php';
require_once '../../../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'visualizar')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Definir cabeçalhos para JSON
header('Content-Type: application/json');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido. Use POST.']);
    exit;
}

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Validar campos
$trimestre = $input['trimestre'] ?? null;
$classe = $input['classe'] ?? null;
$turma = $input['turma'] ?? null;
$disciplinasSelecionadas = $input['disciplinas'] ?? [];
$modo = $input['modo'] ?? 'professor';
$professorNome = $input['professorNome'] ?? '';

if (!$trimestre || !$classe || !$turma) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

try {
    // ============================================
    // 1. BUSCAR DISCIPLINAS DA TABELA distribuicao_professores
    // ============================================
    $disciplinasDaDistribuicao = [];
    
    // Buscar as disciplinas da distribuição para este professor, turma e classe
    $stmt = $pdo->prepare("
        SELECT d.disciplinas, d.ano_letivo, d.turma_id
        FROM distribuicao_professores d
        WHERE d.professor_nome = ? 
        AND d.turma_nome = ? 
        AND d.classe = ?
        ORDER BY d.id DESC
        LIMIT 1
    ");
    $stmt->execute([$professorNome, $turma, $classe]);
    $distribuicao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se não encontrou pelo nome, tentar pelo professor_id
    if (!$distribuicao) {
        $stmt = $pdo->prepare("
            SELECT d.disciplinas, d.ano_letivo, d.turma_id
            FROM distribuicao_professores d
            WHERE d.professor_id = ? 
            AND d.turma_nome = ? 
            AND d.classe = ?
            ORDER BY d.id DESC
            LIMIT 1
        ");
        $stmt->execute([$usuario_id, $turma, $classe]);
        $distribuicao = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($distribuicao && !empty($distribuicao['disciplinas'])) {
        // Separar as disciplinas por vírgula
        $disciplinasDaDistribuicao = array_map('trim', explode(',', $distribuicao['disciplinas']));
    }
    
    // Se não encontrou na distribuição, buscar da tabela professor_disciplina
    if (empty($disciplinasDaDistribuicao)) {
        $stmt = $pdo->prepare("
            SELECT d.nome 
            FROM professor_disciplina pd
            JOIN disciplinas d ON pd.disciplina_id = d.id
            WHERE pd.professor_id = ?
        ");
        $stmt->execute([$usuario_id]);
        $disciplinasDaDistribuicao = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Se ainda está vazio, usar fallback
    if (empty($disciplinasDaDistribuicao)) {
        $disciplinasDaDistribuicao = ['Matemática', 'Português', 'História', 'Geografia', 'Ciências'];
    }
    
    // Usar as disciplinas selecionadas ou todas da distribuição
    $disciplinasParaBuscar = !empty($disciplinasSelecionadas) 
        ? $disciplinasSelecionadas 
        : $disciplinasDaDistribuicao;
    
    if (empty($disciplinasParaBuscar)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhuma disciplina encontrada para este professor'
        ]);
        exit;
    }
    
    // ============================================
    // 2. BUSCAR ALUNOS DIRETAMENTE DA TABELA alunos
    //    COLUNAS CORRETAS: Classe e TURMA
    // ============================================
    
    // Primeiro, verificar a estrutura da tabela para debug
    $debug = [];
    
    // Buscar alunos pela Classe e TURMA (com os nomes corretos das colunas)
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.nome,
            a.genero as sexo,
            a.Classe,
            a.TURMA,
            a.status,
            a.data_nascimento
        FROM alunos a
        WHERE a.Classe = ? 
        AND a.TURMA = ?
        AND a.status = 'ativo'
        ORDER BY a.nome
    ");
    $stmt->execute([$classe, $turma]);
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $debug['busca_por_classe_turma'] = count($alunos);
    
    // Se não encontrou, tentar apenas Classe
    if (empty($alunos)) {
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.nome,
                a.genero as sexo,
                a.Classe,
                a.TURMA,
                a.status,
                a.data_nascimento
            FROM alunos a
            WHERE a.Classe = ? 
            AND a.status = 'ativo'
            ORDER BY a.nome
        ");
        $stmt->execute([$classe]);
        $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug['busca_por_classe'] = count($alunos);
    }
    
    // Se ainda não encontrou, buscar apenas por status ativo
    if (empty($alunos)) {
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.nome,
                a.genero as sexo,
                a.Classe,
                a.TURMA,
                a.status,
                a.data_nascimento
            FROM alunos a
            WHERE a.status = 'ativo'
            ORDER BY a.nome
            LIMIT 20
        ");
        $stmt->execute();
        $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug['busca_todos_ativos'] = count($alunos);
    }
    
    if (empty($alunos)) {
        echo json_encode([
            'success' => true,
            'trimestre' => $trimestre,
            'classe' => $classe,
            'turma' => $turma,
            'alunos' => [],
            'message' => 'Nenhum aluno encontrado na turma',
            'debug' => $debug
        ]);
        exit;
    }
    
    // ============================================
    // 3. BUSCAR NOTAS DOS ALUNOS
    // ============================================
    $alunosComNotas = [];
    
    foreach ($alunos as $aluno) {
        $notas = [];
        
        foreach ($disciplinasParaBuscar as $disciplina) {
            // Buscar nota do aluno para esta disciplina e trimestre
            $stmt = $pdo->prepare("
                SELECT valor 
                FROM notas 
                WHERE aluno_id = ? 
                AND disciplina = ? 
                AND trimestre = ?
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$aluno['id'], $disciplina, $trimestre]);
            $nota = $stmt->fetchColumn();
            
            $notas[$disciplina] = $nota !== false ? floatval($nota) : null;
        }
        
        $alunosComNotas[] = [
            'nome' => $aluno['nome'],
            'sexo' => $aluno['sexo'] ?? 'N/I',
            'classe' => $aluno['Classe'] ?? '',
            'turma' => $aluno['TURMA'] ?? '',
            'notas' => $notas
        ];
    }
    
    // ============================================
    // 4. RETORNAR DADOS
    // ============================================
    echo json_encode([
        'success' => true,
        'trimestre' => $trimestre,
        'classe' => $classe,
        'turma' => $turma,
        'disciplinas' => $disciplinasParaBuscar,
        'alunos' => $alunosComNotas,
        'distribuicao' => $distribuicao ?? null,
        'total_alunos' => count($alunosComNotas),
        'debug' => $debug
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar dados: ' . $e->getMessage(),
        'debug' => $e->getTrace()
    ]);
}