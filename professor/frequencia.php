<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

// Verifica se é professor
$perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
if ($perfil != 'professor' && $perfil != 'docente') {
    header("Location: ../index.php");
    exit;
}

// Incluir configurações
require_once '../config/database.php';

// Dados do usuário
$nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$email = $_SESSION['usuario_email'] ?? '';
$page_title = 'Marcar Frequência';
$active_page = 'frequencia';

// ==========================================
// BUSCAR DADOS DA EMPRESA
// ==========================================
$nome_escola = 'Sistema de Gestão Escolar';
$empresa_dados = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa) {
        $nome_escola = $empresa['razao_social'] ?? $empresa['nome_fantasia'] ?? 'Sistema de Gestão Escolar';
        $empresa_dados = $empresa;
    }
} catch (Exception $e) {}

// ==========================================
// BUSCAR PROFESSOR - CORRIGIDO USANDO num_agente
// ==========================================
$professor_num_agente = null;
$professor_nome = '';
try {
    if (!empty($email)) {
        // Buscar o num_agente do professor
        $stmt = $pdo->prepare("SELECT num_agente, nome FROM funcionarios WHERE email = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$email]);
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($professor) {
            $professor_num_agente = $professor['num_agente'];
            $professor_nome = $professor['nome'] ?? $nome;
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar professor: " . $e->getMessage());
}

// ==========================================
// BUSCAR TURMAS - USANDO num_agente
// ==========================================
$turmas = [];
$total_turmas = 0;
if ($professor_num_agente) {
    try {
        // Buscar distribuições usando o num_agente
        $stmt = $pdo->prepare("SELECT * FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR' ORDER BY turma_nome");
        $stmt->execute([$professor_num_agente]);
        $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($distribuicoes as $dist) {
            // Buscar disciplinas da turma
            $disciplinas_array = [];
            if (!empty($dist['disciplinas'])) {
                $disciplinas_array = array_map('trim', explode(',', $dist['disciplinas']));
            }
            
            $turmas[] = [
                'id' => $dist['turma_id'],
                'nome' => $dist['turma_nome'],
                'classe' => $dist['classe'],
                'disciplinas' => $dist['disciplinas'],
                'disciplinas_array' => $disciplinas_array,
                'ano_letivo' => $dist['ano_letivo'] ?? date('Y')
            ];
        }
        $total_turmas = count($turmas);
    } catch (Exception $e) {
        error_log("Erro ao buscar turmas: " . $e->getMessage());
    }
}

// ==========================================
// BUSCAR DISCIPLINAS - A PARTIR DAS DISTRIBUIÇÕES
// ==========================================
$disciplinas = [];
$total_disciplinas = 0;
if ($professor_num_agente) {
    try {
        // Buscar disciplinas das distribuições
        $sql = "SELECT DISTINCT disciplinas FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$professor_num_agente]);
        $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $disciplinas_temp = [];
        foreach ($resultados as $discs) {
            if (!empty($discs)) {
                $items = array_map('trim', explode(',', $discs));
                foreach ($items as $item) {
                    if (!empty($item) && !in_array($item, $disciplinas_temp)) {
                        $disciplinas_temp[] = $item;
                    }
                }
            }
        }
        
        // Se não encontrou disciplinas, usar um fallback
        if (empty($disciplinas_temp)) {
            $disciplinas_temp = ['Matemática', 'Língua Portuguesa', 'Biologia', 'Física', 'Química'];
        }
        
        sort($disciplinas_temp);
        
        // Criar array com id fictício para as disciplinas
        foreach ($disciplinas_temp as $index => $nome_disc) {
            $disciplinas[] = [
                'id' => $index + 1,
                'nome' => $nome_disc,
                'status' => 'ativa'
            ];
        }
        $total_disciplinas = count($disciplinas);
    } catch (Exception $e) {
        error_log("Erro ao buscar disciplinas: " . $e->getMessage());
    }
}

// ==========================================
// BUSCAR ALUNOS DA TURMA SELECIONADA
// ==========================================
$alunos = [];
$turma_selecionada = $_GET['turma'] ?? null;
$data_selecionada = $_GET['data'] ?? date('Y-m-d');

// FILTROS PARA RELATÓRIO
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$aluno_filtro = $_GET['aluno'] ?? null;
$status_filtro = $_GET['status'] ?? 'todos';

if (isset($_GET['disciplinas'])) {
    if (is_array($_GET['disciplinas'])) {
        $disciplinas_selecionadas = $_GET['disciplinas'];
    } else {
        $disciplinas_selecionadas = explode(',', $_GET['disciplinas']);
    }
} else {
    $disciplinas_selecionadas = [];
}

$disciplinas_selecionadas = array_map('intval', $disciplinas_selecionadas);

// Buscar todos os alunos da turma para o filtro
$todos_alunos_turma = [];
$turma_nome = '';
$turma_classe = '';

if ($turma_selecionada) {
    try {
        $stmt = $pdo->prepare("SELECT turma_nome, classe FROM destribuicao_professores WHERE turma_id = ? LIMIT 1");
        $stmt->execute([$turma_selecionada]);
        $turma_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($turma_info) {
            $turma_nome = $turma_info['turma_nome'];
            $turma_classe = $turma_info['classe'];
            $stmt = $pdo->prepare("SELECT id, nome FROM alunos WHERE TURMA = ? AND status = 'ativo' ORDER BY nome");
            $stmt->execute([$turma_nome]);
            $todos_alunos_turma = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

if ($turma_selecionada) {
    try {
        $stmt = $pdo->prepare("SELECT turma_nome, classe FROM destribuicao_professores WHERE turma_id = ? LIMIT 1");
        $stmt->execute([$turma_selecionada]);
        $turma_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($turma_info) {
            $turma_nome = $turma_info['turma_nome'];
            $turma_classe = $turma_info['classe'];
            
            $stmt = $pdo->prepare("SELECT id, nome, Sexo as sexo FROM alunos WHERE TURMA = ? AND status = 'ativo' ORDER BY nome");
            $stmt->execute([$turma_nome]);
            $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar alunos: " . $e->getMessage());
    }
}

// ==========================================
// PROCESSAR SUBMISSÃO
// ==========================================
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_frequencia'])) {
    try {
        $data = $_POST['data'] ?? date('Y-m-d');
        $turma_id = $_POST['turma_id'] ?? null;
        $presencas = $_POST['presenca'] ?? [];
        
        if (!$turma_id) {
            throw new Exception("Selecione uma turma.");
        }
        
        if (empty($presencas)) {
            throw new Exception("Nenhuma presença foi marcada.");
        }
        
        $total_registros = 0;
        
        foreach ($presencas as $aluno_id => $disciplinas_aluno) {
            if (is_array($disciplinas_aluno)) {
                foreach ($disciplinas_aluno as $disciplina_id => $status) {
                    if (empty($status)) continue;
                    
                    // Verificar se já existe registro
                    $stmt = $pdo->prepare("SELECT id FROM frequencia WHERE aluno_id = ? AND turma_id = ? AND disciplina_id = ? AND data = ? LIMIT 1");
                    $stmt->execute([$aluno_id, $turma_id, $disciplina_id, $data]);
                    $existente = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existente) {
                        $stmt = $pdo->prepare("UPDATE frequencia SET status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$status, $existente['id']]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO frequencia (aluno_id, turma_id, disciplina_id, data, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$aluno_id, $turma_id, $disciplina_id, $data, $status]);
                    }
                    $total_registros++;
                }
            }
        }
        
        $mensagem = "✅ Frequência salva com sucesso! ($total_registros registros)";
        $tipo_mensagem = "success";
        
        $disc_params = !empty($disciplinas_selecionadas) ? '&disciplinas=' . implode(',', $disciplinas_selecionadas) : '';
        header("Location: frequencia.php?turma={$turma_id}&data={$data}{$disc_params}&success=1");
        exit;
        
    } catch (Exception $e) {
        $mensagem = "❌ " . $e->getMessage();
        $tipo_mensagem = "error";
    }
}

// ==========================================
// BUSCAR FREQUÊNCIA JÁ SALVA
// ==========================================
$frequencia_salva = [];
if ($turma_selecionada && !empty($alunos) && !empty($disciplinas_selecionadas)) {
    try {
        $placeholders = implode(',', array_fill(0, count($disciplinas_selecionadas), '?'));
        $params = array_merge($disciplinas_selecionadas, [$turma_selecionada, $data_selecionada]);
        
        $stmt = $pdo->prepare("
            SELECT f.aluno_id, f.disciplina_id, f.status 
            FROM frequencia f
            WHERE f.disciplina_id IN ($placeholders) 
            AND f.turma_id = ? 
            AND f.data = ?
        ");
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultados as $f) {
            $key = $f['aluno_id'] . '_' . $f['disciplina_id'];
            $frequencia_salva[$key] = $f['status'];
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar frequência: " . $e->getMessage());
    }
}

date_default_timezone_set('Africa/Luanda');
$hora_atual = date('H:i:s');
$data_br = date('d/m/Y');

// ==========================================
// CALCULAR ESTATÍSTICAS POR ALUNO
// ==========================================
$estatisticas_alunos = [];
$total_alunos_completos = 0;
$total_alunos_pendentes = 0;
$total_alunos_presentes = 0;
$total_alunos_ausentes = 0;
$total_alunos_justificados = 0;

if (!empty($alunos) && !empty($disciplinas_selecionadas)) {
    foreach ($alunos as $aluno) {
        $presentes = 0;
        $ausentes = 0;
        $justificados = 0;
        $pendentes = 0;
        $tem_presente = false;
        $tem_ausente = false;
        $tem_justificado = false;
        
        foreach ($disciplinas_selecionadas as $disc_id) {
            $key = $aluno['id'] . '_' . $disc_id;
            $status = $frequencia_salva[$key] ?? 'pendente';
            
            if ($status == 'presente') {
                $presentes++;
                $tem_presente = true;
            } elseif ($status == 'ausente') {
                $ausentes++;
                $tem_ausente = true;
            } elseif ($status == 'justificado') {
                $justificados++;
                $tem_justificado = true;
            } else {
                $pendentes++;
            }
        }
        
        $total_disciplinas_aluno = count($disciplinas_selecionadas);
        $marcadas = $presentes + $ausentes + $justificados;
        $percentual = $total_disciplinas_aluno > 0 ? round(($marcadas / $total_disciplinas_aluno) * 100) : 0;
        
        $status_aluno = ($percentual == 100) ? 'Completo' : 'Pendente';
        
        $estatisticas_alunos[$aluno['id']] = [
            'nome' => $aluno['nome'],
            'sexo' => $aluno['sexo'] ?? '-',
            'presentes' => $presentes,
            'ausentes' => $ausentes,
            'justificados' => $justificados,
            'pendentes' => $pendentes,
            'total_disciplinas' => $total_disciplinas_aluno,
            'marcadas' => $marcadas,
            'percentual' => $percentual,
            'status_geral' => $status_aluno,
            'tem_presente' => $tem_presente,
            'tem_ausente' => $tem_ausente,
            'tem_justificado' => $tem_justificado
        ];
        
        if ($status_aluno == 'Completo') {
            $total_alunos_completos++;
        } else {
            $total_alunos_pendentes++;
        }
        
        if ($tem_presente) $total_alunos_presentes++;
        if ($tem_ausente) $total_alunos_ausentes++;
        if ($tem_justificado) $total_alunos_justificados++;
    }
}

$total_alunos = count($alunos);
$percentual_alunos_completos = $total_alunos > 0 ? round(($total_alunos_completos / $total_alunos) * 100) : 0;
$percentual_alunos_pendentes = 100 - $percentual_alunos_completos;

// Incluir cabeçalho
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- ===== MAIN CONTENT ===== -->
<main class="main-content">
    <!-- ===== TOPBAR ===== -->
    <div class="topbar animate-fade-up">
        <div class="topbar-left">
            <div class="page-title">
                <h2>📋 Marcar Frequência</h2>
                <p>Selecione as disciplinas e marque a presença com checkboxes</p>
            </div>
        </div>
        <div class="topbar-right">
            <div class="date-time">
                <div class="time"><?= $hora_atual ?></div>
                <div><?= $data_br ?></div>
            </div>
            <div class="status-indicator">
                <span class="dot"></span> Online
            </div>
        </div>
    </div>

    <!-- ===== MENSAGEM ===== -->
    <?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo_mensagem === 'success' ? 'success' : 'error' ?> animate-fade-up delay-1">
        <?= $mensagem ?>
    </div>
    <?php endif; ?>

    <!-- ===== FORMULÁRIO DE SELEÇÃO ===== -->
    <div class="card animate-fade-up delay-1">
        <div class="card-header">
            <h3><i class="fas fa-sliders-h"></i> Selecionar Turma e Disciplinas</h3>
        </div>
        <form method="GET" action="" id="formFiltro">
            <div class="row">
                <div class="form-group">
                    <label><i class="fas fa-users"></i> Turma</label>
                    <select name="turma" class="form-control" onchange="this.form.submit()">
                        <option value="">Selecione uma turma</option>
                        <?php foreach ($turmas as $turma): ?>
                        <option value="<?= $turma['id'] ?>" <?= ($turma_selecionada == $turma['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($turma['nome']) ?> - <?= htmlspecialchars($turma['classe']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($turmas)): ?>
                    <small style="color:#ef4444;display:block;margin-top:4px;">
                        <i class="fas fa-exclamation-circle"></i> Nenhuma turma atribuída. Verifique se o Nº Agente está configurado.
                    </small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-book"></i> Disciplinas</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; padding: 8px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <?php if (!empty($disciplinas)): ?>
                            <?php foreach ($disciplinas as $disciplina): ?>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 4px 10px; background: <?= in_array($disciplina['id'], $disciplinas_selecionadas) ? 'var(--primary-light)' : 'white' ?>; border-radius: 6px; border: 1px solid <?= in_array($disciplina['id'], $disciplinas_selecionadas) ? 'var(--primary)' : '#e2e8f0' ?>;">
                                <input type="checkbox" name="disciplinas[]" value="<?= $disciplina['id'] ?>" 
                                       <?= in_array($disciplina['id'], $disciplinas_selecionadas) ? 'checked' : '' ?>
                                       onchange="this.form.submit()" style="accent-color: var(--primary); width: 16px; height: 16px;">
                                <span style="font-size: 13px; font-weight: <?= in_array($disciplina['id'], $disciplinas_selecionadas) ? '600' : '400' ?>;">
                                    <?= htmlspecialchars($disciplina['nome']) ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:13px;">
                                <i class="fas fa-info-circle"></i> Nenhuma disciplina disponível
                            </span>
                        <?php endif; ?>
                    </div>
                    <small style="color: var(--text-secondary); font-size: 11px; display: block; margin-top: 4px;">
                        <i class="fas fa-info-circle"></i> Marque/Desmarque as disciplinas para filtrar
                    </small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Data</label>
                    <input type="date" name="data" class="form-control" value="<?= $data_selecionada ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>
    </div>

    <!-- ===== TABELA DE FREQUÊNCIA ===== -->
    <?php if ($turma_selecionada && !empty($disciplinas_selecionadas) && !empty($alunos)): ?>
    
    <div class="card animate-fade-up delay-2">
        <div class="card-header">
            <h3>
                <i class="fas fa-user-check"></i>
                Marcar Presenças
                <span style="font-size: 12px; color: var(--text-secondary); font-weight: normal;">
                    (<?= count($alunos) ?> alunos | <?= count($disciplinas_selecionadas) ?> disciplinas)
                </span>
            </h3>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <span class="badge badge-primary">
                    <span class="dot"></span> <?= $turma_nome ?? 'Turma' ?> - <?= $turma_classe ?? '' ?>
                </span>
                <span class="badge" style="background:#f1f5f9; color:#64748b;">
                    <i class="far fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($data_selecionada)) ?>
                </span>
                <span class="badge" style="background:#dbeafe; color:#1e40af;">
                    <i class="fas fa-sync-alt"></i> Modo: Marcação única por aluno
                </span>
            </div>
        </div>
        
        <form method="POST" action="" id="formFrequencia">
            <input type="hidden" name="turma_id" value="<?= $turma_selecionada ?>">
            <input type="hidden" name="data" value="<?= $data_selecionada ?>">
            
            <div style="margin-bottom: 10px; padding: 8px 12px; background: #f0fdf4; border-radius: 6px; border: 1px solid #bbf7d0;">
                <small style="color: #166534;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Dica:</strong> Clique em <strong>P</strong>, <strong>F</strong> ou <strong>J</strong> na coluna "Marcar Todos" para aplicar a mesma frequência em todas as disciplinas do aluno.
                </small>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:30px;">#</th>
                            <th style="min-width:150px;">Aluno</th>
                            <th style="width:50px;">Sexo</th>
                            <?php foreach ($disciplinas_selecionadas as $disc_id): 
                                $nome_disc = '';
                                foreach ($disciplinas as $d) {
                                    if ($d['id'] == $disc_id) { $nome_disc = $d['nome']; break; }
                                }
                            ?>
                            <th style="min-width:180px; text-align:center;">
                                <?= htmlspecialchars($nome_disc) ?>
                                <br>
                                <small style="font-weight:400; font-size:10px; color:var(--text-light);">
                                    <span style="color:#22c55e;">P</span> | 
                                    <span style="color:#ef4444;">F</span> | 
                                    <span style="color:#f59e0b;">J</span>
                                </small>
                            </th>
                            <?php endforeach; ?>
                            <th style="min-width:140px; text-align:center; background: #f0fdf4;">
                                <span style="color: #16a34a; font-weight: 600;">⚡ Marcar Todos</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alunos as $index => $aluno): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= htmlspecialchars($aluno['nome']) ?></strong></td>
                            <td><?= htmlspecialchars($aluno['sexo'] ?? '-') ?></td>
                            
                            <?php foreach ($disciplinas_selecionadas as $disc_id): 
                                $key = $aluno['id'] . '_' . $disc_id;
                                $status = $frequencia_salva[$key] ?? '';
                            ?>
                            <td style="text-align:center;">
                                <div class="checkbox-group" style="display:flex; gap:8px; justify-content:center; align-items:center;">
                                    <label class="checkbox-label <?= $status == 'presente' ? 'active-presente' : '' ?>" style="display:flex; align-items:center; gap:4px; cursor:pointer; font-size:13px;">
                                        <input type="checkbox" name="presenca[<?= $aluno['id'] ?>][<?= $disc_id ?>]" value="presente" 
                                               <?= $status == 'presente' ? 'checked' : '' ?>
                                               class="presenca-checkbox" data-aluno="<?= $aluno['id'] ?>" data-disc="<?= $disc_id ?>">
                                        <span style="color:#22c55e; font-weight:600;">P</span>
                                    </label>
                                    <label class="checkbox-label <?= $status == 'ausente' ? 'active-falta' : '' ?>" style="display:flex; align-items:center; gap:4px; cursor:pointer; font-size:13px;">
                                        <input type="checkbox" name="presenca[<?= $aluno['id'] ?>][<?= $disc_id ?>]" value="ausente" 
                                               <?= $status == 'ausente' ? 'checked' : '' ?>
                                               class="presenca-checkbox" data-aluno="<?= $aluno['id'] ?>" data-disc="<?= $disc_id ?>">
                                        <span style="color:#ef4444; font-weight:600;">F</span>
                                    </label>
                                    <label class="checkbox-label <?= $status == 'justificado' ? 'active-justificado' : '' ?>" style="display:flex; align-items:center; gap:4px; cursor:pointer; font-size:13px;">
                                        <input type="checkbox" name="presenca[<?= $aluno['id'] ?>][<?= $disc_id ?>]" value="justificado" 
                                               <?= $status == 'justificado' ? 'checked' : '' ?>
                                               class="presenca-checkbox" data-aluno="<?= $aluno['id'] ?>" data-disc="<?= $disc_id ?>">
                                        <span style="color:#f59e0b; font-weight:600;">J</span>
                                    </label>
                                </div>
                            </td>
                            <?php endforeach; ?>
                            
                            <td style="text-align:center; background: #f0fdf4;">
                                <div style="display:flex; gap:6px; justify-content:center; align-items:center; flex-wrap:wrap;">
                                    <button type="button" class="btn-marcar-todos" data-aluno="<?= $aluno['id'] ?>" data-status="presente" style="padding:6px 14px; background:#22c55e; color:white; border:none; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; transition: all 0.2s ease;">
                                        P
                                    </button>
                                    <button type="button" class="btn-marcar-todos" data-aluno="<?= $aluno['id'] ?>" data-status="ausente" style="padding:6px 14px; background:#ef4444; color:white; border:none; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; transition: all 0.2s ease;">
                                        F
                                    </button>
                                    <button type="button" class="btn-marcar-todos" data-aluno="<?= $aluno['id'] ?>" data-status="justificado" style="padding:6px 14px; background:#f59e0b; color:white; border:none; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; transition: all 0.2s ease;">
                                        J
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top:15px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <button type="submit" name="salvar_frequencia" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Frequência
                </button>
                <button type="button" class="btn btn-success" onclick="marcarTodosAlunos('presente')">
                    <i class="fas fa-check-double"></i> Todos Presentes
                </button>
                <button type="button" class="btn btn-danger" onclick="marcarTodosAlunos('ausente')">
                    <i class="fas fa-times-double"></i> Todos Faltas
                </button>
                <button type="button" class="btn btn-warning" onclick="marcarTodosAlunos('justificado')">
                    <i class="fas fa-file-alt"></i> Todos Justificados
                </button>
                <button type="button" class="btn btn-outline" onclick="limparTudo()">
                    <i class="fas fa-undo"></i> Limpar Tudo
                </button>
            </div>
        </form>
    </div>
    
    <?php elseif ($turma_selecionada && empty($disciplinas_selecionadas)): ?>
    <div class="card animate-fade-up delay-2">
        <div class="empty-state">
            <div class="icon">📚</div>
            <h4>Selecione pelo menos uma disciplina</h4>
            <p>Marque as disciplinas acima para começar a marcar a frequência.</p>
        </div>
    </div>
    
    <?php elseif ($turma_selecionada && empty($alunos)): ?>
    <div class="card animate-fade-up delay-2">
        <div class="empty-state">
            <div class="icon">👨‍🎓</div>
            <h4>Nenhum aluno nesta turma</h4>
            <p>Esta turma não possui alunos cadastrados.</p>
        </div>
    </div>
    
    <?php else: ?>
    <div class="card animate-fade-up delay-2">
        <div class="empty-state">
            <div class="icon">📋</div>
            <h4>Selecione uma turma e disciplina</h4>
            <p>Escolha acima para começar a marcar a frequência.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== RESUMO GERAL ===== -->
    <?php if ($turma_selecionada && !empty($disciplinas_selecionadas) && !empty($alunos)): ?>
    
    <div class="card animate-fade-up delay-3">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Resumo Geral da Frequência</h3>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <span class="badge badge-primary">
                    <span class="dot"></span> <?= date('d/m/Y', strtotime($data_selecionada)) ?>
                </span>
                <button onclick="abrirFiltrosRelatorio()" class="btn btn-success" style="padding: 10px 25px; font-size: 15px; border-radius: 8px; font-weight: 600;">
                    <i class="fas fa-file-pdf"></i> Gerar Relatório Analítico
                </button>
                <button onclick="window.print()" class="btn btn-outline" style="padding: 4px 12px; font-size: 12px;">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>
        
        <!-- CARDS DE RESUMO - CONTAGEM POR ALUNO -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:15px;">
            <div style="text-align:center;padding:15px;background:#ecfdf5;border-radius:8px;border:1px solid #bbf7d0;">
                <div style="font-size:32px;font-weight:700;color:#22c55e;"><?= $total_alunos_presentes ?></div>
                <div style="font-size:13px;color:#065f46;font-weight:600;">✅ Alunos Presentes</div>
                <div style="font-size:11px;color:#86efac;"><?= $total_alunos > 0 ? round(($total_alunos_presentes / $total_alunos) * 100) : 0 ?>% da turma</div>
            </div>
            
            <div style="text-align:center;padding:15px;background:#fef2f2;border-radius:8px;border:1px solid #fecaca;">
                <div style="font-size:32px;font-weight:700;color:#ef4444;"><?= $total_alunos_ausentes ?></div>
                <div style="font-size:13px;color:#991b1b;font-weight:600;">❌ Alunos com Falta</div>
                <div style="font-size:11px;color:#fca5a5;"><?= $total_alunos > 0 ? round(($total_alunos_ausentes / $total_alunos) * 100) : 0 ?>% da turma</div>
            </div>
            
            <div style="text-align:center;padding:15px;background:#fffbeb;border-radius:8px;border:1px solid #fde68a;">
                <div style="font-size:32px;font-weight:700;color:#f59e0b;"><?= $total_alunos_justificados ?></div>
                <div style="font-size:13px;color:#92400e;font-weight:600;">📝 Alunos Justificados</div>
                <div style="font-size:11px;color:#fcd34d;"><?= $total_alunos > 0 ? round(($total_alunos_justificados / $total_alunos) * 100) : 0 ?>% da turma</div>
            </div>
            
            <div style="text-align:center;padding:15px;background:#dbeafe;border-radius:8px;border:1px solid #93c5fd;">
                <div style="font-size:32px;font-weight:700;color:#2563eb;"><?= $total_alunos ?></div>
                <div style="font-size:13px;color:#1e40af;font-weight:600;">👨‍🎓 Total de Alunos</div>
                <div style="font-size:11px;color:#93c5fd;"><?= count($disciplinas_selecionadas) ?> disciplinas</div>
            </div>
        </div>
        
        <!-- BARRA DE PROGRESSO -->
        <div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);margin-bottom:4px;">
                <span>Progresso da Turma (Alunos Completos)</span>
                <span><?= $percentual_alunos_completos ?>% (<?= $total_alunos_completos ?>/<?= $total_alunos ?> alunos)</span>
            </div>
            <div style="width:100%;height:10px;background:#e2e8f0;border-radius:6px;overflow:hidden;">
                <div style="width:<?= $percentual_alunos_completos ?>%;height:100%;background:var(--primary-gradient);border-radius:6px;transition:width 0.5s ease;"></div>
            </div>
        </div>
    </div>
    
    <!-- ===== RESUMO ANALÍTICO POR ALUNO ===== -->
    <div class="card animate-fade-up delay-4">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> Resumo Analítico por Aluno</h3>
            <span class="badge badge-primary">
                <?= count($alunos) ?> alunos | <?= count($disciplinas_selecionadas) ?> disciplinas
            </span>
        </div>
        
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f1f5f9;">
                        <th style="padding:8px;border:1px solid #e2e8f0;text-align:left;">#</th>
                        <th style="padding:8px;border:1px solid #e2e8f0;text-align:left;">Aluno</th>
                        <th style="padding:8px;border:1px solid #e2e8f0;text-align:center;background:#ecfdf5;">✅ Presente</th>
                        <th style="padding:8px;border:1px solid #e2e8f0;text-align:center;background:#fef2f2;">❌ Falta</th>
                        <th style="padding:8px;border:1px solid #e2e8f0;text-align:center;background:#fffbeb;">📝 Justificado</th>
                        <th style="padding:8px;border:1px solid #e2e8f0;text-align:center;background:#f1f5f9;">⏳ Pendentes</th>
                        <th style="padding:8px;border:1px solid #e2e8f0;text-align:center;">Status</th>
                        <th style="padding:8px;border:1px solid #e2e8f0;text-align:center;">Progresso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunos as $index => $aluno): 
                        $est = $estatisticas_alunos[$aluno['id']] ?? null;
                        if (!$est) continue;
                        
                        $status_class = $est['status_geral'] == 'Completo' ? 'badge-success' : 'badge-warning';
                        $status_icon = $est['status_geral'] == 'Completo' ? '✅' : '⏳';
                        $percentual = $est['percentual'];
                    ?>
                    <tr>
                        <td style="padding:8px;border:1px solid #e2e8f0;"><?= $index + 1 ?></td>
                        <td style="padding:8px;border:1px solid #e2e8f0;"><strong><?= htmlspecialchars($est['nome']) ?></strong></td>
                        <td style="text-align:center;padding:8px;border:1px solid #e2e8f0;color:#22c55e;font-weight:600;background:#f0fdf4;">
                            <?= $est['tem_presente'] ? '✅ Sim' : '❌ Não' ?>
                        </td>
                        <td style="text-align:center;padding:8px;border:1px solid #e2e8f0;color:#ef4444;font-weight:600;background:#fef2f2;">
                            <?= $est['tem_ausente'] ? '✅ Sim' : '❌ Não' ?>
                        </td>
                        <td style="text-align:center;padding:8px;border:1px solid #e2e8f0;color:#f59e0b;font-weight:600;background:#fffbeb;">
                            <?= $est['tem_justificado'] ? '✅ Sim' : '❌ Não' ?>
                        </td>
                        <td style="text-align:center;padding:8px;border:1px solid #e2e8f0;color:#94a3b8;font-weight:600;background:#f8fafc;">
                            <?= $est['pendentes'] ?>
                        </td>
                        <td style="text-align:center;padding:8px;border:1px solid #e2e8f0;">
                            <span class="badge <?= $status_class ?>" style="font-size:12px;padding:4px 12px;">
                                <?= $status_icon ?> <?= $est['status_geral'] ?>
                            </span>
                        </td>
                        <td style="text-align:center;padding:8px;border:1px solid #e2e8f0;">
                            <div style="display:flex;align-items:center;gap:8px;justify-content:center;">
                                <div style="width:80px;height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden;">
                                    <div style="width:<?= $percentual ?>%;height:100%;background:<?= $percentual == 100 ? '#22c55e' : '#3b82f6' ?>;border-radius:3px;"></div>
                                </div>
                                <span style="font-size:11px;font-weight:600;min-width:35px;"><?= $percentual ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- LEGENDA -->
        <div style="margin-top:15px;padding:12px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;display:flex;gap:20px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="badge badge-success" style="font-size:12px;">✅ Completo</span>
                <span style="font-size:12px;color:#64748b;">= Aluno marcou todas as disciplinas</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="badge badge-warning" style="font-size:12px;">⏳ Pendente</span>
                <span style="font-size:12px;color:#64748b;">= Aluno ainda não marcou todas as disciplinas</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;color:#64748b;">
                    <strong>✅ Presente:</strong> Aluno tem pelo menos 1 P
                </span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;color:#64748b;">
                    <strong>⏳ Pendentes:</strong> Disciplinas sem marcação
                </span>
            </div>
        </div>
    </div>
    
    <?php endif; ?>

    <!-- ===== FOOTER ===== -->
    <?php include 'includes/footer.php'; ?>
</main>

<!-- ===== MODAL DE FILTROS PARA RELATÓRIO ===== -->
<div class="modal fade" id="modalFiltrosRelatorio" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white;">
                <h5 class="modal-title"><i class="fas fa-file-pdf"></i> Gerar Relatório Analítico de Frequência</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="fecharModalFiltros()" style="color:white; font-size:30px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formRelatorio">
                    <input type="hidden" name="turma_id" value="<?= $turma_selecionada ?>">
                    
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div class="form-group">
                            <label><i class="fas fa-user-graduate"></i> Aluno</label>
                            <select name="aluno" class="form-control">
                                <option value="">Todos os alunos</option>
                                <?php foreach ($todos_alunos_turma as $aluno): ?>
                                <option value="<?= $aluno['id'] ?>"><?= htmlspecialchars($aluno['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-filter"></i> Status</label>
                            <select name="status" class="form-control">
                                <option value="todos">Todos</option>
                                <option value="completo">✅ Completos</option>
                                <option value="pendente">⏳ Pendentes</option>
                                <option value="presente">✅ Presentes</option>
                                <option value="ausente">❌ Com Falta</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-book"></i> Disciplinas</label>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:8px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
                            <?php foreach ($disciplinas as $disciplina): ?>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:4px 10px;background:white;border-radius:6px;border:1px solid #e2e8f0;">
                                <input type="checkbox" name="disciplinas_rel[]" value="<?= $disciplina['id'] ?>" checked>
                                <span style="font-size:13px;"><?= htmlspecialchars($disciplina['nome']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-file-alt"></i> Tipo de Relatório</label>
                        <div style="display:flex;gap:15px;flex-wrap:wrap;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 15px;background:#ecfdf5;border-radius:6px;border:2px solid #22c55e;">
                                <input type="radio" name="tipo_relatorio" value="analitico" checked>
                                <span>📊 Analítico (Completo)</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 15px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">
                                <input type="radio" name="tipo_relatorio" value="resumido">
                                <span>📋 Resumido</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 15px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">
                                <input type="radio" name="tipo_relatorio" value="individual">
                                <span>👤 Individual</span>
                            </label>
                        </div>
                    </div>
                    
                    <div style="background:#f0fdf4;padding:12px;border-radius:8px;border:1px solid #bbf7d0;margin-top:10px;">
                        <p style="margin:0;color:#065f46;font-size:13px;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Dica:</strong> Selecione os filtros desejados e clique em "Gerar Relatório" para visualizar o relatório completo.
                            Você também pode exportar para Excel.
                        </p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalFiltros()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-success" onclick="gerarRelatorioComFiltros()" style="padding:10px 30px;font-weight:600;">
                    <i class="fas fa-file-pdf"></i> Gerar Relatório
                </button>
                <button type="button" class="btn btn-primary" onclick="gerarRelatorioComFiltros('excel')" style="padding:10px 25px;font-weight:600;">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL DE VISUALIZAÇÃO DO RELATÓRIO ===== -->
<div class="modal fade" id="modalRelatorio" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white;">
                <h5 class="modal-title"><i class="fas fa-file-pdf"></i> Relatório Analítico de Frequência</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="fecharModal()" style="color:white; font-size:30px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="conteudoRelatorio" style="max-height:80vh;overflow-y:auto;">
                <div style="text-align:center;padding:50px;">
                    <div style="font-size:60px;margin-bottom:20px;">📄</div>
                    <h3 style="color:#334155;">Aguarde...</h3>
                    <p style="color:#94a3b8;">O relatório está sendo gerado.</p>
                    <div style="display:inline-block;width:40px;height:40px;border:4px solid #e2e8f0;border-top:4px solid #2563eb;border-radius:50%;animation:spin 1s linear infinite;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">
                    <i class="fas fa-times"></i> Fechar
                </button>
                <button type="button" class="btn btn-primary" onclick="imprimirRelatorio()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button type="button" class="btn btn-success" onclick="exportarRelatorioExcel()">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== CHECKBOX LABELS ===== */
.checkbox-label {
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    cursor: pointer;
}

.checkbox-label:hover {
    background: #f1f5f9;
}

.checkbox-label.active-presente {
    background: #ecfdf5;
    border-color: #22c55e;
    border-radius: 6px;
}

.checkbox-label.active-falta {
    background: #fef2f2;
    border-color: #ef4444;
    border-radius: 6px;
}

.checkbox-label.active-justificado {
    background: #fffbeb;
    border-color: #f59e0b;
    border-radius: 6px;
}

.checkbox-label input[type="checkbox"] {
    accent-color: var(--primary);
    width: 14px;
    height: 14px;
    cursor: pointer;
}

/* ===== BOTÕES MARCAR TODOS ===== */
.btn-marcar-todos {
    transition: all 0.2s ease;
    min-width: 36px;
    min-height: 36px;
    border-radius: 6px !important;
    font-weight: 700;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-marcar-todos:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}

.btn-marcar-todos:active {
    transform: scale(0.95);
}

/* ===== BADGES ===== */
.badge {
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.badge-success { background: #ecfdf5; color: #065f46; }
.badge-danger { background: #fef2f2; color: #991b1b; }
.badge-warning { background: #fffbeb; color: #92400e; }
.badge-primary { background: var(--primary-light); color: var(--primary-dark); }
.badge .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-success .dot { background: #22c55e; }
.badge-danger .dot { background: #ef4444; }
.badge-warning .dot { background: #f59e0b; }
.badge-primary .dot { background: var(--primary); }

/* ===== MODAL ===== */
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.modal.show {
    display: block;
}

.modal-dialog {
    position: relative;
    width: auto;
    max-width: 1200px;
    margin: 30px auto;
}

.modal-content {
    position: relative;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header .close {
    font-size: 28px;
    font-weight: 700;
    color: #94a3b8;
    cursor: pointer;
    background: none;
    border: none;
}

.modal-header .close:hover {
    color: #475569;
}

.modal-body {
    padding: 20px;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 15px 20px;
    border-top: 1px solid #e2e8f0;
}

/* ===== FORMULÁRIO ===== */
.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    font-size: 14px;
    color: #334155;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

/* ===== ANIMAÇÃO ===== */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .checkbox-group {
        flex-direction: row !important;
        gap: 4px !important;
        flex-wrap: wrap;
    }
    .checkbox-label {
        padding: 2px 6px;
        font-size: 11px;
    }
    .checkbox-label input[type="checkbox"] {
        width: 12px;
        height: 12px;
    }
    .btn-marcar-todos {
        min-width: 30px;
        min-height: 30px;
        font-size: 11px !important;
        padding: 4px 8px !important;
    }
    .modal-dialog {
        margin: 10px;
    }
    .modal-body {
        max-height: 70vh;
    }
    #formRelatorio {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 480px) {
    .checkbox-label {
        padding: 2px 4px;
        font-size: 10px;
    }
    .checkbox-label input[type="checkbox"] {
        width: 10px;
        height: 10px;
    }
    .btn-marcar-todos {
        min-width: 26px;
        min-height: 26px;
        font-size: 10px !important;
        padding: 2px 6px !important;
    }
}
</style>

<!-- ===== SCRIPTS ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ===== MARCAR TODAS AS DISCIPLINAS DE UM ALUNO =====
    document.querySelectorAll('.btn-marcar-todos').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var alunoId = this.getAttribute('data-aluno');
            var status = this.getAttribute('data-status');
            
            var checkboxes = document.querySelectorAll('.presenca-checkbox[data-aluno="' + alunoId + '"]');
            
            checkboxes.forEach(function(cb) {
                cb.checked = false;
                var label = cb.closest('.checkbox-label');
                if (label) {
                    label.classList.remove('active-presente', 'active-falta', 'active-justificado');
                }
            });
            
            checkboxes.forEach(function(cb) {
                if (cb.value === status) {
                    cb.checked = true;
                    var label = cb.closest('.checkbox-label');
                    if (label) {
                        if (status === 'presente') {
                            label.classList.add('active-presente');
                        } else if (status === 'ausente') {
                            label.classList.add('active-falta');
                        } else if (status === 'justificado') {
                            label.classList.add('active-justificado');
                        }
                    }
                }
            });
            
            var btn = this;
            var originalText = btn.textContent;
            btn.textContent = '✓';
            btn.style.transform = 'scale(1.2)';
            setTimeout(function() {
                btn.textContent = originalText;
                btn.style.transform = 'scale(1)';
            }, 500);
        });
    });
    
    // ===== CHECKBOX: Garantir que apenas um seja marcado por disciplina/aluno =====
    document.querySelectorAll('.presenca-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var alunoId = this.getAttribute('data-aluno');
            var discId = this.getAttribute('data-disc');
            var grupo = document.querySelectorAll('.presenca-checkbox[data-aluno="' + alunoId + '"][data-disc="' + discId + '"]');
            
            if (this.checked) {
                grupo.forEach(function(cb) {
                    if (cb !== checkbox) {
                        cb.checked = false;
                        var label = cb.closest('.checkbox-label');
                        if (label) {
                            label.classList.remove('active-presente', 'active-falta', 'active-justificado');
                        }
                    }
                });
                
                var label = this.closest('.checkbox-label');
                if (label) {
                    label.classList.remove('active-presente', 'active-falta', 'active-justificado');
                    if (this.value === 'presente') {
                        label.classList.add('active-presente');
                    } else if (this.value === 'ausente') {
                        label.classList.add('active-falta');
                    } else if (this.value === 'justificado') {
                        label.classList.add('active-justificado');
                    }
                }
            } else {
                var label = this.closest('.checkbox-label');
                if (label) {
                    label.classList.remove('active-presente', 'active-falta', 'active-justificado');
                }
            }
        });
    });
    
    // ===== MARCAR TODOS OS ALUNOS =====
    window.marcarTodosAlunos = function(status) {
        var statusText = {
            'presente': 'Presentes',
            'ausente': 'Faltas',
            'justificado': 'Justificados'
        };
        
        if (!confirm('Deseja marcar todos os alunos como ' + statusText[status] + ' em todas as disciplinas?')) {
            return;
        }
        
        document.querySelectorAll('.presenca-checkbox').forEach(function(cb) {
            cb.checked = false;
            var label = cb.closest('.checkbox-label');
            if (label) {
                label.classList.remove('active-presente', 'active-falta', 'active-justificado');
            }
        });
        
        document.querySelectorAll('.presenca-checkbox[value="' + status + '"]').forEach(function(cb) {
            cb.checked = true;
            var label = cb.closest('.checkbox-label');
            if (label) {
                if (status === 'presente') {
                    label.classList.add('active-presente');
                } else if (status === 'ausente') {
                    label.classList.add('active-falta');
                } else if (status === 'justificado') {
                    label.classList.add('active-justificado');
                }
            }
        });
        
        document.getElementById('formFrequencia').submit();
    };
    
    // ===== LIMPAR TUDO =====
    window.limparTudo = function() {
        if (!confirm('Deseja limpar todas as marcações?')) {
            return;
        }
        
        document.querySelectorAll('.presenca-checkbox').forEach(function(cb) {
            cb.checked = false;
            var label = cb.closest('.checkbox-label');
            if (label) {
                label.classList.remove('active-presente', 'active-falta', 'active-justificado');
            }
        });
        
        document.getElementById('formFrequencia').submit();
    };
    
    // ===== ABRIR FILTROS DO RELATÓRIO =====
    window.abrirFiltrosRelatorio = function() {
        document.getElementById('modalFiltrosRelatorio').style.display = 'block';
        document.getElementById('modalFiltrosRelatorio').classList.add('show');
        document.body.style.overflow = 'hidden';
    };
    
    // ===== FECHAR MODAL FILTROS =====
    window.fecharModalFiltros = function() {
        document.getElementById('modalFiltrosRelatorio').style.display = 'none';
        document.getElementById('modalFiltrosRelatorio').classList.remove('show');
        document.body.style.overflow = 'auto';
    };
    
    // ===== FECHAR MODAL RELATÓRIO =====
    window.fecharModal = function() {
        document.getElementById('modalRelatorio').style.display = 'none';
        document.getElementById('modalRelatorio').classList.remove('show');
        document.body.style.overflow = 'auto';
    };
    
    // ===== GERAR RELATÓRIO COM FILTROS =====
    window.gerarRelatorioComFiltros = function(tipo) {
        var form = document.getElementById('formRelatorio');
        var formData = new FormData(form);
        
        var turmaId = formData.get('turma_id');
        var dataInicio = formData.get('data_inicio');
        var dataFim = formData.get('data_fim');
        var aluno = formData.get('aluno');
        var status = formData.get('status');
        var disciplinas = formData.getAll('disciplinas_rel[]');
        var tipoRelatorio = formData.get('tipo_relatorio');
        
        if (!turmaId) {
            alert('⚠️ Selecione uma turma primeiro!');
            return;
        }
        
        // Construir URL com parâmetros
        var url = 'gerar_relatorio.php?';
        url += 'turma=' + turmaId;
        url += '&data_inicio=' + dataInicio;
        url += '&data_fim=' + dataFim;
        if (aluno) url += '&aluno=' + aluno;
        if (status) url += '&status=' + status;
        if (disciplinas.length > 0) url += '&disciplinas=' + disciplinas.join(',');
        if (tipoRelatorio) url += '&tipo=' + tipoRelatorio;
        if (tipo === 'excel') url += '&formato=excel';
        
        // Fechar modal de filtros
        fecharModalFiltros();
        
        // Mostrar loading no modal do relatório
        document.getElementById('conteudoRelatorio').innerHTML = `
            <div style="text-align:center;padding:50px;">
                <div style="font-size:60px;margin-bottom:20px;">📄</div>
                <h3 style="color:#334155;">Gerando Relatório...</h3>
                <p style="color:#94a3b8;">Aguarde enquanto o relatório está sendo processado.</p>
                <div style="display:inline-block;width:40px;height:40px;border:4px solid #e2e8f0;border-top:4px solid #2563eb;border-radius:50%;animation:spin 1s linear infinite;"></div>
            </div>
        `;
        document.getElementById('modalRelatorio').style.display = 'block';
        document.getElementById('modalRelatorio').classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Se for Excel, abrir em nova janela
        if (tipo === 'excel') {
            window.open(url, '_blank');
            fecharModal();
            return;
        }
        
        // Carregar via AJAX para exibir no modal
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao gerar relatório: ' + response.status);
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('conteudoRelatorio').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('conteudoRelatorio').innerHTML = `
                    <div style="text-align:center;padding:50px;">
                        <div style="font-size:60px;margin-bottom:20px;">❌</div>
                        <h3 style="color:#ef4444;">Erro ao gerar relatório</h3>
                        <p style="color:#94a3b8;">${error.message}</p>
                        <button onclick="fecharModal()" class="btn btn-primary">Fechar</button>
                    </div>
                `;
            });
    };
    
    // ===== IMPRIMIR RELATÓRIO =====
    window.imprimirRelatorio = function() {
        var conteudo = document.getElementById('conteudoRelatorio').innerHTML;
        var win = window.open('', '_blank');
        win.document.write(`
            <html>
                <head>
                    <title>Relatório de Frequência</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { padding: 8px; border: 1px solid #ddd; }
                        th { background: #f1f5f9; }
                        .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 20px; }
                        .header h2 { color: #2563eb; margin: 0; }
                        .header p { color: #64748b; margin: 5px 0; }
                        .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; text-align: center; color: #94a3b8; font-size: 12px; }
                        @media print {
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    ${conteudo}
                    <script>
                        window.onload = function() { window.print(); window.close(); }
                    <\/script>
                </body>
            </html>
        `);
        win.document.close();
    };
    
    // ===== EXPORTAR RELATÓRIO EXCEL =====
    window.exportarRelatorioExcel = function() {
        var conteudo = document.getElementById('conteudoRelatorio').innerHTML;
        var html = `
            <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Relatório de Frequência</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { padding: 8px; border: 1px solid #ddd; }
                        th { background: #f1f5f9; }
                    </style>
                </head>
                <body>
                    ${conteudo}
                </body>
            </html>
        `;
        
        var blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'Relatorio_Frequencia_' + new Date().toISOString().slice(0,10) + '.xls';
        link.click();
    };
    
    console.log('📋 Sistema de Frequência carregado!');
    console.log('👨‍🏫 Professor: <?= htmlspecialchars($professor_nome) ?>');
    console.log('🆔 Nº Agente: <?= $professor_num_agente ?? 'N/A' ?>');
    console.log('🏫 Turmas: <?= count($turmas) ?>');
    console.log('📚 Disciplinas: <?= count($disciplinas) ?>');
});
</script>