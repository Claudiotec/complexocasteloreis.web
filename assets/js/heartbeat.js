// ============================================
// heartbeat.js - Mantém o sistema ativo
// ============================================

(function() {
    'use strict';

    // ============================================
    // CONFIGURAÇÕES
    // ============================================
    const CONFIG = {
        // Intervalo em milissegundos (30 segundos)
        intervalo: 30000,
        // URL do keep alive
        url: '/softgest_web/keep_alive.php',
        // Tempo máximo sem resposta (2 minutos)
        timeoutMax: 120000,
        // Tentativas de reconexão
        maxTentativas: 5
    };

    // ============================================
    // VARIÁVEIS
    // ============================================
    let tentativas = 0;
    let ultimoPing = Date.now();
    let intervaloId = null;
    let timeoutId = null;

    // ============================================
    // FUNÇÃO PRINCIPAL - PING
    // ============================================
    function enviarPing() {
        // Verificar se a página está visível
        if (document.hidden) {
            // Se a página estiver oculta, ainda mantém a conexão
            console.log('💤 Página em segundo plano, mantendo conexão...');
        }

        fetch(CONFIG.url, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Resposta do servidor: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            // Sucesso - resetar tentativas
            tentativas = 0;
            ultimoPing = Date.now();
            
            // Mostrar no console (apenas em desenvolvimento)
            if (data.usuario) {
                console.log(`💓 Keep Alive: ${data.usuario} - ${new Date(data.timestamp * 1000).toLocaleTimeString()}`);
            } else {
                console.log('💓 Keep Alive: OK');
            }
            
            // Atualizar indicador visual se existir
            atualizarIndicador(true);
        })
        .catch(error => {
            console.error('❌ Erro no Keep Alive:', error);
            tentativas++;
            
            // Se exceder tentativas, tentar reconectar
            if (tentativas >= CONFIG.maxTentativas) {
                console.warn('⚠️ Muitas tentativas falhas. Tentando reconectar...');
                tentativas = 0;
                reconectar();
            }
            
            // Atualizar indicador visual
            atualizarIndicador(false);
        });
    }

    // ============================================
    // RECONEXÃO
    // ============================================
    function reconectar() {
        console.log('🔄 Tentando reconectar ao sistema...');
        
        // Tentar recarregar a sessão
        fetch('/softgest_web/index.php', {
            method: 'HEAD',
            credentials: 'same-origin'
        })
        .then(() => {
            console.log('✅ Reconexão bem-sucedida!');
            // Forçar um ping imediato
            setTimeout(enviarPing, 1000);
        })
        .catch(() => {
            console.error('❌ Falha na reconexão. Tentando novamente em 30 segundos...');
            setTimeout(reconectar, 30000);
        });
    }

    // ============================================
    // INDICADOR VISUAL (OPCIONAL)
    // ============================================
    function atualizarIndicador(conectado) {
        const indicador = document.getElementById('statusSistema');
        if (!indicador) return;
        
        if (conectado) {
            indicador.innerHTML = '🟢 Sistema Online';
            indicador.style.color = '#2ecc71';
            indicador.style.background = '#d1fae5';
        } else {
            indicador.innerHTML = '🔴 Reconectando...';
            indicador.style.color = '#e74c3c';
            indicador.style.background = '#fee2e2';
        }
    }

    // ============================================
    // CRIAR INDICADOR VISUAL (SE NÃO EXISTIR)
    // ============================================
    function criarIndicador() {
        // Verificar se já existe
        if (document.getElementById('statusSistema')) return;
        
        const indicador = document.createElement('div');
        indicador.id = 'statusSistema';
        indicador.style.cssText = `
            position: fixed;
            bottom: 10px;
            right: 10px;
            z-index: 9999;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #d1fae5;
            color: #2ecc71;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-family: Arial, sans-serif;
            transition: all 0.3s ease;
        `;
        indicador.innerHTML = '🟢 Sistema Online';
        document.body.appendChild(indicador);
    }

    // ============================================
    // INICIAR
    // ============================================
    function iniciar() {
        // Criar indicador visual
        criarIndicador();
        
        // Enviar ping imediato
        setTimeout(enviarPing, 1000);
        
        // Configurar intervalo
        if (intervaloId) {
            clearInterval(intervaloId);
        }
        intervaloId = setInterval(enviarPing, CONFIG.intervalo);
        
        // Verificar timeout (se não receber resposta por muito tempo)
        if (timeoutId) {
            clearInterval(timeoutId);
        }
        timeoutId = setInterval(function() {
            const tempoSemResposta = Date.now() - ultimoPing;
            if (tempoSemResposta > CONFIG.timeoutMax) {
                console.warn('⚠️ Tempo sem resposta excedido. Tentando reconectar...');
                reconectar();
                // Resetar contador
                ultimoPing = Date.now();
            }
        }, CONFIG.intervalo);
        
        console.log('💓 Sistema Keep Alive iniciado!');
    }

    // ============================================
    // PARAR
    // ============================================
    function parar() {
        if (intervaloId) {
            clearInterval(intervaloId);
            intervaloId = null;
        }
        if (timeoutId) {
            clearInterval(timeoutId);
            timeoutId = null;
        }
        console.log('💤 Keep Alive parado.');
    }

    // ============================================
    // EVENTOS
    // ============================================
    
    // Iniciar quando a página carregar
    document.addEventListener('DOMContentLoaded', iniciar);
    
    // Manter ativo quando a página for reativada
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // Página voltou a ficar visível, enviar ping imediato
            console.log('👀 Página reativada, verificando conexão...');
            enviarPing();
        }
    });
    
    // Parar quando a página for fechada
    window.addEventListener('beforeunload', function() {
        parar();
    });

    // ============================================
    // EXPOR FUNÇÕES PARA USO EXTERNO
    // ============================================
    window.KeepAlive = {
        iniciar: iniciar,
        parar: parar,
        ping: enviarPing,
        status: function() {
            return {
                conectado: tentativas < CONFIG.maxTentativas,
                ultimoPing: new Date(ultimoPing).toLocaleTimeString(),
                tentativas: tentativas
            };
        }
    };

    console.log('💓 Keep Alive carregado. Versão 1.0');
})();