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

// ==========================================
// FUNÇÕES DE NOTIFICAÇÃO
// ==========================================

function getUltimasNotificacoes($pdo, $professor_id, $limite = 5) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'notificacoes_professor'");
        if ($stmt->rowCount() == 0) {
            return [];
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM notificacoes_professor 
            WHERE professor_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bindParam(1, $professor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao buscar notificações: " . $e->getMessage());
        return [];
    }
}

function contarNotificacoesNaoLidas($pdo, $professor_id) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'notificacoes_professor'");
        if ($stmt->rowCount() == 0) {
            return 0;
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes_professor WHERE professor_id = ? AND lida = 0");
        $stmt->execute([$professor_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Erro ao contar notificações: " . $e->getMessage());
        return 0;
    }
}

// ==========================================
// DADOS DO USUÁRIO
// ==========================================
$nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$email = $_SESSION['usuario_email'] ?? '';
$page_title = 'Dashboard Pedagógico';
$active_page = 'dashboard';

// ==========================================
// BUSCAR DADOS DA EMPRESA
// ==========================================
$nome_escola = 'Sistema de Gestão Escolar';
$endereco_escola = '';
$telefone_escola = '';
$email_escola = '';

try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa) {
        $nome_escola = $empresa['razao_social'] ?? $empresa['nome_fantasia'] ?? 'Sistema de Gestão Escolar';
        $endereco_escola = $empresa['endereco'] ?? '';
        $telefone_escola = $empresa['telefone'] ?? '';
        $email_escola = $empresa['email'] ?? '';
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
// BUSCAR NOTIFICAÇÕES
// ==========================================
$notificacoes_nao_lidas = 0;
$ultimas_notificacoes = [];

if ($professor_id) {
    $notificacoes_nao_lidas = contarNotificacoesNaoLidas($pdo, $professor_id);
    $ultimas_notificacoes = getUltimasNotificacoes($pdo, $professor_id, 5);
}

// ==========================================
// BUSCAR DADOS DO PROFESSOR
// ==========================================
$total_turmas = 0;
$total_disciplinas = 0;
$total_alunos = 0;
$total_horarios = 0;
$turmas = [];
$disciplinas = [];

if ($professor_id) {
    try {
        // Turmas
        $stmt = $pdo->prepare("SELECT * FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR'");
        $stmt->execute([$professor_id]);
        $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_turmas = count($distribuicoes);
        $total_horarios = count($distribuicoes);
        
        foreach ($distribuicoes as $dist) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM alunos WHERE TURMA = ? AND status = 'ativo'");
            $stmt->execute([$dist['turma_nome']]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC);
            $turmas[] = [
                'nome' => $dist['turma_nome'],
                'classe' => $dist['classe'],
                'disciplinas' => $dist['disciplinas'],
                'alunos' => $total['total'] ?? 0
            ];
            $total_alunos += $total['total'] ?? 0;
        }
        
        // Disciplinas
        $stmt = $pdo->prepare("SELECT * FROM disciplinas WHERE professor_id = ? AND status = 'ativa'");
        $stmt->execute([$professor_id]);
        $disciplinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_disciplinas = count($disciplinas);
    } catch (Exception $e) {}
}

// ==========================================
// BUSCAR DADOS DE PRESENÇA
// ==========================================
$presencas_mes = 0;
$faltas_mes = 0;
$total_aulas = 0;
$mes_atual = date('m');
$ano_atual = date('Y');

if ($professor_id) {
    try {
        // Buscar turmas do professor pelo nome da turma
        $stmt = $pdo->prepare("
            SELECT DISTINCT turma_nome 
            FROM destribuicao_professores 
            WHERE professor_id = ? AND tipo = 'PROFESSOR'
        ");
        $stmt->execute([$professor_id]);
        $turmas_nomes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($turmas_nomes)) {
            $placeholders = implode(',', array_fill(0, count($turmas_nomes), '?'));
            
            // Buscar alunos das turmas
            $stmt = $pdo->prepare("
                SELECT id FROM alunos 
                WHERE TURMA IN ($placeholders) 
                AND status = 'ativo'
            ");
            $stmt->execute($turmas_nomes);
            $alunos_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($alunos_ids)) {
                $alunos_placeholders = implode(',', array_fill(0, count($alunos_ids), '?'));
                
                // Contar presenças do mês
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM frequencia 
                    WHERE aluno_id IN ($alunos_placeholders) 
                    AND MONTH(data) = ? AND YEAR(data) = ?
                    AND status = 'presente'
                ");
                $params = array_merge($alunos_ids, [$mes_atual, $ano_atual]);
                $stmt->execute($params);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $presencas_mes = $result['total'] ?? 0;
                
                // Contar faltas do mês
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM frequencia 
                    WHERE aluno_id IN ($alunos_placeholders) 
                    AND MONTH(data) = ? AND YEAR(data) = ?
                    AND status = 'ausente'
                ");
                $params = array_merge($alunos_ids, [$mes_atual, $ano_atual]);
                $stmt->execute($params);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $faltas_mes = $result['total'] ?? 0;
                
                // Total de aulas do mês (presenças + faltas + justificados)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM frequencia 
                    WHERE aluno_id IN ($alunos_placeholders) 
                    AND MONTH(data) = ? AND YEAR(data) = ?
                ");
                $params = array_merge($alunos_ids, [$mes_atual, $ano_atual]);
                $stmt->execute($params);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $total_aulas = $result['total'] ?? 0;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar presenças: " . $e->getMessage());
    }
}

date_default_timezone_set('Africa/Luanda');
$data_atual = date('d/m/Y H:i:s');

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
                <h2>📊 Dashboard Pedagógico</h2>
                <p>Visão geral das suas atividades</p>
            </div>
        </div>
        <div class="topbar-right">
            <div class="date-time">
                <div class="time"><?= date('H:i:s') ?></div>
                <div><?= date('d/m/Y') ?></div>
            </div>
            <div class="status-indicator">
                <span class="dot"></span> Online
            </div>
        </div>
    </div>

    <!-- ===== WELCOME ===== -->
    <div class="card animate-fade-up delay-1" style="background:var(--secondary);color:white;border:none;">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            <div style="width:64px;height:64px;background:var(--primary-gradient);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:var(--secondary);flex-shrink:0;">
                <?= strtoupper(substr($nome, 0, 2)) ?>
            </div>
            <div>
                <h2 style="font-size:22px;font-weight:700;">👋 Bem-vindo, <?= htmlspecialchars($nome) ?></h2>
                <div style="color:var(--text-light);font-size:14px;">📚 Professor • <?= ucfirst($perfil) ?></div>
                <div style="color:var(--primary);font-size:13px;margin-top:4px;">
                    <i class="far fa-calendar-alt"></i> <?= $data_atual ?>
                </div>
            </div>
            <div style="display:flex;gap:20px;margin-left:auto;flex-wrap:wrap;">
                <div style="text-align:center;padding:8px 16px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
                    <div style="font-size:20px;font-weight:700;color:var(--primary);"><?= $total_turmas ?></div>
                    <div style="font-size:10px;color:var(--text-light);text-transform:uppercase;">Turmas</div>
                </div>
                <div style="text-align:center;padding:8px 16px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
                    <div style="font-size:20px;font-weight:700;color:var(--primary);"><?= $total_alunos ?></div>
                    <div style="font-size:10px;color:var(--text-light);text-transform:uppercase;">Alunos</div>
                </div>
                <div style="text-align:center;padding:8px 16px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
                    <div style="font-size:20px;font-weight:700;color:var(--primary);"><?= $total_disciplinas ?></div>
                    <div style="font-size:10px;color:var(--text-light);text-transform:uppercase;">Disciplinas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== NOTIFICAÇÕES RECENTES ===== -->
    <?php if ($professor_id): ?>
        <?php 
        $notificacoes_recentes = getUltimasNotificacoes($pdo, $professor_id, 5);
        $total_nao_lidas = contarNotificacoesNaoLidas($pdo, $professor_id);
        ?>
        
        <?php if ($total_nao_lidas > 0): ?>
        <div class="card animate-fade-up delay-2" style="background: #fff3cd; border: 1px solid #ffeaa7; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <i class="fas fa-bell" style="color: #f39c12; font-size: 18px;"></i>
                    <strong style="color: #856404;"><?= $total_nao_lidas ?> notificação(ões) não lida(s)</strong>
                    <span style="color: #856404; font-size: 13px; margin-left: 10px;">Clique no sino para visualizar</span>
                </div>
                <button onclick="marcarTodasLidas()" style="padding: 6px 16px; background: #f39c12; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px;">
                    Marcar todas como lidas
                </button>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ===== BOTÃO PARA GERAR NOTIFICAÇÕES DE TESTE ===== -->
    <?php if ($professor_id && ($_SESSION['usuario_perfil'] == 'professor' || $_SESSION['usuario_perfil'] == 'docente')): ?>
    <div class="card animate-fade-up delay-2" style="background: #f0f4ff; border: 1px dashed var(--primary); margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; flex-wrap: wrap; gap: 10px;">
            <div>
                <i class="fas fa-flask" style="color: var(--primary); font-size: 18px;"></i>
                <strong style="color: var(--text-primary);">🧪 Teste de Notificações</strong>
                <span style="color: var(--text-secondary); font-size: 13px; margin-left: 10px;">Gerar notificações de teste</span>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button onclick="criarNotificacaoTeste(1)" style="padding: 6px 16px; background: var(--primary-gradient); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px;">
                    +1
                </button>
                <button onclick="criarNotificacaoTeste(3)" style="padding: 6px 16px; background: var(--primary-gradient); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px;">
                    +3
                </button>
                <button onclick="criarNotificacaoTeste(5)" style="padding: 6px 16px; background: var(--primary-gradient); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px;">
                    +5
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== STATS ===== -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:20px;">
        <div class="card animate-fade-up delay-2">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:38px;height:38px;background:linear-gradient(135deg,#4f46e5,#818cf8);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div style="font-size:12px;color:var(--text-secondary);font-weight:500;margin-top:6px;">Total Alunos</div>
            <div style="font-size:26px;font-weight:800;color:var(--text-primary);letter-spacing:-1px;"><?= $total_alunos ?></div>
        </div>
        <div class="card animate-fade-up delay-3">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:38px;height:38px;background:linear-gradient(135deg,#06b6d4,#67e8f9);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
            <div style="font-size:12px;color:var(--text-secondary);font-weight:500;margin-top:6px;">Total Registros (Mês)</div>
            <div style="font-size:26px;font-weight:800;color:var(--text-primary);letter-spacing:-1px;"><?= $total_aulas ?></div>
        </div>
        <div class="card animate-fade-up delay-4">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:38px;height:38px;background:linear-gradient(135deg,#22c55e,#86efac);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
            <div style="font-size:12px;color:var(--text-secondary);font-weight:500;margin-top:6px;">Presenças (Mês)</div>
            <div style="font-size:26px;font-weight:800;color:var(--text-primary);letter-spacing:-1px;"><?= $presencas_mes ?></div>
        </div>
        <div class="card animate-fade-up delay-5">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:38px;height:38px;background:linear-gradient(135deg,#ef4444,#fca5a5);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;">
                    <i class="fas fa-user-times"></i>
                </div>
            </div>
            <div style="font-size:12px;color:var(--text-secondary);font-weight:500;margin-top:6px;">Faltas (Mês)</div>
            <div style="font-size:26px;font-weight:800;color:var(--text-primary);letter-spacing:-1px;"><?= $faltas_mes ?></div>
        </div>
    </div>

    <!-- ===== GRÁFICO DE PRESENÇA ===== -->
    <?php if ($total_aulas > 0): ?>
    <div class="card animate-fade-up delay-3">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Resumo de Presenças - <?= date('F/Y') ?></h3>
            <span class="badge-count"><?= date('d/m/Y') ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:30px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <div style="display:flex;flex-direction:column;gap:15px;">
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-secondary);margin-bottom:4px;">
                            <span><i class="fas fa-user-check" style="color:#22c55e;"></i> Presenças</span>
                            <span><strong><?= $presencas_mes ?></strong> (<?= $total_aulas > 0 ? round(($presencas_mes / $total_aulas) * 100, 1) : 0 ?>%)</span>
                        </div>
                        <div style="width:100%;height:10px;background:#e5e7eb;border-radius:10px;overflow:hidden;">
                            <div style="height:100%;width:<?= $total_aulas > 0 ? round(($presencas_mes / $total_aulas) * 100, 1) : 0 ?>%;background:linear-gradient(90deg,#22c55e,#86efac);border-radius:10px;transition:width 0.5s;"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-secondary);margin-bottom:4px;">
                            <span><i class="fas fa-user-times" style="color:#ef4444;"></i> Faltas</span>
                            <span><strong><?= $faltas_mes ?></strong> (<?= $total_aulas > 0 ? round(($faltas_mes / $total_aulas) * 100, 1) : 0 ?>%)</span>
                        </div>
                        <div style="width:100%;height:10px;background:#e5e7eb;border-radius:10px;overflow:hidden;">
                            <div style="height:100%;width:<?= $total_aulas > 0 ? round(($faltas_mes / $total_aulas) * 100, 1) : 0 ?>%;background:linear-gradient(90deg,#ef4444,#fca5a5);border-radius:10px;transition:width 0.5s;"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-secondary);margin-bottom:4px;">
                            <span><i class="fas fa-calendar-check" style="color:#06b6d4;"></i> Total Registros</span>
                            <span><strong><?= $total_aulas ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;padding:15px;background:#f8fafc;border-radius:10px;min-width:150px;">
                <div style="text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:var(--text-primary);"><?= $total_aulas > 0 ? round(($presencas_mes / $total_aulas) * 100, 1) : 0 ?>%</div>
                    <div style="font-size:12px;color:var(--text-secondary);">Taxa de Presença</div>
                </div>
                <div style="text-align:center;padding-top:10px;border-top:1px solid #e2e8f0;">
                    <div style="font-size:12px;color:var(--text-secondary);">
                        <span style="color:#22c55e;"><?= $presencas_mes ?></span> presentes | 
                        <span style="color:#ef4444;"><?= $faltas_mes ?></span> faltas
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== TURMAS E DISCIPLINAS ===== -->
    <div class="grid-2">
        <div class="card animate-fade-up delay-3">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Minhas Turmas</h3>
                <span class="badge-count"><?= $total_turmas ?></span>
            </div>
            <?php if (!empty($turmas)): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Turma</th>
                            <th>Classe</th>
                            <th>Alunos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($turmas as $turma): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($turma['nome']) ?></strong></td>
                            <td><?= htmlspecialchars($turma['classe']) ?></td>
                            <td><?= $turma['alunos'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <h4>Nenhuma turma atribuída</h4>
                <p>Você ainda não possui turmas vinculadas.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="card animate-fade-up delay-4">
            <div class="card-header">
                <h3><i class="fas fa-book"></i> Minhas Disciplinas</h3>
                <span class="badge-count"><?= $total_disciplinas ?></span>
            </div>
            <?php if (!empty($disciplinas)): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Disciplina</th>
                            <th>Carga Horária</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disciplinas as $disciplina): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($disciplina['nome']) ?></strong></td>
                            <td><?= htmlspecialchars($disciplina['carga_horaria'] ?? '-') ?>h</td>
                            <td>
                                <span class="status-badge <?= ($disciplina['status'] ?? 'ativa') == 'ativa' ? 'ativa' : 'inativa' ?>">
                                    <span class="dot"></span> <?= ucfirst($disciplina['status'] ?? 'Ativa') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <h4>Nenhuma disciplina atribuída</h4>
                <p>Você ainda não possui disciplinas.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== TOAST NOTIFICATIONS SCRIPT ===== -->
    <?php if ($professor_id && $notificacoes_nao_lidas > 0): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php 
        $count = 0;
        foreach ($ultimas_notificacoes as $notif): 
            if ($count >= 3) break;
            if (!$notif['lida']):
                $count++;
        ?>
        setTimeout(function() {
            showToast(
                '<?= $notif['icone'] ?? '📢' ?>',
                '<?= addslashes($notif['titulo']) ?>',
                '<?= addslashes(substr($notif['mensagem'], 0, 60)) ?>...',
                '<?= $notif['cor'] ?? 'info' ?>',
                5000
            );
        }, <?= $count * 1500 ?>);
        <?php endif; endforeach; ?>
    });
    </script>
    <?php endif; ?>

    <!-- ===== FOOTER ===== -->
    <?php include 'includes/footer.php'; ?>
</main>