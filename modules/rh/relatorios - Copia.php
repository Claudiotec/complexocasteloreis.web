<?php
// relatorios.php - Relatórios RH
require_once '../../config/database.php';

// =============================================
// CONFIGURAÇÃO
// =============================================
date_default_timezone_set('Africa/Luanda');

// =============================================
// FUNÇÕES DE CÁLCULO
// =============================================

function calcularDescontos($salario, $faltas = 0, $horasFalta = 0) {
    $diasUteis = 22; // Dias úteis por mês
    $horasDia = 8; // Horas por dia (padrão)
    
    // Buscar horas diárias do funcionário na tabela horarios_trabalho
    // Isso será feito fora da função
    
    // Cálculo do valor do dia e hora
    $valorDia = $salario / $diasUteis;
    $valorHora = $valorDia / $horasDia;
    
    // Desconto por faltas (dias completos)
    $descontoFaltasDias = $valorDia * $faltas;
    
    // Desconto por horas de falta (atrasos/saídas antecipadas)
    $descontoFaltasHoras = $valorHora * $horasFalta;
    
    // Total desconto por faltas
    $descontoFaltas = $descontoFaltasDias + $descontoFaltasHoras;
    
    // INSS (11%)
    $inss = $salario * 0.11;
    
    // IRT (10% sobre o que exceder 100.000 AOA)
    if ($salario > 100000) {
        $irt = ($salario - 100000) * 0.10;
    } else {
        $irt = 0;
    }
    
    // Seguro de Saúde (3%)
    $saude = $salario * 0.03;
    
    // Total de descontos
    $totalDescontos = $inss + $irt + $saude + $descontoFaltas;
    
    // Salário líquido
    $liquido = $salario - $totalDescontos;
    
    return [
        'inss' => round($inss, 2),
        'irt' => round($irt, 2),
        'saude' => round($saude, 2),
        'desconto_faltas' => round($descontoFaltas, 2),
        'desconto_faltas_dias' => round($descontoFaltasDias, 2),
        'desconto_faltas_horas' => round($descontoFaltasHoras, 2),
        'total' => round($totalDescontos, 2),
        'liquido' => round($liquido, 2),
        'valor_dia' => round($valorDia, 2),
        'valor_hora' => round($valorHora, 2),
        'faltas' => $faltas,
        'horas_falta' => $horasFalta
    ];
}

// Função para contar dias úteis no mês
function contarDiasUteis($mes, $ano) {
    $diasUteis = 0;
    $totalDias = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    
    for ($dia = 1; $dia <= $totalDias; $dia++) {
        $data = "$ano-$mes-" . str_pad($dia, 2, '0', STR_PAD_LEFT);
        $diaSemana = date('N', strtotime($data));
        // Segunda a Sexta = dias úteis (1 a 5)
        if ($diaSemana >= 1 && $diaSemana <= 5) {
            $diasUteis++;
        }
    }
    return $diasUteis;
}

// =============================================
// FILTROS
// =============================================
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$diasUteisMes = contarDiasUteis($mes, $ano);

// =============================================
// BUSCAR DADOS
// =============================================

// Total de funcionários
$stmtTotal = $pdo->query("SELECT COUNT(*) as total FROM funcionarios WHERE status = 'ativo'");
$totalFuncionarios = $stmtTotal->fetch()['total'];

// Salário médio
$stmtSalario = $pdo->query("SELECT AVG(salario) as media FROM funcionarios WHERE status = 'ativo' AND salario > 0");
$salarioMedio = $stmtSalario->fetch()['media'] ?? 0;

// Total da folha
$stmtFolha = $pdo->query("SELECT SUM(salario) as total FROM funcionarios WHERE status = 'ativo'");
$totalFolha = $stmtFolha->fetch()['total'] ?? 0;

// =============================================
// BUSCAR HORÁRIOS DOS FUNCIONÁRIOS
// =============================================
$stmtHorarios = $pdo->query("
    SELECT 
        h.funcionario_id,
        h.hora_entrada,
        h.hora_saida,
        h.carga_horaria,
        h.turno
    FROM horarios_trabalho h
    WHERE h.dia_semana = 'segunda'
");
$horariosFuncionarios = [];
while ($row = $stmtHorarios->fetch()) {
    $horariosFuncionarios[$row['funcionario_id']] = $row;
}

// =============================================
// FUNCIONÁRIOS COM SALÁRIO E CÁLCULO DE FALTAS
// =============================================
$stmtFuncionarios = $pdo->query("
    SELECT 
        f.id,
        f.nome,
        f.cargo,
        f.departamento,
        f.salario_base,
        f.salario,
        f.status,
        f.data_admissao,
        COUNT(DISTINCT p.data) as dias_com_ponto,
        SUM(CASE WHEN p.status = 'presente' THEN 1 ELSE 0 END) as presencas,
        SUM(CASE WHEN p.status = 'ausente' THEN 1 ELSE 0 END) as ausencias_registradas,
        COALESCE(SUM(p.horas_trabalhadas), 0) as horas_trabalhadas
    FROM funcionarios f
    LEFT JOIN presenca_qr p ON f.id = p.funcionario_id 
        AND MONTH(p.data) = $mes AND YEAR(p.data) = $ano
    WHERE f.status = 'ativo'
    GROUP BY f.id
    ORDER BY f.nome
");
$funcionariosSalario = $stmtFuncionarios->fetchAll();

// =============================================
// CALCULAR FALTAS E DESCONTOS
// =============================================
$totalInss = 0;
$totalIrt = 0;
$totalSaude = 0;
$totalDescontoFaltas = 0;
$totalDescontoFaltasDias = 0;
$totalDescontoFaltasHoras = 0;
$totalDescontos = 0;
$totalLiquido = 0;
$totalFaltasGeral = 0;
$totalHorasFaltaGeral = 0;
$totalPresencasGeral = 0;
$totalDiasComPonto = 0;
$totalHorasTrabalhadas = 0;

$dadosFuncionarios = [];

foreach($funcionariosSalario as $func) {
    $salario = floatval($func['salario'] ?? 0);
    $diasComPonto = intval($func['dias_com_ponto'] ?? 0);
    $presencas = intval($func['presencas'] ?? 0);
    $ausenciasRegistradas = intval($func['ausencias_registradas'] ?? 0);
    $horasTrabalhadas = floatval($func['horas_trabalhadas'] ?? 0);
    
    // =============================================
    // CÁLCULO CORRETO DE FALTAS
    // =============================================
    // FALTAS = Dias úteis do mês - Dias com ponto (que o funcionário registrou)
    // Se o funcionário registrou 1 dia, ele faltou 21 dias (22 - 1)
    $faltas = $diasUteisMes - $diasComPonto;
    if ($faltas < 0) $faltas = 0;
    
    // =============================================
    // CÁLCULO DE HORAS DE FALTA
    // =============================================
    // Buscar carga horária do funcionário (se cadastrada)
    $horasPorDia = 8; // padrão
    if (isset($horariosFuncionarios[$func['id']])) {
        $cargaHoraria = $horariosFuncionarios[$func['id']]['carga_horaria'];
        if ($cargaHoraria > 0) {
            $horasPorDia = $cargaHoraria;
        }
    }
    
    // Horas esperadas = Dias com ponto × Horas por dia
    $horasEsperadas = $diasComPonto * $horasPorDia;
    
    // Horas de falta = Horas esperadas - Horas trabalhadas
    $horasFalta = $horasEsperadas - $horasTrabalhadas;
    if ($horasFalta < 0) $horasFalta = 0;
    
    // =============================================
    // CÁLCULO DOS DESCONTOS
    // =============================================
    $desc = calcularDescontos($salario, $faltas, $horasFalta);
    
    $dadosFuncionarios[] = [
        'id' => $func['id'],
        'nome' => $func['nome'],
        'cargo' => $func['cargo'],
        'departamento' => $func['departamento'],
        'salario' => $salario,
        'status' => $func['status'],
        'dias_uteis' => $diasUteisMes,
        'dias_com_ponto' => $diasComPonto,
        'presencas' => $presencas,
        'ausencias_registradas' => $ausenciasRegistradas,
        'faltas' => $faltas,
        'horas_trabalhadas' => $horasTrabalhadas,
        'horas_esperadas' => $horasEsperadas,
        'horas_falta' => $horasFalta,
        'horas_por_dia' => $horasPorDia,
        'valor_dia' => $desc['valor_dia'],
        'valor_hora' => $desc['valor_hora'],
        'descontos' => $desc
    ];
    
    $totalInss += $desc['inss'];
    $totalIrt += $desc['irt'];
    $totalSaude += $desc['saude'];
    $totalDescontoFaltas += $desc['desconto_faltas'];
    $totalDescontoFaltasDias += $desc['desconto_faltas_dias'];
    $totalDescontoFaltasHoras += $desc['desconto_faltas_horas'];
    $totalDescontos += $desc['total'];
    $totalLiquido += $desc['liquido'];
    $totalFaltasGeral += $faltas;
    $totalHorasFaltaGeral += $horasFalta;
    $totalPresencasGeral += $presencas;
    $totalDiasComPonto += $diasComPonto;
    $totalHorasTrabalhadas += $horasTrabalhadas;
}

// =============================================
// DEPARTAMENTOS
// =============================================
$stmtDept = $pdo->query("SELECT 
    departamento, 
    COUNT(*) as total,
    SUM(salario) as total_salario,
    AVG(salario) as media_salario
    FROM funcionarios 
    WHERE status = 'ativo' AND departamento IS NOT NULL AND departamento != ''
    GROUP BY departamento 
    ORDER BY total DESC");
$departamentos = $stmtDept->fetchAll();

// =============================================
// STATUS
// =============================================
$stmtStatus = $pdo->query("SELECT 
    status, 
    COUNT(*) as total 
    FROM funcionarios 
    GROUP BY status");
$statusLista = $stmtStatus->fetchAll();

// =============================================
// TOP FALTAS
// =============================================
$stmtFaltas = $pdo->query("
    SELECT 
        f.id,
        f.nome,
        f.cargo,
        f.salario,
        COUNT(DISTINCT p.data) as dias_com_ponto,
        $diasUteisMes as dias_uteis,
        ($diasUteisMes - COUNT(DISTINCT p.data)) as faltas,
        COALESCE(SUM(p.horas_trabalhadas), 0) as horas_trabalhadas
    FROM funcionarios f
    LEFT JOIN presenca_qr p ON f.id = p.funcionario_id 
        AND MONTH(p.data) = $mes AND YEAR(p.data) = $ano
    WHERE f.status = 'ativo'
    GROUP BY f.id
    HAVING faltas > 0
    ORDER BY faltas DESC
    LIMIT 10
");
$faltasLista = $stmtFaltas->fetchAll();

// =============================================
// ESTATÍSTICAS DO MÊS
// =============================================
$stmtEstPresenca = $pdo->query("
    SELECT 
        COUNT(*) as total_dias,
        SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as total_presentes,
        SUM(CASE WHEN status = 'ausente' THEN 1 ELSE 0 END) as total_ausentes,
        SUM(horas_trabalhadas) as total_horas
    FROM presenca_qr 
    WHERE MONTH(data) = $mes AND YEAR(data) = $ano
");
$estPresenca = $stmtEstPresenca->fetch();

// Nome do mês
$nomeMes = date('F', mktime(0, 0, 0, $mes, 1, $ano));
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios RH - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f0f4f8; 
            color: #1a2332;
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 20px;
            max-width: calc(100% - 280px);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        .page-title { font-size: 28px; font-weight: 800; color: #1a2332; }
        .page-title span { color: #c9a84c; }
        .page-subtitle { color: #64748b; font-size: 14px; margin-top: 4px; }
        
        .btn-outline {
            background: transparent;
            color: #c9a84c;
            padding: 10px 24px;
            border: 2px solid #c9a84c;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-outline:hover { background: rgba(197,165,50,0.1); transform: translateY(-2px); }
        
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,165,50,0.3); }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 18px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .stat-card .number { font-size: 24px; font-weight: 800; color: #1a2332; }
        .stat-card .label { font-size: 11px; color: #94a3b8; margin-top: 4px; }
        .stat-card .icon { font-size: 22px; display: block; margin-bottom: 8px; }
        .stat-card .sub-info { font-size: 10px; color: #94a3b8; margin-top: 5px; }
        .stat-card.primary { border-left: 4px solid #3498db; }
        .stat-card.success { border-left: 4px solid #2ecc71; }
        .stat-card.warning { border-left: 4px solid #f39c12; }
        .stat-card.danger { border-left: 4px solid #e74c3c; }
        .stat-card.purple { border-left: 4px solid #8b5cf6; }
        .stat-card.gold { border-left: 4px solid #c9a84c; }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eef2f7;
            margin-top: 15px;
            overflow-x: auto;
        }
        .table-container table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .table-container thead { background: #f8fafc; }
        .table-container th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .table-container td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        .table-container tr:hover { background: #f8fafc; }
        
        .badge-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-status.ativo { background: #d1fae5; color: #065f46; }
        .badge-status.ferias { background: #fef3c7; color: #92400e; }
        .badge-status.inativo { background: #fee2e2; color: #991b1b; }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a2332;
            margin: 25px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .section-title .icon { color: #c9a84c; }
        .section-title .periodo {
            font-size: 12px;
            font-weight: 400;
            color: #94a3b8;
            background: #f8fafc;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .valor-positivo { color: #2ecc71; font-weight: 600; }
        .valor-negativo { color: #e74c3c; font-weight: 600; }
        .valor-destaque { color: #c9a84c; font-weight: 700; }
        
        .filtros {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filtros .grupo {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filtros .grupo label {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filtros .grupo select,
        .filtros .grupo input {
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            background: white;
            min-width: 120px;
        }
        .filtros .grupo select:focus,
        .filtros .grupo input:focus {
            border-color: #c9a84c;
            outline: none;
        }
        
        .info-box {
            background: #f8fafc;
            border: 1px solid #eef2f7;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 12px;
            color: #64748b;
        }
        .info-box .item strong {
            color: #1a2332;
        }
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; max-width: 100%; padding: 15px; }
            .grid-2 { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .filtros { flex-direction: column; align-items: stretch; }
            .filtros .grupo { width: 100%; }
            .filtros .grupo select,
            .filtros .grupo input { width: 100%; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">📈 <span>Relatórios</span> RH</h1>
                    <p class="page-subtitle">Análise completa com cálculo de faltas por dias úteis</p>
                </div>
                <div>
                    <a href="index.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Voltar ao RH</a>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filtros">
                <div class="grupo">
                    <label>Mês</label>
                    <select name="mes" id="selectMes">
                        <?php for($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $mes == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                                <?= date('F', mktime(0,0,0,$m,1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="grupo">
                    <label>Ano</label>
                    <select name="ano" id="selectAno">
                        <?php for($a = date('Y'); $a >= 2020; $a--): ?>
                            <option value="<?= $a ?>" <?= $ano == $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="grupo">
                    <label>&nbsp;</label>
                    <button class="btn-gold" onclick="aplicarFiltro()"><i class="fas fa-filter"></i> Filtrar</button>
                </div>
                <div class="grupo" style="margin-left: auto;">
                    <span style="font-size: 12px; color: #94a3b8;">
                        <strong>Dias úteis:</strong> <?= $diasUteisMes ?> dias
                    </span>
                </div>
            </div>
            
            <!-- Info Box -->
            <div class="info-box">
                <div class="item"><strong>Dias úteis:</strong> <?= $diasUteisMes ?> dias</div>
                <div class="item"><strong>Horas/dia padrão:</strong> 8 horas</div>
                <div class="item"><strong>Total horas mês:</strong> <?= $diasUteisMes * 8 ?> horas</div>
                <div class="item"><strong>Falta =</strong> Salário ÷ 22 dias</div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <span class="icon">👥</span>
                    <div class="number"><?= $totalFuncionarios ?></div>
                    <div class="label">Total de Funcionários</div>
                    <div class="sub-info">Ativos no sistema</div>
                </div>
                <div class="stat-card gold">
                    <span class="icon">💰</span>
                    <div class="number">Kz <?= number_format($salarioMedio, 2, ',', '.') ?></div>
                    <div class="label">Salário Médio</div>
                    <div class="sub-info">Média dos salários</div>
                </div>
                <div class="stat-card warning">
                    <span class="icon">📊</span>
                    <div class="number">Kz <?= number_format($totalFolha, 2, ',', '.') ?></div>
                    <div class="label">Total da Folha</div>
                    <div class="sub-info">Soma de todos os salários</div>
                </div>
                <div class="stat-card danger">
                    <span class="icon">❌</span>
                    <div class="number"><?= $totalFaltasGeral ?></div>
                    <div class="label">Total de Faltas</div>
                    <div class="sub-info"><?= $nomeMes ?>/<?= $ano ?></div>
                </div>
                <div class="stat-card danger">
                    <span class="icon">⏱️</span>
                    <div class="number"><?= number_format($totalHorasFaltaGeral, 1) ?>h</div>
                    <div class="label">Horas de Falta</div>
                    <div class="sub-info"><?= $nomeMes ?>/<?= $ano ?></div>
                </div>
                <div class="stat-card success">
                    <span class="icon">✅</span>
                    <div class="number"><?= number_format($totalHorasTrabalhadas, 1) ?>h</div>
                    <div class="label">Horas Trabalhadas</div>
                    <div class="sub-info"><?= $nomeMes ?>/<?= $ano ?></div>
                </div>
            </div>
            
            <!-- Grid 2 colunas -->
            <div class="grid-2">
                <!-- Departamentos -->
                <div>
                    <div class="section-title">
                        <span class="icon">🏢</span> Funcionários por Departamento
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Departamento</th>
                                    <th>Total</th>
                                    <th>Salário Médio</th>
                                    <th>Total Salários</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($departamentos) > 0): ?>
                                    <?php foreach($departamentos as $dept): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($dept['departamento'] ?? 'Não definido') ?></strong></td>
                                        <td><?= $dept['total'] ?></td>
                                        <td>Kz <?= number_format($dept['media_salario'] ?? 0, 2, ',', '.') ?></td>
                                        <td>Kz <?= number_format($dept['total_salario'] ?? 0, 2, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center; padding:20px; color:#999;">Nenhum departamento cadastrado</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Status -->
                <div>
                    <div class="section-title">
                        <span class="icon">📌</span> Funcionários por Status
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Percentual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalGeral = array_sum(array_column($statusLista, 'total'));
                                foreach($statusLista as $st): 
                                    $percentual = $totalGeral > 0 ? round(($st['total'] / $totalGeral) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge-status <?= $st['status'] ?>">
                                            <?= ucfirst($st['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $st['total'] ?></td>
                                    <td><?= $percentual ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Detalhamento de Salários com Descontos e Faltas -->
            <div class="section-title" style="margin-top: 30px;">
                <span class="icon">💰</span> Detalhamento de Salários e Descontos
                <span class="periodo"><?= $nomeMes ?>/<?= $ano ?> - <?= $diasUteisMes ?> dias úteis</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Cargo</th>
                            <th>Salário</th>
                            <th>Valor Dia</th>
                            <th>Dias Ponto</th>
                            <th>Faltas</th>
                            <th>Horas Falta</th>
                            <th>Desc. Faltas</th>
                            <th>INSS</th>
                            <th>IRT</th>
                            <th>Saúde</th>
                            <th>Total Desc.</th>
                            <th>Salário Líquido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($dadosFuncionarios) > 0): ?>
                            <?php foreach($dadosFuncionarios as $func): 
                                $desc = $func['descontos'];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($func['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($func['cargo'] ?? '—') ?></td>
                                <td>Kz <?= number_format($func['salario'], 2, ',', '.') ?></td>
                                <td>Kz <?= number_format($func['valor_dia'], 2, ',', '.') ?></td>
                                <td><?= $func['dias_com_ponto'] ?></td>
                                <td class="valor-negativo"><?= $func['faltas'] ?></td>
                                <td class="valor-negativo"><?= number_format($func['horas_falta'], 1) ?>h</td>
                                <td class="valor-negativo">Kz <?= number_format($desc['desconto_faltas'], 2, ',', '.') ?></td>
                                <td class="valor-negativo">Kz <?= number_format($desc['inss'], 2, ',', '.') ?></td>
                                <td class="valor-negativo">Kz <?= number_format($desc['irt'], 2, ',', '.') ?></td>
                                <td class="valor-negativo">Kz <?= number_format($desc['saude'], 2, ',', '.') ?></td>
                                <td class="valor-negativo" style="font-weight: 700;">Kz <?= number_format($desc['total'], 2, ',', '.') ?></td>
                                <td class="valor-positivo" style="font-weight: 700;">Kz <?= number_format($desc['liquido'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="13" style="text-align:center; padding:20px; color:#999;">Nenhum funcionário cadastrado</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Resumo de Descontos -->
            <div class="section-title" style="margin-top: 30px;">
                <span class="icon">📊</span> Resumo Geral de Descontos
                <span class="periodo"><?= $nomeMes ?>/<?= $ano ?></span>
            </div>
            
            <div class="grid-2" style="margin-top: 0;">
                <div class="stat-card primary" style="text-align:center;">
                    <div class="number" style="font-size: 18px;">Kz <?= number_format($totalInss, 2, ',', '.') ?></div>
                    <div class="label">Total INSS (11%)</div>
                </div>
                <div class="stat-card warning" style="text-align:center;">
                    <div class="number" style="font-size: 18px;">Kz <?= number_format($totalIrt, 2, ',', '.') ?></div>
                    <div class="label">Total IRT (10%)</div>
                </div>
                <div class="stat-card purple" style="text-align:center;">
                    <div class="number" style="font-size: 18px;">Kz <?= number_format($totalSaude, 2, ',', '.') ?></div>
                    <div class="label">Total Saúde (3%)</div>
                </div>
                <div class="stat-card danger" style="text-align:center;">
                    <div class="number" style="font-size: 18px;">Kz <?= number_format($totalDescontoFaltas, 2, ',', '.') ?></div>
                    <div class="label">Total Desconto Faltas</div>
                    <div class="sub-info"><?= $totalFaltasGeral ?> faltas</div>
                </div>
                <div class="stat-card danger" style="text-align:center; border-left-color: #e74c3c;">
                    <div class="number" style="font-size: 18px;">Kz <?= number_format($totalDescontos, 2, ',', '.') ?></div>
                    <div class="label">Total de Descontos</div>
                </div>
                <div class="stat-card success" style="text-align:center;">
                    <div class="number" style="font-size: 18px;">Kz <?= number_format($totalLiquido, 2, ',', '.') ?></div>
                    <div class="label">Total Salário Líquido</div>
                </div>
                <div class="stat-card gold" style="text-align:center; grid-column: span 2;">
                    <div class="number" style="font-size: 22px;">Kz <?= number_format($totalFolha, 2, ',', '.') ?></div>
                    <div class="label">Total Folha Bruta</div>
                    <div class="sub-info">Diferença: Kz <?= number_format($totalFolha - $totalLiquido, 2, ',', '.') ?> em descontos</div>
                </div>
            </div>
            
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        function aplicarFiltro() {
            const mes = document.getElementById('selectMes').value;
            const ano = document.getElementById('selectAno').value;
            window.location.href = 'relatorios.php?mes=' + mes + '&ano=' + ano;
        }
        
        document.querySelectorAll('.filtros select').forEach(el => {
            el.addEventListener('change', function() {
                aplicarFiltro();
            });
        });
    </script>
</body>
</html>