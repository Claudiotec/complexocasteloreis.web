<?php
// ============================================
// coordenador_pedagogico/templates/relatorios.php
// Relatórios - Separado
// ============================================
?>
<div class="card-modern">
    <div class="card-header">
        <span>📈 Relatórios e Estatísticas</span>
        <div>
            <button class="btn-light me-2" onclick="gerarRelatorioPDF()">
                <i class="bi bi-file-pdf"></i> PDF
            </button>
            <button class="btn-light" onclick="gerarRelatorioExcel()">
                <i class="bi bi-file-excel"></i> Excel
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="card border mb-3">
                    <div class="card-body">
                        <h6>📊 Desempenho por Disciplina</h6>
                        <canvas id="chartDesempenho" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border mb-3">
                    <div class="card-body">
                        <h6>📊 Distribuição de Alunos</h6>
                        <canvas id="chartDistribuicao" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card border">
                    <div class="card-body">
                        <h6>📊 Taxa de Aprovação por Turma</h6>
                        <canvas id="chartAprovacao" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border">
                    <div class="card-body">
                        <h6>📊 Ocupação das Turmas</h6>
                        <canvas id="chartOcupacao" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// FUNÇÕES DE RELATÓRIOS
// ============================================
function gerarRelatorioPDF() {
    mostrarNotificacao('📄 Gerando relatório PDF...', 'info');
}

function gerarRelatorioExcel() {
    mostrarNotificacao('📊 Gerando relatório Excel...', 'info');
}

// ============================================
// GRÁFICOS
// ============================================
function initCharts() {
    // Gráfico de Desempenho por Disciplina
    const ctx1 = document.getElementById('chartDesempenho');
    if (ctx1) {
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: ['Matemática', 'Português', 'Física', 'Química', 'Biologia'],
                datasets: [{
                    label: 'Média Geral',
                    data: [14.2, 13.5, 12.8, 11.9, 13.1],
                    backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c'],
                    borderRadius: 5
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 20 } } }
        });
    }

    // Gráfico de Distribuição de Alunos
    const ctx2 = document.getElementById('chartDistribuicao');
    if (ctx2) {
        const labels = <?= json_encode(array_column($turmasAtivas, 'classe')) ?>;
        const data = <?= json_encode(array_column($turmasAtivas, 'total_alunos')) ?>;
        new Chart(ctx2, {
            type: 'doughnut',
            data: { labels: labels.length ? labels : ['10ª', '11ª', '12ª'], datasets: [{ data: data.length ? data : [45, 38, 42], backgroundColor: ['#3498db', '#2ecc71', '#f39c12'], borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    // Gráfico de Taxa de Aprovação
    const ctx3 = document.getElementById('chartAprovacao');
    if (ctx3) {
        new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($turmasAtivas, 'nome')) ?>,
                datasets: [
                    { label: 'Aprovados', data: [28, 22, 25, 20, 27], backgroundColor: '#2ecc71', borderRadius: 5 },
                    { label: 'Reprovados', data: [2, 3, 5, 2, 3], backgroundColor: '#e74c3c', borderRadius: 5 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
        });
    }

    // Gráfico de Ocupação das Turmas
    const ctx4 = document.getElementById('chartOcupacao');
    if (ctx4) {
        const turmas = <?= json_encode($turmasAtivas) ?>;
        const labels = turmas.map(t => t.nome);
        const data = turmas.map(t => {
            const cap = t.capacidade || 45;
            const alunos = t.total_alunos || 0;
            return cap > 0 ? Math.round((alunos / cap) * 100) : 0;
        });
        new Chart(ctx4, {
            type: 'horizontalBar',
            data: {
                labels: labels.length ? labels : ['10ª A', '10ª B', '11ª A'],
                datasets: [{ label: 'Ocupação (%)', data: data.length ? data : [93, 83, 77], backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6'], borderRadius: 5 }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 100 } } }
        });
    }
}
</script>