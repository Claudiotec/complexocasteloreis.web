<?php
require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: text/html; charset=utf-8');
echo '<style>body{font-family:monospace;padding:20px;background:#f8fafc;} .box{background:white;padding:15px;border-radius:8px;margin-bottom:15px;border-left:4px solid #3b82f6;} .ok{color:#16a34a;font-weight:bold;} .erro{color:#dc2626;font-weight:bold;} pre{background:#1a2a3a;color:#fff;padding:15px;border-radius:6px;}</style>';

echo '<h1>🔍 Diagnóstico</h1>';

// 1. Sessão
echo '<div class="box"><h3>1. Sessão atual</h3>';
echo '<pre>' . print_r($_SESSION, true) . '</pre></div>';

// 2. Utilizador
$uid = $_SESSION['usuario_id'] ?? 0;
echo '<div class="box"><h3>2. Utilizador (ID da sessão = ' . $uid . ')</h3>';

$pdo = conectarBanco();
$stmt = $pdo->prepare("SELECT id, nome, email, cargo FROM funcionarios WHERE id = ? LIMIT 1");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo '<pre>' . print_r($user, true) . '</pre>';
    $cargo = strtolower(trim($user['cargo'] ?? ''));
    $is_professor = strpos($cargo, 'professor') !== false;
    $is_admin = strpos($cargo, 'admin') !== false || strpos($cargo, 'gestor') !== false || strpos($cargo, 'diretor') !== false;
    echo '<p><strong>É professor?</strong> ' . ($is_professor ? '<span class="erro">SIM</span>' : '<span class="ok">NÃO</span>') . '</p>';
    echo '<p><strong>É admin?</strong> ' . ($is_admin ? '<span class="ok">SIM</span>' : '<span class="erro">NÃO</span>') . '</p>';
    echo '<p><strong>PODE MOVER?</strong> ' . ((!$is_professor) || $is_admin ? '<span class="erro">SIM (deveria bloquear!)</span>' : '<span class="ok">NÃO (bloqueado corretamente)</span>') . '</p>';
} else {
    echo '<p class="erro">❌ Nenhum funcionário com ID = ' . $uid . '</p>';
    echo '<p>O ID da sessão <strong>não corresponde</strong> ao ID em <code>funcionarios</code>.</p>';
}
echo '</div>';

// 3. Verificar ficheiro mover_horario.php
echo '<div class="box"><h3>3. Ficheiro mover_horario.php</h3>';
$file = __DIR__ . '/mover_horario.php';
if (file_exists($file)) {
    $content = file_get_contents($file);
    $tem = strpos($content, 'is_professor') !== false;
    echo '<p><strong>Existe:</strong> <span class="ok">✅</span></p>';
    echo '<p><strong>Contém verificação "is_professor":</strong> ' . ($tem ? '<span class="ok">✅ SIM (versão nova)</span>' : '<span class="erro">❌ NÃO (versão antiga!)</span>') . '</p>';
    echo '<p><strong>Tamanho:</strong> ' . filesize($file) . ' bytes</p>';
    echo '<p><strong>Modificado:</strong> ' . date('d/m/Y H:i:s', filemtime($file)) . '</p>';
} else {
    echo '<p class="erro">❌ Ficheiro não existe</p>';
}
echo '</div>';

echo '<p style="color:red;font-weight:bold;">⚠️ Apague este ficheiro depois!</p>';