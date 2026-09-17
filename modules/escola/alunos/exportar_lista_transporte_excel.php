<?php
// ============================================
// modules/escola/alunos/exportar_lista_transporte_excel.php - Exportar Lista Transporte Excel
// ============================================

require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para VISUALIZAR
bloquearAcesso('visualizar');

// ============================================
// 1. RECEBER PARÂMETROS
// ============================================
$classe = $_GET['classe'] ?? '';
$turma = $_GET['turma'] ?? '';
$periodo = $_GET['periodo'] ?? '';
$mes_referencia = $_GET['mes_referencia'] ?? '';
$status_pagamento = $_GET['status_pagamento'] ?? '';
$forma_pagamento = $_GET['forma_pagamento'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$busca_nome = $_GET['busca_nome'] ?? '';

// ============================================
// 2. INICIALIZAR
// ============================================
$pdo = null;
$alunos_lista = [];
$info_escola = [];

try {
    $pdo = conectarBanco();
    
    // Buscar informações da escola
    $stmt = $pdo->query("SELECT * FROM config_escola LIMIT 1");
    $info_escola = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$info_escola) {
        $info_escola = [
            'nome_escola' => 'COMPLEXO ESCOLAR CASTELO REIS',
            'endereco' => 'Luanda, Angola',
            'telefone' => '+244 999 999 999',
            'email' => 'info@casteloreis.ao'
        ];
    }
    
    // ============================================
    // 3. IDs DOS EMOLUMENTOS DE TRANSPORTE
    // ============================================
    $emolumento_transporte_ids = [19, 56, 66, 102, 104, 114];
    
    // ============================================
    // 4. CONSULTA
    // ============================================
    $where_conditions = [];
    $params = [];
    
    if (!empty($classe)) {
        $where_conditions[] = "a.Classe = :classe";
        $params[':classe'] = $classe;
    }
    
    if (!empty($turma)) {
        $where_conditions[] = "a.TURMA = :turma";
        $params[':turma'] = $turma;
    }
    
    if (!empty($periodo)) {
        $where_conditions[] = "LOWER(a.Periodo) = LOWER(:periodo)";
        $params[':periodo'] = $periodo;
    }
    
    if (!empty($mes_referencia) && $mes_referencia != 'todos') {
        $where_conditions[] = "p.mes_referencia = :mes_referencia";
        $params[':mes_referencia'] = $mes_referencia;
    }
    
    if (!empty($status_pagamento)) {
        $where_conditions[] = "p.status = :status_pagamento";
        $params[':status_pagamento'] = $status_pagamento;
    }
    
    if (!empty($forma_pagamento)) {
        $where_conditions[] = "p.forma_pagamento = :forma_pagamento";
        $params[':forma_pagamento'] = $forma_pagamento;
    }
    
    if (!empty($data_inicio)) {
        $where_conditions[] = "p.data_pagamento >= :data_inicio";
        $params[':data_inicio'] = $data_inicio;
    }
    if (!empty($data_fim)) {
        $where_conditions[] = "p.data_pagamento <= :data_fim";
        $params[':data_fim'] = $data_fim;
    }
    
    if (!empty($busca_nome)) {
        $where_conditions[] = "a.nome LIKE :busca_nome";
        $params[':busca_nome'] = '%' . $busca_nome . '%';
    }
    
    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT DISTINCT
            a.id AS aluno_id,
            a.nome,
            a.Sexo,
            a.dia,
            a.mes,
            a.Ano,
            a.Classe,
            a.TURMA AS turma,
            a.SALA AS sala,
            a.Periodo,
            a.Situacao_Cadastro,
            YEAR(CURRENT_DATE) - a.Ano - (DATE_FORMAT(CURRENT_DATE, '%m%d') < CONCAT(a.mes, LPAD(a.dia, 2, '0'))) AS idade_atual
        FROM alunos a
        INNER JOIN pagamentos p ON a.id = p.aluno_id
        WHERE p.emolumento_id IN (" . implode(',', $emolumento_transporte_ids) . ")
        AND (a.Situacao_Cadastro = 'Matrícula' OR a.Situacao_Cadastro = 'Confirmação')
        $where_sql
        ORDER BY a.nome
    ";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $alunos_lista = $stmt->fetchAll();
    $total_alunos = count($alunos_lista);
    
} catch (Exception $e) {
    error_log("Erro ao buscar alunos: " . $e->getMessage());
    $alunos_lista = [];
    $total_alunos = 0;
}

// ============================================
// 5. GERAR EXCEL (CSV)
// ============================================

// Nome do arquivo
$nome_arquivo = 'lista_transporte_' . date('Y-m-d') . '.csv';

// Headers para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Criar o arquivo CSV
$output = fopen('php://output', 'w');

// UTF-8 BOM para compatibilidade com Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho do CSV
$cabecalho = [
    'Nº',
    'Nome do Aluno',
    'Sexo',
    'Data Nascimento',
    'Idade',
    'Classe',
    'Turma',
    'Sala',
    'Período',
    'Status'
];
fputcsv($output, $cabecalho, ';');

// Dados
if ($total_alunos > 0) {
    $num = 1;
    foreach ($alunos_lista as $aluno) {
        $data_nasc = '';
        if (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0 && $aluno['dia'] != 0) {
            $data_nasc = sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']);
        } else {
            $data_nasc = '-';
        }
        $idade = $aluno['idade_atual'] ?? '-';
        
        $linha = [
            $num,
            $aluno['nome'] ?? '',
            $aluno['Sexo'] ?? 'M',
            $data_nasc,
            $idade,
            $aluno['Classe'] ?? '-',
            $aluno['turma'] ?? '-',
            $aluno['sala'] ?? '-',
            $aluno['Periodo'] ?? '-',
            $aluno['Situacao_Cadastro'] ?? '-'
        ];
        fputcsv($output, $linha, ';');
        $num++;
    }
} else {
    // Se não houver dados, adicionar uma linha de aviso
    fputcsv($output, ['Nenhum aluno encontrado com os filtros selecionados'], ';');
}

fclose($output);
exit;
?>