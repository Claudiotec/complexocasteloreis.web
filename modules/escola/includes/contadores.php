<?php
// ============================================
// contadores.php - Contadores Centralizados
// ============================================

// ============================================
// CONTAR PROFESSORES - TABELA funcionarios
// ============================================
function contarProfessores() {
    global $pdo;
    
    try {
        // Verifica se a tabela existe
        $check = $pdo->query("SHOW TABLES LIKE 'funcionarios'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        // 🔥 CONSULTA QUE FUNCIONA (testada!)
        $sql = "
            SELECT COUNT(*) as total 
            FROM funcionarios 
            WHERE categoria_actual LIKE '%Professor%'
               OR categoria_actual LIKE '%professor%'
               OR cargo LIKE '%Professor%'
               OR cargo LIKE '%professor%'
               OR funcao_instituicao LIKE '%Professor%'
               OR funcao_instituicao LIKE '%professor%'
        ";
        
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Erro contarProfessores: " . $e->getMessage());
        return 0;
    }
}

// ============================================
// CONTAR ALUNOS
// ============================================
function contarAlunos() {
    global $pdo;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'alunos'");
        if ($check->rowCount() == 0) return 0;
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM alunos WHERE status = 'ativo' OR status IS NULL");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

// ============================================
// CONTAR TURMAS
// ============================================
function contarTurmas() {
    global $pdo;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'turmas'");
        if ($check->rowCount() == 0) return 0;
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM turmas WHERE status = 'ativa' OR status IS NULL");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

// ============================================
// CONTAR DISCIPLINAS
// ============================================
function contarDisciplinas() {
    global $pdo;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'disciplinas'");
        if ($check->rowCount() == 0) return 0;
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM disciplinas WHERE status = 'ativa' OR status IS NULL");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}
?>