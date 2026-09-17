<?php
// ============================================
// modules/escola/notas/imprimir_relatorio.php
// Visualizador de Impressão - Relatório de Notas (A4)
// ============================================

$root_path = dirname(__DIR__, 3);

require_once $root_path . '/config/database.php';
require_once $root_path . '/config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!isset($pdo) || !$pdo) {
    $pdo = conectarBanco();
}

// ===== DADOS DO USUÁRIO E ESCOLA =====
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$escola_nome  = $_SESSION['escola_nome']  ?? 'COMPLEXO ESCOLAR CASTELO REIS';

// Dados da empresa
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}

$nomeEmpresa     = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? $escola_nome;
$telefoneEmpresa = $empresa['telefone'] ?? '';
$emailEmpresa    = $empresa['email'] ?? '';
$cnpjEmpresa     = $empresa['cnpj'] ?? '';
$enderecoEmpresa = $empresa['endereco'] ?? '';
$numeroEmpresa   = $empresa['numero'] ?? '';
$bairroEmpresa   = $empresa['bairro'] ?? '';
$cidadeEmpresa   = $empresa['cidade'] ?? '';
$estadoEmpresa   = $empresa['estado'] ?? '';

$enderecoCompleto = $enderecoEmpresa;
if ($numeroEmpresa) $enderecoCompleto .= ', ' . $numeroEmpresa;
if ($bairroEmpresa) $enderecoCompleto .= ', ' . $bairroEmpresa;
if ($cidadeEmpresa) $enderecoCompleto .= ', ' . $cidadeEmpresa;
if ($estadoEmpresa) $enderecoCompleto .= ' - ' . $estadoEmpresa;

// ===== FUNÇÕES =====
function isEscala10($classe) {
    if (!$classe) return false;
    $c = strtoupper(trim($classe));
    if (strpos($c, 'PRE') !== false || strpos($c, 'PRÉ') !== false) return true;
    $num = intval(preg_replace('/[^0-9]/', '', $c));
    return $num >= 1 && $num <= 6;
}

function getLimitesPorClasse($classe) {
    if (isEscala10($classe)) {
        return ['aprovado' => 5.0, 'recuperacao' => 3.0, 'max' => 10];
    }
    return ['aprovado' => 10.0, 'recuperacao' => 5.0, 'max' => 20];
}

function getSituacaoPorClasse($mfd, $classe) {
    if ($mfd === null || $mfd === '' || !is_numeric($mfd)) {
        return ['label' => 'Sem nota', 'class' => 'status-dispensado'];
    }
    $mfd = (float)$mfd;
    $lim = getLimitesPorClasse($classe);

    if ($mfd >= $lim['aprovado'])      return ['label' => 'Aprovado',    'class' => 'status-aprovado'];
    if ($mfd >= $lim['recuperacao'])   return ['label' => 'Recuperação', 'class' => 'status-recuperacao'];
    if ($mfd > 0)                      return ['label' => 'Reprovado',   'class' => 'status-reprovado'];
    return ['label' => 'Sem nota', 'class' => 'status-dispensado'];
}

function calcularMFD($mt1, $mt2, $mt3, $ignorarNulos = true) {
    $valores = [];
    foreach ([$mt1, $mt2, $mt3] as $v) {
        if ($v === null || $v === '' || !is_numeric($v)) {
            if (!$ignorarNulos) $valores[] = 0;
            continue;
        }
        $valores[] = (float)$v;
    }
    if (empty($valores)) return null;
    return round(array_sum($valores) / count($valores), 1);
}

// ===== FILTROS =====
$ano_letivo    = $_GET['ano_letivo']    ?? (date('Y') . '/' . (date('Y') + 1));
$classe_filtro = $_GET['classe']        ?? '';
$turma_filtro  = $_GET['turma']         ?? '';
$disciplina    = $_GET['disciplina']    ?? '';
$busca_aluno   = trim($_GET['busca_aluno'] ?? '');
$situacao_filt = $_GET['situacao']      ?? '';
$ordenacao     = $_GET['ordenacao']     ?? 'nome';

// ===== BUSCAR NOTAS =====
$notas = [];
try {
    $sql = "SELECT 
                n.id, n.id_aluno, n.nome_aluno, n.disciplina, n.turma, n.classe, n.ano_letivo,
                n.mt1, n.mt2, n.mt3, n.mfd,
                a.Sexo AS sexo, a.Idade AS idade
            FROM notas_alunos n
            LEFT JOIN alunos a ON n.id_aluno = a.id
            WHERE n.ano_letivo = ?";
    $params = [$ano_letivo];

    if ($classe_filtro) { $sql .= " AND n.classe = ?"; $params[] = $classe_filtro; }
    if ($turma_filtro)  { $sql .= " AND n.turma = ?";  $params[] = $turma_filtro; }
    if ($disciplina)    { $sql .= " AND n.disciplina = ?"; $params[] = $disciplina; }
    if ($busca_aluno !== '') {
        $sql .= " AND n.nome_aluno LIKE ?";
        $params[] = '%' . $busca_aluno . '%';
    }

    switch ($ordenacao) {
        case 'media_asc':  $sql .= " ORDER BY n.mfd ASC, n.nome_aluno ASC"; break;
        case 'media_desc': $sql .= " ORDER BY n.mfd DESC, n.nome_aluno ASC"; break;
        case 'classe':     $sql .= " ORDER BY n.classe ASC, n.turma ASC, n.nome_aluno ASC"; break;
        case 'turma':      $sql .= " ORDER BY n.turma ASC, n.nome_aluno ASC"; break;
        case 'disciplina': $sql .= " ORDER BY n.disciplina ASC, n.nome_aluno ASC"; break;
        default:           $sql .= " ORDER BY n.nome_aluno ASC, n.disciplina ASC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
}

// ===== PROCESSAR =====
$notasProcessadas = [];
$totalAprovados = 0; $totalReprovados = 0; $totalRecuperacao = 0; $totalSemNota = 0;
$somaMedias = 0; $countMedias = 0;
$statsPorTurma = []; $statsPorClasse = []; $alunosPorTurma = [];

foreach ($notas as $n) {
    $classeN = $n['classe'] ?? '';
    $turmaN  = $n['turma']  ?? '';
    $lim = getLimitesPorClasse($classeN);
    $escala10 = isEscala10($classeN);

    $mfdCalc = calcularMFD($n['mt1'], $n['mt2'], $n['mt3'], true);
    if ($mfdCalc === null) {
        $mfdCalc = ($n['mfd'] !== null && $n['mfd'] !== '' && is_numeric($n['mfd'])) ? (float)$n['mfd'] : null;
    }

    $situacao = getSituacaoPorClasse($mfdCalc, $classeN);

    if ($situacao_filt !== '') {
        if ($situacao_filt === 'aprovado'    && $situacao['label'] !== 'Aprovado')    continue;
        if ($situacao_filt === 'reprovado'   && $situacao['label'] !== 'Reprovado')   continue;
        if ($situacao_filt === 'recuperacao' && $situacao['label'] !== 'Recuperação') continue;
        if ($situacao_filt === 'sem_nota'    && $situacao['label'] !== 'Sem nota')    continue;
    }

    if ($situacao['label'] === 'Aprovado')      $totalAprovados++;
    elseif ($situacao['label'] === 'Reprovado') $totalReprovados++;
    elseif ($situacao['label'] === 'Recuperação') $totalRecuperacao++;
    else $totalSemNota++;

    if ($mfdCalc !== null) { $somaMedias += $mfdCalc; $countMedias++; }

    // Turma
    if (!isset($statsPorTurma[$turmaN])) {
        $statsPorTurma[$turmaN] = ['total'=>0,'aprovados'=>0,'reprovados'=>0,'recuperacao'=>0,'soma'=>0,'count'=>0,'classe'=>$classeN];
    }
    $statsPorTurma[$turmaN]['total']++;
    if ($situacao['label'] === 'Aprovado')      $statsPorTurma[$turmaN]['aprovados']++;
    elseif ($situacao['label'] === 'Reprovado') $statsPorTurma[$turmaN]['reprovados']++;
    elseif ($situacao['label'] === 'Recuperação') $statsPorTurma[$turmaN]['recuperacao']++;
    if ($mfdCalc !== null) {
        $statsPorTurma[$turmaN]['soma'] += $mfdCalc;
        $statsPorTurma[$turmaN]['count']++;
    }

    // Classe
    if (!isset($statsPorClasse[$classeN])) {
        $statsPorClasse[$classeN] = ['total'=>0,'aprovados'=>0,'reprovados'=>0,'recuperacao'=>0,'soma'=>0,'count'=>0];
    }
    $statsPorClasse[$classeN]['total']++;
    if ($situacao['label'] === 'Aprovado')      $statsPorClasse[$classeN]['aprovados']++;
    elseif ($situacao['label'] === 'Reprovado') $statsPorClasse[$classeN]['reprovados']++;
    elseif ($situacao['label'] === 'Recuperação') $statsPorClasse[$classeN]['recuperacao']++;
    if ($mfdCalc !== null) {
        $statsPorClasse[$classeN]['soma'] += $mfdCalc;
        $statsPorClasse[$classeN]['count']++;
    }

    // Top alunos por turma
    if (!isset($alunosPorTurma[$turmaN][$n['id_aluno']])) {
        $alunosPorTurma[$turmaN][$n['id_aluno']] = ['nome' => $n['nome_aluno'], 'soma' => 0, 'count' => 0];
    }
    if ($mfdCalc !== null) {
        $alunosPorTurma[$turmaN][$n['id_aluno']]['soma'] += $mfdCalc;
        $alunosPorTurma[$turmaN][$n['id_aluno']]['count']++;
    }

    $notasProcessadas[] = array_merge($n, [
        'mfd_calc' => $mfdCalc, 'situacao' => $situacao, 'limites' => $lim, 'escala10' => $escala10
    ]);
}

$totalRegistros = count($notasProcessadas);
$mediaGeral = $countMedias > 0 ? round($somaMedias / $countMedias, 1) : 0;
$percAprov  = $totalRegistros > 0 ? round(($totalAprovados / $totalRegistros) * 100, 1) : 0;
$percReprov = $totalRegistros > 0 ? round(($totalReprovados / $totalRegistros) * 100, 1) : 0;
$percRecup  = $totalRegistros > 0 ? round(($totalRecuperacao / $totalRegistros) * 100, 1) : 0;

$mediasTurmas = [];
foreach ($statsPorTurma as $t => $s) { $mediasTurmas[$t] = $s['count'] > 0 ? round($s['soma'] / $s['count'], 1) : 0; }
$mediasClasses = [];
foreach ($statsPorClasse as $c => $s) { $mediasClasses[$c] = $s['count'] > 0 ? round($s['soma'] / $s['count'], 1) : 0; }

$melhorTurma = null; $piorTurma = null;
if (!empty($mediasTurmas)) {
    arsort($mediasTurmas);
    $melhorTurmaNome = key($mediasTurmas);
    $melhorTurma = ['turma' => $melhorTurmaNome, 'media' => $mediasTurmas[$melhorTurmaNome]];
    asort($mediasTurmas);
    $piorTurmaNome = key($mediasTurmas);
    $piorTurma = ['turma' => $piorTurmaNome, 'media' => $mediasTurmas[$piorTurmaNome]];
}

// Top 3 alunos por turma
$topAlunosPorTurma = [];
foreach ($alunosPorTurma as $turmaN => $alunos) {
    $lista = [];
    foreach ($alunos as $alunoId => $dados) {
        $media = $dados['count'] > 0 ? round($dados['soma'] / $dados['count'], 1) : 0;
        $lista[] = ['nome' => $dados['nome'], 'media' => $media];
    }
    usort($lista, function($a, $b) { return $b['media'] <=> $a['media']; });
    $topAlunosPorTurma[$turmaN] = array_slice($lista, 0, 3);
}
ksort($topAlunosPorTurma);

// Top 5 alunos globais
$todosAlunos = [];
foreach ($alunosPorTurma as $turmaN => $alunos) {
    foreach ($alunos as $alunoId => $dados) {
        $media = $dados['count'] > 0 ? round($dados['soma'] / $dados['count'], 1) : 0;
        if (!isset($todosAlunos[$alunoId]) || $todosAlunos[$alunoId]['media'] < $media) {
            $todosAlunos[$alunoId] = ['nome' => $dados['nome'], 'media' => $media, 'turma' => $turmaN];
        }
    }
}
usort($todosAlunos, function($a, $b) { return $b['media'] <=> $a['media']; });
$topGlobal = array_slice($todosAlunos, 0, 5);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Imprimir - Relatório de Notas</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #e2e8f0; color: #1a2332; padding: 20px; }

    /* ============ BARRA DE CONTROLE (não imprime) ============ */
    .toolbar {
        position: fixed; top: 0; left: 0; right: 0;
        background: #1a2332; color: #fff;
        padding: 12px 20px;
        display: flex; justify-content: space-between; align-items: center;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
    .toolbar .title { font-size: 14px; font-weight: 600; }
    .toolbar .actions { display: flex; gap: 10px; }
    .toolbar button {
        padding: 8px 18px; border-radius: 6px; border: none;
        font-size: 13px; font-weight: 600; cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px;
        transition: all 0.2s;
    }
    .btn-print-now { background: #2ecc71; color: #fff; }
    .btn-print-now:hover { background: #27ae60; }
    .btn-close { background: #e74c3c; color: #fff; }
    .btn-close:hover { background: #c0392b; }

    /* ============ FOLHA A4 ============ */
    .folha-a4 {
        background: #fff;
        width: 297mm;
        min-height: 210mm;
        margin: 70px auto 20px;
        padding: 12mm;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        border-radius: 4px;
        page-break-after: always;
    }

    /* ============ CABEÇALHO INSTITUCIONAL ============ */
    .header-inst {
        text-align: center;
        border-bottom: 3px double #1a2332;
        padding-bottom: 8px;
        margin-bottom: 12px;
    }
    .header-inst .linha1 { font-size: 12px; font-weight: 700; letter-spacing: 2px; }
    .header-inst .linha2 { font-size: 11px; font-weight: 600; letter-spacing: 1px; }
    .header-inst .linha3 { font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
    .header-inst .escola-nome {
        font-size: 15px; font-weight: 900;
        text-transform: uppercase; letter-spacing: 1px;
        margin: 5px 0 3px;
    }
    .header-inst .dados-empresa { font-size: 9px; color: #4a5568; line-height: 1.4; }
    .header-inst .titulo-rel {
        font-size: 13px; font-weight: 900;
        margin-top: 8px; text-transform: uppercase;
        letter-spacing: 2px;
        background: #1a2332; color: #fff;
        padding: 4px 20px; display: inline-block;
        border-radius: 3px;
    }

    .info-geral {
        display: flex; justify-content: space-between;
        font-size: 10px; color: #4a5568;
        margin-bottom: 10px; padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    .info-geral strong { color: #1a2332; }

    /* ============ ESTATÍSTICAS ============ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 8px;
        margin-bottom: 12px;
    }
    .stat-card {
        background: #f8fafc;
        padding: 8px 10px;
        border-radius: 6px;
        border-left: 3px solid #c9a84c;
    }
    .stat-card.aprovados { border-left-color: #2ecc71; }
    .stat-card.reprovados { border-left-color: #e74c3c; }
    .stat-card.recuperacao { border-left-color: #f39c12; }
    .stat-card.total { border-left-color: #3498db; }
    .stat-card.media { border-left-color: #9b59b6; }
    .stat-card .label { font-size: 8px; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    .stat-card .number { font-size: 16px; font-weight: 900; margin: 2px 0; }
    .stat-card .extra { font-size: 8px; color: #94a3b8; }

    /* ============ SEÇÕES ============ */
    .secao { margin-bottom: 12px; }
    .secao-titulo {
        font-size: 11px; font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 2px solid #c9a84c;
        padding-bottom: 4px; margin-bottom: 8px;
    }

    /* ============ GRÁFICOS ============ */
    .graficos-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
    }
    .chart-box {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 8px;
        height: 180px;
    }
    .chart-box canvas { max-height: 160px; }

    /* ============ DESTAQUES ============ */
    .destaques-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1.2fr;
        gap: 10px;
        margin-bottom: 12px;
    }
    .destaque-box {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px;
        border-top: 3px solid #c9a84c;
    }
    .destaque-box.melhor { border-top-color: #2ecc71; }
    .destaque-box.pior { border-top-color: #e74c3c; }
    .destaque-box h4 {
        font-size: 10px; font-weight: 800;
        text-transform: uppercase;
        margin-bottom: 8px; letter-spacing: 0.5px;
    }
    .destaque-turma { text-align: center; }
    .destaque-turma .turma-nome { font-size: 22px; font-weight: 900; }
    .destaque-turma .turma-media { font-size: 14px; font-weight: 700; margin-top: 2px; }
    .destaque-turma .turma-info { font-size: 9px; color: #64748b; margin-top: 3px; }

    .top-lista { display: flex; flex-direction: column; gap: 4px; }
    .top-item {
        display: flex; align-items: center; gap: 6px;
        padding: 4px 6px; background: #f8fafc; border-radius: 4px;
        font-size: 10px;
    }
    .top-item .pos {
        width: 18px; height: 18px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%; font-weight: 800; font-size: 9px;
        flex-shrink: 0;
    }
    .top-item .pos-1 { background: #fef3c7; color: #92400e; }
    .top-item .pos-2 { background: #e5e7eb; color: #4b5563; }
    .top-item .pos-3 { background: #fed7aa; color: #9a3412; }
    .top-item .nome { flex: 1; font-weight: 600; }
    .top-item .media { font-weight: 800; color: #1e40af; padding: 1px 6px; background: #dbeafe; border-radius: 3px; }

    /* ============ TABELAS ============ */
    .tabela { width: 100%; border-collapse: collapse; font-size: 9px; }
    .tabela thead th {
        background: #1a2332; color: #fff;
        padding: 6px 4px; text-align: left;
        font-weight: 700; font-size: 8px;
        text-transform: uppercase; letter-spacing: 0.5px;
        border: 1px solid #1a2332;
    }
    .tabela thead th.center { text-align: center; }
    .tabela tbody td { padding: 5px 4px; border: 1px solid #e2e8f0; vertical-align: middle; }
    .tabela tbody tr:nth-child(even) { background: #f8fafc; }
    .tabela .num { text-align: center; font-weight: 700; color: #c9a84c; width: 25px; }
    .tabela .nome { font-weight: 600; }
    .tabela .center { text-align: center; }
    .tabela .nota-cell { text-align: center; font-weight: 600; }
    .tabela .mfd-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-weight: 700; font-size: 9px; }
    .tabela .status-badge { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 8px; font-weight: 700; text-transform: uppercase; }

    .mfd-verde { background: #d4edda; color: #155724; }
    .mfd-amarelo { background: #fff3cd; color: #856404; }
    .mfd-vermelho { background: #f8d7da; color: #721c24; }

    .status-aprovado { background: #d1fae5; color: #065f46; }
    .status-reprovado { background: #fee2e2; color: #991b1b; }
    .status-recuperacao { background: #fef3c7; color: #92400e; }
    .status-dispensado { background: #f1f5f9; color: #4a5568; }

    /* ============ RESUMO FINAL ============ */
    .resumo-final {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 6px;
        margin-top: 10px;
    }
    .resumo-item {
        text-align: center; padding: 6px;
        border-radius: 4px; background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .resumo-item .titulo { font-size: 8px; color: #64748b; text-transform: uppercase; font-weight: 700; }
    .resumo-item .valor { font-size: 14px; font-weight: 900; margin: 2px 0; }
    .resumo-item .detalhe { font-size: 8px; color: #94a3b8; }

    /* ============ ASSINATURAS ============ */
    .assinaturas {
        margin-top: 25px; padding-top: 10px;
        display: flex; justify-content: space-around; gap: 20px;
    }
    .assinatura { text-align: center; flex: 1; }
    .assinatura .linha-ass {
        border-top: 1px solid #333;
        margin: 30px auto 4px; width: 80%;
    }
    .assinatura .nome { font-size: 10px; font-weight: 700; }
    .assinatura .cargo { font-size: 9px; color: #555; }

    /* ============ RODAPÉ ============ */
    .rodape-doc {
        text-align: center;
        font-size: 8px; color: #94a3b8;
        margin-top: 15px; padding-top: 6px;
        border-top: 1px solid #e2e8f0;
    }

    /* ============ IMPRESSÃO ============ */
    @media print {
        body { background: #fff; padding: 0; margin: 0; }
        .toolbar { display: none !important; }
        .folha-a4 {
            width: 100%; margin: 0;
            padding: 8mm;
            box-shadow: none; border-radius: 0;
            page-break-after: always;
        }
        .folha-a4:last-child { page-break-after: auto; }
        .tabela thead th { background: #1a2332 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .mfd-badge, .status-badge, .stat-card, .top-item .pos, .top-item .media, .resumo-item, .destaque-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page { size: A4 landscape; margin: 8mm; }
    }

    @media (max-width: 768px) {
        .folha-a4 { width: 100%; padding: 10px; }
        .stats-grid, .resumo-final { grid-template-columns: 1fr 1fr; }
        .graficos-grid, .destaques-grid { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<!-- ============ BARRA DE CONTROLE ============ -->
<div class="toolbar">
    <div class="title">📄 Pré-visualização • <?= htmlspecialchars($nomeEmpresa) ?> • <?= htmlspecialchars($ano_letivo) ?></div>
    <div class="actions">
        <button class="btn-print-now" onclick="window.print()">🖨️ Imprimir Agora</button>
        <button class="btn-close" onclick="window.close()">✕ Fechar</button>
    </div>
</div>

<!-- ============ PÁGINA 1 ============ -->
<div class="folha-a4">

    <div class="header-inst">
        <div class="linha1">REPÚBLICA DE ANGOLA</div>
        <div class="linha2">GOVERNO PROVINCIAL DO ÍCOLO E BENGO</div>
        <div class="linha3">DIRECÇÃO MUNICIPAL DA EDUCAÇÃO DE BOM JESUS</div>
        <div class="escola-nome">🏛️ <?= htmlspecialchars($nomeEmpresa) ?></div>
        <?php if ($enderecoCompleto || $telefoneEmpresa || $emailEmpresa || $cnpjEmpresa): ?>
        <div class="dados-empresa">
            <?php if ($enderecoCompleto): ?>📍 <?= htmlspecialchars($enderecoCompleto) ?><?php endif; ?>
            <?php if ($telefoneEmpresa): ?> • 📞 <?= htmlspecialchars($telefoneEmpresa) ?><?php endif; ?>
            <?php if ($emailEmpresa): ?> • ✉️ <?= htmlspecialchars($emailEmpresa) ?><?php endif; ?>
            <?php if ($cnpjEmpresa): ?> • 🆔 NIF: <?= htmlspecialchars($cnpjEmpresa) ?><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="titulo-rel">📊 RELATÓRIO COMPLETO DE NOTAS</div>
    </div>

    <div class="info-geral">
        <div>📅 <strong>Ano Letivo:</strong> <?= htmlspecialchars($ano_letivo) ?></div>
        <div>📊 <strong>Registros:</strong> <?= number_format($totalRegistros, 0, ',', '.') ?></div>
        <div>🎓 <strong>Média Geral:</strong> <?= number_format($mediaGeral, 1) ?></div>
        <div>🕐 <strong>Gerado em:</strong> <?= date('d/m/Y H:i') ?></div>
        <div>👤 <strong>Por:</strong> <?= htmlspecialchars($usuario_nome) ?></div>
    </div>

    <div class="stats-grid">
        <div class="stat-card total">
            <div class="label">Total</div>
            <div class="number"><?= number_format($totalRegistros, 0, ',', '.') ?></div>
            <div class="extra">registros</div>
        </div>
        <div class="stat-card aprovados">
            <div class="label">Aprovados</div>
            <div class="number" style="color:#2ecc71"><?= number_format($totalAprovados, 0, ',', '.') ?></div>
            <div class="extra"><?= $percAprov ?>%</div>
        </div>
        <div class="stat-card recuperacao">
            <div class="label">Recuperação</div>
            <div class="number" style="color:#f39c12"><?= number_format($totalRecuperacao, 0, ',', '.') ?></div>
            <div class="extra"><?= $percRecup ?>%</div>
        </div>
        <div class="stat-card reprovados">
            <div class="label">Reprovados</div>
            <div class="number" style="color:#e74c3c"><?= number_format($totalReprovados, 0, ',', '.') ?></div>
            <div class="extra"><?= $percReprov ?>%</div>
        </div>
        <div class="stat-card media">
            <div class="label">Média Geral</div>
            <div class="number"><?= number_format($mediaGeral, 1) ?></div>
            <div class="extra"><?= $countMedias ?> notas</div>
        </div>
    </div>

    <div class="secao">
        <div class="secao-titulo">📊 Análise Gráfica</div>
        <div class="graficos-grid">
            <div class="chart-box"><canvas id="ch1"></canvas></div>
            <div class="chart-box"><canvas id="ch2"></canvas></div>
            <div class="chart-box"><canvas id="ch3"></canvas></div>
            <div class="chart-box"><canvas id="ch4"></canvas></div>
        </div>
    </div>

    <div class="secao">
        <div class="secao-titulo">🏆 Destaques</div>
        <div class="destaques-grid">
            <div class="destaque-box melhor">
                <h4>🏆 Melhor Turma</h4>
                <?php if ($melhorTurma): ?>
                <div class="destaque-turma">
                    <div class="turma-nome"><?= htmlspecialchars($melhorTurma['turma']) ?></div>
                    <div class="turma-media" style="color:#2ecc71;">Média: <?= number_format($melhorTurma['media'], 1) ?></div>
                    <div class="turma-info"><?= $statsPorTurma[$melhorTurma['turma']]['total'] ?> reg • <?= $statsPorTurma[$melhorTurma['turma']]['aprovados'] ?> aprov</div>
                </div>
                <?php endif; ?>
            </div>
            <div class="destaque-box pior">
                <h4>⚠️ Menor Desempenho</h4>
                <?php if ($piorTurma): ?>
                <div class="destaque-turma">
                    <div class="turma-nome"><?= htmlspecialchars($piorTurma['turma']) ?></div>
                    <div class="turma-media" style="color:#e74c3c;">Média: <?= number_format($piorTurma['media'], 1) ?></div>
                    <div class="turma-info"><?= $statsPorTurma[$piorTurma['turma']]['total'] ?> reg • <?= $statsPorTurma[$piorTurma['turma']]['reprovados'] ?> reprov</div>
                </div>
                <?php endif; ?>
            </div>
            <div class="destaque-box">
                <h4>🥇 Top 5 Alunos (Geral)</h4>
                <div class="top-lista">
                    <?php foreach ($topGlobal as $idx => $aluno): ?>
                    <div class="top-item">
                        <div class="pos pos-<?= ($idx+1) > 3 ? 3 : ($idx+1) ?>"><?= $idx + 1 ?>º</div>
                        <div class="nome"><?= htmlspecialchars($aluno['nome']) ?> <span style="font-size:8px;color:#94a3b8;">(<?= htmlspecialchars($aluno['turma']) ?>)</span></div>
                        <div class="media"><?= number_format($aluno['media'], 1) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="rodape-doc">Relatório gerado em <?= date('d/m/Y \à\s H:i:s') ?> • <?= htmlspecialchars($nomeEmpresa) ?> • Página 1</div>
</div>

<!-- ============ PÁGINA 2 (Tabela + Assinaturas) ============ -->
<div class="folha-a4">
    <div class="header-inst" style="margin-bottom:8px;">
        <div class="linha1">REPÚBLICA DE ANGOLA</div>
        <div class="escola-nome" style="font-size:13px;margin:3px 0;"><?= htmlspecialchars($nomeEmpresa) ?></div>
        <div class="titulo-rel" style="font-size:11px;">📋 DETALHAMENTO DE NOTAS</div>
    </div>

    <div style="font-size:9px;color:#4a5568;margin-bottom:6px;text-align:center;">
        Ano Letivo: <strong><?= htmlspecialchars($ano_letivo) ?></strong> • 
        Total: <strong><?= $totalRegistros ?></strong> registros • 
        Média Geral: <strong><?= number_format($mediaGeral, 1) ?></strong>
    </div>

    <table class="tabela">
        <thead>
            <tr>
                <th class="center">#</th>
                <th>Nome do Aluno</th>
                <th class="center">Sexo</th>
                <th class="center">Classe</th>
                <th class="center">Turma</th>
                <th>Disciplina</th>
                <th class="center">1º T</th>
                <th class="center">2º T</th>
                <th class="center">3º T</th>
                <th class="center">MFD</th>
                <th class="center">Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($notasProcessadas) > 0): ?>
                <?php foreach($notasProcessadas as $i => $n): 
                    $mfd = $n['mfd_calc'];
                    $lim = $n['limites'];
                    $situacao = $n['situacao'];
                    $escala10 = $n['escala10'];
                    
                    $mt1 = ($n['mt1'] !== null && $n['mt1'] !== '') ? number_format((float)$n['mt1'], 1) : '—';
                    $mt2 = ($n['mt2'] !== null && $n['mt2'] !== '') ? number_format((float)$n['mt2'], 1) : '—';
                    $mt3 = ($n['mt3'] !== null && $n['mt3'] !== '') ? number_format((float)$n['mt3'], 1) : '—';
                    
                    if ($mfd === null) {
                        $mfdDisplay = '—'; $mfdCor = 'mfd-vermelho';
                    } else {
                        $mfdDisplay = number_format($mfd, 1);
                        if ($mfd >= $lim['aprovado']) $mfdCor = 'mfd-verde';
                        elseif ($mfd >= $lim['recuperacao']) $mfdCor = 'mfd-amarelo';
                        else $mfdCor = 'mfd-vermelho';
                    }
                    
                    $sexo = $n['sexo'] ?? '';
                    $sexoIcon = ($sexo === 'M' || strtoupper($sexo) === 'MASCULINO') ? 'M' : (($sexo === 'F' || strtoupper($sexo) === 'FEMININO') ? 'F' : '—');
                ?>
                <tr>
                    <td class="num"><?= $i + 1 ?></td>
                    <td class="nome"><?= htmlspecialchars($n['nome_aluno'] ?? '-') ?></td>
                    <td class="center"><?= $sexoIcon ?></td>
                    <td class="center"><?= htmlspecialchars($n['classe'] ?? '-') ?> <span style="font-size:7px;color:#94a3b8;">(<?= $escala10 ? '0-10' : '0-20' ?>)</span></td>
                    <td class="center"><?= htmlspecialchars($n['turma'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($n['disciplina'] ?? '-') ?></td>
                    <td class="nota-cell"><?= $mt1 ?></td>
                    <td class="nota-cell"><?= $mt2 ?></td>
                    <td class="nota-cell"><?= $mt3 ?></td>
                    <td class="nota-cell"><span class="mfd-badge <?= $mfdCor ?>"><?= $mfdDisplay ?></span></td>
                    <td class="center"><span class="status-badge <?= $situacao['class'] ?>"><?= $situacao['label'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="11" style="text-align:center;padding:20px;color:#94a3b8;">Nenhum registro encontrado</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="resumo-final">
        <div class="resumo-item" style="background:#dbeafe;">
            <div class="titulo">Total</div>
            <div class="valor"><?= number_format($totalRegistros, 0, ',', '.') ?></div>
        </div>
        <div class="resumo-item" style="background:#d1fae5;">
            <div class="titulo">Aprovados</div>
            <div class="valor" style="color:#065f46"><?= number_format($totalAprovados, 0, ',', '.') ?></div>
            <div class="detalhe"><?= $percAprov ?>%</div>
        </div>
        <div class="resumo-item" style="background:#fef3c7;">
            <div class="titulo">Recuperação</div>
            <div class="valor" style="color:#92400e"><?= number_format($totalRecuperacao, 0, ',', '.') ?></div>
            <div class="detalhe"><?= $percRecup ?>%</div>
        </div>
        <div class="resumo-item" style="background:#fee2e2;">
            <div class="titulo">Reprovados</div>
            <div class="valor" style="color:#991b1b"><?= number_format($totalReprovados, 0, ',', '.') ?></div>
            <div class="detalhe"><?= $percReprov ?>%</div>
        </div>
        <div class="resumo-item" style="background:#f3e8ff;">
            <div class="titulo">Média Geral</div>
            <div class="valor" style="color:#6b21a8"><?= number_format($mediaGeral, 1) ?></div>
        </div>
    </div>

    <div class="assinaturas">
        <div class="assinatura">
            <div class="linha-ass"></div>
            <div class="nome">O Professor</div>
            <div class="cargo"><?= htmlspecialchars($usuario_nome) ?></div>
        </div>
        <div class="assinatura">
            <div class="linha-ass"></div>
            <div class="nome">O Coordenador Pedagógico</div>
            <div class="cargo">Assinatura e Data</div>
        </div>
        <div class="assinatura">
            <div class="linha-ass"></div>
            <div class="nome">O Subdirector Pedagógico</div>
            <div class="cargo">Assinatura e Data</div>
        </div>
    </div>

    <div class="rodape-doc">
        Documento gerado em <?= date('d/m/Y \à\s H:i:s') ?> por <?= htmlspecialchars($usuario_nome) ?> • <?= htmlspecialchars($nomeEmpresa) ?> • Página 2
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Situação geral
    new Chart(document.getElementById('ch1'), {
        type: 'doughnut',
        data: {
            labels: ['Aprovados', 'Recuperação', 'Reprovados', 'Sem nota'],
            datasets: [{
                data: [<?= $totalAprovados ?>, <?= $totalRecuperacao ?>, <?= $totalReprovados ?>, <?= $totalSemNota ?>],
                backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c', '#94a3b8'],
                borderWidth: 2, borderColor: '#fff'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 9 }, padding: 6 } },
                title: { display: true, text: 'Situação Geral', font: { size: 10, weight: 'bold' } }
            },
            cutout: '55%'
        }
    });

    // 2. Média por classe
    new Chart(document.getElementById('ch2'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($mediasClasses)) ?>,
            datasets: [{ label: 'Média', data: <?= json_encode(array_values($mediasClasses)) ?>, backgroundColor: '#3498db', borderRadius: 4 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Média por Classe', font: { size: 10, weight: 'bold' } }
            },
            scales: { y: { beginAtZero: true, ticks: { font: { size: 8 } } }, x: { ticks: { font: { size: 8 } } } }
        }
    });

    // 3. Aprovação por turma
    new Chart(document.getElementById('ch3'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($statsPorTurma)) ?>,
            datasets: [
                { label: 'Aprov', data: <?= json_encode(array_map(function($s) { return $s['aprovados']; }, array_values($statsPorTurma))) ?>, backgroundColor: '#2ecc71' },
                { label: 'Recup', data: <?= json_encode(array_map(function($s) { return $s['recuperacao']; }, array_values($statsPorTurma))) ?>, backgroundColor: '#f39c12' },
                { label: 'Reprov', data: <?= json_encode(array_map(function($s) { return $s['reprovados']; }, array_values($statsPorTurma))) ?>, backgroundColor: '#e74c3c' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 8 }, padding: 4 } },
                title: { display: true, text: 'Aprovação por Turma', font: { size: 10, weight: 'bold' } }
            },
            scales: { x: { ticks: { font: { size: 7 } } }, y: { beginAtZero: true, ticks: { font: { size: 8 } } } }
        }
    });

    // 4. Aprovação por classe
    new Chart(document.getElementById('ch4'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($statsPorClasse)) ?>,
            datasets: [
                { label: 'Aprovados', data: <?= json_encode(array_map(function($s) { return $s['aprovados']; }, array_values($statsPorClasse))) ?>, backgroundColor: '#27ae60', borderRadius: 4 },
                { label: 'Reprovados', data: <?= json_encode(array_map(function($s) { return $s['reprovados']; }, array_values($statsPorClasse))) ?>, backgroundColor: '#c0392b', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 8 }, padding: 4 } },
                title: { display: true, text: 'Aprovação por Classe', font: { size: 10, weight: 'bold' } }
            },
            scales: { x: { ticks: { font: { size: 8 } } }, y: { beginAtZero: true, ticks: { font: { size: 8 } } } }
        }
    });
});
</script>

</body>
</html>