<?php
// ============================================
// modules/escola/notas/index.php - Notas dos Alunos
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

// ===== GARANTIR CONEXÃO =====
if (!isset($pdo) || !$pdo) {
    $pdo = conectarBanco();
}

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

    if ($mfd >= $lim['aprovado']) {
        return ['label' => 'Aprovado', 'class' => 'status-aprovado'];
    }
    if ($mfd >= $lim['recuperacao']) {
        return ['label' => 'Recuperação', 'class' => 'status-recuperacao'];
    }
    if ($mfd > 0) {
        return ['label' => 'Reprovado', 'class' => 'status-reprovado'];
    }
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
$ano_letivo   = $_GET['ano_letivo']   ?? (date('Y') . '/' . (date('Y') + 1));
$classe       = $_GET['classe']       ?? '';
$turma        = $_GET['turma']        ?? '';
$disciplina   = $_GET['disciplina']   ?? '';
$busca_aluno  = trim($_GET['busca_aluno'] ?? '');

// ===== BUSCAR NOTAS =====
$notas = [];
try {
    $sql = "SELECT 
                n.id,
                n.id_aluno,
                n.nome_aluno,
                n.disciplina,
                n.turma,
                n.classe,
                n.ano_letivo,
                n.mac_t1, n.npt_t1, n.mt1,
                n.mac_t2, n.npt_t2, n.mt2,
                n.mac_t3, n.npt_t3, n.mt3,
                n.neo, n.en, n.mec, n.mfed,
                n.mfd, n.classificacao,
                n.data_lancamento, n.data_atualizacao,
                a.Sexo AS sexo, a.Idade AS idade
            FROM notas_alunos n
            LEFT JOIN alunos a ON n.id_aluno = a.id
            WHERE n.ano_letivo = ?";
    $params = [$ano_letivo];

    if ($classe) {
        $sql .= " AND n.classe = ?";
        $params[] = $classe;
    }
    if ($turma) {
        $sql .= " AND n.turma = ?";
        $params[] = $turma;
    }
    if ($disciplina) {
        $sql .= " AND n.disciplina = ?";
        $params[] = $disciplina;
    }
    if ($busca_aluno !== '') {
        $sql .= " AND n.nome_aluno LIKE ?";
        $params[] = '%' . $busca_aluno . '%';
    }

    $sql .= " ORDER BY n.nome_aluno ASC, n.disciplina ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar notas: " . $e->getMessage());
}

// ===== ESTATÍSTICAS =====
$totalNotas       = 0;
$totalAprovados   = 0;
$totalReprovados  = 0;
$totalRecuperacao = 0;

try {
    $stmt = $pdo->prepare("SELECT mt1, mt2, mt3, mfd, classe FROM notas_alunos WHERE ano_letivo = ?");
    $stmt->execute([$ano_letivo]);
    $todasNotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalNotas = count($todasNotas);

    foreach ($todasNotas as $n) {
        $mfdCalc = calcularMFD($n['mt1'], $n['mt2'], $n['mt3'], true);
        if ($mfdCalc === null) {
            $mfdCalc = ($n['mfd'] !== null && $n['mfd'] !== '' && is_numeric($n['mfd']))
                ? (float)$n['mfd'] : null;
        }
        if ($mfdCalc === null) continue;

        $lim = getLimitesPorClasse($n['classe']);
        if ($mfdCalc >= $lim['aprovado']) {
            $totalAprovados++;
        } elseif ($mfdCalc >= $lim['recuperacao']) {
            $totalRecuperacao++;
        } elseif ($mfdCalc > 0) {
            $totalReprovados++;
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
}

// ===== BUSCAR FILTROS DISPONÍVEIS =====
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
} catch (Exception $e) {
    error_log("Erro ao buscar filtros: " . $e->getMessage());
}

// ===== INCLUIR HEADER =====
include '../includes/header_escola.php';
?>

<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
    .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
    .page-header .subtitle { color: #94a3b8; font-size: 14px; margin: 2px 0 0; }
    .btn { padding: 8px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; }
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(201,168,76,0.3); }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-success { background: #2ecc71; color: #fff; }
    .btn-success:hover { background: #27ae60; }
    .btn-danger { background: #e74c3c; color: #fff; }
    .btn-danger:hover { background: #c0392b; }
    .btn-warning { background: #f39c12; color: #fff; }
    .btn-warning:hover { background: #d68910; }
    .btn-info { background: #3498db; color: #fff; }
    .btn-info:hover { background: #2980b9; }
    .btn-sm { padding: 4px 12px; font-size: 11px; border-radius: 6px; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; padding: 18px 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; border-left: 4px solid #c9a84c; transition: all 0.3s; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .stat-card .number { font-size: 26px; font-weight: 700; color: #1a2332; margin: 0; }
    .stat-card .label { font-size: 12px; color: #94a3b8; margin: 3px 0 0; }
    .stat-card .icon { font-size: 24px; display: block; margin-bottom: 5px; }
    .stat-card.aprovados { border-left-color: #2ecc71; }
    .stat-card.reprovados { border-left-color: #e74c3c; }
    .stat-card.recuperacao { border-left-color: #f39c12; }
    .stat-card.total { border-left-color: #3498db; }
    .filtros { background: white; padding: 18px 20px; border-radius: 12px; border: 1px solid #eef2f7; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
    .filtros .form-group { flex: 1; min-width: 140px; }
    .filtros .form-group label { display: block; font-weight: 600; font-size: 12px; color: #4a5568; margin-bottom: 4px; }
    .filtros .form-group select, .filtros .form-group input { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; background: white; }
    .filtros .form-group select:focus, .filtros .form-group input:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; }
    .table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1100px; }
    .table th { background: #f8fafc; padding: 10px 10px; text-align: left; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; white-space: nowrap; font-size: 11px; }
    .table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 12px; }
    .table tr:hover { background: #fafbfc; }
    .table .nota { font-weight: 600; text-align: center; }
    .table .mfd-destaque { background: #d4edda; color: #155724; font-weight: 700; border-radius: 4px; padding: 3px 6px; }
    .table .mfd-amarelo { background: #fff3cd; color: #856404; font-weight: 700; border-radius: 4px; padding: 3px 6px; }
    .table .mfd-vermelho { background: #f8d7da; color: #721c24; font-weight: 700; border-radius: 4px; padding: 3px 6px; }
    .status-badge { display: inline-block; padding: 3px 14px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .status-aprovado { background: #d1fae5; color: #065f46; }
    .status-reprovado { background: #fee2e2; color: #991b1b; }
    .status-recuperacao { background: #fef3c7; color: #92400e; }
    .status-dispensado { background: #f1f5f9; color: #4a5568; }
    .empty-state { text-align: center; padding: 50px 20px; color: #94a3b8; }
    .empty-state .icon { font-size: 48px; display: block; margin-bottom: 15px; }
    .empty-state h3 { font-size: 18px; color: #4a5568; margin: 0 0 5px; }
    .table-actions { display: flex; gap: 4px; flex-wrap: wrap; }
    .acoes-rapidas { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; justify-content: center; }
    .info-ano { background: #f5edd6; padding: 8px 16px; border-radius: 8px; font-size: 13px; color: #1a2332; border-left: 4px solid #c9a84c; margin-bottom: 15px; display: inline-block; }
    .escala-info { background: #e8f4fc; padding: 10px 16px; border-radius: 8px; font-size: 12px; color: #1a2332; border-left: 4px solid #3498db; margin-bottom: 15px; line-height: 1.7; }
    .escala-info strong { color: #1e40af; }
    .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; font-weight: 500; }
    .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #2ecc71; }
    .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #e74c3c; }

    /* MODAL */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(3px); }
    .modal-overlay.active { display: flex; animation: fadeIn 0.2s; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .modal-box { background: #fff; border-radius: 16px; padding: 30px 35px; max-width: 520px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; animation: slideUp 0.3s ease; }
    @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .modal-icon { font-size: 48px; margin-bottom: 10px; }
    .modal-box h3 { font-size: 20px; color: #1a2332; margin: 0 0 8px; }
    .modal-box > p { font-size: 14px; color: #4a5568; margin: 0 0 15px; }
    .modal-alerta { background: #fef3c7; border-left: 4px solid #f39c12; padding: 12px 16px; border-radius: 8px; font-size: 13px; color: #78350f; text-align: left; margin-bottom: 15px; }
    .modal-alerta-forte { background: #fee2e2; border-left-color: #e74c3c; color: #7f1d1d; font-size: 14px; line-height: 1.5; }
    .modal-detalhes { background: #f8fafc; border-radius: 8px; padding: 12px 16px; font-size: 13px; text-align: left; margin-bottom: 20px; border: 1px solid #e2e8f0; }
    .modal-detalhes strong { color: #1a2332; }
    .modal-input { width: 100%; padding: 12px 16px; border: 2px solid #d1d5db; border-radius: 8px; font-size: 15px; text-align: center; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 20px; font-family: 'Courier New', monospace; }
    .modal-input:focus { outline: none; border-color: #e74c3c; box-shadow: 0 0 0 3px rgba(231,76,60,0.15); }
    .modal-input.valido { border-color: #2ecc71; background: #f0fdf4; }
    .modal-botoes { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
    .modal-botoes .btn { min-width: 140px; justify-content: center; }
    .modal-botoes .btn:disabled { opacity: 0.5; cursor: not-allowed; }

    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: stretch; }
        .filtros { flex-direction: column; }
        .filtros .form-group { min-width: 100%; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card { padding: 14px 16px; }
        .stat-card .number { font-size: 22px; }
        .table { font-size: 11px; min-width: 900px; }
        .table th, .table td { padding: 5px 6px; }
        .btn-sm { font-size: 10px; padding: 3px 8px; }
        .acoes-rapidas { flex-direction: column; align-items: stretch; }
        .acoes-rapidas .btn { justify-content: center; }
        .modal-box { padding: 22px 20px; }
    }
    @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
</style>

<div class="page-header">
    <div>
        <h1>📊 Notas dos Alunos</h1>
        <p class="subtitle">Gestão de notas e avaliações • Ano Letivo <?= htmlspecialchars($ano_letivo) ?></p>
    </div>
    <a href="<?= SITE_URL ?>modules/escola/index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'excluido'): ?>
        <div class="alert alert-success">✅ Nota excluída com sucesso!</div>
    <?php elseif ($_GET['msg'] === 'erro_excluir'): ?>
        <div class="alert alert-error">❌ Erro ao excluir a nota. Tente novamente.</div>
    <?php elseif ($_GET['msg'] === 'erro_id'): ?>
        <div class="alert alert-error">❌ ID inválido.</div>
    <?php elseif ($_GET['msg'] === 'atualizado'): ?>
        <div class="alert alert-success">✅ Nota atualizada com sucesso!</div>
    <?php endif; ?>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card aprovados">
        <span class="icon">🎓</span>
        <div class="number"><?= number_format($totalAprovados, 0, ',', '.') ?></div>
        <div class="label">Aprovados</div>
    </div>
    <div class="stat-card reprovados">
        <span class="icon">❌</span>
        <div class="number"><?= number_format($totalReprovados, 0, ',', '.') ?></div>
        <div class="label">Reprovados</div>
    </div>
    <div class="stat-card recuperacao">
        <span class="icon">📖</span>
        <div class="number"><?= number_format($totalRecuperacao, 0, ',', '.') ?></div>
        <div class="label">Recuperação</div>
    </div>
    <div class="stat-card total">
        <span class="icon">📊</span>
        <div class="number"><?= number_format($totalNotas, 0, ',', '.') ?></div>
        <div class="label">Total de Notas</div>
    </div>
</div>

<div class="escala-info">
    ⚖️ <strong>Escalas de Classificação:</strong>
    <strong>Pré a 6ª classe</strong> → escala 0-10 (Aprovado ≥ 5 | Recuperação 3-4.9 | Reprovado < 3) •
    <strong>7ª a 12ª classe</strong> → escala 0-20 (Aprovado ≥ 10 | Recuperação 5-9.9 | Reprovado < 5)
    <br>
    🧮 <strong>MFD</strong> = (MT1 + MT2 + MT3) ÷ 3
</div>

<div class="filtros">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%; align-items: flex-end;">
        <div class="form-group" style="flex: 2; min-width: 200px;">
            <label>🔍 Buscar Aluno</label>
            <input type="text" name="busca_aluno" value="<?= htmlspecialchars($busca_aluno) ?>" placeholder="Digite o nome do aluno...">
        </div>
        <div class="form-group">
            <label>📅 Ano Letivo</label>
            <input type="text" name="ano_letivo" value="<?= htmlspecialchars($ano_letivo) ?>" placeholder="Ex: 2026/2027">
        </div>
        <div class="form-group">
            <label>📚 Classe</label>
            <select name="classe">
                <option value="">Todas as classes</option>
                <?php foreach($classes as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= ($classe == $c) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c) ?>ª Classe
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>👥 Turma</label>
            <select name="turma">
                <option value="">Todas as turmas</option>
                <?php foreach($turmas as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= ($turma == $t) ? 'selected' : '' ?>>
                    Turma <?= htmlspecialchars($t) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>📖 Disciplina</label>
            <select name="disciplina">
                <option value="">Todas as disciplinas</option>
                <?php foreach($disciplinas as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= ($disciplina == $d) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex: 0 0 auto;">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="index.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<div class="info-ano">
    📅 <strong>Ano Letivo:</strong> <?= htmlspecialchars($ano_letivo) ?> • 
    📊 <strong><?= count($notas) ?></strong> registro(s) encontrado(s)
    <?php if ($busca_aluno): ?> • 🔍 Busca: "<?= htmlspecialchars($busca_aluno) ?>"<?php endif; ?>
    <?php if ($classe): ?> • 📚 Classe <?= htmlspecialchars($classe) ?><?php endif; ?>
    <?php if ($turma): ?> • 👥 Turma <?= htmlspecialchars($turma) ?><?php endif; ?>
    <?php if ($disciplina): ?> • 📖 <?= htmlspecialchars($disciplina) ?><?php endif; ?>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Aluno</th>
                <th>Disciplina</th>
                <th>Classe</th>
                <th>Turma</th>
                <th>1º Trim</th>
                <th>2º Trim</th>
                <th>3º Trim</th>
                <th>MFD</th>
                <th>Classificação</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($notas) > 0): ?>
                <?php foreach($notas as $n): 
                    $classeN = $n['classe'] ?? '';
                    $lim = getLimitesPorClasse($classeN);
                    $escala10 = isEscala10($classeN);
                    
                    $mt1 = ($n['mt1'] !== null && $n['mt1'] !== '') ? number_format((float)$n['mt1'], 1) : '-';
                    $mt2 = ($n['mt2'] !== null && $n['mt2'] !== '') ? number_format((float)$n['mt2'], 1) : '-';
                    $mt3 = ($n['mt3'] !== null && $n['mt3'] !== '') ? number_format((float)$n['mt3'], 1) : '-';
                    
                    $mfdCalc = calcularMFD($n['mt1'], $n['mt2'], $n['mt3'], true);
                    if ($mfdCalc === null) {
                        $mfdCalc = ($n['mfd'] !== null && $n['mfd'] !== '' && is_numeric($n['mfd']))
                            ? (float)$n['mfd'] : null;
                    }
                    
                    $mfd = $mfdCalc !== null ? number_format($mfdCalc, 1) : '-';
                    $mfdNum = $mfdCalc ?? 0;
                    
                    $situacao = getSituacaoPorClasse($mfdCalc, $classeN);
                    $statusClass = $situacao['class'];
                    $statusLabel = $situacao['label'];
                    
                    if ($mfdCalc === null) {
                        $corMfd = 'mfd-vermelho';
                    } elseif ($mfdNum >= $lim['aprovado']) {
                        $corMfd = 'mfd-destaque';
                    } elseif ($mfdNum >= $lim['recuperacao']) {
                        $corMfd = 'mfd-amarelo';
                    } else {
                        $corMfd = 'mfd-vermelho';
                    }
                    
                    // ===== CORRIGIDO: URL de edição aponta para o edit.php do módulo =====
                    $editUrl = 'edit.php?id=' . intval($n['id']);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($n['nome_aluno'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($n['disciplina'] ?? '-') ?></td>
                    <td>
                        <?= htmlspecialchars($classeN ?: '-') ?>
                        <?php if ($classeN): ?>
                            <span style="font-size:10px;color:#94a3b8;">(<?= $escala10 ? '0-10' : '0-20' ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($n['turma'] ?? '-') ?></td>
                    <td class="nota"><?= $mt1 ?></td>
                    <td class="nota"><?= $mt2 ?></td>
                    <td class="nota"><?= $mt3 ?></td>
                    <td class="nota"><span class="<?= $corMfd ?>"><?= $mfd ?></span></td>
                    <td>
                        <span class="status-badge <?= $statusClass ?>">
                            <?= $statusLabel ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="<?= htmlspecialchars($editUrl) ?>" 
                               class="btn btn-sm btn-warning" title="Editar nota">
                                ✏️ Editar
                            </a>
                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="confirmarExclusao(<?= $n['id'] ?>, '<?= addslashes(htmlspecialchars($n['nome_aluno'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($n['disciplina'] ?? '')) ?>')">
                                🗑️ Excluir
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhuma nota registrada para <?= htmlspecialchars($ano_letivo) ?></h3>
                            <p>
                                <?php if ($busca_aluno): ?>
                                    Nenhum aluno encontrado com o nome "<strong><?= htmlspecialchars($busca_aluno) ?></strong>".<br>
                                <?php endif; ?>
                                Selecione outro ano letivo ou ajuste os filtros.
                            </p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="acoes-rapidas">
    <a href="<?= SITE_URL ?>notas.php" class="btn btn-primary">📝 Lançar Notas</a>
    <a href="relatorio_nota.php" class="btn btn-info">📈 Relatório de Notas</a>
    <a href="pautas_trimestrais.php" class="btn btn-info">📄 Boletim / Pautas</a>
    <a href="pautas_trimestrais.php" class="btn btn-info">📋 Pautas Trimestrais</a>
</div>

<div class="modal-overlay" id="modalExclusao">
    <div class="modal-box">
        <div class="modal-icon">⚠️</div>
        <h3>Confirmar Exclusão</h3>
        <p>Tem certeza que deseja excluir esta nota?</p>

        <div id="etapa1">
            <div class="modal-alerta">
                <strong>⚠️ Atenção:</strong> Esta ação é <strong>IRREVERSÍVEL</strong>.
                A nota será permanentemente removida do sistema.
            </div>
            <div class="modal-detalhes" id="detalhesNota"></div>
            <div class="modal-botoes">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="irParaEtapa2()">Continuar →</button>
            </div>
        </div>

        <div id="etapa2" style="display:none;">
            <div class="modal-alerta modal-alerta-forte">
                🚨 <strong>ÚLTIMA CHANCE!</strong><br>
                Esta é a <strong>segunda e última confirmação</strong>. 
                Após clicar em <strong>"Excluir Definitivamente"</strong>, 
                <u>NÃO será possível recuperar</u> esta nota.
            </div>
            <p style="font-size:13px;color:#4a5568;margin:15px 0;">
                Para confirmar, digite a palavra <strong style="color:#e74c3c;">EXCLUIR</strong>:
            </p>
            <input type="text" id="confirmacaoTexto" class="modal-input" 
                   placeholder="Digite EXCLUIR para confirmar" autocomplete="off">
            <div class="modal-botoes">
                <button type="button" class="btn btn-secondary" onclick="voltarEtapa1()">← Voltar</button>
                <button type="button" class="btn btn-danger" id="btnExcluirFinal" 
                        onclick="excluirDefinitivo()" disabled>
                    🗑️ Excluir Definitivamente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var idParaExcluir = null;

function confirmarExclusao(id, nomeAluno, disciplina) {
    idParaExcluir = id;
    
    document.getElementById('detalhesNota').innerHTML = 
        '👤 <strong>Aluno:</strong> ' + nomeAluno + '<br>' +
        '📖 <strong>Disciplina:</strong> ' + disciplina;
    
    document.getElementById('etapa1').style.display = 'block';
    document.getElementById('etapa2').style.display = 'none';
    document.getElementById('confirmacaoTexto').value = '';
    document.getElementById('confirmacaoTexto').classList.remove('valido');
    document.getElementById('btnExcluirFinal').disabled = true;
    document.getElementById('btnExcluirFinal').textContent = '🗑️ Excluir Definitivamente';
    
    document.getElementById('modalExclusao').classList.add('active');
}

function fecharModal() {
    document.getElementById('modalExclusao').classList.remove('active');
    idParaExcluir = null;
}

function irParaEtapa2() {
    document.getElementById('etapa1').style.display = 'none';
    document.getElementById('etapa2').style.display = 'block';
    setTimeout(function() {
        document.getElementById('confirmacaoTexto').focus();
    }, 100);
}

function voltarEtapa1() {
    document.getElementById('etapa2').style.display = 'none';
    document.getElementById('etapa1').style.display = 'block';
    document.getElementById('confirmacaoTexto').value = '';
    document.getElementById('confirmacaoTexto').classList.remove('valido');
    document.getElementById('btnExcluirFinal').disabled = true;
}

document.getElementById('confirmacaoTexto').addEventListener('input', function() {
    var val = this.value.trim().toUpperCase();
    var btn = document.getElementById('btnExcluirFinal');
    if (val === 'EXCLUIR') {
        btn.disabled = false;
        this.classList.add('valido');
    } else {
        btn.disabled = true;
        this.classList.remove('valido');
    }
});

function excluirDefinitivo() {
    if (!idParaExcluir) return;
    
    var btn = document.getElementById('btnExcluirFinal');
    btn.disabled = true;
    btn.textContent = '⏳ Excluindo...';
    
    window.location.href = 'delete.php?id=' + idParaExcluir;
}

document.getElementById('modalExclusao').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharModal();
});
</script>

<?php include '../includes/footer_escola.php'; ?>