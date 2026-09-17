<?php
// ============================================
// funcoes_professores.php - Funções para Professores
// Módulo: Escola > Professores
// ============================================

/**
 * Conta o número total de professores na tabela funcionarios
 * Busca em cascata: categoria_actual → cargo → funcao_instituicao
 * 
 * @return int Total de professores
 */
function contarProfessores() {
    try {
        $pdo = conectarBanco();
        
        // Verifica se a tabela existe
        $check = $pdo->query("SHOW TABLES LIKE 'funcionarios'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        // Conta professores com busca em cascata
        $sql = "
            SELECT COUNT(*) as total FROM funcionarios 
            WHERE LOWER(categoria_actual) LIKE '%professor%'
               OR LOWER(cargo) LIKE '%professor%'
               OR LOWER(funcao_instituicao) LIKE '%professor%'
        ";
        
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Erro ao contar professores: " . $e->getMessage());
        return 0;
    }
}

/**
 * Conta professores por status
 * 
 * @param string $status Status do professor (ativo, inativo, ferias, licenca)
 * @return int Total de professores com aquele status
 */
function contarProfessoresPorStatus($status) {
    try {
        $pdo = conectarBanco();
        
        $check = $pdo->query("SHOW TABLES LIKE 'funcionarios'");
        if ($check->rowCount() == 0) {
            return 0;
        }
        
        $sql = "
            SELECT COUNT(*) as total FROM funcionarios 
            WHERE (LOWER(categoria_actual) LIKE '%professor%'
               OR LOWER(cargo) LIKE '%professor%'
               OR LOWER(funcao_instituicao) LIKE '%professor%')
              AND LOWER(status) = LOWER(:status)
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':status' => $status]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Erro ao contar professores por status: " . $e->getMessage());
        return 0;
    }
}

/**
 * Retorna estatísticas completas de professores
 * 
 * @return array Estatísticas com total, ativos, inativos, ferias, licencas
 */
function getEstatisticasProfessores() {
    return [
        'total' => contarProfessores(),
        'ativos' => contarProfessoresPorStatus('ativo'),
        'inativos' => contarProfessoresPorStatus('inativo'),
        'ferias' => contarProfessoresPorStatus('ferias'),
        'licencas' => contarProfessoresPorStatus('licenca')
    ];
}
?>