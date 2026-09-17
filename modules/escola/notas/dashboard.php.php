<?php
// ============================================
// modules/escola/notas/dashboard.php
// Dashboard de Notas - Visão Geral
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

// ===== FILTRO DE ANO LETIVO =====
$ano_letivo = $_GET['ano_letivo'] ?? (date('Y') . '/' . (date('Y') + 1));

// ===== BUSCAR ESTATÍSTICAS GERAIS =====
$totalRegistros = 0;
$totalAprovados = 0;
$totalReprovados = 0;
$totalRecuperacao = 0;
$totalSemNota = 0;
$somaMedias = 0;
$countMedias = 0;
$totalAlunos = 0;
$totalTurmas = 0;
$totalDisciplinas = 0;
$totalClasses = 0;

try {
    $stmt = $pdo->prepare("SELECT mt1, mt2, mt3, mfd, classe FROM notas_alunos WHERE ano_letivo = ?");
    $stmt->execute([$ano_letivo]);
    $todasNotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalRegistros = count($todasNotas);

    foreach ($todasNotas as $n) {
        $mfdCalc = calcularMFD($n['mt1'], $n['mt2'], $n['mt3'], true);
        if ($mfdCalc === null) {
            $mfdCalc = ($n['mfd'] !== null && $n['mfd'] !== '' && is_numeric($n['mfd']))
                ? (float)$n['mfd'] : null;
        }
        if ($mfdCalc === null) { $totalSemNota++; continue; }

        $lim = getLimitesPorClasse($n['classe']);
        if ($mfdCalc >= $lim['aprovado']) $totalAprovados++;
        elseif ($mfdCalc >= $lim['recuperacao']) $totalRecuperacao++;
        elseif ($mfdCalc > 0) $totalReprovados++;
        else $totalSemNota++;

        $somaMedias += $mfdCalc;
        $countMedias++;
    }

    // Contadores
    $stmt = $pdo->query("SELECT COUNT(*) FROM alunos WHERE status = 'ativo'");
    $totalAlunos = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(DISTINCT TURMA) FROM alunos WHERE status = 'ativo' AND TURMA IS NOT NULL AND TURMA != ''");
    $totalTurmas = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(DISTINCT Classe) FROM alunos WHERE status = 'ativo' AND Classe IS NOT NULL AND Classe != ''");
    $totalClasses = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(DISTINCT disciplina) FROM notas_alunos WHERE disciplina IS NOT NULL AND disciplina != ''");
    $totalDisciplinas = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
}

$mediaGeral = $countMedias > 0 ? round($somaMedias / $countMedias, 1) : 0;
$percAprov  = $totalRegistros > 0 ? round(($totalAprovados / $totalRegistros) * 100, 1) : 0;
$percReprov = $totalRegistros > 0 ? round(($totalReprovados / $totalRegistros) * 100, 1) : 0;
$percRecup  = $totalRegistros > 0 ? round(($totalRecuperacao / $totalRegistros) * 100, 1) : 0;
$percSemNota= $totalRegistros > 0 ? round(($totalSemNota / $totalRegistros) * 100, 1) : 0;

// ===== ÚLTIMOS LANÇAMENTOS =====
$ultimosLancamentos = [];
try {
    $stmt = $pdo->prepare("
        SELECT nome_aluno, disciplina, turma, classe, mfd, data_atualizacao
        FROM notas_alunos
        WHERE ano_letivo = ? AND data_atualizacao IS NOT NULL
        ORDER BY data_atualizacao DESC
        LIMIT 8
    ");
    $stmt->execute([$ano_letivo]);
    $ultimosLancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ===== TOP 5 ALUNOS =====
$topAlunos = [];
try {
    $stmt = $pdo->prepare("
        SELECT nome_aluno, turma, classe, 
               AVG(mfd) as media,
               COUNT(*) as total
        FROM notas_alunos
        WHERE ano_letivo = ? AND mfd IS NOT NULL AND mfd > 0
        GROUP BY id_aluno, nome_aluno, turma, classe
        ORDER BY media DESC
        LIMIT 5
    ");
    $stmt->execute([$ano_letivo]);
    $topAlunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ===== ESTATÍSTICAS POR TURMA =====
$statsTurmas = [];
try {
    $stmt = $pdo->prepare("
        SELECT turma, classe,
               COUNT(*) as total,
               AVG(mfd) as media
        FROM notas_alunos
        WHERE ano_letivo = ? AND mfd IS NOT NULL AND mfd > 0
        GROUP BY turma, classe
        ORDER BY turma
    ");
    $stmt->execute([$ano_letivo]);
    $statsTurmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ===== ÚLTIMAS 6 AULAS/NOTAS POR DATA =====
$notasPorData = [];
try {
    $stmt = $pdo->prepare("
        SELECT DATE(data_atualizacao) as data, COUNT(*) as total
        FROM notas_alunos
        WHERE ano_letivo = ? AND data_atualizacao IS NOT NULL
        GROUP BY DATE(data_atualizacao)
        ORDER BY data DESC
        LIMIT 7
    ");
    $stmt->execute([$ano_letivo]);
    $notasPorData = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {}

include $root_path . '/modules/escola/includes/header_escola.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    .dash-header { background: linear-gradient(135deg, #1a2332, #2c3e50); color: #fff; padding: 22px 28px; border-radius: 14px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
    .dash-header h1 { font-size: 24px; margin: 0; display: flex; align-items: center; gap: 10px; }
    .dash-header h1 i { color: #c9a84c; }
    .dash-header .subtitle { font-size: 13px; color: #cbd5e1; margin-top: 5px; }
    .dash-header .actions { display: flex; gap: 8px; flex-wrap: wrap; }

    .btn { padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; }
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; transform: translateY(-2px); }
    .btn-info { background: #3498db; color: #fff; }
    .btn-info:hover { background: #2980b9; }
    .btn-success { background: #2ecc71; color: #fff; }
    .btn-success:hover { background: #27ae60; }
    .btn-warning { background: #f39c12; color: #fff; }
    .btn-warning:hover { background: #d68910; }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }

    /* ============ KPIs ============ */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 22px; }
    .kpi-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #c9a84c; transition: all 0.3s; position: relative; overflow: hidden; }
    .kpi-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .kpi-card .icon-bg { position: absolute; right: -10px; bottom: -10px; font-size: 80px; opacity: 0.06; }
    .kpi-card .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; margin-bottom: 8px; }
    .kpi-card .value { font-size: 30px; font-weight: 900; color: #1a2332; line-height: 1; }
    .kpi-card .extra { font-size: 11px; color: #94a3b8; margin-top: 5px; }
    .kpi-card.alunos { border-left-color: #3498db; }
    .kpi-card.aprovados { border-left-color: #2ecc71; }
    .kpi-card.reprovados { border-left-color: #e74c3c; }
    .kpi-card.recuperacao { border-left-color: #f39c12; }
    .kpi-card.media { border-left-color: #9b59b6; }
    .kpi-card.registros { border-left-color: #c9a84c; }

    /* ============ SEÇÕES ============ */
    .section { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .section-title { font-size: 15px; font-weight: 700; color: #1a2332; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #c9a84c; display: flex; align-items: center; gap: 8px; }
    .section-title i { color: #c9a84c; }

    /* ============ GRID 2 COLUNAS ============ */
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }

    /* ============ GRÁFICOS ============ */
    .chart-container { position: relative; height: 280px; }

    /* ============ LISTAS ============ */
    .lista { display: flex; flex-direction: column; gap: 8px; }
    .lista-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #f8fafc; border-radius: 8px; transition: all 0.2s; }
    .lista-item:hover { background: #eef2f7; transform: translateX(3px); }

    .rank-badge { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 800; font-size: 13px; flex-shrink: 0; }
    .rank-1 { background: #fef3c7; color: #92400e; }
    .rank-2 { background: #e5e7eb; color: #4b5563; }
    .rank-3 { background: #fed7aa; color: #9a3412; }
    .rank-outros { background: #dbeafe; color: #1e40af; }

    .lista-item .info { flex: 1; }
    .lista-item .info .nome { font-weight: 700; color: #1a2332; font-size: 13px; }
    .lista-item .info .meta { font-size: 11px; color: #94a3b8; margin-top: 2px; }
    .lista-item .badge-media { font-weight: 800; color: #1e40af; padding: 4px 12px; background: #dbeafe; border-radius: 6px; font-size: 13px; }

    /* ============ TABELA ============ */
    .tab-turmas { width: 100%; border-collapse: collapse; font-size: 13px; }
    .tab-turmas thead th { background: #f8fafc; padding: 10px 12px; text-align: left; font-weight: 700; color: #4a5568; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
    .tab-turmas tbody td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
    .tab-turmas tbody tr:hover { background: #fafbfc; }
    .tab-turmas .media-badge { display: inline-block; padding: 3px 12px; border-radius: 6px; font-weight: 700; font-size: 12px; }
    .media-boa { background: #d4edda; color: #155724; }
    .media-media { background: #fff3cd; color: #856404; }
    .media-ruim { background: #f8d7da; color: #721c24; }

    /* ============ ÚLTIMOS LANÇAMENTOS ============ */
    .lancamento { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #f8fafc; border-radius: 8px; border-left: 3px solid #c9a84c; }
    .lancamento + .lancamento { margin-top: 8px; }
    .lancamento .info .nome { font-weight: 700; color: #1a2332; font-size: 13px; }
    .lancamento .info .meta { font-size: 11px; color: #94a3b8; margin-top: 2px; }
    .lancamento .data { font-size: 11px; color: #64748b; text-align: right; }
    .lancamento .data .hora { font-weight: 700; color: #1a2332; font-size: 13px; }

    .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; }
    .empty-state .icon { font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5; }
    .empty-state p { margin: 4px 0; font-size: 13px; }

    /* ============ AÇÕES RÁPIDAS ============ */
    .acoes-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .acao-card { display: flex; align-items: center; gap: 12px; padding: 16px 18px; background: #f8fafc; border-radius: 10px; text-decoration: none; color: #1a2332; transition: all 0.3s; border: 1px solid #e2e8f0; }
    .acao-card:hover { background: #1a2332; color: #fff; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
    .acao-card:hover .acao-icon { background: #c9a84c; color: #1a2332; }
    .acao-card .acao-icon { width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px; background: #dbeafe; color: #1e40af; font-size: 18px; transition: all 0.3s; }
    .acao-card .acao-info .titulo { font-weight: 700; font-size: 13px; }
    .acao-card .acao-info .desc { font-size: 11px; opacity: 0.75; margin-top: 2px; }

    .filtro-ano { display: inline-flex; align-items: center; gap: 8px; }
    .filtro-ano input { padding: 7px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; }
</style>

<!-- ============ HEADER ============ -->
<div class="dash-header">
    <div>
        <h1><i class="fas fa-chart-pie"></i> Dashboard de Notas</h1>
        <div class="subtitle">
            📅 Ano Letivo: <strong><?= htmlspecialchars($ano_letivo) ?></strong> • 
            🎓 <?= htmlspecialchars($escola_nome) ?>
        </div>
    </div>
    <div class="actions">
        <form method="GET" class="filtro-ano">
            <input type="text" name="ano_letivo" value="<?= htmlspecialchars($ano_letivo) ?>" placeholder="Ano letivo">
            <button type="submit" class="btn btn-primary"><i class="fas fa-sync"></i> Atualizar</button>
        </form>
    </div>
</div>

<!-- ============ KPIs ============ -->
<div class="kpi-grid">
    <div class="kpi-card alunos">
        <div class="icon-bg">👨‍🎓</div>
        <div class="label">Total de Alunos</div>
        <div class="value"><?= number_format($totalAlunos, 0, ',', '.') ?></div>
        <div class="extra"><?= $totalTurmas ?> turmas • <?= $totalClasses ?> classes</div>
    </div>
    <div class="kpi-card registros">
        <div class="icon-bg">📊</div>
        <div class="label">Notas Lançadas</div>
        <div class="value"><?= number_format($totalRegistros, 0, ',', '.') ?></div>
        <div class="extra"><?= $totalDisciplinas ?> disciplinas</div>
    </div>
    <div class="kpi-card aprovados">
        <div class="icon-bg">🎓</div>
        <div class="label">Aprovados</div>
        <div class="value" style="color:#2ecc71"><?= number_format($totalAprovados, 0, ',', '.') ?></div>
        <div class="extra"><?= $percAprov ?>% do total</div>
    </div>
    <div class="kpi-card recuperacao">
        <div class="icon-bg">📖</div>
        <div class="label">Recuperação</div>
        <div class="value" style="color:#f39c12"><?= number_format($totalRecuperacao, 0, ',', '.') ?></div>
        <div class="extra"><?= $percRecup ?>% do total</div>
    </div>
    <div class="kpi-card reprovados">
        <div class="icon-bg">❌</div>
        <div class="label">Reprovados</div>
        <div class="value" style="color:#e74c3c"><?= number_format($totalReprovados, 0, ',', '.') ?></div>
        <div class="extra"><?= $percReprov ?>% do total</div>
    </div>
    <div class="kpi-card media">
        <div class="icon-bg">📈</div>
        <div class="label">Média Geral</div>
        <div class="value"><?= number_format($mediaGeral, 1) ?></div>
        <div class="extra"><?= $countMedias ?> notas válidas</div>
    </div>
</div>

<!-- ============ GRÁFICOS ============ -->
<div class="grid-2">
    <div class="section">
        <div class="section-title"><i class="fas fa-chart-pie"></i> Situação Geral dos Alunos</div>
        <div class="chart-container"><canvas id="chartSituacao"></canvas></div>
    </div>
    <div class="section">
        <div class="section-title"><i class="fas fa-chart-line"></i> Evolução dos Lançamentos</div>
        <div class="chart-container"><canvas id="chartEvolucao"></canvas></div>
    </div>
</div>

<!-- ============ TOP ALUNOS + ÚLTIMOS LANÇAMENTOS ============ -->
<div class="grid-2">
    <div class="section">
        <div class="section-title"><i class="fas fa-trophy"></i> Top 5 Melhores Alunos</div>
        <?php if (count($topAlunos) > 0): ?>
        <div class="lista">
            <?php foreach ($topAlunos as $idx => $aluno): ?>
            <div class="lista-item">
                <div class="rank-badge rank-<?= ($idx+1) > 3 ? 'outros' : ($idx+1) ?>"><?= $idx + 1 ?>º</div>
                <div class="info">
                    <div class="nome"><?= htmlspecialchars($aluno['nome_aluno']) ?></div>
                    <div class="meta">👥 Turma <?= htmlspecialchars($aluno['turma']) ?> • 📚 <?= htmlspecialchars($aluno['classe']) ?>ª Classe • <?= $aluno['total'] ?> nota(s)</div>
                </div>
                <div class="badge-media"><?= number_format((float)$aluno['media'], 1) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon">🏆</div>
            <p>Nenhum aluno avaliado</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <div class="section-title"><i class="fas fa-clock"></i> Últimos Lançamentos</div>
        <?php if (count($ultimosLancamentos) > 0): ?>
        <div>
            <?php foreach ($ultimosLancamentos as $l): 
                $data = $l['data_atualizacao'] ? date('d/m', strtotime($l['data_atualizacao'])) : '-';
                $hora = $l['data_atualizacao'] ? date('H:i', strtotime($l['data_atualizacao'])) : '';
                $mfd = $l['mfd'] !== null ? number_format((float)$l['mfd'], 1) : '—';
            ?>
            <div class="lancamento">
                <div class="info">
                    <div class="nome"><?= htmlspecialchars($l['nome_aluno']) ?></div>
                    <div class="meta"><?= htmlspecialchars($l['disciplina']) ?> • Turma <?= htmlspecialchars($l['turma']) ?> • MFD: <strong><?= $mfd ?></strong></div>
                </div>
                <div class="data">
                    <div class="hora"><?= $data ?></div>
                    <div><?= $hora ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon">📝</div>
            <p>Nenhum lançamento recente</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ ESTATÍSTICAS POR TURMA ============ -->
<?php if (count($statsTurmas) > 0): ?>
<div class="section">
    <div class="section-title"><i class="fas fa-users"></i> Desempenho por Turma</div>
    <table class="tab-turmas">
        <thead>
            <tr>
                <th>Turma</th>
                <th>Classe</th>
                <th>Registros</th>
                <th>Média</th>
                <th>Desempenho</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Ordenar por média desc
            usort($statsTurmas, function($a, $b) { return ($b['media'] ?? 0) <=> ($a['media'] ?? 0); });
            foreach ($statsTurmas as $t): 
                $media = round((float)$t['media'], 1);
                $lim = getLimitesPorClasse($t['classe']);
                if ($media >= $lim['aprovado']) { $classeMedia = 'media-boa'; $rotulo = 'Bom'; }
                elseif ($media >= $lim['recuperacao']) { $classeMedia = 'media-media'; $rotulo = 'Razoável'; }
                else { $classeMedia = 'media-ruim'; $rotulo = 'Crítico'; }
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($t['turma']) ?></strong></td>
                <td><?= htmlspecialchars($t['classe']) ?>ª</td>
                <td><?= $t['total'] ?></td>
                <td><span class="media-badge <?= $classeMedia ?>"><?= $media ?></span></td>
                <td><?= $rotulo ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ============ AÇÕES RÁPIDAS ============ -->
<div class="section">
    <div class="section-title"><i class="fas fa-bolt"></i> Ações Rápidas</div>
    <div class="acoes-grid">
        <a href="index.php?ano_letivo=<?= urlencode($ano_letivo) ?>" class="acao-card">
            <div class="acao-icon"><i class="fas fa-list"></i></div>
            <div class="acao-info">
                <div class="titulo">Ver Todas as Notas</div>
                <div class="desc">Listagem completa</div>
            </div>
        </a>
        <a href="relatorio_nota.php?ano_letivo=<?= urlencode($ano_letivo) ?>" class="acao-card">
            <div class="acao-icon"><i class="fas fa-chart-bar"></i></div>
            <div class="acao-info">
                <div class="titulo">Relatório Completo</div>
                <div class="desc">Análise detalhada</div>
            </div>
        </a>
        <a href="<?= SITE_URL ?>notas.php" class="acao-card">
            <div class="acao-icon"><i class="fas fa-edit"></i></div>
            <div class="acao-info">
                <div class="titulo">Lançar Notas</div>
                <div class="desc">Sistema de lançamento</div>
            </div>
        </a>
        <a href="<?= SITE_URL ?>pautas_trimestrais.php" class="acao-card">
            <div class="acao-icon"><i class="fas fa-file-alt"></i></div>
            <div class="acao-info">
                <div class="titulo">Pautas Trimestrais</div>
                <div class="desc">Documentos oficiais</div>
            </div>
        </a>
    </div>
</div>

<!-- ============ SCRIPTS ============ -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // 1. Situação Geral (rosca)
    new Chart(document.getElementById('chartSituacao'), {
        type: 'doughnut',
        data: {
            labels: ['Aprovados', 'Recuperação', 'Reprovados', 'Sem nota'],
            datasets: [{
                data: [<?= $totalAprovados ?>, <?= $totalRecuperacao ?>, <?= $totalReprovados ?>, <?= $totalSemNota ?>],
                backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c', '#94a3b8'],
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 15, usePointStyle: true } },
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
            cutout: '60%'
        }
    });

    // 2. Evolução dos lançamentos (linha)
    var datasLabels = <?= json_encode(array_map(function($n) { return date('d/m', strtotime($n['data'])); }, $notasPorData)) ?>;
    var datasValores = <?= json_encode(array_map(function($n) { return (int)$n['total']; }, $notasPorData)) ?>;

    new Chart(document.getElementById('chartEvolucao'), {
        type: 'line',
        data: {
            labels: datasLabels.length > 0 ? datasLabels : ['Sem dados'],
            datasets: [{
                label: 'Notas lançadas',
                data: datasValores.length > 0 ? datasValores : [0],
                borderColor: '#c9a84c',
                backgroundColor: 'rgba(201,168,76,0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#c9a84c',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } }, grid: { color: '#f1f5f9' } },
                x: { ticks: { font: { size: 11 } }, grid: { display: false } }
            }
        }
    });
});
</script>

<?php include $root_path . '/modules/escola/includes/footer_escola.php'; ?>