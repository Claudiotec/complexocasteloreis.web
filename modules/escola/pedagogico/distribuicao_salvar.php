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
// modules/escola/pedagogico/distribuicao_salvar.php - Salvar Distribuição
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

$tipo = $_POST['tipo'] ?? 'PROFESSOR';
$professor_id = $_POST['professor_id'] ?? '';
$ano_letivo = $_POST['ano_letivo'] ?? date('Y') . '/' . (date('Y') + 1);

$erro = '';
$sucesso = '';

// Buscar nome do professor
$professor_nome = '';
try {
    $stmt = $pdo->prepare("SELECT nome FROM professores WHERE id = ?");
    $stmt->execute([$professor_id]);
    $professor = $stmt->fetch();
    if ($professor) {
        $professor_nome = $professor['nome'];
    }
} catch (Exception $e) {}

if ($tipo == 'PROFESSOR') {
    $classe = $_POST['classe'] ?? '';
    $turmas = $_POST['turmas'] ?? [];
    $disciplinas = $_POST['disciplinas'] ?? [];
    
    if (empty($professor_id) || empty($classe) || empty($turmas) || empty($disciplinas)) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        try {
            $disciplinas_str = implode(', ', $disciplinas);
            
            foreach ($turmas as $turma) {
                list($turma_id, $turma_nome) = explode('|', $turma);
                
                // Verificar se já existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM distribuicao_professores WHERE professor_id = ? AND turma_id = ? AND ano_letivo = ?");
                $stmt->execute([$professor_id, $turma_id, $ano_letivo]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO distribuicao_professores 
                        (professor_id, professor_nome, turma_id, turma_nome, classe, disciplinas, ano_letivo, tipo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$professor_id, $professor_nome, $turma_id, $turma_nome, $classe, $disciplinas_str, $ano_letivo, $tipo]);
                }
            }
            $sucesso = 'Distribuição adicionada com sucesso!';
        } catch (Exception $e) {
            $erro = $e->getMessage();
        }
    }
} elseif ($tipo == 'COORDENADOR') {
    $item = $_POST['item'] ?? '';
    $tipo_coordenacao = $_POST['tipo_coordenacao'] ?? '';
    
    if (empty($professor_id) || empty($item) || empty($tipo_coordenacao)) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO distribuicao_professores 
                (professor_id, professor_nome, turma_id, turma_nome, classe, disciplinas, ano_letivo, tipo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $professor_id, 
                $professor_nome, 
                '', 
                '', 
                $tipo_coordenacao, 
                $item, 
                $ano_letivo, 
                $tipo
            ]);
            $sucesso = 'Coordenador adicionado com sucesso!';
        } catch (Exception $e) {
            $erro = $e->getMessage();
        }
    }
} elseif ($tipo == 'DIRETOR_TURMA') {
    $turma = $_POST['turma_id'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    $classe = $_POST['classe_filtro'] ?? '';
    
    if (empty($professor_id) || empty($turma)) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        list($turma_id, $turma_nome) = explode('|', $turma);
        
        try {
            // Verificar se já existe diretor para esta turma
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM distribuicao_professores WHERE turma_id = ? AND tipo = 'DIRETOR_TURMA' AND ano_letivo = ?");
            $stmt->execute([$turma_id, $ano_letivo]);
            
            if ($stmt->fetchColumn() > 0) {
                // Perguntar se deseja substituir
                echo "<script>
                    if (confirm('Já existe um Diretor de Turma para esta turma.\\nDeseja substituir?')) {
                        window.location.href = 'distribuicao_substituir.php?turma_id={$turma_id}&ano={$ano_letivo}&professor_id={$professor_id}&professor_nome={$professor_nome}&turma_nome={$turma_nome}&classe={$classe}&observacoes=" . urlencode($observacoes) . "';
                    } else {
                        window.location.href = 'distribuicao.php';
                    }
                </script>";
                exit;
            } else {
                $disciplinas_str = 'Diretor de Turma';
                if ($observacoes) {
                    $disciplinas_str .= ' - ' . $observacoes;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO distribuicao_professores 
                    (professor_id, professor_nome, turma_id, turma_nome, classe, disciplinas, ano_letivo, tipo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$professor_id, $professor_nome, $turma_id, $turma_nome, $classe, $disciplinas_str, $ano_letivo, $tipo]);
                $sucesso = 'Diretor de Turma adicionado com sucesso!';
            }
        } catch (Exception $e) {
            $erro = $e->getMessage();
        }
    }
}

// Redirecionar com mensagem
$param = $sucesso ? 'success=' . urlencode($sucesso) : 'error=' . urlencode($erro);
header('Location: distribuicao.php?' . $param);
exit;
?>