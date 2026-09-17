<?php
// ============================================
// modules/escola/download_anexo.php - Download de anexos
// ============================================

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die('ID do anexo não fornecido.');
}

$anexo_id = $_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT a.*, m.remetente_id, m.destinatario_id 
        FROM mensagens_anexos a
        INNER JOIN mensagens m ON a.mensagem_id = m.id
        WHERE a.id = ?
    ");
    $stmt->execute([$anexo_id]);
    $anexo = $stmt->fetch();
    
    if (!$anexo) {
        die('Anexo não encontrado.');
    }
    
    // Verificar permissão (usuário é remetente ou destinatário ou mensagem pública)
    $usuario_id = $_SESSION['usuario_id'];
    if ($anexo['destinatario_id'] && $anexo['destinatario_id'] != $usuario_id && $anexo['remetente_id'] != $usuario_id) {
        // Verificar se é mensagem pública
        $stmt = $pdo->prepare("SELECT tipo FROM mensagens WHERE id = ?");
        $stmt->execute([$anexo['mensagem_id']]);
        $msg = $stmt->fetch();
        if ($msg['tipo'] != 'publica') {
            die('Você não tem permissão para baixar este arquivo.');
        }
    }
    
    // Enviar arquivo
    if (file_exists($anexo['caminho'])) {
        header('Content-Type: ' . $anexo['tipo_arquivo']);
        header('Content-Disposition: attachment; filename="' . $anexo['nome_original'] . '"');
        header('Content-Length: ' . $anexo['tamanho']);
        readfile($anexo['caminho']);
        exit;
    } else {
        die('Arquivo não encontrado no servidor.');
    }
    
} catch (Exception $e) {
    die('Erro ao processar download.');
}