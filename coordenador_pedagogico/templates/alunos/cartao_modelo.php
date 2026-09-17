<?php
// ============================================
// modules/escola/alunos/cartao_modelo.php - Cartão Modelo em Branco
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

// ===== DADOS DA EMPRESA =====
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'SoftGest Sistemas';

// ===== CALCULAR ANO LETIVO =====
$mes_atual = date('m');
$ano_atual = date('Y');
if ($mes_atual >= 9) {
    $ano_letivo = $ano_atual . '/' . ($ano_atual + 1);
} else {
    $ano_letivo = ($ano_atual - 1) . '/' . $ano_atual;
}

$meses = ['SET', 'OUT', 'NOV', 'DEZ', 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL'];
$metade = ceil(count($meses) / 2);
$meses_coluna1 = array_slice($meses, 0, $metade);
$meses_coluna2 = array_slice($meses, $metade);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartão Modelo - <?= htmlspecialchars($nomeEmpresa) ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            margin: 0;
            padding: 10px;
            background: #f5f5f5;
        }
        
        .pagina {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 5mm;
            padding: 5mm;
            max-width: 297mm;
            margin: 0 auto;
            background: white;
            border-radius: 4mm;
            box-shadow: 0 2mm 4mm rgba(0,0,0,0.1);
        }
        
        .cartao-container {
            width: 90mm;
            height: 95mm;
            margin: 0 auto;
            perspective: 1000px;
        }
        
        .cartao {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transition: all 0.8s ease;
            border-radius: 4mm;
            box-shadow: 0 1mm 2mm rgba(0,0,0,0.15);
        }
        
        .cartao:hover {
            transform: rotateY(180deg);
        }
        
        .cartao-frente, .cartao-verso {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: 4mm;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }
        
        .cartao-frente {
            background: linear-gradient(135deg, #FF8C00, #FFA500);
            color: #1a2332;
        }
        
        .cartao-verso {
            background: #ffffff;
            color: #1a2332;
            transform: rotateY(180deg);
            border: 1px solid #e2e8f0;
        }
        
        .cabecalho {
            padding: 2mm;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            font-size: 8pt;
        }
        
        .cartao-verso .cabecalho {
            border-bottom: 2px solid #FF8C00;
        }
        
        .cabecalho .logo {
            font-size: 12pt;
            font-weight: 800;
            color: #1a2332;
        }
        
        .cabecalho .logo span {
            color: #fff;
        }
        
        .cabecalho .subtitulo {
            font-size: 7pt;
            color: rgba(255,255,255,0.8);
        }
        
        .cartao-verso .subtitulo {
            color: #FF8C00;
        }
        
        .corpo {
            padding: 2mm;
            flex-grow: 1;
            display: flex;
        }
        
        .foto {
            width: 25mm;
            height: 30mm;
            background: rgba(255,255,255,0.15);
            border: 1px dashed rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 2mm;
            font-size: 7pt;
            color: rgba(255,255,255,0.4);
            border-radius: 2mm;
            flex-shrink: 0;
        }
        
        .cartao-verso .foto {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            color: #94a3b8;
        }
        
        .dados {
            flex: 1;
            font-size: 8pt;
        }
        
        .dados table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .dados th {
            text-align: left;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            padding: 0.5mm 1mm 0.5mm 0;
            font-size: 6pt;
            width: 20%;
        }
        
        .dados td {
            padding: 0.5mm 0;
            font-weight: 500;
        }
        
        .cartao-verso .dados th {
            color: #94a3b8;
        }
        
        .dados .nome-aluno {
            font-size: 10pt;
            font-weight: 700;
            color: #fff;
        }
        
        .cartao-verso .dados .nome-aluno {
            color: #1a2332;
        }
        
        .rodape {
            padding: 1.5mm;
            font-size: 6pt;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .cartao-verso .rodape {
            border-top: 2px solid #FF8C00;
        }
        
        .codigo-barras {
            font-family: 'Courier New', monospace;
            font-size: 5pt;
            background: rgba(255,255,255,0.1);
            padding: 0.5mm 1mm;
            border-radius: 1mm;
            letter-spacing: 1px;
        }
        
        .cartao-verso .codigo-barras {
            background: #f1f5f9;
        }
        
        .verso-corpo {
            padding: 2mm;
            flex-grow: 1;
        }
        
        .contato-encarregado {
            font-size: 7pt;
            margin-bottom: 1.5mm;
            padding-bottom: 1.5mm;
            border-bottom: 1px dashed #FF8C00;
        }
        
        .contato-encarregado strong {
            color: #FF8C00;
        }
        
        .tabelas-pagamento-container {
            display: flex;
            gap: 1mm;
            margin-top: 1mm;
        }
        
        .tabela-pagamentos {
            width: 100%;
            border-collapse: collapse;
            font-size: 5pt;
        }
        
        .tabela-pagamentos th {
            background: #FF8C00;
            color: #fff;
            padding: 0.5mm;
            text-align: center;
            font-weight: 600;
            border: 0.5px solid #FF8C00;
            font-size: 5pt;
        }
        
        .tabela-pagamentos td {
            border: 0.5px solid #e2e8f0;
            padding: 0.5mm;
            text-align: center;
            height: 3.5mm;
        }
        
        .no-print {
            text-align: center;
            padding: 15px;
            max-width: 297mm;
            margin: 0 auto 15px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .no-print .btn-print {
            padding: 10px 30px;
            background: #c9a84c;
            color: #1a2332;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .no-print .btn-print:hover {
            background: #b8973a;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .pagina {
                box-shadow: none;
                border-radius: 0;
                padding: 2mm;
                gap: 3mm;
            }
            .cartao:hover {
                transform: none !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimir Cartões Modelo</button>
    <a href="cartoes_escolares.php" style="display: inline-block; margin-left: 10px; color: #4a5568; text-decoration: none;">← Voltar</a>
    <p style="margin-top: 5px; color: #94a3b8; font-size: 12px;">6 cartões por página - Preencher manualmente</p>
</div>

<div class="pagina">
    <?php for($i = 0; $i < 6; $i++): ?>
    <div class="cartao-container">
        <div class="cartao">
            <!-- FRENTE -->
            <div class="cartao-frente">
                <div class="cabecalho">
                    <div class="logo"><?= htmlspecialchars($nomeEmpresa) ?> <span>Web</span></div>
                    <div class="subtitulo">CARTÃO DE IDENTIFICAÇÃO ESCOLAR</div>
                </div>
                
                <div class="corpo">
                    <div class="foto">📷 FOTO</div>
                    <div class="dados">
                        <table>
                            <tr><th>Nome:</th><td class="nome-aluno">______________________</td></tr>
                            <tr><th>Nº:</th><td>___________</td></tr>
                            <tr><th>Classe:</th><td>___________</td></tr>
                            <tr><th>Turma:</th><td>___________</td></tr>
                            <tr><th>Sala:</th><td>___________</td></tr>
                            <tr><th>Período:</th><td>___________</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="rodape">
                    <span>ID: _________</span>
                    <span class="codigo-barras">___ ___ ___ ___</span>
                    <span>__/__/____</span>
                </div>
            </div>
            
            <!-- VERSO -->
            <div class="cartao-verso">
                <div class="cabecalho">
                    <div class="subtitulo">CONTROLE DE PAGAMENTOS</div>
                </div>
                
                <div class="corpo">
                    <div class="dados">
                        <div class="verso-corpo">
                            <div class="contato-encarregado">
                                <strong>Encarregado:</strong> ___________________________<br>
                                <strong>Contacto:</strong> ___________________
                            </div>
                            
                            <div class="tabelas-pagamento-container">
                                <table class="tabela-pagamentos">
                                    <thead>
                                        <tr><th colspan="3">PAGAMENTOS MENSAIS</th></tr>
                                        <tr><th>Mês</th><th>Propina</th><th>Transporte</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($meses_coluna1 as $mes): ?>
                                        <tr><td><?= $mes ?></td><td></td><td></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <table class="tabela-pagamentos">
                                    <thead>
                                        <tr><th colspan="3">PAGAMENTOS MENSAIS</th></tr>
                                        <tr><th>Mês</th><th>Propina</th><th>Transporte</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($meses_coluna2 as $mes): ?>
                                        <tr><td><?= $mes ?></td><td></td><td></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="rodape">
                    <span>ID: _________</span>
                    <span>ANO: <?= $ano_letivo ?></span>
                    <span>Emitido: __/__/____</span>
                </div>
            </div>
        </div>
    </div>
    <?php endfor; ?>
</div>

</body>
</html>