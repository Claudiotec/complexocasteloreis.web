<?php
// ============================================
// network_access.php - Acesso à Rede Local
// ============================================

// ===== CARREGAR CONFIGURAÇÃO DE IP FIXO =====
if (file_exists('config/network_config.php')) {
    require_once 'config/network_config.php';
}

// ============================================
// FUNÇÃO PARA OBTER IP LOCAL (PRIORIZA IP FIXO)
// ============================================

function getLocalIP() {
    // PRIMEIRO: Verificar se há IP fixo configurado
    if (defined('FIXED_IP') && FIXED_IP !== '' && FIXED_IP !== '192.168.1.100') {
        return FIXED_IP;
    }
    
    // SEGUNDO: Tentar obter IP via comandos do sistema
    if (PHP_OS_FAMILY === 'Windows') {
        $ip = shell_exec('ipconfig | findstr "IPv4"');
        if (preg_match('/IPv4.*?(\d+\.\d+\.\d+\.\d+)/', $ip, $matches)) {
            $ip_found = $matches[1];
            // Verificar se não é IP de loopback
            if (!str_starts_with($ip_found, '127.')) {
                return $ip_found;
            }
        }
    } else {
        $ip = shell_exec('hostname -I 2>/dev/null');
        if ($ip) {
            $ips = explode(' ', trim($ip));
            foreach ($ips as $ip_found) {
                if (!str_starts_with($ip_found, '127.')) {
                    return $ip_found;
                }
            }
        }
    }
    
    // TERCEIRO: Tentar via PHP
    $ip = gethostbyname(gethostname());
    if ($ip && $ip !== '127.0.0.1' && $ip !== '::1') {
        return $ip;
    }
    
    return 'Não foi possível detectar o IP';
}

function getNetworkInfo() {
    $info = [];
    
    if (PHP_OS_FAMILY === 'Windows') {
        $output = shell_exec('ipconfig');
        $lines = explode("\n", $output);
        $current_adapter = '';
        $adapters = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (preg_match('/Adaptador.*?:(.*)/', $line, $matches)) {
                $current_adapter = trim($matches[1]);
                $adapters[$current_adapter] = [
                    'name' => $current_adapter,
                    'ip' => null,
                    'mask' => null,
                    'gateway' => null
                ];
            }
            
            if (preg_match('/IPv4.*?:\s*(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
                if (isset($adapters[$current_adapter])) {
                    $adapters[$current_adapter]['ip'] = $matches[1];
                }
            }
            
            if (preg_match('/Máscara.*?:\s*(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
                if (isset($adapters[$current_adapter])) {
                    $adapters[$current_adapter]['mask'] = $matches[1];
                }
            }
            
            if (preg_match('/Gateway.*?:\s*(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
                if (isset($adapters[$current_adapter])) {
                    $adapters[$current_adapter]['gateway'] = $matches[1];
                }
            }
        }
        
        foreach ($adapters as $name => $data) {
            if ($data['ip'] && !str_starts_with($data['ip'], '127.')) {
                $info[] = $data;
            }
        }
    }
    
    return $info;
}

// ============================================
// FUNÇÃO PARA OBTER ACESSOS
// ============================================

function getAccessLogs() {
    $logs = [];
    $log_file = 'access_log.txt';
    
    if (file_exists($log_file)) {
        $lines = file($log_file);
        foreach (array_reverse($lines) as $line) {
            $data = json_decode($line, true);
            if ($data) {
                $logs[] = $data;
            }
            if (count($logs) >= 20) break;
        }
    }
    
    return $logs;
}

// ============================================
// OBTER INFORMAÇÕES
// ============================================

$local_ip = getLocalIP();
$network_info = getNetworkInfo();
$port = $_SERVER['SERVER_PORT'];
$server_name = $_SERVER['SERVER_NAME'];
$hostname = gethostname();
$fixed_ip = defined('FIXED_IP') ? FIXED_IP : 'Não configurado';

// ============================================
// GERAR URLs APENAS COM IP VÁLIDO
// ============================================

$urls = [];

// 1. IP FIXO (PRIORIDADE MÁXIMA)
if (defined('FIXED_IP') && FIXED_IP !== '' && FIXED_IP !== '192.168.1.100') {
    $urls[] = [
        'url' => "http://" . FIXED_IP . ":" . $port . "/softgest_web/",
        'label' => '⭐ IP Fixo (Recomendado)',
        'ip' => FIXED_IP,
        'tipo' => 'fixo',
        'destaque' => true
    ];
}

// 2. IP Local Detectado (se for diferente do IP fixo)
if ($local_ip && $local_ip !== 'Não foi possível detectar o IP' && $local_ip !== '127.0.0.1') {
    $ip_exists = false;
    foreach ($urls as $url) {
        if ($url['ip'] === $local_ip) {
            $ip_exists = true;
            break;
        }
    }
    if (!$ip_exists) {
        $urls[] = [
            'url' => "http://{$local_ip}:{$port}/softgest_web/",
            'label' => '🌐 IP Local Detectado',
            'ip' => $local_ip,
            'tipo' => 'local'
        ];
    }
}

// 3. Hostname (se não for localhost)
if ($hostname && $hostname !== 'localhost' && $hostname !== 'DESKTOP-*') {
    $urls[] = [
        'url' => "http://{$hostname}:{$port}/softgest_web/",
        'label' => '🖥️ Nome do PC',
        'ip' => $hostname,
        'tipo' => 'hostname'
    ];
}

// 4. Localhost (apenas como fallback, NÃO gera QR Code)
$urls[] = [
    'url' => "http://localhost:{$port}/softgest_web/",
    'label' => '💻 Localhost (apenas neste PC)',
    'ip' => '127.0.0.1',
    'tipo' => 'localhost',
    'no_qr' => true  // Não gerar QR Code para localhost
];

// ============================================
// GERAR QR CODE (APENAS PARA IPs VÁLIDOS)
// ============================================

function generateQRCode($url) {
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($url);
}

// ============================================
// REGISTRAR ACESSO
// ============================================

if (isset($_SERVER['REMOTE_ADDR'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = $_SERVER['REQUEST_URI'] ?? '';
    
    $log_data = [
        'ip' => $ip,
        'user_agent' => $user_agent,
        'page' => $page,
        'time' => date('Y-m-d H:i:s')
    ];
    
    @file_put_contents('access_log.txt', json_encode($log_data) . "\n", FILE_APPEND);
}

$access_logs = getAccessLogs();
$total_acessos = count($access_logs);

// Contar IPs únicos
$unique_ips = [];
foreach ($access_logs as $log) {
    $unique_ips[$log['ip']] = true;
}
$total_ips = count($unique_ips);

// Filtrar URLs para QR Code (apenas IPs válidos)
$qr_urls = array_filter($urls, function($url) {
    return !isset($url['no_qr']) && 
           $url['ip'] !== '127.0.0.1' && 
           $url['ip'] !== 'localhost' &&
           !str_starts_with($url['ip'], '127.');
});
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso à Rede - SoftGest</title>
    <meta http-equiv="refresh" content="60">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            min-height: 100vh;
            padding: 20px;
            color: #fff;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { 
            text-align: center; 
            padding: 30px 20px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }
        .header h1 { 
            font-size: 32px; 
            font-weight: 800;
            background: linear-gradient(135deg, #f5d76e, #c9a84c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .header p { 
            color: #94a3b8; 
            font-size: 16px; 
            margin-top: 10px;
        }
        .header .status { 
            display: inline-block; 
            padding: 6px 18px; 
            border-radius: 20px; 
            font-size: 13px;
            font-weight: 600;
            margin-top: 12px;
            background: #2ecc71;
            color: #fff;
        }
        .auto-refresh {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(46, 204, 113, 0.2);
            border-radius: 20px;
            color: #2ecc71;
            font-size: 11px;
            margin-left: 8px;
        }
        
        .card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 18px;
        }
        .card h2 { 
            font-size: 18px; 
            margin-bottom: 12px;
            color: #f5d76e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card h2 .badge {
            background: rgba(245, 215, 110, 0.2);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            color: #f5d76e;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }
        .stat-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 28px;
            font-weight: 700;
            color: #f5d76e;
        }
        .stat-card .label {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }
        
        .ip-info { 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr 1fr; 
            gap: 12px;
        }
        .ip-item { 
            background: rgba(255,255,255,0.05);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .ip-item .label { 
            font-size: 11px; 
            color: #94a3b8; 
            text-transform: uppercase;
        }
        .ip-item .value { 
            font-size: 16px; 
            font-weight: 600;
            margin-top: 4px;
            font-family: 'Courier New', monospace;
            color: #f5d76e;
        }
        .ip-item .value.fixo { color: #2ecc71; }
        
        .url-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 12px;
        }
        .url-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .url-card:hover {
            background: rgba(255,255,255,0.1);
        }
        .url-card.destaque {
            border: 2px solid #f5d76e;
            background: rgba(245, 215, 110, 0.1);
        }
        .url-card .label {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .url-card .label .star {
            color: #f5d76e;
        }
        .url-card .url {
            font-size: 14px;
            font-weight: 600;
            color: #f5d76e;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            margin: 8px 0;
        }
        .url-card .actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        
        .btn {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            color: #fff;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .btn-primary { background: linear-gradient(135deg, #f5d76e, #c9a84c); color: #1a2332; }
        .btn-success { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .btn-info { background: linear-gradient(135deg, #3498db, #2980b9); }
        .btn-secondary { background: rgba(255,255,255,0.1); }
        
        .qr-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-top: 12px;
        }
        .qr-item {
            text-align: center;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .qr-item img {
            max-width: 120px;
            height: auto;
            border-radius: 6px;
            background: #fff;
            padding: 4px;
        }
        .qr-item .label {
            margin-top: 8px;
            font-size: 11px;
            color: #94a3b8;
        }
        
        .info-box {
            background: rgba(245, 215, 110, 0.1);
            border-left: 3px solid #f5d76e;
            padding: 12px 16px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 13px;
            color: #94a3b8;
        }
        .info-box strong { color: #f5d76e; }
        .info-box.success {
            background: rgba(46, 204, 113, 0.1);
            border-left-color: #2ecc71;
        }
        .info-box.success strong { color: #2ecc71; }
        
        .log-entry {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }
        .log-entry:last-child { border-bottom: none; }
        .log-entry .time { color: #94a3b8; font-size: 12px; }
        .log-entry .ip { color: #f5d76e; font-weight: 600; }
        .log-entry .page { color: #94a3b8; font-size: 12px; }
        
        .commands {
            background: rgba(0,0,0,0.3);
            padding: 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-top: 8px;
            overflow-x: auto;
        }
        .commands code { color: #2ecc71; }
        
        .actions-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
        }
        .empty-state .icon { font-size: 36px; margin-bottom: 10px; }
        
        .copy-feedback {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #2ecc71;
            color: #fff;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            display: none;
            font-size: 14px;
            z-index: 999;
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .ip-info { grid-template-columns: 1fr; }
            .header h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🌐 Acesso à Rede</h1>
            <p>Conecte outros dispositivos à mesma rede WiFi</p>
            <div>
                <span class="status">🟢 Servidor Ativo</span>
                <span class="auto-refresh">🔄 Atualiza a cada 60s</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= $total_ips ?></div>
                <div class="label">📱 Dispositivos únicos</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $total_acessos ?></div>
                <div class="label">📋 Acessos registrados</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= count($urls) ?></div>
                <div class="label">🔗 Links disponíveis</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #2ecc71;">Online</div>
                <div class="label">🟢 Status</div>
            </div>
        </div>

        <!-- ⭐ CONFIGURAÇÃO DE IP FIXO -->
        <div class="card" style="border: 2px solid <?= (defined('FIXED_IP') && FIXED_IP !== '' && FIXED_IP !== '192.168.1.100') ? '#2ecc71' : '#f39c12' ?>;">
            <h2>⭐ IP Fixo Configurado</h2>
            <div class="info-box <?= (defined('FIXED_IP') && FIXED_IP !== '' && FIXED_IP !== '192.168.1.100') ? 'success' : '' ?>">
                <strong>
                    <?php if (defined('FIXED_IP') && FIXED_IP !== '' && FIXED_IP !== '192.168.1.100'): ?>
                        ✅ IP Fixo: <span style="font-size:20px;color:#2ecc71;"><?= htmlspecialchars(FIXED_IP) ?></span>
                        <br>
                        <small style="color:#94a3b8;">📌 Este IP será usado para gerar os QR Codes</small>
                    <?php else: ?>
                        ⚠️ Nenhum IP fixo configurado!
                        <br>
                        <small style="color:#f39c12;">Configure em: config/network_config.php</small>
                    <?php endif; ?>
                </strong>
            </div>
            <?php if (defined('FIXED_IP') && FIXED_IP !== '' && FIXED_IP !== '192.168.1.100'): ?>
            <div style="text-align:center;margin-top:10px;">
                <a href="http://<?= FIXED_IP ?>:<?= $port ?>/softgest_web/" target="_blank" class="btn btn-success" style="font-size:16px;padding:10px 30px;">
                    🚀 Acessar pelo IP Fixo: <?= FIXED_IP ?>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Informações do Servidor -->
        <div class="card">
            <h2>📡 Informações do Servidor</h2>
            <div class="ip-info">
                <div class="ip-item">
                    <div class="label">⭐ IP Fixo</div>
                    <div class="value fixo"><?= htmlspecialchars($fixed_ip) ?></div>
                </div>
                <div class="ip-item">
                    <div class="label">🌐 IP Local Detectado</div>
                    <div class="value"><?= htmlspecialchars($local_ip) ?></div>
                </div>
                <div class="ip-item">
                    <div class="label">🔌 Porta</div>
                    <div class="value"><?= htmlspecialchars($port) ?></div>
                </div>
                <div class="ip-item">
                    <div class="label">🖥️ Nome do PC</div>
                    <div class="value" style="font-size: 14px;"><?= htmlspecialchars($hostname) ?></div>
                </div>
            </div>
        </div>

        <!-- Links de Acesso -->
        <div class="card">
            <h2>🔗 Links de Acesso <span class="badge">Clique para copiar</span></h2>
            <div class="url-grid">
                <?php foreach ($urls as $url): ?>
                <div class="url-card <?= isset($url['destaque']) ? 'destaque' : '' ?>">
                    <div class="label">
                        <?= isset($url['destaque']) ? '⭐ ' : '' ?>
                        <?= htmlspecialchars($url['label']) ?>
                    </div>
                    <div class="url" id="url_<?= md5($url['url']) ?>"><?= htmlspecialchars($url['url']) ?></div>
                    <div class="actions">
                        <button onclick="copyToClipboard('<?= htmlspecialchars($url['url']) ?>')" class="btn btn-primary">
                            📋 Copiar
                        </button>
                        <a href="<?= htmlspecialchars($url['url']) ?>" target="_blank" class="btn btn-success">
                            🚀 Abrir
                        </a>
                    </div>
                    <?php if (isset($url['no_qr'])): ?>
                    <div style="margin-top:6px;font-size:10px;color:#94a3b8;">⚠️ QR Code não gerado para localhost</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- QR Codes -->
        <div class="card">
            <h2>📱 QR Codes <span class="badge">Escaneie com o celular</span></h2>
            <div class="info-box success">
                💡 <strong>QR Codes gerados com o IP Fixo:</strong> 
                <?= defined('FIXED_IP') && FIXED_IP !== '' && FIXED_IP !== '192.168.1.100' ? FIXED_IP : 'Nenhum IP fixo configurado' ?>
                <br>
                <small>Escaneie com a câmera do celular para acessar o sistema</small>
            </div>
            <div class="qr-container">
                <?php if (!empty($qr_urls)): ?>
                    <?php foreach ($qr_urls as $url): ?>
                    <div class="qr-item">
                        <img src="<?= generateQRCode($url['url']) ?>" alt="QR Code">
                        <div class="label">📌 <?= htmlspecialchars($url['label']) ?></div>
                        <div style="font-size:10px;color:#94a3b8;margin-top:4px;"><?= htmlspecialchars($url['ip']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <div class="icon">📱</div>
                        <p>Nenhum IP válido para gerar QR Code.</p>
                        <p style="font-size:12px;color:#94a3b8;">Configure um IP fixo em config/network_config.php</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Últimos Acessos -->
        <div class="card">
            <h2>
                📋 Últimos Acessos
                <span class="badge"><?= $total_acessos ?> registros</span>
            </h2>
            
            <?php if (!empty($access_logs)): ?>
                <?php foreach ($access_logs as $log): ?>
                <div class="log-entry">
                    <div>
                        <span class="ip"><?= htmlspecialchars($log['ip']) ?></span>
                        <span class="page">→ <?= htmlspecialchars($log['page']) ?></span>
                    </div>
                    <div class="time"><?= htmlspecialchars($log['time']) ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📭</div>
                    <p>Nenhum acesso registrado ainda.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Informações da Rede -->
        <?php if (!empty($network_info)): ?>
        <div class="card">
            <h2>🌐 Adaptadores de Rede</h2>
            <?php foreach ($network_info as $adapter): ?>
            <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 6px; margin-bottom: 8px;">
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; font-size: 13px;">
                    <span><strong><?= htmlspecialchars($adapter['name'] ?? 'N/A') ?></strong></span>
                    <span style="color: #f5d76e;">IP: <?= htmlspecialchars($adapter['ip'] ?? 'N/A') ?></span>
                    <?php if (isset($adapter['gateway'])): ?>
                    <span style="color: #94a3b8;">Gateway: <?= htmlspecialchars($adapter['gateway']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Ações Rápidas -->
        <div class="card" style="text-align: center;">
            <h2>⚡ Ações Rápidas</h2>
            <div class="actions-bar">
                <a href="index.php" class="btn btn-success">🏠 Dashboard</a>
                <a href="login.php" class="btn btn-primary">🔑 Login</a>
                <button onclick="window.print()" class="btn btn-secondary">🖨️ Imprimir</button>
                <button onclick="location.reload()" class="btn btn-secondary">🔄 Atualizar</button>
            </div>
        </div>
    </div>

    <!-- Feedback -->
    <div id="copyFeedback" class="copy-feedback">✅ URL copiada!</div>

    <script>
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => showFeedback());
            } else {
                const input = document.createElement('input');
                input.value = text;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                showFeedback();
            }
        }
        
        function showFeedback() {
            const el = document.getElementById('copyFeedback');
            el.style.display = 'block';
            setTimeout(() => el.style.display = 'none', 2000);
        }
    </script>
</body>
</html>