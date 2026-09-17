// ============================================
// js/reconnect.js - Reconexão Automática
// ============================================

class ConnectionManager {
    constructor() {
        this.maxRetries = 10;
        this.retryCount = 0;
        this.retryDelay = 2000;
        this.baseUrl = window.location.origin + '/softgest_web/';
        this.isConnected = true;
        this.init();
    }
    
    init() {
        this.monitorConnection();
        this.startPing();
    }
    
    monitorConnection() {
        window.addEventListener('online', () => {
            console.log('🟢 Conexão restaurada');
            this.isConnected = true;
            this.retryCount = 0;
            this.showNotification('Conexão restaurada!', 'success');
        });
        
        window.addEventListener('offline', () => {
            console.log('🔴 Conexão perdida');
            this.isConnected = false;
            this.showNotification('Conexão perdida. Tentando reconectar...', 'error');
        });
    }
    
    startPing() {
        setInterval(() => {
            this.ping();
        }, 15000);
    }
    
    async ping() {
        try {
            const response = await fetch(this.baseUrl + 'api/sync.php?acao=ping', {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            
            if (response.ok) {
                if (!this.isConnected) {
                    this.isConnected = true;
                    this.retryCount = 0;
                    this.showNotification('Conexão restaurada!', 'success');
                }
                return true;
            }
        } catch (error) {
            console.log('Ping falhou:', error);
            this.handleDisconnect();
            return false;
        }
    }
    
    handleDisconnect() {
        this.retryCount++;
        this.isConnected = false;
        
        if (this.retryCount <= this.maxRetries) {
            console.log(`Tentativa ${this.retryCount} de reconexão...`);
            this.showNotification(`Tentando reconectar (${this.retryCount}/${this.maxRetries})...`, 'warning');
            
            setTimeout(() => {
                this.ping();
            }, this.retryDelay * this.retryCount);
        } else {
            this.showNotification('Falha na conexão. Verifique sua rede.', 'error');
        }
    }
    
    showNotification(message, type = 'info') {
        // Verifica se já existe notificação
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = `
                position: fixed;
                bottom: 80px;
                right: 20px;
                z-index: 9999;
                max-width: 350px;
            `;
            document.body.appendChild(container);
        }
        
        const colors = {
            success: 'background: #d1fae5; color: #065f46; border-left: 4px solid #2ecc71;',
            error: 'background: #fee2e2; color: #991b1b; border-left: 4px solid #e74c3c;',
            warning: 'background: #fef3c7; color: #92400e; border-left: 4px solid #f39c12;',
            info: 'background: #e0f2fe; color: #075985; border-left: 4px solid #3498db;'
        };
        
        const notification = document.createElement('div');
        notification.style.cssText = `
            ${colors[type] || colors.info}
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            animation: slideIn 0.5s ease;
        `;
        notification.textContent = message;
        
        container.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.5s ease';
            setTimeout(() => notification.remove(), 500);
        }, 5000);
    }
}

// Adicionar animações CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    window.connectionManager = new ConnectionManager();
});