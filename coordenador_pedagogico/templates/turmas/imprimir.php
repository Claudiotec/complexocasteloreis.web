<?php
// ============================================
// modules/escola/alunos/add.php - Cadastrar Aluno
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para CRIAR
bloquearAcesso('criar');

// Resto do código...
?>




<?php
// ============================================
// modules/escola/turmas/imprimir.php - Imprimir Relatório
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
$turmas = [];
$totalAlunos = 0;
$totalTurmas = 0;

try {
    $turmas = $pdo->query("
        SELECT t.*, 
               (SELECT COUNT(*) FROM matriculas WHERE turma_id = t.id AND status = 'ativa') as total_alunos
        FROM turmas t
        ORDER BY t.classe, t.nome
    ")->fetchAll();
    
    $totalTurmas = count($turmas);
    foreach($turmas as $t) {
        $totalAlunos += $t['total_alunos'] ?? 0;
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
    <title>Relatório de Turmas - <?= htmlspecialchars($nomeEmpresa) ?></title>
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
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .stat-card .number {
            font-size: 22px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .stat-card .label {
            font-size: 11px;
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
            font-size: 11px;
        }
        
        .table thead {
            background: #1a2332;
        }
        
        .table thead th {
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            color: #ffffff;
            border: 1px solid #1a2332;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .table tbody tr:nth-child(even) {
            background: #fafbfc;
        }
        
        .table .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
        }
        
        .status-ativa {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-concluida {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-cancelada {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .turno-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 500;
        }
        
        .turno-manha { background: #fef3c7; color: #92400e; }
        .turno-tarde { background: #dbeafe; color: #1e40af; }
        .turno-noite { background: #e0e7ff; color: #3730a3; }
        .turno-integral { background: #d1fae5; color: #065f46; }
        
        /* ===== FOOTER ===== */
        .footer {
            text-align: center;
            margin-top: 25px;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            font-size: 10px;
            color: #94a3b8;
        }
        
        .footer .assinatura {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer .assinatura .linha {
            width: 250px;
            border-bottom: 1px solid #1a2332;
            margin: 30px auto 5px;
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
            .table {
                font-size: 10px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
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
            RELATÓRIO DE TURMAS
        </div>
        <div class="info-relatorio">
            <strong>Período:</strong> <?= date('Y') ?> &nbsp;|&nbsp; 
            <strong>Data de emissão:</strong> <?= date('d/m/Y H:i') ?> &nbsp;|&nbsp;
            <strong>Total de turmas:</strong> <?= $totalTurmas ?>
        </div>
    </div>

    <!-- ===== STATS ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?= $totalTurmas ?></div>
            <div class="label">Total de Turmas</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $totalAlunos ?></div>
            <div class="label">Total de Alunos</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $totalTurmas > 0 ? round($totalAlunos / $totalTurmas, 1) : 0 ?></div>
            <div class="label">Média por Turma</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= count(array_unique(array_column($turmas, 'classe'))) ?></div>
            <div class="label">Classes Diferentes</div>
        </div>
    </div>

    <!-- ===== TABELA ===== -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 5%;">ID</th>
                    <th style="width: 15%;">Turma</th>
                    <th style="width: 10%;">Classe</th>
                    <th style="width: 15%;">Curso</th>
                    <th style="width: 10%;">Turno</th>
                    <th style="width: 8%;">Sala</th>
                    <th style="width: 10%;">Idades</th>
                    <th style="width: 8%;">Limite</th>
                    <th style="width: 8%;">Alunos</th>
                    <th style="width: 8%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($turmas) > 0): ?>
                    <?php foreach($turmas as $t): ?>
                    <tr>
                        <td><?= $t['id'] ?></td>
                        <td><strong><?= htmlspecialchars($t['nome']) ?></strong></td>
                        <td><?= htmlspecialchars($t['classe'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($t['curso'] ?? '-') ?></td>
                        <td>
                            <span class="turno-badge turno-<?= $t['turno'] ?? 'manha' ?>">
                                <?= ucfirst($t['turno'] ?? 'Manhã') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($t['sala'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($t['idades'] ?? '-') ?></td>
                        <td><?= $t['limite'] ?? 30 ?></td>
                        <td><strong><?= $t['total_alunos'] ?? 0 ?></strong></td>
                        <td>
                            <span class="status-badge status-<?= $t['status'] ?? 'ativa' ?>">
                                <?= ucfirst($t['status'] ?? 'ativa') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 30px; color: #94a3b8;">
                            Nenhuma turma cadastrada.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
    <a href="index.php" style="display: inline-block; margin-left: 15px; padding: 12px 25px; background: #f1f5f9; color: #4a5568; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s;">
        ← Voltar
    </a>
</div>

<script>
    // Abrir a janela de impressão automaticamente
    window.onload = function() {
        // Pequeno delay para garantir que o conteúdo foi carregado
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>

</body>
</html>