<?php
// ============================================
// modules/escola/alunos/gerar_comprovante.php - Gerar Comprovante de Matrícula
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';
require_once '../../../includes/upload_functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

$id = $_GET['id'] ?? 0;
$aluno = null;

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
        $stmt->execute([$id]);
        $aluno = $stmt->fetch();
    } catch (Exception $e) {}
}

if (!$aluno) {
    header('Location: index.php');
    exit;
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

// ===== FORMATAR DADOS =====
$data_nasc = ($aluno['dia'] ?? 0) . '/' . ($aluno['mes'] ?? 0) . '/' . ($aluno['Ano'] ?? 0);
$data_matricula = $aluno['Data_Matricula'] ? date('d/m/Y', strtotime($aluno['Data_Matricula'])) : '-';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante de Matrícula - <?= htmlspecialchars($aluno['nome']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; font-size: 12px; background: #ffffff; padding: 20px; color: #1a2332; }
        .container { max-width: 210mm; margin: 0 auto; background: white; padding: 30px 35px; box-shadow: 0 2px 20px rgba(0,0,0,0.1); }
        
        .header { text-align: center; border-bottom: 3px solid #1a2332; padding-bottom: 15px; margin-bottom: 20px; }
        .header .empresa-nome { font-size: 22px; font-weight: 800; color: #1a2332; text-transform: uppercase; letter-spacing: 1px; }
        .header .empresa-nome span { color: #c9a84c; }
        .header .empresa-dados { font-size: 11px; color: #4a5568; margin-top: 4px; }
        .header .empresa-nif { font-size: 12px; font-weight: 700; color: #1a2332; margin-top: 3px; }
        
        .titulo { text-align: center; margin: 15px 0; }
        .titulo h1 { font-size: 18px; font-weight: 700; color: #1a2332; letter-spacing: 3px; text-transform: uppercase; background: #c9a84c; color: #1a2332; padding: 6px 20px; display: inline-block; border-radius: 4px; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 30px; margin-top: 15px; }
        .info-item { display: flex; padding: 5px 0; border-bottom: 1px solid #f1f5f9; }
        .info-item .label { width: 120px; font-weight: 600; color: #4a5568; flex-shrink: 0; font-size: 11px; }
        .info-item .valor { color: #1a2332; font-weight: 500; font-size: 11px; }
        
        .section-title { font-size: 13px; font-weight: 700; color: #1a2332; margin: 12px 0 8px; padding-bottom: 5px; border-bottom: 2px solid #c9a84c; }
        
        .selo { text-align: center; margin: 15px 0; }
        .selo span { display: inline-block; border: 2px solid #c9a84c; padding: 8px 25px; border-radius: 8px; font-weight: 700; font-size: 14px; color: #c9a84c; letter-spacing: 2px; }
        
        .assinatura { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; text-align: center; }
        .assinatura .linha { border-bottom: 1px solid #1a2332; margin: 30px auto 5px; width: 200px; }
        .assinatura .label { font-size: 10px; color: #4a5568; }
        
        .footer { text-align: center; margin-top: 20px; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 12px; }
        
        @media print { body { background: white; padding: 0; } .container { box-shadow: none; padding: 20px 25px; } .no-print { display: none !important; } }
        @media (max-width: 768px) { .container { padding: 15px; } .info-grid { grid-template-columns: 1fr; gap: 0; } .assinatura { grid-template-columns: 1fr; gap: 10px; } }
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
    </div>

    <div class="titulo">
        <h1>📄 COMPROVANTE DE MATRÍCULA</h1>
    </div>

    <div class="selo">
        <span>✅ MATRÍCULA CONFIRMADA</span>
    </div>

    <div class="section-title">📌 Dados do Aluno</div>
    <div class="info-grid">
        <div class="info-item"><span class="label">Nº Processo:</span><span class="valor"><?= htmlspecialchars($aluno['id'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Nome Completo:</span><span class="valor"><?= htmlspecialchars($aluno['nome'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Sexo:</span><span class="valor"><?= $aluno['Sexo'] ?? '-' ?></span></div>
        <div class="info-item"><span class="label">Data Nascimento:</span><span class="valor"><?= $data_nasc ?></span></div>
        <div class="info-item"><span class="label">Idade:</span><span class="valor"><?= $aluno['Idade'] ?? '-' ?> anos</span></div>
        <div class="info-item"><span class="label">Contacto:</span><span class="valor"><?= htmlspecialchars($aluno['Contacto_do_Aluno'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Classe:</span><span class="valor"><?= htmlspecialchars($aluno['Classe'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Curso:</span><span class="valor"><?= htmlspecialchars($aluno['Curso'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Turma:</span><span class="valor"><?= htmlspecialchars($aluno['TURMA'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Sala:</span><span class="valor"><?= htmlspecialchars($aluno['SALA'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Período:</span><span class="valor"><?= htmlspecialchars($aluno['Periodo'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Data Matrícula:</span><span class="valor"><?= $data_matricula ?></span></div>
        <div class="info-item"><span class="label">Situação:</span><span class="valor">
            <span style="display:inline-block;padding:2px 12px;border-radius:10px;font-size:11px;font-weight:600;background:<?= ($aluno['Situacao_Cadastro'] ?? 'Matrícula') == 'Matrícula' ? '#dbeafe' : '#d1fae5' ?>;color:<?= ($aluno['Situacao_Cadastro'] ?? 'Matrícula') == 'Matrícula' ? '#1e40af' : '#065f46' ?>;">
                <?= $aluno['Situacao_Cadastro'] ?? 'Matrícula' ?>
            </span>
        </span></div>
    </div>

    <div class="section-title">👨‍👩‍👦 Dados do Encarregado</div>
    <div class="info-grid">
        <div class="info-item"><span class="label">Nome do Pai:</span><span class="valor"><?= htmlspecialchars($aluno['Nome_do_Pai'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Contacto do Pai:</span><span class="valor"><?= htmlspecialchars($aluno['Contacto4'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Nome da Mãe:</span><span class="valor"><?= htmlspecialchars($aluno['Nome_da_mae'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Contacto da Mãe:</span><span class="valor"><?= htmlspecialchars($aluno['Contacto_Mae'] ?? '-') ?></span></div>
    </div>

    <div class="assinatura">
        <div>
            <div class="linha"></div>
            <div class="label">Assinatura do Encarregado</div>
        </div>
        <div>
            <div class="linha"></div>
            <div class="label">Assinatura do Diretor</div>
        </div>
    </div>

    <div class="footer">
        <p>Documento emitido por sistema autorizado • <?= htmlspecialchars($nomeEmpresa) ?></p>
        <p style="margin-top: 3px;">Data de emissão: <?= date('d/m/Y H:i:s') ?></p>
        <p style="margin-top: 3px; font-size: 9px; color: #cbd5e1;">Este documento é um comprovante oficial de matrícula</p>
    </div>
</div>

<div class="no-print" style="text-align:center;margin-top:20px;padding:15px;background:#f8fafc;border-radius:8px;max-width:210mm;margin-left:auto;margin-right:auto;">
    <button onclick="window.print()" style="padding:12px 40px;background:#c9a84c;color:#1a2332;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;box-shadow:0 2px 15px rgba(201,168,76,0.3);">
        🖨️ Imprimir / Salvar PDF
    </button>
    <a href="view.php?id=<?= $aluno['id'] ?>" style="display:inline-block;margin-left:15px;padding:12px 25px;background:#f1f5f9;color:#4a5568;text-decoration:none;border-radius:8px;font-weight:600;">
        ← Voltar
    </a>
</div>

<script>window.onload=function(){setTimeout(function(){window.print();},500);};</script>
</body>
</html>