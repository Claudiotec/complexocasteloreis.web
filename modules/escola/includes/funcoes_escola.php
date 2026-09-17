<?php
// ============================================
// funcoes_escola.php - Funções Centralizadas do Módulo Escola
// ============================================

// ============================================
// CARREGAR CONFIGURAÇÕES (USANDO O PDO GLOBAL)
// ============================================
require_once __DIR__ . '/../../../config/database.php';

// ============================================
// CONTAR PROFESSORES - DIRETO DA TABELA funcionarios
// ============================================
function contarProfessoresEscola() {
    global $pdo;
    
    try {
        // Verifica se a tabela funcionarios existe
        $check = $pdo->query("SHOW TABLES LIKE 'funcionarios'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        // 🔥 CONTAGEM SIMPLES E DIRETA
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
        $total = (int)($result['total'] ?? 0);
        
        // 🔥 SE DEU 0, TENTA CONTAR MANUALMENTE
        if ($total == 0) {
            $todos = $pdo->query("SELECT categoria_actual, cargo, funcao_instituicao FROM funcionarios")->fetchAll();
            foreach ($todos as $reg) {
                $texto = strtolower(($reg['categoria_actual'] ?? '') . ' ' . ($reg['cargo'] ?? '') . ' ' . ($reg['funcao_instituicao'] ?? ''));
                if (strpos($texto, 'professor') !== false) {
                    $total++;
                }
            }
        }
        
        return $total;
        
    } catch (Exception $e) {
        error_log("Erro ao contar professores: " . $e->getMessage());
        return 0;
    }
}

// ============================================
// CONTAR ALUNOS
// ============================================
function contarAlunosEscola() {
    global $pdo;
    
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'alunos'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as total FROM alunos WHERE status = 'ativo' OR status IS NULL";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Erro ao contar alunos: " . $e->getMessage());
        return 0;
    }
}

// ============================================
// CONTAR TURMAS
// ============================================
function contarTurmasEscola() {
    global $pdo;
    
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'turmas'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as total FROM turmas WHERE status = 'ativa' OR status IS NULL";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Erro ao contar turmas: " . $e->getMessage());
        return 0;
    }
}

// ============================================
// CONTAR DISCIPLINAS
// ============================================
function contarDisciplinasEscola() {
    global $pdo;
    
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'disciplinas'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as total FROM disciplinas WHERE status = 'ativa' OR status IS NULL";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Erro ao contar disciplinas: " . $e->getMessage());
        return 0;
    }
}

// ============================================
// OBTER TODOS OS TOTAIS
// ============================================
function getTotaisEscola() {
    return [
        'alunos' => contarAlunosEscola(),
        'professores' => contarProfessoresEscola(),
        'turmas' => contarTurmasEscola(),
        'disciplinas' => contarDisciplinasEscola()
    ];
}

// ============================================
// LISTAR PROFESSORES
// ============================================
function listarProfessoresEscola() {
    global $pdo;
    
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'funcionarios'");
        if ($check->rowCount() == 0) {
            return [];
        }
        
        $sql = "
            SELECT * FROM funcionarios 
            WHERE categoria_actual LIKE '%Professor%'
               OR categoria_actual LIKE '%professor%'
               OR cargo LIKE '%Professor%'
               OR cargo LIKE '%professor%'
               OR funcao_instituicao LIKE '%Professor%'
               OR funcao_instituicao LIKE '%professor%'
            ORDER BY nome
        ";
        
        $stmt = $pdo->query($sql);
        $result = $stmt->fetchAll();
        
        // Se não encontrou com LIKE, tenta buscar todos e filtrar
        if (empty($result)) {
            $todos = $pdo->query("SELECT * FROM funcionarios")->fetchAll();
            foreach ($todos as $reg) {
                $texto = strtolower(($reg['categoria_actual'] ?? '') . ' ' . ($reg['cargo'] ?? '') . ' ' . ($reg['funcao_instituicao'] ?? ''));
                if (strpos($texto, 'professor') !== false) {
                    $result[] = $reg;
                }
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erro ao listar professores: " . $e->getMessage());
        return [];
    }
}
?>