<?php
/**
 * Gerador de Excel - Relatório de Mensalidades
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
// 2. CARREGA BIBLIOTECA PHPSPREADSHEET
// ============================================

require_once '../../../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

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
        a.matricula,
        a.nome AS aluno_nome,
        a.cpf,
        a.telefone,
        a.email,
        c.nome AS curso_nome,
        c.sigla AS curso_sigla,
        t.nome AS turma_nome,
        t.ano_letivo,
        m.data_vencimento,
        m.data_pagamento,
        m.valor_original,
        m.valor_pago,
        m.valor_desconto,
        m.valor_juros,
        m.valor_multa,
        m.situacao,
        CASE 
            WHEN m.situacao = 'PAGO' THEN 'Pago'
            WHEN m.situacao = 'PENDENTE' AND m.data_vencimento < CURDATE() THEN 'Vencido'
            WHEN m.situacao = 'PENDENTE' AND m.data_vencimento >= CURDATE() THEN 'A Vencer'
            WHEN m.situacao = 'CANCELADO' THEN 'Cancelado'
            WHEN m.situacao = 'REEMBOLSADO' THEN 'Reembolsado'
            ELSE m.situacao
        END AS status_descricao
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
// 6. CRIAÇÃO DO EXCEL
// ============================================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ============================================
// 7. HEADER DO RELATÓRIO
// ============================================

// Título principal
$sheet->setCellValue('A1', 'RELATÓRIO DE MENSALIDADES');
$sheet->mergeCells('A1:M1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Subtítulo
$sheet->setCellValue('A2', 'Softgest - Sistema de Gestão Escolar');
$sheet->mergeCells('A2:M2');
$sheet->getStyle('A2')->getFont()->setSize(12);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Filtros
$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

$linha_filtros = 4;
$sheet->setCellValue("A{$linha_filtros}", 'Filtros aplicados:');
$sheet->getStyle("A{$linha_filtros}")->getFont()->setBold(true);

$sheet->setCellValue("A" . ($linha_filtros + 1), "Ano: {$ano}");
$sheet->setCellValue("C" . ($linha_filtros + 1), "Mês: " . ($mes > 0 ? $meses[$mes] : 'Todos'));
$sheet->setCellValue("E" . ($linha_filtros + 1), "Situação: " . ($situacao == 'todos' ? 'Todos' : $situacao));
$sheet->setCellValue("G" . ($linha_filtros + 1), "Gerado em: " . date('d/m/Y H:i:s'));

if (!empty($busca)) {
    $sheet->setCellValue("I" . ($linha_filtros + 1), "Busca: {$busca}");
}

// ============================================
// 8. TOTAIS
// ============================================

$linha_totais = $linha_filtros + 3;

$totais_cabecalho = ['Total Mensalidades', 'Valor Total', 'Recebido', 'A Receber', 'Descontos', 'Juros', 'Multas'];
$totais_valores = [
    number_format($totais['quantidade'], 0, ',', '.'),
    'R$ ' . number_format($totais['valor_original'], 2, ',', '.'),
    'R$ ' . number_format($totais['recebido'], 2, ',', '.'),
    'R$ ' . number_format($totais['valor_original'] - $totais['recebido'], 2, ',', '.'),
    'R$ ' . number_format($totais['valor_desconto'], 2, ',', '.'),
    'R$ ' . number_format($totais['valor_juros'], 2, ',', '.'),
    'R$ ' . number_format($totais['valor_multa'], 2, ',', '.')
];

$coluna_atual = 'A';
foreach ($totais_cabecalho as $index => $cabecalho) {
    $celula = $coluna_atual . $linha_totais;
    $sheet->setCellValue($celula, $cabecalho);
    $sheet->getStyle($celula)->getFont()->setBold(true);
    $sheet->getStyle($celula)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($celula)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9ECEF');
    
    $celula_valor = $coluna_atual . ($linha_totais + 1);
    $sheet->setCellValue($celula_valor, $totais_valores[$index]);
    $sheet->getStyle($celula_valor)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($celula_valor)->getFont()->setBold(true);
    
    // Aplica cor condicional para valores financeiros
    if ($index == 2) { // Recebido
        $sheet->getStyle($celula_valor)->getFont()->getColor()->setRGB('155724');
    } elseif ($index == 3) { // A Receber
        $sheet->getStyle($celula_valor)->getFont()->getColor()->setRGB('721c24');
    } elseif ($index == 4) { // Descontos
        $sheet->getStyle($celula_valor)->getFont()->getColor()->setRGB('0c5460');
    }
    
    $coluna_atual++;
}

// ============================================
// 9. TABELA DE DADOS
// ============================================

$linha_tabela = $linha_totais + 3;

// Cabeçalho da tabela
$cabecalhos = [
    'Matrícula', 'Aluno', 'CPF', 'Telefone', 'Email',
    'Curso', 'Turma', 'Vencimento', 'Pagamento', 
    'Valor', 'Pago', 'Desconto', 'Juros', 'Multa', 'Status'
];

$colunas = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];

foreach ($cabecalhos as $index => $cabecalho) {
    $celula = $colunas[$index] . $linha_tabela;
    $sheet->setCellValue($celula, $cabecalho);
    $sheet->getStyle($celula)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
    $sheet->getStyle($celula)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2C3E50');
    $sheet->getStyle($celula)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Dados
$linha = $linha_tabela + 1;
foreach ($dados as $row) {
    $sheet->setCellValue('A' . $linha, $row['matricula']);
    $sheet->setCellValue('B' . $linha, $row['aluno_nome']);
    $sheet->setCellValue('C' . $linha, $row['cpf']);
    $sheet->setCellValue('D' . $linha, $row['telefone']);
    $sheet->setCellValue('E' . $linha, $row['email']);
    $sheet->setCellValue('F' . $linha, $row['curso_sigla'] ?? $row['curso_nome']);
    $sheet->setCellValue('G' . $linha, $row['turma_nome']);
    $sheet->setCellValue('H' . $linha, date('d/m/Y', strtotime($row['data_vencimento'])));
    $sheet->setCellValue('I' . $linha, $row['data_pagamento'] ? date('d/m/Y', strtotime($row['data_pagamento'])) : '-');
    $sheet->setCellValue('J' . $linha, $row['valor_original']);
    $sheet->setCellValue('K' . $linha, $row['valor_pago']);
    $sheet->setCellValue('L' . $linha, $row['valor_desconto']);
    $sheet->setCellValue('M' . $linha, $row['valor_juros']);
    $sheet->setCellValue('N' . $linha, $row['valor_multa']);
    $sheet->setCellValue('O' . $linha, $row['status_descricao']);
    
    // Formatação condicional do status
    $celula_status = 'O' . $linha;
    if ($row['status_descricao'] == 'Pago') {
        $sheet->getStyle($celula_status)->getFont()->getColor()->setRGB('155724');
    } elseif ($row['status_descricao'] == 'Vencido') {
        $sheet->getStyle($celula_status)->getFont()->getColor()->setRGB('721c24');
    } elseif ($row['status_descricao'] == 'A Vencer') {
        $sheet->getStyle($celula_status)->getFont()->getColor()->setRGB('856404');
    }
    
    $linha++;
}

// ============================================
// 10. FORMATAÇÃO DA PLANILHA
// ============================================

// Ajusta largura das colunas
foreach (range('A', 'O') as $coluna) {
    $sheet->getColumnDimension($coluna)->setAutoSize(true);
}

// Formata os valores monetários
$ultima_linha = $linha - 1;
for ($i = $linha_tabela + 1; $i <= $ultima_linha; $i++) {
    $sheet->getStyle("J{$i}:N{$i}")->getNumberFormat()->setFormatCode('"R$ "#,##0.00');
}

// Aplica bordas na tabela
$range_tabela = "A{$linha_tabela}:O{$ultima_linha}";
$sheet->getStyle($range_tabela)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Alterna cores das linhas
for ($i = $linha_tabela + 1; $i <= $ultima_linha; $i++) {
    if ($i % 2 == 0) {
        $sheet->getStyle("A{$i}:O{$i}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
    }
}

// ============================================
// 11. RODAPÉ
// ============================================

$linha_rodape = $ultima_linha + 2;
$sheet->setCellValue("A{$linha_rodape}", "Relatório gerado automaticamente pelo Softgest Sistemas © " . date('Y'));
$sheet->mergeCells("A{$linha_rodape}:O{$linha_rodape}");
$sheet->getStyle("A{$linha_rodape}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$linha_rodape}")->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('6C757D'));

// ============================================
// 12. DOWNLOAD DO ARQUIVO
// ============================================

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="relatorio_mensalidades_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit;
?>