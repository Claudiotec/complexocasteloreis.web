<?php
// ============================================
// coordenador_pedagogico/templates/banco_notas.php
// Banco de Notas - Separado
// ============================================
?>
<div class="card-modern">
    <div class="card-header">
        <span>📝 Banco de Notas</span>
        <div>
            <button class="btn-light me-2" onclick="carregarNotas()">
                <i class="bi bi-arrow-clockwise"></i> Atualizar
            </button>
            <button class="btn-light me-2" onclick="exportarNotasExcel()">
                <i class="bi bi-file-excel"></i> Excel
            </button>
            <button class="btn-light" onclick="abrirModalNota()">
                <i class="bi bi-plus-circle"></i> Nova Nota
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <div class="filter-area">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Aluno</label>
                    <input type="text" class="form-control" id="filtroAluno" placeholder="Buscar aluno...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Disciplina</label>
                    <select class="form-select" id="filtroDisciplina">
                        <option value="">Todas</option>
                        <?php foreach ($disciplinasList as $disc): ?>
                            <option value="<?= $disc['id'] ?>"><?= htmlspecialchars($disc['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Turma</label>
                    <select class="form-select" id="filtroTurmaNota">
                        <option value="">Todas</option>
                        <?php foreach ($turmasAtivas as $turma): ?>
                            <option value="<?= $turma['id'] ?>"><?= htmlspecialchars($turma['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="filtrarNotas()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabela de Notas -->
        <div class="table-responsive">
            <table class="table-custom" id="tabelaNotas">
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Disciplina</th>
                        <th>Turma</th>
                        <th>1T</th>
                        <th>2T</th>
                        <th>3T</th>
                        <th>MFD</th>
                        <th>Situação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="corpoNotas">
                    <?php if (!empty($notasList)): ?>
                        <?php foreach ($notasList as $nota): 
                            $mfd = calcularMFD($nota['mt1'] ?? null, $nota['mt2'] ?? null, $nota['mt3'] ?? null);
                            $situacao = getSituacao($mfd);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($nota['aluno_nome'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($nota['disciplina_nome'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($nota['turma_nome'] ?? 'N/A') ?></td>
                            <td class="<?= getNotaClass($nota['mt1'] ?? null) ?>"><?= $nota['mt1'] ?? '-' ?></td>
                            <td class="<?= getNotaClass($nota['mt2'] ?? null) ?>"><?= $nota['mt2'] ?? '-' ?></td>
                            <td class="<?= getNotaClass($nota['mt3'] ?? null) ?>"><?= $nota['mt3'] ?? '-' ?></td>
                            <td class="<?= getNotaClass($mfd) ?>"><strong><?= $mfd ? number_format($mfd, 1) : '-' ?></strong></td>
                            <td><span class="badge <?= getSituacaoClass($situacao) ?>"><?= $situacao ?></span></td>
                            <td>
                                <button class="action-btn" onclick="verDetalhesNota(<?= $nota['id'] ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="action-btn" onclick="editarNota(<?= $nota['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="action-btn text-danger" onclick="excluirNota(<?= $nota['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center text-muted py-3">Nenhuma nota encontrada</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// ============================================
// FUNÇÕES DE NOTAS
// ============================================
function abrirModalNota() {
    const modal = new bootstrap.Modal(document.getElementById('modalNota'));
    document.getElementById('modalNotaTitle').textContent = '📝 Nova Nota';
    document.getElementById('notaId').value = '';
    document.getElementById('formNota').reset();
    carregarAlunosSelect();
    modal.show();
}

function carregarAlunosSelect() {
    $.ajax({
        url: '/softgest_web/api/alunos/listar.php',
        method: 'GET',
        success: function(response) {
            if (response.success && response.alunos) {
                const select = document.getElementById('selectAlunoNota');
                select.innerHTML = '<option value="">Selecione...</option>';
                response.alunos.forEach(aluno => {
                    const opt = document.createElement('option');
                    opt.value = aluno.id;
                    opt.textContent = aluno.nome + ' (' + (aluno.turma || '') + ')';
                    select.appendChild(opt);
                });
            }
        }
    });
}

function salvarNota() {
    const id = document.getElementById('notaId').value;
    const alunoId = document.getElementById('selectAlunoNota').value;
    const disciplinaId = document.getElementById('selectDisciplinaNota').value;
    const turmaId = document.getElementById('selectTurmaNota').value;
    const anoLetivo = document.getElementById('anoLetivoNota').value;
    const mt1 = document.getElementById('nota1T').value || null;
    const mt2 = document.getElementById('nota2T').value || null;
    const mt3 = document.getElementById('nota3T').value || null;
    const obs = document.getElementById('obsNota').value;
    
    if (!alunoId) { mostrarNotificacao('Selecione um aluno', 'warning'); return; }
    if (!disciplinaId) { mostrarNotificacao('Selecione uma disciplina', 'warning'); return; }
    if (!turmaId) { mostrarNotificacao('Selecione uma turma', 'warning'); return; }
    
    showLoading();
    
    $.ajax({
        url: '/softgest_web/api/notas/salvar.php',
        method: 'POST',
        data: {
            id: id,
            aluno_id: alunoId,
            disciplina_id: disciplinaId,
            turma_id: turmaId,
            ano_letivo: anoLetivo,
            mt1: mt1,
            mt2: mt2,
            mt3: mt3,
            observacoes: obs
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                mostrarNotificacao('✅ Nota salva com sucesso!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalNota')).hide();
                carregarNotas();
            } else {
                mostrarNotificacao('❌ ' + (response.message || 'Erro ao salvar'), 'danger');
            }
        },
        error: function() {
            hideLoading();
            mostrarNotificacao('❌ Erro ao comunicar com o servidor', 'danger');
        }
    });
}

function carregarNotas() {
    showLoading();
    $.ajax({
        url: '/softgest_web/api/notas/listar.php',
        method: 'GET',
        success: function(response) {
            hideLoading();
            if (response.success && response.notas) {
                renderizarNotas(response.notas);
                document.getElementById('notasCount').textContent = response.notas.length;
            }
        },
        error: function() {
            hideLoading();
            mostrarNotificacao('❌ Erro ao carregar notas', 'danger');
        }
    });
}

function renderizarNotas(notas) {
    const tbody = document.getElementById('corpoNotas');
    tbody.innerHTML = '';
    
    if (notas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Nenhuma nota encontrada</td></tr>';
        return;
    }
    
    notas.forEach(nota => {
        const mfd = calcularMFD(nota.mt1, nota.mt2, nota.mt3);
        const situacao = getSituacao(mfd);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${nota.aluno_nome || 'N/A'}</td>
            <td>${nota.disciplina_nome || 'N/A'}</td>
            <td>${nota.turma_nome || 'N/A'}</td>
            <td class="${getNotaClass(nota.mt1)}">${nota.mt1 || '-'}</td>
            <td class="${getNotaClass(nota.mt2)}">${nota.mt2 || '-'}</td>
            <td class="${getNotaClass(nota.mt3)}">${nota.mt3 || '-'}</td>
            <td class="${getNotaClass(mfd)}"><strong>${mfd ? mfd.toFixed(1) : '-'}</strong></td>
            <td><span class="badge ${getSituacaoClass(situacao)}">${situacao}</span></td>
            <td>
                <button class="action-btn" onclick="editarNota(${nota.id})"><i class="bi bi-pencil"></i></button>
                <button class="action-btn text-danger" onclick="excluirNota(${nota.id})"><i class="bi bi-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function editarNota(id) {
    mostrarNotificacao('✏️ Editar nota #' + id + ' (em desenvolvimento)', 'info');
}

function excluirNota(id) {
    if (!confirm('Tem certeza que deseja excluir esta nota?')) return;
    
    showLoading();
    $.ajax({
        url: '/softgest_web/api/notas/excluir.php',
        method: 'POST',
        data: { id: id },
        success: function(response) {
            hideLoading();
            if (response.success) {
                mostrarNotificacao('✅ Nota excluída com sucesso!', 'success');
                carregarNotas();
            } else {
                mostrarNotificacao('❌ ' + (response.message || 'Erro ao excluir'), 'danger');
            }
        },
        error: function() {
            hideLoading();
            mostrarNotificacao('❌ Erro ao comunicar com o servidor', 'danger');
        }
    });
}

function verDetalhesNota(id) {
    mostrarNotificacao('📋 Detalhes da nota #' + id + ' (em desenvolvimento)', 'info');
}

function filtrarNotas() {
    mostrarNotificacao('🔍 Filtro aplicado!', 'info');
}

function exportarNotasExcel() {
    mostrarNotificacao('📊 Exportação para Excel iniciada!', 'info');
}
</script>