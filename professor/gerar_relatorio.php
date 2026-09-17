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

// FORÇAR COLLATION
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("SET CHARACTER SET utf8mb4");
$pdo->exec("SET collation_connection = utf8mb4_unicode_ci");

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
    }
} catch (Exception $e) {}

// ==========================================
// BUSCAR DADOS DO PROFESSOR
// ==========================================
$professor_nome = $_SESSION['usuario_nome'] ?? 'Professor';

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
// BUSCAR ALUNOS
// ==========================================
$alunos = [];
if ($turma_id && !empty($turma_nome)) {
    try {
        $sql = "SELECT id, nome, Sexo as sexo 
                FROM alunos 
                WHERE TURMA COLLATE utf8mb4_unicode_ci = ? 
                AND status = 'ativo' 
                ORDER BY nome";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$turma_nome]);
        $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao buscar alunos: " . $e->getMessage());
    }
}

// ==========================================
// BUSCAR FREQUÊNCIAS E PROCESSAR POR ALUNO
// ==========================================
$dados_alunos = [];
$total_presentes = 0;
$total_ausentes = 0;
$total_justificados = 0;
$total_registros = 0;
$dias_unicos = [];
$datas_presentes = [];
$datas_ausentes = [];
$datas_justificados = [];

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
        
        // ==========================================
        // PROCESSAR POR ALUNO COM CONTAGEM POR DATA
        // ==========================================
        foreach ($frequencias as $f) {
            $aluno_id = $f['aluno_id'];
            $data = $f['data'];
            
            if (!isset($dados_alunos[$aluno_id])) {
                $dados_alunos[$aluno_id] = [
                    'nome' => $f['aluno_nome'],
                    'sexo' => $f['aluno_sexo'],
                    'presentes' => 0,
                    'ausentes' => 0,
                    'justificados' => 0,
                    'total' => 0,
                    'tem_presente' => false,
                    'tem_ausente' => false,
                    'tem_justificado' => false,
                    'disciplinas_presentes' => [],
                    'disciplinas_ausentes' => [],
                    'disciplinas_justificados' => [],
                    'datas_presentes' => [],
                    'datas_ausentes' => [],
                    'datas_justificados' => [],
                    'presencas_por_data' => [],
                    'faltas_por_data' => [],
                    'justificados_por_data' => []
                ];
            }
            
            // Contar por status (acumulando por aluno)
            if ($f['status'] == 'presente') {
                $dados_alunos[$aluno_id]['presentes']++;
                $dados_alunos[$aluno_id]['tem_presente'] = true;
                $dados_alunos[$aluno_id]['disciplinas_presentes'][] = $f['disciplina_id'];
                if (!in_array($data, $dados_alunos[$aluno_id]['datas_presentes'])) {
                    $dados_alunos[$aluno_id]['datas_presentes'][] = $data;
                    $dados_alunos[$aluno_id]['presencas_por_data'][$data] = ($dados_alunos[$aluno_id]['presencas_por_data'][$data] ?? 0) + 1;
                }
                $total_presentes++;
                $datas_presentes[$data] = ($datas_presentes[$data] ?? 0) + 1;
            } elseif ($f['status'] == 'ausente') {
                $dados_alunos[$aluno_id]['ausentes']++;
                $dados_alunos[$aluno_id]['tem_ausente'] = true;
                $dados_alunos[$aluno_id]['disciplinas_ausentes'][] = $f['disciplina_id'];
                if (!in_array($data, $dados_alunos[$aluno_id]['datas_ausentes'])) {
                    $dados_alunos[$aluno_id]['datas_ausentes'][] = $data;
                    $dados_alunos[$aluno_id]['faltas_por_data'][$data] = ($dados_alunos[$aluno_id]['faltas_por_data'][$data] ?? 0) + 1;
                }
                $total_ausentes++;
                $datas_ausentes[$data] = ($datas_ausentes[$data] ?? 0) + 1;
            } elseif ($f['status'] == 'justificado') {
                $dados_alunos[$aluno_id]['justificados']++;
                $dados_alunos[$aluno_id]['tem_justificado'] = true;
                $dados_alunos[$aluno_id]['disciplinas_justificados'][] = $f['disciplina_id'];
                if (!in_array($data, $dados_alunos[$aluno_id]['datas_justificados'])) {
                    $dados_alunos[$aluno_id]['datas_justificados'][] = $data;
                    $dados_alunos[$aluno_id]['justificados_por_data'][$data] = ($dados_alunos[$aluno_id]['justificados_por_data'][$data] ?? 0) + 1;
                }
                $total_justificados++;
                $datas_justificados[$data] = ($datas_justificados[$data] ?? 0) + 1;
            }
            $dados_alunos[$aluno_id]['total']++;
            
            $dias_unicos[$f['data']] = true;
        }
        
        $total_registros = $total_presentes + $total_ausentes + $total_justificados;
        $dias_letivos = count($dias_unicos);
        
        // ==========================================
        // CALCULAR PERCENTUAIS POR ALUNO
        // ==========================================
        foreach ($dados_alunos as $id => &$aluno) {
            $total = $aluno['total'];
            
            // PERCENTUAL POR ALUNO
            $percentual = $total > 0 ? round(($aluno['presentes'] / $total) * 100) : 0;
            $aluno['percentual'] = $percentual;
            
            // STATUS DO ALUNO (Completo se todas as disciplinas estão marcadas)
            $total_disciplinas = count($disciplinas_filtro);
            $marcadas = $aluno['presentes'] + $aluno['ausentes'] + $aluno['justificados'];
            $aluno['status_aluno'] = ($marcadas == $total_disciplinas && $total_disciplinas > 0) ? 'Completo' : 'Pendente';
            
            // CLASSIFICAÇÃO DO ALUNO
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
// CALCULAR ESTATÍSTICAS GERAIS
// ==========================================
$total_alunos = count($dados_alunos);

// CONTAGEM POR ALUNO
$alunos_presentes = 0;
$alunos_ausentes = 0;
$alunos_justificados = 0;
$alunos_completos = 0;
$alunos_pendentes = 0;

foreach ($dados_alunos as $aluno) {
    if ($aluno['tem_presente']) $alunos_presentes++;
    if ($aluno['tem_ausente']) $alunos_ausentes++;
    if ($aluno['tem_justificado']) $alunos_justificados++;
    
    if ($aluno['status_aluno'] == 'Completo') {
        $alunos_completos++;
    } else {
        $alunos_pendentes++;
    }
}

// Média de presença POR ALUNO
$media_presenca = $total_alunos > 0 ? round(($alunos_presentes / $total_alunos) * 100) : 0;

// Melhor e pior aluno
$melhor_aluno = null;
$pior_aluno = null;
if (!empty($dados_alunos)) {
    $alunos_array = array_values($dados_alunos);
    $melhor_aluno = $alunos_array[0] ?? null;
    $pior_aluno = end($alunos_array) ?: null;
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

// Distribuição dos alunos
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
        .header {
            border-bottom: 4px solid #1a56db;
            padding-bottom: 20px;
            margin-bottom: 30px;
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
        .info-turma {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .card-stat {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px 20px;
            text-align: center;
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
        .card-stat.alunos .number { color: #1a56db; }
        
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
        .status-badge.completo { background: #ecfdf5; color: #065f46; }
        .status-badge.pendente { background: #fffbeb; color: #92400e; }
        
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
        .distribuicao-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        .distribuicao-item {
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .distribuicao-item .numero {
            font-size: 24px;
            font-weight: 700;
        }
        .distribuicao-item .rotulo {
            font-size: 11px;
            font-weight: 500;
        }
        .distribuicao-item.excelente { background: #ecfdf5; border-color: #22c55e; }
        .distribuicao-item.excelente .numero { color: #22c55e; }
        .distribuicao-item.bom { background: #dbeafe; border-color: #3b82f6; }
        .distribuicao-item.bom .numero { color: #3b82f6; }
        .distribuicao-item.regular { background: #fffbeb; border-color: #f59e0b; }
        .distribuicao-item.regular .numero { color: #f59e0b; }
        .distribuicao-item.ruim { background: #fef2f2; border-color: #ef4444; }
        .distribuicao-item.ruim .numero { color: #ef4444; }
        .distribuicao-item.pessimo { background: #fef2f2; border-color: #dc2626; }
        .distribuicao-item.pessimo .numero { color: #dc2626; }
        
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
    
    <!-- ===== CABEÇALHO ===== -->
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
                <div class="data-emissao">Emitido em: <?= date('d/m/Y H:i:s') ?></div>
                <div class="data-emissao">Ano Letivo: <?= $ano_letivo ?></div>
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
            <span class="value"><?= $dias_letivos ?? 0 ?> dias</span>
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
                <div class="number"><?= $alunos_presentes ?></div>
                <div class="label">✅ Alunos Presentes</div>
                <div class="percent"><?= $total_alunos > 0 ? round(($alunos_presentes / $total_alunos) * 100) : 0 ?>%</div>
            </div>
            <div class="card-stat ausente">
                <div class="number"><?= $alunos_ausentes ?></div>
                <div class="label">❌ Alunos com Falta</div>
                <div class="percent"><?= $total_alunos > 0 ? round(($alunos_ausentes / $total_alunos) * 100) : 0 ?>%</div>
            </div>
            <div class="card-stat justificado">
                <div class="number"><?= $alunos_justificados ?></div>
                <div class="label">📝 Alunos Justificados</div>
                <div class="percent"><?= $total_alunos > 0 ? round(($alunos_justificados / $total_alunos) * 100) : 0 ?>%</div>
            </div>
            <div class="card-stat total">
                <div class="number"><?= $total_alunos ?></div>
                <div class="label">👨‍🎓 Total de Alunos</div>
                <div class="percent">100%</div>
            </div>
            <div class="card-stat media">
                <div class="number"><?= $media_presenca ?>%</div>
                <div class="label">📈 Média de Presença</div>
                <div class="percent">Geral</div>
            </div>
        </div>
    </div>
    
    <!-- ===== CLASSIFICAÇÃO ===== -->
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
                    <p><?= $classificacao_turma == 'Excelente' ? 'Desempenho excepcional! Parabéns!' : ($classificacao_turma == 'Boa' ? 'Bom desempenho, continue melhorando!' : ($classificacao_turma == 'Regular' ? 'Desempenho na média, precisa melhorar.' : ($classificacao_turma == 'Ruim' ? 'Desempenho abaixo do esperado!' : 'Desempenho crítico!'))) ?></p>
                </div>
            </div>
            <div class="media">
                <div class="number"><?= $media_presenca ?>%</div>
                <div class="label">Média de Frequência</div>
            </div>
        </div>
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
                    <?= count($melhor_aluno['datas_presentes']) ?> dias presentes
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
                    <?= count($pior_aluno['datas_ausentes']) ?> dias com falta
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
    
    <!-- ===== DISTRIBUIÇÃO ===== -->
    <div class="section">
        <div class="section-title">
            <span class="icon">👨‍🎓</span>
            <h3>Distribuição dos Alunos por Desempenho</h3>
        </div>
        <div class="distribuicao-grid">
            <div class="distribuicao-item excelente">
                <div class="numero"><?= $alunos_excelente ?></div>
                <div class="rotulo">🏆 Excelente (90%+)</div>
            </div>
            <div class="distribuicao-item bom">
                <div class="numero"><?= $alunos_bom ?></div>
                <div class="rotulo">👍 Bom (75-89%)</div>
            </div>
            <div class="distribuicao-item regular">
                <div class="numero"><?= $alunos_regular ?></div>
                <div class="rotulo">📊 Regular (50-74%)</div>
            </div>
            <div class="distribuicao-item ruim">
                <div class="numero"><?= $alunos_ruim ?></div>
                <div class="rotulo">⚠️ Ruim (25-49%)</div>
            </div>
            <div class="distribuicao-item pessimo">
                <div class="numero"><?= $alunos_pessimo ?></div>
                <div class="rotulo">🚨 Péssimo (0-24%)</div>
            </div>
        </div>
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
    
    <!-- ===== CONTABILIDADE DE PRESENÇAS E FALTAS POR DATA ===== -->
    <div class="section">
        <div class="section-title">
            <span class="icon">📅</span>
            <h3>Contabilidades de Frequência</h3>
        </div>
        
        <!-- Resumo por data -->
        <div style="overflow-x:auto;margin-bottom:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th style="text-align:center;background:#ecfdf5;">✅ Presentes</th>
                        <th style="text-align:center;background:#fef2f2;">❌ Faltas</th>
                        <th style="text-align:center;background:#fffbeb;">📝 Justificados</th>
                        <th style="text-align:center;background:#f1f5f9;">📊 Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $todas_datas = array_keys($dias_unicos);
                    sort($todas_datas);
                    
                    foreach ($todas_datas as $data): 
                        $p = $datas_presentes[$data] ?? 0;
                        $a = $datas_ausentes[$data] ?? 0;
                        $j = $datas_justificados[$data] ?? 0;
                        $total_dia = $p + $a + $j;
                    ?>
                    <tr>
                        <td><strong><?= date('d/m/Y', strtotime($data)) ?></strong></td>
                        <td style="text-align:center;color:#22c55e;font-weight:600;background:#f0fdf4;"><?= $p ?></td>
                        <td style="text-align:center;color:#ef4444;font-weight:600;background:#fef2f2;"><?= $a ?></td>
                        <td style="text-align:center;color:#f59e0b;font-weight:600;background:#fffbeb;"><?= $j ?></td>
                        <td style="text-align:center;font-weight:600;"><?= $total_dia ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background:#f1f5f9;font-weight:700;">
                    <tr>
                        <td><strong>TOTAL</strong></td>
                        <td style="text-align:center;color:#22c55e;"><?= $total_presentes ?></td>
                        <td style="text-align:center;color:#ef4444;"><?= $total_ausentes ?></td>
                        <td style="text-align:center;color:#f59e0b;"><?= $total_justificados ?></td>
                        <td style="text-align:center;"><?= $total_registros ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- ===== TABELA ANALÍTICA COM CONTAGEM POR DATA ===== -->
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
                        <th style="text-align:center;background:#ecfdf5;">✅ Presente</th>
                        <th style="text-align:center;background:#fef2f2;">❌ Falta</th>
                        <th style="text-align:center;background:#fffbeb;">📝 Justif.</th>
                        <th style="text-align:center;background:#f1f5f9;">Dias Pres.</th>
                        <th style="text-align:center;background:#fef2f2;">Dias Falta</th>
                        <th style="text-align:center;">% Presença</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($dados_alunos as $aluno): 
                        $total_disciplinas = count($disciplinas_filtro);
                        $marcadas = $aluno['presentes'] + $aluno['ausentes'] + $aluno['justificados'];
                        $status_aluno = ($marcadas == $total_disciplinas && $total_disciplinas > 0) ? 'Completo' : 'Pendente';
                        $status_aluno_class = strtolower($status_aluno);
                        $dias_presentes = count($aluno['datas_presentes'] ?? []);
                        $dias_ausentes = count($aluno['datas_ausentes'] ?? []);
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($aluno['nome']) ?></strong></td>
                        <td><?= htmlspecialchars($aluno['sexo'] ?? '-') ?></td>
                        <td style="text-align:center;color:#22c55e;font-weight:600;background:#f0fdf4;">
                            <?= $aluno['tem_presente'] ? '✅ Sim' : '❌ Não' ?>
                        </td>
                        <td style="text-align:center;color:#ef4444;font-weight:600;background:#fef2f2;">
                            <?= $aluno['tem_ausente'] ? '✅ Sim' : '❌ Não' ?>
                        </td>
                        <td style="text-align:center;color:#f59e0b;font-weight:600;background:#fffbeb;">
                            <?= $aluno['tem_justificado'] ? '✅ Sim' : '❌ Não' ?>
                        </td>
                        <td style="text-align:center;font-weight:600;background:#f0fdf4;color:#22c55e;">
                            <?= $dias_presentes ?>
                        </td>
                        <td style="text-align:center;font-weight:600;background:#fef2f2;color:#ef4444;">
                            <?= $dias_ausentes ?>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex;align-items:center;gap:8px;justify-content:center;">
                                <div class="progress-bar">
                                    <div class="fill" style="width:<?= $aluno['percentual'] ?>%;background:<?= $aluno['classificacao_cor'] ?>;"></div>
                                </div>
                                <span style="font-size:12px;font-weight:600;min-width:35px;"><?= $aluno['percentual'] ?>%</span>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <span class="status-badge <?= $status_aluno_class ?>">
                                <?= $status_aluno == 'Completo' ? '✅ Completo' : '⏳ Pendente' ?>
                            </span>
                            <br>
                            <small style="font-size:10px;color:#94a3b8;">
                                <?= $marcadas ?>/<?= $total_disciplinas ?> disc.
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- LEGENDA -->
        <div style="margin-top:15px;padding:12px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;display:flex;gap:20px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="status-badge completo">✅ Completo</span>
                <span style="font-size:12px;color:#64748b;">= Aluno marcou todas as disciplinas</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="status-badge pendente">⏳ Pendente</span>
                <span style="font-size:12px;color:#64748b;">= Aluno ainda não marcou todas as disciplinas</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;color:#64748b;">
                    <strong>✅ Presente:</strong> Aluno tem pelo menos 1 P
                </span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;color:#64748b;">
                    <strong>📅 Dias Pres.:</strong> Dias que o aluno teve presença
                </span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;color:#64748b;">
                    <strong>📅 Dias Falta:</strong> Dias que o aluno teve falta
                </span>
            </div>
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

<!-- ===== BOTÕES ===== -->
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