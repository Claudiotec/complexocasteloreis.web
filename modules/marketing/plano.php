<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $objetivo = $_POST['objetivo'];
    $publico_alvo = $_POST['publico_alvo'];
    $orcamento = $_POST['orcamento'];
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    
    $stmt = $pdo->prepare("INSERT INTO plano_marketing (titulo, descricao, objetivo, publico_alvo, orcamento, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$titulo, $descricao, $objetivo, $publico_alvo, $orcamento, $data_inicio, $data_fim]);
    
    header("Location: index.php?success=1");
    exit;
}
?>

<div class="container">
    <h2>📊 Plano de Marketing</h2>
    
    <form method="POST">
        <div class="form-group">
            <label>Título:</label>
            <input type="text" name="titulo" required>
        </div>
        
        <div class="form-group">
            <label>Descrição:</label>
            <textarea name="descricao" rows="3" required></textarea>
        </div>
        
        <div class="form-group">
            <label>Objetivo:</label>
            <textarea name="objetivo" rows="3" required></textarea>
        </div>
        
        <div class="form-group">
            <label>Público Alvo:</label>
            <input type="text" name="publico_alvo">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Orçamento:</label>
                <input type="number" step="0.01" name="orcamento" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Data Início:</label>
                <input type="date" name="data_inicio" required>
            </div>
            <div class="form-group">
                <label>Data Fim:</label>
                <input type="date" name="data_fim" required>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Salvar Plano</button>
        <a href="index.php" class="btn">Cancelar</a>
    </form>
</div>
