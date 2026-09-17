<?php
// ============================================
// exportar_excel.php - Exportar Notas para Excel com Formatação
// ============================================

// ===== 1. CARREGAR CONFIGURAÇÃO =====
$base_path = __DIR__;
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

// ===== 2. SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== 3. VERIFICAR LOGIN =====
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// ===== 4. DADOS =====
$ano_letivo = $_GET['ano_letivo'] ?? date('Y') . '/' . (date('Y') + 1);
$classe = $_GET['classe'] ?? '';
$turma = $_GET['turma'] ?? '';
$disciplina = $_GET['disciplina'] ?? '';

if (!$classe || !$turma || !$disciplina) {
    die('Dados insuficientes para exportar');
}

// ===== 5. BUSCAR DADOS =====
if (!isset($pdo) || !$pdo) {
    $pdo = conectarBanco();
}

function isMiniPauta($classe) {
    $classeNum = intval(preg_replace('/[^0-9]/', '', $classe));
    return in_array($classeNum, [6, 9, 12]);
}

function isPreSexta($classe) {
    $classeNum = intval(preg_replace('/[^0-9]/', '', $classe));
    return $classeNum <= 6;
}

function getSituacao($mfd, $isPreSexta) {
    if ($mfd === null || $mfd <= 0) return 'SEM NOTA';
    $notaMinima = $isPreSexta ? 5 : 10;
    if ($mfd >= $notaMinima) return 'APROVADO';
    if ($mfd >= 3) return 'RECUPERAÇÃO';
    return 'REPROVADO';
}

function getCorSituacao($situacao) {
    switch ($situacao) {
        case 'APROVADO': return '#28a745';
        case 'RECUPERAÇÃO': return '#f39c12';
        case 'REPROVADO': return '#dc3545';
        default: return '#6c757d';
    }
}

$escola_nome = $_SESSION['escola_nome'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';
$usuario_nome = $_SESSION['usuario_nome'] ?? '';

try {
    // Buscar alunos
    $sql = "SELECT id, nome, Sexo as sexo, Idade as idade, TURMA as turma, Classe as classe 
            FROM alunos WHERE TURMA = ? AND status = 'ativo' ORDER BY nome ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$turma]);
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar notas
    $notas = [];
    if (!empty($alunos)) {
        $ids = array_column($alunos, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $sql_notas = "SELECT id_aluno, mac_t1, npt_t1, mt_t1, mac_t2, npt_t2, mt_t2, 
                      mac_t3, npt_t3, mt_t3, mfd, neo, en, classificacao, situacao
                      FROM notas WHERE id_aluno IN ($placeholders) AND disciplina = ? AND ano_letivo = ?";
        $params = array_merge($ids, [$disciplina, $ano_letivo]);
        $stmt_notas = $pdo->prepare($sql_notas);
        $stmt_notas->execute($params);
        $notas_list = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($notas_list as $n) {
            $notas[$n['id_aluno']] = $n;
        }
    }
    
    $isMini = isMiniPauta($classe);
    $isPre = isPreSexta($classe);
    $maxPontos = $isPre ? 10 : 20;
    
} catch (Exception $e) {
    die('Erro ao buscar dados: ' . $e->getMessage());
}

// ===== 6. GERAR HTML PARA EXCEL =====
$html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
              xmlns:x="urn:schemas-microsoft-com:office:excel" 
              xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Pauta de Notas</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        body { font-family: "Times New Roman", Times, serif; padding: 20px; }
        .print-header { text-align: center; margin-bottom: 25px; font-family: "Times New Roman", Times, serif; }
        .print-header h2 { font-size: 16px; margin: 3px 0; font-weight: bold; }
        .print-header h3 { font-size: 14px; margin: 3px 0; font-weight: normal; }
        .print-header .escola-nome { font-size: 18px; font-weight: bold; text-transform: uppercase; margin: 10px 0; text-decoration: underline; }
        .print-header .titulo-pauta { font-size: 20px; font-weight: bold; margin: 15px 0 10px 0; }
        .info-linha { display: flex; justify-content: space-between; margin: 10px 0; font-size: 12px; flex-wrap: wrap; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 0; }
        .info-linha span { padding: 0 8px; }
        .info-linha strong { font-weight: bold; }
        .print-table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 15px 0; font-family: "Times New Roman", Times, serif; }
        .print-table th { background-color: #2c3e50; color: #ffffff; padding: 6px 4px; text-align: center; border: 1px solid #000000; font-weight: bold; font-size: 10px; }
        .print-table td { padding: 4px 4px; border: 1px solid #000000; text-align: center; font-size: 10px; }
        .print-table .aluno-nome { text-align: left; padding-left: 6px; font-weight: normal; }
        .print-table .num-col { text-align: center; font-weight: bold; }
        .situacao-aprovado { background-color: #d4edda; color: #155724; font-weight: bold; padding: 2px 8px; border-radius: 4px; }
        .situacao-recuperacao { background-color: #fff3cd; color: #856404; font-weight: bold; padding: 2px 8px; border-radius: 4px; }
        .situacao-reprovado { background-color: #f8d7da; color: #721c24; font-weight: bold; padding: 2px 8px; border-radius: 4px; }
        .situacao-sem-nota { background-color: #e2e3e5; color: #383d41; padding: 2px 8px; border-radius: 4px; }
        .class-muito-bom { background-color: #d4edda; color: #155724; font-weight: bold; }
        .class-bom { background-color: #d1ecf1; color: #0c5460; font-weight: bold; }
        .class-suficiente { background-color: #fff3cd; color: #856404; font-weight: bold; }
        .class-mediocre { background-color: #f8d7da; color: #721c24; font-weight: bold; }
        .class-mau { background-color: #f5c6cb; color: #721c24; font-weight: bold; }
        .mfd-cell { font-weight: bold; color: #155724; background-color: #d4edda; padding: 2px 6px; border-radius: 4px; border: 2px solid #28a745; }
        .mt-cell { font-weight: bold; color: #2c3e50; background-color: #e3f2fd; padding: 2px 6px; border-radius: 4px; }
        .print-footer { margin-top: 40px; display: flex; justify-content: space-between; font-family: "Times New Roman", Times, serif; }
        .assinatura { text-align: center; min-width: 200px; }
        .linha-assinatura { border-top: 1px solid #000; margin-top: 35px; padding-top: 4px; width: 200px; margin-left: auto; margin-right: auto; }
        .print-footer .professor { margin-top: 5px; font-weight: normal; }
        @page { size: landscape; margin: 1cm; }
    </style>
</head>
<body>';

// ===== CABEÇALHO =====
$html .= '
<div class="print-header">
    <h2>REPÚBLICA DE ANGOLA</h2>
    <h3>GOVERNO DA PROVÍNCIA DO ICOLO E BENGO</h3>
    <h3>DIRECÇÃO MUNICIPAL DE EDUCAÇÃO DO CALUMBO</h3>
    <div class="escola-nome">' . htmlspecialchars($escola_nome) . '</div>
    <div class="titulo-pauta">' . ($isMini ? 'MINI PAUTA' : 'PAUTA DE NOTAS') . '</div>
    <div class="info-linha">
        <span><strong>Ano Letivo:</strong> ' . htmlspecialchars($ano_letivo) . '</span>
        <span><strong>Classe:</strong> ' . htmlspecialchars($classe) . 'ª</span>
        <span><strong>Turma:</strong> ' . htmlspecialchars($turma) . '</span>
        <span><strong>Disciplina:</strong> ' . htmlspecialchars($disciplina) . '</span>
        <span><strong>Trimestre:</strong> FINAL</span>
    </div>
</div>';

// ===== TABELA =====
if ($isMini) {
    $html .= '
    <table class="print-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:35px;">Nº</th>
                <th rowspan="2" style="text-align:left;min-width:180px;">NOME DO ALUNO</th>
                <th colspan="3" style="background-color:#34495e;">I TRIMESTRE</th>
                <th colspan="3" style="background-color:#34495e;">II TRIMESTRE</th>
                <th colspan="5" style="background-color:#34495e;">III TRIMESTRE</th>
                <th rowspan="2" style="min-width:45px;">MFED</th>
                <th rowspan="2" style="min-width:80px;">SITUAÇÃO</th>
            </tr>
            <tr>
                <th>MAC</th><th>NPT</th><th>MT</th>
                <th>MAC</th><th>NPT</th><th>MT</th>
                <th>MAC</th><th>MFD</th><th>NEO</th><th>EN</th><th>MEC</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($alunos as $idx => $aluno) {
        $n = $notas[$aluno['id']] ?? [];
        $mfd = $n['mfd'] ?? 0;
        $situacao = $n['situacao'] ?? getSituacao($mfd, $isPre);
        $corSituacao = getCorSituacao($situacao);
        
        $mac1 = $n['mac_t1'] ?? '-';
        $npt1 = $n['npt_t1'] ?? '-';
        $mac2 = $n['mac_t2'] ?? '-';
        $npt2 = $n['npt_t2'] ?? '-';
        $mac3 = $n['mac_t3'] ?? '-';
        $mfdVal = number_format($n['mfd'] ?? 0, 1);
        $neo = $n['neo'] ?? '-';
        $en = $n['en'] ?? '-';
        $mec = number_format((floatval($n['neo'] ?? 0) + floatval($n['en'] ?? 0)) / 2, 1);
        
        $mt1Calc = floatval($n['mac_t1'] ?? 0) + floatval($n['npt_t1'] ?? 0) > 0 ? 
            number_format((floatval($n['mac_t1'] ?? 0) + floatval($n['npt_t1'] ?? 0)) / 2, 1) : '0.0';
        $mt2Calc = floatval($n['mac_t2'] ?? 0) + floatval($n['npt_t2'] ?? 0) > 0 ? 
            number_format((floatval($n['mac_t2'] ?? 0) + floatval($n['npt_t2'] ?? 0)) / 2, 1) : '0.0';
        
        $mt3 = (floatval($mt1Calc) + floatval($mt2Calc) + floatval($mfdVal)) / 3;
        $mfed = (0.6 * $mt3) + (floatval($n['en'] ?? 0) * 0.4);
        
        $html .= '
        <tr>
            <td class="num-col">' . ($idx + 1) . '</td>
            <td class="aluno-nome">' . htmlspecialchars($aluno['nome']) . '</td>
            <td>' . htmlspecialchars($mac1) . '</td>
            <td>' . htmlspecialchars($npt1) . '</td>
            <td class="mt-cell">' . $mt1Calc . '</td>
            <td>' . htmlspecialchars($mac2) . '</td>
            <td>' . htmlspecialchars($npt2) . '</td>
            <td class="mt-cell">' . $mt2Calc . '</td>
            <td>' . htmlspecialchars($mac3) . '</td>
            <td class="mt-cell">' . $mfdVal . '</td>
            <td>' . htmlspecialchars($neo) . '</td>
            <td>' . htmlspecialchars($en) . '</td>
            <td class="mt-cell">' . $mec . '</td>
            <td class="mfd-cell">' . number_format($mfed, 1) . '</td>
            <td><span class="situacao-' . strtolower($situacao) . '" style="color:' . $corSituacao . ';font-weight:bold;">' . $situacao . '</span></td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    
} else {
    $html .= '
    <table class="print-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:35px;">Nº</th>
                <th rowspan="2" style="text-align:left;min-width:180px;">NOME DO ALUNO</th>
                <th colspan="3" style="background-color:#34495e;">1º TRIMESTRE</th>
                <th colspan="3" style="background-color:#34495e;">2º TRIMESTRE</th>
                <th colspan="3" style="background-color:#34495e;">3º TRIMESTRE</th>
                <th rowspan="2" style="min-width:45px;">MFD/' . $maxPontos . '</th>
                <th rowspan="2" style="min-width:80px;">CLASSIF.</th>
            </tr>
            <tr>
                <th>MAC</th><th>NPT</th><th>MT</th>
                <th>MAC</th><th>NPT</th><th>MT</th>
                <th>MAC</th><th>NPT</th><th>MT</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($alunos as $idx => $aluno) {
        $n = $notas[$aluno['id']] ?? [];
        $mfd = $n['mfd'] ?? 0;
        
        $mac1 = $n['mac_t1'] ?? '-';
        $npt1 = $n['npt_t1'] ?? '-';
        $mt1 = number_format($n['mt_t1'] ?? 0, 1);
        $mac2 = $n['mac_t2'] ?? '-';
        $npt2 = $n['npt_t2'] ?? '-';
        $mt2 = number_format($n['mt_t2'] ?? 0, 1);
        $mac3 = $n['mac_t3'] ?? '-';
        $npt3 = $n['npt_t3'] ?? '-';
        $mt3 = number_format($n['mt_t3'] ?? 0, 1);
        $mfdVal = number_format($mfd, 1);
        
        $classif = $n['classificacao'] ?? '';
        $classClasse = '';
        if ($classif == 'MUITO BOM') $classClasse = 'class-muito-bom';
        else if ($classif == 'BOM') $classClasse = 'class-bom';
        else if ($classif == 'SUFICIENTE') $classClasse = 'class-suficiente';
        else if ($classif == 'MEDÍOCRE') $classClasse = 'class-mediocre';
        else if ($classif == 'MAU') $classClasse = 'class-mau';
        
        $html .= '
        <tr>
            <td class="num-col">' . ($idx + 1) . '</td>
            <td class="aluno-nome">' . htmlspecialchars($aluno['nome']) . '</td>
            <td>' . htmlspecialchars($mac1) . '</td>
            <td>' . htmlspecialchars($npt1) . '</td>
            <td class="mt-cell">' . $mt1 . '</td>
            <td>' . htmlspecialchars($mac2) . '</td>
            <td>' . htmlspecialchars($npt2) . '</td>
            <td class="mt-cell">' . $mt2 . '</td>
            <td>' . htmlspecialchars($mac3) . '</td>
            <td>' . htmlspecialchars($npt3) . '</td>
            <td class="mt-cell">' . $mt3 . '</td>
            <td class="mfd-cell">' . $mfdVal . '</td>
            <td class="' . $classClasse . '">' . $classif . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
}

// ===== RODAPÉ =====
$html .= '
<div class="print-footer">
    <div class="assinatura">
        <div>O Professor</div>
        <div class="linha-assinatura"></div>
        <div class="professor">' . htmlspecialchars($usuario_nome) . '</div>
    </div>
    <div class="assinatura">
        <div>O Director Pedagógico</div>
        <div class="linha-assinatura"></div>
        <div class="professor"></div>
    </div>
</div>
</body>
</html>';

// ===== 7. ENVIAR PARA DOWNLOAD =====
$nome_arquivo = 'Pauta_Notas_' . $classe . '_' . $turma . '_' . $disciplina . '.xls';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
header('Cache-Control: max-age=0');

echo $html;
exit;