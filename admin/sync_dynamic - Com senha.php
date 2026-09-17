<?php
// ============================================
// sync_dynamic.php - Sincronização Dinâmica
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

// ============================================
// CONFIGURAÇÕES
// ============================================

// Local (MySQL - XAMPP)
define('DB_LOCAL_HOST', 'localhost');
define('DB_LOCAL_PORT', '3306');
define('DB_LOCAL_NAME', 'softgest_db');
define('DB_LOCAL_USER', 'root');
define('DB_LOCAL_PASS', 'Claudtec');

// Público (PostgreSQL - Neon.tech)
define('DB_PUBLIC_HOST', 'ep-holy-resonance-atm1ick2-pooler.c-9.us-east-1.aws.neon.tech');
define('DB_PUBLIC_PORT', '5432');
define('DB_PUBLIC_NAME', 'neondb');
define('DB_PUBLIC_USER', 'neondb_owner');
define('DB_PUBLIC_PASS', 'npg_gRkKHXNJAI40');
define('DB_PUBLIC_ENDPOINT', 'ep-holy-resonance-atm1ick2-pooler');

// ============================================
// PASTA DE DESTINO
// ============================================

define('SYNC_FOLDER', 'F:/Nova pasta/');

// ============================================
// FUNÇÕES DE CONEXÃO
// ============================================

function conectarLocal() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_LOCAL_HOST . ";dbname=" . DB_LOCAL_NAME . ";charset=utf8mb4",
            DB_LOCAL_USER,
            DB_LOCAL_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro Local: " . $e->getMessage());
    }
}

function conectarPublico() {
    try {
        $options = 'endpoint=' . DB_PUBLIC_ENDPOINT;
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;sslmode=require;options='%s'",
            DB_PUBLIC_HOST,
            DB_PUBLIC_PORT,
            DB_PUBLIC_NAME,
            $options
        );
        
        $pdo = new PDO($dsn, DB_PUBLIC_USER, DB_PUBLIC_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 30
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro Público: " . $e->getMessage());
    }
}

// ============================================
// FUNÇÃO DE SINCRONIZAÇÃO DINÂMICA
// ============================================

function sincronizarDinamico($direcao = 'local_para_publico') {
    $resultados = [
        'success' => true,
        'message' => '',
        'tabelas' => [],
        'total_registros' => 0,
        'data_hora' => date('Y-m-d H:i:s')
    ];
    
    try {
        // Garantir que a pasta existe
        if (!is_dir(SYNC_FOLDER)) {
            mkdir(SYNC_FOLDER, 0755, true);
        }
        
        if ($direcao === 'local_para_publico') {
            $origem = conectarLocal();
            $destino = conectarPublico();
            $tabelas = listarTabelasMySQL($origem);
        } else {
            $origem = conectarPublico();
            $destino = conectarLocal();
            $tabelas = listarTabelasPostgres($origem);
        }
        
        foreach ($tabelas as $tabela) {
            try {
                // Buscar dados da origem
                $stmt = $origem->query("SELECT * FROM $tabela");
                $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($dados)) {
                    continue;
                }
                
                // Salvar em JSON para sincronização dinâmica
                $arquivo_json = SYNC_FOLDER . $tabela . '.json';
                file_put_contents($arquivo_json, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                // Também salvar em CSV
                $arquivo_csv = SYNC_FOLDER . $tabela . '.csv';
                $csv = fopen($arquivo_csv, 'w');
                if (!empty($dados)) {
                    fputcsv($csv, array_keys($dados[0]));
                    foreach ($dados as $linha) {
                        fputcsv($csv, $linha);
                    }
                }
                fclose($csv);
                
                $resultados['tabelas'][] = [
                    'nome' => $tabela,
                    'registros' => count($dados),
                    'json' => basename($arquivo_json),
                    'csv' => basename($arquivo_csv)
                ];
                
                $resultados['total_registros'] += count($dados);
                
            } catch (Exception $e) {
                $resultados['message'] .= "Erro na tabela $tabela: " . $e->getMessage() . "\n";
            }
        }
        
        // Salvar arquivo de status
        $status = [
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
            'direcao' => $direcao,
            'tabelas' => count($tabelas),
            'registros' => $resultados['total_registros']
        ];
        file_put_contents(SYNC_FOLDER . 'status.json', json_encode($status, JSON_PRETTY_PRINT));
        
        $resultados['message'] = "Sincronização concluída com sucesso!";
        
    } catch (Exception $e) {
        $resultados['success'] = false;
        $resultados['message'] = $e->getMessage();
    }
    
    return $resultados;
}

function listarTabelasMySQL($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

function listarTabelasPostgres($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

// ============================================
// PROCESSAR AÇÕES
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $acao = $_POST['acao'] ?? '';
    $direcao = $_POST['direcao'] ?? 'local_para_publico';
    
    if ($acao === 'sincronizar_dinamico') {
        $resultado = sincronizarDinamico($direcao);
        echo json_encode($resultado);
        exit;
    }
    
    if ($acao === 'status') {
        $status_file = SYNC_FOLDER . 'status.json';
        if (file_exists($status_file)) {
            echo file_get_contents($status_file);
        } else {
            echo json_encode(['error' => 'Nenhuma sincronização realizada ainda']);
        }
        exit;
    }
    
    if ($acao === 'listar_arquivos') {
        $arquivos = [];
        if (is_dir(SYNC_FOLDER)) {
            $files = scandir(SYNC_FOLDER);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $caminho = SYNC_FOLDER . $file;
                    $arquivos[] = [
                        'nome' => $file,
                        'tamanho' => round(filesize($caminho) / 1024, 2) . ' KB',
                        'data' => date('Y-m-d H:i:s', filemtime($caminho)),
                        'extensao' => pathinfo($file, PATHINFO_EXTENSION)
                    ];
                }
            }
        }
        echo json_encode($arquivos);
        exit;
    }
}

// ============================================
// INTERFACE HTML
// ============================================

if (!isset($_POST['acao'])) {
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronização Dinâmica - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        .header {
            background: rgba(255,255,255,0.95);
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .header h1 {
            font-size: 32px;
            color: #1a2332;
        }
        .header h1 span { color: #764ba2; }
        .header p { color: #64748b; margin-top: 5px; }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .card h2 {
            font-size: 20px;
            color: #1a2332;
            margin-bottom: 20px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        
        .status-bar {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 15px;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-item .label { color: #64748b; font-size: 13px; }
        .status-item .value { font-weight: 700; color: #1a2332; }
        .status-item .online { color: #2ecc71; }
        .status-item .offline { color: #e74c3c; }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,0,0,0.15); }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-success { background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; }
        .btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
        .btn-secondary { background: #e2e8f0; color: #1a2332; }
        
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #764ba2;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            margin-top: 15px;
            font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .file-item {
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-item .nome { font-weight: 500; color: #1a2332; font-size: 13px; }
        .file-item .info { color: #94a3b8; font-size: 12px; }
        .file-item .badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #667eea;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea15, #764ba215);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .stat-box .numero {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
        }
        .stat-box .label {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
        
        .direcao-select {
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            min-width: 200px;
        }
        .direcao-select:focus { border-color: #667eea; outline: none; }
        
        @media (max-width: 768px) {
            .header h1 { font-size: 24px; }
            .status-bar { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔄 <span>Sincronização Dinâmica</span></h1>
        <p>Sincronização em tempo real para: <strong>F:\Nova pasta\</strong> | Atualização a cada 1 segundo</p>
    </div>
    
    <div class="card">
        <h2>⚙️ Controle de Sincronização</h2>
        
        <div class="status-bar">
            <div class="status-item">
                <span class="label">📁 Pasta:</span>
                <span class="value">F:\Nova pasta\</span>
            </div>
            <div class="status-item">
                <span class="label">🔄 Status:</span>
                <span id="statusSync" class="value offline">⏹️ Parado</span>
            </div>
            <div class="status-item">
                <span class="label">⏱️ Última sincronização:</span>
                <span id="ultimaSync" class="value">Nunca</span>
            </div>
            <div class="status-item">
                <span class="label">📊 Tabelas:</span>
                <span id="totalTabelas" class="value">0</span>
            </div>
            <div class="status-item">
                <span class="label">📝 Registros:</span>
                <span id="totalRegistros" class="value">0</span>
            </div>
        </div>
        
        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 15px;">
            <select id="direcaoSync" class="direcao-select">
                <option value="local_para_publico">💻 Local → Público</option>
                <option value="publico_para_local">☁️ Público → Local</option>
            </select>
            
            <div class="btn-group">
                <button id="btnIniciar" class="btn btn-success" onclick="iniciarSync()">
                    ▶️ Iniciar Sincronização
                </button>
                <button id="btnParar" class="btn btn-danger" onclick="pararSync()" disabled>
                    ⏹️ Parar
                </button>
                <button id="btnSyncAgora" class="btn btn-primary" onclick="syncAgora()">
                    🔄 Sincronizar Agora
                </button>
                <button class="btn btn-secondary" onclick="carregarStatus()">
                    🔄 Atualizar Status
                </button>
            </div>
        </div>
        
        <div id="mensagem" style="display: none;" class="alert"></div>
        
        <div class="stats-grid">
            <div class="stat-box">
                <div class="numero" id="statTabelas">0</div>
                <div class="label">📋 Tabelas</div>
            </div>
            <div class="stat-box">
                <div class="numero" id="statRegistros">0</div>
                <div class="label">📝 Registros</div>
            </div>
            <div class="stat-box">
                <div class="numero" id="statArquivos">0</div>
                <div class="label">📁 Arquivos</div>
            </div>
            <div class="stat-box">
                <div class="numero" id="statTamanho">0 KB</div>
                <div class="label">💾 Tamanho Total</div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2>📁 Arquivos Sincronizados</h2>
        <div id="listaArquivos" style="max-height: 400px; overflow-y: auto;">
            <div style="text-align: center; color: #94a3b8; padding: 20px;">
                ⏳ Carregando arquivos...
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// VARIÁVEIS
// ============================================
let intervalo = null;
let syncAtivo = false;

// ============================================
// FUNÇÕES PRINCIPAIS
// ============================================

function iniciarSync() {
    if (syncAtivo) {
        mostrarMensagem('⚠️ A sincronização já está em andamento', 'info');
        return;
    }
    
    syncAtivo = true;
    document.getElementById('btnIniciar').disabled = true;
    document.getElementById('btnParar').disabled = false;
    document.getElementById('statusSync').className = 'value online';
    document.getElementById('statusSync').textContent = '▶️ Sincronizando...';
    
    mostrarMensagem('🔄 Sincronização automática iniciada (a cada 1 segundo)', 'info');
    
    // Executar primeira sincronização imediatamente
    syncAgora();
    
    // Configurar intervalo de 1 segundo
    intervalo = setInterval(function() {
        syncAgora();
    }, 1000);
}

function pararSync() {
    if (intervalo) {
        clearInterval(intervalo);
        intervalo = null;
    }
    
    syncAtivo = false;
    document.getElementById('btnIniciar').disabled = false;
    document.getElementById('btnParar').disabled = true;
    document.getElementById('statusSync').className = 'value offline';
    document.getElementById('statusSync').textContent = '⏹️ Parado';
    
    mostrarMensagem('⏹️ Sincronização automática parada', 'info');
}

function syncAgora() {
    const direcao = document.getElementById('direcaoSync').value;
    const btn = document.getElementById('btnSyncAgora');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<span class="loading"></span> Sincronizando...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('acao', 'sincronizar_dinamico');
    formData.append('direcao', direcao);
    
    fetch('sync_dynamic.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('ultimaSync').textContent = data.data_hora;
            document.getElementById('totalTabelas').textContent = data.tabelas.length;
            document.getElementById('totalRegistros').textContent = data.total_registros;
            
            // Atualizar estatísticas
            document.getElementById('statTabelas').textContent = data.tabelas.length;
            document.getElementById('statRegistros').textContent = data.total_registros;
            
            mostrarMensagem('✅ ' + data.message, 'success');
            
            // Atualizar lista de arquivos
            carregarArquivos();
        } else {
            mostrarMensagem('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        mostrarMensagem('❌ Erro: ' + error.message, 'error');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function carregarStatus() {
    const formData = new FormData();
    formData.append('acao', 'status');
    
    fetch('sync_dynamic.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.ultima_sincronizacao) {
            document.getElementById('ultimaSync').textContent = data.ultima_sincronizacao;
            document.getElementById('totalTabelas').textContent = data.tabelas || 0;
            document.getElementById('totalRegistros').textContent = data.registros || 0;
            document.getElementById('statTabelas').textContent = data.tabelas || 0;
            document.getElementById('statRegistros').textContent = data.registros || 0;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar status:', error);
    });
}

function carregarArquivos() {
    const container = document.getElementById('listaArquivos');
    container.innerHTML = '<div style="text-align: center; color: #94a3b8; padding: 20px;">⏳ Carregando arquivos...</div>';
    
    const formData = new FormData();
    formData.append('acao', 'listar_arquivos');
    
    fetch('sync_dynamic.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(arquivos => {
        if (arquivos.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: #94a3b8; padding: 20px;">📭 Nenhum arquivo encontrado</div>';
            document.getElementById('statArquivos').textContent = '0';
            document.getElementById('statTamanho').textContent = '0 KB';
            return;
        }
        
        let html = '<div class="file-grid">';
        let tamanhoTotal = 0;
        
        arquivos.forEach(arquivo => {
            const extensao = arquivo.extensao.toUpperCase();
            let cor = '#667eea';
            if (extensao === 'JSON') cor = '#f39c12';
            else if (extensao === 'CSV') cor = '#2ecc71';
            else if (extensao === 'SQL') cor = '#3498db';
            
            tamanhoTotal += parseFloat(arquivo.tamanho);
            
            html += `
                <div class="file-item">
                    <div>
                        <div class="nome">${arquivo.nome}</div>
                        <div class="info">${arquivo.tamanho} • ${arquivo.data}</div>
                    </div>
                    <span class="badge" style="background: ${cor};">${extensao}</span>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
        document.getElementById('statArquivos').textContent = arquivos.length;
        document.getElementById('statTamanho').textContent = tamanhoTotal.toFixed(2) + ' KB';
    })
    .catch(error => {
        container.innerHTML = '<div style="text-align: center; color: #e74c3c; padding: 20px;">❌ Erro ao carregar arquivos</div>';
        console.error('Erro:', error);
    });
}

function mostrarMensagem(texto, tipo) {
    const el = document.getElementById('mensagem');
    el.textContent = texto;
    el.className = 'alert alert-' + tipo;
    el.style.display = 'block';
    
    setTimeout(() => {
        el.style.display = 'none';
    }, 5000);
}

// ============================================
// INICIALIZAÇÃO
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    carregarStatus();
    carregarArquivos();
    
    // Atualizar automaticamente a cada 10 segundos
    setInterval(() => {
        if (!syncAtivo) {
            carregarArquivos();
        }
    }, 10000);
});

// ============================================
// ATALHOS DO TECLADO
// ============================================

document.addEventListener('keydown', function(e) {
    // Ctrl+Enter para sincronizar agora
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        syncAgora();
    }
    
    // Ctrl+Shift+S para iniciar/parar
    if (e.ctrlKey && e.shiftKey && e.key === 'S') {
        e.preventDefault();
        if (syncAtivo) {
            pararSync();
        } else {
            iniciarSync();
        }
    }
});
</script>

</body>
</html>
<?php
}
?>