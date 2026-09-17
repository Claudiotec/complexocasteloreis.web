<?php
// ============================================
// notas.php - Lançamento de Notas (VERSÃO FINAL)
// Acesso: http://10.159.76.20/softgest_web/notas.php
// ============================================

// Configuração
$base_path = __DIR__;
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';

// Sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Dados do usuário
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Professor';
$usuario_email = $_SESSION['usuario_email'] ?? '';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$escola_nome = $_SESSION['escola_nome'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';

// ==========================================
// BUSCAR DADOS
// ==========================================
$disciplinas = [];
$turmas = [];
$classes = [];

try {
    $sql = "SELECT * FROM destribuicao_professores WHERE professor_id = ? OR professor_nome LIKE ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, '%' . $usuario_nome . '%']);
    $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($distribuicoes)) {
        $sql = "SELECT * FROM destribuicao_professores WHERE professor_id = 13";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([13]);
        $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    foreach ($distribuicoes as $dist) {
        if (!empty($dist['classe']) && !in_array($dist['classe'], $classes)) {
            $classes[] = trim($dist['classe']);
        }
        if (!empty($dist['turma_nome']) && !in_array($dist['turma_nome'], $turmas)) {
            $turmas[] = trim($dist['turma_nome']);
        }
        if (!empty($dist['disciplinas'])) {
            $discs = array_map('trim', explode(',', $dist['disciplinas']));
            foreach ($discs as $disc) {
                if (!empty($disc) && !in_array($disc, $disciplinas)) {
                    $disciplinas[] = $disc;
                }
            }
        }
    }
    
    sort($classes);
    sort($turmas);
    sort($disciplinas);
    
} catch (Exception $e) {
    error_log("Erro ao buscar distribuições: " . $e->getMessage());
}

if (empty($classes)) $classes = ['4'];
if (empty($turmas)) $turmas = ['4AM'];
if (empty($disciplinas)) $disciplinas = ['Matemática', 'Biologia'];

// ==========================================
// PROCESSAR REQUISIÇÕES AJAX
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'buscar_alunos_notas') {
        $turma = $_POST['turma'] ?? '';
        $disciplina = $_POST['disciplina'] ?? '';
        $ano_letivo = $_POST['ano_letivo'] ?? date('Y') . '/' . (date('Y') + 1);
        
        try {
            $sql = "SELECT 
                        id,
                        nome,
                        Sexo as sexo,
                        Idade as idade,
                        TURMA as turma,
                        Classe as classe
                    FROM alunos 
                    WHERE TURMA = ? 
                    AND status = 'ativo'
                    ORDER BY nome ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$turma]);
            $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $notas = [];
            if (!empty($alunos)) {
                $ids = array_column($alunos, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                $sql_notas = "SELECT 
                                id_aluno,
                                mac_t1, npt_t1, mt_t1,
                                mac_t2, npt_t2, mt_t2,
                                mac_t3, npt_t3, mt_t3,
                                mfd, classificacao
                            FROM notas 
                            WHERE id_aluno IN ($placeholders) 
                            AND disciplina = ? 
                            AND ano_letivo = ?";
                
                $params = array_merge($ids, [$disciplina, $ano_letivo]);
                $stmt_notas = $pdo->prepare($sql_notas);
                $stmt_notas->execute($params);
                $notas_list = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($notas_list as $n) {
                    $notas[$n['id_aluno']] = $n;
                }
            }
            
            echo json_encode([
                'success' => true,
                'alunos' => $alunos,
                'notas' => $notas,
                'total' => count($alunos)
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    if ($action === 'salvar_notas') {
        $notas = json_decode($_POST['notas'] ?? '[]', true);
        $ano_letivo = $_POST['ano_letivo'] ?? date('Y') . '/' . (date('Y') + 1);
        
        try {
            $pdo->beginTransaction();
            $salvos = 0;
            
            foreach ($notas as $nota) {
                $id_aluno = $nota['id_aluno'] ?? 0;
                $disciplina = $nota['disciplina'] ?? '';
                $turma = $nota['turma'] ?? '';
                $classe = $nota['classe'] ?? '';
                
                if (!$id_aluno || !$disciplina) {
                    continue;
                }
                
                $mac_t1 = !empty($nota['mac_t1']) ? floatval(str_replace(',', '.', $nota['mac_t1'])) : null;
                $npt_t1 = !empty($nota['npt_t1']) ? floatval(str_replace(',', '.', $nota['npt_t1'])) : null;
                $mac_t2 = !empty($nota['mac_t2']) ? floatval(str_replace(',', '.', $nota['mac_t2'])) : null;
                $npt_t2 = !empty($nota['npt_t2']) ? floatval(str_replace(',', '.', $nota['npt_t2'])) : null;
                $mac_t3 = !empty($nota['mac_t3']) ? floatval(str_replace(',', '.', $nota['mac_t3'])) : null;
                $npt_t3 = !empty($nota['npt_t3']) ? floatval(str_replace(',', '.', $nota['npt_t3'])) : null;
                
                $mt_t1 = ($mac_t1 !== null && $npt_t1 !== null) ? ($mac_t1 + $npt_t1) / 2 : null;
                $mt_t2 = ($mac_t2 !== null && $npt_t2 !== null) ? ($mac_t2 + $npt_t2) / 2 : null;
                $mt_t3 = ($mac_t3 !== null && $npt_t3 !== null) ? ($mac_t3 + $npt_t3) / 2 : null;
                
                $medias = [];
                if ($mt_t1 !== null) $medias[] = $mt_t1;
                if ($mt_t2 !== null) $medias[] = $mt_t2;
                if ($mt_t3 !== null) $medias[] = $mt_t3;
                $mfd = !empty($medias) ? array_sum($medias) / count($medias) : null;
                
                $classificacao = null;
                if ($mfd !== null) {
                    if ($mfd >= 9) $classificacao = 'MUITO BOM';
                    else if ($mfd >= 7) $classificacao = 'BOM';
                    else if ($mfd >= 5) $classificacao = 'SUFICIENTE';
                    else if ($mfd >= 3) $classificacao = 'MEDÍOCRE';
                    else $classificacao = 'MAU';
                }
                
                $sql_check = "SELECT id FROM notas WHERE id_aluno = ? AND disciplina = ? AND ano_letivo = ?";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$id_aluno, $disciplina, $ano_letivo]);
                $existe = $stmt_check->fetch();
                
                if ($existe) {
                    $sql = "UPDATE notas SET 
                                mac_t1 = ?, npt_t1 = ?, mt_t1 = ?,
                                mac_t2 = ?, npt_t2 = ?, mt_t2 = ?,
                                mac_t3 = ?, npt_t3 = ?, mt_t3 = ?,
                                mfd = ?,
                                classificacao = ?,
                                turma = ?, classe = ?,
                                updated_at = NOW()
                            WHERE id_aluno = ? AND disciplina = ? AND ano_letivo = ?";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $mac_t1, $npt_t1, $mt_t1,
                        $mac_t2, $npt_t2, $mt_t2,
                        $mac_t3, $npt_t3, $mt_t3,
                        $mfd,
                        $classificacao,
                        $turma, $classe,
                        $id_aluno, $disciplina, $ano_letivo
                    ]);
                } else {
                    $sql = "INSERT INTO notas (
                                id_aluno, disciplina, turma, classe, ano_letivo,
                                mac_t1, npt_t1, mt_t1,
                                mac_t2, npt_t2, mt_t2,
                                mac_t3, npt_t3, mt_t3,
                                mfd, classificacao
                            ) VALUES (
                                ?, ?, ?, ?, ?,
                                ?, ?, ?,
                                ?, ?, ?,
                                ?, ?, ?,
                                ?, ?
                            )";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $id_aluno, $disciplina, $turma, $classe, $ano_letivo,
                        $mac_t1, $npt_t1, $mt_t1,
                        $mac_t2, $npt_t2, $mt_t2,
                        $mac_t3, $npt_t3, $mt_t3,
                        $mfd, $classificacao
                    ]);
                }
                $salvos++;
            }
            
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => $salvos . " notas salvas com sucesso!",
                'salvos' => $salvos
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao salvar notas: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($escola_nome) ?> - Lançamento de Notas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
        
        .header {
            background: linear-gradient(135deg, #1a2332, #2c3e50);
            color: white;
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header h1 { font-size: 20px; }
        .header h1 span { color: #c9a84c; }
        .user-info { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 13px; }
        
        .btn {
            padding: 7px 15px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
            transition: all 0.3s;
            color: white;
            font-size: 12px;
        }
        .btn-print-mini { background: #8e44ad; }
        .btn-print-mini:hover { background: #6c3483; }
        .btn-print { background: #e67e22; }
        .btn-print:hover { background: #d35400; }
        .btn-back { background: #27ae60; }
        .btn-back:hover { background: #1e8449; }
        .btn-save { background: linear-gradient(135deg, #3498db, #2980b9); }
        .btn-save:hover:not(:disabled) { transform: translateY(-1px); }
        .btn-save:disabled { background: #95a5a6; cursor: not-allowed; }
        .btn-export { background: #9b59b6; }
        .btn-export:hover { background: #8e44ad; }
        .btn-clear { background: #95a5a6; }
        .btn-clear:hover { background: #7f8c8d; }
        .btn-search {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            padding: 9px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-search:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(39,174,96,0.3); }
        .btn-search:disabled { background: #95a5a6; cursor: not-allowed; opacity: 0.7; }
        .btn-forcar {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            padding: 9px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-left: 5px;
        }
        .btn-forcar:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(231,76,60,0.3); }
        
        .container { padding: 15px 20px; max-width: 1400px; margin: 0 auto; }
        .filters {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }
        .filter-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label {
            display: block;
            margin-bottom: 4px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 11px;
        }
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 8px 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            background: white;
            transition: border-color 0.3s;
        }
        .filter-group select:focus, .filter-group input:focus { border-color: #3498db; outline: none; }
        .filter-group select:disabled { background: #f5f5f5; cursor: not-allowed; }
        
        .status-bar {
            background: white;
            padding: 10px 18px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            border-left: 4px solid #3498db;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        
        .notes-table-wrapper {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow-x: auto;
            margin-top: 12px;
            max-height: 70vh;
        }
        .notes-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            min-width: 1000px;
        }
        .notes-table th {
            background: #2c3e50;
            color: white;
            padding: 8px 5px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #1a2332;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 11px;
        }
        .notes-table td {
            padding: 5px 4px;
            border: 1px solid #e8ecf0;
            text-align: center;
            vertical-align: middle;
            font-size: 12px;
        }
        .notes-table tr:nth-child(even) { background: #f8f9fa; }
        .notes-table tr:hover { background: #eaf3fa; }
        
        .notes-table .nome-col {
            text-align: left;
            padding-left: 8px;
            font-weight: 600;
            color: #1a2332;
            min-width: 160px;
            max-width: 200px;
        }
        .notes-table .num-col {
            font-weight: 700;
            color: #c9a84c;
            width: 35px;
        }
        .notes-table .input-nota {
            width: 50px;
            padding: 4px;
            border: 2px solid #ddd;
            border-radius: 4px;
            text-align: center;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .notes-table .input-nota:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52,152,219,0.15);
            outline: none;
        }
        .notes-table .input-nota.has-value {
            background: #e8f4fc;
            border-color: #3498db;
        }
        .notes-table .mt-cell {
            font-weight: 600;
            color: #2c3e50;
            background: #e3f2fd;
            padding: 4px 6px;
            border-radius: 4px;
            display: inline-block;
            min-width: 40px;
            font-size: 13px;
        }
        .notes-table .mfd-cell {
            font-weight: 700;
            color: #155724;
            background: #d4edda;
            padding: 4px 6px;
            border-radius: 4px;
            border: 2px solid #28a745;
            display: inline-block;
            min-width: 40px;
            font-size: 14px;
        }
        .notes-table .class-cell {
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            min-width: 80px;
            font-size: 11px;
        }
        .class-muito-bom { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .class-bom { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .class-suficiente { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .class-mediocre { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .class-mau { background: #f5c6cb; color: #721c24; border: 1px solid #f1b0b7; }
        
        .info-text {
            padding: 30px;
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 15px;
            border: 2px dashed #ddd;
        }
        .loading {
            padding: 30px;
            text-align: center;
            color: #7f8c8d;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .stats-bar {
            display: flex;
            gap: 12px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 100px;
            flex: 1;
        }
        .stat-value { font-size: 20px; font-weight: 700; color: #1a2332; }
        .stat-label { font-size: 10px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .classification-info {
            background: #e8f4fc;
            padding: 8px 16px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 12px;
            color: #2c3e50;
            border-left: 4px solid #3498db;
            display: none;
        }
        
        .zoom-controls {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            padding: 8px;
            display: flex;
            gap: 8px;
            z-index: 999;
        }
        .zoom-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            border: none;
            font-size: 16px;
            cursor: pointer;
        }
        .zoom-btn:hover { background: #2980b9; }
        .zoom-display {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 45px;
            font-weight: bold;
            color: #1a2332;
            font-size: 12px;
        }
        
        .print-view-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            overflow: auto;
            padding: 20px;
        }
        .print-view-content {
            background: white;
            max-width: 1100px;
            margin: 20px auto;
            padding: 30px 40px;
            border-radius: 8px;
            position: relative;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            font-family: 'Times New Roman', Times, serif;
        }
        .close-print-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 26px;
            cursor: pointer;
            color: #666;
            background: none;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }
        .close-print-btn:hover { background: #f0f0f0; }
        .edit-header-btn-print {
            position: absolute;
            top: 10px;
            right: 60px;
            background: #f39c12;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
            font-family: Arial, sans-serif;
        }
        .edit-header-btn-print:hover { background: #e67e22; }
        .print-actions {
            text-align: center;
            margin-top: 18px;
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .print-action-btn {
            padding: 8px 22px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: Arial, sans-serif;
        }
        .print-action-btn:hover { background: #2980b9; }
        .print-action-btn.print { background: #27ae60; }
        .print-action-btn.print:hover { background: #219653; }
        
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            font-family: 'Times New Roman', Times, serif;
        }
        .print-header h2 { font-size: 15px; margin: 2px 0; font-weight: bold; }
        .print-header h3 { font-size: 13px; margin: 2px 0; font-weight: normal; }
        .print-header .escola-nome { font-size: 17px; font-weight: bold; margin: 6px 0; text-transform: uppercase; }
        .print-header .info-linha {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 13px;
            flex-wrap: wrap;
            gap: 6px;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
        }
        .print-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 8px 0;
        }
        .print-table th {
            background: #2c3e50;
            color: white;
            padding: 5px 3px;
            text-align: center;
            border: 1px solid #000;
            font-size: 10px;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .print-table td {
            padding: 3px 3px;
            border: 1px solid #000;
            text-align: center;
            font-size: 10px;
            font-family: 'Times New Roman', Times, serif;
        }
        .print-table .aluno-nome { text-align: left; padding-left: 6px; }
        .print-footer {
            margin-top: 35px;
            display: flex;
            justify-content: space-around;
            font-size: 12px;
            flex-wrap: wrap;
            gap: 20px;
            font-family: 'Times New Roman', Times, serif;
        }
        .print-footer .assinatura { text-align: center; min-width: 160px; }
        .print-footer .linha {
            border-top: 1px solid #000;
            margin-top: 30px;
            padding-top: 4px;
            width: 160px;
            margin-left: auto;
            margin-right: auto;
        }
        
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
            padding: 25px 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .edit-header-content h3 { margin-bottom: 15px; color: #2c3e50; font-size: 18px; }
        .edit-header-form-group { margin-bottom: 10px; }
        .edit-header-form-group label { display: block; margin-bottom: 3px; font-weight: 600; color: #34495e; font-size: 12px; }
        .edit-header-form-group input, .edit-header-form-group select {
            width: 100%;
            padding: 7px 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
        }
        .edit-header-form-group input:focus, .edit-header-form-group select:focus { border-color: #3498db; outline: none; }
        .edit-header-buttons {
            display: flex;
            gap: 10px;
            margin-top: 18px;
            justify-content: flex-end;
        }
        .edit-header-save { background: #27ae60; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .edit-header-save:hover { background: #1e8449; }
        .edit-header-cancel { background: #95a5a6; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .edit-header-cancel:hover { background: #7f8c8d; }
        
        /* ============================================
           CORREÇÃO PARA IMPRESSÃO
           ============================================ */
        @media print {
            body * { visibility: hidden !important; }
            .print-view-modal,
            .print-view-modal *,
            .print-view-content,
            .print-view-content *,
            #printContent,
            #printContent * {
                visibility: visible !important;
            }
            .no-print,
            .close-print-btn,
            .edit-header-btn-print,
            .print-actions,
            .print-action-btn {
                display: none !important;
                visibility: hidden !important;
            }
            .print-view-modal {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background: white !important;
                padding: 20px !important;
                margin: 0 !important;
                z-index: 9999 !important;
            }
            .print-view-content {
                margin: 0 !important;
                padding: 20px !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
                background: white !important;
            }
            .print-table { width: 100% !important; border-collapse: collapse !important; font-size: 11px !important; }
            .print-table th {
                background: #2c3e50 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                padding: 5px 3px !important;
                border: 1px solid #000 !important;
                font-size: 10px !important;
            }
            .print-table td { padding: 3px 3px !important; border: 1px solid #000 !important; font-size: 10px !important; }
            .print-header h2, .print-header h3, .print-header .escola-nome, .print-header .info-linha { visibility: visible !important; }
            .print-footer { visibility: visible !important; }
        }
        
        @media (max-width: 768px) {
            .filter-row { flex-direction: column; }
            .filter-group { min-width: 100%; }
            .user-info { justify-content: center; }
            .header { flex-direction: column; text-align: center; }
            .notes-table { font-size: 10px; min-width: 700px; }
            .notes-table .input-nota { width: 38px; padding: 3px; font-size: 10px; }
        }
    </style>
</head>
<body>

    <!-- ===== HEADER ===== -->
    <div class="header">
        <h1>📝 <span>Lançamento de Notas</span></h1>
        <div class="user-info">
            <span>👤 <?= htmlspecialchars($usuario_nome) ?></span>
            <span id="anoLetivoDisplay">📅 <span id="anoLetivoValor"><?= date('Y') . '/' . (date('Y') + 1) ?></span></span>
            <button class="btn btn-print-mini no-print" onclick="openPrintView()">📋 Visualizar Pauta</button>
            <button class="btn btn-print no-print" onclick="window.print()">🖨️ Imprimir</button>
            <a href="dashboard.php" class="btn btn-back no-print">← Voltar</a>
        </div>
    </div>

    <div class="container">

        <!-- STATUS -->
        <div class="status-bar no-print">
            <div id="statusText"><strong>Status:</strong> Selecione as opções abaixo</div>
            <div id="selectionInfo"></div>
        </div>

        <!-- FILTROS -->
        <div class="filters no-print">
            <div class="filter-row">
                <div class="filter-group">
                    <label>📅 Ano Letivo:</label>
                    <input type="text" id="anoLetivo" value="<?= date('Y') . '/' . (date('Y') + 1) ?>">
                </div>
                <div class="filter-group">
                    <label>📚 Classe:</label>
                    <select id="classe">
                        <option value="">Selecione</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?>ª Classe</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>👥 Turma:</label>
                    <select id="turma" disabled>
                        <option value="">Selecione</option>
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>">Turma <?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📖 Disciplina:</label>
                    <select id="disciplina" disabled>
                        <option value="">Selecione</option>
                        <?php foreach ($disciplinas as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn-search" onclick="carregarAlunos()" disabled>🔍 Buscar</button>
                <button class="btn-forcar" onclick="forcarBusca()">⚡ Forçar</button>
            </div>
        </div>

        <!-- CLASSIFICAÇÃO INFO -->
        <div id="classificationInfo" class="classification-info no-print">
            <span>📊</span>
            <div>
                <div><strong>Sistema 0-10 pontos</strong></div>
                <div style="font-size:11px;color:#666;">MUITO BOM: 9-10 | BOM: 7-8 | SUFICIENTE: 5-6 | MEDÍOCRE: 3-4 | MAU: 0-2</div>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-bar no-print" id="statsBar" style="display:none;">
            <div class="stat-card"><div class="stat-value" id="statTotal">0</div><div class="stat-label">Total</div></div>
            <div class="stat-card"><div class="stat-value" id="statMedia">0.0</div><div class="stat-label">Média</div></div>
            <div class="stat-card"><div class="stat-value" id="statAprovados">0</div><div class="stat-label">Aprovados</div></div>
            <div class="stat-card"><div class="stat-value" id="statReprovados">0</div><div class="stat-label">Reprovados</div></div>
        </div>

        <!-- TABELA -->
        <div class="notes-table-wrapper">
            <div id="studentsTable">
                <div class="info-text">🔍 Selecione classe, turma e disciplina</div>
            </div>
        </div>

        <!-- BOTÕES -->
        <div class="button-group no-print">
            <button class="btn btn-save" onclick="salvarNotas()" disabled>💾 Salvar</button>
            <button class="btn btn-export" onclick="exportarExcel()">📊 Excel</button>
            <button class="btn btn-clear" onclick="limpar()">🗑️ Limpar</button>
        </div>
    </div>

    <!-- ZOOM -->
    <div class="zoom-controls no-print">
        <button class="zoom-btn" onclick="zoomOut()">−</button>
        <div class="zoom-display" id="zoomLevel">100%</div>
        <button class="zoom-btn" onclick="zoomIn()">+</button>
    </div>

    <!-- PRINT VIEW -->
    <div id="printViewModal" class="print-view-modal">
        <div class="print-view-content">
            <button class="close-print-btn no-print" onclick="closePrintView()">&times;</button>
            <button class="edit-header-btn-print no-print" onclick="openEditHeader()">✏️ Editar</button>
            <div id="printContent"></div>
            <div class="print-actions no-print">
                <button class="print-action-btn print" onclick="imprimirPauta()">🖨️ Imprimir</button>
                <button class="print-action-btn" onclick="closePrintView()">✖️ Fechar</button>
            </div>
        </div>
    </div>

    <!-- EDIT HEADER -->
    <div id="editHeaderModal" class="edit-header-modal">
        <div class="edit-header-content">
            <h3>✏️ Editar Cabeçalho</h3>
            <div class="edit-header-form-group"><label>Escola:</label><input type="text" id="editEscola" value="<?= htmlspecialchars($escola_nome) ?>"></div>
            <div class="edit-header-form-group"><label>Província:</label><input type="text" id="editProvincia" value="ICOLO E BENGO"></div>
            <div class="edit-header-form-group"><label>Município:</label><input type="text" id="editMunicipio" value="CALUMBO"></div>
            <div class="edit-header-form-group"><label>Ano Letivo:</label><input type="text" id="editAnoLetivo" value="<?= date('Y') . '/' . (date('Y') + 1) ?>"></div>
            <div class="edit-header-form-group"><label>Classe:</label><input type="text" id="editClasse"></div>
            <div class="edit-header-form-group"><label>Turma:</label><input type="text" id="editTurma"></div>
            <div class="edit-header-form-group"><label>Disciplina:</label><input type="text" id="editDisciplina"></div>
            <div class="edit-header-form-group"><label>Trimestre:</label>
                <select id="editTrimestre">
                    <option value="I TRIMESTRE">I TRIMESTRE</option>
                    <option value="II TRIMESTRE">II TRIMESTRE</option>
                    <option value="III TRIMESTRE">III TRIMESTRE</option>
                    <option value="FINAL" selected>FINAL</option>
                </select>
            </div>
            <div class="edit-header-form-group"><label>Professor:</label><input type="text" id="editProfessor" value="<?= htmlspecialchars($usuario_nome) ?>"></div>
            <div class="edit-header-form-group"><label>Director:</label><input type="text" id="editDiretor"></div>
            <div class="edit-header-buttons">
                <button class="edit-header-save" onclick="salvarHeader()">Salvar</button>
                <button class="edit-header-cancel" onclick="fecharEditHeader()">Cancelar</button>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // VARIÁVEIS
        // ============================================
        var alunos = [];
        var currentSelections = { anoLetivo: '', classe: '', turma: '', disciplina: '' };
        var currentZoom = 100;
        var headerData = {
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
        function zoomIn() { if (currentZoom < 120) { currentZoom += 5; updateZoom(); } }
        function zoomOut() { if (currentZoom > 70) { currentZoom -= 5; updateZoom(); } }
        function updateZoom() {
            var s = currentZoom / 100;
            document.body.style.zoom = s;
            document.body.style.transform = 'scale(' + s + ')';
            document.body.style.transformOrigin = '0 0';
            document.body.style.width = (100 / s) + '%';
            document.getElementById('zoomLevel').textContent = currentZoom + '%';
            localStorage.setItem('notasZoom', currentZoom);
        }
        function loadZoom() {
            var z = localStorage.getItem('notasZoom');
            if (z) { currentZoom = parseInt(z); updateZoom(); }
        }

        // ============================================
        // CLASSIFICAÇÃO
        // ============================================
        function getClassificacao(mfd) {
            if (!mfd || mfd <= 0) return { texto: '', classe: '' };
            if (mfd >= 9) return { texto: 'MUITO BOM', classe: 'class-muito-bom' };
            if (mfd >= 7) return { texto: 'BOM', classe: 'class-bom' };
            if (mfd >= 5) return { texto: 'SUFICIENTE', classe: 'class-suficiente' };
            if (mfd >= 3) return { texto: 'MEDÍOCRE', classe: 'class-mediocre' };
            return { texto: 'MAU', classe: 'class-mau' };
        }

        // ============================================
        // FILTROS
        // ============================================
        document.getElementById('classe').onchange = function() {
            document.getElementById('turma').disabled = false;
            document.getElementById('disciplina').disabled = true;
            document.querySelector('.btn-search').disabled = true;
        };
        document.getElementById('turma').onchange = function() {
            document.getElementById('disciplina').disabled = false;
            document.querySelector('.btn-search').disabled = true;
        };
        document.getElementById('disciplina').onchange = function() {
            var c = document.getElementById('classe').value;
            var t = document.getElementById('turma').value;
            if (c && t && this.value) {
                document.querySelector('.btn-search').disabled = false;
                document.getElementById('selectionInfo').textContent = '📚 ' + c + 'ª • 👥 ' + t + ' • 📖 ' + this.value;
            }
        };

        // ============================================
        // CARREGAR ALUNOS
        // ============================================
        function forcarBusca() {
            var c = document.getElementById('classe').value;
            var t = document.getElementById('turma').value;
            var d = document.getElementById('disciplina').value;
            if (!c || !t || !d) {
                alert('Selecione classe, turma e disciplina!');
                return;
            }
            carregarAlunos();
        }

        function carregarAlunos() {
            var anoLetivo = document.getElementById('anoLetivo').value;
            var classe = document.getElementById('classe').value;
            var turma = document.getElementById('turma').value;
            var disciplina = document.getElementById('disciplina').value;

            if (!classe || !turma || !disciplina) {
                alert('Selecione todos os campos');
                return;
            }

            currentSelections = { anoLetivo: anoLetivo, classe: classe, turma: turma, disciplina: disciplina };

            document.getElementById('studentsTable').innerHTML = 
                '<div class="loading"><div class="spinner"></div><div>Carregando alunos...</div></div>';
            document.querySelector('.btn-save').disabled = true;

            var formData = new FormData();
            formData.append('action', 'buscar_alunos_notas');
            formData.append('turma', turma);
            formData.append('disciplina', disciplina);
            formData.append('ano_letivo', anoLetivo);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.alunos && data.alunos.length > 0) {
                    alunos = data.alunos;
                    renderTabela(alunos, data.notas || {});
                    document.querySelector('.btn-save').disabled = false;
                    document.getElementById('selectionInfo').textContent = 
                        '📚 ' + classe + 'ª • 👥 Turma ' + turma + ' • 📖 ' + disciplina + ' • 📅 ' + anoLetivo + ' • Total: ' + alunos.length;
                    document.getElementById('statusText').innerHTML = '<strong>Status:</strong> ✅ ' + alunos.length + ' alunos carregados';
                    document.getElementById('classificationInfo').style.display = 'flex';
                } else {
                    document.getElementById('studentsTable').innerHTML = '<div class="info-text">📭 Nenhum aluno encontrado</div>';
                }
            })
            .catch(function(error) {
                document.getElementById('studentsTable').innerHTML = '<div class="info-text">❌ Erro ao carregar</div>';
            });
        }

        // ============================================
        // RENDERIZAR TABELA
        // ============================================
        function renderTabela(alunos, notas) {
            var html = 
                '<table class="notes-table">' +
                '<thead><tr>' +
                '<th class="num-col" rowspan="2">Nº</th>' +
                '<th rowspan="2" style="text-align:left;min-width:160px;">Nome do Aluno</th>' +
                '<th colspan="3">1º Trimestre</th>' +
                '<th colspan="3">2º Trimestre</th>' +
                '<th colspan="3">3º Trimestre</th>' +
                '<th rowspan="2" style="min-width:55px;">MFD/10</th>' +
                '<th rowspan="2" style="min-width:80px;">CLASSIFICAÇÃO</th>' +
                '</tr><tr>' +
                '<th>MAC</th><th>NPT</th><th>MT</th>' +
                '<th>MAC</th><th>NPT</th><th>MT</th>' +
                '<th>MAC</th><th>NPT</th><th>MT</th>' +
                '</tr></thead><tbody>';

            for (var idx = 0; idx < alunos.length; idx++) {
                var aluno = alunos[idx];
                var n = notas[aluno.id] || {};
                
                var mac1 = n.mac_t1 || '';
                var npt1 = n.npt_t1 || '';
                var mt1 = n.mt_t1 || 0;
                
                var mac2 = n.mac_t2 || '';
                var npt2 = n.npt_t2 || '';
                var mt2 = n.mt_t2 || 0;
                
                var mac3 = n.mac_t3 || '';
                var npt3 = n.npt_t3 || '';
                var mt3 = n.mt_t3 || 0;
                
                var mfd = n.mfd || 0;
                var classif = getClassificacao(mfd);

                var info = (aluno.sexo ? aluno.sexo + ' • ' : '') + (aluno.idade ? aluno.idade + ' anos' : '');

                html += 
                    '<tr data-id="' + aluno.id + '">' +
                    '<td class="num-col">' + (idx + 1) + '</td>' +
                    '<td class="nome-col"><strong>' + aluno.nome + '</strong>' +
                    (info ? '<br><span style="font-size:10px;color:#888;">' + info + '</span>' : '') + '</td>' +
                    '<td><input type="text" class="input-nota" value="' + mac1 + '" data-trim="1" data-aluno="' + aluno.id + '" onchange="validarNota(this, 1, \'' + aluno.id + '\')"></td>' +
                    '<td><input type="text" class="input-nota" value="' + npt1 + '" data-trim="1" data-aluno="' + aluno.id + '" onchange="validarNota(this, 1, \'' + aluno.id + '\')"></td>' +
                    '<td><span class="mt-cell" id="mt1_' + aluno.id + '">' + parseFloat(mt1).toFixed(1) + '</span></td>' +
                    
                    '<td><input type="text" class="input-nota" value="' + mac2 + '" data-trim="2" data-aluno="' + aluno.id + '" onchange="validarNota(this, 2, \'' + aluno.id + '\')"></td>' +
                    '<td><input type="text" class="input-nota" value="' + npt2 + '" data-trim="2" data-aluno="' + aluno.id + '" onchange="validarNota(this, 2, \'' + aluno.id + '\')"></td>' +
                    '<td><span class="mt-cell" id="mt2_' + aluno.id + '">' + parseFloat(mt2).toFixed(1) + '</span></td>' +
                    
                    '<td><input type="text" class="input-nota" value="' + mac3 + '" data-trim="3" data-aluno="' + aluno.id + '" onchange="validarNota(this, 3, \'' + aluno.id + '\')"></td>' +
                    '<td><input type="text" class="input-nota" value="' + npt3 + '" data-trim="3" data-aluno="' + aluno.id + '" onchange="validarNota(this, 3, \'' + aluno.id + '\')"></td>' +
                    '<td><span class="mt-cell" id="mt3_' + aluno.id + '">' + parseFloat(mt3).toFixed(1) + '</span></td>' +
                    
                    '<td><span class="mfd-cell" id="mfd_' + aluno.id + '">' + parseFloat(mfd).toFixed(1) + '</span></td>' +
                    '<td><span id="class_' + aluno.id + '" class="class-cell ' + classif.classe + '">' + classif.texto + '</span></td>' +
                    '</tr>';
            }

            html += '</tbody></table>';
            document.getElementById('studentsTable').innerHTML = html;
            atualizarStats();
        }

        // ============================================
        // VALIDAR NOTA
        // ============================================
        function validarNota(input, trimestre, alunoId) {
            var valor = input.value.replace(',', '.');
            
            if (valor === '') {
                input.value = '';
                input.classList.remove('has-value');
                calcularMT(input, trimestre, alunoId);
                return;
            }
            
            var num = parseFloat(valor);
            if (isNaN(num)) {
                alert('Digite um número válido (ex: 5.5 ou 5,5)');
                input.value = '';
                input.focus();
                return;
            }
            
            if (num < 0 || num > 10) {
                alert('A nota deve estar entre 0 e 10');
                input.value = '';
                input.focus();
                return;
            }
            
            input.value = num.toString();
            input.classList.add('has-value');
            calcularMT(input, trimestre, alunoId);
        }

        // ============================================
        // CALCULAR MÉDIAS
        // ============================================
        function calcularMT(input, trimestre, alunoId) {
            var row = input.closest('tr');
            var inputs = row.querySelectorAll('input[data-trim="' + trimestre + '"]');
            
            var mac = 0, npt = 0;
            if (inputs[0] && inputs[0].value) {
                mac = parseFloat(inputs[0].value.replace(',', '.')) || 0;
            }
            if (inputs[1] && inputs[1].value) {
                npt = parseFloat(inputs[1].value.replace(',', '.')) || 0;
            }
            
            var mt = 0;
            if (mac > 0 && npt > 0) {
                mt = (mac + npt) / 2;
            }
            
            document.getElementById('mt' + trimestre + '_' + alunoId).textContent = mt.toFixed(1);
            calcularMFD(alunoId);
            atualizarStats();
        }

        function calcularMFD(alunoId) {
            var mt1 = parseFloat(document.getElementById('mt1_' + alunoId).textContent) || 0;
            var mt2 = parseFloat(document.getElementById('mt2_' + alunoId).textContent) || 0;
            var mt3 = parseFloat(document.getElementById('mt3_' + alunoId).textContent) || 0;
            
            var medias = [];
            if (mt1 > 0) medias.push(mt1);
            if (mt2 > 0) medias.push(mt2);
            if (mt3 > 0) medias.push(mt3);
            
            var mfd = 0;
            if (medias.length > 0) {
                var soma = 0;
                for (var i = 0; i < medias.length; i++) {
                    soma += medias[i];
                }
                mfd = soma / medias.length;
            }
            
            document.getElementById('mfd_' + alunoId).textContent = mfd.toFixed(1);
            
            var classif = getClassificacao(mfd);
            var classCell = document.getElementById('class_' + alunoId);
            if (classCell) {
                classCell.textContent = classif.texto;
                classCell.className = 'class-cell ' + classif.classe;
            }
        }

        // ============================================
        // ESTATÍSTICAS
        // ============================================
        function atualizarStats() {
            var rows = document.querySelectorAll('tr[data-id]');
            if (rows.length === 0) return;
            
            var soma = 0, total = 0, aprov = 0, reprov = 0;
            
            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                var id = row.dataset.id;
                var mfd = parseFloat(document.getElementById('mfd_' + id).textContent) || 0;
                if (mfd > 0) {
                    soma += mfd;
                    total++;
                    if (mfd >= 5) aprov++;
                    else reprov++;
                }
            }
            
            var media = total > 0 ? soma / total : 0;
            
            document.getElementById('statTotal').textContent = alunos.length;
            document.getElementById('statMedia').textContent = media.toFixed(1);
            document.getElementById('statAprovados').textContent = aprov;
            document.getElementById('statReprovados').textContent = reprov;
            document.getElementById('statsBar').style.display = 'flex';
        }

        // ============================================
        // SALVAR NOTAS
        // ============================================
        function salvarNotas() {
            if (!confirm('Salvar todas as notas?')) return;
            
            var ano = document.getElementById('anoLetivo').value;
            var disc = document.getElementById('disciplina').value;
            var turma = document.getElementById('turma').value;
            var classe = document.getElementById('classe').value;
            
            if (!disc || !turma || !classe) {
                alert('Selecione disciplina, turma e classe');
                return;
            }
            
            var dados = [];
            var rows = document.querySelectorAll('tr[data-id]');
            
            if (rows.length === 0) {
                alert('Nenhum aluno para salvar');
                return;
            }
            
            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                var id = row.dataset.id;
                var inputs1 = row.querySelectorAll('input[data-trim="1"]');
                var inputs2 = row.querySelectorAll('input[data-trim="2"]');
                var inputs3 = row.querySelectorAll('input[data-trim="3"]');
                
                dados.push({
                    id_aluno: parseInt(id),
                    disciplina: disc,
                    turma: turma,
                    classe: classe,
                    ano_letivo: ano,
                    mac_t1: inputs1[0] ? inputs1[0].value : '',
                    npt_t1: inputs1[1] ? inputs1[1].value : '',
                    mac_t2: inputs2[0] ? inputs2[0].value : '',
                    npt_t2: inputs2[1] ? inputs2[1].value : '',
                    mac_t3: inputs3[0] ? inputs3[0].value : '',
                    npt_t3: inputs3[1] ? inputs3[1].value : ''
                });
            }
            
            var btnSalvar = document.querySelector('.btn-save');
            btnSalvar.disabled = true;
            btnSalvar.textContent = '💾 Salvando...';
            
            var formData = new FormData();
            formData.append('action', 'salvar_notas');
            formData.append('notas', JSON.stringify(dados));
            formData.append('ano_letivo', ano);
            
            fetch(window.location.href, { 
                method: 'POST', 
                body: formData 
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btnSalvar.disabled = false;
                btnSalvar.textContent = '💾 Salvar';
                
                if (data.success) {
                    alert('✅ ' + data.message);
                    carregarAlunos();
                } else {
                    alert('❌ Erro: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(function(error) {
                btnSalvar.disabled = false;
                btnSalvar.textContent = '💾 Salvar';
                alert('❌ Erro ao salvar: ' + error.message);
            });
        }

        // ============================================
        // EXPORTAR EXCEL
        // ============================================
        function exportarExcel() {
            if (!alunos || alunos.length === 0) {
                alert('Nenhum dado para exportar');
                return;
            }
            
            var csv = 'Nº,Nome,Sexo,Idade,MAC1,NPT1,MT1,MAC2,NPT2,MT2,MAC3,NPT3,MT3,MFD,Classificação,Ano Letivo\n';
            
            for (var idx = 0; idx < alunos.length; idx++) {
                var aluno = alunos[idx];
                var row = document.querySelector('tr[data-id="' + aluno.id + '"]');
                if (row) {
                    var i1 = row.querySelectorAll('input[data-trim="1"]');
                    var i2 = row.querySelectorAll('input[data-trim="2"]');
                    var i3 = row.querySelectorAll('input[data-trim="3"]');
                    
                    csv += (idx + 1) + ',"' + aluno.nome + '","' + (aluno.sexo || '') + '","' + (aluno.idade || '') + '",';
                    csv += (i1[0] ? i1[0].value : '') + ',' + (i1[1] ? i1[1].value : '') + ',' + document.getElementById('mt1_' + aluno.id).textContent + ',';
                    csv += (i2[0] ? i2[0].value : '') + ',' + (i2[1] ? i2[1].value : '') + ',' + document.getElementById('mt2_' + aluno.id).textContent + ',';
                    csv += (i3[0] ? i3[0].value : '') + ',' + (i3[1] ? i3[1].value : '') + ',' + document.getElementById('mt3_' + aluno.id).textContent + ',';
                    csv += document.getElementById('mfd_' + aluno.id).textContent + ',';
                    csv += '"' + document.getElementById('class_' + aluno.id).textContent + '",';
                    csv += '"' + currentSelections.anoLetivo + '"\n';
                }
            }
            
            var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'notas_' + currentSelections.classe + '_' + currentSelections.turma + '.csv';
            link.click();
        }

        // ============================================
        // LIMPAR
        // ============================================
        function limpar() {
            if (!confirm('Limpar tudo?')) return;
            document.getElementById('classe').value = '';
            document.getElementById('turma').value = '';
            document.getElementById('turma').disabled = true;
            document.getElementById('disciplina').value = '';
            document.getElementById('disciplina').disabled = true;
            document.querySelector('.btn-search').disabled = true;
            document.querySelector('.btn-save').disabled = true;
            document.getElementById('studentsTable').innerHTML = '<div class="info-text">🔍 Selecione classe, turma e disciplina</div>';
            document.getElementById('statsBar').style.display = 'none';
            document.getElementById('classificationInfo').style.display = 'none';
            document.getElementById('selectionInfo').textContent = '';
            document.getElementById('statusText').innerHTML = '<strong>Status:</strong> Selecione as opções abaixo';
        }

        // ============================================
        // VISUALIZAR PAUTA
        // ============================================
        function openPrintView() {
            if (!alunos || alunos.length === 0) {
                alert('Busque os alunos primeiro');
                return;
            }
            atualizarPrintView();
            document.getElementById('printViewModal').style.display = 'block';
        }

        function closePrintView() {
            document.getElementById('printViewModal').style.display = 'none';
        }

        function atualizarPrintView() {
            var content = document.getElementById('printContent');
            
            var html = 
                '<div class="print-header">' +
                '<h2>REPÚBLICA DE ANGOLA</h2>' +
                '<h3>GOVERNO DA PROVÍNCIA DO ' + headerData.provincia + '</h3>' +
                '<h3>DIRECÇÃO MUNICIPAL DE EDUCAÇÃO DO ' + headerData.municipio + '</h3>' +
                '<div class="escola-nome">' + headerData.escola + '</div>' +
                '<h2>PAUTA DE NOTAS</h2>' +
                '<div class="info-linha">' +
                '<span>Ano Letivo: ' + currentSelections.anoLetivo + '</span>' +
                '<span>Classe: ' + currentSelections.classe + 'ª</span>' +
                '<span>Turma: ' + currentSelections.turma + '</span>' +
                '<span>Disciplina: ' + currentSelections.disciplina + '</span>' +
                '<span>Trimestre: ' + headerData.trimestre + '</span>' +
                '</div></div>' +
                '<table class="print-table"><thead><tr>' +
                '<th rowspan="2">Nº</th>' +
                '<th rowspan="2">NOME DO ALUNO</th>' +
                '<th colspan="3">1º Trimestre</th>' +
                '<th colspan="3">2º Trimestre</th>' +
                '<th colspan="3">3º Trimestre</th>' +
                '<th rowspan="2">MFD/10</th>' +
                '<th rowspan="2">CLASSIFICAÇÃO</th>' +
                '</tr><tr>' +
                '<th>MAC</th><th>NPT</th><th>MT</th>' +
                '<th>MAC</th><th>NPT</th><th>MT</th>' +
                '<th>MAC</th><th>NPT</th><th>MT</th>' +
                '</tr></thead><tbody>';
            
            for (var idx = 0; idx < alunos.length; idx++) {
                var aluno = alunos[idx];
                var row = document.querySelector('tr[data-id="' + aluno.id + '"]');
                if (row) {
                    var i1 = row.querySelectorAll('input[data-trim="1"]');
                    var i2 = row.querySelectorAll('input[data-trim="2"]');
                    var i3 = row.querySelectorAll('input[data-trim="3"]');
                    
                    html += 
                        '<tr>' +
                        '<td>' + (idx + 1) + '</td>' +
                        '<td class="aluno-nome">' + aluno.nome + '</td>' +
                        '<td>' + (i1[0] ? i1[0].value : '-') + '</td>' +
                        '<td>' + (i1[1] ? i1[1].value : '-') + '</td>' +
                        '<td>' + document.getElementById('mt1_' + aluno.id).textContent + '</td>' +
                        '<td>' + (i2[0] ? i2[0].value : '-') + '</td>' +
                        '<td>' + (i2[1] ? i2[1].value : '-') + '</td>' +
                        '<td>' + document.getElementById('mt2_' + aluno.id).textContent + '</td>' +
                        '<td>' + (i3[0] ? i3[0].value : '-') + '</td>' +
                        '<td>' + (i3[1] ? i3[1].value : '-') + '</td>' +
                        '<td>' + document.getElementById('mt3_' + aluno.id).textContent + '</td>' +
                        '<td><strong>' + document.getElementById('mfd_' + aluno.id).textContent + '</strong></td>' +
                        '<td>' + document.getElementById('class_' + aluno.id).textContent + '</td>' +
                        '</tr>';
                }
            }
            
            html += 
                '</tbody></table>' +
                '<div class="print-footer">' +
                '<div class="assinatura"><div>O Professor</div><div class="linha">________________________</div><div>' + headerData.professor + '</div></div>' +
                '<div class="assinatura"><div>O Director Pedagógico</div><div class="linha">________________________</div><div>' + (headerData.diretor || '') + '</div></div>' +
                '</div>';
            
            content.innerHTML = html;
        }

        // ============================================
        // IMPRIMIR PAUTA (SEM LAYOUT)
        // ============================================
        function imprimirPauta() {
            var conteudo = document.getElementById('printContent').innerHTML;
            var janela = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
            
            janela.document.write('<!DOCTYPE html><html><head><title>Pauta de Notas</title>');
            janela.document.write('<style>');
            janela.document.write('body { font-family: "Times New Roman", Times, serif; padding: 30px; background: white; margin: 0; }');
            janela.document.write('.print-header { text-align: center; margin-bottom: 25px; }');
            janela.document.write('.print-header h2 { font-size: 15px; margin: 2px 0; font-weight: bold; }');
            janela.document.write('.print-header h3 { font-size: 13px; margin: 2px 0; font-weight: normal; }');
            janela.document.write('.print-header .escola-nome { font-size: 17px; font-weight: bold; margin: 6px 0; text-transform: uppercase; }');
            janela.document.write('.print-header .info-linha { display: flex; justify-content: space-between; margin: 8px 0; font-size: 13px; border-bottom: 2px solid #000; padding-bottom: 6px; flex-wrap: wrap; }');
            janela.document.write('.print-table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 8px 0; }');
            janela.document.write('.print-table th { background: #2c3e50; color: white; padding: 5px 3px; text-align: center; border: 1px solid #000; font-size: 10px; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
            janela.document.write('.print-table td { padding: 3px 3px; border: 1px solid #000; text-align: center; font-size: 10px; }');
            janela.document.write('.print-table .aluno-nome { text-align: left; padding-left: 6px; }');
            janela.document.write('.print-footer { margin-top: 35px; display: flex; justify-content: space-around; font-size: 12px; flex-wrap: wrap; }');
            janela.document.write('.print-footer .assinatura { text-align: center; min-width: 160px; }');
            janela.document.write('.print-footer .linha { border-top: 1px solid #000; margin-top: 30px; padding-top: 4px; width: 160px; margin-left: auto; margin-right: auto; }');
            janela.document.write('</style>');
            janela.document.write('</head><body>');
            janela.document.write(conteudo);
            janela.document.write('</body></html>');
            janela.document.close();
            
            setTimeout(function() {
                janela.print();
                janela.close();
            }, 800);
        }

        // ============================================
        // EDITAR CABEÇALHO
        // ============================================
        function openEditHeader() {
            document.getElementById('editEscola').value = headerData.escola;
            document.getElementById('editProvincia').value = headerData.provincia;
            document.getElementById('editMunicipio').value = headerData.municipio;
            document.getElementById('editAnoLetivo').value = currentSelections.anoLetivo;
            document.getElementById('editClasse').value = currentSelections.classe ? currentSelections.classe + 'ª' : '';
            document.getElementById('editTurma').value = currentSelections.turma || '';
            document.getElementById('editDisciplina').value = currentSelections.disciplina || '';
            document.getElementById('editProfessor').value = headerData.professor;
            document.getElementById('editDiretor').value = headerData.diretor || '';
            document.getElementById('editHeaderModal').style.display = 'flex';
        }

        function fecharEditHeader() {
            document.getElementById('editHeaderModal').style.display = 'none';
        }

        function salvarHeader() {
            headerData = {
                escola: document.getElementById('editEscola').value,
                provincia: document.getElementById('editProvincia').value,
                municipio: document.getElementById('editMunicipio').value,
                trimestre: document.getElementById('editTrimestre').value,
                professor: document.getElementById('editProfessor').value,
                diretor: document.getElementById('editDiretor').value
            };
            var novoAno = document.getElementById('editAnoLetivo').value;
            if (novoAno && novoAno !== currentSelections.anoLetivo) {
                document.getElementById('anoLetivo').value = novoAno;
                currentSelections.anoLetivo = novoAno;
                document.getElementById('anoLetivoValor').textContent = novoAno;
            }
            atualizarPrintView();
            fecharEditHeader();
        }

        // ============================================
        // EVENTOS
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            loadZoom();
            
            document.getElementById('classe').onchange = function() {
                document.getElementById('turma').disabled = false;
                document.querySelector('.btn-search').disabled = true;
            };
            document.getElementById('turma').onchange = function() {
                document.getElementById('disciplina').disabled = false;
                document.querySelector('.btn-search').disabled = true;
            };
            document.getElementById('disciplina').onchange = function() {
                var c = document.getElementById('classe').value;
                var t = document.getElementById('turma').value;
                if (c && t && this.value) {
                    document.querySelector('.btn-search').disabled = false;
                }
            };
            
            setTimeout(function() {
                var c = document.getElementById('classe').value;
                var t = document.getElementById('turma').value;
                var d = document.getElementById('disciplina').value;
                if (c && t && d) {
                    document.querySelector('.btn-search').disabled = false;
                }
            }, 500);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePrintView();
                fecharEditHeader();
            }
        });
    </script>
</body>
</html>