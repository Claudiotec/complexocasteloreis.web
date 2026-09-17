<?php
// modules/usuarios/excluir.php
// Excluir usuário

require_once '../../config/database.php';
require_once '../../config/functions.php';

// Verificar login
if (!isLoggedIn()) {
    redirect('login.php');
}

// Verificar permissão
if (!temPermissao('Usuarios', 'excluir')) {
    redirect('index.php');
}

$id = $_GET['id'] ?? 0;

// Não permitir excluir próprio usuário
if ($id == $_SESSION['usuario_id']) {
    $_SESSION['mensagem'] = "❌ Não é possível excluir seu próprio usuário!";
    $_SESSION['tipo_mensagem'] = 'danger';
    redirect('modules/usuarios/index.php');
}

try {
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['mensagem'] = "✅ Usuário excluído com sucesso!";
    $_SESSION['tipo_mensagem'] = 'success';
} catch (PDOException $e) {
    $_SESSION['mensagem'] = "❌ Erro ao excluir: " . $e->getMessage();
    $_SESSION['tipo_mensagem'] = 'danger';
}

redirect('modules/usuarios/index.php');
?>