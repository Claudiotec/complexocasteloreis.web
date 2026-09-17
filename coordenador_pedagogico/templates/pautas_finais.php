<?php
// ============================================
// pautas_finais.php - Pautas Finais (VERSÃO COMPLETA)
// ============================================

// ===== 1. CARREGAR CONFIGURAÇÃO =====
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $root_path . '/config/database.php';
require_once $root_path . '/config/app_modes.php';

// ===== 2. SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== 3. VERIFICAR LOGIN =====
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// ===== 4. VERIFICAR PERFIL =====
$perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
$permitidos = ['professor', 'docente', 'coordenador', 'diretor', 'admin', 'pedagogo', 'coordenador_pedagogico'];
if (!in_array($perfil, $permitidos)) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// ===== 5. DADOS DO USUÁRIO =====
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Professor';
$usuario_email = $_SESSION['usuario_email'] ?? '';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$escola_nome = $_SESSION['escola_nome'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';

// ===== 6. GARANTIR CONEXÃO =====
if (!isset($pdo) || !$pdo) {
    $pdo = conectarBanco();
}

// ===== 7. BUSCAR DADOS DA EMPRESA =====
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? $escola_nome;
$telefoneEmpresa = $empresa['telefone'] ?? '';
$emailEmpresa = $empresa['email'] ?? '';
$cnpjEmpresa = $empresa['cnpj'] ?? '';
$enderecoEmpresa = $empresa['endereco'] ?? '';
$numeroEmpresa = $empresa['numero'] ?? '';
$bairroEmpresa = $empresa['bairro'] ?? '';
$cidadeEmpresa = $empresa['cidade'] ?? '';
$estadoEmpresa = $empresa['estado'] ?? '';

$enderecoCompleto = $enderecoEmpresa;
if ($numeroEmpresa) $enderecoCompleto .= ', ' . $numeroEmpresa;
if ($bairroEmpresa) $enderecoCompleto .= ', ' . $bairroEmpresa;
if ($cidadeEmpresa) $enderecoCompleto .= ', ' . $cidadeEmpresa;
if ($estadoEmpresa) $enderecoCompleto .= ' - ' . $estadoEmpresa;

// ===== 8. BUSCAR DADOS DO PROFESSOR =====
$professor_num_agente = null;
$professor_nome = '';
$isCoordenador = in_array($perfil, ['coordenador', 'coordenador_pedagogico', 'diretor', 'admin']);

try {
    $stmt = $pdo->prepare("SELECT num_agente, nome FROM funcionarios WHERE email = ? AND status = 'ativo' LIMIT 1");
    $stmt->execute([$usuario_email]);
    $professor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($professor) {
        $professor_num_agente = $professor['num_agente'];
        $professor_nome = $professor['nome'];
    }
} catch (Exception $e) {
    error_log("Erro ao buscar professor: " . $e->getMessage());
}

// ===== 9. BUSCAR DADOS - COORDENADOR VÊ TUDO =====
$classes = [];
$turmas = [];
$disciplinas = [];

try {
    if ($isCoordenador) {
        // ============================================
        // COORDENADOR: BUSCA TODAS AS TURMAS E DISCIPLINAS
        // ============================================
        
        // Busca todas as turmas
        $stmt = $pdo->query("SELECT DISTINCT classe FROM turmas ORDER BY classe");
        $classesResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($classesResult as $c) {
            if (!empty($c['classe'])) {
                $classes[] = trim($c['classe']);
            }
        }
        
        // Busca todas as turmas
        $stmt = $pdo->query("SELECT DISTINCT nome FROM turmas ORDER BY nome");
        $turmasResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($turmasResult as $t) {
            if (!empty($t['nome'])) {
                $turmas[] = trim($t['nome']);
            }
        }
        
        // Busca todas as disciplinas das turmas
        $stmt = $pdo->query("SELECT DISTINCT disciplinas FROM turmas WHERE disciplinas IS NOT NULL AND disciplinas != ''");
        $discsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $disciplinasSet = [];
        foreach ($discsResult as $d) {
            if (!empty($d['disciplinas'])) {
                $discs = array_map('trim', explode(',', $d['disciplinas']));
                foreach ($discs as $disc) {
                    if (!empty($disc) && !in_array($disc, $disciplinasSet)) {
                        $disciplinasSet[] = $disc;
                    }
                }
            }
        }
        $disciplinas = $disciplinasSet;
        
        // Se não encontrou disciplinas nas turmas, busca da tabela notas_alunos
        if (empty($disciplinas)) {
            $stmt = $pdo->query("SELECT DISTINCT disciplina FROM notas_alunos WHERE disciplina IS NOT NULL AND disciplina != ''");
            $notasDiscs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($notasDiscs as $d) {
                if (!empty($d['disciplina']) && !in_array($d['disciplina'], $disciplinas)) {
                    $disciplinas[] = $d['disciplina'];
                }
            }
        }
        
    } elseif ($professor_num_agente) {
        // ============================================
        // PROFESSOR: BUSCA APENAS SUAS TURMAS
        // ============================================
        $sql = "SELECT * FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$professor_num_agente]);
        $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($distribuicoes) && !empty($professor_nome)) {
            $sql = "SELECT * FROM destribuicao_professores WHERE professor_nome LIKE ? AND tipo = 'PROFESSOR'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['%' . $professor_nome . '%']);
            $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
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
    
} catch (Exception $e) {
    error_log("Erro ao buscar distribuições: " . $e->getMessage());
}

// Se ainda estiver vazio, usa valores padrão
if (empty($classes)) {
    // Busca todas as classes disponíveis na tabela alunos
    try {
        $stmt = $pdo->query("SELECT DISTINCT Classe FROM alunos WHERE Classe IS NOT NULL AND Classe != '' ORDER BY Classe");
        $classesResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($classesResult as $c) {
            if (!empty($c['Classe'])) {
                $classes[] = trim($c['Classe']);
            }
        }
    } catch (Exception $e) {}
}

if (empty($classes)) $classes = ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª', '7ª', '8ª', '9ª', '10ª', '11ª', '12ª'];

if (empty($turmas)) {
    // Busca todas as turmas disponíveis na tabela alunos
    try {
        $stmt = $pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA");
        $turmasResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($turmasResult as $t) {
            if (!empty($t['TURMA'])) {
                $turmas[] = trim($t['TURMA']);
            }
        }
    } catch (Exception $e) {}
}

if (empty($turmas)) $turmas = ['A', 'B', 'C', 'D', 'E'];

if (empty($disciplinas)) {
    // Busca todas as disciplinas disponíveis na tabela notas_alunos
    try {
        $stmt = $pdo->query("SELECT DISTINCT disciplina FROM notas_alunos WHERE disciplina IS NOT NULL AND disciplina != '' ORDER BY disciplina");
        $discsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($discsResult as $d) {
            if (!empty($d['disciplina'])) {
                $disciplinas[] = $d['disciplina'];
            }
        }
    } catch (Exception $e) {}
}

if (empty($disciplinas)) $disciplinas = ['Matemática', 'Língua Portuguesa', 'C. Natureza', 'Ed. Física', 'História', 'Geografia', 'Inglês'];

// ===== 10. FUNÇÃO PARA BUSCAR NOTAS =====
function buscarNotasAlunos($pdo, $classe, $turma, $disciplinas, $periodo) {
    $resultado = [
        'success' => true,
        'alunos' => [],
        'classe' => $classe,
        'turma' => $turma,
        'periodo' => $periodo
    ];
    
    try {
        $sql = "SELECT id, nome, Sexo as sexo, Idade as idade, TURMA as turma, Classe as classe 
                FROM alunos 
                WHERE TURMA = ? AND Classe = ? AND status = 'ativo' 
                ORDER BY nome ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$turma, $classe]);
        $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($alunos)) {
            return ['success' => false, 'message' => 'Nenhum aluno encontrado'];
        }
        
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
        
        $notas_por_aluno = [];
        foreach ($notas_list as $n) {
            if (!isset($notas_por_aluno[$n['id_aluno']])) {
                $notas_por_aluno[$n['id_aluno']] = [];
            }
            $notas_por_aluno[$n['id_aluno']][$n['disciplina']] = $n;
        }
        
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
            
            $resultado['alunos'][] = [
                'id' => $aluno['id'],
                'nome' => $aluno['nome'],
                'sexo' => $aluno['sexo'] ?? '',
                'notas' => $notas
            ];
        }
        
    } catch (Exception $e) {
        error_log("Erro ao buscar notas: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
    
    return $resultado;
}

// ===== 11. PROCESSAR REQUISIÇÕES AJAX =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'gerar_pauta') {
        $data = json_decode(file_get_contents('php://input'), true);
        $classe = $data['classe'] ?? '';
        $turma = $data['turma'] ?? '';
        $disciplinas = $data['disciplinas'] ?? [];
        $periodo = $data['periodo'] ?? 'final';
        $sala = $data['sala'] ?? '';
        $ano_letivo = $data['ano_letivo'] ?? date('Y') . '/' . (date('Y') + 1);
        
        if (empty($classe) || empty($turma) || empty($disciplinas)) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
            exit;
        }
        
        $resultado = buscarNotasAlunos($pdo, $classe, $turma, $disciplinas, $periodo);
        $resultado['sala'] = $sala;
        $resultado['ano_letivo'] = $ano_letivo;
        echo json_encode($resultado);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pautas Finais - <?= htmlspecialchars($nomeEmpresa) ?></title>
    <!-- CSS LOCAL - sem dependências externas -->
    <style>
        /* ===== RESET E BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; }
        
        /* ===== HEADER ===== */
        .header {
            background: linear-gradient(135deg, #1a2332, #2c3e50);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header h1 { font-size: 16px; margin: 0; }
        .header h1 span { color: #c9a84c; }
        .header .badge {
            background: #17a2b8;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            display: inline-block;
        }
        .header .badge i { margin-right: 5px; }
        .header .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-light { background: #f8f9fa; color: #212529; }
        .btn-light:hover { background: #e2e6ea; }
        
        .container { padding: 25px; max-width: 1600px; margin: 0 auto; }
        
        /* ===== CARDS ===== */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .filters-card .row { display: flex; flex-wrap: wrap; gap: 15px; }
        .filters-card .col { flex: 1; min-width: 180px; }
        .filters-card label {
            font-size: 14px;
            font-weight: 600;
            display: block;
            margin-bottom: 4px;
        }
        .filters-card select, .filters-card input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            transition: border-color 0.3s;
        }
        .filters-card select:focus, .filters-card input:focus {
            border-color: #c9a84c;
            outline: none;
        }
        
        .form-label { font-weight: 600; font-size: 14px; }
        .mt-3 { margin-top: 15px; }
        .mt-4 { margin-top: 20px; }
        .mb-3 { margin-bottom: 15px; }
        .me-2 { margin-right: 10px; }
        .ms-2 { margin-left: 10px; }
        .d-block { display: block; }
        .text-muted { color: #6c757d; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: 700; }
        
        /* ===== DISCIPLINAS ===== */
        .disciplinas-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
            margin-top: 10px;
        }
        .disciplina-item {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 20px;
            padding: 8px 16px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            cursor: grab;
            font-size: 14px;
            user-select: none;
        }
        .disciplina-item:hover { 
            background: #e3f2fd; 
            border-color: #3498db; 
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(52,152,219,0.2);
        }
        .disciplina-item input[type="checkbox"] { 
            width: 18px; 
            height: 18px; 
            cursor: pointer; 
        }
        .disciplina-item .drag-handle {
            cursor: grab;
            color: #6c757d;
            font-size: 16px;
            padding: 0 4px;
            transition: color 0.2s;
        }
        .disciplina-item .drag-handle:hover { color: #3498db; }
        .disciplina-item.dragging {
            opacity: 0.4;
            border-color: #3498db;
            background: #e3f2fd;
        }
        .disciplina-item.drag-over {
            border: 2px dashed #3498db;
            background: #e3f2fd;
            transform: scale(1.02);
        }
        .disciplina-item.selected {
            border-color: #3498db;
            background: #dbeafe;
        }
        
        /* ===== BOTÕES ===== */
        .btn {
            padding: 8px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }
        .btn-sm { font-size: 13px; padding: 6px 14px; }
        .btn-lg { font-size: 15px; padding: 10px 25px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-success:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0069d9; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-outline-secondary { background: transparent; color: #6c757d; border: 2px solid #6c757d; }
        .btn-outline-secondary:hover { background: #6c757d; color: white; }
        .btn-print { background: #6c757d; color: white; }
        .btn-print:hover { background: #5a6268; }
        .btn-excel { background: #1d6f42; color: white; }
        .btn-excel:hover { background: #165a34; }
        .btn-arredondar { background: #fd7e14; color: white; }
        .btn-arredondar:hover { background: #e06b0a; }
        .btn-insignia { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; cursor: pointer; transition: background 0.2s; }
        .btn-insignia:hover { background: #495057; }
        
        .px-4 { padding-left: 25px; padding-right: 25px; }
        .gap-3 { gap: 15px; }
        
        .ordenacao-buttons { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        
        /* ===== ALERTAS ===== */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            font-size: 14px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        /* ===== LOADING ===== */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #6c757d;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* ===== PAUTA ===== */
        .pauta-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .pauta-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 20px;
        }
        .pauta-header .insignia-area {
            margin-bottom: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            min-height: 100px;
        }
        .pauta-header .insignia-img {
            max-width: 100px;
            max-height: 100px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .pauta-header .insignia-img:hover { transform: scale(1.05); }
        .pauta-header .republika, .pauta-header .ministerio, .pauta-header .titulo {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
        }
        .pauta-header .republika:hover, .pauta-header .ministerio:hover, .pauta-header .titulo:hover {
            background: #f0f0f0;
            display: inline-block;
            padding: 0 10px;
            border-radius: 5px;
        }
        .pauta-header .escola-info, .pauta-header .ciclo-info {
            font-size: 14px;
            margin: 5px 0;
            cursor: pointer;
            display: inline-block;
            background: #f8f9fa;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .pauta-header .escola-info:hover, .pauta-header .ciclo-info:hover {
            background: #e9ecef;
        }
        .pauta-header .detalhes-info {
            font-size: 14px;
            margin: 3px 0;
            cursor: pointer;
            display: inline-block;
            padding: 2px 8px;
            border-radius: 15px;
        }
        .pauta-header .detalhes-info:hover { background: #e9ecef; }
        .edit-input {
            width: 300px;
            padding: 5px 10px;
            border: 1px solid #3498db;
            border-radius: 5px;
            text-align: center;
            font-size: 14px;
        }
        
        /* ===== TABELA ===== */
        .table-responsive { overflow-x: auto; }
        .pauta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 1200px;
        }
        .pauta-table th {
            background: #2c3e50;
            color: white;
            padding: 8px 6px;
            border: 1px solid #ddd;
            text-align: center;
            font-weight: 600;
            white-space: nowrap;
            font-size: 14px;
        }
        .pauta-table td {
            border: 1px solid #ddd;
            padding: 6px 4px;
            text-align: center;
            font-size: 14px;
        }
        .pauta-table td:first-child { font-weight: 600; width: 35px; }
        .pauta-table td:nth-child(2) { text-align: left; min-width: 180px; }
        .subcoluna {
            font-size: 12px;
            font-weight: normal;
            background: #34495e;
            color: white;
        }
        
        .situacao-aprovado { background: #d4edda !important; color: #155724; font-weight: bold; }
        .situacao-reprovado { background: #f8d7da !important; color: #721c24; font-weight: bold; }
        .situacao-desistente { background: #fff3cd !important; color: #856404; font-weight: bold; }
        .positiva { color: #000000 !important; font-weight: bold; }
        .negativa { color: #dc3545; font-weight: bold; }
        .media-destaque { font-weight: bold; color: #2c3e50; font-size: 14px; }
        
        /* ===== STATS ===== */
        .stats-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        .stats-card h6 { font-size: 14px; font-weight: 700; }
        .stats-card p { font-size: 14px; margin: 5px 0; }
        .progress-bar-custom {
            height: 10px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-fill { height: 100%; background: #28a745; }
        
        /* ===== ASSINATURA ===== */
        .assinatura-area {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            text-align: center;
            flex-wrap: wrap;
        }
        .assinatura-item { flex: 1; min-width: 150px; }
        .assinatura-item small { font-size: 14px; }
        .linha-assinatura {
            border-top: 1px solid #333;
            margin-top: 30px;
            padding-top: 5px;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* ===== LEGENDA ===== */
        .legenda {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }
        .legenda .row { display: flex; flex-wrap: wrap; gap: 10px; font-size: 14px; }
        .legenda .col { flex: 1; min-width: 150px; }
        
        .badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 13px;
            display: inline-block;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #212529; }
        .badge-secondary { background: #6c757d; color: white; }
        .badge-info { background: #17a2b8; color: white; }
        
        /* ===== ACTION BAR ===== */
        .action-bar { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        
        .row.mt-4 { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
        .col-md-6 { flex: 0 0 calc(50% - 10px); min-width: 250px; }
        
        /* ===== UTILITÁRIOS ===== */
        .mt-2 { margin-top: 10px; }
        .mt-3 { margin-top: 15px; }
        .mt-4 { margin-top: 20px; }
        .mt-5 { margin-top: 25px; }
        .mb-2 { margin-bottom: 10px; }
        .mb-3 { margin-bottom: 15px; }
        .me-2 { margin-right: 10px; }
        .ms-2 { margin-left: 10px; }
        .text-muted { color: #6c757d; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .small { font-size: 13px; }
        .fw-bold { font-weight: 700; }
        .d-block { display: block; }
        .d-none { display: none; }
        .w-100 { width: 100%; }
        
        .form-check-input { width: 18px; height: 18px; }
        .form-check-label { cursor: pointer; }
        
        /* ===== INFO ADICIONAL PARA COORDENADOR ===== */
        .info-badge {
            background: #c9a84c;
            color: #1a2332;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; text-align: center; padding: 10px; }
            .filters-card .row { flex-direction: column; }
            .filters-card .col { min-width: 100%; }
            .pauta-table { font-size: 12px; min-width: 800px; }
            .pauta-table th, .pauta-table td { font-size: 12px; padding: 4px 3px; }
            .col-md-6 { flex: 0 0 100%; }
            .assinatura-area { flex-direction: column; gap: 20px; }
        }
        
        @media print {
            .header, .filters-card, .action-bar, .btn-print, .btn-excel, .btn-arredondar,
            .btn-insignia, .insignia-area button, .stats-card, .legenda, 
            .assinatura-area, .text-center.text-muted {
                display: none !important;
            }
            .pauta-container {
                padding: 0;
                margin: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .pauta-table { font-size: 12px; min-width: 100%; }
            .pauta-table th { padding: 4px 3px; font-size: 12px; }
            .pauta-table td { padding: 4px 3px; font-size: 12px; }
            .subcoluna { font-size: 10px; }
            .pauta-header { margin-bottom: 10px; padding-bottom: 10px; }
            .pauta-header .republika, .pauta-header .ministerio, .pauta-header .titulo { font-size: 14px; }
            .pauta-header .escola-info, .pauta-header .ciclo-info { font-size: 12px; }
            .pauta-header .detalhes-info { font-size: 12px; }
        }
    </style>
</head>
<body>

    <!-- ===== HEADER ===== -->
    <div class="header">
        <h1><span>📋</span> <span>Pautas Finais</span></h1>
        <div>
            <span class="badge"><span>👤</span> <?= htmlspecialchars($usuario_nome) ?></span>
            <?php if ($isCoordenador): ?>
                <span class="info-badge">📋 Coordenador - Acesso Total</span>
            <?php else: ?>
                <span style="font-size:13px;color:#c9a84c;margin-right:10px;">🆔 Nº Agente: <?= $professor_num_agente ?? 'N/A' ?></span>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-light" style="font-size:13px;"><span>←</span> Voltar</a>
        </div>
    </div>

    <div class="container">
        <div id="alertMessage" class="alert" style="display: none;"></div>
        
        <div class="filters-card">
            <?php if ($isCoordenador): ?>
            <div style="background: #e8f4fd; padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span style="font-size: 16px;">📋</span>
                <span style="font-weight: 600;">Modo Coordenador:</span>
                <span style="color: #0c5460;">Você tem acesso a TODAS as turmas, classes e disciplinas do sistema.</span>
                <span style="background: #17a2b8; color: white; padding: 2px 12px; border-radius: 20px; font-size: 12px;">Acesso Total</span>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col">
                    <label class="form-label fw-bold"><span>📅</span> Ano Letivo:</label>
                    <select id="anoLetivo" class="form-select" onchange="atualizarAnoLetivo()">
                        <option value="2023/2024">2023/2024</option>
                        <option value="2024/2025">2024/2025</option>
                        <option value="2025/2026" selected>2025/2026</option>
                        <option value="2026/2027">2026/2027</option>
                        <option value="2027/2028">2027/2028</option>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label fw-bold"><span>🎓</span> Classe:</label>
                    <select id="classe" class="form-select" onchange="carregarTurmas()">
                        <option value="">Selecione...</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?>ª Classe</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label fw-bold"><span>👥</span> Turma:</label>
                    <select id="turma" class="form-select">
                        <option value="">Selecione a classe primeiro...</option>
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>">Turma <?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label fw-bold"><span>⏳</span> Período:</label>
                    <select id="periodo" class="form-select">
                        <option value="final">Final (Anual)</option>
                        <option value="1">1º Trimestre</option>
                        <option value="2">2º Trimestre</option>
                        <option value="3">3º Trimestre</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-3">
                <label class="form-label fw-bold"><span>📚</span> Disciplinas:</label>
                <div class="ordenacao-buttons">
                    <button class="btn btn-sm btn-info" onclick="moverDisciplinaCima()"><span>↑</span> Mover Cima</button>
                    <button class="btn btn-sm btn-info" onclick="moverDisciplinaBaixo()"><span>↓</span> Mover Baixo</button>
                    <button class="btn btn-sm btn-warning" onclick="ordenarDisciplinasAZ()"><span>🔤</span> Ordenar A-Z</button>
                    <button class="btn btn-sm btn-primary" onclick="selecionarTodas()"><span>✓</span> Selecionar Todas</button>
                    <button class="btn btn-sm btn-secondary" onclick="limparTodas()"><span>✕</span> Limpar</button>
                </div>
                <div id="disciplinasList" class="disciplinas-list">
                    <div class="text-muted"><span>⏳</span> Carregando disciplinas...</div>
                </div>
                <small class="text-muted mt-2 d-block"><span>↕</span> Arraste as disciplinas para reorganizar ou use os botões</small>
            </div>
            
            <div class="mt-4">
                <button id="btnGerar" class="btn btn-success btn-lg px-4" onclick="gerarPauta()" disabled>
                    <span>📊</span> Gerar Pauta
                </button>
                <button class="btn btn-outline-secondary ms-2" onclick="carregarDisciplinas()">
                    <span>🔄</span> Recarregar
                </button>
            </div>
        </div>

        <div id="pautaResult" style="display: none;"></div>
    </div>

    <script>
    // ============================================================
    // VARIÁVEIS GLOBAIS
    // ============================================================
    let disciplinasSelecionadas = [];
    let todasDisciplinas = [];
    let currentPautaData = null;
    let valoresArredondados = false;
    let insigniaUrl = '';
    let escolaNome = '<?= htmlspecialchars($nomeEmpresa) ?>';
    let disciplinaSelecionadaIndex = -1;
    let isCoordenador = <?= $isCoordenador ? 'true' : 'false' ?>;
    
    let headerTexts = {
        republica: 'REPÚBLICA DE ANGOLA',
        ministerio: 'MINISTÉRIO DA EDUCAÇÃO',
        titulo: 'PAUTA FINAL'
    };
    
    let comunaTexto = 'ÍCOLO E BENGO';
    let municipioTexto = 'ÍCOLO E BENGO';
    let provinciaTexto = 'LUANDA';
    let numeroPauta = '001';
    let anoLectivo = '2025/2026';

    // ============================================================
    // FUNÇÕES AUXILIARES
    // ============================================================
    
    function mostrarMensagem(texto, tipo) {
        const alertDiv = document.getElementById('alertMessage');
        if (!alertDiv) {
            console.log(`[${tipo}] ${texto}`);
            return;
        }
        alertDiv.className = `alert alert-${tipo}`;
        alertDiv.innerHTML = `<span>${tipo === 'success' ? '✅' : '⚠️'}</span> ${texto}`;
        alertDiv.style.display = 'block';
        setTimeout(() => alertDiv.style.display = 'none', 5000);
    }

    function formatarNota(valor) {
        if (valor === '-' || valor === null || valor === undefined || valor === '') return '-';
        const num = parseFloat(valor);
        if (isNaN(num)) return '-';
        if (valoresArredondados) {
            return Math.round(num).toString();
        }
        if (Number.isInteger(num)) {
            return num.toString();
        }
        return num.toFixed(1);
    }

    // ============================================================
    // FUNÇÕES DE DRAG AND DROP
    // ============================================================
    
    let dragSourceIndex = null;

    function handleDragStart(e) {
        const div = e.target.closest('.disciplina-item');
        if (div) {
            dragSourceIndex = parseInt(div.getAttribute('data-index'));
            e.dataTransfer.effectAllowed = 'move';
            div.classList.add('dragging');
        }
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const div = e.target.closest('.disciplina-item');
        if (div && dragSourceIndex !== null) {
            div.classList.add('drag-over');
        }
    }

    function handleDragLeave(e) {
        const div = e.target.closest('.disciplina-item');
        if (div) {
            div.classList.remove('drag-over');
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        const div = e.target.closest('.disciplina-item');
        if (div && dragSourceIndex !== null) {
            const targetIndex = parseInt(div.getAttribute('data-index'));
            if (dragSourceIndex !== targetIndex) {
                moverDisciplina(dragSourceIndex, targetIndex);
            }
            div.classList.remove('drag-over');
        }
        const draggingDiv = document.querySelector('.disciplina-item.dragging');
        if (draggingDiv) draggingDiv.classList.remove('dragging');
        dragSourceIndex = null;
    }

    function moverDisciplina(from, to) {
        if (from < 0 || to < 0 || from >= todasDisciplinas.length || to >= todasDisciplinas.length) return;
        
        const item = todasDisciplinas[from];
        todasDisciplinas.splice(from, 1);
        todasDisciplinas.splice(to, 0, item);
        
        const selecionadasAntigas = [...disciplinasSelecionadas];
        disciplinasSelecionadas = [];
        todasDisciplinas.forEach(disc => {
            if (selecionadasAntigas.includes(disc)) {
                disciplinasSelecionadas.push(disc);
            }
        });
        
        atualizarListaDisciplinas();
        
        if (currentPautaData) {
            currentPautaData.disciplinas = [...todasDisciplinas];
            exibirPauta(currentPautaData);
        }
        
        mostrarMensagem('Disciplina movida', 'success');
    }

    function selecionarDisciplina(index) {
        disciplinaSelecionadaIndex = index;
        atualizarListaDisciplinas();
    }

    function moverDisciplinaCima() {
        if (disciplinaSelecionadaIndex > 0) {
            moverDisciplina(disciplinaSelecionadaIndex, disciplinaSelecionadaIndex - 1);
            disciplinaSelecionadaIndex--;
        } else if (disciplinaSelecionadaIndex === -1) {
            mostrarMensagem('Clique no ícone ↕ de uma disciplina para selecioná-la primeiro', 'warning');
        } else {
            mostrarMensagem('Já está no topo', 'info');
        }
    }

    function moverDisciplinaBaixo() {
        if (disciplinaSelecionadaIndex >= 0 && disciplinaSelecionadaIndex < todasDisciplinas.length - 1) {
            moverDisciplina(disciplinaSelecionadaIndex, disciplinaSelecionadaIndex + 1);
            disciplinaSelecionadaIndex++;
        } else if (disciplinaSelecionadaIndex === -1) {
            mostrarMensagem('Clique no ícone ↕ de uma disciplina para selecioná-la primeiro', 'warning');
        } else {
            mostrarMensagem('Já está no fim', 'info');
        }
    }

    function ordenarDisciplinasAZ() {
        const selecionadas = [...disciplinasSelecionadas];
        todasDisciplinas.sort((a, b) => a.localeCompare(b));
        
        disciplinasSelecionadas = [];
        todasDisciplinas.forEach(disc => {
            if (selecionadas.includes(disc)) {
                disciplinasSelecionadas.push(disc);
            }
        });
        
        atualizarListaDisciplinas();
        disciplinaSelecionadaIndex = -1;
        
        if (currentPautaData) {
            currentPautaData.disciplinas = [...todasDisciplinas];
            exibirPauta(currentPautaData);
        }
        
        mostrarMensagem('Disciplinas ordenadas A-Z', 'success');
    }

    // ============================================================
    // FUNÇÕES DE DISCIPLINAS
    // ============================================================
    
    function selecionarTodas() {
        document.querySelectorAll('#disciplinasList input[type="checkbox"]').forEach(cb => cb.checked = true);
        atualizarSelecionadas();
    }

    function limparTodas() {
        document.querySelectorAll('#disciplinasList input[type="checkbox"]').forEach(cb => cb.checked = false);
        atualizarSelecionadas();
    }

    function atualizarSelecionadas() {
        disciplinasSelecionadas = Array.from(document.querySelectorAll('#disciplinasList input[type="checkbox"]:checked'))
            .map(cb => cb.value);
        document.getElementById('btnGerar').disabled = disciplinasSelecionadas.length === 0;
    }

    function atualizarListaDisciplinas() {
        const container = document.getElementById('disciplinasList');
        if (!container) return;
        
        container.innerHTML = '';
        todasDisciplinas.forEach((disc, idx) => {
            const div = document.createElement('div');
            div.className = 'disciplina-item';
            div.setAttribute('data-index', idx);
            div.setAttribute('data-disciplina', disc);
            div.draggable = true;
            
            const isChecked = disciplinasSelecionadas.includes(disc) ? 'checked' : '';
            const isSelected = disciplinaSelecionadaIndex === idx ? 'selected' : '';
            
            div.innerHTML = `
                <input type="checkbox" class="form-check-input" value="${disc.replace(/"/g, '&quot;')}" 
                       id="disc_${disc.replace(/\s/g, '_')}" ${isChecked}>
                <label class="form-check-label" for="disc_${disc.replace(/\s/g, '_')}" style="font-size:14px;cursor:pointer;">${disc}</label>
                <span class="drag-handle" onclick="selecionarDisciplina(${idx})"><span>↕</span></span>
            `;
            
            if (isSelected) {
                div.style.borderColor = '#3498db';
                div.style.background = '#dbeafe';
            }
            
            div.addEventListener('dragstart', handleDragStart);
            div.addEventListener('dragover', handleDragOver);
            div.addEventListener('dragleave', handleDragLeave);
            div.addEventListener('drop', handleDrop);
            
            container.appendChild(div);
        });
        
        document.querySelectorAll('#disciplinasList input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', atualizarSelecionadas);
        });
    }

    // ============================================================
    // FUNÇÕES DE CARREGAMENTO
    // ============================================================
    
    function carregarTurmas() {
        const classeSelect = document.getElementById('classe');
        const turmaSelect = document.getElementById('turma');
        const classe = classeSelect.value;
        
        if (!classe) {
            turmaSelect.innerHTML = '<option value="">Selecione a classe primeiro...</option>';
            return;
        }
        
        turmaSelect.innerHTML = '<option value="">Carregando turmas...</option>';
        
        const turmas = [
            <?php foreach ($turmas as $t): ?>
                '<?= addslashes($t) ?>',
            <?php endforeach; ?>
        ];
        
        turmaSelect.innerHTML = '<option value="">Selecione...</option>';
        turmas.forEach(t => {
            turmaSelect.innerHTML += `<option value="${t}">Turma ${t}</option>`;
        });
        
        document.getElementById('btnGerar').disabled = false;
    }

    function carregarDisciplinas() {
        const container = document.getElementById('disciplinasList');
        const btn = document.getElementById('btnGerar');
        
        const disciplinas = [
            <?php foreach ($disciplinas as $d): ?>
                '<?= addslashes($d) ?>',
            <?php endforeach; ?>
        ];
        
        if (disciplinas.length > 0) {
            todasDisciplinas = disciplinas;
        } else {
            todasDisciplinas = [
                'L. PORTUGUESA', 'MATEMÁTICA', 'FÍSICA', 'QUÍMICA', 'BIOLOGIA',
                'HISTÓRIA', 'GEOGRAFIA', 'INGLÊS', 'ED. FÍSICA', 'E.M.C', 'E.V.P',
                'FILOSOFIA', 'ED. MANUAL PLÁSTICA', 'ED. MUSICAL', 'C. NATUREZA', 'ED. LABORAL'
            ];
        }
        
        disciplinasSelecionadas = [...todasDisciplinas];
        atualizarListaDisciplinas();
        btn.disabled = false;
        mostrarMensagem('✅ ' + todasDisciplinas.length + ' disciplinas carregadas', 'success');
    }

    function atualizarAnoLetivo() {
        const select = document.getElementById('anoLetivo');
        anoLectivo = select.value;
        mostrarMensagem('📅 Ano letivo alterado para ' + anoLectivo, 'info');
    }

    // ============================================================
    // FUNÇÕES DE EDIÇÃO DO CABEÇALHO
    // ============================================================
    
    function selecionarInsignia() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    insigniaUrl = event.target.result;
                    if (currentPautaData) {
                        exibirPauta(currentPautaData);
                    }
                    mostrarMensagem('Insígnia adicionada com sucesso!', 'success');
                };
                reader.readAsDataURL(file);
            }
        };
        input.click();
    }

    function removerInsignia() {
        insigniaUrl = '';
        if (currentPautaData) {
            exibirPauta(currentPautaData);
        }
        mostrarMensagem('Insígnia removida', 'info');
    }

    function enableHeaderEditing(element, field) {
        if (element.classList.contains('editing')) return;
        const currentText = element.textContent;
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentText;
        input.className = 'edit-input';
        element.classList.add('editing');
        element.innerHTML = '';
        element.appendChild(input);
        input.focus();
        input.addEventListener('blur', () => {
            const newText = input.value.trim().toUpperCase() || headerTexts[field];
            headerTexts[field] = newText;
            element.classList.remove('editing');
            element.innerHTML = newText;
        });
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') input.blur();
        });
    }

    function enableFieldEditing(element, field) {
        if (element.classList.contains('editing')) return;
        const currentText = element.textContent;
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentText;
        input.className = 'edit-input';
        element.classList.add('editing');
        element.innerHTML = '';
        element.appendChild(input);
        input.focus();
        input.addEventListener('blur', () => {
            const newText = input.value.trim().toUpperCase();
            if (field === 'escola') {
                escolaNome = newText;
                element.innerHTML = 'ESCOLA DO ' + escolaNome;
            } else if (field === 'comuna') {
                comunaTexto = newText;
                element.innerHTML = 'COMUNA ' + comunaTexto;
            } else if (field === 'municipio') {
                municipioTexto = newText;
                element.innerHTML = 'MUNICÍPIO DE ' + municipioTexto;
            } else if (field === 'provincia') {
                provinciaTexto = newText;
                element.innerHTML = 'PROVÍNCIA DE ' + provinciaTexto;
            } else if (field === 'numeroPauta') {
                numeroPauta = newText;
                element.innerHTML = 'PAUTA Nº ' + numeroPauta;
            } else if (field === 'anoLectivo') {
                anoLectivo = newText;
                element.innerHTML = 'ANO LECTIVO: ' + anoLectivo;
            } else {
                element.innerHTML = newText;
            }
            element.classList.remove('editing');
        });
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') input.blur();
        });
    }

    function arredondarValores() {
        valoresArredondados = !valoresArredondados;
        if (currentPautaData) {
            exibirPauta(currentPautaData);
            mostrarMensagem(valoresArredondados ? '✅ Valores arredondados para números inteiros' : '✅ Valores restaurados com 1 casa decimal', 'success');
        }
    }

    // ============================================================
    // FUNÇÃO PRINCIPAL - GERAR PAUTA
    // ============================================================
    
    async function gerarPauta() {
        const classe = document.getElementById('classe').value;
        const turma = document.getElementById('turma').value;
        const periodo = document.getElementById('periodo').value;
        const ano = document.getElementById('anoLetivo').value;
        
        if (!classe || !turma || disciplinasSelecionadas.length === 0) {
            mostrarMensagem('Selecione classe, turma e pelo menos uma disciplina', 'warning');
            return;
        }
        
        const btn = document.getElementById('btnGerar');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span> Gerando pauta...';
        btn.disabled = true;
        
        try {
            const response = await fetch(window.location.href + '?action=gerar_pauta', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    classe: classe,
                    turma: turma,
                    periodo: periodo,
                    ano_letivo: ano,
                    disciplinas: disciplinasSelecionadas
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.alunos && data.alunos.length > 0) {
                const alunosDict = {};
                data.alunos.forEach(aluno => {
                    alunosDict[aluno.id] = aluno;
                });
                
                currentPautaData = {
                    ...data,
                    alunos: alunosDict,
                    disciplinas: disciplinasSelecionadas,
                    ano_letivo: ano
                };
                
                exibirPauta(currentPautaData);
                mostrarMensagem('✅ Pauta gerada com ' + data.alunos.length + ' alunos!', 'success');
            } else {
                mostrarMensagem(data.message || 'Nenhum dado encontrado', 'warning');
                gerarPautaExemplo(classe, turma, periodo);
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarMensagem('❌ Erro ao gerar pauta', 'danger');
            gerarPautaExemplo(classe, turma, periodo);
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    }

    // ============================================================
    // FUNÇÃO DE EXEMPLO
    // ============================================================
    
    function gerarPautaExemplo(classe, turma, periodo) {
        const alunosExemplo = [
            { id: 1, nome: 'Abgail Isabel de Sá e Menezes Paulo', sexo: 'F' },
            { id: 2, nome: 'Alice António André', sexo: 'F' },
            { id: 3, nome: 'Armando José da Silva', sexo: 'M' }
        ];
        
        alunosExemplo.forEach(aluno => {
            aluno.notas = {};
            disciplinasSelecionadas.forEach(disc => {
                const notaBase = Math.random() * 15 + 5;
                aluno.notas[disc] = {
                    mt1: (notaBase * 0.8).toFixed(1),
                    mt2: (notaBase * 0.85).toFixed(1),
                    mt3: notaBase.toFixed(1),
                    mfd: (notaBase * 0.9 + Math.random() * 3).toFixed(1)
                };
            });
        });
        
        const alunosDict = {};
        alunosExemplo.forEach(aluno => {
            alunosDict[aluno.id] = aluno;
        });
        
        currentPautaData = {
            success: true,
            classe: classe,
            turma: turma,
            periodo: periodo,
            ano_letivo: document.getElementById('anoLetivo').value,
            disciplinas: disciplinasSelecionadas,
            alunos: alunosDict
        };
        
        exibirPauta(currentPautaData);
        mostrarMensagem('📊 Pauta de exemplo gerada com ' + alunosExemplo.length + ' alunos', 'info');
    }

    // ============================================================
    // EXIBIR PAUTA
    // ============================================================

    function exibirPauta(data) {
        const container = document.getElementById('pautaResult');
        if (!container) return;
        
        const alunos = Object.values(data.alunos || {});
        const disciplinas = data.disciplinas || [];
        const classe = data.classe || '';
        const turma = data.turma || '';
        const periodo = data.periodo || 'final';
        const ano = data.ano_letivo || '2025/2026';
        const sala = data.sala || 'N/A';
        
        const dataAtual = new Date().toLocaleDateString('pt-PT');
        const periodoTexto = periodo === 'final' ? 'FINAL' : periodo + 'º TRIMESTRE';
        
        let classeNum = 7;
        let cicloDisplay = "ENSINO PRIMÁRIO";
        try {
            classeNum = parseInt(classe.replace(/[^0-9]/g, '')) || 7;
            if (classeNum <= 6) {
                cicloDisplay = "ENSINO PRIMÁRIO";
            } else if (classeNum >= 7 && classeNum <= 9) {
                cicloDisplay = "Iº CICLO (7ª-9ª)";
            } else if (classeNum >= 10 && classeNum <= 12) {
                cicloDisplay = "IIº CICLO (10ª-12ª)";
            }
        } catch(e) {}
        
        let notaMinima = 10;
        if (classeNum <= 6) notaMinima = 5;
        
        let insigniaHtml = '';
        if (insigniaUrl) {
            insigniaHtml = `
                <img src="${insigniaUrl}" class="insignia-img" onclick="selecionarInsignia()" title="Clique para trocar a insígnia">
                <button class="btn-insignia" style="background: #c0392b;" onclick="removerInsignia()"><span>🗑️</span> Remover</button>
            `;
        } else {
            insigniaHtml = `<button class="btn-insignia" onclick="selecionarInsignia()"><span>🖼️</span> Inserir Insígnia</button>`;
        }
        
        let html = `
            <div class="pauta-container">
                <div class="pauta-header">
                    <div class="insignia-area">
                        ${insigniaHtml}
                    </div>
                    <div class="republika" style="font-size:16px;" onclick="enableHeaderEditing(this, 'republica')">${headerTexts.republica}</div>
                    <div class="ministerio" style="font-size:16px;" onclick="enableHeaderEditing(this, 'ministerio')">${headerTexts.ministerio}</div>
                    <div class="titulo" style="font-size:16px;" onclick="enableHeaderEditing(this, 'titulo')">${headerTexts.titulo}</div>
                    <div class="escola-info" style="font-size:14px;" onclick="enableFieldEditing(this, 'escola')">ESCOLA DO ${escolaNome}</div>
                    <div class="ciclo-info" style="font-size:14px;" onclick="enableFieldEditing(this, 'ciclo')">${cicloDisplay}</div>
                    <div class="detalhes-info" style="font-size:14px;" onclick="enableFieldEditing(this, 'comuna')">COMUNA ${comunaTexto}</div>
                    <div class="detalhes-info" style="font-size:14px;" onclick="enableFieldEditing(this, 'municipio')">MUNICÍPIO DE ${municipioTexto}</div>
                    <div class="detalhes-info" style="font-size:14px;" onclick="enableFieldEditing(this, 'provincia')">PROVÍNCIA DE ${provinciaTexto}</div>
                    <div class="detalhes-info" style="font-size:14px;" onclick="enableFieldEditing(this, 'numeroPauta')">PAUTA Nº ${numeroPauta}</div>
                    <div class="detalhes-info" style="font-size:14px;" onclick="enableFieldEditing(this, 'anoLectivo')">ANO LECTIVO: ${ano}</div>
                    <div class="detalhes-info" style="font-size:14px;">
                        <span onclick="enableFieldEditing(this, 'periodo')">PERÍODO ${periodoTexto}</span>
                        <span onclick="enableFieldEditing(this, 'classeModulo')">CLASSE/MÓDULO ${classe}</span>
                        <span onclick="enableFieldEditing(this, 'turma')">TURMA ${turma}</span>
                        <span onclick="enableFieldEditing(this, 'sala')">SALA ${sala}</span>
                    </div>
                </div>
                
                <div class="action-bar mb-3">
                    <button class="btn btn-sm btn-arredondar" onclick="arredondarValores()"><span>🧮</span> ${valoresArredondados ? 'Restaurar Decimais' : 'Arredondar'}</button>
                    <button class="btn btn-sm btn-print" onclick="window.print()"><span>🖨️</span> Imprimir</button>
                    <button class="btn btn-sm btn-excel" onclick="exportarExcel()"><span>📊</span> Exportar Excel</button>
                </div>
                
                <div class="table-responsive">
                    <table class="pauta-table">
                        <thead>
                            <tr>
                                <th rowspan="2" style="font-size:14px;">Nº</th>
                                <th rowspan="2" style="font-size:14px;text-align:left;">NOME DO ALUNO</th>
                                <th rowspan="2" style="font-size:14px;">SEXO</th>`;

        disciplinas.forEach(disciplina => {
            html += `<th colspan="4" style="font-size:14px;">${disciplina}</th>`;
        });
        
        html += `<th rowspan="2" style="font-size:14px;">MFD</th>
                <th rowspan="2" style="font-size:14px;">SITUAÇÃO</th>
            </tr>
            <tr>`;
        
        disciplinas.forEach(() => {
            html += `<th class="subcoluna" style="font-size:12px;">MT1</th>
                    <th class="subcoluna" style="font-size:12px;">MT2</th>
                    <th class="subcoluna" style="font-size:12px;">MT3</th>
                    <th class="subcoluna" style="font-size:12px;">MDF</th>`;
        });
        
        html += `</tr>
                </thead>
                <tbody>`;
        
        let total = 0, aprovados = 0, desistentes = 0;
        
        alunos.forEach((aluno, idx) => {
            let somaMedias = 0;
            let countMedias = 0;
            let disciplinasNegativas = 0;
            let temNota = false;
            
            html += `<tr>
                        <td style="font-size:14px;">${idx + 1}</td>
                        <td style="font-size:14px;text-align:left;">${aluno.nome}</td>
                        <td style="font-size:14px;">${(aluno.sexo || 'M').toUpperCase() === 'F' ? 'F' : 'M'}</td>`;
            
            disciplinas.forEach(disciplina => {
                const notas = aluno.notas?.[disciplina] || {};
                let mt1 = parseFloat(notas.mt1) || 0;
                let mt2 = parseFloat(notas.mt2) || 0;
                let mt3 = parseFloat(notas.mt3) || 0;
                let mfd = parseFloat(notas.mfd) || 0;
                
                if (mt1 > 0 || mt2 > 0 || mt3 > 0 || mfd > 0) temNota = true;
                
                const notasValidas = [mt1, mt2, mt3].filter(n => n > 0);
                let mdf = 0;
                if (notasValidas.length > 0) {
                    mdf = notasValidas.reduce((a, b) => a + b, 0) / notasValidas.length;
                } else if (mfd > 0) {
                    mdf = mfd;
                }
                
                if (mdf > 0 && mdf < notaMinima) disciplinasNegativas++;
                
                const mt1Display = mt1 > 0 ? formatarNota(mt1) : '-';
                const mt2Display = mt2 > 0 ? formatarNota(mt2) : '-';
                const mt3Display = mt3 > 0 ? formatarNota(mt3) : '-';
                const mdfDisplay = mdf > 0 ? formatarNota(mdf) : '-';
                
                const mt1Class = mt1 >= notaMinima ? 'positiva' : (mt1 > 0 ? 'negativa' : '');
                const mt2Class = mt2 >= notaMinima ? 'positiva' : (mt2 > 0 ? 'negativa' : '');
                const mt3Class = mt3 >= notaMinima ? 'positiva' : (mt3 > 0 ? 'negativa' : '');
                const mdfClass = mdf >= notaMinima ? 'positiva' : (mdf > 0 ? 'negativa' : '');
                
                html += `<td class="${mt1Class}" style="font-size:14px;">${mt1Display}</td>
                        <td class="${mt2Class}" style="font-size:14px;">${mt2Display}</td>
                        <td class="${mt3Class}" style="font-size:14px;">${mt3Display}</td>
                        <td class="${mdfClass}" style="font-size:14px;">${mdfDisplay}</td>`;
                
                if (mdf > 0) {
                    somaMedias += mdf;
                    countMedias++;
                }
            });
            
            let mediaFinal = 0;
            let mediaFinalDisplay = '-';
            if (countMedias > 0) {
                mediaFinal = somaMedias / countMedias;
                mediaFinalDisplay = formatarNota(mediaFinal);
            }
            
            let situacao = '';
            let situacaoClass = '';
            
            if (!temNota) {
                situacao = 'DESISTENTE';
                situacaoClass = 'situacao-desistente';
                desistentes++;
            } else if (mediaFinal === 0) {
                situacao = 'DESISTENTE';
                situacaoClass = 'situacao-desistente';
                desistentes++;
            } else if (mediaFinal < 2) {
                situacao = 'DESISTENTE';
                situacaoClass = 'situacao-desistente';
                desistentes++;
            } else if (disciplinasNegativas > 3) {
                situacao = 'NÃO TRANSITA';
                situacaoClass = 'situacao-reprovado';
            } else if (mediaFinal >= notaMinima) {
                situacao = 'TRANSITA';
                situacaoClass = 'situacao-aprovado';
                aprovados++;
            } else {
                situacao = 'NÃO TRANSITA';
                situacaoClass = 'situacao-reprovado';
            }
            
            html += `<td class="media-destaque" style="font-size:14px;">${mediaFinalDisplay}</td>
                    <td class="${situacaoClass}" style="font-size:14px;">${situacao}</td>
                </tr>`;
            total++;
        });
        
        const percAprov = total > 0 ? (aprovados / total * 100).toFixed(1) : 0;
        const percDesistentes = total > 0 ? (desistentes / total * 100).toFixed(1) : 0;
        
        html += `
                    </tbody>
                </table>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="stats-card">
                        <h6 style="font-size:14px;"><span>📊</span> RESUMO DA TURMA</h6>
                        <p style="font-size:14px;"><strong>Total de Alunos:</strong> ${total}</p>
                        <p style="font-size:14px;"><strong>TRANSITA (Aprovados):</strong> ${aprovados} (${percAprov}%)</p>
                        <p style="font-size:14px;"><strong>NÃO TRANSITA (Reprovados):</strong> ${total - aprovados - desistentes} (${(100 - percAprov - percDesistentes).toFixed(1)}%)</p>
                        <p style="font-size:14px;"><strong>DESISTENTES:</strong> ${desistentes} (${percDesistentes}%)</p>
                        <div class="progress-bar-custom mt-2">
                            <div class="progress-fill" style="width: ${percAprov}%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stats-card">
                        <h6 style="font-size:14px;"><span>ℹ️</span> INFORMAÇÕES</h6>
                        <p style="font-size:14px;"><strong>Nota Mínima para TRANSITA:</strong> ${notaMinima} valores</p>
                        <p style="font-size:14px;"><strong>Data de Emissão:</strong> ${dataAtual}</p>
                        <p style="font-size:14px;"><strong>Ano Letivo:</strong> ${ano}</p>
                    </div>
                </div>
            </div>
            
            <div class="legenda">
                <div class="row" style="font-size:14px;">
                    <div class="col"><span class="positiva">●</span> POSITIVA: Nota ≥ ${notaMinima}</div>
                    <div class="col"><span class="negativa">●</span> NEGATIVA: Nota < ${notaMinima}</div>
                    <div class="col"><span class="badge badge-success">✓</span> TRANSITA: MFD ≥ ${notaMinima}</div>
                    <div class="col"><span class="badge badge-danger">✗</span> NÃO TRANSITA: MFD < ${notaMinima} ou +3 negativas</div>
                    <div class="col"><span class="badge badge-warning">⚠</span> DESISTENTE: Sem notas ou MFD &lt; 2</div>
                    <div class="col"><span class="badge badge-secondary">-</span> Vazio: Não tem nota no banco</div>
                </div>
            </div>
            
            <div class="assinatura-area">
                <div class="assinatura-item">
                    <div class="linha-assinatura"></div>
                    <small style="font-size:14px;">O Director de Turma</small>
                </div>
                <div class="assinatura-item">
                    <div class="linha-assinatura"></div>
                    <small style="font-size:14px;">O Subdirector Pedagógico</small>
                </div>
                <div class="assinatura-item">
                    <div class="linha-assinatura"></div>
                    <small style="font-size:14px;">O Encarregado de Educação</small>
                </div>
            </div>
            
            <div class="text-center text-muted small mt-3" style="font-size:14px;">
                <span>🖨️</span> Documento gerado eletronicamente
            </div>
        </div>
    `;
    
    container.innerHTML = html;
    container.style.display = 'block';
    container.scrollIntoView({ behavior: 'smooth' });
    }

    // ============================================================
    // EXPORTAR EXCEL
    // ============================================================
    
    function exportarExcel() {
        if (!currentPautaData) {
            mostrarMensagem('Gere uma pauta primeiro', 'warning');
            return;
        }
        
        const container = document.querySelector('.pauta-container');
        if (!container) {
            mostrarMensagem('Pauta não encontrada', 'warning');
            return;
        }
        
        const clone = container.cloneNode(true);
        clone.querySelectorAll('.action-bar, .btn-print, .btn-excel, .btn-arredondar, .btn-insignia, .insignia-area button, .legenda, .stats-card, .assinatura-area, .text-center.text-muted').forEach(el => {
            if (el) el.remove();
        });
        clone.querySelectorAll('.row.mt-4').forEach(el => el.remove());
        
        const style = document.createElement('style');
        style.textContent = `
            body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; font-size: 14px; }
            .pauta-header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #2c3e50; padding-bottom: 15px; }
            .pauta-header .republika, .pauta-header .ministerio, .pauta-header .titulo { font-size: 16px; }
            .pauta-header .escola-info, .pauta-header .ciclo-info, .pauta-header .detalhes-info { font-size: 14px; }
            .pauta-table { border-collapse: collapse; width: 100%; font-size: 14px; }
            .pauta-table th { background: #2c3e50; color: white; padding: 6px 4px; border: 1px solid #000; text-align: center; font-size: 14px; }
            .pauta-table td { border: 1px solid #000; padding: 5px 4px; text-align: center; font-size: 14px; }
            .subcoluna { background: #34495e; color: white; font-size: 12px; }
            .situacao-aprovado { background: #d4edda !important; color: #155724 !important; font-weight: bold; }
            .situacao-reprovado { background: #f8d7da !important; color: #721c24 !important; font-weight: bold; }
            .situacao-desistente { background: #fff3cd !important; color: #856404 !important; font-weight: bold; }
            .positiva { color: #000000 !important; font-weight: bold; }
            .negativa { color: #dc3545 !important; font-weight: bold; }
            .media-destaque { font-weight: bold; color: #2c3e50; font-size: 14px; }
            .insignia-img { max-width: 80px; max-height: 80px; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        `;
        clone.prepend(style);
        
        const data = currentPautaData;
        const ano = data.ano_letivo || '2025-2026';
        const nomeArquivo = 'pauta_' + data.classe + '_' + data.turma + '_' + ano.replace('/', '-');
        
        const htmlContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <title>Pauta - ${data.classe} ${data.turma}</title>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; font-size: 14px; }
                    .pauta-header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #2c3e50; padding-bottom: 15px; }
                    .pauta-header .republika, .pauta-header .ministerio, .pauta-header .titulo { font-size: 16px; }
                    .pauta-header .escola-info, .pauta-header .ciclo-info, .pauta-header .detalhes-info { font-size: 14px; }
                    .pauta-table { border-collapse: collapse; width: 100%; font-size: 14px; }
                    .pauta-table th { background: #2c3e50; color: white; padding: 6px 4px; border: 1px solid #000; text-align: center; font-size: 14px; }
                    .pauta-table td { border: 1px solid #000; padding: 5px 4px; text-align: center; font-size: 14px; }
                    .subcoluna { background: #34495e; color: white; font-size: 12px; }
                    .situacao-aprovado { background: #d4edda !important; color: #155724 !important; font-weight: bold; }
                    .situacao-reprovado { background: #f8d7da !important; color: #721c24 !important; font-weight: bold; }
                    .situacao-desistente { background: #fff3cd !important; color: #856404 !important; font-weight: bold; }
                    .positiva { color: #000000 !important; font-weight: bold; }
                    .negativa { color: #dc3545 !important; font-weight: bold; }
                    .media-destaque { font-weight: bold; color: #2c3e50; font-size: 14px; }
                    .insignia-img { max-width: 80px; max-height: 80px; }
                    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                </style>
            </head>
            <body>
                ${clone.outerHTML}
            </body>
            </html>
        `;
        
        const blob = new Blob([htmlContent], { type: 'application/vnd.ms-excel;charset=UTF-8' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.href = url;
        link.download = nomeArquivo + '.xls';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        mostrarMensagem('📊 Pauta exportada com sucesso!', 'success');
    }

    // ============================================================
    // INICIALIZAÇÃO
    // ============================================================
    
    document.addEventListener('DOMContentLoaded', function() {
        carregarDisciplinas();
        carregarTurmas();
    });
    </script>

</body>
</html>