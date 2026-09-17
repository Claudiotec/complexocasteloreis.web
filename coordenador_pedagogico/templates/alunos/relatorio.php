<?php
// ============================================
// modules/escola/alunos/relatorio.php - Relatório de Alunos
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

// ===== DADOS =====
$totalAlunos = 0;
$totalMatriculados = 0;
$totalConfirmados = 0;
$totalMasculino = 0;
$totalFeminino = 0;

try {
    $totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos")->fetchColumn() ?? 0;
    $totalMatriculados = $pdo->query("SELECT COUNT(*) FROM alunos WHERE Situacao_Cadastro = 'Matrícula'")->fetchColumn() ?? 0;
    $totalConfirmados = $pdo->query("SELECT COUNT(*) FROM alunos WHERE Situacao_Cadastro = 'Confirmação'")->fetchColumn() ?? 0;
    $totalMasculino = $pdo->query("SELECT COUNT(*) FROM alunos WHERE Sexo = 'M'")->fetchColumn() ?? 0;
    $totalFeminino = $pdo->query("SELECT COUNT(*) FROM alunos WHERE Sexo = 'F'")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// ===== ALUNOS POR CLASSE =====
$alunosPorClasse = [];
try {
    $alunosPorClasse = $pdo->query("
        SELECT Classe, COUNT(*) as total 
        FROM alunos 
        GROUP BY Classe 
        ORDER BY Classe
    ")->fetchAll();
} catch (Exception $e) {}

// ===== ALUNOS POR CURSO =====
$alunosPorCurso = [];
try {
    $alunosPorCurso = $pdo->query("
        SELECT Curso, COUNT(*) as total 
        FROM alunos 
        GROUP BY Curso 
        ORDER BY Curso
    ")->fetchAll();
} catch (Exception $e) {}

// ===== ALUNOS POR TURMA =====
$alunosPorTurma = [];
try {
    $alunosPorTurma = $pdo->query("
        SELECT TURMA, COUNT(*) as total 
        FROM alunos 
        WHERE TURMA IS NOT NULL AND TURMA != ''
        GROUP BY TURMA 
        ORDER BY TURMA
    ")->fetchAll();
} catch (Exception $e) {}

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
        box-shadow: 0 4px 15px rgba(201,168,76,0.3);
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
    .stat-card.matriculados { border-left-color: #2ecc71; }
    .stat-card.confirmados { border-left-color: #f39c12; }
    .stat-card.masculino { border-left-color: #1e40af; }
    .stat-card.feminino { border-left-color: #9d174d; }
    
    .report-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }
    
    .report-card {
        background: white;
        border-radius: 12px;
        padding: 18px 22px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .report-card h3 {
        font-size: 16px;
        font-weight: 700;
        color: #1a2332;
        margin: 0 0 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #eef2f7;
    }
    
    .report-card .item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    
    .report-card .item:last-child {
        border-bottom: none;
    }
    
    .report-card .item .label {
        color: #4a5568;
    }
    
    .report-card .item .value {
        font-weight: 600;
        color: #1a2332;
    }
    
    .report-card .item .barra {
        flex: 1;
        margin: 0 10px;
        background: #eef2f7;
        border-radius: 4px;
        height: 8px;
        overflow: hidden;
        align-self: center;
    }
    
    .report-card .item .barra .fill {
        height: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, #c9a84c, #f5d76e);
        transition: width 0.5s;
    }
    
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
        .report-grid {
            grid-template-columns: 1fr;
            gap: 15px;
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
        <h1>📈 Relatório de Alunos</h1>
        <p class="subtitle">Visão geral e estatísticas dos alunos</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Navegação -->
<div class="nav-alunos">
    <a href="index.php">📋 Lista de Alunos</a>
    <a href="add.php">➕ Cadastrar Aluno</a>
    <a href="reconfirmar.php">🔄 Reconfirmação</a>
    <a href="consulta.php">🔍 Consulta</a>
    <a href="relatorio.php" class="active">📈 Relatório</a>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card total">
        <span class="icon">👨‍🎓</span>
        <div class="number"><?= $totalAlunos ?></div>
        <div class="label">Total de Alunos</div>
    </div>
    <div class="stat-card matriculados">
        <span class="icon">📝</span>
        <div class="number"><?= $totalMatriculados ?></div>
        <div class="label">Matriculados</div>
    </div>
    <div class="stat-card confirmados">
        <span class="icon">✅</span>
        <div class="number"><?= $totalConfirmados ?></div>
        <div class="label">Confirmados</div>
    </div>
    <div class="stat-card masculino">
        <span class="icon">👨</span>
        <div class="number"><?= $totalMasculino ?></div>
        <div class="label">Masculino</div>
    </div>
    <div class="stat-card feminino">
        <span class="icon">👩</span>
        <div class="number"><?= $totalFeminino ?></div>
        <div class="label">Feminino</div>
    </div>
</div>

<!-- Relatórios -->
<div class="report-grid">
    <!-- Por Classe -->
    <div class="report-card">
        <h3>📊 Alunos por Classe</h3>
        <?php if (count($alunosPorClasse) > 0): ?>
            <?php foreach($alunosPorClasse as $item): 
                $percentual = $totalAlunos > 0 ? round(($item['total'] / $totalAlunos) * 100) : 0;
            ?>
            <div class="item">
                <span class="label"><?= htmlspecialchars($item['Classe'] ?? 'N/A') ?></span>
                <div class="barra">
                    <div class="fill" style="width: <?= $percentual ?>%;"></div>
                </div>
                <span class="value"><?= $item['total'] ?></span>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #94a3b8; text-align: center; padding: 20px;">Nenhum dado disponível</p>
        <?php endif; ?>
    </div>

    <!-- Por Curso -->
    <div class="report-card">
        <h3>📚 Alunos por Curso</h3>
        <?php if (count($alunosPorCurso) > 0): ?>
            <?php foreach($alunosPorCurso as $item): 
                $percentual = $totalAlunos > 0 ? round(($item['total'] / $totalAlunos) * 100) : 0;
            ?>
            <div class="item">
                <span class="label"><?= htmlspecialchars($item['Curso'] ?? 'N/A') ?></span>
                <div class="barra">
                    <div class="fill" style="width: <?= $percentual ?>%;"></div>
                </div>
                <span class="value"><?= $item['total'] ?></span>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #94a3b8; text-align: center; padding: 20px;">Nenhum dado disponível</p>
        <?php endif; ?>
    </div>

    <!-- Por Turma -->
    <div class="report-card">
        <h3>🏫 Alunos por Turma</h3>
        <?php if (count($alunosPorTurma) > 0): ?>
            <?php foreach($alunosPorTurma as $item): 
                $percentual = $totalAlunos > 0 ? round(($item['total'] / $totalAlunos) * 100) : 0;
            ?>
            <div class="item">
                <span class="label"><?= htmlspecialchars($item['TURMA'] ?? 'N/A') ?></span>
                <div class="barra">
                    <div class="fill" style="width: <?= $percentual ?>%;"></div>
                </div>
                <span class="value"><?= $item['total'] ?></span>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #94a3b8; text-align: center; padding: 20px;">Nenhum dado disponível</p>
        <?php endif; ?>
    </div>

    <!-- Resumo -->
    <div class="report-card">
        <h3>📌 Resumo Geral</h3>
        <div class="item">
            <span class="label">Total de Alunos</span>
            <span class="value"><?= $totalAlunos ?></span>
        </div>
        <div class="item">
            <span class="label">Matriculados</span>
            <span class="value"><?= $totalMatriculados ?> (<?= $totalAlunos > 0 ? round(($totalMatriculados/$totalAlunos)*100) : 0 ?>%)</span>
        </div>
        <div class="item">
            <span class="label">Confirmados</span>
            <span class="value"><?= $totalConfirmados ?> (<?= $totalAlunos > 0 ? round(($totalConfirmados/$totalAlunos)*100) : 0 ?>%)</span>
        </div>
        <div class="item">
            <span class="label">Masculino</span>
            <span class="value"><?= $totalMasculino ?> (<?= $totalAlunos > 0 ? round(($totalMasculino/$totalAlunos)*100) : 0 ?>%)</span>
        </div>
        <div class="item">
            <span class="label">Feminino</span>
            <span class="value"><?= $totalFeminino ?> (<?= $totalAlunos > 0 ? round(($totalFeminino/$totalAlunos)*100) : 0 ?>%)</span>
        </div>
        <div class="item">
            <span class="label">Turmas</span>
            <span class="value"><?= count($alunosPorTurma) ?></span>
        </div>
        <div class="item">
            <span class="label">Classes</span>
            <span class="value"><?= count($alunosPorClasse) ?></span>
        </div>
    </div>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações:</span>
    <a href="exportar.php" class="btn btn-success">📤 Exportar Relatório</a>
    <a href="imprimir_relatorio.php" class="btn btn-info">🖨️ Imprimir</a>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php include '../includes/footer_escola.php'; ?>