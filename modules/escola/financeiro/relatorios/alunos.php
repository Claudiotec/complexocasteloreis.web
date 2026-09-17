<?php
// ============================================
// modules/escola/financeiro/relatorios/alunos.php
// Relatório de Alunos - VERSÃO DEFINITIVA
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== FILTROS =====
$filtro_turma = isset($_GET['turma']) ? $_GET['turma'] : '';
$filtro_classe = isset($_GET['classe']) ? $_GET['classe'] : '';
$filtro_status = isset($_GET['status']) ? $_GET['status'] : 'todos';

// ===== DADOS PARA FILTROS =====
$turmas = [];
$classes = [];

try {
    $turmas = $pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA")->fetchAll();
    $classes = $pdo->query("SELECT DISTINCT Classe FROM alunos WHERE Classe IS NOT NULL AND Classe != '' ORDER BY Classe")->fetchAll();
} catch (Exception $e) {}

// ===== BUSCAR TODOS OS ALUNOS =====
$alunos = [];
$total_alunos = 0;
$total_em_dia = 0;
$total_inadimplentes = 0;
$total_parciais = 0;

try {
    // Buscar TODOS os alunos
    $sql = "SELECT id, nome, Classe, TURMA, matricula FROM alunos";
    $params = [];
    
    if (!empty($filtro_turma)) {
        $sql .= " WHERE TURMA = ?";
        $params[] = $filtro_turma;
    }
    
    if (!empty($filtro_classe)) {
        if (empty($filtro_turma)) {
            $sql .= " WHERE Classe = ?";
        } else {
            $sql .= " AND Classe = ?";
        }
        $params[] = $filtro_classe;
    }
    
    $sql .= " ORDER BY nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alunos_data = $stmt->fetchAll();
    
    foreach ($alunos_data as $aluno) {
        $aluno_id = $aluno['id'];
        
        // Buscar mensalidades do aluno
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as pagas,
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status = 'atrasado' THEN 1 ELSE 0 END) as atrasadas,
                SUM(CASE WHEN status IN ('pendente', 'atrasado') THEN valor ELSE 0 END) as valor_devido
            FROM mensalidades 
            WHERE aluno_id = ?
        ");
        $stmt->execute([$aluno_id]);
        $stats = $stmt->fetch();
        
        // Buscar pagamentos do aluno
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_pagamentos,
                SUM(valor) as total_pago,
                MAX(data_pagamento) as ultimo_pagamento
            FROM pagamentos 
            WHERE aluno_id = ?
        ");
        $stmt->execute([$aluno_id]);
        $pagamentos = $stmt->fetch();
        
        // Se não tiver mensalidades, criar dados vazios
        if (!$stats || $stats['total'] == 0) {
            // Verificar se tem pagamentos
            if ($pagamentos && $pagamentos['total_pagamentos'] > 0) {
                // Tem pagamentos mas não tem mensalidades
                $status_aluno = 'em_dia'; // Considerar como em dia
                $alunos[] = [
                    'id' => $aluno['id'],
                    'nome' => $aluno['nome'] ?? 'N/A',
                    'turma' => $aluno['TURMA'] ?? '-',
                    'classe' => $aluno['Classe'] ?? '-',
                    'matricula' => $aluno['matricula'] ?? '-',
                    'total_mensalidades' => 0,
                    'pagas' => 0,
                    'pendentes' => 0,
                    'atrasadas' => 0,
                    'valor_devido' => 0,
                    'total_pago' => $pagamentos['total_pago'] ?? 0,
                    'total_pagamentos' => $pagamentos['total_pagamentos'] ?? 0,
                    'ultimo_pagamento' => $pagamentos['ultimo_pagamento'] ?? null,
                    'status' => 'em_dia'
                ];
                $total_alunos++;
                $total_em_dia++;
                continue;
            }
            continue; // Pular alunos sem nada
        }
        
        // Determinar status do aluno
        $status_aluno = 'em_dia';
        if ($stats['atrasadas'] > 0) {
            $status_aluno = 'inadimplente';
        } elseif ($stats['pendentes'] > 0 && $stats['pagas'] > 0) {
            $status_aluno = 'parcial';
        } elseif ($stats['pendentes'] > 0 && $stats['pagas'] == 0) {
            $status_aluno = 'inadimplente';
        }
        
        // Aplicar filtro de status
        if ($filtro_status == 'em_dia' && $status_aluno != 'em_dia') continue;
        if ($filtro_status == 'inadimplentes' && $status_aluno != 'inadimplente') continue;
        if ($filtro_status == 'parcial' && $status_aluno != 'parcial') continue;
        
        $alunos[] = [
            'id' => $aluno['id'],
            'nome' => $aluno['nome'] ?? 'N/A',
            'turma' => $aluno['TURMA'] ?? '-',
            'classe' => $aluno['Classe'] ?? '-',
            'matricula' => $aluno['matricula'] ?? '-',
            'total_mensalidades' => $stats['total'] ?? 0,
            'pagas' => $stats['pagas'] ?? 0,
            'pendentes' => $stats['pendentes'] ?? 0,
            'atrasadas' => $stats['atrasadas'] ?? 0,
            'valor_devido' => $stats['valor_devido'] ?? 0,
            'total_pago' => $pagamentos['total_pago'] ?? 0,
            'total_pagamentos' => $pagamentos['total_pagamentos'] ?? 0,
            'ultimo_pagamento' => $pagamentos['ultimo_pagamento'] ?? null,
            'status' => $status_aluno
        ];
        
        $total_alunos++;
        if ($status_aluno == 'em_dia') $total_em_dia++;
        elseif ($status_aluno == 'inadimplente') $total_inadimplentes++;
        else $total_parciais++;
    }
    
} catch (Exception $e) {
    $alunos = [];
}

// ===== FUNÇÕES =====
function formatarMoeda($valor) {
    return 'Kz ' . number_format($valor, 2, ',', '.');
}

function getStatusBadge($status) {
    if ($status == 'em_dia') {
        return '<span class="status-badge status-em-dia">✅ Em dia</span>';
    } elseif ($status == 'inadimplente') {
        return '<span class="status-badge status-inadimplente">⚠️ Inadimplente</span>';
    } else {
        return '<span class="status-badge status-parcial">⏳ Parcial</span>';
    }
}

include '../../includes/header_escola.php';
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
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    .btn-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }
    .filtros {
        background: white;
        padding: 20px 25px;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    .filtros .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        align-items: end;
    }
    .filtros label {
        font-size: 12px;
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 4px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .filtros select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 13px;
        background: white;
    }
    .menu-financeiro {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 25px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
    }
    .menu-financeiro a {
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
        color: #4a5568;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .menu-financeiro a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    .menu-financeiro a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
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
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .stat-card .icon {
        font-size: 24px;
        display: block;
        margin-bottom: 5px;
    }
    .stat-card.total { border-left-color: #3498db; }
    .stat-card.em-dia { border-left-color: #2ecc71; }
    .stat-card.inadimplentes { border-left-color: #e74c3c; }
    .stat-card.parciais { border-left-color: #f39c12; }
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
        font-size: 13px;
        min-width: 800px;
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
    .table tr.inadimplente { background: #fef2f2; }
    .table tr.parcial { background: #fefce8; }
    .table tr.em-dia { background: #f0fdf4; }
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    .status-em-dia {
        background: #d1fae5;
        color: #065f46;
    }
    .status-inadimplente {
        background: #fee2e2;
        color: #991b1b;
    }
    .status-parcial {
        background: #fef3c7;
        color: #92400e;
    }
    .empty-state {
        text-align: center;
        padding: 40px 20px;
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
        margin: 0 0 8px;
    }
    .valor-devido {
        color: #dc3545;
        font-weight: 700;
    }
    .btn-print {
        padding: 8px 20px;
        background: #1a2332;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: stretch; }
        .menu-financeiro { flex-direction: column; align-items: stretch; }
        .filtros .filter-row { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .table { font-size: 12px; min-width: 600px; }
    }
    @media print {
        .no-print { display: none !important; }
        .filtros { display: none !important; }
        .menu-financeiro { display: none !important; }
    }
</style>

<div class="page-header">
    <div>
        <h1>👨‍🎓 Relatório de Alunos</h1>
        <p class="subtitle">Situação financeira dos alunos</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button onclick="window.print()" class="btn-print">🖨️ Imprimir</button>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<div class="menu-financeiro">
    <a href="index.php">📊 Dashboard</a>
    <a href="pagamentos.php">💳 Pagamentos</a>
    <a href="mensalidades.php">📅 Mensalidades</a>
    <a href="contas.php">🏦 Contas</a>
    <a href="alunos.php" class="active">👨‍🎓 Alunos</a>
    <a href="fluxo_caixa.php">💵 Fluxo de Caixa</a>
</div>

<!-- Filtros -->
<div class="filtros no-print">
    <form method="GET" action="">
        <div class="filter-row">
            <div>
                <label for="status">Situação</label>
                <select name="status" id="status">
                    <option value="todos" <?= $filtro_status == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="em_dia" <?= $filtro_status == 'em_dia' ? 'selected' : '' ?>>✅ Em dia</option>
                    <option value="inadimplentes" <?= $filtro_status == 'inadimplentes' ? 'selected' : '' ?>>⚠️ Inadimplentes</option>
                    <option value="parcial" <?= $filtro_status == 'parcial' ? 'selected' : '' ?>>⏳ Parcial</option>
                </select>
            </div>
            <div>
                <label for="turma">Turma</label>
                <select name="turma" id="turma">
                    <option value="">Todas</option>
                    <?php foreach($turmas as $t): ?>
                        <option value="<?= htmlspecialchars($t['TURMA']) ?>" <?= $filtro_turma == $t['TURMA'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['TURMA']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="classe">Classe</label>
                <select name="classe" id="classe">
                    <option value="">Todas</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c['Classe']) ?>" <?= $filtro_classe == $c['Classe'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['Classe']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 8px; align-items: end; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                <a href="alunos.php" class="btn btn-secondary">✕ Limpar</a>
            </div>
        </div>
    </form>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card total">
        <span class="icon">📊</span>
        <div class="number"><?= $total_alunos ?></div>
        <div class="label">Total de Alunos</div>
    </div>
    <div class="stat-card em-dia">
        <span class="icon">✅</span>
        <div class="number"><?= $total_em_dia ?></div>
        <div class="label">Em dia</div>
    </div>
    <div class="stat-card inadimplentes">
        <span class="icon">⚠️</span>
        <div class="number"><?= $total_inadimplentes ?></div>
        <div class="label">Inadimplentes</div>
    </div>
    <div class="stat-card parciais">
        <span class="icon">⏳</span>
        <div class="number"><?= $total_parciais ?></div>
        <div class="label">Parcial</div>
    </div>
</div>

<!-- Tabela -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Aluno</th>
                <th>Turma</th>
                <th>Classe</th>
                <th>Mensalidades</th>
                <th>Pagas</th>
                <th>Pendentes</th>
                <th>Atrasadas</th>
                <th>Valor Devido</th>
                <th>Total Pago</th>
                <th>Último Pagamento</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($alunos) > 0): ?>
                <?php $i = 1; foreach($alunos as $a): 
                    $row_class = $a['status'] == 'inadimplente' ? 'inadimplente' : ($a['status'] == 'parcial' ? 'parcial' : 'em-dia');
                ?>
                <tr class="<?= $row_class ?>">
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($a['nome']) ?></strong></td>
                    <td><?= htmlspecialchars($a['turma']) ?></td>
                    <td><?= htmlspecialchars($a['classe']) ?></td>
                    <td><?= $a['total_mensalidades'] ?></td>
                    <td><?= $a['pagas'] ?></td>
                    <td><?= $a['pendentes'] ?></td>
                    <td><?= $a['atrasadas'] ?></td>
                    <td class="valor-devido"><?= $a['valor_devido'] > 0 ? formatarMoeda($a['valor_devido']) : '-' ?></td>
                    <td><?= $a['total_pago'] > 0 ? formatarMoeda($a['total_pago']) : '-' ?></td>
                    <td><?= $a['ultimo_pagamento'] ? date('d/m/Y', strtotime($a['ultimo_pagamento'])) : '-' ?></td>
                    <td><?= getStatusBadge($a['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhum aluno encontrado</h3>
                            <p>Não há alunos com mensalidades ou pagamentos registrados.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../../includes/footer_escola.php'; ?>