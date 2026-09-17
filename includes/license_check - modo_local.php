<?php
// includes/license_check.php
// Sistema de verificação e bloqueio de licença

function verificarLicencaAtiva() {
    global $pdo;
    
    try {
        // Verificar se a tabela existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'licencas'");
        if ($stmt->rowCount() == 0) {
            return [
                'status' => 'erro',
                'mensagem' => 'Tabela de licenças não encontrada!',
                'bloqueado' => true
            ];
        }
        
        // Buscar licença ativa mais recente
        $stmt = $pdo->prepare("
            SELECT 
                id,
                cliente,
                codigo_licenca,
                tipo,
                data_ativacao,
                data_expiracao,
                TIMESTAMPDIFF(SECOND, NOW(), data_expiracao) as segundos_restantes,
                status
            FROM licencas 
            WHERE cliente = 'Claudtec' 
            AND status = 'ativa'
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $licenca = $stmt->fetch();
        
        // Se não encontrar licença ativa
        if (!$licenca) {
            return [
                'status' => 'bloqueado',
                'mensagem' => 'Nenhuma licença ativa encontrada!',
                'bloqueado' => true,
                'codigo' => null,
                'dias_restantes' => 0
            ];
        }
        
        // Verificar se expirou
        if ($licenca['segundos_restantes'] <= 0) {
            // Desativar licença automaticamente
            $pdo->prepare("UPDATE licencas SET status = 'expirada' WHERE id = ?")
                ->execute([$licenca['id']]);
            
            return [
                'status' => 'expirada',
                'mensagem' => 'Sua licença expirou!',
                'bloqueado' => true,
                'codigo' => $licenca['codigo_licenca'],
                'dias_restantes' => 0,
                'expiracao' => $licenca['data_expiracao']
            ];
        }
        
        // Licença válida
        $segundos = $licenca['segundos_restantes'];
        $dias = floor($segundos / 86400);
        $horas = floor(($segundos % 86400) / 3600);
        $minutos = floor(($segundos % 3600) / 60);
        
        // Verificar se é uma licença de teste (5 minutos)
        $is_teste = ($licenca['tipo'] == 'teste');
        
        // Se for teste e tiver menos de 60 segundos, alertar
        if ($is_teste && $segundos < 60) {
            return [
                'status' => 'alerta',
                'mensagem' => 'Licença de teste expirando em menos de 1 minuto!',
                'bloqueado' => false,
                'codigo' => $licenca['codigo_licenca'],
                'dias_restantes' => 0,
                'tempo_restante' => [
                    'segundos' => $segundos,
                    'minutos' => $minutos,
                    'horas' => $horas,
                    'dias' => $dias,
                    'formatado' => sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos % 60)
                ],
                'expiracao' => $licenca['data_expiracao'],
                'is_teste' => true
            ];
        }
        
        return [
            'status' => 'ativa',
            'mensagem' => 'Licença válida',
            'bloqueado' => false,
            'codigo' => $licenca['codigo_licenca'],
            'dias_restantes' => $dias,
            'tempo_restante' => [
                'segundos' => $segundos,
                'minutos' => $minutos,
                'horas' => $horas,
                'dias' => $dias,
                'formatado' => sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos % 60)
            ],
            'expiracao' => $licenca['data_expiracao'],
            'is_teste' => $is_teste
        ];
        
    } catch (PDOException $e) {
        return [
            'status' => 'erro',
            'mensagem' => 'Erro ao verificar licença: ' . $e->getMessage(),
            'bloqueado' => true
        ];
    }
}

// ============================================
// FUNÇÃO PARA MOSTRAR TELA DE BLOQUEIO
// ============================================
function mostrarTelaBloqueio($licenca_info) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🔒 Licença Expirada - SoftGest</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .license-block {
                background: white;
                border-radius: 20px;
                padding: 50px;
                max-width: 600px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: slideUp 0.5s ease;
            }
            @keyframes slideUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .icon-lock {
                font-size: 80px;
                margin-bottom: 20px;
                display: block;
            }
            h1 {
                color: #1a2332;
                font-size: 32px;
                margin-bottom: 10px;
            }
            .subtitle {
                color: #94a3b8;
                font-size: 16px;
                margin-bottom: 30px;
            }
            .alert-box {
                background: #fee2e2;
                border: 2px solid #fca5a5;
                border-radius: 12px;
                padding: 20px;
                margin: 20px 0;
            }
            .alert-box .code {
                font-family: 'Courier New', monospace;
                background: #1a2332;
                color: #f5d76e;
                padding: 8px 16px;
                border-radius: 6px;
                font-size: 14px;
                display: inline-block;
                margin: 5px 0;
            }
            .alert-box .date {
                color: #991b1b;
                font-weight: 600;
            }
            .timer-box {
                background: #f8fafc;
                border-radius: 12px;
                padding: 20px;
                margin: 20px 0;
            }
            .timer-box .label {
                color: #94a3b8;
                font-size: 14px;
            }
            .timer-box .time {
                font-size: 48px;
                font-weight: 700;
                color: #e74c3c;
                font-family: 'Courier New', monospace;
            }
            .btn {
                display: inline-block;
                padding: 14px 30px;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.3s;
                margin: 5px;
            }
            .btn-primary {
                background: #3498db;
                color: white;
            }
            .btn-primary:hover {
                background: #2980b9;
                transform: translateY(-2px);
            }
            .btn-success {
                background: #2ecc71;
                color: white;
            }
            .btn-success:hover {
                background: #27ae60;
                transform: translateY(-2px);
            }
            .btn-warning {
                background: #f39c12;
                color: white;
            }
            .btn-warning:hover {
                background: #d68910;
                transform: translateY(-2px);
            }
            .btn-outline {
                background: transparent;
                color: #94a3b8;
                border: 2px solid #e2e8f0;
            }
            .btn-outline:hover {
                background: #f8fafc;
            }
            .features {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin: 20px 0;
                text-align: left;
            }
            .features .item {
                padding: 10px;
                background: #f8fafc;
                border-radius: 8px;
                font-size: 13px;
                color: #4a5568;
            }
            .features .item .icon {
                margin-right: 8px;
            }
            .footer {
                margin-top: 20px;
                color: #94a3b8;
                font-size: 13px;
            }
            .footer a {
                color: #3498db;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="license-block">
            <span class="icon-lock">🔒</span>
            <h1>Licença Expirada!</h1>
            <p class="subtitle">O acesso ao sistema foi bloqueado. Renove sua licença para continuar.</p>
            
            <div class="alert-box">
                <p style="font-weight: 600; color: #991b1b; margin-bottom: 10px;">
                    ⚠️ Sua licença não está mais ativa
                </p>
                <?php if (isset($licenca_info['codigo']) && $licenca_info['codigo']): ?>
                <p style="color: #4a5568; font-size: 14px;">
                    Código: <span class="code"><?= htmlspecialchars($licenca_info['codigo']) ?></span>
                </p>
                <?php endif; ?>
                <?php if (isset($licenca_info['expiracao']) && $licenca_info['expiracao']): ?>
                <p style="color: #4a5568; font-size: 14px; margin-top: 5px;">
                    Expirada em: <span class="date"><?= date('d/m/Y H:i:s', strtotime($licenca_info['expiracao'])) ?></span>
                </p>
                <?php endif; ?>
                <p style="color: #4a5568; font-size: 14px; margin-top: 5px;">
                    Status: <span style="color: #e74c3c; font-weight: 600;"><?= ucfirst($licenca_info['status'] ?? 'bloqueado') ?></span>
                </p>
            </div>
            
            <div style="margin: 20px 0;">
                <a href="license_5min.php" class="btn btn-warning">⏱️ Teste 5 Minutos</a>
                <a href="insert_license.php" class="btn btn-success">🔑 Ativar Licença</a>
                <a href="#" class="btn btn-outline" onclick="event.preventDefault(); alert('Entre em contato com o suporte: suporte@softgest.com')">📞 Suporte</a>
            </div>
            
            <div class="footer">
                <p>SoftGest Sistemas © <?= date('Y') ?> - Todos os direitos reservados</p>
                <p><a href="?force_check=1">🔄 Verificar novamente</a></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// FUNÇÃO PARA VERIFICAR E BLOQUEAR
// ============================================
function verificarEBlquear() {
    $licenca = verificarLicencaAtiva();
    
    // Se estiver bloqueado ou expirado, mostrar tela de bloqueio
    if ($licenca['bloqueado'] || $licenca['status'] == 'expirada') {
        mostrarTelaBloqueio($licenca);
        return false;
    }
    
    // Se for alerta (teste com menos de 60 segundos)
    if ($licenca['status'] == 'alerta') {
        // Mostrar aviso mas não bloquear ainda
        $_SESSION['license_alert'] = $licenca;
    }
    
    return true;
}
?>