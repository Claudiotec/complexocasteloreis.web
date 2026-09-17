<?php
// ============================================
// modules/escola/alunos/importados_pendencias.php
// Lista de alunos importados pendentes de reconfirmação
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'editar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== CLASSES PRÉ-DEFINIDAS =====
$CLASSES_PRE_DEFINIDAS = [
    'PRÉ' => '1ª',
    'PRE' => '1ª',
    'PreA' => '1ª',
    'PREB' => '1ª',
    '1ª' => '2ª',
    '2ª' => '3ª',
    '3ª' => '4ª',
    '4ª' => '5ª',
    '5ª' => '6ª',
    '6ª' => '7ª',
    '7ª' => '8ª',
    '8ª' => '9ª',
    '9ª' => '10ª',
    '10ª' => '11ª',
    '11ª' => '12ª'
];

// Mapeamento de meses em português para número
$MESES = [
    'Janeiro' => 1, 'Fevereiro' => 2, 'Março' => 3, 'Abril' => 4,
    'Maio' => 5, 'Junho' => 6, 'Julho' => 7, 'Agosto' => 8,
    'Setembro' => 9, 'Outubro' => 10, 'Novembro' => 11, 'Dezembro' => 12
];

$erro = '';
$sucesso = '';
$alunos = [];
$ano_letivo_atual = date('Y');

// ============================================
// FUNÇÃO PARA CALCULAR IDADE
// ============================================
function calcularIdade($dia, $mes, $ano) {
    if (empty($dia) || empty($mes) || empty($ano)) {
        return null;
    }
    
    global $MESES;
    if (isset($MESES[$mes])) {
        $mes_num = $MESES[$mes];
    } else {
        $mes_num = intval($mes);
    }
    
    $data_nascimento = $ano . '-' . str_pad($mes_num, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
    
    try {
        $nascimento = new DateTime($data_nascimento);
        $hoje = new DateTime();
        $idade = $hoje->diff($nascimento);
        return $idade->y;
    } catch (Exception $e) {
        return null;
    }
}

// ============================================
// FUNÇÃO PARA NORMALIZAR CLASSE
// ============================================
function normalizarClasse($classe) {
    $classe = trim($classe);
    $classe = str_replace(' ', '', $classe);
    return $classe;
}

// ============================================
// BUSCAR ALUNOS PENDENTES
// ============================================
try {
    // Verificar se a tabela existe
    $tabela_existe = $pdo->query("SHOW TABLES LIKE 'alunos_ano_anterior'")->rowCount() > 0;
    
    if ($tabela_existe) {
        $stmt = $pdo->query("
            SELECT * FROM alunos_ano_anterior 
            WHERE Situacao_Cadastro = 'Matrícula' 
               OR Situacao_Cadastro IS NULL 
               OR Situacao_Cadastro = ''
               OR Situacao_Cadastro = 'Pendente'
            ORDER BY Classe, nome
        ");
        $alunos = $stmt->fetchAll();
    } else {
        $erro = 'Tabela alunos_ano_anterior não encontrada!';
    }
} catch (Exception $e) {
    $erro = 'Erro ao buscar alunos: ' . $e->getMessage();
}

// ============================================
// PROCESSAR RECONFIRMAÇÃO INDIVIDUAL
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aluno_id'])) {
    $aluno_id = $_POST['aluno_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM alunos_ano_anterior WHERE id = ?");
        $stmt->execute([$aluno_id]);
        $aluno = $stmt->fetch();
        
        if (!$aluno) {
            $erro = 'Aluno não encontrado!';
        } else {
            // Calcular idade
            $idade = calcularIdade($aluno['dia'], $aluno['mes'], $aluno['Ano']);
            
            // Calcular nova classe
            $classe_atual = normalizarClasse($aluno['Classe']);
            $nova_classe = $CLASSES_PRE_DEFINIDAS[$classe_atual] ?? $classe_atual;
            
            if (empty($nova_classe) || $nova_classe == $classe_atual) {
                $nova_classe = $classe_atual;
            }
            
            // Buscar turma para a nova classe
            $turma_nome = '';
            $sala_nome = '';
            $periodo = $aluno['Periodo'] ?? 'Manhã';
            
            try {
                $stmt_turma = $pdo->prepare("SELECT * FROM turmas WHERE classe = ? AND status = 'ativa' LIMIT 1");
                $stmt_turma->execute([$nova_classe]);
                $turma = $stmt_turma->fetch();
                if ($turma) {
                    $turma_nome = $turma['nome'] ?? '';
                    $sala_nome = $turma['sala'] ?? '';
                    if (!empty($turma['turno'])) {
                        $periodo = $turma['turno'];
                    }
                }
            } catch (Exception $e) {}

            // Inserir na tabela principal
            $stmt_insert = $pdo->prepare("
                INSERT INTO alunos (
                    nome, Sexo, dia, mes, Ano, Morada, Cadastro_Transporte,
                    Contacto_do_Aluno, Debilidade, Idade, Naturalidade,
                    Municipio, Província, N_BI, Classe, Nome_do_Pai,
                    Morada3, Contacto4, Ocupacao, Local_de_Trabalho,
                    Nome_da_mae, Contacto_Mae, Data_Matricula,
                    Ocupacao_do_Aluno, Periodo, Data_Emissao_do_BI,
                    Arq_identificação, Situacao_Cadastro, TURMA, SALA,
                    Curso, data_cadastro, usuario_cadastro, ano_letivo
                ) VALUES (
                    :nome, :Sexo, :dia, :mes, :Ano, :Morada, :Cadastro_Transporte,
                    :Contacto_do_Aluno, :Debilidade, :Idade, :Naturalidade,
                    :Municipio, :Província, :N_BI, :Classe, :Nome_do_Pai,
                    :Morada3, :Contacto4, :Ocupacao, :Local_de_Trabalho,
                    :Nome_da_mae, :Contacto_Mae, NOW(),
                    :Ocupacao_do_Aluno, :Periodo, :Data_Emissao_do_BI,
                    :Arq_identificação, 'Confirmação', :TURMA, :SALA,
                    :Curso, NOW(), :usuario_cadastro, :ano_letivo
                )
            ");
            
            $stmt_insert->execute([
                ':nome' => $aluno['nome'],
                ':Sexo' => $aluno['Sexo'],
                ':dia' => $aluno['dia'],
                ':mes' => $aluno['mes'],
                ':Ano' => $aluno['Ano'],
                ':Morada' => $aluno['Morada'],
                ':Cadastro_Transporte' => $aluno['Cadastro_Transporte'] ?? 'Não',
                ':Contacto_do_Aluno' => $aluno['Contacto_do_Aluno'] ?? '',
                ':Debilidade' => $aluno['Debilidade'] ?? 'Nenhuma',
                ':Idade' => $idade,
                ':Naturalidade' => $aluno['Naturalidade'] ?? '',
                ':Municipio' => $aluno['Município'] ?? '',
                ':Província' => $aluno['Província'] ?? '',
                ':N_BI' => $aluno['N_BI'] ?? '',
                ':Classe' => $nova_classe,
                ':Nome_do_Pai' => $aluno['Nome_do_Pai'] ?? '',
                ':Morada3' => $aluno['Morada3'] ?? '',
                ':Contacto4' => $aluno['Contacto4'] ?? '',
                ':Ocupacao' => $aluno['Ocupacao'] ?? '',
                ':Local_de_Trabalho' => $aluno['Local_de_Trabalho'] ?? '',
                ':Nome_da_mae' => $aluno['Nome_da_mae'] ?? '',
                ':Contacto_Mae' => $aluno['Contacto_Mae'] ?? '',
                ':Ocupacao_do_Aluno' => $aluno['Ocupacao_do_Aluno'] ?? '',
                ':Periodo' => $periodo,
                ':Data_Emissao_do_BI' => $aluno['Data_Emissao_do_BI'] ?? null,
                ':Arq_identificação' => $aluno['Arq_identificação'] ?? '',
                ':TURMA' => $turma_nome,
                ':SALA' => $sala_nome,
                ':Curso' => $aluno['Curso'] ?? '',
                ':usuario_cadastro' => $_SESSION['usuario_id'],
                ':ano_letivo' => $ano_letivo_atual
            ]);
            
            // Remover da tabela de origem
            $stmt_delete = $pdo->prepare("DELETE FROM alunos_ano_anterior WHERE id = ?");
            $stmt_delete->execute([$aluno_id]);
            
            $sucesso = "✅ Aluno <strong>" . htmlspecialchars($aluno['nome']) . "</strong> reconfirmado para <strong>$nova_classe</strong> com sucesso!";
            
            // Recarregar a lista
            $stmt = $pdo->query("
                SELECT * FROM alunos_ano_anterior 
                WHERE Situacao_Cadastro = 'Matrícula' 
                   OR Situacao_Cadastro IS NULL 
                   OR Situacao_Cadastro = ''
                   OR Situacao_Cadastro = 'Pendente'
                ORDER BY Classe, nome
            ");
            $alunos = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        $erro = 'Erro ao reconfirmar: ' . $e->getMessage();
    }
}

// ============================================
// PROCESSAR RECONFIRMAÇÃO EM MASSA
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reconfirmar_todos'])) {
    $reconfirmados = 0;
    $erros = 0;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->query("
            SELECT * FROM alunos_ano_anterior 
            WHERE Situacao_Cadastro = 'Matrícula' 
               OR Situacao_Cadastro IS NULL 
               OR Situacao_Cadastro = ''
               OR Situacao_Cadastro = 'Pendente'
        ");
        $alunos_lista = $stmt->fetchAll();
        
        foreach ($alunos_lista as $aluno) {
            try {
                // Calcular idade
                $idade = calcularIdade($aluno['dia'], $aluno['mes'], $aluno['Ano']);
                
                // Calcular nova classe
                $classe_atual = normalizarClasse($aluno['Classe']);
                $nova_classe = $CLASSES_PRE_DEFINIDAS[$classe_atual] ?? $classe_atual;
                
                if (empty($nova_classe) || $nova_classe == $classe_atual) {
                    $nova_classe = $classe_atual;
                }
                
                // Buscar turma
                $turma_nome = '';
                $sala_nome = '';
                $periodo = $aluno['Periodo'] ?? 'Manhã';
                
                try {
                    $stmt_turma = $pdo->prepare("SELECT * FROM turmas WHERE classe = ? AND status = 'ativa' LIMIT 1");
                    $stmt_turma->execute([$nova_classe]);
                    $turma = $stmt_turma->fetch();
                    if ($turma) {
                        $turma_nome = $turma['nome'] ?? '';
                        $sala_nome = $turma['sala'] ?? '';
                        if (!empty($turma['turno'])) {
                            $periodo = $turma['turno'];
                        }
                    }
                } catch (Exception $e) {}
                
                // Inserir na tabela principal
                $stmt_insert = $pdo->prepare("
                    INSERT INTO alunos (
                        nome, Sexo, dia, mes, Ano, Morada, Cadastro_Transporte,
                        Contacto_do_Aluno, Debilidade, Idade, Naturalidade,
                        Municipio, Província, N_BI, Classe, Nome_do_Pai,
                        Morada3, Contacto4, Ocupacao, Local_de_Trabalho,
                        Nome_da_mae, Contacto_Mae, Data_Matricula,
                        Ocupacao_do_Aluno, Periodo, Data_Emissao_do_BI,
                        Arq_identificação, Situacao_Cadastro, TURMA, SALA,
                        Curso, data_cadastro, usuario_cadastro, ano_letivo
                    ) VALUES (
                        :nome, :Sexo, :dia, :mes, :Ano, :Morada, :Cadastro_Transporte,
                        :Contacto_do_Aluno, :Debilidade, :Idade, :Naturalidade,
                        :Municipio, :Província, :N_BI, :Classe, :Nome_do_Pai,
                        :Morada3, :Contacto4, :Ocupacao, :Local_de_Trabalho,
                        :Nome_da_mae, :Contacto_Mae, NOW(),
                        :Ocupacao_do_Aluno, :Periodo, :Data_Emissao_do_BI,
                        :Arq_identificação, 'Confirmação', :TURMA, :SALA,
                        :Curso, NOW(), :usuario_cadastro, :ano_letivo
                    )
                ");
                
                $stmt_insert->execute([
                    ':nome' => $aluno['nome'],
                    ':Sexo' => $aluno['Sexo'],
                    ':dia' => $aluno['dia'],
                    ':mes' => $aluno['mes'],
                    ':Ano' => $aluno['Ano'],
                    ':Morada' => $aluno['Morada'],
                    ':Cadastro_Transporte' => $aluno['Cadastro_Transporte'] ?? 'Não',
                    ':Contacto_do_Aluno' => $aluno['Contacto_do_Aluno'] ?? '',
                    ':Debilidade' => $aluno['Debilidade'] ?? 'Nenhuma',
                    ':Idade' => $idade,
                    ':Naturalidade' => $aluno['Naturalidade'] ?? '',
                    ':Municipio' => $aluno['Município'] ?? '',
                    ':Província' => $aluno['Província'] ?? '',
                    ':N_BI' => $aluno['N_BI'] ?? '',
                    ':Classe' => $nova_classe,
                    ':Nome_do_Pai' => $aluno['Nome_do_Pai'] ?? '',
                    ':Morada3' => $aluno['Morada3'] ?? '',
                    ':Contacto4' => $aluno['Contacto4'] ?? '',
                    ':Ocupacao' => $aluno['Ocupacao'] ?? '',
                    ':Local_de_Trabalho' => $aluno['Local_de_Trabalho'] ?? '',
                    ':Nome_da_mae' => $aluno['Nome_da_mae'] ?? '',
                    ':Contacto_Mae' => $aluno['Contacto_Mae'] ?? '',
                    ':Ocupacao_do_Aluno' => $aluno['Ocupacao_do_Aluno'] ?? '',
                    ':Periodo' => $periodo,
                    ':Data_Emissao_do_BI' => $aluno['Data_Emissao_do_BI'] ?? null,
                    ':Arq_identificação' => $aluno['Arq_identificação'] ?? '',
                    ':TURMA' => $turma_nome,
                    ':SALA' => $sala_nome,
                    ':Curso' => $aluno['Curso'] ?? '',
                    ':usuario_cadastro' => $_SESSION['usuario_id'],
                    ':ano_letivo' => $ano_letivo_atual
                ]);
                
                // Remover da tabela de origem
                $stmt_delete = $pdo->prepare("DELETE FROM alunos_ano_anterior WHERE id = ?");
                $stmt_delete->execute([$aluno['id']]);
                
                $reconfirmados++;
            } catch (Exception $e) {
                $erros++;
            }
        }
        
        $pdo->commit();
        
        if ($reconfirmados > 0) {
            $sucesso = "✅ $reconfirmados alunos reconfirmados com sucesso!";
            if ($erros > 0) {
                $sucesso .= " ⚠️ $erros alunos tiveram erro.";
            }
        } else {
            $erro = "Nenhum aluno foi reconfirmado.";
        }
        
        // Recarregar a lista
        $stmt = $pdo->query("
            SELECT * FROM alunos_ano_anterior 
            WHERE Situacao_Cadastro = 'Matrícula' 
               OR Situacao_Cadastro IS NULL 
               OR Situacao_Cadastro = ''
               OR Situacao_Cadastro = 'Pendente'
            ORDER BY Classe, nome
        ");
        $alunos = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = 'Erro ao reconfirmar todos: ' . $e->getMessage();
    }
}

include '../includes/header_escola.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .page-header .subtitle {
        color: #94a3b8;
        font-size: 14px;
        margin: 2px 0 0;
    }
    
    .stats-bar {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
    }
    
    .stats-bar .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #4a5568;
    }
    
    .stats-bar .stat-item .number {
        font-weight: 700;
        font-size: 18px;
        color: #1a2332;
    }
    
    .stats-bar .stat-item .label {
        color: #94a3b8;
        font-size: 13px;
    }
    
    .btn {
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
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
    
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    
    .btn-primary:hover {
        background: #b8973d;
        color: #1a2332;
    }
    
    .btn-danger {
        background: #e74c3c;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #c0392b;
    }
    
    .btn-info {
        background: #3498db;
        color: #fff;
    }
    
    .btn-info:hover {
        background: #2980b9;
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 11px;
        border-radius: 6px;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        min-width: 1100px;
    }
    
    .table th {
        background: #f8fafc;
        padding: 10px 12px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    
    .table tr:hover {
        background: #fafbfc;
    }
    
    .table tr.pendente {
        background: #fffbeb;
    }
    
    .table tr.pendente:hover {
        background: #fef3c7;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-Matrícula {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .status-Pendente {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-Confirmação {
        background: #d1fae5;
        color: #065f46;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }
    
    .empty-state .icon {
        font-size: 64px;
        display: block;
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        font-size: 20px;
        color: #4a5568;
        margin: 0 0 5px;
    }
    
    .empty-state p {
        color: #94a3b8;
        margin: 0 0 15px;
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    
    .nav-alunos {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 25px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .nav-alunos a {
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
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
    
    .idade-badge {
        background: #e9d5ff;
        color: #6b21a8;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }
    
    .classe-old {
        color: #94a3b8;
        text-decoration: line-through;
        font-size: 12px;
    }
    
    .classe-new {
        color: #c9a84c;
        font-weight: 700;
        font-size: 14px;
    }
    
    .arrow-icon {
        color: #94a3b8;
        margin: 0 4px;
    }
    
    .sem-classe {
        color: #e74c3c;
        font-size: 12px;
    }
    
    .total-info {
        font-size: 13px;
        color: #4a5568;
        padding: 8px 0;
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
        .table {
            font-size: 12px;
            min-width: 800px;
        }
        .table th, .table td {
            padding: 6px 8px;
        }
        .btn-sm {
            font-size: 10px;
            padding: 3px 8px;
        }
        .acoes-rapidas {
            flex-direction: column;
            align-items: stretch;
        }
        .acoes-rapidas .btn {
            justify-content: center;
        }
        .stats-bar {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>📋 Pendências de Reconfirmação</h1>
        <p class="subtitle">Alunos importados do ano anterior aguardando reconfirmação</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Navegação -->
<div class="nav-alunos">
    <a href="index.php">📋 Lista de Alunos</a>
    <a href="add.php">➕ Cadastrar Aluno</a>
    <a href="reconfirmar.php" class="active">🔄 Reconfirmação</a>
    <a href="importados_pendencias.php" class="active">📋 Pendências</a>
    <a href="consulta.php">🔍 Consulta</a>
    <a href="relatorio.php">📈 Relatório</a>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error">❌ <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<!-- Informações -->
<div class="alert alert-info">
    <strong>📌 Lista de alunos importados:</strong>
    <ul style="margin: 8px 0 0 20px; font-size: 13px;">
        <li>Alunos com status <strong>Matrícula</strong> ou <strong>Pendente</strong> aguardam reconfirmação</li>
        <li>A idade é calculada automaticamente com base na data de nascimento</li>
        <li>A nova classe é calculada com base na classe atual (ex: 1ª → 2ª)</li>
        <li>Ao reconfirmar, o aluno é <strong>movido</strong> para a tabela principal</li>
    </ul>
</div>

<!-- Estatísticas -->
<div class="stats-bar">
    <div class="stat-item">
        <span class="number"><?= count($alunos) ?></span>
        <span class="label">Alunos Pendentes</span>
    </div>
    <?php 
    $por_classe = [];
    foreach ($alunos as $a) {
        $classe = $a['Classe'] ?? 'Sem Classe';
        if (!isset($por_classe[$classe])) $por_classe[$classe] = 0;
        $por_classe[$classe]++;
    }
    foreach ($por_classe as $classe => $qtd): 
    ?>
    <div class="stat-item">
        <span class="number"><?= $qtd ?></span>
        <span class="label"><?= htmlspecialchars($classe) ?></span>
    </div>
    <?php endforeach; ?>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Sexo</th>
                <th>Idade</th>
                <th>Classe Atual</th>
                <th>Nova Classe</th>
                <th>Período</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($alunos) > 0): ?>
                <?php foreach($alunos as $a): 
                    $idade = calcularIdade($a['dia'], $a['mes'], $a['Ano']);
                    $classe_atual = normalizarClasse($a['Classe'] ?? '');
                    $nova_classe = $CLASSES_PRE_DEFINIDAS[$classe_atual] ?? $classe_atual;
                    
                    if (empty($nova_classe) || $nova_classe == $classe_atual) {
                        $nova_classe = $classe_atual;
                    }
                    
                    $status = $a['Situacao_Cadastro'] ?? 'Pendente';
                    $row_class = ($status == 'Pendente' || empty($status)) ? 'pendente' : '';
                ?>
                <tr class="<?= $row_class ?>">
                    <td><strong><?= htmlspecialchars($a['id']) ?></strong></td>
                    <td><?= htmlspecialchars($a['nome']) ?></td>
                    <td><?= htmlspecialchars($a['Sexo'] ?? '-') ?></td>
                    <td>
                        <?php if ($idade !== null): ?>
                            <span class="idade-badge"><?= $idade ?> anos</span>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 12px;">não calculada</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($a['Classe'])): ?>
                            <span class="classe-old"><?= htmlspecialchars($a['Classe']) ?></span>
                        <?php else: ?>
                            <span class="sem-classe">⚠️ Sem classe</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($nova_classe) && $nova_classe != $a['Classe']): ?>
                            <span class="classe-new"><?= $nova_classe ?></span>
                        <?php elseif (!empty($nova_classe)): ?>
                            <span style="color: #94a3b8;"><?= $nova_classe ?></span>
                        <?php else: ?>
                            <span class="sem-classe">⚠️ Definir classe</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($a['Periodo'] ?? 'Manhã') ?></td>
                    <td>
                        <span class="status-badge status-<?= str_replace(' ', '_', $status) ?>">
                            <?= htmlspecialchars($status ?: 'Pendente') ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Deseja reconfirmar <strong><?= htmlspecialchars(addslashes($a['nome'])) ?></strong> para a nova classe?')">
                                <input type="hidden" name="aluno_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">✅ Reconf.</button>
                            </form>
                            <a href="editar.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-info">✏️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <span class="icon">🎉</span>
                            <h3>Nenhum aluno pendente!</h3>
                            <p>
                                <?php if (isset($tabela_ex