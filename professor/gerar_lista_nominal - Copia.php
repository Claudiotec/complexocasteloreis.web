<?php
// ============================================
// gerar_lista_nominal.php - Gerar Lista Nominal de Alunos
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Buscar campos da tabela alunos
$campos_alunos = [];
try {
    $stmt = $pdo->query("DESCRIBE alunos");
    $campos_alunos = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Erro ao buscar campos da tabela alunos: " . $e->getMessage());
}

// Buscar turmas e cursos para filtros
$turmas_lista = [];
$cursos_lista = [];
try {
    $turmas_lista = $pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA")->fetchAll(PDO::FETCH_COLUMN);
    $cursos_lista = $pdo->query("SELECT DISTINCT Curso FROM alunos WHERE Curso IS NOT NULL AND Curso != '' ORDER BY Curso")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Processar geração de lista
$lista_nominal = [];
$campos_selecionados = [];
$erro_lista = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_lista'])) {
    $campos_selecionados = $_POST['campos'] ?? [];
    $filtro_turma = $_POST['filtro_turma'] ?? '';
    $filtro_curso = $_POST['filtro_curso'] ?? '';
    $filtro_status = $_POST['filtro_status'] ?? '';
    
    if (empty($campos_selecionados)) {
        $erro_lista = 'Selecione pelo menos um campo para exibir.';
    } else {
        $sql = "SELECT " . implode(', ', array_map(function($c) { return "`$c`"; }, $campos_selecionados)) . " FROM alunos WHERE 1=1";
        $params = [];
        
        if (!empty($filtro_turma)) {
            $sql .= " AND TURMA = ?";
            $params[] = $filtro_turma;
        }
        if (!empty($filtro_curso)) {
            $sql .= " AND Curso = ?";
            $params[] = $filtro_curso;
        }
        if (!empty($filtro_status)) {
            $sql .= " AND status = ?";
            $params[] = $filtro_status;
        }
        
        $sql .= " ORDER BY nome ASC";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $lista_nominal = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $erro_lista = "Erro ao gerar lista: " . $e->getMessage();
        }
    }
}

// Incluir header
include '../includes/header_escola.php';
?>

<style>
    /* ============================================
       ESTILOS DA PÁGINA
       ============================================ */
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
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    .btn-secondary:hover {
        background: #e2e8f0;
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
    .btn-success {
        background: #2ecc71;
        color: #fff;
    }
    .btn-success:hover {
        background: #27ae60;
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
    .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    /* ============================================
       FORMULÁRIO
       ============================================ */
    .form-lista-container {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        margin-bottom: 25px;
    }
    .form-lista-container h2 {
        font-size: 18px;
        color: #1a2332;
        margin: 0 0 5px 0;
    }
    .form-lista-container .desc {
        color: #94a3b8;
        font-size: 13px;
        margin-bottom: 20px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 15px;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        color: #1a2332;
        margin-bottom: 6px;
        font-size: 13px;
    }
    .form-group label .required {
        color: #e74c3c;
    }
    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        background: #fafbfc;
    }
    .form-control:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.15);
    }
    select.form-control {
        appearance: auto;
    }
    .form-text {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 4px;
    }
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border-left: 4px solid #e74c3c;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border-left: 4px solid #2ecc71;
    }
    .alert .icon {
        font-size: 20px;
    }

    /* Campos Checkbox */
    .campos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 6px;
        max-height: 200px;
        overflow-y: auto;
        padding: 10px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    .campos-grid::-webkit-scrollbar {
        width: 5px;
    }
    .campos-grid::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 5px;
    }
    .campos-grid::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 5px;
    }

    .campo-checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 8px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
        font-size: 13px;
        color: #4a5568;
    }
    .campo-checkbox:hover {
        background: #e2e8f0;
    }
    .campo-checkbox input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: #c9a84c;
        cursor: pointer;
        flex-shrink: 0;
    }
    .campo-checkbox input[type="checkbox"]:checked + span {
        color: #1a2332;
        font-weight: 600;
    }

    .btn-campos-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px;
    }
    .btn-campos-actions button {
        padding: 5px 14px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
        color: #4a5568;
    }
    .btn-campos-actions button:hover {
        background: #e2e8f0;
    }

    /* Filtros */
    .filtros-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 15px;
        margin-top: 10px;
    }

    /* Botão Gerar */
    .btn-gerar {
        padding: 12px 30px;
        background: #c9a84c;
        color: #1a2332;
        border: none;
        border-radius: 8px;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
    }
    .btn-gerar:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201,168,76,0.3);
    }

    /* ============================================
       RESULTADOS DA LISTA
       ============================================ */
    .lista-resultados {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        overflow: hidden;
        margin-top: 25px;
    }
    .lista-resultados-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 15px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #eef2f7;
    }
    .lista-resultados-header .info {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 14px;
        color: #4a5568;
    }
    .lista-resultados-header .info .total {
        font-weight: 700;
        color: #1a2332;
    }
    .lista-acoes {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .btn-export {
        padding: 6px 14px;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #fff;
    }
    .btn-export-csv { background: #2ecc71; }
    .btn-export-csv:hover { background: #27ae60; }
    .btn-export-pdf { background: #e74c3c; }
    .btn-export-pdf:hover { background: #c0392b; }
    .btn-export-print { background: #3498db; }
    .btn-export-print:hover { background: #2980b9; }

    .lista-tabela-wrapper {
        overflow-x: auto;
        padding: 0 0 10px 0;
    }
    .lista-tabela {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        min-width: 600px;
    }
    .lista-tabela thead {
        background: #f8fafc;
    }
    .lista-tabela th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 700;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        position: sticky;
        top: 0;
        background: #f8fafc;
        z-index: 5;
    }
    .lista-tabela td {
        padding: 8px 14px;
        border-bottom: 1px solid #f1f5f9;
        color: #1a2332;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .lista-tabela tr:hover td {
        background: #fafbfc;
    }
    .lista-tabela .id-col {
        font-weight: 600;
        color: #c9a84c;
    }
    .lista-tabela .nome-col {
        font-weight: 600;
        color: #1a2332;
    }
    .status-badge {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    .status-ativo { background: #d1fae5; color: #065f46; }
    .status-inativo { background: #fee2e2; color: #991b1b; }
    .status-pendente { background: #fef3c7; color: #92400e; }
    .status-cancelado { background: #e2e8f0; color: #4a5568; }

    .lista-empty {
        text-align: center;
        padding: 50px 20px;
        color: #94a3b8;
    }
    .lista-empty .icon {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
    }
    .lista-empty h3 {
        font-size: 18px;
        color: #4a5568;
        margin: 0 0 5px;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 992px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .filtros-row {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .actions {
            flex-direction: column;
        }
        .actions .btn {
            justify-content: center;
        }
        .filtros-row {
            grid-template-columns: 1fr;
        }
        .campos-grid {
            grid-template-columns: 1fr 1fr;
        }
        .lista-resultados-header {
            flex-direction: column;
            align-items: stretch;
        }
        .lista-acoes {
            justify-content: center;
        }
        .lista-tabela {
            font-size: 11px;
            min-width: 400px;
        }
        .lista-tabela th,
        .lista-tabela td {
            padding: 6px 10px;
        }
    }
    @media (max-width: 480px) {
        .campos-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ============================================
       ESTILOS PARA IMPRESSÃO
       ============================================ */
    @media print {
        .no-print {
            display: none !important;
        }
        .lista-resultados {
            border: none !important;
            box-shadow: none !important;
        }
        .lista-resultados-header {
            background: #f8fafc !important;
            border-bottom: 2px solid #1a2332 !important;
        }
        .lista-tabela th {
            background: #1a2332 !important;
            color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .status-badge {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .page-header {
            page-break-after: avoid !important;
        }
        .lista-tabela tr {
            page-break-inside: avoid !important;
        }
        .lista-tabela-wrapper {
            overflow: visible !important;
        }
    }
</style>

<!-- ============================================
   HEADER DA PÁGINA
   ============================================ -->
<div class="page-header">
    <div>
        <h1>📋 Gerar Lista Nominal</h1>
        <p class="subtitle">Selecione os campos e filtros para gerar a lista de alunos</p>
    </div>
    <div class="actions no-print">
        <a href="dashboard.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- ============================================
   FORMULÁRIO
   ============================================ -->
<div class="form-lista-container no-print">
    <h2>⚙️ Configurações da Lista</h2>
    <p class="desc">Selecione os campos que deseja exibir e aplique filtros para refinar a lista.</p>

    <?php if ($erro_lista): ?>
        <div class="alert alert-danger">
            <span class="icon">❌</span>
            <span><?= htmlspecialchars($erro_lista) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="formListaNominal">
        <!-- SELEÇÃO DE CAMPOS -->
        <div class="form-group">
            <label>📋 Selecione os campos para exibir:</label>
            <div class="campos-grid" id="camposGrid">
                <?php 
                // Campos importantes para exibir
                $campos_importantes = [
                    'id' => 'ID',
                    'nome' => 'Nome',
                    'Sexo' => 'Sexo',
                    'data_nascimento' => 'Data Nasc.',
                    'genero' => 'Gênero',
                    'email' => 'Email',
                    'telefone' => 'Telefone',
                    'endereco' => 'Endereço',
                    'TURMA' => 'Turma',
                    'Curso' => 'Curso',
                    'status' => 'Status',
                    'data_matricula' => 'Data Matrícula',
                    'nome_pai' => 'Nome do Pai',
                    'nome_mae' => 'Nome da Mãe',
                    'telefone_responsavel' => 'Tel. Responsável',
                    'documento' => 'Documento',
                    'Naturalidade' => 'Naturalidade',
                    'Municipio' => 'Município',
                    'Provincia' => 'Província',
                    'Idade' => 'Idade',
                    'Morada' => 'Morada',
                    'Contacto_do_Aluno' => 'Contacto',
                    'Ocupacao_do_Aluno' => 'Ocupação',
                    'Periodo' => 'Período',
                    'N_BI' => 'Nº BI',
                    'Classe' => 'Classe'
                ];
                
                // Campos padrão selecionados
                $default_campos = ['id', 'nome', 'Sexo', 'TURMA', 'Curso', 'status'];
                
                foreach ($campos_importantes as $campo => $label):
                    if (in_array($campo, $campos_alunos)):
                ?>
                    <label class="campo-checkbox">
                        <input type="checkbox" name="campos[]" value="<?= htmlspecialchars($campo) ?>" 
                            <?= in_array($campo, $default_campos) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
            <div class="btn-campos-actions">
                <button type="button" onclick="selecionarTodos()">✅ Selecionar Todos</button>
                <button type="button" onclick="desmarcarTodos()">❌ Desmarcar Todos</button>
                <button type="button" onclick="selecionarPadrao()">📌 Padrão</button>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="form-group">
            <label>🔍 Filtros (opcional):</label>
            <div class="filtros-row">
                <select name="filtro_turma" class="form-control">
                    <option value="">Todas as Turmas</option>
                    <?php foreach ($turmas_lista as $turma): ?>
                        <option value="<?= htmlspecialchars($turma) ?>" <?= ($_POST['filtro_turma'] ?? '') == $turma ? 'selected' : '' ?>>
                            <?= htmlspecialchars($turma) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="filtro_curso" class="form-control">
                    <option value="">Todos os Cursos</option>
                    <?php foreach ($cursos_lista as $curso): ?>
                        <option value="<?= htmlspecialchars($curso) ?>" <?= ($_POST['filtro_curso'] ?? '') == $curso ? 'selected' : '' ?>>
                            <?= htmlspecialchars($curso) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="filtro_status" class="form-control">
                    <option value="">Todos os Status</option>
                    <option value="ativo" <?= ($_POST['filtro_status'] ?? '') == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= ($_POST['filtro_status'] ?? '') == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    <option value="pendente" <?= ($_POST['filtro_status'] ?? '') == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="cancelado" <?= ($_POST['filtro_status'] ?? '') == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
        </div>

        <button type="submit" name="gerar_lista" class="btn-gerar">
            <i class="fas fa-file-pdf"></i> Gerar Lista
        </button>
    </form>
</div>

<!-- ============================================
   RESULTADOS
   ============================================ -->
<?php if (!empty($lista_nominal)): ?>
<div class="lista-resultados">
    <div class="lista-resultados-header">
        <div class="info">
            <span>📊 Total: <span class="total"><?= count($lista_nominal) ?></span> alunos</span>
            <span>|</span>
            <span>📅 Gerado em: <?= date('d/m/Y H:i:s') ?></span>
            <span>|</span>
            <span>👤 <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></span>
        </div>
        <div class="lista-acoes no-print">
            <button onclick="exportarCSV()" class="btn-export btn-export-csv">
                <i class="fas fa-file-csv"></i> CSV
            </button>
            <button onclick="exportarPDF()" class="btn-export btn-export-pdf">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button onclick="imprimirLista()" class="btn-export btn-export-print">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>
    <div class="lista-tabela-wrapper">
        <table class="lista-tabela" id="tabelaListaNominal">
            <thead>
                <tr>
                    <?php foreach ($campos_selecionados as $campo): ?>
                        <th><?= htmlspecialchars(str_replace('_', ' ', $campo)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista_nominal as $aluno): ?>
                    <tr>
                        <?php foreach ($campos_selecionados as $campo): ?>
                            <td>
                                <?php 
                                $valor = $aluno[$campo] ?? '-';
                                if ($campo == 'status') {
                                    $classes = ['ativo' => 'status-ativo', 'inativo' => 'status-inativo', 'pendente' => 'status-pendente', 'cancelado' => 'status-cancelado'];
                                    $classe = $classes[strtolower($valor)] ?? '';
                                    echo '<span class="status-badge ' . $classe . '">' . htmlspecialchars(ucfirst($valor)) . '</span>';
                                } elseif ($campo == 'id') {
                                    echo '<span class="id-col">#' . htmlspecialchars($valor) . '</span>';
                                } elseif ($campo == 'nome') {
                                    echo '<span class="nome-col">' . htmlspecialchars($valor) . '</span>';
                                } else {
                                    echo htmlspecialchars($valor);
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_lista'])): ?>
<div class="lista-resultados">
    <div class="lista-empty">
        <span class="icon">🔍</span>
        <h3>Nenhum aluno encontrado</h3>
        <p>Tente ajustar os filtros ou selecionar outros campos.</p>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
   SCRIPTS (PHP puro - sem JS externo)
   ============================================ -->
<script>
// ============================================
// FUNÇÕES DE SELEÇÃO DE CAMPOS
// ============================================
function selecionarTodos() {
    var checkboxes = document.querySelectorAll('#formListaNominal input[name="campos[]"]');
    checkboxes.forEach(function(cb) {
        cb.checked = true;
    });
}

function desmarcarTodos() {
    var checkboxes = document.querySelectorAll('#formListaNominal input[name="campos[]"]');
    checkboxes.forEach(function(cb) {
        cb.checked = false;
    });
}

function selecionarPadrao() {
    var camposPadrao = ['id', 'nome', 'Sexo', 'TURMA', 'Curso', 'status'];
    var checkboxes = document.querySelectorAll('#formListaNominal input[name="campos[]"]');
    checkboxes.forEach(function(cb) {
        cb.checked = camposPadrao.includes(cb.value);
    });
}

// ============================================
// EXPORTAR CSV
// ============================================
function exportarCSV() {
    var table = document.getElementById('tabelaListaNominal');
    if (!table) return;
    
    var rows = table.querySelectorAll('tr');
    var csv = [];
    
    rows.forEach(function(row) {
        var cols = row.querySelectorAll('th, td');
        var rowData = [];
        cols.forEach(function(col) {
            var text = col.textContent.trim();
            // Remove múltiplos espaços e quebras de linha
            text = text.replace(/\s+/g, ' ').trim();
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(','));
    });
    
    var csvContent = csv.join('\n');
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'lista_nominal_' + new Date().toISOString().slice(0,10) + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ============================================
// EXPORTAR PDF (via window.print)
// ============================================
function exportarPDF() {
    var table = document.getElementById('tabelaListaNominal');
    if (!table) return;
    
    var headers = [];
    var headerCells = table.querySelectorAll('thead th');
    headerCells.forEach(function(th) {
        headers.push(th.textContent.trim());
    });
    
    var rows = [];
    var bodyRows = table.querySelectorAll('tbody tr');
    bodyRows.forEach(function(tr) {
        var rowData = [];
        var cols = tr.querySelectorAll('td');
        cols.forEach(function(td) {
            rowData.push(td.textContent.trim());
        });
        rows.push(rowData);
    });
    
    var htmlContent = `
    <html>
        <head>
            <title>Lista Nominal de Alunos</title>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: Arial, Helvetica, sans-serif; 
                    padding: 30px 20px;
                    background: white;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 3px solid #1a2332;
                    padding-bottom: 15px;
                }
                .header h1 {
                    font-size: 22px;
                    color: #1a2332;
                    margin-bottom: 5px;
                }
                .header .sub {
                    font-size: 13px;
                    color: #666;
                }
                .header .info {
                    font-size: 12px;
                    color: #888;
                    margin-top: 5px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 11px;
                    margin-top: 10px;
                }
                th {
                    background: #1a2332;
                    color: white;
                    padding: 8px 10px;
                    text-align: left;
                    font-weight: 700;
                }
                td {
                    padding: 6px 10px;
                    border-bottom: 1px solid #e2e8f0;
                }
                tr:nth-child(even) {
                    background: #f8fafc;
                }
                .total {
                    text-align: right;
                    font-weight: bold;
                    margin-top: 15px;
                    font-size: 13px;
                    padding-top: 10px;
                    border-top: 2px solid #1a2332;
                }
                .status-badge {
                    display: inline-block;
                    padding: 1px 10px;
                    border-radius: 10px;
                    font-size: 10px;
                    font-weight: 600;
                }
                .status-ativo { background: #d1fae5; color: #065f46; }
                .status-inativo { background: #fee2e2; color: #991b1b; }
                .status-pendente { background: #fef3c7; color: #92400e; }
                .status-cancelado { background: #e2e8f0; color: #4a5568; }
                .footer {
                    margin-top: 20px;
                    text-align: center;
                    font-size: 11px;
                    color: #999;
                    border-top: 1px solid #e2e8f0;
                    padding-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>📋 LISTA NOMINAL DE ALUNOS</h1>
                <div class="sub"><?= htmlspecialchars($_SESSION['escola_nome'] ?? 'Complexo Escolar Castelo Reis') ?></div>
                <div class="info">Gerado em: <?= date('d/m/Y H:i:s') ?> | Total: ${rows.length} alunos</div>
            </div>
            <table>
                <thead>
                    <tr>
                        ${headers.map(h => `<th>${h}</th>`).join('')}
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(row => `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('')}
                </tbody>
            </table>
            <div class="total">Total de alunos: ${rows.length}</div>
            <div class="footer">Documento gerado pelo Sistema de Gestão Escolar</div>
        </body>
    </html>
    `;
    
    var win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(htmlContent);
    win.document.close();
    win.print();
}

// ============================================
// IMPRIMIR LISTA
// ============================================
function imprimirLista() {
    window.print();
}
</script>

<?php include '../includes/footer_escola.php'; ?>