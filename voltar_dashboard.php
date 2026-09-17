<?php
// ============================================
// voltar_dashboard.php - Redirecionar para Dashboard do Coordenador
// ============================================

// Verificar se o usuário está logado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar perfil do usuário
$perfil = $_SESSION['usuario_perfil'] ?? '';

// Redirecionar baseado no perfil
if ($perfil == 'coordenador_pedagogico' || $perfil == 'coordenador' || $perfil == 'admin') {
    header('Location: /softgest_web/coordenador_pedagogico/dashboard.php');
} else {
    header('Location: /softgest_web/index.php');
}
exit;
?>