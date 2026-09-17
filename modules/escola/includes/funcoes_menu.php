<?php
// ============================================
// funcoes_menu.php - Funções para o Menu Lateral
// Módulo: Escola
// ============================================

// ============================================
// 1. CARREGAR CONFIGURAÇÕES DO BANCO
// ============================================
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/app_modes.php';

// ============================================
// 2. INICIAR SESSÃO (se necessário)
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 3. FUNÇÕES DE CONTAGEM
// ============================================

/**
 * Conta professores com busca em cascata
 * @return int
 */
function contarProfessoresMenu() {
    try {
        // Conexão com o banco
        $pdo = conectarBanco();
        
        // Verifica se a tabela funcionarios existe
        $check = $pdo->query("SHOW TABLES LIKE 'funcionarios'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        // 🔍 BUSCA EM CASCATA:
        // 1º categoria_actual = Professor
        // 2º cargo = Professor
        // 3º funcao_instituicao = Professor
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
        error_log("Erro ao contar professores no menu: " . $e->getMessage());
        return 0;
    }
}

/**
 * Conta alunos ativos
 * @return int
 */
function contarAlunosMenu() {
    try {
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
        error_log("Erro ao contar alunos: " . $e->getMessage());
        return 0;
    }
}

/**
 * Conta turmas ativas
 * @return int
 */
function contarTurmasMenu() {
    try {
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
        error_log("Erro ao contar turmas: " . $e->getMessage());
        return 0;
    }
}

/**
 * Conta mensagens não lidas
 * @return int
 */
function contarMensagensMenu() {
    try {
        $pdo = conectarBanco();
        
        $check = $pdo->query("SHOW TABLES LIKE 'mensagens'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        $usuario_id = $_SESSION['usuario_id'] ?? 0;
        if ($usuario_id == 0) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as total FROM mensagens WHERE destino_id = ? AND lida = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Erro ao contar mensagens: " . $e->getMessage());
        return 0;
    }
}

/**
 * Conta disciplinas
 * @return int
 */
function contarDisciplinasMenu() {
    try {
        $pdo = conectarBanco();
        
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

/**
 * Conta frequências pendentes
 * @return int
 */
function contarFrequenciasMenu() {
    try {
        $pdo = conectarBanco();
        
        $check = $pdo->query("SHOW TABLES LIKE 'frequencias'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as total FROM frequencias WHERE status = 'pendente' OR status IS NULL";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Erro ao contar frequências: " . $e->getMessage());
        return 0;
    }
}

/**
 * Obtém todos os totais de uma vez (otimizado)
 * @return array
 */
function getTotaisMenu() {
    return [
        'alunos' => contarAlunosMenu(),
        'professores' => contarProfessoresMenu(),
        'turmas' => contarTurmasMenu(),
        'disciplinas' => contarDisciplinasMenu(),
        'mensagens' => contarMensagensMenu(),
        'frequencias' => contarFrequenciasMenu()
    ];
}

// ============================================
// 4. CALCULAR OS TOTAIS (executado ao incluir)
// ============================================
$totais_menu = getTotaisMenu();

// Define variáveis individuais para compatibilidade
$totalAlunos = $totais_menu['alunos'];
$totalProfessores = $totais_menu['professores'];
$totalTurmas = $totais_menu['turmas'];
$totalDisciplinas = $totais_menu['disciplinas'];
$totalMensagens = $totais_menu['mensagens'];
$totalFrequencias = $totais_menu['frequencias'];
?>