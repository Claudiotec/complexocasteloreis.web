<?php
// ============================================
// modules/escola/pedagogico/pautas/index.php
// Pautas Trimestrais - BUSCANDO NA TABELA distribuicao_professores
// ============================================

// Carregar configurações
require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ============================================
// DADOS DO USUÁRIO LOGADO
// ============================================
$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_email = $_SESSION['usuario_email'] ?? '';
$usuario_cargo = $_SESSION['cargo'] ?? 'Professor';
$usuario_tipo = $_SESSION['tipo'] ?? 'professor';

// ============================================
// BUSCAR DADOS DO USUÁRIO
// ============================================
$userEscola = 'ESCOLA EXEMPLO';
$isAdminOrCoordenador = false;

try {
    $stmt = $pdo->prepare("
        SELECT u.*, e.nome as escola_nome 
        FROM usuarios u
        LEFT JOIN config_escola e ON u.escola_id = e.id
        WHERE u.id = ?
    ");
    $stmt->execute([$usuario_id]);
    $usuarioData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuarioData) {
        $usuario_nome = $usuarioData['nome'] ?? $usuario_nome;
        $usuario_email = $usuarioData['email'] ?? $usuario_email;
        $usuario_cargo = $usuarioData['cargo'] ?? $usuario_cargo;
        $usuario_tipo = $usuarioData['tipo'] ?? 'professor';
        $userEscola = $usuarioData['escola_nome'] ?? 'ESCOLA EXEMPLO';
        $isAdminOrCoordenador = in_array($usuario_tipo, ['admin', 'coordenador', 'diretor']);
    }
} catch (Exception $e) {
    error_log("Erro ao buscar dados do usuário: " . $e->getMessage());
}

// ============================================
// BUSCAR TODAS AS DISCIPLINAS CADASTRADAS
// ============================================
$todasDisciplinas = [];
try {
    $stmt = $pdo->query("SELECT id, nome, codigo FROM disciplinas ORDER BY nome");
    $todasDisciplinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar todas as disciplinas: " . $e->getMessage());
}

// ============================================
// BUSCAR DADOS DA TABELA distribuicao_professores
// ============================================
$disciplinasProfessor = [];
$userClasses = [];
$userTurmas = [];
$distribuicoesData = [];

try {
    // Verificar se a tabela distribuicao_professores existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'distribuicao_professores'");
    if ($stmt->rowCount() > 0) {
        
        // Buscar distribuições do professor pelo nome
        $stmt = $pdo->prepare("
            SELECT d.* 
            FROM distribuicao_professores d
            WHERE d.professor_nome = ?
            ORDER BY d.classe, d.turma_nome
        ");
        $stmt->execute([$usuario_nome]);
        $distribuicoesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Se não encontrou pelo nome, tentar pelo professor_id
        if (empty($distribuicoesData)) {
            $stmt = $pdo->prepare("
                SELECT d.* 
                FROM distribuicao_professores d
                WHERE d.professor_id = ?
                ORDER BY d.classe, d.turma_nome
            ");
            $stmt->execute([$usuario_id]);
            $distribuicoesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Extrair disciplinas únicas da distribuição
        $disciplinasSet = [];
        $classesSet = [];
        $turmasSet = [];
        
        foreach ($distribuicoesData as $dist) {
            // Extrair disciplinas
            if (!empty($dist['disciplinas'])) {
                $discs = array_map('trim', explode(',', $dist['disciplinas']));
                foreach ($discs as $disc) {
                    if (!empty($disc)) {
                        $disciplinasSet[$disc] = $disc;
                    }
                }
            }
            
            // Extrair classes
            if (!empty($dist['classe'])) {
                $classesSet[$dist['classe']] = $dist['classe'];
            }
            
            // Extrair turmas
            if (!empty($dist['turma_nome'])) {
                $turmasSet[$dist['turma_nome']] = $dist['turma_nome'];
            }
        }
        
        // Converter para arrays
        $disciplinasNomes = array_values($disciplinasSet);
        $userClasses = array_values($classesSet);
        $userTurmas = array_values($turmasSet);
        
        // Buscar IDs das disciplinas na tabela disciplinas
        if (!empty($disciplinasNomes)) {
            $placeholders = implode(',', array_fill(0, count($disciplinasNomes), '?'));
            $stmt = $pdo->prepare("
                SELECT id, nome, codigo 
                FROM disciplinas 
                WHERE nome IN ($placeholders)
                ORDER BY nome
            ");
            $stmt->execute($disciplinasNomes);
            $disciplinasProfessor = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } else {
        // Tabela não existe, usar fallback
        error_log("Tabela distribuicao_professores não encontrada");
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar dados da distribuição: " . $e->getMessage());
}

// ============================================
// FALLBACK: Se não encontrou na distribuição, buscar da tabela professor_disciplina
// ============================================
if (empty($disciplinasProfessor)) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.id, d.nome, d.codigo 
            FROM professor_disciplina pd
            JOIN disciplinas d ON pd.disciplina_id = d.id
            WHERE pd.professor_id = ?
            ORDER BY d.nome
        ");
        $stmt->execute([$usuario_id]);
        $disciplinasProfessor = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao buscar disciplinas do professor: " . $e->getMessage());
    }
}

// ============================================
// FALLBACK 2: Se ainda não tem disciplinas, usar dados mockados
// ============================================
if (empty($disciplinasProfessor)) {
    $disciplinasProfessor = [
        ['id' => 1, 'nome' => 'Matemática', 'codigo' => 'MAT'],
        ['id' => 2, 'nome' => 'Português', 'codigo' => 'POR'],
        ['id' => 3, 'nome' => 'História', 'codigo' => 'HIS'],
        ['id' => 4, 'nome' => 'Geografia', 'codigo' => 'GEO'],
        ['id' => 5, 'nome' => 'Ciências', 'codigo' => 'CIE']
    ];
}

// ============================================
// FALLBACK: Se não tem classes ou turmas, buscar das turmas
// ============================================
if (empty($userClasses) || empty($userTurmas)) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT classe, nome as turma_nome
            FROM turmas
            WHERE professor_id = ? OR coordenador_id = ?
            ORDER BY classe, nome
        ");
        $stmt->execute([$usuario_id, $usuario_id]);
        $turmasData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($turmasData as $t) {
            if (!empty($t['classe'])) {
                $userClasses[] = $t['classe'];
            }
            if (!empty($t['turma_nome'])) {
                $userTurmas[] = $t['turma_nome'];
            }
        }
        
        $userClasses = array_unique($userClasses);
        $userTurmas = array_unique($userTurmas);
    } catch (Exception $e) {
        error_log("Erro ao buscar turmas: " . $e->getMessage());
    }
}

// ============================================
// SE AINDA ESTIVER VAZIO, USAR DADOS MOCKADOS
// ============================================
if (empty($userClasses)) {
    $userClasses = ['6', '7'];
}

if (empty($userTurmas)) {
    $userTurmas = ['A', 'B'];
}

// ============================================
// DADOS PARA O JAVASCRIPT (JSON)
// ============================================
$dadosJS = [
    'todasDisciplinas' => $todasDisciplinas,
    'disciplinasProfessor' => $disciplinasProfessor,
    'userClasses' => $userClasses,
    'userTurmas' => $userTurmas,
    'distribuicoes' => $distribuicoesData,
    'isAdmin' => $isAdminOrCoordenador,
    'usuarioNome' => $usuario_nome,
    'usuarioCargo' => $usuario_cargo,
    'usuarioTipo' => $usuario_tipo,
    'usuarioId' => $usuario_id,
    'escola' => $userEscola
];

// ===== INCLUIR HEADER =====
include '../../includes/header_escola.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($userEscola) ?> - Pautas Trimestrais</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           ESTILOS COMPLETOS (mantidos)
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        .pauta-page { padding: 20px 30px; max-width: 1400px; margin: 0 auto; }
        
        .pauta-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pauta-page-header h1 {
            font-size: 24px;
            font-weight: 800;
            color: #1a2332;
        }
        
        .pauta-page-header h1 i { color: #c9a84c; margin-right: 10px; }
        .pauta-page-header .subtitle { color: #94a3b8; font-size: 14px; }
        
        .pauta-user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 8px 18px;
            border-radius: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            flex-wrap: wrap;
        }
        
        .pauta-user-badge {
            background: #c9a84c;
            color: #1a2332;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        
        /* TOGGLE */
        .toggle-container {
            display: flex;
            align-items: center;
            gap: 15px;
            background: #f8fafc;
            padding: 12px 20px;
            border-radius: 10px;
            border: 1px solid #eef2f7;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .toggle-container .label {
            font-weight: 600;
            color: #1a2332;
            font-size: 14px;
        }
        
        .toggle-container .desc {
            color: #94a3b8;
            font-size: 13px;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 28px;
            flex-shrink: 0;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background: #c9a84c;
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }
        
        .toggle-switch input:disabled + .toggle-slider {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .toggle-status {
            font-size: 13px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .toggle-status.professor-mode {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .toggle-status.all-mode {
            background: #d1fae5;
            color: #065f46;
        }
        
        /* FILTROS */
        .pauta-filters {
            background: white;
            padding: 25px 30px;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin-bottom: 25px;
            border: 1px solid #eef2f7;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #1a2332;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px 14px;
            border: 2px solid #eef2f7;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #fafbfc;
            color: #1a2332;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: #c9a84c;
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
        }
        
        /* DISCIPLINAS */
        .disciplinas-section { margin-top: 15px; }
        
        .disciplinas-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .disciplinas-header label {
            font-weight: 600;
            color: #1a2332;
            font-size: 14px;
        }
        
        .disciplinas-header .selected-count {
            color: #94a3b8;
            font-size: 13px;
            font-weight: 400;
        }
        
        .disciplinas-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 5px 14px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-select-all {
            background: #3498db;
            color: white;
        }
        .btn-select-all:hover { background: #2980b9; }
        
        .btn-clear-all {
            background: #e74c3c;
            color: white;
        }
        .btn-clear-all:hover { background: #c0392b; }
        
        .disciplinas-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 2px solid #eef2f7;
            max-height: 220px;
            overflow-y: auto;
            min-height: 60px;
        }
        
        .disciplina-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            padding: 6px 14px 6px 10px;
            border-radius: 20px;
            border: 2px solid #eef2f7;
            transition: all 0.3s;
            cursor: pointer;
            font-size: 13px;
        }
        
        .disciplina-checkbox:hover {
            border-color: #c9a84c;
            background: #fefbf5;
        }
        
        .disciplina-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #c9a84c;
            cursor: pointer;
        }
        
        .disciplina-checkbox label {
            cursor: pointer;
            color: #1a2332;
        }
        
        .disciplina-checkbox .codigo {
            font-size: 10px;
            color: #94a3b8;
            background: #f1f5f9;
            padding: 1px 8px;
            border-radius: 10px;
        }
        
        .no-disciplinas {
            color: #94a3b8;
            padding: 15px;
            text-align: center;
            width: 100%;
        }
        
        .no-disciplinas .icon {
            font-size: 30px;
            display: block;
            margin-bottom: 8px;
        }
        
        /* BOTÃO GERAR */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-generate {
            background: #c9a84c;
            color: #1a2332;
            border: none;
            padding: 12px 32px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-generate:hover:not(:disabled) {
            background: #b8973a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(201, 168, 76, 0.3);
        }
        
        .btn-generate:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-generate.loading {
            position: relative;
            color: transparent;
        }
        
        .btn-generate.loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-top: -10px;
            margin-left: -10px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1a2332;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* MESSAGE */
        .pauta-message {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: none;
            font-weight: 500;
        }
        
        .pauta-message.error {
            display: block;
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .pauta-message.success {
            display: block;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .pauta-message.info {
            display: block;
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        /* PAUTA RESULT */
        .pauta-result {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            padding: 30px;
            margin-top: 25px;
            border: 1px solid #eef2f7;
            display: none;
            overflow-x: auto;
        }
        
        .pauta-result .header-controls {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
            gap: 10px;
        }
        
        .btn-reset-columns {
            background: #6c757d;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        .btn-reset-columns:hover { background: #5a6268; }
        
        .pauta-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #1a2332;
            padding-bottom: 20px;
        }
        
        .insignia-container {
            display: flex;
            justify-content: center;
            margin-bottom: 10px;
            min-height: 80px;
        }
        
        .insignia-img {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
            cursor: pointer;
            border: 2px dashed #ccc;
            padding: 5px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .insignia-img:hover {
            border-color: #c9a84c;
            background: #fefbf5;
        }
        
        .pauta-header .republika,
        .pauta-header .ministerio,
        .pauta-header .direcao {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 2px 0;
            cursor: pointer;
            padding: 2px 8px;
            border-radius: 4px;
            transition: all 0.2s;
            color: #1a2332;
        }
        
        .pauta-header .republika:hover,
        .pauta-header .ministerio:hover,
        .pauta-header .direcao:hover,
        .pauta-header .escola-nome:hover {
            background: #f0f0f0;
            border: 1px dashed #c9a84c;
        }
        
        .pauta-header .escola-nome {
            font-size: 20px;
            font-weight: 800;
            color: #1a2332;
            text-transform: uppercase;
            margin: 10px 0 5px;
            cursor: pointer;
            padding: 3px 12px;
            border-radius: 6px;
            transition: all 0.3s;
            display: inline-block;
            border: 2px solid transparent;
        }
        
        .pauta-header h3 {
            color: #1a2332;
            margin: 10px 0 5px;
            font-size: 20px;
        }
        
        .pauta-header p {
            color: #64748b;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .edit-input {
            width: 100%;
            padding: 3px 8px;
            border: 2px solid #c9a84c;
            border-radius: 4px;
            font-size: inherit;
            font-weight: inherit;
            text-align: center;
            text-transform: uppercase;
            background: white;
        }
        
        .total-alunos-info {
            background: #e8f4fd;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 700;
            color: #1a2332;
        }
        
        .pauta-table-container {
            overflow-x: auto;
            margin: 15px 0;
            border: 1px solid #eef2f7;
            border-radius: 10px;
        }
        
        .pauta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 800px;
        }
        
        .pauta-table th {
            background: #1a2332;
            color: white;
            padding: 10px 8px;
            text-align: center;
            border: 1px solid #2d3748;
            white-space: nowrap;
            cursor: move;
            user-select: none;
        }
        
        .pauta-table th:hover { background: #2d3748; }
        .pauta-table th.dragging {
            opacity: 0.5;
            background: #c9a84c;
        }
        
        .pauta-table td {
            padding: 8px 6px;
            border: 1px solid #eef2f7;
            text-align: center;
        }
        
        .pauta-table tr:nth-child(even) { background: #fafbfc; }
        .pauta-table tr:hover { background: #fefbf5; }
        
        .situacao-aprovado {
            background: #d1fae5 !important;
            color: #065f46;
            font-weight: 700;
        }
        
        .situacao-reprovado {
            background: #fee2e2 !important;
            color: #991b1b;
            font-weight: 700;
        }
        
        .situacao-desistente {
            background: #fef3c7 !important;
            color: #92400e;
            font-weight: 700;
        }
        
        .positiva {
            color: #065f46;
            font-weight: 700;
        }
        
        .negativa {
            color: #991b1b;
            font-weight: 700;
        }
        
        .media-destaque {
            font-weight: 700;
            color: #1a2332;
        }
        
        .pauta-action-bar {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-bottom: 20px;
            flex-wrap: wrap;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #eef2f7;
        }
        
        .btn-print {
            background: #64748b;
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover { background: #475569; }
        
        .btn-excel {
            background: #217346;
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-excel:hover { background: #1a5e38; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stats-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 18px 22px;
            border: 1px solid #eef2f7;
        }
        
        .stats-card h5 {
            color: #1a2332;
            margin-bottom: 12px;
            border-bottom: 2px solid #c9a84c;
            padding-bottom: 8px;
            font-size: 15px;
        }
        
        .stats-card p { margin: 5px 0; font-size: 14px; color: #1a2332; }
        
        .stats-card table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .stats-card table th {
            background: #eef2f7;
            padding: 6px 8px;
            border: 1px solid #ddd;
            text-align: center;
            font-weight: 600;
        }
        
        .stats-card table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .progress-bar {
            height: 22px;
            background: #eef2f7;
            border-radius: 12px;
            overflow: hidden;
            margin: 6px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #c9a84c;
            color: #1a2332;
            font-size: 12px;
            font-weight: 700;
            line-height: 22px;
            text-align: center;
            transition: width 1s ease;
        }
        
        .gender-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .gender-card {
            background: white;
            border-radius: 10px;
            padding: 18px 22px;
            border: 1px solid #eef2f7;
        }
        
        .gender-card.masculino { border-left: 4px solid #3498db; }
        .gender-card.feminino { border-left: 4px solid #e83e8c; }
        
        .gender-card h6 {
            color: #1a2332;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            font-weight: 700;
        }
        
        .gender-card p { margin: 4px 0; font-size: 13px; }
        
        .pauta-legenda {
            margin-top: 25px;
            padding: 18px 22px;
            background: #e8f4fd;
            border-radius: 10px;
            border-left: 4px solid #c9a84c;
        }
        
        .pauta-legenda h5 { margin-bottom: 10px; color: #1a2332; }
        .pauta-legenda .legend-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;
        }
        .pauta-legenda .legend-item { font-size: 13px; }
        
        .assinaturas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eef2f7;
        }
        
        .assinatura-item { text-align: center; }
        .linha-assinatura {
            margin-top: 30px;
            border-top: 1px solid #1a2332;
            width: 100%;
        }
        .assinatura-nome {
            margin-top: 6px;
            font-weight: 700;
            font-size: 13px;
            color: #1a2332;
        }
        .assinatura-cargo {
            font-size: 12px;
            color: #64748b;
        }
        
        .pauta-rodape {
            margin-top: 25px;
            padding-top: 12px;
            border-top: 1px solid #eef2f7;
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .debug-info {
            background: #f8f9fa;
            padding: 10px 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            font-size: 12px;
            border: 1px solid #dee2e6;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .debug-info .badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 11px;
        }
        .debug-info .badge-success { background: #d1fae5; color: #065f46; }
        .debug-info .badge-info { background: #dbeafe; color: #1e40af; }
        .debug-info .badge-warning { background: #fef3c7; color: #92400e; }
        .debug-info .badge-danger { background: #fee2e2; color: #991b1b; }
        .debug-info .badge-dark { background: #1a2332; color: #fff; }
        
        @media (max-width: 768px) {
            .pauta-page { padding: 15px; }
            .pauta-filters { padding: 18px; }
            .filter-row { grid-template-columns: 1fr; gap: 12px; }
            .pauta-result { padding: 18px; }
            .toggle-container {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .pauta-page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .pauta-user-info {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media print {
            @page { size: A4 landscape; margin: 0.8cm; }
            .pauta-page-header,
            .pauta-filters,
            .pauta-action-bar,
            .header-controls,
            .btn-reset-columns,
            .btn-print,
            .btn-excel,
            .debug-info,
            .toggle-container { display: none !important; }
            .pauta-result {
                box-shadow: none;
                padding: 0.3cm;
                margin: 0;
                border: none;
                display: block !important;
            }
            .pauta-table { font-size: 10px; min-width: auto; }
            .pauta-table th {
                background: #1a2332 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .situacao-aprovado,
            .situacao-reprovado,
            .situacao-desistente,
            .positiva,
            .negativa {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="pauta-page">
        <!-- Header -->
        <div class="pauta-page-header">
            <div>
                <h1><i class="fas fa-file-alt"></i> Pautas Trimestrais</h1>
                <div class="subtitle">Gestão de pautas e avaliações trimestrais</div>
            </div>
            <div class="pauta-user-info">
                <span class="pauta-user-badge">
                    <i class="fas fa-user-graduate"></i> <?= htmlspecialchars($usuario_cargo) ?>
                </span>
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($usuario_nome) ?></span>
                <?php if ($isAdminOrCoordenador): ?>
                    <span style="background: #dbeafe; padding: 3px 12px; border-radius: 12px; font-size: 11px; color: #1e40af;">
                        <i class="fas fa-crown"></i> Admin
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Debug Info -->
        <div class="debug-info">
            <span><strong>🔍 Debug:</strong></span>
            <span class="badge badge-dark">ID: <?= $usuario_id ?></span>
            <span class="badge badge-info">Nome: <?= htmlspecialchars($usuario_nome) ?></span>
            <span class="badge badge-warning">Cargo: <?= htmlspecialchars($usuario_cargo) ?></span>
            <span class="badge <?= $isAdminOrCoordenador ? 'badge-success' : 'badge-warning' ?>">
                <?= $isAdminOrCoordenador ? 'Admin' : 'Professor' ?>
            </span>
            <span>Disciplinas: <strong><?= count($todasDisciplinas) ?></strong> (total) | <strong><?= count($disciplinasProfessor) ?></strong> (professor)</span>
            <span>Classes: <strong><?= count($userClasses) ?></strong></span>
            <span>Turmas: <strong><?= count($userTurmas) ?></strong></span>
            <span>Distribuições: <strong><?= count($distribuicoesData) ?></strong></span>
        </div>

        <!-- Toggle -->
        <div class="toggle-container" id="toggleContainer">
            <span class="label"><i class="fas fa-filter"></i> Modo de exibição:</span>
            <label class="toggle-switch">
                <input type="checkbox" id="toggleModoProfessor" 
                       <?= !$isAdminOrCoordenador ? 'checked' : '' ?>
                       <?= !$isAdminOrCoordenador ? 'disabled' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
            <span class="toggle-status <?= !$isAdminOrCoordenador ? 'professor-mode' : 'all-mode' ?>" id="toggleStatus">
                <i class="fas <?= !$isAdminOrCoordenador ? 'fa-chalkboard-teacher' : 'fa-globe' ?>"></i>
                <?= !$isAdminOrCoordenador ? 'Minhas disciplinas' : 'Todas disciplinas' ?>
            </span>
            <span class="desc">
                <?php if ($isAdminOrCoordenador): ?>
                    <i class="fas fa-toggle-on" style="color:#c9a84c;"></i> 
                    <strong>Clique no switch</strong> para alternar entre 
                    <strong>"Todas disciplinas"</strong> e <strong>"Minhas disciplinas"</strong>
                <?php else: ?>
                    <i class="fas fa-lock"></i> Modo professor - apenas suas disciplinas
                <?php endif; ?>
            </span>
        </div>

        <!-- Área de mensagem -->
        <div id="pautaMessage" class="pauta-message"></div>

        <!-- Filtros -->
        <div class="pauta-filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="trimestre"><i class="fas fa-calendar-alt"></i> Trimestre:</label>
                    <select id="trimestre">
                        <option value="1">1º Trimestre</option>
                        <option value="2">2º Trimestre</option>
                        <option value="3">3º Trimestre</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="classe"><i class="fas fa-layer-group"></i> Classe:</label>
                    <select id="classe">
                        <option value="">Selecione a classe</option>
                        <?php 
                        $classesExibidas = [];
                        foreach ($userClasses as $classe): 
                            if (!in_array($classe, $classesExibidas)):
                                $classesExibidas[] = $classe;
                        ?>
                            <option value="<?= htmlspecialchars($classe) ?>"><?= htmlspecialchars($classe) ?>ª Classe</option>
                        <?php endif; endforeach; ?>
                        <?php if (empty($userClasses)): ?>
                            <option value="6">6ª Classe</option>
                            <option value="7">7ª Classe</option>
                            <option value="8">8ª Classe</option>
                            <option value="9">9ª Classe</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="turma"><i class="fas fa-users"></i> Turma:</label>
                    <select id="turma">
                        <option value="">Selecione a turma</option>
                        <?php 
                        $turmasExibidas = [];
                        foreach ($userTurmas as $turma): 
                            if (!in_array($turma, $turmasExibidas)):
                                $turmasExibidas[] = $turma;
                        ?>
                            <option value="<?= htmlspecialchars($turma) ?>">Turma <?= htmlspecialchars($turma) ?></option>
                        <?php endif; endforeach; ?>
                        <?php if (empty($userTurmas)): ?>
                            <option value="A">Turma A</option>
                            <option value="B">Turma B</option>
                            <option value="C">Turma C</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <!-- Disciplinas -->
            <div class="disciplinas-section">
                <div class="disciplinas-header">
                    <div>
                        <label><i class="fas fa-book"></i> Disciplinas:</label>
                        <span class="selected-count" id="selectedCount">(0 selecionadas)</span>
                    </div>
                    <div class="disciplinas-actions">
                        <button class="btn-sm btn-select-all" onclick="selectAllDisciplinas()">
                            <i class="fas fa-check-double"></i> Todas
                        </button>
                        <button class="btn-sm btn-clear-all" onclick="clearAllDisciplinas()">
                            <i class="fas fa-times"></i> Limpar
                        </button>
                    </div>
                </div>
                <div id="disciplinasList" class="disciplinas-list">
                    <div class="no-disciplinas">
                        <span class="icon">⏳</span>
                        Carregando disciplinas...
                    </div>
                </div>
            </div>

            <!-- Botões -->
            <div class="action-buttons">
                <button class="btn-generate" id="generateBtn" onclick="generatePauta()" disabled>
                    <i class="fas fa-file-pdf"></i> Gerar Pauta
                </button>
            </div>
        </div>

        <!-- Resultado da pauta -->
        <div id="pautaResult" class="pauta-result">
            <!-- Conteúdo gerado via JavaScript -->
        </div>
    </div>

    <!-- Input oculto para upload da insígnia -->
    <input type="file" id="insigniaUpload" accept="image/*" style="display:none">

    <!-- Bibliotecas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
        // ============================================
        // DADOS DO PHP PARA JAVASCRIPT
        // ============================================
        const DADOS = <?= json_encode($dadosJS) ?>;
        
        console.log('📊 DADOS CARREGADOS:');
        console.log('📚 Todas disciplinas:', DADOS.todasDisciplinas.length);
        console.log('📚 Disciplinas do professor:', DADOS.disciplinasProfessor.length);
        console.log('👨‍🏫 É Admin:', DADOS.isAdmin);
        console.log('🏫 Classes:', DADOS.userClasses);
        console.log('🏫 Turmas:', DADOS.userTurmas);
        console.log('📋 Distribuições:', DADOS.distribuicoes.length);
        console.log('👤 Usuário:', DADOS.usuarioNome);

        // ============================================
        // VARIÁVEIS GLOBAIS
        // ============================================
        const NOTA_MINIMA_TRANSITAR = 5.0;
        const NOTA_DESISTENTE = 1.2;
        
        let disciplinasSelecionadas = [];
        let columnOrder = [];
        let currentPautaData = null;
        let escolaNome = DADOS.escola || 'ESCOLA EXEMPLO';
        let modoProfessor = !DADOS.isAdmin;
        let disciplinasAtuais = [];
        let isAdmin = DADOS.isAdmin;

        // Cabeçalhos editáveis
        let headerTexts = {
            republica: 'REPÚBLICA DE ANGOLA',
            ministerio: 'MINISTÉRIO DA EDUCAÇÃO',
            direcao: 'DIREÇÃO MUNICIPAL DA EDUCAÇÃO DE BOM JESUS'
        };

        // ============================================
        // INICIALIZAÇÃO
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Inicializando Pautas Trimestrais...');
            
            // Configurar toggle
            configurarToggle();
            
            // Carregar disciplinas iniciais
            carregarDisciplinas();
            
            // Evento de upload da insígnia
            document.getElementById('insigniaUpload').addEventListener('change', handleInsigniaUpload);
            
            // Eventos de mudança nos filtros
            ['trimestre', 'classe', 'turma'].forEach(id => {
                document.getElementById(id).addEventListener('change', function() {
                    if (currentPautaData) {
                        document.getElementById('pautaResult').style.display = 'none';
                        currentPautaData = null;
                    }
                    validateForm();
                });
            });
            
            // Validar formulário inicial
            validateForm();
            
            console.log('✅ Inicialização concluída!');
        });

        // ============================================
        // CONFIGURAR TOGGLE
        // ============================================
        function configurarToggle() {
            const toggle = document.getElementById('toggleModoProfessor');
            const status = document.getElementById('toggleStatus');
            
            if (isAdmin) {
                modoProfessor = false;
                toggle.checked = false;
                toggle.disabled = false;
                
                status.className = 'toggle-status all-mode';
                status.innerHTML = '<i class="fas fa-globe"></i> Todas disciplinas';
                
                toggle.addEventListener('change', function() {
                    modoProfessor = this.checked;
                    console.log('🔄 Toggle alterado! Modo professor:', modoProfessor);
                    
                    if (modoProfessor) {
                        status.className = 'toggle-status professor-mode';
                        status.innerHTML = '<i class="fas fa-chalkboard-teacher"></i> Apenas minhas disciplinas';
                        showMessage('info', '📚 Modo ativado: Mostrando apenas suas disciplinas');
                    } else {
                        status.className = 'toggle-status all-mode';
                        status.innerHTML = '<i class="fas fa-globe"></i> Todas disciplinas';
                        showMessage('info', '🌍 Modo desativado: Mostrando todas as disciplinas cadastradas');
                    }
                    
                    carregarDisciplinas();
                });
            } else {
                modoProfessor = true;
                toggle.checked = true;
                toggle.disabled = true;
                
                status.className = 'toggle-status professor-mode';
                status.innerHTML = '<i class="fas fa-chalkboard-teacher"></i> Minhas disciplinas (fixo)';
            }
        }

        // ============================================
        // FUNÇÃO PARA CARREGAR DISCIPLINAS
        // ============================================
        function carregarDisciplinas() {
            const disciplinasList = document.getElementById('disciplinasList');
            
            console.log('📚 carregarDisciplinas() - modoProfessor:', modoProfessor);
            
            let disciplinasParaMostrar = [];
            
            if (modoProfessor) {
                if (DADOS.disciplinasProfessor && DADOS.disciplinasProfessor.length > 0) {
                    disciplinasParaMostrar = DADOS.disciplinasProfessor;
                    console.log('📚 Mostrando disciplinas do professor:', disciplinasParaMostrar.length);
                } else {
                    disciplinasParaMostrar = DADOS.todasDisciplinas;
                    console.warn('⚠️ Nenhuma disciplina do professor, mostrando todas');
                }
            } else {
                if (DADOS.todasDisciplinas && DADOS.todasDisciplinas.length > 0) {
                    disciplinasParaMostrar = DADOS.todasDisciplinas;
                    console.log('📚 Mostrando todas as disciplinas:', disciplinasParaMostrar.length);
                } else {
                    disciplinasParaMostrar = DADOS.disciplinasProfessor;
                    console.warn('⚠️ Nenhuma disciplina cadastrada, mostrando do professor');
                }
            }
            
            disciplinasAtuais = disciplinasParaMostrar;
            disciplinasList.innerHTML = '';
            
            if (!disciplinasParaMostrar || disciplinasParaMostrar.length === 0) {
                disciplinasList.innerHTML = `
                    <div class="no-disciplinas">
                        <span class="icon">📭</span>
                        ${modoProfessor ? 'Nenhuma disciplina associada ao professor' : 'Nenhuma disciplina cadastrada no sistema'}
                        <br>
                        <small style="color: #94a3b8;">
                            ${modoProfessor ? 'Contacte o administrador para associar disciplinas.' : 'Cadastre disciplinas no módulo de disciplinas.'}
                        </small>
                    </div>
                `;
                return;
            }
            
            disciplinasParaMostrar.forEach(disciplina => {
                const div = document.createElement('div');
                div.className = 'disciplina-checkbox';
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = `disc_${disciplina.id || Math.random()}`;
                checkbox.value = disciplina.nome;
                checkbox.dataset.id = disciplina.id || 0;
                checkbox.checked = true;
                checkbox.addEventListener('change', updateSelectedDisciplinas);
                
                const label = document.createElement('label');
                label.htmlFor = `disc_${disciplina.id || Math.random()}`;
                const nomeDisplay = disciplina.nome.length > 25 ? disciplina.nome.substring(0, 22) + '...' : disciplina.nome;
                label.textContent = nomeDisplay;
                
                div.appendChild(checkbox);
                div.appendChild(label);
                
                if (disciplina.codigo) {
                    const codigoSpan = document.createElement('span');
                    codigoSpan.className = 'codigo';
                    codigoSpan.textContent = disciplina.codigo;
                    div.appendChild(codigoSpan);
                }
                
                disciplinasList.appendChild(div);
            });
            
            updateSelectedDisciplinas();
            
            if (disciplinasSelecionadas.length === 0) {
                document.getElementById('generateBtn').disabled = true;
            }
        }

        // ============================================
        // FUNÇÕES DE DISCIPLINAS
        // ============================================
        function updateSelectedDisciplinas() {
            const checkboxes = document.querySelectorAll('#disciplinasList input[type="checkbox"]:checked');
            disciplinasSelecionadas = Array.from(checkboxes).map(cb => cb.value);
            columnOrder = [...disciplinasSelecionadas];
            
            const total = document.querySelectorAll('#disciplinasList input[type="checkbox"]').length;
            document.getElementById('selectedCount').textContent = `(${disciplinasSelecionadas.length} de ${total} selecionadas)`;
            validateForm();
        }

        function selectAllDisciplinas() {
            document.querySelectorAll('#disciplinasList input[type="checkbox"]').forEach(cb => {
                cb.checked = true;
            });
            updateSelectedDisciplinas();
        }

        function clearAllDisciplinas() {
            document.querySelectorAll('#disciplinasList input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            updateSelectedDisciplinas();
        }

        function validateForm() {
            const classe = document.getElementById('classe').value;
            const turma = document.getElementById('turma').value;
            const generateBtn = document.getElementById('generateBtn');
            
            const hasDisciplinas = disciplinasSelecionadas.length > 0;
            const hasClasse = classe && classe !== '';
            const hasTurma = turma && turma !== '';
            
            generateBtn.disabled = !(hasClasse && hasTurma && hasDisciplinas);
            
            console.log('🔍 Validação:', { hasClasse, hasTurma, hasDisciplinas, disabled: generateBtn.disabled });
        }

        // ============================================
        // FUNÇÕES DE MENSAGEM
        // ============================================
        function showMessage(type, text) {
            const messageArea = document.getElementById('pautaMessage');
            messageArea.className = `pauta-message ${type}`;
            messageArea.textContent = text;
            messageArea.style.display = 'block';
            
            if (type !== 'error') {
                setTimeout(() => {
                    messageArea.style.display = 'none';
                }, 4000);
            }
        }

        // ============================================
        // FUNÇÕES DE CÁLCULO (mantidas)
        // ============================================
        function calcularMedia(notas, disciplinas) {
            if (!disciplinas || disciplinas.length === 0) return 0;
            
            let soma = 0;
            let count = 0;
            
            disciplinas.forEach(disciplina => {
                const nota = notas?.[disciplina];
                if (nota !== undefined && nota !== null && nota !== '' && !isNaN(parseFloat(nota))) {
                    soma += parseFloat(nota);
                    count++;
                }
            });
            
            return count > 0 ? Math.round((soma / count) * 10) / 10 : 0;
        }

        function determinarSituacao(media) {
            if (media < NOTA_DESISTENTE) return 'DESISTENTE';
            if (media < NOTA_MINIMA_TRANSITAR) return 'NÃO TRANSITA';
            return 'TRANSITA';
        }

        function classificarNota(nota) {
            if (nota === undefined || nota === null || nota === '' || isNaN(parseFloat(nota))) return '';
            return parseFloat(nota) >= NOTA_MINIMA_TRANSITAR ? 'positiva' : 'negativa';
        }

        // ============================================
        // FUNÇÕES DE ESTATÍSTICAS (mantidas)
        // ============================================
        function calculateStats(alunos) {
            const total = alunos.length;
            const aprovados = alunos.filter(a => a.situacao === 'TRANSITA').length;
            const reprovados = alunos.filter(a => a.situacao === 'NÃO TRANSITA').length;
            const desistentes = alunos.filter(a => a.situacao === 'DESISTENTE').length;
            
            const medias = alunos
                .filter(a => a.situacao !== 'DESISTENTE' && a.media > 0)
                .map(a => parseFloat(a.media));
            
            const mediaGeral = medias.length > 0 
                ? (medias.reduce((a, b) => a + b, 0) / medias.length).toFixed(1)
                : '0.0';
            
            const percAprov = total > 0 ? ((aprovados / total) * 100).toFixed(1) : 0;
            const percReprov = total > 0 ? ((reprovados / total) * 100).toFixed(1) : 0;
            const percDesist = total > 0 ? ((desistentes / total) * 100).toFixed(1) : 0;
            
            let totalPositivas = 0;
            let totalNegativas = 0;
            
            alunos.forEach(aluno => {
                if (aluno.situacao !== 'DESISTENTE') {
                    columnOrder.forEach(disciplina => {
                        const nota = parseFloat(aluno.notas?.[disciplina]);
                        if (!isNaN(nota)) {
                            if (nota >= NOTA_MINIMA_TRANSITAR) totalPositivas++;
                            else totalNegativas++;
                        }
                    });
                }
            });
            
            const totalAvaliacoes = totalPositivas + totalNegativas;
            const percentualPositivas = totalAvaliacoes > 0 ? ((totalPositivas / totalAvaliacoes) * 100).toFixed(1) : 0;
            
            return { total, aprovados, reprovados, desistentes, mediaGeral, percAprov, percReprov, percDesist, 
                     totalPositivas, totalNegativas, percentualPositivas };
        }

        function calculateGenderStats(alunos) {
            const stats = {
                masculino: { total: 0, aprovados: 0, reprovados: 0, desistentes: 0, mediaSoma: 0, mediaCount: 0, totalPositivas: 0, totalNegativas: 0 },
                feminino: { total: 0, aprovados: 0, reprovados: 0, desistentes: 0, mediaSoma: 0, mediaCount: 0, totalPositivas: 0, totalNegativas: 0 }
            };
            
            alunos.forEach(aluno => {
                const sexo = (aluno.sexo || 'N/I').toUpperCase();
                const isMasculino = sexo === 'M' || sexo === 'MASCULINO';
                const isFeminino = sexo === 'F' || sexo === 'FEMININO';
                
                if (isMasculino || isFeminino) {
                    const genderKey = isMasculino ? 'masculino' : 'feminino';
                    const g = stats[genderKey];
                    
                    g.total++;
                    if (aluno.situacao === 'TRANSITA') g.aprovados++;
                    else if (aluno.situacao === 'NÃO TRANSITA') g.reprovados++;
                    else if (aluno.situacao === 'DESISTENTE') g.desistentes++;
                    
                    if (aluno.media && !isNaN(aluno.media)) {
                        g.mediaSoma += parseFloat(aluno.media);
                        g.mediaCount++;
                    }
                    
                    if (aluno.situacao !== 'DESISTENTE') {
                        columnOrder.forEach(disciplina => {
                            const nota = parseFloat(aluno.notas?.[disciplina]);
                            if (!isNaN(nota)) {
                                if (nota >= NOTA_MINIMA_TRANSITAR) g.totalPositivas++;
                                else g.totalNegativas++;
                            }
                        });
                    }
                }
            });
            
            ['masculino', 'feminino'].forEach(gender => {
                const g = stats[gender];
                g.percAprov = g.total > 0 ? ((g.aprovados / g.total) * 100).toFixed(1) : 0;
                g.percReprov = g.total > 0 ? ((g.reprovados / g.total) * 100).toFixed(1) : 0;
                g.percDesist = g.total > 0 ? ((g.desistentes / g.total) * 100).toFixed(1) : 0;
                g.mediaGeral = g.mediaCount > 0 ? (g.mediaSoma / g.mediaCount).toFixed(1) : '0.0';
            });
            
            return stats;
        }

        function calculateDisciplinaStats(alunos, disciplina) {
            let positivas = 0, negativas = 0, soma = 0, count = 0;
            
            alunos.forEach(aluno => {
                if (aluno.situacao === 'DESISTENTE') return;
                
                const nota = parseFloat(aluno.notas?.[disciplina]);
                if (!isNaN(nota)) {
                    soma += nota;
                    count++;
                    if (nota >= NOTA_MINIMA_TRANSITAR) positivas++;
                    else negativas++;
                }
            });
            
            const media = count > 0 ? soma / count : 0;
            const total = positivas + negativas;
            const percentualPositivas = total > 0 ? (positivas / total) * 100 : 0;
            
            return { positivas, negativas, media, percentualPositivas };
        }

        // ============================================
        // FUNÇÕES DE RENDERIZAÇÃO DA PAUTA (mantidas)
        // ============================================
        function abreviarNome(nome, maxLength = 12) {
            if (!nome || nome.length <= maxLength) return nome;
            return nome.substring(0, maxLength - 3) + '...';
        }

        function getSexoDisplay(sexo) {
            if (!sexo) return 'N/I';
            const s = sexo.toUpperCase();
            if (s === 'M' || s === 'MASCULINO') return '👨 M';
            if (s === 'F' || s === 'FEMININO') return '👩 F';
            return sexo;
        }

        function getSituacaoClass(situacao) {
            if (situacao === 'TRANSITA') return 'situacao-aprovado';
            if (situacao === 'NÃO TRANSITA') return 'situacao-reprovado';
            if (situacao === 'DESISTENTE') return 'situacao-desistente';
            return '';
        }

        function buildPautaHTML(data, alunos, classe, turma) {
            const stats = calculateStats(alunos);
            const genderStats = calculateGenderStats(alunos);
            const disciplinasStats = {};
            
            columnOrder.forEach(disciplina => {
                disciplinasStats[disciplina] = calculateDisciplinaStats(alunos, disciplina);
            });
            
            let html = `
                <div class="header-controls">
                    <button class="btn-reset-columns" onclick="resetColumnOrder()">
                        <i class="fas fa-undo"></i> Resetar Ordem
                    </button>
                </div>
                
                <div class="pauta-header">
                    <div class="insignia-container">
                        <img id="insigniaImg" class="insignia-img" onclick="document.getElementById('insigniaUpload').click()" 
                             src="" alt="Clique para adicionar insígnia">
                    </div>
                    <div class="republika" onclick="enableHeaderEditing(this, 'republica')">${headerTexts.republica}</div>
                    <div class="ministerio" onclick="enableHeaderEditing(this, 'ministerio')">${headerTexts.ministerio}</div>
                    <div class="direcao" onclick="enableHeaderEditing(this, 'direcao')">${headerTexts.direcao}</div>
                    <div class="escola-nome" onclick="enableEscolaEditing(this)">${escolaNome.toUpperCase()}</div>
                    <h3>PAUTA DO ${data.trimestre}º TRIMESTRE</h3>
                    <p>${classe}ª CLASSE - TURMA ${turma} (10 pontos)</p>
                    <p style="font-size: 12px; color: #666;">Regras: Média ≥ 5.0 = TRANSITA | 1.2 a 4.9 = NÃO TRANSITA | < 1.2 = DESISTENTE</p>
                </div>
                
                <div class="pauta-action-bar">
                    <button class="btn-print" onclick="printPauta()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <button class="btn-excel" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                </div>
                
                <div class="total-alunos-info">
                    <i class="fas fa-users"></i> Total: ${alunos.length} alunos | 
                    <span class="situacao-aprovado">✓ TRANSITA: ${stats.aprovados}</span> | 
                    <span class="situacao-reprovado">✗ NÃO TRANSITA: ${stats.reprovados}</span> | 
                    <span class="situacao-desistente">● DESISTENTE: ${stats.desistentes}</span>
                </div>
                
                <div class="pauta-table-container">
                    <table class="pauta-table">
                        <thead>
                            <tr>
                                <th rowspan="2" draggable="false">Nº</th>
                                <th rowspan="2" draggable="false" style="min-width:150px;">NOME DO ALUNO</th>
                                <th rowspan="2" draggable="false">SEXO</th>
                                <th colspan="${columnOrder.length}">NOTAS DO ${data.trimestre}º TRIMESTRE</th>
                                <th rowspan="2" draggable="false">MÉDIA</th>
                                <th rowspan="2" draggable="false">SITUAÇÃO</th>
                            </tr>
                            <tr>
            `;
            
            columnOrder.forEach(disciplina => {
                html += `<th draggable="true" title="${disciplina}">${abreviarNome(disciplina)}</th>`;
            });
            
            html += `</tr></thead><tbody>`;
            
            alunos.forEach((aluno, index) => {
                const media = parseFloat(aluno.media || 0).toFixed(1);
                const situacaoClass = getSituacaoClass(aluno.situacao);
                const sexoDisplay = getSexoDisplay(aluno.sexo);
                
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td style="text-align:left; font-weight:600;">${aluno.nome || ''}</td>
                        <td>${sexoDisplay}</td>
                `;
                
                columnOrder.forEach(disciplina => {
                    let nota = aluno.notas?.[disciplina];
                    if (nota === undefined || nota === null || nota === '') {
                        html += `<td>-</td>`;
                    } else {
                        const notaNum = parseFloat(nota);
                        const notaClass = classificarNota(nota);
                        html += `<td class="${notaClass}">${!isNaN(notaNum) ? notaNum.toFixed(1) : nota}</td>`;
                    }
                });
                
                html += `
                        <td class="media-destaque">${media}</td>
                        <td class="${situacaoClass}">${aluno.situacao || 'N/D'}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            
            // Gender Stats
            html += `
                <div class="gender-stats">
                    <div class="gender-card masculino">
                        <h6>👨 ALUNOS DO SEXO MASCULINO</h6>
                        <p><strong>Total:</strong> ${genderStats.masculino.total}</p>
                        <p><span class="situacao-aprovado">✓ TRANSITA:</span> ${genderStats.masculino.aprovados} (${genderStats.masculino.percAprov}%)</p>
                        <p><span class="situacao-reprovado">✗ NÃO TRANSITA:</span> ${genderStats.masculino.reprovados} (${genderStats.masculino.percReprov}%)</p>
                        <p><span class="situacao-desistente">● DESISTENTE:</span> ${genderStats.masculino.desistentes} (${genderStats.masculino.percDesist}%)</p>
                        <p><strong>Média Geral:</strong> ${genderStats.masculino.mediaGeral}</p>
                        <p><span class="positiva">Positivas:</span> ${genderStats.masculino.totalPositivas} | <span class="negativa">Negativas:</span> ${genderStats.masculino.totalNegativas}</p>
                    </div>
                    
                    <div class="gender-card feminino">
                        <h6>👩 ALUNAS DO SEXO FEMININO</h6>
                        <p><strong>Total:</strong> ${genderStats.feminino.total}</p>
                        <p><span class="situacao-aprovado">✓ TRANSITA:</span> ${genderStats.feminino.aprovados} (${genderStats.feminino.percAprov}%)</p>
                        <p><span class="situacao-reprovado">✗ NÃO TRANSITA:</span> ${genderStats.feminino.reprovados} (${genderStats.feminino.percReprov}%)</p>
                        <p><span class="situacao-desistente">● DESISTENTE:</span> ${genderStats.feminino.desistentes} (${genderStats.feminino.percDesist}%)</p>
                        <p><strong>Média Geral:</strong> ${genderStats.feminino.mediaGeral}</p>
                        <p><span class="positiva">Positivas:</span> ${genderStats.feminino.totalPositivas} | <span class="negativa">Negativas:</span> ${genderStats.feminino.totalNegativas}</p>
                    </div>
                </div>
            `;
            
            // Stats Grid
            html += `
                <div class="stats-grid">
                    <div class="stats-card">
                        <h5><i class="fas fa-chart-pie"></i> SITUAÇÃO ACADÊMICA</h5>
                        <p><strong>Total de Alunos:</strong> ${stats.total}</p>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${stats.percAprov}%;">
                                ${stats.percAprov}% Aprovados
                            </div>
                        </div>
                        <p><span class="situacao-aprovado">✓ TRANSITA (Média ≥ 5.0):</span> ${stats.aprovados} (${stats.percAprov}%)</p>
                        <p><span class="situacao-reprovado">✗ NÃO TRANSITA (1.2 - 4.9):</span> ${stats.reprovados} (${stats.percReprov}%)</p>
                        <p><span class="situacao-desistente">● DESISTENTE (Média < 1.2):</span> ${stats.desistentes} (${stats.percDesist}%)</p>
                        <p><strong>Média Geral da Turma:</strong> ${stats.mediaGeral}</p>
                        <p><strong>Positivas (≥5.0):</strong> <span class="positiva">${stats.totalPositivas}</span></p>
                        <p><strong>Negativas (<5.0):</strong> <span class="negativa">${stats.totalNegativas}</span></p>
                        <p><strong>Aproveitamento:</strong> ${stats.percentualPositivas}%</p>
                    </div>
                    
                    <div class="stats-card">
                        <h5><i class="fas fa-chart-bar"></i> ESTATÍSTICAS POR DISCIPLINA</h5>
                        <table>
                            <thead>
                                <tr>
                                    <th>Disciplina</th>
                                    <th>Positivas</th>
                                    <th>Negativas</th>
                                    <th>% Pos.</th>
                                    <th>Média</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            columnOrder.forEach(disciplina => {
                const d = disciplinasStats[disciplina];
                html += `
                    <tr>
                        <td style="text-align:left;">${abreviarNome(disciplina, 15)}</td>
                        <td class="positiva">${d.positivas}</td>
                        <td class="negativa">${d.negativas}</td>
                        <td>${d.percentualPositivas.toFixed(1)}%</td>
                        <td>${d.media.toFixed(1)}</td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            // Legenda
            html += `
                <div class="pauta-legenda">
                    <h5><i class="fas fa-info-circle"></i> LEGENDA</h5>
                    <div class="legend-grid">
                        <div class="legend-item"><span class="positiva">● POSITIVA:</span> Nota ≥ 5.0 (verde)</div>
                        <div class="legend-item"><span class="negativa">● NEGATIVA:</span> Nota < 5.0 (vermelho)</div>
                        <div class="legend-item"><span class="situacao-aprovado">✓ TRANSITA:</span> Média ≥ 5.0</div>
                        <div class="legend-item"><span class="situacao-reprovado">✗ NÃO TRANSITA:</span> 1.2 ≤ Média < 5.0</div>
                        <div class="legend-item"><span class="situacao-desistente">● DESISTENTE:</span> Média < 1.2</div>
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
                    <div class="assinatura-item">
                        <div class="linha-assinatura"></div>
                        <div class="assinatura-nome">_________________________</div>
                        <div class="assinatura-cargo">Coordenador</div>
                    </div>
                </div>
            `;
            
            // Rodapé
            const usuarioNome = DADOS.usuarioNome || 'Usuário';
            html += `
                <div class="pauta-rodape">
                    <div>
                        <i class="fas fa-calendar-alt"></i> Data de emissão: ${new Date().toLocaleDateString('pt-BR')} ${new Date().toLocaleTimeString('pt-BR')}
                    </div>
                    <div>
                        <i class="fas fa-user-graduate"></i> Professor: ${usuarioNome}
                        ${modoProfessor ? ' | <span style="color:#3498db;"><i class="fas fa-chalkboard-teacher"></i> Minhas disciplinas</span>' : ' | <span style="color:#c9a84c;"><i class="fas fa-globe"></i> Todas disciplinas</span>'}
                    </div>
                </div>
            `;
            
            return html;
        }

        function displayPauta(data) {
            const container = document.getElementById('pautaResult');
            container.style.display = 'block';
            
            const classe = data.classe || document.getElementById('classe').value;
            const turma = data.turma || document.getElementById('turma').value;
            
            const alunosArray = Object.values(data.alunos || {})
                .sort((a, b) => (a.nome || '').localeCompare(b.nome || ''));
            
            if (alunosArray.length === 0) {
                container.innerHTML = `
                    <div class="pauta-header">
                        <div class="insignia-container">
                            <img class="insignia-img" onclick="document.getElementById('insigniaUpload').click()" 
                                 src="" alt="Clique para adicionar insígnia">
                        </div>
                        <div class="republika">${headerTexts.republica}</div>
                        <div class="ministerio">${headerTexts.ministerio}</div>
                        <div class="direcao">${headerTexts.direcao}</div>
                        <div class="escola-nome">${escolaNome.toUpperCase()}</div>
                        <h3>PAUTA DO ${data.trimestre}º TRIMESTRE</h3>
                        <p>${classe}ª CLASSE - TURMA ${turma}</p>
                        <p style="color: #e74c3c; font-size:18px; padding:20px;">
                            <i class="fas fa-exclamation-triangle"></i> Nenhum aluno encontrado
                        </p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = buildPautaHTML(data, alunosArray, classe, turma);
            
            // Inicializar drag de colunas
            initColumnDrag();
            
            container.scrollIntoView({ behavior: 'smooth' });
        }

        // ============================================
        // FUNÇÕES DE ARRASTAR COLUNAS
        // ============================================
        let draggingColumn = null;

        function initColumnDrag() {
            const headers = document.querySelectorAll('.pauta-table th[draggable="true"]');
            headers.forEach(header => {
                header.addEventListener('dragstart', handleDragStart);
                header.addEventListener('dragover', handleDragOver);
                header.addEventListener('drop', handleDrop);
                header.addEventListener('dragend', handleDragEnd);
            });
        }

        function handleDragStart(e) {
            draggingColumn = e.target;
            e.target.classList.add('dragging');
            e.dataTransfer.setData('text/plain', e.target.cellIndex);
            e.dataTransfer.effectAllowed = 'move';
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        }

        function handleDrop(e) {
            e.preventDefault();
            
            const fromIndex = parseInt(e.dataTransfer.getData('text/plain'));
            const toIndex = e.target.cellIndex;
            
            if (fromIndex === toIndex || toIndex < 3 || fromIndex < 3) return;
            
            const disciplinaFromIndex = fromIndex - 3;
            const disciplinaToIndex = toIndex - 3;
            
            const disciplinaFrom = columnOrder[disciplinaFromIndex];
            const disciplinaTo = columnOrder[disciplinaToIndex];
            
            columnOrder[disciplinaFromIndex] = disciplinaTo;
            columnOrder[disciplinaToIndex] = disciplinaFrom;
            
            if (currentPautaData) {
                displayPauta(currentPautaData);
            }
        }

        function handleDragEnd(e) {
            e.target.classList.remove('dragging');
            draggingColumn = null;
        }

        function resetColumnOrder() {
            columnOrder = [...disciplinasSelecionadas];
            if (currentPautaData) {
                displayPauta(currentPautaData);
            }
        }

        // ============================================
        // FUNÇÕES DE EDIÇÃO DO CABEÇALHO
        // ============================================
        function enableHeaderEditing(element, field) {
            if (element.classList.contains('editing')) return;
            
            const currentName = element.textContent;
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentName;
            input.className = 'edit-input';
            input.style.width = Math.min(400, currentName.length * 12) + 'px';
            
            element.classList.add('editing');
            element.innerHTML = '';
            element.appendChild(input);
            input.focus();
            input.select();
            
            input.addEventListener('blur', () => {
                finishHeaderEditing(element, input, field);
            });
            
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    finishHeaderEditing(element, input, field);
                }
            });
        }

        function finishHeaderEditing(element, input, field) {
            const newText = input.value.trim().toUpperCase() || headerTexts[field];
            headerTexts[field] = newText;
            element.classList.remove('editing');
            element.innerHTML = newText;
        }

        function enableEscolaEditing(element) {
            if (element.classList.contains('editing')) return;
            
            const currentName = element.textContent;
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentName;
            input.className = 'edit-input';
            input.style.width = Math.min(400, currentName.length * 12) + 'px';
            
            element.classList.add('editing');
            element.innerHTML = '';
            element.appendChild(input);
            input.focus();
            input.select();
            
            input.addEventListener('blur', () => {
                finishEscolaEditing(element, input);
            });
            
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    finishEscolaEditing(element, input);
                }
            });
        }

        function finishEscolaEditing(element, input) {
            const newName = input.value.trim().toUpperCase() || escolaNome;
            escolaNome = newName;
            element.classList.remove('editing');
            element.innerHTML = newName;
        }

        // ============================================
        // FUNÇÕES DE UPLOAD DA INSÍGNIA
        // ============================================
        function handleInsigniaUpload(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const insigniaImg = document.getElementById('insigniaImg');
                    if (insigniaImg) {
                        insigniaImg.src = e.target.result;
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        // ============================================
        // FUNÇÃO DE IMPRESSÃO
        // ============================================
        function printPauta() {
            window.print();
        }

        // ============================================
        // FUNÇÃO DE EXPORTAÇÃO PARA EXCEL
        // ============================================
        function exportToExcel() {
            if (!currentPautaData) {
                showMessage('error', 'Gere uma pauta primeiro');
                return;
            }
            
            try {
                const classe = document.getElementById('classe').value;
                const turma = document.getElementById('turma').value;
                const trimestre = document.getElementById('trimestre').value;
                
                const wb = XLSX.utils.book_new();
                const wsData = [];
                
                // Cabeçalho
                wsData.push([headerTexts.republica]);
                wsData.push([headerTexts.ministerio]);
                wsData.push([headerTexts.direcao]);
                wsData.push([escolaNome.toUpperCase()]);
                wsData.push([]);
                wsData.push([`PAUTA DO ${trimestre}º TRIMESTRE`]);
                wsData.push([`${classe}ª CLASSE - TURMA ${turma} (10 pontos)`]);
                wsData.push([`Total de Alunos: ${Object.keys(currentPautaData.alunos || {}).length}`]);
                wsData.push([`Regras: Média ≥ 5.0 = TRANSITA | 1.2 a 4.9 = NÃO TRANSITA | < 1.2 = DESISTENTE`]);
                wsData.push([]);
                
                // Cabeçalho da tabela
                const headerRow = ['Nº', 'NOME DO ALUNO', 'SEXO'];
                columnOrder.forEach(d => headerRow.push(d));
                headerRow.push('MÉDIA', 'SITUAÇÃO');
                wsData.push(headerRow);
                
                // Dados
                const alunos = Object.values(currentPautaData.alunos || {}).sort((a, b) => (a.nome || '').localeCompare(b.nome || ''));
                alunos.forEach((aluno, index) => {
                    const row = [
                        index + 1,
                        aluno.nome || '',
                        aluno.sexo || 'N/I'
                    ];
                    
                    columnOrder.forEach(disciplina => {
                        let nota = aluno.notas?.[disciplina];
                        if (nota === undefined || nota === null || nota === '') {
                            row.push('-');
                        } else {
                            const notaNum = parseFloat(nota);
                            row.push(!isNaN(notaNum) ? notaNum.toFixed(1) : nota);
                        }
                    });
                    
                    row.push(parseFloat(aluno.media || 0).toFixed(1));
                    row.push(aluno.situacao || 'N/D');
                    
                    wsData.push(row);
                });
                
                // Estatísticas
                wsData.push([]);
                wsData.push(['ESTATÍSTICAS DA TURMA']);
                
                const stats = calculateStats(alunos);
                wsData.push(['Total de Alunos', stats.total]);
                wsData.push(['TRANSITA (Média ≥ 5.0)', `${stats.aprovados} (${stats.percAprov}%)`]);
                wsData.push(['NÃO TRANSITA (1.2 - 4.9)', `${stats.reprovados} (${stats.percReprov}%)`]);
                wsData.push(['DESISTENTE (Média < 1.2)', `${stats.desistentes} (${stats.percDesist}%)`]);
                wsData.push(['Média Geral da Turma', stats.mediaGeral]);
                wsData.push(['Total Positivas (≥5.0)', stats.totalPositivas]);
                wsData.push(['Total Negativas (<5.0)', stats.totalNegativas]);
                wsData.push(['Percentual de Aproveitamento', stats.percentualPositivas + '%']);
                
                wsData.push([]);
                wsData.push([`Data de emissão: ${new Date().toLocaleDateString('pt-BR')}`]);
                wsData.push([`Professor: ${DADOS.usuarioNome || 'Usuário'}`]);
                wsData.push([`Modo: ${modoProfessor ? 'Apenas disciplinas do professor' : 'Todas disciplinas cadastradas'}`]);
                
                const ws = XLSX.utils.aoa_to_sheet(wsData);
                XLSX.utils.book_append_sheet(wb, ws, 'Pauta');
                
                const fileName = `pauta_${classe}_${turma}_${trimestre}trim_${new Date().toISOString().slice(0,10)}.xlsx`;
                XLSX.writeFile(wb, fileName);
                
                showMessage('success', 'Arquivo Excel exportado com sucesso!');
            } catch (error) {
                console.error('Erro ao exportar:', error);
                showMessage('error', 'Erro ao exportar para Excel: ' + error.message);
            }
        }

        // ============================================
        // FUNÇÃO DE GERAÇÃO DA PAUTA
        // ============================================
        async function generatePauta() {
            const trimestre = document.getElementById('trimestre').value;
            const classe = document.getElementById('classe').value;
            const turma = document.getElementById('turma').value;
            
            if (!classe || !turma || disciplinasSelecionadas.length === 0) {
                showMessage('error', 'Preencha todos os campos e selecione pelo menos uma disciplina');
                return;
            }
            
            const generateBtn = document.getElementById('generateBtn');
            generateBtn.classList.add('loading');
            generateBtn.disabled = true;
            
            try {
                const requestData = {
                    trimestre: trimestre,
                    classe: classe,
                    turma: turma,
                    disciplinas: disciplinasSelecionadas,
                    modo: modoProfessor ? 'professor' : 'todos',
                    professorNome: DADOS.usuarioNome
                };
                
                const response = await fetch('api/pauta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(requestData)
                });
                
                if (!response.ok) throw new Error(`Erro ${response.status}: ${response.statusText}`);
                
                const data = await response.json();
                
                if (data.success) {
                    data.disciplinas = disciplinasSelecionadas;
                    columnOrder = [...disciplinasSelecionadas];
                    
                    Object.values(data.alunos || {}).forEach(aluno => {
                        if (aluno.notas) {
                            aluno.media = calcularMedia(aluno.notas, disciplinasSelecionadas);
                            aluno.situacao = determinarSituacao(aluno.media);
                        }
                    });
                    
                    currentPautaData = data;
                    displayPauta(data);
                    showMessage('success', 'Pauta gerada com sucesso!');
                } else {
                    throw new Error(data.message || 'Erro ao gerar pauta');
                }
            } catch (error) {
                console.error('Erro:', error);
                showMessage('error', 'Erro ao gerar pauta: ' + error.message);
                
                // Dados mockados para demonstração
                const mockData = {
                    success: true,
                    trimestre: trimestre,
                    classe: classe,
                    turma: turma,
                    alunos: gerarAlunosMock(disciplinasSelecionadas)
                };
                
                mockData.disciplinas = disciplinasSelecionadas;
                columnOrder = [...disciplinasSelecionadas];
                
                Object.values(mockData.alunos || {}).forEach(aluno => {
                    if (aluno.notas) {
                        aluno.media = calcularMedia(aluno.notas, disciplinasSelecionadas);
                        aluno.situacao = determinarSituacao(aluno.media);
                    }
                });
                
                currentPautaData = mockData;
                displayPauta(mockData);
                showMessage('info', '⚠️ Dados de demonstração - API não disponível');
            } finally {
                generateBtn.classList.remove('loading');
                generateBtn.disabled = false;
            }
        }

        // ============================================
        // FUNÇÃO PARA GERAR ALUNOS MOCK
        // ============================================
        function gerarAlunosMock(disciplinas) {
            const nomes = ['João Silva', 'Maria Santos', 'Pedro Costa', 'Ana Oliveira', 'Carlos Lima', 
                           'Juliana Pereira', 'Marcos Souza', 'Patrícia Gomes', 'Rafael Almeida', 'Beatriz Ferreira'];
            const sexos = ['M', 'F', 'M', 'F', 'M', 'F', 'M', 'F', 'M', 'F'];
            const alunos = {};
            
            nomes.forEach((nome, index) => {
                const notas = {};
                disciplinas.forEach(disciplina => {
                    const nota = Math.random() * 10;
                    notas[disciplina] = Math.round(nota * 10) / 10;
                });
                
                alunos[index + 1] = {
                    nome: nome,
                    sexo: sexos[index],
                    notas: notas
                };
            });
            
            return alunos;
        }

        // ============================================
        // VALIDAÇÃO EM TEMPO REAL
        // ============================================
        document.getElementById('classe').addEventListener('change', validateForm);
        document.getElementById('turma').addEventListener('change', validateForm);
        
        console.log('✅ Script carregado completamente!');
    </script>
</body>
</html>