<?php
// ============================================
// upload_functions.php - Funções para upload de imagens
// ============================================

/**
 * Cria a pasta de fotos se não existir
 */
function criarPastaFotos() {
    $caminho = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/';
    if (!file_exists($caminho)) {
        mkdir($caminho, 0777, true);
    }
    return $caminho;
}

/**
 * Faz o upload da foto do aluno
 */
function uploadFotoAluno($arquivo, $id_aluno) {
    // Validar arquivo
    if (!isset($arquivo) || $arquivo['error'] != 0) {
        return ['success' => false, 'error' => 'Erro no upload do arquivo'];
    }
    
    // Validar tipo
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($arquivo['type'], $tiposPermitidos)) {
        return ['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP.'];
    }
    
    // Validar tamanho (máximo 5MB)
    if ($arquivo['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo 5MB.'];
    }
    
    // Criar pasta
    $pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/';
    if (!file_exists($pasta)) {
        mkdir($pasta, 0777, true);
    }
    
    // Gerar nome do arquivo
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $nome_arquivo = $id_aluno . '.' . $extensao;
    $caminho_completo = $pasta . $nome_arquivo;
    
    // Remover foto antiga se existir
    $arquivosAntigos = glob($pasta . $id_aluno . '.*');
    foreach ($arquivosAntigos as $antigo) {
        if (file_exists($antigo) && is_file($antigo)) {
            unlink($antigo);
        }
    }
    
    // Mover arquivo
    if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
        return [
            'success' => true,
            'nome' => $nome_arquivo,
            'caminho' => 'uploads/fotos_alunos/' . $nome_arquivo,
            'caminho_completo' => $caminho_completo
        ];
    } else {
        return ['success' => false, 'error' => 'Erro ao salvar o arquivo'];
    }
}

/**
 * Remove a foto do aluno
 */
function removerFotoAluno($id_aluno) {
    $pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/';
    $arquivos = glob($pasta . $id_aluno . '.*');
    foreach ($arquivos as $arquivo) {
        if (file_exists($arquivo) && is_file($arquivo)) {
            return unlink($arquivo);
        }
    }
    return false;
}

/**
 * Obtém a URL da foto do aluno
 */
function getFotoAluno($id_aluno, $default = 'default.png') {
    $pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/';
    $arquivos = glob($pasta . $id_aluno . '.*');
    
    if (!empty($arquivos) && file_exists($arquivos[0])) {
        return SITE_URL . 'uploads/fotos_alunos/' . basename($arquivos[0]);
    }
    
    return SITE_URL . 'assets/images/' . $default;
}

/**
 * Verifica se o aluno tem foto
 */
function alunoTemFoto($id_aluno) {
    $pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/';
    $arquivos = glob($pasta . $id_aluno . '.*');
    return !empty($arquivos) && file_exists($arquivos[0]);
}
?>