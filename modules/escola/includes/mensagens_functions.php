<?php
// ============================================
// modules/escola/includes/mensagens_functions.php
// ============================================

/**
 * Obtém as últimas mensagens do usuário
 */
function getUltimasMensagens($usuario_id, $limite = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   u.nome as remetente_nome, 
                   u.perfil as remetente_perfil,
                   ud.nome as destinatario_nome,
                   (SELECT COUNT(*) FROM mensagens_anexos WHERE mensagem_id = m.id) as total_anexos
            FROM mensagens m
            LEFT JOIN usuarios u ON m.remetente_id = u.id
            LEFT JOIN usuarios ud ON m.destinatario_id = ud.id
            WHERE m.remetente_id = ? 
               OR m.destinatario_id = ? 
               OR m.tipo = 'publica'
            ORDER BY m.data_envio DESC
            LIMIT ?
        ");
        $stmt->execute([$usuario_id, $usuario_id, $limite]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log('Erro ao buscar últimas mensagens: ' . $e->getMessage());
        return [];
    }
}

/**
 * Obtém mensagens públicas
 */
function getMensagensPublicas($limite = 20) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, 
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
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log('Erro ao buscar mensagens públicas: ' . $e->getMessage());
        return [];
    }
}

/**
 * Obtém usuários para enviar mensagem (exceto o próprio)
 */
function getUsuariosParaMensagem($usuario_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, perfil 
            FROM usuarios 
            WHERE id != ? 
            ORDER BY nome
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log('Erro ao buscar usuários: ' . $e->getMessage());
        return [];
    }
}

/**
 * Envia uma nova mensagem
 */
function enviarMensagem($remetente_id, $destinatario_id, $assunto, $mensagem, $tipo = 'publica', $respondendo_a = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO mensagens (
                remetente_id, 
                destinatario_id, 
                assunto, 
                mensagem, 
                tipo, 
                respondendo_a, 
                status, 
                data_envio
            ) VALUES (?, ?, ?, ?, ?, ?, 'nao_lida', NOW())
        ");
        
        $stmt->execute([
            $remetente_id, 
            $tipo == 'privada' ? $destinatario_id : null, 
            $assunto, 
            $mensagem, 
            $tipo, 
            $respondendo_a
        ]);
        
        $mensagem_id = $pdo->lastInsertId();
        $pdo->commit();
        
        return $mensagem_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Erro ao enviar mensagem: ' . $e->getMessage());
        return false;
    }
}

/**
 * Marca uma mensagem como lida
 */
function marcarComoLida($mensagem_id, $usuario_id) {
    global $pdo;
    
    try {
        // Verifica se a mensagem existe
        $stmt = $pdo->prepare("
            SELECT id, tipo, remetente_id, destinatario_id 
            FROM mensagens 
            WHERE id = ?
        ");
        $stmt->execute([$mensagem_id]);
        $mensagem = $stmt->fetch();
        
        if (!$mensagem) {
            return false;
        }
        
        // Para mensagens privadas, só o destinatário pode marcar como lida
        if ($mensagem['tipo'] == 'privada' && $mensagem['destinatario_id'] != $usuario_id) {
            return false;
        }
        
        // Para mensagens públicas, qualquer um pode marcar como lida
        // (mas não pode marcar as próprias mensagens como lidas)
        if ($mensagem['tipo'] == 'publica' && $mensagem['remetente_id'] == $usuario_id) {
            return false;
        }
        
        // Atualiza o status
        $stmt = $pdo->prepare("
            UPDATE mensagens 
            SET status = 'lida', 
                data_leitura = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$mensagem_id]);
        
    } catch (Exception $e) {
        error_log('Erro ao marcar mensagem como lida: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtém o total de mensagens não lidas para um usuário
 * Conta tanto mensagens públicas quanto privadas
 */
function getTotalMensagensNaoLidas($usuario_id) {
    global $pdo;
    
    try {
        // Conta mensagens privadas não lidas (destinadas ao usuário)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM mensagens m
            WHERE m.destinatario_id = ? 
            AND m.tipo = 'privada' 
            AND m.status = 'nao_lida'
        ");
        $stmt->execute([$usuario_id]);
        $privadas = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_privadas = $privadas ? (int)$privadas['total'] : 0;
        
        // Conta mensagens públicas não lidas (enviadas por outros usuários)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM mensagens m
            WHERE m.tipo = 'publica' 
            AND m.status = 'nao_lida'
            AND m.remetente_id != ?
        ");
        $stmt->execute([$usuario_id]);
        $publicas = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_publicas = $publicas ? (int)$publicas['total'] : 0;
        
        // Retorna a soma total
        return $total_privadas + $total_publicas;
        
    } catch (Exception $e) {
        error_log('Erro ao contar mensagens não lidas: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Obtém mensagens de uma conversa específica
 */
function getMensagensPorConversa($usuario_id, $outro_usuario_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   u.nome as remetente_nome, 
                   u.perfil as remetente_perfil,
                   (SELECT COUNT(*) FROM mensagens_anexos WHERE mensagem_id = m.id) as total_anexos
            FROM mensagens m
            LEFT JOIN usuarios u ON m.remetente_id = u.id
            WHERE (m.remetente_id = ? AND m.destinatario_id = ?)
               OR (m.remetente_id = ? AND m.destinatario_id = ?)
            ORDER BY m.data_envio ASC
        ");
        $stmt->execute([$usuario_id, $outro_usuario_id, $outro_usuario_id, $usuario_id]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log('Erro ao buscar conversa: ' . $e->getMessage());
        return [];
    }
}

/**
 * Adiciona um anexo à mensagem
 */
function adicionarAnexo($mensagem_id, $arquivo) {
    global $pdo;
    
    try {
        // Define o diretório de upload
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/anexos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Gera nome único para o arquivo
        $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $nome_criptografado = md5(uniqid() . time()) . '.' . $extensao;
        $caminho_completo = $upload_dir . $nome_criptografado;
        
        // Move o arquivo
        if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
            $stmt = $pdo->prepare("
                INSERT INTO mensagens_anexos (mensagem_id, nome_original, nome_criptografado, tamanho, tipo, caminho)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $mensagem_id,
                $arquivo['name'],
                $nome_criptografado,
                $arquivo['size'],
                $arquivo['type'],
                'uploads/anexos/' . $nome_criptografado
            ]);
            
            return $pdo->lastInsertId();
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log('Erro ao adicionar anexo: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtém anexos de uma mensagem
 */
function getAnexosDaMensagem($mensagem_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM mensagens_anexos 
            WHERE mensagem_id = ?
            ORDER BY id
        ");
        $stmt->execute([$mensagem_id]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log('Erro ao buscar anexos: ' . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se uma mensagem existe e o usuário tem acesso
 */
function verificarAcessoMensagem($mensagem_id, $usuario_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM mensagens 
            WHERE id = ? 
            AND (remetente_id = ? OR destinatario_id = ? OR tipo = 'publica')
        ");
        $stmt->execute([$mensagem_id, $usuario_id, $usuario_id]);
        return $stmt->fetch() !== false;
        
    } catch (Exception $e) {
        error_log('Erro ao verificar acesso: ' . $e->getMessage());
        return false;
    }
}
?>