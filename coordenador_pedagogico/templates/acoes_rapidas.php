<?php
// ============================================
// coordenador_pedagogico/templates/acoes_rapidas.php
// Ações Rápidas - Separado
// ============================================
?>
<div class="card-modern">
    <div class="card-header">
        <span>⚡ Ações Rápidas</span>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <!-- Distribuir Professores -->
            <a href="#" class="quick-action" onclick="showSection('distribuicao')">
                <span class="icon">👨‍🏫</span>
                <span>Distribuir Professores</span>
                <small class="text-muted">Alocar professores às turmas</small>
            </a>
            
            <!-- Lançar Notas -->
            <a href="#" class="quick-action" onclick="showSection('bancoNotas')">
                <span class="icon">📝</span>
                <span>Lançar Notas</span>
                <small class="text-muted">Registrar notas dos alunos</small>
            </a>
            
            <!-- Gerar Pauta -->
            <a href="#" class="quick-action" onclick="showSection('pautas')">
                <span class="icon">📄</span>
                <span>Gerar Pauta</span>
                <small class="text-muted">Pautas finais e trimestrais</small>
            </a>
            
            <!-- Matricular Aluno -->
            <a href="#" class="quick-action" onclick="abrirModalAluno()">
                <span class="icon">🎓</span>
                <span>Matricular Aluno</span>
                <small class="text-muted">Novo aluno na turma</small>
            </a>
            
            <!-- Criar Turma -->
            <a href="#" class="quick-action" onclick="abrirModalTurma()">
                <span class="icon">📋</span>
                <span>Criar Turma</span>
                <small class="text-muted">Nova turma no sistema</small>
            </a>
            
            <!-- Relatórios -->
            <a href="#" class="quick-action" onclick="showSection('relatorios')">
                <span class="icon">📊</span>
                <span>Relatórios</span>
                <small class="text-muted">Análises e estatísticas</small>
            </a>
            
            <!-- Configurações -->
            <a href="#" class="quick-action" onclick="showSection('configuracoes')">
                <span class="icon">⚙️</span>
                <span>Configurações</span>
                <small class="text-muted">Prazos e notas mínimas</small>
            </a>
            
            <!-- Exportar Dados -->
            <a href="#" class="quick-action" onclick="exportarDados()">
                <span class="icon">📥</span>
                <span>Exportar Dados</span>
                <small class="text-muted">Excel, PDF e mais</small>
            </a>
        </div>
    </div>
</div>

<script>
// ============================================
// FUNÇÃO PARA EXPORTAR DADOS
// ============================================
function exportarDados() {
    mostrarNotificacao('📥 Exportação de dados iniciada!', 'info');
}
</script>