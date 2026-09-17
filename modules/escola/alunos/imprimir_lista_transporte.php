<?php
// ============================================
// modules/escola/alunos/imprimir_lista_transporte.php - Impressão Lista Transporte
// ============================================

require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para VISUALIZAR
bloquearAcesso('visualizar');

// ============================================
// 1. RECEBER PARÂMETROS
// ============================================
$classe = $_GET['classe'] ?? '';
$turma = $_GET['turma'] ?? '';
$periodo = $_GET['periodo'] ?? '';
$mes_referencia = $_GET['mes_referencia'] ?? '';
$status_pagamento = $_GET['status_pagamento'] ?? '';
$forma_pagamento = $_GET['forma_pagamento'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$busca_nome = $_GET['busca_nome'] ?? '';

// ============================================
// 2. INICIALIZAR
// ============================================
$pdo = null;
$alunos_lista = [];
$total_alunos = 0;
$info_escola = [];

try {
    $pdo = conectarBanco();
    
    // Buscar informações da escola
    $stmt = $pdo->query("SELECT * FROM config_escola LIMIT 1");
    $info_escola = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$info_escola) {
        $info_escola = [
            'nome_escola' => 'COMPLEXO ESCOLAR CASTELO REIS',
            'endereco' => 'Luanda, Angola',
            'telefone' => '+244 999 999 999',
            'email' => 'info@casteloreis.ao'
        ];
    }
    
    // ============================================
    // 3. IDs DOS EMOLUMENTOS DE TRANSPORTE
    // ============================================
    $emolumento_transporte_ids = [19, 56, 66, 102, 104, 114];
    
    // ============================================
    // 4. CONSULTA
    // ============================================
    $where_conditions = [];
    $params = [];
    
    if (!empty($classe)) {
        $where_conditions[] = "a.Classe = :classe";
        $params[':classe'] = $classe;
    }
    
    if (!empty($turma)) {
        $where_conditions[] = "a.TURMA = :turma";
        $params[':turma'] = $turma;
    }
    
    if (!empty($periodo)) {
        $where_conditions[] = "LOWER(a.Periodo) = LOWER(:periodo)";
        $params[':periodo'] = $periodo;
    }
    
    if (!empty($mes_referencia) && $mes_referencia != 'todos') {
        $where_conditions[] = "p.mes_referencia = :mes_referencia";
        $params[':mes_referencia'] = $mes_referencia;
    }
    
    if (!empty($status_pagamento)) {
        $where_conditions[] = "p.status = :status_pagamento";
        $params[':status_pagamento'] = $status_pagamento;
    }
    
    if (!empty($forma_pagamento)) {
        $where_conditions[] = "p.forma_pagamento = :forma_pagamento";
        $params[':forma_pagamento'] = $forma_pagamento;
    }
    
    if (!empty($data_inicio)) {
        $where_conditions[] = "p.data_pagamento >= :data_inicio";
        $params[':data_inicio'] = $data_inicio;
    }
    if (!empty($data_fim)) {
        $where_conditions[] = "p.data_pagamento <= :data_fim";
        $params[':data_fim'] = $data_fim;
    }
    
    if (!empty($busca_nome)) {
        $where_conditions[] = "a.nome LIKE :busca_nome";
        $params[':busca_nome'] = '%' . $busca_nome . '%';
    }
    
    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT DISTINCT
            a.id AS aluno_id,
            a.nome,
            a.Sexo,
            a.dia,
            a.mes,
            a.Ano,
            a.Classe,
            a.TURMA AS turma,
            a.SALA AS sala,
            a.Periodo,
            a.Situacao_Cadastro,
            YEAR(CURRENT_DATE) - a.Ano - (DATE_FORMAT(CURRENT_DATE, '%m%d') < CONCAT(a.mes, LPAD(a.dia, 2, '0'))) AS idade_atual
        FROM alunos a
        INNER JOIN pagamentos p ON a.id = p.aluno_id
        WHERE p.emolumento_id IN (" . implode(',', $emolumento_transporte_ids) . ")
        AND (a.Situacao_Cadastro = 'Matrícula' OR a.Situacao_Cadastro = 'Confirmação')
        $where_sql
        ORDER BY a.nome
    ";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $alunos_lista = $stmt->fetchAll();
    $total_alunos = count($alunos_lista);
    
} catch (Exception $e) {
    error_log("Erro ao buscar alunos: " . $e->getMessage());
    $alunos_lista = [];
}

// ============================================
// 5. HEADER PARA IMPRESSÃO A4
// ============================================
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Alunos - Transporte</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #fff;
            color: #1a2332;
            padding: 20px;
        }
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 20mm 15mm;
        }
        .header {
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 3px double #c9a84c;
            margin-bottom: 20px;
        }
        .header .escola-nome {
            font-size: 26px;
            font-weight: 700;
            color: #1a2332;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .header .escola-sub {
            font-size: 14px;
            color: #4a5568;
            margin-top: 2px;
        }
        .header .escola-info {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }
        .header .titulo-relatorio {
            font-size: 20px;
            font-weight: 700;
            color: #c9a84c;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header .subtitulo {
            font-size: 14px;
            color: #4a5568;
            margin-top: 3px;
        }
        .header .turma-destaque {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
            margin-top: 8px;
            background: #f8fafc;
            padding: 5px 15px;
            display: inline-block;
            border-radius: 6px;
            border: 1px solid #c9a84c;
        }
        .filtros-info {
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 6px;
            border-left: 4px solid #c9a84c;
            margin-bottom: 20px;
            font-size: 12px;
            color: #4a5568;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filtros-info .item { display: inline-block; }
        .filtros-info .item strong { color: #1a2332; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        table thead {
            background: #1a2332;
            color: #fff;
        }
        table thead th {
            padding: 10px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #1a2332;
        }
        table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }
        table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        table tbody td {
            padding: 8px 10px;
            vertical-align: middle;
        }
        .badge-sexo {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }
        .badge-m { background: #dbeafe; color: #1e40af; }
        .badge-f { background: #fce7f3; color: #9d174d; }
        .col-num { width: 35px; text-align: center; }
        .col-nome { min-width: 200px; }
        .col-sexo { width: 50px; text-align: center; }
        .col-nasc { width: 100px; text-align: center; }
        .col-idade { width: 50px; text-align: center; }
        .col-classe { width: 60px; text-align: center; }
        .col-turma { width: 60px; text-align: center; }
        .col-periodo { width: 70px; text-align: center; }
        .footer {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            font-size: 11px;
            color: #94a3b8;
        }
        .footer .total {
            font-size: 14px;
            font-weight: 700;
            color: #1a2332;
        }
        .footer .total span { color: #c9a84c; }
        .assinatura-area {
            margin-top: 30px;
            display: flex;
            justify-content: space-around;
            padding-top: 20px;
            border-top: 1px dashed #c9a84c;
        }
        .assinatura-box {
            text-align: center;
            min-width: 180px;
        }
        .assinatura-box .linha {
            border-top: 1px solid #1a2332;
            width: 180px;
            margin: 40px auto 5px;
        }
        .assinatura-box .cargo { font-size: 11px; color: #4a5568; }
        .page-footer {
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #eef2f7;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .empty-state .icon { font-size: 48px; display: block; margin-bottom: 15px; }
        .empty-state h3 { font-size: 18px; color: #4a5568; margin: 0 0 5px; }
        .no-print { display: block; }
        
        @media print {
            body { padding: 0; margin: 0; }
            .no-print { display: none !important; }
            .badge-m { background: #dbeafe !important; color: #1e40af !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .badge-f { background: #fce7f3 !important; color: #9d174d !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            table thead { background: #1a2332 !important; color: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            table tbody tr:nth-child(even) { background: #f8fafc !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .header { border-bottom: 3px double #c9a84c !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .filtros-info { background: #f8fafc !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .header .turma-destaque { background: #f8fafc !important; border: 1px solid #c9a84c !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .assinatura-box .linha { border-top: 1px solid #1a2332 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

<!-- BOTÕES DE IMPRESSÃO (NÃO APARECEM NA IMPRESSÃO) -->
<div class="no-print" style="text-align:center;margin-bottom:20px;padding:15px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
    <button onclick="window.print()" style="padding:12px 30px;background:#c9a84c;color:#1a2332;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;margin-right:10px;">🖨️ Imprimir</button>
    <button onclick="window.close()" style="padding:12px 30px;background:#e74c3c;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;">✕ Fechar</button>
</div>

<!-- CABEÇALHO -->
<div class="header">
    <div class="escola-nome"><?= htmlspecialchars($info_escola['nome_escola'] ?? 'COMPLEXO ESCOLAR CASTELO REIS') ?></div>
    <div class="escola-sub">Módulo Acadêmico - Gestão Escolar</div>
    <div class="escola-info">
        <?= htmlspecialchars($info_escola['endereco'] ?? 'Luanda, Angola') ?> 
        | Tel: <?= htmlspecialchars($info_escola['telefone'] ?? '+244 999 999 999') ?>
        | Email: <?= htmlspecialchars($info_escola['email'] ?? 'info@casteloreis.ao') ?>
    </div>
    <div class="titulo-relatorio">📋 Lista Nominal - Alunos com Pagamentos de Transporte</div>
    <div class="subtitulo"><?= 'Emitido em: ' . date('d/m/Y H:i') ?></div>
    <?php if (!empty($turma) || !empty($classe)): ?>
    <div class="turma-destaque">
        <?php 
        $texto = '';
        if (!empty($classe)) $texto .= 'Classe: ' . htmlspecialchars($classe);
        if (!empty($turma)) {
            if (!empty($texto)) $texto .= ' | ';
            $texto .= 'Turma: ' . htmlspecialchars($turma);
        }
        echo $texto;
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- FILTROS -->
<div class="filtros-info">
    <span class="item"><strong>Classe:</strong> <?= htmlspecialchars($classe ?: 'Todas') ?></span>
    <span class="item"><strong>Turma:</strong> <?= htmlspecialchars($turma ?: 'Todas') ?></span>
    <span class="item"><strong>Período:</strong> <?= htmlspecialchars($periodo ?: 'Todos') ?></span>
    <span class="item"><strong>Mês:</strong> <?= htmlspecialchars($mes_referencia == 'todos' ? 'Todos os meses' : ($mes_referencia ?: 'Não definido')) ?></span>
</div>

<!-- TABELA -->
<?php if ($total_alunos > 0): ?>
<table>
    <thead>
        <tr>
            <th class="col-num">#</th>
            <th class="col-nome">Nome do Aluno</th>
            <th class="col-sexo">Sexo</th>
            <th class="col-nasc">Data Nasc.</th>
            <th class="col-idade">Idade</th>
            <th class="col-classe">Classe</th>
            <th class="col-turma">Turma</th>
            <th class="col-periodo">Período</th>
        </tr>
    </thead>
    <tbody>
        <?php $num = 1; ?>
        <?php foreach ($alunos_lista as $aluno): 
            $data_nasc = '';
            if (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0 && $aluno['dia'] != 0) {
                $data_nasc = sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']);
            } else {
                $data_nasc = '-';
            }
            $idade = $aluno['idade_atual'] ?? '-';
        ?>
        <tr>
            <td class="col-num"><?= $num ?></td>
            <td class="col-nome"><?= htmlspecialchars($aluno['nome']) ?></td>
            <td class="col-sexo">
                <span class="badge-sexo badge-<?= strtolower($aluno['Sexo'] ?? 'm') ?>">
                    <?= $aluno['Sexo'] ?? 'M' ?>
                </span>
            </td>
            <td class="col-nasc"><?= $data_nasc ?></td>
            <td class="col-idade"><?= $idade ?></td>
            <td class="col-classe"><?= htmlspecialchars($aluno['Classe'] ?? '-') ?></td>
            <td class="col-turma"><?= htmlspecialchars($aluno['turma'] ?? '-') ?></td>
            <td class="col-periodo"><?= htmlspecialchars($aluno['Periodo'] ?? '-') ?></td>
        </tr>
        <?php $num++; endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<div class="empty-state">
    <span class="icon">📭</span>
    <h3>Nenhum aluno encontrado</h3>
    <p>Não há alunos com pagamentos de transporte para os critérios informados.</p>
</div>
<?php endif; ?>

<!-- RODAPÉ -->
<div class="footer">
    <div class="total">Total de Alunos: <span><?= $total_alunos ?></span></div>
    <div>Página <span id="pagina-atual"></span> / <span id="total-paginas"></span></div>
</div>

<!-- ASSINATURAS -->
<div class="assinatura-area">
    <div class="assinatura-box">
        <div class="linha"></div>
        <div class="cargo">Secretário(a) Académico(a)</div>
    </div>
    <div class="assinatura-box">
        <div class="linha"></div>
        <div class="cargo">Director(a) Pedagógico(a)</div>
    </div>
    <div class="assinatura-box">
        <div class="linha"></div>
        <div class="cargo">Director(a) Geral</div>
    </div>
</div>

<!-- RODAPÉ DA PÁGINA -->
<div class="page-footer">
    <span>Documento gerado pelo Sistema de Gestão Escolar - <?= date('Y') ?></span>
    <span style="margin:0 10px;">|</span>
    <span>Lista de Alunos com Pagamentos de Transporte</span>
    <span style="margin:0 10px;">|</span>
    <span>Versão 1.0</span>
</div>

</body>
</html>