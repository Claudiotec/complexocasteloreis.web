// ============================================
// notification-client.js - Cliente de Notificações
// ============================================

class NotificationClient {
    constructor(config = {}) {
        this.apiUrl = config.apiUrl || '/softgest_web/modules/escola/financeiro/api_notificacoes_multi.php';
        this.token = config.token || null;
        this.pollingInterval = config.pollingInterval || 3000;
        this.onNotification = config.onNotification || null;
        this.onError = config.onError || null;
        this.isRegistered = false;
        this.intervalId = null;
        
        if (this.token) {
            this.isRegistered = true;
            this.startPolling();
        }
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
                console.log('📬 ' + data.data.length + ' notificação(ões) recebida(s)');
                data.data.forEach(notificacao => {
                    if (this.onNotification) {
                        this.onNotification(notificacao);
                    }
                    this.mostrarNotificacao(notificacao);
                });
            }
        } catch (error) {
            console.error('Erro ao buscar notificações:', error);
            if (this.onError) this.onError(error);
        }
    }
    
    mostrarNotificacao(notificacao) {
        // Mostrar alerta
        alert('🔔 ' + notificacao.titulo + '\n\n' + notificacao.mensagem);
        
        // Tentar mostrar notificação do navegador
        if (Notification.permission === 'granted') {
            new Notification(notificacao.titulo, {
                body: notificacao.mensagem,
                icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🔔</text></svg>'
            });
        }
    }
    
    startPolling() {
        if (this.intervalId) clearInterval(this.intervalId);
        this.pollNotifications();
        this.intervalId = setInterval(() => {
            this.pollNotifications();
        }, this.pollingInterval);
        console.log('📡 Polling iniciado a cada ' + this.pollingInterval + 'ms');
    }
    
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
}

// Inicializar automaticamente
document.addEventListener('DOMContentLoaded', function() {
    const token = sessionStorage.getItem('notif_token_multi') || 
                  localStorage.getItem('notif_token_multi');
    
    if (token) {
        console.log('✅ Token encontrado:', token.substring(0, 20) + '...');
        
        // Verificar permissão para notificações
        if ('Notification' in window && Notification.permission !== 'granted' && Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
        
        window.notificationClient = new NotificationClient({
            pollingInterval: 3000,
            onNotification: function(notificacao) {
                console.log('📬 Notificação:', notificacao.titulo);
            }
        });
        window.notificationClient.token = token;
        window.notificationClient.isRegistered = true;
        window.notificationClient.startPolling();
    } else {
        console.log('⚠️ Nenhum token encontrado');
    }
});