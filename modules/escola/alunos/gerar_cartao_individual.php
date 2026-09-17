<?php
// ============================================
// modules/escola/alunos/gerar_cartao_individual.php - Gerar Cartão Individual (6 por página)
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

$id = $_GET['id'] ?? 0;
$aluno = null;

if ($id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
                   Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro,
                   Nome_do_Pai, Contacto4, foto
            FROM alunos 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $aluno = $stmt->fetch();
    } catch (Exception $e) {}
}

if (!$aluno) {
    header('Location: cartoes_escolares.php');
    exit;
}

// ===== DADOS DA EMPRESA =====
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'SoftGest Sistemas';

// ===== CARREGAR CONFIGURAÇÕES =====
$config_file = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/config_cartao.json';
$config = [
    'largura' => 90,
    'altura' => 95,
    'cor_fundo' => 'linear-gradient(135deg, #FF8C00, #FFA500)',
    'cor_texto' => '#1a2332',
    'cor_destaque' => '#FF8C00',
    'mostrar_controle_pagamentos' => true,
    'mostrar_codigo_barras' => true,
    'mostrar_ano_letivo' => true
];

if (file_exists($config_file)) {
    $json = file_get_contents($config_file);
    $config_salva = json_decode($json, true);
    if ($config_salva) {
        $config = array_merge($config, $config_salva);
    }
}

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

// ===== VERIFICAR FOTO =====
$foto_path = '';
$tem_foto = false;

if (!empty($aluno['foto'])) {
    $caminho_foto = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/' . $aluno['foto'];
    if (file_exists($caminho_foto)) {
        $tem_foto = true;
        $foto_path = SITE_URL . 'uploads/fotos_alunos/' . $aluno['foto'];
    }
}

if (!$tem_foto) {
    $pasta_fotos = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/';
    $arquivos = glob($pasta_fotos . $aluno['id'] . '.*');
    if (!empty($arquivos) && file_exists($arquivos[0])) {
        $tem_foto = true;
        $foto_path = SITE_URL . 'uploads/fotos_alunos/' . basename($arquivos[0]);
    }
}

// ===== FORMATAR DADOS =====
$data_nasc = (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0) ? 
    sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-';

$sexo = $aluno['Sexo'] ?? 'M';
$sexoLabel = $sexo == 'M' ? 'Masculino' : 'Feminino';

// ===== CALCULAR PROPORÇÕES =====
$proporcao_largura = $config['largura'] / 90;
$proporcao_altura = $config['altura'] / 95;
$tamanho_foto_w = intval(30 * $proporcao_largura);
$tamanho_foto_h = intval(35 * $proporcao_altura);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartão Individual - <?= htmlspecialchars($aluno['nome']) ?></title>
    <style>
        /* ============================================
           ESTILOS DO CARTÃO
           ============================================ */
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
            width: <?= $config['largura'] ?>mm;
            height: <?= $config['altura'] ?>mm;
            margin: 0 auto;
            perspective: 1000px;
        }
        
        .cartao {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transition: all 0.8s ease;
            border-radius: 3mm;
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
            border-radius: 3mm;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }
        
        .cartao-frente {
            background: <?= $config['cor_fundo'] ?>;
            color: <?= $config['cor_texto'] ?>;
        }
        
        .cartao-verso {
            background: #ffffff;
            color: #1a2332;
            transform: rotateY(180deg);
            border: 1px solid #e2e8f0;
        }
        
        .cabecalho {
            padding: <?= intval(2 * min($proporcao_largura, $proporcao_altura)) ?>mm;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            font-size: <?= intval(9 * min($proporcao_largura, $proporcao_altura)) ?>pt;
        }
        
        .cartao-verso .cabecalho {
            border-bottom: 2px solid <?= $config['cor_destaque'] ?>;
        }
        
        .cabecalho .logo {
            font-size: <?= intval(14 * min($proporcao_largura, $proporcao_altura)) ?>pt;
            font-weight: 800;
            color: <?= $config['cor_texto'] ?>;
        }
        
        .cabecalho .logo span {
            color: <?= $config['cor_destaque'] ?>;
        }
        
        .cabecalho .subtitulo {
            font-size: <?= intval(8 * min($proporcao_largura, $proporcao_altura)) ?>pt;
            color: <?= $config['cor_texto'] ?>;
            opacity: 0.8;
        }
        
        .cartao-verso .subtitulo {
            color: <?= $config['cor_destaque'] ?>;
            opacity: 1;
        }
        
        .corpo {
            padding: <?= intval(2 * min($proporcao_largura, $proporcao_altura)) ?>mm;
            flex-grow: 1;
            display: flex;
            align-items: center;
        }
        
        .foto {
            width: <?= $tamanho_foto_w ?>mm;
            height: <?= $tamanho_foto_h ?>mm;
            background: rgba(255,255,255,0.15);
            border: 1px dashed rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: <?= intval(3 * $proporcao_largura) ?>mm;
            font-size: <?= intval(8 * min($proporcao_largura, $proporcao_altura)) ?>pt;
            color: rgba(255,255,255,0.4);
            border-radius: 2mm;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cartao-verso .foto {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            color: #94a3b8;
        }
        
        .dados {
            flex: 1;
            font-size: <?= intval(9 * min($proporcao_largura, $proporcao_altura)) ?>pt;
        }
        
        .dados table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .dados th {
            text-align: left;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            padding: <?= intval(0.5 * min($proporcao_largura, $proporcao_altura)) ?>mm <?= intval(1 * min($proporcao_largura, $proporcao_altura)) ?>mm <?= intval(0.5 * min($proporcao_largura, $proporcao_altura)) ?>mm 0;
            font-size: <?= intval(7 * min($proporcao_largura, $proporcao_altura)) ?>pt;
            width: 25%;
        }
        
        .dados td {
            padding: <?= intval(0.5 * min($proporcao_largura, $proporcao_altura)) ?>mm 0;
            font-weight: 500;
        }
        
        .cartao-verso .dados th {
            color: #94a3b8;
        }
        
        .dados .nome-aluno {
            font-size: <?= intval(11 * min($proporcao_largura, $proporcao_altura)) ?>pt;
            font-weight: 700;
            color: #fff;
        }
        
        .cartao-verso .dados .nome-aluno {
            color: #1a2332;
        }
        
        .rodape {
            padding: <?= intval(1.5 * min($proporcao_largura, $proporcao_altura)) ?>mm;
            font-size: <?= intval(7 * min($proporcao_largura, $proporcao_altura)) ?>pt;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(255,255,255,0.2);
            flex-wrap: wrap;
        }
        
        .cartao-verso .rodape {
            border-top: 2px solid <?= $config['cor_destaque'] ?>;
        }
        
        .codigo-barras {
            font-family: 'Courier New', monospace;
            font-size: <?= intval(6 * min($proporcao_largura, $proporcao_altura)) ?>pt;
            background: rgba(255,255,255,0.1);
            padding: <?= intval(0.5 * min($proporcao_largura, $proporcao_altura)) ?>mm <?= intval(1 * min($proporcao_largura, $proporcao_altura)) ?>mm;
            border-radius: 1mm;
            letter-spacing: 1px;
        }
        
        .cartao-verso .codigo-barras {
            background: #f1f5f9;
        }
        
        .verso-corpo {
            padding: <?= intval(2 * min($proporcao_largura, $proporcao_altura)) ?>mm;
            flex-grow: 1;
            width: 100%;
        }
        
        .contato-encarregado {
            font-size: <?= intval(8 * min($proporcao_largura, $proporcao_altura)) ?>pt;
            margin-bottom: <?= intval(1.5 * min($proporcao_largura, $proporcao_altura)) ?>mm;
            padding-bottom: <?= intval(1.5 * min($proporcao_largura, $proporcao_altura)) ?>mm;
            border-bottom: 1px dashed <?= $config['cor_destaque'] ?>;
        }
        
        .contato-encarregado strong {
            color: <?= $config['cor_destaque'] ?>;
        }
        
        .tabelas-pagamento-container {
            display: flex;
            gap: <?= intval(1 * min($proporcao_largura, $proporcao_altura)) ?>mm;
            margin-top: <?= intval(1 * min($proporcao_largura, $proporcao_altura)) ?>mm;
        }
        
        .tabela-pagamentos {
            width: 100%;
            border-collapse: collapse;
            font-size: <?= intval(6 * min($proporcao_largura, $proporcao_altura)) ?>pt;
        }
        
        .tabela-pagamentos th {
            background: <?= $config['cor_destaque'] ?>;
            color: #fff;
            padding: <?= intval(0.5 * min($proporcao_largura, $proporcao_altura)) ?>mm;
            text-align: center;
            font-weight: 600;
            border: 0.5px solid <?= $config['cor_destaque'] ?>;
        }
        
        .tabela-pagamentos td {
            border: 0.5px solid #e2e8f0;
            padding: <?= intval(0.5 * min($proporcao_largura, $proporcao_altura)) ?>mm;
            text-align: center;
            height: <?= intval(4 * min($proporcao_largura, $proporcao_altura)) ?>mm;
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
                padding: 3mm;
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
    <button class="btn-print" onclick="window.print()">🖨️ Imprimir Cartão</button>
    <a href="cartoes_escolares.php" style="display: inline-block; margin-left: 10px; color: #4a5568; text-decoration: none;">← Voltar</a>
    <p style="margin-top: 5px; color: #94a3b8; font-size: 12px;"><?= count(range(1,6)) ?> cartões por página</p>
</div>

<div class="pagina">
    <?php for($i = 0; $i < 6; $i++): ?>
    <div class="cartao-container">
        <div class="cartao">
            <!-- ===== FRENTE ===== -->
            <div class="cartao-frente">
                <div class="cabecalho">
                    <div class="logo"><?= htmlspecialchars($nomeEmpresa) ?> <span>Web</span></div>
                    <div class="subtitulo">CARTÃO DE IDENTIFICAÇÃO ESCOLAR</div>
                </div>
                
                <div class="corpo">
                    <div class="foto">
                        <?php if ($tem_foto && $i == 0): ?>
                            <img src="<?= $foto_path ?>" alt="Foto">
                        <?php else: ?>
                            📷 FOTO
                        <?php endif; ?>
                    </div>
                    <div class="dados">
                        <table>
                            <tr>
                                <th>Nome:</th>
                                <td class="nome-aluno"><?= htmlspecialchars($aluno['nome']) ?></td>
                            </tr>
                            <tr>
                                <th>Nº:</th>
                                <td><?= htmlspecialchars($aluno['id']) ?></td>
                            </tr>
                            <tr>
                                <th>Classe:</th>
                                <td><?= htmlspecialchars($aluno['Classe'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>Turma:</th>
                                <td><?= htmlspecialchars($aluno['TURMA'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>Sala:</th>
                                <td><?= htmlspecialchars($aluno['SALA'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>Período:</th>
                                <td><?= htmlspecialchars($aluno['Periodo'] ?? '-') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="rodape">
                    <span>ID: <?= htmlspecialchars($aluno['id']) ?></span>
                    <?php if ($config['mostrar_codigo_barras']): ?>
                    <span class="codigo-barras"><?= htmlspecialchars($aluno['id']) ?></span>
                    <?php endif; ?>
                    <span><?= date('d/m/Y') ?></span>
                </div>
            </div>
            
            <!-- ===== VERSO ===== -->
            <div class="cartao-verso">
                <div class="cabecalho">
                    <div class="subtitulo">CONTROLE DE PAGAMENTOS</div>
                </div>
                
                <div class="corpo">
                    <div class="dados" style="display: flex; flex-direction: column; width: 100%;">
                        <div class="verso-corpo">
                            <div class="contato-encarregado">
                                <strong>Encarregado:</strong> <?= htmlspecialchars($aluno['Nome_do_Pai'] ?? 'Não informado') ?><br>
                                <strong>Contacto:</strong> <?= htmlspecialchars($aluno['Contacto4'] ?? 'Não informado') ?>
                            </div>
                            
                            <?php if ($config['mostrar_controle_pagamentos']): ?>
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
                            <?php endif; ?>
                            
                            <?php if ($config['mostrar_ano_letivo']): ?>
                            <div style="text-align: center; margin-top: <?= intval(2 * min($proporcao_largura, $proporcao_altura)) ?>mm; font-size: <?= intval(7 * min($proporcao_largura, $proporcao_altura)) ?>pt; color: #94a3b8;">
                                Ano Lectivo: <?= $ano_letivo ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="rodape">
                    <span>ID: <?= htmlspecialchars($aluno['id']) ?></span>
                    <?php if ($config['mostrar_ano_letivo']): ?>
                    <span>ANO: <?= $ano_letivo ?></span>
                    <?php endif; ?>
                    <span>Emitido: <?= date('d/m/Y') ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endfor; ?>
</div>

<script>
    window.onload = function() {
        // Não imprimir automaticamente
        console.log('🪪 Cartão individual gerado para: <?= htmlspecialchars($aluno['nome']) ?>');
    };
</script>

</body>
</html>