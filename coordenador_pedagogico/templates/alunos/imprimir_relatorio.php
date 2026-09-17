<?php
// ============================================
// modules/escola/alunos/imprimir_relatorio.php - Imprimir Relatório
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
$totalMatriculados = 0;
$totalConfirmados = 0;
$totalMasculino = 0;
$totalFeminino = 0;

try {
    $totalAlunos = $pdo->query("SELECT COUNT(*) FROM alunos")->fetchColumn() ?? 0;
    $totalMatriculados = $pdo->query("SELECT COUNT(*) FROM alunos WHERE Situacao_Cadastro = 'Matrícula'")->fetchColumn() ?? 0;
    $totalConfirmados = $pdo->query("SELECT COUNT(*) FROM alunos WHERE Situacao_Cadastro = 'Confirmação'")->fetchColumn() ?? 0;
    $totalMasculino = $pdo->query("SELECT COUNT(*) FROM alunos WHERE Sexo = 'M'")->fetchColumn() ?? 0;
    $totalFeminino = $pdo->query("SELECT COUNT(*) FROM alunos WHERE Sexo = 'F'")->fetchColumn() ?? 0;
} catch (Exception $e) {}

// ===== ALUNOS POR CLASSE =====
$alunosPorClasse = [];
try {
    $alunosPorClasse = $pdo->query("
        SELECT Classe, COUNT(*) as total 
        FROM alunos 
        GROUP BY Classe 
        ORDER BY Classe
    ")->fetchAll();
} catch (Exception $e) {}

// ===== ALUNOS POR CURSO =====
$alunosPorCurso = [];
try {
    $alunosPorCurso = $pdo->query("
        SELECT Curso, COUNT(*) as total 
        FROM alunos 
        GROUP BY Curso 
        ORDER BY Curso
    ")->fetchAll();
} catch (Exception $e) {}

// ===== ALUNOS POR TURMA =====
$alunosPorTurma = [];
try {
    $alunosPorTurma = $pdo->query("
        SELECT TURMA, COUNT(*) as total 
        FROM alunos 
        WHERE TURMA IS NOT NULL AND TURMA != ''
        GROUP BY TURMA 
        ORDER BY TURMA
    ")->fetchAll();
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
    <title>Relatório de Alunos - <?= htmlspecialchars($nomeEmpresa) ?></title>
    <style>
        /* ============================================
           ESTILOS PARA IMPRESSÃO
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 12px;
            background: #ffffff;
            padding: 20px;
            color: #1a2332;
        }
        
        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 25px 30px;
        }
        
        /* ===== HEADER ===== */
        .header {
            text-align: center;
            border-bottom: 3px solid #1a2332;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header .empresa-nome {
            font-size: 22px;
            font-weight: 800;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header .empresa-nome span {
            color: #c9a84c;
        }
        
        .header .empresa-dados {
            font-size: 11px;
            color: #4a5568;
            margin-top: 4px;
        }
        
        .header .empresa-dados .sep {
            margin: 0 6px;
            color: #c9a84c;
        }
        
        .header .empresa-nif {
            font-size: 12px;
            font-weight: 700;
            color: #1a2332;
            margin-top: 3px;
        }
        
        .header .titulo-relatorio {
            margin-top: 12px;
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        .header .info-relatorio {
            font-size: 12px;
            color: #4a5568;
            margin-top: 5px;
        }
        
        .header .info-relatorio strong {
            color: #1a2332;
        }
        
        /* ===== STATS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 15px 0 20px;
        }
        
        .stat-card {
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .stat-card .number {
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .stat-card .label {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 2px;
        }
        
        /* ===== TABELA ===== */
        .table-responsive {
            overflow-x: auto;
            margin: 15px 0;
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
            padding: 6px 8px;
            text-align: left;
            font-weight: 600;
            color: #ffffff;
            border: 1px solid #1a2332;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .table tbody tr:nth-child(even) {
            background: #fafbfc;
        }
        
        .table .status-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 8px;
            font-size: 8px;
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
        
        /* ===== RESUMO ===== */
        .resumo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        
        .resumo-box {
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .resumo-box h4 {
            font-size: 11px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }
        
        .resumo-box .item {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .resumo-box .item:last-child {
            border-bottom: none;
        }
        
        .resumo-box .item .label {
            color: #4a5568;
        }
        
        .resumo-box .item .value {
            font-weight: 600;
            color: #1a2332;
        }
        
        /* ===== FOOTER ===== */
        .footer {
            text-align: center;
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            font-size: 10px;
            color: #94a3b8;
        }
        
        .footer .assinatura {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer .assinatura .linha {
            width: 250px;
            border-bottom: 1px solid #1a2332;
            margin: 25px auto 5px;
        }
        
        /* ============================================
           RESPONSIVO
           ============================================ */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .container {
                padding: 15px 20px;
            }
            .no-print {
                display: none !important;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .resumo-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- ===== HEADER ===== -->
    <div class="header">
        <div class="empresa-nome">
            <?= htmlspecialchars($nomeEmpresa) ?> <span>Web</span>
        </div>
        <div class="empresa-dados">
            <?= htmlspecialchars($enderecoEmpresa) ?>
            <?php if ($telefoneEmpresa): ?>
            <span class="sep">|</span>
            Tel: <?= htmlspecialchars($telefoneEmpresa) ?>
            <?php endif; ?>
            <?php if ($emailEmpresa): ?>
            <span class="sep">|</span>
            Email: <?= htmlspecialchars($emailEmpresa) ?>
            <?php endif; ?>
        </div>
        <div class="empresa-nif">
            NIF: <?= htmlspecialchars($nifEmpresa) ?>
        </div>
        <div class="titulo-relatorio">
            RELATÓRIO DE ALUNOS
        </div>
        <div class="info-relatorio">
            <strong>Período:</strong> <?= date('Y') ?> &nbsp;|&nbsp; 
            <strong>Data de emissão:</strong> <?= date('d/m/Y H:i') ?> &nbsp;|&nbsp;
            <strong>Total de alunos:</strong> <?= $totalAlunos ?>
        </div>
    </div>

    <!-- ===== STATS ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?= $totalAlunos ?></div>
            <div class="label">Total Alunos</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $totalMatriculados ?></div>
            <div class="label">Matriculados</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $totalConfirmados ?></div>
            <div class="label">Confirmados</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $totalMasculino ?></div>
            <div class="label">Masculino</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $totalFeminino ?></div>
            <div class="label">Feminino</div>
        </div>
    </div>

    <!-- ===== TABELA DE ALUNOS ===== -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 25%;">Nome</th>
                    <th style="width: 6%;">Sexo</th>
                    <th style="width: 6%;">Idade</th>
                    <th style="width: 10%;">Classe</th>
                    <th style="width: 15%;">Curso</th>
                    <th style="width: 10%;">Turma</th>
                    <th style="width: 10%;">Período</th>
                    <th style="width: 10%;">Situação</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Buscar todos os alunos para a tabela
                try {
                    $stmt = $pdo->query("
                        SELECT id, nome, Sexo, Idade, Classe, Curso, TURMA, Periodo, Situacao_Cadastro
                        FROM alunos 
                        ORDER BY nome
                    ");
                    $alunosLista = $stmt->fetchAll();
                } catch (Exception $e) {
                    $alunosLista = [];
                }
                ?>
                <?php if (count($alunosLista) > 0): ?>
                    <?php foreach($alunosLista as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['id']) ?></td>
                        <td><?= htmlspecialchars($a['nome']) ?></td>
                        <td><?= $a['Sexo'] ?? '-' ?></td>
                        <td><?= $a['Idade'] ?? '-' ?></td>
                        <td><?= htmlspecialchars($a['Classe'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($a['Curso'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($a['TURMA'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($a['Periodo'] ?? '-') ?></td>
                        <td>
                            <span class="status-badge status-<?= $a['Situacao_Cadastro'] ?? 'Matrícula' ?>">
                                <?= $a['Situacao_Cadastro'] ?? 'Matrícula' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px; color: #94a3b8;">
                            Nenhum aluno cadastrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ===== RESUMO ===== -->
    <div class="resumo-grid">
        <div class="resumo-box">
            <h4>📊 Por Classe</h4>
            <?php if (count($alunosPorClasse) > 0): ?>
                <?php foreach($alunosPorClasse as $item): ?>
                <div class="item">
                    <span class="label"><?= htmlspecialchars($item['Classe'] ?? 'N/A') ?></span>
                    <span class="value"><?= $item['total'] ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #94a3b8; font-size: 10px; text-align: center; padding: 10px;">Nenhum dado</p>
            <?php endif; ?>
        </div>

        <div class="resumo-box">
            <h4>📚 Por Curso</h4>
            <?php if (count($alunosPorCurso) > 0): ?>
                <?php foreach($alunosPorCurso as $item): ?>
                <div class="item">
                    <span class="label"><?= htmlspecialchars($item['Curso'] ?? 'N/A') ?></span>
                    <span class="value"><?= $item['total'] ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #94a3b8; font-size: 10px; text-align: center; padding: 10px;">Nenhum dado</p>
            <?php endif; ?>
        </div>

        <div class="resumo-box">
            <h4>🏫 Por Turma</h4>
            <?php if (count($alunosPorTurma) > 0): ?>
                <?php foreach($alunosPorTurma as $item): ?>
                <div class="item">
                    <span class="label"><?= htmlspecialchars($item['TURMA'] ?? 'N/A') ?></span>
                    <span class="value"><?= $item['total'] ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #94a3b8; font-size: 10px; text-align: center; padding: 10px;">Nenhum dado</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== ASSINATURA ===== -->
    <div class="footer">
        <p>
            Relatório gerado automaticamente pelo sistema SoftGest Web<br>
            Documento impresso em <?= date('d/m/Y à\s H:i') ?>
        </p>
        
        <div class="assinatura">
            <div class="linha"></div>
            <p style="font-size: 11px; color: #1a2332; font-weight: 600;">
                _________________________________________
            </p>
            <p style="font-size: 10px; color: #4a5568;">
                Assinatura do Diretor / Coordenador
            </p>
            <p style="font-size: 9px; color: #94a3b8; margin-top: 3px;">
                Carimbo da Instituição
            </p>
        </div>
        
        <div style="margin-top: 10px; font-size: 9px; color: #cbd5e1;">
            © <?= date('Y') ?> <?= htmlspecialchars($nomeEmpresa) ?> - Todos os direitos reservados
        </div>
    </div>
</div>

<!-- ===== BOTÃO IMPRIMIR ===== -->
<div class="no-print" style="text-align: center; margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; max-width: 210mm; margin-left: auto; margin-right: auto;">
    <button onclick="window.print()" style="padding: 12px 40px; background: #c9a84c; color: #1a2332; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 15px rgba(201, 168, 76, 0.3);">
        🖨️ Imprimir / Salvar PDF
    </button>
    <a href="relatorio.php" style="display: inline-block; margin-left: 15px; padding: 12px 25px; background: #f1f5f9; color: #4a5568; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s;">
        ← Voltar
    </a>
</div>

<script>
    // Abrir a janela de impressão automaticamente
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>

</body>
</html>