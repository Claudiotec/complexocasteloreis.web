<?php
// ============================================
// modules/escola/financeiro/pagamentos/importados_alunos.php
// Visualizar alunos importados do ano anterior
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
// FUNÇÕES AUXILIARES PARA UTF-8
// ============================================

function limparString($texto) {
    if (empty($texto)) return '';
    
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'auto');
    }
    
    $texto = preg_replace('/[[:cntrl:]]/', '', $texto);
    
    $mapa = [
        '�' => '', '?' => '', '�' => 'ã', '�' => 'á', '�' => 'à',
        '�' => 'â', '�' => 'ä', '�' => 'é', '�' => 'è', '�' => 'ê',
        '�' => 'ë', '�' => 'í', '�' => 'ì', '�' => 'î', '�' => 'ï',
        '�' => 'ó', '�' => 'ò', '�' => 'õ', '�' => 'ô', '�' => 'ö',
        '�' => 'ú', '�' => 'ù', '�' => 'û', '�' => 'ü', '�' => 'ç'
    ];
    $texto = str_replace(array_keys($mapa), array_values($mapa), $texto);
    
    $texto = trim($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return $texto;
}

function normalizarClasse($classe) {
    if (empty($classe)) return '';
    $classe = limparString($classe);
    $classe = trim($classe);
    
    $classe = str_replace([' ', '�', '?', '�', '�', '�', '�', '�', '�', '�'], '', $classe);
    
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

function formatarData($data) {
    if (empty($data)) return '-';
    try {
        $date = DateTime::createFromFormat('Y-m-d', $data);
        if ($date) return $date->format('d/m/Y');
        return $data;
    } catch (Exception $e) {
        return $data;
    }
}

include '../../includes/header_escola.php';

// ============================================
// PROCESSAR LIMPEZA DO BANCO
// ============================================

$limpeza_erro = '';
$limpeza_sucesso = '';
$mostrar_modal_limpeza = false;

if (isset($_POST['action']) && $_POST['action'] == 'limpar_banco') {
    $codigo = isset($_POST['codigo_seguranca']) ? trim($_POST['codigo_seguranca']) : '';
    
    // Verificar código de segurança
    if ($codigo === 'Claudtec2011') {
        try {
            // Iniciar transação
            $pdo->beginTransaction();
            
            // Verificar se a tabela existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'alunos_ano_anterior'");
            if ($stmt->rowCount() > 0) {
                // Contar registros antes de limpar
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM alunos_ano_anterior");
                $total_antes = $stmt->fetch()['total'];
                
                // Limpar a tabela (TRUNCATE é mais rápido que DELETE)
                $pdo->exec("TRUNCATE TABLE alunos_ano_anterior");
                
                // Commit da transação
                $pdo->commit();
                
                $limpeza_sucesso = "✅ Banco de dados limpo com sucesso! {$total_antes} registros removidos.";
                
                // Registrar no log
                $log_stmt = $pdo->prepare("
                    INSERT INTO logs_atividades (usuario_id, usuario_nome, acao, detalhes, ip_address)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $log_stmt->execute([
                    $_SESSION['usuario_id'] ?? 0,
                    $_SESSION['user_name'] ?? 'Sistema',
                    'LIMPAR_BANCO_ALUNOS_ANO_ANTERIOR',
                    "Tabela alunos_ano_anterior limpa. {$total_antes} registros removidos.",
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ]);
                
                // Recarregar a página para atualizar a lista
                header('Location: importados_alunos.php?limpeza=sucesso');
                exit;
                
            } else {
                $pdo->rollBack();
                $limpeza_erro = "❌ Tabela alunos_ano_anterior não encontrada!";
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $limpeza_erro = "❌ Erro ao limpar banco: " . $e->getMessage();
        }
    } else {
        $limpeza_erro = "❌ Código de segurança incorreto! Tente novamente.";
    }
}

// Verificar se veio de limpeza bem-sucedida
if (isset($_GET['limpeza']) && $_GET['limpeza'] == 'sucesso') {
    $limpeza_sucesso = "✅ Banco de dados limpo com sucesso!";
}

// ============================================
// BUSCAR DADOS
// ============================================

$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$classe = isset($_GET['classe']) ? trim($_GET['classe']) : '';
$situacao = isset($_GET['situacao']) ? trim($_GET['situacao']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if (!empty($busca)) {
    $where[] = "(nome LIKE ? OR N_BI LIKE ? OR TURMA LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if (!empty($classe)) {
    $where[] = "Classe = ?";
    $params[] = $classe;
}

if (!empty($situacao)) {
    $where[] = "Situacao_Cadastro = ?";
    $params[] = $situacao;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'alunos_ano_anterior'");
    $tabela_existe = $stmt->rowCount() > 0;
    
    if ($tabela_existe) {
        $count_sql = "SELECT COUNT(*) as total FROM alunos_ano_anterior $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'] ?? 0;
        $total_pages = ceil($total / $limit);
        
        $sql = "SELECT * FROM alunos_ano_anterior $where_clause ORDER BY Classe, nome LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $alunos = $stmt->fetchAll();
        
        $classes = $pdo->query("SELECT DISTINCT Classe FROM alunos_ano_anterior WHERE Classe IS NOT NULL AND Classe != '' ORDER BY Classe")->fetchAll();
        $situacoes = $pdo->query("SELECT DISTINCT Situacao_Cadastro FROM alunos_ano_anterior WHERE Situacao_Cadastro IS NOT NULL AND Situacao_Cadastro != '' ORDER BY Situacao_Cadastro")->fetchAll();
    } else {
        $erro = 'Tabela alunos_ano_anterior não encontrada!';
        $alunos = [];
        $total = 0;
        $total_pages = 0;
        $classes = [];
        $situacoes = [];
    }
    
} catch (Exception $e) {
    $erro = 'Erro ao carregar dados: ' . $e->getMessage();
    $alunos = [];
    $total = 0;
    $total_pages = 0;
}

// ============================================
// PROCESSAR EXCLUSÃO INDIVIDUAL
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM alunos_ano_anterior WHERE id = ?");
        $stmt->execute([$id]);
        $sucesso = "🗑️ Registro excluído com sucesso!";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $alunos = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $erro = 'Erro ao excluir: ' . $e->getMessage();
    }
}

// ============================================
// PROCESSAR EXPORTAÇÃO CSV
// ============================================
if (isset($_GET['export']) && $_GET['export'] == 1) {
    try {
        $sql = "SELECT * FROM alunos_ano_anterior $where_clause ORDER BY Classe, nome";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="alunos_importados_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        $headers = ['ID', 'Nome', 'Sexo', 'Dia', 'Mês', 'Ano', 'Morada', 'Cadastro Transporte', 
                   'Contacto Aluno', 'Debilidade', 'Idade', 'Naturalidade', 'Município', 'Província', 
                   'Nº BI', 'Classe', 'Nome do Pai', 'Morada3', 'Contacto4', 'Ocupação', 
                   'Local de Trabalho', 'Nome da Mãe', 'Contacto Mãe', 'Data Matrícula', 
                   'Ocupação Aluno', 'Período', 'Data Emissão BI', 'Arq Identificação', 
                   'Situação Cadastro', 'TURMA', 'SALA', 'Curso'];
        fputcsv($output, $headers, ';');
        
        foreach ($dados as $row) {
            fputcsv($output, [
                $row['id'], $row['nome'], $row['Sexo'], $row['dia'], $row['mes'], $row['Ano'],
                $row['Morada'], $row['Cadastro_Transporte'], $row['Contacto_do_Aluno'],
                $row['Debilidade'], $row['Idade'], $row['Naturalidade'], $row['Município'],
                $row['Província'], $row['N_BI'], $row['Classe'], $row['Nome_do_Pai'],
                $row['Morada3'], $row['Contacto4'], $row['Ocupacao'], $row['Local_de_Trabalho'],
                $row['Nome_da_mae'], $row['Contacto_Mae'], $row['Data_Matricula'],
                $row['Ocupacao_do_Aluno'], $row['Periodo'], $row['Data_Emissao_do_BI'],
                $row['Arq_identificação'], $row['Situacao_Cadastro'], $row['TURMA'],
                $row['SALA'], $row['Curso']
            ], ';');
        }
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        $erro = 'Erro ao exportar: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 Alunos Importados</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f0f2f5;padding:20px}
        .container{max-width:1400px;margin:0 auto}
        .page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
        .page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
        .page-header .subtitle{color:#94a3b8;font-size:14px;margin:2px 0 0}
        .btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
        .btn-primary{background:#c9a84c;color:#1a2332}
        .btn-primary:hover{background:#b8973a;transform:translateY(-2px)}
        .btn-secondary{background:#f1f5f9;color:#4a5568}
        .btn-secondary:hover{background:#e2e8f0}
        .btn-success{background:#2ecc71;color:#fff}
        .btn-success:hover{background:#27ae60}
        .btn-danger{background:#e74c3c;color:#fff}
        .btn-danger:hover{background:#c0392b}
        .btn-info{background:#3498db;color:#fff}
        .btn-info:hover{background:#2980b9}
        .btn-warning{background:#f39c12;color:#fff}
        .btn-warning:hover{background:#e67e22}
        .filtros{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:15px;margin-bottom:20px}
        .filtros .filtros-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
        .filtros .filtros-row .form-group{flex:1;min-width:150px}
        .filtros .filtros-row .form-group label{display:block;font-size:12px;font-weight:600;color:#4a5568;margin-bottom:3px}
        .filtros .filtros-row .form-group input,.filtros .filtros-row .form-group select{width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-family:inherit}
        .filtros .filtros-row .form-group input:focus,.filtros .filtros-row .form-group select:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,0.1)}
        .tabela-container{background:#fff;border-radius:12px;border:1px solid #eef2f7;overflow:auto}
        .tabela-container table{width:100%;border-collapse:collapse;font-size:13px}
        .tabela-container table th{background:#f8fafc;padding:12px 14px;text-align:left;font-weight:600;color:#1a2332;border-bottom:2px solid #e2e8f0;white-space:nowrap;position:sticky;top:0;z-index:10}
        .tabela-container table td{padding:10px 14px;border-bottom:1px solid #eef2f7;vertical-align:middle}
        .tabela-container table tr:hover{background:#f8fafc}
        .badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:600}
        .badge-Matrícula{background:#dbeafe;color:#1e40af}
        .badge-Pendente{background:#fef3c7;color:#92400e}
        .badge-Confirmação{background:#d1fae5;color:#065f46}
        .badge-ativo{background:#d1fae5;color:#065f46}
        .badge-inativo{background:#fee2e2;color:#991b1b}
        .info-total{margin:15px 0;padding:12px 16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0}
        .info-total span{font-weight:600;color:#1a2332}
        .pagination{display:flex;justify-content:center;gap:5px;padding:15px 0;flex-wrap:wrap}
        .pagination .page-link{padding:8px 14px;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#4a5568;font-size:13px;transition:all .2s}
        .pagination .page-link:hover{background:#f1f5f9}
        .pagination .page-link.active{background:#c9a84c;color:#1a2332;border-color:#c9a84c}
        .pagination .page-link.disabled{opacity:0.5;pointer-events:none}
        .alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .alert-info{background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe}
        .alert-warning{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
        .no-data{text-align:center;padding:40px;color:#94a3b8}
        .no-data .icone{font-size:48px;margin-bottom:10px}
        .text-center{text-align:center}
        
        /* Modal de Limpeza */
        .modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9999;justify-content:center;align-items:center;animation:fadeIn .3s}
        .modal-overlay.active{display:flex}
        .modal-box{background:#fff;border-radius:16px;max-width:500px;width:90%;padding:30px;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:slideIn .3s}
        .modal-box .modal-icon{font-size:48px;text-align:center;margin-bottom:15px}
        .modal-box h3{font-size:20px;font-weight:700;color:#1a2332;text-align:center;margin-bottom:10px}
        .modal-box p{color:#4a5568;text-align:center;font-size:14px;line-height:1.6;margin-bottom:20px}
        .modal-box .codigo-input{width:100%;padding:12px;border:2px solid #e2e8f0;border-radius:8px;font-size:18px;font-weight:600;text-align:center;letter-spacing:4px;font-family:monospace;margin-bottom:20px}
        .modal-box .codigo-input:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,0.1)}
        .modal-box .modal-actions{display:flex;gap:10px;justify-content:center}
        .modal-box .modal-actions .btn{min-width:120px;justify-content:center}
        
        @keyframes fadeIn{from{opacity:0}to{opacity:1}}
        @keyframes slideIn{from{transform:translateY(-30px);opacity:0}to{transform:translateY(0);opacity:1}}
        
        @media(max-width:768px){
            .page-header{flex-direction:column;align-items:stretch}
            .filtros .filtros-row{flex-direction:column}
            .filtros .filtros-row .form-group{width:100%}
            .tabela-container{font-size:12px}
            .tabela-container table th,.tabela-container table td{padding:6px 8px}
            .modal-box{padding:20px}
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>📚 Alunos Importados (Ano Anterior)</h1>
            <p class="subtitle">Total: <strong><?= number_format($total ?? 0) ?></strong> registros</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-danger" onclick="abrirModalLimpeza()">
                🗑️ Limpar Banco
            </button>
            <a href="add.php" class="btn btn-secondary">← Voltar</a>
            <button class="btn btn-success" onclick="window.print()">🖨️ Imprimir</button>
            <a href="?export=1<?= !empty($busca) ? '&busca=' . urlencode($busca) : '' ?><?= !empty($classe) ? '&classe=' . urlencode($classe) : '' ?><?= !empty($situacao) ? '&situacao=' . urlencode($situacao) : '' ?>" class="btn btn-info">📥 Exportar CSV</a>
        </div>
    </div>

    <?php if (!empty($limpeza_sucesso)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($limpeza_sucesso, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($limpeza_erro)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($limpeza_erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($sucesso)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($erro)): ?>
    <div class="alert alert-error">❌ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!isset($tabela_existe) || !$tabela_existe): ?>
    <div class="alert alert-info">
        📌 <strong>Nenhum dado importado ainda.</strong> 
        <a href="add.php" style="font-weight:600;color:#c9a84c;">Clique aqui</a> para importar alunos da planilha.
    </div>
    <?php endif; ?>

    <?php if (isset($tabela_existe) && $tabela_existe): ?>
    <!-- Filtros -->
    <div class="filtros">
        <form method="GET" action="">
            <div class="filtros-row">
                <div class="form-group">
                    <label>🔍 Buscar</label>
                    <input type="text" name="busca" placeholder="Nome, BI ou Turma..." value="<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label>📚 Classe</label>
                    <select name="classe">
                        <option value="">Todas</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c['Classe'], ENT_QUOTES, 'UTF-8') ?>" <?= $classe == $c['Classe'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['Classe'] ?: 'Sem Classe', ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>📋 Situação</label>
                    <select name="situacao">
                        <option value="">Todas</option>
                        <?php foreach($situacoes as $s): ?>
                        <option value="<?= htmlspecialchars($s['Situacao_Cadastro'], ENT_QUOTES, 'UTF-8') ?>" <?= $situacao == $s['Situacao_Cadastro'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['Situacao_Cadastro'] ?: 'Sem Situação', ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:0 0 100px">
                    <label>📄 Limite</label>
                    <select name="limit">
                        <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    <a href="importados_alunos.php" class="btn btn-secondary">✕ Limpar</a>
                </div>
            </div>
            <input type="hidden" name="page" value="1">
        </form>
    </div>

    <!-- Total -->
    <div class="info-total">
        <span>📊 Total de registros: <?= number_format($total ?? 0) ?></span>
        <?php if (!empty($busca) || !empty($classe) || !empty($situacao)): ?>
        <span style="margin-left:15px;color:#94a3b8;">(Filtros aplicados)</span>
        <?php endif; ?>
    </div>

    <!-- Tabela -->
    <div class="tabela-container">
        <?php if (!empty($alunos)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Sexo</th>
                    <th>Idade</th>
                    <th>Classe</th>
                    <th>Turma</th>
                    <th>Nº BI</th>
                    <th>Situação</th>
                    <th>Data Matrícula</th>
                    <th>Período</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($alunos as $aluno): 
                    $status = $aluno['Situacao_Cadastro'] ?? 'Desconhecido';
                    $classe_normalizada = normalizarClasse($aluno['Classe'] ?? '');
                ?>
                <tr>
                    <td><?= htmlspecialchars($aluno['id'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><strong><?= htmlspecialchars(limparString($aluno['nome']), ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars($aluno['Sexo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($aluno['Idade'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($classe_normalizada ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($aluno['TURMA'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($aluno['N_BI'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge badge-<?= str_replace(' ', '_', $status) ?>">
                            <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td><?= !empty($aluno['Data_Matricula']) ? date('d/m/Y', strtotime($aluno['Data_Matricula'])) : '-' ?></td>
                    <td><?= htmlspecialchars($aluno['Periodo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-center" style="white-space:nowrap;">
                        <a href="ver_aluno_importado.php?id=<?= $aluno['id'] ?>" class="btn btn-info" style="padding:4px 10px;font-size:11px;text-decoration:none;">👁️</a>
                        <a href="?delete=<?= $aluno['id'] ?>" onclick="return confirm('Excluir este registro?')" class="btn btn-danger" style="padding:4px 10px;font-size:11px;text-decoration:none;">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">
            <div class="icone">📭</div>
            <p>Nenhum aluno importado encontrado</p>
            <p style="font-size:12px;color:#94a3b8;">Importe alunos através do botão "Importar Dados" na página de pagamentos</p>
            <a href="add.php" class="btn btn-primary" style="margin-top:15px;">📤 Importar Alunos</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Paginação -->
    <?php if (isset($total_pages) && $total_pages > 1): ?>
    <div class="pagination">
        <a href="?page=<?= max(1, $page-1) ?>&busca=<?= urlencode($busca) ?>&classe=<?= urlencode($classe) ?>&situacao=<?= urlencode($situacao) ?>&limit=<?= $limit ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">«</a>
        
        <?php for($p = 1; $p <= $total_pages; $p++): ?>
            <?php if($p == $page): ?>
                <span class="page-link active"><?= $p ?></span>
            <?php elseif($p <= 3 || $p > $total_pages - 3 || abs($p - $page) <= 2): ?>
                <a href="?page=<?= $p ?>&busca=<?= urlencode($busca) ?>&classe=<?= urlencode($classe) ?>&situacao=<?= urlencode($situacao) ?>&limit=<?= $limit ?>" class="page-link"><?= $p ?></a>
            <?php elseif($p == 4 && $page > 5): ?>
                <span class="page-link">…</span>
            <?php elseif($p == $total_pages - 3 && $page < $total_pages - 4): ?>
                <span class="page-link">…</span>
            <?php endif; ?>
        <?php endfor; ?>
        
        <a href="?page=<?= min($total_pages, $page+1) ?>&busca=<?= urlencode($busca) ?>&classe=<?= urlencode($classe) ?>&situacao=<?= urlencode($situacao) ?>&limit=<?= $limit ?>" class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>">»</a>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- Footer -->
    <div style="text-align:center;padding:20px;color:#94a3b8;font-size:12px;border-top:1px solid #eef2f7;margin-top:20px;">
        © <?= date('Y') ?> - Sistema de Gestão Escolar | Alunos Importados
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL DE CONFIRMAÇÃO PARA LIMPAR BANCO -->
<!-- ============================================ -->
<div class="modal-overlay" id="modalLimpeza">
    <div class="modal-box">
        <div class="modal-icon">⚠️</div>
        <h3>Limpar Banco de Dados</h3>
        <p>
            <strong>Atenção!</strong> Esta ação irá <strong>remover permanentemente</strong> 
            todos os registros da tabela <strong>alunos_ano_anterior</strong>.
            <br><br>
            Para confirmar, digite o código de segurança abaixo:
        </p>
        <form method="POST" action="" onsubmit="return validarCodigo()">
            <input type="hidden" name="action" value="limpar_banco">
            <input type="password" 
                   class="codigo-input" 
                   id="codigoSeguranca" 
                   name="codigo_seguranca" 
                   placeholder="Digite o código..." 
                   maxlength="20"
                   autocomplete="off"
                   required>
            <div style="text-align:center;margin-bottom:15px;">
                <small style="color:#94a3b8;">Código: <strong>Claudtec2011</strong></small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="fecharModalLimpeza()">Cancelar</button>
                <button type="submit" class="btn btn-danger" id="btnLimparConfirmar">
                    🗑️ Confirmar Limpeza
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ============================================
    // FUNÇÕES DO MODAL
    // ============================================
    
    function abrirModalLimpeza() {
        document.getElementById('modalLimpeza').classList.add('active');
        document.getElementById('codigoSeguranca').value = '';
        document.getElementById('codigoSeguranca').focus();
    }
    
    function fecharModalLimpeza() {
        document.getElementById('modalLimpeza').classList.remove('active');
    }
    
    function validarCodigo() {
        const codigo = document.getElementById('codigoSeguranca').value.trim();
        if (codigo === '') {
            alert('Por favor, digite o código de segurança.');
            return false;
        }
        if (codigo !== 'Claudtec2011') {
            alert('❌ Código incorreto! Tente novamente.');
            document.getElementById('codigoSeguranca').value = '';
            document.getElementById('codigoSeguranca').focus();
            return false;
        }
        
        // Confirmação final
        return confirm('⚠️ TEM CERTEZA QUE DESEJA LIMPAR TODOS OS REGISTROS?\n\nEsta ação NÃO pode ser desfeita!');
    }
    
    // Fechar modal ao clicar fora
    document.getElementById('modalLimpeza').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalLimpeza();
        }
    });
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharModalLimpeza();
        }
    });
    
    // Focar no campo de código ao abrir o modal
    document.addEventListener('DOMContentLoaded', function() {
        const observer = new MutationObserver(function() {
            const modal = document.getElementById('modalLimpeza');
            if (modal.classList.contains('active')) {
                setTimeout(function() {
                    document.getElementById('codigoSeguranca').focus();
                }, 300);
            }
        });
        observer.observe(document.getElementById('modalLimpeza'), { attributes: true, attributeFilter: ['class'] });
    });
</script>

<?php
// Incluir o footer
if (file_exists('../../includes/footer_escola.php')) {
    include '../../includes/footer_escola.php';
}
?>
</body>
</html>