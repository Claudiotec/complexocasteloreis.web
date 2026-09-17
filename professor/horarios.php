<?php
// ============================================
// professor/horarios.php
// Redireciona para o módulo de horários da escola
// ============================================

require_once '../config/database.php';
require_once '../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// Redirecionar para a grade horária
header('Location: ' . SITE_URL . 'modules/escola/horarios/grade.php');
exit;