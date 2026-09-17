<?php
// ============================================
// ajax_gerar_id.php - Gera ID automaticamente via AJAX
// ============================================

require_once '../config/database.php';

try {
    $pdo = conectarBanco();
    
    $ano_atual = date('Y');
    
    // Busca todos os IDs do ano atual
    $stmt = $pdo->prepare("SELECT id FROM alunos WHERE id LIKE ? ORDER BY id DESC");
    $stmt->execute([$ano_atual . '-%']);
    $ids_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($ids_existentes)) {
        echo $ano_atual . '-0001';
        exit;
    }
    
    // Extrai os números
    $numeros = [];
    foreach ($ids_existentes as $id) {
        $partes = explode('-', $id);
        if (count($partes) == 2 && is_numeric($partes[1])) {
            $numeros[] = intval($partes[1]);
        }
    }
    
    if (empty($numeros)) {
        echo $ano_atual . '-0001';
        exit;
    }
    
    sort($numeros);
    
    // Procura a primeira lacuna
    $esperado = 1;
    foreach ($numeros as $num) {
        if ($num > $esperado) {
            break;
        }
        $esperado = $num + 1;
    }
    
    $proximo_numero = $esperado;
    
    if ($proximo_numero > 9999) {
        for ($i = 1; $i <= 9999; $i++) {
            if (!in_array($i, $numeros)) {
                $proximo_numero = $i;
                break;
            }
        }
        if ($proximo_numero > 9999) {
            echo $ano_atual . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            exit;
        }
    }
    
    echo $ano_atual . '-' . str_pad($proximo_numero, 4, '0', STR_PAD_LEFT);
    
} catch (Exception $e) {
    // Em caso de erro, retorna um ID baseado em timestamp
    echo date('Y') . '-' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
}
?>