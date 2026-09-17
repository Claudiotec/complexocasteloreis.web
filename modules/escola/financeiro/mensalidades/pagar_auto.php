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
// modules/escola/financeiro/mensalidades/pagar_auto.php
// Pagamento automático com geração de fatura
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== FUNÇÃO PARA CONVERTER VALOR POR EXTENSO =====
function valorPorExtenso($valor) {
    $unidades = array('', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove');
    $dezenas = array('', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa');
    $centenas = array('', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos');

    if ($valor == 0) return 'Zero Kwanzas';

    $inteiro = intval($valor);
    $centavos = round(($valor - $inteiro) * 100);

    function converterNumero($num, $unidades, $dezenas, $centenas) {
        if ($num == 0) return '';
        $num_str = strval($num);
        $length = strlen($num_str);
        
        if ($length == 1) return $unidades[$num];
        if ($length == 2) {
            if ($num < 20) {
                $especiais = array(10 => 'dez', 11 => 'onze', 12 => 'doze', 13 => 'treze', 
                                  14 => 'quatorze', 15 => 'quinze', 16 => 'dezesseis', 
                                  17 => 'dezessete', 18 => 'dezoito', 19 => 'dezenove');
                return $especiais[$num] ?? $dezenas[intval($num_str[0])] . (intval($num_str[1]) > 0 ? ' e ' . $unidades[intval($num_str[1])] : '');
            }
            return $dezenas[intval($num_str[0])] . (intval($num_str[1]) > 0 ? ' e ' . $unidades[intval($num_str[1])] : '');
        }
        if ($length == 3) {
            $centena = intval($num_str[0]);
            $resto = intval(substr($num_str, 1));
            $palavras = $centena == 1 && $resto == 0 ? 'cem' : $centenas[$centena];
            if ($resto > 0) $palavras .= ' e ' . converterNumero($resto, $unidades, $dezenas, $centenas);
            return $palavras;
        }
        if ($length >= 4 && $length <= 6) {
            $milhar = intval(substr($num_str, 0, $length - 3));
            $resto = intval(substr($num_str, -3));
            $palavras = converterNumero($milhar, $unidades, $dezenas, $centenas) . ' mil';
            if ($resto > 0) $palavras .= ' e ' . converterNumero($resto, $unidades, $dezenas, $centenas);
            return $palavras;
        }
        return $num;
    }

    $palavras = '';
    if ($inteiro > 0) {
        $palavras = converterNumero($inteiro, $unidades, $dezenas, $centenas) . ' Kwanzas';
    }
    if ($centavos > 0) {
        $palavras .= ' e ' . converterNumero($centavos, $unidades, $dezenas, $centenas) . ' Cêntimos';
    }
    return ucfirst($palavras);
}

// ===== FUNÇÃO PARA GERAR FATURA =====
function gerarFatura($dados) {
    $nome_escola = $dados['nome_escola'] ?? 'SoftGest Web Escola';
    $endereco = $dados['endereco'] ?? 'Luanda, Angola';
    $contacto = $dados['contacto'] ?? '+244 900 000 000';
    $email = $dados['email'] ?? 'info@softgest.com';
    $nif = $dados['nif'] ?? '500-000-000';

    $aluno_id = $dados['aluno_id'] ?? '';
    $aluno_nome = $dados['aluno_nome'] ?? '';
    $aluno_classe = $dados['aluno_classe'] ?? '';
    $aluno_turma = $dados['aluno_turma'] ?? '';
    $aluno_genero = $dados['aluno_genero'] ?? '';

    $descricao = $dados['descricao'] ?? 'Mensalidade';
    $valor_base = $dados['valor_base'] ?? 0;
    $desconto = $dados['desconto'] ?? 0;
    $valor_liquido = $dados['valor_liquido'] ?? 0;
    $data_pagamento = $dados['data_pagamento'] ?? date('d/m/Y');
    $forma_pagamento = $dados['forma_pagamento'] ?? 'Dinheiro';
    $referencia = $dados['referencia'] ?? '';
    $observacoes = $dados['observacoes'] ?? '';
    $numero_fatura = $dados['numero_fatura'] ?? 'FAT-' . date('YmdHis');
    $mes_referencia = $dados['mes_referencia'] ?? '-';
    $ano_referencia = $dados['ano_referencia'] ?? date('Y');

    $data_emissao = date('d/m/Y H:i:s');
    $valor_base_formatado = number_format($valor_base, 2, ',', '.');
    $desconto_formatado = number_format($desconto, 2, ',', '.');
    $valor_liquido_formatado = number_format($valor_liquido, 2, ',', '.');
    $porcentagem_desconto = $valor_base > 0 ? round(($desconto / $valor_base) * 100, 1) : 0;
    $valor_extenso = valorPorExtenso($valor_liquido);

    $html = '
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>FATURA Nº ' . $numero_fatura . '</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: "Times New Roman", Times, serif;
                background: white;
                padding: 20px;
                display: flex;
                justify-content: center;
            }
            .fatura {
                max-width: 210mm;
                width: 100%;
                background: white;
                padding: 25px 30px;
                border: 1px solid #ccc;
                position: relative;
            }
            .status-pago {
                position: absolute;
                top: 30px;
                right: 30px;
                background: #27ae60;
                color: white;
                padding: 5px 15px;
                border-radius: 4px;
                font-weight: bold;
                font-size: 14px;
                transform: rotate(15deg);
                box-shadow: 0 2px 10px rgba(39,174,96,0.3);
            }
            .header {
                border-bottom: 3px double #1a2332;
                padding-bottom: 12px;
                margin-bottom: 15px;
            }
            .header-top {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
            }
            .logo-area {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .logo-icon {
                width: 45px;
                height: 45px;
                background: #1a2332;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #c9a84c;
                font-size: 22px;
                font-weight: bold;
                border: 2px solid #c9a84c;
            }
            .titulo-doc {
                font-size: 22px;
                font-weight: bold;
                color: #1a2332;
                letter-spacing: 3px;
            }
            .subtitulo-doc {
                font-size: 11px;
                color: #666;
                letter-spacing: 2px;
            }
            .numero-fatura {
                text-align: right;
                font-size: 13px;
                padding: 5px 15px;
                border: 1px solid #ccc;
                border-radius: 4px;
                background: #f9f9f9;
            }
            .numero-fatura strong { color: #1a2332; font-size: 14px; }
            .info-escola {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 10px;
                padding: 6px 0;
                border-bottom: 1px solid #ddd;
            }
            .info-escola .escola h2 { font-size: 16px; color: #1a2332; }
            .info-escola .escola p { font-size: 12px; color: #555; margin: 2px 0; }
            .nif-box {
                border: 1px solid #1a2332;
                padding: 4px 15px;
                font-size: 13px;
                font-weight: bold;
                background: #f8fafc;
                height: fit-content;
            }
            .dados-aluno {
                border: 1px solid #1a2332;
                padding: 10px 14px;
                margin-bottom: 12px;
                background: #fafafa;
            }
            .dados-aluno .titulo-secao {
                font-weight: bold;
                font-size: 14px;
                text-align: center;
                border-bottom: 1px solid #1a2332;
                padding-bottom: 4px;
                margin-bottom: 6px;
                letter-spacing: 2px;
            }
            .dados-aluno .linha {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 5px;
                font-size: 13px;
                padding: 2px 0;
            }
            .dados-aluno .label { font-weight: 600; color: #333; }
            .dados-aluno .valor { color: #1a2332; }
            .tabela {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12px;
                font-size: 13px;
            }
            .tabela th {
                background: #1a2332;
                color: white;
                padding: 6px 8px;
                text-align: center;
                border: 1px solid #1a2332;
                font-size: 12px;
                letter-spacing: 1px;
            }
            .tabela td {
                padding: 6px 8px;
                border: 1px solid #ccc;
                text-align: center;
            }
            .tabela .text-right { text-align: right; padding-right: 12px; }
            .tabela .text-left { text-align: left; padding-left: 12px; }
            .tabela .total-row { background: #f8fafc; font-weight: bold; }
            .tabela .total-row td { border-top: 2px solid #1a2332; padding: 8px; font-size: 14px; }
            .tabela .valor-total { color: #c0392b; font-size: 16px; font-weight: bold; }
            .resumo {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                padding: 10px;
                background: #f8fafc;
                border: 1px solid #ddd;
                margin-bottom: 12px;
            }
            .resumo .coluna { flex: 1; min-width: 150px; }
            .resumo .coluna p { font-size: 12px; margin: 2px 0; color: #555; }
            .resumo .coluna strong { color: #1a2332; }
            .resumo .valor-extenso {
                font-size: 13px;
                font-style: italic;
                color: #1a2332;
                border-top: 1px dashed #ccc;
                padding-top: 5px;
                margin-top: 5px;
            }
            .footer {
                border-top: 3px double #1a2332;
                padding-top: 10px;
                margin-top: 10px;
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                font-size: 11px;
                color: #666;
            }
            .footer .assinatura { text-align: center; flex: 1; min-width: 150px; }
            .footer .assinatura .linha { width: 200px; border-top: 1px solid #1a2332; margin: 8px auto 4px; }
            .footer .info-rodape { text-align: right; font-size: 10px; color: #999; }
            @media print {
                body { padding: 0; }
                .fatura { border: none; padding: 15px 20px; }
                .no-print { display: none !important; }
                .tabela th { background: #1a2332 !important; color: white !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                .total-row { background: #f8fafc !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                .status-pago { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            }
            @media (max-width: 600px) {
                .fatura { padding: 10px; }
                .header-top { flex-direction: column; text-align: center; }
                .numero-fatura { text-align: center; }
                .info-escola { flex-direction: column; text-align: center; }
                .dados-aluno .linha { flex-direction: column; text-align: center; }
                .resumo { flex-direction: column; text-align: center; }
                .footer { flex-direction: column; text-align: center; }
                .tabela { font-size: 11px; }
                .tabela th, .tabela td { padding: 4px; }
                .status-pago { position: relative; top: 0; right: 0; display: inline-block; margin-bottom: 10px; transform: none; }
            }
        </style>
    </head>
    <body>
        <div class="fatura">
            <div class="status-pago">✅ PAGO</div>
            
            <div class="header">
                <div class="header-top">
                    <div class="logo-area">
                        <div class="logo-icon">🎓</div>
                        <div>
                            <div class="titulo-doc">FATURA</div>
                            <div class="subtitulo-doc">COMPROVANTE DE PAGAMENTO</div>
                        </div>
                    </div>
                    <div class="numero-fatura">
                        <strong>Nº:</strong> ' . $numero_fatura . '<br>
                        <span style="font-size:11px;">Emissão: ' . $data_emissao . '</span>
                    </div>
                </div>
            </div>

            <div class="info-escola">
                <div class="escola">
                    <h2>' . htmlspecialchars($nome_escola) . '</h2>
                    <p>📍 ' . htmlspecialchars($endereco) . '</p>
                    <p>📞 ' . htmlspecialchars($contacto) . ' | ✉ ' . htmlspecialchars($email) . '</p>
                </div>
                <div class="nif-box">NIF: ' . htmlspecialchars($nif) . '</div>
            </div>

            <div class="dados-aluno">
                <div class="titulo-secao">DADOS DO ALUNO</div>
                <div class="linha">
                    <span><span class="label">Nome:</span> <span class="valor">' . htmlspecialchars($aluno_nome) . '</span></span>
                    <span><span class="label">ID:</span> <span class="valor">#' . htmlspecialchars($aluno_id) . '</span></span>
                    <span><span class="label">Classe:</span> <span class="valor">' . htmlspecialchars($aluno_classe) . '</span></span>
                </div>
                <div class="linha">
                    <span><span class="label">Turma:</span> <span class="valor">' . htmlspecialchars($aluno_turma) . '</span></span>
                    ' . ($aluno_genero ? '<span><span class="label">Gênero:</span> <span class="valor">' . ($aluno_genero == 'M' ? 'Masculino' : 'Feminino') . '</span></span>' : '') . '
                    <span><span class="label">Data Pagamento:</span> <span class="valor">' . $data_pagamento . '</span></span>
                </div>
                ' . ($mes_referencia != '-' ? '<div class="linha"><span class="label">Mês Referência:</span> <span class="valor">' . htmlspecialchars($mes_referencia) . '/' . $ano_referencia . '</span></div>' : '') . '
            </div>

            <table class="tabela">
                <thead>
                    <tr>
                        <th style="width:40px;">Item</th>
                        <th style="width:35%;">Descrição</th>
                        <th style="width:15%;">Mês/Ano</th>
                        <th style="width:15%;">Valor Base</th>
                        <th style="width:10%;">Desconto</th>
                        <th style="width:15%;">Valor Líquido</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td class="text-left">' . htmlspecialchars($descricao) . '</td>
                        <td>' . htmlspecialchars($mes_referencia) . '/' . $ano_referencia . '</td>
                        <td class="text-right">' . $valor_base_formatado . ' Kz</td>
                        <td>' . ($desconto > 0 ? $porcentagem_desconto . '%' : '-') . '</td>
                        <td class="text-right"><strong>' . $valor_liquido_formatado . ' Kz</strong></td>
                    </tr>
                    ' . ($desconto > 0 ? '
                    <tr style="color:#27ae60;">
                        <td colspan="3" class="text-right" style="font-weight:bold;">DESCONTO APLICADO</td>
                        <td class="text-right" style="font-weight:bold;">-' . $desconto_formatado . ' Kz</td>
                        <td></td>
                        <td></td>
                    </tr>
                    ' : '') . '
                    <tr class="total-row">
                        <td colspan="4" class="text-right" style="font-size:14px;">TOTAL A PAGAR</td>
                        <td></td>
                        <td class="text-right valor-total">' . $valor_liquido_formatado . ' Kz</td>
                    </tr>
                </tbody>
            </table>

            <div class="resumo">
                <div class="coluna">
                    <p><strong>📅 Data Pagamento:</strong> ' . $data_pagamento . '</p>
                    <p><strong>💳 Forma:</strong> ' . htmlspecialchars($forma_pagamento) . '</p>
                    ' . ($referencia ? '<p><strong>📄 Referência:</strong> ' . htmlspecialchars($referencia) . '</p>' : '') . '
                </div>
                <div class="coluna">
                    <p><strong>✅ Status:</strong> <span style="color:#27ae60;font-weight:bold;">CONFIRMADO</span></p>
                    <p><strong>📋 Nº Fatura:</strong> ' . $numero_fatura . '</p>
                    ' . ($observacoes ? '<p><strong>📝 Obs.:</strong> ' . htmlspecialchars($observacoes) . '</p>' : '') . '
                </div>
                <div class="coluna">
                    <div class="valor-extenso">
                        <strong>Valor por extenso:</strong><br>
                        ' . $valor_extenso . '
                    </div>
                </div>
            </div>

            <div class="footer">
                <div class="assinatura">
                    <p style="font-size:12px;font-weight:bold;">Assinatura do Responsável</p>
                    <div class="linha"></div>
                    <p style="font-size:10px;color:#999;">_________________________________</p>
                </div>
                <div class="info-rodape">
                    <p>Documento emitido eletronicamente</p>
                    <p>' . htmlspecialchars($nome_escola) . ' © ' . date('Y') . '</p>
                    <p style="font-size:9px;color:#bbb;">Este documento serve como comprovante de pagamento</p>
                </div>
            </div>
        </div>

        <div style="text-align:center;margin-top:15px;" class="no-print">
            <button onclick="window.print()" style="padding:10px 30px;background:#1a2332;color:white;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;">
                🖨️ IMPRIMIR
            </button>
            <a href="index.php" style="padding:10px 30px;background:#27ae60;color:white;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;margin-left:10px;">
                ✅ VOLTAR
            </a>
        </div>
    </body>
    </html>
    ';

    return $html;
}

// ===== VERIFICAR ID =====
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?erro=ID não informado');
    exit;
}

$id = intval($_GET['id']);
$forma_pagamento = isset($_GET['forma']) ? $_GET['forma'] : 'dinheiro';

// ===== BUSCAR DADOS DA MENSALIDADE =====
try {
    $stmt = $pdo->prepare("
        SELECT m.*, a.nome as aluno_nome, a.Classe, a.TURMA, a.genero
        FROM mensalidades m
        LEFT JOIN alunos a ON m.aluno_id = a.id
        WHERE m.id = ?
    ");
    $stmt->execute([$id]);
    $mensalidade = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mensalidade) {
        header('Location: index.php?erro=Mensalidade não encontrada');
        exit;
    }
    
    if ($mensalidade['status'] == 'pago') {
        header('Location: index.php?erro=Esta mensalidade já está paga');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: index.php?erro=Erro ao buscar dados: ' . $e->getMessage());
    exit;
}

// ===== PROCESSAR PAGAMENTO AUTOMÁTICO =====
$pagamento_realizado = false;
$numero_fatura = '';
$erro = '';

try {
    $pdo->beginTransaction();
    
    $valor_pago = $mensalidade['valor'];
    $data_pagamento = date('Y-m-d');
    $referencia = 'AUTO-' . date('Ymd') . '-' . str_pad($id, 6, '0', STR_PAD_LEFT);
    $observacoes = 'Pagamento automático - ' . date('d/m/Y H:i:s');
    $mes_referencia = $mensalidade['mes'];
    $ano_referencia = $mensalidade['ano'];
    
    // Buscar emolumento_id (usar 5 como padrão se não tiver)
    $emolumento_id = 5; // ID padrão para mensalidade
    
    // Buscar dados do aluno
    $stmt_aluno = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
    $stmt_aluno->execute([$mensalidade['aluno_id']]);
    $aluno = $stmt_aluno->fetch(PDO::FETCH_ASSOC);
    
    // INSERIR PAGAMENTO (sem mensalidade_id)
    $stmt = $pdo->prepare("
        INSERT INTO pagamentos 
        (aluno_id, emolumento_id, valor, data_pagamento, forma_pagamento, 
         referencia, observacoes, mes_referencia, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmado', NOW())
    ");
    $stmt->execute([
        $mensalidade['aluno_id'],
        $emolumento_id,
        $valor_pago,
        $data_pagamento,
        $forma_pagamento,
        $referencia,
        $observacoes,
        $mes_referencia
    ]);
    $pagamento_id = $pdo->lastInsertId();
    
    // Atualizar mensalidade para PAGO
    $stmt = $pdo->prepare("UPDATE mensalidades SET status = 'pago', valor_pago = valor WHERE id = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();
    $pagamento_realizado = true;
    $numero_fatura = 'FAT-' . date('Ymd') . '-' . str_pad($id, 6, '0', STR_PAD_LEFT);
    
} catch (Exception $e) {
    $pdo->rollBack();
    $erro = '❌ Erro ao processar pagamento: ' . $e->getMessage();
}

// ===== GERAR FATURA =====
$fatura_html = '';
if ($pagamento_realizado) {
    $dados_fatura = [
        'nome_escola' => 'SoftGest Web Escola',
        'endereco' => 'Luanda, Angola',
        'contacto' => '+244 900 000 000',
        'email' => 'info@softgest.com',
        'nif' => '500-000-000',
        'aluno_id' => $mensalidade['aluno_id'],
        'aluno_nome' => $mensalidade['aluno_nome'] ?? 'N/A',
        'aluno_classe' => $mensalidade['Classe'] ?? 'N/A',
        'aluno_turma' => $mensalidade['TURMA'] ?? 'N/A',
        'aluno_genero' => $mensalidade['genero'] ?? '',
        'descricao' => 'Mensalidade Escolar - ' . $mes_referencia . '/' . $ano_referencia,
        'valor_base' => $mensalidade['valor'],
        'desconto' => 0,
        'valor_liquido' => $mensalidade['valor'],
        'data_pagamento' => date('d/m/Y', strtotime($data_pagamento)),
        'forma_pagamento' => ucfirst($forma_pagamento),
        'referencia' => $referencia,
        'observacoes' => $observacoes,
        'numero_fatura' => $numero_fatura,
        'mes_referencia' => $mes_referencia,
        'ano_referencia' => $ano_referencia
    ];
    $fatura_html = gerarFatura($dados_fatura);
}

include '../../includes/header_escola.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    .page-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    .page-header .subtitle {
        color: #94a3b8;
        font-size: 14px;
        margin: 2px 0 0;
    }
    .btn {
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
    }
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    .btn-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
    }
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    .btn-success:hover {
        background: #27ae60;
        transform: translateY(-2px);
    }
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .info-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 20px;
    }
    .info-box .row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }
    .info-box .label {
        font-size: 12px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .info-box .value {
        font-size: 16px;
        font-weight: 600;
        color: #1a2332;
    }
    .fatura-container {
        max-width: 210mm;
        margin: 20px auto;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .fatura-iframe {
        width: 100%;
        height: 900px;
        border: none;
    }
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .info-box .row {
            grid-template-columns: 1fr 1fr;
        }
        .fatura-iframe {
            height: 500px;
        }
    }
    @media print {
        .no-print { display: none !important; }
        .fatura-iframe { height: auto !important; }
        .fatura-container { box-shadow: none !important; border: 1px solid #ddd !important; }
        .page-header { display: none !important; }
        .info-box { display: none !important; }
        .alert { display: none !important; }
    }
</style>

<div class="page-header no-print">
    <div>
        <h1>💰 Pagamento Realizado</h1>
        <p class="subtitle">
            Mensalidade #<?= str_pad($id, 6, '0', STR_PAD_LEFT) ?> - 
            <?= htmlspecialchars($mensalidade['aluno_nome'] ?? 'N/A') ?>
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</button>
        <a href="index.php" class="btn btn-success">✅ Voltar</a>
    </div>
</div>

<?php if ($erro): ?>
<div class="alert alert-danger"><?= $erro ?></div>
<?php endif; ?>

<?php if ($pagamento_realizado): ?>
<!-- Info do Pagamento -->
<div class="info-box no-print">
    <div class="row">
        <div>
            <div class="label">Nº Fatura</div>
            <div class="value"><?= $numero_fatura ?></div>
        </div>
        <div>
            <div class="label">Valor Pago</div>
            <div class="value" style="color: #27ae60;">Kz <?= number_format($mensalidade['valor'], 2, ',', '.') ?></div>
        </div>
        <div>
            <div class="label">Forma de Pagamento</div>
            <div class="value"><?= ucfirst($forma_pagamento) ?></div>
        </div>
        <div>
            <div class="label">Data</div>
            <div class="value"><?= date('d/m/Y') ?></div>
        </div>
        <div>
            <div class="label">Status</div>
            <div class="value" style="color: #27ae60;">✅ CONFIRMADO</div>
        </div>
    </div>
</div>

<!-- Fatura -->
<div class="fatura-container">
    <iframe class="fatura-iframe" srcdoc="<?= htmlspecialchars($fatura_html) ?>"></iframe>
</div>
<?php else: ?>
<div class="alert alert-danger">
    <strong>❌ Erro:</strong> Não foi possível processar o pagamento.
</div>
<?php endif; ?>

<?php include '../../includes/footer_escola.php'; ?>