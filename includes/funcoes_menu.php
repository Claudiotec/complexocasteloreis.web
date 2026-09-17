<?php
// ============================================
// funcoes_menu.php - Funções para o Menu Lateral
// ============================================

/**
 * Conta professores com busca em cascata
 * @return int
 */
function contarProfessoresMenu() {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarBanco();
        
        $check = $pdo->query("SHOW TABLES LIKE 'funcionarios'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        // Busca em cascata: categoria_actual → cargo → funcao_instituicao
        $sql = "
            SELECT COUNT(*) as total 
            FROM funcionarios 
            WHERE LOWER(categoria_actual) LIKE '%professor%'
               OR LOWER(cargo) LIKE '%professor%'
               OR LOWER(funcao_instituicao) LIKE '%professor%'
        ";
        
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Conta alunos ativos
 * @return int
 */
function contarAlunosMenu() {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarBanco();
        
        $check = $pdo->query("SHOW TABLES LIKE 'alunos'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as total FROM alunos WHERE status = 'ativo' OR status IS NULL";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Conta turmas ativas
 * @return int
 */
function contarTurmasMenu() {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarBanco();
        
        $check = $pdo->query("SHOW TABLES LIKE 'turmas'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as total FROM turmas WHERE status = 'ativa' OR status IS NULL";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        return 0;
    }
}