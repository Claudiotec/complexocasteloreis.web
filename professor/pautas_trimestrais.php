<?php
// ============================================
// pautas_trimestrais.php - Pautas Trimestrais
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
$permitidos = ['professor', 'docente', 'coordenador', 'diretor', 'admin', 'pedagogo'];
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

// ===== 9. BUSCAR DADOS ATRIBUIDOS AO PROFESSOR =====
$classes = [];
$turmas = [];
$disciplinas = [];

try {
    if ($professor_num_agente) {
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

if (empty($classes)) $classes = ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª', '7ª', '8ª', '9ª', '10ª', '11ª', '12ª'];
if (empty($turmas)) $turmas = ['2AM'];
if (empty($disciplinas)) $disciplinas = ['Matemática', 'Língua Portuguesa', 'C. Natureza', 'Ed. Física'];

// ===== 10. FUNÇÃO PARA BUSCAR NOTAS (COM FILTRO POR ANO LETIVO) =====
function buscarNotasAlunos($pdo, $classe, $turma, $disciplinas, $trimestre, $ano_letivo = null) {
    $resultado = [
        'success' => true,
        'alunos' => [],
        'trimestre' => $trimestre,
        'classe' => $classe,
        'turma' => $turma,
        'ano_letivo' => $ano_letivo
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
        
        // ===== FILTRO POR ANO LETIVO =====
        $sql_notas = "SELECT id_aluno, disciplina, ano_letivo,
                      mac_t1, npt_t1, mt1, mac_t2, npt_t2, mt2, mac_t3, npt_t3, mt3,
                      neo, en, mec, mfed, mfd, classificacao
                      FROM notas_alunos 
                      WHERE id_aluno IN ($placeholders) AND turma = ? AND classe = ?";
        $params = array_merge($ids, [$turma, $classe]);
        
        if (!empty($ano_letivo)) {
            $sql_notas .= " AND ano_letivo = ?";
            $params[] = $ano_letivo;
        }
        
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
                    
                    if ($trimestre == 1) {
                        $notas[$disciplina] = $n['mt1'] ?? $n['mac_t1'] ?? null;
                    } elseif ($trimestre == 2) {
                        $notas[$disciplina] = $n['mt2'] ?? $n['mac_t2'] ?? null;
                    } elseif ($trimestre == 3) {
                        $notas[$disciplina] = $n['mt3'] ?? $n['mac_t3'] ?? null;
                    } else {
                        $notas[$disciplina] = $n['mfd'] ?? null;
                    }
                } else {
                    $notas[$disciplina] = null;
                }
            }
            
            $medias = array_filter($notas, function($v) {
                return $v !== null && $v !== '' && is_numeric($v);
            });
            $media = !empty($medias) ? array_sum($medias) / count($medias) : 0;
            
            if ($media < 1.2) {
                $situacao = 'DESISTENTE';
            } elseif ($media >= 5.0) {
                $situacao = 'TRANSITA';
            } else {
                $situacao = 'NÃO TRANSITA';
            }
            
            $resultado['alunos'][] = [
                'id' => $aluno['id'],
                'nome' => $aluno['nome'],
                'sexo' => $aluno['sexo'] ?? '',
                'notas' => $notas,
                'media' => round($media, 1),
                'situacao' => $situacao
            ];
        }
        
    } catch (Exception $e) {
        error_log("Erro ao buscar notas: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
    
    return $resultado;
}

// ===== 11. PROCESSAR REQUISIÇÕES AJAX =====
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    
    if ($action === 'buscar_dados') {
        $classe = isset($_GET['classe']) ? $_GET['classe'] : '';
        $turma = isset($_GET['turma']) ? $_GET['turma'] : '';
        $trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 1;
        $disciplinas = isset($_GET['disciplinas']) && $_GET['disciplinas'] !== '' ? explode(',', $_GET['disciplinas']) : [];
        $ano_letivo = isset($_GET['ano_letivo']) ? trim($_GET['ano_letivo']) : null;
        
        if (empty($classe) || empty($turma) || empty($disciplinas)) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
            exit;
        }
        
        $resultado = buscarNotasAlunos($pdo, $classe, $turma, $disciplinas, $trimestre, $ano_letivo);
        echo json_encode($resultado);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pautas Trimestrais - <?= htmlspecialchars($nomeEmpresa) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           RESET E LAYOUT PRINCIPAL
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 13px;
            background: #f0f2f5;
            padding: 15px;
            color: #1a2332;
        }
        
        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 25px 30px;
            border: 1px solid #e2e8f0;
            page-break-inside: avoid;
        }
        
        /* ============================================
           HEADER INSTITUCIONAL
           ============================================ */
        .header-institucional {
            text-align: center;
            border-bottom: 3px solid #1a2332;
            padding-bottom: 12px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .header-institucional .insignia-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 5px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .header-institucional .insignia-container:hover {
            transform: scale(1.05);
        }
        
        .header-institucional .insignia-container .insignia {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .header-institucional .insignia-container .insignia img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            padding: 5px;
        }
        
        .header-institucional .insignia-container .insignia .insignia-emoji {
            font-size: 50px;
            line-height: 1;
        }
        
        .header-institucional .insignia-container .edit-icon-sm {
            font-size: 12px;
            opacity: 0.4;
            margin-left: 5px;
            align-self: center;
        }
        
        .header-institucional .linha1 {
            font-size: 14px;
            font-weight: 700;
            color: #1a2332;
            letter-spacing: 2px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .header-institucional .linha1:hover {
            color: #c9a84c;
        }
        
        .header-institucional .linha2 {
            font-size: 13px;
            font-weight: 600;
            color: #1a2332;
            letter-spacing: 1px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .header-institucional .linha2:hover {
            color: #c9a84c;
        }
        
        .header-institucional .linha3 {
            font-size: 16px;
            font-weight: 800;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: color 0.2s;
            margin-top: 2px;
        }
        
        .header-institucional .linha3:hover {
            color: #c9a84c;
        }
        
        .header-institucional .linha3 span {
            color: #c9a84c;
        }
        
        .header-institucional .linha4 {
            font-size: 15px;
            font-weight: 700;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: color 0.2s;
            margin-top: 2px;
        }
        
        .header-institucional .linha4:hover {
            color: #c9a84c;
        }
        
        .header-institucional .linha4 span {
            color: #c9a84c;
        }
        
        .header-institucional .edit-icon {
            font-size: 12px;
            opacity: 0.4;
            margin-left: 5px;
            transition: opacity 0.2s;
        }
        
        .header-institucional .edit-icon:hover {
            opacity: 1;
        }
        
        .header-institucional .dados-empresa {
            font-size: 12px;
            color: #4a5568;
            margin-top: 4px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .header-institucional .dados-empresa:hover {
            color: #c9a84c;
        }
        
        .header-institucional .dados-empresa .sep {
            margin: 0 5px;
            color: #c9a84c;
        }
        
        /* ============================================
           TÍTULO DA PAUTA
           ============================================ */
        .titulo-pauta {
            text-align: center;
            margin: 5px 0 15px;
        }
        
        .titulo-pauta h1 {
            font-size: 19px;
            font-weight: 700;
            color: #1a2332;
            letter-spacing: 2px;
            text-transform: uppercase;
            background: #c9a84c;
            color: #1a2332;
            padding: 6px 30px;
            display: inline-block;
            border-radius: 4px;
        }
        
        /* ============================================
           FILTROS
           ============================================ */
        .filters {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 160px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 12px;
        }
        
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            background: white;
            transition: border-color 0.3s;
        }
        
        .filter-group select:focus, .filter-group input:focus {
            border-color: #c9a84c;
            outline: none;
        }
        
        .disciplinas-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background: white;
            max-height: 180px;
            overflow-y: auto;
        }
        
        .disciplina-checkbox {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #f8fafc;
            border-radius: 20px;
            border: 1px solid #ddd;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 13px;
        }
        
        .disciplina-checkbox:hover {
            border-color: #c9a84c;
            background: #f5edd6;
        }
        
        .disciplina-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 15px;
            align-items: center;
        }
        
        .generate-btn {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .generate-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39,174,96,0.3);
        }
        
        .generate-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        
        .generate-btn.loading {
            position: relative;
            color: transparent;
        }
        
        .generate-btn.loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-top: -10px;
            margin-left: -10px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .select-all-btn, .clear-all-btn {
            padding: 4px 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        
        .select-all-btn {
            background: #c9a84c;
            color: #1a2332;
        }
        
        .select-all-btn:hover {
            background: #b8973a;
        }
        
        .clear-all-btn {
            background: #e74c3c;
            color: white;
        }
        
        .clear-all-btn:hover {
            background: #c0392b;
        }
        
        /* ============================================
           PAUTA - DRAG AND DROP
           ============================================ */
        .pauta-container {
            background: white;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 25px;
            margin-top: 20px;
            overflow-x: auto;
            display: none;
        }
        
        .pauta-header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #1a2332;
            padding-bottom: 20px;
        }
        
        .pauta-header .escola-nome {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1a2332;
            margin: 10px 0;
        }
        
        .pauta-header h3 {
            color: #1a2332;
            margin: 10px 0 5px;
            font-size: 20px;
        }
        
        .pauta-header p {
            color: #34495e;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .pauta-header .subtitulo {
            font-size: 13px;
            color: #7f8c8d;
        }
        
        .pauta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin: 15px 0;
        }
        
        .pauta-table th {
            background: #1a2332;
            color: white;
            padding: 8px 6px;
            text-align: center;
            border: 1px solid #1a2332;
            white-space: nowrap;
            font-size: 11px;
        }
        
        .pauta-table td {
            padding: 6px 4px;
            border: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
        }
        
        .pauta-table tr:nth-child(even) {
            background: #f8fafc;
        }
        
        .pauta-table tr:hover {
            background: #f5edd6;
        }
        
        .pauta-table .aluno-nome {
            text-align: left;
            font-weight: 500;
        }
        
        .pauta-table .positiva {
            color: #27ae60;
            font-weight: bold;
        }
        
        .pauta-table .negativa {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .situacao-aprovado {
            background: #d4edda !important;
            color: #155724;
            font-weight: bold;
        }
        
        .situacao-reprovado {
            background: #f8d7da !important;
            color: #721c24;
            font-weight: bold;
        }
        
        .situacao-desistente {
            background: #fff3cd !important;
            color: #856404;
            font-weight: bold;
        }
        
        /* DRAG AND DROP */
        .pauta-table th[draggable="true"] {
            cursor: grab;
            user-select: none;
            position: relative;
            transition: background-color 0.3s;
        }
        
        .pauta-table th[draggable="true"]:hover {
            background-color: #34495e;
        }
        
        .pauta-table th[draggable="true"]:active {
            cursor: grabbing;
        }
        
        .pauta-table th[draggable="true"].dragging {
            opacity: 0.4;
            background-color: #c9a84c;
            transform: scale(0.95);
        }
        
        .pauta-table th[draggable="true"] .drag-handle {
            display: inline-block;
            margin-left: 4px;
            font-size: 10px;
            opacity: 0.3;
            transition: opacity 0.3s;
        }
        
        .pauta-table th[draggable="true"]:hover .drag-handle {
            opacity: 1;
        }
        
        .pauta-table th.drag-over {
            border-left: 3px solid #c9a84c;
            border-right: 3px solid #c9a84c;
            background-color: #2c3e50;
        }
        
        .pauta-table th.drag-target {
            background-color: #c9a84c !important;
            color: #1a2332 !important;
        }
        
        .pauta-table th.drag-target .drag-handle {
            opacity: 1 !important;
        }
        
        .pauta-table th[draggable="true"] {
            transition: all 0.2s;
        }
        
        .pauta-table th.dragging {
            transition: none;
        }
        
        .pauta-table th[draggable="true"]::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 10%;
            right: 10%;
            height: 2px;
            background: transparent;
            transition: background 0.3s;
        }
        
        .pauta-table th[draggable="true"]:hover::after {
            background: #c9a84c;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stats-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e2e8f0;
        }
        
        .stats-card h5 {
            color: #1a2332;
            margin-bottom: 10px;
            border-bottom: 2px solid #c9a84c;
            padding-bottom: 5px;
            font-size: 14px;
        }
        
        .stats-card p {
            font-size: 13px;
            margin: 4px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .stats-card .label {
            color: #555;
        }
        
        .stats-card .value {
            font-weight: 600;
        }
        
        .progress-bar {
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 8px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #2ecc71);
            color: white;
            font-size: 11px;
            line-height: 20px;
            text-align: center;
            transition: width 0.5s;
        }
        
        .legenda {
            margin-top: 25px;
            padding: 15px;
            background: #f5edd6;
            border-radius: 6px;
            border-left: 4px solid #c9a84c;
        }
        
        .legenda h5 {
            margin-bottom: 10px;
            color: #1a2332;
        }
        
        .legenda-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .legenda-item {
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legenda-item .cor {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            flex-shrink: 0;
        }
        
        .cor-positiva { background: #27ae60; }
        .cor-negativa { background: #e74c3c; }
        .cor-aprovado { background: #d4edda; border: 1px solid #c3e6cb; }
        .cor-reprovado { background: #f8d7da; border: 1px solid #f5c6cb; }
        .cor-desistente { background: #fff3cd; border: 1px solid #ffeaa7; }
        
        .assinaturas {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .assinatura-item {
            text-align: center;
            flex: 1;
        }
        
        .linha-assinatura {
            border-top: 1px solid #333;
            width: 80%;
            margin: 40px auto 5px;
        }
        
        .assinatura-nome {
            font-weight: bold;
            font-size: 12px;
        }
        
        .assinatura-cargo {
            font-size: 11px;
            color: #666;
        }
        
        .rodape {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            color: #666;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .total-alunos-info {
            background: #f5edd6;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            border: 1px solid #c9a84c;
        }
        
        .message {
            padding: 12px 18px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: none;
            font-weight: 500;
        }
        
        .message.error {
            display: block;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.success {
            display: block;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.warning {
            display: block;
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .action-bar {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .btn-excel {
            background: #217346;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-excel:hover {
            background: #1a5e38;
        }
        
        .btn-print {
            background: #7f8c8d;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-print:hover {
            background: #6c7a7a;
        }
        
        .btn-reset {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-reset:hover {
            background: #5a6268;
        }
        
        .btn-voltar {
            background: #1a2332;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-voltar:hover {
            background: #c9a84c;
        }
        
        /* ============================================
           MODAL PARA EDIÇÃO
           ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: #fff;
            padding: 25px 30px;
            border-radius: 12px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-content h3 {
            font-size: 18px;
            color: #1a2332;
            margin-bottom: 10px;
        }
        
        .modal-content label {
            font-weight: 600;
            display: block;
            margin: 10px 0 5px;
            color: #4a5568;
            font-size: 13px;
        }
        
        .modal-content input[type="text"],
        .modal-content textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Arial', sans-serif;
        }
        
        .modal-content textarea {
            resize: vertical;
            min-height: 40px;
        }
        
        .modal-content .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .modal-content .btn-group button {
            padding: 8px 25px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
        }
        
        .modal-content .btn-group .btn-salvar {
            background: #1a2332;
            color: #fff;
        }
        
        .modal-content .btn-group .btn-salvar:hover {
            background: #c9a84c;
        }
        
        .modal-content .btn-group .btn-cancelar {
            background: #f1f5f9;
            color: #4a5568;
        }
        
        .modal-content .btn-group .btn-cancelar:hover {
            background: #e2e8f0;
        }
        
        .modal-content .btn-group .btn-importar {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .modal-content .btn-group .btn-importar:hover {
            background: #bfdbfe;
        }
        
        .modal-content .preview-insignia {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 10px 0;
            padding: 10px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px dashed #d0d5dd;
        }
        
        .modal-content .preview-insignia .preview-box {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            background: transparent;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .modal-content .preview-insignia .preview-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
        }
        
        .modal-content .preview-insignia .preview-label {
            font-size: 12px;
            color: #4a5568;
        }
        
        .modal-content .preview-insignia .preview-box .preview-emoji {
            font-size: 38px;
            line-height: 1;
        }
        
        /* ============================================
           IMPRESSÃO
           ============================================ */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                border: none;
                padding: 18px 22px;
                box-shadow: none;
                page-break-after: avoid;
            }
            
            .pauta-container {
                display: block !important;
                border: none !important;
                padding: 0.5cm !important;
            }
            
            .filters {
                display: none !important;
            }
            
            .pauta-table {
                font-size: 10px;
            }
            
            .pauta-table th {
                background: #1a2332 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .pauta-table th[draggable="true"] {
                cursor: default !important;
            }
            
            .pauta-table th[draggable="true"] .drag-handle {
                display: none !important;
            }
            
            .situacao-aprovado, .situacao-reprovado, .situacao-desistente {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .stats-grid {
                page-break-inside: avoid;
            }
            
            .assinaturas {
                page-break-inside: avoid;
            }
        }
        
        /* ============================================
           RESPONSIVO
           ============================================ */
        @media (max-width: 768px) {
            .container {
                padding: 12px;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .pauta-table {
                font-size: 10px;
            }
            
            .pauta-table th, .pauta-table td {
                padding: 4px 3px;
            }
            
            .header-institucional .linha1 {
                font-size: 12px;
            }
            
            .header-institucional .linha2 {
                font-size: 11px;
            }
            
            .header-institucional .linha3 {
                font-size: 13px;
            }
            
            .header-institucional .linha4 {
                font-size: 12px;
            }
            
            .header-institucional .insignia-container .insignia {
                width: 65px;
                height: 65px;
            }
            
            .header-institucional .insignia-container .insignia .insignia-emoji {
                font-size: 36px;
            }
        }
    </style>
</head>
<body>

<!-- ===== MODAL PARA EDIÇÃO ===== -->
<div class="modal-overlay" id="modalEditar">
    <div class="modal-content">
        <h3>✏️ Editar Cabeçalho Institucional</h3>
        
        <label for="edit_linha1">Linha 1 - República</label>
        <input type="text" id="edit_linha1" value="REPÚBLICA DE ANGOLA">
        
        <label for="edit_linha2">Linha 2 - Governo Provincial</label>
        <input type="text" id="edit_linha2" value="GOVERNO PROVINCIAL DO ÍCOLO E BENGO">
        
        <label for="edit_linha3">Linha 3 - Direcção Municipal</label>
        <input type="text" id="edit_linha3" value="DIRECÇÃO MUNICIPAL DA EDUCAÇÃO DE BOM JESUS">
        
        <label for="edit_linha4">Linha 4 - Nome da Escola</label>
        <input type="text" id="edit_linha4" value="<?= htmlspecialchars($nomeEmpresa) ?>">
        
        <label for="edit_dados_empresa">Dados da Empresa (Endereço, Telefone, Email, NIF)</label>
        <textarea id="edit_dados_empresa" rows="2"><?= htmlspecialchars($enderecoCompleto) ?> | Tel: <?= htmlspecialchars($telefoneEmpresa) ?> | Email: <?= htmlspecialchars($emailEmpresa) ?> | NIF: <?= htmlspecialchars($cnpjEmpresa) ?></textarea>
        
        <label>Insígnia</label>
        <div class="preview-insignia">
            <div class="preview-box" id="previewInsignia">
                <span class="preview-emoji" id="previewInsigniaConteudo">🏛️</span>
            </div>
            <div class="preview-label">Clique no botão abaixo para importar imagem</div>
        </div>
        
        <input type="text" id="edit_insignia" placeholder="URL da imagem da insígnia ou emoji (ex: 🏛️)">
        
        <div class="btn-group">
            <button class="btn-importar" onclick="importarInsignia()">📁 Importar Imagem</button>
        </div>
        
        <input type="file" id="fileInput" accept="image/*" style="display:none" onchange="uploadInsignia(event)">
        
        <div class="btn-group" style="margin-top:15px;">
            <button class="btn-cancelar" onclick="fecharModal()">Cancelar</button>
            <button class="btn-salvar" onclick="salvarEdicao()">Salvar</button>
        </div>
    </div>
</div>

<!-- ==========================================
     CONTEÚDO PRINCIPAL
     ========================================== -->
<div class="container" id="pautaContainer">

    <!-- ===== HEADER INSTITUCIONAL ===== -->
    <div class="header-institucional">
        <!-- Insígnia no centro - SEM BORDA -->
        <div class="insignia-container" onclick="abrirModal()" title="Clique para editar a insígnia">
            <div class="insignia" id="insigniaContainer">
                <span class="insignia-emoji" id="insigniaConteudo">🏛️</span>
            </div>
            <span class="edit-icon-sm">✎</span>
        </div>
        
        <!-- Linhas do cabeçalho -->
        <div class="linha1" id="linha1" onclick="abrirModal()">
            REPÚBLICA DE ANGOLA
            <span class="edit-icon">✎</span>
        </div>
        <div class="linha2" id="linha2" onclick="abrirModal()">
            GOVERNO PROVINCIAL DO ÍCOLO E BENGO
            <span class="edit-icon">✎</span>
        </div>
        <div class="linha3" id="linha3" onclick="abrirModal()">
            DIRECÇÃO MUNICIPAL DA EDUCAÇÃO DE <span>BOM JESUS</span>
            <span class="edit-icon">✎</span>
        </div>
        
        <!-- LINHA 4 - NOME DA ESCOLA VINDO DO BANCO -->
        <div class="linha4" id="linha4" onclick="abrirModal()">
            <span><?= htmlspecialchars($nomeEmpresa) ?></span>
            <span class="edit-icon">✎</span>
        </div>
        
        <!-- Dados da Empresa - VINDO DO BANCO -->
        <div class="dados-empresa" id="dadosEmpresa" onclick="abrirModal()">
            <?= htmlspecialchars($enderecoCompleto) ?>
            <?php if ($telefoneEmpresa): ?>
            <span class="sep">|</span> Tel: <?= htmlspecialchars($telefoneEmpresa) ?>
            <?php endif; ?>
            <?php if ($emailEmpresa): ?>
            <span class="sep">|</span> Email: <?= htmlspecialchars($emailEmpresa) ?>
            <?php endif; ?>
            <?php if ($cnpjEmpresa): ?>
            <span class="sep">|</span> NIF: <?= htmlspecialchars($cnpjEmpresa) ?>
            <?php endif; ?>
            <span class="edit-icon">✎</span>
        </div>
    </div>

    <!-- ===== TÍTULO ===== -->
    <div class="titulo-pauta">
        <h1>📋 PAUTAS TRIMESTRAIS</h1>
    </div>

    <!-- ===== MENSAGEM ===== -->
    <div id="messageArea" class="message"></div>

    <!-- ===== FILTROS ===== -->
    <div class="filters no-print">
        <div class="filter-row">
            <div class="filter-group">
                <label>📅 Ano Letivo:</label>
                <input type="text" id="anoLetivo" value="<?= date('Y') . '/' . (date('Y') + 1) ?>" placeholder="Ex: 2026/2027">
            </div>
            <div class="filter-group">
                <label>📅 Trimestre:</label>
                <select id="trimestre">
                    <option value="1">1º Trimestre</option>
                    <option value="2">2º Trimestre</option>
                    <option value="3">3º Trimestre</option>
                </select>
            </div>
            <div class="filter-group">
                <label>📚 Classe:</label>
                <select id="classe">
                    <option value="">Selecione</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?>ª Classe</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>👥 Turma:</label>
                <select id="turma">
                    <option value="">Selecione</option>
                    <?php foreach ($turmas as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>">Turma <?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div style="margin-top:15px;">
            <label style="font-weight:600;font-size:12px;color:#1a2332;">
                📖 Disciplinas: <span id="selectedCount" style="font-weight:normal;color:#666;"></span>
            </label>
            <div style="margin:8px 0;">
                <button class="select-all-btn" onclick="selecionarTodas()">Selecionar Todas</button>
                <button class="clear-all-btn" onclick="limparSelecao()">Limpar Seleção</button>
            </div>
            <div id="disciplinasList" class="disciplinas-list">
                <p style="color:#999;">Selecione uma classe e turma para carregar as disciplinas</p>
            </div>
        </div>
        
        <div class="action-buttons">
            <button class="generate-btn" id="generateBtn" onclick="gerarPauta()" disabled>
                <i class="fas fa-file-alt"></i> Gerar Pauta
            </button>
            <a href="dashboard.php" class="btn-voltar">← Voltar</a>
        </div>
    </div>

    <!-- ===== PAUTA ===== -->
    <div id="pautaResult" class="pauta-container">
        <!-- Conteúdo gerado dinamicamente -->
    </div>
</div>

<!-- ==========================================
     JAVASCRIPT
     ========================================== -->
<script>
// ============================================
// MODAL DE EDIÇÃO
// ============================================
function abrirModal() {
    document.getElementById('modalEditar').classList.add('active');
}

function fecharModal() {
    document.getElementById('modalEditar').classList.remove('active');
}

function salvarEdicao() {
    const linha1 = document.getElementById('edit_linha1').value;
    const linha2 = document.getElementById('edit_linha2').value;
    const linha3 = document.getElementById('edit_linha3').value;
    const linha4 = document.getElementById('edit_linha4').value;
    const dadosEmpresa = document.getElementById('edit_dados_empresa').value;
    const insignia = document.getElementById('edit_insignia').value || '🏛️';
    
    document.getElementById('linha1').innerHTML = linha1 + ' <span class="edit-icon">✎</span>';
    document.getElementById('linha2').innerHTML = linha2 + ' <span class="edit-icon">✎</span>';
    document.getElementById('linha3').innerHTML = linha3 + ' <span class="edit-icon">✎</span>';
    document.getElementById('linha4').innerHTML = '<span>' + linha4 + '</span> <span class="edit-icon">✎</span>';
    document.getElementById('dadosEmpresa').innerHTML = dadosEmpresa + ' <span class="edit-icon">✎</span>';
    
    atualizarInsignia(insignia);
    
    localStorage.setItem('pauta_linha1', linha1);
    localStorage.setItem('pauta_linha2', linha2);
    localStorage.setItem('pauta_linha3', linha3);
    localStorage.setItem('pauta_linha4', linha4);
    localStorage.setItem('pauta_dados_empresa', dadosEmpresa);
    localStorage.setItem('pauta_insignia', insignia);
    
    fecharModal();
}

function atualizarInsignia(conteudo) {
    const insigniaContainer = document.getElementById('insigniaConteudo');
    const previewContainer = document.getElementById('previewInsigniaConteudo');
    
    if (conteudo.match(/\.(jpeg|jpg|gif|png|webp)$/i) || conteudo.startsWith('data:image') || conteudo.startsWith('http')) {
        insigniaContainer.innerHTML = '<img src="' + conteudo + '" alt="Insígnia">';
        previewContainer.innerHTML = '<img src="' + conteudo + '" alt="Insígnia">';
        previewContainer.style.display = 'block';
    } else {
        insigniaContainer.innerHTML = conteudo;
        insigniaContainer.className = 'insignia-emoji';
        previewContainer.innerHTML = conteudo;
        previewContainer.className = 'preview-emoji';
    }
}

function importarInsignia() {
    document.getElementById('fileInput').click();
}

function uploadInsignia(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const imageUrl = e.target.result;
        document.getElementById('edit_insignia').value = imageUrl;
        atualizarInsignia(imageUrl);
    };
    reader.readAsDataURL(file);
}

// Carregar dados salvos
document.addEventListener('DOMContentLoaded', function() {
    const linha1Salva = localStorage.getItem('pauta_linha1');
    const linha2Salva = localStorage.getItem('pauta_linha2');
    const linha3Salva = localStorage.getItem('pauta_linha3');
    const linha4Salva = localStorage.getItem('pauta_linha4');
    const dadosEmpresaSalva = localStorage.getItem('pauta_dados_empresa');
    const insigniaSalva = localStorage.getItem('pauta_insignia');
    
    if (linha1Salva) {
        document.getElementById('linha1').innerHTML = linha1Salva + ' <span class="edit-icon">✎</span>';
        document.getElementById('edit_linha1').value = linha1Salva;
    }
    
    if (linha2Salva) {
        document.getElementById('linha2').innerHTML = linha2Salva + ' <span class="edit-icon">✎</span>';
        document.getElementById('edit_linha2').value = linha2Salva;
    }
    
    if (linha3Salva) {
        document.getElementById('linha3').innerHTML = linha3Salva + ' <span class="edit-icon">✎</span>';
        document.getElementById('edit_linha3').value = linha3Salva;
    }
    
    if (linha4Salva) {
        document.getElementById('linha4').innerHTML = '<span>' + linha4Salva + '</span> <span class="edit-icon">✎</span>';
        document.getElementById('edit_linha4').value = linha4Salva;
    }
    
    if (dadosEmpresaSalva) {
        document.getElementById('dadosEmpresa').innerHTML = dadosEmpresaSalva + ' <span class="edit-icon">✎</span>';
        document.getElementById('edit_dados_empresa').value = dadosEmpresaSalva;
    }
    
    if (insigniaSalva) {
        document.getElementById('edit_insignia').value = insigniaSalva;
        atualizarInsignia(insigniaSalva);
    }

    // Recuperar ano letivo salvo
    var anoSalvo = localStorage.getItem('pauta_ano_letivo');
    if (anoSalvo) {
        document.getElementById('anoLetivo').value = anoSalvo;
    }

    // Salvar ao alterar
    document.getElementById('anoLetivo').addEventListener('change', function() {
        localStorage.setItem('pauta_ano_letivo', this.value.trim());
    });
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModal();
    }
});

// Fechar modal clicando fora
document.getElementById('modalEditar').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModal();
    }
});

// ============================================
// VARIÁVEIS DA PAUTA
// ============================================
var disciplinasDisponiveis = [];
var disciplinasSelecionadas = [];
var dadosPauta = null;
var colunasOrdenadas = [];

var disciplinasServer = [
    <?php foreach ($disciplinas as $d): ?>
        '<?= addslashes($d) ?>',
    <?php endforeach; ?>
];

// ============================================
// FUNÇÕES DA PAUTA
// ============================================
function mostrarMensagem(tipo, texto) {
    var msg = document.getElementById('messageArea');
    msg.className = 'message ' + tipo;
    msg.textContent = texto;
    msg.style.display = 'block';
    
    if (tipo !== 'error') {
        setTimeout(function() {
            msg.style.display = 'none';
        }, 5000);
    }
}

function carregarDisciplinas() {
    var classe = document.getElementById('classe').value;
    var turma = document.getElementById('turma').value;
    var lista = document.getElementById('disciplinasList');
    var btn = document.getElementById('generateBtn');
    
    if (!classe || !turma) {
        lista.innerHTML = '<p style="color:#999;">Selecione uma classe e turma</p>';
        btn.disabled = true;
        return;
    }
    
    if (disciplinasServer && disciplinasServer.length > 0) {
        disciplinasDisponiveis = disciplinasServer;
    } else {
        disciplinasDisponiveis = ['Matemática', 'Língua Portuguesa', 'Biologia', 'Física', 'Química'];
    }
    
    lista.innerHTML = '';
    disciplinasDisponiveis.sort().forEach(function(disciplina) {
        if (!disciplina || disciplina.trim() === '') return;
        
        var div = document.createElement('div');
        div.className = 'disciplina-checkbox';
        var id = 'disc_' + disciplina.replace(/\s+/g, '_');
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.id = id;
        checkbox.value = disciplina;
        checkbox.checked = true;
        checkbox.addEventListener('change', atualizarSelecao);
        
        var label = document.createElement('label');
        label.htmlFor = id;
        label.textContent = disciplina.length > 25 ? disciplina.substring(0, 22) + '...' : disciplina;
        
        div.appendChild(checkbox);
        div.appendChild(label);
        lista.appendChild(div);
    });
    
    atualizarSelecao();
    btn.disabled = false;
}

function atualizarSelecao() {
    var checkboxes = document.querySelectorAll('#disciplinasList input[type="checkbox"]:checked');
    disciplinasSelecionadas = Array.from(checkboxes).map(function(cb) { return cb.value; });
    
    // Tentar carregar ordem salva
    var ordemSalva = localStorage.getItem('pauta_colunas_ordenadas');
    if (ordemSalva) {
        try {
            var ordem = JSON.parse(ordemSalva);
            var todasExistem = ordem.every(function(d) {
                return disciplinasSelecionadas.indexOf(d) !== -1;
            });
            if (todasExistem && ordem.length === disciplinasSelecionadas.length) {
                colunasOrdenadas = ordem;
            } else {
                colunasOrdenadas = disciplinasSelecionadas.slice();
            }
        } catch (e) {
            colunasOrdenadas = disciplinasSelecionadas.slice();
        }
    } else {
        colunasOrdenadas = disciplinasSelecionadas.slice();
    }
    
    document.getElementById('selectedCount').textContent = '(' + disciplinasSelecionadas.length + ' selecionadas)';
    document.getElementById('generateBtn').disabled = disciplinasSelecionadas.length === 0;
}

function selecionarTodas() {
    document.querySelectorAll('#disciplinasList input[type="checkbox"]').forEach(function(cb) {
        cb.checked = true;
    });
    atualizarSelecao();
}

function limparSelecao() {
    document.querySelectorAll('#disciplinasList input[type="checkbox"]').forEach(function(cb) {
        cb.checked = false;
    });
    atualizarSelecao();
}

function gerarPauta() {
    var anoLetivo = document.getElementById('anoLetivo').value.trim();
    var trimestre = document.getElementById('trimestre').value;
    var classe = document.getElementById('classe').value;
    var turma = document.getElementById('turma').value;
    
    if (!anoLetivo) {
        mostrarMensagem('error', 'Informe o Ano Letivo (ex: 2026/2027)');
        return;
    }
    
    if (!classe || !turma || disciplinasSelecionadas.length === 0) {
        mostrarMensagem('error', 'Preencha todos os campos e selecione pelo menos uma disciplina');
        return;
    }
    
    var btn = document.getElementById('generateBtn');
    btn.classList.add('loading');
    btn.disabled = true;
    
    var params = new URLSearchParams({
        action: 'buscar_dados',
        ano_letivo: anoLetivo,
        trimestre: trimestre,
        classe: classe,
        turma: turma,
        disciplinas: disciplinasSelecionadas.join(',')
    });
    
    var url = window.location.href + '?' + params.toString();
    
    fetch(url)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            btn.classList.remove('loading');
            btn.disabled = false;
            
            if (data.success) {
                dadosPauta = data;
                dadosPauta.trimestre = trimestre;
                dadosPauta.classe = classe;
                dadosPauta.turma = turma;
                dadosPauta.anoLetivo = anoLetivo;
                renderizarPauta(data);
                mostrarMensagem('success', 'Pauta gerada com sucesso! Ano Letivo: ' + anoLetivo);
            } else {
                mostrarMensagem('error', data.message || 'Nenhuma nota encontrada para o Ano Letivo ' + anoLetivo);
            }
        })
        .catch(function(error) {
            btn.classList.remove('loading');
            btn.disabled = false;
            console.error('Erro:', error);
            mostrarMensagem('error', 'Erro ao gerar pauta: ' + error.message);
        });
}

// ============================================
// DRAG AND DROP - MOVER COLUNAS
// ============================================
var draggingColumnIndex = null;
var draggingColumnName = null;

function initColumnDrag() {
    document.querySelectorAll('.pauta-table th[draggable="true"]').forEach(function(th) {
        th.removeEventListener('dragstart', handleDragStart);
        th.removeEventListener('dragover', handleDragOver);
        th.removeEventListener('dragenter', handleDragEnter);
        th.removeEventListener('dragleave', handleDragLeave);
        th.removeEventListener('drop', handleDrop);
        th.removeEventListener('dragend', handleDragEnd);
        
        th.addEventListener('dragstart', handleDragStart);
        th.addEventListener('dragover', handleDragOver);
        th.addEventListener('dragenter', handleDragEnter);
        th.addEventListener('dragleave', handleDragLeave);
        th.addEventListener('drop', handleDrop);
        th.addEventListener('dragend', handleDragEnd);
        
        if (!th.querySelector('.drag-handle')) {
            var handle = document.createElement('span');
            handle.className = 'drag-handle';
            handle.innerHTML = '⇕';
            th.appendChild(handle);
        }
    });
}

function handleDragStart(e) {
    draggingColumnIndex = parseInt(e.target.dataset.colIndex);
    draggingColumnName = e.target.dataset.disciplina;
    e.target.classList.add('dragging');
    e.dataTransfer.setData('text/plain', draggingColumnIndex);
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function handleDragEnter(e) {
    e.preventDefault();
    e.target.classList.add('drag-over');
}

function handleDragLeave(e) {
    e.target.classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    e.target.classList.remove('drag-over');
    
    var fromIndex = parseInt(e.dataTransfer.getData('text/plain'));
    var toIndex = parseInt(e.target.dataset.colIndex);
    
    if (fromIndex === toIndex || isNaN(fromIndex) || isNaN(toIndex)) return;
    
    var fixedColumns = 3;
    if (fromIndex < fixedColumns || toIndex < fixedColumns) return;
    
    var disciplinaFromIndex = fromIndex - fixedColumns;
    var disciplinaToIndex = toIndex - fixedColumns;
    
    var disciplinaFrom = colunasOrdenadas[disciplinaFromIndex];
    var disciplinaTo = colunasOrdenadas[disciplinaToIndex];
    
    colunasOrdenadas[disciplinaFromIndex] = disciplinaTo;
    colunasOrdenadas[disciplinaToIndex] = disciplinaFrom;
    
    localStorage.setItem('pauta_colunas_ordenadas', JSON.stringify(colunasOrdenadas));
    
    if (dadosPauta) {
        renderizarPauta(dadosPauta);
    }
}

function handleDragEnd(e) {
    e.target.classList.remove('dragging');
    document.querySelectorAll('.pauta-table th').forEach(function(th) {
        th.classList.remove('drag-over', 'drag-target');
    });
    draggingColumnIndex = null;
    draggingColumnName = null;
}

function resetarOrdemColunas() {
    if (!confirm('Deseja resetar a ordem das colunas para a ordem original?')) return;
    
    colunasOrdenadas = disciplinasSelecionadas.slice();
    localStorage.removeItem('pauta_colunas_ordenadas');
    
    if (dadosPauta) {
        renderizarPauta(dadosPauta);
    }
}

// ============================================
// RENDERIZAR PAUTA
// ============================================
function renderizarPauta(data) {
    var container = document.getElementById('pautaResult');
    container.style.display = 'block';
    
    var alunos = data.alunos || [];
    var trimestre = data.trimestre || 1;
    var classe = data.classe || '';
    var turma = data.turma || '';
    var anoLetivo = data.ano_letivo || data.anoLetivo || '-';
    
    if (alunos.length === 0) {
        container.innerHTML = `
            <div class="pauta-header">
                <h3>PAUTA DO ${trimestre}º TRIMESTRE</h3>
                <p><strong>Ano Letivo:</strong> ${anoLetivo}</p>
                <p>${classe}ª CLASSE - TURMA ${turma}</p>
                <p style="color:#e74c3c;">Nenhum aluno encontrado</p>
            </div>
        `;
        return;
    }
    
    var stats = calcularStats(alunos);
    var totalAprovados = alunos.filter(function(a) { return a.situacao === 'TRANSITA'; }).length;
    var totalReprovados = alunos.filter(function(a) { return a.situacao === 'NÃO TRANSITA'; }).length;
    var totalDesistentes = alunos.filter(function(a) { return a.situacao === 'DESISTENTE'; }).length;
    
    var html = `
        <div class="action-bar no-print">
            <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
            <button class="btn-excel" onclick="exportarExcel()"><i class="fas fa-file-excel"></i> Excel</button>
            <button class="btn-reset" onclick="resetarOrdemColunas()"><i class="fas fa-undo"></i> Resetar Ordem</button>
        </div>
        <div class="pauta-header">
            <h3>PAUTA DO ${trimestre}º TRIMESTRE</h3>
            <p><strong>Ano Letivo:</strong> ${anoLetivo}</p>
            <p>${classe}ª CLASSE - TURMA ${turma}</p>
            <p class="subtitulo">Regras: Média ≥ 5.0 = TRANSITA | 1.2 a 4.9 = NÃO TRANSITA | &lt; 1.2 = DESISTENTE</p>
            <p style="font-size:11px;color:#666;margin-top:5px;">
                <i class="fas fa-arrows-alt-h"></i> Arraste as colunas de disciplinas para reordenar
            </p>
        </div>
        
        <div class="total-alunos-info">
            Total de Alunos: ${alunos.length} | TRANSITA: ${totalAprovados} | NÃO TRANSITA: ${totalReprovados} | DESISTENTE: ${totalDesistentes}
        </div>
        
        <div class="pauta-table-container">
            <table class="pauta-table" id="pautaTable">
                <thead>
                    <tr>
                        <th rowspan="2" draggable="false" data-col-index="0">Nº</th>
                        <th rowspan="2" draggable="false" data-col-index="1" style="text-align:left;min-width:150px;">NOME DO ALUNO</th>
                        <th rowspan="2" draggable="false" data-col-index="2">SEXO</th>
                        <th colspan="${colunasOrdenadas.length}">NOTAS DO ${trimestre}º TRIMESTRE</th>
                        <th rowspan="2" draggable="false" data-col-index="${colunasOrdenadas.length + 3}">MÉDIA</th>
                        <th rowspan="2" draggable="false" data-col-index="${colunasOrdenadas.length + 4}">SITUAÇÃO</th>
                    </tr>
                    <tr>
        `;
    
    colunasOrdenadas.forEach(function(disciplina, index) {
        var colIndex = index + 3;
        html += `<th draggable="true" data-col-index="${colIndex}" data-disciplina="${disciplina}" title="Arraste para reordenar">${abreviarNome(disciplina, 10)}</th>`;
    });
    
    html += `</tr></thead><tbody>`;
    
    alunos.forEach(function(aluno, index) {
        var media = parseFloat(aluno.media || 0).toFixed(1);
        var situacaoClass = getSituacaoClass(aluno.situacao);
        var sexoDisplay = getSexoDisplay(aluno.sexo);
        
        html += `
            <tr>
                <td>${index + 1}</td>
                <td class="aluno-nome">${aluno.nome}</td>
                <td>${sexoDisplay}</td>
        `;
        
        colunasOrdenadas.forEach(function(disciplina) {
            var nota = aluno.notas ? aluno.notas[disciplina] : null;
            if (nota === undefined || nota === null || nota === '' || isNaN(parseFloat(nota))) {
                html += `<td>-</td>`;
            } else {
                var notaNum = parseFloat(nota);
                var notaClass = notaNum >= 5.0 ? 'positiva' : 'negativa';
                html += `<td class="${notaClass}">${notaNum.toFixed(1)}</td>`;
            }
        });
        
        html += `
                <td class="media-destaque">${media}</td>
                <td class="${situacaoClass}">${aluno.situacao || 'N/D'}</td>
            </tr>
        `;
    });
    
    html += `</tbody></table></div>`;
    
    // Estatísticas
    html += `
        <div class="stats-grid">
            <div class="stats-card">
                <h5>SITUAÇÃO ACADÉMICA</h5>
                <p><span class="label">Total de Alunos:</span> <span class="value">${stats.total}</span></p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:${stats.percAprov}%;">${stats.percAprov}% Aprovados</div>
                </div>
                <p><span class="label">✓ TRANSITA (Média ≥ 5.0):</span> <span class="value" style="color:#27ae60;">${stats.aprovados} (${stats.percAprov}%)</span></p>
                <p><span class="label">✗ NÃO TRANSITA (1.2 - 4.9):</span> <span class="value" style="color:#e74c3c;">${stats.reprovados} (${stats.percReprov}%)</span></p>
                <p><span class="label">● DESISTENTE (Média < 1.2):</span> <span class="value" style="color:#f39c12;">${stats.desistentes} (${stats.percDesist}%)</span></p>
                <p><span class="label">Média Geral da Turma:</span> <span class="value">${stats.mediaGeral}</span></p>
            </div>
            <div class="stats-card">
                <h5>ESTATÍSTICAS POR DISCIPLINA</h5>
                ${colunasOrdenadas.map(function(disciplina) {
                    var discStats = calcularStatsDisciplina(alunos, disciplina);
                    return `
                        <p><span class="label">${disciplina}:</span> 
                            <span style="color:#27ae60;">${discStats.positivas} ✓</span> | 
                            <span style="color:#e74c3c;">${discStats.negativas} ✗</span> | 
                            Média: ${discStats.media.toFixed(1)}
                        </p>
                    `;
                }).join('')}
            </div>
        </div>
    `;
    
    // Legenda
    html += `
        <div class="legenda">
            <h5>📋 LEGENDA</h5>
            <div class="legenda-grid">
                <div class="legenda-item"><span class="cor cor-positiva"></span> POSITIVA: Nota ≥ 5.0</div>
                <div class="legenda-item"><span class="cor cor-negativa"></span> NEGATIVA: Nota < 5.0</div>
                <div class="legenda-item"><span class="cor cor-aprovado"></span> TRANSITA: Média ≥ 5.0</div>
                <div class="legenda-item"><span class="cor cor-reprovado"></span> NÃO TRANSITA: 1.2 ≤ Média < 5.0</div>
                <div class="legenda-item"><span class="cor cor-desistente"></span> DESISTENTE: Média < 1.2</div>
            </div>
        </div>
    `;
    
    // Assinaturas
    html += `
        <div class="assinaturas">
            <div class="assinatura-item">
                <div class="linha-assinatura"></div>
                <div class="assinatura-nome">_________________________</div>
                <div class="assinatura-cargo">Diretor de Turma</div>
            </div>
            <div class="assinatura-item">
                <div class="linha-assinatura"></div>
                <div class="assinatura-nome">_________________________</div>
                <div class="assinatura-cargo">Subdiretor Pedagógico</div>
            </div>
        </div>
    `;
    
    // Rodapé
    html += `
        <div class="rodape">
            <div>📅 Data: ${new Date().toLocaleDateString('pt-BR')} ${new Date().toLocaleTimeString('pt-BR')}</div>
            <div>👨‍🏫 Professor: <?= htmlspecialchars($usuario_nome) ?></div>
        </div>
    `;
    
    container.innerHTML = html;
    
    setTimeout(function() {
        initColumnDrag();
    }, 100);
    
    container.scrollIntoView({ behavior: 'smooth' });
}

function calcularStats(alunos) {
    var total = alunos.length;
    var aprovados = alunos.filter(function(a) { return a.situacao === 'TRANSITA'; }).length;
    var reprovados = alunos.filter(function(a) { return a.situacao === 'NÃO TRANSITA'; }).length;
    var desistentes = alunos.filter(function(a) { return a.situacao === 'DESISTENTE'; }).length;
    
    var medias = alunos
        .filter(function(a) { return a.situacao !== 'DESISTENTE' && a.media > 0; })
        .map(function(a) { return parseFloat(a.media); });
    
    var mediaGeral = medias.length > 0 ? (medias.reduce(function(a, b) { return a + b; }, 0) / medias.length).toFixed(1) : '0.0';
    
    var percAprov = total > 0 ? ((aprovados / total) * 100).toFixed(1) : 0;
    var percReprov = total > 0 ? ((reprovados / total) * 100).toFixed(1) : 0;
    var percDesist = total > 0 ? ((desistentes / total) * 100).toFixed(1) : 0;
    
    return { total, aprovados, reprovados, desistentes, mediaGeral, percAprov, percReprov, percDesist };
}

function calcularStatsDisciplina(alunos, disciplina) {
    var positivas = 0;
    var negativas = 0;
    var soma = 0;
    var count = 0;
    
    alunos.forEach(function(aluno) {
        if (aluno.situacao === 'DESISTENTE') return;
        
        var nota = parseFloat(aluno.notas ? aluno.notas[disciplina] : null);
        if (!isNaN(nota)) {
            soma += nota;
            count++;
            if (nota >= 5.0) {
                positivas++;
            } else {
                negativas++;
            }
        }
    });
    
    var media = count > 0 ? soma / count : 0;
    return { positivas, negativas, media };
}

function getSituacaoClass(situacao) {
    switch(situacao) {
        case 'TRANSITA': return 'situacao-aprovado';
        case 'NÃO TRANSITA': return 'situacao-reprovado';
        case 'DESISTENTE': return 'situacao-desistente';
        default: return '';
    }
}

function getSexoDisplay(sexo) {
    if (!sexo) return 'N/I';
    var s = sexo.toUpperCase();
    if (s === 'M' || s === 'MASCULINO') return '👨 M';
    if (s === 'F' || s === 'FEMININO') return '👩 F';
    return sexo;
}

function abreviarNome(nome, maxLength) {
    if (!nome || nome.length <= maxLength) return nome;
    return nome.substring(0, maxLength - 2) + '..';
}

function exportarExcel() {
    if (!dadosPauta) {
        mostrarMensagem('error', 'Gere uma pauta primeiro');
        return;
    }
    mostrarMensagem('success', 'Função de exportação Excel em desenvolvimento...');
}

// ============================================
// INICIALIZAR
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('classe').addEventListener('change', carregarDisciplinas);
    document.getElementById('turma').addEventListener('change', carregarDisciplinas);
    
    var classe = document.getElementById('classe').value;
    var turma = document.getElementById('turma').value;
    if (classe && turma) {
        carregarDisciplinas();
    }
});
</script>

</body>
</html>