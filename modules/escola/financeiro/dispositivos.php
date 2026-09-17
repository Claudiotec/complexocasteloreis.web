<?php
require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

include '../../includes/header_escola.php';
?>

<div style="max-width:900px;margin:30px auto;padding:0 20px;">
    <div style="background:#fff;border-radius:16px;padding:30px;box-shadow:0 4px 25px rgba(0,0,0,0.06);border:1px solid #eef2f7;">
        <h2 style="color:#1a2332;margin:0 0 20px;">📱 Dispositivos Conectados</h2>
        
        <div style="margin-bottom:20px;padding:16px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
            <p style="margin:0;color:#4a5568;font-size:14px;">
                <strong>💡 Como funciona:</strong> Ao acessar o sistema em qualquer dispositivo (celular, tablet, outro computador) 
                na mesma rede, você receberá notificações em tempo real de novos pagamentos.
            </p>
        </div>
        
        <div id="dispositivosList">
            <div style="text-align:center;padding:30px;color:#94a3b8;">
                <div style="font-size:40px;margin-bottom:10px;">📱</div>
                <p>Carregando dispositivos conectados...</p>
            </div>
        </div>
        
        <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid #eef2f7;padding-top:20px;">
            <button onclick="compartilharToken()" class="btn btn-primary" style="padding:10px 24px;background:#c9a84c;color:#1a2332;border:none;border-radius:8px;cursor:pointer;font-weight:600;">
                🔗 Compartilhar Token
            </button>
            <button onclick="atualizarDispositivos()" class="btn btn-secondary" style="padding:10px 24px;background:#f1f5f9;color:#4a5568;border:none;border-radius:8px;cursor:pointer;font-weight:600;">
                🔄 Atualizar
            </button>
        </div>
    </div>
</div>

<script>
function carregarDispositivos() {
    fetch('api_notificacoes_mobile.php?acao=listar_dispositivos')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('dispositivosList');
            
            if (data.success && data.data.length > 0) {
                let html = `
                    <div style="display:grid;gap:12px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:10px;padding:10px 14px;background:#f1f5f9;border-radius:8px;font-weight:600;font-size:12px;color:#1a2332;">
                            <span>Dispositivo</span>
                            <span>Tipo</span>
                            <span>Última Atividade</span>
                            <span>Status</span>
                            <span>Ação</span>
                        </div>
                `;
                
                data.data.forEach(disp => {
                    const status = disp.ativo ? '🟢 Ativo' : '🔴 Inativo';
                    const statusColor = disp.ativo ? '#2ecc71' : '#e74c3c';
                    const icons = {
                        'web': '🖥️',
                        'mobile': '📱',
                        'tablet': '📱'
                    };
                    const icon = icons[disp.dispositivo_tipo] || '🖥️';
                    const tempo = new Date(disp.ultima_atividade).toLocaleString('pt-BR');
                    
                    html += `
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:10px;padding:12px 14px;background:#fafbfc;border-radius:8px;border:1px solid #eef2f7;align-items:center;font-size:13px;">
                            <span>${icon} ${disp.dispositivo_nome}</span>
                            <span style="font-size:12px;color:#94a3b8;">${disp.dispositivo_tipo}</span>
                            <span style="font-size:12px;color:#94a3b8;">${tempo}</span>
                            <span style="color:${statusColor};font-weight:600;">${status}</span>
                            <button onclick="desativarDispositivo('${disp.token}')" style="background:#fee2e2;color:#991b1b;border:none;padding:4px 12px;border-radius:6px;cursor:pointer;font-size:12px;">
                                ✕
                            </button>
                        </div>
                    `;
                });
                
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div style="text-align:center;padding:40px;color:#94a3b8;">
                        <div style="font-size:48px;margin-bottom:15px;">📭</div>
                        <p style="font-size:16px;color:#1a2332;">Nenhum dispositivo conectado</p>
                        <p style="font-size:13px;margin-top:5px;">Acesse o sistema em outro dispositivo para receber notificações.</p>
                    </div>
                `;
            }
        })
        .catch(() => {
            document.getElementById('dispositivosList').innerHTML = `
                <div style="text-align:center;padding:30px;color:#e74c3c;">
                    <p>Erro ao carregar dispositivos</p>
                </div>
            `;
        });
}

function desativarDispositivo(token) {
    if (!confirm('Desativar este dispositivo?')) return;
    
    const formData = new FormData();
    formData.append('acao', 'desativar');
    formData.append('token', token);
    
    fetch('api_notificacoes_mobile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Dispositivo desativado');
            carregarDispositivos();
        }
    });
}

function compartilharToken() {
    const token = sessionStorage.getItem('notif_token') || localStorage.getItem('notif_token');
    if (token) {
        if (navigator.share) {
            navigator.share({
                title: 'Token de Notificações',
                text: `Conecte-se para receber notificações: ${token}`,
                url: window.location.origin + window.location.pathname + '?notif_token=' + token
            });
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(token).then(() => {
                alert('✅ Token copiado! Compartilhe com outros dispositivos.');
            });
        } else {
            prompt('Copie este token:', token);
        }
    } else {
        alert('⚠️ Nenhum token disponível. Recarregue a página.');
    }
}

function atualizarDispositivos() {
    carregarDispositivos();
}

// Carregar ao iniciar
document.addEventListener('DOMContentLoaded', carregarDispositivos);
</script>

<?php include '../../includes/footer_escola.php'; ?>