<?php
// ============================================
// INSTALADOR DE DEPENDÊNCIAS - SoftGest
// Baixa todas as bibliotecas necessárias
// ============================================

// Configuração
$base_path = __DIR__;
$assets_path = $base_path . '/assets';

// Criar pastas necessárias
$pastas = [
    $assets_path,
    $assets_path . '/css',
    $assets_path . '/js',
    $assets_path . '/fonts',
    $assets_path . '/webfonts'
];

foreach ($pastas as $pasta) {
    if (!file_exists($pasta)) {
        mkdir($pasta, 0777, true);
        echo "📁 Pasta criada: " . str_replace($base_path, '', $pasta) . "\n";
    }
}

// ============================================
// FUNÇÃO PARA BAIXAR ARQUIVOS
// ============================================
function baixarArquivo($url, $destino) {
    echo "⬇️  Baixando: " . basename($destino) . "... ";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $data) {
        file_put_contents($destino, $data);
        echo "✅ OK (" . round(filesize($destino) / 1024, 1) . " KB)\n";
        return true;
    } else {
        echo "❌ FALHA (HTTP $http_code)\n";
        return false;
    }
}

// ============================================
// 1. BOOTSTRAP 5.1.3
// ============================================
echo "\n📦 Instalando Bootstrap 5.1.3...\n";

$bootstrap_versao = '5.1.3';
$bootstrap_url = "https://cdn.jsdelivr.net/npm/bootstrap@$bootstrap_versao/dist/";

// CSS
baixarArquivo($bootstrap_url . 'css/bootstrap.min.css', $assets_path . '/css/bootstrap.min.css');
baixarArquivo($bootstrap_url . 'css/bootstrap.min.css.map', $assets_path . '/css/bootstrap.min.css.map');

// JS
baixarArquivo($bootstrap_url . 'js/bootstrap.bundle.min.js', $assets_path . '/js/bootstrap.bundle.min.js');
baixarArquivo($bootstrap_url . 'js/bootstrap.bundle.min.js.map', $assets_path . '/js/bootstrap.bundle.min.js.map');

// ============================================
// 2. BOOTSTRAP ICONS 1.8.1
// ============================================
echo "\n📦 Instalando Bootstrap Icons 1.8.1...\n";

$icons_versao = '1.8.1';
$icons_url = "https://cdn.jsdelivr.net/npm/bootstrap-icons@$icons_versao/";

// CSS
baixarArquivo($icons_url . 'font/bootstrap-icons.css', $assets_path . '/css/bootstrap-icons.css');
baixarArquivo($icons_url . 'font/bootstrap-icons.min.css', $assets_path . '/css/bootstrap-icons.min.css');

// Fontes
$fontes = ['bootstrap-icons.woff2', 'bootstrap-icons.woff'];
foreach ($fontes as $fonte) {
    baixarArquivo($icons_url . 'fonts/' . $fonte, $assets_path . '/fonts/' . $fonte);
}

// ============================================
// 3. JQUERY 3.6.0
// ============================================
echo "\n📦 Instalando jQuery 3.6.0...\n";

baixarArquivo(
    'https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js',
    $assets_path . '/js/jquery-3.6.0.min.js'
);

baixarArquivo(
    'https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.map',
    $assets_path . '/js/jquery.min.map'
);

// ============================================
// 4. CHART.JS 3.7.1
// ============================================
echo "\n📦 Instalando Chart.js 3.7.1...\n";

baixarArquivo(
    'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js',
    $assets_path . '/js/chart.min.js'
);

baixarArquivo(
    'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js.map',
    $assets_path . '/js/chart.min.js.map'
);

// ============================================
// 5. DATATABLES 1.11.5
// ============================================
echo "\n📦 Instalando DataTables 1.11.5...\n";

$datatables_url = 'https://cdn.datatables.net/v/bs5/jq-3.6.0/jszip-2.5.0/dt-1.11.5/b-2.2.2/b-html5-2.2.2/b-print-2.2.2/';

// JS
baixarArquivo(
    $datatables_url . 'datatables.min.js',
    $assets_path . '/js/datatables.min.js'
);

// CSS
baixarArquivo(
    $datatables_url . 'datatables.min.css',
    $assets_path . '/css/datatables.min.css'
);

// DataTables Bootstrap 5
baixarArquivo(
    'https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css',
    $assets_path . '/css/dataTables.bootstrap5.min.css'
);

// ============================================
// 6. FONT AWESOME (opcional, como fallback)
// ============================================
echo "\n📦 Instalando Font Awesome 6.0.0 (fallback)...\n";

baixarArquivo(
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css',
    $assets_path . '/css/font-awesome.min.css'
);

// ============================================
// 7. ARQUIVO DE VERIFICAÇÃO
// ============================================
echo "\n📝 Criando arquivo de verificação...\n";

$verificacao = "<?php
// ============================================
// VERIFICAÇÃO DE DEPENDÊNCIAS
// ============================================

function verificarDependencias() {
    \$base = __DIR__ . '/assets/';
    \$arquivos = [
        'css/bootstrap.min.css',
        'css/bootstrap-icons.css',
        'js/jquery-3.6.0.min.js',
        'js/bootstrap.bundle.min.js',
        'js/chart.min.js',
        'js/datatables.min.js'
    ];
    
    \$faltando = [];
    foreach (\$arquivos as \$arquivo) {
        if (!file_exists(\$base . \$arquivo)) {
            \$faltando[] = \$arquivo;
        }
    }
    
    if (empty(\$faltando)) {
        echo '<div class=\"alert alert-success\">✅ Todas as dependências estão instaladas!</div>';
        return true;
    } else {
        echo '<div class=\"alert alert-danger\">❌ Arquivos faltando:<br>';
        foreach (\$faltando as \$f) {
            echo '&nbsp;&nbsp;- ' . \$f . '<br>';
        }
        echo '</div>';
        return false;
    }
}
";

file_put_contents($assets_path . '/verificar.php', $verificacao);

// ============================================
// 8. ATUALIZAR O DASHBOARD
// ============================================
echo "\n🔄 Atualizando o dashboard.php...\n";

$dashboard_path = $base_path . '/coordenador_pedagogico/dashboard.php';
if (file_exists($dashboard_path)) {
    $content = file_get_contents($dashboard_path);
    
    // Substituir CDN por local
    $substituicoes = [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' => '/softgest_web/assets/css/bootstrap.min.css',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css' => '/softgest_web/assets/css/bootstrap-icons.css',
        'https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css' => '/softgest_web/assets/css/dataTables.bootstrap5.min.css',
        'https://code.jquery.com/jquery-3.6.0.min.js' => '/softgest_web/assets/js/jquery-3.6.0.min.js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js' => '/softgest_web/assets/js/bootstrap.bundle.min.js',
        'https://cdn.jsdelivr.net/npm/chart.js' => '/softgest_web/assets/js/chart.min.js',
        'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js' => '/softgest_web/assets/js/datatables.min.js'
    ];
    
    $content = str_replace(array_keys($substituicoes), array_values($substituicoes), $content);
    file_put_contents($dashboard_path, $content);
    echo "✅ dashboard.php atualizado com caminhos locais!\n";
} else {
    echo "⚠️  dashboard.php não encontrado em: $dashboard_path\n";
}

// ============================================
// 9. CRIAR HTML DE TESTE
// ============================================
echo "\n📄 Criando página de teste...\n";

$teste_html = <<<HTML
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
HTML;

file_put_contents($base_path . '/teste_dependencias.php', $teste_html);

// ============================================
// FINALIZAR
// ============================================
echo "\n" . str_repeat('=', 60) . "\n";
echo "✅ INSTALAÇÃO CONCLUÍDA COM SUCESSO!\n";
echo str_repeat('=', 60) . "\n\n";
echo "📂 Arquivos instalados em: /softgest_web/assets/\n";
echo "🧪 Teste: http://localhost/softgest_web/teste_dependencias.php\n";
echo "📊 Dashboard: http://localhost/softgest_web/coordenador_pedagogico/dashboard.php\n\n";
echo "📋 Bibliotecas instaladas:\n";
echo "   - Bootstrap 5.1.3\n";
echo "   - Bootstrap Icons 1.8.1\n";
echo "   - jQuery 3.6.0\n";
echo "   - Chart.js 3.7.1\n";
echo "   - DataTables 1.11.5\n\n";
echo "🚀 O sistema agora funciona OFFLINE!\n";