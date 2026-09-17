<?php
// relatorios.php - Relatórios RH
require_once '../../config/database.php';

// =============================================
// CONFIGURAÇÃO
// =============================================
date_default_timezone_set('Africa/Luanda');

// =============================================
// BUSCAR DADOS DA EMPRESA
// =============================================
$empresa = [];
try {
    $stmtEmpresa = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmtEmpresa->fetch();
} catch (Exception $e) {
    $empresa = [];
}

// Dados da empresa com fallback
$razao_social = $empresa['razao_social'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';
$nome_fantasia = $empresa['nome_fantasia'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';
$endereco = $empresa['endereco'] ?? 'Km44, Desvio do Bom Jesus';
$numero = $empresa['numero'] ?? '';
$complemento = $empresa['complemento'] ?? '';
$bairro = $empresa['bairro'] ?? 'Centro';
$cidade = $empresa['cidade'] ?? 'Icolo e Bengo';
$estado = $empresa['estado'] ?? '';
$cep = $empresa['cep'] ?? '500109175';
$telefone = $empresa['telefone'] ?? '972902412';
$celular = $empresa['celular'] ?? '972902412';
$email = $empresa['email'] ?? 'complexoescolarcasteloreis@gmail.com';
$site = $empresa['site'] ?? 'www.softgest.com';
$cnpj = $empresa['cnpj'] ?? '500 10923';
$inscricao_estadual = $empresa['inscricao_estadual'] ?? '';
$inscricao_municipal = $empresa['inscricao_municipal'] ?? '';
$logo = $empresa['logo'] ?? '';

// =============================================
// CONFIGURAÇÕES DE DESCONTOS (PADRÃO)
// =============================================
$config_descontos_default = [
    'inss' => 11,
    'irt' => 10,
    'saude' => 3,
    'dias_uteis' => 22,
    'horas_dia' => 8,
    'limite_irt' => 100000
];

// Carregar configurações da sessão ou usar padrão
if (isset($_SESSION['config_descontos'])) {
    $config_descontos = array_merge($config_descontos_default, $_SESSION['config_descontos']);
} else {
    $config_descontos = $config_descontos_default;
}

// Salvar configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config'])) {
    $config_descontos['inss'] = floatval($_POST['inss'] ?? 11);
    $config_descontos['irt'] = floatval($_POST['irt'] ?? 10);
    $config_descontos['saude'] = floatval($_POST['saude'] ?? 3);
    $config_descontos['dias_uteis'] = intval($_POST['dias_uteis'] ?? 22);
    $config_descontos['horas_dia'] = intval($_POST['horas_dia'] ?? 8);
    $config_descontos['limite_irt'] = floatval($_POST['limite_irt'] ?? 100000);
    
    $_SESSION['config_descontos'] = $config_descontos;
    
    // Redirecionar para evitar reenvio
    header('Location: ' . $_SERVER['PHP_SELF'] . '?mes=' . ($_GET['mes'] ?? date('m')) . '&ano=' . ($_GET['ano'] ?? date('Y')) . '&config=salvo');
    exit;
}

// Reset config
if (isset($_GET['reset_config'])) {
    unset($_SESSION['config_descontos']);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?mes=' . ($_GET['mes'] ?? date('m')) . '&ano=' . ($_GET['ano'] ?? date('Y')));
    exit;
}

// =============================================
// FUNÇÕES DE CÁLCULO
// =============================================

function calcularDescontos($salario, $config, $faltas = 0, $horasFalta = 0) {
    $diasUteis = $config['dias_uteis'];
    $horasDia = $config['horas_dia'];
    $percInss = $config['inss'] / 100;
    $percIrt = $config['irt'] / 100;
    $percSaude = $config['saude'] / 100;
    $limiteIrt = $config['limite_irt'];
    
    // Cálculo do valor do dia e hora
    $valorDia = $salario / $diasUteis;
    $valorHora = $valorDia / $horasDia;
    
    // Desconto por faltas
    $descontoFaltasDias = $valorDia * $faltas;
    $descontoFaltasHoras = $valorHora * $horasFalta;
    $descontoFaltas = $descontoFaltasDias + $descontoFaltasHoras;
    
    // INSS
    $inss = $salario * $percInss;
    
    // IRT
    if ($salario > $limiteIrt) {
        $irt = ($salario - $limiteIrt) * $percIrt;
    } else {
        $irt = 0;
    }
    
    // Seguro de Saúde
    $saude = $salario * $percSaude;
    
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

// =============================================
// FILTROS
// =============================================
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');

// =============================================
// BUSCAR DADOS - USANDO A TABELA funcionarios
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
// BUSCAR FUNCIONÁRIOS E PRESENÇAS
// =============================================

// Buscar todos os funcionários ativos com suas presenças
$stmtFuncionarios = $pdo->query("
    SELECT 
        f.id,
        f.nome,
        f.cargo,
        f.departamento,
        f.salario,
        f.status,
        f.data_admissao,
        f.iban,
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
    
    // Cálculo de faltas (dias úteis - dias com ponto)
    $faltas = $config_descontos['dias_uteis'] - $diasComPonto;
    if ($faltas < 0) $faltas = 0;
    
    // Horas por dia
    $horasPorDia = $config_descontos['horas_dia'];
    
    $horasEsperadas = $diasComPonto * $horasPorDia;
    $horasFalta = $horasEsperadas - $horasTrabalhadas;
    if ($horasFalta < 0) $horasFalta = 0;
    
    // Calcular descontos
    $desc = calcularDescontos($salario, $config_descontos, $faltas, $horasFalta);
    
    $dadosFuncionarios[] = [
        'id' => $func['id'],
        'nome' => $func['nome'],
        'cargo' => $func['cargo'],
        'departamento' => $func['departamento'],
        'salario' => $salario,
        'status' => $func['status'],
        'iban' => $func['iban'] ?? '',
        'dias_uteis' => $config_descontos['dias_uteis'],
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

// Nome do mês
$nomeMes = date('F', mktime(0, 0, 0, $mes, 1, $ano));

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios RH - <?= htmlspecialchars($nome_fantasia) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== ESTILOS COMPACTOS ===== */
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
        
        /* HEADER DA EMPRESA */
        .empresa-header {
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            border-radius: 16px;
            padding: 30px 35px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(26,35,50,0.2);
        }
        .empresa-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 60%;
            height: 200%;
            background: radial-gradient(circle, rgba(201,168,76,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .empresa-header .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            z-index: 1;
        }
        .empresa-header .logo-area {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .empresa-header .logo-placeholder {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            color: #1a2332;
            font-family: 'Playfair Display', serif;
            flex-shrink: 0;
        }
        .empresa-header .logo-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }
        .empresa-header .empresa-nome { color: white; }
        .empresa-header .empresa-nome h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            color: #f5d76e;
            letter-spacing: 1px;
            line-height: 1.2;
        }
        .empresa-header .empresa-nome .subtitle {
            color: rgba(255,255,255,0.7);
            font-size: 13px;
            font-weight: 300;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .empresa-header .empresa-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px 30px;
            color: rgba(255,255,255,0.8);
            font-size: 13px;
        }
        .empresa-header .empresa-info .item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .empresa-header .empresa-info .item i {
            color: #f5d76e;
            width: 16px;
            font-size: 14px;
        }
        .empresa-header .empresa-info .item strong { color: white; font-weight: 600; }
        .empresa-header .decoration-line {
            height: 3px;
            background: linear-gradient(90deg, #f5d76e, #c9a84c, #f5d76e);
            width: 100%;
            margin-top: 15px;
            border-radius: 2px;
            position: relative;
            z-index: 1;
        }
        
        /* TÍTULO DO RELATÓRIO */
        .report-title {
            text-align: center;
            padding: 15px 0;
            margin-bottom: 20px;
        }
        .report-title h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: #1a2332;
            letter-spacing: 2px;
        }
        .report-title h2 span { color: #c9a84c; }
        .report-title .periodo {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        .report-title .periodo strong { color: #1a2332; }
        
        /* PAGE HEADER */
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
        
        .btn-print {
            background: #1a2332;
            color: white;
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
        .btn-print:hover { background: #2c3e50; transform: translateY(-2px); }
        
        .btn-print-inss {
            background: #e74c3c;
            color: white;
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
        .btn-print-inss:hover { background: #c0392b; transform: translateY(-2px); }
        
        /* STATS CARDS */
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
        
        /* TABELA */
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
        
        .valor-positivo { color: #2ecc71; font-weight: 600; }
        .valor-negativo { color: #e74c3c; font-weight: 600; }
        .valor-destaque { color: #c9a84c; font-weight: 700; }
        
        /* FILTROS */
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
        .info-box .item strong { color: #1a2332; }
        .destaque-amarelo {
            background: #fef9e7;
            color: #c9a84c;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        .total-row td {
            font-weight: 700;
            background: #f8fafc;
            border-top: 2px solid #c9a84c;
        }
        
        .info-legenda {
            background: #fef9e7;
            border: 1px solid #f5d76e;
            border-radius: 8px;
            padding: 8px 15px;
            font-size: 11px;
            color: #7a6a2a;
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .info-legenda .item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .info-legenda .item .cor {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        
        .config-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #eef2f7;
            margin-bottom: 25px;
        }
        .config-panel h4 {
            font-size: 14px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .config-panel .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .config-panel .config-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .config-panel .config-group label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .config-panel .config-group input {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            width: 100%;
        }
        .config-panel .config-group input:focus {
            border-color: #c9a84c;
            outline: none;
        }
        .config-panel .config-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .config-panel .config-actions .btn-save {
            background: #c9a84c;
            color: #1a2332;
            padding: 8px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .config-panel .config-actions .btn-save:hover {
            background: #b8973a;
            transform: translateY(-2px);
        }
        .config-panel .config-actions .btn-reset {
            background: #f1f5f9;
            color: #4a5568;
            padding: 8px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .config-panel .config-actions .btn-reset:hover {
            background: #e2e8f0;
        }
        .config-panel .config-info {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }
        
        .iban-col {
            font-family: monospace;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        
        .total-geral {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px 20px;
            margin-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            border: 1px solid #eef2f7;
        }
        .total-geral .item {
            display: flex;
            flex-direction: column;
        }
        .total-geral .item .label {
            font-size: 10px;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 0.5px;
        }
        .total-geral .item .value {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
        }
        .total-geral .item .value.positivo { color: #2ecc71; }
        .total-geral .item .value.negativo { color: #e74c3c; }
        
        .footer-empresa {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eef2f7;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 12px;
            color: #94a3b8;
        }
        .footer-empresa strong { color: #1a2332; }
        
        /* IMPRESSÃO */
        @media print {
            .no-print { display: none !important; }
            body { background: white; margin: 0; padding: 0; }
            .main-content { margin: 0; max-width: 100%; padding: 10px; }
            .table-container { border: 1px solid #ddd; }
            .table-container th { background: #f5f5f5 !important; }
            .stat-card { border: 1px solid #ddd; box-shadow: none; }
            .btn-print, .btn-print-inss, .btn-gold, .btn-outline { display: none !important; }
            .config-panel { display: none !important; }
            .filtros { display: none !important; }
            .page-header .btn-outline { display: none !important; }
            .empresa-header { background: #1a2332 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .empresa-header .logo-placeholder { background: #c9a84c !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .empresa-header .empresa-nome h1 { color: #f5d76e !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .empresa-header .decoration-line { background: #f5d76e !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .empresa-header .empresa-info .item i { color: #f5d76e !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; max-width: 100%; padding: 15px; }
            .empresa-header .header-content { flex-direction: column; align-items: flex-start; }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .filtros { flex-direction: column; align-items: stretch; }
            .filtros .grupo { width: 100%; }
            .filtros .grupo select,
            .filtros .grupo input { width: 100%; }
            .config-panel .config-grid { grid-template-columns: 1fr 1fr; }
            .empresa-header .empresa-info { flex-direction: column; gap: 8px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .config-panel .config-grid { grid-template-columns: 1fr; }
            .empresa-header .logo-area { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            
            <!-- ===== HEADER DA EMPRESA ===== -->
            <div class="empresa-header">
                <div class="header-content">
                    <div class="logo-area">
                        <div class="logo-placeholder">
                            <?php if (!empty($logo) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/softgest_web/' . $logo)): ?>
                                <img src="<?= '/softgest_web/' . $logo ?>" alt="Logo">
                            <?php else: ?>
                                CR
                            <?php endif; ?>
                        </div>
                        <div class="empresa-nome">
                            <h1><?= htmlspecialchars($nome_fantasia) ?></h1>
                            <div class="subtitle"><?= htmlspecialchars($razao_social) ?></div>
                        </div>
                    </div>
                    <div class="empresa-info">
                        <div class="item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($endereco) ?><?= !empty($numero) ? ', ' . htmlspecialchars($numero) : '' ?><?= !empty($complemento) ? ' - ' . htmlspecialchars($complemento) : '' ?></span>
                        </div>
                        <div class="item">
                            <i class="fas fa-city"></i>
                            <span><?= htmlspecialchars($cidade) ?><?= !empty($estado) ? ' - ' . htmlspecialchars($estado) : '' ?></span>
                        </div>
                        <div class="item">
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($telefone) ?><?= !empty($celular) ? ' / ' . htmlspecialchars($celular) : '' ?></span>
                        </div>
                        <div class="item">
                            <i class="fas fa-envelope"></i>
                            <span><?= htmlspecialchars($email) ?></span>
                        </div>
                        <div class="item">
                            <i class="fas fa-globe"></i>
                            <span><?= htmlspecialchars($site) ?></span>
                        </div>
                        <?php if (!empty($cnpj)): ?>
                        <div class="item">
                            <i class="fas fa-id-card"></i>
                            <span><strong>CNPJ:</strong> <?= htmlspecialchars($cnpj) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="decoration-line"></div>
            </div>
            
            <!-- ===== PAGE HEADER ===== -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">📈 <span>Relatórios</span> RH</h1>
                    <p class="page-subtitle">Análise completa com cálculo de descontos personalizáveis</p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir Relatório</button>
                    <button class="btn-print-inss" onclick="imprimirMapaSalarios()"><i class="fas fa-file-pdf"></i> Mapa de Salários</button>
                    <a href="index.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
            </div>
            
            <!-- ===== TÍTULO DO RELATÓRIO ===== -->
            <div class="report-title">
                <h2>📊 <span>Mapa de Salários</span> e Descontos</h2>
                <div class="periodo">
                    <strong><?= $nomeMes ?></strong> de <strong><?= $ano ?></strong> &bull; 
                    <?= $config_descontos['dias_uteis'] ?> dias úteis &bull; 
                    <span style="color: #c9a84c;"><?= $totalFuncionarios ?></span> funcionários ativos
                </div>
            </div>
            
            <!-- ===== PAINEL DE CONFIGURAÇÃO ===== -->
            <div class="config-panel no-print">
                <h4><i class="fas fa-sliders-h" style="color: #c9a84c;"></i> Configurações de Descontos</h4>
                <form method="POST">
                    <div class="config-grid">
                        <div class="config-group">
                            <label>INSS (%)</label>
                            <input type="number" name="inss" value="<?= $config_descontos['inss'] ?>" step="0.1" min="0" max="100">
                        </div>
                        <div class="config-group">
                            <label>IRT (%)</label>
                            <input type="number" name="irt" value="<?= $config_descontos['irt'] ?>" step="0.1" min="0" max="100">
                        </div>
                        <div class="config-group">
                            <label>Saúde (%)</label>
                            <input type="number" name="saude" value="<?= $config_descontos['saude'] ?>" step="0.1" min="0" max="100">
                        </div>
                        <div class="config-group">
                            <label>Limite IRT (Kz)</label>
                            <input type="number" name="limite_irt" value="<?= $config_descontos['limite_irt'] ?>" step="1000" min="0">
                        </div>
                        <div class="config-group">
                            <label>Dias Úteis</label>
                            <input type="number" name="dias_uteis" value="<?= $config_descontos['dias_uteis'] ?>" min="1" max="31">
                        </div>
                        <div class="config-group">
                            <label>Horas por Dia</label>
                            <input type="number" name="horas_dia" value="<?= $config_descontos['horas_dia'] ?>" min="1" max="24">
                        </div>
                    </div>
                    <div class="config-actions">
                        <button type="submit" name="salvar_config" class="btn-save"><i class="fas fa-save"></i> Salvar Configurações</button>
                        <a href="?reset_config=1&mes=<?= $mes ?>&ano=<?= $ano ?>" class="btn-reset">
                            <i class="fas fa-undo"></i> Restaurar Padrão
                        </a>
                        <?php if (isset($_GET['config']) && $_GET['config'] == 'salvo'): ?>
                            <span style="color: #2ecc71; font-weight: 600; font-size: 13px;">
                                <i class="fas fa-check-circle"></i> Configurações salvas!
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="config-info">
                        <i class="fas fa-info-circle"></i> 
                        INSS: <?= $config_descontos['inss'] ?>% | 
                        IRT: <?= $config_descontos['irt'] ?>% | 
                        Saúde: <?= $config_descontos['saude'] ?>% | 
                        Limite IRT: Kz <?= number_format($config_descontos['limite_irt'], 0, ',', '.') ?> | 
                        Dias úteis: <?= $config_descontos['dias_uteis'] ?> | 
                        Horas/dia: <?= $config_descontos['horas_dia'] ?>
                    </div>
                </form>
            </div>
            
            <!-- ===== FILTROS ===== -->
            <div class="filtros no-print">
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
                        <strong>Dias úteis:</strong> 
                        <span class="destaque-amarelo"><?= $config_descontos['dias_uteis'] ?> dias</span>
                    </span>
                </div>
            </div>
            
            <!-- ===== INFO BOX ===== -->
            <div class="info-box">
                <div class="item"><strong>📅 Dias úteis:</strong> <?= $config_descontos['dias_uteis'] ?> dias</div>
                <div class="item"><strong>⏰ Horas/dia:</strong> <?= $config_descontos['horas_dia'] ?> horas</div>
                <div class="item"><strong>📊 Total horas mês:</strong> <?= $config_descontos['dias_uteis'] * $config_descontos['horas_dia'] ?> horas</div>
                <div class="item"><strong>💵 Falta =</strong> Salário ÷ <?= $config_descontos['dias_uteis'] ?> dias</div>
                <div class="item"><strong>📊 INSS:</strong> <?= $config_descontos['inss'] ?>%</div>
                <div class="item"><strong>📊 IRT:</strong> <?= $config_descontos['irt'] ?>%</div>
                <div class="item"><strong>📊 Saúde:</strong> <?= $config_descontos['saude'] ?>%</div>
            </div>
            
            <!-- ===== STATS CARDS ===== -->
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
            
            <!-- ===== MAPA DE SALÁRIOS ===== -->
            <div class="section-title" style="margin-top: 30px;">
                <span class="icon">💰</span> Mapa de Salários e Descontos
                <span class="periodo"><?= $nomeMes ?>/<?= $ano ?> - <?= $config_descontos['dias_uteis'] ?> dias úteis</span>
            </div>
            <div class="table-container" id="tabela-salarios">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Funcionário</th>
                            <th>Cargo</th>
                            <th>Salário</th>
                            <th>Valor Dia</th>
                            <th>Dias Ponto</th>
                            <th>Faltas</th>
                            <th>INSS</th>
                            <th>IRT</th>
                            <th>Saúde</th>
                            <th>Desc. Faltas</th>
                            <th>Total Desc.</th>
                            <th>Salário Líquido</th>
                            <th>IBAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($dadosFuncionarios) > 0): ?>
                            <?php $contador = 1; ?>
                            <?php foreach($dadosFuncionarios as $func): 
                                $desc = $func['descontos'];
                            ?>
                            <tr>
                                <td><?= $contador++ ?></td>
                                <td><strong><?= htmlspecialchars($func['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($func['cargo'] ?? '—') ?></td>
                                <td>Kz <?= number_format($func['salario'], 2, ',', '.') ?></td>
                                <td>Kz <?= number_format($func['valor_dia'], 2, ',', '.') ?></td>
                                <td><?= $func['dias_com_ponto'] ?></td>
                                <td class="valor-negativo"><?= $func['faltas'] ?></td>
                                <td class="valor-negativo">Kz <?= number_format($desc['inss'], 2, ',', '.') ?></td>
                                <td class="valor-negativo">Kz <?= number_format($desc['irt'], 2, ',', '.') ?></td>
                                <td class="valor-negativo">Kz <?= number_format($desc['saude'], 2, ',', '.') ?></td>
                                <td class="valor-negativo">Kz <?= number_format($desc['desconto_faltas'], 2, ',', '.') ?></td>
                                <td class="valor-negativo" style="font-weight: 700;">Kz <?= number_format($desc['total'], 2, ',', '.') ?></td>
                                <td class="valor-positivo" style="font-weight: 700;">Kz <?= number_format($desc['liquido'], 2, ',', '.') ?></td>
                                <td class="iban-col"><?= htmlspecialchars($func['iban'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Linha de Total -->
                            <tr class="total-row">
                                <td colspan="3" style="text-align: right; font-size: 13px;">TOTAIS GERAIS:</td>
                                <td>Kz <?= number_format($totalFolha, 2, ',', '.') ?></td>
                                <td colspan="3"></td>
                                <td>Kz <?= number_format($totalInss, 2, ',', '.') ?></td>
                                <td>Kz <?= number_format($totalIrt, 2, ',', '.') ?></td>
                                <td>Kz <?= number_format($totalSaude, 2, ',', '.') ?></td>
                                <td>Kz <?= number_format($totalDescontoFaltas, 2, ',', '.') ?></td>
                                <td style="color: #e74c3c; font-weight: 700;">Kz <?= number_format($totalDescontos, 2, ',', '.') ?></td>
                                <td style="color: #2ecc71; font-weight: 700; font-size: 14px;">Kz <?= number_format($totalLiquido, 2, ',', '.') ?></td>
                                <td></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="14" style="text-align:center; padding:20px; color:#999;">Nenhum funcionário cadastrado</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ===== TOTAL GERAL ===== -->
            <div class="total-geral">
                <div class="item">
                    <span class="label">Total Folha Bruta</span>
                    <span class="value">Kz <?= number_format($totalFolha, 2, ',', '.') ?></span>
                </div>
                <div class="item">
                    <span class="label">Total INSS (<?= $config_descontos['inss'] ?>%)</span>
                    <span class="value negativo">Kz <?= number_format($totalInss, 2, ',', '.') ?></span>
                </div>
                <div class="item">
                    <span class="label">Total IRT (<?= $config_descontos['irt'] ?>%)</span>
                    <span class="value negativo">Kz <?= number_format($totalIrt, 2, ',', '.') ?></span>
                </div>
                <div class="item">
                    <span class="label">Total Saúde (<?= $config_descontos['saude'] ?>%)</span>
                    <span class="value negativo">Kz <?= number_format($totalSaude, 2, ',', '.') ?></span>
                </div>
                <div class="item">
                    <span class="label">Total Descontos</span>
                    <span class="value negativo">Kz <?= number_format($totalDescontos, 2, ',', '.') ?></span>
                </div>
                <div class="item">
                    <span class="label">Total Salário Líquido</span>
                    <span class="value positivo">Kz <?= number_format($totalLiquido, 2, ',', '.') ?></span>
                </div>
            </div>
            
            <!-- ===== LEGENDA ===== -->
            <div class="info-legenda">
                <div class="item">
                    <span class="cor" style="background: #2ecc71;"></span>
                    <span><strong>Verde:</strong> Valores positivos (salário líquido)</span>
                </div>
                <div class="item">
                    <span class="cor" style="background: #e74c3c;"></span>
                    <span><strong>Vermelho:</strong> Valores negativos (descontos)</span>
                </div>
                <div class="item">
                    <span class="cor" style="background: #c9a84c;"></span>
                    <span><strong>Dourado:</strong> Destaque especial</span>
                </div>
                <div class="item">
                    <span class="cor" style="background: #f8fafc; border: 1px solid #c9a84c;"></span>
                    <span><strong>Total:</strong> Linha de resumo geral</span>
                </div>
                <div class="item">
                    <span style="font-family: monospace; background: #f1f5f9; padding: 0 8px; border-radius: 3px;">AO00 0000 0000 0000 0000 000</span>
                    <span><strong>IBAN:</strong> Código bancário</span>
                </div>
            </div>
            
            <!-- ===== FÓRMULAS ===== -->
            <div style="margin-top: 20px; background: #f8fafc; border-radius: 12px; padding: 15px 20px; border: 1px solid #eef2f7;">
                <div style="display: flex; flex-wrap: wrap; gap: 20px; font-size: 13px; color: #64748b;">
                    <div>
                        <strong style="color:#1a2332;">📌 Fórmulas de Cálculo:</strong>
                    </div>
                    <div>
                        <span style="background: #eef2f7; padding: 2px 8px; border-radius: 4px;">
                            Faltas = <?= $config_descontos['dias_uteis'] ?> - Dias com Ponto
                        </span>
                    </div>
                    <div>
                        <span style="background: #eef2f7; padding: 2px 8px; border-radius: 4px;">
                            Valor Dia = Salário ÷ <?= $config_descontos['dias_uteis'] ?>
                        </span>
                    </div>
                    <div>
                        <span style="background: #eef2f7; padding: 2px 8px; border-radius: 4px;">
                            INSS = Salário × <?= $config_descontos['inss'] ?>%
                        </span>
                    </div>
                    <div>
                        <span style="background: #eef2f7; padding: 2px 8px; border-radius: 4px;">
                            IRT = (Salário - <?= number_format($config_descontos['limite_irt'], 0, ',', '.') ?>) × <?= $config_descontos['irt'] ?>%
                        </span>
                    </div>
                    <div>
                        <span style="background: #eef2f7; padding: 2px 8px; border-radius: 4px;">
                            Saúde = Salário × <?= $config_descontos['saude'] ?>%
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- ===== FOOTER ===== -->
            <div class="footer-empresa">
                <div>
                    <strong><?= htmlspecialchars($nome_fantasia) ?></strong><br>
                    <?= htmlspecialchars($endereco) ?><?= !empty($numero) ? ', ' . htmlspecialchars($numero) : '' ?><br>
                    <?= htmlspecialchars($cidade) ?><?= !empty($estado) ? ' - ' . htmlspecialchars($estado) : '' ?>
                    <?= !empty($cep) ? ' - CEP: ' . htmlspecialchars($cep) : '' ?>
                </div>
                <div style="text-align: right;">
                    <div><?= htmlspecialchars($telefone) ?><?= !empty($celular) ? ' / ' . htmlspecialchars($celular) : '' ?></div>
                    <div><?= htmlspecialchars($email) ?></div>
                    <div><?= htmlspecialchars($site) ?></div>
                    <div style="margin-top: 5px; font-size: 11px; color: #bbb;">
                        Relatório gerado em <?= date('d/m/Y H:i:s') ?>
                    </div>
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
        
        function imprimirMapaSalarios() {
            var conteudo = document.getElementById('tabela-salarios').outerHTML;
            
            var janela = window.open('', '_blank', 'width=1200,height=800');
            janela.document.write('<!DOCTYPE html><html><head><title>Mapa de Salários - <?= htmlspecialchars($nome_fantasia) ?></title>');
            janela.document.write('<style>');
            janela.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
            janela.document.write('.header-empresa { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #c9a84c; padding-bottom: 15px; }');
            janela.document.write('.header-empresa h1 { color: #1a2332; font-size: 24px; margin-bottom: 5px; }');
            janela.document.write('.header-empresa h2 { color: #c9a84c; font-size: 18px; font-weight: 400; }');
            janela.document.write('.header-empresa .info { color: #64748b; font-size: 13px; }');
            janela.document.write('table { width: 100%; border-collapse: collapse; font-size: 11px; }');
            janela.document.write('th { background: #1a2332; color: white; padding: 8px 10px; text-align: left; }');
            janela.document.write('td { padding: 6px 10px; border-bottom: 1px solid #eef2f7; }');
            janela.document.write('tr:nth-child(even) { background: #f8fafc; }');
            janela.document.write('.total-row td { font-weight: 700; background: #fef9e7; border-top: 2px solid #c9a84c; }');
            janela.document.write('.valor-positivo { color: #2ecc71; font-weight: 600; }');
            janela.document.write('.valor-negativo { color: #e74c3c; font-weight: 600; }');
            janela.document.write('.iban-col { font-family: monospace; font-size: 10px; }');
            janela.document.write('.footer { text-align: center; margin-top: 30px; font-size: 12px; color: #94a3b8; border-top: 1px solid #eef2f7; padding-top: 20px; }');
            janela.document.write('.config-info { background: #f8fafc; padding: 10px 15px; border-radius: 8px; margin: 15px 0; font-size: 12px; display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; }');
            janela.document.write('.config-info .item { background: #eef2f7; padding: 2px 10px; border-radius: 4px; }');
            janela.document.write('.dados-empresa { background: #f8fafc; padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; font-size: 12px; }');
            janela.document.write('@media print { body { padding: 10px; } }');
            janela.document.write('</style>');
            janela.document.write('</head><body>');
            
            // Header da empresa no mapa
            janela.document.write('<div class="header-empresa">');
            janela.document.write('<h1><?= htmlspecialchars($nome_fantasia) ?></h1>');
            janela.document.write('<h2>Mapa de Salários - <?= $nomeMes ?>/<?= $ano ?></h2>');
            janela.document.write('<div class="info"><?= htmlspecialchars($endereco) ?> | <?= htmlspecialchars($telefone) ?> | <?= htmlspecialchars($email) ?></div>');
            janela.document.write('</div>');
            
            janela.document.write('<div class="dados-empresa">');
            janela.document.write('<span><strong>CNPJ:</strong> <?= htmlspecialchars($cnpj) ?></span>');
            janela.document.write('<span><strong>Cidade:</strong> <?= htmlspecialchars($cidade) ?></span>');
            janela.document.write('<span><strong>Total Funcionários:</strong> <?= $totalFuncionarios ?></span>');
            janela.document.write('<span><strong>Dias úteis:</strong> <?= $config_descontos['dias_uteis'] ?></span>');
            janela.document.write('</div>');
            
            janela.document.write('<div class="config-info">');
            janela.document.write('<span class="item">INSS: <?= $config_descontos['inss'] ?>%</span>');
            janela.document.write('<span class="item">IRT: <?= $config_descontos['irt'] ?>%</span>');
            janela.document.write('<span class="item">Saúde: <?= $config_descontos['saude'] ?>%</span>');
            janela.document.write('<span class="item">Total Folha: Kz <?= number_format($totalFolha, 2, ",", ".") ?></span>');
            janela.document.write('<span class="item">Total Líquido: Kz <?= number_format($totalLiquido, 2, ",", ".") ?></span>');
            janela.document.write('<span class="item">Total Descontos: Kz <?= number_format($totalDescontos, 2, ",", ".") ?></span>');
            janela.document.write('</div>');
            
            janela.document.write(conteudo);
            
            janela.document.write('<div class="footer">');
            janela.document.write('<?= htmlspecialchars($nome_fantasia) ?> - <?= htmlspecialchars($endereco) ?><br>');
            janela.document.write('Gerado em <?= date('d/m/Y H:i:s') ?> | SoftGest Web RH');
            janela.document.write('</div>');
            
            janela.document.write('<script>');
            janela.document.write('window.onload = function() { window.print(); }');
            janela.document.write('<\/script>');
            janela.document.write('</body></html>');
            janela.document.close();
        }
    </script>
</body>
</html>