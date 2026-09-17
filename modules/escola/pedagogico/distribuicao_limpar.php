<?php
// modules/escola/index.php
// Ou esta versão (mais robusta):
require_once(__DIR__ . '/../includes/verificar_permissao_escola.php');

$permissoes = verificarMultiplasPermissoesEscola('escola', ['visualizar', 'criar', 'editar', 'excluir']);

if ($permissoes['visualizar']) {
    // Mostra conteúdo
}

if ($permissoes['criar']) {
    // Mostra botão criar
}
?>




<?php
require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id']) || !temPermissao('Escola', 'excluir')) {
    header('Location: ' . SITE_URL);
    exit;
}

$confirmar = $_GET['confirmar'] ?? 0;

if ($confirmar == 1) {
    try {
        $pdo->exec("DELETE FROM distribuicao_professores");
        $_SESSION['mensagem'] = 'Todos os dados foram removidos com sucesso!';
    } catch (Exception $e) {
        $_SESSION['erro'] = $e->getMessage();
    }
}

header('Location: distribuicao.php');
exit;
?>