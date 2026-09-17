<?php
// ============================================
// modules/escola/notas/relatorio_nota.php
// Relatório Completo de Notas com Gráficos
// ============================================

// Descobre a raiz do projeto (sobe 3 níveis)
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

// Dados da empresa (para o cabeçalho)
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

// ===== FUNÇÕES AUXILIARES =====
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
    error_log("Erro ao buscar notas: " . $e->getMessage());
}

// ===== PROCESSAR NOTAS =====
$notasProcessadas = [];
$totalAprovados = 0;
$totalReprovados = 0;
$totalRecuperacao = 0;
$totalSemNota = 0;
$somaMedias = 0;
$countMedias = 0;

// Agrupamentos
$statsPorTurma  = [];
$statsPorClasse = [];
$alunosPorTurma = [];

foreach ($notas as $n) {
    $classeN = $n['classe'] ?? '';
    $turmaN  = $n['turma']  ?? '';
    $lim = getLimitesPorClasse($classeN);
    $escala10 = isEscala10($classeN);

    $mfdCalc = calcularMFD($n['mt1'], $n['mt2'], $n['mt3'], true);
    if ($mfdCalc === null) {
        $mfdCalc = ($n['mfd'] !== null && $n['mfd'] !== '' && is_numeric($n['mfd']))
            ? (float)$n['mfd'] : null;
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

    if ($mfdCalc !== null) {
        $somaMedias += $mfdCalc;
        $countMedias++;
    }

    // ===== AGRUPAR POR TURMA =====
    if (!isset($statsPorTurma[$turmaN])) {
        $statsPorTurma[$turmaN] = [
            'total' => 0, 'aprovados' => 0, 'reprovados' => 0,
            'recuperacao' => 0, 'sem_nota' => 0,
            'soma' => 0, 'count' => 0, 'classe' => $classeN
        ];
    }
    $statsPorTurma[$turmaN]['total']++;
    if ($situacao['label'] === 'Aprovado')      $statsPorTurma[$turmaN]['aprovados']++;
    elseif ($situacao['label'] === 'Reprovado') $statsPorTurma[$turmaN]['reprovados']++;
    elseif ($situacao['label'] === 'Recuperação') $statsPorTurma[$turmaN]['recuperacao']++;
    else $statsPorTurma[$turmaN]['sem_nota']++;
    if ($mfdCalc !== null) {
        $statsPorTurma[$turmaN]['soma'] += $mfdCalc;
        $statsPorTurma[$turmaN]['count']++;
    }

    // ===== AGRUPAR POR CLASSE =====
    if (!isset($statsPorClasse[$classeN])) {
        $statsPorClasse[$classeN] = [
            'total' => 0, 'aprovados' => 0, 'reprovados' => 0,
            'recuperacao' => 0, 'soma' => 0, 'count' => 0
        ];
    }
    $statsPorClasse[$classeN]['total']++;
    if ($situacao['label'] === 'Aprovado')      $statsPorClasse[$classeN]['aprovados']++;
    elseif ($situacao['label'] === 'Reprovado') $statsPorClasse[$classeN]['reprovados']++;
    elseif ($situacao['label'] === 'Recuperação') $statsPorClasse[$classeN]['recuperacao']++;
    if ($mfdCalc !== null) {
        $statsPorClasse[$classeN]['soma'] += $mfdCalc;
        $statsPorClasse[$classeN]['count']++;
    }

    // ===== MELHORES ALUNOS POR TURMA (agrupar por aluno) =====
    $key = $turmaN . '||' . $n['id_aluno'];
    if (!isset($alunosPorTurma[$turmaN][$n['id_aluno']])) {
        $alunosPorTurma[$turmaN][$n['id_aluno']] = [
            'nome' => $n['nome_aluno'], 'soma' => 0, 'count' => 0
        ];
    }
    if ($mfdCalc !== null) {
        $alunosPorTurma[$turmaN][$n['id_aluno']]['soma'] += $mfdCalc;
        $alunosPorTurma[$turmaN][$n['id_aluno']]['count']++;
    }

    $notasProcessadas[] = array_merge($n, [
        'mfd_calc' => $mfdCalc,
        'situacao' => $situacao,
        'limites'  => $lim,
        'escala10' => $escala10
    ]);
}

$totalRegistros = count($notasProcessadas);
$mediaGeral = $countMedias > 0 ? round($somaMedias / $countMedias, 1) : 0;
$percAprov  = $totalRegistros > 0 ? round(($totalAprovados / $totalRegistros) * 100, 1) : 0;
$percReprov = $totalRegistros > 0 ? round(($totalReprovados / $totalRegistros) * 100, 1) : 0;
$percRecup  = $totalRegistros > 0 ? round(($totalRecuperacao / $totalRegistros) * 100, 1) : 0;

// ===== CALCULAR MÉDIAS DAS TURMAS E CLASSES =====
$mediasTurmas = [];
foreach ($statsPorTurma as $t => $s) {
    $mediasTurmas[$t] = $s['count'] > 0 ? round($s['soma'] / $s['count'], 1) : 0;
}
$mediasClasses = [];
foreach ($statsPorClasse as $c => $s) {
    $mediasClasses[$c] = $s['count'] > 0 ? round($s['soma'] / $s['count'], 1) : 0;
}

// ===== MELHOR E PIOR TURMA =====
$melhorTurma = null;
$piorTurma = null;
if (!empty($mediasTurmas)) {
    arsort($mediasTurmas);
    $melhorTurmaNome = key($mediasTurmas);
    $melhorTurma = ['turma' => $melhorTurmaNome, 'media' => $mediasTurmas[$melhorTurmaNome]];
    asort($mediasTurmas);
    $piorTurmaNome = key($mediasTurmas);
    $piorTurma = ['turma' => $piorTurmaNome, 'media' => $mediasTurmas[$piorTurmaNome]];
}

// ===== TOP 3 ALUNOS POR TURMA =====
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

// ===== FILTROS DISPONÍVEIS =====
$classes = [];
$turmas = [];
$disciplinas = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT Classe FROM alunos WHERE status = 'ativo' AND Classe IS NOT NULL AND Classe != '' ORDER BY Classe");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE status = 'ativo' AND TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA");
    $turmas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("SELECT DISTINCT disciplina FROM notas_alunos WHERE disciplina IS NOT NULL AND disciplina != '' ORDER BY disciplina");
    $disciplinas = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// ===== INCLUIR HEADER =====
include $root_path . '/modules/escola/includes/header_escola.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; color: #1a2332; padding: 20px; font-size: 13px; }
    .container { max-width: 1400px; margin: 0 auto; }

    /* ============ CABEÇALHO INSTITUCIONAL (IMPRESSÃO) ============ */
    .print-header { display: none; text-align: center; border-bottom: 3px solid #1a2332; padding-bottom: 15px; margin-bottom: 20px; }
    .print-header .linha1 { font-size: 14px; font-weight: 700; letter-spacing: 2px; }
    .print-header .linha2 { font-size: 13px; font-weight: 600; letter-spacing: 1px; }
    .print-header .linha3 { font-size: 15px; font-weight: 800; text-transform: uppercase; margin-top: 4px; }
    .print-header .escola-nome { font-size: 18px; font-weight: 900; text-transform: uppercase; margin: 8px 0 4px; color: #1a2332; }
    .print-header .dados-empresa { font-size: 11px; color: #4a5568; margin-top: 4px; }
    .print-header .titulo-rel { font-size: 16px; font-weight: 800; margin-top: 10px; text-transform: uppercase; letter-spacing: 2px; color: #1a2332; }

    /* ============ HEADER DA PÁGINA ============ */
    .page-header { background: linear-gradient(135deg, #1a2332, #2c3e50); color: #fff; padding: 20px 25px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
    .page-header h1 { font-size: 22px; margin: 0; display: flex; align-items: center; gap: 10px; }
    .page-header h1 i { color: #c9a84c; }
    .page-header .subtitle { font-size: 13px; color: #cbd5e1; margin-top: 4px; }
    .page-header .actions { display: flex; gap: 8px; flex-wrap: wrap; }

    .btn { padding: 8px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; }
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-success { background: #2ecc71; color: #fff; }
    .btn-info { background: #3498db; color: #fff; }
    .btn-warning { background: #f39c12; color: #fff; }
    .btn-print { background: #6c757d; color: #fff; }

    /* ============ CARDS ESTATÍSTICAS ============ */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .stat-card { background: #fff; padding: 18px 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #c9a84c; }
    .stat-card.aprovados { border-left-color: #2ecc71; }
    .stat-card.reprovados { border-left-color: #e74c3c; }
    .stat-card.recuperacao { border-left-color: #f39c12; }
    .stat-card.total { border-left-color: #3498db; }
    .stat-card.media { border-left-color: #9b59b6; }
    .stat-card .label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
    .stat-card .number { font-size: 26px; font-weight: 700; color: #1a2332; margin: 5px 0 2px; }
    .stat-card .extra { font-size: 11px; color: #64748b; }
    .stat-card .icon { font-size: 20px; float: right; opacity: 0.3; }
    .progress-bar { height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-top: 8px; }
    .progress-fill { height: 100%; border-radius: 3px; }

    /* ============ FILTROS ============ */
    .filtros { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .filtros form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
    .form-group { flex: 1; min-width: 140px; }
    .form-group label { display: block; font-weight: 600; font-size: 11px; color: #4a5568; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
    .form-group input, .form-group select { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; background: #fff; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }
    .form-group.buttons { flex: 0 0 auto; display: flex; gap: 8px; }

    /* ============ GRÁFICOS ============ */
    .graficos-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .chart-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .chart-card h3 { font-size: 14px; color: #1a2332; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #c9a84c; display: flex; align-items: center; gap: 8px; }
    .chart-container { position: relative; height: 280px; }

    /* ============ DESTAQUES ============ */
    .destaques-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .destaque-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-top: 4px solid #c9a84c; }
    .destaque-card.melhor { border-top-color: #2ecc71; }
    .destaque-card.pior { border-top-color: #e74c3c; }
    .destaque-card.top { border-top-color: #c9a84c; }
    .destaque-card h3 { font-size: 14px; color: #1a2332; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }

    .destaque-turma { text-align: center; padding: 15px; background: #f8fafc; border-radius: 10px; }
    .destaque-turma .turma-nome { font-size: 32px; font-weight: 900; color: #1a2332; }
    .destaque-turma .turma-media { font-size: 20px; font-weight: 700; margin-top: 5px; }
    .destaque-turma .turma-info { font-size: 12px; color: #64748b; margin-top: 5px; }

    .top-lista { display: flex; flex-direction: column; gap: 8px; }
    .top-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #f8fafc; border-radius: 8px; transition: all 0.2s; }
    .top-item:hover { background: #f0f4f9; transform: translateX(3px); }
    .top-item .pos { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 800; font-size: 12px; flex-shrink: 0; }
    .top-item .pos-1 { background: #fef3c7; color: #92400e; }
    .top-item .pos-2 { background: #e5e7eb; color: #4b5563; }
    .top-item .pos-3 { background: #fed7aa; color: #9a3412; }
    .top-item .nome { flex: 1; font-weight: 600; font-size: 13px; color: #1a2332; }
    .top-item .media { font-weight: 800; color: #1e40af; font-size: 13px; padding: 3px 10px; background: #dbeafe; border-radius: 6px; }

    /* ============ TABELA ============ */
    .table-wrapper { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow-x: auto; margin-bottom: 20px; }
    .table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 1200px; }
    .table thead th { background: #1a2332; color: #fff; padding: 12px 10px; text-align: left; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
    .table thead th.center { text-align: center; }
    .table tbody td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .table tbody tr:hover { background: #fafbfc; }
    .table .num { text-align: center; font-weight: 700; color: #c9a84c; width: 40px; }
    .table .nome { font-weight: 600; color: #1a2332; min-width: 180px; }
    .table .nota-cell { text-align: center; font-weight: 600; }
    .table .mfd-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; }
    .table .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }

    .mfd-verde { background: #d4edda; color: #155724; }
    .mfd-amarelo { background: #fff3cd; color: #856404; }
    .mfd-vermelho { background: #f8d7da; color: #721c24; }

    .status-aprovado { background: #d1fae5; color: #065f46; }
    .status-reprovado { background: #fee2e2; color: #991b1b; }
    .status-recuperacao { background: #fef3c7; color: #92400e; }
    .status-dispensado { background: #f1f5f9; color: #4a5568; }

    .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
    .empty-state .icon { font-size: 56px; display: block; margin-bottom: 15px; opacity: 0.5; }
    .empty-state h3 { font-size: 18px; color: #4a5568; margin: 0 0 5px; }

    /* ============ RESUMO FINAL ============ */
    .resumo-final { background: #fff; padding: 20px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; }
    .resumo-item { text-align: center; padding: 15px; border-radius: 10px; background: #f8fafc; }
    .resumo-item .titulo { font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
    .resumo-item .valor { font-size: 24px; font-weight: 700; color: #1a2332; margin: 5px 0; }
    .resumo-item .detalhe { font-size: 11px; color: #94a3b8; }

    /* ============ ASSINATURAS (IMPRESSÃO) ============ */
    .print-assinaturas { display: none; margin-top: 50px; padding-top: 20px; }
    .print-assinaturas .linha { display: flex; justify-content: space-around; gap: 40px; }
    .print-assinaturas .assinatura { text-align: center; flex: 1; }
    .print-assinaturas .linha-ass { border-top: 1px solid #333; margin: 50px auto 5px; width: 80%; }
    .print-assinaturas .nome { font-size: 12px; font-weight: 700; }
    .print-assinaturas .cargo { font-size: 11px; color: #555; }

    /* ============ IMPRESSÃO ============ */
    @media print {
        body { background: #fff; padding: 0; font-size: 10px; }
        .no-print, .page-header, .filtros { display: none !important; }
        .print-header { display: block !important; }
        .print-assinaturas { display: block !important; }
        .container { max-width: 100%; padding: 0; }
        .stats-grid { grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .stat-card { padding: 10px 12px; border-left-width: 3px; }
        .stat-card .number { font-size: 18px; }
        .stat-card .label, .stat-card .extra { font-size: 9px; }
        .graficos-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .chart-card { padding: 12px; }
        .chart-card h3 { font-size: 11px; margin-bottom: 8px; }
        .chart-container { height: 200px; }
        .destaques-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .destaque-card { padding: 12px; }
        .table-wrapper, .stats-grid, .resumo-final, .chart-card, .destaque-card { box-shadow: none; border: 1px solid #ddd; }
        .table thead th { background: #1a2332 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-size: 9px; padding: 6px 4px; }
        .table tbody td { font-size: 9px; padding: 5px 4px; }
        .table { min-width: auto; font-size: 9px; }
        .mfd-badge, .status-badge, .stat-card, .top-item .pos, .top-item .media, .resumo-item { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .top-item { padding: 6px 8px; }
        .top-item .nome { font-size: 10px; }
        .top-item .media { font-size: 10px; padding: 2px 6px; }
        .destaque-turma .turma-nome { font-size: 24px; }
        .destaque-turma .turma-media { font-size: 16px; }
        .page-break { page-break-before: always; }
        @page { size: A4 landscape; margin: 10mm; }
    }

    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: stretch; text-align: center; }
        .filtros form { flex-direction: column; }
        .form-group { min-width: 100%; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .graficos-grid, .destaques-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="container">

    <!-- ============ CABEÇALHO INSTITUCIONAL (SÓ NA IMPRESSÃO) ============ -->
    <div class="print-header">
        <div class="linha1">REPÚBLICA DE ANGOLA</div>
        <div class="linha2">GOVERNO PROVINCIAL DO ÍCOLO E BENGO</div>
        <div class="linha3">DIRECÇÃO MUNICIPAL DA EDUCAÇÃO DE BOM JESUS</div>
        <div class="escola-nome"><?= htmlspecialchars($nomeEmpresa) ?></div>
        <?php if ($enderecoCompleto || $telefoneEmpresa || $emailEmpresa): ?>
        <div class="dados-empresa">
            <?= htmlspecialchars($enderecoCompleto) ?>
            <?php if ($telefoneEmpresa): ?> | Tel: <?= htmlspecialchars($telefoneEmpresa) ?><?php endif; ?>
            <?php if ($emailEmpresa): ?> | Email: <?= htmlspecialchars($emailEmpresa) ?><?php endif; ?>
            <?php if ($cnpjEmpresa): ?> | NIF: <?= htmlspecialchars($cnpjEmpresa) ?><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="titulo-rel">📊 RELATÓRIO COMPLETO DE NOTAS</div>
        <div style="font-size:11px;margin-top:5px;color:#4a5568;">
            Ano Letivo: <strong><?= htmlspecialchars($ano_letivo) ?></strong> • 
            Gerado em: <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <!-- ============ HEADER DA PÁGINA (TELA) ============ -->
    <div class="page-header no-print">
        <div>
            <h1><i class="fas fa-chart-bar"></i> Relatório Completo de Notas</h1>
            <div class="subtitle">
                📅 Ano Letivo: <strong><?= htmlspecialchars($ano_letivo) ?></strong> • 
                📊 <?= $totalRegistros ?> registro(s) • 
                🎓 <?= htmlspecialchars($escola_nome) ?>
            </div>
        </div>
        <div class="actions">
            <button class="btn btn-print" onclick="abrirVisualizador()"><i class="fas fa-print"></i> Imprimir</button>
            <a href="index.php" class="btn btn-secondary">← Voltar</a>
        </div>
    </div>

    <!-- ============ ESTATÍSTICAS ============ -->
    <div class="stats-grid">
        <div class="stat-card total">
            <i class="fas fa-list icon"></i>
            <div class="label">Total</div>
            <div class="number"><?= number_format($totalRegistros, 0, ',', '.') ?></div>
            <div class="extra">registros</div>
        </div>
        <div class="stat-card aprovados">
            <i class="fas fa-graduation-cap icon"></i>
            <div class="label">Aprovados</div>
            <div class="number" style="color:#2ecc71"><?= number_format($totalAprovados, 0, ',', '.') ?></div>
            <div class="extra"><?= $percAprov ?>%</div>
        </div>
        <div class="stat-card recuperacao">
            <i class="fas fa-book icon"></i>
            <div class="label">Recuperação</div>
            <div class="number" style="color:#f39c12"><?= number_format($totalRecuperacao, 0, ',', '.') ?></div>
            <div class="extra"><?= $percRecup ?>%</div>
        </div>
        <div class="stat-card reprovados">
            <i class="fas fa-times-circle icon"></i>
            <div class="label">Reprovados</div>
            <div class="number" style="color:#e74c3c"><?= number_format($totalReprovados, 0, ',', '.') ?></div>
            <div class="extra"><?= $percReprov ?>%</div>
        </div>
        <div class="stat-card media">
            <i class="fas fa-chart-line icon"></i>
            <div class="label">Média Geral</div>
            <div class="number"><?= number_format($mediaGeral, 1) ?></div>
            <div class="extra"><?= $countMedias ?> notas</div>
        </div>
    </div>

    <!-- ============ FILTROS ============ -->
    <div class="filtros no-print">
        <form method="GET">
            <div class="form-group">
                <label>🔍 Buscar Aluno</label>
                <input type="text" name="busca_aluno" value="<?= htmlspecialchars($busca_aluno) ?>" placeholder="Nome do aluno...">
            </div>
            <div class="form-group">
                <label>📅 Ano Letivo</label>
                <input type="text" name="ano_letivo" value="<?= htmlspecialchars($ano_letivo) ?>">
            </div>
            <div class="form-group">
                <label>📚 Classe</label>
                <select name="classe">
                    <option value="">Todas</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= ($classe_filtro == $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?>ª</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>👥 Turma</label>
                <select name="turma">
                    <option value="">Todas</option>
                    <?php foreach($turmas as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= ($turma_filtro == $t) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>📖 Disciplina</label>
                <select name="disciplina">
                    <option value="">Todas</option>
                    <?php foreach($disciplinas as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= ($disciplina == $d) ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>🎯 Situação</label>
                <select name="situacao">
                    <option value="">Todas</option>
                    <option value="aprovado" <?= ($situacao_filt == 'aprovado') ? 'selected' : '' ?>>✅ Aprovado</option>
                    <option value="recuperacao" <?= ($situacao_filt == 'recuperacao') ? 'selected' : '' ?>>📖 Recuperação</option>
                    <option value="reprovado" <?= ($situacao_filt == 'reprovado') ? 'selected' : '' ?>>❌ Reprovado</option>
                    <option value="sem_nota" <?= ($situacao_filt == 'sem_nota') ? 'selected' : '' ?>>— Sem nota</option>
                </select>
            </div>
            <div class="form-group buttons">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                <a href="relatorio_nota.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <!-- ============ GRÁFICOS ============ -->
    <?php if ($totalRegistros > 0): ?>
    <div class="graficos-grid">
        <div class="chart-card">
            <h3>📊 Situação Geral dos Alunos</h3>
            <div class="chart-container">
                <canvas id="chartSituacao"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3>📈 Desempenho por Classe (Média)</h3>
            <div class="chart-container">
                <canvas id="chartClasses"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3>🎯 Aprovação por Turma</h3>
            <div class="chart-container">
                <canvas id="chartTurmas"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3>📉 Aprovação por Classe</h3>
            <div class="chart-container">
                <canvas id="chartAprovClasse"></canvas>
            </div>
        </div>
    </div>

    <!-- ============ DESTAQUES ============ -->
    <div class="destaques-grid">

        <!-- MELHOR TURMA -->
        <div class="destaque-card melhor">
            <h3>🏆 Melhor Turma</h3>
            <?php if ($melhorTurma): ?>
            <div class="destaque-turma">
                <div class="turma-nome"><?= htmlspecialchars($melhorTurma['turma']) ?></div>
                <div class="turma-media" style="color:#2ecc71;">Média: <?= number_format($melhorTurma['media'], 1) ?></div>
                <div class="turma-info">
                    <?= $statsPorTurma[$melhorTurma['turma']]['total'] ?> registros • 
                    <?= $statsPorTurma[$melhorTurma['turma']]['aprovados'] ?> aprovados
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:20px;"><p>Sem dados</p></div>
            <?php endif; ?>
        </div>

        <!-- PIOR TURMA -->
        <div class="destaque-card pior">
            <h3>⚠️ Turma com Menor Desempenho</h3>
            <?php if ($piorTurma): ?>
            <div class="destaque-turma">
                <div class="turma-nome"><?= htmlspecialchars($piorTurma['turma']) ?></div>
                <div class="turma-media" style="color:#e74c3c;">Média: <?= number_format($piorTurma['media'], 1) ?></div>
                <div class="turma-info">
                    <?= $statsPorTurma[$piorTurma['turma']]['total'] ?> registros • 
                    <?= $statsPorTurma[$piorTurma['turma']]['reprovados'] ?> reprovados
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:20px;"><p>Sem dados</p></div>
            <?php endif; ?>
        </div>

        <!-- TOP 3 ALUNOS (primeira turma) -->
        <div class="destaque-card top">
            <h3>🥇 Melhores Alunos</h3>
            <?php if (!empty($topAlunosPorTurma)): ?>
                <?php 
                $primeiraTurma = array_key_first($topAlunosPorTurma);
                $top3 = $topAlunosPorTurma[$primeiraTurma];
                ?>
                <div style="font-size:11px;color:#64748b;margin-bottom:8px;">Turma <?= htmlspecialchars($primeiraTurma) ?></div>
                <div class="top-lista">
                    <?php foreach ($top3 as $idx => $aluno): ?>
                    <div class="top-item">
                        <div class="pos pos-<?= $idx + 1 ?>"><?= $idx + 1 ?>º</div>
                        <div class="nome"><?= htmlspecialchars($aluno['nome']) ?></div>
                        <div class="media"><?= number_format($aluno['media'], 1) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
            <div class="empty-state" style="padding:20px;"><p>Sem dados</p></div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============ TOP 3 ALUNOS POR TURMA ============ -->
    <?php if (count($topAlunosPorTurma) > 0): ?>
    <div class="chart-card" style="margin-bottom:20px;">
        <h3>🏅 Melhores Alunos por Turma (Top 3)</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:15px;">
            <?php foreach ($topAlunosPorTurma as $turmaN => $top3): ?>
            <div style="background:#f8fafc; border-radius:10px; padding:15px; border-left:3px solid #c9a84c;">
                <div style="font-weight:700;color:#1a2332;margin-bottom:10px;font-size:13px;">
                    👥 Turma <?= htmlspecialchars($turmaN) ?>
                    <span style="font-weight:400;color:#64748b;font-size:11px;">
                        (<?= $statsPorTurma[$turmaN]['total'] ?? 0 ?> reg. • 
                        Média: <?= number_format($mediasTurmas[$turmaN] ?? 0, 1) ?>)
                    </span>
                </div>
                <div class="top-lista">
                    <?php foreach ($top3 as $idx => $aluno): ?>
                    <div class="top-item">
                        <div class="pos pos-<?= $idx + 1 ?>"><?= $idx + 1 ?>º</div>
                        <div class="nome"><?= htmlspecialchars($aluno['nome']) ?></div>
                        <div class="media"><?= number_format($aluno['media'], 1) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ============ TABELA DETALHADA ============ -->
    <div class="table-wrapper">
        <table class="table">
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
                    <th class="center">Ano</th>
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
                            $mfdDisplay = '—';
                            $mfdCor = 'mfd-vermelho';
                        } else {
                            $mfdDisplay = number_format($mfd, 1);
                            if ($mfd >= $lim['aprovado']) $mfdCor = 'mfd-verde';
                            elseif ($mfd >= $lim['recuperacao']) $mfdCor = 'mfd-amarelo';
                            else $mfdCor = 'mfd-vermelho';
                        }
                        
                        $sexo = $n['sexo'] ?? '';
                        $sexoIcon = '—';
                        if (strtoupper($sexo) === 'M' || strtoupper($sexo) === 'MASCULINO') $sexoIcon = '👨';
                        elseif (strtoupper($sexo) === 'F' || strtoupper($sexo) === 'FEMININO') $sexoIcon = '👩';
                    ?>
                    <tr>
                        <td class="num"><?= $i + 1 ?></td>
                        <td class="nome"><?= htmlspecialchars($n['nome_aluno'] ?? '-') ?></td>
                        <td class="center"><?= $sexoIcon ?></td>
                        <td class="center">
                            <?= htmlspecialchars($n['classe'] ?? '-') ?>
                            <span style="font-size:10px;color:#94a3b8;display:block;">(<?= $escala10 ? '0-10' : '0-20' ?>)</span>
                        </td>
                        <td class="center"><?= htmlspecialchars($n['turma'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($n['disciplina'] ?? '-') ?></td>
                        <td class="nota-cell"><?= $mt1 ?></td>
                        <td class="nota-cell"><?= $mt2 ?></td>
                        <td class="nota-cell"><?= $mt3 ?></td>
                        <td class="nota-cell"><span class="mfd-badge <?= $mfdCor ?>"><?= $mfdDisplay ?></span></td>
                        <td class="center"><span class="status-badge <?= $situacao['class'] ?>"><?= $situacao['label'] ?></span></td>
                        <td class="center" style="font-size:11px;color:#64748b;"><?= htmlspecialchars($n['ano_letivo'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12">
                            <div class="empty-state">
                                <div class="icon">📭</div>
                                <h3>Nenhum registro encontrado</h3>
                                <p>Ajuste os filtros ou verifique o ano letivo selecionado.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ============ RESUMO FINAL ============ -->
    <?php if (count($notasProcessadas) > 0): ?>
    <div class="resumo-final">
        <div class="resumo-item" style="background:#dbeafe;">
            <div class="titulo">Total Avaliados</div>
            <div class="valor"><?= number_format($totalRegistros, 0, ',', '.') ?></div>
            <div class="detalhe">registros</div>
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
            <div class="detalhe"><?= $countMedias ?> notas</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============ ASSINATURAS (SÓ NA IMPRESSÃO) ============ -->
    <div class="print-assinaturas">
        <div class="linha">
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
        <div style="text-align:center;margin-top:30px;font-size:10px;color:#666;">
            Documento gerado em <?= date('d/m/Y \à\s H:i') ?> por <?= htmlspecialchars($usuario_nome) ?> • <?= htmlspecialchars($nomeEmpresa) ?>
        </div>
    </div>

    <!-- ============ RODAPÉ (TELA) ============ -->
    <div class="no-print" style="text-align:center; margin-top:30px; padding:15px; color:#94a3b8; font-size:12px;">
        📅 Relatório gerado em <?= date('d/m/Y \à\s H:i:s') ?> • 
        👤 <?= htmlspecialchars($usuario_nome) ?> • 
        🎓 <?= htmlspecialchars($escola_nome) ?>
    </div>

</div>

<!-- ============ SCRIPTS DE GRÁFICOS ============ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== GRÁFICO 1: SITUAÇÃO GERAL =====
    const ctxSituacao = document.getElementById('chartSituacao');
    if (ctxSituacao) {
        new Chart(ctxSituacao, {
            type: 'doughnut',
            data: {
                labels: ['Aprovados', 'Recuperação', 'Reprovados', 'Sem nota'],
                datasets: [{
                    data: [
                        <?= $totalAprovados ?>,
                        <?= $totalRecuperacao ?>,
                        <?= $totalReprovados ?>,
                        <?= $totalSemNota ?>
                    ],
                    backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c', '#94a3b8'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                                var perc = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + perc + '%)';
                            }
                        }
                    }
                },
                cutout: '55%'
            }
        });
    }

    // ===== GRÁFICO 2: MÉDIA POR CLASSE =====
    const ctxClasses = document.getElementById('chartClasses');
    if (ctxClasses) {
        new Chart(ctxClasses, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($mediasClasses)) ?>,
                datasets: [{
                    label: 'Média',
                    data: <?= json_encode(array_values($mediasClasses)) ?>,
                    backgroundColor: '#3498db',
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { font: { size: 10 } } },
                    x: { ticks: { font: { size: 10 } } }
                }
            }
        });
    }

    // ===== GRÁFICO 3: APROVAÇÃO POR TURMA =====
    const ctxTurmas = document.getElementById('chartTurmas');
    if (ctxTurmas) {
        var turmasLabels = <?= json_encode(array_keys($statsPorTurma)) ?>;
        var turmasAprov = <?= json_encode(array_map(function($s) { return $s['aprovados']; }, array_values($statsPorTurma))) ?>;
        var turmasRecup = <?= json_encode(array_map(function($s) { return $s['recuperacao']; }, array_values($statsPorTurma))) ?>;
        var turmasReprov = <?= json_encode(array_map(function($s) { return $s['reprovados']; }, array_values($statsPorTurma))) ?>;
        
        new Chart(ctxTurmas, {
            type: 'bar',
            data: {
                labels: turmasLabels,
                datasets: [
                    { label: 'Aprovados', data: turmasAprov, backgroundColor: '#2ecc71', borderRadius: 4 },
                    { label: 'Recuperação', data: turmasRecup, backgroundColor: '#f39c12', borderRadius: 4 },
                    { label: 'Reprovados', data: turmasReprov, backgroundColor: '#e74c3c', borderRadius: 4 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 8 } } },
                scales: {
                    x: { stacked: false, ticks: { font: { size: 9 } } },
                    y: { beginAtZero: true, ticks: { font: { size: 10 } } }
                }
            }
        });
    }

    // ===== GRÁFICO 4: APROVAÇÃO POR CLASSE =====
    const ctxAprovClasse = document.getElementById('chartAprovClasse');
    if (ctxAprovClasse) {
        var classLabels = <?= json_encode(array_keys($statsPorClasse)) ?>;
        var classAprov = <?= json_encode(array_map(function($s) { return $s['aprovados']; }, array_values($statsPorClasse))) ?>;
        var classReprov = <?= json_encode(array_map(function($s) { return $s['reprovados']; }, array_values($statsPorClasse))) ?>;
        
        new Chart(ctxAprovClasse, {
            type: 'bar',
            data: {
                labels: classLabels,
                datasets: [
                    { label: 'Aprovados', data: classAprov, backgroundColor: '#27ae60', borderRadius: 4 },
                    { label: 'Reprovados', data: classReprov, backgroundColor: '#c0392b', borderRadius: 4 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 8 } } },
                scales: {
                    x: { ticks: { font: { size: 10 } } },
                    y: { beginAtZero: true, ticks: { font: { size: 10 } } }
                }
            }
        });
    }
});


function abrirVisualizador() {
    var params = window.location.search;
    var janela = window.open('imprimir_relatorio.php' + params, '_blank', 'width=1200,height=800,scrollbars=yes');
    if (!janela) {
        alert('⚠️ Permita popups para visualizar a impressão');
    }
}


</script>

<?php include $root_path . '/modules/escola/includes/footer_escola.php'; ?>