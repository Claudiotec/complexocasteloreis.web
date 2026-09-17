<?php
// ============================================
// teste_foto.php - Debug de Fotos
// ============================================

echo "<h1>🔍 Debug de Fotos dos Alunos</h1>";

$id_aluno = $_GET['id'] ?? 67;
echo "<h2>Buscando foto para o aluno ID: $id_aluno</h2>";

// Conectar ao banco
require_once 'config/database.php';
require_once 'config/app_modes.php';

try {
    $pdo = conectarBanco();
    $stmt = $pdo->prepare("SELECT id, nome, foto FROM alunos WHERE id = ?");
    $stmt->execute([$id_aluno]);
    $aluno = $stmt->fetch();
    
    if ($aluno) {
        echo "<h3>Dados do aluno:</h3>";
        echo "<pre>";
        print_r($aluno);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}

echo "<hr>";

// Possíveis diretórios
$diretorios = [
    'C:/xampp/htdocs/softgest_web/uploads/alunos/',
    'C:/xampp/htdocs/softgest_web/uploads/fotos_alunos/',
    $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/alunos/',
    $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/'
];

foreach ($diretorios as $dir) {
    echo "<h3>📁 Diretório: $dir</h3>";
    
    if (is_dir($dir)) {
        echo "✅ Diretório existe<br>";
        
        // Listar arquivos
        $arquivos = scandir($dir);
        $arquivos = array_diff($arquivos, ['.', '..']);
        
        if (empty($arquivos)) {
            echo "📭 Nenhum arquivo encontrado<br>";
        } else {
            echo "<ul>";
            foreach ($arquivos as $arquivo) {
                // Destacar arquivos que correspondem ao ID
                $is_match = (strpos($arquivo, (string)$id_aluno) !== false);
                $style = $is_match ? 'style="color: green; font-weight: bold;"' : '';
                echo "<li $style>$arquivo " . ($is_match ? '✅ MATCH!' : '') . "</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "❌ Diretório NÃO existe<br>";
    }
    echo "<hr>";
}

echo "<h2>📸 Teste de caminhos para o aluno $id_aluno:</h2>";
$extensoes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

foreach ($diretorios as $dir) {
    foreach ($extensoes as $ext) {
        // Teste 1: ID.extensao
        $caminho = $dir . $id_aluno . '.' . $ext;
        $existe = file_exists($caminho) ? '✅' : '❌';
        echo "$existe $caminho<br>";
        
        // Teste 2: aluno_ID.extensao
        $caminho2 = $dir . 'aluno_' . $id_aluno . '.' . $ext;
        $existe2 = file_exists($caminho2) ? '✅' : '❌';
        echo "$existe2 $caminho2<br>";
        
        // Teste 3: aluno_ID_*.extensao
        $caminho3 = $dir . 'aluno_' . $id_aluno . '_*.' . $ext;
        $arquivos = glob($caminho3);
        $existe3 = !empty($arquivos) ? '✅' : '❌';
        echo "$existe3 $caminho3 " . ($existe3 ? '→ ' . basename($arquivos[0]) : '') . "<br>";
    }
    echo "<br>";
}
?>