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

$id = $_GET['id'] ?? 0;

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM disciplinas WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['mensagem'] = 'Disciplina removida com sucesso!';
    } catch (Exception $e) {
        $_SESSION['erro'] = $e->getMessage();
    }
}

header('Location: distribuicao.php#tab-disciplinas');
exit;
?>