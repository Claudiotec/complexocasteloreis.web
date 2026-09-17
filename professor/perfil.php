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
$usuario_id = $_SESSION['usuario_id'];
$nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$email = $_SESSION['usuario_email'] ?? '';
$page_title = 'Meu Perfil';
$active_page = 'perfil';

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
// BUSCAR DADOS DO PROFESSOR (funcionarios)
// ==========================================
$funcionario = null;
$telefone = '';
$data_nascimento = '';
$endereco = '';
$foto = '';
$num_bi = '';
$genero = '';
$data_admissao = '';
$cargo = '';
$departamento = '';
$salario = 0;
$habilitacoes = '';
$municipio = '';

try {
    // Buscar dados do funcionário pelo email
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($funcionario) {
        $telefone = $funcionario['telefone'] ?? '';
        $data_nascimento = $funcionario['data_nascimento'] ?? '';
        $endereco = $funcionario['endereco'] ?? '';
        $foto = $funcionario['foto'] ?? '';
        $num_bi = $funcionario['num_bi'] ?? '';
        $genero = $funcionario['genero'] ?? '';
        $data_admissao = $funcionario['data_admissao'] ?? '';
        $cargo = $funcionario['cargo'] ?? '';
        $departamento = $funcionario['departamento'] ?? '';
        $salario = $funcionario['salario'] ?? 0;
        $habilitacoes = $funcionario['habilitacoes_literarias'] ?? '';
        $municipio = $funcionario['municipio_residencia'] ?? '';
    }
} catch (Exception $e) {}

// ==========================================
// BUSCAR ESTATÍSTICAS DO PROFESSOR
// ==========================================
$total_turmas = 0;
$total_alunos = 0;
$total_disciplinas = 0;
$turmas_lista = [];

if ($funcionario && isset($funcionario['id'])) {
    $funcionario_id = $funcionario['id'];
    
    try {
        // Turmas (da tabela destribuicao_professores)
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR'");
        $stmt->execute([$funcionario_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_turmas = $result['total'] ?? 0;
        
        // Buscar detalhes das turmas
        $stmt = $pdo->prepare("SELECT * FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR'");
        $stmt->execute([$funcionario_id]);
        $turmas_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Disciplinas (da tabela disciplinas)
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM disciplinas WHERE professor_id = ? AND status = 'ativa'");
        $stmt->execute([$funcionario_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_disciplinas = $result['total'] ?? 0;
        
        // Alunos (buscar da tabela alunos usando as turmas)
        if (!empty($turmas_lista)) {
            $turmas_nomes = array_column($turmas_lista, 'turma_nome');
            $placeholders = implode(',', array_fill(0, count($turmas_nomes), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM alunos WHERE TURMA IN ($placeholders) AND status = 'ativo'");
            $stmt->execute($turmas_nomes);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_alunos = $result['total'] ?? 0;
        }
    } catch (Exception $e) {}
}

// ==========================================
// PROCESSAR ATUALIZAÇÃO DE PERFIL
// ==========================================
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    
    if ($acao == 'atualizar_perfil') {
        $novo_nome = trim($_POST['nome'] ?? '');
        $novo_telefone = trim($_POST['telefone'] ?? '');
        $novo_endereco = trim($_POST['endereco'] ?? '');
        $novo_municipio = trim($_POST['municipio'] ?? '');
        $novo_bi = trim($_POST['num_bi'] ?? '');
        $novo_genero = trim($_POST['genero'] ?? '');
        
        try {
            $stmt = $pdo->prepare("
                UPDATE funcionarios 
                SET nome = ?, telefone = ?, endereco = ?, municipio_residencia = ?, num_bi = ?, genero = ?
                WHERE email = ?
            ");
            $stmt->execute([$novo_nome, $novo_telefone, $novo_endereco, $novo_municipio, $novo_bi, $novo_genero, $email]);
            
            // Atualizar sessão
            $_SESSION['usuario_nome'] = $novo_nome;
            $nome = $novo_nome;
            
            $mensagem = 'Perfil atualizado com sucesso!';
            $tipo_mensagem = 'success';
            
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($funcionario) {
                $telefone = $funcionario['telefone'] ?? '';
                $endereco = $funcionario['endereco'] ?? '';
                $municipio = $funcionario['municipio_residencia'] ?? '';
                $num_bi = $funcionario['num_bi'] ?? '';
                $genero = $funcionario['genero'] ?? '';
            }
        } catch (Exception $e) {
            $mensagem = 'Erro ao atualizar perfil: ' . $e->getMessage();
            $tipo_mensagem = 'danger';
        }
    }
    
    if ($acao == 'alterar_senha') {
        $senha_atual = $_POST['senha_atual'] ?? '';
        $nova_senha = $_POST['nova_senha'] ?? '';
        $confirmar_senha = $_POST['confirmar_senha'] ?? '';
        
        if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
            $mensagem = 'Preencha todos os campos de senha.';
            $tipo_mensagem = 'danger';
        } elseif ($nova_senha != $confirmar_senha) {
            $mensagem = 'As senhas não coincidem.';
            $tipo_mensagem = 'danger';
        } elseif (strlen($nova_senha) < 6) {
            $mensagem = 'A nova senha deve ter pelo menos 6 caracteres.';
            $tipo_mensagem = 'danger';
        } else {
            try {
                // Verificar senha atual
                $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
                $stmt->execute([$usuario_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($senha_atual, $user['senha'])) {
                    $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                    $stmt->execute([$nova_senha_hash, $usuario_id]);
                    
                    $mensagem = 'Senha alterada com sucesso!';
                    $tipo_mensagem = 'success';
                } else {
                    $mensagem = 'Senha atual incorreta.';
                    $tipo_mensagem = 'danger';
                }
            } catch (Exception $e) {
                $mensagem = 'Erro ao alterar senha: ' . $e->getMessage();
                $tipo_mensagem = 'danger';
            }
        }
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
                <h2>👤 Meu Perfil</h2>
                <p>Gerencie suas informações pessoais</p>
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

    <!-- ===== MENSAGENS ===== -->
    <?php if (!empty($mensagem)): ?>
    <div class="alert alert-<?= $tipo_mensagem ?> animate-fade-up" style="padding:12px 18px;border-radius:8px;margin-bottom:20px;<?= $tipo_mensagem == 'success' ? 'background:#d4edda;color:#155724;border:1px solid #c3e6cb;' : 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;' ?>">
        <i class="fas fa-<?= $tipo_mensagem == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($mensagem) ?>
    </div>
    <?php endif; ?>

    <!-- ===== WELCOME ===== -->
    <div class="card animate-fade-up delay-1" style="background:var(--secondary);color:white;border:none;">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            <div style="width:80px;height:80px;background:var(--primary-gradient);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:var(--secondary);flex-shrink:0;">
                <?php if (!empty($foto)): ?>
                    <img src="<?= htmlspecialchars($foto) ?>" alt="Foto" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <?= strtoupper(substr($nome, 0, 2)) ?>
                <?php endif; ?>
            </div>
            <div>
                <h2 style="font-size:24px;font-weight:700;"><?= htmlspecialchars($nome) ?></h2>
                <div style="color:var(--text-light);font-size:14px;">📚 <?= ucfirst($perfil) ?> • <?= htmlspecialchars($email) ?></div>
                <div style="color:var(--primary);font-size:13px;margin-top:4px;">
                    <i class="far fa-calendar-alt"></i> <?= $data_atual ?>
                </div>
                <?php if (!empty($cargo) || !empty($departamento)): ?>
                <div style="color:var(--text-light);font-size:13px;margin-top:4px;">
                    <?= !empty($cargo) ? '🎯 ' . htmlspecialchars($cargo) : '' ?>
                    <?= !empty($departamento) ? ' • 📁 ' . htmlspecialchars($departamento) : '' ?>
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
                <div style="text-align:center;padding:8px 16px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
                    <div style="font-size:20px;font-weight:700;color:var(--primary);"><?= $total_disciplinas ?></div>
                    <div style="font-size:10px;color:var(--text-light);text-transform:uppercase;">Disciplinas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== PERFIL ===== -->
    <div class="grid-2 animate-fade-up delay-2">
        <!-- ===== DADOS PESSOAIS ===== -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Dados Pessoais</h3>
                <span class="badge-count">Editar</span>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="acao" value="atualizar_perfil">
                
                <div style="display:grid;gap:15px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Nome Completo</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" required style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:var(--input-bg);color:var(--text-primary);transition:border-color 0.3s;">
                    </div>
                    
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Email</label>
                        <input type="email" value="<?= htmlspecialchars($email) ?>" disabled style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:#f0f0f0;color:var(--text-secondary);cursor:not-allowed;">
                        <small style="color:var(--text-secondary);font-size:12px;">O email não pode ser alterado</small>
                    </div>
                    
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Telefone</label>
                        <input type="text" name="telefone" value="<?= htmlspecialchars($telefone) ?>" placeholder="Digite seu telefone" style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:var(--input-bg);color:var(--text-primary);transition:border-color 0.3s;">
                    </div>
                    
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Endereço</label>
                        <input type="text" name="endereco" value="<?= htmlspecialchars($endereco) ?>" placeholder="Digite seu endereço" style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:var(--input-bg);color:var(--text-primary);transition:border-color 0.3s;">
                    </div>
                    
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Município de Residência</label>
                        <input type="text" name="municipio" value="<?= htmlspecialchars($municipio) ?>" placeholder="Digite seu município" style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:var(--input-bg);color:var(--text-primary);transition:border-color 0.3s;">
                    </div>
                    
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Nº de BI</label>
                        <input type="text" name="num_bi" value="<?= htmlspecialchars($num_bi) ?>" placeholder="Número do BI" style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:var(--input-bg);color:var(--text-primary);transition:border-color 0.3s;">
                    </div>
                    
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Gênero</label>
                        <select name="genero" style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:var(--input-bg);color:var(--text-primary);">
                            <option value="">Selecione</option>
                            <option value="M" <?= $genero == 'M' ? 'selected' : '' ?>>Masculino</option>
                            <option value="F" <?= $genero == 'F' ? 'selected' : '' ?>>Feminino</option>
                            <option value="Outro" <?= $genero == 'Outro' ? 'selected' : '' ?>>Outro</option>
                        </select>
                    </div>
                    
                    <?php if (!empty($data_nascimento)): ?>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Data de Nascimento</label>
                        <input type="text" value="<?= date('d/m/Y', strtotime($data_nascimento)) ?>" disabled style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:#f0f0f0;color:var(--text-secondary);cursor:not-allowed;">
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($data_admissao)): ?>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Data de Admissão</label>
                        <input type="text" value="<?= date('d/m/Y', strtotime($data_admissao)) ?>" disabled style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:#f0f0f0;color:var(--text-secondary);cursor:not-allowed;">
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($habilitacoes)): ?>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Habilitações Literárias</label>
                        <input type="text" value="<?= htmlspecialchars($habilitacoes) ?>" disabled style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:#f0f0f0;color:var(--text-secondary);cursor:not-allowed;">
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary" style="padding:12px 24px;background:var(--primary-gradient);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;transition:all 0.3s;font-size:15px;">
                        <i class="fas fa-save"></i> Atualizar Perfil
                    </button>
                </div>
            </form>
        </div>

        <!-- ===== ALTERAR SENHA ===== -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-lock"></i> Segurança</h3>
                <span class="badge-count">Alterar Senha</span>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="acao" value="alterar_senha">
                
                <div style="display:grid;gap:15px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Senha Atual</label>
                        <input type="password" name="senha_atual" required placeholder="Digite sua senha atual" style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:var(--input-bg);color:var(--text-primary);transition:border-color 0.3s;">
                    </div>
                    
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Nova Senha</label>
                        <input type="password" name="nova_senha" required placeholder="Digite a nova senha (mínimo 6 caracteres)" style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:var(--input-bg);color:var(--text-primary);transition:border-color 0.3s;">
                    </div>
                    
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">Confirmar Nova Senha</label>
                        <input type="password" name="confirmar_senha" required placeholder="Confirme a nova senha" style="width:100%;padding:10px 14px;border:2px solid var(--border-color);border-radius:8px;font-size:14px;background:var(--input-bg);color:var(--text-primary);transition:border-color 0.3s;">
                    </div>
                    
                    <div style="padding:12px;background:#fff3cd;border-radius:8px;border:1px solid #ffeaa7;color:#856404;font-size:13px;">
                        <i class="fas fa-info-circle"></i>
                        A senha deve ter pelo menos 6 caracteres.
                    </div>
                    
                    <button type="submit" class="btn btn-warning" style="padding:12px 24px;background:#f39c12;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;transition:all 0.3s;font-size:15px;">
                        <i class="fas fa-key"></i> Alterar Senha
                    </button>
                </div>
            </form>
            
            <hr style="margin:25px 0;border:1px solid var(--border-color);">
            
            <!-- ===== INFORMAÇÕES ADICIONAIS ===== -->
            <div>
                <h4 style="font-size:15px;font-weight:600;color:var(--text-secondary);margin-bottom:15px;">
                    <i class="fas fa-chart-bar"></i> Resumo Acadêmico
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div style="padding:12px;background:var(--bg-hover);border-radius:8px;text-align:center;">
                        <div style="font-size:22px;font-weight:700;color:var(--primary);"><?= $total_turmas ?></div>
                        <div style="font-size:12px;color:var(--text-secondary);">Turmas</div>
                    </div>
                    <div style="padding:12px;background:var(--bg-hover);border-radius:8px;text-align:center;">
                        <div style="font-size:22px;font-weight:700;color:#2ecc71;"><?= $total_alunos ?></div>
                        <div style="font-size:12px;color:var(--text-secondary);">Alunos</div>
                    </div>
                    <div style="padding:12px;background:var(--bg-hover);border-radius:8px;text-align:center;">
                        <div style="font-size:22px;font-weight:700;color:#3498db;"><?= $total_disciplinas ?></div>
                        <div style="font-size:12px;color:var(--text-secondary);">Disciplinas</div>
                    </div>
                    <div style="padding:12px;background:var(--bg-hover);border-radius:8px;text-align:center;">
                        <div style="font-size:22px;font-weight:700;color:#f39c12;"><?= number_format($salario, 2, ',', '.') ?> Kz</div>
                        <div style="font-size:12px;color:var(--text-secondary);">Salário</div>
                    </div>
                </div>
            </div>

            <?php if (!empty($turmas_lista)): ?>
            <hr style="margin:20px 0;border:1px solid var(--border-color);">
            <div>
                <h4 style="font-size:15px;font-weight:600;color:var(--text-secondary);margin-bottom:10px;">
                    <i class="fas fa-users"></i> Minhas Turmas
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <?php foreach ($turmas_lista as $turma): ?>
                        <div style="padding:8px 12px;background:var(--bg-hover);border-radius:6px;font-size:13px;">
                            <strong><?= htmlspecialchars($turma['turma_nome']) ?></strong>
                            <span style="color:var(--text-secondary);font-size:11px;">(<?= htmlspecialchars($turma['classe']) ?>)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <?php include 'includes/footer.php'; ?>
</main>

<style>
/* ===== ESTILOS ADICIONAIS ===== */
input:focus, select:focus {
    outline: none;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

input[disabled] {
    opacity: 0.7;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(243, 156, 18, 0.4);
}

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 768px) {
    .grid-2 {
        grid-template-columns: 1fr;
    }
}

.alert {
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert i {
    font-size: 18px;
}

.badge-count {
    background: var(--primary-gradient);
    color: white;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
</style>