<?php
// ============================================
// modules/escola/alunos/index.php - Gestão de Alunos
// ============================================

require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para VISUALIZAR
bloquearAcesso('visualizar');

// Resto do código...
?>



<?php
// ============================================
// modules/escola/alunos/listas_nominais.php - Listas Nominais
// ============================================

// ============================================
// 1. CARREGAR CONFIGURAÇÕES OBRIGATÓRIAS
// ============================================
require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';

// ============================================
// 2. INICIAR SESSÃO
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 3. VERIFICAR LOGIN
// ============================================
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../../../login.php');
    exit;
}

// ============================================
// 4. 🔒 VERIFICA PERMISSÃO PARA VISUALIZAR
// ============================================
bloquearAcesso('visualizar');

// ============================================
// 5. CONECTAR AO BANCO E BUSCAR DADOS
// ============================================
$alunos = [];
$pdo = null;

try {
    // 🔑 CONECTA AO BANCO
    $pdo = conectarBanco();
    
    // Verifica se a tabela alunos existe
    $check = $pdo->query("SHOW TABLES LIKE 'alunos'");
    if ($check->rowCount() > 0) {
        // Busca alunos ativos
        $alunos = $pdo->query("
            SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
                   Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro
            FROM alunos 
            WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
            ORDER BY Classe, TURMA, nome
        ")->fetchAll();
    }
} catch (Exception $e) {
    // Se houver erro, apenas continua com array vazio
    error_log("Erro ao buscar alunos: " . $e->getMessage());
}

// ============================================
// 6. AGRUPAR POR CLASSE E TURMA
// ============================================
$grupos = [];
foreach ($alunos as $aluno) {
    $classe = $aluno['Classe'] ?? 'Sem Classe';
    $turma = $aluno['TURMA'] ?? 'Sem Turma';
    $sala = $aluno['SALA'] ?? 'Sem Sala';
    $key = $classe . '|' . $turma . '|' . $sala;
    
    if (!isset($grupos[$key])) {
        $grupos[$key] = [
            'classe' => $classe,
            'turma' => $turma,
            'sala' => $sala,
            'periodo' => $aluno['Periodo'] ?? 'Manhã',
            'alunos' => []
        ];
    }
    $grupos[$key]['alunos'][] = $aluno;
}

// ============================================
// 7. AGRUPAR POR CLASSE PARA ESTATÍSTICAS
// ============================================
$classes = [];
foreach ($alunos as $aluno) {
    $classe = $aluno['Classe'] ?? 'Sem Classe';
    if (!isset($classes[$classe])) {
        $classes[$classe] = 0;
    }
    $classes[$classe]++;
}

// ============================================
// 8. INCLUIR HEADER
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
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .btn-info:hover {
        background: #2980b9;
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
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 11px;
        border-radius: 6px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
    
    .stat-card.total { border-left-color: #3498db; }
    .stat-card.turmas { border-left-color: #f39c12; }
    .stat-card.classes { border-left-color: #2ecc71; }
    
    .nav-alunos {
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
    
    .nav-alunos a {
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
    
    .nav-alunos a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .nav-alunos a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    
    .listas-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .lista-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        transition: all 0.3s;
    }
    
    .lista-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    }
    
    .lista-card .header-lista {
        border-bottom: 2px solid #c9a84c;
        padding-bottom: 10px;
        margin-bottom: 12px;
    }
    
    .lista-card .header-lista .classe {
        font-size: 18px;
        font-weight: 700;
        color: #1a2332;
    }
    
    .lista-card .header-lista .turma {
        font-size: 14px;
        color: #c9a84c;
        font-weight: 600;
    }
    
    .lista-card .header-lista .info {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 2px;
    }
    
    .lista-card .aluno-item {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    
    .lista-card .aluno-item:last-child {
        border-bottom: none;
    }
    
    .lista-card .aluno-item .nome {
        font-weight: 500;
        color: #1a2332;
    }
    
    .lista-card .aluno-item .info-aluno {
        color: #94a3b8;
        font-size: 12px;
    }
    
    .lista-card .total {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #eef2f7;
        text-align: center;
        font-size: 13px;
        color: #4a5568;
    }
    
    .lista-card .total strong {
        color: #c9a84c;
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
    
    .badge-sexo {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
    }
    
    .badge-m {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .badge-f {
        background: #fce7f3;
        color: #9d174d;
    }
    
    .download-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    
    .download-actions .btn-sm {
        font-size: 10px;
        padding: 3px 10px;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-alunos {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-alunos a {
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
        .listas-grid {
            grid-template-columns: 1fr;
        }
        .acoes-rapidas {
            flex-direction: column;
            align-items: stretch;
        }
        .acoes-rapidas .btn {
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📋 Listas Nominais</h1>
        <p class="subtitle">Visualização de alunos agrupados por classe e turma</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Navegação -->
<div class="nav-alunos">
    <a href="index.php">📋 Lista de Alunos</a>
    <a href="add.php">➕ Cadastrar Aluno</a>
    <a href="reconfirmar.php">🔄 Reconfirmação</a>
    <a href="consulta.php">🔍 Consulta</a>
    <a href="relatorio.php">📈 Relatório</a>
    <a href="relatorio_idade.php">📊 Relatório por Idade</a>
    <a href="listas_nominais.php" class="active">📋 Listas Nominais</a>
    <a href="listas_pagamento_transporte.php" class="active">💰 Pagamentos Transporte</a>
                         
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card total">
        <span class="icon">👨‍🎓</span>
        <div class="number"><?= count($alunos) ?></div>
        <div class="label">Total de Alunos</div>
    </div>
    <div class="stat-card turmas">
        <span class="icon">🏫</span>
        <div class="number"><?= count($grupos) ?></div>
        <div class="label">Turmas</div>
    </div>
    <div class="stat-card classes">
        <span class="icon">📚</span>
        <div class="number"><?= count($classes) ?></div>
        <div class="label">Classes</div>
    </div>
</div>

<!-- Listas -->
<div class="listas-grid">
    <?php if (count($grupos) > 0): ?>
        <?php foreach($grupos as $grupo): 
            $classe = $grupo['classe'];
            $turma = $grupo['turma'];
            $sala = $grupo['sala'];
            $periodo = $grupo['periodo'];
            $alunos_lista = $grupo['alunos'];
            $total = count($alunos_lista);
        ?>
        <div class="lista-card">
            <div class="header-lista">
                <div class="classe"><?= htmlspecialchars($classe) ?></div>
                <div class="turma">Turma <?= htmlspecialchars($turma) ?></div>
                <div class="info">
                    Sala: <?= htmlspecialchars($sala) ?> | Período: <?= htmlspecialchars($periodo) ?>
                </div>
            </div>
            
            <?php 
            $num = 1;
            foreach($alunos_lista as $aluno): 
            ?>
            <div class="aluno-item">
                <span class="nome">
                    <?= $num ?>. <?= htmlspecialchars($aluno['nome']) ?>
                    <span class="badge-sexo badge-<?= strtolower($aluno['Sexo'] ?? 'm') ?>">
                        <?= $aluno['Sexo'] ?? 'M' ?>
                    </span>
                </span>
                <span class="info-aluno">
                    <?= $aluno['Idade'] ?? '-' ?> anos | 
                    <?= isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0 ? 
                        sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-' ?>
                </span>
            </div>
            <?php $num++; endforeach; ?>
            
            <div class="total">
                Total: <strong><?= $total ?></strong> alunos
            </div>
            
            <div class="download-actions">
                <a href="gerar_html_classe.php?classe=<?= urlencode($classe) ?>&turma=<?= urlencode($turma) ?>" class="btn btn-sm btn-info" target="_blank">🌐 HTML</a>
                <a href="gerar_excel_classe.php?classe=<?= urlencode($classe) ?>&turma=<?= urlencode($turma) ?>" class="btn btn-sm btn-success">📊 Excel</a>
                <a href="imprimir_classe.php?classe=<?= urlencode($classe) ?>&turma=<?= urlencode($turma) ?>" class="btn btn-sm btn-warning" target="_blank">🖨️ Imprimir</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="grid-column: 1 / -1;">
            <div class="empty-state">
                <span class="icon">📭</span>
                <h3>Nenhum aluno cadastrado</h3>
                <p>Cadastre alunos para visualizar as listas nominais.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações:</span>
    <a href="gerar_todas_html.php" class="btn btn-primary">🌐 Gerar Todas HTML</a>
    <a href="gerar_todas_excel.php" class="btn btn-success">📊 Gerar Todas Excel</a>
    <a href="imprimir_todas.php" class="btn btn-info">🖨️ Imprimir Todas</a>
</div>

<?php include '../includes/footer_escola.php'; ?>