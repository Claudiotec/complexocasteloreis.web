<?php
// ============================================
// modules/escola/alunos/cartoes_escolares.php - Cartões Escolares
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== CONFIGURAÇÃO DO CARTÃO =====
define('VALOR_CARTAO', 1500.00);    // Valor do cartão escolar (verifica apenas este valor)

// ===== DADOS DA EMPRESA =====
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'SoftGest Sistemas';
$enderecoEmpresa = $empresa['endereco'] ?? '';
$telefoneEmpresa = $empresa['telefone'] ?? '';
$emailEmpresa = $empresa['email'] ?? '';
$nifEmpresa = $empresa['cnpj'] ?? '';

// ===== BUSCAR ALUNOS COM VERIFICAÇÃO DE PAGAMENTO (APENAS POR VALOR) =====
$todos_alunos = [];
$alunos_com_pagamento = [];
$alunos_sem_pagamento = [];

try {
    // Buscar todos os alunos ativos
    $todos_alunos = $pdo->query("
        SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
               Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro,
               Nome_do_Pai, Contacto4
        FROM alunos 
        WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
        ORDER BY Classe, TURMA, nome
    ")->fetchAll();
    
    // Verificar pagamento para cada aluno (apenas pelo valor 1500.00)
    foreach ($todos_alunos as $aluno) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   SUM(valor) as total_pago,
                   GROUP_CONCAT(emolumento_id) as emolumentos_ids
            FROM pagamentos 
            WHERE aluno_id = ? 
            AND valor = ?
            AND status = 'confirmado'
        ");
        $stmt->execute([$aluno['id'], VALOR_CARTAO]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            $aluno['total_pago'] = $result['total_pago'];
            $aluno['emolumentos_ids'] = $result['emolumentos_ids'];
            $alunos_com_pagamento[] = $aluno;
        } else {
            $alunos_sem_pagamento[] = $aluno;
        }
    }
    
    // Lista principal = alunos com pagamento
    $alunos = $alunos_com_pagamento;
    
} catch (Exception $e) {
    error_log("Erro ao buscar alunos: " . $e->getMessage());
}

// ===== CALCULAR ANO LETIVO =====
$mes_atual = date('m');
$ano_atual = date('Y');
if ($mes_atual >= 9) {
    $ano_letivo = $ano_atual . '/' . ($ano_atual + 1);
} else {
    $ano_letivo = ($ano_atual - 1) . '/' . $ano_atual;
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
        padding: 4px 12px;
        font-size: 11px;
        border-radius: 6px;
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
    
    .card-aluno {
        background: white;
        border-radius: 12px;
        padding: 15px 18px;
        border: 1px solid #eef2f7;
        margin-bottom: 10px;
        transition: all 0.3s;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .card-aluno:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    }
    
    .card-aluno .info {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .card-aluno .info .id {
        font-weight: 700;
        color: #c9a84c;
        font-size: 14px;
    }
    
    .card-aluno .info .nome {
        font-weight: 600;
        color: #1a2332;
        font-size: 15px;
    }
    
    .card-aluno .info .classe {
        color: #94a3b8;
        font-size: 13px;
    }
    
    .card-aluno .info .valor-pago {
        font-size: 12px;
        color: #065f46;
        background: #d1fae5;
        padding: 2px 10px;
        border-radius: 12px;
        font-weight: 600;
    }
    
    .card-aluno .info .emolumentos {
        font-size: 11px;
        color: #4a5568;
        background: #f1f5f9;
        padding: 2px 8px;
        border-radius: 10px;
    }
    
    .card-aluno .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .filtros {
        background: white;
        padding: 18px 20px;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .filtros .row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    
    .filtros .form-group {
        flex: 1;
        min-width: 150px;
    }
    
    .filtros .form-group label {
        display: block;
        font-weight: 600;
        font-size: 12px;
        color: #4a5568;
        margin-bottom: 4px;
    }
    
    .filtros .form-group select,
    .filtros .form-group input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        background: white;
        transition: border-color 0.3s;
    }
    
    .filtros .form-group select:focus,
    .filtros .form-group input:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    
    .filtros .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .lista-alunos {
        max-height: 500px;
        overflow-y: auto;
        margin-top: 15px;
    }
    
    .lista-alunos::-webkit-scrollbar {
        width: 6px;
    }
    
    .lista-alunos::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .lista-alunos::-webkit-scrollbar-thumb {
        background: #c9a84c;
        border-radius: 10px;
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
        font-size: 18px;
        color: #4a5568;
        margin: 0 0 5px;
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
    
    .badge-sexo {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 10px;
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
    
    .badge-pago {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-nao-pago {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        background: #fee2e2;
        color: #991b1b;
    }
    
    .stats {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .stats .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #4a5568;
    }
    
    .stats .stat-item strong {
        color: #1a2332;
        font-size: 18px;
    }
    
    .stats .stat-item .total {
        color: #c9a84c;
    }
    
    .stats .stat-item .pago {
        color: #2ecc71;
    }
    
    .stats .stat-item .nao-pago {
        color: #e74c3c;
    }
    
    .alert-warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 20px;
    }
    
    .alert-warning .title {
        font-weight: 600;
        font-size: 14px;
        color: #92400e;
        margin-bottom: 5px;
    }
    
    .alert-warning p {
        color: #92400e;
        font-size: 13px;
        margin: 5px 0;
    }
    
    .alert-warning .lista-sem-pagamento {
        max-height: 150px;
        overflow-y: auto;
        margin: 10px 0 0 20px;
        padding: 0;
    }
    
    .alert-warning .lista-sem-pagamento li {
        padding: 3px 0;
        font-size: 13px;
        color: #92400e;
        list-style: none;
        border-bottom: 1px dashed #fde68a;
    }
    
    .alert-warning .lista-sem-pagamento li:last-child {
        border-bottom: none;
    }
    
    .alert-warning .lista-sem-pagamento li strong {
        color: #78350f;
    }
    
    .info-box {
        background: #ecfdf5;
        border: 1px solid #6ee7b7;
        border-radius: 12px;
        padding: 12px 18px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .info-box .label {
        font-weight: 600;
        color: #065f46;
        font-size: 13px;
    }
    
    .info-box .value {
        background: #065f46;
        color: white;
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 700;
    }
    
    .info-box .obs {
        color: #065f46;
        font-size: 12px;
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
        .filtros .row {
            flex-direction: column;
        }
        .filtros .form-group {
            min-width: 100%;
        }
        .filtros .actions {
            width: 100%;
        }
        .filtros .actions .btn {
            flex: 1;
            justify-content: center;
        }
        .card-aluno {
            flex-direction: column;
            align-items: stretch;
            text-align: center;
        }
        .card-aluno .info {
            justify-content: center;
        }
        .card-aluno .actions {
            justify-content: center;
        }
        .acoes-rapidas {
            flex-direction: column;
            align-items: stretch;
        }
        .acoes-rapidas .btn {
            justify-content: center;
        }
        .stats {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>🪪 Cartões Escolares</h1>
        <p class="subtitle">Gerar cartões de identificação escolar (apenas alunos com pagamento de <?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz)</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
        <?php if (count($alunos_sem_pagamento) > 0): ?>
            <a href="relatorio_sem_pagamento.php" class="btn btn-danger" target="_blank">📋 Sem Pagamento</a>
        <?php endif; ?>
    </div>
</div>

<!-- Informações do Cartão -->
<div class="info-box">
    <span class="label">💳 Valor do Cartão:</span>
    <span class="value"><?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz</span>
    <span class="obs">Verificando apenas pagamentos com este valor (qualquer emolumento)</span>
</div>

<!-- Navegação -->
<div class="nav-alunos">
    <a href="index.php">📋 Lista de Alunos</a>
    <a href="add.php">➕ Cadastrar Aluno</a>
    <a href="reconfirmar.php">🔄 Reconfirmação</a>
    <a href="consulta.php">🔍 Consulta</a>
    <a href="relatorio.php">📈 Relatório</a>
    <a href="relatorio_idade.php">📊 Relatório por Idade</a>
    <a href="listas_nominais.php">📋 Listas Nominais</a>
    <a href="cartoes_escolares.php" class="active">🪪 Cartões Escolares</a>
</div>

<!-- Na seção de actions do card-aluno -->
<div class="actions">
    <a href="gerar_cartao.php?id=<?= $aluno['id'] ?>" class="btn btn-sm btn-primary" target="_blank">🪪 Gerar Cartão</a>
    <a href="gerar_cartao_individual.php?id=<?= $aluno['id'] ?>" class="btn btn-sm btn-info" target="_blank">📄 Individual</a>
    <a href="ficha_pagamento.php?id=<?= $aluno['id'] ?>" class="btn btn-sm btn-success" target="_blank">💰 Ficha Pagamento</a>
</div>
                                

<!-- Estatísticas -->
<div class="stats">
    <div class="stat-item">
        <span>📚 Total de Alunos Matriculados:</span>
        <strong class="total"><?= count($todos_alunos) ?></strong>
    </div>
    <div class="stat-item">
        <span>✅ Com Pagamento de <?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz:</span>
        <strong class="pago"><?= count($alunos_com_pagamento) ?></strong>
    </div>
    <div class="stat-item">
        <span>❌ Sem Pagamento:</span>
        <strong class="nao-pago"><?= count($alunos_sem_pagamento) ?></strong>
    </div>
</div>

<!-- Alerta de alunos sem pagamento -->
<?php if (count($alunos_sem_pagamento) > 0): ?>
<div class="alert-warning">
    <div class="title">⚠️ Atenção: Alunos sem pagamento de <?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz</div>
    <p>Os seguintes alunos NÃO possuem pagamento confirmado no valor de <?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz e NÃO poderão gerar o cartão:</p>
    <ul class="lista-sem-pagamento">
        <?php 
        $exibir = array_slice($alunos_sem_pagamento, 0, 20);
        foreach($exibir as $aluno): 
        ?>
        <li>
            <strong>#<?= htmlspecialchars($aluno['id']) ?></strong> - 
            <?= htmlspecialchars($aluno['nome']) ?> - 
            <?= htmlspecialchars($aluno['Classe'] ?? 'Sem Classe') ?>
            (Turma <?= htmlspecialchars($aluno['TURMA'] ?? '-') ?>)
        </li>
        <?php endforeach; ?>
        <?php if (count($alunos_sem_pagamento) > 20): ?>
            <li><em>... e mais <?= count($alunos_sem_pagamento) - 20 ?> alunos</em></li>
        <?php endif; ?>
    </ul>
    <p style="margin-top: 8px; font-size: 13px;">
        💡 Para gerar o cartão, o aluno deve ter um pagamento <strong>confirmado</strong> 
        no valor de <strong><?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz</strong> 
        (em qualquer emolumento).
    </p>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="filtros">
    <form method="GET" class="row" id="formFiltros">
        <div class="form-group">
            <label>Classe</label>
            <select name="classe" onchange="this.form.submit()">
                <option value="">Todas as classes</option>
                <?php 
                $classes = array_unique(array_column($alunos, 'Classe'));
                sort($classes);
                $classe_filtro = $_GET['classe'] ?? '';
                foreach($classes as $c): 
                    if (empty($c)) continue;
                ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= ($classe_filtro == $c) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Turma</label>
            <select name="turma" onchange="this.form.submit()">
                <option value="">Todas as turmas</option>
                <?php 
                $turmas = array_unique(array_column($alunos, 'TURMA'));
                sort($turmas);
                $turma_filtro = $_GET['turma'] ?? '';
                foreach($turmas as $t): 
                    if (empty($t)) continue;
                ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= ($turma_filtro == $t) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="actions">
            <a href="cartoes_escolares.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<!-- Lista de Alunos (APENAS COM PAGAMENTO) -->
<div class="lista-alunos">
    <?php 
    $alunos_filtrados = $alunos;
    if (!empty($classe_filtro)) {
        $alunos_filtrados = array_filter($alunos_filtrados, function($a) use ($classe_filtro) {
            return ($a['Classe'] ?? '') == $classe_filtro;
        });
    }
    if (!empty($turma_filtro)) {
        $alunos_filtrados = array_filter($alunos_filtrados, function($a) use ($turma_filtro) {
            return ($a['TURMA'] ?? '') == $turma_filtro;
        });
    }
    $alunos_filtrados = array_values($alunos_filtrados);
    ?>
    
    <?php if (count($alunos_filtrados) > 0): ?>
        <?php foreach($alunos_filtrados as $aluno): 
            $sexo = $aluno['Sexo'] ?? 'M';
            $sexoClass = $sexo == 'M' ? 'm' : 'f';
            $data_nasc = (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0) ? 
                sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-';
            $total_pago = isset($aluno['total_pago']) ? number_format($aluno['total_pago'], 2, ',', '.') : '0,00';
            $emolumentos = isset($aluno['emolumentos_ids']) ? $aluno['emolumentos_ids'] : '';
        ?>
        <div class="card-aluno">
            <div class="info">
                <span class="id">#<?= htmlspecialchars($aluno['id']) ?></span>
                <span class="nome"><?= htmlspecialchars($aluno['nome']) ?></span>
                <span class="badge-sexo badge-<?= $sexoClass ?>"><?= $sexo ?></span>
                <span class="classe"><?= htmlspecialchars($aluno['Classe'] ?? '-') ?></span>
                <span class="classe">Turma <?= htmlspecialchars($aluno['TURMA'] ?? '-') ?></span>
                <span class="badge-pago">✅ Pago</span>
                <span class="valor-pago">💳 <?= $total_pago ?> Kz</span>
                <?php if (!empty($emolumentos)): ?>
                    <span class="emolumentos">Emolumento ID: <?= $emolumentos ?></span>
                <?php endif; ?>
            </div>
            <div class="actions">
                <a href="gerar_cartao.php?id=<?= $aluno['id'] ?>" class="btn btn-sm btn-primary" target="_blank">🪪 Gerar Cartão</a>
                <a href="gerar_cartao_individual.php?id=<?= $aluno['id'] ?>" class="btn btn-sm btn-info" target="_blank">📄 Individual</a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align: center; padding: 10px; color: #94a3b8; font-size: 13px;">
            Total: <strong><?= count($alunos_filtrados) ?></strong> alunos com pagamento de <?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz
        </div>
    <?php else: ?>
        <div class="empty-state">
            <span class="icon">📭</span>
            <h3>Nenhum aluno com pagamento encontrado</h3>
            <p>Não há alunos com pagamento de <?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz confirmado com os filtros selecionados.</p>
            <?php if (count($alunos_sem_pagamento) > 0): ?>
                <p style="font-size: 13px; color: #92400e; margin-top: 10px;">
                    💡 Existem <?= count($alunos_sem_pagamento) ?> alunos sem pagamento de <?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz.
                    Eles precisam regularizar o pagamento para gerar o cartão.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Ações Rápidas -->
<div class="acoes-rapidas">
    <span style="color: #94a3b8; font-size: 13px; font-weight: 500;">⚡ Ações:</span>
    
    <?php if (!empty($classe_filtro) && count($alunos_filtrados) > 0): ?>
        <a href="gerar_cartoes_classe.php?classe=<?= urlencode($classe_filtro) ?>" class="btn btn-primary" target="_blank">🪪 Gerar Cartões da Classe</a>
    <?php endif; ?>
    
    <?php if (count($alunos_com_pagamento) > 0): ?>
        <a href="gerar_todos_cartoes.php" class="btn btn-success" target="_blank">🪪 Gerar Todos os Cartões (Pagos)</a>
    <?php endif; ?>
    
    <a href="cartao_modelo.php" class="btn btn-warning" target="_blank">📄 Cartão Modelo</a>
    <a href="configurar_cartao.php" class="btn btn-info">⚙️ Configurar Cartão</a>
    
    <?php if (count($alunos_sem_pagamento) > 0): ?>
        <a href="relatorio_sem_pagamento.php" class="btn btn-danger" target="_blank">📋 Relatório Sem Pagamento</a>
    <?php endif; ?>
</div>

<?php include '../includes/footer_escola.php'; ?>