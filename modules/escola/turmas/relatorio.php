<?php
// ============================================
// modules/escola/alunos/add.php - Cadastrar Aluno
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para CRIAR
bloquearAcesso('criar');

// Resto do código...
?>




<?php
// ============================================
// modules/escola/turmas/relatorio.php - Relatório (COM IDADES)
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

// ===== BUSCAR DADOS =====
$turmas = [];
try {
    $turmas = $pdo->query("
        SELECT t.*, 
               (SELECT COUNT(*) FROM matriculas WHERE turma_id = t.id AND status = 'ativa') as total_alunos
        FROM turmas t
        ORDER BY t.classe, t.nome
    ")->fetchAll();
} catch (Exception $e) {}

$totalAlunos = 0;
$totalTurmas = count($turmas);
foreach($turmas as $t) {
    $totalAlunos += $t['total_alunos'] ?? 0;
}

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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        font-size: 28px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .stat-card .label {
        font-size: 13px;
        color: #94a3b8;
        margin: 3px 0 0;
    }
    
    .stat-card .icon {
        font-size: 28px;
        display: block;
        margin-bottom: 5px;
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
        font-size: 13px;
        min-width: 800px;
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
    
    .idades-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        background: #f3e8ff;
        color: #6b21a8;
    }
    
    .acoes-rapidas {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 20px;
        justify-content: center;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
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
            min-width: 650px;
        }
        .table th, .table td {
            padding: 6px 8px;
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
        <h1>📈 Relatório de Turmas</h1>
        <p class="subtitle">Resumo completo das turmas, alunos e idades</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card" style="border-left-color: #3498db;">
        <span class="icon">🏫</span>
        <div class="number"><?= $totalTurmas ?></div>
        <div class="label">Total de Turmas</div>
    </div>
    <div class="stat-card" style="border-left-color: #2ecc71;">
        <span class="icon">👨‍🎓</span>
        <div class="number"><?= $totalAlunos ?></div>
        <div class="label">Total de Alunos</div>
    </div>
    <div class="stat-card" style="border-left-color: #f39c12;">
        <span class="icon">📊</span>
        <div class="number"><?= $totalTurmas > 0 ? round($totalAlunos / $totalTurmas, 1) : 0 ?></div>
        <div class="label">Média por Turma</div>
    </div>
    <div class="stat-card" style="border-left-color: #9b59b6;">
        <span class="icon">📚</span>
        <div class="number"><?= count(array_unique(array_column($turmas, 'classe'))) ?></div>
        <div class="label">Classes Diferentes</div>
    </div>
</div>

<!-- Tabela -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Turma</th>
                <th>Classe</th>
                <th>Curso</th>
                <th>Turno</th>
                <th>Sala</th>
                <th>Idades</th>
                <th>Limite</th>
                <th>Alunos</th>
                <th>Vagas</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($turmas) > 0): ?>
                <?php foreach($turmas as $t): 
                    $vagas = ($t['limite'] ?? 30) - ($t['total_alunos'] ?? 0);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($t['nome']) ?></strong></td>
                    <td><?= htmlspecialchars($t['classe'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($t['curso'] ?? '-') ?></td>
                    <td><?= ucfirst($t['turno'] ?? 'Manhã') ?></td>
                    <td><?= htmlspecialchars($t['sala'] ?? '-') ?></td>
                    <td><span class="idades-badge"><?= htmlspecialchars($t['idades'] ?? '-') ?></span></td>
                    <td><?= $t['limite'] ?? 30 ?></td>
                    <td><strong><?= $t['total_alunos'] ?? 0 ?></strong></td>
                    <td><?= $vagas ?></td>
                    <td>
                        <span class="status-badge status-<?= $t['status'] ?? 'ativa' ?>">
                            <?= ucfirst($t['status'] ?? 'ativa') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 40px; color: #94a3b8;">
                        Nenhuma turma cadastrada.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Ações -->
<div class="acoes-rapidas">
    <a href="exportar.php" class="btn btn-primary">📤 Exportar Relatório</a>
    <a href="relatorio_pdf.php" class="btn btn-success">📄 Gerar PDF</a>
    <a href="imprimir.php" class="btn btn-info">🖨️ Imprimir</a>
</div>

<?php include '../includes/footer_escola.php'; ?>