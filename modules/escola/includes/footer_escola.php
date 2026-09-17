<?php
// ============================================
// footer_escola.php - Footer do Módulo Escola
// ============================================
?>

        </div> <!-- Fecha .content-area-escola -->
    </main> <!-- Fecha .main-content-escola -->
</div> <!-- Fecha .dashboard-container -->

<!-- ============================================
     FOOTER ESCOLA
     ============================================ -->
<footer class="escola-footer">
    <div class="footer-content">
        <div class="footer-left">
            <span class="footer-icon">🎓</span>
            <span class="footer-text">
                Módulo Gestão Escolar © <?= date('Y') ?> 
                <strong><?= htmlspecialchars($nomeEmpresa ?? 'SoftGest') ?></strong>
            </span>
        </div>
        <div class="footer-center">
            <span class="footer-version">
                <i class="fas fa-code"></i> v1.0
            </span>
            <span class="footer-divider">|</span>
            <span class="footer-status">
                <span class="status-dot"></span> Sistema Online
            </span>
        </div>
        <div class="footer-right">
            <span class="footer-mode">
                <i class="fas fa-laptop"></i> Local
            </span>
        </div>
    </div>
</footer>

<!-- ============================================
     STYLES - FOOTER E NOTIFICAÇÕES
     ============================================ -->
<style>
/* ==========================================
   FOOTER STYLES
   ========================================== */
.escola-footer {
    background: linear-gradient(135deg, #0f1724 0%, #1a2332 100%);
    border-top: 1px solid rgba(255,255,255,0.04);
    padding: 14px 25px;
    margin-top: 30px;
    width: 100%;
    flex-shrink: 0;
    position: relative;
    clear: both;
}

.footer-content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}

.footer-left {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(255,255,255,0.35);
    font-size: 13px;
}

.footer-left .footer-icon { font-size: 18px; }
.footer-left .footer-text strong { color: #c9a84c; font-weight: 600; }

.footer-center {
    display: flex;
    align-items: center;
    gap: 12px;
    color: rgba(255,255,255,0.25);
    font-size: 12px;
}

.footer-center .footer-divider { color: rgba(255,255,255,0.08); }
.footer-center .footer-status {
    display: flex;
    align-items: center;
    gap: 6px;
    color: rgba(255,255,255,0.3);
}

.footer-center .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #2ecc71;
    display: inline-block;
    animation: pulse-dot 2s ease-in-out infinite;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.footer-right {
    color: rgba(255,255,255,0.2);
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ==========================================
   NOTIFICAÇÕES EM TEMPO REAL
   ========================================== */
:root {
    --notif-gold: #c9a84c;
    --notif-dark: #1a2332;
    --notif-gray: #94a3b8;
    --notif-white: #ffffff;
    --notif-shadow: 0 10px 40px rgba(0,0,0,0.12);
    --notif-radius: 12px;
}

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
    background: var(--notif-white);
    border-radius: var(--notif-radius);
    padding: 16px 18px;
    box-shadow: var(--notif-shadow);
    border-left: 4px solid var(--notif-gold);
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
    animation: notifSlideOut 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
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
    background: rgba(201, 168, 76, 0.1);
}

.notification-toast .toast-content { flex: 1; min-width: 0; }
.notification-toast .toast-content .toast-title {
    font-weight: 700;
    color: var(--notif-dark);
    font-size: 14px;
    margin: 0 0 3px;
}
.notification-toast .toast-content .toast-message {
    font-size: 13px;
    color: var(--notif-gray);
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
    color: var(--notif-dark);
    transform: scale(1.2);
}

.notification-toast.success { border-left-color: #2ecc71; }
.notification-toast.error { border-left-color: #e74c3c; }
.notification-toast.warning { border-left-color: #f39c12; }
.notification-toast.info { border-left-color: #3498db; }
.notification-toast.gold { border-left-color: var(--notif-gold); }

/* ==========================================
   RESPONSIVO
   ========================================== */
@media (max-width: 768px) {
    .escola-footer {
        padding: 12px 16px;
        margin-top: 20px;
    }
    .footer-content {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 8px;
    }
    .footer-left { font-size: 12px; }
    .footer-center { font-size: 11px; }
    
    .notification-toast-container {
        top: 70px;
        right: 10px;
        left: 10px;
        max-width: 100%;
    }
}
</style>

<!-- ============================================
     CONTAINER DE NOTIFICAÇÕES
     ============================================ -->
<div class="notification-toast-container" id="toastContainer"></div>

<!-- ============================================
     SCRIPTS PRINCIPAIS
     ============================================ -->
<script>
// ==========================================
// FUNÇÕES DA SIDEBAR
// ==========================================
function toggleSidebarEscola() {
    const sidebar = document.getElementById('sidebarEscola');
    const overlay = document.getElementById('sidebarOverlayEscola');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('active');
}

function closeSidebarEscola() {
    const sidebar = document.getElementById('sidebarEscola');
    const overlay = document.getElementById('sidebarOverlayEscola');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
}

// ==========================================
// RELÓGIO EM TEMPO REAL
// ==========================================
function updateDateTimeEscola() {
    const now = new Date();
    const el = document.getElementById('currentDateTimeEscola');
    if (el) {
        el.textContent = now.toLocaleDateString('pt-BR', {
            weekday: 'short',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
}

updateDateTimeEscola();
setInterval(updateDateTimeEscola, 1000);

// ==========================================
// FECHAR SIDEBAR (MOBILE)
// ==========================================
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebarEscola');
    const toggle = document.querySelector('.menu-toggle');
    const isMobile = window.innerWidth <= 992;
    if (isMobile && sidebar && toggle) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            closeSidebarEscola();
        }
    }
});

window.addEventListener('resize', function() {
    if (window.innerWidth > 992) closeSidebarEscola();
});

console.log('🎓 Módulo Gestão Escolar carregado com sucesso!');

// ==========================================
// SISTEMA DE NOTIFICAÇÕES EM TEMPO REAL
// ==========================================
class NotificationManager {
    constructor() {
        this.toastContainer = document.getElementById('toastContainer');
        if (window.notificationManager) return;
        window.notificationManager = this;
    }

    showToast(notification) {
        const toast = document.createElement('div');
        toast.className = `notification-toast ${notification.cor || 'gold'}`;
        const time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

        toast.innerHTML = `
            <div class="toast-icon">${notification.icone || '📬'}</div>
            <div class="toast-content">
                <div class="toast-title">${this.escapeHtml(notification.titulo)}</div>
                <div class="toast-message">${this.escapeHtml(notification.mensagem)}</div>
                <span class="toast-time">${time}</span>
            </div>
            <button class="toast-close" onclick="event.stopPropagation(); this.closest('.notification-toast').classList.add('removing'); setTimeout(() => this.closest('.notification-toast').remove(), 400)">
                <i class="fas fa-times"></i>
            </button>
        `;

        if (notification.link) {
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', function(e) {
                if (!e.target.closest('.toast-close')) {
                    window.location.href = notification.link;
                }
            });
        }

        this.toastContainer.appendChild(toast);

        setTimeout(() => {
            if (toast.parentNode) {
                toast.classList.add('removing');
                setTimeout(() => { if (toast.parentNode) toast.remove(); }, 400);
            }
        }, 8000);

        this.playNotificationSound();
    }

    playNotificationSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.2);
        } catch (e) {}
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    notifyNewPayment(data) {
        this.showToast({
            titulo: data.titulo || '💰 Novo Pagamento',
            mensagem: data.mensagem || 'Um novo pagamento foi registrado.',
            icone: data.icone || '💰',
            cor: data.cor || 'success',
            link: data.link || '#'
        });
    }
}

// ==========================================
// INICIALIZAR NOTIFICAÇÕES
// ==========================================
let notificationManager;

document.addEventListener('DOMContentLoaded', function() {
    notificationManager = new NotificationManager();
    window.notificationManager = notificationManager;
    window.notifyNewPayment = (data) => notificationManager.notifyNewPayment(data);
});

function notifyPagamentoCriado(dados) {
    if (window.notificationManager) {
        window.notificationManager.notifyNewPayment({
            titulo: dados.titulo || `💰 ${dados.aluno_nome || 'Novo Pagamento'}`,
            mensagem: dados.mensagem || 'Pagamento registrado com sucesso.',
            icone: dados.icone || '💰',
            cor: dados.cor || 'success',
            link: dados.link || '#'
        });
    }
}

window.notifyPagamentoCriado = notifyPagamentoCriado;
</script>

<!-- ============================================
     NOTIFICAÇÃO PARA DISPOSITIVOS MÓVEIS
     ============================================ -->
<?php 
// Armazenar dados da última notificação na sessão se houver sucesso
if (isset($sucesso) && $sucesso && isset($mostrar_fatura) && $mostrar_fatura) {
    $notificacao_dados = [];
    
    // Pagamento Manual
    if (isset($aluno_nome) && isset($ultimo_pagamento_id)) {
        $notificacao_dados = [
            'aluno_nome' => $aluno_nome,
            'valor' => number_format($total_pago ?? 0, 2, ',', '.'),
            'id' => $ultimo_pagamento_id ?? 0,
            'quantidade' => count($itens_registrados ?? [])
        ];
        $_SESSION['ultima_notificacao'] = $notificacao_dados;
        $_SESSION['ultima_notificacao_mobile'] = $notificacao_dados;
    } 
    // Pagamento Normal
    elseif (isset($aluno) && isset($pagamento_id)) {
        $notificacao_dados = [
            'aluno_nome' => $aluno['nome'] ?? 'Desconhecido',
            'valor' => number_format($total_pago ?? 0, 2, ',', '.'),
            'id' => $pagamento_id ?? 0,
            'quantidade' => count($itens_registrados ?? [])
        ];
        $_SESSION['ultima_notificacao'] = $notificacao_dados;
        $_SESSION['ultima_notificacao_mobile'] = $notificacao_dados;
    }
}
?>

<!-- ============================================
     DISPARAR NOTIFICAÇÃO (WEB)
     ============================================ -->
<?php if (isset($_SESSION['ultima_notificacao']) && isset($sucesso) && $sucesso && isset($mostrar_fatura) && $mostrar_fatura): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dados = <?= json_encode($_SESSION['ultima_notificacao']) ?>;
    
    if (typeof notifyPagamentoCriado === 'function') {
        setTimeout(function() {
            notifyPagamentoCriado({
                titulo: `💰 ${dados.aluno_nome || 'Novo Pagamento'}`,
                mensagem: `Pagamento de ${dados.valor || '0,00'} Kz registrado para ${dados.aluno_nome || 'o aluno'}.${dados.quantidade ? ' (' + dados.quantidade + ' itens)' : ''}`,
                icone: '💰',
                cor: 'success',
                link: `pagamentos/view.php?id=${dados.id}`
            });
        }, 800);
    }
    
    <?php unset($_SESSION['ultima_notificacao']); ?>
});
</script>
<?php endif; ?>

<!-- ============================================
     DISPARAR NOTIFICAÇÃO (MOBILE/MULTI-DISPOSITIVO)
     ============================================ -->
<?php if (isset($_SESSION['ultima_notificacao_mobile'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dados = <?= json_encode($_SESSION['ultima_notificacao_mobile']) ?>;
    
    // Enviar para todos os dispositivos conectados
    if (typeof notificarPagamentoMultiDispositivo === 'function') {
        setTimeout(function() {
            notificarPagamentoMultiDispositivo({
                titulo: `💰 ${dados.aluno_nome || 'Novo Pagamento'}`,
                mensagem: `Pagamento de ${dados.valor || '0,00'} Kz para ${dados.aluno_nome || 'aluno'}`,
                icone: '💰',
                cor: 'success',
                link: `pagamentos/view.php?id=${dados.id}`
            });
        }, 1200);
    }
    
    <?php unset($_SESSION['ultima_notificacao_mobile']); ?>
});
</script>
<?php endif; ?>

</body>
</html>