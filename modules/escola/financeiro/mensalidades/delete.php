<?php
// ============================================
// modules/escola/financeiro/mensalidades/delete.php
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== VERIFICAR ID =====
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?erro=ID não informado');
    exit;
}

$id = intval($_GET['id']);

// ===== VERIFICAR SE O REGISTRO EXISTE =====
try {
    $stmt = $pdo->prepare("SELECT id, aluno_id, mes, ano FROM mensalidades WHERE id = ?");
    $stmt->execute([$id]);
    $mensalidade = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mensalidade) {
        header('Location: index.php?erro=Registro não encontrado');
        exit;
    }
    
    // ===== EXCLUIR REGISTRO =====
    $stmt = $pdo->prepare("DELETE FROM mensalidades WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: index.php?sucesso=Mensalidade excluída com sucesso');
    exit;
    
} catch (Exception $e) {
    header('Location: index.php?erro=Erro ao excluir: ' . $e->getMessage());
    exit;
}
?>