<?php
// ============================================
// modules/escola/mensagens.php - Sistema de Mensagens
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';
require_once 'includes/mensagens_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

$acao = $_GET['acao'] ?? 'listar';

// ============================================
// ENVIAR MENSAGEM
// ============================================
if ($acao == 'enviar' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $destinatario_id = $_POST['destinatario_id'] ?? null;
    $assunto = trim($_POST['assunto'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    $tipo = $_POST['tipo'] ?? 'publica';
    $respondendo_a = $_POST['respondendo_a'] ?? null;
    
    if (empty($mensagem)) {
        $_SESSION['erro_msg'] = 'A mensagem não pode estar vazia.';
        header('Location: mensagens.php');
        exit;
    }
    
    if ($tipo == 'privada' && empty($destinatario_id)) {
        $_SESSION['erro_msg'] = 'Selecione um destinatário para mensagens privadas.';
        header('Location: mensagens.php?acao=nova');
        exit;
    }
    
    $mensagem_id = enviarMensagem($usuario_id, $destinatario_id, $assunto, $mensagem, $tipo, $respondendo_a);
    
    if ($mensagem_id) {
        if (!empty($_FILES['anexos'])) {
            foreach ($_FILES['anexos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['anexos']['error'][$key] == UPLOAD_ERR_OK) {
                    $arquivo = [
                        'name' => $_FILES['anexos']['name'][$key],
                        'tmp_name' => $tmp_name,
                        'size' => $_FILES['anexos']['size'][$key],
                        'error' => $_FILES['anexos']['error'][$key],
                        'type' => $_FILES['anexos']['type'][$key]
                    ];
                    adicionarAnexo($mensagem_id, $arquivo);
                }
            }
        }
        
        $_SESSION['sucesso_msg'] = 'Mensagem enviada com sucesso!';
    } else {
        $_SESSION['erro_msg'] = 'Erro ao enviar mensagem. Tente novamente.';
    }
    
    header('Location: mensagens.php');
    exit;
}

// ============================================
// MARCAR COMO LIDA
// ============================================
if ($acao == 'ler' && isset($_GET['id'])) {
    $mensagem_id = $_GET['id'];
    marcarComoLida($mensagem_id, $usuario_id);
    header('Location: mensagens.php?acao=ver&id=' . $mensagem_id);
    exit;
}

// ============================================
// VER MENSAGEM ESPECÍFICA
// ============================================
$mensagem_visualizar = null;
if ($acao == 'ver' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u.nome as remetente_nome, u.perfil as remetente_perfil,
               ud.nome as destinatario_nome,
               (SELECT COUNT(*) FROM mensagens_anexos WHERE mensagem_id = m.id) as total_anexos
        FROM mensagens m
        LEFT JOIN usuarios u ON m.remetente_id = u.id
        LEFT JOIN usuarios ud ON m.destinatario_id = ud.id
        WHERE m.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $mensagem_visualizar = $stmt->fetch();
    
    if ($mensagem_visualizar) {
        if ($mensagem_visualizar['destinatario_id'] == $usuario_id) {
            marcarComoLida($mensagem_visualizar['id'], $usuario_id);
        }
    }
}

// ============================================
// DADOS PARA O DASHBOARD
// ============================================
$mensagens_nao_lidas = getTotalMensagensNaoLidas($usuario_id);
$ultimas_mensagens = getUltimasMensagens($usuario_id, 30);
$usuarios = getUsuariosParaMensagem($usuario_id);
$mensagens_publicas = getMensagensPublicas(20);

$exibir_form_nova = ($acao == 'nova');
$exibir_conversa = ($acao == 'conversa' && isset($_GET['com']));
$usuario_conversa = null;
$conversa_mensagens = [];

if ($exibir_conversa) {
    $usuario_conversa = $pdo->prepare("SELECT id, nome, perfil FROM usuarios WHERE id = ?");
    $usuario_conversa->execute([$_GET['com']]);
    $usuario_conversa = $usuario_conversa->fetch();
    if ($usuario_conversa) {
        $conversa_mensagens = getMensagensPorConversa($usuario_id, $usuario_conversa['id']);
    }
}

include 'includes/header_escola.php';
?>

<style>
/* Estilos do sistema de mensagens */
.mensagens-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 0;
}

.mensagens-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 30px;
}

.mensagens-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: #1a2332;
    margin: 0;
}

.mensagens-header .badge-count {
    background: #e74c3c;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: inline-block;
}

.mensagens-header .badge-count.hidden {
    display: none;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-primary {
    background: #c9a84c;
    color: white;
}

.btn-primary:hover {
    background: #b8973a;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
}

.btn-secondary {
    background: #f1f5f9;
    color: #4a5568;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.mensagem-card {
    background: white;
    border-radius: 14px;
    padding: 20px 25px;
    margin-bottom: 15px;
    border: 1px solid #eef2f7;
    transition: all 0.3s;
    text-decoration: none;
    display: block;
    color: inherit;
}

.mensagem-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    transform: translateY(-2px);
}

.mensagem-card.nao-lida {
    border-left: 4px solid #c9a84c;
    background: #fefcf5;
}

.mensagem-card .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}

.mensagem-card .remetente {
    font-weight: 600;
    color: #1a2332;
    font-size: 16px;
}

.mensagem-card .remetente .perfil-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
    background: #e8f0fe;
    color: #1a56db;
}

.mensagem-card .data {
    color: #94a3b8;
    font-size: 13px;
}

.mensagem-card .assunto {
    font-weight: 600;
    color: #1a2332;
    font-size: 17px;
    margin: 8px 0;
}

.mensagem-card .mensagem-preview {
    color: #4a5568;
    font-size: 15px;
    line-height: 1.5;
}

.mensagem-card .footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
}

.mensagem-card .anexos-info {
    color: #94a3b8;
    font-size: 13px;
}

.form-mensagem {
    background: white;
    border-radius: 14px;
    padding: 25px;
    border: 1px solid #eef2f7;
    margin-bottom: 30px;
}

.form-mensagem .form-group {
    margin-bottom: 18px;
}

.form-mensagem label {
    display: block;
    font-weight: 600;
    color: #1a2332;
    margin-bottom: 6px;
    font-size: 14px;
}

.form-mensagem .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-mensagem .form-control:focus {
    outline: none;
    border-color: #c9a84c;
    box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
}

.form-mensagem textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.file-upload-area {
    border: 2px dashed #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.file-upload-area:hover {
    border-color: #c9a84c;
    background: #fefcf5;
}

.file-upload-area input[type="file"] {
    display: none;
}

.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.tabs .tab {
    padding: 10px 20px;
    background: #f1f5f9;
    border-radius: 10px;
    color: #4a5568;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s;
    position: relative;
}

.tabs .tab:hover {
    background: #e2e8f0;
}

.tabs .tab.active {
    background: #c9a84c;
    color: white;
}

.tabs .tab .badge {
    background: #e74c3c;
    color: white;
    padding: 1px 8px;
    border-radius: 12px;
    font-size: 11px;
    margin-left: 5px;
}

.tabs .tab .badge.hidden {
    display: none;
}

/* Notificação toast */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
}

.toast {
    background: #c9a84c;
    color: white;
    padding: 15px 20px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    margin-bottom: 10px;
    animation: slideIn 0.5s ease;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
}

.toast .toast-icon {
    font-size: 24px;
}

.toast .toast-content {
    flex: 1;
}

.toast .toast-title {
    font-weight: 600;
    margin-bottom: 3px;
}

.toast .toast-message {
    font-size: 14px;
    opacity: 0.9;
}

.toast .toast-close {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    padding: 0 5px;
}

.toast.toast-out {
    animation: slideOut 0.5s ease forwards;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

@media (max-width: 768px) {
    .mensagens-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .mensagem-card .header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .toast {
        max-width: 90%;
    }
}
</style>

<div class="mensagens-container">
    
    <!-- Cabeçalho -->
    <div class="mensagens-header">
        <div>
            <h1>💬 Mensagens</h1>
            <?php if ($mensagens_nao_lidas > 0): ?>
                <span class="badge-count" id="badgeCount"><?= $mensagens_nao_lidas ?> não lidas</span>
            <?php else: ?>
                <span class="badge-count hidden" id="badgeCount">0 não lidas</span>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="mensagens.php" class="btn btn-secondary">
                <span>📥</span> Caixa de Entrada
            </a>
            <a href="mensagens.php?acao=nova" class="btn btn-primary">
                <span>✏️</span> Nova Mensagem
            </a>
            <a href="mensagens.php?acao=publicas" class="btn btn-secondary">
                <span>🌐</span> Públicas
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['sucesso_msg'])): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px;">
            <?= $_SESSION['sucesso_msg'] ?>
        </div>
        <?php unset($_SESSION['sucesso_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['erro_msg'])): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px;">
            <?= $_SESSION['erro_msg'] ?>
        </div>
        <?php unset($_SESSION['erro_msg']); ?>
    <?php endif; ?>

    <!-- Formulário de Nova Mensagem -->
    <?php if ($exibir_form_nova): ?>
    <div class="form-mensagem">
        <h2 style="margin-top: 0; margin-bottom: 20px; color: #1a2332;">✏️ Nova Mensagem</h2>
        <form action="mensagens.php?acao=enviar" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="tipo">Tipo de Mensagem</label>
                <select name="tipo" id="tipo" class="form-control" onchange="toggleDestinatario(this.value)">
                    <option value="publica">🌐 Pública (todos veem)</option>
                    <option value="privada">🔒 Privada (apenas destinatário)</option>
                </select>
            </div>
            
            <div class="form-group" id="destinatario-group">
                <label for="destinatario_id">Destinatário</label>
                <select name="destinatario_id" id="destinatario_id" class="form-control">
                    <option value="">Selecione um usuário</option>
                    <?php foreach($usuarios as $usuario): ?>
                        <option value="<?= $usuario['id'] ?>">
                            <?= htmlspecialchars($usuario['nome']) ?> (<?= $usuario['perfil'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="assunto">Assunto</label>
                <input type="text" name="assunto" id="assunto" class="form-control" placeholder="Digite o assunto da mensagem" required>
            </div>
            
            <div class="form-group">
                <label for="mensagem">Mensagem</label>
                <textarea name="mensagem" id="mensagem" class="form-control" placeholder="Digite sua mensagem..." required></textarea>
            </div>
            
            <div class="form-group">
                <label>Anexar Arquivos</label>
                <div class="file-upload-area" onclick="document.getElementById('arquivos').click()">
                    <div style="font-size: 30px; display: block; margin-bottom: 8px;">📎</div>
                    <p style="margin: 0; color: #4a5568;">Clique para selecionar arquivos</p>
                    <p style="margin: 5px 0 0; color: #94a3b8; font-size: 13px;">Máx: 10MB cada | Formatos: JPG, PNG, PDF, DOC, etc.</p>
                    <input type="file" name="anexos[]" id="arquivos" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar">
                </div>
                <div id="lista-arquivos" style="margin-top: 10px;"></div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">📤 Enviar Mensagem</button>
                <a href="mensagens.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
    
    <script>
    function toggleDestinatario(tipo) {
        const group = document.getElementById('destinatario-group');
        if (tipo === 'publica') {
            group.style.display = 'none';
        } else {
            group.style.display = 'block';
        }
    }
    
    document.getElementById('arquivos').addEventListener('change', function(e) {
        const lista = document.getElementById('lista-arquivos');
        lista.innerHTML = '';
        for (let file of this.files) {
            const div = document.createElement('div');
            div.style.cssText = 'display: inline-flex; align-items: center; gap: 5px; background: #f1f5f9; padding: 4px 12px; border-radius: 6px; margin: 3px; font-size: 13px;';
            div.innerHTML = `📎 ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
            lista.appendChild(div);
        }
    });
    </script>
    <?php endif; ?>

    <!-- Visualizar Conversa -->
    <?php if ($exibir_conversa && $usuario_conversa): ?>
    <div style="background: white; border-radius: 14px; padding: 25px; border: 1px solid #eef2f7; margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">💬 Conversa com <?= htmlspecialchars($usuario_conversa['nome']) ?></h3>
            <a href="mensagens.php" class="btn btn-secondary">← Voltar</a>
        </div>
        
        <div style="max-height: 500px; overflow-y: auto; margin-bottom: 20px;">
            <?php if (empty($conversa_mensagens)): ?>
                <p style="color: #94a3b8; text-align: center; padding: 30px;">Nenhuma mensagem nesta conversa.</p>
            <?php else: ?>
                <?php foreach($conversa_mensagens as $msg): ?>
                    <div style="margin-bottom: 15px; <?= $msg['remetente_id'] == $usuario_id ? 'text-align: right;' : '' ?>">
                        <div style="display: inline-block; max-width: 75%; background: <?= $msg['remetente_id'] == $usuario_id ? '#c9a84c' : '#f1f5f9' ?>; color: <?= $msg['remetente_id'] == $usuario_id ? 'white' : '#1a2332' ?>; padding: 12px 18px; border-radius: 12px; text-align: left;">
                            <div style="font-size: 13px; font-weight: 600; margin-bottom: 4px;">
                                <?= htmlspecialchars($msg['remetente_nome']) ?>
                                <span style="font-weight: normal; opacity: 0.7; margin-left: 8px; font-size: 11px;">
                                    <?= date('d/m/Y H:i', strtotime($msg['data_envio'])) ?>
                                </span>
                            </div>
                            <div style="font-size: 15px; line-height: 1.5;"><?= nl2br(htmlspecialchars($msg['mensagem'])) ?></div>
                            <?php if ($msg['total_anexos'] > 0): ?>
                                <div style="margin-top: 8px; font-size: 13px; opacity: 0.8;">
                                    📎 <?= $msg['total_anexos'] ?> anexo(s)
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <form action="mensagens.php?acao=enviar" method="POST" enctype="multipart/form-data" style="border-top: 1px solid #eef2f7; padding-top: 15px;">
            <input type="hidden" name="tipo" value="privada">
            <input type="hidden" name="destinatario_id" value="<?= $usuario_conversa['id'] ?>">
            <input type="hidden" name="respondendo_a" value="<?= end($conversa_mensagens)['id'] ?? '' ?>">
            <input type="hidden" name="assunto" value="Re: <?= htmlspecialchars(end($conversa_mensagens)['assunto'] ?? 'Conversa') ?>">
            <div style="display: flex; gap: 10px;">
                <textarea name="mensagem" placeholder="Digite sua resposta..." style="flex: 1; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; resize: vertical; min-height: 60px;" required></textarea>
                <button type="submit" class="btn btn-primary">Enviar</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Mensagens Públicas -->
    <?php if ($acao == 'publicas'): ?>
    <div style="margin-bottom: 30px;">
        <h2 style="color: #1a2332; margin-bottom: 20px;">🌐 Mensagens Públicas</h2>
        <?php if (empty($mensagens_publicas)): ?>
            <p style="color: #94a3b8; text-align: center; padding: 30px; background: white; border-radius: 14px; border: 1px solid #eef2f7;">
                Nenhuma mensagem pública ainda.
            </p>
        <?php else: ?>
            <?php foreach($mensagens_publicas as $msg): ?>
            <div class="mensagem-card">
                <div class="header">
                    <div>
                        <span class="remetente">
                            <?= htmlspecialchars($msg['remetente_nome']) ?>
                            <span class="perfil-badge"><?= $msg['remetente_perfil'] ?></span>
                        </span>
                    </div>
                    <span class="data"><?= date('d/m/Y H:i', strtotime($msg['data_envio'])) ?></span>
                </div>
                <div class="assunto"><?= htmlspecialchars($msg['assunto']) ?></div>
                <div class="mensagem-preview"><?= nl2br(htmlspecialchars(substr($msg['mensagem'], 0, 200))) ?></div>
                <div class="footer">
                    <div>
                        <?php if ($msg['total_anexos'] > 0): ?>
                            <span class="anexos-info">📎 <?= $msg['total_anexos'] ?> anexo(s)</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="mensagens.php?acao=ver&id=<?= $msg['id'] ?>" class="btn btn-secondary" style="padding: 6px 14px; font-size: 13px;">Ver</a>
                        <a href="mensagens.php?acao=nova&responder=<?= $msg['id'] ?>" class="btn btn-primary" style="padding: 6px 14px; font-size: 13px;">Responder</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Caixa de Entrada -->
    <?php if (!$exibir_form_nova && !$exibir_conversa && $acao != 'publicas'): ?>
    <div class="tabs">
        <a href="mensagens.php" class="tab active">📥 Todas Mensagens</a>
        <a href="mensagens.php?acao=nao_lidas" class="tab">
            🔴 Não Lidas 
            <span class="badge <?= $mensagens_nao_lidas > 0 ? '' : 'hidden' ?>" id="tabBadge"><?= $mensagens_nao_lidas ?></span>
        </a>
        <a href="mensagens.php?acao=publicas" class="tab">🌐 Públicas</a>
    </div>
    
    <?php if (empty($ultimas_mensagens)): ?>
        <p style="color: #94a3b8; text-align: center; padding: 40px; background: white; border-radius: 14px; border: 1px solid #eef2f7;">
            Nenhuma mensagem encontrada. 
            <a href="mensagens.php?acao=nova" style="color: #c9a84c; font-weight: 600;">Envie sua primeira mensagem!</a>
        </p>
    <?php else: ?>
        <?php foreach($ultimas_mensagens as $msg): ?>
            <?php 
            $is_nao_lida = ($msg['tipo'] == 'privada' && $msg['destinatario_id'] == $usuario_id && $msg['status'] == 'nao_lida');
            $is_publica = ($msg['tipo'] == 'publica');
            ?>
            <a href="mensagens.php?acao=ler&id=<?= $msg['id'] ?>" style="text-decoration: none; display: block;">
                <div class="mensagem-card <?= $is_nao_lida ? 'nao-lida' : '' ?>">
                    <div class="header">
                        <div>
                            <span class="remetente">
                                <?php if ($is_publica): ?>🌐<?php endif; ?>
                                <?= htmlspecialchars($msg['remetente_nome']) ?>
                                <?php if ($msg['remetente_perfil']): ?>
                                    <span class="perfil-badge"><?= $msg['remetente_perfil'] ?></span>
                                <?php endif; ?>
                            </span>
                            <?php if ($is_nao_lida): ?>
                                <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 8px;">NOVA</span>
                            <?php endif; ?>
                        </div>
                        <span class="data"><?= date('d/m/Y H:i', strtotime($msg['data_envio'])) ?></span>
                    </div>
                    <div class="assunto"><?= htmlspecialchars($msg['assunto']) ?></div>
                    <div class="mensagem-preview"><?= nl2br(htmlspecialchars(substr($msg['mensagem'], 0, 150))) ?></div>
                    <div class="footer">
                        <div>
                            <?php if ($msg['total_anexos'] > 0): ?>
                                <span class="anexos-info">📎 <?= $msg['total_anexos'] ?> anexo(s)</span>
                            <?php endif; ?>
                            <?php if ($msg['destinatario_nome'] && $msg['tipo'] == 'privada'): ?>
                                <span class="anexos-info" style="margin-left: 10px;">
                                    👤 Para: <?= htmlspecialchars($msg['destinatario_nome']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 13px; color: #94a3b8;">
                            <?php if ($is_nao_lida): ?>
                                <span style="color: #c9a84c;">Clique para ler ➜</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Visualizar Mensagem Específica -->
    <?php if ($mensagem_visualizar): ?>
    <div style="background: white; border-radius: 14px; padding: 25px; border: 1px solid #eef2f7; margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
            <div>
                <h3 style="margin: 0;">📩 <?= htmlspecialchars($mensagem_visualizar['assunto']) ?></h3>
                <div style="color: #94a3b8; font-size: 14px; margin-top: 5px;">
                    De: <strong><?= htmlspecialchars($mensagem_visualizar['remetente_nome']) ?></strong>
                    <?php if ($mensagem_visualizar['destinatario_nome']): ?>
                        | Para: <strong><?= htmlspecialchars($mensagem_visualizar['destinatario_nome']) ?></strong>
                    <?php endif; ?>
                    | <?= date('d/m/Y H:i', strtotime($mensagem_visualizar['data_envio'])) ?>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="mensagens.php" class="btn btn-secondary">← Voltar</a>
                <?php if ($mensagem_visualizar['remetente_id'] != $usuario_id): ?>
                    <a href="mensagens.php?acao=nova&responder=<?= $mensagem_visualizar['id'] ?>" class="btn btn-primary">Responder</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="font-size: 16px; line-height: 1.6; padding: 15px 0; border-top: 1px solid #eef2f7; border-bottom: 1px solid #eef2f7;">
            <?= nl2br(htmlspecialchars($mensagem_visualizar['mensagem'])) ?>
        </div>
        
        <?php 
        $anexos = getAnexosDaMensagem($mensagem_visualizar['id']);
        if (!empty($anexos)): 
        ?>
        <div style="margin-top: 20px;">
            <h4 style="color: #1a2332; margin-bottom: 10px;">📎 Anexos (<?= count($anexos) ?>)</h4>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <?php foreach($anexos as $anexo): ?>
                    <div style="background: #f8fafc; padding: 10px 15px; border-radius: 8px; border: 1px solid #eef2f7; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">
                            <?php 
                            $ext = pathinfo($anexo['nome_original'], PATHINFO_EXTENSION);
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) echo '🖼️';
                            elseif (in_array($ext, ['pdf'])) echo '📄';
                            elseif (in_array($ext, ['doc', 'docx'])) echo '📝';
                            elseif (in_array($ext, ['xls', 'xlsx'])) echo '📊';
                            else echo '📎';
                            ?>
                        </span>
                        <div>
                            <div style="font-weight: 600; font-size: 14px; color: #1a2332;"><?= htmlspecialchars($anexo['nome_original']) ?></div>
                            <div style="font-size: 12px; color: #94a3b8;"><?= number_format($anexo['tamanho'] / 1024, 1) ?> KB</div>
                        </div>
                        <a href="download_anexo.php?id=<?= $anexo['id'] ?>" class="btn btn-secondary" style="padding: 4px 12px; font-size: 13px;">⬇️ Baixar</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- Toast Container para notificações -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ============================================
// ATUALIZAÇÃO EM TEMPO REAL DAS MENSAGENS
// ============================================

// Variáveis globais
let ultimoIdMensagem = <?= !empty($ultimas_mensagens) ? $ultimas_mensagens[0]['id'] : 0 ?>;
let usuarioId = <?= $usuario_id ?>;

// Função para verificar novas mensagens
function verificarNovasMensagens() {
    fetch('ajax_verificar_mensagens.php?usuario_id=' + usuarioId + '&ultimo_id=' + ultimoIdMensagem)
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                // Atualiza o total de mensagens não lidas
                atualizarContador(data.total_nao_lidas);
                
                // Se houver novas mensagens, mostra notificações
                if (data.novas_mensagens && data.novas_mensagens.length > 0) {
                    // Atualiza o último ID
                    ultimoIdMensagem = data.ultimo_id || ultimoIdMensagem;
                    
                    // Mostra notificações
                    data.novas_mensagens.forEach(msg => {
                        mostrarToast(msg);
                    });
                    
                    // Recarrega a lista se estiver na página de entrada
                    if (window.location.pathname.includes('mensagens.php') && 
                        !window.location.search.includes('acao=nova') &&
                        !window.location.search.includes('acao=ver')) {
                        // Recarrega a página após 2 segundos para mostrar as novas mensagens
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    }
                }
            }
        })
        .catch(error => {
            console.log('Erro ao verificar mensagens:', error);
        });
}

// Função para atualizar o contador
function atualizarContador(total) {
    const badgeCount = document.getElementById('badgeCount');
    const tabBadge = document.getElementById('tabBadge');
    
    // Atualiza badge principal
    if (badgeCount) {
        if (total > 0) {
            badgeCount.textContent = total + ' não lidas';
            badgeCount.classList.remove('hidden');
        } else {
            badgeCount.classList.add('hidden');
        }
    }
    
    // Atualiza badge da tab
    if (tabBadge) {
        if (total > 0) {
            tabBadge.textContent = total;
            tabBadge.classList.remove('hidden');
        } else {
            tabBadge.classList.add('hidden');
        }
    }
    
    // Atualiza o título da página
    if (total > 0) {
        document.title = '(' + total + ') ' + document.title.replace(/^\(\d+\)\s/, '');
    } else {
        document.title = document.title.replace(/^\(\d+\)\s/, '');
    }
}

// Função para mostrar notificação toast
function mostrarToast(mensagem) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    // Verifica se já existe notificação para esta mensagem
    const existingToast = document.querySelector(`.toast[data-msg-id="${mensagem.id}"]`);
    if (existingToast) return;
    
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.dataset.msgId = mensagem.id;
    
    // Ícone baseado no tipo
    const icon = mensagem.tipo === 'privada' ? '🔒' : '🌐';
    
    toast.innerHTML = `
        <span class="toast-icon">📩</span>
        <div class="toast-content">
            <div class="toast-title">${icon} Nova mensagem de ${mensagem.remetente_nome}</div>
            <div class="toast-message">${mensagem.assunto}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
    `;
    
    // Clicar na notificação leva para a mensagem
    toast.addEventListener('click', function(e) {
        if (!e.target.closest('.toast-close')) {
            window.location.href = `mensagens.php?acao=ler&id=${mensagem.id}`;
        }
    });
    
    container.appendChild(toast);
    
    // Remove a notificação após 10 segundos
    setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.add('toast-out');
            setTimeout(() => toast.remove(), 500);
        }
    }, 10000);
}

// Função para marcar mensagem como lida via AJAX
function marcarComoLida(mensagemId) {
    fetch('ajax_marcar_lida.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mensagem_id=' + mensagemId
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            // Atualiza o contador
            verificarNovasMensagens();
        }
    })
    .catch(error => {
        console.log('Erro ao marcar como lida:', error);
    });
}

// Verifica novas mensagens a cada 10 segundos
setInterval(verificarNovasMensagens, 10000);

// Verifica imediatamente ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    // Verifica se já tem mensagens não lidas e atualiza o contador
    verificarNovasMensagens();
});

// Verifica quando a aba recebe foco novamente
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        verificarNovasMensagens();
    }
});

// Se estiver visualizando uma mensagem, marca como lida
<?php if ($acao == 'ver' && isset($_GET['id'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    marcarComoLida(<?= $_GET['id'] ?>);
});
<?php endif; ?>

</script>

<?php include 'includes/footer_escola.php'; ?>