<?php
// ============================================
// notas.php - Lançamento de Notas (VERSÃO COORDENADOR)
// ============================================

// ===== 1. CARREGAR CONFIGURAÇÃO =====
$base_path = __DIR__;
require_once $base_path . '/config/database.php';
require_once $base_path . '/config/app_modes.php';

// ===== 2. SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== 3. VERIFICAR LOGIN =====
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// ===== 4. DADOS DO USUÁRIO =====
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Professor';
$usuario_email = $_SESSION['usuario_email'] ?? '';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$usuario_cargo = $_SESSION['cargo'] ?? '';
$escola_nome = $_SESSION['escola_nome'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';

// ===== 5. GARANTIR CONEXÃO =====
if (!isset($pdo) || !$pdo) {
    $pdo = conectarBanco();
}

// ===== 6. BUSCAR DADOS DO PROFESSOR =====
$professor_num_agente = null;
$professor_nome = '';
$isCoordenador = false;
$cargo_usuario = '';

try {
    $stmt = $pdo->prepare("SELECT num_agente, nome, cargo FROM funcionarios WHERE email = ? AND status = 'ativo' LIMIT 1");
    $stmt->execute([$usuario_email]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($funcionario) {
        $professor_num_agente = $funcionario['num_agente'];
        $professor_nome = $funcionario['nome'];
        $cargo_usuario = $funcionario['cargo'] ?? '';
        
        // Verificar se é coordenador pedagógico
        $cargo_lower = strtolower($cargo_usuario);
        $isCoordenador = (strpos($cargo_lower, 'coordenador') !== false || 
                          strpos($cargo_lower, 'coordenadora') !== false ||
                          strpos($cargo_lower, 'pedagógico') !== false ||
                          strpos($cargo_lower, 'pedagogico') !== false);
        
        // Se não detectou pelo cargo, verificar pelo nome
        if (!$isCoordenador) {
            $nome_lower = strtolower($professor_nome);
            // Usuários específicos que são coordenadores
            $coordenadores = ['teresa', 'vitangui', 'coordenador', 'pedagogico'];
            foreach ($coordenadores as $coord) {
                if (strpos($nome_lower, $coord) !== false || strpos($cargo_lower, $coord) !== false) {
                    $isCoordenador = true;
                    break;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar funcionario: " . $e->getMessage());
}

// ===== 7. BUSCAR DADOS =====
$classes = [];
$turmas = [];
$disciplinas = [];

if ($isCoordenador) {
    // ===== COORDENADOR: ACESSO TOTAL =====
    error_log("👑 Coordenador Pedagógico - Acesso total a todas as turmas");
    
    // 7.1 Buscar TODAS as turmas da tabela alunos
    try {
        $sql = "SELECT DISTINCT TURMA FROM alunos WHERE status = 'ativo' AND TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA";
        $stmt = $pdo->query($sql);
        $turmas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Se não houver turmas, buscar da distribuição
        if (empty($turmas)) {
            $sql = "SELECT DISTINCT turma_nome FROM destribuicao_professores WHERE turma_nome IS NOT NULL AND turma_nome != '' ORDER BY turma_nome";
            $stmt = $pdo->query($sql);
            $turmas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar turmas: " . $e->getMessage());
    }
    
    // 7.2 Buscar TODAS as classes
    try {
        $sql = "SELECT DISTINCT Classe FROM alunos WHERE status = 'ativo' AND Classe IS NOT NULL AND Classe != '' ORDER BY Classe";
        $stmt = $pdo->query($sql);
        $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($classes)) {
            $sql = "SELECT DISTINCT classe FROM destribuicao_professores WHERE classe IS NOT NULL AND classe != '' ORDER BY classe";
            $stmt = $pdo->query($sql);
            $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar classes: " . $e->getMessage());
    }
    
    // 7.3 Buscar TODAS as disciplinas
    try {
        // Buscar da tabela disciplinas
        $sql = "SELECT nome FROM disciplinas WHERE status = 'ativo' OR status IS NULL ORDER BY nome";
        $stmt = $pdo->query($sql);
        $disciplinas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Se não tiver disciplinas, buscar da distribuição
        if (empty($disciplinas)) {
            $sql = "SELECT DISTINCT disciplinas FROM destribuicao_professores WHERE disciplinas IS NOT NULL AND disciplinas != ''";
            $stmt = $pdo->query($sql);
            $discs = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($discs as $discStr) {
                $parts = array_map('trim', explode(',', $discStr));
                foreach ($parts as $d) {
                    if (!empty($d) && !in_array($d, $disciplinas)) {
                        $disciplinas[] = $d;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar disciplinas: " . $e->getMessage());
    }
    
    // 7.4 Fallback - Turmas padrão
    if (empty($turmas)) {
        $turmas = ['1AM', '1BM', '2AM', '2BM', '3AM', '3BM', '4AM', '4BM', '5AM', '5BM', 
                   '6AM', '6AT', '7AM', '7AT', '8AM', '8BM', '8BT', '9AM', '9AT', 'PreA', 'PreB'];
    }
    if (empty($classes)) {
        $classes = ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª', '7ª', '8ª', '9ª', 'PRE'];
    }
    if (empty($disciplinas)) {
        $disciplinas = ['Matemática', 'Língua Portuguesa', 'C. Natureza', 'História', 'Geografia', 
                        'Ed. Física', 'Inglês', 'Informática', 'Química', 'Física', 'Biologia', 
                        'Ed. Musical', 'Ed. Manual Plástica', 'Caligrafia', 'Xadrez'];
    }
    
} else {
    // ===== PROFESSOR: ACESSO RESTRITO =====
    error_log("👨‍🏫 Professor - Acesso restrito ao seu perfil");
    
    try {
        if ($professor_num_agente) {
            $sql = "SELECT * FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$professor_num_agente]);
            $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($distribuicoes) && !empty($professor_nome)) {
                $sql = "SELECT * FROM destribuicao_professores WHERE professor_nome LIKE ? AND tipo = 'PROFESSOR'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['%' . $professor_nome . '%']);
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
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar distribuições: " . $e->getMessage());
    }
    
    // Fallback para professor
    if (empty($classes)) $classes = ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª', '7ª', '8ª', '9ª'];
    if (empty($turmas)) $turmas = ['2AM'];
    if (empty($disciplinas)) $disciplinas = ['Matemática', 'Língua Portuguesa', 'C. Natureza', 'Ed. Física'];
}

// Ordenar
sort($classes);
sort($turmas);
sort($disciplinas);

// ===== 8. FUNÇÕES AUXILIARES =====
function isMiniPauta($classe) {
    $classeNum = intval(preg_replace('/[^0-9]/', '', $classe));
    return in_array($classeNum, [6, 9, 12]);
}

function isPreSexta($classe) {
    $classeNum = intval(preg_replace('/[^0-9]/', '', $classe));
    return $classeNum <= 6 || strtoupper($classe) === 'PRE';
}

function getClassificacao($media, $isPreSexta) {
    if ($media === null || $media <= 0) return ['texto' => '', 'classe' => ''];
    
    $texto = '';
    $classe = '';
    
    if ($isPreSexta) {
        if ($media >= 9) { $texto = "MUITO BOM"; $classe = "class-muito-bom"; }
        else if ($media >= 7) { $texto = "BOM"; $classe = "class-bom"; }
        else if ($media >= 5) { $texto = "SUFICIENTE"; $classe = "class-suficiente"; }
        else if ($media >= 3) { $texto = "MEDÍOCRE"; $classe = "class-mediocre"; }
        else if ($media >= 0) { $texto = "MAU"; $classe = "class-mau"; }
    } else {
        if ($media >= 18) { $texto = "MUITO BOM"; $classe = "class-muito-bom"; }
        else if ($media >= 14) { $texto = "BOM"; $classe = "class-bom"; }
        else if ($media >= 10) { $texto = "SUFICIENTE"; $classe = "class-suficiente"; }
        else if ($media >= 5) { $texto = "MEDÍOCRE"; $classe = "class-mediocre"; }
        else if ($media >= 0) { $texto = "MAU"; $classe = "class-mau"; }
    }
    
    return ['texto' => $texto, 'classe' => $classe];
}

function getSituacao($media, $isPreSexta) {
    if ($media === null || $media <= 0) return 'SEM NOTA';
    $notaMinima = $isPreSexta ? 5 : 10;
    if ($media >= $notaMinima) return 'APROVADO';
    if ($media >= 3) return 'RECUPERAÇÃO';
    return 'REPROVADO';
}

// ===== 9. PROCESSAR REQUISIÇÕES AJAX =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    if ($action === 'buscar_alunos_notas') {
        $turma = trim($_POST['turma'] ?? '');
        $disciplina = $_POST['disciplina'] ?? '';
        $ano_letivo = $_POST['ano_letivo'] ?? date('Y') . '/' . (date('Y') + 1);
        $classe = $_POST['classe'] ?? '';
        
        $turma = str_replace('Turma ', '', $turma);
        $turma = trim($turma);
        
        if (empty($turma)) {
            $turma = '2AM';
        }
        
        try {
            // BUSCAR ALUNOS - COM FILTRO DE CLASSE
            $sql = "SELECT id, nome, Sexo as sexo, Idade as idade, TURMA as turma, Classe as classe 
                    FROM alunos 
                    WHERE TURMA = ? AND status = 'ativo'";
            
            $params = [$turma];
            
            // Se classe foi selecionada, filtrar também
            if (!empty($classe)) {
                $sql .= " AND Classe = ?";
                $params[] = $classe;
            }
            
            $sql .= " ORDER BY nome ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Alunos encontrados para turma $turma: " . count($alunos));
            
            // BUSCAR NOTAS - TABELA notas_alunos
            $notas = [];
            if (!empty($alunos)) {
                $ids = array_column($alunos, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                $sql_notas = "SELECT id_aluno, nome_aluno, disciplina, turma, classe,
                              mac_t1, npt_t1, mt1, mac_t2, npt_t2, mt2, mac_t3, npt_t3, mt3,
                              neo, en, mec, mfed, mfd, classificacao, sexo, idade, sala, turno, ano_letivo
                              FROM notas_alunos 
                              WHERE id_aluno IN ($placeholders) AND disciplina = ? AND turma = ? AND ano_letivo = ?";
                $params_notas = array_merge($ids, [$disciplina, $turma, $ano_letivo]);
                $stmt_notas = $pdo->prepare($sql_notas);
                $stmt_notas->execute($params_notas);
                $notas_list = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($notas_list as $n) {
                    $notas[$n['id_aluno']] = $n;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'alunos' => $alunos, 
                'notas' => $notas, 
                'total' => count($alunos),
                'turma_buscada' => $turma,
                'classe_filtro' => $classe
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao buscar alunos: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
                $nome_aluno = $nota['nome_aluno'] ?? '';
                
                if (!$id_aluno || !$disciplina) continue;
                
                // Dados do 1º Trimestre
                $mac_t1 = !empty($nota['mac_t1']) ? floatval(str_replace(',', '.', $nota['mac_t1'])) : null;
                $npt_t1 = !empty($nota['npt_t1']) ? floatval(str_replace(',', '.', $nota['npt_t1'])) : null;
                $mt1 = ($mac_t1 !== null && $npt_t1 !== null) ? ($mac_t1 + $npt_t1) / 2 : null;
                
                // Dados do 2º Trimestre
                $mac_t2 = !empty($nota['mac_t2']) ? floatval(str_replace(',', '.', $nota['mac_t2'])) : null;
                $npt_t2 = !empty($nota['npt_t2']) ? floatval(str_replace(',', '.', $nota['npt_t2'])) : null;
                $mt2 = ($mac_t2 !== null && $npt_t2 !== null) ? ($mac_t2 + $npt_t2) / 2 : null;
                
                // Dados do 3º Trimestre
                $mac_t3 = !empty($nota['mac_t3']) ? floatval(str_replace(',', '.', $nota['mac_t3'])) : null;
                $npt_t3 = !empty($nota['npt_t3']) ? floatval(str_replace(',', '.', $nota['npt_t3'])) : null;
                $mt3 = ($mac_t3 !== null && $npt_t3 !== null) ? ($mac_t3 + $npt_t3) / 2 : null;
                
                // Mini Pauta (NEO e EN)
                $neo = !empty($nota['neo']) ? floatval(str_replace(',', '.', $nota['neo'])) : null;
                $en = !empty($nota['en']) ? floatval(str_replace(',', '.', $nota['en'])) : null;
                
                // Calcular MEC (Média NEO + EN)
                $mec = ($neo !== null && $en !== null) ? ($neo + $en) / 2 : null;
                
                // Calcular MFED (para Mini Pauta)
                $mfed = null;
                if ($mt3 !== null && $en !== null) {
                    $mfed = (0.6 * $mt3) + (0.4 * $en);
                }
                
                // Calcular MFD (Média Final da Disciplina)
                $medias = [];
                if ($mt1 !== null) $medias[] = $mt1;
                if ($mt2 !== null) $medias[] = $mt2;
                if ($mt3 !== null) $medias[] = $mt3;
                $mfd = !empty($medias) ? array_sum($medias) / count($medias) : null;
                
                // Classificação
                $isPreSexta = isPreSexta($classe);
                $classificacao = '';
                if ($mfd !== null) {
                    $classInfo = getClassificacao($mfd, $isPreSexta);
                    $classificacao = $classInfo['texto'];
                }
                
                // Verificar se já existe registro
                $sql_check = "SELECT id FROM notas_alunos WHERE id_aluno = ? AND disciplina = ? AND turma = ? AND ano_letivo = ?";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$id_aluno, $disciplina, $turma, $ano_letivo]);
                $existe = $stmt_check->fetch();
                
                $sexo = $nota['sexo'] ?? '';
                $idade = $nota['idade'] ?? 0;
                $sala = $nota['sala'] ?? '';
                $turno = $nota['turno'] ?? '';
                
                if ($existe) {
                    $sql = "UPDATE notas_alunos SET 
                            nome_aluno = ?, classe = ?,
                            mac_t1 = ?, npt_t1 = ?, mt1 = ?,
                            mac_t2 = ?, npt_t2 = ?, mt2 = ?,
                            mac_t3 = ?, npt_t3 = ?, mt3 = ?,
                            neo = ?, en = ?, mec = ?, mfed = ?,
                            mfd = ?, classificacao = ?,
                            sexo = ?, idade = ?, sala = ?, turno = ?,
                            data_atualizacao = CURRENT_TIMESTAMP
                            WHERE id_aluno = ? AND disciplina = ? AND turma = ? AND ano_letivo = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $nome_aluno, $classe,
                        $mac_t1, $npt_t1, $mt1,
                        $mac_t2, $npt_t2, $mt2,
                        $mac_t3, $npt_t3, $mt3,
                        $neo, $en, $mec, $mfed,
                        $mfd, $classificacao,
                        $sexo, $idade, $sala, $turno,
                        $id_aluno, $disciplina, $turma, $ano_letivo
                    ]);
                } else {
                    $sql = "INSERT INTO notas_alunos 
                            (id_aluno, nome_aluno, disciplina, turma, classe, ano_letivo,
                             mac_t1, npt_t1, mt1, mac_t2, npt_t2, mt2, mac_t3, npt_t3, mt3,
                             neo, en, mec, mfed, mfd, classificacao, sexo, idade, sala, turno, data_lancamento)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $id_aluno, $nome_aluno, $disciplina, $turma, $classe, $ano_letivo,
                        $mac_t1, $npt_t1, $mt1,
                        $mac_t2, $npt_t2, $mt2,
                        $mac_t3, $npt_t3, $mt3,
                        $neo, $en, $mec, $mfed,
                        $mfd, $classificacao,
                        $sexo, $idade, $sala, $turno, date('Y-m-d')
                    ]);
                }
                $salvos++;
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => $salvos . " notas salvas com sucesso!", 'salvos' => $salvos]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao salvar notas: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ===== 10. LISTAR TURMAS PARA DEBUG =====
try {
    $stmt_debug = $pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE status = 'ativo' ORDER BY TURMA");
    $turmas_debug = $stmt_debug->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $turmas_debug = [];
}

// ===== 11. VERIFICAR SE TABELA notas_alunos EXISTE =====
$tabela_notas_existe = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'notas_alunos'");
    $tabela_notas_existe = $stmt->rowCount() > 0;
} catch (Exception $e) {
    error_log("Erro ao verificar tabela notas_alunos: " . $e->getMessage());
}

// ===== 12. DADOS PARA JAVASCRIPT =====
$turmasJson = json_encode($turmas);
$disciplinasJson = json_encode($disciplinas);
$classesJson = json_encode($classes);
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
        
        .badge-coordenador {
            background: #c9a84c;
            color: #1a2332;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .badge-professor {
            background: #3498db;
            color: white;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
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
        .btn-success { background: #2ecc71; color: #fff; }
        .btn-success:hover { background: #27ae60; }
        .btn-info { background: #3498db; color: #fff; }
        .btn-info:hover { background: #2980b9; }
        .btn-secondary { background: #f1f5f9; color: #4a5568; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-back { background: #27ae60; color: #fff; }
        .btn-back:hover { background: #1e8449; }
        .btn-danger { background: #e74c3c; color: #fff; }
        .btn-danger:hover { background: #c0392b; }
        .btn-warning { background: #f39c12; color: #fff; }
        .btn-warning:hover { background: #d68910; }
        
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
        
        .search-btn {
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
        .search-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(39,174,96,0.3); }
        .search-btn:disabled { background: #95a5a6; cursor: not-allowed; opacity: 0.7; }
        
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
        .status-bar.coordenador {
            border-left-color: #c9a84c;
            background: #fefcf3;
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
            width: 60px;
            padding: 6px;
            border: 2px solid #ddd;
            border-radius: 4px;
            text-align: center;
            font-size: 13px;
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
        .notes-table .input-nota.invalid {
            border-color: #e74c3c;
            background: #fdf2f2;
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
        .notes-table .neo-cell {
            font-weight: 600;
            color: #0c5460;
            background: #d1ecf1;
            padding: 4px 6px;
            border-radius: 4px;
            display: inline-block;
            min-width: 40px;
            font-size: 13px;
        }
        .notes-table .en-cell {
            font-weight: 600;
            color: #856404;
            background: #fff3cd;
            padding: 4px 6px;
            border-radius: 4px;
            display: inline-block;
            min-width: 40px;
            font-size: 13px;
        }
        .notes-table .mec-cell {
            font-weight: 600;
            color: #721c24;
            background: #f8d7da;
            padding: 4px 6px;
            border-radius: 4px;
            display: inline-block;
            min-width: 40px;
            font-size: 13px;
        }
        .notes-table .mfed-cell {
            font-weight: 700;
            color: #155724;
            background: #d4edda;
            padding: 4px 8px;
            border-radius: 4px;
            border: 2px solid #28a745;
            display: inline-block;
            min-width: 50px;
            font-size: 14px;
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
        
        .debug-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 10px 0;
            font-size: 12px;
            color: #856404;
            display: block;
        }
        
        .tipo-pauta-badge {
            display: inline-block;
            padding: 2px 14px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .tipo-pauta-mini {
            background: #fef3c7;
            color: #92400e;
        }
        .tipo-pauta-normal {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .acesso-total-badge {
            background: #c9a84c;
            color: #1a2332;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .filter-row { flex-direction: column; }
            .filter-group { min-width: 100%; }
            .user-info { justify-content: center; }
            .header { flex-direction: column; text-align: center; }
            .notes-table { font-size: 10px; min-width: 700px; }
            .notes-table .input-nota { width: 45px; padding: 4px; font-size: 11px; }
        }
    </style>
</head>
<body>

    <!-- ===== HEADER ===== -->
    <div class="header">
        <h1>📝 <span>Lançamento de Notas</span></h1>
        <div class="user-info">
            <span>👤 <?= htmlspecialchars($usuario_nome) ?></span>
            <?php if ($isCoordenador): ?>
                <span class="badge-coordenador">👑 COORDENADOR PEDAGÓGICO</span>
            <?php else: ?>
                <span class="badge-professor">👨‍🏫 PROFESSOR</span>
            <?php endif; ?>
            <span>📅 <?= date('Y') . '/' . (date('Y') + 1) ?></span>
            <?php if ($professor_num_agente && !$isCoordenador): ?>
            <span style="font-size:11px;color:#c9a84c;">🆔 Nº Agente: <?= $professor_num_agente ?></span>
            <?php endif; ?>
            <button class="btn btn-info" onclick="visualizarPauta()">📋 Visualizar Pauta</button>
            
        </div>
    </div>

    <div class="container">

        <!-- STATUS -->
        <div class="status-bar <?= $isCoordenador ? 'coordenador' : '' ?> no-print">
            <div id="statusText">
                <strong>Status:</strong> 
                <?= $isCoordenador ? '👑 Coordenador Pedagógico - Acesso a todas as turmas' : 'Selecione as opções abaixo' ?>
            </div>
            <div id="selectionInfo"></div>
            <div id="tipoPautaDisplay"></div>
        </div>

        <!-- DEBUG -->
        <div class="debug-box" id="debugBox">
            <strong>🔍 Informações:</strong><br>
            <?php if ($isCoordenador): ?>
                👑 Modo Coordenador - Acesso total<br>
            <?php endif; ?>
            Turmas disponíveis: <?= !empty($turmas) ? count($turmas) . ' turmas' : 'Nenhuma' ?><br>
            Classes disponíveis: <?= !empty($classes) ? count($classes) . ' classes' : 'Nenhuma' ?><br>
            Disciplinas disponíveis: <?= !empty($disciplinas) ? count($disciplinas) . ' disciplinas' : 'Nenhuma' ?><br>
            Tabela notas_alunos: <?= $tabela_notas_existe ? '✅ Existe' : '❌ Não existe' ?>
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
                    <select id="classe" onchange="onClasseChange()">
                        <option value="">Todas as Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?>ª Classe</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>👥 Turma:</label>
                    <select id="turma" onchange="onTurmaChange()" <?= empty($turmas) ? 'disabled' : '' ?>>
                        <option value="">Selecione...</option>
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>">Turma <?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📖 Disciplina:</label>
                    <select id="disciplina" onchange="onDisciplinaChange()" <?= empty($disciplinas) ? 'disabled' : '' ?>>
                        <option value="">Selecione...</option>
                        <?php foreach ($disciplinas as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="search-btn" onclick="buscarAlunos()" disabled>🔍 Buscar</button>
                <button class="btn btn-secondary" onclick="limparTudo()">🗑️ Limpar</button>
                <?php if ($isCoordenador): ?>
                    <button class="btn btn-warning" onclick="carregarTodasTurmas()" title="Carregar todas as turmas">
                        📚 Todas Turmas
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- CLASSIFICAÇÃO INFO -->
        <div id="classificationInfo" class="classification-info no-print">
            <span>📊</span>
            <div>
                <div><strong id="sistemaClassificacao">Sistema de Avaliação: 0-10 pontos</strong></div>
                <div style="font-size:11px;color:#666;" id="faixasClassificacao">MUITO BOM: 9-10 | BOM: 7-8 | SUFICIENTE: 5-6 | MEDÍOCRE: 3-4 | MAU: 0-2</div>
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
                <div class="info-text">🔍 Selecione a turma e disciplina</div>
            </div>
        </div>

        <!-- BOTÕES -->
        <div class="button-group no-print">
            <button class="btn btn-success" onclick="salvarNotas()" disabled id="btnSalvar">💾 Salvar</button>
            <button class="btn btn-info" onclick="visualizarPauta()">📋 Visualizar Pauta</button>
            <button class="btn btn-secondary" onclick="limparTudo()">🗑️ Limpar</button>
            <?php if ($isCoordenador): ?>
                <button class="btn btn-warning" onclick="carregarTodasTurmas()">📚 Todas Turmas</button>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    // ============================================
    // VARIÁVEIS
    // ============================================
    var alunos = [];
    var notas = {};
    var currentSelections = { classe: '', turma: '', disciplina: '', anoLetivo: '' };
    var isPreSexta = false;
    var isMiniPautaStyle = false;
    var maxPontos = 10;
    var isCoordenador = <?= $isCoordenador ? 'true' : 'false' ?>;

    // ============================================
    // DADOS DO PHP
    // ============================================
    var turmasDisponiveis = <?= $turmasJson ?>;
    var disciplinasDisponiveis = <?= $disciplinasJson ?>;
    var classesDisponiveis = <?= $classesJson ?>;

    console.log('📦 Dados carregados:');
    console.log('👑 Coordenador:', isCoordenador);
    console.log('🏫 Turmas:', turmasDisponiveis.length);
    console.log('📚 Classes:', classesDisponiveis.length);
    console.log('📖 Disciplinas:', disciplinasDisponiveis.length);

    // ============================================
    // FUNÇÃO PARA CARREGAR TODAS AS TURMAS
    // ============================================
    function carregarTodasTurmas() {
        if (!isCoordenador) {
            alert('⚠️ Apenas Coordenador Pedagógico pode acessar todas as turmas');
            return;
        }
        
        var select = document.getElementById('turma');
        select.innerHTML = '<option value="">Todas as Turmas</option>';
        
        turmasDisponiveis.forEach(function(t) {
            var opt = document.createElement('option');
            opt.value = t;
            opt.textContent = 'Turma ' + t;
            select.appendChild(opt);
        });
        select.disabled = false;
        
        // Habilitar disciplina
        document.getElementById('disciplina').disabled = false;
        
        mostrarToast('📚 Todas as turmas carregadas!', 'success');
    }

    // ============================================
    // VALIDAÇÃO DE NOTAS
    // ============================================
    function validarNota(input, maxPontos) {
        if (input.value === '') {
            input.classList.remove('has-value', 'invalid');
            return true;
        }
        
        var valor = parseFloat(input.value);
        if (isNaN(valor)) {
            input.value = '';
            input.classList.remove('has-value', 'invalid');
            mostrarToast('⚠️ Digite um número válido!', 'warning');
            return false;
        }
        
        if (valor < 0) {
            input.value = 0;
            input.classList.add('has-value');
            input.classList.remove('invalid');
            mostrarToast('⚠️ Nota não pode ser negativa!', 'warning');
            return false;
        }
        
        if (valor > maxPontos) {
            input.value = maxPontos;
            input.classList.add('has-value');
            input.classList.remove('invalid');
            mostrarToast('⚠️ Nota máxima é ' + maxPontos + '!', 'warning');
            return false;
        }
        
        var resto = valor % 0.5;
        if (resto !== 0) {
            var arredondado = Math.round(valor / 0.5) * 0.5;
            input.value = arredondado;
            input.classList.add('has-value');
            input.classList.remove('invalid');
            mostrarToast('⚠️ Use valores de 0.5 em 0.5! Ex: 5.0, 5.5, 6.0', 'info');
            return true;
        }
        
        input.classList.add('has-value');
        input.classList.remove('invalid');
        return true;
    }

    // ============================================
    // TOAST
    // ============================================
    function mostrarToast(mensagem, tipo) {
        var toast = document.createElement('div');
        var cores = {
            'warning': '#f39c12',
            'info': '#3498db',
            'success': '#2ecc71',
            'danger': '#e74c3c'
        };
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${cores[tipo] || '#3498db'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            z-index: 9999;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            max-width: 350px;
            animation: slideIn 0.3s ease;
            font-family: 'Segoe UI', Arial, sans-serif;
        `;
        toast.textContent = mensagem;
        document.body.appendChild(toast);
        
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function() {
                if (toast.parentNode) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }

    var styleToast = document.createElement('style');
    styleToast.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(styleToast);

    // ============================================
    // FUNÇÕES
    // ============================================
    function determinarSistemaClassificacao(classe) {
        var classeNum = parseInt(classe.replace('ª', '').replace('º', '').trim());
        isPreSexta = classeNum <= 6 || classe.toUpperCase() === 'PRE';
        isMiniPautaStyle = (classeNum === 6 || classeNum === 9 || classeNum === 12);
        maxPontos = isPreSexta ? 10 : 20;
        
        var infoDiv = document.getElementById('classificationInfo');
        var sistemaDiv = document.getElementById('sistemaClassificacao');
        var faixasDiv = document.getElementById('faixasClassificacao');
        var tipoDisplay = document.getElementById('tipoPautaDisplay');
        
        if (isPreSexta) {
            sistemaDiv.textContent = 'Sistema de Avaliação: 0-10 pontos (Pré a 6ª)';
            faixasDiv.innerHTML = 'MUITO BOM: 9-10 | BOM: 7-8 | SUFICIENTE: 5-6 | MEDÍOCRE: 3-4 | MAU: 0-2';
            infoDiv.style.display = 'flex';
            infoDiv.style.backgroundColor = '#fff3cd';
            infoDiv.style.borderLeftColor = '#f39c12';
        } else {
            sistemaDiv.textContent = 'Sistema de Avaliação: 0-20 pontos (7ª a 12ª)';
            faixasDiv.innerHTML = 'MUITO BOM: 18-20 | BOM: 14-17 | SUFICIENTE: 10-13 | MEDÍOCRE: 5-9 | MAU: 0-4';
            infoDiv.style.display = 'flex';
            infoDiv.style.backgroundColor = '#e8f4fc';
            infoDiv.style.borderLeftColor = '#3498db';
        }
        
        if (isMiniPautaStyle) {
            tipoDisplay.innerHTML = '<span class="tipo-pauta-badge tipo-pauta-mini">📋 Mini Pauta (6ª/9ª/12ª)</span>';
        } else {
            tipoDisplay.innerHTML = '<span class="tipo-pauta-badge tipo-pauta-normal">📋 Pauta Normal</span>';
        }
        
        return isPreSexta;
    }

    function getClassificacao(mfd) {
        if (!mfd || mfd <= 0) return { texto: '', classe: '' };
        var texto = '', classe = '';
        
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
        
        return { texto: texto, classe: classe };
    }

    // ============================================
    // FILTROS
    // ============================================
    function onClasseChange() {
        var classe = document.getElementById('classe').value;
        var turmaSelect = document.getElementById('turma');
        var disciplinaSelect = document.getElementById('disciplina');
        var searchBtn = document.querySelector('.search-btn');
        
        turmaSelect.disabled = false;
        disciplinaSelect.disabled = false;
        searchBtn.disabled = true;
        document.getElementById('btnSalvar').disabled = true;
        document.getElementById('studentsTable').innerHTML = '<div class="info-text">🔍 Selecione a turma e disciplina</div>';
        document.getElementById('statsBar').style.display = 'none';
        document.getElementById('selectionInfo').innerHTML = '';
        document.getElementById('tipoPautaDisplay').innerHTML = '';
        
        if (classe) {
            determinarSistemaClassificacao(classe);
            document.getElementById('statusText').innerHTML = '<strong>Status:</strong> Classe selecionada. Selecione a turma.';
        } else {
            document.getElementById('statusText').innerHTML = '<strong>Status:</strong> ' + 
                (isCoordenador ? '👑 Coordenador - Selecione os filtros' : 'Selecione as opções abaixo');
        }
    }

    function onTurmaChange() {
        var turma = document.getElementById('turma').value;
        var disciplinaSelect = document.getElementById('disciplina');
        var searchBtn = document.querySelector('.search-btn');
        
        if (turma) {
            disciplinaSelect.disabled = false;
            searchBtn.disabled = !document.getElementById('disciplina').value;
            document.getElementById('statusText').innerHTML = '<strong>Status:</strong> Turma selecionada. Selecione a disciplina.';
        } else {
            disciplinaSelect.disabled = true;
            searchBtn.disabled = true;
        }
        document.getElementById('btnSalvar').disabled = true;
        document.getElementById('studentsTable').innerHTML = '<div class="info-text">🔍 Selecione a disciplina</div>';
    }

    function onDisciplinaChange() {
        var disciplina = document.getElementById('disciplina').value;
        var searchBtn = document.querySelector('.search-btn');
        var turma = document.getElementById('turma').value;
        
        if (disciplina && turma) {
            searchBtn.disabled = false;
            document.getElementById('statusText').innerHTML = '<strong>Status:</strong> Pronto para buscar!';
        } else {
            searchBtn.disabled = true;
        }
    }

    // ============================================
    // BUSCAR ALUNOS
    // ============================================
    function buscarAlunos() {
        var classe = document.getElementById('classe').value;
        var turma = document.getElementById('turma').value;
        var disciplina = document.getElementById('disciplina').value;
        var anoLetivo = document.getElementById('anoLetivo').value;
        
        if (!turma || !disciplina) {
            alert('Selecione todos os campos');
            return;
        }
        
        turma = turma.replace('Turma ', '');
        turma = turma.trim();
        
        if (turma === '') {
            turma = '2AM';
        }
        
        currentSelections = { classe: classe, turma: turma, disciplina: disciplina, anoLetivo: anoLetivo };
        
        document.getElementById('studentsTable').innerHTML = 
            '<div class="loading"><div class="spinner"></div><div>Carregando alunos da turma ' + turma + '...</div></div>';
        document.getElementById('btnSalvar').disabled = true;
        
        var formData = new FormData();
        formData.append('action', 'buscar_alunos_notas');
        formData.append('turma', turma);
        formData.append('disciplina', disciplina);
        formData.append('ano_letivo', anoLetivo);
        formData.append('classe', classe);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.alunos && data.alunos.length > 0) {
                alunos = data.alunos;
                notas = data.notas || {};
                renderizarTabela(alunos, notas);
                document.getElementById('btnSalvar').disabled = false;
                document.getElementById('statusText').innerHTML = 
                    '<strong>Status:</strong> ✅ ' + alunos.length + ' alunos carregados • Turma ' + turma + ' • ' + disciplina;
                document.getElementById('selectionInfo').textContent = 
                    '👥 Turma ' + turma + ' • 📖 ' + disciplina + ' • 📅 ' + anoLetivo +
                    (classe ? ' • 📚 Classe ' + classe : '');
            } else {
                var msg = 'Nenhum aluno encontrado para a turma <strong>' + turma + '</strong>';
                if (classe) msg += ' e classe <strong>' + classe + '</strong>';
                document.getElementById('studentsTable').innerHTML = '<div class="info-text">📭 ' + msg + '</div>';
                document.getElementById('statusText').innerHTML = 
                    '<strong>Status:</strong> ⚠️ Nenhum aluno encontrado para a turma ' + turma;
            }
        })
        .catch(function(error) {
            console.error('Erro:', error);
            document.getElementById('studentsTable').innerHTML = '<div class="info-text">❌ Erro ao carregar alunos</div>';
            document.getElementById('statusText').innerHTML = '<strong>Status:</strong> ❌ Erro ao carregar alunos';
        });
    }

    // ============================================
    // RENDERIZAR TABELA - MINI PAUTA
    // ============================================
    function renderizarTabela(alunos, notas) {
        var html = '';
        var isMini = isMiniPautaStyle;
        var max = maxPontos;
        
        if (isMini) {
            html = `
                <table class="notes-table">
                    <thead>
                        <tr>
                            <th class="num-col" rowspan="2">Nº</th>
                            <th rowspan="2" style="text-align:left;min-width:160px;">Nome do Aluno</th>
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
            
            for (var i = 0; i < alunos.length; i++) {
                var aluno = alunos[i];
                var n = notas[aluno.id] || {};
                
                var mac1 = n.mac_t1 || '';
                var npt1 = n.npt_t1 || '';
                var mt1 = (parseFloat(mac1) || 0) + (parseFloat(npt1) || 0) > 0 ? 
                    ((parseFloat(mac1) || 0) + (parseFloat(npt1) || 0)) / 2 : 0;
                
                var mac2 = n.mac_t2 || '';
                var npt2 = n.npt_t2 || '';
                var mt2 = (parseFloat(mac2) || 0) + (parseFloat(npt2) || 0) > 0 ? 
                    ((parseFloat(mac2) || 0) + (parseFloat(npt2) || 0)) / 2 : 0;
                
                var mac3 = n.mac_t3 || '';
                var mfd = n.mfd || 0;
                var neo = n.neo || '';
                var en = n.en || '';
                var mec = (parseFloat(neo) || 0) + (parseFloat(en) || 0) > 0 ? 
                    ((parseFloat(neo) || 0) + (parseFloat(en) || 0)) / 2 : 0;
                
                var mt3 = (parseFloat(mt1) + parseFloat(mt2) + parseFloat(mfd)) / 3;
                var mfed = (0.6 * mt3) + ((parseFloat(en) || 0) * 0.4);
                
                html += `
                    <tr data-id="${aluno.id}">
                        <td class="num-col">${i + 1}</td>
                        <td class="nome-col">${aluno.nome}</td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${mac1}" data-trim="1" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMiniMT(this, 1, '${aluno.id}')"></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${npt1}" data-trim="1" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMiniMT(this, 1, '${aluno.id}')"></td>
                        <td><span class="mt-cell" id="mt1_${aluno.id}">${mt1.toFixed(1)}</span></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${mac2}" data-trim="2" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMiniMT(this, 2, '${aluno.id}')"></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${npt2}" data-trim="2" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMiniMT(this, 2, '${aluno.id}')"></td>
                        <td><span class="mt-cell" id="mt2_${aluno.id}">${mt2.toFixed(1)}</span></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${mac3}" data-trim="3" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMiniIII('${aluno.id}')"></td>
                        <td><span class="mt-cell" id="mfd_${aluno.id}">${parseFloat(mfd).toFixed(1)}</span></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${neo}" data-trim="neo" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMiniIII('${aluno.id}')"></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${en}" data-trim="en" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMiniIII('${aluno.id}')"></td>
                        <td><span class="mec-cell" id="mec_${aluno.id}">${mec.toFixed(1)}</span></td>
                        <td><span class="mfed-cell" id="mfed_${aluno.id}">${mfed.toFixed(1)}</span></td>
                    </tr>
                `;
            }
            
            html += '</tbody></table>';
        } else {
            // PAUTA NORMAL
            html = `
                <table class="notes-table">
                    <thead>
                        <tr>
                            <th class="num-col" rowspan="2">Nº</th>
                            <th rowspan="2" style="text-align:left;min-width:160px;">Nome do Aluno</th>
                            <th colspan="3">1º Trimestre</th>
                            <th colspan="3">2º Trimestre</th>
                            <th colspan="3">3º Trimestre</th>
                            <th rowspan="2">MFD/${max}</th>
                            <th rowspan="2">CLASSIF.</th>
                        </tr>
                        <tr>
                            <th>MAC</th><th>NPT</th><th>MT</th>
                            <th>MAC</th><th>NPT</th><th>MT</th>
                            <th>MAC</th><th>NPT</th><th>MT</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            for (var i = 0; i < alunos.length; i++) {
                var aluno = alunos[i];
                var n = notas[aluno.id] || {};
                
                var mac1 = n.mac_t1 || '';
                var npt1 = n.npt_t1 || '';
                var mt1 = (parseFloat(mac1) || 0) + (parseFloat(npt1) || 0) > 0 ? 
                    ((parseFloat(mac1) || 0) + (parseFloat(npt1) || 0)) / 2 : 0;
                
                var mac2 = n.mac_t2 || '';
                var npt2 = n.npt_t2 || '';
                var mt2 = (parseFloat(mac2) || 0) + (parseFloat(npt2) || 0) > 0 ? 
                    ((parseFloat(mac2) || 0) + (parseFloat(npt2) || 0)) / 2 : 0;
                
                var mac3 = n.mac_t3 || '';
                var npt3 = n.npt_t3 || '';
                var mt3 = (parseFloat(mac3) || 0) + (parseFloat(npt3) || 0) > 0 ? 
                    ((parseFloat(mac3) || 0) + (parseFloat(npt3) || 0)) / 2 : 0;
                
                var medias = [];
                if (mt1 > 0) medias.push(mt1);
                if (mt2 > 0) medias.push(mt2);
                if (mt3 > 0) medias.push(mt3);
                var mfd = medias.length > 0 ? medias.reduce(function(a, b) { return a + b; }, 0) / medias.length : 0;
                var classif = getClassificacao(mfd);
                
                html += `
                    <tr data-id="${aluno.id}">
                        <td class="num-col">${i + 1}</td>
                        <td class="nome-col">${aluno.nome}</td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${mac1}" data-trim="1" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMT(this, 1, '${aluno.id}')"></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${npt1}" data-trim="1" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMT(this, 1, '${aluno.id}')"></td>
                        <td><span class="mt-cell" id="mt1_${aluno.id}">${mt1.toFixed(1)}</span></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${mac2}" data-trim="2" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMT(this, 2, '${aluno.id}')"></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${npt2}" data-trim="2" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMT(this, 2, '${aluno.id}')"></td>
                        <td><span class="mt-cell" id="mt2_${aluno.id}">${mt2.toFixed(1)}</span></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${mac3}" data-trim="3" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMT(this, 3, '${aluno.id}')"></td>
                        <td><input type="number" class="input-nota" min="0" max="${max}" step="0.5" value="${npt3}" data-trim="3" data-aluno="${aluno.id}" onchange="validarNota(this, ${max}); calcularMT(this, 3, '${aluno.id}')"></td>
                        <td><span class="mt-cell" id="mt3_${aluno.id}">${mt3.toFixed(1)}</span></td>
                        <td><span class="mfd-cell" id="mfd_${aluno.id}">${mfd.toFixed(1)}</span></td>
                        <td><span id="class_${aluno.id}" class="class-cell ${classif.classe}">${classif.texto}</span></td>
                    </tr>
                `;
            }
            
            html += '</tbody></table>';
        }
        
        document.getElementById('studentsTable').innerHTML = html;
        atualizarStats();
    }

    // ============================================
    // FUNÇÕES DE CÁLCULO - MINI PAUTA
    // ============================================
    function calcularMiniMT(input, trimestre, alunoId) {
        var row = input.closest('tr');
        var inputs = row.querySelectorAll('input[data-trim="' + trimestre + '"][data-aluno="' + alunoId + '"]');
        var mac = parseFloat(inputs[0]?.value) || 0;
        var npt = parseFloat(inputs[1]?.value) || 0;
        var mt = (mac + npt) / 2;
        document.getElementById('mt' + trimestre + '_' + alunoId).textContent = mt.toFixed(1);
        calcularMiniMFED(alunoId);
        atualizarStats();
    }

    function calcularMiniIII(alunoId) {
        var mac3 = parseFloat(document.querySelector('input[data-trim="3"][data-aluno="' + alunoId + '"]')?.value) || 0;
        var neo = parseFloat(document.querySelector('input[data-trim="neo"][data-aluno="' + alunoId + '"]')?.value) || 0;
        var en = parseFloat(document.querySelector('input[data-trim="en"][data-aluno="' + alunoId + '"]')?.value) || 0;
        
        document.getElementById('mfd_' + alunoId).textContent = mac3.toFixed(1);
        var mec = (neo + en) / 2;
        document.getElementById('mec_' + alunoId).textContent = mec.toFixed(1);
        calcularMiniMFED(alunoId);
        atualizarStats();
    }

    function calcularMiniMFED(alunoId) {
        var mt1 = parseFloat(document.getElementById('mt1_' + alunoId)?.textContent) || 0;
        var mt2 = parseFloat(document.getElementById('mt2_' + alunoId)?.textContent) || 0;
        var mfd = parseFloat(document.getElementById('mfd_' + alunoId)?.textContent) || 0;
        var en = parseFloat(document.querySelector('input[data-trim="en"][data-aluno="' + alunoId + '"]')?.value) || 0;
        var mt3 = (mt1 + mt2 + mfd) / 3;
        var mfed = (0.6 * mt3) + (en * 0.4);
        document.getElementById('mfed_' + alunoId).textContent = mfed.toFixed(1);
        atualizarStats();
    }

    // ============================================
    // FUNÇÕES DE CÁLCULO - PAUTA NORMAL
    // ============================================
    function calcularMT(input, trimestre, alunoId) {
        var row = input.closest('tr');
        var inputs = row.querySelectorAll('input[data-trim="' + trimestre + '"][data-aluno="' + alunoId + '"]');
        var mac = parseFloat(inputs[0]?.value) || 0;
        var npt = parseFloat(inputs[1]?.value) || 0;
        var mt = (mac + npt) / 2;
        document.getElementById('mt' + trimestre + '_' + alunoId).textContent = mt.toFixed(1);
        calcularMFD(alunoId);
        atualizarStats();
    }

    function calcularMFD(alunoId) {
        var mt1 = parseFloat(document.getElementById('mt1_' + alunoId)?.textContent) || 0;
        var mt2 = parseFloat(document.getElementById('mt2_' + alunoId)?.textContent) || 0;
        var mt3 = parseFloat(document.getElementById('mt3_' + alunoId)?.textContent) || 0;
        var medias = [];
        if (mt1 > 0) medias.push(mt1);
        if (mt2 > 0) medias.push(mt2);
        if (mt3 > 0) medias.push(mt3);
        var mfd = medias.length > 0 ? medias.reduce(function(a, b) { return a + b; }, 0) / medias.length : 0;
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
        var rows = document.querySelectorAll('#studentsTable tr[data-id]');
        if (rows.length === 0) return;
        var soma = 0, total = 0, aprov = 0, reprov = 0;
        var notaMinima = isPreSexta ? 5 : 10;
        
        for (var i = 0; i < rows.length; i++) {
            var id = rows[i].dataset.id;
            var mfd = 0;
            if (isMiniPautaStyle) {
                mfd = parseFloat(document.getElementById('mfed_' + id)?.textContent) || 0;
            } else {
                mfd = parseFloat(document.getElementById('mfd_' + id)?.textContent) || 0;
            }
            if (mfd > 0) {
                soma += mfd;
                total++;
                if (mfd >= notaMinima) aprov++;
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
        if (!confirm('Deseja salvar todas as notas?')) return;
        
        var anoLetivo = document.getElementById('anoLetivo').value;
        var disciplina = document.getElementById('disciplina').value;
        var turma = document.getElementById('turma').value;
        var classe = document.getElementById('classe').value;
        
        turma = turma.replace('Turma ', '');
        turma = turma.trim();
        
        var rows = document.querySelectorAll('#studentsTable tr[data-id]');
        if (rows.length === 0) { alert('Nenhum aluno para salvar'); return; }
        
        var dados = [];
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var id = row.dataset.id;
            var aluno = alunos.find(function(a) { return a.id == id; });
            if (!aluno) continue;
            
            var nota = {
                id_aluno: parseInt(id),
                nome_aluno: aluno.nome,
                disciplina: disciplina,
                turma: turma,
                classe: classe || aluno.classe || '',
                ano_letivo: anoLetivo,
                sexo: aluno.sexo || '',
                idade: aluno.idade || 0,
                sala: '09',
                turno: 'MANHÃ'
            };
            
            if (isMiniPautaStyle) {
                nota.mac_t1 = row.querySelector('input[data-trim="1"][data-aluno="' + id + '"]')?.value || '';
                nota.npt_t1 = row.querySelectorAll('input[data-trim="1"][data-aluno="' + id + '"]')[1]?.value || '';
                nota.mac_t2 = row.querySelector('input[data-trim="2"][data-aluno="' + id + '"]')?.value || '';
                nota.npt_t2 = row.querySelectorAll('input[data-trim="2"][data-aluno="' + id + '"]')[1]?.value || '';
                nota.mac_t3 = row.querySelector('input[data-trim="3"][data-aluno="' + id + '"]')?.value || '';
                nota.neo = row.querySelector('input[data-trim="neo"][data-aluno="' + id + '"]')?.value || '';
                nota.en = row.querySelector('input[data-trim="en"][data-aluno="' + id + '"]')?.value || '';
            } else {
                for (var t = 1; t <= 3; t++) {
                    var inputs = row.querySelectorAll('input[data-trim="' + t + '"][data-aluno="' + id + '"]');
                    nota['mac_t' + t] = inputs[0]?.value || '';
                    nota['npt_t' + t] = inputs[1]?.value || '';
                }
                nota.neo = '';
                nota.en = '';
            }
            dados.push(nota);
        }
        
        var btn = document.getElementById('btnSalvar');
        btn.disabled = true;
        btn.textContent = '💾 Salvando...';
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                action: 'salvar_notas',
                notas: JSON.stringify(dados),
                ano_letivo: anoLetivo
            },
            success: function(response) {
                btn.disabled = false;
                btn.textContent = '💾 Salvar';
                if (response.success) {
                    alert('✅ ' + response.message);
                    buscarAlunos();
                } else {
                    alert('❌ ' + (response.message || 'Erro ao salvar'));
                }
            },
            error: function() {
                btn.disabled = false;
                btn.textContent = '💾 Salvar';
                alert('❌ Erro ao salvar notas');
            }
        });
    }

    // ============================================
    // VISUALIZAR PAUTA
    // ============================================
    function visualizarPauta() {
        if (!alunos || alunos.length === 0) {
            alert('⚠️ Busque os alunos primeiro');
            return;
        }
        
        var janela = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes');
        if (!janela) {
            alert('⚠️ Permitir popups para visualizar a pauta');
            return;
        }
        
        var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Pauta de Notas</title>';
        html += '<style>';
        html += 'body{font-family:"Times New Roman",Arial,sans-serif;padding:30px;background:#fff;}';
        html += '.print-header{text-align:center;margin-bottom:30px;position:relative;}';
        html += '.print-header h2{font-size:18px;margin:5px 0;cursor:pointer;}';
        html += '.print-header h3{font-size:16px;margin:5px 0;cursor:pointer;}';
        html += '.print-header .escola-nome{font-size:20px;font-weight:bold;text-transform:uppercase;margin:15px 0;cursor:pointer;}';
        html += '.print-header .titulo-pauta{font-size:22px;font-weight:bold;margin:20px 0 10px 0;cursor:pointer;}';
        html += '.print-header [contenteditable="true"]:hover{background:#f0f8ff;border-radius:4px;padding:2px 5px;}';
        html += '.print-header [contenteditable="true"]:focus{background:#e8f4fc;outline:2px solid #3498db;border-radius:4px;padding:2px 5px;}';
        html += '.info-linha{display:flex;justify-content:space-between;margin:15px 0;font-size:13px;flex-wrap:wrap;gap:10px;}';
        html += '.print-table{width:100%;border-collapse:collapse;font-size:11px;margin:20px 0;}';
        html += '.print-table th{background:#2c3e50;color:#fff;padding:8px 5px;text-align:center;border:1px solid #000;}';
        html += '.print-table td{padding:6px 5px;border:1px solid #000;text-align:center;}';
        html += '.print-table .aluno-nome{text-align:left;}';
        html += '.print-footer{margin-top:50px;display:flex;justify-content:space-between;}';
        html += '.assinatura{text-align:center;min-width:200px;}';
        html += '.linha-assinatura{border-top:1px solid #000;margin-top:40px;padding-top:5px;width:220px;}';
        
        // ===== INSÍGNIA ACIMA DO CABEÇALHO =====
        html += '.insignia-topo{text-align:center;margin-bottom:10px;}';
        html += '.insignia-topo img{max-height:90px;max-width:150px;cursor:pointer;}';
        html += '.insignia-placeholder{width:150px;height:90px;border:2px dashed #ccc;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;background:#fafafa;color:#999;font-size:11px;text-align:center;transition:all 0.3s;flex-direction:column;gap:4px;}';
        html += '.insignia-placeholder:hover{border-color:#3498db;background:#f0f8ff;color:#3498db;}';
        html += '.insignia-input{display:none;}';
        html += '.edit-hint{font-size:10px;color:#999;margin-bottom:8px;font-style:italic;}';
        
        // ===== IMPRESSÃO =====
        html += '@media print{';
        html += '  .no-print{display:none!important;}';
        html += '  .insignia-placeholder{display:none!important;}';
        html += '  .insignia-topo img{max-height:90px;max-width:150px;}';
        html += '  .edit-hint{display:none!important;}';
        html += '  body{padding:10px;}';
        html += '}';
        html += '</style></head><body>';
        
        // ===== INSÍGNIA (ACIMA DE TUDO) =====
        html += '<div class="insignia-topo">';
        html += '<div class="insignia-placeholder no-print" id="insigniaPlaceholder" onclick="document.getElementById(\'insigniaInput\').click()">';
        html += '<span style="font-size:22px;">📷</span>';
        html += '<span>Clique para<br>inserir insígnia</span>';
        html += '</div>';
        html += '<img id="insigniaImg" src="" style="display:none;" onclick="document.getElementById(\'insigniaInput\').click()" title="Clique para trocar a insígnia">';
        html += '<input type="file" class="insignia-input" id="insigniaInput" accept="image/*" onchange="carregarInsignia(event)">';
        html += '</div>';
        
        // ===== CABEÇALHO =====
        html += '<div class="print-header">';
        html += '<div class="edit-hint no-print">💡 Dê duplo clique em qualquer texto do cabeçalho para editar</div>';
        html += '<h2 contenteditable="false" ondblclick="this.contentEditable=true;this.focus();" onblur="this.contentEditable=false;">REPÚBLICA DE ANGOLA</h2>';
        html += '<h3 contenteditable="false" ondblclick="this.contentEditable=true;this.focus();" onblur="this.contentEditable=false;">GOVERNO DA PROVÍNCIA DO ICOLO E BENGO</h3>';
        html += '<h3 contenteditable="false" ondblclick="this.contentEditable=true;this.focus();" onblur="this.contentEditable=false;">DIRECÇÃO MUNICIPAL DE EDUCAÇÃO DO CALUMBO</h3>';
        html += '<div class="escola-nome" contenteditable="false" ondblclick="this.contentEditable=true;this.focus();" onblur="this.contentEditable=false;"><?= htmlspecialchars($escola_nome) ?></div>';
        html += '<div class="titulo-pauta" contenteditable="false" ondblclick="this.contentEditable=true;this.focus();" onblur="this.contentEditable=false;">' + (isMiniPautaStyle ? 'MINI PAUTA' : 'PAUTA DE NOTAS') + '</div>';
        
        html += '<div class="info-linha">';
        html += '<span><strong>Ano Letivo:</strong> ' + currentSelections.anoLetivo + '</span>';
        html += '<span><strong>Classe:</strong> ' + (currentSelections.classe || 'N/A') + 'ª</span>';
        html += '<span><strong>Turma:</strong> ' + currentSelections.turma + '</span>';
        html += '<span><strong>Disciplina:</strong> ' + currentSelections.disciplina + '</span>';
        html += '<span><strong>Trimestre:</strong> FINAL</span>';
        if (isCoordenador) {
            html += '<span><span style="background:#c9a84c;color:#1a2332;padding:2px 10px;border-radius:4px;">👑 Coordenador</span></span>';
        }
        html += '</div></div>';
        
        // ===== TABELA =====
        if (isMiniPautaStyle) {
            html += '<table class="print-table"><thead><tr>';
            html += '<th rowspan="2">Nº</th><th rowspan="2">NOME DO ALUNO</th>';
            html += '<th colspan="3">I TRIMESTRE</th><th colspan="3">II TRIMESTRE</th><th colspan="5">III TRIMESTRE</th>';
            html += '<th rowspan="2">MFED</th>';
            html += '</tr><tr>';
            html += '<th>MAC</th><th>NPT</th><th>MT</th>';
            html += '<th>MAC</th><th>NPT</th><th>MT</th>';
            html += '<th>MAC</th><th>MFD</th><th>NEO</th><th>EN</th><th>MEC</th>';
            html += '</tr></thead><tbody>';
            
            for (var i = 0; i < alunos.length; i++) {
                var a = alunos[i];
                var id = a.id;
                html += '<tr>';
                html += '<td>' + (i + 1) + '</td>';
                html += '<td class="aluno-nome">' + a.nome + '</td>';
                html += '<td>' + (document.querySelector('input[data-trim="1"][data-aluno="' + id + '"]')?.value || '-') + '</td>';
                html += '<td>' + (document.querySelectorAll('input[data-trim="1"][data-aluno="' + id + '"]')[1]?.value || '-') + '</td>';
                html += '<td>' + (document.getElementById('mt1_' + id)?.textContent || '0.0') + '</td>';
                html += '<td>' + (document.querySelector('input[data-trim="2"][data-aluno="' + id + '"]')?.value || '-') + '</td>';
                html += '<td>' + (document.querySelectorAll('input[data-trim="2"][data-aluno="' + id + '"]')[1]?.value || '-') + '</td>';
                html += '<td>' + (document.getElementById('mt2_' + id)?.textContent || '0.0') + '</td>';
                html += '<td>' + (document.querySelector('input[data-trim="3"][data-aluno="' + id + '"]')?.value || '-') + '</td>';
                html += '<td>' + (document.getElementById('mfd_' + id)?.textContent || '0.0') + '</td>';
                html += '<td>' + (document.querySelector('input[data-trim="neo"][data-aluno="' + id + '"]')?.value || '-') + '</td>';
                html += '<td>' + (document.querySelector('input[data-trim="en"][data-aluno="' + id + '"]')?.value || '-') + '</td>';
                html += '<td>' + (document.getElementById('mec_' + id)?.textContent || '0.0') + '</td>';
                html += '<td><strong>' + (document.getElementById('mfed_' + id)?.textContent || '0.0') + '</strong></td>';
                html += '</tr>';
            }
        } else {
            html += '<table class="print-table"><thead><tr>';
            html += '<th rowspan="2">Nº</th><th rowspan="2">NOME DO ALUNO</th>';
            html += '<th colspan="3">1º TRIMESTRE</th><th colspan="3">2º TRIMESTRE</th><th colspan="3">3º TRIMESTRE</th>';
            html += '<th rowspan="2">MFD/' + maxPontos + '</th><th rowspan="2">CLASSIFICAÇÃO</th>';
            html += '</tr><tr>';
            html += '<th>MAC</th><th>NPT</th><th>MT</th>';
            html += '<th>MAC</th><th>NPT</th><th>MT</th>';
            html += '<th>MAC</th><th>NPT</th><th>MT</th>';
            html += '</tr></thead><tbody>';
            
            for (var i = 0; i < alunos.length; i++) {
                var a = alunos[i];
                var id = a.id;
                html += '<tr>';
                html += '<td>' + (i + 1) + '</td>';
                html += '<td class="aluno-nome">' + a.nome + '</td>';
                html += '<td>' + (document.querySelector('input[data-trim="1"][data-aluno="' + id + '"]')?.value || '-') + '</td>';
                html += '<td>' + (document.querySelectorAll('input[data-trim="1"][data-aluno="' + id + '"]')[1]?.value || '-') + '</td>';
                html += '<td>' + (document.getElementById('mt1_' + id)?.textContent || '0.0') + '</td>';
                html += '<td>' + (document.querySelector('input[data-trim="2"][data-aluno="' + id + '"]')?.value || '-') + '</td>';
                html += '<td>' + (document.querySelectorAll('input[data-trim="2"][data-aluno="' + id + '"]')[1]?.value || '-') + '</td>';
                html += '<td>' + (document.getElementById('mt2_' + id)?.textContent || '0.0') + '</td>';
                html += '<td>' + (document.querySelector('input[data-trim="3"][data-aluno="' + id + '"]')?.value || '-') + '</td>';
                html += '<td>' + (document.querySelectorAll('input[data-trim="3"][data-aluno="' + id + '"]')[1]?.value || '-') + '</td>';
                html += '<td>' + (document.getElementById('mt3_' + id)?.textContent || '0.0') + '</td>';
                html += '<td><strong>' + (document.getElementById('mfd_' + id)?.textContent || '0.0') + '</strong></td>';
                html += '<td>' + (document.getElementById('class_' + id)?.textContent || '') + '</td>';
                html += '</tr>';
            }
        }
        
        html += '</tbody></table>';
        html += '<div class="print-footer">';
        html += '<div class="assinatura"><div>O Professor</div><div class="linha-assinatura"></div><div><?= htmlspecialchars($usuario_nome) ?></div></div>';
        html += '<div class="assinatura"><div>O Director Pedagógico</div><div class="linha-assinatura"></div><div></div></div>';
        html += '</div>';
        
        // ===== SCRIPT =====
        html += '<script>';
        
        // Carregar insígnia
        html += 'function carregarInsignia(event){';
        html += '  var file = event.target.files[0];';
        html += '  if(!file) return;';
        html += '  var reader = new FileReader();';
        html += '  reader.onload = function(e){';
        html += '    var placeholder = document.getElementById("insigniaPlaceholder");';
        html += '    var img = document.getElementById("insigniaImg");';
        html += '    placeholder.style.display = "none";';
        html += '    img.src = e.target.result;';
        html += '    img.style.display = "inline-block";';
        html += '  };';
        html += '  reader.readAsDataURL(file);';
        html += '}';
        
        // Blur nos contenteditable
        html += 'document.querySelectorAll("[contenteditable]").forEach(function(el){';
        html += '  el.addEventListener("blur", function(){ this.contentEditable = false; });';
        html += '  el.addEventListener("keydown", function(e){ if(e.key === "Enter" && !e.shiftKey){ e.preventDefault(); this.blur(); } });';
        html += '});';
        
        // Impressão automática
        html += 'window.onload = function(){';
        html += '  setTimeout(function(){ window.print(); }, 1500);';
        html += '};';
        
        html += '<\/script>';
        html += '</body></html>';
        
        janela.document.write(html);
        janela.document.close();
    }




    // ============================================
    // LIMPAR
    // ============================================
    function limparTudo() {
        if (!confirm('Deseja limpar todos os dados?')) return;
        document.getElementById('classe').value = '';
        document.getElementById('turma').value = '';
        document.getElementById('disciplina').value = '';
        document.querySelector('.search-btn').disabled = true;
        document.getElementById('btnSalvar').disabled = true;
        document.getElementById('studentsTable').innerHTML = '<div class="info-text">🔍 Selecione a turma e disciplina</div>';
        document.getElementById('statsBar').style.display = 'none';
        document.getElementById('classificationInfo').style.display = 'none';
        document.getElementById('tipoPautaDisplay').innerHTML = '';
        alunos = [];
        notas = {};
        
        if (isCoordenador) {
            document.getElementById('statusText').innerHTML = '<strong>Status:</strong> 👑 Coordenador - Selecione os filtros';
        }
    }

    // ============================================
    // INICIALIZAR
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        console.log('📝 Sistema de Notas inicializado');
        console.log('👑 Coordenador:', isCoordenador);
        
        if (isCoordenador) {
            document.getElementById('statusText').innerHTML = '<strong>Status:</strong> 👑 Coordenador Pedagógico - Acesso total a todas as turmas';
            // Habilitar todos os selects
            document.getElementById('turma').disabled = false;
            document.getElementById('disciplina').disabled = false;
        }
    });
    </script>
</body>
</html>