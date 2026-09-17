<?php
// ============================================
// modules/escola/alunos/gerar_lista_transporte.php - Gerar Lista HTML de Transporte
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
$debug_info = [];

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
    // 4. BUSCAR PAGAMENTOS PRIMEIRO
    // ============================================
    $sql_pagamentos = "
        SELECT 
            p.aluno_id AS pagamento_aluno_id,
            p.nome_aluno,
            p.emolumento_id,
            p.valor,
            p.data_pagamento,
            p.forma_pagamento,
            p.status,
            p.mes_referencia
        FROM pagamentos p
        WHERE p.emolumento_id IN (" . implode(',', $emolumento_transporte_ids) . ")
    ";
    
    $where_pag = [];
    $params_pag = [];
    
    if (!empty($mes_referencia) && $mes_referencia != 'todos') {
        $where_pag[] = "p.mes_referencia = :mes_referencia";
        $params_pag[':mes_referencia'] = $mes_referencia;
    }
    
    if (!empty($status_pagamento)) {
        $where_pag[] = "p.status = :status_pagamento";
        $params_pag[':status_pagamento'] = $status_pagamento;
    }
    
    if (!empty($forma_pagamento)) {
        $where_pag[] = "p.forma_pagamento = :forma_pagamento";
        $params_pag[':forma_pagamento'] = $forma_pagamento;
    }
    
    if (!empty($data_inicio)) {
        $where_pag[] = "p.data_pagamento >= :data_inicio";
        $params_pag[':data_inicio'] = $data_inicio;
    }
    if (!empty($data_fim)) {
        $where_pag[] = "p.data_pagamento <= :data_fim";
        $params_pag[':data_fim'] = $data_fim;
    }
    
    if (!empty($where_pag)) {
        $sql_pagamentos .= " AND " . implode(' AND ', $where_pag);
    }
    
    $sql_pagamentos .= " ORDER BY p.data_pagamento DESC";
    
    $debug_info['sql_pagamentos'] = $sql_pagamentos;
    
    $stmt = $pdo->prepare($sql_pagamentos);
    foreach ($params_pag as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $pagamentos = $stmt->fetchAll();
    
    $debug_info['total_pagamentos'] = count($pagamentos);
    $debug_info['primeiro_pagamento'] = !empty($pagamentos) ? $pagamentos[0] : null;
    
    // ============================================
    // 5. PARA CADA PAGAMENTO, BUSCAR O ALUNO
    // ============================================
    $alunos_temp = [];
    $alunos_ids_processados = [];
    
    foreach ($pagamentos as $pag) {
        $nome_pagamento = trim($pag['nome_aluno'] ?? '');
        $pagamento_aluno_id = $pag['pagamento_aluno_id'];
        
        if (in_array($pagamento_aluno_id, $alunos_ids_processados)) {
            continue;
        }
        $alunos_ids_processados[] = $pagamento_aluno_id;
        
        $aluno = null;
        
        // 1. Buscar pelo ID
        $sql_aluno = "
            SELECT 
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
            WHERE a.id = :aluno_id
        ";
        
        $stmt = $pdo->prepare($sql_aluno);
        $stmt->bindValue(':aluno_id', $pagamento_aluno_id);
        $stmt->execute();
        $aluno = $stmt->fetch();
        
        // 2. Buscar pelo NOME (LIKE)
        if (!$aluno && !empty($nome_pagamento)) {
            $sql_aluno_nome = "
                SELECT 
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
                WHERE a.nome LIKE :nome
                LIMIT 1
            ";
            
            $stmt = $pdo->prepare($sql_aluno_nome);
            $stmt->bindValue(':nome', '%' . $nome_pagamento . '%');
            $stmt->execute();
            $aluno = $stmt->fetch();
        }
        
        // 3. Buscar pelo NOME EXATO
        if (!$aluno && !empty($nome_pagamento)) {
            $sql_aluno_nome_exato = "
                SELECT 
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
                WHERE a.nome = :nome
                LIMIT 1
            ";
            
            $stmt = $pdo->prepare($sql_aluno_nome_exato);
            $stmt->bindValue(':nome', $nome_pagamento);
            $stmt->execute();
            $aluno = $stmt->fetch();
        }
        
        if ($aluno) {
            // Verificar status
            $status_ok = ($aluno['Situacao_Cadastro'] == 'Matrícula' || $aluno['Situacao_Cadastro'] == 'Confirmação');
            
            // Aplicar filtros
            $classe_match = empty($classe) || $aluno['Classe'] == $classe;
            $turma_match = empty($turma) || $aluno['turma'] == $turma;
            $periodo_match = empty($periodo) || strtolower($aluno['Periodo']) == strtolower($periodo);
            $nome_match = empty($busca_nome) || stripos($aluno['nome'], $busca_nome) !== false;
            
            if ($status_ok && $classe_match && $turma_match && $periodo_match && $nome_match) {
                $alunos_temp[] = $aluno;
            }
        }
    }
    
    // Remover duplicados
    $ids_vistos = [];
    foreach ($alunos_temp as $aluno) {
        if (!in_array($aluno['aluno_id'], $ids_vistos)) {
            $ids_vistos[] = $aluno['aluno_id'];
            $alunos_lista[] = $aluno;
        }
    }
    
    // Ordenar por nome
    usort($alunos_lista, function($a, $b) {
        return strcmp($a['nome'], $b['nome']);
    });
    
    $total_alunos = count($alunos_lista);
    
    $debug_info['alunos_temp'] = count($alunos_temp);
    $debug_info['alunos_final'] = $total_alunos;
    
} catch (Exception $e) {
    error_log("Erro ao buscar alunos: " . $e->getMessage());
    $debug_info['erro'] = $e->getMessage();
    $alunos_lista = [];
}

// ============================================
// 6. HEADER
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
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f6fa;
            padding: 20px;
            color: #1a2332;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 3px double #c9a84c;
            margin-bottom: 25px;
        }
        .header .escola-nome {
            font-size: 28px;
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
            font-size: 22px;
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
            padding: 8px 20px;
            display: inline-block;
            border-radius: 8px;
            border: 2px solid #c9a84c;
        }
        .filtros-info {
            background: #f8fafc;
            padding: 12px 18px;
            border-radius: 8px;
            border-left: 4px solid #c9a84c;
            margin-bottom: 25px;
            font-size: 13px;
            color: #4a5568;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filtros-info .item { display: inline-block; }
        .filtros-info .item strong { color: #1a2332; }
        .total-alunos {
            text-align: right;
            font-size: 14px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 15px;
            padding: 8px 15px;
            background: #f8fafc;
            border-radius: 6px;
        }
        .total-alunos span { color: #c9a84c; font-size: 18px; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        table thead {
            background: #1a2332;
            color: #fff;
        }
        table thead th {
            padding: 12px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table tbody tr {
            border-bottom: 1px solid #eef2f7;
        }
        table tbody tr:nth-child(even) {
            background: #fafaf8;
        }
        table tbody td {
            padding: 10px 12px;
            vertical-align: middle;
        }
        .badge-sexo {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            min-width: 25px;
            text-align: center;
        }
        .badge-m { background: #dbeafe; color: #1e40af; }
        .badge-f { background: #fce7f3; color: #9d174d; }
        .col-num { width: 45px; text-align: center; }
        .col-nome { min-width: 200px; }
        .col-sexo { width: 70px; text-align: center; }
        .col-nasc { width: 120px; text-align: center; }
        .col-idade { width: 60px; text-align: center; }
        .col-classe { width: 80px; text-align: center; }
        .col-turma { width: 80px; text-align: center; }
        .col-periodo { width: 90px; text-align: center; }
        .footer {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            font-size: 12px;
            color: #94a3b8;
        }
        .footer .total strong { color: #c9a84c; }
        .acoes {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn {
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        .btn-print { background: #c9a84c; color: #1a2332; }
        .btn-print:hover { background: #b8973a; transform: translateY(-2px); }
        .btn-back { background: #f1f5f9; color: #4a5568; }
        .btn-back:hover { background: #e2e8f0; }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state .icon { font-size: 56px; display: block; margin-bottom: 15px; }
        .empty-state h3 { font-size: 20px; color: #4a5568; margin: 0 0 5px; }
        .debug-box {
            margin-top: 15px;
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            text-align: left;
            font-size: 12px;
        }
        .debug-box strong { color: #856404; }
        .no-print { display: block; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .container { box-shadow: none; padding: 20px; border-radius: 0; }
            .acoes { display: none !important; }
            .no-print { display: none !important; }
            .btn { display: none !important; }
            .debug-box { display: none !important; }
            .badge-m { background: #dbeafe !important; color: #1e40af !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .badge-f { background: #fce7f3 !important; color: #9d174d !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            table thead { background: #1a2332 !important; color: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            table tbody tr:nth-child(even) { background: #fafaf8 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .header { border-bottom: 3px double #c9a84c !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .filtros-info { background: #f8fafc !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .header .turma-destaque { background: #f8fafc !important; border: 2px solid #c9a84c !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
        
        @media screen and (max-width: 768px) {
            .container { padding: 15px; }
            .header .escola-nome { font-size: 20px; }
            .filtros-info { flex-direction: column; gap: 5px; }
            table { font-size: 12px; }
            table thead th, table tbody td { padding: 6px 8px; }
            .col-nasc { display: none; }
            .col-idade { display: none; }
            .col-periodo { width: 60px; }
        }
    </style>
</head>
<body>

<div class="container">
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
        <div class="subtitulo"><?= 'Gerado em: ' . date('d/m/Y H:i') ?></div>
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

    <!-- TOTAL -->
    <div class="total-alunos">
        Total de Alunos: <span><?= $total_alunos ?></span>
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
            
            <!-- DEBUG -->
            <div class="debug-box no-print">
                <p><strong>🔍 Informações de Debug:</strong></p>
                <p><strong>Total de pagamentos encontrados:</strong> <?= $debug_info['total_pagamentos'] ?? 0 ?></p>
                <p><strong>Alunos encontrados (temp):</strong> <?= $debug_info['alunos_temp'] ?? 0 ?></p>
                <p><strong>Alunos encontrados (final):</strong> <?= $debug_info['alunos_final'] ?? 0 ?></p>
                <?php if (!empty($debug_info['primeiro_pagamento'])): ?>
                    <p><strong>Primeiro pagamento:</strong> ID=<?= $debug_info['primeiro_pagamento']['pagamento_aluno_id'] ?? 'N/A' ?>, Nome=<?= $debug_info['primeiro_pagamento']['nome_aluno'] ?? 'N/A' ?></p>
                <?php endif; ?>
                <?php if (!empty($debug_info['erro'])): ?>
                    <p><strong>Erro:</strong> <?= htmlspecialchars($debug_info['erro']) ?></p>
                <?php endif; ?>
                <p style="margin-top:5px;font-size:10px;color:#856404;">
                    <strong>SQL:</strong> <?= htmlspecialchars($debug_info['sql_pagamentos'] ?? 'N/A') ?>
                </p>
                <p style="margin-top:5px;color:#856404;">
                    💡 <strong>Dica:</strong> Verifique se os alunos têm status "Matrícula" ou "Confirmação"
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- RODAPÉ -->
    <div class="footer">
        <div class="total"><strong>Total de Alunos: <?= $total_alunos ?></strong></div>
        <div><?= date('d/m/Y H:i') ?></div>
    </div>

    <!-- AÇÕES -->
    <div class="acoes no-print">
        <button onclick="window.print()" class="btn btn-print">🖨️ Imprimir</button>
        <button onclick="window.close()" class="btn btn-back">✕ Fechar</button>
    </div>
</div>

</body>
</html>