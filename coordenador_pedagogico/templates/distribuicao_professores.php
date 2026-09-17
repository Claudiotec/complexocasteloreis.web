<?php
// ============================================
// coordenador_pedagogico/templates/distribuicao_professores.php
// Distribuição de Professores - COMPLETO
// ============================================


// ============================================
// DEBUG - VERIFICAR PROFESSORES
// ============================================
// Debug - verificar se há professores
if (empty($professoresList)) {
    error_log("⚠️ Nenhum professor encontrado no template!");
} else {
    error_log("✅ Professores no template: " . count($professoresList));
    foreach ($professoresList as $p) {
        // Verificar se a chave 'nome' existe
        $nome = isset($p['nome']) ? $p['nome'] : (isset($p['nome_completo']) ? $p['nome_completo'] : 'N/A');
        error_log("  - ID: " . ($p['id'] ?? 'N/A') . " | Nome: " . $nome);
    }
}

// Dados do PHP para fallback - COM VERIFICAÇÃO
$professoresJson = json_encode($professoresList ?: []);
$turmasJson = json_encode($turmasAtivas ?: []);
$disciplinasJson = json_encode($disciplinasList ?: []);
$distribuicoesJson = json_encode($distribuicoes ?: []);


// Dados do PHP para fallback
$professoresJson = json_encode($professoresList);
$turmasJson = json_encode($turmasAtivas);
$disciplinasJson = json_encode($disciplinasList);
$distribuicoesJson = json_encode($distribuicoes);
?>

<!-- ===== MODAL DE EDIÇÃO ===== -->
<div class="modal fade" id="modalEditarDistribuicao" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">✏️ Editar Distribuição</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarDistribuicao">
                    <input type="hidden" id="editDistId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Professor *</label>
                            <select class="form-select" id="editProfessor" required>
                                <option value="">Selecione...</option>
                                <?php if (!empty($professoresList)): ?>
                                    <?php foreach ($professoresList as $prof): ?>
                                        <option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['nome'] . ' (' . ($prof['cargo'] ?? 'Professor') . ')') ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Turma *</label>
                            <select class="form-select" id="editTurma" required>
                                <option value="">Selecione...</option>
                                <?php if (!empty($turmasAtivas)): ?>
                                    <?php foreach ($turmasAtivas as $turma): ?>
                                        <option value="<?= $turma['id'] ?>"><?= htmlspecialchars($turma['nome'] . ' - ' . ($turma['classe'] ?? '') . ' (' . ($turma['turno'] ?? '') . ')') ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ano Letivo</label>
                            <input type="text" class="form-control" id="editAnoLetivo" value="2026">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Disciplinas *</label>
                            <select class="form-select" id="editDisciplinas" multiple style="height:120px;" required>
                                <?php if (!empty($disciplinasList)): ?>
                                    <?php foreach ($disciplinasList as $disc): ?>
                                        <option value="<?= $disc['id'] ?>"><?= htmlspecialchars($disc['nome']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">Segure Ctrl (Cmd no Mac) para selecionar múltiplas</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarEdicaoDistribuicao()">
                    <i class="bi bi-save"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

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
                    <!-- PROFESSOR -->
                    <div class="col-md-4">
                        <label class="form-label">Professor *</label>
                        <select class="form-select" id="selectProfessor" required>
                            <option value="">Selecione...</option>
                            <?php 
                            if (!empty($professoresList)) {
                                foreach ($professoresList as $prof): 
                            ?>
                                <option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['nome'] . ' (' . ($prof['cargo'] ?? 'Professor') . ')') ?></option>
                            <?php 
                                endforeach;
                            } else {
                                echo '<option value="">Nenhum professor encontrado</option>';
                            }
                            ?>
                        </select>
                        <?php if (empty($professoresList)): ?>
                            <small class="text-danger d-block mt-1">⚠️ Nenhum professor cadastrado.</small>
                        <?php endif; ?>
                    </div>
                    
                    <!-- CLASSE -->
                    <div class="col-md-4">
                        <label class="form-label">Classe *</label>
                        <select class="form-select" id="selectClasse" multiple style="height:80px;" required>
                            <?php 
                            if (!empty($turmasAtivas)) {
                                $classes = array_unique(array_column($turmasAtivas, 'classe'));
                                sort($classes);
                                foreach ($classes as $classe): 
                                    if (!empty($classe)):
                            ?>
                                <option value="<?= htmlspecialchars($classe) ?>"><?= htmlspecialchars($classe) ?></option>
                            <?php 
                                    endif;
                                endforeach;
                            } else {
                                echo '<option value="">Nenhuma classe encontrada</option>';
                            }
                            ?>
                        </select>
                        <small class="text-muted">Segure Ctrl (Cmd) para selecionar múltiplas classes</small>
                    </div>
                    
                    <!-- TURMA -->
                    <div class="col-md-4">
                        <label class="form-label">Turma *</label>
                        <select class="form-select" id="selectTurma" multiple style="height:80px;" required>
                            <?php 
                            if (!empty($turmasAtivas)) {
                                foreach ($turmasAtivas as $turma): 
                                    $nomeTurma = $turma['nome'] ?? '';
                                    $classe = $turma['classe'] ?? '';
                                    $turno = $turma['turno'] ?? '';
                                    $label = $nomeTurma;
                                    if ($classe) $label .= ' - ' . $classe;
                                    if ($turno) $label .= ' (' . $turno . ')';
                            ?>
                                <option value="<?= $turma['id'] ?>"><?= htmlspecialchars($label) ?></option>
                            <?php 
                                endforeach;
                            } else {
                                echo '<option value="">Nenhuma turma encontrada</option>';
                            }
                            ?>
                        </select>
                        <small class="text-muted">Segure Ctrl (Cmd) para selecionar múltiplas turmas</small>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Ano Letivo</label>
                        <input type="text" class="form-control" id="anoLetivoDist" value="2026">
                    </div>
                    
                    <!-- DISCIPLINAS DA TURMA -->
                    <div class="col-6">
                        <label class="form-label">Disciplinas da Turma</label>
                        <select class="form-select" id="selectDisciplinasTurma" multiple style="height:120px;">
                            <?php 
                            if (!empty($turmasAtivas)) {
                                $todasDisciplinas = [];
                                foreach ($turmasAtivas as $turma) {
                                    if (!empty($turma['disciplinas'])) {
                                        $discs = explode(',', $turma['disciplinas']);
                                        foreach ($discs as $d) {
                                            $d = trim($d);
                                            if (!empty($d) && !in_array($d, $todasDisciplinas)) {
                                                $todasDisciplinas[] = $d;
                                            }
                                        }
                                    }
                                }
                                sort($todasDisciplinas);
                                foreach ($todasDisciplinas as $disc):
                            ?>
                                <option value="<?= htmlspecialchars($disc) ?>"><?= htmlspecialchars($disc) ?></option>
                            <?php 
                                endforeach;
                            } else {
                                echo '<option value="">Nenhuma disciplina encontrada</option>';
                            }
                            ?>
                        </select>
                        <small class="text-muted">Disciplinas extraídas das turmas (coluna disciplinas)</small>
                        <button type="button" class="btn btn-sm btn-info mt-1" onclick="carregarDisciplinasDasTurmas()">
                            <i class="bi bi-arrow-repeat"></i> Carregar das Turmas Selecionadas
                        </button>
                    </div>
                    
                    <!-- DISCIPLINAS DO SISTEMA -->
                    <div class="col-6">
                        <label class="form-label">Disciplinas do Sistema *</label>
                        <select class="form-select" id="selectDisciplinas" multiple style="height:120px;" required>
                            <?php 
                            if (!empty($disciplinasList)) {
                                foreach ($disciplinasList as $disc): 
                            ?>
                                <option value="<?= $disc['id'] ?>"><?= htmlspecialchars($disc['nome']) ?></option>
                            <?php 
                                endforeach;
                            } else {
                                echo '<option value="">Nenhuma disciplina encontrada</option>';
                            }
                            ?>
                        </select>
                        <small class="text-muted">Segure Ctrl (Cmd) para selecionar múltiplas</small>
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
        <h6>Distribuições Existentes <span class="badge bg-secondary" id="totalDistribuicoes"><?= count($distribuicoes) ?></span></h6>
        <div class="table-responsive">
            <table class="table-custom" id="tabelaDistribuicoes">
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
                            <td><strong><?= htmlspecialchars($dist['professor_nome'] ?? 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars($dist['turma_nome'] ?? 'N/A') ?></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($dist['classe'] ?? 'N/A') ?></span></td>
                            <td><?= htmlspecialchars($dist['disciplinas'] ?? '-') ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($dist['ano_letivo'] ?? '2026') ?></span></td>
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

<script>
// ============================================
// DADOS DO PHP PARA FALLBACK
// ============================================
const dadosProfessores = <?= $professoresJson ?>;
const dadosTurmas = <?= $turmasJson ?>;
const dadosDisciplinas = <?= $disciplinasJson ?>;
const dadosDistribuicoes = <?= $distribuicoesJson ?>;

console.log('📦 Dados do PHP carregados:');
console.log('👨‍🏫 Professores:', dadosProfessores.length);
console.log('🏫 Turmas:', dadosTurmas.length);
console.log('📚 Disciplinas:', dadosDisciplinas.length);
console.log('📋 Distribuições:', dadosDistribuicoes.length);

// ============================================
// CARREGAR PROFESSORES
// ============================================
function carregarProfessores() {
    const select = document.getElementById('selectProfessor');
    const editSelect = document.getElementById('editProfessor');
    
    // Função para popular selects
    function popularSelects(professores) {
        [select, editSelect].forEach(sel => {
            if (sel) {
                sel.innerHTML = '<option value="">Selecione...</option>';
                if (professores && professores.length > 0) {
                    professores.forEach(prof => {
                        const opt = document.createElement('option');
                        opt.value = prof.id;
                        const nome = prof.nome || prof.nome_completo || 'Professor';
                        const cargo = prof.cargo || 'Professor';
                        opt.textContent = nome + ' (' + cargo + ')';
                        sel.appendChild(opt);
                    });
                    console.log('✅ ' + professores.length + ' professores carregados');
                } else {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'Nenhum professor encontrado';
                    sel.appendChild(opt);
                }
            }
        });
    }
    
    // 1. Tentar usar dados do PHP
    if (dadosProfessores && dadosProfessores.length > 0) {
        popularSelects(dadosProfessores);
        return;
    }
    
    // 2. Tentar AJAX
    if (navigator.onLine) {
        showLoading();
        $.ajax({
            url: '/softgest_web/api/funcionarios/listar_professores.php',
            method: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                hideLoading();
                if (response.success && response.professores && response.professores.length > 0) {
                    popularSelects(response.professores);
                    // Salvar no localStorage para fallback offline
                    localStorage.setItem('professores_cache', JSON.stringify(response.professores));
                } else {
                    // Tentar cache local
                    const cache = localStorage.getItem('professores_cache');
                    if (cache) {
                        try {
                            const cached = JSON.parse(cache);
                            if (cached && cached.length > 0) {
                                popularSelects(cached);
                                mostrarNotificacao('📡 Usando professores em cache', 'warning');
                                return;
                            }
                        } catch(e) {}
                    }
                    popularSelects([]);
                    mostrarNotificacao('⚠️ Nenhum professor encontrado', 'warning');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('❌ Erro ao carregar professores:', error);
                // Tentar cache
                const cache = localStorage.getItem('professores_cache');
                if (cache) {
                    try {
                        const cached = JSON.parse(cache);
                        if (cached && cached.length > 0) {
                            popularSelects(cached);
                            mostrarNotificacao('📡 Usando professores em cache (offline)', 'warning');
                            return;
                        }
                    } catch(e) {}
                }
                popularSelects([]);
                mostrarNotificacao('❌ Erro ao carregar professores', 'danger');
            }
        });
    } else {
        // Modo offline - usar cache
        const cache = localStorage.getItem('professores_cache');
        if (cache) {
            try {
                const cached = JSON.parse(cache);
                if (cached && cached.length > 0) {
                    popularSelects(cached);
                    mostrarNotificacao('📡 Modo offline - usando cache', 'warning');
                    return;
                }
            } catch(e) {}
        }
        popularSelects([]);
        mostrarNotificacao('📡 Modo offline - sem dados de professores', 'warning');
    }
}


// ============================================
// CARREGAR CLASSES
// ============================================
function carregarClasses() {
    const select = document.getElementById('selectClasse');
    
    // Primeiro tentar usar dados do PHP
    if (dadosTurmas && dadosTurmas.length > 0) {
        const classes = [];
        dadosTurmas.forEach(turma => {
            if (turma.classe && !classes.includes(turma.classe)) {
                classes.push(turma.classe);
            }
        });
        
        if (classes.length > 0) {
            classes.sort();
            select.innerHTML = '<option value="">Selecione...</option>';
            classes.forEach(classe => {
                const opt = document.createElement('option');
                opt.value = classe;
                opt.textContent = classe;
                select.appendChild(opt);
            });
            console.log('✅ Classes carregadas do PHP:', classes.length);
            return;
        }
    }
    
    // Se não tiver dados no PHP, tentar AJAX
    if (navigator.onLine) {
        showLoading();
        $.ajax({
            url: '/softgest_web/api/turmas/classes.php',
            method: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                hideLoading();
                if (response.success && response.classes && response.classes.length > 0) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    response.classes.forEach(classe => {
                        const opt = document.createElement('option');
                        opt.value = classe;
                        opt.textContent = classe;
                        select.appendChild(opt);
                    });
                    console.log('✅ Classes carregadas via AJAX:', response.classes.length);
                } else {
                    select.innerHTML = '<option value="">Nenhuma classe encontrada</option>';
                    mostrarNotificacao('⚠️ Nenhuma classe encontrada', 'warning');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('❌ Erro ao carregar classes:', error);
                select.innerHTML = '<option value="">Erro ao carregar classes</option>';
                mostrarNotificacao('❌ Erro ao carregar classes', 'danger');
            }
        });
    } else {
        select.innerHTML = '<option value="">Modo offline - sem classes</option>';
        mostrarNotificacao('📡 Modo offline - conecte-se para carregar classes', 'warning');
    }
}

// ============================================
// CARREGAR TURMAS
// ============================================
function carregarTurmas() {
    const select = document.getElementById('selectTurma');
    const editSelect = document.getElementById('editTurma');
    
    if (dadosTurmas && dadosTurmas.length > 0) {
        [select, editSelect].forEach(sel => {
            if (sel) {
                sel.innerHTML = '<option value="">Selecione...</option>';
                dadosTurmas.forEach(turma => {
                    const opt = document.createElement('option');
                    opt.value = turma.id;
                    const label = turma.nome + ' - ' + (turma.classe || '') + ' (' + (turma.turno || '') + ')';
                    opt.textContent = label;
                    sel.appendChild(opt);
                });
            }
        });
        console.log('✅ Turmas carregadas:', dadosTurmas.length);
        return true;
    }
    
    // Fallback AJAX
    if (navigator.onLine) {
        showLoading();
        $.ajax({
            url: '/softgest_web/api/turmas/listar.php',
            method: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                hideLoading();
                if (response.success && response.turmas) {
                    [select, editSelect].forEach(sel => {
                        if (sel) {
                            sel.innerHTML = '<option value="">Selecione...</option>';
                            response.turmas.forEach(turma => {
                                const opt = document.createElement('option');
                                opt.value = turma.id;
                                opt.textContent = turma.nome + ' - ' + (turma.classe || '') + ' (' + (turma.turno || '') + ')';
                                sel.appendChild(opt);
                            });
                        }
                    });
                    console.log('✅ Turmas carregadas via AJAX:', response.turmas.length);
                }
            },
            error: function() {
                hideLoading();
                mostrarNotificacao('❌ Erro ao carregar turmas', 'danger');
            }
        });
    }
}

// ============================================
// CARREGAR DISCIPLINAS DO SISTEMA
// ============================================
function carregarDisciplinas() {
    const select = document.getElementById('selectDisciplinas');
    const editSelect = document.getElementById('editDisciplinas');
    
    if (dadosDisciplinas && dadosDisciplinas.length > 0) {
        [select, editSelect].forEach(sel => {
            if (sel) {
                sel.innerHTML = '';
                dadosDisciplinas.forEach(disc => {
                    const opt = document.createElement('option');
                    opt.value = disc.id;
                    opt.textContent = disc.nome;
                    sel.appendChild(opt);
                });
            }
        });
        console.log('✅ Disciplinas carregadas:', dadosDisciplinas.length);
        return true;
    }
    
    // Fallback AJAX
    if (navigator.onLine) {
        showLoading();
        $.ajax({
            url: '/softgest_web/api/disciplinas/listar.php',
            method: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                hideLoading();
                if (response.success && response.disciplinas) {
                    [select, editSelect].forEach(sel => {
                        if (sel) {
                            sel.innerHTML = '';
                            response.disciplinas.forEach(disc => {
                                const opt = document.createElement('option');
                                opt.value = disc.id;
                                opt.textContent = disc.nome;
                                sel.appendChild(opt);
                            });
                        }
                    });
                    console.log('✅ Disciplinas carregadas via AJAX:', response.disciplinas.length);
                }
            },
            error: function() {
                hideLoading();
                mostrarNotificacao('❌ Erro ao carregar disciplinas', 'danger');
            }
        });
    }
}

// ============================================
// CARREGAR DISCIPLINAS DAS TURMAS (FALLBACK)
// ============================================
function carregarDisciplinasDasTurmasFallback(turmasIds) {
    const disciplinasTurmaSelect = document.getElementById('selectDisciplinasTurma');
    disciplinasTurmaSelect.innerHTML = '';
    
    let disciplinasEncontradas = [];
    
    turmasIds.forEach(id => {
        const turma = dadosTurmas.find(t => t.id == id);
        if (turma && turma.disciplinas) {
            const disc = turma.disciplinas.split(',').map(d => d.trim());
            disc.forEach(d => {
                if (d && !disciplinasEncontradas.includes(d)) {
                    disciplinasEncontradas.push(d);
                }
            });
        }
    });
    
    if (disciplinasEncontradas.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Nenhuma disciplina encontrada';
        disciplinasTurmaSelect.appendChild(opt);
        mostrarNotificacao('⚠️ Nenhuma disciplina encontrada nas turmas selecionadas', 'warning');
        return;
    }
    
    disciplinasEncontradas.sort().forEach(disc => {
        const opt = document.createElement('option');
        opt.value = disc;
        opt.textContent = disc;
        opt.selected = true;
        disciplinasTurmaSelect.appendChild(opt);
    });
    
    mostrarNotificacao('✅ ' + disciplinasEncontradas.length + ' disciplinas carregadas', 'success');
    console.log('📚 Disciplinas carregadas:', disciplinasEncontradas);
}

// ============================================
// CARREGAR DISCIPLINAS DAS TURMAS SELECIONADAS
// ============================================
function carregarDisciplinasDasTurmas() {
    const turmaSelect = document.getElementById('selectTurma');
    const turmasSelecionadas = Array.from(turmaSelect.selectedOptions).map(opt => opt.value);
    
    if (turmasSelecionadas.length === 0) {
        mostrarNotificacao('⚠️ Selecione pelo menos uma turma', 'warning');
        return;
    }
    
    const disciplinasTurmaSelect = document.getElementById('selectDisciplinasTurma');
    disciplinasTurmaSelect.innerHTML = '<option value="">Carregando...</option>';
    
    if (navigator.onLine) {
        showLoading();
        $.ajax({
            url: '/softgest_web/api/turmas/disciplinas.php',
            method: 'POST',
            data: { turmas: turmasSelecionadas.join(',') },
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                hideLoading();
                if (response.success && response.disciplinas && response.disciplinas.length > 0) {
                    disciplinasTurmaSelect.innerHTML = '';
                    response.disciplinas.forEach(disc => {
                        const opt = document.createElement('option');
                        opt.value = disc;
                        opt.textContent = disc;
                        opt.selected = true;
                        disciplinasTurmaSelect.appendChild(opt);
                    });
                    mostrarNotificacao('✅ ' + response.disciplinas.length + ' disciplinas carregadas', 'success');
                } else {
                    carregarDisciplinasDasTurmasFallback(turmasSelecionadas);
                }
            },
            error: function() {
                hideLoading();
                carregarDisciplinasDasTurmasFallback(turmasSelecionadas);
            }
        });
    } else {
        carregarDisciplinasDasTurmasFallback(turmasSelecionadas);
    }
}

// ============================================
// CARREGAR DISTRIBUIÇÕES
// ============================================
function carregarDistribuicoes() {
    if (dadosDistribuicoes && dadosDistribuicoes.length > 0) {
        renderizarDistribuicoes(dadosDistribuicoes);
        document.getElementById('totalDistribuicoes').textContent = dadosDistribuicoes.length;
        document.getElementById('distribCount').textContent = dadosDistribuicoes.length;
        return true;
    }
    
    if (navigator.onLine) {
        showLoading();
        $.ajax({
            url: '/softgest_web/api/distribuicao/listar.php',
            method: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                hideLoading();
                if (response.success && response.distribuicoes) {
                    renderizarDistribuicoes(response.distribuicoes);
                    document.getElementById('totalDistribuicoes').textContent = response.distribuicoes.length;
                    document.getElementById('distribCount').textContent = response.distribuicoes.length;
                }
            },
            error: function() {
                hideLoading();
                mostrarNotificacao('❌ Erro ao carregar distribuições', 'danger');
            }
        });
    }
}

// ============================================
// RENDERIZAR DISTRIBUIÇÕES
// ============================================
function renderizarDistribuicoes(distribuicoes) {
    const tbody = document.getElementById('corpoDistribuicoes');
    tbody.innerHTML = '';
    
    if (!distribuicoes || distribuicoes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Nenhuma distribuição encontrada</td></tr>';
        return;
    }
    
    distribuicoes.forEach(dist => {
        const tr = document.createElement('tr');
        tr.id = 'distRow_' + dist.id;
        tr.innerHTML = `
            <td><strong>${dist.professor_nome || 'N/A'}</strong></td>
            <td>${dist.turma_nome || 'N/A'}</td>
            <td><span class="badge bg-info">${dist.classe || 'N/A'}</span></td>
            <td>${dist.disciplinas || '-'}</td>
            <td><span class="badge bg-secondary">${dist.ano_letivo || '2026'}</span></td>
            <td>
                <button class="action-btn" onclick="editarDistribuicao(${dist.id})">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="action-btn text-danger" onclick="excluirDistribuicao(${dist.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// ============================================
// ADICIONAR DISTRIBUIÇÃO - VERSÃO SIMPLES E CORRIGIDA
// ============================================
function adicionarDistribuicao() {
    console.log('🔄 Função adicionarDistribuicao chamada!');
    
    // PEGAR O PROFESSOR
    var selectProfessor = document.getElementById('selectProfessor');
    var professorId = selectProfessor.value;
    
    console.log('📌 Professor ID:', professorId);
    console.log('📌 Select options:', selectProfessor.options.length);
    
    // Se o valor for vazio, pegar do primeiro option com valor
    if (!professorId || professorId === '' || professorId === 'null') {
        for (var i = 0; i < selectProfessor.options.length; i++) {
            var opt = selectProfessor.options[i];
            if (opt.value && opt.value !== '' && opt.value !== 'null') {
                professorId = opt.value;
                console.log('📌 ID encontrado no option ' + i + ':', professorId);
                break;
            }
        }
    }
    
    // Se ainda não tiver ID, tentar buscar no texto
    if (!professorId || professorId === '' || professorId === 'null') {
        var selectedOption = selectProfessor.options[selectProfessor.selectedIndex];
        if (selectedOption && selectedOption.text) {
            // Tentar extrair números do texto
            var match = selectedOption.text.match(/\d+/);
            if (match) {
                professorId = match[0];
                console.log('📌 ID extraído do texto:', professorId);
            }
        }
    }
    
    // VALIDAÇÃO FINAL
    if (!professorId || professorId === '' || professorId === 'null') {
        alert('❌ Selecione um professor válido');
        return;
    }
    
    // PEGAR OS OUTROS VALORES
    var turmaSelect = document.getElementById('selectTurma');
    var classeSelect = document.getElementById('selectClasse');
    var disciplinasSelect = document.getElementById('selectDisciplinas');
    var disciplinasTurmaSelect = document.getElementById('selectDisciplinasTurma');
    
    // Capturar turmas selecionadas
    var turmas = [];
    for (var i = 0; i < turmaSelect.options.length; i++) {
        if (turmaSelect.options[i].selected) {
            turmas.push(turmaSelect.options[i].value);
        }
    }
    
    // Capturar classes selecionadas
    var classes = [];
    for (var i = 0; i < classeSelect.options.length; i++) {
        if (classeSelect.options[i].selected) {
            classes.push(classeSelect.options[i].value);
        }
    }
    
    // Capturar disciplinas selecionadas
    var disciplinas = [];
    for (var i = 0; i < disciplinasSelect.options.length; i++) {
        if (disciplinasSelect.options[i].selected) {
            disciplinas.push(disciplinasSelect.options[i].value);
        }
    }
    
    // Capturar disciplinas da turma selecionadas
    var disciplinasTurma = [];
    for (var i = 0; i < disciplinasTurmaSelect.options.length; i++) {
        if (disciplinasTurmaSelect.options[i].selected) {
            disciplinasTurma.push(disciplinasTurmaSelect.options[i].value);
        }
    }
    
    var anoLetivo = document.getElementById('anoLetivoDist').value;
    
    console.log('📤 Dados:', {
        professor_id: professorId,
        turmas: turmas,
        classes: classes,
        disciplinas: disciplinas,
        disciplinasTurma: disciplinasTurma,
        ano_letivo: anoLetivo
    });
    
    // VALIDAR
    if (turmas.length === 0) {
        alert('⚠️ Selecione pelo menos uma turma');
        return;
    }
    
    if (classes.length === 0) {
        alert('⚠️ Selecione pelo menos uma classe');
        return;
    }
    
    // COMBINAR DISCIPLINAS
    var todasDisciplinas = disciplinas.slice();
    for (var i = 0; i < disciplinasTurma.length; i++) {
        if (disciplinasTurma[i] && todasDisciplinas.indexOf(disciplinasTurma[i]) === -1) {
            todasDisciplinas.push(disciplinasTurma[i]);
        }
    }
    
    if (todasDisciplinas.length === 0) {
        alert('⚠️ Selecione pelo menos uma disciplina');
        return;
    }
    
    // MOSTRAR LOADING
    document.getElementById('loadingOverlay').classList.add('active');
    
    // ENVIAR PARA O SERVIDOR
    $.ajax({
        url: '/softgest_web/api/distribuicao/adicionar.php',
        method: 'POST',
        data: {
            professor_id: professorId,
            turmas: turmas.join(','),
            classes: classes.join(','),
            disciplinas: todasDisciplinas.join(','),
            ano_letivo: anoLetivo
        },
        dataType: 'json',
        timeout: 15000,
        success: function(response) {
            document.getElementById('loadingOverlay').classList.remove('active');
            console.log('📥 Resposta:', response);
            if (response.success) {
                alert('✅ ' + response.message);
                limparFormDistribuicao();
                location.reload();
            } else {
                alert('❌ ' + (response.message || 'Erro ao adicionar'));
            }
        },
        error: function(xhr) {
            document.getElementById('loadingOverlay').classList.remove('active');
            console.error('❌ Erro:', xhr.responseText);
            alert('❌ Erro ao comunicar com o servidor');
        }
    });
}


// ============================================
// EXCLUIR DISTRIBUIÇÃO
// ============================================
function excluirDistribuicao(id) {
    if (!confirm('Tem certeza que deseja excluir esta distribuição?')) return;
    if (!navigator.onLine) {
        mostrarNotificacao('❌ Sem conexão com a rede.', 'danger');
        return;
    }
    showLoading();
    $.ajax({
        url: '/softgest_web/api/distribuicao/excluir.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            hideLoading();
            if (response.success) {
                mostrarNotificacao('✅ Distribuição excluída!', 'success');
                carregarDistribuicoes();
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

function editarDistribuicao(id) {
    mostrarNotificacao('✏️ Editar distribuição #' + id + ' (em desenvolvimento)', 'info');
}

function salvarEdicaoDistribuicao() {
    mostrarNotificacao('💾 Salvando edição... (em desenvolvimento)', 'info');
}

// ============================================
// LIMPAR FORMULÁRIO
// ============================================
function limparFormDistribuicao() {
    document.getElementById('selectProfessor').value = '';
    document.getElementById('anoLetivoDist').value = '2026';
    document.querySelectorAll('#selectTurma option').forEach(opt => opt.selected = false);
    document.querySelectorAll('#selectClasse option').forEach(opt => opt.selected = false);
    document.querySelectorAll('#selectDisciplinas option').forEach(opt => opt.selected = false);
    document.querySelectorAll('#selectDisciplinasTurma option').forEach(opt => opt.selected = false);
    document.getElementById('selectDisciplinasTurma').innerHTML = '<option value="">Selecione uma turma primeiro</option>';
}

function exportarDistribuicaoExcel() {
    mostrarNotificacao('📊 Exportação para Excel iniciada!', 'info');
}

// ============================================
// INICIALIZAR
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('📋 Template de Distribuição carregado!');
    console.log('📊 Status da rede:', navigator.onLine ? 'Online' : 'Offline');
    
    carregarProfessores();
    carregarClasses();
    carregarTurmas();
    carregarDisciplinas();
    carregarDistribuicoes();
    
    if (!navigator.onLine) {
        mostrarNotificacao('📡 Modo offline - Usando dados em cache', 'warning');
    }
});
</script>