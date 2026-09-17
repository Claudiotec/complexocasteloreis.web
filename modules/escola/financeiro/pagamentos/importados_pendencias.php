<?php
// ============================================
// modules/escola/financeiro/pagamentos/importados_pendencias.php
// Visualização completa da tabela pendencias_anterior
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

// Configurar header para UTF-8
header('Content-Type: text/html; charset=utf-8');

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
// CLASSES PRÉ-DEFINIDAS
// ============================================
$CLASSES_PRE_DEFINIDAS = [
    'PRÉ' => '1ª',
    'PRE' => '1ª',
    'PreA' => '1ª',
    'PREB' => '1ª',
    '1ª' => '2ª',
    '2ª' => '3ª',
    '3ª' => '4ª',
    '4ª' => '5ª',
    '5ª' => '6ª',
    '6ª' => '7ª',
    '7ª' => '8ª',
    '8ª' => '9ª',
    '9ª' => '10ª',
    '10ª' => '11ª',
    '11ª' => '12ª'
];

$erro = '';
$sucesso = '';
$alunos = [];
$classes_disponiveis = [];

include '../../includes/header_escola.php';

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function limparString($texto) {
    if (empty($texto)) return '';
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'auto');
    }
    $texto = preg_replace('/[[:cntrl:]]/', '', $texto);
    $texto = str_replace(['?', '�', '�', '�', '�', '�', '�'], '', $texto);
    $texto = trim($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return $texto;
}

function normalizarClasse($classe) {
    if (empty($classe)) return '';
    $classe = limparString($classe);
    $classe = trim($classe);
    $classe = str_replace(' ', '', $classe);
    $classe = str_replace(['?', '�', '�', '�', '�', '�', '�', '?'], '', $classe);
    
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

function normalizarStatus($status) {
    if (empty($status)) return 'Pendente';
    $status = limparString($status);
    $status = strtolower($status);
    
    if ($status == 'pago' || $status == 'confirmado') return 'Pago ✅';
    if ($status == 'pendente' || $status == 'pendent' || $status == 'nulo') return 'Pendente ⏳';
    if ($status == 'cancelado' || $status == 'cancel') return 'Cancelado ❌';
    if ($status == 'isento') return 'Isento ➖';
    
    return ucfirst($status);
}

function formatarStatusBadge($status) {
    $status_clean = str_replace(['✅', '⏳', '❌', '➖'], '', $status);
    $status_clean = trim($status_clean);
    return $status_clean;
}

// ============================================
// PROCESSAR LIMPEZA DE TODOS OS DADOS
// ============================================
if (isset($_POST['limpar_todos'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM pendencias_anterior");
        $total = $stmt->fetch()['total'];
        
        $pdo->exec("DELETE FROM pendencias_anterior");
        $pdo->exec("ALTER TABLE pendencias_anterior AUTO_INCREMENT = 1");
        
        $pdo->commit();
        $sucesso = "✅ Todos os $total alunos foram removidos com sucesso!";
        
        $alunos = [];
        $classes_disponiveis = [];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = 'Erro ao limpar dados: ' . $e->getMessage();
    }
}

// ============================================
// PROCESSAR EXCLUSÃO INDIVIDUAL COM SENHA
// ============================================
if (isset($_POST['excluir_registro']) && isset($_POST['aluno_id'])) {
    $aluno_id = intval($_POST['aluno_id']);
    $senha_digitada = isset($_POST['senha_exclusao']) ? trim($_POST['senha_exclusao']) : '';
    $senha_correta = 'Claudtec2011';
    
    if ($senha_digitada === $senha_correta) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT nome_aluno FROM pendencias_anterior WHERE id = ?");
            $stmt->execute([$aluno_id]);
            $aluno = $stmt->fetch();
            
            if (!$aluno) {
                $erro = 'Aluno não encontrado!';
                $pdo->rollBack();
            } else {
                $nome_aluno = limparString($aluno['nome_aluno']);
                
                $stmt = $pdo->prepare("DELETE FROM pendencias_anterior WHERE id = ?");
                $stmt->execute([$aluno_id]);
                
                $pdo->commit();
                $sucesso = "✅ Aluno <strong>" . htmlspecialchars($nome_aluno, ENT_QUOTES, 'UTF-8') . "</strong> excluído com sucesso!";
                
                // Recarregar lista
                $where_conditions = [];
                if (!empty($busca)) {
                    $where_conditions[] = "(nome_aluno LIKE '%$busca%' OR id LIKE '%$busca%')";
                }
                if (!empty($filtro_classe)) {
                    $where_conditions[] = "classe = '$filtro_classe'";
                }
                $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                $sql = "SELECT * FROM pendencias_anterior $where_sql ORDER BY classe, nome_aluno";
                $stmt = $pdo->query($sql);
                $alunos = $stmt->fetchAll();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = 'Erro ao excluir: ' . $e->getMessage();
        }
    } else {
        $erro = '❌ Senha incorreta! Digite a senha correta para excluir.';
    }
}



// ============================================
// PROCESSAR EDIÇÃO INDIVIDUAL - CORRIGIDO
// ============================================
if (isset($_POST['editar_registro']) && isset($_POST['aluno_id_edit'])) {
    $aluno_id = intval($_POST['aluno_id_edit']);
    $senha_digitada = isset($_POST['senha_edicao']) ? trim($_POST['senha_edicao']) : '';
    $senha_correta = 'Claudtec2011';
    
    if ($senha_digitada === $senha_correta) {
        try {
            $pdo->beginTransaction();
            
            // Campos obrigatórios
            $nome = limparString($_POST['edit_nome'] ?? '');
            $classe = limparString($_POST['edit_classe'] ?? '');
            $turma = limparString($_POST['edit_turma'] ?? '');
            
            // Status (com valores padrão)
            $propina_status = limparString($_POST['edit_propina_status'] ?? 'Pendente');
            $propina_quantidade = intval($_POST['edit_propina_quantidade'] ?? 0);
            $transporte_status = limparString($_POST['edit_transporte_status'] ?? 'Pendente');
            $transporte_quantidade = intval($_POST['edit_transporte_quantidade'] ?? 0);
            $folha_prova_1 = limparString($_POST['edit_folha_prova_1'] ?? 'Pendente');
            $folha_prova_2 = limparString($_POST['edit_folha_prova_2'] ?? 'Pendente');
            $folha_prova_3 = limparString($_POST['edit_folha_prova_3'] ?? 'Pendente');
            $boletim_notas_1 = limparString($_POST['edit_boletim_notas_1'] ?? 'Pendente');
            $boletim_notas_2 = limparString($_POST['edit_boletim_notas_2'] ?? 'Pendente');
            $cartao_status = limparString($_POST['edit_cartao_status'] ?? 'Pendente');
            
            if (empty($nome)) {
                throw new Exception('Nome do aluno é obrigatório!');
            }
            
            // Query com os nomes CORRETOS das colunas
            $stmt = $pdo->prepare("
                UPDATE pendencias_anterior SET
                    nome_aluno = :nome,
                    classe = :classe,
                    turma = :turma,
                    propina_status = :propina_status,
                    propina_quantidade = :propina_quantidade,
                    transporte_status = :transporte_status,
                    transporte_quantidade = :transporte_quantidade,
                    folha_prova_1_status = :folha_prova_1,
                    folha_prova_2_status = :folha_prova_2,
                    folha_prova_3_status = :folha_prova_3,
                    boletim_notas_1_status = :boletim_notas_1,
                    boletim_notas_2_status = :boletim_notas_2,
                    cartao_status = :cartao_status
                WHERE id = :id
            ");
            
            $result = $stmt->execute([
                ':nome' => $nome,
                ':classe' => $classe,
                ':turma' => $turma,
                ':propina_status' => $propina_status,
                ':propina_quantidade' => $propina_quantidade,
                ':transporte_status' => $transporte_status,
                ':transporte_quantidade' => $transporte_quantidade,
                ':folha_prova_1' => $folha_prova_1,
                ':folha_prova_2' => $folha_prova_2,
                ':folha_prova_3' => $folha_prova_3,
                ':boletim_notas_1' => $boletim_notas_1,
                ':boletim_notas_2' => $boletim_notas_2,
                ':cartao_status' => $cartao_status,
                ':id' => $aluno_id
            ]);
            
            if ($result) {
                $pdo->commit();
                $sucesso = "✅ Aluno editado com sucesso!";
                
                // Recarregar lista
                $where_conditions = [];
                if (!empty($busca)) {
                    $where_conditions[] = "(nome_aluno LIKE '%$busca%' OR id LIKE '%$busca%')";
                }
                if (!empty($filtro_classe)) {
                    $where_conditions[] = "classe = '$filtro_classe'";
                }
                $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                $sql = "SELECT * FROM pendencias_anterior $where_sql ORDER BY classe, nome_aluno";
                $stmt = $pdo->query($sql);
                $alunos = $stmt->fetchAll();
            } else {
                $pdo->rollBack();
                $erro = 'Erro ao editar aluno!';
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = 'Erro ao editar: ' . $e->getMessage();
        }
    } else {
        $erro = '❌ Senha incorreta! Digite a senha correta para editar.';
    }
}



    
// ============================================
// BUSCAR ALUNOS DA TABELA pendencias_anterior
// ============================================
$busca = isset($_GET['busca']) ? limparString($_GET['busca']) : '';
$filtro_classe = isset($_GET['filtro_classe']) ? limparString($_GET['filtro_classe']) : '';

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'pendencias_anterior'");
    $tabela_existe = $stmt->rowCount() > 0;
    
    if ($tabela_existe) {
        $where_conditions = [];
        
        if (!empty($busca)) {
            $where_conditions[] = "(nome_aluno LIKE '%$busca%' OR id LIKE '%$busca%')";
        }
        
        if (!empty($filtro_classe)) {
            $where_conditions[] = "classe = '$filtro_classe'";
        }
        
        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "
            SELECT * FROM pendencias_anterior 
            $where_sql
            ORDER BY classe, nome_aluno
        ";
        $stmt = $pdo->query($sql);
        $alunos = $stmt->fetchAll();
        
        $stmt_classes = $pdo->query("SELECT DISTINCT classe FROM pendencias_anterior WHERE classe IS NOT NULL AND classe != '' ORDER BY classe");
        $classes_disponiveis = $stmt_classes->fetchAll(PDO::FETCH_COLUMN);
        
    } else {
        $erro = 'Tabela pendencias_anterior não encontrada!';
        $classes_disponiveis = [];
    }
} catch (Exception $e) {
    $erro = 'Erro ao buscar alunos: ' . $e->getMessage();
    $classes_disponiveis = [];
}

// ============================================
// ESTATÍSTICAS
// ============================================
$total_alunos = count($alunos);
$por_classe = [];

foreach ($alunos as $a) {
    $classe = normalizarClasse($a['classe'] ?? 'Sem Classe');
    if (!isset($por_classe[$classe])) $por_classe[$classe] = 0;
    $por_classe[$classe]++;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 Pendências de Reconfirmação</title>
    <style>
        /* ===== ESTILOS GERAIS ===== */
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f0f2f5;padding:20px}
        .container{max-width:1400px;margin:0 auto}
        
        /* ===== HEADER ===== */
        .page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
        .page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
        .page-header .subtitle{color:#94a3b8;font-size:14px;margin:2px 0 0}
        
        /* ===== BOTÕES ===== */
        .btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
        .btn-secondary{background:#f1f5f9;color:#4a5568}
        .btn-secondary:hover{background:#e2e8f0}
        .btn-success{background:#2ecc71;color:#fff}
        .btn-success:hover{background:#27ae60}
        .btn-primary{background:#c9a84c;color:#1a2332}
        .btn-primary:hover{background:#b8973d;color:#1a2332}
        .btn-info{background:#3498db;color:#fff}
        .btn-info:hover{background:#2980b9}
        .btn-sm{padding:4px 12px;font-size:11px;border-radius:6px}
        .btn-danger{background:#e74c3c;color:#fff}
        .btn-danger:hover{background:#c0392b}
        .btn-warning{background:#f39c12;color:#fff}
        .btn-warning:hover{background:#e67e22}
        .btn-limpar{background:#e74c3c;color:#fff}
        .btn-limpar:hover{background:#c0392b}
        .btn-group{display:flex;gap:8px;flex-wrap:wrap}
        
        /* ===== FILTROS ===== */
        .filtros{display:flex;gap:15px;flex-wrap:wrap;margin-bottom:20px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7;align-items:center}
        .filtros input,.filtros select{padding:8px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;transition:border-color .3s}
        .filtros input:focus,.filtros select:focus{border-color:#c9a84c}
        .filtros input{flex:1;min-width:200px}
        
        /* ===== NAVEGAÇÃO ===== */
        .nav-alunos{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:25px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
        .nav-alunos a{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;transition:all .3s;color:#4a5568;background:#f8fafc;border:1px solid #e2e8f0;display:inline-flex;align-items:center;gap:6px}
        .nav-alunos a:hover{background:#c9a84c;color:#1a2332;border-color:#c9a84c;transform:translateY(-2px)}
        .nav-alunos a.active{background:#c9a84c;color:#1a2332;border-color:#c9a84c}
        
        /* ===== ESTATÍSTICAS ===== */
        .stats-bar{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
        .stats-bar .stat-item{display:flex;align-items:center;gap:8px;font-size:14px;color:#4a5568}
        .stats-bar .stat-item .number{font-weight:700;font-size:18px;color:#1a2332}
        
        /* ===== TABELA ===== */
        .table-responsive{overflow-x:auto;background:#fff;border-radius:12px;border:1px solid #eef2f7}
        .table{width:100%;border-collapse:collapse;font-size:12px;min-width:1400px}
        .table th{background:#f8fafc;padding:8px 10px;text-align:left;font-weight:600;color:#4a5568;border-bottom:2px solid #e2e8f0;white-space:nowrap;font-size:9px;text-transform:uppercase;letter-spacing:.5px}
        .table td{padding:8px 10px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        .table tr:hover{background:#fafbfc}
        .table-actions{display:flex;gap:4px;flex-wrap:wrap}
        
        /* ===== STATUS BADGE ===== */
        .status-badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:10px;font-weight:600}
        .status-Pago{background:#d1fae5;color:#065f46}
        .status-Pendente{background:#fef3c7;color:#92400e}
        .status-Cancelado{background:#fee2e2;color:#991b1b}
        .status-Isento{background:#e5e7eb;color:#4a5568}
        
        /* ===== CLASSES ===== */
        .classe-old{color:#94a3b8;text-decoration:line-through;font-size:12px}
        .classe-new{color:#c9a84c;font-weight:700;font-size:14px}
        .sem-classe{color:#e74c3c;font-size:12px}
        
        /* ===== ALERTAS ===== */
        .alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .alert-info{background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe}
        
        /* ===== EMPTY STATE ===== */
        .empty-state{text-align:center;padding:60px 20px;color:#94a3b8}
        .empty-state .icon{font-size:64px;display:block;margin-bottom:15px}
        .empty-state h3{font-size:20px;color:#4a5568;margin:0 0 5px}
        .empty-state p{color:#94a3b8;margin:0 0 15px}
        
        /* ===== FOOTER ===== */
        .footer{text-align:center;padding:20px;color:#94a3b8;font-size:12px;border-top:1px solid #eef2f7;margin-top:20px}
        
        /* ===== MODAL ===== */
        .modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;justify-content:center;align-items:center;animation:fadeIn .3s}
        .modal-overlay.active{display:flex}
        .modal-box{background:#fff;border-radius:16px;padding:30px;max-width:800px;width:95%;box-shadow:0 20px 60px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;animation:slideIn .3s}
        .modal-box h2{color:#1a2332;margin:0 0 5px;font-size:20px}
        .modal-box .modal-subtitle{color:#94a3b8;font-size:14px;margin:0 0 20px}
        .modal-box .form-group{margin-bottom:12px}
        .modal-box .form-group label{display:block;font-weight:600;font-size:12px;color:#4a5568;margin-bottom:3px}
        .modal-box .form-group input,.modal-box .form-group select{width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;font-family:inherit;transition:border-color .3s;background:#fff}
        .modal-box .form-group input:focus,.modal-box .form-group select:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,0.1)}
        .modal-box .senha-field{margin-top:15px;border-top:1px solid #eef2f7;padding-top:15px}
        .modal-box .senha-field label{font-weight:600;color:#e74c3c}
        .modal-box h4{font-size:14px;margin:0 0 10px 0;font-weight:600}
        .modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:15px;border-top:1px solid #eef2f7}
        .modal-excluir .modal-box{max-width:450px}
        .modal-excluir .modal-box h2{color:#e74c3c}
        .modal-excluir .warning-text{background:#fef3c7;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #f39c12}
        .modal-excluir .warning-text strong{color:#92400e}
        
        @keyframes fadeIn{from{opacity:0}to{opacity:1}}
        @keyframes slideIn{from{transform:translateY(-30px);opacity:0}to{transform:translateY(0);opacity:1}}
        
        .acoes-rapidas{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;padding:15px;background:#f8fafc;border-radius:12px;border:1px solid #eef2f7;align-items:center;justify-content:center}
        
        /* ===== RESPONSIVO ===== */
        @media(max-width:768px){
            .page-header{flex-direction:column;align-items:stretch}
            .nav-alunos{flex-direction:column;align-items:stretch}
            .nav-alunos a{text-align:center;justify-content:center}
            .table{font-size:11px;min-width:1000px}
            .table th,.table td{padding:4px 6px}
            .btn-sm{font-size:9px;padding:2px 6px}
            .acoes-rapidas{flex-direction:column;align-items:stretch}
            .acoes-rapidas .btn{justify-content:center}
            .stats-bar{flex-direction:column;gap:10px}
            .filtros{flex-direction:column}
            .filtros input{width:100%}
            .modal-box{padding:20px}
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>📋 Pendências de Reconfirmação</h1>
            <p class="subtitle">Alunos da tabela <strong>pendencias_anterior</strong> aguardando reconfirmação</p>
        </div>
        <div class="btn-group">
            <a href="add.php" class="btn btn-secondary">← Voltar</a>
            <?php if ($total_alunos > 0): ?>
            <button onclick="confirmarLimpeza()" class="btn btn-limpar">🗑️ Limpar Todos</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navegação -->
    <div class="nav-alunos">
        <a href="index.php">📋 Lista de Alunos</a>
        <a href="add.php">➕ Cadastrar Aluno</a>
        <a href="importados_pendencias.php" class="active">📋 Pendências</a>
    </div>

    <?php if ($sucesso): ?>
        <div class="alert alert-success"><?= $sucesso ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="filtros">
        <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;width:100%;align-items:center;">
            <input type="text" name="busca" placeholder="🔍 Buscar por nome ou ID..." value="<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>">
            <select name="filtro_classe">
                <option value="">📚 Todas as classes</option>
                <?php foreach ($classes_disponiveis as $classe): 
                    $classe_normalizada = normalizarClasse($classe);
                ?>
                    <option value="<?= htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') ?>" <?= ($filtro_classe == $classe) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($classe_normalizada, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <?php if (!empty($busca) || !empty($filtro_classe)): ?>
                <a href="importados_pendencias.php" class="btn btn-secondary">🗑️ Limpar Filtros</a>
            <?php endif; ?>
            <span style="margin-left:auto;font-size:13px;color:#94a3b8;">
                <strong><?= $total_alunos ?></strong> resultado(s)
            </span>
        </form>
    </div>

    <!-- Estatísticas -->
    <div class="stats-bar">
        <div class="stat-item">
            <span class="number"><?= $total_alunos ?></span>
            <span class="label">Total de Alunos</span>
        </div>
        <?php foreach ($por_classe as $classe => $qtd): ?>
        <div class="stat-item">
            <span class="number"><?= $qtd ?></span>
            <span class="label"><?= htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabela Completa -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome do Aluno</th>
                    <th>Classe</th>
                    <th>Nova Classe</th>
                    <th>Turma</th>
                    <th>Propina</th>
                    <th>Qtd</th>
                    <th>Transporte</th>
                    <th>Qtd</th>
                    <th>1º Prova</th>
                    <th>2º Prova</th>
                    <th>3º Prova</th>
                    <th>Data Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($alunos) > 0): ?>
                    <?php foreach($alunos as $a): 
                        $classe_atual = normalizarClasse($a['classe'] ?? '');
                        $nova_classe = $CLASSES_PRE_DEFINIDAS[$classe_atual] ?? $classe_atual;
                        if (empty($nova_classe) || $nova_classe == $classe_atual) $nova_classe = $classe_atual;
                        
                        $status_propina = normalizarStatus($a['propina_status'] ?? 'Pendente');
                        $status_transporte = normalizarStatus($a['transporte_status'] ?? 'Pendente');
                        $status_prova1 = normalizarStatus($a['folha_prova_1_status'] ?? 'Pendente');
                        $status_prova2 = normalizarStatus($a['folha_prova_2_status'] ?? 'Pendente');
                        $status_prova3 = normalizarStatus($a['folha_prova_3_status'] ?? 'Pendente');
                        
                        $data_cadastro = !empty($a['data_cadastro']) ? date('d/m/Y H:i', strtotime($a['data_cadastro'])) : '-';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($a['id'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars(limparString($a['nome_aluno']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if (!empty($classe_atual)): ?>
                                <span class="classe-old"><?= htmlspecialchars($classe_atual, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="sem-classe">⚠️</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($nova_classe) && $nova_classe != $classe_atual): ?>
                                <span class="classe-new"><?= htmlspecialchars($nova_classe, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php elseif (!empty($nova_classe)): ?>
                                <span style="color:#94a3b8;"><?= htmlspecialchars($nova_classe, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="sem-classe">⚠️</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($a['turma'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="status-badge status-<?= formatarStatusBadge($status_propina) ?>">
                                <?= $status_propina ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($a['propina_quantidade'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="status-badge status-<?= formatarStatusBadge($status_transporte) ?>">
                                <?= $status_transporte ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($a['transporte_quantidade'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="status-badge status-<?= formatarStatusBadge($status_prova1) ?>">
                                <?= $status_prova1 ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?= formatarStatusBadge($status_prova2) ?>">
                                <?= $status_prova2 ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?= formatarStatusBadge($status_prova3) ?>">
                                <?= $status_prova3 ?>
                            </span>
                        </td>
                        <td style="font-size:11px;color:#94a3b8;"><?= htmlspecialchars($data_cadastro, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <div class="table-actions">
                                <button 
                                    onclick="abrirModalEditar(
                                        <?= $a['id'] ?>,
                                        '<?= htmlspecialchars(addslashes(limparString($a['nome_aluno'])), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Sexo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Idade'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['dia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['mes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Ano'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Morada'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Contacto4'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Contacto_do_Aluno'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Naturalidade'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Município'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Província'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['N_BI'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Classe'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['TURMA'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['SALA'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Periodo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Data_Matricula'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Data_Emissao_do_BI'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Cadastro_Transporte'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Debilidade'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Arq_identificação'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Nome_do_Pai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Morada3'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Ocupacao'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Local_de_Trabalho'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Nome_da_mae'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Contacto_Mae'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['Ocupacao_do_Aluno'] ?? ''), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['propina_status'] ?? 'Pendente'), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['propina_quantidade'] ?? 0), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['transporte_status'] ?? 'Pendente'), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['transporte_quantidade'] ?? 0), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['folha_prova_1_status'] ?? 'Pendente'), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['folha_prova_2_status'] ?? 'Pendente'), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['folha_prova_3_status'] ?? 'Pendente'), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['boletim_notas_1_status'] ?? 'Pendente'), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['boletim_notas_2_status'] ?? 'Pendente'), ENT_QUOTES, 'UTF-8') ?>',
                                        '<?= htmlspecialchars(addslashes($a['cartao_status'] ?? 'Pendente'), ENT_QUOTES, 'UTF-8') ?>'
                                    )" 
                                    class="btn btn-sm btn-warning" 
                                    title="Editar"
                                >✏️</button>
                                <button onclick="abrirModalExcluir(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes(limparString($a['nome_aluno'])), ENT_QUOTES, 'UTF-8') ?>')" class="btn btn-sm btn-danger" title="Excluir">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="14">
                            <div class="empty-state">
                                <span class="icon"><?= (!empty($busca) || !empty($filtro_classe)) ? '🔍' : '📭' ?></span>
                                <h3><?= (!empty($busca) || !empty($filtro_classe)) ? 'Nenhum resultado encontrado' : 'Nenhum aluno na tabela pendencias_anterior!' ?></h3>
                                <p>
                                    <?php if (!empty($busca) || !empty($filtro_classe)): ?>
                                        Tente ajustar os filtros de busca.
                                        <br><a href="importados_pendencias.php" class="btn btn-secondary" style="margin-top:10px;">🗑️ Limpar Filtros</a>
                                    <?php else: ?>
                                        A tabela <strong>pendencias_anterior</strong> está vazia.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Ações Rápidas -->
    <?php if (count($alunos) > 0): ?>
    <div class="acoes-rapidas">
        <span style="color:#94a3b8;font-size:13px;font-weight:500;">⚡ Ações:</span>
        <a href="ver_conta_pendente.php" class="btn btn-info">💰 Ver Conta Pendente</a>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        © <?= date('Y') ?> - Sistema de Gestão Escolar | Reconfirmação de Matrícula | Tabela: pendencias_anterior
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL DE EDIÇÃO - COM TODOS OS CAMPOS -->
<!-- ============================================================ -->
<div id="modalEditar" class="modal-overlay" onclick="if(event.target===this) fecharModalEditar()">
    <div class="modal-box">
        <h2>✏️ Editar Aluno</h2>
        <p class="modal-subtitle">Altere os dados do aluno selecionado</p>
        
        <form method="POST" id="formEditar">
            <input type="hidden" name="aluno_id_edit" id="edit_aluno_id">
            <input type="hidden" name="editar_registro" value="1">
            
            <!-- ===== DADOS PESSOAIS ===== -->
            <div style="background:#f8fafc;padding:12px 15px;border-radius:8px;margin-bottom:15px;border-left:4px solid #c9a84c;">
                <h4 style="margin:0 0 10px;font-size:14px;color:#1a2332;">👤 Dados Pessoais</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Nome do Aluno *</label>
                        <input type="text" name="edit_nome" id="edit_nome" required>
                    </div>
                    <div class="form-group">
                        <label>Sexo</label>
                        <select name="edit_sexo" id="edit_sexo">
                            <option value="">Selecione</option>
                            <option value="M">Masculino</option>
                            <option value="F">Feminino</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Idade</label>
                        <input type="number" name="edit_idade" id="edit_idade" min="0" max="99">
                    </div>
                    <div class="form-group">
                        <label>Dia</label>
                        <input type="number" name="edit_dia" id="edit_dia" min="1" max="31">
                    </div>
                    <div class="form-group">
                        <label>Mês</label>
                        <select name="edit_mes" id="edit_mes">
                            <option value="">Selecione</option>
                            <option value="Janeiro">Janeiro</option>
                            <option value="Fevereiro">Fevereiro</option>
                            <option value="Março">Março</option>
                            <option value="Abril">Abril</option>
                            <option value="Maio">Maio</option>
                            <option value="Junho">Junho</option>
                            <option value="Julho">Julho</option>
                            <option value="Agosto">Agosto</option>
                            <option value="Setembro">Setembro</option>
                            <option value="Outubro">Outubro</option>
                            <option value="Novembro">Novembro</option>
                            <option value="Dezembro">Dezembro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ano</label>
                        <input type="number" name="edit_ano" id="edit_ano" min="2000" max="2030">
                    </div>
                </div>
            </div>
            
            <!-- ===== ENDEREÇO E CONTACTO ===== -->
            <div style="background:#f8fafc;padding:12px 15px;border-radius:8px;margin-bottom:15px;border-left:4px solid #3498db;">
                <h4 style="margin:0 0 10px;font-size:14px;color:#1a2332;">📍 Endereço e Contacto</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="grid-column:1/3;">
                        <label>Morada</label>
                        <input type="text" name="edit_morada" id="edit_morada">
                    </div>
                    <div class="form-group">
                        <label>Contacto</label>
                        <input type="text" name="edit_contacto" id="edit_contacto">
                    </div>
                    <div class="form-group">
                        <label>Contacto do Aluno</label>
                        <input type="text" name="edit_contacto_aluno" id="edit_contacto_aluno">
                    </div>
                    <div class="form-group">
                        <label>Naturalidade</label>
                        <input type="text" name="edit_naturalidade" id="edit_naturalidade">
                    </div>
                    <div class="form-group">
                        <label>Município</label>
                        <input type="text" name="edit_municipio" id="edit_municipio">
                    </div>
                    <div class="form-group">
                        <label>Província</label>
                        <input type="text" name="edit_provincia" id="edit_provincia">
                    </div>
                    <div class="form-group">
                        <label>Nº BI</label>
                        <input type="text" name="edit_n_bi" id="edit_n_bi">
                    </div>
                </div>
            </div>
            
            <!-- ===== DADOS ESCOLARES ===== -->
            <div style="background:#f8fafc;padding:12px 15px;border-radius:8px;margin-bottom:15px;border-left:4px solid #2ecc71;">
                <h4 style="margin:0 0 10px;font-size:14px;color:#1a2332;">🏫 Dados Escolares</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Classe</label>
                        <input type="text" name="edit_classe" id="edit_classe">
                    </div>
                    <div class="form-group">
                        <label>Turma</label>
                        <input type="text" name="edit_turma" id="edit_turma">
                    </div>
                    <div class="form-group">
                        <label>Sala</label>
                        <input type="text" name="edit_sala" id="edit_sala">
                    </div>
                    <div class="form-group">
                        <label>Período</label>
                        <select name="edit_periodo" id="edit_periodo">
                            <option value="">Selecione</option>
                            <option value="Manhã">Manhã</option>
                            <option value="Tarde">Tarde</option>
                            <option value="Noite">Noite</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Data Matrícula</label>
                        <input type="date" name="edit_data_matricula" id="edit_data_matricula">
                    </div>
                    <div class="form-group">
                        <label>Data Emissão BI</label>
                        <input type="date" name="edit_data_emissao_bi" id="edit_data_emissao_bi">
                    </div>
                    <div class="form-group">
                        <label>Cadastro Transporte</label>
                        <select name="edit_cadastro_transporte" id="edit_cadastro_transporte">
                            <option value="">Selecione</option>
                            <option value="Sim">Sim</option>
                            <option value="Não">Não</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Debilidade</label>
                        <input type="text" name="edit_debilidade" id="edit_debilidade">
                    </div>
                    <div class="form-group">
                        <label>Arq. Identificação</label>
                        <input type="text" name="edit_arq_identificacao" id="edit_arq_identificacao">
                    </div>
                </div>
            </div>
            
            <!-- ===== FAMÍLIA ===== -->
            <div style="background:#f8fafc;padding:12px 15px;border-radius:8px;margin-bottom:15px;border-left:4px solid #9b59b6;">
                <h4 style="margin:0 0 10px;font-size:14px;color:#1a2332;">👨‍👩‍👧 Dados da Família</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Nome do Pai</label>
                        <input type="text" name="edit_nome_pai" id="edit_nome_pai">
                    </div>
                    <div class="form-group">
                        <label>Morada do Pai</label>
                        <input type="text" name="edit_morada_pai" id="edit_morada_pai">
                    </div>
                    <div class="form-group">
                        <label>Contacto do Pai</label>
                        <input type="text" name="edit_contacto_pai" id="edit_contacto_pai">
                    </div>
                    <div class="form-group">
                        <label>Ocupação</label>
                        <input type="text" name="edit_ocupacao" id="edit_ocupacao">
                    </div>
                    <div class="form-group">
                        <label>Local de Trabalho</label>
                        <input type="text" name="edit_local_trabalho" id="edit_local_trabalho">
                    </div>
                    <div class="form-group">
                        <label>Nome da Mãe</label>
                        <input type="text" name="edit_nome_mae" id="edit_nome_mae">
                    </div>
                    <div class="form-group">
                        <label>Contacto da Mãe</label>
                        <input type="text" name="edit_contacto_mae" id="edit_contacto_mae">
                    </div>
                    <div class="form-group">
                        <label>Ocupação do Aluno</label>
                        <input type="text" name="edit_ocupacao_aluno" id="edit_ocupacao_aluno">
                    </div>
                </div>
            </div>
            
            <!-- ===== STATUS E PENDÊNCIAS ===== -->
            <div style="background:#f8fafc;padding:12px 15px;border-radius:8px;margin-bottom:15px;border-left:4px solid #e74c3c;">
                <h4 style="margin:0 0 10px;font-size:14px;color:#1a2332;">📋 Status e Pendências</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Status da Propina</label>
                        <select name="edit_propina_status" id="edit_propina_status">
                            <option value="Pago">Pago ✅</option>
                            <option value="Pendente" selected>Pendente ⏳</option>
                            <option value="Cancelado">Cancelado ❌</option>
                            <option value="Isento">Isento ➖</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Qtd Propina</label>
                        <input type="number" name="edit_propina_quantidade" id="edit_propina_quantidade" min="0">
                    </div>
                    <div class="form-group">
                        <label>Status do Transporte</label>
                        <select name="edit_transporte_status" id="edit_transporte_status">
                            <option value="Pago">Pago ✅</option>
                            <option value="Pendente" selected>Pendente ⏳</option>
                            <option value="Cancelado">Cancelado ❌</option>
                            <option value="Isento">Isento ➖</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Qtd Transporte</label>
                        <input type="number" name="edit_transporte_quantidade" id="edit_transporte_quantidade" min="0">
                    </div>
                    <div class="form-group">
                        <label>1º Folha Prova</label>
                        <select name="edit_folha_prova_1" id="edit_folha_prova_1">
                            <option value="Pago">Pago ✅</option>
                            <option value="Pendente" selected>Pendente ⏳</option>
                            <option value="Cancelado">Cancelado ❌</option>
                            <option value="Isento">Isento ➖</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>2º Folha Prova</label>
                        <select name="edit_folha_prova_2" id="edit_folha_prova_2">
                            <option value="Pago">Pago ✅</option>
                            <option value="Pendente" selected>Pendente ⏳</option>
                            <option value="Cancelado">Cancelado ❌</option>
                            <option value="Isento">Isento ➖</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>3º Folha Prova</label>
                        <select name="edit_folha_prova_3" id="edit_folha_prova_3">
                            <option value="Pago">Pago ✅</option>
                            <option value="Pendente" selected>Pendente ⏳</option>
                            <option value="Cancelado">Cancelado ❌</option>
                            <option value="Isento">Isento ➖</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>1º Boletim Notas</label>
                        <select name="edit_boletim_notas_1" id="edit_boletim_notas_1">
                            <option value="Pago">Pago ✅</option>
                            <option value="Pendente" selected>Pendente ⏳</option>
                            <option value="Cancelado">Cancelado ❌</option>
                            <option value="Isento">Isento ➖</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>2º Boletim Notas</label>
                        <select name="edit_boletim_notas_2" id="edit_boletim_notas_2">
                            <option value="Pago">Pago ✅</option>
                            <option value="Pendente" selected>Pendente ⏳</option>
                            <option value="Cancelado">Cancelado ❌</option>
                            <option value="Isento">Isento ➖</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status do Cartão</label>
                        <select name="edit_cartao_status" id="edit_cartao_status">
                            <option value="Pago">Pago ✅</option>
                            <option value="Pendente" selected>Pendente ⏳</option>
                            <option value="Cancelado">Cancelado ❌</option>
                            <option value="Isento">Isento ➖</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- ===== SENHA ===== -->
            <div style="background:#fff5f5;padding:15px;border-radius:8px;margin-bottom:15px;border:2px solid #e74c3c;">
                <div class="form-group">
                    <label style="color:#e74c3c;font-weight:700;">🔑 Senha de Segurança *</label>
                    <input type="password" name="senha_edicao" placeholder="Digite a senha para editar" required style="border-color:#e74c3c;">
                    <small style="color:#94a3b8;font-size:11px;">Código: <strong style="color:#c9a84c;">Claudtec2011</strong></small>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="fecharModalEditar()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL DE CONFIRMAÇÃO DE EXCLUSÃO -->
<!-- ============================================================ -->
<div id="modalExcluir" class="modal-overlay modal-excluir" onclick="if(event.target===this) fecharModalExcluir()">
    <div class="modal-box">
        <h2>🗑️ Confirmar Exclusão</h2>
        <p class="modal-subtitle">Tem certeza que deseja excluir este aluno?</p>
        
        <div class="warning-text">
            <strong>⚠️ Atenção:</strong> Esta ação não pode ser desfeita!
            <br>
            <span id="excluir_nome_aluno" style="font-size:16px;font-weight:700;color:#1a2332;"></span>
        </div>
        
        <form method="POST" id="formExcluir">
            <input type="hidden" name="aluno_id" id="excluir_aluno_id">
            <input type="hidden" name="excluir_registro" value="1">
            
            <div class="senha-field">
                <label>🔑 Senha de Segurança *</label>
                <input type="password" name="senha_exclusao" placeholder="Digite a senha para excluir" required>
                <small style="color:#94a3b8;font-size:11px;">Código: <strong>Claudtec2011</strong></small>
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="fecharModalExcluir()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-danger">🗑️ Confirmar Exclusão</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL DE CONFIRMAÇÃO DE LIMPEZA -->
<!-- ============================================================ -->
<div id="modalLimpar" class="modal-overlay" onclick="if(event.target===this) fecharModalLimpar()">
    <div class="modal-box">
        <h2>⚠️ Atenção!</h2>
        <p>Você está prestes a <strong>remover TODOS os alunos</strong> da tabela de pendências.</p>
        <p class="modal-subtitle">
            Esta ação é <strong>irreversível</strong>. Todos os dados serão perdidos.
            <br><br>
            <strong><?= $total_alunos ?></strong> alunos serão removidos da tabela <strong>pendencias_anterior</strong>.
        </p>
        <div class="modal-actions">
            <button onclick="fecharModalLimpar()" class="btn btn-secondary">Cancelar</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="limpar_todos" value="1">
                <button type="submit" class="btn btn-limpar" onclick="return confirm('Confirma a exclusão de TODOS os dados?')">🗑️ Confirmar Exclusão</button>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================================
// FUNÇÃO ABRIR MODAL DE EDIÇÃO - COM TODOS OS CAMPOS
// ============================================================

function abrirModalEditar(
    id, nome, sexo, idade, dia, mes, ano,
    morada, contacto, contacto_aluno, naturalidade, 
    municipio, provincia, n_bi,
    classe, turma, sala, periodo, data_matricula, 
    data_emissao_bi, cadastro_transporte, debilidade, arq_identificacao,
    nome_pai, morada_pai, contacto_pai, ocupacao, local_trabalho,
    nome_mae, contacto_mae, ocupacao_aluno,
    propina_status, propina_quantidade, transporte_status, transporte_quantidade,
    folha_prova_1, folha_prova_2, folha_prova_3,
    boletim_notas_1, boletim_notas_2, cartao_status
) {
    // Dados Pessoais
    document.getElementById('edit_aluno_id').value = id;
    document.getElementById('edit_nome').value = nome || '';
    document.getElementById('edit_sexo').value = sexo || '';
    document.getElementById('edit_idade').value = idade || '';
    document.getElementById('edit_dia').value = dia || '';
    document.getElementById('edit_mes').value = mes || '';
    document.getElementById('edit_ano').value = ano || '';
    
    // Endereço e Contacto
    document.getElementById('edit_morada').value = morada || '';
    document.getElementById('edit_contacto').value = contacto || '';
    document.getElementById('edit_contacto_aluno').value = contacto_aluno || '';
    document.getElementById('edit_naturalidade').value = naturalidade || '';
    document.getElementById('edit_municipio').value = municipio || '';
    document.getElementById('edit_provincia').value = provincia || '';
    document.getElementById('edit_n_bi').value = n_bi || '';
    
    // Dados Escolares
    document.getElementById('edit_classe').value = classe || '';
    document.getElementById('edit_turma').value = turma || '';
    document.getElementById('edit_sala').value = sala || '';
    document.getElementById('edit_periodo').value = periodo || '';
    document.getElementById('edit_data_matricula').value = data_matricula || '';
    document.getElementById('edit_data_emissao_bi').value = data_emissao_bi || '';
    document.getElementById('edit_cadastro_transporte').value = cadastro_transporte || '';
    document.getElementById('edit_debilidade').value = debilidade || '';
    document.getElementById('edit_arq_identificacao').value = arq_identificacao || '';
    
    // Família
    document.getElementById('edit_nome_pai').value = nome_pai || '';
    document.getElementById('edit_morada_pai').value = morada_pai || '';
    document.getElementById('edit_contacto_pai').value = contacto_pai || '';
    document.getElementById('edit_ocupacao').value = ocupacao || '';
    document.getElementById('edit_local_trabalho').value = local_trabalho || '';
    document.getElementById('edit_nome_mae').value = nome_mae || '';
    document.getElementById('edit_contacto_mae').value = contacto_mae || '';
    document.getElementById('edit_ocupacao_aluno').value = ocupacao_aluno || '';
    
    // Status
    document.getElementById('edit_propina_status').value = propina_status || 'Pendente';
    document.getElementById('edit_propina_quantidade').value = propina_quantidade || 0;
    document.getElementById('edit_transporte_status').value = transporte_status || 'Pendente';
    document.getElementById('edit_transporte_quantidade').value = transporte_quantidade || 0;
    document.getElementById('edit_folha_prova_1').value = folha_prova_1 || 'Pendente';
    document.getElementById('edit_folha_prova_2').value = folha_prova_2 || 'Pendente';
    document.getElementById('edit_folha_prova_3').value = folha_prova_3 || 'Pendente';
    document.getElementById('edit_boletim_notas_1').value = boletim_notas_1 || 'Pendente';
    document.getElementById('edit_boletim_notas_2').value = boletim_notas_2 || 'Pendente';
    document.getElementById('edit_cartao_status').value = cartao_status || 'Pendente';
    
    // Limpar senha
    document.querySelector('#formEditar input[name="senha_edicao"]').value = '';
    
    // Abrir modal
    document.getElementById('modalEditar').classList.add('active');
}

function fecharModalEditar() {
    document.getElementById('modalEditar').classList.remove('active');
}

// ============================================================
// FUNÇÕES DO MODAL DE EXCLUSÃO
// ============================================================
function abrirModalExcluir(id, nome) {
    document.getElementById('excluir_aluno_id').value = id;
    document.getElementById('excluir_nome_aluno').textContent = nome || 'Aluno não identificado';
    document.getElementById('modalExcluir').classList.add('active');
    document.querySelector('#formExcluir input[name="senha_exclusao"]').value = '';
}

function fecharModalExcluir() {
    document.getElementById('modalExcluir').classList.remove('active');
}

// ============================================================
// FUNÇÕES DO MODAL DE LIMPEZA
// ============================================================
function confirmarLimpeza() {
    document.getElementById('modalLimpar').style.display = 'flex';
}

function fecharModalLimpar() {
    document.getElementById('modalLimpar').style.display = 'none';
}

// ============================================================
// FECHAR MODAIS COM ESC
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalEditar();
        fecharModalExcluir();
        fecharModalLimpar();
    }
});
</script>

<?php include '../../includes/footer_escola.php'; ?>