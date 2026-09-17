<?php
// ============================================
// modules/escola/alunos/gerar_html_classe.php - Gerar HTML de uma Classe
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

$classe = $_GET['classe'] ?? '';
$turma = $_GET['turma'] ?? '';

if (empty($classe)) {
    header('Location: listas_nominais.php');
    exit;
}

// ===== BUSCAR ALUNOS =====
$alunos = [];
try {
    $sql = "SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
                   Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro
            FROM alunos 
            WHERE (Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação')
              AND Classe = ?";
    $params = [$classe];
    
    if (!empty($turma)) {
        $sql .= " AND TURMA = ?";
        $params[] = $turma;
    }
    
    $sql .= " ORDER BY TURMA, nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alunos = $stmt->fetchAll();
} catch (Exception $e) {}

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

$data_atual = date('d/m/Y H:i');

// ===== GERAR HTML =====
$total = count($alunos);
$turma_nome = $turma ?: 'Todas as Turmas';

// Agrupar por turma
$grupos_turmas = [];
foreach ($alunos as $aluno) {
    $t = $aluno['TURMA'] ?? 'Sem Turma';
    if (!isset($grupos_turmas[$t])) {
        $grupos_turmas[$t] = [];
    }
    $grupos_turmas[$t][] = $aluno;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista Nominal - <?= htmlspecialchars($classe) ?></title>
    <style>
        @page { size: landscape; margin: 15px; }
        body { font-family: Arial, sans-serif; background: white; margin: 0; padding: 15px; color: #1a2332; }
        .container { max-width: 100%; margin: 0 auto; }
        
        .header { 
            text-align: center; 
            border-bottom: 3px solid #1a2332; 
            padding-bottom: 15px; 
            margin-bottom: 20px;
        }
        .header .empresa { font-size: 22px; font-weight: 800; color: #1a2332; text-transform: uppercase; }
        .header .empresa span { color: #c9a84c; }
        .header .dados { font-size: 11px; color: #4a5568; margin-top: 4px; }
        .header .nif { font-size: 12px; font-weight: 700; color: #1a2332; margin-top: 3px; }
        
        .info-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin: 15px 0;
            background: #f8fafc;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .info-bar .item { text-align: center; font-size: 13px; }
        .info-bar .item strong { color: #1a2332; }
        
        .title { text-align: center; margin: 15px 0; }
        .title h2 { font-size: 18px; font-weight: 700; color: #1a2332; text-transform: uppercase; margin: 0; }
        .title p { font-size: 11px; color: #94a3b8; margin: 2px 0 0; }
        
        .table-container { overflow-x: auto; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        thead { background: #1a2332; }
        thead th { 
            padding: 8px 10px; 
            text-align: left; 
            font-weight: 600; 
            color: #ffffff; 
            border: 1px solid #1a2332;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tbody td { 
            padding: 6px 10px; 
            border: 1px solid #e2e8f0; 
            vertical-align: middle;
        }
        tbody tr:nth-child(even) { background: #fafbfc; }
        tbody tr:hover { background: #f1f5f9; }
        
        .badge-sexo {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-m { background: #dbeafe; color: #1e40af; }
        .badge-f { background: #fce7f3; color: #9d174d; }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            color: #94a3b8;
        }
        
        .turma-title {
            background: #c9a84c;
            color: #1a2332;
            padding: 6px 12px;
            border-radius: 4px;
            display: inline-block;
            font-weight: 700;
            font-size: 14px;
            margin: 15px 0 10px;
        }
        
        .page-break { page-break-after: always; }
        
        @media print {
            body { padding: 0; }
            .container { padding: 10px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Header -->
    <div class="header">
        <div class="empresa"><?= htmlspecialchars($nomeEmpresa) ?> <span>Web</span></div>
        <div class="dados">
            <?= htmlspecialchars($enderecoEmpresa) ?>
            <?php if ($telefoneEmpresa): ?>
            <span class="sep">|</span> Tel: <?= htmlspecialchars($telefoneEmpresa) ?>
            <?php endif; ?>
            <?php if ($emailEmpresa): ?>
            <span class="sep">|</span> Email: <?= htmlspecialchars($emailEmpresa) ?>
            <?php endif; ?>
        </div>
        <div class="nif">NIF: <?= htmlspecialchars($nifEmpresa) ?></div>
    </div>

    <!-- Info Bar -->
    <div class="info-bar">
        <div class="item"><strong>Classe:</strong> <?= htmlspecialchars($classe) ?></div>
        <div class="item"><strong>Turma:</strong> <?= htmlspecialchars($turma_nome) ?></div>
        <div class="item"><strong>Ano Lectivo:</strong> <?= $ano_letivo ?></div>
        <div class="item"><strong>Total Alunos:</strong> <?= $total ?></div>
    </div>

    <!-- Title -->
    <div class="title">
        <h2>Lista Nominal dos Alunos</h2>
        <p>Relatório gerado em <?= $data_atual ?></p>
    </div>

    <?php if (!empty($turma) || count($grupos_turmas) == 1): ?>
        <!-- Apenas uma turma -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">Nº</th>
                        <th style="width: 40%;">Nome do Aluno</th>
                        <th style="width: 10%;">Sexo</th>
                        <th style="width: 10%;">Idade</th>
                        <th style="width: 15%;">Data Nasc.</th>
                        <th style="width: 20%;">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $num = 1;
                    foreach($alunos as $aluno): 
                        $sexo = $aluno['Sexo'] ?? 'M';
                        $sexoClass = $sexo == 'M' ? 'm' : 'f';
                        $data_nasc = (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0) ? 
                            sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-';
                    ?>
                    <tr>
                        <td><?= $num ?></td>
                        <td><?= htmlspecialchars($aluno['nome']) ?></td>
                        <td><span class="badge-sexo badge-<?= $sexoClass ?>"><?= $sexo ?></span></td>
                        <td><?= $aluno['Idade'] ?? '-' ?></td>
                        <td><?= $data_nasc ?></td>
                        <td><?= $aluno['Situacao_Cadastro'] ?? 'Matrícula' ?></td>
                    </tr>
                    <?php $num++; endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- Múltiplas turmas -->
        <?php foreach($grupos_turmas as $t => $alunos_turma): 
            $total_turma = count($alunos_turma);
        ?>
        <div class="turma-title">Turma <?= htmlspecialchars($t) ?> - Total: <?= $total_turma ?> alunos</div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">Nº</th>
                        <th style="width: 40%;">Nome do Aluno</th>
                        <th style="width: 10%;">Sexo</th>
                        <th style="width: 10%;">Idade</th>
                        <th style="width: 15%;">Data Nasc.</th>
                        <th style="width: 20%;">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $num = 1;
                    foreach($alunos_turma as $aluno): 
                        $sexo = $aluno['Sexo'] ?? 'M';
                        $sexoClass = $sexo == 'M' ? 'm' : 'f';
                        $data_nasc = (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0) ? 
                            sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-';
                    ?>
                    <tr>
                        <td><?= $num ?></td>
                        <td><?= htmlspecialchars($aluno['nome']) ?></td>
                        <td><span class="badge-sexo badge-<?= $sexoClass ?>"><?= $sexo ?></span></td>
                        <td><?= $aluno['Idade'] ?? '-' ?></td>
                        <td><?= $data_nasc ?></td>
                        <td><?= $aluno['Situacao_Cadastro'] ?? 'Matrícula' ?></td>
                    </tr>
                    <?php $num++; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        <p>Documento emitido por sistema autorizado • <?= htmlspecialchars($nomeEmpresa) ?></p>
        <p style="margin-top: 2px;">Data de emissão: <?= date('d/m/Y H:i:s') ?></p>
    </div>

</div>

<!-- Botão imprimir -->
<div class="no-print" style="text-align:center;margin-top:20px;padding:12px;background:#f8fafc;border-radius:8px;max-width:100%;">
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