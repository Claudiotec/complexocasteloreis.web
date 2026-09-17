<?php
// ============================================
// modules/escola/alunos/add.php - Cadastrar Aluno
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para CRIAR
bloquearAcesso('criar');

// Resto do código...
?>







<?php
// ============================================
// modules/escola/turmas/relatorio_vagas.php - Relatório de Vagas
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

// Buscar dados
$turmas = [];
try {
    $turmas = $pdo->query("
        SELECT t.*, 
               (SELECT COUNT(*) FROM matriculas WHERE turma_id = t.id AND status = 'ativa') as total_alunos
        FROM turmas t
        ORDER BY t.classe, t.nome
    ")->fetchAll();
} catch (Exception $e) {}

// Configurar cabeçalhos para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=relatorio_vagas_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Turma', 'Classe', 'Curso', 'Turno', 'Limite', 'Matriculados', 'Vagas', 'Situação']);

foreach($turmas as $t) {
    $limite = $t['limite'] ?? 30;
    $alunos = $t['total_alunos'] ?? 0;
    $vagas = $limite - $alunos;
    $situacao = $vagas > 5 ? 'Disponível' : ($vagas > 0 ? 'Poucas vagas' : 'Lotado');
    
    fputcsv($output, [
        $t['nome'],
        $t['classe'] ?? '',
        $t['curso'] ?? '',
        $t['turno'] ?? '',
        $limite,
        $alunos,
        max(0, $vagas),
        $situacao
    ]);
}

fclose($output);
exit;
?>