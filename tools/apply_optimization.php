<?php
// ============================================
// tools/apply_optimization.php
// Aplicar otimização em todos os arquivos PHP
// ============================================

// ===== CONFIGURAÇÃO =====
$baseDir = __DIR__ . '/../'; // Raiz do projeto
$cacheDir = $baseDir . 'cache/';
$backupDir = $baseDir . 'backups/optimization/';

// Criar diretórios
if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

// ===== ARQUIVOS A OTIMIZAR =====
$patterns = [
    'modules/**/*.php',
    'coordenador_pedagogico/**/*.php',
    'api/**/*.php',
    'includes/**/*.php'
];

// ===== FUNÇÃO PARA OTIMIZAR ARQUIVO =====
function optimizeFile($filePath, $backupDir) {
    echo "🔄 Otimizando: $filePath\n";
    
    // Fazer backup
    $backupPath = $backupDir . str_replace('/', '_', $filePath) . '.bak';
    copy($filePath, $backupPath);
    
    // Ler conteúdo
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // ===== 1. ADICIONAR BOOTSTRAP OTIMIZADO =====
    if (strpos($content, 'bootstrap_optimized.php') === false) {
        $bootstrapLine = "require_once __DIR__ . '/../config/bootstrap_optimized.php';";
        
        // Encontrar onde inserir
        $patterns = [
            'require_once.*config/database.php',
            'require_once.*config/app_modes.php',
            'session_start()',
            '<?php'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern . '/', $content)) {
                $content = preg_replace(
                    '/' . $pattern . '/',
                    "$0\n$bootstrapLine",
                    $content,
                    1
                );
                break;
            }
        }
    }
    
    // ===== 2. ADICIONAR HEADER OTIMIZADO =====
    if (strpos($content, 'header_optimized.php') === false && strpos($content, 'header_escola.php') !== false) {
        $content = str_replace(
            "include '../includes/header_escola.php';",
            "include '../includes/header_optimized.php';",
            $content
        );
    }
    
    // ===== 3. USAR CACHE NAS QUERIES =====
    // Substituir queries diretas por queries com cache
    $content = preg_replace_callback(
        '/\$pdo->query\("SELECT .*?"\)->fetchAll\(\);/',
        function($matches) {
            $query = $matches[0];
            $key = md5($query);
            return "\$cache = \$GLOBALS['cache'];\n" .
                   "\$data = \$cache->get('$key');\n" .
                   "if (\$data === null) {\n" .
                   "    \$data = $query\n" .
                   "    \$cache->set('$key', \$data);\n" .
                   "}\n" .
                   "\$result = \$data;";
        },
        $content
    );
    
    // ===== 4. ADICIONAR COMPRESSÃO GZIP =====
    if (strpos($content, 'zlib.output_compression') === false) {
        $content = preg_replace(
            '/\<\?php/',
            "<?php\nif (!ini_get('zlib.output_compression')) {\n    ini_set('zlib.output_compression', 'On');\n    ini_set('zlib.output_compression_level', '6');\n}\n",
            $content
        );
    }
    
    // ===== 5. ADICIONAR CACHE HEADERS =====
    if (strpos($content, 'header\("Cache-Control"') === false && strpos($content, '<?php') !== false) {
        $content = preg_replace(
            '/\<\?php/',
            "<?php\nheader('Cache-Control: private, max-age=3600, must-revalidate');\nheader('Pragma: private');",
            $content,
            1
        );
    }
    
    // ===== 6. ADICIONAR DETECÇÃO DE REDE LENTA =====
    if (strpos($content, 'isSlowNetwork') === false) {
        $content = preg_replace(
            '/\<\?php/',
            "<?php\nif (isset(\$_COOKIE['slow_network']) && \$_COOKIE['slow_network'] === 'true') {\n    \$isSlowNetwork = true;\n}\n",
            $content,
            1
        );
    }
    
    // Salvar se houve alteração
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "✅ Otimizado: $filePath\n";
        return true;
    } else {
        echo "⏭️  Sem alterações: $filePath\n";
        return false;
    }
}

// ===== FUNÇÃO PARA LISTAR ARQUIVOS =====
function getPhpFiles($baseDir, $patterns) {
    $files = [];
    
    foreach ($patterns as $pattern) {
        $fullPattern = $baseDir . $pattern;
        $matches = glob($fullPattern, GLOB_BRACE);
        
        foreach ($matches as $file) {
            if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $files[] = $file;
            }
        }
    }
    
    return array_unique($files);
}

// ===== EXECUTAR OTIMIZAÇÃO =====
echo "🚀 INICIANDO OTIMIZAÇÃO EM MASSA...\n";
echo "=========================================\n\n";

// Listar arquivos
$files = getPhpFiles($baseDir, $patterns);
$total = count($files);
echo "📁 Encontrados $total arquivos PHP\n\n";

// Otimizar cada arquivo
$optimized = 0;
foreach ($files as $file) {
    if (optimizeFile($file, $backupDir)) {
        $optimized++;
    }
}

// ===== RELATÓRIO FINAL =====
echo "\n=========================================\n";
echo "📊 RELATÓRIO FINAL\n";
echo "=========================================\n";
echo "📁 Total de arquivos: $total\n";
echo "✅ Otimizados: $optimized\n";
echo "⏭️  Sem alterações: " . ($total - $optimized) . "\n";
echo "💾 Backup criado em: $backupDir\n";
echo "\n✅ Otimização concluída!\n";
?>