<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h2>Diagnóstico libpq</h2>";
if (function_exists('pg_version')) {
    $v = pg_version();
    echo "<p><strong>Versão libpq (client):</strong> " . htmlspecialchars($v['client'] ?? 'Desconhecida') . "</p>";
    if (version_compare($v['client'] ?? '0', '14', '<')) {
        echo "<p style='color:red;font-weight:bold;'>❌ libpq MUITO ANTIGO! Precisa ser >= 14 para suportar SNI.</p>";
    } else {
        echo "<p style='color:green;font-weight:bold;'>✅ libpq está OK (>= 14).</p>";
    }
} else {
    echo "<p>Extensão pgsql não carregada.</p>";
}