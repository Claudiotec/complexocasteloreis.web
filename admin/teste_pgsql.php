<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== Drivers PDO disponíveis ===\n";
print_r(PDO::getAvailableDrivers());

echo "\n=== Extensões ===\n";
echo "pdo_pgsql: " . (extension_loaded('pdo_pgsql') ? 'SIM ✅' : 'NÃO ❌') . "\n";
echo "pgsql:     " . (extension_loaded('pgsql')     ? 'SIM ✅' : 'NÃO ❌') . "\n";

echo "\n=== Versão da libpq ===\n";
echo pg_version()['client'] ?? 'desconhecido';
echo "\n";

// URL do Neon
$url = 'postgresql://neondb_owner:npg_xKFNESzC5pt2@ep-aged-paper-b4jtvclh-pooler.c-6.us-east-2.aws.neon.tech/neondb?sslmode=require';
$p = parse_url($url);
$host = $p['host'];
$endpoint = explode('.', $host)[0];

echo "\n=== Endpoint ID extraído ===\n";
echo "$endpoint\n";

echo "\n=== Teste de conexão ===\n";
$dsn = "pgsql:host=$host;port=5432;dbname=" . ltrim($p['path'], '/') . ";sslmode=require;options='endpoint=$endpoint'";

try {
    $pdo = new PDO($dsn, $p['user'], $p['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 15,
    ]);
    echo "✅ Conectado ao Neon com sucesso!\n";
    $n = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public'")->fetchColumn();
    echo "Tabelas no schema public: $n\n";

    $tabelas = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
    echo "\nPrimeiras 10 tabelas:\n";
    foreach ($tabelas as $t) echo "  - $t\n";
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}