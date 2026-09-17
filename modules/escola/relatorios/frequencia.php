<?php
// ============================================
// modules/escola/relatorios/frequencia.php - Relatório de Frequência
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
$mes_filtro = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano_filtro = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$status_filtro = isset($_GET['status']) ? $_GET['status'] : 'todos';
$formato = isset($_GET['formato']) ? $_GET['formato'] : 'html';
$imprimir = isset($_GET['imprimir']) ? $_GET['imprimir'] : false;

// ============================================
// 2. BUSCAR TURMAS PARA FILTRO
// ============================================
$turmas = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM turmas WHERE status = 'ativa' OR status = 1 ORDER BY nome");
    $turmas = $stmt->fetchAll();
} catch (Exception $e) {
    $turmas = [];
}

// ============================================
// 3. BUSCAR DADOS DO RELATÓRIO
// ============================================
$frequencias = [];
$resumo = [
    'total' => 0,
    'presentes' => 0,
    'ausentes' => 0,
    'justificados' => 0,
    'atrasados' => 0,
    'percentual_presenca' => 0
];

// Nome da turma selecionada
$nome_turma = 'Todas as turmas';
if ($turma_id > 0) {
    foreach ($turmas as $t) {
        if ($t['id'] == $turma_id) {
            $nome_turma = $t['nome'];
            break;
        }
    }
}

try {
    $sql = "
        SELECT 
            f.id,
            f.aluno_id,
            f.turma_id,
            f.data,
            f.status,
            f.created_at,
            a.nome AS aluno_nome,
            t.nome AS turma_nome,
            DATE_FORMAT(f.data, '%d/%m/%Y') AS data_formatada,
            CASE 
                WHEN f.status = 'presente' THEN 'Presente'
                WHEN f.status = 'ausente' THEN 'Ausente'
                WHEN f.status = 'justificado' THEN 'Justificado'
                WHEN f.status = 'atrasado' THEN 'Atrasado'
                ELSE f.status
            END AS status_descricao
        FROM frequencia f
        LEFT JOIN alunos a ON f.aluno_id = a.id
        LEFT JOIN turmas t ON f.turma_id = t.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($turma_id)) {
        $sql .= " AND f.turma_id = ?";
        $params[] = $turma_id;
    }
    
    if (!empty($mes_filtro)) {
        $sql .= " AND MONTH(f.data) = ?";
        $params[] = $mes_filtro;
    }
    
    if (!empty($ano_filtro)) {
        $sql .= " AND YEAR(f.data) = ?";
        $params[] = $ano_filtro;
    }
    
    if ($status_filtro != 'todos' && !empty($status_filtro)) {
        $sql .= " AND f.status = ?";
        $params[] = $status_filtro;
    }
    
    $sql .= " ORDER BY f.data DESC, a.nome ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $frequencias = $stmt->fetchAll();
    
    // ============================================
    // 4. CALCULAR RESUMO
    // ============================================
    $resumo['total'] = count($frequencias);
    
    foreach ($frequencias as $f) {
        switch ($f['status']) {
            case 'presente':
                $resumo['presentes']++;
                break;
            case 'ausente':
                $resumo['ausentes']++;
                break;
            case 'justificado':
                $resumo['justificados']++;
                break;
            case 'atrasado':
                $resumo['atrasados']++;
                break;
        }
    }
    
    if ($resumo['total'] > 0) {
        $resumo['percentual_presenca'] = round(($resumo['presentes'] / $resumo['total']) * 100, 1);
    }
    
} catch (Exception $e) {
    die('<div class="alert alert-danger">Erro ao carregar dados: ' . $e->getMessage() . '</div>');
}

// ============================================
// 5. GERAR PDF (se solicitado)
// ============================================
if ($formato == 'pdf') {
    header('Location: gerar_pdf_frequencia.php?' . http_build_query($_GET));
    exit;
}

// ============================================
// 6. GERAR EXCEL (se solicitado)
// ============================================
if ($formato == 'excel') {
    header('Location: gerar_excel_frequencia.php?' . http_build_query($_GET));
    exit;
}

// ============================================
// 7. SE FOR IMPRESSÃO, MOSTRA APENAS O RELATÓRIO A4
// ============================================
if ($imprimir) {
    // Mostra apenas o relatório A4 sem header/footer
    include 'frequencia_imprimir.php';
    exit;
}

// ============================================
// 8. TELA NORMAL COM HEADER
// ============================================
include '../includes/header_escola.php';
?>

<style>
    .container-tela {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    
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
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    .btn-info:hover {
        background: #2980b9;
    }
    
    .botoes-acao {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        justify-content: flex-start;
    }
    
    .filtros {
        background: white;
        padding: 18px 20px;
        border-radius: 10px;
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
    
    .resumo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .resumo-card {
        background: white;
        padding: 18px 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        border-left: 4px solid #c9a84c;
    }
    .resumo-card .number {
        font-size: 28px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    .resumo-card .label {
        font-size: 12px;
        color: #94a3b8;
        margin: 3px 0 0;
    }
    .resumo-card .icon {
        font-size: 24px;
        display: block;
        margin-bottom: 5px;
    }
    .resumo-card.total { border-left-color: #6c757d; }
    .resumo-card.presentes { border-left-color: #2ecc71; }
    .resumo-card.ausentes { border-left-color: #e74c3c; }
    .resumo-card.justificados { border-left-color: #f39c12; }
    .resumo-card.atrasados { border-left-color: #3498db; }
    .resumo-card.percentual { border-left-color: #8e44ad; }
    
    .table-responsive {
        overflow-x: auto;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        min-width: 600px;
    }
    .table th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
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
        .resumo-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .botoes-acao {
            flex-direction: column;
        }
        .botoes-acao .btn {
            justify-content: center;
        }
        .table {
            font-size: 12px;
            min-width: 500px;
        }
        .table th,
        .table td {
            padding: 8px 10px;
        }
    }
    @media (max-width: 480px) {
        .resumo-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ===== PAGE HEADER ===== -->
<div class="page-header">
    <div>
        <h1>📈 Relatório de Frequência</h1>
        <p class="subtitle">Análise detalhada de presenças por período</p>
    </div>
    <div>
        <a href="../frequencia/index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- ===== BOTÕES DE AÇÃO ===== -->
<div class="botoes-acao">
    <a href="?<?= http_build_query(array_merge($_GET, ['imprimir' => 1])) ?>" class="btn btn-primary" target="_blank">
        🖨️ Imprimir Relatório
    </a>
    <a href="?<?= http_build_query(array_merge($_GET, ['formato' => 'pdf'])) ?>" class="btn btn-danger">
        📄 Gerar PDF
    </a>
    <a href="?<?= http_build_query(array_merge($_GET, ['formato' => 'excel'])) ?>" class="btn btn-success">
        📊 Gerar Excel
    </a>
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
                <?php for ($a = date('Y') - 3; $a <= date('Y') + 1; $a++): ?>
                    <option value="<?= $a ?>" <?= ($ano_filtro == $a) ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="todos" <?= $status_filtro == 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="presente" <?= $status_filtro == 'presente' ? 'selected' : '' ?>>Presente</option>
                <option value="ausente" <?= $status_filtro == 'ausente' ? 'selected' : '' ?>>Ausente</option>
                <option value="justificado" <?= $status_filtro == 'justificado' ? 'selected' : '' ?>>Justificado</option>
                <option value="atrasado" <?= $status_filtro == 'atrasado' ? 'selected' : '' ?>>Atrasado</option>
            </select>
        </div>
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="frequencia.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<!-- ===== RESUMO ===== -->
<div class="resumo-grid">
    <div class="resumo-card total">
        <span class="icon">📊</span>
        <div class="number"><?= $resumo['total'] ?></div>
        <div class="label">Total de Registros</div>
    </div>
    <div class="resumo-card presentes">
        <span class="icon">✅</span>
        <div class="number"><?= $resumo['presentes'] ?></div>
        <div class="label">Presentes</div>
    </div>
    <div class="resumo-card ausentes">
        <span class="icon">❌</span>
        <div class="number"><?= $resumo['ausentes'] ?></div>
        <div class="label">Ausentes</div>
    </div>
    <div class="resumo-card justificados">
        <span class="icon">📋</span>
        <div class="number"><?= $resumo['justificados'] ?></div>
        <div class="label">Justificados</div>
    </div>
    <div class="resumo-card atrasados">
        <span class="icon">⏰</span>
        <div class="number"><?= $resumo['atrasados'] ?></div>
        <div class="label">Atrasados</div>
    </div>
    <div class="resumo-card percentual">
        <span class="icon">📈</span>
        <div class="number"><?= $resumo['percentual_presenca'] ?>%</div>
        <div class="label">Taxa de Presença</div>
    </div>
</div>

<!-- ===== TABELA ===== -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Data</th>
                <th>Aluno</th>
                <th>Turma</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($frequencias) > 0): ?>
                <?php $contador = 1; ?>
                <?php foreach ($frequencias as $f): ?>
                    <tr>
                        <td><?= $contador++ ?></td>
                        <td><?= $f['data_formatada'] ?></td>
                        <td>
                            <?php if (empty($f['aluno_nome'])): ?>
                                <span class="aluno-nao-encontrado">⚠️ Aluno #<?= $f['aluno_id'] ?></span>
                            <?php else: ?>
                                <strong><?= htmlspecialchars($f['aluno_nome']) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($f['turma_nome'] ?? 'Turma #' . $f['turma_id']) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($f['status'] ?? 'presente') ?>">
                                <?= $f['status_descricao'] ?? ucfirst($f['status'] ?? 'Presente') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Ajuste os filtros para visualizar os dados.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ===== RODAPÉ NA TELA ===== -->
<div class="row mt-4" style="font-size: 12px; color: #94a3b8; border-top: 1px solid #eef2f7; padding-top: 15px;">
    <div class="col-md-6">
        <i class="fa fa-calendar"></i> Gerado em: <?= date('d/m/Y H:i:s') ?>
        <br>
        <i class="fa fa-user"></i> Usuário: <?= $_SESSION['usuario_nome'] ?? 'Sistema' ?>
    </div>
    <div class="col-md-6 text-right">
        Complexo Escolar Castelo Reis &copy; <?= date('Y') ?>
    </div>
</div>

<?php include '../includes/footer_escola.php'; ?>