<?php
// ============================================
// modules/escola/financeiro/relatorios/auto_agt_completo.php
// RELATÓRIO AGT COMPLETO - COM FILTROS DINÂMICOS
// ============================================

// Evitar execução múltipla
if (defined('AUTO_AGT_COMPLETO_RUNNING')) {
    return;
}
define('AUTO_AGT_COMPLETO_RUNNING', true);

// ============================================
// CAMINHO ABSOLUTO
// ============================================
$rootPath = 'C:/xampp/htdocs/softgest_web/';

// Carregar configurações
require_once $rootPath . 'config/database.php';
require_once $rootPath . 'config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * CLASSE: RELATÓRIO AGT COMPLETO COM FILTROS
 */
class RelatorioAGTCompleto {
    private $pdo;
    private $diretorioBase;
    private $diretorioFaturas;
    private $configAGT;
    private $empresa;
    private $anoAtual;
    private $mesAtual;
    private $emolumentosCache = [];
    
    // Filtros
    private $filtros = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->diretorioBase = __DIR__ . '/relatorios_agt_completo/';
        $this->diretorioFaturas = 'C:/xampp/htdocs/meus_documentos/faturas/';
        $this->anoAtual = date('Y');
        $this->mesAtual = date('m');
        
        // Carregar filtros da requisição e manter via SESSION
        $this->carregarFiltros();
        
        $this->carregarConfiguracoes();
        $this->criarDiretorios();
        $this->carregarEmolumentos();
    }
    
    private function carregarFiltros() {
        // Se houver requisição POST ou GET com filtros, salvar na SESSION
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['aplicar_filtros'])) {
            // Iniciar sessão se não estiver ativa
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Salvar filtros na SESSION
            $_SESSION['agt_filtros'] = [
                'categoria' => $_REQUEST['categoria'] ?? $_SESSION['agt_filtros']['categoria'] ?? '',
                'classe' => $_REQUEST['classe'] ?? $_SESSION['agt_filtros']['classe'] ?? '',
                'aluno' => $_REQUEST['aluno'] ?? $_SESSION['agt_filtros']['aluno'] ?? '',
                'mes_referencia' => $_REQUEST['mes_referencia'] ?? $_SESSION['agt_filtros']['mes_referencia'] ?? '',
                'tipo_pagamento' => $_REQUEST['tipo_pagamento'] ?? $_SESSION['agt_filtros']['tipo_pagamento'] ?? '',
                'status' => $_REQUEST['status'] ?? $_SESSION['agt_filtros']['status'] ?? '',
                'data_inicio' => $_REQUEST['data_inicio'] ?? $_SESSION['agt_filtros']['data_inicio'] ?? '',
                'data_fim' => $_REQUEST['data_fim'] ?? $_SESSION['agt_filtros']['data_fim'] ?? '',
                'order_by' => $_REQUEST['order_by'] ?? $_SESSION['agt_filtros']['order_by'] ?? 'nome',
                'order_dir' => $_REQUEST['order_dir'] ?? $_SESSION['agt_filtros']['order_dir'] ?? 'ASC'
            ];
        }
        
        // Limpar filtros individuais
        if (isset($_GET['limpar_filtro']) && !empty($_GET['limpar_filtro'])) {
            $key = $_GET['limpar_filtro'];
            if (isset($_SESSION['agt_filtros'][$key])) {
                $_SESSION['agt_filtros'][$key] = '';
            }
        }
        
        // Limpar todos os filtros
        if (isset($_GET['limpar_filtros'])) {
            $_SESSION['agt_filtros'] = [
                'categoria' => '',
                'classe' => '',
                'aluno' => '',
                'mes_referencia' => '',
                'tipo_pagamento' => '',
                'status' => '',
                'data_inicio' => '',
                'data_fim' => '',
                'order_by' => 'nome',
                'order_dir' => 'ASC'
            ];
            // Redirecionar para remover os parâmetros da URL
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        
        // Carregar filtros da SESSION ou usar valores padrão
        if (isset($_SESSION['agt_filtros'])) {
            $this->filtros = $_SESSION['agt_filtros'];
        } else {
            $this->filtros = [
                'categoria' => '',
                'classe' => '',
                'aluno' => '',
                'mes_referencia' => '',
                'tipo_pagamento' => '',
                'status' => '',
                'data_inicio' => '',
                'data_fim' => '',
                'order_by' => 'nome',
                'order_dir' => 'ASC'
            ];
        }
        
        // Se a requisição for GET e tiver parâmetros de filtro, atualizar
        if (!empty($_GET) && !isset($_GET['limpar_filtros']) && !isset($_GET['limpar_filtro'])) {
            $params = ['categoria', 'classe', 'aluno', 'mes_referencia', 'tipo_pagamento', 'status', 'data_inicio', 'data_fim', 'order_by', 'order_dir'];
            foreach ($params as $param) {
                if (isset($_GET[$param])) {
                    $this->filtros[$param] = $_GET[$param];
                    $_SESSION['agt_filtros'][$param] = $_GET[$param];
                }
            }
        }
    }
    
    private function carregarConfiguracoes() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM config_agt WHERE id = 1");
            $stmt->execute();
            $this->configAGT = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $this->configAGT = [];
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM empresa WHERE id = 1");
            $stmt->execute();
            $this->empresa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $this->empresa = [];
        }
    }
    
    private function criarDiretorios() {
        $diretorios = [
            $this->diretorioBase,
            $this->diretorioBase . $this->anoAtual . '/',
            $this->diretorioBase . $this->anoAtual . '/' . $this->mesAtual . '/',
            $this->diretorioBase . 'logs/',
            $this->diretorioBase . 'backups/'
        ];
        
        foreach ($diretorios as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
        }
        
        // Diretório de faturas
        if (!file_exists($this->diretorioFaturas)) {
            mkdir($this->diretorioFaturas, 0777, true);
        }
        
        $dirFaturasAno = $this->diretorioFaturas . $this->anoAtual . '/';
        if (!file_exists($dirFaturasAno)) {
            mkdir($dirFaturasAno, 0777, true);
        }
        
        $dirFaturasMes = $dirFaturasAno . $this->mesAtual . '/';
        if (!file_exists($dirFaturasMes)) {
            mkdir($dirFaturasMes, 0777, true);
        }
    }
    
    private function carregarEmolumentos() {
        try {
            $stmt = $this->pdo->query("SELECT id, nome, descricao, categoria FROM emolumentos");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->emolumentosCache[$row['id']] = [
                    'nome' => $row['nome'],
                    'descricao' => $row['descricao'] ?? '',
                    'categoria' => $row['categoria'] ?? ''
                ];
            }
        } catch (Exception $e) {}
    }
    
    private function getEmolumentoNome($emolumentoId) {
        if (isset($this->emolumentosCache[$emolumentoId])) {
            return $this->emolumentosCache[$emolumentoId]['nome'];
        }
        return null;
    }
    
    private function getEmolumentoDescricao($emolumentoId) {
        if (isset($this->emolumentosCache[$emolumentoId])) {
            return $this->emolumentosCache[$emolumentoId]['descricao'];
        }
        return null;
    }
    
    /**
     * APLICA FILTROS NA CONSULTA SQL
     */
    private function aplicarFiltros($sql) {
        $where = [];
        $params = [];
        
        // Filtro por categoria
        if (!empty($this->filtros['categoria'])) {
            $where[] = "e.categoria = :categoria";
            $params[':categoria'] = $this->filtros['categoria'];
        }
        
        // Filtro por classe/turma
        if (!empty($this->filtros['classe'])) {
            $where[] = "a.TURMA = :classe";
            $params[':classe'] = $this->filtros['classe'];
        }
        
        // Filtro por aluno
        if (!empty($this->filtros['aluno'])) {
            $where[] = "a.nome LIKE :aluno";
            $params[':aluno'] = '%' . $this->filtros['aluno'] . '%';
        }
        
        // Filtro por mês de referência
        if (!empty($this->filtros['mes_referencia'])) {
            $where[] = "p.mes_referencia = :mes_referencia";
            $params[':mes_referencia'] = $this->filtros['mes_referencia'];
        }
        
        // Filtro por tipo de pagamento
        if (!empty($this->filtros['tipo_pagamento'])) {
            $where[] = "p.forma_pagamento = :tipo_pagamento";
            $params[':tipo_pagamento'] = $this->filtros['tipo_pagamento'];
        }
        
        // Filtro por status
        if (!empty($this->filtros['status'])) {
            $where[] = "p.status = :status";
            $params[':status'] = $this->filtros['status'];
        }
        
        // Filtro por data (início e fim)
        if (!empty($this->filtros['data_inicio']) && !empty($this->filtros['data_fim'])) {
            $where[] = "p.data_pagamento BETWEEN :data_inicio AND :data_fim";
            $params[':data_inicio'] = $this->filtros['data_inicio'];
            $params[':data_fim'] = $this->filtros['data_fim'];
        } elseif (!empty($this->filtros['data_inicio'])) {
            $where[] = "p.data_pagamento >= :data_inicio";
            $params[':data_inicio'] = $this->filtros['data_inicio'];
        } elseif (!empty($this->filtros['data_fim'])) {
            $where[] = "p.data_pagamento <= :data_fim";
            $params[':data_fim'] = $this->filtros['data_fim'];
        }
        
        // Adicionar WHERE se houver filtros
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        // Ordenação
        $orderBy = 'a.nome';
        switch ($this->filtros['order_by']) {
            case 'data':
                $orderBy = 'p.data_pagamento';
                break;
            case 'valor':
                $orderBy = 'p.valor';
                break;
            case 'status':
                $orderBy = 'p.status';
                break;
            case 'categoria':
                $orderBy = 'e.categoria';
                break;
            default:
                $orderBy = 'a.nome';
        }
        
        $orderDir = $this->filtros['order_dir'] === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY " . $orderBy . " " . $orderDir;
        
        return ['sql' => $sql, 'params' => $params];
    }
    
    /**
     * BUSCA TODOS OS DADOS COM FILTROS
     */
    private function buscarDadosCompletos() {
        $dados = [
            'totalRecebido' => 0,
            'pagamentosMes' => 0,
            'totalPendente' => 0,
            'totalAlunos' => 0,
            'totalEmolumentos' => 0,
            'todosPagamentos' => [],
            'todosAlunos' => [],
            'alunosPorTurma' => [],
            'pagamentosPorMes' => [],
            'pagamentosPorDia' => [],
            'registroDescritivoAlunos' => [],
            'pagamentosPorTipo' => [],
            'resumoAlunosDetalhado' => [],
            'totalPagamentos' => 0,
            'totalPagamentosConfirmados' => 0,
            'totalPagamentosPendentes' => 0,
            'totalPagamentosCartao' => 0,
            'totalPagamentosDinheiro' => 0,
            'totalPagamentosTransferencia' => 0,
            'totalPagamentosPropina' => 0,
            'totalPagamentosMatricula' => 0,
            'totalPagamentosOutros' => 0,
            'filtros_aplicados' => $this->filtros
        ];
        
        try {
            // ============================================
            // 1. ALUNOS
            // ============================================
            try {
                $dados['todosAlunos'] = $this->pdo->query("
                    SELECT * FROM alunos 
                    WHERE status = 'ativo' OR status IS NULL 
                    ORDER BY nome ASC
                ")->fetchAll();
                
                $dados['totalAlunos'] = count($dados['todosAlunos']);
                
                $dados['alunosPorTurma'] = $this->pdo->query("
                    SELECT TURMA, COUNT(*) as total 
                    FROM alunos 
                    WHERE status = 'ativo' OR status IS NULL AND TURMA IS NOT NULL AND TURMA != ''
                    GROUP BY TURMA 
                    ORDER BY TURMA
                ")->fetchAll();
            } catch (Exception $e) {}
            
            // ============================================
            // 2. TODOS OS PAGAMENTOS - COM FILTROS
            // ============================================
            try {
                $sql = "SELECT 
                            p.id as pagamento_id,
                            p.*,
                            a.nome as aluno_nome,
                            a.TURMA as aluno_turma,
                            p.forma_pagamento as tipo_pagamento,
                            p.mes_referencia,
                            p.observacoes,
                            e.nome as emolumento_nome,
                            e.descricao as emolumento_descricao,
                            e.categoria as emolumento_categoria
                        FROM pagamentos p
                        LEFT JOIN alunos a ON p.aluno_id = a.id
                        LEFT JOIN emolumentos e ON p.emolumento_id = e.id";
                
                // Aplicar filtros
                $result = $this->aplicarFiltros($sql);
                $sql = $result['sql'];
                $params = $result['params'];
                
                $stmt = $this->pdo->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $dados['todosPagamentos'] = $stmt->fetchAll();
                $dados['totalPagamentos'] = count($dados['todosPagamentos']);
                
                // Processar pagamentos
                $mesAtual = date('m');
                $anoAtual = date('Y');
                
                foreach ($dados['todosPagamentos'] as &$pag) {
                    if (empty($pag['emolumento_nome']) && !empty($pag['emolumento_id'])) {
                        $nomeCache = $this->getEmolumentoNome($pag['emolumento_id']);
                        if ($nomeCache) {
                            $pag['emolumento_nome'] = $nomeCache;
                        }
                        $descCache = $this->getEmolumentoDescricao($pag['emolumento_id']);
                        if ($descCache) {
                            $pag['emolumento_descricao'] = $descCache;
                        }
                    }
                    
                    if (empty($pag['emolumento_nome'])) {
                        $pag['emolumento_nome'] = 'Pagamento';
                    }
                    
                    $valor = floatval($pag['valor'] ?? 0);
                    $status = $pag['status'] ?? 'pendente';
                    $tipoPagamento = strtolower($pag['tipo_pagamento'] ?? $pag['forma_pagamento'] ?? 'dinheiro');
                    $emolumentoNome = strtolower($pag['emolumento_nome'] ?? '');
                    
                    if ($status == 'confirmado' || $status == 'pago') {
                        $dados['totalRecebido'] += $valor;
                        $dados['totalPagamentosConfirmados']++;
                        
                        $dataPag = $pag['data_pagamento'] ?? $pag['created_at'] ?? null;
                        if ($dataPag && date('m', strtotime($dataPag)) == $mesAtual && date('Y', strtotime($dataPag)) == $anoAtual) {
                            $dados['pagamentosMes'] += $valor;
                        }
                        
                        if (strpos($tipoPagamento, 'cartao') !== false || strpos($tipoPagamento, 'cartão') !== false) {
                            $dados['totalPagamentosCartao'] += $valor;
                        } elseif (strpos($tipoPagamento, 'transferencia') !== false || strpos($tipoPagamento, 'transferência') !== false) {
                            $dados['totalPagamentosTransferencia'] += $valor;
                        } elseif (strpos($tipoPagamento, 'dinheiro') !== false) {
                            $dados['totalPagamentosDinheiro'] += $valor;
                        }
                        
                        if (strpos($emolumentoNome, 'propina') !== false || strpos($emolumentoNome, 'mensalidade') !== false) {
                            $dados['totalPagamentosPropina'] += $valor;
                        } elseif (strpos($emolumentoNome, 'matricula') !== false || strpos($emolumentoNome, 'matrícula') !== false) {
                            $dados['totalPagamentosMatricula'] += $valor;
                        } else {
                            $dados['totalPagamentosOutros'] += $valor;
                        }
                    } elseif ($status == 'pendente') {
                        $dados['totalPagamentosPendentes']++;
                    }
                }
                unset($pag);
                
            } catch (Exception $e) {
                error_log("Erro ao buscar pagamentos: " . $e->getMessage());
            }
            
            // ============================================
            // 3. REGISTRO DESCRITIVO POR ALUNO
            // ============================================
            $dados['registroDescritivoAlunos'] = $this->gerarRegistroDescritivoAlunos($dados['todosPagamentos']);
            
            // ============================================
            // 4. RESUMO DETALHADO POR ALUNO
            // ============================================
            $dados['resumoAlunosDetalhado'] = $this->gerarResumoAlunosDetalhado($dados['todosPagamentos']);
            
        } catch (Exception $e) {
            error_log("Erro ao buscar dados completos AGT: " . $e->getMessage());
        }
        
        return $dados;
    }
    
    /**
     * GERA REGISTRO DESCRITIVO POR ALUNO
     */
    private function gerarRegistroDescritivoAlunos($pagamentos) {
        $registro = [];
        
        $pagamentosPorAluno = [];
        foreach ($pagamentos as $pag) {
            $alunoNome = $pag['aluno_nome'] ?? 'Aluno Desconhecido';
            
            if (!isset($pagamentosPorAluno[$alunoNome])) {
                $pagamentosPorAluno[$alunoNome] = [
                    'aluno_nome' => $alunoNome,
                    'aluno_turma' => $pag['aluno_turma'] ?? '',
                    'pagamentos' => [],
                    'total_pago' => 0,
                    'total_pagamentos' => 0
                ];
            }
            
            if (empty($pag['emolumento_nome'])) {
                $pag['emolumento_nome'] = 'Pagamento';
            }
            
            $pagamentosPorAluno[$alunoNome]['pagamentos'][] = $pag;
            $pagamentosPorAluno[$alunoNome]['total_pago'] += floatval($pag['valor'] ?? 0);
            $pagamentosPorAluno[$alunoNome]['total_pagamentos']++;
        }
        
        ksort($pagamentosPorAluno);
        
        foreach ($pagamentosPorAluno as $alunoNome => $dadosAluno) {
            $registro[] = [
                'tipo' => 'aluno_cabecalho',
                'aluno' => $alunoNome,
                'turma' => $dadosAluno['aluno_turma'],
                'total_pagamentos' => $dadosAluno['total_pagamentos'],
                'total_pago' => $dadosAluno['total_pago']
            ];
            
            foreach ($dadosAluno['pagamentos'] as $pag) {
                $tipoPagamento = $pag['tipo_pagamento'] ?? $pag['forma_pagamento'] ?? 'dinheiro';
                $emolumentoNome = $pag['emolumento_nome'] ?? 'Pagamento';
                $descricao = $pag['emolumento_descricao'] ?? '';
                $mesReferencia = $pag['mes_referencia'] ?? '';
                $pagamentoId = $pag['pagamento_id'] ?? $pag['id'] ?? '';
                
                $categoria = $this->determinarCategoria($emolumentoNome, $descricao);
                
                $registro[] = [
                    'tipo' => 'pagamento_detalhe',
                    'pagamento_id' => $pagamentoId,
                    'data' => $pag['data_pagamento'] ?? $pag['created_at'] ?? date('Y-m-d H:i:s'),
                    'emolumento' => $emolumentoNome,
                    'descricao' => $descricao,
                    'categoria' => $categoria,
                    'tipo_pagamento' => $tipoPagamento,
                    'valor' => floatval($pag['valor'] ?? 0),
                    'status' => $pag['status'] ?? 'pendente',
                    'referencia' => $pag['referencia'] ?? '',
                    'mes_referencia' => $mesReferencia,
                    'observacoes' => $pag['observacoes'] ?? ''
                ];
            }
        }
        
        return $registro;
    }
    
    private function determinarCategoria($nome, $descricao) {
        $texto = strtolower($nome . ' ' . $descricao);
        
        if (strpos($texto, 'propina') !== false || strpos($texto, 'mensalidade') !== false) {
            return 'Propina / Mensalidade';
        } elseif (strpos($texto, 'matricula') !== false || strpos($texto, 'matrícula') !== false) {
            return 'Matrícula';
        } elseif (strpos($texto, 'inscricao') !== false || strpos($texto, 'inscrição') !== false) {
            return 'Inscrição';
        } elseif (strpos($texto, 'exame') !== false) {
            return 'Exame';
        } elseif (strpos($texto, 'certificado') !== false) {
            return 'Certificado';
        } elseif (strpos($texto, 'atestado') !== false) {
            return 'Atestado';
        } elseif (strpos($texto, 'declaracao') !== false || strpos($texto, 'declaração') !== false) {
            return 'Declaração';
        } elseif (strpos($texto, 'transferencia') !== false || strpos($texto, 'transferência') !== false) {
            return 'Transferência';
        } elseif (strpos($texto, 'multa') !== false) {
            return 'Multa';
        } elseif (strpos($texto, 'transporte') !== false) {
            return 'Transporte';
        } elseif (strpos($texto, 'material') !== false) {
            return 'Material Escolar';
        } elseif (strpos($texto, 'uniforme') !== false) {
            return 'Uniforme';
        } else {
            return 'Outros';
        }
    }
    
    private function gerarResumoAlunosDetalhado($pagamentos) {
        $resumo = [];
        
        foreach ($pagamentos as $pag) {
            $alunoNome = $pag['aluno_nome'] ?? 'Aluno Desconhecido';
            
            if (!isset($resumo[$alunoNome])) {
                $resumo[$alunoNome] = [
                    'aluno_nome' => $alunoNome,
                    'aluno_turma' => $pag['aluno_turma'] ?? '',
                    'total_pago' => 0,
                    'total_pagamentos' => 0,
                    'pagamentos_confirmados' => 0,
                    'pagamentos_pendentes' => 0,
                    'por_categoria' => []
                ];
            }
            
            $valor = floatval($pag['valor'] ?? 0);
            $status = $pag['status'] ?? 'pendente';
            $emolumentoNome = $pag['emolumento_nome'] ?? 'Pagamento';
            $descricao = $pag['emolumento_descricao'] ?? '';
            $categoria = $this->determinarCategoria($emolumentoNome, $descricao);
            
            if ($status == 'confirmado' || $status == 'pago') {
                $resumo[$alunoNome]['total_pago'] += $valor;
                $resumo[$alunoNome]['pagamentos_confirmados']++;
            } else {
                $resumo[$alunoNome]['pagamentos_pendentes']++;
            }
            
            $resumo[$alunoNome]['total_pagamentos']++;
            
            if (!isset($resumo[$alunoNome]['por_categoria'][$categoria])) {
                $resumo[$alunoNome]['por_categoria'][$categoria] = 0;
            }
            if ($status == 'confirmado' || $status == 'pago') {
                $resumo[$alunoNome]['por_categoria'][$categoria] += $valor;
            }
        }
        
        return $resumo;
    }
    
    /**
     * GERA HTML DOS FILTROS - CORRIGIDO
     */
    private function gerarFiltrosHTML() {
        // Buscar turmas disponíveis
        $turmas = [];
        try {
            $turmas = $this->pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA")->fetchAll();
        } catch (Exception $e) {}
        
        // Buscar meses de referência disponíveis
        $mesesRef = [];
        try {
            $mesesRef = $this->pdo->query("SELECT DISTINCT mes_referencia FROM pagamentos WHERE mes_referencia IS NOT NULL AND mes_referencia != '-' ORDER BY mes_referencia DESC")->fetchAll();
        } catch (Exception $e) {}
        
        // Categorias disponíveis
        $categorias = [
            'Propina / Mensalidade', 'Matrícula', 'Inscrição', 'Exame', 
            'Certificado', 'Atestado', 'Declaração', 'Transferência',
            'Multa', 'Transporte', 'Material Escolar', 'Uniforme', 'Outros'
        ];
        
        $tiposPagamento = ['dinheiro', 'cartao', 'transferencia'];
        $statusList = ['confirmado', 'pago', 'pendente', 'cancelado'];
        
        // Valores atuais dos filtros
        $f = $this->filtros;
        
        $html = '<div class="filtros-container" style="background:#f8fafc;padding:20px;border-radius:8px;margin-bottom:25px;border:1px solid #e2e8f0;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:15px;">
                <h4 style="color:#1a2332;margin:0;font-size:16px;">🔍 Filtros Dinâmicos</h4>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="?limpar_filtros=1" style="padding:6px 15px;background:#e2e8f0;color:#4a5568;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;" onclick="return confirm(\'Tem certeza que deseja limpar todos os filtros?\')">🗑️ Limpar Filtros</a>
                    <button type="submit" form="form-filtros" style="padding:6px 20px;background:#c9a84c;color:#1a2332;border:none;border-radius:6px;font-weight:700;cursor:pointer;">🔍 Aplicar Filtros</button>
                </div>
            </div>
            
            <form id="form-filtros" method="GET" action="" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                <input type="hidden" name="aplicar_filtros" value="1">
                
                <!-- Categoria -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4a5568;display:block;margin-bottom:3px;">Categoria</label>
                    <select name="categoria" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                        <option value="">Todas</option>';
        
        foreach ($categorias as $cat) {
            $selected = ($f['categoria'] == $cat) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($cat) . '" ' . $selected . '>' . htmlspecialchars($cat) . '</option>';
        }
        
        $html .= '</select>
                </div>
                
                <!-- Classe/Turma -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4a5568;display:block;margin-bottom:3px;">Classe/Turma</label>
                    <select name="classe" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                        <option value="">Todas</option>';
        
        foreach ($turmas as $turma) {
            $selected = ($f['classe'] == $turma['TURMA']) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($turma['TURMA']) . '" ' . $selected . '>' . htmlspecialchars($turma['TURMA']) . '</option>';
        }
        
        $html .= '</select>
                </div>
                
                <!-- Aluno -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4a5568;display:block;margin-bottom:3px;">Aluno</label>
                    <input type="text" name="aluno" value="' . htmlspecialchars($f['aluno']) . '" placeholder="Nome do aluno..." style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                </div>
                
                <!-- Mês Referência -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4a5568;display:block;margin-bottom:3px;">Mês Ref.</label>
                    <select name="mes_referencia" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                        <option value="">Todos</option>';
        
        foreach ($mesesRef as $mes) {
            $selected = ($f['mes_referencia'] == $mes['mes_referencia']) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($mes['mes_referencia']) . '" ' . $selected . '>' . htmlspecialchars($mes['mes_referencia']) . '</option>';
        }
        
        $html .= '</select>
                </div>
                
                <!-- Tipo Pagamento -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4a5568;display:block;margin-bottom:3px;">Tipo Pagamento</label>
                    <select name="tipo_pagamento" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                        <option value="">Todos</option>';
        
        foreach ($tiposPagamento as $tipo) {
            $selected = ($f['tipo_pagamento'] == $tipo) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($tipo) . '" ' . $selected . '>' . ucfirst(htmlspecialchars($tipo)) . '</option>';
        }
        
        $html .= '</select>
                </div>
                
                <!-- Status -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4a5568;display:block;margin-bottom:3px;">Status</label>
                    <select name="status" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                        <option value="">Todos</option>';
        
        foreach ($statusList as $status) {
            $selected = ($f['status'] == $status) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($status) . '" ' . $selected . '>' . ucfirst(htmlspecialchars($status)) . '</option>';
        }
        
        $html .= '</select>
                </div>
                
                <!-- Data Início -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4a5568;display:block;margin-bottom:3px;">Data Início</label>
                    <input type="date" name="data_inicio" value="' . htmlspecialchars($f['data_inicio']) . '" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                </div>
                
                <!-- Data Fim -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4a5568;display:block;margin-bottom:3px;">Data Fim</label>
                    <input type="date" name="data_fim" value="' . htmlspecialchars($f['data_fim']) . '" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                </div>
                
                <!-- Ordenar por -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4a5568;display:block;margin-bottom:3px;">Ordenar por</label>
                    <select name="order_by" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                        <option value="nome" ' . ($f['order_by'] == 'nome' ? 'selected' : '') . '>Nome do Aluno</option>
                        <option value="data" ' . ($f['order_by'] == 'data' ? 'selected' : '') . '>Data</option>
                        <option value="valor" ' . ($f['order_by'] == 'valor' ? 'selected' : '') . '>Valor</option>
                        <option value="status" ' . ($f['order_by'] == 'status' ? 'selected' : '') . '>Status</option>
                        <option value="categoria" ' . ($f['order_by'] == 'categoria' ? 'selected' : '') . '>Categoria</option>
                    </select>
                </div>
                
                <!-- Ordem -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4a5568;display:block;margin-bottom:3px;">Ordem</label>
                    <select name="order_dir" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                        <option value="ASC" ' . ($f['order_dir'] == 'ASC' ? 'selected' : '') . '>Crescente (A-Z)</option>
                        <option value="DESC" ' . ($f['order_dir'] == 'DESC' ? 'selected' : '') . '>Decrescente (Z-A)</option>
                    </select>
                </div>
            </form>
            
            <!-- Mostrar filtros aplicados -->
            ' . $this->gerarBadgesFiltros() . '
        </div>';
        
        return $html;
    }
    
    /**
     * GERA BADGES DOS FILTROS APLICADOS
     */
    private function gerarBadgesFiltros() {
        $filtrosAtivos = [];
        $labels = [
            'categoria' => 'Categoria',
            'classe' => 'Classe',
            'aluno' => 'Aluno',
            'mes_referencia' => 'Mês Ref.',
            'tipo_pagamento' => 'Tipo Pagamento',
            'status' => 'Status',
            'data_inicio' => 'Data Início',
            'data_fim' => 'Data Fim'
        ];
        
        foreach ($this->filtros as $key => $value) {
            if (!empty($value) && isset($labels[$key])) {
                $filtrosAtivos[] = '<span style="display:inline-block;background:#eef2f7;padding:3px 12px;border-radius:12px;font-size:12px;margin:3px;">
                    <strong>' . $labels[$key] . ':</strong> ' . htmlspecialchars($value) . '
                    <a href="?limpar_filtro=' . $key . '" style="color:#e74c3c;text-decoration:none;margin-left:5px;" onclick="return confirm(\'Remover este filtro?\')">✕</a>
                </span>';
            }
        }
        
        if (empty($filtrosAtivos)) {
            return '<div style="margin-top:10px;font-size:13px;color:#94a3b8;">📌 Nenhum filtro aplicado</div>';
        }
        
        return '<div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:4px;">
            <span style="font-size:13px;color:#4a5568;font-weight:600;margin-right:5px;">Filtros ativos:</span>
            ' . implode('', $filtrosAtivos) . '
        </div>';
    }
    
    /**
     * GERA HTML COMPLETO DO RELATÓRIO
     */
    private function gerarHTML($dados) {
        // ... (restante do código HTML igual ao anterior, mantendo os filtros)
        // O código HTML continua igual, usando $this->gerarFiltrosHTML() que já foi corrigido
        // e mantendo os filtros via SESSION
        
        $nomeEscola = $this->configAGT['nome_comercial'] ?? 
                     $this->empresa['nome_fantasia'] ?? 
                     $_SESSION['escola_nome'] ?? 
                     'Complexo Escolar Castelo';
        
        $enderecoEscola = $this->configAGT['endereco'] ?? 
                          $this->empresa['endereco'] ?? 
                          $_SESSION['escola_endereco'] ?? 
                          'Luanda, Angola';
        
        $nifEscola = $this->configAGT['nif'] ?? 
                     $this->empresa['cnpj'] ?? 
                     $_SESSION['escola_nif'] ?? 
                     '5001234567';
        
        $telefoneEscola = $this->configAGT['telefone'] ?? 
                          $this->empresa['telefone'] ?? 
                          $_SESSION['escola_telefone'] ?? 
                          '(+244) 900 000 000';
        
        $numDoc = 'AGT/' . $this->anoAtual . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $temPagamentos = count($dados['todosPagamentos']) > 0;
        $temRegistroAlunos = count($dados['registroDescritivoAlunos']) > 0;
        
        $html = '<!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Registo Descritivo de Pagamentos - ' . htmlspecialchars($nomeEscola) . '</title>
            <style>
                * { margin:0; padding:0; box-sizing:border-box; }
                body { font-family: Arial, "Segoe UI", sans-serif; background:#f5f7fa; padding:15px; color:#1a2332; }
                .container { max-width:1200px; margin:0 auto; background:#fff; padding:35px 40px; border:1px solid #d1d5db; border-radius:4px; }
                
                .header { border-bottom:3px solid #c9a84c; padding-bottom:20px; margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; }
                .header .pais { font-size:11px; font-weight:600; color:#1a237e; text-transform:uppercase; letter-spacing:1px; }
                .header .orgao { font-size:18px; font-weight:800; color:#0d1445; }
                .header .orgao span { color:#c9a84c; }
                .header .doc-info { text-align:right; line-height:1.4; }
                .header .doc-info .titulo { font-size:20px; font-weight:800; color:#0d1445; text-transform:uppercase; letter-spacing:1px; }
                .header .doc-info .num { font-size:12px; color:#4a5568; font-weight:500; }
                .header .doc-info .data { font-size:13px; color:#4a5568; }
                
                .info-escola { background:#f8fafc; padding:12px 20px; border-radius:8px; margin-bottom:25px; border-left:4px solid #c9a84c; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; }
                .info-escola .nome { font-weight:700; font-size:15px; }
                .info-escola .detalhes { font-size:13px; color:#4a5568; }
                
                .resumo { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px,1fr)); gap:10px; margin-bottom:25px; }
                .resumo-item { background:#f8fafc; padding:10px 12px; border-radius:8px; text-align:center; border:1px solid #e2e8f0; }
                .resumo-item .label { font-size:9px; text-transform:uppercase; color:#94a3b8; font-weight:600; letter-spacing:0.5px; }
                .resumo-item .value { font-size:16px; font-weight:800; margin-top:2px; }
                .resumo-item .value.positivo { color:#2ecc71; }
                .resumo-item .value.negativo { color:#e74c3c; }
                .resumo-item .value.destaque { color:#c9a84c; }
                
                .secao { margin-bottom:25px; }
                .secao-titulo { font-size:15px; font-weight:700; padding-bottom:8px; border-bottom:2px solid #e2e8f0; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:5px; }
                .secao-titulo .contador { font-size:12px; color:#94a3b8; font-weight:400; }
                .secao-descricao { font-size:13px; color:#64748b; margin-bottom:10px; padding:10px 15px; background:#f8fafc; border-radius:6px; border-left:3px solid #c9a84c; }
                
                .table-wrapper { overflow-x:auto; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:10px; }
                table { width:100%; border-collapse:collapse; font-size:12px; }
                thead th { background:#f1f5f9; padding:6px 10px; text-align:left; font-weight:600; border-bottom:2px solid #c9a84c; font-size:10px; text-transform:uppercase; letter-spacing:0.5px; position:sticky; top:0; }
                tbody td { padding:6px 10px; border-bottom:1px solid #e2e8f0; }
                tbody tr:hover { background:#f8fafc; }
                tbody tr:nth-child(even) { background:#fafbfc; }
                .text-right { text-align:right; }
                .text-center { text-align:center; }
                .text-nowrap { white-space:nowrap; }
                
                .status { display:inline-block; padding:2px 10px; border-radius:12px; font-size:10px; font-weight:600; }
                .status.confirmado, .status.pago { background:#d1fae5; color:#065f46; }
                .status.pendente { background:#fef3c7; color:#92400e; }
                .status.atrasado { background:#fee2e2; color:#991b1b; }
                .status.cancelado { background:#f1f5f9; color:#64748b; }
                
                .table-total { background:#f8fafc; font-weight:700; border-top:2px solid #1a2332; }
                .table-total td { padding:8px 10px !important; }
                
                .aluno-cabecalho { background:#eef2f7; font-weight:700; }
                .aluno-cabecalho td { padding:8px 10px !important; border-bottom:2px solid #c9a84c; }
                
                .categoria-propina { color:#2ecc71; }
                .categoria-matricula { color:#3498db; }
                .categoria-inscricao { color:#9b59b6; }
                .categoria-exame { color:#e67e22; }
                .categoria-certificado { color:#1abc9c; }
                .categoria-transporte { color:#f39c12; }
                .categoria-outros { color:#95a5a6; }
                
                .badge-tipo { display:inline-block; padding:2px 8px; border-radius:4px; font-size:9px; font-weight:600; }
                .badge-cartao { background:#dbeafe; color:#1e40af; }
                .badge-dinheiro { background:#d1fae5; color:#065f46; }
                .badge-transferencia { background:#fef3c7; color:#92400e; }
                
                .footer { margin-top:30px; padding-top:20px; border-top:2px solid #e2e8f0; display:flex; justify-content:space-between; flex-wrap:wrap; gap:15px; font-size:12px; color:#94a3b8; }
                .selo { display:inline-block; padding:4px 12px; border:2px solid #c9a84c; border-radius:4px; font-size:10px; font-weight:700; color:#c9a84c; text-transform:uppercase; letter-spacing:1px; background:rgba(201,168,76,0.05); }
                .assinatura { text-align:center; padding-top:15px; flex:1; }
                .assinatura .linha { width:180px; border-top:1px solid #1a2332; margin:0 auto 5px; }
                .assinatura .nome { font-weight:600; color:#1a2332; }
                .assinatura .cargo { font-size:11px; color:#4a5568; font-weight:500; }
                .sem-dados { text-align:center; padding:40px 20px; color:#94a3b8; }
                .sem-dados .icon { font-size:48px; display:block; margin-bottom:10px; }
                .info-extra { font-size:11px; color:#94a3b8; text-align:center; padding:10px; border-top:1px solid #e2e8f0; margin-top:20px; }
                
                .filtros-container { background:#f8fafc; padding:20px; border-radius:8px; margin-bottom:25px; border:1px solid #e2e8f0; }
                .filtros-container select, .filtros-container input { background:#fff; }
                
                @media print { 
                    body { background:#fff; padding:10px; } 
                    .container { border:none; box-shadow:none; padding:20px; } 
                    .filtros-container { display:none !important; }
                }
                @media (max-width:768px) { 
                    .container { padding:15px; } 
                    .header { flex-direction:column; text-align:center; } 
                    .header .doc-info { text-align:center; } 
                    .resumo { grid-template-columns:1fr 1fr; } 
                    .info-escola { flex-direction:column; text-align:center; } 
                    table { font-size:10px; }
                    .filtros-container form { grid-template-columns:1fr 1fr; }
                }
                @media (max-width:480px) {
                    .filtros-container form { grid-template-columns:1fr; }
                }
            </style>
        </head>
        <body>
        <div class="container">
            <!-- HEADER -->
            <div class="header">
                <div>
                    <div class="pais">República de Angola</div>
                    <div class="orgao"><span>AGT</span> · Administração Geral Tributária</div>
                </div>
                <div class="doc-info">
                    <div class="titulo">Registo Descritivo de Pagamentos</div>
                    <div class="num">DOC-' . $numDoc . '</div>
                    <div class="data">' . date('d/m/Y H:i:s') . '</div>
                </div>
            </div>
            
            <!-- INFO ESCOLA -->
            <div class="info-escola">
                <div>
                    <div class="nome">' . htmlspecialchars($nomeEscola) . '</div>
                    <div class="detalhes">📍 ' . htmlspecialchars($enderecoEscola) . ' | 📞 ' . htmlspecialchars($telefoneEscola) . '</div>
                </div>
                <div class="detalhes">🔑 NIF: ' . htmlspecialchars($nifEscola) . '</div>
            </div>
            
            <!-- FILTROS -->
            ' . $this->gerarFiltrosHTML() . '
            
            <!-- RESUMO EXECUTIVO -->
            <div class="resumo">';
        
        $resumoItems = [
            ['Total Arrecadado', 'Kz ' . number_format($dados['totalRecebido'], 2, ',', '.'), 'positivo'],
            ['Arrecadado (Mês)', 'Kz ' . number_format($dados['pagamentosMes'], 2, ',', '.'), 'destaque'],
            ['Alunos Ativos', number_format($dados['totalAlunos']), ''],
            ['Pagamentos', number_format($dados['totalPagamentos']), ''],
            ['Confirmados', number_format($dados['totalPagamentosConfirmados']), 'positivo'],
            ['Pendentes', number_format($dados['totalPagamentosPendentes']), 'negativo']
        ];
        
        foreach ($resumoItems as $item) {
            if ($item[1] > 0 || $item[0] == 'Alunos Ativos') {
                $html .= '<div class="resumo-item">
                    <div class="label">' . $item[0] . '</div>
                    <div class="value ' . ($item[2] ?? '') . '">' . $item[1] . '</div>
                </div>';
            }
        }
        
        $html .= '</div>';
        
        // ============================================
        // REGISTRO DESCRITIVO POR ALUNO
        // ============================================
        if ($temRegistroAlunos && $dados['totalPagamentos'] > 0) {
            $totalAlunos = count($dados['resumoAlunosDetalhado']);
            $html .= '<div class="secao">
                <div class="secao-titulo">
                    <span>📋 REGISTRO DESCRITIVO POR ALUNO</span>
                    <span class="contador">' . $totalAlunos . ' alunos | ' . $dados['totalPagamentos'] . ' pagamentos</span>
                </div>
                <div class="secao-descricao">
                    📌 Detalhamento completo de todos os pagamentos por aluno, incluindo ID do pagamento, categoria, tipo de pagamento e status.
                    ' . (!empty($dados['filtros_aplicados']['categoria']) ? '<br><strong>Filtro:</strong> Categoria: ' . htmlspecialchars($dados['filtros_aplicados']['categoria']) : '') . '
                    ' . (!empty($dados['filtros_aplicados']['classe']) ? ' | Classe: ' . htmlspecialchars($dados['filtros_aplicados']['classe']) : '') . '
                    ' . (!empty($dados['filtros_aplicados']['aluno']) ? ' | Aluno: ' . htmlspecialchars($dados['filtros_aplicados']['aluno']) : '') . '
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Pag.</th>
                                <th>Aluno</th>
                                <th>Descrição do Pagamento</th>
                                <th>Tipo Pagamento</th>
                                <th class="text-right">Valor (Kz)</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Mês Ref.</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            $totalGeral = 0;
            
            foreach ($dados['registroDescritivoAlunos'] as $registro) {
                if ($registro['tipo'] == 'aluno_cabecalho') {
                    $html .= '<tr class="aluno-cabecalho">
                        <td colspan="8"><strong>👨‍🎓 ' . htmlspecialchars($registro['aluno']) . '</strong> 
                            <span style="font-weight:400;color:#64748b;font-size:11px;">
                                | Total: ' . $registro['total_pagamentos'] . ' pagamentos 
                                | Kz ' . number_format($registro['total_pago'], 2, ',', '.') . '
                            </span>
                        </td>
                    </tr>';
                    
                } elseif ($registro['tipo'] == 'pagamento_detalhe') {
                    $totalGeral += $registro['valor'];
                    
                    $categoriaClass = 'categoria-outros';
                    if (strpos($registro['categoria'], 'Propina') !== false) $categoriaClass = 'categoria-propina';
                    elseif (strpos($registro['categoria'], 'Matrícula') !== false) $categoriaClass = 'categoria-matricula';
                    elseif (strpos($registro['categoria'], 'Inscrição') !== false) $categoriaClass = 'categoria-inscricao';
                    elseif (strpos($registro['categoria'], 'Exame') !== false) $categoriaClass = 'categoria-exame';
                    elseif (strpos($registro['categoria'], 'Certificado') !== false) $categoriaClass = 'categoria-certificado';
                    elseif (strpos($registro['categoria'], 'Transporte') !== false) $categoriaClass = 'categoria-transporte';
                    
                    $tipoBadge = 'badge-dinheiro';
                    $tipoPag = strtolower($registro['tipo_pagamento'] ?? '');
                    if (strpos($tipoPag, 'cartao') !== false || strpos($tipoPag, 'cartão') !== false) {
                        $tipoBadge = 'badge-cartao';
                    } elseif (strpos($tipoPag, 'transferencia') !== false || strpos($tipoPag, 'transferência') !== false) {
                        $tipoBadge = 'badge-transferencia';
                    }
                    
                    $html .= '<tr>
                        <td style="font-weight:700;color:#c9a84c;">#' . $registro['pagamento_id'] . '</td>
                        <td></td>
                        <td><span style="font-weight:600;">' . htmlspecialchars($registro['emolumento'] ?? 'Pagamento') . '</span>
                            <span style="font-size:10px;color:#94a3b8;display:block;">' . htmlspecialchars($registro['categoria']) . '</span>
                        </td>
                        <td><span class="badge-tipo ' . $tipoBadge . '">' . ucfirst(htmlspecialchars($registro['tipo_pagamento'] ?? 'dinheiro')) . '</span></td>
                        <td class="text-right">' . number_format($registro['valor'], 2, ',', '.') . '</td>
                        <td><span class="status ' . ($registro['status'] ?? 'pendente') . '">' . ucfirst($registro['status'] ?? 'pendente') . '</span></td>
                        <td class="text-nowrap">' . date('d/m/Y', strtotime($registro['data'])) . '</td>
                        <td>' . htmlspecialchars($registro['mes_referencia'] ?? '-') . '</td>
                    </tr>';
                }
            }
            
            $html .= '<tr class="table-total" style="background:#f1f5f9;border-top:3px solid #c9a84c;">
                <td colspan="4" style="text-align:right;font-size:14px;"><strong>TOTAL GERAL</strong></td>
                <td class="text-right" style="font-size:15px;"><strong>Kz ' . number_format($totalGeral, 2, ',', '.') . '</strong></td>
                <td colspan="3"></td>
            </tr>
        </tbody></table></div></div>';
        }
        
        // ============================================
        // SEM DADOS
        // ============================================
        if ($dados['totalPagamentos'] == 0) {
            $html .= '<div class="sem-dados" style="background:#fef9e7;border:2px dashed #f39c12;border-radius:12px;padding:40px 20px;margin:20px 0;">
                <span class="icon" style="font-size:64px;">📭</span>
                <h3 style="color:#1a2332;margin-bottom:10px;">Nenhum pagamento encontrado</h3>
                <p style="color:#64748b;font-size:15px;">Não há registros de pagamentos com os filtros selecionados.</p>
                <p style="color:#94a3b8;font-size:13px;margin-top:10px;">Tente ajustar os filtros ou limpar a seleção.</p>
            </div>';
        }
        
        // ============================================
        // FOOTER
        // ============================================
        $html .= '<div class="footer">
                <div>
                    <span class="selo">Documento Oficial AGT</span>
                    <div style="margin-top:5px;">🔒 Válido para fins tributários</div>
                </div>
                <div>
                    <div>Emissão: ' . date('d/m/Y H:i:s') . '</div>
                    <div style="font-size:11px;color:#94a3b8;">Sistema AGT v2.0</div>
                </div>
            </div>
            
            <div style="display:flex;flex-wrap:wrap;gap:30px;margin-top:20px;padding-top:20px;border-top:1px solid #e2e8f0;">
                <div class="assinatura">
                    <div class="linha"></div>
                    <div class="nome">__________________________________</div>
                    <div class="cargo">Director Financeiro</div>
                </div>
                <div class="assinatura">
                    <div class="linha"></div>
                    <div class="nome">__________________________________</div>
                    <div class="cargo">Coordenador AGT</div>
                </div>
            </div>
            
            <div class="info-extra">
                Relatório completo gerado automaticamente em ' . date('d/m/Y H:i:s') . ' | ID: ' . uniqid() . ' | Versão: 3.0
            </div>
        </div>
        </body>
        </html>';
        
        return $html;
    }
    
    private function salvarRelatorio($html) {
        $timestamp = date('Y-m-d_His');
        $nomeArquivo = 'AGT_Registo_Descritivo_' . $timestamp . '.html';
        $caminhoArquivo = $this->diretorioBase . $this->anoAtual . '/' . $this->mesAtual . '/' . $nomeArquivo;
        file_put_contents($caminhoArquivo, $html);
        
        $nomeFatura = 'Fatura_AGT_' . $timestamp . '.html';
        $caminhoFatura = $this->diretorioFaturas . $this->anoAtual . '/' . $this->mesAtual . '/' . $nomeFatura;
        file_put_contents($caminhoFatura, $html);
        
        $info = [
            'caminho' => $caminhoArquivo,
            'caminho_fatura' => $caminhoFatura,
            'nome' => $nomeArquivo,
            'nome_fatura' => $nomeFatura,
            'ano' => $this->anoAtual,
            'mes' => $this->mesAtual,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => date('Y-m-d'),
            'filtros' => $this->filtros
        ];
        
        $infoFile = $this->diretorioBase . 'ultimo_relatorio_completo.json';
        file_put_contents($infoFile, json_encode($info));
        
        return $info;
    }
    
    public function gerar() {
        try {
            $dados = $this->buscarDadosCompletos();
            $html = $this->gerarHTML($dados);
            $info = $this->salvarRelatorio($html);
            
            error_log("✅ Relatório AGT Descritivo gerado: " . $info['nome']);
            error_log("✅ Fatura salva em: " . $info['caminho_fatura']);
            return $info;
            
        } catch (Exception $e) {
            error_log("❌ Erro ao gerar relatório AGT Descritivo: " . $e->getMessage());
            return null;
        }
    }
}

// ============================================
// EXECUÇÃO
// ============================================

// Iniciar sessão para manter filtros
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpar filtros individuais
if (isset($_GET['limpar_filtro']) && !empty($_GET['limpar_filtro'])) {
    $key = $_GET['limpar_filtro'];
    if (isset($_SESSION['agt_filtros'][$key])) {
        $_SESSION['agt_filtros'][$key] = '';
    }
    // Redirecionar para remover o parâmetro da URL
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $url);
    exit;
}

// Limpar todos os filtros
if (isset($_GET['limpar_filtros'])) {
    $_SESSION['agt_filtros'] = [
        'categoria' => '',
        'classe' => '',
        'aluno' => '',
        'mes_referencia' => '',
        'tipo_pagamento' => '',
        'status' => '',
        'data_inicio' => '',
        'data_fim' => '',
        'order_by' => 'nome',
        'order_dir' => 'ASC'
    ];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

try {
    if (isset($pdo) && $pdo) {
        $relatorio = new RelatorioAGTCompleto($pdo);
        $relatorio->gerar();
    }
} catch (Exception $e) {
    error_log("Erro AGT Completo: " . $e->getMessage());
}
?>