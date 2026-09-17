<?php
// ============================================
// modules/escola/notas/add.php - Redireciona para o lançamento real
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Redireciona para o sistema de lançamento real
header('Location: ../../notas.php');
exit;