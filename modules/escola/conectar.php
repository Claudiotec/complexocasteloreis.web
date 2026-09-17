<?php
// ============================================
// conectar.php - Conectar Dispositivo com QR Code
// ============================================

require_once '../../config/database.php';
require_once '../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

// Gerar token se não existir
$token = null;
try {
    // Verificar se a tabela existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dispositivos_conectados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            dispositivo_nome VARCHAR(100) DEFAULT NULL,
            dispositivo_tipo VARCHAR(50) DEFAULT 'web',
            token VARCHAR(100) UNIQUE NOT NULL,
            ultima_atividade TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ativo TINYINT(1) DEFAULT 1,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (usuario_id),
            INDEX idx_token (token),
            INDEX idx_ativo (ativo),
            INDEX idx_ultima_atividade (ultima_atividade)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Buscar token existente
    $stmt = $pdo->prepare("
        SELECT token FROM dispositivos_conectados 
        WHERE usuario_id = ? AND ativo = 1 
        ORDER BY ultima_atividade DESC LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        $token = $result['token'];
        // Atualizar atividade
        $stmt = $pdo->prepare("
            UPDATE dispositivos_conectados 
            SET ultima_atividade = NOW(), ip = ?, user_agent = ?
            WHERE token = ?
        ");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $token
        ]);
    } else {
        // Gerar novo token
        $token = bin2hex(random_bytes(32));
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $dispositivo_tipo = 'web';
        if (strpos($user_agent, 'Mobile') !== false) {
            $dispositivo_tipo = 'mobile';
        } elseif (strpos($user_agent, 'Tablet') !== false) {
            $dispositivo_tipo = 'tablet';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO dispositivos_conectados 
            (usuario_id, dispositivo_nome, dispositivo_tipo, token, ip, user_agent, ultima_atividade)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $usuario_id,
            $dispositivo_tipo . '_' . date('YmdHis'),
            $dispositivo_tipo,
            $token,
            $ip,
            $user_agent
        ]);
    }
} catch (Exception $e) {
    error_log("Erro ao gerar token: " . $e->getMessage());
}

include __DIR__ . '/includes/header_escola.php';
?>

<style>
/* ==========================================
   ESTILOS DA PÁGINA DE CONEXÃO
   ========================================== */
.conectar-container {
    max-width: 750px;
    margin: 30px auto;
    padding: 0 20px;
}

.conectar-card {
    background: #fff;
    border-radius: 16px;
    padding: 35px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.06);
    border: 1px solid #eef2f7;
}

.conectar-card h2 {
    color: #1a2332;
    margin: 0 0 5px;
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.conectar-card .subtitle {
    color: #94a3b8;
    font-size: 14px;
    margin: 0 0 25px;
}

/* QR Code */
.qr-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 40px;
    flex-wrap: wrap;
    padding: 20px 0;
}

.qr-box {
    background: #fff;
    padding: 20px;
    border-radius: 16px;
    border: 2px solid #eef2f7;
    text-align: center;
    min-width: 200px;
}

.qr-box canvas,
.qr-box img {
    border-radius: 8px;
}

.qr-box .qr-label {
    font-size: 13px;
    color: #94a3b8;
    margin-top: 10px;
    display: block;
}

/* Link de conexão */
.link-box {
    background: #f8fafc;
    border-radius: 10px;
    padding: 14px 18px;
    border: 1px solid #e2e8f0;
    word-break: break-all;
    font-family: monospace;
    font-size: 13px;
    color: #1a2332;
    margin: 15px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.link-box .link-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.link-box .btn-copy {
    background: none;
    border: none;
    color: #c9a84c;
    cursor: pointer;
    font-size: 18px;
    padding: 5px 10px;
    transition: all 0.3s;
    flex-shrink: 0;
}

.link-box .btn-copy:hover {
    transform: scale(1.2);
}

/* Botões */
.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin: 15px 0;
}

.btn-conectar {
    padding: 10px 24px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-size: 14px;
}

.btn-conectar-primary {
    background: #c9a84c;
    color: #1a2332;
}
.btn-conectar-primary:hover {
    background: #b8973a;
    transform: translateY(-2px);
}

.btn-conectar-secondary {
    background: #f1f5f9;
    color: #4a5568;
}
.btn-conectar-secondary:hover {
    background: #e2e8f0;
    transform: translateY(-2px);
}

.btn-conectar-success {
    background: #2ecc71;
    color: #fff;
}
.btn-conectar-success:hover {
    background: #27ae60;
    transform: translateY(-2px);
}

.btn-conectar-danger {
    background: #e74c3c;
    color: #fff;
}
.btn-conectar-danger:hover {
    background: #c0392b;
    transform: translateY(-2px);
}

.btn-conectar-info {
    background: #3498db;
    color: #fff;
}
.btn-conectar-info:hover {
    background: #2980b9;
    transform: translateY(-2px);
}

.btn-conectar-purple {
    background: #8e44ad;
    color: #fff;
}
.btn-conectar-purple:hover {
    background: #7d3c98;
    transform: translateY(-2px);
}

/* Dispositivos conectados */
.dispositivos-lista {
    margin-top: 25px;
    border-top: 1px solid #eef2f7;
    padding-top: 20px;
}

.dispositivos-lista h4 {
    color: #1a2332;
    font-size: 15px;
    margin: 0 0 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.dispositivo-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: #f8fafc;
    border-radius: 8px;
    margin-bottom: 8px;
    border: 1px solid #eef2f7;
    font-size: 13px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.dispositivo-item .info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.dispositivo-item .status {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.dispositivo-item .status.ativo {
    background: #d1fae5;
    color: #065f46;
}
.dispositivo-item .status.inativo {
    background: #fee2e2;
    color: #991b1b;
}

.dispositivo-item .tempo {
    font-size: 12px;
    color: #94a3b8;
}

/* Dicas */
.dicas-box {
    background: #fef9e8;
    border-radius: 10px;
    padding: 15px 18px;
    border: 1px solid #fde68a;
    margin-top: 15px;
}

.dicas-box h4 {
    color: #78350f;
    margin: 0 0 8px;
    font-size: 14px;
}

.dicas-box ul {
    margin: 0;
    padding-left: 20px;
    color: #78350f;
    font-size: 13px;
}

.dicas-box ul li {
    margin-bottom: 4px;
}

/* Seção de teste - Sistema Confiável */
.teste-section {
    margin-top: 25px;
    border-top: 2px solid #eef2f7;
    padding-top: 20px;
}

.teste-section h4 {
    color: #1a2332;
    margin: 0 0 12px;
}

.teste-resultado {
    margin-top: 10px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    display: none;
    max-height: 400px;
    overflow-y: auto;
}

.teste-resultado .mensagem {
    font-size: 13px;
    color: #1a2332;
}

.teste-resultado .notificacao-item {
    background: #fff;
    padding: 8px 12px;
    border-radius: 6px;
    margin: 4px 0;
    border-left: 3px solid #c9a84c;
}

.teste-resultado .notificacao-item .titulo {
    font-weight: 700;
    color: #1a2332;
}

.teste-resultado .notificacao-item .texto {
    font-size: 13px;
    color: #4a5568;
}

.teste-resultado .notificacao-item .tempo {
    font-size: 11px;
    color: #94a3b8;
}

/* Status */
.status-info {
    background: #dbeafe;
    color: #1e40af;
    padding: 10px 16px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 13px;
}
.status-success {
    background: #d1fae5;
    color: #065f46;
    padding: 10px 16px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 13px;
}
.status-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 10px 16px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 13px;
}
.status-warning {
    background: #fef9e8;
    color: #78350f;
    padding: 10px 16px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 13px;
}

/* Responsivo */
@media (max-width: 768px) {
    .conectar-card {
        padding: 20px;
    }
    .qr-wrapper {
        flex-direction: column;
        gap: 20px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-conectar {
        justify-content: center;
    }
    .link-box {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
    .dispositivo-item {
        flex-direction: column;
        align-items: stretch;
        gap: 5px;
        text-align: center;
    }
    .dispositivo-item .info {
        justify-content: center;
    }
}

/* Animação de notificação */
.notification-toast-container {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 99999;
    max-width: 380px;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}

.notification-toast {
    pointer-events: all;
    background: #ffffff;
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.12);
    border-left: 4px solid #c9a84c;
    animation: notifSlideIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.notification-toast.removing {
    animation: notifSlideOut 0.4s forwards;
}

@keyframes notifSlideIn {
    from { opacity: 0; transform: translateX(80px) scale(0.95); }
    to { opacity: 1; transform: translateX(0) scale(1); }
}

@keyframes notifSlideOut {
    from { opacity: 1; transform: translateX(0) scale(1); }
    to { opacity: 0; transform: translateX(80px) scale(0.95); }
}

.notification-toast .toast-icon {
    font-size: 24px;
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(201,168,76,0.1);
}

.notification-toast .toast-content { flex: 1; min-width: 0; }
.notification-toast .toast-content .toast-title {
    font-weight: 700;
    color: #1a2332;
    font-size: 14px;
    margin: 0 0 3px;
}
.notification-toast .toast-content .toast-message {
    font-size: 13px;
    color: #94a3b8;
    margin: 0;
    line-height: 1.5;
}
.notification-toast .toast-content .toast-time {
    font-size: 11px;
    color: #a0aec0;
    margin-top: 4px;
    display: block;
}

.notification-toast .toast-close {
    background: none;
    border: none;
    color: #a0aec0;
    cursor: pointer;
    font-size: 16px;
    padding: 4px;
    transition: all 0.2s ease;
    flex-shrink: 0;
    margin-top: -2px;
}
.notification-toast .toast-close:hover {
    color: #1a2332;
    transform: scale(1.2);
}

.notification-toast.success { border-left-color: #2ecc71; }
.notification-toast.error { border-left-color: #e74c3c; }
.notification-toast.warning { border-left-color: #f39c12; }
.notification-toast.info { border-left-color: #3498db; }
.notification-toast.gold { border-left-color: #c9a84c; }
</style>

<div class="conectar-container">
    <div class="conectar-card">
        <h2>📱 Conectar Dispositivo</h2>
        <p class="subtitle">Escaneie o QR Code ou copie o link para conectar seu celular</p>
        
        <?php if ($token): ?>
        
        <!-- ===== QR CODE ===== -->
        <div class="qr-wrapper">
            <div class="qr-box">
                <div id="qrcode"></div>
                <span class="qr-label">📸 Escaneie com o celular</span>
            </div>
            
            <div style="text-align:left;flex:1;min-width:200px;">
                <div style="font-size:14px;color:#1a2332;font-weight:600;margin-bottom:10px;">
                    🔗 Link de Conexão
                </div>
                <div class="link-box">
                    <span class="link-text" id="linkConexao">
                        <?= SITE_URL ?>modules/escola/financeiro/?notif_token=<?= $token ?>
                    </span>
                    <button class="btn-copy" onclick="copiarLink()" title="Copiar link">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                
                <div class="btn-group">
                    <button class="btn-conectar btn-conectar-primary" onclick="copiarLink()">
                        📋 Copiar Link
                    </button>
                    <button class="btn-conectar btn-conectar-info" onclick="baixarQRCode()">
                        💾 Salvar QR Code
                    </button>
                    <a href="<?= SITE_URL ?>modules/escola/financeiro/?notif_token=<?= $token ?>" 
                       class="btn-conectar btn-conectar-success" target="_blank">
                        🚀 Abrir no Celular
                    </a>
                    <button class="btn-conectar btn-conectar-danger" onclick="regenerarToken()">
                        🔄 Novo Token
                    </button>
                </div>
            </div>
        </div>
        
        <!-- ===== DICAS ===== -->
        <div class="dicas-box">
            <h4>💡 Como conectar seu celular:</h4>
            <ul>
                <li><strong>Opção 1:</strong> Escaneie o QR Code com a câmera do celular</li>
                <li><strong>Opção 2:</strong> Copie o link e cole no navegador do celular</li>
                <li><strong>Opção 3:</strong> Clique em "Abrir no Celular" e envie o link</li>
                <li>⚠️ Ambos os dispositivos devem estar na <strong>mesma rede Wi-Fi</strong></li>
                <li>📱 O celular receberá notificações em <strong>tempo real</strong></li>
            </ul>
        </div>
        
        <!-- ===== DISPOSITIVOS CONECTADOS ===== -->
        <div class="dispositivos-lista">
            <h4>📋 Dispositivos Conectados <span style="font-size:12px;color:#94a3b8;font-weight:400;" id="qtdDispositivos"></span></h4>
            <div id="listaDispositivos">
                <div style="text-align:center;padding:20px;color:#94a3b8;font-size:13px;">
                    ⏳ Carregando dispositivos...
                </div>
            </div>
        </div>
        
        <!-- ===== SEÇÃO DE TESTE - SISTEMA CONFIÁVEL ===== -->
        <div class="teste-section">
            <h4>🧪 Teste de Notificação - Sistema Confiável</h4>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn-conectar btn-conectar-purple" onclick="enviarNotificacaoConfiavel()">
                    📤 Enviar Notificação (Confiável)
                </button>
                <button class="btn-conectar btn-conectar-success" onclick="enviarNotificacaoMultipla()">
                    📤 Enviar 5 Notificações
                </button>
                <button class="btn-conectar btn-conectar-danger" onclick="testarConexaoCompleta()">
                    📡 Testar Conexão
                </button>
            </div>
            <div id="statusNotificacao" class="status-info">
                🟢 Sistema pronto - Aguardando conexão...
            </div>
        </div>
        
        <?php else: ?>
        <div style="padding:40px;text-align:center;color:#94a3b8;">
            <div style="font-size:48px;margin-bottom:15px;">⚠️</div>
            <p style="font-size:16px;color:#1a2332;">Erro ao gerar token</p>
            <p style="font-size:13px;">Tente recarregar a página ou clique no botão abaixo</p>
            <button class="btn-conectar btn-conectar-primary" onclick="location.reload()" style="margin-top:15px;">
                🔄 Recarregar
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<script>
// ==========================================
// TOKEN DO PHP PARA O JAVASCRIPT
// ==========================================
const TOKEN_PHP = <?= json_encode($token) ?>;

// ==========================================
// FUNÇÃO PARA OBTER TOKEN
// ==========================================
function obterToken() {
    let token = sessionStorage.getItem('notif_token_multi') || 
                localStorage.getItem('notif_token_multi');
    
    if (!token) {
        token = new URLSearchParams(window.location.search).get('notif_token');
    }
    
    if (!token && TOKEN_PHP) {
        token = TOKEN_PHP;
        sessionStorage.setItem('notif_token_multi', token);
        localStorage.setItem('notif_token_multi', token);
    }
    
    return token;
}

// ==========================================
// QR CODE
// ==========================================
function gerarQRCode() {
    const link = document.getElementById('linkConexao').textContent;
    const container = document.getElementById('qrcode');
    container.innerHTML = '';
    
    new QRCode(container, {
        text: link,
        width: 200,
        height: 200,
        colorDark: '#1a2332',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });
}

function copiarLink() {
    const link = document.getElementById('linkConexao').textContent;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(link).then(() => {
            mostrarToast('✅ Link copiado!', 'success');
        }).catch(() => {
            prompt('Copie o link:', link);
        });
    } else {
        prompt('Copie o link:', link);
    }
}

function baixarQRCode() {
    const canvas = document.querySelector('#qrcode canvas');
    if (canvas) {
        const link = document.createElement('a');
        link.download = 'qrcode_conectar.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        mostrarToast('✅ QR Code baixado!', 'success');
    } else {
        mostrarToast('❌ Aguarde o QR Code carregar', 'error');
    }
}

function regenerarToken() {
    if (!confirm('Deseja gerar um novo token? Todos os dispositivos antigos serão desconectados.')) {
        return;
    }
    
    mostrarToast('⏳ Gerando novo token...', 'info');
    
    fetch('api_gerar_token.php?forcar_novo=1', {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.token) {
            const linkBox = document.getElementById('linkConexao');
            const url = new URL(window.location);
            url.searchParams.set('notif_token', data.token);
            linkBox.textContent = url.toString();
            
            gerarQRCode();
            
            sessionStorage.setItem('notif_token_multi', data.token);
            localStorage.setItem('notif_token_multi', data.token);
            window.TOKEN_PHP = data.token;
            
            mostrarToast('✅ Novo token gerado!', 'success');
            carregarDispositivos();
        } else {
            mostrarToast('❌ Erro ao gerar token: ' + (data.error || 'Erro desconhecido'), 'error');
        }
    })
    .catch(error => {
        mostrarToast('❌ Erro: ' + error.message, 'error');
    });
}

// ==========================================
// TOAST
// ==========================================
function mostrarToast(mensagem, tipo = 'info') {
    const cores = {
        'success': '#2ecc71',
        'error': '#e74c3c',
        'info': '#3498db',
        'warning': '#f39c12'
    };
    
    const toast = document.createElement('div');
    toast.className = `notification-toast ${tipo}`;
    const time = new Date().toLocaleTimeString('pt-BR');
    
    toast.innerHTML = `
        <div class="toast-icon">${tipo === 'success' ? '✅' : tipo === 'error' ? '❌' : 'ℹ️'}</div>
        <div class="toast-content">
            <div class="toast-title">${tipo === 'success' ? 'Sucesso' : tipo === 'error' ? 'Erro' : 'Informação'}</div>
            <div class="toast-message">${mensagem}</div>
            <span class="toast-time">${time}</span>
        </div>
        <button class="toast-close" onclick="this.closest('.notification-toast').remove()">✕</button>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.add('removing');
            setTimeout(() => { if (toast.parentNode) toast.remove(); }, 400);
        }
    }, 5000);
}

// ==========================================
// CARREGAR DISPOSITIVOS
// ==========================================
function carregarDispositivos() {
    fetch('financeiro/api_notificacoes_multi.php?acao=listar_dispositivos')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('listaDispositivos');
            const qtd = document.getElementById('qtdDispositivos');
            
            if (data.success && data.data && data.data.length > 0) {
                const ativos = data.data.filter(d => d.ativo == 1);
                qtd.textContent = `(${ativos.length} ativo${ativos.length > 1 ? 's' : ''})`;
                
                let html = '';
                data.data.forEach(disp => {
                    const status = disp.ativo ? 'ativo' : 'inativo';
                    const statusText = disp.ativo ? '🟢 Ativo' : '🔴 Inativo';
                    const icon = disp.dispositivo_tipo === 'mobile' ? '📱' : 
                                 disp.dispositivo_tipo === 'tablet' ? '📱' : '💻';
                    
                    const diff = Math.floor((Date.now() - new Date(disp.ultima_atividade).getTime()) / 1000);
                    let tempoTexto = 'agora';
                    if (diff > 60) tempoTexto = Math.floor(diff / 60) + ' min atrás';
                    if (diff > 3600) tempoTexto = Math.floor(diff / 3600) + 'h atrás';
                    if (diff > 86400) tempoTexto = Math.floor(diff / 86400) + ' dias atrás';
                    
                    html += `
                        <div class="dispositivo-item">
                            <div class="info">
                                <span>${icon}</span>
                                <span><strong>${disp.dispositivo_nome}</strong></span>
                                <span class="status ${status}">${statusText}</span>
                                <span style="font-size:12px;color:#94a3b8;">
                                    ${disp.ip ? '📡 ' + disp.ip : ''}
                                </span>
                            </div>
                            <span class="tempo">🕐 ${tempoTexto}</span>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                qtd.textContent = '(0 ativo)';
                container.innerHTML = `
                    <div style="text-align:center;padding:20px;color:#94a3b8;font-size:13px;">
                        📭 Nenhum dispositivo conectado ainda
                        <br><span style="font-size:12px;">Conecte seu celular escaneando o QR Code</span>
                    </div>
                `;
            }
        })
        .catch(() => {
            document.getElementById('listaDispositivos').innerHTML = `
                <div style="text-align:center;padding:20px;color:#e74c3c;font-size:13px;">
                    ❌ Erro ao carregar dispositivos
                </div>
            `;
        });
}

// ==========================================
// ENVIAR NOTIFICAÇÃO CONFIÁVEL
// ==========================================
async function enviarNotificacaoConfiavel() {
    const status = document.getElementById('statusNotificacao');
    status.textContent = '⏳ Enviando notificação...';
    status.className = 'status-info';
    
    try {
        // Verificar se há celular conectado
        const responseDispositivos = await fetch('/softgest_web/modules/escola/financeiro/api_notificacoes_multi.php?acao=listar_dispositivos');
        const dadosDispositivos = await responseDispositivos.json();
        
        let mobileToken = null;
        let mobileNome = null;
        
        if (dadosDispositivos.success && dadosDispositivos.data) {
            const mobile = dadosDispositivos.data.find(d => d.dispositivo_tipo === 'mobile' && d.ativo == 1);
            if (mobile) {
                mobileToken = mobile.token;
                mobileNome = mobile.dispositivo_nome;
            }
        }
        
        if (!mobileToken) {
            status.textContent = '📱 Nenhum celular conectado! Escaneie o QR Code primeiro.';
            status.className = 'status-warning';
            return;
        }
        
        status.textContent = '📱 Enviando para ' + mobileNome + '...';
        status.className = 'status-info';
        
        // Enviar notificação
        const formData = new FormData();
        formData.append('acao', 'enviar');
        formData.append('token', mobileToken);
        formData.append('titulo', '🧪 Teste Confiável');
        formData.append('mensagem', '✅ Esta notificação foi enviada pelo sistema confiável! Recebida em ' + new Date().toLocaleTimeString());
        formData.append('icone', '✅');
        formData.append('cor', 'success');
        formData.append('link', '/softgest_web/modules/escola/financeiro/');
        
        const response = await fetch('/softgest_web/modules/escola/financeiro/api_notificacoes_multi.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            status.textContent = '✅ Notificação enviada com sucesso! ID: ' + data.id + ' - Aguardando confirmação...';
            status.className = 'status-success';
            
            // Verificar se chegou
            setTimeout(async () => {
                const busca = await fetch('/softgest_web/modules/escola/financeiro/api_notificacoes_multi.php?acao=buscar&token=' + mobileToken + '&limite=3');
                const dadosBusca = await busca.json();
                
                if (dadosBusca.success && dadosBusca.data && dadosBusca.data.length > 0) {
                    status.textContent = '✅ Notificação recebida pelo celular! Verifique o alerta.';
                    status.className = 'status-success';
                }
            }, 3000);
        } else {
            status.textContent = '❌ Erro: ' + (data.error || 'Erro desconhecido');
            status.className = 'status-error';
        }
    } catch (error) {
        status.textContent = '❌ Erro: ' + error.message;
        status.className = 'status-error';
    }
}

// ==========================================
// ENVIAR MÚLTIPLAS NOTIFICAÇÕES
// ==========================================
async function enviarNotificacaoMultipla() {
    const status = document.getElementById('statusNotificacao');
    status.textContent = '⏳ Enviando 5 notificações...';
    status.className = 'status-info';
    
    const mensagens = [
        '📌 Notificação 1 - Teste de sistema',
        '📌 Notificação 2 - Conexão estável',
        '📌 Notificação 3 - Recebimento confirmado',
        '📌 Notificação 4 - Sistema funcionando',
        '📌 Notificação 5 - Última notificação do teste'
    ];
    
    let enviadas = 0;
    for (let i = 0; i < mensagens.length; i++) {
        try {
            const formData = new FormData();
            formData.append('acao', 'enviar');
            formData.append('token', obterToken());
            formData.append('titulo', '📨 Teste Múltiplo #' + (i + 1));
            formData.append('mensagem', mensagens[i] + ' - ' + new Date().toLocaleTimeString());
            formData.append('icone', '📨');
            formData.append('cor', 'gold');
            
            await fetch('/softgest_web/modules/escola/financeiro/api_notificacoes_multi.php', {
                method: 'POST',
                body: formData
            });
            enviadas++;
            await new Promise(r => setTimeout(r, 500));
        } catch (e) {
            console.error('Erro na notificação ' + (i+1), e);
        }
    }
    
    status.textContent = '✅ ' + enviadas + ' notificações enviadas com sucesso!';
    status.className = 'status-success';
}

// ==========================================
// TESTAR CONEXÃO COMPLETA
// ==========================================
async function testarConexaoCompleta() {
    const status = document.getElementById('statusNotificacao');
    status.textContent = '📡 Testando conexão...';
    status.className = 'status-info';
    
    try {
        // 1. Verificar token
        const token = obterToken();
        if (!token) {
            status.textContent = '❌ Nenhum token encontrado!';
            status.className = 'status-error';
            return;
        }
        
        // 2. Verificar dispositivos
        const response = await fetch('/softgest_web/modules/escola/financeiro/api_notificacoes_multi.php?acao=listar_dispositivos');
        const data = await response.json();
        
        let relatorio = '📡 Conexão: OK\n';
        relatorio += '🔑 Token: ' + token.substring(0, 20) + '...\n';
        
        if (data.success && data.data) {
            const mobile = data.data.find(d => d.dispositivo_tipo === 'mobile' && d.ativo == 1);
            const web = data.data.find(d => d.dispositivo_tipo === 'web' && d.ativo == 1);
            
            if (mobile) {
                relatorio += '📱 Celular: ' + mobile.dispositivo_nome + ' (✅ Conectado)\n';
            } else {
                relatorio += '📱 Celular: ❌ Nenhum celular conectado\n';
            }
            
            if (web) {
                relatorio += '💻 Computador: ' + web.dispositivo_nome + ' (✅ Conectado)\n';
            }
        }
        
        status.textContent = relatorio.replace(/\n/g, ' | ');
        status.className = data.data && data.data.find(d => d.dispositivo_tipo === 'mobile' && d.ativo == 1) ? 'status-success' : 'status-warning';
        
        // Se tiver celular, enviar notificação de teste
        if (data.data && data.data.find(d => d.dispositivo_tipo === 'mobile' && d.ativo == 1)) {
            setTimeout(async () => {
                await enviarNotificacaoConfiavel();
            }, 1000);
        }
    } catch (error) {
        status.textContent = '❌ Erro no teste: ' + error.message;
        status.className = 'status-error';
    }
}

// ==========================================
// INICIALIZAR
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔑 TOKEN_PHP:', TOKEN_PHP ? TOKEN_PHP.substring(0, 20) + '...' : 'NENHUM');
    
    // Verificar token no storage
    const tokenStorage = sessionStorage.getItem('notif_token_multi') || localStorage.getItem('notif_token_multi');
    console.log('🔑 Token no storage:', tokenStorage ? tokenStorage.substring(0, 20) + '...' : 'NENHUM');
    
    if (!tokenStorage && TOKEN_PHP) {
        sessionStorage.setItem('notif_token_multi', TOKEN_PHP);
        localStorage.setItem('notif_token_multi', TOKEN_PHP);
        console.log('✅ Token salvo do PHP para o storage');
    }
    
    // Gerar QR Code
    gerarQRCode();
    
    // Carregar dispositivos
    carregarDispositivos();
    
    // Atualizar a cada 30 segundos
    setInterval(carregarDispositivos, 30000);
    
    // Verificar se veio com token na URL
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('notif_token');
    if (token) {
        sessionStorage.setItem('notif_token_multi', token);
        localStorage.setItem('notif_token_multi', token);
        mostrarToast('✅ Dispositivo conectado com sucesso!', 'success');
    }
});

console.log('📱 Sistema de notificações confiável carregado!');
console.log('💡 Use os botões na seção "Teste de Notificação" para enviar notificações.');
</script>

<?php include __DIR__ . '/includes/footer_escola.php'; ?>