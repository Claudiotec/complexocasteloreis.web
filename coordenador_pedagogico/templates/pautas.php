<?php
// ============================================
// coordenador_pedagogico/templates/pautas.php
// Pautas - Separado
// ============================================
?>
<div class="card-modern">
    <div class="card-header">
        <span>📄 Geração de Pautas</span>
        <div>
            <button class="btn-light me-2" onclick="exportarPautaPDF()">
                <i class="bi bi-file-pdf"></i> PDF
            </button>
            <button class="btn-light" onclick="exportarPautaExcel()">
                <i class="bi bi-file-excel"></i> Excel
            </button>
        </div>
    </div>
    <div class="card-body">
        <form id="formPauta" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Disciplina *</label>
                <select class="form-select" id="selectDisciplinaPauta" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($disciplinasList as $disc): ?>
                        <option value="<?= $disc['id'] ?>"><?= htmlspecialchars($disc['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Turma *</label>
                <select class="form-select" id="selectTurmaPauta" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($turmasAtivas as $turma): ?>
                        <option value="<?= $turma['id'] ?>"><?= htmlspecialchars($turma['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tipo de Pauta</label>
                <select class="form-select" id="selectTipoPauta">
                    <option value="final">Pauta Final (MFD)</option>
                    <option value="trimestral">Pauta Trimestral</option>
                </select>
            </div>
            <div class="col-12">
                <button type="button" class="btn btn-primary" onclick="gerarPauta()">
                    <i class="bi bi-gear"></i> Gerar Pauta
                </button>
            </div>
        </form>

        <div id="resultadoPauta" style="display:none; margin-top:20px;">
            <h6 id="tituloPauta">Pauta Final - Matemática - 10ª A</h6>
            <div class="table-responsive">
                <table class="table-custom" id="tabelaPautaResultado">
                    <thead>
                        <tr>
                            <th>Nº</th>
                            <th>Aluno</th>
                            <th>1T</th>
                            <th>2T</th>
                            <th>3T</th>
                            <th>MFD</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody id="corpoPautaResultado">
                        <tr><td colspan="7" class="text-center text-muted py-3">Aguardando geração...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="row mt-3" id="statsPauta">
                <div class="col-md-3">
                    <div class="card bg-light"><div class="card-body text-center">
                        <h5 id="totalAlunosPauta">0</h5>
                        <small class="text-muted">Total Alunos</small>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light"><div class="card-body text-center">
                        <h5 id="aprovadosPauta" class="text-success">0</h5>
                        <small class="text-muted">Aprovados</small>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light"><div class="card-body text-center">
                        <h5 id="reprovadosPauta" class="text-danger">0</h5>
                        <small class="text-muted">Reprovados</small>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light"><div class="card-body text-center">
                        <h5 id="mediaPauta">0.0</h5>
                        <small class="text-muted">Média Geral</small>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// FUNÇÕES DE PAUTAS
// ============================================
function gerarPauta() {
    const disciplinaId = document.getElementById('selectDisciplinaPauta').value;
    const turmaId = document.getElementById('selectTurmaPauta').value;
    const tipo = document.getElementById('selectTipoPauta').value;
    
    if (!disciplinaId) { mostrarNotificacao('Selecione uma disciplina', 'warning'); return; }
    if (!turmaId) { mostrarNotificacao('Selecione uma turma', 'warning'); return; }
    
    showLoading();
    
    $.ajax({
        url: '/softgest_web/api/pautas/gerar.php',
        method: 'POST',
        data: {
            disciplina_id: disciplinaId,
            turma_id: turmaId,
            tipo: tipo
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                mostrarNotificacao('📄 Pauta gerada com sucesso!', 'success');
                document.getElementById('resultadoPauta').style.display = 'block';
                renderizarPauta(response.pauta, response.stats);
            } else {
                mostrarNotificacao('❌ ' + (response.message || 'Erro ao gerar pauta'), 'danger');
            }
        },
        error: function() {
            hideLoading();
            mostrarNotificacao('❌ Erro ao comunicar com o servidor', 'danger');
        }
    });
}

function renderizarPauta(pauta, stats) {
    const tbody = document.getElementById('corpoPautaResultado');
    tbody.innerHTML = '';
    
    if (!pauta || pauta.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Nenhum aluno encontrado</td></tr>';
        return;
    }
    
    pauta.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${index + 1}</td>
            <td>${item.aluno_nome}</td>
            <td class="${getNotaClass(item.mt1)}">${item.mt1 || '-'}</td>
            <td class="${getNotaClass(item.mt2)}">${item.mt2 || '-'}</td>
            <td class="${getNotaClass(item.mt3)}">${item.mt3 || '-'}</td>
            <td class="${getNotaClass(item.mfd)}"><strong>${item.mfd ? item.mfd.toFixed(1) : '-'}</strong></td>
            <td><span class="badge ${getSituacaoClass(item.situacao)}">${item.situacao}</span></td>
        `;
        tbody.appendChild(tr);
    });
    
    if (stats) {
        document.getElementById('totalAlunosPauta').textContent = stats.total || 0;
        document.getElementById('aprovadosPauta').textContent = stats.aprovados || 0;
        document.getElementById('reprovadosPauta').textContent = stats.reprovados || 0;
        document.getElementById('mediaPauta').textContent = stats.media || '0.0';
    }
}

function exportarPautaPDF() {
    mostrarNotificacao('📄 Exportação para PDF iniciada!', 'info');
}

function exportarPautaExcel() {
    mostrarNotificacao('📊 Exportação para Excel iniciada!', 'info');
}
</script>