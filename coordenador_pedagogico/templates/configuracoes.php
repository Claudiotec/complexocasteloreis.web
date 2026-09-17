<?php
// ============================================
// coordenador_pedagogico/templates/configuracoes.php
// Configurações - Separado
// ============================================
?>
<div class="card-modern">
    <div class="card-header">
        <span>⚙️ Configurações</span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>📝 Prazos de Lançamento</h6>
                <div class="mb-3">
                    <label class="form-label">1º Trimestre</label>
                    <input type="date" class="form-control" id="prazo1T" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">2º Trimestre</label>
                    <input type="date" class="form-control" id="prazo2T" value="<?= date('Y-m-d', strtotime('+90 days')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">3º Trimestre</label>
                    <input type="date" class="form-control" id="prazo3T" value="<?= date('Y-m-d', strtotime('+150 days')) ?>">
                </div>
            </div>
            <div class="col-md-6">
                <h6>📊 Configurações Gerais</h6>
                <div class="mb-3">
                    <label class="form-label">Ano Letivo Atual</label>
                    <input type="text" class="form-control" id="anoLetivoConfig" value="2026">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nota Mínima para Aprovação</label>
                    <input type="number" class="form-control" id="notaMinAprovacao" value="10" min="0" max="20">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nota Mínima para Recuperação</label>
                    <input type="number" class="form-control" id="notaMinRecuperacao" value="8" min="0" max="20">
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary" onclick="salvarConfiguracoes()">
                <i class="bi bi-save"></i> Salvar Configurações
            </button>
        </div>
    </div>
</div>

<script>
// ============================================
// FUNÇÕES DE CONFIGURAÇÕES
// ============================================
function salvarConfiguracoes() {
    const data = {
        prazo1T: document.getElementById('prazo1T').value,
        prazo2T: document.getElementById('prazo2T').value,
        prazo3T: document.getElementById('prazo3T').value,
        ano_letivo: document.getElementById('anoLetivoConfig').value,
        nota_min_aprovacao: document.getElementById('notaMinAprovacao').value,
        nota_min_recuperacao: document.getElementById('notaMinRecuperacao').value
    };
    
    showLoading();
    $.ajax({
        url: '/softgest_web/api/configuracoes/salvar.php',
        method: 'POST',
        data: data,
        success: function(response) {
            hideLoading();
            if (response.success) {
                mostrarNotificacao('✅ Configurações salvas com sucesso!', 'success');
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
</script>