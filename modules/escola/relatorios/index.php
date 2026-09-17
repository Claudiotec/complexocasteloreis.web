<?php
// ============================================
// modules/escola/relatorios/index.php - Relatórios Escolares
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

// ===== DADOS PARA RELATÓRIOS =====
$totalAlunos = 0;
$totalProfessores = 0;
$totalTurmas = 0;
$totalDisciplinas = 0;
$totalMatriculas = 0;
$totalFrequencias = 0;
$totalNotas = 0;

try {
    $totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos WHERE status = 'ativo'")->fetchColumn() ?? 0;
    $totalProfessores = $pdo->query("SELECT COUNT(*) FROM professores WHERE status = 'ativo'")->fetchColumn() ?? 0;
    $totalTurmas = $pdo->query("SELECT COUNT(*) FROM turmas WHERE status = 'ativa'")->fetchColumn() ?? 0;
    $totalDisciplinas = $pdo->query("SELECT COUNT(*) FROM disciplinas WHERE status = 'ativa'")->fetchColumn() ?? 0;
    $totalMatriculas = $pdo->query("SELECT COUNT(*) FROM matriculas WHERE status = 'ativa'")->fetchColumn() ?? 0;
    $totalFrequencias = $pdo->query("SELECT COUNT(*) FROM frequencia")->fetchColumn() ?? 0;
    $totalNotas = $pdo->query("SELECT COUNT(*) FROM notas")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// ===== ALUNOS POR TURMA =====
$alunosPorTurma = [];
try {
    // CORREÇÃO: Usar a coluna TURMA da tabela alunos em vez de matriculas
    $alunosPorTurma = $pdo->query("
        SELECT t.nome, COUNT(a.id) as total 
        FROM turmas t 
        LEFT JOIN alunos a ON t.nome = a.TURMA AND a.status = 'ativo'
        WHERE t.status = 'ativa'
        GROUP BY t.id, t.nome
        ORDER BY t.nome
    ")->fetchAll();
} catch (Exception $e) {
    // Fallback: Se a consulta acima falhar, usa apenas a tabela alunos
    try {
        $alunosPorTurma = $pdo->query("
            SELECT TURMA as nome, COUNT(*) as total 
            FROM alunos 
            WHERE status = 'ativo' AND TURMA IS NOT NULL AND TURMA != ''
            GROUP BY TURMA
            ORDER BY TURMA
        ")->fetchAll();
    } catch (Exception $e2) {}
}

// ===== FREQUENCIA POR STATUS =====
$frequenciaStatus = [];
try {
    $frequenciaStatus = $pdo->query("
        SELECT status, COUNT(*) as total 
        FROM frequencia 
        GROUP BY status
    ")->fetchAll();
} catch (Exception $e) {}

// ===== NOTAS POR RESULTADO =====
$notasResultado = [];
try {
    $notasResultado = $pdo->query("
        SELECT resultado, COUNT(*) as total 
        FROM notas 
        GROUP BY resultado
    ")->fetchAll();
} catch (Exception $e) {}

// ===== INCLUIR HEADER =====
include '../includes/header_escola.php';
?>

<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
    .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
    .page-header .subtitle { color: #94a3b8; font-size: 14px; margin: 2px 0 0; }
    .btn { padding: 8px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(201,168,76,0.3); }
    .btn-success { background: #2ecc71; color: #fff; }
    .btn-success:hover { background: #27ae60; }
    .btn-info { background: #3498db; color: #fff; }
    .btn-info:hover { background: #2980b9; }
    .btn-warning { background: #f39c12; color: #fff; }
    .btn-warning:hover { background: #d68910; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; padding: 18px 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; border-left: 4px solid #c9a84c; transition: all 0.3s; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .stat-card .number { font-size: 26px; font-weight: 700; color: #1a2332; margin: 0; }
    .stat-card .label { font-size: 12px; color: #94a3b8; margin: 3px 0 0; }
    .stat-card .icon { font-size: 24px; display: block; margin-bottom: 5px; }
    .stat-card.alunos { border-left-color: #9b59b6; }
    .stat-card.professores { border-left-color: #2ecc71; }
    .stat-card.turmas { border-left-color: #f39c12; }
    .stat-card.disciplinas { border-left-color: #3498db; }
    .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
    .report-card { background: white; border-radius: 12px; padding: 20px 24px; border: 1px solid #eef2f7; box-shadow: 0 2px 10px rgba(0,0,0,0.04); transition: all 0.3s; cursor: pointer; }
    .report-card:hover { transform: translateY(-5px); box-shadow: 0 10px 40px rgba(0,0,0,0.08); }
    .report-card .icon { font-size: 36px; display: block; margin-bottom: 10px; }
    .report-card h3 { font-size: 16px; color: #1a2332; margin: 0 0 5px; font-weight: 600; }
    .report-card p { font-size: 13px; color: #94a3b8; margin: 0 0 12px; }
    .report-card .badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(201,168,76,0.1); color: #c9a84c; }
    .mini-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
    .mini-item { background: #f8fafc; padding: 10px 12px; border-radius: 8px; text-align: center; }
    .mini-item .value { font-size: 18px; font-weight: 700; color: #1a2332; }
    .mini-item .label { font-size: 11px; color: #94a3b8; }
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; margin-top: 20px; }
    .table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 400px; }
    .table th { background: #f8fafc; padding: 10px 14px; text-align: left; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
    .table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }
    .table tr:hover { background: #fafbfc; }
    .status-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
    .status-presente { background: #d1fae5; color: #065f46; }
    .status-ausente { background: #fee2e2; color: #991b1b; }
    .status-justificado { background: #fef3c7; color: #92400e; }
    .status-atrasado { background: #dbeafe; color: #1e40af; }
    .status-aprovado { background: #d1fae5; color: #065f46; }
    .status-reprovado { background: #fee2e2; color: #991b1b; }
    .status-recuperacao { background: #fef3c7; color: #92400e; }
    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: stretch; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card { padding: 14px 16px; }
        .stat-card .number { font-size: 22px; }
        .report-grid { grid-template-columns: 1fr; }
        .mini-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
    
    /* Estilos para turmas sem alunos */
    .turma-vazia { color: #94a3b8; }
    .turma-com-alunos { color: #1a2332; font-weight: 600; }
    .total-alunos { font-weight: 700; color: #c9a84c; }
    
    /* Badge de total geral */
    .total-geral { 
        background: #1a2332; 
        color: white; 
        padding: 4px 12px; 
        border-radius: 20px; 
        font-size: 12px;
        font-weight: 600;
    }
</style>

<div class="page-header">
    <div>
        <h1>📈 Relatórios Escolares</h1>
        <p class="subtitle">Análise e visualização de dados acadêmicos</p>
    </div>
    <a href="../index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card alunos">
        <span class="icon">👨‍🎓</span>
        <div class="number"><?= $totalAlunos ?></div>
        <div class="label">Alunos Ativos</div>
    </div>
    <div class="stat-card professores">
        <span class="icon">👨‍🏫</span>
        <div class="number"><?= $totalProfessores ?></div>
        <div class="label">Professores</div>
    </div>
    <div class="stat-card turmas">
        <span class="icon">🏫</span>
        <div class="number"><?= $totalTurmas ?></div>
        <div class="label">Turmas Ativas</div>
    </div>
    <div class="stat-card disciplinas">
        <span class="icon">📚</span>
        <div class="number"><?= $totalDisciplinas ?></div>
        <div class="label">Disciplinas</div>
    </div>
</div>

<!-- Cards de Relatórios -->
<div class="report-grid">
    <div class="report-card" onclick="window.location.href='alunos.php'">
        <span class="icon">👨‍🎓</span>
        <h3>Relatório de Alunos</h3>
        <p>Lista completa de alunos com filtros</p>
        <span class="badge"><?= $totalAlunos ?> registros</span>
    </div>
    
    <div class="report-card" onclick="window.location.href='frequencia.php'">
        <span class="icon">✅</span>
        <h3>Relatório de Frequência</h3>
        <p>Análise de presenças e ausências</p>
        <span class="badge"><?= $totalFrequencias ?> registros</span>
        <div class="mini-grid">
            <?php if (!empty($frequenciaStatus)): ?>
                <?php foreach($frequenciaStatus as $fs): ?>
                <div class="mini-item">
                    <div class="value"><?= $fs['total'] ?></div>
                    <div class="label"><?= ucfirst($fs['status']) ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="mini-item" style="grid-column: 1 / -1;">
                    <div class="value" style="font-size: 14px; color: #94a3b8;">Sem dados</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="report-card" onclick="window.location.href='notas.php'">
        <span class="icon">📊</span>
        <h3>Relatório de Notas</h3>
        <p>Desempenho dos alunos por disciplina</p>
        <span class="badge"><?= $totalNotas ?> registros</span>
        <div class="mini-grid">
            <?php if (!empty($notasResultado)): ?>
                <?php foreach($notasResultado as $nr): ?>
                <div class="mini-item">
                    <div class="value"><?= $nr['total'] ?></div>
                    <div class="label"><?= ucfirst($nr['resultado']) ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="mini-item" style="grid-column: 1 / -1;">
                    <div class="value" style="font-size: 14px; color: #94a3b8;">Sem dados</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="report-card" onclick="window.location.href='turmas.php'">
        <span class="icon">🏫</span>
        <h3>Relatório por Turma</h3>
        <p>Distribuição de alunos por turma</p>
        <span class="badge"><?= count($alunosPorTurma) ?> turmas</span>
        <div style="margin-top: 8px; max-height: 200px; overflow-y: auto;">
            <?php if (!empty($alunosPorTurma)): ?>
                <?php foreach($alunosPorTurma as $at): ?>
                <div style="display: flex; justify-content: space-between; font-size: 12px; padding: 3px 0; border-bottom: 1px solid #f1f5f9;">
                    <span><?= htmlspecialchars($at['nome']) ?></span>
                    <span><strong><?= $at['total'] ?></strong> alunos</span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 10px; color: #94a3b8; font-size: 13px;">
                    Nenhuma turma cadastrada
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabela de Alunos por Turma -->
<div class="table-responsive">
    <h3 style="padding: 16px 16px 0; color: #1a2332; font-size: 16px;">
        📊 Distribuição de Alunos por Turma
        <span style="float: right; font-size: 13px; font-weight: normal; color: #94a3b8;">
            Total: <?= array_sum(array_column($alunosPorTurma, 'total')) ?> alunos
        </span>
    </h3>
    <table class="table">
        <thead>
            <tr>
                <th>Turma</th>
                <th>Total de Alunos</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($alunosPorTurma)): ?>
                <?php 
                $totalGeral = 0;
                foreach($alunosPorTurma as $at): 
                    $totalGeral += $at['total'];
                    $temAlunos = $at['total'] > 0;
                ?>
                <tr>
                    <td>
                        <strong class="<?= $temAlunos ? 'turma-com-alunos' : 'turma-vazia' ?>">
                            <?= htmlspecialchars($at['nome']) ?>
                        </strong>
                    </td>
                    <td>
                        <span class="<?= $temAlunos ? 'total-alunos' : 'turma-vazia' ?>">
                            <?= $at['total'] ?> alunos
                        </span>
                    </td>
                    <td>
                        <?php if ($temAlunos): ?>
                            <span class="status-badge status-presente">✅ Ativa</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: #f1f5f9; color: #94a3b8;">⚪ Vazia</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <!-- Linha de total -->
                <tr style="background: #f8fafc; font-weight: 700;">
                    <td style="border-top: 2px solid #c9a84c;">📌 TOTAL GERAL</td>
                    <td style="border-top: 2px solid #c9a84c; color: #c9a84c; font-size: 16px;">
                        <?= $totalGeral ?> alunos
                    </td>
                    <td style="border-top: 2px solid #c9a84c;">
                        <span class="total-geral"><?= count($alunosPorTurma) ?> turmas</span>
                    </td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center; padding: 40px; color: #94a3b8;">
                        <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                        Nenhuma turma cadastrada ou alunos matriculados
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Rodapé com informações adicionais -->
<div style="display: flex; justify-content: space-between; margin-top: 20px; padding: 15px 0; border-top: 1px solid #eef2f7; font-size: 12px; color: #94a3b8; flex-wrap: wrap; gap: 10px;">
    <div>
        📊 Última atualização: <?= date('d/m/Y H:i:s') ?>
    </div>
    <div>
        🏫 <?= $totalTurmas ?> turmas | 👨‍🎓 <?= $totalAlunos ?> alunos | 📚 <?= $totalDisciplinas ?> disciplinas
    </div>
    <div>
        💾 Sistema v1.0
    </div>
</div>

<?php include '../includes/footer_escola.php'; ?>