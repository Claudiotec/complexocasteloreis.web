<?php
// ============================================
// admin/auto_sync_config.php
// ============================================
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$CONFIG_FILE = __DIR__ . '/../auto_sync_config.json';

// ---------- LER ----------
if (($_GET['acao'] ?? '') === 'ler') {
    $cfg = [];
    if (file_exists($CONFIG_FILE)) {
        $json = @file_get_contents($CONFIG_FILE);
        $cfg = json_decode($json, true) ?: [];
    }

    echo json_encode([
        'success' => true,
        'ativo' => $cfg['ativo'] ?? false,
        'intervalo_minutos' => $cfg['intervalo_minutos'] ?? 2,
        'ultima_execucao' => $cfg['ultima_execucao'] ?? null,
        'proxima_execucao' => $cfg['proxima_execucao'] ?? null,
    ]);
    exit;
}

// ---------- SALVAR ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ativo = ($_POST['ativo'] ?? '0') === '1';
    $intervalo = max(1, min(60, (int)($_POST['intervalo_minutos'] ?? 2)));

    $cfg = [
        'ativo' => $ativo,
        'intervalo_minutos' => $intervalo,
        'ultima_execucao' => null,
        'proxima_execucao' => $ativo ? date('c', time() + $intervalo * 60) : null,
        'atualizado_em' => date('c'),
    ];

    @file_put_contents($CONFIG_FILE, json_encode($cfg, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true, 'config' => $cfg]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação inválida']);