<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

// Verifica se é professor
$perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
if ($perfil != 'professor' && $perfil != 'docente') {
    header("Location: ../index.php");
    exit;
}

// Incluir configurações
require_once '../config/database.php';

// Configurar collation
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("SET CHARACTER SET utf8mb4");

// ==========================================
// RECEBER FILTROS
// ==========================================
$turma_id = $_GET['turma'] ?? null;
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$aluno_filtro = $_GET['aluno'] ?? null;
$status_filtro = $_GET['status'] ?? 'todos';
$tipo_relatorio = $_GET['tipo'] ?? 'analitico';
$formato = $_GET['formato'] ?? 'html';
$disciplinas_filtro = isset($_GET['disciplinas']) ? explode(',', $_GET['disciplinas']) : [];

// ==========================================
// BUSCAR DADOS DA EMPRESA
// ==========================================
$nome_escola = 'Sistema de Gestão Escolar';
$empresa_logo = '';
$endereco = '';
$telefone = '';
$email_escola = '';
$nif = '';
$ano_letivo = date('Y') . '/' . (date('Y') + 1);

try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa) {
        $nome_escola = $empresa['razao_social'] ?? $empresa['nome_fantasia'] ?? 'Sistema de Gestão Escolar';
        $endereco = $empresa['endereco'] ?? '';
        $telefone = $empresa['telefone'] ?? '';
        $email_escola = $empresa['email'] ?? '';
        $nif = $empresa['nif'] ?? '';
        $empresa_logo = $empresa['logo'] ?? '';
    }
} catch (Exception $e) {}

// ==========================================
// BUSCAR DADOS DO PROFESSOR
// ==========================================
$professor_nome = $_SESSION['usuario_nome'] ?? 'Professor';
$professor_email = $_SESSION['usuario_email'] ?? '';

// ==========================================
// BUSCAR DADOS DA TURMA
// ==========================================
$turma_nome = '';
$turma_classe = '';
if ($turma_id) {
    try {
        $stmt = $pdo->prepare("SELECT turma_nome, classe FROM destribuicao_professores WHERE turma_id = ? LIMIT 1");
        $stmt->execute([$turma_id]);
        $turma_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($turma_info) {
            $turma_nome = $turma_info['turma_nome'];
            $turma_classe = $turma_info['classe'];
        }
    } catch (Exception $e) {}
}

// ==========================================
// BUSCAR DISCIPLINAS DA TURMA
// ==========================================
$disciplinas_turma = [];
if ($turma_id) {
    try {
        $stmt = $pdo->prepare("SELECT disciplina_id, nome FROM disciplinas WHERE id IN (SELECT disciplina_id FROM destribuicao_professores WHERE turma_id = ?)");
        $stmt->execute([$turma_id]);
        $disciplinas_turma = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Se não houver disciplinas filtradas, usar todas da turma
if (empty($disciplinas_filtro) && !empty($disciplinas_turma)) {
    $disciplinas_filtro = array_column($disciplinas_turma, 'disciplina_id');
}

// ==========================================
// BUSCAR ALUNOS
// ==========================================
$alunos = [];
if ($turma_id && !empty($turma_nome)) {
    try {
        $sql = "SELECT id, nome, Sexo as sexo FROM alunos WHERE TURMA COLLATE utf8mb4_unicode_ci = ? AND status = 'ativo' ORDER BY nome";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$turma_nome]);
        $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ==========================================
// BUSCAR FREQUÊNCIAS
// ==========================================
$frequencias = [];
$dados_alunos = [];
$total_presentes = 0;
$total_ausentes = 0;
$total_justificados = 0;
$total_registros = 0;
$dias_letivos = 0;
$dias_unicos = [];

if (!empty($alunos) && !empty($disciplinas_filtro) && $turma_id) {
    try {
        $placeholders = implode(',', array_fill(0, count($disciplinas_filtro), '?'));
        $params = array_merge($disciplinas_filtro, [$turma_id, $data_inicio, $data_fim]);
        
        $sql = "SELECT f.*, a.nome as aluno_nome, a.Sexo as aluno_sexo 
                FROM frequencia f
                JOIN alunos a ON f.aluno_id = a.id
                WHERE f.disciplina_id IN ($placeholders) 
                AND f.turma_id = ? 
                AND f.data BETWEEN ? AND ?";
        
        if ($aluno_filtro) {
            $sql .= " AND f.aluno_id = ?";
            $params[] = $aluno_filtro;
        }
        
        $sql .= " ORDER BY a.nome, f.data";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $frequencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Processar dados
        foreach ($frequencias as $f) {
            $aluno_id = $f['aluno_id'];
            if (!isset($dados_alunos[$aluno_id])) {
                $dados_alunos[$aluno_id] = [
                    'nome' => $f['aluno_nome'],
                    'sexo' => $f['aluno_sexo'],
                    'presentes' => 0,
                    'ausentes' => 0,
                    'justificados' => 0,
                    'total' => 0,
                    'disciplinas' => []
                ];
            }
            
            if ($f['status'] == 'presente') {
                $dados_alunos[$aluno_id]['presentes']++;
                $total_presentes++;
            } elseif ($f['status'] == 'ausente') {
                $dados_alunos[$aluno_id]['ausentes']++;
                $total_ausentes++;
            } elseif ($f['status'] == 'justificado') {
                $dados_alunos[$aluno_id]['justificados']++;
                $total_justificados++;
            }
            $dados_alunos[$aluno_id]['total']++;
            
            $dias_unicos[$f['data']] = true;
        }
        
        $total_registros = $total_presentes + $total_ausentes + $total_justificados;
        $dias_letivos = count($dias_unicos);
        
        // Calcular percentuais
        foreach ($dados_alunos as $id => &$aluno) {
            $total = $aluno['total'];
            $percentual = $total > 0 ? round(($aluno['presentes'] / $total) * 100) : 0;
            $aluno['percentual'] = $percentual;
            
            // Classificação do aluno
            if ($percentual >= 90) {
                $aluno['classificacao'] = 'Excelente';
                $aluno['classificacao_cor'] = '#22c55e';
            } elseif ($percentual >= 75) {
                $aluno['classificacao'] = 'Bom';
                $aluno['classificacao_cor'] = '#3b82f6';
            } elseif ($percentual >= 50) {
                $aluno['classificacao'] = 'Regular';
                $aluno['classificacao_cor'] = '#f59e0b';
            } elseif ($percentual >= 25) {
                $aluno['classificacao'] = 'Ruim';
                $aluno['classificacao_cor'] = '#ef4444';
            } else {
                $aluno['classificacao'] = 'Péssimo';
                $aluno['classificacao_cor'] = '#dc2626';
            }
        }
        
        // Ordenar por percentual
        uasort($dados_alunos, function($a, $b) {
            return $b['percentual'] - $a['percentual'];
        });
    } catch (Exception $e) {
        error_log("Erro ao buscar frequências: " . $e->getMessage());
    }
}

// ==========================================
// CALCULAR ESTATÍSTICAS
// ==========================================
$total_alunos = count($dados_alunos);
$media_presenca = $total_registros > 0 ? round(($total_presentes / $total_registros) * 100) : 0;

// Aluno com maior e menor frequência
$melhor_aluno = null;
$pior_aluno = null;
if (!empty($dados_alunos)) {
    $primeiro = reset($dados_alunos);
    $ultimo = end($dados_alunos);
    if ($primeiro) $melhor_aluno = $primeiro;
    if ($ultimo) $pior_aluno = $ultimo;
}

// Classificação da turma
if ($media_presenca >= 90) {
    $classificacao_turma = 'Excelente';
    $classificacao_cor = '#22c55e';
    $classificacao_icone = '🏆';
    $sugestao = 'A turma apresenta um excelente índice de frequência. Mantenha as boas práticas pedagógicas e continue incentivando a participação dos alunos.';
} elseif ($media_presenca >= 75) {
    $classificacao_turma = 'Boa';
    $classificacao_cor = '#3b82f6';
    $classificacao_icone = '👍';
    $sugestao = 'A turma tem uma boa frequência. Sugere-se reforçar as estratégias de engajamento para alcançar a excelência.';
} elseif ($media_presenca >= 50) {
    $classificacao_turma = 'Regular';
    $classificacao_cor = '#f59e0b';
    $classificacao_icone = '📊';
    $sugestao = 'A frequência está na média. Recomenda-se investigar as causas das faltas e implementar ações corretivas.';
} elseif ($media_presenca >= 25) {
    $classificacao_turma = 'Ruim';
    $classificacao_cor = '#ef4444';
    $classificacao_icone = '⚠️';
    $sugestao = 'A frequência está abaixo do esperado. É necessário intervir urgentemente com medidas pedagógicas e disciplinares.';
} else {
    $classificacao_turma = 'Péssima';
    $classificacao_cor = '#dc2626';
    $classificacao_icone = '🚨';
    $sugestao = 'A frequência está crítica. Ação imediata necessária com envolvimento da coordenação e direção pedagógica.';
}

// Contagem por status
$alunos_excelente = 0;
$alunos_bom = 0;
$alunos_regular = 0;
$alunos_ruim = 0;
$alunos_pessimo = 0;

foreach ($dados_alunos as $aluno) {
    if ($aluno['percentual'] >= 90) $alunos_excelente++;
    elseif ($aluno['percentual'] >= 75) $alunos_bom++;
    elseif ($aluno['percentual'] >= 50) $alunos_regular++;
    elseif ($aluno['percentual'] >= 25) $alunos_ruim++;
    else $alunos_pessimo++;
}

// ==========================================
// SE FOR EXCEL, GERAR PLANILHA
// ==========================================
if ($formato == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Relatorio_Frequencia_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><title>Relatório de Frequência</title></head><body>';
}

// ==========================================
// GERAR RELATÓRIO HTML
// ==========================================
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Analítico de Frequência</title>
    <style>
        /* ===== ESTILOS DO RELATÓRIO ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Arial', 'Helvetica', sans-serif; 
            background: #f8fafc; 
            padding: 20px;
            color: #1e293b;
        }
        
        .relatorio-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        /* ===== CABEÇALHO ESTILO AGT ===== */
        .header {
            border-bottom: 4px solid #1a56db;
            padding-bottom: 20px;
            margin-bottom: 30px;
            position: relative;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-left .logo {
            width: 80px;
            height: 80px;
            background: #1a56db;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: 700;
        }
        
        .header-left .info h1 {
            color: #1a56db;
            font-size: 24px;
            margin-bottom: 2px;
        }
        
        .header-left .info p {
            color: #64748b;
            font-size: 13px;
            margin: 2px 0;
        }
        
        .header-right {
            text-align: right;
            border-left: 2px solid #e2e8f0;
            padding-left: 20px;
        }
        
        .header-right .badge {
            display: inline-block;
            background: #1a56db;
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .header-right .data-emissao {
            margin-top: 5px;
            font-size: 12px;
            color: #64748b;
        }
        
        .header-title {
            text-align: center;
            margin: 20px 0 10px;
            padding: 15px;
            background: #f1f5f9;
            border-radius: 8px;
        }
        
        .header-title h2 {
            color: #1e293b;
            font-size: 22px;
            letter-spacing: 2px;
        }
        
        .header-title p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        
        /* ===== INFO TURMA ===== */
        .info-turma {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }
        
        .info-turma .item {
            display: flex;
            flex-direction: column;
        }
        
        .info-turma .item .label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .info-turma .item .value {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-top: 2px;
        }
        
        /* ===== SEÇÃO ===== */
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .section-title .icon {
            font-size: 24px;
        }
        
        .section-title h3 {
            font-size: 18px;
            color: #1e293b;
        }
        
        /* ===== CARDS ===== */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .card-stat {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px 20px;
            text-align: center;
            transition: transform 0.2s;
        }
        
        .card-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .card-stat .number {
            font-size: 28px;
            font-weight: 700;
        }
        
        .card-stat .label {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
        
        .card-stat .percent {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 2px;
        }
        
        .card-stat.presente .number { color: #22c55e; }
        .card-stat.ausente .number { color: #ef4444; }
        .card-stat.justificado .number { color: #f59e0b; }
        .card-stat.total .number { color: #3b82f6; }
        .card-stat.media .number { color: #8b5cf6; }
        
        /* ===== CLASSIFICAÇÃO ===== */
        .classificacao-box {
            background: <?= $classificacao_cor ?>15;
            border: 2px solid <?= $classificacao_cor ?>;
            border-radius: 10px;
            padding: 20px 25px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .classificacao-box .left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .classificacao-box .left .icon {
            font-size: 40px;
        }
        
        .classificacao-box .left .info h4 {
            font-size: 20px;
            color: <?= $classificacao_cor ?>;
        }
        
        .classificacao-box .left .info p {
            font-size: 14px;
            color: #475569;
        }
        
        .classificacao-box .media {
            text-align: center;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .classificacao-box .media .number {
            font-size: 32px;
            font-weight: 700;
            color: <?= $classificacao_cor ?>;
        }
        
        .classificacao-box .media .label {
            font-size: 12px;
            color: #64748b;
        }
        
        /* ===== SUGESTÃO ===== */
        .sugestao-box {
            background: #f1f5f9;
            border-left: 4px solid #1a56db;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .sugestao-box h4 {
            color: #1a56db;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .sugestao-box p {
            color: #475569;
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* ===== TABELAS ===== */
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        table thead {
            background: #f1f5f9;
        }
        
        table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        
        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        table tbody tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.excelente { background: #ecfdf5; color: #065f46; }
        .status-badge.bom { background: #dbeafe; color: #1e40af; }
        .status-badge.regular { background: #fffbeb; color: #92400e; }
        .status-badge.ruim { background: #fef2f2; color: #991b1b; }
        .status-badge.pessimo { background: #fef2f2; color: #7f1d1d; }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            min-width: 80px;
        }
        
        .progress-bar .fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        /* ===== DESTAQUES ===== */
        .destaques-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .destaque-card {
            border-radius: 8px;
            padding: 15px 20px;
            border: 1px solid #e2e8f0;
        }
        
        .destaque-card.melhor {
            background: #ecfdf5;
            border-color: #bbf7d0;
        }
        
        .destaque-card.pior {
            background: #fef2f2;
            border-color: #fecaca;
        }
        
        .destaque-card .titulo {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .destaque-card .nome {
            font-size: 18px;
            font-weight: 700;
            margin: 5px 0;
        }
        
        .destaque-card .detalhes {
            font-size: 13px;
            color: #475569;
        }
        
        .destaque-card.melhor .nome { color: #22c55e; }
        .destaque-card.pior .nome { color: #ef4444; }
        
        /* ===== RODAPÉ ===== */
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .footer .assinaturas {
            display: flex;
            gap: 40px;
        }
        
        .footer .assinatura {
            text-align: center;
        }
        
        .footer .assinatura .linha {
            width: 150px;
            border-top: 1px solid #94a3b8;
            margin: 5px 0;
        }
        
        .footer .assinatura .cargo {
            font-size: 11px;
            color: #94a3b8;
        }
        
        /* ===== RESPONSIVE ===== */
        @media print {
            body { background: white; padding: 0; }
            .relatorio-container { box-shadow: none; padding: 20px; }
            .no-print { display: none !important; }
            .card-stat:hover { transform: none; }
            table tbody tr:hover { background: transparent; }
            .destaque-card { break-inside: avoid; }
        }
        
        @media (max-width: 768px) {
            .relatorio-container { padding: 15px; }
            .header-top { flex-direction: column; align-items: flex-start; }
            .header-right { border-left: none; padding-left: 0; text-align: left; }
            .destaques-grid { grid-template-columns: 1fr; }
            .classificacao-box { flex-direction: column; text-align: center; }
            .classificacao-box .left { flex-direction: column; }
            .footer { flex-direction: column; text-align: center; }
            .footer .assinaturas { flex-direction: column; align-items: center; }
            .cards-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 480px) {
            .cards-grid { grid-template-columns: 1fr; }
            .info-turma { grid-template-columns: 1fr; }
            .header-left .logo { width: 60px; height: 60px; font-size: 24px; }
            .header-left .info h1 { font-size: 18px; }
        }
    </style>
</head>
<body>
<div class="relatorio-container">
    
    <!-- ===== CABEÇALHO ESTILO AGT ===== -->
    <div class="header">
        <div class="header-top">
            <div class="header-left">
                <div class="logo">CE</div>
                <div class="info">
                    <h1><?= htmlspecialchars($nome_escola) ?></h1>
                    <p><?= htmlspecialchars($endereco) ?></p>
                    <p>📞 <?= htmlspecialchars($telefone) ?> | ✉ <?= htmlspecialchars($email_escola) ?> | NIF: <?= htmlspecialchars($nif) ?></p>
                </div>
            </div>
            <div class="header-right">
                <span class="badge">RELATÓRIO OFICIAL</span>
                <div class="data-emissao">
                    Emitido em: <?= date('d/m/Y H:i:s') ?>
                </div>
                <div class="data-emissao">
                    Ano Letivo: <?= $ano_letivo ?>
                </div>
            </div>
        </div>
        
        <div class="header-title">
            <h2>📊 RELATÓRIO ANALÍTICO DE FREQUÊNCIA</h2>
            <p>Relatório Pedagógico para Conselho de Classe</p>
        </div>
    </div>
    
    <!-- ===== INFO TURMA ===== -->
    <div class="info-turma">
        <div class="item">
            <span class="label">Turma</span>
            <span class="value"><?= htmlspecialchars($turma_nome) ?> - <?= htmlspecialchars($turma_classe) ?></span>
        </div>
        <div class="item">
            <span class="label">Professor</span>
            <span class="value"><?= htmlspecialchars($professor_nome) ?></span>
        </div>
        <div class="item">
            <span class="label">Período</span>
            <span class="value"><?= date('d/m/Y', strtotime($data_inicio)) ?> até <?= date('d/m/Y', strtotime($data_fim)) ?></span>
        </div>
        <div class="item">
            <span class="label">Dias Letivos</span>
            <span class="value"><?= $dias_letivos ?> dias</span>
        </div>
        <div class="item">
            <span class="label">Disciplinas</span>
            <span class="value"><?= count($disciplinas_filtro) ?> disciplinas</span>
        </div>
        <div class="item">
            <span class="label">Total Alunos</span>
            <span class="value"><?= $total_alunos ?> alunos</span>
        </div>
    </div>
    
    <!-- ===== RESUMO ESTATÍSTICO ===== -->
    <div class="section">
        <div class="section-title">
            <span class="icon">📊</span>
            <h3>Resumo Estatístico</h3>
        </div>
        
        <div class="cards-grid">
            <div class="card-stat presente">
                <div class="number"><?= $total_presentes ?></div>
                <div class="label">✅ Presenças</div>
                <div class="percent"><?= $total_registros > 0 ? round(($total_presentes / $total_registros) * 100) : 0 ?>%</div>
            </div>
            <div class="card-stat ausente">
                <div class="number"><?= $total_ausentes ?></div>
                <div class="label">❌ Faltas</div>
                <div class="percent"><?= $total_registros > 0 ? round(($total_ausentes / $total_registros) * 100) : 0 ?>%</div>
            </div>
            <div class="card-stat justificado">
                <div class="number"><?= $total_justificados ?></div>
                <div class="label">📝 Justificados</div>
                <div class="percent"><?= $total_registros > 0 ? round(($total_justificados / $total_registros) * 100) : 0 ?>%</div>
            </div>
            <div class="card-stat total">
                <div class="number"><?= $total_registros ?></div>
                <div class="label">📋 Total Registros</div>
                <div class="percent">100%</div>
            </div>
            <div class="card-stat media">
                <div class="number"><?= $media_presenca ?>%</div>
                <div class="label">📈 Média de Presença</div>
                <div class="percent">Geral</div>
            </div>
        </div>
    </div>
    
    <!-- ===== CLASSIFICAÇÃO DA TURMA ===== -->
    <div class="section">
        <div class="section-title">
            <span class="icon">📌</span>
            <h3>Classificação da Turma</h3>
        </div>
        
        <div class="classificacao-box">
            <div class="left">
                <span class="icon"><?= $classificacao_icone ?></span>
                <div class="info">
                    <h4><?= $classificacao_turma ?></h4>
                    <p><?= $classificacao_turma == 'Excelente' ? 'Desempenho excepcional! Parabéns!' : 
                           ($classificacao_turma == 'Boa' ? 'Bom desempenho, continue melhorando!' :
                           ($classificacao_turma == 'Regular' ? 'Desempenho na média, precisa melhorar.' :
                           ($classificacao_turma == 'Ruim' ? 'Desempenho abaixo do esperado!' :
                           'Desempenho crítico!'))); ?></p>
                </div>
            </div>
            <div class="media">
                <div class="number"><?= $media_presenca ?>%</div>
                <div class="label">Média de Frequência</div>
            </div>
        </div>
        
        <!-- Sugestão Pedagógica -->
        <div class="sugestao-box">
            <h4>💡 Sugestão Pedagógica</h4>
            <p><?= $sugestao ?></p>
        </div>
    </div>
    
    <!-- ===== DESTAQUES ===== -->
    <?php if ($melhor_aluno && $pior_aluno): ?>
    <div class="section">
        <div class="section-title">
            <span class="icon">🏆</span>
            <h3>Destaques da Turma</h3>
        </div>
        
        <div class="destaques-grid">
            <div class="destaque-card melhor">
                <div class="titulo">🏆 Melhor Aluno</div>
                <div class="nome"><?= htmlspecialchars($melhor_aluno['nome']) ?></div>
                <div class="detalhes">
                    <?= $melhor_aluno['percentual'] ?>% de presença | 
                    <?= $melhor_aluno['presentes'] ?> presentes em <?= $melhor_aluno['total'] ?> registros
                </div>
                <div style="margin-top:8px;">
                    <span class="status-badge <?= strtolower($melhor_aluno['classificacao']) ?>">
                        <?= $melhor_aluno['classificacao'] ?>
                    </span>
                </div>
            </div>
            <div class="destaque-card pior">
                <div class="titulo">⚠️ Aluno com Mais Faltas</div>
                <div class="nome"><?= htmlspecialchars($pior_aluno['nome']) ?></div>
                <div class="detalhes">
                    <?= $pior_aluno['percentual'] ?>% de presença | 
                    <?= $pior_aluno['ausentes'] ?> faltas em <?= $pior_aluno['total'] ?> registros
                </div>
                <div style="margin-top:8px;">
                    <span class="status-badge <?= strtolower($pior_aluno['classificacao']) ?>">
                        <?= $pior_aluno['classificacao'] ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ===== DISTRIBUIÇÃO DOS ALUNOS ===== -->
    <div class="section">
        <div class="section-title">
            <span class="icon">👨‍🎓</span>
            <h3>Distribuição dos Alunos por Desempenho</h3>
        </div>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:10px;margin-bottom:15px;">
            <div style="text-align:center;padding:12px;background:#22c55e15;border-radius:8px;border:1px solid #22c55e;">
                <div style="font-size:24px;font-weight:700;color:#22c55e;"><?= $alunos_excelente ?></div>
                <div style="font-size:11px;color:#065f46;font-weight:500;">🏆 Excelente (90%+)</div>
            </div>
            <div style="text-align:center;padding:12px;background:#3b82f615;border-radius:8px;border:1px solid #3b82f6;">
                <div style="font-size:24px;font-weight:700;color:#3b82f6;"><?= $alunos_bom ?></div>
                <div style="font-size:11px;color:#1e40af;font-weight:500;">👍 Bom (75-89%)</div>
            </div>
            <div style="text-align:center;padding:12px;background:#f59e0b15;border-radius:8px;border:1px solid #f59e0b;">
                <div style="font-size:24px;font-weight:700;color:#f59e0b;"><?= $alunos_regular ?></div>
                <div style="font-size:11px;color:#92400e;font-weight:500;">📊 Regular (50-74%)</div>
            </div>
            <div style="text-align:center;padding:12px;background:#ef444415;border-radius:8px;border:1px solid #ef4444;">
                <div style="font-size:24px;font-weight:700;color:#ef4444;"><?= $alunos_ruim ?></div>
                <div style="font-size:11px;color:#991b1b;font-weight:500;">⚠️ Ruim (25-49%)</div>
            </div>
            <div style="text-align:center;padding:12px;background:#dc262615;border-radius:8px;border:1px solid #dc2626;">
                <div style="font-size:24px;font-weight:700;color:#dc2626;"><?= $alunos_pessimo ?></div>
                <div style="font-size:11px;color:#7f1d1d;font-weight:500;">🚨 Péssimo (0-24%)</div>
            </div>
        </div>
        
        <!-- Barra de progresso geral -->
        <div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:4px;">
                <span>Progresso Geral da Turma</span>
                <span><?= $media_presenca ?>%</span>
            </div>
            <div style="width:100%;height:10px;background:#e2e8f0;border-radius:6px;overflow:hidden;">
                <div style="width:<?= $media_presenca ?>%;height:100%;background:<?= $classificacao_cor ?>;border-radius:6px;"></div>
            </div>
        </div>
    </div>
    
    <!-- ===== TABELA ANALÍTICA ===== -->
    <div class="section">
        <div class="section-title">
            <span class="icon">📋</span>
            <h3>Análise Detalhada por Aluno</h3>
            <span style="font-size:12px;color:#94a3b8;margin-left:auto;"><?= $total_alunos ?> alunos</span>
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Aluno</th>
                        <th>Sexo</th>
                        <th style="text-align:center;background:#ecfdf5;">✅ Presentes</th>
                        <th style="text-align:center;background:#fef2f2;">❌ Faltas</th>
                        <th style="text-align:center;background:#fffbeb;">📝 Justif.</th>
                        <th style="text-align:center;background:#f1f5f9;">Total</th>
                        <th style="text-align:center;">% Presença</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($dados_alunos as $aluno): 
                        $status_class = strtolower($aluno['classificacao']);
                        $total = $aluno['presentes'] + $aluno['ausentes'] + $aluno['justificados'];
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($aluno['nome']) ?></strong></td>
                        <td><?= htmlspecialchars($aluno['sexo'] ?? '-') ?></td>
                        <td style="text-align:center;color:#22c55e;font-weight:600;background:#f0fdf4;"><?= $aluno['presentes'] ?></td>
                        <td style="text-align:center;color:#ef4444;font-weight:600;background:#fef2f2;"><?= $aluno['ausentes'] ?></td>
                        <td style="text-align:center;color:#f59e0b;font-weight:600;background:#fffbeb;"><?= $aluno['justificados'] ?></td>
                        <td style="text-align:center;font-weight:600;"><?= $total ?></td>
                        <td style="text-align:center;">
                            <div style="display:flex;align-items:center;gap:8px;justify-content:center;">
                                <div class="progress-bar">
                                    <div class="fill" style="width:<?= $aluno['percentual'] ?>%;background:<?= $aluno['classificacao_cor'] ?>;"></div>
                                </div>
                                <span style="font-size:12px;font-weight:600;min-width:35px;"><?= $aluno['percentual'] ?>%</span>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <span class="status-badge <?= $status_class ?>">
                                <?= $aluno['classificacao'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- ===== RODAPÉ ===== -->
    <div class="footer">
        <div>
            <p><strong><?= htmlspecialchars($nome_escola) ?></strong></p>
            <p>Sistema de Gestão Escolar - SoftGest Web</p>
            <p>Relatório gerado em <?= date('d/m/Y H:i:s') ?></p>
        </div>
        <div class="assinaturas">
            <div class="assinatura">
                <div class="linha"></div>
                <div><strong>_________________________</strong></div>
                <div class="cargo">Professor</div>
            </div>
            <div class="assinatura">
                <div class="linha"></div>
                <div><strong>_________________________</strong></div>
                <div class="cargo">Coordenador Pedagógico</div>
            </div>
            <div class="assinatura">
                <div class="linha"></div>
                <div><strong>_________________________</strong></div>
                <div class="cargo">Diretor Pedagógico</div>
            </div>
        </div>
    </div>
    
</div>

<!-- ===== BOTÃO IMPRIMIR ===== -->
<div style="text-align:center;margin-top:20px;" class="no-print">
    <button onclick="window.print()" style="background:#1a56db;color:white;border:none;padding:12px 40px;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(26,86,219,0.3);">
        🖨️ Imprimir Relatório
    </button>
    <button onclick="window.close()" style="background:#e2e8f0;color:#475569;border:none;padding:12px 40px;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;margin-left:10px;">
        ✖ Fechar
    </button>
</div>

<?php if ($formato == 'excel'): ?>
</body></html>
<?php endif; ?>

</body>
</html>