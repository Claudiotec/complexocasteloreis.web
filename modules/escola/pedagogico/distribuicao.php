<?php
// modules/escola/index.php
// Ou esta versão (mais robusta):
require_once(__DIR__ . '/../includes/verificar_permissao_escola.php');

$permissoes = verificarMultiplasPermissoesEscola('escola', ['visualizar', 'criar', 'editar', 'excluir']);

if ($permissoes['visualizar']) {
    // Mostra conteúdo
}

if ($permissoes['criar']) {
    // Mostra botão criar
}
?>




<?php
// ============================================
// modules/escola/pedagogico/distribuicao.php - Distribuição de Professores
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

// ===== BUSCAR PROFESSORES =====
$professores = [];
try {
    $professores = $pdo->query("SELECT id, nome FROM professores WHERE status = 'ativo' ORDER BY nome")->fetchAll();
} catch (Exception $e) {}

// ===== BUSCAR TURMAS =====
$turmas = [];
try {
    $turmas = $pdo->query("SELECT id, nome, classe, curso, turno FROM turmas WHERE status = 'ativa' ORDER BY classe, nome")->fetchAll();
} catch (Exception $e) {}

// ===== BUSCAR DISCIPLINAS =====
$disciplinas = [];
try {
    $disciplinas = $pdo->query("SELECT id, nome, descricao, professor_id, carga_horaria FROM disciplinas WHERE status = 'ativa' ORDER BY nome")->fetchAll();
} catch (Exception $e) {
    $disciplinas = [];
}

// ===== BUSCAR DISTRIBUIÇÕES =====
$distribuicoes = [];
try {
    $distribuicoes = $pdo->query("
        SELECT d.*, 
               CASE d.tipo 
                   WHEN 'DIRETOR_TURMA' THEN 'Diretor de Turma'
                   WHEN 'COORDENADOR' THEN 'Coordenador'
                   ELSE 'Professor'
               END as funcao
        FROM distribuicao_professores d
        ORDER BY d.tipo, d.professor_nome, d.classe, d.turma_nome
    ")->fetchAll();
} catch (Exception $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS distribuicao_professores (
                id INT PRIMARY KEY AUTO_INCREMENT,
                professor_id VARCHAR(50) NOT NULL,
                professor_nome VARCHAR(100) NOT NULL,
                turma_id VARCHAR(20) NOT NULL,
                turma_nome VARCHAR(50) NOT NULL,
                classe VARCHAR(20) NOT NULL,
                disciplinas TEXT NOT NULL,
                ano_letivo VARCHAR(10) NOT NULL,
                tipo ENUM('PROFESSOR','COORDENADOR','DIRETOR_TURMA') DEFAULT 'PROFESSOR',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $distribuicoes = [];
    } catch (Exception $e2) {}
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
    
    .nav-pedagogico {
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
    
    .nav-pedagogico a {
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
    
    .nav-pedagogico a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .nav-pedagogico a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    
    .tabs-container {
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        margin-bottom: 25px;
    }
    
    .tabs-header {
        display: flex;
        border-bottom: 2px solid #eef2f7;
        background: #fafbfc;
        flex-wrap: wrap;
    }
    
    .tabs-header .tab-btn {
        padding: 12px 24px;
        background: none;
        border: none;
        font-size: 14px;
        font-weight: 600;
        color: #4a5568;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
    }
    
    .tabs-header .tab-btn:hover {
        color: #c9a84c;
    }
    
    .tabs-header .tab-btn.active {
        color: #c9a84c;
    }
    
    .tabs-header .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: #c9a84c;
    }
    
    .tab-content {
        display: none;
        padding: 20px;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #1a2332;
        font-size: 13px;
    }
    
    .form-group label .required {
        color: #e74c3c;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    
    .form-group textarea {
        min-height: 60px;
        resize: vertical;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 10px;
    }
    
    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-bottom: 10px;
    }
    
    .listbox-container {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        overflow: hidden;
        min-height: 120px;
    }
    
    .listbox-container select {
        width: 100%;
        min-height: 120px;
        border: none;
        padding: 5px;
        font-size: 13px;
    }
    
    .listbox-container select option {
        padding: 5px 8px;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .listbox-container select option:checked {
        background: #c9a84c;
        color: #1a2332;
    }
    
    .listbox-actions {
        display: flex;
        gap: 5px;
        margin-top: 5px;
        flex-wrap: wrap;
    }
    
    .listbox-actions .btn-sm {
        font-size: 11px;
        padding: 3px 10px;
    }
    
    .status-info {
        padding: 8px 12px;
        background: #f8fafc;
        border-radius: 6px;
        font-size: 13px;
        color: #4a5568;
        margin-top: 10px;
    }
    
    .status-info strong {
        color: #1a2332;
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
    
    .table .tipo-badge {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
    }
    
    .tipo-professor {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .tipo-coordenador {
        background: #fef3c7;
        color: #92400e;
    }
    
    .tipo-diretor {
        background: #d1fae5;
        color: #065f46;
    }
    
    .disciplina-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 500;
        background: #e0f2fe;
        color: #0369a1;
        margin: 2px;
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
    
    .table-actions {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
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
    
    .alert-warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
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
    
    .info-box {
        background: #f8fafc;
        padding: 10px 14px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        font-size: 12px;
        color: #4a5568;
        margin-top: 5px;
    }
    
    .info-box strong {
        color: #1a2332;
    }
    
    .disciplina-card {
        background: #f8fafc;
        padding: 10px 14px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .disciplina-card .info {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .disciplina-card .info .nome {
        font-weight: 600;
        color: #1a2332;
    }
    
    .disciplina-card .info .descricao {
        font-size: 12px;
        color: #94a3b8;
    }
    
    .disciplina-card .actions {
        display: flex;
        gap: 5px;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .form-row-3 {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .tabs-header {
            flex-direction: column;
        }
        .tabs-header .tab-btn {
            text-align: center;
            border-bottom: 1px solid #eef2f7;
        }
        .tabs-header .tab-btn.active::after {
            display: none;
        }
        .nav-pedagogico {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-pedagogico a {
            text-align: center;
            justify-content: center;
        }
        .table {
            font-size: 12px;
            min-width: 650px;
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
        .disciplina-card {
            flex-direction: column;
            align-items: stretch;
            text-align: center;
        }
        .disciplina-card .info {
            justify-content: center;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📋 Distribuição de Professores</h1>
        <p class="subtitle">Gestão de professores, coordenadores, diretores de turma e disciplinas</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Navegação -->
<div class="nav-pedagogico">
    <a href="index.php">📊 Dashboard</a>
    <a href="planos/">📋 Planos de Ensino</a>
    <a href="atividades/">📝 Atividades</a>
    <a href="avaliacoes/">📊 Avaliações</a>
    <a href="projetos/">🎯 Projetos</a>
    <a href="distribuicao.php" class="active">👨‍🏫 Distribuição</a>
    <a href="relatorios/">📈 Relatórios</a>
</div>

<!-- Tabs -->
<div class="tabs-container">
    <div class="tabs-header">
        <button class="tab-btn active" data-tab="tab-professores">👨‍🏫 Professores</button>
        <button class="tab-btn" data-tab="tab-coordenadores">📋 Coordenadores</button>
        <button class="tab-btn" data-tab="tab-diretores">🎯 Diretores de Turma</button>
        <button class="tab-btn" data-tab="tab-disciplinas">📚 Disciplinas</button>
        <button class="tab-btn" data-tab="tab-visualizar">📊 Visualizar</button>
    </div>

    <!-- ===== ABA 1: PROFESSORES ===== -->
    <div class="tab-content active" id="tab-professores">
        <form method="POST" action="distribuicao_salvar.php">
            <input type="hidden" name="tipo" value="PROFESSOR">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Professor <span class="required">*</span></label>
                    <select name="professor_id" required>
                        <option value="">Selecione um professor</option>
                        <?php foreach($professores as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Classe <span class="required">*</span></label>
                    <select name="classe" id="classe_professor" required>
                        <option value="">Selecione uma classe</option>
                        <?php 
                        $classes = array_unique(array_column($turmas, 'classe'));
                        sort($classes);
                        foreach($classes as $c): 
                        ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Turmas (seleção múltipla) <span class="required">*</span></label>
                <div class="listbox-container">
                    <select name="turmas[]" id="turmas_professor" multiple size="4">
                        <?php foreach($turmas as $t): ?>
                        <option value="<?= $t['id'] ?>|<?= htmlspecialchars($t['nome']) ?>" data-classe="<?= $t['classe'] ?>">
                            <?= $t['id'] ?> - <?= htmlspecialchars($t['nome']) ?> (<?= htmlspecialchars($t['classe']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="listbox-actions">
                    <button type="button" class="btn btn-sm btn-info" onclick="selecionarTodos('turmas_professor')">Selecionar Todos</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="limparSelecao('turmas_professor')">Limpar</button>
                    <button type="button" class="btn btn-sm btn-warning" onclick="filtrarTurmasPorClasse('turmas_professor', 'classe_professor')">Filtrar por Classe</button>
                </div>
            </div>
            
            <div class="form-group">
                <label>Disciplinas (seleção múltipla) <span class="required">*</span></label>
                <div class="listbox-container">
                    <select name="disciplinas[]" id="disciplinas_professor" multiple size="4">
                        <?php 
                        $disciplinas_list = [];
                        try {
                            $stmt = $pdo->query("SELECT nome FROM disciplinas WHERE status = 'ativa' ORDER BY nome");
                            $disciplinas_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        } catch (Exception $e) {}
                        
                        if (empty($disciplinas_list)) {
                            $disciplinas_list = ['Matemática', 'Português', 'História', 'Geografia', 'Ciências', 'Inglês', 'Francês', 'Física', 'Química', 'Biologia', 'Educação Física', 'Artes'];
                        }
                        foreach($disciplinas_list as $d): 
                        ?>
                        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="listbox-actions">
                    <button type="button" class="btn btn-sm btn-info" onclick="selecionarTodos('disciplinas_professor')">Selecionar Todas</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="limparSelecao('disciplinas_professor')">Limpar</button>
                </div>
                <div style="margin-top: 5px;">
                    <a href="#tab-disciplinas" class="btn btn-sm btn-warning" onclick="document.querySelector('[data-tab=\'tab-disciplinas\']').click();">➕ Adicionar Nova Disciplina</a>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Ano Letivo <span class="required">*</span></label>
                    <input type="text" name="ano_letivo" value="<?= date('Y') . '/' . (date('Y')+1) ?>" required>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">✅ Adicionar Distribuição</button>
                </div>
            </div>
        </form>
        
        <div class="status-info">
            <span id="status_professor">Turmas: 0 | Disciplinas: 0</span>
        </div>
    </div>

    <!-- ===== ABA 2: COORDENADORES ===== -->
    <div class="tab-content" id="tab-coordenadores">
        <form method="POST" action="distribuicao_salvar.php">
            <input type="hidden" name="tipo" value="COORDENADOR">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Coordenador <span class="required">*</span></label>
                    <select name="professor_id" required>
                        <option value="">Selecione um professor</option>
                        <?php foreach($professores as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo de Coordenação <span class="required">*</span></label>
                    <select name="tipo_coordenacao" required>
                        <option value="">Selecione o tipo</option>
                        <option value="Coordenador de Curso">Coordenador de Curso</option>
                        <option value="Coordenador de Classe">Coordenador de Classe</option>
                        <option value="Coordenador de Departamento">Coordenador de Departamento</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Curso / Classe / Departamento <span class="required">*</span></label>
                <input type="text" name="item" placeholder="Ex: Ensino Secundário, 6ª Classe, Departamento de Matemática" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Ano Letivo <span class="required">*</span></label>
                    <input type="text" name="ano_letivo" value="<?= date('Y') . '/' . (date('Y')+1) ?>" required>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">✅ Adicionar Coordenador</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ===== ABA 3: DIRETORES DE TURMA ===== -->
    <div class="tab-content" id="tab-diretores">
        <form method="POST" action="distribuicao_salvar.php">
            <input type="hidden" name="tipo" value="DIRETOR_TURMA">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Diretor de Turma (Professor) <span class="required">*</span></label>
                    <select name="professor_id" required>
                        <option value="">Selecione um professor</option>
                        <?php foreach($professores as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Classe (para filtrar) <span class="required">*</span></label>
                    <select name="classe_filtro" id="classe_diretor" required>
                        <option value="">Selecione uma classe</option>
                        <?php 
                        $classes = array_unique(array_column($turmas, 'classe'));
                        sort($classes);
                        foreach($classes as $c): 
                        ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Turma (selecionar UMA) <span class="required">*</span></label>
                <div class="listbox-container">
                    <select name="turma_id" id="turma_diretor" size="4">
                        <option value="">Selecione uma turma</option>
                        <?php foreach($turmas as $t): ?>
                        <option value="<?= $t['id'] ?>|<?= htmlspecialchars($t['nome']) ?>" data-classe="<?= $t['classe'] ?>">
                            <?= $t['id'] ?> - <?= htmlspecialchars($t['nome']) ?> (<?= htmlspecialchars($t['classe']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="listbox-actions">
                    <button type="button" class="btn btn-sm btn-warning" onclick="filtrarTurmasPorClasse('turma_diretor', 'classe_diretor')">Filtrar por Classe</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="limparSelecao('turma_diretor')">Limpar</button>
                </div>
            </div>
            
            <div class="form-group">
                <label>Observações (opcional)</label>
                <textarea name="observacoes" placeholder="Observações sobre o Diretor de Turma" rows="2"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Ano Letivo <span class="required">*</span></label>
                    <input type="text" name="ano_letivo" value="<?= date('Y') . '/' . (date('Y')+1) ?>" required>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">✅ Adicionar Diretor de Turma</button>
                </div>
            </div>
        </form>
        
        <div class="info-box">
            <strong>⚠️ Importante:</strong> Cada turma deve ter apenas UM Diretor de Turma. Se já existir, o sistema avisará.
        </div>
    </div>

    <!-- ===== ABA 4: DISCIPLINAS ===== -->
    <div class="tab-content" id="tab-disciplinas">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
            <h3 style="color: #1a2332; margin: 0;">📚 Gerenciar Disciplinas</h3>
            <button class="btn btn-primary" onclick="mostrarFormDisciplina()">➕ Nova Disciplina</button>
        </div>
        
        <!-- Formulário para adicionar disciplina -->
        <div id="formDisciplina" style="display: none; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
            <h4 style="color: #1a2332; margin-bottom: 15px;">📝 Cadastrar Nova Disciplina</h4>
            <form method="POST" action="distribuicao_disciplina_salvar.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome da Disciplina <span class="required">*</span></label>
                        <input type="text" name="nome" placeholder="Ex: Matemática" required>
                    </div>
                    <div class="form-group">
                        <label>Professor Responsável</label>
                        <select name="professor_id">
                            <option value="">Selecione um professor</option>
                            <?php foreach($professores as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" placeholder="Descrição da disciplina" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Carga Horária (horas)</label>
                    <input type="number" name="carga_horaria" placeholder="Ex: 120" min="1">
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-success">💾 Salvar Disciplina</button>
                    <button type="button" class="btn btn-secondary" onclick="esconderFormDisciplina()">Cancelar</button>
                </div>
            </form>
        </div>
        
        <!-- Lista de Disciplinas -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Disciplina</th>
                        <th>Descrição</th>
                        <th>Professor</th>
                        <th>Carga Horária</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($disciplinas) > 0): ?>
                        <?php foreach($disciplinas as $d): ?>
                        <tr>
                            <td><strong><?= $d['id'] ?></strong></td>
                            <td><span class="disciplina-badge"><?= htmlspecialchars($d['nome']) ?></span></td>
                            <td><?= htmlspecialchars(substr($d['descricao'] ?? '', 0, 30)) ?></td>
                            <td>
                                <?php 
                                if ($d['professor_id']) {
                                    $prof_nome = '';
                                    foreach($professores as $p) {
                                        if ($p['id'] == $d['professor_id']) {
                                            $prof_nome = $p['nome'];
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($prof_nome);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?= $d['carga_horaria'] ?? '-' ?>h</td>
                            <td>
                                <div class="table-actions">
                                    <a href="distribuicao_disciplina_editar.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                    <a href="distribuicao_disciplina_remover.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta disciplina?')">Excluir</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <span class="icon">📭</span>
                                    <h3>Nenhuma disciplina cadastrada</h3>
                                    <p>Clique em "Nova Disciplina" para começar.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== ABA 5: VISUALIZAR ===== -->
    <div class="tab-content" id="tab-visualizar">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
            <div>
                <span style="font-weight: 600; color: #1a2332;">📊 Distribuições Existentes</span>
                <span class="badge" style="background: #c9a84c; color: #1a2332; padding: 2px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px;">
                    <?= count($distribuicoes) ?> registros
                </span>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="distribuicao_exportar.php" class="btn btn-sm btn-success">📤 Exportar Excel</a>
                <a href="distribuicao_relatorio.php" class="btn btn-sm btn-info">📄 Relatório HTML</a>
                <button class="btn btn-sm btn-danger" onclick="limparTudo()">🗑️ Limpar Tudo</button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Professor</th>
                        <th>ID Turma</th>
                        <th>Turma</th>
                        <th>Classe</th>
                        <th>Disciplinas/Obs.</th>
                        <th>Ano</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($distribuicoes) > 0): ?>
                        <?php foreach($distribuicoes as $d): ?>
                        <tr>
                            <td><strong><?= $d['id'] ?></strong></td>
                            <td>
                                <span class="tipo-badge tipo-<?= strtolower($d['tipo'] == 'PROFESSOR' ? 'professor' : ($d['tipo'] == 'COORDENADOR' ? 'coordenador' : 'diretor')) ?>">
                                    <?= $d['funcao'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($d['professor_nome']) ?></td>
                            <td><?= htmlspecialchars($d['turma_id']) ?></td>
                            <td><?= htmlspecialchars($d['turma_nome']) ?></td>
                            <td><?= htmlspecialchars($d['classe']) ?></td>
                            <td><?= htmlspecialchars(substr($d['disciplinas'], 0, 30)) ?></td>
                            <td><?= htmlspecialchars($d['ano_letivo']) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="distribuicao_editar.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                    <a href="distribuicao_remover.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?')">Excluir</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <span class="icon">📭</span>
                                    <h3>Nenhuma distribuição cadastrada</h3>
                                    <p>Adicione distribuições nas abas acima.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações rápidas:</span>
    <a href="distribuicao_relatorio.php" class="btn btn-info">📄 Relatório Completo</a>
    <a href="distribuicao_exportar.php" class="btn btn-success">📤 Exportar Excel</a>
    <a href="distribuicao_limpar.php" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja limpar todos os dados?')">🗑️ Limpar Tudo</a>
</div>

<script>
    // ===== FUNÇÕES PARA LISTBOX =====
    function selecionarTodos(id) {
        var select = document.getElementById(id);
        if (select) {
            for (var i = 0; i < select.options.length; i++) {
                select.options[i].selected = true;
            }
        }
    }
    
    function limparSelecao(id) {
        var select = document.getElementById(id);
        if (select) {
            for (var i = 0; i < select.options.length; i++) {
                select.options[i].selected = false;
            }
        }
    }
    
    function filtrarTurmasPorClasse(listboxId, classeId) {
        var select = document.getElementById(listboxId);
        var classeSelect = document.getElementById(classeId);
        var classe = classeSelect ? classeSelect.value : '';
        
        if (!classe) {
            alert('Selecione uma classe primeiro!');
            return;
        }
        
        limparSelecao(listboxId);
        
        for (var i = 0; i < select.options.length; i++) {
            var option = select.options[i];
            var dataClasse = option.getAttribute('data-classe');
            if (dataClasse && dataClasse == classe) {
                option.style.display = '';
            } else if (option.value === '') {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        }
        
        for (var i = 0; i < select.options.length; i++) {
            var option = select.options[i];
            var dataClasse = option.getAttribute('data-classe');
            if (dataClasse && dataClasse == classe) {
                option.selected = true;
            }
        }
        
        atualizarStatus(listboxId);
    }
    
    function atualizarStatus(listboxId) {
        var select = document.getElementById(listboxId);
        if (!select) return;
        
        var count = 0;
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].selected && select.options[i].value !== '') {
                count++;
            }
        }
        
        var statusEl = document.getElementById('status_professor');
        if (statusEl) {
            var disciplinasSelect = document.getElementById('disciplinas_professor');
            var discCount = 0;
            if (disciplinasSelect) {
                for (var i = 0; i < disciplinasSelect.options.length; i++) {
                    if (disciplinasSelect.options[i].selected) {
                        discCount++;
                    }
                }
            }
            statusEl.textContent = 'Turmas: ' + count + ' | Disciplinas: ' + discCount;
        }
    }
    
    // ===== TABS =====
    document.addEventListener('DOMContentLoaded', function() {
        var tabs = document.querySelectorAll('.tab-btn');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                tabs.forEach(function(t) {
                    t.classList.remove('active');
                });
                this.classList.add('active');
                
                var contents = document.querySelectorAll('.tab-content');
                contents.forEach(function(c) {
                    c.classList.remove('active');
                });
                
                var targetId = this.getAttribute('data-tab');
                var targetContent = document.getElementById(targetId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });
        
        var turmasSelect = document.getElementById('turmas_professor');
        var disciplinasSelect = document.getElementById('disciplinas_professor');
        
        if (turmasSelect) {
            turmasSelect.addEventListener('change', function() {
                atualizarStatus('turmas_professor');
            });
        }
        
        if (disciplinasSelect) {
            disciplinasSelect.addEventListener('change', function() {
                atualizarStatus('disciplinas_professor');
            });
        }
        
        var classeProfessor = document.getElementById('classe_professor');
        if (classeProfessor) {
            classeProfessor.addEventListener('change', function() {
                filtrarTurmasPorClasse('turmas_professor', 'classe_professor');
            });
        }
        
        var classeDiretor = document.getElementById('classe_diretor');
        if (classeDiretor) {
            classeDiretor.addEventListener('change', function() {
                filtrarTurmasPorClasse('turma_diretor', 'classe_diretor');
            });
        }
    });
    
    // ===== FUNÇÕES PARA DISCIPLINAS =====
    function mostrarFormDisciplina() {
        document.getElementById('formDisciplina').style.display = 'block';
        document.getElementById('formDisciplina').scrollIntoView({ behavior: 'smooth' });
    }
    
    function esconderFormDisciplina() {
        document.getElementById('formDisciplina').style.display = 'none';
    }
    
    function limparTudo() {
        if (confirm('TEM CERTEZA?\n\nEsta ação irá apagar TODOS os dados da tabela!\n\nEsta operação não pode ser desfeita.')) {
            window.location.href = 'distribuicao_limpar.php?confirmar=1';
        }
    }
</script>

<?php include '../includes/footer_escola.php'; ?>