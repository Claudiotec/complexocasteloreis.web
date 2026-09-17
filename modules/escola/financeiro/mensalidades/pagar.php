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
// modules/escola/financeiro/mensalidades/pagar.php
// Página para pagamento de mensalidades
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
                        <td class="text-left">' . htmlspecialchars($descricao) . '</td>
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

// ===== VERIFICAR E CRIAR TABELAS =====
function verificarTabelas($pdo) {
    try {
        // Verificar tabela pagamentos
        $stmt = $pdo->query("SHOW TABLES LIKE 'pagamentos'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE pagamentos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    aluno_id INT NOT NULL,
                    emolumento_id INT NOT NULL,
                    valor DECIMAL(10,2) NOT NULL,
                    data_pagamento DATE NOT NULL,
                    forma_pagamento VARCHAR(50) DEFAULT 'dinheiro',
                    referencia VARCHAR(100) DEFAULT NULL,
                    observacoes TEXT DEFAULT NULL,
                    mes_referencia VARCHAR(20) DEFAULT '-',
                    status VARCHAR(20) DEFAULT 'Pago',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
        
        // Verificar coluna status em pagamentos
        $stmt = $pdo->query("SHOW COLUMNS FROM pagamentos LIKE 'status'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE pagamentos ADD COLUMN status VARCHAR(20) DEFAULT 'Pago'");
        }
        
        // Verificar coluna mes_referencia em pagamentos
        $stmt = $pdo->query("SHOW COLUMNS FROM pagamentos LIKE 'mes_referencia'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE pagamentos ADD COLUMN mes_referencia VARCHAR(20) DEFAULT '-'");
        }
        
        // Verificar tabela mensalidades
        $stmt = $pdo->query("SHOW TABLES LIKE 'mensalidades'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE mensalidades (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    aluno_id INT NOT NULL,
                    mes VARCHAR(20) NOT NULL,
                    ano INT NOT NULL,
                    valor DECIMAL(10,2) NOT NULL,
                    data_vencimento DATE NOT NULL,
                    status VARCHAR(20) DEFAULT 'Pendente',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_aluno_mes_ano (aluno_id, mes, ano)
                )
            ");
        }
        
        // Verificar coluna status em mensalidades
        $stmt = $pdo->query("SHOW COLUMNS FROM mensalidades LIKE 'status'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE mensalidades ADD COLUMN status VARCHAR(20) DEFAULT 'Pendente'");
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ===== ATUALIZAR STATUS DA MENSALIDADE =====
function atualizarStatusMensalidade($pdo, $aluno_id, $mes_referencia) {
    try {
        // Extrair mês e ano do formato "Mês/Ano" ou "Mês Ano"
        $mes_ano = explode('/', $mes_referencia);
        if (count($mes_ano) == 2) {
            $mes = trim($mes_ano[0]);
            $ano = intval(trim($mes_ano[1]));
        } else {
            // Tenta separar por espaço
            $mes_ano = explode(' ', $mes_referencia);
            if (count($mes_ano) >= 2) {
                $mes = trim($mes_ano[0]);
                $ano = intval(trim($mes_ano[1]));
            } else {
                return false;
            }
        }
        
        // Mapear meses em português para números
        $mapa_meses = array(
            'Janeiro' => 1, 'Fevereiro' => 2, 'Março' => 3, 'Abril' => 4,
            'Maio' => 5, 'Junho' => 6, 'Julho' => 7, 'Agosto' => 8,
            'Setembro' => 9, 'Outubro' => 10, 'Novembro' => 11, 'Dezembro' => 12
        );
        
        $mes_numero = $mapa_meses[$mes] ?? 0;
        if ($mes_numero == 0) {
            return false;
        }
        
        // Atualizar status da mensalidade
        $stmt = $pdo->prepare("
            UPDATE mensalidades 
            SET status = 'Pago', updated_at = NOW() 
            WHERE aluno_id = ? AND mes = ? AND ano = ?
        ");
        $stmt->execute([$aluno_id, $mes, $ano]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ===== EXECUTAR VERIFICAÇÃO =====
verificarTabelas($pdo);

// ===== PROCESSAR ID DO ALUNO =====
$aluno_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$aluno = null;
$erro = '';
$sucesso = '';
$fatura_html = '';
$mostrar_fatura = false;
$alunos_lista = [];
$ultimo_pagamento = null;
$mensalidade_id = isset($_GET['mensalidade_id']) ? intval($_GET['mensalidade_id']) : 0;
$mensalidade = null;

// ===== BUSCAR DADOS DO ALUNO =====
if ($aluno_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$aluno_id]);
        $aluno = $stmt->fetch();
        
        if (!$aluno) {
            $erro = 'Aluno não encontrado ou inativo.';
            $stmt = $pdo->query("SELECT id, nome, status FROM alunos WHERE status = 'ativo' ORDER BY nome LIMIT 10");
            $alunos_lista = $stmt->fetchAll();
        } else {
            // Buscar mensalidade específica se fornecida
            if ($mensalidade_id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM mensalidades WHERE id = ? AND aluno_id = ?");
                $stmt->execute([$mensalidade_id, $aluno_id]);
                $mensalidade = $stmt->fetch();
            }
            
            // Buscar último pagamento do aluno
            $stmt = $pdo->prepare("SELECT * FROM pagamentos WHERE aluno_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$aluno_id]);
            $ultimo_pagamento = $stmt->fetch();
        }
    } catch (Exception $e) {
        $erro = 'Erro ao buscar aluno: ' . $e->getMessage();
    }
} else {
    $erro = 'ID do aluno não informado.';
}

// ===== BUSCAR EMOLUMENTOS =====
$emolumentos = [];
try {
    $emolumentos = $pdo->query("SELECT id, nome, valor FROM emolumentos WHERE status = 'ativo' ORDER BY nome")->fetchAll();
} catch (Exception $e) {
    // Se a tabela não existir, criar alguns emolumentos padrão
}

// ===== PROCESSAR FORMULÁRIO =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $aluno) {
    $emolumento_id = $_POST['emolumento_id'] ?? null;
    $valor_base = floatval($_POST['valor_base'] ?? 0);
    $desconto = floatval($_POST['desconto'] ?? 0);
    $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    $forma_pagamento = $_POST['forma_pagamento'] ?? 'dinheiro';
    $referencia = $_POST['referencia'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    $mes_referencia = $_POST['mes_referencia'] ?? '-';
    $mensalidade_id_post = $_POST['mensalidade_id'] ?? 0;
    $status = 'Pago';

    $valor_liquido = $valor_base - $desconto;
    if ($valor_liquido < 0) $valor_liquido = 0;

    if (!$emolumento_id || $valor_base <= 0) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        try {
            // Buscar dados do emolumento
            $stmt_emol = $pdo->prepare("SELECT nome FROM emolumentos WHERE id = ?");
            $stmt_emol->execute([$emolumento_id]);
            $emolumento = $stmt_emol->fetch();
            $nome_emolumento = $emolumento['nome'] ?? 'Mensalidade';

            // Iniciar transação
            $pdo->beginTransaction();

            // Inserir pagamento
            $stmt = $pdo->prepare("
                INSERT INTO pagamentos 
                (aluno_id, emolumento_id, valor, data_pagamento, forma_pagamento, referencia, observacoes, mes_referencia, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $aluno_id, 
                $emolumento_id, 
                $valor_liquido, 
                $data_pagamento, 
                $forma_pagamento, 
                $referencia, 
                $observacoes, 
                $mes_referencia,
                $status
            ]);
            
            if ($result) {
                $pagamento_id = $pdo->lastInsertId();
                
                // ATUALIZAR STATUS DA MENSALIDADE
                $atualizado = false;
                
                // Se veio de uma mensalidade específica
                if ($mensalidade_id_post > 0) {
                    $stmt = $pdo->prepare("UPDATE mensalidades SET status = 'Pago', updated_at = NOW() WHERE id = ? AND aluno_id = ?");
                    $stmt->execute([$mensalidade_id_post, $aluno_id]);
                    $atualizado = $stmt->rowCount() > 0;
                } 
                // Caso contrário, tentar encontrar pelo mês
                elseif ($mes_referencia != '-') {
                    $atualizado = atualizarStatusMensalidade($pdo, $aluno_id, $mes_referencia);
                }
                
                // Commit da transação
                $pdo->commit();
                
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
                    'descricao' => $nome_emolumento,
                    'valor_base' => $valor_base,
                    'desconto' => $desconto,
                    'valor_liquido' => $valor_liquido,
                    'data_pagamento' => $data_pagamento,
                    'forma_pagamento' => $forma_pagamento,
                    'referencia' => $referencia,
                    'observacoes' => $observacoes,
                    'numero_fatura' => $numero_fatura,
                    'mes_referencia' => $mes_referencia
                ];

                // Gerar HTML da fatura
                $fatura_html = gerarFatura($dados_fatura);

                // Salvar fatura
                salvarFatura($fatura_html, $numero_fatura);

                $mostrar_fatura = true;
                $sucesso = '✅ Pagamento registrado com sucesso! Fatura gerada.';
                if ($atualizado) {
                    $sucesso .= ' Mensalidade marcada como PAGA.';
                } else {
                    $sucesso .= ' (Mensalidade não encontrada para marcar como Paga)';
                }
                
                // Buscar o pagamento recém-criado
                $stmt = $pdo->prepare("SELECT * FROM pagamentos WHERE id = ?");
                $stmt->execute([$pagamento_id]);
                $ultimo_pagamento = $stmt->fetch();
                
                // Limpar dados do formulário
                $_POST = [];
            } else {
                $pdo->rollBack();
                $erro = 'Erro ao registrar pagamento no banco de dados.';
            }

        } catch (Exception $e) {
            $pdo->rollBack();
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
    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
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
    .dados-aluno-card {
        background: #f8fafc;
        padding: 15px 20px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
    }
    .dados-aluno-card .info-row {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        padding: 4px 0;
        font-size: 14px;
    }
    .dados-aluno-card .label {
        font-weight: 600;
        color: #1a2332;
    }
    .dados-aluno-card .valor {
        color: #4a5568;
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
    .sugestoes-alunos {
        background: #fef9e8;
        border: 1px solid #fde68a;
        border-radius: 8px;
        padding: 15px;
        margin-top: 15px;
    }
    .sugestoes-alunos ul {
        list-style: none;
        padding: 0;
        margin: 10px 0 0 0;
    }
    .sugestoes-alunos ul li {
        padding: 5px 10px;
        margin: 3px 0;
        background: white;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        display: inline-block;
        margin-right: 8px;
    }
    .sugestoes-alunos ul li a {
        text-decoration: none;
        color: #1a2332;
        font-weight: 500;
    }
    .sugestoes-alunos ul li a:hover {
        color: #c9a84c;
    }
    .pagamento-info {
        background: #d1fae5;
        border: 1px solid #a7f3d0;
        border-radius: 8px;
        padding: 12px 16px;
        margin-top: 10px;
        font-size: 13px;
        color: #065f46;
    }
    .pagamento-info strong {
        color: #1a2332;
    }
    .mensalidade-info {
        background: #dbeafe;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 12px 16px;
        margin-top: 10px;
        font-size: 13px;
        color: #1e40af;
    }
    .mensalidade-info strong {
        color: #1a2332;
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
        .fatura-modal-content {
            margin: 10px;
        }
        .fatura-iframe {
            height: 500px;
        }
        .dados-aluno-card .info-row {
            flex-direction: column;
            gap: 2px;
        }
        .sugestoes-alunos ul li {
            display: block;
            margin: 5px 0;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>💰 Pagar Mensalidade</h1>
        <p class="subtitle">Registrar pagamento de mensalidade</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="../pagamentos/index.php">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="index.php" class="active">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<?php if ($sucesso && !$mostrar_fatura): ?>
<div class="alert alert-success">✅ <?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert-error">
    <strong>❌ <?= $erro ?></strong>
    <?php if (!empty($alunos_lista)): ?>
    <div class="sugestoes-alunos">
        <strong>💡 Alunos ativos disponíveis:</strong>
        <ul>
            <?php foreach($alunos_lista as $a): ?>
            <li>
                <a href="pagar.php?id=<?= $a['id'] ?>">
                    #<?= $a['id'] ?> - <?= htmlspecialchars($a['nome']) ?>
                    <?php if ($a['status'] == 'ativo'): ?>
                    <span style="color:#27ae60;">✅</span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <p style="margin-top: 10px; font-size: 13px; color: #78350f;">
            Clique no nome do aluno para acessar o pagamento.
        </p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($aluno): ?>
<div class="form-container">
    <!-- Dados do Aluno -->
    <div class="dados-aluno-card">
        <div style="font-weight: 600; margin-bottom: 10px; color: #1a2332; font-size: 16px;">
            👨‍🎓 Dados do Aluno
        </div>
        <div class="info-row">
            <span><span class="label">Nome:</span> <span class="valor"><?= htmlspecialchars($aluno['nome']) ?></span></span>
            <span><span class="label">ID:</span> <span class="valor">#<?= $aluno['id'] ?></span></span>
            <span><span class="label">Classe:</span> <span class="valor"><?= htmlspecialchars($aluno['classe'] ?? 'N/A') ?></span></span>
        </div>
        <div class="info-row">
            <span><span class="label">Turma:</span> <span class="valor"><?= htmlspecialchars($aluno['turma'] ?? 'N/A') ?></span></span>
            <span><span class="label">Gênero:</span> <span class="valor"><?= ($aluno['genero'] ?? '') == 'M' ? 'Masculino' : 'Feminino' ?></span></span>
            <span><span class="label">Curso:</span> <span class="valor"><?= htmlspecialchars($aluno['curso'] ?? 'N/A') ?></span></span>
        </div>
    </div>

    <?php if ($mensalidade): ?>
    <div class="mensalidade-info">
        <strong>📅 Mensalidade:</strong>
        <?= htmlspecialchars($mensalidade['mes']) ?>/<?= $mensalidade['ano'] ?> - 
        R$ <?= number_format($mensalidade['valor'] ?? 0, 2, ',', '.') ?> - 
        Status: <span style="font-weight:bold;color:#e74c3c;"><?= $mensalidade['status'] ?? 'Pendente' ?></span>
        <?php if (!empty($mensalidade['data_vencimento'])): ?>
        - Vencimento: <?= date('d/m/Y', strtotime($mensalidade['data_vencimento'])) ?>
        <?php endif; ?>
    </div>
    <input type="hidden" name="mensalidade_id" value="<?= $mensalidade['id'] ?>">
    <?php endif; ?>

    <?php if ($ultimo_pagamento): ?>
    <div class="pagamento-info">
        <strong>📋 Último Pagamento:</strong>
        R$ <?= number_format($ultimo_pagamento['valor'] ?? 0, 2, ',', '.') ?> - 
        Status: <span style="font-weight:bold;color:#27ae60;"><?= $ultimo_pagamento['status'] ?? 'Pago' ?></span>
        <?php if (!empty($ultimo_pagamento['data_pagamento'])): ?>
        - Data: <?= date('d/m/Y', strtotime($ultimo_pagamento['data_pagamento'])) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Formulário -->
    <form method="POST" action="" onsubmit="return validarFormulario()">
        <input type="hidden" name="mensalidade_id" value="<?= $mensalidade_id ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label>Descrição <span class="required">*</span></label>
                <select name="emolumento_id" id="emolumento_id" required onchange="atualizarValor()">
                    <option value="">Selecione</option>
                    <?php foreach($emolumentos as $emol): ?>
                    <option value="<?= $emol['id'] ?>" data-valor="<?= $emol['valor'] ?>" data-nome="<?= htmlspecialchars($emol['nome']) ?>" <?= ($_POST['emolumento_id'] ?? '') == $emol['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emol['nome']) ?> - R$ <?= number_format($emol['valor'], 2, ',', '.') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Valor Base <span class="required">*</span></label>
                <input type="number" step="0.01" name="valor_base" id="valor_base" value="<?= $mensalidade['valor'] ?? $_POST['valor_base'] ?? '' ?>" required onchange="calcularDesconto()" oninput="calcularDesconto()">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Mês de Referência</label>
                <select name="mes_referencia" id="mes_referencia">
                    <option value="-">-</option>
                    <?php
                    $meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                              'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                    $mes_selecionado = $mensalidade['mes'] ?? $_POST['mes_referencia'] ?? '';
                    foreach($meses as $mes):
                    ?>
                    <option value="<?= $mes ?>" <?= $mes_selecionado == $mes ? 'selected' : '' ?>><?= $mes ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="mes-hint" id="mesHint">💡 Selecione o mês referente ao pagamento</div>
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
            <strong>Valor a Pagar: <span id="resumo_final" style="font-size: 18px; font-weight: bold; color: #c0392b;">R$ 0,00</span></strong>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Data do Pagamento <span class="required">*</span></label>
                <input type="date" name="data_pagamento" value="<?= $_POST['data_pagamento'] ?? date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Referência</label>
                <input type="text" name="referencia" value="<?= htmlspecialchars($_POST['referencia'] ?? '') ?>" placeholder="Nº do comprovante, recibo...">
            </div>
        </div>

        <div class="form-group">
            <label>Observações</label>
            <textarea name="observacoes" placeholder="Observações sobre o pagamento"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
        </div>

        <div class="info-valor">
            💡 O pagamento será registrado como <strong>PAGO</strong> e a mensalidade será atualizada automaticamente.
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">✅ Registrar Pagamento</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
<?php endif; ?>

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
        
        if (selectedOption.value) {
            const valor = parseFloat(selectedOption.dataset.valor) || 0;
            valorInput.value = valor.toFixed(2);
            calcularDesconto();
        } else {
            valorInput.value = '';
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
        
        document.getElementById('valor_liquido').value = valorLiquido.toFixed(2);
        document.getElementById('resumo_base').textContent = 'R$ ' + valorBase.toFixed(2).replace('.', ',');
        document.getElementById('resumo_desconto').textContent = 'R$ ' + descontoValor.toFixed(2).replace('.', ',');
        document.getElementById('resumo_final').textContent = 'R$ ' + valorLiquido.toFixed(2).replace('.', ',');
        
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
     * Valida o formulário antes de enviar
     */
    function validarFormulario() {
        const emolumento = document.getElementById('emolumento_id').value;
        if (!emolumento) {
            alert('⚠️ Por favor, selecione um emolumento.');
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
        window.location.href = 'index.php';
    }
    
    /**
     * Inicializar
     */
    document.addEventListener('DOMContentLoaded', function() {
        atualizarValor();
    });
</script>

<?php include '../../includes/footer_escola.php'; ?>