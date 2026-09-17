<?php
// ============================================
// editar_horario.php - Editar Horário
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $disciplina = $_POST['disciplina'] ?? '';
    $funcionario_id = $_POST['funcionario_id'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fim = $_POST['hora_fim'] ?? '';
    $sala = $_POST['sala'] ?? '';
    $is_intervalo = isset($_POST['is_intervalo']) ? 1 : 0;
    $turma_id = $_POST['turma_id'] ?? 0;
    
    if (empty($id)) {
        $_SESSION['erro'] = 'ID do horário não informado';
        header('Location: grade.php?turma=' . $turma_id);
        exit;
    }
    
    try {
        $pdo = conectarBanco();
        
        // Buscar nome do funcionário
        $funcionario_nome = '';
        if (!empty($funcionario_id)) {
            $stmt = $pdo->prepare("SELECT nome FROM funcionarios WHERE id = ?");
            $stmt->execute([$funcionario_id]);
            $funcionario = $stmt->fetch();
            $funcionario_nome = $funcionario['nome'] ?? '';
        }
        
        $stmt = $pdo->prepare("
            UPDATE horarios 
            SET disciplina = ?, 
                funcionario_id = ?, 
                funcionario_nome = ?,
                hora_inicio = ?,
                hora_fim = ?,
                sala = ?,
                is_intervalo = ?
            WHERE id = ?
        ");
        $stmt->execute([$disciplina, $funcionario_id, $funcionario_nome, $hora_inicio, $hora_fim, $sala, $is_intervalo, $id]);
        
        $_SESSION['sucesso'] = 'Horário atualizado com sucesso!';
    } catch (Exception $e) {
        $_SESSION['erro'] = 'Erro ao atualizar horário: ' . $e->getMessage();
    }
    
    header('Location: grade.php?turma=' . $turma_id);
    exit;
}