<?php
// ============================================
// modules/escola/financeiro/relatorios/auto_relatorio_agt.php
// Sistema Automático de Relatórios AGT
// ============================================

// Carregar configurações
require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

/**
 * CLASSE PARA GERENCIAR RELATÓRIOS AUTOMÁTICOS
 */
class AutoRelatorioAGT {
    private $pdo;
    private $diretorioBase;
    private $anoAtual;
    private $mesAtual;
    private $dataAtual;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->anoAtual = date('Y');
        $this->mesAtual = date('m');
        $this->dataAtual = date('Y-m-d');
        $this->diretorioBase = __DIR__ . '/relatorios_agt/';
        
        // Criar diretório se não existir
        if (!file_exists($this->diretorioBase)) {
            mkdir($this->diretorioBase, 0777, true);
        }
        
        // Criar diretório do ano se não existir
        $dirAno = $this->diretorioBase . $this->anoAtual . '/';
        if (!file_exists($dirAno)) {
            mkdir($dirAno, 0777, true);
        }
        
        // Criar diretório do mês se não existir
        $dirMes = $dirAno . $this->mesAtual . '/';
        if (!file_exists($dirMes)) {
            mkdir($dirMes, 0777, true);
        }
    }
    
    /**
     * Busca todos os dados para o relatório
     */
    public function buscarDados() {
        $dados = [
            'totalRecebido' => 0,
            'pagamentosMes' => 0,
            'totalPendente' => 0,
            'totalAlunos' => 0,
            'totalEmolumentos' => 0,
            'totalAtrasados' => 0,
            'totalMensalidades' => 0,
            'todosPagamentos' => [],
            'todasMensalidades' => [],
            'arrecadacaoMensal' => [],
            'resumoEmolumentos' => [],
            'alunosPorTurma' => [],
            'pagamentosPorAluno' => []
        ];
        
        try {
            // ===== 1. BUSCAR TODOS OS PAGAMENTOS =====
            $tabelaPagamentos = $this->tabelaExiste('pagamentos');
            
            if ($tabelaPagamentos) {
                $colunasPagamentos = $this->getColunas('pagamentos');
                
                $sql = "SELECT p.*";
                
                if (in_array('aluno_id', $colunasPagamentos)) {
                    $sql .= ", a.nome as aluno_nome, a.TURMA as aluno_turma";
                }
                
                if (in_array('emolumento_id', $colunasPagamentos)) {
                    $sql .= ", e.nome as emolumento_nome, e.descricao as emolumento_descricao";
                }
                
                $sql .= " FROM pagamentos p";
                
                if (in_array('aluno_id', $colunasPagamentos)) {
                    $sql .= " LEFT JOIN alunos a ON p.aluno_id = a.id";
                }
                if (in_array('emolumento_id', $colunasPagamentos)) {
                    $sql .= " LEFT JOIN emolumentos e ON p.emolumento_id = e.id";
                }
                
                $orderCol = in_array('data_pagamento', $colunasPagamentos) ? 'data_pagamento' : 'created_at';
                $orderCol = in_array($orderCol, $colunasPagamentos) ? $orderCol : 'id';
                
                $sql .= " ORDER BY p.$orderCol DESC";
                
                $dados['todosPagamentos'] = $this->pdo->query($sql)->fetchAll();
                
                // Calcular totais
                foreach ($dados['todosPagamentos'] as $pag) {
                    $valor = floatval($pag['valor'] ?? 0);
                    $status = $pag['status'] ?? 'pendente';
                    
                    if ($status == 'confirmado' || $status == 'pago') {
                        $dados['totalRecebido'] += $valor;
                        
                        $dataPag = $pag['data_pagamento'] ?? $pag['created_at'] ?? null;
                        if ($dataPag) {
                            $mesPag = date('m', strtotime($dataPag));
                            $anoPag = date('Y', strtotime($dataPag));
                            if ($mesPag == $this->mesAtual && $anoPag == $this->anoAtual) {
                                $dados['pagamentosMes'] += $valor;
                            }
                        }
                    }
                }
            }
            
            // ===== 2. BUSCAR MENSALIDADES =====
            if ($this->tabelaExiste('mensalidades')) {
                $sql = "SELECT m.*, a.nome as aluno_nome, a.TURMA as aluno_turma 
                        FROM mensalidades m
                        LEFT JOIN alunos a ON m.aluno_id = a.id
                        ORDER BY m.data_vencimento DESC";
                
                $dados['todasMensalidades'] = $this->pdo->query($sql)->fetchAll();
                
                foreach ($dados['todasMensalidades'] as $mens) {
                    $valor = floatval($mens['valor'] ?? 0);
                    $status = $mens['status'] ?? 'pendente';
                    
                    if ($status == 'pendente' || $status == 'atrasado') {
                        $dados['totalPendente'] += $valor;
                        if ($status == 'atrasado') {
                            $dados['totalAtrasados']++;
                        }
                    }
                    $dados['totalMensalidades']++;
                }
            }
            
            // ===== 3. BUSCAR ALUNOS =====
            if ($this->tabelaExiste('alunos')) {
                $colunasAlunos = $this->getColunas('alunos');
                
                $sql = "SELECT COUNT(*) FROM alunos";
                if (in_array('status', $colunasAlunos)) {
                    $sql .= " WHERE status = 'ativo' OR status IS NULL";
                }
                $dados['totalAlunos'] = $this->pdo->query($sql)->fetchColumn() ?? 0;
                
                // Alunos por turma
                $sqlTurmas = "SELECT TURMA, COUNT(*) as total FROM alunos";
                if (in_array('status', $colunasAlunos)) {
                    $sqlTurmas .= " WHERE status = 'ativo' OR status IS NULL";
                }
                $sqlTurmas .= " GROUP BY TURMA ORDER BY TURMA";
                $dados['alunosPorTurma'] = $this->pdo->query($sqlTurmas)->fetchAll();
            }
            
            // ===== 4. BUSCAR EMOLUMENTOS =====
            if ($this->tabelaExiste('emolumentos')) {
                $colunasEmolumentos = $this->getColunas('emolumentos');
                
                $sql = "SELECT COUNT(*) FROM emolumentos";
                if (in_array('status', $colunasEmolumentos)) {
                    $sql .= " WHERE status = 'ativo' OR status IS NULL";
                }
                $dados['totalEmolumentos'] = $this->pdo->query($sql)->fetchColumn() ?? 0;
            }
            
            // ===== 5. ARRECADAÇÃO POR MÊS =====
            if ($tabelaPagamentos) {
                try {
                    $sql = "SELECT 
                                DATE_FORMAT(p.data_pagamento, '%Y-%m') as mes_ano,
                                DATE_FORMAT(p.data_pagamento, '%M/%Y') as mes_nome,
                                COUNT(*) as total_pagamentos,
                                SUM(p.valor) as total_arrecadado
                            FROM pagamentos p
                            WHERE p.status IN ('confirmado', 'pago')
                            GROUP BY DATE_FORMAT(p.data_pagamento, '%Y-%m')
                            ORDER BY mes_ano DESC";
                    
                    $dados['arrecadacaoMensal'] = $this->pdo->query($sql)->fetchAll();
                    
                    if (empty($dados['arrecadacaoMensal'])) {
                        $sql = "SELECT 
                                    DATE_FORMAT(p.data_pagamento, '%Y-%m') as mes_ano,
                                    DATE_FORMAT(p.data_pagamento, '%M/%Y') as mes_nome,
                                    COUNT(*) as total_pagamentos,
                                    SUM(p.valor) as total_arrecadado
                                FROM pagamentos p
                                GROUP BY DATE_FORMAT(p.data_pagamento, '%Y-%m')
                                ORDER BY mes_ano DESC";
                        
                        $dados['arrecadacaoMensal'] = $this->pdo->query($sql)->fetchAll();
                    }
                } catch (Exception $e) {
                    $dados['arrecadacaoMensal'] = [];
                }
            }
            
            // ===== 6. RESUMO POR EMOLUMENTO =====
            if ($tabelaPagamentos) {
                try {
                    $sql = "SELECT 
                                e.nome as emolumento_nome,
                                e.descricao as emolumento_descricao,
                                COUNT(DISTINCT p.id) as total_pagamentos,
                                COUNT(DISTINCT p.aluno_id) as total_alunos,
                                SUM(CASE WHEN p.status IN ('confirmado', 'pago') THEN p.valor ELSE 0 END) as valor_total
                            FROM emolumentos e
                            LEFT JOIN pagamentos p ON p.emolumento_id = e.id
                            WHERE e.status = 'ativo' OR e.status IS NULL
                            GROUP BY e.id
                            HAVING valor_total > 0
                            ORDER BY valor_total DESC";
                    
                    $dados['resumoEmolumentos'] = $this->pdo->query($sql)->fetchAll();
                    
                    if (empty($dados['resumoEmolumentos'])) {
                        $sql = "SELECT 
                                    e.nome as emolumento_nome,
                                    e.descricao as emolumento_descricao,
                                    COUNT(DISTINCT p.id) as total_pagamentos,
                                    COUNT(DISTINCT p.aluno_id) as total_alunos,
                                    SUM(p.valor) as valor_total
                                FROM emolumentos e
                                LEFT JOIN pagamentos p ON p.emolumento_id = e.id
                                GROUP BY e.id
                                HAVING valor_total > 0
                                ORDER BY valor_total DESC";
                        
                        $dados['resumoEmolumentos'] = $this->pdo->query($sql)->fetchAll();
                    }
                } catch (Exception $e) {
                    $dados['resumoEmolumentos'] = [];
                }
            }
            
            // ===== 7. PAGAMENTOS POR ALUNO =====
            if ($tabelaPagamentos) {
                try {
                    $sql = "SELECT 
                                a.nome as aluno_nome,
                                a.TURMA as aluno_turma,
                                COUNT(p.id) as total_pagamentos,
                                SUM(CASE WHEN p.status IN ('confirmado', 'pago') THEN p.valor ELSE 0 END) as total_pago
                            FROM alunos a
                            LEFT JOIN pagamentos p ON p.aluno_id = a.id
                            WHERE a.status = 'ativo' OR a.status IS NULL
                            GROUP BY a.id
                            HAVING total_pagamentos > 0
                            ORDER BY a.nome ASC";
                    
                    $dados['pagamentosPorAluno'] = $this->pdo->query($sql)->fetchAll();
                } catch (Exception $e) {
                    $dados['pagamentosPorAluno'] = [];
                }
            }
            
        } catch (Exception $e) {
            // Log do erro
            error_log("Erro ao buscar dados: " . $e->getMessage());
        }
        
        return $dados;
    }
    
    /**
     * Verifica se uma tabela existe
     */
    private function tabelaExiste($tabela) {
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE '$tabela'");
            return $check->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtém colunas de uma tabela
     */
    private function getColunas($tabela) {
        $colunas = [];
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM $tabela");
            while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
                $colunas[] = $col['Field'];
            }
        } catch (Exception $e) {}
        return $colunas;
    }
    
    /**
     * Gera o relatório HTML
     */
    public function gerarHTML($dados) {
        $anoAtual = $this->anoAtual;
        $mesAtual = $this->mesAtual;
        $dataAtual = date('d/m/Y');
        $horaAtual = date('H:i:s');
        
        $nomeEscola = $_SESSION['escola_nome'] ?? 'Complexo Escolar Castelo';
        $enderecoEscola = $_SESSION['escola_endereco'] ?? 'Luanda, Angola';
        $nifEscola = $_SESSION['escola_nif'] ?? '1234567890';
        $telefoneEscola = $_SESSION['escola_telefone'] ?? '(+244) 900 000 000';
        
        $temPagamentos = count($dados['todosPagamentos']) > 0;
        $temMensalidades = count($dados['todasMensalidades']) > 0;
        $temArrecadacaoMensal = count($dados['arrecadacaoMensal']) > 0;
        $temResumoEmolumentos = count($dados['resumoEmolumentos']) > 0;
        $temAlunosPorTurma = count($dados['alunosPorTurma']) > 0;
        $temPagamentosPorAluno = count($dados['pagamentosPorAluno']) > 0;
        
        $html = '<!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Relatório AGT - ' . htmlspecialchars($nomeEscola) . ' - ' . date('d/m/Y') . '</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap");
                
                * { margin: 0; padding: 0; box-sizing: border-box; }
                
                body {
                    font-family: "Inter", "Segoe UI", Arial, sans-serif;
                    background: #f5f7fa;
                    padding: 20px;
                    color: #1a2332;
                }
                
                .relatorio-container {
                    max-width: 1200px;
                    margin: 0 auto;
                    background: #ffffff;
                    padding: 40px 50px;
                    border: 1px solid #d1d5db;
                    box-shadow: 0 10px 50px rgba(0,0,0,0.08);
                }
                
                .header-oficial {
                    border-bottom: 3px solid #c9a84c;
                    padding-bottom: 20px;
                    margin-bottom: 25px;
                    position: relative;
                }
                
                .header-oficial::after {
                    content: "";
                    position: absolute;
                    bottom: -6px;
                    left: 0;
                    right: 0;
                    height: 3px;
                    background: #1a237e;
                }
                
                .header-oficial .topo {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    flex-wrap: wrap;
                    gap: 15px;
                }
                
                .header-oficial .brasao {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                
                .header-oficial .brasao .emblema {
                    font-size: 48px;
                    line-height: 1;
                }
                
                .header-oficial .brasao .info {
                    line-height: 1.2;
                }
                
                .header-oficial .brasao .info .pais {
                    font-size: 11px;
                    font-weight: 600;
                    color: #1a237e;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                
                .header-oficial .brasao .info .orgao {
                    font-size: 18px;
                    font-weight: 800;
                    color: #0d1445;
                    letter-spacing: -0.5px;
                }
                
                .header-oficial .brasao .info .orgao span {
                    color: #c9a84c;
                }
                
                .header-oficial .documento-info {
                    text-align: right;
                    line-height: 1.4;
                }
                
                .header-oficial .documento-info .titulo-doc {
                    font-size: 20px;
                    font-weight: 800;
                    color: #0d1445;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                
                .header-oficial .documento-info .num-doc {
                    font-size: 12px;
                    color: #4a5568;
                    font-weight: 500;
                }
                
                .header-oficial .documento-info .data-doc {
                    font-size: 13px;
                    color: #4a5568;
                }
                
                .info-escola {
                    background: #f8fafc;
                    padding: 12px 20px;
                    border-radius: 8px;
                    margin-bottom: 25px;
                    border-left: 4px solid #c9a84c;
                    display: flex;
                    justify-content: space-between;
                    flex-wrap: wrap;
                    gap: 10px;
                }
                
                .info-escola .escola-nome {
                    font-size: 15px;
                    font-weight: 700;
                    color: #1a2332;
                }
                
                .info-escola .escola-detalhes {
                    font-size: 13px;
                    color: #4a5568;
                }
                
                .resumo-executivo {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 12px;
                    margin-bottom: 25px;
                }
                
                .resumo-item {
                    background: #f8fafc;
                    padding: 12px 15px;
                    border-radius: 8px;
                    text-align: center;
                    border: 1px solid #e2e8f0;
                }
                
                .resumo-item .label {
                    font-size: 10px;
                    text-transform: uppercase;
                    color: #94a3b8;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                }
                
                .resumo-item .value {
                    font-size: 20px;
                    font-weight: 800;
                    color: #1a2332;
                    margin-top: 2px;
                }
                
                .resumo-item .value.positivo { color: #2ecc71; }
                .resumo-item .value.negativo { color: #e74c3c; }
                .resumo-item .value.destaque { color: #c9a84c; }
                
                .secao {
                    margin-bottom: 25px;
                }
                
                .secao .secao-titulo {
                    font-size: 15px;
                    font-weight: 700;
                    color: #1a2332;
                    padding-bottom: 8px;
                    border-bottom: 2px solid #e2e8f0;
                    margin-bottom: 12px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    justify-content: space-between;
                }
                
                .secao .secao-titulo .contador {
                    font-size: 12px;
                    color: #94a3b8;
                    font-weight: 400;
                }
                
                .table-wrapper {
                    overflow-x: auto;
                    border-radius: 8px;
                    border: 1px solid #e2e8f0;
                }
                
                .table-relatorio {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 13px;
                }
                
                .table-relatorio thead th {
                    background: #f1f5f9;
                    padding: 8px 12px;
                    text-align: left;
                    font-weight: 600;
                    color: #1a2332;
                    border-bottom: 2px solid #c9a84c;
                    font-size: 11px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .table-relatorio tbody td {
                    padding: 8px 12px;
                    border-bottom: 1px solid #e2e8f0;
                }
                
                .table-relatorio tbody tr:hover {
                    background: #f8fafc;
                }
                
                .table-relatorio tbody tr:last-child td {
                    border-bottom: none;
                }
                
                .table-relatorio .text-right {
                    text-align: right;
                }
                
                .table-relatorio .text-center {
                    text-align: center;
                }
                
                .table-relatorio .status {
                    display: inline-block;
                    padding: 2px 10px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: 600;
                }
                
                .table-relatorio .status.confirmado,
                .table-relatorio .status.pago {
                    background: #d1fae5;
                    color: #065f46;
                }
                
                .table-relatorio .status.pendente {
                    background: #fef3c7;
                    color: #92400e;
                }
                
                .table-relatorio .status.atrasado {
                    background: #fee2e2;
                    color: #991b1b;
                }
                
                .table-relatorio .valor {
                    font-weight: 600;
                }
                
                .table-relatorio .valor.positivo {
                    color: #2ecc71;
                }
                
                .table-relatorio .valor.negativo {
                    color: #e74c3c;
                }
                
                .table-total {
                    background: #f8fafc;
                    font-weight: 700;
                    border-top: 2px solid #1a2332;
                }
                
                .table-total td {
                    padding: 10px 12px !important;
                }
                
                .barra-progresso {
                    width: 100%;
                    height: 18px;
                    background: #f1f5f9;
                    border-radius: 10px;
                    overflow: hidden;
                    position: relative;
                }
                
                .barra-progresso .barra {
                    height: 100%;
                    border-radius: 10px;
                    background: linear-gradient(90deg, #c9a84c, #f5d76e);
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    padding-right: 8px;
                    font-size: 10px;
                    font-weight: 700;
                    color: #1a2332;
                }
                
                .barra-progresso .barra.destaque {
                    background: linear-gradient(90deg, #2ecc71, #27ae60);
                }
                
                .mes-destaque {
                    background: rgba(201, 168, 76, 0.08) !important;
                }
                
                .footer-oficial {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 2px solid #e2e8f0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 15px;
                    font-size: 12px;
                    color: #94a3b8;
                }
                
                .footer-oficial .assinatura {
                    text-align: center;
                    padding-top: 25px;
                }
                
                .footer-oficial .assinatura .linha {
                    width: 180px;
                    border-top: 1px solid #1a2332;
                    margin: 0 auto 5px;
                }
                
                .footer-oficial .assinatura .cargo {
                    font-size: 11px;
                    color: #4a5568;
                    font-weight: 500;
                }
                
                .footer-oficial .assinatura .nome {
                    font-weight: 600;
                    color: #1a2332;
                }
                
                .selo-oficial {
                    display: inline-block;
                    padding: 4px 12px;
                    border: 2px solid #c9a84c;
                    border-radius: 4px;
                    font-size: 10px;
                    font-weight: 700;
                    color: #c9a84c;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    background: rgba(201, 168, 76, 0.05);
                }
                
                .sem-dados {
                    text-align: center;
                    padding: 30px 20px;
                    color: #94a3b8;
                }
                
                .sem-dados .icon {
                    font-size: 40px;
                    display: block;
                    margin-bottom: 10px;
                }
                
                .info-geracao {
                    font-size: 11px;
                    color: #94a3b8;
                    text-align: center;
                    padding: 10px;
                    border-top: 1px solid #e2e8f0;
                    margin-top: 20px;
                }
                
                @media print {
                    body {
                        background: #ffffff;
                        padding: 10px;
                    }
                    .relatorio-container {
                        border: none;
                        box-shadow: none;
                        padding: 20px;
                        max-width: 100%;
                    }
                    .no-print { display: none !important; }
                    .resumo-item { background: #f8fafc !important; }
                }
                
                @media (max-width: 768px) {
                    body { padding: 10px; }
                    .relatorio-container { padding: 20px; }
                    .header-oficial .topo { flex-direction: column; }
                    .header-oficial .documento-info { text-align: left; }
                    .resumo-executivo { grid-template-columns: 1fr 1fr; }
                    .info-escola { flex-direction: column; }
                }
            </style>
        </head>
        <body>
        <div class="relatorio-container">
            <!-- CABEÇALHO -->
            <div class="header-oficial">
                <div class="topo">
                    <div class="brasao">
                        <div class="emblema">🏛️</div>
                        <div class="info">
                            <div class="pais">República de Angola</div>
                            <div class="orgao"><span>AGT</span> · Administração Geral Tributária</div>
                        </div>
                    </div>
                    <div class="documento-info">
                        <div class="titulo-doc">Relatório Financeiro</div>
                        <div class="num-doc">DOC-AGT/' . $anoAtual . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT) . '</div>
                        <div class="data-doc">' . $dataAtual . ' · ' . $horaAtual . '</div>
                    </div>
                </div>
            </div>

            <!-- INFORMAÇÕES DA ESCOLA -->
            <div class="info-escola">
                <div>
                    <div class="escola-nome">' . htmlspecialchars($nomeEscola) . '</div>
                    <div class="escola-detalhes">
                        📍 ' . htmlspecialchars($enderecoEscola) . '
                        <span style="margin:0 8px;">|</span> 📞 ' . htmlspecialchars($telefoneEscola) . '
                    </div>
                </div>
                <div class="escola-detalhes">
                    🔑 NIF: ' . htmlspecialchars($nifEscola) . '
                </div>
            </div>

            <!-- RESUMO EXECUTIVO -->';
        
        $html .= '<div class="resumo-executivo">';
        if ($dados['totalRecebido'] > 0) {
            $html .= '<div class="resumo-item">
                <div class="label">Total Arrecadado</div>
                <div class="value positivo">Kz ' . number_format($dados['totalRecebido'], 2, ',', '.') . '</div>
            </div>';
        }
        if ($dados['pagamentosMes'] > 0) {
            $html .= '<div class="resumo-item">
                <div class="label">Arrecadado (Mês)</div>
                <div class="value destaque">Kz ' . number_format($dados['pagamentosMes'], 2, ',', '.') . '</div>
            </div>';
        }
        if ($dados['totalPendente'] > 0) {
            $html .= '<div class="resumo-item">
                <div class="label">Pendente</div>
                <div class="value negativo">Kz ' . number_format($dados['totalPendente'], 2, ',', '.') . '</div>
            </div>';
        }
        if ($dados['totalAlunos'] > 0) {
            $html .= '<div class="resumo-item">
                <div class="label">Alunos Ativos</div>
                <div class="value">' . number_format($dados['totalAlunos']) . '</div>
            </div>';
        }
        if ($dados['totalMensalidades'] > 0) {
            $html .= '<div class="resumo-item">
                <div class="label">Mensalidades</div>
                <div class="value">' . number_format($dados['totalMensalidades']) . '</div>
            </div>';
        }
        if ($dados['totalAtrasados'] > 0) {
            $html .= '<div class="resumo-item">
                <div class="label">Atrasadas</div>
                <div class="value negativo">' . number_format($dados['totalAtrasados']) . '</div>
            </div>';
        }
        if ($dados['totalEmolumentos'] > 0) {
            $html .= '<div class="resumo-item">
                <div class="label">Emolumentos</div>
                <div class="value">' . number_format($dados['totalEmolumentos']) . '</div>
            </div>';
        }
        $html .= '</div>';

        // ALUNOS POR TURMA
        if ($temAlunosPorTurma) {
            $html .= '<div class="secao">
                <div class="secao-titulo">
                    <span>🏫 Alunos por Turma</span>
                    <span class="contador">' . count($dados['alunosPorTurma']) . ' turmas</span>
                </div>
                <div class="table-wrapper">
                    <table class="table-relatorio">
                        <thead>
                            <tr>
                                <th>Turma</th>
                                <th class="text-center">Total Alunos</th>
                                <th style="min-width: 150px;">Percentual</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            $totalAlunosTurmas = array_sum(array_column($dados['alunosPorTurma'], 'total'));
            $totalAlunosTurmas = $totalAlunosTurmas > 0 ? $totalAlunosTurmas : 1;
            
            foreach ($dados['alunosPorTurma'] as $turma) {
                $percentual = ($turma['total'] / $totalAlunosTurmas) * 100;
                $html .= '<tr>
                    <td><strong>' . htmlspecialchars($turma['TURMA']) . '</strong></td>
                    <td class="text-center">' . $turma['total'] . ' alunos</td>
                    <td>
                        <div class="barra-progresso">
                            <div class="barra" style="width: ' . max(5, $percentual) . '%;">
                                ' . number_format($percentual, 1) . '%
                            </div>
                        </div>
                    </td>
                </tr>';
            }
            
            $html .= '</tbody></table></div></div>';
        }

        // RESUMO POR EMOLUMENTO
        if ($temResumoEmolumentos) {
            $html .= '<div class="secao">
                <div class="secao-titulo">
                    <span>📋 Resumo por Emolumento</span>
                    <span class="contador">' . count($dados['resumoEmolumentos']) . ' tipos</span>
                </div>
                <div class="table-wrapper">
                    <table class="table-relatorio">
                        <thead>
                            <tr>
                                <th>Emolumento</th>
                                <th>Descrição</th>
                                <th class="text-center">Pagamentos</th>
                                <th class="text-center">Alunos</th>
                                <th class="text-right">Total (Kz)</th>
                                <th class="text-right">% do Total</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            $somaEmolumentos = 0;
            $totalGeralEmolumentos = 0;
            
            foreach($dados['resumoEmolumentos'] as $emol) {
                $totalGeralEmolumentos += floatval($emol['valor_total'] ?? 0);
            }
            
            foreach($dados['resumoEmolumentos'] as $emol):
                $valorTotal = floatval($emol['valor_total'] ?? 0);
                $somaEmolumentos += $valorTotal;
                $percentual = $totalGeralEmolumentos > 0 ? ($valorTotal / $totalGeralEmolumentos) * 100 : 0;
                $nome = $emol['emolumento_nome'] ?? 'Não definido';
                $descricao = $emol['emolumento_descricao'] ?? '';
                
                $html .= '<tr>
                    <td><strong>' . htmlspecialchars($nome) . '</strong></td>
                    <td style="font-size:12px;color:#64748b;">' . htmlspecialchars($descricao ?: '-') . '</td>
                    <td class="text-center">' . ($emol['total_pagamentos'] ?? 0) . '</td>
                    <td class="text-center">' . ($emol['total_alunos'] ?? 0) . '</td>
                    <td class="text-right valor positivo">Kz ' . number_format($valorTotal, 2, ',', '.') . '</td>
                    <td class="text-right" style="font-weight:600;color:#c9a84c;">' . number_format($percentual, 1) . '%</td>
                </tr>';
            endforeach;
            
            $html .= '<tr class="table-total">
                <td colspan="4" style="text-align: right; font-size: 14px;">
                    <strong>TOTAL GERAL</strong>
                </td>
                <td class="text-right" style="font-size: 15px; color: #1a2332;">
                    <strong>Kz ' . number_format($somaEmolumentos, 2, ',', '.') . '</strong>
                </td>
                <td class="text-right">
                    <strong>100%</strong>
                </td>
            </tr>
        </tbody></table></div></div>';
        }

        // ARRECADAÇÃO POR MÊS
        if ($temArrecadacaoMensal) {
            $html .= '<div class="secao">
                <div class="secao-titulo">
                    <span>📈 Arrecadação por Mês</span>
                    <span class="contador">Total: ' . count($dados['arrecadacaoMensal']) . ' meses</span>
                </div>';
            
            $maiorValor = 0;
            foreach ($dados['arrecadacaoMensal'] as $mes) {
                $valor = floatval($mes['total_arrecadado'] ?? 0);
                if ($valor > $maiorValor) $maiorValor = $valor;
            }
            $maiorValor = $maiorValor > 0 ? $maiorValor : 1;
            $somaMensal = 0;
            $mesAtualNome = date('F/Y');
            
            $html .= '<div class="table-wrapper">
                <table class="table-relatorio">
                    <thead>
                        <tr>
                            <th>Mês/Ano</th>
                            <th class="text-center">Pagamentos</th>
                            <th class="text-right">Total (Kz)</th>
                            <th style="min-width: 150px;">Progresso</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach($dados['arrecadacaoMensal'] as $mes):
                $valor = floatval($mes['total_arrecadado'] ?? 0);
                $somaMensal += $valor;
                $percentual = ($valor / $maiorValor) * 100;
                $isMesAtual = ($mes['mes_nome'] ?? '') == $mesAtualNome;
                
                $html .= '<tr class="' . ($isMesAtual ? 'mes-destaque' : '') . '">
                    <td>
                        <strong>' . htmlspecialchars($mes['mes_nome'] ?? $mes['mes_ano'] ?? 'N/I') . '</strong>
                        ' . ($isMesAtual ? '<span style="font-size:10px;color:#c9a84c;font-weight:600;margin-left:8px;">← Atual</span>' : '') . '
                    </td>
                    <td class="text-center">' . ($mes['total_pagamentos'] ?? 0) . '</td>
                    <td class="text-right valor positivo">' . number_format($valor, 2, ',', '.') . '</td>
                    <td>
                        <div class="barra-progresso">
                            <div class="barra ' . ($isMesAtual ? 'destaque' : '') . '" style="width: ' . max(5, $percentual) . '%;">
                                ' . number_format($percentual, 0) . '%
                            </div>
                        </div>
                    </td>
                </tr>';
            endforeach;
            
            $html .= '<tr class="table-total">
                <td><strong>TOTAL GERAL</strong></td>
                <td class="text-center"><strong>' . array_sum(array_column($dados['arrecadacaoMensal'], 'total_pagamentos')) . '</strong></td>
                <td class="text-right" style="font-size: 15px;"><strong>Kz ' . number_format($somaMensal, 2, ',', '.') . '</strong></td>
                <td></td>
            </tr>
        </tbody></table></div></div>';
        }

        // PAGAMENTOS POR ALUNO (ORDEM ALFABÉTICA)
        if ($temPagamentosPorAluno) {
            $html .= '<div class="secao">
                <div class="secao-titulo">
                    <span>👨‍🎓 Pagamentos por Aluno (Ordem Alfabética)</span>
                    <span class="contador">Total: ' . count($dados['pagamentosPorAluno']) . ' alunos</span>
                </div>
                <div class="table-wrapper">
                    <table class="table-relatorio">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Turma</th>
                                <th class="text-center">Pagamentos</th>
                                <th class="text-right">Total Pago (Kz)</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            $somaTotalPago = 0;
            foreach($dados['pagamentosPorAluno'] as $aluno):
                $totalPago = floatval($aluno['total_pago'] ?? 0);
                $somaTotalPago += $totalPago;
                
                $html .= '<tr>
                    <td><strong>' . htmlspecialchars($aluno['aluno_nome'] ?? 'N/I') . '</strong></td>
                    <td>' . htmlspecialchars($aluno['aluno_turma'] ?? '-') . '</td>
                    <td class="text-center">' . ($aluno['total_pagamentos'] ?? 0) . '</td>
                    <td class="text-right valor positivo">' . number_format($totalPago, 2, ',', '.') . '</td>
                </tr>';
            endforeach;
            
            $html .= '<tr class="table-total">
                <td colspan="3" style="text-align: right;"><strong>TOTAL GERAL</strong></td>
                <td class="text-right" style="font-size: 15px;"><strong>Kz ' . number_format($somaTotalPago, 2, ',', '.') . '</strong></td>
            </tr>
        </tbody></table></div></div>';
        }

        // TODOS OS PAGAMENTOS
        if ($temPagamentos) {
            $html .= '<div class="secao">
                <div class="secao-titulo">
                    <span>💰 Todos os Pagamentos</span>
                    <span class="contador">Total: ' . count($dados['todosPagamentos']) . ' registros</span>
                </div>
                <div class="table-wrapper">
                    <table class="table-relatorio">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Aluno</th>
                                <th>Turma</th>
                                <th>Emolumento</th>
                                <th class="text-right">Valor (Kz)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            $somaTotal = 0;
            foreach($dados['todosPagamentos'] as $pag):
                $dataPag = isset($pag['data_pagamento']) ? $pag['data_pagamento'] : (isset($pag['created_at']) ? $pag['created_at'] : date('Y-m-d'));
                $status = isset($pag['status']) ? $pag['status'] : 'pendente';
                $valor = floatval($pag['valor'] ?? 0);
                $somaTotal += $valor;
                $emolumentoNome = $pag['emolumento_nome'] ?? 'Pagamento';
                
                $html .= '<tr>
                    <td>' . date('d/m/Y', strtotime($dataPag)) . '</td>
                    <td><strong>' . htmlspecialchars($pag['aluno_nome'] ?? 'N/I') . '</strong></td>
                    <td>' . htmlspecialchars($pag['aluno_turma'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($emolumentoNome) . '</td>
                    <td class="text-right valor ' . (($status == 'confirmado' || $status == 'pago') ? 'positivo' : ($status == 'cancelado' ? 'negativo' : '')) . '">' . number_format($valor, 2, ',', '.') . '</td>
                    <td>
                        <span class="status ' . $status . '">' . ucfirst($status) . '</span>
                    </td>
                </tr>';
            endforeach;
            
            $html .= '<tr class="table-total">
                <td colspan="4" style="text-align: right;"><strong>TOTAL GERAL</strong></td>
                <td class="text-right" style="font-size: 15px;"><strong>Kz ' . number_format($somaTotal, 2, ',', '.') . '</strong></td>
                <td></td>
            </tr>
        </tbody></table></div></div>';
        }

        // MENSAGEM QUANDO NÃO HÁ DADOS
        if (!$temPagamentos && !$temMensalidades && !$temArrecadacaoMensal && !$temResumoEmolumentos) {
            $html .= '<div class="sem-dados" style="padding: 50px 20px;">
                <span class="icon">📭</span>
                <h3 style="color: #1a2332; margin-bottom: 10px;">Nenhum dado financeiro encontrado</h3>
                <p>Não há registros de pagamentos, mensalidades ou emolumentos no sistema.</p>
                <p style="font-size: 13px; margin-top: 10px; color: #94a3b8;">
                    Cadastre alunos, emolumentos e registre pagamentos para visualizar o relatório.
                </p>
            </div>';
        }

        // RODAPÉ
        $html .= '<div class="footer-oficial">
                <div>
                    <span class="selo-oficial">Documento Oficial AGT</span>
                    <div style="margin-top: 5px;">🔒 Válido para fins tributários</div>
                </div>
                <div>
                    <div>Emissão: ' . $dataAtual . ' · ' . $horaAtual . '</div>
                    <div style="font-size: 11px; color: #94a3b8;">Sistema AGT v2.0</div>
                </div>
            </div>
            
            <div class="footer-oficial" style="margin-top: 10px; border-top: none; padding-top: 0;">
                <div class="assinatura" style="flex: 1;">
                    <div class="linha"></div>
                    <div class="nome">__________________________________</div>
                    <div class="cargo">Director Financeiro</div>
                    <div style="font-size: 11px; color: #94a3b8;">Responsável Tributário</div>
                </div>
                <div class="assinatura" style="flex: 1;">
                    <div class="linha"></div>
                    <div class="nome">__________________________________</div>
                    <div class="cargo">Coordenador AGT</div>
                    <div style="font-size: 11px; color: #94a3b8;">Administração Geral Tributária</div>
                </div>
            </div>
            
            <div class="info-geracao">
                Relatório gerado automaticamente em ' . $dataAtual . ' às ' . $horaAtual . '
                | Versão: 1.0 | ID: ' . uniqid() . '
            </div>
        </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Salva o relatório automaticamente
     */
    public function salvarRelatorio($html) {
        $timestamp = date('Y-m-d_His');
        $nomeArquivo = 'AGT_Relatorio_' . $timestamp . '.html';
        $caminhoArquivo = $this->diretorioBase . $this->anoAtual . '/' . $this->mesAtual . '/' . $nomeArquivo;
        
        file_put_contents($caminhoArquivo, $html);
        
        return $caminhoArquivo;
    }
    
    /**
     * Lista todos os relatórios salvos
     */
    public function listarRelatorios() {
        $relatorios = [];
        $dirAno = $this->diretorioBase;
        
        if (!file_exists($dirAno)) {
            return $relatorios;
        }
        
        $anos = scandir($dirAno);
        $anos = array_filter($anos, function($item) {
            return $item !== '.' && $item !== '..' && is_dir($this->diretorioBase . $item);
        });
        
        rsort($anos);
        
        foreach ($anos as $ano) {
            $dirMes = $this->diretorioBase . $ano . '/';
            if (!file_exists($dirMes)) continue;
            
            $meses = scandir($dirMes);
            $meses = array_filter($meses, function($item) {
                return $item !== '.' && $item !== '..' && is_dir($this->diretorioBase . $ano . '/' . $item);
            });
            
            rsort($meses);
            
            foreach ($meses as $mes) {
                $dirRelatorios = $this->diretorioBase . $ano . '/' . $mes . '/';
                if (!file_exists($dirRelatorios)) continue;
                
                $arquivos = scandir($dirRelatorios);
                $arquivos = array_filter($arquivos, function($item) {
                    return $item !== '.' && $item !== '..' && pathinfo($item, PATHINFO_EXTENSION) === 'html';
                });
                
                sort($arquivos);
                
                foreach ($arquivos as $arquivo) {
                    $caminho = $dirRelatorios . $arquivo;
                    $relatorios[] = [
                        'ano' => $ano,
                        'mes' => $mes,
                        'nome' => $arquivo,
                        'caminho' => $caminho,
                        'data' => date('d/m/Y H:i:s', filemtime($caminho)),
                        'tamanho' => $this->formatarTamanho(filesize($caminho))
                    ];
                }
            }
        }
        
        return $relatorios;
    }
    
    /**
     * Formata o tamanho do arquivo
     */
    private function formatarTamanho($bytes) {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
    
    /**
     * Gera e salva relatório automaticamente
     */
    public function gerarEAutomatico() {
        $dados = $this->buscarDados();
        $html = $this->gerarHTML($dados);
        $caminho = $this->salvarRelatorio($html);
        
        return [
            'caminho' => $caminho,
            'html' => $html,
            'dados' => $dados
        ];
    }
}

// ===== EXECUÇÃO =====

$autoRelatorio = new AutoRelatorioAGT($pdo);

// Verificar se é uma requisição AJAX para listar relatórios
if (isset($_GET['acao']) && $_GET['acao'] === 'listar') {
    header('Content-Type: application/json');
    echo json_encode($autoRelatorio->listarRelatorios());
    exit;
}

// Gerar relatório automático
$resultado = $autoRelatorio->gerarEAutomatico();
$relatorios = $autoRelatorio->listarRelatorios();
$ultimoRelatorio = !empty($relatorios) ? $relatorios[0] : null;

// Exibir o HTML do relatório gerado
echo $resultado['html'];
?>

<!-- ===== PAINEL DE CONTROLE DOS RELATÓRIOS ===== -->
<div class="no-print" style="max-width: 1200px; margin: 20px auto; background: #ffffff; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <h3 style="color: #1a2332; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                📁 Relatórios Automáticos AGT
                <span style="font-size: 12px; font-weight: 400; color: #94a3b8;">
                    Total: <?= count($relatorios) ?>
                </span>
            </h3>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="window.location.reload()" style="
                padding: 8px 20px;
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                color: #4a5568;
                transition: all 0.3s;
            " onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                🔄 Gerar Novo
            </button>
            <button onclick="window.print()" style="
                padding: 8px 20px;
                background: linear-gradient(135deg, #1a237e, #0d1445);
                color: #c9a84c;
                border: 2px solid #c9a84c;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 700;
                transition: all 0.3s;
                font-size: 13px;
            " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                🖨️ Imprimir / PDF
            </button>
        </div>
    </div>
    
    <?php if (!empty($relatorios)): ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="text-align: left; padding: 10px 15px; font-weight: 600; color: #4a5568;">#</th>
                    <th style="text-align: left; padding: 10px 15px; font-weight: 600; color: #4a5568;">Ano/Mês</th>
                    <th style="text-align: left; padding: 10px 15px; font-weight: 600; color: #4a5568;">Arquivo</th>
                    <th style="text-align: left; padding: 10px 15px; font-weight: 600; color: #4a5568;">Data de Criação</th>
                    <th style="text-align: left; padding: 10px 15px; font-weight: 600; color: #4a5568;">Tamanho</th>
                    <th style="text-align: left; padding: 10px 15px; font-weight: 600; color: #4a5568;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($relatorios as $index => $rel): ?>
                <tr style="border-bottom: 1px solid #f1f5f9; <?= $index === 0 ? 'background: #f0f7ff;' : '' ?>">
                    <td style="padding: 10px 15px;"><?= $index + 1 ?></td>
                    <td style="padding: 10px 15px;">
                        <strong><?= $rel['ano'] ?></strong> / <?= $rel['mes'] ?>
                        <?php if ($index === 0): ?>
                            <span style="display: inline-block; background: #c9a84c; color: #fff; font-size: 10px; padding: 1px 8px; border-radius: 10px; margin-left: 8px; font-weight: 700;">ATUAL</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px 15px;">
                        <span style="color: #1a237e; font-weight: 500;"><?= htmlspecialchars($rel['nome']) ?></span>
                    </td>
                    <td style="padding: 10px 15px; color: #64748b;"><?= $rel['data'] ?></td>
                    <td style="padding: 10px 15px; color: #64748b;"><?= $rel['tamanho'] ?></td>
                    <td style="padding: 10px 15px;">
                        <a href="<?= $rel['caminho'] ?>" target="_blank" style="
                            display: inline-block;
                            padding: 4px 15px;
                            background: #1a237e;
                            color: #fff;
                            text-decoration: none;
                            border-radius: 6px;
                            font-size: 12px;
                            font-weight: 600;
                            transition: all 0.3s;
                        " onmouseover="this.style.background='#0d1445'" onmouseout="this.style.background='#1a237e'">
                            📄 Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align: center; padding: 30px; color: #94a3b8;">
        <div style="font-size: 40px; margin-bottom: 10px;">📭</div>
        <p style="font-weight: 500;">Nenhum relatório salvo ainda.</p>
        <p style="font-size: 13px;">Os relatórios serão salvos automaticamente a cada acesso.</p>
    </div>
    <?php endif; ?>
</div>