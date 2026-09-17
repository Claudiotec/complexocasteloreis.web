<?php
session_start();

// ==========================================
// VERIFICAÇÃO DE SESSÃO
// ==========================================
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
if ($perfil != 'professor' && $perfil != 'docente') {
    header("Location: ../index.php");
    exit;
}

// ==========================================
// CONFIGURAÇÕES
// ==========================================
require_once '../config/database.php';

date_default_timezone_set('Africa/Luanda');

$professor_id = $_SESSION['usuario_id'];
$professor_nome = $_SESSION['usuario_nome'] ?? 'Professor';
$professor_email = $_SESSION['usuario_email'] ?? '';

// ==========================================
// BUSCAR DADOS DA ESCOLA
// ==========================================
$nome_escola = 'Sistema de Gestão Escolar';
$endereco_escola = '';
$telefone_escola = '';
$email_escola = '';
$logo_escola = '';

try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa) {
        $nome_escola = $empresa['razao_social'] ?? $empresa['nome_fantasia'] ?? 'Sistema de Gestão Escolar';
        $endereco_escola = $empresa['endereco'] ?? '';
        $telefone_escola = $empresa['telefone'] ?? '';
        $email_escola = $empresa['email'] ?? '';
        $logo_escola = $empresa['logo'] ?? '';
    }
} catch (Exception $e) {}

// ==========================================
// BUSCAR DADOS DO PROFESSOR
// ==========================================
$professor_num_agente = null;
$professor_nome_completo = '';

try {
    if (!empty($professor_email)) {
        $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE email = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$professor_email]);
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($professor) {
            $professor_num_agente = $professor['num_agente'] ?? $professor['id'];
            $professor_nome_completo = $professor['nome'];
        }
    }
    
    if (!$professor_num_agente && !empty($professor_nome)) {
        $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE nome LIKE ? AND status = 'ativo' LIMIT 1");
        $stmt->execute(['%' . $professor_nome . '%']);
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($professor) {
            $professor_num_agente = $professor['num_agente'] ?? $professor['id'];
            $professor_nome_completo = $professor['nome'];
        }
    }
} catch (Exception $e) {}

// ==========================================
// VARIÁVEIS PARA FILTROS
// ==========================================
$tipo_relatorio = isset($_GET['tipo']) ? $_GET['tipo'] : 'geral';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$turma_id = isset($_GET['turma_id']) ? $_GET['turma_id'] : '';
$disciplina_id = isset($_GET['disciplina_id']) ? $_GET['disciplina_id'] : '';

// ==========================================
// BUSCAR TURMAS DO PROFESSOR
// ==========================================
$turmas = [];
try {
    if ($professor_num_agente) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT turma_nome, classe 
            FROM destribuicao_professores 
            WHERE professor_id = ? AND tipo = 'PROFESSOR'
            ORDER BY turma_nome ASC
        ");
        $stmt->execute([$professor_num_agente]);
        $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// ==========================================
// FUNÇÕES DE RELATÓRIOS
// ==========================================

function gerarRelatorioGeral($pdo, $professor_num_agente) {
    $dados = [
        'total_turmas' => 0,
        'total_alunos' => 0,
        'total_disciplinas' => 0,
        'presencas_mes' => 0,
        'faltas_mes' => 0,
        'total_aulas' => 0,
        'taxa_presenca' => 0
    ];
    
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT turma_nome 
            FROM destribuicao_professores 
            WHERE professor_id = ? AND tipo = 'PROFESSOR'
        ");
        $stmt->execute([$professor_num_agente]);
        $turmas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $dados['total_turmas'] = count($turmas);
        
        if (!empty($turmas)) {
            $placeholders = implode(',', array_fill(0, count($turmas), '?'));
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM alunos 
                WHERE TURMA IN ($placeholders) AND status = 'ativo'
            ");
            $stmt->execute($turmas);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $dados['total_alunos'] = $result['total'] ?? 0;
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT disciplinas) as total 
            FROM destribuicao_professores 
            WHERE professor_id = ? AND tipo = 'PROFESSOR' AND disciplinas IS NOT NULL
        ");
        $stmt->execute([$professor_num_agente]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $dados['total_disciplinas'] = $result['total'] ?? 0;
        
        $mes_atual = date('m');
        $ano_atual = date('Y');
        
        if (!empty($turmas)) {
            $stmt = $pdo->prepare("
                SELECT id FROM alunos 
                WHERE TURMA IN ($placeholders) AND status = 'ativo'
            ");
            $stmt->execute($turmas);
            $alunos_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($alunos_ids)) {
                $alunos_placeholders = implode(',', array_fill(0, count($alunos_ids), '?'));
                
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as presencas,
                        SUM(CASE WHEN status = 'ausente' THEN 1 ELSE 0 END) as faltas
                    FROM frequencia 
                    WHERE aluno_id IN ($alunos_placeholders) 
                    AND MONTH(data) = ? AND YEAR(data) = ?
                ");
                $params = array_merge($alunos_ids, [$mes_atual, $ano_atual]);
                $stmt->execute($params);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $dados['total_aulas'] = $result['total'] ?? 0;
                $dados['presencas_mes'] = $result['presencas'] ?? 0;
                $dados['faltas_mes'] = $result['faltas'] ?? 0;
                $dados['taxa_presenca'] = $dados['total_aulas'] > 0 ? 
                    round(($dados['presencas_mes'] / $dados['total_aulas']) * 100, 1) : 0;
            }
        }
        
    } catch (Exception $e) {
        error_log("Erro no relatório geral: " . $e->getMessage());
    }
    
    return $dados;
}

function gerarRelatorioFrequencia($pdo, $professor_num_agente, $data_inicio, $data_fim, $turma_id = null) {
    $dados = [];
    
    try {
        $sql = "
            SELECT 
                a.id as aluno_id,
                a.nome as aluno_nome,
                a.TURMA as turma_nome,
                COUNT(f.id) as total_aulas,
                SUM(CASE WHEN f.status = 'presente' THEN 1 ELSE 0 END) as presencas,
                SUM(CASE WHEN f.status = 'ausente' THEN 1 ELSE 0 END) as faltas,
                ROUND((SUM(CASE WHEN f.status = 'presente' THEN 1 ELSE 0 END) / NULLIF(COUNT(f.id), 0)) * 100, 2) as percentual_presenca
            FROM alunos a
            INNER JOIN destribuicao_professores d ON a.TURMA = d.turma_nome
            LEFT JOIN frequencia f ON a.id = f.aluno_id 
                AND f.data BETWEEN :data_inicio AND :data_fim
            WHERE d.professor_id = :professor_id AND d.tipo = 'PROFESSOR'
        ";
        
        if ($turma_id) {
            $sql .= " AND a.TURMA = :turma_nome";
        }
        
        $sql .= " GROUP BY a.id, a.nome, a.TURMA
                  ORDER BY a.nome ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':professor_id', $professor_num_agente);
        $stmt->bindParam(':data_inicio', $data_inicio);
        $stmt->bindParam(':data_fim', $data_fim);
        
        if ($turma_id) {
            $stmt->bindParam(':turma_nome', $turma_id);
        }
        
        $stmt->execute();
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Erro no relatório de frequência: " . $e->getMessage());
    }
    
    return $dados;
}

function gerarRelatorioNotas($pdo, $professor_num_agente, $data_inicio, $data_fim, $turma_id = null) {
    $dados = [];
    
    try {
        $sql = "
            SELECT 
                a.id as aluno_id,
                a.nome as aluno_nome,
                a.TURMA as turma_nome,
                ROUND(AVG(n.nota), 2) as media_geral,
                COUNT(n.id) as total_avaliacoes,
                ROUND(MAX(n.nota), 2) as maior_nota,
                ROUND(MIN(n.nota), 2) as menor_nota,
                CASE 
                    WHEN ROUND(AVG(n.nota), 2) >= 14 THEN 'Excelente'
                    WHEN ROUND(AVG(n.nota), 2) >= 10 THEN 'Bom'
                    WHEN ROUND(AVG(n.nota), 2) >= 7 THEN 'Regular'
                    ELSE 'Precisa Melhorar'
                END as classificacao
            FROM alunos a
            INNER JOIN destribuicao_professores d ON a.TURMA = d.turma_nome
            INNER JOIN notas n ON a.id = n.aluno_id
            WHERE d.professor_id = :professor_id AND d.tipo = 'PROFESSOR'
                AND n.data_avaliacao BETWEEN :data_inicio AND :data_fim
        ";
        
        if ($turma_id) {
            $sql .= " AND a.TURMA = :turma_nome";
        }
        
        $sql .= " GROUP BY a.id, a.nome, a.TURMA
                  ORDER BY media_geral DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':professor_id', $professor_num_agente);
        $stmt->bindParam(':data_inicio', $data_inicio);
        $stmt->bindParam(':data_fim', $data_fim);
        
        if ($turma_id) {
            $stmt->bindParam(':turma_nome', $turma_id);
        }
        
        $stmt->execute();
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Erro no relatório de notas: " . $e->getMessage());
    }
    
    return $dados;
}

function gerarRelatorioTurmas($pdo, $professor_num_agente) {
    $dados = [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                d.turma_nome,
                d.classe,
                d.disciplinas,
                COUNT(a.id) as total_alunos,
                COUNT(DISTINCT f.id) as total_frequencias,
                SUM(CASE WHEN f.status = 'presente' THEN 1 ELSE 0 END) as presencas,
                SUM(CASE WHEN f.status = 'ausente' THEN 1 ELSE 0 END) as faltas,
                ROUND(AVG(n.nota), 2) as media_turma
            FROM destribuicao_professores d
            LEFT JOIN alunos a ON d.turma_nome = a.TURMA AND a.status = 'ativo'
            LEFT JOIN frequencia f ON a.id = f.aluno_id AND MONTH(f.data) = MONTH(CURRENT_DATE())
            LEFT JOIN notas n ON a.id = n.aluno_id AND MONTH(n.data_avaliacao) = MONTH(CURRENT_DATE())
            WHERE d.professor_id = :professor_id AND d.tipo = 'PROFESSOR'
            GROUP BY d.turma_nome, d.classe, d.disciplinas
            ORDER BY d.turma_nome ASC
        ");
        $stmt->execute([':professor_id' => $professor_num_agente]);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Erro no relatório de turmas: " . $e->getMessage());
    }
    
    return $dados;
}

// ==========================================
// GERAR RELATÓRIO
// ==========================================
$dados_relatorio = [];
$mostrar_tabela = false;
$titulo_relatorio = 'Relatório Geral';
$periodo = date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));

if (isset($_GET['action']) && $_GET['action'] == 'view') {
    $mostrar_tabela = true;
    
    switch ($tipo_relatorio) {
        case 'frequencia':
            $dados_relatorio = gerarRelatorioFrequencia($pdo, $professor_num_agente, $data_inicio, $data_fim, $turma_id);
            $titulo_relatorio = 'Relatório de Frequência';
            break;
        case 'notas':
            $dados_relatorio = gerarRelatorioNotas($pdo, $professor_num_agente, $data_inicio, $data_fim, $turma_id);
            $titulo_relatorio = 'Relatório de Notas';
            break;
        case 'turmas':
            $dados_relatorio = gerarRelatorioTurmas($pdo, $professor_num_agente);
            $titulo_relatorio = 'Relatório de Turmas';
            break;
        case 'geral':
        default:
            $dados_relatorio = gerarRelatorioGeral($pdo, $professor_num_agente);
            $titulo_relatorio = 'Relatório Geral';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - SoftGest Web</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* ===== RESET E CONFIGURAÇÕES GERAIS ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        
        /* ===== CONFIGURAÇÕES DE IMPRESSÃO ===== */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .print-container {
                padding: 20px !important;
                margin: 0 !important;
                background: white !important;
                box-shadow: none !important;
            }
            
            .page-break {
                page-break-after: always;
            }
            
            .print-table {
                font-size: 11px !important;
            }
            
            .print-table th,
            .print-table td {
                padding: 6px 8px !important;
            }
            
            .stat-card-print {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
            
            .card-print {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            
            .card-print .card-header {
                background: #f8f9fa !important;
                border-bottom: 2px solid #333 !important;
            }
            
            .table-responsive {
                overflow: visible !important;
            }
            
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            thead {
                display: table-header-group;
            }
            
            tfoot {
                display: table-footer-group;
            }
            
            .header-print {
                border-bottom: 3px solid #c9a84c !important;
                padding-bottom: 15px !important;
                margin-bottom: 20px !important;
            }
            
            .footer-print {
                border-top: 2px solid #ccc !important;
                padding-top: 15px !important;
                margin-top: 30px !important;
                font-size: 10px !important;
                color: #666 !important;
            }
            
            .assinatura-container {
                margin-top: 50px !important;
                padding-top: 20px !important;
                border-top: 2px dashed #ccc !important;
            }
            
            .assinatura {
                display: inline-block;
                width: 250px;
                text-align: center;
                margin: 0 30px;
            }
            
            .assinatura .linha {
                border-top: 2px solid #333;
                margin-top: 40px;
                padding-top: 10px;
            }
            
            .badge-print {
                padding: 2px 10px !important;
                border-radius: 12px !important;
                font-size: 10px !important;
                font-weight: 600 !important;
            }
        }
        
        /* ===== CONTAINER PRINCIPAL ===== */
        .print-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            min-height: 100vh;
        }
        
        /* ===== CABEÇALHO ===== */
        .header-print {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            margin-bottom: 25px;
            border-bottom: 4px solid #c9a84c;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-left .logo-placeholder {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #c9a84c, #a8872e);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: 800;
            flex-shrink: 0;
        }
        
        .header-left .school-info h1 {
            font-size: 22px;
            font-weight: 800;
            color: #1a2332;
            margin: 0;
            line-height: 1.2;
        }
        
        .header-left .school-info .subtitle {
            font-size: 13px;
            color: #64748b;
            margin: 2px 0 0 0;
        }
        
        .header-left .school-info .details {
            font-size: 12px;
            color: #94a3b8;
            margin: 2px 0 0 0;
        }
        
        .header-right {
            text-align: right;
        }
        
        .header-right .doc-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header-right .doc-subtitle {
            font-size: 13px;
            color: #64748b;
        }
        
        .header-right .doc-date {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 3px;
        }
        
        .header-right .doc-id {
            font-size: 11px;
            color: #94a3b8;
            background: #f1f5f9;
            padding: 2px 10px;
            border-radius: 12px;
            display: inline-block;
            margin-top: 3px;
        }
        
        /* ===== INFO DO PROFESSOR ===== */
        .professor-info {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px 40px;
            border-left: 4px solid #c9a84c;
        }
        
        .professor-info .item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #334155;
        }
        
        .professor-info .item i {
            color: #c9a84c;
            width: 18px;
            text-align: center;
        }
        
        .professor-info .item strong {
            font-weight: 600;
        }
        
        /* ===== FILTROS ===== */
        .filters-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px 30px;
            padding: 12px 18px;
            background: #f1f5f9;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .filters-info .filter-item {
            font-size: 13px;
            color: #334155;
        }
        
        .filters-info .filter-item strong {
            font-weight: 600;
            color: #1a2332;
        }
        
        /* ===== STATS CARDS ===== */
        .stats-grid-print {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .stat-card-print {
            background: #f8fafc;
            border-radius: 10px;
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        
        .stat-card-print .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #1a2332;
            line-height: 1.2;
        }
        
        .stat-card-print .stat-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card-print .stat-icon {
            font-size: 24px;
            color: #c9a84c;
            opacity: 0.6;
            margin-bottom: 4px;
        }
        
        /* ===== TABELA ===== */
        .card-print {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-print .card-header {
            padding: 12px 18px;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-print .card-header h5 {
            font-size: 15px;
            font-weight: 700;
            color: #1a2332;
            margin: 0;
        }
        
        .card-print .card-header .badge-count {
            background: #c9a84c;
            color: #1a2332;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .table-responsive-print {
            overflow-x: auto;
            padding: 0;
        }
        
        .print-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .print-table thead {
            background: #f1f5f9;
        }
        
        .print-table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 700;
            color: #1a2332;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #c9a84c;
        }
        
        .print-table td {
            padding: 9px 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        
        .print-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .print-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .print-table .text-center {
            text-align: center;
        }
        
        .print-table .text-right {
            text-align: right;
        }
        
        /* ===== BADGES ===== */
        .badge-print {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-print.excelente {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-print.bom {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-print.regular {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-print.precisa {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-print.alta {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-print.media {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-print.baixa {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* ===== RODAPÉ ===== */
        .footer-print {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 11px;
            color: #94a3b8;
        }
        
        .footer-print .left {
            text-align: left;
        }
        
        .footer-print .right {
            text-align: right;
        }
        
        /* ===== ASSINATURAS ===== */
        .assinatura-container {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px dashed #e2e8f0;
            flex-wrap: wrap;
        }
        
        .assinatura {
            text-align: center;
            min-width: 200px;
        }
        
        .assinatura .label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        
        .assinatura .linha {
            border-top: 2px solid #1a2332;
            margin-top: 35px;
            padding-top: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #1a2332;
        }
        
        .assinatura .data {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
        }
        
        /* ===== EMPTY STATE ===== */
        .empty-state-print {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        
        .empty-state-print .icon {
            font-size: 48px;
            opacity: 0.3;
            margin-bottom: 15px;
        }
        
        .empty-state-print h4 {
            font-size: 18px;
            color: #1a2332;
            margin-bottom: 5px;
        }
        
        /* ===== BOTÕES DE CONTROLE ===== */
        .controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .controls .btn {
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
            cursor: pointer;
            font-size: 14px;
        }
        
        .controls .btn-primary {
            background: #c9a84c;
            color: #1a2332;
        }
        
        .controls .btn-primary:hover {
            background: #b8973a;
            transform: translateY(-2px);
        }
        
        .controls .btn-success {
            background: #217346;
            color: white;
        }
        
        .controls .btn-success:hover {
            background: #1a5c38;
            transform: translateY(-2px);
        }
        
        .controls .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .controls .btn-danger:hover {
            background: #b02a37;
            transform: translateY(-2px);
        }
        
        .controls .btn-secondary {
            background: #1a2332;
            color: white;
        }
        
        .controls .btn-secondary:hover {
            background: #2d3748;
            transform: translateY(-2px);
        }
        
        /* ===== RESPONSIVO ===== */
        @media (max-width: 768px) {
            .header-print {
                flex-direction: column;
                text-align: center;
            }
            
            .header-left {
                flex-direction: column;
                text-align: center;
            }
            
            .header-right {
                text-align: center;
            }
            
            .professor-info {
                flex-direction: column;
                gap: 8px;
            }
            
            .filters-info {
                flex-direction: column;
                gap: 8px;
            }
            
            .assinatura-container {
                flex-direction: column;
                align-items: center;
                gap: 30px;
            }
            
            .stats-grid-print {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid-print {
                grid-template-columns: 1fr;
            }
            
            .print-table {
                font-size: 11px;
            }
            
            .print-table th,
            .print-table td {
                padding: 6px 8px;
            }
        }
        
        /* ===== FILTROS FORM ===== */
        .filters-form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: end;
        }
        
        .filters-form .form-group {
            flex: 1;
            min-width: 150px;
        }
        
        .filters-form label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            display: block;
            margin-bottom: 3px;
        }
        
        .filters-form select,
        .filters-form input {
            width: 100%;
            padding: 7px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s;
            background: white;
        }
        
        .filters-form select:focus,
        .filters-form input:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.15);
        }
        
        .filters-form .btn-search {
            padding: 7px 30px;
            background: #c9a84c;
            color: #1a2332;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .filters-form .btn-search:hover {
            background: #b8973a;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<!-- ========================================== -->
<!-- CONTAINER PARA IMPRESSÃO -->
<!-- ========================================== -->
<div class="print-container" id="printContainer">

    <!-- ===== CABEÇALHO ===== -->
    <div class="header-print">
        <div class="header-left">
            <div class="logo-placeholder">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="school-info">
                <h1><?= htmlspecialchars($nome_escola) ?></h1>
                <p class="subtitle">Sistema de Gestão Escolar</p>
                <?php if (!empty($endereco_escola)): ?>
                    <p class="details"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($endereco_escola) ?></p>
                <?php endif; ?>
                <?php if (!empty($telefone_escola) || !empty($email_escola)): ?>
                    <p class="details">
                        <?php if (!empty($telefone_escola)): ?>
                            <i class="fas fa-phone"></i> <?= htmlspecialchars($telefone_escola) ?>
                        <?php endif; ?>
                        <?php if (!empty($email_escola)): ?>
                            &nbsp;|&nbsp; <i class="fas fa-envelope"></i> <?= htmlspecialchars($email_escola) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-right">
            <div class="doc-title"><?= $titulo_relatorio ?></div>
            <div class="doc-subtitle">Ano Letivo <?= date('Y') ?></div>
            <div class="doc-date"><?= date('d/m/Y H:i') ?></div>
            <div class="doc-id">#REL-<?= date('Ymd') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>

    <!-- ===== INFORMAÇÕES DO PROFESSOR ===== -->
    <div class="professor-info">
        <div class="item">
            <i class="fas fa-user"></i>
            <strong>Professor:</strong> <?= htmlspecialchars($professor_nome_completo ?: $professor_nome) ?>
        </div>
        <?php if ($professor_num_agente): ?>
        <div class="item">
            <i class="fas fa-id-badge"></i>
            <strong>Nº Agente:</strong> <?= htmlspecialchars($professor_num_agente) ?>
        </div>
        <?php endif; ?>
        <div class="item">
            <i class="fas fa-envelope"></i>
            <strong>E-mail:</strong> <?= htmlspecialchars($professor_email) ?>
        </div>
        <div class="item">
            <i class="fas fa-calendar-alt"></i>
            <strong>Período:</strong> <?= $periodo ?>
        </div>
    </div>

    <!-- ===== INFORMAÇÕES DOS FILTROS ===== -->
    <div class="filters-info">
        <div class="filter-item">
            <strong>Relatório:</strong> <?= ucfirst($tipo_relatorio) ?>
        </div>
        <?php if ($turma_id): ?>
        <div class="filter-item">
            <strong>Turma:</strong> <?= htmlspecialchars($turma_id) ?>
        </div>
        <?php endif; ?>
        <?php if ($disciplina_id): ?>
        <div class="filter-item">
            <strong>Disciplina:</strong> <?= htmlspecialchars($disciplina_id) ?>
        </div>
        <?php endif; ?>
        <div class="filter-item">
            <strong>Data Emissão:</strong> <?= date('d/m/Y H:i:s') ?>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- CONTEÚDO DO RELATÓRIO -->
    <!-- ========================================== -->
    
    <?php if ($mostrar_tabela && !empty($dados_relatorio)): ?>

        <!-- ===== RELATÓRIO GERAL ===== -->
        <?php if ($tipo_relatorio == 'geral' && isset($dados_relatorio['total_turmas'])): ?>
        
        <div class="stats-grid-print">
            <div class="stat-card-print">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?= $dados_relatorio['total_alunos'] ?? 0 ?></div>
                <div class="stat-label">Total de Alunos</div>
            </div>
            <div class="stat-card-print">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-value"><?= $dados_relatorio['total_turmas'] ?? 0 ?></div>
                <div class="stat-label">Total de Turmas</div>
            </div>
            <div class="stat-card-print">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-value"><?= $dados_relatorio['total_disciplinas'] ?? 0 ?></div>
                <div class="stat-label">Total de Disciplinas</div>
            </div>
            <div class="stat-card-print">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-value"><?= $dados_relatorio['presencas_mes'] ?? 0 ?></div>
                <div class="stat-label">Presenças no Mês</div>
            </div>
            <div class="stat-card-print">
                <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                <div class="stat-value"><?= $dados_relatorio['faltas_mes'] ?? 0 ?></div>
                <div class="stat-label">Faltas no Mês</div>
            </div>
            <div class="stat-card-print">
                <div class="stat-icon"><i class="fas fa-percent"></i></div>
                <div class="stat-value"><?= ($dados_relatorio['taxa_presenca'] ?? 0) ?>%</div>
                <div class="stat-label">Taxa de Presença</div>
            </div>
        </div>
        
        <!-- Tabela de resumo para relatório geral -->
        <div class="card-print">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar"></i> Resumo Geral</h5>
                <span class="badge-count"><?= date('d/m/Y') ?></span>
            </div>
            <div class="table-responsive-print">
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>Indicador</th>
                            <th class="text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Total de Alunos</strong></td>
                            <td class="text-right"><?= $dados_relatorio['total_alunos'] ?? 0 ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total de Turmas</strong></td>
                            <td class="text-right"><?= $dados_relatorio['total_turmas'] ?? 0 ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total de Disciplinas</strong></td>
                            <td class="text-right"><?= $dados_relatorio['total_disciplinas'] ?? 0 ?></td>
                        </tr>
                        <tr>
                            <td><strong>Presenças no Mês</strong></td>
                            <td class="text-right"><?= $dados_relatorio['presencas_mes'] ?? 0 ?></td>
                        </tr>
                        <tr>
                            <td><strong>Faltas no Mês</strong></td>
                            <td class="text-right"><?= $dados_relatorio['faltas_mes'] ?? 0 ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total de Aulas</strong></td>
                            <td class="text-right"><?= $dados_relatorio['total_aulas'] ?? 0 ?></td>
                        </tr>
                        <tr style="background: #f8fafc; font-weight: 700;">
                            <td><strong>Taxa de Presença</strong></td>
                            <td class="text-right" style="font-size: 16px; color: #c9a84c;">
                                <?= ($dados_relatorio['taxa_presenca'] ?? 0) ?>%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== RELATÓRIO DE FREQUÊNCIA ===== -->
        <?php elseif ($tipo_relatorio == 'frequencia'): ?>
        
        <div class="card-print">
            <div class="card-header">
                <h5><i class="fas fa-clipboard-check"></i> Frequência dos Alunos</h5>
                <span class="badge-count"><?= count($dados_relatorio) ?> alunos</span>
            </div>
            <div class="table-responsive-print">
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Aluno</th>
                            <th>Turma</th>
                            <th class="text-center">Total Aulas</th>
                            <th class="text-center">Presenças</th>
                            <th class="text-center">Faltas</th>
                            <th class="text-center">% Presença</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($dados_relatorio as $linha): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($linha['aluno_nome'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($linha['turma_nome'] ?? '-') ?></td>
                            <td class="text-center"><?= $linha['total_aulas'] ?? 0 ?></td>
                            <td class="text-center"><?= $linha['presencas'] ?? 0 ?></td>
                            <td class="text-center"><?= $linha['faltas'] ?? 0 ?></td>
                            <td class="text-center">
                                <?php 
                                $percentual = $linha['percentual_presenca'] ?? 0;
                                echo number_format($percentual, 1, ',', '.') . '%';
                                ?>
                            </td>
                            <td class="text-center">
                                <?php 
                                $percentual = $linha['percentual_presenca'] ?? 0;
                                if ($percentual >= 75) {
                                    echo '<span class="badge-print alta">✅ Alto</span>';
                                } elseif ($percentual >= 50) {
                                    echo '<span class="badge-print media">⚠️ Médio</span>';
                                } else {
                                    echo '<span class="badge-print baixa">❌ Baixo</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== RELATÓRIO DE NOTAS ===== -->
        <?php elseif ($tipo_relatorio == 'notas'): ?>
        
        <div class="card-print">
            <div class="card-header">
                <h5><i class="fas fa-star"></i> Desempenho dos Alunos</h5>
                <span class="badge-count"><?= count($dados_relatorio) ?> alunos</span>
            </div>
            <div class="table-responsive-print">
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Aluno</th>
                            <th>Turma</th>
                            <th class="text-center">Média</th>
                            <th class="text-center">Avaliações</th>
                            <th class="text-center">Maior Nota</th>
                            <th class="text-center">Menor Nota</th>
                            <th class="text-center">Classificação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($dados_relatorio as $linha): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($linha['aluno_nome'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($linha['turma_nome'] ?? '-') ?></td>
                            <td class="text-center" style="font-weight: 700; font-size: 15px;">
                                <?= number_format($linha['media_geral'] ?? 0, 1, ',', '.') ?>
                            </td>
                            <td class="text-center"><?= $linha['total_avaliacoes'] ?? 0 ?></td>
                            <td class="text-center"><?= number_format($linha['maior_nota'] ?? 0, 1, ',', '.') ?></td>
                            <td class="text-center"><?= number_format($linha['menor_nota'] ?? 0, 1, ',', '.') ?></td>
                            <td class="text-center">
                                <?php 
                                $classificacao = $linha['classificacao'] ?? 'Regular';
                                $class_map = [
                                    'Excelente' => 'excelente',
                                    'Bom' => 'bom',
                                    'Regular' => 'regular',
                                    'Precisa Melhorar' => 'precisa'
                                ];
                                $class = $class_map[$classificacao] ?? 'regular';
                                ?>
                                <span class="badge-print <?= $class ?>">
                                    <?= htmlspecialchars($classificacao) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== RELATÓRIO DE TURMAS ===== -->
        <?php elseif ($tipo_relatorio == 'turmas'): ?>
        
        <div class="card-print">
            <div class="card-header">
                <h5><i class="fas fa-building"></i> Resumo por Turma</h5>
                <span class="badge-count"><?= count($dados_relatorio) ?> turmas</span>
            </div>
            <div class="table-responsive-print">
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Turma</th>
                            <th>Classe</th>
                            <th>Disciplinas</th>
                            <th class="text-center">Alunos</th>
                            <th class="text-center">Presenças</th>
                            <th class="text-center">Faltas</th>
                            <th class="text-center">Média Turma</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($dados_relatorio as $linha): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($linha['turma_nome'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($linha['classe'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($linha['disciplinas'] ?? '-') ?></td>
                            <td class="text-center"><?= $linha['total_alunos'] ?? 0 ?></td>
                            <td class="text-center"><?= $linha['presencas'] ?? 0 ?></td>
                            <td class="text-center"><?= $linha['faltas'] ?? 0 ?></td>
                            <td class="text-center" style="font-weight: 700;">
                                <?= number_format($linha['media_turma'] ?? 0, 1, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>

    <!-- ========================================== -->
    <!-- NENHUM DADO ENCONTRADO -->
    <!-- ========================================== -->
    <?php elseif ($mostrar_tabela): ?>
    
    <div class="empty-state-print">
        <div class="icon"><i class="fas fa-inbox"></i></div>
        <h4>Nenhum dado encontrado</h4>
        <p>Não há registros para os filtros selecionados.</p>
        <p style="font-size: 13px; color: #94a3b8; margin-top: 5px;">
            Tente ajustar o período ou selecionar outra turma.
        </p>
    </div>

    <?php else: ?>
    
    <!-- ===== ESTADO INICIAL ===== -->
    <div class="empty-state-print">
        <div class="icon"><i class="fas fa-chart-pie"></i></div>
        <h4>Selecione os filtros e gere o relatório</h4>
        <p>Utilize os filtros acima para gerar relatórios personalizados.</p>
    </div>

    <?php endif; ?>

    <!-- ========================================== -->
    <!-- ASSINATURAS -->
    <!-- ========================================== -->
    <?php if ($mostrar_tabela && !empty($dados_relatorio)): ?>
    <div class="assinatura-container">
        <div class="assinatura">
            <div class="label">_________________________</div>
            <div class="linha"><?= htmlspecialchars($professor_nome_completo ?: $professor_nome) ?></div>
            <div class="data">Professor / Docente</div>
        </div>
        <div class="assinatura">
            <div class="label">_________________________</div>
            <div class="linha">Diretor Pedagógico</div>
            <div class="data">Carimbo e Assinatura</div>
        </div>
        <div class="assinatura">
            <div class="label">_________________________</div>
            <div class="linha"><?= htmlspecialchars($nome_escola) ?></div>
            <div class="data"><?= date('d/m/Y') ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- RODAPÉ -->
    <!-- ========================================== -->
    <div class="footer-print">
        <div class="left">
            <strong><?= htmlspecialchars($nome_escola) ?></strong><br>
            Sistema de Gestão Escolar - SoftGest Web v4.0<br>
            Relatório gerado em <?= date('d/m/Y H:i:s') ?>
        </div>
        <div class="right">
            Página 1/1<br>
            Documento: #REL-<?= date('Ymd') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?><br>
            <span style="font-size: 10px; color: #94a3b8;">
                Este documento é de uso interno e de caráter pedagógico.
            </span>
        </div>
    </div>

</div>

<!-- ========================================== -->
<!-- BOTÕES DE CONTROLE (NÃO IMPRIMEM) -->
<!-- ========================================== -->
<div class="controls no-print" style="max-width: 210mm; margin: 20px auto;">
    <button class="btn btn-primary" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'">
        <i class="fas fa-sync-alt"></i> Novo Relatório
    </button>
    <button class="btn btn-secondary" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimir
    </button>
    <button class="btn btn-success" onclick="exportarExcel()">
        <i class="fas fa-file-excel"></i> Exportar Excel
    </button>
    <button class="btn btn-danger" onclick="exportarPDF()">
        <i class="fas fa-file-pdf"></i> Exportar PDF
    </button>
</div>

<!-- ========================================== -->
<!-- FORMULÁRIO DE FILTROS (NÃO IMPRIME) -->
<!-- ========================================== -->
<div class="no-print" style="max-width: 210mm; margin: 0 auto 20px auto;">
    <form method="GET" action="" class="filters-form">
        <div class="form-group">
            <label><i class="fas fa-file-alt"></i> Tipo</label>
            <select name="tipo" id="tipoRelatorio">
                <option value="geral" <?= $tipo_relatorio == 'geral' ? 'selected' : '' ?>>📊 Geral</option>
                <option value="frequencia" <?= $tipo_relatorio == 'frequencia' ? 'selected' : '' ?>>✅ Frequência</option>
                <option value="notas" <?= $tipo_relatorio == 'notas' ? 'selected' : '' ?>>📝 Notas</option>
                <option value="turmas" <?= $tipo_relatorio == 'turmas' ? 'selected' : '' ?>>🏫 Turmas</option>
            </select>
        </div>
        
        <div class="form-group">
            <label><i class="fas fa-calendar-alt"></i> Início</label>
            <input type="date" name="data_inicio" value="<?= $data_inicio ?>">
        </div>
        
        <div class="form-group">
            <label><i class="fas fa-calendar-alt"></i> Fim</label>
            <input type="date" name="data_fim" value="<?= $data_fim ?>">
        </div>
        
        <div class="form-group">
            <label><i class="fas fa-users"></i> Turma</label>
            <select name="turma_id">
                <option value="">Todas</option>
                <?php foreach ($turmas as $turma): ?>
                    <option value="<?= htmlspecialchars($turma['turma_nome']) ?>" 
                        <?= $turma_id == $turma['turma_nome'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($turma['turma_nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label><i class="fas fa-book"></i> Disciplina</label>
            <select name="disciplina_id">
                <option value="">Todas</option>
                <?php foreach ($disciplinas as $disc): ?>
                    <option value="<?= htmlspecialchars($disc['nome']) ?>"
                        <?= $disciplina_id == $disc['nome'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($disc['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="flex: 0 0 auto;">
            <input type="hidden" name="action" value="view">
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Gerar
            </button>
        </div>
    </form>
</div>

<script>
    // ==========================================
    // AUTO-SUBMIT AO MUDAR TIPO
    // ==========================================
    document.getElementById('tipoRelatorio')?.addEventListener('change', function() {
        this.closest('form').submit();
    });

    // ==========================================
    // EXPORTAR FUNCTIONS
    // ==========================================
    function exportarExcel() {
        const form = document.querySelector('.filters-form');
        if (form) {
            const params = new URLSearchParams(new FormData(form));
            params.append('export', 'excel');
            window.location.href = window.location.pathname + '?' + params.toString();
        }
    }

    function exportarPDF() {
        const form = document.querySelector('.filters-form');
        if (form) {
            const params = new URLSearchParams(new FormData(form));
            params.append('export', 'pdf');
            window.location.href = window.location.pathname + '?' + params.toString();
        }
    }

    // ==========================================
    // IMPRIMIR COM CONFIGURAÇÃO A4
    // ==========================================
    function imprimirRelatorio() {
        window.print();
    }

    console.log('📄 Sistema de Relatórios - Modo Impressão A4');
    console.log('📋 Professor: <?= htmlspecialchars($professor_nome) ?>');
    console.log('📊 Relatório: <?= $titulo_relatorio ?>');
</script>

</body>
</html>