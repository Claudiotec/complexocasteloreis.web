<?php
// ============================================
// lancamento_notas.php - Lançamento de Notas
// Local: C:\xampp\htdocs\softgest_web\professor\
// ============================================

// Definição do caminho base
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Dados do usuário logado
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Professor';
$usuario_email = $_SESSION['usuario_email'] ?? '';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$escola_nome = $_SESSION['escola_nome'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';

// Buscar dados do professor (disciplinas, turmas, classes)
$professor_dados = [];
try {
    // Buscar funcionário
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ? OR email = ?");
    $stmt->execute([$usuario_id, $usuario_email]);
    $professor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($professor) {
        // Buscar disciplinas do professor
        $stmt = $pdo->prepare("SELECT DISTINCT disciplina_lecciona FROM funcionarios WHERE id = ? AND disciplina_lecciona IS NOT NULL AND disciplina_lecciona != ''");
        $stmt->execute([$professor['id']]);
        $disciplinas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Buscar turmas do professor
        $stmt = $pdo->prepare("SELECT DISTINCT TURMA FROM alunos WHERE TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA");
        $stmt->execute();
        $turmas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Buscar classes
        $stmt = $pdo->prepare("SELECT DISTINCT Classe FROM alunos WHERE Classe IS NOT NULL AND Classe != '' ORDER BY Classe");
        $stmt->execute();
        $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $professor_dados = [
            'disciplinas' => $disciplinas ?: ['Matemática', 'Português', 'História'],
            'turmas' => $turmas ?: ['A', 'B', 'C'],
            'classes' => $classes ?: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']
        ];
    }
} catch (Exception $e) {
    error_log("Erro ao buscar dados do professor: " . $e->getMessage());
    // Dados padrão se não conseguir buscar
    $professor_dados = [
        'disciplinas' => ['Matemática', 'Português', 'História', 'Ciências'],
        'turmas' => ['A', 'B', 'C'],
        'classes' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']
    ];
}

include $base_path . '/modules/escola/includes/header_escola.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($escola_nome) ?> - Lançamento de Notas</title>
    <style>
        /* ============================================
           ESTILOS COMPLETOS
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f8f9fa;
            overflow-x: auto;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            min-width: 1200px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 600;
            white-space: nowrap;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: background-color 0.3s;
            white-space: nowrap;
            color: white;
        }
        .btn-print-mini { background-color: #8e44ad; }
        .btn-print-mini:hover { background-color: #6c3483; }
        .btn-print { background-color: #e67e22; }
        .btn-print:hover { background-color: #d35400; }
        .btn-back { background-color: #27ae60; }
        .btn-back:hover { background-color: #219653; }
        .btn-save { background: linear-gradient(135deg, #3498db, #2980b9); }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(52,152,219,0.3); }
        .btn-export { background-color: #9b59b6; }
        .btn-export:hover { background-color: #8e44ad; }
        .btn-clear { background-color: #95a5a6; }
        .btn-clear:hover { background-color: #7f8c8d; }
        
        .container {
            padding: 25px;
            min-width: 1200px;
            margin: 0 auto;
            overflow-x: visible;
            width: 100%;
        }
        .filters {
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            min-width: 1200px;
            width: 100%;
        }
        .filter-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            width: 100%;
        }
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
        }
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            transition: border-color 0.3s;
        }
        .filter-group select:focus, .filter-group input:focus {
            border-color: #3498db;
            outline: none;
        }
        .filter-group select:disabled {
            background-color: #f5f7fa;
            color: #95a5a6;
            cursor: not-allowed;
        }
        .btn-search {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            padding: 11px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: transform 0.2s, box-shadow 0.2s;
            min-width: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-search:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        .btn-search:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .notes-table-wrapper {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-top: 20px;
            overflow-x: auto;
            width: 100%;
            min-width: 1200px;
            max-height: 75vh;
        }
        .notes-table-wrapper::-webkit-scrollbar {
            height: 10px;
            width: 10px;
        }
        .notes-table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 6px;
        }
        .notes-table-wrapper::-webkit-scrollbar-thumb {
            background: #3498db;
            border-radius: 6px;
            border: 2px solid #f1f1f1;
        }
        .notes-table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #2980b9;
        }
        
        .notes-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
            font-size: 13px;
        }
        th {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 14px 12px;
            text-align: center;
            font-weight: 600;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
            font-size: 13px;
        }
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #eef1f5;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            font-size: 13px;
        }
        tr:hover {
            background-color: #f8fafc;
        }
        tr:nth-child(even) {
            background-color: #fafcfd;
        }
        tr:nth-child(even):hover {
            background-color: #f1f7fd;
        }
        
        .aluno-nome-cell {
            position: sticky;
            left: 0;
            background: white;
            z-index: 5;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            min-width: 250px;
            max-width: 250px;
            text-align: left;
        }
        .aluno-nome {
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            line-height: 1.4;
            padding-right: 15px;
        }
        .aluno-nome strong {
            color: #2c3e50;
            font-size: 14px;
        }
        .aluno-info {
            color: #7f8c8d;
            font-size: 12px;
            display: block;
            margin-top: 3px;
        }
        
        input[type="number"] {
            width: 75px;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        input[type="number"]:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }
        input[type="number"].has-value {
            background-color: #e8f4fc;
            border-color: #3498db;
        }
        
        .mt-field {
            font-weight: 600;
            color: #2c3e50;
            background-color: #e3f2fd;
            padding: 9px;
            border-radius: 6px;
            text-align: center;
            min-width: 65px;
            font-size: 14px;
        }
        
        .loading {
            padding: 40px;
            text-align: center;
            color: #7f8c8d;
            font-size: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .spinner {
            width: 35px;
            height: 35px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error {
            padding: 40px;
            text-align: center;
            color: #e74c3c;
            font-size: 15px;
            background-color: #fdf2f2;
            border-radius: 8px;
            margin: 15px;
        }
        .info-text {
            padding: 30px;
            text-align: center;
            color: #5d6d7e;
            font-size: 15px;
            font-style: italic;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 15px;
            border: 2px dashed #d5d8dc;
        }
        
        .status-bar {
            background: linear-gradient(135deg, #ecf0f1, #f8f9fa);
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #2c3e50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            min-width: 1200px;
            width: 100%;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .trimestre-header {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
        }
        .numero-col {
            width: 50px;
            text-align: center;
            font-weight: 600;
            color: #2c3e50;
            position: sticky;
            left: 0;
            background: white;
            z-index: 5;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        
        .classificacao-cell {
            font-weight: 600;
            padding: 9px;
            border-radius: 6px;
            text-align: center;
            min-width: 110px;
            font-size: 13px;
        }
        .class-muito-bom { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .class-bom { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .class-suficiente { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .class-mediocre { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .class-mau { background-color: #f5c6cb; color: #721c24; border: 1px solid #f1b0b7; }
        
        .stats-bar {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            flex-wrap: wrap;
            min-width: 1200px;
            width: 100%;
        }
        .stat-card {
            background: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 140px;
            flex: 1;
        }
        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
        }
        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            text-align: center;
            margin-top: 5px;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap;
            min-width: 1200px;
            width: 100%;
        }
        .action-btn {
            padding: 11px 22px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            flex: 1;
            justify-content: center;
            font-size: 14px;
            color: white;
        }
        
        .classification-info {
            background: #e8f4fc;
            padding: 10px 18px;
            border-radius: 8px;
            margin: 12px 0;
            font-size: 13px;
            color: #2c3e50;
            border-left: 4px solid #3498db;
            display: none;
            align-items: center;
            gap: 10px;
            min-width: 1200px;
            width: 100%;
        }
        .classification-info .icon { font-size: 18px; flex-shrink: 0; }
        .classification-info .details { flex: 1; min-width: 0; }
        .classification-info .sistema { font-weight: bold; color: #2c3e50; white-space: nowrap; }
        .classification-info .faixas { font-size: 12px; color: #5d6d7e; margin-top: 3px; white-space: normal; line-height: 1.4; }
        
        .scroll-hint {
            position: sticky;
            right: 10px;
            top: 10px;
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 20;
            white-space: nowrap;
            float: right;
            margin: 5px 10px 0 0;
        }
        
        /* Mini Pauta Style */
        .mini-pauta-style th {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
        }
        .mini-pauta-style td {
            border-bottom: 1px solid #000;
        }
        .mini-pauta-style .trimestre-header {
            background: linear-gradient(135deg, #34495e, #2c3e50);
        }
        .input-mini {
            width: 60px;
            padding: 6px;
            border: 1px solid #999;
            border-radius: 4px;
            text-align: center;
            font-size: 12px;
        }
        
        /* Print View Modal */
        .print-view-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            overflow: auto;
            padding: 20px;
        }
        .print-view-content {
            background: white;
            max-width: 1400px;
            margin: 30px auto;
            padding: 30px;
            border-radius: 12px;
            position: relative;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            font-family: 'Times New Roman', serif;
            position: relative;
        }
        .print-header h2 {
            font-size: 24px;
            margin: 5px 0;
            text-transform: uppercase;
        }
        .print-header h3 {
            font-size: 20px;
            margin: 5px 0;
        }
        .print-header .escola-nome {
            font-size: 22px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 10px 0;
        }
        .print-header .info-linha {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            font-size: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .print-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin: 20px 0;
        }
        .print-table th {
            background: #2c3e50;
            color: white;
            padding: 12px 8px;
            text-align: center;
            border: 1px solid #000;
            font-size: 13px;
        }
        .print-table td {
            padding: 10px 8px;
            border: 1px solid #000;
            text-align: center;
        }
        .print-table .aluno-nome {
            text-align: left;
            font-weight: normal;
        }
        .print-footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-around;
            font-size: 14px;
            flex-wrap: wrap;
            gap: 30px;
        }
        .print-footer .assinatura {
            text-align: center;
            min-width: 200px;
        }
        .print-footer .linha {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
            width: 200px;
            margin-left: auto;
            margin-right: auto;
        }
        .close-print-btn {
            position: absolute;
            top: 15px;
            right: 25px;
            font-size: 30px;
            cursor: pointer;
            color: #666;
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .close-print-btn:hover {
            background: #f0f0f0;
        }
        .print-actions {
            text-align: center;
            margin: 20px 0;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .print-action-btn {
            padding: 12px 30px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .print-action-btn:hover {
            background: #2980b9;
        }
        .print-action-btn.print {
            background: #27ae60;
        }
        .print-action-btn.print:hover {
            background: #219653;
        }
        
        /* Edit Header Modal */
        .edit-header-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2100;
            justify-content: center;
            align-items: center;
        }
        .edit-header-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .edit-header-content h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 20px;
        }
        .edit-header-form-group {
            margin-bottom: 15px;
        }
        .edit-header-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #34495e;
        }
        .edit-header-form-group input, 
        .edit-header-form-group select,
        .edit-header-form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        .edit-header-form-group input:focus,
        .edit-header-form-group select:focus,
        .edit-header-form-group textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        .edit-header-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            justify-content: flex-end;
        }
        .edit-header-save {
            background: #27ae60;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .edit-header-save:hover {
            background: #219653;
        }
        .edit-header-cancel {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .edit-header-cancel:hover {
            background: #7f8c8d;
        }
        
        .edit-header-btn-print {
            position: absolute;
            top: 0;
            right: 60px;
            background: #f39c12;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .edit-header-btn-print:hover {
            background: #e67e22;
        }
        
        .zoom-controls {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 10px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        .zoom-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            border: none;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .zoom-btn:hover {
            background: #2980b9;
        }
        .zoom-display {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { background: white; font-size: 12px; width: 100%; margin: 0; padding: 20px; }
            .print-view-modal { display: block !important; position: static; background: white; padding: 0; }
            .print-view-content { margin: 0; padding: 0; box-shadow: none; }
            .print-table th { background: #2c3e50 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: stretch; min-width: auto; }
            .user-info { flex-wrap: wrap; justify-content: center; }
            .filter-row { flex-direction: column; }
            .filter-group { min-width: 100%; }
            .container { min-width: auto; padding: 10px; }
            .status-bar { min-width: auto; flex-direction: column; text-align: center; }
            .stats-bar { min-width: auto; flex-direction: column; }
            .button-group { min-width: auto; flex-direction: column; }
            .notes-table-wrapper { min-width: auto; }
            .classification-info { min-width: auto; }
            .scroll-hint { display: none; }
            .zoom-controls { bottom: 10px; right: 10px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= htmlspecialchars($escola_nome) ?> - Sistema de Lançamento de Notas</h1>
        <div class="user-info">
            <span style="font-weight: 500; white-space: nowrap;">👤 <?= htmlspecialchars($usuario_nome) ?></span>
            <span style="font-weight: 500; white-space: nowrap;" id="anoLetivoDisplay">📅 Ano Letivo: <span id="anoLetivoValor"><?= date('Y') . '/' . (date('Y') + 1) ?></span></span>
            <button class="btn btn-print-mini no-print" onclick="openPrintView()">📋 Visualizar Mini Pauta</button>
            <button class="btn btn-print no-print" onclick="window.print()">🖨️ Imprimir</button>
            <a href="dashboard.php" class="btn btn-back no-print">← Voltar</a>
        </div>
    </div>

    <div class="container">
        <div class="status-bar no-print">
            <div id="statusText" style="white-space: nowrap;">
                <strong>Status:</strong> Selecione as opções abaixo para carregar os alunos
            </div>
            <div id="selectionInfo" style="font-weight: 500; color: #2c3e50; white-space: nowrap;"></div>
        </div>

        <div class="filters no-print">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="anoLetivo">📅 Ano Letivo:</label>
                    <input type="text" id="anoLetivo" placeholder="Ex: 2025/2026" value="<?= date('Y') . '/' . (date('Y') + 1) ?>" onchange="onAnoLetivoChange()">
                </div>
                <div class="filter-group">
                    <label for="classe">📚 Classe:</label>
                    <select id="classe" onchange="onClasseChange()">
                        <option value="">Selecione a classe</option>
                        <?php foreach ($professor_dados['classes'] as $classe): ?>
                            <option value="<?= htmlspecialchars($classe) ?>"><?= htmlspecialchars($classe) ?>ª Classe</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="turma">👥 Turma:</label>
                    <select id="turma" onchange="onTurmaChange()" disabled>
                        <option value="">Selecione a turma</option>
                        <?php foreach ($professor_dados['turmas'] as $turma): ?>
                            <option value="<?= htmlspecialchars($turma) ?>">Turma <?= htmlspecialchars($turma) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="disciplina">📖 Disciplina:</label>
                    <select id="disciplina" disabled>
                        <option value="">Selecione a disciplina</option>
                        <?php foreach ($professor_dados['disciplinas'] as $disciplina): ?>
                            <option value="<?= htmlspecialchars($disciplina) ?>"><?= htmlspecialchars($disciplina) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn-search" onclick="searchStudents()" disabled>
                    🔍 Buscar Alunos
                </button>
            </div>
        </div>

        <div id="classificationInfo" class="classification-info no-print" style="display: none;">
            <div class="icon">📊</div>
            <div class="details">
                <div class="sistema" id="sistemaClassificacao">Sistema de Avaliação: 0-20 pontos</div>
                <div class="faixas" id="faixasClassificacao">
                    MUITO BOM: 18-20 | BOM: 14-17 | SUFICIENTE: 10-13 | MEDÍOCRE: 5-9 | MAU: 0-4
                </div>
            </div>
        </div>

        <div class="stats-bar no-print" id="statsBar" style="display: none;">
            <div class="stat-card">
                <div class="stat-value" id="statTotalAlunos">0</div>
                <div class="stat-label">Total de Alunos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="statMediaTurma">0.0</div>
                <div class="stat-label">Média da Turma</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="statAprovados">0</div>
                <div class="stat-label">Aprovados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="statReprovados">0</div>
                <div class="stat-label">Reprovados</div>
            </div>
        </div>

        <div id="notesTableContainer" class="notes-table-wrapper">
            <div class="scroll-hint no-print">↔️ Role horizontalmente</div>
            <div id="studentsTable">
                <div class="info-text">🔍 Selecione classe, turma e disciplina para visualizar e editar as notas dos alunos</div>
            </div>
        </div>

        <div class="button-group no-print">
            <button class="btn btn-save" onclick="saveNotes()" disabled>💾 Salvar Todas as Notas</button>
            <button class="btn btn-export" onclick="exportToExcel()">📊 Exportar Excel</button>
            <button class="btn btn-clear" onclick="clearAll()">🗑️ Limpar Seleção</button>
        </div>
    </div>

    <div class="zoom-controls no-print">
        <button class="zoom-btn" onclick="zoomOut()">−</button>
        <div class="zoom-display" id="zoomLevel">100%</div>
        <button class="zoom-btn" onclick="zoomIn()">+</button>
    </div>

    <!-- Print View Modal -->
    <div id="printViewModal" class="print-view-modal">
        <div class="print-view-content">
            <div class="close-print-btn no-print" onclick="closePrintView()">&times;</div>
            <button class="edit-header-btn-print no-print" onclick="openEditHeaderModal()">✏️ Editar Cabeçalho</button>
            <div id="printContent" class="print-content"></div>
            <div class="print-actions no-print">
                <button class="print-action-btn print" onclick="window.print()">🖨️ Imprimir Mini Pauta</button>
                <button class="print-action-btn" onclick="closePrintView()">✖️ Fechar</button>
            </div>
        </div>
    </div>

    <!-- Edit Header Modal -->
    <div id="editHeaderModal" class="edit-header-modal">
        <div class="edit-header-content">
            <h3>Editar Cabeçalho da Pauta</h3>
            <div class="edit-header-form-group">
                <label for="editEscola">Nome da Escola:</label>
                <input type="text" id="editEscola" value="<?= htmlspecialchars($escola_nome) ?>">
            </div>
            <div class="edit-header-form-group">
                <label for="editProvincia">Província:</label>
                <input type="text" id="editProvincia" value="ICOLO E BENGO">
            </div>
            <div class="edit-header-form-group">
                <label for="editMunicipio">Município:</label>
                <input type="text" id="editMunicipio" value="CALUMBO">
            </div>
            <div class="edit-header-form-group">
                <label for="editAnoLetivo">Ano Letivo:</label>
                <input type="text" id="editAnoLetivo" value="<?= date('Y') . '/' . (date('Y') + 1) ?>">
            </div>
            <div class="edit-header-form-group">
                <label for="editClasse">Classe:</label>
                <input type="text" id="editClasse" value="">
            </div>
            <div class="edit-header-form-group">
                <label for="editTurma">Turma:</label>
                <input type="text" id="editTurma" value="">
            </div>
            <div class="edit-header-form-group">
                <label for="editDisciplina">Disciplina:</label>
                <input type="text" id="editDisciplina" value="">
            </div>
            <div class="edit-header-form-group">
                <label for="editTrimestre">Trimestre:</label>
                <select id="editTrimestre">
                    <option value="I TRIMESTRE">I TRIMESTRE</option>
                    <option value="II TRIMESTRE">II TRIMESTRE</option>
                    <option value="III TRIMESTRE">III TRIMESTRE</option>
                    <option value="FINAL" selected>FINAL</option>
                </select>
            </div>
            <div class="edit-header-form-group">
                <label for="editProfessor">Professor:</label>
                <input type="text" id="editProfessor" value="<?= htmlspecialchars($usuario_nome) ?>">
            </div>
            <div class="edit-header-form-group">
                <label for="editDiretor">Diretor Pedagógico:</label>
                <input type="text" id="editDiretor" value="">
            </div>
            <div class="edit-header-buttons">
                <button class="edit-header-save" onclick="saveHeaderChanges()">Salvar</button>
                <button class="edit-header-cancel" onclick="closeEditHeaderModal()">Cancelar</button>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // VARIÁVEIS GLOBAIS
        // ============================================
        let alunos = [];
        let currentSelections = {
            anoLetivo: '<?= date('Y') . '/' . (date('Y') + 1) ?>',
            classe: '',
            turma: '',
            disciplina: ''
        };
        let isPreSexta = false;
        let isMiniPautaStyle = false;
        let currentZoom = 100;
        
        let headerData = {
            escola: '<?= htmlspecialchars($escola_nome) ?>',
            provincia: 'ICOLO E BENGO',
            municipio: 'CALUMBO',
            trimestre: 'FINAL',
            professor: '<?= htmlspecialchars($usuario_nome) ?>',
            diretor: ''
        };

        // ============================================
        // ZOOM
        // ============================================
        function zoomIn() {
            if (currentZoom < 120) {
                currentZoom += 5;
                updateZoom();
            }
        }

        function zoomOut() {
            if (currentZoom > 70) {
                currentZoom -= 5;
                updateZoom();
            }
        }

        function updateZoom() {
            const scale = currentZoom / 100;
            document.body.style.zoom = scale;
            document.body.style.transform = `scale(${scale})`;
            document.body.style.transformOrigin = '0 0';
            document.body.style.width = `${100/scale}%`;
            document.getElementById('zoomLevel').textContent = `${currentZoom}%`;
            localStorage.setItem('notasZoomLevel', currentZoom);
        }

        function loadZoom() {
            const savedZoom = localStorage.getItem('notasZoomLevel');
            if (savedZoom) {
                currentZoom = parseInt(savedZoom);
                updateZoom();
            }
        }

        // ============================================
        // CLASSIFICAÇÃO
        // ============================================
        function determinarSistemaClassificacao(classe) {
            try {
                const classeNum = parseInt(classe.replace('ª', '').replace('º', '').trim());
                isPreSexta = classeNum <= 6;
                isMiniPautaStyle = (classeNum === 6 || classeNum === 9 || classeNum === 12);
                
                const infoDiv = document.getElementById('classificationInfo');
                const sistemaDiv = document.getElementById('sistemaClassificacao');
                const faixasDiv = document.getElementById('faixasClassificacao');
                
                if (isPreSexta) {
                    sistemaDiv.textContent = 'Sistema de Avaliação: 0-10 pontos (Pré a 6ª)';
                    faixasDiv.innerHTML = `
                        <strong>MUITO BOM:</strong> 9-10 | 
                        <strong>BOM:</strong> 7-8 | 
                        <strong>SUFICIENTE:</strong> 5-6 | 
                        <strong>MEDÍOCRE:</strong> 3-4 | 
                        <strong>MAU:</strong> 0-2
                    `;
                    infoDiv.style.display = 'flex';
                    infoDiv.style.backgroundColor = '#fff3cd';
                    infoDiv.style.borderLeftColor = '#f39c12';
                } else {
                    sistemaDiv.textContent = 'Sistema de Avaliação: 0-20 pontos (7ª a 12ª)';
                    faixasDiv.innerHTML = `
                        <strong>MUITO BOM:</strong> 18-20 | 
                        <strong>BOM:</strong> 14-17 | 
                        <strong>SUFICIENTE:</strong> 10-13 | 
                        <strong>MEDÍOCRE:</strong> 5-9 | 
                        <strong>MAU:</strong> 0-4
                    `;
                    infoDiv.style.display = 'flex';
                    infoDiv.style.backgroundColor = '#e8f4fc';
                    infoDiv.style.borderLeftColor = '#3498db';
                }
                
                return isPreSexta;
            } catch (error) {
                console.error('Erro ao determinar sistema de classificação:', error);
                isPreSexta = false;
                isMiniPautaStyle = false;
                return false;
            }
        }

        function determinarClassificacao(mfd) {
            if (!mfd || mfd <= 0) return { texto: '', classe: '' };
            
            let texto = '';
            let classe = '';
            
            if (isPreSexta) {
                if (mfd >= 9) { texto = "MUITO BOM"; classe = "class-muito-bom"; }
                else if (mfd >= 7) { texto = "BOM"; classe = "class-bom"; }
                else if (mfd >= 5) { texto = "SUFICIENTE"; classe = "class-suficiente"; }
                else if (mfd >= 3) { texto = "MEDÍOCRE"; classe = "class-mediocre"; }
                else if (mfd >= 0) { texto = "MAU"; classe = "class-mau"; }
            } else {
                if (mfd >= 18) { texto = "MUITO BOM"; classe = "class-muito-bom"; }
                else if (mfd >= 14) { texto = "BOM"; classe = "class-bom"; }
                else if (mfd >= 10) { texto = "SUFICIENTE"; classe = "class-suficiente"; }
                else if (mfd >= 5) { texto = "MEDÍOCRE"; classe = "class-mediocre"; }
                else if (mfd >= 0) { texto = "MAU"; classe = "class-mau"; }
            }
            
            return { texto, classe };
        }

        // ============================================
        // FUNÇÕES DE FILTRO
        // ============================================
        function onAnoLetivoChange() {
            const anoLetivo = document.getElementById('anoLetivo').value;
            if (!anoLetivo.match(/^\d{4}\/\d{4}$/)) {
                alert('Formato de ano letivo inválido. Use o formato: 2025/2026');
                return;
            }
            currentSelections.anoLetivo = anoLetivo;
            document.getElementById('anoLetivoValor').textContent = anoLetivo;
            document.getElementById('editAnoLetivo').value = anoLetivo;
            updateStatus(`Ano Letivo ${anoLetivo} selecionado`);
        }

        function onClasseChange() {
            const classe = document.getElementById('classe').value;
            const turmaSelect = document.getElementById('turma');
            const disciplinaSelect = document.getElementById('disciplina');
            const searchBtn = document.querySelector('.btn-search');
            
            currentSelections.classe = classe;
            currentSelections.turma = '';
            currentSelections.disciplina = '';
            
            if (classe) {
                determinarSistemaClassificacao(classe);
                turmaSelect.disabled = false;
                disciplinaSelect.disabled = true;
                disciplinaSelect.innerHTML = '<option value="">Selecione a disciplina</option>';
                searchBtn.disabled = true;
                updateStatus(`Classe ${classe}ª selecionada. Agora selecione a turma.`);
            } else {
                turmaSelect.disabled = true;
                turmaSelect.innerHTML = '<option value="">Selecione a turma</option>';
                disciplinaSelect.disabled = true;
                disciplinaSelect.innerHTML = '<option value="">Selecione a disciplina</option>';
                searchBtn.disabled = true;
                document.getElementById('classificationInfo').style.display = 'none';
                updateStatus('Selecione uma classe para continuar');
            }
            clearTable();
        }

        function onTurmaChange() {
            const classe = document.getElementById('classe').value;
            const turma = document.getElementById('turma').value;
            const disciplinaSelect = document.getElementById('disciplina');
            const searchBtn = document.querySelector('.btn-search');
            
            currentSelections.turma = turma;
            
            if (classe && turma) {
                disciplinaSelect.disabled = false;
                searchBtn.disabled = true;
                updateStatus(`Selecionado: ${classe}ª Classe - Turma ${turma}. Agora selecione a disciplina.`);
            } else {
                disciplinaSelect.disabled = true;
                disciplinaSelect.innerHTML = '<option value="">Selecione a disciplina</option>';
                searchBtn.disabled = true;
            }
            clearTable();
        }

        function updateStatus(message) {
            const statusText = document.getElementById('statusText');
            statusText.innerHTML = `<strong>Status:</strong> ${message}`;
            
            const selectionInfo = document.getElementById('selectionInfo');
            let info = '';
            if (currentSelections.anoLetivo) info += `📅 ${currentSelections.anoLetivo} `;
            if (currentSelections.classe) info += `📚 ${currentSelections.classe}ª `;
            if (currentSelections.turma) info += `👥 ${currentSelections.turma} `;
            if (currentSelections.disciplina) info += `📖 ${currentSelections.disciplina}`;
            selectionInfo.innerHTML = info;
        }

        function clearTable() {
            const studentsTable = document.getElementById('studentsTable');
            studentsTable.innerHTML = '<div class="info-text">🔍 Selecione classe, turma e disciplina para visualizar os alunos</div>';
            document.querySelector('.btn-save').disabled = true;
            document.getElementById('statsBar').style.display = 'none';
        }

        // ============================================
        // BUSCAR ALUNOS (AJAX para PHP)
        // ============================================
        async function searchStudents() {
            const anoLetivo = document.getElementById('anoLetivo').value;
            const classe = document.getElementById('classe').value;
            const turma = document.getElementById('turma').value;
            const disciplina = document.getElementById('disciplina').value;
            
            if (!classe || !turma || !disciplina) {
                alert('Por favor, selecione classe, turma e disciplina');
                return;
            }
            
            determinarSistemaClassificacao(classe);
            
            currentSelections = { anoLetivo, classe, turma, disciplina };
            
            try {
                document.getElementById('studentsTable').innerHTML = `
                    <div class="loading">
                        <div class="spinner"></div>
                        <div>Buscando alunos e notas...</div>
                    </div>
                `;
                updateStatus(`Buscando dados para ${classe}ª - Turma ${turma} - ${disciplina} (Ano Letivo ${anoLetivo})`);
                
                // Buscar alunos via AJAX
                const alunosResponse = await fetch(`/softgest_web/api/alunos.php?classe=${encodeURIComponent(classe)}&turma=${encodeURIComponent(turma)}&ano_letivo=${encodeURIComponent(anoLetivo)}`);
                const alunosData = await alunosResponse.json();
                
                if (!alunosData.success) {
                    throw new Error(alunosData.message || 'Erro ao buscar alunos');
                }
                
                alunos = alunosData.alunos || [];
                
                if (alunos.length === 0) {
                    document.getElementById('studentsTable').innerHTML = '<div class="info-text">📭 Nenhum aluno encontrado para esta turma no ano letivo selecionado.</div>';
                    updateStatus(`Nenhum aluno encontrado na ${classe}ª - Turma ${turma} - Ano ${anoLetivo}`);
                    return;
                }
                
                // Buscar notas via AJAX
                const notasResponse = await fetch(`/softgest_web/api/notas.php?disciplina=${encodeURIComponent(disciplina)}&classe=${classe}&turma=${turma}&ano_letivo=${encodeURIComponent(anoLetivo)}`);
                const notasData = await notasResponse.json();
                
                let notasExistentes = {};
                if (notasData.success) {
                    (notasData.notas || []).forEach(nota => {
                        notasExistentes[nota.id_aluno] = {
                            mac_t1: nota.mac_t1,
                            npt_t1: nota.npt_t1,
                            mac_t2: nota.mac_t2,
                            npt_t2: nota.npt_t2,
                            mac_t3: nota.mac_t3,
                            npt_t3: nota.npt_t3,
                            neo: nota.neo,
                            en: nota.en
                        };
                    });
                }
                
                renderNotesTable(alunos, notasExistentes);
                document.querySelector('.btn-save').disabled = false;
                updateStatistics();
                updateStatus(`✅ Carregados ${alunos.length} alunos com suas notas - Ano Letivo ${anoLetivo}`);
                
            } catch (error) {
                console.error('Erro:', error);
                document.getElementById('studentsTable').innerHTML = `<div class="error">❌ Erro ao buscar dados: ${error.message}</div>`;
                updateStatus('❌ Erro ao carregar dados');
            }
        }

        // ============================================
        // RENDERIZAR TABELA
        // ============================================
        function renderNotesTable(alunos, notasExistentes) {
            const maxPontos = isPreSexta ? 10 : 20;
            
            let html = '';
            
            if (isMiniPautaStyle) {
                html = `
                    <table class="notes-table mini-pauta-style">
                        <thead>
                            <tr>
                                <th class="numero-col" rowspan="2">Nº</th>
                                <th class="aluno-nome-cell" rowspan="2">Nome do Aluno</th>
                                <th class="trimestre-header" colspan="3">I TRIMESTRE</th>
                                <th class="trimestre-header" colspan="3">II TRIMESTRE</th>
                                <th class="trimestre-header" colspan="5">III TRIMESTRE</th>
                                <th rowspan="2">MFED</th>
                            </tr>
                            <tr>
                                <th>MAC</th><th>NPT</th><th>MT</th>
                                <th>MAC</th><th>NPT</th><th>MT</th>
                                <th>MAC</th><th>MFD</th><th>NEO</th><th>EN</th><th>MEC</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
            } else {
                html = `
                    <table class="notes-table">
                        <thead>
                            <tr>
                                <th class="numero-col">Nº</th>
                                <th class="aluno-nome-cell">Nome do Aluno</th>
                                <th class="trimestre-header" colspan="3">1º Trimestre</th>
                                <th class="trimestre-header" colspan="3">2º Trimestre</th>
                                <th class="trimestre-header" colspan="3">3º Trimestre</th>
                                <th>MFD/${maxPontos}</th>
                                <th>Classificação</th>
                            </tr>
                            <tr>
                                <th></th><th></th>
                                <th>MAC</th><th>NPT</th><th>MT</th>
                                <th>MAC</th><th>NPT</th><th>MT</th>
                                <th>MAC</th><th>NPT</th><th>MT</th>
                                <th>Final</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                `;
            }
            
            alunos.forEach((aluno, index) => {
                const nota = notasExistentes[aluno.id] || {};
                const nomeCompleto = `
                    <div class="aluno-nome">
                        <strong>${aluno.nome || 'Sem nome'}</strong>
                        <span class="aluno-info">
                            ${aluno.sexo ? aluno.sexo + ' • ' : ''}
                            ${aluno.idade ? aluno.idade + ' anos' : ''}
                        </span>
                    </div>
                `;
                
                if (isMiniPautaStyle) {
                    const mac1 = parseFloat(nota.mac_t1) || 0;
                    const npt1 = parseFloat(nota.npt_t1) || 0;
                    const mt1 = (mac1 + npt1) / 2;
                    
                    const mac2 = parseFloat(nota.mac_t2) || 0;
                    const npt2 = parseFloat(nota.npt_t2) || 0;
                    const mt2 = (mac2 + npt2) / 2;
                    
                    const mac3 = parseFloat(nota.mac_t3) || 0;
                    const neo = parseFloat(nota.neo) || 0;
                    const en = parseFloat(nota.en) || 0;
                    
                    const mfd = mac3;
                    const mec = (neo + en) / 2;
                    const mt3 = (mt1 + mt2 + mfd) / 3;
                    const mfed = (0.6 * mt3) + en + 0.4;
                    
                    html += `
                        <tr data-aluno-id="${aluno.id}">
                            <td class="numero-col">${index + 1}</td>
                            <td class="aluno-nome-cell">${nomeCompleto}</td>
                            <td><input type="number" class="input-mini" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.mac_t1 || ''}" 
                                onchange="calcularMiniMT(1, '${aluno.id}')" 
                                id="mac1_${aluno.id}"></td>
                            <td><input type="number" class="input-mini" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.npt_t1 || ''}" 
                                onchange="calcularMiniMT(1, '${aluno.id}')" 
                                id="npt1_${aluno.id}"></td>
                            <td class="mt-field" id="mt1_${aluno.id}">${mt1.toFixed(1)}</td>
                            <td><input type="number" class="input-mini" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.mac_t2 || ''}" 
                                onchange="calcularMiniMT(2, '${aluno.id}')" 
                                id="mac2_${aluno.id}"></td>
                            <td><input type="number" class="input-mini" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.npt_t2 || ''}" 
                                onchange="calcularMiniMT(2, '${aluno.id}')" 
                                id="npt2_${aluno.id}"></td>
                            <td class="mt-field" id="mt2_${aluno.id}">${mt2.toFixed(1)}</td>
                            <td><input type="number" class="input-mini" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.mac_t3 || ''}" 
                                onchange="calcularMiniIII('${aluno.id}')" 
                                id="mac3_${aluno.id}"></td>
                            <td class="mt-field" id="mfd_${aluno.id}">${mfd.toFixed(1)}</td>
                            <td><input type="number" class="input-mini" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.neo || ''}" 
                                onchange="calcularMiniIII('${aluno.id}')" 
                                id="neo_${aluno.id}"></td>
                            <td><input type="number" class="input-mini" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.en || ''}" 
                                onchange="calcularMiniIII('${aluno.id}')" 
                                id="en_${aluno.id}"></td>
                            <td class="mt-field" id="mec_${aluno.id}">${mec.toFixed(1)}</td>
                            <td class="mt-field" id="mfed_${aluno.id}" style="font-weight: bold;">${mfed.toFixed(1)}</td>
                        </tr>
                    `;
                } else {
                    const mac1 = parseFloat(nota.mac_t1) || 0;
                    const npt1 = parseFloat(nota.npt_t1) || 0;
                    const mt1 = (mac1 > 0 && npt1 > 0) ? (mac1 + npt1) / 2 : 0;
                    
                    const mac2 = parseFloat(nota.mac_t2) || 0;
                    const npt2 = parseFloat(nota.npt_t2) || 0;
                    const mt2 = (mac2 > 0 && npt2 > 0) ? (mac2 + npt2) / 2 : 0;
                    
                    const mac3 = parseFloat(nota.mac_t3) || 0;
                    const npt3 = parseFloat(nota.npt_t3) || 0;
                    const mt3 = (mac3 > 0 && npt3 > 0) ? (mac3 + npt3) / 2 : 0;
                    
                    const mediasValidas = [];
                    if (mt1 > 0) mediasValidas.push(mt1);
                    if (mt2 > 0) mediasValidas.push(mt2);
                    if (mt3 > 0) mediasValidas.push(mt3);
                    
                    const mfd = mediasValidas.length > 0 ? 
                        mediasValidas.reduce((a, b) => a + b, 0) / mediasValidas.length : 0;
                    
                    const classificacao = determinarClassificacao(mfd);
                    
                    html += `
                        <tr data-aluno-id="${aluno.id}">
                            <td class="numero-col">${index + 1}</td>
                            <td class="aluno-nome-cell">${nomeCompleto}</td>
                            <td><input type="number" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.mac_t1 || ''}" 
                                oninput="calculateMT(this, 1, '${aluno.id}')" 
                                data-field="mac_t1" data-aluno="${aluno.id}"
                                ${nota.mac_t1 ? 'class="has-value"' : ''}></td>
                            <td><input type="number" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.npt_t1 || ''}" 
                                oninput="calculateMT(this, 1, '${aluno.id}')" 
                                data-field="npt_t1" data-aluno="${aluno.id}"
                                ${nota.npt_t1 ? 'class="has-value"' : ''}></td>
                            <td class="mt-field" id="mt1_${aluno.id}">${mt1.toFixed(1)}</td>
                            <td><input type="number" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.mac_t2 || ''}" 
                                oninput="calculateMT(this, 2, '${aluno.id}')" 
                                data-field="mac_t2" data-aluno="${aluno.id}"
                                ${nota.mac_t2 ? 'class="has-value"' : ''}></td>
                            <td><input type="number" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.npt_t2 || ''}" 
                                oninput="calculateMT(this, 2, '${aluno.id}')" 
                                data-field="npt_t2" data-aluno="${aluno.id}"
                                ${nota.npt_t2 ? 'class="has-value"' : ''}></td>
                            <td class="mt-field" id="mt2_${aluno.id}">${mt2.toFixed(1)}</td>
                            <td><input type="number" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.mac_t3 || ''}" 
                                oninput="calculateMT(this, 3, '${aluno.id}')" 
                                data-field="mac_t3" data-aluno="${aluno.id}"
                                ${nota.mac_t3 ? 'class="has-value"' : ''}></td>
                            <td><input type="number" min="0" max="${maxPontos}" step="0.5" 
                                value="${nota.npt_t3 || ''}" 
                                oninput="calculateMT(this, 3, '${aluno.id}')" 
                                data-field="npt_t3" data-aluno="${aluno.id}"
                                ${nota.npt_t3 ? 'class="has-value"' : ''}></td>
                            <td class="mt-field" id="mt3_${aluno.id}">${mt3.toFixed(1)}</td>
                            <td class="mt-field" id="mfd_${aluno.id}" style="font-size: 14px; font-weight: 700;">${mfd.toFixed(1)}</td>
                            <td id="class_${aluno.id}" class="classificacao-cell ${classificacao.classe}">${classificacao.texto}</td>
                        </tr>
                    `;
                }
            });
            
            html += `</tbody></table>`;
            document.getElementById('studentsTable').innerHTML = html;
            updateInputClasses();
        }

        // ============================================
        // FUNÇÕES DE CÁLCULO
        // ============================================
        function calcularMiniMT(trimestre, alunoId) {
            const mac = parseFloat(document.getElementById(`mac${trimestre}_${alunoId}`).value) || 0;
            const npt = parseFloat(document.getElementById(`npt${trimestre}_${alunoId}`).value) || 0;
            const mt = (mac + npt) / 2;
            document.getElementById(`mt${trimestre}_${alunoId}`).textContent = mt.toFixed(1);
            calcularMiniMFED(alunoId);
        }

        function calcularMiniIII(alunoId) {
            const mac3 = parseFloat(document.getElementById(`mac3_${alunoId}`).value) || 0;
            const neo = parseFloat(document.getElementById(`neo_${alunoId}`).value) || 0;
            const en = parseFloat(document.getElementById(`en_${alunoId}`).value) || 0;
            
            document.getElementById(`mfd_${alunoId}`).textContent = mac3.toFixed(1);
            const mec = (neo + en) / 2;
            document.getElementById(`mec_${alunoId}`).textContent = mec.toFixed(1);
            calcularMiniMFED(alunoId);
        }

        function calcularMiniMFED(alunoId) {
            const mt1 = parseFloat(document.getElementById(`mt1_${alunoId}`).textContent) || 0;
            const mt2 = parseFloat(document.getElementById(`mt2_${alunoId}`).textContent) || 0;
            const mfd = parseFloat(document.getElementById(`mfd_${alunoId}`).textContent) || 0;
            const en = parseFloat(document.getElementById(`en_${alunoId}`).value) || 0;
            
            const mt3 = (mt1 + mt2 + mfd) / 3;
            const mfed = (0.6 * mt3) + en + 0.4;
            document.getElementById(`mfed_${alunoId}`).textContent = mfed.toFixed(1);
            updateStatistics();
        }

        function updateInputClasses() {
            document.querySelectorAll('input[type="number"]').forEach(input => {
                if (input.value && input.value !== '') {
                    input.classList.add('has-value');
                } else {
                    input.classList.remove('has-value');
                }
            });
        }

        function calculateMT(input, trimestre, alunoId) {
            const row = input.closest('tr');
            
            if (input.value && input.value !== '') {
                input.classList.add('has-value');
            } else {
                input.classList.remove('has-value');
            }
            
            const mac = parseFloat(row.querySelector(`input[data-field="mac_t${trimestre}"][data-aluno="${alunoId}"]`).value) || 0;
            const npt = parseFloat(row.querySelector(`input[data-field="npt_t${trimestre}"][data-aluno="${alunoId}"]`).value) || 0;
            
            let mt = 0;
            if (mac > 0 && npt > 0) {
                mt = (mac + npt) / 2;
            }
            
            document.getElementById(`mt${trimestre}_${alunoId}`).textContent = mt.toFixed(1);
            calculateMFD(alunoId);
            updateStatistics();
        }

        function calculateMFD(alunoId) {
            const row = document.querySelector(`tr[data-aluno-id="${alunoId}"]`);
            if (!row) return;
            
            const medias = [];
            
            for (let i = 1; i <= 3; i++) {
                const mac = parseFloat(row.querySelector(`input[data-field="mac_t${i}"][data-aluno="${alunoId}"]`).value) || 0;
                const npt = parseFloat(row.querySelector(`input[data-field="npt_t${i}"][data-aluno="${alunoId}"]`).value) || 0;
                
                if (mac > 0 && npt > 0) {
                    medias.push((mac + npt) / 2);
                }
            }
            
            let mfd = 0;
            if (medias.length > 0) {
                mfd = medias.reduce((a, b) => a + b, 0) / medias.length;
            }
            
            const mfdCell = document.getElementById(`mfd_${alunoId}`);
            if (mfdCell) {
                mfdCell.textContent = mfd.toFixed(1);
            }
            
            if (!isMiniPautaStyle) {
                const classificacao = determinarClassificacao(mfd);
                const classCell = document.getElementById(`class_${alunoId}`);
                if (classCell) {
                    classCell.textContent = classificacao.texto;
                    classCell.className = `classificacao-cell ${classificacao.classe}`;
                }
            }
        }

        function updateStatistics() {
            const rows = document.querySelectorAll('tr[data-aluno-id]');
            if (rows.length === 0) return;
            
            let somaMfds = 0;
            let aprovados = 0;
            let reprovados = 0;
            let totalComNota = 0;
            
            rows.forEach(row => {
                const alunoId = row.dataset.alunoId;
                let mfd = 0;
                
                if (isMiniPautaStyle) {
                    mfd = parseFloat(document.getElementById(`mfed_${alunoId}`)?.textContent) || 0;
                } else {
                    mfd = parseFloat(document.getElementById(`mfd_${alunoId}`)?.textContent) || 0;
                }
                
                if (mfd > 0) {
                    somaMfds += mfd;
                    totalComNota++;
                    
                    const notaMinimaAprovacao = isPreSexta ? 5 : 10;
                    if (mfd >= notaMinimaAprovacao) {
                        aprovados++;
                    } else {
                        reprovados++;
                    }
                }
            });
            
            const mediaTurma = totalComNota > 0 ? somaMfds / totalComNota : 0;
            
            document.getElementById('statTotalAlunos').textContent = alunos.length;
            document.getElementById('statMediaTurma').textContent = mediaTurma.toFixed(1);
            document.getElementById('statAprovados').textContent = aprovados;
            document.getElementById('statReprovados').textContent = reprovados;
            
            document.getElementById('statsBar').style.display = 'flex';
        }

        // ============================================
        // SALVAR NOTAS (AJAX para PHP)
        // ============================================
        async function saveNotes() {
            if (!confirm('Deseja salvar todas as notas alteradas?')) {
                return;
            }
            
            const anoLetivo = document.getElementById('anoLetivo').value;
            const notesData = [];
            const rows = document.querySelectorAll('tr[data-aluno-id]');
            
            rows.forEach(row => {
                const alunoId = row.dataset.alunoId;
                const aluno = alunos.find(a => a.id == alunoId);
                
                if (aluno) {
                    const note = {
                        id_aluno: alunoId,
                        nome_aluno: aluno.nome || '',
                        disciplina: currentSelections.disciplina,
                        turma: currentSelections.turma,
                        classe: currentSelections.classe,
                        ano_letivo: anoLetivo,
                        sexo: aluno.sexo || '',
                        idade: aluno.idade || 0,
                        sala: '09',
                        turno: 'MANHÃ'
                    };
                    
                    if (isMiniPautaStyle) {
                        note.mac_t1 = document.getElementById(`mac1_${alunoId}`)?.value || '';
                        note.npt_t1 = document.getElementById(`npt1_${alunoId}`)?.value || '';
                        note.mac_t2 = document.getElementById(`mac2_${alunoId}`)?.value || '';
                        note.npt_t2 = document.getElementById(`npt2_${alunoId}`)?.value || '';
                        note.mac_t3 = document.getElementById(`mac3_${alunoId}`)?.value || '';
                        note.neo = document.getElementById(`neo_${alunoId}`)?.value || '';
                        note.en = document.getElementById(`en_${alunoId}`)?.value || '';
                    } else {
                        for (let i = 1; i <= 3; i++) {
                            const macInput = row.querySelector(`input[data-field="mac_t${i}"][data-aluno="${alunoId}"]`);
                            const nptInput = row.querySelector(`input[data-field="npt_t${i}"][data-aluno="${alunoId}"]`);
                            note[`mac_t${i}`] = macInput?.value || '';
                            note[`npt_t${i}`] = nptInput?.value || '';
                        }
                        note.neo = '';
                        note.en = '';
                    }
                    
                    notesData.push(note);
                }
            });
            
            try {
                updateStatus('💾 Salvando notas...');
                
                const response = await fetch('/softgest_web/api/notas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        notas: notesData,
                        ano_letivo: anoLetivo
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`✅ ${result.message}`);
                    updateStatus(`Notas salvas com sucesso! - Ano Letivo: ${anoLetivo}`);
                    await searchStudents();
                } else {
                    throw new Error(result.message || 'Erro ao salvar notas');
                }
            } catch (error) {
                console.error('❌ Erro:', error);
                alert(`❌ Erro ao salvar: ${error.message}`);
                updateStatus('❌ Erro ao salvar notas');
            }
        }

        // ============================================
        // EXPORTAR EXCEL (CSV)
        // ============================================
        function exportToExcel() {
            if (alunos.length === 0) {
                alert('Nenhum dado para exportar. Primeiro busque os alunos.');
                return;
            }
            
            try {
                let csv = '';
                
                if (isMiniPautaStyle) {
                    csv = 'Nº,Nome,MAC1,NPT1,MT1,MAC2,NPT2,MT2,MAC3,MFD3,NEO,EN,MEC,MFED,Ano Letivo\n';
                } else {
                    csv = 'Nº,Nome,Sexo,Idade,MAC1,NPT1,MT1,MAC2,NPT2,MT2,MAC3,NPT3,MT3,MFD,Classificação,Ano Letivo\n';
                }
                
                alunos.forEach((aluno, index) => {
                    if (isMiniPautaStyle) {
                        const row = [
                            index + 1,
                            `"${aluno.nome || ''}"`,
                            document.getElementById(`mac1_${aluno.id}`)?.value || '',
                            document.getElementById(`npt1_${aluno.id}`)?.value || '',
                            document.getElementById(`mt1_${aluno.id}`)?.textContent || '0',
                            document.getElementById(`mac2_${aluno.id}`)?.value || '',
                            document.getElementById(`npt2_${aluno.id}`)?.value || '',
                            document.getElementById(`mt2_${aluno.id}`)?.textContent || '0',
                            document.getElementById(`mac3_${aluno.id}`)?.value || '',
                            document.getElementById(`mfd_${aluno.id}`)?.textContent || '0',
                            document.getElementById(`neo_${aluno.id}`)?.value || '',
                            document.getElementById(`en_${aluno.id}`)?.value || '',
                            document.getElementById(`mec_${aluno.id}`)?.textContent || '0',
                            document.getElementById(`mfed_${aluno.id}`)?.textContent || '0',
                            `"${currentSelections.anoLetivo}"`
                        ].join(',');
                        csv += row + '\n';
                    } else {
                        const row = document.querySelector(`tr[data-aluno-id="${aluno.id}"]`);
                        if (row) {
                            const mfd = document.getElementById(`mfd_${aluno.id}`)?.textContent || '0';
                            const classificacao = document.getElementById(`class_${aluno.id}`)?.textContent || '';
                            
                            csv += `${index + 1},"${aluno.nome || ''}","${aluno.sexo || ''}","${aluno.idade || ''}",`;
                            
                            for (let i = 1; i <= 3; i++) {
                                const mac = row.querySelector(`input[data-field="mac_t${i}"][data-aluno="${aluno.id}"]`)?.value || '';
                                const npt = row.querySelector(`input[data-field="npt_t${i}"][data-aluno="${aluno.id}"]`)?.value || '';
                                const mt = document.getElementById(`mt${i}_${aluno.id}`)?.textContent || '0';
                                csv += `${mac},${npt},${mt},`;
                            }
                            
                            csv += `${mfd},"${classificacao}","${currentSelections.anoLetivo}"\n`;
                        }
                    }
                });
                
                const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', `notas_${currentSelections.classe}_${currentSelections.turma}_${currentSelections.disciplina}_${currentSelections.anoLetivo.replace('/', '_')}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                updateStatus('✅ Dados exportados com sucesso!');
                
            } catch (error) {
                console.error('Erro ao exportar:', error);
                alert('❌ Erro ao exportar dados');
            }
        }

        // ============================================
        // LIMPAR SELEÇÃO
        // ============================================
        function clearAll() {
            if (confirm('Deseja limpar todos os filtros e dados?')) {
                document.getElementById('classe').value = '';
                document.getElementById('turma').value = '';
                document.getElementById('turma').disabled = true;
                document.getElementById('disciplina').value = '';
                document.getElementById('disciplina').disabled = true;
                
                document.querySelector('.btn-search').disabled = true;
                document.querySelector('.btn-save').disabled = true;
                
                clearTable();
                
                currentSelections = { 
                    anoLetivo: document.getElementById('anoLetivo').value,
                    classe: '', 
                    turma: '', 
                    disciplina: '' 
                };
                updateStatus('Selecione as opções abaixo para carregar os alunos');
                document.getElementById('selectionInfo').innerHTML = '';
                document.getElementById('classificationInfo').style.display = 'none';
                document.getElementById('statsBar').style.display = 'none';
                isMiniPautaStyle = false;
            }
        }

        // ============================================
        // VISUALIZAÇÃO DE IMPRESSÃO
        // ============================================
        function openPrintView() {
            if (alunos.length === 0) {
                alert('Por favor, busque os alunos primeiro.');
                return;
            }
            updatePrintView();
            document.getElementById('printViewModal').style.display = 'block';
        }

        function closePrintView() {
            document.getElementById('printViewModal').style.display = 'none';
        }

        function updatePrintView() {
            const printContent = document.getElementById('printContent');
            const classeNum = parseInt(currentSelections.classe.replace('ª', '').replace('º', '').trim());
            const isMiniPauta = (classeNum === 6 || classeNum === 9 || classeNum === 12);
            
            let html = `
                <div class="print-header">
                    <h2>REPÚBLICA DE ANGOLA</h2>
                    <h3>GOVERNO DA PROVÍNCIA DO ${headerData.provincia}</h3>
                    <h3>DIRECÇÃO MUNICIPAL DE EDUCAÇÃO DO ${headerData.municipio}</h3>
                    <div class="escola-nome">${headerData.escola}</div>
                    <h2>${isMiniPauta ? 'MINI PAUTA' : 'PAUTA DE NOTAS'}</h2>
                    <div class="info-linha">
                        <span>Ano Letivo: ${currentSelections.anoLetivo}</span>
                        <span>Classe: ${currentSelections.classe}ª</span>
                        <span>Turma: ${currentSelections.turma}</span>
                        <span>Disciplina: ${currentSelections.disciplina}</span>
                        <span>Trimestre: ${headerData.trimestre}</span>
                    </div>
                </div>
            `;
            
            if (isMiniPauta) {
                html += `
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th rowspan="2">Nº</th>
                                <th rowspan="2">NOME DO ALUNO</th>
                                <th colspan="3">I TRIMESTRE</th>
                                <th colspan="3">II TRIMESTRE</th>
                                <th colspan="5">III TRIMESTRE</th>
                                <th rowspan="2">MFED</th>
                            </tr>
                            <tr>
                                <th>MAC</th><th>NPT</th><th>MT</th>
                                <th>MAC</th><th>NPT</th><th>MT</th>
                                <th>MAC</th><th>MFD</th><th>NEO</th><th>EN</th><th>MEC</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                alunos.forEach((aluno, index) => {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td class="aluno-nome">${aluno.nome || ''}</td>
                            <td>${document.getElementById(`mac1_${aluno.id}`)?.value || '-'}</td>
                            <td>${document.getElementById(`npt1_${aluno.id}`)?.value || '-'}</td>
                            <td>${document.getElementById(`mt1_${aluno.id}`)?.textContent || '0.0'}</td>
                            <td>${document.getElementById(`mac2_${aluno.id}`)?.value || '-'}</td>
                            <td>${document.getElementById(`npt2_${aluno.id}`)?.value || '-'}</td>
                            <td>${document.getElementById(`mt2_${aluno.id}`)?.textContent || '0.0'}</td>
                            <td>${document.getElementById(`mac3_${aluno.id}`)?.value || '-'}</td>
                            <td>${document.getElementById(`mfd_${aluno.id}`)?.textContent || '0.0'}</td>
                            <td>${document.getElementById(`neo_${aluno.id}`)?.value || '-'}</td>
                            <td>${document.getElementById(`en_${aluno.id}`)?.value || '-'}</td>
                            <td>${document.getElementById(`mec_${aluno.id}`)?.textContent || '0.0'}</td>
                            <td><strong>${document.getElementById(`mfed_${aluno.id}`)?.textContent || '0.0'}</strong></td>
                        </tr>
                    `;
                });
            } else {
                const maxPontos = isPreSexta ? 10 : 20;
                html += `
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th rowspan="2">Nº</th>
                                <th rowspan="2">NOME DO ALUNO</th>
                                <th colspan="3">1º TRIMESTRE</th>
                                <th colspan="3">2º TRIMESTRE</th>
                                <th colspan="3">3º TRIMESTRE</th>
                                <th rowspan="2">MFD/${maxPontos}</th>
                                <th rowspan="2">CLASSIFICAÇÃO</th>
                            </tr>
                            <tr>
                                <th>MAC</th><th>NPT</th><th>MT</th>
                                <th>MAC</th><th>NPT</th><th>MT</th>
                                <th>MAC</th><th>NPT</th><th>MT</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                alunos.forEach((aluno, index) => {
                    const row = document.querySelector(`tr[data-aluno-id="${aluno.id}"]`);
                    if (row) {
                        const mac1 = row.querySelector(`input[data-field="mac_t1"][data-aluno="${aluno.id}"]`)?.value || '';
                        const npt1 = row.querySelector(`input[data-field="npt_t1"][data-aluno="${aluno.id}"]`)?.value || '';
                        const mt1 = document.getElementById(`mt1_${aluno.id}`)?.textContent || '0.0';
                        const mac2 = row.querySelector(`input[data-field="mac_t2"][data-aluno="${aluno.id}"]`)?.value || '';
                        const npt2 = row.querySelector(`input[data-field="npt_t2"][data-aluno="${aluno.id}"]`)?.value || '';
                        const mt2 = document.getElementById(`mt2_${aluno.id}`)?.textContent || '0.0';
                        const mac3 = row.querySelector(`input[data-field="mac_t3"][data-aluno="${aluno.id}"]`)?.value || '';
                        const npt3 = row.querySelector(`input[data-field="npt_t3"][data-aluno="${aluno.id}"]`)?.value || '';
                        const mt3 = document.getElementById(`mt3_${aluno.id}`)?.textContent || '0.0';
                        const mfd = document.getElementById(`mfd_${aluno.id}`)?.textContent || '0.0';
                        const classificacao = document.getElementById(`class_${aluno.id}`)?.textContent || '';
                        
                        html += `
                            <tr>
                                <td>${index + 1}</td>
                                <td class="aluno-nome">${aluno.nome || ''}</td>
                                <td>${mac1 || '-'}</td>
                                <td>${npt1 || '-'}</td>
                                <td>${mt1}</td>
                                <td>${mac2 || '-'}</td>
                                <td>${npt2 || '-'}</td>
                                <td>${mt2}</td>
                                <td>${mac3 || '-'}</td>
                                <td>${npt3 || '-'}</td>
                                <td>${mt3}</td>
                                <td><strong>${mfd}</strong></td>
                                <td>${classificacao}</td>
                            </tr>
                        `;
                    }
                });
            }
            
            html += `
                    </tbody>
                </table>
                <div class="print-footer">
                    <div class="assinatura">
                        <div>O Professor</div>
                        <div class="linha">________________________</div>
                        <div>${headerData.professor}</div>
                    </div>
                    <div class="assinatura">
                        <div>O Director Pedagógico</div>
                        <div class="linha">________________________</div>
                        <div>${headerData.diretor || ''}</div>
                    </div>
                </div>
            `;
            
            printContent.innerHTML = html;
        }

        // ============================================
        // EDIÇÃO DE CABEÇALHO
        // ============================================
        function openEditHeaderModal() {
            document.getElementById('editEscola').value = headerData.escola || '<?= htmlspecialchars($escola_nome) ?>';
            document.getElementById('editProvincia').value = headerData.provincia || 'ICOLO E BENGO';
            document.getElementById('editMunicipio').value = headerData.municipio || 'CALUMBO';
            document.getElementById('editAnoLetivo').value = currentSelections.anoLetivo;
            document.getElementById('editClasse').value = currentSelections.classe ? currentSelections.classe + 'ª' : '';
            document.getElementById('editTurma').value = currentSelections.turma || '';
            document.getElementById('editDisciplina').value = currentSelections.disciplina || '';
            document.getElementById('editProfessor').value = headerData.professor || '<?= htmlspecialchars($usuario_nome) ?>';
            document.getElementById('editDiretor').value = headerData.diretor || '';
            
            document.getElementById('editHeaderModal').style.display = 'flex';
        }

        function closeEditHeaderModal() {
            document.getElementById('editHeaderModal').style.display = 'none';
        }

        function saveHeaderChanges() {
            headerData = {
                escola: document.getElementById('editEscola').value,
                provincia: document.getElementById('editProvincia').value,
                municipio: document.getElementById('editMunicipio').value,
                trimestre: document.getElementById('editTrimestre').value,
                professor: document.getElementById('editProfessor').value,
                diretor: document.getElementById('editDiretor').value
            };
            
            const novoAnoLetivo = document.getElementById('editAnoLetivo').value;
            if (novoAnoLetivo && novoAnoLetivo !== currentSelections.anoLetivo) {
                document.getElementById('anoLetivo').value = novoAnoLetivo;
                currentSelections.anoLetivo = novoAnoLetivo;
                document.getElementById('anoLetivoValor').textContent = novoAnoLetivo;
            }
            
            updatePrintView();
            closeEditHeaderModal();
        }

        // ============================================
        // EVENTOS
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            loadZoom();
            
            // Habilitar busca quando disciplina for selecionada
            document.getElementById('disciplina').addEventListener('change', function() {
                const classe = document.getElementById('classe').value;
                const turma = document.getElementById('turma').value;
                const disciplina = this.value;
                
                currentSelections.disciplina = disciplina;
                
                if (classe && turma && disciplina) {
                    document.querySelector('.btn-search').disabled = false;
                    updateStatus(`Pronto para buscar alunos: ${classe}ª - Turma ${turma} - ${disciplina}`);
                } else {
                    document.querySelector('.btn-search').disabled = true;
                }
            });
        });

        // Fechar modais com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePrintView();
                closeEditHeaderModal();
            }
        });

        // Fechar modais clicando fora
        document.querySelector('.print-view-modal').addEventListener('click', function(e) {
            if (e.target === this) closePrintView();
        });
        document.querySelector('.edit-header-modal').addEventListener('click', function(e) {
            if (e.target === this) closeEditHeaderModal();
        });
    </script>

<?php include $base_path . '/modules/escola/includes/footer_escola.php'; ?>
</body>
</html>