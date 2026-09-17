<?php
// modules/escola/index.php
// Ou esta versão (mais robusta):
require_once(__DIR__ . '/../includes/verificar_permissao_escola.php');

$permissoes = verificarMultiplasPermissoesEscola('escola', ['visualizar', 'criar', 'editar', 'excluir']);

if ($permissoes['visualizar']) {
    // Mostra conteúdo
}

if ($permissoes['criar']) {
    // Mostra botão criar
}
?>



<?php
// =============================================
// delete.php - Excluir pagamento
// Versão com conexão direta
// =============================================

// Iniciar sessão
session_start();

// =============================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// AJUSTE AQUI COM OS DADOS DO SEU SISTEMA!
// =============================================
$host = 'localhost';        // ou o IP do servidor MySQL
$user = 'root';             // usuário do banco
$password = 'Claudtec';             // senha do banco
$database = 'softgest_db';     // nome do banco de dados

// Conectar ao banco
$conn = mysqli_connect($host, $user, $password, $database);

// Verificar conexão
if (!$conn) {
    die("Erro de conexão: " . mysqli_connect_error());
}

// Verificar se usuário está logado (opcional - ajuste conforme seu sistema)
if (!isset($_SESSION['usuario_id'])) {
    // Se tiver sistema de login, descomente abaixo
    // header('Location: ../../../login.php');
    // exit;
}

// =============================================
// PROCESSAR EXCLUSÃO
// =============================================

// Verificar se o ID foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: listar.php?erro=ID do pagamento não informado');
    exit;
}

// Sanitizar o ID
$id = intval($_GET['id']);

// Verificar se o pagamento existe
$sql_check = "SELECT * FROM pagamentos WHERE id = $id";
$result_check = mysqli_query($conn, $sql_check);

if (!$result_check || mysqli_num_rows($result_check) == 0) {
    header('Location: listar.php?erro=Pagamento não encontrado');
    exit;
}

// =============================================
// EXCLUIR O REGISTRO
// =============================================
$sql_delete = "DELETE FROM pagamentos WHERE id = $id";

if (mysqli_query($conn, $sql_delete)) {
    header('Location: listar.php?sucesso=Pagamento excluído com sucesso');
} else {
    header('Location: listar.php?erro=Erro ao excluir: ' . mysqli_error($conn));
}

// Fechar conexão
mysqli_close($conn);
exit;
?>