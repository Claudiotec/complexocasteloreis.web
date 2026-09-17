<?php
/**
 * Relatório de Mensalidades com Status de Pagamento - Softgest Web
 */

// ============================================
// 1. CONFIGURAÇÕES INICIAIS
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] == '') {
    header('Location: ../../../../../login.php');
    exit;
}

// ============================================
// 2. CONEXÃO COM BANCO DE DADOS
// ============================================

$db_host = 'localhost';
$db_name = 'softgest_db';
$db_user = 'root';
$db_pass = '';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die('<div style="padding:20px;margin:20px;border:1px solid #f5c6cb;background:#f8d7da;border-radius:5px;color:#721c24;">
        <h2>Erro de Conexão</h2>
        <p>' . mysqli_connect_error() . '</p>
    </div>');
}

mysqli_set_charset($conn, "utf8mb4");

// ============================================
// 3. FUNÇÕES AUXILIARES
// ============================================

function mesNome($mes) {
    $meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];
    return $meses[(int)$mes] ?? 'Mês inválido';
}

function formatarMoeda($valor) {
    return 'Kz ' . number_format($valor, 2, ',', '.');
}

function formatarData($data) {
    if (empty($data) || $data == '0000-00-00') {
        return '-';
    }
    return date('d/m/Y', strtotime($data));
}

function formaPagamentoLabel($forma) {
    $labels = [
        'dinheiro' => '💰 Dinheiro',
        'transferencia' => '🏦 Transferência',
        'pix' => '📱 PIX',
        'cartao' => '💳 Cartão',
        'cartao_credito' => '💳 Cartão Crédito',
        'cartao_debito' => '💳 Cartão Débito',
        'boleto' => '📄 Boleto'
    ];
    return $labels[$forma] ?? ucfirst($forma);
}

// ============================================
// 4. RECEBE PARÂMETROS
// ============================================

$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
$situacao = isset($_GET['situacao']) ? mysqli_real_escape_string($conn, $_GET['situacao']) : 'todos';
$busca = isset($_GET['busca']) ? trim(mysqli_real_escape_string($conn, $_GET['busca'])) : '';

$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : 50;

// ============================================
// 5. QUERY PRINCIPAL
// ============================================

$sql_base = "
    SELECT 
        m.id,
        m.aluno_id,
        m.turma_id,
        m.emolumento_id,
        m.mes,
        m.ano,
        m.valor,
        m.valor_pago,
        m.data_vencimento,
        m.status AS mensalidade_status,
        m.descricao,
        m.num_documento,
        m.created_at,
        a.nome AS aluno_nome,
        a.TURMA AS turma_nome,
        a.Classe AS classe,
        a.Curso AS curso_nome,
        a.N_BI AS bi,
        a.telefone,
        a.email,
        a.Contacto4 AS telefone_responsavel,
        a.Nome_do_Pai,
        a.Nome_da_mae,
        t.nome AS turma_nome2,
        t.classe AS turma_classe,
        e.nome AS emolumento_nome,
        e.valor AS emolumento_valor,
        e.tipo AS emolumento_tipo,
        (SELECT COUNT(*) FROM pagamentos p 
         WHERE p.aluno_id = m.aluno_id 
         AND p.emolumento_id = m.emolumento_id
         AND p.status = 'confirmado'
         AND (
             p.mes_referencia = CONCAT(
                 ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                 '/',
                 m.ano
             )
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
         )
        ) AS tem_pagamento,
        (SELECT SUM(p.valor) FROM pagamentos p 
         WHERE p.aluno_id = m.aluno_id 
         AND p.emolumento_id = m.emolumento_id
         AND p.status = 'confirmado'
         AND (
             p.mes_referencia = CONCAT(
                 ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                 '/',
                 m.ano
             )
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
         )
        ) AS valor_efetivamente_pago,
        (SELECT p.data_pagamento FROM pagamentos p 
         WHERE p.aluno_id = m.aluno_id 
         AND p.emolumento_id = m.emolumento_id
         AND p.status = 'confirmado'
         AND (
             p.mes_referencia = CONCAT(
                 ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                 '/',
                 m.ano
             )
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
         )
         ORDER BY p.data_pagamento DESC
         LIMIT 1
        ) AS data_pagamento_real,
        (SELECT p.forma_pagamento FROM pagamentos p 
         WHERE p.aluno_id = m.aluno_id 
         AND p.emolumento_id = m.emolumento_id
         AND p.status = 'confirmado'
         AND (
             p.mes_referencia = CONCAT(
                 ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                 '/',
                 m.ano
             )
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
         )
         ORDER BY p.data_pagamento DESC
         LIMIT 1
        ) AS forma_pagamento_real
    FROM 
        mensalidades m
    INNER JOIN 
        alunos a ON m.aluno_id = a.id
    LEFT JOIN 
        turmas t ON m.turma_id = t.id
    LEFT JOIN 
        emolumentos e ON m.emolumento_id = e.id
    WHERE 
        1=1
";

$conditions = [];

if ($ano > 0) {
    $conditions[] = "m.ano = $ano";
}

if ($mes > 0) {
    $conditions[] = "m.mes = $mes";
}

if ($situacao != 'todos' && !empty($situacao)) {
    if ($situacao == 'pago') {
        $conditions[] = "EXISTS (
            SELECT 1 FROM pagamentos p 
            WHERE p.aluno_id = m.aluno_id 
            AND p.emolumento_id = m.emolumento_id
            AND p.status = 'confirmado'
            AND (
                p.mes_referencia = CONCAT(
                    ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                    '/',
                    m.ano
                )
                OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
            )
        )";
    } elseif ($situacao == 'pendente') {
        $conditions[] = "NOT EXISTS (
            SELECT 1 FROM pagamentos p 
            WHERE p.aluno_id = m.aluno_id 
            AND p.emolumento_id = m.emolumento_id
            AND p.status = 'confirmado'
            AND (
                p.mes_referencia = CONCAT(
                    ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                    '/',
                    m.ano
                )
                OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
            )
        )";
        $conditions[] = "m.status != 'cancelado'";
    } elseif ($situacao == 'atrasado') {
        $conditions[] = "NOT EXISTS (
            SELECT 1 FROM pagamentos p 
            WHERE p.aluno_id = m.aluno_id 
            AND p.emolumento_id = m.emolumento_id
            AND p.status = 'confirmado'
            AND (
                p.mes_referencia = CONCAT(
                    ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                    '/',
                    m.ano
                )
                OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
            )
        )";
        $conditions[] = "m.data_vencimento < CURDATE()";
        $conditions[] = "m.status != 'cancelado'";
    } elseif ($situacao == 'cancelado') {
        $conditions[] = "m.status = 'cancelado'";
    }
}

if (!empty($busca)) {
    $conditions[] = "(a.nome LIKE '%$busca%' OR a.N_BI LIKE '%$busca%' OR a.telefone LIKE '%$busca%')";
}

if (!empty($conditions)) {
    $sql_base .= " AND " . implode(" AND ", $conditions);
}

$sql_base .= " ORDER BY m.data_vencimento DESC, m.id DESC";

// Contagem total
$sql_count = "SELECT COUNT(*) as total FROM mensalidades m 
              INNER JOIN alunos a ON m.aluno_id = a.id 
              WHERE 1=1";

if (!empty($conditions)) {
    $sql_count .= " AND " . implode(" AND ", $conditions);
}

$count_result = mysqli_query($conn, $sql_count);
$total_registros = mysqli_fetch_assoc($count_result)['total'];
mysqli_free_result($count_result);

$offset = ($pagina - 1) * $por_pagina;
$sql_final = $sql_base . " LIMIT $offset, $por_pagina";
$result = mysqli_query($conn, $sql_final);

if (!$result) {
    die('<div class="alert alert-danger"><h4>Erro na consulta</h4><p>' . mysqli_error($conn) . '</p></div>');
}

$totais = [
    'quantidade' => 0,
    'valor_total' => 0,
    'valor_pago' => 0,
    'valor_pendente' => 0,
    'valor_atrasado' => 0,
    'valor_cancelado' => 0,
    'quantidade_paga' => 0,
    'quantidade_pendente' => 0,
    'quantidade_atrasado' => 0
];

$dados = [];
while ($row = mysqli_fetch_assoc($result)) {
    $dados[] = $row;
    
    $totais['quantidade']++;
    $totais['valor_total'] += (float)$row['valor'];
    
    $tem_pagamento = $row['tem_pagamento'] > 0;
    
    if ($row['mensalidade_status'] == 'cancelado') {
        $totais['valor_cancelado'] += (float)$row['valor'];
    } elseif ($tem_pagamento) {
        $totais['valor_pago'] += (float)$row['valor'];
        $totais['quantidade_paga']++;
    } else {
        $hoje = time();
        $data_venc = strtotime($row['data_vencimento']);
        if ($data_venc < $hoje) {
            $totais['valor_atrasado'] += (float)$row['valor'];
            $totais['quantidade_atrasado']++;
        } else {
            $totais['valor_pendente'] += (float)$row['valor'];
            $totais['quantidade_pendente']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Mensalidades - Softgest</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .relatorio-container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        
        .header-card {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .header-card h2 { margin: 0; font-weight: 300; }
        .header-card .subtitle { opacity: 0.8; font-size: 14px; }
        
        .filtros-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .totais-card {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .total-item {
            text-align: center;
            padding: 10px;
            border-right: 1px solid #e9ecef;
        }
        .total-item:last-child { border-right: none; }
        .total-item .valor { font-size: 20px; font-weight: bold; color: #2d3436; }
        .total-item .rotulo { font-size: 11px; color: #636e72; text-transform: uppercase; letter-spacing: 0.5px; }
        .total-item .valor.text-success { color: #00b894; }
        .total-item .valor.text-danger { color: #e17055; }
        .total-item .valor.text-warning { color: #fdcb6e; }
        .total-item .valor.text-info { color: #0984e3; }
        .total-item .valor.text-secondary { color: #636e72; }
        
        .badge-status { font-size: 12px; padding: 5px 12px; border-radius: 20px; font-weight: 500; }
        
        .table-relatorio {
            font-size: 13px;
            margin-bottom: 0;
        }
        .table-relatorio thead th {
            background: #2d3436;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            border: none;
            padding: 12px 10px;
        }
        .table-relatorio tbody td { padding: 10px; vertical-align: middle; border-bottom: 1px solid #f1f2f6; }
        .table-relatorio tbody tr:hover { background: #f8f9fa; }
        .table-relatorio tbody tr.pago { background: #f0fdf4; }
        .table-relatorio tbody tr.atrasado { background: #fef2f2; }
        
        .card-relatorio {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .card-relatorio .card-header {
            background: white;
            border-bottom: 2px solid #f1f2f6;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .pago-badge { background: #d1fae5; color: #065f46; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .pendente-badge { background: #fef3c7; color: #92400e; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .atrasado-badge { background: #fee2e2; color: #991b1b; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        
        /* ===== BOTÕES ===== */
        .btn-notificar-whats {
            background: #25D366;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-notificar-whats:hover { background: #1da851; transform: scale(1.05); }
        
        .btn-extrato {
            background: #6c5ce7;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-extrato:hover { background: #5a4bd1; transform: scale(1.05); }
        
        .btn-group .btn { font-size: 11px; padding: 4px 8px; }
        
        @media print {
            .no-print { display: none !important; }
            .relatorio-container { padding: 0; }
            .header-card { background: #333 !important; }
        }
    </style>
</head>
<body>

<div class="relatorio-container">
    
    <!-- HEADER -->
    <div class="header-card no-print">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2>
                    <i class="fas fa-file-invoice me-2"></i> Relatório de Mensalidades
                    <span class="subtitle ms-3">
                        <i class="fas fa-calendar-alt me-1"></i>
                        <?= mesNome($mes) ?>/<?= $ano ?>
                    </span>
                </h2>
                <div class="subtitle mt-1">
                    <i class="fas fa-user me-1"></i> 
                    <?= $_SESSION['nome'] ?? $_SESSION['usuario_nome'] ?? 'Usuário' ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-clock me-1"></i>
                    <?= date('d/m/Y H:i:s') ?>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <button onclick="window.print()" class="btn btn-light btn-sm me-2">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <a href="?<?= http_build_query(array_merge($_GET, ['formato' => 'pdf'])) ?>" class="btn btn-danger btn-sm me-2">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['formato' => 'excel'])) ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filtros-card no-print">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Ano</label>
                <select name="ano" class="form-select form-select-sm">
                    <?php for($a = date('Y') - 5; $a <= date('Y') + 1; $a++): ?>
                        <option value="<?= $a ?>" <?= $a == $ano ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Mês</label>
                <select name="mes" class="form-select form-select-sm">
                    <?php for($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= mesNome($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Situação</label>
                <select name="situacao" class="form-select form-select-sm">
                    <option value="todos" <?= $situacao == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="pago" <?= $situacao == 'pago' ? 'selected' : '' ?>>Pago</option>
                    <option value="pendente" <?= $situacao == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="atrasado" <?= $situacao == 'atrasado' ? 'selected' : '' ?>>Atrasado</option>
                    <option value="cancelado" <?= $situacao == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">Buscar</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="busca" class="form-control" 
                           placeholder="Nome, BI ou Telefone" 
                           value="<?= htmlspecialchars($busca) ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="?ano=<?= date('Y') ?>&mes=<?= date('m') ?>" class="btn btn-secondary">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-filter me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- TOTAIS -->
    <div class="totais-card">
        <div class="row">
            <div class="col-md-3 total-item">
                <div class="rotulo">Total</div>
                <div class="valor"><?= number_format($totais['quantidade'], 0, ',', '.') ?></div>
            </div>
            <div class="col-md-3 total-item">
                <div class="rotulo">Valor Total</div>
                <div class="valor"><?= formatarMoeda($totais['valor_total']) ?></div>
            </div>
            <div class="col-md-3 total-item">
                <div class="rotulo">💰 Pago</div>
                <div class="valor text-success"><?= formatarMoeda($totais['valor_pago']) ?></div>
                <small><?= $totais['quantidade_paga'] ?> registros</small>
            </div>
            <div class="col-md-3 total-item">
                <div class="rotulo">⏳ Pendente</div>
                <div class="valor text-warning"><?= formatarMoeda($totais['valor_pendente']) ?></div>
                <small><?= $totais['quantidade_pendente'] ?> registros</small>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-4 total-item">
                <div class="rotulo">⚠️ Atrasado</div>
                <div class="valor text-danger"><?= formatarMoeda($totais['valor_atrasado']) ?></div>
                <small><?= $totais['quantidade_atrasado'] ?> registros</small>
            </div>
            <div class="col-md-4 total-item">
                <div class="rotulo">❌ Cancelado</div>
                <div class="valor text-secondary"><?= formatarMoeda($totais['valor_cancelado']) ?></div>
            </div>
            <div class="col-md-4 total-item">
                <div class="rotulo">📊 Taxa de Pagamento</div>
                <div class="valor text-info">
                    <?php 
                    $taxa = $totais['quantidade'] > 0 ? round(($totais['quantidade_paga'] / $totais['quantidade']) * 100, 1) : 0;
                    echo $taxa . '%';
                    ?>
                </div>
                <small><?= $totais['quantidade_paga'] ?> de <?= $totais['quantidade'] ?></small>
            </div>
        </div>
    </div>

    <!-- TABELA -->
    <div class="card card-relatorio">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <i class="fas fa-list me-2"></i> Lista de Mensalidades
                    <span class="badge bg-secondary ms-2"><?= $total_registros ?> registros</span>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted small">
                        Página <?= $pagina ?> de <?= max(1, ceil($total_registros / $por_pagina)) ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-relatorio" id="tabelaMensalidades">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Aluno</th>
                            <th>Emolumento</th>
                            <th>Turma</th>
                            <th>Mês/Ano</th>
                            <th>Vencimento</th>
                            <th class="text-end">Valor</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($dados) > 0): ?>
                            <?php $cont = $offset + 1; ?>
                            <?php foreach($dados as $row): 
                                $tem_pagamento = $row['tem_pagamento'] > 0;
                                $classe_linha = '';
                                if ($tem_pagamento) $classe_linha = 'pago';
                                elseif ($row['mensalidade_status'] != 'cancelado' && strtotime($row['data_vencimento']) < time()) $classe_linha = 'atrasado';
                                $telefone = $row['telefone_responsavel'] ?? '';
                            ?>
                                <tr class="<?= $classe_linha ?>">
                                    <td><?= $cont++ ?></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($row['aluno_nome']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($row['bi'] ?? 'Sem BI') ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if(!empty($row['emolumento_nome'])): ?>
                                            <span class="badge bg-info text-dark">
                                                <?= htmlspecialchars($row['emolumento_nome']) ?>
                                            </span>
                                            <br>
                                            <small class="text-muted"><?= formatarMoeda($row['emolumento_valor'] ?? $row['valor']) ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sem emolumento</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['turma_nome'] ?? $row['turma_nome2'] ?? '-') ?></td>
                                    <td><?= mesNome($row['mes']) ?>/<?= $row['ano'] ?></td>
                                    <td><?= formatarData($row['data_vencimento']) ?></td>
                                    <td class="text-end"><strong><?= formatarMoeda($row['valor']) ?></strong></td>
                                    <td>
                                        <?php if($tem_pagamento): ?>
                                            <span class="pago-badge"><i class="fas fa-check me-1"></i> PAGO</span>
                                            <?php if($row['forma_pagamento_real']): ?>
                                                <br><small class="text-muted"><?= formaPagamentoLabel($row['forma_pagamento_real']) ?></small>
                                            <?php endif; ?>
                                        <?php elseif($row['mensalidade_status'] == 'cancelado'): ?>
                                            <span class="badge bg-secondary"><i class="fas fa-times me-1"></i> Cancelado</span>
                                        <?php elseif(strtotime($row['data_vencimento']) < time()): ?>
                                            <span class="atrasado-badge"><i class="fas fa-exclamation-triangle me-1"></i> ATRASADO</span>
                                        <?php else: ?>
                                            <span class="pendente-badge"><i class="fas fa-clock me-1"></i> PENDENTE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <?php if(!empty($telefone)): ?>
                                                <button class="btn-notificar-whats" onclick="notificarWhatsApp(<?= $row['aluno_id'] ?>, '<?= htmlspecialchars($telefone) ?>')" title="Notificar Encarregado via WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-extrato" onclick="abrirExtrato(<?= $row['aluno_id'] ?>)" title="Ver Extrato do Aluno">
                                                <i class="fas fa-file-invoice"></i>
                                            </button>
                                            <?php if(!$tem_pagamento && $row['mensalidade_status'] != 'cancelado'): ?>
                                                <a href="../pagamentos/baixar.php?mensalidade_id=<?= $row['id'] ?>&aluno_id=<?= $row['aluno_id'] ?>&emolumento_id=<?= $row['emolumento_id'] ?>&mes=<?= $row['mes'] ?>&ano=<?= $row['ano'] ?>&valor=<?= $row['valor'] ?>" class="btn btn-outline-success btn-sm" title="Baixar">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="../pagamentos/view.php?id=<?= $row['id'] ?>" class="btn btn-outline-info btn-sm" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-search fa-3x text-muted d-block mb-3"></i>
                                    <h5 class="text-muted">Nenhuma mensalidade encontrada</h5>
                                    <p class="text-muted small">Tente ajustar os filtros de busca</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if($total_registros > $por_pagina): ?>
            <div class="card-footer bg-white">
                <nav aria-label="Paginação">
                    <ul class="pagination justify-content-center mb-0">
                        <?php 
                        $total_paginas = ceil($total_registros / $por_pagina);
                        $intervalo = 2;
                        $pagina_inicial = max(1, $pagina - $intervalo);
                        $pagina_final = min($total_paginas, $pagina + $intervalo);
                        ?>
                        
                        <?php if($pagina > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for($p = $pagina_inicial; $p <= $pagina_final; $p++): ?>
                            <li class="page-item <?= $p == $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if($pagina < $total_paginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="row mt-3">
        <div class="col-md-6">
            <small class="text-muted">
                <i class="fas fa-calendar-alt me-1"></i> Gerado em: <?= date('d/m/Y H:i:s') ?>
            </small>
        </div>
        <div class="col-md-6 text-end">
            <small class="text-muted">
                Softgest Sistemas &copy; <?= date('Y') ?>
            </small>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL EXTRATO (REMOVER - USAR NOVA JANELA) -->
<!-- ========================================== -->

<script>
// ============================================
// ABRIR EXTRATO - ABRE EM NOVA JANELA
// ============================================
function abrirExtrato(alunoId) {
    var url = '/softgest_web/modules/escola/financeiro/mensalidades/extrato_aluno.php?aluno_id=' + alunoId;
    window.open(url, '_blank', 'width=1024,height=800,scrollbars=yes,toolbar=yes,menubar=yes');
}

// ============================================
// NOTIFICAR VIA WHATSAPP
// ============================================
function notificarWhatsApp(alunoId, telefone) {
    if (!telefone || telefone == '') {
        alert('⚠️ O encarregado deste aluno não possui número de telefone cadastrado!');
        return;
    }
    
    var numero = telefone.replace(/\D/g, '');
    if (numero.length < 9) {
        alert('⚠️ Número de telefone inválido: ' + telefone);
        return;
    }
    
    if (numero.length === 9) {
        numero = '244' + numero;
    }
    
    var mensagem = '📋 *EXTRATO DE MENSALIDADES*\n\n';
    mensagem += '📍 Escola: <?= $_SESSION['escola_nome'] ?? 'COMPLEXO ESCOLAR CASTELO REIS' ?>\n';
    mensagem += '📅 Data: ' + new Date().toLocaleDateString('pt-BR') + '\n\n';
    mensagem += '🔗 Clique no link para visualizar o extrato completo:\n';
    mensagem += window.location.origin + '/softgest_web/modules/escola/financeiro/mensalidades/extrato_aluno.php?aluno_id=' + alunoId;
    mensagem += '\n\n📌 *Aviso automático - Não responda a esta mensagem.*';
    
    var url = 'https://api.whatsapp.com/send?phone=' + numero + '&text=' + encodeURIComponent(mensagem);
    window.open(url, '_blank');
}

// ============================================
// AUTO-SUBMIT DOS FILTROS
// ============================================
$(document).ready(function() {
    $('select[name="ano"], select[name="mes"], select[name="situacao"]').on('change', function() {
        $(this).closest('form').submit();
    });

    var timeout;
    $('input[name="busca"]').on('keyup', function() {
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            $('form').submit();
        }, 500);
    });
});
</script>

<?php
if (isset($conn)) {
    mysqli_close($conn);
}
?>
</body>
</html>