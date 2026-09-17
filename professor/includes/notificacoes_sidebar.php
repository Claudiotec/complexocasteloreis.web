<?php
// includes/notificacoes_sidebar.php
// Adicionar ao sidebar.php após o menu principal

// Buscar notificações do professor
$professor_id = null;
try {
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE email = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$email]);
        $prof = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($prof) $professor_id = $prof['id'];
    }
} catch (Exception $e) {}

$notificacoes_nao_lidas = 0;
$ultimas_notificacoes = [];

if ($professor_id) {
    try {
        // Contar não lidas
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes_professor WHERE professor_id = ? AND lida = 0");
        $stmt->execute([$professor_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $notificacoes_nao_lidas = $result['total'] ?? 0;
        
        // Buscar últimas 5 notificações
        $stmt = $pdo->prepare("
            SELECT * FROM notificacoes_professor 
            WHERE professor_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$professor_id]);
        $ultimas_notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>

<!-- ===== NOTIFICAÇÕES NO MENU ===== -->
<li class="nav-item">
    <a href="#" class="nav-link" data-bs-toggle="collapse" data-bs-target="#notificationsMenu" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <span>Notificações</span>
        <?php if ($notificacoes_nao_lidas > 0): ?>
            <span class="badge-notification"><?= $notificacoes_nao_lidas ?></span>
        <?php endif; ?>
    </a>
    <div class="collapse" id="notificationsMenu">
        <div class="notification-dropdown" style="padding: 10px 15px; max-height: 400px; overflow-y: auto;">
            <?php if (empty($ultimas_notificacoes)): ?>
                <div style="text-align: center; padding: 20px; color: var(--text-secondary);">
                    <i class="fas fa-bell-slash" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                    <span>Nenhuma notificação</span>
                </div>
            <?php else: ?>
                <?php foreach ($ultimas_notificacoes as $notif): ?>
                    <div class="notification-item <?= $notif['lida'] ? '' : 'unread' ?>" 
                         style="padding: 10px; border-bottom: 1px solid var(--border-color); cursor: pointer; <?= $notif['lida'] ? '' : 'background: var(--bg-hover); border-left: 3px solid var(--primary);' ?>"
                         onclick="marcarNotificacaoLida(<?= $notif['id'] ?>)">
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <div style="font-size: 20px;"><?= $notif['icone'] ?? '📢' ?></div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 13px; color: var(--text-primary);">
                                    <?= htmlspecialchars($notif['titulo']) ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                                    <?= htmlspecialchars(substr($notif['mensagem'], 0, 80)) ?><?= strlen($notif['mensagem']) > 80 ? '...' : '' ?>
                                </div>
                                <div style="font-size: 10px; color: var(--text-secondary); margin-top: 4px;">
                                    <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                </div>
                            </div>
                            <?php if (!$notif['lida']): ?>
                                <span style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%; flex-shrink: 0;"></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($notificacoes_nao_lidas > 0): ?>
                    <div style="padding: 10px; text-align: center;">
                        <button onclick="marcarTodasLidas()" style="background: none; border: none; color: var(--primary); cursor: pointer; font-size: 13px; font-weight: 600;">
                            Marcar todas como lidas
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</li>

<style>
.badge-notification {
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    padding: 2px 8px;
    font-size: 11px;
    margin-left: auto;
    font-weight: 700;
    min-width: 20px;
    text-align: center;
}

.notification-item {
    transition: all 0.2s;
}

.notification-item:hover {
    background: var(--bg-hover);
}

.notification-item.unread {
    border-left: 3px solid var(--primary);
}
</style>

<script>
function marcarNotificacaoLida(id) {
    fetch('/softgest_web/professor/ajax/marcar_notificacao.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}

function marcarTodasLidas() {
    if (!confirm('Marcar todas as notificações como lidas?')) return;
    
    fetch('/softgest_web/professor/ajax/marcar_todas_lidas.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>