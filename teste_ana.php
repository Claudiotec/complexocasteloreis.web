<?php
// ============================================
// teste_ana.php - Teste específico para Ana Rodrigues
// ============================================

require_once 'config/database.php';
require_once 'config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    echo "❌ Usuário não está logado!<br>";
    echo "<a href='login.php'>Fazer login</a>";
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

echo "<h1>🔍 TESTE PARA ANA RODRIGUES</h1>";
echo "<hr>";

// ===== 1. DADOS DO USUÁRIO =====
echo "<h2>1. Dados do Usuário Logado</h2>";
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if ($usuario) {
    echo "<pre>";
    print_r($usuario);
    echo "</pre>";
    echo "ID: " . $usuario['id'] . "<br>";
    echo "Nome: " . $usuario['nome'] . "<br>";
    echo "Email: " . $usuario['email'] . "<br>";
    echo "Perfil: " . $usuario['perfil'] . "<br>";
} else {
    echo "❌ Usuário não encontrado!";
    exit;
}

echo "<hr>";

// ===== 2. BUSCAR PROFESSOR PELO NOME =====
echo "<h2>2. Buscar Professor pelo Nome: '" . $usuario['nome'] . "'</h2>";

$stmt = $pdo->prepare("
    SELECT * FROM professores 
    WHERE professor_nome = ? 
    LIMIT 1
");
$stmt->execute([$usuario['nome']]);
$professor = $stmt->fetch();

if ($professor) {
    echo "✅ Professor encontrado!<br>";
    echo "<pre>";
    print_r($professor);
    echo "</pre>";
    echo "ID: " . $professor['id'] . "<br>";
    echo "Professor ID: " . $professor['professor_id'] . "<br>";
    echo "Nome: " . $professor['professor_nome'] . "<br>";
    echo "Tipo: " . $professor['tipo'] . "<br>";
    echo "Turma: " . $professor['turma_nome'] . "<br>";
    echo "Classe: " . $professor['classe'] . "<br>";
    
    // Salvar na sessão
    $_SESSION['professor_id'] = $professor['professor_id'] ?? $professor['id'];
    $_SESSION['professor_nome'] = $professor['professor_nome'];
    $_SESSION['professor_dados'] = $professor;
    $_SESSION['is_professor'] = true;
    
    echo "<br>✅ Sessão atualizada!<br>";
    echo "professor_id na sessão: " . $_SESSION['professor_id'] . "<br>";
    echo "is_professor: " . ($_SESSION['is_professor'] ? 'true' : 'false') . "<br>";
    
    echo "<br><a href='modules/escola/professor_dashboard.php' style='display:inline-block;padding:10px 20px;background:#c9a84c;color:#1a2332;text-decoration:none;border-radius:8px;font-weight:bold;'>Ir para Painel do Professor →</a>";
    
} else {
    echo "❌ Nenhum professor encontrado com o nome: " . $usuario['nome'] . "<br>";
    
    // Buscar todos os professores
    echo "<h3>Todos os professores cadastrados:</h3>";
    $stmt = $pdo->query("SELECT id, professor_id, professor_nome, turma_nome, classe, tipo FROM professores");
    $todos = $stmt->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Professor ID</th><th>Nome</th><th>Turma</th><th>Classe</th><th>Tipo</th></tr>";
    foreach ($todos as $p) {
        echo "<tr>";
        echo "<td>" . $p['id'] . "</td>";
        echo "<td>" . $p['professor_id'] . "</td>";
        echo "<td>" . $p['professor_nome'] . "</td>";
        echo "<td>" . $p['turma_nome'] . "</td>";
        echo "<td>" . $p['classe'] . "</td>";
        echo "<td>" . $p['tipo'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// ===== 3. VERIFICAR SESSÃO =====
echo "<h2>3. Sessão Atual</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";

// ===== 4. REDIRECIONAR MANUALMENTE =====
echo "<h2>4. Redirecionar Manualmente</h2>";
echo "<a href='modules/escola/professor_dashboard.php' style='display:inline-block;padding:15px 30px;background:#c9a84c;color:#1a2332;text-decoration:none;border-radius:8px;font-weight:bold;font-size:18px;'>📚 Ir para Painel do Professor</a>";