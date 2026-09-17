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
// modules/escola/turmas/exportar.php - Exportar Dados (COM IDADES)
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
header('Content-Disposition: attachment; filename=turmas_' . date('Y-m-d') . '.csv');

// Criar arquivo CSV
$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Turma', 'Classe', 'Curso', 'Ano Letivo', 'Turno', 'Sala', 'Idades', 'Limite', 'Alunos', 'Vagas', 'Status', 'Disciplinas']);

foreach($turmas as $t) {
    $vagas = ($t['limite'] ?? 30) - ($t['total_alunos'] ?? 0);
    fputcsv($output, [
        $t['id'],
        $t['nome'],
        $t['classe'] ?? '',
        $t['curso'] ?? '',
        $t['ano_letivo'] ?? '',
        $t['turno'] ?? '',
        $t['sala'] ?? '',
        $t['idades'] ?? '',
        $t['limite'] ?? 30,
        $t['total_alunos'] ?? 0,
        $vagas,
        $t['status'] ?? 'ativa',
        $t['disciplinas'] ?? ''
    ]);
}

fclose($output);
exit;
?>