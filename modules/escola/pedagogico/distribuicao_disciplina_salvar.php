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
// ============================================
// modules/escola/pedagogico/distribuicao_disciplina_salvar.php - Salvar Disciplina
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'criar')) {
    header('Location: ' . SITE_URL);
    exit;
}

$nome = $_POST['nome'] ?? '';
$descricao = $_POST['descricao'] ?? '';
$professor_id = $_POST['professor_id'] ?? null;
$carga_horaria = $_POST['carga_horaria'] ?? null;

$erro = '';
$sucesso = '';

if (empty($nome)) {
    $erro = 'O nome da disciplina é obrigatório!';
} else {
    try {
        // Verificar se a tabela disciplinas existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'disciplinas'");
        if ($stmt->rowCount() == 0) {
            // Criar tabela disciplinas
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS disciplinas (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    nome VARCHAR(100) NOT NULL,
                    descricao TEXT,
                    professor_id INT,
                    carga_horaria INT,
                    status ENUM('ativa','inativa') DEFAULT 'ativa',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }
        
        // Verificar se já existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM disciplinas WHERE nome = ?");
        $stmt->execute([$nome]);
        if ($stmt->fetchColumn() > 0) {
            $erro = "Já existe uma disciplina com o nome '$nome'!";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO disciplinas (nome, descricao, professor_id, carga_horaria, status)
                VALUES (?, ?, ?, ?, 'ativa')
            ");
            $stmt->execute([$nome, $descricao, $professor_id, $carga_horaria]);
            $sucesso = "Disciplina '$nome' cadastrada com sucesso!";
        }
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Redirecionar
$param = $sucesso ? 'success=' . urlencode($sucesso) : 'error=' . urlencode($erro);
header('Location: distribuicao.php?' . $param . '#tab-disciplinas');
exit;
?>