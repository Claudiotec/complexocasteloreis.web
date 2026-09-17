<?php
// ============================================
// modules/escola/financeiro/relatorios/resumo.php - Resumo Financeiro
// ============================================

// Carregar configurações
require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'relatorios')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== FUNÇÕES =====
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

function obterNomeMes($mes) {
    $meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
              'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    return $meses[$mes - 1] ?? $mes;
}

// ===== BUSCAR CONFIGURAÇÕES DA ESCOLA =====
$nome_escola = 'Sistema Escolar';
$endereco = '';
$contacto = '';
$email = '';
$nif = '';
$logo = '';

$config_file = '../../../../config_escola.json';
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
    $nome_escola = $config['nome'] ?? 'Sistema Escolar';
    $endereco = $config['endereco'] ?? '';
    $contacto = $config['contacto'] ?? '';
    $email = $config['email'] ?? '';
    $nif = $config['nif'] ?? '';
}

// ===== FILTROS =====
$ano_filtro = $_GET['ano'] ?? date('Y');
$mes_filtro = $_GET['mes'] ?? '';
$status_filtro = $_GET['status'] ?? '';
$busca_aluno = $_GET['busca'] ?? '';

// ===== BUSCAR DADOS FINANCEIROS =====
try {
    // ===== 1. RESUMO GERAL =====
    $sql_resumo = "SELECT 
                        COUNT(*) as total_mensalidades,
                        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as total_pago,
                        SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as total_pendente,
                        SUM(CASE WHEN status = 'atrasado' THEN valor ELSE 0 END) as total_atrasado,
                        SUM(valor) as total_geral
                    FROM mensalidades";
    
    if (!empty($ano_filtro)) {
        $sql_resumo .= " WHERE ano = " . intval($ano_filtro);
    }
    
    $stmt = $pdo->query($sql_resumo);
    $resumo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ===== 2. MENSALIDADES POR MÊS =====
    $sql_meses = "SELECT 
                        mes, ano,
                        COUNT(*) as quantidade,
                        SUM(valor) as total,
                        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as pago,
                        SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as pendente,
                        SUM(CASE WHEN status = 'atrasado' THEN valor ELSE 0 END) as atrasado
                    FROM mensalidades";
    
    $where = [];
    if (!empty($ano_filtro)) {
        $where[] = "ano = " . intval($ano_filtro);
    }
    if (!empty($mes_filtro)) {
        $where[] = "mes = " . intval($mes_filtro);
    }
    if (!empty($status_filtro)) {
        $where[] = "status = '" . $status_filtro . "'";
    }
    
    if (!empty($where)) {
        $sql_meses .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql_meses .= " GROUP BY mes, ano ORDER BY ano DESC, mes DESC";
    $stmt = $pdo->query($sql_meses);
    $mensalidades_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== 3. ÚLTIMOS PAGAMENTOS =====
    $sql_pagamentos = "SELECT 
                            p.*, 
                            a.nome as aluno_nome,
                            a.classe as aluno_classe,
                            a.id as aluno_id,
                            e.nome as emolumento_nome
                        FROM pagamentos p
                        LEFT JOIN alunos a ON p.aluno_id = a.id
                        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
                        ORDER BY p.id DESC LIMIT 50";
    $stmt = $pdo->query($sql_pagamentos);
    $ultimos_pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== 4. ALUNOS COM MAIORES DÉBITOS =====
    $sql_debitos = "SELECT 
                        m.aluno_id,
                        a.nome as aluno_nome,
                        a.classe as aluno_classe,
                        COUNT(*) as total_mensalidades,
                        SUM(m.valor) as total_debito
                    FROM mensalidades m
                    LEFT JOIN alunos a ON m.aluno_id = a.id
                    WHERE m.status IN ('pendente', 'atrasado')";
    
    if (!empty($ano_filtro)) {
        $sql_debitos .= " AND m.ano = " . intval($ano_filtro);
    }
    
    $sql_debitos .= " GROUP BY m.aluno_id, a.nome, a.classe
                      ORDER BY total_debito DESC LIMIT 20";
    $stmt = $pdo->query($sql_debitos);
    $alunos_devedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== 5. ESTATÍSTICAS POR ANO LETIVO =====
    $sql_ano_letivo = "SELECT 
                            ano_letivo_inicio, ano_letivo_fim,
                            COUNT(*) as total,
                            SUM(valor) as total_valor,
                            SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as total_pago
                        FROM mensalidades
                        GROUP BY ano_letivo_inicio, ano_letivo_fim
                        ORDER BY ano_letivo_inicio DESC";
    $stmt = $pdo->query($sql_ano_letivo);
    $anos_letivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $erro = $e->getMessage();
}

// ===== INCLUIR HEADER =====
include '../../includes/header_escola.php';
?>

<style>
    /* ===== ESTILOS DO RELATÓRIO ===== */
    .page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
    .page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
    .page-header .subtitle{color:#94a3b8;font-size:14px;margin:2px 0 0}
    
    .btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
    .btn-primary{background:#c9a84c;color:#1a2332}
    .btn-primary:hover{background:#b8973a;transform:translateY(-2px);box-shadow:0 4px 15px rgba(201,168,76,0.3)}
    .btn-secondary{background:#f1f5f9;color:#4a5568}
    .btn-secondary:hover{background:#e2e8f0}
    .btn-success{background:#2ecc71;color:#fff}
    .btn-success:hover{background:#27ae60}
    .btn-danger{background:#e74c3c;color:#fff}
    .btn-danger:hover{background:#c0392b}
    .btn-warning{background:#f39c12;color:#fff}
    .btn-warning:hover{background:#e67e22}
    .btn-info{background:#3498db;color:#fff}
    .btn-info:hover{background:#2980b9}
    
    .menu-financeiro{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:25px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
    .menu-financeiro a{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;color:#4a5568;background:#f8fafc;border:1px solid #e2e8f0;display:inline-flex;align-items:center;gap:6px}
    .menu-financeiro a:hover,.menu-financeiro a.active{background:#c9a84c;color:#1a2332;border-color:#c9a84c}
    
    .filtros-box{background:#f8fafc;padding:15px 20px;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:25px}
    .filtros-box .filtros-row{display:flex;flex-wrap:wrap;gap:15px;align-items:flex-end}
    .filtros-box .filtro-group{display:flex;flex-direction:column;gap:5px}
    .filtros-box .filtro-group label{font-size:12px;font-weight:600;color:#4a5568}
    .filtros-box .filtro-group input,.filtros-box .filtro-group select{padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;min-width:150px}
    .filtros-box .filtro-group input:focus,.filtros-box .filtro-group select:focus{outline:none;border-color:#c9a84c}
    
    /* ===== CARDS ===== */
    .cards-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:25px}
    .card{background:#fff;border-radius:12px;padding:20px;border:1px solid #eef2f7;box-shadow:0 2px 4px rgba(0,0,0,0.04)}
    .card .card-label{font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:600}
    .card .card-value{font-size:28px;font-weight:700;margin-top:5px}
    .card .card-value.positive{color:#27ae60}
    .card .card-value.negative{color:#e74c3c}
    .card .card-value.warning{color:#f39c12}
    .card .card-value.primary{color:#1a2332}
    .card .card-value .small{font-size:14px;font-weight:400;color:#94a3b8}
    
    /* ===== TABELAS ===== */
    .table-responsive{overflow-x:auto;margin-bottom:25px}
    .table{width:100%;border-collapse:collapse;font-size:14px;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #eef2f7}
    .table th{background:#f8fafc;padding:12px 15px;text-align:left;font-weight:600;color:#1a2332;border-bottom:2px solid #e2e8f0;font-size:12px;text-transform:uppercase;letter-spacing:0.5px}
    .table td{padding:10px 15px;border-bottom:1px solid #eef2f7;color:#4a5568}
    .table tr:last-child td{border-bottom:none}
    .table tr:hover{background:#f8fafc}
    .table .text-right{text-align:right}
    .table .text-center{text-align:center}
    .table .text-success{color:#27ae60;font-weight:600}
    .table .text-danger{color:#e74c3c;font-weight:600}
    .table .text-warning{color:#f39c12;font-weight:600}
    
    /* ===== LINK DO ALUNO ===== */
    .link-aluno{color:#1a2332;text-decoration:none;font-weight:600;transition:color .2s}
    .link-aluno:hover{color:#c9a84c;text-decoration:underline;cursor:pointer}
    
    /* ===== BADGES ===== */
    .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}
    .badge-success{background:#d1fae5;color:#065f46}
    .badge-danger{background:#fee2e2;color:#991b1b}
    .badge-warning{background:#fef3c7;color:#92400e}
    .badge-info{background:#dbeafe;color:#1e40af}
    .badge-primary{background:#e0e7ff;color:#3730a3}
    
    /* ===== SEÇÃO ===== */
    .section-title{font-size:18px;font-weight:700;color:#1a2332;margin:30px 0 15px;padding-bottom:8px;border-bottom:2px solid #eef2f7}
    .section-title .icon{margin-right:8px}
    
    /* ===== IMPRESSÃO ===== */
    @media print {
        .no-print{display:none !important}
        .menu-financeiro{display:none !important}
        .page-header .btn{display:none !important}
        .filtros-box{display:none !important}
        .card{box-shadow:none;border:1px solid #ddd}
        .table{border:1px solid #ddd}
        .table th{background:#f1f5f9 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .badge{-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .card .card-value{-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .link-aluno{text-decoration:none !important;color:#1a2332 !important}
    }
    
    @media(max-width:768px){
        .filtros-box .filtros-row{flex-direction:column;align-items:stretch}
        .filtros-box .filtro-group input,.filtros-box .filtro-group select{width:100%;min-width:auto}
        .cards-grid{grid-template-columns:1fr 1fr}
        .table{font-size:12px}
        .table th,.table td{padding:6px 8px}
    }
</style>

<div class="page-header">
    <div>
        <h1>📊 Resumo Financeiro</h1>
        <p class="subtitle">Relatório detalhado de mensalidades e pagamentos</p>
    </div>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</button>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro no-print">
    <a href="../index.php">📊 Dashboard</a>
    <a href="../pagamentos/">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="index.php" class="active">📈 Relatórios</a>
</div>

<!-- Filtros -->
<div class="filtros-box no-print">
    <form method="GET" action="">
        <div class="filtros-row">
            <div class="filtro-group">
                <label>📅 Ano</label>
                <select name="ano">
                    <option value="">Todos</option>
                    <?php for($a = date('Y') - 3; $a <= date('Y') + 1; $a++): ?>
                    <option value="<?= $a ?>" <?= ($ano_filtro == $a) ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label>📆 Mês</label>
                <select name="mes">
                    <option value="">Todos</option>
                    <?php for($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($mes_filtro == $m) ? 'selected' : '' ?>><?= obterNomeMes($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label>📌 Status</label>
                <select name="status">
                    <option value="">Todos</option>
                    <option value="pago" <?= ($status_filtro == 'pago') ? 'selected' : '' ?>>✅ Pago</option>
                    <option value="pendente" <?= ($status_filtro == 'pendente') ? 'selected' : '' ?>>⏳ Pendente</option>
                    <option value="atrasado" <?= ($status_filtro == 'atrasado') ? 'selected' : '' ?>>⚠️ Atrasado</option>
                </select>
            </div>
            <div class="filtro-group">
                <label>🔍 Buscar Aluno</label>
                <input type="text" name="busca" placeholder="Nome ou ID..." value="<?= htmlspecialchars($busca_aluno) ?>">
            </div>
            <div class="filtro-group">
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                <a href="resumo.php" class="btn btn-secondary">↺ Limpar</a>
            </div>
        </div>
    </form>
</div>

<!-- ===== CARDS ===== -->
<div class="cards-grid">
    <div class="card">
        <div class="card-label">💰 Total Geral</div>
        <div class="card-value primary"><?= formatarMoeda($resumo['total_geral'] ?? 0) ?></div>
    </div>
    <div class="card">
        <div class="card-label">✅ Total Pago</div>
        <div class="card-value positive"><?= formatarMoeda($resumo['total_pago'] ?? 0) ?></div>
    </div>
    <div class="card">
        <div class="card-label">⏳ Total Pendente</div>
        <div class="card-value warning"><?= formatarMoeda($resumo['total_pendente'] ?? 0) ?></div>
    </div>
    <div class="card">
        <div class="card-label">⚠️ Total Atrasado</div>
        <div class="card-value negative"><?= formatarMoeda($resumo['total_atrasado'] ?? 0) ?></div>
    </div>
    <div class="card">
        <div class="card-label">📄 Total Mensalidades</div>
        <div class="card-value primary"><?= number_format($resumo['total_mensalidades'] ?? 0, 0, ',', '.') ?></div>
    </div>
    <div class="card">
        <div class="card-label">📊 Taxa de Pagamento</div>
        <div class="card-value primary">
            <?php 
            $total = ($resumo['total_geral'] ?? 0);
            $pago = ($resumo['total_pago'] ?? 0);
            $taxa = $total > 0 ? round(($pago / $total) * 100, 1) : 0;
            ?>
            <?= $taxa ?>%
            <span class="small">do total</span>
        </div>
    </div>
</div>

<!-- ===== MENSALIDADES POR MÊS ===== -->
<div class="section-title"><span class="icon">📅</span> Mensalidades por Mês</div>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Mês/Ano</th>
                <th class="text-center">Quantidade</th>
                <th class="text-right">Total</th>
                <th class="text-right">✅ Pago</th>
                <th class="text-right">⏳ Pendente</th>
                <th class="text-right">⚠️ Atrasado</th>
                <th class="text-center">Taxa</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($mensalidades_por_mes)): ?>
                <?php foreach ($mensalidades_por_mes as $row): ?>
                <?php 
                $total_mes = $row['total'] ?? 0;
                $pago_mes = $row['pago'] ?? 0;
                $taxa_mes = $total_mes > 0 ? round(($pago_mes / $total_mes) * 100, 1) : 0;
                ?>
                <tr>
                    <td><strong><?= obterNomeMes($row['mes']) ?>/<?= $row['ano'] ?></strong></td>
                    <td class="text-center"><?= $row['quantidade'] ?></td>
                    <td class="text-right"><?= formatarMoeda($total_mes) ?></td>
                    <td class="text-right text-success"><?= formatarMoeda($pago_mes) ?></td>
                    <td class="text-right text-warning"><?= formatarMoeda($row['pendente'] ?? 0) ?></td>
                    <td class="text-right text-danger"><?= formatarMoeda($row['atrasado'] ?? 0) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $taxa_mes >= 70 ? 'badge-success' : ($taxa_mes >= 40 ? 'badge-warning' : 'badge-danger') ?>">
                            <?= $taxa_mes ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center">Nenhum dado encontrado</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ===== ALUNOS COM MAIORES DÉBITOS ===== -->
<?php if (!empty($alunos_devedores)): ?>
<div class="section-title"><span class="icon">🔴</span> Alunos com Maiores Débitos</div>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Aluno</th>
                <th>Classe</th>
                <th class="text-center">Mensalidades</th>
                <th class="text-right">Débito Total</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $rank = 1; ?>
            <?php foreach ($alunos_devedores as $aluno): ?>
            <tr>
                <td><?= $rank++ ?></td>
                <td>
                    <a href="../../alunos/visualizar.php?id=<?= $aluno['aluno_id'] ?>" 
                       class="link-aluno" 
                       target="_blank"
                       title="Clique para ver a ficha do aluno">
                        <?= htmlspecialchars($aluno['aluno_nome'] ?? 'N/A') ?>
                        <span style="font-size:11px;color:#94a3b8;">↗</span>
                    </a>
                </td>
                <td><?= htmlspecialchars($aluno['aluno_classe'] ?? 'N/A') ?></td>
                <td class="text-center"><?= $aluno['total_mensalidades'] ?></td>
                <td class="text-right text-danger"><strong><?= formatarMoeda($aluno['total_debito']) ?></strong></td>
                <td class="text-center">
                    <span class="badge badge-danger">⚠️ Pendente</span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ===== ÚLTIMOS PAGAMENTOS ===== -->
<div class="section-title"><span class="icon">🕐</span> Últimos Pagamentos</div>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Aluno</th>
                <th>Classe</th>
                <th>Descrição</th>
                <th class="text-right">Valor</th>
                <th>Forma</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($ultimos_pagamentos)): ?>
                <?php foreach ($ultimos_pagamentos as $pag): ?>
                <tr>
                    <td><?= formatarData($pag['data_pagamento'] ?? date('Y-m-d')) ?></td>
                    <td>
                        <a href="../../alunos/visualizar.php?id=<?= $pag['aluno_id'] ?>" 
                           class="link-aluno" 
                           target="_blank"
                           title="Clique para ver a ficha do aluno">
                            <?= htmlspecialchars($pag['aluno_nome'] ?? 'N/A') ?>
                            <span style="font-size:11px;color:#94a3b8;">↗</span>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($pag['aluno_classe'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($pag['emolumento_nome'] ?? $pag['descricao'] ?? 'Pagamento') ?></td>
                    <td class="text-right text-success"><?= formatarMoeda($pag['valor'] ?? 0) ?></td>
                    <td><?= ucfirst($pag['forma_pagamento'] ?? 'Dinheiro') ?></td>
                    <td>
                        <span class="badge badge-success">✅ Confirmado</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center">Nenhum pagamento encontrado</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ===== RESUMO POR ANO LETIVO ===== -->
<?php if (!empty($anos_letivos)): ?>
<div class="section-title"><span class="icon">📆</span> Resumo por Ano Letivo</div>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Ano Letivo</th>
                <th class="text-center">Total Mensalidades</th>
                <th class="text-right">Valor Total</th>
                <th class="text-right">✅ Valor Pago</th>
                <th class="text-right">⏳ Valor Pendente</th>
                <th class="text-center">Taxa</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($anos_letivos as $ano): 
                $total = $ano['total_valor'] ?? 0;
                $pago = $ano['total_pago'] ?? 0;
                $pendente = $total - $pago;
                $taxa = $total > 0 ? round(($pago / $total) * 100, 1) : 0;
            ?>
            <tr>
                <td><strong><?= $ano['ano_letivo_inicio'] ?>/<?= $ano['ano_letivo_fim'] ?></strong></td>
                <td class="text-center"><?= $ano['total'] ?></td>
                <td class="text-right"><?= formatarMoeda($total) ?></td>
                <td class="text-right text-success"><?= formatarMoeda($pago) ?></td>
                <td class="text-right <?= $pendente > 0 ? 'text-danger' : '' ?>"><?= formatarMoeda($pendente) ?></td>
                <td class="text-center">
                    <span class="badge <?= $taxa >= 70 ? 'badge-success' : ($taxa >= 40 ? 'badge-warning' : 'badge-danger') ?>">
                        <?= $taxa ?>%
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ===== RODAPÉ DO RELATÓRIO ===== -->
<div style="margin-top:40px;padding-top:20px;border-top:2px solid #eef2f7;display:flex;justify-content:space-between;flex-wrap:wrap;gap:15px;font-size:12px;color:#94a3b8;">
    <div>
        <strong><?= htmlspecialchars($nome_escola) ?></strong><br>
        <?= htmlspecialchars($endereco) ?><br>
        📞 <?= htmlspecialchars($contacto) ?> | ✉ <?= htmlspecialchars($email) ?>
    </div>
    <div style="text-align:right;">
        <div>📅 Gerado em: <?= date('d/m/Y H:i:s') ?></div>
        <div>👤 Usuário: <?= $_SESSION['usuario_nome'] ?? 'Administrador' ?></div>
        <div>📄 Relatório Financeiro - v1.0</div>
    </div>
</div>

<script>
    // Abrir link do aluno em nova aba ao clicar com Ctrl+Click ou Click normal
    document.querySelectorAll('.link-aluno').forEach(function(link) {
        link.addEventListener('click', function(e) {
            // Se não for Ctrl+Click, abrir na mesma aba (padrão)
            // O target="_blank" já faz isso
        });
    });
</script>

<?php include '../../includes/footer_escola.php'; ?>