<?php
// ============================================
// modules/escola/agt/gerar_fatura_html.php - Gerar HTML da Fatura
// ============================================

function gerarFaturaHTMLAGT($empresa, $aluno, $itens, $numero_fatura, $total_base, $total_iva, $total_liquido, $hash) {
    
    $html = '<!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Fatura Nº ' . $numero_fatura . '</title>
        <style>
            @page {
                size: A4;
                margin: 8mm;
                @top-center {
                    content: "FATURA DE SERVIÇOS EDUCACIONAIS";
                    font-size: 9pt;
                    color: #666;
                }
                @bottom-center {
                    content: "Página " counter(page) " de " counter(pages);
                    font-size: 8pt;
                    color: #999;
                }
            }
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: "Times New Roman", Arial, sans-serif;
            }
            body {
                background: #fff;
                padding: 5mm;
                font-size: 12px;
            }
            .fatura-container {
                max-width: 200mm;
                margin: 0 auto;
                background: #fff;
            }
            /* ===== CABEÇALHO ===== */
            .header-fatura {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 8px 0 12px 0;
                border-bottom: 3px double #1a2332;
                margin-bottom: 12px;
            }
            .header-fatura .empresa-info {
                flex: 1;
            }
            .header-fatura .empresa-info .nome {
                font-size: 20px;
                font-weight: 800;
                color: #1a2332;
                text-transform: uppercase;
            }
            .header-fatura .empresa-info .dados {
                font-size: 10px;
                color: #555;
                margin: 2px 0;
            }
            .header-fatura .empresa-info .nif-box {
                display: inline-block;
                background: #1a2332;
                color: #fff;
                padding: 2px 15px;
                border-radius: 4px;
                font-size: 10px;
                font-weight: 700;
                margin-top: 4px;
            }
            .header-fatura .fatura-titulo {
                text-align: right;
                min-width: 180px;
            }
            .header-fatura .fatura-titulo .tipo {
                font-size: 22px;
                font-weight: 800;
                color: #c9a84c;
                letter-spacing: 3px;
            }
            .header-fatura .fatura-titulo .numero {
                font-size: 14px;
                font-weight: 700;
                color: #1a2332;
                margin: 3px 0;
            }
            .header-fatura .fatura-titulo .data {
                font-size: 10px;
                color: #555;
            }
            .header-fatura .fatura-titulo .status-badge {
                display: inline-block;
                padding: 2px 15px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 700;
                color: #fff;
                background: #2ecc71;
                margin-top: 4px;
            }
            
            /* ===== DADOS DO ALUNO ===== */
            .aluno-area {
                padding: 10px 14px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                margin-bottom: 12px;
            }
            .aluno-area .label {
                font-size: 8px;
                color: #94a3b8;
                text-transform: uppercase;
                font-weight: 600;
                letter-spacing: 0.5px;
            }
            .aluno-area .nome {
                font-size: 16px;
                font-weight: 700;
                color: #1a2332;
            }
            .aluno-area .info {
                font-size: 11px;
                color: #555;
                margin: 3px 0;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .aluno-area .info .tag {
                background: #eef2f7;
                padding: 1px 10px;
                border-radius: 12px;
                font-size: 10px;
            }
            
            /* ===== TABELA DE ITENS ===== */
            .tabela-itens {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12px;
                font-size: 11px;
            }
            .tabela-itens th {
                background: #1a2332;
                color: #fff;
                padding: 6px 8px;
                text-align: left;
                border: 1px solid #1a2332;
                font-size: 9px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .tabela-itens td {
                padding: 5px 8px;
                border: 1px solid #e2e8f0;
                vertical-align: middle;
            }
            .tabela-itens .text-right {
                text-align: right;
            }
            .tabela-itens .text-center {
                text-align: center;
            }
            .tabela-itens .total-row {
                background: #f8fafc;
                font-weight: 700;
            }
            .tabela-itens .total-row td {
                border-top: 2px solid #1a2332;
            }
            .tabela-itens .valor-total {
                color: #c0392b;
                font-size: 14px;
            }
            
            /* ===== RESUMO ===== */
            .resumo-fatura {
                display: flex;
                justify-content: flex-end;
                padding: 12px 16px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                margin-bottom: 12px;
            }
            .resumo-fatura .grid {
                display: grid;
                grid-template-columns: auto auto;
                gap: 3px 25px;
                font-size: 12px;
            }
            .resumo-fatura .grid .label {
                color: #555;
            }
            .resumo-fatura .grid .value {
                text-align: right;
                font-weight: 600;
            }
            .resumo-fatura .grid .total {
                font-size: 18px;
                color: #c0392b;
                font-weight: 800;
                border-top: 2px solid #1a2332;
                padding-top: 6px;
                margin-top: 4px;
            }
            
            /* ===== RODAPÉ ===== */
            .rodape-fatura {
                padding: 12px 0;
                border-top: 2px solid #1a2332;
                margin-top: 8px;
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
            }
            .rodape-fatura .assinatura {
                text-align: center;
                min-width: 140px;
            }
            .rodape-fatura .assinatura .linha {
                width: 130px;
                border-top: 1px solid #333;
                margin: 4px auto;
            }
            .rodape-fatura .assinatura .cargo {
                font-size: 9px;
                color: #555;
            }
            .rodape-fatura .info-extra {
                font-size: 8px;
                color: #999;
                text-align: right;
            }
            .rodape-fatura .info-extra .hash {
                font-size: 7px;
                word-break: break-all;
                max-width: 200px;
            }
            
            /* ===== DIVISOR ENTRE VIAS ===== */
            .via-divider {
                text-align: center;
                font-size: 13px;
                font-weight: 700;
                color: #1a2332;
                padding: 12px 0;
                margin: 15px 0;
                border-top: 2px dashed #ccc;
                border-bottom: 2px dashed #ccc;
            }
            .via-divider span {
                background: #fff;
                padding: 0 18px;
            }
            
            /* ===== SELO DE VALIDAÇÃO ===== */
            .selo-validacao {
                display: inline-block;
                padding: 3px 12px;
                border: 2px solid #c9a84c;
                border-radius: 4px;
                font-size: 8px;
                color: #1a2332;
                font-weight: 600;
                margin-top: 5px;
            }
            
            @media print {
                body { background: #fff !important; padding: 0 !important; }
                .fatura-container { box-shadow: none !important; }
                .no-print { display: none !important; }
                * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            }
            @media screen and (max-width: 768px) {
                .header-fatura { flex-direction: column; gap: 8px; }
                .header-fatura .fatura-titulo { text-align: left; }
                .resumo-fatura { justify-content: flex-start; }
                .resumo-fatura .grid { grid-template-columns: 1fr; }
                .tabela-itens { font-size: 9px; }
                .tabela-itens th, .tabela-itens td { padding: 3px 5px; }
                .rodape-fatura { flex-direction: column; text-align: center; }
            }
        </style>
    </head>
    <body>
    <div class="fatura-container">';
    
    // ===== VIA DO CLIENTE =====
    $html .= '<div style="border: 1px solid #ddd; border-radius: 6px; padding: 12px; margin-bottom: 8px;">';
    $html .= '<div style="text-align: center; font-size: 9px; font-weight: 700; color: #fff; background: #1a2332; padding: 3px 0; border-radius: 4px 4px 0 0; margin: -12px -12px 12px -12px; letter-spacing: 3px;">VIA DO CLIENTE</div>';
    $html .= gerarCorpoFaturaAGT($empresa, $aluno, $itens, $numero_fatura, $total_base, $total_iva, $total_liquido, $hash);
    $html .= '</div>';
    
    // ===== DIVISOR =====
    $html .= '<div class="via-divider"><span>✂️ CORTE AQUI ✂️</span></div>';
    
    // ===== VIA DA ESCOLA =====
    $html .= '<div style="border: 2px dashed #c9a84c; border-radius: 6px; padding: 12px;">';
    $html .= '<div style="text-align: center; font-size: 9px; font-weight: 700; color: #1a2332; background: #c9a84c; padding: 3px 0; border-radius: 4px 4px 0 0; margin: -12px -12px 12px -12px; letter-spacing: 3px;">VIA DA ESCOLA</div>';
    $html .= gerarCorpoFaturaAGT($empresa, $aluno, $itens, $numero_fatura, $total_base, $total_iva, $total_liquido, $hash);
    $html .= '</div>';
    
    $html .= '
    <div class="no-print" style="text-align:center;padding:15px 0;">
        <button onclick="window.print()" style="padding:10px 30px;background:#1a2332;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">🖨️ IMPRIMIR</button>
        <button onclick="window.close()" style="padding:10px 30px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;margin-left:10px;">✕ FECHAR</button>
    </div>
    </body></html>';
    
    return $html;
}

function gerarCorpoFaturaAGT($empresa, $aluno, $itens, $numero_fatura, $total_base, $total_iva, $total_liquido, $hash) {
    
    $html = '
    <div class="header-fatura">
        <div class="empresa-info">
            <div class="nome">' . htmlspecialchars($empresa['nome']) . '</div>
            <div class="dados">📍 ' . htmlspecialchars($empresa['endereco']) . '</div>
            <div class="dados">📞 ' . htmlspecialchars($empresa['telefone']) . ' | ✉ ' . htmlspecialchars($empresa['email']) . '</div>
            <div class="nif-box">NIF: ' . htmlspecialchars($empresa['nif']) . '</div>
        </div>
        <div class="fatura-titulo">
            <div class="tipo">FATURA</div>
            <div class="numero">Nº ' . $numero_fatura . '</div>
            <div class="data">' . date('d/m/Y H:i') . '</div>
            <div class="status-badge">✅ PAGO</div>
            <div class="selo-validacao">🔒 FATURA VÁLIDA</div>
        </div>
    </div>
    
    <div class="aluno-area">
        <div class="label">📋 DADOS DO ALUNO</div>
        <div class="nome">' . htmlspecialchars($aluno['nome']) . '</div>
        <div class="info">
            <span class="tag">NIF: ' . htmlspecialchars($aluno['nif']) . '</span>
            <span class="tag">Classe: ' . htmlspecialchars($aluno['classe']) . '</span>
            <span class="tag">Turma: ' . htmlspecialchars($aluno['turma']) . '</span>
            <span class="tag">Período: ' . htmlspecialchars($aluno['periodo']) . '</span>
            <span class="tag">📞 ' . htmlspecialchars($aluno['telefone']) . '</span>
        </div>
    </div>
    
    <table class="tabela-itens">
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th style="text-align:left;">Descrição</th>
                <th style="width:80px;">Mês</th>
                <th style="width:90px;text-align:right;">Valor Base</th>
                <th style="width:60px;text-align:right;">IVA %</th>
                <th style="width:80px;text-align:right;">Valor IVA</th>
                <th style="width:100px;text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>';
    
    $item_num = 1;
    foreach ($itens as $item) {
        $mes_exibicao = ($item['mes_referencia'] ?? '-') != '-' ? ($item['mes_referencia'] ?? '-') : '-';
        $taxa_iva = $item['taxa_iva'] ?? 0;
        $html .= '
            <tr>
                <td class="text-center">' . $item_num . '</td>
                <td>' . htmlspecialchars($item['descricao']) . '</td>
                <td class="text-center">' . $mes_exibicao . '</td>
                <td class="text-right">' . number_format($item['valor_base'] ?? 0, 2, ',', '.') . ' Kz</td>
                <td class="text-right">' . number_format($taxa_iva, 2, ',', '.') . '%</td>
                <td class="text-right">' . number_format($item['valor_iva'] ?? 0, 2, ',', '.') . ' Kz</td>
                <td class="text-right"><strong>' . number_format($item['valor_liquido'] ?? 0, 2, ',', '.') . ' Kz</strong></td>
            </tr>';
        $item_num++;
    }
    
    $html .= '
            <tr class="total-row">
                <td colspan="3" style="text-align:right;font-size:10px;">TOTAIS</td>
                <td class="text-right">' . number_format($total_base, 2, ',', '.') . ' Kz</td>
                <td class="text-right"></td>
                <td class="text-right">' . number_format($total_iva, 2, ',', '.') . ' Kz</td>
                <td class="text-right valor-total">' . number_format($total_liquido, 2, ',', '.') . ' Kz</td>
            </tr>
        </tbody>
    </table>
    
    <div class="resumo-fatura">
        <div class="grid">
            <div class="label">Subtotal</div>
            <div class="value">' . number_format($total_base, 2, ',', '.') . ' Kz</div>
            <div class="label">IVA (' . ($itens[0]['taxa_iva'] ?? 0) . '%)</div>
            <div class="value">' . number_format($total_iva, 2, ',', '.') . ' Kz</div>
            <div class="label" style="border-top:1px solid #ddd;padding-top:4px;"><strong>TOTAL</strong></div>
            <div class="value total">' . number_format($total_liquido, 2, ',', '.') . ' Kz</div>
        </div>
    </div>
    
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;font-size:9px;color:#555;padding:5px 0;border-bottom:1px solid #eee;margin-bottom:8px;">
        <div><strong>💳 Forma:</strong> ' . htmlspecialchars($dados['forma_pagamento'] ?? 'Dinheiro') . '</div>
        <div><strong>📅 Data Pagamento:</strong> ' . date('d/m/Y') . '</div>
        <div><strong>📄 Referência:</strong> ' . htmlspecialchars($dados['referencia'] ?? 'N/A') . '</div>
    </div>
    
    <div class="rodape-fatura">
        <div>
            <p style="margin:1px 0;font-size:8px;">Documento emitido eletronicamente</p>
            <p style="margin:1px 0;font-size:8px;">' . htmlspecialchars($empresa['nome']) . ' © ' . date('Y') . '</p>
            <p style="margin:1px 0;font-size:7px;color:#999;">Regime de IVA: ' . ($empresa['regime_iva'] ?? 'normal') . '</p>
        </div>
        <div class="assinatura">
            <p style="font-size:8px;font-weight:bold;margin:1px 0;">Assinatura do Responsável</p>
            <div class="linha"></div>
            <p style="font-size:7px;color:#999;margin:1px 0;">_________________________________</p>
        </div>
        <div class="info-extra">
            <p style="margin:1px 0;">Impresso em: ' . date('d/m/Y H:i:s') . '</p>
            <p class="hash">Hash: ' . substr($hash, 0, 20) . '...</p>
            <p style="margin:1px 0;font-size:7px;color:#999;">Código: ' . $numero_fatura . '</p>
        </div>
    </div>';
    
    return $html;
}