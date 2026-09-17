<?php
// models/Mensagem.php

require_once __DIR__ . '/../config/database.php';

class Mensagem {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * Cria uma nova conversa
     */
    public function criarConversa($usuario_id, $participantes, $titulo = null, $tipo = 'privada') {
        try {
            $this->pdo->beginTransaction();
            
            // Se for privada, verificar se já existe
            if ($tipo == 'privada' && count($participantes) == 1) {
                $stmt = $this->pdo->prepare("
                    SELECT c.id 
                    FROM conversas c
                    INNER JOIN conversa_participantes cp1 ON c.id = cp1.conversa_id
                    INNER JOIN conversa_participantes cp2 ON c.id = cp2.conversa_id
                    WHERE c.tipo = 'privada'
                    AND cp1.usuario_id = ?
                    AND cp2.usuario_id = ?
                    AND c.ativo = 1
                    AND cp1.saiu = 0
                    AND cp2.saiu = 0
                ");
                $stmt->execute([$usuario_id, $participantes[0]]);
                $existente = $stmt->fetch();
                
                if ($existente) {
                    return $existente['id'];
                }
            }
            
            // Criar conversa
            $stmt = $this->pdo->prepare("
                INSERT INTO conversas (tipo, titulo, criador_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$tipo, $titulo, $usuario_id]);
            $conversa_id = $this->pdo->lastInsertId();
            
            // Adicionar participantes (incluindo o criador)
            $participantes[] = $usuario_id;
            $participantes = array_unique($participantes);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO conversa_participantes (conversa_id, usuario_id)
                VALUES (?, ?)
            ");
            
            foreach ($participantes as $participante_id) {
                $stmt->execute([$conversa_id, $participante_id]);
            }
            
            $this->pdo->commit();
            return $conversa_id;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Busca todas as conversas de um usuário
     */
    public function listarConversas($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                (
                    SELECT COUNT(*) 
                    FROM mensagens m 
                    WHERE m.conversa_id = c.id 
                    AND m.remetente_id != ?
                    AND m.lida = 0
                    AND m.excluida_para_todos = 0
                    AND m.excluida_para_mim = 0
                ) as nao_lidas,
                (
                    SELECT conteudo 
                    FROM mensagens 
                    WHERE conversa_id = c.id 
                    AND excluida_para_todos = 0
                    AND excluida_para_mim = 0
                    ORDER BY data_envio DESC 
                    LIMIT 1
                ) as ultima_mensagem,
                (
                    SELECT data_envio 
                    FROM mensagens 
                    WHERE conversa_id = c.id 
                    AND excluida_para_todos = 0
                    AND excluida_para_mim = 0
                    ORDER BY data_envio DESC 
                    LIMIT 1
                ) as ultima_mensagem_data,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(ua.id, '|', ua.nome_completo)
                        SEPARATOR ','
                    )
                    FROM conversa_participantes cp
                    JOIN usuarios_autorizados ua ON cp.usuario_id = ua.id
                    WHERE cp.conversa_id = c.id 
                    AND cp.usuario_id != ?
                    AND cp.saiu = 0
                ) as participantes
            FROM conversas c
            INNER JOIN conversa_participantes cp ON c.id = cp.conversa_id
            WHERE cp.usuario_id = ?
            AND cp.saiu = 0
            AND c.ativo = 1
            ORDER BY c.ultima_atividade DESC
        ");
        $stmt->execute([$usuario_id, $usuario_id, $usuario_id]);
        
        $conversas = [];
        while ($row = $stmt->fetch()) {
            // Processar participantes
            $participantes = [];
            if ($row['participantes']) {
                foreach (explode(',', $row['participantes']) as $p) {
                    list($id, $nome) = explode('|', $p);
                    $participantes[] = ['id' => $id, 'nome' => $nome];
                }
            }
            $row['participantes'] = $participantes;
            
            // Buscar nome dos participantes para título
            if ($row['tipo'] == 'privada' && !$row['titulo']) {
                $nomes = array_column($participantes, 'nome');
                $row['titulo'] = implode(', ', $nomes);
            }
            
            $conversas[] = $row;
        }
        
        return $conversas;
    }
    
    /**
     * Busca mensagens de uma conversa
     */
    public function buscarMensagens($conversa_id, $usuario_id, $limite = 50, $offset = 0) {
        // Marcar mensagens como lidas
        $stmt = $this->pdo->prepare("
            UPDATE mensagens 
            SET lida = 1 
            WHERE conversa_id = ? 
            AND remetente_id != ?
            AND lida = 0
            AND excluida_para_todos = 0
            AND excluida_para_mim = 0
        ");
        $stmt->execute([$conversa_id, $usuario_id]);
        
        // Buscar mensagens
        $stmt = $this->pdo->prepare("
            SELECT 
                m.*,
                ua.nome_completo as remetente_nome,
                ua.funcao as remetente_funcao,
                (
                    SELECT reacao 
                    FROM reacoes_mensagens 
                    WHERE mensagem_id = m.id 
                    AND usuario_id = ?
                ) as minha_reacao,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(rm.reacao, ':', ua2.nome_completo)
                        SEPARATOR '|'
                    )
                    FROM reacoes_mensagens rm
                    JOIN usuarios_autorizados ua2 ON rm.usuario_id = ua2.id
                    WHERE rm.mensagem_id = m.id
                ) as reacoes
            FROM mensagens m
            JOIN usuarios_autorizados ua ON m.remetente_id = ua.id
            WHERE m.conversa_id = ?
            AND m.excluida_para_todos = 0
            AND m.excluida_para_mim = 0
            ORDER BY m.data_envio DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$usuario_id, $conversa_id, $limite, $offset]);
        
        $mensagens = [];
        while ($row = $stmt->fetch()) {
            $mensagens[] = $row;
        }
        
        return array_reverse($mensagens);
    }
    
    /**
     * Envia uma mensagem
     */
    public function enviarMensagem($conversa_id, $remetente_id, $conteudo, $tipo = 'texto', $arquivo = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Verificar se o usuário participa da conversa
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM conversa_participantes 
                WHERE conversa_id = ? AND usuario_id = ? AND saiu = 0
            ");
            $stmt->execute([$conversa_id, $remetente_id]);
            
            if (!$stmt->fetch()) {
                throw new Exception("Usuário não participa desta conversa");
            }
            
            // Inserir mensagem
            if ($arquivo) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO mensagens (conversa_id, remetente_id, conteudo, tipo, arquivo_nome, arquivo_tamanho)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $conversa_id, 
                    $remetente_id, 
                    $conteudo, 
                    $tipo,
                    $arquivo['nome'],
                    $arquivo['tamanho']
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO mensagens (conversa_id, remetente_id, conteudo, tipo)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$conversa_id, $remetente_id, $conteudo, $tipo]);
            }
            
            $mensagem_id = $this->pdo->lastInsertId();
            
            // Atualizar última atividade da conversa
            $stmt = $this->pdo->prepare("
                UPDATE conversas SET ultima_atividade = CURRENT_TIMESTAMP WHERE id = ?
            ");
            $stmt->execute([$conversa_id]);
            
            // Buscar a mensagem completa
            $stmt = $this->pdo->prepare("
                SELECT m.*, ua.nome_completo as remetente_nome
                FROM mensagens m
                JOIN usuarios_autorizados ua ON m.remetente_id = ua.id
                WHERE m.id = ?
            ");
            $stmt->execute([$mensagem_id]);
            $mensagem = $stmt->fetch();
            
            $this->pdo->commit();
            
            // Notificar participantes (async)
            $this->notificarParticipantes($conversa_id, $mensagem_id, $remetente_id);
            
            return $mensagem;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Notifica participantes sobre nova mensagem
     */
    private function notificarParticipantes($conversa_id, $mensagem_id, $remetente_id) {
        try {
            // Buscar participantes
            $stmt = $this->pdo->prepare("
                SELECT usuario_id 
                FROM conversa_participantes 
                WHERE conversa_id = ? 
                AND usuario_id != ?
                AND saiu = 0
                AND notificacoes_ativas = 1
            ");
            $stmt->execute([$conversa_id, $remetente_id]);
            $participantes = $stmt->fetchAll();
            
            // Buscar remetente
            $stmt = $this->pdo->prepare("
                SELECT nome_completo FROM usuarios_autorizados WHERE id = ?
            ");
            $stmt->execute([$remetente_id]);
            $remetente = $stmt->fetch();
            
            // Buscar mensagem
            $stmt = $this->pdo->prepare("
                SELECT conteudo FROM mensagens WHERE id = ?
            ");
            $stmt->execute([$mensagem_id]);
            $mensagem = $stmt->fetch();
            
            // Criar notificações
            $stmt = $this->pdo->prepare("
                INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, dados)
                VALUES (?, 'mensagem', ?, ?, ?)
            ");
            
            foreach ($participantes as $p) {
                $dados = json_encode([
                    'conversa_id' => $conversa_id,
                    'mensagem_id' => $mensagem_id,
                    'remetente_id' => $remetente_id
                ]);
                
                $stmt->execute([
                    $p['usuario_id'],
                    "Nova mensagem de " . $remetente['nome_completo'],
                    substr($mensagem['conteudo'], 0, 100),
                    $dados
                ]);
            }
            
        } catch (Exception $e) {
            // Não falhar se a notificação não funcionar
            error_log("Erro ao notificar: " . $e->getMessage());
        }
    }
    
    /**
     * Remove uma mensagem
     */
    public function removerMensagem($mensagem_id, $usuario_id, $para_todos = false) {
        try {
            $this->pdo->beginTransaction();
            
            // Verificar se é o remetente
            $stmt = $this->pdo->prepare("
                SELECT remetente_id, conversa_id FROM mensagens WHERE id = ?
            ");
            $stmt->execute([$mensagem_id]);
            $mensagem = $stmt->fetch();
            
            if (!$mensagem) {
                throw new Exception("Mensagem não encontrada");
            }
            
            if ($para_todos && $mensagem['remetente_id'] != $usuario_id) {
                throw new Exception("Apenas o remetente pode excluir para todos");
            }
            
            if ($para_todos) {
                $stmt = $this->pdo->prepare("
                    UPDATE mensagens 
                    SET excluida_para_todos = 1, data_exclusao = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$mensagem_id]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE mensagens 
                    SET excluida_para_mim = 1, data_exclusao = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$mensagem_id]);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Edita uma mensagem
     */
    public function editarMensagem($mensagem_id, $usuario_id, $novo_conteudo) {
        try {
            $this->pdo->beginTransaction();
            
            // Verificar se é o remetente
            $stmt = $this->pdo->prepare("
                SELECT remetente_id FROM mensagens WHERE id = ? AND excluida_para_todos = 0
            ");
            $stmt->execute([$mensagem_id]);
            $mensagem = $stmt->fetch();
            
            if (!$mensagem) {
                throw new Exception("Mensagem não encontrada");
            }
            
            if ($mensagem['remetente_id'] != $usuario_id) {
                throw new Exception("Apenas o remetente pode editar a mensagem");
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE mensagens 
                SET conteudo = ?, data_edicao = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$novo_conteudo, $mensagem_id]);
            
            // Buscar mensagem atualizada
            $stmt = $this->pdo->prepare("
                SELECT m.*, ua.nome_completo as remetente_nome
                FROM mensagens m
                JOIN usuarios_autorizados ua ON m.remetente_id = ua.id
                WHERE m.id = ?
            ");
            $stmt->execute([$mensagem_id]);
            $mensagem_atualizada = $stmt->fetch();
            
            $this->pdo->commit();
            
            return $mensagem_atualizada;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Adiciona/remove reação
     */
    public function reagirMensagem($mensagem_id, $usuario_id, $reacao) {
        try {
            $this->pdo->beginTransaction();
            
            if ($reacao === 'remover') {
                $stmt = $this->pdo->prepare("
                    DELETE FROM reacoes_mensagens 
                    WHERE mensagem_id = ? AND usuario_id = ?
                ");
                $stmt->execute([$mensagem_id, $usuario_id]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO reacoes_mensagens (mensagem_id, usuario_id, reacao)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE reacao = ?, data_reacao = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$mensagem_id, $usuario_id, $reacao, $reacao]);
            }
            
            // Buscar todas as reações
            $stmt = $this->pdo->prepare("
                SELECT 
                    rm.reacao,
                    GROUP_CONCAT(DISTINCT ua.nome_completo SEPARATOR ', ') as usuarios
                FROM reacoes_mensagens rm
                JOIN usuarios_autorizados ua ON rm.usuario_id = ua.id
                WHERE rm.mensagem_id = ?
                GROUP BY rm.reacao
            ");
            $stmt->execute([$mensagem_id]);
            $reacoes = $stmt->fetchAll();
            
            $this->pdo->commit();
            
            return $reacoes;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Atualiza status online
     */
    public function atualizarStatus($usuario_id, $status, $dispositivo = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO status_online (usuario_id, status, ultima_atividade, dispositivo)
            VALUES (?, ?, CURRENT_TIMESTAMP, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                ultima_atividade = CURRENT_TIMESTAMP,
                dispositivo = VALUES(dispositivo)
        ");
        $stmt->execute([$usuario_id, $status, $dispositivo]);
    }
    
    /**
     * Busca status de um usuário
     */
    public function buscarStatus($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT status, ultima_atividade 
            FROM status_online 
            WHERE usuario_id = ?
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetch();
    }
    
    /**
     * Busca usuários disponíveis para conversa
     */
    public function buscarUsuariosDisponiveis($usuario_id, $termo = '') {
        $sql = "
            SELECT id, nome_completo, funcao, 
                   (SELECT status FROM status_online WHERE usuario_id = ua.id) as status_online
            FROM usuarios_autorizados ua
            WHERE id != ?
            AND status = 'ativo'
        ";
        $params = [$usuario_id];
        
        if ($termo) {
            $sql .= " AND (nome_completo LIKE ? OR funcao LIKE ? OR email LIKE ?)";
            $termo = "%$termo%";
            $params = array_merge($params, [$termo, $termo, $termo]);
        }
        
        $sql .= " ORDER BY nome_completo LIMIT 50";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}