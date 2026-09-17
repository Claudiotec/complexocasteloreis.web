<?php
// ============================================
// modules/escola/financeiro/pagamentos/add.php - Novo Pagamento
// ============================================

// Carregar configurações
require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'criar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== FUNÇÃO PARA REMOVER ACENTOS =====
function removerAcentos($string) {
    $mapa = array(
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C'
    );
    return strtr($string, $mapa);
}

// ===== FUNÇÃO PARA GERAR FATURA ESTILO AGT =====
function gerarFaturaAGT($dados) {
    $nome_escola = $dados['nome_escola'] ?? 'Sistema Escolar';
    $endereco = $dados['endereco'] ?? '';
    $contacto = $dados['contacto'] ?? '';
    $email = $dados['email'] ?? '';
    $nif = $dados['nif'] ?? '';

    $aluno_id = $dados['aluno_id'] ?? '';
    $aluno_nome = $dados['aluno_nome'] ?? '';
    $aluno_classe = $dados['aluno_classe'] ?? '';
    $aluno_turma = $dados['aluno_turma'] ?? '';
    $aluno_genero = $dados['aluno_genero'] ?? '';

    $emolumento = $dados['emolumento'] ?? '';
    $valor_base = $dados['valor_base'] ?? 0;
    $desconto = $dados['desconto'] ?? 0;
    $valor_liquido = $dados['valor_liquido'] ?? 0;
    $data_pagamento = $dados['data_pagamento'] ?? date('d/m/Y');
    $forma_pagamento = $dados['forma_pagamento'] ?? 'Dinheiro';
    $referencia = $dados['referencia'] ?? '';
    $observacoes = $dados['observacoes'] ?? '';
    $numero_fatura = $dados['numero_fatura'] ?? 'FAT-' . date('YmdHis');
    $mes_referencia = $dados['mes_referencia'] ?? '-';

    $data_emissao = date('d/m/Y H:i:s');
    $valor_base_formatado = number_format($valor_base, 2, ',', '.');
    $desconto_formatado = number_format($desconto, 2, ',', '.');
    $valor_liquido_formatado = number_format($valor_liquido, 2, ',', '.');

    $porcentagem_desconto = $valor_base > 0 ? round(($desconto / $valor_base) * 100, 1) : 0;

    // Por extenso
    $valor_extenso = valorPorExtenso($valor_liquido);

    $html = '
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Fatura - ' . $numero_fatura . '</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: "Times New Roman", Times, serif;
                background: #f0f0f0;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .fatura-container {
                max-width: 210mm;
                width: 100%;
                background: white;
                padding: 15px 20px 20px 20px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                border: 1px solid #ccc;
            }
            .header {
                border-bottom: 3px double #1a2332;
                padding-bottom: 10px;
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
                width: 50px;
                height: 50px;
                background: #1a2332;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #c9a84c;
                font-size: 24px;
                font-weight: bold;
                border: 2px solid #c9a84c;
            }
            .titulo-documento {
                font-size: 22px;
                font-weight: bold;
                color: #1a2332;
                letter-spacing: 2px;
            }
            .subtitulo-documento {
                font-size: 11px;
                color: #666;
                letter-spacing: 1px;
            }
            .numero-fatura {
                text-align: right;
                font-size: 13px;
                color: #555;
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
                margin-bottom: 12px;
                padding: 8px 0;
                border-bottom: 1px solid #ddd;
            }
            .info-escola .escola { font-size: 14px; }
            .info-escola .escola h2 { font-size: 16px; color: #1a2332; margin-bottom: 2px; }
            .info-escola .escola p { font-size: 12px; color: #555; margin: 2px 0; }
            .info-escola .nif-box {
                border: 1px solid #1a2332;
                padding: 4px 12px;
                font-size: 13px;
                font-weight: bold;
                text-align: center;
                background: #f8fafc;
                height: fit-content;
            }
            .dados-aluno {
                border: 1px solid #1a2332;
                padding: 10px 12px;
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
            .tabela-itens {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12px;
                font-size: 13px;
            }
            .tabela-itens th {
                background: #1a2332;
                color: white;
                padding: 6px 8px;
                text-align: center;
                font-weight: bold;
                border: 1px solid #1a2332;
                font-size: 12px;
                letter-spacing: 1px;
            }
            .tabela-itens td {
                padding: 6px 8px;
                border: 1px solid #ccc;
                text-align: center;
            }
            .tabela-itens .text-right { text-align: right; padding-right: 12px; }
            .tabela-itens .text-left { text-align: left; padding-left: 12px; }
            .tabela-itens .total-row { background: #f8fafc; font-weight: bold; }
            .tabela-itens .total-row td { border-top: 2px solid #1a2332; padding: 8px 8px; font-size: 14px; }
            .tabela-itens .label-total { text-align: right; font-size: 14px; }
            .tabela-itens .valor-total { color: #c0392b; font-size: 16px; font-weight: bold; }
            .tabela-itens .desconto-row td { color: #27ae60; }
            .resumo {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 12px;
                padding: 10px;
                background: #f8fafc;
                border: 1px solid #ddd;
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
            .footer .assinatura .linha { width: 200px; border-top: 1px solid #1a2332; margin: 8px auto 4px auto; }
            .footer .info-rodape { text-align: right; font-size: 10px; color: #999; }
            @media print {
                body { background: white; padding: 0; }
                .fatura-container { box-shadow: none; border: none; padding: 10px 15px; }
                .no-print { display: none !important; }
                .tabela-itens th { background: #1a2332 !important; color: white !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                .total-row { background: #f8fafc !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            }
            @media (max-width: 600px) {
                body { padding: 5px; }
                .fatura-container { padding: 10px; }
                .header-top { flex-direction: column; text-align: center; }
                .numero-fatura { text-align: center; }
                .info-escola { flex-direction: column; text-align: center; }
                .dados-aluno .linha { flex-direction: column; text-align: center; }
                .resumo { flex-direction: column; text-align: center; }
                .footer { flex-direction: column; text-align: center; }
                .tabela-itens { font-size: 11px; }
                .tabela-itens th, .tabela-itens td { padding: 4px 4px; }
            }
        </style>
    </head>
    <body>
        <div class="fatura-container">
            <div class="header">
                <div class="header-top">
                    <div class="logo-area">
                        <div class="logo-icon">🎓</div>
                        <div>
                            <div class="titulo-documento">FATURA</div>
                            <div class="subtitulo-documento">DOCUMENTO DE PAGAMENTO</div>
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
                    <span><span class="label">Data Pagamento:</span> <span class="valor">' . date('d/m/Y', strtotime($data_pagamento)) . '</span></span>
                </div>
                ' . ($mes_referencia != '-' ? '<div class="linha"><span class="label">Mês Referência:</span> <span class="valor">' . htmlspecialchars($mes_referencia) . '</span></div>' : '') . '
            </div>

            <table class="tabela-itens">
                <thead>
                    <tr>
                        <th style="width:50px;">Item</th>
                        <th style="width:30%;">Descrição</th>
                        <th style="width:15%;">Mês</th>
                        <th style="width:15%;">Valor Base</th>
                        <th style="width:10%;">Desconto %</th>
                        <th style="width:15%;">Valor Líquido</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td class="text-left">' . htmlspecialchars($emolumento) . '</td>
                        <td>' . htmlspecialchars($mes_referencia) . '</td>
                        <td class="text-right">' . $valor_base_formatado . ' Kz</td>
                        <td>' . ($desconto > 0 ? $porcentagem_desconto . '%' : '0%') . '</td>
                        <td class="text-right"><strong>' . $valor_liquido_formatado . ' Kz</strong></td>
                    </tr>
                    ' . ($desconto > 0 ? '
                    <tr class="desconto-row">
                        <td colspan="4" class="text-right" style="font-weight:bold;">DESCONTO APLICADO</td>
                        <td class="text-right" style="color:#27ae60;font-weight:bold;">-' . $desconto_formatado . ' Kz</td>
                        <td></td>
                    </tr>
                    ' : '') . '
                    <tr class="total-row">
                        <td colspan="4" class="label-total">TOTAL A PAGAR</td>
                        <td></td>
                        <td class="text-right valor-total">' . $valor_liquido_formatado . ' Kz</td>
                    </tr>
                </tbody>
            </table>

            <div class="resumo">
                <div class="coluna">
                    <p><strong>📅 Data Pagamento:</strong> ' . date('d/m/Y', strtotime($data_pagamento)) . '</p>
                    <p><strong>💳 Forma:</strong> ' . htmlspecialchars($forma_pagamento) . '</p>
                    ' . ($referencia ? '<p><strong>📄 Referência:</strong> ' . htmlspecialchars($referencia) . '</p>' : '') . '
                </div>
                <div class="coluna">
                    <p><strong>✅ Status:</strong> <span style="color:#27ae60;font-weight:bold;">PAGO</span></p>
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
            <button onclick="window.close()" style="padding:10px 30px;background:#e74c3c;color:white;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;margin-left:10px;">
                ✕ FECHAR
            </button>
        </div>
    </body>
    </html>
    ';

    return $html;
}

// ===== FUNÇÃO PARA CONVERTER VALOR POR EXTENSO =====
function valorPorExtenso($valor) {
    $unidades = array('', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove');
    $dezenas = array('', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa');
    $centenas = array('', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos');

    if ($valor == 0) return 'Zero Kwanzas';

    $inteiro = intval($valor);
    $centavos = round(($valor - $inteiro) * 100);

    $palavras = '';
    
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

    if ($inteiro > 0) {
        $palavras = converterNumero($inteiro, $unidades, $dezenas, $centenas) . ' Kwanzas';
    }

    if ($centavos > 0) {
        $palavras .= ' e ' . converterNumero($centavos, $unidades, $dezenas, $centenas) . ' Cêntimos';
    }

    return ucfirst($palavras);
}

// ===== FUNÇÃO PARA SALVAR FATURA =====
function salvarFatura($html, $numero_fatura) {
    $pasta_faturas = '../../../../faturas/';
    if (!file_exists($pasta_faturas)) {
        mkdir($pasta_faturas, 0777, true);
    }

    $nome_arquivo = 'fatura_' . $numero_fatura . '.html';
    $caminho_completo = $pasta_faturas . $nome_arquivo;

    file_put_contents($caminho_completo, $html);
    return $caminho_completo;
}

// ===== FUNÇÃO PARA VERIFICAR PAGAMENTO DUPLICADO =====
function verificarPagamentoDuplicado($pdo, $aluno_id, $emolumento_id, $mes_referencia) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM pagamentos p
            INNER JOIN emolumentos e ON p.emolumento_id = e.id
            WHERE p.aluno_id = ? 
            AND p.emolumento_id = ?
            AND (e.nome LIKE 'Propina%' OR e.nome LIKE 'Transporte%' OR e.nome LIKE 'Mensalidade%')
            AND p.mes_referencia = ?
            AND p.status = 'confirmado'
        ");
        $stmt->execute([$aluno_id, $emolumento_id, $mes_referencia]);
        $resultado = $stmt->fetch();
        return $resultado['total'] > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ===== FUNÇÃO PARA VERIFICAR SE PRECISA DE MÊS =====
function precisaMes($nome_emolumento) {
    $nomes = ['Propina', 'Transporte', 'Mensalidade'];
    foreach ($nomes as $nome) {
        if (stripos($nome_emolumento, $nome) !== false) {
            return true;
        }
    }
    return false;
}

// ===== BUSCAR DADOS =====
$alunos = [];
$emolumentos = [];
$aluno_selecionado = null;
$alunos_busca = [];
$aluno_busca = '';
$erro = '';
$sucesso = '';
$fatura_html = '';
$mostrar_fatura = false;
$desconto_aplicado = 0;
$pagamento_duplicado = false;

// ===== PROCESSAR BUSCA (GET) =====
if (isset($_GET['busca']) && !empty(trim($_GET['busca']))) {
    $aluno_busca = trim($_GET['busca']);
    $aluno_busca_sem_acento = removerAcentos($aluno_busca);

    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, genero, data_nascimento, turma, classe, curso, status
            FROM alunos 
            WHERE status = 'ativo' 
            AND (
                id = ? OR
                id LIKE ? OR
                nome LIKE ? OR 
                nome LIKE ?
            )
            ORDER BY 
                CASE 
                    WHEN id = ? THEN 0
                    WHEN nome LIKE ? THEN 1
                    WHEN nome LIKE ? THEN 2
                    ELSE 3
                END,
                nome 
            LIMIT 20
        ");

        $busca_like = "%{$aluno_busca}%";
        $busca_start = "{$aluno_busca}%";

        $stmt->execute([
            $aluno_busca, $busca_like, $busca_like, $busca_start,
            $aluno_busca, $busca_start, $busca_like
        ]);

        $alunos_busca = $stmt->fetchAll();

        if (empty($alunos_busca) && $aluno_busca_sem_acento != $aluno_busca) {
            $stmt = $pdo->prepare("
                SELECT id, nome, genero, data_nascimento, turma, classe, curso, status
                FROM alunos 
                WHERE status = 'ativo' 
                AND (nome LIKE ? OR nome LIKE ?)
                ORDER BY nome 
                LIMIT 20
            ");
            $busca_sem_acento_like = "%{$aluno_busca_sem_acento}%";
            $busca_sem_acento_start = "{$aluno_busca_sem_acento}%";
            $stmt->execute([$busca_sem_acento_like, $busca_sem_acento_start]);
            $alunos_busca = $stmt->fetchAll();
        }

        if (empty($alunos_busca)) {
            $partes_nome = explode(' ', $aluno_busca);
            if (count($partes_nome) > 1) {
                $conditions = [];
                $params = [];

                foreach ($partes_nome as $parte) {
                    if (strlen($parte) >= 2) {
                        $conditions[] = "nome LIKE ?";
                        $params[] = "%{$parte}%";
                    }
                }

                if (!empty($conditions)) {
                    $sql = "SELECT id, nome, genero, data_nascimento, turma, classe, curso, status
                            FROM alunos 
                            WHERE status = 'ativo' 
                            AND (" . implode(" OR ", $conditions) . ")
                            ORDER BY nome 
                            LIMIT 20";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $alunos_busca = $stmt->fetchAll();
                }
            }
        }

        if (count($alunos_busca) == 1) {
            $aluno_selecionado = $alunos_busca[0];
        }

    } catch (Exception $e) {
        $erro = 'Erro na busca: ' . $e->getMessage();
    }
}

// Buscar todos os alunos e emolumentos
try {
    if (empty($aluno_busca)) {
        $alunos = $pdo->query("SELECT id, nome, genero, turma, classe, curso FROM alunos WHERE status = 'ativo' ORDER BY nome LIMIT 100")->fetchAll();
    } else {
        $alunos = $alunos_busca;
    }
    // Buscar emolumentos com nome e valor
    $emolumentos = $pdo->query("SELECT id, nome, valor FROM emolumentos WHERE status = 'ativo' ORDER BY nome")->fetchAll();
} catch (Exception $e) {
    $erro = 'Erro ao carregar dados: ' . $e->getMessage();
}

// ===== PROCESSAR FORMULÁRIO (POST) =====
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $aluno_id = $_POST['aluno_id'] ?? null;
    $emolumento_id = $_POST['emolumento_id'] ?? null;
    $valor_base = $_POST['valor_base'] ?? 0;
    $desconto_aplicado = $_POST['desconto'] ?? 0;
    $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    $forma_pagamento = $_POST['forma_pagamento'] ?? 'dinheiro';
    $referencia = $_POST['referencia'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    $mes_referencia = $_POST['mes_referencia'] ?? '-';

    // Calcular valor líquido
    $valor_liquido = $valor_base - $desconto_aplicado;
    if ($valor_liquido < 0) $valor_liquido = 0;

    if (!$aluno_id || !$emolumento_id || $valor_base <= 0) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        try {
            // Buscar dados do emolumento para verificar se é propina/transporte/mensalidade
            $stmt_emol = $pdo->prepare("SELECT nome FROM emolumentos WHERE id = ?");
            $stmt_emol->execute([$emolumento_id]);
            $emolumento = $stmt_emol->fetch();
            $nome_emolumento = $emolumento['nome'] ?? '';

            // Verificar se precisa de mês
            $precisa_mes = precisaMes($nome_emolumento);

            // Verificar duplicidade apenas para propina, transporte e mensalidade
            if ($precisa_mes && $mes_referencia != '-') {
                if (verificarPagamentoDuplicado($pdo, $aluno_id, $emolumento_id, $mes_referencia)) {
                    $erro = '⚠️ ATENÇÃO: Já existe um pagamento de <strong>' . htmlspecialchars($nome_emolumento) . '</strong> para o mês de <strong>' . htmlspecialchars($mes_referencia) . '</strong> para este aluno!';
                    $pagamento_duplicado = true;
                }
            }

            // Se não houver erro de duplicidade, prosseguir
            if (empty($erro)) {
                // Buscar dados do aluno
                $stmt_aluno = $pdo->prepare("SELECT nome, genero, classe, turma FROM alunos WHERE id = ?");
                $stmt_aluno->execute([$aluno_id]);
                $aluno = $stmt_aluno->fetch();

                // Inserir pagamento
                $stmt = $pdo->prepare("
                    INSERT INTO pagamentos (aluno_id, emolumento_id, valor, data_pagamento, forma_pagamento, referencia, observacoes, status, mes_referencia) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmado', ?)
                ");
                $stmt->execute([$aluno_id, $emolumento_id, $valor_liquido, $data_pagamento, $forma_pagamento, $referencia, $observacoes, $mes_referencia]);
                $pagamento_id = $pdo->lastInsertId();

                // Gerar número da fatura
                $numero_fatura = 'FAT-' . date('Ymd') . '-' . str_pad($pagamento_id, 6, '0', STR_PAD_LEFT);

                // Buscar configurações da escola
                $nome_escola = 'Sistema Escolar';
                $endereco = '';
                $contacto = '';
                $email = '';
                $nif = '';

                try {
                    $config_file = '../../../../config_escola.json';
                    if (file_exists($config_file)) {
                        $config = json_decode(file_get_contents($config_file), true);
                        $nome_escola = $config['nome'] ?? 'Sistema Escolar';
                        $endereco = $config['endereco'] ?? '';
                        $contacto = $config['contacto'] ?? '';
                        $email = $config['email'] ?? '';
                        $nif = $config['nif'] ?? '';
                    }
                } catch (Exception $e) {}

                // Preparar dados para a fatura
                $dados_fatura = [
                    'nome_escola' => $nome_escola,
                    'endereco' => $endereco,
                    'contacto' => $contacto,
                    'email' => $email,
                    'nif' => $nif,
                    'aluno_id' => $aluno_id,
                    'aluno_nome' => $aluno['nome'] ?? '',
                    'aluno_classe' => $aluno['classe'] ?? '',
                    'aluno_turma' => $aluno['turma'] ?? '',
                    'aluno_genero' => $aluno['genero'] ?? '',
                    'emolumento' => $nome_emolumento,
                    'valor_base' => $valor_base,
                    'desconto' => $desconto_aplicado,
                    'valor_liquido' => $valor_liquido,
                    'data_pagamento' => $data_pagamento,
                    'forma_pagamento' => $forma_pagamento,
                    'referencia' => $referencia,
                    'observacoes' => $observacoes,
                    'numero_fatura' => $numero_fatura,
                    'mes_referencia' => $mes_referencia
                ];

                // Gerar HTML da fatura estilo AGT
                $fatura_html = gerarFaturaAGT($dados_fatura);

                // Salvar fatura em arquivo
                salvarFatura($fatura_html, $numero_fatura);

                // Mostrar fatura
                $mostrar_fatura = true;
                $sucesso = '✅ Pagamento registrado com sucesso! Fatura gerada.';

                // Limpar formulário
                $_POST = [];
                $_GET['busca'] = '';
                $aluno_busca = '';
                $alunos_busca = [];
                $aluno_selecionado = null;

                // Recarregar alunos
                try {
                    $alunos = $pdo->query("SELECT id, nome, genero, turma, classe, curso FROM alunos WHERE status = 'ativo' ORDER BY nome LIMIT 100")->fetchAll();
                } catch (Exception $e) {}
            }

        } catch (Exception $e) {
            $erro = 'Erro ao registrar: ' . $e->getMessage();
        }
    }
}

// ===== INCLUIR HEADER =====
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
    }
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    .btn-danger:hover {
        background: #c0392b;
    }
    .menu-financeiro {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 25px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    .menu-financeiro a {
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
        color: #4a5568;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .menu-financeiro a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    .menu-financeiro a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 800px;
    }
    .form-group {
        margin-bottom: 18px;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #1a2332;
        font-size: 13px;
    }
    .form-group label .required {
        color: #e74c3c;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
    }
    .form-group textarea {
        min-height: 60px;
        resize: vertical;
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
    }
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .alert-warning {
        background: #fef9e8;
        color: #78350f;
        border: 1px solid #fde68a;
    }
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    .info-valor {
        background: #f8fafc;
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        margin-top: 5px;
        font-size: 13px;
        color: #4a5568;
    }
    .info-valor strong {
        color: #1a2332;
    }
    .desconto-info {
        background: #fef9e8;
        border: 1px solid #fde68a;
        padding: 10px 14px;
        border-radius: 8px;
        margin-top: 10px;
        font-size: 13px;
    }
    .desconto-info .valor-final {
        font-size: 18px;
        font-weight: bold;
        color: #c0392b;
    }
    .busca-aluno-container {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }
    .busca-aluno-container .busca-row {
        display: flex;
        gap: 10px;
    }
    .busca-aluno-container .busca-row input {
        flex: 1;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
    }
    .busca-aluno-container .busca-row input:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
    }
    .busca-aluno-container .busca-row .btn {
        padding: 10px 20px;
        white-space: nowrap;
    }
    .resultado-busca {
        margin-top: 12px;
        border-top: 1px solid #e2e8f0;
        padding-top: 12px;
        max-height: 400px;
        overflow-y: auto;
    }
    .resultado-busca .aluno-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: white;
        border-radius: 8px;
        margin-bottom: 8px;
        border: 2px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.2s;
    }
    .resultado-busca .aluno-item:hover {
        border-color: #c9a84c;
        background: #fef9e8;
        transform: translateX(5px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .resultado-busca .aluno-item .info {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
    }
    .resultado-busca .aluno-item .info .nome {
        font-weight: 600;
        color: #1a2332;
        font-size: 15px;
    }
    .resultado-busca .aluno-item .info .detalhes {
        font-size: 12px;
        color: #94a3b8;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .resultado-busca .aluno-item .info .detalhes span {
        background: #f1f5f9;
        padding: 2px 8px;
        border-radius: 4px;
    }
    .resultado-busca .aluno-item .selecionar-btn {
        padding: 6px 16px;
        border-radius: 6px;
        background: #c9a84c;
        color: #1a2332;
        border: none;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        margin-left: 10px;
    }
    .resultado-busca .aluno-item .selecionar-btn:hover {
        background: #b8973a;
        transform: scale(1.05);
    }
    .resultado-busca .aluno-item.aluno-selecionado {
        background: #d1fae5 !important;
        border-color: #2ecc71 !important;
    }
    .resultado-busca .aluno-item.aluno-selecionado .selecionar-btn {
        background: #2ecc71 !important;
        color: #fff !important;
    }
    .resultado-busca .aluno-item.aluno-selecionado .selecionar-btn:hover {
        background: #27ae60 !important;
    }
    .nenhum-resultado {
        text-align: center;
        padding: 30px;
        color: #94a3b8;
        font-size: 14px;
    }
    .nenhum-resultado .icone {
        font-size: 48px;
        margin-bottom: 10px;
    }
    .aluno-selecionado-feedback {
        margin-top: 8px;
        padding: 12px 16px;
        background: #d1fae5;
        border-radius: 6px;
        font-size: 13px;
        color: #065f46;
        border-left: 4px solid #2ecc71;
        display: flex;
        justify-content: space-between;
        align-items: center;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .aluno-selecionado-feedback .remover-btn {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 4px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
    }
    .aluno-selecionado-feedback .remover-btn:hover {
        background: #c0392b;
        transform: scale(1.05);
    }
    .badge-status {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    .badge-ativo {
        background: #d1fae5;
        color: #065f46;
    }
    .badge-inativo {
        background: #fee2e2;
        color: #991b1b;
    }
    .dica-busca {
        background: #fef9e8;
        border: 1px solid #fde68a;
        border-radius: 6px;
        padding: 10px 14px;
        margin-top: 10px;
        font-size: 13px;
        color: #78350f;
    }
    .dica-busca ul {
        margin: 5px 0 0 20px;
        padding: 0;
    }
    .dica-busca ul li {
        margin: 3px 0;
    }
    .fatura-modal {
        display: <?= $mostrar_fatura ? 'block' : 'none' ?>;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
        z-index: 9999;
        overflow-y: auto;
        padding: 20px;
    }
    .fatura-modal-content {
        max-width: 210mm;
        margin: 20px auto;
        background: white;
        border-radius: 8px;
        padding: 0;
        position: relative;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    }
    .fatura-modal .btn-fechar {
        position: sticky;
        top: 0;
        float: right;
        background: #e74c3c;
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        margin: 10px;
        z-index: 10;
    }
    .fatura-modal .btn-fechar:hover {
        background: #c0392b;
    }
    .fatura-iframe {
        width: 100%;
        height: 900px;
        border: none;
        border-radius: 0 0 8px 8px;
    }
    .mes-hint {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 4px;
    }
    .mes-hint.ativo {
        color: #27ae60;
        font-weight: 500;
    }
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .menu-financeiro {
            flex-direction: column;
            align-items: stretch;
        }
        .menu-financeiro a {
            text-align: center;
            justify-content: center;
        }
        .form-row, .form-row-3 {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .form-container {
            padding: 20px;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
        }
        .busca-aluno-container .busca-row {
            flex-direction: column;
        }
        .resultado-busca .aluno-item {
            flex-wrap: wrap;
        }
        .resultado-busca .aluno-item .selecionar-btn {
            margin-left: 0;
            margin-top: 8px;
            width: 100%;
        }
        .aluno-selecionado-feedback {
            flex-direction: column;
            gap: 8px;
            text-align: center;
        }
        .fatura-modal-content {
            margin: 10px;
        }
        .fatura-iframe {
            height: 500px;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>💳 Novo Pagamento</h1>
        <p class="subtitle">Registrar novo pagamento escolar com desconto</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="index.php" class="active">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<?php if ($sucesso && !$mostrar_fatura): ?>
<div class="alert alert-success">✅ <?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert-<?= $pagamento_duplicado ? 'warning' : 'error' ?>">
    <?= $erro ?>
</div>
<?php endif; ?>

<div class="form-container">
    <!-- Formulário de Busca de Alunos -->
    <form method="GET" action="" id="buscaForm">
        <div class="busca-aluno-container">
            <div class="busca-row">
                <input type="text" 
                       id="busca_aluno" 
                       name="busca" 
                       placeholder="🔍 Buscar por ID ou Nome do aluno..." 
                       value="<?= htmlspecialchars($aluno_busca) ?>"
                       autocomplete="off">
                <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                <?php if (!empty($aluno_busca)): ?>
                <a href="add.php" class="btn btn-secondary">✕ Limpar</a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($aluno_busca) && !empty($alunos_busca)): ?>
            <div class="resultado-busca">
                <div style="font-size: 12px; color: #94a3b8; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span>📋 Encontrados <?= count($alunos_busca) ?> aluno(s)</span>
                    <span style="font-size: 11px;">Clique em "Selecionar" para escolher o aluno</span>
                </div>
                <?php foreach($alunos_busca as $aluno): ?>
                <div class="aluno-item <?= ($aluno_selecionado && $aluno_selecionado['id'] == $aluno['id']) ? 'aluno-selecionado' : '' ?>" 
                     data-id="<?= $aluno['id'] ?>"
                     data-nome="<?= htmlspecialchars($aluno['nome']) ?>"
                     data-genero="<?= htmlspecialchars($aluno['genero'] ?? '') ?>"
                     data-classe="<?= htmlspecialchars($aluno['classe'] ?? '') ?>"
                     data-curso="<?= htmlspecialchars($aluno['curso'] ?? '') ?>"
                     data-turma="<?= htmlspecialchars($aluno['turma'] ?? '') ?>">
                    <div class="info">
                        <span class="nome">
                            #<?= $aluno['id'] ?> - <?= htmlspecialchars($aluno['nome']) ?>
                            <?php if (!empty($aluno['status'])): ?>
                                <span class="badge-status badge-<?= $aluno['status'] == 'ativo' ? 'ativo' : 'inativo' ?>">
                                    <?= ucfirst($aluno['status']) ?>
                                </span>
                            <?php endif; ?>
                        </span>
                        <div class="detalhes">
                            <?php if (!empty($aluno['genero'])): ?>
                                <span>👤 <?= $aluno['genero'] == 'M' ? 'Masculino' : 'Feminino' ?></span>
                            <?php endif; ?>
                            <?php if (!empty($aluno['classe']) || !empty($aluno['curso'])): ?>
                                <span>🏫 <?= htmlspecialchars($aluno['classe'] ?? '') ?> <?= htmlspecialchars($aluno['curso'] ?? '') ?></span>
                            <?php endif; ?>
                            <?php if (!empty($aluno['turma'])): ?>
                                <span>📖 Turma: <?= htmlspecialchars($aluno['turma']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="selecionar-btn" onclick="selecionarAluno(this)">
                        ✅ Selecionar
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php elseif (!empty($aluno_busca)): ?>
            <div class="resultado-busca">
                <div class="nenhum-resultado">
                    <div class="icone">😕</div>
                    <div>Nenhum aluno encontrado com "<strong><?= htmlspecialchars($aluno_busca) ?></strong>"</div>
                    <div style="margin-top: 10px; font-size: 12px; color: #94a3b8;">
                        💡 Dica: Busque por <strong>ID</strong> (ex: 1, 23, 44) ou <strong>Nome</strong> (ex: Ana, João, Abel)
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
    
    <!-- Formulário Principal -->
    <form method="POST" action="" id="pagamentoForm" onsubmit="return validarFormulario()">
        <!-- Aluno Selecionado -->
        <div class="form-group">
            <label>Aluno <span class="required">*</span></label>
            <select name="aluno_id" id="aluno_id" required>
                <option value="">Selecione um aluno</option>
                <?php foreach($alunos as $aluno): ?>
                <option value="<?= $aluno['id'] ?>" 
                        <?= ($_POST['aluno_id'] ?? $aluno_selecionado['id'] ?? '') == $aluno['id'] ? 'selected' : '' ?>>
                    #<?= $aluno['id'] ?> - <?= htmlspecialchars($aluno['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($aluno_selecionado): ?>
            <div class="aluno-selecionado-feedback" id="alunoFeedback">
                <span>
                    ✅ <strong>#<?= $aluno_selecionado['id'] ?></strong> - 
                    <?= htmlspecialchars($aluno_selecionado['nome']) ?>
                    <?php if (!empty($aluno_selecionado['genero'])): ?>
                        - <?= $aluno_selecionado['genero'] == 'M' ? '👨 Masculino' : '👩 Feminino' ?>
                    <?php endif; ?>
                </span>
                <button type="button" class="remover-btn" onclick="removerSelecao()">✕ Remover</button>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Emolumento <span class="required">*</span></label>
                <select name="emolumento_id" id="emolumento_id" required onchange="atualizarValor()">
                    <option value="">Selecione um emolumento</option>
                    <?php foreach($emolumentos as $emolumento): ?>
                    <option value="<?= $emolumento['id'] ?>" data-valor="<?= $emolumento['valor'] ?>" data-nome="<?= htmlspecialchars($emolumento['nome']) ?>" <?= ($_POST['emolumento_id'] ?? '') == $emolumento['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emolumento['nome']) ?> - R$ <?= number_format($emolumento['valor'], 2, ',', '.') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Valor Base <span class="required">*</span></label>
                <input type="number" step="0.01" name="valor_base" id="valor_base" value="<?= $_POST['valor_base'] ?? '' ?>" required onchange="calcularDesconto()" oninput="calcularDesconto()">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Mês de Referência</label>
                <select name="mes_referencia" id="mes_referencia" disabled>
                    <option value="-">-</option>
                    <option value="Janeiro" <?= ($_POST['mes_referencia'] ?? '') == 'Janeiro' ? 'selected' : '' ?>>Janeiro</option>
                    <option value="Fevereiro" <?= ($_POST['mes_referencia'] ?? '') == 'Fevereiro' ? 'selected' : '' ?>>Fevereiro</option>
                    <option value="Março" <?= ($_POST['mes_referencia'] ?? '') == 'Março' ? 'selected' : '' ?>>Março</option>
                    <option value="Abril" <?= ($_POST['mes_referencia'] ?? '') == 'Abril' ? 'selected' : '' ?>>Abril</option>
                    <option value="Maio" <?= ($_POST['mes_referencia'] ?? '') == 'Maio' ? 'selected' : '' ?>>Maio</option>
                    <option value="Junho" <?= ($_POST['mes_referencia'] ?? '') == 'Junho' ? 'selected' : '' ?>>Junho</option>
                    <option value="Julho" <?= ($_POST['mes_referencia'] ?? '') == 'Julho' ? 'selected' : '' ?>>Julho</option>
                    <option value="Agosto" <?= ($_POST['mes_referencia'] ?? '') == 'Agosto' ? 'selected' : '' ?>>Agosto</option>
                    <option value="Setembro" <?= ($_POST['mes_referencia'] ?? '') == 'Setembro' ? 'selected' : '' ?>>Setembro</option>
                    <option value="Outubro" <?= ($_POST['mes_referencia'] ?? '') == 'Outubro' ? 'selected' : '' ?>>Outubro</option>
                    <option value="Novembro" <?= ($_POST['mes_referencia'] ?? '') == 'Novembro' ? 'selected' : '' ?>>Novembro</option>
                    <option value="Dezembro" <?= ($_POST['mes_referencia'] ?? '') == 'Dezembro' ? 'selected' : '' ?>>Dezembro</option>
                </select>
                <div class="mes-hint" id="mesHint">💡 Selecione o mês apenas para Propina, Transporte ou Mensalidade</div>
            </div>
            
            <div class="form-group">
                <label>Desconto (Kz)</label>
                <input type="number" step="0.01" name="desconto" id="desconto" value="<?= $_POST['desconto'] ?? 0 ?>" min="0" onchange="calcularDesconto()" oninput="calcularDesconto()">
            </div>
        </div>
        
        <div class="form-row-3">
            <div class="form-group">
                <label>Desconto (%)</label>
                <input type="number" step="0.1" id="desconto_percent" value="0" min="0" max="100" onchange="aplicarDescontoPercentual()" oninput="aplicarDescontoPercentual()">
            </div>
            
            <div class="form-group">
                <label>Valor Líquido</label>
                <input type="text" id="valor_liquido" readonly style="background: #f8fafc; font-weight: bold; color: #1a2332; font-size: 16px;">
            </div>
            
            <div class="form-group">
                <label>Forma de Pagamento <span class="required">*</span></label>
                <select name="forma_pagamento" required>
                    <option value="dinheiro" <?= ($_POST['forma_pagamento'] ?? '') == 'dinheiro' ? 'selected' : '' ?>>💰 Dinheiro</option>
                    <option value="cartao" <?= ($_POST['forma_pagamento'] ?? '') == 'cartao' ? 'selected' : '' ?>>💳 Cartão</option>
                    <option value="transferencia" <?= ($_POST['forma_pagamento'] ?? '') == 'transferencia' ? 'selected' : '' ?>>🏦 Transferência</option>
                    <option value="pix" <?= ($_POST['forma_pagamento'] ?? '') == 'pix' ? 'selected' : '' ?>>📱 Pix</option>
                </select>
            </div>
        </div>
        
        <div class="desconto-info" id="descontoInfo" style="display: none;">
            <strong>📊 Resumo do Desconto:</strong><br>
            Valor Base: <span id="resumo_base">R$ 0,00</span> | 
            Desconto: <span id="resumo_desconto" style="color: #27ae60;">R$ 0,00</span> | 
            <strong>Valor a Pagar: <span id="resumo_final" class="valor-final">R$ 0,00</span></strong>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Data do Pagamento <span class="required">*</span></label>
                <input type="date" name="data_pagamento" value="<?= $_POST['data_pagamento'] ?? date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Referência</label>
                <input type="text" name="referencia" placeholder="Nº do comprovante, recibo..." value="<?= htmlspecialchars($_POST['referencia'] ?? '') ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label>Observações</label>
            <textarea name="observacoes" placeholder="Observações sobre o pagamento"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
        </div>
        
        <div class="info-valor">
            💡 O pagamento será registrado como <strong>confirmado</strong> e uma fatura estilo <strong>AGT</strong> será gerada automaticamente.
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">✅ Registrar Pagamento</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<!-- Modal da Fatura -->
<?php if ($mostrar_fatura && $fatura_html): ?>
<div class="fatura-modal" id="faturaModal">
    <div class="fatura-modal-content">
        <button class="btn-fechar" onclick="fecharFatura()">✕ Fechar</button>
        <iframe class="fatura-iframe" srcdoc="<?= htmlspecialchars($fatura_html) ?>"></iframe>
    </div>
</div>
<?php endif; ?>

<script>
    /**
     * Atualiza o valor base quando um emolumento é selecionado
     */
    function atualizarValor() {
        const select = document.getElementById('emolumento_id');
        const valorInput = document.getElementById('valor_base');
        const selectedOption = select.options[select.selectedIndex];
        const mesSelect = document.getElementById('mes_referencia');
        const mesHint = document.getElementById('mesHint');
        
        if (selectedOption.value) {
            const valor = parseFloat(selectedOption.dataset.valor) || 0;
            valorInput.value = valor.toFixed(2);
            calcularDesconto();
            
            // Verificar se é propina, transporte ou mensalidade
            const nome = selectedOption.dataset.nome || '';
            const isMesRequired = nome.toLowerCase().includes('propina') || 
                                  nome.toLowerCase().includes('transporte') || 
                                  nome.toLowerCase().includes('mensalidade');
            
            // Habilitar/desabilitar campo mês
            mesSelect.disabled = !isMesRequired;
            if (!isMesRequired) {
                mesSelect.value = '-';
                mesHint.innerHTML = '💡 Selecione o mês apenas para Propina, Transporte ou Mensalidade';
                mesHint.className = 'mes-hint';
            } else {
                mesHint.innerHTML = '✅ Mês obrigatório para <strong>' + nome + '</strong>';
                mesHint.className = 'mes-hint ativo';
                if (mesSelect.value === '-') {
                    // Definir mês atual como padrão
                    const meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                                  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                    const mesAtual = new Date().getMonth();
                    mesSelect.value = meses[mesAtual];
                }
            }
        } else {
            valorInput.value = '';
            mesSelect.disabled = true;
            mesSelect.value = '-';
            mesHint.innerHTML = '💡 Selecione o mês apenas para Propina, Transporte ou Mensalidade';
            mesHint.className = 'mes-hint';
        }
    }
    
    /**
     * Calcula o desconto e atualiza os campos
     */
    function calcularDesconto() {
        const valorBase = parseFloat(document.getElementById('valor_base').value) || 0;
        const descontoValor = parseFloat(document.getElementById('desconto').value) || 0;
        
        let valorLiquido = valorBase - descontoValor;
        if (valorLiquido < 0) valorLiquido = 0;
        
        // Atualizar campo de valor líquido
        document.getElementById('valor_liquido').value = valorLiquido.toFixed(2);
        
        // Atualizar resumo
        document.getElementById('resumo_base').textContent = 'R$ ' + valorBase.toFixed(2).replace('.', ',');
        document.getElementById('resumo_desconto').textContent = 'R$ ' + descontoValor.toFixed(2).replace('.', ',');
        document.getElementById('resumo_final').textContent = 'R$ ' + valorLiquido.toFixed(2).replace('.', ',');
        
        // Mostrar/ocultar resumo
        const descontoInfo = document.getElementById('descontoInfo');
        if (descontoValor > 0) {
            descontoInfo.style.display = 'block';
        } else {
            descontoInfo.style.display = 'none';
        }
    }
    
    /**
     * Aplica desconto percentual
     */
    function aplicarDescontoPercentual() {
        const valorBase = parseFloat(document.getElementById('valor_base').value) || 0;
        const percentual = parseFloat(document.getElementById('desconto_percent').value) || 0;
        
        if (percentual < 0 || percentual > 100) {
            alert('O percentual de desconto deve estar entre 0 e 100');
            document.getElementById('desconto_percent').value = 0;
            return;
        }
        
        const descontoValor = (valorBase * percentual) / 100;
        document.getElementById('desconto').value = descontoValor.toFixed(2);
        calcularDesconto();
    }
    
    /**
     * Seleciona um aluno da lista de resultados
     */
    function selecionarAluno(btn) {
        const item = btn.closest('.aluno-item');
        if (!item) return;
        
        const alunoId = item.dataset.id;
        const alunoNome = item.dataset.nome;
        const alunoGenero = item.dataset.genero;
        
        // Selecionar no dropdown
        const select = document.getElementById('aluno_id');
        for (let option of select.options) {
            if (option.value == alunoId) {
                option.selected = true;
                break;
            }
        }
        
        // Remover feedback antigo
        const oldFeedback = document.getElementById('alunoFeedback');
        if (oldFeedback) {
            oldFeedback.remove();
        }
        
        // Criar novo feedback
        const parentGroup = document.querySelector('.form-group');
        const feedbackDiv = document.createElement('div');
        feedbackDiv.id = 'alunoFeedback';
        feedbackDiv.className = 'aluno-selecionado-feedback';
        
        let infoText = `✅ <strong>#${alunoId}</strong> - ${alunoNome}`;
        if (alunoGenero) {
            infoText += ` - ${alunoGenero === 'M' ? '👨 Masculino' : '👩 Feminino'}`;
        }
        
        feedbackDiv.innerHTML = `
            <span>${infoText}</span>
            <button type="button" class="remover-btn" onclick="removerSelecao()">✕ Remover</button>
        `;
        parentGroup.appendChild(feedbackDiv);
        
        // Destacar na lista de resultados
        const items = document.querySelectorAll('.aluno-item');
        items.forEach(i => {
            i.classList.remove('aluno-selecionado');
            if (i.dataset.id == alunoId) {
                i.classList.add('aluno-selecionado');
            }
        });
        
        feedbackDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    /**
     * Remove a seleção atual do aluno
     */
    function removerSelecao() {
        const select = document.getElementById('aluno_id');
        select.value = '';
        
        const feedback = document.getElementById('alunoFeedback');
        if (feedback) {
            feedback.remove();
        }
        
        const items = document.querySelectorAll('.aluno-item');
        items.forEach(item => {
            item.classList.remove('aluno-selecionado');
        });
    }
    
    /**
     * Valida o formulário antes de enviar
     */
    function validarFormulario() {
        const emolumentoSelect = document.getElementById('emolumento_id');
        const selectedOption = emolumentoSelect.options[emolumentoSelect.selectedIndex];
        const nomeEmolumento = selectedOption.dataset.nome || '';
        const mesReferencia = document.getElementById('mes_referencia').value;
        const mesSelect = document.getElementById('mes_referencia');
        
        // Verificar se é propina, transporte ou mensalidade
        const isMesRequired = nomeEmolumento.toLowerCase().includes('propina') || 
                              nomeEmolumento.toLowerCase().includes('transporte') || 
                              nomeEmolumento.toLowerCase().includes('mensalidade');
        
        if (isMesRequired && !mesSelect.disabled && mesReferencia === '-') {
            alert('⚠️ Por favor, selecione o mês de referência para ' + nomeEmolumento + '.');
            document.getElementById('mes_referencia').focus();
            return false;
        }
        
        return true;
    }
    
    /**
     * Fecha o modal da fatura
     */
    function fecharFatura() {
        const modal = document.getElementById('faturaModal');
        if (modal) {
            modal.style.display = 'none';
        }
        window.location.href = 'add.php';
    }
    
    /**
     * Busca automática com debounce
     */
    let timeoutId = null;
    document.getElementById('busca_aluno')?.addEventListener('input', function() {
        clearTimeout(timeoutId);
        const value = this.value.trim();
        
        if (value.length >= 2) {
            timeoutId = setTimeout(() => {
                document.getElementById('buscaForm').submit();
            }, 500);
        }
    });
    
    /**
     * Busca ao pressionar Enter
     */
    document.getElementById('busca_aluno')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('buscaForm').submit();
        }
    });
    
    /**
     * Inicializar
     */
    document.addEventListener('DOMContentLoaded', function() {
        atualizarValor();
        
        <?php if ($aluno_selecionado): ?>
        setTimeout(function() {
            const items = document.querySelectorAll('.aluno-item');
            items.forEach(item => {
                if (item.dataset.id == <?= $aluno_selecionado['id'] ?>) {
                    item.classList.add('aluno-selecionado');
                }
            });
        }, 100);
        <?php endif; ?>
    });
</script>

<?php include '../../includes/footer_escola.php'; ?>