<?php
// ============================================
// modules/escola/alunos/index.php - Gestão de Alunos
// ============================================

// ============================================
// 1. CARREGAR CONFIGURAÇÕES
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
// 4. 🔒 VERIFICAR PERMISSÃO PARA VISUALIZAR
// ============================================
bloquearAcesso('visualizar');

// ============================================
// 5. VERIFICAR PERMISSÕES PARA BOTÕES
// ============================================
$pode_criar = pode('criar');
$pode_editar = pode('editar');
$pode_excluir = pode('excluir');

// ============================================
// 6. CONECTAR AO BANCO E BUSCAR DADOS
// ============================================
function limparExibicao($texto) {
    if (empty($texto)) return '-';
    $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    $texto = str_replace(['?', '�', '�', '�', '�'], '', $texto);
    return $texto;
}

$totalAlunos = 0;
$totalMatriculados = 0;
$totalConfirmados = 0;
$alunos = [];
$mensagem_erro = '';

try {
    // 🔑 CONECTA AO BANCO
    $pdo = conectarBanco();
    
    // Verifica se a tabela alunos existe
    $check = $pdo->query("SHOW TABLES LIKE 'alunos'");
    if ($check->rowCount() > 0) {
        // Totais
        $totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos WHERE status = 'ativo' OR status IS NULL")->fetchColumn() ?? 0;
        $totalMatriculados = $pdo->query("SELECT COUNT(*) FROM alunos WHERE Situacao_Cadastro = 'Matrícula' AND (status = 'ativo' OR status IS NULL)")->fetchColumn() ?? 0;
        $totalConfirmados = $pdo->query("SELECT COUNT(*) FROM alunos WHERE Situacao_Cadastro = 'Confirmação' AND (status = 'ativo' OR status IS NULL)")->fetchColumn() ?? 0;
        
        // Lista de alunos
        $alunos = $pdo->query("
            SELECT id, nome, Sexo, Idade, Morada, Contacto_do_Aluno, 
                   Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro,
                   status, created_at
            FROM alunos 
            WHERE status = 'ativo' OR status IS NULL
            ORDER BY nome ASC
        ")->fetchAll();
    }
} catch (Exception $e) {
    $mensagem_erro = "Erro ao carregar dados: " . $e->getMessage();
}

// ============================================
// 7. INCLUIR HEADER
// ============================================

?>

<!-- ============================================
     8. CONTEÚDO
     ============================================ -->
<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
    .page-header .subtitle { color: #94a3b8; font-size: 14px; margin: 2px 0 0; }
    
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
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(201,168,76,0.3); }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-info { background: #3498db; color: #fff; }
    .btn-info:hover { background: #2980b9; }
    .btn-warning { background: #f39c12; color: #fff; }
    .btn-warning:hover { background: #d68910; }
    .btn-danger { background: #e74c3c; color: #fff; }
    .btn-danger:hover { background: #c0392b; }
    .btn-sm { padding: 4px 12px; font-size: 11px; border-radius: 6px; }
    
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
    }
    .stat-card .number { font-size: 26px; font-weight: 700; color: #1a2332; margin: 0; }
    .stat-card .label { font-size: 12px; color: #94a3b8; margin: 3px 0 0; }
    .stat-card .icon { font-size: 24px; display: block; margin-bottom: 5px; }
    .stat-card.total { border-left-color: #3498db; }
    .stat-card.matriculados { border-left-color: #2ecc71; }
    .stat-card.confirmados { border-left-color: #f39c12; }
    
    .table-responsive {
        overflow-x: auto;
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
        font-size: 10px;
        text-transform: uppercase;
    }
    .table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .table tr:hover { background: #fafbfc; }
    .table .nome { font-weight: 600; color: #1a2332; }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    .status-Matrícula { background: #dbeafe; color: #1e40af; }
    .status-Confirmação { background: #d1fae5; color: #065f46; }
    
    .sexo-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
    .sexo-M { background: #dbeafe; color: #1e40af; }
    .sexo-F { background: #fce7f3; color: #9d174d; }
    
    .turma-tag {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 11px;
    }
    .turma-manha { background: #d4edda; color: #155724; }
    .turma-tarde { background: #cce5ff; color: #004085; }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #94a3b8;
    }
    .empty-state .icon { font-size: 48px; display: block; margin-bottom: 15px; }
    .empty-state h3 { font-size: 18px; color: #4a5568; margin: 0 0 5px; }
    
    .table-actions { display: flex; gap: 4px; flex-wrap: wrap; }
    
    .nav-alunos {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 25px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
    }
    .nav-alunos a {
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        color: #4a5568;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
    }
    .nav-alunos a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    .nav-alunos a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    
    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: stretch; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .table { font-size: 12px; min-width: 650px; }
        .table th, .table td { padding: 6px 8px; }
        .btn-sm { font-size: 10px; padding: 3px 8px; }
    }
</style>

<div style="padding: 20px 30px;">

    <!-- Cabeçalho -->
    <div class="page-header">
        <div>
            <h1>👨‍🎓 Alunos</h1>
            <p class="subtitle">Gestão de cadastro e matrícula de alunos</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <?php if ($pode_criar): ?>
                <a href="add.php" class="btn btn-primary">➕ Novo Aluno</a>
            <?php endif; ?>
            <a href="../index.php" class="btn btn-secondary">← Voltar</a>
        </div>
    </div>

    <!-- Navegação -->
    <div class="nav-alunos">
        <a href="index.php" class="active">📋 Lista de Alunos</a>
        <?php if ($pode_criar): ?>
            <a href="add.php">➕ Cadastrar Aluno</a>
        <?php endif; ?>
        <a href="reconfirmar.php">🔄 Reconfirmação</a>
        <a href="consulta.php">🔍 Consulta</a>
        <a href="relatorio.php">📈 Relatório</a>
        <a href="relatorio_idade.php">📊 Relatório por Idade</a>
        <a href="listas_nominais.php">📋 Listas Nominais</a>
        <a href="cartoes_escolares.php" style="background: #FF8C00; color: #fff; border-color: #FF8C00;">🪪 Cartões Escolares</a>
    </div>

    <?php if (!empty($mensagem_erro)): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:15px;border-radius:8px;margin-bottom:20px;">⚠️ <?= $mensagem_erro ?></div>
    <?php endif; ?>

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
    </div>

    <!-- Tabela -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Sexo</th>
                    <th>Idade</th>
                    <th>Classe</th>
                    <th>Turma</th>
                    <th>Período</th>
                    <th>Situação</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($alunos) > 0): ?>
                    <?php foreach($alunos as $a): 
                        $turma_class = '';
                        if (stripos($a['Periodo'] ?? '', 'manh') !== false) $turma_class = 'turma-manha';
                        elseif (stripos($a['Periodo'] ?? '', 'tard') !== false) $turma_class = 'turma-tarde';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($a['id']) ?></strong></td>
                        <td class="nome"><?= limparExibicao($a['nome']) ?></td>
                        <td>
                            <span class="sexo-badge sexo-<?= $a['Sexo'] ?? 'M' ?>">
                                <?= $a['Sexo'] ?? 'M' ?>
                            </span>
                        </td>
                        <td><?= $a['Idade'] ?? '-' ?></td>
                        <td><?= limparExibicao($a['Classe']) ?></td>
                        <td>
                            <?php if (!empty($a['TURMA']) && $a['TURMA'] != '-'): ?>
                                <span class="turma-tag <?= $turma_class ?>">
                                    <?= limparExibicao($a['TURMA']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= limparExibicao($a['Periodo']) ?></td>
                        <td>
                            <span class="status-badge status-<?= $a['Situacao_Cadastro'] ?? 'Matrícula' ?>">
                                <?= limparExibicao($a['Situacao_Cadastro']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="view.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-info">Ver</a>
                                <?php if ($pode_editar): ?>
                                    <a href="edit.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                <?php endif; ?>
                                <?php if ($pode_excluir): ?>
                                    <a href="delete.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir este aluno?')">Excluir</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <span class="icon">📭</span>
                                <h3>Nenhum aluno cadastrado</h3>
                                <p>Clique em "Novo Aluno" para começar a cadastrar.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

