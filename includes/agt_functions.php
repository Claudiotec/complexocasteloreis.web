<?php
// ============================================
// agt_functions.php - Funções para faturação AGT
// ============================================

/**
 * Busca configuração AGT da empresa
 */
function getConfigAGT() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM empresa_agt LIMIT 1");
        $config = $stmt->fetch();
        
        if (!$config) {
            // Configuração padrão
            return [
                'nif' => '5000123456',
                'nome_comercial' => 'SoftGest Sistemas',
                'endereco' => 'Luanda, Angola',
                'telefone' => '923456789',
                'email' => 'contato@softgest.com',
                'regime_iva' => 'normal',
                'taxa_iva_padrao' => 14.00,
                'serie_fatura' => 'A',
                'numero_fatura' => 1
            ];
        }
        return $config;
    } catch (Exception $e) {
        return [
            'nif' => '5000123456',
            'nome_comercial' => 'SoftGest Sistemas',
            'endereco' => 'Luanda, Angola',
            'telefone' => '923456789',
            'email' => 'contato@softgest.com',
            'regime_iva' => 'normal',
            'taxa_iva_padrao' => 14.00,
            'serie_fatura' => 'A',
            'numero_fatura' => 1
        ];
    }
}

/**
 * Gera número de fatura sequencial
 */
function gerarNumeroFatura($serie = 'A') {
    global $pdo;
    
    try {
        // Buscar último número
        $stmt = $pdo->prepare("SELECT numero_fatura FROM empresa_agt LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch();
        
        if (!$config) {
            $numero = 1;
        } else {
            $numero = $config['numero_fatura'] + 1;
            // Atualizar número
            $stmt = $pdo->prepare("UPDATE empresa_agt SET numero_fatura = ?");
            $stmt->execute([$numero]);
        }
        
        // Formatar: A-2024-0001
        $ano = date('Y');
        return sprintf("%s-%s-%04d", $serie, $ano, $numero);
        
    } catch (Exception $e) {
        return sprintf("%s-%s-%04d", $serie, date('Y'), rand(1, 9999));
    }
}

/**
 * Calcula IVA e totais
 */
function calcularTotaisFatura($itens, $taxa_iva = 14) {
    $subtotal = 0;
    $desconto_total = 0;
    
    foreach ($itens as $item) {
        $subtotal += $item['preco_unitario'] * $item['quantidade'];
        $desconto_total += $item['desconto'] ?? 0;
    }
    
    $base_incidencia = $subtotal - $desconto_total;
    $valor_iva = $base_incidencia * ($taxa_iva / 100);
    $valor_total = $base_incidencia + $valor_iva;
    
    return [
        'subtotal' => $subtotal,
        'desconto_total' => $desconto_total,
        'base_incidencia' => $base_incidencia,
        'valor_iva' => $valor_iva,
        'taxa_iva' => $taxa_iva,
        'valor_total' => $valor_total
    ];
}

/**
 * Gera código de validação (AGT)
 */
function gerarCodigoValidacao($fatura_id, $dados) {
    // Código baseado em dados da fatura
    $base = $fatura_id . '|' . ($dados['numero_fatura'] ?? '') . '|' . ($dados['cliente_nif'] ?? '') . '|' . ($dados['valor_total'] ?? 0) . '|' . date('Y-m-d');
    return 'AGT-' . substr(md5($base), 0, 12);
}

/**
 * Gera QR Code para a fatura (simulado)
 */
function gerarQRCodeAGT($dados) {
    // Verificar se $dados existe e é um array
    if (!is_array($dados)) {
        $dados = [];
    }
    
    // Definir valores padrão
    $dados = array_merge([
        'nif_emissor' => '5000123456',
        'cliente_nif' => '999999999',
        'numero_fatura' => 'A-2026-0001',
        'valor_total' => 0,
        'codigo_validacao' => 'AGT-XXXXXXXXXXXX'
    ], $dados);
    
    // Construir dados para QR Code
    $qr_data = [
        'NIF_EMISSOR' => $dados['nif_emissor'],
        'NIF_CLIENTE' => $dados['cliente_nif'],
        'NUMERO' => $dados['numero_fatura'],
        'DATA' => date('Y-m-d H:i:s'),
        'TOTAL' => number_format($dados['valor_total'], 2, '.', ''),
        'CODIGO' => $dados['codigo_validacao']
    ];
    
    return http_build_query($qr_data);
}

/**
 * Emitir fatura AGT
 */
function emitirFaturaAGT($dados) {
    global $pdo;
    
    try {
        // Validar dados
        if (empty($dados['cliente_nome']) || empty($dados['itens'])) {
            throw new Exception('Dados da fatura incompletos');
        }
        
        // Buscar configuração
        $config = getConfigAGT();
        
        // Gerar número da fatura
        $numero_fatura = gerarNumeroFatura($config['serie_fatura']);
        
        // Calcular totais
        $totais = calcularTotaisFatura($dados['itens'], $config['taxa_iva_padrao']);
        
        // Inserir fatura
        $stmt = $pdo->prepare("
            INSERT INTO faturas_agt (
                numero_fatura, serie, data_emissao,
                cliente_id, cliente_nome, cliente_nif, cliente_endereco, cliente_telefone, cliente_email,
                tipo_documento, subtotal, desconto, base_incidencia, valor_iva, taxa_iva, valor_total,
                forma_pagamento, observacoes, created_by
            ) VALUES (
                ?, ?, NOW(),
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?
            )
        ");
        
        $stmt->execute([
            $numero_fatura,
            $config['serie_fatura'],
            $dados['cliente_id'] ?? null,
            $dados['cliente_nome'],
            $dados['cliente_nif'] ?? null,
            $dados['cliente_endereco'] ?? null,
            $dados['cliente_telefone'] ?? null,
            $dados['cliente_email'] ?? null,
            'fatura',
            $totais['subtotal'],
            $totais['desconto_total'],
            $totais['base_incidencia'],
            $totais['valor_iva'],
            $totais['taxa_iva'],
            $totais['valor_total'],
            $dados['forma_pagamento'] ?? 'dinheiro',
            $dados['observacoes'] ?? null,
            $_SESSION['usuario_id'] ?? null
        ]);
        
        $fatura_id = $pdo->lastInsertId();
        
        // Gerar código de validação com dados corretos
        $codigo_validacao = gerarCodigoValidacao($fatura_id, [
            'numero_fatura' => $numero_fatura,
            'cliente_nif' => $dados['cliente_nif'] ?? '999999999',
            'valor_total' => $totais['valor_total']
        ]);
        
        // Atualizar fatura com código
        $stmt = $pdo->prepare("UPDATE faturas_agt SET codigo_validacao = ? WHERE id = ?");
        $stmt->execute([$codigo_validacao, $fatura_id]);
        
        // Inserir itens
        foreach ($dados['itens'] as $item) {
            $preco = $item['preco_unitario'] ?? 0;
            $qtd = $item['quantidade'] ?? 1;
            $desconto_item = $item['desconto'] ?? 0;
            $total_item = ($preco * $qtd) - $desconto_item;
            $iva_item = $total_item * ($config['taxa_iva_padrao'] / 100);
            
            $stmt = $pdo->prepare("
                INSERT INTO fatura_agt_itens (
                    fatura_id, produto_id, codigo, descricao, quantidade,
                    preco_unitario, desconto, taxa_iva, valor_iva, total_item
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fatura_id,
                $item['produto_id'] ?? null,
                $item['codigo'] ?? null,
                $item['descricao'],
                $qtd,
                $preco,
                $desconto_item,
                $config['taxa_iva_padrao'],
                $iva_item,
                $total_item + $iva_item
            ]);
        }
        
        // Gerar QR Code com dados corretos
        $qr_data = [
            'nif_emissor' => $config['nif'],
            'cliente_nif' => $dados['cliente_nif'] ?? '999999999',
            'numero_fatura' => $numero_fatura,
            'valor_total' => $totais['valor_total'],
            'codigo_validacao' => $codigo_validacao
        ];
        
        $qr_code = gerarQRCodeAGT($qr_data);
        
        $stmt = $pdo->prepare("UPDATE faturas_agt SET qr_code = ? WHERE id = ?");
        $stmt->execute([$qr_code, $fatura_id]);
        
        return [
            'success' => true,
            'fatura_id' => $fatura_id,
            'numero_fatura' => $numero_fatura,
            'codigo_validacao' => $codigo_validacao,
            'qr_code' => $qr_code,
            'valor_total' => $totais['valor_total']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Buscar fatura por ID
 */
function getFaturaAGT($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT f.*, 
                   u.nome as usuario_nome,
                   (SELECT COUNT(*) FROM fatura_agt_itens WHERE fatura_id = f.id) as total_itens
            FROM faturas_agt f
            LEFT JOIN usuarios u ON f.created_by = u.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Buscar itens da fatura
 */
function getItensFaturaAGT($fatura_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT i.*, p.nome as produto_nome
            FROM fatura_agt_itens i
            LEFT JOIN produtos p ON i.produto_id = p.id
            WHERE i.fatura_id = ?
            ORDER BY i.id
        ");
        $stmt->execute([$fatura_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Imprimir fatura em HTML (modelo AGT) - COMPLETO COM CSS CORRETO
 */
function imprimirFaturaAGT($fatura_id) {
    global $pdo;
    
    // Buscar fatura
    $fatura = getFaturaAGT($fatura_id);
    $itens = getItensFaturaAGT($fatura_id);
    $config = getConfigAGT();
    
    if (!$fatura) {
        return '<p>Fatura não encontrada</p>';
    }
    
    // Buscar dados da empresa
    $empresa = [];
    try {
        $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
        $empresa = $stmt->fetch();
    } catch (Exception $e) {}
    
    $nomeEmpresa = $empresa['nome_fantasia'] ?? $config['nome_comercial'] ?? 'SoftGest Sistemas';
    $enderecoEmpresa = $empresa['endereco'] ?? $config['endereco'] ?? 'Luanda, Angola';
    $telefoneEmpresa = $empresa['telefone'] ?? $config['telefone'] ?? '923456789';
    $emailEmpresa = $empresa['email'] ?? $config['email'] ?? 'contato@softgest.com';
    $nifEmpresa = $config['nif'] ?? '5000123456';
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Fatura <?= $fatura['numero_fatura'] ?></title>
        <style>
            /* ============================================
               RESET E LAYOUT PRINCIPAL
               ============================================ */
            * { 
                margin: 0; 
                padding: 0; 
                box-sizing: border-box; 
            }
            
            body { 
                font-family: 'Arial', 'Helvetica', sans-serif; 
                font-size: 12px; 
                margin: 0;
                padding: 20px;
                background: #f5f5f5;
            }
            
            .fatura-container {
                max-width: 800px;
                width: 100%;
                margin: 0 auto;
                background: #ffffff;
                padding: 35px 40px;
                box-shadow: 0 2px 20px rgba(0,0,0,0.1);
                border-radius: 4px;
            }
            
            /* ============================================
               HEADER - CENTRALIZADO
               ============================================ */
            .header {
                text-align: center;
                border-bottom: 3px solid #1a2332;
                padding-bottom: 18px;
                margin-bottom: 25px;
            }
            
            .header .empresa-nome {
                font-size: 24px;
                font-weight: 800;
                color: #1a2332;
                letter-spacing: 0.5px;
            }
            
            .header .empresa-nome .destaque {
                color: #c9a84c;
            }
            
            .header .empresa-dados {
                font-size: 12px;
                color: #4a5568;
                margin-top: 4px;
            }
            
            .header .empresa-dados .sep {
                margin: 0 6px;
                color: #c9a84c;
            }
            
            .header .empresa-nif {
                font-size: 13px;
                font-weight: 700;
                color: #1a2332;
                margin-top: 3px;
            }
            
            .header .empresa-nif .nif-box {
                background: #f8fafc;
                padding: 2px 14px;
                border-radius: 4px;
                border: 1px solid #e2e8f0;
            }
            
            .header .titulo-fatura {
                margin-top: 12px;
            }
            
            .header .titulo-fatura .titulo-box {
                background: #c9a84c;
                color: #1a2332;
                padding: 4px 30px;
                border-radius: 4px;
                font-size: 20px;
                font-weight: 700;
                letter-spacing: 3px;
                display: inline-block;
            }
            
            .header .info-fatura {
                margin-top: 8px;
                font-size: 13px;
                color: #4a5568;
            }
            
            .header .info-fatura .label {
                font-weight: 600;
                color: #1a2332;
            }
            
            .header .info-fatura .numero {
                font-weight: 700;
                color: #c9a84c;
                font-size: 15px;
            }
            
            .header .info-fatura .barra {
                margin: 0 12px;
                color: #cbd5e1;
            }
            
            /* ============================================
               GRID DE INFORMAÇÕES
               ============================================ */
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 18px;
                margin-bottom: 25px;
            }
            
            .info-box {
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 14px 18px;
                background: #fafbfc;
            }
            
            .info-box .titulo-box {
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #94a3b8;
                margin-bottom: 8px;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 6px;
            }
            
            .info-box .linha {
                display: flex;
                justify-content: space-between;
                padding: 3px 0;
                font-size: 12px;
            }
            
            .info-box .linha .rotulo {
                color: #94a3b8;
                font-weight: 500;
            }
            
            .info-box .linha .valor {
                color: #1a2332;
                font-weight: 600;
            }
            
            /* ============================================
               TABELA
               ============================================ */
            .table-wrap {
                overflow-x: auto;
                margin: 20px 0 15px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 12px;
            }
            
            table thead {
                background: #1a2332;
            }
            
            table thead th {
                padding: 10px 12px;
                text-align: left;
                font-weight: 600;
                color: #ffffff;
                border: 1px solid #1a2332;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            table thead th:last-child {
                text-align: right;
            }
            
            table tbody td {
                padding: 9px 12px;
                border: 1px solid #e2e8f0;
                vertical-align: middle;
            }
            
            table tbody td:last-child {
                text-align: right;
                font-weight: 600;
            }
            
            table tbody tr:nth-child(even) {
                background: #fafbfc;
            }
            
            table tbody tr:hover {
                background: #f1f5f9;
            }
            
            /* ============================================
               TOTAIS
               ============================================ */
            .totais {
                margin-top: 15px;
                border-top: 2px solid #e2e8f0;
                padding-top: 12px;
                text-align: right;
                max-width: 320px;
                margin-left: auto;
            }
            
            .totais .linha {
                display: flex;
                justify-content: space-between;
                padding: 3px 0;
                font-size: 12px;
            }
            
            .totais .linha .rotulo {
                color: #4a5568;
                font-weight: 500;
            }
            
            .totais .linha .valor {
                font-weight: 600;
                color: #1a2332;
            }
            
            .totais .linha.total {
                font-size: 17px;
                border-top: 2px solid #c9a84c;
                padding-top: 8px;
                margin-top: 4px;
            }
            
            .totais .linha.total .rotulo {
                font-weight: 700;
                color: #1a2332;
            }
            
            .totais .linha.total .valor {
                font-weight: 800;
                color: #c9a84c;
                font-size: 19px;
            }
            
            /* ============================================
               CÓDIGO DE VALIDAÇÃO
               ============================================ */
            .codigo-validacao {
                background: #f8fafc;
                border: 2px dashed #c9a84c;
                border-radius: 8px;
                padding: 12px 20px;
                text-align: center;
                margin: 20px 0 15px;
            }
            
            .codigo-validacao .rotulo {
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #94a3b8;
                display: block;
            }
            
            .codigo-validacao .codigo {
                font-size: 16px;
                font-weight: 700;
                color: #1a2332;
                font-family: 'Courier New', monospace;
                letter-spacing: 0.5px;
                margin-top: 2px;
            }
            
            .codigo-validacao .codigo span {
                background: #c9a84c;
                color: #1a2332;
                padding: 2px 18px;
                border-radius: 4px;
            }
            
            /* ============================================
               QR CODE
               ============================================ */
            .qr-code {
                text-align: center;
                margin: 12px 0 15px;
            }
            
            .qr-code .rotulo {
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #94a3b8;
                display: block;
                margin-bottom: 6px;
            }
            
            .qr-code .qr-box {
                display: inline-block;
                border: 1px solid #e2e8f0;
                padding: 10px 18px;
                background: #ffffff;
                border-radius: 8px;
            }
            
            .qr-code .qr-box pre {
                font-size: 7px;
                margin: 0;
                font-family: 'Courier New', monospace;
                color: #1a2332;
                line-height: 1.3;
            }
            
            /* ============================================
               FOOTER
               ============================================ */
            .footer {
                text-align: center;
                margin-top: 20px;
                border-top: 1px solid #e2e8f0;
                padding-top: 12px;
                font-size: 10px;
                color: #94a3b8;
            }
            
            .footer .link {
                color: #c9a84c;
                font-weight: 600;
                text-decoration: none;
            }
            
            /* ============================================
               RESPONSIVO
               ============================================ */
            @media (max-width: 768px) {
                .fatura-container { 
                    padding: 18px 16px; 
                }
                .info-grid { 
                    grid-template-columns: 1fr; 
                    gap: 12px; 
                }
                .header .empresa-nome { 
                    font-size: 18px; 
                }
                .header .titulo-fatura .titulo-box { 
                    font-size: 16px; 
                    padding: 3px 20px; 
                }
                table { 
                    font-size: 10px; 
                }
                table thead th, 
                table tbody td { 
                    padding: 6px 8px; 
                }
                .totais { 
                    max-width: 100%; 
                }
                .totais .linha.total { 
                    font-size: 15px; 
                }
                .totais .linha.total .valor { 
                    font-size: 17px; 
                }
                .codigo-validacao .codigo { 
                    font-size: 13px; 
                }
            }
            
            @media print {
                body { 
                    background: #ffffff; 
                    padding: 0; 
                }
                .fatura-container { 
                    box-shadow: none; 
                    padding: 20px 25px; 
                    border-radius: 0;
                }
                .no-print { 
                    display: none !important; 
                }
            }
        </style>
    </head>
    <body>
        <div class="fatura-container">
            <!-- ==========================================
                 HEADER - CENTRALIZADO
                 ========================================== -->
            <div class="header">
                <div class="empresa-nome">
                    <?= htmlspecialchars($nomeEmpresa) ?> <span class="destaque">Web</span>
                </div>
                <div class="empresa-dados">
                    <?= htmlspecialchars($enderecoEmpresa) ?>
                    <span class="sep">|</span>
                    Tel: <?= htmlspecialchars($telefoneEmpresa) ?>
                    <span class="sep">|</span>
                    Email: <?= htmlspecialchars($emailEmpresa) ?>
                </div>
                <div class="empresa-nif">
                    NIF: <span class="nif-box"><?= htmlspecialchars($nifEmpresa) ?></span>
                </div>
                <div class="titulo-fatura">
                    <span class="titulo-box">FATURA</span>
                </div>
                <div class="info-fatura">
                    <span class="label">Nº:</span> 
                    <span class="numero"><?= $fatura['numero_fatura'] ?></span>
                    <span class="barra">|</span>
                    <span class="label">Data:</span> 
                    <?= date('d/m/Y H:i', strtotime($fatura['data_emissao'])) ?>
                </div>
            </div>
            
            <!-- ==========================================
                 INFORMAÇÕES
                 ========================================== -->
            <div class="info-grid">
                <div class="info-box">
                    <div class="titulo-box">📋 DADOS DO CLIENTE</div>
                    <div class="linha">
                        <span class="rotulo">Nome:</span>
                        <span class="valor"><?= htmlspecialchars($fatura['cliente_nome']) ?></span>
                    </div>
                    <div class="linha">
                        <span class="rotulo">NIF:</span>
                        <span class="valor"><?= htmlspecialchars($fatura['cliente_nif'] ?? '---') ?></span>
                    </div>
                    <div class="linha">
                        <span class="rotulo">Endereço:</span>
                        <span class="valor"><?= htmlspecialchars($fatura['cliente_endereco'] ?? '---') ?></span>
                    </div>
                    <div class="linha">
                        <span class="rotulo">Telefone:</span>
                        <span class="valor"><?= htmlspecialchars($fatura['cliente_telefone'] ?? '---') ?></span>
                    </div>
                </div>
                
                <div class="info-box">
                    <div class="titulo-box">📄 INFORMAÇÕES DA FATURA</div>
                    <div class="linha">
                        <span class="rotulo">Status:</span>
                        <span class="valor"><?= ucfirst($fatura['status']) ?></span>
                    </div>
                    <div class="linha">
                        <span class="rotulo">Forma Pagamento:</span>
                        <span class="valor"><?= ucfirst(str_replace('_', ' ', $fatura['forma_pagamento'] ?? '---')) ?></span>
                    </div>
                    <div class="linha">
                        <span class="rotulo">Taxa IVA:</span>
                        <span class="valor"><?= $fatura['taxa_iva'] ?>%</span>
                    </div>
                    <div class="linha">
                        <span class="rotulo">Tipo Documento:</span>
                        <span class="valor">Fatura Fiscal</span>
                    </div>
                </div>
            </div>
            
            <!-- ==========================================
                 TABELA DE ITENS
                 ========================================== -->
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 8%;">Qtd</th>
                            <th style="width: 12%;">Código</th>
                            <th style="width: 35%;">Descrição</th>
                            <th style="width: 15%;">Preço Unit.</th>
                            <th style="width: 10%;">Desconto</th>
                            <th style="width: 20%; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($itens as $item): ?>
                        <tr>
                            <td><?= number_format($item['quantidade'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($item['codigo'] ?? '---') ?></td>
                            <td><?= htmlspecialchars($item['descricao']) ?></td>
                            <td>R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($item['desconto'] ?? 0, 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($item['total_item'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ==========================================
                 TOTAIS
                 ========================================== -->
            <div class="totais">
                <div class="linha">
                    <span class="rotulo">Subtotal:</span>
                    <span class="valor">R$ <?= number_format($fatura['subtotal'], 2, ',', '.') ?></span>
                </div>
                <div class="linha">
                    <span class="rotulo">Desconto:</span>
                    <span class="valor">R$ <?= number_format($fatura['desconto'] ?? 0, 2, ',', '.') ?></span>
                </div>
                <div class="linha">
                    <span class="rotulo">Base Incidência:</span>
                    <span class="valor">R$ <?= number_format($fatura['base_incidencia'], 2, ',', '.') ?></span>
                </div>
                <div class="linha">
                    <span class="rotulo">IVA (<?= $fatura['taxa_iva'] ?>%):</span>
                    <span class="valor">R$ <?= number_format($fatura['valor_iva'], 2, ',', '.') ?></span>
                </div>
                <div class="linha total">
                    <span class="rotulo">TOTAL:</span>
                    <span class="valor">R$ <?= number_format($fatura['valor_total'], 2, ',', '.') ?></span>
                </div>
            </div>
            
            <!-- ==========================================
                 CÓDIGO DE VALIDAÇÃO
                 ========================================== -->
            <div class="codigo-validacao">
                <span class="rotulo">🔐 Código de Validação AGT</span>
                <div class="codigo">
                    <span><?= $fatura['codigo_validacao'] ?></span>
                </div>
            </div>
            
            <!-- ==========================================
                 QR CODE
                 ========================================== -->
            <div class="qr-code">
                <span class="rotulo">📱 QR Code de Validação</span>
                <div class="qr-box">
                    <pre><?= $fatura['qr_code'] ?></pre>
                </div>
            </div>
            
            <!-- ==========================================
                 FOOTER
                 ========================================== -->
            <div class="footer">
                <p>
                    Documento emitido por sistema autorizado AGT • 
                    Consulte a validade em: 
                    <a href="https://portal.agt.gov.ao" target="_blank" class="link">portal.agt.gov.ao</a>
                </p>
                <p style="margin-top: 3px;">
                    Data de emissão: <?= date('d/m/Y H:i:s') ?>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>