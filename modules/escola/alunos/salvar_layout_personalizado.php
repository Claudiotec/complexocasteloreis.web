<?php
// ============================================
// modules/escola/alunos/salvar_layout_personalizado.php - Salvar Layout Personalizado
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'editar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// Receber dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

// Validar dados
if (empty($input['nome']) || empty($input['cor_fundo'])) {
    echo json_encode(['success' => false, 'error' => 'Nome e cor de fundo são obrigatórios']);
    exit;
}

// Criar estrutura do layout
$layout = [
    'nome' => $input['nome'],
    'descricao' => $input['descricao'] ?? 'Layout personalizado',
    'cor_fundo' => $input['cor_fundo'],
    'cor_texto' => $input['cor_texto'] ?? '#ffffff',
    'cor_destaque' => $input['cor_destaque'] ?? '#f5d76e',
    'fonte' => $input['fonte'] ?? 'Arial',
    'estilo_foto' => $input['estilo_foto'] ?? 'circle',
    'borda' => $input['borda'] ?? '3mm solid #f5d76e',
    'preview' => $input['preview'] ?? '🎨',
    'url' => $input['url'] ?? '',
    'personalizado' => true,
    'data_criacao' => date('Y-m-d H:i:s')
];

// Carregar layouts existentes
$layouts_file = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/layouts_personalizados.json';
$layouts = [];

if (file_exists($layouts_file)) {
    $json = file_get_contents($layouts_file);
    $layouts = json_decode($json, true) ?? [];
}

// Adicionar novo layout
$layouts[$input['layout']] = $layout;

// Salvar
if (file_put_contents($layouts_file, json_encode($layouts, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'message' => 'Layout salvo com sucesso']);
} else {
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar layout']);
}
exit;
?>