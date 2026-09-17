<?php
// ============================================
// modules/escola/horarios/delete.php - Apagar Horário
// Permite: admin, gestor, diretor, coordenador, secretario
// Bloqueia: professor, docente
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

$pdo = conectarBanco();

$id       = intval($_GET['id'] ?? 0);
$turma_id = intval($_GET['turma_id'] ?? 0);

$redirect_url = 'index.php' . ($turma_id ? '?turma_id=' . $turma_id : '');

// ============================================
// VERIFICAR PERMISSÕES
// 1) Verifica na tabela usuarios (admin/professor)
// 2) Verifica em funcionarios (cargo)
// ============================================
try {
    $is_professor = false;
    $is_admin = false;
    $user_encontrado = false;
    
    $uid = $_SESSION['usuario_id'] ?? 0;
    
    // ===== TENTAR 1: Tabela usuarios (principal) =====
    if ($uid > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([$uid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $user_encontrado = true;
                
                // Verificar tipo/cargo/perfil na tabela usuarios
                $tipo = strtolower(trim(
                    $user['tipo'] ?? 
                    $user['cargo'] ?? 
                    $user['perfil'] ?? 
                    $user['nivel'] ?? 
                    ''
                ));
                
                // Se for admin → libera
                if (strpos($tipo, 'admin') !== false 
                    || strpos($tipo, 'gestor') !== false 
                    || strpos($tipo, 'diretor') !== false 
                    || strpos($tipo, 'coordenador') !== false
                    || strpos($tipo, 'secretario') !== false) {
                    $is_admin = true;
                }
                
                // Se for professor → bloqueia
                if (strpos($tipo, 'professor') !== false 
                    || strpos($tipo, 'prof') !== false 
                    || strpos($tipo, 'docente') !== false) {
                    $is_professor = true;
                }
                
                // Se tiver funcionario_id, buscar o cargo real
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
            // Tabela usuarios ou coluna tipo pode não existir — ignorar
        }
    }
    
    // ===== TENTAR 2: Tabela funcionarios (fallback) =====
    if (!$user_encontrado) {
        $user_func = null;
        
        // Procurar por ID
        if ($uid > 0) {
            $stmt = $pdo->prepare("SELECT id, nome, email, cargo FROM funcionarios WHERE id = ? LIMIT 1");
            $stmt->execute([$uid]);
            $user_func = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_func) $user_encontrado = true;
        }
        
        // Procurar por email
        if (!$user_encontrado && !empty($_SESSION['usuario_email'])) {
            $stmt = $pdo->prepare("SELECT id, nome, email, cargo FROM funcionarios WHERE email = ? LIMIT 1");
            $stmt->execute([$_SESSION['usuario_email']]);
            $user_func = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_func) $user_encontrado = true;
        }
        
        // Procurar por nome
        if (!$user_encontrado && !empty($_SESSION['usuario_nome'])) {
            $stmt = $pdo->prepare("SELECT id, nome, email, cargo FROM funcionarios WHERE nome = ? LIMIT 1");
            $stmt->execute([$_SESSION['usuario_nome']]);
            $user_func = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_func) $user_encontrado = true;
        }
        
        // Analisar cargo
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
    // Bloquear professor (que não seja admin)
    if ($is_professor && !$is_admin) {
        $_SESSION['erro_horario'] = 'Professores não têm permissão para apagar horários.';
        header('Location: ' . $redirect_url);
        exit;
    }
    
    // Bloquear se não for admin
    if (!$is_admin) {
        $_SESSION['erro_horario'] = 'Apenas administradores podem apagar horários.';
        header('Location: ' . $redirect_url);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Erro permissão delete: " . $e->getMessage());
    $_SESSION['erro_horario'] = 'Erro ao verificar permissões.';
    header('Location: ' . $redirect_url);
    exit;
}

// ============================================
// VALIDAR ID
// ============================================
if ($id <= 0) {
    $_SESSION['erro_horario'] = 'ID inválido.';
    header('Location: ' . $redirect_url);
    exit;
}

// ============================================
// APAGAR
// ============================================
try {
    // Buscar dados antes de apagar (para mensagem)
    $stmt = $pdo->prepare("
        SELECT h.*, t.nome as turma_nome, t.classe as turma_classe
        FROM horarios h
        LEFT JOIN turmas t ON h.turma_id = t.id
        WHERE h.id = ? LIMIT 1
    ");
    $stmt->execute([$id]);
    $horario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$horario) {
        $_SESSION['erro_horario'] = 'Horário não encontrado.';
        header('Location: ' . $redirect_url);
        exit;
    }
    
    // Apagar
    $stmt = $pdo->prepare("DELETE FROM horarios WHERE id = ?");
    $stmt->execute([$id]);
    
    $descricao = $horario['disciplina'] ?? 'Horário';
    if (!empty($horario['is_intervalo'])) {
        $descricao = 'Intervalo';
    }
    
    $_SESSION['sucesso_horario'] = "{$descricao} removido com sucesso!";
    
} catch (Exception $e) {
    error_log("Erro ao apagar horário: " . $e->getMessage());
    $_SESSION['erro_horario'] = 'Erro ao apagar: ' . $e->getMessage();
}

header('Location: ' . $redirect_url);
exit;