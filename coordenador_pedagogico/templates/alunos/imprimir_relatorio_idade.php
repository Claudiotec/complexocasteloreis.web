<?php
// ============================================
// modules/escola/alunos/imprimir_relatorio_idade.php - Imprimir Relatório Idade
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

// ===== DADOS =====
$totalAlunos = 0;
$alunosPorIdade = [];
$faixasEtarias = [
    '0-5' => 0,
    '6-10' => 0,
    '11-15' => 0,
    '16-20' => 0,
    '21-25' => 0,
    '26-30' => 0,
    '31+' => 0
];

try {
    $totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos")->fetchColumn() ?? 0;
    
    $alunosPorIdade = $pdo->query("
        SELECT Idade, COUNT(*) as total 
        FROM alunos 
        WHERE Idade IS NOT NULL AND Idade > 0
        GROUP BY Idade 
        ORDER BY Idade
    ")->fetchAll();
    
    foreach($alunosPorIdade as $item) {
        $idade = $item['Idade'];
        $total = $item['total'];
        if ($idade <= 5) $faixasEtarias['0-5'] += $total;
        elseif ($idade <= 10) $faixasEtarias['6-10'] += $total;
        elseif ($idade <= 15) $faixasEtarias['11-15'] += $total;
        elseif ($idade <= 20) $faixasEtarias['16-20'] += $total;
        elseif ($idade <= 25) $faixasEtarias['21-25'] += $total;
        elseif ($idade <= 30) $faixasEtarias['26-30'] += $total;
        else $faixasEtarias['31+'] += $total;
    }
    
    $idadeMinima = 0;
    $idadeMaxima = 0;
    $idadeMedia = 0;
    $somaIdades = 0;
    $totalComIdade = 0;
    
    foreach($alunosPorIdade as $item) {
        $idade = $item['Idade'];
        $total = $item['total'];
        if ($idadeMinima == 0 || $idade < $idadeMinima) $idadeMinima = $idade;
        if ($idade > $idadeMaxima) $idadeMaxima = $idade;
        $somaIdades += $idade * $total;
        $totalComIdade += $total;
    }
    
    if ($totalComIdade > 0) {
        $idadeMedia = round($somaIdades / $totalComIdade, 1);
    }
    
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

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório por Idade - <?= htmlspecialchars($nomeEmpresa) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; font-size: 12px; background: #ffffff; padding: 20px; color: #1a2332; }
        .container { max-width: 210mm; margin: 0 auto; background: white; padding: 25px 30px; }
        
        .header { text-align: center; border-bottom: 3px solid #1a2332; padding-bottom: 15px; margin-bottom: 20px; }
        .header .empresa-nome { font-size: 22px; font-weight: 800; color: #1a2332; text-transform: uppercase; letter-spacing: 1px; }
        .header .empresa-nome span { color: #c9a84c; }
        .header .empresa-dados { font-size: 11px; color: #4a5568; margin-top: 4px; }
        .header .empresa-nif { font-size: 12px; font-weight: 700; color: #1a2332; margin-top: 3px; }
        .header .titulo-relatorio { margin-top: 12px; font-size: 18px; font-weight: 700; color: #1a2332; letter-spacing: 2px; text-transform: uppercase; }
        .header .info-relatorio { font-size: 12px; color: #4a5568; margin-top: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 15px 0 20px; }
        .stat-card { background: #f8fafc; padding: 10px 12px; border-radius: 6px; text-align: center; border: 1px solid #e2e8f0; }
        .stat-card .number { font-size: 20px; font-weight: 700; color: #1a2332; }
        .stat-card .label { font-size: 10px; color: #94a3b8; margin-top: 2px; }
        
        .faixas-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin: 15px 0 20px; }
        .faixa-card { background: #f8fafc; padding: 10px; border-radius: 6px; text-align: center; border: 1px solid #e2e8f0; border-top: 3px solid #c9a84c; }
        .faixa-card .faixa { font-size: 11px; font-weight: 600; color: #1a2332; }
        .faixa-card .number { font-size: 18px; font-weight: 700; color: #1a2332; }
        .faixa-card .percentual { font-size: 9px; color: #94a3b8; }
        
        .table-responsive { overflow-x: auto; margin: 15px 0; }
        .table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .table thead { background: #1a2332; }
        .table thead th { padding: 6px 8px; text-align: left; font-weight: 600; color: #ffffff; border: 1px solid #1a2332; font-size: 9px; text-transform: uppercase; }
        .table tbody td { padding: 5px 8px; border: 1px solid #e2e8f0; vertical-align: middle; }
        .table tbody tr:nth-child(even) { background: #fafbfc; }
        .table .idade { font-weight: 700; text-align: center; }
        
        .status-badge { display: inline-block; padding: 1px 8px; border-radius: 8px; font-size: 8px; font-weight: 600; }
        .status-Matrícula { background: #dbeafe; color: #1e40af; }
        .status-Confirmação { background: #d1fae5; color: #065f46; }
        
        .footer { text-align: center; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 12px; font-size: 10px; color: #94a3b8; }
        .footer .assinatura { margin-top: 25px; padding-top: 15px; border-top: 1px solid #e2e8f0; }
        .footer .assinatura .linha { width: 250px; border-bottom: 1px solid #1a2332; margin: 25px auto 5px; }
        
        @media print { body { background: white; padding: 0; } .container { padding: 15px 20px; } .no-print { display: none !important; } }
        @media (max-width: 768px) { .faixas-grid { grid-template-columns: 1fr 1fr; } .stats-grid { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
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
        <div class="titulo-relatorio">RELATÓRIO DE ALUNOS POR IDADE</div>
        <div class="info-relatorio">
            <strong>Data:</strong> <?= date('d/m/Y H:i') ?> &nbsp;|&nbsp; 
            <strong>Total:</strong> <?= $totalAlunos ?> alunos
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="number"><?= $totalAlunos ?></div><div class="label">Total</div></div>
        <div class="stat-card"><div class="number"><?= $idadeMinima > 0 ? $idadeMinima : '-' ?></div><div class="label">Mínima</div></div>
        <div class="stat-card"><div class="number"><?= $idadeMaxima > 0 ? $idadeMaxima : '-' ?></div><div class="label">Máxima</div></div>
        <div class="stat-card"><div class="number"><?= $idadeMedia > 0 ? $idadeMedia : '-' ?></div><div class="label">Média</div></div>
    </div>

    <div class="faixas-grid">
        <?php foreach($faixasEtarias as $faixa => $total): 
            $percentual = $totalAlunos > 0 ? round(($total / $totalAlunos) * 100) : 0;
            $cores = ['0-5'=>'#3498db','6-10'=>'#2ecc71','11-15'=>'#f39c12','16-20'=>'#e67e22','21-25'=>'#9b59b6','26-30'=>'#1abc9c','31+'=>'#e74c3c'];
        ?>
        <div class="faixa-card" style="border-top-color: <?= $cores[$faixa] ?? '#c9a84c' ?>;">
            <div class="faixa"><?= $faixa ?></div>
            <div class="number"><?= $total ?></div>
            <div class="percentual"><?= $percentual ?>%</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>ID</th><th>Nome</th><th>Sexo</th><th>Idade</th><th>Classe</th><th>Curso</th><th>Turma</th><th>Situação</th></tr></thead>
            <tbody>
                <?php 
                try {
                    $alunos = $pdo->query("SELECT id, nome, Sexo, Idade, Classe, Curso, TURMA, Situacao_Cadastro FROM alunos WHERE Idade IS NOT NULL AND Idade > 0 ORDER BY Idade, nome")->fetchAll();
                } catch (Exception $e) { $alunos = []; }
                ?>
                <?php if (count($alunos) > 0): ?>
                    <?php foreach($alunos as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['id']) ?></td>
                        <td><?= htmlspecialchars($a['nome']) ?></td>
                        <td><?= $a['Sexo'] ?? '-' ?></td>
                        <td class="idade"><?= $a['Idade'] ?></td>
                        <td><?= htmlspecialchars($a['Classe'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($a['Curso'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($a['TURMA'] ?? '-') ?></td>
                        <td><span class="status-badge status-<?= $a['Situacao_Cadastro'] ?? 'Matrícula' ?>"><?= $a['Situacao_Cadastro'] ?? 'Matrícula' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;padding:20px;color:#94a3b8;">Nenhum aluno com idade registrada</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>Relatório gerado automaticamente pelo sistema SoftGest Web</p>
        <div class="assinatura">
            <div class="linha"></div>
            <p style="font-size:11px;color:#1a2332;font-weight:600;">_________________________________________</p>
            <p style="font-size:10px;color:#4a5568;">Assinatura do Diretor / Coordenador</p>
        </div>
        <p style="font-size:9px;color:#cbd5e1;margin-top:10px;">© <?= date('Y') ?> <?= htmlspecialchars($nomeEmpresa) ?> - Todos os direitos reservados</p>
    </div>
</div>

<div class="no-print" style="text-align:center;margin-top:20px;padding:15px;background:#f8fafc;border-radius:8px;max-width:210mm;margin-left:auto;margin-right:auto;">
    <button onclick="window.print()" style="padding:12px 40px;background:#c9a84c;color:#1a2332;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;box-shadow:0 2px 15px rgba(201,168,76,0.3);">
        🖨️ Imprimir / Salvar PDF
    </button>
    <a href="relatorio_idade.php" style="display:inline-block;margin-left:15px;padding:12px 25px;background:#f1f5f9;color:#4a5568;text-decoration:none;border-radius:8px;font-weight:600;">
        ← Voltar
    </a>
</div>

<script>window.onload=function(){setTimeout(function(){window.print();},500);};</script>
</body>
</html>