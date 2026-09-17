<?php
echo "<h2>Teste de Fuso Horário</h2>";

// Mostrar configurações atuais
echo "<p><strong>date_default_timezone_get():</strong> " . date_default_timezone_get() . "</p>";
echo "<p><strong>ini_get('date.timezone'):</strong> " . ini_get('date.timezone') . "</p>";
echo "<p><strong>Hora atual (sem configurar):</strong> " . date('Y-m-d H:i:s') . "</p>";

// Configurar Angola
date_default_timezone_set('Africa/Luanda');
echo "<p><strong>Após configurar Africa/Luanda:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Tentar outro método
putenv('TZ=Africa/Luanda');
echo "<p><strong>Com putenv('TZ=Africa/Luanda'):</strong> " . date('Y-m-d H:i:s') . "</p>";

// Mostrar diferença
echo "<p><strong>Fuso atual:</strong> " . date_default_timezone_get() . "</p>";

// Lista de fusos disponíveis para Angola
echo "<p><strong>Fusos para Angola:</strong></p>";
$angola_fusos = ['Africa/Luanda', 'Africa/Lagos', 'Africa/Kinshasa', 'Africa/Windhoek'];
foreach ($angola_fusos as $fuso) {
    date_default_timezone_set($fuso);
    echo "<p>$fuso: " . date('H:i:s') . "</p>";
}
?>