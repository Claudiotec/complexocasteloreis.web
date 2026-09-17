<?php
// ============================================
// modules/escola/frequencia/marcar.php - Marcar Presença (CORRIGIDO)
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

// ============================================
// 1. RECEBE PARÂMETROS
// ============================================
$turma_id = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : (isset($_POST['turma_id']) ? (int)$_POST['turma_id'] : 0);
$data = isset($_GET['data']) ? $_GET['data'] : (isset($_POST['data']) ? $_POST['data'] : date('Y-m-d'));
$mensagem = '';
$tipo_mensagem = '';

// ============================================
// 2. BUSCAR TURMAS
// ============================================
$turmas = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM turmas WHERE status = 'ativa' OR status = 1 ORDER BY nome");
    $turmas = $stmt->fetchAll();
} catch (Exception $e) {
    $turmas = [];
}

// ============================================
// 3. BUSCAR ALUNOS DA TURMA (USANDO CAMPO TURMA)
// ============================================
$alunos = [];
$frequencias_existentes = [];

if ($turma_id > 0) {
    try {
        // Primeiro, buscar o nome da turma para filtrar
        $stmt_turma = $pdo->prepare("SELECT nome FROM turmas WHERE id = ?");
        $stmt_turma->execute([$turma_id]);
        $turma_nome = $stmt_turma->fetchColumn();
        
        if ($turma_nome) {
            // Buscar alunos que estão nesta turma (usando o campo TURMA da tabela alunos)
            $sql = "
                SELECT 
                    id,
                    nome,
                    TURMA
                FROM alunos 
                WHERE TURMA = ?
                AND status = 'ativo'
                ORDER BY nome ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$turma_nome]);
            $alunos = $stmt->fetchAll();
        }
        
        // Buscar frequências já registradas para esta data e turma
        $sql_freq = "
            SELECT aluno_id, status 
            FROM frequencia 
            WHERE turma_id = ? AND data = ?
        ";
        $stmt_freq = $pdo->prepare($sql_freq);
        $stmt_freq->execute([$turma_id, $data]);
        $frequencias_existentes = $stmt_freq->fetchAll(PDO::FETCH_KEY_PAIR);
        
    } catch (Exception $e) {
        $mensagem = 'Erro ao buscar alunos: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// ============================================
// 4. PROCESSAR FORMULÁRIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
    $turma_id_post = (int)$_POST['turma_id'];
    $data_post = $_POST['data'];
    $status = $_POST['status'] ?? [];
    
    try {
        // Iniciar transação
        $pdo->beginTransaction();
        
        // Primeiro, remover frequências existentes para esta data/turma
        $stmt_delete = $pdo->prepare("DELETE FROM frequencia WHERE turma_id = ? AND data = ?");
        $stmt_delete->execute([$turma_id_post, $data_post]);
        
        // Inserir novas frequências
        $stmt_insert = $pdo->prepare("
            INSERT INTO frequencia (aluno_id, turma_id, data, status, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $inseridos = 0;
        foreach ($status as $aluno_id => $status_aluno) {
            if (!empty($status_aluno)) {
                $stmt_insert->execute([$aluno_id, $turma_id_post, $data_post, $status_aluno]);
                $inseridos++;
            }
        }
        
        // Confirmar transação
        $pdo->commit();
        
        $mensagem = "✅ Frequência salva com sucesso! ($inseridos registros)";
        $tipo_mensagem = 'success';
        
        // Recarregar dados
        $sql_freq = "SELECT aluno_id, status FROM frequencia WHERE turma_id = ? AND data = ?";
        $stmt_freq = $pdo->prepare($sql_freq);
        $stmt_freq->execute([$turma_id_post, $data_post]);
        $frequencias_existentes = $stmt_freq->fetchAll(PDO::FETCH_KEY_PAIR);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = '❌ Erro ao salvar frequência: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// ============================================
// 5. INCLUIR HEADER
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
    
    .filtros {
        background: white;
        padding: 18px 20px;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        margin-bottom: 20px;
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
    }
    .filtros .form-group select:focus,
    .filtros .form-group input:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    .filtros .btn-group {
        display: flex;
        gap: 8px;
        flex: 0 0 auto;
    }
    
    .alert {
        padding: 12px 18px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .table-container {
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        overflow: hidden;
    }
    .table-container .table-header {
        padding: 15px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #eef2f7;
        font-weight: 600;
        color: #4a5568;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        min-width: 400px;
    }
    .table th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        background: #fafbfc;
    }
    .table td {
        padding: 10px 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .table tr:hover {
        background: #fafbfc;
    }
    .table select {
        padding: 6px 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 13px;
        background: white;
        min-width: 120px;
    }
    .table select:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
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
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        padding: 15px 20px;
        background: #f8fafc;
        border-top: 1px solid #eef2f7;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    
    .badge-count {
        background: #c9a84c;
        color: #1a2332;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .filtros {
            flex-direction: column;
        }
        .filtros .form-group {
            min-width: 100%;
        }
        .table {
            font-size: 12px;
            min-width: 300px;
        }
        .table th,
        .table td {
            padding: 8px 10px;
        }
        .table select {
            min-width: 90px;
            font-size: 11px;
            padding: 4px 8px;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
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

<!-- ===== PAGE HEADER ===== -->
<div class="page-header">
    <div>
        <h1>📌 Marcar Presença</h1>
        <p class="subtitle">Registro de frequência dos alunos</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- ===== MENSAGEM ===== -->
<?php if (!empty($mensagem)): ?>
    <div class="alert alert-<?php echo $tipo_mensagem; ?>">
        <?php echo htmlspecialchars($mensagem); ?>
    </div>
<?php endif; ?>

<!-- ===== FILTROS ===== -->
<div class="filtros">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%; align-items: flex-end;">
        <div class="form-group">
            <label>Turma *</label>
            <select name="turma_id" required>
                <option value="">Selecione uma turma</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo ($turma_id == $t['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Data *</label>
            <input type="date" name="data" value="<?php echo htmlspecialchars($data); ?>" required>
        </div>
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">🔍 Carregar Alunos</button>
            <a href="marcar.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<!-- ===== LISTA DE ALUNOS ===== -->
<?php if ($turma_id > 0): ?>
    <div class="table-container">
        <?php if (count($alunos) > 0): ?>
            <div class="table-header">
                <span>
                    📋 Lista de Alunos 
                    <span class="badge-count"><?php echo count($alunos); ?></span>
                </span>
                <span style="font-size: 13px; font-weight: normal; color: #94a3b8;">
                    Data: <?php echo date('d/m/Y', strtotime($data)); ?>
                </span>
            </div>
            
            <form method="POST">
                <input type="hidden" name="turma_id" value="<?php echo $turma_id; ?>">
                <input type="hidden" name="data" value="<?php echo $data; ?>">
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Aluno</th>
                                <th style="min-width: 150px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; ?>
                            <?php foreach ($alunos as $aluno): ?>
                                <?php 
                                $status_atual = isset($frequencias_existentes[$aluno['id']]) ? $frequencias_existentes[$aluno['id']] : '';
                                ?>
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($aluno['nome']); ?></strong>
                                    </td>
                                    <td>
                                        <select name="status[<?php echo $aluno['id']; ?>]" class="status-select">
                                            <option value="">-- Selecionar --</option>
                                            <option value="presente" <?php echo ($status_atual == 'presente') ? 'selected' : ''; ?>>✅ Presente</option>
                                            <option value="ausente" <?php echo ($status_atual == 'ausente') ? 'selected' : ''; ?>>❌ Ausente</option>
                                            <option value="justificado" <?php echo ($status_atual == 'justificado') ? 'selected' : ''; ?>>📋 Justificado</option>
                                            <option value="atrasado" <?php echo ($status_atual == 'atrasado') ? 'selected' : ''; ?>>⏰ Atrasado</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-success" onclick="marcarTodos('presente')">✅ Marcar Todos Presentes</button>
                    <button type="button" class="btn btn-danger" onclick="marcarTodos('ausente')">❌ Marcar Todos Ausentes</button>
                    <button type="submit" name="salvar" value="1" class="btn btn-primary">💾 Salvar Frequência</button>
                </div>
            </form>
            
        <?php else: ?>
            <div class="empty-state">
                <span class="icon">📭</span>
                <h3>Nenhum aluno matriculado nesta turma</h3>
                <p>Cadastre alunos e faça matrículas para registrar frequência.</p>
                <div style="margin-top: 15px;">
                    <a href="../alunos/index.php" class="btn btn-primary">📝 Gerenciar Alunos</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ===== AÇÕES RÁPIDAS ===== -->
<div class="acoes-rapidas">
    <a href="index.php" class="btn btn-secondary">📋 Lista de Frequência</a>
    <a href="../relatorios/frequencia.php" class="btn btn-info">📈 Relatório</a>
</div>

<script>
// Função para marcar todos os alunos com um status específico
function marcarTodos(status) {
    var selects = document.querySelectorAll('.status-select');
    selects.forEach(function(select) {
        select.value = status;
        // Disparar evento change para atualizar visual
        var event = new Event('change');
        select.dispatchEvent(event);
    });
}

// Adicionar evento change para mudar cor do select
document.addEventListener('DOMContentLoaded', function() {
    var selects = document.querySelectorAll('.status-select');
    selects.forEach(function(select) {
        select.addEventListener('change', function() {
            var status = this.value;
            var colors = {
                'presente': '#d1fae5',
                'ausente': '#fee2e2',
                'justificado': '#fef3c7',
                'atrasado': '#dbeafe'
            };
            this.style.backgroundColor = colors[status] || 'white';
        });
        // Aplicar cor inicial
        var event = new Event('change');
        select.dispatchEvent(event);
    });
});
</script>

<?php include '../includes/footer_escola.php'; ?>