<?php
// ============================================
// modules/escola/alunos/visualizar_fatura.php
// Visualização da fatura - Suporte a Múltiplos Pagamentos
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// ===== RECEBER IDs =====
$ids = isset($_GET['ids']) ? $_GET['ids'] : '';
$origem = isset($_GET['origem']) ? $_GET['origem'] : '';
$erro = '';
$pagamentos = [];
$fatura_html = '';
$is_multiplo = false;

// ===== BUSCAR DADOS DA EMPRESA =====
function buscarDadosEmpresa($pdo) {
    $dados = [
        'nome' => 'Sistema Escolar',
        'endereco' => '',
        'telefone' => '',
        'celular' => '',
        'email' => '',
        'nif' => '',
        'cidade' => '',
        'estado' => '',
        'cep' => ''
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM empresa WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $empresa = $stmt->fetch();
        
        if ($empresa) {
            $dados['nome'] = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Sistema Escolar';
            
            $endereco_parts = [];
            if (!empty($empresa['endereco'])) $endereco_parts[] = $empresa['endereco'];
            if (!empty($empresa['numero'])) $endereco_parts[] = $empresa['numero'];
            if (!empty($empresa['bairro'])) $endereco_parts[] = $empresa['bairro'];
            if (!empty($empresa['cidade'])) $endereco_parts[] = $empresa['cidade'];
            if (!empty($empresa['estado'])) $endereco_parts[] = $empresa['estado'];
            if (!empty($empresa['cep'])) $endereco_parts[] = 'CEP: ' . $empresa['cep'];
            
            $dados['endereco'] = implode(', ', $endereco_parts);
            $dados['telefone'] = $empresa['telefone'] ?? '';
            $dados['celular'] = $empresa['celular'] ?? '';
            $dados['email'] = $empresa['email'] ?? '';
            $dados['nif'] = $empresa['cnpj'] ?? $empresa['inscricao_estadual'] ?? '';
            $dados['cidade'] = $empresa['cidade'] ?? '';
            $dados['estado'] = $empresa['estado'] ?? '';
            $dados['cep'] = $empresa['cep'] ?? '';
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar dados da empresa: " . $e->getMessage());
    }
    
    return $dados;
}

// ===== PROCESSAR IDs =====
if (!empty($ids)) {
    // Separar IDs por vírgula
    $ids_array = array_map('intval', explode(',', $ids));
    $ids_array = array_filter($ids_array, function($id) { return $id > 0; });
    
    if (empty($ids_array)) {
        $erro = 'Nenhum ID válido informado.';
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
            $sql = "
                SELECT 
                    p.*,
                    a.nome as aluno_nome,
                    a.Classe as aluno_classe,
                    a.TURMA as aluno_turma,
                    a.Periodo as aluno_periodo,
                    a.genero as aluno_genero,
                    e.nome as emolumento_nome,
                    e.valor as emolumento_valor
                FROM pagamentos p
                LEFT JOIN alunos a ON p.aluno_id = a.id
                LEFT JOIN emolumentos e ON p.emolumento_id = e.id
                WHERE p.id IN ($placeholders)
                ORDER BY p.aluno_id, p.data_pagamento DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids_array);
            $pagamentos = $stmt->fetchAll();
            
            if (empty($pagamentos)) {
                $erro = 'Nenhum pagamento encontrado para os IDs informados.';
            } else {
                $is_multiplo = count($pagamentos) > 1;
                $dados_empresa = buscarDadosEmpresa($pdo);
                
                if ($is_multiplo) {
                    // Gerar fatura múltipla consolidada
                    $fatura_html = gerarFaturaMultiplaConsolidada($pagamentos, $dados_empresa);
                } else {
                    // Gerar fatura individual (usando a função existente)
                    $pagamento = $pagamentos[0];
                    
                    // Buscar dados do aluno
                    $aluno = null;
                    if (!empty($pagamento['aluno_id'])) {
                        try {
                            $stmt = $pdo->prepare("SELECT id, nome, Classe, TURMA, Periodo, genero FROM alunos WHERE id = ?");
                            $stmt->execute([$pagamento['aluno_id']]);
                            $aluno = $stmt->fetch();
                        } catch (Exception $e) {}
                    }
                    
                    // Criar item de pagamento
                    $itens_pagamento = [];
                    $nome_emolumento = $pagamento['emolumento_nome'] ?? 'Pagamento Escolar';
                    $itens_pagamento[] = [
                        'item' => $nome_emolumento,
                        'descricao' => $pagamento['observacoes'] ?? $pagamento['referencia'] ?? $nome_emolumento,
                        'quantidade' => 1,
                        'valor_total' => floatval($pagamento['valor'])
                    ];
                    
                    // Adicionar campos extras
                    $pagamento['aluno_nome'] = $aluno['nome'] ?? $pagamento['aluno_nome'] ?? 'N/A';
                    $pagamento['classe'] = $aluno['Classe'] ?? $pagamento['aluno_classe'] ?? 'N/A';
                    $pagamento['turma'] = $aluno['TURMA'] ?? $pagamento['aluno_turma'] ?? 'N/A';
                    $pagamento['periodo'] = $aluno['Periodo'] ?? $pagamento['aluno_periodo'] ?? 'Manhã';
                    $pagamento['genero'] = $aluno['genero'] ?? $pagamento['aluno_genero'] ?? 'M';
                    $pagamento['valor_total'] = floatval($pagamento['valor']);
                    $pagamento['numero_fatura'] = 'FAT-' . str_pad($pagamento['id'], 6, '0', STR_PAD_LEFT);
                    $pagamento['descricao'] = $pagamento['observacoes'] ?? $pagamento['referencia'] ?? $nome_emolumento;
                    
                    if (empty($pagamento['mes_referencia']) || $pagamento['mes_referencia'] == '-') {
                        $pagamento['mes_referencia'] = date('F/Y', strtotime($pagamento['data_pagamento']));
                    }
                    
                    $fatura_html = gerarFaturaIndividual($pagamento, $aluno, $itens_pagamento, $dados_empresa);
                }
            }
            
        } catch (Exception $e) {
            $erro = 'Erro ao buscar pagamentos: ' . $e->getMessage();
        }
    }
} else {
    // Fallback: tentar buscar por ID único (compatibilidade com versões anteriores)
    $pagamento_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($pagamento_id > 0) {
        header('Location: ' . SITE_URL . 'modules/escola/alunos/visualizar_fatura.php?ids=' . $pagamento_id . '&origem=' . $origem);
        exit;
    } else {
        $erro = 'Nenhum pagamento especificado.';
    }
}

// ===== FUNÇÃO PARA GERAR FATURA INDIVIDUAL =====
function gerarFaturaIndividual($pagamento, $aluno, $itens, $dados_empresa) {
    $nome_escola = $dados_empresa['nome'] ?? 'Sistema Escolar';
    $endereco = $dados_empresa['endereco'] ?? '';
    $contacto = $dados_empresa['telefone'] ?? $dados_empresa['celular'] ?? '';
    $email = $dados_empresa['email'] ?? '';
    $nif = $dados_empresa['nif'] ?? '';
    
    $numero_fatura = $pagamento['numero_fatura'] ?? 'FAT-' . str_pad($pagamento['id'], 6, '0', STR_PAD_LEFT);
    $data_pagamento = date('d/m/Y', strtotime($pagamento['data_pagamento']));
    $data_emissao = date('d/m/Y H:i:s');
    $forma_pagamento = ucfirst(str_replace('_', ' ', $pagamento['forma_pagamento'] ?? 'Dinheiro'));
    $referencia = $pagamento['referencia'] ?? '';
    $observacoes = $pagamento['observacoes'] ?? '';
    $mes_referencia = $pagamento['mes_referencia'] ?? '-';
    $status = ucfirst($pagamento['status'] ?? 'Confirmado');
    
    $aluno_nome = $aluno['nome'] ?? $pagamento['aluno_nome'] ?? 'N/A';
    $aluno_classe = $aluno['Classe'] ?? $pagamento['classe'] ?? 'N/A';
    $aluno_turma = $aluno['TURMA'] ?? $pagamento['turma'] ?? 'N/A';
    $aluno_periodo = $aluno['Periodo'] ?? $pagamento['periodo'] ?? 'Manhã';
    $aluno_genero = $aluno['genero'] ?? $pagamento['genero'] ?? 'M';
    $aluno_id = $pagamento['aluno_id'] ?? '';
    
    $total = 0;
    foreach ($itens as $item) {
        $total += $item['valor_total'] ?? 0;
    }

    $html = gerarEstruturaFatura($numero_fatura, $data_emissao, $data_pagamento, $total, $nome_escola, $endereco, $contacto, $email, $nif, $aluno_id, $aluno_nome, $aluno_classe, $aluno_turma, $aluno_periodo, $aluno_genero, $itens, $forma_pagamento, $referencia, $observacoes, $mes_referencia, $status, false);
    
    return $html;
}

// ===== FUNÇÃO PARA GERAR FATURA MÚLTIPLA CONSOLIDADA =====
function gerarFaturaMultiplaConsolidada($pagamentos, $dados_empresa) {
    $nome_escola = $dados_empresa['nome'] ?? 'Sistema Escolar';
    $endereco = $dados_empresa['endereco'] ?? '';
    $contacto = $dados_empresa['telefone'] ?? $dados_empresa['celular'] ?? '';
    $email = $dados_empresa['email'] ?? '';
    $nif = $dados_empresa['nif'] ?? '';
    
    $numero_fatura = 'FAT-CONS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $data_emissao = date('d/m/Y H:i:s');
    
    // Agrupar por aluno
    $alunos_agrupados = [];
    $total_geral = 0;
    $total_pagamentos = count($pagamentos);
    
    foreach ($pagamentos as $p) {
        $aluno_id = $p['aluno_id'] ?? 0;
        if (!isset($alunos_agrupados[$aluno_id])) {
            $alunos_agrupados[$aluno_id] = [
                'aluno_id' => $aluno_id,
                'aluno_nome' => $p['aluno_nome'] ?? 'N/A',
                'aluno_classe' => $p['aluno_classe'] ?? 'N/A',
                'aluno_turma' => $p['aluno_turma'] ?? 'N/A',
                'aluno_periodo' => $p['aluno_periodo'] ?? 'Manhã',
                'aluno_genero' => $p['aluno_genero'] ?? 'M',
                'itens' => [],
                'total_aluno' => 0
            ];
        }
        
        $item = [
            'emolumento' => $p['emolumento_nome'] ?? 'Pagamento',
            'valor' => floatval($p['valor']),
            'data' => date('d/m/Y', strtotime($p['data_pagamento'])),
            'mes_referencia' => $p['mes_referencia'] ?? '-',
            'forma_pagamento' => ucfirst(str_replace('_', ' ', $p['forma_pagamento'] ?? 'Dinheiro')),
            'status' => $p['status'] ?? 'confirmado',
            'referencia' => $p['referencia'] ?? '',
            'id' => $p['id']
        ];
        
        $alunos_agrupados[$aluno_id]['itens'][] = $item;
        $alunos_agrupados[$aluno_id]['total_aluno'] += $item['valor'];
        $total_geral += $item['valor'];
    }
    
    $total_alunos = count($alunos_agrupados);
    
    // Gerar HTML da fatura consolidada
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Fatura Consolidada - ' . $numero_fatura . '</title>
        <style>
            @page { size: A4; margin: 5mm; }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 0; margin: 0; }
            .fatura-page { max-width: 200mm; width: 100%; margin: 0 auto; background: #fff; padding: 5mm; }
            .fatura-container { background: #fff; padding: 4px 6px; border-radius: 3px; margin-bottom: 6px; page-break-inside: avoid; border: 1px solid #ddd; }
            .via-header { padding: 4px 0; text-align: center; font-size: 10px; font-weight: bold; letter-spacing: 2px; border-radius: 3px 3px 0 0; }
            .via-header.cliente { background: #1a2332; color: #fff; }
            .via-header.escola { background: #c9a84c; color: #1a2332; }
            .via-divider { text-align: center; font-size: 11px; font-weight: bold; color: #1a2332; padding: 6px 0; border-top: 2px dashed #ccc; border-bottom: 2px dashed #ccc; margin: 8px 0; }
            .via-divider span { background: #fff; padding: 0 15px; }
            .header-fatura { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 2px double #1a2332; margin-bottom: 6px; }
            .header-fatura .logo { display: flex; align-items: center; gap: 8px; }
            .header-fatura .logo-icon { width: 36px; height: 36px; background: #1a2332; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #c9a84c; font-size: 16px; font-weight: bold; border: 2px solid #c9a84c; }
            .header-fatura .escola-nome { font-size: 14px; font-weight: 800; color: #1a2332; }
            .header-fatura .escola-end { font-size: 8px; color: #666; }
            .header-fatura .fatura-titulo { text-align: right; }
            .header-fatura .fatura-titulo h2 { font-size: 14px; font-weight: 800; color: #c9a84c; letter-spacing: 1px; }
            .header-fatura .fatura-num { font-size: 9px; color: #555; background: #f8fafc; padding: 2px 8px; border-radius: 3px; border: 1px solid #ddd; margin-top: 2px; }
            .info-empresa { display: flex; justify-content: space-between; flex-wrap: wrap; padding: 3px 0; border-bottom: 1px solid #ddd; margin-bottom: 5px; font-size: 9px; }
            .info-empresa .nif-box { background: #1a2332; color: #fff; padding: 1px 10px; border-radius: 3px; font-weight: bold; font-size: 9px; }
            .info-resumo { background: #fef9e8; padding: 4px 8px; border-radius: 3px; margin-bottom: 6px; font-size: 9px; border: 1px solid #fde68a; text-align: center; }
            .info-resumo strong { color: #1a2332; }
            .aluno-section { border: 1px solid #1a2332; padding: 4px 8px; margin-bottom: 8px; background: #fafafa; border-radius: 3px; }
            .aluno-section .aluno-header { font-weight: bold; font-size: 10px; border-bottom: 1px solid #ddd; padding-bottom: 2px; margin-bottom: 4px; color: #1a2332; display: flex; justify-content: space-between; flex-wrap: wrap; }
            .aluno-section .aluno-header .total-aluno { color: #c0392b; }
            .aluno-section .grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 3px; font-size: 9px; margin-bottom: 4px; }
            .tabela-itens { width: 100%; border-collapse: collapse; margin-bottom: 3px; font-size: 8px; }
            .tabela-itens th { background: #1a2332; color: #fff; padding: 2px 4px; text-align: center; border: 1px solid #1a2332; }
            .tabela-itens td { padding: 2px 4px; border: 1px solid #ddd; text-align: center; }
            .tabela-itens .text-left { text-align: left; }
            .tabela-itens .text-right { text-align: right; }
            .tabela-itens .total-row { background: #f8fafc; font-weight: bold; }
            .tabela-itens .total-row td { border-top: 2px solid #1a2332; }
            .tabela-itens .valor-total { color: #c0392b; font-size: 11px; }
            .resumo-geral { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 4px; padding: 4px 8px; background: #1a2332; color: #fff; border-radius: 3px; margin-bottom: 5px; font-size: 9px; }
            .resumo-geral .total-destaque { text-align: right; }
            .resumo-geral .total-destaque .label { font-size: 8px; color: #c9a84c; }
            .resumo-geral .total-destaque .valor { font-size: 18px; font-weight: 800; color: #c9a84c; }
            .resumo-geral .info-item { color: #ccc; }
            .rodape { display: flex; justify-content: space-between; flex-wrap: wrap; padding-top: 4px; border-top: 1px solid #1a2332; font-size: 7px; color: #666; }
            .rodape .assinatura { text-align: center; }
            .rodape .assinatura .linha { width: 120px; border-top: 1px solid #333; margin: 2px auto; }
            .status-badge { display: inline-block; padding: 1px 8px; border-radius: 8px; font-size: 7px; font-weight: bold; }
            .status-confirmado { background: #d1fae5; color: #065f46; }
            .status-pendente { background: #fef3c7; color: #92400e; }
            .status-cancelado { background: #fee2e2; color: #991b1b; }
            .badge-pagamento { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 7px; font-weight: bold; background: #dbeafe; color: #1e40af; }
            @media print {
                body { background: #fff !important; }
                .fatura-page { box-shadow: none !important; padding: 3mm !important; }
                .no-print { display: none !important; }
                .fatura-container * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                table { page-break-inside: avoid !important; }
                tr { page-break-inside: avoid !important; }
            }
            @media screen and (max-width: 768px) {
                .aluno-section .grid { grid-template-columns: 1fr !important; }
                .fatura-page { padding: 3mm; }
            }
        </style>
        <script>
            function imprimirFatura() { setTimeout(function() { window.print(); }, 800); }
            window.onload = function() { if (window.location.search.indexOf("print=1") > -1) { imprimirFatura(); } };
        </script>
    </head>
    <body>
    <div class="fatura-page">';
    
    // VIA DO CLIENTE
    $html .= '<div class="fatura-container">';
    $html .= '<div class="via-header cliente">VIA DO CLIENTE - FATURA CONSOLIDADA</div>';
    $html .= gerarCorpoFaturaMultipla($numero_fatura, $data_emissao, $nome_escola, $endereco, $contacto, $email, $nif, $alunos_agrupados, $total_geral, $total_pagamentos, $total_alunos);
    $html .= '</div>';
    
    // SEPARADOR
    $html .= '<div class="via-divider"><span>✂️ CORTE AQUI ✂️</span></div>';
    
    // VIA DA ESCOLA
    $html .= '<div class="fatura-container" style="border:2px dashed #c9a84c;">';
    $html .= '<div class="via-header escola">VIA DA ESCOLA - FATURA CONSOLIDADA</div>';
    $html .= gerarCorpoFaturaMultipla($numero_fatura, $data_emissao, $nome_escola, $endereco, $contacto, $email, $nif, $alunos_agrupados, $total_geral, $total_pagamentos, $total_alunos);
    $html .= '</div>';
    
    // BOTÕES
    $html .= '
    </div>
    <div class="no-print" style="text-align:center;padding:15px;max-width:200mm;margin:0 auto;">
        <button onclick="window.print()" style="padding:12px 35px;background:#1a2332;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;margin-right:10px;">🖨️ IMPRIMIR</button>
        <button onclick="window.close()" style="padding:12px 35px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">✕ FECHAR</button>
    </div>
    </body></html>';
    
    return $html;
}

// ===== FUNÇÃO PARA GERAR CORPO DA FATURA MÚLTIPLA =====
function gerarCorpoFaturaMultipla($numero_fatura, $data_emissao, $nome_escola, $endereco, $contacto, $email, $nif, $alunos_agrupados, $total_geral, $total_pagamentos, $total_alunos) {
    $html = '
    <div class="header-fatura">
        <div class="logo">
            <div class="logo-icon">🎓</div>
            <div>
                <div class="escola-nome">' . htmlspecialchars($nome_escola) . '</div>
                <div class="escola-end">📍 ' . htmlspecialchars($endereco) . '</div>
            </div>
        </div>
        <div class="fatura-titulo">
            <h2>FATURA CONSOLIDADA</h2>
            <div class="fatura-num"><strong>Nº:</strong> ' . $numero_fatura . '<br><span style="font-size:8px;">Emissão: ' . $data_emissao . '</span></div>
        </div>
    </div>
    
    <div class="info-empresa">
        <div><strong>📞</strong> ' . htmlspecialchars($contacto) . '</div>
        <div><strong>✉</strong> ' . htmlspecialchars($email) . '</div>
        <div class="nif-box">NIF: ' . htmlspecialchars($nif) . '</div>
    </div>
    
    <div class="info-resumo">
        📋 Esta fatura consolida <strong>' . $total_alunos . ' aluno(s)</strong> e <strong>' . $total_pagamentos . ' pagamento(s)</strong>
        <span style="margin-left:15px;">💰 Total Geral: <strong>' . number_format($total_geral, 2, ',', '.') . ' Kz</strong></span>
    </div>';
    
    $aluno_num = 1;
    foreach ($alunos_agrupados as $aluno) {
        $total_itens = count($aluno['itens']);
        $html .= '
    <div class="aluno-section">
        <div class="aluno-header">
            <span>👤 <strong>#' . $aluno['aluno_id'] . ' - ' . htmlspecialchars($aluno['aluno_nome']) . '</strong></span>
            <span>📚 ' . htmlspecialchars($aluno['aluno_classe']) . ' | Turma: ' . htmlspecialchars($aluno['aluno_turma']) . ' | ' . htmlspecialchars($aluno['aluno_periodo']) . '</span>
            <span class="total-aluno">💰 Total: ' . number_format($aluno['total_aluno'], 2, ',', '.') . ' Kz (' . $total_itens . ' itens)</span>
        </div>
        
        <table class="tabela-itens">
            <thead>
                <tr>
                    <th style="width:20px;">#</th>
                    <th style="text-align:left;width:35%;">Descrição</th>
                    <th style="width:15%;">Mês Ref.</th>
                    <th style="width:15%;">Data</th>
                    <th style="width:12%;">Forma</th>
                    <th style="width:15%;text-align:right;">Valor</th>
                </tr>
            </thead>
            <tbody>';
        
        $item_num = 1;
        foreach ($aluno['itens'] as $item) {
            $status_class = 'status-' . ($item['status'] ?? 'confirmado');
            $html .= '
                <tr>
                    <td>' . $item_num . '</td>
                    <td class="text-left">' . htmlspecialchars($item['emolumento']) . 
                    (!empty($item['referencia']) ? ' <span style="color:#666;font-size:7px;">(Ref: ' . htmlspecialchars($item['referencia']) . ')</span>' : '') . '</td>
                    <td>' . htmlspecialchars($item['mes_referencia']) . '</td>
                    <td>' . $item['data'] . '</td>
                    <td><span class="badge-pagamento">' . $item['forma_pagamento'] . '</span></td>
                    <td class="text-right"><strong>' . number_format($item['valor'], 2, ',', '.') . ' Kz</strong></td>
                </tr>';
            $item_num++;
        }
        
        $html .= '
                <tr class="total-row">
                    <td colspan="5" style="text-align:right;font-size:9px;">SUBTOTAL ' . htmlspecialchars($aluno['aluno_nome']) . '</td>
                    <td class="text-right valor-total">' . number_format($aluno['total_aluno'], 2, ',', '.') . ' Kz</td>
                </tr>
            </tbody>
        </table>
    </div>';
        $aluno_num++;
    }
    
    $html .= '
    <div class="resumo-geral">
        <div>
            <div class="info-item">👤 Total de Alunos: <strong>' . count($alunos_agrupados) . '</strong></div>
            <div class="info-item">📄 Total de Pagamentos: <strong>' . $total_pagamentos . '</strong></div>
            <div class="info-item">📅 Emitido em: ' . date('d/m/Y H:i:s') . '</div>
        </div>
        <div class="total-destaque">
            <div class="label">TOTAL GERAL</div>
            <div class="valor">' . number_format($total_geral, 2, ',', '.') . ' Kz</div>
        </div>
    </div>
    
    <div class="rodape">
        <div>
            <p style="margin:1px 0;">Documento emitido eletronicamente</p>
            <p style="margin:1px 0;">' . htmlspecialchars($nome_escola) . ' © ' . date('Y') . '</p>
            <p style="margin:1px 0;">Fatura consolidada de múltiplos pagamentos</p>
        </div>
        <div class="assinatura">
            <p style="font-size:8px;font-weight:bold;margin:1px 0;">Assinatura do Responsável</p>
            <div class="linha"></div>
            <p style="font-size:7px;color:#999;margin:1px 0;">_________________________________</p>
        </div>
        <div style="text-align:right;font-size:7px;color:#999;">
            <p style="margin:1px 0;">Impresso em: ' . date('d/m/Y H:i:s') . '</p>
        </div>
    </div>';
    
    return $html;
}

// ===== FUNÇÃO PARA GERAR ESTRUTURA DA FATURA (INDIVIDUAL) =====
function gerarEstruturaFatura($numero_fatura, $data_emissao, $data_pagamento, $total, $nome_escola, $endereco, $contacto, $email, $nif, $aluno_id, $aluno_nome, $aluno_classe, $aluno_turma, $aluno_periodo, $aluno_genero, $itens, $forma_pagamento, $referencia, $observacoes, $mes_referencia, $status) {
    
    $status_class = 'status-' . strtolower($status);
    if ($status_class == 'status-pago') $status_class = 'status-confirmado';
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Fatura - ' . $numero_fatura . '</title>
        <style>
            @page { size: A4; margin: 5mm; }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 0; margin: 0; }
            .fatura-page { max-width: 200mm; width: 100%; margin: 0 auto; background: #fff; padding: 5mm; }
            .fatura-container { background: #fff; padding: 4px 6px; border-radius: 3px; margin-bottom: 6px; page-break-inside: avoid; border: 1px solid #ddd; }
            .via-header { padding: 4px 0; text-align: center; font-size: 10px; font-weight: bold; letter-spacing: 2px; border-radius: 3px 3px 0 0; }
            .via-header.cliente { background: #1a2332; color: #fff; }
            .via-header.escola { background: #c9a84c; color: #1a2332; }
            .via-divider { text-align: center; font-size: 11px; font-weight: bold; color: #1a2332; padding: 6px 0; border-top: 2px dashed #ccc; border-bottom: 2px dashed #ccc; margin: 8px 0; }
            .via-divider span { background: #fff; padding: 0 15px; }
            .header-fatura { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 2px double #1a2332; margin-bottom: 6px; }
            .header-fatura .logo { display: flex; align-items: center; gap: 8px; }
            .header-fatura .logo-icon { width: 36px; height: 36px; background: #1a2332; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #c9a84c; font-size: 16px; font-weight: bold; border: 2px solid #c9a84c; }
            .header-fatura .escola-nome { font-size: 14px; font-weight: 800; color: #1a2332; }
            .header-fatura .escola-end { font-size: 8px; color: #666; }
            .header-fatura .fatura-titulo { text-align: right; }
            .header-fatura .fatura-titulo h2 { font-size: 14px; font-weight: 800; color: #c9a84c; letter-spacing: 1px; }
            .header-fatura .fatura-num { font-size: 9px; color: #555; background: #f8fafc; padding: 2px 8px; border-radius: 3px; border: 1px solid #ddd; margin-top: 2px; }
            .info-empresa { display: flex; justify-content: space-between; flex-wrap: wrap; padding: 3px 0; border-bottom: 1px solid #ddd; margin-bottom: 5px; font-size: 9px; }
            .info-empresa .nif-box { background: #1a2332; color: #fff; padding: 1px 10px; border-radius: 3px; font-weight: bold; font-size: 9px; }
            .dados-aluno { border: 1px solid #1a2332; padding: 4px 8px; margin-bottom: 5px; background: #fafafa; border-radius: 3px; }
            .dados-aluno .titulo { font-weight: bold; font-size: 10px; text-align: center; border-bottom: 1px solid #1a2332; padding-bottom: 2px; margin-bottom: 4px; color: #1a2332; }
            .dados-aluno .grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 3px; font-size: 9px; }
            .tabela-itens { width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 8px; }
            .tabela-itens th { background: #1a2332; color: #fff; padding: 3px 4px; text-align: center; border: 1px solid #1a2332; }
            .tabela-itens td { padding: 3px 4px; border: 1px solid #ddd; text-align: center; }
            .tabela-itens .text-left { text-align: left; }
            .tabela-itens .text-right { text-align: right; }
            .tabela-itens .total-row { background: #f8fafc; font-weight: bold; }
            .tabela-itens .total-row td { border-top: 2px solid #1a2332; }
            .tabela-itens .valor-total { color: #c0392b; font-size: 12px; }
            .resumo-fatura { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 4px; padding: 4px 8px; background: #f8fafc; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 5px; font-size: 8px; }
            .resumo-fatura .total-destaque { text-align: right; border-left: 2px solid #c9a84c; padding-left: 10px; }
            .resumo-fatura .total-destaque .label { font-size: 8px; color: #666; }
            .resumo-fatura .total-destaque .valor { font-size: 16px; font-weight: 800; color: #c0392b; }
            .rodape { display: flex; justify-content: space-between; flex-wrap: wrap; padding-top: 4px; border-top: 1px solid #1a2332; font-size: 7px; color: #666; }
            .rodape .assinatura { text-align: center; }
            .rodape .assinatura .linha { width: 120px; border-top: 1px solid #333; margin: 2px auto; }
            .status-badge { display: inline-block; padding: 1px 10px; border-radius: 10px; font-size: 8px; font-weight: bold; }
            .status-confirmado { background: #d1fae5; color: #065f46; }
            .status-pendente { background: #fef3c7; color: #92400e; }
            .status-cancelado { background: #fee2e2; color: #991b1b; }
            @media print {
                body { background: #fff !important; }
                .fatura-page { box-shadow: none !important; padding: 3mm !important; }
                .no-print { display: none !important; }
                .fatura-container * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                table { page-break-inside: avoid !important; }
                tr { page-break-inside: avoid !important; }
            }
            @media screen and (max-width: 768px) {
                .dados-aluno .grid { grid-template-columns: 1fr !important; }
                .fatura-page { padding: 3mm; }
            }
        </style>
        <script>
            function imprimirFatura() { setTimeout(function() { window.print(); }, 800); }
            window.onload = function() { if (window.location.search.indexOf("print=1") > -1) { imprimirFatura(); } };
        </script>
    </head>
    <body>
    <div class="fatura-page">';
    
    // VIA DO CLIENTE
    $html .= '<div class="fatura-container">';
    $html .= '<div class="via-header cliente">VIA DO CLIENTE</div>';
    $html .= gerarCorpoFaturaIndividual($numero_fatura, $data_emissao, $data_pagamento, $total, $nome_escola, $endereco, $contacto, $email, $nif, $aluno_id, $aluno_nome, $aluno_classe, $aluno_turma, $aluno_periodo, $aluno_genero, $itens, $forma_pagamento, $referencia, $observacoes, $mes_referencia, $status);
    $html .= '</div>';
    
    // SEPARADOR
    $html .= '<div class="via-divider"><span>✂️ CORTE AQUI ✂️</span></div>';
    
    // VIA DA ESCOLA
    $html .= '<div class="fatura-container" style="border:2px dashed #c9a84c;">';
    $html .= '<div class="via-header escola">VIA DA ESCOLA</div>';
    $html .= gerarCorpoFaturaIndividual($numero_fatura, $data_emissao, $data_pagamento, $total, $nome_escola, $endereco, $contacto, $email, $nif, $aluno_id, $aluno_nome, $aluno_classe, $aluno_turma, $aluno_periodo, $aluno_genero, $itens, $forma_pagamento, $referencia, $observacoes, $mes_referencia, $status);
    $html .= '</div>';
    
    // BOTÕES
    $html .= '
    </div>
    <div class="no-print" style="text-align:center;padding:15px;max-width:200mm;margin:0 auto;">
        <button onclick="window.print()" style="padding:12px 35px;background:#1a2332;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;margin-right:10px;">🖨️ IMPRIMIR</button>
        <button onclick="window.close()" style="padding:12px 35px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">✕ FECHAR</button>
    </div>
    </body></html>';
    
    return $html;
}

// ===== FUNÇÃO PARA GERAR CORPO DA FATURA INDIVIDUAL =====
function gerarCorpoFaturaIndividual($numero_fatura, $data_emissao, $data_pagamento, $total, $nome_escola, $endereco, $contacto, $email, $nif, $aluno_id, $aluno_nome, $aluno_classe, $aluno_turma, $aluno_periodo, $aluno_genero, $itens, $forma_pagamento, $referencia, $observacoes, $mes_referencia, $status) {
    
    $status_class = 'status-' . strtolower($status);
    if ($status_class == 'status-pago') $status_class = 'status-confirmado';
    
    $html = '
    <div class="header-fatura">
        <div class="logo">
            <div class="logo-icon">🎓</div>
            <div>
                <div class="escola-nome">' . htmlspecialchars($nome_escola) . '</div>
                <div class="escola-end">📍 ' . htmlspecialchars($endereco) . '</div>
            </div>
        </div>
        <div class="fatura-titulo">
            <h2>FATURA</h2>
            <div class="fatura-num"><strong>Nº:</strong> ' . $numero_fatura . '<br><span style="font-size:8px;">Emissão: ' . $data_emissao . '</span></div>
        </div>
    </div>
    
    <div class="info-empresa">
        <div><strong>📞</strong> ' . htmlspecialchars($contacto) . '</div>
        <div><strong>✉</strong> ' . htmlspecialchars($email) . '</div>
        <div class="nif-box">NIF: ' . htmlspecialchars($nif) . '</div>
    </div>
    
    <div class="dados-aluno">
        <div class="titulo">📋 DADOS DO ALUNO</div>
        <div class="grid">
            <div><strong>Nome:</strong> ' . htmlspecialchars($aluno_nome) . '</div>
            <div><strong>ID:</strong> #' . htmlspecialchars($aluno_id) . '</div>
            <div><strong>Classe:</strong> ' . htmlspecialchars($aluno_classe) . '</div>
            <div><strong>Turma:</strong> ' . htmlspecialchars($aluno_turma) . '</div>
            <div><strong>Período:</strong> ' . htmlspecialchars($aluno_periodo) . '</div>
            <div><strong>Gênero:</strong> ' . ($aluno_genero == 'M' ? 'Masculino' : 'Feminino') . '</div>
        </div>
    </div>
    
    <table class="tabela-itens">
        <thead>
            <tr>
                <th style="width:25px;">#</th>
                <th style="text-align:left;">Descrição</th>
                <th style="width:20%;">Mês Referência</th>
                <th style="width:20%;text-align:right;">Valor</th>
            </tr>
        </thead>
        <tbody>';
    
    $item_num = 1;
    foreach ($itens as $item) {
        $descricao = $item['item'] ?? $item['descricao'] ?? 'Pagamento';
        $valor = $item['valor_total'] ?? 0;
        $mes_exibicao = $mes_referencia ?? '-';
        
        $html .= '
            <tr>
                <td>' . $item_num . '</td>
                <td class="text-left">' . htmlspecialchars($descricao) . '</td>
                <td>' . htmlspecialchars($mes_exibicao) . '</td>
                <td class="text-right"><strong>' . number_format($valor, 2, ',', '.') . ' Kz</strong></td>
            </tr>';
        $item_num++;
    }
    
    $html .= '
            <tr class="total-row">
                <td colspan="3" style="text-align:right;font-size:10px;">TOTAL</td>
                <td class="text-right valor-total">' . number_format($total, 2, ',', '.') . ' Kz</td>
            </tr>
        </tbody>
    </table>
    
    <div class="resumo-fatura">
        <div>
            <div><strong>📅 Data:</strong> ' . $data_pagamento . '</div>
            <div><strong>💳 Forma:</strong> ' . htmlspecialchars($forma_pagamento) . '</div>
            ' . ($referencia ? '<div><strong>📄 Ref.:</strong> ' . htmlspecialchars($referencia) . '</div>' : '') . '
            <div><strong>📆 Mês Ref.:</strong> ' . htmlspecialchars($mes_referencia) . '</div>
        </div>
        <div>
            <div><strong>✅ Status:</strong> <span class="status-badge ' . $status_class . '">' . $status . '</span></div>
            <div><strong>📦 Itens:</strong> ' . count($itens) . '</div>
            ' . ($observacoes ? '<div><strong>📝 Obs.:</strong> ' . htmlspecialchars($observacoes) . '</div>' : '') . '
        </div>
        <div class="total-destaque">
            <div class="label">VALOR TOTAL</div>
            <div class="valor">' . number_format($total, 2, ',', '.') . ' Kz</div>
        </div>
    </div>
    
    <div class="rodape">
        <div>
            <p style="margin:1px 0;">Documento emitido eletronicamente</p>
            <p style="margin:1px 0;">' . htmlspecialchars($nome_escola) . ' © ' . date('Y') . '</p>
        </div>
        <div class="assinatura">
            <p style="font-size:8px;font-weight:bold;margin:1px 0;">Assinatura do Responsável</p>
            <div class="linha"></div>
            <p style="font-size:7px;color:#999;margin:1px 0;">_________________________________</p>
        </div>
        <div style="text-align:right;font-size:7px;color:#999;">
            <p style="margin:1px 0;">Impresso em: ' . date('d/m/Y H:i:s') . '</p>
        </div>
    </div>';
    
    return $html;
}

include '../includes/header_escola.php';
?>

<style>
    .container-fatura {
        max-width: 210mm;
        margin: 0 auto;
        padding: 20px;
    }
    
    .fatura-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
        margin-top: 20px;
        padding: 20px;
        background: #fff;
        border-radius: 12px;
        border: 1px solid #eef2f7;
    }
    
    .btn-voltar {
        background: #f1f5f9;
        color: #4a5568;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
    }
    
    .btn-voltar:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }
    
    .btn-imprimir {
        background: #1a2332;
        color: #fff;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 14px;
        display: inline-block;
    }
    
    .btn-imprimir:hover {
        background: #2d3748;
        transform: translateY(-2px);
    }
    
    .btn-pdf {
        background: #c9a84c;
        color: #1a2332;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 14px;
        display: inline-block;
    }
    
    .btn-pdf:hover {
        background: #b8973d;
        transform: translateY(-2px);
    }
    
    .fatura-iframe {
        width: 100%;
        height: 900px;
        border: 1px solid #eef2f7;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .notification {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .notification-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .notification-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .notification .icon {
        font-size: 24px;
    }
    
    .info-multipla {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .info-multipla strong {
        font-size: 16px;
    }
    
    @media (max-width: 768px) {
        .container-fatura {
            padding: 10px;
        }
        .fatura-iframe {
            height: 600px;
        }
        .fatura-actions {
            flex-direction: column;
            align-items: stretch;
        }
        .fatura-actions .btn {
            text-align: center;
            justify-content: center;
        }
        .info-multipla {
            flex-direction: column;
            text-align: center;
        }
    }
    
    @media print {
        .no-print {
            display: none !important;
        }
        .fatura-iframe {
            height: auto !important;
            border: none !important;
            box-shadow: none !important;
        }
        body {
            background: #fff !important;
        }
        .container-fatura {
            padding: 0 !important;
        }
    }
</style>

<div class="container-fatura">
    
    <?php if ($erro): ?>
    <div class="notification notification-error">
        <span class="icon">❌</span>
        <div><?= $erro ?></div>
    </div>
    <div style="text-align: center; margin-top: 20px;">
        <a href="<?= $origem == 'pagamentos' ? SITE_URL . 'modules/escola/financeiro/relatorios/pagamentos.php' : 'javascript:history.back()' ?>" class="btn-voltar">← Voltar</a>
    </div>
    <?php else: ?>
    
    <!-- INFO MÚLTIPLA -->
    <?php if ($is_multiplo): ?>
    <div class="info-multipla no-print">
        <div>
            <span class="icon">📋</span>
            <strong><?= count($pagamentos) ?> pagamentos consolidados</strong>
            <span style="margin-left:10px;">Total: <?= number_format(array_sum(array_column($pagamentos, 'valor')), 2, ',', '.') ?> Kz</span>
        </div>
        <div style="font-size:13px;color:#1e40af;">
            <?php 
            $alunos_unicos = array_unique(array_column($pagamentos, 'aluno_id'));
            echo count($alunos_unicos) . ' aluno(s) envolvido(s)';
            ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- FATURA -->
    <div class="fatura-container">
        <?php if (isset($fatura_html) && !empty($fatura_html)): ?>
        <iframe class="fatura-iframe" srcdoc="<?= htmlspecialchars($fatura_html) ?>"></iframe>
        <?php else: ?>
        <div class="notification notification-error">
            <span class="icon">❌</span>
            <div>Não foi possível gerar a fatura.</div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- AÇÕES -->
    <div class="fatura-actions no-print">
        <a href="<?= $origem == 'pagamentos' ? SITE_URL . 'modules/escola/financeiro/relatorios/pagamentos.php' : 'javascript:history.back()' ?>" class="btn-voltar">← Voltar</a>
        <button onclick="imprimirFatura()" class="btn-imprimir">🖨️ Imprimir Fatura</button>
        <button onclick="baixarPDF()" class="btn-pdf">📥 Baixar PDF</button>
    </div>
    
    <?php endif; ?>
</div>

<script>
function imprimirFatura() {
    const iframe = document.querySelector('.fatura-iframe');
    if (iframe) {
        try {
            const iframeWindow = iframe.contentWindow || iframe.contentDocument;
            if (iframeWindow) {
                iframeWindow.print();
                return;
            }
        } catch (e) {}
        // Fallback: abrir em nova janela
        const conteudo = iframe.srcdoc || iframe.contentDocument?.documentElement?.outerHTML || '';
        const janela = window.open('', '_blank', 'width=800,height=600');
        if (janela) {
            janela.document.write(conteudo);
            janela.document.close();
            janela.focus();
            setTimeout(() => { janela.print(); }, 500);
        }
    }
}

function baixarPDF() {
    const iframe = document.querySelector('.fatura-iframe');
    if (iframe) {
        try {
            const conteudo = iframe.srcdoc || iframe.contentDocument?.documentElement?.outerHTML || '';
            const janela = window.open('', '_blank', 'width=800,height=600');
            if (janela) {
                janela.document.write(conteudo);
                janela.document.close();
                janela.focus();
                setTimeout(() => {
                    janela.print();
                }, 1000);
            }
        } catch (e) {
            alert('Erro ao gerar PDF. Utilize a opção Imprimir e selecione "Salvar como PDF".');
        }
    }
}

// Atalho de teclado Ctrl+P para imprimir
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        imprimirFatura();
    }
});
</script>

<?php include '../includes/footer_escola.php'; ?>