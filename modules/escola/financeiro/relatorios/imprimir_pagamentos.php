<?php
// ============================================
// modules/escola/financeiro/relatorios/imprimir_pagamentos.php
// Impressão de Pagamentos Filtrados com Cabeçalho da Empresa
// FORMATO: A4 VERTICAL (RETRATO) - TODOS OS DADOS
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

$pdo->exec("SET NAMES utf8mb4");
$pdo->exec("SET CHARACTER SET utf8mb4");

// ===== FILTROS =====
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$filtro_data_fim    = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$filtro_aluno       = isset($_GET['aluno_id']) ? intval($_GET['aluno_id']) : 0;
$filtro_status      = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_forma       = isset($_GET['forma']) ? $_GET['forma'] : '';
$filtro_classe      = isset($_GET['classe']) ? $_GET['classe'] : '';
$filtro_turma       = isset($_GET['turma']) ? $_GET['turma'] : '';
$filtro_emolumentos = isset($_GET['emolumentos']) ? $_GET['emolumentos'] : [];
$ordenar_alfabetico = isset($_GET['ordenar_alfabetico']) ? $_GET['ordenar_alfabetico'] : '0';

$filtro_aluno       = max(0, intval($filtro_aluno));
$filtro_emolumentos = array_map('intval', (array)$filtro_emolumentos);
$filtro_emolumentos = array_filter($filtro_emolumentos, function($v) { return $v > 0; });

// ===== BUSCAR DADOS DA EMPRESA =====
function buscarDadosEmpresaImpressao($pdo) {
    $dados = [
        'nome' => 'Sistema Escolar',
        'endereco' => '',
        'telefone' => '',
        'celular' => '',
        'email' => '',
        'nif' => '',
        'cidade' => '',
        'estado' => '',
        'cep' => '',
        'logo' => ''
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM empresa WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            $dados['nome'] = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Sistema Escolar';
            
            $endereco_parts = [];
            if (!empty($empresa['endereco'])) $endereco_parts[] = $empresa['endereco'];
            if (!empty($empresa['numero'])) $endereco_parts[] = $empresa['numero'];
            if (!empty($empresa['bairro'])) $endereco_parts[] = $empresa['bairro'];
            if (!empty($empresa['cidade'])) $endereco_parts[] = $empresa['cidade'];
            if (!empty($empresa['estado'])) $endereco_parts[] = $empresa['estado'];
            if (!empty($empresa['cep'])) $endereco_parts[] = 'CEP: ' . $empresa['cep'];
            
            $dados['endereco'] = implode(', ', $endereco_parts);
            $dados['telefone'] = $empresa['telefone'] ?? '';
            $dados['celular'] = $empresa['celular'] ?? '';
            $dados['email'] = $empresa['email'] ?? '';
            $dados['nif'] = $empresa['cnpj'] ?? $empresa['inscricao_estadual'] ?? '';
            $dados['cidade'] = $empresa['cidade'] ?? '';
            $dados['estado'] = $empresa['estado'] ?? '';
            $dados['cep'] = $empresa['cep'] ?? '';
            $dados['logo'] = $empresa['logo'] ?? '';
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar dados da empresa: " . $e->getMessage());
    }
    
    return $dados;
}

$dados_empresa = buscarDadosEmpresaImpressao($pdo);

// ===== BUSCAR PAGAMENTOS (TODOS, SEM PAGINAÇÃO) =====
$pagamentos = [];
$total_geral = 0;
$total_confirmados = 0;
$total_pendentes = 0;
$total_valor_confirmado = 0;
$total_valor_pendente = 0;

try {
    $sql = "
        SELECT 
            p.*,
            a.nome as aluno_nome,
            a.Classe as aluno_classe,
            a.TURMA as aluno_turma,
            a.Periodo as aluno_periodo,
            e.nome as emolumento_nome
        FROM pagamentos p
        LEFT JOIN alunos a ON p.aluno_id = a.id
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        WHERE p.data_pagamento BETWEEN ? AND ?
    ";
    $params = [$filtro_data_inicio, $filtro_data_fim];
    
    if ($filtro_aluno > 0) {
        $sql .= " AND p.aluno_id = ?";
        $params[] = $filtro_aluno;
    }
    
    if (!empty($filtro_status)) {
        $sql .= " AND p.status = ?";
        $params[] = $filtro_status;
    }
    
    if (!empty($filtro_forma)) {
        $sql .= " AND p.forma_pagamento = ?";
        $params[] = $filtro_forma;
    }
    
    if (!empty($filtro_classe)) {
        $sql .= " AND a.Classe = ?";
        $params[] = $filtro_classe;
    }
    
    if (!empty($filtro_turma)) {
        $sql .= " AND a.TURMA = ?";
        $params[] = $filtro_turma;
    }
    
    if (!empty($filtro_emolumentos)) {
        $placeholders = implode(',', array_fill(0, count($filtro_emolumentos), '?'));
        $sql .= " AND p.emolumento_id IN ($placeholders)";
        $params = array_merge($params, $filtro_emolumentos);
    }
    
    if ($ordenar_alfabetico == '1') {
        $sql .= " ORDER BY a.nome ASC, p.data_pagamento DESC";
    } else {
        $sql .= " ORDER BY p.data_pagamento DESC, p.created_at DESC";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pagamentos as $p) {
        $total_geral += floatval($p['valor']);
        if ($p['status'] == 'confirmado') {
            $total_confirmados++;
            $total_valor_confirmado += floatval($p['valor']);
        } else {
            $total_pendentes++;
            $total_valor_pendente += floatval($p['valor']);
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar pagamentos: " . $e->getMessage());
}

// ===== BUSCAR NOME DO ALUNO PARA FILTRO =====
$nome_aluno_filtro = '';
if ($filtro_aluno > 0) {
    try {
        $stmt = $pdo->prepare("SELECT nome FROM alunos WHERE id = ? LIMIT 1");
        $stmt->execute([$filtro_aluno]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nome_aluno_filtro = $row['nome'] ?? '';
    } catch (Exception $e) {}
}

// ===== FUNÇÕES AUXILIARES =====
function formatarMoedaImpressao($valor) {
    return 'Kz ' . number_format($valor, 2, ',', '.');
}

function getStatusLabelImpressao($status) {
    $labels = [
        'confirmado'  => 'Confirmado',
        'pendente'    => 'Pendente',
        'cancelado'   => 'Cancelado',
        'reembolsado' => 'Reembolsado'
    ];
    return $labels[$status] ?? 'Pendente';
}

function getStatusColorImpressao($status) {
    $colors = [
        'confirmado'  => '#065f46',
        'pendente'    => '#92400e',
        'cancelado'   => '#991b1b',
        'reembolsado' => '#1e40af'
    ];
    return $colors[$status] ?? '#92400e';
}

function getStatusBgImpressao($status) {
    $bgs = [
        'confirmado'  => '#d1fae5',
        'pendente'    => '#fef3c7',
        'cancelado'   => '#fee2e2',
        'reembolsado' => '#dbeafe'
    ];
    return $bgs[$status] ?? '#fef3c7';
}

// Descrição dos filtros aplicados
$filtros_aplicados = [];
$filtros_aplicados[] = 'Período: ' . date('d/m/Y', strtotime($filtro_data_inicio)) . ' a ' . date('d/m/Y', strtotime($filtro_data_fim));
if (!empty($nome_aluno_filtro)) $filtros_aplicados[] = 'Aluno: ' . $nome_aluno_filtro;
if (!empty($filtro_status)) $filtros_aplicados[] = 'Status: ' . ucfirst($filtro_status);
if (!empty($filtro_forma)) $filtros_aplicados[] = 'Forma: ' . ucfirst($filtro_forma);
if (!empty($filtro_classe)) $filtros_aplicados[] = 'Classe: ' . $filtro_classe . 'ª';
if (!empty($filtro_turma)) $filtros_aplicados[] = 'Turma: ' . $filtro_turma;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relatório de Pagamentos - <?= htmlspecialchars($dados_empresa['nome']) ?></title>
<style>
    /* ===== FORMATO A4 VERTICAL (RETRATO) ===== */
    @page { 
        size: A4 portrait; 
        margin: 6mm; 
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { 
        font-family: Arial, Helvetica, sans-serif; 
        background: #f0f0f0; 
        padding: 0; 
        margin: 0; 
        color: #1a2332;
    }
    .page { 
        max-width: 210mm; 
        width: 100%; 
        margin: 0 auto; 
        background: #fff; 
        padding: 6mm; 
    }

    /* ===== CABEÇALHO DA EMPRESA ===== */
    .header-empresa {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 5px;
        border-bottom: 3px double #1a2332;
        margin-bottom: 6px;
        gap: 6px;
    }
    .header-empresa .logo-area {
        display: flex;
        align-items: center;
        gap: 6px;
        flex: 1;
        min-width: 0;
    }
    .header-empresa .logo-icon {
        width: 38px;
        height: 38px;
        background: #1a2332;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #c9a84c;
        font-size: 18px;
        font-weight: bold;
        border: 2px solid #c9a84c;
        flex-shrink: 0;
    }
    .header-empresa .logo-img {
        width: 38px;
        height: 38px;
        object-fit: contain;
        flex-shrink: 0;
    }
    .header-empresa .empresa-info {
        min-width: 0;
        flex: 1;
    }
    .header-empresa .empresa-info h1 {
        font-size: 11px;
        font-weight: 800;
        color: #1a2332;
        line-height: 1.1;
        word-wrap: break-word;
    }
    .header-empresa .empresa-info .endereco {
        font-size: 7px;
        color: #555;
        margin-top: 1px;
        word-wrap: break-word;
    }
    .header-empresa .empresa-info .contactos {
        font-size: 7px;
        color: #555;
        margin-top: 1px;
        word-wrap: break-word;
    }
    .header-empresa .doc-info {
        text-align: right;
        flex-shrink: 0;
        max-width: 110px;
    }
    .header-empresa .doc-info h2 {
        font-size: 9px;
        font-weight: 800;
        color: #c9a84c;
        letter-spacing: 0.3px;
        line-height: 1.1;
    }
    .header-empresa .doc-info .nif-box {
        background: #1a2332;
        color: #fff;
        padding: 1px 5px;
        border-radius: 2px;
        font-weight: bold;
        font-size: 7px;
        display: inline-block;
        margin-top: 2px;
    }
    .header-empresa .doc-info .data-emissao {
        font-size: 6px;
        color: #666;
        margin-top: 2px;
    }

    /* ===== FILTROS APLICADOS ===== */
    .filtros-aplicados {
        background: #f8fafc;
        border: 1px solid #ddd;
        border-left: 3px solid #c9a84c;
        padding: 3px 6px;
        border-radius: 3px;
        margin-bottom: 5px;
        font-size: 7px;
        color: #4a5568;
        line-height: 1.3;
    }
    .filtros-aplicados strong {
        color: #1a2332;
    }

    /* ===== RESUMO ===== */
    .resumo {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 4px;
        margin-bottom: 6px;
    }
    .resumo .card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-left: 2px solid #c9a84c;
        border-radius: 3px;
        padding: 3px 4px;
        text-align: center;
    }
    .resumo .card.total { border-left-color: #3498db; }
    .resumo .card.confirmados { border-left-color: #2ecc71; }
    .resumo .card.pendentes { border-left-color: #f39c12; }
    .resumo .card .label {
        font-size: 6px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        line-height: 1.1;
    }
    .resumo .card .valor {
        font-size: 9px;
        font-weight: 800;
        color: #1a2332;
        margin-top: 1px;
        line-height: 1.1;
    }
    .resumo .card .valor.verde { color: #2ecc71; }
    .resumo .card .valor.laranja { color: #f39c12; }

    /* ===== TABELA ===== */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 6.5px;
        margin-bottom: 5px;
        table-layout: fixed;
    }
    thead th {
        background: #1a2332;
        color: #fff;
        padding: 3px 2px;
        text-align: left;
        border: 1px solid #1a2332;
        font-size: 6.5px;
        white-space: nowrap;
        word-wrap: break-word;
        line-height: 1.1;
    }
    thead th.center { text-align: center; }
    thead th.right { text-align: right; }
    tbody td {
        padding: 2px 2px;
        border: 1px solid #e2e8f0;
        vertical-align: middle;
        font-size: 6.5px;
        word-wrap: break-word;
        overflow-wrap: break-word;
        line-height: 1.2;
    }
    tbody td.center { text-align: center; }
    tbody td.right { text-align: right; }
    tbody tr:nth-child(even) {
        background: #fafbfc;
    }
    .status-badge {
        display: inline-block;
        padding: 0px 4px;
        border-radius: 5px;
        font-size: 5.5px;
        font-weight: bold;
        white-space: nowrap;
    }
    .valor-positivo { color: #2ecc71; font-weight: 700; }
    tfoot td {
        padding: 4px 4px;
        font-weight: 800;
        font-size: 8px;
        border-top: 2px solid #1a2332;
        background: #f8fafc;
    }
    tfoot .total-geral {
        color: #c0392b;
        font-size: 10px;
    }

    /* ===== RODAPÉ ===== */
    .rodape {
        margin-top: 8px;
        padding-top: 5px;
        border-top: 1px solid #1a2332;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
        font-size: 6px;
        color: #666;
    }
    .rodape .assinatura {
        text-align: center;
        min-width: 120px;
    }
    .rodape .assinatura .linha {
        width: 110px;
        border-top: 1px solid #333;
        margin: 15px auto 2px;
    }
    .rodape .info-doc {
        text-align: right;
        font-size: 6px;
        color: #888;
    }

    /* ===== BOTÕES ===== */
    .no-print {
        text-align: center;
        padding: 15px;
        max-width: 210mm;
        margin: 0 auto;
    }
    .btn {
        padding: 12px 35px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        margin: 0 5px;
        transition: all 0.3s;
    }
    .btn-print { background: #1a2332; color: #fff; }
    .btn-print:hover { background: #2d3748; }
    .btn-close { background: #e74c3c; color: #fff; }
    .btn-close:hover { background: #c0392b; }

    /* ===== IMPRESSÃO ===== */
    @media print {
        body { background: #fff !important; }
        .page { padding: 0 !important; max-width: 100% !important; }
        .no-print { display: none !important; }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>
<script>
    function imprimir() {
        setTimeout(function() { window.print(); }, 500);
    }
    window.onload = function() {
        if (window.location.search.indexOf("print=1") > -1) {
            imprimir();
        }
    };
</script>
</head>
<body>
<div class="page">

    <!-- ===== CABEÇALHO DA EMPRESA ===== -->
    <div class="header-empresa">
        <div class="logo-area">
            <?php if (!empty($dados_empresa['logo']) && file_exists('../../../../' . $dados_empresa['logo'])): ?>
                <img src="../../../../<?= htmlspecialchars($dados_empresa['logo']) ?>" alt="Logo" class="logo-img">
            <?php else: ?>
                <div class="logo-icon">🎓</div>
            <?php endif; ?>
            <div class="empresa-info">
                <h1><?= htmlspecialchars($dados_empresa['nome'], ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if (!empty($dados_empresa['endereco'])): ?>
                    <div class="endereco">📍 <?= htmlspecialchars($dados_empresa['endereco'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <div class="contactos">
                    <?php if (!empty($dados_empresa['telefone'])): ?>
                        📞 <?= htmlspecialchars($dados_empresa['telefone'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                    <?php if (!empty($dados_empresa['celular'])): ?>
                        | 📱 <?= htmlspecialchars($dados_empresa['celular'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                    <?php if (!empty($dados_empresa['email'])): ?>
                        | ✉️ <?= htmlspecialchars($dados_empresa['email'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="doc-info">
            <h2>RELATÓRIO DE<br>PAGAMENTOS</h2>
            <?php if (!empty($dados_empresa['nif'])): ?>
                <div class="nif-box">NIF: <?= htmlspecialchars($dados_empresa['nif'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="data-emissao">Emitido: <?= date('d/m/Y H:i') ?></div>
        </div>
    </div>

    <!-- ===== FILTROS APLICADOS ===== -->
    <div class="filtros-aplicados">
        <strong>Filtros:</strong> <?= htmlspecialchars(implode(' | ', $filtros_aplicados), ENT_QUOTES, 'UTF-8') ?>
    </div>

    <!-- ===== RESUMO ===== -->
    <div class="resumo">
        <div class="card total">
            <div class="label">Registos</div>
            <div class="valor"><?= number_format(count($pagamentos)) ?></div>
        </div>
        <div class="card">
            <div class="label">Valor Total</div>
            <div class="valor"><?= formatarMoedaImpressao($total_geral) ?></div>
        </div>
        <div class="card confirmados">
            <div class="label">Confirmados</div>
            <div class="valor verde"><?= $total_confirmados ?> (<?= formatarMoedaImpressao($total_valor_confirmado) ?>)</div>
        </div>
        <div class="card pendentes">
            <div class="label">Pendentes</div>
            <div class="valor laranja"><?= $total_pendentes ?> (<?= formatarMoedaImpressao($total_valor_pendente) ?>)</div>
        </div>
    </div>

    <!-- ===== TABELA ===== -->
    <table>
        <colgroup>
            <col style="width: 3%;">   <!-- # -->
            <col style="width: 20%;">  <!-- Aluno -->
            <col style="width: 6%;">   <!-- Classe -->
            <col style="width: 6%;">   <!-- Turma -->
            <col style="width: 18%;">  <!-- Emolumento -->
            <col style="width: 12%;">  <!-- Valor -->
            <col style="width: 9%;">   <!-- Data -->
            <col style="width: 10%;">  <!-- Forma -->
            <col style="width: 8%;">   <!-- Status -->
            <col style="width: 8%;">   <!-- Mês Ref. -->
        </colgroup>
        <thead>
            <tr>
                <th class="center">#</th>
                <th>Aluno</th>
                <th class="center">Cl.</th>
                <th class="center">Tur.</th>
                <th>Emolumento</th>
                <th class="right">Valor</th>
                <th class="center">Data</th>
                <th class="center">Forma</th>
                <th class="center">Status</th>
                <th class="center">Mês</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($pagamentos) > 0): ?>
                <?php $i = 1; foreach($pagamentos as $p): ?>
                <tr>
                    <td class="center"><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($p['aluno_nome'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td class="center"><?= htmlspecialchars($p['aluno_classe'] ?? '-', ENT_QUOTES, 'UTF-8') ?>ª</td>
                    <td class="center"><?= htmlspecialchars($p['aluno_turma'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($p['emolumento_nome'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="right valor-positivo"><?= formatarMoedaImpressao($p['valor']) ?></td>
                    <td class="center"><?= date('d/m/Y', strtotime($p['data_pagamento'])) ?></td>
                    <td class="center"><?= ucfirst(htmlspecialchars($p['forma_pagamento'] ?? '-', ENT_QUOTES, 'UTF-8')) ?></td>
                    <td class="center">
                        <span class="status-badge" style="background:<?= getStatusBgImpressao($p['status'] ?? '') ?>;color:<?= getStatusColorImpressao($p['status'] ?? '') ?>;">
                            <?= getStatusLabelImpressao($p['status'] ?? '') ?>
                        </span>
                    </td>
                    <td class="center"><?= htmlspecialchars($p['mes_referencia'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" class="center" style="padding:15px;color:#94a3b8;">
                        Nenhum pagamento encontrado com os filtros aplicados.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <?php if (count($pagamentos) > 0): ?>
        <tfoot>
            <tr>
                <td colspan="5" class="right">TOTAL GERAL:</td>
                <td class="right total-geral"><?= formatarMoedaImpressao($total_geral) ?></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <!-- ===== RODAPÉ ===== -->
    <div class="rodape">
        <div>
            <div><strong><?= htmlspecialchars($dados_empresa['nome'], ENT_QUOTES, 'UTF-8') ?></strong></div>
            <?php if (!empty($dados_empresa['nif'])): ?>
                <div>NIF: <?= htmlspecialchars($dados_empresa['nif'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div>Gerado eletronicamente em <?= date('d/m/Y \à\s H:i:s') ?></div>
        </div>
        <div class="assinatura">
            <div class="linha"></div>
            <div>Assinatura do Responsável</div>
        </div>
        <div class="info-doc">
            <div>Documento gerado pelo Sistema Escolar</div>
            <div>Página 1</div>
        </div>
    </div>

</div>

<!-- ===== BOTÕES ===== -->
<div class="no-print">
    <button onclick="imprimir()" class="btn btn-print">🖨️ IMPRIMIR</button>
    <button onclick="window.close()" class="btn btn-close">✕ FECHAR</button>
</div>

</body>
</html>