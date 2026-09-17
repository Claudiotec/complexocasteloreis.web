<?php
// ============================================
// modules/escola/professores/index.php - Lista de Professores
// ============================================

// ============================================
// 1. CARREGAR CONFIGURAÇÕES
// ============================================
require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';
require_once __DIR__ . '/../includes/contadores.php';  // ← 🔥 LINHA ADICIONADA!

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
// 6. FUNÇÕES AUXILIARES
// ============================================
function limparExibicao($texto) {
    if (empty($texto)) return '-';
    $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    $texto = str_replace(['?', '�', '�', '�', '�'], '', $texto);
    return $texto;
}

function isProfessor($valor) {
    if (empty($valor)) return false;
    $valor = trim($valor);
    return stripos($valor, 'professor') !== false;
}

// ============================================
// 7. BUSCAR PROFESSORES - USANDO A FUNÇÃO CORRETA
// ============================================
$professores = [];
$totalProfessores = 0;
$mensagem_erro = '';

try {
    $pdo = conectarBanco();
    
    $check = $pdo->query("SHOW TABLES LIKE 'funcionarios'");
    if ($check->rowCount() > 0) {
        
        // 🔍 BUSCAR PROFESSORES
        $sql = "
            SELECT * FROM funcionarios 
            WHERE categoria_actual LIKE '%Professor%'
               OR categoria_actual LIKE '%professor%'
               OR cargo LIKE '%Professor%'
               OR cargo LIKE '%professor%'
               OR funcao_instituicao LIKE '%Professor%'
               OR funcao_instituicao LIKE '%professor%'
            ORDER BY nome
        ";
        $professores = $pdo->query($sql)->fetchAll();
        
        // 🔥 USAR A FUNÇÃO CENTRALIZADA PARA CONTAR
        $totalProfessores = contarProfessores();  // ← 🔥 LINHA CORRIGIDA!
        
    } else {
        $mensagem_erro = "⚠️ Tabela 'funcionarios' não encontrada.";
    }
} catch (Exception $e) {
    $mensagem_erro = "Erro ao carregar dados: " . $e->getMessage();
    $totalProfessores = count($professores);
}

// ============================================
// 8. INCLUIR HEADER
// ============================================
include '../includes/header_escola.php';
?>

<!-- ============================================
     9. CONTEÚDO
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
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }
    
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .btn-secondary:hover {
        background: #e2e8f0;
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
    
    .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-card {
        background: white;
        padding: 22px 24px;
        border-radius: 14px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        border-left: 5px solid #2ecc71;
        transition: all 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .stat-card .number {
        font-size: 32px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .stat-card .label {
        font-size: 14px;
        color: #94a3b8;
        margin: 5px 0 0;
        font-weight: 500;
    }
    
    .stat-card .icon {
        font-size: 34px;
        display: block;
        margin-bottom: 8px;
    }
    
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
        min-width: 1000px;
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
        white-space: nowrap;
    }
    
    .table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    
    .table tr:hover {
        background: #fafbfc;
    }
    
    .table .nome {
        font-weight: 600;
        color: #1a2332;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-ativo {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-inativo {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .status-ferias {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-licenca {
        background: #dbeafe;
        color: #1e40af;
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
    
    .table-actions {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .categoria-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        background: #fef3c7;
        color: #92400e;
    }
    
    .cargo-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        background: #dbeafe;
        color: #1e40af;
    }
    
    .funcao-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        background: #e0f2fe;
        color: #0369a1;
    }
    
    .disciplina-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        background: #f0fdf4;
        color: #166534;
    }
    
    .origem-badge {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 9px;
        font-weight: 600;
        background: #e2e8f0;
        color: #4a5568;
        margin-left: 4px;
    }
    
    .origem-categoria { background: #fef3c7; color: #92400e; }
    .origem-cargo { background: #dbeafe; color: #1e40af; }
    .origem-funcao { background: #e0f2fe; color: #0369a1; }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .actions {
            flex-direction: column;
        }
        .actions .btn {
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
            min-width: 800px;
        }
        .table th, .table td {
            padding: 6px 8px;
        }
        .btn-sm {
            font-size: 10px;
            padding: 3px 8px;
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
        <h1>👨‍🏫 Professores</h1>
        <p class="subtitle">Gestão de professores e colaboradores</p>
        <p style="font-size:12px;color:#94a3b8;margin-top:4px;">
            🔍 Busca em cascata: 
            <span class="origem-badge origem-categoria">categoria_actual</span> → 
            <span class="origem-badge origem-cargo">cargo</span> → 
            <span class="origem-badge origem-funcao">funcao_instituicao</span>
        </p>
    </div>
    <div class="actions">
        <?php if ($pode_criar): ?>
            <a href="add.php" class="btn btn-primary">➕ Novo Professor</a>
        <?php endif; ?>
        <a href="../index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<?php if (!empty($mensagem_erro)): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:15px;border-radius:8px;margin-bottom:20px;">⚠️ <?= $mensagem_erro ?></div>
<?php endif; ?>

<!-- Stats Card - Total -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="icon">👥</span>
        <div class="number" style="color: #2ecc71; font-size: 36px;"><?= $totalProfessores ?></div>
        <div class="label">Total de Professores</div>
        <?php if ($totalProfessores > 0): ?>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">
                <?= count($professores) ?> registros encontrados
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tabela -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Cargo</th>
                <th>Função</th>
                <th>Disciplina</th>
                <th>Contacto</th>
                <th>Email</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($professores) > 0): ?>
                <?php foreach($professores as $prof): 
                    $origem = '';
                    $origem_class = '';
                    if (isProfessor($prof['categoria_actual'] ?? '')) {
                        $origem = 'categoria_actual';
                        $origem_class = 'origem-categoria';
                    } elseif (isProfessor($prof['cargo'] ?? '')) {
                        $origem = 'cargo';
                        $origem_class = 'origem-cargo';
                    } elseif (isProfessor($prof['funcao_instituicao'] ?? '')) {
                        $origem = 'funcao_instituicao';
                        $origem_class = 'origem-funcao';
                    }
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($prof['id'] ?? '-') ?></strong></td>
                    <td class="nome">
                        <?= limparExibicao($prof['nome']) ?>
                        <?php if ($origem): ?>
                            <span class="origem-badge <?= $origem_class ?>">
                                <?= str_replace('_', ' ', $origem) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="categoria-badge">
                            <?= limparExibicao($prof['categoria_actual'] ?? '-') ?>
                        </span>
                    </td>
                    <td>
                        <span class="cargo-badge">
                            <?= limparExibicao($prof['cargo'] ?? '-') ?>
                        </span>
                    </td>
                    <td>
                        <span class="funcao-badge">
                            <?= limparExibicao($prof['funcao_instituicao'] ?? '-') ?>
                        </span>
                    </td>
                    <td>
                        <span class="disciplina-badge">
                            <?= limparExibicao($prof['disciplina_lecciona'] ?? '-') ?>
                        </span>
                    </td>
                    <td><?= limparExibicao($prof['contacto_telefonico'] ?? $prof['telefone'] ?? '-') ?></td>
                    <td><?= limparExibicao($prof['email'] ?? '-') ?></td>
                    <td>
                        <?php 
                        $status = strtolower($prof['status'] ?? 'ativo');
                        $status_label = ucfirst($status);
                        ?>
                        <span class="status-badge status-<?= $status ?>">
                            <?= $status_label ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="view.php?id=<?= $prof['id'] ?>" class="btn btn-sm btn-info">Ver</a>
                            <?php if ($pode_editar): ?>
                                <a href="edit.php?id=<?= $prof['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                            <?php endif; ?>
                            <?php if ($pode_excluir): ?>
                                <a href="delete.php?id=<?= $prof['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir este professor?')">Excluir</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <span class="icon">📭</span>
                            <h3>Nenhum professor cadastrado</h3>
                            <p>Clique em "Novo Professor" para começar a cadastrar.</p>
                            <p style="font-size:12px;color:#94a3b8;margin-top:5px;">
                                <strong>Dica:</strong> Verifique os campos <strong>categoria_actual</strong>, 
                                <strong>cargo</strong> ou <strong>funcao_instituicao</strong> com valor "Professor".
                            </p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// ============================================
// 10. INCLUIR FOOTER
// ============================================
include '../includes/footer_escola.php';
?>