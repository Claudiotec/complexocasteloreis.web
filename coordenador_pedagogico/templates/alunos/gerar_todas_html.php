<?php
// ============================================
// modules/escola/alunos/gerar_todas_html.php - Gerar Todas HTML
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== BUSCAR CLASSES =====
$classes = [];
try {
    $classes = $pdo->query("
        SELECT DISTINCT Classe 
        FROM alunos 
        WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
        ORDER BY Classe
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// ===== GERAR ARQUIVOS =====
$pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/listas_nominais/';
if (!file_exists($pasta)) {
    mkdir($pasta, 0777, true);
}

$arquivos_gerados = [];
$total = 0;

foreach ($classes as $classe) {
    $url = SITE_URL . 'modules/escola/alunos/gerar_html_classe.php?classe=' . urlencode($classe);
    
    // Baixar o conteúdo
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    curl_close($ch);
    
    if ($html) {
        $nome_arquivo = 'lista_nominal_' . preg_replace('/[^a-zA-Z0-9]/', '_', $classe) . '.html';
        $caminho = $pasta . $nome_arquivo;
        file_put_contents($caminho, $html);
        $arquivos_gerados[] = $nome_arquivo;
        $total++;
    }
}

// ===== REDIRECIONAR =====
$_SESSION['mensagem'] = $total . ' arquivos HTML gerados com sucesso!';
header('Location: listas_nominais.php');
exit;
?>