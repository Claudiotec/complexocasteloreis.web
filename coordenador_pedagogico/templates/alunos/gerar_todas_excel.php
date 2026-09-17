<?php
// ============================================
// modules/escola/alunos/gerar_todas_excel.php - Gerar Todas Excel (SEM PHPSPREADSHEET)
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== FUNÇÃO PARA GERAR ARQUIVO CSV =====
function gerarCSV($dados, $nome_arquivo) {
    $pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/listas_nominais_excel/';
    if (!file_exists($pasta)) {
        mkdir($pasta, 0777, true);
    }
    
    $caminho = $pasta . $nome_arquivo . '.csv';
    $file = fopen($caminho, 'w', false);
    
    // BOM para UTF-8
    fwrite($file, "\xEF\xBB\xBF");
    
    // Cabeçalho
    fputcsv($file, ['Nº', 'Nome do Aluno', 'Sexo', 'Idade', 'Data Nasc.', 'Situação'], ';');
    
    // Dados
    $num = 1;
    foreach ($dados as $aluno) {
        $sexo = $aluno['Sexo'] ?? 'M';
        $data_nasc = (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0) ? 
            sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-';
        
        fputcsv($file, [
            $num,
            $aluno['nome'],
            $sexo,
            $aluno['Idade'] ?? '-',
            $data_nasc,
            $aluno['Situacao_Cadastro'] ?? 'Matrícula'
        ], ';');
        $num++;
    }
    
    fclose($file);
    return $caminho;
}

// ===== FUNÇÃO PARA GERAR ARQUIVO HTML COM TABELA (PARA EXCEL) =====
function gerarExcelHTML($dados, $nome_arquivo, $classe, $turma, $nomeEmpresa, $ano_letivo) {
    $pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/listas_nominais_excel/';
    if (!file_exists($pasta)) {
        mkdir($pasta, 0777, true);
    }
    
    $caminho = $pasta . $nome_arquivo . '.xls';
    
    $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
                  xmlns:x="urn:schemas-microsoft-com:office:excel" 
                  xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>' . htmlspecialchars($classe . ' - ' . $turma) . '</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            /* Colunas */
            .col0 { width: 30px; }
            .col1 { width: 250px; }
            .col2 { width: 60px; }
            .col3 { width: 50px; }
            .col4 { width: 80px; }
            .col5 { width: 100px; }
            
            /* Estilos */
            .header { 
                font-size: 16pt; 
                font-weight: bold; 
                text-align: center;
                background-color: #343a40;
                color: #ffffff;
                padding: 5px;
            }
            .subheader {
                font-size: 14pt;
                font-weight: bold;
                text-align: center;
                background-color: #343a40;
                color: #ffffff;
                padding: 5px;
            }
            .info {
                font-size: 10pt;
                font-weight: bold;
                text-align: center;
                background-color: #e9ecef;
                padding: 3px;
            }
            .info-value {
                font-size: 11pt;
                font-weight: bold;
                text-align: center;
                padding: 3px;
            }
            .title {
                font-size: 12pt;
                font-weight: bold;
                text-align: center;
                padding: 5px;
            }
            .subtitle {
                font-size: 10pt;
                color: #6c757d;
                text-align: center;
                padding: 3px;
            }
            .table-header {
                font-size: 11pt;
                font-weight: bold;
                text-align: center;
                background-color: #1a2332;
                color: #ffffff;
                padding: 5px;
                border: 1px solid #1a2332;
            }
            .table-cell {
                font-size: 10pt;
                padding: 4px;
                border: 1px solid #dee2e6;
            }
            .table-cell-center {
                text-align: center;
            }
            .footer {
                font-size: 10pt;
                color: #6c757d;
                text-align: center;
                padding: 5px;
                background-color: #f8f9fa;
            }
            .badge-m {
                background-color: #dbeafe;
                color: #1e40af;
                padding: 2px 8px;
                border-radius: 10px;
                font-weight: bold;
            }
            .badge-f {
                background-color: #fce7f3;
                color: #9d174d;
                padding: 2px 8px;
                border-radius: 10px;
                font-weight: bold;
            }
            table {
                border-collapse: collapse;
                width: 100%;
            }
            .even-row {
                background-color: #f9f9f9;
            }
        </style>
    </head>
    <body>
        <table>
            <!-- Cabeçalho -->
            <tr><td colspan="6" class="header">REPÚBLICA DE ANGOLA</td></tr>
            <tr><td colspan="6" class="subheader">MINISTÉRIO DA EDUCAÇÃO</td></tr>
            <tr><td colspan="6" class="subheader">' . htmlspecialchars($nomeEmpresa) . '</td></tr>
            <tr><td colspan="6" style="height: 5px;"></td></tr>
            
            <!-- Informações -->
            <tr>
                <td class="info">Classe</td>
                <td class="info-value">' . htmlspecialchars($classe) . '</td>
                <td class="info">Turma</td>
                <td class="info-value">' . htmlspecialchars($turma) . '</td>
                <td class="info">Ano Lectivo</td>
                <td class="info-value">' . htmlspecialchars($ano_letivo) . '</td>
            </tr>
            <tr><td colspan="6" style="height: 5px;"></td></tr>
            
            <!-- Título -->
            <tr><td colspan="6" class="title">LISTA NOMINAL DOS ALUNOS</td></tr>
            <tr><td colspan="6" class="subtitle">Relatório gerado em ' . date('d/m/Y H:i') . '</td></tr>
            <tr><td colspan="6" style="height: 5px;"></td></tr>
            
            <!-- Cabeçalho da Tabela -->
            <tr>
                <td class="table-header" style="width: 5%;">Nº</td>
                <td class="table-header" style="width: 35%;">Nome do Aluno</td>
                <td class="table-header" style="width: 10%;">Sexo</td>
                <td class="table-header" style="width: 10%;">Idade</td>
                <td class="table-header" style="width: 15%;">Data Nasc.</td>
                <td class="table-header" style="width: 15%;">Situação</td>
            </tr>';
    
    // Dados
    $num = 1;
    $row_class = '';
    foreach ($dados as $aluno) {
        $sexo = $aluno['Sexo'] ?? 'M';
        $sexoBadge = $sexo == 'M' ? '<span class="badge-m">M</span>' : '<span class="badge-f">F</span>';
        $data_nasc = (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0) ? 
            sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-';
        
        $row_class = ($num % 2 == 0) ? 'even-row' : '';
        
        $html .= '
            <tr class="' . $row_class . '">
                <td class="table-cell table-cell-center">' . $num . '</td>
                <td class="table-cell">' . htmlspecialchars($aluno['nome']) . '</td>
                <td class="table-cell table-cell-center">' . $sexoBadge . '</td>
                <td class="table-cell table-cell-center">' . ($aluno['Idade'] ?? '-') . '</td>
                <td class="table-cell table-cell-center">' . $data_nasc . '</td>
                <td class="table-cell table-cell-center">' . ($aluno['Situacao_Cadastro'] ?? 'Matrícula') . '</td>
            </tr>';
        $num++;
    }
    
    // Rodapé
    $html .= '
            <tr><td colspan="6" class="footer">Total de alunos: ' . ($num - 1) . ' | ' . htmlspecialchars($nomeEmpresa) . '</td></tr>
        </table>
    </body>
    </html>';
    
    file_put_contents($caminho, $html);
    return $caminho;
}

// ===== BUSCAR ALUNOS =====
$alunos = [];
try {
    $alunos = $pdo->query("
        SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
               Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro
        FROM alunos 
        WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
        ORDER BY Classe, TURMA, nome
    ")->fetchAll();
} catch (Exception $e) {}

// ===== DADOS DA EMPRESA =====
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'SoftGest Sistemas';

// ===== CALCULAR ANO LETIVO =====
$mes_atual = date('m');
$ano_atual = date('Y');
if ($mes_atual >= 9) {
    $ano_letivo = $ano_atual . '/' . ($ano_atual + 1);
} else {
    $ano_letivo = ($ano_atual - 1) . '/' . $ano_atual;
}

// ===== AGRUPAR POR CLASSE E TURMA =====
$grupos = [];
foreach ($alunos as $aluno) {
    $classe = $aluno['Classe'] ?? 'Sem Classe';
    $turma = $aluno['TURMA'] ?? 'Sem Turma';
    $key = $classe . '|' . $turma;
    
    if (!isset($grupos[$key])) {
        $grupos[$key] = [
            'classe' => $classe,
            'turma' => $turma,
            'alunos' => []
        ];
    }
    $grupos[$key]['alunos'][] = $aluno;
}

// ===== GERAR ARQUIVOS =====
$arquivos_gerados = [];
$total_arquivos = 0;

// Criar arquivo ZIP para download
$zip = new ZipArchive();
$zip_nome = 'listas_nominais_' . date('Y-m-d_H-i-s') . '.zip';
$zip_caminho = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/' . $zip_nome;

if ($zip->open($zip_caminho, ZipArchive::CREATE) !== TRUE) {
    die('Erro ao criar arquivo ZIP');
}

foreach ($grupos as $grupo) {
    $classe = $grupo['classe'];
    $turma = $grupo['turma'];
    $alunos_lista = $grupo['alunos'];
    
    $nome_base = 'lista_nominal_' . preg_replace('/[^a-zA-Z0-9]/', '_', $classe) . '_T_' . preg_replace('/[^a-zA-Z0-9]/', '_', $turma);
    
    // Gerar Excel (HTML)
    $caminho_excel = gerarExcelHTML($alunos_lista, $nome_base, $classe, $turma, $nomeEmpresa, $ano_letivo);
    $zip->addFile($caminho_excel, basename($caminho_excel));
    
    // Gerar CSV
    $caminho_csv = gerarCSV($alunos_lista, $nome_base);
    $zip->addFile($caminho_csv, basename($caminho_csv));
    
    $arquivos_gerados[] = basename($caminho_excel);
    $total_arquivos += 2;
}

$zip->close();

// ===== DOWNLOAD =====
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_nome . '"');
header('Content-Length: ' . filesize($zip_caminho));
readfile($zip_caminho);

// Limpar arquivos temporários
foreach ($arquivos_gerados as $arquivo) {
    $pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/listas_nominais_excel/';
    if (file_exists($pasta . $arquivo)) {
        unlink($pasta . $arquivo);
    }
}
unlink($zip_caminho);

exit;
?>