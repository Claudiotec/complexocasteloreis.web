<?php
// ============================================
// modules/escola/financeiro/relatorios/relatorio_agt.php
// Relatório Financeiro - Estilo AGT (Documento Oficial)
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
if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== DADOS PARA RELATÓRIO =====
$anoAtual = date('Y');
$mesAtual = date('m');
$dataAtual = date('d/m/Y');
$horaAtual = date('H:i:s');

// Dados da escola
$nomeEscola = $_SESSION['escola_nome'] ?? 'Escola Modelo';
$enderecoEscola = $_SESSION['escola_endereco'] ?? 'Luanda, Angola';
$nifEscola = $_SESSION['escola_nif'] ?? '1234567890';
$telefoneEscola = $_SESSION['escola_telefone'] ?? '(+244) 900 000 000';

// ===== BUSCAR TODOS OS DADOS DO BANCO =====
try {
    // ===== 1. BUSCAR TODOS OS PAGAMENTOS =====
    $todosPagamentos = [];
    $totalRecebido = 0;
    $pagamentosMes = 0;
    $totalPendente = 0;
    $totalAtrasados = 0;
    $totalMensalidades = 0;
    
    // Verificar se a tabela pagamentos existe
    $tabelaPagamentos = false;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'pagamentos'");
        if ($check->rowCount() > 0) {
            $tabelaPagamentos = true;
        }
    } catch (Exception $e) {}
    
    if ($tabelaPagamentos) {
        // Verificar colunas disponíveis
        $colunasPagamentos = [];
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM pagamentos");
            while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
                $colunasPagamentos[] = $col['Field'];
            }
        } catch (Exception $e) {}
        
        // BUSCAR TODOS OS PAGAMENTOS COM JOINS
        $sql = "SELECT p.*";
        
        // Aluno
        if (in_array('aluno_id', $colunasPagamentos)) {
            $sql .= ", a.nome as aluno_nome, a.TURMA as aluno_turma";
        }
        
        // Emolumento
        if (in_array('emolumento_id', $colunasPagamentos)) {
            $sql .= ", e.nome as emolumento_nome, e.descricao as emolumento_descricao";
        }
        
        $sql .= " FROM pagamentos p";
        
        if (in_array('aluno_id', $colunasPagamentos)) {
            $sql .= " LEFT JOIN alunos a ON p.aluno_id = a.id";
        }
        if (in_array('emolumento_id', $colunasPagamentos)) {
            $sql .= " LEFT JOIN emolumentos e ON p.emolumento_id = e.id";
        }
        
        // Ordenar por data (mais recentes primeiro)
        $orderCol = in_array('data_pagamento', $colunasPagamentos) ? 'data_pagamento' : 'created_at';
        $orderCol = in_array($orderCol, $colunasPagamentos) ? $orderCol : 'id';
        
        $sql .= " ORDER BY p.$orderCol DESC";
        
        $todosPagamentos = $pdo->query($sql)->fetchAll();
        
        // Calcular totais de TODOS os pagamentos
        $totalRecebido = 0;
        $pagamentosMes = 0;
        
        foreach ($todosPagamentos as $pag) {
            $valor = floatval($pag['valor'] ?? 0);
            $status = $pag['status'] ?? 'pendente';
            
            if ($status == 'confirmado' || $status == 'pago') {
                $totalRecebido += $valor;
                
                // Verificar se é do mês atual
                $dataPag = $pag['data_pagamento'] ?? $pag['created_at'] ?? null;
                if ($dataPag) {
                    $mesPag = date('m', strtotime($dataPag));
                    $anoPag = date('Y', strtotime($dataPag));
                    if ($mesPag == $mesAtual && $anoPag == $anoAtual) {
                        $pagamentosMes += $valor;
                    }
                }
            }
        }
    }
    
    // ===== 2. BUSCAR TODAS AS MENSALIDADES =====
    $todasMensalidades = [];
    $totalPendente = 0;
    $totalAtrasados = 0;
    $totalMensalidades = 0;
    
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'mensalidades'");
        if ($check->rowCount() > 0) {
            $sql = "SELECT m.*, a.nome as aluno_nome, a.TURMA as aluno_turma 
                    FROM mensalidades m
                    LEFT JOIN alunos a ON m.aluno_id = a.id
                    ORDER BY m.data_vencimento DESC";
            
            $todasMensalidades = $pdo->query($sql)->fetchAll();
            
            foreach ($todasMensalidades as $mens) {
                $valor = floatval($mens['valor'] ?? 0);
                $status = $mens['status'] ?? 'pendente';
                
                if ($status == 'pendente') {
                    $totalPendente += $valor;
                } elseif ($status == 'atrasado') {
                    $totalPendente += $valor;
                    $totalAtrasados++;
                }
                $totalMensalidades++;
            }
        }
    } catch (Exception $e) {}
    
    // ===== 3. BUSCAR TODOS OS ALUNOS =====
    $totalAlunos = 0;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'alunos'");
        if ($check->rowCount() > 0) {
            $colunasAlunos = [];
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM alunos");
                while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
                    $colunasAlunos[] = $col['Field'];
                }
            } catch (Exception $e) {}
            
            $sql = "SELECT COUNT(*) FROM alunos";
            if (in_array('status', $colunasAlunos)) {
                $sql .= " WHERE status = 'ativo' OR status IS NULL";
            }
            $totalAlunos = $pdo->query($sql)->fetchColumn() ?? 0;
        }
    } catch (Exception $e) {}
    
    // ===== 4. BUSCAR EMOLUMENTOS =====
    $totalEmolumentos = 0;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'emolumentos'");
        if ($check->rowCount() > 0) {
            $colunasEmolumentos = [];
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM emolumentos");
                while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
                    $colunasEmolumentos[] = $col['Field'];
                }
            } catch (Exception $e) {}
            
            $sql = "SELECT COUNT(*) FROM emolumentos";
            if (in_array('status', $colunasEmolumentos)) {
                $sql .= " WHERE status = 'ativo' OR status IS NULL";
            }
            $totalEmolumentos = $pdo->query($sql)->fetchColumn() ?? 0;
        }
    } catch (Exception $e) {}
    
    // ===== 5. ARRECADAÇÃO POR MÊS =====
    $arrecadacaoMensal = [];
    if ($tabelaPagamentos) {
        try {
            $sql = "SELECT 
                        DATE_FORMAT(p.data_pagamento, '%Y-%m') as mes_ano,
                        DATE_FORMAT(p.data_pagamento, '%M/%Y') as mes_nome,
                        COUNT(*) as total_pagamentos,
                        SUM(p.valor) as total_arrecadado
                    FROM pagamentos p
                    WHERE p.status IN ('confirmado', 'pago')
                    GROUP BY DATE_FORMAT(p.data_pagamento, '%Y-%m')
                    ORDER BY mes_ano DESC";
            
            $arrecadacaoMensal = $pdo->query($sql)->fetchAll();
            
            if (empty($arrecadacaoMensal)) {
                $sql = "SELECT 
                            DATE_FORMAT(p.data_pagamento, '%Y-%m') as mes_ano,
                            DATE_FORMAT(p.data_pagamento, '%M/%Y') as mes_nome,
                            COUNT(*) as total_pagamentos,
                            SUM(p.valor) as total_arrecadado
                        FROM pagamentos p
                        GROUP BY DATE_FORMAT(p.data_pagamento, '%Y-%m')
                        ORDER BY mes_ano DESC";
                
                $arrecadacaoMensal = $pdo->query($sql)->fetchAll();
            }
        } catch (Exception $e) {
            $arrecadacaoMensal = [];
        }
    }
    
    // ===== 6. RESUMO POR EMOLUMENTO =====
    $resumoEmolumentos = [];
    if ($tabelaPagamentos) {
        try {
            $sql = "SELECT 
                        e.nome as emolumento_nome,
                        e.descricao as emolumento_descricao,
                        COUNT(DISTINCT p.id) as total_pagamentos,
                        COUNT(DISTINCT p.aluno_id) as total_alunos,
                        SUM(CASE WHEN p.status IN ('confirmado', 'pago') THEN p.valor ELSE 0 END) as valor_total
                    FROM emolumentos e
                    LEFT JOIN pagamentos p ON p.emolumento_id = e.id
                    WHERE e.status = 'ativo' OR e.status IS NULL
                    GROUP BY e.id
                    HAVING valor_total > 0
                    ORDER BY valor_total DESC";
            
            $resumoEmolumentos = $pdo->query($sql)->fetchAll();
            
            // Se não houver dados, tentar sem filtro
            if (empty($resumoEmolumentos)) {
                $sql = "SELECT 
                            e.nome as emolumento_nome,
                            e.descricao as emolumento_descricao,
                            COUNT(DISTINCT p.id) as total_pagamentos,
                            COUNT(DISTINCT p.aluno_id) as total_alunos,
                            SUM(p.valor) as valor_total
                        FROM emolumentos e
                        LEFT JOIN pagamentos p ON p.emolumento_id = e.id
                        GROUP BY e.id
                        HAVING valor_total > 0
                        ORDER BY valor_total DESC";
                
                $resumoEmolumentos = $pdo->query($sql)->fetchAll();
            }
        } catch (Exception $e) {
            $resumoEmolumentos = [];
        }
    }
    
} catch (Exception $e) {
    $todosPagamentos = [];
    $todasMensalidades = [];
    $arrecadacaoMensal = [];
    $resumoEmolumentos = [];
    $totalRecebido = 0;
    $totalPendente = 0;
    $pagamentosMes = 0;
    $totalAlunos = 0;
    $totalEmolumentos = 0;
    $totalAtrasados = 0;
    $totalMensalidades = 0;
}

// ===== VARIÁVEIS PARA CONTROLAR EXIBIÇÃO =====
$temPagamentos = count($todosPagamentos) > 0;
$temMensalidades = count($todasMensalidades) > 0;
$temArrecadacaoMensal = count($arrecadacaoMensal) > 0;
$temResumoEmolumentos = count($resumoEmolumentos) > 0;
$temAlunos = $totalAlunos > 0;
$temPendente = $totalPendente > 0;
$temAtrasados = $totalAtrasados > 0;

// ===== INICIALIZAR BUFFER PARA PDF =====
ob_start();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório AGT - <?= htmlspecialchars($nomeEscola) ?></title>
    <style>
        /* ============================================
           ESTILOS PARA RELATÓRIO AGT
           ============================================ */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: #f5f7fa;
            padding: 30px;
            color: #1a2332;
        }
        
        .relatorio-container {
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px 50px;
            border: 1px solid #d1d5db;
            box-shadow: 0 10px 50px rgba(0,0,0,0.08);
        }
        
        /* ===== CABEÇALHO OFICIAL ===== */
        .header-oficial {
            border-bottom: 3px solid #c9a84c;
            padding-bottom: 20px;
            margin-bottom: 25px;
            position: relative;
        }
        
        .header-oficial::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            right: 0;
            height: 3px;
            background: #1a237e;
        }
        
        .header-oficial .topo {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-oficial .brasao {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-oficial .brasao .emblema {
            font-size: 48px;
            line-height: 1;
        }
        
        .header-oficial .brasao .info {
            line-height: 1.2;
        }
        
        .header-oficial .brasao .info .pais {
            font-size: 11px;
            font-weight: 600;
            color: #1a237e;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header-oficial .brasao .info .orgao {
            font-size: 18px;
            font-weight: 800;
            color: #0d1445;
            letter-spacing: -0.5px;
        }
        
        .header-oficial .brasao .info .orgao span {
            color: #c9a84c;
        }
        
        .header-oficial .documento-info {
            text-align: right;
            line-height: 1.4;
        }
        
        .header-oficial .documento-info .titulo-doc {
            font-size: 20px;
            font-weight: 800;
            color: #0d1445;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header-oficial .documento-info .num-doc {
            font-size: 12px;
            color: #4a5568;
            font-weight: 500;
        }
        
        .header-oficial .documento-info .data-doc {
            font-size: 13px;
            color: #4a5568;
        }
        
        /* ===== INFORMAÇÕES DA ESCOLA ===== */
        .info-escola {
            background: #f8fafc;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #c9a84c;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .info-escola .escola-nome {
            font-size: 15px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .info-escola .escola-detalhes {
            font-size: 13px;
            color: #4a5568;
        }
        
        /* ===== RESUMO EXECUTIVO ===== */
        .resumo-executivo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .resumo-item {
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .resumo-item .label {
            font-size: 10px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .resumo-item .value {
            font-size: 22px;
            font-weight: 800;
            color: #1a2332;
            margin-top: 2px;
        }
        
        .resumo-item .value.positivo { color: #2ecc71; }
        .resumo-item .value.negativo { color: #e74c3c; }
        .resumo-item .value.destaque { color: #c9a84c; }
        
        /* ===== SEÇÕES ===== */
        .secao {
            margin-bottom: 25px;
        }
        
        .secao .secao-titulo {
            font-size: 15px;
            font-weight: 700;
            color: #1a2332;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: space-between;
        }
        
        .secao .secao-titulo .contador {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 400;
        }
        
        /* ===== TABELAS ===== */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .table-relatorio {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .table-relatorio thead th {
            background: #f1f5f9;
            padding: 8px 12px;
            text-align: left;
            font-weight: 600;
            color: #1a2332;
            border-bottom: 2px solid #c9a84c;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table-relatorio tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .table-relatorio tbody tr:hover {
            background: #f8fafc;
        }
        
        .table-relatorio tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table-relatorio .text-right {
            text-align: right;
        }
        
        .table-relatorio .text-center {
            text-align: center;
        }
        
        .table-relatorio .status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .table-relatorio .status.confirmado,
        .table-relatorio .status.pago {
            background: #d1fae5;
            color: #065f46;
        }
        
        .table-relatorio .status.pendente {
            background: #fef3c7;
            color: #92400e;
        }
        
        .table-relatorio .status.atrasado {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .table-relatorio .valor {
            font-weight: 600;
        }
        
        .table-relatorio .valor.positivo {
            color: #2ecc71;
        }
        
        .table-relatorio .valor.negativo {
            color: #e74c3c;
        }
        
        .table-total {
            background: #f8fafc;
            font-weight: 700;
            border-top: 2px solid #1a2332;
        }
        
        .table-total td {
            padding: 10px 12px !important;
        }
        
        /* ===== BARRA DE PROGRESSO ===== */
        .barra-progresso {
            width: 100%;
            height: 18px;
            background: #f1f5f9;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .barra-progresso .barra {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, #c9a84c, #f5d76e);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
            font-size: 10px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .barra-progresso .barra.destaque {
            background: linear-gradient(90deg, #2ecc71, #27ae60);
        }
        
        .mes-destaque {
            background: rgba(201, 168, 76, 0.08) !important;
        }
        
        /* ===== RODAPÉ ===== */
        .footer-oficial {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .footer-oficial .assinatura {
            text-align: center;
            padding-top: 25px;
        }
        
        .footer-oficial .assinatura .linha {
            width: 180px;
            border-top: 1px solid #1a2332;
            margin: 0 auto 5px;
        }
        
        .footer-oficial .assinatura .cargo {
            font-size: 11px;
            color: #4a5568;
            font-weight: 500;
        }
        
        .footer-oficial .assinatura .nome {
            font-weight: 600;
            color: #1a2332;
        }
        
        .selo-oficial {
            display: inline-block;
            padding: 4px 12px;
            border: 2px solid #c9a84c;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            color: #c9a84c;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(201, 168, 76, 0.05);
        }
        
        .sem-dados {
            text-align: center;
            padding: 30px 20px;
            color: #94a3b8;
        }
        
        .sem-dados .icon {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
        }
        
        /* ===== IMPRESSÃO ===== */
        @media print {
            body {
                background: #ffffff;
                padding: 15px;
            }
            .relatorio-container {
                border: none;
                box-shadow: none;
                padding: 20px;
                max-width: 100%;
            }
            .no-print { display: none !important; }
            .resumo-item { background: #f8fafc !important; }
            .table-relatorio thead th { background: #f1f5f9 !important; }
            .status.confirmado, .status.pago { background: #d1fae5 !important; }
            .status.pendente { background: #fef3c7 !important; }
            .status.atrasado { background: #fee2e2 !important; }
            .barra-progresso .barra { -webkit-print-color-adjust: exact !important; }
        }
        
        @media (max-width: 768px) {
            body { padding: 15px; }
            .relatorio-container { padding: 20px; }
            .header-oficial .topo { flex-direction: column; }
            .header-oficial .documento-info { text-align: left; }
            .resumo-executivo { grid-template-columns: 1fr 1fr; }
            .info-escola { flex-direction: column; }
            .footer-oficial { flex-direction: column; text-align: center; }
            .secao .secao-titulo { flex-direction: column; align-items: flex-start; gap: 5px; }
        }
        
        @media (max-width: 480px) {
            .resumo-executivo { grid-template-columns: 1fr; }
            .header-oficial .brasao { flex-direction: column; text-align: center; }
            .table-relatorio { font-size: 11px; }
            .table-relatorio thead th,
            .table-relatorio tbody td { padding: 5px 8px; }
        }
    </style>
</head>
<body>

<div class="relatorio-container">
    <!-- ===== CABEÇALHO OFICIAL ===== -->
    <div class="header-oficial">
        <div class="topo">
            <div class="brasao">
                <div class="emblema">🏛️</div>
                <div class="info">
                    <div class="pais">República de Angola</div>
                    <div class="orgao"><span>AGT</span> · Administração Geral Tributária</div>
                </div>
            </div>
            <div class="documento-info">
                <div class="titulo-doc">Relatório Financeiro</div>
                <div class="num-doc">DOC-AGT/<?= date('Y') ?>/<?= str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT) ?></div>
                <div class="data-doc"><?= $dataAtual ?> · <?= $horaAtual ?></div>
            </div>
        </div>
    </div>

    <!-- ===== INFORMAÇÕES DA ESCOLA ===== -->
    <div class="info-escola">
        <div>
            <div class="escola-nome"><?= htmlspecialchars($nomeEscola) ?></div>
            <div class="escola-detalhes">
                📍 <?= htmlspecialchars($enderecoEscola) ?>
                <span style="margin:0 8px;">|</span> 📞 <?= htmlspecialchars($telefoneEscola) ?>
            </div>
        </div>
        <div class="escola-detalhes">
            🔑 NIF: <?= htmlspecialchars($nifEscola) ?>
        </div>
    </div>

    <!-- ===== RESUMO EXECUTIVO ===== -->
    <div class="resumo-executivo">
        <?php if ($totalRecebido > 0): ?>
        <div class="resumo-item">
            <div class="label">Total Arrecadado</div>
            <div class="value positivo">Kz <?= number_format($totalRecebido, 2, ',', '.') ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($pagamentosMes > 0): ?>
        <div class="resumo-item">
            <div class="label">Arrecadado (Mês)</div>
            <div class="value destaque">Kz <?= number_format($pagamentosMes, 2, ',', '.') ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($totalPendente > 0): ?>
        <div class="resumo-item">
            <div class="label">Pendente</div>
            <div class="value negativo">Kz <?= number_format($totalPendente, 2, ',', '.') ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($totalAlunos > 0): ?>
        <div class="resumo-item">
            <div class="label">Alunos Ativos</div>
            <div class="value"><?= number_format($totalAlunos) ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($totalMensalidades > 0): ?>
        <div class="resumo-item">
            <div class="label">Mensalidades</div>
            <div class="value"><?= number_format($totalMensalidades) ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($totalAtrasados > 0): ?>
        <div class="resumo-item">
            <div class="label">Atrasadas</div>
            <div class="value negativo"><?= number_format($totalAtrasados) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== RESUMO POR EMOLUMENTO (SÓ MOSTRA SE TIVER DADOS) ===== -->
    <?php if ($temResumoEmolumentos): ?>
    <div class="secao">
        <div class="secao-titulo">
            <span>📋 Resumo por Emolumento</span>
            <span class="contador"><?= count($resumoEmolumentos) ?> tipos</span>
        </div>
        <div class="table-wrapper">
            <table class="table-relatorio">
                <thead>
                    <tr>
                        <th>Emolumento</th>
                        <th>Descrição</th>
                        <th class="text-center">Pagamentos</th>
                        <th class="text-center">Alunos</th>
                        <th class="text-right">Total (Kz)</th>
                        <th class="text-right">% do Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $somaEmolumentos = 0;
                    $totalGeralEmolumentos = 0;
                    
                    // Primeiro calcular o total geral
                    foreach($resumoEmolumentos as $emol) {
                        $totalGeralEmolumentos += floatval($emol['valor_total'] ?? 0);
                    }
                    
                    foreach($resumoEmolumentos as $emol):
                        $valorTotal = floatval($emol['valor_total'] ?? 0);
                        $somaEmolumentos += $valorTotal;
                        $percentual = $totalGeralEmolumentos > 0 ? ($valorTotal / $totalGeralEmolumentos) * 100 : 0;
                        $nome = $emol['emolumento_nome'] ?? 'Não definido';
                        $descricao = $emol['emolumento_descricao'] ?? '';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($nome) ?></strong></td>
                        <td style="font-size:12px;color:#64748b;"><?= htmlspecialchars($descricao ?: '-') ?></td>
                        <td class="text-center"><?= $emol['total_pagamentos'] ?? 0 ?></td>
                        <td class="text-center"><?= $emol['total_alunos'] ?? 0 ?></td>
                        <td class="text-right valor positivo">Kz <?= number_format($valorTotal, 2, ',', '.') ?></td>
                        <td class="text-right" style="font-weight:600;color:#c9a84c;">
                            <?= number_format($percentual, 1) ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- TOTAL -->
                    <tr class="table-total">
                        <td colspan="4" style="text-align: right; font-size: 14px;">
                            <strong>TOTAL GERAL</strong>
                        </td>
                        <td class="text-right" style="font-size: 15px; color: #1a2332;">
                            <strong>Kz <?= number_format($somaEmolumentos, 2, ',', '.') ?></strong>
                        </td>
                        <td class="text-right">
                            <strong>100%</strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== ARRECADAÇÃO POR MÊS (SÓ MOSTRA SE TIVER DADOS) ===== -->
    <?php if ($temArrecadacaoMensal): ?>
    <div class="secao">
        <div class="secao-titulo">
            <span>📈 Arrecadação por Mês</span>
            <span class="contador">Total: <?= count($arrecadacaoMensal) ?> meses</span>
        </div>
        
        <?php 
            $maiorValor = 0;
            foreach ($arrecadacaoMensal as $mes) {
                $valor = floatval($mes['total_arrecadado'] ?? 0);
                if ($valor > $maiorValor) $maiorValor = $valor;
            }
            $maiorValor = $maiorValor > 0 ? $maiorValor : 1;
            $somaMensal = 0;
            $mesAtualNome = date('F/Y');
        ?>
        <div class="table-wrapper">
            <table class="table-relatorio">
                <thead>
                    <tr>
                        <th>Mês/Ano</th>
                        <th class="text-center">Pagamentos</th>
                        <th class="text-right">Total (Kz)</th>
                        <th style="min-width: 150px;">Progresso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($arrecadacaoMensal as $mes):
                        $valor = floatval($mes['total_arrecadado'] ?? 0);
                        $somaMensal += $valor;
                        $percentual = ($valor / $maiorValor) * 100;
                        $isMesAtual = ($mes['mes_nome'] ?? '') == $mesAtualNome;
                    ?>
                    <tr class="<?= $isMesAtual ? 'mes-destaque' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($mes['mes_nome'] ?? $mes['mes_ano'] ?? 'N/I') ?></strong>
                            <?php if ($isMesAtual): ?>
                                <span style="font-size:10px;color:#c9a84c;font-weight:600;margin-left:8px;">← Atual</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $mes['total_pagamentos'] ?? 0 ?></td>
                        <td class="text-right valor positivo"><?= number_format($valor, 2, ',', '.') ?></td>
                        <td>
                            <div class="barra-progresso">
                                <div class="barra <?= $isMesAtual ? 'destaque' : '' ?>" style="width: <?= max(5, $percentual) ?>%;">
                                    <?= number_format($percentual, 0) ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-total">
                        <td><strong>TOTAL GERAL</strong></td>
                        <td class="text-center"><strong><?= array_sum(array_column($arrecadacaoMensal, 'total_pagamentos')) ?></strong></td>
                        <td class="text-right" style="font-size: 15px;"><strong>Kz <?= number_format($somaMensal, 2, ',', '.') ?></strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== TODOS OS PAGAMENTOS (SÓ MOSTRA SE TIVER DADOS) ===== -->
    <?php if ($temPagamentos): ?>
    <div class="secao">
        <div class="secao-titulo">
            <span>💰 Todos os Pagamentos</span>
            <span class="contador">Total: <?= count($todosPagamentos) ?> registros</span>
        </div>
        <div class="table-wrapper">
            <table class="table-relatorio">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Aluno</th>
                        <th>Turma</th>
                        <th>Emolumento</th>
                        <th class="text-right">Valor (Kz)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $somaTotal = 0;
                    foreach($todosPagamentos as $pag): 
                        $dataPag = isset($pag['data_pagamento']) ? $pag['data_pagamento'] : (isset($pag['created_at']) ? $pag['created_at'] : date('Y-m-d'));
                        $status = isset($pag['status']) ? $pag['status'] : 'pendente';
                        $valor = floatval($pag['valor'] ?? 0);
                        $somaTotal += $valor;
                        
                        $emolumentoNome = $pag['emolumento_nome'] ?? 'Pagamento';
                        $valorClass = 'valor';
                        if ($status == 'confirmado' || $status == 'pago') {
                            $valorClass .= ' positivo';
                        } elseif ($status == 'cancelado') {
                            $valorClass .= ' negativo';
                        }
                    ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($dataPag)) ?></td>
                        <td><strong><?= htmlspecialchars($pag['aluno_nome'] ?? 'N/I') ?></strong></td>
                        <td><?= htmlspecialchars($pag['aluno_turma'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($emolumentoNome) ?></td>
                        <td class="text-right <?= $valorClass ?>"><?= number_format($valor, 2, ',', '.') ?></td>
                        <td>
                            <span class="status <?= $status ?>">
                                <?= ucfirst($status) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-total">
                        <td colspan="4" style="text-align: right;"><strong>TOTAL GERAL</strong></td>
                        <td class="text-right" style="font-size: 15px;"><strong>Kz <?= number_format($somaTotal, 2, ',', '.') ?></strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== MENSAGEM QUANDO NÃO HÁ DADOS ===== -->
    <?php if (!$temPagamentos && !$temMensalidades && !$temArrecadacaoMensal && !$temResumoEmolumentos): ?>
    <div class="sem-dados" style="padding: 50px 20px;">
        <span class="icon">📭</span>
        <h3 style="color: #1a2332; margin-bottom: 10px;">Nenhum dado financeiro encontrado</h3>
        <p>Não há registros de pagamentos, mensalidades ou emolumentos no sistema.</p>
        <p style="font-size: 13px; margin-top: 10px; color: #94a3b8;">
            Cadastre alunos, emolumentos e registre pagamentos para visualizar o relatório.
        </p>
    </div>
    <?php endif; ?>

    <!-- ===== RODAPÉ OFICIAL ===== -->
    <div class="footer-oficial">
        <div>
            <span class="selo-oficial">Documento Oficial AGT</span>
            <div style="margin-top: 5px;">🔒 Válido para fins tributários</div>
        </div>
        <div>
            <div>Emissão: <?= $dataAtual ?> · <?= $horaAtual ?></div>
            <div style="font-size: 11px; color: #94a3b8;">Sistema AGT v2.0</div>
        </div>
    </div>
    
    <!-- ===== ASSINATURA ===== -->
    <div class="footer-oficial" style="margin-top: 10px; border-top: none; padding-top: 0;">
        <div class="assinatura" style="flex: 1;">
            <div class="linha"></div>
            <div class="nome">__________________________________</div>
            <div class="cargo">Director Financeiro</div>
            <div style="font-size: 11px; color: #94a3b8;">Responsável Tributário</div>
        </div>
        <div class="assinatura" style="flex: 1;">
            <div class="linha"></div>
            <div class="nome">__________________________________</div>
            <div class="cargo">Coordenador AGT</div>
            <div style="font-size: 11px; color: #94a3b8;">Administração Geral Tributária</div>
        </div>
    </div>
    
    <!-- ===== BOTÕES ===== -->
    <div class="no-print" style="text-align: center; margin-top: 25px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
        <?php if ($temPagamentos || $temArrecadacaoMensal || $temResumoEmolumentos): ?>
        <button onclick="window.print()" style="
            padding: 12px 35px;
            background: linear-gradient(135deg, #1a237e, #0d1445);
            color: #c9a84c;
            border: 2px solid #c9a84c;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 40px rgba(26,35,126,0.3)'" 
        onmouseout="this.style.transform='none'; this.style.boxShadow='none'">
            🖨️ Imprimir / PDF
        </button>
        <?php endif; ?>
        <a href="../index.php" style="
            display: inline-block;
            padding: 12px 35px;
            background: #f1f5f9;
            color: #4a5568;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid transparent;
        " onmouseover="this.style.background='#e2e8f0'; this.style.transform='translateY(-2px)'" 
        onmouseout="this.style.background='#f1f5f9'; this.style.transform='none'">
            ← Voltar
        </a>
    </div>
</div>

<script>
    window.onbeforeprint = function() {};
</script>

</body>
</html>
<?php
// ===== FIM DO BUFFER =====
$html = ob_get_clean();

// Exibe o HTML
echo $html;
?>