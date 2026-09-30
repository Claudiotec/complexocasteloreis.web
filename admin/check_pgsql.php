<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnóstico PDO_PGSQL</h2>";

echo "<p>Extensão pdo_pgsql: " . (extension_loaded('pdo_pgsql') ? '✅ Ativa' : '❌ Inativa') . "</p>";
echo "<p>Versão PHP: " . PHP_VERSION . "</p>";

if (extension_loaded('pdo_pgsql')) {
    $info = phpinfo(INFO_MODULES);
    // Extrair versão do libpq
    if (preg_match('/PostgreSQL\(libpq\) Version => ([^\n]+)/', $info, $m)) {
        echo "<p><strong>Versão libpq:</strong> " . htmlspecialchars($m[1]) . "</p>";
    }
    if (preg_match('/PostgreSQL\(libpq\) SSL Library => ([^\n]+)/', $info, $m)) {
        echo "<p><strong>SSL Library:</strong> " . htmlspecialchars($m[1]) . "</p>";
    }
}