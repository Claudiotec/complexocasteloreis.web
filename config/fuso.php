<?php
// config/fuso.php - Configuração de fuso horário para Angola

// Método 1: Configurar diretamente
date_default_timezone_set('Africa/Luanda');

// Método 2: Forçar via variável de ambiente
putenv('TZ=Africa/Luanda');

// Método 3: Verificar se funcionou
if (date_default_timezone_get() !== 'Africa/Luanda') {
    // Se não funcionou, tentar UTC+1
    date_default_timezone_set('Etc/GMT-1');
}

// Definir para o MySQL também
try {
    global $pdo;
    if (isset($pdo)) {
        $pdo->exec("SET time_zone = '+1:00'");
    }
} catch (Exception $e) {
    // Ignorar erro se não conseguir conectar
}

// Retornar a hora atual de Angola
function getHoraAngola() {
    return date('H:i:s');
}

function getDataAngola() {
    return date('Y-m-d');
}

function getDataHoraAngola() {
    return date('Y-m-d H:i:s');
}

// Constante com fuso horário
define('FUSO_ANGOLA', 'Africa/Luanda');
define('UTC_ANGOLA', '+1:00');
?>