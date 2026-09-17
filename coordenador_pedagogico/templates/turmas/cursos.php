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
// modules/escola/turmas/cursos.php - Gerenciar Cursos
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

// ===== PROCESSAR FORMULÁRIO =====
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $nome_curso = $_POST['nome_curso'] ?? '';
        $codigo_curso = $_POST['codigo_curso'] ?? '';
        $duracao = $_POST['duracao'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        $coordenador = $_POST['coordenador'] ?? '';

        if (empty($nome_curso) || empty($codigo_curso) || empty($duracao)) {
            $erro = 'Preencha todos os campos obrigatórios!';
        } else {
            try {
                // Verificar se já existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM cursos WHERE codigo_curso = ? OR nome_curso = ?");
                $stmt->execute([$codigo_curso, $nome_curso]);
                if ($stmt->fetchColumn() > 0) {
                    $erro = 'Já existe um curso com este código ou nome!';
                } else {
                    // Criar tabela cursos se não existir
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS cursos (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            nome_curso VARCHAR(100) NOT NULL,
                            codigo_curso VARCHAR(20) NOT NULL UNIQUE,
                            duracao VARCHAR(20) NOT NULL,
                            descricao TEXT,
                            coordenador VARCHAR(100),
                            ativo INT DEFAULT 1,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        )
                    ");
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO cursos (nome_curso, codigo_curso, duracao, descricao, coordenador)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$nome_curso, $codigo_curso, $duracao, $descricao, $coordenador]);
                    $sucesso = 'Curso adicionado com sucesso!';
                    $_POST = [];
                }
            } catch (Exception $e) {
                $erro = $e->getMessage();
            }
        }
    } elseif ($_POST['action'] == 'delete') {
        $curso_id = $_POST['curso_id'] ?? 0;
        if ($curso_id) {
            try {
                // Verificar se o curso está sendo usado
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM turmas WHERE curso = (SELECT nome_curso FROM cursos WHERE id = ?)");
                $stmt->execute([$curso_id]);
                if ($stmt->fetchColumn() > 0) {
                    $erro = 'Este curso não pode ser excluído pois está sendo usado em turmas!';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = ?");
                    $stmt->execute([$curso_id]);
                    $sucesso = 'Curso excluído com sucesso!';
                }
            } catch (Exception $e) {
                $erro = $e->getMessage();
            }
        }
    }
}

// ===== BUSCAR CURSOS =====
$cursos = [];
try {
    // Criar tabela se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cursos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nome_curso VARCHAR(100) NOT NULL,
            codigo_curso VARCHAR(20) NOT NULL UNIQUE,
            duracao VARCHAR(20) NOT NULL,
            descricao TEXT,
            coordenador VARCHAR(100),
            ativo INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    $cursos = $pdo->query("SELECT * FROM cursos WHERE ativo = 1 ORDER BY nome_curso")->fetchAll();
} catch (Exception $e) {}


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
    
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        margin-bottom: 25px;
    }
    
    .form-container h3 {
        color: #1a2332;
        margin-bottom: 15px;
        font-size: 16px;
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
    .form-group textarea {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    
    .form-group input:focus,
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
    }
    
    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
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
        font-size: 14px;
        min-width: 500px;
    }
    
    .table th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }
    
    .table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    
    .table tr:hover {
        background: #fafbfc;
    }
    
    .empty-state {
        text-align: center;
        padding: 30px 20px;
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
    
    .curso-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        background: #e0f2fe;
        color: #0369a1;
    }
    
    .nav-turmas {
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
    
    .nav-turmas a {
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
    
    .nav-turmas a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .nav-turmas a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
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
        .nav-turmas {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-turmas a {
            text-align: center;
            justify-content: center;
        }
        .table {
            font-size: 12px;
            min-width: 400px;
        }
        .table th, .table td {
            padding: 8px 10px;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📚 Gerenciar Cursos</h1>
        <p class="subtitle">Cadastro e gestão de cursos</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Navegação -->
<div class="nav-turmas">
    <a href="index.php">📋 Lista de Turmas</a>
    <a href="add.php">➕ Cadastrar Turma</a>
    <a href="cursos.php" class="active">📚 Gerenciar Cursos</a>
    <a href="vagas.php">📊 Verificar Vagas</a>
    <a href="relatorio.php">📈 Relatório</a>
    <a href="exportar.php">📤 Exportar</a>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success">✅ <?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error">❌ <?= $erro ?></div>
<?php endif; ?>

<!-- Formulário para adicionar curso -->
<div class="form-container">
    <h3>➕ Adicionar Novo Curso</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        
        <div class="form-row-3">
            <div class="form-group">
                <label>Nome do Curso <span class="required">*</span></label>
                <input type="text" name="nome_curso" value="<?= htmlspecialchars($_POST['nome_curso'] ?? '') ?>" placeholder="Ex: Ensino Secundário" required>
            </div>
            <div class="form-group">
                <label>Código do Curso <span class="required">*</span></label>
                <input type="text" name="codigo_curso" value="<?= htmlspecialchars($_POST['codigo_curso'] ?? '') ?>" placeholder="Ex: ES-001" required>
            </div>
            <div class="form-group">
                <label>Duração <span class="required">*</span></label>
                <input type="text" name="duracao" value="<?= htmlspecialchars($_POST['duracao'] ?? '') ?>" placeholder="Ex: 3 anos" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Coordenador</label>
                <input type="text" name="coordenador" value="<?= htmlspecialchars($_POST['coordenador'] ?? '') ?>" placeholder="Nome do coordenador">
            </div>
            <div class="form-group" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">➕ Adicionar Curso</button>
            </div>
        </div>
        
        <div class="form-group">
            <label>Descrição</label>
            <textarea name="descricao" placeholder="Descrição detalhada do curso"><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
        </div>
    </form>
</div>

<!-- Lista de Cursos -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome do Curso</th>
                <th>Código</th>
                <th>Duração</th>
                <th>Coordenador</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($cursos) > 0): ?>
                <?php foreach($cursos as $c): ?>
                <tr>
                    <td><strong><?= $c['id'] ?></strong></td>
                    <td><span class="curso-badge"><?= htmlspecialchars($c['nome_curso']) ?></span></td>
                    <td><strong><?= htmlspecialchars($c['codigo_curso']) ?></strong></td>
                    <td><?= htmlspecialchars($c['duracao']) ?></td>
                    <td><?= htmlspecialchars($c['coordenador'] ?? '-') ?></td>
                    <td>
                        <div class="table-actions">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este curso?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="curso_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhum curso cadastrado</h3>
                            <p>Adicione cursos usando o formulário acima.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer_escola.php'; ?>