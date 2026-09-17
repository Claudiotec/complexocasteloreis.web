<?php
// ============================================
// modules/escola/financeiro/pagamentos/add.php - Novo Pagamento
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'criar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== FUNÇÕES =====
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

function mesNomeParaNumero($nome) {
    $mapa = array(
        'Janeiro' => 1, 'Fevereiro' => 2, 'Março' => 3, 'Abril' => 4,
        'Maio' => 5, 'Junho' => 6, 'Julho' => 7, 'Agosto' => 8,
        'Setembro' => 9, 'Outubro' => 10, 'Novembro' => 11, 'Dezembro' => 12
    );
    return $mapa[$nome] ?? 0;
}

function mesNumeroParaNome($num) {
    $mapa = array(
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    );
    return $mapa[$num] ?? '';
}

// ===== CALCULAR MULTA =====
function calcularMulta($pdo, $emolumento_id, $mes, $ano, $valor_base) {
    try {
        $stmt = $pdo->prepare("
            SELECT multa_tipo, multa_valor, prazo_dias 
            FROM emolumentos 
            WHERE id = ?
        ");
        $stmt->execute([$emolumento_id]);
        $config = $stmt->fetch();
        
        if (!$config) {
            $stmt = $pdo->prepare("
                SELECT multa_tipo, multa_valor, prazo_dias 
                FROM mensalidades 
                WHERE emolumento_id = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$emolumento_id]);
            $config = $stmt->fetch();
        }
        
        if (!$config) {
            $config = [
                'multa_tipo' => 'percentual',
                'multa_valor' => 10,
                'prazo_dias' => 10
            ];
        }
        
        $data_vencimento = date("Y-m-d", strtotime("$ano-$mes-10"));
        $data_atual = date('Y-m-d');
        
        $diff = strtotime($data_atual) - strtotime($data_vencimento);
        $dias_atraso = floor($diff / (60 * 60 * 24));
        
        if ($dias_atraso <= $config['prazo_dias']) {
            return 0;
        }
        
        if ($config['multa_tipo'] == 'percentual') {
            $multa = ($valor_base * $config['multa_valor']) / 100;
        } else {
            $multa = $config['multa_valor'];
        }
        
        return $multa;
        
    } catch (Exception $e) {
        error_log("Erro ao calcular multa: " . $e->getMessage());
        return 0;
    }
}

// ===== ATUALIZAR MENSALIDADE =====
function atualizarMensalidade($pdo, $aluno_id, $mes_numero, $ano, $valor_pago, $emolumento_id = null) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, status FROM mensalidades 
            WHERE aluno_id = ? AND mes = ? AND ano = ?
        ");
        $stmt->execute([$aluno_id, $mes_numero, $ano]);
        $mensalidade = $stmt->fetch();
        
        if ($mensalidade) {
            $stmt = $pdo->prepare("
                UPDATE mensalidades 
                SET status = 'pago', valor_pago = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$valor_pago, $mensalidade['id']]);
            return true;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO mensalidades 
                (aluno_id, mes, ano, valor, valor_pago, status, emolumento_id, data_vencimento, descricao) 
                VALUES (?, ?, ?, ?, ?, 'pago', ?, NOW(), 'Mensalidade escolar')
            ");
            $stmt->execute([$aluno_id, $mes_numero, $ano, $valor_pago, $valor_pago, $emolumento_id]);
            return true;
        }
    } catch (Exception $e) {
        return false;
    }
}

// ===== VERIFICAR DUPLICADO =====
function verificarDuplicadoDetalhado($pdo, $aluno_id, $emolumento_id, $mes_referencia) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, e.nome as emolumento_nome 
            FROM pagamentos p
            LEFT JOIN emolumentos e ON p.emolumento_id = e.id
            WHERE p.aluno_id = ? AND p.emolumento_id = ? AND p.mes_referencia = ? 
            AND p.status IN ('confirmado', 'Pago', 'pago')
            ORDER BY p.id DESC LIMIT 1
        ");
        $stmt->execute([$aluno_id, $emolumento_id, $mes_referencia]);
        $pagamento = $stmt->fetch();
        
        if ($pagamento) {
            return [
                'duplicado' => true,
                'fonte' => 'pagamentos',
                'detalhes' => [
                    'emolumento' => $pagamento['emolumento_nome'] ?? 'Desconhecido',
                    'valor' => $pagamento['valor'] ?? 0,
                    'data' => $pagamento['data_pagamento'] ?? 'Desconhecida'
                ]
            ];
        }
        
        if ($mes_referencia != '-') {
            $partes = explode('/', $mes_referencia);
            if (count($partes) == 2) {
                $mes_nome = $partes[0];
                $ano = intval($partes[1]);
                $mes_numero = mesNomeParaNumero($mes_nome);
                
                if ($mes_numero > 0) {
                    $stmt = $pdo->prepare("
                        SELECT * FROM mensalidades 
                        WHERE aluno_id = ? AND mes = ? AND ano = ? AND status = 'pago'
                        LIMIT 1
                    ");
                    $stmt->execute([$aluno_id, $mes_numero, $ano]);
                    $mensalidade = $stmt->fetch();
                    
                    if ($mensalidade) {
                        return [
                            'duplicado' => true,
                            'fonte' => 'mensalidades',
                            'detalhes' => [
                                'emolumento' => 'Mensalidade',
                                'valor' => $mensalidade['valor'] ?? 0,
                                'data' => $mensalidade['updated_at'] ?? 'Desconhecida'
                            ]
                        ];
                    }
                }
            }
        }
        
        return ['duplicado' => false];
        
    } catch (Exception $e) {
        error_log("Erro ao verificar duplicado detalhado: " . $e->getMessage());
        return ['duplicado' => false];
    }
}

// ===== BUSCAR EMOLUMENTOS AGRUPADOS =====
function buscarEmolumentosAgrupados($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, nome, valor 
            FROM emolumentos 
            WHERE status = 'ativo' 
            GROUP BY nome 
            ORDER BY nome
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// ===== GERAR FATURA COM MÚLTIPLOS ITENS (COM DUAS VIAS) =====
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
    $itens = $dados['itens'] ?? [];
    $numero_fatura = $dados['numero_fatura'] ?? 'FAT-' . date('YmdHis');
    $data_pagamento = $dados['data_pagamento'] ?? date('d/m/Y');
    $forma_pagamento = $dados['forma_pagamento'] ?? 'Dinheiro';
    $referencia = $dados['referencia'] ?? '';
    $observacoes = $dados['observacoes'] ?? '';
    $data_emissao = date('d/m/Y H:i:s');
    
    $total_base = 0;
    $total_multa = 0;
    $total_desconto = 0;
    $total_liquido = 0;
    
    foreach ($itens as $item) {
        $total_base += $item['valor_base'] ?? 0;
        $total_multa += $item['multa'] ?? 0;
        $total_desconto += $item['desconto'] ?? 0;
        $total_liquido += $item['valor_liquido'] ?? 0;
    }

    // Função para gerar uma via da fatura
    function gerarViaFatura($tipo, $dados, $numero_fatura, $data_emissao, $total_base, $total_multa, $total_desconto, $total_liquido) {
        extract($dados);
        
        $html = '';
        
        // Cabeçalho da via
        if ($tipo == 'cliente') {
            $html .= '<div style="text-align:center;background:#1a2332;color:#fff;padding:4px 0;font-size:12px;font-weight:bold;letter-spacing:2px;margin-bottom:8px;">VIA DO CLIENTE</div>';
        } else {
            $html .= '<div style="text-align:center;background:#c9a84c;color:#1a2332;padding:4px 0;font-size:12px;font-weight:bold;letter-spacing:2px;margin-bottom:8px;">VIA DA ESCOLA</div>';
        }
        
        $html .= '
        <div class="fatura-container" style="' . ($tipo == 'escola' ? 'border:2px dashed #c9a84c;' : '') . '">
            <div class="header">
                <div class="header-top">
                    <div><span class="logo-icon">🎓</span> <span class="titulo-documento">FATURA</span></div>
                    <div class="numero-fatura"><strong>Nº:</strong> ' . $numero_fatura . '<br><span style="font-size:10px;">Emissão: ' . $data_emissao . '</span></div>
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
                    <span><span class="label">Nome:</span> ' . htmlspecialchars($aluno_nome) . '</span>
                    <span><span class="label">ID:</span> #' . htmlspecialchars($aluno_id) . '</span>
                    <span><span class="label">Classe:</span> ' . htmlspecialchars($aluno_classe) . '</span>
                </div>
                <div class="linha">
                    <span><span class="label">Turma:</span> ' . htmlspecialchars($aluno_turma) . '</span>
                    ' . ($aluno_genero ? '<span><span class="label">Gênero:</span> ' . ($aluno_genero == 'M' ? 'Masculino' : 'Feminino') . '</span>' : '') . '
                    <span><span class="label">Data Pagamento:</span> ' . date('d/m/Y', strtotime($data_pagamento)) . '</span>
                </div>
            </div>
            <table class="tabela-itens">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="width:25%;">Descrição</th>
                        <th style="width:12%;">Mês</th>
                        <th style="width:13%;">Valor Base</th>
                        <th style="width:10%;">Multa</th>
                        <th style="width:10%;">Desconto</th>
                        <th style="width:13%;">Total</th>
                    </tr>
                </thead>
                <tbody>';
        
        $item_num = 1;
        foreach ($itens as $item) {
            $valor_base_f = number_format($item['valor_base'] ?? 0, 2, ',', '.');
            $multa_f = number_format($item['multa'] ?? 0, 2, ',', '.');
            $desconto_f = number_format($item['desconto'] ?? 0, 2, ',', '.');
            $valor_liquido_f = number_format($item['valor_liquido'] ?? 0, 2, ',', '.');
            $mes_exibicao = ($item['mes_referencia'] ?? '-') != '-' ? ($item['mes_referencia'] ?? '-') . '/' . ($item['ano_referencia'] ?? date('Y')) : '-';
            
            $html .= '
                    <tr>
                        <td>' . $item_num . '</td>
                        <td class="text-left">' . htmlspecialchars($item['emolumento'] ?? '') . '</td>
                        <td>' . $mes_exibicao . '</td>
                        <td class="text-right">' . $valor_base_f . ' Kz</td>
                        <td class="text-right">' . $multa_f . ' Kz</td>
                        <td class="text-right">' . $desconto_f . ' Kz</td>
                        <td class="text-right"><strong>' . $valor_liquido_f . ' Kz</strong></td>
                    </tr>';
            $item_num++;
        }
        
        $total_base_f = number_format($total_base, 2, ',', '.');
        $total_multa_f = number_format($total_multa, 2, ',', '.');
        $total_desconto_f = number_format($total_desconto, 2, ',', '.');
        $total_liquido_f = number_format($total_liquido, 2, ',', '.');
        
        $html .= '
                    <tr class="total-row">
                        <td colspan="3" class="text-right" style="font-size:14px;">TOTAIS</td>
                        <td class="text-right">' . $total_base_f . ' Kz</td>
                        <td class="text-right">' . $total_multa_f . ' Kz</td>
                        <td class="text-right">' . $total_desconto_f . ' Kz</td>
                        <td class="text-right valor-total">' . $total_liquido_f . ' Kz</td>
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
                    <p><strong>📦 Itens:</strong> ' . count($itens) . '</p>
                    ' . ($observacoes ? '<p><strong>📝 Obs.:</strong> ' . htmlspecialchars($observacoes) . '</p>' : '') . '
                </div>
                <div class="coluna">
                    <p><strong>💰 Total Itens:</strong> ' . count($itens) . '</p>
                    <p><strong>💵 Valor Total:</strong> <span style="color:#c0392b;font-weight:bold;font-size:16px;">' . $total_liquido_f . ' Kz</span></p>
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
                </div>
            </div>
        </div>';
        
        return $html;
    }

    // Construir a página HTML com duas vias
    $html = '<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Fatura</title>
    <style>
        body{font-family:"Times New Roman",serif;background:#f0f0f0;padding:20px;margin:0}
        .fatura-page{max-width:210mm;margin:auto;background:#fff;padding:20px;border:1px solid #ccc}
        .fatura-container{max-width:100%;margin:auto;background:#fff;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:10px}
        .header{border-bottom:3px double #1a2332;padding-bottom:8px;margin-bottom:10px}
        .header-top{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap}
        .logo-icon{width:40px;height:40px;background:#1a2332;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#c9a84c;font-size:20px;font-weight:bold;border:2px solid #c9a84c}
        .titulo-documento{font-size:18px;font-weight:bold;color:#1a2332;margin-left:8px}
        .numero-fatura{text-align:right;font-size:11px;color:#555;padding:3px 12px;border:1px solid #ccc;border-radius:4px;background:#f9f9f9}
        .info-escola{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #ddd;margin-bottom:8px;flex-wrap:wrap}
        .info-escola .escola h2{font-size:14px;color:#1a2332;margin:0}
        .info-escola .escola p{font-size:11px;color:#555;margin:1px 0}
        .nif-box{border:1px solid #1a2332;padding:2px 10px;font-size:12px;font-weight:bold;background:#f8fafc;align-self:center}
        .dados-aluno{border:1px solid #1a2332;padding:8px 10px;margin-bottom:8px;background:#fafafa}
        .dados-aluno .titulo-secao{font-weight:bold;font-size:12px;text-align:center;border-bottom:1px solid #1a2332;padding-bottom:3px;margin-bottom:4px}
        .dados-aluno .linha{display:flex;justify-content:space-between;flex-wrap:wrap;font-size:11px;padding:1px 0}
        .dados-aluno .label{font-weight:600}
        .tabela-itens{width:100%;border-collapse:collapse;margin-bottom:8px;font-size:11px}
        .tabela-itens th{background:#1a2332;color:#fff;padding:4px 6px;text-align:center;border:1px solid #1a2332;font-size:10px}
        .tabela-itens td{padding:4px 6px;border:1px solid #ccc;text-align:center;font-size:10px}
        .tabela-itens .text-right{text-align:right;padding-right:8px}
        .tabela-itens .text-left{text-align:left;padding-left:8px}
        .tabela-itens .total-row{background:#f8fafc;font-weight:bold}
        .tabela-itens .total-row td{border-top:2px solid #1a2332}
        .tabela-itens .valor-total{color:#c0392b;font-size:14px}
        .resumo{display:flex;justify-content:space-between;flex-wrap:wrap;gap:5px;padding:6px 10px;background:#f8fafc;border:1px solid #ddd;margin-bottom:8px;font-size:10px}
        .resumo .coluna{flex:1;min-width:120px}
        .resumo .coluna p{font-size:10px;margin:1px 0;color:#555}
        .footer{border-top:3px double #1a2332;padding-top:8px;margin-top:8px;display:flex;justify-content:space-between;font-size:10px;color:#666;flex-wrap:wrap}
        .footer .assinatura{text-align:center;flex:1}
        .footer .assinatura .linha{width:150px;border-top:1px solid #1a2332;margin:5px auto 2px}
        .no-print{text-align:center;margin-top:15px}
        .no-print button{padding:10px 30px;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer}
        .btn-print{background:#1a2332;color:#fff}
        .btn-close{background:#e74c3c;color:#fff;margin-left:10px}
        .via-divider{text-align:center;font-size:14px;font-weight:bold;color:#1a2332;padding:8px 0;border-top:2px dashed #ccc;border-bottom:2px dashed #ccc;margin:10px 0}
        .via-divider span{background:#fff;padding:0 20px}
        @media print{.no-print{display:none}.fatura-page{background:#fff;padding:15px;border:none;box-shadow:none}.fatura-container{page-break-inside:avoid;border:1px solid #ddd}.via-divider{page-break-after:avoid}}
        @media(max-width:600px){.header-top{flex-direction:column;text-align:center}.numero-fatura{text-align:center}.info-escola{flex-direction:column;text-align:center}.dados-aluno .linha{flex-direction:column;text-align:center}.resumo{flex-direction:column;text-align:center}.footer{flex-direction:column;text-align:center}}
        .via-cliente{background:#f8fafc}
        .via-escola{background:#fffef8}
    </style>
    </head><body>
    <div class="fatura-page">';
    
    // Via do Cliente
    $html .= '<div class="via-cliente">';
    $html .= gerarViaFatura('cliente', $dados, $numero_fatura, $data_emissao, $total_base, $total_multa, $total_desconto, $total_liquido);
    $html .= '</div>';
    
    // Separador entre as vias
    $html .= '<div class="via-divider"><span>✂️ CORTE AQUI ✂️</span></div>';
    
    // Via da Escola
    $html .= '<div class="via-escola">';
    $html .= gerarViaFatura('escola', $dados, $numero_fatura, $data_emissao, $total_base, $total_multa, $total_desconto, $total_liquido);
    $html .= '</div>';
    
    // Botões
    $html .= '
    </div>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ IMPRIMIR</button>
        <button class="btn-close" onclick="window.close()">✕ FECHAR</button>
    </div>
    </body></html>';
    
    return $html;
}

function salvarFatura($html, $numero_fatura) {
    $pasta = '../../../../faturas/';
    if (!file_exists($pasta)) mkdir($pasta, 0777, true);
    $arquivo = $pasta . 'fatura_' . $numero_fatura . '.html';
    file_put_contents($arquivo, $html);
    return $arquivo;
}

// ===== ADICIONAR COLUNA MES_REFERENCIA SE NÃO EXISTIR =====
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM pagamentos LIKE 'mes_referencia'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE pagamentos ADD COLUMN mes_referencia VARCHAR(30) DEFAULT '-'");
    }
} catch (Exception $e) {}

// ===== FUNÇÃO PARA IMPORTAR ALUNOS (CORRIGIDA) =====
function importarPlanilhaAlunosCSV($pdo, $file) {
    try {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Se for XLSX, tentar usar PhpSpreadsheet
        if ($ext === 'xlsx' || $ext === 'xls') {
            if (file_exists('../../../../vendor/autoload.php')) {
                require_once '../../../../vendor/autoload.php';
                return importarPlanilhaAlunosPhpSpreadsheet($pdo, $file);
            } else {
                return ['success' => false, 'message' => 'Arquivo .xlsx não suportado. Instale o Composer: composer require phpoffice/phpspreadsheet, OU converta para CSV.'];
            }
        }
        
        if ($ext !== 'csv') {
            return ['success' => false, 'message' => 'Formato não suportado. Use CSV ou XLSX.'];
        }
        
        // Verificar se o arquivo existe
        if (!file_exists($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Arquivo temporário não encontrado'];
        }
        
        // Abrir arquivo CSV com tratamento de erro
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return ['success' => false, 'message' => 'Não foi possível abrir o arquivo'];
        }
        
        // Ler o arquivo linha por linha
        $rows = [];
        $row_number = 0;
        while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
            $row_number++;
            // Ignorar linhas vazias
            if (empty(array_filter($row))) continue;
            $rows[] = $row;
        }
        fclose($handle);
        
        if (empty($rows)) {
            return ['success' => false, 'message' => 'Arquivo vazio ou inválido'];
        }
        
        // Mapear cabeçalhos (primeira linha)
        $headers = array_map('trim', $rows[0]);
        $header_map = [];
        foreach ($headers as $index => $header) {
            $header_clean = strtolower(removerAcentos(trim($header)));
            // Remover caracteres especiais do cabeçalho
            $header_clean = preg_replace('/[^a-z0-9_]/', '', $header_clean);
            $header_map[$header_clean] = $index;
        }
        
        // Verificar se o cabeçalho 'nome' existe
        if (!isset($header_map['nome'])) {
            return ['success' => false, 'message' => 'Cabeçalho "nome" não encontrado no arquivo'];
        }
        
        // Iniciar transação APENAS se não estiver ativa
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
        $inserted = 0;
        
        // Criar tabela se não existir (COM A ESTRUTURA CORRETA - SEM Coluna1)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `alunos_ano_anterior` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `nome` VARCHAR(255) DEFAULT NULL,
                `Sexo` VARCHAR(10) DEFAULT NULL,
                `dia` VARCHAR(10) DEFAULT NULL,
                `mes` VARCHAR(20) DEFAULT NULL,
                `Ano` VARCHAR(10) DEFAULT NULL,
                `Morada` TEXT DEFAULT NULL,
                `Cadastro_Transporte` VARCHAR(10) DEFAULT NULL,
                `Contacto_do_Aluno` VARCHAR(50) DEFAULT NULL,
                `Debilidade` VARCHAR(100) DEFAULT NULL,
                `Idade` INT(11) DEFAULT NULL,
                `Naturalidade` VARCHAR(100) DEFAULT NULL,
                `Município` VARCHAR(100) DEFAULT NULL,
                `Província` VARCHAR(100) DEFAULT NULL,
                `N_BI` VARCHAR(50) DEFAULT NULL,
                `Classe` VARCHAR(10) DEFAULT NULL,
                `Nome_do_Pai` VARCHAR(255) DEFAULT NULL,
                `Morada3` TEXT DEFAULT NULL,
                `Contacto4` VARCHAR(50) DEFAULT NULL,
                `Ocupacao` VARCHAR(100) DEFAULT NULL,
                `Local_de_Trabalho` VARCHAR(255) DEFAULT NULL,
                `Nome_da_mae` VARCHAR(255) DEFAULT NULL,
                `Contacto_Mae` VARCHAR(50) DEFAULT NULL,
                `Data_Matricula` DATE DEFAULT NULL,
                `Ocupacao_do_Aluno` VARCHAR(100) DEFAULT NULL,
                `Periodo` VARCHAR(20) DEFAULT NULL,
                `Data_Emissao_do_BI` VARCHAR(50) DEFAULT NULL,
                `Arq_identificação` VARCHAR(100) DEFAULT NULL,
                `Situacao_Cadastro` VARCHAR(50) DEFAULT NULL,
                `TURMA` VARCHAR(50) DEFAULT NULL,
                `SALA` VARCHAR(50) DEFAULT NULL,
                `Curso` VARCHAR(50) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Preparar a consulta SEM a coluna Coluna1
        $stmt = $pdo->prepare("
            INSERT INTO alunos_ano_anterior 
            (nome, Sexo, dia, mes, Ano, Morada, Cadastro_Transporte, Contacto_do_Aluno, 
             Debilidade, Idade, Naturalidade, Município, Província, N_BI, Classe, 
             Nome_do_Pai, Morada3, Contacto4, Ocupacao, Local_de_Trabalho, Nome_da_mae, 
             Contacto_Mae, Data_Matricula, Ocupacao_do_Aluno, Periodo, Data_Emissao_do_BI, 
             Arq_identificação, Situacao_Cadastro, TURMA, SALA, Curso) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Processar linhas (começando da linha 1, ignorando o cabeçalho)
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) continue;
            
            // Extrair dados com verificação de existência
            $nome = isset($row[$header_map['nome'] ?? -1]) ? trim($row[$header_map['nome'] ?? -1]) : null;
            if (empty($nome)) continue;
            
            $sexo = isset($row[$header_map['sexo'] ?? -1]) ? trim($row[$header_map['sexo'] ?? -1]) : null;
            $dia = isset($row[$header_map['dia'] ?? -1]) ? trim($row[$header_map['dia'] ?? -1]) : null;
            $mes = isset($row[$header_map['mes'] ?? -1]) ? trim($row[$header_map['mes'] ?? -1]) : null;
            $ano = isset($row[$header_map['ano'] ?? -1]) ? trim($row[$header_map['ano'] ?? -1]) : null;
            $morada = isset($row[$header_map['morada'] ?? -1]) ? trim($row[$header_map['morada'] ?? -1]) : null;
            $cadastro_transporte = isset($row[$header_map['cadastro_transporte'] ?? -1]) ? trim($row[$header_map['cadastro_transporte'] ?? -1]) : null;
            $contacto_aluno = isset($row[$header_map['contacto_do_aluno'] ?? -1]) ? trim($row[$header_map['contacto_do_aluno'] ?? -1]) : null;
            $debilidade = isset($row[$header_map['debilidade'] ?? -1]) ? trim($row[$header_map['debilidade'] ?? -1]) : null;
            $idade = isset($row[$header_map['idade'] ?? -1]) ? trim($row[$header_map['idade'] ?? -1]) : null;
            $naturalidade = isset($row[$header_map['naturalidade'] ?? -1]) ? trim($row[$header_map['naturalidade'] ?? -1]) : null;
            $municipio = isset($row[$header_map['município'] ?? -1]) ? trim($row[$header_map['município'] ?? -1]) : null;
            $provincia = isset($row[$header_map['província'] ?? -1]) ? trim($row[$header_map['província'] ?? -1]) : null;
            $n_bi = isset($row[$header_map['n_bi'] ?? -1]) ? trim($row[$header_map['n_bi'] ?? -1]) : null;
            $classe = isset($row[$header_map['classe'] ?? -1]) ? trim($row[$header_map['classe'] ?? -1]) : null;
            $nome_pai = isset($row[$header_map['nome_do_pai'] ?? -1]) ? trim($row[$header_map['nome_do_pai'] ?? -1]) : null;
            $morada3 = isset($row[$header_map['morada3'] ?? -1]) ? trim($row[$header_map['morada3'] ?? -1]) : null;
            $contacto4 = isset($row[$header_map['contacto4'] ?? -1]) ? trim($row[$header_map['contacto4'] ?? -1]) : null;
            $ocupacao = isset($row[$header_map['ocupacao'] ?? -1]) ? trim($row[$header_map['ocupacao'] ?? -1]) : null;
            $local_trabalho = isset($row[$header_map['local_de_trabalho'] ?? -1]) ? trim($row[$header_map['local_de_trabalho'] ?? -1]) : null;
            $nome_mae = isset($row[$header_map['nome_da_mae'] ?? -1]) ? trim($row[$header_map['nome_da_mae'] ?? -1]) : null;
            $contacto_mae = isset($row[$header_map['contacto_mae'] ?? -1]) ? trim($row[$header_map['contacto_mae'] ?? -1]) : null;
            $data_matricula = isset($row[$header_map['data_matricula'] ?? -1]) ? trim($row[$header_map['data_matricula'] ?? -1]) : null;
            $ocupacao_aluno = isset($row[$header_map['ocupacao_do_aluno'] ?? -1]) ? trim($row[$header_map['ocupacao_do_aluno'] ?? -1]) : null;
            $periodo = isset($row[$header_map['periodo'] ?? -1]) ? trim($row[$header_map['periodo'] ?? -1]) : null;
            $data_emissao_bi = isset($row[$header_map['data_emissao_do_bi'] ?? -1]) ? trim($row[$header_map['data_emissao_do_bi'] ?? -1]) : null;
            $arq_identificacao = isset($row[$header_map['arq_identificação'] ?? -1]) ? trim($row[$header_map['arq_identificação'] ?? -1]) : null;
            $situacao_cadastro = isset($row[$header_map['situacao_cadastro'] ?? -1]) ? trim($row[$header_map['situacao_cadastro'] ?? -1]) : null;
            $turma = isset($row[$header_map['turma'] ?? -1]) ? trim($row[$header_map['turma'] ?? -1]) : null;
            $sala = isset($row[$header_map['sala'] ?? -1]) ? trim($row[$header_map['sala'] ?? -1]) : null;
            $curso = isset($row[$header_map['curso'] ?? -1]) ? trim($row[$header_map['curso'] ?? -1]) : null;
            
            // Converter data matricula
            $data_matricula_formatada = null;
            if (!empty($data_matricula)) {
                $date = DateTime::createFromFormat('Y-m-d', $data_matricula);
                if ($date) {
                    $data_matricula_formatada = $date->format('Y-m-d');
                } else {
                    // Tentar outros formatos
                    $date = DateTime::createFromFormat('d/m/Y', $data_matricula);
                    if ($date) {
                        $data_matricula_formatada = $date->format('Y-m-d');
                    }
                }
            }
            
            // Executar INSERT
            try {
                $stmt->execute([
                    $nome, $sexo, $dia, $mes, $ano, $morada, $cadastro_transporte, $contacto_aluno,
                    $debilidade, $idade, $naturalidade, $municipio, $provincia, $n_bi, $classe,
                    $nome_pai, $morada3, $contacto4, $ocupacao, $local_trabalho, $nome_mae,
                    $contacto_mae, $data_matricula_formatada, $ocupacao_aluno, $periodo, $data_emissao_bi,
                    $arq_identificacao, $situacao_cadastro, $turma, $sala, $curso
                ]);
                $inserted++;
            } catch (PDOException $e) {
                // Log do erro mas continua
                error_log("Erro ao inserir linha $i: " . $e->getMessage());
                // Continua com a próxima linha
            }
        }
        
        // Commit da transação
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        
        return ['success' => true, 'inserted' => $inserted, 'message' => "Importados $inserted alunos com sucesso!"];
        
    } catch (Exception $e) {
        // Rollback apenas se houver transação ativa
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Erro na importação: ' . $e->getMessage()];
    }
}

function importarPlanilhaAlunosPhpSpreadsheet($pdo, $file) {
    try {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        if (empty($rows)) {
            return ['success' => false, 'message' => 'Arquivo vazio ou inválido'];
        }
        
        $headers = array_map('trim', $rows[0]);
        $header_map = [];
        foreach ($headers as $index => $header) {
            $header_clean = strtolower(removerAcentos(trim($header)));
            $header_map[$header_clean] = $index;
        }
        
        $pdo->beginTransaction();
        $inserted = 0;
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `alunos_ano_anterior` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `nome` VARCHAR(255) DEFAULT NULL,
                `Sexo` VARCHAR(10) DEFAULT NULL,
                `dia` VARCHAR(10) DEFAULT NULL,
                `mes` VARCHAR(20) DEFAULT NULL,
                `Ano` VARCHAR(10) DEFAULT NULL,
                `Morada` TEXT DEFAULT NULL,
                `Cadastro_Transporte` VARCHAR(10) DEFAULT NULL,
                `Contacto_do_Aluno` VARCHAR(50) DEFAULT NULL,
                `Debilidade` VARCHAR(100) DEFAULT NULL,
                `Idade` INT(11) DEFAULT NULL,
                `Naturalidade` VARCHAR(100) DEFAULT NULL,
                `Município` VARCHAR(100) DEFAULT NULL,
                `Província` VARCHAR(100) DEFAULT NULL,
                `N_BI` VARCHAR(50) DEFAULT NULL,
                `Classe` VARCHAR(10) DEFAULT NULL,
                `Nome_do_Pai` VARCHAR(255) DEFAULT NULL,
                `Morada3` TEXT DEFAULT NULL,
                `Contacto4` VARCHAR(50) DEFAULT NULL,
                `Ocupacao` VARCHAR(100) DEFAULT NULL,
                `Local_de_Trabalho` VARCHAR(255) DEFAULT NULL,
                `Nome_da_mae` VARCHAR(255) DEFAULT NULL,
                `Contacto_Mae` VARCHAR(50) DEFAULT NULL,
                `Data_Matricula` DATE DEFAULT NULL,
                `Ocupacao_do_Aluno` VARCHAR(100) DEFAULT NULL,
                `Periodo` VARCHAR(20) DEFAULT NULL,
                `Data_Emissao_do_BI` VARCHAR(50) DEFAULT NULL,
                `Arq_identificação` VARCHAR(100) DEFAULT NULL,
                `Situacao_Cadastro` VARCHAR(50) DEFAULT NULL,
                `TURMA` VARCHAR(50) DEFAULT NULL,
                `SALA` VARCHAR(50) DEFAULT NULL,
                `Curso` VARCHAR(50) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO alunos_ano_anterior 
            (nome, Sexo, dia, mes, Ano, Morada, Cadastro_Transporte, Contacto_do_Aluno, 
             Debilidade, Idade, Naturalidade, Município, Província, N_BI, Classe, 
             Nome_do_Pai, Morada3, Contacto4, Ocupacao, Local_de_Trabalho, Nome_da_mae, 
             Contacto_Mae, Data_Matricula, Ocupacao_do_Aluno, Periodo, Data_Emissao_do_BI, 
             Arq_identificação, Situacao_Cadastro, TURMA, SALA, Curso) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) continue;
            
            $nome = isset($row[$header_map['nome'] ?? -1]) ? trim($row[$header_map['nome'] ?? -1]) : null;
            if (empty($nome)) continue;
            
            $sexo = isset($row[$header_map['sexo'] ?? -1]) ? trim($row[$header_map['sexo'] ?? -1]) : null;
            $dia = isset($row[$header_map['dia'] ?? -1]) ? trim($row[$header_map['dia'] ?? -1]) : null;
            $mes = isset($row[$header_map['mes'] ?? -1]) ? trim($row[$header_map['mes'] ?? -1]) : null;
            $ano = isset($row[$header_map['ano'] ?? -1]) ? trim($row[$header_map['ano'] ?? -1]) : null;
            $morada = isset($row[$header_map['morada'] ?? -1]) ? trim($row[$header_map['morada'] ?? -1]) : null;
            $cadastro_transporte = isset($row[$header_map['cadastro_transporte'] ?? -1]) ? trim($row[$header_map['cadastro_transporte'] ?? -1]) : null;
            $contacto_aluno = isset($row[$header_map['contacto_do_aluno'] ?? -1]) ? trim($row[$header_map['contacto_do_aluno'] ?? -1]) : null;
            $debilidade = isset($row[$header_map['debilidade'] ?? -1]) ? trim($row[$header_map['debilidade'] ?? -1]) : null;
            $idade = isset($row[$header_map['idade'] ?? -1]) ? trim($row[$header_map['idade'] ?? -1]) : null;
            $naturalidade = isset($row[$header_map['naturalidade'] ?? -1]) ? trim($row[$header_map['naturalidade'] ?? -1]) : null;
            $municipio = isset($row[$header_map['município'] ?? -1]) ? trim($row[$header_map['município'] ?? -1]) : null;
            $provincia = isset($row[$header_map['província'] ?? -1]) ? trim($row[$header_map['província'] ?? -1]) : null;
            $n_bi = isset($row[$header_map['n_bi'] ?? -1]) ? trim($row[$header_map['n_bi'] ?? -1]) : null;
            $classe = isset($row[$header_map['classe'] ?? -1]) ? trim($row[$header_map['classe'] ?? -1]) : null;
            $nome_pai = isset($row[$header_map['nome_do_pai'] ?? -1]) ? trim($row[$header_map['nome_do_pai'] ?? -1]) : null;
            $morada3 = isset($row[$header_map['morada3'] ?? -1]) ? trim($row[$header_map['morada3'] ?? -1]) : null;
            $contacto4 = isset($row[$header_map['contacto4'] ?? -1]) ? trim($row[$header_map['contacto4'] ?? -1]) : null;
            $ocupacao = isset($row[$header_map['ocupacao'] ?? -1]) ? trim($row[$header_map['ocupacao'] ?? -1]) : null;
            $local_trabalho = isset($row[$header_map['local_de_trabalho'] ?? -1]) ? trim($row[$header_map['local_de_trabalho'] ?? -1]) : null;
            $nome_mae = isset($row[$header_map['nome_da_mae'] ?? -1]) ? trim($row[$header_map['nome_da_mae'] ?? -1]) : null;
            $contacto_mae = isset($row[$header_map['contacto_mae'] ?? -1]) ? trim($row[$header_map['contacto_mae'] ?? -1]) : null;
            $data_matricula = isset($row[$header_map['data_matricula'] ?? -1]) ? trim($row[$header_map['data_matricula'] ?? -1]) : null;
            $ocupacao_aluno = isset($row[$header_map['ocupacao_do_aluno'] ?? -1]) ? trim($row[$header_map['ocupacao_do_aluno'] ?? -1]) : null;
            $periodo = isset($row[$header_map['periodo'] ?? -1]) ? trim($row[$header_map['periodo'] ?? -1]) : null;
            $data_emissao_bi = isset($row[$header_map['data_emissao_do_bi'] ?? -1]) ? trim($row[$header_map['data_emissao_do_bi'] ?? -1]) : null;
            $arq_identificacao = isset($row[$header_map['arq_identificação'] ?? -1]) ? trim($row[$header_map['arq_identificação'] ?? -1]) : null;
            $situacao_cadastro = isset($row[$header_map['situacao_cadastro'] ?? -1]) ? trim($row[$header_map['situacao_cadastro'] ?? -1]) : null;
            $turma = isset($row[$header_map['turma'] ?? -1]) ? trim($row[$header_map['turma'] ?? -1]) : null;
            $sala = isset($row[$header_map['sala'] ?? -1]) ? trim($row[$header_map['sala'] ?? -1]) : null;
            $curso = isset($row[$header_map['curso'] ?? -1]) ? trim($row[$header_map['curso'] ?? -1]) : null;
            
            $data_matricula_formatada = null;
            if (!empty($data_matricula)) {
                $date = DateTime::createFromFormat('Y-m-d', $data_matricula);
                if ($date) $data_matricula_formatada = $date->format('Y-m-d');
            }
            
            $stmt->execute([
                $nome, $sexo, $dia, $mes, $ano, $morada, $cadastro_transporte, $contacto_aluno,
                $debilidade, $idade, $naturalidade, $municipio, $provincia, $n_bi, $classe,
                $nome_pai, $morada3, $contacto4, $ocupacao, $local_trabalho, $nome_mae,
                $contacto_mae, $data_matricula_formatada, $ocupacao_aluno, $periodo, $data_emissao_bi,
                $arq_identificacao, $situacao_cadastro, $turma, $sala, $curso
            ]);
            
            $inserted++;
        }
        
        $pdo->commit();
        return ['success' => true, 'inserted' => $inserted, 'message' => "Importados $inserted alunos com sucesso!"];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Erro na importação: ' . $e->getMessage()];
    }
}


// ===== FUNÇÃO PARA IMPORTAR PENDÊNCIAS (CORRIGIDA) =====
function importarRelatorioPendenciasCSV($pdo, $file) {
    try {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Se for XLSX, tentar usar PhpSpreadsheet
        if ($ext === 'xlsx' || $ext === 'xls') {
            if (file_exists('../../../../vendor/autoload.php')) {
                require_once '../../../../vendor/autoload.php';
                return importarRelatorioPendenciasPhpSpreadsheet($pdo, $file);
            } else {
                return ['success' => false, 'message' => 'Arquivo .xlsx não suportado. Instale o Composer ou converta para CSV.'];
            }
        }
        
        if ($ext !== 'csv') {
            return ['success' => false, 'message' => 'Formato não suportado. Use CSV ou XLSX.'];
        }
        
        // Verificar se o arquivo existe
        if (!file_exists($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Arquivo temporário não encontrado'];
        }
        
        // Abrir arquivo CSV
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return ['success' => false, 'message' => 'Não foi possível abrir o arquivo'];
        }
        
        // Ler o arquivo linha por linha
        $rows = [];
        $row_number = 0;
        while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
            $row_number++;
            if (empty(array_filter($row))) continue;
            $rows[] = $row;
        }
        fclose($handle);
        
        if (empty($rows)) {
            return ['success' => false, 'message' => 'Arquivo vazio ou inválido'];
        }
        
        // Mapear cabeçalhos - USANDO OS CABEÇALHOS CORRETOS DO SEU ARQUIVO
        $headers = array_map('trim', $rows[0]);
        
        // Debug: verificar cabeçalhos
        error_log("Cabeçalhos encontrados: " . print_r($headers, true));
        
        $header_map = [];
        foreach ($headers as $index => $header) {
            // Remover acentos e caracteres especiais para comparação
            $header_clean = removerAcentos(trim($header));
            $header_clean = strtolower($header_clean);
            $header_clean = preg_replace('/[^a-z0-9_]/', '', $header_clean);
            $header_map[$header_clean] = $index;
        }
        
        // Debug: verificar mapa de cabeçalhos
        error_log("Mapa de cabeçalhos: " . print_r($header_map, true));
        
        // Mapear cabeçalhos específicos do seu arquivo
        $mapa_cabecalhos = [
            // Nome do aluno
            'nomedoaluno' => $header_map['nomedoaluno'] ?? $header_map['nome'] ?? null,
            // Classe
            'classe' => $header_map['classe'] ?? null,
            // Turma
            'turma' => $header_map['turma'] ?? null,
            // Propina
            'propina' => $header_map['propina'] ?? null,
            // Quantidade de propina
            'quantidade' => $header_map['quantidade'] ?? null,
            // Transporte
            'transporte' => $header_map['transporte'] ?? null,
            // Quantidade de transporte
            'quantidade2' => $header_map['quantidade2'] ?? null,
            // Folha de prova 1º trimestre
            'folhadeprova1t' => $header_map['folhadeprova1trimestre'] ?? $header_map['folhadeprova1t'] ?? null,
            // Folha de prova 2º trimestre
            'folhadeprova2t' => $header_map['folhadeprova2trimestre'] ?? $header_map['folhadeprova2t'] ?? null,
            // Folha de prova 3º trimestre
            'folhadeprova3t' => $header_map['folhadeprova3trimestre'] ?? $header_map['folhadeprova3t'] ?? null,
            // Boletim 1º trimestre
            'boletim1t' => $header_map['boletim1t'] ?? $header_map['boletimnotas1trimestre'] ?? null,
            // Boletim 2º trimestre
            'boletim2t' => $header_map['boletim2t'] ?? $header_map['boletimnotas2trimestre'] ?? null,
            // Cartão
            'cartao' => $header_map['cartao'] ?? $header_map['carto'] ?? null,
        ];
        
        // Debug: verificar mapa
        error_log("Mapa de cabeçalhos mapeados: " . print_r($mapa_cabecalhos, true));
        
        // Verificar se o cabeçalho 'nome' existe
        if ($mapa_cabecalhos['nomedoaluno'] === null && $mapa_cabecalhos['classe'] === null) {
            return ['success' => false, 'message' => 'Cabeçalhos não reconhecidos. Verifique se o arquivo tem as colunas: Nome do aluno, Classe, Turma, Propina, Quantidade, Transporte, Quantidade2, Folha de prova 1º trimestre, Folha de prova 2º trimestre, Folha de prova 3º trimestre'];
        }
        
        // Iniciar transação APENAS se não estiver ativa
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
        $inserted = 0;
        
        // Criar tabela se não existir
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pendencias_anterior` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `nome_aluno` VARCHAR(255) DEFAULT NULL,
                `classe` VARCHAR(20) DEFAULT NULL,
                `turma` VARCHAR(50) DEFAULT NULL,
                `propina_status` VARCHAR(20) DEFAULT NULL,
                `propina_quantidade` INT(11) DEFAULT 0,
                `transporte_status` VARCHAR(20) DEFAULT NULL,
                `transporte_quantidade` INT(11) DEFAULT 0,
                `folha_prova_1_status` VARCHAR(20) DEFAULT NULL,
                `folha_prova_2_status` VARCHAR(20) DEFAULT NULL,
                `folha_prova_3_status` VARCHAR(20) DEFAULT NULL,
                `boletim_1_status` VARCHAR(20) DEFAULT NULL,
                `boletim_2_status` VARCHAR(20) DEFAULT NULL,
                `cartao_status` VARCHAR(20) DEFAULT NULL,
                `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // ALTERAR A TABELA PARA ADICIONAR AS NOVAS COLUNAS SE NÃO EXISTIREM
        try {
            $pdo->exec("ALTER TABLE `pendencias_anterior` ADD COLUMN `boletim_1_status` VARCHAR(20) DEFAULT NULL");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE `pendencias_anterior` ADD COLUMN `boletim_2_status` VARCHAR(20) DEFAULT NULL");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE `pendencias_anterior` ADD COLUMN `cartao_status` VARCHAR(20) DEFAULT NULL");
        } catch (Exception $e) {}
        
        $stmt = $pdo->prepare("
            INSERT INTO pendencias_anterior 
            (nome_aluno, classe, turma, propina_status, propina_quantidade, 
             transporte_status, transporte_quantidade, folha_prova_1_status, 
             folha_prova_2_status, folha_prova_3_status,
             boletim_1_status, boletim_2_status, cartao_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Processar linhas (começando da linha 1, ignorando o cabeçalho)
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) continue;
            
            // Tentar encontrar o nome do aluno
            $nome = null;
            if ($mapa_cabecalhos['nomedoaluno'] !== null && isset($row[$mapa_cabecalhos['nomedoaluno']])) {
                $nome = trim($row[$mapa_cabecalhos['nomedoaluno']]);
            }
            
            if (empty($nome)) continue;
            
            // Classe
            $classe = null;
            if ($mapa_cabecalhos['classe'] !== null && isset($row[$mapa_cabecalhos['classe']])) {
                $classe = trim($row[$mapa_cabecalhos['classe']]);
            }
            
            // Turma
            $turma = null;
            if ($mapa_cabecalhos['turma'] !== null && isset($row[$mapa_cabecalhos['turma']])) {
                $turma = trim($row[$mapa_cabecalhos['turma']]);
            }
            
            // Propina
            $propina_status = null;
            if ($mapa_cabecalhos['propina'] !== null && isset($row[$mapa_cabecalhos['propina']])) {
                $propina_status = trim($row[$mapa_cabecalhos['propina']]);
            }
            
            // Quantidade de propina
            $propina_qtd = 0;
            if ($mapa_cabecalhos['quantidade'] !== null && isset($row[$mapa_cabecalhos['quantidade']])) {
                $propina_qtd = intval(trim($row[$mapa_cabecalhos['quantidade']]));
            }
            
            // Transporte
            $transp_status = null;
            if ($mapa_cabecalhos['transporte'] !== null && isset($row[$mapa_cabecalhos['transporte']])) {
                $transp_status = trim($row[$mapa_cabecalhos['transporte']]);
            }
            
            // Quantidade de transporte
            $transp_qtd = 0;
            if ($mapa_cabecalhos['quantidade2'] !== null && isset($row[$mapa_cabecalhos['quantidade2']])) {
                $transp_qtd = intval(trim($row[$mapa_cabecalhos['quantidade2']]));
            }
            
            // Folhas de prova
            $folha1 = null;
            if ($mapa_cabecalhos['folhadeprova1t'] !== null && isset($row[$mapa_cabecalhos['folhadeprova1t']])) {
                $folha1 = trim($row[$mapa_cabecalhos['folhadeprova1t']]);
            }
            
            $folha2 = null;
            if ($mapa_cabecalhos['folhadeprova2t'] !== null && isset($row[$mapa_cabecalhos['folhadeprova2t']])) {
                $folha2 = trim($row[$mapa_cabecalhos['folhadeprova2t']]);
            }
            
            $folha3 = null;
            if ($mapa_cabecalhos['folhadeprova3t'] !== null && isset($row[$mapa_cabecalhos['folhadeprova3t']])) {
                $folha3 = trim($row[$mapa_cabecalhos['folhadeprova3t']]);
            }
            
            // Boletins
            $boletim1 = null;
            if ($mapa_cabecalhos['boletim1t'] !== null && isset($row[$mapa_cabecalhos['boletim1t']])) {
                $boletim1 = trim($row[$mapa_cabecalhos['boletim1t']]);
            }
            
            $boletim2 = null;
            if ($mapa_cabecalhos['boletim2t'] !== null && isset($row[$mapa_cabecalhos['boletim2t']])) {
                $boletim2 = trim($row[$mapa_cabecalhos['boletim2t']]);
            }
            
            // Cartão
            $cartao = null;
            if ($mapa_cabecalhos['cartao'] !== null && isset($row[$mapa_cabecalhos['cartao']])) {
                $cartao = trim($row[$mapa_cabecalhos['cartao']]);
            }
            
            // Executar INSERT
            try {
                $stmt->execute([
                    $nome, $classe, $turma,
                    $propina_status, $propina_qtd,
                    $transp_status, $transp_qtd,
                    $folha1, $folha2, $folha3,
                    $boletim1, $boletim2, $cartao
                ]);
                $inserted++;
            } catch (PDOException $e) {
                error_log("Erro ao inserir linha $i: " . $e->getMessage());
                error_log("Dados: " . print_r([
                    $nome, $classe, $turma,
                    $propina_status, $propina_qtd,
                    $transp_status, $transp_qtd,
                    $folha1, $folha2, $folha3,
                    $boletim1, $boletim2, $cartao
                ], true));
                // Continua com a próxima linha
            }
        }
        
        // Commit da transação
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        
        return ['success' => true, 'inserted' => $inserted, 'message' => "Importadas $inserted pendências com sucesso!"];
        
    } catch (Exception $e) {
        // Rollback apenas se houver transação ativa
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Erro na importação: ' . $e->getMessage()];
    }
}



// ===== PROCESSAR IMPORTAÇÃO =====
$import_result = null;
if (isset($_POST['importar_alunos']) && isset($_FILES['arquivo_importacao'])) {
    $import_result = importarPlanilhaAlunosCSV($pdo, $_FILES['arquivo_importacao']);
}

if (isset($_POST['importar_pendencias']) && isset($_FILES['arquivo_pendencias'])) {
    $import_result = importarRelatorioPendenciasCSV($pdo, $_FILES['arquivo_pendencias']);
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
$pagamento_duplicado = false;

// ===== ITENS DA FATURA (SESSÃO) =====
if (!isset($_SESSION['fatura_itens'])) {
    $_SESSION['fatura_itens'] = [];
}
$itens_fatura = $_SESSION['fatura_itens'];

// ===== PROCESSAR BUSCA =====
if (isset($_GET['busca']) && !empty(trim($_GET['busca']))) {
    $aluno_busca = trim($_GET['busca']);
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, genero, turma, classe, curso, status
            FROM alunos WHERE status = 'ativo' 
            AND (id = ? OR id LIKE ? OR nome LIKE ? OR nome LIKE ?)
            ORDER BY nome LIMIT 20
        ");
        $busca = "%{$aluno_busca}%";
        $stmt->execute([$aluno_busca, $busca, $busca, "{$aluno_busca}%"]);
        $alunos_busca = $stmt->fetchAll();
        if (count($alunos_busca) == 1) $aluno_selecionado = $alunos_busca[0];
    } catch (Exception $e) {
        $erro = 'Erro na busca: ' . $e->getMessage();
    }
}

try {
    if (empty($aluno_busca)) {
        $alunos = $pdo->query("SELECT id, nome, genero, turma, classe, curso FROM alunos WHERE status='ativo' ORDER BY nome LIMIT 100")->fetchAll();
    } else {
        $alunos = $alunos_busca;
    }
    $emolumentos = buscarEmolumentosAgrupados($pdo);
} catch (Exception $e) {
    $erro = 'Erro ao carregar dados: ' . $e->getMessage();
}

// ===== ADICIONAR ITEM =====
if (isset($_POST['add_item']) && isset($_POST['aluno_id'])) {
    $aluno_id = $_POST['aluno_id'] ?? null;
    $emolumento_id = $_POST['emolumento_id'] ?? null;
    $valor_base = floatval($_POST['valor_base'] ?? 0);
    $desconto = floatval($_POST['desconto'] ?? 0);
    $mes_nome = $_POST['mes_referencia'] ?? '-';
    $ano = intval($_POST['ano_referencia'] ?? date('Y'));
    $mes_numero = mesNomeParaNumero($mes_nome);
    
    if (!$aluno_id || !$emolumento_id || $valor_base <= 0) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        $mes_referencia_formatado = $mes_nome != '-' ? $mes_nome . '/' . $ano : '-';
        $verificacao = verificarDuplicadoDetalhado($pdo, $aluno_id, $emolumento_id, $mes_referencia_formatado);
        
        if ($verificacao['duplicado']) {
            $detalhes = $verificacao['detalhes'] ?? [];
            $erro = '⚠️ <strong>PAGAMENTO DUPLICADO!</strong><br>';
            $erro .= 'Já existe um pagamento para este mês referente a <strong>' . htmlspecialchars($detalhes['emolumento'] ?? 'Desconhecido') . '</strong>';
            $erro .= ' no valor de ' . number_format($detalhes['valor'] ?? 0, 2, ',', '.') . ' Kz.';
            $pagamento_duplicado = true;
        } else {
            $stmt_emol = $pdo->prepare("SELECT nome, valor FROM emolumentos WHERE id = ?");
            $stmt_emol->execute([$emolumento_id]);
            $emol = $stmt_emol->fetch();
            $nome_emolumento = $emol['nome'] ?? '';
            
            $multa = 0;
            if ($mes_numero > 0) {
                $multa = calcularMulta($pdo, $emolumento_id, $mes_numero, $ano, $valor_base);
            }
            
            $valor_liquido = $valor_base + $multa - $desconto;
            if ($valor_liquido < 0) $valor_liquido = 0;
            
            $item = [
                'emolumento_id' => $emolumento_id,
                'emolumento' => $nome_emolumento,
                'valor_base' => $valor_base,
                'multa' => $multa,
                'desconto' => $desconto,
                'valor_liquido' => $valor_liquido,
                'mes_referencia' => $mes_nome,
                'ano_referencia' => $ano,
                'mes_numero' => $mes_numero
            ];
            
            $_SESSION['fatura_itens'][] = $item;
            $itens_fatura = $_SESSION['fatura_itens'];
            $sucesso = '✅ Item adicionado à fatura!';
        }
    }
}

// ===== REMOVER ITEM =====
if (isset($_GET['remove_item'])) {
    $index = intval($_GET['remove_item']);
    if (isset($_SESSION['fatura_itens'][$index])) {
        unset($_SESSION['fatura_itens'][$index]);
        $_SESSION['fatura_itens'] = array_values($_SESSION['fatura_itens']);
        $itens_fatura = $_SESSION['fatura_itens'];
        $sucesso = '🗑️ Item removido da fatura!';
    }
}

// ===== LIMPAR ITENS =====
if (isset($_GET['clear_items'])) {
    $_SESSION['fatura_itens'] = [];
    $itens_fatura = [];
    $sucesso = '🧹 Fatura limpa!';
}

// ===== PROCESSAR FORMULÁRIO (FINALIZAR) =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_pagamento'])) {
    $aluno_id = $_POST['aluno_id'] ?? null;
    $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    $forma_pagamento = $_POST['forma_pagamento'] ?? 'dinheiro';
    $referencia = $_POST['referencia'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    
    if (empty($itens_fatura)) {
        $erro = 'Adicione pelo menos um item à fatura!';
    } elseif (!$aluno_id) {
        $erro = 'Selecione um aluno!';
    } else {
        try {
            $stmt_aluno = $pdo->prepare("SELECT nome, genero, classe, turma FROM alunos WHERE id = ?");
            $stmt_aluno->execute([$aluno_id]);
            $aluno = $stmt_aluno->fetch();
            
            $total_pago = 0;
            $itens_registrados = [];
            $itens_duplicados = [];
            
            foreach ($itens_fatura as $item) {
                $mes_referencia_formatado = ($item['mes_referencia'] ?? '-') != '-' ? ($item['mes_referencia'] ?? '-') . '/' . ($item['ano_referencia'] ?? date('Y')) : '-';
                
                $verificacao = verificarDuplicadoDetalhado($pdo, $aluno_id, $item['emolumento_id'], $mes_referencia_formatado);
                
                if ($verificacao['duplicado']) {
                    $itens_duplicados[] = [
                        'emolumento' => $item['emolumento'],
                        'mes' => $mes_referencia_formatado,
                        'detalhes' => $verificacao['detalhes']
                    ];
                    continue;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO pagamentos 
                    (aluno_id, emolumento_id, valor, data_pagamento, forma_pagamento, 
                     referencia, observacoes, status, mes_referencia) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmado', ?)
                ");
                $stmt->execute([
                    $aluno_id, $item['emolumento_id'], $item['valor_liquido'], $data_pagamento, 
                    $forma_pagamento, $referencia, $observacoes, $mes_referencia_formatado
                ]);
                $pagamento_id = $pdo->lastInsertId();
                $total_pago += $item['valor_liquido'];
                $itens_registrados[] = $item;
                
                if ($item['mes_numero'] > 0) {
                    atualizarMensalidade($pdo, $aluno_id, $item['mes_numero'], $item['ano_referencia'], $item['valor_liquido'], $item['emolumento_id']);
                }
            }
            
            if (empty($itens_registrados) && !empty($itens_duplicados)) {
                $erro = '⚠️ <strong>TODOS OS ITENS SÃO DUPLICADOS!</strong><br>';
                foreach ($itens_duplicados as $dup) {
                    $erro .= '• ' . htmlspecialchars($dup['emolumento']) . ' - Mês: ' . htmlspecialchars($dup['mes']) . ' (Já pago)<br>';
                }
            } elseif (!empty($itens_registrados)) {
                $numero_fatura = 'FAT-' . date('Ymd') . '-' . str_pad($pagamento_id ?? 1, 6, '0', STR_PAD_LEFT);
                
                $nome_escola = 'Sistema Escolar';
                $endereco = $contacto = $email = $nif = '';
                $config_file = '../../../../config_escola.json';
                if (file_exists($config_file)) {
                    $config = json_decode(file_get_contents($config_file), true);
                    $nome_escola = $config['nome'] ?? 'Sistema Escolar';
                    $endereco = $config['endereco'] ?? '';
                    $contacto = $config['contacto'] ?? '';
                    $email = $config['email'] ?? '';
                    $nif = $config['nif'] ?? '';
                }
                
                $dados_fatura = [
                    'nome_escola' => $nome_escola, 'endereco' => $endereco,
                    'contacto' => $contacto, 'email' => $email, 'nif' => $nif,
                    'aluno_id' => $aluno_id, 'aluno_nome' => $aluno['nome'] ?? '',
                    'aluno_classe' => $aluno['classe'] ?? '', 'aluno_turma' => $aluno['turma'] ?? '',
                    'aluno_genero' => $aluno['genero'] ?? '',
                    'itens' => $itens_registrados,
                    'numero_fatura' => $numero_fatura,
                    'data_pagamento' => $data_pagamento,
                    'forma_pagamento' => $forma_pagamento,
                    'referencia' => $referencia,
                    'observacoes' => $observacoes
                ];
                
                $fatura_html = gerarFaturaAGT($dados_fatura);
                salvarFatura($fatura_html, $numero_fatura);
                
                $mostrar_fatura = true;
                $sucesso = '✅ Pagamento registrado! ' . count($itens_registrados) . ' item(ns) processado(s). Total: ' . number_format($total_pago, 2, ',', '.') . ' Kz';
                
                if (!empty($itens_duplicados)) {
                    $sucesso .= '<br>⚠️ ' . count($itens_duplicados) . ' item(ns) ignorado(s) por duplicidade.';
                }
                
                $_SESSION['fatura_itens'] = [];
                $itens_fatura = [];
            }
        } catch (Exception $e) {
            $erro = 'Erro: ' . $e->getMessage();
        }
    }
}

include '../../includes/header_escola.php';
?>

<!-- ===== TODO O HTML E CSS (MANTIDOS IGUAIS) ===== -->
<style>
/* ... (mesmo CSS anterior) ... */
.page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
.page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
.page-header .subtitle{color:#94a3b8;font-size:14px;margin:2px 0 0}
.btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
.btn-primary{background:#c9a84c;color:#1a2332}
.btn-primary:hover{background:#b8973a;transform:translateY(-2px)}
.btn-secondary{background:#f1f5f9;color:#4a5568}
.btn-secondary:hover{background:#e2e8f0}
.btn-success{background:#2ecc71;color:#fff}
.btn-success:hover{background:#27ae60}
.btn-danger{background:#e74c3c;color:#fff}
.btn-danger:hover{background:#c0392b}
.btn-info{background:#3498db;color:#fff}
.btn-info:hover{background:#2980b9}
.menu-financeiro{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:25px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
.menu-financeiro a{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;color:#4a5568;background:#f8fafc;border:1px solid #e2e8f0;display:inline-flex;align-items:center;gap:6px}
.menu-financeiro a:hover,.menu-financeiro a.active{background:#c9a84c;color:#1a2332;border-color:#c9a84c}
.form-container{background:#fff;border-radius:12px;padding:30px;border:1px solid #eef2f7;max-width:900px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-weight:600;margin-bottom:5px;color:#1a2332;font-size:13px}
.form-group label .required{color:#e74c3c}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;transition:border-color .3s;font-family:inherit}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,0.1)}
.form-group textarea{min-height:60px;resize:vertical}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.alert-warning{background:#fef9e8;color:#78350f;border:1px solid #fde68a}
.form-actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
.info-valor{background:#f8fafc;padding:12px 16px;border-radius:8px;border:1px solid #e2e8f0;margin-top:5px;font-size:13px;color:#4a5568}
.info-valor strong{color:#1a2332}
.busca-aluno-container{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:15px;margin-bottom:15px}
.busca-aluno-container .busca-row{display:flex;gap:10px}
.busca-aluno-container .busca-row input{flex:1;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px}
.busca-aluno-container .busca-row input:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,0.1)}
.busca-aluno-container .busca-row .btn{padding:10px 20px;white-space:nowrap}
.resultado-busca{margin-top:12px;border-top:1px solid #e2e8f0;padding-top:12px;max-height:400px;overflow-y:auto}
.resultado-busca .aluno-item{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:#fff;border-radius:8px;margin-bottom:8px;border:2px solid #e2e8f0;cursor:pointer;transition:all .2s}
.resultado-busca .aluno-item:hover{border-color:#c9a84c;background:#fef9e8;transform:translateX(5px)}
.resultado-busca .aluno-item .info{display:flex;flex-direction:column;gap:4px;flex:1}
.resultado-busca .aluno-item .info .nome{font-weight:600;color:#1a2332;font-size:15px}
.resultado-busca .aluno-item .info .detalhes{font-size:12px;color:#94a3b8;display:flex;flex-wrap:wrap;gap:10px}
.resultado-busca .aluno-item .info .detalhes span{background:#f1f5f9;padding:2px 8px;border-radius:4px}
.resultado-busca .aluno-item .selecionar-btn{padding:6px 16px;border-radius:6px;background:#c9a84c;color:#1a2332;border:none;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;margin-left:10px}
.resultado-busca .aluno-item .selecionar-btn:hover{background:#b8973a;transform:scale(1.05)}
.resultado-busca .aluno-item.aluno-selecionado{background:#d1fae5 !important;border-color:#2ecc71 !important}
.resultado-busca .aluno-item.aluno-selecionado .selecionar-btn{background:#2ecc71 !important;color:#fff !important}
.nenhum-resultado{text-align:center;padding:30px;color:#94a3b8;font-size:14px}
.nenhum-resultado .icone{font-size:48px;margin-bottom:10px}
.aluno-selecionado-feedback{margin-top:8px;padding:12px 16px;background:#d1fae5;border-radius:6px;font-size:13px;color:#065f46;border-left:4px solid #2ecc71;display:flex;justify-content:space-between;align-items:center;animation:slideDown .3s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.aluno-selecionado-feedback .remover-btn{background:#e74c3c;color:#fff;border:none;padding:4px 12px;border-radius:4px;cursor:pointer;font-size:12px}
.badge-status{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600}
.badge-ativo{background:#d1fae5;color:#065f46}
.fatura-modal{display:<?= $mostrar_fatura ? 'block' : 'none' ?>;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;overflow-y:auto;padding:20px}
.fatura-modal-content{max-width:210mm;margin:20px auto;background:#fff;border-radius:8px;padding:0;position:relative;box-shadow:0 10px 40px rgba(0,0,0,0.3)}
.fatura-modal .btn-fechar{position:sticky;top:0;float:right;background:#e74c3c;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;margin:10px;z-index:10}
.fatura-modal .btn-fechar:hover{background:#c0392b}
.fatura-iframe{width:100%;height:900px;border:none;border-radius:0 0 8px 8px}
.mes-hint{font-size:12px;color:#94a3b8;margin-top:4px}
.mes-hint.ativo{color:#27ae60;font-weight:500}
.ano-input{width:100px !important}
.multa-info{background:#fef9e8;border:1px solid #fde68a;padding:8px 12px;border-radius:6px;margin-top:5px;font-size:13px;color:#78350f;display:none}
.multa-info.ativo{display:block}
.tabela-itens-fatura{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px}
.tabela-itens-fatura th{background:#f1f5f9;padding:8px;text-align:left;border-bottom:2px solid #e2e8f0}
.tabela-itens-fatura td{padding:8px;border-bottom:1px solid #e2e8f0}
.tabela-itens-fatura .text-right{text-align:right}
.tabela-itens-fatura .total-row{font-weight:bold;background:#f8fafc}
.tabela-itens-fatura .total-row td{border-top:2px solid #1a2332}
.btn-remove-item{background:#fee2e2;color:#991b1b;border:none;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:12px}
.btn-remove-item:hover{background:#fecaca}
.btn-add-item{background:#d1fae5;color:#065f46;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:600}
.btn-add-item:hover{background:#a7f3d0}
.resumo-fatura{background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;margin-top:10px}
.resumo-fatura .total{font-size:18px;font-weight:bold;color:#c0392b}
.duplicado-warning{background:#fee2e2;border:1px solid #fecaca;padding:12px 16px;border-radius:8px;color:#991b1b;font-size:14px;margin-bottom:15px}
.duplicado-warning strong{color:#991b1b}

.import-section{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:15px;margin-top:20px}
.import-section .import-header{display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none}
.import-section .import-header h3{margin:0;font-size:15px;color:#1a2332}
.import-section .import-header .toggle-icon{font-size:18px;transition:transform .3s}
.import-section .import-body{display:none;padding-top:15px;border-top:1px solid #e2e8f0;margin-top:15px}
.import-section.active .import-body{display:block}
.import-section.active .toggle-icon{transform:rotate(180deg)}
.import-section .file-input-wrapper{display:flex;gap:10px;flex-wrap:wrap}
.import-section .file-input-wrapper input[type="file"]{flex:1;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}
.import-section .file-input-wrapper input[type="file"]:hover{cursor:pointer}
.import-section .file-input-wrapper .btn{white-space:nowrap}
.import-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:15px}
.import-card{background:#fff;padding:15px;border-radius:8px;border:1px solid #e2e8f0}
.import-card h4{margin:0 0 10px;font-size:14px;color:#1a2332}
.import-actions{display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid #e2e8f0;padding-top:15px;margin-top:5px}

@media(max-width:768px){
.page-header{flex-direction:column;align-items:stretch}
.menu-financeiro{flex-direction:column;align-items:stretch}
.menu-financeiro a{text-align:center;justify-content:center}
.form-row,.form-row-3{grid-template-columns:1fr;gap:0}
.form-container{padding:20px}
.form-actions{flex-direction:column}
.form-actions .btn{justify-content:center}
.busca-aluno-container .busca-row{flex-direction:column}
.resultado-busca .aluno-item{flex-wrap:wrap}
.resultado-busca .aluno-item .selecionar-btn{margin-left:0;margin-top:8px;width:100%}
.aluno-selecionado-feedback{flex-direction:column;gap:8px;text-align:center}
.fatura-modal-content{margin:10px}
.fatura-iframe{height:500px}
.ano-input{width:100% !important}
.tabela-itens-fatura{font-size:11px}
.tabela-itens-fatura th,.tabela-itens-fatura td{padding:4px}
.import-section .file-input-wrapper{flex-direction:column}
.import-section .file-input-wrapper .btn{width:100%;justify-content:center}
.import-grid{grid-template-columns:1fr}
}
</style>

<div class="page-header">
    <div><h1>💳 Novo Pagamento</h1><p class="subtitle">Registrar pagamento com múltiplos itens</p></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-info" onclick="toggleImportacao()">📥 Importar Dados</button>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="index.php" class="active">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<!-- SEÇÃO DE IMPORTAÇÃO -->
<div class="import-section" id="importSection">
    <div class="import-header" onclick="toggleImportacao()">
        <h3>📥 Importar Dados da Planilha</h3>
        <span class="toggle-icon">▼</span>
    </div>
    <div class="import-body">
        <?php if ($import_result): ?>
            <div class="alert alert-<?= $import_result['success'] ? 'success' : 'error' ?>">
                <?= $import_result['message'] ?>
            </div>
        <?php endif; ?>
        
        <div class="import-grid">
            <div class="import-card">
                <h4>📚 Alunos Ano Anterior</h4>
                <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;">
                    <div class="file-input-wrapper">
                        <input type="file" name="arquivo_importacao" accept=".csv,.xlsx,.xls" required>
                    </div>
                    <button type="submit" name="importar_alunos" class="btn btn-primary" style="align-self:flex-start;">⬆️ Importar Alunos</button>
                </form>
                <p style="font-size:11px;color:#94a3b8;margin-top:8px;">Formatos: CSV ou XLSX (com PhpSpreadsheet)</p>
            </div>
            
            <div class="import-card">
                <h4>📋 Relatório de Pendências</h4>
                <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;">
                    <div class="file-input-wrapper">
                        <input type="file" name="arquivo_pendencias" accept=".csv,.xlsx,.xls" required>
                    </div>
                    <button type="submit" name="importar_pendencias" class="btn btn-primary" style="align-self:flex-start;">⬆️ Importar Pendências</button>
                </form>
                <p style="font-size:11px;color:#94a3b8;margin-top:8px;">Formatos: CSV ou XLSX (com PhpSpreadsheet)</p>
            </div>
        </div>
        
        <div class="import-actions">
            <a href="importados_alunos.php" class="btn btn-secondary" target="_blank">👁️ Ver Alunos Importados</a>
            <a href="importados_pendencias.php" class="btn btn-secondary" target="_blank">👁️ Ver Pendências Importadas</a>
        </div>
    </div>
</div>

<?php if ($sucesso && !$mostrar_fatura): ?>
<div class="alert alert-success"><?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert-<?= $pagamento_duplicado ? 'warning' : 'error' ?>"><?= $erro ?></div>
<?php endif; ?>

<!-- ===== RESTO DO HTML (MANTIDO IGUAL) ===== -->
<div class="form-container">
    <form method="GET" action="" id="buscaForm">
        <div class="busca-aluno-container">
            <div class="busca-row">
                <input type="text" id="busca_aluno" name="busca" placeholder="🔍 Buscar por ID ou Nome..." value="<?= htmlspecialchars($aluno_busca) ?>" autocomplete="off">
                <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                <?php if (!empty($aluno_busca)): ?>
                <a href="add.php" class="btn btn-secondary">✕ Limpar</a>
                <?php endif; ?>
            </div>
            <?php if (!empty($aluno_busca) && !empty($alunos_busca)): ?>
            <div class="resultado-busca">
                <div style="font-size:12px;color:#94a3b8;margin-bottom:10px;display:flex;justify-content:space-between">
                    <span>📋 Encontrados <?= count($alunos_busca) ?> aluno(s)</span>
                    <span style="font-size:11px;">Clique em "Selecionar"</span>
                </div>
                <?php foreach($alunos_busca as $aluno): ?>
                <div class="aluno-item <?= ($aluno_selecionado && $aluno_selecionado['id'] == $aluno['id']) ? 'aluno-selecionado' : '' ?>" 
                     data-id="<?= $aluno['id'] ?>" data-nome="<?= htmlspecialchars($aluno['nome']) ?>" 
                     data-genero="<?= htmlspecialchars($aluno['genero'] ?? '') ?>" 
                     data-classe="<?= htmlspecialchars($aluno['classe'] ?? '') ?>" 
                     data-curso="<?= htmlspecialchars($aluno['curso'] ?? '') ?>" 
                     data-turma="<?= htmlspecialchars($aluno['turma'] ?? '') ?>">
                    <div class="info">
                        <span class="nome">#<?= $aluno['id'] ?> - <?= htmlspecialchars($aluno['nome']) ?>
                            <?php if (!empty($aluno['status'])): ?>
                            <span class="badge-status badge-<?= $aluno['status'] == 'ativo' ? 'ativo' : 'inativo' ?>"><?= ucfirst($aluno['status']) ?></span>
                            <?php endif; ?>
                        </span>
                        <div class="detalhes">
                            <?php if (!empty($aluno['genero'])): ?><span>👤 <?= $aluno['genero'] == 'M' ? 'Masculino' : 'Feminino' ?></span><?php endif; ?>
                            <?php if (!empty($aluno['classe']) || !empty($aluno['curso'])): ?><span>🏫 <?= htmlspecialchars($aluno['classe'] ?? '') ?> <?= htmlspecialchars($aluno['curso'] ?? '') ?></span><?php endif; ?>
                            <?php if (!empty($aluno['turma'])): ?><span>📖 Turma: <?= htmlspecialchars($aluno['turma']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="selecionar-btn" onclick="selecionarAluno(this)">✅ Selecionar</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif (!empty($aluno_busca)): ?>
            <div class="resultado-busca">
                <div class="nenhum-resultado"><div class="icone">😕</div><div>Nenhum aluno encontrado com "<strong><?= htmlspecialchars($aluno_busca) ?></strong>"</div></div>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <form method="POST" action="" id="itemForm">
        <input type="hidden" name="add_item" value="1">
        <input type="hidden" name="aluno_id" id="aluno_id_hidden" value="<?= $_POST['aluno_id'] ?? $aluno_selecionado['id'] ?? '' ?>">
        
        <div class="form-group">
            <label>Aluno <span class="required">*</span></label>
            <select name="aluno_id" id="aluno_id" required>
                <option value="">Selecione um aluno</option>
                <?php foreach($alunos as $aluno): ?>
                <option value="<?= $aluno['id'] ?>" <?= ($_POST['aluno_id'] ?? $aluno_selecionado['id'] ?? '') == $aluno['id'] ? 'selected' : '' ?>>
                    #<?= $aluno['id'] ?> - <?= htmlspecialchars($aluno['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($aluno_selecionado): ?>
            <div class="aluno-selecionado-feedback" id="alunoFeedback">
                <span>✅ <strong>#<?= $aluno_selecionado['id'] ?></strong> - <?= htmlspecialchars($aluno_selecionado['nome']) ?>
                <?php if (!empty($aluno_selecionado['genero'])): ?> - <?= $aluno_selecionado['genero'] == 'M' ? '👨 Masculino' : '👩 Feminino' ?><?php endif; ?></span>
                <button type="button" class="remover-btn" onclick="removerSelecao()">✕ Remover</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Emolumento <span class="required">*</span></label>
                <select name="emolumento_id" id="emolumento_id" required onchange="atualizarValorItem()">
                    <option value="">Selecione</option>
                    <?php 
                    $emolumentos_exibidos = [];
                    foreach($emolumentos as $emol): 
                        $nome_sem_acento = removerAcentos(strtolower(trim($emol['nome'])));
                        if (!in_array($nome_sem_acento, $emolumentos_exibidos)):
                            $emolumentos_exibidos[] = $nome_sem_acento;
                    ?>
                    <option value="<?= $emol['id'] ?>" data-valor="<?= $emol['valor'] ?>" data-nome="<?= htmlspecialchars($emol['nome']) ?>">
                        <?= htmlspecialchars($emol['nome']) ?> - R$ <?= number_format($emol['valor'], 2, ',', '.') ?>
                    </option>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Valor Base <span class="required">*</span></label>
                <input type="number" step="0.01" name="valor_base" id="valor_base_item" value="" required onchange="calcularTotalItem()" oninput="calcularTotalItem()">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Mês de Referência</label>
                <select name="mes_referencia" id="mes_referencia_item" disabled>
                    <option value="-">-</option>
                    <option value="Janeiro">Janeiro</option><option value="Fevereiro">Fevereiro</option>
                    <option value="Março">Março</option><option value="Abril">Abril</option>
                    <option value="Maio">Maio</option><option value="Junho">Junho</option>
                    <option value="Julho">Julho</option><option value="Agosto">Agosto</option>
                    <option value="Setembro">Setembro</option><option value="Outubro">Outubro</option>
                    <option value="Novembro">Novembro</option><option value="Dezembro">Dezembro</option>
                </select>
                <div class="mes-hint" id="mesHintItem">💡 Selecione o mês para Propina, Transporte ou Mensalidade</div>
            </div>
            <div class="form-group">
                <label>Ano</label>
                <input type="number" class="ano-input" name="ano_referencia" id="ano_referencia_item" value="<?= date('Y') ?>" min="2000" max="2100">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Multa (calculada)</label>
                <input type="text" id="multa_item" readonly style="background:#f8fafc;font-weight:bold;color:#e67e22;font-size:15px;">
                <div class="multa-info" id="multaInfoItem"></div>
            </div>
            <div class="form-group">
                <label>Desconto (Kz)</label>
                <input type="number" step="0.01" name="desconto" id="desconto_item" value="0" min="0" onchange="calcularTotalItem()" oninput="calcularTotalItem()">
            </div>
        </div>

        <div class="form-row-3">
            <div class="form-group">
                <label>Desconto (%)</label>
                <input type="number" step="0.1" id="desconto_percent_item" value="0" min="0" max="100" onchange="aplicarDescontoPercentualItem()" oninput="aplicarDescontoPercentualItem()">
            </div>
            <div class="form-group">
                <label>Valor Líquido</label>
                <input type="text" id="valor_liquido_item" readonly style="background:#f8fafc;font-weight:bold;color:#1a2332;font-size:16px;">
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;">
                <button type="submit" class="btn btn-success btn-add-item" style="width:100%;">➕ Adicionar Item</button>
            </div>
        </div>
    </form>

    <?php if (!empty($itens_fatura)): ?>
    <div style="margin-top:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h3 style="color:#1a2332;margin:0;">📋 Itens da Fatura (<?= count($itens_fatura) ?>)</h3>
            <div>
                <a href="?clear_items=1" class="btn btn-danger" onclick="return confirm('Limpar todos os itens?')">🗑️ Limpar</a>
            </div>
        </div>
        
        <table class="tabela-itens-fatura">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descrição</th>
                    <th>Mês</th>
                    <th class="text-right">Valor Base</th>
                    <th class="text-right">Multa</th>
                    <th class="text-right">Desconto</th>
                    <th class="text-right">Total</th>
                    <th style="text-align:center;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_fatura = 0;
                $item_num = 1;
                foreach ($itens_fatura as $index => $item): 
                    $total_fatura += $item['valor_liquido'];
                    $mes_exibicao = ($item['mes_referencia'] ?? '-') != '-' ? ($item['mes_referencia'] ?? '-') . '/' . ($item['ano_referencia'] ?? date('Y')) : '-';
                ?>
                <tr>
                    <td><?= $item_num++ ?></td>
                    <td><?= htmlspecialchars($item['emolumento'] ?? '') ?></td>
                    <td><?= $mes_exibicao ?></td>
                    <td class="text-right"><?= number_format($item['valor_base'] ?? 0, 2, ',', '.') ?> Kz</td>
                    <td class="text-right"><?= number_format($item['multa'] ?? 0, 2, ',', '.') ?> Kz</td>
                    <td class="text-right"><?= number_format($item['desconto'] ?? 0, 2, ',', '.') ?> Kz</td>
                    <td class="text-right"><strong><?= number_format($item['valor_liquido'] ?? 0, 2, ',', '.') ?> Kz</strong></td>
                    <td style="text-align:center;">
                        <a href="?remove_item=<?= $index ?>" class="btn-remove-item" onclick="return confirm('Remover este item?')">✕</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="6" class="text-right">TOTAL FATURA</td>
                    <td class="text-right"><?= number_format($total_fatura, 2, ',', '.') ?> Kz</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <form method="POST" action="" style="margin-top:20px;border-top:2px solid #eef2f7;padding-top:20px;">
        <input type="hidden" name="finalizar_pagamento" value="1">
        <input type="hidden" name="aluno_id" value="<?= $_POST['aluno_id'] ?? $aluno_selecionado['id'] ?? '' ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label>Data do Pagamento <span class="required">*</span></label>
                <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Forma de Pagamento <span class="required">*</span></label>
                <select name="forma_pagamento" required>
                    <option value="dinheiro">💰 Dinheiro</option>
                    <option value="cartao">💳 Cartão</option>
                    <option value="transferencia">🏦 Transferência</option>
                    <option value="pix">📱 Pix</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Referência</label>
                <input type="text" name="referencia" placeholder="Nº do comprovante...">
            </div>
            <div class="form-group">
                <label>Observações</label>
                <input type="text" name="observacoes" placeholder="Observações...">
            </div>
        </div>

        <?php if (!empty($itens_fatura)): ?>
        <div class="info-valor" style="margin-top:10px;">
            <strong>📊 Resumo da Fatura:</strong>
            <span id="total_fatura_resumo"><?= count($itens_fatura) ?> item(ns) - Total: <strong style="color:#c0392b;"><?= number_format($total_fatura, 2, ',', '.') ?> Kz</strong></span>
            <input type="hidden" id="total_fatura_hidden" value="<?= $total_fatura ?>">
        </div>
        <?php endif; ?>

        <div class="info-valor" style="margin-top:10px;background:#fef9e8;border-color:#fde68a;">
            ⚠️ <strong>Verificação de Duplicados:</strong> O sistema verifica automaticamente se já existe pagamento para cada item antes de registrar.
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success" <?= empty($itens_fatura) ? 'disabled' : '' ?>>
                ✅ Finalizar Pagamento
            </button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php if ($mostrar_fatura && $fatura_html): ?>
<div class="fatura-modal" id="faturaModal">
    <div class="fatura-modal-content">
        <button class="btn-fechar" onclick="fecharFatura()">✕ Fechar</button>
        <iframe class="fatura-iframe" srcdoc="<?= htmlspecialchars($fatura_html) ?>"></iframe>
    </div>
</div>
<?php endif; ?>

<script>
// ===== TODO O JAVASCRIPT (MANTIDO IGUAL) =====
function atualizarValorItem() {
    const select = document.getElementById('emolumento_id');
    const valorInput = document.getElementById('valor_base_item');
    const selected = select.options[select.selectedIndex];
    const mesSelect = document.getElementById('mes_referencia_item');
    const mesHint = document.getElementById('mesHintItem');
    
    if (selected.value) {
        const valor = parseFloat(selected.dataset.valor) || 0;
        valorInput.value = valor.toFixed(2);
        
        const nome = selected.dataset.nome || '';
        const isMesRequired = nome.toLowerCase().includes('propina') || 
                              nome.toLowerCase().includes('transporte') || 
                              nome.toLowerCase().includes('mensalidade');
        
        mesSelect.disabled = !isMesRequired;
        if (!isMesRequired) {
            mesSelect.value = '-';
            mesHint.innerHTML = '💡 Selecione o mês para Propina, Transporte ou Mensalidade';
            mesHint.className = 'mes-hint';
        } else {
            mesHint.innerHTML = '✅ Mês obrigatório para <strong>' + nome + '</strong>';
            mesHint.className = 'mes-hint ativo';
            if (mesSelect.value === '-') {
                const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
                mesSelect.value = meses[new Date().getMonth()];
            }
        }
        calcularMultaItem();
        calcularTotalItem();
    }
}

function calcularMultaItem() {
    const select = document.getElementById('emolumento_id');
    const selected = select.options[select.selectedIndex];
    const emolumentoId = selected.value;
    const valorBase = parseFloat(document.getElementById('valor_base_item').value) || 0;
    const mesSelect = document.getElementById('mes_referencia_item');
    const mesNome = mesSelect.value;
    const ano = parseInt(document.getElementById('ano_referencia_item').value) || new Date().getFullYear();
    const multaInput = document.getElementById('multa_item');
    const multaInfo = document.getElementById('multaInfoItem');
    
    if (!emolumentoId || mesNome === '-' || !valorBase) {
        multaInput.value = '0,00 Kz';
        multaInfo.className = 'multa-info';
        return;
    }
    
    fetch('calcular_multa_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `emolumento_id=${emolumentoId}&mes=${mesNome}&ano=${ano}&valor_base=${valorBase}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const multa = data.multa;
            if (multa > 0) {
                multaInput.value = multa.toFixed(2).replace('.', ',') + ' Kz';
                multaInfo.innerHTML = '⚠️ Multa de ' + multa.toFixed(2).replace('.', ',') + ' Kz por atraso de ' + data.dias_atraso + ' dias.';
                multaInfo.className = 'multa-info ativo';
            } else {
                multaInput.value = '0,00 Kz';
                multaInfo.className = 'multa-info';
            }
            calcularTotalItem();
        }
    })
    .catch(() => {
        multaInput.value = '0,00 Kz';
        multaInfo.className = 'multa-info';
    });
}

function calcularTotalItem() {
    const valorBase = parseFloat(document.getElementById('valor_base_item').value) || 0;
    const desconto = parseFloat(document.getElementById('desconto_item').value) || 0;
    const multaText = document.getElementById('multa_item').value.replace(/[^0-9,.]/g, '').replace(',', '.');
    const multa = parseFloat(multaText) || 0;
    
    let total = valorBase + multa - desconto;
    if (total < 0) total = 0;
    
    document.getElementById('valor_liquido_item').value = total.toFixed(2);
}

function aplicarDescontoPercentualItem() {
    const valorBase = parseFloat(document.getElementById('valor_base_item').value) || 0;
    const percent = parseFloat(document.getElementById('desconto_percent_item').value) || 0;
    if (percent < 0 || percent > 100) { alert('Percentual deve ser entre 0 e 100'); return; }
    document.getElementById('desconto_item').value = ((valorBase * percent) / 100).toFixed(2);
    calcularTotalItem();
}

document.getElementById('emolumento_id').addEventListener('change', function() {
    setTimeout(calcularMultaItem, 100);
});
document.getElementById('mes_referencia_item').addEventListener('change', calcularMultaItem);
document.getElementById('ano_referencia_item').addEventListener('change', calcularMultaItem);
document.getElementById('ano_referencia_item').addEventListener('input', calcularMultaItem);
document.getElementById('valor_base_item').addEventListener('change', calcularMultaItem);
document.getElementById('valor_base_item').addEventListener('input', function() {
    setTimeout(calcularMultaItem, 300);
});

function selecionarAluno(btn) {
    const item = btn.closest('.aluno-item');
    if (!item) return;
    const id = item.dataset.id, nome = item.dataset.nome, genero = item.dataset.genero;
    const select = document.getElementById('aluno_id');
    const hidden = document.getElementById('aluno_id_hidden');
    for (let opt of select.options) { if (opt.value == id) { opt.selected = true; break; } }
    if (hidden) hidden.value = id;
    const old = document.getElementById('alunoFeedback');
    if (old) old.remove();
    const parent = document.querySelector('.form-group');
    const div = document.createElement('div');
    div.id = 'alunoFeedback';
    div.className = 'aluno-selecionado-feedback';
    div.innerHTML = `<span>✅ <strong>#${id}</strong> - ${nome}${genero ? ' - ' + (genero === 'M' ? '👨 Masculino' : '👩 Feminino') : ''}</span>
                     <button type="button" class="remover-btn" onclick="removerSelecao()">✕ Remover</button>`;
    parent.appendChild(div);
    document.querySelectorAll('.aluno-item').forEach(i => i.classList.remove('aluno-selecionado'));
    item.classList.add('aluno-selecionado');
    div.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function removerSelecao() {
    document.getElementById('aluno_id').value = '';
    document.getElementById('aluno_id_hidden').value = '';
    const fb = document.getElementById('alunoFeedback');
    if (fb) fb.remove();
    document.querySelectorAll('.aluno-item').forEach(i => i.classList.remove('aluno-selecionado'));
}

function fecharFatura() {
    document.getElementById('faturaModal').style.display = 'none';
    window.location.href = 'add.php';
}

let timeoutId;
document.getElementById('busca_aluno')?.addEventListener('input', function() {
    clearTimeout(timeoutId);
    if (this.value.trim().length >= 2) {
        timeoutId = setTimeout(() => document.getElementById('buscaForm').submit(), 500);
    }
});

function toggleImportacao() {
    const section = document.getElementById('importSection');
    section.classList.toggle('active');
    localStorage.setItem('importSectionOpen', section.classList.contains('active') ? 'true' : 'false');
}

document.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem('importSectionOpen');
    if (saved === 'true') {
        document.getElementById('importSection').classList.add('active');
    }
    
    <?php if ($aluno_selecionado): ?>
    setTimeout(() => {
        document.querySelectorAll('.aluno-item').forEach(i => {
            if (i.dataset.id == <?= $aluno_selecionado['id'] ?>) i.classList.add('aluno-selecionado');
        });
    }, 100);
    <?php endif; ?>
    setTimeout(calcularMultaItem, 500);
});
</script>

<?php include '../../includes/footer_escola.php'; ?>