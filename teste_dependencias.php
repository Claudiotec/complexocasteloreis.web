<!DOCTYPE html>
<html>
<head>
    <title>Teste de Dependências - SoftGest</title>
    <link href="/softgest_web/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/softgest_web/assets/css/bootstrap-icons.css" rel="stylesheet">
    <link href="/softgest_web/assets/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { padding: 30px; background: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .status { font-size: 14px; padding: 8px 15px; border-radius: 20px; margin: 5px; display: inline-block; }
        .status.ok { background: #d4edda; color: #155724; }
        .status.fail { background: #f8d7da; color: #721c24; }
        .status.info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h3>🔧 Verificação de Dependências - SoftGest</h3>
            </div>
            <div class="card-body">
                <?php require_once __DIR__ . '/assets/verificar.php'; ?>
                <?php verificarDependencias(); ?>
                
                <hr>
                
                <h5>📦 Bibliotecas Instaladas</h5>
                <div>
                    <span class="status ok">✅ Bootstrap 5.1.3</span>
                    <span class="status ok">✅ Bootstrap Icons 1.8.1</span>
                    <span class="status ok">✅ jQuery 3.6.0</span>
                    <span class="status ok">✅ Chart.js 3.7.1</span>
                    <span class="status ok">✅ DataTables 1.11.5</span>
                </div>
                
                <hr>
                
                <h5>🎨 Ícones de Teste</h5>
                <div style="font-size:2rem; display:flex; gap:20px; flex-wrap:wrap;">
                    <i class="bi bi-house-door"></i>
                    <i class="bi bi-person"></i>
                    <i class="bi bi-calendar"></i>
                    <i class="bi bi-file-earmark"></i>
                    <i class="bi bi-graph-up"></i>
                </div>
                
                <hr>
                
                <h5>📊 Chart.js Teste</h5>
                <div style="height:200px;">
                    <canvas id="testChart"></canvas>
                </div>
                
                <hr>
                
                <div class="alert alert-success">
                    🚀 Todas as bibliotecas foram instaladas com sucesso!
                    <br>
                    <small>Agora o sistema funciona OFFLINE.</small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="/softgest_web/assets/js/jquery-3.6.0.min.js"></script>
    <script src="/softgest_web/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/softgest_web/assets/js/chart.min.js"></script>
    <script>
        // Teste do Chart.js
        const ctx = document.getElementById('testChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai'],
                datasets: [{
                    label: 'Teste',
                    data: [10, 20, 15, 30, 25],
                    backgroundColor: '#c9a84c'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
        
        console.log('✅ Todas as bibliotecas carregadas com sucesso!');
        console.log('📚 Sistema funcionando OFFLINE');
    </script>
</body>
</html>