// ============================================
// NOTIFICATION MOBILE - Plugin Multi-dispositivo
// ============================================

class NotificationMobile {
    constructor(config = {}) {
        this.apiUrl = config.apiUrl || '/modules/escola/financeiro/api_notificacoes_mobile.php';
        this.token = this.getToken();
        this.deviceName = config.deviceName || this.generateDeviceName();
        this.pollingInterval = config.pollingInterval || 3000; // 3 segundos
        this.onNotification = config.onNotification || null;
        this.onError = config.onError || null;
        this.isRegistered = false;
        this.intervalId = null;
        
        this.init();
    }
    
    init() {
        // Registrar dispositivo se não tiver token
        if (!this.token) {
            this.registerDevice();
        } else {
            this.isRegistered = true;
            this.startPolling();
            this.updateActivity();
        }
        
        // Verificar token na URL (para compartilhamento)
        this.checkUrlToken();
    }
    
    generateDeviceName() {
        const tipo = /Mobile/i.test(navigator.userAgent) ? 'mobile' : 
                     /Tablet/i.test(navigator.userAgent) ? 'tablet' : 'web';
        const data = new Date().toISOString().replace(/[:.]/g, '').slice(0, 14);
        return `${tipo}_${data}`;
    }
    
    getToken() {
        // Tentar pegar da sessão
        let token = sessionStorage.getItem('notif_token');
        
        // Tentar pegar da URL
        if (!token) {
            const urlParams = new URLSearchParams(window.location.search);
            token = urlParams.get('notif_token');
        }
        
        // Tentar pegar de localStorage (compartilhado entre abas)
        if (!token) {
            token = localStorage.getItem('notif_token');
        }
        
        return token;
    }
    
    setToken(token) {
        this.token = token;
        sessionStorage.setItem('notif_token', token);
        localStorage.setItem('notif_token', token);
        
        // Atualizar URL sem recarregar
        const url = new URL(window.location);
        url.searchParams.set('notif_token', token);
        window.history.replaceState({}, '', url);
    }
    
    checkUrlToken() {
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('notif_token');
        if (token && token !== this.token) {
            this.token = token;
            sessionStorage.setItem('notif_token', token);
            localStorage.setItem('notif_token', token);
            this.startPolling();
        }
    }
    
    async registerDevice() {
        try {
            const formData = new FormData();
            formData.append('acao', 'registrar');
            formData.append('dispositivo_nome', this.deviceName);
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.setToken(data.token);
                this.isRegistered = true;
                this.startPolling();
                console.log('📱 Dispositivo registrado:', data.dispositivo_nome);
            } else {
                console.error('Erro ao registrar dispositivo:', data.error);
            }
        } catch (error) {
            console.error('Erro na conexão:', error);
            if (this.onError) this.onError(error);
        }
    }
    
    async updateActivity() {
        if (!this.token) return;
        
        try {
            const formData = new FormData();
            formData.append('acao', 'buscar');
            formData.append('token', this.token);
            formData.append('limite', 1);
            
            await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Erro ao atualizar atividade:', error);
        }
    }
    
    async startPolling() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
        
        // Buscar imediatamente
        this.pollNotifications();
        
        // Configurar polling
        this.intervalId = setInterval(() => {
            this.pollNotifications();
            this.updateActivity();
        }, this.pollingInterval);
    }
    
    async pollNotifications() {
        if (!this.token || !this.isRegistered) return;
        
        try {
            const formData = new FormData();
            formData.append('acao', 'buscar');
            formData.append('token', this.token);
            formData.append('limite', 20);
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success && data.data && data.data.length > 0) {
                // Processar notificações
                data.data.forEach(notificacao => {
                    if (this.onNotification) {
                        this.onNotification(notificacao);
                    }
                    this.showToast(notificacao);
                });
            }
        } catch (error) {
            console.error('Erro ao buscar notificações:', error);
        }
    }
    
    showToast(notificacao) {
        // Usar o sistema de toast existente ou criar um
        if (typeof showNotificationToast === 'function') {
            showNotificationToast({
                titulo: notificacao.titulo,
                mensagem: notificacao.mensagem,
                icone: notificacao.icone || '📬',
                cor: notificacao.cor || 'gold',
                link: notificacao.link || '#'
            });
        } else {
            // Fallback: alert simples
            console.log(`📬 ${notificacao.titulo}: ${notificacao.mensagem}`);
        }
    }
    
    async sendNotification(titulo, mensagem, icone = '📬', link = null, cor = 'gold') {
        if (!this.token) return;
        
        try {
            const formData = new FormData();
            formData.append('acao', 'enviar');
            formData.append('token', this.token);
            formData.append('titulo', titulo);
            formData.append('mensagem', mensagem);
            formData.append('icone', icone);
            if (link) formData.append('link', link);
            formData.append('cor', cor);
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            
            return await response.json();
        } catch (error) {
            console.error('Erro ao enviar notificação:', error);
            return { success: false, error: error.message };
        }
    }
    
    async sendToAll(titulo, mensagem, icone = '📬', link = null, cor = 'gold') {
        try {
            const formData = new FormData();
            formData.append('acao', 'enviar_todos');
            formData.append('titulo', titulo);
            formData.append('mensagem', mensagem);
            formData.append('icone', icone);
            if (link) formData.append('link', link);
            formData.append('cor', cor);
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            
            return await response.json();
        } catch (error) {
            console.error('Erro ao enviar notificação para todos:', error);
            return { success: false, error: error.message };
        }
    }
    
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
}

// ============================================
// INICIALIZAR GLOBALMENTE
// ============================================
let notificationMobile;

document.addEventListener('DOMContentLoaded', function() {
    notificationMobile = new NotificationMobile({
        pollingInterval: 3000,
        onNotification: function(notificacao) {
            console.log('📬 Nova notificação:', notificacao);
            // Disparar evento personalizado
            document.dispatchEvent(new CustomEvent('nova-notificacao', {
                detail: notificacao
            }));
        },
        onError: function(error) {
            console.error('Erro no sistema de notificações:', error);
        }
    });
    
    window.notificationMobile = notificationMobile;
});

// ============================================
// FUNÇÃO PARA ENVIAR NOTIFICAÇÃO DE PAGAMENTO
// ============================================
function notificarPagamentoMultiDispositivo(dados) {
    if (window.notificationMobile) {
        window.notificationMobile.sendToAll(
            dados.titulo || `💰 Novo Pagamento - ${dados.aluno_nome || ''}`,
            dados.mensagem || `Pagamento de ${dados.valor || '0,00'} Kz registrado.`,
            dados.icone || '💰',
            dados.link || '#',
            dados.cor || 'success'
        );
    }
}

// Expor função global
window.notificarPagamentoMultiDispositivo = notificarPagamentoMultiDispositivo;