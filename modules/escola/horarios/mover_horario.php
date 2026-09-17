<?php
// ============================================
// modules/escola/horarios/mover_horario.php
// Move um horário para outro dia/tempo
// Permite: admin, gestor, diretor, coordenador, secretario
// Bloqueia: professor, docente
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

// ===== VERIFICAR SESSÃO =====
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

$pdo = conectarBanco();

// ============================================
// VERIFICAR PERMISSÕES
// ============================================
try {
    $is_professor = false;
    $is_admin = false;
    $user_encontrado = false;
    
    $uid = $_SESSION['usuario_id'] ?? 0;
    
    // ===== TENTAR 1: Tabela usuarios =====
    if ($uid > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([$uid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $user_encontrado = true;
                
                $tipo = strtolower(trim(
                    $user['tipo'] ?? 
                    $user['cargo'] ?? 
                    $user['perfil'] ?? 
                    $user['nivel'] ?? 
                    ''
                ));
                
                if (strpos($tipo, 'admin') !== false 
                    || strpos($tipo, 'gestor') !== false 
                    || strpos($tipo, 'diretor') !== false 
                    || strpos($tipo, 'coordenador') !== false
                    || strpos($tipo, 'secretario') !== false) {
                    $is_admin = true;
                }
                
                if (strpos($tipo, 'professor') !== false 
                    || strpos($tipo, 'prof') !== false 
                    || strpos($tipo, 'docente') !== false) {
                    $is_professor = true;
                }
                
                // Verificar cargo em funcionarios se tiver funcionario_id
                if (!empty($user['funcionario_id'])) {
                    $stmt2 = $pdo->prepare("SELECT cargo FROM funcionarios WHERE id = ? LIMIT 1");
                    $stmt2->execute([$user['funcionario_id']]);
                    $func = $stmt2->fetch(PDO::FETCH_ASSOC);
                    if ($func) {
                        $cargo = strtolower(trim($func['cargo'] ?? ''));
                        if (strpos($cargo, 'professor') !== false || strpos($cargo, 'prof') !== false || strpos($cargo, 'docente') !== false) {
                            $is_professor = true;
                        }
                        if (strpos($cargo, 'admin') !== false || strpos($cargo, 'gestor') !== false || strpos($cargo, 'diretor') !== false) {
                            $is_admin = true;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Ignorar
        }
    }
    
    // ===== TENTAR 2: Tabela funcionarios =====
    if (!$user_encontrado) {
        $user_func = null;
        
        if ($uid > 0) {
            $stmt = $pdo->prepare("SELECT id, nome, email, cargo FROM funcionarios WHERE id = ? LIMIT 1");
            $stmt->execute([$uid]);
            $user_func = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user_func) $user_encontrado = true;
        }
        
        if (!$user_encontrado && !empty($_SESSION['usuario_email'])) {
            $stmt = $pdo->prepare("SELECT id, nome, email, cargo FROM funcionarios WHERE email = ? LIMIT 1");
            $stmt->execute([$_SESSION['usuario_email']]);
            $user_func = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user_func) $user_encontrado = true;
        }
        
        if (!$user_encontrado && !empty($_SESSION['usuario_nome'])) {
            $stmt = $pdo->prepare("SELECT id, nome, email, cargo FROM funcionarios WHERE nome = ? LIMIT 1");
            $stmt->execute([$_SESSION['usuario_nome']]);
            $user_func = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user_func) $user_encontrado = true;
        }
        
        if (!empty($user_func)) {
            $cargo = strtolower(trim($user_func['cargo'] ?? ''));
            
            $is_professor = 
                strpos($cargo, 'professor') !== false ||
                strpos($cargo, 'prof') !== false ||
                strpos($cargo, 'docente') !== false;
            
            $is_admin = 
                strpos($cargo, 'admin') !== false ||
                strpos($cargo, 'gestor') !== false ||
                strpos($cargo, 'diretor') !== false ||
                strpos($cargo, 'coordenador') !== false ||
                strpos($cargo, 'secretario') !== false;
        }
    }
    
    // ===== DECISÃO FINAL =====
    if ($is_professor && !$is_admin) {
        echo json_encode([
            'success' => false,
            'message' => 'Professores não têm permissão para mover horários.'
        ]);
        exit;
    }
    
    if (!$is_admin) {
        echo json_encode([
            'success' => false,
            'message' => 'Apenas administradores podem mover horários.'
        ]);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Erro permissão mover: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao verificar permissões.'
    ]);
    exit;
}

// ============================================
// RECEBER DADOS
// ============================================
$id             = intval($_REQUEST['id'] ?? 0);
$novo_dia       = trim($_REQUEST['dia_semana'] ?? '');
$nova_hora_ini  = trim($_REQUEST['hora_inicio'] ?? '');
$nova_hora_fim  = trim($_REQUEST['hora_fim'] ?? '');
$tempo_id       = intval($_REQUEST['tempo_id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do horário inválido.']);
    exit;
}

if (empty($novo_dia)) {
    echo json_encode(['success' => false, 'message' => 'Dia da semana não informado.']);
    exit;
}

$dias_validos = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
if (!in_array($novo_dia, $dias_validos)) {
    echo json_encode(['success' => false, 'message' => 'Dia da semana inválido.']);
    exit;
}

// ============================================
// PROCESSAR
// ============================================
try {
    $stmt = $pdo->prepare("SELECT * FROM horarios WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $horario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$horario) {
        echo json_encode(['success' => false, 'message' => 'Horário não encontrado.']);
        exit;
    }
    
    if ($tempo_id > 0) {
        $stmt = $pdo->prepare("SELECT id, nome, hora_inicio, hora_fim FROM tempos WHERE id = ? LIMIT 1");
        $stmt->execute([$tempo_id]);
        $tempo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tempo) {
            echo json_encode(['success' => false, 'message' => 'Tempo não encontrado.']);
            exit;
        }
        
        $nova_hora_ini = $tempo['hora_inicio'];
        $nova_hora_fim = $tempo['hora_fim'];
    }
    
    if (empty($nova_hora_ini) || empty($nova_hora_fim)) {
        $nova_hora_ini = $horario['hora_inicio'];
        $nova_hora_fim = $horario['hora_fim'];
    }
    
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $nova_hora_ini) ||
        !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $nova_hora_fim)) {
        echo json_encode(['success' => false, 'message' => 'Formato de hora inválido.']);
        exit;
    }
    
    // Verificar conflito
    $stmt = $pdo->prepare("
        SELECT id, disciplina FROM horarios 
        WHERE turma_id = ? 
          AND dia_semana = ? 
          AND id != ?
          AND (
                (hora_inicio < ? AND hora_fim > ?)
                OR (hora_inicio = ? AND hora_fim = ?)
              )
        LIMIT 1
    ");
    $stmt->execute([
        $horario['turma_id'],
        $novo_dia,
        $id,
        $nova_hora_fim, $nova_hora_ini,
        $nova_hora_ini, $nova_hora_fim
    ]);
    $conflito = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conflito) {
        echo json_encode([
            'success' => false,
            'message' => 'Já existe um horário ("' . ($conflito['disciplina'] ?? 'aula') . '") nesta turma para este dia e horário.'
        ]);
        exit;
    }
    
    // Atualizar
    $stmt = $pdo->prepare("
        UPDATE horarios 
        SET dia_semana = ?, 
            hora_inicio = ?, 
            hora_fim = ?, 
            tempo_id = CASE WHEN ? > 0 THEN ? ELSE tempo_id END,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $novo_dia,
        $nova_hora_ini,
        $nova_hora_fim,
        $tempo_id, $tempo_id,
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Horário movido com sucesso!',
        'id' => $id,
        'novo_dia' => $novo_dia,
        'nova_hora_inicio' => $nova_hora_ini,
        'nova_hora_fim' => $nova_hora_fim,
        'tempo_id' => $tempo_id > 0 ? $tempo_id : $horario['tempo_id']
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao mover horário: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao mover: ' . $e->getMessage()
    ]);
}