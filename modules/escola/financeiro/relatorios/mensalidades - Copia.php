<?php
/**
 * Relatório de Mensalidades - Softgest Web
 * Módulo: Escola / Financeiro
 * 
 * @package Softgest
 * @subpackage Modules/Escola/Financeiro/Relatorios
 * @author Softgest Team
 * @version 1.0.0
 * @copyright 2026 Softgest Sistemas
 */

// ============================================
// 1. CONFIGURAÇÕES INICIAIS E SEGURANÇA
// ============================================

// Previne acesso direto sem autenticação
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] == '') {
    header('Location: ../../../../../login.php');
    exit;
}

// ============================================
// 1.1 DEFINIÇÃO DE CAMINHOS ABSOLUTOS
// ============================================

// Define o caminho base do projeto (ABSOLUTO)
$base_path = 'C:/xampp/htdocs/softgest_web';

// Verifica se o caminho base existe
if (!is_dir($base_path)) {
    // Tenta encontrar o caminho relativo como fallback
    $base_path = realpath('../../../../..');
    if ($base_path === false) {
        die('<div class="alert alert-danger">
            <strong>Erro Crítico:</strong> 
            Não foi possível encontrar o diretório base do projeto.
            <br><br>
            <strong>Caminho procurado:</strong> C:/xampp/htdocs/softgest_web
            <br>
            <strong>Arquivo atual:</strong> ' . __FILE__ . '
        </div>');
    }
}

// Define constantes com caminhos absolutos
define('BASE_PATH', $base_path);
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('CLASSES_PATH', INCLUDES_PATH . '/classes');
define('ASSETS_PATH', BASE_PATH . '/assets');

// ============================================
// 1.2 CARREGAMENTO DOS ARQUIVOS (CAMINHOS ABSOLUTOS)
// ============================================

// Função para carregar arquivo com verificação
function carregarArquivo($caminho, $descricao = '') {
    if (file_exists($caminho)) {
        require_once $caminho;
        return true;
    } else {
        $mensagem = "<div class='alert alert-danger' style='margin: 20px; padding: 20px; border-radius: 5px;'>";
        $mensagem .= "<h4><i class='fa fa-exclamation-triangle'></i> Erro ao carregar arquivo</h4>";
        $mensagem .= "<p><strong>Arquivo:</strong> " . htmlspecialchars($caminho) . "</p>";
        if (!empty($descricao)) {
            $mensagem .= "<p><strong>Descrição:</strong> " . htmlspecialchars($descricao) . "</p>";
        }
        $mensagem .= "<p><strong>Diretório atual:</strong> " . __DIR__ . "</p>";
        $mensagem .= "<p><strong>Base path:</strong> " . BASE_PATH . "</p>";
        $mensagem .= "<hr>";
        $mensagem .= "<p><strong>Soluções:</strong></p>";
        $mensagem .= "<ul>";
        $mensagem .= "<li>Verifique se o arquivo existe no caminho especificado</li>";
        $mensagem .= "<li>Verifique as permissões de leitura do arquivo</li>";
        $mensagem .= "<li>Certifique-se de que a estrutura de diretórios está correta</li>";
        $mensagem .= "</ul>";
        $mensagem .= "</div>";
        die($mensagem);
    }
}

try {
    // Carrega arquivos de configuração (CAMINHOS ABSOLUTOS)
    carregarArquivo('C:/xampp/htdocs/softgest_web/config/config.php', 'Arquivo de Configuração Principal');
    carregarArquivo('C:/xampp/htdocs/softgest_web/config/database.php', 'Arquivo de Configuração do Banco de Dados');
    
    carregarArquivo('C:/xampp/htdocs/softgest_web/includes/classes/Pagination.php', 'Classe de Paginação');
    
} catch (Exception $e) {
    die('<div class="alert alert-danger">Erro ao carregar arquivos: ' . $e->getMessage() . '</div>');
}

// ============================================
// 2. VALIDAÇÃO DE PERMISSÕES
// ============================================

$modulo = 'financeiro';
$acao = 'visualizar_relatorios';
if (!function_exists('temPermissao') || !temPermissao($modulo, $acao)) {
    die('<div class="alert alert-danger">Você não tem permissão para acessar este relatório.</div>');
}

// ============================================
// 3. RECEBE E VALIDA PARÂMETROS
// ============================================

// Filtros
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
$id_turma = isset($_GET['id_turma']) ? (int)$_GET['id_turma'] : 0;
$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
$situacao = isset($_GET['situacao']) ? $_GET['situacao'] : 'todos';
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$formato = isset($_GET['formato']) ? $_GET['formato'] : 'html';

// Paginação
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : 50;

// Ordenação
$ordenar_por = isset($_GET['ordenar_por']) ? $_GET['ordenar_por'] : 'aluno_nome';
$ordenar_dir = isset($_GET['ordenar_dir']) && strtoupper($_GET['ordenar_dir']) === 'DESC' ? 'DESC' : 'ASC';

// ============================================
// 4. CONEXÃO COM BANCO DE DADOS
// ============================================

try {
    // Verifica se a classe Database existe
    if (!class_exists('Database')) {
        throw new Exception('Classe Database não encontrada. Verifique o arquivo database.php');
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Falha ao conectar ao banco de dados');
    }
    
} catch (Exception $e) {
    die('<div class="alert alert-danger">
        <h4><i class="fa fa-database"></i> Erro de Conexão</h4>
        <p>' . $e->getMessage() . '</p>
        <hr>
        <p><strong>Arquivo:</strong> ' . __FILE__ . '</p>
        <p><strong>Linha:</strong> ' . __LINE__ . '</p>
    </div>');
}

// ============================================
// 5. CONSTRUÇÃO DA QUERY PRINCIPAL
// ============================================

// Query base para listar mensalidades
$sql_base = "
    SELECT 
        m.id_mensalidade,
        m.id_aluno,
        m.id_turma,
        m.id_plano_pagamento,
        m.data_vencimento,
        m.data_pagamento,
        m.valor_original,
        m.valor_pago,
        m.valor_desconto,
        m.valor_juros,
        m.valor_multa,
        m.situacao,
        m.observacao,
        m.data_cadastro,
        m.data_atualizacao,
        a.nome AS aluno_nome,
        a.matricula,
        a.cpf,
        a.telefone,
        a.email,
        t.nome AS turma_nome,
        t.ano_letivo,
        c.nome AS curso_nome,
        c.sigla AS curso_sigla,
        pp.descricao AS plano_descricao,
        pp.parcelas AS plano_parcelas,
        CASE 
            WHEN m.situacao = 'PAGO' THEN 'Pago'
            WHEN m.situacao = 'PENDENTE' AND m.data_vencimento < CURDATE() THEN 'Vencido'
            WHEN m.situacao = 'PENDENTE' AND m.data_vencimento >= CURDATE() THEN 'A Vencer'
            WHEN m.situacao = 'CANCELADO' THEN 'Cancelado'
            WHEN m.situacao = 'REEMBOLSADO' THEN 'Reembolsado'
            ELSE m.situacao
        END AS status_descricao
    FROM 
        mensalidades m
    INNER JOIN 
        alunos a ON m.id_aluno = a.id_aluno
    INNER JOIN 
        turmas t ON m.id_turma = t.id_turma
    INNER JOIN 
        cursos c ON t.id_curso = c.id_curso
    LEFT JOIN 
        planos_pagamento pp ON m.id_plano_pagamento = pp.id_plano
    WHERE 
        1=1
";

// ============================================
// 6. APLICAÇÃO DOS FILTROS
// ============================================

$params = [];
$tipos_param = [];

// Filtro por ano
if ($ano > 0) {
    $sql_base .= " AND YEAR(m.data_vencimento) = ?";
    $params[] = $ano;
    $tipos_param[] = 'i';
}

// Filtro por mês
if ($mes > 0) {
    $sql_base .= " AND MONTH(m.data_vencimento) = ?";
    $params[] = $mes;
    $tipos_param[] = 'i';
}

// Filtro por turma
if ($id_turma > 0) {
    $sql_base .= " AND m.id_turma = ?";
    $params[] = $id_turma;
    $tipos_param[] = 'i';
}

// Filtro por curso
if ($id_curso > 0) {
    $sql_base .= " AND t.id_curso = ?";
    $params[] = $id_curso;
    $tipos_param[] = 'i';
}

// Filtro por situação
if ($situacao != 'todos' && !empty($situacao)) {
    $sql_base .= " AND m.situacao = ?";
    $params[] = $situacao;
    $tipos_param[] = 's';
}

// Filtro por busca (nome, matrícula, CPF)
if (!empty($busca)) {
    $sql_base .= " AND (a.nome LIKE ? OR a.matricula LIKE ? OR a.cpf LIKE ?)";
    $busca_like = "%" . $busca . "%";
    $params[] = $busca_like;
    $params[] = $busca_like;
    $params[] = $busca_like;
    $tipos_param[] = 's';
    $tipos_param[] = 's';
    $tipos_param[] = 's';
}

// ============================================
// 7. ORDENAÇÃO E PAGINAÇÃO
// ============================================

// Mapeamento dos campos de ordenação
$map_ordenacao = [
    'aluno_nome' => 'a.nome',
    'matricula' => 'a.matricula',
    'data_vencimento' => 'm.data_vencimento',
    'data_pagamento' => 'm.data_pagamento',
    'valor_original' => 'm.valor_original',
    'situacao' => 'm.situacao',
    'turma_nome' => 't.nome',
    'curso_nome' => 'c.nome'
];

$campo_ordenacao = isset($map_ordenacao[$ordenar_por]) ? $map_ordenacao[$ordenar_por] : 'a.nome';
$sql_base .= " ORDER BY {$campo_ordenacao} {$ordenar_dir}";

// ============================================
// 8. EXECUÇÃO DA QUERY E CONTAGEM TOTAL
// ============================================

try {
    // Query para contar total de registros
    $sql_count = "SELECT COUNT(*) as total FROM ({$sql_base}) as subquery";
    $stmt_count = $conn->prepare($sql_count);
    if (!empty($params)) {
        $stmt_count->bind_param(implode('', $tipos_param), ...$params);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $total_registros = $result_count->fetch_assoc()['total'];
    $stmt_count->close();

    // Calcula offset para paginação
    $offset = ($pagina - 1) * $por_pagina;
    $sql_base .= " LIMIT ? OFFSET ?";
    $params[] = $por_pagina;
    $params[] = $offset;
    $tipos_param[] = 'i';
    $tipos_param[] = 'i';

    // Executa query principal
    $stmt = $conn->prepare($sql_base);
    if (!empty($params)) {
        $stmt->bind_param(implode('', $tipos_param), ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

} catch (Exception $e) {
    die('<div class="alert alert-danger">
        <h4><i class="fa fa-exclamation-circle"></i> Erro na Consulta</h4>
        <p>' . $e->getMessage() . '</p>
        <hr>
        <p><strong>SQL:</strong> ' . htmlspecialchars($sql_base) . '</p>
    </div>');
}

// ============================================
// 9. CÁLCULO DOS TOTAIS
// ============================================

$totais = [
    'quantidade' => 0,
    'valor_original' => 0,
    'valor_pago' => 0,
    'valor_desconto' => 0,
    'valor_juros' => 0,
    'valor_multa' => 0,
    'a_receber' => 0,
    'recebido' => 0,
    'pendente' => 0,
    'vencido' => 0
];

$dados = [];
while ($row = $result->fetch_assoc()) {
    $dados[] = $row;
    
    $totais['quantidade']++;
    $totais['valor_original'] += $row['valor_original'];
    $totais['valor_pago'] += $row['valor_pago'];
    $totais['valor_desconto'] += $row['valor_desconto'];
    $totais['valor_juros'] += $row['valor_juros'];
    $totais['valor_multa'] += $row['valor_multa'];
    
    if ($row['situacao'] == 'PAGO') {
        $totais['recebido'] += $row['valor_pago'];
    } elseif ($row['situacao'] == 'PENDENTE') {
        $totais['pendente'] += $row['valor_original'];
        if (strtotime($row['data_vencimento']) < time()) {
            $totais['vencido'] += $row['valor_original'];
        }
    }
}

$totais['a_receber'] = $totais['valor_original'] - $totais['recebido'];

// ============================================
// 10. GERADOR DE RELATÓRIO (HTML, PDF, EXCEL)
// ============================================

// Se for PDF ou Excel, redireciona para o gerador específico
if ($formato == 'pdf') {
    header('Location: gerar_pdf.php?' . http_build_query($_GET));
    exit;
} elseif ($formato == 'excel') {
    header('Location: gerar_excel.php?' . http_build_query($_GET));
    exit;
}

// ============================================
// 11. EXIBIÇÃO DO RELATÓRIO EM HTML
// ============================================

// Função para nome do mês
function mesNome($mes) {
    $meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];
    return $meses[(int)$mes] ?? 'Mês inválido';
}

// Inclui o header (com caminho absoluto)
$header_path = 'C:/xampp/htdocs/softgest_web/includes/header.php';
if (file_exists($header_path)) {
    include_once $header_path;
} else {
    // Fallback para caminho relativo
    $header_fallback = '../../../../includes/header.php';
    if (file_exists($header_fallback)) {
        include_once $header_fallback;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Mensalidades - Softgest</title>
    
    <!-- CSS (caminhos absolutos) -->
    <link rel="stylesheet" href="C:/xampp/htdocs/softgest_web/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="C:/xampp/htdocs/softgest_web/assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="C:/xampp/htdocs/softgest_web/assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="C:/xampp/htdocs/softgest_web/assets/css/select2.min.css">
    <link rel="stylesheet" href="C:/xampp/htdocs/softgest_web/assets/css/style.css">
    
    <style>
        .relatorio-container {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .filtros-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .totais-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .total-item {
            text-align: center;
            padding: 10px;
            border-right: 1px solid #dee2e6;
        }
        .total-item:last-child {
            border-right: none;
        }
        .total-item .valor {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        .total-item .rotulo {
            font-size: 14px;
            color: #6c757d;
        }
        .badge-status {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .badge-pago { background: #d4edda; color: #155724; }
        .badge-pendente { background: #fff3cd; color: #856404; }
        .badge-vencido { background: #f8d7da; color: #721c24; }
        .badge-cancelado { background: #e2e3e5; color: #383d41; }
        .badge-reembolsado { background: #d1ecf1; color: #0c5460; }
        
        .table-relatorio th {
            background: #343a40;
            color: white;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-relatorio td {
            font-size: 13px;
            vertical-align: middle;
        }
        .table-relatorio tr:hover {
            background: #f1f3f5;
        }
        
        .btn-acoes {
            padding: 2px 8px;
            font-size: 12px;
        }
        
        @media print {
            .filtros-card, .btn-acoes, .no-print {
                display: none !important;
            }
            .relatorio-container {
                padding: 0;
                background: white;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid relatorio-container">
    
    <!-- ========================================== -->
    <!-- TÍTULO E BOTÕES DE AÇÃO -->
    <!-- ========================================== -->
    <div class="row mb-3 no-print">
        <div class="col-md-8">
            <h2>
                <i class="fa fa-file-text-o"></i> Relatório de Mensalidades
                <small class="text-muted">Período: <?= mesNome($mes) ?>/<?= $ano ?></small>
            </h2>
        </div>
        <div class="col-md-4 text-right">
            <div class="btn-group">
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fa fa-print"></i> Imprimir
                </button>
                <a href="?<?= http_build_query(array_merge($_GET, ['formato' => 'pdf'])) ?>" 
                   class="btn btn-danger">
                    <i class="fa fa-file-pdf-o"></i> PDF
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['formato' => 'excel'])) ?>" 
                   class="btn btn-success">
                    <i class="fa fa-file-excel-o"></i> Excel
                </a>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- FILTROS -->
    <!-- ========================================== -->
    <div class="filtros-card no-print">
        <form method="GET" class="form-inline" id="formFiltros">
            <div class="form-group mr-2">
                <label class="mr-1">Ano:</label>
                <select name="ano" class="form-control form-control-sm">
                    <?php for($a = date('Y') - 5; $a <= date('Y') + 1; $a++): ?>
                        <option value="<?= $a ?>" <?= $a == $ano ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group mr-2">
                <label class="mr-1">Mês:</label>
                <select name="mes" class="form-control form-control-sm">
                    <?php for($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= mesNome($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group mr-2">
                <label class="mr-1">Curso:</label>
                <select name="id_curso" class="form-control form-control-sm select2" style="width:150px;">
                    <option value="0">Todos</option>
                    <?php 
                    $cursos = $conn->query("SELECT id_curso, nome FROM cursos WHERE ativo = 1 ORDER BY nome");
                    while($c = $cursos->fetch_assoc()): 
                    ?>
                        <option value="<?= $c['id_curso'] ?>" <?= $c['id_curso'] == $id_curso ? 'selected' : '' ?>>
                            <?= $c['nome'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group mr-2">
                <label class="mr-1">Turma:</label>
                <select name="id_turma" class="form-control form-control-sm select2" style="width:150px;">
                    <option value="0">Todas</option>
                    <?php 
                    $turmas = $conn->query("SELECT id_turma, nome FROM turmas WHERE ativo = 1 ORDER BY nome");
                    while($t = $turmas->fetch_assoc()): 
                    ?>
                        <option value="<?= $t['id_turma'] ?>" <?= $t['id_turma'] == $id_turma ? 'selected' : '' ?>>
                            <?= $t['nome'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group mr-2">
                <label class="mr-1">Situação:</label>
                <select name="situacao" class="form-control form-control-sm">
                    <option value="todos" <?= $situacao == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="PAGO" <?= $situacao == 'PAGO' ? 'selected' : '' ?>>Pago</option>
                    <option value="PENDENTE" <?= $situacao == 'PENDENTE' ? 'selected' : '' ?>>Pendente</option>
                    <option value="CANCELADO" <?= $situacao == 'CANCELADO' ? 'selected' : '' ?>>Cancelado</option>
                    <option value="REEMBOLSADO" <?= $situacao == 'REEMBOLSADO' ? 'selected' : '' ?>>Reembolsado</option>
                </select>
            </div>
            
            <div class="form-group mr-2">
                <label class="mr-1">Busca:</label>
                <input type="text" name="busca" class="form-control form-control-sm" 
                       placeholder="Nome, Matrícula ou CPF" value="<?= htmlspecialchars($busca) ?>" style="width:200px;">
            </div>
            
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa fa-search"></i> Filtrar
            </button>
            
            <a href="?ano=<?= date('Y') ?>&mes=<?= date('m') ?>" class="btn btn-secondary btn-sm ml-1">
                <i class="fa fa-refresh"></i> Limpar
            </a>
        </form>
    </div>

    <!-- ========================================== -->
    <!-- TOTAIS -->
    <!-- ========================================== -->
    <div class="totais-card">
        <div class="row">
            <div class="col-md-3 total-item">
                <div class="rotulo">Total de Mensalidades</div>
                <div class="valor"><?= number_format($totais['quantidade'], 0, ',', '.') ?></div>
            </div>
            <div class="col-md-3 total-item">
                <div class="rotulo">Valor Total</div>
                <div class="valor">R$ <?= number_format($totais['valor_original'], 2, ',', '.') ?></div>
            </div>
            <div class="col-md-3 total-item">
                <div class="rotulo">Recebido</div>
                <div class="valor text-success">R$ <?= number_format($totais['recebido'], 2, ',', '.') ?></div>
            </div>
            <div class="col-md-3 total-item">
                <div class="rotulo">A Receber</div>
                <div class="valor text-danger">R$ <?= number_format($totais['a_receber'], 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-4 total-item">
                <div class="rotulo">Descontos</div>
                <div class="valor text-info">R$ <?= number_format($totais['valor_desconto'], 2, ',', '.') ?></div>
            </div>
            <div class="col-md-4 total-item">
                <div class="rotulo">Juros</div>
                <div class="valor text-warning">R$ <?= number_format($totais['valor_juros'], 2, ',', '.') ?></div>
            </div>
            <div class="col-md-4 total-item">
                <div class="rotulo">Multas</div>
                <div class="valor text-danger">R$ <?= number_format($totais['valor_multa'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TABELA DE DADOS -->
    <!-- ========================================== -->
    <div class="card">
        <div class="card-header">
            <div class="row">
                <div class="col-md-6">
                    <i class="fa fa-list"></i> Lista de Mensalidades
                    <span class="badge badge-secondary"><?= $total_registros ?> registros</span>
                </div>
                <div class="col-md-6 text-right">
                    <span class="text-muted">Página <?= $pagina ?> de <?= ceil($total_registros / $por_pagina) ?></span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-relatorio mb-0">
                    <thead>
                        <tr>
                            <th>
                                <a href="?<?= http_build_query(array_merge($_GET, ['ordenar_por' => 'matricula', 'ordenar_dir' => $ordenar_por == 'matricula' && $ordenar_dir == 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                    Matrícula
                                    <?php if($ordenar_por == 'matricula'): ?>
                                        <i class="fa fa-caret-<?= $ordenar_dir == 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?= http_build_query(array_merge($_GET, ['ordenar_por' => 'aluno_nome', 'ordenar_dir' => $ordenar_por == 'aluno_nome' && $ordenar_dir == 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                    Aluno
                                    <?php if($ordenar_por == 'aluno_nome'): ?>
                                        <i class="fa fa-caret-<?= $ordenar_dir == 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Curso</th>
                            <th>Turma</th>
                            <th>
                                <a href="?<?= http_build_query(array_merge($_GET, ['ordenar_por' => 'data_vencimento', 'ordenar_dir' => $ordenar_por == 'data_vencimento' && $ordenar_dir == 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                    Vencimento
                                    <?php if($ordenar_por == 'data_vencimento'): ?>
                                        <i class="fa fa-caret-<?= $ordenar_dir == 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Valor</th>
                            <th>Pago</th>
                            <th>Status</th>
                            <th class="text-center no-print">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($dados) > 0): ?>
                            <?php foreach($dados as $row): ?>
                                <tr>
                                    <td><strong><?= $row['matricula'] ?></strong></td>
                                    <td>
                                        <?= $row['aluno_nome'] ?>
                                        <br>
                                        <small class="text-muted"><?= $row['cpf'] ?></small>
                                    </td>
                                    <td><?= $row['curso_sigla'] ?? $row['curso_nome'] ?></td>
                                    <td><?= $row['turma_nome'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['data_vencimento'])) ?></td>
                                    <td><strong>R$ <?= number_format($row['valor_original'], 2, ',', '.') ?></strong></td>
                                    <td>
                                        <?php if($row['valor_pago'] > 0): ?>
                                            R$ <?= number_format($row['valor_pago'], 2, ',', '.') ?>
                                            <?php if($row['valor_desconto'] > 0): ?>
                                                <br><small class="text-success">(-R$ <?= number_format($row['valor_desconto'], 2, ',', '.') ?>)</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-<?= strtolower(str_replace(' ', '', $row['status_descricao'])) ?>">
                                            <?= $row['status_descricao'] ?>
                                        </span>
                                        <?php if($row['situacao'] == 'PENDENTE' && strtotime($row['data_vencimento']) < time()): ?>
                                            <br><small class="text-danger"><i class="fa fa-exclamation-circle"></i> Atrasado</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center no-print">
                                        <div class="btn-group btn-group-sm">
                                            <a href="../mensalidades/editar.php?id=<?= $row['id_mensalidade'] ?>" 
                                               class="btn btn-outline-primary btn-acoes" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <a href="../mensalidades/visualizar.php?id=<?= $row['id_mensalidade'] ?>" 
                                               class="btn btn-outline-info btn-acoes" title="Visualizar">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <?php if($row['situacao'] == 'PENDENTE'): ?>
                                                <a href="../mensalidades/baixar.php?id=<?= $row['id_mensalidade'] ?>" 
                                                   class="btn btn-outline-success btn-acoes" title="Baixar">
                                                    <i class="fa fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="../mensalidades/imprimir.php?id=<?= $row['id_mensalidade'] ?>" 
                                               class="btn btn-outline-secondary btn-acoes" title="Imprimir" target="_blank">
                                                <i class="fa fa-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fa fa-search fa-2x text-muted d-block"></i>
                                    <span class="text-muted">Nenhuma mensalidade encontrada com os filtros selecionados.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- ========================================== -->
        <!-- PAGINAÇÃO -->
        <!-- ========================================== -->
        <?php if($total_registros > $por_pagina): ?>
            <div class="card-footer">
                <nav aria-label="Paginação">
                    <ul class="pagination justify-content-center mb-0">
                        <?php 
                        $total_paginas = ceil($total_registros / $por_pagina);
                        $intervalo = 3;
                        $pagina_inicial = max(1, $pagina - $intervalo);
                        $pagina_final = min($total_paginas, $pagina + $intervalo);
                        ?>
                        
                        <?php if($pagina > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">
                                    <i class="fa fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">
                                    <i class="fa fa-angle-left"></i>
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
                                    <i class="fa fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>">
                                    <i class="fa fa-angle-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ========================================== -->
    <!-- RODAPÉ DO RELATÓRIO -->
    <!-- ========================================== -->
    <div class="row mt-3">
        <div class="col-md-6">
            <small class="text-muted">
                <i class="fa fa-calendar"></i> Gerado em: <?= date('d/m/Y H:i:s') ?>
                <br>
                <i class="fa fa-user"></i> Usuário: <?= $_SESSION['usuario_nome'] ?? 'Sistema' ?>
            </small>
        </div>
        <div class="col-md-6 text-right">
            <small class="text-muted">
                Softgest Sistemas &copy; <?= date('Y') ?> - Versão 1.0
            </small>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- SCRIPTS (caminhos absolutos) -->
<!-- ========================================== -->
<script src="C:/xampp/htdocs/softgest_web/assets/js/jquery-3.5.1.min.js"></script>
<script src="C:/xampp/htdocs/softgest_web/assets/js/bootstrap.bundle.min.js"></script>
<script src="C:/xampp/htdocs/softgest_web/assets/js/select2.min.js"></script>
<script src="C:/xampp/htdocs/softgest_web/assets/js/jquery.dataTables.min.js"></script>
<script src="C:/xampp/htdocs/softgest_web/assets/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    // Inicializa Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%',
        allowClear: true,
        placeholder: 'Selecione...'
    });
    
    // Auto-submit ao alterar selects principais (exceto busca)
    $('#formFiltros select').change(function() {
        if ($(this).attr('name') !== 'busca') {
            $(this).closest('form').submit();
        }
    });
    
    // Atualiza turmas quando curso muda
    $('select[name="id_curso"]').change(function() {
        var cursoId = $(this).val();
        var turmaSelect = $('select[name="id_turma"]');
        
        if (cursoId > 0) {
            $.ajax({
                url: 'C:/xampp/htdocs/softgest_web/modules/ajax/get_turmas.php',
                type: 'POST',
                data: { id_curso: cursoId },
                dataType: 'json',
                success: function(data) {
                    turmaSelect.empty();
                    turmaSelect.append('<option value="0">Todas</option>');
                    $.each(data, function(key, value) {
                        turmaSelect.append('<option value="' + key + '">' + value + '</option>');
                    });
                    turmaSelect.val(0).trigger('change');
                }
            });
        } else {
            turmaSelect.empty();
            turmaSelect.append('<option value="0">Todas</option>');
            turmaSelect.val(0).trigger('change');
        }
    });
    
    // Tecla Enter no campo de busca submete o formulário
    $('input[name="busca"]').keypress(function(e) {
        if (e.which == 13) {
            $(this).closest('form').submit();
        }
    });
});
</script>

<?php
// Fecha a conexão
if (isset($conn)) {
    $conn->close();
}

// Inclui o footer (com caminho absoluto)
$footer_path = 'C:/xampp/htdocs/softgest_web/includes/footer.php';
if (file_exists($footer_path)) {
    include_once $footer_path;
} else {
    // Fallback para caminho relativo
    $footer_fallback = '../../../../includes/footer.php';
    if (file_exists($footer_fallback)) {
        include_once $footer_fallback;
    }
}
?>
</body>
</html>