<?php
// ============================================
// modules/escola/alunos/exportar_todas_listas_transporte.php - Exportar Todas Listas Transporte
// ============================================

require_once '../../../config/app_modes.php';
require_once '../../../config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para VISUALIZAR
bloquearAcesso('visualizar');

// ============================================
// 1. REDIRECIONAR PARA A PÁGINA PRINCIPAL
// ============================================
$filtros = $_GET;
$query = http_build_query($filtros);

// Redireciona para a página principal com os filtros
header('Location: listas_pagamento_transporte.php?' . $query);
exit;
?>