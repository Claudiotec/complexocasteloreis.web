<?php
// ==========================================
// BUSCAR NOTIFICAÇÕES PARA O PROFESSOR
// ==========================================
$notificacoes_nao_lidas = 0;
$ultimas_notificacoes = [];

if (isset($_SESSION['usuario_perfil']) && ($_SESSION['usuario_perfil'] == 'professor' || $_SESSION['usuario_perfil'] == 'docente')) {
    $email = $_SESSION['usuario_email'] ?? '';
    
    try {
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE email = ? AND status = 'ativo' LIMIT 1");
            $stmt->execute([$email]);
            $prof = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($prof) {
                $professor_id = $prof['id'];
                
                $stmt = $pdo->query("SHOW TABLES LIKE 'notificacoes_professor'");
                if ($stmt->rowCount() > 0) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes_professor WHERE professor_id = ? AND lida = 0");
                    $stmt->execute([$professor_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $notificacoes_nao_lidas = $result['total'] ?? 0;
                    
                    $stmt = $pdo->prepare("SELECT * FROM notificacoes_professor WHERE professor_id = ? ORDER BY created_at DESC LIMIT 5");
                    $stmt->execute([$professor_id]);
                    $ultimas_notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar notificações: " . $e->getMessage());
    }
}

// ==========================================
// BUSCAR CAMPOS DA TABELA ALUNOS
// ==========================================
$campos_alunos = [];
try {
    $stmt = $pdo->query("DESCRIBE alunos");
    $campos_alunos = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Erro ao buscar campos da tabela alunos: " . $e->getMessage());
}

// ==========================================
// PROCESSAR GERAÇÃO DE LISTA NOMINAL
// ==========================================
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

// Buscar turmas e cursos para filtros
$turmas_lista = [];
$cursos_lista = [];
try {
    $turmas_lista = $pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA")->fetchAll(PDO::FETCH_COLUMN);
    $cursos_lista = $pdo->query("SELECT DISTINCT Curso FROM alunos WHERE Curso IS NOT NULL AND Curso != '' ORDER BY Curso")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Definir URL base
$base_url = '/softgest_web';
?>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
        <h1>Soft<span>Gest</span></h1>
        <p>Painel Pedagógico</p>
    </div>

    <div class="sidebar-user">
        <div class="avatar"><?= strtoupper(substr($nome, 0, 2)) ?></div>
        <div class="user-info">
            <div class="name"><?= htmlspecialchars($nome) ?></div>
            <div class="email"><?= htmlspecialchars($email) ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Menu</div>
        <a href="dashboard.php" class="<?= $active_page == 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="horarios.php" class="<?= $active_page == 'horarios' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> Horários 
            <span class="badge-nav"><?= $total_horarios ?? 0 ?></span>
        </a>
        <a href="disciplinas.php" class="<?= $active_page == 'disciplinas' ? 'active' : '' ?>">
            <i class="fas fa-book"></i> Disciplinas 
            <span class="badge-nav"><?= $total_disciplinas ?? 0 ?></span>
        </a>
        <a href="turmas.php" class="<?= $active_page == 'turmas' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Turmas 
            <span class="badge-nav"><?= $total_turmas ?? 0 ?></span>
        </a>
        
        <div class="nav-label" style="margin-top:12px;">Avaliações</div>
        
        <a href="/softgest_web/professor/lancamento_notas.php" class="<?= $active_page == 'lancamento_notas' ? 'active' : '' ?>" style="background: linear-gradient(135deg, #2c3e50, #3498db); color: white; border-radius: 8px; margin: 2px 0; padding: 10px 15px; display: flex; align-items: center; justify-content: space-between;">
            <span><i class="fas fa-edit"></i> Lançamento de Notas</span>
            <span class="badge-nav" style="background: #e74c3c; color: white; font-size: 9px; padding: 2px 8px; border-radius: 10px;">Novo</span>
        </a>

        <a href="pautas_trimestrais.php" class="<?= $active_page == 'pautas_trimestrais' ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i> Pautas Trimestrais
        </a>
        <a href="pautas_finais.php" class="<?= $active_page == 'pautas_finais' ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i> Pautas Finais
        </a>
        
        <a href="frequencia.php" class="<?= $active_page == 'frequencia' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i> Frequência
        </a>

        <!-- ===== BOLETINS ===== -->
        <a href="boletins.php" class="<?= $active_page == 'boletins' ? 'active' : '' ?>">
            <i class="fas fa-file-pdf"></i> Boletins
            <span class="badge-nav" style="background: #c9a84c; color: #1a2332; font-size: 9px; padding: 2px 8px; border-radius: 10px;">WhatsApp</span>
        </a>

        <div class="nav-label" style="margin-top:12px;">Relatórios</div>
        
        <!-- ===== GERAR LISTA NOMINAL ===== -->
        <a href="#" class="lista-nominal-toggle" onclick="toggleListaNominal(event); return false;">
            <i class="fas fa-list"></i> Gerar Lista Nominal
            <i class="fas fa-chevron-down" style="font-size:10px;opacity:0.5;margin-left:auto;"></i>
        </a>

        <a href="gerar_lista_nominal.php" class="<?= $active_page == 'gerar_lista' ? 'active' : '' ?>" style="font-size:12px; padding:5px 15px 5px 35px; opacity:0.7;">
            <i class="fas fa-external-link-alt"></i> Visualizar Lista Completa
        </a>

        <!-- DROPDOWN LISTA NOMINAL -->
        <div class="lista-nominal-dropdown" id="listaNominalDropdown" style="display:none;">
            <?php if ($erro_lista): ?>
                <div class="lista-erro">❌ <?= htmlspecialchars($erro_lista) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="formListaNominal">
                <div class="lista-campos">
                    <div class="lista-label">📋 Selecione os campos:</div>
                    <div class="lista-checkbox-group">
                        <?php 
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
                        
                        $default_campos = ['id', 'nome', 'Sexo', 'TURMA', 'Curso', 'status'];
                        
                        foreach ($campos_importantes as $campo => $label):
                            if (in_array($campo, $campos_alunos)):
                        ?>
                            <label class="lista-checkbox">
                                <input type="checkbox" name="campos[]" value="<?= htmlspecialchars($campo) ?>" 
                                    <?= in_array($campo, $default_campos) ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($label) ?></span>
                            </label>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                    <div class="lista-actions">
                        <button type="button" class="btn-select-all" onclick="selecionarTodosCampos()">✅ Selecionar Todos</button>
                        <button type="button" class="btn-deselect-all" onclick="desmarcarTodosCampos()">❌ Desmarcar Todos</button>
                        <button type="button" class="btn-default" onclick="selecionarPadrao()">📌 Padrão</button>
                    </div>
                </div>

                <div class="lista-filtros">
                    <div class="lista-label">🔍 Filtros (opcional):</div>
                    <div class="lista-filtro-row">
                        <select name="filtro_turma" class="lista-select">
                            <option value="">Todas as Turmas</option>
                            <?php foreach ($turmas_lista as $turma): ?>
                                <option value="<?= htmlspecialchars($turma) ?>" <?= ($_POST['filtro_turma'] ?? '') == $turma ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($turma) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="filtro_curso" class="lista-select">
                            <option value="">Todos os Cursos</option>
                            <?php foreach ($cursos_lista as $curso): ?>
                                <option value="<?= htmlspecialchars($curso) ?>" <?= ($_POST['filtro_curso'] ?? '') == $curso ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($curso) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="filtro_status" class="lista-select">
                            <option value="">Todos os Status</option>
                            <option value="ativo" <?= ($_POST['filtro_status'] ?? '') == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= ($_POST['filtro_status'] ?? '') == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                            <option value="pendente" <?= ($_POST['filtro_status'] ?? '') == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="cancelado" <?= ($_POST['filtro_status'] ?? '') == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="gerar_lista" class="btn-gerar-lista">
                    <i class="fas fa-file-pdf"></i> Gerar Lista
                </button>
            </form>

            <?php if (!empty($lista_nominal)): ?>
            <div class="lista-resultados">
                <div class="lista-resultados-header">
                    <span><i class="fas fa-users"></i> Total: <strong><?= count($lista_nominal) ?></strong> alunos</span>
                    <span style="font-size:10px;color:#94a3b8;"><?= date('d/m/Y H:i') ?></span>
                    <div class="lista-resultados-actions">
                        <button onclick="exportarCSV()" class="btn-export-csv" title="Exportar CSV">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                        <button onclick="exportarPDF()" class="btn-export-pdf" title="Exportar PDF">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button onclick="imprimirLista()" class="btn-export-print" title="Imprimir">
                            <i class="fas fa-print"></i>
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
                                                echo '<span style="color:#c9a84c;font-weight:700;">#' . htmlspecialchars($valor) . '</span>';
                                            } elseif ($campo == 'nome') {
                                                echo '<span style="font-weight:600;color:#1a2332;">' . htmlspecialchars($valor) . '</span>';
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
                <div class="lista-empty">
                    <i class="fas fa-search"></i>
                    <span>Nenhum aluno encontrado com os filtros selecionados.</span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="nav-label" style="margin-top:12px;">Configurações</div>
        <a href="perfil.php"><i class="fas fa-user-cog"></i> Meu Perfil</a>
        
        <!-- ===== NOTIFICAÇÕES ===== -->
        <?php if ($_SESSION['usuario_perfil'] == 'professor' || $_SESSION['usuario_perfil'] == 'docente'): ?>
        <a href="#" class="notifications-toggle" onclick="toggleNotifications(event); return false;">
            <i class="fas fa-bell"></i> Notificações 
            <?php if ($notificacoes_nao_lidas > 0): ?>
                <span class="badge-nav" id="notificationBadge" style="background:#ef4444;color:white;"><?= $notificacoes_nao_lidas ?></span>
            <?php else: ?>
                <span class="badge-nav" id="notificationBadge" style="background:#ef4444;color:white;display:none;">0</span>
            <?php endif; ?>
            <i class="fas fa-chevron-down" style="font-size:10px;opacity:0.5;margin-left:auto;"></i>
        </a>

        <div class="notifications-dropdown" id="notificationsDropdown" style="display:none;">
            <?php if (empty($ultimas_notificacoes)): ?>
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <span>Nenhuma notificação</span>
                </div>
            <?php else: ?>
                <?php foreach ($ultimas_notificacoes as $notif): ?>
                    <div class="notification-item <?= $notif['lida'] ? '' : 'unread' ?>" 
                         onclick="marcarNotificacaoLida(<?= $notif['id'] ?>)">
                        <div class="notification-icon" style="background: <?= $notif['cor'] == 'danger' ? 'rgba(231,76,60,0.15)' : ($notif['cor'] == 'success' ? 'rgba(46,204,113,0.15)' : ($notif['cor'] == 'warning' ? 'rgba(243,156,18,0.15)' : 'rgba(201,168,76,0.15)')) ?>;">
                            <?= $notif['icone'] ?? '📢' ?>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?= htmlspecialchars($notif['titulo']) ?></div>
                            <div class="notification-message"><?= htmlspecialchars(substr($notif['mensagem'], 0, 50)) ?><?= strlen($notif['mensagem']) > 50 ? '...' : '' ?></div>
                            <div class="notification-time"><?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></div>
                        </div>
                        <?php if (!$notif['lida']): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($notificacoes_nao_lidas > 0): ?>
                    <div class="notification-footer">
                        <button onclick="marcarTodasLidas()" class="btn-mark-all">
                            Marcar todas como lidas
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</aside>

<!-- ============================================
   SCRIPTS INLINE
   ============================================ -->
<script>
// ============================================
// TOGGLE LISTA NOMINAL
// ============================================
function toggleListaNominal(event) {
    event.preventDefault();
    event.stopPropagation();
    
    var dropdown = document.getElementById('listaNominalDropdown');
    var notificationsDropdown = document.getElementById('notificationsDropdown');
    
    if (notificationsDropdown) {
        notificationsDropdown.style.display = 'none';
    }
    
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        dropdown.style.display = 'block';
    } else {
        dropdown.style.display = 'none';
    }
}

// ============================================
// TOGGLE NOTIFICAÇÕES
// ============================================
function toggleNotifications(event) {
    event.preventDefault();
    event.stopPropagation();
    
    var dropdown = document.getElementById('notificationsDropdown');
    var listaDropdown = document.getElementById('listaNominalDropdown');
    
    if (listaDropdown) {
        listaDropdown.style.display = 'none';
    }
    
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        dropdown.style.display = 'block';
    } else {
        dropdown.style.display = 'none';
    }
}

// ============================================
// SELEÇÃO DE CAMPOS
// ============================================
function selecionarTodosCampos() {
    var checkboxes = document.querySelectorAll('#formListaNominal input[name="campos[]"]');
    checkboxes.forEach(function(cb) {
        cb.checked = true;
    });
}

function desmarcarTodosCampos() {
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
            var text = col.textContent.trim().replace(/\s+/g, ' ');
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(','));
    });
    
    var csvContent = csv.join('\n');
    var blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'lista_nominal_' + new Date().toISOString().slice(0,10) + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ============================================
// EXPORTAR PDF
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
                body { font-family: Arial, sans-serif; padding: 30px; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #1a2332; padding-bottom: 15px; }
                .header h1 { font-size: 20px; color: #1a2332; }
                .header .sub { font-size: 13px; color: #666; }
                .header .info { font-size: 12px; color: #888; margin-top: 5px; }
                table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 10px; }
                th { background: #1a2332; color: white; padding: 8px 10px; text-align: left; }
                td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; }
                tr:nth-child(even) { background: #f8fafc; }
                .total { text-align: right; font-weight: bold; margin-top: 15px; padding-top: 10px; border-top: 2px solid #1a2332; }
                .status-badge { display: inline-block; padding: 1px 10px; border-radius: 10px; font-size: 10px; font-weight: 600; }
                .status-ativo { background: #d1fae5; color: #065f46; }
                .status-inativo { background: #fee2e2; color: #991b1b; }
                .status-pendente { background: #fef3c7; color: #92400e; }
                .status-cancelado { background: #e2e8f0; color: #4a5568; }
                .footer { margin-top: 20px; text-align: center; font-size: 11px; color: #999; border-top: 1px solid #e2e8f0; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>📋 LISTA NOMINAL DE ALUNOS</h1>
                <div class="sub"><?= $_SESSION['escola_nome'] ?? 'Complexo Escolar Castelo Reis' ?></div>
                <div class="info">Gerado em: <?= date('d/m/Y H:i:s') ?> | Total: ${rows.length} alunos</div>
            </div>
            <table>
                <thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
                <tbody>${rows.map(row => `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('')}</tbody>
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
// IMPRIMIR
// ============================================
function imprimirLista() {
    window.print();
}

// ============================================
// MARCAR NOTIFICAÇÃO COMO LIDA
// ============================================
function marcarNotificacaoLida(id) {
    fetch('marcar_notificacao_lida.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            var badge = document.getElementById('notificationBadge');
            var current = parseInt(badge.textContent) || 0;
            if (current > 0) {
                badge.textContent = current - 1;
                if (badge.textContent == '0') {
                    badge.style.display = 'none';
                }
            }
            location.reload();
        }
    })
    .catch(function(error) {
        console.error('Erro:', error);
    });
}

// ============================================
// MARCAR TODAS COMO LIDAS
// ============================================
function marcarTodasLidas() {
    fetch('marcar_todas_lidas.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            var badge = document.getElementById('notificationBadge');
            badge.textContent = '0';
            badge.style.display = 'none';
            location.reload();
        }
    })
    .catch(function(error) {
        console.error('Erro:', error);
    });
}

// ============================================
// FECHAR DROPDOWNS AO CLICAR FORA
// ============================================
document.addEventListener('click', function(event) {
    var listaDropdown = document.getElementById('listaNominalDropdown');
    var notifDropdown = document.getElementById('notificationsDropdown');
    
    if (listaDropdown && !event.target.closest('.lista-nominal-toggle') && !event.target.closest('.lista-nominal-dropdown')) {
        listaDropdown.style.display = 'none';
    }
    
    if (notifDropdown && !event.target.closest('.notifications-toggle') && !event.target.closest('.notifications-dropdown')) {
        notifDropdown.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    var listaDropdown = document.getElementById('listaNominalDropdown');
    var notifDropdown = document.getElementById('notificationsDropdown');
    
    if (listaDropdown) {
        listaDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    if (notifDropdown) {
        notifDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});
</script>