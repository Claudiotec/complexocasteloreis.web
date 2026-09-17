<?php
// ============================================
// modules/escola/financeiro/mensalidades/relatorios_mensalidades.php
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

// ===== PROCESSAR FILTROS =====
$tipo_relatorio = isset($_GET['tipo']) ? $_GET['tipo'] : 'geral';
$filtro_turma = isset($_GET['turma']) ? $_GET['turma'] : '';
$filtro_classe = isset($_GET['classe']) ? $_GET['classe'] : '';
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_mes = isset($_GET['mes']) ? intval($_GET['mes']) : 0;
$filtro_ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');
$filtro_aluno = isset($_GET['aluno']) ? intval($_GET['aluno']) : 0;
$filtro_periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '';

// ===== DADOS PARA FILTROS =====
$turmas = [];
$classes = [];
$alunos = [];

try {
    $turmas = $pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA")->fetchAll();
    $classes = $pdo->query("SELECT DISTINCT Classe FROM alunos WHERE Classe IS NOT NULL AND Classe != '' ORDER BY Classe")->fetchAll();
    $alunos = $pdo->query("SELECT id, nome FROM alunos ORDER BY nome")->fetchAll();
} catch (Exception $e) {}

// ===== FUNÇÃO PARA ATUALIZAR STATUS =====
function atualizarStatusMensalidades($pdo) {
    try {
        // Verificar se a tabela existe
        $check = $pdo->query("SHOW TABLES LIKE 'mensalidades'");
        if ($check->rowCount() > 0) {
            // Atualizar mensalidades vencidas
            $sql = "UPDATE mensalidades 
                    SET status = 'atrasado' 
                    WHERE data_vencimento < CURDATE() 
                    AND status IN ('pendente', '')";
            $pdo->exec($sql);
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Executar atualização
atualizarStatusMensalidades($pdo);

// ===== BUSCAR DADOS =====
$dados_relatorio = [];
$total_geral = 0;
$total_pago = 0;
$total_pendente = 0;
$total_atrasado = 0;

try {
    // Verificar se a tabela mensalidades existe
    $check = $pdo->query("SHOW TABLES LIKE 'mensalidades'");
    if ($check->rowCount() == 0) {
        $dados_relatorio = [];
        $erro_tabela = "A tabela 'mensalidades' ainda não foi criada. Execute o script SQL para criar a tabela.";
    } else {
        // Query principal
        $sql = "
            SELECT 
                m.*,
                a.nome as aluno_nome,
                a.Classe as aluno_classe,
                a.TURMA as turma_nome,
                a.Periodo as aluno_periodo,
                a.matricula
            FROM mensalidades m
            LEFT JOIN alunos a ON m.aluno_id = a.id
        ";
        
        // Adicionar filtros
        $where = [];
        $params = [];
        
        if (!empty($filtro_turma)) {
            $where[] = "a.TURMA = ?";
            $params[] = $filtro_turma;
        }
        
        if (!empty($filtro_classe)) {
            $where[] = "a.Classe = ?";
            $params[] = $filtro_classe;
        }
        
        if (!empty($filtro_status)) {
            $where[] = "m.status = ?";
            $params[] = $filtro_status;
        }
        
        if ($filtro_mes > 0) {
            $where[] = "m.mes = ?";
            $params[] = $filtro_mes;
        }
        
        if ($filtro_ano > 0) {
            $where[] = "m.ano = ?";
            $params[] = $filtro_ano;
        }
        
        if ($filtro_aluno > 0) {
            $where[] = "m.aluno_id = ?";
            $params[] = $filtro_aluno;
        }
        
        if (!empty($filtro_periodo)) {
            $dias = intval($filtro_periodo);
            $where[] = "m.data_vencimento >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $params[] = $dias;
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY m.data_vencimento ASC, m.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dados_relatorio = $stmt->fetchAll();
        
        // Calcular totais
        foreach ($dados_relatorio as $d) {
            $total_geral += $d['valor'];
            if ($d['status'] == 'pago') {
                $total_pago += $d['valor'];
            } elseif ($d['status'] == 'atrasado') {
                $total_atrasado += $d['valor'];
            } else {
                $total_pendente += $d['valor'];
            }
        }
    }
    
} catch (Exception $e) {
    $dados_relatorio = [];
    $erro_tabela = "Erro ao buscar dados: " . $e->getMessage();
}

// ===== AGRUPAR POR TURMA =====
$por_turma = [];
foreach ($dados_relatorio as $d) {
    $key = $d['turma_nome'] ?? 'Sem Turma';
    if (!isset($por_turma[$key])) {
        $por_turma[$key] = ['total' => 0, 'pago' => 0, 'pendente' => 0, 'atrasado' => 0, 'count' => 0];
    }
    $por_turma[$key]['total'] += $d['valor'];
    $por_turma[$key]['count']++;
    if ($d['status'] == 'pago') {
        $por_turma[$key]['pago'] += $d['valor'];
    } elseif ($d['status'] == 'atrasado') {
        $por_turma[$key]['atrasado'] += $d['valor'];
    } else {
        $por_turma[$key]['pendente'] += $d['valor'];
    }
}

// ===== AGRUPAR POR CLASSE =====
$por_classe = [];
foreach ($dados_relatorio as $d) {
    $key = $d['aluno_classe'] ?? 'Sem Classe';
    if (!isset($por_classe[$key])) {
        $por_classe[$key] = ['total' => 0, 'pago' => 0, 'pendente' => 0, 'atrasado' => 0, 'count' => 0];
    }
    $por_classe[$key]['total'] += $d['valor'];
    $por_classe[$key]['count']++;
    if ($d['status'] == 'pago') {
        $por_classe[$key]['pago'] += $d['valor'];
    } elseif ($d['status'] == 'atrasado') {
        $por_classe[$key]['atrasado'] += $d['valor'];
    } else {
        $por_classe[$key]['pendente'] += $d['valor'];
    }
}

include '../../includes/header_escola.php';
?>

<style>
    /* ===== ESTILO PROFISSIONAL ===== */
    :root {
        --agt-primary: #0d1a26;
        --agt-secondary: #c9a84c;
        --agt-dark: #0d1a26;
        --agt-border: #e9ecef;
        --agt-success: #28a745;
        --agt-danger: #dc3545;
        --agt-warning: #ffc107;
        --agt-info: #17a2b8;
    }
    
    .agt-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
        background: #f4f6f9;
    }
    
    .agt-header {
        background: linear-gradient(135deg, #0d1a26 0%, #1a2a3a 100%);
        color: white;
        padding: 25px 35px;
        border-radius: 12px 12px 0 0;
        position: relative;
        overflow: hidden;
    }
    
    .agt-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(201, 168, 76, 0.08);
        border-radius: 50%;
    }
    
    .agt-header .brand {
        display: flex;
        align-items: center;
        gap: 15px;
        position: relative;
        z-index: 1;
    }
    
    .agt-header .brand-icon {
        width: 50px;
        height: 50px;
        background: #c9a84c;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        font-weight: 700;
        color: #0d1a26;
    }
    
    .agt-header h1 {
        font-size: 26px;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.5px;
    }
    
    .agt-header .subtitle {
        color: rgba(255,255,255,0.7);
        font-size: 13px;
        margin: 3px 0 0;
    }
    
    .agt-header .badge-agt {
        background: #c9a84c;
        color: #0d1a26;
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 12px;
    }
    
    .agt-header .header-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    
    .agt-menu {
        background: white;
        padding: 12px 20px;
        border-bottom: 2px solid #e9ecef;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .agt-menu a {
        padding: 6px 16px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 500;
        transition: all 0.3s;
        color: #4a5568;
        background: #f8fafc;
        border: 1px solid #e9ecef;
    }
    
    .agt-menu a:hover {
        background: #c9a84c;
        color: #0d1a26;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }
    
    .agt-menu a.active {
        background: #c9a84c;
        color: #0d1a26;
        border-color: #c9a84c;
    }
    
    .agt-filters {
        background: white;
        padding: 20px 25px;
        border-radius: 12px;
        margin: 15px 0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #e9ecef;
    }
    
    .agt-filters .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        align-items: end;
    }
    
    .agt-filters label {
        font-size: 11px;
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 3px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .agt-filters select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        font-size: 12px;
        background: white;
    }
    
    .agt-filters select:focus {
        border-color: #c9a84c;
        outline: none;
        box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
    }
    
    .btn-agt-primary {
        padding: 8px 24px;
        background: #c9a84c;
        color: #0d1a26;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
    }
    
    .btn-agt-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }
    
    .btn-agt-secondary {
        padding: 8px 24px;
        background: #f1f5f9;
        color: #4a5568;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-size: 12px;
    }
    
    .btn-agt-secondary:hover {
        background: #e2e8f0;
    }
    
    .agt-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin: 15px 0;
    }
    
    .agt-stat-card {
        background: white;
        padding: 15px 20px;
        border-radius: 10px;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border-left: 4px solid #c9a84c;
        transition: all 0.3s;
    }
    
    .agt-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .agt-stat-card .number {
        font-size: 24px;
        font-weight: 700;
        color: #0d1a26;
        margin: 0;
    }
    
    .agt-stat-card .label {
        font-size: 11px;
        color: #94a3b8;
        margin: 2px 0 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .agt-stat-card .icon {
        font-size: 20px;
        display: block;
        margin-bottom: 3px;
    }
    
    .agt-stat-card.total { border-left-color: #17a2b8; }
    .agt-stat-card.pago { border-left-color: #28a745; }
    .agt-stat-card.pendente { border-left-color: #ffc107; }
    .agt-stat-card.atrasado { border-left-color: #dc3545; }
    
    .agt-group-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e9ecef;
        padding: 20px 25px;
        margin: 15px 0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .agt-group-card h3 {
        font-size: 15px;
        font-weight: 700;
        color: #0d1a26;
        margin: 0 0 12px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .agt-group-card h3 .badge-count {
        background: #c9a84c;
        color: #0d1a26;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
    }
    
    .agt-table-wrap {
        overflow-x: auto;
    }
    
    .agt-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    
    .agt-table thead {
        background: linear-gradient(135deg, #0d1a26 0%, #1a2a3a 100%);
        color: white;
    }
    
    .agt-table th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    
    .agt-table td {
        padding: 8px 14px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: middle;
    }
    
    .agt-table tbody tr:hover {
        background: #f8fafc;
    }
    
    .agt-status {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .agt-status-pago {
        background: #d1fae5;
        color: #065f46;
    }
    
    .agt-status-pendente {
        background: #fef3c7;
        color: #92400e;
    }
    
    .agt-status-atrasado {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .agt-status-cancelado {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .agt-valor-positivo {
        color: #28a745;
        font-weight: 700;
    }
    
    .agt-valor-negativo {
        color: #dc3545;
        font-weight: 700;
    }
    
    .agt-valor-warning {
        color: #ffc107;
        font-weight: 700;
    }
    
    .agt-progress {
        height: 6px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 5px;
    }
    
    .agt-progress .fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.8s ease;
    }
    
    .agt-progress .fill.success { background: #28a745; }
    .agt-progress .fill.warning { background: #ffc107; }
    .agt-progress .fill.danger { background: #dc3545; }
    
    .agt-footer {
        background: #0d1a26;
        color: rgba(255,255,255,0.7);
        padding: 15px 30px;
        border-radius: 0 0 12px 12px;
        text-align: center;
        font-size: 11px;
        margin-top: 15px;
    }
    
    .agt-footer strong {
        color: #c9a84c;
    }
    
    .agt-print-btn {
        padding: 8px 20px;
        background: white;
        color: #0d1a26;
        border: 2px solid white;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
    }
    
    .agt-print-btn:hover {
        background: rgba(255,255,255,0.1);
        color: white;
        transform: translateY(-2px);
    }
    
    .agt-empty {
        text-align: center;
        padding: 40px 20px;
        color: #94a3b8;
    }
    
    .agt-empty .icon {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
    }
    
    .agt-empty h3 {
        font-size: 18px;
        color: #4a5568;
        margin: 0 0 8px;
    }
    
    .agt-alert {
        background: #fef3c7;
        border: 1px solid #fde68a;
        color: #92400e;
        padding: 15px 20px;
        border-radius: 8px;
        margin: 15px 0;
    }
    
    .agt-alert .icon {
        font-size: 24px;
        margin-right: 10px;
    }
    
    /* ===== IMPRESSÃO ===== */
    @media print {
        .no-print { display: none !important; }
        .agt-filters { display: none !important; }
        .agt-menu { display: none !important; }
        
        .agt-header {
            background: #0d1a26 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            padding: 15px 25px !important;
            border-radius: 0 !important;
        }
        
        .agt-table thead {
            background: #0d1a26 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .agt-table td {
            padding: 5px 8px !important;
            font-size: 10px !important;
        }
        
        .agt-stat-card {
            border: 1px solid #ddd !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .agt-status {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .agt-footer {
            background: #0d1a26 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            border-radius: 0 !important;
        }
        
        body {
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .agt-container {
            padding: 0 !important;
            max-width: 100% !important;
            background: white !important;
        }
    }
</style>

<div class="agt-container">
    
    <!-- HEADER -->
    <div class="agt-header">
        <div class="brand">
            <div class="brand-icon">📅</div>
            <div>
                <h1>Relatório de Mensalidades</h1>
                <div class="subtitle">Sistema de Gestão Escolar - SoftGest</div>
            </div>
        </div>
        <div class="header-actions">
            <span class="badge-agt">📅 <?= date('d/m/Y H:i') ?></span>
            <button onclick="window.print()" class="agt-print-btn no-print">🖨️ Imprimir</button>
            <a href="index.php" class="agt-print-btn no-print" style="background: transparent; border-color: rgba(255,255,255,0.3); color: white;">← Voltar</a>
        </div>
    </div>
    
    <!-- MENU -->
    <div class="agt-menu no-print">
        <a href="relatorios_mensalidades.php?tipo=geral" class="<?= $tipo_relatorio == 'geral' ? 'active' : '' ?>">📋 Geral</a>
        <a href="relatorios_mensalidades.php?tipo=pendentes" class="<?= $tipo_relatorio == 'pendentes' ? 'active' : '' ?>">⏳ Pendentes</a>
        <a href="relatorios_mensalidades.php?tipo=atrasados" class="<?= $tipo_relatorio == 'atrasados' ? 'active' : '' ?>">⚠️ Atrasados</a>
        <a href="relatorios_mensalidades.php?tipo=pagos" class="<?= $tipo_relatorio == 'pagos' ? 'active' : '' ?>">✅ Pagos</a>
        <a href="relatorios_mensalidades.php?tipo=por_turma" class="<?= $tipo_relatorio == 'por_turma' ? 'active' : '' ?>">🏫 Por Turma</a>
        <a href="relatorios_mensalidades.php?tipo=por_classe" class="<?= $tipo_relatorio == 'por_classe' ? 'active' : '' ?>">📚 Por Classe</a>
        <a href="relatorios_mensalidades.php?tipo=resumo" class="<?= $tipo_relatorio == 'resumo' ? 'active' : '' ?>">📊 Resumo</a>
        <a href="relatorios_mensalidades.php?tipo=aluno" class="<?= $tipo_relatorio == 'aluno' ? 'active' : '' ?>">👤 Por Aluno</a>
    </div>
    
    <!-- FILTROS -->
    <div class="agt-filters no-print">
        <form method="GET" action="">
            <input type="hidden" name="tipo" value="<?= $tipo_relatorio ?>">
            <div class="filter-grid">
                <div>
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">Todos</option>
                        <option value="pago" <?= $filtro_status == 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="pendente" <?= $filtro_status == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="atrasado" <?= $filtro_status == 'atrasado' ? 'selected' : '' ?>>Atrasado</option>
                        <option value="cancelado" <?= $filtro_status == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
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
                <div>
                    <label for="mes">Mês</label>
                    <select name="mes" id="mes">
                        <option value="0">Todos</option>
                        <?php for($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $filtro_mes == $i ? 'selected' : '' ?>>
                                <?= date('F', mktime(0,0,0,$i,1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="ano">Ano</label>
                    <select name="ano" id="ano">
                        <?php for($i = date('Y'); $i >= date('Y')-5; $i--): ?>
                            <option value="<?= $i ?>" <?= $filtro_ano == $i ? 'selected' : '' ?>>
                                <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="periodo">Período</label>
                    <select name="periodo" id="periodo">
                        <option value="">Todos</option>
                        <option value="30" <?= $filtro_periodo == '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                        <option value="60" <?= $filtro_periodo == '60' ? 'selected' : '' ?>>Últimos 60 dias</option>
                        <option value="90" <?= $filtro_periodo == '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                    </select>
                </div>
                <div>
                    <label for="aluno">Aluno</label>
                    <select name="aluno" id="aluno">
                        <option value="0">Todos</option>
                        <?php foreach($alunos as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $filtro_aluno == $a['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 8px; align-items: end; flex-wrap: wrap;">
                    <button type="submit" class="btn-agt-primary">🔍 Filtrar</button>
                    <a href="relatorios_mensalidades.php?tipo=<?= $tipo_relatorio ?>" class="btn-agt-secondary">✕ Limpar</a>
                </div>
            </div>
        </form>
    </div>
    
    <?php if (isset($erro_tabela)): ?>
        <!-- ALERTA DE TABELA NÃO EXISTE -->
        <div class="agt-alert">
            <span class="icon">⚠️</span>
            <strong>Atenção:</strong> <?= $erro_tabela ?>
            <br><br>
            <strong>Script SQL para criar a tabela:</strong>
            <pre style="background: #f1f5f9; padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 11px; overflow-x: auto;">
CREATE TABLE mensalidades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    aluno_id INT NOT NULL,
    mes INT NOT NULL,
    ano INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE,
    status ENUM('pago', 'pendente', 'atrasado', 'cancelado') DEFAULT 'pendente',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_aluno (aluno_id),
    INDEX idx_status (status),
    INDEX idx_mes_ano (mes, ano),
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            </pre>
        </div>
    <?php endif; ?>
    
    <!-- STATS -->
    <div class="agt-stats">
        <div class="agt-stat-card total">
            <span class="icon">📊</span>
            <div class="number"><?= count($dados_relatorio) ?></div>
            <div class="label">Total Registros</div>
        </div>
        <div class="agt-stat-card total">
            <span class="icon">💰</span>
            <div class="number">Kz <?= number_format($total_geral, 2, ',', '.') ?></div>
            <div class="label">Valor Total</div>
        </div>
        <div class="agt-stat-card pago">
            <span class="icon">✅</span>
            <div class="number">Kz <?= number_format($total_pago, 2, ',', '.') ?></div>
            <div class="label">Total Pago</div>
        </div>
        <div class="agt-stat-card pendente">
            <span class="icon">⏳</span>
            <div class="number">Kz <?= number_format($total_pendente, 2, ',', '.') ?></div>
            <div class="label">Total Pendente</div>
        </div>
        <div class="agt-stat-card atrasado">
            <span class="icon">⚠️</span>
            <div class="number">Kz <?= number_format($total_atrasado, 2, ',', '.') ?></div>
            <div class="label">Total Atrasado</div>
        </div>
    </div>
    
    <?php if ($tipo_relatorio == 'por_turma' && count($dados_relatorio) > 0): ?>
        <!-- ===== RELATÓRIO POR TURMA ===== -->
        <div class="agt-group-card">
            <h3>🏫 Resumo por Turma <span class="badge-count"><?= count($por_turma) ?> turmas</span></h3>
            <div class="agt-table-wrap">
                <table class="agt-table">
                    <thead>
                        <tr>
                            <th>Turma</th>
                            <th>Alunos</th>
                            <th>Valor Total</th>
                            <th>✅ Pago</th>
                            <th>⏳ Pendente</th>
                            <th>⚠️ Atrasado</th>
                            <th>% Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($por_turma as $turma => $dados): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($turma) ?></strong></td>
                            <td><?= $dados['count'] ?></td>
                            <td><strong>Kz <?= number_format($dados['total'], 2, ',', '.') ?></strong></td>
                            <td class="agt-valor-positivo">Kz <?= number_format($dados['pago'], 2, ',', '.') ?></td>
                            <td class="agt-valor-warning">Kz <?= number_format($dados['pendente'], 2, ',', '.') ?></td>
                            <td class="agt-valor-negativo">Kz <?= number_format($dados['atrasado'], 2, ',', '.') ?></td>
                            <td>
                                <?php 
                                $percentual = $dados['total'] > 0 ? round(($dados['pago'] / $dados['total']) * 100) : 0;
                                ?>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-weight: 600; min-width: 45px;"><?= $percentual ?>%</span>
                                    <div class="agt-progress" style="flex: 1;">
                                        <div class="fill <?= $percentual >= 80 ? 'success' : ($percentual >= 50 ? 'warning' : 'danger') ?>" 
                                             style="width: <?= $percentual ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($tipo_relatorio == 'por_classe' && count($dados_relatorio) > 0): ?>
        <!-- ===== RELATÓRIO POR CLASSE ===== -->
        <div class="agt-group-card">
            <h3>📚 Resumo por Classe <span class="badge-count"><?= count($por_classe) ?> classes</span></h3>
            <div class="agt-table-wrap">
                <table class="agt-table">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Alunos</th>
                            <th>Valor Total</th>
                            <th>✅ Pago</th>
                            <th>⏳ Pendente</th>
                            <th>⚠️ Atrasado</th>
                            <th>% Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($por_classe as $classe => $dados): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($classe) ?></strong></td>
                            <td><?= $dados['count'] ?></td>
                            <td><strong>Kz <?= number_format($dados['total'], 2, ',', '.') ?></strong></td>
                            <td class="agt-valor-positivo">Kz <?= number_format($dados['pago'], 2, ',', '.') ?></td>
                            <td class="agt-valor-warning">Kz <?= number_format($dados['pendente'], 2, ',', '.') ?></td>
                            <td class="agt-valor-negativo">Kz <?= number_format($dados['atrasado'], 2, ',', '.') ?></td>
                            <td>
                                <?php 
                                $percentual = $dados['total'] > 0 ? round(($dados['pago'] / $dados['total']) * 100) : 0;
                                ?>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-weight: 600; min-width: 45px;"><?= $percentual ?>%</span>
                                    <div class="agt-progress" style="flex: 1;">
                                        <div class="fill <?= $percentual >= 80 ? 'success' : ($percentual >= 50 ? 'warning' : 'danger') ?>" 
                                             style="width: <?= $percentual ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($tipo_relatorio == 'resumo' && count($dados_relatorio) > 0): ?>
        <!-- ===== RESUMO GERAL ===== -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
            <div class="agt-group-card">
                <h3>📊 Distribuição por Status</h3>
                <?php 
                $status_count = [];
                $status_valor = [];
                foreach($dados_relatorio as $d) {
                    $status = $d['status'] ?? 'pendente';
                    if (!isset($status_count[$status])) $status_count[$status] = 0;
                    if (!isset($status_valor[$status])) $status_valor[$status] = 0;
                    $status_count[$status]++;
                    $status_valor[$status] += $d['valor'];
                }
                $total = count($dados_relatorio);
                ?>
                <table class="agt-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Quantidade</th>
                            <th>Valor</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach(['pago' => '✅ Pago', 'pendente' => '⏳ Pendente', 'atrasado' => '⚠️ Atrasado'] as $key => $label): 
                            $count = $status_count[$key] ?? 0;
                            $valor = $status_valor[$key] ?? 0;
                            $percent = $total > 0 ? round(($count / $total) * 100) : 0;
                            $color = $key == 'pago' ? 'success' : ($key == 'atrasado' ? 'danger' : 'warning');
                        ?>
                        <tr>
                            <td><?= $label ?></td>
                            <td><?= $count ?></td>
                            <td class="agt-valor-<?= $key == 'pago' ? 'positivo' : ($key == 'atrasado' ? 'negativo' : 'warning') ?>">
                                Kz <?= number_format($valor, 2, ',', '.') ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-weight: 600; min-width: 40px;"><?= $percent ?>%</span>
                                    <div class="agt-progress" style="flex: 1;">
                                        <div class="fill <?= $color ?>" style="width: <?= $percent ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="agt-group-card">
                <h3>💰 Resumo Financeiro</h3>
                <table class="agt-table">
                    <thead>
                        <tr>
                            <th>Indicador</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>💵 Total Geral</td>
                            <td><strong>Kz <?= number_format($total_geral, 2, ',', '.') ?></strong></td>
                        </tr>
                        <tr>
                            <td>✅ Total Pago</td>
                            <td class="agt-valor-positivo">Kz <?= number_format($total_pago, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td>⏳ Total Pendente</td>
                            <td class="agt-valor-warning">Kz <?= number_format($total_pendente, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td>⚠️ Total Atrasado</td>
                            <td class="agt-valor-negativo">Kz <?= number_format($total_atrasado, 2, ',', '.') ?></td>
                        </tr>
                        <tr style="border-top: 2px solid #e9ecef;">
                            <td><strong>📊 Taxa de Pagamento</strong></td>
                            <td>
                                <?php 
                                $taxa = $total_geral > 0 ? round(($total_pago / $total_geral) * 100) : 0;
                                ?>
                                <strong><?= $taxa ?>%</strong>
                                <div class="agt-progress" style="margin-top: 5px;">
                                    <div class="fill <?= $taxa >= 80 ? 'success' : ($taxa >= 50 ? 'warning' : 'danger') ?>" 
                                         style="width: <?= $taxa ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php else: ?>
        <!-- ===== LISTA GERAL ===== -->
        <div class="agt-group-card">
            <h3>
                <?php if ($tipo_relatorio == 'pendentes'): ?>
                    ⏳ Mensalidades Pendentes
                <?php elseif ($tipo_relatorio == 'atrasados'): ?>
                    ⚠️ Mensalidades Atrasadas
                <?php elseif ($tipo_relatorio == 'pagos'): ?>
                    ✅ Mensalidades Pagas
                <?php elseif ($tipo_relatorio == 'aluno' && $filtro_aluno > 0): ?>
                    👤 Mensalidades do Aluno
                <?php else: ?>
                    📋 Lista Geral de Mensalidades
                <?php endif; ?>
                <span class="badge-count"><?= count($dados_relatorio) ?> registros</span>
            </h3>
            
            <?php if (count($dados_relatorio) > 0): ?>
                <div class="agt-table-wrap">
                    <table class="agt-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Aluno</th>
                                <th>Turma</th>
                                <th>Classe</th>
                                <th>Mês/Ano</th>
                                <th>Valor (Kz)</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th>Dias</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach($dados_relatorio as $d): 
                                $dias = 0;
                                if ($d['data_vencimento'] && $d['status'] != 'pago') {
                                    $venc = new DateTime($d['data_vencimento']);
                                    $hoje = new DateTime();
                                    if ($venc < $hoje) {
                                        $dias = $hoje->diff($venc)->days;
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($d['aluno_nome'] ?? 'N/A') ?></strong></td>
                                <td><?= htmlspecialchars($d['turma_nome'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($d['aluno_classe'] ?? '-') ?></td>
                                <td><?= str_pad($d['mes'], 2, '0', STR_PAD_LEFT) ?>/<?= $d['ano'] ?></td>
                                <td class="<?= $d['status'] == 'pago' ? 'agt-valor-positivo' : 'agt-valor-negativo' ?>">
                                    <?= number_format($d['valor'], 2, ',', '.') ?>
                                </td>
                                <td><?= $d['data_vencimento'] ? date('d/m/Y', strtotime($d['data_vencimento'])) : '-' ?></td>
                                <td>
                                    <span class="agt-status agt-status-<?= $d['status'] ?>">
                                        <?= ucfirst($d['status'] ?? 'pendente') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($dias > 0): ?>
                                        <span style="color: #dc3545; font-weight: bold;"><?= $dias ?>d</span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="agt-empty">
                    <span class="icon">📭</span>
                    <h3>Nenhum registro encontrado</h3>
                    <p>Não há dados com os filtros selecionados.</p>
                    <?php if (!isset($erro_tabela)): ?>
                        <p style="margin-top: 10px; font-size: 12px; color: #94a3b8;">
                            Clique em "Gerar Mensalidades" para criar as mensalidades.
                        </p>
                        <a href="gerar.php" class="btn-agt-primary" style="margin-top: 10px;">📅 Gerar Mensalidades</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="agt-footer">
        <p>
            <strong>SoftGest Web</strong> © <?= date('Y') ?> - Sistema de Gestão Escolar<br>
            <span style="font-size: 10px;">
                Relatório gerado em <?= date('d/m/Y H:i:s') ?> | 
                <?= count($dados_relatorio) ?> registros | 
                Total: Kz <?= number_format($total_geral, 2, ',', '.') ?>
            </span>
        </p>
    </div>
    
</div>

<?php include '../../includes/footer_escola.php'; ?>