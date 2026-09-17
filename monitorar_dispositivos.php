<?php
// monitorar_dispositivos.php
// Monitoramento de dispositivos conectados à rede

// ============================================
// FUNÇÕES PARA DETECTAR DISPOSITIVOS
// ============================================

function getNetworkDevices() {
    $devices = [];
    $subnet = getSubnet();
    
    if (!$subnet) {
        return ['error' => 'Não foi possível detectar a sub-rede'];
    }
    
    // Escanear IPs de 1 a 254
    for ($i = 1; $i <= 254; $i++) {
        $ip = $subnet . $i;
        $hostname = gethostbyaddr($ip);
        
        // Verificar se o dispositivo está ativo (ping)
        $active = pingDevice($ip);
        
        if ($active) {
            $devices[] = [
                'ip' => $ip,
                'hostname' => ($hostname && $hostname !== $ip) ? $hostname : 'Desconhecido',
                'status' => 'online',
                'last_seen' => date('H:i:s'),
                'first_seen' => date('H:i:s'),
                'os' => detectOS($ip)
            ];
        }
    }
    
    return $devices;
}

function getSubnet() {
    if (PHP_OS_FAMILY === 'Windows') {
        $output = shell_exec('ipconfig | findstr "IPv4"');
        if (preg_match('/IPv4.*?(\d+\.\d+\.\d+)\.\d+/', $output, $matches)) {
            return $matches[1] . '.';
        }
    } else {
        $output = shell_exec('ifconfig | grep "inet " | grep -v 127.0.0.1');
        if (preg_match('/inet\s+(\d+\.\d+\.\d+)\.\d+/', $output, $matches)) {
            return $matches[1] . '.';
        }
    }
    return null;
}

function pingDevice($ip) {
    if (PHP_OS_FAMILY === 'Windows') {
        $ping = exec("ping -n 1 -w 1000 $ip 2>&1", $output, $return);
    } else {
        $ping = exec("ping -c 1 -W 1 $ip 2>&1", $output, $return);
    }
    return $return === 0;
}

function detectOS($ip) {
    // Tentar detectar SO pelo TTL ou outras características
    return 'Desconhecido';
}

function getAccessLogs() {
    $logs = [];
    $log_file = 'access_log.txt';
    
    if (file_exists($log_file)) {
        $lines = file($log_file);
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data) {
                $logs[] = $data;
            }
        }
    }
    
    return array_reverse($logs);
}

function logAccess($ip, $user_agent, $page) {
    $log_file = 'access_log.txt';
    $data = [
        'ip' => $ip,
        'user_agent' => $user_agent,
        'page' => $page,
        'time' => date('Y-m-d H:i:s')
    ];
    file_put_contents($log_file, json_encode($data) . "\n", FILE_APPEND);
}

// ============================================
// OBTER DADOS
// ============================================

$devices = getNetworkDevices();
$access_logs = getAccessLogs();
$total_devices = count($devices);

// Contar dispositivos ativos
$active_devices = array_filter($devices, function($d) {
    return $d['status'] === 'online';
});

$online_count = count($active_devices);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Dispositivos - SoftGest</title>
    <meta http-equiv="refresh" content="30">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            min-height: 100vh;
            padding: 20px;
            color: #fff;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 28px;
            background: linear-gradient(135deg, #f5d76e, #c9a84c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .header .status {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .badge-online { background: #2ecc71; color: #fff; }
        .badge-offline { background: #e74c3c; color: #fff; }
        .badge-total { background: #3498db; color: #fff; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #f5d76e;
        }
        .stat-card .label {
            font-size: 14px;
            color: #94a3b8;
            margin-top: 5px;
        }
        
        .card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .card h2 {
            font-size: 20px;
            color: #f5d76e;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card h2 .count {
            font-size: 14px;
            background: rgba(255,255,255,0.1);
            padding: 2px 12px;
            border-radius: 12px;
            color: #94a3b8;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th {
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            color: #94a3b8;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .table tr:hover {
            background: rgba(255,255,255,0.05);
        }
        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-dot.online { background: #2ecc71; }
        .status-dot.offline { background: #e74c3c; }
        .status-dot.unknown { background: #f39c12; }
        
        .device-type {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .device-type.windows { background: #0078d4; color: #fff; }
        .device-type.android { background: #3ddc84; color: #000; }
        .device-type.ios { background: #000; color: #fff; }
        .device-type.linux { background: #f80000; color: #fff; }
        .device-type.unknown { background: #94a3b8; color: #fff; }
        
        .log-entry {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .log-entry:last-child { border-bottom: none; }
        .log-entry .time { color: #94a3b8; font-size: 13px; }
        .log-entry .ip { color: #f5d76e; font-weight: 600; }
        
        .auto-refresh {
            display: inline-block;
            padding: 5px 15px;
            background: rgba(46, 204, 113, 0.2);
            border-radius: 20px;
            color: #2ecc71;
            font-size: 12px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            color: #fff;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .btn-primary { background: linear-gradient(135deg, #f5d76e, #c9a84c); color: #1a2332; }
        .btn-success { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .btn-info { background: linear-gradient(135deg, #3498db, #2980b9); }
        .btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 15px; }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 15px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .table { font-size: 13px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📡 Monitor de Dispositivos</h1>
            <div class="status">
                <span class="auto-refresh">🔄 Atualizando a cada 30s</span>
                <a href="network_access.php" class="btn btn-primary">🌐 Voltar</a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= $total_devices ?></div>
                <div class="label">Total de Dispositivos</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #2ecc71;"><?= $online_count ?></div>
                <div class="label">Dispositivos Online</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #e74c3c;"><?= $total_devices - $online_count ?></div>
                <div class="label">Dispositivos Offline</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #3498db;"><?= count($access_logs) ?></div>
                <div class="label">Acessos Registrados</div>
            </div>
        </div>
        
        <!-- Lista de Dispositivos -->
        <div class="card">
            <h2>
                🖥️ Dispositivos na Rede
                <span class="count"><?= $total_devices ?> encontrados</span>
            </h2>
            
            <?php if (!empty($devices) && !isset($devices['error'])): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>IP</th>
                        <th>Hostname</th>
                        <th>Tipo</th>
                        <th>Última Conexão</th>
                        <th>Primeira Conexão</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $device): ?>
                    <tr>
                        <td>
                            <span class="status-dot <?= $device['status'] ?>"></span>
                            <?= ucfirst($device['status']) ?>
                        </td>
                        <td><strong><?= htmlspecialchars($device['ip']) ?></strong></td>
                        <td><?= htmlspecialchars($device['hostname']) ?></td>
                        <td>
                            <span class="device-type unknown">Desconhecido</span>
                        </td>
                        <td><?= $device['last_seen'] ?></td>
                        <td><?= $device['first_seen'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="icon">🔍</div>
                <p>Nenhum dispositivo encontrado ou erro ao escanear a rede.</p>
                <p style="font-size: 13px; margin-top: 10px;"><?= isset($devices['error']) ? $devices['error'] : '' ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Logs de Acesso -->
        <div class="card">
            <h2>
                📋 Últimos Acessos
                <span class="count"><?= count($access_logs) ?> registros</span>
            </h2>
            
            <?php if (!empty($access_logs)): ?>
                <?php foreach (array_slice($access_logs, 0, 20) as $log): ?>
                <div class="log-entry">
                    <div>
                        <span class="ip"><?= htmlspecialchars($log['ip']) ?></span>
                        <span style="color: #94a3b8; font-size: 13px;">acessou</span>
                        <span style="color: #f5d76e;"><?= htmlspecialchars($log['page']) ?></span>
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
        
        <!-- Ações -->
        <div class="card" style="text-align: center;">
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <button onclick="location.reload()" class="btn btn-primary">🔄 Atualizar Agora</button>
                <a href="network_access.php" class="btn btn-info">🌐 Voltar para Acesso</a>
                <a href="index.php" class="btn btn-success">🏠 Dashboard</a>
            </div>
        </div>
    </div>
    
    <script>
        // Atualização automática a cada 30 segundos
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>