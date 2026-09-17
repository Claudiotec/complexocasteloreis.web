<?php
// ============================================
// modules/escola/alunos/cartoes_escolares.php - Cartões Escolares
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

// ===== DADOS DA EMPRESA =====
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'SoftGest Sistemas';
$enderecoEmpresa = $empresa['endereco'] ?? '';
$telefoneEmpresa = $empresa['telefone'] ?? '';
$emailEmpresa = $empresa['email'] ?? '';
$nifEmpresa = $empresa['cnpj'] ?? '';

// ===== BUSCAR ALUNOS =====
$alunos = [];
try {
    $alunos = $pdo->query("
        SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
               Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro,
               Nome_do_Pai, Contacto4
        FROM alunos 
        WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
        ORDER BY Classe, TURMA, nome
    ")->fetchAll();
} catch (Exception $e) {}

// ===== CALCULAR ANO LETIVO =====
$mes_atual = date('m');
$ano_atual = date('Y');
if ($mes_atual >= 9) {
    $ano_letivo = $ano_atual . '/' . ($ano_atual + 1);
} else {
    $ano_letivo = ($ano_atual - 1) . '/' . $ano_atual;
}


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
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-warning:hover {
        background: #d68910;
    }
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 11px;
        border-radius: 6px;
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
    
    .card-aluno {
        background: white;
        border-radius: 12px;
        padding: 15px 18px;
        border: 1px solid #eef2f7;
        margin-bottom: 10px;
        transition: all 0.3s;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .card-aluno:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    }
    
    .card-aluno .info {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .card-aluno .info .id {
        font-weight: 700;
        color: #c9a84c;
        font-size: 14px;
    }
    
    .card-aluno .info .nome {
        font-weight: 600;
        color: #1a2332;
        font-size: 15px;
    }
    
    .card-aluno .info .classe {
        color: #94a3b8;
        font-size: 13px;
    }
    
    .card-aluno .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
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
    
    .lista-alunos {
        max-height: 500px;
        overflow-y: auto;
        margin-top: 15px;
    }
    
    .lista-alunos::-webkit-scrollbar {
        width: 6px;
    }
    
    .lista-alunos::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .lista-alunos::-webkit-scrollbar-thumb {
        background: #c9a84c;
        border-radius: 10px;
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
        .card-aluno {
            flex-direction: column;
            align-items: stretch;
            text-align: center;
        }
        .card-aluno .info {
            justify-content: center;
        }
        .card-aluno .actions {
            justify-content: center;
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
        <h1>🪪 Cartões Escolares</h1>
        <p class="subtitle">Gerar cartões de identificação escolar</p>
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
    <a href="listas_nominais.php">📋 Listas Nominais</a>
    <a href="cartoes_escolares.php" class="active">🪪 Cartões Escolares</a>
</div>

<!-- Filtros -->
<div class="filtros">
    <form method="GET" class="row" id="formFiltros">
        <div class="form-group">
            <label>Classe</label>
            <select name="classe" onchange="this.form.submit()">
                <option value="">Todas as classes</option>
                <?php 
                $classes = array_unique(array_column($alunos, 'Classe'));
                sort($classes);
                $classe_filtro = $_GET['classe'] ?? '';
                foreach($classes as $c): 
                    if (empty($c)) continue;
                ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= ($classe_filtro == $c) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Turma</label>
            <select name="turma" onchange="this.form.submit()">
                <option value="">Todas as turmas</option>
                <?php 
                $turmas = array_unique(array_column($alunos, 'TURMA'));
                sort($turmas);
                $turma_filtro = $_GET['turma'] ?? '';
                foreach($turmas as $t): 
                    if (empty($t)) continue;
                ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= ($turma_filtro == $t) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="actions">
            <a href="cartoes_escolares.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<!-- Lista de Alunos -->
<div class="lista-alunos">
    <?php 
    $alunos_filtrados = $alunos;
    if (!empty($classe_filtro)) {
        $alunos_filtrados = array_filter($alunos_filtrados, function($a) use ($classe_filtro) {
            return ($a['Classe'] ?? '') == $classe_filtro;
        });
    }
    if (!empty($turma_filtro)) {
        $alunos_filtrados = array_filter($alunos_filtrados, function($a) use ($turma_filtro) {
            return ($a['TURMA'] ?? '') == $turma_filtro;
        });
    }
    $alunos_filtrados = array_values($alunos_filtrados);
    ?>
    
    <?php if (count($alunos_filtrados) > 0): ?>
        <?php foreach($alunos_filtrados as $aluno): 
            $sexo = $aluno['Sexo'] ?? 'M';
            $sexoClass = $sexo == 'M' ? 'm' : 'f';
            $data_nasc = (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0) ? 
                sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-';
        ?>
        <div class="card-aluno">
            <div class="info">
                <span class="id">#<?= htmlspecialchars($aluno['id']) ?></span>
                <span class="nome"><?= htmlspecialchars($aluno['nome']) ?></span>
                <span class="badge-sexo badge-<?= $sexoClass ?>"><?= $sexo ?></span>
                <span class="classe"><?= htmlspecialchars($aluno['Classe'] ?? '-') ?></span>
                <span class="classe">Turma <?= htmlspecialchars($aluno['TURMA'] ?? '-') ?></span>
            </div>
            <div class="actions">
                <a href="gerar_cartao.php?id=<?= $aluno['id'] ?>" class="btn btn-sm btn-primary" target="_blank">🪪 Gerar Cartão</a>
                <a href="gerar_cartao_individual.php?id=<?= $aluno['id'] ?>" class="btn btn-sm btn-info" target="_blank">📄 Individual</a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align: center; padding: 10px; color: #94a3b8; font-size: 13px;">
            Total: <strong><?= count($alunos_filtrados) ?></strong> alunos
        </div>
    <?php else: ?>
        <div class="empty-state">
            <span class="icon">📭</span>
            <h3>Nenhum aluno encontrado</h3>
            <p>Não há alunos cadastrados com os filtros selecionados.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações:</span>
    <a href="gerar_cartoes_classe.php?classe=<?= urlencode($classe_filtro) ?>" class="btn btn-primary" target="_blank">🪪 Gerar Cartões da Classe</a>
    <a href="gerar_todos_cartoes.php" class="btn btn-success" target="_blank">🪪 Gerar Todos os Cartões</a>
    <a href="cartao_modelo.php" class="btn btn-warning" target="_blank">📄 Cartão Modelo</a>
    <a href="configurar_cartao.php" class="btn btn-info">⚙️ Configurar Cartão</a>
</div>

<?php include '../includes/footer_escola.php'; ?>