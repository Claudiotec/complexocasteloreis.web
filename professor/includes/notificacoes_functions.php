<?php
// includes/notificacoes_functions.php

/**
 * Sistema de Notificações para Professores
 * Monitora todos os eventos relacionados aos alunos das turmas do professor
 */

function getTurmasProfessor($pdo, $professor_id) {
    $stmt = $pdo->prepare("SELECT turma_nome, turma_id FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR'");
    $stmt->execute([$professor_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAlunosTurmas($pdo, $turmas) {
    if (empty($turmas)) return [];
    
    $turmas_nomes = array_column($turmas, 'turma_nome');
    $placeholders = implode(',', array_fill(0, count($turmas_nomes), '?'));
    $stmt = $pdo->prepare("SELECT id, nome, TURMA FROM alunos WHERE TURMA IN ($placeholders) AND status = 'ativo'");
    $stmt->execute($turmas_nomes);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function criarNotificacao($pdo, $professor_id, $tipo, $titulo, $mensagem, $link = null, $icone = '📢', $cor = 'gold', $aluno_id = null, $aluno_nome = null, $turma_id = null, $turma_nome = null) {
    $stmt = $pdo->prepare("
        INSERT INTO notificacoes_professor 
        (professor_id, tipo, titulo, mensagem, link, icone, cor, aluno_id, aluno_nome, turma_id, turma_nome, data_evento) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    return $stmt->execute([$professor_id, $tipo, $titulo, $mensagem, $link, $icone, $cor, $aluno_id, $aluno_nome, $turma_id, $turma_nome]);
}

// ==========================================
// TRIGGERS DE NOTIFICAÇÃO
// ==========================================

function notificarPagamento($pdo, $pagamento_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, a.id as aluno_id, a.nome as aluno_nome, a.TURMA as turma_nome, e.nome as emolumento_nome
            FROM pagamentos p
            JOIN alunos a ON p.aluno_id = a.id
            LEFT JOIN emolumentos e ON p.emolumento_id = e.id
            WHERE p.id = ?
        ");
        $stmt->execute([$pagamento_id]);
        $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pagamento) return false;
        
        // Buscar professores das turmas do aluno
        $stmt = $pdo->prepare("
            SELECT DISTINCT dp.professor_id 
            FROM destribuicao_professores dp 
            WHERE dp.turma_nome = ? AND dp.tipo = 'PROFESSOR'
        ");
        $stmt->execute([$pagamento['turma_nome']]);
        $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($professores as $prof) {
            $titulo = "💰 Novo Pagamento - {$pagamento['aluno_nome']}";
            $mensagem = "Pagamento de " . number_format($pagamento['valor'], 2, ',', '.') . " Kz para " . 
                        ($pagamento['emolumento_nome'] ?? 'Emolumento') . " registrado com sucesso.";
            $link = "/softgest_web/modules/escola/financeiro/pagamentos/view.php?id={$pagamento['id']}";
            
            criarNotificacao(
                $pdo, 
                $prof['professor_id'], 
                'pagamento',
                $titulo,
                $mensagem,
                $link,
                '💰',
                'success',
                $pagamento['aluno_id'],
                $pagamento['aluno_nome'],
                null,
                $pagamento['turma_nome']
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Erro ao notificar pagamento: " . $e->getMessage());
        return false;
    }
}

function notificarFalta($pdo, $falta_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT f.*, a.id as aluno_id, a.nome as aluno_nome, a.TURMA as turma_nome, 
                   d.nome as disciplina_nome, t.nome as turma_nome_full
            FROM frequencia f
            JOIN alunos a ON f.aluno_id = a.id
            JOIN disciplinas d ON f.disciplina_id = d.id
            LEFT JOIN turmas t ON f.turma_id = t.id
            WHERE f.id = ? AND f.status = 'ausente'
        ");
        $stmt->execute([$falta_id]);
        $falta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$falta) return false;
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT dp.professor_id 
            FROM destribuicao_professores dp 
            WHERE dp.turma_nome = ? AND dp.tipo = 'PROFESSOR'
        ");
        $stmt->execute([$falta['turma_nome']]);
        $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($professores as $prof) {
            $titulo = "❌ Falta Registrada - {$falta['aluno_nome']}";
            $mensagem = "O aluno {$falta['aluno_nome']} faltou à disciplina {$falta['disciplina_nome']} em " . date('d/m/Y', strtotime($falta['data']));
            $link = "/softgest_web/modules/escola/frequencia/view.php?id={$falta['id']}";
            
            criarNotificacao(
                $pdo,
                $prof['professor_id'],
                'falta',
                $titulo,
                $mensagem,
                $link,
                '❌',
                'danger',
                $falta['aluno_id'],
                $falta['aluno_nome'],
                $falta['turma_id'],
                $falta['turma_nome']
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Erro ao notificar falta: " . $e->getMessage());
        return false;
    }
}

function notificarNota($pdo, $nota_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT n.*, a.id as aluno_id, a.nome as aluno_nome, a.TURMA as turma_nome,
                   d.nome as disciplina_nome, m.turma_id
            FROM notas n
            JOIN matriculas m ON n.matricula_id = m.id
            JOIN alunos a ON m.aluno_id = a.id
            JOIN disciplinas d ON n.disciplina_id = d.id
            WHERE n.id = ?
        ");
        $stmt->execute([$nota_id]);
        $nota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nota) return false;
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT dp.professor_id 
            FROM destribuicao_professores dp 
            WHERE dp.turma_nome = ? AND dp.tipo = 'PROFESSOR'
        ");
        $stmt->execute([$nota['turma_nome']]);
        $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($professores as $prof) {
            $media = $nota['media'] ?? 'N/A';
            $titulo = "📝 Nota Lançada - {$nota['aluno_nome']}";
            $mensagem = "Nota de {$nota['disciplina_nome']} para {$nota['aluno_nome']}: Média = {$media} - " . 
                        ($nota['resultado'] ?? 'Aguardando');
            $link = "/softgest_web/modules/escola/notas/view.php?id={$nota['id']}";
            
            criarNotificacao(
                $pdo,
                $prof['professor_id'],
                'nota',
                $titulo,
                $mensagem,
                $link,
                '📝',
                'info',
                $nota['aluno_id'],
                $nota['aluno_nome'],
                $nota['turma_id'],
                $nota['turma_nome']
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Erro ao notificar nota: " . $e->getMessage());
        return false;
    }
}

function notificarMatricula($pdo, $matricula_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, a.id as aluno_id, a.nome as aluno_nome, a.TURMA as turma_nome,
                   t.nome as turma_nome_full, t.id as turma_id
            FROM matriculas m
            JOIN alunos a ON m.aluno_id = a.id
            JOIN turmas t ON m.turma_id = t.id
            WHERE m.id = ?
        ");
        $stmt->execute([$matricula_id]);
        $matricula = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$matricula) return false;
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT dp.professor_id 
            FROM destribuicao_professores dp 
            WHERE dp.turma_nome = ? AND dp.tipo = 'PROFESSOR'
        ");
        $stmt->execute([$matricula['turma_nome']]);
        $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($professores as $prof) {
            $titulo = "📋 Nova Matrícula - {$matricula['aluno_nome']}";
            $mensagem = "O aluno {$matricula['aluno_nome']} foi matriculado na turma {$matricula['turma_nome_full']} em " . date('d/m/Y', strtotime($matricula['data_matricula']));
            $link = "/softgest_web/modules/escola/matriculas/view.php?id={$matricula['id']}";
            
            criarNotificacao(
                $pdo,
                $prof['professor_id'],
                'matricula',
                $titulo,
                $mensagem,
                $link,
                '📋',
                'primary',
                $matricula['aluno_id'],
                $matricula['aluno_nome'],
                $matricula['turma_id'],
                $matricula['turma_nome']
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Erro ao notificar matrícula: " . $e->getMessage());
        return false;
    }
}

function notificarMensalidade($pdo, $mensalidade_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, a.id as aluno_id, a.nome as aluno_nome, a.TURMA as turma_nome
            FROM mensalidades m
            JOIN alunos a ON m.aluno_id = a.id
            WHERE m.id = ? AND m.status IN ('pendente', 'atrasado')
        ");
        $stmt->execute([$mensalidade_id]);
        $mensalidade = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mensalidade) return false;
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT dp.professor_id 
            FROM destribuicao_professores dp 
            WHERE dp.turma_nome = ? AND dp.tipo = 'PROFESSOR'
        ");
        $stmt->execute([$mensalidade['turma_nome']]);
        $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $status_emoji = $mensalidade['status'] == 'atrasado' ? '⚠️' : '⏳';
        
        foreach ($professores as $prof) {
            $titulo = "{$status_emoji} Mensalidade {$mensalidade['status']} - {$mensalidade['aluno_nome']}";
            $mensagem = "Mensalidade de " . number_format($mensalidade['valor'], 2, ',', '.') . " Kz para {$mensalidade['aluno_nome']} está " . 
                        ($mensalidade['status'] == 'atrasado' ? 'ATRASADA' : 'pendente') . 
                        " - Vencimento: " . date('d/m/Y', strtotime($mensalidade['data_vencimento']));
            $link = "/softgest_web/modules/escola/financeiro/mensalidades/view.php?id={$mensalidade['id']}";
            
            criarNotificacao(
                $pdo,
                $prof['professor_id'],
                'mensalidade',
                $titulo,
                $mensagem,
                $link,
                $status_emoji,
                $mensalidade['status'] == 'atrasado' ? 'danger' : 'warning',
                $mensalidade['aluno_id'],
                $mensalidade['aluno_nome'],
                null,
                $mensalidade['turma_nome']
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Erro ao notificar mensalidade: " . $e->getMessage());
        return false;
    }
}

function notificarFuncionarioEvento($pdo, $funcionario_id, $tipo_evento, $descricao) {
    try {
        // Buscar professores que são funcionários ou têm relação
        $stmt = $pdo->prepare("
            SELECT DISTINCT dp.professor_id 
            FROM destribuicao_professores dp
            WHERE dp.professor_id = ?
        ");
        $stmt->execute([$funcionario_id]);
        $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($professores as $prof) {
            $titulo = "👤 Evento do Funcionário";
            $mensagem = $descricao;
            
            criarNotificacao(
                $pdo,
                $prof['professor_id'],
                'funcionario',
                $titulo,
                $mensagem,
                null,
                '👤',
                'info',
                null,
                null,
                null,
                null
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Erro ao notificar evento do funcionário: " . $e->getMessage());
        return false;
    }
}

// ==========================================
// FUNÇÃO PARA VERIFICAR EVENTOS PERIODICAMENTE
// ==========================================

function verificarEventosPeriodicos($pdo) {
    // Verificar mensalidades vencidas
    $stmt = $pdo->query("
        SELECT m.*, a.id as aluno_id, a.nome as aluno_nome, a.TURMA as turma_nome
        FROM mensalidades m
        JOIN alunos a ON m.aluno_id = a.id
        WHERE m.status = 'pendente' AND m.data_vencimento < CURDATE()
    ");
    $mensalidades_vencidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($mensalidades_vencidas as $mensalidade) {
        // Atualizar status para atrasado
        $stmt = $pdo->prepare("UPDATE mensalidades SET status = 'atrasado' WHERE id = ?");
        $stmt->execute([$mensalidade['id']]);
        
        // Notificar professores
        $stmt = $pdo->prepare("
            SELECT DISTINCT dp.professor_id 
            FROM destribuicao_professores dp 
            WHERE dp.turma_nome = ? AND dp.tipo = 'PROFESSOR'
        ");
        $stmt->execute([$mensalidade['turma_nome']]);
        $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($professores as $prof) {
            $titulo = "⚠️ Mensalidade Vencida - {$mensalidade['aluno_nome']}";
            $mensagem = "Mensalidade de " . number_format($mensalidade['valor'], 2, ',', '.') . " Kz para {$mensalidade['aluno_nome']} está VENCIDA desde " . date('d/m/Y', strtotime($mensalidade['data_vencimento']));
            $link = "/softgest_web/modules/escola/financeiro/mensalidades/view.php?id={$mensalidade['id']}";
            
            criarNotificacao(
                $pdo,
                $prof['professor_id'],
                'mensalidade',
                $titulo,
                $mensagem,
                $link,
                '⚠️',
                'danger',
                $mensalidade['aluno_id'],
                $mensalidade['aluno_nome'],
                null,
                $mensalidade['turma_nome']
            );
        }
    }
}

// ==========================================
// CONTADOR DE NOTIFICAÇÕES NÃO LIDAS
// ==========================================

function contarNotificacoesNaoLidas($pdo, $professor_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes_professor WHERE professor_id = ? AND lida = 0");
    $stmt->execute([$professor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

function getUltimasNotificacoes($pdo, $professor_id, $limite = 10) {
    $stmt = $pdo->prepare("
        SELECT * FROM notificacoes_professor 
        WHERE professor_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bindParam(1, $professor_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function marcarNotificacaoLida($pdo, $notificacao_id, $professor_id) {
    $stmt = $pdo->prepare("UPDATE notificacoes_professor SET lida = 1 WHERE id = ? AND professor_id = ?");
    return $stmt->execute([$notificacao_id, $professor_id]);
}

function marcarTodasLidas($pdo, $professor_id) {
    $stmt = $pdo->prepare("UPDATE notificacoes_professor SET lida = 1 WHERE professor_id = ?");
    return $stmt->execute([$professor_id]);
}