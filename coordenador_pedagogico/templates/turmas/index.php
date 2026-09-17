<?php
// ============================================
// modules/escola/turmas/index.php - Lista de Turmas (COM ALERTAS DE VAGAS)
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

// ===== CLASSES PRÉ-DEFINIDAS =====
$CLASSES_PRE_DEFINIDAS = [
    'PRÉ', '1ª', '2ª', '3ª', '4ª', '5ª', '6ª', 
    '7ª', '8ª', '9ª', '10ª', '11ª', '12ª'
];

// ===== DADOS =====
$totalTurmas = 0;
$totalAtivas = 0;
$totalConcluidas = 0;
$totalCanceladas = 0;
$totalEmergencia = 0;
$totalAlerta = 0;

try {
    $totalTurmas = $pdo->query("SELECT COUNT(*) FROM turmas")->fetchColumn() ?? 0;
    $totalAtivas = $pdo->query("SELECT COUNT(*) FROM turmas WHERE status = 'ativa'")->fetchColumn() ?? 0;
    $totalConcluidas = $pdo->query("SELECT COUNT(*) FROM turmas WHERE status = 'concluida'")->fetchColumn() ?? 0;
    $totalCanceladas = $pdo->query("SELECT COUNT(*) FROM turmas WHERE status = 'cancelada'")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// ===== BUSCAR TURMAS =====
$turmas = [];
try {
    // Query simplificada sem subquery problemática
    $turmas = $pdo->query("
        SELECT t.*, 
               p.nome as professor_nome
        FROM turmas t
        LEFT JOIN professores p ON t.professor_id = p.id
        ORDER BY t.nome
    ")->fetchAll();
    
    // Buscar contagem de alunos separadamente para cada turma
    foreach ($turmas as &$t) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE TURMA = ? AND status = 'ativo'");
            $stmt->execute([$t['nome']]);
            $t['total_alunos'] = $stmt->fetchColumn() ?: 0;
            
            // Calcular vagas e definir nível de alerta
            $limite = $t['limite'] ?? 30;
            $totalAlunos = $t['total_alunos'];
            $vagasDisponiveis = $limite - $totalAlunos;
            $percentualOcupacao = $limite > 0 ? ($totalAlunos / $limite) * 100 : 0;
            
            // Definir status de alerta
            if ($vagasDisponiveis <= 0) {
                $t['alerta'] = 'emergencia';
                $t['alerta_msg'] = '🚨 TURMA LOTADA! Sem vagas disponíveis';
                $totalEmergencia++;
            } elseif ($vagasDisponiveis <= 3) {
                $t['alerta'] = 'critico';
                $t['alerta_msg'] = '⚠️ ATENÇÃO! Apenas ' . $vagasDisponiveis . ' vaga(s) restante(s)';
                $totalAlerta++;
            } elseif ($vagasDisponiveis <= 5) {
                $t['alerta'] = 'atencao';
                $t['alerta_msg'] = '⚡ Poucas vagas: ' . $vagasDisponiveis . ' vaga(s) disponíveis';
                $totalAlerta++;
            } else {
                $t['alerta'] = 'normal';
                $t['alerta_msg'] = '✅ ' . $vagasDisponiveis . ' vagas disponíveis';
            }
            
            $t['vagas_disponiveis'] = $vagasDisponiveis;
            $t['percentual_ocupacao'] = round($percentualOcupacao, 1);
            
        } catch (Exception $e) {
            $t['total_alunos'] = 0;
            $t['alerta'] = 'normal';
            $t['alerta_msg'] = '✅ Vagas disponíveis';
            $t['vagas_disponiveis'] = $t['limite'] ?? 30;
            $t['percentual_ocupacao'] = 0;
        }
    }
    
} catch (Exception $e) {
    // Se a tabela não existir, criar
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS turmas (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nome VARCHAR(50) NOT NULL,
                classe VARCHAR(10) NOT NULL,
                curso VARCHAR(50) NOT NULL,
                ano_letivo VARCHAR(10),
                turno ENUM('manha','tarde','noite','integral') DEFAULT 'manha',
                sala VARCHAR(20),
                limite INT DEFAULT 30,
                idades VARCHAR(20) DEFAULT '',
                disciplinas TEXT,
                professor_id INT,
                status ENUM('ativa','concluida','cancelada') DEFAULT 'ativa',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $turmas = [];
    } catch (Exception $e2) {
        $turmas = [];
    }
}

// ===== BUSCAR CURSOS =====
$cursos = [];
try {
    $cursos = $pdo->query("SELECT DISTINCT curso FROM turmas WHERE curso IS NOT NULL AND curso != '' ORDER BY curso")->fetchAll();
} catch (Exception $e) {}

// ===== BUSCAR PROFESSORES =====
$professores = [];
try {
    $professores = $pdo->query("SELECT id, nome FROM professores WHERE status = 'ativo' ORDER BY nome")->fetchAll();
} catch (Exception $e) {}


?>

<style>
    /* ===== ESTILOS GERAIS ===== */
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
    
    .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    /* ===== STATS CARDS ===== */
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
    
    .stat-card.ativas { border-left-color: #2ecc71; }
    .stat-card.concluidas { border-left-color: #3498db; }
    .stat-card.canceladas { border-left-color: #e74c3c; }
    .stat-card.total { border-left-color: #c9a84c; }
    .stat-card.emergencia { 
        border-left-color: #e74c3c;
        background: #fff5f5;
        animation: pulse-emergencia 2s infinite;
    }
    .stat-card.alerta { 
        border-left-color: #f39c12;
        background: #fffbeb;
    }
    
    @keyframes pulse-emergencia {
        0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(231, 76, 60, 0); }
        100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
    }
    
    /* ===== NAVEGAÇÃO ===== */
    .nav-turmas {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 25px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .nav-turmas a {
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
    
    .nav-turmas a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .nav-turmas a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    
    /* ===== TABELA ===== */
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
        font-size: 13px;
        min-width: 1100px;
    }
    
    .table th {
        background: #f8fafc;
        padding: 10px 12px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    
    .table tr:hover {
        background: #fafbfc;
    }
    
    .table .nome {
        font-weight: 600;
        color: #1a2332;
    }
    
    /* ===== STATUS BADGES ===== */
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-ativa {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-concluida {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .status-cancelada {
        background: #fee2e2;
        color: #991b1b;
    }
    
    /* ===== ALERTA DE VAGAS ===== */
    .alerta-emergencia {
        background: #fee2e2 !important;
        border-left: 4px solid #e74c3c !important;
        animation: alerta-pisca 1.5s ease-in-out infinite;
    }
    
    .alerta-emergencia td {
        background: #fee2e2 !important;
    }
    
    .alerta-emergencia .vagas-badge {
        background: #e74c3c !important;
        color: #fff !important;
        animation: pulse 1s ease-in-out infinite;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
    }
    
    .alerta-critico {
        background: #fffbeb !important;
        border-left: 4px solid #f39c12 !important;
    }
    
    .alerta-critico td {
        background: #fffbeb !important;
    }
    
    .alerta-critico .vagas-badge {
        background: #f39c12 !important;
        color: #fff !important;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
    }
    
    .alerta-atencao {
        background: #f0f9ff !important;
        border-left: 4px solid #3498db !important;
    }
    
    .alerta-atencao td {
        background: #f0f9ff !important;
    }
    
    .alerta-atencao .vagas-badge {
        background: #3498db !important;
        color: #fff !important;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
    }
    
    @keyframes alerta-pisca {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.85; background: #fecaca !important; }
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    /* ===== VAGAS BADGE ===== */
    .vagas-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .vagas-disponivel { background: #d1fae5; color: #065f46; }
    .vagas-lotado { background: #fee2e2; color: #991b1b; }
    .vagas-poucas { background: #fef3c7; color: #92400e; }
    .vagas-normal { background: #dbeafe; color: #1e40af; }
    
    /* ===== BARRA DE PROGRESSO ===== */
    .progress-bar-container {
        width: 100px;
        height: 8px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        display: inline-block;
        vertical-align: middle;
        margin-right: 5px;
    }
    
    .progress-bar {
        height: 100%;
        border-radius: 10px;
        transition: width 0.5s ease;
    }
    
    .progress-bar.emergencia { background: #e74c3c; }
    .progress-bar.critico { background: #f39c12; }
    .progress-bar.atencao { background: #3498db; }
    .progress-bar.normal { background: #2ecc71; }
    
    /* ===== ÍCONE DE ALERTA ===== */
    .alerta-icon {
        font-size: 18px;
        display: inline-block;
        animation: alerta-icon-pulse 1s ease-in-out infinite;
    }
    
    @keyframes alerta-icon-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.3); }
    }
    
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
    
    /* ===== TURNO BADGE ===== */
    .turno-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
    }
    
    .turno-manha { background: #fef3c7; color: #92400e; }
    .turno-tarde { background: #dbeafe; color: #1e40af; }
    .turno-noite { background: #e0e7ff; color: #3730a3; }
    .turno-integral { background: #d1fae5; color: #065f46; }
    
    .classe-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        background: #e0f2fe;
        color: #0369a1;
    }
    
    .idades-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        background: #f3e8ff;
        color: #6b21a8;
    }
    
    /* ===== BANNER DE EMERGÊNCIA ===== */
    .banner-emergencia {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: #fff;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: none;
        align-items: center;
        gap: 15px;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        animation: slideDown 0.5s ease;
    }
    
    .banner-emergencia.ativo {
        display: flex;
    }
    
    .banner-emergencia .icon {
        font-size: 30px;
    }
    
    .banner-emergencia .conteudo {
        flex: 1;
    }
    
    .banner-emergencia .titulo {
        font-size: 16px;
        font-weight: 700;
        margin: 0;
    }
    
    .banner-emergencia .descricao {
        font-size: 13px;
        opacity: 0.9;
        margin: 2px 0 0;
    }
    
    .banner-emergencia .contador {
        background: rgba(255,255,255,0.2);
        padding: 8px 20px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 20px;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .acoes-rapidas {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 20px;
        justify-content: center;
        padding: 15px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #eef2f7;
    }
    
    /* ===== FILTROS ===== */
    .filtros {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .filtros select, .filtros input {
        padding: 8px 15px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        background: white;
        outline: none;
        transition: all 0.3s;
    }
    
    .filtros select:focus, .filtros input:focus {
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    
    /* ===== RESPONSIVIDADE ===== */
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .actions {
            flex-direction: column;
        }
        .actions .btn {
            justify-content: center;
        }
        .nav-turmas {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-turmas a {
            text-align: center;
            justify-content: center;
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
            min-width: 750px;
        }
        .table th, .table td {
            padding: 6px 8px;
        }
        .btn-sm {
            font-size: 10px;
            padding: 3px 8px;
        }
        .acoes-rapidas {
            flex-direction: column;
            align-items: stretch;
        }
        .acoes-rapidas .btn {
            justify-content: center;
        }
        .progress-bar-container {
            width: 60px;
        }
        .banner-emergencia {
            flex-direction: column;
            text-align: center;
        }
        .filtros {
            flex-direction: column;
        }
        .filtros select, .filtros input {
            width: 100%;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ===== BANNER DE EMERGÊNCIA ===== -->
<div class="banner-emergencia <?= ($totalEmergencia > 0) ? 'ativo' : '' ?>">
    <span class="icon">🚨</span>
    <div class="conteudo">
        <p class="titulo">ALERTA DE EMERGÊNCIA - TURMAS LOTADAS</p>
        <p class="descricao">Existem <?= $totalEmergencia ?> turma(s) com capacidade esgotada. Ação imediata necessária!</p>
    </div>
    <div class="contador"><?= $totalEmergencia ?></div>
</div>

<div class="page-header">
    <div>
        <h1>🏫 Turmas</h1>
        <p class="subtitle">Gestão de turmas, classes e cursos</p>
    </div>
    <div class="actions">
        <a href="add.php" class="btn btn-primary">➕ Nova Turma</a>
        <a href="cursos.php" class="btn btn-info">📚 Cursos</a>
        <a href="vagas.php" class="btn btn-warning">📊 Verificar Vagas</a>
        <a href="relatorio.php" class="btn btn-success">📈 Relatório</a>
        <a href="../index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Navegação -->
<div class="nav-turmas">
    <a href="index.php" class="active">📋 Lista de Turmas</a>
    <a href="add.php">➕ Cadastrar Turma</a>
    <a href="cursos.php">📚 Gerenciar Cursos</a>
    <a href="vagas.php">📊 Verificar Vagas</a>
    <a href="relatorio.php">📈 Relatório</a>
    <a href="exportar.php">📤 Exportar</a>
</div>

<!-- Filtros -->
<div class="filtros">
    <select id="filtroAlerta" onchange="filtrarTurmas()">
        <option value="todos">📊 Todos os alertas</option>
        <option value="emergencia">🚨 Emergência (Lotado)</option>
        <option value="critico">⚠️ Crítico (≤ 3 vagas)</option>
        <option value="atencao">⚡ Atenção (≤ 5 vagas)</option>
        <option value="normal">✅ Normal</option>
    </select>
    <select id="filtroStatus" onchange="filtrarTurmas()">
        <option value="todos">📌 Todos os status</option>
        <option value="ativa">✅ Ativa</option>
        <option value="concluida">📌 Concluída</option>
        <option value="cancelada">❌ Cancelada</option>
    </select>
    <input type="text" id="filtroBusca" placeholder="🔍 Buscar turma..." onkeyup="filtrarTurmas()">
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card ativas">
        <span class="icon">✅</span>
        <div class="number"><?= $totalAtivas ?></div>
        <div class="label">Ativas</div>
    </div>
    <div class="stat-card concluidas">
        <span class="icon">📌</span>
        <div class="number"><?= $totalConcluidas ?></div>
        <div class="label">Concluídas</div>
    </div>
    <div class="stat-card canceladas">
        <span class="icon">❌</span>
        <div class="number"><?= $totalCanceladas ?></div>
        <div class="label">Canceladas</div>
    </div>
    <div class="stat-card total">
        <span class="icon">📊</span>
        <div class="number"><?= $totalTurmas ?></div>
        <div class="label">Total de Turmas</div>
    </div>
    <?php if ($totalEmergencia > 0): ?>
    <div class="stat-card emergencia">
        <span class="icon">🚨</span>
        <div class="number"><?= $totalEmergencia ?></div>
        <div class="label">⚠️ EMERGÊNCIA</div>
    </div>
    <?php endif; ?>
    <?php if ($totalAlerta > 0): ?>
    <div class="stat-card alerta">
        <span class="icon">⚠️</span>
        <div class="number"><?= $totalAlerta ?></div>
        <div class="label">Em Alerta</div>
    </div>
    <?php endif; ?>
</div>

<!-- Tabela -->
<div class="table-responsive">
    <table class="table" id="tabelaTurmas">
        <thead>
            <tr>
                <th>ID</th>
                <th>Turma</th>
                <th>Classe</th>
                <th>Curso</th>
                <th>Turno</th>
                <th>Sala</th>
                <th>Idades</th>
                <th>Alunos</th>
                <th>Vagas</th>
                <th>Ocupação</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($turmas) > 0): ?>
                <?php foreach($turmas as $t): 
                    $totalAlunos = $t['total_alunos'] ?? 0;
                    $limite = $t['limite'] ?? 30;
                    $vagas = $t['vagas_disponiveis'] ?? ($limite - $totalAlunos);
                    $percentual = $t['percentual_ocupacao'] ?? ($limite > 0 ? ($totalAlunos / $limite) * 100 : 0);
                    $alerta = $t['alerta'] ?? 'normal';
                    $alertaMsg = $t['alerta_msg'] ?? '✅ Vagas disponíveis';
                    
                    // Classe CSS para alerta
                    $classeAlerta = '';
                    $iconAlerta = '';
                    if ($alerta == 'emergencia') {
                        $classeAlerta = 'alerta-emergencia';
                        $iconAlerta = '🚨';
                    } elseif ($alerta == 'critico') {
                        $classeAlerta = 'alerta-critico';
                        $iconAlerta = '⚠️';
                    } elseif ($alerta == 'atencao') {
                        $classeAlerta = 'alerta-atencao';
                        $iconAlerta = '⚡';
                    }
                    
                    // Classe da barra de progresso
                    $progressClass = $alerta;
                    
                    // Label das vagas
                    $vagasLabel = $vagas > 5 ? '✅ ' . $vagas . ' vagas' : ($vagas > 0 ? '⚠️ ' . $vagas . ' vagas' : '🚨 LOTADO');
                    $vagasClass = $vagas > 5 ? 'vagas-disponivel' : ($vagas > 0 ? 'vagas-poucas' : 'vagas-lotado');
                ?>
                <tr class="<?= $classeAlerta ?>" data-alerta="<?= $alerta ?>" data-status="<?= $t['status'] ?? 'ativa' ?>" data-nome="<?= strtolower($t['nome']) ?>">
                    <td><strong><?= $t['id'] ?></strong></td>
                    <td class="nome">
                        <?php if ($iconAlerta): ?>
                            <span class="alerta-icon"><?= $iconAlerta ?></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($t['nome']) ?>
                    </td>
                    <td><span class="classe-badge"><?= htmlspecialchars($t['classe'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($t['curso'] ?? '-') ?></td>
                    <td><span class="turno-badge turno-<?= $t['turno'] ?? 'manha' ?>"><?= ucfirst($t['turno'] ?? 'Manhã') ?></span></td>
                    <td><?= htmlspecialchars($t['sala'] ?? '-') ?></td>
                    <td><span class="idades-badge"><?= htmlspecialchars($t['idades'] ?? '-') ?></span></td>
                    <td><strong><?= $totalAlunos ?></strong></td>
                    <td>
                        <span class="vagas-badge <?= $vagasClass ?>">
                            <?= $vagasLabel ?>
                        </span>
                    </td>
                    <td>
                        <div class="progress-bar-container">
                            <div class="progress-bar <?= $progressClass ?>" style="width: <?= $percentual ?>%;"></div>
                        </div>
                        <span style="font-size: 11px; color: #4a5568;"><?= $percentual ?>%</span>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $t['status'] ?? 'ativa' ?>">
                            <?= ucfirst($t['status'] ?? 'ativa') ?>
                        </span>
                        <?php if ($alerta != 'normal'): ?>
                            <br><small style="color: <?= $alerta == 'emergencia' ? '#e74c3c' : ($alerta == 'critico' ? '#f39c12' : '#3498db') ?>; font-weight: 600;">
                                <?= $alertaMsg ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-info">Visualizar</a>
                            <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                            <a href="delete.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta turma?')">Excluir</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhuma turma cadastrada</h3>
                            <p>Clique em "Nova Turma" para começar a cadastrar.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações rápidas:</span>
    <a href="add.php" class="btn btn-primary">➕ Nova Turma</a>
    <a href="cursos.php" class="btn btn-info">📚 Gerenciar Cursos</a>
    <a href="vagas.php" class="btn btn-warning">📊 Verificar Vagas</a>
    <a href="relatorio.php" class="btn btn-success">📈 Gerar Relatório</a>
    <a href="exportar.php" class="btn btn-secondary">📤 Exportar Dados</a>
</div>

<script>
// ===== FUNÇÃO DE FILTRAGEM =====
function filtrarTurmas() {
    const filtroAlerta = document.getElementById('filtroAlerta').value;
    const filtroStatus = document.getElementById('filtroStatus').value;
    const filtroBusca = document.getElementById('filtroBusca').value.toLowerCase();
    const linhas = document.querySelectorAll('#tabelaTurmas tbody tr');
    
    linhas.forEach(linha => {
        const alerta = linha.dataset.alerta || 'normal';
        const status = linha.dataset.status || 'ativa';
        const nome = linha.dataset.nome || '';
        
        let mostrar = true;
        
        // Filtrar por alerta
        if (filtroAlerta !== 'todos' && alerta !== filtroAlerta) {
            mostrar = false;
        }
        
        // Filtrar por status
        if (filtroStatus !== 'todos' && status !== filtroStatus) {
            mostrar = false;
        }
        
        // Filtrar por busca
        if (filtroBusca && !nome.includes(filtroBusca)) {
            mostrar = false;
        }
        
        linha.style.display = mostrar ? '' : 'none';
    });
}

// ===== ATUALIZAR CONTAGEM VISÍVEL =====
function atualizarContagem() {
    const linhasVisiveis = document.querySelectorAll('#tabelaTurmas tbody tr[style*="display: none"]');
    // Não é necessário fazer nada, apenas mantemos a funcionalidade
}

// ===== DESTAQUE DE EMERGÊNCIA =====
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se há turmas em emergência e destacar
    const emergencias = document.querySelectorAll('.alerta-emergencia');
    if (emergencias.length > 0) {
        console.log('🚨 ATENÇÃO: ' + emergencias.length + ' turma(s) em estado de EMERGÊNCIA!');
        
        // Criar notificação visual
        const notificacao = document.createElement('div');
        notificacao.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #e74c3c;
            color: #fff;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(231, 76, 60, 0.4);
            z-index: 9999;
            font-weight: 600;
            animation: slideDown 0.5s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        `;
        notificacao.innerHTML = `
            <span style="font-size: 24px;">🚨</span>
            <div>
                <div style="font-weight: 700;">EMERGÊNCIA</div>
                <div style="font-size: 13px; opacity: 0.9;">${emergencias.length} turma(s) lotada(s)</div>
            </div>
        `;
        notificacao.onclick = function() {
            document.querySelector('.alerta-emergencia').scrollIntoView({ behavior: 'smooth' });
        };
        document.body.appendChild(notificacao);
        
        // Remover após 10 segundos
        setTimeout(() => {
            notificacao.style.opacity = '0';
            notificacao.style.transition = 'opacity 0.5s';
            setTimeout(() => notificacao.remove(), 500);
        }, 10000);
    }
});
</script>

<?php include '../includes/footer_escola.php'; ?>