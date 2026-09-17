<?php
// ============================================
// coordenador_pedagogico/templates/dashboard_content.php
// Conteúdo do Dashboard
// ============================================
?>

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
<?php include_once 'acoes_rapidas.php'; ?>

<!-- Turmas Ativas -->
<div class="card-modern">
    <div class="card-header">
        <span>🏫 Turmas Ativas</span>
        <a href="#" class="btn-light">Ver Todas →</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table-custom" id="turmasTable">
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
                <div id="prazosContainer">
                    <?php if (!empty($prazosList)): ?>
                        <?php foreach ($prazosList as $prazo): ?>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span>📝 <?= htmlspecialchars($prazo['nome'] ?? 'Prazo') ?></span>
                                <span class="badge bg-<?= $prazo['status'] == 'ativo' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($prazo['status'] ?? 'Inativo') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted py-2">Nenhum prazo ativo</div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <small class="text-muted">Total: <strong><?= $totalPrazos ?></strong> prazos ativos</small>
                </div>
            </div>
        </div>
    </div>
</div>