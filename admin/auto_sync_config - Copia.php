<?php
// ============================================
// admin/auto_sync_config.php
// Salva e lê a configuração do auto-sync
// ============================================

require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

$arquivo = __DIR__ . '/../auto_sync_config.json';

// ============================================
// LER CONFIGURAÇÃO
// ============================================
if (($_GET['acao'] ?? '') === 'ler') {
    if (!file_exists($arquivo)) {
        echo json_encode([
            'success' => true,
            'ativo' => false,
            'intervalo_minutos' => 2,
            'ultima_execucao' => null,
            'proxima_execucao' => null,
        ]);
        exit;
    }

    $cfg = json_decode(file_get_contents($arquivo), true) ?: [];
    echo json_encode([
        'success' => true,
        'ativo' => (bool)($cfg['ativo'] ?? false),
        'intervalo_minutos' => (int)($cfg['intervalo_minutos'] ?? 2),
        'ultima_execucao' => $cfg['ultima_execucao'] ?? null,
        'proxima_execucao' => $cfg['proxima_execucao'] ?? null,
    ]);
    exit;
}

// ============================================
// SALVAR CONFIGURAÇÃO
// ============================================
if (($_POST['acao'] ?? '') === 'salvar') {
    $ativo = !empty($_POST['ativo']);
    $intervalo = max(1, min(60, (int)($_POST['intervalo_minutos'] ?? 2)));

    $cfg = [
        'ativo' => $ativo,
        'intervalo_minutos' => $intervalo,
        'ultima_execucao' => null,
        'proxima_execucao' => null,
        'atualizado_em' => date('c'),
    ];

    // Preserva última execução se já existia
    if (file_exists($arquivo)) {
        $antigo = json_decode(file_get_contents($arquivo), true) ?: [];
        $cfg['ultima_execucao'] = $antigo['ultima_execucao'] ?? null;
    }

    if ($ativo) {
        $prox = time() + ($intervalo * 60);
        $cfg['proxima_execucao'] = date('c', $prox);
    }

    file_put_contents($arquivo, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => $ativo ? 'Auto-Sync ATIVADO' : 'Auto-Sync DESATIVADO',
        'ativo' => $ativo,
        'intervalo_minutos' => $intervalo,
    ]);
    exit;
}

// ============================================
// REGISTRAR EXECUÇÃO
// ============================================
if (($_POST['acao'] ?? '') === 'registrar_execucao') {
    if (!file_exists($arquivo)) {
        echo json_encode(['success' => false, 'message' => 'Config não existe']);
        exit;
    }

    $cfg = json_decode(file_get_contents($arquivo), true) ?: [];
    $cfg['ultima_execucao'] = date('c');

    if (!empty($cfg['ativo'])) {
        $prox = time() + (($cfg['intervalo_minutos'] ?? 2) * 60);
        $cfg['proxima_execucao'] = date('c', $prox);
    }

    file_put_contents($arquivo, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação inválida']);