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
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa) {
        $nome_escola = $empresa['razao_social'] ?? $empresa['nome_fantasia'] ?? 'Sistema de Gestão Escolar';
    }
} catch (Exception $e) {}

// ==========================================
// BUSCAR PROFESSOR
// ==========================================
$professor_id = null;
try {
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE email = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$email]);
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($professor) $professor_id = $professor['id'];
    }
} catch (Exception $e) {}

// ==========================================
// BUSCAR TURMAS
// ==========================================
$turmas = [];
$total_turmas = 0;
if ($professor_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR' ORDER BY turma_nome");
        $stmt->execute([$professor_id]);
        $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($distribuicoes as $dist) {
            $turmas[] = [
                'id' => $dist['turma_id'],
                'nome' => $dist['turma_nome'],
                'classe' => $dist['classe'],
                'disciplinas' => $dist['disciplinas']
            ];
        }
        $total_turmas = count($turmas);
    } catch (Exception $e) {}
}

// ==========================================
// BUSCAR DISCIPLINAS
// ==========================================
$disciplinas = [];
$total_disciplinas = 0;
if ($professor_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM disciplinas WHERE professor_id = ? AND status = 'ativa' ORDER BY nome");
        $stmt->execute([$professor_id]);
        $disciplinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_disciplinas = count($disciplinas);
    } catch (Exception $e) {}
}

// ==========================================
// BUSCAR ALUNOS DA TURMA SELECIONADA
// ==========================================
$alunos = [];
$turma_selecionada = $_GET['turma'] ?? null;
$data_selecionada = $_GET['data'] ?? date('Y-m-d');

// CORREÇÃO: Verificar se é array ou string
if (isset($_GET['disciplinas'])) {
    if (is_array($_GET['disciplinas'])) {
        $disciplinas_selecionadas = $_GET['disciplinas'];
    } else {
        $disciplinas_selecionadas = explode(',', $_GET['disciplinas']);
    }
} else {
    $disciplinas_selecionadas = [];
}

// Garantir que é um array de inteiros
$disciplinas_selecionadas = array_map('intval', $disciplinas_selecionadas);

if ($turma_selecionada) {
    try {
        // Buscar nome da turma
        $stmt = $pdo->prepare("SELECT turma_nome, classe FROM destribuicao_professores WHERE turma_id = ? LIMIT 1");
        $stmt->execute([$turma_selecionada]);
        $turma_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($turma_info) {
            $turma_nome = $turma_info['turma_nome'];
            $turma_classe = $turma_info['classe'];
            
            // Buscar alunos
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
        
        // Para cada aluno com presença marcada
        foreach ($presencas as $aluno_id => $disciplinas_aluno) {
            // Se for array (várias disciplinas)
            if (is_array($disciplinas_aluno)) {
                foreach ($disciplinas_aluno as $disciplina_id => $status) {
                    if (empty($status)) continue;
                    
                    // Verificar se já existe registro
                    $stmt = $pdo->prepare("
                        SELECT id FROM frequencia 
                        WHERE aluno_id = ? AND turma_id = ? AND disciplina_id = ? AND data = ? 
                        LIMIT 1
                    ");
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
            } else {
                // Disciplina única (formato antigo)
                $disciplina_id = key($disciplinas_aluno);
                $status = current($disciplinas_aluno);
                
                // Verificar se já existe registro
                $stmt = $pdo->prepare("
                    SELECT id FROM frequencia 
                    WHERE aluno_id = ? AND turma_id = ? AND disciplina_id = ? AND data = ? 
                    LIMIT 1
                ");
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
        
        $mensagem = "✅ Frequência salva com sucesso! ($total_registros registros)";
        $tipo_mensagem = "success";
        
        // Recarregar
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
            SELECT aluno_id, disciplina_id, status 
            FROM frequencia 
            WHERE disciplina_id IN ($placeholders) 
            AND turma_id = ? 
            AND data = ?
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

// Se veio com success
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $mensagem = "✅ Frequência salva com sucesso!";
    $tipo_mensagem = "success";
}

date_default_timezone_set('Africa/Luanda');
$hora_atual = date('H:i:s');
$data_br = date('d/m/Y');

// ==========================================
// CALCULAR ESTATÍSTICAS
// ==========================================
$total_presentes = 0;
$total_ausentes = 0;
$total_justificados = 0;
$total_pendentes = 0;
$total_registros = 0;

if (!empty($alunos) && !empty($disciplinas_selecionadas)) {
    $total_registros = count($alunos) * count($disciplinas_selecionadas);
    foreach ($alunos as $aluno) {
        foreach ($disciplinas_selecionadas as $disc_id) {
            $key = $aluno['id'] . '_' . $disc_id;
            $status = $frequencia_salva[$key] ?? 'pendente';
            if ($status == 'presente') $total_presentes++;
            elseif ($status == 'ausente') $total_ausentes++;
            elseif ($status == 'justificado') $total_justificados++;
            else $total_pendentes++;
        }
    }
}

$percentual = $total_registros > 0 ? round((($total_presentes + $total_ausentes + $total_justificados) / $total_registros) * 100) : 0;

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
                </div>
                
                <!-- ===== DISCIPLINAS CHECKBOXES ===== -->
                <div class="form-group">
                    <label><i class="fas fa-book"></i> Disciplinas</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; padding: 8px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
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
                            
                            <!-- Coluna para marcar todos de uma vez -->
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

    <!-- ===== RESUMO ===== -->
    <?php if ($turma_selecionada && !empty($disciplinas_selecionadas) && !empty($alunos)): ?>
    <div class="card animate-fade-up delay-3">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Resumo da Frequência</h3>
            <span class="badge badge-primary">
                <span class="dot"></span> <?= date('d/m/Y', strtotime($data_selecionada)) ?>
            </span>
        </div>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:10px;">
            <div style="text-align:center;padding:10px;background:#ecfdf5;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#22c55e;" id="total-presentes"><?= $total_presentes ?></div>
                <div style="font-size:11px;color:#065f46;font-weight:500;">Presentes</div>
            </div>
            <div style="text-align:center;padding:10px;background:#fef2f2;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#ef4444;" id="total-faltas"><?= $total_ausentes ?></div>
                <div style="font-size:11px;color:#991b1b;font-weight:500;">Faltas</div>
            </div>
            <div style="text-align:center;padding:10px;background:#fffbeb;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#f59e0b;" id="total-justificados"><?= $total_justificados ?></div>
                <div style="font-size:11px;color:#92400e;font-weight:500;">Justificados</div>
            </div>
            <div style="text-align:center;padding:10px;background:#f1f5f9;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#94a3b8;" id="total-pendentes"><?= $total_pendentes ?></div>
                <div style="font-size:11px;color:#64748b;font-weight:500;">Pendentes</div>
            </div>
        </div>
        
        <div style="margin-top:15px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);margin-bottom:4px;">
                <span>Progresso</span>
                <span><?= $percentual ?>% (<?= $total_presentes + $total_ausentes + $total_justificados ?>/<?= $total_registros ?> marcados)</span>
            </div>
            <div style="width:100%;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                <div style="width:<?= $percentual ?>%;height:100%;background:var(--primary-gradient);border-radius:4px;transition:width 0.5s ease;"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== FOOTER ===== -->
    <?php include 'includes/footer.php'; ?>
</main>

<!-- ===== SCRIPTS ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ===== MARCAR TODAS AS DISCIPLINAS DE UM ALUNO =====
    document.querySelectorAll('.btn-marcar-todos').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var alunoId = this.getAttribute('data-aluno');
            var status = this.getAttribute('data-status');
            
            console.log('Marcando aluno ' + alunoId + ' como ' + status);
            
            // Encontrar todos os checkboxes deste aluno
            var checkboxes = document.querySelectorAll('.presenca-checkbox[data-aluno="' + alunoId + '"]');
            
            console.log('Checkboxes encontrados: ' + checkboxes.length);
            
            // Primeiro desmarcar todos
            checkboxes.forEach(function(cb) {
                cb.checked = false;
                var label = cb.closest('.checkbox-label');
                if (label) {
                    label.classList.remove('active-presente', 'active-falta', 'active-justificado');
                }
            });
            
            // Marcar apenas os checkboxes com o status selecionado
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
            
            atualizarResumo();
            
            // Feedback visual no botão
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
            
            // Se este checkbox foi marcado
            if (this.checked) {
                // Desmarcar os outros do mesmo grupo
                grupo.forEach(function(cb) {
                    if (cb !== checkbox) {
                        cb.checked = false;
                        var label = cb.closest('.checkbox-label');
                        if (label) {
                            label.classList.remove('active-presente', 'active-falta', 'active-justificado');
                        }
                    }
                });
                
                // Adicionar classe active no label deste
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
                // Se desmarcou, remover classe active
                var label = this.closest('.checkbox-label');
                if (label) {
                    label.classList.remove('active-presente', 'active-falta', 'active-justificado');
                }
            }
            
            atualizarResumo();
        });
    });
    
    // ===== ATUALIZAR RESUMO =====
    function atualizarResumo() {
        var presentes = 0;
        var faltas = 0;
        var justificados = 0;
        var pendentes = 0;
        
        document.querySelectorAll('.presenca-checkbox').forEach(function(cb) {
            if (cb.checked) {
                if (cb.value === 'presente') presentes++;
                else if (cb.value === 'ausente') faltas++;
                else if (cb.value === 'justificado') justificados++;
            } else {
                pendentes++;
            }
        });
        
        var total = document.querySelectorAll('.presenca-checkbox').length;
        
        var elPresentes = document.getElementById('total-presentes');
        var elFaltas = document.getElementById('total-faltas');
        var elJustificados = document.getElementById('total-justificados');
        var elPendentes = document.getElementById('total-pendentes');
        
        if (elPresentes) elPresentes.textContent = presentes;
        if (elFaltas) elFaltas.textContent = faltas;
        if (elJustificados) elJustificados.textContent = justificados;
        if (elPendentes) elPendentes.textContent = pendentes;
        
        // Atualizar barra de progresso
        var marcados = presentes + faltas + justificados;
        var percentual = total > 0 ? Math.round((marcados / total) * 100) : 0;
        
        var barra = document.querySelector('.card:last-child .progress-bar');
        if (barra) {
            barra.style.width = percentual + '%';
        }
    }
    
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
        
        // Desmarcar todos
        document.querySelectorAll('.presenca-checkbox').forEach(function(cb) {
            cb.checked = false;
            var label = cb.closest('.checkbox-label');
            if (label) {
                label.classList.remove('active-presente', 'active-falta', 'active-justificado');
            }
        });
        
        // Marcar com o status selecionado
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
        
        atualizarResumo();
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
        atualizarResumo();
    };
    
    console.log('📋 Sistema de Frequência com Marcação Unificada carregado!');
});
</script>

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