<?php
// ============================================
// modules/escola/financeiro/pagamentos/ver_conta_pendente.php
// Visualização detalhada da conta pendente de cada aluno
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

$erro = '';
$sucesso = '';
$alunos = [];
$emolumentos = [];
$total_geral = 0;

include '../../includes/header_escola.php';

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function limparString($texto) {
    if (empty($texto)) return '';
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'auto');
    }
    $texto = preg_replace('/[[:cntrl:]]/', '', $texto);
    $texto = str_replace(['?', '�', '�', '�', '�', '�', '�'], '', $texto);
    $texto = trim($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return $texto;
}

function normalizarClasse($classe) {
    if (empty($classe)) return '';
    $classe = limparString($classe);
    $classe = trim($classe);
    $classe = str_replace(' ', '', $classe);
    $classe = str_replace(['?', '�', '�', '�', '�', '�', '�', '?'], '', $classe);
    
    if (preg_match('/^PRE/i', $classe)) return 'Pré';
    if (preg_match('/^1/i', $classe)) return '1ª';
    if (preg_match('/^2/i', $classe)) return '2ª';
    if (preg_match('/^3/i', $classe)) return '3ª';
    if (preg_match('/^4/i', $classe)) return '4ª';
    if (preg_match('/^5/i', $classe)) return '5ª';
    if (preg_match('/^6/i', $classe)) return '6ª';
    if (preg_match('/^7/i', $classe)) return '7ª';
    if (preg_match('/^8/i', $classe)) return '8ª';
    if (preg_match('/^9/i', $classe)) return '9ª';
    if (preg_match('/^10/i', $classe)) return '10ª';
    if (preg_match('/^11/i', $classe)) return '11ª';
    if (preg_match('/^12/i', $classe)) return '12ª';
    return $classe;
}

function normalizarPeriodo($periodo) {
    if (empty($periodo)) return 'Manhã';
    $periodo = limparString($periodo);
    $periodo = trim($periodo);
    if (stripos($periodo, 'manh') !== false) return 'Manhã';
    if (stripos($periodo, 'tard') !== false) return 'Tarde';
    if (stripos($periodo, 'noit') !== false) return 'Noite';
    return 'Manhã';
}

function normalizarStatus($status) {
    if (empty($status)) return 'Pendente';
    $status = limparString($status);
    $status = strtolower($status);
    if ($status == 'pago' || $status == 'confirmado') return 'Pago';
    if ($status == 'pendente' || $status == 'pendent' || $status == 'nulo' || $status == 'null') return 'Pendente';
    if ($status == 'cancelado' || $status == 'cancel') return 'Cancelado';
    if ($status == 'isento') return 'Isento';
    return ucfirst($status);
}

function getStatusBadge($status) {
    $status_clean = normalizarStatus($status);
    $colors = [
        'Pago' => 'background:#d1fae5;color:#065f46;',
        'Pendente' => 'background:#fef3c7;color:#92400e;',
        'Cancelado' => 'background:#fee2e2;color:#991b1b;',
        'Isento' => 'background:#e5e7eb;color:#4a5568;'
    ];
    $color = $colors[$status_clean] ?? 'background:#f1f5f9;color:#4a5568;';
    return '<span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:10px;font-weight:600;' . $color . '">' . $status_clean . '</span>';
}

// ============================================
// CARREGAR EMOLUMENTOS DA TABELA
// ============================================
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'emolumentos_anterior'");
    $tabela_emolumentos = $stmt->rowCount() > 0;
    
    if ($tabela_emolumentos) {
        $stmt = $pdo->query("SELECT * FROM emolumentos_anterior ORDER BY Classe, Descricao, Periodo");
        while ($row = $stmt->fetch()) {
            $classe = normalizarClasse($row['Classe']);
            $descricao = limparString($row['Descricao']);
            $periodo = normalizarPeriodo($row['Periodo']);
            $valor = floatval($row['Valor']);
            
            $key = $classe . '|' . $descricao . '|' . $periodo;
            $emolumentos[$key] = $valor;
        }
    }
} catch (Exception $e) {
    $erro = 'Erro ao carregar emolumentos: ' . $e->getMessage();
}

// ============================================
// FUNÇÃO PARA BUSCAR VALOR DO EMOLUMENTO
// ============================================
function buscarValorEmolumento($classe, $descricao, $periodo, $emolumentos) {
    $classe = normalizarClasse($classe);
    $periodo = normalizarPeriodo($periodo);
    $descricao = limparString($descricao);
    
    // Mapeamento de descrições
    $mapa_descricao = [
        'folha de prova 1º trimestre' => 'Folha de Prova',
        'folha de prova 2º trimestre' => 'Folha de Prova',
        'folha de prova 3º trimestre' => 'Folha de Prova',
        'boletim de notas 1º trimestre' => 'Boletim de Notas',
        'boletim de notas 2º trimestre' => 'Boletim de Notas',
        'cartão' => 'Cartão',
        'propina' => 'Propina',
        'transporte' => 'Transporte'
    ];
    
    $desc_lower = strtolower($descricao);
    $desc_normalizada = $descricao;
    
    foreach ($mapa_descricao as $key => $value) {
        if (strpos($desc_lower, strtolower($key)) !== false) {
            $desc_normalizada = $value;
            break;
        }
    }
    
    $keys_to_try = [
        $classe . '|' . $desc_normalizada . '|' . $periodo,
        $classe . '|' . $desc_normalizada . '|Manhã',
        $classe . '|' . $descricao . '|' . $periodo,
        $classe . '|' . $descricao . '|Manhã'
    ];
    
    foreach ($keys_to_try as $key) {
        if (isset($emolumentos[$key])) {
            return $emolumentos[$key];
        }
    }
    
    return 0;
}

// ============================================
// BUSCAR ALUNOS DA TABELA pendencias_anterior
// ============================================
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'pendencias_anterior'");
    $tabela_pendencias = $stmt->rowCount() > 0;
    
    if ($tabela_pendencias) {
        $sql = "SELECT * FROM pendencias_anterior ORDER BY classe, nome_aluno";
        $stmt = $pdo->query($sql);
        $alunos = $stmt->fetchAll();
        
        // Calcular totais para cada aluno
        foreach ($alunos as &$a) {
            $classe = normalizarClasse($a['classe'] ?? '');
            $periodo = normalizarPeriodo($a['periodo'] ?? 'Manhã');
            $nome = limparString($a['nome_aluno']);
            
            // --- VALORES DOS EMOLUMENTOS ---
            $valor_unitario_propina = buscarValorEmolumento($classe, 'Propina', $periodo, $emolumentos);
            $valor_unitario_transporte = buscarValorEmolumento($classe, 'Transporte', $periodo, $emolumentos);
            $valor_unitario_prova = buscarValorEmolumento($classe, 'Folha de Prova', $periodo, $emolumentos);
            $valor_unitario_boletim = buscarValorEmolumento($classe, 'Boletim de Notas', $periodo, $emolumentos);
            $valor_unitario_cartao = buscarValorEmolumento($classe, 'Cartão', $periodo, $emolumentos);
            
            // --- PROPINA ---
            $status_propina = normalizarStatus($a['propina_status'] ?? 'Pendente');
            $qtd_propina = intval($a['propina_quantidade'] ?? 0);
            if ($status_propina == 'Pago' || $qtd_propina <= 0) {
                $a['valor_propina'] = 0;
            } else {
                $a['valor_propina'] = $valor_unitario_propina * $qtd_propina;
            }
            
            // --- TRANSPORTE ---
            $status_transporte = normalizarStatus($a['transporte_status'] ?? 'Pendente');
            $qtd_transporte = intval($a['transporte_quantidade'] ?? 0);
            if ($status_transporte == 'Pago' || $qtd_transporte <= 0) {
                $a['valor_transporte'] = 0;
            } else {
                $a['valor_transporte'] = $valor_unitario_transporte * $qtd_transporte;
            }
            
            // --- FOLHA DE PROVA 1º TRIMESTRE ---
            $status_prova1 = normalizarStatus($a['folha_prova_1_status'] ?? 'Pendente');
            $a['valor_prova1'] = ($status_prova1 == 'Pago') ? 0 : $valor_unitario_prova;
            
            // --- FOLHA DE PROVA 2º TRIMESTRE ---
            $status_prova2 = normalizarStatus($a['folha_prova_2_status'] ?? 'Pendente');
            $a['valor_prova2'] = ($status_prova2 == 'Pago') ? 0 : $valor_unitario_prova;
            
            // --- FOLHA DE PROVA 3º TRIMESTRE ---
            $status_prova3 = normalizarStatus($a['folha_prova_3_status'] ?? 'Pendente');
            $a['valor_prova3'] = ($status_prova3 == 'Pago') ? 0 : $valor_unitario_prova;
            
            // --- BOLETIM DE NOTAS 1º TRIMESTRE ---
            $status_boletim1 = normalizarStatus($a['boletim_notas_1_status'] ?? 'Pendente');
            $a['valor_boletim1'] = ($status_boletim1 == 'Pago') ? 0 : $valor_unitario_boletim;
            
            // --- BOLETIM DE NOTAS 2º TRIMESTRE ---
            $status_boletim2 = normalizarStatus($a['boletim_notas_2_status'] ?? 'Pendente');
            $a['valor_boletim2'] = ($status_boletim2 == 'Pago') ? 0 : $valor_unitario_boletim;
            
            // --- CARTÃO ---
            $status_cartao = normalizarStatus($a['cartao_status'] ?? 'Pendente');
            $a['valor_cartao'] = ($status_cartao == 'Pago') ? 0 : $valor_unitario_cartao;
            
            // Total do aluno
            $a['total_pendente'] = 
                $a['valor_propina'] + 
                $a['valor_transporte'] + 
                $a['valor_prova1'] + 
                $a['valor_prova2'] + 
                $a['valor_prova3'] + 
                $a['valor_boletim1'] + 
                $a['valor_boletim2'] + 
                $a['valor_cartao'];
        }
        
        // Calcular total geral
        foreach ($alunos as $a) {
            $total_geral += $a['total_pendente'];
        }
    }
} catch (Exception $e) {
    $erro = 'Erro ao buscar alunos: ' . $e->getMessage();
}

// ============================================
// ESTATÍSTICAS
// ============================================
$com_pendencia = 0;
$sem_pendencia = 0;
foreach ($alunos as $a) {
    if ($a['total_pendente'] > 0) $com_pendencia++;
    else $sem_pendencia++;
}
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
.page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
.page-header .subtitle{color:#94a3b8;font-size:14px;margin:2px 0 0}
.btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
.btn-secondary{background:#f1f5f9;color:#4a5568}
.btn-secondary:hover{background:#e2e8f0}
.btn-success{background:#2ecc71;color:#fff}
.btn-success:hover{background:#27ae60}
.btn-primary{background:#c9a84c;color:#1a2332}
.btn-primary:hover{background:#b8973d;color:#1a2332}
.btn-info{background:#3498db;color:#fff}
.btn-info:hover{background:#2980b9}
.btn-sm{padding:4px 12px;font-size:11px;border-radius:6px}
.stats-bar{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
.stats-bar .stat-item{display:flex;align-items:center;gap:8px;font-size:14px;color:#4a5568}
.stats-bar .stat-item .number{font-weight:700;font-size:18px;color:#1a2332}
.table-responsive{overflow-x:auto;background:#fff;border-radius:12px;border:1px solid #eef2f7}
.table{width:100%;border-collapse:collapse;font-size:11px;min-width:1600px}
.table th{background:#f8fafc;padding:6px 8px;text-align:left;font-weight:600;color:#4a5568;border-bottom:2px solid #e2e8f0;white-space:nowrap;font-size:9px;text-transform:uppercase;letter-spacing:.3px}
.table td{padding:6px 8px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.table tr:hover{background:#fafbfc}
.table tr.pendente{background:#fffbeb}
.table tr.pendente:hover{background:#fef3c7}
.table .total-row{background:#f8fafc;font-weight:700}
.table .total-row td{border-top:2px solid #c9a84c}
.empty-state{text-align:center;padding:60px 20px;color:#94a3b8}
.empty-state .icon{font-size:64px;display:block;margin-bottom:15px}
.empty-state h3{font-size:20px;color:#4a5568;margin:0 0 5px}
.empty-state p{color:#94a3b8;margin:0 0 15px}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.alert-info{background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe}
.alert-warning{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.nav-alunos{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:25px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
.nav-alunos a{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;transition:all .3s;color:#4a5568;background:#f8fafc;border:1px solid #e2e8f0;display:inline-flex;align-items:center;gap:6px}
.nav-alunos a:hover{background:#c9a84c;color:#1a2332;border-color:#c9a84c;transform:translateY(-2px)}
.nav-alunos a.active{background:#c9a84c;color:#1a2332;border-color:#c9a84c}
.acoes-rapidas{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;padding:15px;background:#f8fafc;border-radius:12px;border:1px solid #eef2f7;align-items:center;justify-content:center}
.footer{text-align:center;padding:20px;color:#94a3b8;font-size:12px;border-top:1px solid #eef2f7;margin-top:20px}
.valor-pendente{color:#e74c3c;font-weight:700}
.valor-pago{color:#2ecc71;font-weight:700}
.valor-total{color:#c9a84c;font-weight:700;font-size:14px}
@media(max-width:768px){
.page-header{flex-direction:column;align-items:stretch}
.nav-alunos{flex-direction:column;align-items:stretch}
.nav-alunos a{text-align:center;justify-content:center}
.table{font-size:10px;min-width:1300px}
.table th,.table td{padding:4px 5px}
.btn-sm{font-size:9px;padding:2px 6px}
.acoes-rapidas{flex-direction:column;align-items:stretch}
.acoes-rapidas .btn{justify-content:center}
.stats-bar{flex-direction:column;gap:10px}
}
</style>

<div class="container">
    <div class="page-header">
        <div>
            <h1>💰 Ver Conta Pendente</h1>
            <p class="subtitle">Cálculo detalhado de valores pendentes por aluno</p>
        </div>
        <div class="btn-group">
            <a href="importados_pendencias.php" class="btn btn-secondary">← Voltar</a>
            <a href="?exportar=pdf" class="btn btn-primary">📄 Exportar PDF</a>
            <a href="?exportar=excel" class="btn btn-success">📊 Exportar Excel</a>
        </div>
    </div>

    <!-- Navegação -->
    <div class="nav-alunos">
        <a href="index.php">📋 Lista de Alunos</a>
        <a href="add.php">➕ Cadastrar Aluno</a>
        <a href="importados_pendencias.php">📋 Pendências</a>
        <a href="ver_conta_pendente.php" class="active">💰 Ver Conta</a>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Resumo -->
    <div class="stats-bar">
        <div class="stat-item">
            <span class="number"><?= count($alunos) ?></span>
            <span class="label">Total de Alunos</span>
        </div>
        <div class="stat-item">
            <span class="number" style="color:#c9a84c;"><?= number_format($total_geral, 2, ',', '.') ?> Kz</span>
            <span class="label">Total Geral Pendente</span>
        </div>
        <div class="stat-item">
            <span class="number" style="color:#e74c3c;"><?= $com_pendencia ?></span>
            <span class="label">Alunos com Pendência</span>
        </div>
        <div class="stat-item">
            <span class="number" style="color:#2ecc71;"><?= $sem_pendencia ?></span>
            <span class="label">Alunos em Dia</span>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome do Aluno</th>
                    <th>Classe</th>
                    <th>Propina</th>
                    <th>Transporte</th>
                    <th>1º Prova</th>
                    <th>2º Prova</th>
                    <th>3º Prova</th>
                    <th>1º Boletim</th>
                    <th>2º Boletim</th>
                    <th>Cartão</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($alunos) > 0): ?>
                    <?php foreach($alunos as $index => $a): 
                        $classe_atual = normalizarClasse($a['classe'] ?? '');
                        $periodo = normalizarPeriodo($a['periodo'] ?? 'Manhã');
                        $total = $a['total_pendente'] ?? 0;
                        $status_geral = $total > 0 ? 'Pendente' : 'Em Dia';
                        $row_class = $total > 0 ? 'pendente' : '';
                        
                        // Status para cada item
                        $status_propina = normalizarStatus($a['propina_status'] ?? 'Pendente');
                        $status_transporte = normalizarStatus($a['transporte_status'] ?? 'Pendente');
                        $status_prova1 = normalizarStatus($a['folha_prova_1_status'] ?? 'Pendente');
                        $status_prova2 = normalizarStatus($a['folha_prova_2_status'] ?? 'Pendente');
                        $status_prova3 = normalizarStatus($a['folha_prova_3_status'] ?? 'Pendente');
                        $status_boletim1 = normalizarStatus($a['boletim_notas_1_status'] ?? 'Pendente');
                        $status_boletim2 = normalizarStatus($a['boletim_notas_2_status'] ?? 'Pendente');
                        $status_cartao = normalizarStatus($a['cartao_status'] ?? 'Pendente');
                        
                        // Quantidades
                        $qtd_propina = intval($a['propina_quantidade'] ?? 0);
                        $qtd_transporte = intval($a['transporte_quantidade'] ?? 0);
                        
                        // Valores
                        $valor_propina = $a['valor_propina'] ?? 0;
                        $valor_transporte = $a['valor_transporte'] ?? 0;
                        $valor_prova1 = $a['valor_prova1'] ?? 0;
                        $valor_prova2 = $a['valor_prova2'] ?? 0;
                        $valor_prova3 = $a['valor_prova3'] ?? 0;
                        $valor_boletim1 = $a['valor_boletim1'] ?? 0;
                        $valor_boletim2 = $a['valor_boletim2'] ?? 0;
                        $valor_cartao = $a['valor_cartao'] ?? 0;
                        
                        $nome_exibicao = limparString($a['nome_aluno']);
                    ?>
                    <tr class="<?= $row_class ?>">
                        <td><strong><?= $index + 1 ?></strong></td>
                        <td><?= htmlspecialchars($nome_exibicao, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= htmlspecialchars($classe_atual, ENT_QUOTES, 'UTF-8') ?>
                            <span style="color:#94a3b8;font-size:10px;">(<?= $periodo ?>)</span>
                            <?php if ($qtd_propina > 0 || $qtd_transporte > 0): ?>
                                <br><span style="font-size:9px;color:#94a3b8;">
                                    <?php if ($qtd_propina > 0): ?>Prop: <?= $qtd_propina ?>m <?php endif; ?>
                                    <?php if ($qtd_transporte > 0): ?>| Trans: <?= $qtd_transporte ?>m<?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= getStatusBadge($status_propina) ?>
                            <br>
                            <span style="font-size:11px;color:<?= $valor_propina > 0 ? '#e74c3c' : '#2ecc71' ?>;">
                                <?= number_format($valor_propina, 2, ',', '.') ?> Kz
                            </span>
                        </td>
                        <td>
                            <?= getStatusBadge($status_transporte) ?>
                            <br>
                            <span style="font-size:11px;color:<?= $valor_transporte > 0 ? '#e74c3c' : '#2ecc71' ?>;">
                                <?= number_format($valor_transporte, 2, ',', '.') ?> Kz
                            </span>
                        </td>
                        <td>
                            <?= getStatusBadge($status_prova1) ?>
                            <br>
                            <span style="font-size:11px;color:<?= $valor_prova1 > 0 ? '#e74c3c' : '#2ecc71' ?>;">
                                <?= number_format($valor_prova1, 2, ',', '.') ?> Kz
                            </span>
                        </td>
                        <td>
                            <?= getStatusBadge($status_prova2) ?>
                            <br>
                            <span style="font-size:11px;color:<?= $valor_prova2 > 0 ? '#e74c3c' : '#2ecc71' ?>;">
                                <?= number_format($valor_prova2, 2, ',', '.') ?> Kz
                            </span>
                        </td>
                        <td>
                            <?= getStatusBadge($status_prova3) ?>
                            <br>
                            <span style="font-size:11px;color:<?= $valor_prova3 > 0 ? '#e74c3c' : '#2ecc71' ?>;">
                                <?= number_format($valor_prova3, 2, ',', '.') ?> Kz
                            </span>
                        </td>
                        <td>
                            <?= getStatusBadge($status_boletim1) ?>
                            <br>
                            <span style="font-size:11px;color:<?= $valor_boletim1 > 0 ? '#e74c3c' : '#2ecc71' ?>;">
                                <?= number_format($valor_boletim1, 2, ',', '.') ?> Kz
                            </span>
                        </td>
                        <td>
                            <?= getStatusBadge($status_boletim2) ?>
                            <br>
                            <span style="font-size:11px;color:<?= $valor_boletim2 > 0 ? '#e74c3c' : '#2ecc71' ?>;">
                                <?= number_format($valor_boletim2, 2, ',', '.') ?> Kz
                            </span>
                        </td>
                        <td>
                            <?= getStatusBadge($status_cartao) ?>
                            <br>
                            <span style="font-size:11px;color:<?= $valor_cartao > 0 ? '#e74c3c' : '#2ecc71' ?>;">
                                <?= number_format($valor_cartao, 2, ',', '.') ?> Kz
                            </span>
                        </td>
                        <td>
                            <span class="valor-<?= $total > 0 ? 'pendente' : 'pago' ?>">
                                <?= number_format($total, 2, ',', '.') ?> Kz
                            </span>
                        </td>
                        <td>
                            <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:11px;font-weight:600;<?= $total > 0 ? 'background:#fee2e2;color:#991b1b;' : 'background:#d1fae5;color:#065f46;' ?>">
                                <?= $status_geral ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Linha de Total -->
                    <tr class="total-row">
                        <td colspan="11" style="text-align:right;font-size:13px;">
                            <strong>TOTAL GERAL:</strong>
                        </td>
                        <td>
                            <span class="valor-total">
                                <?= number_format($total_geral, 2, ',', '.') ?> Kz
                            </span>
                        </td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="13">
                            <div class="empty-state">
                                <span class="icon">📭</span>
                                <h3>Nenhum aluno encontrado</h3>
                                <p>A tabela <strong>pendencias_anterior</strong> está vazia.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Ações Rápidas -->
    <div class="acoes-rapidas">
        <span style="color:#94a3b8;font-size:13px;font-weight:500;">⚡ Ações:</span>
        <a href="importados_pendencias.php" class="btn btn-info">📋 Pendências</a>
        <a href="?exportar=pdf" class="btn btn-primary">📄 Exportar PDF</a>
        <a href="?exportar=excel" class="btn btn-success">📊 Exportar Excel</a>
    </div>

    <!-- Footer -->
    <div class="footer">
        © <?= date('Y') ?> - Sistema de Gestão Escolar | Conta Pendente | Tabela: pendencias_anterior
    </div>
</div>

<?php include '../../includes/footer_escola.php'; ?>