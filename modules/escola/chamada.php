<?php
// ============================================
// modules/escola/chamada.php - Chamada com Voz e Chat
// ============================================

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /softgest_web/login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// ============================================
// FUNÇÕES
// ============================================

function buscarUsuarios($pdo, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nome, u.email, u.perfil,
                   (SELECT COUNT(*) FROM chamadas_internas 
                    WHERE (usuario_origem = u.id OR usuario_destino = u.id) 
                    AND status = 'em_andamento') as em_chamada
            FROM usuarios u
            WHERE u.id != ? AND u.status = 'ativo'
            ORDER BY u.nome
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function buscarChamadasAtivas($pdo, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   u1.nome as origem_nome, u1.perfil as origem_perfil,
                   u2.nome as destino_nome, u2.perfil as destino_perfil
            FROM chamadas_internas c
            LEFT JOIN usuarios u1 ON c.usuario_origem = u1.id
            LEFT JOIN usuarios u2 ON c.usuario_destino = u2.id
            WHERE (c.usuario_origem = ? OR c.usuario_destino = ?)
            AND c.status IN ('pendente', 'em_andamento')
            ORDER BY c.data_chamada DESC
        ");
        $stmt->execute([$usuario_id, $usuario_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function iniciarChamada($pdo, $usuario_origem, $usuario_destino, $tipo = 'voz') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chamadas_internas 
            (usuario_origem, usuario_destino, tipo, data_chamada, origem_ip)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$usuario_origem, $usuario_destino, $tipo, $_SERVER['REMOTE_ADDR'] ?? null]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Erro iniciarChamada: " . $e->getMessage());
        return false;
    }
}

function responderChamada($pdo, $chamada_id, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE chamadas_internas 
            SET status = 'em_andamento', data_resposta = NOW()
            WHERE id = ? AND usuario_destino = ?
        ");
        $stmt->execute([$chamada_id, $usuario_id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Erro responderChamada: " . $e->getMessage());
        return false;
    }
}

function encerrarChamada($pdo, $chamada_id, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE chamadas_internas 
            SET status = 'finalizada'
            WHERE id = ? AND (usuario_origem = ? OR usuario_destino = ?)
        ");
        $stmt->execute([$chamada_id, $usuario_id, $usuario_id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Erro encerrarChamada: " . $e->getMessage());
        return false;
    }
}

function recusarChamada($pdo, $chamada_id, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE chamadas_internas 
            SET status = 'recusada'
            WHERE id = ? AND usuario_destino = ?
            AND status = 'pendente'
        ");
        $stmt->execute([$chamada_id, $usuario_id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Erro recusarChamada: " . $e->getMessage());
        return false;
    }
}

function encerrarTodasChamadas($pdo, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE chamadas_internas 
            SET status = 'finalizada'
            WHERE (usuario_origem = ? OR usuario_destino = ?)
            AND status IN ('pendente', 'em_andamento')
        ");
        $stmt->execute([$usuario_id, $usuario_id]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Erro encerrarTodasChamadas: " . $e->getMessage());
        return 0;
    }
}

function buscarMensagens($pdo, $chamada_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, u.nome
            FROM chamadas_mensagens m
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.chamada_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$chamada_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function enviarMensagemChat($pdo, $chamada_id, $usuario_id, $mensagem) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chamadas_mensagens (chamada_id, usuario_id, mensagem)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$chamada_id, $usuario_id, $mensagem]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Erro enviarMensagemChat: " . $e->getMessage());
        return false;
    }
}

// ============================================
// PROCESSAR AÇÕES
// ============================================

$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

// Iniciar chamada
if ($acao == 'iniciar' && isset($_GET['destino'])) {
    $destino = intval($_GET['destino']);
    $tipo = $_GET['tipo'] ?? 'voz';
    $chamada_id = iniciarChamada($pdo, $usuario_id, $destino, $tipo);
    if ($chamada_id) {
        header('Location: /softgest_web/modules/escola/chamada.php?chamada=' . $chamada_id);
    } else {
        header('Location: /softgest_web/modules/escola/chamada.php?erro=1');
    }
    exit;
}

// Responder chamada
if ($acao == 'responder' && isset($_GET['chamada_id'])) {
    $chamada_id = intval($_GET['chamada_id']);
    responderChamada($pdo, $chamada_id, $usuario_id);
    header('Location: /softgest_web/modules/escola/chamada.php?chamada=' . $chamada_id);
    exit;
}

// Recusar chamada
if ($acao == 'recusar' && isset($_GET['chamada_id'])) {
    $chamada_id = intval($_GET['chamada_id']);
    recusarChamada($pdo, $chamada_id, $usuario_id);
    header('Location: /softgest_web/modules/escola/chamada.php');
    exit;
}

// Encerrar chamada
if ($acao == 'encerrar' && isset($_GET['chamada_id'])) {
    $chamada_id = intval($_GET['chamada_id']);
    encerrarChamada($pdo, $chamada_id, $usuario_id);
    header('Location: /softgest_web/modules/escola/chamada.php');
    exit;
}

// Encerrar todas as chamadas
if ($acao == 'encerrar_todas') {
    $total = encerrarTodasChamadas($pdo, $usuario_id);
    header('Location: /softgest_web/modules/escola/chamada.php?msg=' . urlencode("$total chamada(s) encerrada(s)"));
    exit;
}

// AJAX - Enviar mensagem
if ($acao == 'enviar_mensagem' && isset($_POST['chamada_id'], $_POST['mensagem'])) {
    header('Content-Type: application/json');
    $chamada_id = intval($_POST['chamada_id']);
    $mensagem = trim($_POST['mensagem']);
    
    if (!empty($mensagem)) {
        $id = enviarMensagemChat($pdo, $chamada_id, $usuario_id, $mensagem);
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Mensagem vazia']);
    }
    exit;
}

// ============================================
// CARREGAR DADOS
// ============================================

$usuarios = buscarUsuarios($pdo, $usuario_id);
$chamadas_ativas = buscarChamadasAtivas($pdo, $usuario_id);

$chamada_atual = null;
if (isset($_GET['chamada'])) {
    $chamada_id = intval($_GET['chamada']);
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u1.nome as origem_nome, u1.perfil as origem_perfil,
               u2.nome as destino_nome, u2.perfil as destino_perfil
        FROM chamadas_internas c
        LEFT JOIN usuarios u1 ON c.usuario_origem = u1.id
        LEFT JOIN usuarios u2 ON c.usuario_destino = u2.id
        WHERE c.id = ?
    ");
    $stmt->execute([$chamada_id]);
    $chamada_atual = $stmt->fetch();
}

$erro = isset($_GET['erro']) ? 'Erro ao iniciar chamada' : '';
$mensagem = isset($_GET['msg']) ? $_GET['msg'] : '';
$mensagens_chat = $chamada_atual ? buscarMensagens($pdo, $chamada_atual['id']) : [];

// Contar chamadas ativas para o badge
$total_ativas = 0;
foreach ($chamadas_ativas as $c) {
    if ($c['status'] == 'pendente' || $c['status'] == 'em_andamento') {
        $total_ativas++;
    }
}

include $base_path . '/modules/escola/includes/header_escola.php';
?>

<style>
/* ===== ESTILOS COMPLETOS ===== */
.chamada-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.usuarios-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.usuario-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    border: 2px solid #eef2f7;
    transition: all 0.3s;
}

.usuario-card:hover {
    border-color: #c9a84c;
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.usuario-card .avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 24px;
    font-weight: 700;
    color: white;
}

.usuario-card .nome {
    font-weight: 600;
    font-size: 16px;
    color: #1a2332;
}

.usuario-card .perfil {
    font-size: 12px;
    color: #94a3b8;
}

.usuario-card .status {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 8px;
}

.usuario-card .status.online {
    background: #d1fae5;
    color: #065f46;
}

.usuario-card .status.chamada {
    background: #fef3c7;
    color: #78350f;
}

.usuario-card .btn-chamar {
    margin-top: 12px;
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    background: #c9a84c;
    color: #1a2332;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 13px;
}

.usuario-card .btn-chamar:hover {
    background: #b8973a;
    transform: scale(1.05);
}

.usuario-card .btn-chamar:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.usuario-card .btn-chamar-voz {
    background: #2ecc71;
    color: white;
}

.usuario-card .btn-chamar-voz:hover {
    background: #27ae60;
}

.usuario-card .btn-chamar-chat {
    background: #3498db;
    color: white;
}

.usuario-card .btn-chamar-chat:hover {
    background: #2980b9;
}

/* ===== JANELA DE CHAMADA ===== */
.chamada-window {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    margin-top: 20px;
}

.chamada-header {
    background: #1a2332;
    color: white;
    padding: 15px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.chamada-header .info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.chamada-header .info .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
}

.chamada-header .acoes {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.chamada-header .acoes a,
.chamada-header .acoes button {
    padding: 8px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}

.chamada-header .acoes .btn-responder {
    background: #2ecc71;
    color: white;
    animation: pulse 1.5s infinite;
}

.chamada-header .acoes .btn-responder:hover {
    background: #27ae60;
}

.chamada-header .acoes .btn-recusar {
    background: #e74c3c;
    color: white;
}

.chamada-header .acoes .btn-recusar:hover {
    background: #c0392b;
}

.chamada-header .acoes .btn-encerrar {
    background: #e74c3c;
    color: white;
}

.chamada-header .acoes .btn-encerrar:hover {
    background: #c0392b;
}

.chamada-header .acoes .btn-voltar {
    background: #94a3b8;
    color: white;
}

.chamada-header .acoes .btn-voltar:hover {
    background: #7f8c8d;
}

.chamada-header .acoes .btn-microfone {
    background: #f39c12;
    color: white;
}

.chamada-header .acoes .btn-microfone.ativo {
    background: #2ecc71;
}

.chamada-header .acoes .btn-microfone:hover {
    background: #d68910;
}

/* ===== INDICADOR DE VOZ ===== */
.voz-indicador {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    background: #1a2332;
    color: white;
    font-size: 13px;
    border-top: 1px solid #333;
}

.voz-indicador .onda {
    display: inline-block;
    width: 4px;
    height: 4px;
    background: #2ecc71;
    border-radius: 50%;
    animation: onda 1s ease-in-out infinite;
}

.voz-indicador .onda:nth-child(2) {
    animation-delay: 0.2s;
}

.voz-indicador .onda:nth-child(3) {
    animation-delay: 0.4s;
}

.voz-indicador .onda:nth-child(4) {
    animation-delay: 0.6s;
}

@keyframes onda {
    0%, 100% { height: 4px; }
    50% { height: 20px; }
}

/* ===== CHAMADA PENDENTE ===== */
.chamada-pendente {
    padding: 40px;
    text-align: center;
    background: #f8fafc;
}

.chamada-pendente .icone {
    font-size: 60px;
    margin-bottom: 15px;
}

.chamada-pendente .titulo {
    font-size: 24px;
    font-weight: 700;
    color: #1a2332;
}

.chamada-pendente .subtitulo {
    color: #94a3b8;
    margin: 5px 0 20px;
}

.chamada-pendente .acoes {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.chamada-pendente .acoes a {
    padding: 15px 40px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: 18px;
}

.btn-atender-grande {
    background: #2ecc71;
    color: white;
    animation: pulse 1.5s infinite;
}

.btn-recusar-grande {
    background: #e74c3c;
    color: white;
}

/* ===== CHAT ===== */
.chamada-chat {
    padding: 20px;
    height: 350px;
    overflow-y: auto;
    background: #f8fafc;
}

.chamada-input {
    padding: 15px 20px;
    background: white;
    border-top: 1px solid #eef2f7;
    display: flex;
    gap: 10px;
}

.chamada-input input {
    flex: 1;
    padding: 10px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
}

.chamada-input input:focus {
    outline: none;
    border-color: #c9a84c;
}

.chamada-input button {
    padding: 10px 25px;
    background: #c9a84c;
    color: #1a2332;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.chamada-input button:hover {
    background: #b8973a;
}

.mensagem-chat {
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
}

.mensagem-chat.enviada {
    align-items: flex-end;
}

.mensagem-chat.recebida {
    align-items: flex-start;
}

.mensagem-chat .balao {
    max-width: 70%;
    padding: 10px 16px;
    border-radius: 12px;
    word-wrap: break-word;
    font-size: 14px;
}

.mensagem-chat.enviada .balao {
    background: #c9a84c;
    color: #1a2332;
    border-bottom-right-radius: 4px;
}

.mensagem-chat.recebida .balao {
    background: white;
    color: #1a2332;
    border: 1px solid #eef2f7;
    border-bottom-left-radius: 4px;
}

.mensagem-chat .info-msg {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 4px;
}

/* ===== BOTÃO FLUTUANTE ENCERRAR TODAS ===== */
.btn-flutuante-encerrar {
    position: fixed;
    bottom: 100px;
    right: 30px;
    z-index: 999;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: #e74c3c;
    color: white;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 700;
    box-shadow: 0 4px 20px rgba(231, 76, 60, 0.4);
    transition: all 0.3s;
    animation: pulse 2s infinite;
}

.btn-flutuante-encerrar:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 30px rgba(231, 76, 60, 0.6);
}

.btn-flutuante-encerrar .badge-enc {
    background: white;
    color: #e74c3c;
    border-radius: 50%;
    padding: 0 8px;
    font-size: 14px;
    font-weight: 700;
}

/* ===== ANIMAÇÕES ===== */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.badge-chamada {
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: bold;
    animation: pulse 1.5s infinite;
}

/* ===== NOTIFICAÇÃO ===== */
#notificacaoChamada {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    background: white;
    border-radius: 16px;
    padding: 25px 30px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    border-left: 5px solid #2ecc71;
    max-width: 400px;
    animation: slideInRight 0.5s ease;
    font-family: Arial, sans-serif;
    display: none;
}

/* ===== MICROFONE - POPUP ===== */
#microfoneStatus {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 99999;
    background: white;
    border-radius: 16px;
    padding: 35px;
    box-shadow: 0 10px 60px rgba(0,0,0,0.4);
    max-width: 420px;
    text-align: center;
    border: 3px solid #c9a84c;
    font-family: Arial, sans-serif;
    animation: slideDown 0.3s ease;
}

#microfoneStatus button:hover {
    transform: scale(1.05);
}
#microfoneStatus button:active {
    transform: scale(0.95);
}

/* ===== INDICADOR MICROFONE ===== */
#indicadorMicrofone {
    display: none;
    position: fixed;
    bottom: 25px;
    left: 25px;
    z-index: 999;
    background: #1a2332;
    color: white;
    padding: 12px 22px;
    border-radius: 30px;
    font-size: 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    font-family: Arial, sans-serif;
    border-left: 4px solid #2ecc71;
    animation: slideDown 0.5s ease;
}

@keyframes slideDown {
    from { transform: translateY(-30px) translateX(-50%); opacity: 0; }
    to { transform: translateY(0) translateX(-50%); opacity: 1; }
}

/* ===== RESPONSIVO ===== */
@media (max-width: 768px) {
    .chamada-header {
        flex-direction: column;
        text-align: center;
    }
    .chamada-header .info {
        flex-direction: column;
    }
    .usuarios-grid {
        grid-template-columns: 1fr 1fr;
    }
    .chamada-pendente .acoes {
        flex-direction: column;
        align-items: center;
    }
    .chamada-chat {
        height: 200px;
    }
    .btn-flutuante-encerrar {
        bottom: 80px;
        right: 15px;
        padding: 10px 16px;
        font-size: 13px;
    }
    #microfoneStatus {
        max-width: 90%;
        padding: 25px;
    }
    #indicadorMicrofone {
        bottom: 15px;
        left: 15px;
        padding: 8px 15px;
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .usuarios-grid {
        grid-template-columns: 1fr;
    }
    .chamada-header .acoes {
        justify-content: center;
    }
    #notificacaoChamada {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
        padding: 20px;
    }
}
</style>

<div class="chamada-container">

    <!-- Cabeçalho com botões -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1a2332; margin: 0;">📞 Chamada com Voz</h1>
            <p style="color: #94a3b8; margin: 4px 0 0;">Comunique-se por voz e chat com outros usuários</p>
        </div>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                <input type="checkbox" id="toggleSom" checked> 🔔 Som
            </label>
            
            <!-- Botão Encerrar Todas no cabeçalho -->
            <?php if ($total_ativas > 0): ?>
            <a href="?acao=encerrar_todas" 
               onclick="return confirm('⚠️ Tem certeza que deseja encerrar TODAS as chamadas ativas?')" 
               style="padding: 8px 20px; background: #e74c3c; color: white; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 6px;">
                🛑 Encerrar Todas
                <span style="background: white; color: #e74c3c; border-radius: 50%; padding: 0 8px; font-size: 12px; font-weight: 700;">
                    <?= $total_ativas ?>
                </span>
            </a>
            <?php endif; ?>
            
            <a href="/softgest_web/modules/escola/index.php" style="padding: 8px 20px; background: #f1f5f9; color: #4a5568; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px;">
                ← Voltar
            </a>
        </div>
    </div>

    <!-- Mensagem -->
    <?php if ($mensagem): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #a7f3d0;">
        ✅ <?= htmlspecialchars($mensagem) ?>
    </div>
    <?php endif; ?>

    <?php if ($erro): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
        ❌ <?= $erro ?>
    </div>
    <?php endif; ?>

    <!-- JANELA DE CHAMADA ATIVA -->
    <?php if ($chamada_atual): ?>
    <div class="chamada-window">
        <div class="chamada-header">
            <div class="info">
                <div class="avatar" style="background: <?= ($chamada_atual['usuario_origem'] == $usuario_id) ? '#2ecc71' : '#3498db' ?>;">
                    <?= strtoupper(substr($chamada_atual['destino_nome'] ?? $chamada_atual['origem_nome'], 0, 2)) ?>
                </div>
                <div>
                    <div style="font-size: 18px; font-weight: 600;">
                        <?php if ($chamada_atual['usuario_origem'] == $usuario_id): ?>
                            Chamando: <?= htmlspecialchars($chamada_atual['destino_nome']) ?>
                        <?php else: ?>
                            📞 Chamada de: <?= htmlspecialchars($chamada_atual['origem_nome']) ?>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 12px; color: #94a3b8;">
                        Tipo: <?= ucfirst($chamada_atual['tipo'] ?? 'voz') ?> 
                        | Status: <span id="statusChamada" style="font-weight: 600; color: <?= $chamada_atual['status'] == 'em_andamento' ? '#2ecc71' : '#f39c12' ?>;">
                            <?= ucfirst(str_replace('_', ' ', $chamada_atual['status'])) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="acoes">
                <?php if ($chamada_atual['status'] == 'pendente' && $chamada_atual['usuario_destino'] == $usuario_id): ?>
                    <a href="?acao=responder&chamada_id=<?= $chamada_atual['id'] ?>" class="btn-responder">
                        📞 ATENDER
                    </a>
                    <a href="?acao=recusar&chamada_id=<?= $chamada_atual['id'] ?>" class="btn-recusar">
                        ❌ RECUSAR
                    </a>
                <?php endif; ?>
                
                <?php if ($chamada_atual['status'] == 'em_andamento'): ?>
                    <button class="btn-microfone ativo" onclick="toggleMicrofone()" id="btnMicrofone">
                        🎤 Microfone
                    </button>
                <?php endif; ?>
                
                <?php if ($chamada_atual['status'] == 'em_andamento' || $chamada_atual['status'] == 'pendente'): ?>
                    <a href="?acao=encerrar&chamada_id=<?= $chamada_atual['id'] ?>" class="btn-encerrar" onclick="return confirm('Encerrar esta chamada?')">
                        📴 Encerrar
                    </a>
                <?php endif; ?>
                
                <a href="/softgest_web/modules/escola/chamada.php" class="btn-voltar">
                    ✕ Fechar
                </a>
            </div>
        </div>

        <!-- INDICADOR DE VOZ -->
        <?php if ($chamada_atual['status'] == 'em_andamento' && $chamada_atual['tipo'] == 'voz'): ?>
        <div class="voz-indicador" id="vozIndicador">
            <span>🎙️ Voz:</span>
            <span class="onda"></span>
            <span class="onda"></span>
            <span class="onda"></span>
            <span class="onda"></span>
            <span id="statusVoz" style="margin-left: 10px; color: #2ecc71;">Ativo</span>
        </div>
        <?php endif; ?>

        <!-- CHAMADA PENDENTE -->
        <?php if ($chamada_atual['status'] == 'pendente' && $chamada_atual['usuario_destino'] == $usuario_id): ?>
        <div class="chamada-pendente">
            <div class="icone">📞</div>
            <div class="titulo">Chamada Recebida!</div>
            <div class="subtitulo"><?= htmlspecialchars($chamada_atual['origem_nome']) ?> está chamando via <?= ucfirst($chamada_atual['tipo'] ?? 'voz') ?>...</div>
            <div class="acoes">
                <a href="?acao=responder&chamada_id=<?= $chamada_atual['id'] ?>" class="btn-atender-grande">
                    ✅ ATENDER
                </a>
                <a href="?acao=recusar&chamada_id=<?= $chamada_atual['id'] ?>" class="btn-recusar-grande">
                    ❌ RECUSAR
                </a>
            </div>
            <div style="margin-top: 15px; color: #94a3b8; font-size: 14px;">
                ⏳ Aguardando resposta...
            </div>
        </div>
        <?php endif; ?>

        <!-- CHAT -->
        <?php if ($chamada_atual['status'] == 'em_andamento'): ?>
        <div class="chamada-chat" id="chatContainer">
            <div id="mensagensChat">
                <?php if (empty($mensagens_chat)): ?>
                <div style="text-align: center; color: #94a3b8; padding: 20px;">
                    💬 Digite sua primeira mensagem...
                </div>
                <?php else: ?>
                <?php foreach($mensagens_chat as $msg): ?>
                <div class="mensagem-chat <?= $msg['usuario_id'] == $usuario_id ? 'enviada' : 'recebida' ?>" data-msg-id="<?= $msg['id'] ?>">
                    <div class="balao"><?= htmlspecialchars($msg['mensagem']) ?></div>
                    <div class="info-msg"><?= htmlspecialchars($msg['nome']) ?> • <?= date('H:i:s', strtotime($msg['created_at'])) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="chamada-input">
            <input type="text" id="msgInput" placeholder="Digite sua mensagem..." onkeypress="if(event.key==='Enter') enviarMensagem()">
            <button onclick="enviarMensagem()">📨 Enviar</button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- LISTA DE USUÁRIOS -->
    <h2 style="color: #1a2332; font-size: 20px; margin: 30px 0 10px;">👥 Usuários Online</h2>
    <div class="usuarios-grid">
        <?php if (empty($usuarios)): ?>
            <p style="color: #94a3b8; grid-column: 1 / -1; text-align: center; padding: 40px;">
                Nenhum usuário online no momento.
            </p>
        <?php else: ?>
            <?php foreach($usuarios as $usuario): ?>
            <div class="usuario-card">
                <div class="avatar" style="background: <?= $usuario['em_chamada'] ? '#f39c12' : '#3498db' ?>;">
                    <?= strtoupper(substr($usuario['nome'], 0, 2)) ?>
                </div>
                <div class="nome"><?= htmlspecialchars($usuario['nome']) ?></div>
                <div class="perfil"><?= ucfirst($usuario['perfil'] ?? 'Usuário') ?></div>
                <div class="status <?= $usuario['em_chamada'] ? 'chamada' : 'online' ?>">
                    <?= $usuario['em_chamada'] ? '📞 Em chamada' : '🟢 Online' ?>
                </div>
                <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                    <button class="btn-chamar btn-chamar-voz" 
                            onclick="window.location.href='?acao=iniciar&destino=<?= $usuario['id'] ?>&tipo=voz'" 
                            <?= $usuario['em_chamada'] ? 'disabled' : '' ?>>
                        🎤 Voz
                    </button>
                    <button class="btn-chamar btn-chamar-chat" 
                            onclick="window.location.href='?acao=iniciar&destino=<?= $usuario['id'] ?>&tipo=chat'" 
                            <?= $usuario['em_chamada'] ? 'disabled' : '' ?>>
                        💬 Chat
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- BOTÃO FLUTUANTE ENCERRAR TODAS -->
<?php if ($total_ativas > 0): ?>
<a href="?acao=encerrar_todas" 
   onclick="return confirm('⚠️ Tem certeza que deseja encerrar TODAS as chamadas ativas?')" 
   class="btn-flutuante-encerrar">
    🛑 Encerrar Todas
    <span class="badge-enc"><?= $total_ativas ?></span>
</a>
<?php endif; ?>

<!-- ============================================ -->
<!-- MICROFONE - POPUP DE SOLICITAÇÃO -->
<!-- ============================================ -->
<div id="microfoneStatus">
    <div style="font-size: 56px; margin-bottom: 10px;">🎤</div>
    <h3 style="color: #1a2332; margin: 0 0 10px; font-size: 22px;">Permitir Microfone</h3>
    <p style="color: #555; margin: 0 0 5px; font-size: 15px;">
        Para usar a <strong>chamada de voz</strong>, precisamos acessar seu microfone.
    </p>
    <p style="color: #94a3b8; margin: 0 0 20px; font-size: 13px;">
        🔒 O áudio será transmitido apenas durante a chamada.
    </p>
    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
        <button onclick="solicitarMicrofone()" 
                style="padding: 14px 35px; background: #2ecc71; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s;">
            🎤 Permitir
        </button>
        <button onclick="fecharMicrofoneStatus()" 
                style="padding: 14px 35px; background: #95a5a6; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s;">
            ❌ Não permitir
        </button>
    </div>
    <div style="margin-top: 15px; font-size: 12px; color: #94a3b8; border-top: 1px solid #eef2f7; padding-top: 15px;">
        💡 Se não aparecer a permissão, clique no <strong>cadeado 🔒</strong> na barra de endereços e permita o microfone manualmente.
    </div>
</div>

<!-- INDICADOR DE STATUS DO MICROFONE -->
<div id="indicadorMicrofone">
    <span id="statusMicIcon">🎤</span>
    <span id="statusMicTexto" style="margin-left: 8px;">Microfone: Conectado</span>
    <button onclick="reiniciarMicrofone()" 
            style="margin-left: 12px; padding: 4px 12px; background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 15px; cursor: pointer; font-size: 11px;">
        🔄
    </button>
</div>

<!-- NOTIFICAÇÃO HTML -->
<div id="notificacaoChamada">
    <div style="font-size: 18px; font-weight: 700; color: #1a2332;" id="notifTitulo">📞 Chamada Recebida</div>
    <div style="color: #94a3b8; margin: 5px 0 15px;" id="notifOrigem">Carregando...</div>
    <div style="display: flex; gap: 10px;">
        <button class="btn-atender-notif" style="padding: 10px 25px; background: #2ecc71; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            ✅ Atender
        </button>
        <button class="btn-recusar-notif" style="padding: 10px 25px; background: #e74c3c; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            ❌ Recusar
        </button>
    </div>
</div>

<script>
// ============================================
// VARIÁVEIS GLOBAIS
// ============================================

let ultimoIdChamada = <?= $chamada_atual['id'] ?? 0 ?>;
let ultimoIdMensagem = 0;
let chamadaAtivaId = <?= $chamada_atual['id'] ?? 0 ?>;
let usuarioId = <?= $usuario_id ?>;
let notificacaoExibida = false;

// ===== VARIÁVEIS WEBRTC (VOZ) =====
let peerConnection = null;
let localStream = null;
let remoteStream = null;
let isMicrofoneAtivo = true;
let isCallActive = false;

// ============================================
// FUNÇÕES DO MICROFONE
// ============================================

function fecharMicrofoneStatus() {
    const status = document.getElementById('microfoneStatus');
    if (status) status.style.display = 'none';
}

function mostrarMicrofoneStatus() {
    const status = document.getElementById('microfoneStatus');
    if (status) status.style.display = 'block';
}

function atualizarIndicadorMicrofone(ativo) {
    const indicador = document.getElementById('indicadorMicrofone');
    const icon = document.getElementById('statusMicIcon');
    const texto = document.getElementById('statusMicTexto');
    
    if (!indicador || !icon || !texto) return;
    
    indicador.style.display = 'block';
    
    if (ativo) {
        icon.textContent = '🎤';
        texto.textContent = 'Microfone: Conectado';
        indicador.style.borderLeft = '4px solid #2ecc71';
        texto.style.color = '#2ecc71';
    } else {
        icon.textContent = '🔇';
        texto.textContent = 'Microfone: Desconectado';
        indicador.style.borderLeft = '4px solid #e74c3c';
        texto.style.color = '#e74c3c';
    }
}

function mostrarMensagemSucesso(msg) {
    const div = document.createElement('div');
    div.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 99999;
        background: #d1fae5;
        color: #065f46;
        padding: 15px 30px;
        border-radius: 10px;
        border: 1px solid #a7f3d0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        font-weight: 600;
        animation: slideDown 0.5s ease;
        font-family: Arial, sans-serif;
    `;
    div.textContent = msg;
    document.body.appendChild(div);
    
    setTimeout(() => {
        div.style.opacity = '0';
        div.style.transition = 'opacity 0.5s';
        setTimeout(() => div.remove(), 500);
    }, 4000);
}

function mostrarErroMicrofone(msg) {
    const div = document.createElement('div');
    div.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 99999;
        background: #fee2e2;
        color: #991b1b;
        padding: 15px 30px;
        border-radius: 10px;
        border: 1px solid #fecaca;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        font-weight: 600;
        max-width: 500px;
        text-align: center;
        animation: slideDown 0.5s ease;
        font-family: Arial, sans-serif;
    `;
    div.innerHTML = msg;
    document.body.appendChild(div);
    
    setTimeout(() => {
        div.style.opacity = '0';
        div.style.transition = 'opacity 0.5s';
        setTimeout(() => div.remove(), 500);
    }, 8000);
}

// ============================================
// SOLICITAR PERMISSÃO DO MICROFONE
// ============================================

function solicitarMicrofone() {
    const status = document.getElementById('microfoneStatus');
    if (status) {
        status.innerHTML = `
            <div style="font-size: 48px; margin-bottom: 10px;">⏳</div>
            <h3 style="color: #1a2332; margin: 0 0 10px;">Solicitando permissão...</h3>
            <p style="color: #94a3b8;">Aguardando sua resposta no navegador.</p>
            <div style="margin-top: 15px; color: #94a3b8; font-size: 13px;">
                ⏱️ Aguarde...
            </div>
        `;
    }
    
    navigator.mediaDevices.getUserMedia({ 
        audio: true,
        video: false 
    })
    .then(stream => {
        window.localStream = stream;
        window.microfonePermitido = true;
        isMicrofoneAtivo = true;
        
        atualizarIndicadorMicrofone(true);
        fecharMicrofoneStatus();
        mostrarMensagemSucesso('✅ Microfone permitido! Agora você pode fazer chamadas de voz.');
        
        console.log('✅ Microfone permitido!');
        
        if (chamadaAtivaId > 0) {
            <?php if ($chamada_atual && $chamada_atual['tipo'] == 'voz'): ?>
            setTimeout(function() {
                <?php if ($chamada_atual['usuario_destino'] == $usuario_id): ?>
                atenderVoz(<?= $chamada_atual['id'] ?>);
                <?php else: ?>
                iniciarVoz(<?= $chamada_atual['id'] ?>);
                <?php endif; ?>
            }, 500);
            <?php endif; ?>
        }
    })
    .catch(err => {
        console.error('❌ Erro ao acessar microfone:', err);
        
        let mensagem = '';
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            mensagem = '🔴 <strong>Microfone bloqueado pelo navegador.</strong><br><br>' +
                       '📍 <strong>Como permitir:</strong><br>' +
                       '1️⃣ Clique no <strong>cadeado 🔒</strong> na barra de endereços<br>' +
                       '2️⃣ Vá em <strong>"Permissões do site"</strong><br>' +
                       '3️⃣ Em <strong>"Microfone"</strong>, selecione <strong>"Permitir"</strong><br>' +
                       '4️⃣ Recarregue a página (F5)';
        } else if (err.name === 'NotFoundError') {
            mensagem = '🔴 <strong>Nenhum microfone encontrado.</strong><br><br>' +
                       '📍 Conecte um microfone ao computador e tente novamente.';
        } else {
            mensagem = '🔴 <strong>Erro ao acessar microfone:</strong><br><br>' + err.message;
        }
        
        mostrarErroMicrofone(mensagem);
        window.microfonePermitido = false;
        atualizarIndicadorMicrofone(false);
        
        const statusEl = document.getElementById('microfoneStatus');
        if (statusEl) {
            statusEl.innerHTML = `
                <div style="font-size: 48px; margin-bottom: 10px;">🔴</div>
                <h3 style="color: #e74c3c; margin: 0 0 10px;">Microfone Bloqueado</h3>
                <p style="color: #555; margin: 0 0 15px; font-size: 14px;">
                    ${err.message || 'Permissão negada pelo navegador.'}
                </p>
                <div style="background: #fef9e8; padding: 12px; border-radius: 8px; text-align: left; margin-bottom: 15px; font-size: 13px; border-left: 3px solid #f39c12;">
                    <strong>💡 Como permitir:</strong><br>
                    1️⃣ Clique no <strong>cadeado 🔒</strong> na barra de endereços<br>
                    2️⃣ Vá em <strong>"Permissões do site"</strong><br>
                    3️⃣ Em <strong>"Microfone"</strong>, selecione <strong>"Permitir"</strong>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <button onclick="solicitarMicrofone()" 
                            style="padding: 12px 30px; background: #f39c12; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer;">
                        🔄 Tentar novamente
                    </button>
                    <button onclick="fecharMicrofoneStatus()" 
                            style="padding: 12px 30px; background: #95a5a6; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer;">
                        ✕ Fechar
                    </button>
                </div>
            `;
            statusEl.style.display = 'block';
        }
    });
}

// ============================================
// VERIFICAR PERMISSÃO DO MICROFONE
// ============================================

function verificarPermissaoMicrofone() {
    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(stream => {
            window.localStream = stream;
            window.microfonePermitido = true;
            isMicrofoneAtivo = true;
            atualizarIndicadorMicrofone(true);
            console.log('✅ Microfone já permitido');
            
            <?php if ($chamada_atual && $chamada_atual['status'] == 'em_andamento' && $chamada_atual['tipo'] == 'voz'): ?>
            setTimeout(function() {
                <?php if ($chamada_atual['usuario_destino'] == $usuario_id): ?>
                atenderVoz(<?= $chamada_atual['id'] ?>);
                <?php else: ?>
                iniciarVoz(<?= $chamada_atual['id'] ?>);
                <?php endif; ?>
            }, 500);
            <?php endif; ?>
        })
        .catch(err => {
            console.log('⚠️ Microfone não permitido:', err.name);
            window.microfonePermitido = false;
            atualizarIndicadorMicrofone(false);
            
            <?php if ($chamada_atual && $chamada_atual['tipo'] == 'voz'): ?>
            setTimeout(mostrarMicrofoneStatus, 1000);
            <?php endif; ?>
        });
}

// ============================================
// REINICIAR MICROFONE
// ============================================

function reiniciarMicrofone() {
    if (window.localStream) {
        window.localStream.getTracks().forEach(track => track.stop());
        window.localStream = null;
    }
    window.microfonePermitido = false;
    isMicrofoneAtivo = false;
    atualizarIndicadorMicrofone(false);
    mostrarMicrofoneStatus();
}

// ============================================
// WEBRTC - CHAMADA DE VOZ
// ============================================

async function iniciarVoz(chamadaId) {
    try {
        if (!window.localStream) {
            localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: false
            });
            window.localStream = localStream;
        } else {
            localStream = window.localStream;
        }
        
        peerConnection = new RTCPeerConnection({
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        });
        
        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
        });
        
        peerConnection.ontrack = (event) => {
            remoteStream = event.streams[0];
        };
        
        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);
        await enviarSinalWebRTC(chamadaId, 'offer', JSON.stringify(offer));
        
        isCallActive = true;
        const statusVoz = document.getElementById('statusVoz');
        if (statusVoz) {
            statusVoz.textContent = 'Conectado';
            statusVoz.style.color = '#2ecc71';
        }
        
    } catch (error) {
        console.error('Erro ao iniciar voz:', error);
        alert('Não foi possível acessar o microfone. Verifique as permissões.');
    }
}

async function atenderVoz(chamadaId) {
    try {
        if (!window.localStream) {
            localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: false
            });
            window.localStream = localStream;
        } else {
            localStream = window.localStream;
        }
        
        peerConnection = new RTCPeerConnection({
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        });
        
        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
        });
        
        peerConnection.ontrack = (event) => {
            remoteStream = event.streams[0];
        };
        
        const resposta = await fetch('/softgest_web/modules/escola/chamada_webrtc.php?acao=buscar&chamada_id=' + chamadaId);
        const dados = await resposta.json();
        
        if (dados.success && dados.dados.length > 0) {
            for (const item of dados.dados) {
                if (item.tipo === 'offer') {
                    const offer = JSON.parse(item.dados);
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
                    
                    const answer = await peerConnection.createAnswer();
                    await peerConnection.setLocalDescription(answer);
                    await enviarSinalWebRTC(chamadaId, 'answer', JSON.stringify(answer));
                }
            }
        }
        
        isCallActive = true;
        const statusVoz = document.getElementById('statusVoz');
        if (statusVoz) {
            statusVoz.textContent = 'Conectado';
            statusVoz.style.color = '#2ecc71';
        }
        
    } catch (error) {
        console.error('Erro ao atender voz:', error);
        alert('Não foi possível acessar o microfone. Verifique as permissões.');
    }
}

async function enviarSinalWebRTC(chamadaId, tipo, dados) {
    try {
        const formData = new FormData();
        formData.append('acao', 'salvar');
        formData.append('chamada_id', chamadaId);
        formData.append('tipo', tipo);
        formData.append('dados', dados);
        
        await fetch('/softgest_web/modules/escola/chamada_webrtc.php', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Erro ao enviar sinal:', error);
    }
}

async function buscarSinaisWebRTC(chamadaId, ultimoId = 0) {
    try {
        const resposta = await fetch('/softgest_web/modules/escola/chamada_webrtc.php?acao=buscar&chamada_id=' + chamadaId + '&ultimo_id=' + ultimoId);
        const dados = await resposta.json();
        
        if (dados.success && dados.dados.length > 0 && peerConnection) {
            for (const item of dados.dados) {
                const sinal = JSON.parse(item.dados);
                
                if (item.tipo === 'answer') {
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(sinal));
                } else if (item.tipo === 'candidate') {
                    await peerConnection.addIceCandidate(new RTCIceCandidate(sinal));
                }
            }
        }
    } catch (error) {
        console.error('Erro ao buscar sinais:', error);
    }
}

function toggleMicrofone() {
    if (!localStream) return;
    
    isMicrofoneAtivo = !isMicrofoneAtivo;
    const tracks = localStream.getAudioTracks();
    
    tracks.forEach(track => {
        track.enabled = isMicrofoneAtivo;
    });
    
    const btn = document.getElementById('btnMicrofone');
    const statusVoz = document.getElementById('statusVoz');
    
    if (isMicrofoneAtivo) {
        if (btn) {
            btn.classList.add('ativo');
            btn.textContent = '🎤 Microfone';
        }
        if (statusVoz) {
            statusVoz.textContent = 'Ativo';
            statusVoz.style.color = '#2ecc71';
        }
    } else {
        if (btn) {
            btn.classList.remove('ativo');
            btn.textContent = '🔇 Mudo';
        }
        if (statusVoz) {
            statusVoz.textContent = 'Mudo';
            statusVoz.style.color = '#e74c3c';
        }
    }
}

function encerrarVoz() {
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
        localStream = null;
    }
    if (peerConnection) {
        peerConnection.close();
        peerConnection = null;
    }
    isCallActive = false;
}

// ============================================
// POLLING - VERIFICA NOVAS CHAMADAS
// ============================================

function verificarNovasChamadas() {
    const url = '/softgest_web/modules/escola/chamada_polling.php?ultimo_id=' + ultimoIdChamada + '&chamada_id=' + chamadaAtivaId + '&ultimo_msg=' + ultimoIdMensagem;
    
    fetch(url)
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.log('Erro no polling:', data.error);
            return;
        }
        
        if (data.chamadas && data.chamadas.length > 0) {
            data.chamadas.forEach(chamada => {
                if (chamada.id > ultimoIdChamada) {
                    ultimoIdChamada = chamada.id;
                }
                
                if (chamada.usuario_destino == usuarioId && chamada.status == 'pendente') {
                    mostrarNotificacao(chamada);
                    tocarSom('chamada_entrando');
                    atualizarBadge(data.pendentes || 1);
                }
                
                if (chamada.status == 'em_andamento' && chamada.id == chamadaAtivaId) {
                    const statusEl = document.getElementById('statusChamada');
                    if (statusEl) {
                        statusEl.textContent = 'Em andamento';
                        statusEl.style.color = '#2ecc71';
                    }
                    tocarSom('conectado');
                    
                    if (chamada.tipo == 'voz' && !isCallActive && window.microfonePermitido) {
                        if (chamada.usuario_destino == usuarioId) {
                            atenderVoz(chamada.id);
                        } else if (chamada.usuario_origem == usuarioId) {
                            iniciarVoz(chamada.id);
                        }
                    }
                }
            });
        }
        
        if (data.mensagens && data.mensagens.length > 0) {
            data.mensagens.forEach(msg => {
                adicionarMensagemChat(msg);
                if (msg.id > ultimoIdMensagem) {
                    ultimoIdMensagem = msg.id;
                }
            });
        }
        
        if (chamadaAtivaId > 0 && isCallActive) {
            buscarSinaisWebRTC(chamadaAtivaId);
        }
        
        if (data.pendentes !== undefined) {
            atualizarBadge(data.pendentes);
        }
        
    })
    .catch(error => {
        console.log('Erro no polling:', error);
    });
}

// ============================================
// NOTIFICAÇÃO VISUAL
// ============================================

function mostrarNotificacao(chamada) {
    if (window.location.search.includes('chamada=')) return;
    
    const notif = document.getElementById('notificacaoChamada');
    if (!notif) return;
    
    const tipoTexto = chamada.tipo == 'voz' ? 'Voz' : 'Chat';
    
    document.getElementById('notifTitulo').textContent = '📞 Chamada ' + tipoTexto + ' Recebida';
    document.getElementById('notifOrigem').innerHTML = '<strong>' + chamada.origem_nome + '</strong> (' + chamada.origem_perfil + ') está chamando via ' + tipoTexto + '!';
    document.querySelector('#notificacaoChamada .btn-atender-notif').setAttribute('onclick', 'atenderChamada(' + chamada.id + ')');
    document.querySelector('#notificacaoChamada .btn-recusar-notif').setAttribute('onclick', 'recusarChamada(' + chamada.id + ')');
    
    notif.style.display = 'block';
    tocarSom('chamada_entrando');
    
    if (navigator.vibrate) {
        navigator.vibrate([200, 100, 200, 100, 200]);
    }
}

function atenderChamada(chamadaId) {
    window.location.href = '/softgest_web/modules/escola/chamada.php?acao=responder&chamada_id=' + chamadaId;
}

function recusarChamada(chamadaId) {
    const notif = document.getElementById('notificacaoChamada');
    if (notif) notif.style.display = 'none';
    
    fetch('/softgest_web/modules/escola/chamada.php?acao=recusar&chamada_id=' + chamadaId)
    .catch(error => console.log('Erro ao recusar:', error));
}

// ============================================
// CHAT
// ============================================

function adicionarMensagemChat(msg) {
    const container = document.getElementById('mensagensChat');
    if (!container) return;
    
    const placeholder = container.querySelector('.placeholder-msg');
    if (placeholder) placeholder.remove();
    
    if (container.querySelector(`[data-msg-id="${msg.id}"]`)) return;
    
    const div = document.createElement('div');
    div.className = 'mensagem-chat ' + (msg.usuario_id == usuarioId ? 'enviada' : 'recebida');
    div.dataset.msgId = msg.id;
    div.innerHTML = `
        <div class="balao">${msg.mensagem}</div>
        <div class="info-msg">${msg.nome} • ${new Date(msg.created_at).toLocaleTimeString()}</div>
    `;
    container.appendChild(div);
    
    const chatContainer = document.getElementById('chatContainer');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
}

function enviarMensagem() {
    const input = document.getElementById('msgInput');
    const mensagem = input.value.trim();
    
    if (!mensagem || !chamadaAtivaId) return;
    
    fetch('/softgest_web/modules/escola/chamada.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'acao=enviar_mensagem&chamada_id=' + chamadaAtivaId + '&mensagem=' + encodeURIComponent(mensagem)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
        }
    })
    .catch(error => console.log('Erro ao enviar mensagem:', error));
}

// ============================================
// SOM
// ============================================

function tocarSom(tipo) {
    const ativo = document.getElementById('toggleSom')?.checked;
    if (!ativo) return;
    
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        
        if (tipo === 'chamada_entrando') {
            osc.frequency.value = 440;
            gain.gain.value = 0.3;
            osc.start();
            setTimeout(() => osc.stop(), 600);
            
            setTimeout(() => {
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.frequency.value = 440;
                gain2.gain.value = 0.3;
                osc2.start();
                setTimeout(() => osc2.stop(), 400);
            }, 800);
        } else if (tipo === 'mensagem') {
            osc.frequency.value = 800;
            gain.gain.value = 0.2;
            osc.start();
            setTimeout(() => osc.stop(), 150);
        } else if (tipo === 'conectado') {
            osc.frequency.value = 600;
            gain.gain.value = 0.2;
            osc.start();
            setTimeout(() => {
                osc.frequency.value = 800;
                setTimeout(() => osc.stop(), 200);
            }, 200);
        }
    } catch(e) {
        console.log('Som não disponível');
    }
}

// ============================================
// BADGE DE CHAMADAS
// ============================================

function atualizarBadge(total) {
    const badges = document.querySelectorAll('.badge-chamada, .menu-badge');
    badges.forEach(badge => {
        if (badge) {
            badge.textContent = total;
            badge.style.display = total > 0 ? 'inline' : 'none';
        }
    });
}

// ============================================
// INICIALIZAÇÃO
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    setInterval(verificarNovasChamadas, 3000);
    setTimeout(verificarNovasChamadas, 1000);
    
    const msgInput = document.getElementById('msgInput');
    if (msgInput) {
        msgInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') enviarMensagem();
        });
    }
    
    // Verificar permissão do microfone
    setTimeout(verificarPermissaoMicrofone, 800);
    
    <?php if (isset($_GET['tipo']) && $_GET['tipo'] == 'voz'): ?>
    setTimeout(mostrarMicrofoneStatus, 1500);
    <?php endif; ?>
    
    <?php if ($chamada_atual && $chamada_atual['status'] == 'em_andamento' && $chamada_atual['tipo'] == 'voz'): ?>
    setTimeout(function() {
        <?php if ($chamada_atual['usuario_destino'] == $usuario_id): ?>
        atenderVoz(<?= $chamada_atual['id'] ?>);
        <?php else: ?>
        iniciarVoz(<?= $chamada_atual['id'] ?>);
        <?php endif; ?>
    }, 1000);
    <?php endif; ?>
});

// Limpar recursos ao sair
window.addEventListener('beforeunload', function() {
    encerrarVoz();
});
</script>

<?php include $base_path . '/modules/escola/includes/footer_escola.php'; ?>