<?php
// ============================================
// modules/escola/financeiro/pagamentos/relatorio_reconfirmacao.php
// Relatório de alunos reconfirmados
// ============================================

// ============================================
// INCLUIR CONFIGURAÇÃO DO BANCO
// ============================================
require_once '../../config/database.php';

// ============================================
// CONFIGURAR HEADER PARA UTF-8
// ============================================
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// ============================================
// SESSÃO
// ============================================
if (session_status() === PHP_SESSION_NONE) session_start();

// ============================================
// FUNÇÕES AUXILIARES
// ============================================
function limparString($texto) {
    if (empty($texto)) return '';
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'auto');
    }
    $texto = preg_replace('/[[:cntrl:]]/', '', $texto);
    $texto = str_replace(['?', '�', '�', '�'], '', $texto);
    $texto = trim($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return $texto;
}

function normalizarClasse($classe) {
    if (empty($classe)) return '';
    $classe = limparString($classe);
    $classe = trim($classe);
    $classe = str_replace(' ', '', $classe);
    $classe = str_replace(['?', '�', '�', '�', '?'], '', $classe);
    
    if (preg_match('/^PRE/i', $classe)) return '1ª';
    if (preg_match('/^1/i', $classe)) return '1ª';
    if (preg_match('/^2/i', $classe)) return '2ª';
    if (preg_match('/^3/i', $classe)) return '3ª';
    if (preg_match('/^4/i', $classe)) return '4ª';
    if (preg_match('/^5/i', $classe)) return '5ª';
    if (preg_match('/^6/i', $classe)) return '6ª';
    if (preg_match('/^7/i', $classe)) return '7ª';
    if (preg_match('/^8/i', $classe)) return '8ª';
    if (preg_match('/^9/i', $classe)) return '9ª';
    if (preg_match('/^10/i', $classe)) return '10ª';
    if (preg_match('/^11/i', $classe)) return '11ª';
    if (preg_match('/^12/i', $classe)) return '12ª';
    
    if (preg_match('/^(\d+)ª/', $classe, $matches)) return $matches[0];
    if (preg_match('/^(\d+)º/', $classe, $matches)) return $matches[1] . 'ª';
    if (preg_match('/^(\d+)$/', $classe, $matches)) {
        $num = intval($matches[1]);
        if ($num >= 1 && $num <= 12) return $num . 'ª';
    }
    return $classe;
}

$erro = '';
$sucesso = '';
$alunos_reconfirmados = [];
$ano_letivo_atual = date('Y');

// ============================================
// BUSCAR ALUNOS RECONFIRMADOS
// ============================================
try {
    mysqli_set_charset($conn, "utf8mb4");
    
    // Buscar alunos com status 'Confirmação' ou que foram reconfirmados recentemente
    $query = "
        SELECT 
            id, 
            nome, 
            Sexo, 
            Idade, 
            Classe, 
            Periodo, 
            TURMA, 
            SALA,
            Situacao_Cadastro,
            data_matricula,
            created_at,
            DATE(created_at) as data_reconfirmacao
        FROM alunos 
        WHERE Situacao_Cadastro = 'Confirmação'
           OR Situacao_Cadastro = 'Confirmado'
        ORDER BY created_at DESC, nome
    ";
    
    $result = mysqli_query($conn, $query);
    if ($result) {
        $alunos_reconfirmados = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    
    // Se não houver alunos com status 'Confirmação', buscar todos os alunos
    if (count($alunos_reconfirmados) == 0) {
        $query = "
            SELECT 
                id, 
                nome, 
                Sexo, 
                Idade, 
                Classe, 
                Periodo, 
                TURMA, 
                SALA,
                Situacao_Cadastro,
                data_matricula,
                created_at,
                DATE(created_at) as data_reconfirmacao
            FROM alunos 
            ORDER BY created_at DESC, nome
            LIMIT 100
        ";
        $result = mysqli_query($conn, $query);
        if ($result) {
            $alunos_reconfirmados = mysqli_fetch_all($result, MYSQLI_ASSOC);
        }
    }
    
} catch (Exception $e) {
    $erro = 'Erro ao buscar alunos: ' . $e->getMessage();
}

// ============================================
// ESTATÍSTICAS
// ============================================
$total_alunos = count($alunos_reconfirmados);
$por_classe = [];
$por_periodo = [];
$por_sexo = [];

foreach ($alunos_reconfirmados as $a) {
    $classe = normalizarClasse($a['Classe'] ?? 'Sem Classe');
    if (!isset($por_classe[$classe])) $por_classe[$classe] = 0;
    $por_classe[$classe]++;
    
    $periodo = $a['Periodo'] ?? 'Não definido';
    if (!isset($por_periodo[$periodo])) $por_periodo[$periodo] = 0;
    $por_periodo[$periodo]++;
    
    $sexo = $a['Sexo'] ?? 'Indefinido';
    if (!isset($por_sexo[$sexo])) $por_sexo[$sexo] = 0;
    $por_sexo[$sexo]++;
}

// ============================================
// INCLUIR HEADER
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
    
    .stats-bar {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
    }
    
    .stats-bar .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #4a5568;
    }
    
    .stats-bar .stat-item .number {
        font-weight: 700;
        font-size: 18px;
        color: #1a2332;
    }
    
    .stats-bar .stat-item .number-f {
        font-weight: 700;
        font-size: 18px;
        color: #e74c3c;
    }
    
    .stats-bar .stat-item .number-m {
        font-weight: 700;
        font-size: 18px;
        color: #3498db;
    }
    
    .stats-bar .stat-item .label {
        color: #94a3b8;
        font-size: 13px;
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
        background: #b8973d;
        color: #1a2332;
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
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
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
        min-width: 900px;
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
    
    .status-Confirmação {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-Confirmado {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .status-Matrícula {
        background: #fef3c7;
        color: #92400e;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }
    
    .empty-state .icon {
        font-size: 64px;
        display: block;
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        font-size: 20px;
        color: #4a5568;
        margin: 0 0 5px;
    }
    
    .empty-state p {
        color: #94a3b8;
        margin: 0 0 15px;
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
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
    
    .idade-badge {
        background: #e9d5ff;
        color: #6b21a8;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }
    
    .data-badge {
        background: #f1f5f9;
        color: #4a5568;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 11px;
        display: inline-block;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 15px 20px;
        border: 1px solid #eef2f7;
        text-align: center;
    }
    
    .stats-card .number {
        font-size: 28px;
        font-weight: 700;
        color: #1a2332;
    }
    
    .stats-card .label {
        font-size: 12px;
        color: #94a3b8;
        display: block;
        margin-top: 5px;
    }
    
    .stats-card .number-f {
        color: #e74c3c;
    }
    
    .stats-card .number-m {
        color: #3498db;
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
        .table {
            font-size: 12px;
            min-width: 700px;
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
        .stats-bar {
            flex-direction: column;
            gap: 10px;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📈 Relatório de Reconfirmação</h1>
        <p class="subtitle">Alunos reconfirmados para o ano letivo <?= $ano_letivo_atual ?></p>
    </div>
    <a href="importados_pendencias.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Navegação -->
<div class="nav-alunos">
    <a href="index.php">📋 Lista de Alunos</a>
    <a href="add.php">➕ Cadastrar Aluno</a>
    <a href="reconfirmar.php">🔄 Reconfirmação</a>
    <a href="importados_pendencias.php">📋 Pendências</a>
    <a href="consulta.php">🔍 Consulta</a>
    <a href="relatorio_reconfirmacao.php" class="active">📈 Relatório</a>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error">❌ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- Estatísticas Gerais -->
<div class="stats-grid">
    <div class="stats-card">
        <span class="number"><?= $total_alunos ?></span>
        <span class="label">Total de Alunos</span>
    </div>
    <div class="stats-card">
        <span class="number number-f"><?= $por_sexo['F'] ?? 0 ?></span>
        <span class="label">👩 Feminino</span>
    </div>
    <div class="stats-card">
        <span class="number number-m"><?= $por_sexo['M'] ?? 0 ?></span>
        <span class="label">👨 Masculino</span>
    </div>
    <div class="stats-card">
        <span class="number"><?= count($por_classe) ?></span>
        <span class="label">📚 Classes Atendidas</span>
    </div>
</div>

<!-- Distribuição por Classe -->
<div class="stats-bar">
    <span style="font-weight: 600; color: #1a2332;">📊 Distribuição por Classe:</span>
    <?php foreach ($por_classe as $classe => $qtd): ?>
    <div class="stat-item">
        <span class="number"><?= $qtd ?></span>
        <span class="label"><?= htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Distribuição por Período -->
<div class="stats-bar">
    <span style="font-weight: 600; color: #1a2332;">🕐 Distribuição por Período:</span>
    <?php foreach ($por_periodo as $periodo => $qtd): ?>
    <div class="stat-item">
        <span class="number"><?= $qtd ?></span>
        <span class="label"><?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabela de Alunos -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Sexo</th>
                <th>Idade</th>
                <th>Classe</th>
                <th>Período</th>
                <th>Turma</th>
                <th>Sala</th>
                <th>Status</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($alunos_reconfirmados) > 0): ?>
                <?php foreach($alunos_reconfirmados as $a): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['id'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars(limparString($a['nome']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($a['Sexo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($a['Idade'])): ?>
                            <span class="idade-badge"><?= $a['Idade'] ?> anos</span>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 12px;">não definida</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(normalizarClasse($a['Classe']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($a['Periodo'] ?? 'Manhã', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($a['TURMA'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($a['SALA'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="status-badge status-<?= str_replace(' ', '_', $a['Situacao_Cadastro'] ?? '') ?>">
                            <?= htmlspecialchars($a['Situacao_Cadastro'] ?? 'Pendente', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <span class="data-badge">
                            <?= isset($a['data_reconfirmacao']) ? date('d/m/Y', strtotime($a['data_reconfirmacao'])) : date('d/m/Y', strtotime($a['data_matricula'] ?? 'now')) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhum aluno reconfirmado</h3>
                            <p>Ainda não há alunos reconfirmados no sistema ou a tabela está vazia.</p>
                            <a href="importados_pendencias.php" class="btn btn-primary">📋 Ver Pendências</a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações:</span>
    <a href="importados_pendencias.php" class="btn btn-info">📋 Pendências</a>
    <a href="reconfirmar.php" class="btn btn-primary">🔄 Reconfirmar</a>
    <?php if (count($alunos_reconfirmados) > 0): ?>
    <a href="?exportar=pdf" class="btn btn-danger">📄 Exportar PDF</a>
    <a href="?exportar=excel" class="btn btn-success">📊 Exportar Excel</a>
    <?php endif; ?>
</div>

<?php include '../includes/footer_escola.php'; ?>