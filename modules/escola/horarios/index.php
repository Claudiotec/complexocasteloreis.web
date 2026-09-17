<?php
// ============================================
// modules/escola/horarios/index.php - Grade de Horários
// COM: Ordenação por tempo e botão de remover
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

// ===== FILTROS =====
$turma_id = isset($_GET['turma_id']) ? $_GET['turma_id'] : null;

// ===== BUSCAR TURMAS =====
$turmas = [];
try {
    $pdo = conectarBanco();
    $stmt = $pdo->query("SELECT id, nome, classe FROM turmas WHERE status = 'ativa' ORDER BY classe, nome");
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ===== BUSCAR HORÁRIOS =====
$horarios = [];
$erro = '';
try {
    $pdo = conectarBanco();
    $sql = "
        SELECT h.*, 
               t.nome as turma_nome,
               t.classe as turma_classe,
               f.nome as professor_nome,
               f.cargo as professor_cargo,
               tp.nome as tempo_nome,
               tp.ordem as tempo_ordem,
               tp.hora_inicio as tempo_hora_inicio,
               tp.hora_fim as tempo_hora_fim
        FROM horarios h
        LEFT JOIN turmas t ON h.turma_id = t.id
        LEFT JOIN funcionarios f ON h.funcionario_id = f.id
        LEFT JOIN tempos tp ON h.tempo_id = tp.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($turma_id) {
        $sql .= " AND h.turma_id = ?";
        $params[] = $turma_id;
    }
    
    // ORDENAR: por classe, turma, dia, ordem do tempo, hora início
    $sql .= " ORDER BY t.classe, t.nome, 
              FIELD(h.dia_semana, 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'),
              COALESCE(tp.ordem, 999), h.hora_inicio";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $erro = 'Erro ao carregar horários: ' . $e->getMessage();
}

// ===== DIAS DA SEMANA =====
$dias = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

// ===== AGRUPAR HORÁRIOS POR TURMA E DIA =====
$horariosAgrupados = [];
foreach ($horarios as $h) {
    $turmaKey = $h['turma_id'] . '|' . ($h['turma_classe'] ?? '') . '|' . ($h['turma_nome'] ?? '');
    if (!isset($horariosAgrupados[$turmaKey])) {
        $horariosAgrupados[$turmaKey] = [
            'turma_id' => $h['turma_id'],
            'classe' => $h['turma_classe'] ?? $h['classe'] ?? '',
            'nome' => $h['turma_nome'] ?? '',
            'dias' => []
        ];
    }
    
    $dia = $h['dia_semana'] ?? 'Segunda-feira';
    if (!isset($horariosAgrupados[$turmaKey]['dias'][$dia])) {
        $horariosAgrupados[$turmaKey]['dias'][$dia] = [];
    }
    $horariosAgrupados[$turmaKey]['dias'][$dia][] = $h;
}

// ===== ORDENAR CADA DIA POR ORDEM DO TEMPO =====
foreach ($horariosAgrupados as &$turmaData) {
    foreach ($turmaData['dias'] as &$aulas) {
        usort($aulas, function($a, $b) {
            $ordemA = intval($a['tempo_ordem'] ?? 999);
            $ordemB = intval($b['tempo_ordem'] ?? 999);
            if ($ordemA === $ordemB) {
                return strcmp($a['hora_inicio'] ?? '', $b['hora_inicio'] ?? '');
            }
            return $ordemA - $ordemB;
        });
    }
    unset($aulas);
}
unset($turmaData);

// ===== MENSAGENS =====
$sucesso_msg = $_SESSION['sucesso_horario'] ?? '';
$erro_msg = $_SESSION['erro_horario'] ?? '';
unset($_SESSION['sucesso_horario'], $_SESSION['erro_horario']);

// ===== INCLUIR HEADER =====
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
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(201,168,76,0.3); }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-info { background: #3b82f6; color: #fff; }
    .btn-info:hover { background: #2563eb; }
    .btn-print { background: #475569; color: #fff; }
    .btn-print:hover { background: #334155; transform: translateY(-2px); }
    
    .alert {
        padding: 14px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #2ecc71; }
    .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #e74c3c; }
    
    .filtros {
        background: white;
        padding: 18px 20px;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    .filtros .form-group { flex: 1; min-width: 200px; }
    .filtros .form-group label {
        display: block; font-weight: 600; font-size: 12px; color: #4a5568; margin-bottom: 4px;
    }
    .filtros .form-group select {
        width: 100%; padding: 8px 12px; border: 1px solid #d1d5db;
        border-radius: 8px; font-size: 13px; background: white;
    }
    .filtros .form-group select:focus {
        outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    
    .grade-container { overflow-x: auto; margin-top: 20px; }
    .grade-table {
        width: 100%; border-collapse: collapse; font-size: 13px; min-width: 900px;
    }
    .grade-table th {
        background: #1a2332; color: white; padding: 10px 12px; text-align: center;
        font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;
    }
    .grade-table th:first-child { border-radius: 8px 0 0 0; }
    .grade-table th:last-child { border-radius: 0 8px 0 0; }
    .grade-table td {
        padding: 6px;
        border: 1px solid #e2e8f0;
        text-align: center;
        vertical-align: top;
    }
    .grade-table .turma-col {
        font-weight: 700; color: #1a2332; background: #f8fafc;
        text-align: left; min-width: 120px; vertical-align: middle;
    }
    .grade-table .turma-col .classe {
        display: block; font-size: 11px; font-weight: 400; color: #94a3b8;
    }
    
    .aulas-lista {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .grade-cell {
        padding: 5px 7px;
        border-radius: 6px;
        position: relative;
        text-align: left;
        border-left: 3px solid #c9a84c;
        background: #ffffff;
        transition: all 0.2s;
    }
    .grade-cell:hover {
        background: #f8fafc;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }
    .grade-cell.intervalo-cell {
        background: #fef9e7;
        border-left-color: #d4a843;
    }
    .grade-cell .disciplina {
        font-weight: 700;
        color: #0f172a;
        display: block;
        font-size: 12px;
        line-height: 1.2;
        padding-right: 20px;
    }
    .grade-cell .professor {
        font-size: 10px;
        color: #64748b;
        display: block;
        margin-top: 1px;
    }
    .grade-cell .sala {
        display: inline-block;
        background: #e2e8f0;
        padding: 0px 6px;
        border-radius: 8px;
        font-size: 9px;
        font-weight: 600;
        color: #475569;
        margin-top: 2px;
    }
    .grade-cell .intervalo {
        color: #d4a843;
        font-weight: 700;
        font-size: 12px;
    }
    .grade-cell .tempo {
        font-size: 9px;
        color: #94a3b8;
        display: block;
        margin-top: 2px;
        font-weight: 600;
    }
    
    /* BOTÃO REMOVER */
    .btn-remover {
        position: absolute;
        top: 3px;
        right: 3px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fee2e2;
        color: #e74c3c;
        border: none;
        font-size: 11px;
        font-weight: bold;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        line-height: 1;
        padding: 0;
    }
    .btn-remover:hover {
        background: #e74c3c;
        color: white;
        transform: scale(1.15);
    }
    
    .grade-cell.vazio {
        color: #94a3b8;
        font-style: italic;
        font-size: 12px;
        text-align: center;
        border-left: 3px solid #e2e8f0;
        background: transparent;
        padding: 8px;
    }
    
    .empty-state { text-align: center; padding: 50px 20px; color: #94a3b8; }
    .empty-state .icon { font-size: 48px; display: block; margin-bottom: 15px; }
    .empty-state h3 { font-size: 18px; color: #4a5568; margin: 0 0 5px; }
    .empty-state p { font-size: 14px; margin: 0; }
    
    .acoes-rapidas {
        display: flex; gap: 10px; flex-wrap: wrap;
        margin-top: 20px; justify-content: center;
    }
    .resumo {
        margin-top: 20px; padding: 15px 20px; background: #f8fafc;
        border-radius: 8px; border: 1px solid #e2e8f0;
        display: flex; flex-wrap: wrap; gap: 20px;
        justify-content: space-between; align-items: center;
    }
    .resumo .total { font-weight: 700; color: #1a2332; }
    .resumo .badge {
        display: inline-block; padding: 2px 10px; border-radius: 12px;
        font-size: 12px; font-weight: 600;
    }
    .resumo .badge-aula { background: #dbeafe; color: #1e40af; }
    .resumo .badge-intervalo { background: #fef9e7; color: #d4a843; border: 1px solid #d4a843; }
    
    .legenda {
        display: flex; gap: 20px; flex-wrap: wrap; padding: 10px 15px;
        background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;
        margin-bottom: 15px;
    }
    .legenda-item { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #475569; }
    .legenda-item .cor { width: 20px; height: 20px; border-radius: 4px; border: 1px solid #e2e8f0; }
    .legenda-item .cor.aula { background: #ffffff; border-left: 3px solid #c9a84c; }
    .legenda-item .cor.intervalo { background: #fef9e7; border-left: 3px solid #c9a84c; }
    .legenda-item .cor.livre { background: white; }
    
    .modal-overlay {
        display: none;
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 9999;
        align-items: center; justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
        background: white; border-radius: 12px; padding: 25px 30px;
        max-width: 420px; width: 90%; text-align: center;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .modal-box .icon {
        font-size: 42px; margin-bottom: 10px; display: block;
    }
    .modal-box h3 {
        font-size: 18px; color: #1a2332; margin: 0 0 8px;
    }
    .modal-box p {
        font-size: 14px; color: #64748b; margin: 0 0 20px;
    }
    .modal-box .actions {
        display: flex; gap: 10px; justify-content: center;
    }
    .btn-danger { background: #e74c3c; color: #fff; }
    .btn-danger:hover { background: #c0392b; }
    
    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: stretch; }
        .filtros { flex-direction: column; }
        .filtros .form-group { min-width: 100%; }
        .grade-table { font-size: 11px; min-width: 700px; }
        .grade-table td, .grade-table th { padding: 4px; }
        .grade-cell .disciplina { font-size: 11px; }
        .acoes-rapidas { flex-direction: column; align-items: stretch; }
        .acoes-rapidas .btn { justify-content: center; }
        .resumo { flex-direction: column; gap: 10px; text-align: center; }
        .legenda { flex-direction: column; gap: 5px; }
    }
    @media print {
        .no-print, .btn-remover { display: none !important; }
        .grade-table th {
            background: #1a2332 !important; color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .grade-cell.intervalo-cell {
            background: #fef9e7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>⏰ Grade de Horários</h1>
        <p class="subtitle">Visualização da grade horária por turma</p>
    </div>
    <div class="no-print">
        <a href="add.php" class="btn btn-primary">➕ Novo Horário</a>
        <a href="tempos.php" class="btn btn-info">⏱️ Gerenciar Tempos</a>
        <?php if (count($horarios) > 0): ?>
            <a href="print.php<?= $turma_id ? '?turma_id=' . $turma_id : '' ?>" target="_blank" class="btn btn-print">🖨️ Imprimir Grade</a>
        <?php endif; ?>
        <a href="../index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<?php if (!empty($sucesso_msg)): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($sucesso_msg) ?></div>
<?php endif; ?>

<?php if (!empty($erro_msg)): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($erro_msg) ?></div>
<?php endif; ?>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<!-- Filtros -->
<div class="filtros no-print">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%; align-items: flex-end;">
        <div class="form-group">
            <label>Filtrar por Turma</label>
            <select name="turma_id" onchange="this.form.submit()">
                <option value="">Todas as turmas</option>
                <?php foreach($turmas as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($turma_id == $t['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['classe'] ?? '') ?> - <?= htmlspecialchars($t['nome'] ?? '') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex: 0 0 auto;">
            <a href="index.php" class="btn btn-secondary">↺ Limpar</a>
            <?php if (count($horarios) > 0): ?>
                <a href="print.php<?= $turma_id ? '?turma_id=' . $turma_id : '' ?>" target="_blank" class="btn btn-print">🖨️ Imprimir</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Legenda -->
<div class="legenda no-print">
    <div class="legenda-item">
        <span class="cor aula"></span>
        Aula
    </div>
    <div class="legenda-item">
        <span class="cor intervalo"></span>
        Intervalo
    </div>
    <div class="legenda-item">
        <span class="cor livre"></span>
        Horário livre
    </div>
    <div class="legenda-item" style="margin-left: auto; font-size: 11px; color: #94a3b8;">
        <?php if ($turma_id): ?>
            Mostrando grade da turma selecionada
        <?php else: ?>
            Mostrando todas as turmas
        <?php endif; ?>
        <span style="margin-left: 10px; color: #c9a84c; font-weight: 600;">• Ordenado por tempo</span>
    </div>
</div>

<!-- GRADE HORÁRIA -->
<?php if (count($horarios) > 0): ?>
    <div class="grade-container">
        <table class="grade-table">
            <thead>
                <tr>
                    <th>Turma</th>
                    <?php foreach ($dias as $dia): ?>
                        <th class="<?= (date('l', strtotime('now')) == $dia) ? 'hoje' : '' ?>">
                            <?= $dia ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horariosAgrupados as $turmaKey => $turmaData): ?>
                <tr>
                    <td class="turma-col">
                        <?= htmlspecialchars($turmaData['classe'] ?? '') ?>
                        <span class="classe"><?= htmlspecialchars($turmaData['nome'] ?? '') ?></span>
                    </td>
                    <?php foreach ($dias as $dia): 
                        $aulas = $turmaData['dias'][$dia] ?? [];
                    ?>
                        <td>
                            <?php if (count($aulas) > 0): ?>
                                <div class="aulas-lista">
                                    <?php foreach ($aulas as $aula): ?>
                                        <div class="grade-cell <?= $aula['is_intervalo'] == 1 ? 'intervalo-cell' : 'aula-cell' ?>">
                                            <!-- BOTÃO REMOVER -->
                                            <a href="delete.php?id=<?= $aula['id'] ?><?= $turma_id ? '&turma_id=' . $turma_id : '' ?>"
                                               class="btn-remover no-print"
                                               title="Remover este horário"
                                               onclick="return confirmarRemocao(event, '<?= htmlspecialchars(addslashes($aula['disciplina'] ?? 'Horário'), ENT_QUOTES) ?>')">
                                                ✕
                                            </a>
                                            
                                            <?php if ($aula['is_intervalo'] == 1): ?>
                                                <span class="intervalo">☕ <?= htmlspecialchars($aula['disciplina']) ?></span>
                                            <?php else: ?>
                                                <span class="disciplina"><?= htmlspecialchars($aula['disciplina'] ?? '-') ?></span>
                                                <span class="professor">👨‍🏫 <?= htmlspecialchars($aula['professor_nome'] ?? $aula['funcionario_nome'] ?? '-') ?></span>
                                                <?php if (!empty($aula['sala'])): ?>
                                                    <span class="sala">🏠 <?= htmlspecialchars($aula['sala']) ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (!empty($aula['tempo_nome'])): ?>
                                                <span class="tempo">⏱️ <?= htmlspecialchars($aula['tempo_nome']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="grade-cell vazio">—</div>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Resumo -->
    <div class="resumo">
        <div>
            <span class="total">📊 Total:</span>
            <span style="margin-left: 10px; color: #64748b;">
                <?= count($horarios) ?> horários cadastrados
            </span>
        </div>
        <div>
            <span style="margin-right: 15px;">
                <span class="badge badge-aula">📚 Aulas: <?= count(array_filter($horarios, function($h) { return $h['is_intervalo'] == 0; })) ?></span>
            </span>
            <span>
                <span class="badge badge-intervalo">☕ Intervalos: <?= count(array_filter($horarios, function($h) { return $h['is_intervalo'] == 1; })) ?></span>
            </span>
        </div>
        <div style="font-size: 12px; color: #94a3b8;">
            Atualizado em: <?= date('d/m/Y H:i') ?>
        </div>
    </div>
<?php else: ?>
    <div class="empty-state">
        <span class="icon">📭</span>
        <h3>Nenhum horário cadastrado</h3>
        <p>Clique em "Novo Horário" para criar a grade de horários.</p>
        <div class="acoes-rapidas no-print">
            <a href="add.php" class="btn btn-primary">➕ Novo Horário</a>
            <a href="../index.php" class="btn btn-secondary">← Voltar</a>
        </div>
    </div>
<?php endif; ?>

<!-- Ações Rápidas -->
<div class="acoes-rapidas no-print">
    <?php if (count($horarios) > 0): ?>
        <a href="print.php<?= $turma_id ? '?turma_id=' . $turma_id : '' ?>" target="_blank" class="btn btn-print">🖨️ Imprimir Grade Completa</a>
    <?php endif; ?>
    <a href="grade.php" class="btn btn-info">📊 Visualizar Grade Completa</a>
    <a href="tempos.php" class="btn btn-secondary">⏱️ Gerenciar Tempos</a>
</div>

<!-- MODAL DE CONFIRMAÇÃO -->
<div class="modal-overlay" id="modalConfirmar">
    <div class="modal-box">
        <span class="icon">🗑️</span>
        <h3>Remover Horário</h3>
        <p id="modalMensagem">Tem a certeza que deseja remover este horário?</p>
        <div class="actions">
            <button onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
            <a href="#" id="btnConfirmarRemover" class="btn btn-danger">Sim, Remover</a>
        </div>
    </div>
</div>

<script>
    let urlRemover = '';

    function confirmarRemocao(event, disciplina) {
        event.preventDefault();
        urlRemover = event.currentTarget.getAttribute('href');
        
        document.getElementById('modalMensagem').innerHTML = 
            'Tem a certeza que deseja remover <strong>' + disciplina + '</strong> da grade?';
        document.getElementById('btnConfirmarRemover').setAttribute('href', urlRemover);
        document.getElementById('modalConfirmar').classList.add('active');
        
        return false;
    }

    function fecharModal() {
        document.getElementById('modalConfirmar').classList.remove('active');
    }

    // Fechar com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') fecharModal();
    });

    // Fechar ao clicar fora
    document.getElementById('modalConfirmar').addEventListener('click', function(e) {
        if (e.target === this) fecharModal();
    });
</script>

<?php include '../includes/footer_escola.php'; ?>