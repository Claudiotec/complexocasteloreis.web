<?php
require_once '../../config/database.php';

// Verificar se está logado
if (!isLoggedIn()) {
    header("Location: " . SITE_URL . "login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipoMensagem = '';

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header("Location: " . SITE_URL . "logout.php");
    exit;
}

// Atualizar perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    try {
        if (empty($nome) || empty($email)) {
            $mensagem = '❌ Nome e email são obrigatórios!';
            $tipoMensagem = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = '❌ Email inválido!';
            $tipoMensagem = 'error';
        } else {
            $erro = false;
            
            // Verificar email
            if ($email != $usuario['email']) {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$email, $usuario_id]);
                if ($stmt->fetch()) {
                    $mensagem = '❌ Este email já está em uso!';
                    $tipoMensagem = 'error';
                    $erro = true;
                }
            }
            
            // Verificar telefone
            if ($telefone && $telefone != $usuario['telefone']) {
                $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone);
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = ? AND id != ?");
                $stmt->execute([$telefone_limpo, $usuario_id]);
                if ($stmt->fetch()) {
                    $mensagem = '❌ Este telefone já está em uso!';
                    $tipoMensagem = 'error';
                    $erro = true;
                }
            }
            
            // Verificar senha
            if (!empty($nova_senha)) {
                if (empty($senha_atual)) {
                    $mensagem = '❌ Digite a senha atual para alterar!';
                    $tipoMensagem = 'error';
                    $erro = true;
                } elseif (!password_verify($senha_atual, $usuario['senha'])) {
                    $mensagem = '❌ Senha atual incorreta!';
                    $tipoMensagem = 'error';
                    $erro = true;
                } elseif (strlen($nova_senha) < 6) {
                    $mensagem = '❌ A nova senha deve ter pelo menos 6 caracteres!';
                    $tipoMensagem = 'error';
                    $erro = true;
                } elseif ($nova_senha !== $confirmar_senha) {
                    $mensagem = '❌ As senhas não coincidem!';
                    $tipoMensagem = 'error';
                    $erro = true;
                }
            }
            
            if (!$erro) {
                $sql = "UPDATE usuarios SET nome = ?, email = ?, telefone = ?";
                $params = [$nome, $email, $telefone];
                
                if (!empty($nova_senha)) {
                    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $sql .= ", senha = ?";
                    $params[] = $senha_hash;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $usuario_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $_SESSION['usuario_nome'] = $nome;
                $_SESSION['usuario_email'] = $email;
                $_SESSION['usuario_telefone'] = $telefone;
                
                $mensagem = '✅ Perfil atualizado com sucesso!';
                $tipoMensagem = 'success';
                
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$usuario_id]);
                $usuario = $stmt->fetch();
            }
        }
    } catch(PDOException $e) {
        $mensagem = '❌ Erro ao atualizar: ' . $e->getMessage();
        $tipoMensagem = 'error';
    }
}

// Estatísticas
$totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$totalPendentes = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'pendente'")->fetchColumn();
$totalAtivos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo'")->fetchColumn();
$totalBloqueados = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'bloqueado'")->fetchColumn();
$totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$totalProdutos = $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
$totalFaturas = $pdo->query("SELECT COUNT(*) FROM faturas_proforma")->fetchColumn();
$totalFuncionarios = $pdo->query("SELECT COUNT(*) FROM funcionarios")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .profile-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 25px;
        }
        .profile-sidebar {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            text-align: center;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 15px;
            color: #1a2332;
        }
        .profile-name {
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
        }
        .profile-email {
            color: #94a3b8;
            font-size: 14px;
        }
        .profile-role {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }
        .profile-role.admin {
            background: #dbeafe;
            color: #1e40af;
        }
        .profile-role.gerente {
            background: #fef3c7;
            color: #92400e;
        }
        .profile-role.usuario {
            background: #f1f5f9;
            color: #475569;
        }
        .profile-status {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
        }
        .profile-card h3 {
            color: #1a2332;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-group input:focus {
            border-color: #c9a84c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(197,165,50,0.1);
        }
        .form-group .hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(197,165,50,0.3);
        }
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .divider {
            border-top: 1px solid #f1f5f9;
            margin: 20px 0;
        }
        
        /* Menu Admin */
        .admin-menu {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }
        .admin-menu a {
            padding: 12px 10px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            text-align: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #4a5568;
        }
        .admin-menu a:hover {
            background: #c9a84c;
            color: #1a2332;
            border-color: #c9a84c;
            transform: translateY(-2px);
        }
        .admin-menu a .icon {
            font-size: 24px;
            display: block;
            margin-bottom: 4px;
        }
        .admin-menu a .label {
            font-size: 12px;
        }
        
        /* Estatísticas */
        .stats-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 15px;
        }
        .stats-mini .item {
            background: #f8fafc;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }
        .stats-mini .item .num {
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
        }
        .stats-mini .item .label {
            font-size: 10px;
            color: #94a3b8;
        }
        .stats-mini .item.pendente .num { color: #f59e0b; }
        .stats-mini .item.ativo .num { color: #2ecc71; }
        .stats-mini .item.bloqueado .num { color: #ef4444; }
        
        .pendentes-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            text-align: center;
        }
        .pendentes-box .pendentes-num {
            font-size: 28px;
            font-weight: 700;
            color: #92400e;
        }
        .pendentes-box .pendentes-label {
            font-size: 13px;
            color: #92400e;
        }
        .pendentes-box a {
            color: #92400e;
            font-weight: 600;
            text-decoration: none;
        }
        .pendentes-box a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .admin-menu {
                grid-template-columns: 1fr 1fr;
            }
            .stats-mini {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 480px) {
            .admin-menu {
                grid-template-columns: 1fr;
            }
            .stats-mini {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="profile-container">
        <h2>👤 Meu Perfil</h2>
        
        <div class="profile-grid">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar">👤</div>
                <div class="profile-name"><?= htmlspecialchars($usuario['nome']) ?></div>
                <div class="profile-email"><?= htmlspecialchars($usuario['email']) ?></div>
                <span class="profile-role <?= $usuario['perfil'] ?>">
                    <?= ucfirst($usuario['perfil']) ?>
                </span>
                <div class="profile-status">Status: <?= ucfirst($usuario['status']) ?></div>
                
                <div class="divider"></div>
                
                <?php if ($usuario['perfil'] == 'admin'): ?>
                <!-- Menu Admin -->
                <h4 style="color: #1a2332; margin-bottom: 10px; text-align: left;">🔐 Administração</h4>
                <div class="admin-menu">
                    <a href="../usuarios/">
                        <span class="icon">👥</span>
                        <span class="label">Gerenciar Usuários</span>
                    </a>
                    <a href="../usuarios/permissoes.php">
                        <span class="icon">🔑</span>
                        <span class="label">Permissões</span>
                    </a>
                    <a href="../empresa/">
                        <span class="icon">🏢</span>
                        <span class="label">Dados da Empresa</span>
                    </a>
                    <a href="../usuarios/novo.php">
                        <span class="icon">➕</span>
                        <span class="label">Novo Usuário</span>
                    </a>
                </div>
                
                <!-- Pendentes -->
                <?php if ($totalPendentes > 0): ?>
                <div class="pendentes-box">
                    <div class="pendentes-num"><?= $totalPendentes ?></div>
                    <div class="pendentes-label">⏳ Usuários pendentes de aprovação</div>
                    <a href="../usuarios/">Clique aqui para aprovar</a>
                </div>
                <?php endif; ?>
                
                <!-- Estatísticas Rápidas -->
                <div class="divider"></div>
                <h4 style="color: #1a2332; margin-bottom: 10px; text-align: left;">📊 Estatísticas</h4>
                <div class="stats-mini">
                    <div class="item">
                        <div class="num"><?= $totalUsuarios ?></div>
                        <div class="label">Usuários</div>
                    </div>
                    <div class="item pendente">
                        <div class="num"><?= $totalPendentes ?></div>
                        <div class="label">Pendentes</div>
                    </div>
                    <div class="item ativo">
                        <div class="num"><?= $totalAtivos ?></div>
                        <div class="label">Ativos</div>
                    </div>
                    <div class="item bloqueado">
                        <div class="num"><?= $totalBloqueados ?></div>
                        <div class="label">Bloqueados</div>
                    </div>
                    <div class="item">
                        <div class="num"><?= $totalClientes ?></div>
                        <div class="label">Clientes</div>
                    </div>
                    <div class="item">
                        <div class="num"><?= $totalProdutos ?></div>
                        <div class="label">Produtos</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Formulário -->
            <div class="profile-card">
                <h3>✏️ Editar Perfil</h3>
                
                <?php if ($mensagem): ?>
                    <div class="alert alert-<?= $tipoMensagem ?>"><?= $mensagem ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="tel" name="telefone" value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>" placeholder="946646242">
                        <div class="hint">Digite apenas os 9 dígitos (ex: 946646242)</div>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <h4 style="color: #1a2332; margin-bottom: 15px;">🔒 Alterar Senha</h4>
                    
                    <div class="form-group">
                        <label>Senha Atual</label>
                        <input type="password" name="senha_atual" placeholder="Digite sua senha atual">
                    </div>
                    
                    <div class="form-group">
                        <label>Nova Senha</label>
                        <input type="password" name="nova_senha" placeholder="Mínimo 6 caracteres">
                    </div>
                    
                    <div class="form-group">
                        <label>Confirmar Nova Senha</label>
                        <input type="password" name="confirmar_senha" placeholder="Digite a nova senha novamente">
                    </div>
                    
                    <button type="submit" class="btn-gold">💾 Salvar Alterações</button>
                    <a href="<?= SITE_URL ?>" class="btn">← Voltar</a>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>