<?php
// ============================================
// modules/escola/financeiro/relatorios/pagamentos.php
// Relatório de Pagamentos - VERSÃO CORRIGIDA
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

// ===== CONFIGURAÇÃO DE CODIFICAÇÃO =====
$pdo->exec("SET NAMES utf8mb4");
$pdo->exec("SET CHARACTER SET utf8mb4");

// ===== FILTROS COM VALIDAÇÃO =====
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$filtro_aluno = isset($_GET['aluno_id']) ? intval($_GET['aluno_id']) : 0;
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_forma = isset($_GET['forma']) ? $_GET['forma'] : '';
$filtro_classe = isset($_GET['classe']) ? $_GET['classe'] : '';
$filtro_turma = isset($_GET['turma']) ? $_GET['turma'] : '';
$filtro_emolumentos = isset($_GET['emolumentos']) ? $_GET['emolumentos'] : [];
$ordenar_alfabetico = isset($_GET['ordenar_alfabetico']) ? $_GET['ordenar_alfabetico'] : '0';

// Validar e sanitizar filtros
$filtro_aluno = max(0, intval($filtro_aluno));
$filtro_emolumentos = array_map('intval', $filtro_emolumentos);
$filtro_emolumentos = array_filter($filtro_emolumentos, function($v) { return $v > 0; });

// ===== AÇÃO: GERAR FATURA MÚLTIPLA =====
$fatura_multipla_html = '';
$mostrar_fatura_multipla = false;
$erro_fatura = '';

if (isset($_POST['gerar_fatura_multipla']) && isset($_POST['pagamentos_selecionados'])) {
    $ids_selecionados = $_POST['pagamentos_selecionados'];
    
    // Validar IDs
    if (!empty($ids_selecionados) && is_array($ids_selecionados)) {
        $ids_selecionados = array_map('intval', $ids_selecionados);
        $ids_selecionados = array_filter($ids_selecionados, function($v) { return $v > 0; });
        
        if (!empty($ids_selecionados)) {
            try {
                $placeholders = implode(',', array_fill(0, count($ids_selecionados), '?'));
                $sql = "
                    SELECT 
                        p.*,
                        a.nome as aluno_nome,
                        a.Classe as aluno_classe,
                        a.TURMA as aluno_turma,
                        a.Periodo as aluno_periodo,
                        e.nome as emolumento_nome
                    FROM pagamentos p
                    LEFT JOIN alunos a ON p.aluno_id = a.id
                    LEFT JOIN emolumentos e ON p.emolumento_id = e.id
                    WHERE p.id IN ($placeholders)
                    ORDER BY p.aluno_id, p.data_pagamento
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($ids_selecionados);
                $pagamentos_selecionados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($pagamentos_selecionados)) {
                    $fatura_multipla_html = gerarFaturaMultipla($pagamentos_selecionados, $pdo);
                    $mostrar_fatura_multipla = true;
                }
            } catch (Exception $e) {
                $erro_fatura = 'Erro ao gerar fatura: ' . $e->getMessage();
            }
        }
    }
}

// ===== FUNÇÃO PARA GERAR FATURA MÚLTIPLA =====
function gerarFaturaMultipla($pagamentos, $pdo) {
    // Buscar dados da empresa
    $dados_empresa = buscarDadosEmpresa($pdo);
    
    $nome_escola = $dados_empresa['nome'] ?? 'Sistema Escolar';
    $endereco = $dados_empresa['endereco'] ?? '';
    $contacto = $dados_empresa['telefone'] ?? $dados_empresa['celular'] ?? '';
    $email = $dados_empresa['email'] ?? '';
    $nif = $dados_empresa['nif'] ?? '';
    
    // Número de fatura único
    $numero_fatura = 'FAT-MULTI-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT) . '-' . uniqid();
    $data_emissao = date('d/m/Y H:i:s');
    
    // Agrupar por aluno
    $alunos_agrupados = [];
    $total_geral = 0;
    
    foreach ($pagamentos as $p) {
        $aluno_id = $p['aluno_id'] ?? 0;
        if (!isset($alunos_agrupados[$aluno_id])) {
            $alunos_agrupados[$aluno_id] = [
                'aluno_id' => $aluno_id,
                'aluno_nome' => htmlspecialchars($p['aluno_nome'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'aluno_classe' => htmlspecialchars($p['aluno_classe'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'aluno_turma' => htmlspecialchars($p['aluno_turma'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'aluno_periodo' => htmlspecialchars($p['aluno_periodo'] ?? 'Manhã', ENT_QUOTES, 'UTF-8'),
                'itens' => [],
                'total_aluno' => 0
            ];
        }
        
        $item = [
            'emolumento' => htmlspecialchars($p['emolumento_nome'] ?? 'Pagamento', ENT_QUOTES, 'UTF-8'),
            'valor' => floatval($p['valor']),
            'data' => date('d/m/Y', strtotime($p['data_pagamento'])),
            'mes_referencia' => htmlspecialchars($p['mes_referencia'] ?? '-', ENT_QUOTES, 'UTF-8'),
            'forma_pagamento' => ucfirst(htmlspecialchars($p['forma_pagamento'] ?? 'Dinheiro', ENT_QUOTES, 'UTF-8')),
            'status' => $p['status'] ?? 'confirmado'
        ];
        
        $alunos_agrupados[$aluno_id]['itens'][] = $item;
        $alunos_agrupados[$aluno_id]['total_aluno'] += $item['valor'];
        $total_geral += $item['valor'];
    }

    // Gerar HTML da fatura
    $html = gerarHTMLFatura($numero_fatura, $data_emissao, $nome_escola, $endereco, $contacto, $email, $nif, $alunos_agrupados, $total_geral);
    
    return $html;
}

// ===== FUNÇÃO PARA GERAR HTML DA FATURA =====
function gerarHTMLFatura($numero_fatura, $data_emissao, $nome_escola, $endereco, $contacto, $email, $nif, $alunos_agrupados, $total_geral) {
    $html = '<!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Fatura Consolidada - ' . $numero_fatura . '</title>
        <style>
            @page { 
                size: A4; 
                margin: 5mm; 
            }
            
            * { 
                margin: 0; 
                padding: 0; 
                box-sizing: border-box; 
            }
            
            body { 
                font-family: Arial, sans-serif; 
                background: #f0f0f0; 
                padding: 0; 
                margin: 0; 
            }
            
            .fatura-page { 
                max-width: 200mm; 
                width: 100%; 
                margin: 0 auto; 
                background: #fff; 
                padding: 5mm; 
            }
            
            .fatura-container { 
                background: #fff; 
                padding: 4px 6px; 
                border-radius: 3px; 
                margin-bottom: 6px; 
                page-break-inside: avoid; 
                border: 1px solid #ddd; 
            }
            
            .via-header { 
                padding: 4px 0; 
                text-align: center; 
                font-size: 10px; 
                font-weight: bold; 
                letter-spacing: 2px; 
                border-radius: 3px 3px 0 0; 
            }
            
            .via-header.cliente { 
                background: #1a2332; 
                color: #fff; 
            }
            
            .via-header.escola { 
                background: #c9a84c; 
                color: #1a2332; 
            }
            
            .via-divider { 
                text-align: center; 
                font-size: 11px; 
                font-weight: bold; 
                color: #1a2332; 
                padding: 6px 0; 
                border-top: 2px dashed #ccc; 
                border-bottom: 2px dashed #ccc; 
                margin: 8px 0; 
            }
            
            .via-divider span { 
                background: #fff; 
                padding: 0 15px; 
            }
            
            .header-fatura { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 4px 0; 
                border-bottom: 2px double #1a2332; 
                margin-bottom: 6px; 
            }
            
            .header-fatura .logo { 
                display: flex; 
                align-items: center; 
                gap: 8px; 
            }
            
            .header-fatura .logo-icon { 
                width: 36px; 
                height: 36px; 
                background: #1a2332; 
                border-radius: 50%; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                color: #c9a84c; 
                font-size: 16px; 
                font-weight: bold; 
                border: 2px solid #c9a84c; 
            }
            
            .header-fatura .escola-nome { 
                font-size: 14px; 
                font-weight: 800; 
                color: #1a2332; 
            }
            
            .header-fatura .escola-end { 
                font-size: 8px; 
                color: #666; 
            }
            
            .header-fatura .fatura-titulo { 
                text-align: right; 
            }
            
            .header-fatura .fatura-titulo h2 { 
                font-size: 14px; 
                font-weight: 800; 
                color: #c9a84c; 
                letter-spacing: 1px; 
            }
            
            .header-fatura .fatura-num { 
                font-size: 9px; 
                color: #555; 
                background: #f8fafc; 
                padding: 2px 8px; 
                border-radius: 3px; 
                border: 1px solid #ddd; 
                margin-top: 2px; 
            }
            
            .info-empresa { 
                display: flex; 
                justify-content: space-between; 
                flex-wrap: wrap; 
                padding: 3px 0; 
                border-bottom: 1px solid #ddd; 
                margin-bottom: 5px; 
                font-size: 9px; 
            }
            
            .info-empresa .nif-box { 
                background: #1a2332; 
                color: #fff; 
                padding: 1px 10px; 
                border-radius: 3px; 
                font-weight: bold; 
                font-size: 9px; 
            }
            
            .aluno-section { 
                border: 1px solid #1a2332; 
                padding: 4px 8px; 
                margin-bottom: 8px; 
                background: #fafafa; 
                border-radius: 3px; 
            }
            
            .aluno-section .aluno-header { 
                font-weight: bold; 
                font-size: 10px; 
                border-bottom: 1px solid #ddd; 
                padding-bottom: 2px; 
                margin-bottom: 4px; 
                color: #1a2332; 
                display: flex; 
                justify-content: space-between; 
                flex-wrap: wrap; 
            }
            
            .aluno-section .aluno-header .total-aluno { 
                color: #c0392b; 
            }
            
            .aluno-section .grid { 
                display: grid; 
                grid-template-columns: 1fr 1fr 1fr; 
                gap: 3px; 
                font-size: 9px; 
                margin-bottom: 4px; 
            }
            
            .tabela-itens { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 3px; 
                font-size: 8px; 
            }
            
            .tabela-itens th { 
                background: #1a2332; 
                color: #fff; 
                padding: 2px 4px; 
                text-align: center; 
                border: 1px solid #1a2332; 
            }
            
            .tabela-itens td { 
                padding: 2px 4px; 
                border: 1px solid #ddd; 
                text-align: center; 
            }
            
            .tabela-itens .text-left { 
                text-align: left; 
            }
            
            .tabela-itens .text-right { 
                text-align: right; 
            }
            
            .tabela-itens .total-row { 
                background: #f8fafc; 
                font-weight: bold; 
            }
            
            .tabela-itens .total-row td { 
                border-top: 2px solid #1a2332; 
            }
            
            .tabela-itens .valor-total { 
                color: #c0392b; 
                font-size: 11px; 
            }
            
            .resumo-geral { 
                display: flex; 
                justify-content: space-between; 
                flex-wrap: wrap; 
                gap: 4px; 
                padding: 4px 8px; 
                background: #1a2332; 
                color: #fff; 
                border-radius: 3px; 
                margin-bottom: 5px; 
                font-size: 9px; 
            }
            
            .resumo-geral .total-destaque { 
                text-align: right; 
            }
            
            .resumo-geral .total-destaque .label { 
                font-size: 8px; 
                color: #c9a84c; 
            }
            
            .resumo-geral .total-destaque .valor { 
                font-size: 18px; 
                font-weight: 800; 
                color: #c9a84c; 
            }
            
            .resumo-geral .info-item { 
                color: #ccc; 
            }
            
            .rodape { 
                display: flex; 
                justify-content: space-between; 
                flex-wrap: wrap; 
                padding-top: 4px; 
                border-top: 1px solid #1a2332; 
                font-size: 7px; 
                color: #666; 
            }
            
            .rodape .assinatura { 
                text-align: center; 
            }
            
            .rodape .assinatura .linha { 
                width: 120px; 
                border-top: 1px solid #333; 
                margin: 2px auto; 
            }
            
            .status-badge { 
                display: inline-block; 
                padding: 1px 8px; 
                border-radius: 8px; 
                font-size: 7px; 
                font-weight: bold; 
            }
            
            .status-confirmado { 
                background: #d1fae5; 
                color: #065f46; 
            }
            
            .status-pendente { 
                background: #fef3c7; 
                color: #92400e; 
            }
            
            @media print {
                body { 
                    background: #fff !important; 
                }
                .fatura-page { 
                    box-shadow: none !important; 
                    padding: 3mm !important; 
                }
                .no-print { 
                    display: none !important; 
                }
                .fatura-container * { 
                    -webkit-print-color-adjust: exact !important; 
                    print-color-adjust: exact !important; 
                }
                table { 
                    page-break-inside: avoid !important; 
                }
                tr { 
                    page-break-inside: avoid !important; 
                }
            }
            
            @media screen and (max-width: 768px) {
                .aluno-section .grid { 
                    grid-template-columns: 1fr !important; 
                }
                .fatura-page { 
                    padding: 3mm; 
                }
            }
        </style>
        <script>
            function imprimirFatura() { 
                setTimeout(function() { 
                    window.print(); 
                }, 800); 
            }
            window.onload = function() { 
                if (window.location.search.indexOf("print=1") > -1) { 
                    imprimirFatura(); 
                } 
            };
        </script>
    </head>
    <body>
    <div class="fatura-page">';
    
    // VIA DO CLIENTE
    $html .= '<div class="fatura-container">';
    $html .= '<div class="via-header cliente">VIA DO CLIENTE - FATURA CONSOLIDADA</div>';
    $html .= gerarCorpoFatura($numero_fatura, $data_emissao, $nome_escola, $endereco, $contacto, $email, $nif, $alunos_agrupados, $total_geral);
    $html .= '</div>';
    
    // SEPARADOR
    $html .= '<div class="via-divider"><span>✂️ CORTE AQUI ✂️</span></div>';
    
    // VIA DA ESCOLA
    $html .= '<div class="fatura-container" style="border:2px dashed #c9a84c;">';
    $html .= '<div class="via-header escola">VIA DA ESCOLA - FATURA CONSOLIDADA</div>';
    $html .= gerarCorpoFatura($numero_fatura, $data_emissao, $nome_escola, $endereco, $contacto, $email, $nif, $alunos_agrupados, $total_geral);
    $html .= '</div>';
    
    $html .= '
    </div>
    <div class="no-print" style="text-align:center;padding:15px;max-width:200mm;margin:0 auto;">
        <button onclick="window.print()" style="padding:12px 35px;background:#1a2332;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;margin-right:10px;">🖨️ IMPRIMIR</button>
        <button onclick="window.close()" style="padding:12px 35px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">✕ FECHAR</button>
    </div>
    </body></html>';
    
    return $html;
}

// ===== FUNÇÃO PARA GERAR CORPO DA FATURA =====
function gerarCorpoFatura($numero_fatura, $data_emissao, $nome_escola, $endereco, $contacto, $email, $nif, $alunos_agrupados, $total_geral) {
    $html = '<div class="header-fatura">
        <div class="logo">
            <div class="logo-icon">🎓</div>
            <div>
                <div class="escola-nome">' . htmlspecialchars($nome_escola, ENT_QUOTES, 'UTF-8') . '</div>
                <div class="escola-end">' . htmlspecialchars($endereco, ENT_QUOTES, 'UTF-8') . '</div>
            </div>
        </div>
        <div class="fatura-titulo">
            <h2>FATURA</h2>
            <div class="fatura-num">Nº: ' . htmlspecialchars($numero_fatura, ENT_QUOTES, 'UTF-8') . '</div>
            <div style="font-size:8px;color:#666;">Emissão: ' . htmlspecialchars($data_emissao, ENT_QUOTES, 'UTF-8') . '</div>
        </div>
    </div>
    <div class="info-empresa">
        <span><strong>Empresa:</strong> ' . htmlspecialchars($nome_escola, ENT_QUOTES, 'UTF-8') . '</span>
        <span><strong>Contacto:</strong> ' . htmlspecialchars($contacto, ENT_QUOTES, 'UTF-8') . '</span>
        <span><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</span>
        <span class="nif-box">NIF: ' . htmlspecialchars($nif, ENT_QUOTES, 'UTF-8') . '</span>
    </div>';
    
    // Para cada aluno
    foreach ($alunos_agrupados as $aluno) {
        $html .= '<div class="aluno-section">
            <div class="aluno-header">
                <span>👤 ' . $aluno['aluno_nome'] . ' | ' . $aluno['aluno_classe'] . 'ª | Turma ' . $aluno['aluno_turma'] . ' | ' . $aluno['aluno_periodo'] . '</span>
                <span class="total-aluno">Total: Kz ' . number_format($aluno['total_aluno'], 2, ',', '.') . '</span>
            </div>
            <table class="tabela-itens">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Emolumento</th>
                        <th>Valor</th>
                        <th>Data</th>
                        <th>Mês Referência</th>
                        <th>Forma</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
        
        $i = 1;
        foreach ($aluno['itens'] as $item) {
            $statusClass = $item['status'] == 'confirmado' ? 'status-confirmado' : 'status-pendente';
            $statusLabel = $item['status'] == 'confirmado' ? '✅ Confirmado' : '⏳ Pendente';
            
            $html .= '<tr>
                <td>' . $i++ . '</td>
                <td class="text-left">' . $item['emolumento'] . '</td>
                <td class="text-right">Kz ' . number_format($item['valor'], 2, ',', '.') . '</td>
                <td>' . $item['data'] . '</td>
                <td>' . $item['mes_referencia'] . '</td>
                <td>' . $item['forma_pagamento'] . '</td>
                <td><span class="status-badge ' . $statusClass . '">' . $statusLabel . '</span></td>
            </tr>';
        }
        
        $html .= '<tr class="total-row">
            <td colspan="2" class="text-right"><strong>Subtotal</strong></td>
            <td class="text-right valor-total"><strong>Kz ' . number_format($aluno['total_aluno'], 2, ',', '.') . '</strong></td>
            <td colspan="4"></td>
        </tr>
        </tbody></table>
    </div>';
    }
    
    // Resumo geral
    $html .= '<div class="resumo-geral">
        <div class="info-item">
            <div><strong>Total de Alunos:</strong> ' . count($alunos_agrupados) . '</div>
            <div><strong>Total de Itens:</strong> ' . array_sum(array_map(function($a) { return count($a['itens']); }, $alunos_agrupados)) . '</div>
        </div>
        <div class="total-destaque">
            <div class="label">TOTAL GERAL</div>
            <div class="valor">Kz ' . number_format($total_geral, 2, ',', '.') . '</div>
        </div>
    </div>';
    
    // Rodapé
    $html .= '<div class="rodape">
        <div>
            <div><strong>Forma de Pagamento:</strong> Múltipla</div>
            <div><strong>Data de Emissão:</strong> ' . date('d/m/Y H:i:s') . '</div>
        </div>
        <div class="assinatura">
            <div class="linha"></div>
            <div>Assinatura do Responsável</div>
        </div>
        <div>
            <div><strong>Documento gerado eletronicamente</strong></div>
            <div>' . htmlspecialchars($nome_escola, ENT_QUOTES, 'UTF-8') . '</div>
        </div>
    </div>';
    
    return $html;
}

// ===== BUSCAR DADOS DA EMPRESA COM CACHE =====
function buscarDadosEmpresa($pdo) {
    // Verificar cache na sessão
    if (isset($_SESSION['empresa_dados']) && !empty($_SESSION['empresa_dados'])) {
        return $_SESSION['empresa_dados'];
    }
    
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
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
            
            // Salvar em cache na sessão
            $_SESSION['empresa_dados'] = $dados;
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar dados da empresa: " . $e->getMessage());
    }
    
    return $dados;
}

// ===== BUSCAR DADOS PARA FILTROS =====
$alunos = [];
$classes = [];
$turmas = [];
$emolumentos = [];

try {
    $stmt = $pdo->query("SELECT id, nome FROM alunos WHERE status = 'ativo' ORDER BY nome");
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT DISTINCT Classe FROM alunos WHERE Classe IS NOT NULL AND Classe != '' ORDER BY Classe");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA");
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT id, nome FROM emolumentos WHERE status = 'ativo' ORDER BY nome");
    $emolumentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar dados para filtros: " . $e->getMessage());
}

// ===== BUSCAR PAGAMENTOS COM PAGINAÇÃO =====
$pagamentos = [];
$total_geral = 0;
$total_confirmados = 0;
$total_pendentes = 0;
$total_registros = 0;

// Configuração de paginação
$por_pagina = 50;
$pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_atual - 1) * $por_pagina;

try {
    // Query base
    $sql_base = "
        SELECT 
            p.*,
            a.nome as aluno_nome,
            a.Classe as aluno_classe,
            a.TURMA as aluno_turma,
            a.Periodo as aluno_periodo,
            e.nome as emolumento_nome
        FROM pagamentos p
        LEFT JOIN alunos a ON p.aluno_id = a.id
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        WHERE p.data_pagamento BETWEEN ? AND ?
    ";
    $params_base = [$filtro_data_inicio, $filtro_data_fim];
    
    // Aplicar filtros
    if ($filtro_aluno > 0) {
        $sql_base .= " AND p.aluno_id = ?";
        $params_base[] = $filtro_aluno;
    }
    
    if (!empty($filtro_status)) {
        $sql_base .= " AND p.status = ?";
        $params_base[] = $filtro_status;
    }
    
    if (!empty($filtro_forma)) {
        $sql_base .= " AND p.forma_pagamento = ?";
        $params_base[] = $filtro_forma;
    }
    
    if (!empty($filtro_classe)) {
        $sql_base .= " AND a.Classe = ?";
        $params_base[] = $filtro_classe;
    }
    
    if (!empty($filtro_turma)) {
        $sql_base .= " AND a.TURMA = ?";
        $params_base[] = $filtro_turma;
    }
    
    if (!empty($filtro_emolumentos) && is_array($filtro_emolumentos)) {
        $placeholders = implode(',', array_fill(0, count($filtro_emolumentos), '?'));
        $sql_base .= " AND p.emolumento_id IN ($placeholders)";
        $params_base = array_merge($params_base, $filtro_emolumentos);
    }
    
    // Contar total de registros
    $sql_count = str_replace(
        "SELECT \n            p.*,\n            a.nome as aluno_nome,\n            a.Classe as aluno_classe,\n            a.TURMA as aluno_turma,\n            a.Periodo as aluno_periodo,\n            e.nome as emolumento_nome",
        "SELECT COUNT(*) as total",
        $sql_base
    );
    
    $stmt = $pdo->prepare($sql_count);
    $stmt->execute($params_base);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_registros = $result['total'] ?? 0;
    
    // Query com ordenação e paginação
    $sql = $sql_base;
    if ($ordenar_alfabetico == '1') {
        $sql .= " ORDER BY a.nome ASC, p.data_pagamento DESC";
    } else {
        $sql .= " ORDER BY p.data_pagamento DESC, p.created_at DESC";
    }
    $sql .= " LIMIT ? OFFSET ?";
    
    $params = array_merge($params_base, [$por_pagina, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totais
    foreach ($pagamentos as $p) {
        $total_geral += floatval($p['valor']);
        if ($p['status'] == 'confirmado') {
            $total_confirmados++;
        } else {
            $total_pendentes++;
        }
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar pagamentos: " . $e->getMessage());
    $pagamentos = [];
    $total_registros = 0;
}

// Calcular total de páginas
$total_paginas = ceil($total_registros / $por_pagina);

// ===== FUNÇÃO PARA FORMATAR MOEDA =====
function formatarMoeda($valor) {
    return 'Kz ' . number_format($valor, 2, ',', '.');
}

// ===== FUNÇÃO PARA STATUS =====
function getStatusBadge($status) {
    if (empty($status)) {
        return '<span class="status-badge status-pendente">⏳ Pendente</span>';
    }
    
    $statuses = [
        'confirmado' => '<span class="status-badge status-confirmado">✅ Confirmado</span>',
        'pendente' => '<span class="status-badge status-pendente">⏳ Pendente</span>',
        'cancelado' => '<span class="status-badge status-cancelado">❌ Cancelado</span>',
        'reembolsado' => '<span class="status-badge status-reembolsado">🔄 Reembolsado</span>'
    ];
    return $statuses[$status] ?? '<span class="status-badge status-pendente">⏳ Pendente</span>';
}

include '../../includes/header_escola.php';
?>

<style>
    /* ===== FONTE AUMENTADA PARA 16px ===== */
    body, .page-header h1, .page-header .subtitle, .btn, .filtros label, 
    .filtros input, .filtros select, .table, .stat-card .number, 
    .stat-card .label, .menu-financeiro a, .status-badge, 
    .selection-bar .info, .selection-bar .actions .btn,
    .pagination a, .pagination span {
        font-size: 16px !important;
    }
    
    .table th, .table td {
        font-size: 15px !important;
        padding: 14px 18px !important;
    }
    
    .stat-card .number {
        font-size: 30px !important;
    }
    
    .btn-sm {
        font-size: 13px !important;
        padding: 6px 14px !important;
    }
    
    .page-header h1 {
        font-size: 28px !important;
    }
    
    .page-header .subtitle {
        font-size: 17px !important;
    }
    
    .filtros input, .filtros select {
        font-size: 15px !important;
        padding: 10px 14px !important;
    }
    
    .menu-financeiro a {
        font-size: 15px !important;
        padding: 10px 20px !important;
    }
    
    /* ===== ESTILOS EXISTENTES ===== */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .btn {
        padding: 10px 24px;
        border-radius: 8px;
        text-decoration: none;
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
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .btn-info:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
    }
    
    .btn-multi {
        background: #8e44ad;
        color: #fff;
    }
    
    .btn-multi:hover {
        background: #7d3c98;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(142, 68, 173, 0.3);
    }
    
    .btn-multi:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-print-view {
        background: #1a2332;
        color: #fff;
        padding: 10px 25px;
        font-size: 14px;
    }
    
    .btn-print-view:hover {
        background: #2d3748;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(26, 35, 50, 0.3);
    }
    
    .btn-ordenar {
        background: #6c5ce7;
        color: #fff;
    }
    
    .btn-ordenar:hover {
        background: #5a4bd1;
        transform: translateY(-2px);
    }
    
    .btn-ordenar.ativo {
        background: #e74c3c;
    }
    
    .filtros {
        background: white;
        padding: 25px 30px;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .filtros .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 18px;
        align-items: end;
    }
    
    .filtros label {
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 5px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 13px !important;
    }
    
    .filtros input,
    .filtros select {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: white;
        transition: all 0.3s;
        font-size: 15px !important;
    }
    
    .filtros input:focus,
    .filtros select:focus {
        border-color: #c9a84c;
        outline: none;
        box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
    }
    
    .filtros select[multiple] {
        min-height: 60px;
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
    }
    
    .menu-financeiro a {
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        color: #4a5568;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 15px !important;
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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-card {
        background: white;
        padding: 20px 24px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        border-left: 4px solid #c9a84c;
        transition: all 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .stat-card .number {
        font-size: 30px !important;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .stat-card .label {
        font-size: 14px !important;
        color: #94a3b8;
        margin: 3px 0 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-card .icon {
        font-size: 28px;
        display: block;
        margin-bottom: 5px;
    }
    
    .stat-card.total { border-left-color: #3498db; }
    .stat-card.quantidade { border-left-color: #c9a84c; }
    .stat-card.confirmados { border-left-color: #2ecc71; }
    .stat-card.pendentes { border-left-color: #f39c12; }
    
    .table-responsive {
        overflow-x: auto;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }
    
    .table th {
        background: #f8fafc;
        padding: 14px 18px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        font-size: 15px !important;
    }
    
    .table th:first-child {
        width: 40px;
        text-align: center;
    }
    
    .table td {
        padding: 14px 18px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        font-size: 15px !important;
    }
    
    .table td:first-child {
        text-align: center;
    }
    
    .table tr:hover {
        background: #fafbfc;
    }
    
    .table .checkbox-row {
        cursor: pointer;
    }
    
    .table .checkbox-row input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #c9a84c;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 16px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 14px !important;
    }
    
    .status-confirmado {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-pendente {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-cancelado {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .status-reembolsado {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .valor-positivo {
        color: #2ecc71;
        font-weight: 700;
        font-size: 16px !important;
    }
    
    .valor-negativo {
        color: #e74c3c;
        font-weight: 700;
        font-size: 16px !important;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #94a3b8;
    }
    
    .empty-state .icon {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        font-size: 20px;
        color: #4a5568;
        margin: 0 0 8px;
    }
    
    .selection-bar {
        background: #f8fafc;
        padding: 14px 24px;
        border-radius: 8px;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        border: 1px solid #e2e8f0;
    }
    
    .selection-bar .info {
        font-size: 15px !important;
        color: #4a5568;
    }
    
    .selection-bar .info strong {
        color: #1a2332;
    }
    
    .selection-bar .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .selection-count {
        background: #c9a84c;
        color: #1a2332;
        padding: 2px 14px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 15px;
    }
    
    .fatura-multiple-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        z-index: 9999;
        overflow-y: auto;
        padding: 20px;
    }
    
    .fatura-multiple-modal .modal-content {
        max-width: 210mm;
        margin: 20px auto;
        background: #fff;
        border-radius: 8px;
        padding: 0;
        position: relative;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    }
    
    .fatura-multiple-modal .btn-fechar {
        position: sticky;
        top: 0;
        float: right;
        background: #e74c3c;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        margin: 10px;
        z-index: 10;
    }
    
    .fatura-multiple-modal .btn-fechar:hover {
        background: #c0392b;
    }
    
    .fatura-multiple-modal .fatura-iframe {
        width: 100%;
        height: 900px;
        border: none;
        border-radius: 0 0 8px 8px;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin: 20px 0;
        flex-wrap: wrap;
    }
    
    .pagination a, .pagination span {
        padding: 10px 18px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        text-decoration: none;
        color: #4a5568;
        font-weight: 500;
        transition: all 0.3s;
        background: white;
        font-size: 15px !important;
    }
    
    .pagination a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .pagination .active {
        background: #1a2332;
        color: #fff;
        border-color: #1a2332;
    }
    
    .pagination .disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .pagination .info {
        display: flex;
        align-items: center;
        padding: 10px 18px;
        color: #94a3b8;
        font-size: 14px !important;
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
        .filtros .filter-row {
            grid-template-columns: 1fr;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .table {
            font-size: 14px;
            min-width: 750px;
        }
        .table th, .table td {
            padding: 10px 12px;
        }
        .btn-sm {
            font-size: 12px !important;
            padding: 5px 10px !important;
        }
        .selection-bar {
            flex-direction: column;
            align-items: stretch;
        }
        .selection-bar .actions {
            justify-content: center;
        }
        .fatura-multiple-modal .fatura-iframe {
            height: 500px;
        }
        .pagination {
            gap: 5px;
        }
        .pagination a, .pagination span {
            padding: 6px 12px;
            font-size: 13px !important;
        }
    }
    
    @media print {
        .no-print { display: none !important; }
        .filtros { display: none !important; }
        .menu-financeiro { display: none !important; }
        .page-header .btn { display: none !important; }
        .table-responsive { border: none !important; box-shadow: none !important; }
        .stats-grid { page-break-inside: avoid; }
        .stat-card { border: 1px solid #ddd !important; }
        .selection-bar { display: none !important; }
        .pagination { display: none !important; }
    }
</style>

<div class="page-header">
    <div>
        <h1>📊 Relatório de Pagamentos</h1>
        <p class="subtitle">Histórico completo de pagamentos</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button onclick="abrirVisualizadorImpressao()" class="btn btn-print-view">🖨️ Visualizar Impressão</button>
        <button onclick="imprimirPagamentosFiltrados()" class="btn btn-print-view">🖨️ Imprimir Filtrados</button>



        <a href="../pagamentos/index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../pagamentos/index.php">📊 Dashboard</a>
    <a href="pagamentos.php" class="active">💳 Pagamentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
</div>

<!-- Filtros -->
<div class="filtros no-print">
    <form method="GET" action="" id="formFiltros">
        <div class="filter-row">
            <div>
                <label for="data_inicio">📅 Data Início</label>
                <input type="date" name="data_inicio" id="data_inicio" value="<?= htmlspecialchars($filtro_data_inicio) ?>">
            </div>
            <div>
                <label for="data_fim">📅 Data Fim</label>
                <input type="date" name="data_fim" id="data_fim" value="<?= htmlspecialchars($filtro_data_fim) ?>">
            </div>
            <div>
                <label for="aluno_id">👤 Aluno</label>
                <select name="aluno_id" id="aluno_id">
                    <option value="0">Todos</option>
                    <?php foreach($alunos as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $filtro_aluno == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['nome'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="classe">📚 Classe</label>
                <select name="classe" id="classe">
                    <option value="">Todas</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c['Classe'], ENT_QUOTES, 'UTF-8') ?>" <?= $filtro_classe == $c['Classe'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['Classe'], ENT_QUOTES, 'UTF-8') ?>ª
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="turma">🏫 Turma</label>
                <select name="turma" id="turma">
                    <option value="">Todas</option>
                    <?php foreach($turmas as $t): ?>
                        <option value="<?= htmlspecialchars($t['TURMA'], ENT_QUOTES, 'UTF-8') ?>" <?= $filtro_turma == $t['TURMA'] ? 'selected' : '' ?>>
                            Turma <?= htmlspecialchars($t['TURMA'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-row" style="margin-top: 15px;">
            <div>
                <label for="status">📊 Status</label>
                <select name="status" id="status">
                    <option value="">Todos</option>
                    <option value="confirmado" <?= $filtro_status == 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                    <option value="pendente" <?= $filtro_status == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="cancelado" <?= $filtro_status == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div>
                <label for="forma">💳 Forma de Pagamento</label>
                <select name="forma" id="forma">
                    <option value="">Todas</option>
                    <option value="dinheiro" <?= $filtro_forma == 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                    <option value="transferencia" <?= $filtro_forma == 'transferencia' ? 'selected' : '' ?>>Transferência</option>
                    <option value="pix" <?= $filtro_forma == 'pix' ? 'selected' : '' ?>>PIX</option>
                    <option value="cartao" <?= $filtro_forma == 'cartao' ? 'selected' : '' ?>>Cartão</option>
                    <option value="cartao_credito" <?= $filtro_forma == 'cartao_credito' ? 'selected' : '' ?>>Cartão Crédito</option>
                    <option value="cartao_debito" <?= $filtro_forma == 'cartao_debito' ? 'selected' : '' ?>>Cartão Débito</option>
                    <option value="boleto" <?= $filtro_forma == 'boleto' ? 'selected' : '' ?>>Boleto</option>
                </select>
            </div>
            <div>
                <label for="emolumentos">📋 Emolumentos</label>
                <select name="emolumentos[]" id="emolumentos" multiple>
                    <?php foreach($emolumentos as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= in_array($e['id'], $filtro_emolumentos) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nome'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="font-size:12px;color:#94a3b8;">Segure Ctrl para selecionar múltiplos</small>
            </div>
            <div style="display: flex; gap: 8px; align-items: end; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                <a href="pagamentos.php" class="btn btn-secondary">✕ Limpar</a>
                <button type="submit" name="ordenar_alfabetico" value="1" class="btn btn-ordenar <?= $ordenar_alfabetico == '1' ? 'ativo' : '' ?>">
                    <?= $ordenar_alfabetico == '1' ? '🔤 A-Z Ativo' : '🔤 Ordenar A-Z' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card quantidade">
        <span class="icon">📊</span>
        <div class="number"><?= number_format($total_registros) ?></div>
        <div class="label">Total de Pagamentos</div>
    </div>
    <div class="stat-card total">
        <span class="icon">💰</span>
        <div class="number"><?= formatarMoeda($total_geral) ?></div>
        <div class="label">Valor Total (página)</div>
    </div>
    <div class="stat-card confirmados">
        <span class="icon">✅</span>
        <div class="number"><?= $total_confirmados ?></div>
        <div class="label">Confirmados</div>
    </div>
    <div class="stat-card pendentes">
        <span class="icon">⏳</span>
        <div class="number"><?= $total_pendentes ?></div>
        <div class="label">Pendentes</div>
    </div>
</div>

<!-- Barra de Seleção -->
<form method="POST" action="" id="formMultipla">
    <div class="selection-bar no-print">
        <div class="info">
            <input type="checkbox" id="selecionar_todos" onchange="toggleTodos()" style="width:18px;height:18px;">
            <label for="selecionar_todos" style="margin-left:8px;cursor:pointer;font-size:15px;">Selecionar Todos</label>
            <span style="margin-left:20px;font-size:15px;">
                <span class="selection-count" id="contadorSelecionados">0</span> selecionados
            </span>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-secondary btn-sm" onclick="selecionarPorAluno()">👤 Por Aluno</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="selecionarPorStatus()">📊 Por Status</button>
            <button type="submit" name="gerar_fatura_multipla" class="btn btn-multi" id="btnGerarMultipla" disabled>
                📄 Gerar Fatura Múltipla
            </button>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selecionar_todos_tabela" onchange="toggleTodos()" style="width:18px;height:18px;"></th>
                    <th>#</th>
                    <th>Aluno</th>
                    <th>Emolumento</th>
                    <th>Valor</th>
                    <th>Data</th>
                    <th>Forma</th>
                    <th>Status</th>
                    <th>Mês Ref.</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pagamentos) > 0): ?>
                    <?php $i = $offset + 1; foreach($pagamentos as $p): ?>
                    <tr class="checkbox-row">
                        <td>
                            <input type="checkbox" name="pagamentos_selecionados[]" value="<?= $p['id'] ?>" 
                                   class="selecionar-item" onchange="atualizarContador()"
                                   data-aluno="<?= $p['aluno_id'] ?>" 
                                   data-status="<?= $p['status'] ?>"
                                   data-valor="<?= $p['valor'] ?>">
                        </td>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($p['aluno_nome'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($p['emolumento_nome'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="valor-positivo"><?= formatarMoeda($p['valor']) ?></td>
                        <td><?= date('d/m/Y', strtotime($p['data_pagamento'])) ?></td>
                        <td><?= ucfirst(htmlspecialchars($p['forma_pagamento'] ?? '-', ENT_QUOTES, 'UTF-8')) ?></td>
                        <td><?= getStatusBadge($p['status'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['mes_referencia'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-center">
                            <a href="<?= SITE_URL ?>modules/escola/alunos/visualizar_fatura.php?id=<?= $p['id'] ?>&origem=pagamentos" 
                               class="btn btn-info btn-sm" 
                               title="Visualizar Fatura"
                               target="_blank">
                                📄 Ver Fatura
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center">
                            <div class="empty-state">
                                <span class="icon">📭</span>
                                <h3>Nenhum pagamento encontrado</h3>
                                <p>Não há pagamentos no período selecionado.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (count($pagamentos) > 0): ?>
            <tfoot style="background: #f8fafc; font-weight: 700;">
                <tr>
                    <td colspan="4" style="text-align: right;font-size:16px;">TOTAL DA PÁGINA:</td>
                    <td style="font-size:16px;"><?= formatarMoeda($total_geral) ?></td>
                    <td colspan="5"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</form>

<!-- Paginação -->
<?php if ($total_registros > $por_pagina): ?>
<div class="pagination no-print">
    <div class="info">
        Mostrando <?= ($offset + 1) ?> - <?= min($offset + $por_pagina, $total_registros) ?> de <?= number_format($total_registros) ?>
    </div>
    <div>
        <?php if ($pagina_atual > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">« Primeira</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])) ?>">‹ Anterior</a>
        <?php else: ?>
            <span class="disabled">« Primeira</span>
            <span class="disabled">‹ Anterior</span>
        <?php endif; ?>
        
        <?php
        $inicio = max(1, $pagina_atual - 2);
        $fim = min($total_paginas, $pagina_atual + 2);
        for ($i = $inicio; $i <= $fim; $i++):
        ?>
            <?php if ($i == $pagina_atual): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($pagina_atual < $total_paginas): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])) ?>">Próxima ›</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>">Última »</a>
        <?php else: ?>
            <span class="disabled">Próxima ›</span>
            <span class="disabled">Última »</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Modal Fatura Múltipla -->
<?php if ($mostrar_fatura_multipla && $fatura_multipla_html): ?>
<div class="fatura-multiple-modal" id="faturaMultiplaModal">
    <div class="modal-content">
        <button class="btn-fechar" onclick="fecharFaturaMultipla()">✕ Fechar</button>
        <?php
        // Codificar o HTML da fatura para evitar problemas com aspas
        $fatura_html_base64 = base64_encode($fatura_multipla_html);
        ?>
        <iframe class="fatura-iframe" srcdoc="<?= htmlspecialchars($fatura_multipla_html, ENT_QUOTES, 'UTF-8') ?>"></iframe>
    </div>
</div>
<?php endif; ?>

<script>
// ===== CONTROLE DE SELEÇÃO =====
function atualizarContador() {
    const checkboxes = document.querySelectorAll('.selecionar-item:checked');
    const total = checkboxes.length;
    const contador = document.getElementById('contadorSelecionados');
    const btnGerar = document.getElementById('btnGerarMultipla');
    
    contador.textContent = total;
    btnGerar.disabled = total === 0;
    
    // Atualizar checkbox "Selecionar Todos"
    const todosCheckboxes = document.querySelectorAll('.selecionar-item');
    const todos = document.getElementById('selecionar_todos');
    const todosTabela = document.getElementById('selecionar_todos_tabela');
    
    if (todosCheckboxes.length > 0 && total === todosCheckboxes.length) {
        todos.checked = true;
        todosTabela.checked = true;
    } else {
        todos.checked = false;
        todosTabela.checked = false;
    }
}

function toggleTodos() {
    const checked = document.getElementById('selecionar_todos').checked;
    const todosTabela = document.getElementById('selecionar_todos_tabela');
    todosTabela.checked = checked;
    
    document.querySelectorAll('.selecionar-item').forEach(cb => {
        cb.checked = checked;
    });
    atualizarContador();
}

function selecionarPorAluno() {
    const alunos = new Set();
    document.querySelectorAll('.selecionar-item').forEach(cb => {
        const aluno = cb.dataset.aluno;
        if (!alunos.has(aluno)) {
            alunos.add(aluno);
            cb.checked = true;
        } else {
            cb.checked = false;
        }
    });
    atualizarContador();
}

function selecionarPorStatus() {
    document.querySelectorAll('.selecionar-item').forEach(cb => {
        cb.checked = cb.dataset.status === 'confirmado';
    });
    atualizarContador();
}

function fecharFaturaMultipla() {
    const modal = document.getElementById('faturaMultiplaModal');
    if (modal) {
        modal.style.display = 'none';
    }
    if (window.history && window.history.pushState) {
        window.history.pushState({}, '', window.location.pathname);
    }
}

// ===== ABRIR VISUALIZADOR DE IMPRESSÃO =====
function abrirVisualizadorImpressao() {
    const data_inicio = document.getElementById('data_inicio')?.value || '<?= $filtro_data_inicio ?>';
    const data_fim = document.getElementById('data_fim')?.value || '<?= $filtro_data_fim ?>';
    const aluno_id = document.getElementById('aluno_id')?.value || '<?= $filtro_aluno ?>';
    const status = document.getElementById('status')?.value || '<?= $filtro_status ?>';
    const forma = document.getElementById('forma')?.value || '<?= $filtro_forma ?>';
    const classe = document.getElementById('classe')?.value || '<?= $filtro_classe ?>';
    const turma = document.getElementById('turma')?.value || '<?= $filtro_turma ?>';
    const ordem = '<?= $ordenar_alfabetico ?>';
    
    // Pegar emolumentos selecionados
    const emolumentosSelect = document.getElementById('emolumentos');
    const emolumentos = Array.from(emolumentosSelect?.selectedOptions || []).map(opt => opt.value);
    
    let url = 'visualizar_impressao.php?' + 
        'data_inicio=' + encodeURIComponent(data_inicio) +
        '&data_fim=' + encodeURIComponent(data_fim) +
        '&aluno_id=' + encodeURIComponent(aluno_id) +
        '&status=' + encodeURIComponent(status) +
        '&forma=' + encodeURIComponent(forma) +
        '&classe=' + encodeURIComponent(classe) +
        '&turma=' + encodeURIComponent(turma) +
        '&ordenar_alfabetico=' + encodeURIComponent(ordem);
    
    emolumentos.forEach(id => {
        url += '&emolumentos[]=' + encodeURIComponent(id);
    });
    
    window.open(url, '_blank', 'width=1024,height=768,scrollbars=yes');
}

// Fechar com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('faturaMultiplaModal');
        if (modal && modal.style.display !== 'none') {
            fecharFaturaMultipla();
        }
    }
});

// Atualizar contador ao carregar
document.addEventListener('DOMContentLoaded', function() {
    atualizarContador();
    
    // Adicionar evento de clique nas linhas da tabela
    document.querySelectorAll('.checkbox-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || 
                e.target.closest('a') || e.target.closest('button') ||
                e.target.tagName === 'INPUT') {
                return;
            }
            const checkbox = this.querySelector('.selecionar-item');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                atualizarContador();
            }
        });
    });
});

// Feedback visual de seleção
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('selecionar-item')) {
        const row = e.target.closest('tr');
        if (row) {
            if (e.target.checked) {
                row.style.backgroundColor = '#fef9e8';
            } else {
                row.style.backgroundColor = '';
            }
        }
    }
});

// ===== CONFIRMAR FATURA MÚLTIPLA =====
document.getElementById('formMultipla')?.addEventListener('submit', function(e) {
    const checkboxes = document.querySelectorAll('.selecionar-item:checked');
    if (checkboxes.length === 0) {
        e.preventDefault();
        alert('Selecione pelo menos um pagamento para gerar a fatura múltipla.');
        return false;
    }
    if (checkboxes.length > 1) {
        let total = 0;
        checkboxes.forEach(cb => {
            total += parseFloat(cb.dataset.valor || 0);
        });
        if (!confirm('Deseja gerar uma fatura consolidada com ' + checkboxes.length + ' pagamentos?\nTotal: ' + 
            total.toFixed(2).replace('.', ',') + ' Kz')) {
            e.preventDefault();
            return false;
        }
    }
    return true;
});


// ===== IMPRIMIR PAGAMENTOS FILTRADOS COM CABEÇALHO DA EMPRESA =====
function imprimirPagamentosFiltrados() {
    const data_inicio = document.getElementById('data_inicio')?.value || '<?= $filtro_data_inicio ?>';
    const data_fim = document.getElementById('data_fim')?.value || '<?= $filtro_data_fim ?>';
    const aluno_id = document.getElementById('aluno_id')?.value || '<?= $filtro_aluno ?>';
    const status = document.getElementById('status')?.value || '<?= $filtro_status ?>';
    const forma = document.getElementById('forma')?.value || '<?= $filtro_forma ?>';
    const classe = document.getElementById('classe')?.value || '<?= $filtro_classe ?>';
    const turma = document.getElementById('turma')?.value || '<?= $filtro_turma ?>';
    const ordem = '<?= $ordenar_alfabetico ?>';
    
    // Pegar emolumentos selecionados
    const emolumentosSelect = document.getElementById('emolumentos');
    const emolumentos = Array.from(emolumentosSelect?.selectedOptions || []).map(opt => opt.value);
    
    let url = 'imprimir_pagamentos.php?' + 
        'data_inicio=' + encodeURIComponent(data_inicio) +
        '&data_fim=' + encodeURIComponent(data_fim) +
        '&aluno_id=' + encodeURIComponent(aluno_id) +
        '&status=' + encodeURIComponent(status) +
        '&forma=' + encodeURIComponent(forma) +
        '&classe=' + encodeURIComponent(classe) +
        '&turma=' + encodeURIComponent(turma) +
        '&ordenar_alfabetico=' + encodeURIComponent(ordem);
    
    emolumentos.forEach(id => {
        url += '&emolumentos[]=' + encodeURIComponent(id);
    });
    
    // Abrir em nova janela com auto-print
    const janela = window.open(url + '&print=1', '_blank', 'width=1200,height=800,scrollbars=yes');
    if (janela) {
        janela.focus();
    } else {
        alert('Por favor, permita pop-ups para imprimir o relatório.');
    }
}

</script>

<?php include '../../includes/footer_escola.php'; ?>