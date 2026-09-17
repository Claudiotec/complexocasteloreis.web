<?php
// ============================================
// modules/escola/alunos/consulta.php - Consulta de Alunos
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
$filtro_tipo = $_GET['filtro'] ?? 'todos';
$filtro_valor = $_GET['valor'] ?? '';

// ===== BUSCAR ALUNOS =====
$alunos = [];
$totalEncontrados = 0;

try {
    $sql = "SELECT id, nome, Sexo, Idade, Morada, Contacto_do_Aluno, 
                   Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro,
                   Data_Matricula, Nome_do_Pai, Nome_da_mae, N_BI
            FROM alunos WHERE 1=1";
    $params = [];

    if ($filtro_valor && $filtro_tipo != 'todos') {
        switch ($filtro_tipo) {
            case 'id':
                $sql .= " AND id LIKE ?";
                $params[] = "%$filtro_valor%";
                break;
            case 'nome':
                $sql .= " AND nome LIKE ?";
                $params[] = "%$filtro_valor%";
                break;
            case 'classe':
                $sql .= " AND Classe LIKE ?";
                $params[] = "%$filtro_valor%";
                break;
            case 'curso':
                $sql .= " AND Curso LIKE ?";
                $params[] = "%$filtro_valor%";
                break;
            case 'turma':
                $sql .= " AND TURMA LIKE ?";
                $params[] = "%$filtro_valor%";
                break;
            case 'periodo':
                $sql .= " AND Periodo LIKE ?";
                $params[] = "%$filtro_valor%";
                break;
            case 'situacao':
                $sql .= " AND Situacao_Cadastro = ?";
                $params[] = $filtro_valor;
                break;
        }
    }

    $sql .= " ORDER BY nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alunos = $stmt->fetchAll();
    $totalEncontrados = count($alunos);
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
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .btn-info:hover {
        background: #2980b9;
    }
    
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    
    .btn-success:hover {
        background: #27ae60;
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 11px;
        border-radius: 6px;
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
    
    .table-actions {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
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
    }
</style>

<div class="page-header">
    <div>
        <h1>🔍 Consulta de Alunos</h1>
        <p class="subtitle">Pesquise e filtre alunos cadastrados</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Navegação -->
<div class="nav-alunos">
    <a href="index.php">📋 Lista de Alunos</a>
    <a href="add.php">➕ Cadastrar Aluno</a>
    <a href="reconfirmar.php">🔄 Reconfirmação</a>
    <a href="consulta.php" class="active">🔍 Consulta</a>
    <a href="relatorio.php">📈 Relatório</a>
</div>

<!-- Filtros -->
<div class="filtros">
    <form method="GET" class="row">
        <div class="form-group">
            <label>Filtrar por</label>
            <select name="filtro">
                <option value="todos" <?= $filtro_tipo == 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="id" <?= $filtro_tipo == 'id' ? 'selected' : '' ?>>ID</option>
                <option value="nome" <?= $filtro_tipo == 'nome' ? 'selected' : '' ?>>Nome</option>
                <option value="classe" <?= $filtro_tipo == 'classe' ? 'selected' : '' ?>>Classe</option>
                <option value="curso" <?= $filtro_tipo == 'curso' ? 'selected' : '' ?>>Curso</option>
                <option value="turma" <?= $filtro_tipo == 'turma' ? 'selected' : '' ?>>Turma</option>
                <option value="periodo" <?= $filtro_tipo == 'periodo' ? 'selected' : '' ?>>Período</option>
                <option value="situacao" <?= $filtro_tipo == 'situacao' ? 'selected' : '' ?>>Situação</option>
            </select>
        </div>
        <div class="form-group">
            <label>Valor</label>
            <input type="text" name="valor" value="<?= htmlspecialchars($filtro_valor) ?>" placeholder="Digite o valor para filtrar">
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="consulta.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<!-- Resultados -->
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
                <th>Período</th>
                <th>Situação</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($alunos) > 0): ?>
                <?php foreach($alunos as $a): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['id']) ?></strong></td>
                    <td><?= htmlspecialchars($a['nome']) ?></td>
                    <td>
                        <span class="sexo-badge sexo-<?= $a['Sexo'] ?? 'M' ?>">
                            <?= $a['Sexo'] ?? 'M' ?>
                        </span>
                    </td>
                    <td><?= $a['Idade'] ?? '-' ?></td>
                    <td><?= htmlspecialchars($a['Classe'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($a['Curso'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($a['TURMA'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($a['Periodo'] ?? '-') ?></td>
                    <td>
                        <span class="status-badge status-<?= $a['Situacao_Cadastro'] ?? 'Matrícula' ?>">
                            <?= $a['Situacao_Cadastro'] ?? 'Matrícula' ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="view.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-info">Visualizar</a>
                            <a href="edit.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                            <a href="delete.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?')">Excluir</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <span class="icon">🔍</span>
                            <h3>Nenhum aluno encontrado</h3>
                            <p>Tente ajustar os filtros de pesquisa.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="total-info">
        Total de registros: <strong><?= $totalEncontrados ?></strong>
    </div>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações:</span>
    <a href="exportar.php?filtro=<?= $filtro_tipo ?>&valor=<?= urlencode($filtro_valor) ?>" class="btn btn-success">📤 Exportar Resultados</a>
    <a href="relatorio.php" class="btn btn-info">📈 Relatório Completo</a>
</div>

<?php include '../includes/footer_escola.php'; ?>