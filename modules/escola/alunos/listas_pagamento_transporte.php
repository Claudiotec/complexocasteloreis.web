<?php
// ============================================
// modules/escola/alunos/listas_pagamento_transporte.php - Lista Nominal de Alunos com Pagamentos
// ============================================

require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para VISUALIZAR
bloquearAcesso('visualizar');

// ============================================
// 1. PROCESSAR FILTROS
// ============================================
$filtros = [
    'classe' => $_GET['classe'] ?? '',
    'turma' => $_GET['turma'] ?? '',
    'periodo' => $_GET['periodo'] ?? '',
    'mes_referencia' => $_GET['mes_referencia'] ?? '',
    'status_pagamento' => $_GET['status_pagamento'] ?? '',
    'forma_pagamento' => $_GET['forma_pagamento'] ?? '',
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
    'busca_nome' => $_GET['busca_nome'] ?? ''
];

// ============================================
// 2. INICIALIZAR VARIÁVEIS
// ============================================
$pdo = null;
$alunos_agrupados = [];
$meses_disponiveis = [];
$classes_disponiveis = [];
$turmas_disponiveis = [];
$periodos_disponiveis = [];
$total_alunos = 0;
$total_turmas = 0;
$total_pagamentos = 0;
$emolumento_transporte_ids = [];

try {
    $pdo = conectarBanco();
    
    // ============================================
    // 3. BUSCAR OPÇÕES PARA FILTROS - DIRETO DOS PAGAMENTOS
    // ============================================
    
    // Buscar Meses disponíveis com pagamentos
    $stmt = $pdo->query("
        SELECT DISTINCT mes_referencia 
        FROM pagamentos 
        WHERE mes_referencia IS NOT NULL 
        AND mes_referencia != ''
        AND mes_referencia != '-'
        ORDER BY mes_referencia DESC
    ");
    $meses_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($meses_disponiveis)) {
        $meses_disponiveis = [date('F/Y')];
    }
    
    // Buscar Classes - DIRETO DOS ALUNOS
    $stmt = $pdo->query("
        SELECT DISTINCT Classe 
        FROM alunos 
        WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
        AND Classe IS NOT NULL AND Classe != ''
        ORDER BY Classe
    ");
    $classes_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Buscar Turmas - DIRETO DOS ALUNOS
    $stmt = $pdo->query("
        SELECT DISTINCT TURMA 
        FROM alunos 
        WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
        AND TURMA IS NOT NULL AND TURMA != ''
        ORDER BY TURMA
    ");
    $turmas_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Buscar Períodos - DIRETO DOS ALUNOS
    $stmt = $pdo->query("
        SELECT DISTINCT Periodo 
        FROM alunos 
        WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
        AND Periodo IS NOT NULL AND Periodo != ''
        ORDER BY Periodo
    ");
    $periodos_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // ============================================
    // 4. BUSCAR IDs DOS EMOLUMENTOS DE TRANSPORTE - DINÂMICO
    // ============================================
    try {
        // Tentar buscar pelo nome que começa com 'Transporte'
        $stmt = $pdo->query("
            SELECT id 
            FROM emolumentos 
            WHERE nome LIKE 'Transporte%' 
            AND status = 'ativo'
            ORDER BY id
        ");
        $emolumento_transporte_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Se não encontrou, tentar pela categoria
        if (empty($emolumento_transporte_ids)) {
            $stmt = $pdo->query("
                SELECT id 
                FROM emolumentos 
                WHERE categoria = 'transporte' 
                AND status = 'ativo'
                ORDER BY id
            ");
            $emolumento_transporte_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Se ainda não encontrou, tentar pela descrição
        if (empty($emolumento_transporte_ids)) {
            $stmt = $pdo->query("
                SELECT id 
                FROM emolumentos 
                WHERE descricao LIKE '%Transporte%' 
                AND status = 'ativo'
                ORDER BY id
            ");
            $emolumento_transporte_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Se não encontrou nenhum, usar fallback com os IDs manuais
        if (empty($emolumento_transporte_ids)) {
            $emolumento_transporte_ids = [19, 56, 66, 102, 104, 114];
            error_log("⚠️ Nenhum emolumento de transporte encontrado no banco. Usando fallback manual.");
        }
        
    } catch (Exception $e) {
        error_log("Erro ao buscar emolumentos de transporte: " . $e->getMessage());
        $emolumento_transporte_ids = [19, 56, 66, 102, 104, 114];
    }
    
    // ============================================
    // 5. CONSULTA: BUSCAR PAGAMENTOS DE TRANSPORTE
    // ============================================
    
    // Se não houver IDs de transporte, não faz consulta
    if (empty($emolumento_transporte_ids)) {
        $total_pagamentos = 0;
        $alunos_agrupados = [];
    } else {
        // Primeiro, buscar todos os pagamentos de transporte
        $sql_pagamentos = "
            SELECT 
                p.aluno_id AS pagamento_aluno_id,
                p.nome_aluno,
                p.emolumento_id,
                p.valor,
                p.data_pagamento,
                p.forma_pagamento,
                p.status,
                p.mes_referencia
            FROM pagamentos p
            WHERE p.emolumento_id IN (" . implode(',', array_map('intval', $emolumento_transporte_ids)) . ")
        ";
        
        // Aplicar filtros de data e status nos pagamentos
        $where_pag = [];
        $params_pag = [];
        
        if (!empty($filtros['mes_referencia']) && $filtros['mes_referencia'] != 'todos') {
            $where_pag[] = "p.mes_referencia = :mes_referencia";
            $params_pag[':mes_referencia'] = $filtros['mes_referencia'];
        }
        
        if (!empty($filtros['status_pagamento'])) {
            $where_pag[] = "p.status = :status_pagamento";
            $params_pag[':status_pagamento'] = $filtros['status_pagamento'];
        }
        
        if (!empty($filtros['forma_pagamento'])) {
            $where_pag[] = "p.forma_pagamento = :forma_pagamento";
            $params_pag[':forma_pagamento'] = $filtros['forma_pagamento'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $where_pag[] = "p.data_pagamento >= :data_inicio";
            $params_pag[':data_inicio'] = $filtros['data_inicio'];
        }
        if (!empty($filtros['data_fim'])) {
            $where_pag[] = "p.data_pagamento <= :data_fim";
            $params_pag[':data_fim'] = $filtros['data_fim'];
        }
        
        if (!empty($where_pag)) {
            $sql_pagamentos .= " AND " . implode(' AND ', $where_pag);
        }
        
        $sql_pagamentos .= " ORDER BY p.data_pagamento DESC";
        
        $stmt = $pdo->prepare($sql_pagamentos);
        foreach ($params_pag as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $pagamentos = $stmt->fetchAll();
        
        $total_pagamentos = count($pagamentos);
        
        // ============================================
        // 6. PARA CADA PAGAMENTO, BUSCAR O ALUNO COM TODOS OS DADOS
        // ============================================
        $alunos_encontrados = [];
        $alunos_ids_processados = [];
        
        foreach ($pagamentos as $pag) {
            $nome_pagamento = trim($pag['nome_aluno'] ?? '');
            $pagamento_aluno_id = $pag['pagamento_aluno_id'];
            
            // Se já processou esse aluno, pula
            if (in_array($pagamento_aluno_id, $alunos_ids_processados)) {
                continue;
            }
            $alunos_ids_processados[] = $pagamento_aluno_id;
            
            // Buscar aluno pelo ID primeiro com TODOS OS DADOS
            $sql_aluno = "
                SELECT 
                    a.id AS aluno_id,
                    a.nome,
                    a.Sexo,
                    a.dia,
                    a.mes,
                    a.Ano,
                    a.Classe,
                    a.TURMA AS turma,
                    a.SALA AS sala,
                    a.Periodo,
                    a.Situacao_Cadastro,
                    a.Morada,
                    a.Contacto4,
                    a.Contacto_Mae,
                    YEAR(CURRENT_DATE) - a.Ano - (DATE_FORMAT(CURRENT_DATE, '%m%d') < CONCAT(a.mes, LPAD(a.dia, 2, '0'))) AS idade_atual
                FROM alunos a
                WHERE a.id = :aluno_id
                AND (a.Situacao_Cadastro = 'Matrícula' OR a.Situacao_Cadastro = 'Confirmação')
            ";
            
            $stmt = $pdo->prepare($sql_aluno);
            $stmt->bindValue(':aluno_id', $pagamento_aluno_id);
            $stmt->execute();
            $aluno = $stmt->fetch();
            
            // Se não encontrou pelo ID, buscar pelo NOME com TODOS OS DADOS
            if (!$aluno && !empty($nome_pagamento)) {
                // Tentar buscar pelo nome com LIKE
                $sql_aluno_nome = "
                    SELECT 
                        a.id AS aluno_id,
                        a.nome,
                        a.Sexo,
                        a.dia,
                        a.mes,
                        a.Ano,
                        a.Classe,
                        a.TURMA AS turma,
                        a.SALA AS sala,
                        a.Periodo,
                        a.Situacao_Cadastro,
                        a.Morada,
                        a.Contacto4,
                        a.Contacto_Mae,
                        YEAR(CURRENT_DATE) - a.Ano - (DATE_FORMAT(CURRENT_DATE, '%m%d') < CONCAT(a.mes, LPAD(a.dia, 2, '0'))) AS idade_atual
                    FROM alunos a
                    WHERE a.nome LIKE :nome
                    AND (a.Situacao_Cadastro = 'Matrícula' OR a.Situacao_Cadastro = 'Confirmação')
                    LIMIT 1
                ";
                
                $stmt = $pdo->prepare($sql_aluno_nome);
                $stmt->bindValue(':nome', '%' . $nome_pagamento . '%');
                $stmt->execute();
                $aluno = $stmt->fetch();
                
                // Se ainda não encontrou, tentar com o nome exato
                if (!$aluno) {
                    $sql_aluno_nome_exato = "
                        SELECT 
                            a.id AS aluno_id,
                            a.nome,
                            a.Sexo,
                            a.dia,
                            a.mes,
                            a.Ano,
                            a.Classe,
                            a.TURMA AS turma,
                            a.SALA AS sala,
                            a.Periodo,
                            a.Situacao_Cadastro,
                            a.Morada,
                            a.Contacto4,
                            a.Contacto_Mae,
                            YEAR(CURRENT_DATE) - a.Ano - (DATE_FORMAT(CURRENT_DATE, '%m%d') < CONCAT(a.mes, LPAD(a.dia, 2, '0'))) AS idade_atual
                        FROM alunos a
                        WHERE a.nome = :nome
                        AND (a.Situacao_Cadastro = 'Matrícula' OR a.Situacao_Cadastro = 'Confirmação')
                        LIMIT 1
                    ";
                    
                    $stmt = $pdo->prepare($sql_aluno_nome_exato);
                    $stmt->bindValue(':nome', $nome_pagamento);
                    $stmt->execute();
                    $aluno = $stmt->fetch();
                }
            }
            
            // Se encontrou o aluno, adiciona à lista
            if ($aluno) {
                // Aplicar filtros de Classe, Turma, Período e Nome
                $classe_match = empty($filtros['classe']) || $aluno['Classe'] == $filtros['classe'];
                $turma_match = empty($filtros['turma']) || $aluno['turma'] == $filtros['turma'];
                $periodo_match = empty($filtros['periodo']) || strtolower($aluno['Periodo']) == strtolower($filtros['periodo']);
                $nome_match = empty($filtros['busca_nome']) || stripos($aluno['nome'], $filtros['busca_nome']) !== false;
                
                if ($classe_match && $turma_match && $periodo_match && $nome_match) {
                    $alunos_encontrados[] = $aluno;
                }
            }
        }
        
        // ============================================
        // 7. AGRUPAR ALUNOS POR CLASSE E TURMA
        // ============================================
        $alunos_agrupados = [];
        foreach ($alunos_encontrados as $aluno) {
            $classe = $aluno['Classe'] ?? 'Sem Classe';
            $turma = $aluno['turma'] ?? 'Sem Turma';
            $sala = $aluno['sala'] ?? 'Sem Sala';
            $key = $classe . '|' . $turma . '|' . $sala;
            
            if (!isset($alunos_agrupados[$key])) {
                $alunos_agrupados[$key] = [
                    'classe' => $classe,
                    'turma' => $turma,
                    'sala' => $sala,
                    'periodo' => $aluno['Periodo'] ?? 'Manhã',
                    'alunos' => []
                ];
            }
            
            // Verificar se o aluno já está na lista (evitar duplicados)
            $existe = false;
            foreach ($alunos_agrupados[$key]['alunos'] as $a) {
                if ($a['aluno_id'] == $aluno['aluno_id']) {
                    $existe = true;
                    break;
                }
            }
            
            if (!$existe) {
                $alunos_agrupados[$key]['alunos'][] = $aluno;
                $total_alunos++;
            }
        }
        
        // ============================================
        // 7.1 ORDENAR ALUNOS EM ORDEM ALFABÉTICA DENTRO DE CADA TURMA
        // ============================================
        foreach ($alunos_agrupados as &$grupo) {
            usort($grupo['alunos'], function($a, $b) {
                return strcmp(
                    iconv('UTF-8', 'ASCII//TRANSLIT', $a['nome']),
                    iconv('UTF-8', 'ASCII//TRANSLIT', $b['nome'])
                );
            });
        }
        unset($grupo);
        
        $total_turmas = count($alunos_agrupados);
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar alunos com pagamentos: " . $e->getMessage());
    $alunos_agrupados = [];
    if (empty($meses_disponiveis)) {
        $meses_disponiveis = [date('F/Y')];
    }
    // Em caso de erro, usar fallback
    if (empty($emolumento_transporte_ids)) {
        $emolumento_transporte_ids = [19, 56, 66, 102, 104, 114];
    }
}

// ============================================
// 8. INCLUIR HEADER
// ============================================
include '../includes/header_escola.php';
?>

<style>
    /* ============================================ */
    /* ESTILOS DA PÁGINA PRINCIPAL - FONTE 14px    */
    /* ============================================ */
    * {
        font-size: 14px;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        font-size: 26px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .page-header .subtitle {
        color: #94a3b8;
        font-size: 15px;
        margin: 2px 0 0;
    }
    
    .btn {
        padding: 10px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
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
        box-shadow: 0 4px 15px rgba(201,168,76,0.3);
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
    }
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .btn-info:hover {
        background: #2980b9;
    }
    
    .btn-warning {
        background: #f39c12;
        color: #fff;
    }
    
    .btn-warning:hover {
        background: #d68910;
    }
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
    }
    
    .btn-sm {
        padding: 6px 14px;
        font-size: 13px;
        border-radius: 6px;
    }
    
    .nav-alunos {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 25px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .nav-alunos a {
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
        color: #4a5568;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .nav-alunos a:hover {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
        transform: translateY(-2px);
    }
    
    .nav-alunos a.active {
        background: #c9a84c;
        color: #1a2332;
        border-color: #c9a84c;
    }
    
    .filtros-container {
        background: white;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        margin-bottom: 25px;
    }
    
    .filtros-container h3 {
        font-size: 18px;
        color: #1a2332;
        margin: 0 0 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filtros-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .filtros-grid .form-group {
        display: flex;
        flex-direction: column;
    }
    
    .filtros-grid .form-group label {
        font-size: 14px;
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 4px;
    }
    
    .filtros-grid .form-group input,
    .filtros-grid .form-group select {
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        background: #f8fafc;
    }
    
    .filtros-grid .form-group input:focus,
    .filtros-grid .form-group select:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    
    .filtros-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        font-size: 32px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .stat-card .label {
        font-size: 14px;
        color: #94a3b8;
        margin: 3px 0 0;
    }
    
    .stat-card .icon {
        font-size: 28px;
        display: block;
        margin-bottom: 5px;
    }
    
    .stat-card.alunos { border-left-color: #3498db; }
    .stat-card.turmas { border-left-color: #f39c12; }
    .stat-card.pagamentos { border-left-color: #2ecc71; }
    
    .listas-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(800px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .lista-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        transition: all 0.3s;
        overflow-x: auto;
    }
    
    .lista-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    }
    
    .lista-card .header-lista {
        border-bottom: 2px solid #c9a84c;
        padding-bottom: 12px;
        margin-bottom: 15px;
    }
    
    .lista-card .header-lista .classe {
        font-size: 20px;
        font-weight: 700;
        color: #1a2332;
    }
    
    .lista-card .header-lista .turma {
        font-size: 16px;
        color: #c9a84c;
        font-weight: 600;
    }
    
    .lista-card .header-lista .info {
        font-size: 14px;
        color: #94a3b8;
        margin-top: 2px;
    }
    
    /* Tabela de alunos - FONTE 14px */
    .lista-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    
    .lista-table thead th {
        background: #f1f5f9;
        color: #1a2332;
        font-weight: 700;
        padding: 10px 12px;
        text-align: left;
        border-bottom: 2px solid #c9a84c;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    
    .lista-table tbody td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        font-size: 14px;
    }
    
    .lista-table tbody tr:hover {
        background: #f8fafc;
    }
    
    .lista-table tbody tr:nth-child(even) {
        background: #fafcff;
    }
    
    .badge-sexo {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
    }
    
    .badge-m {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .badge-f {
        background: #fce7f3;
        color: #9d174d;
    }
    
    .contacto-pai {
        color: #1e40af;
        font-weight: 600;
        font-size: 14px;
    }
    
    .contacto-mae {
        color: #9d174d;
        font-weight: 600;
        font-size: 14px;
    }
    
    .data-nasc {
        color: #64748b;
        font-size: 14px;
        text-align: center;
        white-space: nowrap;
    }
    
    .idade-aluno {
        color: #c9a84c;
        font-size: 14px;
        font-weight: 700;
        text-align: center;
        white-space: nowrap;
    }
    
    .morada-aluno {
        color: #64748b;
        font-size: 14px;
        max-width: 180px;
        word-break: break-word;
    }
    
    .total {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #eef2f7;
        text-align: center;
        font-size: 15px;
        color: #4a5568;
    }
    
    .total strong {
        color: #c9a84c;
        font-size: 16px;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
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
        margin: 0 0 5px;
    }
    
    .empty-state .debug-info {
        margin-top: 10px;
        padding: 15px;
        background: #f8fafc;
        border-radius: 8px;
        font-size: 14px;
        text-align: left;
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .acoes-rapidas {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 20px;
        justify-content: center;
        padding: 15px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #eef2f7;
    }
    
    .acoes-rapidas span {
        font-size: 14px;
    }
    
    .download-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    
    .download-actions .btn-sm {
        font-size: 13px;
        padding: 6px 14px;
    }
    
    /* ============================================ */
    /* RESPONSIVIDADE                              */
    /* ============================================ */
    @media (max-width: 992px) {
        .listas-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-alunos {
            flex-direction: column;
            align-items: stretch;
        }
        .nav-alunos a {
            text-align: center;
            justify-content: center;
        }
        .filtros-grid {
            grid-template-columns: 1fr;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .listas-grid {
            grid-template-columns: 1fr;
        }
        .filtros-actions {
            justify-content: center;
        }
        .acoes-rapidas {
            flex-direction: column;
            align-items: stretch;
        }
        .acoes-rapidas .btn {
            justify-content: center;
        }
        .lista-table {
            font-size: 13px;
        }
        .lista-table thead th,
        .lista-table tbody td {
            padding: 8px 10px;
            font-size: 13px;
        }
        .morada-aluno {
            max-width: 100px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .lista-table {
            font-size: 12px;
        }
        .lista-table thead th,
        .lista-table tbody td {
            padding: 6px 8px;
            font-size: 12px;
        }
        .badge-sexo {
            font-size: 11px;
            padding: 1px 8px;
        }
        .lista-card {
            padding: 12px;
        }
        .lista-card .header-lista .classe {
            font-size: 17px;
        }
    }
    
    /* ============================================ */
    /* MODAL DE IMPRESSÃO                          */
    /* ============================================ */
    .print-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
        z-index: 99999;
        overflow-y: auto;
        padding: 20px;
    }
    
    .print-modal.active {
        display: block;
    }
    
    .print-modal .modal-content {
        max-width: 210mm;
        margin: 20px auto;
        background: white;
        padding: 10mm 12mm;
        border-radius: 8px;
        box-shadow: 0 10px 60px rgba(0,0,0,0.3);
        position: relative;
    }
    
    .print-modal .modal-close {
        position: sticky;
        top: 0;
        float: right;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        font-size: 20px;
        cursor: pointer;
        z-index: 10;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .print-modal .modal-close:hover {
        background: #c0392b;
    }
    
    .print-modal .modal-actions {
        text-align: center;
        padding: 15px 0;
        border-bottom: 2px solid #eee;
        margin-bottom: 20px;
        display: flex;
        justify-content: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .print-modal .modal-actions .btn {
        font-size: 14px;
        padding: 10px 24px;
    }
    
    /* ============================================ */
    /* ESTILOS PARA IMPRESSÃO A4 (DENTRO DO MODAL) */
    /* ============================================ */
    .print-content {
        font-family: 'Times New Roman', Times, serif;
        font-size: 12px;
        line-height: 1.4;
        color: #1a1a1a;
    }
    
    .print-content .header-print {
        text-align: center;
        border-bottom: 3px double #003366;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    
    .print-content .header-print h1 {
        font-size: 22px;
        color: #003366;
        letter-spacing: 1px;
        margin: 0;
    }
    
    .print-content .header-print h2 {
        font-size: 16px;
        font-weight: normal;
        color: #333;
        margin: 5px 0;
    }
    
    .print-content .header-print .info-extra {
        font-size: 12px;
        color: #555;
        margin-top: 4px;
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .print-content .header-print .info-extra span {
        background: #f5f5f5;
        padding: 2px 12px;
        border-radius: 3px;
    }
    
    .print-content .resumo-print {
        display: flex;
        justify-content: space-around;
        background: #f8f8f8;
        padding: 8px 12px;
        border-radius: 4px;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        font-size: 13px;
    }
    
    .print-content .resumo-print strong {
        color: #003366;
        font-size: 16px;
    }
    
    .print-content .turma-print {
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 10px 12px;
        margin-bottom: 15px;
        page-break-inside: avoid;
        background: white;
    }
    
    .print-content .turma-print .titulo-turma {
        background: #003366;
        color: white;
        padding: 6px 10px;
        margin: -10px -12px 10px -12px;
        border-radius: 4px 4px 0 0;
        font-size: 15px;
        font-weight: bold;
        display: flex;
        justify-content: space-between;
    }
    
    .print-content .turma-print .titulo-turma .sala-periodo {
        font-weight: normal;
        font-size: 13px;
        opacity: 0.9;
    }
    
    .print-content table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }
    
    .print-content table th {
        background: #e6e6e6;
        text-align: left;
        padding: 4px 6px;
        border-bottom: 2px solid #003366;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .print-content table td {
        padding: 4px 6px;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
        font-size: 11px;
    }
    
    .print-content table tr:nth-child(even) {
        background: #fafafa;
    }
    
    .print-content .badge-sexo {
        display: inline-block;
        padding: 0 6px;
        border-radius: 6px;
        font-size: 9px;
        font-weight: bold;
    }
    
    .print-content .badge-m {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .print-content .badge-f {
        background: #fce7f3;
        color: #9d174d;
    }
    
    .print-content .total-alunos {
        text-align: right;
        font-weight: bold;
        font-size: 12px;
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px dashed #ccc;
    }
    
    .print-content .footer-print {
        text-align: center;
        font-size: 10px;
        color: #888;
        border-top: 1px solid #ddd;
        padding-top: 8px;
        margin-top: 8px;
    }
    
    @media print {
        .no-print {
            display: none !important;
        }
        .print-modal {
            display: block !important;
            background: white !important;
            padding: 0 !important;
            position: static !important;
        }
        .print-modal .modal-content {
            box-shadow: none !important;
            padding: 6mm 8mm !important;
            margin: 0 !important;
            border-radius: 0 !important;
        }
        .print-modal .modal-close {
            display: none !important;
        }
        .print-modal .modal-actions {
            display: none !important;
        }
        .print-content .turma-print {
            page-break-inside: avoid;
            border-color: #999;
        }
        .print-content .resumo-print {
            background: #f5f5f5;
            border: 1px solid #aaa;
        }
        .print-content .footer-print {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 4px 8mm;
        }
        .print-content table td {
            font-size: 10px;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📋 Lista Nominal - Alunos com Pagamentos de Transporte</h1>
        <p class="subtitle">Alunos com registro de pagamentos de transporte agrupados por classe e turma</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="nav-alunos">
    <a href="index.php">📋 Lista de Alunos</a>
    <a href="add.php">➕ Cadastrar Aluno</a>
    <a href="reconfirmar.php">🔄 Reconfirmação</a>
    <a href="consulta.php">🔍 Consulta</a>
    <a href="relatorio.php">📈 Relatório</a>
    <a href="relatorio_idade.php">📊 Relatório por Idade</a>
    <a href="listas_nominais.php">📋 Listas Nominais</a>
    <a href="listas_pagamento_transporte.php" class="active">🚌 Pagamentos Transporte</a>
</div>

<div class="filtros-container">
    <h3>🔍 Filtros de Busca</h3>
    <form method="GET" action="">
        <div class="filtros-grid">
            <div class="form-group">
                <label for="classe">Classe</label>
                <select name="classe" id="classe">
                    <option value="">Todas</option>
                    <?php foreach ($classes_disponiveis as $classe): ?>
                        <option value="<?= htmlspecialchars($classe) ?>" <?= $filtros['classe'] == $classe ? 'selected' : '' ?>>
                            <?= htmlspecialchars($classe) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="turma">Turma</label>
                <select name="turma" id="turma">
                    <option value="">Todas</option>
                    <?php foreach ($turmas_disponiveis as $turma): ?>
                        <option value="<?= htmlspecialchars($turma) ?>" <?= $filtros['turma'] == $turma ? 'selected' : '' ?>>
                            <?= htmlspecialchars($turma) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="periodo">Período</label>
                <select name="periodo" id="periodo">
                    <option value="">Todos</option>
                    <?php foreach ($periodos_disponiveis as $periodo): ?>
                        <option value="<?= htmlspecialchars($periodo) ?>" <?= $filtros['periodo'] == $periodo ? 'selected' : '' ?>>
                            <?= htmlspecialchars($periodo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="mes_referencia">Mês Referência</label>
                <select name="mes_referencia" id="mes_referencia">
                    <option value="todos">Todos os meses</option>
                    <?php foreach ($meses_disponiveis as $mes): ?>
                        <option value="<?= htmlspecialchars($mes) ?>" <?= $filtros['mes_referencia'] == $mes ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mes) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="status_pagamento">Status</label>
                <select name="status_pagamento" id="status_pagamento">
                    <option value="">Todos</option>
                    <option value="confirmado" <?= $filtros['status_pagamento'] == 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                    <option value="pendente" <?= $filtros['status_pagamento'] == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="cancelado" <?= $filtros['status_pagamento'] == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="forma_pagamento">Forma de Pagamento</label>
                <select name="forma_pagamento" id="forma_pagamento">
                    <option value="">Todas</option>
                    <option value="dinheiro" <?= $filtros['forma_pagamento'] == 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                    <option value="cartao" <?= $filtros['forma_pagamento'] == 'cartao' ? 'selected' : '' ?>>Cartão</option>
                    <option value="transferencia" <?= $filtros['forma_pagamento'] == 'transferencia' ? 'selected' : '' ?>>Transferência</option>
                    <option value="pix" <?= $filtros['forma_pagamento'] == 'pix' ? 'selected' : '' ?>>PIX</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="data_inicio">Data Início</label>
                <input type="date" name="data_inicio" id="data_inicio" value="<?= htmlspecialchars($filtros['data_inicio']) ?>">
            </div>
            
            <div class="form-group">
                <label for="data_fim">Data Fim</label>
                <input type="date" name="data_fim" id="data_fim" value="<?= htmlspecialchars($filtros['data_fim']) ?>">
            </div>
            
            <div class="form-group">
                <label for="busca_nome">Buscar por Nome</label>
                <input type="text" name="busca_nome" id="busca_nome" placeholder="Digite o nome do aluno..." value="<?= htmlspecialchars($filtros['busca_nome']) ?>">
            </div>
        </div>
        
        <div class="filtros-actions">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="listas_pagamento_transporte.php" class="btn btn-secondary">🔄 Limpar Filtros</a>
        </div>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card alunos">
        <span class="icon">👨‍🎓</span>
        <div class="number"><?= $total_alunos ?></div>
        <div class="label">Alunos com Pagamento</div>
    </div>
    <div class="stat-card turmas">
        <span class="icon">🏫</span>
        <div class="number"><?= $total_turmas ?></div>
        <div class="label">Turmas</div>
    </div>
    <div class="stat-card pagamentos">
        <span class="icon">💰</span>
        <div class="number"><?= $total_pagamentos ?></div>
        <div class="label">Total de Pagamentos</div>
    </div>
</div>

<div class="listas-grid">
    <?php if (count($alunos_agrupados) > 0): ?>
        <?php foreach($alunos_agrupados as $grupo): 
            $classe = $grupo['classe'];
            $turma = $grupo['turma'];
            $sala = $grupo['sala'];
            $periodo = $grupo['periodo'];
            $alunos_lista = $grupo['alunos'];
            $total = count($alunos_lista);
        ?>
        <div class="lista-card" data-classe="<?= htmlspecialchars($classe) ?>" data-turma="<?= htmlspecialchars($turma) ?>">
            <div class="header-lista">
                <div class="classe">📚 <?= htmlspecialchars($classe) ?></div>
                <div class="turma">Turma <?= htmlspecialchars($turma) ?></div>
                <div class="info">
                    Sala: <?= htmlspecialchars($sala) ?> | Período: <?= htmlspecialchars($periodo) ?>
                </div>
            </div>
            
            <!-- Tabela de alunos -->
            <table class="lista-table">
                <thead>
                    <tr>
                        <th style="width:35px;">Nº</th>
                        <th style="min-width:180px;">Nome</th>
                        <th style="width:50px;">Sexo</th>
                        <th style="min-width:150px;">Morada</th>
                        <th style="width:120px;">Contacto Pai</th>
                        <th style="width:120px;">Contacto Mãe</th>
                        <th style="width:110px;">Nascimento</th>
                        <th style="width:55px;">Idade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $num = 1;
                    foreach($alunos_lista as $aluno): 
                        $morada = $aluno['Morada'] ?? '';
                        $contacto_pai = $aluno['Contacto4'] ?? '';
                        $contacto_mae = $aluno['Contacto_Mae'] ?? '';
                        $dia = $aluno['dia'] ?? 0;
                        $mes = $aluno['mes'] ?? 0;
                        $ano = $aluno['Ano'] ?? 0;
                        $data_nasc = ($dia > 0 && $mes > 0 && $ano > 0) ? sprintf("%02d/%02d/%04d", $dia, $mes, $ano) : '-';
                        $idade = $aluno['idade_atual'] ?? '-';
                    ?>
                    <tr>
                        <td style="text-align:center;font-weight:700;color:#94a3b8;"><?= $num ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($aluno['nome']) ?></td>
                        <td>
                            <span class="badge-sexo badge-<?= strtolower($aluno['Sexo'] ?? 'm') ?>">
                                <?= $aluno['Sexo'] ?? 'M' ?>
                            </span>
                        </td>
                        <td class="morada-aluno"><?= !empty($morada) ? htmlspecialchars($morada) : '—' ?></td>
                        <td class="contacto-pai"><?= !empty($contacto_pai) ? htmlspecialchars($contacto_pai) : '—' ?></td>
                        <td class="contacto-mae"><?= !empty($contacto_mae) ? htmlspecialchars($contacto_mae) : '—' ?></td>
                        <td class="data-nasc"><?= $data_nasc ?></td>
                        <td class="idade-aluno"><?= $idade ?> anos</td>
                    </tr>
                    <?php $num++; endforeach; ?>
                </tbody>
            </table>
            
            <div class="total">
                Total: <strong><?= $total ?></strong> alunos
            </div>
            
            <div class="download-actions">
                <button onclick="abrirImpressao('<?= htmlspecialchars($classe) ?>', '<?= htmlspecialchars($turma) ?>')" class="btn btn-sm btn-warning">🖨️ Imprimir</button>
                <a href="gerar_lista_transporte.php?classe=<?= urlencode($classe) ?>&turma=<?= urlencode($turma) ?>&<?= http_build_query($filtros) ?>" class="btn btn-sm btn-info" target="_blank">🌐 HTML</a>
                <a href="exportar_lista_transporte_excel.php?classe=<?= urlencode($classe) ?>&turma=<?= urlencode($turma) ?>&<?= http_build_query($filtros) ?>" class="btn btn-sm btn-success">📊 Excel</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="grid-column: 1 / -1;">
            <div class="empty-state">
                <span class="icon">🔍</span>
                <h3>Nenhum aluno encontrado com pagamentos de transporte</h3>
                <p>Não há alunos com pagamentos de transporte para os filtros selecionados.</p>
                <div class="debug-info">
                    <p><strong>📊 Resumo:</strong></p>
                    <p><strong>Total de pagamentos de transporte:</strong> <?= $total_pagamentos ?></p>
                    <p><strong>IDs de emolumentos configurados:</strong> <?= implode(', ', $emolumento_transporte_ids ?? [19, 56, 66, 102, 104, 114]) ?></p>
                    <hr style="border: 1px solid #e2e8f0; margin: 10px 0;">
                    <p><strong>⚠️ Possíveis causas:</strong></p>
                    <ul style="text-align: left; margin: 5px 0; padding-left: 20px;">
                        <li>Os IDs dos alunos na tabela <strong>pagamentos</strong> não correspondem aos IDs na tabela <strong>alunos</strong></li>
                        <li>Os alunos podem estar com status diferente de "Matrícula" ou "Confirmação"</li>
                        <li>Os filtros de data podem estar muito restritos</li>
                    </ul>
                    <p style="margin-top:10px;color:#c9a84c;font-weight:bold;">
                        💡 Tente selecionar "Todos os meses" e remover os filtros de data
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-weight: 500;">⚡ Ações:</span>
    <button onclick="abrirImpressaoTodas()" class="btn btn-primary">🖨️ Imprimir Todas</button>
    <a href="exportar_todas_listas_transporte.php?<?= http_build_query($filtros) ?>" class="btn btn-success">📊 Exportar Todas Excel</a>
    <a href="gerar_todas_listas_transporte.php?<?= http_build_query($filtros) ?>" class="btn btn-info">🌐 Gerar Todas HTML</a>
</div>

<!-- ============================================ -->
<!-- MODAL DE IMPRESSÃO                          -->
<!-- ============================================ -->
<div id="printModal" class="print-modal">
    <div class="modal-content">
        <button class="modal-close" onclick="fecharImpressao()">✕</button>
        
        <div class="modal-actions no-print">
            <button onclick="imprimirConteudo()" class="btn btn-primary">🖨️ Imprimir</button>
            <button onclick="imprimirConteudo()" class="btn btn-danger">📄 Salvar PDF</button>
            <button onclick="fecharImpressao()" class="btn btn-secondary">✖ Fechar</button>
        </div>
        
        <div id="printContent" class="print-content">
            <!-- Conteúdo será inserido via JavaScript -->
        </div>
    </div>
</div>

<script>
// ============================================
// FUNÇÕES DE IMPRESSÃO
// ============================================

function abrirImpressao(classe, turma) {
    // Buscar os dados da turma específica
    var cards = document.querySelectorAll('.lista-card');
    var dados = null;
    
    cards.forEach(function(card) {
        var cardClasse = card.getAttribute('data-classe');
        var cardTurma = card.getAttribute('data-turma');
        if (cardClasse === classe && cardTurma === turma) {
            dados = card.cloneNode(true);
            // Remover botões de ação
            var actions = dados.querySelector('.download-actions');
            if (actions) actions.remove();
        }
    });
    
    if (!dados) {
        alert('Dados não encontrados para impressão.');
        return;
    }
    
    // Construir HTML para impressão
    var html = gerarHTMLImpressao([dados]);
    document.getElementById('printContent').innerHTML = html;
    document.getElementById('printModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function abrirImpressaoTodas() {
    var cards = document.querySelectorAll('.lista-card');
    var dados = [];
    
    cards.forEach(function(card) {
        var clone = card.cloneNode(true);
        var actions = clone.querySelector('.download-actions');
        if (actions) actions.remove();
        dados.push(clone);
    });
    
    if (dados.length === 0) {
        alert('Nenhum dado para imprimir.');
        return;
    }
    
    var html = gerarHTMLImpressao(dados);
    document.getElementById('printContent').innerHTML = html;
    document.getElementById('printModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function gerarHTMLImpressao(cards) {
    var html = '';
    
    // Cabeçalho
    html += `
        <div class="header-print">
            <h1>COMPLEXO ESCOLAR CASTELO REIS</h1>
            <h2>📋 Lista Nominal - Alunos com Pagamentos de Transporte</h2>
            <div class="info-extra">
                <span>📅 Data: <?= date('d/m/Y') ?></span>
                <span>⏰ Hora: <?= date('H:i:s') ?></span>
                <?php if (!empty($filtros['mes_referencia']) && $filtros['mes_referencia'] != 'todos'): ?>
                <span>📆 Mês: <?= htmlspecialchars($filtros['mes_referencia']) ?></span>
                <?php endif; ?>
                <?php if (!empty($filtros['classe'])): ?>
                <span>📚 Classe: <?= htmlspecialchars($filtros['classe']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    `;
    
    // Resumo
    var totalAlunos = 0;
    cards.forEach(function(card) {
        var totalText = card.querySelector('.total strong');
        if (totalText) {
            totalAlunos += parseInt(totalText.textContent) || 0;
        }
    });
    
    html += `
        <div class="resumo-print">
            <div>👨‍🎓 <strong>${totalAlunos}</strong> Alunos</div>
            <div>🏫 <strong>${cards.length}</strong> Turmas</div>
            <div>💰 <strong><?= $total_pagamentos ?></strong> Pagamentos</div>
        </div>
    `;
    
    // Turmas
    cards.forEach(function(card) {
        var classe = card.querySelector('.classe')?.textContent?.trim() || 'Sem Classe';
        var turma = card.querySelector('.turma')?.textContent?.trim() || 'Sem Turma';
        var info = card.querySelector('.info')?.textContent?.trim() || '';
        var table = card.querySelector('.lista-table');
        var rows = table ? table.querySelectorAll('tbody tr') : [];
        
        html += `
            <div class="turma-print">
                <div class="titulo-turma">
                    <span>${classe} - Turma ${turma}</span>
                    <span class="sala-periodo">${info}</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:25px;">Nº</th>
                            <th style="width:22%;">Nome do Aluno</th>
                            <th style="width:30px;">Sexo</th>
                            <th style="width:18%;">Morada</th>
                            <th style="width:13%;">Contato Pai</th>
                            <th style="width:13%;">Contato Mãe</th>
                            <th style="width:75px;">Nascimento</th>
                            <th style="width:40px;">Idade</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        var num = 1;
        rows.forEach(function(row) {
            var cells = row.querySelectorAll('td');
            if (cells.length >= 8) {
                var nome = cells[1]?.textContent?.trim() || '';
                var sexo = cells[2]?.textContent?.trim() || 'M';
                var morada = cells[3]?.textContent?.trim() || '';
                if (morada === '—') morada = '';
                var contatoPai = cells[4]?.textContent?.trim() || '';
                if (contatoPai === '—') contatoPai = '';
                var contatoMae = cells[5]?.textContent?.trim() || '';
                if (contatoMae === '—') contatoMae = '';
                var dataNasc = cells[6]?.textContent?.trim() || '-';
                var idade = cells[7]?.textContent?.trim() || '-';
                
                html += `
                    <tr>
                        <td style="text-align:center;">${num}</td>
                        <td>${nome}</td>
                        <td><span class="badge-sexo badge-${sexo.toLowerCase()}">${sexo}</span></td>
                        <td style="font-size:10px;color:#555;word-break:break-word;">${morada}</td>
                        <td style="font-size:10px;color:#1e40af;font-weight:bold;">${contatoPai}</td>
                        <td style="font-size:10px;color:#9d174d;font-weight:bold;">${contatoMae}</td>
                        <td style="text-align:center;">${dataNasc}</td>
                        <td style="text-align:center;font-weight:bold;color:#003366;">${idade}</td>
                    </tr>
                `;
                num++;
            }
        });
        
        var total = num - 1;
        html += `
                    </tbody>
                </table>
                <div class="total-alunos">Total: ${total} alunos</div>
            </div>
        `;
    });
    
    // Rodapé
    html += `
        <div class="footer-print">
            Módulo Gestão Escolar © <?= date('Y') ?> COMPLEXO ESCOLAR CASTELO REIS v1.0 | Sistema Online
        </div>
    `;
    
    return html;
}

function imprimirConteudo() {
    // Abrir uma nova janela apenas com o conteúdo para impressão
    var printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write('<!DOCTYPE html><html><head><title>Imprimir Lista</title>');
    printWindow.document.write('<style>');
    printWindow.document.write(`
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 12px; 
            margin: 8mm; 
            padding: 0;
        }
        .header-print {
            text-align: center;
            border-bottom: 3px double #003366;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header-print h1 {
            font-size: 22px;
            color: #003366;
            letter-spacing: 1px;
            margin: 0;
        }
        .header-print h2 {
            font-size: 16px;
            font-weight: normal;
            color: #333;
            margin: 5px 0;
        }
        .header-print .info-extra {
            font-size: 12px;
            color: #555;
            margin-top: 4px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .header-print .info-extra span {
            background: #f5f5f5;
            padding: 2px 12px;
            border-radius: 3px;
        }
        .resumo-print {
            display: flex;
            justify-content: space-around;
            background: #f8f8f8;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            font-size: 13px;
        }
        .resumo-print strong {
            color: #003366;
            font-size: 16px;
        }
        .turma-print {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 10px 12px;
            margin-bottom: 15px;
            page-break-inside: avoid;
            background: white;
        }
        .turma-print .titulo-turma {
            background: #003366;
            color: white;
            padding: 6px 10px;
            margin: -10px -12px 10px -12px;
            border-radius: 4px 4px 0 0;
            font-size: 15px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
        }
        .turma-print .titulo-turma .sala-periodo {
            font-weight: normal;
            font-size: 13px;
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table th {
            background: #e6e6e6;
            text-align: left;
            padding: 4px 6px;
            border-bottom: 2px solid #003366;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table td {
            padding: 4px 6px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            font-size: 11px;
        }
        table tr:nth-child(even) {
            background: #fafafa;
        }
        .badge-sexo {
            display: inline-block;
            padding: 0 6px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-m {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-f {
            background: #fce7f3;
            color: #9d174d;
        }
        .total-alunos {
            text-align: right;
            font-weight: bold;
            font-size: 12px;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed #ccc;
        }
        .footer-print {
            text-align: center;
            font-size: 10px;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 8px;
            margin-top: 8px;
        }
        @media print {
            body { margin: 5mm; }
            .turma-print { page-break-inside: avoid; }
            table td { font-size: 10px; }
        }
    `);
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    
    // Pegar o conteúdo HTML do printContent
    var content = document.getElementById('printContent').innerHTML;
    printWindow.document.write(content);
    
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    
    // Aguardar o carregamento e imprimir
    printWindow.onload = function() {
        printWindow.focus();
        printWindow.print();
    };
}

function fecharImpressao() {
    document.getElementById('printModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Fechar modal ao clicar fora
document.getElementById('printModal').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharImpressao();
    }
});

// Fechar com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharImpressao();
    }
});
</script>

<?php include '../includes/footer_escola.php'; ?>