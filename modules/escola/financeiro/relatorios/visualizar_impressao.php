<?php
// ============================================
// modules/escola/financeiro/relatorios/visualizar_impressao.php
// Visualizador de Impressão - Estilo AGT
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

// ===== FILTROS (recebidos via GET) =====
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$filtro_aluno = isset($_GET['aluno_id']) ? intval($_GET['aluno_id']) : 0;
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_forma = isset($_GET['forma']) ? $_GET['forma'] : '';
$modo = isset($_GET['modo']) ? $_GET['modo'] : 'visualizar';

// ===== BUSCAR DADOS DA EMPRESA =====
function buscarDadosEmpresa($pdo) {
    $dados = [
        'nome' => 'Sistema Escolar',
        'razao_social' => '',
        'endereco' => '',
        'telefone' => '',
        'celular' => '',
        'email' => '',
        'nif' => '',
        'cidade' => '',
        'estado' => '',
        'cep' => '',
        'logo' => '',
        'regime' => 'Normal'
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM empresa WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $empresa = $stmt->fetch();
        
        if ($empresa) {
            $dados['nome'] = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Sistema Escolar';
            $dados['razao_social'] = $empresa['razao_social'] ?? '';
            $dados['nif'] = $empresa['cnpj'] ?? $empresa['nif'] ?? $empresa['inscricao_estadual'] ?? '';
            $dados['telefone'] = $empresa['telefone'] ?? '';
            $dados['celular'] = $empresa['celular'] ?? '';
            $dados['email'] = $empresa['email'] ?? '';
            $dados['cidade'] = $empresa['cidade'] ?? '';
            $dados['estado'] = $empresa['estado'] ?? '';
            $dados['cep'] = $empresa['cep'] ?? '';
            $dados['regime'] = $empresa['regime_tributario'] ?? 'Normal';
            
            $endereco_parts = [];
            if (!empty($empresa['endereco'])) $endereco_parts[] = $empresa['endereco'];
            if (!empty($empresa['numero'])) $endereco_parts[] = $empresa['numero'];
            if (!empty($empresa['bairro'])) $endereco_parts[] = $empresa['bairro'];
            $dados['endereco'] = implode(', ', $endereco_parts);
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar dados da empresa: " . $e->getMessage());
    }
    
    return $dados;
}

$dados_empresa = buscarDadosEmpresa($pdo);

// ===== BUSCAR DADOS DO RELATÓRIO =====
$pagamentos = [];
$total_geral = 0;
$total_confirmados = 0;
$total_pendentes = 0;
$total_por_forma = [];
$total_por_mes = [];
$total_por_status = [];

try {
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
        WHERE p.data_pagamento BETWEEN ? AND ?
    ";
    $params = [$filtro_data_inicio, $filtro_data_fim];
    
    if ($filtro_aluno > 0) {
        $sql .= " AND p.aluno_id = ?";
        $params[] = $filtro_aluno;
    }
    
    if (!empty($filtro_status)) {
        $sql .= " AND p.status = ?";
        $params[] = $filtro_status;
    }
    
    if (!empty($filtro_forma)) {
        $sql .= " AND p.forma_pagamento = ?";
        $params[] = $filtro_forma;
    }
    
    $sql .= " ORDER BY p.data_pagamento DESC, p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pagamentos = $stmt->fetchAll();
    
    // Calcular totais e estatísticas
    foreach ($pagamentos as $p) {
        $total_geral += $p['valor'];
        
        if ($p['status'] == 'confirmado') {
            $total_confirmados++;
        } else {
            $total_pendentes++;
        }
        
        $forma = $p['forma_pagamento'] ?? 'outros';
        if (!isset($total_por_forma[$forma])) {
            $total_por_forma[$forma] = ['total' => 0, 'count' => 0];
        }
        $total_por_forma[$forma]['total'] += $p['valor'];
        $total_por_forma[$forma]['count']++;
        
        $status = $p['status'] ?? 'pendente';
        if (!isset($total_por_status[$status])) {
            $total_por_status[$status] = ['total' => 0, 'count' => 0];
        }
        $total_por_status[$status]['total'] += $p['valor'];
        $total_por_status[$status]['count']++;
        
        $mes = date('m/Y', strtotime($p['data_pagamento']));
        if (!isset($total_por_mes[$mes])) {
            $total_por_mes[$mes] = ['total' => 0, 'count' => 0];
        }
        $total_por_mes[$mes]['total'] += $p['valor'];
        $total_por_mes[$mes]['count']++;
    }
    
    ksort($total_por_mes);
    
} catch (Exception $e) {
    $pagamentos = [];
}

// ===== BUSCAR NOME DO ALUNO =====
$nome_aluno = '';
if ($filtro_aluno > 0) {
    try {
        $stmt = $pdo->prepare("SELECT nome FROM alunos WHERE id = ?");
        $stmt->execute([$filtro_aluno]);
        $aluno = $stmt->fetch();
        $nome_aluno = $aluno['nome'] ?? '';
    } catch (Exception $e) {}
}

// ===== BUSCAR ALUNOS PARA FILTRO =====
$alunos_lista = [];
try {
    $alunos_lista = $pdo->query("SELECT id, nome FROM alunos WHERE status = 'ativo' ORDER BY nome")->fetchAll();
} catch (Exception $e) {}

// ===== FUNÇÕES AUXILIARES =====
function formatarMoeda($valor) {
    return 'Kz ' . number_format($valor, 2, ',', '.');
}

function getStatusLabel($status) {
    $statuses = [
        'confirmado' => 'Confirmado',
        'pendente' => 'Pendente',
        'cancelado' => 'Cancelado',
        'reembolsado' => 'Reembolsado',
        'pago' => 'Pago'
    ];
    return $statuses[$status] ?? 'Pendente';
}

function getStatusBadgePrint($status) {
    $statuses = [
        'confirmado' => 'badge-success',
        'pago' => 'badge-success',
        'pendente' => 'badge-warning',
        'cancelado' => 'badge-danger',
        'reembolsado' => 'badge-info'
    ];
    $class = $statuses[$status] ?? 'badge-warning';
    return '<span class="badge ' . $class . '">' . getStatusLabel($status) . '</span>';
}

function getFormaLabel($forma) {
    $formas = [
        'dinheiro' => 'Dinheiro',
        'cartao' => 'Cartão',
        'cartao_credito' => 'Cartão Crédito',
        'cartao_debito' => 'Cartão Débito',
        'transferencia' => 'Transferência',
        'pix' => 'PIX',
        'boleto' => 'Boleto'
    ];
    return $formas[$forma] ?? $forma;
}

function formatarData($data) {
    if (empty($data)) return '-';
    return date('d/m/Y', strtotime($data));
}

function formatarDataHora($data) {
    if (empty($data)) return '-';
    return date('d/m/Y H:i:s', strtotime($data));
}

function numeroExtenso($valor) {
    $valor = round($valor, 2);
    $inteiro = intval($valor);
    $centavos = round(($valor - $inteiro) * 100);
    
    $palavras = [
        1 => 'um', 2 => 'dois', 3 => 'três', 4 => 'quatro', 5 => 'cinco',
        6 => 'seis', 7 => 'sete', 8 => 'oito', 9 => 'nove', 10 => 'dez',
        11 => 'onze', 12 => 'doze', 13 => 'treze', 14 => 'catorze', 15 => 'quinze',
        16 => 'dezesseis', 17 => 'dezessete', 18 => 'dezoito', 19 => 'dezenove', 20 => 'vinte',
        30 => 'trinta', 40 => 'quarenta', 50 => 'cinquenta', 60 => 'sessenta',
        70 => 'setenta', 80 => 'oitenta', 90 => 'noventa',
        100 => 'cem', 200 => 'duzentos', 300 => 'trezentos', 400 => 'quatrocentos',
        500 => 'quinhentos', 600 => 'seiscentos', 700 => 'setecentos',
        800 => 'oitocentos', 900 => 'novecentos'
    ];
    
    if ($inteiro == 0) {
        $texto = 'zero';
    } else {
        $milhares = intval($inteiro / 1000);
        $resto = $inteiro % 1000;
        $texto = '';
        
        if ($milhares > 0) {
            if ($milhares == 1) {
                $texto .= 'um mil';
            } else {
                $texto .= ($milhares < 10 ? $palavras[$milhares] : $milhares) . ' mil';
            }
            if ($resto > 0) $texto .= ' e ';
        }
        
        if ($resto > 0) {
            if ($resto <= 20) {
                $texto .= $palavras[$resto] ?? $resto;
            } else {
                $dezena = intval($resto / 10) * 10;
                $unidade = $resto % 10;
                $texto .= $palavras[$dezena] ?? $dezena;
                if ($unidade > 0) {
                    $texto .= ' e ' . ($palavras[$unidade] ?? $unidade);
                }
            }
        }
    }
    
    $texto .= ' kwanzas';
    if ($centavos > 0) {
        $texto .= ' e ' . $centavos . ' centavos';
    }
    
    return ucfirst($texto);
}

// Se for modo impressão, exibe apenas o relatório
if ($modo === 'imprimir') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Relatório de Pagamentos</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Arial', sans-serif; background: #fff; padding: 20px; }
            .print-container { max-width: 210mm; margin: 0 auto; }
            /* Estilos do relatório serão incluídos */
        </style>
    </head>
    <body>
        <?= gerarConteudoRelatorioAGT($pagamentos, $total_geral, $total_confirmados, $total_pendentes, $total_por_forma, $total_por_mes, $filtro_data_inicio, $filtro_data_fim, $filtro_aluno, $filtro_status, $filtro_forma, $nome_aluno, $dados_empresa) ?>
        <script>window.onload = function() { window.print(); }</script>
    </body>
    </html>
    <?php
    exit;
}

// ===== GERAR CONTEÚDO DO RELATÓRIO - ESTILO AGT =====
function gerarConteudoRelatorioAGT($pagamentos, $total_geral, $total_confirmados, $total_pendentes, $total_por_forma, $total_por_mes, $filtro_data_inicio, $filtro_data_fim, $filtro_aluno, $filtro_status, $filtro_forma, $nome_aluno, $dados_empresa) {
    $cores = ['#1a2332', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c', '#3498db'];
    
    $total_pagamentos = count($pagamentos);
    $total_formas = array_sum(array_column($total_por_forma, 'total'));
    
    $html = '
    <div class="print-container agt-style">
        <!-- ===== CABEÇALHO AGT ===== -->
        <div class="agt-header">
            <div class="agt-header-top">
                <div class="agt-logo">
                    <div class="logo-icon">🎓</div>
                    <div>
                        <div class="empresa-nome">' . htmlspecialchars($dados_empresa['nome']) . '</div>
                        <div class="empresa-razao">' . htmlspecialchars($dados_empresa['razao_social']) . '</div>
                    </div>
                </div>
                <div class="agt-documento">
                    <div class="doc-titulo">RELATÓRIO DE PAGAMENTOS</div>
                    <div class="doc-numero">Nº: REL-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) . '</div>
                </div>
            </div>
            <div class="agt-header-info">
                <div class="info-item">
                    <span class="label">NIF:</span>
                    <span class="value">' . htmlspecialchars($dados_empresa['nif']) . '</span>
                </div>
                <div class="info-item">
                    <span class="label">Regime:</span>
                    <span class="value">' . htmlspecialchars($dados_empresa['regime']) . '</span>
                </div>
                <div class="info-item">
                    <span class="label">Telefone:</span>
                    <span class="value">' . htmlspecialchars($dados_empresa['telefone'] . ($dados_empresa['celular'] ? ' / ' . $dados_empresa['celular'] : '')) . '</span>
                </div>
                <div class="info-item">
                    <span class="label">Email:</span>
                    <span class="value">' . htmlspecialchars($dados_empresa['email']) . '</span>
                </div>
            </div>
            <div class="agt-header-endereco">
                📍 ' . htmlspecialchars($dados_empresa['endereco']) . ' - ' . htmlspecialchars($dados_empresa['cidade']) . ' - ' . htmlspecialchars($dados_empresa['estado']) . '
            </div>
        </div>

        <!-- ===== PERÍODO E FILTROS ===== -->
        <div class="agt-periodo">
            <div class="periodo-info">
                <span class="periodo-label">📅 Período:</span>
                <span class="periodo-data">' . formatarData($filtro_data_inicio) . ' a ' . formatarData($filtro_data_fim) . '</span>
            </div>
            <div class="filtros-info">
                ' . ($filtro_aluno > 0 && !empty($nome_aluno) ? '<span class="filtro-tag">👤 ' . htmlspecialchars($nome_aluno) . '</span>' : '') . '
                ' . (!empty($filtro_status) ? '<span class="filtro-tag">📌 ' . getStatusLabel($filtro_status) . '</span>' : '') . '
                ' . (!empty($filtro_forma) ? '<span class="filtro-tag">💳 ' . getFormaLabel($filtro_forma) . '</span>' : '') . '
            </div>
        </div>

        <!-- ===== STATS ===== -->
        <div class="agt-stats">
            <div class="stat-box total-pagamentos">
                <div class="stat-number">' . $total_pagamentos . '</div>
                <div class="stat-label">Total de Pagamentos</div>
            </div>
            <div class="stat-box valor-total">
                <div class="stat-number">' . formatarMoeda($total_geral) . '</div>
                <div class="stat-label">Valor Total</div>
            </div>
            <div class="stat-box confirmados">
                <div class="stat-number">' . $total_confirmados . '</div>
                <div class="stat-label">✅ Confirmados</div>
            </div>
            <div class="stat-box pendentes">
                <div class="stat-number">' . $total_pendentes . '</div>
                <div class="stat-label">⏳ Pendentes</div>
            </div>
        </div>

        <!-- ===== GRÁFICO DE BARRAS ===== -->
        <div class="agt-chart">
            <div class="chart-title">📈 Evolução Mensal dos Pagamentos</div>
            <div class="chart-bars-container">';
    
    if (count($total_por_mes) > 0) {
        $max_valor = max(array_column($total_por_mes, 'total'));
        $max_valor = $max_valor > 0 ? $max_valor : 1;
        
        foreach ($total_por_mes as $mes => $dados) {
            $altura = ($dados['total'] / $max_valor) * 100;
            $altura = max($altura, 5);
            $html .= '
                <div class="chart-bar-item">
                    <div class="chart-bar" style="height: ' . $altura . '%;">
                        <span class="bar-value">' . formatarMoeda($dados['total']) . '</span>
                    </div>
                    <div class="chart-bar-label">' . $mes . '</div>
                    <div class="chart-bar-count">' . $dados['count'] . ' pag.</div>
                </div>';
        }
    } else {
        $html .= '<div class="chart-empty">Sem dados para exibir</div>';
    }
    
    $html .= '
            </div>
        </div>

        <!-- ===== DISTRIBUIÇÃO POR FORMA ===== -->
        <div class="agt-chart">
            <div class="chart-title">💳 Distribuição por Forma de Pagamento</div>';
    
    if (count($total_por_forma) > 0) {
        $i = 0;
        foreach ($total_por_forma as $forma => $dados) {
            $percentual = $total_formas > 0 ? ($dados['total'] / $total_formas) * 100 : 0;
            $cor = $cores[$i % count($cores)];
            $i++;
            
            $html .= '
            <div class="progress-item">
                <div class="progress-label">
                    <span class="progress-nome"><strong>' . getFormaLabel($forma) . '</strong></span>
                    <span class="progress-values">' . formatarMoeda($dados['total']) . ' (' . $dados['count'] . ' pag.)</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width:' . $percentual . '%;background:' . $cor . ';">
                        ' . ($percentual >= 10 ? number_format($percentual, 1) . '%' : '') . '
                    </div>
                </div>
            </div>';
        }
    } else {
        $html .= '<div class="chart-empty">Sem dados para exibir</div>';
    }
    
    $html .= '
        </div>

        <!-- ===== TABELA DETALHADA ===== -->
        <div class="agt-table-section">
            <div class="table-title">
                📋 Detalhamento dos Pagamentos
                <span class="table-count">' . $total_pagamentos . ' registros</span>
            </div>';
    
    if ($total_pagamentos > 0) {
        $html .= '
            <table class="agt-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="width:18%;">Aluno</th>
                        <th style="width:18%;">Emolumento</th>
                        <th style="width:12%;text-align:right;">Valor</th>
                        <th style="width:12%;">Data</th>
                        <th style="width:10%;">Forma</th>
                        <th style="width:10%;">Status</th>
                        <th style="width:12%;">Mês Ref.</th>
                    </tr>
                </thead>
                <tbody>';
        
        $i = 1;
        foreach ($pagamentos as $p) {
            $html .= '
                <tr>
                    <td>' . $i++ . '</td>
                    <td><strong>' . htmlspecialchars($p['aluno_nome'] ?? 'N/A') . '</strong></td>
                    <td>' . htmlspecialchars($p['emolumento_nome'] ?? 'N/A') . '</td>
                    <td style="text-align:right;" class="valor">' . formatarMoeda($p['valor']) . '</td>
                    <td>' . formatarData($p['data_pagamento']) . '</td>
                    <td><span class="badge-forma">' . getFormaLabel($p['forma_pagamento'] ?? '') . '</span></td>
                    <td>' . getStatusBadgePrint($p['status'] ?? 'pendente') . '</td>
                    <td>' . htmlspecialchars($p['mes_referencia'] ?? '-') . '</td>
                </tr>';
        }
        
        $html .= '
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" style="text-align:right;">TOTAL GERAL</td>
                        <td style="text-align:right;">' . formatarMoeda($total_geral) . '</td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>';
    } else {
        $html .= '
            <div class="empty-state">
                <span class="empty-icon">📭</span>
                <h3>Nenhum pagamento encontrado</h3>
                <p>Não há pagamentos para os filtros selecionados.</p>
            </div>';
    }
    
    // ===== VALOR POR EXTENSO =====
    if ($total_geral > 0) {
        $html .= '
        <div class="agt-extenso">
            <span class="extenso-label">Valor por extenso:</span>
            <span class="extenso-text">' . numeroExtenso($total_geral) . '</span>
        </div>';
    }
    
    $html .= '
        </div>

        <!-- ===== RODAPÉ ===== -->
        <div class="agt-footer">
            <div class="footer-left">
                <div class="assinatura">
                    <div class="linha-assinatura"></div>
                    <div class="assinatura-label">Assinatura do Responsável</div>
                </div>
            </div>
            <div class="footer-center">
                <div class="carimbo">
                    <div class="carimbo-texto">' . htmlspecialchars($dados_empresa['nome']) . '</div>
                    <div class="carimbo-sub">Carimbo da Empresa</div>
                </div>
            </div>
            <div class="footer-right">
                <div class="info-geracao">
                    <div>📅 Gerado em: ' . formatarDataHora(date('Y-m-d H:i:s')) . '</div>
                    <div>📄 Documento eletrônico</div>
                </div>
            </div>
        </div>
        
        <div class="agt-rodape">
            <span>© ' . date('Y') . ' ' . htmlspecialchars($dados_empresa['nome']) . ' - Sistema de Gestão Escolar</span>
            <span>|</span>
            <span>Documento emitido eletronicamente</span>
        </div>
    </div>';
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Impressão - Relatório de Pagamentos</title>
    <style>
        /* ===== ESTILOS GERAIS ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', 'Helvetica', sans-serif; background: #f0f2f5; padding: 20px; color: #1a2332; }
        .page-container { max-width: 210mm; margin: 0 auto; }

        /* ===== ESTILO AGT ===== */
        .print-container.agt-style {
            background: #fff;
            padding: 30px 35px;
            border-radius: 4px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }

        /* ===== CABEÇALHO AGT ===== */
        .agt-header {
            border-bottom: 3px double #1a2332;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .agt-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .agt-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .agt-logo .logo-icon {
            width: 48px;
            height: 48px;
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
        
        .agt-logo .empresa-nome {
            font-size: 20px;
            font-weight: 800;
            color: #1a2332;
            letter-spacing: 0.5px;
        }
        
        .agt-logo .empresa-razao {
            font-size: 11px;
            color: #94a3b8;
        }
        
        .agt-documento {
            text-align: right;
        }
        
        .agt-documento .doc-titulo {
            font-size: 18px;
            font-weight: 700;
            color: #c9a84c;
            letter-spacing: 1px;
        }
        
        .agt-documento .doc-numero {
            font-size: 12px;
            color: #4a5568;
            background: #f8fafc;
            padding: 2px 12px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            display: inline-block;
            margin-top: 3px;
        }
        
        .agt-header-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px 25px;
            margin-top: 8px;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        
        .agt-header-info .info-item {
            font-size: 12px;
        }
        
        .agt-header-info .info-item .label {
            font-weight: 600;
            color: #4a5568;
        }
        
        .agt-header-info .info-item .value {
            color: #1a2332;
        }
        
        .agt-header-endereco {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 6px;
            text-align: center;
        }

        /* ===== PERÍODO ===== */
        .agt-periodo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .agt-periodo .periodo-info {
            font-size: 13px;
        }
        
        .agt-periodo .periodo-label {
            font-weight: 600;
            color: #4a5568;
        }
        
        .agt-periodo .periodo-data {
            font-weight: 700;
            color: #1a2332;
        }
        
        .agt-periodo .filtros-info {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .agt-periodo .filtro-tag {
            background: #e2e8f0;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            color: #4a5568;
        }

        /* ===== STATS ===== */
        .agt-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #e2e8f0;
            border-top: 4px solid #c9a84c;
        }
        
        .stat-box .stat-number {
            font-size: 22px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .stat-box .stat-label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 3px;
        }
        
        .stat-box.total-pagamentos { border-top-color: #3498db; }
        .stat-box.valor-total { border-top-color: #c9a84c; }
        .stat-box.confirmados { border-top-color: #2ecc71; }
        .stat-box.pendentes { border-top-color: #f39c12; }

        /* ===== GRÁFICOS ===== */
        .agt-chart {
            margin: 15px 0;
            padding: 15px 20px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .agt-chart .chart-title {
            font-size: 14px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 12px;
            text-align: center;
        }
        
        .chart-bars-container {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            height: 160px;
            padding: 8px 0;
            border-bottom: 2px solid #1a2332;
        }
        
        .chart-bar-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            max-width: 70px;
        }
        
        .chart-bar {
            width: 35px;
            background: linear-gradient(to top, #c9a84c, #e8d07a);
            border-radius: 3px 3px 0 0;
            min-height: 5px;
            position: relative;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .chart-bar .bar-value {
            position: absolute;
            top: -18px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 9px;
            font-weight: 700;
            color: #1a2332;
            white-space: nowrap;
            background: rgba(255,255,255,0.9);
            padding: 1px 5px;
            border-radius: 3px;
            border: 1px solid #e2e8f0;
        }
        
        .chart-bar-label {
            font-size: 9px;
            color: #4a5568;
            margin-top: 4px;
            font-weight: 600;
        }
        
        .chart-bar-count {
            font-size: 8px;
            color: #94a3b8;
        }
        
        .chart-empty {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
            font-size: 13px;
        }

        /* ===== PROGRESS ===== */
        .progress-item {
            margin: 6px 0;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #4a5568;
            margin-bottom: 2px;
        }
        
        .progress-label .progress-nome {
            color: #1a2332;
        }
        
        .progress-label .progress-values {
            color: #94a3b8;
        }
        
        .progress-track {
            width: 100%;
            height: 18px;
            background: #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-track .progress-fill {
            height: 100%;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 6px;
            font-size: 9px;
            font-weight: 600;
            color: #fff;
            min-width: 30px;
            transition: width 0.8s ease;
        }

        /* ===== TABELA ===== */
        .agt-table-section {
            margin-top: 20px;
        }
        
        .table-title {
            font-size: 15px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #c9a84c;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .table-title .table-count {
            background: #c9a84c;
            color: #1a2332;
            padding: 1px 14px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .agt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        
        .agt-table th {
            background: #1a2332;
            color: #fff;
            padding: 6px 10px;
            text-align: left;
            font-weight: 600;
        }
        
        .agt-table td {
            padding: 5px 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .agt-table tr:hover {
            background: #f8fafc;
        }
        
        .agt-table .total-row {
            background: #f8fafc;
            font-weight: 700;
        }
        
        .agt-table .total-row td {
            border-top: 2px solid #1a2332;
        }
        
        .agt-table .valor {
            color: #2ecc71;
            font-weight: 600;
        }
        
        .badge {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        .badge-forma {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 600;
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        
        .empty-state .empty-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            color: #4a5568;
            margin: 0 0 5px;
        }

        /* ===== VALOR POR EXTENSO ===== */
        .agt-extenso {
            margin-top: 15px;
            padding: 10px 15px;
            background: #fef9e8;
            border: 1px solid #fde68a;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .agt-extenso .extenso-label {
            font-weight: 600;
            color: #92400e;
        }
        
        .agt-extenso .extenso-text {
            color: #1a2332;
        }

        /* ===== RODAPÉ ===== */
        .agt-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .agt-footer .assinatura {
            text-align: center;
            min-width: 150px;
        }
        
        .agt-footer .linha-assinatura {
            width: 180px;
            border-top: 1px solid #1a2332;
            margin: 0 auto 5px;
        }
        
        .agt-footer .assinatura-label {
            font-size: 10px;
            color: #94a3b8;
        }
        
        .agt-footer .carimbo {
            text-align: center;
            border: 2px dashed #c9a84c;
            padding: 8px 20px;
            border-radius: 4px;
            min-width: 150px;
        }
        
        .agt-footer .carimbo-texto {
            font-size: 12px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .agt-footer .carimbo-sub {
            font-size: 9px;
            color: #94a3b8;
        }
        
        .agt-footer .info-geracao {
            text-align: right;
            font-size: 10px;
            color: #94a3b8;
        }
        
        .agt-rodape {
            text-align: center;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
        }
        
        .agt-rodape span {
            margin: 0 5px;
        }

        /* ===== BOTÕES ===== */
        .no-print {
            text-align: center;
            padding: 20px;
            margin-top: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        
        .no-print .btn {
            padding: 10px 30px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn-print { background: #1a2332; color: #fff; }
        .btn-print:hover { background: #2d3748; transform: translateY(-2px); }
        .btn-close { background: #e74c3c; color: #fff; }
        .btn-close:hover { background: #c0392b; transform: translateY(-2px); }
        .btn-back { background: #f1f5f9; color: #4a5568; }
        .btn-back:hover { background: #e2e8f0; transform: translateY(-2px); }
        .btn-filter { background: #c9a84c; color: #1a2332; }
        .btn-filter:hover { background: #b8973a; transform: translateY(-2px); }

        /* ===== FILTROS DINÂMICOS ===== */
        .filtros-dinamicos {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
        }
        
        .filtros-dinamicos .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            align-items: end;
        }
        
        .filtros-dinamicos label {
            font-size: 11px;
            font-weight: 600;
            color: #4a5568;
            display: block;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filtros-dinamicos input,
        .filtros-dinamicos select {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 12px;
            background: #fff;
        }
        
        .filtros-dinamicos input:focus,
        .filtros-dinamicos select:focus {
            border-color: #c9a84c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
        }
        
        .filtros-dinamicos .btn-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* ===== RESPONSIVO ===== */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .print-container.agt-style { padding: 15px; }
            .agt-stats { grid-template-columns: 1fr 1fr; }
            .agt-header-top { flex-direction: column; text-align: center; }
            .agt-documento { text-align: center; }
            .agt-header-info { flex-direction: column; gap: 5px; align-items: center; }
            .agt-periodo { flex-direction: column; text-align: center; }
            .agt-periodo .filtros-info { justify-content: center; }
            .chart-bars-container { height: 120px; }
            .chart-bar { width: 25px; }
            .agt-footer { flex-direction: column; align-items: center; }
            .agt-footer .info-geracao { text-align: center; }
            .no-print .btn { width: 100%; margin: 5px 0; }
            .filtros-dinamicos .filtros-grid { grid-template-columns: 1fr; }
            .filtros-dinamicos .btn-group { justify-content: center; }
            .agt-table { font-size: 9px; }
            .agt-table th, .agt-table td { padding: 3px 5px; }
        }

        /* ===== IMPRESSÃO ===== */
        @media print {
            body { background: #fff !important; padding: 0 !important; }
            .print-container.agt-style { box-shadow: none !important; padding: 10px !important; border-radius: 0 !important; border: none !important; }
            .no-print { display: none !important; }
            .page-container { max-width: 100% !important; }
            .agt-table { font-size: 9px !important; }
            .agt-table th, .agt-table td { padding: 3px 5px !important; }
            .agt-stats { page-break-inside: avoid; }
            .agt-chart { page-break-inside: avoid; }
            .stat-box { padding: 10px !important; }
            .stat-box .stat-number { font-size: 18px !important; }
            .chart-bar { width: 30px !important; }
            .chart-bars-container { height: 140px !important; }
            .agt-footer { margin-top: 15px !important; padding-top: 10px !important; }
            .agt-rodape { margin-top: 8px !important; padding-top: 6px !important; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- ===== FILTROS DINÂMICOS ===== -->
        <div class="filtros-dinamicos no-print">
            <form method="GET" action="">
                <div class="filtros-grid">
                    <div>
                        <label for="data_inicio">Data Início</label>
                        <input type="date" name="data_inicio" id="data_inicio" value="<?= $filtro_data_inicio ?>">
                    </div>
                    <div>
                        <label for="data_fim">Data Fim</label>
                        <input type="date" name="data_fim" id="data_fim" value="<?= $filtro_data_fim ?>">
                    </div>
                    <div>
                        <label for="aluno_id">Aluno</label>
                        <select name="aluno_id" id="aluno_id">
                            <option value="0">Todos</option>
                            <?php foreach($alunos_lista as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= $filtro_aluno == $a['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Todos</option>
                            <option value="confirmado" <?= $filtro_status == 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                            <option value="pendente" <?= $filtro_status == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="cancelado" <?= $filtro_status == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div>
                        <label for="forma">Forma de Pagamento</label>
                        <select name="forma" id="forma">
                            <option value="">Todas</option>
                            <option value="dinheiro" <?= $filtro_forma == 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                            <option value="transferencia" <?= $filtro_forma == 'transferencia' ? 'selected' : '' ?>>Transferência</option>
                            <option value="pix" <?= $filtro_forma == 'pix' ? 'selected' : '' ?>>PIX</option>
                            <option value="cartao" <?= $filtro_forma == 'cartao' ? 'selected' : '' ?>>Cartão</option>
                            <option value="boleto" <?= $filtro_forma == 'boleto' ? 'selected' : '' ?>>Boleto</option>
                        </select>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-filter">🔍 Filtrar</button>
                        <a href="visualizar_impressao.php" class="btn btn-back">✕ Limpar</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- ===== CONTEÚDO DO RELATÓRIO ===== -->
        <?= gerarConteudoRelatorioAGT($pagamentos, $total_geral, $total_confirmados, $total_pendentes, $total_por_forma, $total_por_mes, $filtro_data_inicio, $filtro_data_fim, $filtro_aluno, $filtro_status, $filtro_forma, $nome_aluno, $dados_empresa) ?>
        
        <!-- ===== BOTÕES ===== -->
        <div class="no-print">
            <button class="btn btn-print" onclick="imprimirRelatorio()">🖨️ Imprimir</button>
            <button class="btn btn-back" onclick="window.location.href='pagamentos.php'">← Voltar</button>
            <button class="btn btn-close" onclick="window.close()">✕ Fechar</button>
        </div>
    </div>

    <script>
        function imprimirRelatorio() {
            window.print();
        }

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                imprimirRelatorio();
            }
            if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>
</html>