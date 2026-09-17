<?php
// ============================================
// modules/escola/frequencia/relatorio.php - Relatório de Frequência
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

// ===== FILTROS =====
$turma_id = $_GET['turma_id'] ?? null;
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');

// ===== BUSCAR TURMAS =====
$turmas = [];
try {
    $turmas = $pdo->query("SELECT id, nome, classe FROM turmas WHERE status = 'ativa' ORDER BY classe, nome")->fetchAll();
} catch (Exception $e) {}

// ===== BUSCAR DADOS =====
$alunos = [];
$frequencias = [];
$total_presentes = 0;
$total_ausentes = 0;
$total_justificados = 0;
$total_atrasados = 0;

if ($turma_id) {
    try {
        // Buscar alunos da turma
        $stmt = $pdo->prepare("
            SELECT m.id as matricula_id, a.id as aluno_id, a.nome
            FROM matriculas m
            LEFT JOIN alunos a ON m.aluno_id = a.id
            WHERE m.turma_id = ? AND m.status = 'ativa'
            ORDER BY a.nome
        ");
        $stmt->execute([$turma_id]);
        $alunos = $stmt->fetchAll();
        
        // Buscar frequências
        foreach ($alunos as &$aluno) {
            $stmt = $pdo->prepare("
                SELECT data, status, observacao 
                FROM frequencia 
                WHERE matricula_id = ? AND MONTH(data) = ? AND YEAR(data) = ?
                ORDER BY data
            ");
            $stmt->execute([$aluno['matricula_id'], $mes, $ano]);
            $aluno['frequencias'] = $stmt->fetchAll();
            
            // Contar status
            $aluno['presentes'] = 0;
            $aluno['ausentes'] = 0;
            $aluno['justificados'] = 0;
            $aluno['atrasados'] = 0;
            
            foreach ($aluno['frequencias'] as $f) {
                switch ($f['status']) {
                    case 'presente': $aluno['presentes']++; break;
                    case 'ausente': $aluno['ausentes']++; break;
                    case 'justificado': $aluno['justificados']++; break;
                    case 'atrasado': $aluno['atrasados']++; break;
                }
            }
            
            $total_presentes += $aluno['presentes'];
            $total_ausentes += $aluno['ausentes'];
            $total_justificados += $aluno['justificados'];
            $total_atrasados += $aluno['atrasados'];
        }
        unset($aluno);
        
    } catch (Exception $e) {}
}

include '../includes/header_escola.php';
?>

<style>
    /* (mesmos estilos do marcar.php) */
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
    
    .filtros {
        background: white;
        padding: 18px 20px;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .filtros .row {
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
        transition: border-color 0.3s;
    }
    
    .filtros .form-group select:focus,
    .filtros .form-group input:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    
    .filtros .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
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
    
    .stat-card.presentes { border-left-color: #2ecc71; }
    .stat-card.ausentes { border-left-color: #e74c3c; }
    .stat-card.justificados { border-left-color: #f39c12; }
    .stat-card.atrasados { border-left-color: #3498db; }
    
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
        min-width: 700px;
    }
    
    .table th {
        background: #f8fafc;
        padding: 10px 12px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        font-size: 11px;
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
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-presente {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-ausente {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .status-justificado {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-atrasado {
        background: #dbeafe;
        color: #1e40af;
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
        margin: 0 0 5px;
    }
    
    .nav-escola {
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
    
    .nav-escola a {
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
    
    .nav-escola a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .nav-escola a.active {
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
        .filtros .row {
            flex-direction: column;
        }
        .filtros .form-group {
            min-width: 100%;
        }
        .filtros .actions {
            width: 100%;
        }
        .filtros .actions .btn {
            flex: 1;
            justify-content: center;
        }
        .nav-escola {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-escola a {
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
            min-width: 600px;
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
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📈 Relatório de Frequência</h1>
        <p class="subtitle">Análise de presenças e ausências</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Navegação -->
<div class="nav-escola">
    <a href="index.php">📊 Dashboard</a>
    <a href="marcar.php">📌 Marcar Presença</a>
    <a href="relatorio.php" class="active">📈 Relatório</a>
</div>

<!-- Filtros -->
<div class="filtros">
    <form method="GET" class="row">
        <div class="form-group">
            <label>Turma</label>
            <select name="turma_id">
                <option value="">Todas as turmas</option>
                <?php foreach($turmas as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($turma_id == $t['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['classe']) ?> - <?= htmlspecialchars($t['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Mês</label>
            <select name="mes">
                <?php for($m=1; $m<=12; $m++): ?>
                <option value="<?= $m ?>" <?= ($mes == $m) ? 'selected' : '' ?>>
                    <?= strftime('%B', mktime(0,0,0,$m,1,2000)) ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Ano</label>
            <select name="ano">
                <?php for($a=date('Y')-2; $a<=date('Y'); $a++): ?>
                <option value="<?= $a ?>" <?= ($ano == $a) ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="relatorio.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<?php if ($turma_id && count($alunos) > 0): ?>
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card presentes">
            <span class="icon">✅</span>
            <div class="number"><?= $total_presentes ?></div>
            <div class="label">Presentes</div>
        </div>
        <div class="stat-card ausentes">
            <span class="icon">❌</span>
            <div class="number"><?= $total_ausentes ?></div>
            <div class="label">Ausentes</div>
        </div>
        <div class="stat-card justificados">
            <span class="icon">📋</span>
            <div class="number"><?= $total_justificados ?></div>
            <div class="label">Justificados</div>
        </div>
        <div class="stat-card atrasados">
            <span class="icon">⏰</span>
            <div class="number"><?= $total_atrasados ?></div>
            <div class="label">Atrasados</div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Aluno</th>
                    <th>✅ Presentes</th>
                    <th>❌ Ausentes</th>
                    <th>📋 Justificados</th>
                    <th>⏰ Atrasados</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($alunos as $aluno): 
                    $total = $aluno['presentes'] + $aluno['ausentes'] + $aluno['justificados'] + $aluno['atrasados'];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($aluno['nome']) ?></strong></td>
                    <td style="color: #2ecc71; font-weight: 600;"><?= $aluno['presentes'] ?></td>
                    <td style="color: #e74c3c; font-weight: 600;"><?= $aluno['ausentes'] ?></td>
                    <td style="color: #f39c12; font-weight: 600;"><?= $aluno['justificados'] ?></td>
                    <td style="color: #3498db; font-weight: 600;"><?= $aluno['atrasados'] ?></td>
                    <td style="font-weight: 700; color: #1a2332;"><?= $total ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="acoes-rapidas">
        <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações:</span>
        <a href="exportar_frequencia.php?turma_id=<?= $turma_id ?>&mes=<?= $mes ?>&ano=<?= $ano ?>" class="btn btn-success">📤 Exportar</a>
        <a href="marcar.php?turma_id=<?= $turma_id ?>" class="btn btn-primary">📌 Marcar Presença</a>
    </div>

<?php elseif ($turma_id && count($alunos) == 0): ?>
    <div class="empty-state">
        <span class="icon">📭</span>
        <h3>Nenhum aluno matriculado nesta turma</h3>
        <p>Não há registros de frequência para esta turma.</p>
    </div>
<?php else: ?>
    <div class="empty-state">
        <span class="icon">🔍</span>
        <h3>Selecione uma turma</h3>
        <p>Escolha a turma e o período para visualizar o relatório.</p>
    </div>
<?php endif; ?>

<?php include '../includes/footer_escola.php'; ?>