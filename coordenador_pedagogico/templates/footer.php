<?php
// ============================================
// coordenador_pedagogico/templates/footer.php
// ============================================
?>
        </div>
    </main>
</div>

<!-- ===== SCRIPTS GLOBAIS ===== -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="assets/js/dashboard.js"></script>

<script>
// ============================================
// FUNÇÕES GLOBAIS
// ============================================

// Sidebar
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

// Relógio
function updateDateTime() {
    const now = new Date();
    const options = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    const el = document.getElementById('currentTime');
    if (el) el.textContent = now.toLocaleDateString('pt-BR', options);
}
setInterval(updateDateTime, 1000);
updateDateTime();

// Loading
function showLoading() {
    document.getElementById('loadingOverlay').classList.add('active');
}
function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('active');
}

// Notificações
function mostrarNotificacao(mensagem, tipo = 'info') {
    let container = document.getElementById('notificacoesContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificacoesContainer';
        document.body.appendChild(container);
    }
    
    const cores = { 'info': '#3498db', 'success': '#27ae60', 'warning': '#f39c12', 'danger': '#e74c3c' };
    const toast = document.createElement('div');
    toast.className = `notification-toast ${tipo}`;
    toast.innerHTML = `
        <div style="display:flex;align-items:start;gap:10px;">
            <div style="color:${cores[tipo]};font-size:1.2rem;">
                <i class="bi bi-${tipo === 'danger' ? 'exclamation-triangle' : 'bell-fill'}"></i>
            </div>
            <div style="flex:1;">
                <div style="font-weight:bold;">${tipo === 'danger' ? '⚠️ Alerta' : '📢 Notificação'}</div>
                <div style="font-size:0.85rem;color:#666;">${mensagem}</div>
                <div style="font-size:0.7rem;color:#999;margin-top:5px;">${new Date().toLocaleTimeString()}</div>
            </div>
            <button class="btn-close" style="font-size:0.7rem;" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 8000);
}

// Navegação
function showSection(section) {
    document.querySelectorAll('[id^="section-"]').forEach(el => el.style.display = 'none');
    const target = document.getElementById('section-' + section);
    if (target) target.style.display = 'block';
    
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.classList.remove('active');
        if (link.dataset.section === section) link.classList.add('active');
    });
    
    const titles = {
        'dashboard': 'Dashboard Pedagógico',
        'distribuicao': 'Distribuição de Professores',
        'bancoNotas': 'Banco de Notas',
        'pautas': 'Pautas',
        'relatorios': 'Relatórios e Estatísticas',
        'configuracoes': 'Configurações'
    };
    document.getElementById('pageTitle').textContent = titles[section] || 'Dashboard';
    
    closeSidebar();
}

// Funções dos Modais
function abrirModalAluno() {
    const modal = new bootstrap.Modal(document.getElementById('modalAluno'));
    document.getElementById('formAluno').reset();
    modal.show();
}

function abrirModalTurma() {
    const modal = new bootstrap.Modal(document.getElementById('modalTurma'));
    document.getElementById('formTurma').reset();
    document.getElementById('capacidadeTurma').value = 45;
    document.getElementById('anoTurma').value = '2026';
    modal.show();
}

function verDetalhesTurma(id) {
    mostrarNotificacao('📋 Detalhes da turma #' + id, 'info');
}

function editarTurma(id) {
    mostrarNotificacao('✏️ Editar turma #' + id, 'info');
}

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Fechar sidebar em mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.querySelector('.btn-toggle-sidebar');
        if (window.innerWidth <= 992 && sidebar.classList.contains('open')) {
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                closeSidebar();
            }
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) closeSidebar();
    });
    
    setTimeout(() => {
        mostrarNotificacao('👋 Bem-vindo ao Dashboard do Coordenador Pedagógico!', 'success');
    }, 1500);
    
    console.log('📋 Dashboard do Coordenador Pedagógico inicializado!');
});
</script>

</body>
</html>