<?php
// ============================================
// coordenador_pedagogico/templates/modais.php
// Modais do sistema
// ============================================
?>

<!-- MODAL NOTA -->
<div class="modal fade" id="modalNota" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNotaTitle">📝 Nova Nota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNota">
                    <input type="hidden" id="notaId" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Aluno *</label>
                            <select class="form-select" id="selectAlunoNota" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Disciplina *</label>
                            <select class="form-select" id="selectDisciplinaNota" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Turma *</label>
                            <select class="form-select" id="selectTurmaNota" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Classe</label>
                            <input type="text" class="form-control" id="classeNota" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ano Letivo</label>
                            <select class="form-select" id="anoLetivoNota">
                                <option value="2026">2026</option>
                                <option value="2025">2025</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <h6 class="mt-2">Notas por Trimestre</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">1º Trimestre (MT)</label>
                                    <input type="number" class="form-control" id="nota1T" min="0" max="20" step="0.1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">2º Trimestre (MT)</label>
                                    <input type="number" class="form-control" id="nota2T" min="0" max="20" step="0.1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">3º Trimestre (MT)</label>
                                    <input type="number" class="form-control" id="nota3T" min="0" max="20" step="0.1">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="obsNota" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarNota()">
                    <i class="bi bi-save"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ALUNO -->
<div class="modal fade" id="modalAluno" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🎓 Matricular Aluno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAluno">
                    <div class="mb-3">
                        <label class="form-label">Nome do Aluno *</label>
                        <input type="text" class="form-control" id="nomeAluno" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Turma *</label>
                        <select class="form-select" id="turmaAluno" required>
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data de Nascimento</label>
                        <input type="date" class="form-control" id="dataNascAluno">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contacto</label>
                        <input type="text" class="form-control" id="contactoAluno" placeholder="Telefone do encarregado">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarAluno()">
                    <i class="bi bi-save"></i> Matricular
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TURMA -->
<div class="modal fade" id="modalTurma" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📋 Criar Nova Turma</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formTurma">
                    <div class="mb-3">
                        <label class="form-label">Nome da Turma *</label>
                        <input type="text" class="form-control" id="nomeTurma" placeholder="Ex: 10ª A" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Classe *</label>
                        <select class="form-select" id="classeTurma" required>
                            <option value="">Selecione...</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>ª Classe"><?= $i ?>ª Classe</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Turno</label>
                        <select class="form-select" id="turnoTurma">
                            <option value="Manhã">Manhã</option>
                            <option value="Tarde">Tarde</option>
                            <option value="Noite">Noite</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacidade</label>
                        <input type="number" class="form-control" id="capacidadeTurma" value="45" min="1" max="60">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ano Letivo</label>
                        <input type="text" class="form-control" id="anoTurma" value="2026">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarTurma()">
                    <i class="bi bi-save"></i> Criar Turma
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ===== MODAL EDITAR DISTRIBUIÇÃO ===== -->
<div class="modal fade" id="modalEditarDistribuicao" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square"></i> Editar Distribuição
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarDistribuicao">
                    <input type="hidden" id="editDistId" value="">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Professor *</label>
                            <select class="form-select" id="editProfessor" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($professoresList as $prof): ?>
                                    <option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Turma *</label>
                            <select class="form-select" id="editTurma" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($turmasAtivas as $turma): ?>
                                    <option value="<?= $turma['id'] ?>"><?= htmlspecialchars($turma['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ano Letivo</label>
                            <input type="text" class="form-control" id="editAnoLetivo" value="2026">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Disciplinas *</label>
                            <select class="form-select" id="editDisciplinas" multiple style="height:150px;" required>
                                <?php foreach ($disciplinasList as $disc): ?>
                                    <option value="<?= $disc['id'] ?>"><?= htmlspecialchars($disc['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Segure Ctrl (Cmd no Mac) para selecionar múltiplas</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarEdicaoDistribuicao()">
                    <i class="bi bi-save"></i> Salvar Alterações
                </button>
            </div>
        </div>
    </div>
</div>