<?php
// ============================================
// modules/escola/alunos/imprimir_todas.php - Imprimir Todas Listas Nominais
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== BUSCAR ALUNOS =====
$alunos = [];
try {
    $alunos = $pdo->query("
        SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
               Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro
        FROM alunos 
        WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
        ORDER BY Classe, TURMA, nome
    ")->fetchAll();
} catch (Exception $e) {}

// ===== AGRUPAR POR CLASSE =====
$grupos = [];
foreach ($alunos as $aluno) {
    $classe = $aluno['Classe'] ?? 'Sem Classe';
    $turma = $aluno['TURMA'] ?? 'Sem Turma';
    $sala = $aluno['SALA'] ?? 'Sem Sala';
    $key = $classe . '|' . $turma . '|' . $sala;
    
    if (!isset($grupos[$key])) {
        $grupos[$key] = [
            'classe' => $classe,
            'turma' => $turma,
            'sala' => $sala,
            'periodo' => $aluno['Periodo'] ?? 'Manhã',
            'alunos' => []
        ];
    }
    $grupos[$key]['alunos'][] = $aluno;
}

// ===== DADOS DA EMPRESA =====
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'SoftGest Sistemas';
$enderecoEmpresa = $empresa['endereco'] ?? '';
$telefoneEmpresa = $empresa['telefone'] ?? '';
$emailEmpresa = $empresa['email'] ?? '';
$nifEmpresa = $empresa['cnpj'] ?? '';

// ===== CALCULAR ANO LETIVO =====
$mes_atual = date('m');
$ano_atual = date('Y');
if ($mes_atual >= 9) {
    $ano_letivo = $ano_atual . '/' . ($ano_atual + 1);
} else {
    $ano_letivo = ($ano_atual - 1) . '/' . $ano_atual;
}

$total_alunos = count($alunos);
$total_turmas = count($grupos);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listas Nominais - <?= htmlspecialchars($nomeEmpresa) ?></title>
    <style>
        /* ============================================
           ESTILOS PARA IMPRESSÃO
           ============================================ */
        @page {
            size: landscape;
            margin: 12px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 11px;
            background: #ffffff;
            padding: 8px;
            color: #1a2332;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        /* ===== HEADER GERAL ===== */
        .header-geral {
            text-align: center;
            border-bottom: 3px solid #1a2332;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        
        .header-geral .empresa-nome {
            font-size: 20px;
            font-weight: 800;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header-geral .empresa-nome span {
            color: #c9a84c;
        }
        
        .header-geral .empresa-dados {
            font-size: 10px;
            color: #4a5568;
            margin-top: 2px;
        }
        
        .header-geral .empresa-nif {
            font-size: 11px;
            font-weight: 700;
            color: #1a2332;
            margin-top: 2px;
        }
        
        .header-geral .titulo {
            margin-top: 8px;
            font-size: 16px;
            font-weight: 700;
            color: #1a2332;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        .header-geral .info {
            font-size: 10px;
            color: #4a5568;
            margin-top: 2px;
        }
        
        /* ===== INFO BAR ===== */
        .info-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 8px 0 12px;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        
        .info-bar .item {
            text-align: center;
            font-size: 11px;
        }
        
        .info-bar .item strong {
            color: #1a2332;
        }
        
        /* ===== LISTA POR TURMA ===== */
        .lista-turma {
            page-break-after: always;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px 12px;
            background: #ffffff;
        }
        
        .lista-turma:last-child {
            page-break-after: avoid;
            border-bottom: none;
        }
        
        .turma-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #c9a84c;
            padding-bottom: 6px;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .turma-header .classe {
            font-size: 14px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .turma-header .turma {
            font-size: 12px;
            color: #c9a84c;
            font-weight: 600;
        }
        
        .turma-header .info-turma {
            font-size: 10px;
            color: #94a3b8;
        }
        
        .turma-header .total-turma {
            font-size: 11px;
            font-weight: 600;
            color: #1a2332;
            background: #f8fafc;
            padding: 2px 10px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        /* ===== TABELA ===== */
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        
        .table thead {
            background: #1a2332;
        }
        
        .table thead th {
            padding: 4px 6px;
            text-align: left;
            font-weight: 600;
            color: #ffffff;
            border: 1px solid #1a2332;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 3px 6px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .table tbody tr:nth-child(even) {
            background: #fafbfc;
        }
        
        .badge-sexo {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 8px;
            font-weight: 600;
        }
        
        .badge-m {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-f {
            background: #fce7f3;
            color: #9d174d;
        }
        
        .status-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 7px;
            font-weight: 600;
        }
        
        .status-Matrícula {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-Confirmação {
            background: #d1fae5;
            color: #065f46;
        }
        
        /* ===== FOOTER ===== */
        .footer {
            text-align: center;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 8px;
            color: #94a3b8;
        }
        
        /* ============================================
           RESPONSIVO E IMPRESSÃO
           ============================================ */
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .lista-turma {
                border: none;
                padding: 5px 8px;
                page-break-after: always;
            }
            .lista-turma:last-child {
                page-break-after: avoid;
            }
            .table thead th {
                font-size: 7px;
                padding: 3px 4px;
            }
            .table tbody td {
                font-size: 8px;
                padding: 2px 4px;
            }
            .turma-header .classe {
                font-size: 12px;
            }
            .header-geral .empresa-nome {
                font-size: 18px;
            }
        }
        
        @media (max-width: 768px) {
            .info-bar {
                grid-template-columns: 1fr 1fr;
                gap: 4px;
            }
            .turma-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .table {
                font-size: 9px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- ===== HEADER GERAL ===== -->
    <div class="header-geral">
        <div class="empresa-nome"><?= htmlspecialchars($nomeEmpresa) ?> <span>Web</span></div>
        <div class="empresa-dados">
            <?= htmlspecialchars($enderecoEmpresa) ?>
            <?php if ($telefoneEmpresa): ?>
            <span class="sep">|</span> Tel: <?= htmlspecialchars($telefoneEmpresa) ?>
            <?php endif; ?>
            <?php if ($emailEmpresa): ?>
            <span class="sep">|</span> Email: <?= htmlspecialchars($emailEmpresa) ?>
            <?php endif; ?>
        </div>
        <div class="empresa-nif">NIF: <?= htmlspecialchars($nifEmpresa) ?></div>
        <div class="titulo">📋 Listas Nominais</div>
        <div class="info">
            Ano Lectivo: <?= $ano_letivo ?> | 
            Data: <?= date('d/m/Y H:i') ?> | 
            Total de Alunos: <?= $total_alunos ?> | 
            Total de Turmas: <?= $total_turmas ?>
        </div>
    </div>

    <!-- ===== INFO BAR ===== -->
    <div class="info-bar">
        <div class="item"><strong>Total Alunos:</strong> <?= $total_alunos ?></div>
        <div class="item"><strong>Total Turmas:</strong> <?= $total_turmas ?></div>
        <div class="item"><strong>Total Classes:</strong> <?= count(array_unique(array_column($alunos, 'Classe'))) ?></div>
        <div class="item"><strong>Ano Lectivo:</strong> <?= $ano_letivo ?></div>
    </div>

    <!-- ===== LISTAS POR TURMA ===== -->
    <?php 
    $total_geral = 0;
    foreach($grupos as $grupo): 
        $classe = $grupo['classe'];
        $turma = $grupo['turma'];
        $sala = $grupo['sala'];
        $periodo = $grupo['periodo'];
        $alunos_lista = $grupo['alunos'];
        $total = count($alunos_lista);
        $total_geral += $total;
    ?>
    
    <div class="lista-turma">
        <!-- Header da Turma -->
        <div class="turma-header">
            <div>
                <span class="classe"><?= htmlspecialchars($classe) ?></span>
                <span class="turma">Turma <?= htmlspecialchars($turma) ?></span>
            </div>
            <div>
                <span class="info-turma">
                    Sala: <?= htmlspecialchars($sala) ?> | Período: <?= htmlspecialchars($periodo) ?>
                </span>
                <span class="total-turma">Total: <?= $total ?> alunos</span>
            </div>
        </div>
        
        <!-- Tabela -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 5%;">Nº</th>
                        <th style="width: 35%;">Nome do Aluno</th>
                        <th style="width: 8%;">Sexo</th>
                        <th style="width: 8%;">Idade</th>
                        <th style="width: 15%;">Data Nasc.</th>
                        <th style="width: 15%;">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $num = 1;
                    foreach($alunos_lista as $aluno): 
                        $sexo = $aluno['Sexo'] ?? 'M';
                        $sexoClass = $sexo == 'M' ? 'm' : 'f';
                        $data_nasc = (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0) ? 
                            sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-';
                    ?>
                    <tr>
                        <td style="text-align: center;"><?= $num ?></td>
                        <td><?= htmlspecialchars($aluno['nome']) ?></td>
                        <td style="text-align: center;">
                            <span class="badge-sexo badge-<?= $sexoClass ?>"><?= $sexo ?></span>
                        </td>
                        <td style="text-align: center;"><?= $aluno['Idade'] ?? '-' ?></td>
                        <td style="text-align: center;"><?= $data_nasc ?></td>
                        <td style="text-align: center;">
                            <span class="status-badge status-<?= $aluno['Situacao_Cadastro'] ?? 'Matrícula' ?>">
                                <?= $aluno['Situacao_Cadastro'] ?? 'Matrícula' ?>
                            </span>
                        </td>
                    </tr>
                    <?php $num++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php endforeach; ?>

    <!-- ===== FOOTER ===== -->
    <div class="footer">
        <p>
            Total de alunos: <strong><?= $total_geral ?></strong> | 
            <?= htmlspecialchars($nomeEmpresa) ?> - Sistema de Gestão Escolar
        </p>
        <p style="margin-top: 2px;">
            Documento emitido em <?= date('d/m/Y H:i:s') ?>
        </p>
    </div>
</div>

<!-- ===== BOTÃO IMPRIMIR ===== -->
<div class="no-print" style="text-align:center;margin-top:15px;padding:12px;background:#f8fafc;border-radius:8px;max-width:100%;">
    <button onclick="window.print()" style="padding:10px 35px;background:#c9a84c;color:#1a2332;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;box-shadow:0 2px 15px rgba(201,168,76,0.3);">
        🖨️ Imprimir / Salvar PDF
    </button>
    <a href="listas_nominais.php" style="display:inline-block;margin-left:12px;padding:10px 20px;background:#f1f5f9;color:#4a5568;text-decoration:none;border-radius:8px;font-weight:600;">
        ← Voltar
    </a>
</div>

<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>

</body>
</html>