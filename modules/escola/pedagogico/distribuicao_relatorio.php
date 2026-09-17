<?php
// modules/escola/index.php
// Ou esta versão (mais robusta):
require_once(__DIR__ . '/../includes/verificar_permissao_escola.php');

$permissoes = verificarMultiplasPermissoesEscola('escola', ['visualizar', 'criar', 'editar', 'excluir']);

if ($permissoes['visualizar']) {
    // Mostra conteúdo
}

if ($permissoes['criar']) {
    // Mostra botão criar
}
?>



<?php
// ============================================
// modules/escola/pedagogico/distribuicao_relatorio.php - Relatório HTML
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

// ===== BUSCAR DADOS =====
$distribuicoes = [];
try {
    $distribuicoes = $pdo->query("
        SELECT d.*, 
               CASE d.tipo 
                   WHEN 'DIRETOR_TURMA' THEN 'Diretor de Turma'
                   WHEN 'COORDENADOR' THEN 'Coordenador'
                   ELSE 'Professor'
               END as funcao
        FROM distribuicao_professores d
        ORDER BY d.tipo, d.professor_nome, d.classe, d.turma_nome
    ")->fetchAll();
} catch (Exception $e) {}

// ===== ESTATÍSTICAS =====
$stats = [];
try {
    $stats = $pdo->query("
        SELECT tipo, COUNT(*) as total 
        FROM distribuicao_professores 
        GROUP BY tipo
    ")->fetchAll();
} catch (Exception $e) {}

// ===== DADOS DA EMPRESA =====
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'SoftGest Sistemas';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Distribuição - <?= htmlspecialchars($nomeEmpresa) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; font-size: 12px; background: #ffffff; padding: 20px; color: #1a2332; }
        .container { max-width: 210mm; margin: 0 auto; background: white; padding: 25px 30px; }
        .header { text-align: center; border-bottom: 3px solid #1a2332; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 22px; font-weight: 800; color: #1a2332; text-transform: uppercase; }
        .header h1 span { color: #c9a84c; }
        .header .sub { font-size: 11px; color: #4a5568; margin-top: 4px; }
        .header .data { font-size: 11px; color: #94a3b8; margin-top: 2px; }
        
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 15px 0 20px; }
        .stat-card { background: #f8fafc; padding: 10px 12px; border-radius: 6px; text-align: center; border: 1px solid #e2e8f0; }
        .stat-card .num { font-size: 20px; font-weight: 700; color: #1a2332; }
        .stat-card .label { font-size: 10px; color: #94a3b8; margin-top: 2px; }
        
        .table { width: 100%; border-collapse: collapse; font-size: 10px; margin: 15px 0; }
        .table thead { background: #1a2332; }
        .table thead th { padding: 6px 8px; text-align: left; font-weight: 600; color: #ffffff; border: 1px solid #1a2332; font-size: 9px; text-transform: uppercase; }
        .table tbody td { padding: 5px 8px; border: 1px solid #e2e8f0; vertical-align: middle; }
        .table tbody tr:nth-child(even) { background: #fafbfc; }
        
        .badge { display: inline-block; padding: 1px 8px; border-radius: 8px; font-size: 8px; font-weight: 600; }
        .badge-professor { background: #dbeafe; color: #1e40af; }
        .badge-coordenador { background: #fef3c7; color: #92400e; }
        .badge-diretor { background: #d1fae5; color: #065f46; }
        
        .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        @media print { body { background: white; padding: 0; } .container { padding: 15px 20px; } .no-print { display: none !important; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><?= htmlspecialchars($nomeEmpresa) ?> <span>Web</span></h1>
        <div class="sub">RELATÓRIO DE DISTRIBUIÇÃO DE PROFESSORES</div>
        <div class="data">Data de emissão: <?= date('d/m/Y H:i:s') ?></div>
    </div>

    <div class="stats">
        <?php 
        $total = 0;
        foreach($stats as $s) $total += $s['total'];
        ?>
        <div class="stat-card"><div class="num"><?= $total ?></div><div class="label">Total</div></div>
        <?php foreach($stats as $s): 
            $label = $s['tipo'] == 'DIRETOR_TURMA' ? 'Diretores' : ($s['tipo'] == 'COORDENADOR' ? 'Coordenadores' : 'Professores');
        ?>
        <div class="stat-card"><div class="num"><?= $s['total'] ?></div><div class="label"><?= $label ?></div></div>
        <?php endforeach; ?>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Professor</th>
                <th>Turma</th>
                <th>Classe</th>
                <th>Disciplinas/Obs.</th>
                <th>Ano</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($distribuicoes) > 0): ?>
                <?php foreach($distribuicoes as $d): ?>
                <tr>
                    <td><span class="badge badge-<?= strtolower($d['tipo'] == 'PROFESSOR' ? 'professor' : ($d['tipo'] == 'COORDENADOR' ? 'coordenador' : 'diretor')) ?>"><?= $d['funcao'] ?></span></td>
                    <td><?= htmlspecialchars($d['professor_nome']) ?></td>
                    <td><?= htmlspecialchars($d['turma_nome'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($d['classe'] ?: '-') ?></td>
                    <td><?= htmlspecialchars(substr($d['disciplinas'], 0, 40)) ?></td>
                    <td><?= htmlspecialchars($d['ano_letivo']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">Nenhuma distribuição cadastrada</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Documento emitido por sistema autorizado • <?= htmlspecialchars($nomeEmpresa) ?></p>
        <p style="margin-top: 2px;">Data de emissão: <?= date('d/m/Y H:i:s') ?></p>
    </div>
</div>

<div class="no-print" style="text-align:center;margin-top:15px;padding:12px;background:#f8fafc;border-radius:8px;max-width:210mm;margin-left:auto;margin-right:auto;">
    <button onclick="window.print()" style="padding:10px 35px;background:#c9a84c;color:#1a2332;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;">🖨️ Imprimir / Salvar PDF</button>
    <a href="distribuicao.php" style="display:inline-block;margin-left:12px;padding:10px 20px;background:#f1f5f9;color:#4a5568;text-decoration:none;border-radius:8px;font-weight:600;">← Voltar</a>
</div>

<script>window.onload=function(){setTimeout(function(){window.print();},500);};</script>
</body>
</html>