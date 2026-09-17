<?php
// ============================================
// modules/escola/alunos/relatorio_idade.php - Relatório por Idade
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
$alunosPorIdade = [];
$faixasEtarias = [
    '0-5' => 0,
    '6-10' => 0,
    '11-15' => 0,
    '16-20' => 0,
    '21-25' => 0,
    '26-30' => 0,
    '31+' => 0
];

try {
    $totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos")->fetchColumn() ?? 0;
    
    // Buscar alunos com idade
    $alunosPorIdade = $pdo->query("
        SELECT Idade, COUNT(*) as total 
        FROM alunos 
        WHERE Idade IS NOT NULL AND Idade > 0
        GROUP BY Idade 
        ORDER BY Idade
    ")->fetchAll();
    
    // Calcular faixas etárias
    foreach($alunosPorIdade as $item) {
        $idade = $item['Idade'];
        $total = $item['total'];
        
        if ($idade <= 5) {
            $faixasEtarias['0-5'] += $total;
        } elseif ($idade <= 10) {
            $faixasEtarias['6-10'] += $total;
        } elseif ($idade <= 15) {
            $faixasEtarias['11-15'] += $total;
        } elseif ($idade <= 20) {
            $faixasEtarias['16-20'] += $total;
        } elseif ($idade <= 25) {
            $faixasEtarias['21-25'] += $total;
        } elseif ($idade <= 30) {
            $faixasEtarias['26-30'] += $total;
        } else {
            $faixasEtarias['31+'] += $total;
        }
    }
    
    // Calcular estatísticas
    $idadeMinima = 0;
    $idadeMaxima = 0;
    $idadeMedia = 0;
    $somaIdades = 0;
    $totalComIdade = 0;
    
    foreach($alunosPorIdade as $item) {
        $idade = $item['Idade'];
        $total = $item['total'];
        if ($idadeMinima == 0 || $idade < $idadeMinima) $idadeMinima = $idade;
        if ($idade > $idadeMaxima) $idadeMaxima = $idade;
        $somaIdades += $idade * $total;
        $totalComIdade += $total;
    }
    
    if ($totalComIdade > 0) {
        $idadeMedia = round($somaIdades / $totalComIdade, 1);
    }
    
} catch (Exception $e) {}

// ===== BUSCAR ALUNOS DETALHADOS POR IDADE =====
$alunosDetalhados = [];
try {
    $alunosDetalhados = $pdo->query("
        SELECT id, nome, Sexo, Idade, Classe, Curso, TURMA, Situacao_Cadastro
        FROM alunos 
        WHERE Idade IS NOT NULL AND Idade > 0
        ORDER BY Idade, nome
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
    .stat-card.min { border-left-color: #2ecc71; }
    .stat-card.max { border-left-color: #e74c3c; }
    .stat-card.media { border-left-color: #f39c12; }
    
    .faixas-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .faixa-card {
        background: white;
        padding: 15px 18px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        border-top: 4px solid #c9a84c;
        transition: all 0.3s;
    }
    
    .faixa-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .faixa-card .faixa {
        font-size: 14px;
        font-weight: 600;
        color: #1a2332;
    }
    
    .faixa-card .number {
        font-size: 28px;
        font-weight: 700;
        color: #1a2332;
        margin: 5px 0;
    }
    
    .faixa-card .percentual {
        font-size: 12px;
        color: #94a3b8;
    }
    
    .faixa-card .barra {
        height: 6px;
        background: #eef2f7;
        border-radius: 4px;
        margin-top: 8px;
        overflow: hidden;
    }
    
    .faixa-card .barra .fill {
        height: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, #c9a84c, #f5d76e);
        transition: width 0.5s;
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
    
    .table .idade {
        font-weight: 700;
        color: #1a2332;
        text-align: center;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-Matrícula {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .status-Confirmação {
        background: #d1fae5;
        color: #065f46;
    }
    
    .sexo-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .sexo-M {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .sexo-F {
        background: #fce7f3;
        color: #9d174d;
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
    
    .total-info {
        padding: 10px 16px;
        font-size: 13px;
        color: #94a3b8;
        border-top: 1px solid #f1f5f9;
        text-align: right;
    }
    
    .total-info strong {
        color: #1a2332;
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
        .faixas-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
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
        .faixas-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📊 Relatório por Idade</h1>
        <p class="subtitle">Distribuição de alunos por faixa etária</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Navegação -->
<div class="nav-alunos">
    <a href="index.php">📋 Lista de Alunos</a>
    <a href="add.php">➕ Cadastrar Aluno</a>
    <a href="reconfirmar.php">🔄 Reconfirmação</a>
    <a href="consulta.php">🔍 Consulta</a>
    <a href="relatorio.php">📈 Relatório Geral</a>
    <a href="relatorio_idade.php" class="active">📊 Relatório por Idade</a>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card total">
        <span class="icon">👨‍🎓</span>
        <div class="number"><?= $totalAlunos ?></div>
        <div class="label">Total de Alunos</div>
    </div>
    <div class="stat-card min">
        <span class="icon">⬇️</span>
        <div class="number"><?= $idadeMinima > 0 ? $idadeMinima : '-' ?></div>
        <div class="label">Idade Mínima</div>
    </div>
    <div class="stat-card max">
        <span class="icon">⬆️</span>
        <div class="number"><?= $idadeMaxima > 0 ? $idadeMaxima : '-' ?></div>
        <div class="label">Idade Máxima</div>
    </div>
    <div class="stat-card media">
        <span class="icon">📊</span>
        <div class="number"><?= $idadeMedia > 0 ? $idadeMedia : '-' ?></div>
        <div class="label">Idade Média</div>
    </div>
</div>

<!-- Faixas Etárias -->
<div class="faixas-grid">
    <?php foreach($faixasEtarias as $faixa => $total): 
        $percentual = $totalAlunos > 0 ? round(($total / $totalAlunos) * 100) : 0;
        $cores = [
            '0-5' => '#3498db',
            '6-10' => '#2ecc71',
            '11-15' => '#f39c12',
            '16-20' => '#e67e22',
            '21-25' => '#9b59b6',
            '26-30' => '#1abc9c',
            '31+' => '#e74c3c'
        ];
        $cor = $cores[$faixa] ?? '#c9a84c';
    ?>
    <div class="faixa-card" style="border-top-color: <?= $cor ?>;">
        <div class="faixa"><?= $faixa ?> anos</div>
        <div class="number"><?= $total ?></div>
        <div class="percentual"><?= $percentual ?>% dos alunos</div>
        <div class="barra">
            <div class="fill" style="width: <?= $percentual ?>%; background: <?= $cor ?>;"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabela Detalhada -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Sexo</th>
                <th>Idade</th>
                <th>Classe</th>
                <th>Curso</th>
                <th>Turma</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($alunosDetalhados) > 0): ?>
                <?php foreach($alunosDetalhados as $a): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['id']) ?></strong></td>
                    <td><?= htmlspecialchars($a['nome']) ?></td>
                    <td>
                        <span class="sexo-badge sexo-<?= $a['Sexo'] ?? 'M' ?>">
                            <?= $a['Sexo'] ?? 'M' ?>
                        </span>
                    </td>
                    <td class="idade"><?= $a['Idade'] ?></td>
                    <td><?= htmlspecialchars($a['Classe'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($a['Curso'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($a['TURMA'] ?? '-') ?></td>
                    <td>
                        <span class="status-badge status-<?= $a['Situacao_Cadastro'] ?? 'Matrícula' ?>">
                            <?= $a['Situacao_Cadastro'] ?? 'Matrícula' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhum aluno com idade registrada</h3>
                            <p>Cadastre alunos com data de nascimento para visualizar o relatório por idade.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="total-info">
        Total de registros: <strong><?= count($alunosDetalhados) ?></strong>
    </div>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações:</span>
    <a href="imprimir_relatorio_idade.php" class="btn btn-primary">🖨️ Imprimir Relatório</a>
    <a href="exportar_idade.php" class="btn btn-success">📤 Exportar Dados</a>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php include '../includes/footer_escola.php'; ?>