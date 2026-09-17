<?php
/**
 * Relatório de Mensalidades com Status de Pagamento - Softgest Web
 * VERSÃO 100% LOCAL - Sem dependências externas
 * Todos os estilos e scripts embutidos no código
 */

// ============================================
// 1. CONFIGURAÇÕES INICIAIS
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] == '') {
    header('Location: ../../../../../login.php');
    exit;
}

// ============================================
// 2. CONEXÃO COM BANCO DE DADOS
// ============================================

$db_host = 'localhost';
$db_name = 'softgest_db';
$db_user = 'root';
$db_pass = '';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die('<div style="padding:20px;margin:20px;border:1px solid #f5c6cb;background:#f8d7da;border-radius:5px;color:#721c24;">
        <h2>Erro de Conexão</h2>
        <p>' . mysqli_connect_error() . '</p>
    </div>');
}

mysqli_set_charset($conn, "utf8mb4");

// ============================================
// 3. FUNÇÕES AUXILIARES
// ============================================

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

// ============================================
// 4. BUSCAR DADOS DA EMPRESA
// ============================================
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
    $sql_emp = "SELECT * FROM empresa WHERE id = 1 LIMIT 1";
    $result_emp = mysqli_query($conn, $sql_emp);
    if ($result_emp && mysqli_num_rows($result_emp) > 0) {
        $emp = mysqli_fetch_assoc($result_emp);
        $empresa['nome'] = $emp['nome_fantasia'] ?? $emp['razao_social'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';
        $empresa['endereco'] = $emp['endereco'] ?? '';
        $empresa['telefone'] = $emp['telefone'] ?? $emp['celular'] ?? '';
        $empresa['email'] = $emp['email'] ?? '';
        $empresa['nif'] = $emp['cnpj'] ?? $emp['inscricao_estadual'] ?? '';
        $empresa['cidade'] = $emp['cidade'] ?? '';
        $empresa['provincia'] = $emp['estado'] ?? '';
        $empresa['logo'] = $emp['logo'] ?? '';
    }
} catch (Exception $e) {}

// ============================================
// 5. BUSCAR CLASSES E EMOLUMENTOS PARA FILTROS
// ============================================
$classes_disponiveis = [];
$emolumentos_disponiveis = [];

$sql_classes = "SELECT DISTINCT Classe FROM alunos WHERE Classe IS NOT NULL AND Classe != '' ORDER BY Classe";
$result_classes = mysqli_query($conn, $sql_classes);
while ($row = mysqli_fetch_assoc($result_classes)) {
    $classes_disponiveis[] = $row['Classe'];
}

$sql_emol = "SELECT id, nome FROM emolumentos WHERE status = 'ativo' ORDER BY nome";
$result_emol = mysqli_query($conn, $sql_emol);
while ($row = mysqli_fetch_assoc($result_emol)) {
    $emolumentos_disponiveis[] = $row;
}

// ============================================
// 6. RECEBE PARÂMETROS
// ============================================

$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
$situacao = isset($_GET['situacao']) ? mysqli_real_escape_string($conn, $_GET['situacao']) : 'todos';
$busca = isset($_GET['busca']) ? trim(mysqli_real_escape_string($conn, $_GET['busca'])) : '';
$filtro_classe = isset($_GET['filtro_classe']) ? mysqli_real_escape_string($conn, $_GET['filtro_classe']) : '';
$filtro_emolumento = isset($_GET['filtro_emolumento']) ? (int)$_GET['filtro_emolumento'] : 0;
$acao = isset($_GET['acao']) ? $_GET['acao'] : '';

$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : 50;

// ============================================
// 7. FUNÇÃO PARA GERAR RECIBOS DE COBRANÇA
// ============================================
function gerarRecibosCobranca($devedores, $empresa) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Recibos de Cobrança</title>
        <style>
            @page { size: A4; margin: 8mm; }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: "Times New Roman", Arial, sans-serif; background: #fff; }
            .page { page-break-after: always; }
            .recibo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; height: 100%; }
            .recibo-item { border: 2px solid #1a2332; padding: 12px; border-radius: 4px; display: flex; flex-direction: column; justify-content: space-between; min-height: 180px; }
            .recibo-item .header { text-align: center; border-bottom: 1px dashed #ccc; padding-bottom: 6px; margin-bottom: 6px; }
            .recibo-item .header .escola { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #1a2332; }
            .recibo-item .header .titulo { font-size: 13px; font-weight: bold; color: #c9a84c; }
            .recibo-item .corpo { flex: 1; font-size: 10px; }
            .recibo-item .corpo .linha { display: flex; justify-content: space-between; padding: 2px 0; border-bottom: 1px dotted #eee; }
            .recibo-item .corpo .linha .label { font-weight: bold; }
            .recibo-item .rodape { border-top: 1px dashed #ccc; padding-top: 6px; margin-top: 6px; font-size: 9px; text-align: center; color: #666; }
            .recibo-item .valor-destaque { font-size: 16px; font-weight: bold; color: #c0392b; }
            .recibo-item .status-pendente { color: #e74c3c; font-weight: bold; }
            .recibo-item .status-atrasado { color: #c0392b; font-weight: bold; }
            .recibo-item .assinatura { margin-top: 8px; text-align: center; }
            .recibo-item .assinatura .linha { border-top: 1px solid #000; width: 120px; margin: 2px auto; }
            @media print {
                .no-print { display: none !important; }
                .recibo-item { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            }
        </style>
    </head>
    <body>';
    
    $count = 0;
    $total = count($devedores);
    
    for ($i = 0; $i < $total; $i += 4) {
        $html .= '<div class="page"><div class="recibo-grid">';
        for ($j = 0; $j < 4; $j++) {
            $idx = $i + $j;
            if ($idx >= $total) {
                $html .= '<div class="recibo-item" style="border-color:#ccc;background:#f9f9f9;">
                    <div style="text-align:center;color:#999;padding:20px 0;">
                        <span style="font-size:24px;">📋</span>
                        <p style="font-size:11px;">Recibo vazio</p>
                    </div>
                </div>';
                continue;
            }
            $d = $devedores[$idx];
            $html .= '
            <div class="recibo-item">
                <div class="header">
                    <div class="escola">' . htmlspecialchars($empresa['nome']) . '</div>
                    <div class="titulo">📋 RECIBO DE COBRANÇA</div>
                    <div style="font-size:8px;color:#666;">Nº: ' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT) . '/' . date('Y') . '</div>
                </div>
                <div class="corpo">
                    <div class="linha">
                        <span class="label">👤 Aluno:</span>
                        <span>' . htmlspecialchars($d['aluno_nome']) . '</span>
                    </div>
                    <div class="linha">
                        <span class="label">📚 Classe:</span>
                        <span>' . htmlspecialchars($d['classe']) . 'ª</span>
                    </div>
                    <div class="linha">
                        <span class="label">🏫 Turma:</span>
                        <span>' . htmlspecialchars($d['turma_nome']) . '</span>
                    </div>
                    <div class="linha">
                        <span class="label">📅 Vencimento:</span>
                        <span>' . formatarData($d['data_vencimento']) . '</span>
                    </div>
                    <div class="linha" style="border-bottom:2px solid #1a2332;padding:4px 0;margin-top:4px;">
                        <span class="label" style="font-size:11px;">💰 VALOR DEVIDO:</span>
                        <span class="valor-destaque">' . formatarMoeda($d['valor']) . '</span>
                    </div>
                    <div class="linha">
                        <span class="label">📋 Emolumento:</span>
                        <span>' . htmlspecialchars($d['emolumento_nome'] ?? 'Mensalidade') . '</span>
                    </div>
                    <div class="linha">
                        <span class="label">📌 Status:</span>
                        <span class="status-' . ($d['status'] == 'atrasado' ? 'atrasado' : 'pendente') . '">' . strtoupper($d['status']) . '</span>
                    </div>
                </div>
                <div class="rodape">
                    <div style="display:flex;justify-content:space-between;font-size:8px;">
                        <span>Data: ' . date('d/m/Y') . '</span>
                        <span>Pagamento até: ' . formatarData($d['data_vencimento']) . '</span>
                    </div>
                    <div class="assinatura">
                        <div class="linha"></div>
                        <span style="font-size:8px;">Assinatura do Responsável</span>
                    </div>
                </div>
            </div>';
        }
        $html .= '</div></div>';
    }
    
    $html .= '
    <div class="no-print" style="text-align:center;padding:20px;position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #1a2332;">
        <button onclick="window.print()" style="padding:10px 30px;background:#1a2332;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;margin:0 10px;">
            🖨️ IMPRIMIR RECIBOS
        </button>
        <button onclick="window.close()" style="padding:10px 30px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">
            ✕ FECHAR
        </button>
    </div>
    </body></html>';
    
    return $html;
}

// ============================================
// 8. QUERY PRINCIPAL
// ============================================

$sql_base = "
    SELECT 
        m.id,
        m.aluno_id,
        m.turma_id,
        m.emolumento_id,
        m.mes,
        m.ano,
        m.valor,
        m.valor_pago,
        m.data_vencimento,
        m.status AS mensalidade_status,
        m.descricao,
        m.num_documento,
        m.created_at,
        a.nome AS aluno_nome,
        a.TURMA AS turma_nome,
        a.Classe AS classe,
        a.Curso AS curso_nome,
        a.N_BI AS bi,
        a.telefone,
        a.email,
        a.Contacto4 AS telefone_responsavel,
        a.Nome_do_Pai,
        a.Nome_da_mae,
        t.nome AS turma_nome2,
        t.classe AS turma_classe,
        e.nome AS emolumento_nome,
        e.valor AS emolumento_valor,
        e.tipo AS emolumento_tipo,
        (SELECT COUNT(*) FROM pagamentos p 
         WHERE p.aluno_id = m.aluno_id 
         AND p.emolumento_id = m.emolumento_id
         AND p.status = 'confirmado'
         AND (
             p.mes_referencia = CONCAT(
                 ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                 '/',
                 m.ano
             )
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
         )
        ) AS tem_pagamento,
        (SELECT SUM(p.valor) FROM pagamentos p 
         WHERE p.aluno_id = m.aluno_id 
         AND p.emolumento_id = m.emolumento_id
         AND p.status = 'confirmado'
         AND (
             p.mes_referencia = CONCAT(
                 ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                 '/',
                 m.ano
             )
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
         )
        ) AS valor_efetivamente_pago,
        (SELECT p.data_pagamento FROM pagamentos p 
         WHERE p.aluno_id = m.aluno_id 
         AND p.emolumento_id = m.emolumento_id
         AND p.status = 'confirmado'
         AND (
             p.mes_referencia = CONCAT(
                 ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                 '/',
                 m.ano
             )
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
         )
         ORDER BY p.data_pagamento DESC
         LIMIT 1
        ) AS data_pagamento_real,
        (SELECT p.forma_pagamento FROM pagamentos p 
         WHERE p.aluno_id = m.aluno_id 
         AND p.emolumento_id = m.emolumento_id
         AND p.status = 'confirmado'
         AND (
             p.mes_referencia = CONCAT(
                 ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                 '/',
                 m.ano
             )
             OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
         )
         ORDER BY p.data_pagamento DESC
         LIMIT 1
        ) AS forma_pagamento_real
    FROM 
        mensalidades m
    INNER JOIN 
        alunos a ON m.aluno_id = a.id
    LEFT JOIN 
        turmas t ON m.turma_id = t.id
    LEFT JOIN 
        emolumentos e ON m.emolumento_id = e.id
    WHERE 
        1=1
";

$conditions = [];

if ($ano > 0) {
    $conditions[] = "m.ano = $ano";
}

if ($mes > 0) {
    $conditions[] = "m.mes = $mes";
}

if ($situacao != 'todos' && !empty($situacao)) {
    if ($situacao == 'pago') {
        $conditions[] = "EXISTS (
            SELECT 1 FROM pagamentos p 
            WHERE p.aluno_id = m.aluno_id 
            AND p.emolumento_id = m.emolumento_id
            AND p.status = 'confirmado'
            AND (
                p.mes_referencia = CONCAT(
                    ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                    '/',
                    m.ano
                )
                OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
            )
        )";
    } elseif ($situacao == 'pendente') {
        $conditions[] = "NOT EXISTS (
            SELECT 1 FROM pagamentos p 
            WHERE p.aluno_id = m.aluno_id 
            AND p.emolumento_id = m.emolumento_id
            AND p.status = 'confirmado'
            AND (
                p.mes_referencia = CONCAT(
                    ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                    '/',
                    m.ano
                )
                OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
            )
        )";
        $conditions[] = "m.status != 'cancelado'";
    } elseif ($situacao == 'atrasado') {
        $conditions[] = "NOT EXISTS (
            SELECT 1 FROM pagamentos p 
            WHERE p.aluno_id = m.aluno_id 
            AND p.emolumento_id = m.emolumento_id
            AND p.status = 'confirmado'
            AND (
                p.mes_referencia = CONCAT(
                    ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                    '/',
                    m.ano
                )
                OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
            )
        )";
        $conditions[] = "m.data_vencimento < CURDATE()";
        $conditions[] = "m.status != 'cancelado'";
    } elseif ($situacao == 'cancelado') {
        $conditions[] = "m.status = 'cancelado'";
    }
}

if (!empty($filtro_classe)) {
    $conditions[] = "a.Classe = '$filtro_classe'";
}

if ($filtro_emolumento > 0) {
    $conditions[] = "m.emolumento_id = $filtro_emolumento";
}

if (!empty($busca)) {
    $conditions[] = "(a.nome LIKE '%$busca%' OR a.N_BI LIKE '%$busca%' OR a.telefone LIKE '%$busca%')";
}

if (!empty($conditions)) {
    $sql_base .= " AND " . implode(" AND ", $conditions);
}

$sql_base .= " ORDER BY a.Classe, a.nome, m.data_vencimento DESC";

// Contagem total
$sql_count = "SELECT COUNT(*) as total FROM mensalidades m 
              INNER JOIN alunos a ON m.aluno_id = a.id 
              WHERE 1=1";

if (!empty($conditions)) {
    $sql_count .= " AND " . implode(" AND ", $conditions);
}

$count_result = mysqli_query($conn, $sql_count);
$total_registros = mysqli_fetch_assoc($count_result)['total'];
mysqli_free_result($count_result);

$offset = ($pagina - 1) * $por_pagina;
$sql_final = $sql_base . " LIMIT $offset, $por_pagina";
$result = mysqli_query($conn, $sql_final);

if (!$result) {
    die('<div class="alert alert-danger"><h4>Erro na consulta</h4><p>' . mysqli_error($conn) . '</p></div>');
}

$totais = [
    'quantidade' => 0,
    'valor_total' => 0,
    'valor_pago' => 0,
    'valor_pendente' => 0,
    'valor_atrasado' => 0,
    'valor_cancelado' => 0,
    'quantidade_paga' => 0,
    'quantidade_pendente' => 0,
    'quantidade_atrasado' => 0
];

$dados = [];
while ($row = mysqli_fetch_assoc($result)) {
    $dados[] = $row;
    
    $totais['quantidade']++;
    $totais['valor_total'] += (float)$row['valor'];
    
    $tem_pagamento = $row['tem_pagamento'] > 0;
    
    if ($row['mensalidade_status'] == 'cancelado') {
        $totais['valor_cancelado'] += (float)$row['valor'];
    } elseif ($tem_pagamento) {
        $totais['valor_pago'] += (float)$row['valor'];
        $totais['quantidade_paga']++;
    } else {
        $hoje = time();
        $data_venc = strtotime($row['data_vencimento']);
        if ($data_venc < $hoje) {
            $totais['valor_atrasado'] += (float)$row['valor'];
            $totais['quantidade_atrasado']++;
        } else {
            $totais['valor_pendente'] += (float)$row['valor'];
            $totais['quantidade_pendente']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Mensalidades - Softgest</title>
    
    <style>
        /* ============================================ */
        /* ESTILOS 100% LOCAIS - SEM DEPENDÊNCIAS EXTERNAS */
        /* ============================================ */
        
        /* RESET BÁSICO */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f0f2f5;
            line-height: 1.5;
            color: #333;
        }
        
        .relatorio-container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        
        /* HEADER */
        .header-card {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .header-card h2 { margin: 0; font-weight: 300; font-size: 26px; }
        .header-card .subtitle { opacity: 0.8; font-size: 14px; }
        
        /* FILTROS */
        .filtros-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .row { display: flex; flex-wrap: wrap; margin: 0 -10px; }
        .col { flex: 1; padding: 0 10px; }
        .col-md-2 { flex: 0 0 16.666%; max-width: 16.666%; }
        .col-md-3 { flex: 0 0 25%; max-width: 25%; }
        .col-md-4 { flex: 0 0 33.333%; max-width: 33.333%; }
        .col-md-6 { flex: 0 0 50%; max-width: 50%; }
        .col-md-8 { flex: 0 0 66.666%; max-width: 66.666%; }
        .col-md-12 { flex: 0 0 100%; max-width: 100%; }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 700;
            font-size: 11px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-select, .form-control {
            width: 100%;
            padding: 6px 12px;
            font-size: 14px;
            line-height: 1.5;
            color: #495057;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            transition: border-color 0.15s;
        }
        .form-select:focus, .form-control:focus {
            border-color: #0f3460;
            outline: none;
            box-shadow: 0 0 0 3px rgba(15,52,96,0.15);
        }
        .form-select-sm, .form-control-sm { font-size: 12px; padding: 4px 8px; }
        
        .input-group { display: flex; }
        .input-group .form-control { flex: 1; border-radius: 6px 0 0 6px; }
        .input-group .btn { border-radius: 0 6px 6px 0; }
        
        /* BOTÕES */
        .btn {
            display: inline-block;
            padding: 6px 14px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            line-height: 1.5;
        }
        .btn-sm { font-size: 12px; padding: 4px 10px; }
        .btn-primary { background: #0f3460; color: #fff; }
        .btn-primary:hover { background: #1a1a2e; transform: translateY(-1px); }
        .btn-secondary { background: #6c757d; color: #fff; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-success { background: #28a745; color: #fff; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; color: #fff; }
        .btn-info:hover { background: #138496; }
        .btn-outline-success { background: transparent; color: #28a745; border: 1px solid #28a745; }
        .btn-outline-success:hover { background: #28a745; color: #fff; }
        .btn-outline-info { background: transparent; color: #17a2b8; border: 1px solid #17a2b8; }
        .btn-outline-info:hover { background: #17a2b8; color: #fff; }
        
        .btn-group { display: inline-flex; gap: 4px; }
        .btn-group .btn { border-radius: 4px; }
        
        .btn-print-relatorio {
            background: #1a2332;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print-relatorio:hover { background: #2d3748; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(26,35,50,0.3); }
        
        .btn-devedores {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-devedores:hover { background: #c0392b; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(231,76,60,0.3); }
        
        .btn-recibos {
            background: #f39c12;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-recibos:hover { background: #e67e22; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(243,156,18,0.3); }
        
        .btn-notificar-whats {
            background: #25D366;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-notificar-whats:hover { background: #1da851; transform: scale(1.05); }
        
        .btn-extrato {
            background: #6c5ce7;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-extrato:hover { background: #5a4bd1; transform: scale(1.05); }
        
        /* TOTAIS */
        .totais-card {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .total-item {
            text-align: center;
            padding: 10px;
            border-right: 1px solid #e9ecef;
        }
        .total-item:last-child { border-right: none; }
        .total-item .valor { font-size: 20px; font-weight: bold; color: #2d3436; }
        .total-item .rotulo { font-size: 11px; color: #636e72; text-transform: uppercase; letter-spacing: 0.5px; }
        .total-item .valor.text-success { color: #00b894; }
        .total-item .valor.text-danger { color: #e17055; }
        .total-item .valor.text-warning { color: #fdcb6e; }
        .total-item .valor.text-info { color: #0984e3; }
        .total-item .valor.text-secondary { color: #636e72; }
        .mt-2 { margin-top: 10px; }
        .mt-3 { margin-top: 15px; }
        .mb-3 { margin-bottom: 15px; }
        .me-1 { margin-right: 5px; }
        .me-2 { margin-right: 10px; }
        .ms-2 { margin-left: 10px; }
        .ms-3 { margin-left: 15px; }
        .mx-2 { margin-left: 10px; margin-right: 10px; }
        .gap-2 { gap: 10px; }
        .flex-wrap { flex-wrap: wrap; }
        .d-flex { display: flex; }
        .align-items-center { align-items: center; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .text-muted { color: #6c757d; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-warning { color: #ffc107; }
        .text-info { color: #17a2b8; }
        .text-secondary { color: #6c757d; }
        .fw-bold { font-weight: 700; }
        .small { font-size: 80%; }
        
        /* BADGES */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 12px;
        }
        .badge.bg-secondary { background: #6c757d; color: #fff; }
        .badge.bg-info { background: #17a2b8; color: #fff; }
        .badge.bg-danger { background: #dc3545; color: #fff; }
        .badge.bg-success { background: #28a745; color: #fff; }
        .badge.bg-warning { background: #ffc107; color: #212529; }
        .badge.bg-dark { background: #343a40; color: #fff; }
        .badge.bg-light { background: #f8f9fa; color: #212529; }
        
        .pago-badge { background: #d1fae5; color: #065f46; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .pendente-badge { background: #fef3c7; color: #92400e; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .atrasado-badge { background: #fee2e2; color: #991b1b; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        
        /* CARD */
        .card-relatorio {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .card-relatorio .card-header {
            background: white;
            border-bottom: 2px solid #f1f2f6;
            padding: 15px 20px;
            font-weight: 600;
        }
        .card-relatorio .card-body { padding: 0; }
        .card-relatorio .card-footer {
            background: white;
            padding: 15px 20px;
            border-top: 1px solid #f1f2f6;
        }
        .card-footer.bg-white { background: white; }
        
        /* TABELA */
        .table-responsive { overflow-x: auto; }
        
        .table-relatorio {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
        }
        .table-relatorio thead th {
            background: #2d3436;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            border: none;
            padding: 12px 10px;
            text-align: left;
            white-space: nowrap;
        }
        .table-relatorio thead th.text-end { text-align: right; }
        .table-relatorio thead th.text-center { text-align: center; }
        .table-relatorio tbody td {
            padding: 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f2f6;
        }
        .table-relatorio tbody tr:hover { background: #f8f9fa; }
        .table-relatorio tbody tr.pago { background: #f0fdf4; }
        .table-relatorio tbody tr.atrasado { background: #fef2f2; }
        .table-relatorio tbody td.text-end { text-align: right; }
        .table-relatorio tbody td.text-center { text-align: center; }
        
        /* PAGINAÇÃO */
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            justify-content: center;
            gap: 4px;
        }
        .pagination .page-item { display: inline-block; }
        .pagination .page-link {
            display: block;
            padding: 6px 12px;
            color: #0f3460;
            text-decoration: none;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .pagination .page-link:hover { background: #e9ecef; }
        .pagination .page-item.active .page-link {
            background: #0f3460;
            color: #fff;
            border-color: #0f3460;
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background: #fff;
        }
        
        /* ALERTAS */
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* RESPONSIVIDADE */
        @media (max-width: 768px) {
            .col-md-2, .col-md-3, .col-md-4, .col-md-6 { flex: 0 0 100%; max-width: 100%; }
            .header-card h2 { font-size: 20px; }
            .header-card .subtitle { font-size: 12px; }
            .total-item { border-right: none; border-bottom: 1px solid #e9ecef; }
            .total-item:last-child { border-bottom: none; }
            .d-flex.gap-2 { flex-direction: column; }
            .d-flex.gap-2 .btn { width: 100%; justify-content: center; }
            .table-relatorio { font-size: 11px; }
            .table-relatorio thead th { font-size: 9px; padding: 6px 4px; }
            .table-relatorio tbody td { padding: 6px 4px; }
            .btn-group .btn { font-size: 10px; padding: 2px 6px; }
        }
        
        /* IMPRESSÃO */
        @media print {
            .no-print { display: none !important; }
            .relatorio-container { padding: 0 !important; max-width: 100% !important; }
            .header-card {
                background: #1a1a2e !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border-radius: 0 !important;
                padding: 15px 20px !important;
            }
            .header-card h2 { font-size: 16pt !important; }
            .header-card .subtitle { font-size: 10pt !important; }
            .filtros-card { display: none !important; }
            .totais-card {
                border: 1px solid #ddd !important;
                padding: 10px 15px !important;
                page-break-inside: avoid !important;
            }
            .total-item .valor { font-size: 14pt !important; }
            .total-item .rotulo { font-size: 8pt !important; }
            .table-relatorio { font-size: 9pt !important; }
            .table-relatorio thead th {
                background: #2d3436 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-size: 7pt !important;
                padding: 5px 8px !important;
            }
            .table-relatorio tbody td { padding: 4px 8px !important; font-size: 8pt !important; }
            .badge-status { font-size: 7pt !important; padding: 2px 6px !important; }
            .card-relatorio {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            .card-relatorio .card-header {
                background: #f8f9fa !important;
                padding: 8px 15px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .pagination { display: none !important; }
            .table-relatorio tbody tr.pago {
                background: #f0fdf4 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .table-relatorio tbody tr.atrasado {
                background: #fef2f2 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .total-item .valor.text-success { color: #00b894 !important; }
            .total-item .valor.text-danger { color: #e17055 !important; }
            .total-item .valor.text-warning { color: #fdcb6e !important; }
            .total-item .valor.text-info { color: #0984e3 !important; }
            @page { size: A4; margin: 12mm 15mm 12mm 15mm; }
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
        }
    </style>
</head>
<body>

<div class="relatorio-container">
    
    <!-- HEADER -->
    <div class="header-card no-print">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2>
                    <span style="margin-right:10px;">📄</span> Relatório de Mensalidades
                    <span class="subtitle ms-3">
                        <span style="margin-right:5px;">📅</span>
                        <?= mesNome($mes) ?>/<?= $ano ?>
                    </span>
                </h2>
                <div class="subtitle mt-1">
                    <span style="margin-right:5px;">👤</span> 
                    <?= $_SESSION['nome'] ?? $_SESSION['usuario_nome'] ?? 'Usuário' ?>
                    <span class="mx-2">|</span>
                    <span style="margin-right:5px;">🕐</span>
                    <?= date('d/m/Y H:i:s') ?>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <button onclick="window.print()" class="btn-print-relatorio" style="background:#c9a84c;color:#1a2332;">
                    🖨️ Imprimir
                </button>
                <a href="?<?= http_build_query(array_merge($_GET, ['formato' => 'pdf'])) ?>" class="btn btn-danger btn-sm" style="color:#fff;">
                    📄 PDF
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['formato' => 'excel'])) ?>" class="btn btn-success btn-sm" style="color:#fff;">
                    📊 Excel
                </a>
            </div>
        </div>
    </div>

    <!-- BOTÕES DE AÇÃO -->
    <div class="row mb-3 no-print">
        <div class="col-md-12">
            <div class="d-flex gap-2 flex-wrap">
                <button onclick="window.print()" class="btn-print-relatorio">
                    🖨️ Imprimir Relatório
                </button>

                <a href="relatorio_devedores.php?ano=<?= $ano ?>&mes=<?= $mes ?>&classe=<?= $filtro_classe ?>&emolumento=<?= $filtro_emolumento ?>" class="btn-devedores" target="_blank">
                    👥 Relatório de Devedores
                </a>

                <a href="?acao=recibos&ano=<?= $ano ?>&mes=<?= $mes ?>&filtro_classe=<?= $filtro_classe ?>&filtro_emolumento=<?= $filtro_emolumento ?>" class="btn-recibos" target="_blank">
                    📋 Recibos de Cobrança
                </a>
                <a href="?ano=<?= date('Y') ?>&mes=<?= date('m') ?>" class="btn btn-secondary btn-sm">
                    🔄 Hoje
                </a>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filtros-card no-print">
        <form method="GET" class="row" style="margin:0 -10px;">
            <div class="col-md-2" style="padding:0 10px;">
                <label class="form-label">Ano</label>
                <select name="ano" class="form-select form-select-sm">
                    <?php for($a = date('Y') - 5; $a <= date('Y') + 1; $a++): ?>
                        <option value="<?= $a ?>" <?= $a == $ano ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2" style="padding:0 10px;">
                <label class="form-label">Mês</label>
                <select name="mes" class="form-select form-select-sm">
                    <?php for($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= mesNome($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2" style="padding:0 10px;">
                <label class="form-label">Situação</label>
                <select name="situacao" class="form-select form-select-sm">
                    <option value="todos" <?= $situacao == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="pago" <?= $situacao == 'pago' ? 'selected' : '' ?>>Pago</option>
                    <option value="pendente" <?= $situacao == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="atrasado" <?= $situacao == 'atrasado' ? 'selected' : '' ?>>Atrasado</option>
                    <option value="cancelado" <?= $situacao == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-2" style="padding:0 10px;">
                <label class="form-label">Classe</label>
                <select name="filtro_classe" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach($classes_disponiveis as $c): ?>
                        <option value="<?= $c ?>" <?= ($filtro_classe == $c) ? 'selected' : '' ?>><?= $c ?>ª Classe</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2" style="padding:0 10px;">
                <label class="form-label">Emolumento</label>
                <select name="filtro_emolumento" class="form-select form-select-sm">
                    <option value="0">Todos</option>
                    <?php foreach($emolumentos_disponiveis as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= ($filtro_emolumento == $e['id']) ? 'selected' : '' ?>><?= htmlspecialchars($e['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4" style="padding:0 10px;">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <input type="text" name="busca" class="form-control form-control-sm" 
                           placeholder="Nome, BI ou Telefone" 
                           value="<?= htmlspecialchars($busca) ?>">
                    <button type="submit" class="btn btn-primary btn-sm">🔍</button>
                    <a href="?ano=<?= date('Y') ?>&mes=<?= date('m') ?>" class="btn btn-secondary btn-sm">↺</a>
                </div>
            </div>
            <div class="col-md-2" style="padding:0 10px;display:flex;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">
                    🔽 Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- TOTAIS -->
    <div class="totais-card">
        <div class="row">
            <div class="col-md-3 total-item">
                <div class="rotulo">Total</div>
                <div class="valor"><?= number_format($totais['quantidade'], 0, ',', '.') ?></div>
            </div>
            <div class="col-md-3 total-item">
                <div class="rotulo">Valor Total</div>
                <div class="valor"><?= formatarMoeda($totais['valor_total']) ?></div>
            </div>
            <div class="col-md-3 total-item">
                <div class="rotulo">💰 Pago</div>
                <div class="valor text-success"><?= formatarMoeda($totais['valor_pago']) ?></div>
                <small><?= $totais['quantidade_paga'] ?> registros</small>
            </div>
            <div class="col-md-3 total-item">
                <div class="rotulo">⏳ Pendente</div>
                <div class="valor text-warning"><?= formatarMoeda($totais['valor_pendente']) ?></div>
                <small><?= $totais['quantidade_pendente'] ?> registros</small>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-4 total-item">
                <div class="rotulo">⚠️ Atrasado</div>
                <div class="valor text-danger"><?= formatarMoeda($totais['valor_atrasado']) ?></div>
                <small><?= $totais['quantidade_atrasado'] ?> registros</small>
            </div>
            <div class="col-md-4 total-item">
                <div class="rotulo">❌ Cancelado</div>
                <div class="valor text-secondary"><?= formatarMoeda($totais['valor_cancelado']) ?></div>
            </div>
            <div class="col-md-4 total-item">
                <div class="rotulo">📊 Taxa de Pagamento</div>
                <div class="valor text-info">
                    <?php 
                    $taxa = $totais['quantidade'] > 0 ? round(($totais['quantidade_paga'] / $totais['quantidade']) * 100, 1) : 0;
                    echo $taxa . '%';
                    ?>
                </div>
                <small><?= $totais['quantidade_paga'] ?> de <?= $totais['quantidade'] ?></small>
            </div>
        </div>
    </div>

    <!-- TABELA -->
    <div class="card-relatorio">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    📋 Lista de Mensalidades
                    <span class="badge bg-secondary ms-2"><?= $total_registros ?> registros</span>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted small">
                        Página <?= $pagina ?> de <?= max(1, ceil($total_registros / $por_pagina)) ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table-relatorio" id="tabelaMensalidades">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Aluno</th>
                            <th>Classe</th>
                            <th>Emolumento</th>
                            <th>Turma</th>
                            <th>Mês/Ano</th>
                            <th>Vencimento</th>
                            <th class="text-end">Valor</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($dados) > 0): ?>
                            <?php $cont = $offset + 1; ?>
                            <?php foreach($dados as $row): 
                                $tem_pagamento = $row['tem_pagamento'] > 0;
                                $classe_linha = '';
                                if ($tem_pagamento) $classe_linha = 'pago';
                                elseif ($row['mensalidade_status'] != 'cancelado' && strtotime($row['data_vencimento']) < time()) $classe_linha = 'atrasado';
                                $telefone = $row['telefone_responsavel'] ?? '';
                            ?>
                                <tr class="<?= $classe_linha ?>">
                                    <td><?= $cont++ ?></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($row['aluno_nome']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($row['bi'] ?? 'Sem BI') ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['classe']) ?>ª</td>
                                    <td>
                                        <?php if(!empty($row['emolumento_nome'])): ?>
                                            <span class="badge bg-info text-dark">
                                                <?= htmlspecialchars($row['emolumento_nome']) ?>
                                            </span>
                                            <br>
                                            <small class="text-muted"><?= formatarMoeda($row['emolumento_valor'] ?? $row['valor']) ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sem emolumento</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['turma_nome'] ?? $row['turma_nome2'] ?? '-') ?></td>
                                    <td><?= mesNome($row['mes']) ?>/<?= $row['ano'] ?></td>
                                    <td><?= formatarData($row['data_vencimento']) ?></td>
                                    <td class="text-end"><strong><?= formatarMoeda($row['valor']) ?></strong></td>
                                    <td>
                                        <?php if($tem_pagamento): ?>
                                            <span class="pago-badge">✅ PAGO</span>
                                            <?php if($row['forma_pagamento_real']): ?>
                                                <br><small class="text-muted"><?= formaPagamentoLabel($row['forma_pagamento_real']) ?></small>
                                            <?php endif; ?>
                                        <?php elseif($row['mensalidade_status'] == 'cancelado'): ?>
                                            <span class="badge bg-secondary">❌ Cancelado</span>
                                        <?php elseif(strtotime($row['data_vencimento']) < time()): ?>
                                            <span class="atrasado-badge">⚠️ ATRASADO</span>
                                        <?php else: ?>
                                            <span class="pendente-badge">⏳ PENDENTE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <?php if(!empty($telefone)): ?>
                                                <button class="btn-notificar-whats" onclick="notificarWhatsApp(<?= $row['aluno_id'] ?>, '<?= htmlspecialchars($telefone) ?>')" title="Notificar Encarregado via WhatsApp">
                                                    💬
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-extrato" onclick="abrirExtrato(<?= $row['aluno_id'] ?>)" title="Ver Extrato do Aluno">
                                                📄
                                            </button>
                                            <?php if(!$tem_pagamento && $row['mensalidade_status'] != 'cancelado'): ?>
                                                <a href="../pagamentos/baixar.php?mensalidade_id=<?= $row['id'] ?>&aluno_id=<?= $row['aluno_id'] ?>&emolumento_id=<?= $row['emolumento_id'] ?>&mes=<?= $row['mes'] ?>&ano=<?= $row['ano'] ?>&valor=<?= $row['valor'] ?>" class="btn btn-outline-success btn-sm" title="Baixar">
                                                    ✅
                                                </a>
                                            <?php endif; ?>
                                            <a href="../pagamentos/view.php?id=<?= $row['id'] ?>" class="btn btn-outline-info btn-sm" title="Ver">
                                                👁️
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center" style="padding:40px 0;">
                                    <div style="font-size:48px;color:#ccc;margin-bottom:15px;">🔍</div>
                                    <h5 class="text-muted">Nenhuma mensalidade encontrada</h5>
                                    <p class="text-muted small">Tente ajustar os filtros de busca</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if($total_registros > $por_pagina): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination">
                        <?php 
                        $total_paginas = ceil($total_registros / $por_pagina);
                        $intervalo = 2;
                        $pagina_inicial = max(1, $pagina - $intervalo);
                        $pagina_final = min($total_paginas, $pagina + $intervalo);
                        ?>
                        
                        <?php if($pagina > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">
                                    ««
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">
                                    «
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for($p = $pagina_inicial; $p <= $pagina_final; $p++): ?>
                            <li class="page-item <?= $p == $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if($pagina < $total_paginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">
                                    »
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>">
                                    »»
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="row mt-3">
        <div class="col-md-6">
            <small class="text-muted">
                📅 Gerado em: <?= date('d/m/Y H:i:s') ?>
            </small>
        </div>
        <div class="col-md-6 text-end">
            <small class="text-muted">
                Softgest Sistemas © <?= date('Y') ?>
            </small>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- SCRIPTS 100% LOCAIS - SEM DEPENDÊNCIAS EXTERNAS -->
<!-- ========================================== -->
<script>
// ============================================
// ABRIR EXTRATO - ABRE EM NOVA JANELA
// ============================================
function abrirExtrato(alunoId) {
    var url = '/softgest_web/modules/escola/financeiro/mensalidades/extrato_aluno.php?aluno_id=' + alunoId;
    window.open(url, '_blank', 'width=1024,height=800,scrollbars=yes,toolbar=yes,menubar=yes');
}

// ============================================
// NOTIFICAR VIA WHATSAPP
// ============================================
function notificarWhatsApp(alunoId, telefone) {
    if (!telefone || telefone == '') {
        alert('⚠️ O encarregado deste aluno não possui número de telefone cadastrado!');
        return;
    }
    
    var numero = telefone.replace(/\D/g, '');
    if (numero.length < 9) {
        alert('⚠️ Número de telefone inválido: ' + telefone);
        return;
    }
    
    if (numero.length === 9) {
        numero = '244' + numero;
    }
    
    var mensagem = '📋 *EXTRATO DE MENSALIDADES*\n\n';
    mensagem += '📍 Escola: <?= $_SESSION['escola_nome'] ?? 'COMPLEXO ESCOLAR CASTELO REIS' ?>\n';
    mensagem += '📅 Data: ' + new Date().toLocaleDateString('pt-BR') + '\n\n';
    mensagem += '🔗 Clique no link para visualizar o extrato completo:\n';
    mensagem += window.location.origin + '/softgest_web/modules/escola/financeiro/mensalidades/extrato_aluno.php?aluno_id=' + alunoId;
    mensagem += '\n\n📌 *Aviso automático - Não responda a esta mensagem.*';
    
    var url = 'https://api.whatsapp.com/send?phone=' + numero + '&text=' + encodeURIComponent(mensagem);
    window.open(url, '_blank');
}

// ============================================
// AUTO-SUBMIT DOS FILTROS (VERSÃO LOCAL)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit para selects
    var selects = document.querySelectorAll('select[name="ano"], select[name="mes"], select[name="situacao"], select[name="filtro_classe"], select[name="filtro_emolumento"]');
    selects.forEach(function(select) {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    // Busca com delay
    var buscaInput = document.querySelector('input[name="busca"]');
    var timeout;
    buscaInput.addEventListener('keyup', function() {
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            buscaInput.closest('form').submit();
        }, 500);
    });
});

// ============================================
// IMPRIMIR RELATÓRIO
// ============================================
function imprimirRelatorio() {
    window.print();
}
</script>

<?php
// ============================================
// GERAR RELATÓRIO DE DEVEDORES - INDEPENDENTE
// ============================================
if ($acao == 'devedores') {
    // Buscar devedores - mensalidades que VENCERAM e NÃO FORAM PAGAS
    $sql_devedores = "
        SELECT 
            a.id as aluno_id,
            a.nome as aluno_nome,
            a.Classe as classe,
            a.TURMA as turma_nome,
            m.id as mensalidade_id,
            m.mes,
            m.ano,
            m.valor,
            m.data_vencimento,
            m.status,
            e.nome as emolumento_nome,
            (SELECT COUNT(*) FROM pagamentos p 
             WHERE p.aluno_id = m.aluno_id 
             AND p.emolumento_id = m.emolumento_id
             AND p.status = 'confirmado'
             AND (
                 p.mes_referencia = CONCAT(
                     ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                     '/',
                     m.ano
                 )
                 OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
             )
            ) as tem_pagamento
        FROM mensalidades m
        INNER JOIN alunos a ON m.aluno_id = a.id
        LEFT JOIN emolumentos e ON m.emolumento_id = e.id
        WHERE m.ano = $ano
        AND m.mes = $mes
        AND m.status != 'pago'
        AND m.status != 'cancelado'
        AND (SELECT COUNT(*) FROM pagamentos p 
             WHERE p.aluno_id = m.aluno_id 
             AND p.emolumento_id = m.emolumento_id
             AND p.status = 'confirmado'
             AND (
                 p.mes_referencia = CONCAT(
                     ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                     '/',
                     m.ano
                 )
                 OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
             )
            ) = 0
    ";
    
    if (!empty($filtro_classe)) {
        $sql_devedores .= " AND a.Classe = '$filtro_classe'";
    }
    
    if ($filtro_emolumento > 0) {
        $sql_devedores .= " AND m.emolumento_id = $filtro_emolumento";
    }
    
    $sql_devedores .= " ORDER BY a.Classe, a.nome, m.data_vencimento";
    
    $result_devedores = mysqli_query($conn, $sql_devedores);
    $devedores = [];
    while ($row = mysqli_fetch_assoc($result_devedores)) {
        $devedores[] = $row;
    }
    
    // Se não houver devedores para o mês, mostrar mensagem
    if (empty($devedores)) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Relatório de Devedores</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; text-align: center; background: #f8f9fa; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
                .sucesso { color: #2ecc71; font-size: 64px; }
                h2 { color: #1a2332; margin: 15px 0 10px; }
                p { color: #666; }
                .btn { padding: 10px 30px; background: #1a2332; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; margin-top: 20px; }
                .btn:hover { background: #2d3748; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="sucesso">✅</div>
                <h2>Nenhum devedor encontrado!</h2>
                <p>Competência: ' . mesNome($mes) . '/' . $ano . '</p>
                <p>Todos os alunos estão em dia com suas mensalidades.</p>
                <button class="btn" onclick="window.close()">✕ FECHAR</button>
            </div>
        </body>
        </html>';
        exit;
    }
    
    // ============================================
    // GERAR HTML DO RELATÓRIO DE DEVEDORES
    // ============================================
    $hoje = time();
    $total_devedores = count($devedores);
    $total_valor = array_sum(array_column($devedores, 'valor'));
    
    $total_atrasados = 0;
    foreach ($devedores as $d) {
        if (strtotime($d['data_vencimento']) < $hoje) {
            $total_atrasados++;
        }
    }
    $total_pendentes = $total_devedores - $total_atrasados;
    
    // Agrupar por classe
    $devedores_por_classe = [];
    foreach ($devedores as $d) {
        $classe = $d['classe'] ?? 'Sem Classe';
        if (!isset($devedores_por_classe[$classe])) {
            $devedores_por_classe[$classe] = [];
        }
        $devedores_por_classe[$classe][] = $d;
    }
    ksort($devedores_por_classe);
    
    // ============================================
    // HTML DO RELATÓRIO INDEPENDENTE
    // ============================================
    header('Content-Type: text/html; charset=UTF-8');
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Relatório de Devedores - ' . mesNome($mes) . '/' . $ano . '</title>
        <style>
            @page { size: A4; margin: 12mm 15mm 12mm 15mm; }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: "Times New Roman", Arial, sans-serif; background: #f0f2f5; padding: 20px; }
            .relatorio-wrapper { max-width: 210mm; margin: 0 auto; background: #ffffff; padding: 15mm 20mm; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); min-height: 297mm; }
            .header { text-align: center; border-bottom: 3px double #1a2332; padding-bottom: 15px; margin-bottom: 20px; }
            .header .escola { font-size: 22pt; font-weight: 700; text-transform: uppercase; color: #1a2332; letter-spacing: 2px; }
            .header .endereco { font-size: 10pt; color: #555; margin-top: 3px; }
            .header .titulo { font-size: 18pt; font-weight: 700; color: #c9a84c; margin-top: 10px; letter-spacing: 3px; }
            .header .info { font-size: 10pt; color: #666; margin-top: 5px; }
            .resumo { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; }
            .resumo .item { text-align: center; }
            .resumo .item .numero { font-size: 24pt; font-weight: 700; }
            .resumo .item .label { font-size: 9pt; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
            .resumo .item .numero.text-danger { color: #e74c3c; }
            .resumo .item .numero.text-warning { color: #f39c12; }
            .resumo .item .numero.text-primary { color: #1a2332; }
            .tabela { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 10px; }
            .tabela thead th { background: #1a2332; color: #ffffff; padding: 8px 10px; text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #1a2332; }
            .tabela tbody td { padding: 6px 10px; border-bottom: 1px solid #eee; font-size: 9pt; }
            .tabela tbody tr:hover { background: #f8f9fa; }
            .tabela .classe-header td { padding: 8px 10px; background: #e9ecef; font-weight: 700; border-top: 2px solid #1a2332; border-bottom: 2px solid #1a2332; }
            .tabela .total-row td { background: #f8f9fa; font-weight: 700; border-top: 2px solid #1a2332; padding: 8px 10px; }
            .status-atrasado { color: #e74c3c; font-weight: 700; }
            .status-pendente { color: #f39c12; font-weight: 700; }
            .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #ddd; text-align: center; font-size: 9pt; color: #666; }
            .assinatura { margin-top: 35px; display: flex; justify-content: space-around; }
            .assinatura .item { text-align: center; min-width: 200px; }
            .assinatura .linha { border-top: 1px solid #000; width: 180px; margin: 30px auto 5px; }
            .assinatura .cargo { font-size: 10pt; color: #555; }
            .botoes { text-align: center; padding: 15px; margin-top: 20px; }
            .botoes button { padding: 10px 30px; border: none; border-radius: 6px; font-size: 13pt; font-weight: 600; cursor: pointer; margin: 0 8px; }
            .btn-imprimir { background: #1a2332; color: #fff; }
            .btn-imprimir:hover { background: #2d3748; }
            .btn-fechar { background: #e74c3c; color: #fff; }
            .btn-fechar:hover { background: #c0392b; }
            @media print {
                .botoes { display: none !important; }
                body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
                .relatorio-wrapper { box-shadow: none !important; border-radius: 0 !important; padding: 5mm 10mm !important; min-height: auto !important; }
                .tabela thead th { background: #1a2332 !important; color: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                .tabela .classe-header td { background: #e9ecef !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                .resumo { background: #f8f9fa !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                .tabela .total-row td { background: #f8f9fa !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                .status-atrasado { color: #e74c3c !important; }
                .status-pendente { color: #f39c12 !important; }
            }
        </style>
    </head>
    <body>
    <div class="relatorio-wrapper">
        
        <div class="header">
            <div class="escola">' . htmlspecialchars($empresa['nome']) . '</div>
            <div class="endereco">' . htmlspecialchars($empresa['endereco']) . '</div>
            <div class="endereco">Tel: ' . htmlspecialchars($empresa['telefone']) . ' | NIF: ' . htmlspecialchars($empresa['nif']) . '</div>
            <div class="titulo">📋 RELATÓRIO DE DEVEDORES</div>
            <div class="info">Competência: ' . mesNome($mes) . '/' . $ano . ' | Gerado em: ' . date('d/m/Y H:i:s') . '</div>
        </div>
        
        <div class="resumo">
            <div class="item">
                <div class="numero text-primary">' . $total_devedores . '</div>
                <div class="label">Total de Devedores</div>
            </div>
            <div class="item">
                <div class="numero text-primary">' . formatarMoeda($total_valor) . '</div>
                <div class="label">Valor em Débito</div>
            </div>
            <div class="item">
                <div class="numero text-danger">' . $total_atrasados . '</div>
                <div class="label">⚠️ Atrasados</div>
            </div>
            <div class="item">
                <div class="numero text-warning">' . $total_pendentes . '</div>
                <div class="label">⏳ Pendentes</div>
            </div>
        </div>
        
        <table class="tabela">
            <thead>
                <tr>
                    <th style="width:35px;">Nº</th>
                    <th style="text-align:left;">Aluno</th>
                    <th style="text-align:left;">Classe</th>
                    <th style="text-align:left;">Turma</th>
                    <th style="text-align:left;">Emolumento</th>
                    <th style="text-align:left;">Vencimento</th>
                    <th style="text-align:right;">Valor</th>
                    <th style="text-align:center;">Status</th>
                </tr>
            </thead>
            <tbody>';
    
    $cont = 0;
    $classe_atual = '';
    
    foreach ($devedores as $d) {
        $classe = $d['classe'] ?? 'Sem Classe';
        
        if ($classe != $classe_atual) {
            $classe_atual = $classe;
            $html .= '
            <tr class="classe-header">
                <td colspan="8"><strong>📚 ' . htmlspecialchars($classe) . 'ª Classe</strong></td>
            </tr>';
        }
        
        $cont++;
        $is_atrasado = strtotime($d['data_vencimento']) < $hoje;
        $status_class = $is_atrasado ? 'status-atrasado' : 'status-pendente';
        $status_label = $is_atrasado ? '⚠️ ATRASADO' : '⏳ PENDENTE';
        
        $html .= '
        <tr>
            <td style="text-align:center;">' . $cont . '</td>
            <td><strong>' . htmlspecialchars($d['aluno_nome']) . '</strong></td>
            <td>' . htmlspecialchars($d['classe']) . 'ª</td>
            <td>' . htmlspecialchars($d['turma_nome']) . '</td>
            <td>' . htmlspecialchars($d['emolumento_nome'] ?? 'Mensalidade') . '</td>
            <td>' . formatarData($d['data_vencimento']) . '</td>
            <td style="text-align:right;"><strong>' . formatarMoeda($d['valor']) . '</strong></td>
            <td style="text-align:center;"><span class="' . $status_class . '">' . $status_label . '</span></td>
        </tr>';
    }
    
    $html .= '
            <tr class="total-row">
                <td colspan="6" style="text-align:right;">TOTAL GERAL:</td>
                <td style="text-align:right;">' . formatarMoeda($total_valor) . '</td>
                <td style="text-align:center;">' . $total_devedores . ' devedores</td>
            </tr>
        </tbody>
        </table>
        
        <div class="footer">
            <p>Documento emitido eletronicamente - ' . htmlspecialchars($empresa['nome']) . ' © ' . date('Y') . '</p>
        </div>
        
        <div class="assinatura">
            <div class="item">
                <div class="linha"></div>
                <div class="cargo">Responsável Financeiro</div>
            </div>
            <div class="item">
                <div class="linha"></div>
                <div class="cargo">Director Pedagógico</div>
            </div>
        </div>
        
    </div>
    
    <div class="botoes no-print">
        <button class="btn-imprimir" onclick="window.print()">🖨️ IMPRIMIR RELATÓRIO</button>
        <button class="btn-fechar" onclick="window.close()">✕ FECHAR</button>
    </div>
    
    </body>
    </html>';
    
    echo $html;
    exit;
}

// ============================================
// GERAR RECIBOS DE COBRANÇA
// ============================================
if ($acao == 'recibos') {
    // Buscar devedores para recibos
    $sql_devedores = "
        SELECT 
            a.id as aluno_id,
            a.nome as aluno_nome,
            a.Classe as classe,
            a.TURMA as turma_nome,
            m.id as mensalidade_id,
            m.mes,
            m.ano,
            m.valor,
            m.data_vencimento,
            m.status,
            e.nome as emolumento_nome
        FROM mensalidades m
        INNER JOIN alunos a ON m.aluno_id = a.id
        LEFT JOIN emolumentos e ON m.emolumento_id = e.id
        WHERE m.status != 'pago'
        AND m.status != 'cancelado'
        AND m.data_vencimento < CURDATE()
        AND m.ano = $ano
        AND (SELECT COUNT(*) FROM pagamentos p 
             WHERE p.aluno_id = m.aluno_id 
             AND p.emolumento_id = m.emolumento_id
             AND p.status = 'confirmado'
             AND (
                 p.mes_referencia = CONCAT(
                     ELT(m.mes, 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'),
                     '/',
                     m.ano
                 )
                 OR (MONTH(p.data_pagamento) = m.mes AND YEAR(p.data_pagamento) = m.ano)
             )
            ) = 0
    ";
    
    if (!empty($filtro_classe)) {
        $sql_devedores .= " AND a.Classe = '$filtro_classe'";
    }
    
    if ($filtro_emolumento > 0) {
        $sql_devedores .= " AND m.emolumento_id = $filtro_emolumento";
    }
    
    $sql_devedores .= " ORDER BY a.Classe, a.nome";
    
    $result_devedores = mysqli_query($conn, $sql_devedores);
    $devedores = [];
    while ($row = mysqli_fetch_assoc($result_devedores)) {
        $devedores[] = $row;
    }
    
    if (empty($devedores)) {
        echo '<div style="padding:40px;text-align:center;font-size:18px;color:#666;">
            <div style="font-size:48px;color:#2ecc71;">✅</div>
            <h3>Nenhum devedor encontrado!</h3>
            <p>Todos os alunos estão em dia com suas mensalidades.</p>
        </div>';
        exit;
    }
    
    echo gerarRecibosCobranca($devedores, $empresa);
    exit;
}

if (isset($conn)) {
    mysqli_close($conn);
}
?>
</body>
</html>