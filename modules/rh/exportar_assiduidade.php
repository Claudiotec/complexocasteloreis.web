<?php
// exportar_assiduidade.php - Exportar dados para Excel ou visualizar para impressão
require_once '../../config/database.php';

date_default_timezone_set('Africa/Luanda');

// Função para obter dados da empresa
function getDadosEmpresa($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
        $empresa = $stmt->fetch();
        if (!$empresa) {
            return [
                'nome_fantasia' => 'SoftGest Web',
                'razao_social' => 'SoftGest Sistemas Ltda',
                'cnpj' => '00.000.000/0001-00',
                'endereco' => 'Rua Principal, 123',
                'telefone' => '(11) 9999-9999',
                'email' => 'contato@softgest.com',
                'logo' => null
            ];
        }
        return $empresa;
    } catch(PDOException $e) {
        return [
            'nome_fantasia' => 'SoftGest Web',
            'razao_social' => 'SoftGest Sistemas Ltda',
            'cnpj' => '00.000.000/0001-00',
            'endereco' => 'Rua Principal, 123',
            'telefone' => '(11) 9999-9999',
            'email' => 'contato@softgest.com',
            'logo' => null
        ];
    }
}

// Filtros
$filtro_data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$filtro_data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$filtro_funcionario = $_GET['funcionario'] ?? '';
$filtro_mes = $_GET['mes'] ?? date('m');
$filtro_ano = $_GET['ano'] ?? date('Y');

// Verificar se é para imprimir (visualizar) ou exportar
$imprimir = isset($_GET['imprimir']) && $_GET['imprimir'] == 1;

$empresa = getDadosEmpresa($pdo);

// Buscar dados
$sql = "SELECT 
    f.id as funcionario_id,
    f.nome_completo,
    f.numero_agente,
    f.categoria_actual,
    f.funcao,
    f.instituicao,
    p.data,
    p.hora_entrada,
    p.hora_saida,
    p.status as presenca_status,
    p.horas_trabalhadas,
    DATE_FORMAT(p.data, '%d/%m/%Y') as data_formatada
FROM forca_trabalho f
LEFT JOIN presenca_qr p ON f.id = p.funcionario_id 
WHERE f.status = 'ativo'";

if (!empty($filtro_funcionario)) {
    $sql .= " AND f.id = " . intval($filtro_funcionario);
}

if (!empty($filtro_data_inicio) && !empty($filtro_data_fim)) {
    $sql .= " AND p.data BETWEEN '" . $filtro_data_inicio . "' AND '" . $filtro_data_fim . "'";
}

if (!empty($filtro_mes) && !empty($filtro_ano)) {
    $sql .= " AND MONTH(p.data) = " . intval($filtro_mes) . " AND YEAR(p.data) = " . intval($filtro_ano);
}

$sql .= " ORDER BY f.nome_completo, p.data DESC";

$stmt = $pdo->query($sql);
$registros = $stmt->fetchAll();

// Calcular totais
$total_presentes = 0;
$total_ausentes = 0;
$total_horas = 0;
foreach ($registros as $r) {
    if ($r['presenca_status'] === 'presente') $total_presentes++;
    else $total_ausentes++;
    $total_horas += floatval($r['horas_trabalhadas'] ?? 0);
}
$total_registros = count($registros);
$efetividade = $total_registros > 0 ? round(($total_presentes / max($total_registros, 1)) * 100, 2) : 0;

// Buscar nome do funcionário se filtrado
$nome_funcionario = 'Todos';
if (!empty($filtro_funcionario)) {
    $stmt = $pdo->prepare("SELECT nome_completo FROM forca_trabalho WHERE id = ?");
    $stmt->execute([$filtro_funcionario]);
    $func = $stmt->fetch();
    if ($func) $nome_funcionario = $func['nome_completo'];
}

// =========================================================
// SE FOR EXPORTAR (NÃO IMPRIMIR) - BAIXAR COMO EXCEL
// =========================================================
if (!$imprimir) {
    $nome_arquivo = 'Relatorio_Assiduidade_' . date('Y-m-d') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Versão simplificada para Excel (sem CSS complexo)
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Relatório de Assiduidade</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            .header-empresa { border-bottom: 2px solid #c9a84c; padding-bottom: 10px; margin-bottom: 15px; }
            .header-empresa h1 { color: #1a2332; font-size: 18px; }
            .resumo-box { background: #f8fafc; padding: 10px; margin-bottom: 15px; border: 1px solid #eef2f7; }
            .resumo-box .item { display: inline-block; padding: 5px 20px; text-align: center; }
            .resumo-box .numero { font-size: 18px; font-weight: bold; }
            .resumo-box .rotulo { font-size: 10px; color: #666; }
            table { width: 100%; border-collapse: collapse; }
            table thead { background: #1a2332; color: white; }
            table th, table td { padding: 6px 10px; border: 1px solid #ccc; text-align: left; }
            table tr:nth-child(even) { background: #f9f9f9; }
            .status-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; }
            .status-badge.presente { background: #d1fae5; color: #065f46; }
            .status-badge.ausente { background: #fee2e2; color: #991b1b; }
            .footer-relatorio { margin-top: 15px; padding-top: 10px; border-top: 1px solid #ccc; font-size: 10px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header-empresa">
            <h1><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest Web') ?></h1>
            <p><?= htmlspecialchars($empresa['endereco'] ?? 'Rua Principal, 123') ?> | 
               Tel: <?= htmlspecialchars($empresa['telefone'] ?? '(11) 9999-9999') ?> | 
               Email: <?= htmlspecialchars($empresa['email'] ?? 'contato@softgest.com') ?></p>
            <h2>RELATÓRIO DE ASSIDUIDADE</h2>
            <p>
                <?php if (!empty($filtro_data_inicio) && !empty($filtro_data_fim)): ?>
                    Período: <?= date('d/m/Y', strtotime($filtro_data_inicio)) ?> a <?= date('d/m/Y', strtotime($filtro_data_fim)) ?>
                <?php elseif (!empty($filtro_mes) && !empty($filtro_ano)): ?>
                    Mês: <?= date('F', mktime(0,0,0,$filtro_mes,1)) ?>/<?= $filtro_ano ?>
                <?php else: ?>
                    Data: <?= date('d/m/Y') ?>
                <?php endif; ?>
                | Funcionário: <?= htmlspecialchars($nome_funcionario) ?>
            </p>
        </div>
        
        <div class="resumo-box">
            <div class="item"><div class="numero"><?= $total_registros ?></div><div class="rotulo">Total Registros</div></div>
            <div class="item"><div class="numero" style="color: #2ecc71;"><?= $total_presentes ?></div><div class="rotulo">Presentes</div></div>
            <div class="item"><div class="numero" style="color: #e74c3c;"><?= $total_ausentes ?></div><div class="rotulo">Ausentes</div></div>
            <div class="item"><div class="numero" style="color: #8b5cf6;"><?= number_format($total_horas, 1, ',', '.') ?>h</div><div class="rotulo">Horas</div></div>
            <div class="item"><div class="numero" style="color: #c9a84c;"><?= $efetividade ?>%</div><div class="rotulo">Efetividade</div></div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Nº Agente</th>
                    <th>Categoria</th>
                    <th>Data</th>
                    <th>Entrada</th>
                    <th>Saída</th>
                    <th>Horas</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($registros) > 0): ?>
                    <?php foreach($registros as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['nome_completo']) ?></td>
                        <td><?= htmlspecialchars($r['numero_agente']) ?></td>
                        <td><?= htmlspecialchars($r['categoria_actual'] ?? '—') ?></td>
                        <td><?= $r['data_formatada'] ?></td>
                        <td><?= $r['hora_entrada'] ? date('H:i:s', strtotime($r['hora_entrada'])) : '—' ?></td>
                        <td><?= $r['hora_saida'] ? date('H:i:s', strtotime($r['hora_saida'])) : '—' ?></td>
                        <td><?= $r['horas_trabalhadas'] ? number_format($r['horas_trabalhadas'], 2, ',', '.') . 'h' : '—' ?></td>
                        <td>
                            <span class="status-badge <?= $r['presenca_status'] ?? 'ausente' ?>">
                                <?= ucfirst($r['presenca_status'] ?? 'ausente') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center;">Nenhum registro encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="footer-relatorio">
            Relatório gerado em <?= date('d/m/Y H:i:s') ?> | SoftGest Web - Sistema de Gestão Empresarial
        </div>
    </body>
    </html>
    <?php
    exit;
}

// =========================================================
// SE FOR IMPRIMIR - VISUALIZADOR A4 PROFISSIONAL
// =========================================================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório de Assiduidade - Impressão</title>
    <style>
        /* =============================================
           ESTILOS PARA IMPRESSÃO A4
           ============================================= */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Calibri', 'Arial', sans-serif; 
            background: #f0f4f8;
            color: #1a2332;
            padding: 20px;
        }
        
        .report-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 15mm 12mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            min-height: 297mm;
        }
        
        /* CABEÇALHO */
        .header-empresa {
            border-bottom: 4px solid #c9a84c;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-empresa .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-empresa .logo-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #1a2332;
            font-weight: 800;
            flex-shrink: 0;
        }
        
        .header-empresa .empresa-info h1 {
            font-size: 20px;
            color: #1a2332;
            font-weight: 700;
            margin-bottom: 2px;
        }
        
        .header-empresa .empresa-info .detalhes {
            font-size: 10px;
            color: #64748b;
        }
        
        .header-empresa .empresa-info .detalhes span {
            margin-right: 12px;
        }
        
        .header-empresa .titulo-relatorio {
            text-align: right;
        }
        
        .header-empresa .titulo-relatorio h2 {
            font-size: 18px;
            color: #c9a84c;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .header-empresa .titulo-relatorio .periodo {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
            line-height: 1.6;
        }
        
        .header-empresa .titulo-relatorio .periodo strong {
            color: #1a2332;
        }
        
        /* RESUMO */
        .resumo-box {
            background: #f8fafc;
            border: 1px solid #eef2f7;
            border-radius: 8px;
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            align-items: center;
        }
        
        .resumo-box .item {
            text-align: center;
            padding: 3px 12px;
        }
        
        .resumo-box .item .numero {
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .resumo-box .item .rotulo {
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .resumo-box .item .numero.presente { color: #2ecc71; }
        .resumo-box .item .numero.ausente { color: #e74c3c; }
        .resumo-box .item .numero.horas { color: #8b5cf6; }
        .resumo-box .item .numero.efetividade { color: #c9a84c; }
        
        /* TABELA */
        .table-container {
            margin-top: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }
        
        table thead {
            background: #1a2332;
            color: white;
        }
        
        table thead th {
            padding: 7px 10px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 0.5px;
            border: 1px solid #1a2332;
        }
        
        table tbody td {
            padding: 6px 10px;
            border: 1px solid #eef2f7;
            vertical-align: middle;
        }
        
        table tbody tr:nth-child(even) {
            background: #fafbfc;
        }
        
        table tbody tr:hover {
            background: #f1f5f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
        }
        
        .status-badge.presente {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.ausente {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-badge.atraso {
            background: #fef3c7;
            color: #92400e;
        }
        
        .text-center { text-align: center; }
        .fw-bold { font-weight: 700; }
        
        /* RODAPÉ */
        .footer-relatorio {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 2px solid #eef2f7;
            display: flex;
            justify-content: space-between;
            font-size: 9.5px;
            color: #94a3b8;
        }
        
        .footer-relatorio .assinatura {
            display: flex;
            gap: 25px;
        }
        
        .footer-relatorio .assinatura .campo {
            text-align: center;
        }
        
        .footer-relatorio .assinatura .campo .linha {
            width: 120px;
            border-bottom: 1.5px solid #1a2332;
            margin: 3px auto;
        }
        
        .footer-relatorio .assinatura .campo .label {
            font-size: 8.5px;
            color: #64748b;
        }
        
        /* BOTÕES */
        .no-print {
            display: block;
        }
        
        .actions-bar {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .btn-voltar {
            display: inline-block;
            padding: 8px 20px;
            background: #c9a84c;
            color: #1a2332;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }
        .btn-voltar:hover { background: #b8953a; }
        
        .btn-print-action {
            display: inline-block;
            padding: 8px 20px;
            background: #1a2332;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }
        .btn-print-action:hover { background: #2d3748; }
        
        .btn-close {
            display: inline-block;
            padding: 8px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }
        .btn-close:hover { background: #c0392b; }
        
        /* IMPRESSÃO A4 */
        @page {
            size: A4;
            margin: 10mm 10mm 10mm 10mm;
        }
        
        @media print {
            body { 
                background: white !important; 
                padding: 0 !important; 
                margin: 0 !important;
            }
            
            .no-print { 
                display: none !important; 
            }
            
            .report-container {
                max-width: 100% !important;
                padding: 5mm 8mm !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                min-height: auto !important;
            }
            
            .header-empresa { 
                border-bottom: 3px solid #c9a84c !important;
                padding-bottom: 10px !important;
                margin-bottom: 15px !important;
            }
            
            .resumo-box { 
                background: #f8fafc !important; 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important;
                padding: 8px 12px !important;
                margin-bottom: 15px !important;
            }
            
            table thead { 
                background: #1a2332 !important; 
                color: white !important; 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important;
            }
            
            table thead th {
                padding: 5px 8px !important;
                font-size: 7.5px !important;
            }
            
            table tbody td {
                padding: 4px 8px !important;
                font-size: 9px !important;
            }
            
            .status-badge { 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important;
                padding: 1px 8px !important;
                font-size: 8px !important;
            }
            
            .footer-relatorio { 
                margin-top: 15px !important;
                padding-top: 10px !important;
                page-break-inside: avoid !important;
            }
            
            .footer-relatorio .assinatura .campo .linha {
                width: 80px !important;
            }
            
            table tbody tr {
                page-break-inside: avoid;
            }
            
            table thead {
                display: table-header-group;
            }
        }
        
        /* RESPONSIVO */
        @media (max-width: 768px) {
            .header-empresa {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .header-empresa .logo-area {
                flex-direction: column;
                text-align: center;
            }
            
            .header-empresa .titulo-relatorio {
                text-align: center;
            }
            
            .resumo-box {
                flex-direction: column;
                gap: 5px;
            }
            
            .resumo-box .item {
                width: 100%;
            }
            
            .footer-relatorio {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .footer-relatorio .assinatura {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .footer-relatorio .assinatura .campo .linha {
                width: 80px;
            }
        }
    </style>
</head>
<body>
    <!-- ===== BARRA DE AÇÕES (só aparece na visualização) ===== -->
    <div class="actions-bar no-print">
        <a href="assiduidade.php" class="btn-voltar">← Voltar</a>
        <button onclick="window.print()" class="btn-print-action">🖨️ Imprimir</button>
        <button onclick="window.close()" class="btn-close">✕ Fechar</button>
    </div>
    
    <!-- =============================================
         RELATÓRIO
         ============================================= -->
    <div class="report-container" id="relatorio">
        
        <!-- ===== CABEÇALHO ===== -->
        <div class="header-empresa">
            <div class="logo-area">
                <div class="logo-icon"><?= substr($empresa['nome_fantasia'] ?? 'SG', 0, 2) ?></div>
                <div class="empresa-info">
                    <h1><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest Web') ?></h1>
                    <div class="detalhes">
                        <span>📍 <?= htmlspecialchars($empresa['endereco'] ?? 'Rua Principal, 123') ?></span>
                        <span>📞 <?= htmlspecialchars($empresa['telefone'] ?? '(11) 9999-9999') ?></span>
                        <span>✉️ <?= htmlspecialchars($empresa['email'] ?? 'contato@softgest.com') ?></span>
                        <span>📋 CNPJ: <?= htmlspecialchars($empresa['cnpj'] ?? '00.000.000/0001-00') ?></span>
                    </div>
                </div>
            </div>
            <div class="titulo-relatorio">
                <h2>📊 RELATÓRIO DE ASSIDUIDADE</h2>
                <div class="periodo">
                    <?php if (!empty($filtro_data_inicio) && !empty($filtro_data_fim)): ?>
                        Período: <strong><?= date('d/m/Y', strtotime($filtro_data_inicio)) ?></strong> a <strong><?= date('d/m/Y', strtotime($filtro_data_fim)) ?></strong>
                    <?php elseif (!empty($filtro_mes) && !empty($filtro_ano)): ?>
                        Mês: <strong><?= date('F', mktime(0,0,0,$filtro_mes,1)) ?>/<?= $filtro_ano ?></strong>
                    <?php else: ?>
                        Data: <strong><?= date('d/m/Y') ?></strong>
                    <?php endif; ?>
                    <br>
                    Funcionário: <strong><?= htmlspecialchars($nome_funcionario) ?></strong>
                    <br>
                    <span style="font-size: 9px; color: #94a3b8;">Gerado em: <?= date('d/m/Y H:i:s') ?></span>
                </div>
            </div>
        </div>
        
        <!-- ===== RESUMO ===== -->
        <div class="resumo-box">
            <div class="item">
                <div class="numero"><?= $total_registros ?></div>
                <div class="rotulo">Total Registros</div>
            </div>
            <div class="item">
                <div class="numero presente"><?= $total_presentes ?></div>
                <div class="rotulo">✅ Presentes</div>
            </div>
            <div class="item">
                <div class="numero ausente"><?= $total_ausentes ?></div>
                <div class="rotulo">❌ Ausentes</div>
            </div>
            <div class="item">
                <div class="numero horas"><?= number_format($total_horas, 1, ',', '.') ?>h</div>
                <div class="rotulo">⏱️ Horas</div>
            </div>
            <div class="item">
                <div class="numero efetividade"><?= $efetividade ?>%</div>
                <div class="rotulo">📈 Efetividade</div>
            </div>
        </div>
        
        <!-- ===== TABELA ===== -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 22%;">Funcionário</th>
                        <th style="width: 10%;">Nº Agente</th>
                        <th style="width: 14%;">Categoria</th>
                        <th style="width: 10%;">Data</th>
                        <th style="width: 10%;">Entrada</th>
                        <th style="width: 10%;">Saída</th>
                        <th style="width: 10%;">Horas</th>
                        <th style="width: 14%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($registros) > 0): ?>
                        <?php foreach($registros as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['nome_completo']) ?></strong></td>
                            <td><?= htmlspecialchars($r['numero_agente']) ?></td>
                            <td><?= htmlspecialchars($r['categoria_actual'] ?? '—') ?></td>
                            <td><?= $r['data_formatada'] ?></td>
                            <td><?= $r['hora_entrada'] ? date('H:i:s', strtotime($r['hora_entrada'])) : '—' ?></td>
                            <td><?= $r['hora_saida'] ? date('H:i:s', strtotime($r['hora_saida'])) : '—' ?></td>
                            <td class="text-center"><?= $r['horas_trabalhadas'] ? number_format($r['horas_trabalhadas'], 2, ',', '.') . 'h' : '—' ?></td>
                            <td>
                                <span class="status-badge <?= $r['presenca_status'] ?? 'ausente' ?>">
                                    <?= ucfirst($r['presenca_status'] ?? 'ausente') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                Nenhum registro encontrado para os filtros selecionados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ===== RODAPÉ ===== -->
        <div class="footer-relatorio">
            <div>
                <strong>SoftGest Web</strong> - Sistema de Gestão Empresarial<br>
                Relatório gerado em <?= date('d/m/Y H:i:s') ?>
            </div>
            <div class="assinatura">
                <div class="campo">
                    <div class="linha"></div>
                    <span class="label">Responsável pelo RH</span>
                </div>
                <div class="campo">
                    <div class="linha"></div>
                    <span class="label">Diretor Geral</span>
                </div>
                <div class="campo">
                    <div class="linha"></div>
                    <span class="label">Funcionário</span>
                </div>
            </div>
        </div>
        
        <!-- ===== MARCA D'ÁGUA ===== -->
        <div style="text-align: center; margin-top: 5px; font-size: 7px; color: #d1d5db; letter-spacing: 2px;">
            • S O F T G E S T   W E B   •   S I S T E M A   D E   G E S T Ã O   E M P R E S A R I A L   •
        </div>
    </div>
    
    <script>
        // Auto-imprimir se veio com parâmetro imprimir=1
        window.onload = function() {
            <?php if ($imprimir): ?>
            setTimeout(function() {
                window.print();
            }, 500);
            <?php endif; ?>
        }
    </script>
</body>
</html>