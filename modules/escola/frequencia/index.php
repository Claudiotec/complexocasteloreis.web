<?php
// ============================================
// modules/escola/frequencia/index.php - CORRIGIDO (SEM matricula)
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

// ============================================
// 1. RECEBE FILTROS
// ============================================
$turma_id = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : null;
$data_filtro = isset($_GET['data']) ? $_GET['data'] : '';
$mes_filtro = isset($_GET['mes']) ? (int)$_GET['mes'] : null;
$ano_filtro = isset($_GET['ano']) ? (int)$_GET['ano'] : null;

// ============================================
// 2. BUSCAR TURMAS
// ============================================
$turmas = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM turmas WHERE status = 'ativa' OR status = 1 ORDER BY nome");
    $turmas = $stmt->fetchAll();
} catch (Exception $e) {
    $turmas = [];
}

// ============================================
// 3. BUSCAR FREQUÊNCIA COM LEFT JOIN
// ============================================
$frequencias = [];
$totalPresentes = 0;
$totalAusentes = 0;
$totalJustificados = 0;
$totalAtrasados = 0;
$totalRegistros = 0;

try {
    // ============================================
    // VERIFICAR TOTAL DE REGISTROS
    // ============================================
    $checkSql = "SELECT COUNT(*) as total FROM frequencia";
    $checkStmt = $pdo->query($checkSql);
    $totalRegistros = $checkStmt->fetchColumn();
    
    // ============================================
    // CONSULTA COM LEFT JOIN (SEM matricula)
    // ============================================
    $sql = "
        SELECT 
            f.*,
            a.nome AS aluno_nome,
            t.nome AS turma_nome
        FROM frequencia f
        LEFT JOIN alunos a ON f.aluno_id = a.id
        LEFT JOIN turmas t ON f.turma_id = t.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($data_filtro)) {
        $sql .= " AND f.data = ?";
        $params[] = $data_filtro;
    }
    
    if (!empty($mes_filtro) && !empty($ano_filtro)) {
        $sql .= " AND MONTH(f.data) = ? AND YEAR(f.data) = ?";
        $params[] = $mes_filtro;
        $params[] = $ano_filtro;
    }
    
    if (!empty($turma_id)) {
        $sql .= " AND f.turma_id = ?";
        $params[] = $turma_id;
    }
    
    $sql .= " ORDER BY f.data DESC, f.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $frequencias = $stmt->fetchAll();
    
    // ============================================
    // CORRIGIR NOMES VAZIOS
    // ============================================
    foreach ($frequencias as &$f) {
        if (empty($f['aluno_nome'])) {
            $f['aluno_nome'] = 'Aluno #' . $f['aluno_id'] . ' (não encontrado)';
        }
        if (empty($f['turma_nome'])) {
            $f['turma_nome'] = 'Turma #' . $f['turma_id'] . ' (não encontrada)';
        }
    }
    unset($f);
    
    // ============================================
    // CALCULAR TOTAIS
    // ============================================
    $stmtTotal = $pdo->query("
        SELECT 
            COUNT(CASE WHEN status = 'presente' THEN 1 END) as presentes,
            COUNT(CASE WHEN status = 'ausente' THEN 1 END) as ausentes,
            COUNT(CASE WHEN status = 'justificado' THEN 1 END) as justificados,
            COUNT(CASE WHEN status = 'atrasado' THEN 1 END) as atrasados
        FROM frequencia
    ");
    $totaisGerais = $stmtTotal->fetch();
    
    $totalPresentes = $totaisGerais['presentes'] ?? 0;
    $totalAusentes = $totaisGerais['ausentes'] ?? 0;
    $totalJustificados = $totaisGerais['justificados'] ?? 0;
    $totalAtrasados = $totaisGerais['atrasados'] ?? 0;
    
} catch (Exception $e) {
    die('<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>');
}

// ============================================
// 4. INCLUIR HEADER
// ============================================
include '../includes/header_escola.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    .page-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    .page-header .subtitle {
        color: #94a3b8;
        font-size: 14px;
        margin: 2px 0 0;
    }
    
    .btn {
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
    }
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    .btn-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201,168,76,0.3);
    }
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    .btn-success:hover {
        background: #27ae60;
    }
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    .btn-danger:hover {
        background: #c0392b;
    }
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    .btn-warning:hover {
        background: #d68910;
    }
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    .btn-info:hover {
        background: #2980b9;
    }
    .btn-sm {
        padding: 4px 12px;
        font-size: 11px;
        border-radius: 6px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .stat-card {
        background: white;
        padding: 18px 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        border-left: 4px solid #c9a84c;
        transition: all 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    .stat-card .number {
        font-size: 26px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    .stat-card .label {
        font-size: 12px;
        color: #94a3b8;
        margin: 3px 0 0;
    }
    .stat-card .icon {
        font-size: 24px;
        display: block;
        margin-bottom: 5px;
    }
    .stat-card.presentes { border-left-color: #2ecc71; }
    .stat-card.ausentes { border-left-color: #e74c3c; }
    .stat-card.justificados { border-left-color: #f39c12; }
    .stat-card.atrasados { border-left-color: #3498db; }
    
    .filtros {
        background: white;
        padding: 18px 20px;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    .filtros .form-group {
        flex: 1;
        min-width: 150px;
    }
    .filtros .form-group label {
        display: block;
        font-weight: 600;
        font-size: 12px;
        color: #4a5568;
        margin-bottom: 4px;
    }
    .filtros .form-group select,
    .filtros .form-group input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        background: white;
    }
    .filtros .form-group select:focus,
    .filtros .form-group input:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    .filtros .btn-group {
        display: flex;
        gap: 8px;
        flex: 0 0 auto;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
    }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        min-width: 700px;
    }
    .table th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }
    .table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .table tr:hover {
        background: #fafbfc;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    .status-presente { background: #d1fae5; color: #065f46; }
    .status-ausente { background: #fee2e2; color: #991b1b; }
    .status-justificado { background: #fef3c7; color: #92400e; }
    .status-atrasado { background: #dbeafe; color: #1e40af; }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #94a3b8;
    }
    .empty-state .icon {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
    }
    .empty-state h3 {
        font-size: 18px;
        color: #4a5568;
        margin: 0 0 5px;
    }
    
    .table-actions {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    .acoes-rapidas {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 20px;
        justify-content: center;
    }
    
    .debug-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        font-size: 13px;
    }
    .debug-box code {
        background: #e9ecef;
        padding: 2px 6px;
        border-radius: 4px;
    }
    .debug-box .badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-info { background: #dbeafe; color: #1e40af; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    
    .aluno-nao-encontrado {
        color: #e74c3c;
        font-style: italic;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .filtros {
            flex-direction: column;
        }
        .filtros .form-group {
            min-width: 100%;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .stat-card {
            padding: 14px 16px;
        }
        .stat-card .number {
            font-size: 22px;
        }
        .table {
            font-size: 12px;
            min-width: 600px;
        }
        .table th,
        .table td {
            padding: 8px 10px;
        }
    }
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ===== PAGE HEADER ===== -->
<div class="page-header">
    <div>
        <h1>✅ Frequência</h1>
        <p class="subtitle">Registro e acompanhamento de presenças</p>
    </div>
    <a href="../index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- ===== DEBUG BOX ===== -->
<div class="debug-box">
    <strong>🔍 Informações:</strong><br>
    Total de registros: <span class="badge badge-info"><?= $totalRegistros ?></span><br>
    Registros encontrados: <span class="badge <?= count($frequencias) > 0 ? 'badge-success' : 'badge-danger' ?>">
        <?= count($frequencias) ?>
    </span><br>
    Filtros: 
    Data=<?= $data_filtro ?: 'Todos' ?>, 
    Mês=<?= $mes_filtro ?: 'Todos' ?>, 
    Ano=<?= $ano_filtro ?: 'Todos' ?>,
    Turma=<?= $turma_id ?: 'Todas' ?>
</div>

<!-- ===== STATS ===== -->
<div class="stats-grid">
    <div class="stat-card presentes">
        <span class="icon">✅</span>
        <div class="number"><?= $totalPresentes ?></div>
        <div class="label">Presentes</div>
    </div>
    <div class="stat-card ausentes">
        <span class="icon">❌</span>
        <div class="number"><?= $totalAusentes ?></div>
        <div class="label">Ausentes</div>
    </div>
    <div class="stat-card justificados">
        <span class="icon">📋</span>
        <div class="number"><?= $totalJustificados ?></div>
        <div class="label">Justificados</div>
    </div>
    <div class="stat-card atrasados">
        <span class="icon">⏰</span>
        <div class="number"><?= $totalAtrasados ?></div>
        <div class="label">Atrasados</div>
    </div>
</div>

<!-- ===== FILTROS ===== -->
<div class="filtros">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%; align-items: flex-end;">
        <div class="form-group">
            <label>Turma</label>
            <select name="turma_id">
                <option value="">Todas as turmas</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($turma_id == $t['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Data</label>
            <input type="date" name="data" value="<?= htmlspecialchars($data_filtro) ?>">
        </div>
        <div class="form-group">
            <label>Mês</label>
            <select name="mes">
                <option value="">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($mes_filtro == $m) ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 1, 2000)) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Ano</label>
            <select name="ano">
                <option value="">Todos</option>
                <?php for ($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
                    <option value="<?= $a ?>" <?= ($ano_filtro == $a) ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="index.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<!-- ===== TABELA ===== -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Data</th>
                <th>ID Aluno</th>
                <th>Aluno</th>
                <th>Turma</th>
                <th>Status</th>
                <th>Observação</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($frequencias) > 0): ?>
                <?php foreach ($frequencias as $f): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($f['data'])) ?></td>
                        <td><span class="badge badge-info">#<?= $f['aluno_id'] ?></span></td>
                        <td>
                            <?php if (strpos($f['aluno_nome'], 'não encontrado') !== false): ?>
                                <span class="aluno-nao-encontrado">⚠️ <?= htmlspecialchars($f['aluno_nome']) ?></span>
                            <?php else: ?>
                                <strong><?= htmlspecialchars($f['aluno_nome']) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($f['turma_nome'] ?? 'N/A') ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($f['status'] ?? 'presente') ?>">
                                <?= ucfirst($f['status'] ?? 'Presente') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(substr($f['observacao'] ?? '', 0, 30)) ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-warning">✏️</a>
                                <a href="delete.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?')">🗑️</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhum registro de frequência</h3>
                            <p>
                                <?php if ($totalRegistros > 0): ?>
                                    Existem <?= $totalRegistros ?> registros no banco, mas nenhum corresponde aos filtros.
                                    <br>Clique em "Limpar" para ver todos.
                                <?php else: ?>
                                    Clique em "Marcar Presença" para começar.
                                <?php endif; ?>
                            </p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ===== AÇÕES RÁPIDAS ===== -->
<div class="acoes-rapidas">
    <a href="marcar.php" class="btn btn-success">📌 Marcar Presença</a>
    <a href="relatorio.php" class="btn btn-info">📈 Relatório de Frequência</a>
</div>

<?php include '../includes/footer_escola.php'; ?>