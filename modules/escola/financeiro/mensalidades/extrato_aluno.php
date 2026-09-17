<?php
// ============================================
// extrato_aluno.php - Exibe extrato do aluno com totais
// ============================================

// ===== 1. CARREGAR CONFIGURAÇÕES =====
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

// ===== 2. SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    die('<div style="padding:20px;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:5px;">
        <h4>❌ Acesso negado</h4>
        <p>Faça login para acessar esta página.</p>
    </div>');
}

// ===== 3. GARANTIR CONEXÃO =====
if (!isset($pdo) || !$pdo) {
    try {
        $pdo = conectarBanco();
    } catch (Exception $e) {
        die('<div style="padding:20px;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:5px;">
            <h4>❌ Erro de conexão</h4>
            <p>' . $e->getMessage() . '</p>
        </div>');
    }
}

// ===== 4. BUSCAR DADOS DA EMPRESA =====
$empresa = [
    'nome' => 'COMPLEXO ESCOLAR CASTELO REIS',
    'endereco' => '',
    'telefone' => '',
    'email' => '',
    'nif' => '',
    'cidade' => '',
    'provincia' => '',
    'logo' => ''
];

try {
    $stmt = $pdo->prepare("SELECT * FROM empresa WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $empresa_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($empresa_data) {
        $empresa['nome'] = $empresa_data['nome_fantasia'] ?? $empresa_data['razao_social'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';
        $empresa['endereco'] = $empresa_data['endereco'] ?? '';
        $empresa['telefone'] = $empresa_data['telefone'] ?? $empresa_data['celular'] ?? '';
        $empresa['email'] = $empresa_data['email'] ?? '';
        $empresa['nif'] = $empresa_data['cnpj'] ?? $empresa_data['inscricao_estadual'] ?? '';
        $empresa['cidade'] = $empresa_data['cidade'] ?? '';
        $empresa['provincia'] = $empresa_data['estado'] ?? '';
        $empresa['logo'] = $empresa_data['logo'] ?? '';
    }
} catch (Exception $e) {
    error_log("Erro ao buscar dados da empresa: " . $e->getMessage());
}

// ===== 5. FUNÇÕES AUXILIARES =====
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

// ===== 6. RECEBER PARÂMETROS =====
$aluno_id = isset($_GET['aluno_id']) ? (int)$_GET['aluno_id'] : 0;

if ($aluno_id <= 0) {
    die('<div class="alert alert-danger" style="padding:20px;margin:20px;">ID do aluno inválido</div>');
}

// ===== 7. BUSCAR DADOS DO ALUNO =====
$aluno = null;
try {
    $stmt = $pdo->prepare("SELECT id, nome, Classe, TURMA, Contacto4 as telefone_responsavel, Nome_do_Pai, Nome_da_mae FROM alunos WHERE id = ?");
    $stmt->execute([$aluno_id]);
    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('<div class="alert alert-danger" style="padding:20px;margin:20px;">Erro ao buscar aluno: ' . $e->getMessage() . '</div>');
}

if (!$aluno) {
    die('<div class="alert alert-danger" style="padding:20px;margin:20px;">Aluno não encontrado</div>');
}

// ===== 8. BUSCAR PAGAMENTOS DO ALUNO =====
$pagamentos = [];
$total_pago = 0;
try {
    $stmt = $pdo->prepare("
        SELECT p.*, e.nome as emolumento_nome 
        FROM pagamentos p 
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id 
        WHERE p.aluno_id = ? 
        AND p.status = 'confirmado'
        ORDER BY p.data_pagamento DESC
    ");
    $stmt->execute([$aluno_id]);
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pagamentos as $p) {
        $total_pago += (float)$p['valor'];
    }
} catch (Exception $e) {
    $pagamentos = [];
}

// ===== 9. BUSCAR MENSALIDADES PENDENTES =====
$mensalidades = [];
$total_pendente = 0;
try {
    $stmt = $pdo->prepare("
        SELECT m.*, e.nome as emolumento_nome 
        FROM mensalidades m 
        LEFT JOIN emolumentos e ON m.emolumento_id = e.id 
        WHERE m.aluno_id = ? 
        AND m.status != 'pago'
        AND m.status != 'cancelado'
        ORDER BY m.data_vencimento ASC
    ");
    $stmt->execute([$aluno_id]);
    $mensalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($mensalidades as $m) {
        $total_pendente += (float)$m['valor'];
    }
} catch (Exception $e) {
    $mensalidades = [];
}

// ===== 10. CALCULAR TOTAIS =====
$total_geral = $total_pago + $total_pendente;
$usuario_nome = $_SESSION['usuario_nome'] ?? '';

// ===== 11. DETERMINAR SITUAÇÃO =====
$situacao = 'EM DIA';
$situacao_classe = 'text-success';
if (count($mensalidades) > 0) {
    $situacao = count($mensalidades) . ' PENDENTE(S)';
    $situacao_classe = 'text-danger';
}

// ===== 12. DADOS PARA O CABEÇALHO =====
$empresa_nome = $empresa['nome'];
$empresa_endereco = $empresa['endereco'];
$empresa_telefone = $empresa['telefone'];
$empresa_email = $empresa['email'];
$empresa_nif = $empresa['nif'];
$empresa_cidade = $empresa['cidade'];
$empresa_provincia = $empresa['provincia'];

// Montar endereço completo
$endereco_completo = $empresa_endereco;
if (!empty($empresa_cidade)) {
    $endereco_completo .= (!empty($endereco_completo) ? ', ' : '') . $empresa_cidade;
}
if (!empty($empresa_provincia)) {
    $endereco_completo .= (!empty($endereco_completo) ? ', ' : '') . $empresa_provincia;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extrato do Aluno - <?= htmlspecialchars($aluno['nome']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== CONFIGURAÇÃO PARA IMPRESSÃO A4 ===== */
        @page {
            size: A4;
            margin: 15mm 20mm 15mm 20mm;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Times New Roman', 'Segoe UI', Arial, sans-serif; 
            background: #f4f6f9; 
            padding: 20px;
            font-size: 12pt;
        }
        
        .extrato-container { 
            max-width: 210mm; 
            margin: 0 auto; 
            background: white; 
            padding: 25px 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            min-height: 297mm;
        }
        
        /* ===== CABEÇALHO ===== */
        .header-extrato {
            text-align: center;
            border-bottom: 3px double #1a2332;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header-extrato .escola-nome {
            font-size: 18pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #1a2332;
            letter-spacing: 2px;
        }
        .header-extrato .escola-info {
            font-size: 10pt;
            color: #555;
            margin-top: 2px;
        }
        .header-extrato .titulo {
            font-size: 16pt;
            font-weight: 700;
            margin-top: 8px;
            color: #c9a84c;
            letter-spacing: 3px;
        }
        .header-extrato .data-emissao {
            font-size: 10pt;
            color: #777;
            margin-top: 3px;
        }
        
        /* ===== INFO ALUNO ===== */
        .info-aluno { 
            background: #f8f9fa; 
            padding: 10px 15px; 
            border-radius: 6px; 
            margin-bottom: 15px; 
            border-left: 4px solid #c9a84c; 
        }
        .info-aluno h5 { 
            color: #1a2332; 
            margin: 0 0 3px 0; 
            font-size: 13pt;
        }
        .info-aluno small { 
            color: #6c757d; 
            font-size: 10pt;
        }
        
        /* ===== RESUMO DE TOTAIS ===== */
        .resumo-extrato { 
            background: #f8f9fa; 
            border-radius: 8px; 
            padding: 12px 15px; 
            margin: 12px 0; 
            border: 1px solid #e9ecef; 
        }
        .resumo-extrato .item { 
            text-align: center; 
            padding: 3px; 
        }
        .resumo-extrato .item .numero { 
            font-size: 16pt; 
            font-weight: 700; 
        }
        .resumo-extrato .item .label { 
            font-size: 8pt; 
            color: #6c757d; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }
        .resumo-extrato .item .numero.text-success { color: #28a745; }
        .resumo-extrato .item .numero.text-danger { color: #dc3545; }
        .resumo-extrato .item .numero.text-warning { color: #f39c12; }
        .resumo-extrato .item .numero.text-primary { color: #0d6efd; }
        
        /* ===== TOTAL GERAL BOX ===== */
        .total-geral-box {
            background: linear-gradient(135deg, #1a2332 0%, #2d3748 100%);
            color: white;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 12px 0;
        }
        .total-geral-box .item { text-align: center; padding: 3px; }
        .total-geral-box .item .numero { font-size: 16pt; font-weight: 700; }
        .total-geral-box .item .label { font-size: 8pt; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.5px; }
        .total-geral-box .item .numero.text-success { color: #2ecc71; }
        .total-geral-box .item .numero.text-danger { color: #e74c3c; }
        .total-geral-box .item .numero.text-warning { color: #f1c40f; }
        
        /* ===== TABELAS ===== */
        .extrato-tabela { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 10pt; 
            margin: 8px 0; 
        }
        .extrato-tabela th { 
            background: #1a2332; 
            color: white; 
            padding: 6px 10px; 
            font-size: 8pt; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            border: 1px solid #1a2332;
        }
        .extrato-tabela td { 
            padding: 5px 10px; 
            border-bottom: 1px solid #e9ecef; 
            vertical-align: middle; 
        }
        .extrato-tabela tr:hover { background: #f8f9fa; }
        .extrato-tabela .subtotal-row { 
            background: #f8f9fa; 
            font-weight: bold; 
            border-top: 2px solid #1a2332;
        }
        
        .status-pago { color: #28a745; font-weight: 600; }
        .status-pendente { color: #f39c12; font-weight: 600; }
        .status-atrasado { color: #dc3545; font-weight: 600; }
        
        .text-success { color: #28a745; }
        .text-warning { color: #f39c12; }
        .text-danger { color: #dc3545; }
        
        /* ===== RESUMO FINAL ===== */
        .resumo-final {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 10px 15px;
            margin-top: 15px;
            border: 1px solid #e9ecef;
        }
        .resumo-final .item { text-align: center; }
        .resumo-final .item .valor { font-size: 14pt; font-weight: 700; }
        .resumo-final .item .label { font-size: 8pt; color: #6c757d; text-transform: uppercase; }
        
        /* ===== RODAPÉ ===== */
        .footer-extrato {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e9ecef;
            text-align: center;
            font-size: 9pt;
            color: #6c757d;
        }
        
        /* ===== BOTÕES ===== */
        .btn-imprimir { background: #1a2332; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-size: 12pt; font-weight: 600; transition: all 0.3s; }
        .btn-imprimir:hover { background: #2d3748; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(26,35,50,0.3); }
        .btn-voltar { background: #6c757d; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-size: 12pt; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-voltar:hover { background: #5a6268; color: white; }
        .btn-whats { background: #25D366; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-size: 12pt; font-weight: 600; transition: all 0.3s; }
        .btn-whats:hover { background: #1da851; transform: translateY(-2px); }
        
        /* ========================================== */
        /* ESTILOS PARA IMPRESSÃO A4 */
        /* ========================================== */
        @media print {
            body { 
                background: white !important; 
                padding: 0 !important;
                margin: 0 !important;
            }
            .extrato-container { 
                box-shadow: none !important; 
                border: none !important; 
                padding: 20px 25px !important;
                border-radius: 0 !important;
                min-height: auto !important;
                max-width: 100% !important;
            }
            .btn-imprimir, .btn-voltar, .btn-whats, .no-print { 
                display: none !important; 
            }
            .extrato-tabela th { 
                background: #1a2332 !important; 
                color: white !important; 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important; 
            }
            .header-extrato { 
                border-bottom: 2px double #000 !important; 
            }
            .total-geral-box { 
                background: #1a2332 !important; 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important; 
            }
            .info-aluno {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .resumo-extrato {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .resumo-final {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .extrato-tabela .subtotal-row {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .footer-extrato {
                border-top: 1px solid #ddd !important;
            }
            /* Evitar quebras de página */
            .extrato-tabela, .resumo-extrato, .total-geral-box, .resumo-final {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="extrato-container" id="extratoPrint">
    
    <!-- ===== CABEÇALHO ===== -->
    <div class="header-extrato">
        <div class="escola-nome"><?= htmlspecialchars($empresa_nome) ?></div>
        <div class="escola-info">
            <?php if (!empty($endereco_completo)): ?>
                <i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($endereco_completo) ?>
            <?php endif; ?>
            <?php if (!empty($empresa_telefone)): ?>
                <span class="mx-2">|</span>
                <i class="fas fa-phone me-1"></i> <?= htmlspecialchars($empresa_telefone) ?>
            <?php endif; ?>
            <?php if (!empty($empresa_email)): ?>
                <span class="mx-2">|</span>
                <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($empresa_email) ?>
            <?php endif; ?>
            <?php if (!empty($empresa_nif)): ?>
                <span class="mx-2">|</span>
                <i class="fas fa-id-card me-1"></i> NIF: <?= htmlspecialchars($empresa_nif) ?>
            <?php endif; ?>
        </div>
        <div class="titulo">📋 EXTRATO DO ALUNO</div>
        <div class="data-emissao">
            <i class="fas fa-calendar-alt me-1"></i> Emitido em: <?= date('d/m/Y H:i:s') ?>
        </div>
    </div>
    
    <!-- ===== INFO ALUNO ===== -->
    <div class="info-aluno">
        <h5><i class="fas fa-user-graduate me-2"></i> <?= htmlspecialchars($aluno['nome']) ?></h5>
        <small>
            <i class="fas fa-school me-1"></i> Classe: <?= htmlspecialchars($aluno['Classe']) ?>ª
            <span class="mx-2">|</span>
            <i class="fas fa-users me-1"></i> Turma: <?= htmlspecialchars($aluno['TURMA']) ?>
            <span class="mx-2">|</span>
            <i class="fas fa-phone me-1"></i> Encarregado: <?= htmlspecialchars($aluno['telefone_responsavel'] ?? 'Não informado') ?>
        </small>
        <input type="hidden" id="extratoTelefone" value="<?= htmlspecialchars($aluno['telefone_responsavel'] ?? '') ?>">
    </div>
    
    <!-- ===== RESUMO DE TOTAIS ===== -->
    <div class="resumo-extrato">
        <div class="row">
            <div class="col-3 item">
                <div class="numero text-success"><?= count($pagamentos) ?></div>
                <div class="label">Pagamentos</div>
            </div>
            <div class="col-3 item">
                <div class="numero text-warning"><?= count($mensalidades) ?></div>
                <div class="label">Pendentes</div>
            </div>
            <div class="col-3 item">
                <div class="numero text-primary"><?= formatarMoeda($total_pago) ?></div>
                <div class="label">Total Pago</div>
            </div>
            <div class="col-3 item">
                <div class="numero <?= $situacao_classe ?>"><?= $situacao ?></div>
                <div class="label">Situação</div>
            </div>
        </div>
    </div>
    
    <!-- ===== TOTAL GERAL ===== -->
    <div class="total-geral-box">
        <div class="row">
            <div class="col-4 item">
                <div class="numero text-success"><?= formatarMoeda($total_pago) ?></div>
                <div class="label">Total Pago</div>
            </div>
            <div class="col-4 item">
                <div class="numero text-danger"><?= formatarMoeda($total_pendente) ?></div>
                <div class="label">Total Pendente</div>
            </div>
            <div class="col-4 item">
                <div class="numero text-warning"><?= formatarMoeda($total_geral) ?></div>
                <div class="label">Total Geral</div>
            </div>
        </div>
    </div>
    
    <?php if (count($pagamentos) > 0 || count($mensalidades) > 0): ?>
    <div style="margin-top:12px;">
        
        <!-- ===== PAGAMENTOS REALIZADOS ===== -->
        <h6 style="margin-bottom:6px;font-size:11pt;font-weight:700;">
            <i class="fas fa-check-circle text-success me-2"></i> Pagamentos Realizados
        </h6>
        <table class="extrato-tabela">
            <thead>
                <tr>
                    <th style="width:15%;">Data</th>
                    <th style="width:35%;">Descrição</th>
                    <th style="width:20%;">Valor</th>
                    <th style="width:15%;">Status</th>
                    <th style="width:15%;">Forma</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pagamentos) > 0): ?>
                    <?php foreach ($pagamentos as $pag): ?>
                    <tr>
                        <td><?= formatarData($pag['data_pagamento']) ?></td>
                        <td><?= htmlspecialchars($pag['emolumento_nome'] ?? 'Pagamento') ?></td>
                        <td><strong><?= formatarMoeda($pag['valor']) ?></strong></td>
                        <td><span class="status-pago"><i class="fas fa-check-circle"></i> CONFIRMADO</span></td>
                        <td><?= formaPagamentoLabel($pag['forma_pagamento'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal-row">
                        <td colspan="2" style="text-align:right;">SUBTOTAL PAGO:</td>
                        <td colspan="3"><?= formatarMoeda($total_pago) ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-2">Nenhum pagamento registrado</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- ===== MENSALIDADES PENDENTES ===== -->
        <?php if (count($mensalidades) > 0): ?>
        <h6 class="mt-3" style="margin-bottom:6px;font-size:11pt;font-weight:700;">
            <i class="fas fa-clock text-warning me-2"></i> Mensalidades Pendentes
        </h6>
        <table class="extrato-tabela">
            <thead>
                <tr>
                    <th style="width:20%;">Mês/Ano</th>
                    <th style="width:30%;">Emolumento</th>
                    <th style="width:20%;">Valor</th>
                    <th style="width:20%;">Vencimento</th>
                    <th style="width:10%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mensalidades as $men): ?>
                    <?php $atrasado = strtotime($men['data_vencimento']) < time(); ?>
                <tr>
                    <td><?= mesNome($men['mes']) ?>/<?= $men['ano'] ?></td>
                    <td><?= htmlspecialchars($men['emolumento_nome'] ?? 'Mensalidade') ?></td>
                    <td><strong><?= formatarMoeda($men['valor']) ?></strong></td>
                    <td><?= formatarData($men['data_vencimento']) ?></td>
                    <td>
                        <?php if ($atrasado): ?>
                            <span class="status-atrasado"><i class="fas fa-exclamation-triangle"></i> ATRASADO</span>
                        <?php else: ?>
                            <span class="status-pendente"><i class="fas fa-clock"></i> PENDENTE</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="subtotal-row">
                    <td colspan="2" style="text-align:right;">SUBTOTAL PENDENTE:</td>
                    <td colspan="3"><?= formatarMoeda($total_pendente) ?></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-4">
        <i class="fas fa-check-circle fa-4x text-success mb-2"></i>
        <h5 style="font-size:14pt;">Aluno em dia com todas as mensalidades!</h5>
        <p class="text-muted" style="font-size:10pt;">Nenhum pagamento pendente encontrado.</p>
    </div>
    <?php endif; ?>
    
    <!-- ===== RESUMO FINAL ===== -->
    <div class="resumo-final">
        <div class="row">
            <div class="col-4 item">
                <div class="label">TOTAL PAGO</div>
                <div class="valor text-success"><?= formatarMoeda($total_pago) ?></div>
            </div>
            <div class="col-4 item">
                <div class="label">TOTAL PENDENTE</div>
                <div class="valor text-danger"><?= formatarMoeda($total_pendente) ?></div>
            </div>
            <div class="col-4 item">
                <div class="label">TOTAL GERAL</div>
                <div class="valor text-primary"><?= formatarMoeda($total_geral) ?></div>
            </div>
        </div>
    </div>
    
    <!-- ===== RODAPÉ ===== -->
    <div class="footer-extrato">
        <div class="row">
            <div class="col-6 text-start">
                <small>Documento emitido eletronicamente</small>
            </div>
            <div class="col-6 text-end">
                <small><?= htmlspecialchars($empresa_nome) ?> © <?= date('Y') ?></small>
            </div>
        </div>
    </div>
    
    <!-- ===== BOTÕES ===== -->
    <div class="text-center mt-3 no-print" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <button onclick="window.print()" class="btn-imprimir">
            <i class="fas fa-print me-2"></i> Imprimir Extrato
        </button>
        <?php if (!empty($aluno['telefone_responsavel'])): ?>
        <button onclick="enviarWhatsApp()" class="btn-whats">
            <i class="fab fa-whatsapp me-2"></i> Enviar via WhatsApp
        </button>
        <?php endif; ?>
        <button onclick="window.close()" class="btn-voltar">
            <i class="fas fa-times me-2"></i> Fechar
        </button>
    </div>
</div>

<script>
function enviarWhatsApp() {
    var telefone = document.getElementById('extratoTelefone').value;
    if (!telefone) {
        alert('⚠️ Número de telefone não cadastrado!');
        return;
    }
    
    var numero = telefone.replace(/\D/g, '');
    if (numero.length === 9) {
        numero = '244' + numero;
    }
    
    var mensagem = '📋 *EXTRATO DO ALUNO*\n\n';
    mensagem += '📍 *Escola:* <?= htmlspecialchars($empresa_nome) ?>\n';
    mensagem += '👤 *Aluno:* <?= htmlspecialchars($aluno['nome']) ?>\n';
    mensagem += '📚 *Classe:* <?= htmlspecialchars($aluno['Classe']) ?>ª\n';
    mensagem += '🏫 *Turma:* <?= htmlspecialchars($aluno['TURMA']) ?>\n\n';
    mensagem += '📊 *RESUMO FINANCEIRO:*\n';
    mensagem += '✅ Total Pago: <?= formatarMoeda($total_pago) ?>\n';
    mensagem += '⏳ Total Pendente: <?= formatarMoeda($total_pendente) ?>\n';
    mensagem += '📊 Total Geral: <?= formatarMoeda($total_geral) ?>\n';
    mensagem += '📋 Pagamentos: <?= count($pagamentos) ?>\n';
    mensagem += '⏳ Pendentes: <?= count($mensalidades) ?>\n\n';
    mensagem += '📅 *Data:* <?= date('d/m/Y H:i:s') ?>\n';
    mensagem += '📌 *Aviso automático - Não responda a esta mensagem.*';
    
    var url = 'https://api.whatsapp.com/send?phone=' + numero + '&text=' + encodeURIComponent(mensagem);
    window.open(url, '_blank');
}
</script>

</body>
</html>