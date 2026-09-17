// ============================================
// notificacoes_confiavel.js - Sistema Confiável
// ============================================

class SistemaNotificacoes {
    constructor(config = {}) {
        this.apiUrl = config.apiUrl || '/softgest_web/modules/escola/financeiro/sse_notificacoes.php';
        this.enviarUrl = config.enviarUrl || '/softgest_web/modules/escola/financeiro/enviar_notificacao.php';
        this.token = config.token || null;
        this.usuarioId = config.usuarioId || null;
        this.onNotification = config.onNotification || null;
        this.onError = config.onError || null;
        this.eventSource = null;
        this.ultimoId = 0;
        this.reconectarTimeout = null;
        this.tentativasReconexao = 0;
        this.maxTentativas = 10;
        this.estaAtivo = false;
        this.pollingInterval = null;
        this.usarPolling = false;
        
        this.iniciar();
    }
    
    iniciar() {
        // Tentar SSE primeiro
        if (typeof EventSource !== 'undefined' && !this.usarPolling) {
            this.iniciarSSE();
        } else {
            this.iniciarPolling();
        }
    }
    
    iniciarSSE() {
        try {
            const url = this.apiUrl + '?last_id=' + this.ultimoId + '&t=' + Date.now();
            this.eventSource = new EventSource(url);
            
            this.eventSource.onopen = () => {
                console.log('📡 Conexão SSE estabelecida');
                this.tentativasReconexao = 0;
                this.estaAtivo = true;
                this.mostrarStatus('🟢 Conectado via SSE', 'success');
            };
            
            this.eventSource.onmessage = (event) => {
                if (event.data && event.data !== 'keepalive') {
                    try {
                        const data = JSON.parse(event.data);
                        this.processarNotificacao(data);
                    } catch (e) {
                        console.log('Heartbeat:', event.data);
                    }
                }
            };
            
            this.eventSource.addEventListener('notification', (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.processarNotificacao(data);
                } catch (e) {
                    console.error('Erro ao processar notificação:', e);
                }
            });
            
            this.eventSource.onerror = (error) => {
                console.warn('⚠️ Erro no SSE:', error);
                this.eventSource.close();
                this.estaAtivo = false;
                
                // Tentar reconectar com backoff exponencial
                const tempo = Math.min(1000 * Math.pow(1.5, this.tentativasReconexao), 30000);
                this.tentativasReconexao++;
                
                if (this.tentativasReconexao <= this.maxTentativas) {
                    this.mostrarStatus(`🔄 Reconectando em ${Math.round(tempo/1000)}s...`, 'info');
                    setTimeout(() => {
                        this.iniciarSSE();
                    }, tempo);
                } else {
                    // Fallback para polling
                    this.mostrarStatus('🔄 Mudando para modo polling...', 'warning');
                    this.usarPolling = true;
                    this.iniciarPolling();
                }
            };
            
        } catch (error) {
            console.error('Erro ao iniciar SSE:', error);
            this.usarPolling = true;
            this.iniciarPolling();
        }
    }
    
    iniciarPolling() {
        this.mostrarStatus('📡 Conectado via Polling', 'info');
        this.buscarNotificacoes();
        this.pollingInterval = setInterval(() => {
            this.buscarNotificacoes();
        }, 3000);
    }
    
    async buscarNotificacoes() {
        try {
            const response = await fetch('/softgest_web/modules/escola/financeiro/api_notificacoes_multi.php?acao=buscar&token=' + this.token + '&limite=10');
            const data = await response.json();
            
            if (data.success && data.data && data.data.length > 0) {
                data.data.forEach(notif => {
                    this.processarNotificacao(notif);
                });
            }
        } catch (error) {
            console.error('Erro no polling:', error);
        }
    }
    
    processarNotificacao(data) {
        console.log('📬 Notificação recebida:', data);
        
        // Atualizar último ID
        if (data.id > this.ultimoId) {
            this.ultimoId = data.id;
        }
        
        // Mostrar notificação visual
        this.mostrarNotificacao(data);
        
        // Chamar callback
        if (this.onNotification) {
            this.onNotification(data);
        }
        
        // Disparar evento customizado
        document.dispatchEvent(new CustomEvent('notificacao-recebida', {
            detail: data
        }));
    }
    
    mostrarNotificacao(data) {
        // 1. ALERTA (SEMPRE FUNCIONA)
        alert('🔔 ' + data.titulo + '\n\n' + data.mensagem);
        
        // 2. TOAST (opcional)
        this.mostrarToast(data);
        
        // 3. NOTIFICAÇÃO DO NAVEGADOR
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(data.titulo, {
                body: data.mensagem,
                icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🔔</text></svg>',
                vibrate: [100, 50, 100]
            });
        }
        
        // 4. VIBRAÇÃO (mobile)
        if (navigator.vibrate) {
            navigator.vibrate([100, 50, 100, 50, 100]);
        }
        
        // 5. SOM
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) {}
    }
    
    mostrarToast(data) {
        let container = document.getElementById('toastNotificacoes');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastNotificacoes';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 999999;
                max-width: 380px;
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        const cores = { success: '#2ecc71', error: '#e74c3c', warning: '#f39c12', gold: '#c9a84c' };
        const cor = cores[data.cor] || '#c9a84c';
        
        toast.style.cssText = `
            pointer-events: all;
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 18px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            border-left: 4px solid ${cor};
            animation: toastEntrada 0.5s ease forwards;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: ${data.link ? 'pointer' : 'default'};
        `;
        
        toast.innerHTML = `
            <div style="font-size:24px;flex-shrink:0;">${data.icone || '📬'}</div>
            <div style="flex:1;">
                <div style="font-weight:700;color:#1a2332;font-size:14px;">${this.escapeHtml(data.titulo)}</div>
                <div style="font-size:13px;color:#4a5568;margin-top:3px;">${this.escapeHtml(data.mensagem)}</div>
                <span style="font-size:11px;color:#94a3b8;margin-top:4px;display:block;">${new Date().toLocaleTimeString()}</span>
            </div>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;">✕</button>
        `;
        
        if (data.link) {
            toast.addEventListener('click', function(e) {
                if (!e.target.closest('button')) {
                    window.location.href = data.link;
                }
            });
        }
        
        container.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'toastSaida 0.4s ease forwards';
                setTimeout(() => { if (toast.parentNode) toast.remove(); }, 400);
            }
        }, 8000);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    mostrarStatus(texto, tipo = 'info') {
        const statusEl = document.getElementById('statusNotificacao');
        if (statusEl) {
            statusEl.textContent = texto;
            statusEl.className = 'status-' + tipo;
        }
        console.log('📡 ' + texto);
    }
    
    enviar(titulo, mensagem, icone = '📬', link = null, cor = 'gold') {
        return new Promise(async (resolve, reject) => {
            try {
                const formData = new FormData();
                formData.append('titulo', titulo);
                formData.append('mensagem', mensagem);
                formData.append('icone', icone);
                if (link) formData.append('link', link);
                formData.append('cor', cor);
                
                const response = await fetch(this.enviarUrl, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                resolve(data);
            } catch (error) {
                reject(error);
            }
        });
    }
    
    parar() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        this.estaAtivo = false;
    }
}

// ============================================
// INICIALIZAR GLOBALMENTE
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar estilos de animação
    const style = document.createElement('style');
    style.textContent = `
        @keyframes toastEntrada {
            from { opacity: 0; transform: translateX(80px) scale(0.95); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }
        @keyframes toastSaida {
            from { opacity: 1; transform: translateX(0) scale(1); }
            to { opacity: 0; transform: translateX(80px) scale(0.95); }
        }
        .status-success { background: #d1fae5; color: #065f46; padding: 8px 16px; border-radius: 8px; }
        .status-error { background: #fee2e2; color: #991b1b; padding: 8px 16px; border-radius: 8px; }
        .status-info { background: #dbeafe; color: #1e40af; padding: 8px 16px; border-radius: 8px; }
        .status-warning { background: #fef9e8; color: #78350f; padding: 8px 16px; border-radius: 8px; }
    `;
    document.head.appendChild(style);
    
    // Verificar token
    let token = sessionStorage.getItem('notif_token_multi') || 
                localStorage.getItem('notif_token_multi');
    
    if (!token) {
        const urlParams = new URLSearchParams(window.location.search);
        token = urlParams.get('notif_token');
    }
    
    if (token) {
        console.log('✅ Token encontrado:', token.substring(0, 20) + '...');
        sessionStorage.setItem('notif_token_multi', token);
        localStorage.setItem('notif_token_multi', token);
        
        // Solicitar permissão para notificações
        if ('Notification' in window && Notification.permission !== 'granted' && Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
        
        // Iniciar sistema
        window.notificacoes = new SistemaNotificacoes({
            token: token,
            onNotification: function(data) {
                console.log('🔔 Notificação processada:', data);
            }
        });
        
        console.log('📱 Sistema de notificações confiável iniciado!');
    } else {
        console.warn('⚠️ Nenhum token encontrado');
    }
});

// ============================================
// FUNÇÃO GLOBAL PARA ENVIAR NOTIFICAÇÕES
// ============================================
window.enviarNotificacao = async function(titulo, mensagem, icone = '📬', link = null, cor = 'gold') {
    if (window.notificacoes) {
        return await window.notificacoes.enviar(titulo, mensagem, icone, link, cor);
    }
    return { success: false, error: 'Sistema não inicializado' };
};