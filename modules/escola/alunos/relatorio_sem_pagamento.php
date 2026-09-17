<?php
// ============================================
// modules/escola/alunos/relatorio_sem_pagamento.php - Relatório de Alunos sem Pagamento
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

// Dados da escola
$nome_escola = "COMPLEXO ESCOLAR CASTELO REIS";
$ano_letivo = "2026";

// ============================================
// CRITÉRIO: Aluno com pagamento de cartão se:
// 1. emolumento_id = 56 (Cartão)
// 2. OU valor = 1500.00 (qualquer emolumento que custe 1500)
// ============================================
$alunos_sem_pagamento = [];

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, Sexo, Idade, Classe, Curso, TURMA, Periodo, 
               Nome_do_Pai, Contacto4
        FROM alunos 
        WHERE Situacao_Cadastro IN ('Matrícula', 'Confirmação')
        AND id NOT IN (
            SELECT DISTINCT aluno_id 
            FROM pagamentos 
            WHERE status = 'confirmado'
            AND (
                emolumento_id = 56 
                OR valor = 1500.00
            )
        )
        ORDER BY 
            CAST(Classe AS UNSIGNED) ASC,
            TURMA ASC,
            nome ASC
    ");
    $stmt->execute();
    $alunos_sem_pagamento = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erro ao buscar alunos sem pagamento: " . $e->getMessage());
}

// Agrupar alunos por classe
$alunos_por_classe = [];
foreach ($alunos_sem_pagamento as $aluno) {
    $classe = !empty($aluno['Classe']) ? $aluno['Classe'] : 'PRÉ';
    $turma = !empty($aluno['TURMA']) ? $aluno['TURMA'] : '';
    $key = $classe . ($turma ? ' - ' . $turma : '');
    
    if (!isset($alunos_por_classe[$key])) {
        $alunos_por_classe[$key] = [];
    }
    $alunos_por_classe[$key][] = $aluno;
}

// Obter lista de classes para o filtro
$classes_disponiveis = array_keys($alunos_por_classe);

// Classes selecionadas para impressão
$classes_selecionadas = isset($_GET['classes']) ? $_GET['classes'] : [];
if (empty($classes_selecionadas)) {
    $classes_selecionadas = $classes_disponiveis;
} elseif (!is_array($classes_selecionadas)) {
    $classes_selecionadas = [$classes_selecionadas];
}

include '../includes/header_escola.php';
?>

<style>
    /* Estilos gerais */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f0f2f5;
        color: #1a2332;
        padding: 20px;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
        background: white;
        padding: 20px 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
    }
    
    .page-header h1 {
        font-size: 22px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .page-header .subtitle {
        color: #64748b;
        font-size: 14px;
        margin-top: 4px;
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
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .btn:hover {
        background: #e2e8f0;
    }
    
    .btn-print {
        background: #1a2332;
        color: white;
    }
    
    .btn-print:hover {
        background: #0f172a;
    }
    
    .btn-primary {
        background: #2563eb;
        color: white;
    }
    
    .btn-primary:hover {
        background: #1d4ed8;
    }
    
    .btn-success {
        background: #22c55e;
        color: white;
    }
    
    .btn-success:hover {
        background: #16a34a;
    }
    
    .stats {
        display: flex;
        gap: 30px;
        flex-wrap: wrap;
        margin-bottom: 25px;
        padding: 15px 25px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
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
        font-size: 20px;
    }
    
    .stats .stat-item .total {
        color: #e74c3c;
    }
    
    .stats .stat-item .badge-info {
        background: #dbeafe;
        color: #1e40af;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    /* Filtro de classes */
    .filter-section {
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        padding: 20px 25px;
        margin-bottom: 25px;
    }
    
    .filter-section .filter-title {
        font-size: 15px;
        font-weight: 600;
        color: #1a2332;
        margin-bottom: 12px;
    }
    
    .filter-section .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .filter-section .filter-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #4a5568;
        cursor: pointer;
        padding: 6px 10px;
        border-radius: 6px;
        transition: background 0.2s;
    }
    
    .filter-section .filter-item:hover {
        background: #f1f5f9;
    }
    
    .filter-section .filter-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #2563eb;
    }
    
    .filter-section .filter-item .class-count {
        color: #94a3b8;
        font-size: 11px;
        background: #f1f5f9;
        padding: 1px 8px;
        border-radius: 10px;
        margin-left: 4px;
    }
    
    .filter-section .filter-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        border-top: 1px solid #eef2f7;
        padding-top: 15px;
        margin-top: 5px;
    }
    
    .filter-section .selected-count {
        font-size: 13px;
        color: #64748b;
        margin-left: 10px;
        font-weight: 500;
    }
    
    /* Estilos da tabela por classe */
    .class-section {
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        margin-bottom: 25px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .class-header {
        background: #f8fafc;
        padding: 12px 20px;
        border-bottom: 2px solid #eef2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .class-header h2 {
        font-size: 16px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .class-header .class-count {
        font-size: 13px;
        color: #64748b;
        background: #f1f5f9;
        padding: 2px 14px;
        border-radius: 12px;
    }
    
    .class-header .class-count span {
        font-weight: 700;
        color: #e74c3c;
    }
    
    .table-wrap {
        overflow-x: auto;
        padding: 0;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    
    .table thead {
        background: #f8fafc;
    }
    
    .table th {
        padding: 10px 15px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        color: #4a5568;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #eef2f7;
        white-space: nowrap;
    }
    
    .table td {
        padding: 10px 15px;
        color: #1a2332;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    
    .table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .table tbody tr:hover {
        background: #fafcff;
    }
    
    .badge-nao-pago {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        background: #fee2e2;
        color: #991b1b;
        white-space: nowrap;
    }
    
    .badge-pago {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        background: #dcfce7;
        color: #166534;
        white-space: nowrap;
    }
    
    .id-col {
        font-weight: 700;
        color: #1a2332;
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
    
    .footer-note {
        margin-top: 20px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
        padding: 15px;
        background: white;
        border-radius: 12px;
        border: 1px solid #eef2f7;
    }
    
    .footer-note p {
        margin: 0;
    }

    /* Modal/Visualizador de impressão */
    .print-viewer-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        z-index: 9999;
        overflow-y: auto;
        padding: 20px;
    }
    
    .print-viewer-overlay.active {
        display: block;
    }
    
    .print-viewer-container {
        max-width: 1200px;
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .print-viewer-toolbar {
        background: #1a2332;
        padding: 15px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 10;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .print-viewer-toolbar .title {
        color: white;
        font-size: 16px;
        font-weight: 600;
    }
    
    .print-viewer-toolbar .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .print-viewer-toolbar .actions .btn {
        background: rgba(255,255,255,0.1);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    .print-viewer-toolbar .actions .btn:hover {
        background: rgba(255,255,255,0.2);
    }
    
    .print-viewer-toolbar .actions .btn-close {
        background: #dc2626;
        border-color: #dc2626;
    }
    
    .print-viewer-toolbar .actions .btn-close:hover {
        background: #b91c1c;
    }
    
    .print-viewer-toolbar .actions .btn-print-view {
        background: #2563eb;
        border-color: #2563eb;
    }
    
    .print-viewer-toolbar .actions .btn-print-view:hover {
        background: #1d4ed8;
    }
    
    .print-viewer-content {
        padding: 30px;
        background: white;
    }
    
    /* ============================================
       AUMENTAR FONTE NO VISUALIZADOR DE IMPRESSÃO
       ============================================ */
    #printContent {
        font-size: 14px !important;
        line-height: 1.6 !important;
    }

    #printContent * {
        font-size: 14px !important;
    }

    /* Cabeçalho */
    #printContent h1 {
        font-size: 22px !important;
        font-weight: 700 !important;
    }

    #printContent h2 {
        font-size: 18px !important;
        font-weight: 600 !important;
    }

    #printContent h3 {
        font-size: 16px !important;
        font-weight: 700 !important;
    }

    /* Estatísticas */
    #printContent > div:nth-child(2) div {
        font-size: 14px !important;
    }

    #printContent > div:nth-child(2) strong {
        font-size: 18px !important;
    }

    /* Tabela */
    #printContent table {
        font-size: 13px !important;
        width: 100% !important;
        border-collapse: collapse !important;
    }

    #printContent table th {
        font-size: 11px !important;
        padding: 6px 10px !important;
        text-align: left !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        background: #f5f5f5 !important;
        border-bottom: 2px solid #ddd !important;
    }

    #printContent table td {
        font-size: 13px !important;
        padding: 6px 10px !important;
        border-bottom: 1px solid #eee !important;
    }

    #printContent table td .badge,
    #printContent table td span {
        font-size: 12px !important;
    }

    /* Rodapé e notas */
    #printContent .footer,
    #printContent .note,
    #printContent > div:last-child {
        font-size: 12px !important;
    }

    /* Classe header */
    #printContent .class-header h3 {
        font-size: 16px !important;
        margin: 0 !important;
    }

    #printContent .class-header span {
        font-size: 13px !important;
    }

    #printContent .class-header {
        padding: 8px 15px !important;
    }

    /* Bloco de classe */
    #printContent .class-block {
        margin-bottom: 15px !important;
        border: 1px solid #ddd !important;
        border-radius: 4px !important;
        overflow: hidden !important;
        page-break-inside: avoid !important;
    }

    /* Mensagem de nenhum resultado */
    #printContent .empty-message p {
        font-size: 16px !important;
    }

    #printContent .empty-message p small {
        font-size: 14px !important;
    }

    /* ============================================
       ESTILOS OTIMIZADOS PARA IMPRESSÃO
       ============================================ */
    @media print {
        body {
            background: white !important;
            padding: 0 !important;
            margin: 0 !important;
            font-size: 14px !important;
        }
        
        .no-print {
            display: none !important;
        }
        
        /* Reset de margens para impressão */
        .print-viewer-container {
            max-width: 100% !important;
            margin: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }
        
        .print-viewer-content {
            padding: 15px 20px !important;
        }
        
        .print-viewer-toolbar {
            display: none !important;
        }
        
        /* Forçar fonte 14px na impressão */
        #printContent,
        #printContent * {
            font-size: 14px !important;
        }
        
        #printContent h1 {
            font-size: 22px !important;
        }
        
        #printContent h2 {
            font-size: 18px !important;
        }
        
        #printContent h3 {
            font-size: 16px !important;
        }
        
        #printContent table {
            font-size: 13px !important;
        }
        
        #printContent table th {
            font-size: 11px !important;
        }
        
        #printContent table td {
            font-size: 13px !important;
        }
        
        /* Compactar cabeçalho */
        #printContent > div:first-child {
            padding-bottom: 10px !important;
            margin-bottom: 12px !important;
        }
        
        /* Compactar estatísticas */
        #printContent > div:nth-child(2) {
            padding: 6px 0 10px 0 !important;
            margin-bottom: 12px !important;
        }
        
        /* Classes - mais compacto */
        #printContent .class-block {
            margin-bottom: 10px !important;
            border: 1px solid #ccc !important;
        }
        
        #printContent .class-block .class-header {
            padding: 4px 12px !important;
        }
        
        /* Evitar quebras de página desnecessárias */
        #printContent .class-block {
            page-break-inside: avoid !important;
        }
        
        #printContent table tr {
            page-break-inside: avoid !important;
        }
        
        /* Rodapé */
        #printContent > div:last-child {
            font-size: 11px !important;
            padding-top: 8px !important;
            margin-top: 10px !important;
        }
    }

    @media (max-width: 768px) {
        .table {
            font-size: 12px;
        }
        .table th, .table td {
            padding: 8px 10px;
        }
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .print-viewer-toolbar {
            flex-direction: column;
            align-items: stretch;
        }
        .print-viewer-toolbar .actions {
            justify-content: center;
        }
        .print-viewer-content {
            padding: 15px;
        }
        .filter-section .filter-grid {
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        }
    }
</style>

<!-- ============================================
     CONTEÚDO PRINCIPAL
     ============================================ -->
<div class="page-header no-print">
    <div>
        <h1>📋 Relatório de Alunos Sem Pagamento de Cartão</h1>
        <p class="subtitle">Lista de alunos matriculados que não possuem pagamento confirmado para o cartão escolar</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="cartoes_escolares.php" class="btn">← Voltar</a>
        <button onclick="openPrintViewer()" class="btn btn-print">🖨️ Visualizar para Impressão</button>
    </div>
</div>

<div class="stats no-print">
    <div class="stat-item">
        <span>Total de alunos sem pagamento:</span>
        <strong class="total"><?= count($alunos_sem_pagamento) ?></strong>
    </div>
    <div class="stat-item">
        <span>Classes com pendência:</span>
        <strong><?= count($alunos_por_classe) ?></strong>
    </div>
    <div class="stat-item">
        <span>Critério:</span>
        <strong><span class="badge-info">emolumento_id = 56</span> <span style="color:#94a3b8;">ou</span> <span class="badge-info">valor = 1.500,00</span></strong>
    </div>
</div>

<!-- ============================================
     FILTRO DE CLASSES
     ============================================ -->
<div class="filter-section no-print">
    <div class="filter-title">📌 Selecionar Classes para Impressão</div>
    
    <div class="filter-grid" id="filterGrid">
        <?php foreach ($classes_disponiveis as $classe): ?>
        <label class="filter-item">
            <input type="checkbox" 
                   name="classes[]" 
                   value="<?= htmlspecialchars($classe) ?>" 
                   <?= in_array($classe, $classes_selecionadas) ? 'checked' : '' ?>
                   onchange="updateSelectedCount()">
            <?= htmlspecialchars($classe) ?>
            <span class="class-count"><?= count($alunos_por_classe[$classe]) ?></span>
        </label>
        <?php endforeach; ?>
    </div>
    
    <div class="filter-actions">
        <button onclick="selectAllClasses()" class="btn">✓ Selecionar Todos</button>
        <button onclick="deselectAllClasses()" class="btn">✕ Desmarcar Todos</button>
        <button onclick="applyFilter()" class="btn btn-primary">🔄 Aplicar Filtro</button>
        <span class="selected-count" id="selectedCount">
            <?= count($classes_selecionadas) ?> classes selecionadas
        </span>
    </div>
</div>

<!-- ============================================
     LISTA DE ALUNOS (Filtrada)
     ============================================ -->
<?php if (count($alunos_sem_pagamento) > 0): ?>

    <?php 
    // Filtrar apenas as classes selecionadas
    $classes_filtradas = array_intersect_key($alunos_por_classe, array_flip($classes_selecionadas));
    ?>
    
    <?php if (empty($classes_filtradas)): ?>
        <div class="empty-state no-print">
            <span class="icon">📭</span>
            <h3>Nenhuma classe selecionada</h3>
            <p>Selecione pelo menos uma classe no filtro acima para visualizar os alunos.</p>
        </div>
    <?php else: ?>
    
        <?php foreach ($classes_filtradas as $classe => $alunos): ?>
        <div class="class-section no-print">
            <div class="class-header">
                <h2>📚 Classe: <?= htmlspecialchars($classe) ?></h2>
                <span class="class-count">Total: <span><?= count($alunos) ?></span> aluno(s)</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Nome</th>
                            <th>Sexo</th>
                            <th>Turma</th>
                            <th>Período</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alunos as $aluno): ?>
                        <tr>
                            <td class="id-col"><?= htmlspecialchars($aluno['id']) ?></td>
                            <td><?= htmlspecialchars($aluno['nome']) ?></td>
                            <td><?= htmlspecialchars($aluno['Sexo'] ?? 'M') ?></td>
                            <td><?= htmlspecialchars($aluno['TURMA'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($aluno['Periodo'] ?? 'Manhã') ?></td>
                            <td><span class="badge-nao-pago">❌ Sem pagamento</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    
        <div class="footer-note no-print">
            <p>💡 Esses alunos precisam regularizar o pagamento para terem acesso ao cartão escolar.</p>
            <p style="font-size: 11px; color: #94a3b8; margin-top: 4px;">
                Critério: <strong>emolumento_id = 56</strong> (Cartão) ou <strong>valor = 1.500,00</strong> (qualquer emolumento)
            </p>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="empty-state">
        <span class="icon">🎉</span>
        <h3>Todos os alunos têm pagamento de cartão!</h3>
        <p>Parabéns! Todos os alunos matriculados possuem pagamento confirmado para o cartão escolar.</p>
    </div>
<?php endif; ?>

<!-- ============================================
     VISUALIZADOR DE IMPRESSÃO (MODAL)
     ============================================ -->
<div id="printViewer" class="print-viewer-overlay" onclick="closePrintViewerOutside(event)">
    <div class="print-viewer-container">
        <!-- Toolbar do visualizador -->
        <div class="print-viewer-toolbar no-print">
            <div class="title">🖨️ Visualização para Impressão</div>
            <div class="actions">
                <button onclick="printReport()" class="btn btn-print-view">
                    🖨️ Imprimir
                </button>
                <button onclick="closePrintViewer()" class="btn btn-close">
                    ✕ Fechar
                </button>
            </div>
        </div>
        
        <!-- Conteúdo para impressão com fonte 14px -->
        <div class="print-viewer-content" id="printContent">
            <!-- Cabeçalho -->
            <div style="text-align: center; border-bottom: 2px solid #1a2332; padding-bottom: 15px; margin-bottom: 15px;">
                <h1 style="font-size: 22px; font-weight: 700; color: #1a2332; letter-spacing: 0.5px; margin: 0;">
                    <?= htmlspecialchars($nome_escola) ?>
                </h1>
                <h2 style="font-size: 18px; font-weight: 600; color: #4a5568; margin: 5px 0 0 0;">
                    📋 Relatório de Alunos sem Pagamento de Cartão Escolar
                </h2>
                <p style="font-size: 14px; color: #64748b; margin: 5px 0 0 0;">
                    Ano Letivo <?= $ano_letivo ?> | Gerado em: <?= date('d/m/Y H:i:s') ?>
                </p>
                <p style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                    Critério: emolumento_id = 56 (Cartão) ou valor = 1.500,00
                </p>
            </div>
            
            <!-- Estatísticas -->
            <div style="display: flex; gap: 30px; padding: 8px 0 12px 0; border-bottom: 1px solid #eef2f7; margin-bottom: 15px;">
                <div style="font-size: 14px; color: #4a5568;">
                    <strong style="font-size: 18px; color: #e74c3c;"><?= count($alunos_sem_pagamento) ?></strong> 
                    Total de alunos sem pagamento
                </div>
                <div style="font-size: 14px; color: #4a5568;">
                    <strong style="font-size: 18px; color: #1a2332;"><?= count($classes_selecionadas) ?></strong> 
                    Classes selecionadas
                </div>
            </div>
            
            <!-- Lista de alunos por classe (filtrada) -->
            <?php if (!empty($classes_filtradas)): ?>
                <?php foreach ($classes_filtradas as $classe => $alunos): ?>
                <div style="margin-bottom: 15px; border: 1px solid #eef2f7; border-radius: 4px; overflow: hidden; page-break-inside: avoid;">
                    <div style="background: #f8fafc; padding: 8px 15px; border-bottom: 1px solid #eef2f7; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #1a2332; margin: 0;">
                            📚 Classe: <?= htmlspecialchars($classe) ?>
                        </h3>
                        <span style="font-size: 13px; color: #64748b; background: #f1f5f9; padding: 2px 14px; border-radius: 12px;">
                            Total: <strong style="color: #e74c3c; font-size: 15px;"><?= count($alunos) ?></strong>
                        </span>
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th style="padding: 6px 10px; text-align: left; font-size: 11px; font-weight: 700; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #eef2f7;">#ID</th>
                                <th style="padding: 6px 10px; text-align: left; font-size: 11px; font-weight: 700; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #eef2f7;">Nome</th>
                                <th style="padding: 6px 10px; text-align: left; font-size: 11px; font-weight: 700; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #eef2f7;">Sexo</th>
                                <th style="padding: 6px 10px; text-align: left; font-size: 11px; font-weight: 700; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #eef2f7;">Turma</th>
                                <th style="padding: 6px 10px; text-align: left; font-size: 11px; font-weight: 700; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #eef2f7;">Período</th>
                                <th style="padding: 6px 10px; text-align: left; font-size: 11px; font-weight: 700; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #eef2f7;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunos as $aluno): ?>
                            <tr>
                                <td style="padding: 6px 10px; border-bottom: 1px solid #f1f5f9; font-weight: 600; font-size: 13px; color: #1a2332;"><?= htmlspecialchars($aluno['id']) ?></td>
                                <td style="padding: 6px 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px;"><?= htmlspecialchars($aluno['nome']) ?></td>
                                <td style="padding: 6px 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px;"><?= htmlspecialchars($aluno['Sexo'] ?? 'M') ?></td>
                                <td style="padding: 6px 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px;"><?= htmlspecialchars($aluno['TURMA'] ?? '-') ?></td>
                                <td style="padding: 6px 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px;"><?= htmlspecialchars($aluno['Periodo'] ?? 'Manhã') ?></td>
                                <td style="padding: 6px 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px;">
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: 600; background: #fee2e2; color: #991b1b;">❌ Sem pagamento</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <div style="text-align: center; color: #94a3b8; font-size: 12px; padding: 10px 0 6px 0; border-top: 1px solid #eef2f7; margin-top: 12px;">
                    <p style="margin: 0;">💡 Esses alunos precisam regularizar o pagamento para terem acesso ao cartão escolar.</p>
                    <p style="font-size: 10px; color: #94a3b8; margin-top: 2px;">
                        Critério: emolumento_id = 56 (Cartão) ou valor = 1.500,00
                    </p>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                    <p style="font-size: 16px;">📭 Nenhuma classe selecionada para impressão.</p>
                    <p style="font-size: 14px; margin-top: 6px;">Selecione pelo menos uma classe no filtro.</p>
                </div>
            <?php endif; ?>
            
            <!-- Rodapé -->
            <div style="text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #eef2f7; padding-top: 10px; margin-top: 12px;">
                <?= htmlspecialchars($nome_escola) ?> | Relatório de Alunos sem Pagamento de Cartão | <?= date('d/m/Y') ?>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// FUNÇÕES DO FILTRO
// ============================================

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('input[name="classes[]"]');
    const checked = document.querySelectorAll('input[name="classes[]"]:checked');
    document.getElementById('selectedCount').textContent = checked.length + ' classes selecionadas';
}

function selectAllClasses() {
    document.querySelectorAll('input[name="classes[]"]').forEach(cb => cb.checked = true);
    updateSelectedCount();
}

function deselectAllClasses() {
    document.querySelectorAll('input[name="classes[]"]').forEach(cb => cb.checked = false);
    updateSelectedCount();
}

function applyFilter() {
    const checkboxes = document.querySelectorAll('input[name="classes[]"]:checked');
    const selected = Array.from(checkboxes).map(cb => cb.value);
    
    if (selected.length === 0) {
        alert('Selecione pelo menos uma classe para visualizar.');
        return;
    }
    
    const url = new URL(window.location.href);
    url.searchParams.delete('classes');
    selected.forEach(cls => {
        url.searchParams.append('classes', cls);
    });
    window.location.href = url.toString();
}

// ============================================
// FUNÇÕES DO VISUALIZADOR DE IMPRESSÃO
// ============================================

function openPrintViewer() {
    const checked = document.querySelectorAll('input[name="classes[]"]:checked');
    if (checked.length === 0) {
        alert('Selecione pelo menos uma classe para imprimir.');
        return;
    }
    document.getElementById('printViewer').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closePrintViewer() {
    document.getElementById('printViewer').classList.remove('active');
    document.body.style.overflow = 'auto';
}

function closePrintViewerOutside(event) {
    if (event.target === event.currentTarget) {
        closePrintViewer();
    }
}

function printReport() {
    // Obter o conteúdo do visualizador
    var printContent = document.getElementById('printContent').innerHTML;
    
    // Abrir nova janela para impressão
    var printWindow = window.open('', '_blank', 'width=1024,height=768');
    printWindow.document.write('<!DOCTYPE html><html><head><title>Imprimir Relatório</title>');
    printWindow.document.write('<style>');
    printWindow.document.write(`
        /* Reset para impressão */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            padding: 15px 20px; 
            color: #1a2332; 
            background: white;
            font-size: 14px;
            line-height: 1.6;
        }
        .print-content { max-width: 100%; margin: 0 auto; }
        
        /* Cabeçalho */
        .print-content > div:first-child {
            text-align: center;
            border-bottom: 2px solid #1a2332;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }
        .print-content > div:first-child h1 { 
            font-size: 22px; 
            font-weight: 700;
            margin: 0; 
        }
        .print-content > div:first-child h2 { 
            font-size: 18px; 
            font-weight: 600;
            margin: 5px 0 0 0; 
        }
        .print-content > div:first-child p { 
            font-size: 14px; 
            margin: 5px 0 0 0; 
        }
        
        /* Estatísticas */
        .print-content > div:nth-child(2) {
            display: flex;
            gap: 30px;
            padding: 8px 0 12px 0;
            border-bottom: 1px solid #eef2f7;
            margin-bottom: 15px;
        }
        .print-content > div:nth-child(2) div { 
            font-size: 14px; 
            color: #4a5568;
        }
        .print-content > div:nth-child(2) strong { 
            font-size: 18px; 
        }
        .print-content > div:nth-child(2) .total { color: #e74c3c; }
        
        /* Classes */
        .class-block {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .class-block .class-header {
            background: #f5f5f5;
            padding: 8px 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .class-block .class-header h3 { 
            font-size: 16px; 
            font-weight: 700;
            margin: 0; 
            color: #1a2332;
        }
        .class-block .class-header span { 
            font-size: 13px; 
            color: #64748b;
            background: #f1f5f9;
            padding: 2px 14px;
            border-radius: 12px;
        }
        .class-block .class-header span strong {
            color: #e74c3c;
            font-size: 15px;
        }
        
        /* Tabela */
        .class-block table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .class-block table th {
            padding: 6px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: #f5f5f5;
            border-bottom: 2px solid #ddd;
            color: #4a5568;
        }
        .class-block table td {
            padding: 6px 10px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            color: #1a2332;
        }
        .class-block table tr:last-child td { border-bottom: none; }
        
        /* Status badge */
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Rodapé */
        .footer {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #eef2f7;
            padding-top: 10px;
            margin-top: 12px;
        }
        
        .note {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            padding: 10px 0 6px 0;
            border-top: 1px solid #eef2f7;
            margin-top: 12px;
        }
        
        .empty-message {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .empty-message p { font-size: 16px; }
        .empty-message p small { font-size: 14px; }
        
        @media print {
            body { padding: 10px 15px; }
            .class-block { page-break-inside: avoid; }
            .class-block table tr { page-break-inside: avoid; }
        }
    `);
    printWindow.document.write('</style></head><body>');
    printWindow.document.write('<div class="print-content">');
    printWindow.document.write(printContent);
    printWindow.document.write('</div>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    
    printWindow.onload = function() {
        setTimeout(function() {
            printWindow.print();
        }, 400);
    };
}

// Fechar com ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePrintViewer();
    }
});

// Inicializar contador
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});
</script>

<?php include '../includes/footer_escola.php'; ?>