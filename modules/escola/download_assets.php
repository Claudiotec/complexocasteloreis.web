<?php
// download_assets.php - Execute este arquivo uma vez para baixar todos os assets

$assets = [
    'css/bootstrap.min.css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'js/bootstrap.bundle.min.js' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'css/all.min.css' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'js/jquery-3.7.0.min.js' => 'https://code.jquery.com/jquery-3.7.0.min.js'
];

// Criar pastas se não existirem
if (!is_dir('assets/css')) {
    mkdir('assets/css', 0777, true);
}
if (!is_dir('assets/js')) {
    mkdir('assets/js', 0777, true);
}

echo "<h1>Baixando Assets...</h1>\n";

foreach ($assets as $path => $url) {
    echo "Baixando: $url\n<br>";
    
    $content = file_get_contents($url);
    if ($content !== false) {
        file_put_contents($path, $content);
        echo "✅ Salvo em: $path\n<br>";
    } else {
        echo "❌ Erro ao baixar: $url\n<br>";
    }
}

echo "<h2>✅ Download concluído!</h2>";
echo "Todos os arquivos foram salvos na pasta 'assets/'";