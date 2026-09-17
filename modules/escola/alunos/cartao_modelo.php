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

// ============================================
// CONFIGURAÇÕES DO CARTÃO (MESMAS DO gerar_cartao.php)
// ============================================
$config_default = [
    'cor_primaria' => '#1a2a3a',
    'cor_secundaria' => '#d4a843',
    'cor_fundo' => '#ffffff',
    'cor_texto' => '#1a2a3a',
    'cor_cabecalho' => '#1a2a3a',
    'cor_rodape' => '#f5f0eb',
    'tamanho_largura' => '85',
    'tamanho_altura' => '120',
    'tamanho_fonte' => '10',
    'borda_arredondada' => '6',
    'mostrar_foto' => 'sim',
    'mostrar_verso' => 'sim',
    'mostrar_info_importante' => 'sim',
    'fundo_decorado' => 'sim',
    'layout' => 'classico',
    'logo_upload' => '',
    'logo_fundo' => '',
    'foto_largura' => '22',
    'foto_altura' => '28',
    'nome_cor' => '#d4a843',
    'nome_tamanho' => '14',
    'nome_peso' => '700',
    'nome_maiusculo' => 'sim',
    'classe_cor' => '#1a2a3a',
    'classe_tamanho' => '11',
    'classe_peso' => '600',
    'mostrar_linha_ondulada' => 'sim',
    'linha_ondulada_cor' => '#d4a843',
    'linha_ondulada_altura' => '3',
    'linha_ondulada_posicao' => 'antes_nome',
    'linha_ondulada_offset_y' => '0',
    'linha_ondulada_curvatura' => '1',
    'mostrar_qr_code_frente' => 'sim',
    'qr_code_tamanho' => '12',
    'mostrar_assinatura_frente' => 'sim',
    'assinatura_frente_nome' => 'Dr. Carlos Manuel Reis',
    'assinatura_frente_tamanho' => '0.35',
    'mostrar_assinatura_verso' => 'sim',
    'assinatura_verso_nome' => 'Dr. Carlos Manuel Reis',
    'assinatura_verso_tamanho' => '0.55',
    'assinatura_verso_cargo' => 'DIRECTOR DA ESCOLA',
    'tabela_altura_linha' => '3.5',
    'tabela_distribuicao' => '1.2',
    'verso_offset_x' => '0',
    'verso_offset_y' => '0',
    'ano_letivo' => ''
];

if (isset($_SESSION['cartao_config'])) {
    $config = array_merge($config_default, $_SESSION['cartao_config']);
} else {
    $config = $config_default;
}

// ============================================
// DADOS DA EMPRESA
// ============================================
$empresa = [];
try {
    $pdo = conectarBanco();
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'COMPLEXO ESCOLAR PRIVADO CASTELO REIS & FILHOS';
$enderecoEmpresa = $empresa['endereco'] ?? 'Município do Bom Jesus – Km 44, Desvio';
$cidadeEmpresa = $empresa['cidade'] ?? 'Província de Ícolo e Bengo – Angola';
$telefoneEmpresa = $empresa['telefone'] ?? '923 456 789 / 934 567 890';
$emailEmpresa = $empresa['email'] ?? 'www.casteloreisfilhos.ao';
$telefone2 = '911 222 333';

// ============================================
// CALCULAR ANO LETIVO
// ============================================
if (!empty($config['ano_letivo'])) {
    $ano_letivo = $config['ano_letivo'];
} else {
    $mes_atual = date('m');
    $ano_atual = date('Y');
    if ($mes_atual >= 9) {
        $ano_letivo = $ano_atual . ' / ' . ($ano_atual + 1);
    } else {
        $ano_letivo = ($ano_atual - 1) . ' / ' . $ano_atual;
    }
}

$meses = ['SET', 'OUT', 'NOV', 'DEZ', 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL'];
$metade = ceil(count($meses) / 2);
$meses_coluna1 = array_slice($meses, 0, $metade);
$meses_coluna2 = array_slice($meses, $metade);

// ============================================
// FUNÇÃO PARA GERAR LINHA ONDULADA
// ============================================
function gerarLinhaOnduladaModelo($cor, $altura, $offset_y = 0, $curvatura = 1) {
    $altura_px = $altura * 1.5;
    $offset_px = $offset_y * 1.5;
    $curvatura_ajustada = $curvatura * 8;
    
    $html = '
    <svg viewBox="0 0 200 20" xmlns="http://www.w3.org/2000/svg" 
         style="width:100%;height:' . $altura_px . 'mm;display:block;">
        <defs>
            <linearGradient id="gradOnda" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="' . $cor . '" stop-opacity="0.2"/>
                <stop offset="30%" stop-color="' . $cor . '" stop-opacity="1"/>
                <stop offset="70%" stop-color="' . $cor . '" stop-opacity="1"/>
                <stop offset="100%" stop-color="' . $cor . '" stop-opacity="0.2"/>
            </linearGradient>
        </defs>
        <path d="M 0 ' . (10 + $offset_px) . ' 
                 C ' . (15 + $curvatura_ajustada) . ' ' . (2 + $offset_px) . ', 
                   ' . (25 - $curvatura_ajustada) . ' ' . (18 + $offset_px) . ', 
                   40 ' . (10 + $offset_px) . ' 
                 C ' . (55 + $curvatura_ajustada) . ' ' . (2 + $offset_px) . ', 
                   ' . (65 - $curvatura_ajustada) . ' ' . (18 + $offset_px) . ', 
                   80 ' . (10 + $offset_px) . ' 
                 C ' . (95 + $curvatura_ajustada) . ' ' . (2 + $offset_px) . ', 
                   ' . (105 - $curvatura_ajustada) . ' ' . (18 + $offset_px) . ', 
                   120 ' . (10 + $offset_px) . ' 
                 C ' . (135 + $curvatura_ajustada) . ' ' . (2 + $offset_px) . ', 
                   ' . (145 - $curvatura_ajustada) . ' ' . (18 + $offset_px) . ', 
                   160 ' . (10 + $offset_px) . ' 
                 C ' . (175 + $curvatura_ajustada) . ' ' . (2 + $offset_px) . ', 
                   ' . (185 - $curvatura_ajustada) . ' ' . (18 + $offset_px) . ', 
                   200 ' . (10 + $offset_px) . '" 
              stroke="' . $cor . '" 
              stroke-width="2.8" 
              fill="none" 
              opacity="0.9"/>
        <path d="M 0 ' . (13 + $offset_px) . ' 
                 C ' . (15 + $curvatura_ajustada) . ' ' . (5 + $offset_px) . ', 
                   ' . (25 - $curvatura_ajustada) . ' ' . (21 + $offset_px) . ', 
                   40 ' . (13 + $offset_px) . ' 
                 C ' . (55 + $curvatura_ajustada) . ' ' . (5 + $offset_px) . ', 
                   ' . (65 - $curvatura_ajustada) . ' ' . (21 + $offset_px) . ', 
                   80 ' . (13 + $offset_px) . ' 
                 C ' . (95 + $curvatura_ajustada) . ' ' . (5 + $offset_px) . ', 
                   ' . (105 - $curvatura_ajustada) . ' ' . (21 + $offset_px) . ', 
                   120 ' . (13 + $offset_px) . ' 
                 C ' . (135 + $curvatura_ajustada) . ' ' . (5 + $offset_px) . ', 
                   ' . (145 - $curvatura_ajustada) . ' ' . (21 + $offset_px) . ', 
                   160 ' . (13 + $offset_px) . ' 
                 C ' . (175 + $curvatura_ajustada) . ' ' . (5 + $offset_px) . ', 
                   ' . (185 - $curvatura_ajustada) . ' ' . (21 + $offset_px) . ', 
                   200 ' . (13 + $offset_px) . '" 
              stroke="' . $cor . '" 
              stroke-width="1.2" 
              fill="none" 
              opacity="0.3"/>
        <circle cx="10" cy="' . (10 + $offset_px) . '" r="3" fill="' . $cor . '" opacity="0.5"/>
        <circle cx="190" cy="' . (10 + $offset_px) . '" r="3" fill="' . $cor . '" opacity="0.5"/>
    </svg>';
    
    return $html;
}

$linha_ondulada_html = '';
if ($config['mostrar_linha_ondulada'] == 'sim') {
    $linha_ondulada_html = '<div class="linha-ondulada-container" style="padding:0 2mm;margin:0.2mm 0;">' . 
        gerarLinhaOnduladaModelo($config['linha_ondulada_cor'], $config['linha_ondulada_altura'], $config['linha_ondulada_offset_y'], $config['linha_ondulada_curvatura']) . 
        '</div>';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartão Modelo - <?= htmlspecialchars($nomeEmpresa) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 4mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            background: #e8e8e8;
            padding: 0;
            margin: 0;
        }
        
        .no-print {
            text-align: center;
            padding: 15px;
            max-width: 210mm;
            margin: 0 auto 15px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .no-print .btn-print {
            padding: 10px 30px;
            background: <?= $config['cor_secundaria'] ?>;
            color: <?= $config['cor_texto'] ?>;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .no-print .btn-print:hover {
            background: #c9a84c;
        }
        
        .no-print a {
            display: inline-block;
            margin-left: 10px;
            color: #4a5568;
            text-decoration: none;
        }
        
        .pagina {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: auto repeat(3, 1fr);
            gap: 4mm;
            padding: 4mm;
            width: 100%;
            max-width: 210mm;
            min-height: 297mm;
            height: 297mm;
            margin: 0 auto 5mm;
            background: white;
            border-radius: 2mm;
            box-shadow: 0 1mm 3mm rgba(0,0,0,0.1);
            page-break-after: always;
            box-sizing: border-box;
            overflow: hidden;
        }
        
        .pagina:last-child {
            page-break-after: avoid;
        }
        
        .cartao-container {
            width: 100%;
            height: 100%;
            max-height: <?= floatval($config['tamanho_altura']) + 2 ?>mm;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1000px;
        }
        
        .cartao {
            width: <?= $config['tamanho_largura'] ?>mm;
            height: <?= $config['tamanho_altura'] ?>mm;
            position: relative;
            transform-style: preserve-3d;
            transition: all 0.8s ease;
            border-radius: <?= $config['borda_arredondada'] ?>mm;
            flex-shrink: 0;
        }
        
        .cartao:hover {
            transform: rotateY(180deg);
        }
        
        .cartao-frente, .cartao-verso {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: <?= $config['borda_arredondada'] ?>mm;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }
        
        .cartao-frente {
            background: <?= $config['cor_fundo'] ?>;
            color: <?= $config['cor_texto'] ?>;
            border: 1px solid <?= $config['cor_secundaria'] ?>;
            z-index: 2;
        }
        
        .cartao-verso {
            background: <?= $config['cor_fundo'] ?>;
            color: <?= $config['cor_texto'] ?>;
            transform: rotateY(180deg);
            border: 1px solid <?= $config['cor_secundaria'] ?>;
        }
        
        .cartao-frente .fundo-decorado {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .cartao-frente .logo-fundo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.15;
            z-index: 0;
            pointer-events: none;
            max-width: 65%;
            max-height: 65%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cartao-frente .logo-fundo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .cartao-frente .cartao-header {
            background: <?= $config['cor_cabecalho'] ?>;
            padding: 2.5mm 4mm 2mm;
            text-align: center;
            border-bottom: 3px solid <?= $config['cor_secundaria'] ?>;
            position: relative;
            z-index: 1;
        }
        
        .cartao-frente .cartao-header .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2mm;
        }
        
        .cartao-frente .cartao-header .logo-img {
            width: 7mm;
            height: 7mm;
            border-radius: 50%;
            overflow: hidden;
            background: #fff;
            border: 1px solid <?= $config['cor_secundaria'] ?>;
            flex-shrink: 0;
        }
        
        .cartao-frente .cartao-header .logo-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cartao-frente .cartao-header .logo-text {
            font-size: 0.8em;
            font-weight: 800;
            color: <?= $config['cor_secundaria'] ?>;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.1;
        }
        
        .cartao-frente .cartao-header .logo-sub {
            font-size: 0.45em;
            color: #f0e6d3;
            font-weight: 400;
            letter-spacing: 1px;
            margin-top: 0.3mm;
            text-transform: uppercase;
        }
        
        .cartao-frente .cartao-body {
            padding: 2mm 3mm 1.5mm;
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .cartao-frente .cartao-body .titulo-cartao {
            text-align: center;
            font-size: 0.85em;
            font-weight: 700;
            color: <?= $config['cor_texto'] ?>;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid <?= $config['cor_secundaria'] ?>;
            padding-bottom: 1mm;
            margin-bottom: 1mm;
        }
        
        .cartao-frente .linha-ondulada-container {
            padding: 0 3mm;
            margin: 0.5mm 0;
        }
        
        .cartao-frente .foto-container {
            border: 2px dashed <?= $config['cor_secundaria'] ?>;
            border-radius: 2mm;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f0eb;
        }
        
        .cartao-frente .foto-container .sem-foto {
            font-size: 0.7em;
            color: #b0a090;
        }
        
        .cartao-frente .foto-dados {
            display: flex;
            gap: 1.5mm;
            align-items: stretch;
            flex: 1;
            min-height: 0;
        }
        
        .cartao-frente .dados-qr-container {
            flex: 1;
            display: flex;
            flex-direction: row;
            gap: 1mm;
            min-height: 0;
            align-items: stretch;
        }
        
        .cartao-frente .dados-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }
        
        .cartao-frente .dados-container .campo {
            margin-bottom: 0.3mm;
            padding-bottom: 0.2mm;
            border-bottom: 0.5px dashed #e0d5c8;
        }
        
        .cartao-frente .dados-container .campo:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .cartao-frente .dados-container .label {
            font-weight: 600;
            color: #5a4a3a;
            font-size: 0.45em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .cartao-frente .dados-container .valor {
            font-weight: 600;
            color: <?= $config['cor_texto'] ?>;
            font-size: 0.65em;
            margin-top: 0.2mm;
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.2mm;
            min-height: 3mm;
        }
        
        .cartao-frente .dados-container .valor.destaque {
            color: <?= $config['nome_cor'] ?>;
        }
        
        .cartao-frente .campo-duplo {
            display: flex;
            gap: 2mm;
        }
        
        .cartao-frente .campo-duplo .item {
            flex: 1;
        }
        
        .cartao-frente .campo-duplo .item .label {
            font-weight: 600;
            color: #5a4a3a;
            font-size: 0.45em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .cartao-frente .campo-duplo .item .valor {
            font-weight: 600;
            color: <?= $config['cor_texto'] ?>;
            font-size: 0.65em;
            margin-top: 0.2mm;
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.2mm;
            min-height: 3mm;
        }
        
        .cartao-frente .qr-code-frente {
            flex-shrink: 0;
            border: 1px solid <?= $config['cor_secundaria'] ?>;
            border-radius: 2mm;
            overflow: hidden;
            padding: 1mm;
            background: white;
            width: 12mm;
            height: 12mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cartao-frente .qr-code-frente .qr-placeholder {
            font-size: 0.4em;
            color: #94a3b8;
            text-align: center;
        }
        
        .cartao-frente .assinatura-frente {
            text-align: center;
            margin-top: 0.5mm;
            border-top: 1px solid <?= $config['cor_texto'] ?>;
            padding-top: 0.5mm;
            width: 100%;
        }
        
        .cartao-frente .assinatura-frente .linha-assinatura {
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.3mm;
            min-height: 3mm;
        }
        
        .cartao-frente .info-importante {
            margin-top: 1mm;
            padding: 1mm 2mm;
            background: #f8f4ef;
            border-radius: 2mm;
            border-left: 2px solid <?= $config['cor_secundaria'] ?>;
        }
        
        .cartao-frente .info-importante .titulo-info {
            font-size: 0.45em;
            font-weight: 700;
            color: <?= $config['cor_texto'] ?>;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.3mm;
        }
        
        .cartao-frente .info-importante .texto-info {
            font-size: 0.4em;
            color: #4a3a2a;
            line-height: 1.2;
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        
        .cartao-frente .info-importante .texto-info li {
            padding: 0.1mm 0;
            padding-left: 2mm;
            position: relative;
        }
        
        .cartao-frente .info-importante .texto-info li::before {
            content: "•";
            color: <?= $config['cor_secundaria'] ?>;
            font-weight: 700;
            position: absolute;
            left: 0;
        }
        
        .cartao-frente .cartao-footer {
            background: <?= $config['cor_rodape'] ?>;
            border-top: 2px solid <?= $config['cor_secundaria'] ?>;
            padding: 1mm 3mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.45em;
            color: #5a4a3a;
            position: relative;
            z-index: 1;
        }
        
        .cartao-frente .cartao-footer .contato {
            text-align: center;
            flex: 1;
        }
        
        .cartao-frente .cartao-footer .contato strong {
            color: <?= $config['cor_texto'] ?>;
        }
        
        /* Estilos do verso */
        .cartao-verso .fundo-decorado-verso {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .cartao-verso .verso-header {
            background: <?= $config['cor_cabecalho'] ?>;
            padding: 3mm 4mm 2mm;
            text-align: center;
            border-bottom: 3px solid <?= $config['cor_secundaria'] ?>;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        
        .cartao-verso .verso-header .verso-titulo {
            font-size: 0.9em;
            font-weight: 700;
            color: <?= $config['cor_secundaria'] ?>;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .cartao-verso .verso-body {
            padding: 2mm 3mm 2mm;
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .cartao-verso .titulo-verso {
            text-align: center;
            font-size: 0.7em;
            font-weight: 700;
            color: <?= $config['cor_texto'] ?>;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid <?= $config['cor_secundaria'] ?>;
            padding-bottom: 0.5mm;
            margin-bottom: 0.5mm;
            flex-shrink: 0;
        }
        
        .cartao-verso .info-encarregado {
            font-size: 0.6em;
            padding: 1mm 2mm;
            background: #f8f4ef;
            border-radius: 1.5mm;
            margin-bottom: 1mm;
            border-left: 2px solid <?= $config['cor_secundaria'] ?>;
            flex-shrink: 0;
        }
        
        .cartao-verso .info-encarregado .linha {
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.2mm;
            min-height: 2.5mm;
        }
        
        .cartao-verso .info-encarregado strong {
            color: <?= $config['cor_texto'] ?>;
        }
        
        .cartao-verso .verso-tabelas-duplas {
            display: flex;
            gap: 2mm;
            flex: 1;
            min-height: 0;
        }
        
        .cartao-verso .verso-tabela-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .cartao-verso .tabela-pagamentos {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.55em;
            flex: 1;
        }
        
        .cartao-verso .tabela-pagamentos th {
            background: <?= $config['cor_secundaria'] ?>;
            color: <?= $config['cor_texto'] ?>;
            padding: 0.5mm 0.5mm;
            text-align: center;
            font-weight: 700;
            border: 0.5px solid <?= $config['cor_secundaria'] ?>;
        }
        
        .cartao-verso .tabela-pagamentos td {
            border: 0.5px solid #e0d5c8;
            padding: 0.3mm 0.5mm;
            text-align: center;
            height: 4.5mm;
        }
        
        .cartao-verso .tabela-pagamentos td .linha {
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.2mm;
            min-height: 3.5mm;
        }
        
        .cartao-verso .tabela-pagamentos tr:nth-child(even) td {
            background: #faf8f5;
        }
        
        .cartao-verso .verso-assinatura-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding-top: 1mm;
            flex-shrink: 0;
        }
        
        .cartao-verso .assinatura-container {
            width: 60%;
            text-align: center;
        }
        
        .cartao-verso .linha-assinatura {
            border-top: 1.5px solid <?= $config['cor_texto'] ?>;
            width: 80%;
            margin: 0 auto 0.3mm auto;
        }
        
        .cartao-verso .assinatura-texto {
            text-align: center;
            color: <?= $config['cor_texto'] ?>;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.2;
        }
        
        .cartao-verso .assinatura-texto .linha {
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.2mm;
            min-height: 2.5mm;
        }
        
        .cartao-verso .assinatura-cargo {
            font-size: 0.4em;
            text-align: center;
            color: #94a3b8;
            font-weight: 400;
            margin-top: 0.3mm;
        }
        
        .cartao-verso .verso-footer {
            background: <?= $config['cor_rodape'] ?>;
            border-top: 2px solid <?= $config['cor_secundaria'] ?>;
            padding: 1.5mm 3mm;
            text-align: center;
            font-size: 0.5em;
            color: #5a4a3a;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        
        .cartao-verso .verso-footer strong {
            color: <?= $config['cor_texto'] ?>;
        }
        
        .cartao-verso .verso-footer .linha {
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.2mm;
            min-height: 2.5mm;
            display: inline-block;
            min-width: 20mm;
        }
        
        /* IMPRESSÃO */
        @media print {
            html, body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 210mm !important;
                height: 297mm !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .pagina {
                page-break-after: always !important;
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                max-height: 297mm !important;
                padding: 4mm !important;
                margin: 0 !important;
                background: white !important;
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                grid-template-rows: auto repeat(3, 1fr) !important;
                gap: 3mm !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            
            .pagina:last-child {
                page-break-after: avoid !important;
            }
            
            .cartao-container {
                max-height: <?= floatval($config['tamanho_altura']) + 2 ?>mm !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .cartao {
                width: <?= $config['tamanho_largura'] ?>mm !important;
                height: <?= $config['tamanho_altura'] ?>mm !important;
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                border-radius: <?= $config['borda_arredondada'] ?>mm !important;
            }
            
            .cartao:hover {
                transform: none !important;
            }
            
            .cartao-frente, .cartao-verso {
                border-radius: <?= $config['borda_arredondada'] ?>mm !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="imprimirFrente()">🖨️ Imprimir Frente</button>
    <button class="btn-print" onclick="imprimirVerso()" style="margin-left:10px;">🔄 Imprimir Verso</button>
    <a href="cartoes_escolares.php" style="margin-left:10px;">← Voltar</a>
    <p style="margin-top: 5px; color: #94a3b8; font-size: 12px;">
        6 cartões por página - Preencher manualmente
    </p>
</div>

<!-- ==========================================
     FRENTE DOS CARTÕES
     ========================================== -->
<div id="frente-cartoes">
    <div class="pagina">
        <?php for($i = 0; $i < 6; $i++): ?>
        <div class="cartao-container">
            <div class="cartao">
                <!-- FRENTE -->
                <div class="cartao-frente">
                    <!-- FUNDO DECORADO -->
                    <div class="fundo-decorado" style="background: linear-gradient(135deg, <?= $config['cor_primaria'] ?> 0%, <?= $config['cor_secundaria'] ?> 100%); opacity: 0.05; position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;"></div>
                    
                    <!-- HEADER -->
                    <div class="cartao-header" style="background:<?= $config['cor_cabecalho'] ?>;border-bottom:3px solid <?= $config['cor_secundaria'] ?>;position:relative;z-index:1;">
                        <div class="logo-container" style="display:flex;align-items:center;justify-content:center;gap:2mm;">
                            <div class="logo-img" style="width:7mm;height:7mm;border-radius:50%;overflow:hidden;background:#fff;border:1.5px solid <?= $config['cor_secundaria'] ?>;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:4pt;color:#999;">CR</div>
                            <div>
                                <div class="logo-text" style="font-size:0.8em;font-weight:800;color:<?= $config['cor_secundaria'] ?>;letter-spacing:0.5px;text-transform:uppercase;line-height:1.1;">COMPLEXO ESCOLAR PRIVADO<br>CASTELO REIS</div>
                                <div class="logo-sub" style="font-size:0.45em;color:#f0e6d3;font-weight:400;letter-spacing:1px;margin-top:0.3mm;text-transform:uppercase;">EDUCAR PARA TRANSFORMAR, FORMAR PARA A VIDA.</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- BODY -->
                    <div class="cartao-body">
                        <div class="titulo-cartao">CARTÃO DE ESTUDANTE</div>
                        
                        <?= $linha_ondulada_html ?>
                        
                        <div class="foto-dados">
                            <div class="foto-container" style="width:<?= $config['foto_largura'] ?>mm;height:<?= $config['foto_altura'] ?>mm;border:2px dashed <?= $config['cor_secundaria'] ?>;border-radius:2mm;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:#f5f0eb;align-self:center;">
                                <div class="sem-foto" style="font-size:0.7em;color:#b0a090;">📷 FOTO</div>
                            </div>
                            
                            <div class="dados-qr-container" style="flex:1;display:flex;flex-direction:row;gap:1mm;min-height:0;align-items:stretch;">
                                <div class="dados-container" style="flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden;">
                                    <div class="campo" style="margin-bottom:0.3mm;padding-bottom:0.2mm;border-bottom:0.5px dashed #e0d5c8;margin-top:1.5mm;">
                                        <span class="label" style="font-weight:600;color:#5a4a3a;font-size:0.45em;text-transform:uppercase;letter-spacing:0.3px;display:block;">NOME DO ESTUDANTE</span>
                                        <div class="valor destaque" style="font-weight:700;color:<?= $config['nome_cor'] ?>;font-size:0.65em;margin-top:0.2mm;border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3mm;">________________________</div>
                                    </div>
                                    
                                    <div class="campo-duplo" style="display:flex;gap:2mm;margin-bottom:0.2mm;">
                                        <div class="item" style="flex:1;">
                                            <span class="label" style="font-weight:600;color:#5a4a3a;font-size:0.45em;text-transform:uppercase;letter-spacing:0.3px;display:block;">Nº DE ESTUDANTE</span>
                                            <div class="valor" style="font-weight:600;color:<?= $config['cor_texto'] ?>;font-size:0.6em;margin-top:0.2mm;border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3mm;">___________</div>
                                        </div>
                                        <div class="item" style="flex:1;">
                                            <span class="label" style="font-weight:600;color:#5a4a3a;font-size:0.45em;text-transform:uppercase;letter-spacing:0.3px;display:block;">CLASSE / TURMA</span>
                                            <div class="valor" style="font-weight:600;color:<?= $config['classe_cor'] ?>;font-size:0.65em;margin-top:0.2mm;border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3mm;">___________</div>
                                        </div>
                                    </div>
                                    
                                    <div class="campo-duplo" style="display:flex;gap:2mm;padding-top:0.2mm;border-top:0.5px dashed #e0d5c8;flex:1;align-items:flex-start;">
                                        <div class="item" style="flex:1;">
                                            <span class="label" style="font-weight:600;color:#5a4a3a;font-size:0.4em;text-transform:uppercase;letter-spacing:0.3px;display:block;">TURNO</span>
                                            <div class="valor" style="font-weight:600;color:<?= $config['cor_texto'] ?>;font-size:0.55em;margin-top:0.2mm;border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3mm;">___________</div>
                                        </div>
                                        <div class="item" style="flex:1;">
                                            <span class="label" style="font-weight:600;color:#5a4a3a;font-size:0.4em;text-transform:uppercase;letter-spacing:0.3px;display:block;">ANO LECTIVO</span>
                                            <div class="valor" style="font-weight:600;color:<?= $config['cor_texto'] ?>;font-size:0.55em;margin-top:0.2mm;border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3mm;"><?= $ano_letivo ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="qr-code-frente" style="flex-shrink:0;border:1px solid <?= $config['cor_secundaria'] ?>;border-radius:2mm;overflow:hidden;padding:1mm;background:white;width:12mm;height:12mm;display:flex;align-items:center;justify-content:center;">
                                    <div class="qr-placeholder" style="font-size:0.4em;color:#94a3b8;text-align:center;">QR<br>CODE</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-importante" style="margin-top:1mm;padding:1mm 2mm;background:#f8f4ef;border-radius:2mm;border-left:2px solid <?= $config['cor_secundaria'] ?>;flex-shrink:0;">
                            <div class="titulo-info" style="font-size:0.45em;font-weight:700;color:<?= $config['cor_texto'] ?>;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.3mm;">INFORMAÇÕES IMPORTANTES</div>
                            <ul class="texto-info" style="font-size:0.4em;color:#4a3a2a;line-height:1.2;list-style:none;padding-left:0;margin:0;">
                                <li style="padding:0.1mm 0;padding-left:2mm;position:relative;">Este cartão é pessoal e intransmissível.</li>
                                <li style="padding:0.1mm 0;padding-left:2mm;position:relative;">Deve ser apresentado sempre que solicitado.</li>
                                <li style="padding:0.1mm 0;padding-left:2mm;position:relative;">Em caso de perda, comunique imediatamente à Direcção da Escola.</li>
                            </ul>
                        </div>
                        
                        <div class="assinatura-frente" style="text-align:center;margin-top:0.5mm;border-top:1px solid <?= $config['cor_texto'] ?>;padding-top:0.5mm;width:100%;flex-shrink:0;">
                            <div class="linha-assinatura" style="border-bottom:1px solid #ccc;padding-bottom:0.3mm;min-height:3mm;"><?= $config['assinatura_frente_nome'] ?></div>
                        </div>
                    </div>
                    
                    <!-- FOOTER -->
                    <div class="cartao-footer" style="background:<?= $config['cor_rodape'] ?>;border-top:2px solid <?= $config['cor_secundaria'] ?>;padding:1mm 3mm;display:flex;justify-content:space-between;align-items:center;font-size:0.45em;color:#5a4a3a;position:relative;z-index:1;flex-shrink:0;">
                        <div class="contato" style="text-align:center;flex:1;min-width:0;"><strong style="color:<?= $config['cor_texto'] ?>;"><?= htmlspecialchars($enderecoEmpresa) ?></strong><br><?= htmlspecialchars($cidadeEmpresa) ?></div>
                        <div class="contato" style="text-align:center;flex:1;min-width:0;"><strong style="color:<?= $config['cor_texto'] ?>;"><?= htmlspecialchars($telefoneEmpresa) ?></strong><br><?= htmlspecialchars($emailEmpresa) ?> | <?= $telefone2 ?></div>
                    </div>
                </div>
                
                <!-- VERSO -->
                <div class="cartao-verso">
                    <div class="fundo-decorado-verso" style="background: linear-gradient(135deg, <?= $config['cor_primaria'] ?> 0%, <?= $config['cor_secundaria'] ?> 100%); opacity: 0.05; position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;"></div>
                    
                    <div class="verso-header" style="background:<?= $config['cor_cabecalho'] ?>;border-bottom:3px solid <?= $config['cor_secundaria'] ?>;padding:3mm 4mm 2mm;text-align:center;position:relative;z-index:1;flex-shrink:0;">
                        <div class="verso-titulo" style="font-size:0.9em;font-weight:700;color:<?= $config['cor_secundaria'] ?>;text-transform:uppercase;letter-spacing:1px;">CONTROLE DE PAGAMENTOS</div>
                    </div>
                    
                    <div class="verso-body" style="padding:2mm 3mm 2mm;position:relative;z-index:1;flex:1;display:flex;flex-direction:column;min-height:0;">
                        <div class="titulo-verso" style="text-align:center;font-size:0.7em;font-weight:700;color:<?= $config['cor_texto'] ?>;text-transform:uppercase;letter-spacing:1px;border-bottom:2px solid <?= $config['cor_secundaria'] ?>;padding-bottom:0.5mm;margin-bottom:0.5mm;flex-shrink:0;">CONTROLE DE PAGAMENTOS</div>
                        
                        <div class="info-encarregado" style="font-size:0.6em;padding:1mm 2mm;background:#f8f4ef;border-radius:1.5mm;margin-bottom:1mm;border-left:2px solid <?= $config['cor_secundaria'] ?>;flex-shrink:0;">
                            <strong style="color:<?= $config['cor_texto'] ?>;">Encarregado:</strong> <span class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:2.5mm;display:inline-block;min-width:40mm;">_______________________</span><br>
                            <strong style="color:<?= $config['cor_texto'] ?>;">Contacto:</strong> <span class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:2.5mm;display:inline-block;min-width:25mm;">_________________</span>
                        </div>
                        
                        <div class="verso-tabelas-duplas" style="display:flex;gap:2mm;flex:1;min-height:0;">
                            <div class="verso-tabela-container" style="flex:1;display:flex;flex-direction:column;min-height:0;">
                                <table class="tabela-pagamentos" style="width:100%;border-collapse:collapse;font-size:0.55em;flex:1;">
                                    <thead>
                                        <tr><th colspan="3" style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.65em;">1º TRIMESTRE</th></tr>
                                        <tr>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Mês</th>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Propina</th>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Transporte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($meses_coluna1 as $mes): ?>
                                        <tr>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;font-weight:600;"><?= $mes ?></td>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;"><div class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3.5mm;"></div></td>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;"><div class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3.5mm;"></div></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="verso-tabela-container" style="flex:1;display:flex;flex-direction:column;min-height:0;">
                                <table class="tabela-pagamentos" style="width:100%;border-collapse:collapse;font-size:0.55em;flex:1;">
                                    <thead>
                                        <tr><th colspan="3" style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.65em;">2º TRIMESTRE</th></tr>
                                        <tr>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Mês</th>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Propina</th>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Transporte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($meses_coluna2 as $mes): ?>
                                        <tr>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;font-weight:600;"><?= $mes ?></td>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;"><div class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3.5mm;"></div></td>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;"><div class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3.5mm;"></div></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="verso-assinatura-container" style="display:flex;justify-content:center;align-items:center;padding-top:1mm;flex-shrink:0;">
                            <div class="assinatura-container" style="width:60%;text-align:center;">
                                <div class="linha-assinatura" style="border-top:1.5px solid <?= $config['cor_texto'] ?>;width:80%;margin:0 auto 0.3mm auto;"></div>
                                <div class="assinatura-texto" style="text-align:center;color:<?= $config['cor_texto'] ?>;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;line-height:1.2;">
                                    <span class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:2.5mm;display:inline-block;min-width:30mm;"><?= $config['assinatura_verso_nome'] ?></span>
                                </div>
                                <div class="assinatura-cargo" style="font-size:0.4em;text-align:center;color:#94a3b8;font-weight:400;margin-top:0.3mm;"><?= $config['assinatura_verso_cargo'] ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="verso-footer" style="background:<?= $config['cor_rodape'] ?>;border-top:2px solid <?= $config['cor_secundaria'] ?>;padding:1.5mm 3mm;text-align:center;font-size:0.5em;color:#5a4a3a;position:relative;z-index:1;flex-shrink:0;">
                        <strong style="color:<?= $config['cor_texto'] ?>;">ID:</strong> <span class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:2.5mm;display:inline-block;min-width:15mm;">____________</span> &nbsp;|&nbsp;
                        <strong style="color:<?= $config['cor_texto'] ?>;">ANO:</strong> <?= $ano_letivo ?> &nbsp;|&nbsp;
                        <strong style="color:<?= $config['cor_texto'] ?>;">Emitido:</strong> <span class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:2.5mm;display:inline-block;min-width:15mm;">__/__/____</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- ==========================================
     VERSO DOS CARTÕES
     ========================================== -->
<div id="verso-cartoes" style="display:none;">
    <div class="pagina">
        <?php for($i = 0; $i < 6; $i++): ?>
        <div class="cartao-container">
            <div class="cartao">
                <div class="cartao-verso">
                    <div class="fundo-decorado-verso" style="background: linear-gradient(135deg, <?= $config['cor_primaria'] ?> 0%, <?= $config['cor_secundaria'] ?> 100%); opacity: 0.05; position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;"></div>
                    
                    <div class="verso-header" style="background:<?= $config['cor_cabecalho'] ?>;border-bottom:3px solid <?= $config['cor_secundaria'] ?>;padding:3mm 4mm 2mm;text-align:center;position:relative;z-index:1;flex-shrink:0;">
                        <div class="verso-titulo" style="font-size:0.9em;font-weight:700;color:<?= $config['cor_secundaria'] ?>;text-transform:uppercase;letter-spacing:1px;">CONTROLE DE PAGAMENTOS</div>
                    </div>
                    
                    <div class="verso-body" style="padding:2mm 3mm 2mm;position:relative;z-index:1;flex:1;display:flex;flex-direction:column;min-height:0;">
                        <div class="titulo-verso" style="text-align:center;font-size:0.7em;font-weight:700;color:<?= $config['cor_texto'] ?>;text-transform:uppercase;letter-spacing:1px;border-bottom:2px solid <?= $config['cor_secundaria'] ?>;padding-bottom:0.5mm;margin-bottom:0.5mm;flex-shrink:0;">CONTROLE DE PAGAMENTOS</div>
                        
                        <div class="info-encarregado" style="font-size:0.6em;padding:1mm 2mm;background:#f8f4ef;border-radius:1.5mm;margin-bottom:1mm;border-left:2px solid <?= $config['cor_secundaria'] ?>;flex-shrink:0;">
                            <strong style="color:<?= $config['cor_texto'] ?>;">Encarregado:</strong> <span class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:2.5mm;display:inline-block;min-width:40mm;">_______________________</span><br>
                            <strong style="color:<?= $config['cor_texto'] ?>;">Contacto:</strong> <span class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:2.5mm;display:inline-block;min-width:25mm;">_________________</span>
                        </div>
                        
                        <div class="verso-tabelas-duplas" style="display:flex;gap:2mm;flex:1;min-height:0;">
                            <div class="verso-tabela-container" style="flex:1;display:flex;flex-direction:column;min-height:0;">
                                <table class="tabela-pagamentos" style="width:100%;border-collapse:collapse;font-size:0.55em;flex:1;">
                                    <thead>
                                        <tr><th colspan="3" style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.65em;">1º TRIMESTRE</th></tr>
                                        <tr>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Mês</th>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Propina</th>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Transporte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($meses_coluna1 as $mes): ?>
                                        <tr>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;font-weight:600;"><?= $mes ?></td>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;"><div class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3.5mm;"></div></td>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;"><div class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3.5mm;"></div></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="verso-tabela-container" style="flex:1;display:flex;flex-direction:column;min-height:0;">
                                <table class="tabela-pagamentos" style="width:100%;border-collapse:collapse;font-size:0.55em;flex:1;">
                                    <thead>
                                        <tr><th colspan="3" style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.65em;">2º TRIMESTRE</th></tr>
                                        <tr>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Mês</th>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Propina</th>
                                            <th style="background:<?= $config['cor_secundaria'] ?>;color:<?= $config['cor_texto'] ?>;padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid <?= $config['cor_secundaria'] ?>;font-size:0.55em;">Transporte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($meses_coluna2 as $mes): ?>
                                        <tr>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;font-weight:600;"><?= $mes ?></td>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;"><div class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3.5mm;"></div></td>
                                            <td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:4.5mm;"><div class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:3.5mm;"></div></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="verso-assinatura-container" style="display:flex;justify-content:center;align-items:center;padding-top:1mm;flex-shrink:0;">
                            <div class="assinatura-container" style="width:60%;text-align:center;">
                                <div class="linha-assinatura" style="border-top:1.5px solid <?= $config['cor_texto'] ?>;width:80%;margin:0 auto 0.3mm auto;"></div>
                                <div class="assinatura-texto" style="text-align:center;color:<?= $config['cor_texto'] ?>;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;line-height:1.2;">
                                    <span class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:2.5mm;display:inline-block;min-width:30mm;"><?= $config['assinatura_verso_nome'] ?></span>
                                </div>
                                <div class="assinatura-cargo" style="font-size:0.4em;text-align:center;color:#94a3b8;font-weight:400;margin-top:0.3mm;"><?= $config['assinatura_verso_cargo'] ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="verso-footer" style="background:<?= $config['cor_rodape'] ?>;border-top:2px solid <?= $config['cor_secundaria'] ?>;padding:1.5mm 3mm;text-align:center;font-size:0.5em;color:#5a4a3a;position:relative;z-index:1;flex-shrink:0;">
                        <strong style="color:<?= $config['cor_texto'] ?>;">ID:</strong> <span class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:2.5mm;display:inline-block;min-width:15mm;">____________</span> &nbsp;|&nbsp;
                        <strong style="color:<?= $config['cor_texto'] ?>;">ANO:</strong> <?= $ano_letivo ?> &nbsp;|&nbsp;
                        <strong style="color:<?= $config['cor_texto'] ?>;">Emitido:</strong> <span class="linha" style="border-bottom:1px solid #ccc;padding-bottom:0.2mm;min-height:2.5mm;display:inline-block;min-width:15mm;">__/__/____</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<script>
    function imprimirFrente() {
        var frenteDiv = document.getElementById('frente-cartoes');
        var versoDiv = document.getElementById('verso-cartoes');
        var noPrint = document.querySelectorAll('.no-print');
        
        if (versoDiv) versoDiv.style.display = 'none';
        if (frenteDiv) frenteDiv.style.display = 'block';
        noPrint.forEach(function(el) { el.style.display = 'none'; });
        
        setTimeout(function() {
            window.print();
            setTimeout(function() {
                noPrint.forEach(function(el) { el.style.display = ''; });
                if (versoDiv) versoDiv.style.display = 'none';
                if (frenteDiv) frenteDiv.style.display = 'block';
            }, 500);
        }, 300);
    }
    
    function imprimirVerso() {
        var frenteDiv = document.getElementById('frente-cartoes');
        var versoDiv = document.getElementById('verso-cartoes');
        var noPrint = document.querySelectorAll('.no-print');
        
        if (!versoDiv) {
            alert('O verso não está disponível.');
            return;
        }
        
        if (frenteDiv) frenteDiv.style.display = 'none';
        if (versoDiv) versoDiv.style.display = 'block';
        noPrint.forEach(function(el) { el.style.display = 'none'; });
        
        setTimeout(function() {
            window.print();
            setTimeout(function() {
                noPrint.forEach(function(el) { el.style.display = ''; });
                if (frenteDiv) frenteDiv.style.display = 'block';
                if (versoDiv) versoDiv.style.display = 'none';
            }, 500);
        }, 300);
    }
</script>

</body>
</html>