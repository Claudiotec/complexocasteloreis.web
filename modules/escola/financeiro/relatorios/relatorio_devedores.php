<?php
// ============================================
// relatorio_devedores.php - Relatório de Devedores (INDEPENDENTE)
// ============================================

// ===== 1. CONFIGURAÇÕES =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] == '') {
    header('Location: ../../../../../login.php');
    exit;
}

// ===== 2. CONEXÃO COM BANCO =====
$db_host = 'localhost';
$db_name = 'softgest_db';
$db_user = 'root';
$db_pass = '';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die('Erro de conexão: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// ===== 3. PARÂMETROS =====
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
$filtro_classe = isset($_GET['classe']) ? mysqli_real_escape_string($conn, $_GET['classe']) : '';
$filtro_emolumento = isset($_GET['emolumento']) ? (int)$_GET['emolumento'] : 0;
$acao = isset($_GET['acao']) ? $_GET['acao'] : '';

// ===== 4. FUNÇÕES =====
function mesNome($mes) {
    $meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];
    return $meses[(int)$mes] ?? 'Mês inválido';
}

function formatarMoeda($valor) {
    return 'Kz ' . number_format($valor, 2, ',', '.');
}

function formatarData($data) {
    if (empty($data) || $data == '0000-00-00') {
        return '-';
    }
    return date('d/m/Y', strtotime($data));
}

function formaPagamentoLabel($forma) {
    $labels = [
        'dinheiro' => '💰 Dinheiro',
        'transferencia' => '🏦 Transferência',
        'pix' => '📱 PIX',
        'cartao' => '💳 Cartão',
        'cartao_credito' => '💳 Cartão Crédito',
        'cartao_debito' => '💳 Cartão Débito',
        'boleto' => '📄 Boleto'
    ];
    return $labels[$forma] ?? ucfirst($forma);
}

// ===== 5. BUSCAR DADOS DA EMPRESA =====
$empresa = [
    'nome' => 'COMPLEXO ESCOLAR CASTELO REIS',
    'endereco' => '',
    'telefone' => '',
    'email' => '',
    'nif' => '',
];

try {
    $sql_emp = "SELECT * FROM empresa WHERE id = 1 LIMIT 1";
    $result_emp = mysqli_query($conn, $sql_emp);
    if ($result_emp && mysqli_num_rows($result_emp) > 0) {
        $emp = mysqli_fetch_assoc($result_emp);
        $empresa['nome'] = $emp['nome_fantasia'] ?? $emp['razao_social'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';
        $empresa['endereco'] = $emp['endereco'] ?? '';
        $empresa['telefone'] = $emp['telefone'] ?? $emp['celular'] ?? '';
        $empresa['email'] = $emp['email'] ?? '';
        $empresa['nif'] = $emp['cnpj'] ?? $emp['inscricao_estadual'] ?? '';
    }
} catch (Exception $e) {}

// ===== 6. BUSCAR DEVEDORES =====
$sql = "
    SELECT 
        a.id as aluno_id,
        a.nome as aluno_nome,
        a.Classe as classe,
        a.TURMA as turma_nome,
        a.Contacto4 as telefone_responsavel,
        a.telefone as telefone_aluno,
        m.id as mensalidade_id,
        m.mes,
        m.ano,
        m.valor,
        m.data_vencimento,
        m.status,
        e.nome as emolumento_nome,
        (SELECT COUNT(*) FROM pagamentos p 
         WHERE p.aluno_id = m.aluno_id 
         AND p.emolumento_id = m.emolumento_id
         AND p.status = 'confirmado'
         AND (
             p.mes_referencia = CONCAT(
                 ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                 '/',
                 m.ano
             )
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
         )
        ) as tem_pagamento
    FROM mensalidades m
    INNER JOIN alunos a ON m.aluno_id = a.id
    LEFT JOIN emolumentos e ON m.emolumento_id = e.id
    WHERE m.ano = $ano
    AND m.mes = $mes
    AND m.status != 'pago'
    AND m.status != 'cancelado'
    AND (SELECT COUNT(*) FROM pagamentos p 
         WHERE p.aluno_id = m.aluno_id 
         AND p.emolumento_id = m.emolumento_id
         AND p.status = 'confirmado'
         AND (
             p.mes_referencia = CONCAT(
                 ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                 '/',
                 m.ano
             )
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
         )
        ) = 0
";

if (!empty($filtro_classe)) {
    $sql .= " AND a.Classe = '$filtro_classe'";
}

if ($filtro_emolumento > 0) {
    $sql .= " AND m.emolumento_id = $filtro_emolumento";
}

$sql .= " ORDER BY a.Classe, a.nome";

$result = mysqli_query($conn, $sql);
$devedores = [];
while ($row = mysqli_fetch_assoc($result)) {
    $devedores[] = $row;
}

// ===== 7. FUNÇÃO PARA GERAR RECIBOS DE COBRANÇA PROFISSIONAL - 6 POR FOLHA =====
function gerarRecibosCobranca($devedores, $empresa, $ano, $mes) {
    $hoje = date('d/m/Y');
    $prazo = date('d/m/Y', strtotime('+2 days'));
    $data_limite = date('d/m/Y', strtotime('+5 days'));
    $mes_referencia = mesNome($mes) . '/' . $ano;
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>RECIBOS DE COBRANÇA - URGENTE</title>
        <style>
            @page { 
                size: A4; 
                margin: 3mm 4mm 3mm 4mm;
            }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: "Times New Roman", Arial, sans-serif; 
                background: #fff; 
                padding: 0;
            }
            .page { 
                page-break-after: always; 
                padding: 1mm;
                min-height: 297mm;
                display: flex;
                flex-direction: column;
            }
            .page:last-child { page-break-after: avoid; }
            
            .recibo-grid { 
                display: grid; 
                grid-template-columns: 1fr 1fr 1fr; 
                gap: 3px; 
                flex: 1;
                height: 100%;
            }
            
            .recibo-item { 
                border: 2px solid #1a2332; 
                padding: 8px 10px; 
                border-radius: 3px; 
                display: flex; 
                flex-direction: column; 
                justify-content: space-between;
                background: #fff;
                position: relative;
                min-height: 120px;
                font-size: 11pt;
            }
            
            .recibo-item .selo-urgencia {
                position: absolute;
                top: -8px;
                right: -8px;
                background: #e74c3c;
                color: #fff;
                font-size: 7pt;
                font-weight: 700;
                padding: 2px 10px;
                border-radius: 10px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                transform: rotate(5deg);
                border: 1px solid #c0392b;
                box-shadow: 0 2px 8px rgba(231,76,60,0.3);
                z-index: 10;
            }
            
            .recibo-item .header { 
                text-align: center; 
                border-bottom: 2px double #1a2332; 
                padding-bottom: 4px; 
                margin-bottom: 4px; 
            }
            .recibo-item .header .escola { 
                font-size: 10pt; 
                font-weight: 700; 
                text-transform: uppercase; 
                color: #1a2332; 
                letter-spacing: 0.5px;
            }
            .recibo-item .header .titulo { 
                font-size: 13pt; 
                font-weight: 700; 
                color: #c0392b; 
                letter-spacing: 1px;
            }
            .recibo-item .header .numero { 
                font-size: 8pt; 
                color: #666; 
                font-weight: 600;
            }
            
            .recibo-item .corpo { 
                flex: 1; 
                font-size: 10pt; 
            }
            .recibo-item .corpo .linha { 
                display: flex; 
                justify-content: space-between; 
                padding: 2px 0; 
                border-bottom: 1px dotted #eee; 
            }
            .recibo-item .corpo .linha .label { 
                font-weight: 700; 
                color: #1a2332;
                font-size: 9pt;
            }
            .recibo-item .corpo .linha .valor-texto {
                font-size: 9pt;
            }
            .recibo-item .corpo .linha .valor-texto strong {
                font-size: 10pt;
            }
            .recibo-item .corpo .valor-destaque { 
                font-size: 16pt; 
                font-weight: 700; 
                color: #c0392b; 
                letter-spacing: 0.5px;
            }
            .recibo-item .corpo .urgencia-texto {
                font-size: 8pt;
                color: #e74c3c;
                font-weight: 700;
                text-align: center;
                padding: 3px 0;
                background: #fef2f2;
                border-radius: 3px;
                margin-top: 3px;
                border: 1px solid #fecaca;
            }
            .recibo-item .corpo .prazo-texto {
                font-size: 7pt;
                color: #666;
                text-align: center;
                padding: 2px 0;
                background: #fff3cd;
                border-radius: 3px;
                margin-top: 2px;
                border: 1px solid #ffeaa7;
            }
            .recibo-item .corpo .mes-referencia {
                font-size: 8pt;
                color: #1a2332;
                text-align: center;
                font-weight: 600;
                padding: 2px 0;
                background: #e8f4fd;
                border-radius: 3px;
                margin-top: 2px;
                border: 1px solid #b8d4e8;
            }
            
            .recibo-item .status-atrasado { color: #e74c3c; font-weight: 700; }
            .recibo-item .status-pendente { color: #f39c12; font-weight: 700; }
            
            .recibo-item .rodape { 
                border-top: 2px double #1a2332; 
                padding-top: 3px; 
                margin-top: 3px; 
                font-size: 7pt; 
                text-align: center; 
                color: #666; 
            }
            .recibo-item .assinatura { 
                margin-top: 4px; 
                text-align: center; 
            }
            .recibo-item .assinatura .linha { 
                border-top: 1px solid #000; 
                width: 120px; 
                margin: 3px auto; 
            }
            .recibo-item .assinatura .ass-texto {
                font-size: 7pt;
                color: #666;
            }
            
            .recibo-vazio {
                border: 2px solid #ccc;
                background: #f9f9f9;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #999;
                font-size: 11pt;
                border-radius: 3px;
                min-height: 120px;
            }
            
            .footer-pagina {
                text-align: center;
                font-size: 6pt;
                color: #999;
                padding-top: 2px;
                border-top: 1px solid #eee;
                margin-top: 2px;
            }
            
            @media print {
                .no-print { display: none !important; }
                .recibo-item { 
                    -webkit-print-color-adjust: exact !important; 
                    print-color-adjust: exact !important; 
                }
                .recibo-item .selo-urgencia {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .recibo-item .corpo .urgencia-texto {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .recibo-item .corpo .prazo-texto {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .recibo-item .corpo .mes-referencia {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .page { padding: 1mm; min-height: auto; }
                .recibo-grid { gap: 3px; }
            }
        </style>
    </head>
    <body>';
    
    $total = count($devedores);
    $hoje_time = time();
    
    if ($total === 0) {
        $html .= '<div style="text-align:center;padding:40px;font-size:18px;color:#666;">
            <h3>Nenhum devedor encontrado!</h3>
            <p>Não há recibos para gerar.</p>
        </div>';
        return $html;
    }
    
    for ($i = 0; $i < $total; $i += 6) {
        $html .= '<div class="page">';
        $html .= '<div class="recibo-grid">';
        
        for ($j = 0; $j < 6; $j++) {
            $idx = $i + $j;
            if ($idx >= $total) {
                $html .= '<div class="recibo-vazio">📋 Recibo vazio</div>';
                continue;
            }
            $d = $devedores[$idx];
            $is_atrasado = strtotime($d['data_vencimento']) < $hoje_time;
            $status_label = $is_atrasado ? '⚠️ ATRASADO' : '⏳ PENDENTE';
            $status_class = $is_atrasado ? 'status-atrasado' : 'status-pendente';
            $status_cor = $is_atrasado ? '#e74c3c' : '#f39c12';
            
            $html .= '
            <div class="recibo-item">
                <div class="selo-urgencia">🚨 URGENTE</div>
                <div class="header">
                    <div class="escola">' . htmlspecialchars($empresa['nome']) . '</div>
                    <div class="titulo">📋 RECIBO DE COBRANÇA</div>
                    <div class="numero">Nº: ' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT) . '/' . date('Y') . '</div>
                </div>
                <div class="corpo">
                    <div class="linha">
                        <span class="label">👤 Aluno:</span>
                        <span class="valor-texto"><strong>' . htmlspecialchars($d['aluno_nome']) . '</strong></span>
                    </div>
                    <div class="linha">
                        <span class="label">📚 Classe:</span>
                        <span class="valor-texto">' . htmlspecialchars($d['classe']) . 'ª</span>
                    </div>
                    <div class="linha">
                        <span class="label">🏫 Turma:</span>
                        <span class="valor-texto">' . htmlspecialchars($d['turma_nome']) . '</span>
                    </div>
                    <div class="linha">
                        <span class="label">📋 Emolumento:</span>
                        <span class="valor-texto">' . htmlspecialchars($d['emolumento_nome'] ?? 'Mensalidade') . '</span>
                    </div>
                    <div class="mes-referencia">
                        📅 Mês de Referência: ' . $mes_referencia . '
                    </div>
                    <div class="linha" style="border-bottom:2px solid #c0392b;padding:3px 0;margin-top:2px;">
                        <span class="label" style="font-size:10pt;">💰 VALOR DEVIDO:</span>
                        <span class="valor-destaque">' . formatarMoeda($d['valor']) . '</span>
                    </div>
                    <div class="linha">
                        <span class="label">📅 Vencimento:</span>
                        <span class="valor-texto" style="color:' . $status_cor . ';font-weight:700;">' . formatarData($d['data_vencimento']) . '</span>
                    </div>
                    <div class="linha">
                        <span class="label">📌 Status:</span>
                        <span class="' . $status_class . ' valor-texto" style="color:' . $status_cor . ';">' . $status_label . '</span>
                    </div>
                    <div class="urgencia-texto">
                        ⚠️ PAGAMENTO URGENTE - 48 HORAS
                    </div>
                    <div class="prazo-texto">
                        📅 Prazo limite: ' . $data_limite . ' | Evite sanções
                    </div>
                </div>
                <div class="rodape">
                    <div style="display:flex;justify-content:space-between;font-size:6pt;">
                        <span>📅 Emissão: ' . $hoje . '</span>
                        <span>⏳ Prazo: ' . $prazo . '</span>
                        <span>📞 Secretaria</span>
                    </div>
                    <div class="assinatura">
                        <div class="linha"></div>
                        <span class="ass-texto">Assinatura do Responsável</span>
                    </div>
                </div>
            </div>';
        }
        
        $html .= '</div>';
        $html .= '<div class="footer-pagina">Documento emitido eletronicamente - ' . htmlspecialchars($empresa['nome']) . ' © ' . date('Y') . ' | Página ' . (floor($i / 6) + 1) . '</div>';
        $html .= '</div>';
    }
    
    $html .= '
    <div class="no-print" style="text-align:center;padding:10px;position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #1a2332;z-index:9999;">
        <button onclick="window.print()" style="padding:8px 25px;background:#1a2332;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;margin:0 6px;">
            🖨️ IMPRIMIR RECIBOS
        </button>
        <button onclick="window.close()" style="padding:8px 25px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
            ✕ FECHAR
        </button>
    </div>
    </body></html>';
    
    return $html;
}


// ============================================
// 8. GERAR RECIBOS DE COBRANÇA
// ============================================
if ($acao == 'recibos') {
    if (empty($devedores)) {
        echo '<div style="padding:40px;text-align:center;font-size:18px;color:#666;font-family:Arial,sans-serif;">
            <div style="font-size:48px;color:#2ecc71;">✅</div>
            <h3>Nenhum devedor encontrado!</h3>
            <p>Não há recibos para gerar.</p>
            <button onclick="window.close()" style="padding:10px 30px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;margin-top:20px;">
                ✕ FECHAR
            </button>
        </div>';
        exit;
    }
    
    echo gerarRecibosCobranca($devedores, $empresa, $ano, $mes);
    exit;
}

// ============================================
// 9. GERAR HTML DO RELATÓRIO
// ============================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Devedores - <?= mesNome($mes) ?>/<?= $ano ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page { size: A4; margin: 12mm 15mm 12mm 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Times New Roman", Arial, sans-serif; background: #f0f2f5; padding: 20px; }
        
        .relatorio-wrapper {
            max-width: 210mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 15mm 20mm;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            min-height: 297mm;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px double #1a2332;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header .escola { font-size: 22pt; font-weight: 700; text-transform: uppercase; color: #1a2332; letter-spacing: 2px; }
        .header .endereco { font-size: 10pt; color: #555; margin-top: 3px; }
        .header .titulo { font-size: 18pt; font-weight: 700; color: #c9a84c; margin-top: 10px; letter-spacing: 3px; }
        .header .info { font-size: 10pt; color: #666; margin-top: 5px; }
        
        .resumo {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .resumo .item { text-align: center; }
        .resumo .item .numero { font-size: 24pt; font-weight: 700; }
        .resumo .item .label { font-size: 9pt; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
        .resumo .item .numero.text-danger { color: #e74c3c; }
        .resumo .item .numero.text-warning { color: #f39c12; }
        .resumo .item .numero.text-primary { color: #1a2332; }
        
        .tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-top: 10px;
        }
        .tabela thead th {
            background: #1a2332;
            color: #ffffff;
            padding: 8px 10px;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #1a2332;
        }
        .tabela tbody td { padding: 6px 10px; border-bottom: 1px solid #eee; font-size: 9pt; }
        .tabela tbody tr:hover { background: #f8f9fa; }
        .tabela .classe-header td {
            padding: 8px 10px;
            background: #e9ecef;
            font-weight: 700;
            border-top: 2px solid #1a2332;
            border-bottom: 2px solid #1a2332;
        }
        .tabela .total-row td {
            background: #f8f9fa;
            font-weight: 700;
            border-top: 2px solid #1a2332;
            padding: 8px 10px;
        }
        
        .status-atrasado { color: #e74c3c; font-weight: 700; }
        .status-pendente { color: #f39c12; font-weight: 700; }
        
        .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #ddd; text-align: center; font-size: 9pt; color: #666; }
        
        .assinatura {
            margin-top: 35px;
            display: flex;
            justify-content: space-around;
        }
        .assinatura .item { text-align: center; min-width: 200px; }
        .assinatura .linha { border-top: 1px solid #000; width: 180px; margin: 30px auto 5px; }
        .assinatura .cargo { font-size: 10pt; color: #555; }
        
        .sem-dados { text-align: center; padding: 40px; }
        .sem-dados .icon { font-size: 64px; color: #2ecc71; }
        .sem-dados h2 { color: #1a2332; margin: 15px 0 10px; }
        .sem-dados p { color: #666; }
        
        .botoes-acoes {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .botoes-acoes .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 12pt;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .botoes-acoes .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .btn-whats { background: #25D366; color: #fff; }
        .btn-whats:hover { background: #1da851; }
        .btn-recibos { background: #f39c12; color: #fff; }
        .btn-recibos:hover { background: #e67e22; }
        .btn-imprimir { background: #1a2332; color: #fff; }
        .btn-imprimir:hover { background: #2d3748; }
        .btn-fechar { background: #e74c3c; color: #fff; }
        .btn-fechar:hover { background: #c0392b; }
        
        .botoes { text-align: center; padding: 15px; margin-top: 20px; }
        .botoes button {
            padding: 10px 30px;
            border: none;
            border-radius: 6px;
            font-size: 13pt;
            font-weight: 600;
            cursor: pointer;
            margin: 0 8px;
        }
        
        @media print {
            .botoes { display: none !important; }
            .botoes-acoes { display: none !important; }
            body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
            .relatorio-wrapper {
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 5mm 10mm !important;
                min-height: auto !important;
            }
            .tabela thead th {
                background: #1a2332 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .tabela .classe-header td {
                background: #e9ecef !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .resumo {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .tabela .total-row td {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

<?php if (empty($devedores)): ?>
    <div class="relatorio-wrapper">
        <div class="sem-dados">
            <div class="icon">✅</div>
            <h2>Nenhum devedor encontrado!</h2>
            <p>Competência: <?= mesNome($mes) ?>/<?= $ano ?></p>
            <p>Todos os alunos estão em dia com suas mensalidades.</p>
            <div class="botoes no-print">
                <button class="btn-fechar" onclick="window.close()">✕ FECHAR</button>
            </div>
        </div>
    </div>
<?php else: 
    $total_devedores = count($devedores);
    $total_valor = array_sum(array_column($devedores, 'valor'));
    $hoje = time();
    $total_atrasados = 0;
    foreach ($devedores as $d) {
        if (strtotime($d['data_vencimento']) < $hoje) {
            $total_atrasados++;
        }
    }
    $total_pendentes = $total_devedores - $total_atrasados;
    
    $tem_telefones = false;
    foreach ($devedores as $d) {
        if (!empty($d['telefone_responsavel']) || !empty($d['telefone_aluno'])) {
            $tem_telefones = true;
            break;
        }
    }
?>

<div class="relatorio-wrapper">
    
    <div class="botoes-acoes no-print">
        <button onclick="window.print()" class="btn btn-imprimir">🖨️ Imprimir Relatório</button>
        <a href="?acao=recibos&ano=<?= $ano ?>&mes=<?= $mes ?>&classe=<?= $filtro_classe ?>&emolumento=<?= $filtro_emolumento ?>" class="btn btn-recibos" target="_blank">
            📋 Gerar Recibos (<?= $total_devedores ?>)
        </a>
        <button onclick="window.close()" class="btn btn-fechar">✕ FECHAR</button>
    </div>
    
    <div class="header">
        <div class="escola"><?= htmlspecialchars($empresa['nome']) ?></div>
        <div class="endereco"><?= htmlspecialchars($empresa['endereco']) ?></div>
        <div class="endereco">Tel: <?= htmlspecialchars($empresa['telefone']) ?> | NIF: <?= htmlspecialchars($empresa['nif']) ?></div>
        <div class="titulo">📋 RELATÓRIO DE DEVEDORES</div>
        <div class="info">Competência: <?= mesNome($mes) ?>/<?= $ano ?> | Gerado em: <?= date('d/m/Y H:i:s') ?></div>
    </div>
    
    <div class="resumo">
        <div class="item">
            <div class="numero text-primary"><?= $total_devedores ?></div>
            <div class="label">Total de Devedores</div>
        </div>
        <div class="item">
            <div class="numero text-primary"><?= formatarMoeda($total_valor) ?></div>
            <div class="label">Valor em Débito</div>
        </div>
        <div class="item">
            <div class="numero text-danger"><?= $total_atrasados ?></div>
            <div class="label">⚠️ Atrasados</div>
        </div>
        <div class="item">
            <div class="numero text-warning"><?= $total_pendentes ?></div>
            <div class="label">⏳ Pendentes</div>
        </div>
    </div>
    
    <table class="tabela">
        <thead>
            <tr>
                <th style="width:35px;">Nº</th>
                <th style="text-align:left;">Aluno</th>
                <th style="text-align:left;">Classe</th>
                <th style="text-align:left;">Turma</th>
                <th style="text-align:left;">Emolumento</th>
                <th style="text-align:left;">Vencimento</th>
                <th style="text-align:right;">Valor</th>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $cont = 0;
        $classe_atual = '';
        foreach ($devedores as $d):
            $classe = $d['classe'] ?? 'Sem Classe';
            if ($classe != $classe_atual) {
                $classe_atual = $classe;
        ?>
            <tr class="classe-header">
                <td colspan="8"><strong>📚 <?= htmlspecialchars($classe) ?>ª Classe</strong></td>
            </tr>
        <?php
            }
            $cont++;
            $is_atrasado = strtotime($d['data_vencimento']) < $hoje;
            $status_class = $is_atrasado ? 'status-atrasado' : 'status-pendente';
            $status_label = $is_atrasado ? '⚠️ ATRASADO' : '⏳ PENDENTE';
        ?>
            <tr>
                <td style="text-align:center;"><?= $cont ?></td>
                <td><strong><?= htmlspecialchars($d['aluno_nome']) ?></strong></td>
                <td><?= htmlspecialchars($d['classe']) ?>ª</td>
                <td><?= htmlspecialchars($d['turma_nome']) ?></td>
                <td><?= htmlspecialchars($d['emolumento_nome'] ?? 'Mensalidade') ?></td>
                <td><?= formatarData($d['data_vencimento']) ?></td>
                <td style="text-align:right;"><strong><?= formatarMoeda($d['valor']) ?></strong></td>
                <td style="text-align:center;"><span class="<?= $status_class ?>"><?= $status_label ?></span></td>
            </tr>
        <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="7" style="text-align:right;">TOTAL GERAL:</td>
                <td style="text-align:right;"><?= formatarMoeda($total_valor) ?></td>
                <td style="text-align:center;"><?= $total_devedores ?> devedores</td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Documento emitido eletronicamente - <?= htmlspecialchars($empresa['nome']) ?> © <?= date('Y') ?></p>
    </div>
    
    <div class="assinatura">
        <div class="item">
            <div class="linha"></div>
            <div class="cargo">Responsável Financeiro</div>
        </div>
        <div class="item">
            <div class="linha"></div>
            <div class="cargo">Director Pedagógico</div>
        </div>
    </div>
    
    <div class="botoes no-print">
        <button onclick="window.print()" style="padding:10px 30px;background:#1a2332;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;margin:0 8px;">
            🖨️ IMPRIMIR RELATÓRIO
        </button>
        <button onclick="window.close()" style="padding:10px 30px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;margin:0 8px;">
            ✕ FECHAR
        </button>
    </div>
    
<?php endif; ?>

</div>

</body>
</html>

<?php
mysqli_close($conn);
?>