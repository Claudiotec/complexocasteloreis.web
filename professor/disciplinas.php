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
$page_title = 'Gerenciamento de Disciplinas';
$active_page = 'disciplinas';

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
// BUSCAR DISCIPLINAS
// ==========================================
$disciplinas = [];
$total_disciplinas = 0;
$disciplinas_ativas = 0;
$disciplinas_inativas = 0;
$carga_total = 0;

try {
    // Buscar todas as disciplinas (sem professor_id)
    $stmt = $pdo->query("SELECT * FROM disciplinas ORDER BY nome");
    $disciplinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_disciplinas = count($disciplinas);
    
    // Estatísticas
    foreach ($disciplinas as $d) {
        if (($d['status'] ?? 'ativa') == 'ativa') {
            $disciplinas_ativas++;
        } else {
            $disciplinas_inativas++;
        }
        $carga_total += $d['carga_horaria'] ?? 0;
    }
} catch (Exception $e) {}

// ==========================================
// DADOS POR STATUS
// ==========================================
$status_data = [];
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as total FROM disciplinas GROUP BY status");
    $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ==========================================
// DADOS POR CARGA HORÁRIA
// ==========================================
$carga_faixas = [];
try {
    $stmt = $pdo->query("SELECT 
                         CASE 
                             WHEN carga_horaria <= 20 THEN '0-20h'
                             WHEN carga_horaria <= 40 THEN '21-40h'
                             WHEN carga_horaria <= 60 THEN '41-60h'
                             ELSE '60h+'
                         END as faixa,
                         COUNT(*) as total
                         FROM disciplinas
                         GROUP BY faixa
                         ORDER BY faixa");
    $carga_faixas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ==========================================
// ÚLTIMAS DISCIPLINAS
// ==========================================
$ultimas_disciplinas = [];
try {
    $stmt = $pdo->query("SELECT * FROM disciplinas ORDER BY created_at DESC LIMIT 5");
    $ultimas_disciplinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

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
                <h2>📚 Gerenciamento de Disciplinas</h2>
                <p>Visualize todas as disciplinas cadastradas no sistema</p>
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
                <h2 style="font-size:22px;font-weight:700;">📊 Painel de Disciplinas</h2>
                <div style="color:var(--text-light);font-size:14px;">📚 Total de <?= $total_disciplinas ?> disciplinas cadastradas</div>
                <div style="color:var(--primary);font-size:13px;margin-top:4px;">
                    <i class="far fa-calendar-alt"></i> <?= $data_atual ?>
                </div>
            </div>
            <div style="display:flex;gap:20px;margin-left:auto;flex-wrap:wrap;">
                <div style="text-align:center;padding:8px 16px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
                    <div style="font-size:20px;font-weight:700;color:#2ecc71;"><?= $disciplinas_ativas ?></div>
                    <div style="font-size:10px;color:var(--text-light);text-transform:uppercase;">Ativas</div>
                </div>
                <div style="text-align:center;padding:8px 16px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
                    <div style="font-size:20px;font-weight:700;color:#e74c3c;"><?= $disciplinas_inativas ?></div>
                    <div style="font-size:10px;color:var(--text-light);text-transform:uppercase;">Inativas</div>
                </div>
                <div style="text-align:center;padding:8px 16px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
                    <div style="font-size:20px;font-weight:700;color:#f39c12;"><?= $carga_total ?>h</div>
                    <div style="font-size:10px;color:var(--text-light);text-transform:uppercase;">Carga Total</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== STATS ===== -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:20px;">
        <div class="card animate-fade-up delay-2">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:38px;height:38px;background:linear-gradient(135deg,#4f46e5,#818cf8);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;">
                    <i class="fas fa-book"></i>
                </div>
            </div>
            <div style="font-size:12px;color:var(--text-secondary);font-weight:500;margin-top:6px;">Total Disciplinas</div>
            <div style="font-size:26px;font-weight:800;color:var(--text-primary);letter-spacing:-1px;"><?= $total_disciplinas ?></div>
        </div>
        <div class="card animate-fade-up delay-3">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:38px;height:38px;background:linear-gradient(135deg,#2ecc71,#82e0aa);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div style="font-size:12px;color:var(--text-secondary);font-weight:500;margin-top:6px;">Disciplinas Ativas</div>
            <div style="font-size:26px;font-weight:800;color:var(--text-primary);letter-spacing:-1px;"><?= $disciplinas_ativas ?></div>
        </div>
        <div class="card animate-fade-up delay-4">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:38px;height:38px;background:linear-gradient(135deg,#e74c3c,#f1948a);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
            <div style="font-size:12px;color:var(--text-secondary);font-weight:500;margin-top:6px;">Disciplinas Inativas</div>
            <div style="font-size:26px;font-weight:800;color:var(--text-primary);letter-spacing:-1px;"><?= $disciplinas_inativas ?></div>
        </div>
        <div class="card animate-fade-up delay-5">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:38px;height:38px;background:linear-gradient(135deg,#f59e0b,#fbbf24);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div style="font-size:12px;color:var(--text-secondary);font-weight:500;margin-top:6px;">Carga Horária Total</div>
            <div style="font-size:26px;font-weight:800;color:var(--text-primary);letter-spacing:-1px;"><?= $carga_total ?>h</div>
        </div>
    </div>

    <!-- ===== TABS ===== -->
    <div class="card animate-fade-up delay-3">
        <div style="display:flex;gap:5px;border-bottom:2px solid var(--border-color);margin-bottom:20px;flex-wrap:wrap;padding-bottom:10px;">
            <button class="tab-btn active" data-tab="lista" style="padding:10px 20px;background:transparent;border:none;border-radius:8px 8px 0 0;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all 0.3s;font-size:14px;">
                📋 Lista Completa <span class="badge" style="background:#e74c3c;color:white;border-radius:50%;padding:2px 8px;font-size:12px;margin-left:6px;"><?= $total_disciplinas ?></span>
            </button>
            <button class="tab-btn" data-tab="status" style="padding:10px 20px;background:transparent;border:none;border-radius:8px 8px 0 0;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all 0.3s;font-size:14px;">
                📊 Por Status
            </button>
            <button class="tab-btn" data-tab="carga" style="padding:10px 20px;background:transparent;border:none;border-radius:8px 8px 0 0;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all 0.3s;font-size:14px;">
                ⏱️ Carga Horária
            </button>
            <button class="tab-btn" data-tab="faixa" style="padding:10px 20px;background:transparent;border:none;border-radius:8px 8px 0 0;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all 0.3s;font-size:14px;">
                📈 Faixas de Carga
            </button>
            <button class="tab-btn" data-tab="ativas" style="padding:10px 20px;background:transparent;border:none;border-radius:8px 8px 0 0;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all 0.3s;font-size:14px;">
                ✅ Ativas <span class="badge" style="background:#2ecc71;color:white;border-radius:50%;padding:2px 8px;font-size:12px;margin-left:6px;"><?= $disciplinas_ativas ?></span>
            </button>
            <button class="tab-btn" data-tab="ultimas" style="padding:10px 20px;background:transparent;border:none;border-radius:8px 8px 0 0;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all 0.3s;font-size:14px;">
                🆕 Últimas Cadastradas
            </button>
        </div>

        <!-- ===== TOOLS ===== -->
        <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
            <input type="text" id="searchInput" placeholder="🔍 Buscar disciplina..." style="flex:1;min-width:200px;padding:8px 15px;border:2px solid var(--border-color);border-radius:6px;font-size:14px;background:var(--input-bg);color:var(--text-primary);transition:border-color 0.3s;">
            <button onclick="filtrarTabela()" class="btn btn-primary" style="padding:8px 20px;background:var(--primary-gradient);color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.3s;">🔍 Filtrar</button>
            <button onclick="limparFiltro()" class="btn btn-danger" style="padding:8px 20px;background:#e74c3c;color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.3s;">❌ Limpar</button>
            <button onclick="exportarExcel()" class="btn btn-success" style="padding:8px 20px;background:#2ecc71;color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all 0.3s;">📊 Exportar</button>
            <span style="margin-left:auto;color:var(--text-secondary);font-size:13px;">
                <?= $total_disciplinas ?> disciplinas encontradas
            </span>
        </div>

        <!-- ===== TAB 1: LISTA COMPLETA ===== -->
        <div class="tab-content active" id="tab-lista">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Carga Horária</th>
                            <th>Status</th>
                            <th>Cadastro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($disciplinas)): ?>
                        <tr>
                            <td colspan="6" class="empty-state" style="text-align:center;padding:40px;color:var(--text-secondary);">
                                <div class="icon" style="font-size:48px;display:block;margin-bottom:10px;">📚</div>
                                <h4>Nenhuma disciplina cadastrada</h4>
                                <p>Clique em "Nova Disciplina" para adicionar.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($disciplinas as $d): ?>
                        <tr>
                            <td><?= $d['id'] ?></td>
                            <td><strong><?= htmlspecialchars($d['nome']) ?></strong></td>
                            <td><?= htmlspecialchars(substr($d['descricao'] ?? '', 0, 40)) ?><?= strlen($d['descricao'] ?? '') > 40 ? '...' : '' ?></td>
                            <td><?= $d['carga_horaria'] ?>h</td>
                            <td>
                                <span class="status-badge <?= (($d['status'] ?? 'ativa') == 'ativa') ? 'ativa' : 'inativa' ?>">
                                    <span class="dot"></span> <?= ucfirst($d['status'] ?? 'Ativa') ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== TAB 2: POR STATUS ===== -->
        <div class="tab-content" id="tab-status">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Percentual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($status_data)): ?>
                        <tr>
                            <td colspan="3" class="empty-state" style="text-align:center;padding:40px;color:var(--text-secondary);">
                                <div class="icon" style="font-size:48px;display:block;margin-bottom:10px;">📊</div>
                                <h4>Nenhum status encontrado</h4>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php 
                        $total_geral = array_sum(array_column($status_data, 'total'));
                        $cores = ['ativa' => '#2ecc71', 'inativa' => '#e74c3c', 'pendente' => '#f39c12'];
                        foreach ($status_data as $item): 
                            $percentual = $total_geral > 0 ? round(($item['total'] / $total_geral) * 100, 1) : 0;
                            $status = strtolower($item['status']);
                        ?>
                        <tr>
                            <td>
                                <span class="status-badge <?= $status == 'ativa' ? 'ativa' : ($status == 'inativa' ? 'inativa' : 'pendente') ?>">
                                    <span class="dot"></span> <?= ucfirst($item['status']) ?>
                                </span>
                            </td>
                            <td><strong><?= $item['total'] ?></strong></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="flex:1;height:20px;background:var(--border-color);border-radius:10px;overflow:hidden;">
                                        <div style="height:100%;width:<?= $percentual ?>%;background:<?= $cores[$status] ?? '#3498db' ?>;border-radius:10px;transition:width 0.5s;"></div>
                                    </div>
                                    <span><?= $percentual ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== TAB 3: CARGA HORÁRIA ===== -->
        <div class="tab-content" id="tab-carga">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Disciplina</th>
                            <th>Carga Horária</th>
                            <th>Status</th>
                            <th>Cadastro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($disciplinas)): ?>
                        <tr>
                            <td colspan="4" class="empty-state" style="text-align:center;padding:40px;color:var(--text-secondary);">
                                <div class="icon" style="font-size:48px;display:block;margin-bottom:10px;">⏱️</div>
                                <h4>Nenhuma disciplina com carga horária</h4>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php 
                        $max_horas = max(array_column($disciplinas, 'carga_horaria'));
                        $total_horas = array_sum(array_column($disciplinas, 'carga_horaria'));
                        foreach ($disciplinas as $item): 
                            $percentual_carga = $max_horas > 0 ? round(($item['carga_horaria'] / $max_horas) * 100) : 0;
                            $cor_carga = $percentual_carga >= 80 ? '#e74c3c' : ($percentual_carga >= 50 ? '#f39c12' : '#2ecc71');
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['nome']) ?></strong></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span><strong><?= $item['carga_horaria'] ?>h</strong></span>
                                    <div style="flex:1;height:15px;background:var(--border-color);border-radius:8px;overflow:hidden;min-width:80px;">
                                        <div style="height:100%;width:<?= $percentual_carga ?>%;background:<?= $cor_carga ?>;border-radius:8px;transition:width 0.5s;"></div>
                                    </div>
                                    <?php if ($item['carga_horaria'] == $max_horas && $max_horas > 0): ?>
                                        <span style="color:#e74c3c;font-size:12px;">🏆 Mais carga</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?= (($item['status'] ?? 'ativa') == 'ativa') ? 'ativa' : 'inativa' ?>">
                                    <span class="dot"></span> <?= ucfirst($item['status'] ?? 'Ativa') ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($item['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:var(--bg-hover);font-weight:bold;">
                            <td style="text-align:right;" colspan="3">TOTAL CARGA HORÁRIA:</td>
                            <td><?= $total_horas ?>h</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== TAB 4: FAIXAS DE CARGA ===== -->
        <div class="tab-content" id="tab-faixa">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Faixa de Carga Horária</th>
                            <th>Total</th>
                            <th>Percentual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($carga_faixas)): ?>
                        <tr>
                            <td colspan="3" class="empty-state" style="text-align:center;padding:40px;color:var(--text-secondary);">
                                <div class="icon" style="font-size:48px;display:block;margin-bottom:10px;">📈</div>
                                <h4>Nenhuma disciplina encontrada</h4>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php 
                        $total_geral = array_sum(array_column($carga_faixas, 'total'));
                        $cores = ['#3498db', '#2ecc71', '#f39c12', '#e74c3c'];
                        $i = 0;
                        foreach ($carga_faixas as $item): 
                            $percentual = $total_geral > 0 ? round(($item['total'] / $total_geral) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong><?= $item['faixa'] ?></strong></td>
                            <td><strong><?= $item['total'] ?></strong></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="flex:1;height:20px;background:var(--border-color);border-radius:10px;overflow:hidden;">
                                        <div style="height:100%;width:<?= $percentual ?>%;background:<?= $cores[$i % count($cores)] ?>;border-radius:10px;transition:width 0.5s;"></div>
                                    </div>
                                    <span><?= $percentual ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php 
                        $i++;
                        endforeach; 
                        ?>
                        <tr style="background:var(--bg-hover);font-weight:bold;">
                            <td>TOTAL</td>
                            <td><?= $total_geral ?></td>
                            <td>100%</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== TAB 5: ATIVAS ===== -->
        <div class="tab-content" id="tab-ativas">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Carga Horária</th>
                            <th>Cadastro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $disciplinas_ativas_lista = array_filter($disciplinas, function($d) {
                            return ($d['status'] ?? 'ativa') == 'ativa';
                        });
                        if (empty($disciplinas_ativas_lista)): 
                        ?>
                        <tr>
                            <td colspan="5" class="empty-state" style="text-align:center;padding:40px;color:var(--text-secondary);">
                                <div class="icon" style="font-size:48px;display:block;margin-bottom:10px;">✅</div>
                                <h4>Nenhuma disciplina ativa encontrada</h4>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($disciplinas_ativas_lista as $d): ?>
                        <tr>
                            <td><?= $d['id'] ?></td>
                            <td><strong><?= htmlspecialchars($d['nome']) ?></strong></td>
                            <td><?= htmlspecialchars(substr($d['descricao'] ?? '', 0, 40)) ?><?= strlen($d['descricao'] ?? '') > 40 ? '...' : '' ?></td>
                            <td><?= $d['carga_horaria'] ?>h</td>
                            <td><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== TAB 6: ÚLTIMAS CADASTRADAS ===== -->
        <div class="tab-content" id="tab-ultimas">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Carga</th>
                            <th>Status</th>
                            <th>Cadastro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimas_disciplinas)): ?>
                        <tr>
                            <td colspan="6" class="empty-state" style="text-align:center;padding:40px;color:var(--text-secondary);">
                                <div class="icon" style="font-size:48px;display:block;margin-bottom:10px;">🆕</div>
                                <h4>Nenhuma disciplina cadastrada recentemente</h4>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($ultimas_disciplinas as $d): ?>
                        <tr>
                            <td><?= $d['id'] ?></td>
                            <td><strong><?= htmlspecialchars($d['nome']) ?></strong></td>
                            <td><?= htmlspecialchars(substr($d['descricao'] ?? '', 0, 30)) ?><?= strlen($d['descricao'] ?? '') > 30 ? '...' : '' ?></td>
                            <td><?= $d['carga_horaria'] ?>h</td>
                            <td>
                                <span class="status-badge <?= (($d['status'] ?? 'ativa') == 'ativa') ? 'ativa' : 'inativa' ?>">
                                    <span class="dot"></span> <?= ucfirst($d['status'] ?? 'Ativa') ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <?php include 'includes/footer.php'; ?>
</main>

<!-- ===== SCRIPTS ===== -->
<script>
// ========== SISTEMA DE TABS ==========
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab-btn');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => {
                t.classList.remove('active');
                t.style.background = 'transparent';
                t.style.color = 'var(--text-secondary)';
            });
            contents.forEach(c => c.classList.remove('active'));

            this.classList.add('active');
            this.style.background = 'var(--primary-gradient)';
            this.style.color = 'white';
            
            const tabId = this.dataset.tab;
            document.getElementById('tab-' + tabId).classList.add('active');

            document.getElementById('searchInput').value = '';
            mostrarTodos();
        });
    });

    // Ativar primeiro tab por padrão
    const firstTab = document.querySelector('.tab-btn');
    if (firstTab) {
        firstTab.style.background = 'var(--primary-gradient)';
        firstTab.style.color = 'white';
    }
});

// ========== SISTEMA DE BUSCA ==========
function filtrarTabela() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const activeTab = document.querySelector('.tab-content.active');
    const rows = activeTab.querySelectorAll('table tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

function limparFiltro() {
    document.getElementById('searchInput').value = '';
    mostrarTodos();
}

function mostrarTodos() {
    const activeTab = document.querySelector('.tab-content.active');
    const rows = activeTab.querySelectorAll('table tbody tr');
    rows.forEach(row => row.style.display = '');
}

document.getElementById('searchInput').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') filtrarTabela();
});

// ========== EXPORTAR EXCEL ==========
function exportarExcel() {
    const activeTab = document.querySelector('.tab-content.active');
    const table = activeTab.querySelector('table');
    
    if (!table) {
        alert('Nenhuma tabela encontrada!');
        return;
    }

    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const rowData = [];
        const cols = row.querySelectorAll('th, td');
        cols.forEach(col => {
            let text = col.textContent.trim();
            text = text.replace(/[^\w\s,.@\-()/h%]/g, '');
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(','));
    });

    const csvContent = csv.join('\n');
    const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const tabName = document.querySelector('.tab-btn.active').dataset.tab;
    link.href = url;
    link.download = `disciplinas_${tabName}_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

// ========== ATALHO CTRL+F ==========
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
});

console.log('📚 Sistema de Disciplinas carregado!');
console.log('📊 Total de disciplinas: <?= $total_disciplinas ?>');
console.log('💡 Dica: Use Ctrl+F para buscar rapidamente');
</script>

<style>
/* ===== ESTILOS ADICIONAIS ===== */
.tab-btn.active {
    background: var(--primary-gradient) !important;
    color: white !important;
}

.tab-btn:hover:not(.active) {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.tab-content {
    display: none;
    animation: fadeIn 0.3s ease;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.ativa {
    background: #d4edda;
    color: #155724;
}

.status-badge.ativa .dot {
    background: #2ecc71;
}

.status-badge.inativa {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.inativa .dot {
    background: #e74c3c;
}

.status-badge.pendente {
    background: #fff3cd;
    color: #856404;
}

.status-badge.pendente .dot {
    background: #f39c12;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.empty-state .icon {
    font-size: 48px;
    display: block;
    margin-bottom: 10px;
}

.empty-state h4 {
    margin: 0 0 5px 0;
    color: var(--text-primary);
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

#searchInput:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}
</style>