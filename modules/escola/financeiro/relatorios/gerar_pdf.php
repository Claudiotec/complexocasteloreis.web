<?php
/**
 * Gerador de PDF - Relatório de Mensalidades
 * 
 * @package Softgest
 * @subpackage Modules/Escola/Financeiro/Relatorios
 * @version 1.0.0
 */

// ============================================
// 1. CONFIGURAÇÕES INICIAIS
// ============================================

session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] == '') {
    header('Location: ../../../../../login.php');
    exit;
}

// Carrega configurações
require_once '../../../../../config/config.php';
require_once '../../../../../config/database.php';
require_once '../../../../../includes/functions.php';

// ============================================
// 2. CARREGA BIBLIOTECA DOMPDF
// ============================================

require_once '../../../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ============================================
// 3. RECEBE E VALIDA PARÂMETROS
// ============================================

$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
$id_turma = isset($_GET['id_turma']) ? (int)$_GET['id_turma'] : 0;
$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
$situacao = isset($_GET['situacao']) ? $_GET['situacao'] : 'todos';
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// ============================================
// 4. CONEXÃO COM BANCO DE DADOS
// ============================================

try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
}

// ============================================
// 5. CONSULTA OS DADOS
// ============================================

$sql = "
    SELECT 
        m.id_mensalidade,
        m.id_aluno,
        m.data_vencimento,
        m.data_pagamento,
        m.valor_original,
        m.valor_pago,
        m.valor_desconto,
        m.valor_juros,
        m.valor_multa,
        m.situacao,
        a.nome AS aluno_nome,
        a.matricula,
        a.cpf,
        t.nome AS turma_nome,
        t.ano_letivo,
        c.nome AS curso_nome,
        c.sigla AS curso_sigla
    FROM 
        mensalidades m
    INNER JOIN 
        alunos a ON m.id_aluno = a.id_aluno
    INNER JOIN 
        turmas t ON m.id_turma = t.id_turma
    INNER JOIN 
        cursos c ON t.id_curso = c.id_curso
    WHERE 
        1=1
";

$params = [];

if ($ano > 0) {
    $sql .= " AND YEAR(m.data_vencimento) = ?";
    $params[] = $ano;
}

if ($mes > 0) {
    $sql .= " AND MONTH(m.data_vencimento) = ?";
    $params[] = $mes;
}

if ($id_turma > 0) {
    $sql .= " AND m.id_turma = ?";
    $params[] = $id_turma;
}

if ($id_curso > 0) {
    $sql .= " AND t.id_curso = ?";
    $params[] = $id_curso;
}

if ($situacao != 'todos' && !empty($situacao)) {
    $sql .= " AND m.situacao = ?";
    $params[] = $situacao;
}

if (!empty($busca)) {
    $sql .= " AND (a.nome LIKE ? OR a.matricula LIKE ? OR a.cpf LIKE ?)";
    $busca_like = "%" . $busca . "%";
    $params[] = $busca_like;
    $params[] = $busca_like;
    $params[] = $busca_like;
}

$sql .= " ORDER BY a.nome ASC, m.data_vencimento ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
$totais = [
    'quantidade' => 0,
    'valor_original' => 0,
    'valor_pago' => 0,
    'valor_desconto' => 0,
    'valor_juros' => 0,
    'valor_multa' => 0,
    'recebido' => 0
];

while ($row = $result->fetch_assoc()) {
    $dados[] = $row;
    $totais['quantidade']++;
    $totais['valor_original'] += $row['valor_original'];
    $totais['valor_pago'] += $row['valor_pago'];
    $totais['valor_desconto'] += $row['valor_desconto'];
    $totais['valor_juros'] += $row['valor_juros'];
    $totais['valor_multa'] += $row['valor_multa'];
    
    if ($row['situacao'] == 'PAGO') {
        $totais['recebido'] += $row['valor_pago'];
    }
}

$conn->close();

// ============================================
// 6. GERA O HTML DO PDF
// ============================================

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório de Mensalidades</title>
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11px;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 22px;
        }
        .header h2 {
            margin: 5px 0 0 0;
            color: #7f8c8d;
            font-size: 16px;
            font-weight: normal;
        }
        .header .info {
            margin-top: 8px;
            font-size: 12px;
            color: #7f8c8d;
        }
        .filtros {
            background: #f8f9fa;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 11px;
        }
        .filtros span {
            display: inline-block;
            margin-right: 20px;
        }
        .totais {
            background: #e9ecef;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .totais table {
            width: 100%;
            border-collapse: collapse;
        }
        .totais td {
            padding: 5px 10px;
            text-align: center;
            border-right: 1px solid #ced4da;
        }
        .totais td:last-child {
            border-right: none;
        }
        .totais .valor {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
        }
        .totais .rotulo {
            font-size: 10px;
            color: #6c757d;
        }
        table.dados {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.dados th {
            background: #2c3e50;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.dados td {
            padding: 6px;
            border-bottom: 1px solid #dee2e6;
            font-size: 10px;
        }
        table.dados tr:nth-child(even) {
            background: #f8f9fa;
        }
        .status-pago {
            color: #155724;
            font-weight: bold;
        }
        .status-pendente {
            color: #856404;
            font-weight: bold;
        }
        .status-vencido {
            color: #721c24;
            font-weight: bold;
        }
        .status-cancelado {
            color: #383d41;
        }
        .status-reembolsado {
            color: #0c5460;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            text-align: center;
            font-size: 9px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .text-success {
            color: #155724;
        }
        .text-danger {
            color: #721c24;
        }
        .text-info {
            color: #0c5460;
        }
        .text-warning {
            color: #856404;
        }
        .no-data {
            text-align: center;
            padding: 40px 0;
            color: #6c757d;
        }
        .no-data i {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-pago { background: #d4edda; color: #155724; }
        .badge-pendente { background: #fff3cd; color: #856404; }
        .badge-vencido { background: #f8d7da; color: #721c24; }
        .badge-cancelado { background: #e2e3e5; color: #383d41; }
        .badge-reembolsado { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <h1>RELATÓRIO DE MENSALIDADES</h1>
    <h2>Softgest - Sistema de Gestão Escolar</h2>
    <div class="info">
        Período: ' . ($mes > 0 ? $meses[$mes] . '/' : '') . $ano . ' | 
        Gerado em: ' . date('d/m/Y H:i:s') . '
    </div>
</div>

<!-- FILTROS APLICADOS -->
<div class="filtros">
    <strong>Filtros aplicados:</strong>
    <span>📅 Ano: ' . $ano . '</span>
    ' . ($mes > 0 ? '<span>📆 Mês: ' . $meses[$mes] . '</span>' : '<span>📆 Mês: Todos</span>') . '
    ' . ($id_curso > 0 ? '<span>📚 Curso: ' . getIdNome($conn, 'cursos', 'id_curso', $id_curso) . '</span>' : '<span>📚 Curso: Todos</span>') . '
    ' . ($id_turma > 0 ? '<span>🏫 Turma: ' . getIdNome($conn, 'turmas', 'id_turma', $id_turma) . '</span>' : '<span>🏫 Turma: Todas</span>') . '
    <span>📊 Situação: ' . ($situacao == 'todos' ? 'Todos' : $situacao) . '</span>
    ' . (!empty($busca) ? '<span>🔍 Busca: ' . $busca . '</span>' : '') . '
</div>

<!-- TOTAIS -->
<div class="totais">
    <table>
        <tr>
            <td>
                <div class="rotulo">Total de Mensalidades</div>
                <div class="valor">' . number_format($totais['quantidade'], 0, ',', '.') . '</div>
            </td>
            <td>
                <div class="rotulo">Valor Total</div>
                <div class="valor">R$ ' . number_format($totais['valor_original'], 2, ',', '.') . '</div>
            </td>
            <td>
                <div class="rotulo">Recebido</div>
                <div class="valor text-success">R$ ' . number_format($totais['recebido'], 2, ',', '.') . '</div>
            </td>
            <td>
                <div class="rotulo">A Receber</div>
                <div class="valor text-danger">R$ ' . number_format($totais['valor_original'] - $totais['recebido'], 2, ',', '.') . '</div>
            </td>
            <td>
                <div class="rotulo">Descontos</div>
                <div class="valor text-info">R$ ' . number_format($totais['valor_desconto'], 2, ',', '.') . '</div>
            </td>
            <td>
                <div class="rotulo">Juros</div>
                <div class="valor text-warning">R$ ' . number_format($totais['valor_juros'], 2, ',', '.') . '</div>
            </td>
            <td>
                <div class="rotulo">Multas</div>
                <div class="valor text-danger">R$ ' . number_format($totais['valor_multa'], 2, ',', '.') . '</div>
            </td>
        </tr>
    </table>
</div>

<!-- TABELA DE DADOS -->
';

if (count($dados) > 0) {
    $html .= '
    <table class="dados">
        <thead>
            <tr>
                <th width="8%">Matrícula</th>
                <th width="20%">Aluno</th>
                <th width="12%">Curso</th>
                <th width="12%">Turma</th>
                <th width="10%">Vencimento</th>
                <th width="10%">Valor</th>
                <th width="10%">Pago</th>
                <th width="10%">Status</th>
            </tr>
        </thead>
        <tbody>
    ';
    
    foreach ($dados as $row) {
        $status_class = 'status-' . strtolower($row['situacao']);
        
        // Define o status exibido
        $status_exibido = $row['situacao'];
        if ($row['situacao'] == 'PENDENTE' && strtotime($row['data_vencimento']) < time()) {
            $status_exibido = 'VENCIDO';
            $status_class = 'status-vencido';
        }
        
        $html .= '
            <tr>
                <td><strong>' . $row['matricula'] . '</strong></td>
                <td>' . $row['aluno_nome'] . '<br><small style="color:#6c757d;">' . $row['cpf'] . '</small></td>
                <td>' . ($row['curso_sigla'] ?? $row['curso_nome']) . '</td>
                <td>' . $row['turma_nome'] . '</td>
                <td>' . date('d/m/Y', strtotime($row['data_vencimento'])) . '</td>
                <td><strong>R$ ' . number_format($row['valor_original'], 2, ',', '.') . '</strong></td>
                <td>' . ($row['valor_pago'] > 0 ? 'R$ ' . number_format($row['valor_pago'], 2, ',', '.') : '-') . '</td>
                <td><span class="badge badge-' . strtolower($row['situacao']) . '">' . $status_exibido . '</span></td>
            </tr>
        ';
    }
    
    $html .= '
        </tbody>
    </table>
    ';
} else {
    $html .= '
    <div class="no-data">
        Nenhuma mensalidade encontrada com os filtros selecionados.
    </div>
    ';
}

// FOOTER
$html .= '
<div class="footer">
    Softgest Sistemas &copy; ' . date('Y') . ' - Versão 1.0 | Página {PAGE_NUM} de {PAGE_COUNT}
</div>

</body>
</html>
';

// ============================================
// 7. CONFIGURA E GERA O PDF
// ============================================

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Envia para download
$nome_arquivo = 'relatorio_mensalidades_' . date('Ymd_His') . '.pdf';
$dompdf->stream($nome_arquivo, ['Attachment' => true]);

exit;

/**
 * Função auxiliar para buscar nome por ID
 */
function getIdNome($conn, $tabela, $campo_id, $id) {
    $sql = "SELECT nome FROM {$tabela} WHERE {$campo_id} = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['nome'];
    }
    return 'Desconhecido';
}
?>