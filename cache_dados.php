<?php
// ============================================
// cache_dados.php - Cache de Dados
// ============================================

require_once 'config/cache_sistema.php';

$cache = new SistemaCache();

// Cache de alunos
function getAlunosCached() {
    global $pdo, $cache;
    
    $chave = 'alunos_lista';
    $dados = $cache->buscar($chave);
    
    if ($dados === null) {
        $stmt = $pdo->query("SELECT * FROM alunos LIMIT 100");
        $dados = $stmt->fetchAll();
        $cache->salvar($chave, $dados, 1800); // 30 minutos
    }
    
    return $dados;
}

// Cache de turmas
function getTurmasCached() {
    global $pdo, $cache;
    
    $chave = 'turmas_lista';
    $dados = $cache->buscar($chave);
    
    if ($dados === null) {
        $stmt = $pdo->query("SELECT * FROM turmas");
        $dados = $stmt->fetchAll();
        $cache->salvar($chave, $dados, 3600); // 1 hora
    }
    
    return $dados;
}

// Cache de configurações
function getConfiguracoesCached() {
    global $pdo, $cache;
    
    $chave = 'configuracoes';
    $dados = $cache->buscar($chave);
    
    if ($dados === null) {
        $stmt = $pdo->query("SELECT * FROM configuracoes");
        $dados = $stmt->fetchAll();
        $cache->salvar($chave, $dados, 7200); // 2 horas
    }
    
    return $dados;
}
?>