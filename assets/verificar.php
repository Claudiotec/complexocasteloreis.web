<?php
// ============================================
// VERIFICAÇÃO DE DEPENDÊNCIAS
// ============================================

function verificarDependencias() {
    $base = __DIR__ . '/assets/';
    $arquivos = [
        'css/bootstrap.min.css',
        'css/bootstrap-icons.css',
        'js/jquery-3.6.0.min.js',
        'js/bootstrap.bundle.min.js',
        'js/chart.min.js',
        'js/datatables.min.js'
    ];
    
    $faltando = [];
    foreach ($arquivos as $arquivo) {
        if (!file_exists($base . $arquivo)) {
            $faltando[] = $arquivo;
        }
    }
    
    if (empty($faltando)) {
        echo '<div class="alert alert-success">✅ Todas as dependências estão instaladas!</div>';
        return true;
    } else {
        echo '<div class="alert alert-danger">❌ Arquivos faltando:<br>';
        foreach ($faltando as $f) {
            echo '&nbsp;&nbsp;- ' . $f . '<br>';
        }
        echo '</div>';
        return false;
    }
}
