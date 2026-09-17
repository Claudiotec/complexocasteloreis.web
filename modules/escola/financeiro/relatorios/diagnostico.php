<?php
// Crie: modules/escola/financeiro/relatorios/diagnostico.php

echo "<h2>Diagnóstico de Estrutura de Diretórios</h2>";
echo "<pre>";

echo "DIRETÓRIO ATUAL: " . __DIR__ . "\n";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n\n";

echo "ESTRUTURA DE DIRETÓRIOS:\n";
echo "-----------------------\n";

// Sobe níveis para encontrar config.php
$caminhos = [
    __DIR__ . '/../../../../../config/config.php',
    __DIR__ . '/../../../../config/config.php',
    __DIR__ . '/../../../config/config.php',
    __DIR__ . '/../../config/config.php',
    __DIR__ . '/../config/config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/config/config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/config/config.php',
    '../config/config.php'
];

foreach ($caminhos as $caminho) {
    echo "Testando: " . $caminho . "\n";
    echo "Existe? " . (file_exists($caminho) ? "SIM" : "NÃO") . "\n";
    echo "-----------------------\n";
}

echo "\nCONTEÚDO DO DIRETÓRIO ATUAL:\n";
print_r(scandir(__DIR__));

echo "\nCONTEÚDO DO DIRETÓRIO PAI:\n";
print_r(scandir(dirname(__DIR__)));

echo "\nCONTEÚDO DO DIRETÓRIO RAIZ DO PROJETO:\n";
$projeto_root = dirname(__DIR__, 5);
if (file_exists($projeto_root)) {
    print_r(scandir($projeto_root));
}

echo "</pre>";
?>