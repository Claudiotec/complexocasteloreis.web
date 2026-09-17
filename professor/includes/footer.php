<!-- ===== FOOTER ===== -->
<div class="footer">
    <span class="escola-nome">© <?= date('Y') ?> <?= htmlspecialchars($nome_escola ?? 'Sistema de Gestão Escolar') ?></span>
    <span class="escola-endereco">
        <i class="fas fa-map-marker-alt" style="margin-right: 4px;"></i>
        <?= htmlspecialchars($endereco_escola ?? 'Sistema de Gestão Escolar') ?>
        <?php if (!empty($telefone_escola)): ?>
        | <i class="fas fa-phone" style="margin-right: 4px;"></i> <?= htmlspecialchars($telefone_escola) ?>
        <?php endif; ?>
        <?php if (!empty($email_escola)): ?>
        | <i class="fas fa-envelope" style="margin-right: 4px;"></i> <?= htmlspecialchars($email_escola) ?>
        <?php endif; ?>
    </span>
    <span style="font-size: 11px; color: var(--text-light);">
        💻 Modo Local | mysql | localhost | 🐛 Debug
    </span>
</div>

<!-- ===== TOAST NOTIFICATIONS CONTAINER ===== -->
<div class="toast-container" id="toastContainer"></div>

<!-- ===== SCRIPTS ===== -->
<script>
    // ==========================================
    // SIDEBAR TOGGLE
    // ==========================================
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
    });

    // ==========================================
    // TOAST NOTIFICATIONS
    // ==========================================

    function showToast(icon, title, message, type = 'info', timeout = 5000) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        
        const icons = {
            'success': '✅',
            'danger': '❌',
            'warning': '⚠️',
            'info': 'ℹ️',
            'primary': '📢'
        };
        
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = `
            <div class="toast-icon ${type}">${icon || icons[type] || '📢'}</div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
                <div class="toast-time">${new Date().toLocaleTimeString('pt-BR')}</div>
            </div>
            <button class="toast-close" onclick="closeToast(this)">&times;</button>
        `;
        
        container.appendChild(toast);
        
        // Auto remover após o timeout
        setTimeout(() => {
            closeToast(toast.querySelector('.toast-close'));
        }, timeout);
    }

    function closeToast(button) {
        const toast = button ? button.closest('.toast') : null;
        if (toast) {
            toast.classList.add('hiding');
            setTimeout(() => {
                toast.remove();
            }, 400);
        }
    }

    // ==========================================
    // NOTIFICAÇÕES - FUNÇÕES
    // ==========================================

    function toggleNotifications(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        var dropdown = document.getElementById('notificationsDropdown');
        var toggle = document.querySelector('.notifications-toggle');
        
        if (!dropdown) {
            console.log('Dropdown não encontrado');
            return;
        }
        
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
            dropdown.classList.remove('open');
            if (toggle) toggle.classList.remove('open');
        } else {
            dropdown.style.display = 'block';
            dropdown.classList.add('open');
            if (toggle) toggle.classList.add('open');
        }
    }

    function marcarNotificacaoLida(id) {
        if (!id) return;
        
        // Pegar dados da notificação antes de marcar
        var item = document.querySelector('.notification-item[onclick*="' + id + '"]');
        var title = item ? item.querySelector('.notification-title') : null;
        var message = item ? item.querySelector('.notification-message') : null;
        var icon = item ? item.querySelector('.notification-icon') : null;
        
        fetch('<?= SITE_URL ?>professor/ajax/marcar_notificacao.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(function(response) { 
            return response.json(); 
        })
        .then(function(data) {
            if (data.success) {
                // Mostrar toast de confirmação
                showToast(
                    '✅',
                    'Notificação lida',
                    title ? title.textContent : 'Marcada como lida',
                    'success',
                    3000
                );
                
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        })
        .catch(function(error) {
            console.error('Erro ao marcar notificação:', error);
        });
    }

    function marcarTodasLidas() {
        if (!confirm('Marcar todas as notificações como lidas?')) return;
        
        fetch('<?= SITE_URL ?>professor/ajax/marcar_todas_lidas.php', {
            method: 'POST'
        })
        .then(function(response) { 
            return response.json(); 
        })
        .then(function(data) {
            if (data.success) {
                showToast(
                    '✅',
                    'Todas lidas',
                    'Todas as notificações foram marcadas como lidas',
                    'success',
                    3000
                );
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        })
        .catch(function(error) {
            console.error('Erro ao marcar todas:', error);
        });
    }

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function(event) {
        var dropdown = document.getElementById('notificationsDropdown');
        var toggle = document.querySelector('.notifications-toggle');
        
        if (dropdown && toggle) {
            if (!toggle.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.style.display = 'none';
                dropdown.classList.remove('open');
                toggle.classList.remove('open');
            }
        }
    });

    // ==========================================
    // VERIFICAR NOVAS NOTIFICAÇÕES E MOSTRAR TOAST
    // ==========================================

    <?php if (isset($_SESSION['usuario_perfil']) && ($_SESSION['usuario_perfil'] == 'professor' || $_SESSION['usuario_perfil'] == 'docente')): ?>
    // Verificar novidades a cada 30 segundos
    setInterval(function() {
        fetch('<?= SITE_URL ?>professor/ajax/verificar_novidades.php')
            .then(function(response) { 
                return response.json(); 
            })
            .then(function(data) {
                if (data.novas_notificacoes > 0) {
                    // Atualizar badge
                    var badge = document.getElementById('notificationBadge');
                    if (badge) {
                        badge.textContent = data.novas_notificacoes;
                        badge.style.display = 'inline-block';
                    }
                    
                    // Buscar últimas notificações para mostrar toast
                    fetch('<?= SITE_URL ?>professor/ajax/ultimas_notificacoes.php')
                        .then(function(response) { return response.json(); })
                        .then(function(notificacoes) {
                            if (notificacoes.length > 0) {
                                // Mostrar apenas a mais recente como toast
                                var notif = notificacoes[0];
                                var tipo = notif.tipo || 'info';
                                var icone = notif.icone || '📢';
                                var cor = notif.cor || 'info';
                                
                                showToast(
                                    icone,
                                    notif.titulo,
                                    notif.mensagem.substring(0, 80) + (notif.mensagem.length > 80 ? '...' : ''),
                                    cor,
                                    6000
                                );
                            }
                        })
                        .catch(function(error) {
                            console.error('Erro ao buscar últimas notificações:', error);
                        });
                }
            })
            .catch(function(error) {
                console.error('Erro ao verificar novidades:', error);
            });
    }, 30000);
    <?php endif; ?>

    // ==========================================
    // MOSTRAR TOAST PARA CADA NOTIFICAÇÃO NO DROPDOWN
    // ==========================================

    document.addEventListener('click', function(event) {
        var item = event.target.closest('.notification-item');
        if (item) {
            var title = item.querySelector('.notification-title');
            var message = item.querySelector('.notification-message');
            var icon = item.querySelector('.notification-icon');
            
            if (title && message) {
                var tipo = 'info';
                if (icon) {
                    var iconText = icon.textContent.trim();
                    if (iconText.includes('💰')) tipo = 'success';
                    else if (iconText.includes('❌')) tipo = 'danger';
                    else if (iconText.includes('⚠️')) tipo = 'warning';
                    else if (iconText.includes('📝')) tipo = 'info';
                }
                
                showToast(
                    icon ? icon.textContent.trim() : '📢',
                    title.textContent,
                    message.textContent,
                    tipo,
                    5000
                );
            }
        }
    });

    // ==========================================
    // LOGS DO SISTEMA
    // ==========================================

    console.log('📚 SoftGest carregado com sucesso!');
    <?php if (isset($notificacoes_nao_lidas) && $notificacoes_nao_lidas > 0): ?>
    console.log('🔔 Você tem <?= $notificacoes_nao_lidas ?> notificação(ões) não lida(s)');
    <?php endif; ?>
    console.log('💡 Sistema de notificações em execução...');
</script>
</body>
</html>