<?php
// ============================================
// coordenador_pedagogico/dashboard.php
// Dashboard do Coordenador Pedagógico - Principal
// ============================================

session_start();

// ===== VERIFICAR LOGIN =====
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /softgest_web/login.php');
    exit;
}

// ===== VERIFICAR PERFIL =====
$perfil = $_SESSION['usuario_perfil'] ?? '';
if ($perfil != 'coordenador_pedagogico' && $perfil != 'coordenador' && $perfil != 'admin') {
    header('Location: /softgest_web/index.php');
    exit;
}

// ===== CONFIGURAÇÕES =====
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

// ===== DADOS DO USUÁRIO =====
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Coordenador';
$usuario_email = $_SESSION['usuario_email'] ?? '';
$usuario_perfil = $_SESSION['usuario_perfil'] ?? 'coordenador_pedagogico';

// ===== BUSCAR DADOS DO DASHBOARD =====
$totalAlunos = 0;
$totalProfessores = 0;
$totalTurmas = 0;
$totalDisciplinas = 0;
$totalMatriculas = 0;
$totalPrazos = 0;
$turmasAtivas = [];
$distribuicoes = [];

try {
    // Total de Alunos
    $stmt = $pdo->query("SELECT COUNT(*) FROM alunos");
    $totalAlunos = $stmt->fetchColumn() ?: 0;
    
    // Total de Professores
    $stmt = $pdo->query("SELECT COUNT(*) FROM funcionarios WHERE cargo LIKE '%Professor%'");
    $totalProfessores = $stmt->fetchColumn() ?: 0;
    
    // Total de Turmas
    $stmt = $pdo->query("SELECT COUNT(*) FROM turmas WHERE status = 'ativa' OR status IS NULL");
    $totalTurmas = $stmt->fetchColumn() ?: 0;
    
    // Total de Disciplinas
    $stmt = $pdo->query("SELECT COUNT(*) FROM disciplinas");
    $totalDisciplinas = $stmt->fetchColumn() ?: 0;
    
    // Total de Matrículas
    $stmt = $pdo->query("SELECT COUNT(*) FROM matriculas WHERE status = 'ativa'");
    $totalMatriculas = $stmt->fetchColumn() ?: 0;
    
    // Total de Prazos
    $stmt = $pdo->query("SELECT COUNT(*) FROM prazos WHERE status = 'ativo'");
    $totalPrazos = $stmt->fetchColumn() ?: 0;
    
    // Turmas com alunos
    $stmt = $pdo->query("
        SELECT t.id, t.nome, t.classe, t.turno, t.capacidade, t.sala, t.ano_letivo,
               COUNT(m.id) as total_alunos
        FROM turmas t
        LEFT JOIN matriculas m ON m.turma_id = t.id AND m.status = 'ativa'
        WHERE t.status = 'ativa' OR t.status IS NULL
        GROUP BY t.id
        ORDER BY t.classe, t.nome
    ");
    $turmasAtivas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Distribuições
    $stmt = $pdo->query("SELECT * FROM destribuicao_professores_nova ORDER BY id DESC");
    $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao carregar dados: " . $e->getMessage());
}

// ===== FUNÇÕES AUXILIARES =====
function calcularMFD($mt1, $mt2, $mt3) {
    $notas = array_filter([$mt1, $mt2, $mt3], function($n) {
        return $n !== null && $n !== '' && is_numeric($n);
    });
    if (empty($notas)) return null;
    return round(array_sum($notas) / count($notas), 1);
}

function getSituacao($mfd) {
    if ($mfd === null) return 'SEM NOTA';
    if ($mfd >= 10) return 'APROVADO';
    if ($mfd >= 8) return 'RECUPERAÇÃO';
    return 'REPROVADO';
}

function getNotaClass($nota) {
    if ($nota === null || $nota === '') return '';
    $n = (float)$nota;
    if ($n >= 14) return 'nota-alta';
    if ($n >= 10) return 'nota-media';
    if ($n >= 8) return 'nota-media';
    return 'nota-baixa';
}

// ===== INCLUIR HEADER =====
include 'templates/header.php';
include 'templates/sidebar.php';
?>

<!-- ========================================== -->
<!-- SEÇÃO: DASHBOARD -->
<!-- ========================================== -->
<div id="section-dashboard">
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon">🎓</div>
            <div class="info">
                <h3><?= $totalAlunos ?></h3>
                <p>Total de Alunos</p>
            </div>
        </div>
        <div class="stat-card" style="border-left-color: #9b59b6;">
            <div class="icon">👨‍🏫</div>
            <div class="info">
                <h3><?= $totalProfessores ?></h3>
                <p>Professores</p>
            </div>
        </div>
        <div class="stat-card" style="border-left-color: #3498db;">
            <div class="icon">📚</div>
            <div class="info">
                <h3><?= $totalTurmas ?></h3>
                <p>Turmas Ativas</p>
            </div>
        </div>
        <div class="stat-card" style="border-left-color: #27ae60;">
            <div class="icon">📊</div>
            <div class="info">
                <h3><?= $totalDisciplinas ?></h3>
                <p>Disciplinas</p>
            </div>
        </div>
    </div>

    <!-- Ações Rápidas -->
    <div class="card-modern">
        <div class="card-header">
            <span>⚡ Ações Rápidas</span>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="#" class="quick-action" onclick="showSection('distribuicao')">
                    <span class="icon">👨‍🏫</span> Distribuir Professores
                </a>
                <a href="#" class="quick-action" onclick="showSection('bancoNotas')">
                    <span class="icon">📝</span> Lançar Notas
                </a>
                <a href="#" class="quick-action" onclick="showSection('pautas')">
                    <span class="icon">📄</span> Gerar Pauta
                </a>
                <a href="#" class="quick-action" onclick="abrirModalAluno()">
                    <span class="icon">🎓</span> Matricular Aluno
                </a>
                <a href="#" class="quick-action" onclick="abrirModalTurma()">
                    <span class="icon">📋</span> Criar Turma
                </a>
                <a href="#" class="quick-action" onclick="showSection('relatorios')">
                    <span class="icon">📊</span> Relatórios
                </a>
            </div>
        </div>
    </div>

    <!-- Turmas Ativas -->
    <div class="card-modern">
        <div class="card-header">
            <span>🏫 Turmas Ativas</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Turma</th>
                            <th>Classe</th>
                            <th>Turno</th>
                            <th>Alunos</th>
                            <th>Ocupação</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($turmasAtivas)): ?>
                            <?php foreach ($turmasAtivas as $turma): 
                                $capacidade = $turma['capacidade'] ?? 45;
                                $alunos = $turma['total_alunos'] ?? 0;
                                $percentual = $capacidade > 0 ? round(($alunos / $capacidade) * 100) : 0;
                                $statusClass = $percentual >= 100 ? 'danger' : ($percentual >= 90 ? 'warning' : 'success');
                                $statusText = $percentual >= 100 ? 'Cheia' : ($percentual >= 90 ? 'Quase Cheia' : 'Disponível');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($turma['nome'] ?? '') ?></strong></td>
                                <td><?= htmlspecialchars($turma['classe'] ?? '') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($turma['turno'] ?? '') ?></span></td>
                                <td><?= $alunos ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 8px; width: 80px;">
                                            <div class="progress-bar bg-<?= $statusClass ?>" style="width: <?= $percentual ?>%"></div>
                                        </div>
                                        <small><?= $percentual ?>%</small>
                                    </div>
                                </td>
                                <td><span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span></td>
                                <td>
                                    <button class="action-btn" onclick="verDetalhesTurma(<?= $turma['id'] ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="action-btn" onclick="editarTurma(<?= $turma['id'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">Nenhuma turma encontrada</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="row">
        <div class="col-md-6">
            <div class="card-modern">
                <div class="card-header"><span>📈 Estatísticas</span></div>
                <div class="card-body">
                    <?php 
                    $ocupacaoTotal = 0;
                    $totalCapacidade = 0;
                    $totalAlunosTurmas = 0;
                    foreach ($turmasAtivas as $t) {
                        $totalCapacidade += $t['capacidade'] ?? 45;
                        $totalAlunosTurmas += $t['total_alunos'] ?? 0;
                    }
                    $ocupacaoTotal = $totalCapacidade > 0 ? round(($totalAlunosTurmas / $totalCapacidade) * 100) : 0;
                    ?>
                    <div class="mb-3">
                        <small class="text-muted">Taxa de Ocupação</small>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-<?= $ocupacaoTotal >= 90 ? 'warning' : ($ocupacaoTotal >= 100 ? 'danger' : 'success') ?>" 
                                 style="width: <?= min($ocupacaoTotal, 100) ?>%"></div>
                        </div>
                        <small class="text-muted"><?= $ocupacaoTotal ?>% das vagas ocupadas</small>
                    </div>
                    <div>
                        <small class="text-muted">Média de Alunos por Turma</small>
                        <h4><?= count($turmasAtivas) > 0 ? round($totalAlunosTurmas / count($turmasAtivas), 1) : 0 ?></h4>
                    </div>
                    <div>
                        <small class="text-muted">Ano Letivo Atual</small>
                        <h5>2026</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-modern">
                <div class="card-header"><span>🔔 Prazos Ativos</span></div>
                <div class="card-body">
                    <div class="text-muted py-2">Nenhum prazo ativo</div>
                    <div class="mt-3">
                        <small class="text-muted">Total: <strong><?= $totalPrazos ?></strong> prazos ativos</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- SEÇÃO: DISTRIBUIÇÃO DE PROFESSORES -->
<!-- ========================================== -->
<div id="section-distribuicao" style="display:none;">
    <div class="card-modern">
        <div class="card-header">
            <span>👨‍🏫 Distribuição de Professores</span>
            <div>
                <button class="btn-light me-2" onclick="carregarDistribuicoes()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
                <button class="btn-light" onclick="exportarDistribuicaoExcel()">
                    <i class="bi bi-file-excel"></i> Excel
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Formulário -->
            <div class="card border mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Nova Distribuição</h6>
                    <form id="formDistribuicao" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Professor *</label>
                            <select class="form-select" id="selectProfessor" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Turma *</label>
                            <select class="form-select" id="selectTurma" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ano Letivo</label>
                            <input type="text" class="form-control" id="anoLetivoDist" value="2026">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Disciplinas *</label>
                            <select class="form-select" id="selectDisciplinas" multiple style="height:120px;" required>
                            </select>
                            <small class="text-muted">Segure Ctrl para selecionar múltiplas</small>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-primary" onclick="adicionarDistribuicao()">
                                <i class="bi bi-plus-circle"></i> Adicionar
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="limparFormDistribuicao()">
                                <i class="bi bi-x-circle"></i> Limpar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Distribuições -->
            <h6>Distribuições Existentes</h6>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Professor</th>
                            <th>Turma</th>
                            <th>Classe</th>
                            <th>Disciplinas</th>
                            <th>Ano Letivo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="corpoDistribuicoes">
                        <?php if (!empty($distribuicoes)): ?>
                            <?php foreach ($distribuicoes as $dist): ?>
                            <tr id="distRow_<?= $dist['id'] ?>">
                                <td><?= htmlspecialchars($dist['professor_nome'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($dist['turma_nome'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($dist['classe'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($dist['disciplinas'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($dist['ano_letivo'] ?? '2026') ?></td>
                                <td>
                                    <button class="action-btn" onclick="editarDistribuicao(<?= $dist['id'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="action-btn text-danger" onclick="excluirDistribuicao(<?= $dist['id'] ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Nenhuma distribuição encontrada</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- INCLUIR OS TEMPLATES DAS OUTRAS FUNÇÕES -->
<!-- ========================================== -->
<?php
include 'templates/banco_notas.php';
include 'templates/pautas.php';
include 'templates/relatorios.php';
include 'templates/configuracoes.php';
?>

<!-- ===== MODAIS ===== -->
<?php include 'templates/modais.php'; ?>

<!-- ========================================== -->
<!-- FOOTER -->
<!-- ========================================== -->
<?php include 'templates/footer.php'; ?>