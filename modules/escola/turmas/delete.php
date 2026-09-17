<?php
// ============================================
// modules/escola/turmas/delete.php - Excluir Turma
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'excluir') && !temPermissao('Escola', 'visualizar')) {
    $_SESSION['mensagem'] = "❌ Você não tem permissão para excluir turmas.";
    $_SESSION['mensagem_tipo'] = 'error';
    header('Location: index.php');
    exit;
}

// ⚠️ Aceitar id=0 (existe no banco)
if (!isset($_GET['id']) || $_GET['id'] === '') {
    $_SESSION['mensagem'] = "❌ ID não informado.";
    $_SESSION['mensagem_tipo'] = 'error';
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

try {
    // Buscar a turma
    $stmt = $pdo->prepare("SELECT * FROM turmas WHERE id = ?");
    $stmt->execute([$id]);
    $turma = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turma) {
        $_SESSION['mensagem'] = "❌ Turma com ID {$id} não encontrada.";
        $_SESSION['mensagem_tipo'] = 'error';
        header('Location: index.php');
        exit;
    }

    // Verificar se há alunos ativos nesta turma
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE TURMA = ? AND status = 'ativo'");
    $stmtCheck->execute([$turma['nome']]);
    $totalAlunos = (int)$stmtCheck->fetchColumn();

    if ($totalAlunos > 0) {
        $_SESSION['mensagem'] = "⚠️ Não é possível excluir a turma '{$turma['nome']}': existem {$totalAlunos} aluno(s) ativo(s) matriculado(s).";
        $_SESSION['mensagem_tipo'] = 'error';
        header('Location: index.php');
        exit;
    }

    // Excluir
    $stmt = $pdo->prepare("DELETE FROM turmas WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['mensagem'] = "✅ Turma '{$turma['nome']}' excluída com sucesso!";
        $_SESSION['mensagem_tipo'] = 'success';
    } else {
        $_SESSION['mensagem'] = "⚠️ Nenhum registro foi excluído (ID {$id}).";
        $_SESSION['mensagem_tipo'] = 'error';
    }

} catch (Exception $e) {
    $_SESSION['mensagem'] = "❌ Erro ao excluir turma: " . $e->getMessage();
    $_SESSION['mensagem_tipo'] = 'error';
}

header('Location: index.php');
exit;