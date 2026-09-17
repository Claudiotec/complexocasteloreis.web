<?php
// cadastrar_horario.php
require_once('../../config/conexao.php');
require_once('../includes/verificar_permissao_escola.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Receber dados
    $funcionario_id = mysqli_real_escape_string($conn, $_POST['funcionario_id']);
    $dia_semana = mysqli_real_escape_string($conn, $_POST['dia_semana']);
    $hora_entrada = mysqli_real_escape_string($conn, $_POST['hora_entrada']);
    $hora_saida = mysqli_real_escape_string($conn, $_POST['hora_saida']);
    $inicio_intervalo = mysqli_real_escape_string($conn, $_POST['inicio_intervalo']);
    $fim_intervalo = mysqli_real_escape_string($conn, $_POST['fim_intervalo']);
    $turno = mysqli_real_escape_string($conn, $_POST['turno']);
    
    // Validar
    if (empty($funcionario_id) || empty($dia_semana) || empty($hora_entrada) || empty($hora_saida)) {
        die("❌ Todos os campos obrigatórios devem ser preenchidos!");
    }
    
    // Inserir
    $sql = "INSERT INTO horarios 
            (funcionario_id, dia_semana, hora_entrada, hora_saida, inicio_intervalo, fim_intervalo, turno) 
            VALUES 
            ('$funcionario_id', '$dia_semana', '$hora_entrada', '$hora_saida', '$inicio_intervalo', '$fim_intervalo', '$turno')";
    
    if (mysqli_query($conn, $sql)) {
        echo "✅ Horário cadastrado com sucesso!";
        echo "<br><a href='listar.php'>Voltar para lista</a>";
    } else {
        echo "❌ Erro ao cadastrar: " . mysqli_error($conn);
        echo "<br>SQL: $sql";
    }
} else {
    echo "❌ Método não permitido! Use POST.";
}
?>