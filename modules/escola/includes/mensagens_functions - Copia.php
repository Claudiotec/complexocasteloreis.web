<?php
// ============================================
// FUNÇÕES AUXILIARES PARA MENSAGENS
// ============================================

function getMensagensNaoLidas($usuario_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM mensagens 
            WHERE destinatario_id = ? 
            AND status = 'nao_lida'
            AND tipo = 'privada'
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchColumn() ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getTotalMensagensNaoLidas($usuario_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM mensagens 
            WHERE (destinatario_id = ? OR tipo = 'publica')
            AND status = 'nao_lida'
            AND remetente_id != ?
        ");
        $stmt->execute([$usuario_id, $usuario_id]);
        return $stmt->fetchColumn() ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getUltimasMensagens($usuario_id, $limite = 20) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.nome as remetente_nome,
                u.perfil as remetente_perfil,
                ud.nome as destinatario_nome,
                (SELECT COUNT(*) FROM mensagens_anexos WHERE mensagem_id = m.id) as total_anexos
            FROM mensagens m
            LEFT JOIN usuarios u ON m.remetente_id = u.id
            LEFT JOIN usuarios ud ON m.destinatario_id = ud.id
            WHERE m.tipo = 'publica' 
            OR m.destinatario_id = ? 
            OR m.remetente_id = ?
            ORDER BY m.data_envio DESC
            LIMIT ?
        ");
        $stmt->execute([$usuario_id, $usuario_id, $limite]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getMensagensPorConversa($usuario_id, $outro_usuario_id, $limite = 50) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.nome as remetente_nome,
                u.perfil as remetente_perfil,
                (SELECT COUNT(*) FROM mensagens_anexos WHERE mensagem_id = m.id) as total_anexos
            FROM mensagens m
            LEFT JOIN usuarios u ON m.remetente_id = u.id
            WHERE ((m.remetente_id = ? AND m.destinatario_id = ?)
            OR (m.remetente_id = ? AND m.destinatario_id = ?))
            AND m.tipo = 'privada'
            ORDER BY m.data_envio DESC
            LIMIT ?
        ");
        $stmt->execute([$usuario_id, $outro_usuario_id, $outro_usuario_id, $usuario_id, $limite]);
        return array_reverse($stmt->fetchAll());
    } catch (Exception $e) {
        return [];
    }
}

function getMensagensPublicas($limite = 50) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.nome as remetente_nome,
                u.perfil as remetente_perfil,
                (SELECT COUNT(*) FROM mensagens_anexos WHERE mensagem_id = m.id) as total_anexos
            FROM mensagens m
            LEFT JOIN usuarios u ON m.remetente_id = u.id
            WHERE m.tipo = 'publica'
            ORDER BY m.data_envio DESC
            LIMIT ?
        ");
        $stmt->execute([$limite]);
        return array_reverse($stmt->fetchAll());
    } catch (Exception $e) {
        return [];
    }
}

function marcarComoLida($mensagem_id, $usuario_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE mensagens 
            SET status = 'lida', data_leitura = NOW() 
            WHERE id = ? AND (destinatario_id = ? OR tipo = 'publica')
        ");
        $stmt->execute([$mensagem_id, $usuario_id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function enviarMensagem($remetente_id, $destinatario_id, $assunto, $mensagem, $tipo = 'publica', $respondendo_a = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mensagens (remetente_id, destinatario_id, assunto, mensagem, tipo, respondendo_a)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$remetente_id, $destinatario_id, $assunto, $mensagem, $tipo, $respondendo_a]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        return false;
    }
}

function adicionarAnexo($mensagem_id, $arquivo) {
    global $pdo;
    
    if ($arquivo['error'] != UPLOAD_ERR_OK) {
        return false;
    }
    
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];
    if (!in_array($extensao, $allowed)) {
        return false;
    }
    
    if ($arquivo['size'] > 10 * 1024 * 1024) {
        return false;
    }
    
    $upload_dir = __DIR__ . '/../../../uploads/mensagens/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $nome_arquivo = uniqid() . '.' . $extensao;
    $caminho = $upload_dir . $nome_arquivo;
    
    if (!move_uploaded_file($arquivo['tmp_name'], $caminho)) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mensagens_anexos (mensagem_id, nome_original, nome_arquivo, caminho, tipo_arquivo, tamanho)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $mensagem_id,
            $arquivo['name'],
            $nome_arquivo,
            $caminho,
            $arquivo['type'],
            $arquivo['size']
        ]);
        return true;
    } catch (Exception $e) {
        @unlink($caminho);
        return false;
    }
}

function getAnexosDaMensagem($mensagem_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM mensagens_anexos 
            WHERE mensagem_id = ?
            ORDER BY data_upload DESC
        ");
        $stmt->execute([$mensagem_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getUsuariosParaMensagem($usuario_atual_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, perfil, email 
            FROM usuarios 
            WHERE id != ? 
            AND status = 'ativo'
            ORDER BY nome
        ");
        $stmt->execute([$usuario_atual_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}