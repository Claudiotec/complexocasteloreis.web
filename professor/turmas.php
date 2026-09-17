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
// DADOS DO USUÁRIO
// ==========================================
$nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$email = $_SESSION['usuario_email'] ?? '';
$page_title = 'Minhas Turmas';
$active_page = 'turmas';

// ==========================================
// BUSCAR PROFESSOR E SUAS TURMAS
// ==========================================
$professor_num_agente = null;
$professor_nome_completo = '';
$turmas = [];
$total_turmas = 0;
$total_alunos = 0;
$erro_busca = '';

try {
    // PASSO 1: Buscar o professor pelo email
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE email = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$email]);
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($professor) {
            $professor_num_agente = $professor['num_agente'];
            $professor_nome_completo = $professor['nome'];
            
            // Se num_agente estiver vazio, usar o id
            if (empty($professor_num_agente)) {
                $professor_num_agente = $professor['id'];
            }
        } else {
            $erro_busca = 'Professor não encontrado na tabela funcionarios';
        }
    } else {
        $erro_busca = 'Email do professor não encontrado na sessão';
    }
    
    // PASSO 2: Buscar as turmas do professor usando o num_agente
    if ($professor_num_agente) {
        // Buscar distribuições - USANDO professor_id = num_agente
        $stmt = $pdo->prepare("
            SELECT * FROM destribuicao_professores 
            WHERE professor_id = ? 
            AND tipo = 'PROFESSOR'
            ORDER BY turma_nome
        ");
        $stmt->execute([$professor_num_agente]);
        $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Se não encontrou pelo num_agente, tentar pelo nome
        if (empty($distribuicoes) && !empty($professor_nome_completo)) {
            $stmt = $pdo->prepare("
                SELECT * FROM destribuicao_professores 
                WHERE professor_nome LIKE ? 
                AND tipo = 'PROFESSOR'
                ORDER BY turma_nome
            ");
            $stmt->execute(['%' . $professor_nome_completo . '%']);
            $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Processar as distribuições encontradas
        foreach ($distribuicoes as $dist) {
            // Buscar alunos da turma
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM alunos 
                WHERE TURMA = ? AND status = 'ativo'
            ");
            $stmt->execute([$dist['turma_nome']]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Buscar alunos detalhados - REMOVIDO CAMPO 'numero'
            $stmt = $pdo->prepare("
                SELECT id, nome, genero, data_nascimento 
                FROM alunos 
                WHERE TURMA = ? AND status = 'ativo'
                ORDER BY nome
            ");
            $stmt->execute([$dist['turma_nome']]);
            $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Converter disciplinas para array
            $disciplinas_array = array_map('trim', explode(',', $dist['disciplinas']));
            
            $turmas[] = [
                'id' => $dist['turma_id'],
                'nome' => $dist['turma_nome'],
                'classe' => $dist['classe'],
                'disciplinas' => $dist['disciplinas'],
                'disciplinas_array' => $disciplinas_array,
                'ano_letivo' => $dist['ano_letivo'],
                'total_alunos' => $total['total'] ?? 0,
                'alunos' => $alunos
            ];
            
            $total_alunos += $total['total'] ?? 0;
        }
        
        $total_turmas = count($turmas);
    }
    
} catch (PDOException $e) {
    $erro_busca = 'Erro no banco de dados: ' . $e->getMessage();
    error_log("Erro ao buscar turmas: " . $e->getMessage());
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
                <h2>🏫 Minhas Turmas</h2>
                <p>Visualize todas as turmas e alunos sob sua responsabilidade</p>
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
                <h2 style="font-size:22px;font-weight:700;">👋 <?= htmlspecialchars($nome) ?></h2>
                <div style="color:var(--text-light);font-size:14px;">📚 Professor • <?= ucfirst($perfil) ?></div>
                <div style="color:var(--primary);font-size:13px;margin-top:4px;">
                    <i class="far fa-calendar-alt"></i> <?= $data_atual ?>
                </div>
                <?php if ($professor_num_agente): ?>
                <div style="color:var(--text-light);font-size:12px;margin-top:2px;">
                    <i class="fas fa-id-badge"></i> Nº Agente: <strong><?= $professor_num_agente ?></strong>
                </div>
                <?php endif; ?>
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
            </div>
        </div>
    </div>

    <!-- ===== DEBUG INFO ===== -->
    <?php if ($erro_busca): ?>
    <div class="card animate-fade-up delay-2" style="background:#fff3cd;border:1px solid #ffeaa7;">
        <div style="padding:15px;color:#856404;">
            <strong>⚠️ Debug:</strong> <?= $erro_busca ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($professor_num_agente && empty($turmas)): ?>
        <!-- ===== SEM TURMAS - MOSTRAR INFO DE DEBUG ===== -->
        <div class="card animate-fade-up delay-2">
            <div class="empty-state" style="padding:40px 20px;text-align:center;">
                <div class="icon" style="font-size:64px;margin-bottom:20px;">📭</div>
                <h3 style="color:var(--text-primary);margin-bottom:10px;">Nenhuma turma atribuída</h3>
                <p style="color:var(--text-secondary);max-width:400px;margin:0 auto;">
                    Você ainda não possui turmas vinculadas. 
                    Entre em contato com o coordenador pedagógico para fazer a distribuição.
                </p>
                
                <!-- INFO DE DEBUG -->
                <div style="margin-top:20px;padding:15px;background:#f8f9fa;border-radius:8px;text-align:left;max-width:500px;margin-left:auto;margin-right:auto;">
                    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:5px;">
                        <strong>🔍 Informações de Debug:</strong>
                    </p>
                    <p style="font-size:12px;color:var(--text-secondary);margin:3px 0;">
                        📧 Email: <?= htmlspecialchars($email) ?>
                    </p>
                    <p style="font-size:12px;color:var(--text-secondary);margin:3px 0;">
                        🆔 Nº Agente: <?= $professor_num_agente ?>
                    </p>
                    <p style="font-size:12px;color:var(--text-secondary);margin:3px 0;">
                        👤 Nome: <?= htmlspecialchars($professor_nome_completo) ?>
                    </p>
                    <p style="font-size:12px;color:var(--text-secondary);margin:3px 0;">
                        📊 Total de turmas encontradas: <?= $total_turmas ?>
                    </p>
                </div>
            </div>
        </div>
    <?php elseif (!empty($turmas)): ?>
        <!-- ===== LISTA DE TURMAS ===== -->
        <?php foreach ($turmas as $turma): ?>
        <div class="card animate-fade-up delay-2" style="margin-bottom:20px;">
            <div class="card-header" style="background:var(--primary-gradient);color:white;border-radius:12px 12px 0 0;padding:15px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <div>
                        <h3 style="font-size:18px;font-weight:700;color:white;">
                            🏫 <?= htmlspecialchars($turma['nome']) ?>
                        </h3>
                        <div style="font-size:13px;color:rgba(255,255,255,0.8);">
                            <?= htmlspecialchars($turma['classe']) ?> • 
                            Ano: <?= htmlspecialchars($turma['ano_letivo']) ?>
                        </div>
                    </div>
                    <div style="display:flex;gap:15px;flex-wrap:wrap;">
                        <div style="text-align:center;padding:5px 12px;background:rgba(255,255,255,0.15);border-radius:8px;">
                            <div style="font-size:18px;font-weight:700;"><?= $turma['total_alunos'] ?></div>
                            <div style="font-size:10px;text-transform:uppercase;">Alunos</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body" style="padding:20px;">
                <!-- Disciplinas -->
                <div style="margin-bottom:15px;display:flex;flex-wrap:wrap;gap:8px;">
                    <span style="font-weight:600;font-size:14px;color:var(--text-primary);margin-right:10px;">📚 Disciplinas:</span>
                    <?php foreach ($turma['disciplinas_array'] as $disc): ?>
                        <?php if (!empty($disc)): ?>
                        <span style="background:#f1f5f9;padding:4px 12px;border-radius:20px;font-size:13px;color:var(--text-primary);">
                            <?= htmlspecialchars($disc) ?>
                        </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <!-- Lista de Alunos -->
                <?php if (!empty($turma['alunos'])): ?>
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nome</th>
                                <th>Gênero</th>
                                <th>Data Nasc.</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $count = 1; foreach ($turma['alunos'] as $aluno): ?>
                            <tr>
                                <td><?= $count++ ?></td>
                                <td><strong><?= htmlspecialchars($aluno['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($aluno['genero'] ?? '-') ?></td>
                                <td><?= $aluno['data_nascimento'] ? date('d/m/Y', strtotime($aluno['data_nascimento'])) : '-' ?></td>
                                <td>
                                    <a href="aluno_detalhes.php?id=<?= $aluno['id'] ?>" class="action-btn" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="frequencia_aluno.php?id=<?= $aluno['id'] ?>" class="action-btn" title="Ver frequência">
                                        <i class="fas fa-calendar-check"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="text-align:center;padding:20px;color:var(--text-secondary);">
                    <p>Nenhum aluno matriculado nesta turma.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ===== FOOTER ===== -->
    <?php include 'includes/footer.php'; ?>
</main>

<style>
/* Estilos adicionais para a página de turmas */
.table-custom {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.table-custom thead {
    background: #f1f5f9;
}

.table-custom th {
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary);
    border-bottom: 2px solid #e2e8f0;
}

.table-custom td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    color: var(--text-secondary);
}

.table-custom tbody tr:hover {
    background: #f8fafc;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.action-btn:hover {
    background: var(--primary);
    color: white;
}

.action-btn i {
    font-size: 14px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state .icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.table-responsive {
    overflow-x: auto;
}

/* Responsivo */
@media (max-width: 768px) {
    .table-custom {
        font-size: 12px;
    }
    
    .table-custom th,
    .table-custom td {
        padding: 6px 8px;
    }
    
    .action-btn {
        width: 28px;
        height: 28px;
    }
    
    .card-header {
        padding: 12px 15px;
    }
    
    .card-body {
        padding: 15px;
    }
}
</style>