<?php
require_once '../../config/database.php';

// Verificar se é admin
if ($_SESSION['usuario_perfil'] != 'admin') {
    header("Location: " . SITE_URL);
    exit;
}

// Buscar usuários
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY created_at DESC")->fetchAll();

// Aprovar usuário
if (isset($_GET['aprovar'])) {
    $id = $_GET['aprovar'];
    $stmt = $pdo->prepare("UPDATE usuarios SET status = 'ativo', aprovado_em = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?success=1");
    exit;
}

// Bloquear/Desbloquear usuário
if (isset($_GET['bloquear'])) {
    $id = $_GET['bloquear'];
    $stmt = $pdo->prepare("UPDATE usuarios SET status = IF(status = 'bloqueado', 'ativo', 'bloqueado') WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?success=1");
    exit;
}

// Excluir usuário
if (isset($_GET['excluir'])) {
    $id = $_GET['excluir'];
    if ($id != 1) { // Não permite excluir o admin
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .header-actions { display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0; }
        .btn-gold {
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332; padding: 10px 24px; border: none; border-radius: 8px;
            font-weight: 600; cursor: pointer; transition: all 0.3s;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197,165,50,0.3); }
        .status-badge {
            display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;
        }
        .status-ativo { background: #d1fae5; color: #065f46; }
        .status-pendente { background: #fef3c7; color: #92400e; }
        .status-bloqueado { background: #fee2e2; color: #991b1b; }
        .perfil-badge {
            display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 11px; font-weight: 600;
        }
        .perfil-admin { background: #dbeafe; color: #1e40af; }
        .perfil-gerente { background: #fef3c7; color: #92400e; }
        .perfil-usuario { background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>🔐 Gerenciar Usuários</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Operação realizada com sucesso!</div>
        <?php endif; ?>
        
        <div class="header-actions">
            <a href="novo.php" class="btn-gold">➕ Novo Usuário</a>
            <a href="permissoes.php" class="btn-gold" style="background: #3498db; color: white;">🔑 Gerenciar Permissões</a>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th>Cadastro</th>
                        <th>Último Acesso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><strong><?= htmlspecialchars($user['nome']) ?></strong></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <span class="perfil-badge perfil-<?= $user['perfil'] ?>">
                                <?= ucfirst($user['perfil']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $user['status'] ?>">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        <td><?= $user['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($user['ultimo_acesso'])) : 'Nunca' ?></td>
                        <td>
                            <?php if ($user['status'] == 'pendente'): ?>
                                <a href="?aprovar=<?= $user['id'] ?>" class="btn-small" style="background: #2ecc71;" onclick="return confirm('Aprovar este usuário?')">✅ Aprovar</a>
                            <?php endif; ?>
                            <?php if ($user['id'] != 1): ?>
                                <a href="editar.php?id=<?= $user['id'] ?>" class="btn-small" style="background: #f59e0b;">Editar</a>
                                <a href="?bloquear=<?= $user['id'] ?>" class="btn-small" style="background: <?= $user['status'] == 'bloqueado' ? '#2ecc71' : '#ef4444' ?>;" onclick="return confirm('Confirmar?')">
                                    <?= $user['status'] == 'bloqueado' ? '🔓 Desbloquear' : '🔒 Bloquear' ?>
                                </a>
                                <a href="?excluir=<?= $user['id'] ?>" class="btn-small" style="background: #ef4444;" onclick="return confirm('Excluir este usuário?')">Excluir</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>