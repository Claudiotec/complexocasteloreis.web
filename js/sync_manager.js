// ============================================
// js/sync_manager.js - Gerenciador de Sincronização
// ============================================

class SyncManager {
    constructor() {
        this.dbName = 'SoftGestDB';
        this.dbVersion = 1;
        this.db = null;
        this.syncInterval = 30000; // 30 segundos
        this.maxRetries = 5;
        this.retryDelay = 5000; // 5 segundos
        this.isOnline = navigator.onLine;
        this.pendingSyncs = [];
        this.init();
    }
    
    // Inicializar
    async init() {
        await this.openDatabase();
        this.loadPendingSyncs();
        this.startSyncInterval();
        this.setupEventListeners();
    }
    
    // Abrir banco de dados IndexedDB
    openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Store para dados offline
                if (!db.objectStoreNames.contains('offlineData')) {
                    const store = db.createObjectStore('offlineData', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('tabela', 'tabela', { unique: false });
                    store.createIndex('sincronizado', 'sincronizado', { unique: false });
                }
                
                // Store para fila de sincronização
                if (!db.objectStoreNames.contains('syncQueue')) {
                    const store = db.createObjectStore('syncQueue', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('tabela', 'tabela', { unique: false });
                    store.createIndex('tentativas', 'tentativas', { unique: false });
                }
            };
            
            request.onsuccess = (event) => {
                this.db = event.target.result;
                resolve(this.db);
            };
            
            request.onerror = (event) => {
                reject(event.target.error);
            };
        });
    }
    
    // Salvar dados offline
    async salvarOffline(tabela, dados) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['offlineData'], 'readwrite');
            const store = transaction.objectStore('offlineData');
            
            const registro = {
                tabela: tabela,
                dados: dados,
                sincronizado: false,
                criado_em: new Date().toISOString()
            };
            
            const request = store.add(registro);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    // Adicionar à fila de sincronização
    async adicionarSync(tabela, dados, acao = 'insert') {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['syncQueue'], 'readwrite');
            const store = transaction.objectStore('syncQueue');
            
            const syncItem = {
                tabela: tabela,
                dados: dados,
                acao: acao,
                tentativas: 0,
                criado_em: new Date().toISOString()
            };
            
            const request = store.add(syncItem);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    // Carregar sincronizações pendentes
    loadPendingSyncs() {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['syncQueue'], 'readonly');
        const store = transaction.objectStore('syncQueue');
        const index = store.index('tentativas');
        const request = index.getAll();
        
        request.onsuccess = () => {
            this.pendingSyncs = request.result;
        };
    }
    
    // Processar sincronização
    async processarSync() {
        if (!this.isOnline || this.pendingSyncs.length === 0) return;
        
        console.log('🔄 Processando sincronização...');
        
        for (const sync of this.pendingSyncs) {
            try {
                await this.enviarSync(sync);
                await this.removerSync(sync.id);
                console.log(`✅ Sincronizado: ${sync.tabela}`);
            } catch (error) {
                console.error(`❌ Erro ao sincronizar ${sync.tabela}:`, error);
                await this.incrementarTentativas(sync.id);
            }
        }
    }
    
    // Enviar sincronização
    async enviarSync(sync) {
        const response = await fetch('/softgest_web/api/sync.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                tabela: sync.tabela,
                dados: sync.dados,
                acao: sync.acao
            })
        });
        
        if (!response.ok) {
            throw new Error('Erro ao sincronizar');
        }
        
        return await response.json();
    }
    
    // Remover da fila
    async removerSync(id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['syncQueue'], 'readwrite');
            const store = transaction.objectStore('syncQueue');
            const request = store.delete(id);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }
    
    // Incrementar tentativas
    async incrementarTentativas(id) {
        const transaction = this.db.transaction(['syncQueue'], 'readwrite');
        const store = transaction.objectStore('syncQueue');
        const request = store.get(id);
        
        request.onsuccess = () => {
            const data = request.result;
            if (data) {
                data.tentativas = (data.tentativas || 0) + 1;
                if (data.tentativas < this.maxRetries) {
                    store.put(data);
                } else {
                    // Remover após muitas tentativas
                    store.delete(id);
                }
            }
        };
    }
    
    // Iniciar intervalo de sincronização
    startSyncInterval() {
        setInterval(() => {
            this.processarSync();
        }, this.syncInterval);
    }
    
    // Configurar event listeners
    setupEventListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            console.log('🟢 Conexão restaurada!');
            this.processarSync();
            this.notificarStatus('online');
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            console.log('🔴 Conexão perdida!');
            this.notificarStatus('offline');
        });
    }
    
    // Notificar status
    notificarStatus(status) {
        const evento = new CustomEvent('statusConexao', {
            detail: { status: status }
        });
        document.dispatchEvent(evento);
    }
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', () => {
    window.syncManager = new SyncManager();
});