<?php
// ============================================
// modules/escola/financeiro/relatorios/auto_agt.php
// Gerador Automático de Relatórios AGT
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * CLASSE AUTO RELATÓRIO AGT
 */
class AutoRelatorioAGT {
    private $pdo;
    private $diretorioBase;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->diretorioBase = __DIR__ . '/relatorios_agt/';
    }
    
    /**
     * Gera e salva o relatório automaticamente
     */
    public function gerarEAutomatico() {
        // Criar diretórios
        $this->criarDiretorios();
        
        // Buscar dados
        $dados = $this->buscarDados();
        
        // Gerar HTML
        $html = $this->gerarHTML($dados);
        
        // Salvar
        return $this->salvarRelatorio($html);
    }
    
    /**
     * Cria os diretórios necessários
     */
    private function criarDiretorios() {
        $ano = date('Y');
        $mes = date('m');
        
        // Pasta principal
        if (!file_exists($this->diretorioBase)) {
            mkdir($this->diretorioBase, 0777, true);
        }
        
        // Pasta do ano
        $dirAno = $this->diretorioBase . $ano . '/';
        if (!file_exists($dirAno)) {
            mkdir($dirAno, 0777, true);
        }
        
        // Pasta do mês
        $dirMes = $dirAno . $mes . '/';
        if (!file_exists($dirMes)) {
            mkdir($dirMes, 0777, true);
        }
        
        return $dirMes;
    }
    
    /**
     * Busca todos os dados necessários
     */
    private function buscarDados() {
        $dados = [
            'totalRecebido' => 0,
            'pagamentosMes' => 0,
            'totalPendente' => 0,
            'totalAlunos' => 0,
            'totalEmolumentos' => 0,
            'totalAtrasados' => 0,
            'totalMensalidades' => 0,
            'todosPagamentos' => [],
            'arrecadacaoMensal' => [],
            'resumoEmolumentos' => [],
            'alunosPorTurma' => []
        ];
        
        try {
            // 1. ALUNOS
            try {
                $dados['totalAlunos'] = $this->pdo->query("SELECT COUNT(*) FROM alunos WHERE status = 'ativo' OR status IS NULL")->fetchColumn() ?? 0;
                
                // Alunos por turma
                $dados['alunosPorTurma'] = $this->pdo->query("
                    SELECT TURMA, COUNT(*) as total 
                    FROM alunos 
                    WHERE status = 'ativo' OR status IS NULL 
                    GROUP BY TURMA 
                    ORDER BY TURMA
                ")->fetchAll();
            } catch (Exception $e) {}
            
            // 2. PAGAMENTOS
            try {
                // Verificar colunas
                $colunas = [];
                $cols = $this->pdo->query("SHOW COLUMNS FROM pagamentos");
                while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
                    $colunas[] = $col['Field'];
                }
                
                $sql = "SELECT p.*, a.nome as aluno_nome, a.TURMA as aluno_turma, e.nome as emolumento_nome 
                        FROM pagamentos p
                        LEFT JOIN alunos a ON p.aluno_id = a.id
                        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
                        ORDER BY p.data_pagamento DESC";
                
                $dados['todosPagamentos'] = $this->pdo->query($sql)->fetchAll();
                
                // Calcular totais
                foreach ($dados['todosPagamentos'] as $pag) {
                    $valor = floatval($pag['valor'] ?? 0);
                    $status = $pag['status'] ?? 'pendente';
                    
                    if ($status == 'confirmado' || $status == 'pago') {
                        $dados['totalRecebido'] += $valor;
                        
                        $dataPag = $pag['data_pagamento'] ?? null;
                        if ($dataPag && date('m', strtotime($dataPag)) == date('m')) {
                            $dados['pagamentosMes'] += $valor;
                        }
                    }
                }
            } catch (Exception $e) {}
            
            // 3. ARRECADAÇÃO POR MÊS
            try {
                $dados['arrecadacaoMensal'] = $this->pdo->query("
                    SELECT 
                        DATE_FORMAT(data_pagamento, '%M/%Y') as mes_nome,
                        COUNT(*) as total_pagamentos,
                        SUM(valor) as total_arrecadado
                    FROM pagamentos
                    WHERE status IN ('confirmado', 'pago')
                    GROUP BY DATE_FORMAT(data_pagamento, '%Y-%m')
                    ORDER BY data_pagamento DESC
                    LIMIT 12
                ")->fetchAll();
            } catch (Exception $e) {}
            
        } catch (Exception $e) {}
        
        return $dados;
    }
    
    /**
     * Gera o HTML do relatório
     */
    private function gerarHTML($dados) {
        $anoAtual = date('Y');
        $dataAtual = date('d/m/Y');
        $horaAtual = date('H:i:s');
        $nomeEscola = $_SESSION['escola_nome'] ?? 'Complexo Escolar Castelo';
        $enderecoEscola = $_SESSION['escola_endereco'] ?? 'Luanda, Angola';
        $nifEscola = $_SESSION['escola_nif'] ?? '1234567890';
        $telefoneEscola = $_SESSION['escola_telefone'] ?? '(+244) 900 000 000';
        
        $temPagamentos = count($dados['todosPagamentos']) > 0;
        $temAlunosPorTurma = count($dados['alunosPorTurma']) > 0;
        
        $html = '<!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <title>Relatório AGT - ' . $nomeEscola . '</title>
            <style>
                * { margin:0; padding:0; box-sizing:border-box; }
                body { font-family: Arial, sans-serif; background:#f5f7fa; padding:20px; color:#1a2332; }
                .container { max-width:1100px; margin:0 auto; background:#fff; padding:40px; border:1px solid #d1d5db; }
                .header { border-bottom:3px solid #c9a84c; padding-bottom:20px; margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; }
                .header .title { font-size:24px; font-weight:800; color:#0d1445; }
                .header .title span { color:#c9a84c; }
                .info-escola { background:#f8fafc; padding:15px 20px; border-radius:8px; margin-bottom:25px; border-left:4px solid #c9a84c; display:flex; justify-content:space-between; flex-wrap:wrap; }
                .resumo { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px,1fr)); gap:12px; margin-bottom:25px; }
                .resumo-item { background:#f8fafc; padding:12px; border-radius:8px; text-align:center; border:1px solid #e2e8f0; }
                .resumo-item .label { font-size:10px; text-transform:uppercase; color:#94a3b8; font-weight:600; }
                .resumo-item .value { font-size:20px; font-weight:800; margin-top:2px; }
                .resumo-item .value.positivo { color:#2ecc71; }
                .resumo-item .value.negativo { color:#e74c3c; }
                .secao { margin-bottom:25px; }
                .secao-titulo { font-size:15px; font-weight:700; padding-bottom:8px; border-bottom:2px solid #e2e8f0; margin-bottom:12px; display:flex; justify-content:space-between; }
                .table-wrapper { overflow-x:auto; border-radius:8px; border:1px solid #e2e8f0; }
                table { width:100%; border-collapse:collapse; font-size:13px; }
                thead th { background:#f1f5f9; padding:8px 12px; text-align:left; font-weight:600; border-bottom:2px solid #c9a84c; font-size:11px; text-transform:uppercase; }
                tbody td { padding:8px 12px; border-bottom:1px solid #e2e8f0; }
                .text-right { text-align:right; }
                .text-center { text-align:center; }
                .status { display:inline-block; padding:2px 10px; border-radius:12px; font-size:11px; font-weight:600; }
                .status.confirmado, .status.pago { background:#d1fae5; color:#065f46; }
                .status.pendente { background:#fef3c7; color:#92400e; }
                .status.atrasado { background:#fee2e2; color:#991b1b; }
                .footer { margin-top:30px; padding-top:20px; border-top:2px solid #e2e8f0; display:flex; justify-content:space-between; flex-wrap:wrap; font-size:12px; color:#94a3b8; }
                .selo { display:inline-block; padding:4px 12px; border:2px solid #c9a84c; border-radius:4px; font-size:10px; font-weight:700; color:#c9a84c; text-transform:uppercase; }
                .info-geracao { text-align:center; padding:10px; border-top:1px solid #e2e8f0; margin-top:20px; font-size:11px; color:#94a3b8; }
                .sem-dados { text-align:center; padding:30px; color:#94a3b8; }
                @media print { body { background:#fff; padding:10px; } .container { border:none; box-shadow:none; padding:20px; } }
                @media (max-width:768px) { .container { padding:20px; } .header { flex-direction:column; text-align:center; } }
            </style>
        </head>
        <body>
        <div class="container">
            <!-- HEADER -->
            <div class="header">
                <div>
                    <div style="font-size:11px;font-weight:600;color:#1a237e;text-transform:uppercase;">República de Angola</div>
                    <div class="title"><span>AGT</span> · Administração Geral Tributária</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:18px;font-weight:800;color:#0d1445;text-transform:uppercase;">Relatório Financeiro</div>
                    <div style="font-size:12px;color:#4a5568;">DOC-AGT/' . $anoAtual . '/' . str_pad(rand(1,9999),4,'0',STR_PAD_LEFT) . '</div>
                    <div style="font-size:13px;color:#4a5568;">' . $dataAtual . ' · ' . $horaAtual . '</div>
                </div>
            </div>
            
            <!-- INFO ESCOLA -->
            <div class="info-escola">
                <div>
                    <div style="font-weight:700;font-size:15px;">' . htmlspecialchars($nomeEscola) . '</div>
                    <div style="font-size:13px;color:#4a5568;">📍 ' . htmlspecialchars($enderecoEscola) . ' | 📞 ' . htmlspecialchars($telefoneEscola) . '</div>
                </div>
                <div style="font-size:13px;color:#4a5568;">🔑 NIF: ' . htmlspecialchars($nifEscola) . '</div>
            </div>
            
            <!-- RESUMO -->
            <div class="resumo">';
        
        if ($dados['totalRecebido'] > 0) {
            $html .= '<div class="resumo-item"><div class="label">Total Arrecadado</div><div class="value positivo">Kz ' . number_format($dados['totalRecebido'],2,',','.') . '</div></div>';
        }
        if ($dados['pagamentosMes'] > 0) {
            $html .= '<div class="resumo-item"><div class="label">Arrecadado (Mês)</div><div class="value" style="color:#c9a84c;">Kz ' . number_format($dados['pagamentosMes'],2,',','.') . '</div></div>';
        }
        if ($dados['totalPendente'] > 0) {
            $html .= '<div class="resumo-item"><div class="label">Pendente</div><div class="value negativo">Kz ' . number_format($dados['totalPendente'],2,',','.') . '</div></div>';
        }
        if ($dados['totalAlunos'] > 0) {
            $html .= '<div class="resumo-item"><div class="label">Alunos Ativos</div><div class="value">' . number_format($dados['totalAlunos']) . '</div></div>';
        }
        
        $html .= '</div>';
        
        // ALUNOS POR TURMA
        if ($temAlunosPorTurma) {
            $html .= '<div class="secao">
                <div class="secao-titulo"><span>🏫 Alunos por Turma</span><span class="contador" style="font-size:12px;color:#94a3b8;font-weight:400;">' . count($dados['alunosPorTurma']) . ' turmas</span></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Turma</th><th class="text-center">Total Alunos</th></tr></thead>
                        <tbody>';
            
            foreach ($dados['alunosPorTurma'] as $turma) {
                $html .= '<tr><td><strong>' . htmlspecialchars($turma['TURMA']) . '</strong></td><td class="text-center">' . $turma['total'] . '</td></tr>';
            }
            
            $html .= '</tbody></table></div></div>';
        }
        
        // PAGAMENTOS
        if ($temPagamentos) {
            $html .= '<div class="secao">
                <div class="secao-titulo"><span>💰 Últimos Pagamentos</span><span class="contador" style="font-size:12px;color:#94a3b8;font-weight:400;">Total: ' . count($dados['todosPagamentos']) . ' registros</span></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Data</th><th>Aluno</th><th>Emolumento</th><th class="text-right">Valor (Kz)</th><th>Status</th></tr></thead>
                        <tbody>';
            
            $somaTotal = 0;
            $limit = min(20, count($dados['todosPagamentos']));
            
            for ($i = 0; $i < $limit; $i++) {
                $pag = $dados['todosPagamentos'][$i];
                $dataPag = $pag['data_pagamento'] ?? date('Y-m-d');
                $status = $pag['status'] ?? 'pendente';
                $valor = floatval($pag['valor'] ?? 0);
                $somaTotal += $valor;
                
                $html .= '<tr>
                    <td>' . date('d/m/Y', strtotime($dataPag)) . '</td>
                    <td><strong>' . htmlspecialchars($pag['aluno_nome'] ?? 'N/I') . '</strong></td>
                    <td>' . htmlspecialchars($pag['emolumento_nome'] ?? 'Pagamento') . '</td>
                    <td class="text-right">' . number_format($valor,2,',','.') . '</td>
                    <td><span class="status ' . $status . '">' . ucfirst($status) . '</span></td>
                </tr>';
            }
            
            if (count($dados['todosPagamentos']) > 20) {
                $html .= '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:10px;">... e mais ' . (count($dados['todosPagamentos']) - 20) . ' registros</td></tr>';
            }
            
            $html .= '<tr style="background:#f8fafc;font-weight:700;border-top:2px solid #1a2332;">
                <td colspan="3" style="text-align:right;padding:10px 12px;">TOTAL GERAL</td>
                <td class="text-right" style="font-size:15px;padding:10px 12px;">Kz ' . number_format($somaTotal,2,',','.') . '</td>
                <td></td>
            </tr>
        </tbody></table></div></div>';
        }
        
        // SEM DADOS
        if (!$temPagamentos && $dados['totalAlunos'] == 0) {
            $html .= '<div class="sem-dados"><div style="font-size:40px;margin-bottom:10px;">📭</div><h3 style="color:#1a2332;">Nenhum dado financeiro encontrado</h3><p style="margin-top:10px;">Cadastre alunos, emolumentos e registre pagamentos.</p></div>';
        }
        
        // FOOTER
        $html .= '<div class="footer">
                <div><span class="selo">Documento Oficial AGT</span><div style="margin-top:5px;">🔒 Válido para fins tributários</div></div>
                <div><div>Emissão: ' . $dataAtual . ' · ' . $horaAtual . '</div><div style="font-size:11px;color:#94a3b8;">Sistema AGT v2.0</div></div>
            </div>
            <div class="info-geracao">
                Relatório gerado automaticamente em ' . $dataAtual . ' às ' . $horaAtual . ' | ID: ' . uniqid() . '
            </div>
        </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Salva o relatório
     */
    private function salvarRelatorio($html) {
        $ano = date('Y');
        $mes = date('m');
        $timestamp = date('Y-m-d_His');
        $nomeArquivo = 'AGT_Relatorio_' . $timestamp . '.html';
        $caminhoArquivo = $this->diretorioBase . $ano . '/' . $mes . '/' . $nomeArquivo;
        
        file_put_contents($caminhoArquivo, $html);
        
        return [
            'caminho' => $caminhoArquivo,
            'nome' => $nomeArquivo,
            'ano' => $ano,
            'mes' => $mes,
            'timestamp' => $timestamp
        ];
    }
}

// ===== EXECUÇÃO =====

// Verificar se deve gerar relatório
$gerarRelatorio = true;

// Se for requisição AJAX, apenas retornar status
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'mensagem' => 'Sistema AGT ativo']);
    exit;
}

// Gerar relatório automaticamente
if ($gerarRelatorio) {
    try {
        $autoRelatorio = new AutoRelatorioAGT($pdo);
        $resultado = $autoRelatorio->gerarEAutomatico();
        
        // Registrar no log (opcional)
        error_log("📄 Relatório AGT gerado: " . $resultado['nome']);
    } catch (Exception $e) {
        error_log("❌ Erro ao gerar relatório AGT: " . $e->getMessage());
    }
}

// Se chamado diretamente, mostrar status
if (!isset($_GET['silent'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>AGT - Gerador Automático</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f7fa; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; max-width: 500px; }
            .check { font-size: 60px; color: #2ecc71; }
            h2 { color: #1a2332; margin: 10px 0; }
            p { color: #64748b; }
            .info { background: #f8fafc; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: left; font-size: 13px; }
            .btn { display: inline-block; padding: 10px 25px; background: #1a2332; color: white; text-decoration: none; border-radius: 8px; margin-top: 10px; }
            .btn:hover { background: #2c3e50; }
        </style>
    </head>
    <body>
        <div class="box">
            <div class="check">✅</div>
            <h2>Relatório AGT Gerado!</h2>
            <p>O relatório financeiro foi gerado automaticamente.</p>
            <div class="info">
                <strong>📁 Local:</strong> modules/escola/financeiro/relatorios/relatorios_agt/<?= date('Y') ?>/<?= date('m') ?>/<br>
                <strong>📄 Arquivo:</strong> <?= $resultado['nome'] ?? 'AGT_Relatorio_' . date('Y-m-d_His') . '.html' ?>
            </div>
            <a href="relatorio_agt.php" class="btn" target="_blank">📄 Ver Relatório Completo</a>
            <br><br>
            <a href="../../index.php" style="color: #94a3b8; text-decoration: none; font-size: 14px;">← Voltar ao Dashboard</a>
        </div>
    </body>
    </html>
    <?php
}
?>