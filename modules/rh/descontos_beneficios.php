<?php
// descontos_beneficios.php - Gerenciamento de Descontos e Benefícios

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';





// =============================================
// BUSCAR DADOS DA EMPRESA
// =============================================
$empresa = [];
try {
    $stmtEmpresa = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmtEmpresa->fetch();
} catch (Exception $e) {
    $empresa = [];
}

$nome_fantasia = $empresa['nome_fantasia'] ?? 'SoftGest Web';
$logo = $empresa['logo'] ?? '';

// =============================================
// FILTROS
// =============================================
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$tipo = $_GET['tipo'] ?? 'todos';
$status_filter = $_GET['status'] ?? 'todos';

// =============================================
// PROCESSAR FORMULÁRIO - CADASTRAR EMPRÉSTIMO
// =============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'salvar_emprestimo') {
        $funcionario_id = $_POST['funcionario_id'] ?? 0;
        $valor = floatval($_POST['valor'] ?? 0);
        $data_emprestimo = $_POST['data_emprestimo'] ?? date('Y-m-d');
        $data_pagamento = $_POST['data_pagamento'] ?? '';
        $tipo_pagamento = $_POST['tipo_pagamento'] ?? 'parcelado';
        $parcelas = intval($_POST['parcelas'] ?? 1);
        $descricao = $_POST['descricao'] ?? '';
        $status = $_POST['status'] ?? 'pendente';
        
        if ($funcionario_id > 0 && $valor > 0) {
            try {
                // Inserir empréstimo
                $stmt = $pdo->prepare("
                    INSERT INTO emprestimos_funcionarios 
                    (funcionario_id, valor, data_emprestimo, data_pagamento, tipo_pagamento, parcelas, descricao, status, mes, ano) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $funcionario_id, $valor, $data_emprestimo, $data_pagamento, 
                    $tipo_pagamento, $parcelas, $descricao, $status, $mes, $ano
                ]);
                
                $emprestimo_id = $pdo->lastInsertId();
                
                // Se for parcelado, criar parcelas
                if ($tipo_pagamento == 'parcelado' && $parcelas > 1) {
                    $valor_parcela = $valor / $parcelas;
                    $intervalo = 30; // dias entre parcelas
                    
                    for ($i = 1; $i <= $parcelas; $i++) {
                        $data_parcela = date('Y-m-d', strtotime($data_emprestimo . ' + ' . ($i * $intervalo) . ' days'));
                        $stmt = $pdo->prepare("
                            INSERT INTO emprestimos_parcelas 
                            (emprestimo_id, numero_parcela, valor, data_vencimento, status) 
                            VALUES (?, ?, ?, ?, 'pendente')
                        ");
                        $stmt->execute([$emprestimo_id, $i, $valor_parcela, $data_parcela]);
                    }
                }
                
                header("Location: descontos_beneficios.php?success=1&msg=Empréstimo cadastrado com sucesso!&mes=$mes&ano=$ano");
                exit;
            } catch (Exception $e) {
                header("Location: descontos_beneficios.php?error=1&msg=" . urlencode($e->getMessage()) . "&mes=$mes&ano=$ano");
                exit;
            }
        }
    }
    
    if ($action == 'salvar_desconto') {
        $funcionario_id = $_POST['funcionario_id'] ?? 0;
        $tipo_desconto = $_POST['tipo_desconto'] ?? '';
        $valor = floatval($_POST['valor'] ?? 0);
        $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
        $data_fim = $_POST['data_fim'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        
        if ($funcionario_id > 0 && $valor > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO descontos_funcionarios 
                    (funcionario_id, tipo_desconto, valor, data_inicio, data_fim, descricao, mes, ano) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $funcionario_id, $tipo_desconto, $valor, $data_inicio, $data_fim, $descricao, $mes, $ano
                ]);
                
                header("Location: descontos_beneficios.php?success=1&msg=Desconto cadastrado com sucesso!&mes=$mes&ano=$ano");
                exit;
            } catch (Exception $e) {
                header("Location: descontos_beneficios.php?error=1&msg=" . urlencode($e->getMessage()) . "&mes=$mes&ano=$ano");
                exit;
            }
        }
    }
    
    if ($action == 'salvar_beneficio') {
        $funcionario_id = $_POST['funcionario_id'] ?? 0;
        $tipo_beneficio = $_POST['tipo_beneficio'] ?? '';
        $valor = floatval($_POST['valor'] ?? 0);
        $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
        $descricao = $_POST['descricao'] ?? '';
        
        if ($funcionario_id > 0 && $valor > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO beneficios_funcionarios 
                    (funcionario_id, tipo_beneficio, valor, data_inicio, descricao, mes, ano) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $funcionario_id, $tipo_beneficio, $valor, $data_inicio, $descricao, $mes, $ano
                ]);
                
                header("Location: descontos_beneficios.php?success=1&msg=Benefício cadastrado com sucesso!&mes=$mes&ano=$ano");
                exit;
            } catch (Exception $e) {
                header("Location: descontos_beneficios.php?error=1&msg=" . urlencode($e->getMessage()) . "&mes=$mes&ano=$ano");
                exit;
            }
        }
    }
}

// =============================================
// ATUALIZAR STATUS DO EMPRÉSTIMO
// =============================================
if (isset($_GET['atualizar_status']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $novo_status = $_GET['status'] ?? 'pago';
    
    try {
        $stmt = $pdo->prepare("UPDATE emprestimos_funcionarios SET status = ? WHERE id = ?");
        $stmt->execute([$novo_status, $id]);
        
        // Atualizar também todas as parcelas
        if ($novo_status == 'pago') {
            $stmt = $pdo->prepare("UPDATE emprestimos_parcelas SET status = 'pago' WHERE emprestimo_id = ?");
            $stmt->execute([$id]);
        }
        
        header("Location: descontos_beneficios.php?success=1&msg=Status atualizado com sucesso!&mes=$mes&ano=$ano");
        exit;
    } catch (Exception $e) {
        header("Location: descontos_beneficios.php?error=1&msg=" . urlencode($e->getMessage()) . "&mes=$mes&ano=$ano");
        exit;
    }
}

// =============================================
// BUSCAR FUNCIONÁRIOS
// =============================================
$stmtFuncionarios = $pdo->query("SELECT id, nome, cargo, salario FROM funcionarios WHERE status = 'ativo' ORDER BY nome");
$funcionarios = $stmtFuncionarios->fetchAll();

// =============================================
// BUSCAR EMPRÉSTIMOS
// =============================================
$sql_emprestimos = "
    SELECT e.*, f.nome as funcionario_nome, f.cargo 
    FROM emprestimos_funcionarios e 
    JOIN funcionarios f ON e.funcionario_id = f.id 
    WHERE 1=1
";

if ($mes) $sql_emprestimos .= " AND e.mes = '$mes'";
if ($ano) $sql_emprestimos .= " AND e.ano = '$ano'";
if ($status_filter != 'todos') $sql_emprestimos .= " AND e.status = '$status_filter'";

$sql_emprestimos .= " ORDER BY e.id DESC";

$stmtEmprestimos = $pdo->query($sql_emprestimos);
$emprestimos = $stmtEmprestimos->fetchAll();

// =============================================
// BUSCAR DESCONTOS
// =============================================
$sql_descontos = "
    SELECT d.*, f.nome as funcionario_nome, f.cargo 
    FROM descontos_funcionarios d 
    JOIN funcionarios f ON d.funcionario_id = f.id 
    WHERE 1=1
";
if ($mes) $sql_descontos .= " AND d.mes = '$mes'";
if ($ano) $sql_descontos .= " AND d.ano = '$ano'";
$sql_descontos .= " ORDER BY d.id DESC";

$stmtDescontos = $pdo->query($sql_descontos);
$descontos = $stmtDescontos->fetchAll();

// =============================================
// BUSCAR BENEFÍCIOS
// =============================================
$sql_beneficios = "
    SELECT b.*, f.nome as funcionario_nome, f.cargo 
    FROM beneficios_funcionarios b 
    JOIN funcionarios f ON b.funcionario_id = f.id 
    WHERE 1=1
";
if ($mes) $sql_beneficios .= " AND b.mes = '$mes'";
if ($ano) $sql_beneficios .= " AND b.ano = '$ano'";
$sql_beneficios .= " ORDER BY b.id DESC";

$stmtBeneficios = $pdo->query($sql_beneficios);
$beneficios = $stmtBeneficios->fetchAll();

// =============================================
// TOTAIS
// =============================================
$totalEmprestimos = array_sum(array_column($emprestimos, 'valor'));
$totalDescontos = array_sum(array_column($descontos, 'valor'));
$totalBeneficios = array_sum(array_column($beneficios, 'valor'));

$nomeMes = date('F', mktime(0, 0, 0, $mes, 1, $ano));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descontos e Benefícios - <?= htmlspecialchars($nome_fantasia) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f0f4f8; 
            color: #1a2332;
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 20px;
            max-width: calc(100% - 280px);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        .page-title { font-size: 28px; font-weight: 800; color: #1a2332; }
        .page-title span { color: #c9a84c; }
        .page-subtitle { color: #64748b; font-size: 14px; margin-top: 4px; }
        
        .btn-outline {
            background: transparent;
            color: #c9a84c;
            padding: 10px 24px;
            border: 2px solid #c9a84c;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-outline:hover { background: rgba(197,165,50,0.1); transform: translateY(-2px); }
        
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,165,50,0.3); }
        
        .btn-print {
            background: #1a2332;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .btn-print:hover { background: #2c3e50; transform: translateY(-2px); }
        
        .filtros {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filtros .grupo {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filtros .grupo label {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filtros .grupo select {
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            background: white;
            min-width: 120px;
        }
        .filtros .grupo select:focus {
            border-color: #c9a84c;
            outline: none;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 18px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .stat-card .number { font-size: 24px; font-weight: 800; color: #1a2332; }
        .stat-card .label { font-size: 11px; color: #94a3b8; margin-top: 4px; }
        .stat-card .icon { font-size: 22px; display: block; margin-bottom: 8px; }
        .stat-card .sub-info { font-size: 10px; color: #94a3b8; margin-top: 5px; }
        .stat-card.primary { border-left: 4px solid #3498db; }
        .stat-card.success { border-left: 4px solid #2ecc71; }
        .stat-card.warning { border-left: 4px solid #f39c12; }
        .stat-card.danger { border-left: 4px solid #e74c3c; }
        .stat-card.purple { border-left: 4px solid #8b5cf6; }
        .stat-card.gold { border-left: 4px solid #c9a84c; }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eef2f7;
            margin-top: 15px;
            overflow-x: auto;
        }
        .table-container table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .table-container thead { background: #f8fafc; }
        .table-container th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .table-container td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        .table-container tr:hover { background: #f8fafc; }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a2332;
            margin: 25px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .section-title .icon { color: #c9a84c; }
        .section-title .periodo {
            font-size: 12px;
            font-weight: 400;
            color: #94a3b8;
            background: #f8fafc;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .badge-status {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-status.pendente { background: #fef3c7; color: #92400e; }
        .badge-status.pago { background: #d1fae5; color: #065f46; }
        .badge-status.cancelado { background: #fee2e2; color: #991b1b; }
        .badge-status.ativo { background: #dbeafe; color: #1e40af; }
        
        .valor-positivo { color: #2ecc71; font-weight: 600; }
        .valor-negativo { color: #e74c3c; font-weight: 600; }
        .valor-destaque { color: #c9a84c; font-weight: 700; }
        
        .modal {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            max-width: 600px;
            margin: 20px 0;
        }
        .modal h3 {
            margin-bottom: 15px;
            color: #1a2332;
            font-size: 16px;
        }
        .modal .form-group {
            margin-bottom: 15px;
        }
        .modal label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 13px;
        }
        .modal input, .modal select, .modal textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        .modal input:focus, .modal select:focus {
            border-color: #c9a84c;
            outline: none;
        }
        .modal .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-add {
            background: #c9a84c;
            color: #1a2332;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .btn-add:hover { background: #b8973a; transform: translateY(-2px); }
        
        .btn-small {
            padding: 4px 12px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
        }
        .btn-small-success { background: #2ecc71; color: white; }
        .btn-small-success:hover { background: #27ae60; }
        .btn-small-danger { background: #e74c3c; color: white; }
        .btn-small-danger:hover { background: #c0392b; }
        .btn-small-warning { background: #f39c12; color: white; }
        .btn-small-warning:hover { background: #d68910; }
        
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
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
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .tabs .tab {
            padding: 10px 20px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            color: #64748b;
            transition: all 0.3s;
            text-decoration: none;
        }
        .tabs .tab:hover { background: #f8fafc; }
        .tabs .tab.active {
            background: #c9a84c;
            color: #1a2332;
            border-color: #c9a84c;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        details summary {
            cursor: pointer;
            font-weight: 600;
            color: #c9a84c;
            font-size: 14px;
            padding: 10px 0;
        }
        details summary:hover { color: #b8973a; }
        
        .info-box {
            background: #f8fafc;
            border: 1px solid #eef2f7;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 12px;
            color: #64748b;
        }
        .info-box .item strong {
            color: #1a2332;
        }
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; max-width: 100%; padding: 15px; }
            .modal .row-2 { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .filtros { flex-direction: column; align-items: stretch; }
            .filtros .grupo { width: 100%; }
            .filtros .grupo select { width: 100%; }
            .tabs { flex-direction: column; }
            .tabs .tab { border-radius: 8px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .modal .row-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">💰 <span>Descontos</span> e Benefícios</h1>
                    <p class="page-subtitle">Gerenciamento de empréstimos, descontos e benefícios para funcionários</p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
                    <a href="relatorios.php" class="btn-outline"><i class="fas fa-chart-bar"></i> Relatórios</a>
                    <a href="index.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✅ <?= htmlspecialchars($_GET['msg'] ?? 'Operação realizada com sucesso!') ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    ❌ <?= htmlspecialchars($_GET['msg'] ?? 'Erro ao realizar operação!') ?>
                </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="filtros">
                <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; width: 100%;">
                    <div class="grupo">
                        <label>Mês</label>
                        <select name="mes">
                            <?php for($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $mes == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="grupo">
                        <label>Ano</label>
                        <select name="ano">
                            <?php for($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
                                <option value="<?= $a ?>" <?= $ano == $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="grupo">
                        <label>Status</label>
                        <select name="status">
                            <option value="todos" <?= $status_filter == 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="pendente" <?= $status_filter == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="pago" <?= $status_filter == 'pago' ? 'selected' : '' ?>>Pago</option>
                            <option value="cancelado" <?= $status_filter == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="grupo">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-gold"><i class="fas fa-filter"></i> Filtrar</button>
                    </div>
                    <div class="grupo" style="margin-left: auto;">
                        <a href="descontos_beneficios.php?mes=<?= date('m') ?>&ano=<?= date('Y') ?>" class="btn-outline" style="padding: 8px 16px; font-size: 13px;">
                            <i class="fas fa-sync"></i> Atualizar
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <span class="icon">🏦</span>
                    <div class="number"><?= count($emprestimos) ?></div>
                    <div class="label">Total Empréstimos</div>
                    <div class="sub-info">Kz <?= number_format($totalEmprestimos, 2, ',', '.') ?></div>
                </div>
                <div class="stat-card danger">
                    <span class="icon">📉</span>
                    <div class="number"><?= count($descontos) ?></div>
                    <div class="label">Total Descontos</div>
                    <div class="sub-info">Kz <?= number_format($totalDescontos, 2, ',', '.') ?></div>
                </div>
                <div class="stat-card success">
                    <span class="icon">📈</span>
                    <div class="number"><?= count($beneficios) ?></div>
                    <div class="label">Total Benefícios</div>
                    <div class="sub-info">Kz <?= number_format($totalBeneficios, 2, ',', '.') ?></div>
                </div>
                <div class="stat-card gold">
                    <span class="icon">📊</span>
                    <div class="number">Kz <?= number_format($totalEmprestimos + $totalDescontos + $totalBeneficios, 2, ',', '.') ?></div>
                    <div class="label">Total Geral</div>
                    <div class="sub-info"><?= $nomeMes ?>/<?= $ano ?></div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <a href="#emprestimos" class="tab active" onclick="showTab('emprestimos')">
                    <i class="fas fa-hand-holding-usd"></i> Empréstimos
                </a>
                <a href="#descontos" class="tab" onclick="showTab('descontos')">
                    <i class="fas fa-minus-circle"></i> Descontos
                </a>
                <a href="#beneficios" class="tab" onclick="showTab('beneficios')">
                    <i class="fas fa-gift"></i> Benefícios
                </a>
            </div>
            
            <!-- ==========================================
            TAB - EMPRÉSTIMOS
            ========================================== -->
            <div id="tab-emprestimos" class="tab-content active">
                
                <!-- Cadastrar Empréstimo -->
                <div class="no-print" style="margin-bottom: 20px;">
                    <details>
                        <summary><i class="fas fa-plus-circle"></i> Novo Empréstimo</summary>
                        <div class="modal">
                            <form method="POST">
                                <input type="hidden" name="action" value="salvar_emprestimo">
                                
                                <div class="form-group">
                                    <label>Funcionário</label>
                                    <select name="funcionario_id" required>
                                        <option value="">Selecione</option>
                                        <?php foreach($funcionarios as $func): ?>
                                            <option value="<?= $func['id'] ?>">
                                                <?= htmlspecialchars($func['nome']) ?> - <?= htmlspecialchars($func['cargo'] ?? 'Sem cargo') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Valor do Empréstimo (Kz)</label>
                                    <input type="number" step="0.01" name="valor" required placeholder="0.00">
                                </div>
                                
                                <div class="row-2">
                                    <div class="form-group">
                                        <label>Data do Empréstimo</label>
                                        <input type="date" name="data_emprestimo" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Data de Pagamento</label>
                                        <input type="date" name="data_pagamento">
                                    </div>
                                </div>
                                
                                <div class="row-2">
                                    <div class="form-group">
                                        <label>Tipo de Pagamento</label>
                                        <select name="tipo_pagamento" onchange="toggleParcelas()">
                                            <option value="total">Total</option>
                                            <option value="parcelado">Parcelado</option>
                                        </select>
                                    </div>
                                    <div class="form-group" id="parcelas_group">
                                        <label>Número de Parcelas</label>
                                        <input type="number" name="parcelas" value="2" min="2" max="24">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Descrição</label>
                                    <input type="text" name="descricao" placeholder="Motivo do empréstimo">
                                </div>
                                
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status">
                                        <option value="pendente">Pendente</option>
                                        <option value="pago">Pago</option>
                                        <option value="cancelado">Cancelado</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn-add"><i class="fas fa-save"></i> Salvar Empréstimo</button>
                            </form>
                        </div>
                    </details>
                </div>
                
                <!-- Lista de Empréstimos -->
                <div class="section-title">
                    <span class="icon">🏦</span> Empréstimos Registrados
                    <span class="periodo"><?= $nomeMes ?>/<?= $ano ?></span>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Funcionário</th>
                                <th>Cargo</th>
                                <th>Valor</th>
                                <th>Data Empréstimo</th>
                                <th>Data Pagamento</th>
                                <th>Tipo</th>
                                <th>Parcelas</th>
                                <th>Status</th>
                                <th class="no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($emprestimos) > 0): ?>
                                <?php $contador = 1; ?>
                                <?php foreach($emprestimos as $emp): ?>
                                <tr>
                                    <td><?= $contador++ ?></td>
                                    <td><strong><?= htmlspecialchars($emp['funcionario_nome']) ?></strong></td>
                                    <td><?= htmlspecialchars($emp['cargo'] ?? '—') ?></td>
                                    <td class="valor-negativo">Kz <?= number_format($emp['valor'], 2, ',', '.') ?></td>
                                    <td><?= date('d/m/Y', strtotime($emp['data_emprestimo'])) ?></td>
                                    <td><?= $emp['data_pagamento'] ? date('d/m/Y', strtotime($emp['data_pagamento'])) : '—' ?></td>
                                    <td><?= ucfirst($emp['tipo_pagamento']) ?></td>
                                    <td><?= $emp['parcelas'] ?></td>
                                    <td>
                                        <span class="badge-status <?= $emp['status'] ?>">
                                            <?= ucfirst($emp['status']) ?>
                                        </span>
                                    </td>
                                    <td class="no-print">
                                        <?php if ($emp['status'] == 'pendente'): ?>
                                            <a href="?atualizar_status=1&id=<?= $emp['id'] ?>&status=pago&mes=<?= $mes ?>&ano=<?= $ano ?>" 
                                               class="btn-small btn-small-success" 
                                               onclick="return confirm('Confirmar pagamento do empréstimo?')">
                                                <i class="fas fa-check"></i> Pagar
                                            </a>
                                            <a href="?atualizar_status=1&id=<?= $emp['id'] ?>&status=cancelado&mes=<?= $mes ?>&ano=<?= $ano ?>" 
                                               class="btn-small btn-small-danger" 
                                               onclick="return confirm('Cancelar empréstimo?')">
                                                <i class="fas fa-times"></i> Cancelar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 30px; color: #999;">
                                        <i class="fas fa-inbox" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                                        Nenhum empréstimo encontrado
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ==========================================
            TAB - DESCONTOS
            ========================================== -->
            <div id="tab-descontos" class="tab-content">
                
                <!-- Cadastrar Desconto -->
                <div class="no-print" style="margin-bottom: 20px;">
                    <details>
                        <summary><i class="fas fa-plus-circle"></i> Novo Desconto</summary>
                        <div class="modal">
                            <form method="POST">
                                <input type="hidden" name="action" value="salvar_desconto">
                                
                                <div class="form-group">
                                    <label>Funcionário</label>
                                    <select name="funcionario_id" required>
                                        <option value="">Selecione</option>
                                        <?php foreach($funcionarios as $func): ?>
                                            <option value="<?= $func['id'] ?>">
                                                <?= htmlspecialchars($func['nome']) ?> - <?= htmlspecialchars($func['cargo'] ?? 'Sem cargo') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Tipo de Desconto</label>
                                    <select name="tipo_desconto" required>
                                        <option value="">Selecione</option>
                                        <option value="adiantamento">Adiantamento</option>
                                        <option value="penalidade">Penalidade</option>
                                        <option value="restituicao">Restituição</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Valor (Kz)</label>
                                    <input type="number" step="0.01" name="valor" required placeholder="0.00">
                                </div>
                                
                                <div class="row-2">
                                    <div class="form-group">
                                        <label>Data Início</label>
                                        <input type="date" name="data_inicio" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Data Fim</label>
                                        <input type="date" name="data_fim">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Descrição</label>
                                    <input type="text" name="descricao" placeholder="Motivo do desconto">
                                </div>
                                
                                <button type="submit" class="btn-add"><i class="fas fa-save"></i> Salvar Desconto</button>
                            </form>
                        </div>
                    </details>
                </div>
                
                <!-- Lista de Descontos -->
                <div class="section-title">
                    <span class="icon">📉</span> Descontos Registrados
                    <span class="periodo"><?= $nomeMes ?>/<?= $ano ?></span>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Funcionário</th>
                                <th>Cargo</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Data Início</th>
                                <th>Data Fim</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($descontos) > 0): ?>
                                <?php $contador = 1; ?>
                                <?php foreach($descontos as $desc): ?>
                                <tr>
                                    <td><?= $contador++ ?></td>
                                    <td><strong><?= htmlspecialchars($desc['funcionario_nome']) ?></strong></td>
                                    <td><?= htmlspecialchars($desc['cargo'] ?? '—') ?></td>
                                    <td><?= ucfirst($desc['tipo_desconto']) ?></td>
                                    <td class="valor-negativo">Kz <?= number_format($desc['valor'], 2, ',', '.') ?></td>
                                    <td><?= date('d/m/Y', strtotime($desc['data_inicio'])) ?></td>
                                    <td><?= $desc['data_fim'] ? date('d/m/Y', strtotime($desc['data_fim'])) : '—' ?></td>
                                    <td><?= htmlspecialchars($desc['descricao'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                        <i class="fas fa-inbox" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                                        Nenhum desconto encontrado
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ==========================================
            TAB - BENEFÍCIOS
            ========================================== -->
            <div id="tab-beneficios" class="tab-content">
                
                <!-- Cadastrar Benefício -->
                <div class="no-print" style="margin-bottom: 20px;">
                    <details>
                        <summary><i class="fas fa-plus-circle"></i> Novo Benefício</summary>
                        <div class="modal">
                            <form method="POST">
                                <input type="hidden" name="action" value="salvar_beneficio">
                                
                                <div class="form-group">
                                    <label>Funcionário</label>
                                    <select name="funcionario_id" required>
                                        <option value="">Selecione</option>
                                        <?php foreach($funcionarios as $func): ?>
                                            <option value="<?= $func['id'] ?>">
                                                <?= htmlspecialchars($func['nome']) ?> - <?= htmlspecialchars($func['cargo'] ?? 'Sem cargo') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Tipo de Benefício</label>
                                    <select name="tipo_beneficio" required>
                                        <option value="">Selecione</option>
                                        <option value="alimentacao">Alimentação</option>
                                        <option value="transporte">Transporte</option>
                                        <option value="saude">Saúde</option>
                                        <option value="educacao">Educação</option>
                                        <option value="bonus">Bônus</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Valor (Kz)</label>
                                    <input type="number" step="0.01" name="valor" required placeholder="0.00">
                                </div>
                                
                                <div class="form-group">
                                    <label>Data Início</label>
                                    <input type="date" name="data_inicio" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Descrição</label>
                                    <input type="text" name="descricao" placeholder="Motivo do benefício">
                                </div>
                                
                                <button type="submit" class="btn-add"><i class="fas fa-save"></i> Salvar Benefício</button>
                            </form>
                        </div>
                    </details>
                </div>
                
                <!-- Lista de Benefícios -->
                <div class="section-title">
                    <span class="icon">📈</span> Benefícios Registrados
                    <span class="periodo"><?= $nomeMes ?>/<?= $ano ?></span>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Funcionário</th>
                                <th>Cargo</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Data Início</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($beneficios) > 0): ?>
                                <?php $contador = 1; ?>
                                <?php foreach($beneficios as $ben): ?>
                                <tr>
                                    <td><?= $contador++ ?></td>
                                    <td><strong><?= htmlspecialchars($ben['funcionario_nome']) ?></strong></td>
                                    <td><?= htmlspecialchars($ben['cargo'] ?? '—') ?></td>
                                    <td><?= ucfirst($ben['tipo_beneficio']) ?></td>
                                    <td class="valor-positivo">Kz <?= number_format($ben['valor'], 2, ',', '.') ?></td>
                                    <td><?= date('d/m/Y', strtotime($ben['data_inicio'])) ?></td>
                                    <td><?= htmlspecialchars($ben['descricao'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                                        <i class="fas fa-inbox" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                                        Nenhum benefício encontrado
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Info Box -->
            <div class="info-box">
                <div class="item"><strong>💰 Total Empréstimos:</strong> Kz <?= number_format($totalEmprestimos, 2, ',', '.') ?></div>
                <div class="item"><strong>📉 Total Descontos:</strong> Kz <?= number_format($totalDescontos, 2, ',', '.') ?></div>
                <div class="item"><strong>📈 Total Benefícios:</strong> Kz <?= number_format($totalBeneficios, 2, ',', '.') ?></div>
                <div class="item"><strong>📊 Saldo:</strong> Kz <?= number_format($totalBeneficios - ($totalEmprestimos + $totalDescontos), 2, ',', '.') ?></div>
                <div class="item"><strong>📅 Período:</strong> <?= $nomeMes ?>/<?= $ano ?></div>
            </div>
            
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        function showTab(tab) {
            // Esconder todas as tabs
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('active');
            });
            
            // Remover active de todas as tabs
            document.querySelectorAll('.tabs .tab').forEach(el => {
                el.classList.remove('active');
            });
            
            // Mostrar tab selecionada
            document.getElementById('tab-' + tab).classList.add('active');
            
            // Marcar tab como ativa
            document.querySelectorAll('.tabs .tab').forEach(el => {
                if (el.getAttribute('href') == '#' + tab) {
                    el.classList.add('active');
                }
            });
        }
        
        function toggleParcelas() {
            var tipo = document.querySelector('select[name="tipo_pagamento"]').value;
            var parcelasGroup = document.getElementById('parcelas_group');
            if (tipo == 'parcelado') {
                parcelasGroup.style.display = 'block';
            } else {
                parcelasGroup.style.display = 'none';
            }
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar se há uma tab na URL
            var hash = window.location.hash;
            if (hash) {
                var tab = hash.replace('#', '');
                showTab(tab);
            }
            
            // Inicializar parcelas
            toggleParcelas();
        });
    </script>
</body>
</html>